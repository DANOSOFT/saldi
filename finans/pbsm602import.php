<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/pbsm602import.php --- patch 4.1.1 --- 2025.06.13 ---
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20200102 PHR Instead of finding the latest invoice it now finds the oldest unpaid invoice. 20200102
// 20230711 PHR Better recognition of debitor account for BS042 / '0297
// 20230908 PHR Expanded debitor recognition character from 13 to 19 in above
// 20231003 PHR Reversed above.
// 20231023 PHR Combined recognition with invoice nummer for use with and without.
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
// 20250613 PHR	Corrected error in chages made 20250130

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import til kassekladde";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

if(($_GET)||($_POST)) {

	if ($_GET) {
		$kladde_id=$_GET['kladde_id'];
		$bilag=$_GET['bilagsnr'];
	}
	else {
		$submit    = $_POST['submit'];
		$show      = $_POST['show'];
		$kladde_id = $_POST['kladde_id'];
		$fileName  = $_POST['fileName'];
		$modkonto  = $_POST['modkonto'];
#		$feltnavn=$_POST['feltnavn'];
#		$feltantal=$_POST['feltantal'];
#		$kontonr=$_POST['kontonr'];
		$bilag    = $_POST['bilag']*1; #*1 tilfojet 06.07.12
#		$datoformat=$_POST['datoformat'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Importer til kassekladde (Kassekladde $kladde_id)</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
	print "</tbody></table>";
	print "</td></tr>";

	if (isset($_FILES['uploadedfile']['tmp_name']) && count($_FILES['uploadedfile']['tmp_name'])) {
		$path = "../temp/$db/m602/";
		if (file_exists($path)) {
			$files = glob($path.'/*');
			foreach($files as $file) {
				if(is_file($file)) unlink($file);
			}
		}
		else mkdir($path);
		foreach($_FILES['uploadedfile']['tmp_name'] as $key => $tmp_name) {
			$fileName = $_FILES['uploadedfile']['name'][$key];
			$fileSize = $_FILES['uploadedfile']['size'][$key];
			$fileTemp = $_FILES['uploadedfile']['tmp_name'][$key];
			$fileType = $_FILES['uploadedfile']['type'][$key];  
			move_uploaded_file($fileTemp,$path.$fileName);
		}
	/*
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			vis_data($kladde_id, $filnavn, $bilag, $modkonto);*/
		if (file_exists($path.$fileName)) vis_data($kladde_id, $bilag, $modkonto);
		else	echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} 
	if ($submit=='Flyt' && $kladde_id && $modkonto) flyt_data($kladde_id, $bilag, $modkonto);
	elseif ($show && $kladde_id) vis_data($kladde_id, $bilag, $modkonto);
	else print "<meta http-equiv=\"refresh\" content=\"0;URL=importer.php?kladde_id=$kladde_id&bilag=$bilag\">";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id&bilag=$bilag\">";

print "</tbody></table>";
################################################################################################################
function vis_data($kladde_id, $bilag, $modkonto){
	global $bgcolor,$bgcolor5;
	global $db;
	global $charset;
	global $bruger_id;

	$dd = date('Y-m-d');
	$r=db_fetch_array(db_select("select art,pbs_nr,pbs from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$pbs_nr=$r['pbs_nr'];
	$lev_pbs=$r['pbs'];
	if ($modkonto) {
		db_modify("update grupper set box14='$modkonto' where ART='KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
	} elseif ($r=db_fetch_array(db_select("select * from grupper where ART = 'KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__))) {
			$modkonto=if_isset($r['box14']);
			#box1 - 3 bruges af import.php
	} else {
		db_modify ("insert into grupper (beskrivelse,art,kode,kodenr) values ('Kassekladdeimport','KASKL','2','$bruger_id')",__FILE__ . " linje " . __LINE__);
	}
	$feltantal = $linjeantal = $i = $y = 0;
	$path = "../temp/$db/m602/";
#	if (file_exists($path)) {
	$files = glob($path.'/*');
	foreach($files as $fileName) {
		$fp=fopen("$fileName","r");
		if ($fp) {
			while ($linje[$i]=fgets($fp)) {
				if (trim($linje[$i])) {
					$linje[$i]=trim(mb_convert_encoding($linje[$i], 'UTF-8', 'ISO-8859-1'));
					$fn[$i]=$fileName; 
					$i++;
				}
			}
		}
		fclose ($fp);
	}
	$BS002 = $BS012 = 0;
	$addToPBS[$y] = $deleteFromPBS[$y] = $debitor[$y] = array();
	for ($i=0;$i<count($linje);$i++) {
	#	$beskrivelse[$y] = $date[$y] = $dato[$y] = $debitor[$y] = $amount[$y] = $belob[$y] = NULL;
		$addToPBS[$y] = $deleteFromPBS[$y] = $debitor[$y] = 0;
		if (substr($linje[$i],0,5)=='BS002' && substr($linje[$i],16,4)=='0603') {
			if (!$BS002) $BS002=1;
			else $BS002=0;
		}
		if ($BS002) {
			if (substr($linje[$i],0,5)=='BS012') {
				$date[$y] = usdate(substr($linje[$i],49,6));
				if (!$BS012) $BS012=1;
				else $BS012=0;
			}
		}
		if ($lev_pbs == 'L' && substr($linje[$i],0,3)=='510') {
			$debitor[$y]=substr($linje[$i],8,15)*1;
			$pbsnr[$y]=99999;
			$qtxt = "select konto_id, pbs_nr from pbs_kunder where kontonr = '$debitor[$y]' order by id limit 1";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt = "delete from bs_kunder where kontonr = '$debitor[$y]' and id != '$r[id]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else {
				$qtxt = "select id from adresser where kontonr = '$debitor[$y]' and art = 'D'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$accountId[$y] = $r['id'];
					$qtxt = "update adresser set pbs='on',pbs_nr='$pbsnr[$y]' where id = '$accountId[$y]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "insert into pbs_kunder(konto_id,kontonr,pbs_nr) values "; 
					$qtxt = "('$accountId[$y]','$debitor[$y]','$pbsnr[$y]')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__); #20121024
					$qtxt = "update ordrer set pbs='FI',udskriv_til='PBS' ";
					$qtxt = "where kontonr = '$debitor[$x]' and art = 'DO' and nextfakt >= '$dd'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$addToPBS[$y]=1;
				}		
				$y++;
			}
		} elseif ($lev_pbs == 'L' && substr($linje[$i],0,3)=='540') { #20130516
			$debitor[$y]=substr($linje[$i],8,15)*1;
			$qtxt = "select konto_id from pbs_kunder where kontonr = '$debitor[$y]'";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$accountId[$y] = $r['konto_id'];
				$qtxt = "update adresser set pbs='',pbs_nr='' where id = '$accountId[$x]'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "delete from pbs_kunder where kontonr = '$debitor[$y]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$deleteFromPBS[$y]=1;
				$y++;
			}
		} elseif ($BS002 && substr($linje[$i],0,5)=='BS042' && substr($linje[$i],13,3)=='023') {
			$date[$y]=$date[$y-1];
			$dato[$y]=dkdato($date[$y]);
			
			$debitor[$y]=substr($linje[$i],25,15)*1;
			$pbsnr[$y]=substr($linje[$i],40,9);
			$tilfra[$y]=substr($linje[$i],13,4); 
			$qtxt = "select id, firmanavn from adresser where kontonr='$debitor[$y]'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$accountId[$y] = $r['id'];
			$firmanavn[$y] = $r['firmanavn'];
			if ($tilfra[$y]=='0231') {	
				$beskrivelse[$y] = "$firmanavn[$y] Tilmeldt";
				$qtxt = "update adresser set pbs='on',pbs_nr='$pbsnr[$y]' where kontonr = '$debitor[$y]' and art = 'D'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update ordrer set pbs='FI',udskriv_til='PBS' ";
				$qtxt.= "where kontonr = '$debitor[$y]' and art = 'DO' and nextfakt >= '$dd'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} elseif ($tilfra[$y]=='0232' || $tilfra[$y]=='0233' || $tilfra[$y]=='0234') {
				if ($tilfra[$y]=='0232') $beskrivelse[$y] = "$firmanavn[$y] Afmeldt af debitors pengeinstitut";
				if ($tilfra[$y]=='0233') $beskrivelse[$y] = "$firmanavn[$y] Afmeldt af kreditor pengeinstitut";
				if ($tilfra[$y]=='0234') $beskrivelse[$y] = "$firmanavn[$y] Afmeldt af PBS";
				$qtxt = "update adresser set pbs='',pbs_nr='' where kontonr = '$debitor[$y]' and art = 'D'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update ordrer set pbs='',udskriv_til='email' where ";
				$qtxt.= "kontonr = '$debitor[$y]' and art = 'DO' and nextfakt >= '$dd'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} elseif (!$accountId[$y]) $beskrivelse[$y] = "$fn[$i] $kundenr[$y] Ikke fundet i adresseliste</td></tr>";
#			$BS002 = $BS012 = 0;
			$y++;
		} elseif ($BS002 && substr($linje[$i],0,5)=='BS092' && substr($linje[$i],16,4)=='0603' ) {
			$BS002 = $BS012 = 0;
		} elseif ($linje[$i] && substr($linje[$i],0,5)=='BS042') {
			if (substr($linje[$i],13,4)=='0297') {
				$skriv_linje[$y]=1;
				$amount[$y]=substr($linje[$i],115,13)/100;
				$invoice[$y]=substr($linje[$i],38,5);
				$debitor[$y] = (int)substr($linje[$i],25,13);
				$found = 0;
				$qtxt = "select kontonr from ordrer where art = 'DO' and fakturanr = '$invoice[$y]' ";
				$qtxt.= "and sum+moms = '$amount[$y]' and kontonr like '%$debitor[$y]%'";
				if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$debitor[$y] = $r['kontonr'];
						$found = 1;
				}
				if (!$found) {
					$debitor[$y] = (int)substr($linje[$i],25,19);
					$qtxt = "select kontonr from ordrer where art = 'DO' and kontonr = '$debitor[$y]' ";
					$qtxt.= "and sum+moms = '$amount[$y]'";
					if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$debitor[$y] = $r['kontonr'];
						$found = 1;
					}
				}
				if (!$found) {
					$debitor[$y] = (int)substr($linje[$i],25,19);
					$qtxt = "select kontonr from ordrer where art = 'DO' and kontonr = '$debitor[$y]' ";
					if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$debitor[$y] = $r['kontonr'];
						$found = 1;
					}
				}
				$beskrivelse[$y]="Indbetaling via FI kort. Kunde $debitor[$y]";
				$date[$y]=usdate(substr($linje[$i],103,6));
				$belob[$y]=dkdecimal($amount[$y]);
				if (!$amount[$y]) $skriv_linje[$y]=0;
				list($aar,$maaned,$dag)=explode("-",$date[$y]);
				if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
				else $skriv_linje[$y]=0;
				$y++;
			} elseif ($linje[$i] && substr($linje[$i],13,4)=='0236') {
				$skriv_linje[$y]=1;
				$debitor[$y]=substr($linje[$i],25,15)*1;
				$beskrivelse[$y]="Indbetaling via BS. Kunde $debitor[$y]";
				$date[$y]=usdate(substr($linje[$i],103,6));
				$aftalenr[$y]==substr($linje[$i],40,9);
				$amount[$y]=substr($linje[$i],115,13)/100;
				$belob[$y]=dkdecimal($amount[$y]);
				if (!$amount[$y]) $skriv_linje[$y]=0;
				list($aar,$maaned,$dag)=explode("-",$date[$y]);
				if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
				else $skriv_linje[$y]=0;
				$y++;
			} #else echo __line__." ".substr($linje[$i],0,5)."$linje[$i]<br>";
		} #	else echo __line__." ".substr($linje[$i],0,5)."$linje[$i]<br>";
	}
	$linjeantal=$y;
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"pbsm602import.php\" method=\"POST\">";
	#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
	print "<tr><td colspan=\"5\" align=\"center\">Modkonto<input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=\"modkonto\" value=\"$modkonto\"> ";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "&nbsp; <input type=\"submit\" name=\"show\" value=\"Vis\" />";
	if ($kladde_id && $modkonto) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
	print "<tr><td colspan=\"5\"><hr></td></tr>\n";
	print "<tr><td><span title='Angiv 1. bilagsnummer'><input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=bilag value=$bilag></span></td>";
	print "<td><b>Kundenr</b></td><td><b>Tekst</b></td><td><b>Dato</b></td><td><b>Bel&oslash;b</b></td></tr>";
	print "</form>";
	

	
#	array_multisort($date,$dato,$debitor,$beskrivelse,$belob);	
	$linjebg=$bgcolor;
	$d = $date[0];
	for ($x=0;$x<$linjeantal;$x++) {
		($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($skriv_linje[$x]==1) {
			if ($d != $date[$x]) $bilag++;
			$txtcolor="0,0,0";
		} else {
			$txtcolor="255,0,0";
		}
		print "<tr bgcolor=\"$linjebg\" style=\"color:rgb($txtcolor);\"><td align=\"right\" width=\"10px\">$bilag</td><td>$debitor[$x]</td><td>$beskrivelse[$x]</td><td>$dato[$x]</td><td>$belob[$x]</td></span></tr>";
	}
	print "</tbody></table>";
	print "</td></tr>";
	exit;
} # function vis_data;

function flyt_data($kladde_id, $bilag, $modkonto){
	global $charset,$db;

	$feltantal = $linjeantal = $i = $y = 0;
	$linje = array();
	$path = "../temp/$db/m602/";
#	if (file_exists($path)) {
	$files = glob($path.'/*');
	foreach($files as $fileName) {
		$fp=fopen("$fileName","r");
		if ($fp) {
			while ($linje[$i]=fgets($fp)) {
				if (trim($linje[$i])) {
					$linje[$i]=trim(mb_convert_encoding($linje[$i], 'UTF-8', 'ISO-8859-1'));
					$i++;
				}
			}
		}
		fclose ($fp);
	}
	transaktion('begin');
	for ($i=0;$i<count($linje);$i++) {
#			$linje[$i]=trim(mb_convert_encoding($linje[$i]), 'UTF-8', 'ISO-8859-1');
			if ($linje[$i] && substr($linje[$i],0,5)=='BS042') {
				if (substr($linje[$i],13,4)=='0297') {
					$skriv_linje[$y]=1;
					$amount[$y]=substr($linje[$i],115,13)/100;
					$invoice[$y]=substr($linje[$i],38,5);
					$debitor[$y] = (int)substr($linje[$i],25,13);
					$found = 0;
					$qtxt = "select kontonr from ordrer where art = 'DO' and fakturanr = '$invoice[$y]' ";
					$qtxt.= "and sum+moms = '$amount[$y]' and kontonr like '%$debitor[$y]%'";
					if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$debitor[$y] = $r['kontonr'];
							$found = 1;
					}
					if (!$found) {
						$debitor[$y] = (int)substr($linje[$i],25,19);
						$qtxt = "select kontonr from ordrer where art = 'DO' and kontonr = '$debitor[$y]' ";
						$qtxt.= "and sum+moms = '$amount[$y]'";
						if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$debitor[$y] = $r['kontonr'];
							$found = 1;
						}
					}
					if (!$found) {
						$debitor[$y] = (int)substr($linje[$i],25,19);
						$qtxt = "select kontonr from ordrer where art = 'DO' and kontonr = '$debitor[$y]' ";
						if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
							$debitor[$y] = $r['kontonr'];
							$found = 1;
						}
					}
					$beskrivelse[$y]="Indbetaling via FI kort. Kunde $debitor[$y]";
					$beskrivelse[$y]="Indbetaling via FI kort. Kunde $debitor[$y]";
					$date[$y]=usdate(substr($linje[$i],103,6));
					if (!$amount[$y]) $skriv_linje[$y]=0;
					list($aar,$maaned,$dag)=explode("-",$date[$y]);
					if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
					else $skriv_linje[$y]=0;
					if ($skriv_linje[$y]) $y++;
				} elseif ($linje[$i] && substr($linje[$i],13,4)=='0236') {
					$skriv_linje[$y]=1;
					$debitor[$y]=substr($linje[$i],25,15)*1;
					$beskrivelse[$y]="Indbetaling via BS. Kunde $debitor[$y]";
					$date[$y]=usdate(substr($linje[$i],103,6));
					$aftalenr[$y]==substr($linje[$i],40,9);
					$amount[$y]=substr($linje[$i],115,13)/100;
					if (!$amount[$y]) $skriv_linje[$y]=0;
					list($aar,$maaned,$dag)=explode("-",$date[$y]);
					if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
					else $skriv_linje[$y]=0;
					if ($skriv_linje[$y]) $y++;
				}
			}
		}
	$linjeantal=$y;
	$sum=0;
	$d=$date[0];
	if (count($skriv_linje)) {
		for ($x=0;$x<count($skriv_linje);$x++) {
			if ($skriv_linje[$x]==1) {
				$sum+=$amount[$x];
				$bilag = (int)$bilag;
				$qtxt = "select faktnr from openpost where amount = '$amount[$x]' and konto_nr = '$debitor[$x]' ";
				$qtxt.= "and udlignet='0' order by transdate limit 1"; #20200102
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$faktura=$r['faktnr'];
				$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[$x]','$beskrivelse[$x]','F','0','D','$debitor[$x]','$amount[$x]','$kladde_id','$faktura')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if (!isset($date[$x+1]) || $date[$x] != $date[$x+1]) {
					$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[$x]','PBS Samlet betaling','F','$modkonto','F','0','$sum','$kladde_id','')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$bilag++;
					$d = $date[$x];
					$sum=0;
				}
			}
		}
/*
		$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$d','PBS Samlet betaling','F','$modkonto','F','0','$sum','$kladde_id','')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
*/
	} elseif ($skriv_linje[$linjeantal]==1) {
		$r=db_fetch_array(db_select("select faktnr from openpost where amount = '$amount[$linjeantal]' and konto_nr = '$debitor[$linjeantal]' order by transdate desc",__FILE__ . " linje " . __LINE__));
		$faktura=$r['faktnr'];
		$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id,faktura) values ('$bilag','$date[$linjeantal]','$beskrivelse[$linjeantal]','F','$modkonto','D','$debitor[$linjeantal]','$amount[$linjeantal]','$kladde_id','$faktura')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
#xit;
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
