<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------finans/datalonimport.php--------patch 3.9.8-----2020.11.03-----------
// LICENSE
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
//
// Copyright (c) 2019-2020 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20201103 PHR Switches $kontonr[$y],$beskrivelse[$y] in list | explode
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import fra Datal&oslash;n til kassekladde";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

if (!isset ($_POST['submit'])) $_POST['submit'] = NULL;
if (!isset ($_POST['filnavn'])) $_POST['filnavn'] = NULL;
if (!isset ($_POST['bilag'])) $_POST['bilag'] = NULL;

if(($_GET)||($_POST)) {

	if ($_GET) {
		$kladde_id=$_GET['kladde_id'];
		$bilag=$_GET['bilagsnr'];
	} else {
		$submit=$_POST['submit'];
		$kladde_id=$_POST['kladde_id'];
		$filnavn=$_POST['filnavn'];
#		$modkonto=$_POST['modkonto'];
#		$feltnavn=$_POST['feltnavn'];
#		$feltantal=$_POST['feltantal'];
#		$kontonr=$_POST['kontonr'];
		$bilag=$_POST['bilag'];
#		$datoformat=$_POST['datoformat'];
	}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$title (Kassekladde $kladde_id)</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
	print "</tbody></table>";
	print "</td></tr>";

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			vis_data($kladde_id, $filnavn, $bilag);
	} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
}	elseif ($submit=='Flyt' && $kladde_id && $filnavn) flyt_data($kladde_id, $filnavn, $bilag);
elseif ($submit && $kladde_id && $filnavn) vis_data($kladde_id, $filnavn, $bilag);
else print "<meta http-equiv=\"refresh\" content=\"0;URL=importer.php?kladde_id=$kladde_id&bilag=$bilag\">";
} else print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id&bilag=$bilag\">";

print "</tbody></table>";
################################################################################################################
function vis_data($kladde_id, $filnavn, $bilag){
	global $bgcolor;
	global $bgcolor5;
	global $charset;
	global $bruger_id;

	$fp=fopen("$filnavn","r");
	if ($fp) {
		$y=0;
		$feltantal=0;
#	for ($y=1; $y<20; $y++) {
		while ($linje=fgets($fp)) {
#			$linje=trim(mb_convert_encoding($linje), 'UTF-8', 'ISO-8859-1');
			if ($linje) {
				$y++;
				$skriv_linje[$y]=1;
				if ($y==1) {
					if (substr($linje,5,5) == 'Bilag') $preSetNo = 1;
					else {# fjerner uft16 snask - bør finde en bedre løsning
						$preSetNo = 0;
						while(!is_numeric(substr($linje,0,1)) && $linje) $linje=substr($linje,1);	
					}
				}
				if ($preSetNo) list($dato[$y],$bilag,$faktura[$y],$beskrivelse[$y],$belob[$y],$tmp,$kontonr[$y])=explode(";",mb_convert_encoding($linje), 'UTF-8', 'ISO-8859-1');
				else list($dato[$y],$kontonr[$y],$beskrivelse[$y],$belob[$y])=explode(";",$linje);
				if (!is_numeric($kontonr[$y]) && is_numeric($beskrivelse[$y])) {
					list($dato[$y],$beskrivelse[$y],$kontonr[$y],$belob[$y])=explode(";",$linje);
				}
				if (!is_numeric($kontonr[$y])) $skriv_linje[$y]=0;
				$amount[$y]=usdecimal($belob[$y])*1;
				if (!$amount[$y]) $skriv_linje[$y]=0;
				list($dag,$maaned,$aar)=explode(".",$dato[$y]);
				$maaned*=1; $dag*=1; $aar*=1;
				if (checkdate($maaned,$dag,$aar)) $date[$y]=usdate($dato[$y]);
				else $skriv_linje[$y]=0;
			}
		}
	}  
	$linjeantal=$y;
	fclose ($fp);
	print "<tr><td width=100% align=center><table width=\"1000px\" border=\"0\" cellspacing=\"5\" cellpadding=\"1\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"datalonimport.php\" method=\"POST\">";
	#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
	print "<tr>";
#	<td colspan=\"5\" align=\"center\">Modkonto<input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=\"modkonto\" value=\"$modkonto\"> ";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
	if ($kladde_id && $filnavn) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
	print "<tr><td colspan=\"5\"><hr></td></tr>\n";
	print "<tr><td  style=\"width:60px\"><span title='Angiv bilagsnummer'><input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=bilag value=$bilag></span></td>";
	print "<td><b>Dato</b></td><td><b>Kontonr</b></td>";
	if ($preSetNo) print "<td><b>Faktura</b></td>";
	print "<td><b>Tekst</b></td><td align='center'><b>Bel&oslash;b</b></td></tr>";
	print "</form>";
	$linjebg=$bgcolor;
	for ($x=1;$x<=$linjeantal;$x++) {
		($linjebg==$bgcolor)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($skriv_linje[$x]==1) {
#			$bilag++;
			$txtcolor="0,0,0";
		} else {
			$txtcolor="255,0,0";
	}	
		print "<tr bgcolor=\"$linjebg\" style=\"color:rgb($txtcolor);\">";
		print "<td align=\"right\" width=\"10px\">$bilag</td><td>$dato[$x]</td><td>$kontonr[$x]</td>";
		if ($preSetNo) print "<td><b>$faktura[$y]</b></td>";
		print "<td>$beskrivelse[$x]</td><td align='right'>".dkdecimal($amount[$x])."</td></tr>";
	}
	print "</tbody></table>";
	print "</td></tr>";
} # function vis_data;

function flyt_data($kladde_id, $filnavn, $bilag){
	global $charset;
	echo "F 	$filnavn<br>";
#	transaktion('begin');
	$fp=fopen("$filnavn","r");
	if ($fp) {
		$y=0;
		$feltantal=0;
#	for ($y=1; $y<20; $y++) {
		while ($linje=fgets($fp)) {
#			$linje=trim(mb_convert_encoding($linje), 'UTF-8', 'ISO-8859-1');
			if ($linje = trim($linje)) {
				$y++;
				$skriv_linje[$y]=1;
				if ($y==1) {
					if (substr($linje,5,5) == 'Bilag') $preSetNo = 1;
					else {# fjerner uft16 snask - bør finde en bedre løsning
						$preSetNo = 0;
						while(!is_numeric(substr($linje,0,1)) && $linje) $linje=substr($linje,1);	
					}
				}
				if ($preSetNo) list($dato[$y],$bilag,$faktura[$y],$beskrivelse[$y],$belob[$y],$tmp,$kontonr[$y])=explode(";",mb_convert_encoding($linje), 'UTF-8', 'ISO-8859-1');
				else {
					list($dato[$y],$kontonr[$y],$beskrivelse[$y],$belob[$y])=explode(";",$linje);
					if (!is_numeric($kontonr[$y]) && is_numeric($beskrivelse[$y])) {
						list($dato[$y],$beskrivelse[$y],$kontonr[$y],$belob[$y])=explode(";",$linje);
					}
					$faktura[$y] = '';
				}
				if (!is_numeric($kontonr[$y])) $skriv_linje[$y]=0;
				$amount[$y] = usdecimal($belob[$y])*1;
				$dato[$y]   = str_replace(".","-",$dato[$y]);
				$dato[$y]   = str_replace("/","-",$dato[$y]);
				if (!$amount[$y]) $skriv_linje[$y]=0;
				list($dag,$maaned,$aar)=explode("-",$dato[$y]);
				$maaned*=1; $dag*=1; $aar*=1;
				if (checkdate($maaned,$dag,$aar)) $date[$y]=usdate($dato[$y]);
				else $skriv_linje[$y]=0;
			}
		}
	}
	$linjeantal=$y;
	fclose ($fp);
	for ($x=1;$x<=$linjeantal;$x++) {
		if ($skriv_linje[$x]==1) {
#			$bilag++;
			$qtxt = "insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,faktura,kladde_id) values ('$bilag','$date[$x]','$beskrivelse[$x]','F','$kontonr[$x]','F','0','$amount[$x]','$faktura[$x]', '$kladde_id')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}	
	fclose($fp);
	unlink($filnavn); # sletter filen.
#	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
