<?php
// --- admin/usage_snapshot_job.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Nightly usage snapshot (entitlements spec §7.3 step 1 / panels spec §5.5): for every open
//                  ledger measure usage in the customer database, store usage_snapshots for today and refresh
//                  regnskab.posteret/sidst so the lists are fresh without "Genberegn posteringer".
//                  CLI only:  cd admin && php usage_snapshot_job.php [limit]
//                  Cron (owner's decision 10/10-2026: 02:00 every night), as the web server user:
//                    0 2 * * * cd /var/www/html/<map>/admin && /usr/bin/php usage_snapshot_job.php >> ../temp/usage_snapshot.log 2>&1
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("cli only\n"); }
chdir(__DIR__);
include("../includes/connect.php");
if (!function_exists('db_select')) include("../includes/db_query.php");
include("../includes/std_func.php");
include("../includes/entitlements.php");
include("../includes/partnerScope.php");
include("vr_ui.php");
include("inc_lifecycle.php");
include("../includes/saldiBillingApi.php");
include("inc_betalinger.php");
$brugernavn = 'cron'; $sprog_id = 1;
ent_tables_ensure(); partner_tables_ensure();
$limit = isset($argv[1]) ? (int)$argv[1] : 0;
$ids = array(); $q = db_select("select id, regnskab, db from regnskab where db != '$sqdb' and lukket != 'on' order by id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $ids[] = $r;
$n = 0; $bad = 0; $t0 = microtime(true);
$due = lc_apply_due(); foreach ($ids as $reg) ent_apply_due_changes((int)$reg['id']);
echo "lifecycle: $due due event(s) applied\n";
foreach ($ids as $reg) {
	if ($limit && $n + $bad >= $limit) break;
	$u = ent_usage_collect($reg);
	if ($u['ok']) { ent_snapshot_store((int)$reg['id'], $u); $n++; echo str_pad($reg['id'], 5)." ".str_pad($reg['regnskab'], 30)." users ".str_pad((string)$u['users_active'], 4)." post12m ".str_pad((string)$u['postings_12m'], 8)." month ".$u['postings_month']."\n"; }
	else { $bad++; echo str_pad($reg['id'], 5)." ".str_pad($reg['regnskab'], 30)." (ingen database)\n"; }
}
// billing status cache (skipped quietly when the billing API is unreachable, e.g. on test servers)
$bn = 0;
if (get_saldi_api_token()) {
	foreach ($ids as $reg) {
		if ($limit && $bn >= $limit) break;
		$cfg = bill_get((int)$reg['id']); $cvr = '';
		if ($reg['db'] && db_exists($reg['db']) && @db_connect($sqhost, $squser, $sqpass, $reg['db'], __FILE__ . " linje " . __LINE__)) { $r = @db_fetch_array(@db_select("select cvrnr from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__)); if ($r) $cvr = str_replace(' ', '', preg_replace('/^DK\s*/i', '', trim((string)$r['cvrnr']))); include("../includes/connect.php"); }
		$data = bill_fetch($cfg['billing_kontonr'] ?: ($cvr ?: $reg['regnskab'])); bill_cache_store((int)$reg['id'], $data, $cfg); $bn++;
	}
	echo "billing: $bn customers checked\n";
} else echo "billing: API unreachable, cache not refreshed\n";
partner_log('ledger.snapshot', null, null, "$n regnskaber, $bad uden database, $bn betalingsstatus, ".round(microtime(true) - $t0, 1)." s");
echo "done: $n snapshots, $bad skipped, ".round(microtime(true) - $t0, 1)." s\n";
?>
