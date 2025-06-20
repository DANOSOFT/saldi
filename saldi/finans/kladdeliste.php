<?php
// ---------------------------------------------------finans/kladdeliste.php----------Patch 1.0.7---------
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
		
$modulnr=3;		
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/db_query.php");
		
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><html><head><title>SALDI - kladdeliste</title><meta http-equiv="content-type" content="text/html; charset=ISO-8859-1"></head>
<body bgcolor="#339999" link="#000000" vlink="#000000" alink="#000000" center="">
<div align="center">

<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
	<tr><td height = "25" align="center" valign="top">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td width="25%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>
			<td width="50%" bgcolor="<?php echo $bgcolor2 ?>" align="center"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Kladdeliste</small></td>
			<td width="25%" bgcolor="<?php echo $bgcolor2 ?>" align="right" onClick="javascript:kladde=window.open('kassekladde.php?returside=kladdeliste.php&tjek=-1','kladde','scrollbars=1,resizable=1');kladde.focus();"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><?php echo"<a href=kladdeliste.php?sort=$sort&rf=$rf&vis=$vis accesskey=N>"?>Ny</a></small></td>
			</tbody></table>
	</td></tr>
 <tr><td valign="top">
<table cellpadding="1" cellspacing="1" border="0" width="100%" valign = "top">
<?php
$sort = $_GET['sort'];
$rf = $_GET['rf'];
$vis = $_GET['vis'];
print "<meta http-equiv=\"refresh\" content=\"150;URL=kladdeliste.php?sort=$sort&rf=$rf&vis=$vis\">";


if (!$sort) {
	$sort = "id";
	$rf = "desc";
}
if ($vis=='alle') {print "<tr><td colspan=6 align=center>$font<small><a href=kladdeliste.php?sort=$sort&rf=$rf>vis egne</a></small></td></tr>";}
else {print "<tr><td colspan=6 align=center>$font<small><a href=kladdeliste.php?sort=$sort&rf=$rf&vis=alle>vis alle</a></small></td></tr>";}
if ((!isset($linjebg))||($linjebg!=$bgcolor)) {$linjebg=$bgcolor; $color='#000000';}
else {$linjebg=$bgcolor5; $color='#000000';}
print "<tr bgcolor=\"$linjebg\">";
if (($sort == 'id')&&(!$rf)) {print "<td width = 5%><small><b>$font<a href=kladdeliste.php?sort=id&rf=desc>Id</a></b></small></td>\n";}
else {print "<td width = 5%><small><b>$font<a href=kladdeliste.php?sort=id>Id</a></b></small></td>\n";}
if (($sort == 'kladdedate')&&(!$rf)) {print "<td width = 10%><small><b>$font<a href=kladdeliste.php?sort=kladdedate&rf=desc>Dato</a></b></small></td>\n";}
else {print "<td width = 10%><small><b>$font<a href=kladdeliste.php?sort=kladdedate>Dato</a></b></small></td>\n";}
if (($sort == 'oprettet_af')&&(!$rf)) {print "<td><small><b>$font<a href=kladdeliste.php?sort=oprettet_af&rf=desc>Ejer</a></b></small></td>\n";}
else {print "<td><small><b>$font<a href=kladdeliste.php?sort=oprettet_af>Ejer</a></b></small></td>\n";}
if (($sort == 'kladdenote')&&(!$rf)) {print "<td width = 70%><small><b>$font<a href=kladdeliste.php?sort=kladdenote&rf=desc>Bem&aelig;rkning</a></b></small></td>\n";}
else {print "<td width = 70%><small><b>$font<a href=kladdeliste.php?sort=kladdenote>Bem&aelig;rkning</a></b></small></td>\n";}
if (($sort == 'bogforingsdate')&&(!$rf)) {print "<td align=center><small><b>$font<a href=kladdeliste.php?sort=bogforingsdate&rf=desc>Bogf&oslash;rt</a></b></small></td>\n";}
else {print "<td align=center><small><b>$font<a href=kladdeliste.php?sort=bogforingsdate>Bogf&oslash;rt</a></b></small></td>\n";}
if (($sort == 'bogfort_af')&&(!$rf)) {print "<td><small><b>$font<a href=kladdeliste.php?sort=bogfort_af&rf=desc>Af</a></b></small></td>\n";}
else {print "<td><small><b>$font<a href=kladdeliste.php?sort=bogfort_af>af</a></b></small></td>\n";}
print "</tr>\n";
 
#$sqhost = "localhost";
	if ($vis == 'alle') {$vis = '';} 
	else {$vis="and oprettet_af = '".$brugernavn."'";}
	$tidspkt=date("U");
	$query = db_select("select * from kladdeliste where bogfort = '-' $vis order by $sort $rf");
	while ($row = db_fetch_array($query))
	{
		$kladde="kladde".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($row['tidspkt'])>3600)||($row['hvem']==$brugernavn)) {
			print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?tjek=$row[id]&kladde_id=$row[id]&returside=kladdeliste.php','$kladde','scrollbars=1,resizable=1');$kladde.focus();\"><small>$font<span style=\"text-decoration: underline;\">$row[id]</a></span></small></td>";
		}
		else {print "<td><small>$font<span title= 'Kladde er l&aring;st af $row[hvem]'>$row[id]</span></small></td>";}
		$kladdedato=dkdato($row['kladdedate']);
		print "<td><small>$font$kladdedato<br></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row['oprettet_af']))."<br></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row['kladdenote']))."<br></small></td>";
		print "<td align = center><small>$font$row[bogfort]<br></small></td>";
		print "<td></td></tr>";
	}
	print "<tr><td colspan=6><hr></td></tr>";
	$query = db_select("select * from kladdeliste where bogfort = '!' $vis order by $sort $rf");
	while ($row = db_fetch_array($query)) {
		$kladde="kladde".$row[id];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)) {
		print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php','$kladde','scrollbars=1,resizable=1');$kladde.focus();\"><small>$font<span style=\"text-decoration: underline;\">$row[id]</a></span></small></td>";
		}
		else {print "<td><small>$font<span title= 'Kladde er l&aring;st af $row[hvem]'>$row[id]</span></small></td>";}
#		print "<tr>";
#		print "<td><small>$font $row[id]<br></small></td>";
		$kladdedato=dkdato($row[kladdedate]);
		print "<td><small>$font$kladdedato<br></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row[oprettet_af]))."<br></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row[kladdenote]))."<br></small></td>";
		print "<td align = center><small>$font$row[bogfort]<br></small></td>";
		print "</tr>";
	}
	if ($row){print "<tr><td colspan=6><hr></td></tr>";}
	$query = db_select("select * from kladdeliste where bogfort = 'V' $vis order by $sort $rf");
	while ($row = db_fetch_array($query)){
		$kladde="kladde".$row['id'];
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td  onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kladde=window.open('kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php','$kladde','scrollbars=1,resizable=1');$kladde.focus();\"><small>$font<span style=\"text-decoration: underline;\">$row[id]</a></span></small></td>";
#		print "<td><small>$font<a href=kassekladde.php?kladde_id=$row[id]&returside=kladdeliste.php>$row[id]</a><br></small></td>";
		$kladdedato=dkdato($row['kladdedate']);
		print "<td><small>$font$kladdedato<br></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row['oprettet_af']))."<br></small></td>";
		print "<td><small>$font".htmlentities(stripslashes($row['kladdenote']))."<br></small></td>";
## Da der ikke blev sat bogfringsdato foer ver. 0.23 skal det saettes hak ved kladder bogfrt fr denne version...
		if ($row['bogforingsdate']){
			$bogforingsdato=dkdato($row['bogforingsdate']);
			print "<td align = center><small>$font$bogforingsdato<br></small></td>";
		}
		else {print "<td align = center><small>$font$row[bogfort]<br></small></td>";}
		print "<td><small>$font$row[bogfort_af]<br></small></td>";

		print "</tr>";
	}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
