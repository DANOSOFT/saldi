<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ finans/rapport.php --------------- lap 3.8.2 --- 2019-09-24 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------

// 20120927 Hvis budgettal indsat og konto lukket blev konto alligevel vist under budget
// 20130210 Break ændret til break 1
// 20130918	Diverse tilretninger til simulering - Søg $simulering
// 20130919	Fejl i søgefunktion ved opdelte projektnumre. Søg 20130919
// 20140729 Listeangivelse ændret fra kvartal til måned - ca. Søg 20140729
// 20140825 Resultatkonto viste årssaldo uanset om den var valgt periode. PHR Søg 20140825
// 20140909 Resultat fra resultatkto kom ikke med i sum. PHR Søg 20140909
// 20150104 Tilføjet dynamisk vagerværdi - Søg /*aut_lager*/
// 20150125 Fejl i lagerberegning i statusrapport- lagetræk blev lagt til værdi, ombyttet + & - - Søg 20150125
// 20150408 Fejl i lagerberegning i statusrapport- medtog sidste dag i foregående md - tilføjet 'start'/'slut' til find_lagervaerdi. Søg find_lagervaerdi
// 20150825 Transaktioner med ens bilag, tekst og kontonummer blev samlet sammen til linje. Ved ikke hvorfor men det gør det svært at kontrollere bank
// 20151001 Sat fast bredde på felter i overskrifter.
// 20160116	Diverse i forbindelse med indførelse af valutakonti	Søg 'valuta'
// 20160515 Oprydning dk- og uscecimal, indsat ',2'
// 20170516 PHR Fakturadate ændret til kobsdate i søgning efter lagerbevægelser for bedre overensstemmelse med svar fra 'find_lagervaerdi' Søg 20170516
// 20180226 PHR - Bortkommenteret if (!$dim) så primo vises på afdelinger.
// 20180424 PHR - Tilføjet "regnskab" (Resultat + bufget i et).
// 20181031 PHR - Tilføjet  "&& $kontotype[$x]=='D'" så den kun søger i driftskonti da der kan ligge budgettal i andre konti hvis kontoplan ændret. 20181031
// 2018.12.20 MSC - Rettet topmenu design til
// 2018.12.21 MSC - Rettet topmenu design til og rettet isset fejl
// 2019.02.07 MSC - Rettet array fejl (linje 815) - rettelse (linje 813)
// 2019.02.20 PHR tilføjet " || ($kontotype[$x] == 'Z' && $x==$kontoantal) " Da balancekonto ellers ikke vises hvis sum=0 - 20190220
// 2019.04.12 PHR Moved functions to 'rapport_includes' and added 'Resultat/Sidste år' 
// 20190924 PHR Added option 'Poster uden afd". when "afdelinger" is used. $afd='0' 


$title="Finansrapport";
@session_start();
$s_id=session_id();

$title="Finansrapport";
$modulnr=4;
$css="../css/standard.css";

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset ($find)) $find = NULL;
if (!isset ($prj_navn_til)) $prj_navn_til = NULL;
if (!isset ($prj_navn_fra)) $prj_navn_fra = NULL;

if ($_POST){

	$submit=str2low(trim($_POST['submit']));
	if (!$popup) {
		if ($submit=='kontrolspor') {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=kontrolspor.php\">";
			exit;
		} elseif ($submit=='provisionsrapport') {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=provisionsrapport.php\">";
			exit;
		}
	}
	$rapportart=if_isset($_POST['rapportart']);
	$aar_fra=if_isset($_POST['aar_fra']);
	$aar_til=if_isset($_POST['aar_til']);
	$maaned_fra=trim(if_isset($_POST['maaned_fra']));
	$maaned_til=trim(if_isset($_POST['maaned_til']));
	$dato_fra=if_isset($_POST['dato_fra']);
	$dato_til=if_isset($_POST['dato_til']);
	$md=if_isset($_POST['md']);
	$ansat_id=if_isset($_POST['ansat_id']);
	$ansat_init=if_isset($_POST['ansat_init']);
	$antal_ansatte=if_isset($_POST['antal_ansatte']);
	$ansat_fra=if_isset($_POST['ansat_fra']);
	$projekt_fra=if_isset($_POST['projekt_fra']);
	$projekt_til=if_isset($_POST['projekt_til']);
	$simulering=if_isset($_POST['simulering']);
	$lagerbev=if_isset($_POST['lagerbev']);

	#cho "prj_navn_fra $prj_navn_fra -> $projekt_fra<br>";
	if ( stristr($rapportart,"Listeangivelse") ) {
			$listeperiode=preg_replace('/[^0-9.]*/','',$rapportart); # 20140729 afsnit 1
			print "<meta http-equiv=\"refresh\" content=\"0;URL=listeangivelse.php?listeperiode=$listeperiode\">";
			exit;
	}	

	if ($ansat_fra) {
		list ($tmp, $tmp2) = explode(":", $ansat_fra);
		$tmp=trim($tmp);
		for ($x=1; $x<=$antal_ansatte; $x++) {
			if ($tmp==$ansat_init[$x]) {
				$ansat_fra=$ansat_id[$x];
				$ansat_init_fra=$ansat_init[$x];
				$ansatte=$tmp;
			}
		}
	}
	$ansat_til=if_isset($_POST['ansat_til']);
	if ($ansat_til) {
		$ansatte_id=$ansat_fra;
		list ($tmp, $tmp2) = explode(":", $ansat_til);
		$tmp=trim($tmp);
		for ($x=1; $x<=$antal_ansatte; $x++) {
			if ($tmp==$ansat_init[$x]) {
				$ansat_til=$ansat_id[$x];
				if ($ansat_init_fra!=$tmp) {
					$ansatte=$ansatte.",".$tmp;
					$ansatte_id=$ansatte_id.",".$ansat_id[$x];
				}
				$x=$antal_ansatte;
			} elseif ($ansat_init[$x]>$ansat_init_fra) {
				$ansatte=$ansatte.",".$ansat_init[$x];
				$ansatte_id=$ansatte_id.",".$ansat_id[$x];
			}
		}
	}
	$afd=if_isset($_POST['afd']);
	if ($afd || $afd=='0') {
		list ($afd, $afd_navn) = explode(":", $afd);
		$afd=trim($afd);
	}
	$delprojekt=if_isset($_POST['delprojekt']);
	if ($projekt_til) $delprojekt=NULL;	 
	else {
		$find=0; #20130919 +næste 5 linjer
		for ($a=0;$a<count($delprojekt);$a++) {
			if ($delprojekt[$a]) $find=1;
		}
	}
	if ($find) {
		$prj_cfg=if_isset($_POST['prj_cfg']);
		$prcfg=explode("|",$prj_cfg);
		$b=count($delprojekt);
		$projekt_fra=NULL;
		for ($a=0;$a<$b;$a++) {
			$c=strlen($delprojekt[$a]);
			if ($c>$prcfg[$a]) $delprojekt[$a]=mb_substr($delprojekt[$a],0,$prcfg[$a],$db_encode);
			for($d=$c;$d<$prcfg[$a];$d++) {
				$delprojekt[$a]="?".$delprojekt[$a];  
			}
			$projekt_fra.=$delprojekt[$a];
		}
		$projekt_til=$projekt_fra;
	} else {
		$projekt_fra=if_isset($_POST['projekt_fra']);
		if (strpos($projekt_fra, ":")) {
			list ($projekt_fra, $prj_navn_fra) = explode(":", $projekt_fra);
			$projekt_fra=trim($projekt_fra);
		}
		$projekt_til=if_isset($_POST['projekt_til']);
		if (strpos($projekt_til,":")) {
			list ($projekt_til, $prj_navn_til) = explode(":", $projekt_til);
			$projekt_til=trim($projekt_til);
		}
		if ($projekt_fra && ! $prj_navn_fra) {
			$r=db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$projekt_fra'",__FILE__ . " linje " . __LINE__));
			$prj_navn_fra=$r['beskrivelse'];
		}
		if ($projekt_til && ! $prj_navn_til) {
			$r=db_fetch_array(db_select("select beskrivelse from grupper where kodenr = '$projekt_til'",__FILE__ . " linje " . __LINE__));
			$prj_navn_til=$r['beskrivelse'];
		}
		
	}
#cho "135 $projekt_fra $prj_navn_fra<br>";
#cho "135 $projekt_til $prj_navn_til<br>";
	$tmp=str_replace("?","",$projekt_fra);
	if (!$tmp) {
		$projekt_fra=NULL;
		$projekt_til=NULL;
	}
	$konto_fra=if_isset($_POST['konto_fra']);
	if ($konto_fra) list ($konto_fra, $beskrivelse) = explode(":", $konto_fra);
	$konto_til=if_isset($_POST['konto_til']);
	if ($konto_til) list ($konto_til, $beskrivelse) = explode(":", $konto_til);
	$regnaar=if_isset($_POST['regnaar']);
	if ($regnaar && !is_numeric($regnaar)) list ($regnaar, $beskrivelse)= explode("-", $regnaar);
} else {
	$rapportart=if_isset($_GET['rapportart']);
	$dato_fra=if_isset($_GET['dato_fra']);
	$dato_til=if_isset($_GET['dato_til']);
	$aar_fra=if_isset($_GET['aar_fra']);
	$aar_til=if_isset($_GET['aar_til']);
	$maaned_fra=if_isset($_GET['maaned_fra']);
	$maaned_til=if_isset($_GET['maaned_til']);
	$konto_fra=if_isset($_GET['konto_fra']);
	$konto_fra2=if_isset($_GET['konto_fra']);
	if ($konto_fra2) $konto_fra=$konto_fra2;
	$konto_til=if_isset($_GET['konto_til']);
	if (isset($_GET['regnaar'])) $regnaar=$_GET['regnaar'];
	$afd=if_isset($_GET['afd']);
	$ansat_fra=if_isset($_GET['ansat_fra']);
	$ansat_til=if_isset($_GET['ansat_til']);
	$projekt_fra=if_isset($_GET['projekt_fra']);
	$projekt_til=if_isset($_GET['projekt_til']);
	$simulering=if_isset($_GET['simulering']);
	$lagerbev=if_isset($_GET['lagerbev']);
	
}
$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

if ($submit != 'ok') $submit='forside';
elseif ($rapportart){
	if ($rapportart=="balance"||$rapportart=="resultat"||$rapportart=="budget"||$rapportart=="lastYear"){
		if ($r = db_fetch_array(db_select("select kontonr from kontoplan where regnskabsaar='$regnaar' and kontotype='X'",__FILE__ . " linje " . __LINE__))) {
			if ($rapportart != "balance") {
				$konto_til=$r['kontonr']-1;
			}
			else $konto_fra=$r['kontonr']+1;
		} else print "<BODY onLoad=\"javascript:alert('Sideskiftkonto ikke defineret i kontoplan - Balance & Resultat kan ikke adskilles')\">";
		$submit="regnskab";
	} else $submit=str2low($rapportart);
}

if ($maaned_fra && (!$aar_fra||!$aar_til)) {
	list ($aar_fra, $maaned_fra) = explode(" ", $maaned_fra);
	list ($aar_til, $maaned_til) = explode(" ", $maaned_til);
}
include("rapport_includes/$submit.php");
$submit($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til, $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart, $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,$simulering,$lagerbev);
#################################################################################################
function kontobemaerkning ( $l_kontonavn ) {
	$retur=NULL;
	if (strstr( $l_kontonavn, "RESULTAT")) {
		$retur = "title=\"Negativt resultat betyder overskud. Positivt resultat betyder underskud.\"";
	} elseif ($l_kontonavn=="Balancekontrol") {
		$retur = "title=\"Balancekontrollen viser det forel&oslash;bige eller periodens resultat, n&aring;r regnskabet ikke er afsluttet. Positivt viser et overskud. Negativt et underskud.\"";
	}
	return($retur);
}

function momsrubrik($rubrik_konto, $rubrik_navn, $regnaar, $regnstart, $regnslut) {
		print "<tr><td>".$rubrik_konto."</td><td colspan='3'>".$rubrik_navn."</td>";
		if ( $rubrik_konto ) {
			$q = db_select("select * from kontoplan where regnskabsaar='$regnaar' and kontonr=$rubrik_konto",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array($q);
#			$kontobeskrivelse[$x]=$r['beskrivelse'];
			$rubriksum=0;
			$q = db_select("select * from transaktioner where transdate>='$regnstart' and transdate<='$regnslut' and kontonr=$rubrik_konto",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$rubriksum+=afrund($r['debet'],2)-afrund($r['kredit'],2);
			}
			print "<td align='right'>".dkdecimal($rubriksum,2)."</td>";
		} else {
			print "<td align='right'><span title='Intet bel&oslash;b i den angivne periode.'>-</span></td>";
		}
		print "<td>&nbsp;</td></tr>\n";
		return;
}

# Funktionen ændret fra kvartal til måned. 20140729 start afsnit 2 
function listeangivelser ($regnaar, $rapportart, $option_type) {

	$qtxt="select box1, box2, box3, box4 from grupper where art = 'RA' and kodenr = '$regnaar' order by box2, box1 desc";
	$x=0;
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($q);
	$liste_aar[$x]=($row['box2']*1);
	$liste_md[$x]=($row['box1']*1);
	$liste_rapportart[$x] = "Listeangivelse ".$liste_md[$x].". måned ".$liste_aar[$x];
	if ( $liste_md[$x] < 10 ) $liste_md[$x] = "0".$liste_md[$x];
	$liste_aarmd[$x] = $liste_aar[$x].$liste_md[$x];
	$kvartal_aarmd[$x] = ($kvartal_aar[$x].$row['box1'])*1+2;
	$slut_aarmd = ($row['box4'].$row['box3'])*1;
while ( $liste_aarmd[$x] < $slut_aarmd ) {
		$x++;
		$liste_md[$x]=$liste_md[$x-1]+1;
		$liste_aar[$x]=$liste_aar[$x-1];
		if ($liste_md[$x] >= 13 ) {
			$liste_md[$x] = 1;
			$liste_aar[$x] += 1;
		}
		$liste_rapportart[$x] = "Listeangivelse ".$liste_md[$x].". måned ".$liste_aar[$x];
		if ( $liste_md[$x] < 10 ) $liste_md[$x] = "0".$liste_md[$x];
		$liste_aarmd[$x] = $liste_aar[$x].$liste_md[$x];
	}
	$retur = "";
	for ($i=0; $i <= $x; $i++) {
		if ( $rapportart && $option_type == "matcher" && $rapportart == $liste_rapportart[$i] ) {
			print "<option title=\"Listeangivelser pr. måned.\">".$liste_rapportart[$i]."</option>\n";
		}
	}
	for ($i=0; $i <= $x; $i++) {
		if ( $option_type == "alle andre" && ( !$rapportart || !($rapportart == $liste_rapportart[$i]) ) ) {
#			print "<option value=\"".$liste_mdaar[$i]."\" title=\"Listeangivelser pr. måned.\">".$liste_rapportart[$i]."</option>\n";
			print "<option title=\"Listeangivelser pr. måned.\">".$liste_rapportart[$i]."</option>\n";
		}
	}

	return $retur;
} # slut function listeangivelser

?>
</body>
</html>

