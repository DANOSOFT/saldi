<?php
// tests/characterization/support/run_rykkerprint.php
//
// Child-process runner for includes/formfunk.php rykkerprint() as debitor/rykkerprint.php calls it for the
// reminders ticked under Åbne rykkere (Udskriv) or sent from debitor/rykker.php (Send).
//
// rykkerprint() prints HTML and exit()s, so it runs here and not in the PHPUnit process. The bootstrap is
// the session-less one the other ordrefunc runners share (bootstrap_ordrefunc.php). send_mails() is replaced
// by a recorder before formfunk.php loads (formfunk.php only defines it when missing), and ps2pdf by a
// stand-in that writes a small PDF, or fails for the reminders the test names, so nothing leaves the machine
// and no Ghostscript is needed.
//
// Usage: php run_rykkerprint.php <tenant_db> <reminder ids, ';'-separated> [ids whose PDF fails, ';'-separated] [automation]
//   automation: 1 installs the automatic reminder tables (includes/opdat_func/reminderAutomation.php) first.
//
// Prints a single JSON object on the LAST line of stdout, also when rykkerprint() exit()s: what the page
// printed and the recorded mails.
//
// History:
// 20261126 ASR Created for review I7: a failing reminder in a batch is named and the others are sent.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_rykkerprint.php <tenant_db> <reminder ids> [failing ids] [automation]\n");
    exit(2);
}
$tenantDb = $argv[1];
$reminderIds = array_map('intval', explode(';', $argv[2]));
$failing = array_filter(array_map('intval', explode(';', $argv[3] ?? '')));
$automation = ($argv[4] ?? '') === '1';

$sentMails = array();
function send_mails($ordre_id, $filnavn, $email, $mailsprog, $form_nr, $subjekt, $mailtext, $mailbilag, $mailnr)
{
    global $sentMails;
    $sentMails[] = array('ordre_id' => (int)$ordre_id, 'email' => $email, 'pdf' => is_file($filnavn) ? file_get_contents($filnavn, false, null, 0, 5) : '');
    return 'OK';
}

require __DIR__ . '/bootstrap_ordrefunc.php';
include __DIR__ . '/../../../includes/formfunk.php';
if ($automation) {
    require_once __DIR__ . '/../../../includes/opdat_func/reminderAutomation.php';
    reminder_automation_schema_ensure();
}

// ps2pdf <source> <pdf>: a small PDF, or exit 1 for a failing reminder's source (.../Rykker<id>).
$ps2pdf = tempnam(sys_get_temp_dir(), 'chartest-ps2pdf-');
$fail = implode(' ', array_map(static fn($id) => 'Rykker' . $id, $failing));
file_put_contents($ps2pdf, "#!/bin/sh\ncase \" $fail \" in *\" \$(basename \"\$1\") \"*) exit 1;; esac\nprintf '%%PDF-1.4 chartest\\n' > \"\$2\"\n");
chmod($ps2pdf, 0700);
$pdftk = '/bin/false';
$formularsprog = 'dansk';

register_shutdown_function(static function () use ($ps2pdf) {
    $printed = '';
    while (ob_get_level() > 0) {
        $printed = ob_get_clean() . $printed;
    }
    @unlink($ps2pdf);
    fwrite(STDOUT, json_encode(array('printed' => $printed, 'mails' => $GLOBALS['sentMails']), JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
});

ob_start();
rykkerprint(array(), $reminderIds, '0', '', '', $regnaar, 0);
