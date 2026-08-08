<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$expected = [
    'api/verify.php' => '4dc549c2afea0772d3f2ffa8b330fd24b8b13ec2',
    'api/check_license.php' => 'e3bc343489bb384304e003fa25e02d494fd83b8a',
    'includes/functions.php' => '778f02afc27d989acd2d58ed28001a5f325f5cda',
    'includes/security.php' => '6c2cfd4ce1d10eb02b41ce1a77ebeeb71de05532',
];
foreach ($expected as $rel => $hash) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) { fwrite(STDERR, "Missing frozen API v1 file: {$rel}\n"); exit(1); }
    $data = str_replace(["\r\n", "\r"], "\n", (string)file_get_contents($path));
    $actual = sha1('blob ' . strlen($data) . "\0" . $data);
    if (!hash_equals($hash, $actual)) { fwrite(STDERR, "API v1 drift: {$rel} {$actual} != {$hash}\n"); exit(1); }
}
echo "API v1 freeze checks passed.\n";
