<?php
// ----------finans/danlonimport.php------------patch 3.1.3-----2011.02.01-----------
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
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import fra Danl&oslash;n til kassekladde";
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
			$linje=trim(utf8_encode($linje));
			if ($linje) {
				$y++;
				$skriv_linje[$y]=1;
				list(,$date[$y],,$kontonr[$y],,$belob[$y],$beskrivelse[$y],)=explode(";",$linje);
				if (!is_numeric($kontonr[$y])) $skriv_linje[$y]=0;
				$amount[$y]=usdecimal($belob[$y])*1;
				if (!$amount[$y]) $skriv_linje[$y]=0;
				list($aar,$maaned,$dag)=explode("-",$date[$y]);
# echo "$maaned,$dag,$aar<br>";
				if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
				else $skriv_linje[$y]=0;
			}
		}
	}  
	$linjeantal=$y;
	fclose ($fp);
	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"danlonimport.php\" method=\"POST\">";
	#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
	print "<tr>";
#	<td colspan=\"5\" align=\"center\">Modkonto<input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=\"modkonto\" value=\"$modkonto\"> ";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
	print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
	print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
	if ($kladde_id && $filnavn) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
	print "<tr><td colspan=\"5\"><hr></td></tr>\n";
	print "<tr><td><span title='Angiv bilagsnummer'><input class=\"inputbox\" style=\"text-align:right;width:60px\" type=\"text\" name=bilag value=$bilag></span></td>";
	print "<td><b>Dato</b></td><td><b>Kontonr</b></td><td><b>Tekst</b></td><td><b>Bel&oslash;b</b></td></tr>";
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
		print "<tr bgcolor=\"$linjebg\" style=\"color:rgb($txtcolor);\"><td align=\"right\" width=\"10px\">$bilag</td><td>$dato[$x]</td><td>$debet[$x]</td><td>$beskrivelse[$x]</td><td>$belob[$x]</td></span></tr>";
	}
	print "</tbody></table>";
	print "</td></tr>";
} # function vis_data;

function flyt_data($kladde_id, $filnavn, $bilag){
	global $charset;

#	transaktion('begin');
	$fp=fopen("$filnavn","r");
	if ($fp) {
		$y=0;
		$feltantal=0;
#	for ($y=1; $y<20; $y++) {
		while ($linje=fgets($fp)) {
			$linje=trim(utf8_encode($linje));
			if ($linje) {
				$y++;
				$skriv_linje[$y]=1;
				list(,$date[$y],,$kontonr[$y],,$belob[$y],$beskrivelse[$y],)=explode(";",$linje);
				if (!is_numeric($kontonr[$y])) $skriv_linje[$y]=0;
				$amount[$y]=usdecimal($belob[$y])*1;
				if (!$amount[$y]) $skriv_linje[$y]=0;
				elseif ($amount[$y]<0) {
					$amount[$y]*=-1;
					$debet[$y]=0;
					$kredit[$y]=$kontonr[$y];
				} else {
					$debet[$y]=$kontonr[$y];
					$kredit[$y]=0;
				}

				list($aar,$maaned,$dag)=explode("-",$date[$y]);
# echo "$maaned,$dag,$aar<br>";
				if (checkdate($maaned,$dag,$aar)) $dato[$y]=dkdato($date[$y]);
				else $skriv_linje[$y]=0;
			}
		}
	}
	$linjeantal=$y;
	fclose ($fp);
	for ($x=1;$x<=$linjeantal;$x++) {
		if ($skriv_linje[$x]==1) {
#			$bilag++;
			db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,k_type,kredit,amount,kladde_id) values ('$bilag','$date[$x]','$beskrivelse[$x]','F','$debet[$x]','F','$kredit[$x]','$amount[$x]', '$kladde_id')",__FILE__ . " linje " . __LINE__);
		}
	}	
	fclose($fp);
	unlink($filnavn); # sletter filen.
#	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
