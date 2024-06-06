<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// ------ systemdata/importer_varelokationer.php ------------ lap 3.6.4 -- 2016-02-19 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2016 DANOSOFT ApS
// ----------------------------------------------------------------------------

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import&eacute;r varelokationer";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">\n";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>\n"; 
else print "<td width=\"10%\" $top_bund><a href=diverse.php?sektion=div_io accesskey=L>Luk</a></td>\n";
print "<td width=\"80%\" $top_bund>$title</td>\n";
print "<td width=\"10%\" $top_bund><br></td>\n";
print "</tbody></table>\n";
print "</td></tr>\n";

$submit=if_isset($_POST['submit']);

if($submit) {
	if (strstr($submit, "Import")) $submit="Importer";
	$filnavn=$_POST['filnavn'];
	$splitter=$_POST['splitter'];
	$lager=$_POST['lager'];
	$tegnset=$_POST['tegnset'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			overfoer_data($filnavn,$lager,$tegnset);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	}
} else upload();

print "</tbody></table>";
print "</body></html>";
#####################################################################################################
function upload($bilag){
	$x=0;
	$q=db_select("select * from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$lagernr[$x]=$r['kodenr'];
		$lagernavn[$x]=$r['beskrivelse'];
		$x++;
	}
	print "<form enctype='multipart/form-data' action='importer_varelokationer.php' method='POST'>\n";
	print "<tr><td width='100%' align='center'><table border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	#print "<input type='hidden' name='MAX_FILE_SIZE' value='900000'>\n";
	print "<tr><td colspan='2'>Der kan importeres lokationer for 1 lager ad gangen</td></tr>";
	print "<tr><td colspan='2>Filen skal bestå af 2 kolonner adskilt af \";\" eller tabulatortegn</td></tr>";
	print "<tr><td colspan='2>Kolonne 1 skal indeholde varenummer, kolonne 2 skal indeholde lokationen</td></tr>";
	print "<tr><td colspan='2><hr></td></tr>";
	print "<tr><td>Tegnsæt</td>";
	print "<td align=\"center\"><select style=\"width:160px;text-align:left;\" name=\"tegnset\">";
	print "<option value=\"ISO-8859-1\">ISO-8859-1 (Windows)</option>";
	print "<option value=\"UTF-8\">UTF-8 (Mac/Linux)</option>";
	print "</select></td></tr><tr><td colspan='2'><hr></td></tr>";	
	print "<tr><td>Lager</td>";
	print "<td align=\"center\"><select style=\"width:160px;text-align:left;\" name=\"lager\">";
	for ($i=0;$i<count($lagernr);$i++) {
		print "<option value=\"$lagernr[$i]\">$lagernr[$i]:$lagernavn[$i]</option>";
	}
	print "</select></td></tr><tr><td colspan='2'><hr></td></tr>";	
	print "<tr><td colspan='2' align='center'><input type='hidden' name='MAX_FILE_SIZE' value='2900000'>\n";
	
	print "<input name='uploadedfile' type='file' /><br /></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td colspan= '2' align='center'><input type='submit' style=\"width:200px\" name='submit' value='Hent' /></td></tr>\n";
	#print "</tbody></table>\n";
	#print "</td></tr>\n";
	print "<tr><td>&nbsp;</td></tr>\n";
	print "</form>\n";
} # end function upload


function overfoer_data($filnavn,$lager,$charset) {
	global $charset;

	$upd=0;
	$imp=0;
	$x=0;
	$fp=fopen("$filnavn","r");
	if ($fp) {
		for ($y=1; $y<4; $y++) {
			$tmp=fgets($fp);
			if($tmp) $linje=$tmp;
		}
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,";"),1)) {$semikolon++;}
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,","),1)) {$komma++;}
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,chr(9)),1)) {$tabulator++;}
		$tmp='';
		if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
		elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}
		elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}
		if (!$splitter) {$splitter=$tmp;}
	}
	fclose($fp);

	print "<tr><td colspan=2><hr></td></tr>\n";
	if ((!$splitter)||($splitter=='Semikolon')) $splitter=';';
	elseif ($splitter=='Komma') $splitter=',';
	elseif ($splitter=='Tabulator') $splitter=chr(9);

	transaktion('begin');

	$l=0;
	$q=db_select("select id,vare_id from lagerstatus where lager='$lager' order by vare_id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$l_id[$l]=$r['id'];
		$l_vid[$l]=$r['vare_id'];
#cho __line__." $l_id[$l] -> $l_vid[$l]<br>";		
		$l++;
	}

	$v=0;
	$q=db_select("select id,varenr from varer order by id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$v_id[$v]=$r['id'];
		$v_nr[$v]=$r['varenr'];
		$vl_id[$v]=NULL;
		if (in_array($v_id[$v],$l_vid)) {
			for ($l=0;$l<count($l_id);$l++){
#cho __line__." if ($l_vid[$l]==$v_id[$v])<br>"; 
					if ($l_vid[$l]==$v_id[$v]) {
					$vl_id[$v]=$l_id[$l];
#cho __line__." if $vl_id[$v]=$l_vid[$l];<br>"; 
					break 1;
				}
			}
		}
		$v++;
	}

	$fp=fopen("$filnavn","r");
	if ($fp) {
		$x=0;
		$imp_antal=0;
		$upd_antal=0;
		while (!feof($fp)) {
			$skriv_linje=0;
			if ($linje=fgets($fp)) {
				$x++;
				if ($charset=='UTF-8' && $tegnset!='UTF-8') $linje=utf8_encode($linje);
				elseif ($charset!='UTF-8' && $tegnset=='UTF-8') $linje=utf8_decode($linje);
				list($varenr,$lokation)=explode($splitter,$linje);
				$vare_id=NULL;
				$lok_id=NULL;
				for ($v=0;$v<count($v_id);$v++) {
					if ($v_nr[$v]==$varenr)	{
						$vare_id=$v_id[$v];
						$lok_id=$vl_id[$v];
#cho __line__." $vare_id $lok_id<br>";
						}
					if (!$vare_id) {
						for ($v=0;$v<count($v_id);$v++) {
							if (strtolower($v_nr[$v])==strtolower($varenr)) {
								$vare_id=$v_id[$v];
								$lok_id=$vl_id[$v];
#cho __line__." $vare_id $lok_id<br>";
							}
						}
					}
					if (!$vare_id && is_numeric($varenr)) { #20161015
						for ($v=0;$v<count($v_id);$v++) {
							if (is_numeric($v_nr[$v]) && $v_nr[$v]*1==$varenr*1) {
								$vare_id=$v_id[$v];
								$lok_id=$vl_id[$v];
#cho __line__." $vare_id $lok_id<br>";
							}
						}
					}
				}
				if (!$vare_id) print "<tr><td align='center'>Varenr \"$varenr\" ikke fundet i varelisten</td></tr>";
				else {
					$qtxt=NULL;
					if ($lok_id) {
						$qtxt="update lagerstatus set lok1='$lokation' where id=$lok_id";
						$upd++;
					} elseif ($lokation) {
						$qtxt="insert into lagerstatus(vare_id,beholdning,lager,lok1) values ('$vare_id','0','$lager','$lokation')";
						$imp++;
					}
					#cho "$qtxt<br>";
					if ($qtxt) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					#cho "$vare_id har ingen lokation<br>";
				}
			}
		}
		fclose($fp);
	}
	transaktion('commit');
	print "<tr><td align='center'>$upd lokationer opdateret i lager $lager</td></tr>";
	print "<tr><td align='center'>$imp lokationer oprettet i lager $lager</td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}
?>
