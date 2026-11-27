<?php
// tests/characterization/support/run_settings_fiscal_save.php
//
// Child runner for SettingsFiscalYearSaveTest: saves one setting through SettingsService, as the
// settings page does, for a given fiscal year, and reports the grupper rows of its art and kodenr.
// The service needs the session's globals from online.php ($db, $sprog_id), so it runs here
// rather than in the PHPUnit process.
//
// argv: 1 = session id (must match the seeded online row)
//       2 = the fiscal year to save in ($regnaar)
//       3 = setting key, e.g. pos.tills.brand.1
//       4 = value
//       5 = grupper art, 6 = kodenr (the rows to report)
// prints JSON on the last line: {"saved": bool, "rows": [{"fiscal_year": int, "box1": string}, ...]}
//
// History:
// 20261125 CL/MJ SD-877 Created.
// 20261126 CL/MJ SD-877 Reports the boxes it is given (box1 when none).

if ($argc < 7) {
    fwrite(STDERR, "usage: php run_settings_fiscal_save.php <session_id> <regnaar> <key> <value> <art> <kodenr>\n");
    exit(2);
}
[, $sessionId, $year, $key, $value, $art, $kodenr] = $argv;
$boxes = isset($argv[7]) ? explode(',', $argv[7]) : array('box1');

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

$regnaar = (int) $year;
SettingsService::reset();
$saved = SettingsService::save($key, $value);

$rows = array();
$q = db_select("select fiscal_year, " . implode(', ', array_map(function ($b) { return preg_replace('/[^a-z0-9]/', '', $b); }, $boxes)) . " from grupper where art = '" . db_escape_string($art) . "' and kodenr = '" . db_escape_string($kodenr) . "' order by fiscal_year, id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $row = array('fiscal_year' => (int) $r['fiscal_year']);
    foreach ($boxes as $b) {
        $row[$b] = (string) $r[$b];
    }
    $rows[] = $row;
}
echo "\n" . json_encode(array('saved' => $saved, 'rows' => $rows)) . "\n";
