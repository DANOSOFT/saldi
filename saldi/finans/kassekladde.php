<?php
ob_start(); //Starter output buffering
// ---------------------------------------------------finans/kassekladde.php------------lap 1.0.7-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------


@session_start();
$s_id=session_id();
$title="kassekladde";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/db_query.php");

if ($tjek=$_GET['tjek']) {
	$tidspkt=date("U")-1; # Der fratraekkes 1 sekund af hensyn til "refresktjek"
	$query = db_select("select tidspkt, hvem from kladdeliste where bogfort = '-' and id = $tjek");
	if ($row = db_fetch_array($query))	{
		if (($tidspkt-($row['tidspkt'])<3600)&&($row[hvem]!=$brugernavn)){
			print "<body onLoad=\"javascript:alert('Kladden er i brug af $row[hvem]')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		}
		else {
			db_modify("update kladdeliste set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'");
#			$tidspkt=$tidspkt+1; #Skal vï¿½e saadan af hensyn til "refreshtjek"
		}
	}
}

$kladde_id =$_POST['kladde_id'];
$antal_ny=$_POST['antal_ny'];
$h=$antal_ny*10;

?>
<script type="text/javascript">
var boolShiftDown = false;
var curField = null;

function doKeyDown(e) {
	if (!e) var e = window.event;
	if (e.keyCode) kCode = e.keyCode;
	else if (e.which) kCode = e.which;
	var elm;
	if (e.srcElement) elm = e.srcElement;
	else if (e.target) elm = e.target;
	else return false;
// alert(kCode);
	curField = (elm.tagName.toUpperCase() == 'INPUT' && elm.type == 'text') ? elm : null;
	var boolDoCancel = false;
	if (boolShiftDown) {
		switch (kCode) {
			case 37: navHor('previous'); boolDoCancel = true; break; //shift + venstre pil
			case 39: if (boolShiftDown) navHor('next'); boolDoCancel = true; break; // shift + højre pil
		}
	}
	else {
		switch (kCode) {
	  	case 38: navVer('previous'); boolDoCancel = true; break; //pil op
	 	 case 40: navVer('next'); boolDoCancel = true; break; //pil ned
		}
	}
	boolShiftDown = (kCode == 16);
	if (boolDoCancel) {
		e.returnValue = false;
		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
		}
}

function navHor(dir) {
	var elm = eval('curField.parentElement.' + dir + 'Sibling');
	if (elm != null) {
		elm = elm.firstChild;
		if (elm != null && elm.tagName.toUpperCase() == 'INPUT' && elm.type == 'text') elm.focus();
		}
}

function navVer(dir) {
	var elm = eval('curField.parentElement.parentElement.' + dir + 'Sibling');
	var ix = 0;
	var tmpElm = curField.parentElement;
	while (tmpElm.previousSibling) {
		ix++;
		tmpElm = tmpElm.previousSibling;
		}
	if (elm != null) {
		elm = elm.firstChild;
		if (elm != null) {
			i = 0;
			while (ix > i) {
				if (elm.nextSibling) {
					elm = elm.nextSibling;
					}
				else return false;
				i++;
				}
			if (elm.firstChild != null) elm.firstChild.focus();
			}
		}
}

</script>
<script type="text/javascript">
<!--
function simuler()
{
window.open("bogfor.php?kladde_id=<?php echo $kladde_id?>&funktion=simuler","","width=600,height=<?php echo $h?>,scrollbars=1,resizable=1")
}
//-->
</script>
<script type="text/javascript">
<!--
function bogfor() {
	window.open("bogfor.php?kladde_id=<?php echo $kladde_id?>","","width=600,height=<?php echo $h?>,scrollbars=1,resizable=1")
}
//-->
</script>
<div align="center">
<?php

if($_GET) {
	$returside=$_GET['returside'];
	if (!$returside){$returside="kladdeliste.php";}

	$fokus=$_GET['fokus'];
	$sort=$_GET['sort'];
	$funktion=$_GET['funktion'];
	$x=$_GET['x'];
	$id[$x]=$_GET['id'];
	$kladde_id=$_GET['kladde_id'];
	$bilag[$x]=$_GET['bilag'];
	$dato[$x]=$_GET['dato'];
	$beskrivelse[$x]=str_replace("!og!", "&",$_GET['beskrivelse']);
	$beskrivelse[$x]=str_replace("!apostrof!", "'",$beskrivelse[$x]);
	$d_type[$x]=$_GET['d_type'];
	$debet[$x]=$_GET['debet'];
	$k_type[$x]=$_GET['k_type'];
	$kredit[$x]=$_GET['kredit'];
	$faktura[$x]=trim($_GET['faktura']);
	$belob[$x]=$_GET['belob'];
	$momsfri[$x]=$_GET['momsfri'];
	$afd[$x]=$_GET['afd'];

	$beskrivelse[$x]=trim($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=trim($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	if (($sort)&&($funktion)) {
		$funktion($sort, $fokus, $x, $id[$x], $kladde_id, $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $afd[$x]);
	}
	if (!$kontonr) {
		$y=0;
		$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar");
		while($row = db_fetch_array($query)) {
			$y++;
			$kontonr[$y]=trim($row[kontonr]);
		}
	}
	if ($kladde_id) {	
		$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
		if ($row[bogfort]=='-') opdater($id[$x], $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $kontonr, $kladde_id, $afd[$x]);
	}
}

if ($_POST) {
	$submit =$_POST['submit'];
	$tidspkt =$_POST['tidspkt'];
	$kladde_id =$_POST['kladde_id'];
	$kontrolkonto=$_POST['kontrolkonto'];
	$bilagsnr=$_POST['bilagsnr'];
	$kladdenote = addslashes(trim($_POST['kladdenote']));
	$ny_kladdenote = addslashes(trim($_POST['ny_kladdenote']));
	$antal_ny=$_POST['antal_ny'];
	$antal_ex=$_POST['antal_ex'];
	$id=$_POST['id'];
	for ($x=1; $x<=$antal_ny;$x++) {
		$y="bila".$x;
		$bilag[$x]=trim($_POST[$y]);
		if (!$bilag[$x]) $bilag[$x]='0';  # PHR 02.09.06
		$y="dato".$x;
		$dato[$x]=trim($_POST[$y]);
		$y="besk".$x;
		$beskrivelse[$x]=trim($_POST[$y]);
		while (strpos($beskrivelse[$x],"  ")) $beskrivelse[$x]=str_replace("  "," ",$beskrivelse[$x]);
		$y="d_ty".$x;
		$d_type[$x]=strtoupper($_POST[$y]);
		$y="debe".$x;
		$debet[$x]=trim($_POST[$y]);
		$y="k_ty".$x;
		$k_type[$x]=strtoupper($_POST[$y]);
		$y="kred".$x;
		$kredit[$x]=trim($_POST[$y]);
		$y="fakt".$x;
		$faktura[$x]=trim($_POST[$y]);
		$y="belo".$x;
		$belob[$x]=$_POST[$y];
		$y="afd_".$x;
		$afd[$x]=$_POST[$y];
		# Sletstart og sletslut bruges til at slette mange bilagsnumre.
		if ((!$sletslut) && ($bilag[$x]=="->")) $sletstart=$x;  
		if (($sletstart) && ($bilag[$x]=="<-")) $sletslut=$x;
		if (strtoupper($debet[$x]) == 'D') $d_type[$x]='D';
		if (strtoupper($debet[$x]) == 'K') $d_type[$x]='K';
		if (strtoupper($kredit[$x]) == 'D') $k_type[$x]='D';
		if (strtoupper($kredit[$x]) == 'K') $k_type[$x]='K';
		if (($debet[$x])&&(($d_type[$x]!="F")||(strlen($debet[$x])>1))) $debet[$x]=$debet[$x]*1;
		if (($kredit[$x])&&(($k_type[$x]!="F")||(strlen($kredit[$x])>1))) $kredit[$x]=$kredit[$x]*1;
		if ((strpos($bilag[$x], '+'))&&($kladde_id)) indsaet_linjer($kladde_id, $bilag[$x], $dato[$x]);
	}
	if (($sletstart)&&($sletslut)&&($sletstart<$sletslut)) {
		for ($x=$sletstart; $x<=$sletslut; $x++) $bilag[$x]="-";
	}
	
	if ($submit=="Kopier til ny") {
		kopier_til_ny($kladde_id, $bilagsnr);
	}

	
	$momsfri=$_POST['momsfri'];
	$fokus=$_POST['fokus'];
	if (!$kladde_id) {
		$tidspkt=date("U");
		$kladde_id=1;
		$query = db_select("select id from kladdeliste");
		while ($row = db_fetch_array($query)) {
			if ($kladde_id<=$row[id]){$kladde_id=$row[id]+1;}
		}
		$kladdedate=date("Y-m-d");	# OBS I naeste linje indsaettes tidspkt fratrukket 1 sek. Ellers bliver 1. gemning afvist af	"Refresktjek"
		db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, hvem, oprettet_af, tidspkt) values ('$kladde_id', '$ny_kladdenote', '$kladdedate', '-', '$brugernavn', '$brugernavn', $tidspkt-1)");
	}
	if ($kladde_id) {
		$row = db_fetch_array(db_select("select tidspkt from kladdeliste where id=$kladde_id"));
		if ($tidspkt==$row[tidspkt]) { #Refreshtjek"
			print "<BODY onLoad=\"javascript:alert('Brug af refresh konstateret - handling ignoreret')\">";
		}
		else {
			db_modify("update kladdeliste set kladdenote = '$ny_kladdenote', hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$kladde_id'");
			$kladdenote = $ny_kladdenote;
			if (!$kontonr) {
				$x=0;
				$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar");
				while($row = db_fetch_array($query)) {
					$x++;
					$kontonr[$x]=trim($row[kontonr]);
				}
				$acc_ant=$x;
			}
			if ($submit == "Opslag") $opslag_id=substr($fokus,4,strlen($fokus)-4);

			if ($kladde_id) {	
				$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
				if ($row[bogfort]=='-') {
					for ($x=0; $x<=$antal_ny; $x++) {
						if (($x!=$opslag_id)&&(($beskrivelse[$x])||($debet[$x])||($kredit[$x]))) {
							opdater($id[$x], $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $kontonr, $kladde_id, $afd[$x]);
						}
						elseif (($x!=$opslag_id)&&($bilag[$x]=="-")) {
							opdater($id[$x], $bilag[$x], $dato[$x], $beskrivelse[$x], $d_type[$x], $debet[$x], $k_type[$x], $kredit[$x], $faktura[$x], $belob[$x], $momsfri[$x], $kontonr, $kladde_id, $afd[$x]);
							$beskrivelse[$x]=""; $dato[$x]=""; $d_type[$x]=""; $debet[$x]=""; $ktype[$x]=""; $kredit[$x]=""; $faktura[$x]=""; $belob[$x]="";
						}
					}
				}
			}
#******************************
			if ($submit == "Opslag") {
				if (strtoupper($debet[$opslag_id])=="K") {$d_type[$opslag_id]="K";}
				elseif (strtoupper($debet[$opslag_id])=="D") {$d_type[$opslag_id]="D";}
#				else {$d_type[$opslag_id]="F";}
				if (strtoupper($kredit[$opslag_id])=="K") {$k_type[$opslag_id]="K";}
				elseif (strtoupper($kredit[$opslag_id])=="D") {$k_type[$opslag_id]="D";}
#				else {$k_type[$opslag_id]="F";}
				$d_type[$opslag_id]=trim(strtoupper($d_type[$opslag_id]));
				$k_type[$opslag_id]=trim(strtoupper($k_type[$opslag_id]));
				if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))) {
					if($d_type[$opslag_id]=="K") kreditoropslag(firmanavn, $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id]);
					elseif($d_type[$opslag_id]=="D") debitoropslag(firmanavn, $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id]);
					else finansopslag($fokus, $opslag_id);
				}
				if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
					if($k_type[$opslag_id]=="K") kreditoropslag(firmanavn, $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id]);
					if($k_type[$opslag_id]=="D") debitoropslag(firmanavn, $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id]);
					else {finansopslag($fokus, $opslag_id);}
				}
				if ((strstr($fokus,"fakt"))||(strstr($fokus,"belo"))) openpost (firmanavn, $fokus, $opslag_id, $id[$opslag_id], $kladde_id, $bilag[$opslag_id], $dato[$opslag_id], $beskrivelse[$opslag_id], $d_type[$opslag_id], $debet[$opslag_id], $k_type[$opslag_id], $kredit[$opslag_id], $faktura[$opslag_id], $belob[$opslag_id], $momsfri[$opslag_id], $afd[$opslag_id]);
				if (strstr($fokus,"afd")) {afd_opslag ($fokus, $opslag_id);}
		 	}

			if (strstr($submit,"Simul")) {
				?>
				<body onload="simuler()">
				<?php
			}
			if (strstr($submit,"Bogf"))	{
				print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?kladde_id=$kladde_id&funktion=bogfor\">";
			}
			if (strstr($submit,"Tilbagef")){
				tilbagefor($kladde_id);
			}
			if (strstr($submit,"Hent")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=hentordrer.php?kladde_id=$kladde_id\">";
			}
			if (strstr($submit,"Impor")) {
				print "<meta http-equiv=\"refresh\" content=\"0;URL=bankimport.php?kladde_id=$kladde_id&bilagsnr=$bilagsnr\">";
			}
		}	 
	}
}
if ($kontrolkonto) {
	if (strlen($kontrolkonto)==1) {
		$kontrolkonto=strtoupper($kontrolkonto);
		$query = db_select("select * from kontoplan where genvej='$kontrolkonto' and regnskabsaar='$regnaar'");
		if ($row = db_fetch_array($query)) $kontrolkonto=$row[kontonr];
		else {
			$kontrolkonto='';
#			print "<body onLoad=\"javascript:alert('kontrolkontonummer findes ikke')\">";
		}
	}
	setcookie("saldi_ktrkto",$kontrolkonto,time()+60*60*24*30);
}
ob_end_flush();	//Sender det "bufferede" output afsted... 

if ($kladde_id) {
	$query = db_select("select kladdenote, bogfort from kladdeliste where id = $kladde_id");
	$row = db_fetch_array($query);
	$kladdenote = htmlentities(stripslashes($row['kladdenote']));
	$bogfort = $row['bogfort'];
}
$x=0;
print "<form name=\"kassekladde\" action=\"kassekladde.php\" method=\"post\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=\"$kladde_id\">";
print "<input type=\"hidden\" name=\"kladdenote\" value=\"$kladdenote\">";

if (!$simuler) {
	if ($returside != "regnskab"){$returside="kladdeliste.php";}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height=\"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small>";
	if ($returside=="kladdeliste.php") 
	{
		print "<a href=\"../includes/luk.php?tabel=kladdeliste&amp;id=$kladde_id\" accesskey=\"T\">Tilbage</a></small></td>";
	} else {
		print "<a href=\"$returside\" accesskey=\"T\">Tilbage</a></small></td>";}
	print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Kassekladde $kladde_id</small></td>";
	print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font<small><a href=kassekladde.php accesskey=N>Ny</a></small></td>";
	print "</tbody></table>";
	print "</td></tr>\n";
}
print "<tr><td valign=\"top\">";
if(($bogfort)&&($bogfort!='-')) {print "<table cellpadding=\"0\" cellspacing=\"1\" border=\"1\" align = \"center\" valign = \"top\">";}
else {print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align = \"center\" valign = \"top\" id=\"inputTable\" onkeydown=\"doKeyDown()\">";}
print "</tr>\n<tbody>";
print "<tr>";
if (($bogfort)&&($bogfort!='-')){print "<td colspan=10 align=center><b>$font $kladdenote</b></td>";}
else	{
	print "<td><small><b>$font <span title= 'Her kan skrives en bem&aelig;rkning til kladden'>Bem&aelig;rkning:</b></small></td>";
	print "<td colspan=10><input type=\"text\" size=95 name=ny_kladdenote value=\"$kladdenote\"></td>";
	if ((!$kontrolkonto)&&(isset($_COOKIE['saldi_ktrkto']))) {$kontrolkonto = $_COOKIE['saldi_ktrkto'];}
	print "<td><span title= 'Angiv kontonummer til kontrol'><input type=\"text\" style=text-align:right size=8 name=kontrolkonto value=\"$kontrolkonto\"></td>";
	print "</tr><tr><td colspan=10><hr></td></tr>\n";
}
print "<tr>";
print "<td width=\"75\" align = center><small><b>$font Bilag</b></small></td>";
print "<td width=\"75\" align = center><small><b>$font <span title= 'Angiv dato som ddmmyy (f.eks 241205)'>Dato</a></b></small></td>";
print "<td width=\"165\" align = center><small><b>$font Bilagstekst</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv D for debitor, K for kreditor eller F for finanspostering'>D/K</b></small></td>";
print "<td width=\"75\" align = center><small><b>$font <span title= 'Skriv D eller K og klik p&aring; [Opslag] for opslag i hhv, debitor- eller kreditorkartotek'>Debet</b></small></td>";
print "<td align = center><small><b>$font <span title= 'Angiv D for debitor, K for kreditor eller F for finanspostering'>D/K</b></small></td>";
print "<td width=\"75\" align = center><small><b>$font <span title= 'Skriv D eller K og klik p&aring; [Opslag] for opslag i hhv, debitor- eller kreditorkartotek'>Kredit</b></small></td>";
print "<td width=\"75\" align = center><small><b>$font <span title= 'Angiv fakturanummer - klik pï¿½opslag for at sl&aring; op i &aring;bne poster'>Fakturanr.</b></small></td>";
print "<td width=\"75\" align = center><small><b>$font <span title= 'Angiv belob - klik pï¿½opslag for at sl&aring; op i &aring;bne poster'>Bel&oslash;b</b></small></td>";
print "<td width=\"10\" align = left><small><b>$font <span title= 'Angiv hvilken afdeling posteringen h&oslash;rer under'>Afd.</b></small></td>";
print "<td width=\"10\" align = left><small><b>$font <span title= 'Afm&aelig;rk her, hvis der ikke skal tr&aelig;kkes moms'>u/m</b></small></td>";
print "</tr>\n";

	
$x=0;

if ($kladde_id) {
	print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php?tabel=kladdeliste&id=$kladde_id\">";
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag, transdate, id");
	while ($row = db_fetch_array($query)) {
		$x++;
		$id[$x]=$row['id'];
		$bilag[$x]=$row['bilag'];
		$dato[$x]=dkdato($row['transdate']);
		$beskrivelse[$x]=htmlentities(stripslashes($row['beskrivelse']));
		$d_type[$x]=trim($row['d_type']);
		$debet[$x]=$row['debet'];
		$k_type[$x]=$row['k_type'];
		$kredit[$x]=$row['kredit'];
		$faktura[$x]=htmlentities(stripslashes($row['faktura']));
		$amount[$x]=$row['amount'];
		$momsfri[$x]=$row['momsfri'];
		$afd[$x]=$row['afd'];
		if (!$beskrivelse) {$beskrivelse='';}
		if (($d_type[$x]=='F')&&($debet[$x])) {
			$query2 = db_select("select beskrivelse from kontoplan where kontonr='$debet[$x]'");
			$row2 = db_fetch_array($query2);
			$debettext[$x]=$row2['beskrivelse'];
		}
		if ((($d_type[$x]=='D')||($d_type[$x]=='K'))&&($debet[$x])) {
			$query2 = db_select("select firmanavn from adresser where kontonr='$debet[$x]' and art = '$d_type[$x]'");
			$row2 = db_fetch_array($query2);
			$debettext[$x]=trim($row2['firmanavn']);
		}
		if (($k_type[$x]=='F')&&($kredit[$x])) {
			$query2 = db_select("select beskrivelse from kontoplan where kontonr='$kredit[$x]'");
			$row2 = db_fetch_array($query2);
			$kredittext[$x]=trim($row2['beskrivelse']);
		}
		if ((($k_type[$x]=='D')||($k_type[$x]=='K'))&&($kredit[$x])) {
			$query2 = db_select("select firmanavn from adresser where kontonr='$kredit[$x]' and art = '$k_type[$x]'");
			$row2 = db_fetch_array($query2);
			$kredittext[$x]=trim($row2['firmanavn']);
		}
	}
} 

if (($bogfort)&&($bogfort!='-')) {
	for ($y=1; $y<=$x; $y++) {
		if (!$beskrivelse[$y]){$beskrivelse[$y]="&nbsp;";}
		if (($d_type[$y]!="D")&&($d_type[$y]!="K")){$d_type[$y]="&nbsp;";}
		if ($debet[$y] < 1){$debet[$y]="&nbsp;";}
		if (($k_type[$y]!="D")&&($k_type[$y]!="K")){$k_type[$y]="&nbsp;";}
		if ($kredit[$y] < 1){$kredit[$y]="&nbsp;";}
		if (!$faktura[$y]){$faktura[$y]="&nbsp;";}
		$belob=dkdecimal($amount[$y]);
		print "<tr>";
		print "<td><small>$font $bilag[$y]</small></td>";
		print "<td><small>$font $dato[$y]</td></small>";
		print "<td><small>$font $beskrivelse[$y]</small></td>";
		print "<td><small>$font $d_type[$y]</small></td>";
		print "<td align=right><small>$font $debet[$y]</small></td>";
		print "<td><small>$font $k_type[$y]</small></td>";
		print "<td align=right><small>$font $kredit[$y]</small></td>";
		print "<td align=right><small>$font $faktura[$y]</small></td>";
		print "<td align=right><small>$font $belob</small></td>";
		print "<td align=right><small>$font $afd[$y]</small></td>";
		if (strstr($momsfri[$y],"on")) {print "<td align=center><small>$font V</small></td>";}
		else {print "<td><small>$font <br></small></td>";}
		print "</tr>\n";
	}
	if ($bogfort=='V'){
		print "<input type=hidden name=ny_kladdenote value=\"$kladdenote\">";
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"k\" value=\"Kopier til ny\" name=\"submit\"></td></tr>\n";
		print "</form>";
		print "</tbody></table></td></tr>\n";
		print "</tbody></table>";
	}
	elseif ($bogfort=='!'){
		print "<input type=hidden name=ny_kladdenote value=\"$kladdenote\">";
		print "<tr><td colspan=9 align=center><input type=submit accesskey=\"b\" value=\"Tilbagef&oslash;r\" name=\"submit\"></td></tr>\n";
		print "</form>";
		print "</tbody></table></td></tr>\n";
		print "</tbody></table>";
	}
}
else {
	$debetsum=0;
	$kreditsum=0;
	if ($kontrolkonto) {
		$kontrolkonto=$kontrolkonto*1;
		$query = db_select("select * from kontoplan where kontonr='$kontrolkonto' and regnskabsaar='$regnaar'");
		if ($row = db_fetch_array($query)) {
			$kontrolsaldo=$row[primo]+$row[md01]+$row[md02]+$row[md03]+$row[md04]+$row[md05]+$row[md06]+$row[md07]+$row[md08]+$row[md09]+$row[md10]+$row[md11]+$row[md12];
		}
		else { 
			$kontrolkonto='';
#			print "<BODY onLoad=\"javascript:alert('Angivet kontrolkontonummer findes ikke')\">";
		}
	}
	for ($y=1; $y<=$x; $y++) {
		if ($debet[$y] < 1){$debet[$y]="";}
		if ($kredit[$y] < 1){$kredit[$y]="";}
		if ($debet[$y]==$kontrolkonto) {$kontrolsaldo=$kontrolsaldo+$amount[$y];}
		if ($kredit[$y]==$kontrolkonto) {$kontrolsaldo=$kontrolsaldo-$amount[$y];}
		$belob[$y]=dkdecimal($amount[$y]);
		print "<tr>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=bila$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$bilag[$y]\"\"></td>";
		print "<td><input type=\"text\" size=10 name=dato$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$dato[$y]\"></td>";
		print "<td><input type=\"text\" size=25 name=besk$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$beskrivelse[$y]\"></td>";
		print "<td><input type=\"text\" size=1 name=d_ty$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$d_type[$y]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=debe$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$debet[$y]\" title=\"$debettext[$y]\"></td>";
		print "<td><input type=\"text\" size=1 name=k_ty$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$k_type[$y]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=kred$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$kredit[$y]\" title= \"$kredittext[$y]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=fakt$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$faktura[$y]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=belo$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$belob[$y]\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=1 name=afd_$y onfocus=\"document.forms[0].fokus.value=this.name;\" value =\"$afd[$y]\" ></td>";
		if ($momsfri[$y] == 'on') {print "<td><input type=checkbox name=momsfri[$y] checked></td>";}
		else {print"<td><input type=checkbox name=momsfri[$y]></td>";}
		if ($kontrolkonto) {print "<td align=right>$font<small>".dkdecimal($kontrolsaldo) ."</td>";}
		print "<input type=hidden name=id[$y] value=$id[$y]>";
		print "</tr>\n";
		if ($bilag[$y] != $bilag[$y-1])
		{
			$debetsum=0;
			$kreditsum=0;
			$amount[$x+1]=0;
		}
		if ((($debet[$y])||($kredit[$y]))&&($amount[$y] > 0)) {
			if (($debet[$y])||($debet[$y]>0)) {$debetsum=$debetsum+$amount[$y];}
			if (($kredit[$y])||($kredit[$y]>0)) {$kreditsum=$kreditsum+$amount[$y];}
			if ((!$bilag[$x+1])||($bilag[$x+1]<$bilag[$y])) {$bilag[$x+1]=$bilag[$y];}
			if (!$dato[$x+1]) {$dato[$x+1]=$dato[$y];}
			$amount[$x+1]=$debetsum-$kreditsum;
		}
	}
	if (abs($amount[$x+1])>0.01) {
	 $beskrivelse[$x+1]=$beskrivelse[$x];
		$bilag[$x+1]=$bilag[$x];
		$dato[$x+1]=$dato[$x];
	}
#	else {$bilag[$x+1]=$bilag[$x]+1;}
	elseif ($bilag[$x+1]==$bilag[$x]) {
		if (($amount[$p] == 0)&&($amount[$x] > 0)) {
			$amount[$x+1]='';
			$bilag[$x+1]=$bilag[$x]+1;
			$dato[$x+1]=$dato[$x];
		}
	}#end if($bilag[$x+1]==$bilag[$x])
	if ($x > 20) {$y=$x+5;}
	else {$y=24;}
	$x++;
	if ($amount[$x]<0){$amount[$x]=$amount[$x]*-1;}
	$belob=dkdecimal($amount[$x]);
	if (($amount[$x-1])&&($amount[$x-1]<0.01)) {
		$bilag[$x]="";
		$dato[$x]="";
		$belob="";
	}
#	$fokus=nextfokus($fokus); # Udeladt 061106 da jeg finder det mere praktisk at cusoren bliver stående hvor den står - PHR
#	if (((!$bilag[$x])||($bilag[$x]=="-"))&&($x==1)) { #PHR 02.10.06
	if ((($bilag[$x]=="-")&&($x==1))||(!$kladde_id)) {
		$bilag[$x]=1;
		$query = db_select("select MAX(bilag) as bilag from kassekladde");
		if ($row = db_fetch_array($query)) $bilag[$x]=$row['bilag']+1;
	}
	if (($bilag[$x])&&(!$dato[$x])){$dato[$x]=dkdato(date("Y-m-d"));}
	if (($debet[$x-1])||($kredit[$x-1])||$x==1) {
		
		print "<tr>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=bila$x onfocus=\"document.forms[0].fokus.value=this.name;\" value ='$bilag[$x]'></td>";
		print "<td><input type=\"text\" size=10 name=dato$x onfocus=\"document.forms[0].fokus.value=this.name;\" 	value ='$dato[$x]'></td>";
		print "<td><input type=\"text\" size=25 name=besk$x onfocus=\"document.forms[0].fokus.value=this.name;\" value ='$beskrivelse[$x]'></td>";
		print "<td><input type=\"text\" size=1 name=d_ty$x onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\" value ='$d_type[$x]'></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=debe$x onfocus=\"document.forms[0].fokus.value=this.name;\" value ='$debet[$x]'></td>";
		print "<td><input type=\"text\" size=1 name=k_ty$x onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\" value ='$k_type[$x]'></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=kred$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$kredit[$x]'></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=fakt$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$faktura[$x]'></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=belo$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$belob'></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=1 name=afd_$x onfocus=\"document.forms[0].fokus.value=this.name;\" value='$afd[$x]'></td>";
		if ($momsfri[$y] == 'on') {print"<td><input type=checkbox name=momsfri[$x] checked></td>";}
		else {print"<td><input type=checkbox name=momsfri[$x]></td>";}
		print "<input type=hidden name=antal_ex value='$x'>";
	}
	$bilagsnr=$bilag[$x];
	if ($x > 20) {$y=$x+5;}
	else {$y=24;}
	$x++;
	for ($z=$x; $z<=$y; $z++) {
		print "<tr>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=bila$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=10 name=dato$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=25 name=besk$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=1 name=d_ty$z onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=debe$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" size=1 name=k_ty$z onfocus=\"document.forms[0].fokus.value=this.name;\" onBlur=\"validerFelt(this,'Gyldig v&aelig;rdi: D, K eller Blank');\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=kred$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=fakt$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=10 name=belo$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=\"text\" style=\"text-align:right\" size=1 name=afd_$z onfocus=\"document.forms[0].fokus.value=this.name;\"></td>";
		print "<td><input type=checkbox name=momsfri[$z]></td>";
		print "</tr>\n";
	}
	print "<input type=\"hidden\" name=\"fokus\" id=\"fokus\">";
	print "<input type=hidden name=kladde_id value=$kladde_id>";
	$tidspkt=date("U");
	print "<input type=hidden name=tidspkt value=$tidspkt>";
	print "<input type=hidden name=bilagsnr value=$bilagsnr>";
	print "<input type=hidden name=antal_ny value='$y'>";
	print "<tr><td colspan=9><hr></td></tr>\n";
	print "<td align=center colspan=9>";
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"1\"><tbody><tr>";
	print "<td align=center><input type=submit accesskey=\"g\" value=\"&nbsp;&nbsp;Gem&nbsp;&nbsp;\" name=\"submit\"></td>";
	print "<td align=center><input type=submit accesskey=\"o\" value=\"Opslag\" name=\"submit\"></td>";
	print "<td align=center><input type=submit accesskey=\"s\" value=\"Simuler\" name=\"submit\"></td>";
	print "<td align=center><input type=submit accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\"></td>";
	print "<td align=center><input type=submit accesskey=\"h\" value=\"Hent ordrer\" name=\"submit\"></td>";
	print "<td align=center><input type=submit accesskey=\"i\" value=\"bankImport\" name=\"submit\"></td>";
	print "</form>";
	print "</tbody></table></td></tr>\n";
	print "</tbody></table>";
} #end if $bogfort...else

######################################################################################################################################
function debitoropslag($sort, $fokus, $opslag_id, $id, $kladde_id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $afd) {
	
	global $bgcolor2;
	global $font;
	
	$beskrivelse=(stripslashes($beskrivelse));
	$beskrivelse=(str_replace("&","!og!",$beskrivelse));
	$beskrivelse=(str_replace("'","!apostrof!",$beskrivelse));
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	$faktura=trim(stripslashes($faktura));
	$belob=trim($belob);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Tilbage</a></small></td>";
	print"<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Debitorliste</small></td>";
#	print"<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font<small><a href=../debitor/debitorkort.php?returside=../includes/luk.php target=blank accesskey=N>Ny</a></small></td>";
	print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../debitor/debitorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\">$font<small><u>Ny</u></small></td>";

	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontonr&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Kundenr</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=firmanavn&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Navn</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr1&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Adresse</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr2&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Adresse2</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=postnr&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Postnr</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=bynavn&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>By</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontakt&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Kontaktperson</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=tlf&funktion=debitoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Telefon</b></small></td>";
	print" </tr>\n";

	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = firmanavn;}


	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'D' order by $sort");
	while ($row = db_fetch_array($query)) {
		if (!$beskrivelse){
			$beskr=html_prep(stripslashes($row['firmanavn']));
		}
		else {$beskr=$beskrivelse;} 
		$kontonr=trim($row['kontonr']);
		print "<tr>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>";}
		else {$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>";} 
		print "<td><small>$font $tmp $row[kontonr]</a><br></small></td>";
		print "<td><small>$font $tmp ". stripslashes($row[firmanavn]) ."</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr1]</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr2]</a><br></small></td>";
		print "<td><small>$font $tmp $row[postnr]</a><br></small></td>";
		print "<td><small>$font $tmp $row[bynavn]</a><br></small></td>";
		print "<td><small>$font $tmp $row[kontakt]</a><br></small></td>";
		print "<td><small>$font $tmp $row[tlf]</a><br></small></td>";
		print "</tr>\n";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function kreditoropslag($sort, $fokus, $opslag_id, $id, $kladde_id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $afd) {
	global $bgcolor2;
	global $font;

	$beskrivelse=(html_prep($beskrivelse));
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	$faktura=(html_prep($faktura));
	$belob=trim($belob);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=on' accesskey='t'>Tilbage</a></small></td>";
	print"<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>kreditorliste</small></td>";
	print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\">$font<small><u>Ny</u></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontonr&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Kundenr</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=firmanavn&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Navn</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr1&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Adresse</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=addr2&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Adresse2</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=postnr&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Postnr</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=bynavn&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>By</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=kontakt&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Kontaktperson</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=tlf&funktion=kreditoropslag&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Telefon</b></small></td>";
	print" </tr>\n";


	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = firmanavn;}


	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, kontakt, tlf from adresser where art = 'K' order by $sort");
	while ($row = db_fetch_array($query)){
		if (!$beskrivelse){
			$beskr=html_prep(stripslashes($row['firmanavn']));
		}
		else {$beskr=$beskrivelse;} 
		$kontonr=trim($row['kontonr']);
		print "<tr>";
		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty"))){$tmp = "<td><small>$font <a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$kontonr&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>";}
		else {$tmp="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kontonr&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>";} 
		print "<td><small>$font $tmp $row[kontonr]</a><br></small></td>";
		print "<td><small>$font $tmp ". stripslashes($row[firmanavn]) ."</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr1]</a><br></small></td>";
		print "<td><small>$font $tmp $row[addr2]</a><br></small></td>";
		print "<td><small>$font $tmp $row[postnr]</a><br></small></td>";
		print "<td><small>$font $tmp $row[bynavn]</a><br></small></td>";
		print "<td><small>$font $tmp $row[kontakt]</a><br></small></td>";
		print "<td><small>$font $tmp $row[tlf]</a><br></small></td>";
		print "</tr>\n";
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function openpost($sort, $fokus, $opslag_id, $id, $kladde_id, $bilag, $kladdedato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $afd) {
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $font;
	
	$d_type=trim($d_type);
	$debet=trim($debet);
	$k_type=trim($k_type);
	$kredit=trim($kredit);
	if ($faktura) $faktura=html_prep($faktura);
	$belob=str_replace("-","",trim($belob));
	if ($beskrivelse) $beskrivelse=html_prep($beskrivelse);

#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$kladdedato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=on&afd=$afd'>Tilbage</a></small></td>";
	print"<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>&Aring;benposter</small></td>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Kundenr</b></small></td>";
#	print"<td><small><b>$font <a 'href=kassekladde.php?sort=konto_nr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Kundenr</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=firmanavn&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Navn</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=faktnr&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Fakturanr</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=transdate&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Dato</b></small></td>";
	print"<td><small><b>$font <a href='kassekladde.php?sort=amount&funktion=openpost&x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$dato&beskrivelse=$beskrivelse&d_type=$d_type&debet=$debet&k_type=$k_type&kredit=$kredit&faktura=$faktura&belob=$belob&momsfri=$momsfri&afd=$afd'>Bel&oslash;b</b></small></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = 'konto_nr';}

	$x=0;
	$query = db_select("select id, firmanavn, art, gruppe from adresser order by firmanavn");
	while ($row = db_fetch_array($query)) {
		$x++;
		$konto_id[$x]=$row[id];
		$firmanavn[$x]=stripslashes($row['firmanavn']);
		$art[$x]=$row['art'];
		$gruppe=$row['gruppe'];
		$gruppeart=$art[$x]."G";
		$r2 = db_fetch_array(db_select("SELECT box4 FROM grupper WHERE art ='$gruppeart' AND kodenr	= '$gruppe'"));
		$modkonto[$x]=$r2[box4];
	}
	
		$kriterie='where udlignet !=1';
		if ($faktura) {$kriterie=$kriterie." and faktnr='".$faktura."'";}	
		if ((($d_type=='K')|| ($d_type=='D')) and ($debet)) {$kriterie=$kriterie." and konto_nr=".$debet;}	
		elseif ((($k_type=='K')|| ($k_type=='D')) and ($kredit)) {$kriterie=$kriterie." and konto_nr=".$kredit;}	
		if ($belob) {
			$amount=usdecimal($belob);
			if ($amount != 0) {$kriterie=$kriterie." and (amount=".$amount." or amount=-".$amount.")";}
		}
		
		if ($sort=="firmanavn") {$sort='konto_nr';} 
	
		
		$query = db_select("select id, konto_id, konto_nr, faktnr, transdate, amount from openpost $kriterie order by $sort");
		while ($row = db_fetch_array($query)){
			for ($y=1;$y<=$x;$y++) {
			if ($row[konto_id]==$konto_id[$y]) {
				$firmanavn[0]=$firmanavn[$y];
				$art[0]=str_replace(" ",22,$art[$y]);
			}
		}
		if (!$beskrivelse) {
#			$beskr=html_prep($firmanavn[0]);
			$beskr="Udligning, nota $faktura";
		}
		else	{$beskr=$beskrivelse;}
		$konto_nr=trim($row['konto_nr']);
		$dato=dkdato($row[transdate]);
			
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor;}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5;}

		print "<tr bgcolor=\"$linjebg\">";
			
		
		if ($row[amount]<0) {
			$belob=dkdecimal($row[amount]*-1);
			if (!$kredit) {$kredit=$modkonto[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$kladdedato&beskrivelse=$beskr&d_type=$art[0]&debet=$konto_nr&k_type=$k_type&kredit=$kredit&faktura=$row[faktnr]&belob=$belob&momsfri=on&afd=$afd'>";
		}
		else 	{
			$belob=dkdecimal($row[amount]);
			if (!$debet) {$debet=$modkonto[$x];}
			$tmp="<a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id&bilag=$bilag&dato=$kladdedato&beskrivelse=$beskr&d_type=$d_type&debet=$debet&k_type=$art[0]&kredit=$konto_nr&faktura=$row[faktnr]&belob=$belob&momsfri=on&afd=$afd'>";
		}
			print "<td><small>$font $tmp $row[konto_nr]</a><br></small></td>";
#		print "<td><small>$font $row[id]<br></small></td>";
		#print "<td><small>$font $row[konto_nr]<br></small></td>";
		print "<td><small>$font $tmp $firmanavn[0]</a><br></small></td>";
		print "<td><small>$font $tmp $row[faktnr]</a><br></small></td>";
		print "<td><small>$font $tmp $dato</a><br></small></td>";
		print "<td><small>$font $tmp $belob</a><br></small></td>";
		print "</tr>\n";
	}
	
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function finansopslag($fokus, $x) {

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $momsfri;
	global $afd;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;
		
	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]dato&beskrivelse=$beskrivelse[$x]&d_type=$art[$x]&debet=$debet[$x]&k_type=$k_type$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]'>Tilbage</a></small></td>";
	print"<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Finansopslag</small></td>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
	?>
	<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=10%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Kontonr</b></td>
		<td width=35%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Moms</a></b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Genvej</a></b></td>
		</tr>
		<tr><td colspan=4><hr></td></tr>
		<?php
		$i=0;
		$query = db_select("select * from grupper");
		while ($row = db_fetch_array($query)) {
			if (substr(trim($row['art']),1,1)=='M') {
				$i++;
				$moms[$x]=$row['kode'].$row['kodenr'];
			$momstekst[$x]=$row['beskrivelse'];
			}
			$momsantal=$i;
	}
	$query = db_select("select kontonr, beskrivelse, moms, genvej from kontoplan where (kontotype ='D' or kontotype ='S') and regnskabsaar='$regnaar' order by kontonr");
	while ($row = db_fetch_array($query)) {
		if ($row[moms]){
			for ($i=1; $i<=$momsantal; $i++){
				if ($moms[$i]==$row['moms']) $spantekst=$momstekst[$i];
			}
		}
		$kontonr=trim($row['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=$linjebg>";
		$faktura[$x]=trim($faktura[$x]);
		$beskrivelse[$x]=trim($beskrivelse[$x]);

		if ((strstr($fokus,"debe"))||(strstr($fokus,"d_ty")))	{
			$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$kontonr&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]' accesskey=$row[genvej]>";
		}
		if ((strstr($fokus,"kred"))||(strstr($fokus,"k_ty"))) {
			$href="<a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kontonr&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]' accesskey=$row[genvej]>";
		}
		print "<td><small><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href<font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$kontonr</font></a><span></small></td>";
		print "<td><small><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href<font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]</font></a><span></small></td>";
		print "<td><span title='$spantekst'><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[moms]</font></small></span></td>";
		print "<td><small><span title='Klik for at overf&oslash;re kontonr til kassekladde'>$href<font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[genvej]</font></a><span></small></td>";
		print "</tr>\n";
	}
	exit;
}
######################################################################################################################################
function afd_opslag($fokus, $x) 
{

	global $id;
	global $kladde_id;
	global $bilag;
	global $dato;
	global $beskrivelse;
	global $d_type;
	global $debet;
	global $k_type;
	global $kredit;
	global $faktura;
	global $belob;
	global $momsfri;
	global $regnaar;
	global $bgcolor;
	global $bgcolor2;
	global $bgcolor5;
	global $fgcolor;
	global $font;


	$beskrivelse[$x]=html_prep($beskrivelse[$x]);
	$d_type[$x]=trim($d_type[$x]);
	$debet[$x]=trim($debet[$x]);
	$k_type[$x]=trim($k_type[$x]);
	$kredit[$x]=trim($kredit[$x]);
	$faktura[$x]=html_prep($faktura[$x]);
	$belob[$x]=trim($belob[$x]);

	 print"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\">$font<small><a href='kassekladde.php?fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$art[$x]&debet=$debet[$x]&k_type=$k_type&debet=$debet[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$afd[$x]'>Tilbage</a></small></td>";
	print"<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font<small>Afd. opslag</small></td>";
	print"<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\">$font<small></small></td>";
	print"</tbody></table>";
	print"</td></tr>\n";
?>
		<tr><td valign="top">
	<table cellpadding="0" cellspacing="0" border="0" width="100%" valign = "top">
	<tbody>
	<tr>
		<td width=20%><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Afdeling nr.</b></td>
		<td><b><font face="Helvetica, Arial, sans-serif" color="<?php echo $fgcolor ?>">Beskrivelse</a></b></td>
	</tr>
	<?php
	$query = db_select("select kodenr, beskrivelse from grupper where art='AFD' order by kodenr");
	while ($row = db_fetch_array($query)) {
		$kodenr=trim($row['kodenr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		elseif ($linjebg!=$bgcolor5){$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=$linjebg>";
		print "<td><small><a href='kassekladde.php?x=$x&fokus=$fokus&kladde_id=$kladde_id&id=$id[$x]&bilag=$bilag[$x]&dato=$dato[$x]&beskrivelse=$beskrivelse[$x]&d_type=$d_type[$x]&debet=$debet[$x]&k_type=$k_type[$x]&kredit=$kredit[$x]&faktura=$faktura[$x]&belob=$belob[$x]&momsfri=$momsfri[$x]&afd=$kodenr'><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$kodenr</a><br></small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\">$row[beskrivelse]<br></small></td>";
		print "</tr>\n";

	}
	exit;
}
######################################################################################################################################
function opdater($id, $bilag, $dato, $beskrivelse, $d_type, $debet, $k_type, $kredit, $faktura, $belob, $momsfri, $kontonr, $kladde_id, $afd) {
	global $fokus;
	global $debitornr;
	global $connection;
	global $regnaar;
	global $aarstart;
	global $aarslut;
	global $prebilag;
	
	if ($kladde_id) {	
		$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
		if ($row[bogfort]!='-') {
			print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogf&oslash;rt - kladden lukkes')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			exit;
		}
	}

	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
		if ($row = db_fetch_array($query)) {
			$year=substr(trim($row['box2']),-2);
			$aarstart=trim($year).trim($row['box1']);
			$year=substr(trim($row['box4']),-2);
			$aarslut=trim($year).trim($row['box3']);
	 	}
	}
	$fejl=0;
#	(!$bilag) {$bilag=$prebilag;} PHR 02.10.06
#	if ($bilag=="-"){$bilag="";} PHR 02.10.06
	if (($bilag)&&($bilag!='0')&&($bilag!='-')) $bilag=$bilag*1;
	$debet=trim($debet);
	$kredit=trim($kredit);
	if (($bilag!='-')&&(($bilag)||($beskrivelse)||($kredit)||($debet)||($faktura)||($belob))) {
		if ((!$bilag)&&($bilag!='0')) {$bilag=$prebilag;}
		if (!$bilag) $bilag='0';
		if ((strstr($d_type,"d"))||(strstr($d_type,"D"))){$d_type="D";}
		elseif ((strstr($d_type,"k"))||(strstr($d_type,"K"))){$d_type="K";}
		else {$d_type="F";}

		if ((strstr($k_type,"d"))||(strstr($k_type,"D"))){$k_type="D";}
		elseif ((strstr($k_type,"k"))||(strstr($k_type,"K"))){$k_type="K";}
		else {$k_type="F";}
		if (!$debet) {$debet=0;}
		if (!$kredit) {$kredit=0;}
		if (!$kontonr) {
			$y=0;
			$query = db_select("select kontonr from kontoplan where kontotype != 'H' and kontotype != 'Z' and regnskabsaar=$regnaar");
			while($row = db_fetch_array($query)) {
				$y++;
				$kontonr[$y]=trim($row[kontonr]);
			}
		}

		if (($d_type=="D")||($k_type=="D")||($d_type=="K")||($k_type=="K")) {
			$x=0;
			$y=0;
			$query = db_select("select kontonr, art from adresser");
			while ($row = db_fetch_array($query)) {
				if (strstr($row[art],"D")) {
					$x++;
					$debitornr[$x]=trim($row[kontonr]);
				}
				if (strstr($row[art],"K")){
					$y++;
					$kreditornr[$y]=trim($row[kontonr]);
				}
			}
		}
		if (($d_type=="F")&&(strlen($debet)==1)&&($debet!='0')) {
			$debet=strtoupper($debet);
			$query = db_select("select kontonr from kontoplan where genvej='$debet'");
			if ($row = db_fetch_array($query)) $debet=$row[kontonr];
			else {
				print "<BODY onLoad=\"javascript:alert('$debet er ikke defineret som genvejstast (Bilag nr $bilag)')\">";
				$fejl=1;
			}
		}
		if (($k_type=="F")&&(strlen($kredit)==1)&&($kredit!='0')) {
			$kredit=strtoupper($kredit);
			$query = db_select("select kontonr from kontoplan where genvej='$kredit'");
			if ($row = db_fetch_array($query)) $kredit=$row[kontonr];
			else {
				print "<BODY onLoad=\"javascript:alert('$kredit er ikke defineret som genvejstast (Bilag nr $bilag)')\">";
				$fejl=1;
			}
		}
		if (($d_type=="F")&&($debet>0)&&(!in_array($debet,$kontonr))) {
			print "<BODY onLoad=\"javascript:alert('Debetkonto $debet eksisterer ikke (Bilag nr $bilag)')\">";
			$fejl=1;
		}
		if (($k_type=="F")&&($kredit>0)&&(!in_array($kredit,$kontonr))) {
			print "<BODY onLoad=\"javascript:alert('Kreditkonto $kredit eksisterer ikke (Bilag nr $bilag)')\">";
			$fejl=1;
		}
		if (($d_type=="D")&&($debet>0)&&(!in_array($debet,$debitornr))) {
			print "<BODY onLoad=\"javascript:alert('Debitor $debet eksisterer ikke (Bilag nr $bilag)')\">";
			$fejl=1;
		}
		if (($k_type=="D")&&($kredit>0)&&(!in_array($kredit,$debitornr))) {
			print "<BODY onLoad=\"javascript:alert('Debitor $kredit eksisterer ikke (Bilag nr $bilag)')\">";
			$fejl=1;
		}
		if (($d_type=="K")&&($debet>0)&&(!in_array($debet,$kreditornr))) {
			print "<BODY onLoad=\"javascript:alert('Kreditor $debet eksisterer ikke (Bilag nr $bilag)')\">";
			$fejl=1;
		}
		if (($k_type=="K")&&($kredit>0)&&(!in_array($kredit,$kreditornr))) {
		 print "<BODY onLoad=\"javascript:alert('Kreditor $kredit eksisterer ikke (Bilag nr $bilag)')\">";
			$fejl=1;
		}
		if (($d_type=="K")&&(strtoupper($debet)=="K")) $debet=0;
		if (($d_type=="D")&&(strtoupper($debet)=="D")) $debet=0;
		if (($k_type=="K")&&(strtoupper($kredit)=="K")) $kredit=0;
		if (($k_type=="D")&&(strtoupper($kredit)=="D")) $kredit=0;
		$transdate=usdate($dato);
		list ($year, $month, $day) = split ('-', $transdate);
		$year=substr($year,-2);
		$ym=$year.$month;
		if (($ym<$aarstart)||($ym>$aarslut)) {
			print "<BODY onLoad=\"javascript:alert('Dato udenfor regnskabs&aring;r (Bilag nr $bilag) dato sat til dd.')\">";
			$transdate=date("Y-m-d");
		}
		$afd=$afd*1;
		if ($afd!='0') {
			if (!$row= db_fetch_array(db_select("select id from grupper where art='AFD' and kodenr='$afd'"))){
				print "<BODY onLoad=\"javascript:alert('Afdeling $afd eksisterer ikke (Bilag nr $bilag)')\">";
				$fejl=1;
			}
		}
		if (!$fejl) {
			$amount=usdecimal($belob);
			$momsfri=trim($momsfri);
			$debet=trim($debet);
			$kredit=trim($kredit);
			$faktura=addslashes(trim($faktura));
#			$beskrivelse=trim($beskrivelse);
			$beskrivelse=addslashes(trim($beskrivelse));

			if ($amount < 0) {# Hvis beloebbet er negativt, byttes om paa debet og kredit.
				$tmp=$kredit; $kredit=$debet;	$debet=$tmp;
				$tmp=$k_type; $k_type=$d_type; $d_type=$tmp;
				$amount=$amount*-1;
			}
			
			if($id) {
				db_modify("update kassekladde set bilag = '$bilag', transdate = '$transdate', beskrivelse = '$beskrivelse', d_type = '$d_type', debet = '$debet', k_type = '$k_type', kredit = '$kredit', faktura = '$faktura', amount = '$amount', momsfri = '$momsfri', afd= '$afd' where id = '$id'");
			}
			else {
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, kladde_id) values ('$bilag', '$transdate', '$beskrivelse', '$d_type', '$debet', '$k_type', '$kredit', '$faktura', '$amount', '$momsfri', '$afd', '$kladde_id')");
			}
		}
	}
	elseif ($id) {
		db_modify("delete from kassekladde where id = $id");
	}
	$prebilag=$bilag;
}
######################################################################################################################################
function html_prep($temp)
{
	$temp=(trim($temp));
	$temp=(str_replace("&","!og!",$temp));
	$temp=(str_replace("'","!apostrof!",$temp));
	
	return $temp;
}
######################################################################################################################################
function tilbagefor($kladde_id)
{
	global $regnaar;
	global $connection;

	$query = db_select("select kladdenote from kladdeliste where id = '$kladde_id' and bogfort='!'");
	if ($row = db_fetch_array($query))
	{
		db_modify("delete from openpost where kladde_id = '$kladde_id'");
		db_modify("delete from transaktioner where kladde_id = '$kladde_id'");
		db_modify("update kladdeliste set bogfort = '-' where id = '$kladde_id'");
	}
}
######################################################################################################################################
function kopier_til_ny($kladde_id, $bilagsnr)
{
	global $regnaar;
	global $connection;
	global $brugernavn;

	if ($bilagsnr) {
		$query = db_select("select kladdenote from kladdeliste where id = '$kladde_id' and bogfort='V'");
		if ($row = db_fetch_array($query)){
			transaktion('begin');
			$kladdenote=$row['kladdenote'];
			$tidspkt=date("U");
			$kladdedate=date("Y-m-d");
			$ny_kladde_id=1;
			$query = db_select("select id from kladdeliste where id>=$kladde_id");
			while ($row = db_fetch_array($query)) {
				if ($ny_kladde_id<=$row[id]){$ny_kladde_id=$row[id]+1;}
			}
			db_modify("insert into kladdeliste (id, kladdenote, kladdedate, bogfort, oprettet_af) values ('$ny_kladde_id', '$kladdenote', '$kladdedate', '-', '$brugernavn')");
			$query = db_select("select * from kassekladde where kladde_id=$kladde_id order by bilag");
			while ($row = db_fetch_array($query)) {
				if (!isset($b_diff)) $b_diff=$bilagsnr-$row[bilag];
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, k_type, kredit, faktura, amount, momsfri, afd, kladde_id) values ($row[bilag]+$b_diff, '$row[transdate]', '$row[beskrivelse]', '$row[d_type]', '$row[debet]', '$row[k_type]', '$row[kredit]', '$row[faktura]', '$row[amount]', '$row[momsfri]', '$row[afd]', '$ny_kladde_id')");
			}
			transaktion('commit');
		}
		print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$ny_kladde_id\">";
		exit;
	} else {
		$query = db_select("select * from kassekladde order by bilag desc");
		$row = db_fetch_array($query);
		$bilagsnr=$row[bilag]+1;
		print "<form name=\"Form1\" action=kassekladde.php method=post>";
		print "<span style=center>Angiv 1. bilagsnr <input type=\"text\" size=4 name=bilagsnr value=$bilagsnr><br><br><br>";
		print "<input type=hidden name=kladde_id value=$kladde_id>";
		print "<input type=submit accesskey=\"k\" value=\"Kopier til ny\" name=\"submit\">&nbsp;<input type=button value=fortryd onClick=\"location.href='../includes/luk.php'\"><br></span>\n";
		exit;
	}
}
######################################################################################################################################
function nextfokus($fokus) 
{
	global $id;
	global $amount;

	if ($fokus) {
		$f_id=substr($fokus,4,(strlen($fokus)-4));
		if (strstr($fokus,"bila")) {$fokus="dato".$f_id;}
		elseif (strstr($fokus,"dato")) {$fokus="besk".$f_id;}
		elseif (strstr($fokus,"besk")) {$fokus="d_ty".$f_id;}
		elseif (strstr($fokus,"d_ty")) {$fokus="debe".$f_id;}
		elseif (strstr($fokus,"debe")) {$fokus="k_ty".$f_id;}
		elseif (strstr($fokus,"k_ty")) {$fokus="kred".$f_id;}
		elseif (strstr($fokus,"kred")) {$fokus="fakt".$f_id;}
		elseif (strstr($fokus,"fakt")) {$fokus="belo".$f_id;}
		elseif (strstr($fokus,"belo")) {
			$f_id++;
			$fokus="bila".$f_id;
		}
	} #else $fokus="bila".$x;
# 	if ($amount[$x-1]>0) {$fokus="bila".$x;}

	return $fokus;
}

######################################################################################################################################
function indsaet_linjer($kladde_id, $bilag, $dato)
 {
	$date=usdate($dato);
	$bilag = str_replace('+',':',$bilag); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($bilag, $antal) = split (':', $bilag);
	for ($x=1; $x<=$antal; $x++) db_modify("insert into kassekladde (bilag, kladde_id, transdate) values ('$bilag', '$kladde_id', '$date')");
}



?>

</tbody></table>

<script language="javascript">
document.kassekladde.<?php echo $fokus?>.focus();
</script>

</body></html>
