<?php
// ------------debitor/import.php------- patch 3.5.8---20135-09-04------
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
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012-08-22 Tilrettet til leverandørservice
// 2012-10-24 Søg #20121024
// 2013-05-16 Afmelding af leverandørservice#20130516
// 2015.09.04 Viser kundenavn og viser også detaljer selvom der hverken er til eller framelding.

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Import fra PBS";
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

print "<div align=\"center\">";

if(($_GET)||($_POST)) {

	$submit=$_POST['submit'];
	$filnavn=$_POST['filnavn'];
	$dd=date("Y-m-d");
	
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	if ($popup) print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=../includes/luk.php accesskey=L>Luk</a></td>";
	else print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=ordreliste.php? accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Import fra PBS</td>";
	print "<td width=\"10%\" $top_bund ><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<tr><td align=\"center\"><table border=\"1\"><tbody>";
	$r=db_fetch_array(db_select("select art,pbs_nr,pbs from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$lev_pbs_nr=$r['pbs_nr'];
	$lev_pbs=$r['pbs'];
	if ($lev_pbs) $udskriv_til="PBS_FI";
	else $udskriv_til="PBS_BS";
	$udskriv_til="PBS";
	if (!$lev_pbs_nr) print  "<tr><td>PBS nr mangler i stamdata (Indstillinger -> Stamdata)</td></tr>";
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".$bruger_id.".pbs";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			$BS001=0;$BS012=0;
			$fp=fopen("$filnavn","r");
			if ($fp) {
				$linjenr=0;
				print  "<tr><td><b>Kundenr</b></td><td><b>Navn</b></td><td><b>PBS nr</b></td><td><b>Handling</b></td></tr>";
				while ($linje=fgets($fp)) {
					$linjenr++;
					if ($lev_pbs=='L') {
						if (substr($linje,0,3)=='510') {
							$x++;
							$kundenr[$x]=substr($linje,8,15)*1;
							$pbsnr[$x]=99999;
							
							if ($r=db_fetch_array(db_select("select firmanavn,id from adresser where art = 'D' and kontonr='$kundenr[$x]'",__FILE__ . " linje " . __LINE__))) {
								print  "<tr><td>$kundenr[$x]</td><td>".$r['firmanavn']."</td><td>$pbsnr[$x]</td><td>Stamkort opdateret</td></tr>";
								db_modify("update adresser set pbs='on',pbs_nr='$pbsnr[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
								db_modify("insert into pbs_kunder(konto_id,kontonr,pbs_nr) values ('$r[id]','$kundenr[$x]','$pbsnr[$x]')",__FILE__ . " linje " . __LINE__); #20121024
								if ($udskriv_til) db_modify("update ordrer set pbs='FI',udskriv_til='$udskriv_til' where kontonr = '$kundenr[$x]' and art = 'DO' and nextfakt >= '$dd'",__FILE__ . " linje " . __LINE__);
							} else print "<tr><td>$kundenr[$x]</td><td colspan=\"3\">eksisterer ikke</td></tr>";
						} elseif (substr($linje,0,3)=='540') { #20130516
							$x++;
							$kundenr[$x]=substr($linje,8,15)*1;
							$pbsnr[$x]=NULL;
							if ($r=db_fetch_array(db_select("select firmanavn,id from adresser where art = 'D' and kontonr='$kundenr[$x]'",__FILE__ . " linje " . __LINE__))) {
								print  "<tr><td>$kundenr[$x]</td><td>".$r['firmanavn']."</td><td>$pbsnr[$x]</td><td>Afmeldt fra NETS (PBS)</td></tr>";
								db_modify("update adresser set pbs=NULL,pbs_nr='$pbsnr[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
								db_modify("delete from pbs_kunder where id='$r[id]'",__FILE__ . " linje " . __LINE__); #20121024
								if ($udskriv_til) db_modify("update ordrer set pbs=NULL,udskriv_til='PDF' where kontonr = '$kundenr[$x]' and art = 'DO' and nextfakt >= '$dd'",__FILE__ . " linje " . __LINE__);
							} else print "<tr><td>$kundenr[$x]</td><td colspan=\"3\">eksisterer ikke</td></tr>";
						} 					
					} else {
						if ($linjenr==1 && substr($linje,16,4)!='0603') {
							print "<BODY onLoad=\"javascript:alert('Filen indeholder ingen aftaleoplysninger!')\">";
							print "<meta http-equiv=\"refresh\" content=\"0;URL=pbs_import.php\">";
						} else {
						if (substr($linje,0,5)=='BS002') {
							if (!$BS002) $BS002=1;
							else $BS002=0;
						}
						if ($BS002) {
							if (substr($linje,0,5)=='BS012') {
								if (!$BS012) $BS012=1;
								else $BS012=0;
							}
							if ($BS012) {
								if (substr($linje,0,5)=='BS042') {
									$x++;
									$kundenr[$x]=substr($linje,25,15)*1;
									$pbsnr[$x]=substr($linje,40,9);
									$tilfra[$x]=substr($linje,13,4); 
									$r=db_fetch_array(db_select("select firmanavn from adresser where kontonr='$kundenr[$x]'",__FILE__ . " linje " . __LINE__));
									$firmanavn[$x]=$r['firmanavn'];
									if ($tilfra[$x]=='0231') {	
										print  "<tr><td>$kundenr[$x]</td><td>$firmanavn</td><td>$pbsnr[$x]</td><td>Tilmeldt - Stamkort opdateret</td></tr>";
										db_modify("update adresser set pbs='on',pbs_nr='$pbsnr[$x]' where kontonr = '$kundenr[$x]' and art = 'D'",__FILE__ . " linje " . __LINE__);
										if ($udskriv_til) db_modify("update ordrer set pbs='FI',udskriv_til='$udskriv_til' where kontonr = '$kundenr[$x]' and art = 'DO' and nextfakt >= '$dd'",__FILE__ . " linje " . __LINE__);
									} elseif ($tilfra[$x]=='0232' || $tilfra[$x]=='0233' || $tilfra[$x]=='0234') {
										if ($tilfra[$x]=='0232') print "<tr><td>$kundenr[$x]</td><td>$firmanavn[$x]</td><td>$pbsnr[$x]</td><td>Afmeldt af debitors pengeinstitut - Stamkort opdateret</td></tr>";
										if ($tilfra[$x]=='0233') print "<tr><td>$kundenr[$x]</td><td>$firmanavn[$x]</td><td>$pbsnr[$x]</td><td>Afmeldt af kreditor pengeinstitut - Stamkort opdateret</td></tr>";
										if ($tilfra[$x]=='0234') print "<tr><td>$kundenr[$x]</td><td>$firmanavn[$x]</td><td>$pbsnr[$x]</td><td>Afmeldt af PBS - Stamkort opdateret</td></tr>";
										db_modify("update adresser set pbs='',pbs_nr='' where kontonr = '$kundenr[$x]' and art = 'D'",__FILE__ . " linje " . __LINE__);
										db_modify("update ordrer set pbs='',udskriv_til='email' where kontonr = '$kundenr[$x]' and art = 'DO' and nextfakt >= '$dd'",__FILE__ . " linje " . __LINE__);
									} else { #20150904
										if ($firmanavn[$x]) print  "<tr><td>$kundenr[$x]</td><td>$firmanavn[$x]</td><td>$pbsnr[$x]</td><td>Ingen handling</td></tr>";
										else print  "<tr><td>$kundenr[$x]</td><td colspan=\"3\">Ikke fundet i adresseliste</td></tr>";
									}
									
								}
							}
						}
					}
					}
				}
				print "</tbody></table>";
				print  "</td></tr>";
			}
		}
	} else upload();
} else upload();
print "</tbody></table>";
################################################################################################################
function upload(){
global $charset;

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td width=100% align=center>Import af PBS data</td></tr>";
print "<tr><td width=100% align=center><br></td></tr>";
print "<form enctype=\"multipart/form-data\" action=\"pbs_import.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "<tr><td><br></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function vis_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag){
global $charset;
global $bruger_id;

$fp=fopen("$filnavn","r");
if ($fp) {
	for ($y=1; $y<10; $y++) {
		$linje=fgets($fp); 
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,";"),1)) $semikolon++;	
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,","),1)) $komma++;
		$tmp=$linje;
		while ($tmp=substr(strstr($tmp,chr(9)),1)) $tabulator++;
		$tmp='';
	}
	if (($komma>$semikolon)&& ($komma>$tabulator)) {$tmp='Komma'; $feltantal=$komma;}
	elseif (($semikolon>$tabulator)&&($semikolon>$komma)) {$tmp='Semikolon'; $feltantal=$semikolon;}			
	elseif (($tabulator>$semikolon)&&($tabulator>$komma)) {$tmp='Tabulator'; $feltantal=$tabulator;}			

	if (!$splitter) {$splitter=$tmp;}
	if ($splitter=='Komma') $feltantal=$komma;
	elseif ($splitter=='Semikolon') $feltantal=$semikolon;
	elseif ($splitter=='Tabulator') $feltantal=$tabulator;
	$cols=$feltantal+1;
}
fclose($fp);

$fp=fopen("$filnavn","r");
if ($fp) {
	if ($splitter=='Komma') $splittegn=",";
	elseif ($splitter=='Semikolon') $splittegn=";";
	elseif ($splitter=='Tabulator') $splittegn=chr(9);
	
	$y=0;
	$feltantal=0;
#	for ($y=1; $y<20; $y++) {
	while ($linje=fgets($fp)) {
		$linje=trim($linje);
		if ($linje) {
			$y++;
			if ($charset=='UTF-8') $linje=utf8_encode($linje);
			$anftegn=0;
				$felt=array();
				$z=0;
				for ($x=0; $x<strlen($linje);$x++) {
				if ($x==0 && substr($linje,$x,1)=='"') {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif ($x==0) {
					$z++; $felt[$z]=substr($linje,$x,1);
				} elseif (substr($linje,$x,1)=='"' && substr($linje,$x-1,1)==$splittegn && !$anftegn) {
					$z++; $anftegn=1; $felt[$z]='';
				} elseif (substr($linje,$x,1)=='"' && (substr($linje,$x+1,1)==$splittegn || $x==strlen($linje)-1)) {
					$anftegn=0;
					if (substr($linje,$x+2,1)=='"') $x++;
#					if ($x==strlen($linje)) $z--;
				}	elseif (!$anftegn && substr($linje,$x,1)==$splittegn) {
					$z++; $felt[$z]='';
					if (substr($linje,$x+1,1)=='"') $x++;
				} else {
					$felt[$z]=$felt[$z].substr($linje,$x,1);
				} 
			}
			if ($z>$feltantal) $feltantal=$z-1;
			for ($x=1; $x<=$z; $x++) {
				$ny_linje[$y]=$ny_linje[$y].$felt[$x].chr(9);
#				echo "$felt[$x]|".chr(9);	
			}
			$x++;
			$ny_linje[$y]=$ny_linje[$y].$felt[$x]."\n";
#			echo "$felt[$x]<br>";
		}
	}
}  
$linjeantal=$y;
#$cols=$feltantal;
fclose ($fp);
$fp=fopen($filnavn."2","w");
for ($y=1; $y<=$linjeantal;$y++) {
	fwrite($fp,$ny_linje[$y]);
}
fclose ($fp);
print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"1\" cellpadding=\"1\"><tbody>";
print "<form enctype=\"multipart/form-data\" action=\"importer.php\" method=\"POST\">";
#print "<tr><td colspan=6 width=100% align=center> $filnavn</td></tr>";
print "<tr><td colspan=$cols align=center><span title='Angiv hvilket skilletegn der anvendes til opdeling af kolonner'>Separatortegn&nbsp;<select name=splitter>\n";
if ($splitter) {print "<option>$splitter</option>\n";}
if ($splitter!='Semikolon') print "<option>Semikolon</option>\n";
if ($splitter!='Komma') print "<option>Komma</option>\n";
if ($splitter!='Tabulator') print "<option>Tabulator</option>\n";
print "</select></span>";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<input type=\"hidden\" name=\"feltantal\" value=$feltantal>";
print "<input type=\"hidden\" name=\"kladde_id\" value=$kladde_id>";
print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Vis\" />";
if (($kladde_id)&&($filnavn)&&($splitter)) print "&nbsp; <input type=\"submit\" name=\"submit\" value=\"Flyt\" /></td></tr>";
print "<tr><td colspan=$cols><hr></td></tr>\n";
$fil_splitter=$splitter;
#if ((!$splitter)||($splitter=='Semikolon')) {$splitter=';';}
#elseif ($splitter=='Komma') {$splitter=',';}
#elseif ($splitter=='Tabulator') {$splitter=chr(9);}
$splitter=chr(9);
print "<tr><td><span title='Angiv 1. bilagsnummer'><input type=text size=4 name=bilag value=$bilag></span></td>";
for ($y=0; $y<=$feltantal; $y++) {
	if (($feltnavn[$y]=='Dato') &&($dato==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Dato')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Beskrivelse') &&($beskr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Beskrivelse')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Debitor') &&($debitor==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Debitor')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Kreditor') &&($kreditor==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Kreditor')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Debet') &&($debet==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Debet')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Kredit') &&($kredit==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Kredit')\">";
		$feltnavn[$y]='';
	}
	if (($feltnavn[$y]=='Fakturanr') &&($fakturanr==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Fakturanr')\">";
		$feltnavn[$y]='';
	}
	if ((strstr($feltnavn[$y],'Bel'))&&($belob==1)) {
		print "<BODY onLoad=\"javascript:alert('Der kan kun v&aelig;re 1 kolonne med Bel&oslash;b')\">";
		$feltnavn[$y]='';
	}
	if (strstr($feltnavn[$y],'Bel')) print "<td align=right><select name=feltnavn[$y]>\n";
	elseif ($feltnavn[$y]) print "<td><select name=feltnavn[$y]>\n";
	else  print "<td align=center><select name=feltnavn[$y]>\n";
	print "<option>$feltnavn[$y]</option>\n";
	if ($feltnavn[$y]) print "<option></option>\n";
	if ($feltnavn[$y]!='Dato') print "<option>Dato</option>\n";
	else $dato=1;
	if ($feltnavn[$y]!='Beskrivelse') print "<option>Beskrivelse</option>\n";
	else $beskr=1;
	if ($feltnavn[$y]!='Debet') print "<option>Debet</option>\n";
	else $debet=1;
	if ($feltnavn[$y]!='Kredit') print "<option>Kredit</option>\n";
	else $kredit=1;
	if ($feltnavn[$y]!='Debitor') print "<option>Debitor</option>\n";
	else $debitor=1;
	if ($feltnavn[$y]!='Kreditor') print "<option>Kreditor</option>\n";
	else $kreditor=1;
	if ($feltnavn[$y]!='Fakturanr') print "<option>Fakturanr</option>\n";
	else $fakturanr=1;
	if (!strstr($feltnavn[$y],'Bel')) print "<option>Bel&oslash;b</option>\n";
	else $belob=1;
	print "</select>";
}
print "</form>";
$fp=fopen($filnavn."2","r");
if ($fp) {
	$x=0;
	while($linje=fgets($fp)) {
#	while (!feof($fp)) {
		$skriv_linje=0;
		if ($linje=trim($linje)) {
			$x++;
			$skriv_linje=1;
			$felt=array();
			$felt = explode($splitter, $linje);
			for ($y=0; $y<=$feltantal; $y++) {
				$felt[$y]=trim($felt[$y]);
				if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
				if ($feltnavn[$y]=='Dato') $felt[$y]=str_replace(".","-",$felt[$y]);
				if (strstr($feltnavn[$y],'Bel')) {
					if (nummertjek($felt[$y])=='US') {
						if ($felt[$y]==0) $skriv_linje=0;
						else $felt[$y]=dkdecimal($felt[$y]);
					} elseif (nummertjek($felt[$y])=='DK') {
						if (usdecimal($felt[$y])==0) $skriv_linje=0;
					}	else $skriv_linje=0;		
				}
			}
 		}		
		if ($skriv_linje==1){
			print "<tr><td>$bilag</td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if (strstr($feltnavn[$y],'Bel')) {
					print "<td align=right>$felt[$y]&nbsp;</td>";
				}
				elseif ($feltnavn[$y]) {print "<td>$felt[$y]&nbsp;</td>";}
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
			$bilag++;
		} else {
			print "<tr><td><span style=\"color: rgb(153, 153, 153);\">-</span></td>";
			for ($y=0; $y<=$feltantal; $y++) {
				if (strstr($feltnavn[$y],'Bel')) {
					print "<td align=right><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				} elseif ($feltnavn[$y]) print "<td><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";
				else {print "<td align=center><span style=\"color: rgb(153, 153, 153);\">$felt[$y]&nbsp;</span></td>";}
			}
			print "</tr>";
		}	
	}
}
fclose($fp);
print "</tbody></table>";
print "</td></tr>";
db_modify("update grupper set box1='$feltantal' where ART='KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
for ($y=0; $y<=$feltantal; $y++) {
	$box=$y+2;
	$box="box$box";
	db_modify("update grupper set $box='$feltnavn[$y]' where ART='KASKL' and kode='2' and kodenr='$bruger_id'",__FILE__ . " linje " . __LINE__);
}
} # function slut;

function flyt_data($kladde_id, $filnavn, $splitter, $feltnavn, $feltantal, $bilag){
	global $charset;

	transaktion('begin');
	$splitter=chr(9);
	$fp=fopen($filnavn."2","r");
	if ($fp) {
		$x=0;
		while (!feof($fp)) {
			$skriv_linje=0;
			if ($linje=trim(fgets($fp))) {
				$x++;
				$skriv_linje=1;
				$felt=array();
				$felt = explode($splitter, $linje);
				for ($y=0; $y<=$feltantal; $y++) {
					$felt[$y]=trim($felt[$y]);
					if ((substr($felt[$y],0,1) == '"')&&(substr($felt[$y],-1) == '"')) $felt[$y]=substr($felt[$y],1,strlen($felt[$y])-2);
					if ($feltnavn[$y]=='Dato') $felt[$y]=str_replace(".","-",$felt[$y]);
					if (strstr($feltnavn[$y],'Bel')) {
						if (nummertjek($felt[$y])=='US') $felt[$y]=dkdecimal($felt[$y]);
						elseif (nummertjek($felt[$y])!='DK') $skriv_linje=0;		
					}
				}
 			}		
			if ($skriv_linje==1){
				for ($y=0; $y<=$feltantal; $y++) {
					$bilag=$bilag*1;
					if (strstr($feltnavn[$y],'Bel')) $amount=usdecimal($felt[$y]);
					elseif ($feltnavn[$y]=="Dato") $transdate=usdate($felt[$y]);
					elseif ($feltnavn[$y]=="Beskrivelse") $beskrivelse=addslashes($felt[$y]);
					elseif ($feltnavn[$y]=="Debet") {
						$d_type="F";
						$debet=$felt[$y];
					} elseif ($feltnavn[$y]=="Kredit") {
						$d_type="F";
						$kredit=$felt[$y];
					} elseif ($feltnavn[$y]=="Debitor") {
						$d_type="D";
						$debet=$felt[$y];
					} elseif ($feltnavn[$y]=="Kreditor") {
						$k_type="K";
						$kredit=$felt[$y];
					} elseif ($feltnavn[$y]=="Fakturanr") $fakturanr=addslashes($felt[$y]);
				}
				if ($amount*1!=0) {
#					$debet=$debet*1;$kredit=$kredit*1;
					$felttext1=NULL;$felttext2=NULL;
					if (is_numeric($debet)) {
						$felttext1 = "d_type,debet,";
						$felttext2 = "'$d_type','$debet',";
					}
					if (is_numeric($kredit)) {
						$felttext1 = $felttext1."k_type,kredit,";
						$felttext2 = $felttext2."'$k_type','$kredit',";
					}
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse,$felttext1 faktura, amount, kladde_id) values ('$bilag', '$transdate', '$beskrivelse',$felttext2 '$fakturanr','$amount', '$kladde_id')",__FILE__ . " linje " . __LINE__);
					$bilag++;
				}
			}
		}
	}	
	fclose($fp);
	unlink($filnavn); # sletter filen.
	unlink($filnavn."2"); # sletter filen.
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
}
function nummertjek ($nummer){
	$nummer=trim($nummer);
	$retur=1;
	$nummerliste=array("1", "2", "3", "4", "5", "6", "7", "8", "9", "0", ",", ".", "-");
	for ($x=0; $x<strlen($nummer); $x++) {
		if (!in_array($nummer{$x}, $nummerliste)) $retur=0;
	}
	if ($retur) {
		for ($x=0; $x<strlen($nummer); $x++) {
			if ($nummer{$x}==',') $komma++;
			elseif ($nummer{$x}=='.') $punktum++;		
		}
		if ((!$komma)&&(!$punktum)) $retur='US';
		elseif (($komma==1)&&(substr($nummer,-3,1)==',')) $retur='DK';
		elseif (($punktum==1)&&(substr($nummer,-3,1)=='.')) $retur='US';
		elseif (($komma==1)&&(!$punktum)) $retur='DK';
		elseif (($punktum==1)&&(!$komma)) $retur='US';	
	}
	return $retur;
}
	
