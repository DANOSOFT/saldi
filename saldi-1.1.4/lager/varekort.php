<?php ob_start(); //Starter output buffering

// ----------/lager/varekort.php ----------patch 1.1.4--------
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

@session_start();
$s_id=session_id();

$modulnr=9;
$title="Varekort";
$styklister=0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/vareopslag.php");
include("../includes/fuld_stykliste.php");
$font="<small>".$font;
$id = $_GET['id'];
if($_GET['returside']){
	$returside= $_GET['returside'];
	$ordre_id = $_GET['ordre_id']*1;
	$fokus = $_GET['fokus'];
	$vare_lev_id = $_GET['leverandor'];
	$vis_samlevarer =  $_GET['vis_samlevarer'];
	setcookie("saldi",$returside,$ordre_id,$fokus,$vare_lev_id);
}
if ($funktion=$_GET['funktion']) {
	$funktion($_GET['sort'], $_GET['fokus'], $id,  $_GET['vis_kost'], '',$_GET['find'], 'varekort.php');
}
if ($konto_id=$_GET['konto_id']) {
	 db_modify("insert into vare_lev (lev_id, vare_id, posnr) values ('$konto_id', '$id', '1')");
 }
if (($vare_id=$_GET['vare_id'])&&(cirkeltjek($vare_id)==0)) { 
# Fejlsøges - indsættter vare i stkliste efter leverandoropslag --- 
#	db_modify("insert into styklister (vare_id, indgaar_i, antal) values ('$vare_id', '$id', '1')");
#	db_modify("update varer set delvare =  'on' where id = '$vare_id'");
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
	$gl_kostpris=$_POST['gl_kostpris'];
	$kostpris[0]=usdecimal($kostpris[0]);
	$kostpris2=$_POST['kostpris2'];
	$provisionsfri=trim($_POST['provisionsfri']);
	list ($leverandor) = split(':', $_POST['leverandor']);
	$vare_lev_id=$_POST['vare_lev_id'];
	$lev_varenr=$_POST['lev_varenr'];
	$lev_antal=$_POST['lev_antal'];
	$lev_pos=$_POST['lev_pos'];
	$gruppe=$_POST['gruppe'];
	$operation=$_POST['operation'];
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
	$be_af_vare_id=$_POST['be_af_vare_id'];
	$be_af_vnr=$_POST['be_af_vnr'];
	$be_af_beskrivelse=$_POST['be_af_beskrivelse'];


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
#			$fejl=cirkeltjek($x, 'vare_id');
		}
		if (strlen(trim($be_af_ant[0]))>1) {
			list ($x) = split(':',$be_af_ant[0]);
		}

#		if (($samlevare=='on')&&($id)) samletjek($id);
#		if ((($samlevare=='on')||($delvare=='on'))&&($id)) {
#			samletjek($id);
#	}
	# if ($samlevare=='on'){$kostpris=0;}
		if (($delvare=='on')&&($gl_kostpris-$kostpris[0]!=0)) {
#				print "<BODY onLoad=\"javascript:alert('Opdater priser p&aring; alle vare som denne vare indg&aring;r i - Det kan vare flere minutter!')\">";
			$diff=$kostpris[0]-$gl_kostpris;
#			prisopdat($id, $diff);
		}	

		if (!$fejl) {
		if (($samlevare!='on')&&($ant_be_af>0)) {
			print "Du skal s&aelig;tte antal til 0 p&aring; samtlige varer som denne vare best&aring;r af, f&oslash;r du fjerner fluebenet i \"samlevare\"!<br>";
			$samlevare='on';
		}
#		if (($delvare!='on')&&($ant_indg_i>0)) {
#			print "Du skal s&aelig;tte antal til 0 p&aring; samtlige varer som denne vare indg&aring;r i, f&oslash;r du fjerner fluebenet i \"delvare\"!<br>";
#			$delvare='on';
#		}

		if(!$betalingsdage){$betalingsdage=0;}
		if ($id==0) {
			$query = db_select("select id from varer where varenr = '$varenr'");
			$row = db_fetch_array($query);
			if ($row[id]) {
				print "<BODY onLoad=\"javascript:alert('Der findes allerede en vare med varenr: $varenr!')\">";
				$varenr='';
				$id=0;
			} else {
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
			db_modify("update varer set beskrivelse = '$beskrivelse', enhed='$enhed', enhed2='$enhed2', forhold='$forhold', salgspris = '$salgspris', kostpris = '$kostpris[0]', provisionsfri = '$provisionsfri', gruppe = '$gruppe', serienr = '$serienr', lukket = '$lukket', notes = '$notes', samlevare='$samlevare', min_lager='$min_lager', max_lager='$max_lager' where id = '$id'");
			if (($operation)&&($r=db_fetch_array(db_select("select varenr from varer where operation = '$operation' and id !=$id")))) {
				print "<BODY onLoad=\"javascript:alert('Operationsnr: $operation er i brug af $r[varenr]! Operationsnr ikke &aelig;ndret')\">";
			} elseif ($operation) {
				$r=db_fetch_array(db_select("select box10 from grupper where art='VG' and kodenr = '$gruppe'"));
				if ($r[box10]!='on') $operation=0;
				db_modify("update varer set operation = '$operation' where id = '$id'");
			}
######################################## Stykliste ############################################
			if ($samlevare=='on') {
				for ($x=1; $x<=$ant_be_af; $x++) {
					if (($be_af_ant[$x]>0)&&($be_af_pos[$x])) {
						$be_af_pos[$x]=round($be_af_pos[$x]);
						db_modify("update styklister set antal = $be_af_ant[$x], posnr = $be_af_pos[$x] where id = '$be_af_id[$x]'");
					}
					else {
					db_modify("delete from styklister where id = '$be_af_id[$x]'");}
				}
				if (($be_af_vnr[0])||($be_af_beskrivelse[0])) {
					$be_af_pos[0]=round($be_af_pos[0]);
					if (($be_af_vnr[0])&&($be_af_beskrivelse[0])) $query = db_select("select id from varer where varenr = '$be_af_vnr[0]' or beskrivelse = '$be_af_beskrivelse[0]'");
					elseif ($be_af_vnr[0]) $query = db_select("select id from varer where varenr = '$be_af_vnr[0]'");
					elseif ($be_af_beskrivelse[0]) $query = db_select("select id from varer where beskrivelse = '$be_af_beskrivelse[0]'");
					if ($row = db_fetch_array($query)) {
						if (($row[id]==$id)||(in_array($row[id],$be_af_vare_id))) {}
						elseif (cirkeltjek($row[id])==0) {
							db_modify("insert into styklister (vare_id, indgaar_i, antal, posnr) values ('$row[id]', '$id', '1', '$be_af_pos[0]')");
							db_modify("update varer set delvare =  'on' where id = '$row[id]'");
						}
					}
					elseif (($be_af_vnr[0])&&($be_af_beskrivelse[0])) {
						if (!strpos($be_af_vnr[0],"*")) $be_af_vnr[0]="*".$be_af_vnr[0]."*";
						if (!strpos($be_af_beskrivelse[0],"*")) $be_af_beskrivelse[0]="*".$be_af_beskrivelse[0]."*";
						$fokus="varenr";
						$find="'".$be_af_vnr[0]."' and beskrivelse like '".$be_af_beskrivelse[0]."'";
					}
					elseif ($be_af_vnr[0]) {
						if (!strpos($be_af_vnr[0],"*")) $be_af_vnr[0]="*".$be_af_vnr[0]."*";
						$fokus="varenr";
						$find="'".$be_af_vnr[0]."'";
					}
					else {
						if (!strpos($be_af_beskrivelse[0],"*")) $be_af_beskrivelse[0]="*".$be_af_beskrivelse[0]."*";
						$fokus="beskrivelse";
						$find="'".$be_af_beskrivelse[0]."'";
					}
				}
				$kostpris[0]=fuld_stykliste($id,0,'')*1;
			
				db_modify("update varer set kostpris = '$kostpris[0]' where id = '$id'");

			}
/*
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
*/
#############################################################################################
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
	if (strstr($submit, "Leverand")) kontoopslag("navn", $fokus, $id, "", "", "", "");
	if (strstr($submit, "Vare")) {
		if (!$sort) $sort="varenr"; if (!$fokus) $fokus="varenr";
		vareopslag ($sort, $fokus, $id, $vis_kost, $ref, $find, "varekort.php");
	}
}
if (!$returside) $returside="../includes/luk.php";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
if (trim($returside)!='varer.php') print "<td width=\"10%\" $top_bund>$font<a href=$returside?id=$ordre_id&fokus=$fokus&varenr=$varenr&vare_id=$id accesskey=L>Luk</a></td>";
else print "<td width=\"10%\" onClick=\"javascript=opener.location.reload();\" $top_bund>$font <a href='../includes/luk.php?' accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">$font varekort</td>";
if ($id) print "<td width=\"10%\" $top_bund align=\"right\">$font<a href=varekort.php?returside=$returside&ordre_id=$id accesskey=N>Ny</a>";
print "</td></tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"50%\"><tbody>";

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
	$gruppe=$row['gruppe']*1;
	$serienr=$row['serienr'];
	$lukket=$row['lukket'];
	$notes=htmlentities(stripslashes($row['notes']));
	$delvare=$row['delvare'];
	$samlevare=$row['samlevare'];
	$min_lager=$row['min_lager'];
	$max_lager=$row['max_lager'];
	$beholdning=$row['beholdning']*1;
	$operation=$row['operation']*1;
#	$kpris=dkdecimal($row['kostpris']);

	$query = db_select("select * from grupper where art='VG' and kodenr = '$gruppe'");
	$row = db_fetch_array($query);
	$box8=$row['box8'];
	$box9=$row['box9'];
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
print "<tr><td>$font Salgspris</td><td><br></td><td><input type=text style=text-align:right size=10 name=salgspris value=\"$x\">$font /$enhed&nbsp;";
if (($enhed2)&&($forhold>0)) {
	$x=dkdecimal($salgspris/$forhold);
	print "<input type=text style=text-align:right size=10 name=salgspris2 value=\"$x\">$font /$enhed2";
}
print "</td></tr>";
$x=dkdecimal($kostpris[0]);

if ($samlevare!='on') {
	print "<tr><td>$font Kostpris</td><td><br></td><td><input type=text style=text-align:right size=10 name=kostpris[0] value=\"$x\">$font /$enhed&nbsp;";
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
			print "<td><span title='Pos = minus sletter leverandren';><input type=text size=1 name=lev_pos[$x] value=$x></span></td><td>$font $row[kontonr]:".htmlentities(stripslashes($row[firmanavn]))."</td><td><input type=text style=text-align:right size=9 name=lev_varenr[$x] value=$lev_varenr[$x]></td><td style=text-align:right><input type=text style=text-align:right size=9 name=kostpris[$x] value=\"$y\"></td>";
			if (($enhed2)&&($forhold>0)) {
				$y=dkdecimal($kostpris[$x]/$forhold);
				print "<td><input type=text style=text-align:right size=9 name=kostpris2[$x] value=\"$y\"></td>";
			}
			print "</td></tr>";
			print "<input type=hidden name=vare_lev_id[$x] value=$vare_lev_id[$x]>";
		}
		print "</tbody></table>";
	}
	else {
	}
}else print "<tr><td>$font Kostpris</td><td><br></td><td><input readonly=readonly style=text-align:right size=10 name=kostpris[0] value=\"$x\">$font /$enhed&nbsp;";

print "<tr><td>$font Varegruppe</td><td></td>";
if (!$gruppe){$gruppe=1;}
$r = db_fetch_array(db_select("select beskrivelse, box10 from grupper where art='VG' and kodenr='$gruppe'"));
if (($r['box10']=='on')&&(!$operation)) {
	$r2 = db_fetch_array(db_select("select MAX(operation) as max from varer where lukket !='on'"));
	$operation=$r2[max]+1;
}
print "<td colspan=3><SELECT NAME=gruppe value='$gruppe'>";
print "<option>$gruppe:$r[beskrivelse]</option>";
if (!$beholdning) { 
	if ($samlevare=='on') $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' and box8!='on' order by kodenr");
	else $query = db_select("select * from grupper where art='VG' and kodenr!='$gruppe' order by kodenr");
	while ($row = db_fetch_array($query)) {
		print "<option>$row[kodenr]:$row[beskrivelse]</option>";
	}
}
print "</SELECT></td></tr>";
if ($operation) print "<tr><td>$font Operation nr:</td><td></td><td><input type=text size=5 style=text-align:right name=operation value=$operation>";
elseif ($box8=='on'){
	print "<tr><td>$font Beholdning</td><td>$font min:</td><td><input type=text size=5 style=text-align:right name=min_lager value=$min_lager>";
	print "$font&nbsp;&nbsp;max:&nbsp;<input type=text size=5 style=text-align:right name=max_lager value=$max_lager>";
	print "&nbsp;&nbsp;aktuel: $beholdning</td></tr>";
}
if ($provisionsfri == 'on') {print "<tr><td>$font Provisionsfri</td><td></td><td><input type=checkbox name=provisionsfri checked></td>";}
else {print "<tr><td>$font Provisionsfri</td><td></td><td><input type=checkbox name=provisionsfri></td>";}
print "<tr><td valign=top>$font Bem&aelig;rkning</td><td><br></td><td colspan=9><textarea name=\"notes\" rows=\"3\" cols=\"60\">$notes</textarea></td></tr>";
print "<tr><td colspan=2></td><td colspan=3><table width=100% border=0><tbody><tr>";
if ($serienr == 'on') {print "<td width=25%>$font Serienr.&nbsp;<input type=checkbox name=serienr checked></td>";}
elseif  ($box9 == 'on') {print "<td width=25%>$font Serienr&nbsp;<input type=checkbox name=serienr></td>";}
if (($styklister)&&($box8!='on')&&(!$lev_id[1])) { # /* Udeladt intil test af vareflow er afsluttet (2006-03-03)
	if ($samlevare == 'on') {print "<td width=25%>$font Samlevare&nbsp;<input type=checkbox name=samlevare checked></td>";}
	else {print "<td width=25%>$font Samlevare&nbsp;<input type=checkbox name=samlevare></td>";}
#	if ($delvare == 'on') {print "<td width=25%>$font Delvare&nbsp;<input type=checkbox name=delvare checked></td>";}
#	else {print "<td width=25%>$font Delvare&nbsp;<input type=checkbox name=delvare></td>";}
}
if ($lukket==0) {print "<td width=25%>$font Udg&aring;et&nbsp;<input type=checkbox name=lukket></td>";}
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
		print "<input type=hidden name=be_af_vare_id[$x] value='$row[vare_id]'>";
		print "<input type=hidden name=be_af_vnr[$x] value='$be_af_vnr[$x]'>";
		print "<input type=hidden name=be_af_beskrivelse[$x] value='$be_af_beskrivelse[$x]'>";
	}
	$ant_be_af=$x;
}

if ($delvare=='on') {
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
	if ($x==0) {
		print "<input type=hidden name=delvare value=''>";
		$delvare='';
	}
	$ant_indg_i=$x;
}


if ($samlevare=='on') {
	$be_af_pos[0]=0;
	print "<tr><td valign=top><table width=20%><tbody><tr><td>$font <a href=stykliste.php?id=$id>Stykliste</a></td></tr><tr><td>$font <a href=fuld_stykliste.php?id=$id>Komplet</a></td></tr></tbody></table></td>";
	print "<td></td><td><table border=0 width=80%><tbody>";
	print "<tr><td>$font Pos.</td><td width=80>$font V.nr.</td><td width=300>$font Beskrivelse</td><td>$font Antal</td></tr>";
	for ($x=1; $x<=$ant_be_af; $x++){
		print "<tr><td><input type=text size=2 style=text-align:right name=be_af_pos[$x] value=$x></td><td>$font<a href=varekort.php?id=$be_af_id[$x]>$be_af_vnr[$x]</a></td><td>$font $be_af_beskrivelse[$x]</td><td>$font<input type=text size=2 style=text-align:right name=be_af_ant[$x] value=$be_af_ant[$x]>&nbsp;$be_af_enhed[$x]</td></tr>";
	}
	$be_af_pos[0]=$ant_be_af+1;
	print 	"<tr><td><input type=text size=2 style=text-align:right name=be_af_pos[0] value=$be_af_pos[0]></td>";
	print 	"<td><input type=text size=10 name=be_af_vnr[0] title='Indtast varenummer som skal tilf&oslash;jes styklisten'></td>";
	print 	"<td><input type=text size=60 name=be_af_beskrivelse[0] title='Indtast varebsekrivelse pï¿½vare som skal tilf&oslash;jes styklisten'></td></tr>";
	print "<input type=text size=2 style=text-align:right name=be_af_ant[0]</td></tr>";
/*
	print "<tr><td><input type=text size=2 name=be_af_pos[0]] value=$x></td><td colspan=2><SELECT NAME=be_af_ant[0]>";
	print "<option>$font $row[varenr]&nbsp;$font".substr($row[beskrivelse],0,60)."</option>";
	$query = db_select("select * from varer where id != $id order by varenr");
	while ($row = db_fetch_array($query)){
		if ((!in_array($row[id], $be_af_id))&&(!in_array($row[id], $indg_i_id))){print "<option>$font$row[varenr] : $font".substr($row[beskrivelse],0,60)."</option>";}
	}
	print "</SELECT></td>";
*/
	print "</tr></tbody></table></td></tr>";
	print "<input type=hidden name=ant_be_af value='$ant_be_af'>";

}


if ($delvare=='on') {
	if ($vis_samlevarer) {
		print "<tr><td valign=top width=10%>$font<span title='Klik her for at lukke oversigten'><a href=varekort.php?id=$id&returside=$returside>Indg&aring;r i</a></td><td></td><td><table width=80% border=0><tbody>";
		print "<tr><td>$font Pos.</td><td width=80>$font V.nr.</td><td width=300>$font Beskrivelse</td><td>$font Antal</td></tr>";
		for ($x=1; $x<=$ant_indg_i; $x++) {
			print "<tr><td><input type=text size=2 name=indg_i_ant[$x] value=$x></td><td>$font<a href=varekort.php?id=$indg_i_id[$x]>$indg_i_vnr[$x]</a></td><td>$font $indg_i_beskrivelse[$x]</td><td>$font<input type=text size=2 name=indg_i_ant[$x] value=$indg_i_ant[$x]>&nbsp;$indg_i_enhed[$x]</td></tr>";
		}
	} else { 
		print "<tr><td colspan=3><table width=100% border=1><tbody>";
		print "<tr><td width=100% align=center>$font<a href=varekort.php?id=$id&returside=$returside&vis_samlevarer=on>Denne vare indg&aring;r i andre varer - Klik for oversigt</a></td></tr>";

	}
	
/*
	print "<tr><td><input type=text size=2 name=indg_i_ant[$x] value=$x></td><td colspan=2><SELECT NAME=indg_i_ant[0]>";
	print "<option>$font $row[varenr]&nbsp;$font".substr($row[beskrivelse],0,60)."</option>";
	$query = db_select("select * from varer where id != $id order by varenr");
	while ($row = db_fetch_array($query)) {
		if ((!in_array($row[id], $be_af_id))&&(!in_array($row[id], $indg_i_id))){print "<option>$font $row[varenr] : $font".substr($row[beskrivelse],0,60)."</option>";}
	}	
	print "</SELECT></td>";
*/
	print "</tr></tbody></table></td></tr>";
}

 print "<tr><td colspan=4><hr></td></tr>";
print "<tr><td colspan=4 align=center><table width=100%><tbody>";

print "<input type=hidden name=ant_indg_i value='$ant_indg_i'>";
print "<input type=hidden name=returside value='$returside'>";
print "<input type=hidden name=fokus value='$fokus'>";
print "<input type=hidden name=ordre_id value='$ordre_id'>";
print "<input type=hidden name=delvare value='$delvare'>";
print "<input type=hidden name=gl_kostpris value='$kostpris[0]'>";

print "<tr><td align = center><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\"></td>";

if (($varenr)&&($samlevare=='on')) print "<td align = center><input type=submit title='Inds&aelig;t varer i stykliste' accesskey=\"l\" value=\"Vareopslag\" name=\"submit\"></td>";
elseif ($varenr) print "<td align = center><input type=submit accesskey=\"l\" value=\"Leverand&oslash;ropslag\" name=\"submit\"></td>";

if ($id) {
	$query = db_select("select id from ordrelinjer where vare_id = $id");
	if ((!$row = db_fetch_array($query))&&($lev_ant < 1)) {
	 print "<td align=center><input type=submit value=\"Slet\" name=\"submit\"></td>";
	}
}

print "</tr></tbody></table></td></tr>";
print "</tr></tbody></table></td></tr>";

function prisopdat($id, $diff) {
	global $connect;

	$x=0;
	$y=0;
	$q1 = db_select("select * from styklister where vare_id =$id");
	while ($r1 = db_fetch_array($q1)) {
		$x++;
		$indgaar_i[$x]=$r1[indgaar_i];
		$belob=$r1[antal]*$diff;
echo "update varer set kostpris=kostpris+$belob where id=$indgaar_i[$x]<br>";
		db_modify("update varer set kostpris=kostpris+$belob where id=$indgaar_i[$x]");
	}
	$y=$x;
	for ($y=1; $y<=$x; $y++) {
		$q1 = db_select("select * from styklister where vare_id=$indgaar_i[$y]");
		while ($r1 = db_fetch_array($q1)) {
			if ($row[indgaar_i]!=$id) {
				$x++;
				$vare_id[$x]=$r1[id];
				$indgaar_i[$x]=$r1[indgaar_i];
				$antal[$x]=$r1[antal];
echo "update varer set kostpris=kostpris+$diff*$antal[$x] where id=$vare_id[$x]<br>";
				db_modify("update varer set kostpris=kostpris+$diff*$antal[$x] where id=$vare_id[$x]");
			} else {
				$r2 = db_fetch_array(db_select("select varenr from varer where id=$vare_id[$y]"));
				db_modify("delete from styklister where id=$r1[id]");
				print "<BODY onLoad=\"javascript:alert('Cirkul&aelig;r reference registreret varenr.: $r2[varenr] fjernet fra styklisten')\">";
			}
		}
	}
}

/*
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
		if ($kostpris>0) {
		db_modify("update varer set kostpris='$kostpris' where id=$vare_id[$a]");
		}
	}
	for ($a=1; $a<=$vareantal; $a++)	{
	}
}
*/
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

function cirkeltjek($vare_id) 
{
	global $id;
	$x=0;
	$fejl=0;
	$query = db_select("select styklister.vare_id as vare_id, varer.samlevare as samlevare from styklister, varer where indgaar_i=$vare_id and varer.id=$vare_id");
	while ($row = db_fetch_array($query)){
		if ($id==$row[vare_id]) {
			print "<BODY onLoad=\"javascript:alert('Cirkulï¿½ reference registreret')\">";
			$x=0;
			$fejl=1;
			break;
		} elseif (($row['samlevare']=='on') && ($fejl!=1)) {
			$x++;
			$s_vare_id[$x]=$row[vare_id];
		}
	}
	for ($a=1; $a<=$x; $a++)	{
		$query = db_select("select styklister.vare_id as vare_id, varer.samlevare as samlevare from styklister, varer where indgaar_i=$s_vare_id[$a] and varer.id=$s_vare_id[$a]");
		while ($row = db_fetch_array($query)) {
			if ($id==$row[vare_id]) {
				print "<BODY onLoad=\"javascript:alert('Cirkulï¿½ reference registreret')\">";
				$a=$x;
				$fejl=1;
				break;
			} elseif (($row['samlevare']=='on') && ($fejl!=1)) {
				$x++;
				$s_vare_id[$x]=$row[vare_id];
			}
		}
	}
	if ($fejl>0) return $fejl;
}

######################################################################################################################################
function kontoopslag($sort, $fokus, $id, $tmp, $tmp, $tmp, $tmp )
{
	global $bgcolor2;
	global $font;
	global $top_bund;
	global $returside;
	global $ordre_id;
	global $fokus;
	global $vare_lev_id;
		
	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0	\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr><td colspan=8>";
	print "		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "			<td width=\"10%\" $top_bund>$font<a href=varekort.php?returside=$returside&ordre_id=$ordre_id&vare_id=$id&id=$id&fokus=$fokus accesskey=L>Luk</a></td>";
	print "			<td width=\"80%\" $top_bund align=\"center\">$font varekort</td>";
	print "<td width=\"10%\" $top_bund align=\"right\" onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"JavaScript:window.open('../kreditor/kreditorkort.php?returside=../includes/luk.php', '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,resizable=yes');\">$font<u>Ny</u></td>";
	print "		</tbody></table></td></tr>";

	print"<td><b>$font<a href=varekort.php?sort=kontonr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Kundenr</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=firmanavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Navn</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=addr1&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Adresse</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=addr2&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Adresse2</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=postnr&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Postnr</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=bynavn&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>bynavn</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=land&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>land</b></td>";
#	print"<td><b>$font<a href=varekort.php?sort=kontakt&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Kontaktperson</b></td>";
	print"<td><b>$font<a href=varekort.php?sort=tlf&funktion=kontoOpslag&id=$id&returside=$returside&ordre_id=$ordre_id&vare_id=$id&$fokus=$fokus>Telefon</b></td>";
	print" </tr>";


	 $sort = $_GET['sort'];
	 if (!$sort) {$sort = firmanavn;}

	$query = db_select("select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' order by $sort");
	while ($row = db_fetch_array($query))
	{
		$kontonr=str_replace(" ","",$row['kontonr']);
		print "<tr>";
		print "<td>$font<a href=varekort.php?fokus=$fokus&id=$id&konto_id=$row[id]&returside=$returside&ordre_id=$ordre_id&vare_lev_id=$vare_lev_id>$row[kontonr]</a></td>";
		print "<td>$font$row[firmanavn]</td>";
		print "<td>$font$row[addr1]</td>";
		print "<td>$font$row[addr2]</td>";
		print "<td>$font$row[postnr]</td>";
		print "<td>$font$row[bynavn]</td>";
		print "<td>$font$row[land]</td>";
#		print "<td>$font$row[kontakt]</td>";
		print "<td>$font$row[tlf]</td>";
		print "</tr>";
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}

?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><br></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
