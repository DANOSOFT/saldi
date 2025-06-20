<?php
// ----------lager/ret_varenr.php-------------patch 3.5.9 -- 20151208----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2015.12.08 Tilføjet flet funtion til sammenlægning af varer.

@session_start();
$s_id=session_id();

$title="Ret varenummer";
$modulnr=9;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (isset($_GET['id'])) $id = $_GET['id'];
elseif(isset($_POST['id'])) {
	$id = $_POST['id'];
	$varenr = $_POST['varenr'];
	$nyt_varenr = db_escape_string(trim($_POST['nyt_varenr']));
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
print "<tr><td width=\"10%\" $top_bund><a href=varekort.php?id=$id accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">$title</td>";
print "<td width=\"10%\" $top_bund align=\"right\"><br></td></tr>";
print "</tbody></table>";
print "</td></tr>\n";
print "<tr><td>\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=100% valign = \"center\" align = \"center\"><tbody>\n";

if (($nyt_varenr) && ($nyt_varenr!=$varenr)) {
	if ($r=db_fetch_array(db_select("select id from varer where varenr = '$nyt_varenr' ",__FILE__ . " linje " . __LINE__))) {
		print tekstboks('Varenummer: $nyt_varenr er i brug, varenummer ikke &aelig;ndret');
	}	elseif (substr($nyt_varenr,0,1)=='=') {
		$fletvnr=substr($nyt_varenr,1); 
		if ($varenr == $fletvnr) {
			print tekstboks("Varenummer: $varenr kan ikke sammenlægges med sig selv");
			print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
			exit;
		}
		elseif ($r=db_fetch_array(db_select("select id from varer where varenr = '$fletvnr'",__FILE__ . " linje " . __LINE__))) {
			print tekstboks("Varenummer: $varenr sammenlægges med $fletvnr",__FILE__ . " linje " . __LINE__);
			flet($id,$varenr,$r['id'],$fletvnr);
			exit;
		} else {
			print tekstboks("Varenr $fletvnr ikke fundet",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
			exit;
		}
	}	else {
		db_modify("update varer set varenr='$nyt_varenr' where id='$id'",__FILE__ . " linje " . __LINE__);
		$x=0;
		$q=db_select("select ordrelinjer.id as ordrelinje_id, ordrer.art as art, ordrer.ordrenr as ordrenr from ordrelinjer, ordrer where ordrer.status<3 and ordrelinjer.ordre_id = ordrer.id and ordrelinjer.vare_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			db_modify("update ordrelinjer set varenr='$nyt_varenr' where id='$r[ordrelinje_id]'",__FILE__ . " linje " . __LINE__);
			if ($x==1) echo "<tr><td>Varenummer rettet i f&oslash;lgende ordrer: $r[ordrenr]";
			else echo ", $r[ordrenr]";
		}
		if ($x>=1)echo "</td></tr><tr><td><hr></td></tr>";
		print "<BODY onLoad=\"javascript:alert('Varenummer er rettet fra $varenr til $nyt_varenr')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id\">";

	}
}

if ($r=db_fetch_array(db_select("select varenr from varer where id = '$id'",__FILE__ . " linje " . __LINE__))) $varenr=$r['varenr'];

print "<form name=ret_varenr action=ret_varenr.php method=post>"
;
print "<tr><td align=center>Varenummer rettes i alle uafsluttede ordrer, tilbud, indk&oslash;bsforslag og indk&oslash;bsordrer</td></tr>";
print "<tr><td align=center>Bem&aelig;rk at hvis der er brugere som er ved at redigere en ordre kan dette bevirke at varenummeret ikke &aelig;ndres</td></tr>";
print "<tr><td align=center>i den p&aring;g&aelig;ldende ordre. Det anbefales derfor at tilse at &oslash;vrige brugere lukker alle ordrevinduer.</td></tr>";
print "<tr><td align=center>&AElig;ndring af varenummer har ingen indflydelse p&aring; varestatestik eller andet, bortset fra at varen vil figurere</td></tr>";
print "<tr><td align=center>med det gamle varenummer i ordrer som er afsluttet f&oslash;r &aelig;ndringsdatoen.</td></tr>";
print "<tr><td align=center><hr></td></tr>";
print "<tr><td align=center>Det er også muligt at sammenlægger 2 varenumre til 1. Hef skal du skrive det varenummer som du vil lægge denne</td></tr>";
print "<tr><td align=center>ind i og sætter et lighedstegn foran, f.eks.: '=100' </td></tr>";
print "<tr><td align=center>Så vil al historik mm, varebeholdning og evt.leverandør og shop bindinger blive lagt sammen til 1 vare, og varenr $varenummer vil blive slettet</td></tr>";

print "<tr><td align=center><hr width=50%></td></tr>";
print "<tr><td align=center>Ret varenummer $varenr til: <input type=text name=nyt_varenr  width=30 value=\"$varenr\"></td></tr>";
print "<input type=hidden name=id  width=30 value='$id'>";
print "<input type=hidden name=varenr  width=30 value=\"$varenr\">";
print "<tr><td align=center><input type=submit value=\"Ret\" name=\"submit\"></td></tr>";
print "</form>";

print "</tbody></table";
print "</td></tr>\n";
print "</tbody></table";

function flet($id,$varenr,$flet_id,$flet_vnr){

	if ($r=db_fetch_array(db_select("select id,shop_id from shop_varer where saldi_id = '$id'",__FILE__ . " linje " . __LINE__))){
		$shop_id=$r['shop_id'];
		$r=db_fetch_array(db_select("select samlevare from varer where id = '$flet_id'",__FILE__ . " linje " . __LINE__));
		$fletsamlevare=$r['samlevare'];
		if ($r=db_fetch_array(db_select("select id,shop_id from shop_varer where saldi_id = '$flet_id'",__FILE__ . " linje " . __LINE__))) {
			if ($shop_id!=$r['shop_id']) {
				print tekstboks("Varenummer: $varenr har en shop_relation til shop vare med id: $shop_id og $fletvnr relaterer til shop vare $r[shop_id]<br> Sammenlægning kan ikke gennemføres");
				print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
				exit;	
			}
		}
	}
	transaktion('begin');
	print tekstboks("Varenummer: $varenr sammenlægges med $fletvnr");
	$r=db_fetch_array(db_select("select beholdning from varer where id = '$id'",__FILE__ . " linje " . __LINE__));
	if ($r['beholdning']) {
		$fletbeholdning=$r['beholdning'];
		if ($fletsamlevare) {
			$x=0;
			$q=db_select("select * from styklister where indgaar_i='$flet_id'");
			while ($r=db_fetch_array($q)) {
				$vare_id[$x]=$r['vare_id'];
				$antal[$x]=$r['antal'];
				$x++;
			}
			for ($x=0;$x<count($vare_id);$x++){
			$r=db_fetch_array(db_select("select gruppe,beholdning from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				$r2=db_fetch_array(db_select("select box8 from grupper where art='VG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				if ($r2['box8']=='on') {
					$ny_beholdning=$r['beholdning']+($antal[$x]*$fletbeholdning);
					db_modify("update varer set beholdning = '$ny_beholdning' where id ='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}	else db_modify("update varer set beholdning=beholdning+$r[beholdning] where id = '$flet_id'",__FILE__ . " linje " . __LINE__);
	}
	db_modify("update batch_salg set vare_id = '$flet_id' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("update batch_kob set vare_id = '$flet_id' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("update ordrelinjer set vare_id = '$flet_id', varenr = '$flet_vnr' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("update vare_lev set vare_id = '$flet_id' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select id from shop_varer where saldi_id = '$flet_id'",__FILE__ . " linje " . __LINE__));
	if (!$r['saldi_id']) db_modify("update shop_varer set saldi_id = '$flet_id' where saldi_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("delete from varer where id = '$id'",__FILE__ . " linje " . __LINE__);
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$flet_id\">";
}
?>