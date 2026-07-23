<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/installation.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$read = static function (string $path) use ($root): string {
    $full = $root . '/' . $path;
    return is_file($full) ? (string)file_get_contents($full) : '';
};

$config = $read('includes/config.php');
$installer = $read('install.php');
$installation = $read('includes/installation.php');

$assert(strpos($config, "env_value('APP_VERSION', '5.1.1')") !== false, 'default application version is v5.1.1');
$assert(strpos($installation, "'APP_VERSION' => '5.1.1'") !== false, 'generated installer configuration targets v5.1.1');
$assert(strpos($installer, 'Professional installation wizard for Licora v5.1.1') !== false, 'installer branding targets v5.1.1');

foreach ([
    '\'message\' => $exception->getMessage()',
    '\'file\' => $exception->getFile()',
    '\'line\' => $exception->getLine()',
] as $unsafeOutput) {
    $assert(strpos($config, $unsafeOutput) === false, 'exception response hides technical detail: ' . $unsafeOutput);
}
$assert(strpos($config, "echo json_encode(['error' => 'Internal Server Error']);") !== false, 'unhandled exception response remains generic');
$assert(strpos($installer, 'licora_installer_public_error($e)') !== false, 'installer exceptions use the safe public-error mapper');

$requirements = licora_installer_requirements($root);
foreach ($requirements as $requirement) {
    $detail = (string)($requirement['detail'] ?? '');
    $assert(strpos($detail, $root) === false, 'installer requirement detail hides server paths');
}

$validApplication = [
    'app_name' => 'Licora',
    'timezone' => 'Asia/Dhaka',
    'locale' => 'en',
    'base_url' => 'https://licenses.example.com/licora',
    'mail_from_name' => 'Licora',
];
$assert(licora_installer_validate_application($validApplication) === [], 'valid application configuration remains accepted');

foreach ([
    'https://user:password@licenses.example.com/licora',
    'https://licenses.example.com/licora?token=secret',
    'https://licenses.example.com/licora#fragment',
] as $unsafeUrl) {
    $candidate = $validApplication;
    $candidate['base_url'] = $unsafeUrl;
    $assert(licora_installer_validate_application($candidate) !== [], 'unsafe base URL rejected: ' . $unsafeUrl);
}

$unsafeMail = $validApplication;
$unsafeMail['mail_from_name'] = "Licora\r\nBcc: attacker@example.com";
$assert(licora_installer_validate_application($unsafeMail) !== [], 'mail-from header control characters rejected');

$assert(licora_installer_generated_secret_is_valid(str_repeat('a', 64)), 'generated 64-character hexadecimal secret accepted');
$assert(!licora_installer_generated_secret_is_valid('replace-me'), 'placeholder generated secret rejected');

$technical = new RuntimeException('SQLSTATE[HY000] password=secret /private/server/path');
$public = licora_installer_public_error($technical);
foreach (['SQLSTATE', 'password', 'secret', '/private/server/path'] as $sensitive) {
    $assert(strpos($public, $sensitive) === false, 'public installer error redacts: ' . $sensitive);
}

foreach ([
    'RELEASE_NOTES_v5.1.1.md',
    'PHASE2_1_QUALITY_IMPROVEMENT_SUMMARY.md',
    'docs/FAQ.md',
    'docs/COMPATIBILITY_MATRIX.md',
] as $path) {
    $assert(is_file($root . '/' . $path), 'quality/stability documentation exists: ' . $path);
}

if ($failures !== []) {
    fwrite(STDERR, "Quality and stability test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Quality and stability checks passed.\n";
