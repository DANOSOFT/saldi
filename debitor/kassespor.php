<?php
// ----------debitor/kassespor.php-------------lap 3.1.8-----2011-05-11-----
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------
ob_start();
@session_start();
$s_id=session_id();
$title="Kassespor";
$modulnr=4;
$css="../css/standard.css";
$udsrkiv=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");

$id = if_isset($_GET['id']);
$fakturadatoer = if_isset($_GET['fakturadatoer']);
$logtime = if_isset($_GET['logtime']);
$afdelinger= if_isset($_GET['afdelinger']);
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$idnumre = if_isset($_GET['idnumre']);
$kontonumre = if_isset($_GET['kontonumre']);
$fakturanumre = if_isset($_GET['fakturanumre']);
$sum = if_isset($_GET['sum']);
$betalinger = if_isset($_GET['betalinger']);
$betalinger2 = if_isset($_GET['betalinger2']);
$modtagelser = if_isset($_GET['modtagelser']);
$modtagelser2 = if_isset($_GET['modtagelser2']);
$kasser =  if_isset($_GET['kasser']);
$ref =  if_isset($_GET['ref']);
$start = if_isset($_GET['start']);

 if ($submit=$_POST['submit']){
	$fakturadatoer = if_isset($_POST['fakturadatoer']);
	$logtime = if_isset($_POST['logtime']);
	$afdelinger=if_isset($_POST['afdelinger']);
#	$sort = if_isset($_POST['sort']);
#	$nysort = if_isset($_POST['nysort']);
	$idnumre = if_isset($_POST['idnumre']);
	$kontonumre = if_isset($_POST['kontonumre']);
	$fakturanumre = if_isset(trim($_POST['fakturanumre']));
	$sum = if_isset($_POST['sum']);
	$betalinger = if_isset(trim($_POST['betalinger']));
	$betalinger2 = if_isset($_POST['betalinger2']);
	$modtagelser = if_isset($_POST['modtagelser']);
	$modtagelser2 = if_isset($_POST['modtagelser2']);
	$kasser =  if_isset($_POST['kasser']);
	$ref =  if_isset(trim($_POST['ref']));
	$linjeantal = if_isset($_POST['linjeantal']);

	$cookievalue="$fakturadatoer;$logtime;$afdelinger;$sort;$nysort;$idnumre;$fakturanumre;$sum;$betalinger;$betalinger2;$modtagelser;$modtagelser2;$kasser;$ref;$linjeantal";
	setcookie("saldi_kassespor", $cookievalue);
} else {
	list ($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$sum,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$ref,$linjeantal) = explode(";", $_COOKIE['saldi_kassespor']);
	$beskrivelse=str_replace("semikolon",";",$beskrivelse);
}
ob_end_flush();  //Sender det "bufferede" output afsted...

if (!$fakturadatoer&&!$logtime&&!$afdelinger&&!$sort&&!$nysort&&!$idnumre&&!$fakturanumre&&!$sum&&!$betalinger&&!$betalinger2&&!$modtagelser&&!$modtagelser2&&!$kasser&&!$ref) {
	$r=db_fetch_array(db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__));
	$fakturadatoer="01".$r['box1'].substr($r['box2'],-2).":31".$r['box3'].substr($r['box4'],-2);
}

if ($logtid) {
	list ($h,$m)=explode(":",$logtid);
	$h=$h*1;
	$m=$m*1;
	if (strlen($h)>2) $h=substr($h,-2);
	if (strlen($m)>2) $m=substr($m,-2);
	$logtid="$h:$m";
}
$tidspkt=date("U");

$modulnr=2;

if (!$sort) {$sort = "id desc";}
elseif ($nysort==$sort){$sort=$sort." desc";}
elseif ($nysort) {$sort=$nysort;}

$x=0;
$q=db_select("select distinct(lower(felt_1)) as felt_1 from ordrer where art = 'PO' and felt_1 != '' order by felt_1",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	$bet_type[$x]=$r['felt_1'];
}
$bet_antal=$x;

print "<table width=100% height=100% border=1 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
print "<tr>";
print "<td width=10% $top_bund>";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
	else print "<a href=rapport.php accesskey=L>Luk</a></td>";
print "<td width=80% $top_bund>Kassespor</td>";
print "<td width=10% $top_bund><br></td>";
print "</tr>\n";
print "<tr>";

print "<form name=bonliste action=kassespor.php method=post>";
if (!$linjeantal) $linjeantal=50;
$next=udskriv($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$sum,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$ref,$linjeantal,$sort,$start,'');
if ($start>=$linjeantal) {
	$tmp=$start-$linjeantal;
	print "<td><a href='kassespor.php?sort=$sort&start=$tmp'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "<td align=center><span title= 'Angiv maksimale antal linjer, som skal vises pr. side'><input class=\"inputbox\" type=text style=\"text-align:right;width:30px\" name=\"linjeantal\" value=\"$linjeantal\"></td>";
$tmp=$start+$linjeantal;
if ($next>0) {
	print "<td align=right><a href='kassespor.php?sort=$sort&start=$tmp'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
} else print  "<td></td>";
print "</tr>\n";

print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 width=100% valign = top>";

print "<tbody>";
print "<tr>";

print "<td style=\"text-align:center;width:60px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Id</b></td>";
print "<td style=\"text-align:center;width:110px\"><b><a href='kassespor.php?nysort=fakturadate&sort=$sort&valg=$valg$hreftext'>Bondato</a></b></td>";
print "<td style=\"text-align:center;width:50px\"><b>Tidspkt.</a></b></td>";
#print "<td align=center><b><a href='kassespor.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>Konto</b></td>";
print "<td style=\"text-align:center;width:110px\"><b><a href='kassespor.php?nysort=faktura&sort=$sort&valg=$valg$hreftext'>Bonnr</a></b></td>";
print "<td style=\"text-align:center;width:50px\"><b><a href='kassespor.php?nysort=kasse&sort=$sort&valg=$valg$hreftext'>Kasse</a></b></td>";
print "<td style=\"text-align:center;width:50px\"><b><a href='kassespor.php?nysort=ref&sort=$sort&valg=$valg$hreftext'>Ref.</a></b></td>";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=belob&sort=$sort&valg=$valg$hreftext'>Beløb</a></b></td>";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=betaling&sort=$sort&valg=$valg$hreftext'>Betaling 1</a></b></td>";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=modtaget&sort=$sort&valg=$valg$hreftext'>Modtaget</a></b></td>";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=betaling2&sort=$sort&valg=$valg$hreftext'>Betaling 2</a></b></td>";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=modtaget2&sort=$sort&valg=$valg$hreftext'>Modtaget 2</a></b></td>";
print "<td style=\"text-align:center;width:100px\"><b>Retur</a></b></td>";
print "</tr>\n";

print "<form name=ordreliste action=kassespor.php method=post>";
print "<input type=hidden name=valg value=\"$valg\">";
print "<input type=hidden name=sort value=\"$sort\">";
#print "<input type=hidden name=nysort value=\"$nysort\">";
print "<input type=hidden name=kontoid value=\"$kontoid\">";
print "<input type=hidden name=start value=\"$start\">";
print "<tr>";
print "<td align=center><span title= 'Angiv et id-nummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px;height:20px;font-size:12px\" name=\"idnumre\" value=\"$idnumre\"></td>";
print "<td align=center><span title= 'Angiv en bondato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:110px;height:20px;font-size:12px\" name=\"fakturadatoer\" value=\"$fakturadatoer\"></td>";
print "<td align=center><span title= 'Angiv et tidspunkt  (f.eks 17:35)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:20px;font-size:12px\" name=\"logtid\" value=\"$logtid\"></td>";
print "<td align=center><span title= 'Angiv et bonnummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:110px;height:20px;font-size:12px\" name=\"fakturanumre\" value=\"$fakturanumre\"></td>";
print "<td align=center><span title= 'Angiv et kasse nr. eller angiv to adskilt af kolon (f.eks 3:4)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:20px;font-size:12px\" name=\"kasser\" value=\"$kasser\"></td>";
print "<td align=center><span title= 'Angiv brugernavn p&aring; ekspedient'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:20px;font-size:12px\" name=\"ref\" value=\"$ref\"></td>";
print "<td align=center><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:20px;font-size:12px\" name=\"sum\" value=\"$sum\"></td>";
print "<td align=center><span title= 'Angiv betalingform 1'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:22px;font-size:12px\" name=\"betalinger\">";
if ($betalinger) print "<option>$betalinger</option>";
print "<option></option>";
for ($x=1;$x<=$bet_antal;$x++) {
	if ($bet_type[$x] != $betalinger) print "<option>$bet_type[$x]</option>";
}
print "</select></td>";
print "<td align=center><span title= 'Angiv et modtaget bel&oslash;b for betaling 1 eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:20px;font-size:12px\" name=\"modtaget\" value=\"$modtaget\"></td>";
print "<td align=center><span title= 'Angiv betalingform 2'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:22px;font-size:12px\" name=\"betalinger2\">";
if ($betalinger2) print "<option>$betalinger2</option>";
print "<option></option>";
for ($x=1;$x<=$bet_antal;$x++) {
	if ($bet_type[$x] != $betalinger2) print "<option>$bet_type[$x]</option>";
}
print "</select></td>";
print "<td align=center><span title= 'Angiv et modtaget bel&oslash;b for betaling 2 eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:20px;font-size:12px\" name=\"modtaget2\" value=\"$modtaget2\"></td>";
print "<td><br></td>";
print "<td><input type=submit value=\"OK\" name=\"submit\"></td>";
print "</form></tr>\n";
udskriv($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$sum,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$ref,$linjeantal,$sort,$start,'skriv');
####################################################################################

#                 $fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$sum,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$ref,$linjeantal,$sort,$start+50,''
function udskriv($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$sum,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$ref,$linjeantal,$sort,$start,$skriv) {

	global $bgcolor;
	global $bgcolor5;
	global $linjeantal;
	global $regnaar;

#	if ($sort=='id') $sort='.id';

	$udvaelg='';
	if ($idnumre) $udvaelg=$udvaelg.udvaelg($idnumre, 'ordrer.id', 'NR');
	if ($fakturanumre) $udvaelg=$udvaelg.udvaelg($fakturanumre, 'ordrer.fakturanr', 'NR');
	if ($betalinger) $udvaelg=$udvaelg.udvaelg($betalinger, 'ordrer.felt_1', '');
	if ($betalinger2) $udvaelg=$udvaelg.udvaelg($betalinger2, 'ordrer.felt_3', '');
	if ($fakturadatoer) $udvaelg=$udvaelg.udvaelg($fakturadatoer, 'ordrer.fakturadate', 'DATO');
	if ($modtagelser) $udvaelg=$udvaelg.udvaelg($modtagelser, 'ordrer.felt_2', 'TEXT');
	if ($modtagelser2) $udvaelg=$udvaelg.udvaelg($modtagelser2, 'ordrer.felt_4', 'TEXT');
	if ($belob) $udvaelg=$udvaelg.udvaelg($belob, 'ordrer.sum', 'BELOB');
	if ($modtagelser) $udvaelg=$udvaelg.udvaelg($modtagelser, 'ordrer.felt_5', 'TEXT');
	if ($kasser) $udvaelg=$udvaelg.udvaelg($kasser, 'ordrer.felt_5','NR');
	if ($ref) $udvaelg=$udvaelg.udvaelg($ref, 'ordrer.ref', '');

	$udvaelg=trim($udvaelg);
	if (substr($udvaelg,0,3)=='and') $udvaelg="where".substr($udvaelg, 3);
	if ($sort=="logdate") $sort = $sort.", logtime";
/*
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
*/
	if (!$udvaelg) $udvaelg="where";
	else $udvaelg=$udvaelg." and";
	$x=0;
	$q = db_select("select * from ordrer $udvaelg status >= 3 and art = 'PO' order by $sort",__FILE__ . " linje " . __LINE__);
	while ($r =db_fetch_array($q)) {
/*
		if (($beskrivelse)&&($r[beskrivelse])){
			$udskriv=0;
			if ($startstjerne){
				if ($slutstjerne) {
					if (strpos(strtolower($r[beskrivelse]), $beskrivelse)) $udskriv=1;
				} elseif (substr(strtolower($r[beskrivelse]),-$b_strlen,$b_strlen)==$beskrivelse) $udskriv=1;
			} elseif ($slutstjerne) {
				if (substr(strtolower($r[beskrivelse]),0,$b_strlen)==$beskrivelse) $udskriv=1;
			} elseif (strtolower($r[beskrivelse]) == $beskrivelse) $udskriv=1;
		} else $udskriv=1;
*/
		$udskriv=1;
#$fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$sum,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$ref,$linjeantal,$sort

		if ($udskriv) $x++;
		if (($x>=$start)&&($x<$start+$linjeantal) && ($udskriv)){
			$retur=0;
			$y++;
			if ($skriv) {
				if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				print "<tr bgcolor=\"$linjebg\">";
				print "<td align=right>$r[id]</span><br></td>";
				$fakturadato=dkdato($r['fakturadate']);
				print "<td align=right>$fakturadato<br></td>";
				print "<td align=right>".substr($r['tidspkt'],-5)."<br></td>";
				print "<td align=right>$r[fakturanr]<br></td>";
				print "<td align=right>".$r['felt_5']."<br></td>";
				print "<td align=right>".$r['ref']."<br></td>";
				print "<td align=right>".dkdecimal($r['sum']+$r['moms'])."<br></td>";
				print "<td align=right>$r[felt_1]<br></td>";
				print "<td align=right>".dkdecimal($r['felt_2'],2)."<br></td>";
				if ($r['felt_1']=='Konto') print "<td><br></td><td><br></td>";
				else {
					print "<td align=right>$r[felt_3]<br></td>";
					if ($r['felt_3']) print "<td align=right>".dkdecimal($r['felt_4'],2)."<br></td>";
					else print "<td><br></td>";
					$retur=(($r['felt_2']+$r['felt_4'])-($r['sum']+$r['moms']));
				}
				print "<td align=right>".dkdecimal($retur)."<br></td>";
#				print "<td align=right>H $r[kontonr]<br></span></td>";
#				if ($r[debet]) print "<td align=right> ".dkdecimal($r['debet'])."<br></td>";
#				else print "<td>&nbsp;</td>";
#					if ($r[kredit]) print "<td align=right> ".dkdecimal($r['kredit'])."<br></td>";
#					else print "<td>&nbsp;</td>";
#					print "<td> &nbsp; $r[beskrivelse]<br></td>";
				print "</tr>\n";
			}
		}
	}
#	if ($debetsum || $kreditsum) {
#		print "<tr><td colspan=11><hr></td></tr>";
#		print "<td colspan=8>Kontrolsum<br></td><td align=right>".dkdecimal($debetsum)."<br></td><td align=right>".dkdecimal($kreditsum)."<br></td><td><br></td></tr>";
#	}
#	print "<tr><td colspan=11><hr></td></tr>";
	return ($y);
} #endfunction udskriv()
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>

