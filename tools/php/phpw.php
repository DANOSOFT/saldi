<?php
// tools/php/phpw.php - portable PHP wrapper for the Saldi toolchain.
//
// Some PHP builds load no php.ini at all (the winget PHP package on Windows
// reports "Loaded Configuration File: (none)"), so the bundled extensions
// PHPUnit and Composer need (mbstring, openssl, zip, curl) are never enabled
// and both fail at startup. This wrapper needs no extensions itself: it
// checks which required extensions are missing in the current PHP and
// re-invokes PHP on the given script with -d extension_dir/-d extension
// flags, auto-detecting the ext/ directory next to the PHP binary.
//
// On a PHP that already loads a proper php.ini (typical Linux/macOS), no
// flags are added and the script runs as-is.
//
// Usage:
//   php tools/php/phpw.php tools/php/composer.phar install
//   php tools/php/phpw.php vendor/bin/phpunit [args...]
//
// `composer test` routes through this wrapper (see "scripts" in composer.json).
//
// History:
// 20260723 CL/LH Created for SD-593: replaces the hard-coded extension_dir
//                in tools/php/php.ini with runtime auto-detection.

$args = array_slice($argv, 1);
if (count($args) === 0) {
    fwrite(STDERR, "Usage: php tools/php/phpw.php <script.php|tool.phar> [args...]\n");
    exit(2);
}

$required = array('mbstring', 'openssl', 'zip', 'curl');
$missing = array();
foreach ($required as $ext) {
    if (!extension_loaded($ext)) {
        $missing[] = $ext;
    }
}

$cmd = array(PHP_BINARY);
if (count($missing) > 0) {
    $extDir = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'ext';
    if (is_dir($extDir)) {
        $cmd[] = '-d';
        $cmd[] = 'extension_dir=' . $extDir;
    }
    foreach ($missing as $ext) {
        $cmd[] = '-d';
        $cmd[] = 'extension=' . $ext;
    }
}
$cmd = array_merge($cmd, $args);

// Array-form proc_open (PHP >= 7.4) execs directly, no shell quoting issues.
$proc = proc_open($cmd, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "phpw: failed to launch " . implode(' ', $cmd) . "\n");
    exit(1);
}
exit(proc_close($proc));
