<?php
// 20261127 CDX/NTR Replay the real POS delivery-note pages with an isolated test session.

declare(strict_types=1);

if ($argc !== 5) {
    throw new InvalidArgumentException('Expected page, test session, request JSON and output file');
}
[, $page, $sessionId, $request, $outputFile] = $argv;
if (!in_array($page, ['debitor/pos_ordre.php', 'debitor/udskriftsvalg.php', 'debitor/formularprint.php', 'includes/udskriv.php'], true)) {
    throw new InvalidArgumentException('Expected a POS delivery-note page');
}
$request = json_decode($request, true, 512, JSON_THROW_ON_ERROR);
$_GET = $request['get'] ?? [];
$_POST = [];
$_COOKIE = $request['cookie'] ?? [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/saldi/' . $page;
$_SERVER['PHP_SELF'] = '/saldi/' . $page;
$_SERVER['HTTP_HOST'] = 'chartest.local';
$_SERVER['HTTPS'] = '';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);

chdir(dirname(__DIR__, 3) . '/' . dirname($page));
ob_start();
register_shutdown_function(static function () use ($outputFile): void {
    file_put_contents($outputFile, (string)ob_get_clean());
});
include __DIR__ . '/../../../' . $page;
