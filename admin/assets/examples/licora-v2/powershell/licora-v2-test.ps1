<#
.SYNOPSIS
  Licora Secure API v2 lifecycle test for Windows PowerShell 5.1+ / PowerShell 7+.

.DESCRIPTION
  Creates an ephemeral P-256 device, then runs activate -> status -> refresh ->
  status -> deactivate. It never asks for or embeds an API v1 shared key.

  Production applications must persist the device private key and rotated refresh
  token in OS-backed secure storage, and verify LICORA-V2/RS256 access-token
  signatures with the pinned Licora server public key before trusting token claims.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$BaseUrl,
    [Parameter(Mandatory=$true)][string]$AppId,
    [Parameter(Mandatory=$true)][string]$LicenseKey,
    [string]$AppVersion = '1.0.0'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Net.Http
try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12 } catch {}


function Get-RandomBytes([int]$Count) {
    $bytes = New-Object byte[] $Count
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($bytes); return $bytes } finally { $rng.Dispose() }
}

function Convert-ToBase64Url([byte[]]$Bytes) {
    return [Convert]::ToBase64String($Bytes).TrimEnd('=').Replace('+','-').Replace('/','_')
}

function Convert-FromBase64Url([string]$Value) {
    $s = $Value.Replace('-','+').Replace('_','/')
    while (($s.Length % 4) -ne 0) { $s += '=' }
    return [Convert]::FromBase64String($s)
}

function Convert-HexToBytes([string]$Hex) {
    $bytes = New-Object byte[] ($Hex.Length / 2)
    for ($i = 0; $i -lt $bytes.Length; $i++) { $bytes[$i] = [Convert]::ToByte($Hex.Substring($i * 2, 2), 16) }
    return $bytes
}

function Convert-P1363ToDer([byte[]]$Signature) {
    if ($Signature.Length -gt 0 -and $Signature[0] -eq 0x30) { return $Signature }
    if ($Signature.Length -ne 64) { throw "Unexpected ECDSA signature format/length: $($Signature.Length)" }
    function Encode-Integer([byte[]]$Value) {
        $start = 0
        while ($start -lt ($Value.Length - 1) -and $Value[$start] -eq 0) { $start++ }
        $v = $Value[$start..($Value.Length - 1)]
        if (($v[0] -band 0x80) -ne 0) { $v = [byte[]]@(0) + $v }
        return [byte[]]@(0x02, [byte]$v.Length) + $v
    }
    $r = Encode-Integer $Signature[0..31]
    $s = Encode-Integer $Signature[32..63]
    $body = $r + $s
    return [byte[]]@(0x30, [byte]$body.Length) + $body
}

function Get-PublicKeyPem($Ecdsa) {
    $p = $Ecdsa.ExportParameters($false)
    if ($p.Q.X.Length -ne 32 -or $p.Q.Y.Length -ne 32) { throw 'Unexpected P-256 public-key coordinate size.' }
    # SubjectPublicKeyInfo prefix for id-ecPublicKey + prime256v1, followed by 0x04 || X || Y.
    $prefix = Convert-HexToBytes '3059301306072A8648CE3D020106082A8648CE3D03010703420004'
    $der = $prefix + $p.Q.X + $p.Q.Y
    $b64 = [Convert]::ToBase64String($der)
    $lines = for ($i = 0; $i -lt $b64.Length; $i += 64) { $b64.Substring($i, [Math]::Min(64, $b64.Length - $i)) }
    return "-----BEGIN PUBLIC KEY-----`n$($lines -join "`n")`n-----END PUBLIC KEY-----`n"
}

function Get-Sha256Hex([byte[]]$Bytes) {
    $sha = [System.Security.Cryptography.SHA256]::Create()
    try { return ([BitConverter]::ToString($sha.ComputeHash($Bytes))).Replace('-','').ToLowerInvariant() }
    finally { $sha.Dispose() }
}

function Get-Jti([string]$Token) {
    $parts = $Token.Split('.')
    if ($parts.Length -ne 3) { throw 'Licora returned a malformed access token.' }
    $payload = [Text.Encoding]::UTF8.GetString((Convert-FromBase64Url $parts[1])) | ConvertFrom-Json
    if (-not $payload.jti) { throw 'Licora access token has no jti.' }
    return [string]$payload.jti
}

function New-LicoraEcdsa {
    try {
        return [System.Security.Cryptography.ECDsa]::Create([System.Security.Cryptography.ECCurve]::NamedCurves.nistP256)
    } catch {
        $key = New-Object System.Security.Cryptography.ECDsaCng
        $key.GenerateKey([System.Security.Cryptography.ECCurve]::NamedCurves.nistP256)
        return $key
    }
}

$script:DeviceKey = New-LicoraEcdsa
$script:PublicPem = Get-PublicKeyPem $script:DeviceKey
$script:DeviceId = 'ps-' + ([Guid]::NewGuid().ToString('N'))
$script:Http = New-Object System.Net.Http.HttpClient
$script:BaseUrl = $BaseUrl.TrimEnd('/')

function Invoke-LicoraPost([string]$Name, [hashtable]$Payload, [string]$Context, [string]$AccessToken = '') {
    $url = "$script:BaseUrl/api/v2/$Name.php"
    $bodyText = $Payload | ConvertTo-Json -Compress
    $body = [Text.Encoding]::UTF8.GetBytes($bodyText)
    $timestamp = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $nonce = Convert-ToBase64Url ((Get-RandomBytes 18))
    $path = ([Uri]$url).AbsolutePath
    $canonical = "POST`n$path`n$timestamp`n$nonce`n$(Get-Sha256Hex $body)`n$Context"
    $canonicalBytes = [Text.Encoding]::UTF8.GetBytes($canonical)
    $signature = $script:DeviceKey.SignData($canonicalBytes, [System.Security.Cryptography.HashAlgorithmName]::SHA256)
    $signature = Convert-P1363ToDer $signature

    $request = New-Object System.Net.Http.HttpRequestMessage([System.Net.Http.HttpMethod]::Post, $url)
    $request.Content = New-Object System.Net.Http.StringContent($bodyText, [Text.Encoding]::UTF8, 'application/json')
    [void]$request.Headers.TryAddWithoutValidation('X-Licora-Timestamp', [string]$timestamp)
    [void]$request.Headers.TryAddWithoutValidation('X-Licora-Nonce', $nonce)
    [void]$request.Headers.TryAddWithoutValidation('X-Licora-Device-Signature', (Convert-ToBase64Url $signature))
    if ($AccessToken) { $request.Headers.Authorization = New-Object System.Net.Http.Headers.AuthenticationHeaderValue('Bearer', $AccessToken) }
    try {
        $response = $script:Http.SendAsync($request).GetAwaiter().GetResult()
        $text = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        try { $data = $text | ConvertFrom-Json } catch { throw "HTTP $([int]$response.StatusCode): non-JSON response" }
        if (-not $data.success) { throw "Licora error $($data.code) (HTTP $([int]$response.StatusCode))" }
        return $data
    } finally { $request.Dispose() }
}

$accessToken = ''
try {
    $activation = Invoke-LicoraPost 'activate' @{
        license_key = $LicenseKey
        app_id = $AppId
        app_version = $AppVersion
        device_id = $script:DeviceId
        device_public_key = $script:PublicPem
    } "activate:$AppId"
    $accessToken = [string]$activation.access_token
    $refreshToken = [string]$activation.refresh_token
    Write-Host '[PASS] activate' -ForegroundColor Green

    [void](Invoke-LicoraPost 'status' @{} (Get-Jti $accessToken) $accessToken)
    Write-Host '[PASS] status' -ForegroundColor Green

    $refreshContext = 'refresh:' + (Get-Sha256Hex ([Text.Encoding]::UTF8.GetBytes($refreshToken)))
    $refreshed = Invoke-LicoraPost 'refresh' @{ refresh_token = $refreshToken; app_version = $AppVersion } $refreshContext
    $accessToken = [string]$refreshed.access_token
    $refreshToken = [string]$refreshed.refresh_token
    Write-Host '[PASS] refresh (rotated refresh token)' -ForegroundColor Green

    [void](Invoke-LicoraPost 'status' @{} (Get-Jti $accessToken) $accessToken)
    Write-Host '[PASS] status-after-refresh' -ForegroundColor Green

    [void](Invoke-LicoraPost 'deactivate' @{} (Get-Jti $accessToken) $accessToken)
    $accessToken = ''
    Write-Host '[PASS] deactivate' -ForegroundColor Green
} finally {
    if ($accessToken) {
        try { [void](Invoke-LicoraPost 'deactivate' @{} (Get-Jti $accessToken) $accessToken); Write-Host '[INFO] cleanup deactivate completed' }
        catch { Write-Warning 'cleanup deactivate failed' }
    }
    if ($script:Http) { $script:Http.Dispose() }
    if ($script:DeviceKey) { $script:DeviceKey.Dispose() }
}
