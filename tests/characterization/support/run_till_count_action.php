<?php
// tests/characterization/support/run_till_count_action.php
//
// Child runner for TillCountPerYearTest: in a given fiscal year, reads the till count the settings
// page uses, or runs its "Tilføj kasse" / "Fjern kasse" action, and reports every POS/1 row.
// The settings code needs the session's globals from online.php, so it runs here rather than in
// the PHPUnit process.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = the fiscal year ($regnaar)
//       3 = count | add | remove
// prints JSON on the last line: {"count": int, "rows": [{"fiscal_year": int, "box1": string, "box5": string}, ...]}
//
// History:
// 20261125 CL/MJ SD-880 Created.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_till_count_action.php <session_id> <regnaar> <count|add|remove>\n");
    exit(2);
}
[, $sessionId, $year, $what] = $argv;

error_reporting(E_ERROR | E_PARSE);
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/settingsSection.php';
$_SERVER['PHP_SELF'] = '/saldi/systemdata/settingsSection.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_GET = $_POST = $_REQUEST = array();
ini_set('session.save_path', sys_get_temp_dir());
session_id($sessionId);
chdir(dirname(__DIR__, 3) . '/systemdata');

@session_start();
$s_id = session_id(); // online.php finds the seeded online row by this id, as on every page
$modulnr = 0;
include __DIR__ . '/../../../includes/connect.php';
include __DIR__ . '/../../../includes/online.php';
include __DIR__ . '/../../../includes/std_func.php';
include_once __DIR__ . '/../../../includes/settings/components.php';
include_once __DIR__ . '/../../../includes/settings/actions.php';

$regnaar = (int) $year;
if ($what === 'add' || $what === 'remove') {
    // "Fjern kasse" is defined on the last till only (per 'till_last'): pos.tills.remove.<count>.
    $key = $what === 'add' ? 'pos.tills.add' : 'pos.tills.remove.' . settings_till_count();
    settings_run_action(SettingsService::definition($key), 'settingsSection.php?s=pos.tills');
    $GLOBALS['settings_tills_changed'] = true;
}
$count = settings_till_count();

$rows = array();
$q = db_select("select fiscal_year, box1, box5 from grupper where art = 'POS' and kodenr = '1' order by fiscal_year, id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $rows[] = array('fiscal_year' => (int) $r['fiscal_year'], 'box1' => (string) $r['box1'], 'box5' => (string) $r['box5']);
}
echo "\n" . json_encode(array('count' => $count, 'rows' => $rows)) . "\n";
