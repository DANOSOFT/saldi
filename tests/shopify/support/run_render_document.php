<?php
// tests/shopify/support/run_render_document.php
//
// Child-process runner for includes/documents/renderDocument.php render_document() (SD-751).
// Boots the tenant session-less with shopify_bootstrap(), as the worker does, and replaces
// send_mails() with a recorder before formfunk.php loads, so a test sees any mail the render
// would have sent. Anything the render prints is captured and reported.
//
// Usage: php run_render_document.php <test_db> <ordre_id> <formular>
// Env: RENDER_PDFTK, RENDER_PS2PDF (commands passed as opts pdftk / ps2pdf).
//
// Prints one JSON object: result (render_document's array) or error (class, message and code),
// mails, the printed output and the working directory before and after.
//
// History:
// 20261029 CL/SZ SD-751: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_render_document.php <test_db> <ordre_id> <formular>\n");
    exit(2);
}
$testDb = $argv[1];
$ordreId = (int)$argv[2];
$formular = (int)$argv[3];

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);

$sentMails = array();
function send_mails($ordre_id, $filnavn, $email, $mailsprog, $form_nr, $subjekt, $mailtext, $mailbilag, $mailnr)
{
    global $sentMails;
    $sentMails[] = array('ordre_id' => $ordre_id, 'email' => $email);
    return 'OK';
}

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqhost = ShopifyEnv::pgHost();
$squser = ShopifyEnv::pgUser();
$sqpass = ShopifyEnv::pgPass();
$sqdb = ShopifyEnv::masterDb();
$db = $sqdb;
$db_skriv_id = null;
$brugernavn = '';
$webservice = false;

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
require_once ShopifyEnv::repoRoot() . '/includes/std_func.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/documents/renderDocument.php';

putenv('FAKE_PDFTK_CALLER=' . getmypid());
$out = array();
try {
    shopify_bootstrap($testDb);
    $out['cwd_before'] = getcwd();
    $opts = array();
    foreach (array('pdftk' => 'RENDER_PDFTK', 'ps2pdf' => 'RENDER_PS2PDF') as $opt => $env) {
        if (($value = getenv($env)) !== false && $value !== '') {
            $opts[$opt] = $value;
        }
    }
    ob_start();
    try {
        $out['result'] = render_document($ordreId, $formular, null, $opts);
    } finally {
        $out['printed'] = ob_get_clean();
    }
} catch (Throwable $e) {
    $out['error'] = array('class' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode());
}
$out['cwd_after'] = getcwd();
$out['mails'] = $sentMails;
echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
