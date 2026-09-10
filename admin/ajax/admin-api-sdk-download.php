<?php
declare(strict_types=1);

require_once '../../includes/auth.php';

$auth = new Auth();
if (!$auth->isAdminLoggedIn()) { header('Location: login.php'); exit(); }
if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The PHP Zip extension is required to build the SDK download.';
    exit();
}

$sourceRoot = realpath(__DIR__ . '/../../SDK/admin-license-api');
if (!is_string($sourceRoot) || !is_dir($sourceRoot)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The Admin License API SDK source package is unavailable.';
    exit();
}

$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $item) {
    if (!$item instanceof SplFileInfo || !$item->isFile() || $item->isLink()) { continue; }
    $realPath = $item->getRealPath();
    if (!is_string($realPath) || strpos($realPath, $sourceRoot . DIRECTORY_SEPARATOR) !== 0) { continue; }
    $relativePath = substr($realPath, strlen($sourceRoot) + 1);
    if ($relativePath === '' || preg_match('/(?:^|[\\\/])\.\.(?:[\\\/]|$)/', $relativePath)) { continue; }
    $files[str_replace(DIRECTORY_SEPARATOR, '/', $relativePath)] = $realPath;
}
ksort($files, SORT_STRING);
if ($files === []) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The Admin License API SDK package contains no downloadable files.';
    exit();
}

$temporaryPath = tempnam(sys_get_temp_dir(), 'licora-admin-sdk-');
if (!is_string($temporaryPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to prepare the SDK download.';
    exit();
}
register_shutdown_function(static function () use ($temporaryPath): void {
    if (is_file($temporaryPath)) { @unlink($temporaryPath); }
});

$zip = new ZipArchive();
if ($zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to create the SDK archive.';
    exit();
}

$archiveRoot = 'Licora-Admin-API-SDK-v1.0.0/';
foreach ($files as $relativePath => $realPath) {
    if (!$zip->addFile($realPath, $archiveRoot . $relativePath)) {
        $zip->close();
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unable to add an SDK file to the archive.';
        exit();
    }
}
$zip->setArchiveComment('Licora Admin License API SDK v1.0.0');
if (!$zip->close() || !is_file($temporaryPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to finalize the SDK archive.';
    exit();
}

$downloadName = 'Licora-Admin-API-SDK-v1.0.0.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . (string)filesize($temporaryPath));
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
readfile($temporaryPath);
exit();
