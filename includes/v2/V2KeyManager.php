<?php
declare(strict_types=1);

final class V2KeyManager
{
    private string $privatePath;
    private string $publicPath;
    private string $keyId;

    public function __construct(?string $privatePath = null, ?string $publicPath = null, ?string $keyId = null)
    {
        $this->privatePath = $privatePath ?: self::setting(
            'LICENSE_V2_SIGNING_PRIVATE_KEY_PATH',
            dirname(__DIR__) . '/.licora-v2-signing-private.pem'
        );
        $this->publicPath = $publicPath ?: self::setting(
            'LICENSE_V2_SIGNING_PUBLIC_KEY_PATH',
            dirname(__DIR__) . '/.licora-v2-signing-public.pem'
        );
        $this->keyId = $keyId ?: self::setting('LICENSE_V2_SIGNING_KEY_ID', 'primary-v1');
    }

    public static function setting(string $name, string $default): string
    {
        if (defined($name)) {
            $value = (string)constant($name);
            if ($value !== '') { return $value; }
        }
        $env = getenv($name);
        return ($env === false || $env === '') ? $default : (string)$env;
    }

    public function keyId(): string { return $this->keyId; }
    public function privatePath(): string { return $this->privatePath; }
    public function publicPath(): string { return $this->publicPath; }

    public function requirePrivateKey()
    {
        if (!is_file($this->privatePath) || !is_readable($this->privatePath)) {
            throw new RuntimeException('Licora API v2 signing private key is not configured.');
        }
        $pem = file_get_contents($this->privatePath);
        $key = $pem === false ? false : openssl_pkey_get_private($pem);
        if ($key === false) { throw new RuntimeException('Licora API v2 signing private key is invalid.'); }
        return $key;
    }

    public function requirePublicKey()
    {
        if (!is_file($this->publicPath) || !is_readable($this->publicPath)) {
            throw new RuntimeException('Licora API v2 signing public key is not configured.');
        }
        $pem = file_get_contents($this->publicPath);
        $key = $pem === false ? false : openssl_pkey_get_public($pem);
        if ($key === false) { throw new RuntimeException('Licora API v2 signing public key is invalid.'); }
        return $key;
    }

    public function publicKeyPem(): string
    {
        $details = openssl_pkey_get_details($this->requirePublicKey());
        if (!is_array($details) || empty($details['key'])) { throw new RuntimeException('Unable to read API v2 public key.'); }
        return (string)$details['key'];
    }

    public function generateIfMissing(): bool
    {
        if (PHP_SAPI !== 'cli') { throw new RuntimeException('Signing key generation is CLI-only.'); }
        if (is_file($this->privatePath) || is_file($this->publicPath)) {
            if (!is_file($this->privatePath) || !is_file($this->publicPath)) {
                throw new RuntimeException('Only one API v2 signing key file exists; refusing to replace it.');
            }
            $this->requirePrivateKey();
            $this->requirePublicKey();
            return false;
        }

        $resource = openssl_pkey_new([
            'private_key_bits' => 3072,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($resource === false) { throw new RuntimeException('Unable to generate RSA-3072 signing key.'); }
        $privatePem = '';
        if (!openssl_pkey_export($resource, $privatePem)) { throw new RuntimeException('Unable to export API v2 private key.'); }
        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || empty($details['key'])) { throw new RuntimeException('Unable to export API v2 public key.'); }
        self::atomicWrite($this->privatePath, $privatePem, 0600);
        try { self::atomicWrite($this->publicPath, (string)$details['key'], 0644); }
        catch (Throwable $e) { @unlink($this->privatePath); throw $e; }
        return true;
    }

    private static function atomicWrite(string $path, string $content, int $mode): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) || !is_writable($directory)) { throw new RuntimeException('Signing key directory is not writable: ' . $directory); }
        $tmp = $path . '.installing.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $content, LOCK_EX) === false) { throw new RuntimeException('Unable to write signing key file.'); }
        @chmod($tmp, $mode);
        if (!@rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Unable to activate signing key file.'); }
        @chmod($path, $mode);
    }
}

