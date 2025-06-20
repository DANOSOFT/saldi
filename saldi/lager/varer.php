<?php
// --------------------------------------------------lager/varer.php-------------patch 1.0.5----------
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
?>
<script type="text/javascript">
<!--
var vare_id=0;
var lager=0;
var space=":";

function lagerflyt(vare_id, lager)
{
	window.open("lagerflyt.php?input="+ lager +space + vare_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
}
//-->
</script>
<?php
$title="Varer";
$modulnr=9;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
# include("../includes/db_query.php");

$vis_lev=$_GET['vis_lev'];
$sort = $_GET['sort'];
if (isset($_GET['start'])) $start = $_GET['start'];
else $start=0;
if (isset($_GET['linjeantal'])) $linjeantal = $_GET['linjeantal'];
else $linjeantal=500;
if (isset($_GET['slut'])) $slut = $_GET['slut'];
else $slut=$start+$linjeantal;

if (isset($_POST['start'])) {
	$start = $_POST['start'];
	$linjeantal = $_POST['linjeantal'];
	$slut=$start+$linjeantal;
}
if (!isset($linjeantal)) $linjeantal=100;
if ($slut <= $start) $slut=$start+$linjeantal;

if (!$sort) $sort = varenr;

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small><a href=../includes/luk.php accesskey=T>Tilbage</a></small></td>\n";
print "<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small>Vareliste</small></td>\n";
print "<td width=\"25%\" bgcolor=\"$bgcolor2\" align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:window.open('varekort.php?returside=varer.php','varekort','scrollbars=1,resizable=1');ordre.focus();\"><small>$font<span style=\"text-decoration: underline;\">Ny</a></span></small></td>";
#<font face=\"Helvetica, Arial, sans-serif\" color=\"$color\"><small><a href=varekort.php accesskey=N>Ny</a></small></td>\n";
print "</td></tr>\n";

print "<form name=vareliste action=varer.php method=post>";
print "<input type=hidden name=valg value=$valg>";
print "<input type=hidden name=sort value=$sort>";
print "<input type=hidden name=start value=$start>";
$next=udskriv($start, $slut, $sort, '');
if ($start>=$linjeantal) {
	$tmp=$start-$linjeantal;
	print "<td><a href='varer.php?sort=$sort&start=$tmp&linjeantal=$linjeantal'><img src=../ikoner/left.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
}
else print  "<td></td>";
print "<td></td>";
#print "<td align=center><span title= 'Angiv max antal linjer som skal vises pr side'>";
#print "<input type=text size=4 name=start value=$start > - ";
#print "<input type=text size=2 name=linjeantal value=$linjeantal ></td>";
$tmp=$start+$linjeantal;
if ($next>=$slut) {
	print "<td align=right><a href='varer.php?sort=$sort&start=$tmp&linjeantal=$linjeantal'><img src=../ikoner/right.gif style=\"border: 0px solid; width: 15px; height: 15px;\"></a></td>";
}
else print  "<td></td>"; 
print "</tr>\n";

print "<tr>";
# print "<td><span title= 'Angiv et varenummer eller angiv to adskilt af kolon (f.eks 345:350)'><input type=text size=5 name=varenumre value=$varenumre></td>";
# print "<td colspan=3 align=right><input type=submit value=\"OK\" name=\"submit\"></td></tr>";
print "</form></tr>\n";

print "</tbody></table>\n";

print "<tr><td valign=\"top\">\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">\n";
print "<tbody>\n";
if (!$vis_lev) {
	$x=0;
	$lagernavn[0]="Hovedlager";
	$query = db_select("select beskrivelse, kodenr from grupper where art='LG' order by kodenr");
	while ($row = db_fetch_array($query)) {
		$x++;
		$lagernavn[$x]=$row['beskrivelse'];			 
	}
	$lagerantal=$x;
	$x=$x+6; #kolonneantal;
	print "<tr><td colspan=$x align=center><a href=varer.php?vis_lev=on&sort=$sort>$font<small>Vis lev. info</a></td></tr>";
}
else {print "<tr><td colspan=9 align=center><a href=varer.php?sort=$sort>$font<small>Udelad lev. info</a></td></tr>";}
print "</form>";
print "<tr>";
print "<td><small><b>$font<a href=varer.php?sort=varenr&vis_lev=$vis_lev&start=$start>Varenr</b></small></td>\n";
print "<td><small><b>$font<a href=varer.php?sort=enhed&vis_lev=$vis_lev&start=$start>Enhed</b></small></td>\n";
print "<td><small><b>$font<a href=varer.php?sort=beskrivelse&vis_lev=$vis_lev&start=$start>Beskrivelse</a></b></small></td>\n";
if (!$vis_lev){
	if ($lagerantal>=1) {
		for ($x=0;$x<=$lagerantal; $x++) {
			print "<td align=right><small><b>$font<span title= '$lagernavn[$x]'>L $x</b></small></td>\n";
		}
	print "<td align=right><small><b>$font<a href=varer.php?sort=beholdning&vis_lev=$vis_lev>Ialt</a></b></small></td>\n";
	}
	else {	
		print "<td align=right><small><b>$font<a href=varer.php?sort=beholdning&vis_lev=$vis_lev>Beholdn.</a></b></small></td>\n";
	}
}
print "<td align=right><small><b>$font<a href=varer.php?sort=salgspris&vis_lev=$vis_lev>Salgspris</a></b></small></td>\n";
if ($vis_lev) {
	print "<td align=right><small><b>$font Kostpris</b></small></td>\n";
	print "<td align=right><small><b>$font Beholdn.</b></small></td>\n";	
	print "<td>&nbsp;</td>\n";
	print "<td><small><b>$font Leverand&oslash;r</b></small></td>\n";
	print "<td><small><b>$font Lev. varenr</small></td>\n";
}
print "</tr>\n";

$next = udskriv($start, $slut, $sort, '1');
if ($next<$slut) lukkede_varer();

function udskriv($start, $slut, $sort, $udskriv) {

if (!$slut) $slut=$start+50; 

$query = db_select("select * from varer where lukket != '1' order by $sort");
while ($row = db_fetch_array($query)) {
	$z++;
	if (($z>=$start)&&($z<$slut)){
		$z1++;
		if ($udskriv) {
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		$kort="kort".$row[id];
		#print "<td><small>$font<a href=varekort.php?id=$row[id]>".htmlentities(stripslashes($row[varenr]))."</a><br></small></td>";
		print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kort=window.open('varekort.php?id=$row[id]&returside=varer.php','$kort','scrollbars=1,resizable=1');$kort.focus();\"><small>$font<span style=\"text-decoration: underline;\">".htmlentities(stripslashes($row[varenr]))."</a></span></small></td>";
	
		print "<td><small>$font ".htmlentities(stripslashes($row[enhed]))."<br></small></td>";
		print "<td><small>$font ".htmlentities(stripslashes($row[beskrivelse]))."<br></small></td>";
	#	if (!$row[beholdning]){$row[beholdning]=0;}
		if (!$vis_lev){
			if ($lagerantal>=1) { 
			for ($x=0;$x<=$lagerantal; $x++) {
					$r2=db_fetch_array(db_select("select lager, beholdning from lagerstatus where vare_id = $row[id] and lager = $x"));
					$y=$r2[beholdning];
	#				if (!$y) {$y='0';} 
					print "<td align=center onClick=\"lagerflyt($row[id], $x)\">$font<span title= 'Flyt til andet lager'><a href><small>$y</small></a></td></td>";
				}
			}
			print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";
		}
		$salgspris=dkdecimal($row[salgspris]);
		print "<td align=right><small>$font $salgspris<br></small></td>";
		if ($vis_lev==on) {
			$query2 = db_select("select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $row[id] order by posnr");
			$row2 = db_fetch_array($query2);
			if ($row2[lev_id]) {
				$lev_varenr=$row2['lev_varenr'];
				$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]");
				$levrow = db_fetch_array($levquery);
				$kostpris=dkdecimal($row2[kostpris]);
			}
			elseif ($row[samlevare]=='on') {$kostpris=dkdecimal($row[kostpris]);}
			print "<td align=right><small>$font $kostpris</small></td>";
			$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
			$row2 =db_fetch_array($query2);
			if (($row2[box8]=='on')||($row[samlevare]=='on')){
				 $ordre_id=array();
			 	$x=0;
			 	$query2 = db_select("select id from ordrer where status >= 1	and status < 3 and art = 'DO'");
			 	while ($row2 =db_fetch_array($query2)){
				 	$x++;
				 	$ordre_id[$x]=$row2[id];
			 	}
			 	$x=0;
			 	$query2 = db_select("select id, ordre_id, antal from ordrelinjer where vare_id = $row[id]");
			 	while ($row2 =db_fetch_array($query2)) {
				 	if (in_array($row2[ordre_id],$ordre_id)) {
					 	$x=$x+$row2[antal];	 
					 	$query3 = db_select("select antal from batch_salg where linje_id = $row2[id]");
					 	while ($row3=db_fetch_array($query3)) {$x=$x-$row3[antal];}
				 	}
			 	}	
			 	$linjetext="<span title= 'Der er $x i ordre'>";
			 	print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";		
			 	print "<td></td>";		
			 	print "<td><small>$font $levrow[kontonr] - ".htmlentities(stripslashes($levrow[firmanavn]))."</small></td>";
			 	print "<td><small>$font ".htmlentities(stripslashes($lev_varenr))."</small></td>";
			}
			else {print "<td></td>";}	 
		}
		print "</tr>\n";
	}
	}
	elseif ($z>=$slut) {
		break;
	}
}
return($z);
}


function lukkede_varer(){
global $sort;

print "<tr><td colspan=9><hr></td></tr>";
print "<tr><td colspan=9 align=center>$font<small>Lukkede varer</small></td></tr>";
print "<tr><td colspan=9><hr></td></tr>";


$query = db_select("select * from varer where lukket = '1' order by $sort");
while ($row = db_fetch_array($query)) {
	if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
	else {$linjebg=$bgcolor5; $color='#000000';}
	print "<tr bgcolor=\"$linjebg\">";
	$kort="kort".$row[id];
	#print "<td><small>$font<a href=varekort.php?id=$row[id]>".htmlentities(stripslashes($row[varenr]))."</a><br></small></td>";
	print "<td onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kort=window.open('varekort.php?id=$row[id]&returside=varer.php','$kort','scrollbars=1,resizable=1');$kort.focus();\"><small>$font<span style=\"text-decoration: underline;\">".htmlentities(stripslashes($row[varenr]))."</a></span></small></td>";
	
	print "<td><small>$font ".htmlentities(stripslashes($row[enhed]))."<br></small></td>";
	print "<td><small>$font ".htmlentities(stripslashes($row[beskrivelse]))."<br></small></td>";
#	if (!$row[beholdning]){$row[beholdning]=0;}
	if (!$vis_lev){
		if ($lagerantal>=1) { 
		for ($x=0;$x<=$lagerantal; $x++) {
				$r2=db_fetch_array(db_select("select lager, beholdning from lagerstatus where vare_id = $row[id] and lager = $x"));
				$y=$r2[beholdning];
#				if (!$y) {$y='0';} 
				print "<td align=center onClick=\"lagerflyt($row[id], $x)\">$font<span title= 'Flyt til andet lager'><a href><small>$y</small></a></td></td>";
			}
		}
		print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";
	}
	$salgspris=dkdecimal($row[salgspris]);
	print "<td align=right><small>$font $salgspris<br></small></td>";
	if ($vis_lev==on) {
		$query2 = db_select("select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $row[id] order by posnr");
		$row2 = db_fetch_array($query2);
		if ($row2[lev_id]) {
			$lev_varenr=$row2['lev_varenr'];
			$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]");
			$levrow = db_fetch_array($levquery);
			$kostpris=dkdecimal($row2[kostpris]);
		}
		elseif ($row[samlevare]=='on') {$kostpris=dkdecimal($row[kostpris]);}
		print "<td align=right><small>$font $kostpris</small></td>";
		$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'");
		$row2 =db_fetch_array($query2);
		if (($row2[box8]=='on')||($row[samlevare]=='on'))
		{
			 $ordre_id=array();
			 $x=0;
			 $query2 = db_select("select id from ordrer where status >= 1	and status < 3 and art = 'DO'");
			 while ($row2 =db_fetch_array($query2))
			 {
				 $x++;
				 $ordre_id[$x]=$row2[id];
			 }
			 $x=0;
			 $query2 = db_select("select id, ordre_id, antal from ordrelinjer where vare_id = $row[id]");
			 while ($row2 =db_fetch_array($query2))
			 {
				 if (in_array($row2[ordre_id],$ordre_id))
				 {
					 $x=$x+$row2[antal];	 
					 $query3 = db_select("select antal from batch_salg where linje_id = $row2[id]");
					 while ($row3=db_fetch_array($query3)) {$x=$x-$row3[antal];}
				 }
			 }	
			 $linjetext="<span title= 'Der er $x i ordre'>";
			 print "<td align=right>$linjetext<small>$font $row[beholdning]</small></span></td>";		
			 print "<td></td>";		
			 print "<td><small>$font $levrow[kontonr] - ".htmlentities(stripslashes($levrow[firmanavn]))."</small></td>";
			 print "<td><small>$font ".htmlentities(stripslashes($lev_varenr))."</small></td>";
		}
		else {print "<td></td>";}	 
	}
	print "</tr>\n";
}
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
