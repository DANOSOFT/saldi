<?php
// ------------- debitor/jobkort.php ----- (modul nr 6)------ lap 3.2.2 ----2011-08-05-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$feltantal=NULL;$returside=NULL;$ordre_id=NULL;$fokus=NULL;$ny=NULL;

$title="Jobkort";
$modulnr=6;
$kortnr=1;
$css="../css/standard.css";
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/db_query.php");


$luk=if_isset($_GET['luk']);

$kortnavn=findtekst(29,$sprog_id);

$id=if_isset($_GET['id']);
$select_id=if_isset($_GET['select_id']); 
$konto_id=if_isset($_GET['konto_id'])*1; 
$ordre_id=if_isset($_GET['ordre_id'])*1; 
$returside=if_isset($_GET['returside']); 
$opdat17=if_isset($_GET['opdat17']); 

#if ($ordre_id) $returside="ordre.php?id=$ordre_id";
#elseif ($popup) $returside="../includes/luk.php";
#else $returside="jobliste.php";

if($luk) {
	if ($r=db_fetch_array(db_select("select * from navigator where bruger_id='$bruger_id' and session_id='$s_id' and side='jobkort.php'",__FILE__ . " linje " . __LINE__))) {
		db_modify("delete from navigator where bruger_id='$bruger_id' and session_id='$s_id' and side='jobkort.php'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$r[returside]?konto_id=$r[konto_id]&ordre_id=$r[ordre_id]\">";
	} else print "<meta http-equiv=\"refresh\" content=\"0;URL=debitor.php\">";
	exit;
} elseif ($returside) db_modify("insert into navigator(bruger_id,session_id,side,returside,ordre_id,konto_id) values ('$bruger_id','$s_id','jobkort.php','$returside','$ordre_id','$konto_id')",__FILE__ . " linje " . __LINE__);

if (!$id && $ordre_id) {
	$r=db_fetch_array(db_select("select id from jobkort where ordre_id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
}
if (!$id && $konto_id) {
		$tidspkt=microtime();
		$initdate=date("Y-m-d");
		$ordre_id*=1;
		$r=db_fetch_array(db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		db_modify("insert into jobkort (konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,tlf,hvem,oprettet_af,initdate,tidspkt,ordre_id) values ('$konto_id', '$r[kontonr]', '".addslashes($r['firmanavn'])."', '".addslashes($r['addr1'])."', '".addslashes($r['addr2'])."','".addslashes($r['postnr'])."', '".addslashes($r['bynavn'])."', '$r[tlf]', '$hvem', '$hvem', '$initdate', '$tidspkt',$ordre_id)",__FILE__ . " linje " . __LINE__);
	  $r=db_fetch_array(db_select("select id from jobkort where konto_id='$konto_id'and hvem='$hvem' and tidspkt = '$tidspkt'",__FILE__ . " linje " . __LINE__));
		$id=$r['id'];
		$r=db_fetch_array(db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		if ($r['felt_1'] && findtekst(7,$sprog_id)==findtekst(255,$sprog_id)) {
			db_modify("update jobkort set felt_2='".addslashes($r['felt_1'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
		}
		if ($r['felt_2'] && findtekst(13,$sprog_id)==findtekst(256,$sprog_id)) {
			db_modify("update jobkort set felt_8='".addslashes($r['felt_2'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
		}
		if ($r['felt_3'] && findtekst(9,$sprog_id)==findtekst(257,$sprog_id)) {
			db_modify("update jobkort set felt_4='".addslashes($r['felt_3'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
		}
		if ($r['felt_4'] && findtekst(11,$sprog_id)==findtekst(258,$sprog_id)) {
			db_modify("update jobkort set felt_6='".addslashes($r['felt_4'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
		}
		if ($r['felt_5'] && findtekst(16,$sprog_id)==findtekst(259,$sprog_id)) {
			db_modify("update jobkort set felt_11='".addslashes($r['felt_5'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
		}
		if ($id && $ordre_id) {
			$r=db_fetch_array(db_select("select * from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
			if ($r['lev_navn']) db_modify("update jobkort set firmanavn='".addslashes($r['lev_navn'])."'where id='$id'",__FILE__ . " linje " . __LINE__); 
			if ($r['lev_addr1']) db_modify("update jobkort set addr1='".addslashes($r['lev_addr1'])."',addr2='".addslashes($r['lev_addr2'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			if ($r['lev_postnr']) db_modify("update jobkort set postnr='".addslashes($r['lev_postnr'])."',bynavn = '".addslashes($r['lev_bynavn'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			if ($r['felt_1'] && findtekst(7,$sprog_id)==findtekst(244,$sprog_id)) {
				db_modify("update jobkort set felt_2='".addslashes($r['felt_1'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			}
			if ($r['felt_2'] && findtekst(13,$sprog_id)==findtekst(245,$sprog_id)) {
				db_modify("update jobkort set felt_8='".addslashes($r['felt_2'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			}
			if ($r['felt_3'] && findtekst(9,$sprog_id)==findtekst(246,$sprog_id)) {
				db_modify("update jobkort set felt_4='".addslashes($r['felt_3'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			}
			if ($r['felt_4'] && findtekst(11,$sprog_id)==findtekst(247,$sprog_id)) {
				db_modify("update jobkort set felt_6='".addslashes($r['felt_4'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			}
			if ($r['felt_5'] && findtekst(16,$sprog_id)==findtekst(248,$sprog_id)) {
				db_modify("update jobkort set felt_11='".addslashes($r['felt_5'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
			}
		}
#		db_modify("update jobkort set felt_11='".addslashes($r['felt_5'])."' where id='$id'",__FILE__ . " linje " . __LINE__); 
	}
	if ($id && $ordre_id && $opdat17) {
		$ordrelinjer='';
		$q=db_select("select beskrivelse from ordrelinjer where ordre_id = '$ordre_id' order by posnr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$ordrelinjer.=addslashes($r['beskrivelse'])."\n";
		}
	}

#if (!$id && !$konto_id) find_konto();
if ($_POST){
	$udskriv=if_isset($_POST['udskriv']);
	$slet=if_isset($_POST['slet']);
	$konto_id=if_isset($_POST['konto_id']);
	$select_id=if_isset($_POST['select_id']); 
	$feltantal=if_isset($_POST['feltantal']);
	$felt_id=if_isset($_POST['felt_id']);
	$felt_indhold=if_isset($_POST['felt_indhold']);	
	$felt_1=addslashes(if_isset($_POST['felt_1']));	
	$felt_2=addslashes(if_isset($_POST['felt_2']));	
	$felt_3=addslashes(if_isset($_POST['felt_3']));	
	$felt_4=addslashes(if_isset($_POST['felt_4']));	
	$felt_5=addslashes(if_isset($_POST['felt_5']));	
	$felt_6=addslashes(if_isset($_POST['felt_6']));	
	$felt_7=addslashes(if_isset($_POST['felt_7']));	
	$felt_8=addslashes(if_isset($_POST['felt_8']));	
	$felt_9=addslashes(if_isset($_POST['felt_9']));	
	$felt_10=addslashes(if_isset($_POST['felt_10']));	
	$felt_11=addslashes(if_isset($_POST['felt_11']));	
	$x=1;
	$y=1;
	
	$id=$id*1;

	if ($slet) {
		db_modify("delete from jobkort_felter where job_id = '$id'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from jobkort where id = '$id'",__FILE__ . " linje " . __LINE__);
		$id=0;
	}

	db_modify("update jobkort set felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',felt_6='$felt_6',felt_7='$felt_7',felt_8='$felt_8',felt_9='$felt_9',felt_10='$felt_10',felt_11='$felt_11' where id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($x<=24) {
		$tmp1=if_isset($felt_id[$x][$y]);
		$tmp2=addslashes(if_isset($felt_indhold[$x][$y]));
		if ($x==2) $tmp2=usdate($tmp2);
		if ($x>=5 && $x<=7) $tmp2=usdecimal($tmp2);
		if ($felt_id[$x][$y]) db_modify("update jobkort_felter set indhold='$tmp2' where id = '$tmp1'",__FILE__ . " linje " . __LINE__);
		elseif ($felt_indhold[$x][$y]) db_modify("insert into jobkort_felter (job_id, indhold, feltnr, subnr) values ('$id','$tmp2','$x','$y')",__FILE__ . " linje " . __LINE__);
		if (isset($felt_indhold[$x][$y+1])) $y++;
		else {
			$y=1;
			$x++;
		}
	}	
	if ($udskriv) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL='jobkortprint.php?id=$id'\">";
	}
}
print "<div style=\"font-family: arial, verdana, sans-serif;\">";

if (!$konto_id && $id) {
	$r=db_fetch_array(db_select("select konto_id from jobkort where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
}
if (!$konto_id) {
		kontoopslag($id);
		exit;
}
/*
$q = db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
$kontonr=trim($r['kontonr']);
$firmanavn=stripslashes(stripslashes(trim($r['firmanavn'])));
$addr1=stripslashes(stripslashes(trim($r['addr1'])));
$addr2=stripslashes(stripslashes(trim($r['addr2'])));
$postnr=trim($r['postnr']);
$bynavn=stripslashes(stripslashes(trim($r['bynavn'])));
$tlf=trim($r['tlf']);
$fax=trim($r['fax']);
$email=trim($r['email']);
*/
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td colspan=3 align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\"$top_bund><a href=jobkort.php?luk=luk accesskey=L>".findtekst(30,$sprog_id)."</a><br></td>";
print "<td width=\"80%\"$top_bund>".findtekst(29,$sprog_id)."<br></td>";
print "<td width=\"10%\"$top_bund><a href=jobkort.php accesskey=N>".findtekst(39,$sprog_id)."</a><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td width=10% align=center></td><td width=80% align=center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\" widht=\"800\"><tbody>";

print "<form name=\"jobkort\" action=\"jobkort.php?id=$id\" method=\"post\">";
print "<input type=hidden name=konto_id value='$konto_id'>";
print "<input type=hidden name=select_id value='$select_id'>";

$r=db_fetch_array(db_select("select * from jobkort where id = '$id'",__FILE__ . " linje " . __LINE__));
$konto_id=$r['konto_id']*1;
$kontonr=trim($r['kontonr']);
$firmanavn=stripslashes($r['firmanavn']);
$tlf=stripslashes($r['tlf']);
$addr1=stripslashes($r['addr1']);
$addr2=stripslashes($r['addr2']);
$postnr=stripslashes($r['postnr']);
$bynavn=stripslashes($r['bynavn']);
$tlf=stripslashes($r['tlf']);
$felt_1=stripslashes($r['felt_1']);
$felt_2=stripslashes($r['felt_2']);
$felt_3=stripslashes($r['felt_3']);
$felt_4=stripslashes($r['felt_4']);
$felt_5=stripslashes($r['felt_5']);
$felt_6=stripslashes($r['felt_6']);
$felt_7=stripslashes($r['felt_7']);
$felt_8=stripslashes($r['felt_8']);
$felt_9=stripslashes($r['felt_9']);
$felt_10=stripslashes($r['felt_10']);
$felt_11=stripslashes($r['felt_11']);

if ($felt_3) $felt_3="checked";
if ($felt_5) $felt_5="checked";
if ($felt_7) $felt_7="checked";
if ($felt_9) $felt_9="checked";

$r=db_fetch_array(db_select("select fax,tlf from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
$fax=stripslashes($r['fax']);
$tlf=stripslashes($r['tlf']);

$z=0;
$felt_id=array(array());
$felt_indhold=array(array());
$felt=array(array());
for($x=1;$x<=11;$x++) $felt_indhold[$x][1]=NULL;
$q = db_select("select * from jobkort_felter where job_id = '$id' order by feltnr, subnr",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$z++;
	$x=$r['feltnr']*1;
	$y=$r['subnr']*1;
	$felt_id[$x][$y]=$r['id'];
	$felt_indhold[$x][$y]=stripslashes($r['indhold']);
	print "<input type=hidden name=felt_id[$x][$y] value='$r[id]'>";
}
$feltantal=$z;
$tmp=trim(findtekst(28,$sprog_id));
print "<tr><td colspan=\"6\"><table border=\"0\" width=\"100%\"><tbody>";
if ($tmp=="Firmanavn") $tekst="Title=\"Tip: Tekster kan ændres under Indstillinger -> Diverse -> Sprog -> Dansk!\"";
print "<tr><td width=\"20%\">".findtekst(6,$sprog_id)." $id</td><td align = center \"$tekst\" width=\"60%\">".findtekst(28,$sprog_id)."<!--tekst 28--></td>";
print "<td align=\"right\">".findtekst(27,$sprog_id)."<!--tekst 27--><input type=text size=1 name=felt_1 value=\"".$felt_1."\"></tr>";
if ($ordre_id) {
	$r=db_fetch_array(db_select("select ordrenr from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
	$ordrenr=$r['ordrenr'];
	print "<tr><td>".findtekst(500,$sprog_id)." $ordrenr</td></tr>";
}
print "</tbody></table></td></tr>";
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td colspan=4>$firmanavn<br></td><td width=10%><align=\"right\">Kontonr</td><td align=\"right\">$kontonr</td></tr>";
print "<tr><td colspan=4>$addr1<br></td><td width=10%><align=\"right\">".findtekst(377,$sprog_id)."<!--tekst 377--></td><td align=\"right\">$tlf</td></tr>";
print "<tr><td colspan=4>$addr2<br></td><td width=10%><align=\"right\">".findtekst(378,$sprog_id)."<!--tekst 378--></td><td align=\"right\">$fax</td></tr>";
print "<tr><td colspan=4>$postnr $bynavn<br></td><td width=10%><br></tr>";
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td>".findtekst(7,$sprog_id)."<!--tekst 7--></td><td colspan=2><input type=text size=40 name=felt_2 value=\"".$felt_2."\"><br></td>";
print "<td>".findtekst(8,$sprog_id)."<!--tekst 8--></td><td colspan=2><input type=checkbox name=felt_3 \"".$felt_3."\"></tr>";
print "<tr><td>".findtekst(9,$sprog_id)."<!--tekst 9--></td><td colspan=2><input type=text size=40 name=felt_4 value=\"".$felt_4."\"><br></td>";
print "<td>".findtekst(10,$sprog_id)."<!--tekst 10--></td><td colspan=2><input type=checkbox name=felt_5 \"".$felt_5."\"><br></tr>";
print "<tr><td>".findtekst(11,$sprog_id)."<!--tekst 11--></td><td colspan=2><input type=text size=40 name=felt_6 value=\"".$felt_6."\"><br></td>";
print "<td>".findtekst(12,$sprog_id)."<!--tekst 12--></td><td colspan=2><input type=checkbox name=felt_7 \"".$felt_7."\"><br></tr>";
print "<tr><td>".findtekst(13,$sprog_id)."<!--tekst 13--></td><td colspan=2><input type=text size=40 name=felt_8 value=\"".$felt_8."\"><br></td>";
print "<td>".findtekst(14,$sprog_id)."<!--tekst 14--></td><td colspan=2><input type=checkbox name=felt_9 \"".$felt_9."\"><br></tr>";
print "<tr><td>".findtekst(15,$sprog_id)."<!--tekst 15--></td><td colspan=2><input type=text size=40 name=felt_10 value=\"".$felt_10."\"><br></td></tr>";
print "<tr><td>".findtekst(16,$sprog_id)."<!--tekst 16--></td><td colspan=4><input type=text size=120 name=felt_11 value=\"".$felt_11."\"><br></tr>";
print "<tr><td colspan=6><hr></td></tr>";
print "";
if ($ordre_id) {
	$tekst=findtekst(501,$sprog_id);
	$a="<span title=\"Klik her for at opdatere fra ordre\" onclick=\"return confirm('$tekst')\"><a href=jobkort.php?id=$id&returside=$returside&$id&konto_id=$konto_id&ordre_id=$ordre_id&opdat17=1>";
	$b="</a>";
} else {
	$a=NULL;
	$b=NULL;
}
print "<tr><td colspan=6>$a".findtekst(17,$sprog_id)."$b<!--tekst 17--></td></tr>";
if (!$felt_indhold[1][1] && $ordrelinjer) $felt_indhold[1][1]=$ordrelinjer;
print "<tr><td colspan=6><textarea name=\"felt_indhold[1][1]\" rows=\"5\" cols=\"150\">".$felt_indhold[1][1]."</textarea></td></tr>\n";
print "<tr><td colspan=6><hr></td></tr>";
print "<tr><td>".findtekst(18,$sprog_id)."<!--tekst 18--></td><td>".findtekst(19,$sprog_id)."<!--tekst 19--></td><td>".findtekst(20,$sprog_id)."<!--tekst 20--></td><td>".findtekst(21,$sprog_id)."<!--tekst 21--></td><td>".findtekst(22,$sprog_id)."<!--tekst 22--></td><td>".findtekst(23,$sprog_id)."<!--tekst 23--></td></tr>";
$x=1;$sum6=0;$sum7=0;$sum8=0;
while (isset($felt_id[2][$x])|isset($felt_id[3][$x])|isset($felt_id[4][$x])|isset($felt_id[5][$x])|isset($felt_id[6][$x])|isset($felt_id[7][$x])) {
#	for($i=2;$i<=7;$i++) if (!isset($felt_indhold[$i][$x])) $felt_indhold[$i][$x]=NULL;
	$sum5=$sum5+$felt_indhold[5][$x];
	$sum6=$sum6+$felt_indhold[6][$x];
	$sum7=$sum7+$felt_indhold[7][$x];
	$tmp[2]=dkdato($felt_indhold[2][$x]);
	for($i=5;$i<=7;$i++) $tmp[$i]=dkdecimal($felt_indhold[$i][$x]);
	print "<tr><td><input type=text size=20 name=\"felt_indhold[2][$x]\" value=\"".$tmp[2]."\"></td><td><input type=text size=20 name=felt_indhold[3][$x] value=\"".$felt_indhold[3][$x]."\"></td>";
	print	"<td><input type=text size=20 name=felt_indhold[4][$x] value=\"".$felt_indhold[4][$x]."\"></td><td><input type=text style=\"text-align: right\" size=10 name=felt_indhold[5][$x] value=\"".$tmp[5]."\"></td>";
	print	"<td><input type=text style=\"text-align: right\" size=10 name=felt_indhold[6][$x] value=\"".$tmp[6]."\"></td><td><input type=text style=\"text-align: right\" size=10 name=felt_indhold[7][$x] value=\"".$tmp[7]."\"></td></tr>";
	$x++;
}
$sum5=dkdecimal($sum5);$sum6=dkdecimal($sum6);$sum7=dkdecimal($sum7);
print "<tr><td><input type=text size=20 name=felt_indhold[2][$x]></td><td><input type=text size=20 name=felt_indhold[3][$x]></td>";
print	"<td><input type=text size=20 name=felt_indhold[4][$x]></td><td><input type=text style=\"text-align: right\" size=10 name=felt_indhold[5][$x]></td>";
print	"<td><input type=text style=\"text-align: right\" size=10 name=felt_indhold[6][$x]></td><td><input type=text style=\"text-align: right\" size=10 name=felt_indhold[7][$x]></td></tr>";
print	"<td colspan=3></td><td><input type=readonly style=\"text-align: right\" size=10 value=$sum5></td><td><input type=readonly style=\"text-align: right\" size=10 value=$sum6></td><td><input type=readonly style=\"text-align: right\" size=10 value=$sum7></td></tr>";

print "<tr><td colspan=6>".findtekst(24,$sprog_id)."</td></tr>";
print "<tr><td colspan=6><textarea name=\"felt_indhold[8][1]\" rows=\"5\" cols=\"150\">".$felt_indhold[8][1]."</textarea></td></tr>\n";
print "<tr><td colspan=6><br></td></tr>";
print "<tr><td colspan=6>".findtekst(25,$sprog_id)."</td></tr>";
print "<tr><td colspan=6><textarea name=\"felt_indhold[9][1]\" rows=\"5\" cols=\"150\">".$felt_indhold[9][1]."</textarea></td></tr>\n";
print "<tr><td colspan=6><br></td></tr>";
print "<tr><td colspan=6>".findtekst(26,$sprog_id)."</td></tr>";
print "<tr><td colspan=6><textarea name=\"felt_indhold[10][1]\" rows=\"5\" cols=\"150\">".$felt_indhold[10][1]."</textarea></td></tr>\n";
print "<tr><td colspan=6><br></td></tr>";
print "<tr><td colspan=6 align=center><input type=submit accesskey=\"g\" value=\"Gem\" name=\"gem\">
				<input type=submit accesskey=\"u\" value=\"Udskriv\" name=\"udskriv\">
				<input type=submit accesskey=\"s\" value=\"Slet\" name=\"slet\"></td></tr>";
print "</form>";
print "</tbody></table>";
print "</td><td width=10%>";

function kontoopslag($id) {
	global $bgcolor;
	global $bgcolor5;	

	if ($find) $find=str_replace("*","%",$find);
	else $find="%";
	if (substr($find,-1,1)!='%') $find=$find.'%';
#	sidehoved($id, "jobkort.php", "../debitor/jobkort.php", $fokus, "Kundeordre $id - Kontoopslag");
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=jobkort.php?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kundenr</b></td>";
	print"<td><b><a href=jobkort.php?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=jobkort.php?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse</b></td>";
	print"<td><b><a href=jobkort.php?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Adresse2</b></td>";
	print"<td><b><a href=jobkort.php?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Postnr</b></td>";
	print"<td><b><a href=jobkort.php?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>bynavn</b></td>";
	print"<td><b><a href=jobkort.php?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>land</b></td>";
	print"<td><b><a href=jobkort.php?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Kontaktperson</b></td>";
	print"<td><b><a href=jobkort.php?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>tlf</b></td>";
	print" </tr>\n";

	$sort = $_GET['sort'];
	if (!$sort) {$sort = firmanavn;}
	if ($find) $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' order by $sort",__FILE__ . " linje " . __LINE__);
	else $query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' order by $sort",__FILE__ . " linje " . __LINE__);
	$fokus_id='id=fokus';
	while ($row = db_fetch_array($query)) {
		$kontonr=str_replace(" ","",$row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=jobkort.php?id=$id&konto_id=$row[id]>$row[kontonr]</a></td>";
		$fokus_id='';
		print "<td>".stripslashes($row[firmanavn])."</td>";
		print "<td>".stripslashes($row[addr1])."</td>";
		print "<td>".stripslashes($row[addr2])."</td>";
		print "<td>".stripslashes($row[postnr])."</td>";
		print "<td>".stripslashes($row[bynavn])."</td>";
		print "<td>".stripslashes($row[land])."</td>";
		print "<td>".stripslashes($row[kontakt])."</td>";
		print "<td>".stripslashes($row[tlf])."</td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}

?>
</td></tr>
</tbody></table>
</div>
</body></html>