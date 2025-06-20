<?php
// ---------------debitor/ordreliste.php---lap 3.2.2------2011-12-22----
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
#ob_start();
@session_start();
$s_id=session_id();

$check_all=NULL; $ny_sort=NULL;

print "
<script LANGUAGE=\"JavaScript\">
<!--
function MasseFakt(tekst)
{
	var agree = confirm(tekst);
	if (agree)
		return true ;
	else
    return false ;
}
// -->
</script>
";
$css="../css/standard.css";
$modulnr=5;
$title="Ordreliste - Debitorer";
$dk_dg=NULL; $vis_projekt=NULL;
$firmanavn=NULL; $firmanavn_ant=NULL; $hurtigfakt=NULL; $konto_id=NULL; $linjebg=NULL; $checked=NULL; $totalkost=NULL; $understreg=NULL;
$find=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
	
#print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Kunder</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";


$konto_id = if_isset($_GET['konto_id']);

$id = $_GET['id'];
$returside=if_isset($_GET['returside']);
$valg= strtolower(if_isset($_GET['valg']));
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$kontoid= if_isset($_GET['kontoid']);
$genberegn = if_isset($_GET['genberegn']);
$start = if_isset($_GET['start']);

if (!$returside && $konto_id && !$popup) $returside="debitorkort.php?id=$konto_id";

if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'",__FILE__ . " linje " . __LINE__))) $hurtigfakt='on';
if ($valg=="tilbud" && $hurtigfakt) $valg="ordrer"; 
if (!$valg) $valg="ordrer";
$tjek=array("tilbud","ordrer","faktura","pbs");
if (!in_array($valg,$tjek)) $valg='ordrer';
#if ($valg=="ordrer" && $sort=="fakturanr") $sort="ordrenr";
$sort=str_replace("ordrer.","",$sort);
if ($sort && $nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;

if ($r=db_fetch_array(db_select("select id from adresser where art = 'S' and pbs_nr > '0'",__FILE__ . " linje " . __LINE__))) {
 $pbs=1;
} else $pbs=0;

if (!$r=db_fetch_array(db_select("select id from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
#	db_modify("update grupper set box2='$returside' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
#} else { 
	if ($valg=="tilbud") {
		$box3="ordrenr,ordredate,kontonr,firmanavn,ref,sum";
		$box5="right,left,left,left,left,right";
		$box4="50,100,100,150,100,100";
		$box6="Tilbudsnr.,Tilbudsdato,Kontonr.,Firmanavn,S&aelig;lger,Tilbudssum";
	} elseif ($valg=="ordrer") {
		$box3="ordrenr,ordredate,levdate,kontonr,firmanavn,ref,sum";
		$box5="right,left,left,left,left,left,right";
		$box4="50,100,100,100,150,100,100";
		$box6="Ordrenr.,Ordredato,Levdato,Kontonr.,Firmanavn,S&aelig;lger,Ordresum";
	} else {
		$box3="ordrenr,ordredate,fakturanr,fakturadate,nextfakt,kontonr,firmanavn,ref,sum";
		$box5="right,left,right,left,left,left,left,left,right";
		$box4="50,100,100,100,100,150,100,100";
		$box6="Ordrenr.,Ordredato,Fakt.nr.,Fakt.dato,Genfakt.,Kontonr.,Firmanavn,S&aelig;lger,Fakturasum";
	}
	db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box2,box3,box4,box5,box6,box7) values ('Ordrelistevisning','$valg','$bruger_id','OLV','$returside','$box3','$box4','$box5','$box6','100')",__FILE__ . " linje " . __LINE__);
} else {
	$r=db_fetch_array(db_select("select box2,box7,box8,box9 from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__)); 
	if (!$returside) {
		$returside=$r['box2'];
		if (strstr($returside,"debitorkort.php?id=") && !$konto_id) {
			list($tmp,$konto_id)=explode("=",$returside);
		}
	}
	$linjeantal=$r['box7'];
	if (!$sort) $sort=$r['box8'];
#	echo "$r[box9]<br>";
	$find=explode("\n",$r['box9']);
}
if (!$returside) {
#	$r=db_fetch_array(db_select("select box2,box7 from grupper where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__)); 
#	$returside=$r['box2'];
#	$linjeantal=$r['box7'];
	if ($popup) $returside= "../includes/luk.php";
	else $returside= "../index/menu.php";
} elseif (!$popup && $returside=="../includes/luk.php") $returside="../index/menu.php";
db_modify("update grupper set box2='$returside',box8='$sort' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);
if (!$popup) db_modify("update ordrer set hvem='', tidspkt='' where hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
		
$tidspkt=date("U");
 
#if (isset($_POST)) {
if ($submit=if_isset($_POST['submit'])) {
	if (strstr($submit, "Genfaktur")) $submit="Genfakturer";
	$find=if_isset($_POST['find']);
	$valg=if_isset($_POST['valg']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$firma=if_isset($_POST['firma']);
	$kontoid=if_isset($_POST['kontoid']);
	$firmanavn_ant=if_isset($_POST['firmanavn_antal']);
}
if (($firma)&&($firmanavn_ant>0)) {
	for ($x=1; $x<=$firmanavn_ant; $x++) {
		$tmp="firmanavn$x";
		if ($firma==$_POST[$tmp]) {
			$tmp="konto_id$x";
			$kontoid=$_POST[$tmp];
		}
	}
}
elseif ($firmanavn_ant>0) {$kontoid='';}

if (!$valg) $valg = "ordrer";
if (!$sort) $sort = "firmanavn";

$sort=str_replace("ordrer.","",$sort); #2008.02.05
$sortering=$sort;

if ($valg!='faktura') {
#	$fakturanumre='';
#	$fakturadatoer='';
	$genfakturer='';
}
if ($valg=="tilbud") {$status="status = 0";}
elseif ($valg=="faktura") {$status="status >= 3";}
else {$status="(status = 1 or status = 2)";}

if (db_fetch_array(db_select("select distinct id from ordrer where projekt > '0' and $status",__FILE__ . " linje " . __LINE__))) $vis_projekt='on';

if ($submit=="Udskriv"){
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	$checked = if_isset($_POST['checked']);
	
	for ($x=1; $x<=$ordre_antal; $x++){
		if ($checked[$x]=="on") {
			$y++;
			if (!$udskriv) $udskriv=$ordre_id[$x];
			else $udskriv=$udskriv.",".$ordre_id[$x];
		}
	}
	if ($y>0) {
		print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4&udskriv_til=PDF' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	}
	else print "<BODY onLoad=\"javascript:alert('Ingen fakturaer er markeret til udskrivning!')\">";
}
if (isset($_POST['check'])||isset($_POST['uncheck'])) {
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	if (isset($_POST['check'])) $check_all='on';
}

if ($submit=="Genfakturer" || $submit=="Ret"){
	$ordre_antal = if_isset($_POST['ordre_antal']);
	$ordre_id = if_isset($_POST['ordre_id']);
	$checked = if_isset($_POST['checked']);

	for ($x=1; $x<=$ordre_antal; $x++){
		if ($checked[$x]=="on") {
			$y++;
			if (!$genfakt) $genfakt=$ordre_id[$x];
			else $genfakt=$genfakt.",".$ordre_id[$x];
		}
	}
	if ($y>0) {
		if ($popup) { 
			if ($submit=="Ret") {
				print "<BODY onLoad=\"JavaScript:window.open('ret_genfakt.php?ordreliste=$genfakt' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
			} else print "<BODY onLoad=\"JavaScript:window.open('genfakturer.php?id=-1&ordre_antal=$y&genfakt=$genfakt' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
		} else {
			if ($submit=="Ret") print "<meta http-equiv=\"refresh\" content=\"0;URL=ret_genfakt.php?ordreliste=$genfakt\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=genfakturer.php?id=-1&ordre_antal=$y&genfakt=$genfakt\">";
		}
	}
	else print "<BODY onLoad=\"javascript:alert('Ingen fakturaer er markeret til genfakturering!')\">";
} 
print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>\n";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody><td width=10% $top_bund>";
#if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
#else print "<a href=\"../index/menu.php\" accesskey=\"L\">Luk</a></td>";
print "<a href=$returside accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund align=center><table border=0 cellspacing=2 cellpadding=0><tbody>\n";
if ($valg=='tilbud' && !$hurtigfakt) {print "<td width = 20% align=center $knap_ind>&nbsp;Tilbud&nbsp;</td>";}
elseif (!$hurtigfakt) {print "<td width = 20% align=center><a href='ordreliste.php?valg=tilbud&konto_id=$konto_id&returside=$returside'>&nbsp;Tilbud&nbsp;</a></td>";}
if ($valg=='ordrer') {print "<td width = 20% align=center $knap_ind>&nbsp;Ordrer&nbsp;</td>";}
else {print "<td width = 20% align=center><a href='ordreliste.php?valg=ordrer&konto_id=$konto_id&returside=$returside'>&nbsp;Ordrer&nbsp;</a></td>";}
if ($valg=='faktura') print "<td width = 20% align=center $knap_ind>&nbsp;Faktura&nbsp;</td>";
else print "<td width = 20% align=center><a href='ordreliste.php?valg=faktura&konto_id=$konto_id&returside=$returside'>&nbsp;Faktura&nbsp;</a></td>";
if ($valg=='pbs') print "<td width = 20% align=center $knap_ind>&nbsp;PBS&nbsp;</td>";
elseif ($pbs) print "<td width = 20% align=center><a href='ordreliste.php?valg=pbs&konto_id=$konto_id&returside=$returside'>&nbsp;PBS&nbsp;</a></td>";

print "</tbody></table></td>\n";

if ($valg=='pbs') {
	if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('pbs_import.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Import PBS</a></td>\n";
	else  print "<td width=10% $top_bund><a href=pbs_import.php?returside=ordreliste.php>Import PBS</a></td>\n";
	include("pbsliste.php");
	exit;
}
#echo "select box3,box4,box5, box6 from grupper where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'<br>";
$r = db_fetch_array(db_select("select box3,box4,box5, box6 from grupper where art = 'OLV' and kodenr = '$bruger_id' and kode='$valg'",__FILE__ . " linje " . __LINE__));
$vis_felt=explode(",",$r['box3']);
$feltbredde=explode(",",$r['box4']);
$justering=explode(",",$r['box5']);
$feltnavn=explode(",",$r['box6']);
$vis_feltantal=count($vis_felt);
$selectfelter=array("konto_id","firmanavn","addr1","addr2","bynavn","land","kontakt","lev_navn","lev_addr1","lev_addr2","lev_postnr","lev_bynavn","lev_kontakt","ean","institution","betalingsbet","betalingsdage","cvrnr","art","momssats","ref","betalt","valuta","sprog","email","mail_fakt","pbs","mail","mail_cc","mail_bcc","mail_subj","mail_text","udskriv_til");

####################################################################################
$udvaelg=NULL;
$tmp=trim($find[0]);
for ($x=1;$x<$vis_feltantal;$x++) $tmp=$tmp."\n".trim($find[$x]);
$tmp=addslashes($tmp);
# echo "update grupper set box9='$tmp' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'<br>";
db_modify("update grupper set box9='$tmp' where art = 'OLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);

for ($x=0;$x<$vis_feltantal;$x++) {
	if ($feltbredde[$x]<=10) $feltbredde[$x]*=10;
	if (!$feltbredde[$x]) $feltbredde[$x]=100;
	$find[$x]=addslashes(trim($find[$x]));
	if ($find[$x]=="-") $find[$x]=NULL; 
	if ($find[$x] && ($vis_felt[$x]=='firmanavn' || $vis_felt[$x]=='kontonr') && !strpos("$find[$x]",":")) {
		$d=0;
		$tmplist=array();
		if ($vis_felt[$x]=='firmanavn' && !$konto_id) $q=db_select("select distinct(konto_id) as konto_id from ordrer where firmanavn = '$find[$x]'",__FILE__ . " linje " . __LINE__);
		elseif(!$konto_id) {
			$q=db_select("select distinct(konto_id) as konto_id from ordrer where kontonr = '$find[$x]'",__FILE__ . " linje " . __LINE__);
		}
		while ($r=db_fetch_array($q)) {
			$d++;
			$tmpliste[$d]=$r['konto_id'];
		}
		if ($d) {
			$tmp=$d;
				$udvaelg.="and(ordrer.konto_id='$tmpliste[1]'";
			for($d=2;$d<=$tmp;$d++) {
				$udvaelg.=" or ordrer.konto_id='$tmpliste[$d]'";
			} 
			$udvaelg.=")";
		} elseif (!$konto_id) $udvaelg.="and ordrer.konto_id='0'"; 
	} else {
		$tmp=$vis_felt[$x];
		if ($tmp=='ordrenr' && $find[$x]) {
			if (strlen($find[$x])>=11) $find[$x]=substr($find[$x],0,10);
			$find[$x]*=1;
		}
		if ($tmp=='kontonr' && $find[$x]) {
			$find[$x]*=1;
		}
		if (in_array($vis_felt[$x],$selectfelter) && ($find[$x]||$find[$x]=="0")) {
			$udvaelg= $udvaelg." and ordrer.$tmp='$find[$x]'";
		} elseif ((strpos($vis_felt[$x],"date") || $vis_felt[$x]=="nextfakt") && ($find[$x]||$find[$x]=="0")) {
			if ($vis_felt[$x]=="nextfakt") $genfakturer="1";
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'DATO');
		} elseif ($vis_felt[$x]=="sum" && ($find[$x]||$find[$x]=="0")) {
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'BELOB');
		} elseif ($find[$x]||$find[$x]=="0") {
			$tmp2="ordrer.".$tmp."";
			$udvaelg=$udvaelg.udvaelg($find[$x],$tmp2, 'NR');
		}
	}
}

if (strstr($sortering,'fakturanr')) {
	if ($db_type=='mysql') $sortering=str_replace("fakturanr","CAST(ordrer.fakturanr AS SIGNED)",$sortering); 
	else $sortering=str_replace("fakturanr","to_number(textcat('0',ordrer.fakturanr),text(99999999))",$sortering);
} else $sortering="ordrer.".$sortering;
# if (strstr($udvaelg,'fakturanr')) $udvaelg=str_replace("fakturanr","fakturanr::varchar::numeric",$udvaelg);
$ordreliste="";

if ($valg=="tilbud") $status="status < 1";
elseif ($valg=="ordrer" && $hurtigfakt) $status="status < 3"; 
elseif ($valg=="ordrer") $status="(status = 1 or status = 2)"; 
else $status="status >= 3";

$ialt=0;
$lnr=0;
if (!$linjeantal) $linjeantal=100;
#$start=0;
$slut=$start+$linjeantal;
$ordreantal=0;

if ($konto_id) $udvaelg=$udvaelg."and konto_id=$konto_id";
# echo "select count(id) as antal from ordrer where (art = 'DO' or art = 'DK') and $status $udvaelg<br>";
$r=db_fetch_array(db_select("select count(id) as antal from ordrer where (art = 'DO' or art = 'DK' or (art = 'PO' and konto_id > '0')) and $status $udvaelg",__FILE__ . " linje " . __LINE__));
$antal=$r['antal'];

if ($valg=='pbs') {
#	if ($popup) print "<td width=10% $top_bund onClick=\"javascript:ordre=window.open('pbs_import.php?returside=ordreliste.php','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href=ordreliste.php?sort=$sort>Import PBS</a></td>\n";
#	else  print "<td width=10% $top_bund><a href=pbs_import.php?returside=ordreliste.php>Import PBS</a></td>\n";
} else {
		print "<td width=5% $top_bund><a accesskey=V href=ordrevisning.php?valg=$valg>Visning</a></td>\n";
if ($popup) {
		print "<td width=5% $top_bund onClick=\"javascript:ordre=window.open('ordre.php?returside=ordreliste.php&konto_id=$konto_id','ordre','scrollbars=1,resizable=1');ordre.focus();\"><a accesskey=N href='".$_SERVER['PHP_SELF']."'>Ny</a></td>\n";
	} else {
		print "<td width=5%  $top_bund><a href=ordre.php?returside=ordreliste.php?konto_id=$konto_id>Ny</a></td>\n";
	}
}
print "</td></tr>\n";

print "</tbody></table>";
print " </td></tr>\n<tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 valign=top<tbody>\n<tr>";
if ($start>0) {
	$tmp=$start-$linjeantal;
	if ($tmp<0) $tmp=0;
	print "<td><a href=ordreliste.php?start=$tmp&valg=$valg&konto_id=$konto_id><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print "<td></td>";
# if ($valg=='tilbud') {
for ($x=0;$x<$vis_feltantal;$x++) {
		if (!$feltbredde[$x]) $feltbredde[$x]*="100";
		elseif ($feltbredde[$x]<15) $feltbredde[$x]*="10";
	if ($feltbredde[$x]) {
		$width="width=\"$feltbredde[$x]px\"";
	} else $width="";
	print "<td align=$justering[$x] $width><b><a href='ordreliste.php?nysort=$vis_felt[$x]&sort=$sort&valg=$valg'>$feltnavn[$x]</b></td>\n";
}
$tmp=$start+$linjeantal;
if ($antal>$slut) print "<td align=right><a href=ordreliste.php?start=$tmp&valg=$valg&konto_id=$konto_id><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
print "</tr>\n";

#################################### Sogefelter ##########################################


print "<form name=ordreliste action=ordreliste.php?konto_id=$konto_id method=post>";
print "<input type=hidden name=valg value=$valg>";
print "<input type=hidden name=sort value='$ny_sort'>";
print "<input type=hidden name=nysort value='$sort'>";
print "<input type=hidden name=kontoid value=$kontoid>";

print "<tr><td></td>";
#if ($valg=='tilbud') {
	for ($x=0;$x<$vis_feltantal;$x++) {
		if (!$feltbredde[$x]) $feltbredde[$x]*="100";
		elseif ($feltbredde[$x]<15) $feltbredde[$x]*="10";
		if ($feltbredde[$x]) {
			$width="width:$feltbredde[$x]px";
		} else $width="";
		if ($konto_id && ($vis_felt[$x]=="kontonr" || $vis_felt[$x]=="firmanavn")) $span = 'Listen er &aring;bnet fra debitorkort - s&oslash;gefelt deaktiveret';
		elseif (strpos($vis_felt[$x],"nr")) $span = 'Skriv et nummer eller skriv to adskilt af kolon (f.eks 345:350)';
		elseif (strpos($vis_felt[$x],"date") || $vis_felt[$x]=="nextfakt") $span = 'Skriv en dato eller to datoer adskilt af kolon (f.eks 011009:311009)';
		elseif ($vis_felt[$x]=="sum") $span = 'Skriv et beb&oslash;b eller to adskilt af kolon (f.eks 525,25:525,50)';
		else $span=''; 
		print "<td align=$justering[$x]><span title= '$span'>";
#echo "$konto_id && ($vis_felt[$x]==\"kontonr\" || $vis_felt[$x]==\"firmanavn\"<br>";		
		if ($konto_id && ($vis_felt[$x]=="kontonr" || $vis_felt[$x]=="firmanavn")) {
			$r=db_fetch_array(db_select("select $vis_felt[$x] as tmp from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
			print "<input class=\"inputbox\" type=text readonly=$readonly style=\"text-align:$justering[$x];$width;\" name=find[$x] value=\"$r[tmp]\">";
		} elseif (in_array($vis_felt[$x],$selectfelter)) {
			$tmp=$vis_felt[$x];
			print "<SELECT NAME=\"find[$x]\" class=\"inputbox\" style=\"$width;\">";
# echo "select distinct($tmp) from ordrer where (art = 'DO' or art = 'DK') and status <= 1<br>";			
			if ($valg=="tilbud") $status = "status < 1";
			elseif ($valg=="ordrer" && $hurtigfakt) $status  = "status <= 2";
			elseif ($valg=="ordrer") $status  = "(status >= 1 and status <= 2)";
			else $status  = "status >= 3";
# echo "select distinct($tmp) from ordrer where (art = 'DO' or art = 'DK') and $status<br>";			
			$q=db_select("select distinct($tmp) from ordrer where (art = 'DO' or art = 'DK' or (art = 'PO' and konto_id > '0')) and $status order by $tmp",__FILE__ . " linje " . __LINE__);
			print "<option>".stripslashes($find[$x])."</option>";
			if ($find[$x]) print "<option></option>";
			while ($r=db_fetch_array($q)) {
				print "<option>$r[$tmp]</option>";
			}
			print "</SELECT></td>";			
		} else print "<input class=\"inputbox\" type=text style=\"text-align:$justering[$x];$width;\" name=find[$x] value=\"$find[$x]\">";
	}
	print "</td>\n";  
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr><td></td>\n";

######################################################################################################################
if ($genfakt) $checked=array();
print "<form name=fakturaprint action=ordreliste.php?valg=$valg$hreftext&start=$start method=post>";
$query = db_select("select * from ordrer where (art = 'DO' or art = 'DK' or (art = 'PO' and konto_id > '0')) and $status $udvaelg order by $sortering",__FILE__ . " linje " . __LINE__);

while ($row=db_fetch_array($query)) {
	$lnr++;
	if($lnr>=$start && $lnr<$slut) {
		$ordreantal++;
		if ($ordreliste) $ordreliste=$ordreliste.",".$row['id'];
		else $ordreliste=$row['id'];
		$ordre="ordre".$row['id'];
		$sum=$row['sum'];
		$kostpris=$row['kostpris'];
		$valutakurs=$row['valutakurs'];
		$nextfakt=$row['nextfakt'];
#echo "G $nextfakt<br>";
		if ($valg=='faktura') {
			$tmp=$row['sum']+$row['moms'];
			$udlignet=0;
#echo "select udlignet from openpost where faktnr = '$row[fakturanr]' and konto_id='$row[konto_id]' and amount='$tmp'<br>";
			if ($r=db_fetch_array(db_select("select udlignet from openpost where faktnr = '$row[fakturanr]' and konto_id='$row[konto_id]' and amount='$tmp'",__FILE__ . " linje " . __LINE__))) {
				$udlignet=$r['udlignet']*1;	
			} else { # 20101220 Denne del er indsat grundet enkelte forekomster med manglende faktnr  
				$tmp1="Faktura - ".$row['fakturanr'];
				$tmp2="Faktura - ".$row['fakturanr']." - ".$row['fakturadate'];
# echo "select id,udlignet from openpost where (beskrivelse = '$tmp1' or beskrivelse = '$tmp2') and konto_id='$row[konto_id]' and amount='$tmp'<br>";
				if ($r=db_fetch_array(db_select("select id,udlignet from openpost where (beskrivelse = '$tmp1' or beskrivelse = '$tmp2') and konto_id='$row[konto_id]' and amount='$tmp'",__FILE__ . " linje " . __LINE__))) {
					$udlignet=$r['udlignet']*1;
# echo "update openpost set faktnr='$row[fakturanr]' where id = '$r[id]'<br>";
					db_modify("update openpost set faktnr='$row[fakturanr]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
					$message=$db." | ".$tmp2." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $fejltekst";
					$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
					mail('fejl@saldi.dk', 'SALDI Opdater openpost', $message, $headers);
				}
			}
		}
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
			if ($popup) {
				$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" ";
				$understreg='<span style="text-decoration: underline;">';
				$hrefslut="";
			} else {
				$javascript="";
				$understreg="<a href=ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php>";
				$hrefslut="</a>";
			}
			$linjetext="";
		} else {
			$javascript="onClick=\"javascript:$ordre.focus();\"";
			$understreg='';
			$linjetext="<span title= 'Ordre er l&aring;st af $row[hvem]'>";
		}


		if ( $valg == "ordrer" && $bgnuance1 ) {
			$q2=db_select("select antal,leveres,leveret from ordrelinjer where ordre_id = '$row[id]'",__FILE__ . " linje " . __LINE__);
			$levstatus=0;
			while ($r2=db_fetch_array($q2)) {
				if ( $levstatus === "Mangler" ) {
					continue;
				} 
				if ( ( $r2['leveret'] > 0 &&  $r2['antal'] > $r2['leveret'] ) || ( $r2['antal'] > ($r2['leveres']+$r2['leveret'] ) ) ) {
					$levstatus="Mangler";
				} elseif ( $r2['leveret'] == 0 && ( $r2['antal'] == $r2['leveres'] ) ) {
					if ( $levstatus === "Leveret" ) {
						$levstatus="Mangler";
					} else {
						$levstatus="Intet";
					}
				} elseif ( ( ! $levstatus == "Intet" ) && $r2['leveret'] > 0 && $r2['antal'] == $r2['leveret'] ) {
					$levstatus="Leveret";
				}
			}
	
			if ( $levstatus == "Mangler" ) {
				$bgnuance=$bgnuance1;
				$color='#000000';
				if ($row['art']=='DK') {
					$tr_title="Mangler modtagelse af en eller flere vare.";
				} else {
					$tr_title="Mangler levering af en eller flere vare.";
				}
			} elseif ( $levstatus == "Leveret" ) {
				$bgnuance=0;
				$color='#555555';
				if ($row['art']=='DK') {
					$tr_title="Alt modtaget, mangler kun at sende kreditnota.";
				} else {
					$tr_title="Alt leveret, mangler kun at fakturere.";
				}
			} else {
				$bgnuance=0;
				$color='#000000';
				if ($row['art']=='DK') {
					$tr_title="Intet modtaget.";
				} else {
					$tr_title="Intet leveret.";
				}
			}
			$linjebg=linjefarve($linjebg, $bgcolor, $bgcolor5, $bgnuance1, $bgnuance);
			print "<tr bgcolor=\"$linjebg\" title='$tr_title'><td bgcolor=$bgcolor></td>";
		} else {
			if ($linjebg!=$bgcolor) {
				$linjebg=$bgcolor; $color='#000000';
			} else {
				$linjebg=$bgcolor5; $color='#000000';
			}
			print "<tr bgcolor=\"$linjebg\" title='$tr_title'><td bgcolor=$bgcolor></td>";
		}

		if ($row['art']=='DK') {
			print "<td align=$justering[0] $javascript style='color:$color'> (KN)&nbsp;$linjetext $understreg $row[ordrenr]$hrefslut</span><br></td>";
		} else {
			print "<td align=$justering[0] $javascript style='color:$color'> $linjetext $understreg $row[ordrenr]$hrefslut</span><br></td>";
		}
#		print "<td></td>";
		$row['ordredato']=dkdato($row['ordredate']);
#		print "<td>$ordredato<br></td>";
#		$levdato=dkdato($row['levdate']);
#		print "<td>$levdato<br></td>";
#		print"<td></td>";
		for ($x=1;$x<$vis_feltantal;$x++) {
			print "<td align=$justering[$x] style='color:$color'>";
			if ($vis_felt[$x]=="sum") {
				if ($genberegn) $kostpris=genberegn($row['id']);
				if ($valutakurs && $valutakurs!=100) {
					$sum=$sum*$valutakurs/100;
					$kostpris=$kostpris*$valutakurs/100;
#					$sum=bidrag($sum, $kostpris,'1');
#					print "a".dkdecimal($sum);
#					$tmp=dkdecimal($sum);
				} elseif ($valg!='faktura') {
					 print dkdecimal($sum);
				}
				if ($valg=='faktura') {
					$sum=bidrag($sum, $kostpris, $udlignet);
#					if ($checked[$ordreantal]=='on' || $check_all) $checked[$ordreantal]='checked';
#					print "<td align=right><input class=\"inputbox\" type=\"checkbox\" name=\"checked[$ordreantal]\" $checked[$ordreantal]></td>";
#					print "<input type=hidden name=ordre_id[$ordreantal] value=$row[id]>";
				} 
			} elseif (strpos($vis_felt[$x],"date") || $vis_felt[$x]=="nextfakt") {
	 			print dkdato($row[$vis_felt[$x]]);
			} else {
				$tmp=$vis_felt[$x];
				print $row[$tmp];
			}
			print "</td>"; 
		}
		if ($valg=='faktura' || ($valg=='ordrer' && $nextfakt)) {
			$vis_ret_next=1;
			if ($checked[$ordreantal]=='on' || $check_all) $checked[$ordreantal]='checked';
			print "<td align=right><input class=\"inputbox\" type=\"checkbox\" name=\"checked[$ordreantal]\" $checked[$ordreantal]></td>";
		} else print "<td><br></td>";
		print "<input type=hidden name=ordre_id[$ordreantal] value=$row[id]>";
		$ialt=$ialt+$sum;	

	}# endif ($lnr>=$start && $lnr<$slut)
}# endwhile

$colspan=$vis_feltantal+2;
if ($valg=="faktura") {		
#	if ($vis_projekt) $colspan++;
	if ($check_all) {
		print "<tr><td align=right colspan=$colspan><input type=\"submit\" name=\"uncheck\" value=\"".findtekst(90,$sprog_id)."\">";
	} else {
		print "<tr><td align=right colspan=$colspan><input type=\"submit\" name=\"check\" value=\"".findtekst(89,$sprog_id)."\">";
	}
	print "	</td></tr>\n";
	print "<input type=hidden name=ordre_antal value='$ordreantal'>";
	print "<input type=hidden name=valg value='$valg'>";
	print "<input type=hidden name=ordrenumre value='$ordrenumre'>";
	print "<input type=hidden name=kontonumre value='$kontonumre'>";
	print "<input type=hidden name=fakturanumre value='$fakturanumre'>";
	print "<input type=hidden name=ordredatoer value='$ordredatoer'>";
	print "<input type=hidden name=lev_datoer value='$lev_datoer'>";
	print "<input type=hidden name=fakturadatoer value='$fakturadatoer'>";
	print "<input type=hidden name=genfaktdatoer value='$genfaktdatoer'>";
	print "<input type=hidden name=summer value='$summer'>";
	print "<input type=hidden name=ref value='$ref[0]'>";
	print "<input type=hidden name=firma value='$firma'>";
	print "<input type=hidden name=kontoid value='$kontoid'>";
	print "<input type=hidden name=sort value='$sort'>";
	print "<input type=hidden name=nysort value='$nysort'>";
	print "</tr><tr><td colspan=$colspan align=right>";
	if ($genfakturer) print "<input type=submit value=\"Genfaktur&eacute;r\" name=\"submit\">&nbsp;";
	if ( strlen("which ps2pdf")) {
			print "<span title=\"Udskriver valgte fakturaer som PDF\"><input type=submit value=\"Udskriv\" name=\"submit\"></span></td>";
	} else {
			print "<input type=submit value=\"Udskriv\" name=\"submit\" disabled=\"disabled\"></td>";
	}
	print "</form></tr>\n";
}

if ($valg=="ordrer") {
#	if ($vis_projekt) $colspan++;
	if ($vis_ret_next) {
		if ($check_all) { 
			print "<tr><td align=right colspan=$colspan><input type=\"submit\" name=\"uncheck\" value=\"".findtekst(90,$sprog_id)."\">";
		} else {
			print "<tr><td align=right colspan=$colspan><input type=\"submit\" name=\"check\" value=\"".findtekst(89,$sprog_id)."\">";
		}
		print "	</td></tr>\n";
	}
	print "<input type=hidden name=ordre_antal value='$ordreantal'>";
	print "<input type=hidden name=valg value='$valg'>";
	print "<input type=hidden name=ordrenumre value='$ordrenumre'>";
	print "<input type=hidden name=kontonumre value='$kontonumre'>";
	print "<input type=hidden name=fakturanumre value='$fakturanumre'>";
	print "<input type=hidden name=ordredatoer value='$ordredatoer'>";
	print "<input type=hidden name=lev_datoer value='$lev_datoer'>";
	print "<input type=hidden name=fakturadatoer value='$fakturadatoer'>";
	print "<input type=hidden name=genfaktdatoer value='$genfaktdatoer'>";
	print "<input type=hidden name=summer value='$summer'>";
	print "<input type=hidden name=ref value='$ref[0]'>";
	print "<input type=hidden name=firma value='$firma'>";
	print "<input type=hidden name=kontoid value='$kontoid'>";
	print "<input type=hidden name=sort value='$sort'>";
	print "<input type=hidden name=nysort value='$nysort'>";
	print "</tr><tr><td colspan=$colspan align=right>";
	if ($vis_ret_next) print "<span title='Klik her for at rette detaljer i abonnementsordrer'><input type=\"submit\" value=\"Ret\" name=\"submit\"></td>";
	print "</form></tr>\n";
}

if ($r=db_fetch_array(db_select("select id from grupper where art = 'OLV' and kode = '$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__))) {
	db_modify("update grupper set box1='$ordreliste' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
} #else db_modify("insert into grupper (beskrivelse,kode,kodenr,art,box1) values ('Ordrelistevisning','$valg','$bruger_id','OLV','$ordreliste')",__FILE__ . " linje " . __LINE__);

#if ($valg=='tilbud') {$cols=7;}
#elseif ($valg=='faktura') {$cols=12;}
#else {$cols=8;}
#if ($vis_projekt) $cols++;
print "<tr><td colspan=$colspan><hr></td></tr>\n";
#$cols=$cols-4;
$dk_db=dkdecimal($ialt-$totalkost);		
if ($ialt!=0) {$dk_dg=dkdecimal(($ialt-$totalkost)*100/$ialt);}		
$ialt=dkdecimal($ialt);
#$cols--;
print "<tr><td colspan=$colspan width=100%>";
print "<table border=0 width=100%><tbody>";
if ($valg=='faktura') {
	print "<td width=30%><br></td><td width=40% align=center><span title= 'Klik for at genberegne DB/DG'><b><a href=ordreliste.php?genberegn=1&valg=$valg accesskey=G>Samlet oms&aelig;tning / db / dg (excl. moms.) </a></td><td width=30% align=right><b>$ialt / $dk_db / $dk_dg%</td></tr>\n";
} else {
	print "<td width=30%><br></td><td width=40% align=center>Samlet oms&aelig;tning</td><td width=30% align=right><b>$ialt</td></tr>\n";
}
print "</tbody></table></td>";
if ($genberegn==1) print "<meta http-equiv=\"refresh\" content=\"0;URL='ordreliste.php?genberegn=2&valg=$valg'\">";
#$cols++;
if ($valg=='faktura'){$cols++;}
#$cols=$cols+4;
print "<tr><td colspan=$colspan><hr></td></tr>\n";
$r=db_fetch_array(db_select("select box1 from grupper where art='MFAKT' and kodenr='1'",__FILE__ . " linje " . __LINE__));
if ($r['box1'] && $valg=='ordrer' && $ialt!="0,00") {
	$tekst="Faktur&eacute;r alt som kan leveres?";
	print "<tr><td colspan=$colspan align=right><span title='Klik her for at fakturere alle ordrer p&aring; listen'><a href=massefakt.php target=\"_blank\" onClick=\"return MasseFakt('$tekst')\">Faktur&eacute;r alt</a></span></td></tr>";
#	print "<tr><td colspan=$colspan align=right><span title='Klik her for at fakturere alle ordrer p&aring; listen'><a href=massefakt.php target=\"_blank\" onClick=\"onclick="return confirm('Vil du fakturere alle åbne ordrer?')\">Faktur&eacute;r alt</a></span></td></tr>";
}
function genberegn($id) {
	$kostpris=0;
	$q0 = db_select("select id, vare_id, antal, pris from ordrelinjer where ordre_id = $id and posnr>0 and vare_id > 0",__FILE__ . " linje " . __LINE__);
	while ($r0=db_fetch_array($q0)) {
		if ($r1=db_fetch_array(db_select("select provisionsfri, gruppe from varer where id = '$r0[vare_id]'",__FILE__ . " linje " . __LINE__))) {
			if ((!$r1[provisionsfri])&&($r1=db_fetch_array(db_select("select box9 from grupper where art = 'VG' and ".nr_cast(kodenr)."='$r1[gruppe]' and box9 = 'on' ",__FILE__ . " linje " . __LINE__)))) {
				$batch_tjek='0';
				$q1 = db_select("select antal, batch_kob_id from batch_salg where linje_id = '$r0[id]' and batch_kob_id != 0",__FILE__ . " linje " . __LINE__);	
				while ($r1=db_fetch_array($q1)) {
					if ($r2=db_fetch_array(db_select("select pris, fakturadate, linje_id from batch_kob where id = '$r1[batch_kob_id]'",__FILE__ . " linje " . __LINE__))) {
						if ($r2['fakturadate']<'2000-01-01') $r2=db_fetch_array(db_select("select pris from ordrelinjer where id = '$r2[linje_id]'",__FILE__ . " linje " . __LINE__));
						$batch_tjek=1;
						$tmpp=$r2['pris']*$r1['antal'];
						$kostpris=$kostpris+$r2['pris']*$r1['antal'];
					}
				}
				if ($batch_tjek<1) {
					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__));	
					$kostpris=$kostpris+$r2['kostpris']*$r0['antal'];
				}		
			}
			elseif ($r1[provisionsfri]) {$kostpris=$kostpris+$r0['pris']*$r0['antal'];}
			else {	
					$r2=db_fetch_array(db_select("select kostpris from varer where id = $r0[vare_id]",__FILE__ . " linje " . __LINE__));	
					$kostpris=$kostpris+$r2['kostpris']*$r0['antal'];
			}
		}
	} 
	db_modify("update ordrer set kostpris=$kostpris where id = $id",__FILE__ . " linje " . __LINE__);
	return $kostpris;
}

function bidrag ($sum,$kostpris,$udlignet){
	global $ialt;
	global $totalkost;
	global $genberegn;

	$ialt=$ialt+$sum;
	$totalkost=$totalkost+$kostpris;
	$dk_db=dkdecimal($sum-$kostpris);		
	$sum=round($sum,2);
	$kostpris=round($kostpris,2);
	if ($sum) $dk_dg=dkdecimal(($sum-$kostpris)*100/$sum);		
	else $dk_dg='0,00';
	$sum=dkdecimal($sum);
	if ($genberegn) {print "<span title= 'db: $dk_db - dg: $dk_dg%'>$sum/$dk_db/$dk_dg%<br></span>";}
	else {
		if ($udlignet) $span="style='color: #000000;' title='db: $dk_db - dg: $dk_dg%'";
		else $span="style='color: #FF0000;' title='Ikke udlignet\r\ndb: $dk_db - dg: $dk_dg%'";
		print "<span $span>$sum<br></span>";
	}
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
