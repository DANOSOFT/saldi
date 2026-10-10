<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------/admin/slet_regnskab.php-----patch 3.8.9------2020.02.27--------
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
if ($_POST['regnskabsantal']) {
	$regnskabsantal=$_POST['regnskabsantal'];
	$id=$_POST['id'];
	$db_navn=$_POST['db_navn'];
	$regnskab=$_POST['regnskab'];
	$slet=$_POST['slet'];

	if ($regnskabsantal) {
		$slet_antal=0;
		for ($x=1; $x<=$regnskabsantal; $x++) {
			if ($slet[$x]=='on'){
			 	$slet_antal++;
				$mappe='../nedlagte_regnskaber/';
				$tmpmappe='../nedlagte_regnskaber/'.$db_navn[$x];
				if (!file_exists($mappe)) mkdir("$mappe", 0777);
				mkdir("$tmpmappe", 0777);
				if (file_exists($tmpmappe)) {
					$logofil="../logolib/logo_".$db_id[$x].".eps";
					$dump_filnavn=$tmpmappe."/".trim($db_navn[$x].".sql");
					$info_filnavn=$tmpmappe."/backup.info";
					$tgz_filnavn=trim($db_navn[$x]."_".date("Ymd-Hi")).".tgz";
					$tgz_filnavn=trim($db_navn[$x]."_".date("Ymd-Hi")).".sdat";
					$tidspkt= date("d-m-Y H:i");
					$infotekst="$regnskab[$x] slettet $tidspkt af $brugernavn";$fp=fopen($info_filnavn,"w");
					$fp=fopen($info_filnavn,"w");
					if ($fp) {
						fwrite($fp,"$timestamp".chr(9)."$db".chr(9)."$dbver".chr(9)."$regnskab".chr(9)."$db_encode".chr(9)."$db_type".chr(9)."$infotekst");
					} 
					fclose($fp);
					if ($db_type=='mysql') system ("mysqldump -h $sqhost -u $squser --password=$sqpass -n $db_navn[$x] > $dump_filnavn");
					else system ("export PGPASSWORD=$sqpass\npg_dump -h $sqhost -U $squser -f $dump_filnavn $db_navn[$x]");
					//system("export PGPASSWORD=$sqpass\npg_dump -h $sqhost -U $squser -f $dump_filnavn $db_navn[$x]");
					system ("cd $mappe\ntar -pzcf $tgz_filnavn $db_navn[$x]\nmv $tgz_filnavn $dat_filnavn\nrm -r $tmpmappe");
					if (file_exists("$mappe/$dat_filnavn")) {
 						//print "Sletter regnskab: $regnskab[$x]<br>";
						
						if ($r=db_fetch_array(db_select("select id from kundedata where regnskab_id='$id[$x]'",__FILE__ . " linje " . __LINE__))) {
							$qtxt="update kundedata set slettet='on' where id='$r[id]'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
						} else {
							$qtxt="update kundedata set slettet='on',regnskab_id='$id[$x]' where regnskab='".db_escape_string($regnskab[$x])."'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
						}
						$qtxt="delete from regnskab where id = $id[$x]";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt="DROP DATABASE IF EXISTS $db_navn[$x]";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$slettet_regnskab=$regnskab[$x];
					} else $vr_err[] = "Backupfejl - $regnskab[$x] ikke slettet";
				}
			}	
		}
		$vr_msg = ($slet_antal==1) ? "$slettet_regnskab slettet" : "$slet_antal regnskaber slettet";
		partner_log('ledger.deleted', null, null, $vr_msg);
		}
}
$q = db_select("select * from brugere where brugernavn = '$brugernavn'");
$r = db_fetch_array($q);
list($admin,$oprette,$slette,$tmp)=explode(",",$r['rettigheder'],4);
$adgang_til=explode(",",$tmp);

$x=0;
$q1= db_select("select id, regnskab, db from regnskab where db != '$sqdb' and lukket='on' order by id",__FILE__ . " linje " . __LINE__);
while ($r1=db_fetch_array($q1)) {
	if ($admin || in_array($r1['id'],$adgang_til)) {
		$x++;
		$id[$x]=$r1['id'];
		$regnskab[$x]=$r1['regnskab'];	
		$db_navn[$x]=$r1['db'];
	}
}
$regnskabsantal=$x;

vr_open(array(array('Administrationspanel', 'admin_panel.php'), findtekst('341|Slet regnskab', $sprog_id)), findtekst('341|Slet regnskab', $sprog_id), vr_t('Kun regnskaber, der er markeret som lukket, kan slettes. Der tages en sikkerhedskopi til nedlagte_regnskaber, før databasen fjernes.', 'Only accounts marked as closed can be deleted. A backup is written to nedlagte_regnskaber before the database is dropped.'));
if ($vr_msg) vr_note(array($vr_msg)); if ($vr_err) vr_note($vr_err, 'warn');
print "<section class=\"vr-sect\"><h2>".vr_t('Lukkede regnskaber','Closed accounts')." <small>$regnskabsantal</small></h2><div class=\"vr-card\">";
print "<form name=\"slet_regnskab\" action=\"slet_regnskab.php\" method=\"post\" onsubmit=\"return Slet_Regnskab()\">";
print "<table class=\"vr-t\"><thead><tr><th style=\"width:40px\"></th><th class=\"vr-r vr-id\">Id</th><th>".findtekst('2682|Regnskab', $sprog_id)."</th><th>".vr_t('Database','Database')."</th></tr></thead><tbody>";
for ($x=1; $x<=$regnskabsantal; $x++) {
	print "<input type=\"hidden\" name=\"id[$x]\" value=\"".(int)$id[$x]."\"><input type=\"hidden\" name=\"db_navn[$x]\" value=\"".vr_h($db_navn[$x])."\"><input type=\"hidden\" name=\"regnskab[$x]\" value=\"".vr_h($regnskab[$x])."\">";
	print "<tr><td><input type=\"checkbox\" name=\"slet[$x]\"></td><td class=\"vr-r vr-id\">".(int)$id[$x]."</td><td class=\"vr-nm\"><a href=\"admin_panel.php?regnskab_id=".(int)$id[$x]."\">".vr_h($regnskab[$x])."</a></td><td class=\"vr-mut\">".vr_h($db_navn[$x])."</td></tr>";
}
if (!$regnskabsantal) print "<tr><td colspan=\"4\" class=\"vr-mut\" style=\"padding:24px 20px\">".vr_t('Ingen regnskaber er markeret som lukket. Luk et regnskab fra kundekortets Indstillinger først.','No accounts are marked as closed. Close an account from the customer card first.')."</td></tr>";
print "</tbody></table><input type=\"hidden\" name=\"regnskabsantal\" value=\"$regnskabsantal\">";
print "<div class=\"vr-foot\"><span>".vr_t('Sletning kan ikke fortrydes. Sikkerhedskopien ligger på serveren.','Deletion cannot be undone. The backup stays on the server.')."</span><span class=\"vr-grow\"></span>".($regnskabsantal ? "<button type=\"submit\" class=\"vr-btn vr-danger\" accesskey=\"a\" name=\"submit\" value=\"OK\">".vr_t('Slet valgte','Delete selected')."</button>" : "")."</div></form></div></section>";
vr_close();
?>
</body></html>
