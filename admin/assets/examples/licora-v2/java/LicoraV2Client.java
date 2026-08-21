import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.security.*;
import java.security.spec.ECGenParameterSpec;
import java.time.Instant;
import java.util.*;

/** Licora Secure API v2 lifecycle reference for Java 17+. */
public final class LicoraV2Client {
    private static final ObjectMapper JSON = new ObjectMapper();
    private final HttpClient http = HttpClient.newHttpClient();
    private final KeyPair deviceKey;
    private final String baseUrl;
    private final String appId;
    private final String appVersion;
    private final String deviceId = "java-" + UUID.randomUUID().toString().replace("-", "");

    public LicoraV2Client(String baseUrl, String appId, String appVersion) throws Exception {
        this.baseUrl = baseUrl.replaceAll("/+$", "");
        this.appId = appId;
        this.appVersion = appVersion;
        KeyPairGenerator generator = KeyPairGenerator.getInstance("EC");
        generator.initialize(new ECGenParameterSpec("secp256r1"));
        this.deviceKey = generator.generateKeyPair();
    }

    private static String b64url(byte[] data) { return Base64.getUrlEncoder().withoutPadding().encodeToString(data); }
    private static String sha256Hex(byte[] data) throws Exception {
        byte[] digest = MessageDigest.getInstance("SHA-256").digest(data);
        StringBuilder out = new StringBuilder();
        for (byte b : digest) out.append(String.format("%02x", b));
        return out.toString();
    }
    private String endpoint(String name) { return baseUrl + "/api/v2/" + name + ".php"; }
    private String publicPem() {
        String b64 = Base64.getMimeEncoder(64, "\n".getBytes(StandardCharsets.US_ASCII)).encodeToString(deviceKey.getPublic().getEncoded());
        return "-----BEGIN PUBLIC KEY-----\n" + b64 + "\n-----END PUBLIC KEY-----\n";
    }
    private static String jwtJti(String token) throws Exception {
        String[] parts = token.split("\\.");
        if (parts.length != 3) throw new IllegalStateException("Licora returned a malformed access token.");
        JsonNode payload = JSON.readTree(Base64.getUrlDecoder().decode(parts[1]));
        return payload.path("jti").asText();
    }

    private JsonNode post(String name, Map<String,Object> payload, String context, String accessToken) throws Exception {
        String url = endpoint(name);
        byte[] body = JSON.writeValueAsBytes(payload);
        long timestamp = Instant.now().getEpochSecond();
        byte[] nonceBytes = new byte[18]; new SecureRandom().nextBytes(nonceBytes);
        String nonce = b64url(nonceBytes);
        String canonical = String.join("\n", "POST", URI.create(url).getPath(), Long.toString(timestamp), nonce, sha256Hex(body), context);
        Signature signer = Signature.getInstance("SHA256withECDSA");
        signer.initSign(deviceKey.getPrivate());
        signer.update(canonical.getBytes(StandardCharsets.UTF_8));
        String signature = b64url(signer.sign()); // SHA256withECDSA returns RFC3279 DER.

        HttpRequest.Builder request = HttpRequest.newBuilder(URI.create(url))
            .header("Content-Type", "application/json")
            .header("X-Licora-Timestamp", Long.toString(timestamp))
            .header("X-Licora-Nonce", nonce)
            .header("X-Licora-Device-Signature", signature)
            .POST(HttpRequest.BodyPublishers.ofByteArray(body));
        if (accessToken != null && !accessToken.isBlank()) request.header("Authorization", "Bearer " + accessToken);
        HttpResponse<String> response = http.send(request.build(), HttpResponse.BodyHandlers.ofString());
        JsonNode data = JSON.readTree(response.body());
        if (!data.path("success").asBoolean(false)) throw new IllegalStateException("Licora error " + data.path("code").asText("UNKNOWN") + " (HTTP " + response.statusCode() + ")");
        return data;
    }

    public JsonNode activate(String licenseKey) throws Exception {
        Map<String,Object> body = new LinkedHashMap<>();
        body.put("license_key", licenseKey); body.put("app_id", appId); body.put("app_version", appVersion);
        body.put("device_id", deviceId); body.put("device_public_key", publicPem());
        return post("activate", body, "activate:" + appId, null);
    }
    public JsonNode status(String accessToken) throws Exception { return post("status", new LinkedHashMap<>(), jwtJti(accessToken), accessToken); }
    public JsonNode refresh(String refreshToken) throws Exception {
        Map<String,Object> body = new LinkedHashMap<>(); body.put("refresh_token", refreshToken); body.put("app_version", appVersion);
        return post("refresh", body, "refresh:" + sha256Hex(refreshToken.getBytes(StandardCharsets.UTF_8)), null);
    }
    public JsonNode deactivate(String accessToken) throws Exception { return post("deactivate", new LinkedHashMap<>(), jwtJti(accessToken), accessToken); }

    public static void main(String[] args) throws Exception {
        if (args.length < 3) { System.err.println("Usage: java LicoraV2Client <base-url> <app-id> <license-key> [app-version]"); System.exit(2); }
        LicoraV2Client client = new LicoraV2Client(args[0], args[1], args.length > 3 ? args[3] : "1.0.0");
        String accessToken = "";
        try {
            JsonNode activated = client.activate(args[2]); accessToken = activated.path("access_token").asText(); String refreshToken = activated.path("refresh_token").asText(); System.out.println("[PASS] activate");
            client.status(accessToken); System.out.println("[PASS] status");
            JsonNode refreshed = client.refresh(refreshToken); accessToken = refreshed.path("access_token").asText(); refreshToken = refreshed.path("refresh_token").asText(); System.out.println("[PASS] refresh (rotated refresh token)");
            client.status(accessToken); System.out.println("[PASS] status-after-refresh");
            client.deactivate(accessToken); accessToken = ""; System.out.println("[PASS] deactivate");
        } finally {
            if (!accessToken.isEmpty()) { try { client.deactivate(accessToken); System.out.println("[INFO] cleanup deactivate completed"); } catch (Exception ignored) { System.err.println("[WARN] cleanup deactivate failed"); } }
        }
    }
}
