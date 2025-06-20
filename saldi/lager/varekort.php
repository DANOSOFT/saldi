<?php ob_start(); //Starter output buffering

// ---------------------------------/lager/varekort.php (modul nr. 9)-----------patch 1.0.9--------
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

$modulnr=9;
$title="Varekort";
$styklister=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
# include("../includes/db_query.php");
$id = $_GET['id'];
if ($funktion=$_GET['funktion']) {$funktion($id, $_GET['sort']);}
if ($konto_id=$_GET['konto_id']) {
	 db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ($konto_id, $id, 1)");
 }

if($_GET['returside']){
	$returside= $_GET['returside'];
	$ordre_id = $_GET['ordre_id'];
	$fokus = $_GET['fokus'];
	$vare_lev_id = $_GET['leverandor'];
	setcookie("saldi",$returside,$ordre_id,$fokus,$vare_lev_id);
#	setcookie("saldi",$regnskab,time()+60*60*24*30);
}

if ($_POST){
	$submit=trim($_POST['submit']);
	$id=$_POST['id'];
	$varenr=addslashes(trim($_POST['varenr']));
	$beskrivelse=addslashes(trim($_POST['beskrivelse']));
	$enhed=addslashes(trim($_POST['enhed']));
	$enhed2=addslashes(trim($_POST['enhed2']));
	$forhold=usdecimal($_POST['forhold']);
	$salgspris=usdecimal($_POST['salgspris']);
	$salgspris2=usdecimal($_POST['salgspris2']);
	$kostpris=$_POST['kostpris'];
	$kostpris[0]=usdecimal($kostpris[0]);
	$kostpris2=$_POST['kostpris2'];
	$provisionsfri=trim($_POST['provisionsfri']);
	list ($leverandor) = split(':', $_POST['leverandor']);
	$vare_lev_id=$_POST['vare_lev_id'];
	$lev_varenr=$_POST['lev_varenr'];
	$lev_antal=$_POST['lev_antal'];
	$lev_pos=$_POST['lev_pos'];
	$gruppe=$_POST['gruppe'];
	$min_lager= $_POST['min_lager']; 
	$max_lager= $_POST['max_lager']; 
	$lukket=$_POST['lukket'];
	$serienr=addslashes(trim($_POST['serienr']));
	list ($gruppe) = split (':', $_POST['gruppe']);
	$notes=addslashes(trim($_POST['notes']));
	$ordre_id=$_POST['ordre_id'];
	$returside=$_POST['returside'];
	$fokus=$_POST['fokus'];
	$delvare=$_POST['delvare'];
	$samlevare=$_POST['samlevare'];
	$fokus=$_POST['fokus'];
	$be_af_ant=$_POST['be_af_ant'];
	$be_af_id=$_POST['be_af_id'];
	$ant_be_af=$_POST['ant_be_af'];
	$indg_i_id=$_POST['indg_i_id'];
	$indg_i_ant=$_POST['indg_i_ant'];
	$ant_indg_i=$_POST['ant_indg_i'];
	$indg_i_pos=$_POST['indg_i_pos'];
	$be_af_pos=$_POST['be_af_pos'];

	 if ($submit=="Slet") {
		db_modify("delete from varer where id = $id");
	}
	else	{
		if (($salgspris == 0)&&($salgspris2 > 0)&&($forhold > 0)){$salgspris=$salgspris2*$forhold;}
		for($x=1; $x<=$lev_antal; $x++) {
			$lev_pos[$x]=trim($lev_pos[$x]);
			if (($lev_pos[$x]!="-")&&($lev_pos[$x])) {
				if (($kostpris[$x] == 0)&&($kostpris2[$x] > 0)&&($forhold > 0)){$kostpris[$x]=$kostpris2[$x]*$forhold;}
				$kostpris[$x]=usdecimal($kostpris[$x]);
				$lev_varenr[$x]=addslashes(trim($lev_varenr[$x]));
				db_modify("update vare_lev set posnr = $lev_pos[$x], lev_varenr = '$lev_varenr[$x]', kostpris = '$kostpris[$x]' where id = '$vare_lev_id[$x]'");
			}
			elseif (!$lev_pos[$x]) {print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en leverand&oslash;r!')\">";}
			else {db_modify("delete from vare_lev where id = '$vare_lev_id[$x]'");}
		}

		if (!$min_lager){$min_lager='0';}
		if (!$max_lager){$max_lager='0';}
		
		if (!$lukket){$lukket='0';}
		else {$lukket='1';}

		 if (strlen(trim($indg_i_ant[0]))>1) {
			list ($x) = split(':',$indg_i_ant[0]);
			$fejl=cirkeltjek($x, 'vare_id');
		}
		if (strlen(trim($be_af_ant[0]))>1) {
			list ($x) = split(':',$be_af_ant[0]);
			$fejl=cirkeltjek($x, 'indgaar_i');
		}

		if ((($samlevare=='on')||($delvare=='on'))&&($id)) {
			samletjek($id);

	}
	# if ($samlevare=='on'){$kostpris=0;}

		if (!$fejl) {
		if (($samlevare!='on')&&($ant_be_af>0)) {
			print "Du skal s&aelig;tte antal til 0 p&aring; samtlige varer som denne vare best&aring;r af, f&oslash;r du fjerner fluebenet i \"samlevare\"!<br>";
			$samlevare='on';
		}
		if (($delvare!='on')&&($ant_indg_i>0)) {
			print "Du skal s&aelig;tte antal til 0 p&aring; samtlige varer som denne vare indg&aring;r i, f&oslash;r du fjerner fluebenet i \"delvare\"!<br>";
			$delvare='on';
		}

		if(!$betalingsdage){$betalingsdage=0;}
		if ($id==0) {
			$query = db_select("select id from varer where varenr = '$varenr'");
			$row = db_fetch_array($query);
			if ($row[id]) {
				print "<BODY onLoad=\"javascript:alert('Der findes allerede en vare med varenr: $varenr!')\">";
				$varenr='';
				$id=0;
			}
			else {
#				db_modify("insert into varer (varenr, text, enhed, enhed2, forhold, salgspris, gruppe, serienr, lukket, notes, samlevare, delvare, min_lager) values ('$varenr', '$text', '$enhed', '$enhed2', '$forhold', '$salgspris', '$gruppe', '$serienr', '$lukket', '$notes', '$samlevare', '$delvare', '$min_lager')");
				db_modify("insert into varer (varenr, lukket) values ('$varenr', '0')");
				$query = db_select("select id from varer where varenr = '$varenr'");
				$row = db_fetch_array($query);
				$id = $row[id];
				if ($vare_lev_id) {db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ($vare_lev_id, $id, 1)");}
			}
		}
		elseif ($id > 0) {
			if (!$leverandor) {$leverandor='0';}
			db_modify("update varer set beskrivelse = '$beskrivelse', enhed='$enhed', enhed2='$enhed2', forhold='$forhold', salgspris = '$salgspris', kostpris = '$kostpris[0]', provisionsfri = '$provisionsfri', gruppe = '$gruppe', serienr = '$serienr', lukket = '$lukket', notes = '$notes', samlevare='$samlevare', delvare='$delvare', min_lager='$min_lager', max_lager='$max_lager' where id = '$id'");

			if ($samlevare=='on') {
				for ($x=1; $x<=$ant_be_af; $x++) {
					if (($be_af_ant[$x]>0)&&($be_af_pos[$x])) {
						$be_af_pos[$x]=round($be_af_pos[$x]);
#						$be_af_ant[$x]=round($be_af_ant[$x]);
						db_modify("update styklister set antal = $be_af_ant[$x], posnr = $be_af_pos[$x] where id = '$be_af_id[$x]'");
					}
					else {
					db_modify("delete from styklister where id = '$be_af_id[$x]'");}
				}
				if (strlen(trim($be_af_ant[0]))>1) {
					list ($x) = split(':',$be_af_ant[0]);
					$x=trim($x); 
					$query = db_select("select id from varer where varenr = '$x'");
					$row = db_fetch_array($query);
					db_modify("insert into styklister (vare_id, indgaar_i, antal, posnr) values ($row[id], $id, 1, $be_af_pos[0])");
					db_modify("update varer set delvare='on' where id = $row[id]");
				}
			}
			if ($delvare=='on') {
				for ($x=1; $x<=$ant_indg_i; $x++)	{
					if ($indg_i_ant[$x]>0) {
					#	$indg_i_ant[$x]=round($indg_i_ant[$x]);
						db_modify("update styklister set antal = $indg_i_ant[$x] where id = '$indg_i_id[$x]'");
					}
					else {db_modify("delete from styklister where id = '$indg_i_id[$x]'");}
				}
				if (strlen(trim($indg_i_ant[0]))>1) {
					list ($x) = split(':',$indg_i_ant[0]);
					$x=trim($x);
					$query = db_select("select id from varer where varenr = '$x'");
					$row = db_fetch_array($query);
					db_modify("insert into styklister (vare_id, indgaar_i, antal) values ($id, $row[id], 1)");
					db_modify("update varer set samlevare='on' where id = $row[id]");
				}
			}
			if (($samlevare=='on')||($delvare=='on')){prisopdat($id);}
		}
		$leverandor=trim($leverandor);
		if ($leverandor) {
			$query = db_select("select id from adresser where kontonr='$leverandor' and art = 'K'");
			if ($row = db_fetch_array($query)) {
				db_modify("insert into vare_lev (lev_id, vare_id) values ($row[id], $id)");
			}
		}
	 }
	}
	if (strstr($submit, "Leverand")) {kontoopslag($id, "navn");}
}
if (!$returside) $returside="../includes/luk.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "	<tr><td align=\"center\" valign=\"top\">";
print "		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
if (trim($returside)!='varer.php') print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font <small><a href=$returside?id=$ordre_id&fokus=$fokus&varenr=$varenr&vare_id=$id accesskey=T>Tilbage</a></small></td>";
else print "<td width=\"25%\" bgcolor=\"$bgcolor2\">$font <small><a href='../includes/luk.php?' accesskey=T>Tilbage</a></small></td>";
print "			<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font <small>varekort</small></td>";
print "			<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\">$font <small><a href=varekort.php?returside=$returside&ordre_id=$id accesskey=N>Ny</a></small></td>";
print "		</tbody></table>";
print "	</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if ($id > 0) {
	$query = db_select("select * from varer where id = '$id'");
	$row = db_fetch_array($query);
	$varenr=htmlentities(stripslashes($row['varenr']));
	$beskrivelse=htmlentities(stripslashes($row['beskrivelse']));
	$enhed=htmlentities(stripslashes($row['enhed']));
	$enhed2=htmlentities(stripslashes($row['enhed2']));
	$forhold=$row['forhold'];
	$salgspris=$row['salgspris'];
	$kostpris[0]=$row['kostpris'];
	$provisionsfri=$row['provisionsfri'];
	$gruppe=$row['gruppe'];
	$serienr=$row['serienr'];
	$lukket=$row['lukket'];
	$notes=htmlentities(stripslashes($row['notes']));
	$delvare=$row['delvare'];
	$samlevare=$row['samlevare'];
	$min_lager=$row['min_lager'];
	$max_lager=$row['max_lager'];
	$beholdning=$row[beholdning]*1;
#	$kpris=dkdecimal($row['kostpris']);
}
else {$gruppe=1; $leverandor=0;}

if (!$min_lager){$min_lager=0;}
if (!$max_lager){$max_lager=0;}

print "<form name=varekort action=varekort.php method=post>";

print "<input type=hidden name=id value='$id'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<input type=hidden name=leverandor value='$lev'>";
print "<tr><td>$font Varenr</td><td><br></td>";

if (!$varenr) {
	print "<input type=hidden name=vare_lev_id value=$vare_lev_id>";
	print "<td colspan=3><input type=text size=25 name=varenr value=\"$varenr\"></td></tr>";
}
else {
print "<td colspan=3><a href=ret_varenr.php?id=$id>$font $varenr</a></td></tr>";
print "<input type=hidden name=varenr value=$varenr>";
print "<tr><td>$font Beskrivelse</td><td><br></td><td colspan=3><input type=text size=60 name=beskrivelse value=\"$beskrivelse\"></td></tr>";

print "<tr><td>$font Enhed</td><td></td>";
print "<td><SELECT NAME=enhed>";
print "<option>$enhed</option>";
$query = db_select("select betegnelse from enheder order by betegnelse");
$x=0;
while ($row = db_fetch_array($query)) {
	$x++;
	$betegnelse[$x]=stripslashes($row['betegnelse']);

}
$antal_enheder=$x;
for ($x=0; $x<=$antal_enheder; $x++) {
	if ($enhed!=$betegnelse[$x]) {print "<option>$betegnelse[$x]</option>";}
}
print "</SELECT>&nbsp";
print "$font Alternativ enhed";
print "&nbsp;<SELECT NAME=enhed2>";
print "<option>$enhed2</option>";
for ($x=0; $x<=$antal_enheder; $x++) {
 if ($enhed2!=$betegnelse[$x]) {print "<option>$betegnelse[$x]</option>";}
}
print "</SELECT></td></tr>";

#print "<td width=100><input type=text size=2 name=enhed value='$enhed'>&nbsp;$font Alternativ enhed&nbsp;<input type=text size=2 name=enhed2 value='$enhed2'></td></tr>";
if ($forhold > 0){$x=dkdecimal($forhold);}
else {$x='';}
if (($enhed)&&($enhed2)) print "<tr><td>$font $enhed2/$enhed</td><td><br></td><td width=100><input type=text size=2 name=forhold value=\"$x\"></td></tr>";

$x=dkdecimal($salgspris);
print "<tr><td>$font Salgspris</td><td><br></td><td><input type=text align=right size=10 name=salgspris value=\"$x\">$font /$enhed&nbsp;";
if (($enhed2)&&($forhold>0)) {
	$x=dkdecimal($salgspris/$forhold);
	print "<input type=text align=right size=10 name=salgspris2 value=\"$x\">$font /$enhed2";
}
print "</td></tr>";
$x=dkdecimal($kostpris[0]);
print "<tr><td>$font Kostpris</td><td><br></td><td><input type=text align=right size=10 name=kostpris[0] value=\"$x\">$font /$enhed&nbsp;";

if ($samlevare!='on') {
	if ($id) {
		print "<tr><td></td><td></td><td><table border=1><tbody>";
		print "<tr><td>$font Pos.</td><td>$font Leverand&oslash;r</td><td>$font Varenr.</td><td>$font Kostpris ($enhed)</td>";
		if (($enhed2)&&($forhold>0)) {print "<td>$font Kostpris ($enhed2)</td>";}
		print "</tr>";
		$x=0;
		$vare_lev_id=array();
	 $query = db_select("select * from vare_lev where vare_id='$id' order by posnr");
		while ($row = db_fetch_array($query)) {
			$x++;
			$vare_lev_id[$x]=$row[id];
			$lev_id[$x]=$row[lev_id];
			$lev_varenr[$x]=htmlentities(stripslashes($row['lev_varenr']));
			$kostpris[$x]=$row['kostpris'];
		}
		$lev_ant=$x;
		 print "<input type=hidden name=lev_antal value=$lev_ant>";
		for ($x=1; $x<=$lev_ant; $x++) {
			$query = db_select("select kontonr, firmanavn from adresser where id='$lev_id[$x]'");
			$row = db_fetch_array($query);
			$y=dkdecimal($kostpris[$x]);
			print "<td><span title='Pos = minus sletter leverandøren';><input type=text size=1 name=lev_pos[$x] value=$x></span></td><td>$font $row[kontonr]:".htmlentities(stripslashes($row[firmanavn]))."</td><td><input type=text align=right size=9 name=lev_varenr[$x] value=$lev_varenr[$x]></td><td align=right><input type=text align=right size=9 name=kostpris[$x] value=\"$y\"></td>";
			if (($enhed2)&&($forhold>0)) {
				$y=dkdecimal($kostpris[$x]/$forhold);
				print "<td><input type=text align=right size=9 name=kostpris2[$x] value=\"$y\"></td>";
			}
			print "</td></tr>";
			print "<input type=hidden name=vare_lev_id[$x] value=$vare_lev_id[$x]>";
		}
		print "</tbody></table>";
	}
	else {
	}
}
print "<tr><td>$font Varegruppe</td><td></td>";
if (!$gruppe){$gruppe=1;}
$query = db_select("select beskrivelse from grupper where art='VG' and kodenr='$gruppe'");
$row = db_fetch_array($query);
print "<td colspan=3><SELECT NAME=gruppe value='$gruppe'>";
print "<option>$gruppe:$row[beskrivelse]</option>";
$query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' order by kodenr");
while ($row = db_fetch_array($query)) {
	print "<option>$row[kodenr]:$row[beskrivelse]</option>";
}
print "</SELECT></td></tr>";
print "<tr><td>$font Beholdning</td><td>min:</td><td><input type=text size=5 align=right name=min_lager value=$min_lager>";
print "&nbsp;&nbsp;max:&nbsp;<input type=text size=5 align=right name=max_lager value=$max_lager>";
print "&nbsp;&nbsp;aktuel: $beholdning</td></tr>";
if ($provisionsfri == 'on') {print "<td>$font Provisionsfri</td><td></td><td><input type=checkbox name=provisionsfri checked></td>";}
else {print "<td>$font Provisionsfri</td><td></td><td><input type=checkbox name=provisionsfri></td>";}
print "<tr><td valign=top>$font Bem&aelig;rkning</td><td><br></td><td colspan=9><textarea name=\"notes\" rows=\"3\" cols=\"60\">$notes</textarea></td></tr>";
print "<tr><td colspan=2></td><td colspan=3><table width=100% border=0><tbody><tr>";
if ($serienr == 'on') {print "<td width=25%>$font Serienr.&nbsp;<input type=checkbox name=serienr checked></td>";}
else {print "<td width=25%>$font Serienr&nbsp;<input type=checkbox name=serienr></td>";}
if ($styklister) { # /*	Udeladt intil test af vareflow er afsluttet (2006-03-03)
	if ($samlevare == 'on') {print "<td width=25%>$font Samlevare&nbsp;<input type=checkbox name=samlevare checked></td>";}
	else {print "<td width=25%>$font Samlevare&nbsp;<input type=checkbox name=samlevare></td>";}
	if ($delvare == 'on') {print "<td width=25%>$font Delvare&nbsp;<input type=checkbox name=delvare checked></td>";}
	else {print "<td width=25%>$font Delvare&nbsp;<input type=checkbox name=delvare></td>";}
}
if ($lukket==0) {print "<td width=25%>$font Lukket&nbsp;<input type=checkbox name=lukket></td>";}
else {print "<td width=25%>$font Lukket&nbsp;<input type=checkbox name=lukket checked></td>";}
}
print "</tr></tbody></table></td></tr>";
print "<tr><td colspan=5 width=100%><table border=0 width=100%><tbody>";
if ($samlevare=='on') {
	$query = db_select("select * from styklister where indgaar_i=$id");
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$query2 = db_select("select * from varer where id = $row[vare_id]");
		$row2 = db_fetch_array($query2);
		$be_af_vnr[$x]=$row2[varenr];
		$be_af_beskrivelse[$x]=$row2[beskrivelse];
		$be_af_enhed[$x]=$row2[enhed];
		$be_af_ant[$x]=$row[antal];
		$be_af_id[$x]=$row2[id];
		print "<input type=hidden name=be_af_id[$x] value='$row[id]'>";
	}
	$ant_be_af=$x;
}
if ($delvare=='on')
{
	$query = db_select("select * from styklister where vare_id=$id order by vare_id");
	$x=0;
	while ($row = db_fetch_array($query)) {
		$x++;
		$query2 = db_select("select * from varer where id = $row[indgaar_i]");
		$row2 = db_fetch_array($query2);
		$indg_i_vnr[$x]=$row2[varenr];
		$indg_i_beskrivelse[$x]=$row2[beskrivelse];
		$indg_i_enhed[$x]=$row2[enhed];
		$indg_i_ant[$x]=$row[antal];
		$indg_i_id[$x]=$row2[id];
		print "<input type=hidden name=indg_i_id[$x] value='$row[id]'>";
	}
	$ant_indg_i=$x;
}

if ($samlevare=='on') {
	print "<tr><td valign=top><table width=20%><tbody><tr><td>$font <a href=stykliste.php?id=$id>Stykliste</a></td></tr><tr><td>$font <a href=fuld_stykliste.php?id=$id>Komplet</a></td></tr></tbody></table></td>";
	print "<td></td><td><table border=0 width=80%><tbody>";
	print "<tr><td>$font Pos.</td><td width=80>$font V.nr.</td><td width=300>$font Beskrivelse</td><td>$font Antal</td></tr>";
	for ($x=1; $x<=$ant_be_af; $x++){
		print "<tr><td><input type=text size=2 name=be_af_pos[$x] value=$x></td><td>$font<small><a href=varekort.php?id=$be_af_id[$x]>$be_af_vnr[$x]</a></td><td>$font<small> $be_af_beskrivelse[$x]</td><td>$font<small><input type=text size=2 name=be_af_ant[$x] value=$be_af_ant[$x]>&nbsp;$be_af_enhed[$x]</td></tr>";
	}
	print "<tr><td><input type=text size=2 name=be_af_pos[0]] value=$x></td><td colspan=2><SELECT NAME=be_af_ant[0]>";
	print "<option>$font<small> $row[varenr]&nbsp;$font<small>".substr($row[beskrivelse],0,60)."</option>";
	$query = db_select("select * from varer where id != $id order by varenr");
	while ($row = db_fetch_array($query)){
		if ((!in_array($row[id], $be_af_id))&&(!in_array($row[id], $indg_i_id))){print "<option>$font<small>$row[varenr] : $font<small>".substr($row[beskrivelse],0,60)."</option>";}
	}
	print "</SELECT></td>";
	print "</tr></tbody></table></td></tr>";
	print "<input type=hidden name=ant_be_af value='$ant_be_af'>";
}
if ($delvare=='on') {
	print "<tr><td valign=top width=10%>$font Indg&aring;r i</td><td></td><td><table width=80% border=0><tbody>";
	print "<tr><td>$font Pos.</td><td width=80>$font V.nr.</td><td width=300>$font Beskrivelse</td><td>$font Antal</td></tr>";
	for ($x=1; $x<=$ant_indg_i; $x++) {
		print "<tr><td><input type=text size=2 name=indg_i_ant[$x] value=$x></td><td>$font<small><a href=varekort.php?id=$indg_i_id[$x]>$indg_i_vnr[$x]</a></td><td>$font<small> $indg_i_beskrivelse[$x]</td><td>$font<small><input type=text size=2 name=indg_i_ant[$x] value=$indg_i_ant[$x]>&nbsp;$indg_i_enhed[$x]</td></tr>";
	}
	print "<tr><td><input type=text size=2 name=indg_i_ant[$x] value=$x></td><td colspan=2><SELECT NAME=indg_i_ant[0]>";
	print "<option>$font<small> $row[varenr]&nbsp;$font<small>".substr($row[beskrivelse],0,60)."</option>";
	$query = db_select("select * from varer where id != $id order by varenr");
	while ($row = db_fetch_array($query)) {
		if ((!in_array($row[id], $be_af_id))&&(!in_array($row[id], $indg_i_id))){print "<option>$font<small> $row[varenr] : $font<small>".substr($row[beskrivelse],0,60)."</option>";}
	}	
	print "</SELECT></td>";
	print "</tr></tbody></table></td></tr>";
}
 print "<tr><td colspan=4><hr></td></tr>";
print "<tr><td colspan=4 align=center><table width=100%><tbody>";

print "<input type=hidden name=ant_indg_i value='$ant_indg_i'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";

print "<tr><td align = center><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\"></td>";

if ($varenr) {print "<td align = center><input type=submit accesskey=\"l\" value=\"Leverand&oslash;ropslag\" name=\"submit\"></td>";}

if ($id) {
	$query = db_select("select id from ordrelinjer where vare_id = $id");
	if ((!$row = db_fetch_array($query))&&($lev_ant < 1)) {
	 print "<td align=center><input type=submit value=\"Slet\" name=\"submit\"></td>";
	}
}

print "</tr></tbody></table></td></tr>";
print "</tr></tbody></table></td></tr>";

function prisopdat($id) {
	global $connect;	

	$x=0;
	$query = db_select("select id from varer where delvare = 'on' and samlevare != 'on'"); #finder varer paa laveste nevieu
	while ($row = db_fetch_array($query)) {
		$x++;
		$vare_id[$x]=$row[id];
	}	
	$vareantal=$x;
		
	$x=0;
	$query = db_select("select * from styklister");
	while ($row = db_fetch_array($query)) {
		$x++;
		$s_id[$x]=$row[id];
		$s_vare_id[$x]=$row[vare_id];
		$s_antal[$x]=$row[antal];
		$s_indgaar_i[$x]=$row[indgaar_i];
	}
	$antal_s=$x;
	$kontrol=array();
	$x=0;
	for ($a=1; $a<=$vareantal; $a++) {
		$kostpris=0;
		for ($b=1; $b<=$antal_s; $b++) {
			if ($vare_id[$a]==$s_indgaar_i[$b]) {
				 $query = db_select("select kostpris from vare_lev where vare_id = $s_vare_id[$b] order by posnr"); #finder varer 1 nivaau lavere
				 if ($row = db_fetch_array($query)){$kostpris=$kostpris+$row[kostpris]*$s_antal[$b];}
				 else {
					 $query = db_select("select kostpris from varer where id = $s_vare_id[$b]"); #finder varer 1 nivaau lavere
					 $row = db_fetch_array($query);
					 $kostpris=$kostpris+$row[kostpris]*$s_antal[$b];
				 }
			}
			if ($vare_id[$a]==$s_vare_id[$b]) {
				 $vareantal++;
				 $vare_id[$vareantal]=$s_indgaar_i[$b];
			}
		}
		if ($kostpris>0) db_modify("update varer set kostpris='$kostpris' where id=$vare_id[$a]");
	}
	for ($a=1; $a<=$vareantal; $a++)	{
	}
}

function samletjek($id){
	$x=0;
	$indgaar_i=array();
	$vare_id=array();
	$query = db_select("select vare_id, indgaar_i from styklister where vare_id != $id"); 
	while ($row = db_fetch_array($query)) {
		$x++;
		$indgaar_i[$x]=$row[indgaar_i];
		$vare_id[$x]=$row[vare_id];
	}
	$query = db_select("select id from varer where id != $id and samlevare='on'");
	while ($row = db_fetch_array($query)) {
		if (!in_array($row[id], $indgaar_i)) {db_modify("update varer set samlevare = '' where id=$row[id]");}
		else {db_modify("delete from vare_lev where vare_id=$row[id]");}
	}
	$query = db_select("select id from varer where id != $id and delvare='on'");
	while ($row = db_fetch_array($query)) {
		if (!in_array($row[id], $vare_id)) {db_modify("update varer set delvare = '' where id=$row[id]");}
	}
}

function cirkeltjek($id, $retning) {
	$s_id[0]=trim($id); 
	$query = db_select("select id from varer where varenr='$id'"); #Varen ikke er tilfÃžjet i "styklister endnu"	 
	if ($row = db_fetch_array($query)) {	
		$id=$row[id];
		$x=0;
		$query = db_select("select * from styklister where $retning=$id");
		while ($row = db_fetch_array($query))	{
			$x++;
			$s_id[$x]=$row[id];
			$s_vare_id[$x]=$row[vare_id];
		} 
		$antal_s=$x;
		$fejl=0;
		for ($a=1; $a<=$antal_s; $a++)	{
			$slut=0;
			while (($slut==0)&&($fejl==0))	{
				$query = db_select("select * from styklister where $retning = $s_vare_id[$a]"); 
				while ($row = db_fetch_array($query)) {
					if (in_array($row[id], $s_id)) {
						print " Du har lavet en cirkul&aelig;r referance - posten er ikke blevet gemt<br>";
						$fejl=1;
						break;
					}
					elseif($row[vare_id]) {
						$x++;
						$s_id[$x]=$row[id];
						$s_vare_id[$x]=$row[vare_id];
					}
				}
			
				if ($antal_s==$x) {$slut=1;}
				else {$antal_s=$x;}
			}
		}
	}
	if ($fejl>0) {return $fejl;}
}
######################################################################################################################################
function kontoopslag($id, $sort)
{
	global $bgcolor2;
	global $font;
	global $returside;
	global $ordre_id;
	global $fokus;
	global $vare_lev_id;
		
#	 $returside="../lager/varekort.php";

#	sidehoved($id, "varekort.php", "../debitor/debitorkort.php", $fokus, "Kundeordre $id - Kontoopslag");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr><td colspan=8>";
	print "		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "			<td width=\"25%\" bgcolor=\"$bgcolor2\">$font <small><a href=$returside?id=$id&fokus=$fokus accesskey=T>Tilbage</a></small></td>";
print "			<td width=\"50%\" bgcolor=\"$bgcolor2\" align=\"center\">$font <small>varekort</small></td>";
print "			<td width=\"25%\" bgcolor=\"$bgcolor2\" align = \"right\">$font <small><a href=../kreditor/kreditorkort.php?returside=$returside&ordre_id=$id accesskey=N>Ny</a></small></td>";
print "		</tbody></table></td></tr>";

	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=kontonr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Kundenr</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=firmanavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Navn</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=addr1&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Adresse</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=addr2&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Adresse2</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=postnr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Postnr</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=bynavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>bynavn</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=land&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>land</b></small></td>";
#	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=kontakt&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Kontaktperson</b></small></td>";
	print"<td><small><b><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?sort=tlf&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&$fokus=$fokus&vare_lev_id=$vare_lev_id>Telefon</b></small></td>";
	print" </tr>";


	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = firmanavn;}

	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' order by $sort");
	while ($row = db_fetch_array($query))
	{
		$kontonr=str_replace(" ","",$row['kontonr']);
		print "<tr>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\"><a href=varekort.php?fokus=$fokus&id=$id&konto_id=$row[id]&returside=$returside&ordre_id=$ordre_id&vare_lev_id=$vare_lev_id>$row[kontonr]</a></small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[firmanavn]</small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[addr1]</small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[addr2]</small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[postnr]</small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[bynavn]</small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[land]</small></td>";
#		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[kontakt]</small></td>";
		print "<td><small><font face=\"Helvetica, Arial, sans-serif\">$row[tlf]</small></td>";
		print "</tr>";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################


?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
