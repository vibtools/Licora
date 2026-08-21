#include <curl/curl.h>
#include <nlohmann/json.hpp>
#include <openssl/evp.h>
#include <openssl/ec.h>
#include <openssl/pem.h>
#include <openssl/rand.h>
#include <openssl/sha.h>

#include <algorithm>
#include <cstdlib>
#include <ctime>
#include <iomanip>
#include <iostream>
#include <memory>
#include <sstream>
#include <stdexcept>
#include <string>
#include <vector>

// Licora Secure API v2 lifecycle reference.
// Dependencies: libcurl, OpenSSL 3.x, nlohmann/json.
// Production clients must persist the P-256 private key and rotated refresh
// token securely and verify LICORA-V2/RS256 tokens with the pinned server
// public key before trusting token claims locally.

using json = nlohmann::json;
using PKey = std::unique_ptr<EVP_PKEY, decltype(&EVP_PKEY_free)>;

static std::string b64url(const unsigned char* data, size_t size) {
    std::string out(4 * ((size + 2) / 3), '\0');
    int n = EVP_EncodeBlock(reinterpret_cast<unsigned char*>(out.data()), data, static_cast<int>(size));
    out.resize(static_cast<size_t>(n));
    std::replace(out.begin(), out.end(), '+', '-');
    std::replace(out.begin(), out.end(), '/', '_');
    while (!out.empty() && out.back() == '=') out.pop_back();
    return out;
}

static std::vector<unsigned char> b64url_decode(std::string value) {
    std::replace(value.begin(), value.end(), '-', '+');
    std::replace(value.begin(), value.end(), '_', '/');
    while (value.size() % 4) value.push_back('=');
    std::vector<unsigned char> out((value.size() / 4) * 3 + 3);
    int n = EVP_DecodeBlock(out.data(), reinterpret_cast<const unsigned char*>(value.data()), static_cast<int>(value.size()));
    if (n < 0) throw std::runtime_error("invalid Base64URL");
    size_t pad = value.size() >= 2 && value[value.size()-2] == '=' ? 2 : (value.back() == '=' ? 1 : 0);
    out.resize(static_cast<size_t>(n) - pad);
    return out;
}

static std::string sha256_hex(const std::string& data) {
    unsigned char digest[SHA256_DIGEST_LENGTH];
    SHA256(reinterpret_cast<const unsigned char*>(data.data()), data.size(), digest);
    std::ostringstream out;
    for (unsigned char b : digest) out << std::hex << std::setw(2) << std::setfill('0') << static_cast<int>(b);
    return out.str();
}

static std::string url_path(const std::string& url) {
    auto scheme = url.find("://");
    auto start = scheme == std::string::npos ? 0 : scheme + 3;
    auto slash = url.find('/', start);
    if (slash == std::string::npos) return "/";
    auto end = url.find_first_of("?#", slash);
    return url.substr(slash, end == std::string::npos ? std::string::npos : end - slash);
}

static std::string random_nonce(size_t bytes = 18) {
    std::vector<unsigned char> buffer(bytes);
    if (RAND_bytes(buffer.data(), static_cast<int>(buffer.size())) != 1) throw std::runtime_error("RAND_bytes failed");
    return b64url(buffer.data(), buffer.size());
}

static PKey generate_p256() {
    EVP_PKEY_CTX* raw = EVP_PKEY_CTX_new_id(EVP_PKEY_EC, nullptr);
    if (!raw) throw std::runtime_error("EVP_PKEY_CTX_new_id failed");
    std::unique_ptr<EVP_PKEY_CTX, decltype(&EVP_PKEY_CTX_free)> ctx(raw, EVP_PKEY_CTX_free);
    if (EVP_PKEY_keygen_init(ctx.get()) <= 0 || EVP_PKEY_CTX_set_ec_paramgen_curve_nid(ctx.get(), NID_X9_62_prime256v1) <= 0) throw std::runtime_error("P-256 init failed");
    EVP_PKEY* key = nullptr;
    if (EVP_PKEY_keygen(ctx.get(), &key) <= 0 || !key) throw std::runtime_error("P-256 keygen failed");
    return PKey(key, EVP_PKEY_free);
}

static std::string public_pem(EVP_PKEY* key) {
    BIO* raw = BIO_new(BIO_s_mem());
    if (!raw) throw std::runtime_error("BIO_new failed");
    std::unique_ptr<BIO, decltype(&BIO_free)> bio(raw, BIO_free);
    if (PEM_write_bio_PUBKEY(bio.get(), key) != 1) throw std::runtime_error("public key export failed");
    BUF_MEM* memory = nullptr; BIO_get_mem_ptr(bio.get(), &memory);
    return std::string(memory->data, memory->length);
}

static std::string sign_der_b64url(EVP_PKEY* key, const std::string& canonical) {
    EVP_MD_CTX* raw = EVP_MD_CTX_new();
    if (!raw) throw std::runtime_error("EVP_MD_CTX_new failed");
    std::unique_ptr<EVP_MD_CTX, decltype(&EVP_MD_CTX_free)> ctx(raw, EVP_MD_CTX_free);
    if (EVP_DigestSignInit(ctx.get(), nullptr, EVP_sha256(), nullptr, key) <= 0 || EVP_DigestSignUpdate(ctx.get(), canonical.data(), canonical.size()) <= 0) throw std::runtime_error("ECDSA sign init failed");
    size_t length = 0;
    if (EVP_DigestSignFinal(ctx.get(), nullptr, &length) <= 0) throw std::runtime_error("ECDSA signature length failed");
    std::vector<unsigned char> signature(length);
    if (EVP_DigestSignFinal(ctx.get(), signature.data(), &length) <= 0) throw std::runtime_error("ECDSA signature failed");
    signature.resize(length); // OpenSSL ECDSA output is RFC3279 DER.
    return b64url(signature.data(), signature.size());
}

static size_t write_body(char* ptr, size_t size, size_t nmemb, void* userdata) {
    static_cast<std::string*>(userdata)->append(ptr, size * nmemb);
    return size * nmemb;
}

static std::string jwt_jti(const std::string& token) {
    auto first = token.find('.'); auto second = token.find('.', first == std::string::npos ? 0 : first + 1);
    if (first == std::string::npos || second == std::string::npos) throw std::runtime_error("malformed access token");
    auto payload = b64url_decode(token.substr(first + 1, second - first - 1));
    return json::parse(std::string(payload.begin(), payload.end())).at("jti").get<std::string>();
}

class LicoraV2Client {
public:
    LicoraV2Client(std::string baseUrl, std::string appId, std::string appVersion)
        : base_(std::move(baseUrl)), app_(std::move(appId)), version_(std::move(appVersion)), key_(generate_p256()) {
        while (!base_.empty() && base_.back() == '/') base_.pop_back();
        unsigned char random[16]; if (RAND_bytes(random, sizeof(random)) != 1) throw std::runtime_error("RAND_bytes failed");
        device_ = "cpp-" + b64url(random, sizeof(random));
        publicPem_ = public_pem(key_.get());
        curl_global_init(CURL_GLOBAL_DEFAULT);
    }
    ~LicoraV2Client() { curl_global_cleanup(); }

    json activate(const std::string& license) {
        return post("activate", {{"license_key",license},{"app_id",app_},{"app_version",version_},{"device_id",device_},{"device_public_key",publicPem_}}, "activate:" + app_);
    }
    json status(const std::string& access) { return post("status", json::object(), jwt_jti(access), access); }
    json refresh(const std::string& refreshToken) { return post("refresh", {{"refresh_token",refreshToken},{"app_version",version_}}, "refresh:" + sha256_hex(refreshToken)); }
    json deactivate(const std::string& access) { return post("deactivate", json::object(), jwt_jti(access), access); }

private:
    std::string base_, app_, version_, device_, publicPem_;
    PKey key_{nullptr, EVP_PKEY_free};

    std::string endpoint(const std::string& name) const { return base_ + "/api/v2/" + name + ".php"; }

    json post(const std::string& name, const json& payload, const std::string& context, const std::string& access = "") {
        const std::string url = endpoint(name);
        const std::string body = payload.dump();
        const auto timestamp = static_cast<long long>(std::time(nullptr));
        const std::string nonce = random_nonce();
        const std::string canonical = "POST\n" + url_path(url) + "\n" + std::to_string(timestamp) + "\n" + nonce + "\n" + sha256_hex(body) + "\n" + context;
        const std::string signature = sign_der_b64url(key_.get(), canonical);

        CURL* raw = curl_easy_init();
        if (!raw) throw std::runtime_error("curl_easy_init failed");
        std::unique_ptr<CURL, decltype(&curl_easy_cleanup)> curl(raw, curl_easy_cleanup);
        struct curl_slist* headers = nullptr;
        headers = curl_slist_append(headers, "Content-Type: application/json");
        headers = curl_slist_append(headers, ("X-Licora-Timestamp: " + std::to_string(timestamp)).c_str());
        headers = curl_slist_append(headers, ("X-Licora-Nonce: " + nonce).c_str());
        headers = curl_slist_append(headers, ("X-Licora-Device-Signature: " + signature).c_str());
        if (!access.empty()) headers = curl_slist_append(headers, ("Authorization: Bearer " + access).c_str());
        std::unique_ptr<curl_slist, decltype(&curl_slist_free_all)> headerGuard(headers, curl_slist_free_all);
        std::string response;
        curl_easy_setopt(curl.get(), CURLOPT_URL, url.c_str());
        curl_easy_setopt(curl.get(), CURLOPT_POST, 1L);
        curl_easy_setopt(curl.get(), CURLOPT_POSTFIELDS, body.data());
        curl_easy_setopt(curl.get(), CURLOPT_POSTFIELDSIZE, static_cast<long>(body.size()));
        curl_easy_setopt(curl.get(), CURLOPT_HTTPHEADER, headers);
        curl_easy_setopt(curl.get(), CURLOPT_WRITEFUNCTION, write_body);
        curl_easy_setopt(curl.get(), CURLOPT_WRITEDATA, &response);
        curl_easy_setopt(curl.get(), CURLOPT_TIMEOUT, 20L);
        CURLcode code = curl_easy_perform(curl.get());
        if (code != CURLE_OK) throw std::runtime_error(std::string("HTTP request failed: ") + curl_easy_strerror(code));
        long statusCode = 0; curl_easy_getinfo(curl.get(), CURLINFO_RESPONSE_CODE, &statusCode);
        json data = json::parse(response);
        if (!data.value("success", false)) throw std::runtime_error("Licora error " + data.value("code", "UNKNOWN") + " (HTTP " + std::to_string(statusCode) + ")");
        return data;
    }
};

int main(int argc, char** argv) {
    if (argc < 4) { std::cerr << "Usage: licora_v2_client <base-url> <app-id> <license-key> [app-version]\n"; return 2; }
    try {
        LicoraV2Client client(argv[1], argv[2], argc > 4 ? argv[4] : "1.0.0");
        std::string access;
        try {
            auto activated = client.activate(argv[3]); access = activated.at("access_token").get<std::string>(); auto refresh = activated.at("refresh_token").get<std::string>(); std::cout << "[PASS] activate\n";
            client.status(access); std::cout << "[PASS] status\n";
            auto refreshed = client.refresh(refresh); access = refreshed.at("access_token").get<std::string>(); refresh = refreshed.at("refresh_token").get<std::string>(); std::cout << "[PASS] refresh (rotated)\n";
            client.status(access); std::cout << "[PASS] status-after-refresh\n";
            client.deactivate(access); access.clear(); std::cout << "[PASS] deactivate\n";
        } catch (...) {
            if (!access.empty()) { try { client.deactivate(access); std::cerr << "[INFO] cleanup deactivate completed\n"; } catch (...) { std::cerr << "[WARN] cleanup deactivate failed\n"; } }
            throw;
        }
        return 0;
    } catch (const std::exception& e) { std::cerr << e.what() << '\n'; return 1; }
}
