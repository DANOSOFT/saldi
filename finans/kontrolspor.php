<?php
// ----------finans/kontrolspor.php-------------lap 3.2.5-----2011-11-27-----
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2011 DANOSOFT ApS
// ----------------------------------------------------------------------
ob_start();
@session_start();
$s_id=session_id();
$title="Kontrolspor";
$modulnr=4;
$css="../css/standard.css";

print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");

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
$projektnumre = $_GET['projektnumre'];
$beskrivelse = $_GET['beskrivelse'];
$start = $_GET['start'];
$csv =  if_isset($_GET['csv']);

if ($submit=$_POST['submit']){
	$linjeantal = trim($_POST['linjeantal']);
	$idnumre = trim($_POST['idnumre']);
	$kontonumre = trim($_POST['kontonumre']);
	$fakturanumre = trim($_POST['fakturanumre']);
	$bilagsnumre = trim($_POST['bilagsnumre']);
	$debetbelob = trim($_POST['debetbelob']);
	$kreditbelob = trim($_POST['kreditbelob']);
	$transdatoer = trim($_POST['transdatoer']);
	$logdatoer = trim($_POST['logdatoer']);
	$logtid = trim($_POST['logtid']);
	$kladdenumre = trim($_POST['kladdenumre']);
	$projektnumre = trim($_POST['projektnumre']);
	$beskrivelse = trim($_POST['beskrivelse']);
	$sort = $_POST['sort'];
	$nysort = $_POST['nysort'];

	$cookievalue="$idnumre;$kontonumre;$fakturanumre;$bilagsnumre;$debetbelob;$kreditbelob;$transdatoer;$logdatoer;$logtid;$kladdenumre;$projeknumre;$beskrivelse;$linjeantal";
	setcookie("saldi_kontrolspor", $cookievalue);
} else {
	list ($idnumre, $kontonumre, $fakturanumre, $bilagsnumre, $debetbelob, $kreditbelob, $transdatoer, $logdatoer, $logtid, $kladdenumre, $projeknumre, $beskrivelse, $linjeantal) = explode(";", $_COOKIE['saldi_kontrolspor']);
	$beskrivelse=str_replace("semikolon",";",$beskrivelse);
}
ob_end_flush();  //Sender det "bufferede" output afsted...

if (!$idnumre&&!$kontonumre&&!$fakturanumre&&!$bilagsnumre&&!$debetbelob&&!$kreditbelob&&!$transdatoer&&!$logdatoer&&!$logtid&&!$kladdenumre&&!$projeknumre&&!$beskrivelse) {
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$transdatoer="01".$r['box1'].substr($r['box2'],-2).":31".$r['box3'].substr($r['box4'],-2);
}

$r=db_fetch_array(db_select("select * from grupper where art = 'PRJ' and kodenr!='0'",__FILE__ . " linje " . __LINE__));
$vis_projekt=$r['id'];

if ($logtid) {
	list ($h,$m)=explode(":",$logtid);
	$h=$h*1;
	$m=$m*1;
	if (strlen($h)>2) $h=substr($h,-2);
	if (strlen($m)>2) $m=substr($m,-2);
	$logtid="$h:$m";
}

# $valg="idnumre=$idnumre&kontonumre=$kontonumre&fakturanumre=$fakturanumre&bilagsnumre$bilagsnumre&debetbelob=$debetbelob&kreditbelob=$kreditbelob&transdatoer=$transdatoer&logdatoer=$logdatoer&logtid=$logtid&kladdenumre=$kladdenumre&projeknumre=$projeknumre&$beskrivelse = $_GET['beskrivelse'];


$tidspkt=date("U");

$modulnr=2;


if (!$sort) {$sort = "id desc";}
elseif ($nysort==$sort){$sort=$sort." desc";}
elseif ($nysort) {$sort=$nysort;}

print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
print "<tr>";
print "<td width=10% $top_bund>";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
	else print "<a href=rapport.php accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund>Kontrolspor</td>";
print "<td width=10% $top_bund><a href=kontrolspor.php?sort=$sort&valg=$valg$hreftext&csv=1' title=\"".findtekst(505,$sprog_id)."\">CSV</a></td>";
print "</tr>\n";
print "<tr>";

print "<form name=transaktionsliste action=kontrolspor.php method=post>";
if (!$linjeantal) $linjeantal=50;
# echo "A next $next start $start | linjeantal $linjeantal<br>"; 
$next=udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre, $kontonumre, $transdatoer, $logdatoer, $debetbelob, $kreditbelob, $logtid, $beskrivelse, $sort, $start+50,'',$projektnumre);
# echo "B next $next start $start | linjeantal $linjeantal<br>"; 
if ($start>=$linjeantal) {
	$tmp=$start-$linjeantal;
	print "<td><a href='kontrolspor.php?sort=$sort&start=$tmp'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "<td align=center><span title= 'Angiv maksimale antal linjer, som skal vises pr. side'><input class=\"inputbox\" type=text style=\"text-align:right;width:30px\" name=\"linjeantal\" value=\"$linjeantal\"></td>";
$tmp=$start+$linjeantal;
if ($next>0) {
	print "<td align=right><a href='kontrolspor.php?sort=$sort&start=$tmp'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "</tr>\n";

print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top>";

print "<tbody>";
print "<tr>";
print "<td align=center><b><a href='kontrolspor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Id</b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=transdate&sort=$sort&valg=$valg$hreftext'>Dato</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=logdate&sort=$sort&valg=$valg$hreftext'>Log. dato</a></b></td>";
print "<td align=center><b>Tidspkt.</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=kladde_id&sort=$sort&valg=$valg$hreftext'>Kladde</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=bilag&sort=$sort&valg=$valg$hreftext'>Bilag</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>Konto</b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=faktura&sort=$sort&valg=$valg$hreftext'>Fakturanr</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=debet&sort=$sort&valg=$valg$hreftext'>Debet</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=kredit&sort=$sort&valg=$valg$hreftext'>Kredit</a></b></td>";
if($vis_projekt) print "<td align=center><b><a href='kontrolspor.php?nysort=projekt&sort=$sort&valg=$valg$hreftext'>Projekt</a></b></td>";
print "<td align=center><b><a href='kontrolspor.php?nysort=beskrivelse&sort=$sort&valg=$valg$hreftext'>S&oslash;getekst</a></b></td>";
print "</tr>\n";

print "<form name=ordreliste action=kontrolspor.php method=post>";
print "<input type=hidden name=valg value=\"$valg\">";
print "<input type=hidden name=sort value=\"$sort\">";
#print "<input type=hidden name=nysort value=\"$nysort\">";
print "<input type=hidden name=kontoid value=\"$kontoid\">";
print "<input type=hidden name=start value=\"$start\">";
print "<tr>";
print "<td align=right><span title= 'Angiv et id-nummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"idnumre\" value=\"$idnumre\"></td>";
print "<td align=right><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"transdatoer\" value=\"$transdatoer\"></td>";
print "<td align=right><span title= 'Angiv en dato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"logdatoer\" value=\"$logdatoer\"></td>";
print "<td align=right><span title= 'Angiv et tidspunkt  (f.eks 17:35)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"logtid\" value=\"$logtid\"></td>";
print "<td align=right><span title= 'Angiv et kassekladdenummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"kladdenumre\" value=\"$kladdenumre\"></td>";
print "<td align=right><span title= 'Angiv et bilagsnummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"bilagsnumre\" value=\"$bilagsnumre\"></td>";
print "<td align=right><span title= 'Angiv et kontonummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"kontonumre\" value=\"$kontonumre\"></td>";
print "<td align=right><span title= 'Angiv et fakturanummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px\" name=\"fakturanumre\" value=\"$fakturanumre\"></td>";
print "<td align=right><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"debetbelob\" value=\"$debetbelob\"></td>";
print "<td align=right><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:80px\" name=\"kreditbelob\" value=\"$kreditbelob\"></td>";
if ($vis_projekt) print "<td align=right><span title= 'Angiv et projektnummer eller angiv to adskilt af kolon (f.eks 5:7)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:200px\" name=\"projektnumre\" value=\"$projektnumre\"></td>";
print "<td><span title= 'Angiv en s&oslash;getekst. Der kan anvendes * f&oslash;r og efter teksten'><input class=\"inputbox\" type=\"text\"  style=\"text-align:left;width:200px\" size=35 name=beskrivelse value=\"$beskrivelse\"></td>";
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr>\n";
udskriv($idnumre,$bilagsnumre,$kladdenumre,$fakturanumre,$kontonumre,$transdatoer,$logdatoer,$debetbelob,$kreditbelob,$logtid,$beskrivelse,$sort,$start,'skriv',$projektnumre);
####################################################################################
function udskriv($idnumre, $bilagsnumre, $kladdenumre, $fakturanumre,$kontonumre,$transdatoer,$logdatoer,$debetbelob,$kreditbelob,$logtid,$beskrivelse,$sort,$start,$skriv,$projektnumre) {

	global $bgcolor;
	global $bgcolor5;
	global $linjeantal;
	global $regnaar;
	global $vis_projekt;
	global $rettigheder;
	global $csv;

	if ($sort=='id') $sort='transaktioner.id';
	$ret_projekt=substr($rettigheder,1,1);

	if ($csv) {
		$fp=fopen("../temp/$db/kontrolspor.csv","w");
		fwrite($fp,"Id".chr(9)."Dato".chr(9)."Logdato".chr(9)."Logtid".chr(9)."Kladde id".chr(9)."Bilag".chr(9)."Kontonr".chr(9)."Kontonavn".chr(9)."Faktura".chr(9)."Debet".chr(9)."Kredit".chr(9)."Projekt".chr(9)."Beskrivelse\n");
	}
	$udvaelg='';
	if ($idnumre)		$udvaelg=$udvaelg.udvaelg($idnumre, 'transaktioner.id', 'NR');
	if ($bilagsnumre)	$udvaelg=$udvaelg.udvaelg($bilagsnumre, 'transaktioner.bilag', 'NR');
	if ($kladdenumre)	$udvaelg=$udvaelg.udvaelg($kladdenumre, 'transaktioner.kladde_id', 'NR');
	if ($fakturanumre)	$udvaelg=$udvaelg.udvaelg($fakturanumre, 'transaktioner.faktura', 'TEXT');
	if ($kontonumre)	$udvaelg=$udvaelg.udvaelg($kontonumre, 'transaktioner.kontonr', 'NR');
	if ($transdatoer)	$udvaelg=$udvaelg.udvaelg($transdatoer, 'transaktioner.transdate', 'DATO');
	if ($logdatoer)		$udvaelg=$udvaelg.udvaelg($logdatoer, 'transaktioner.logdate', 'DATO');
	if ($debetbelob) 	$udvaelg=$udvaelg.udvaelg($debetbelob, 'transaktioner.debet', 'BELOB');
	if ($kreditbelob) 	$udvaelg=$udvaelg.udvaelg($kreditbelob, 'transaktioner.kredit', 'BELOB');
	if ($projektnumre) 	$udvaelg=$udvaelg.udvaelg($projektnumre, 'transaktioner.projekt', '');
	if ($logtid) 		$udvaelg=$udvaelg.udvaelg($logtid, 'transaktioner.logtime', 'TID');

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
	if (!$udvaelg) $udvaelg="where";
	else $udvaelg=$udvaelg." and";

	$x=0;
# echo "select transaktioner.*, kontoplan.beskrivelse as kontonavn from transaktioner, kontoplan $udvaelg kontoplan.regnskabsaar='$regnaar' and kontoplan.kontonr = transaktioner.kontonr order by $sort<br>";
	$query = db_select("select transaktioner.*, kontoplan.beskrivelse as kontonavn from transaktioner, kontoplan $udvaelg kontoplan.regnskabsaar='$regnaar' and kontoplan.kontonr = transaktioner.kontonr order by $sort",__FILE__ . " linje " . __LINE__);
	while ($row =db_fetch_array($query)) {
		if (($beskrivelse)&&($row['beskrivelse'])){
			$udskriv=0;
			if ($startstjerne){
				if ($slutstjerne) {
					if (strpos(strtolower($row['beskrivelse']), $beskrivelse)) $udskriv=1;
				} elseif (substr(strtolower($row['beskrivelse']),-$b_strlen,$b_strlen)==$beskrivelse) $udskriv=1;
			} elseif ($slutstjerne) {
				if (substr(strtolower($row['beskrivelse']),0,$b_strlen)==$beskrivelse) $udskriv=1;
			} elseif (strtolower($row['beskrivelse']) == $beskrivelse) $udskriv=1;
		} else $udskriv=1;
		if ($udskriv) $x++;
# echo "(($x>=$start)&&($x<$start+$linjeantal) && ($udskriv))<br>";
		if ((($x>=$start)&&($x<$start+$linjeantal) && ($udskriv)) || $csv){
				$y++;
			if ($csv || $skriv) {
# echo "Y1 $y<br>";
				$transdato=dkdato($row['transdate']);
				$logdato=dkdato($row['logdate']);
				$debetsum=afrund($debetsum+$row['debet'],2);
				$kreditsum=afrund($kreditsum+$row['kredit'],2);
				if ($skriv && !$csv) {
					if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
					else {$linjebg=$bgcolor5; $color='#000000';}
					print "<tr bgcolor=\"$linjebg\">";
					print "<td align=right> $row[id]</span><br></td>";
					print "<td align=right> $transdato<br></td>";
					print "<td align=right> $logdato<br></td>";
					print "<td align=right>". substr($row['logtime'],0,5)."<br></td>";
					print "<td align=right> $row[kladde_id]<br></td>";
					print "<td align=right> $row[bilag]<br></td>";
					print "<td align=right><span title='$row[kontonavn]'>$row[kontonr]<br></span></td>";
					print "<td align=right> $row[faktura]<br></td>";
					if ($row[debet]) print "<td align=right> ".dkdecimal($row['debet'])."<br></td>";
					else print "<td>&nbsp;</td>";
					if ($row[kredit]) print "<td align=right> ".dkdecimal($row['kredit'])."<br></td>";
					else print "<td>&nbsp;</td>";
					if ($vis_projekt) {
						($row['projekt'] && $ret_projekt)?$title="Projektnummeret kan rettes ved at klikke her":$title=NULL;
						($ret_projekt)?$tmp="<a href=\"../includes/ret_transaktion.php?id=$row[id]&felt=projekt\">$row[projekt]</a>":$tmp=$row['projekt'];
						print "<td align=right title=\"$title\">$tmp<br></td>";
					}
					print "<td> &nbsp; $row[beskrivelse]<br></td>";
					print "</tr>\n";
				}
				if ($csv) {
					fwrite($fp,$row['id'].chr(9).$transdato.chr(9).$logdato.chr(9).substr($row['logtime'],0,5).chr(9).$row['kladde_id'].chr(9).$row['bilag'].chr(9).$row['kontonr'].chr(9).utf8_decode(stripslashes($row['kontonavn'])).chr(9).$row['faktura'].chr(9).dkdecimal($row['debet']).chr(9).dkdecimal($row['kredit']).chr(9).$row['projekt'].chr(9).utf8_decode(stripslashes($row['beskrivelse']))."\n");
				}
			}
		}
	}
	if ($csv){ fclose($fp);
		print "<BODY onLoad=\"JavaScript:window.open('../temp/$db/kontrolspor.csv' ,'' ,'$jsvars');\">\n";
	}
	if (!$csv && ($debetsum || $kreditsum)) {
		($vis_projekt)?$colspan=12:$colspan=11;
		print "<tr><td colspan=\"$colspan\"><hr></td></tr>";
		print "<td colspan=8>Kontrolsum<br></td><td align=right>".dkdecimal($debetsum)."<br></td><td align=right>".dkdecimal($kreditsum)."<br></td><td><br></td></tr>";
	}
#	print "<tr><td colspan=11><hr></td></tr>";
# echo "Y2 $y<br>";

	return ($y);
} #endfunction udskriv()
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
