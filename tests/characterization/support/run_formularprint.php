<?php
// tests/characterization/support/run_formularprint.php
//
// Child-process runner for includes/formfunk.php formularprint() (SD-751).
//
// formularprint() prints HTML, writes .ps/.htm files to temp/<db>/<bruger_id>/ and, on some
// branches, calls exit(), so it runs here and not in the PHPUnit process. The bootstrap is the
// session-less one the other ordrefunc runners share (bootstrap_ordrefunc.php). send_mails() is
// replaced by a recorder before formfunk.php loads (formfunk.php only defines it when missing),
// so a test sees every mail the print would have sent and nothing leaves the machine.
//
// Usage: php run_formularprint.php <ordre_id> <formular> <tenant_db> [udskriv_alle_til] [headless]
//   udskriv_alle_til: passed as is; '-' for an empty value (take ordrer.udskriv_til). Default 'PDF'.
//   headless: 1 runs formularprint() with a fresh work directory under temp/<db>/.
//
// Prints a single JSON object on the LAST line of stdout: the return value, the printed output,
// the recorded mails and every file in temp/<db>/<bruger_id>/ and in the work directory.
//
// History:
// 20261029 CL/SZ SD-751: created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_formularprint.php <ordre_id> <formular> <tenant_db> [udskriv_alle_til] [headless]\n");
    exit(2);
}
$ordreId = (int)$argv[1];
$formular = (int)$argv[2];
$tenantDb = $argv[3];
$udskrivAlleTil = ($argv[4] ?? 'PDF') === '-' ? '' : ($argv[4] ?? 'PDF');
$headless = ($argv[5] ?? '') === '1';

$sentMails = array();
function send_mails($ordre_id, $filnavn, $email, $mailsprog, $form_nr, $subjekt, $mailtext, $mailbilag, $mailnr)
{
    global $sentMails;
    $sentMails[] = array('ordre_id' => $ordre_id, 'email' => $email, 'file' => basename($filnavn));
    return 'OK';
}

require __DIR__ . '/bootstrap_ordrefunc.php';
include("../includes/formfunk.php");
error_reporting(E_ALL);

/** @return array<string, string> file name => contents */
function run_formularprint_files(string $dir): array
{
    $files = array();
    foreach (is_dir($dir) ? scandir($dir) : array() as $file) {
        if ($file !== '.' && $file !== '..' && is_file("$dir/$file")) {
            $files[$file] = file_get_contents("$dir/$file");
        }
    }
    ksort($files);
    return $files;
}

$printDir = "../temp/$db/" . abs($bruger_id);
$workDir = null;
if ($headless) {
    $workDir = "../temp/$db/.ht_doc_runner_" . getmypid();
    mkdir($workDir, 0700, true);
}
ob_start();
$returned = formularprint($ordreId, $formular, 0, 'UTF-8', $udskrivAlleTil, null, $workDir);
$printed = ob_get_clean();

ob_end_clean();
fwrite(STDOUT, json_encode(array(
    'returned' => $returned,
    'printed' => $printed,
    'mails' => $sentMails,
    'print_files' => run_formularprint_files($printDir),
    'work_files' => $workDir === null ? null : run_formularprint_files($workDir),
), JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
if ($workDir !== null) {
    array_map('unlink', glob("$workDir/*") ?: array());
    rmdir($workDir);
}
