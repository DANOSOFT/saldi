<?php
// ----------debitor/kassespor.php-------------lap 3.5.3-----2015-03-05-----
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
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20141119 PHR Tilføjet summer og bord
// 20150305 PHR Tilføjet status.
// 20150305 PHR Skriver nu bordnavn i stedet for bordnr. Søg $bordnavn & $bordnr

ob_start();
@session_start();
$s_id=session_id();
$title="Kassespor";
$modulnr=4;
$css="../css/standard.css";
$udskriv=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");

$status = if_isset($_GET['status']);
$id = if_isset($_GET['id']);
$fakturadatoer = if_isset($_GET['fakturadatoer']);
$logtime = if_isset($_GET['logtime']);
$afdelinger= if_isset($_GET['afdelinger']);
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$idnumre = if_isset($_GET['idnumre']);
$kontonumre = if_isset($_GET['kontonumre']);
$fakturanumre = if_isset($_GET['fakturanumre']);
$summer = if_isset($_GET['summer']);
$betalinger = if_isset($_GET['betalinger']);
$betalinger2 = if_isset($_GET['betalinger2']);
$modtagelser = if_isset($_GET['modtagelser']);
$modtagelser2 = if_isset($_GET['modtagelser2']);
$kasser =  if_isset($_GET['kasser']);
$borde =  if_isset($_GET['borde']);
$refas =  if_isset($_GET['refas']);
$start = if_isset($_GET['start']);

if ($submit=$_POST['submit']){
	$status = if_isset($_POST['status']);
	$fakturadatoer = if_isset($_POST['fakturadatoer']);
	$logtime = if_isset($_POST['logtime']);
	$afdelinger=if_isset($_POST['afdelinger']);
	$sort = if_isset($_POST['sort']);
	$nysort = if_isset($_POST['nysort']);
	$idnumre = trim(if_isset($_POST['idnumre']));
	$kontonumre = if_isset($_POST['kontonumre']);
	$fakturanumre = if_isset(trim($_POST['fakturanumre']));
	$summer = if_isset($_POST['summer']);
	$betalinger = if_isset(trim($_POST['betalinger']));
	$betalinger2 = if_isset($_POST['betalinger2']);
	$modtagelser = if_isset($_POST['modtagelser']);
	$modtagelser2 = if_isset($_POST['modtagelser2']);
	$kasser =  if_isset($_POST['kasser']);
	$borde =  if_isset($_POST['borde']);
	$refs =  if_isset(trim($_POST['refs']));
	$linjeantal = if_isset($_POST['linjeantal']);

	$cookievalue="$sort;$nysort;$fakturadatoer;$logtime;$afdelinger;$sort;$nysort;$idnumre;$fakturanumre;$summer;$betalinger;$betalinger2;$modtagelser;$modtagelser2;$kasser;$refs;$linjeantal;$borde;$status";
	setcookie("saldi_kassespor", $cookievalue);
} else {
	list ($sort,$nysort,$fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$borde,$status) = explode(";", $_COOKIE['saldi_kassespor']);
	$beskrivelse=str_replace("semikolon",";",$beskrivelse);
	if (isset($_GET['sort'])) $sort = $_GET['sort'];
	if (isset($_GET['nysort'])) $nysort = $_GET['nysort'];
}
ob_end_flush();  //Sender det "bufferede" output afsted...

if (!$fakturadatoer&&!$logtime&&!$afdelinger&&!$sort&&!$nysort&&!$idnumre&&!$fakturanumre&&!$summer&&!$betalinger&&!$betalinger2&&!$modtagelser&&!$modtagelser2&&!$kasser&&!$refs&&!$borde) {
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

if (!$sort) $sort = "tidspkt desc";
elseif ($nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;

$x=0;
$bet_type=array();
$q=db_select("select betalingstype from pos_betalinger group by betalingstype order by betalingstype",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if (!is_numeric($r['betalingstype']) && !in_array($r['betalingstype'],$bet_type)) {
		$bet_type[$x]=$r['betalingstype'];
		$bet_sum[$x]=0;
		$x++;
	}
}
$r = db_fetch_array(db_select("select box7 from grupper where art = 'POS' and kodenr='2'",__FILE__ . " linje " . __LINE__)); 
($r['box7'])?$bordnavn=explode(chr(9),$r['box7']):$bordnavn=NULL; #20141119

print "<table width=100% height=100% border=1 cellspacing=0 cellpadding=0><tbody>";
print "<tr><td height = 25 align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>";
print "<tr>";
print "<td width=10% $top_bund>";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>\n";
	else print "<a href=rapport.php accesskey=L>Luk</a></td>\n";
print "<td width=80% $top_bund>Kassespor</td>\n";
print "<td width=10% $top_bund><br></td>\n";
print "</tr>\n";
print "<tr>";
print "<form name=bonliste action=kassespor.php method=post>";
if (!$linjeantal) $linjeantal=50;
$next=udskriv($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$sort,$start,'',$borde,$status);
if ($start>=$linjeantal) {
	$tmp=$start-$linjeantal;
	print "<td><a href='kassespor.php?sort=$sort&start=$tmp'><img src=../ikoner/left.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>\n";
} else print  "<td></td>\n";
print "<td align=center><span title= 'Angiv maksimale antal linjer, som skal vises pr. side'><input class=\"inputbox\" type=text style=\"text-align:right;width:30px\" name=\"linjeantal\" value=\"$linjeantal\"></td>\n";
$tmp=$start+$linjeantal;
if ($next>0) {
	print "<td align=right><a href='kassespor.php?sort=$sort&start=$tmp'><img src=../ikoner/right.png style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>\n";
} else print  "<td></td>\n";
print "</tr>\n";
print "</tbody></table>";
print " </td></tr><tr><td align=center valign=top>";
print "<table cellpadding=1 cellspacing=1 border=0 valign = top>";

print "<tbody>";
print "<tr>";
print "<td style=\"text-align:center;width:30px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Status</b></td>\n";
print "<td style=\"text-align:center;width:60px\"><b><a href='kassespor.php?nysort=id&sort=$sort&valg=$valg$hreftext'>Id</b></td>\n";
print "<td style=\"text-align:center;width:110px\"><b><a href='kassespor.php?nysort=fakturadate&sort=$sort&valg=$valg$hreftext'>Bondato</a></b></td>\n";
print "<td style=\"text-align:center;width:50px\"><b>Tidspkt.</a></b></td>\n";
#print "<td align=center><b><a href='kassespor.php?nysort=kontonr&sort=$sort&valg=$valg$hreftext'>Konto</b></td>\n";
print "<td style=\"text-align:center;width:110px\"><b><a href='kassespor.php?nysort=fakturanr&sort=$sort&valg=$valg$hreftext'>Bonnr</a></b></td>\n";
print "<td style=\"text-align:center;width:50px\"><b><a href='kassespor.php?nysort=felt_5&sort=$sort&valg=$valg$hreftext'>Kasse</a></b></td>\n";
print "<td style=\"text-align:center;width:50px\"><b><a href='kassespor.php?nysort=nr&sort=$sort&valg=$valg$hreftext'>Bord</a></b></td>\n";
print "<td style=\"text-align:center;width:50px\"><b><a href='kassespor.php?nysort=refs&sort=$sort&valg=$valg$hreftext'>Ref.</a></b></td>\n";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=summer&sort=$sort&valg=$valg$hreftext'>Beløb</a></b></td>\n";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=felt_1&sort=$sort&valg=$valg$hreftext'>Betaling</a></b></td>\n";
print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=felt_2&sort=$sort&valg=$valg$hreftext'>Modtaget</a></b></td>\n";
#print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=felt_3&sort=$sort&valg=$valg$hreftext'>Betaling 2</a></b></td>\n";
#print "<td style=\"text-align:center;width:100px\"><b><a href='kassespor.php?nysort=felt_4&sort=$sort&valg=$valg$hreftext'>Modtaget 2</a></b></td>\n";
print "<td style=\"text-align:center;width:100px\"><b>Retur</a></b></td>\n";
print "</tr>\n";

print "<form name=ordreliste action=kassespor.php method=post>";
print "<input type=hidden name=valg value=\"$valg\">";
print "<input type=hidden name=sort value=\"$sort\">";
#print "<input type=hidden name=nysort value=\"$nysort\">";
print "<input type=hidden name=kontoid value=\"$kontoid\">";
print "<input type=hidden name=start value=\"$start\">";
print "<tr>";
print "<td align=center><span title= 'Vælg 3 eller 4. 3 er ikke ikke bogført, 4 er bogført '>";
print "<select class=\"inputbox\" style=\"text-align:right;width:40px;height:20px;font-size:12px\" name=\"status\">";
if ($status=='') {
	print "<option value=\"\"></option>"; 
	print "<option value=\"3\">3</option>"; 
	print "<option value=\"4\">4</option>";
} elseif ($status==3) {
	print "<option value=\"3\">3</option>"; 
	print "<option value=\"\"></option>"; 
	print "<option value=\"4\">4</option>"; 
} else {
	print "<option value=\"4\">4</option>"; 
	print "<option value=\"\"></option>"; 
	print "<option value=\"3\">3</option>";
}
print "</select></td>\n";
print "<td align=center><span title= 'Angiv et id-nummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px;height:20px;font-size:12px\" name=\"idnumre\" value=\"$idnumre\"></td>\n";
print "<td align=center><span title= 'Angiv en bondato eller angiv to adskilt af kolon (f.eks 010605:300605)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:110px;height:20px;font-size:12px\" name=\"fakturadatoer\" value=\"$fakturadatoer\"></td>\n";
#print "<td align=center><span title= 'Angiv et tidspunkt  (f.eks 17:35)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:20px;font-size:12px\" name=\"logtid\" value=\"$logtid\"></td>\n";
print "<td></td>\n";
print "<td align=center><span title= 'Angiv et bonnummer eller angiv to adskilt af kolon (f.eks 345:350)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:110px;height:20px;font-size:12px\" name=\"fakturanumre\" value=\"$fakturanumre\"></td>\n";
print "<td align=center><span title= 'Angiv et kasse nr. eller angiv to adskilt af kolon (f.eks 3:4)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:20px;font-size:12px\" name=\"kasser\" value=\"$kasser\"</td>\n";
print "<td align=center><span title= 'Vælg et bord'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:50px;height:20px;font-size:12px\" name=\"borde\">\n";
if ($borde) print "<option>$borde</option>\n";
print "<option></option>\n";
for ($x=0;$x<count($bordnavn);$x++) {
	if ($bordnavn[$x] != $borde) print "<option value=\"$bordnavn[$x]\">$bordnavn[$x]</option>\n";
}
print "</select></td>\n";
print "<td align=center><span title= 'Angiv brugernavn p&aring; ekspedient'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:150px;height:20px;font-size:12px\" name=\"refs\" value=\"$refs\"></td>\n";
print "<td align=center><span title= 'Angiv et bel&oslash;b eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:20px;font-size:12px\" name=\"summer\" value=\"$summer\"></td>\n";
print "<td align=center><span title= 'Angiv betalingform'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:22px;font-size:12px\" name=\"betalinger\">";
if ($betalinger) print "<option>$betalinger</option>\n";
print "<option></option>\n";
for ($x=0;$x<count($bet_type);$x++) {
	if ($bet_type[$x] != $betalinger) print "<option>$bet_type[$x]</option>\n";
}
print "</select></td>\n";
print "<td align=center><span title= 'Angiv et modtaget bel&oslash;b for betaling 1 eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:20px;font-size:12px\" name=\"modtagelser\" value=\"$modtagelser\"></td>\n";
#print "<td align=center><span title= 'Angiv betalingform 2'><select class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:22px;font-size:12px\" name=\"betalinger2\">";
#if ($betalinger2) print "<option>$betalinger2</option>\n";
#print "<option></option>\n";
#for ($x=0;$x<count($bet_type);$x++) {
#	if ($bet_type[$x] != $betalinger2) print "<option>$bet_type[$x]</option>\n";
#}
#print "</select></td>\n";
#print "<td align=center><span title= 'Angiv et modtaget bel&oslash;b for betaling 2 eller angiv to adskilt af kolon (f.eks 10000,00:14999,99)'><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:100px;height:20px;font-size:12px\" name=\"modtagelser2\" value=\"$modtagelser2\"></td>\n";
#print "<td><br></td>\n";
print "<td></td><td><input type=submit value=\"OK\" name=\"submit\"></td>\n";
print "</form></tr>\n";
udskriv($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$sort,$start,'skriv',$borde,$status);

print "<tr><td colspan=\"9\" align=\"right\"><b>".dkdecimal($omsaet)."</b></td><td colspan=\"2\" align=\"right\"><b>".dkdecimal($modtaget)."</b></td><td colspan=\"1\" align=\"right\"><b>".dkdecimal($retursum)."</b></td></tr>"; 

for ($z=0;$z<count($bet_type);$z++) {
	print "<tr><td colspan=\"10\" align=\"right\"><b>$bet_type[$z]</b></td><td align=\"right\"><b>".dkdecimal($bet_sum[$z])."</b></td></tr>"; 
}
for ($z=0;$z<count($bet_type);$z++) {
	if (strtolower($bet_type[$z])=='kontant'){
		print "<tr><td colspan=\"10\" align=\"right\"><b>Tilgang kasse</b></td><td align=\"right\"><b>".dkdecimal($bet_sum[$z]-$retursum)."</b></td></tr>"; 
	}
}

####################################################################################

# $fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$sort,$start+50,''
function udskriv($fakturadatoer,$logtime,$afdelinger,$sort,$nysort,$idnumre,$fakturanumre,$summer,$betalinger,$betalinger2,$modtagelser,$modtagelser2,$kasser,$refs,$linjeantal,$sort,$start,$skriv,$borde,$status) {

	global $bgcolor;
	global $bgcolor5;
	global $linjeantal;
	global $regnaar;
	global $bet_sum;
	global $bet_type;
	global $bordnavn;
	global $omsaet;
	global $modtaget;
	global $retursum;

	if ($borde && count($bordnavn)) {
		for ($x=0;$x<count($bordnavn);$x++) {
			if ($bordnavn[$x]==$borde) {
				$bordnr=$x;
			}
		}
	}
	$udvaelg='';
	if ($status) $udvaelg=$udvaelg.udvaelg($status, 'ordrer.status', 'NR');
	if ($idnumre) $udvaelg=$udvaelg.udvaelg($idnumre, 'ordrer.id', 'NR');
	if ($fakturanumre) $udvaelg=$udvaelg.udvaelg($fakturanumre, 'ordrer.fakturanr', 'NR');
	if ($betalinger) $udvaelg=$udvaelg.udvaelg($betalinger, 'ordrer.felt_1', '');
#	if ($betalinger2) $udvaelg=$udvaelg.udvaelg($betalinger2, 'ordrer.felt_3', '');
	if ($fakturadatoer) $udvaelg=$udvaelg.udvaelg($fakturadatoer, 'ordrer.fakturadate', 'DATO');
	if ($modtagelser) $udvaelg=$udvaelg.udvaelg($modtagelser, 'ordrer.felt_2', 'TEXT');
#	if ($modtagelser2) $udvaelg=$udvaelg.udvaelg($modtagelser2, 'ordrer.felt_4', 'TEXT');
	if ($summer) $udvaelg=$udvaelg.udvaelg($summer, 'ordrer.sum+ordrer.moms', 'BELOB');
#	if ($modtagelser) $udvaelg=$udvaelg.udvaelg($modtagelser, 'ordrer.felt_5', 'TEXT');
	if ($kasser) $udvaelg=$udvaelg.udvaelg($kasser, 'ordrer.felt_5','NR');
	if ($borde && ($bordnr || $bordnr=='0')) $udvaelg=$udvaelg.udvaelg($bordnr, 'ordrer.nr','NR');
	if ($refs) $udvaelg=$udvaelg.udvaelg($refs, 'ordrer.ref', '');
	$udvaelg=trim($udvaelg);
	if (substr($udvaelg,0,3)=='and') $udvaelg="where".substr($udvaelg, 3);
	if ($sort=="logdate") $sort = $sort.", logtime";
	if (!$udvaelg) $udvaelg="where";
	else $udvaelg=$udvaelg." and";
	$x=0;
	$qtxt="select * from ordrer $udvaelg (art = 'PO' or art like 'D%') order by $sort";
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ordrestatus[$x]=$r['status'];
		$id[$x]=$r['id'];
		$fakturadato[$x]=dkdato($r['fakturadate']);
		$tidspkt[$x]=substr($r['tidspkt'],-5);
		$fakturanr[$x]=$r['fakturanr'];
		$kasse[$x]=$r['felt_5'];
		$bord[$x]=$r['nr'];
		$ref[$x]=$r['ref'];
		$sum[$x]=$r['sum'];
		$moms[$x]=$r['moms'];
		$dkksum[$x]=dkdecimal($sum[$x]+$moms[$x]);
		$x++;
	}
	for ($x=0;$x<count($id);$x++) {
		$udskriv=1;
		if (($x>=$start)&&($x<$start+$linjeantal) && ($udskriv)){
			$y++;
			if ($skriv) {
				$omsaet+=($sum[$x]+$moms[$x]);
				if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				$y=0;
				$ordre_id=array();
				$q2=db_select("select * from pos_betalinger where ordre_id = '$id[$x]' order by betalingstype");	
				while ($r2=db_fetch_array($q2)) {
					if (!$y) {
						$betalt=0;
					}
					if (!is_numeric($r2['betalingstype'])) {
						$ordre_id[$y]=$r2['ordre_id'];
						$amount[$y]=$r2['amount'];
						$modtaget+=$amount[$y];
						$betalt+=$amount[$y];
						$betalingstype[$y]=$r2['betalingstype'];
						for ($z=0;$z<count($bet_type);$z++) {
							if (strtolower($bet_type[$z])==strtolower($betalingstype[$y])) $bet_sum[$z]+=$amount[$y];
						}
						$y++;
					}
				}
				for ($y=0;$y<count($ordre_id);$y++) {
					print "<tr bgcolor=\"$linjebg\">";
					print "<td>$ordrestatus[$x]</td>";
					print "<td align=right>$id[$x]</span><br></td>\n";
					print "<td align=right>$fakturadato[$x]<br></td>\n";
					print "<td align=right>$tidspkt[$x]<br></td>\n";
					print "<td align=right>$fakturanr[$x]<br></td>\n";
					print "<td align=right>$kasse[$x]<br></td>\n";
					print "<td align=right>".$bordnavn[$bord[$x]]."<br></td>\n";
					print "<td align=right>$ref[$x]<br></td>\n";
					print "<td align=right>$dkksum[$x]<br></td>\n";
					print "<td align=right>$betalingstype[$y]<br></td>\n";
					print "<td align=right>".dkdecimal($amount[$y],2)."<br></td>\n";
					$retur=$betalt-($sum[$x]+$moms[$x]);
					if ($ordre_id[$y]!=$ordre_id[$y+1]) {
					print "<td align=right>".dkdecimal($retur)."<br></td>\n";
						$retursum+=$retur;
					} else print "<td align=right><br></td>\n";
				}
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

