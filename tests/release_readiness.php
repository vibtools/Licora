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
$releaseNotes = $read('RELEASE_NOTES_v5.2.1.md');
$changelog = $read('CHANGELOG.md');
$configuration = $read('docs/CONFIGURATION.md');
$releaseGuide = $read('docs/RELEASE.md');
$packager = $read('scripts/package-release.sh');

$versionDefinition = "if (!defined('APP_VERSION')) define('APP_VERSION', env_value('APP_VERSION', '5.2.1'));";
$localConfigRequire = 'require_once $localConfig;';
$versionPosition = strpos($config, $versionDefinition);
$localConfigPosition = strpos($config, $localConfigRequire);

$assert(substr_count($config, $versionDefinition) === 1, 'runtime version has exactly one source definition');
$assert(
    $versionPosition !== false && $localConfigPosition !== false && $versionPosition < $localConfigPosition,
    'runtime version resolves before preserved private configuration'
);
$assert(strpos($config, "env_value('APP_NAME', 'Licora')") !== false, 'default application name is Licora');
$assert(strpos($installation, "'APP_VERSION' => '5.2.1'") !== false, 'generated installer configuration targets v5.2.1');
$assert(strpos($installer, 'Professional installation wizard for Licora v5.2.1') !== false, 'installer branding targets v5.2.1');

foreach ([
    "'message' => \$exception->getMessage()",
    "'file' => \$exception->getFile()",
    "'line' => \$exception->getLine()",
] as $unsafeOutput) {
    $assert(strpos($config, $unsafeOutput) === false, 'exception response hides technical detail: ' . $unsafeOutput);
}
$assert(strpos($config, "echo json_encode(['error' => 'Internal Server Error']);") !== false, 'unhandled exception response is generic');
$assert(strpos($installer, 'licora_installer_public_error($e)') !== false, 'installer exceptions use safe public mapping');

$requirements = licora_installer_requirements($root);
foreach ($requirements as $requirement) {
    $detail = (string)($requirement['detail'] ?? '');
    $assert(strpos($detail, $root) === false, 'installer requirement detail hides server paths');
}

$clearStatMarker = 'clearstatcache(true, $includesPath);';
$writableMarker = '$includesWritable = is_writable($includesPath);';
$clearStatPosition = strpos($installation, $clearStatMarker);
$writablePosition = strpos($installation, $writableMarker);
$assert(substr_count($installation, $clearStatMarker) === 1, 'installer refreshes cached includes status exactly once');
$assert(substr_count($installation, $writableMarker) === 1, 'installer evaluates includes writability exactly once');
$assert(
    $clearStatPosition !== false && $writablePosition !== false && $clearStatPosition < $writablePosition,
    'installer clears cached status before checking writability'
);

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
$assert(licora_installer_validate_application($unsafeMail) !== [], 'mail header control characters rejected');

$assert(licora_installer_generated_secret_is_valid(str_repeat('a', 64)), 'generated 64-character hexadecimal secret accepted');
$assert(!licora_installer_generated_secret_is_valid('replace-me'), 'placeholder secret rejected');

$technical = new RuntimeException('SQLSTATE[HY000] password=secret /private/server/path');
$public = licora_installer_public_error($technical);
foreach (['SQLSTATE', 'password', 'secret', '/private/server/path'] as $sensitive) {
    $assert(strpos($public, $sensitive) === false, 'public installer error redacts: ' . $sensitive);
}
$triggerError = new RuntimeException("SQLSTATE[42000]: TRIGGER command denied to user 'demo'");
$assert(
    licora_installer_public_error($triggerError) === 'The database account lacks the TRIGGER privilege required by the Licora schema.',
    'trigger privilege denial receives a safe actionable diagnostic'
);

foreach ([
    'RELEASE_NOTES_v5.2.1.md',
    'PHASE2_INSTALLER_SUMMARY.md',
    'RELEASE_COMMANDS_v5.2.1.md',
    'docs/FAQ.md',
    'docs/COMPATIBILITY_MATRIX.md',
] as $path) {
    $assert(is_file($root . '/' . $path), 'release documentation exists: ' . $path);
}

$assert(strpos($releaseNotes, 'Licora v5.2.1') !== false, 'release notes identify v5.2.1');
$assert(strpos($changelog, '## [5.2.1] - 2026-08-08') !== false, 'changelog contains the publication date');
$assert(strpos($configuration, '`APP_VERSION` | `APP_VERSION` | `5.2.1`') !== false, 'configuration reference matches runtime version');
$assert(strpos($releaseGuide, 'scripts/package-release.sh v5.2.1 v5.2.1') !== false, 'release guide uses the v5.2.1 packager command');
$assert(strpos($packager, 'git archive --format=zip') !== false, 'release package is created from a Git ref');
$assert(strpos($packager, 'git diff --quiet') !== false, 'release packager rejects tracked working-tree changes');

foreach ([
    'includes/config.php',
    'includes/installation.php',
    'install.php',
    'config.sample.php',
    'CHANGELOG.md',
    'RELEASE_NOTES_v5.2.1.md',
] as $path) {
    $content = $read($path);
    $assert(strpos($content, '5.2.2') === false, 'v5.2.1 release file does not contain future version marker: ' . $path);
}

if ($failures !== []) {
    fwrite(STDERR, "Release readiness test failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Release readiness checks passed.\n";
