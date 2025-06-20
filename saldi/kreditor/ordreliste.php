<?php
// -------------------------------------------------------kreditor/ordreliste.php--------------lap 1.0.9----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
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

$title="Kreditorer";
$modulnr=7;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");


print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Ordreliste - Leverand&oslash;rer</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$valg=$_GET['valg'];
$sort = $_GET['sort'];
$nysort = $_GET['nysort'];
 $tidspkt=date("U");
	 
if (!$valg) {$valg = "ordre";}
if (!$sort) {$sort = "ordrenr_desc";}
elseif ($nysort==$sort){$sort=$sort."_desc";}
elseif ($nysort) {$sort=$nysort;}

 if ($valg!="faktura") {print "<meta http-equiv=\"refresh\" content=\"10;URL=ordreliste.php?sort=$sort&valg=$valg\">";}
		
print "<div align=center>";
print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=0 cellpadding=0><tbody>";
print "<td width=25% bgcolor= $bgcolor2>$font $color<small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>";

print "<td width=50% bgcolor=$bgcolor2 align=center><table border=0 cellspacing=0 cellpadding=0><tbody>";
# print "<td witth=25% align=center>$font<small>Lev.</td>";
if ($valg=='tilbud') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href=ordreliste.php?sort=$sort&valg=tilbud>&nbsp;Forslag&nbsp;</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href=ordreliste.php?sort=$sort&valg=tilbud>&nbsp;Forslag&nbsp;</a></td>";}
if ($valg=='ordrer') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href=ordreliste.php?sort=$sort&valg=ordrer>&nbsp;Ordrer&nbsp;</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href=ordreliste.php?sort=$sort&valg=ordrer>&nbsp;Ordrer&nbsp;</a></td>";}
if ($valg=='faktura') {print "<td width = 20% align=center bgcolor=$bgcolor>$font<small><a href=ordreliste.php?sort=$sort&valg=faktura>&nbsp;Faktura&nbsp;</a></td>";}
else {print "<td width = 20% align=center>$font<small><a href=ordreliste.php?sort=$sort&valg=faktura>&nbsp;Faktura&nbsp;</a></td>";}

print "</tbody></table></td>";

print "<td width=25% align=right	bgcolor=$bgcolor2 onClick=\"javascript:kordre=window.open('ordre.php?returside=ordreliste.php','kordre','scrollbars=1,resizable=1');ordre.focus();\">$font<small><a accesskey=N href=ordreliste.php?sort=$sort&valg=$valg>Ny</a></small></td>";
print "</tbody></table>";
print "</td></tr>";
print " <tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=95% valign = top>";
print "<tbody>";
print "<tr>";
print " <td align=right width=50><small><b>$font<a href='ordreliste.php?nysort=ordrenr&sort=$sort&valg=$valg'>Ordrenr.</b></small></td>";
if ($valg=='faktura') {
	print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=modtagelse&sort=$sort&valg=$valg'>Modt.nr.</b></small></td>";
	print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=fakturanr&sort=$sort&valg=$valg'>Faktnr.</b></small></td>";
}
print "<td width=50></td>";
if ($valg=='tilbud') {print "<td><small><b>$font<a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg'>Tilbudsdato</b></small></td>";}
else{
	print "<td><small><b>$font<a href='ordreliste.php?nysort=ordredate&sort=$sort&valg=$valg'>Ordredato</b></small></td>";
	print "<td><small><b>$font<a href='ordreliste.php?nysort=levdate&sort=$sort&valg=$valg'>Levdato</b></small></td>";
}
if ($valg=='faktura') {print "<td><small><b>$font<a href='ordreliste.php?nysort=fakturadate&sort=$sort&valg=$valg'>Fakt.dato</b></small></td>";}
print " <td><small><b>$font<a href='ordreliste.php?nysort=kontonr&sort=$sort&valg=$valg'>Leverand&oslash;rnr</b></small></td>";
print " <td><small><b>$font<a href='ordreliste.php?nysort=firmanavn&sort=$sort&valg=$valg'>Navn</a></b></small></td>";
print " <td><small><b>$font<a href='ordreliste.php?nysort=lev_navn&sort=$sort&valg=$valg'>Leveres til</a></b></small></td>";
if ($valg=='tilbud') {print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg'>Tilbudssum</a></b></small></td>";}
elseif ($valg=='ordrer'){print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg'>Ordresum</a></b></small></td>";}
else {print "<td align=right><small><b>$font<a href='ordreliste.php?nysort=sum&sort=$sort&valg=$valg'>Fakturasum</a></b></small></td>";}
print "</tr>";
$sort=str_replace("_"," ",$sort);
if ($valg=="tilbud") {
	$query = db_select("select * from ordrer where (art = 'KO' or art = 'KK') and status < 1 order by $sort");
	while ($row =db_fetch_array($query)){
		$ordre="ordre".$row[id];
		 if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)){
			$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
			$understreg='<span style="text-decoration: underline;">';
			$linjetext="";
		}
		else {
			$javascript="onClick=\"javascript:$ordre.focus();\"";
			$understreg='';
			$linjetext="<span title= 'Ordre er l&aring;st af $row[hvem]'>";
		}
		print "<tr>";
		if ($row[art]=='DK'){print "<td align=right $javascript><small>$font (KN)&nbsp; $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
		else {print "<td align=right $javascript><small>$font $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
		print "<td></td>";
		$ordredato=dkdato($row[ordredate]);
		print "<td><small>$font$ordredato<br></small></td>";
		print "<td><small>$font$row[kontonr]<br></small></td>";
		print "<td><small>$font$row[firmanavn]<br></small></td>";
		print "<td><small>$font$row[lev_navn]<br></small></td>";
		if ($row[art]=='DK') {$sum=dkdecimal($row[sum])*-1;}
		else {$sum=dkdecimal($row[sum]);}
		print "<td align=right><small>$font$sum<br></small></td>";
		print "</tr>";
	}
	print "<tr><td colspan=7><hr></td></tr>";
}
elseif ($valg=='ordrer'){
	$query = db_select("select * from ordrer where	(art = 'KO' or art = 'KK') and (status = 1 or status = 2) order by $sort");
	while ($row =db_fetch_array($query)){
		$ordre="ordre".$row[id];
		if (($tidspkt-($row[tidspkt])>3600)||($row[hvem]==$brugernavn)){
			$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?tjek=$row[id]&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
			$understreg='<span style="text-decoration: underline;">';
			$linjetext="";
		}
		else {
			$javascript="onClick=\"javascript:$ordre.focus();\"";
			$understreg='';
			$linjetext="<span title= 'Ordre er l&aring;st af $row[hvem]'>";
		}
		print "<tr>";
		if ($row[art]=='DK'){print "<td align=right $javascript><small>$font(KN)&nbsp; $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
		else {print "<td align=right $javascript><small>$font $linjetext $understreg $row[ordrenr]</span><br></small></td>";}
		print "<td></td>";
		$ordredato=dkdato($row[ordredate]);
		print "<td><small>$font$ordredato<br></small></td>";
		$levdato=dkdato($row[levdate]);
		print "<td><small>$font$levdato<br></small></td>";
		print "<td><small>$font$row[kontonr]<br></small></td>";
		print "<td><small>$font$row[firmanavn]<br></small></td>";
		print "<td><small>$font$row[lev_navn]<br></small></td>";
		if ($row[art]=='DK') {$sum=dkdecimal($row[sum])*-1;}
		else {$sum=dkdecimal($row[sum]);}
		print "<td align=right><small>$font$sum<br></small></td>";
		print "</tr>";
	}
	print "<tr><td colspan=8><hr></td></tr>";
}
else{
	$query = db_select("select * from ordrer where (art = 'KO' or art = 'KK') and status >= 3 order by $sort");
	while ($row =db_fetch_array($query)){
		$ordre="ordre".$row[id];
		$javascript="onClick=\"javascript:$ordre=window.open('ordre.php?&id=$row[id]&returside=ordreliste.php','$ordre','scrollbars=1,resizable=1');$ordre.focus();\"";
		$understreg='<span style="text-decoration: underline;">';
		print "<tr>";
		if ($row[art]=='DK'){print "<td align=right $javascript><small>$font(KN)&nbsp; $understreg $row[ordrenr]</span><br></small></td>";}
		else {print "<td align=right $javascript><small>$font $understreg $row[ordrenr]</span><br></small></td>";}
		print "<td align=right><small>$font$row[modtagelse]</small></td>";
		print "<td align=right><small>$font$row[fakturanr]</small></td>";
		print"<td></td>";
		$ordredato=dkdato($row[ordredate]);
		print "<td><small>$font$ordredato<br></small></td>";
		$levdato=dkdato($row[levdate]);
		print "<td><small>$font$levdato<br></small></td>";
		$faktdato=dkdato($row[fakturadate]);
		print "<td><small>$font$faktdato<br></small></td>";
		print "<td><small>$font$row[kontonr]<br></small></td>";
		print "<td><small>$font$row[firmanavn]<br></small></td>";
		print "<td><small>$font$row[lev_navn]<br></small></td>";
		if ($row[art]=='DK') $sum=dkdecimal($row[sum])*-1;
		else $sum=dkdecimal($row[sum]);
		print "<td align=right><small>$font$sum<br></small></td>";
		print "</tr>";
	}
	print "<tr><td colspan=11><hr></td></tr>";
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>
</body></html>
