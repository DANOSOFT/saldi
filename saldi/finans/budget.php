<?php
// -------------finans/budget.php ---------------------- 3.5.6 -- 2015-06-22 --
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------------
//
// 20130210 Break ændret til break 1
// 20140909 - Dubletter i regnskab saldi_660  
// 20140923 - Definerer $MD som array da den bruges både som en og 2 dimentionelt. Følge af 20140909
// 20141007 - Definerer $id & $amount som arrays da de bruges både som en og 2 dimentionelt. Følge af 20140909
// 20141219 - rettelse til 20141007 - $id & $amount skal ikke devineres som arrays ved "udflyld med sidste års tal".
// 20150622 CA  Budgetdata kan hentes som CSV-fil. 


@session_start();
$s_id=session_id();
$css="../css/standard.css";
		
$modulnr=4;	
$title="Budget";
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/finansfunk.php");
	
$udfyld=if_isset($_POST['udfyld']);
$procent=if_isset($_POST['procent']);
$plusminus=if_isset($_POST['plusminus']);

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$beskrivelse[0]=$row['beskrivelse'];
$startmaaned=$row['box1'];
$startaar=$row['box2'];
$slutmaaned=$row['box3'];
$slutaar=$row['box4'];
$slutdato=31;
$filnavn="../temp/$db/budget_".$startaar.$startmaaned."-".$slutaar.$slutmaaned."_".$bruger_id.".csv"; # 20150622 del 1 start
$fp=fopen($filnavn,"w"); # 20150622 del 1 slut
		
if (!$udfyld && isset($_POST['gem'])) {
#cho "Gemmer nu<br>";	
	$kontoantal=$_POST['kontoantal'];
	$maanedantal=$_POST['maanedantal'];
	$kontonr=$_POST['kontonr'];
	$amount=$_POST['amount'];
	$id=$_POST['id'];
	for ($x=1;$x<=$kontoantal;$x++) {
		for ($z=1;$z<=$maanedantal;$z++) {
			$b_id=$id[$x][$z]*1;
			$tmp=substr($amount[$x][$z],-4);
			if(strpos($tmp,",")) $amount[$x][$z]=usdecimal($amount[$x][$z]);
			$tal=round($amount[$x][$z],0);
			if ($b_id) {
				db_modify("update budget set amount='$tal' where id='$b_id'",__FILE__ . " linje " . __LINE__);
			} elseif ($tal) {
				db_modify("insert into budget(regnaar,kontonr,md,amount) values ($regnaar,'$kontonr[$x]','$z','$tal')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	db_modify("delete from budget where amount = 0",__FILE__ . " linje " . __LINE__);
}

$x=0;
$md=array(); #20140923
$q=db_select("select id,amount,md,kontonr from budget where regnaar='$regnaar' order by kontonr,md,id",__FILE__ . " linje " . __LINE__); #20140909
while ($r=db_fetch_array($q)) {
	$id[$x]=$r['id'];
	$md[$x]=$r['md'];
	$kontonr[$x]=$r['kontonr'];
	if ($x && $md[$x]==$md[$x-1] && $kontonr[$x]==$kontonr[$x-1]) db_modify("delete from budget where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
	$x++;
}

print "<div align=\"center\">";

print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "	<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "		<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "			<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\">";
if ($popup) print "<a href=../includes/luk.php accesskey=L>Luk</a></td>";
else print "<a href=\"../index/menu.php\" accesskey=\"L\">Luk</a></td>";
print "			<td width=\"80%\" $top_bund>$title $beskrivelse[0]</td> ";
print "			<td width=\"10%\" $top_bund><a href=\"regnskab.php\" accesskey=\"R\">Regnskab</a></td> ";
print "			</tbody></table> ";
print "	</td></tr> ";
		
		
while (!checkdate($slutmaaned, $slutdato, $slutaar)) {
#echo "$slutdato, $slutmaaned, $slutaar	";				
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}
#echo "slutdato $slutdato<br>";		
$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

$md=array(); #20140923
$tmpaar=$startaar;
$md[1][0]=$startmaaned;
$md[1][1]=$regnstart;
$md[1][2]=0;
$x=1;

while ($md[$x][1]<$regnslut) {
	$x++;
	$md[$x][0]=$md[$x-1][0]+1;
	if ($md[$x][0]>12) {
		$tmpaar++;
		$md[$x][0]=1;
	}
	if ($md[$x][0]<10) $tmp="0".$md[$x][0];
	else $tmp=$md[$x][0];
	$md[$x][1]=$tmpaar. "-" .$tmp."-01"; 
	$md[$x][2]=0;
}

if ($udfyld) {

	$tmpaar=$startaar-1;
	$prestart = $startaar-1 . "-" . $startmaaned . "-" . '01';
	$preslut = $slutaar-1 . "-" . $slutmaaned . "-" . $slutdato;
	$md[1][0]=$startmaaned;
	$md[1][1]=$prestart;
	$md[1][2]=0;
	$x=1;
	while ($md[$x][1]<$preslut) {
		$x++;
		$md[$x][0]=$md[$x-1][0]+1;
		if ($md[$x][0]>12) {
			$tmpaar++;
			$md[$x][0]=1;
		}
		if ($md[$x][0]<10) $tmp="0".$md[$x][0];
		else $tmp=$md[$x][0];
		$md[$x][1]=$tmpaar. "-" .$tmp."-01"; 
		$md[$x][2]=0;
	}
}
#echo $md[1][0];
#echo $md[1][1];
#echo $md[1][2];
#echo "<br>";
$maanedantal=$x-1;

$x=0;
$query = db_select("select * from kontoplan where regnskabsaar='$regnaar' and lukket != 'on' order by kontonr",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	$x++;
	$konto_id[$x]=$row['id'];
	$kontonr[$x]=trim($row['kontonr']);
	$kontotype[$x]=$row['kontotype'];
	$beskrivelse[$x]=$row['beskrivelse'];
	$fra_kto[$x]=$row['fra_kto'];
		
	if ($udfyld && ($row['kontotype']=='D' || $row['kontotype']=='S')) {
		$q2 = db_select("select * from transaktioner where transdate>='$prestart' and transdate<='$preslut' and kontonr='$kontonr[$x]' order by transdate",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
		 	for ($y=1; $y<=$maanedantal; $y++) {
				if (($md[$y][1]<=$r2['transdate'])&&($md[$y+1][1]>$r2['transdate'])) {
			 		$md[$y][2]=$md[$y][2]+afrund($r2['debet'],0)-afrund($r2['kredit'],0);
					$amount[$x][$y]=$amount[$x][$y]+afrund($r2['debet'],0)-afrund($r2['kredit'],0);
				}
			}
		}
	}
}
$kontoantal=$x;
if (!$udfyld) {
	$id=array(); #20141007
	$amount=array(); #20141007
}
for ($x=1; $x<=$kontoantal; $x++) {
	$q=db_select("select id,amount,md from budget where regnaar='$regnaar' and kontonr='$kontonr[$x]' order by md",__FILE__ . " linje " . __LINE__);
	$b=0;
	while ($r=db_fetch_array($q)) {
		$b=$r['md'];
		$amount[$x][$b]=0;
		$id[$x][$b]=$r['id'];
		if (!$udfyld) $amount[$x][$b]=$r['amount'];
	}
}
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1']*1;
$startaar=$row['box2']*1;
$slutmaaned=$row['box3']*1;
$slutaar=$row['box4']*1;
$slutdato=31;
$regnskabsaar=$row['beskrivelse'];

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28)break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

print " <tr><td valign=\"top\"> ";
print "<table width=100% cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\"> ";
print "<tbody>";
print "<form name=udfyld action=budget.php?regnaar=$regnaar&returside=$returside method=post>";
print "<tr><td><br></td><td colspan=15>Udfyld med sidste &aring;rs tal ";
print "<select class=\"inputbox\" NAME=\"plusminus\">";
if ($plusminus) print "<option value=\"$plusminus\">$plusminus</option>";
if ($plusminus != "+") print "<option value=\"+\">+</option>";
if ($plusminus != "-") print "<option value=\"-\">-</option>";
print "</select>";
$procent=$procent*1;
print "<input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=2 name=\"procent\" value=\"$procent\">% ";
print "<input type=submit name=udfyld value=OK>";
print "</td></tr>";
print "</form>";
print "<tr><td><b> Kontonr.</b></td> ";
print "<td><b> Kontonavn</b></td> ";
##print "<td title=\"Ved regnskabs&aring;rets begyndelse. De fleste overf&oslash;rt fra regnskabet &aring;ret f&oslash;r.\" align=right><b> Primo</a></b></td> ";
#for ($z=1; $z<=$maanedantal; $z++) {
#	print "<td width=20 title=\"$z. regnskabsm&aring;ned\"><b> MD_$z<b><br></td>";
#}
$budget_csvdata="\"Kontonr\";\"Kontonavn\";"; # 20150622 del 2 start
$budget_csvdata.=periodeoverskrifter($maanedantal, $startaar, $startmaaned, 1, "regnskabsmaaned", $regnskabsaar);
print "<td align=right><b> I alt</a></b></td> "; 
$budget_csvdata.="\"I alt\"\n"; # 20150622 del 2 slut
print "</tr>";

$y='';
print "<form name=budget action=budget.php?regnaar=$regnaar&returside=$returside method=post>";
for ($x=1; $x<=$kontoantal; $x++){
	$budget_csvdata.="\"$kontonr[$x]\";\"$beskrivelse[$x]\";"; #20150622
	print "<input type=\"hidden\" name=\"kontonr[$x]\" value=\"$kontonr[$x]\">";
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=$linjebg>";
	if ($kontotype[$x]=='H') {
		print "<td><b>$kontonr[$x]<br></b></td>";
		print "<td colspan=15><b>$beskrivelse[$x]<br></b></td>";
	} elseif ($kontotype[$x]!='Z') {
#		if ($kontotype[$x]!='Z') {$link="<a href=kontospec.php?kontonr=$kontonr[$x]&month=";}
#		else {$link='';}
		print "<td>$kontonr[$x]<br></td>";
		print "<td>$beskrivelse[$x]<br></td>";
		$ultimo[$x]=0;
#		print "<td align=right>$tal<br></td>";
		for ($z=1; $z<=$maanedantal; $z++) {
			if ($kontotype[$x]!='Z') {
				if ($udfyld) {
				$tmp=afrund($amount[$x][$z]*$procent/100,0);
				if ($plusminus == "+") $amount[$x][$z]=afrund($amount[$x][$z]+$tmp,0);
				else $amount[$x][$z]=afrund($amount[$x][$z]-$tmp,0);
				}
				$tal=$amount[$x][$z];
				$ultimo[$x]+=$tal;
				if (!$tal) $tal="";
				print "<td title=\"".$id[$x][$z]."\"><input type=\"text\" size=\"6\" style=\"text-align:right\" name=\"amount[$x][$z]\" value=\"$tal\"></td>";
				$tmp=$id[$x][$z];
				print "<input type = \"hidden\" name=\"id[$x][$z]\" value=\"$tmp\">"; 
			}	else print "<td align=right>$tal<br></td>";
			$budget_csvdata.="\"$tal\";"; # 20150622
		}
		print "<td align=right>$ultimo[$x]<br></td>";
		$budget_csvdata.="\"$ultimo[$x]\";"; # 20150622
		$y='';
		print "</tr>";
	} else {
		print "<td>$kontonr[$x]<br></td>";
		print "<td>$beskrivelse[$x]<br></td>";
		$ultimo[$x]=0;
		for ($z=1;$z<=$maanedantal; $z++) {
			$tal=0;
			for ($y=1;$y<$x;$y++) {
				if ($kontonr[$y]>=$fra_kto[$x]) {
				 	$tal+=$amount[$y][$z];
				}
			}
			print "<td align=right>$tal<br></td>";
			$budget_csvdata.="\"$tal\";"; # 20150622
			$ultimo[$x]+=$tal;
		}
		print "<td align=right>$ultimo[$x]<br></td></tr>";
		$budget_csvdata.="\"$ultimo[$x]\";"; # 20150622
	}
	if ($kontotype[$x]=='H') {$linjebg='#ffffff'; $color='#ffffff';}
	$budget_csvdata.="\n"; # 20150622
}

if ($fp) { # 20150622 del 3 start
	fwrite ($fp, "$budget_csvdata");
}
fclose($fp);

print "<input type='hidden' name='kontoantal' value='$kontoantal'>\n";
print "<input type='hidden' name='maanedantal' value='$maanedantal'>\n";
print "<tr>\n";
print "<td><input type='submit' name='gem' value='Gem' accesskey='g'></td>\n";
print "<td>Hent budget som datafil ved at &oslash;jreklikke p&aring; <a href='".$filnavn."'>dette link</a> og v&aelig;lg \"Gem link som ...\"."; 
print "</tr>\n";
print "</form>\n"; # 20150622 del 3 slut
####################################################################################################
?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
