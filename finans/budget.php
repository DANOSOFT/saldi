<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------finans/budget.php ----------- patch 4.0.7 --- 2023.03.04 ---
//                           LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------------
//
// 20130210 -Break ændret til break 1
// 20140909 - Dubletter i regnskab saldi_660  
// 20140923 - Definerer $MD som array da den bruges både som en og 2 dimentionelt. Følge af 20140909
// 20141007 - Definerer $id & $amount som arrays da de bruges både som en og 2 dimentionelt. Følge af 20140909
// 20141219 - rettelse til 20141007 - $id & $amount skal ikke devineres som arrays ved "udflyld med sidste års tal".
// 20150622 CA  Budgetdata kan hentes som CSV-fil. 
// 21081121 PHR Oprydning udefinerede variabler.
// 20181217 msc Design tilrettelse
// 20181221 MSC Rettet topmenu design til
// 20190216 PHR	Ændret csv til ISO-8859-1.
// 20210312 LOE Translated the former Danish text here to English and Applied findtekst function to this and the menu items
// 20220926 MSC Removed a 2 number in title for budget
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

@session_start();
$s_id=session_id();
$css="../css/standard.css";
		
$modulnr=4;	
$title="Budget";

$linjebg=$returside=NULL;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/finansfunk.php");
include("../includes/topline_settings.php");
	
$udfyld=if_isset($_POST['udfyld']);
$procent=if_isset($_POST['procent']);
$plusminus=if_isset($_POST['plusminus']);

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($query);
$beskrivelse[0]=$r['beskrivelse'];
$startmaaned=$r['box1'];
$startaar=$r['box2'];
$slutmaaned=$r['box3'];
$slutaar=$r['box4'];
$slutdato=31;
$filnavn="../temp/$db/budget_".$startaar.$startmaaned."-".$slutaar.$slutmaaned."_".$bruger_id.".csv"; # 20150622 del 1 start
$fp=fopen($filnavn,"w"); # 20150622 del 1 slut
		
if (!$udfyld && isset($_POST['gem'])) {
	$kontoantal=$_POST['kontoantal'];
	$maanedantal=$_POST['maanedantal'];
	$kontonr=$_POST['kontonr'];
	$amount=$_POST['amount'];
	$id=$_POST['id'];
	for ($x=1;$x<=$kontoantal;$x++) {
		for ($z=1;$z<=$maanedantal;$z++) {
			if (!isset($amount[$x][$z])) $amount[$x][$z]=0;
			if (!isset($id[$x][$z])) $id[$x][$z]=0;
			$b_id=$id[$x][$z]*1;
			if ($z==1 && substr($amount[$x][$z],-1)=='*') {
				$setall[$x]=str_replace('*','',$amount[$x][$z]);
			} elseif($z==1) $setall[$x]=NULL;
			if ($setall[$x] || $setall[$x]=='0') $amount[$x][$z]=$setall[$x];
			$tmp=substr($amount[$x][$z],-4);
			if(strpos($tmp,",")) $amount[$x][$z]=usdecimal($amount[$x][$z]);
			$tal=round(if_isset($amount[$x][$z],0),0);
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
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title $beskrivelse[0]</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print  "<table class='dataTable' width='100%' border='0' cellspacing='0'>";
} elseif ($menu=='S') {
	print "<div align=\"center\">";

	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	print "<td width='10%'>";
	print "<a href=\"../index/menu.php\" accesskey=\"L\">
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst('30|Tilbage',$sprog_id)."</button></a></td>";

	print "<td style='$topStyle' align='center'>$title $beskrivelse[0]</td>";

	print "<td width='13%'><a href='regnskab.php' accesskey='R'>
		   <button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor = 'pointer'\">"
		   .findtekst('115|Regnskab',$sprog_id)."</button></a></td>";

	print "</tbody></table>";
	print "</td></tr>";
} else {
	print "<div align=\"center\">";
	print "<table width=100% border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=100% align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\">";
	if ($popup) print "<a href=../includes/luk.php accesskey=L>".findtekst(30,$sprog_id)."</a></td>"; # 20210312
	else print "<a href=\"../index/menu.php\" accesskey=\"L\">".findtekst(30,$sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund>$title $beskrivelse[0]</td> ";
	print "<td width=\"10%\" $top_bund><a href=\"regnskab.php\" accesskey=\"R\">".findtekst(115,$sprog_id)."</a></td> ";
	print "</tbody></table> ";
	print "</td></tr> ";
}

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
$qtxt="select * from kontoplan where regnskabsaar='$regnaar' and lukket != 'on' order by kontonr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$x++;
	$konto_id[$x]=$r['id'];
	$kontonr[$x]=trim($r['kontonr']);
	$kontotype[$x]=$r['kontotype'];
	$beskrivelse[$x]=$r['beskrivelse'];
	$fra_kto[$x]=$r['fra_kto'];
		
	if ($udfyld && ($kontotype[$x]=='D' || $kontotype[$x]=='S')) {
		$qtxt="select * from transaktioner where transdate>='$prestart' and transdate<='$preslut' and kontonr='$kontonr[$x]' order by transdate";
		$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
		 	for ($y=1; $y<=$maanedantal; $y++) {
				if (!isset($amount[$x][$y])) $amount[$x][$y]=0;
				if (($md[$y][1]<=$r2['transdate'])&&($md[$y+1][1]>$r2['transdate'])) {
			 		$md[$y][2]+=afrund($r2['debet'],0)-afrund($r2['kredit'],0);
					$amount[$x][$y]+=afrund($r2['debet'],0)-afrund($r2['kredit'],0);
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
		$id[$x][$b]=$r['id'];
		if (!$udfyld) $amount[$x][$b]=$r['amount'];
	}
}

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($query);
$startmaaned=$r['box1']*1;
$startaar=$r['box2']*1;
$slutmaaned=$r['box3']*1;
$slutaar=$r['box4']*1;
$slutdato=31;
$regnskabsaar=$r['beskrivelse'];

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28)break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

print " <tr><td valign=\"top\"> ";
if ($menu=='T') {
} else {
	print "<table width=100% cellpadding=\"0\" cellspacing=\"1px\" border=\"0\" valign = \"top\" align='center'> ";
}
print "<tbody>";
print "<form name=udfyld action=budget.php?regnaar=$regnaar&returside=$returside method=post>";
print "<tr><td><br></td><td colspan=15>".findtekst(806, $sprog_id)." ";
print "<select class=\"inputbox\" NAME=\"plusminus\">";
if ($plusminus) print "<option value=\"$plusminus\">$plusminus</option>";
if ($plusminus != "+") print "<option value=\"+\">+</option>";
if ($plusminus != "-") print "<option value=\"-\">-</option>";
print "</select>";
$procent=$procent*1;
print " &nbsp;<input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=2 name=\"procent\" value=\"$procent\">% &nbsp;";
print "<input class='button blue small' type=submit name=udfyld value=OK>";
print "</td></tr>";
print "</form>";
print "<tr><td><b>".findtekst(804, $sprog_id)."</b></td> ";
print "<td><b>".findtekst(805, $sprog_id)."</b></td> ";
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
			if (!isset($amount[$x][$z])) $amount[$x][$z]=0;
			if (!isset($id[$x][$z])) $id[$x][$z]=0;
			if ($kontotype[$x]!='Z') {
				if ($udfyld) {
				$tmp=afrund($amount[$x][$z]*$procent/100,0);
				if ($plusminus == "+") $amount[$x][$z]=afrund($amount[$x][$z]+$tmp,0);
				else $amount[$x][$z]=afrund($amount[$x][$z]-$tmp,0);
				}
				$tal=$amount[$x][$z];
				$ultimo[$x]+=$tal;
				if (!$tal) $tal="";
				($z==1)?$title="Sæt * efter værdien for at få samme beløb i alle felter for konto $kontonr[$x]":$title=NULL;
				print "<td title=\"".$title."\" align='right'><input type=\"text\" size=\"6\" style=\"text-align:right\" name=\"amount[$x][$z]\" value=\"$tal\"></td>";
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
			if (!isset($amount[$x][$z])) $amount[$x][$z]=0;
			if (!isset($id[$x][$z])) $id[$x][$z]=0;
			$tal=0;
			for ($y=1;$y<$x;$y++) {
				if ($kontonr[$y]>=$fra_kto[$x]) {
					if (!isset($amount[$y][$z])) $amount[$y][$z]=0;
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
	fwrite ($fp, mb_convert_encoding($budget_csvdata, 'ISO-8859-1', 'UTF-8'));
}
fclose($fp);

print "<input type='hidden' name='kontoantal' value='$kontoantal'>\n";
print "<input type='hidden' name='maanedantal' value='$maanedantal'>\n";
print "<tr>\n";
print "<td colspan='20'><center><input class='button green medium' style='width:150px;' type='submit' name='gem' value='".findtekst('3|Gem', $sprog_id)."' accesskey='g'>\n</center></td></tr>";  # 20210312
print "<tr><td colspan='20'><center>".findtekst('807|Hent budget som datafil ved at højreklikke på', $sprog_id)." <a href='".$filnavn."'>".findtekst('809|dette link', $sprog_id)."</a> ".findtekst('808|og vælg &apos;Gem link som ..', $sprog_id)."'</center></td></tr>"; 
print "</tr>\n";
print "</form>\n"; # 20150622 del 3 slut
####################################################################################################

print "</tbody>
</table>
	</td></tr>
</tbody></table>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>

