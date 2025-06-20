<?php
// ------------debitor/jobliste.php---lap 3.2.2------2011-07-03----------
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
ob_start();
@session_start();
$s_id=session_id();
$title="Jobliste";
$css="../css/standard.css";

$modulnr=5;
$dk_dg=NULL; $vis_projekt=NULL;
$firmanavn=NULL; $firmanavn_ant=NULL; $hurtigfakt=NULL; $konto_id=NULL; $linjebg=NULL; $skriv=NULL; $totalkost=NULL; $understreg=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");

$felt01= if_isset($_GET['felt01']);
$felt02= if_isset($_GET['felt02']);
$felt03= if_isset($_GET['felt03']);
$felt04= if_isset($_GET['felt04']);
$felt05= if_isset($_GET['felt05']);
$felt06= if_isset($_GET['felt06']);
$felt07= if_isset($_GET['felt07']);
$felt08= if_isset($_GET['felt08']);
$valg= if_isset($_GET['valg']);
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$konto_id=if_isset($_GET['konto_id'])*1;
$ordre_id=if_isset($_GET['ordre_id'])*1;
$returside=if_isset($_GET['returside']);
$luk=if_isset($_GET['luk']);

if($luk) {
	if ($r=db_fetch_array(db_select("select * from navigator where bruger_id='$bruger_id' and session_id='$s_id' and side='jobliste.php'",__FILE__ . " linje " . __LINE__))) {
		db_modify("delete from navigator where bruger_id='$bruger_id' and session_id='$s_id' and side='jobliste.php'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$r[returside]?konto_id=$r[konto_id]&ordre_id=$r[ordre_id]\">";
	} else print "<meta http-equiv=\"refresh\" content=\"0;URL=debitor.php\">";
	exit;
} elseif ($returside) db_modify("insert into navigator(bruger_id,session_id,side,returside,ordre_id,konto_id) values ('$bruger_id','$s_id','jobliste.php','$returside','$ordre_id','$konto_id')",__FILE__ . " linje " . __LINE__);
	
$tidspkt=date("U");
 
if ($submit=if_isset($_POST['submit'])){
	$felt01= if_isset($_POST['felt01']);
	$felt02= if_isset($_POST['felt02']);
	$felt03= if_isset($_POST['felt03']);
	$felt04= if_isset($_POST['felt04']);
	$felt05= if_isset($_POST['felt05']);
	$felt06= if_isset($_POST['felt06']);
	$felt07= if_isset($_POST['felt07']);
	$felt08= if_isset($_POST['felt08']);
	$valg=if_isset($_POST['valg']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$firma=if_isset($_POST['firma']);
	$kontoid=if_isset($_POST['kontoid']);
	$firmanavn_ant=if_isset($_POST['firmanavn_antal']);
}

if ($nysort=='kolonne01') $nysort='id';
if ($nysort=='kolonne02') $nysort='firmanavn';
if ($nysort=='kolonne03') $nysort='postnr';
if ($nysort=='kolonne04') $nysort='felt_1';
if ($nysort=='kolonne05') $nysort='felt_2';
if ($nysort=='kolonne06') $nysort='felt_4';
if ($nysort=='kolonne07') $nysort='felt_4';
if ($nysort=='kolonne08') $nysort='felt_4';

ob_end_flush();	//Sender det "bufferede" output afsted...
	
if (!$sort) $sort = "firmanavn";
elseif ($nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;

#$parameter="sort=$sort&ordre_id=$ordre_id&returside=$returside&valg=$valg$hreftext";
$parameter=NULL;

$hreftext=NULL;
#$hreftext="&jobnumre=$jobnumre&kontonumre=$kontonumre&fakturanumre=$fakturanumre&jobdatoer=$jobdatoer&lev_datoer=$lev_datoer&fakturadatoer=$fakturadatoer&genfaktdatoer=$genfaktdatoer&summer=$summer&ref=$ref[0]&kontoid=$kontoid";
 
print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
#if ($popup) print "<td width=10% $top_bund><a href=../includes/luk.php?returside=$returside accesskey=L>".findtekst(30,$sprog_id)."</a></td>";
#else 
print "<td width=10% $top_bund><a href=\"jobliste.php?luk=luk\" accesskey=\"L\">".findtekst(30,$sprog_id)."</a></td>";
print "<td width=30% $top_bund align=center><br></td>";
print "<td width=10% $top_bund align=center><a href=debitor.php>".findtekst(34,$sprog_id)."</a></td>";
print "<td width=10% $knap_ind align=center>".findtekst(38,$sprog_id)."</td>";
print "<td width=30% $top_bund align=center><br></td>";
if ($popup) print "<td width=10% $top_bund onClick=\"javascript:job=window.open('jobkort.php?returside=jobliste.php&konto_id=$konto_id&ordre_id=$ordre_id','job','scrollbars=1,resizable=1');job.focus();\"><a accesskey=N href=jobliste.php?$parameter>".findtekst(39,$sprog_id)."</a></td>";
else print "<td width=10% $top_bund><a href=jobkort.php?returside=jobliste.php&konto_id=$konto_id&ordre_id=$ordre_id accesskey=N>".findtekst(39,$sprog_id)."</a></td>";
print "</td></tr>\n";
#print "<tr><td></td><td align=center><table border=1	cellspacing=0 cellpadding=0><tbody>";
#print "<td width = 20%$top_bund align=center><a href=jobliste.php?valg=tilbud accesskey=T>Tilbud</a></td>";
#print "<td width = 20% bgcolor=$bgcolor5 align=center> jobr</td>";
#print "<td width = 20% bgcolor=$bgcolor5 align=center> Faktura</td>";
#print "</tbody></table></td><td></td</tr>\n";

print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top>";

print "<tbody>";
print "<tr>";
print "<td><b><a href='jobliste.php?nysort=kolonne01&$parameter'>".findtekst(6,$sprog_id)."</b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne02&$parameter'>".findtekst(35,$sprog_id)."</b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne03&$parameter'>".findtekst(36,$sprog_id)."</b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne04&$parameter'>".findtekst(37,$sprog_id)."</a></b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne05&$parameter'>".findtekst(7,$sprog_id)."</a></b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne06&$parameter'>".findtekst(9,$sprog_id)."</a></b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne07&$parameter'>".findtekst(13,$sprog_id)."</a></b></td>";
print "<td><b><a href='jobliste.php?nysort=kolonne08&$parameter'>".findtekst(27,$sprog_id)."</a></b></td>";
print "</a></b></td>";

print "</tr>\n";

#################################### Sogefelter ##########################################

print "<form name=\"jobliste\" action=\"jobliste.php?$parameter\" method=\"post\">";
print "<input type=\"hidden\" name=\"valg\" value=\"$valg\">";
print "<input type=\"hidden\" name=\"sort\" value=\"$sort\">";
print "<input type=\"hidden\" name=\"nysort\" value=\"$nysort\">";
print "<tr>";
print "<td><span title= '".findtekst(39,$sprog_id)."'><input type=text size=5 name=felt01 value=\"".$felt01."\"></td>";
print "<td><span title= '".findtekst(40,$sprog_id)."'><input type=text size=5 name=felt02 value=\"".$felt02."\"></td>";
print "<td><span title= '".findtekst(41,$sprog_id)."'><input type=text size=10 name=felt03 value=\"".$felt03."\"></td>";
print "<td><span title= '".findtekst(42,$sprog_id)."'><input type=text size=10 name=felt04 value=\"".$felt04."\"></td>";
print "<td><span title= '".findtekst(41,$sprog_id)."'><input type=text size=10 name=felt05 value=\"".$felt05."\"></td>";
print "<td><span title= '".findtekst(41,$sprog_id)."'><input type=text size=10 name=felt06 value=\"".$felt06."\"></td>";
print "<td><span title= '".findtekst(41,$sprog_id)."'><input type=text size=10 name=felt07 value=\"".$felt07."\"></td>";
print "<td><span title= '".findtekst(41,$sprog_id)."'><input type=text size=10 name=felt08 value=\"".$felt08."\"></td>";

$x=0;
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr>\n";

####################################################################################

$udvaelg='';
if ($felt01) {
	$udvaelg=$udvaelg.udvaelg($felt01, 'id', 'NR');
}
if ($felt02) {
	$udvaelg=$udvaelg.=udvaelg($felt02, 'firmanavn', 'TEKST');
}
if ($felt03) {
	$udvaelg=$udvaelg.=udvaelg($felt03, 'postnr', 'NR');
}
if ($felt04) {
	$udvaelg=$udvaelg.udvaelg($felt04, 'tlf', 'TEKST');
}
if ($felt05) {
	$udvaelg=$udvaelg.udvaelg($felt05, 'felt_2', 'TEKST');
}
if ($felt06) {
	$udvaelg=$udvaelg.udvaelg($felt06, 'felt_4', 'TEKST');
}
if ($felt07) {
	$udvaelg=$udvaelg.udvaelg($felt07, 'felt_8', 'TEKST');
}
if ($felt08) {
	$udvaelg=$udvaelg.udvaelg($felt08, 'felt_1', 'NR');
}

$ialt=0;
if ($konto_id) $udvaelg.="and konto_id=$konto_id ";
$q = db_select("select * from jobkort where id > 0 $udvaelg order by $sort",__FILE__ . " linje " . __LINE__);
while ($r =db_fetch_array($q)) {
	$ialt++;
	$job="job".$r['id'];
	$firmanavn=stripslashes($r['firmanavn']);
	$tlf=stripslashes($r['tlf']);
	$postnr=stripslashes($r['postnr']);
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

	
	
	if (($tidspkt-($r['tidspkt'])>3600)||($r[hvem]==$brugernavn)) {
		if ($popup) {
			$javascript="onClick=\"javascript:$job=window.open('jobkort.php?tjek=$r[id]&id=$r[id]&returside=jobliste.php','$job','scrollbars=1,resizable=1');$job.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" ";
			$understreg='<span style="text-decoration: underline;">';
			$linjetext="";
			$slut="</span>";
		} else {
			$javascript="";
			$understreg="";
			$linjetext="<a href=jobkort.php?tjek=$r[id]&id=$r[id]&returside=jobliste.php>";
			$slut="</a>";
		} 
	}
	else {
		$javascript="onClick=\"javascript:$job.focus();\"";
		$understreg='';
		$linjetext="<span title= 'job er l&aring;st af $r[hvem]'>";
			$slut="</span>";
	}
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=\"$linjebg\">";
#	if ($r['art']=='DK'){print "<td $javascript> (KN)&nbsp;$linjetext $understreg $r[id]</span><br></td>";}
#	else {print "<td $javascript> $linjetext $understreg $r[id]</span><br></td>";}
	print "<td $javascript> $linjetext $understreg $r[id]$slut<br></td>";
	print "<td>$firmanavn<br></td>";
	print "<td>$postnr<br></td>";
	print "<td>$tlf<br></td>";
	print "<td>$felt_2<br></td>";
	print "<td>$felt_4<br></td>";
	print "<td>$felt_8<br></td>";
	print "<td>$felt_1<br></td>";
	print "</tr>\n";
}
if (!$ialt && $konto_id) print "<Body onLoad=\"javascript:job=window.open('jobkort.php?returside=jobliste.php&konto_id=$konto_id&ordre_id=$ordre_id,'job','scrollbars=1,resizable=1');job.focus();\">";
$cols=9;
print "<tr><td colspan=$cols><hr></td></tr>\n";
print "<tr><td colspan=$cols><hr></td></tr>\n";

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
