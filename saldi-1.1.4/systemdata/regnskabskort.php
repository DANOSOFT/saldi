<?php
// ------------------------------------------------------------systemdata/regnskabskort.php	--------- lap 1.1.0 -----------------------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------
@session_start();
$s_id=session_id();
	
$modulnr=2;
$title="Regnskabskort";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/db_query.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/genberegn.php");
	 

Print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>"; ####################table 1a start.
Print "<tr><td align=center valign=top>";
Print "<table width=100% align=center border=0 cellspacing=2 cellpadding=0><tbody>"; ##############table 2b start
Print "<td width=10% $top_bund>$font <small><a href=regnskabsaar.php accesskey=L>Luk</a></small></td>";
Print "<td width=80% $top_bund>$font <small>Regnskabskort</small></td>";
Print "<td width=10% $top_bund>$font <small><a href=regnskabskort.php accesskey=N>Ny</a><br></small></td>";
Print "</tbody></table>"; #####################################################table 2b slut.
Print "</td></tr>";
Print "<td align = center valign = center>";
Print "<table cellpadding=1 cellspacing=1 border=1><tbody>"; ############################	##table 3b start



$id=$_GET['id'];

if ($_POST) {
	$id=$_POST['id'];
	$beskrivelse=$_POST['beskrivelse'];
	$kodenr=$_POST['kodenr'];
	$kode=$_POST['kode'];
	$startmd=$_POST['startmd'];
	$startaar=$_POST['startaar'];
	$slutmd=$_POST['slutmd'];
	$slutaar=$_POST['slutaar'];
	$aaben=trim($_POST['aaben']);
	$fakt=$_POST['fakt']*1;
	$modt=$_POST['modt']*1;
	$no_faktbill=trim($_POST['no_faktbill']);
	$faktbill=trim($_POST['faktbill']);
	$modtbill=trim($_POST['modtbill']);
	$kontoantal=$_POST['kontoantal'];
	$kontonr=$_POST['kontonr'];
	$debet=$_POST['debet'];
	$kredit=$_POST['kredit'];
	$saldo=$_POST['saldo'];
	$overfor_til=$_POST['overfor_til'];
#		$primotal=$_POST['primotal'];
	$aar=date(Y);
	$topaar=$aar+10;
	$bundaar=$aar-10;
	$fejl=0;
	$startmd=$startmd*1;
	$startaar=$startaar*1;
	$slutmd=$slutmd*1;
	$slutaar=$slutaar*1;
	
	if (!$beskrivelse){
		Print "<BODY onLoad=\"javascript:alert('Beskrivelse ikke angivet. S&aelig;ttes til $aar!')\">";
		$beskrivelse="$aar";
	}
	if (($startmd<1)||($startmd>12)){
		Print "<BODY onLoad=\"javascript:alert('Startm&aring;ned skal v&aelig;re mellem 1 og 12!')\">";
		$startmd="";
	}
	elseif ($startmd<10){$startmd="0".$startmd;};
	if (($slutmd<1)||($slutmd>12)){
		Print "<BODY onLoad=\"javascript:alert('Slutm&aring;ned skal v&aelig;re mellem 1 og 12!')\">";
		$slutmd="";
	}
	elseif ($slutmd<10){$slutmd="0".$slutmd;};
	if (($startaar<$bundaar)||($startaar>$topaar)){
		print "<BODY onLoad=\"javascript:alert('Start&aring;r skal v&aelig;re mellem $bundaar og $topaar!')\">";
		$startaar="";
	}
	if (($slutaar<$bundaar)||($slutaar>$topaar)){
		print "<BODY onLoad=\"javascript:alert('Slut&aring;r skal v&aelig;re mellem $bundaar og $topaar!')\">";
		$slutaar="";
	}
	$startdato=$startaar.$startmd;
	$slutdato=$slutaar.$slutmd;
	if ($slutdato<=$startdato){
		Print "<BODY onLoad=\"javascript:alert('Regnskabs&aring;r skal slutte senere end det starter')\">";
		$aaben="";
	}

	if ((($id!=0)||(!db_fetch_array(db_select("select id from grupper where kodenr = '$kodenr' and art = 'RA'"))))&&(($startmd)&&($slutmd)&&($startdato)&&($slutdato)&&($startaar)&&($slutaar)&&($beskrivelse))) {
		transaktion("begin");
		if ($id==0){
			db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('$beskrivelse', '$kodenr', '$kode', 'RA', '$startmd','$startaar', '$slutmd', '$slutaar','$aaben')");
			$query = db_select("select id from grupper where kodenr = '$kodenr' and art = 'RA'");
			$row = db_fetch_array($query);
			$id = $row[id];
		}
		if ($kodenr==1) {
			for ($x=1; $x<=$kontoantal; $x++) {
				$sum=0;
				if ($debet[$x]) {$sum= $sum+usdecimal($debet[$x]);}
				if ($kredit[$x]) {$sum= $sum - usdecimal($kredit[$x]);}
				db_modify ("update kontoplan set primo='$sum' where kontonr='$kontonr[$x]' and regnskabsaar=1");
			}
				$query = db_select("select * from grupper where art = 'RB'");
				if (db_fetch_array($query)) {db_modify("update grupper set box1 = '$fakt', box2 = '$modt', box3 = '$faktbill', box4 = '$modtbill', box5 = '$no_faktbill' where art = 'RB'");}
				else {db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('Regnskabsbilag', '1', '1', 'RB', '$fakt', '$modt', '$faktbill', '$modtbill', '$no_faktbill')");}
		} 
		if (($id>0)&&($kodenr>0)) {
			db_modify("update grupper set beskrivelse = '$beskrivelse', kodenr = '$kodenr', kode = '$kode', box1 = '$startmd', box2 = '$startaar', box3 = '$slutmd', box4 = '$slutaar', box5 = '$aaben' where id = '$id'");
			if ($kodenr==1){
				for ($x=1; $x<=$kontoantal; $x++) {
					if ($saldo[$x]&&$overfor_til[$x]) db_modify ("update kontoplan set primo=primo+$saldo[$x] where kontonr='$kontonr[$x]' and regnskabsaar=$kodenr");
				}
			}
			else {
				$query = db_select("select id from kontoplan where regnskabsaar=$kodenr");
				if($row = db_fetch_array($query)) {
					db_modify ("update kontoplan set primo='0' where  regnskabsaar=$kodenr");
					for ($x=1; $x<=$kontoantal; $x++) {
						db_modify ("update kontoplan set overfor_til=$overfor_til[$x] where kontonr='$kontonr[$x]' and regnskabsaar=$kodenr-1");
						db_modify ("update kontoplan set primo=primo+$saldo[$x] where kontonr='$overfor_til[$x]' and regnskabsaar=$kodenr");
					}
				}
				else {
					$query = db_select("select * from kontoplan where regnskabsaar=$kodenr-1 order by kontonr");
					$y=0;
					while ($row = db_fetch_array($query)) {
						if ($row[kontotype]=="S") { 
						$belob=$row[saldo];
						} else $belob='0';
						if (!$belob) $belob='0';
						if (!$row[fra_kto]) $row[fra_kto]='0';
						if (!$row[til_kto]) $row[til_kto]='0';
						if (!$row[overfor_til]) $row[overfor_til]='0';
						db_modify("insert into kontoplan(kontonr, beskrivelse, kontotype, moms, fra_kto, til_kto, lukket, primo, regnskabsaar, overfor_til)values('$row[kontonr]',  '$row[beskrivelse]', '$row[kontotype]', '$row[moms]', '$row[fra_kto]', '$row[til_kto]', '$row[lukket]', '$belob', '$kodenr', '$row[overfor_til]')");
					}
				}	
			}
		}
		transaktion("commit");
	}
}
if ($id > 0) {
	$query = db_select("select * from grupper where id = '$id' and art = 'RA'");
	if ($row = db_fetch_array($query)) {
		genberegn($row[kodenr]);
		if ($row['kodenr']==1){aar_1($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5']);}
		else
		{
			aar_x($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5']);
		}
	}
} else {
	$x=0;
	$query = db_select("select * from grupper where art = 'RA' order by kodenr desc");
	if ($row = db_fetch_array($query)) {
		if ($x <= $row[kodenr]) $x=$row[kodenr]+1;
		if ($row[box3]==12) {
			$startmd=1;
			$startaar=$row[box4]+1;
		} else {
			$startmd=$row[box3]+1;
			$startaar=$row[box4];
		}
		$slutmd=$row[box3];
		$slutaar=$row[box4]+1;
	} else {
		$beskrivelse=date(Y);
		$startaar=date(Y);
		$startmd='01';
		$slutaar=date(Y);
		$slutmd='12';
		$aaben='on';
	}
	if ($x==0) aar_1($id, '1', $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben);
	else aar_x($id, $x, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben);
}

function aar_1($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben)
{
	global $font;
	$row = db_fetch_array(db_select("select MAX(kodenr) as kodenr from grupper where art = 'RA'"));
	if ($row[kodenr] > $kodenr) $laast=1;  
	if ($row = db_fetch_array(db_select("select * from grupper where art = 'RB' order by kodenr"))) {
		$fakt=$row[box1]*1;
		$modt=$row[box2]*1;
		$faktbill=trim($row[box3]);
		$modtbill=trim($row[box4]);
		$no_faktbill=trim($row[box5]);
	
	} else {
		$fakt='1';
		$modt='1';
		$no_faktbill='on';
		$modtbill='on';
	}
	if (!$fakt) $fakt='1';
	if (!$modt) $modt='1';
	
	print "<form name=aar_x action=regnskabskort.php method=post>";
	if ($id){print "<tr><td colspan=4 align = center>$font<big><b>Ret 1. regnskabs&aring;r: $beskrivelse</td></tr>";}
	else {print "<tr><td colspan=4 align = center>$font<big><b>Opret 1. regnskabs&aring;r: $beskrivelse</td></tr>";}
	print "<tr><td colspan=4 align=center><table width=100% border=0><tbody><tr>"; #########################table 4c start
	print "<tr><td></td><td align=center>$font<small>Start</td><td align=center>$font<small>Start</td><td align=center>$font<small>Slut</td><td align=center>$font<small>Slut</td><td align=center>$font<small>Bogf&oslash;ring</td></tr>";
	print "<tr><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>m&aring;ned</td><td align=center>$font<small>&aring;r</td><td align=center>$font<small>m&aring;ned</td><td align=center>$font<small>&aring;r</td><td align=center>$font<small>tilladt</tr>";
	print "<input type=hidden name=kodenr value=1><input type=hidden name=id value='$id'	>";
	print "<tr><td align=center>$font<input type=text size=30 name=beskrivelse value='$beskrivelse'></td>";
	if ($laast) $type="readonly=readonly";
	else $type="type=text";
	print "<td align=center>$font<input $type align=right size=2 name=startmd value=$startmd></td>";
	print "<td align=center>$font<input $type align=right size=4 name=startaar value=$startaar></td>";
	print "<td align=center>$font<input $type align=right size=2 name=slutmd value=$slutmd></td>";
	print "<td align=center>$font<input $type align=right size=4 name=slutaar value=$slutaar></td>";
	if (strstr($aaben,'on')) {print "<td align=center>$font<input type=checkbox name=aaben checked></td>";}
	else {print "<td align=center>$font<input type=checkbox name=aaben></td>";}
	print "</tr></tbody></table></td></tr>"; ###################################################table 4c slut
	print "<tr><td colspan=4 width=100% align=center><table heigth=100% border=0><tbody>"; ###########################table 5c start
	print "<td align=center><table heigth=100% border=1><tbody><td>";  #################################table 6d start	print "<tr><td align=center>$font<small>1. faktnr</td><td align=center>$font<small>1. modt. nr.</td><tr>";
	print "<tr><td align=center>$font<small>1.&nbsp;faktnr</td><td align=center>$font<small>1.&nbsp;modt.nr</td><tr>";  
	print "<tr><td align=center>$font<input type=text align=right size=4 name=fakt value=$fakt></td>";
	print "<td align=center>$font<input type=text align=right size=4 name=modt value=$modt></td>";
	print "</td></tbody></table></td>"; ##########################################################table 6d slut
	print "<td><table border=1><tbody><td>"; ##############################################table 7d start
	if ($no_faktbill) $no_faktbill="checked"; 
	if ((!$no_faktbill)&&($faktbill)) $faktbill="checked"; 
	if ($modtbill) $modtbill="checked";
	print "<tr><td align=center>$font<small>Unlad bilagsnr. til fakt.</td><td align=center>$font<input type=checkbox name=no_faktbill $no_faktbill></td></tr>";
	print "<tr><td align=center>$font<small>Brug fakt.nr. som bilagsnr.</td><td align=center>$font<input type=checkbox name=faktbill $faktbill></td></tr>";
	print "<tr><td align=center>$font<small>Brug modt.nr. som bilagsnr.</td><td align=center>$font<input type=checkbox name=modtbill $modtbill></td></tr>";
	print "</td></tbody></table></td>"; ##########################################################table 7d slut
	print "<td><table border=0><tbody>"; ##############################################table 8d start
	print "<td><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\"></td></tr>";
	print "</tbody></table></td></tr>";#####################################################table8d slut
	print "</td></tbody></table></td></tr>";#####################################################table5c slut
	print "<tr><td colspan=2 align=center>$font Indtast primotal for 1. regnskabs&aring;r:</td><td align = center>debet</td><td align = center>kredit</td></tr>";
	$query = db_select("select id, kontonr, primo, beskrivelse from kontoplan where kontotype='S' and regnskabsaar='1' order by kontonr");
	$y=0;
	$debetsum=0;
	$kreditsum=0;
	while ($row = db_fetch_array($query)) {
		$y++;
		print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]>";
		$debet[$y]="0,00";
		$kredit[$y]="0,00";
		if ($row[primo]>0) {
			$debet[$y]=dkdecimal($row[primo]);
			$debetsum=$debetsum+$row[primo];
		}
		elseif ($row[primo]<0) {
			$kredit[$y]=dkdecimal($row[primo]*-1);
			$kreditsum=$kreditsum+($row[primo]*-1);
		}
		print "<td>$font $row[kontonr]</td>";
		print "<td>$font $row[beskrivelse]</td>";
		print "<td width=10 align=right>$font<input type=text align=right size=10 name=debet[$y] value=$debet[$y]></td>";
		print "<td width=10 align=right>$font<input type=text align=right size=10 name=kredit[$y] value=$kredit[$y]></td></tr>";
	}
	print "<td></td><td></td><td align=right>$font".dkdecimal($debetsum)."</td><td align=right>$font".dkdecimal($kreditsum)."</td></tr>";
	if (abs($debetsum-$kreditsum)>0.009) {print "<BODY onLoad=\"javascript:alert('Konti er ikke i balance')\">";}
	
#	print "<tr><td colspan = 3>$font Overfr ï¿½ningsbalance</td><td align=center><input type=checkbox name=primotal checked></td></tr>";
	print "<input type=hidden name=kontoantal value=$y>";
	print "<tr><td colspan = 4 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td></tr>";
	print "</form>";
	exit;
}

function aar_x($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben)
{
	global $font;
	global $overfor_til;
	
	$tmp=$kodenr-1;
	$query = db_select("select * from grupper where art = 'RA' and kodenr = '$tmp'");
	if ($row = db_fetch_array($query)) {
		$pre_startmd=$row['box1'];
		$pre_startaar=$row['box2'];
		$pre_slutmd=$row['box3'];
		$pre_slutaar=$row['box4'];
	}
	
	$pre_slutdato=31;
	while (!checkdate($pre_slutmd, $pre_slutdato, $pre_slutaar)) {
		$pre_slutdato=$pre_slutdato-1;
		if ($pre_slutdato<28) break;
	}
	$pre_regnstart = $pre_startaar. "-" . $pre_startmd . "-" . '01';
	$pre_regnslut = $pre_slutaar . "-" . $pre_slutmd . "-" . $pre_slutdato;

	print "<form name=aar_1 action=regnskabskort.php method=post>";
	if ($id){print "<tr><td colspan=5 align = center>$font<big><b>Ret $kodenr. regnskabs&aring;r: $beskrivelse</td></tr>";}
	else {print "<tr><td colspan=5 align = center>$font<big><b>Opret $kodenr. regnskabs&aring;r: $beskrivelse</td></tr>";}
	print "<tr><td colspan=5 align=center><table width=100% border=0><tbody><tr>"; ###########################table 8d start
	print "<tr><td></td><td align=center>$font<small>Start</td><td align=center>$font<small>Start</td><td align=center>$font<small>Slut</td><td align=center>$font<small>Slut</td><td align=center>$font<small>Bogf&oslash;ring</td></tr>";
	print "<tr><td align=center>$font<small>Beskrivelse</td><td align=center>$font<small>m&aring;ned</td><td align=center>$font<small>&aring;r</td><td align=center>$font<small>m&aring;ned</td><td align=center>$font<small>&aring;r</td><td align=center>$font<small>tilladt</tr>";
	print "<input type=hidden name=kodenr value=$kodenr><input type=hidden name=id value='$id'	>";
	print "<td align=center>$font<input type=text size=30 name=beskrivelse value=$beskrivelse></td>";
	print "<td align=center>$font<input readonly=readonly align=right size=2 name=startmd value=$startmd></td>";
	print "<td align=center>$font<input readonly=readonly align=right size=4 name=startaar value=$startaar></td>";
	print "<td align=center>$font<input type=text align=right size=2 name=slutmd value=$slutmd></td>";
		print "<td align=center>$font<input type=text align=right size=4 name=slutaar value=$slutaar></td>";
	if (strstr($aaben,'on')) {print "<td align=center>$font<input type=checkbox name=aaben checked></td>";}
	else {print "<td align=center>$font<input type=checkbox name=aaben></td>";}
	print "</tr></tbody></table></td></tr>"; #####################################################table 8d slut
	print "<tr><td colspan=2 align=center>$font Primotal for $kodenr. regnskabs&aring;r:</td><td align = center>$font saldo</td><td align = center>$font overf&oslash;r til</td><td align = center>$font ny primo</td></tr>";
	$tmp=$kodenr;
	$kontoantal=0;
	while ($kontoantal<1&&$tmp>0){ #Hvis der ikke er oprettet konti for indeværende regsskabsår, hentes konti fra forrige.
		$query = db_select("select primo, kontonr from kontoplan where kontotype='S' and regnskabsaar='$tmp' order by kontonr");
		while ($row = db_fetch_array($query)) {
			$kontoantal++;
			$ny_primo[$kontoantal]=$row['primo'];
			$kontonr[$kontoantal]=$row['kontonr'];
		} 
		$tmp--;
	}
	$tmp=$kodenr-1;
	
	$query = db_select("select * from kontoplan where kontotype='S' and regnskabsaar=$tmp order by kontonr");
	$y=0;
	while ($row = db_fetch_array($query)) {
		$y++;
		$belob=0;
		$belob=$row['primo'];
		print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]>";
		$q2 = db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$row[kontonr]'");
		while ($r2 = db_fetch_array($q2)) {
			 $belob=$belob+$r2[debet]-$r2[kredit];
		}
		$saldosum=$saldosum+$belob;
		print "<td>$font $row[kontonr]</td>";
		print "<td>$font $row[beskrivelse]</td>";
		print "<td width=10 align=right>$font<input type=hidden name=saldo[$y] value=$belob>".dkdecimal($belob)."</td>";
		print "<td><SELECT NAME=overfor_til[$y]>";
		if ($row[overfor_til]) print "<option>$row[overfor_til]</option>";  
		else print "<option>$kontonr[$y]</option>";
		for ($x=1;$x<=$kontoantal;$x++) print "<option>$kontonr[$x]</option>";
		print "</SELECT></td>";
		print "<td width=10 align=right>$font<input type=hidden name=ny_primo[$y] value=$ny_primo[$y]>$font".dkdecimal($ny_primo[$y])."</td></tr>";
	}
	print "<td></td><td></td><td align=right>$font".dkdecimal($saldosum)."</td><td align=right>$font".dkdecimal($kreditsum)."</td></tr>";
	if ($debetsum-$kreditsum!=0) {print "<BODY onLoad=\"javascript:alert('Konti er ikke i balance')\">";}
#	print "<tr><td colspan = 3>$font Overfr ï¿½ningsbalance</td><td align=center><input type=checkbox name=primotal checked></td></tr>";
	print "<input type=hidden name=kontoantal value=$y>";
	print "<tr><td colspan = 4 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td></tr>";
	print "</form>";
	exit;
}
######################################################################################################################################
print "</tbody></table></td></tr>";# table 3b slut
print "</tbody></table></td></tr>";# table 1a slut

?>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="#ffcc00"><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Copyright (C) 2004 DANOSOFT ApS</small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
