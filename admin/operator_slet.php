<?php
ob_start(); # for the redirect after an action (vr_prg)
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------/admin/operator_slet.php-----patch 3.8.9------2026.10.29--------
// 20261012 CL/ASR Operator copy of admin/slet_regnskab.php for Saldi staff only (vr_require_operator). The original page is
//                  unchanged for bookkeepers and everyone else; this copy carries the operator panel's changes.
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2020.02.27 PHR Check if db exist before dropping 20200227
// 20261010 CL/ASR Rendered in the admin-layer shell; deletion logic unchanged. Duplicate doctype removed; the
//                  deleted-ledger message and list use the shared components.
// 20261029 CL/ASR Trailing whitespace removed from the lines this branch adds.

@session_start();
$s_id=session_id();

$title="Slet regnskaber";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("vr_ui.php");
vr_require_operator(); # Saldi staff only; everyone else keeps the existing pages
partner_tables_ensure();
if ($db != $sqdb) {
	print "<BODY onLoad=\"javascript:alert('".findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id)."')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}
$modulnr=103;

?>
<script LANGUAGE="JavaScript">
<!--
function Slet_Regnskab()
{
 var agree=confirm("Slet de valgte regnskaber?");
	if (agree)
		return true ;
	else
    return false ;
}
// -->
</script>
<?php

if (!$font) $font="Helvetica, Arial, sans-serif";
if (!$top_bund) $top_bund="style=\"border: 1px solid rgb(0, 0, 0); padding: 0pt 0pt 1px;\" align=\"center\" background=\"../img/knap_bg.gif\";";
?>
<?php
$id=array();$db_navn=array();$regnskab=array();$slet=array();$vr_msg='';$vr_err=array();
// 20261012 CL/ASR Deletion hardened: only the ticked ids are taken from the form; name and database come from the
//                  master table, and each ledger must be closed, not suspended and within the user's scope (before,
//                  the posted db name went straight into shell commands and DROP DATABASE). The backup check now looks
//                  at the real .sdat file (the variable it used was never set, so the check always passed). Each
//                  deleted ledger is logged; the page then redirects (vr_prg) so a reload cannot repeat the deletion.
include("inc_lifecycle.php"); lc_tables_ensure();
$vr_deletable = function($rid) use ($sqdb) {
	$scope = partner_ledgers(); if ($scope !== null && !in_array((int)$rid, $scope)) return null;
	$r = db_fetch_array(db_select("select id, regnskab, db, lukket from regnskab where id = '".(int)$rid."' and db != '".db_escape_string($sqdb)."'", __FILE__ . " linje " . __LINE__));
	if (!$r || $r['lukket'] != 'on' || !preg_match('/^[a-z0-9_]+$/i', (string)$r['db'])) return null;
	$st = lc_state((int)$rid, $r); if ($st['state'] == 'suspended') return null;
	return $r;
};
if (isset($_POST['regnskabsantal'])) {
	$pid = (array)if_isset($_POST, array(), 'id'); $pslet = (array)if_isset($_POST, array(), 'slet'); $done = array();
	foreach ($pslet as $x => $on) {
		if ($on != 'on' || !isset($pid[$x])) continue;
		$r = $vr_deletable((int)$pid[$x]);
		if (!$r) { $vr_err[] = vr_t('Regnskab','Account').' '.(int)$pid[$x].' '.vr_t('kan ikke slettes (ikke lukket, suspenderet eller uden adgang)','cannot be deleted (not closed, suspended or no access)'); continue; }
		$dbn = $r['db']; $mappe = '../nedlagte_regnskaber/'; $tmpmappe = $mappe.$dbn;
		if (!file_exists($mappe)) mkdir($mappe, 0777);
		if (!file_exists($tmpmappe)) mkdir($tmpmappe, 0777);
		if (!file_exists($tmpmappe)) { $vr_err[] = "Backupfejl - $r[regnskab] ikke slettet"; continue; }
		$dump_filnavn = $tmpmappe."/".$dbn.".sql"; $dat_filnavn = $dbn."_".date("Ymd-Hi").".sdat";
		if ($fp = fopen($tmpmappe."/backup.info", "w")) { fwrite($fp, date("YmdHis").chr(9).$dbn.chr(9).chr(9).$r['regnskab'].chr(9)."$db_encode".chr(9)."$db_type".chr(9)."$r[regnskab] slettet ".date("d-m-Y H:i")." af $brugernavn"); fclose($fp); }
		if ($db_type == 'mysql' || $db_type == 'mysqli') system("mysqldump -h ".escapeshellarg($sqhost)." -u ".escapeshellarg($squser)." --password=".escapeshellarg($sqpass)." -n ".escapeshellarg($dbn)." > ".escapeshellarg($dump_filnavn));
		else system("export PGPASSWORD=".escapeshellarg($sqpass)."\npg_dump -h ".escapeshellarg($sqhost)." -U ".escapeshellarg($squser)." -f ".escapeshellarg($dump_filnavn)." ".escapeshellarg($dbn));
		$dumpOk = file_exists($dump_filnavn) && filesize($dump_filnavn) > 0;
		if ($dumpOk) system("cd ".escapeshellarg($mappe)."\ntar -pzcf ".escapeshellarg($dat_filnavn)." ".escapeshellarg($dbn));
		system("rm -r ".escapeshellarg($tmpmappe));
		if (!$dumpOk || !file_exists($mappe.$dat_filnavn) || !filesize($mappe.$dat_filnavn)) { $vr_err[] = "Backupfejl - $r[regnskab] ikke slettet"; continue; }
		if ($k = db_fetch_array(db_select("select id from kundedata where regnskab_id='".(int)$r['id']."'", __FILE__ . " linje " . __LINE__))) db_modify("update kundedata set slettet='on' where id='$k[id]'", __FILE__ . " linje " . __LINE__);
		else db_modify("update kundedata set slettet='on',regnskab_id='".(int)$r['id']."' where regnskab='".db_escape_string($r['regnskab'])."'", __FILE__ . " linje " . __LINE__);
		db_modify("delete from regnskab where id = '".(int)$r['id']."'", __FILE__ . " linje " . __LINE__);
		db_modify("DROP DATABASE IF EXISTS $dbn", __FILE__ . " linje " . __LINE__);
		partner_log('ledger.deleted', (int)$r['id'], null, "$r[regnskab] ($dbn), backup nedlagte_regnskaber/$dat_filnavn");
		$done[] = $r['regnskab'];
	}
	if ($done) $vr_msg = count($done) == 1 ? $done[0].' '.vr_t('er slettet','deleted') : count($done).' '.vr_t('regnskaber slettet','accounts deleted');
	vr_prg(array_merge($vr_msg ? array($vr_msg) : array(), $vr_err), null, $vr_err ? 'warn' : 'ok');
}
$vr_flash = vr_flash();
// 20261010 CL/ASR db_select() was called with one argument here, which is fatal on PHP 8 (the page never loaded).
//                  Scope now comes from partnerScope.php: operators see everything, others their own ledgers.
$vr_scope = partner_ledgers();
$x=0;
$q1= db_select("select id, regnskab, db from regnskab where db != '$sqdb' and lukket='on' and id not in (select regnskab_id from regnskab_lifecycle l where l.applied_at is not null and l.cancelled_at is null and l.state = 'suspended' and l.id = (select max(id) from regnskab_lifecycle where regnskab_id = l.regnskab_id and applied_at is not null and cancelled_at is null)) order by id",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	if ($vr_scope === null || in_array((int)$r1['id'],$vr_scope)) {
		$x++;
		$id[$x]=$r1['id'];
		$regnskab[$x]=$r1['regnskab'];
		$db_navn[$x]=$r1['db'];
	}
}
$regnskabsantal=$x;

vr_open(array(array('Administrationspanel', 'operator_panel.php'), findtekst('341|Slet regnskab', $sprog_id)), findtekst('341|Slet regnskab', $sprog_id), vr_t('Kun regnskaber, der er lukket, kan slettes. Suspenderede regnskaber vises ikke her. Der tages en sikkerhedskopi til nedlagte_regnskaber, før databasen fjernes.', 'Only accounts marked as closed can be deleted. A backup is written to nedlagte_regnskaber before the database is dropped.'));
vr_note($vr_flash, vr_flash_kind());
print "<section class=\"vr-sect\"><h2>".vr_t('Lukkede regnskaber','Closed accounts')." <small>$regnskabsantal</small></h2><div class=\"vr-card\">";
print "<form name=\"slet_regnskab\" action=\"operator_slet.php\" method=\"post\" onsubmit=\"return Slet_Regnskab()\">";
print "<table class=\"vr-t\"><thead><tr><th style=\"width:40px\"></th><th class=\"vr-r vr-id\">Id</th><th>".findtekst('2682|Regnskab', $sprog_id)."</th><th>".vr_t('Database','Database')."</th></tr></thead><tbody>";
for ($x=1; $x<=$regnskabsantal; $x++) {
	print "<input type=\"hidden\" name=\"id[$x]\" value=\"".(int)$id[$x]."\"><input type=\"hidden\" name=\"db_navn[$x]\" value=\"".vr_h($db_navn[$x])."\"><input type=\"hidden\" name=\"regnskab[$x]\" value=\"".vr_h($regnskab[$x])."\">";
	print "<tr><td><input type=\"checkbox\" name=\"slet[$x]\"></td><td class=\"vr-r vr-id\">".(int)$id[$x]."</td><td class=\"vr-nm\"><a href=\"operator_panel.php?regnskab_id=".(int)$id[$x]."\">".vr_h($regnskab[$x])."</a></td><td class=\"vr-mut\">".vr_h($db_navn[$x])."</td></tr>";
}
if (!$regnskabsantal) print "<tr><td colspan=\"4\" class=\"vr-mut\" style=\"padding:24px 20px\">".vr_t('Ingen regnskaber er markeret som lukket. Luk et regnskab fra kundekortets Indstillinger først.','No accounts are marked as closed. Close an account from the customer card first.')."</td></tr>";
print "</tbody></table><input type=\"hidden\" name=\"regnskabsantal\" value=\"$regnskabsantal\">";
print "<div class=\"vr-foot\"><span>".vr_t('Sletning kan ikke fortrydes. Sikkerhedskopien ligger på serveren.','Deletion cannot be undone. The backup stays on the server.')."</span><span class=\"vr-grow\"></span>".($regnskabsantal ? "<button type=\"submit\" class=\"vr-btn vr-danger\" accesskey=\"a\" name=\"submit\" value=\"OK\">".vr_t('Slet valgte','Delete selected')."</button>" : "")."</div></form></div></section>";
vr_close();
?>
</body></html>
