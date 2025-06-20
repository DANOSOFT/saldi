<?php
// ----systemdata/regnskabskort.php ---------------- lap 3.5.5 -- 2015-01-02 --
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1
// 2015-01-02 Tilrettet til dynamisk lagerværdi. Søg find_lagervaerdi
// 20150327 CA  Topmenudesign tilføjet søg 20150327

@session_start();
$s_id=session_id();

$laast=NULL;
	
$modulnr=2;
$title="Regnskabskort";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");
/*
if ($menu=='T') {  # 20150327 start
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">\n";
        print "<div class=\"headerbtnLft\"></div>\n";
        print "</div><!-- end of header -->";
        print "<div id=\"leftmenuholder\">";
        include_once 'left_menu.php';
        print "</div><!-- end of leftmenuholder -->\n";
        print "<div class=\"maincontent\">\n";
        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>";
} else {
        include("top.php");
        print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>";
}  # 20150327 stop
*/

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

print "<table width=100% height=100% border=0 cellspacing=0 cellpadding=0><tbody>"; ####################table 1a start.
print "<tr><td align=center valign=top>";
print "<table width=100% align=center border=0 cellspacing=4 cellpadding=0><tbody>\n"; ##############table 2b start
print "<tr>\n";

if ($menu=='T') { # 20150327 stare
	print "<td align=\"center\"><b>Regnskabskort</b></td>\n";
} else {
	$tekst=findtekst(154,$sprog_id);
	print "<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"javascript:confirmClose('regnskabsaar.php','$tekst')\" accesskey=L>Luk</a></div></td>\n";
	print "<td width=80% align=center><div class=\"top_bund\">Regnskabskort</div></td>\n";
	print "<td width=\"10%\" align=center><div class=\"top_bund\">&nbsp;</div></td>\n";
} # 20150327 stop
print "</tr>\n";
print "</tbody></table>\n"; #####################################################table 2b slut.
print "</td></tr>\n";
print "<tr>\n";
print "<td align = center valign = center>";
print "<table cellpadding=0 cellspacing=0 border=1><tbody>\n"; ############################	##table 3b start

$id=$_GET['id'];

if ($_POST) {
	transaktion ("begin");
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
	$aar=date("Y");
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

	if ((($id!=0)||(!db_fetch_array(db_select("select id from grupper where kodenr = '$kodenr' and art = 'RA'",__FILE__ . " linje " . __LINE__))))&&(($startmd)&&($slutmd)&&($startdato)&&($slutdato)&&($startaar)&&($slutaar)&&($beskrivelse))) {
		transaktion("begin");
		if ($id==0){
			db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('".db_escape_string($beskrivelse)."', '$kodenr', '$kode', 'RA', '$startmd','$startaar', '$slutmd', '$slutaar','$aaben')",__FILE__ . " linje " . __LINE__);
			$query = db_select("select id from grupper where kodenr = '$kodenr' and art = 'RA'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$id = $row['id'];
#			if (!$)
			
		}
		if ($kodenr==1) {
			for ($x=1; $x<=$kontoantal; $x++) {
				$sum=0;
				if ($debet[$x]) $sum+=usdecimal($debet[$x]);
				if ($kredit[$x]) $sum-=usdecimal($kredit[$x]);
				db_modify ("update kontoplan set primo='$sum' where kontonr='$kontonr[$x]' and regnskabsaar=1",__FILE__ . " linje " . __LINE__);
			}
				$query = db_select("select * from grupper where art = 'RB'",__FILE__ . " linje " . __LINE__);
				if (db_fetch_array($query)) {db_modify("update grupper set box1 = '$fakt', box2 = '$modt', box3 = '$faktbill', box4 = '$modtbill', box5 = '$no_faktbill' where art = 'RB'",__FILE__ . " linje " . __LINE__);}
				else {db_modify("insert into grupper (beskrivelse, kodenr, kode, art, box1, box2, box3, box4, box5) values ('Regnskabsbilag', '1', '1', 'RB', '$fakt', '$modt', '$faktbill', '$modtbill', '$no_faktbill')",__FILE__ . " linje " . __LINE__);}
		} 
		if (($id>0)&&($kodenr>0)) {
			db_modify("update grupper set beskrivelse = '".db_escape_string($beskrivelse)."', kodenr = '$kodenr', kode = '$kode', box1 = '$startmd', box2 = '$startaar', box3 = '$slutmd', box4 = '$slutaar', box5 = '$aaben' where id = '$id'",__FILE__ . " linje " . __LINE__);
			if ($kodenr==1){
				for ($x=1; $x<=$kontoantal; $x++) {
					if ($saldo[$x] && $overfor_til[$x]) db_modify ("update kontoplan set primo=primo+$saldo[$x],overfor_til=$overfor_til[$x] where kontonr='$kontonr[$x]' and regnskabsaar=$kodenr",__FILE__ . " linje " . __LINE__);
				}
			} else {
				$query = db_select("select id from kontoplan where regnskabsaar=$kodenr",__FILE__ . " linje " . __LINE__);
				if($row = db_fetch_array($query)) {
					db_modify ("update kontoplan set primo='0' where  regnskabsaar=$kodenr",__FILE__ . " linje " . __LINE__);
					for ($x=0; $x<=$kontoantal; $x++) {
						$overfor_til[$x]=$overfor_til[$x]*1;
						$kontonr[$x]=$kontonr[$x]*1;
						if ($overfor_til[$x]) {
							$saldo[$x]*=1; #phr 20110605
							db_modify ("update kontoplan set overfor_til='$overfor_til[$x]' where kontonr='$kontonr[$x]' and (regnskabsaar=$kodenr-1 or regnskabsaar='$kodenr')",__FILE__ . " linje " . __LINE__);
#cho "update kontoplan set overfor_til='$overfor_til[$x]' where kontonr='$kontonr[$x]' and regnskabsaar=$kodenr-1 or regnskabsaar=$kodenr<br>";		
							db_modify ("update kontoplan set primo=primo+'$saldo[$x]' where kontonr='$overfor_til[$x]' and regnskabsaar=$kodenr",__FILE__ . " linje " . __LINE__);
						}
					}
				}
				else {
					$query = db_select("select * from kontoplan where regnskabsaar=$kodenr-1 order by kontonr",__FILE__ . " linje " . __LINE__);
					$y=0;
					while ($row = db_fetch_array($query)) {
						if ($row[kontotype]=="S") { 
						$belob=$row['saldo'];
						} else $belob='0';
						if (!$belob) $belob='0';
						if (!$row['fra_kto']) $row['fra_kto']='0';
						if (!$row['til_kto']) $row['til_kto']='0';
						if (!$row['overfor_til']) $row['overfor_til']='0';
						db_modify("insert into kontoplan(kontonr,beskrivelse,kontotype,moms,fra_kto,til_kto,lukket,primo,regnskabsaar,overfor_til,genvej)values('$row[kontonr]','".db_escape_string($row['beskrivelse'])."','$row[kontotype]','$row[moms]','$row[fra_kto]','$row[til_kto]','$row[lukket]','$belob','$kodenr','$row[overfor_til]','$row[genvej]')",__FILE__ . " linje " . __LINE__);
					}
				}	
			}
		}
		transaktion("commit");
	}
}
if ($id > 0) {
	$query = db_select("select * from grupper where id = '$id' and art = 'RA'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		genberegn($row['kodenr']);
		if ($row['kodenr']==1){aar_1($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5']);}
		else
		{
			aar_x($row['id'], $row['kodenr'], $row['beskrivelse'], $row['box1'], $row['box2'], $row['box3'], $row['box4'], $row['box5']);
		}
	}
} else {
#	Print "<BODY onLoad=\"javascript:alert('$tekst')\">";
#	print "<BODY onLoad=\"javascript:velkommen=window.open('velkommen.html','velkommen','".$jsvars."';) velkommen.focus();\";"
	print "<BODY onLoad=\"javascript:docChange = true;\">";
	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by kodenr desc",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) if ($x <= $r['kodenr']) $x=$r['kodenr']; 
	$query = db_select("select * from grupper where art = 'RA' and kodenr='$x'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$x++;
		if ($row['box3']==12) {
			$startmd=1;
			$startaar=$row['box4']+1;
		} else {
			$startmd=$row['box3']+1;
			$startaar=$row['box4'];
		}
		$slutmd=$row['box3'];
		$slutaar=$row['box4']+1;
	} else {
		$beskrivelse=date(Y);
		$startaar=date(Y);
		$startmd='01';
		$slutaar=date(Y);
		$slutmd='12';
		$aaben='on';
	}
	if ($x==0) aar_1($id, 1, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben);
	else aar_x($id, $x, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben);
}
function aar_1($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben) {
	$laast=NULL; 
	
	$row = db_fetch_array(db_select("select MAX(kodenr) as kodenr from grupper where art = 'RA'",__FILE__ . " linje " . __LINE__));
	if ($row['kodenr'] > $kodenr) $laast=1;  
	if ($row = db_fetch_array(db_select("select * from grupper where art = 'RB' order by kodenr",__FILE__ . " linje " . __LINE__))) {
		$fakt=$row['box1']*1;
		$modt=$row['box2']*1;
		$faktbill=trim($row['box3']);
		$modtbill=trim($row['box4']);
		$no_faktbill=trim($row['box5']);
	
	} else {
		$fakt='1';
		$modt='1';
		$no_faktbill='on';
		$modtbill='on';
	}
	if (!$fakt) $fakt='1';
	if (!$modt) $modt='1';
	
	print "<form name=aar_1 action=regnskabskort.php method=post>";
	if ($id){print "<tr><td colspan=4 align = center><big><b>Ret 1. regnskabs&aring;r: $beskrivelse</big></td></tr>\n";}
	else {
		print "<tr><td colspan=4 align = center><big><b>Velkommen til som SALDI bruger</b></big><br />
			Du skal f&oslash;rst oprette dit 1. regnskabs&aring;r, f&oslash;r du kan bruge systemet.<br /><br /> 
			Systemet er allerede sat op, s&aring; det passer til de fleste virksomheder. Hvis dit 1. regnskabs&aring;r<br />
			passer med perioden 1. januar $startaar til 31. december $slutaar, skal du blot trykke p&aring; knappen [Gem],<br />
			og dit regnskab er klar til brug.<br /><br />
			Hvis der er noget, du er i tvivl om, er du velkommen til at kontakte os p&aring; telefon 4690 2208<br /> 
			God forn&oslash;jelse.<br /><br />
			</td></tr>\n";
		print "<tr><td colspan=4 align = center><big><b>Opret 1. regnskabs&aring;r: $beskrivelse</big></td></tr>\n";
	}
	print "<tr><td colspan=4 align=center><table width=100% border=0><tbody><tr>"; #########################table 4c start
	print "<tr><td></td><td align=center>Start</td><td align=center>Start</td><td align=center>Slut</td><td align=center>Slut</td><td align=center>Bogf&oslash;ring</td></tr>\n";
	print "<tr><td align=center>Beskrivelse</td><td align=center>m&aring;ned</td><td align=center>&aring;r</td><td align=center>m&aring;ned</td><td align=center>&aring;r</td><td align=center>tilladt</tr>\n";
	print "<input type=hidden name=kodenr value=1><input type=hidden name=id value='$id'>\n";
	print "<tr><td align=center><input type=text size=30 name=beskrivelse value=\"$beskrivelse\" onchange=\"javascript:docChange = true;\"></td>\n";
	if ($laast) $type="readonly=readonly";
	else $type="type=text";
	print "<td align=center><input $type style=\"text-align:right\" size=2 name=startmd value=$startmd onchange=\"javascript:docChange = true;\"></td>\n";
	print "<td align=center><input $type style=\"text-align:right\" size=4 name=startaar value=$startaar onchange=\"javascript:docChange = true;\"></td>\n";
	print "<td align=center><input $type style=\"text-align:right\" size=2 name=slutmd value=$slutmd onchange=\"javascript:docChange = true;\"></td>\n";
	print "<td align=center><input $type style=\"text-align:right\" size=4 name=slutaar value=$slutaar onchange=\"javascript:docChange = true;\"></td>\n";
	if (strstr($aaben,'on')) {print "<td align=center><input type=checkbox name=aaben checked onchange=\"javascript:docChange = true;\"></td>\n";}
	else {print "<td align=center><input type=checkbox name=aaben onchange=\"javascript:docChange = true;\"></td>\n";}
	print "</tr>\n</tbody></table></td></tr>\n"; ###################################################table 4c slut
	print "<tr><td colspan=4 width=100% align=center><table heigth=100% border=0><tbody>"; ###########################table 5c start
	print "<td align=center valign=\"top\"><table heigth=100% border=1><tbody>\n";  #################################table 6d start	print "<tr><td align=center>1. faktnr</td><td align=center>1. modt. nr.</td><tr>";
	print "<tr>\n <td>1.&nbsp;fakturanummer</td>\n";
	print " <td align=center><input type=text style=\"text-align:right\" size=4 name=fakt value=$fakt onchange=\"javascript:docChange = true;\"></td>\n</tr>\n";  
	print "<tr>\n <td>1.&nbsp;modtagelsesnummer</td>\n";
	print " <td align=center><input type=text style=\"text-align:right\" size=4 name=modt value=$modt onchange=\"javascript:docChange = true;\"></td>\n</tr>\n";  
	print "</tbody></table></td>\n"; ##########################################################table 6d slut
	print "<td><table border=1><tbody>"; ##############################################table 7d start
	if ($no_faktbill) $no_faktbill="checked"; 
	if ((!$no_faktbill)&&($faktbill)) $faktbill="checked"; 
	if ($modtbill) $modtbill="checked";
	print "<tr><td align=center>Undlad bilagsnummer til faktura</td><td align=center><input type=checkbox name=no_faktbill $no_faktbill onchange=\"javascript:docChange = true;\"></td></tr>\n";
	print "<tr><td align=center>Brug fakturanummer som bilagsnummer</td><td align=center><input type=checkbox name=faktbill $faktbill onchange=\"javascript:docChange = true;\"></td></tr>\n";
	print "<tr><td align=center>Brug modtagelsesnummer som bilagsnummer</td><td align=center><input type=checkbox name=modtbill $modtbill onchange=\"javascript:docChange = true;\"></td></tr>\n";
	print "</tbody></table></td>\n"; ##########################################################table 7d slut
	print "<td valign=\"top\"><table border=0><tbody>\n"; ##############################################table 8d start
	print "<tr><td><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
	print "</tbody></table></td></tr>\n";#####################################################table8d slut
	print "</td></tbody></table></td></tr>\n";#####################################################table5c slut
	print "<tr><td colspan=2 align=center> Indtast primotal for 1. regnskabs&aring;r:</td><td align = center> Debet</td><td align = center> Kredit</td></tr>\n";
	$query = db_select("select id, kontonr, primo, beskrivelse from kontoplan where kontotype='S' and regnskabsaar='1' order by kontonr",__FILE__ . " linje " . __LINE__);
	$y=0;
	$debetsum=0;
	$kreditsum=0;
	while ($row = db_fetch_array($query)) {
		$y++;
		print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]>";
		$debet[$y]="0,00";
		$kredit[$y]="0,00";
		if ($row['primo']>0) {
			$debet[$y]=dkdecimal($row['primo']);
			$debetsum=$debetsum+$row['primo'];
		}
		elseif ($row['primo']<0) {
			$kredit[$y]=dkdecimal($row['primo']*-1);
			$kreditsum=$kreditsum+($row['primo']*-1);
		}
		print "<td>$row[kontonr]</td>";
		print "<td>$row[beskrivelse]</td>";
		print "<td align=right><input type=text style=\"text-align:right\" size=10 name=debet[$y] value=$debet[$y] onchange=\"javascript:docChange = true;\"></td>";
		print "<td align=right><input type=text style=\"text-align:right\" size=10 name=kredit[$y] value=$kredit[$y] onchange=\"javascript:docChange = true;\"></td></tr>\n";
	}
	print "<td></td><td></td><td align=right>".dkdecimal($debetsum)."</td><td align=right>".dkdecimal($kreditsum)."</td></tr>\n";
	if (abs($debetsum-$kreditsum)>0.009) {
		print "<BODY onLoad=\"javascript:alert('Konti er ikke i balance')\">";
	}
	
#	print "<tr><td colspan = 3> Overfr �ningsbalance</td><td align=center><input type=checkbox name=primotal checked></td></tr>\n";
	print "<input type=hidden name=kontoantal value=$y>";
	print "<tr><td colspan='4' align='center'><input type='submit' accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td></tr>\n";
	print "</form>";
	exit;
}

function aar_x($id, $kodenr, $beskrivelse, $startmd, $startaar, $slutmd, $slutaar, $aaben) {
	global $overfor_til;
	
	$r=db_fetch_array(db_select("select max(kodenr) as max_aar from grupper where art = 'RA'",__FILE__ . " linje " . __LINE__));
	$max_aar=$r['max_aar'];
	
	$pre_regnaar=$kodenr-1;
	$query = db_select("select * from grupper where art = 'RA' and kodenr = '$pre_regnaar'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$pre_startmd=$row['box1'];
		$pre_startaar=$row['box2'];
		$pre_slutmd=$row['box3'];
		$pre_slutaar=$row['box4'];
	}
	
	$pre_slutdato=31;
	while (!checkdate($pre_slutmd, $pre_slutdato, $pre_slutaar)) {
		$pre_slutdato=$pre_slutdato-1;
		if ($pre_slutdato<28) break 1;
	}
	$pre_regnstart = $pre_startaar. "-" . $pre_startmd . "-" . '01';
	$pre_regnslut = $pre_slutaar . "-" . $pre_slutmd . "-" . $pre_slutdato;

	print "<form name=aar_1 action=regnskabskort.php method=post>";
	if ($id){print "<tr><td colspan=5 align = center><big><b>Ret $kodenr. regnskabs&aring;r: $beskrivelse</td></tr>\n";}
	else {print "<tr><td colspan=5 align = center><big><b>Opret $kodenr. regnskabs&aring;r: $beskrivelse</td></tr>\n";}
	print "<tr><td colspan=5 align=center><table width=100% border=0><tbody><tr>"; ###########################table 8d start
	print "<tr><td></td><td align=center>Start</td><td align=center>Start</td><td align=center>Slut</td><td align=center>Slut</td><td align=center>Bogf&oslash;ring</td></tr>\n";
	print "<tr><td align=center>Beskrivelse</td><td align=center>m&aring;ned</td><td align=center>&aring;r</td><td align=center>m&aring;ned</td><td align=center>&aring;r</td><td align=center>tilladt</td></tr>\n";
	print "<tr><input type=hidden name=kodenr value=$kodenr><input type=hidden name=id value='$id'	>";
	print "<td align=center><input type=text size=30 name=beskrivelse value=\"$beskrivelse\" onchange=\"javascript:docChange = true;\"></td>";
	print "<td align=center><input readonly=readonly style=\"text-align:right\" size=2 name=startmd value=$startmd></td>";
	print "<td align=center><input readonly=readonly style=\"text-align:right\" size=4 name=startaar value=$startaar></td>";
	print "<td align=center><input type=text style=\"text-align:right\" size=2 name=slutmd value=$slutmd onchange=\"javascript:docChange = true;\"></td>";
	print "<td align=center><input type=text style=\"text-align:right\" size=4 name=slutaar value=$slutaar onchange=\"javascript:docChange = true;\"></td>";
	(strstr($aaben,'on'))?$checked='checked':$checked=NULL;
	if (!$id) $checked='checked';
	print "<td align=center><input type=checkbox name=aaben $checked onchange=\"javascript:docChange = true;\"></td>";
	print "</tr>\n</tbody></table></td></tr>\n"; #####################################################table 8d slut
	print "<tr><td colspan=2 align=center> Primotal for $kodenr. regnskabs&aring;r:</td><td align = center> saldo</td><td align = center> overf&oslash;r til</td><td align = center> ny primo</td></tr>\n";
	$tmp=$kodenr;
	$kontoantal=0;
	while ($kontoantal<1&&$tmp>0){ #Hvis der ikke er oprettet konti for indevaerende regsskabsaar, hentes konti fra forrige.
		$query = db_select("select primo, kontonr, beskrivelse from kontoplan where kontotype='S' and regnskabsaar='$tmp' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$kontoantal++;
			$primo[$kontoantal]=$row['primo'];
			$kontonr[$kontoantal]=$row['kontonr'];
		} 
		$tmp--;
	}
	$pre_regnaar=$kodenr-1;
	
	$r=db_fetch_array(db_select("select box2 from grupper where kodenr='$pre_regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
	($r['box2'] >= '2015')?$aut_lager='on':$aut_lager=NULL;
	if (!$pre_regnaar) {
		echo "regnaar mangler";
		exit;
	}	

	if ($aut_lager) {
		$x=0;
		$varekob=array();
		$q=db_select("select box1,box2,box3 from grupper where art = 'VG' and box8 = 'on'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if ($r['box1'] && !in_array($r['box3'],$varekob)) {
				$varelager_i[$x]=$r['box1'];
				$varelager_u[$x]=$r['box2'];
				$varekob[$x]=$r['box3'];
				$x++;
			}
		}
	}
	
	$ny_sum=0;
	$resultat=0;
	$q = db_select("select * from kontoplan where kontotype='D' and regnskabsaar=$pre_regnaar order by kontonr",__FILE__ . " linje " . __LINE__);
	$y=0;
	while ($r = db_fetch_array($q)) {
		$resultat+=afrund($r['primo'],2);
		$q2 = db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$r[kontonr]'",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) $resultat+=afrund($r2['debet']-$r2['kredit'],2);
		if ($aut_lager) {
			if (in_array($r['kontonr'],$varekob)) {
				$l_a_primo[$x]=find_lagervaerdi($r['kontonr'],$pre_regnstart);
				$l_a_sum[$x]=find_lagervaerdi($r['kontonr'],$pre_regnslut);
# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges 
				$resultat+=$l_a_primo[$x]; 
				$resultat-=$l_a_sum[$x];		
			}
			if (in_array($r['kontonr'],$varelager_i) || in_array($r['kontonr'],$varelager_u)) {
				$l_a_primo[$x]=find_lagervaerdi($r['kontonr'],$pre_regnstart);
				$l_a_sum[$x]=find_lagervaerdi($r['kontonr'],$pre_regnslut);
# Varelager (debet) krediteres lager primo og og debiteres lager saldo.  Dvs tallet øges hvis lager øges
				$resultat-=$l_a_primo[$x];
				$resultat+=$l_a_sum[$x];
			}
		}
	}
	$resultat=afrund($resultat,2);
	$r=db_fetch_array(db_select("select * from kontoplan where kontotype='X' and regnskabsaar=$pre_regnaar",__FILE__ . " linje " . __LINE__));
	$sideskift=$r['kontonr']*1;
	if ($sideskift) {
		$q2=db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$sideskift'",__FILE__ . " linje " . __LINE__);
		while ($r2=db_fetch_array($q2)) $resultat+=afrund($r2['debet']-$r2['kredit'],2);
		$saldosum=$resultat;
		print "<td><br /></td>";
		print "<td>Resultat</td>";
		print "<input type=hidden name=kontonr[0] value=$sideskift>";
		print "<td width=10 align=right><input type=hidden name=saldo[0] value=$resultat>".dkdecimal($resultat)."</td>";
		print "<td><SELECT NAME=overfor_til[0]>";
		if ($r['overfor_til']) print "<option>$r[overfor_til]</option>";  
		print "<option>$kontonr[$y]</option>";
		for ($x=1;$x<=$kontoantal;$x++) print "<option>$kontonr[$x]</option>";
		print "</SELECT></td>";
		print "<td width=10><br /></td></tr>\n";
		$ny_sum+=$ny_primo[$y];
	}
#cho "select * from kontoplan where kontotype='S' and regnskabsaar='$pre_regnaar' order by kontonr<br>";
	$query = db_select("select * from kontoplan where kontotype='S' and regnskabsaar='$pre_regnaar' order by kontonr",__FILE__ . " linje " . __LINE__);
	$y=0;
	while ($row = db_fetch_array($query)) {
		$y++;
		$ny_primo[$y]=0;
    for ($x=1; $x<=$kontoantal; $x++) {
			if ($kontonr[$x]==$row['kontonr']) {
				$ny_primo[$y]=$primo[$x]; 
#				$overfor_til[$y]=$row['overfor_til'];
			}
		}
		$belob=0;
		$belob=$row['primo'];
		print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]>";
		$q2 = db_select("select * from transaktioner where transdate>='$pre_regnstart' and transdate<='$pre_regnslut' and kontonr='$row[kontonr]'",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			 $belob+=afrund($r2['debet']-$r2['kredit'],2);
		}
		if ($aut_lager) {
			if (in_array($row['kontonr'],$varekob)) {
				$l_a_primo[$x]=find_lagervaerdi($row['kontonr'],$pre_regnstart);
				$l_a_sum[$x]=find_lagervaerdi($row['kontonr'],$pre_regnslut);
# Varekøb (debet) debiteres lager primo og krediteres lager saldo. Dvs tallet mindskes hvis lager øges 
				$belob+=$l_a_primo[$x]; 
				$belob-=$l_a_sum[$x];		
			}
			if (in_array($row['kontonr'],$varelager_i) || in_array($row['kontonr'],$varelager_u)) {
				$l_a_primo[$x]=find_lagervaerdi($row['kontonr'],$pre_regnstart);
				$l_a_sum[$x]=find_lagervaerdi($row['kontonr'],$pre_regnslut);
# Varelager (debet) krediteres lager primo og og debiteres lager saldo.  Dvs tallet øges hvis lager øges
				$belob-=$l_a_primo[$x];
				$belob+=$l_a_sum[$x];
			}
		}
		
		$saldosum=$saldosum+$belob;
		print "<td>$row[kontonr]</td>";
		print "<td>$row[beskrivelse]</td>";
		print "<td width=10 align=right><input type=hidden name=saldo[$y] value=$belob>".dkdecimal($belob)."</td>";
		print "<td><SELECT NAME=overfor_til[$y]>";
		if ($row['overfor_til'] && in_array($row['overfor_til'],$kontonr)) print "<option>$row[overfor_til]</option>";  
		elseif (in_array($row['kontonr'],$kontonr)) print "<option>$row[kontonr]</option>";
		else print "<option></option>";
		for ($x=1;$x<=$kontoantal;$x++) print "<option>$kontonr[$x]</option>";
		print "</SELECT></td>";
		
		print "<td width=10 align=right><input type=hidden name=ny_primo[$y] value=$ny_primo[$y]>".dkdecimal($ny_primo[$y])."</td></tr>\n";
		$ny_sum=$ny_sum+$ny_primo[$y];
	}
	print "<td></td><td></td><td align=right>".dkdecimal($saldosum)."</td><td></td><td align=right>".dkdecimal($ny_sum)."</td></tr>\n";
	if ($debetsum-$kreditsum!=0) {print "<BODY onLoad=\"javascript:alert('Konti er ikke i balance')\">";}
#	print "<tr><td colspan = 3> Overfr �ningsbalance</td><td align=center><input type=checkbox name=primotal checked></td></tr>\n";
	print "<input type=hidden name=kontoantal value=$y>";
	print "<tr><td colspan = 5 align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
	if ($regnaar==$max_aar) print "<input type=submit value=\"Slet\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
	print "</td></tr>\n";
	print "</form>";
	exit;
}
######################################################################################################################################
print "</tbody></table></td></tr>\n";# table 3b slut
print "</tbody></table></td></tr>\n";# table 1a slut

?>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="#ffcc00"><font face="Helvetica, Arial, sans-serif" color="#000066">Copyright (C) 2004 DANOSOFT ApS</td>
		</tbody></table>
</td></tr>\n
</tbody></table>
<?php if ($menu=='T') print "</div> <!-- end of maincontent -->\n";  # 20150327 ?>
</body></html>
