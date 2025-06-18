<?php
// ---------debitor/betalingsliste.php-----------------------Patch 3.5.9---2015.11.04---
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
// -----------------------------------------------------------------------------------
//
// 2015.11.04 Kopieret fra kreditor (phr) 
// 20211102 MSC - Implementing new design

@session_start();
$s_id=session_id();
		
$modulnr=12;	
$title="Betalingsliste";	
$css="../css/standard.css";

global $menu;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/row-hover-style-with-link-no-input.js.php");

$sort=isset($_GET['sort'])? $_GET['sort']:Null;
$rf=isset($_GET['rf'])? $_GET['rf']:Null;
$vis=isset($_GET['vis'])? $_GET['vis']:Null;
$deleteList=isset($_GET['deleteList'])? $_GET['deleteList']:Null;
$confirmDelete=isset($_GET['confirmDelete'])? $_GET['confirmDelete']:Null;

print "<meta http-equiv=\"refresh\" content=\"150;URL=betalingsliste.php?sort=$sort&rf=$rf&vis=$vis\">";

if ($deleteList) {
	if ($confirmDelete == 'yes') {
		$qtxt = "delete from betalinger where liste_id = '$deleteList'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "delete from betalingsliste where id = '$deleteList'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} else {
		print "<center><br><br><br>";
		print "<big><b>Slet liste $deleteList ?</b></big><br><br>";
		print "<td><span onclick = \"location.href = 'betalingsliste.php?sort=$sort&rf=$rf&vis=";
		($vis)?print $vis:print 'alle';
		print "'\"><Button style = 'width:50px;'>Nej</button></span>&nbsp;&nbsp;&nbsp;";
		print "<td><span onclick = \"location.href = 'betalingsliste.php?sort=$sort&rf=$rf&vis=";
		($vis)?print $vis:print 'alle';
		print "&deleteList=$deleteList&confirmDelete=yes'\"><Button style = 'width:50px;'>Ja</button></span>";
		exit;
	}
}

if (!$sort) {
	$sort = "id";
	$rf = "desc";
}

if ($menu=='T') {

	include_once '../includes/topmenu/header.php';
	print "<div class='$kund'>$title</div>
	<div class='content-noside'>";
	if ($vis=='alle') {
		print "<center><div style='padding:10px'><input type='button' onclick=\"location.href='betalingsliste.php?sort=$sort&rf=$rf'\" value='Vis egne'>";
	} else { 
		print "<center><div style='padding:10px'><input type='button' onclick=\"location.href='betalingsliste.php?sort=$sort&rf=$rf&vis=alle'\" value='Vis alle'>";
	}
	print "&nbsp;•&nbsp;<input type='button' onclick=\"location.href='betalinger.php?id=0'\" accesskey=N value='Ny'></center>";
	print "<div class='dataTablediv'><table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top' class='dataTable'><thead>";
} else {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";
	print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href='rapport.php' accesskey=L>Luk</a></td>";
	print "<td width='80%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'>Betalingsliste</td>";
	print "<td width='10%' $top_bund><font face='Helvetica, Arial, sans-serif' color='#000066'><a href=betalinger.php?id=0 accesskey=N>Ny</a></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td valign='top'>";
	print "<table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top'>";
	if ($vis=='alle') {
		print "<tr><td colspan=6 align=center><a href=betalingsliste.php?sort=$sort&rf=$rf>Vis egne</a></td></tr>";
	} else {
		print "<tr><td colspan=6 align=center><a href=betalingsliste.php?sort=$sort&rf=$rf&vis=alle>Vis alle</a></td></tr>";
	}
}

if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';}
else {$linjebg=$bgcolor5; $color='#000000';}
if ($menu=='T') {
	print "<tr>";
	if (($sort == 'id')&&(!$rf)) print "<th width = 5%><b><a href=betalingsliste.php?sort=id&rf=desc>Id</a></b></th>\n";
	else print "<th width = 5%><b><a href=betalingsliste.php?sort=id>Id</a></b></th>\n";
	if (($sort == 'listedate')&&(!$rf)) print "<th width = 10%><b><a href=betalingsliste.php?sort=listedate&rf=desc>Dato</a></b></th>\n";
	else print "<th width = 10%><b><a href=betalingsliste.php?sort=listedate>Dato</a></b></th>\n";
	if (($sort == 'oprettet_af')&&(!$rf)) print "<th><b><a href=betalingsliste.php?sort=oprettet_af&rf=desc>Ejer</a></b></th>\n";
	else print "<th><b><a href=betalingsliste.php?sort=oprettet_af>Ejer</a></b></th>\n";
	if (($sort == 'listenote')&&(!$rf)) print "<th width = 70%><b><a href=betalingsliste.php?sort=listenote&rf=desc>Bem&aelig;rkning</a></b></th>\n";
	else print "<th width = 70%><b><a href=betalingsliste.php?sort=listenote>Bem&aelig;rkning</a></b></th>\n";
	if (($sort == 'bogforingsdate')&&(!$rf)) print "<th align=center><b><a href=betalingsliste.php?sort=bogforingsdate&rf=desc>Lukket</a></b></th>\n";
	else print "<th class='text-center'><b>Lukket</b></th>\n"; 
	if (($sort == 'bogfort_af')&&(!$rf)) print "<th><b><a href=betalingsliste.php?sort=bogfort_af&rf=desc>Af</a></b></th>\n";
	else print "<th><b><a href=betalingsliste.php?sort=bogfort_af>af</a></b></th>\n";
	print "</tr></thead><tbody>\n";
} else {
	print "<tr>";
	if (($sort == 'id')&&(!$rf)) print "<td width = 5%><b><a href=betalingsliste.php?sort=id&rf=desc>Id</a></b></td>\n";
	else print "<td width = 5%><b><a href=betalingsliste.php?sort=id>Id</a></b></td>\n";
	if (($sort == 'listedate')&&(!$rf)) print "<td width = 10%><b><a href=betalingsliste.php?sort=listedate&rf=desc>Dato</a></b></td>\n";
	else print "<td width = 10%><b><a href=betalingsliste.php?sort=listedate>Dato</a></b></td>\n";
	if (($sort == 'oprettet_af')&&(!$rf)) print "<td><b><a href=betalingsliste.php?sort=oprettet_af&rf=desc>Ejer</a></b></td>\n";
	else print "<td><b><a href=betalingsliste.php?sort=oprettet_af>Ejer</a></b></td>\n";
	if (($sort == 'listenote')&&(!$rf)) print "<td width = 70%><b><a href=betalingsliste.php?sort=listenote&rf=desc>Bem&aelig;rkning</a></b></td>\n";
	else print "<td width = 70%><b><a href=betalingsliste.php?sort=listenote>Bem&aelig;rkning</a></b></td>\n";
	if (($sort == 'bogforingsdate')&&(!$rf)) print "<td align=center><b><a href=betalingsliste.php?sort=bogforingsdate&rf=desc>Lukket</a></b></td>\n";
	else print "<td align=center><b>Lukket</b></td>\n"; 
	if (($sort == 'bogfort_af')&&(!$rf)) print "<td><b><a href=betalingsliste.php?sort=bogfort_af&rf=desc>Af</a></b></td>\n";
	else print "<td><b><a href=betalingsliste.php?sort=bogfort_af>af</a></b></td>\n";
	print "</tr>\n";
}
	$tidspkt=date("U");
	$qtxt = "select * from betalingsliste where bogfort = '-' ";
	if ($vis != 'alle') $qtxt.="and oprettet_af = '".$brugernavn."' ";
	$qtxt.= "order by $sort $rf";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		$liste="liste".$r['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($r['tidspkt'])>3600)||($r['hvem']==$brugernavn)) {
			print "<td><a href=\"betalinger.php?tjek=$r[id]&liste_id=$r[id]&returside=betalingsliste.php\">$r[id]</a></td>";
		}
		else print "<td><span title= 'liste er l&aring;st af $r[hvem]'>$r[id]</span></td>";
		$listedato=dkdato($r['listedate']);
		print "<td>$listedato<br></td>";
		print "<td>".htmlentities(stripslashes($r['oprettet_af']))."<br></td>";
		print "<td>".htmlentities(stripslashes($r['listenote']))."<br></td>";
		print "<td align = center>$r[bogfort]<br></td>";
		print "<td></td>";
#		print "<td><span onclick = \"location.href = 'betalingsliste.php?sort=$sort&rf=$rf&vis=";
		$txt = "betalingsliste.php?sort=$sort&rf=$rf&deleteList=$r[id]";
		print "<td><span onclick = \"location.href = '$txt'\">";
		print "<img src=\"../ikoner/delete.png\" style=\"border: 0px solid; width: 13px; height: 13px;\"></span><br></td>";
		print "</tr>\n";
	}
	if ($menu=='T') {
		print "<tr><td colspan=10 class='border-hr-top'></td></tr>\n";
	} else {
		print "<tr><td colspan=6><hr></td></tr>";
	}
/*	
	$qtxt = "select * from betalingsliste where bogfort = '!' ";
	if ($vis != 'alle') $qtxt.="and oprettet_af = '".$brugernavn."' ";
	$qtxt.= "order by $sort $rf";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$liste="liste".$r['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($r['tidspkt'])>3600)||($r[hvem]==$brugernavn)) {
			print "<td><a href=\"betalinger.php?liste_id=$r[id]&returside=betalingsliste.php\">$r[id]</a></td>";
		}
		else print "<td><span title= 'liste er l&aring;st af $r[hvem]'>$r[id]</span></td>";
#		print "<tr>";
#		print "<td> $r[id]<br></td>";
		$listedato=dkdato($r['listedate']);
		print "<td>$listedato<br></td>";
    print "<td>".htmlentities(stripslashes($r['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
    print "<td>".htmlentities(stripslashes($r['kladdenote']),ENT_QUOTES,$charset)."<br></td>";
		print "<td align = center>$r[bogfort]<br></td>";
		$txt = "betalingsliste.php?sort=$sort&rf=$rf&vis=";
		($vis)?$txt.= $vis:$txt.= 'alle';
		$txt.= "&deleteList=$r[id]";
#		$txt = str_replace("'",$txt); 
		$txt = htmlentities($txt);
		print "<td><span onclick = \"location.href = '$txt'\">";
		print "<img src=\"../ikoner/delete.png\" style=\"border: 0px solid; width: 13px; height: 13px;\"></span><br></td>";
		print "</tr>";
	}
*/	
	if ($r)print "<tr><td colspan=6><hr></td></tr>";
	$qtxt = "select * from betalingsliste where bogfort = 'V' ";
	if ($vis != 'alle') $qtxt.="and oprettet_af = '".$brugernavn."' ";
	$qtxt.= "order by $sort $rf";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)){
		$liste="liste".$r['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=\"betalinger.php?liste_id=$r[id]&returside=betalingsliste.php'\">$r[id]</a></td>";
#		print "<td><a href=kasseliste.php?liste_id=$r[id]&returside=betalingsliste.php>$r[id]</a><br></td>";
		$listedato=dkdato($r['listedate']);
		print "<td>$listedato<br></td>";
                print "<td>".htmlentities(stripslashes($r['oprettet_af']),ENT_QUOTES,$charset)."<br></td>";
                print "<td>".htmlentities(stripslashes($r['listenote']),ENT_QUOTES,$charset)."<br></td>";
## Da der ikke blev sat bogfringsdato foer ver. 0.23 skal det saettes hak ved lister bogfrt fr denne version...
		if ($r['bogforingsdate']){
			$bogforingsdato=dkdato($r['bogforingsdate']);
			print "<td align = center>$bogforingsdato<br></td>";
		}	else {
			print "<td align = center>$r[bogfort]<br></td>";
		}
		print "<td>$r[bogfort_af]<br></td>";
/*
		$txt = "betalingsliste.php?sort=$sort&rf=$rf&";
		($vis)?$txt.= 'oprettet af=$brugernavn':$txt.= 'vis=alle';
		$txt.= "&deleteList=$r[id]";
#		$txt = str_replace("'",$txt); 
		$txt = htmlentities($txt);
*/
		$txt = "betalingsliste.php?sort=$sort&rf=$rf&deleteList=$r[id]";
		print "<td><span onclick = \"location.href = '$txt'\">";
		print "<img src=\"../ikoner/delete.png\" style=\"border: 0px solid; width: 13px; height: 13px;\"></span><br></td>";
		print "</tr>\n";
	}

if ($menu=='T') {
	print "
	</tbody>
	<tfoot><tr><td></td></tr></tfoot>
	</table>
	</div>
	<center><input type='button' onclick=\"location.href='rapport.php'\" accesskey='L' value='".findtekst(30,$sprog_id)."'></center>
	";
	include_once '../includes/topmenu/footerDebRapporter.php';
} else {
	print "
	</tbody>
	</table>
		</td></tr>
	</tbody></table>
	</body></html>
";
}


?>
