<?php
// -------------debitor/csv2ordre.php----------lap 3.4.3-----2014-08-06----
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// -----------------------------------------------------------------------


@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="Importer ordrer fra CSV";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

print "<div align=\"center\">";

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund><a href=ordreliste.php?valg=tilbud accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

if($_POST) {
	$submit=$_POST['submit'];
	$gruppe=$_POST['gruppe'];

	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			overfoer_data($filnavn, $splitter, $feltnavn, $feltantal);
		}
	}
} else {
	upload();
}

print "</tbody></table>";
#####################################################################################################
function upload(){
	global $charset;

	$x=0;
	$q=db_select("select kodenr,beskrivelse from grupper where art='DG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array ($q)){
		$gruppe[$x]=$r['kodenr'];
		$gruppebeskr[$x]=$r['beskrivelse'];
		$x++;
	}
	print "<tr><td width=100% align=center><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td colspan=\"2\">Denne funktion importerer ordrer fra en tabulatorsapareret fil til ordrer</td></tr>";
	print "<tr><td colspan=\"2\">Filen skal have følgende format:</td></tr>";
	$txt=HtmlEntities("Kundenr<tab>Ordrenr<tab>Dato<tab>Projekt<tab>Telefon<tab>Navn<tab>Adresse1<tab>Adresse2<tab>Postnr<tab>Bynavn<tab>Email<tab>Varenummer<tab>Varenavn<tab>Antal<tab>Pris",ENT_COMPAT,$charset);
	$txt.="<br>".HtmlEntities("Hvis kundenummer ikke eksisterer i forvejen, oprettes en ny kunde i den valgte debitorgruppe.",ENT_COMPAT,$charset);
	$txt.="<br>".HtmlEntities("Hvis der ikke er angivet varenummer søges efter vare med samme navn. Hvis denne ikke findes, indsættes linjen som kommentar.",ENT_COMPAT,$charset);
	print "<tr><td colspan=\"2\">$txt<br></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<form enctype=\"multipart/form-data\" action=\"csv2ordre.php\" method=\"POST\">";
	print "<tr><td width=\"150px\">Debitorgruppe</td><td align=\"right\"><select name=\"gruppe\" style=\"width:150px\">\n";
	for ($x=0;$x<count($gruppe);$x++)	print "<option value=\"$gruppe[$x]\">$gruppe[$x] $gruppebeskr[$x]</option>\n";
	print "</select></span></td></tr>";
	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"900000\">";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td> V&aelig;lg datafil:</td><td><input name=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td colspan=\"2\" align=center><input type=\"submit\" value=\"Hent\"></td></tr>";
	print "</form>";
	print "</tbody></table>";
	print "</td></tr>";
}


function overfoer_data($filnavn){
	global $charset;
	global $gruppe;
	
	$betalingsbet='Netto';
	$betalingsdage=8;

	$x=0;
	$q=db_select("select kontonr from adresser where art = 'D'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array ($q)){
		$kontonumre[$x]=$r['kontonr'];
		$x++;
	}
	$imp_antal=0;

	$fp=fopen("$filnavn","r");
	if ($fp) {
		$pre_kontonr=0;
		transaktion('begin');
		$fp=fopen("$filnavn","r");
		if ($fp) {
			$x=0;
			$imp_antal=0;
			while (!feof($fp)) {
				$skriv_linje=0;
				if ($linje=fgets($fp)) {
				$x++;
				$skriv_linje=1;
				if ($charset=='UTF-8') $linje=utf8_encode($linje);
				if ($x) $pre_kontonr=$kontonr;
				if (strpos($linje,chr(9))) list($kontonr,$ordrenr,$dato,$projekt,$telefon,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$email,$varenr,$varenavn,$antal,$pris)=explode(chr(9),$linje);
				elseif (strpos($linje,';')) list($kontonr,$ordrenr,$dato,$projekt,$telefon,$firmanavn,$addr1,$addr2,$postnr,$bynavn,$email,$varenr,$varenavn,$antal,$pris)=explode(';',$linje);
				if (!is_numeric($kontonr)) $skriv_linje=0; 
				if ($skriv_linje==1) {
					if (!in_array($kontonr,$kontonumre)) {
						db_modify("insert into adresser(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,tlf,gruppe,art,betalingsbet,betalingsdage) values ('$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."','".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($email)."','".db_escape_string($telefon)."','$gruppe','D','$betalingsbet','$betalingsdage')",__FILE__ . " linje " . __LINE__);
						$kontonumre[count($kontonumre)]=$kontonr;
					}
					if ($pre_kontonr!=$kontonr) {
						$qtxt="select id from adresser where art='D' and kontonr = '$kontonr'";
						$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
						$konto_id=$r['id'];
					
						$qtxt="select max(ordrenr) as ordrenr from ordrer where art='DO'";
						$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
						$ordrenr=$r['ordrenr']+1;

						$projektnr=0;
						if ($projekt) {
							$qtxt="select kodenr from grupper where art='PRJ' and beskrivelse = '$projekt'";
							$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
							$projektnr=$r['kodenr'];
						}
						$qtxt="select box1 from grupper where art='DG' and kodenr = '$gruppe'";
						$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
						$momsgruppe=str_replace('S','',$r['box1']);
						$qtxt="select box2 from grupper where art='SM' and kodenr = '$momsgruppe'";
						$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
						$momssats=$r['box2']*1;
						
						db_modify("insert into ordrer(ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,art,projekt,momssats,betalingsbet,betalingsdage,status,ordredate) values ('$ordrenr','$konto_id','$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($addr1)."','".db_escape_string($addr2)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($email)."','DO','$projektnr','$momssats','$betalingsbet','$betalingsdage','0','".usdate($dato)."')",__FILE__ . " linje " . __LINE__);
						$r=db_fetch_array($q=db_select("select max(id) as id from ordrer where kontonr='$kontonr'",__FILE__ . " linje " . __LINE__));
						$ordre_id=$r['id'];
						$posnr=0;
						$imp_antal++;
					}
					$posnr++;
					if ($varenr) $qtxt="select id,varenr,salgspris,beskrivelse from varer where varenr = '$varenr'";
					else $qtxt="select id,varenr,salgspris,beskrivelse from varer where beskrivelse = '$varenavn'";
					$r=db_fetch_array(db_select("$qtxt",__FILE__ . " linje " . __LINE__));
					if ($r['id']) {
						opret_ordrelinje($ordre_id,$r['id'],$r['varenr'],usdecimal($antal),$varenavn,usdecimal($pris),0,100,'DO','',$posnr,'0','on','','','0');
						} else {
							if (!$varenavn) $varenavn="ukendt, $antal stk á $pris";
							db_modify("insert into ordrelinjer(ordre_id,posnr,beskrivelse) values ('$ordre_id','$posnr','".db_escape_string($varenavn)."')",__FILE__ . " linje " . __LINE__);
						}
					}
				}
			}
		}
		fclose($fp);
		transaktion('commit');
	}	
	print "</tbody></table>";
	print "</td></tr>";
	print "<BODY onLoad=\"javascript:alert('$imp_antal adresser importeret')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../debitor/ordreliste.php?valg=tilbud\">";
	exit;
} # endfunc overfoer_data

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
	return $retur=chr(32);
}
