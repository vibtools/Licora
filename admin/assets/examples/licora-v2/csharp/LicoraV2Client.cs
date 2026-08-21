using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

// Licora Secure API v2 lifecycle reference for .NET 8+.
// This test client creates an ephemeral device and deactivates it before exit.
// Production clients must persist the device private key and rotated refresh token
// in OS-backed secure storage, and verify LICORA-V2/RS256 tokens with the pinned
// Licora server public key before trusting token claims locally.

sealed class LicoraV2Client : IDisposable
{
    readonly HttpClient http = new();
    readonly ECDsa deviceKey = ECDsa.Create(ECCurve.NamedCurves.nistP256);
    readonly string baseUrl;
    readonly string appId;
    readonly string appVersion;
    readonly string deviceId = "dotnet-" + Guid.NewGuid().ToString("N");

    public LicoraV2Client(string baseUrl, string appId, string appVersion)
    {
        this.baseUrl = baseUrl.TrimEnd('/');
        this.appId = appId;
        this.appVersion = appVersion;
    }

    static string Base64Url(byte[] data) => Convert.ToBase64String(data).TrimEnd('=').Replace('+', '-').Replace('/', '_');
    static byte[] Utf8(string value) => Encoding.UTF8.GetBytes(value);
    static string Sha256Hex(byte[] value) => Convert.ToHexString(SHA256.HashData(value)).ToLowerInvariant();
    string Endpoint(string name) => $"{baseUrl}/api/v2/{name}.php";

    string PublicPem() => deviceKey.ExportSubjectPublicKeyInfoPem().Replace("\r\n", "\n");

    static string JwtJti(string token)
    {
        var parts = token.Split('.');
        if (parts.Length != 3) throw new InvalidOperationException("Licora returned a malformed access token.");
        var segment = parts[1].Replace('-', '+').Replace('_', '/');
        segment += new string('=', (4 - segment.Length % 4) % 4);
        using var document = JsonDocument.Parse(Convert.FromBase64String(segment));
        return document.RootElement.GetProperty("jti").GetString() ?? throw new InvalidOperationException("Access token has no jti.");
    }

    async Task<JsonElement> PostAsync(string name, object payload, string context, string? accessToken = null)
    {
        var url = Endpoint(name);
        var json = JsonSerializer.Serialize(payload);
        var body = Utf8(json);
        var timestamp = DateTimeOffset.UtcNow.ToUnixTimeSeconds();
        var nonce = Base64Url(RandomNumberGenerator.GetBytes(18));
        var path = new Uri(url).AbsolutePath;
        var canonical = $"POST\n{path}\n{timestamp}\n{nonce}\n{Sha256Hex(body)}\n{context}";
        var signature = deviceKey.SignData(Utf8(canonical), HashAlgorithmName.SHA256, DSASignatureFormat.Rfc3279DerSequence);

        using var request = new HttpRequestMessage(HttpMethod.Post, url);
        request.Content = new ByteArrayContent(body);
        request.Content.Headers.ContentType = new MediaTypeHeaderValue("application/json");
        request.Headers.TryAddWithoutValidation("X-Licora-Timestamp", timestamp.ToString());
        request.Headers.TryAddWithoutValidation("X-Licora-Nonce", nonce);
        request.Headers.TryAddWithoutValidation("X-Licora-Device-Signature", Base64Url(signature));
        if (!string.IsNullOrWhiteSpace(accessToken)) request.Headers.Authorization = new AuthenticationHeaderValue("Bearer", accessToken);

        using var response = await http.SendAsync(request);
        var responseText = await response.Content.ReadAsStringAsync();
        using var document = JsonDocument.Parse(responseText);
        var root = document.RootElement.Clone();
        if (!root.TryGetProperty("success", out var success) || !success.GetBoolean())
        {
            var code = root.TryGetProperty("code", out var codeNode) ? codeNode.GetString() : "UNKNOWN";
            throw new InvalidOperationException($"Licora error {code} (HTTP {(int)response.StatusCode})");
        }
        return root;
    }

    public Task<JsonElement> ActivateAsync(string licenseKey) => PostAsync("activate", new Dictionary<string, object>
    {
        ["license_key"] = licenseKey,
        ["app_id"] = appId,
        ["app_version"] = appVersion,
        ["device_id"] = deviceId,
        ["device_public_key"] = PublicPem(),
    }, $"activate:{appId}");

    public Task<JsonElement> StatusAsync(string accessToken) => PostAsync("status", new Dictionary<string, object>(), JwtJti(accessToken), accessToken);

    public Task<JsonElement> RefreshAsync(string refreshToken) => PostAsync("refresh", new Dictionary<string, object>
    {
        ["refresh_token"] = refreshToken,
        ["app_version"] = appVersion,
    }, "refresh:" + Sha256Hex(Utf8(refreshToken)));

    public Task<JsonElement> DeactivateAsync(string accessToken) => PostAsync("deactivate", new Dictionary<string, object>(), JwtJti(accessToken), accessToken);

    public void Dispose() { deviceKey.Dispose(); http.Dispose(); }
}

static class Program
{
    static async Task<int> Main(string[] args)
    {
        if (args.Length < 3)
        {
            Console.Error.WriteLine("Usage: dotnet run -- <base-url> <app-id> <license-key> [app-version]");
            return 2;
        }
        using var client = new LicoraV2Client(args[0], args[1], args.Length > 3 ? args[3] : "1.0.0");
        string accessToken = "";
        try
        {
            var activated = await client.ActivateAsync(args[2]);
            accessToken = activated.GetProperty("access_token").GetString()!;
            var refreshToken = activated.GetProperty("refresh_token").GetString()!;
            Console.WriteLine("[PASS] activate");
            await client.StatusAsync(accessToken); Console.WriteLine("[PASS] status");
            var refreshed = await client.RefreshAsync(refreshToken);
            accessToken = refreshed.GetProperty("access_token").GetString()!;
            refreshToken = refreshed.GetProperty("refresh_token").GetString()!;
            Console.WriteLine("[PASS] refresh (rotated refresh token)");
            await client.StatusAsync(accessToken); Console.WriteLine("[PASS] status-after-refresh");
            await client.DeactivateAsync(accessToken); accessToken = ""; Console.WriteLine("[PASS] deactivate");
            return 0;
        }
        finally
        {
            if (accessToken.Length > 0)
            {
                try { await client.DeactivateAsync(accessToken); Console.WriteLine("[INFO] cleanup deactivate completed"); }
                catch { Console.Error.WriteLine("[WARN] cleanup deactivate failed"); }
            }
        }
    }
}
