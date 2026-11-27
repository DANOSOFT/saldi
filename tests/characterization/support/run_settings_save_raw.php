<?php
// tests/characterization/support/run_settings_save_raw.php
//
// Child-process runner for SettingsService::saveRaw() called on its own, as
// setting_save() calls it: without a page and without a transaction of the
// caller's. Bootstraps the tenant as bootstrap_ordrefunc.php does.
//
// argv: 1 = tenant database
//       2 = setting key (systemdata/settingsDefinitions.php)
//       3 = the stored string to write
//
// Prints one JSON line on stdout:
//   changed - what saveRaw() returned (true when the stored value changed)
//   depth   - the transaction depth after the call (0: nothing left open)
//
// History:
// 20261119 CL/ASR Created for SettingsCompositeSaveRaceTest.

if ($argc < 4) {
    fwrite(STDERR, "usage: php run_settings_save_raw.php <tenant_db> <setting key> <stored value>\n");
    exit(2);
}
$tenantDb = $argv[1];
$chartestKey = $argv[2];
$chartestRaw = $argv[3];

require __DIR__ . '/bootstrap_ordrefunc.php';
require_once __DIR__ . '/../../../includes/settings/SettingsService.php';

$chartestChanged = SettingsService::saveRaw($chartestKey, $chartestRaw);
while (ob_get_level() > 0) {
    ob_end_clean();
}
fwrite(STDOUT, "\n" . json_encode(['changed' => $chartestChanged, 'depth' => (int) ($GLOBALS['db_transaktion_depth'] ?? 0)]) . "\n");
