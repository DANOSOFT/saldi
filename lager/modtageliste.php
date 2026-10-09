<?php
// ---------lager/modtageliste.php----------lap 2.0.9-------2009-09-24----------------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// -----------------------------------------------------------------------------------
// 20250516 Sulayman make sure the back button redirect to the previous page rather than the dashboard
// 20261008 Sawaneh Settings 4e: the old top-menu ($menu=='T') branches removed; the else legs stay (spec §7.6 R3).
// 20261008 Sawaneh The list on the new page head (page_bar): title, Ny modtagelse as the primary button (the popup's
//                  window features now really use $jsvars; the old bar printed the PHP tag as text).

@session_start();
$s_id=session_id();
	
$css="../css/standard.css";		
$modulnr=2;	
$title="Modtageliste";	
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/row-hover-style.js.php");


global $menu;

$sort=isset($_GET['sort'])? $_GET['sort']:Null;
$rf=isset($_GET['rf'])? $_GET['rf']:Null;
$vis=isset($_GET['vis'])? $_GET['vis']:Null;

if (!$sort) {
	$sort = "id";
	$rf = "desc";
}
// WP-5.12: back one page, not two (go(-2) skipped the page the user came from); a given returside is sanitised.
$backUrl = !empty($_GET['returside'])
    ? nav_sanitize_returside($_GET['returside'])
    : 'javascript:window.history.go(-1);';
if ($backUrl === '') $backUrl = 'javascript:window.history.go(-1);';
if ($popup) $returside="../includes/luk.php";
else $returside=$backUrl;

if ($menu=='S') {
	// 20261008 Sawaneh The new page head (topbar addendum §5, prototype_dashboard_tema v5) instead of the blue bar.
	include_once("../includes/stdFunc/pageBar.php");
	page_bar(array(
		'title' => findtekst('963|Modtageliste', $sprog_id),
		'back' => $returside,
		'primary' => array(findtekst('7005|Ny modtagelse', $sprog_id), "modtageliste.php?sort=$sort&rf=$rf&vis=$vis", 'accesskey' => 'N',
			'onclick' => "liste=window.open('modtagelse.php?returside=modtageliste.php&tjek=-1','liste','" . (isset($jsvars) ? $jsvars : '') . "');liste.focus();"),
	));
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
		   <tr><td valign=\"top\">
		   <table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
} else {
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
		<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
		<td width=\"10%\"  title=\"".findtekst('2230|Klik her for at lukke modtagelisten', $sprog_id)."\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>
		<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('963|Modtageliste', $sprog_id)."</td>
		<td width=\"10%\" title=\"".findtekst('2231|Klik her for at oprette en ny modtagelse', $sprog_id)."\" $top_bund onClick=\"javascript:liste=window.open('modtagelse.php?returside=modtageliste.php&tjek=-1','liste','<?php echo $jsvars ?>');liste.focus();\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=modtageliste.php?sort=$sort&rf=$rf&vis=$vis accesskey=N>".findtekst('39|Ny', $sprog_id)."</a></td>
		</tbody></table>
		</td></tr>
		<tr><td valign=\"top\">
		<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
}

if ($vis=='alle') {print "<tr><td colspan=6 align=center><a href=modtageliste.php?sort=$sort&rf=$rf>".findtekst('641|Vis egne', $sprog_id)."</a></td></tr>";}
else {print "<tr><td colspan=6 align=center title='Klik her for at se alle lister'><a href=modtageliste.php?sort=$sort&rf=$rf&vis=alle>".findtekst('636|Vis alle', $sprog_id)."</a></td></tr>";}
if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';}
else {$linjebg=$bgcolor5; $color='#000000';}
print "<tr bgcolor=\"$linjebg\">";
if (($sort == 'id')&&(!$rf)) {print "<td width = 5%><b><a href=modtageliste.php?sort=id&rf=desc>Id</a></b></td>\n";}
else {print "<td width = 5% title='Klik her for at sortere p&aring; ID'><b><a href=modtageliste.php?sort=id>Id</a></b></td>\n";}
if (($sort == 'listedate')&&(!$rf)) {print "<td width = 10%><b><a href=modtageliste.php?sort=listedate&rf=desc>".findtekst('635|Dato', $sprog_id)."</a></b></td>\n";}
else {print "<td width = 10% title='Klik her for at sortere p&aring; dato'><b><a href=modtageliste.php?sort=initdate>".findtekst('635|Dato', $sprog_id)."</a></b></td>\n";}
if (($sort == 'init_af')&&(!$rf)) {print "<td><b><a href=modtageliste.php?sort=init_af&rf=desc>".findtekst('958|Oprettet af', $sprog_id)."</a></b></td>\n";}
else {print "<td title='Klik her for at sortere p&aring; ejer (den der har oprettet modtagelsen)'><b><a href=modtageliste.php?sort=init_af>".findtekst('958|Oprettet af', $sprog_id)."</a></b></td>\n";}
#if (($sort == 'listenote')&&(!$rf)) {print "<td width = 70%><b><a href=modtageliste.php?sort=listenote&rf=desc>Bem&aelig;rkning</a></b></td>\n";}
#else {print "<td width = 70% title='Klik her for at sortere p&aring; bem&aelig;rkning'><b><a href=modtageliste.php?sort=listenote>Bem&aelig;rkning</a></b></td>\n";}
if (($sort == 'modtaget_af')&&(!$rf)) {print "<td><b><a href=modtageliste.php?sort=modtaget_af&rf=desc>".findtekst('959|Modtaget af', $sprog_id)."</a></b></td>\n";}
else {print "<td title='Klik her for at sortere p&aring; \"bogf&oslash;rt af\"'><b><a href=modtageliste.php?sort=modtaget_af>".findtekst('959|Modtaget af', $sprog_id)."</a></b></td>\n";}
if (($sort == 'modtagdate')&&(!$rf)) {print "<td><b><a href=modtageliste.php?sort=modtagdate&rf=desc>".findtekst('960|Modtagelsesdato', $sprog_id)."</a></b></td>\n";}
else {print "<td><b><a href=modtageliste.php?sort=modtagdate>".findtekst('960|Modtagelsesdato', $sprog_id)."</a></b></td>\n";}
print "</tr>\n";
$tjek=0;
#$sqhost = "localhost";
	if ($vis == 'alle') {$vis = '';} 
	else {$vis="and init_af = '".$brugernavn."'";}
	$tidspkt=date("U");
	$query = db_select("select * from modtageliste where modtaget = '-' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$tjek++;
		$liste="liste".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($row['tidspkt'])>3600)||($row['hvem']==$brugernavn)) {
			print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$liste=window.open('modtagelse.php?tjek=$row[id]&liste_id=$row[id]&returside=modtageliste.php','$liste','".$jsvars."');$liste.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
		}
		else {print "<td><span title= 'liste er l&aring;st af $row[hvem]'>$row[id]</span></td>";}
		$initdato=dkdato($row['initdate']);
		print "<td>$initdato<br></td>";
		print "<td>".htmlentities(stripslashes($row['init_af']))."<br></td>";
#		print "<td>".htmlentities(stripslashes($row['listenote']))."<br></td>";
#		print "<td align = center>$row[modtaget]<br></td>";
		print "<td>-</td><td>-</td></tr>";
	}
	print "<tr><td colspan=6><hr></td></tr>";
	$query = db_select("select * from modtageliste where modtaget = '!' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$liste="liste".$row[id];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
			print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$liste=window.open('modtagelse.php?liste_id=$row[id]&returside=modtageliste.php','$liste','".$jsvars."');$liste.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
		}
		else {print "<td><span title= 'liste er l&aring;st af $row[hvem]'>$row[id]</span></td>";}
#		print "<tr>";
#		print "<td> $row[id]<br></td>";
		$listedato=dkdato($row['initdate']);
		print "<td>$listedato<br></td>";
		print "<td>".htmlentities(stripslashes($row['init_af']))."<br></td>";
		print "<td>".htmlentities(stripslashes($row['listenote']))."<br></td>";
		print "<td align = center>$row[modtaget]<br></td>";
		print "</tr>";
	}
	if ($row){print "<tr><td colspan=6><hr></td></tr>";}
	$query = db_select("select * from modtageliste where modtaget = 'V' $vis order by $sort $rf",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$tjek++;
		$liste="liste".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if ($popup) print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$liste=window.open('modtagelse.php?liste_id=$row[id]&returside=../includes/luk.php','$liste','".$jsvars."');$liste.focus();\"><span style=\"text-decoration: underline;\">$row[id]</a></span></td>";
   	else print "<td><a href=modtagelse.php?liste_id=$row[id]&returside=modtageliste.php>$row[id]</a><br></td>";
		$listedato=dkdato($row['initdate']);
		print "<td>$listedato<br></td>";
		print "<td>".htmlentities(stripslashes($row['init_af']))."<br></td>";
		print "<td>$row[modtaget_af]<br></td>";
		$modtagelsesdato=dkdato($row['modtagdate']);
		print "<td>$modtagelsesdato<br></td>";

		print "</tr>";
	}
	if (!$tjek) {
		print "<tr><td colspan=5 height=25> </td></tr>"; 
		print "<tr><td colspan=3 align=right>TIP 1: </td><td>".findtekst('961|Du opretter en ny modtagelse ved at klikke på Ny øverst til højre.', $sprog_id)."</td></tr>"; 
		if (db_fetch_array(db_select("select * from modtageliste",__FILE__ . " linje " . __LINE__))) {
			print "<tr><td colspan=3 align=right>TIP 2: </td><td>".findtekst('962|Du kan se dine kollegers lister ved at klikke på Vis alle.', $sprog_id)."</td></tr>"; 
		}
	}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
