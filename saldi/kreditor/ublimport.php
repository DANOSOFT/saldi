<?php
//                         ___   _   _   ___  _
//                        / __| / \ | | |   \| |
//                        \__ \/ _ \| |_| |) | |
//                        |___/_/ \_|___|___/|_|
//
// -------------kreditor/ublimport.php----------lap 3.6.6-----2016-12-07----
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
// Copyright (c) 2004-2016 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2013.02.10 Break ændret til break 1
// 2013.09.16 Tilføjet import fra pulje
// Rettet puljesti - søg nfs_mount

@session_start();
$s_id=session_id();
$css="../css/standard.css";

$title="OIOUBL import";
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (file_exists("../owncloud")) $nfs_mappe='owncloud';
elseif (file_exists("../bilag")) $nfs_mappe='bilag';

print "<div align=\"center\">";
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\" colspan=\"3\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#if ($popup) print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>"; 
if (isset($_POST['descfil']) || isset($_GET['funktion'])) print "<td width=\"10%\" $top_bund><a href=ordreliste.php accesskey=L>Luk</a></td>";
else print "<td width=\"10%\" $top_bund><a href=ordre.php accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$title</td>";
print "<td width=\"10%\" $top_bund><br></td>";
print "</tbody></table>";
print "</td></tr>";

$funktion=if_isset($_GET['funktion']);


if($_POST['hent'] || $_POST['importer'] || $_POST['opret'] || $_POST['tilknyt']) {
#  $submit=$_POST['submit'];
	if (basename($_FILES['uploadedfile']['name'])) {
		$filnavn="../temp/".$db."_".str_replace(" ","_",$brugernavn).".csv";
		if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $filnavn)) {
			importer($filnavn,0,0,$nfs_mappe);
		} else echo "Der er sket en fejl under hentningen, pr&oslash;v venligst igen";
	} elseif ($filnavn=$_POST['filnavn']) {
		$opret_lev=if_isset($_POST['opret_lev']);	
		$opret_vare=if_isset($_POST['opret_vare']);	
		$opret_varenr=if_isset($_POST['opret_varenr']);	
		$gl_varenr=if_isset($_POST['gl_varenr']);	
		$salgspris=if_isset($_POST['salgspris']);	
		importer($filnavn,$opret_lev,$opret_vare,$nfs_mappe);
	} 
} elseif ($_POST['indsaet']) {
	$filnavn=if_isset($_POST['filnavn']);
	importer($filnavn,$opret_lev,$opret_vare,$nfs_mappe);
} elseif (isset($_POST['pulje'])) {
	$puljefil=if_isset($_GET['puljefil']);
	gennemse($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$puljefil,$nfs_mappe);
}elseif (isset($_POST['pulje'])||$funktion=='gennemse') {
	$puljefil=if_isset($_GET['puljefil']);
	gennemse($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$puljefil,$nfs_mappe);
} else upload($filnavn,$nfs_mappe);

print "</tbody></table>";
#####################################################################################################
function upload($filnavn,$nfs_mappe){

print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
/*
$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
if($box6=$r['box6']) {
	print "<form name=\"pulje\" action=\"ublimport.php?sort=$sort&kilde=$kilde\"; method=\"post\">";
	print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
	print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
	print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
	print "<tr><td width=100% align=center> V&aelig;lg bilag fra pulje: <input class=\"inputbox\" name=\"pulje\" type=\"submit\" value=\"Gennemse\"/><br /></td></tr>";
	print "</form>";
}
*/
print "<form enctype=\"multipart/form-data\" action=\"ublimport.php\" method=\"POST\">";
print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"900000\">";
print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
print "<tr><td width=100% align=center> V&aelig;lg datafil: <input name=\"uploadedfile\" type=\"file\" /><br /></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td align=center><input type=\"submit\" name=\"hent\" value=\"Hent\" /></td></tr>";
print "<tr><td></form></td></tr>";
print "</tbody></table>";
print "</td></tr>";
}

function importer($filnavn,$opret_lev,$opret_vare,$nfs_mappe){
	$descfil=if_isset($_POST['descfil']);

#	list($firmanavn,$kontonr,$vejnavn,$husnr,$postnr,$bynavn,$land,$cvrnr,$ordredate,$l_varenr,$l_pris,$l_tekst,$l_antal,$l_sum,$l_moms,$l_momssats,$l_posnr)=explode(chr(9),vis_oioubl($filnavn));
	global $firmanavn;
	global $kontonr;
	global $vejnavn;
	global $husnr;
	global $postnr;
	global $bynavn;
	global $land;
	global $cvrnr;
	global $ordredate;
	global $fakturadate;
	global $l_varenr;
	global $l_pris;
	global $l_tekst;
	global $l_antal;
	global $l_sum;
	global $l_moms;
	global $l_momssats;
	global $l_posnr;
	
	vis_oioubl($filnavn,$nfs_mappe);
	
	if ($opret_lev) {
		if (!$kontonr) {
		 	$x=0;
 		 	$q = db_select("select kontonr from adresser where art = 'K' order by kontonr",__FILE__ . " linje " . __LINE__);
 		 	while ($r = db_fetch_array($q)) {
 		 	 	$x++;
 		 	 	$ktoliste[$x]=$r['kontonr'];
 			}
 			$kontonr=1000;
 			while(in_array($kontonr, $ktoliste)) $kontonr++;
		}
		db_modify("insert into adresser (firmanavn,kontonr,addr1,postnr,bynavn,land,cvrnr,gruppe,art) values ('".db_escape_string($firmanavn)."','$kontonr','".db_escape_string($vejnavn." ".$husnr)."','".db_escape_string($postnr)."','".db_escape_string($bynavn)."','".db_escape_string($land)."','".db_escape_string($cvrnr)."','$opret_lev','K')",__FILE__ . " linje " . __LINE__);
	}
	if (!$konto_id) {
		$r = db_fetch_array(db_select("select * from adresser where art = 'K' and cvrnr='$cvrnr'",__FILE__ . " linje " . __LINE__));
		if ($r['id']) {
			$konto_id=$r['id']; 
			$kontonr=$r['kontonr'];
			$gruppe=$r['gruppe'];
			$betalingsdage=$r['betalingsdage'];
			if (!$betalingsdage) $betalingsdage=8; 
			$betalingsbet=$r['betalingsbet'];
			if (!$betalingsbet) $betalingsbet='Netto'; 
			if ($gruppe) {
				$r=db_fetch_array(db_select("select box1, box3 from grupper where art='KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
				$valuta=trim($r['box3']);
				if (substr($r['box1'],0,1)=='K') {
					$tmp=substr($r['box1'],1,1)*1;
					$r=db_fetch_array(db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
					$momssats=trim($r['box2'])*1;
				} elseif (substr($r['box1'],0,1)=='E') {
					$momssats='0.00';
				} elseif (substr($r['box1'],0,1)=='Y') { 
					$momssats='0.00';
				}
			} else print "<BODY onLoad=\"javascript:alert('Kreditor ikke tilknyttet en kreditorgruppe')\">";
		} else {
			$grp_antal=0;
			$q=db_select("select * from grupper where art='KG' order by kodenr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$grp_antal++;
				$grp_id[$grp_antal]=$r['kodenr'];
				$grp_navn[$grp_antal]=$r['beskrivelse'];
			}
			print "<form name=\"opret_kreditor\" action=\"$_SERVER[PHP_SELF]\" method=\"post\">";
			print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
			print "<input type=\"hidden\" name=\"descfil\" value=$descfil>";
			print "<tr><td align=\"center\"><table><tbody>";
			print "<tr><td>Der eksisterer ingen leverandører med cvrnr.: $cvrnr</td></tr>";
			if ($grp_antal>1) {
				print "<tr><td>Vælg kreditorgruppe: <select name=\"opret_lev\">";
				for ($y=1;$y<=$grp_antal;$y++) {
					print "<option value=\"$grp_id[$y]\">$grp_id[$y] : $grp_navn[$y]</option>";
				}
				print "</select></td></tr>\n";
			}
 			print "<tr><td align=\"center\"><input type=\"submit\" name=\"opret\" value=\"Opret\"></td></tr>\n";
			print "</tbody></table></td></tr>";
			print "</form>";
			exit;
		}	
	}
	if ($opret_vare) {
		global $gl_varenr;
		global $opret_varenr;
		global $salgspris;

		if ($opret_varenr && $salgspris) {
			$salgspris=usdecimal($salgspris);
			$low=strtolower($opret_varenr);
			$up=strtoupper($opret_varenr);
			for ($x=0;$x<count($l_varenr);$x++) {
				if ($gl_varenr==$l_varenr[$x]) {
					transaktion('begin');
					if ($r=db_fetch_array(db_select("select id,beskrivelse from varer where varenr='$opret_varenr' or lower(varenr)= '$low' or upper(varenr)='$up' or lower(stregkode)= '$low' or upper(stregkode)='$up'",__FILE__ . " linje " . __LINE__))) {
						$vare_id[$x]=$r['id'];
					} else {
						db_modify("insert into varer (varenr,kostpris,salgspris,beskrivelse,gruppe,lukket) values ('$opret_varenr','$l_pris[$x]','$salgspris','$l_tekst[$x]','$opret_vare','0')",__FILE__ . " linje " . __LINE__);
						$r = db_fetch_array(db_select("select id,beskrivelse from varer where varenr='$opret_varenr'",__FILE__ . " linje " . __LINE__));
						$vare_id[$x]=$r['id'];
					}
					db_modify("insert into vare_lev(posnr,lev_id,vare_id,lev_varenr,kostpris) values ('0','$konto_id','$vare_id[$x]','$l_varenr[$x]','$l_pris[$x]')",__FILE__ . " linje " . __LINE__);
#					$x=$linjenr;
					transaktion('commit');
				}
			}
		} elseif ($opret_varenr) {
			print "<BODY onLoad=\"javascript:alert('Angiv en salgspris på varen')\">";
		}
		if (isset($_POST['tilknyt']) || $tilknyt) {
			$low=strtolower($opret_varenr);
			$up=strtoupper($opret_varenr);
			for ($x=0;$x<count($l_varenr);$x++) {
				if ($gl_varenr==$l_varenr[$x]) {
					if ($r=db_fetch_array(db_select("select id,beskrivelse from varer where varenr='$opret_varenr' or lower(varenr)= '$low' or upper(varenr)='$up' or lower(stregkode)= '$low' or upper(stregkode)='$up'",__FILE__ . " linje " . __LINE__))) {
						$vare_id[$x]=$r['id'];
						db_modify("insert into vare_lev(posnr,lev_id,vare_id,lev_varenr,kostpris) values ('0','$konto_id','$vare_id[$x]','$l_varenr[$x]','$l_pris[$x]')",__FILE__ . " linje " . __LINE__);
#						$x=$linjenr;
					}
				}
			}
		}
	}
	for ($x=0;$x<count($l_varenr);$x++) {
		if (!$vare_id[$x]) {
			$low=strtolower($l_varenr[$x]);
			$up=strtoupper($l_varenr[$x]);
			$r = db_fetch_array(db_select("select * from vare_lev where lev_id='$konto_id' and (lev_varenr='$l_varenr[$x]' or lower(lev_varenr)= '$low' or upper(lev_varenr)='$up')",__FILE__ . " linje " . __LINE__));
			$vare_lev_id[$x]=$r['id'];
			$vare_id[$x]=$r['vare_id'];
		}	
		if (!$vare_id[$x]) {
			print "<tr><td align=\"center\"><table><tbody>";
			print "<form name=\"opret_vare\" action=\"$_SERVER[PHP_SELF]\" method=\"post\">";
			$r = db_fetch_array(db_select("select id,beskrivelse from varer where varenr='$l_varenr[$x]' or lower(varenr)= '$low' or upper(varenr)='$up' or lower(stregkode)= '$low' or upper(stregkode)='$up'",__FILE__ . " linje " . __LINE__));
			if ($vare_id[$x]=$r['id']){
				print "<tr><td colspan=\"2\">Varenummer $l_varenr[$x] ($l_tekst[$x]) eksisterer i systemet med beskrivelsen ".$r['beskrivelse']." og er ikke tilknyttet denne leverandør<br></td></tr>";
				print "<tr><td colspan=\"2\">Klik OK for et tilknytte den eksisterende vare til denne leverandør eller skriv et alternativt varenummer for et oprette varen med andet varenr.<br></td></tr>";
			}
			$grp_antal=0;
			$q=db_select("select * from grupper where art='VG' order by kodenr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$grp_antal++;
				$grp_id[$grp_antal]=$r['kodenr'];
				$grp_navn[$grp_antal]=$r['beskrivelse'];
			}
			print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
			print "<input type=\"hidden\" name=\"descfil\" value=$descfil>";
			print "<input type=\"hidden\" name=\"gl_varenr\" value=\"$l_varenr[$x]\">";
			print "<tr><td colspan=\"2\">Der eksisterer ingen varer med varenr / stregkode.: $l_varenr[$x]<br></td></tr>";
			print "<tr><td colspan=\"2\">Hvis varen eksisterer med et andet varenummer kan du skrive det herunder og klikke på \"Tilknyt\"<br></td></tr>";
			print "<tr><td colspan=\"2\">Eller du kan klikke på \"Opret\" og oprette varen med det varenummer som er angivet.<br></td></tr>";
			if ($grp_antal>1) {
				print "<tr><td>Varegruppe:</td><td><select name=\"opret_vare\">";
				for ($y=1;$y<=$grp_antal;$y++) {
					print "<option value=\"$grp_id[$y]\">$grp_id[$y] : $grp_navn[$y]</option>";
				}
				print "</select></td></tr>";
			}
			print "<tr><td>Varenr:</td><td><input type=\"tekst\" name=\"opret_varenr\" value=\"$l_varenr[$x]\"><br></td></tr>";
			print "<tr><td>Købspris:</td><td>".dkdecimal($l_pris[$x])."<br></td></tr>";
			print "<tr><td>Salgspris:</td><td><input type=\"text\" name=\"salgspris\"><br></td></tr>";
			print "<tr><td align=\"center\" colspan=\"2\"><input type=\"submit\" name=\"opret\" value=\"Opret\"><input type=\"submit\" name=\"tilknyt\" value=\"Tilknyt\"></td></tr>";
			print "</form></tbody></table></td></tr>";
			exit;
		}
	}
	if (isset($_POST['importer']) && $_POST['importer']=='Importer') {
		transaktion('begin');
		#cho "Importer til ordre";
		$r=db_fetch_array(db_select("select max(ordrenr) as ordrenr from ordrer where art='KO' or art='KK'",__FILE__ . " linje " . __LINE__));
		$ordrenr=$r['ordrenr']+1;

		db_modify("insert into ordrer (ordrenr,konto_id,kontonr,firmanavn,addr1,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,art,ordredate,levdate,momssats,status,hvem,tidspkt,valuta,fakturanr) values ('$ordrenr','$konto_id','$kontonr','".db_escape_string($firmanavn)."','".db_escape_string($vejnavn)." $husnr','$postnr','$bynavn','$land','$betalingsdage','$betalingsbet','$cvrnr','KO','$ordredate','$ordredate','$momssats','1','$brugernavn','$tidspkt','$valuta','$fakturanr')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select id from ordrer where art='KO' and ordrenr = '$ordrenr'",__FILE__ . " linje " . __LINE__));
		$ordre_id=$r['id'];
		$pos=0;
		for ($x=0;$x<count($l_varenr);$x++) {
			if ($vare_id[$x]) {
				$pos++;
				$r=db_fetch_array(db_select("select * from varer  where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				db_modify("insert into ordrelinjer (ordre_id,lev_varenr,varenr,vare_id,beskrivelse,antal,leveres,pris,posnr) values ('$ordre_id','$l_varenr[$x]','$r[varenr]','$vare_id[$x]', '$l_tekst[$x]','$l_antal[$x]','$l_antal[$x]','$l_pris[$x]','$pos')",__FILE__ . " linje " . __LINE__);
			}
		}
		transaktion('commit');
		if ($descfil) {
			print "<input type=\"hidden\" name=\"slet_bilailnavn\" value=$filnavn>";
			print "<input type=\"hidden\" name=\"descfil\" value=$descfil>";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ublimport.php?slet=1&descfil=$descfil&funktion=gennemse\">";
		} else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$ordre_id\">";
	} else {
		print "<form name=\"importer\" action=\"$_SERVER[PHP_SELF]\" method=\"post\">";
		print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
		print "<input type=\"hidden\" name=\"descfil\" value=$descfil>";
		print "<tr><td width=\"100%\" align=\"center\"><input type=\"submit\" name=\"importer\" value=\"Importer\"></td></tr>";
		print "</form>";
	}
}

function vis_oioubl($filnavn,$nfs_mappe) {
	global $charset;
	global $bgcolor;
	global $bgcolor5;
	
	global $firmanavn;
	global $kontonr;
	global $vejnavn;
	global $husnr;
	global $postnr;
	global $bynavn;
	global $land;
	global $cvrnr;
	global $ordredate;
	global $fakturadate;
	global $l_varenr;
	global $l_pris;
	global $l_tekst;
	global $l_antal;
	global $l_sum;
	global $l_moms;
	global $l_momssats;
	global $l_posnr;

	$accountingsupplierparty=NULL;
	$cvrnr=NULL;
	$firmanavn=NULL;
	$linjenr=0;
	$orderreference=NULL;
	$party=null; $partyname=NULL;

	$indhold=null;
	$ean=NULL;
	$fp=fopen($filnavn,'r');
	if ($fp) {
		$x=0;
		while($linje[$x]=fgets($fp)) $x++;
		$linjeantal=$x;
		fclose($fp);
	}
	for ($x=0;$x<=$linjeantal;$x++) {
		$linje[$x]=trim($linje[$x]);
		$tmp=strtolower($linje[$x]);
#cho htmlentities($linje[$x])." -> ".htmlentities($tmp)."<br>";
		if ((strstr($tmp,"<cbc:id>") || $orderid) && !$fakturanr) {
			$orderid=1;
			$find="<cbc:id";
			if (strstr($tmp,"$find")) $fakturanr=find_var ($tmp,$linje[$x],$find);
			if (strstr($tmp,"</cac:id>")) $orderid=0;
		}
		if ((strstr($tmp,"<cbc:issuedate>") || $inv_date) && !$fakturadate) {
			$inv_date=1;
			$find="<cbc:issuedate";
			if (strstr($tmp,"$find")) $fakturadate=find_var ($tmp,$linje[$x],$find);
			if (strstr($tmp,"</cac:issuedate>")) $inv_date=0;
		}
		if (strstr($tmp,"<cac:orderreference>") || $orderreference) {
			$orderreference=1;
			$find="<cbc:salesorderid";
			if (strstr($tmp,"$find")) $ordrenr=find_var ($tmp,$linje[$x],$find);
			$find="<cbc:issuedate";
			if (strstr($tmp,"$find")) $ordredate=find_var ($tmp,$linje[$x],$find);
			if (strstr($tmp,"</cac:orderreference>")) $orderreference=0;
		}
		if (strstr($tmp,"<cac:accountingsupplierparty>") || $accountingsupplierparty) {
#cho "Z1 $tmp -> $linje[$x] ->\"cbc:endpointid\"<br>";
			$accountingsupplierparty=1;
			if (strstr($tmp,"<cac:party>") || $party) {
				$party=1;
				$find="<cbc:endpointid";
				if (strstr($tmp,"$find")) $cvrnr=find_var ($tmp,$linje[$x],$find);
				if (strstr($tmp,"<cac:partyname>") || $partyname) {
					$partyname=1;
					$find="<cbc:name";
					if (strstr($tmp,"$find")) $firmanavn=find_var ($tmp,$linje[$x],$find);
					if (strstr($tmp,"</cac:partyname>")) $partyname=0;
				}
				if (strstr($tmp,"<cac:postaladdress>") || $postaladdress) {
					$postaladdress=1;
					$find="<cbc:streetname";
					if (strstr($tmp,"$find")) $vejnavn=find_var ($tmp,$linje[$x],$find);
					$find="<cbc:buildingnumber";
					if (strstr($tmp,"$find")) $husnr=find_var ($tmp,$linje[$x],$find);
					$find="<cbc:cityname";
					if (strstr($tmp,"$find")) $bynavn=find_var ($tmp,$linje[$x],$find);
					$find="<cbc:postalzone";
					if (strstr($tmp,"$find")) $postnr=find_var ($tmp,$linje[$x],$find);
					if (strstr($tmp,"<cac:country>") || $country) {
						$country=1;
						$find="<cbc:identificationcode";
						if (strstr($tmp,"$find")) $land=find_var ($tmp,$linje[$x],$find);
						if (strstr($tmp,"</cac:country>")) $country=0;
					}
					if (strstr($tmp,"</cac:partyname>")) $partyname=0;
					if (strstr($tmp,"</cac:postaladdress>")) $postaladdress=0;
				}
				if (strstr($tmp,"<cbc:telephone>") || $telephone) {
					$country=1;
					$find="<cbc:telephone";
					if (strstr($tmp,"$find")) $telefon=find_var ($tmp,$linje[$x],$find);
					if (strstr($tmp,"</cac:telephone>")) $telephone=0;
				}
				if (strstr($tmp,"</cbc:party>")) $party=0;
			}
			if (strstr($tmp,"</cac:accountingsupplierparty>")) $accountingsupplierparty=0;
		}

#---------------- ordrelinjer -------------

		if (strstr($tmp,"<cac:invoiceline") || $invoiceline) {
			$invoiceline=1;
			if ((strstr($tmp,"<cbc:note>") || $linenote)) {
				$linenote=1;
				$find="<cbc:note";
				if (strstr($tmp,"$find")) $linjenote[$linjenr].=find_var ($tmp,$linje[$x],$find);
				if (strstr($tmp,"</cbc:note>")) $linenote=0;
			}
			if ((strstr($tmp,"<cbc:invoicedquantity") || $invoicedquantity)) {
#cho htmlentities($tmp);
				$invoicedquantity=1;
				$find="<cbc:invoicedquantity";
				if (strstr($tmp,"$find")) $l_antal[$linjenr]=find_var ($tmp,$linje[$x],$find);
#cho "<br>$linjenr antal ".$l_antal[$linjenr]."<br>";
				if (strstr($tmp,"</cbc:invoicedquantity>")) $invoicedquantity=0;
			}
			if ((strstr($tmp,"<cbc:lineextensionamount") || $lineextensionamount)) {
				$lineextensionamount=1;
				$find="<cbc:lineextensionamount";
				if (strstr($tmp,"$find")) $l_sum[$linjenr]=find_var ($tmp,$linje[$x],$find);
				if (strstr($tmp,"</cbc:lineextensionamount>")) $lineextensionamount=0;
			}
			if (strstr($tmp,"<cac:taxtotal") || $taxtotal) {
				$taxtotal=1;
				if ((strstr($tmp,"<cbc:taxamount") || $taxamount)) {
					$taxamount=1;
					$find="<cbc:taxamount";
					if (strstr($tmp,"$find")) $l_moms[$linjenr]=find_var ($tmp,$linje[$x],$find);
					if (strstr($tmp,"</cbc:taxamount>")) $taxamount=0;
				}
				if ((strstr($tmp,"<cbc:percent") || $percent)) {
					$percent=1;
					$find="<cbc:percent";
					if (strstr($tmp,"$find")) $l_momssats[$linjenr]=find_var ($tmp,$linje[$x],$find);
					if (strstr($tmp,"</cbc:percent>")) $percent=0;
				}
				if (strstr($tmp,"</cac:taxtotal>")) $taxtotal=0;
			}
			if ((strstr($tmp,"<cbc:description") || $description)) {
				$description=1;
				$find="<cbc:description";
				if (strstr($tmp,"$find")) $l_tekst[$linjenr]=find_var ($tmp,$linje[$x],$find);
				if (strstr($tmp,"</cbc:description>")) $description=0;
			}
			if (strstr($tmp,"<cac:price") || $price) {
#cho "Pris $l_pris[$linjenr]<br>";
				$price=1;
				if ((strstr($tmp,"<cbc:priceamount") || $priceamount)) {
					$priceamount=1;
					$find="<cbc:priceamount";
					if (strstr($tmp,"$find")) $l_pris[$linjenr]=find_var ($tmp,$linje[$x],$find);
#cho "$linjenr Pris $l_pris[$linjenr]<br>";
					if (strstr($tmp,"</cbc:priceamount>")) $priceamount=0;
				}
				if (strstr($tmp,"</cac:price>")) $price=0;
			}
			if ((strstr($tmp,"<cac:sellersitemidentification") || $sellersitemidentification)) {
				$sellersitemidentification=1;
				$find="<cbc:id";
				if (strstr($tmp,"$find")) $l_varenr[$linjenr]=find_var ($tmp,$linje[$x],$find);
				if (strstr($tmp,"</cac:sellersitemidentification>")) $sellersitemidentification=0;
			}
			if (strstr($tmp,"</cac:invoiceline")) {
				$invoiceline=0;
				if ($l_varenr[$linjenr]) $linjenr++;
			}
		}
	}
	if (!$ordredate && $fakturadate) $ordredate=$fakturadate;
	elseif (!$ordredate) $ordredate=date("Y-m-d");
	
	$linjebg=$bgcolor5;
	print "<tr><td valign=\"top\" align=\"center\"><table valign=\"top\"><tbody>";
	print "<tr bgcolor=\"$bgcolor5\"><td width=\"100px\"><b>Firmanavn:</b></td><td width=\"200px\">$firmanavn<br></td><td width=\"100px\"><b>Ordrenr:</b></td><td width=\"200px\">$ordrenr<br></td></tr>\n";
	print "<tr><td><b>Adresse:</b></td><td>$vejnavn $husnr<br></td><td><b>Ordredate:</td><td>$ordredate<br></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td><b>Adresse:</b></td><td>$postnr $bynavn<br></td><td><b>Fakturanr:</b></td><td>$fakturanr<br></td></tr>\n";
	print "<tr><td><b>Land:</b></td><td>$land<br></td><td><b>Cvr:</b></td><td>$cvrnr<br></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td bgcolor=\"$bgcolor5\"><b>Telefon:</b></td><td>$telefon<br></td><td><b></b></td><br><td><br></td></tr>\n";
	print "</tbody></table></td></tr>";
	print "<tr><td valign=\"top\" align=\"center\"><table valign=\"top\"><tbody>";
	print "<tr bgcolor=\"$linjebg\"><td width=\"100px\"><b>Varenr</b></td><td width=\"50px\" align=\"right\"><b>Antal</b></td><td width=\"500px\"><b>Beskrivelse</b></td><td width=\"50px\"><b>Købspris</b></td><td width=\"70px\" align=\"right\"><b>Moms %</b></td>\n";
	for ($x=0;$x<$linjenr;$x++) {
	if ($linjebg!=$bgcolor) {
		$linjebg=$bgcolor;
	} else {
		$linjebg=$bgcolor5;
	}
		$l_posnr[$x]=$x+1;
		print "<tr bgcolor=\"$linjebg\"><td>$l_varenr[$x]</td><td align=\"right\">".dkdecimal($l_antal[$x])."</td><td>$l_tekst[$x]</td><td align=\"right\">".dkdecimal($l_pris[$x])."</td><td align=\"right\">".dkdecimal($l_momssats[$x])."</td>\n";
#cho "Pos $l_posnr[$x], vnr $l_varenr[$x], antal $l_antal[$x], tekst $l_tekst[$x], pris $l_pris[$x], momssats $l_momssats[$x]<br>";
	}
	print "<tr><td colspan=\"5\"><hr></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
#	return($firmanavn.chr(9).$kontonr.chr(9).$vejnavn.chr(9).$husnr.chr(9).$postnr.chr(9).$bynavn.chr(9).$land.chr(9).$cvrnr.chr(9).$ordredate.chr(9).$l_varenr.chr(9).$l_pris.chr(9).$l_tekst.chr(9).$l_antal.chr(9).$l_sum.chr(9).$l_moms.chr(9).$l_momssats.chr(9).$l_posnr);
}

function find_var ($lowline,$line,$varname) {

if ($varname=="<sellersitemidentification") echo htmlentities($tmp)."<br>";
	if (strstr($lowline,$varname)) {
		$pos=strpos("$varname",$lowline)+strlen($varname);
	}
#cho "<br>pre var: ";
	while(substr($lowline,$pos,1)!=">") {
#cho substr($lowline,$pos,1);
		$pos++;
		if ($pos>200) {
			echo "A: fejl i skema, linje $x<br>"; 
			echo htmlentities($lowline)."<br>";
			exit;
		}
	}
	$pos++;
	$var='';
#cho "<br>var: ";
	while(substr($lowline,$pos,1)!="<" && $pos<=strlen($lowline)) {
#cho substr($lowline,$pos,1);
		$var.=substr($line,$pos,1);
		$pos++;
		if ($pos>200) {
			echo "B: fejl i skema, linje $x<br>"; 
			echo htmlentities($lowline)."<br>";
			break 1;
		}
	}
#$var="$pos - hest";
#cho "<br>var $var<br>";
	return ("$var"); 
}

function gennemse($kilde_id,$kilde,$bilag_id,$bilag,$fokus,$filnavn,$puljefil,$nfs_mappe){
	global $db;
	
	(isset($_POST['slet_bilag']) && $_POST['slet_bilag']=='Slet')?	$slet=1:$slet=0;
	(isset($_POST['upload_bilag']) && $_POST['upload_bilag']=='inds&aelig;t')?	$indsaet=1:$inssaet=0;
	$descfil=if_isset($_POST['descfil']);
	if (!$dsecfil) {
		$descfil=if_isset($_GET['descfil']);
		$slet=if_isset($_GET['slet']);
	}

	if ($descfil && $slet) {
		$tmp=str_replace(" ","\\ ",$descfil);
		echo "slettter ../temp/$db/pulje/$descfil*<br>";
		system("rm ../".$nfs_mappe."/$db/pulje/$tmp*\n");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ublimport.php?bilag=$bilag&kladde_id==$kilde_id&fokus=$fokus&funktion=gennemse\">";
		exit;
	}
	if ($indsaet) {
		echo "indsætter $puljefil";
		exit;
	}
	
	$r=db_fetch_array(db_select("select * from grupper where art='bilag'",__FILE__ . " linje " . __LINE__));
	$google_docs=$r['box7'];

	if ($bilag_id) {
		$r=db_fetch_array(db_select("select * from kassekladde where id='$bilag_id'",__FILE__ . " linje " . __LINE__));
		if (!$bilag) $bilag=$r['bilag'];
		if (!$dato) $dato=dkdato($r['transdate']);
		if (!$beskrivelse) $beskrivelse=$r['beskrivelse'];
		if (!$debet) {
			if ($r['d_type']!='F') $debet=$r['d_type'].$r['debet'];
			else $debet=$r['debet'];
		}
		if (!$kredit) {
			if ($r['k_type']!='F') $kredit=$r['d_type'].$r['kredit'];
			else $kredit=$r['kredit'];
		} if (!$fakturanr) $fakturanr=$r['fakturanr'];
		if (!$sum) $sum=dkdecimal($r['amount']);
	}
	$dir="../".$nfs_mappe."/".$db."/pulje";
	$url="://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
	$url=str_replace("/kreditor/ublimport.php","/temp/$db/pulje/",$url);
	if ($_SERVER['HTTPS']) $url="s".$url;
	$url="http".$url;
	if (!$puljefil) {
		if (is_dir($dir)) {
			if ($dh = opendir($dir)) {
				while (($file = readdir($dh)) !== false) {
				if (substr($file,0,1)!='.' && (substr($file,-4)=='.xml' || substr($file,-4)=='.fak')) {
						if (!$puljefil) $puljefil=$file;
					}
				}
				closedir($dh);
			}
		} else echo "Dir eksisterer ikke<br>";
	}
#xit;
	print "<form name=\"gennemse\" action=\"ublimport.php?funktion=gennemse&puljefil=$puljefil\" method=\"post\">\n";
	print "<input type=\"hidden\" name=\"puljefil\" value=$puljefil>";
	print "<input type=\"hidden\" name=\"filnavn\" value=$filnavn>";
	print "<input type=\"hidden\" name=\"kilde_id\" value=$kilde_id>";
	print "<input type=\"hidden\" name=\"kilde\" value=$kilde>";
	print "<input type=\"hidden\" name=\"bilag_id\" value=$bilag_id>";
	print "<input type=\"hidden\" name=\"bilag\" value=$bilag>";
	print "<input type=\"hidden\" name=\"fokus\" value=$fokus>";
	print "<tr><td width=15% align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print "<tr><td width=100% align=center>";
	$fil_nr=0;
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (substr($file,0,1)!='.' && (substr($file,-4)=='.xml' || substr($file,-4)=='.fak')) {
					($file==$puljefil)?$bgcolor='#aaaaaa':$bgcolor='#ffffff'; 
					$fil_nr++;
					print "<tr><td bgcolor=\"$bgcolor\"><a href=ublimport.php?funktion=gennemse&kilde_id=$kilde_id&kilde=$kilde&bilag=$bilag&bilag_id=$bilag_id&dato=$dato&fokus=$fil_nr&puljefil=$file onfocus=\"document.forms[0].fokus.value=this.name;\" id=\"$fil_nr\">$file</a></td></tr>";
				}
			}
			closedir($dh);
		}
	}
	
	if ($puljefil) {
		$tmp="../../../".$nfs_mappe."/$db/pulje/$puljefil";
		if (!is_dir("../temp/$db/pulje")) mkdir("../temp/$db/pulje"); 
		system("cd ../temp/$db/pulje\nrm *\ncp $tmp .\n");
	} else {
		print "<BODY onLoad=\"javascript:alert('Ingen bilag af typen `.xml` eller `.fak` i pulje')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../kreditor/ordreliste.php\">";
	}
	print "</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "</table></td>";
	print "<td rowspan=\"2\" width=85% height=\"100%\" align=center><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	print	"<tr><td width=100% align=center>";
$tmp="../temp/$db/pulje/".$puljefil;
	vis_oioubl($tmp,$nfs_mappe);
	#	$tmp=$url.$puljefil;
#	if ($puljefil) {
#		if ($google_docs) $src="http://docs.google.com/viewer?url=$tmp&embedded=true";
#		else $src=$tmp;
#		print "X2<iframe style=\"width:100%;height:100%\" src=\"$src\" frameborder=\"0\">";
#		print "</iframe></td></tr>";
#	}
	print "</tbody></table></td></tr>";
	print "<tr><td height=\"100px\"><table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" style=\"border: 3px solid rgb(180, 180, 255); padding: 0pt 0pt 1px;\"><tbody>";
	$descfil=NULL;
	if ($puljefil) {
		if (substr($puljefil,-3,1)==".") $descfil=substr($puljefil,0,strlen($puljefil)-3); 
		elseif (substr($puljefil,-4,1)==".") $descfil=substr($puljefil,0,strlen($puljefil)-4); 
		else $descfil=NULL;
	}
/*
	if ($descfil) {
		$tmp="../../".$dir."/".$descfil.".desc";
		system("cd ../temp/$db/pulje\ncp $tmp .\n");
		$fp=fopen("../temp/$db/pulje/$descfil.desc","r");
		while($linje=trim(fgets($fp))) {
			if (strtolower(substr($linje,0,6))=='bilag:') $bilag=trim(substr($linje,6));
			if (strtolower(substr($linje,0,5))=='dato:') $dato=trim(substr($linje,5));
			if (strtolower(substr($linje,0,12))=='beskrivelse:') $beskrivelse=trim(substr($linje,12));
			if (strtolower(substr($linje,0,6))=='debet:') $debet=trim(substr($linje,6));
			if (strtolower(substr($linje,0,7))=='kredit:') $kredit=trim(substr($linje,7));
			if (strtolower(substr($linje,0,10))=='fakturanr:') $fakturanr=trim(substr($linje,10));
			if (strtolower(substr($linje,0,4))=='sum:') $sum=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='sag:') $sag=trim(substr($linje,4));
			if (strtolower(substr($linje,0,4))=='afd:') $afd=trim(substr($linje,4));
			if (strtolower(substr($linje,0,8))==='projekt:') $projekt=trim(substr($linje,8));
		}
	}

	if (!$dato) $dato=date("d-m-Y");
	print "<tr><td>Bilag</td><td><input type=\"text\" style=\"width:150px\" name=\"bilag\" value=\"$bilag\"</td></tr>";	
	print "<tr><td>Dato</td><td><input type=\"text\" style=\"width:150px\" name=\"dato\" value=\"$dato\"</td></tr>";	
	print "<tr><td>beskrivelse</td><td><input type=\"text\" style=\"width:150px\" name=\"beskrivelse\" value=\"$beskrivelse\"</td></tr>";	
	print "<tr><td>Debet</td><td><input type=\"text\" style=\"width:150px\" name=\"debet\" value=\"$debet\"</td></tr>";	
	print "<tr><td>Kredit</td><td><input type=\"text\" style=\"width:150px\" name=\"kredit\" value=\"$kredit\"</td></tr>";	
	print "<tr><td>Fakturanr</td><td><input type=\"text\" style=\"width:150px\" name=\"fakturanr\" value=\"$fakturanr\"</td></tr>";	
	print "<tr><td>Sum</td><td><input type=\"text\" style=\"width:150px\" name=\"sum\" value=\"$sum\"</td></tr>";	
	print "<tr><td>Sag</td><td><input type=\"text\" style=\"width:150px\" name=\"sag\" value=\"$sag\"</td></tr>";	
	print "<tr><td>Afd</td><td><input type=\"text\" style=\"width:150px\" name=\"afd\" value=\"$afd\"</td></tr>";	
	print "<tr><td>Projekt</td><td><input type=\"text\" style=\"width:150px\" name=\"projekt\" value=\"$projekt\"</td></tr>";	
*/
	print "<tr><td colspan=\"2\"><input style=\"width:100%;height:50px;\" type=\"submit\" name=\"indsaet\" value=\"Inds&aelig;t\"</tr>";	
	print "<tr><td colspan=\"2\"><input style=\"width:100%;height:50px;\" type=\"submit\" name=\"slet_bilag\" value=\"Slet\"</tr>";	
	print "</tbody></table></td></tr>";
	print "<input type=\"hidden\" style=\"width:150px\" name=\"descfil\" value=\"$descfil\"</td></tr>";	
	print "<input type=\"hidden\" style=\"width:150px\" name=\"filnavn\" value=\"".$url.$puljefil."\"</td></tr>";	
	print "</form>";
	print "<script language=\"javascript\">";
	print "document.gennemse.$fokus.focus();";
	print "</script>";
	exit;

} #endfunc gennemse
