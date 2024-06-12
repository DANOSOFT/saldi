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

@session_start();
$s_id=session_id();
	
$css="../css/standard.css";		
$modulnr=2;	
$title="Modtageliste";	
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$sort=isset($_GET['sort'])? $_GET['sort']:Null;
$rf=isset($_GET['rf'])? $_GET['rf']:Null;
$vis=isset($_GET['vis'])? $_GET['vis']:Null;

if (!$sort) {
	$sort = "id";
	$rf = "desc";
}

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
		<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>
		<td width=\"10%\"  title=\"Klik her for at lukke modtagelisten\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=$returside accesskey=L>Luk</a></td>
		<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Modtageliste</td>
		<td width=\"10%\" title=\"Klik her for at oprette en ny modtagelse\" $top_bund onClick=\"javascript:liste=window.open('modtagelse.php?returside=modtageliste.php&tjek=-1','liste','<?php echo $jsvars ?>');liste.focus();\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=modtageliste.php?sort=$sort&rf=$rf&vis=$vis accesskey=N>Ny</a></td>
		</tbody></table>
		</td></tr>
		<tr><td valign=\"top\">
		<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";

if ($vis=='alle') {print "<tr><td colspan=6 align=center><a href=modtageliste.php?sort=$sort&rf=$rf>Vis egne</a></td></tr>";}
else {print "<tr><td colspan=6 align=center title='Klik her for at se alle lister'><a href=modtageliste.php?sort=$sort&rf=$rf&vis=alle>Vis alle</a></td></tr>";}
if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';}
else {$linjebg=$bgcolor5; $color='#000000';}
print "<tr bgcolor=\"$linjebg\">";
if (($sort == 'id')&&(!$rf)) {print "<td width = 5%><b><a href=modtageliste.php?sort=id&rf=desc>Id</a></b></td>\n";}
else {print "<td width = 5% title='Klik her for at sortere p&aring; ID'><b><a href=modtageliste.php?sort=id>Id</a></b></td>\n";}
if (($sort == 'listedate')&&(!$rf)) {print "<td width = 10%><b><a href=modtageliste.php?sort=listedate&rf=desc>Dato</a></b></td>\n";}
else {print "<td width = 10% title='Klik her for at sortere p&aring; dato'><b><a href=modtageliste.php?sort=initdate>Dato</a></b></td>\n";}
if (($sort == 'init_af')&&(!$rf)) {print "<td><b><a href=modtageliste.php?sort=init_af&rf=desc>Oprettet af</a></b></td>\n";}
else {print "<td title='Klik her for at sortere p&aring; ejer (den der har oprettet modtagelsen)'><b><a href=modtageliste.php?sort=init_af>Oprettet af</a></b></td>\n";}
#if (($sort == 'listenote')&&(!$rf)) {print "<td width = 70%><b><a href=modtageliste.php?sort=listenote&rf=desc>Bem&aelig;rkning</a></b></td>\n";}
#else {print "<td width = 70% title='Klik her for at sortere p&aring; bem&aelig;rkning'><b><a href=modtageliste.php?sort=listenote>Bem&aelig;rkning</a></b></td>\n";}
if (($sort == 'modtaget_af')&&(!$rf)) {print "<td><b><a href=modtageliste.php?sort=modtaget_af&rf=desc>Modtaget af</a></b></td>\n";}
else {print "<td title='Klik her for at sortere p&aring; \"bogf&oslash;rt af\"'><b><a href=modtageliste.php?sort=modtaget_af>Modtaget af</a></b></td>\n";}
if (($sort == 'modtagdate')&&(!$rf)) {print "<td><b><a href=modtageliste.php?sort=modtagdate&rf=desc>Modtagelsesdato</a></b></td>\n";}
else {print "<td><b><a href=modtageliste.php?sort=modtagdate>Modtagelsesdato</a></b></td>\n";}
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
		print "<tr><td colspan=3 align=right>TIP 1: </td><td>Du opretter en ny modtagelse ved at klikke p&aring; <u>Ny</u> &oslash;verst til h&oslash;jre.</td></tr>"; 
		if (db_fetch_array(db_select("select * from modtageliste",__FILE__ . " linje " . __LINE__))) {
			print "<tr><td colspan=3 align=right>TIP 2: </td><td>Du kan se dine kollegers lister ved at klikke p&aring; <u>Vis alle</u>.</td></tr>"; 
		}
	}
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
