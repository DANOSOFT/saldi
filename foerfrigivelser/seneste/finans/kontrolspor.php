<?php
// ----------finans/kontrolspor.php-------------lap 1.9.4-----2008-04-16-----
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
ob_start();
@session_start();
$s_id=session_id();
$title="Kontrolspor";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/dkdecimal.php");
include("../includes/udvaelg.php");

print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>Kontrolspor</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";

$id = $_GET['id'];
$kontonr = $_GET['kontonr'];
$bilag = $_GET['bilag'];
$transdate = $_GET['transdate'];
$logdate = $_GET['logdate'];
$logtime = $_GET['logtime'];
$debet = $_GET['debet'];
$kredit = $_GET['kredit'];
$kladde_id = $_GET['kladde_id'];
$projekt_id=$_GET['projekt_id'];
$afd=$_GET['afd'];
$beskrivelse=$_GET['beskrivelse'];
$faktura=$_GET['faktura'];
$sort = $_GET['sort'];
$nysort = $_GET['nysort'];
$idnumre = $_GET['idnumre'];
$kontonumre = $_GET['kontonumre'];
$fakturanumre = $_GET['fakturanumre'];
$bilagsnumre = $_GET['bilagsnumre'];
$debetbelob = $_GET['debetbelob'];
$kreditbelob = $_GET['kreditbelob'];
$transdatoer = $_GET['transdatoer'];
$logdatoer = $_GET['logdatoer'];
$logtid = $_GET['logtid'];
$kladdenumre = $_GET['kladdenumre'];
$projeknumre = $_GET['projeknumre'];
$beskrivelse = $_GET['beskrivelse'];
$start = $_GET['start'];


 if ($submit=$_POST['submit']){
	$linjeantal = $_POST['linjeantal'];
	$idnumre = $_POST['idnumre'];
	$kontonumre = $_POST['kontonumre'];
	$fakturanumre = $_POST['fakturanumre'];
	$bilagsnumre = $_POST['bilagsnumre'];
	$debetbelob = $_POST['debetbelob'];
	$kreditbelob = $_POST['kreditbelob'];
	$transdatoer = $_POST['transdatoer'];
	$logdatoer = $_POST['logdatoer'];
	$logtid = $_POST['logtid'];
	$kladdenumre = $_POST['kladdenumre'];
	$projeknumre = $_POST['projeknumre'];
	$beskrivelse = $_POST['beskrivelse'];
	$sort = $_POST['sort'];
	$nysort = $_POST['nysort'];
	$cookievalue="$idnumre;$kontonumre;$fakturanumre;$bilagsnumre;$debetbelob;$kreditbelob;$transdatoer;$logdatoer;$logtid;$kladdenumre;$projeknumre;$beskrivelse;$linjeantal";
	setcookie("saldi_kontrolspor", $cookievalue);
} else {
	list ($idnumre, $kontonumre, $fakturanumre, $bilagsnumre, $debetbelob, $kreditbelob, $transdatoer, $logdatoer, $logtid, $kladdenumre, $projeknumre, $beskrivelse, $linjeantal) = split(";", $_COOKIE['saldi_kontrolspor']);
	$beskrivelse=str_replace("semikolon",";",$beskrivelse);
}
ob_end_flush();  //Sender det "bufferede" output afsted...

# $valg="idnumre=$idnumre&kontonumre=$kontonumre&fakturanumre=$fakturanumre&bilagsnumre$bilagsnumre&debetbelob=$debetbelob&kreditbelob=$kreditbelob&transdatoer=$transdatoer&logdatoer=$logdatoer&logtid=$logtid&kladdenumre=$kladdenumre&projeknumre=$projeknumre&$beskrivelse = $_GET['beskrivelse'];


$tidspkt=date("U");

$modulnr=2;


if (!$sort) {$sort = "id";}
elseif ($nysort==$sort){$sort=$sort." desc";}
elseif ($nysort) {$sort=$nysort;}

print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
print "<tr>";
print "<td width=10% $top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
print "<td width=80% $top_bund>$font<small>Kontrolspor</small></td>";
print "<td width=10% $top_bund>$font<small><br></small></td>";
print "</tr>\n";
print "<tr>";

print "<form name=transaktionsliste action=kontrolspor.php method=post>";
if (!$linjeantal) $linjeantal=50;
$next=udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre, $kontonumre, $transdatoer, $logdatoer, $debetbelob, $kreditbelob, $logtid, $beskrivelse, $sort, $start+50, '');
if ($start>=$linjeantal) {
	$tmp=$start-$linjeantal;
	print "<td><a href='kontrolspor.php?sort=$sort&start=$tmp'><img src=../ikoner/left.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "<td align=center><span title= 'Angiv maks. antal linjer som skal vises pr side'><input type=text size=3 style=\"text-align:right\" name=linjeantal value=$linjeantal></td>";
$tmp=$start+$linjeantal;
if ($next>0) {
	print "<td align=right><a href='kontrolspor.php?sort=$sort&start=$tmp'><img src=../ikoner/right.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>"; 
print "</tr>\n";

print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top>";

print "<tbody>";
print "<tr>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>ID</b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=transdate&sort=$sort&valg=$valg$hreftext'>Dato</a></b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=logdate&sort=$sort&valg=$valg$hreftext'>Logdato</a></b></small></td>";
print "<td align=center><small><b>$font Tidspkt</b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=kladde_id&sort=$sort&valg=$valg$hreftext'>Kladde</a></b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=bilag&sort=$sort&valg=$valg$hreftext'>Bilag</a></b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>Konto</b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=faktura&sort=$sort&valg=$valg$hreftext'>Fakturanr</a></b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=debet&sort=$sort&valg=$valg$hreftext'>Debet</a></b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=kredit&sort=$sort&valg=$valg$hreftext'>Kredit</a></b></small></td>";
print "<td align=center><small><b>$font<a href='kontrolspor.php?nysort=beskrivelse&sort=$sort&valg=$valg$hreftext'>S&oslash;getekst</a></b></small></td>";
print "</tr>\n";

print "<form name=ordreliste action=kontrolspor.php method=post>";
print "<input type=hidden name=valg value=$valg>";
print "<input type=hidden name=sort value=$sort>";
#print "<input type=hidden name=nysort value=$nysort>";
print "<input type=hidden name=kontoid value=$kontoid>";
print "<input type=hidden name=start value=$start>";
print "<tr>";
print "<td align=right><span title= 'Angiv et id nummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text style=\"text-align:right\" size=3 name=idnumre value=$idnumre></td>";
print "<td align=right><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text style=\"text-align:right\" size=8 name=transdatoer value=$transdatoer></td>";
print "<td align=right><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input type=text style=\"text-align:right\" size=8 name=logdatoer value=$logdatoer></td>";
print "<td align=right><span title= 'Angiv et tidspunkt  (f.eks 17:35)'><input type=text style=\"text-align:right\" size=3 name=logtid value=$logtid></td>";
print "<td align=right><span title= 'Angiv et kassekladdenummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text size=3 style=\"text-align:right\" name=kladdenumre value=$kladdenumre></td>";
print "<td align=right><span title= 'Angiv et bilagsnummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text style=\"text-align:right\" size=5 name=bilagsnumre value=$bilagsnumre></td>";
print "<td align=right><span title= 'Angiv et kontonummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text style=\"text-align:right\" size=3 name=kontonumre value=$kontonumre></td>";
print "<td align=right><span title= 'Angiv et fakturanummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text style=\"text-align:right\" size=5 name=fakturanumre value=$fakturanumre></td>";
print "<td align=right><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input type=text style=\"text-align:right\" size=8 name=debetbelob value=$debetbelob></td>";
print "<td align=right><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input type=text style=\"text-align:right\" size=8 name=kreditbelob value=$kreditbelob></td>";
print "<td><span title= 'Angiv en s&oslash;getekst. Der kan anvendes * f&oslash;r og efter teksten'><input type=text size=35 name=beskrivelse value='$beskrivelse'></td>";
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr>\n";
udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre, $kontonumre, $transdatoer, $logdatoer, $debetbelob, $kreditbelob, $logtid, $beskrivelse, $sort, $start, 'skriv');
####################################################################################


function udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre, $kontonumre, $transdatoer, $logdatoer, $debetbelob, $kreditbelob, $logtid, $beskrivelse, $sort, $start, $skriv) {

	global $font;
	global $bgcolor;
	global $bgcolor5;
	global $linjeantal;

	$udvaelg='';
	if ($idnumre)		$udvaelg=$udvaelg.udvaelg($idnumre, 'id', 'NR');
	if ($bilagsnumre)	$udvaelg=$udvaelg.udvaelg($bilagsnumre, 'bilag', 'NR');
	if ($kladdenumre)	$udvaelg=$udvaelg.udvaelg($kladdenumre, 'kladde_id', 'NR');
	if ($fakturanumre)	$udvaelg=$udvaelg.udvaelg($fakturanumre, 'faktura', 'NR');
	if ($kontonumre)	$udvaelg=$udvaelg.udvaelg($kontonumre, 'kontonr', 'NR');
	if ($transdatoer)	$udvaelg=$udvaelg.udvaelg($transdatoer, 'transdate', 'DATO');
	if ($logdatoer)		$udvaelg=$udvaelg.udvaelg($logdatoer, 'logdate', 'DATO');
	if ($debetbelob) 	$udvaelg=$udvaelg.udvaelg($debetbelob, 'debet', 'BELOB');
	if ($kreditbelob) 	$udvaelg=$udvaelg.udvaelg($kreditbelob, 'kredit', 'BELOB');
	if ($logtid) 		$udvaelg=$udvaelg.udvaelg($logtid, 'logtime', 'TID');

	$udvaelg=trim($udvaelg);
	if (substr($udvaelg,0,3)=='and') $udvaelg="where".substr($udvaelg, 3);
	if ($sort=="logdate") $sort = $sort.", logtime";
	$beskrivelse=trim(strtolower($beskrivelse));
	if (substr($beskrivelse,0,1)=='*'){
		$beskrivelse=substr($beskrivelse,1);
		$startstjerne=1;
	}
	if (substr($beskrivelse,-1,1)=='*') {
		$beskrivelse=substr($beskrivelse,0,strlen($beskrivelse)-1);
		$slutstjerne=1;
	}
	$b_strlen=strlen($beskrivelse);

	$x=0;
	$query = db_select("select * from transaktioner $udvaelg order by $sort");
	while ($row =db_fetch_array($query)) {
		if (($beskrivelse)&&($row[beskrivelse])){
			$udskriv=0;
			if ($startstjerne){
				if ($slutstjerne) {
					if (strpos(strtolower($row[beskrivelse]), $beskrivelse)) $udskriv=1;
				} elseif (substr(strtolower($row[beskrivelse]),-$b_strlen,$b_strlen)==$beskrivelse) $udskriv=1;
			} elseif ($slutstjerne) {
				if (substr(strtolower($row[beskrivelse]),0,$b_strlen)==$beskrivelse) $udskriv=1;
			} elseif (strtolower($row[beskrivelse]) == $beskrivelse) $udskriv=1;
		} else $udskriv=1;
		if ($udskriv) $x++;
		if (($x>=$start)&&($x<$start+$linjeantal) && ($udskriv)){
			$y++;
			if ($skriv) {
				if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				print "<tr bgcolor=\"$linjebg\">";
				print "<td align=right><small>$font $row[id]</span><br></small></td>";
				$dato=dkdato($row[transdate]);
				print "<td align=right><small>$font $dato<br></small></td>";
				$dato=dkdato($row[logdate]);
				print "<td align=right><small>$font $dato<br></small></td>";
				print "<td align=right><small>$font". substr($row[logtime],0,5)."<br></small></td>";
				print "<td align=right><small>$font $row[kladde_id]<br></small></td>";
				print "<td align=right><small>$font $row[bilag]<br></small></td>";
				print "<td align=right><small>$font $row[kontonr]<br></small></td>";
				print "<td align=right><small>$font $row[faktura]<br></small></td>";
				if ($row[debet]) print "<td align=right><small>$font ".dkdecimal($row[debet])."<br></small></td>";
				else print "<td></td>";
					if ($row[kredit]) print "<td align=right><small>$font ".dkdecimal($row[kredit])."<br></small></td>";
				else print "<td></td>";
					print "<td><small>$font &nbsp; $row[beskrivelse]<br></small></td>";
				print "</tr>\n";
			}
		}
	}
	return ($y);
} #endfunction udskriv()
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
