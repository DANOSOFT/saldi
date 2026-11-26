<?php
// --- admin/usage_snapshot_job.php --- ver 5.1.1 --- 2026.11.26 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Nightly usage snapshot (entitlements spec §7.3 step 1 / panels spec §5.5): for every open ledger measure usage in the customer database, store usage_snapshots for today and refresh regnskab.posteret/sidst so the lists are fresh without "Genberegn posteringer".
//                 CLI only: cd admin && php usage_snapshot_job.php [limit] Cron (owner's decision 10/10-2026: 02:00 every night), as the web server user: 0 2 * * * cd /var/www/html/<map>/admin && /usr/bin/php usage_snapshot_job.php >> ../temp/usage_snapshot.log 2>&1
// 20261029 CL/ASR Exits quietly until the update chain has created the panel's tables (operator_panel_schema flag).
// 20261116 CL/ASR The billing status is looked up by an exact customer number, CVR or name (bill_key(), review #785).
//                 The ledger's database is selected after db_connect() (db_connect_database()), and REQUEST_URI/REMOTE_ADDR get CLI defaults, since db_query.php's logging reads them.
// 20261124 CDX/NTR Use shared database compatibility helpers and the operator-panel structure include.
// 20261126 CL/ASR Each account's plan is fetched from its recurring invoicing orders in the billing ledger with the payment status (bill_plan_refresh(), admin/inc_betalinger.php).
//                 The job waits for the panel's schema at the current version (operator_panel_ready()), so a run between a deploy and the first login does not write to columns that are not there yet (Hugin's pre-check on #1227).
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("cli only\n"); }
$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] ?? '/admin/usage_snapshot_job.php'; $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; # db_query.php's logging reads both, and the CLI has neither
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
include("inc_installationer.php");
$brugernavn = 'cron'; $sprog_id = 1;
include_once("../includes/structure/operatorPanelSchema.php");
if (!operator_panel_ready()) exit("operator panel tables not created or not at the current version yet (next login to any account does it); nothing to do\n");
ent_tables_ensure(); partner_tables_ensure();
$limit = isset($argv[1]) ? (int)$argv[1] : 0;
$due = lc_apply_due(); # lifecycle first, so the list of open ledgers below reflects today's changes
$ids = array(); $q = db_select("select id, regnskab, db from regnskab where db != '$sqdb' and coalesce(lukket,'') != 'on' order by id", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) $ids[] = $r;
$n = 0; $bad = 0; $t0 = microtime(true);
foreach ($ids as $reg) ent_apply_due_changes((int)$reg['id']);
echo "lifecycle: $due due event(s) applied\n";
foreach ($ids as $reg) {
	if ($limit && $n + $bad >= $limit) break;
	$u = ent_usage_collect($reg);
	if ($u['ok']) { ent_snapshot_store((int)$reg['id'], $u); $n++; echo str_pad($reg['id'], 5)." ".str_pad($reg['regnskab'], 30)." users ".str_pad((string)$u['users_active'], 4)." post12m ".str_pad((string)$u['postings_12m'], 8)." month ".$u['postings_month']."\n"; }
	else { $bad++; echo str_pad($reg['id'], 5)." ".str_pad($reg['regnskab'], 30)." (ingen database)\n"; }
}
// billing status cache (skipped quietly when the billing API is unreachable, e.g. on test servers)
$bn = 0; $bpPlan = 0; $bpNew = 0; $bpNone = 0; $bpErr = 0;
if (get_saldi_api_token()) {
	foreach ($ids as $reg) {
		if ($limit && $bn >= $limit) break;
		$res = bill_plan_refresh($reg); $bn++;
		if (isset($res['error'])) $bpErr++; elseif ($res['plan']) { $bpPlan++; if ($res['changed']) $bpNew++; } else $bpNone++;
	}
	echo "billing: $bn customers checked\n";
	echo "plans: $bpPlan from a recurring order ($bpNew new or changed), $bpNone without one plan, $bpErr not found\n";
	$in = 0; foreach (inst_all() as $ir) { if ($ir['status'] == 'ended') continue; inst_payments($ir); $in++; } echo "billing: $in self-hosted installations checked\n";
} else echo "billing: API unreachable, cache not refreshed\n";
partner_log('ledger.snapshot', null, null, "$n regnskaber, $bad uden database, $bn betalingsstatus, $bpPlan pakker fra genfaktureringsordrer, ".round(microtime(true) - $t0, 1)." s");
echo "done: $n snapshots, $bad skipped, ".round(microtime(true) - $t0, 1)." s\n";
?>
