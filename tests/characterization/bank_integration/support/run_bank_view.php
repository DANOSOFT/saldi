<?php
// 20261007 CDX/LH Exercised paginated views, tenant-switch rejection and live settings search.
// 20261007 CDX/LH Render bank account, feed and settings views against the disposable tenant.
// 20261007 CDX/LH Carried frozen review snapshot parameters through rendered page requests.
// 20261028 CDX/LH Exercise manual actions and remove first-review request parameters.
// 20261116 CL/LH Render the feed with a kladde_id (journal context) and post the settings form without the journal line cap.
//                The test database and session come from SALDI_EB_TEST_DB and SALDI_EB_SESSION_ID.
$view = $argv[1];
if (!in_array($view, ['accounts', 'feed', 'settings', 'connect', 'search'], true)) {
    throw new InvalidArgumentException('Unknown bank view');
}
putenv('SALDI_ENABLEBANKING_APP_ID=fixture-application');
putenv('SALDI_ENABLEBANKING_KEY_PATH=' . $argv[3]);
putenv('SALDI_ENABLEBANKING_REDIRECT_URL=https://example.invalid/callback');
putenv('SALDI_ENABLEBANKING_ENV=SANDBOX');
if (($argv[5] ?? '') === 'unconfigured') {
    putenv('SALDI_ENABLEBANKING_KEY_PATH=/nonexistent-enablebanking-key');
}
require_once dirname($argv[3]) . '/includes/crypt.php';
if ($view === 'settings') {
    require_once __DIR__ . '/../../../../bank_integration/includes/ebClient.php';
    require_once __DIR__ . '/EbTestEnv.php';
    ebTestConnect();
    require_once __DIR__ . '/../../../../bank_integration/includes/ebAccess.php';
    $rettigheder = str_repeat('9', 50);
    session_start();
    if (($argv[5] ?? '') === 'csrf') {
        $db = 'rendering_tenant_a';
        $token = ebCsrfToken();
        $db = ebTestDb();
        $_POST = ['eb_db' => 'rendering_tenant_a', 'eb_csrf' => $token, 'eb_enabled' => '1'];
        if (($argv[6] ?? '') === 'same_tenant') {
            $_POST['eb_db'] = $db;
        }
        register_shutdown_function(static function (): void { echo "\nHTTP_STATUS:" . http_response_code(); });
    }
    require_once __DIR__ . '/../../../../systemdata/diverseIncludes/bank_integration.php';
    if (($argv[5] ?? '') === 'csrf') {
        bankIntegrationSave();
    }
    bankIntegrationSettings();
    session_write_close();
} else {
    $_SERVER['REQUEST_METHOD'] = ($argv[5] ?? '') === 'csrf' ? 'POST' : 'GET';
    $_SERVER['REQUEST_URI'] = '/saldi/bank_integration/' . $view . '.php';
    $_SERVER['PHP_SELF'] = $_SERVER['REQUEST_URI'];
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'chartest';
    $_GET = ['account' => $argv[2], 'tab' => $argv[4], 'search' => 'Bankforbindelse'];
    if (($argv[5] ?? '') === 'kladde') {
        $_GET['kladde_id'] = $argv[6]; // The journal the user came from (the kassekladde button Bankforbindelse).
    }
    session_id((string)getenv('SALDI_EB_SESSION_ID'));
    if (($argv[5] ?? '') === 'csrf') {
        session_start();
        require_once __DIR__ . '/../../../../bank_integration/includes/ebAccess.php';
        $db = 'rendering_tenant_a';
        $_POST = ['eb_db' => $db, 'eb_csrf' => ebCsrfToken(), 'action' => $view === 'accounts' ? 'disconnect_connection' : 'import_selected', 'ids' => [1], 'account' => $argv[2], 'purpose' => 'reconnect', 'connection' => 1];
        if (($argv[6] ?? '') === 'same_tenant') {
            $_POST['eb_db'] = getenv('SALDI_EB_TEST_DB');
        }
        register_shutdown_function(static function (): void { echo "\nHTTP_STATUS:" . http_response_code(); });
    }
    chdir(__DIR__ . '/../../../../bank_integration');
    if ($view === 'search') {
        chdir(__DIR__ . '/../../../../systemdata');
        require __DIR__ . '/../../../../systemdata/settingsSearch.php';
    } else {
        require __DIR__ . '/../../../../bank_integration/' . $view . '.php';
    }
}
