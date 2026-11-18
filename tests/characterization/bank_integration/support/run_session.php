<?php
// 20261007 CDX/LH Verify session release and flash persistence in an isolated PHP process.
require_once __DIR__ . '/../../../../bank_integration/includes/ebAccess.php';
require_once __DIR__ . '/EbTestEnv.php';
session_save_path($argv[1]);
session_id(bin2hex(random_bytes(16)));
session_start();
$csrf = ebCsrfToken();
ebFlash('info', 'Før hentning');
session_write_close();
$transport = new EbFixtureTransport([['method' => 'GET', 'path' => '/aspsps?', 'status' => 200,
    'body' => ['aspsps' => []], 'effect' => static function (): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Session locked during HTTP');
        }
    }]]);
$config = ['app_id' => 'fixture-application', 'key_path' => $argv[2], 'redirect_url' => 'https://example.invalid/callback'];
(new EnableBankingClient($config, $transport))->aspsps('DK');
ebFlash('ok', 'Efter hentning');
if (session_status() === PHP_SESSION_ACTIVE) {
    throw new RuntimeException('Flash kept session locked');
}
session_start();
if (ebCsrfToken() !== $csrf || array_column(ebTakeFlash(), 'text') !== ['Før hentning', 'Efter hentning']) {
    throw new RuntimeException('Flash or CSRF lost');
}
session_write_close();
if (ebTakeFlash() !== []) {
    throw new RuntimeException('Flash not cleared');
}
echo "session and flash ok\n";
