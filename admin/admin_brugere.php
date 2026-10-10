<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// -- systemdata/admin_brugere.php ------------- lap 4.0.8 -- 2023-02-27 --
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ------------------------------------------------------------------------
// 20210328 PHR Some cleanup.
// 20210917 LOE translated some texts
// 20230227 CA  Add missing parameters on some calls to db_select & db_modify
// 20230323 PBLM Fixed some minor errors
// 20260908 CL/NTR Reject usernames over 80 characters (is_input_too_long) on create/update, matching login.php

@session_start();
$s_id=session_id();

$modulnr=104;
$css="../css/standard.css";

include("../includes/std_func.php");
#$title=findtekst("Brugere", $sprog_id);
include("../includes/connect.php");
include("../includes/online.php");

include("../includes/topline_settings.php");
include("../includes/partnerScope.php");
include("vr_ui.php");
partner_tables_ensure();
$vr_alerts = array();
$ret_id = if_isset($_GET['ret_id'], 0);
$slet_id=if_isset($_GET['slet_id'], 0);

if ($_POST) {
	$submit=$_POST['submit'];
	$id=$_POST['id'];	
	$tmp=$_POST['random'];
	$ret_bruger=trim($_POST[$tmp]);
	$kode=trim($_POST['kode']);
	$kode2=trim($_POST['kode2']);
	$ret_bruger=trim($ret_bruger);
	if (is_input_too_long($ret_bruger)) {
		$alerttext=findtekst('5149|Brugernavnet må højst være 80 tegn', $sprog_id);
		$vr_alerts[] = $alerttext;
		$ret_bruger=NULL;
	}
	$admin=$_POST['admin'];
	$oprette=if_isset($_POST['oprette'], 0);
	$slette=if_isset($_POST['slette'], 0);
	$adgang_til=addslashes(trim($_POST['adgang_til']));

	$rettigheder="$admin,$oprette,$slette,$adgang_til";

	if ($kode && $kode != $kode2) {
			$alerttext=findtekst('1345|Begge adgangskoder skal være ens', $sprog_id).".";
			$vr_alerts[] = $alerttext;
			$kode=NULL;
			$ret_id=$id;
	}
	if (($kode) && (!strstr($kode,'**********'))) {
		$insetKode = $kode;
		$kode=saldikrypt($id,$kode);
	} elseif($kode)	{
		$query = db_select("select * from brugere where id = '$id'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query))
		$kode=trim($row['kode']);
	}
	if ((strstr($submit,'Tilf'))&&($ret_bruger)&&($ret_bruger!="-")) {
		$query = db_select("select id from brugere where brugernavn = '$ret_bruger'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$txt = findtekst('1928|Der findes allerede en bruger med dette brugernavn', $sprog_id); 
			$alerttext="$txt: $ret_bruger!";
			$vr_alerts[] = $alerttext;
#			print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $ret_bruger!</td></tr>\n";
		}	else {
			db_modify("insert into brugere (brugernavn,rettigheder) values ('$ret_bruger','$rettigheder')",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from brugere where brugernavn = '$ret_bruger'",__FILE__ . " linje " . __LINE__));
			$kode = saldikrypt($r['id'],$insetKode);
			db_modify("update brugere set kode='$kode' where id=$r[id]",__FILE__ . " linje " . __LINE__);
		}
	} elseif ((strstr($submit,'Opdat'))&&($ret_bruger)&&($ret_bruger!="-")) {
		db_modify("update brugere set brugernavn='$ret_bruger',kode='$kode',rettigheder='$rettigheder' where id=$id",__FILE__ . " linje " . __LINE__);
	}
	elseif (($id)&&($ret_bruger=="-")) {db_modify("delete from brugere where id = $id",__FILE__ . " linje " . __LINE__);}
}

$pu = array(); $q = db_select("select pu.bruger_id, p.name from partner_users pu join partners p on p.id = pu.partner_id", __FILE__ . " linje " . __LINE__); while ($pr = db_fetch_array($q)) $pu[(int)$pr['bruger_id']] = $pr['name'];
$r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
$bruger_id=$r['id'];
list($br_admin,$tmp)=explode(",",$r['rettigheder'],2);
if (!$br_admin) { $ret_id=$bruger_id; $disabled="disabled"; } else $disabled="";
vr_open(array(array('Administrationspanel', 'admin_panel.php'), findtekst('1927|Admin brugere', $sprog_id)), findtekst('1927|Admin brugere', $sprog_id), vr_t('Brugere i administrationslaget: operatører og brugere med adgang til udvalgte regnskaber. Bogholderes medarbejdere styres under Bogholdere og koncerner.', 'Users in the administration layer: operators and users with access to selected accounts. Accountant employees are managed under Accountants and groups.'), $br_admin ? "<a class=\"vr-btn vr-primary\" href=\"admin_brugere.php#ny\">+ ".findtekst('333|Ny bruger', $sprog_id)."</a>" : '');
vr_note($vr_alerts, 'warn');
$fieldsRow = function($row, $isNew) use ($sprog_id, $disabled) {
	$nm = $isNew ? "navn".rand(100,999) : $row['id'];
	list($admin,$oprette,$slette,$adgang_til)=array_pad(explode(",", $isNew ? ',,,' : $row['rettigheder'], 4), 4, '');
	$h = "<form name=\"bruger\" action=\"admin_brugere.php\" method=\"post\" class=\"vr-form\" style=\"max-width:none\">";
	$h .= "<input type=\"hidden\" name=\"random\" value=\"$nm\">".($isNew ? "" : "<input type=\"hidden\" name=\"id\" value=\"".(int)$row['id']."\">");
	$h .= "<div class=\"vr-inline\" style=\"gap:14px;align-items:flex-end\"><label>".($isNew ? findtekst('333|Ny bruger', $sprog_id) : findtekst('225|Brugernavn', $sprog_id))."<input class=\"vr-inp\" type=\"text\" maxlength=\"80\" name=\"$nm\" value=\"".($isNew ? '' : vr_h($row['brugernavn']))."\" title=\"".($isNew ? '' : findtekst('326|Skriv - (minus) som brugernavn for at slette bruger', $sprog_id))."\" required></label>";
	$h .= "<label title=\"".findtekst('334|Sæt * for adgang til alle regnskaber eller skriv en liste med ID på de regnskaber det skal være adgang til', $sprog_id)."\">".findtekst('329|Adgang til', $sprog_id)."<input class=\"vr-inp\" type=\"text\" name=\"adgang_til\" value=\"".vr_h($adgang_til)."\" placeholder=\"* ".vr_t('eller id-liste','or id list')."\" $disabled></label>";
	$h .= "<label>".findtekst('324|Adgangskode', $sprog_id)."<input class=\"vr-inp\" type=\"password\" name=\"kode\" value=\"".($isNew ? '' : '********************')."\"></label><label>".findtekst('328|Gentag adgangskode', $sprog_id)."<input class=\"vr-inp\" type=\"password\" name=\"kode2\" value=\"".($isNew ? '' : '********************')."\"></label></div>";
	$h .= "<div class=\"vr-inline\" style=\"gap:18px\"><label class=\"vr-chk\" title=\"".findtekst('335|Afmærk her, hvis brugeren skal have administratorrettigheder', $sprog_id)."\"><input type=\"checkbox\" name=\"admin\" value=\"on\"".($admin ? " checked" : "")." $disabled> ".findtekst('330|Administrator', $sprog_id)."</label><label class=\"vr-chk\" title=\"".findtekst('336|Afmærk her, hvis brugeren skal kunne oprette regnskaber og efterfølgende have adgang til disse', $sprog_id)."\"><input type=\"checkbox\" name=\"oprette\" value=\"on\"".($oprette ? " checked" : "")." $disabled> ".vr_t('Må oprette regnskaber','May create accounts')."</label><label class=\"vr-chk\" title=\"".findtekst('337|Afmærk her, hvis brugeren skal kunne slette de regnskaber, de har adgang til', $sprog_id)."\"><input type=\"checkbox\" name=\"slette\" value=\"on\"".($slette ? " checked" : "")." $disabled> ".vr_t('Må slette regnskaber','May delete accounts')."</label></div>";
	if ($disabled) $h .= "<input type=\"hidden\" name=\"adgang_til\" value=\"".vr_h($adgang_til)."\"><input type=\"hidden\" name=\"admin\" value=\"".vr_h($admin)."\"><input type=\"hidden\" name=\"oprette\" value=\"".vr_h($oprette)."\"><input type=\"hidden\" name=\"slette\" value=\"".vr_h($slette)."\">";
	$h .= "<div class=\"vr-inline\"><button type=\"submit\" class=\"vr-btn vr-primary\" name=\"submit\" value=\"".($isNew ? findtekst('1175|Tilføj', $sprog_id) : 'Opdatér')."\">".($isNew ? findtekst('1175|Tilføj', $sprog_id) : findtekst('898|Opdatér', $sprog_id))."</button>".($isNew ? "" : " <a class=\"vr-btn vr-quiet\" href=\"admin_brugere.php\">".findtekst('5|Annullér', $sprog_id)."</a><span class=\"vr-mut2\" style=\"font-size:12.5px\">".findtekst('326|Skriv - (minus) som brugernavn for at slette bruger', $sprog_id)."</span>")."</div></form>";
	return $h;
};
if ($br_admin) {
	print "<section class=\"vr-sect\"><h2>".vr_t('Alle brugere','All users')."</h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".findtekst('225|Brugernavn', $sprog_id)."</th><th>".findtekst('329|Adgang til', $sprog_id)."</th><th>".vr_t('Partner','Partner')."</th><th>".findtekst('330|Administrator', $sprog_id)."</th><th>".vr_t('Opret','Create')."</th><th>".vr_t('Slet','Delete')."</th><th></th></tr></thead><tbody>";
	$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		list($admin,$oprette,$slette,$adgang_til)=array_pad(explode(",",$row['rettigheder'],4), 4, '');
		$ok = fn($v) => $v ? '<span class="vr-st"><span class="vr-dot vr-ok"></span></span>' : '<span class="vr-mut2">–</span>';
		print "<tr".((int)$row['id'] == (int)$ret_id ? " style=\"background:var(--surface-2)\"" : "")."><td class=\"vr-nm\"><a href=\"admin_brugere.php?ret_id=".(int)$row['id']."\">".vr_h($row['brugernavn'])."</a></td><td class=\"vr-mut vr-num\">".($adgang_til === '*' ? vr_t('alle','all') : vr_h($adgang_til))."</td><td class=\"vr-mut\">".(isset($pu[(int)$row['id']]) ? vr_h($pu[(int)$row['id']]) : '<span class="vr-mut2">–</span>')."</td><td>".$ok($admin)."</td><td>".$ok($oprette)."</td><td>".$ok($slette)."</td><td class=\"vr-r\"><a class=\"vr-link\" href=\"admin_brugere.php?ret_id=".(int)$row['id']."\">".findtekst('1206|Ret', $sprog_id)."</a></td></tr>";
	}
	print "</tbody></table></div></section>";
}
if ($ret_id) {
	$row = db_fetch_array(db_select("select * from brugere where id = ".(int)$ret_id,__FILE__ . " linje " . __LINE__));
	if ($row) { print "<section class=\"vr-sect\" id=\"ret\"><h2>".findtekst('1206|Ret', $sprog_id).": ".vr_h($row['brugernavn'])."</h2><div class=\"vr-card\">".$fieldsRow($row, false)."</div></section>"; }
} elseif ($br_admin) {
	print "<section class=\"vr-sect\" id=\"ny\"><h2>".findtekst('333|Ny bruger', $sprog_id)."</h2><div class=\"vr-card\">".$fieldsRow(null, true)."</div></section>";
}
vr_close();
?>
</body></html>
