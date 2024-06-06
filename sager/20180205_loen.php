<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ sager/loen.php-------lap 3.7.1 ------2018-01-18------15:23---------
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
// Copyright (c) 2003-2018 saldi.dk ApS
// ----------------------------------------------------------------------

// 160113 Leder skal kunne tilføje på acc - time seddel 
// Dato på oversigt
// Side til diverse -f.eks sats for skurpenge. / perioder / sygdomssats / skolesats
// Art for sygdom og skole.
// årsskifte er altid i periode 1. den dag periode starret frist for overførsel 
// Godkend til periode  - Periode  
// Acconto 
// Håndtering af afviste.
// Rettelse af HTML fejl.
// 20130201 Udvidet med sags-opgaver 
// 20130204 Tilfojet lønafregning
// 20130218 Dato vises ikke, løndato ikke sat.
// 20130220 Opgave id vises forkert, Value i option blev ikke sat.
// 20130226 Fejl i kontrol for skur - søeg 30120226
// 20130227	Ændret 50% sats til 56.95 & 100% sats til 113.85. Søg 56.95 eller 113.85 
// 20130228 Lønafregning samler nu op op på akktimer. Søg 20130228
// 20130301 Rettet diverse fejl v. afvisning og opsamling af km.
// 20130305 Akkord samles ikke op på "ren akkord" 
// 20130311	Div fejl v. opsamling af dyrtidssedler.
// 20130314 MasterID sættes på tilknyttede dyrtidssedler ved afslutning af akkord afregning; 
// 20130322 Fejl i kontrol for skur - søeg 20130322
// 20130401 Indført "trainee fordeling" søg på $fordel_timer, tjek_fordeling og trainee
// 20130408 Tilføjet transport og telt tillæg - søg transport og telt
// 20130422 Tilføjet mulighed for afvisning at godkendte sedler + krav om kommentar ved afvisning.
// 20130422 Tilføjet afregning af løn + dato.
// 20130422 Tilføjet mulighed for at se egen afregning for medarbejdere.
// 20130501	Tilføjet kontrol for 2x telttillaeg - søg telt_tillaeg
// 20130531	Masterid sættes nu til 0 i stedet for ny ID ved afvisning. 
//          Årsag, v eftergølgende ændring af opgavene blev timer hængende på afregning - søg 20130531
// 20130604	Afviste akkordtimer blev medtaget på afregning. Søg 20130604
// 20130604	Sum forkert ved klik på overfør uden at gemme efter ændring. Søg 20130604
// 20130607 Indsat " || $r['vare_id']<0". Der kunne kun sættes transport på 1 stilladstype. søg 20130607
// 20130905 Fejl v. trainee på akkordsedler da $loen_datoer[$x] ikke bliver sat Søg 20130905
// 20130905	Akkordsedler lukker ikke efter afvisning Søg 20130905-2
// 20131003	Sammenblanding af opgaver efter afvisning af akk_afr. Endvidere tilføjet visning af seddel nr v mouse over på dato  Søg 20131003 eller $akkord_nr  
// 20131004	Akkordsedler bliver ikke frigivet fra master ved ændring af opgave. Søg 20131004 & gl_opg_id 
// 20131004 Skur forsvinder v. afvisning søg 20131004-2
// 20140325 Kørsel er sat tilbage på lønopgørelse. Søg 20142503
// 20140326 Rettet "order by vare_id" tilbage til "order by tekst" da "order by vare_id" ødelægger sammentællingen.
// 20140328 Tilføjet 'varenr' i tabel 'loen_enheder', og ændret til 'order by varenr,tekst'. Søg 20140328
// 20140502	PHR - Ændret 50% sats fra 56.95 til 58.35 & 100% sats fra 113.85 til 116.7. Søg 58.35 eller 116.7 
// 20140506	PHR - Dyrtid kom ikke med på alle afregninger fordi afregning søgte "where afvist<'1'" og i dyrtidssedlerne til sag 7384 var afvist NULL
//					Har derfor erstattet alle forekomster af afvist<'1' med (afvist<'1' or afvist is NULL) og har tilføjet afvist='' og afvist_af='' i insert into loen 
// 20140627 PK - Har indsat 'ferie' i løntype. Der kan vælges to datoer med fra og til, samt to nye felter i DB med 'feriefra' og 'ferietil' 
// 20140810 PHR - Fejl v. beregning af telttillæg hvis "type" ikke er telt. Søg 20140810 
// 20140909 PK - Har tilføjet udførelses adresse på lønopgørelse for den enkelte ansatte. Søg 20140909
// 20140923 PK - Har rettet udførelses adresse på lønopgørelse, så alle adresser på den enkelte dato vises. Søg 20140923
// 20141003 PK - Har lavet lønopgørelse af alle ansatte, så alle medarbejders lønopgørelse vises. Det er nu muligt at printe alle medarbejders lønopgørelse på en gang. 
//          Ulempe, Var nød til at kopier hele funktionen i en 'if else'. Der skal så rettes to steder, i stedet for et, i funktion 'loenafregning'. Søg 20141003
// 20141103 PHR ($t50pct)?$t50pct.=chr(9).$r['t50pct']:$t50pct=$r['t50pct']; gav det problem at hvis den 1. $r['t50pct'] var tom rykkede de efterfølgende en plads op i akk-afr.
//          Samme problem for øvrige variabler. 
// 20141217 PK - Har tilføjet hvilke status der skal vises i opgaver. Opgaver med status 'Tilbud', 'Ordrebekræftelse' og 'Afsluttet' vises ikke i liste. Søg Ordrebekræftelse
// 20150204 PHR - Fejl på beregning af telttillæg efter afslutning af akkordafregning. Sum1 erstattet at teltsum. Søg teltsum.
// 20152104	PK - Ændret 50% sats fra 58.35 til 59.18 og 100% sats fra 116.7 til 118.35. Satser pr. 1/3-15. Søg 59.18 eller 118.35
// 20151305	PK - Tilføjet Overtidstillæg 50% og 100% til funktion 'satser'. 50% og 100% sats er nu blevet til variabler. Søg $overtid_50pct eller $overtid_100pct
// 20150602	PHR - Fejl hvis $telt_pris ikke er sat.
// 20150617 PHR Flyttet "}" fra over $fratræk længere nede da km blev forkert Søg 20150617
// 20150618 PK - Indsat hidden field til 'loen_tekst', da teksten forsvandt under afvisning. Søg 20150618 
// 20150623 PK - Mange html rettelser på lønindtastning og akkordliste
// 20150623 PK - Har ændret validering af lønindtastning fra 'javascript:alert' til php-css. Det betyder færre click for bruger, da man ikke skal trykke alertbox væk.
//          Tilføjet valideringen af udført på 'akktimer,akk_afr,akkord,aconto,regulering og timer'. Har også fjernet 'slet' ved oprettelse af lønseddel, så den først vises efter der er trykket 'gem'. Søg 20150623-1
// 20150623 PK - Der er tilføjet print funktion til 'ret_loen'. Søg 20150623-2
// 20150623 PHR Km blev fratrukket på alle sedler selvom der havde samme dato. Har fungeret men er blevet kommenteret ud 20130226 Søg 20150623
// 20150702 PK - Har ændret den extra opdatering af siden i lønopgørelse, der henter 'alle_ansatte_id' til leftmenu. Nu laves et ajax kald for at opdatere leftmenu, i stedet for at opdatere hele siden igen. Søg 20150702
// 20150826	Telttillæg kom kun på hvis telt var valgt som type. Søg 20150826
// 20150928 $fratraek[$x]=$km_fra-($t_km-$loen_km[$x]) ændret til $fratraek[$x]=$km_fra-$t_km da kørsel på igangværende seddel ikke skal trækkes fra; Søg 2015092
// 20151009 $fratræek[$x] må aldrig være større end $loen_km[$x]. Søg 20151009
// 20151116 Tilføjet telt_antal som hidden variabel da telt kun kom med hvis telt var sat som type. Søg 20151116
// 20151215 Fjernet if da master_id ikke blev sat på akkord seddel hvis der var flere akkord sedler med samme dato på samme sag/opg.
// 20151216 Viser nr på afregningsseddel på akkordsedler mm. 
// 20160819 PK - Har tilføjet visning af timesum + fjernet sum fra 'aconto, regulering'. Søg 20160819
// 20161006 PHR - udkommenteret aconto og regulering fra select. Søg 20161006
// 20161031 PK - Afvisningstekst på ny seddel efter afvisning. Søg 20161031
// 20170524 PHR - Afviste sedler blev godkendt sammen med ikke afviste med samme master_id='$id',sag_id og opg_id # 20170524
// 20170920 PK - Indsat if($gemt>=1) foran hidden field med 'telt_antal', da 'gem't kører to gange og den mister det valgte i anden kørsel. Søg 20170920
// 20170921 PK - Har tilføjet Telt tillæg 35% og 55% samt bgcolor på hvert anden linje så det bliver lettere at læse. Søg $telt35 eller $telt55
// 20170922 PK - Har tilføjet $telt_antal i 'function vis_liste', da $telt_antal bliver sat til '0' ved anden kørsel i 'gemt' når der ikke er gemt i database. Søg vis_liste(
// 20170927 PK - Har fjernet Telt tillæg 55%, da det ikke er standart overdækning. Har også fjernet bgcolor på 60%. Søg $telt55
// 20171023 MO - Optimering af loenafregningsdelen. Loenafregning viser nu information for hver enkelt sag på en dag istedet for bare et samlet total for en enkelt dag.
// 20180117 PHR - Tilføjet "$loen_date[$x] &&" da der genereres fejl når variabel er tom. 


@session_start();
$s_id=session_id();

/*
ini_set("log_errors", 1);
ini_set("error_log", "/home/mols/logs/php-loen");
*/

$bg="nix";
$header='nix';

$menu_sager=NULL;
$menu_planlaeg=NULL;
$menu_dagbog=NULL;
$menu_kunder=NULL;
$menu_loen='id="menuActive"';
$menu_ansatte=NULL;
$menu_certificering=NULL;
$menu_medarbejdermappe=NULL;

$modulnr=0;
	
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("loenliste.php");

$r=db_fetch_array(db_select("select * from grupper where art = 'loen'",__FILE__ . " linje " . __LINE__));
list($overtid_50pct,$overtid_100pct)=explode(chr(9),$r['box8']);
$overtid=$r['box8'];
#cho "overtid: $overtid<br>";

if ($overtid==NULL) {
	#cho "Er tom<br>";
	$overtid_50pct='59.18';
	$overtid_100pct='118.35';
	db_modify("update grupper set box8='".$overtid_50pct.chr(9).$overtid_100pct."' where art = 'loen'",__FILE__ . " linje " . __LINE__);
}

$funktion=if_isset($_GET['funktion']);
if (!$funktion) $funktion="loenliste";
$sort=if_isset($_GET['sort']);
$nysort=if_isset($_GET['nysort']);
$vis=if_isset($_GET['vis']);

ini_set("display_errors", "0");

	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
		<head>
			<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
			<meta name=\"viewport\" content=\"width=1024\">
			<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\">
			<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/form.css\">
			<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/search.css\">
			<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/pajinate.css\">
			<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/smoothness/jquery-ui-1.9.2.custom.css\">
			<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/print.css\" media=\"print\">
			<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery-ui-1.9.2.custom.min.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/ui.datepicker-da.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery.autosize.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery.table.addrow.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery.tablednd_0_5.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery.pajinate.js\"></script>
			<script type=\"text/javascript\" src=\"../javascript/jquery.cookie.js\"></script>
			
			<!--[if lt IE 9]>
			<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
			<![endif]-->
			
			<script type=\"text/javascript\">
			
			/* jQuery funktion til autosize på textarea */
			$(document).ready(function(){
				$('.autosize').autosize();
			});
		
			/* javascript funktion til print */
			function printDiv(divName) { // Original code. MÅ IKKE SLETTES!!!!!
				var printContents = document.getElementById(divName).innerHTML;
				var originalContents = document.body.innerHTML;

				document.body.innerHTML = printContents;

				window.print();

				document.body.innerHTML = originalContents;
			}
			</script>
			<style type=\"text/css\">
				.dp-highlight .ui-state-default {
					background: #484;
					color: #FFF;
				}
			</style>
			<title>Stillads</title>
		</head>
		<body>
		<div id=\"wrapper\">";
		include ("../includes/sagsmenu.php");
		print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
				if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Sager\"><img src=\"../img/home.png\" alt=\"Sager\" class=\"home\" /></a>\n";
				else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				print "</li>";
				if ($funktion=='ret_loen') {
					print "<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li>Lønindtastning</li>";
				} elseif ($funktion=='loenafregning') {
					print "<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li>Lønafregning</li>";
				} elseif ($funktion=='satser') {
					print "<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li>Satser</li>";
				} else {
				print "
				<!--<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
				<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
				<li>Current page</li>-->
				<li>Løn</li>";
				}
				if ($funktion=='ret_loen') print "<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>"; #20150623-2
				if ($funktion=='loenafregning') print "<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>";
		print "
			</ul>
			</div><!-- end of breadcrumbbar -->
				<div id=\"leftmenuholder\">";
					include ("leftmenu.php");
				print "</div><!-- end of leftmenuholder -->

				<div class=\"maincontent\">";
				$funktion();
				print "</div><!-- end of maincontent -->

		</div><!-- end of wrapper -->
		<script type=\"text/javascript\" src=\"../javascript/jquery.loen.js\"></script>
	</body>
	</html>";


function ret_loen()
{
	global $db_encode;
	global $brugernavn;
	global $sag_rettigheder;
	global $overtid_50pct;
	global $overtid_100pct;
	
	$afvis=NULL;$ansatte=NULL;$afsluttet=NULL;
	$beskyttet=NULL;
	$fordeling=NULL;$fratraek=array();
	$oprettet=NULL;
	$t50pct=NULL;$t100pct=NULL;$timer=NULL;

	if ($luk=if_isset($_POST['luk'])) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
		exit;
	}	
	$id=if_isset($_GET['id']);
	transaktion('begin');
	if ($gem=if_isset($_POST['gem'])|| $afslut=if_isset($_POST['afslut']) || $afvis=if_isset($_POST['afvis'])) {
		# 20160830 ->
		$afs=if_isset($_POST['afs']);
		$gemt=if_isset($_POST['gemt']);
#cho "Gemt $gemt";		
		if ($afslut)
		{
			$afs++;
			if ($afs<3)
			{
				$gem=$afslut;
				$afslut=NULL;
			}
		}
		elseif ($gem)
		{
			$gemt++;
		}

		# <- 20160830
		$id=if_isset($_POST['id']);
		$listevalg=if_isset($_POST['listevalg'])*1;
		$listevalg_ny=if_isset($_POST['listevalg_ny'])*1;
		$op=if_isset($_POST['op']);
		$ned=if_isset($_POST['ned']);
		$op_25=if_isset($_POST['op_25']);
		$ned_25=if_isset($_POST['ned_25']);
		$op_40=if_isset($_POST['op_40']);
		$ned_40=if_isset($_POST['ned_40']);
		$op_60=if_isset($_POST['op_60']);
		$ned_60=if_isset($_POST['ned_60']);
		$op_30m=if_isset($_POST['op_30m']);
		$ned_30m=if_isset($_POST['ned_30m']);
		$pris_op=if_isset($_POST['pris_op']);
		$pris_ned=if_isset($_POST['pris_ned']);
		$vare_id=if_isset($_POST['vare_id']);
		$vare_nr=if_isset($_POST['varenr']); // indsat 20142803
		$vare_tekst=if_isset($_POST['vare_tekst']);
		$tr_id=if_isset($_POST['tr_id']);
		$tr_antal=if_isset($_POST['tr_antal']);
		$tr_pris=if_isset($_POST['tr_pris']);
		$telt_id=if_isset($_POST['telt_id']);
		$telt_antal=if_isset($_POST['telt_antal']);
		$telt_pris=if_isset($_POST['telt_pris']);
		$enhed_id=if_isset($_POST['enhed_id']);
		$loen_nr=if_isset($_POST['loen_nr']);
		$loen_art=if_isset($_POST['loen_art']);
		$loen_tekst=db_escape_string(if_isset($_POST['loen_tekst']));
		$loen_ansatte=if_isset($_POST['ansatte']);
		$loen_date=if_isset($_POST['loen_date']);
		$loen_fordeling=if_isset($_POST['loen_fordeling']);
		$loen_timer=if_isset($_POST['loen_timer']);
		$loen_50pct=if_isset($_POST['loen_50pct']);
		$loen_100pct=if_isset($_POST['loen_100pct']);
		$loen_loen=if_isset($_POST['loen_loen']);
		$skur1=if_isset($_POST['skur1']);
		$skur2=if_isset($_POST['skur2']);
		$skur_sats1=if_isset($_POST['skur_sats1']);
		$skur_sats2=if_isset($_POST['skur_sats2']);
		$loen_km=if_isset($_POST['loen_km']);
		$km_sats=if_isset($_POST['km_sats']);
		$km_fra=if_isset($_POST['km_fra']);
		$hvem=db_escape_string(if_isset($_POST['hvem']));
		$sag_nr=if_isset($_POST['sag_nr'])*1;
		$sag_id=if_isset($_POST['sag_id'])*1;
		$sag_ref=if_isset($_POST['sag_ref']);
		$opg_nr=if_isset($_POST['opg_nr'])*1;
		$gl_opg_id=if_isset($_POST['gl_opg_id'])*1;
		$opg_id=if_isset($_POST['opg_id'])*1;
		$loendato=if_isset($_POST['loendato']);
		$loendate=usdate($loendato);
		$oprettet=if_isset($_POST['oprettet']);
		$oprettet_af=if_isset($_POST['oprettet_af']);
		$afsluttet=if_isset($_POST['afsluttet']);
		$godkendt=if_isset($_POST['godkendt']);
		$godkendt_af=if_isset($_POST['godkendt_af']);
		$afvist=if_isset($_POST['afvist']);
		$afvist_af=if_isset($_POST['afvist_af']);
		$afvist_pga=if_isset($_POST['afvist_pga']);
#		$tilbagefoer=if_isset($_POST['tilbagefoer']);
		$loen_id=if_isset($_POST['loen_id']);
		$ansat_id=if_isset($_POST['ansat_id']);
		$medarb_nr=if_isset($_POST['medarb_nr']);
		$medarb_navn=if_isset($_POST['medarb_navn']);
		$sum=if_isset($_POST['sum'])*1;
		$dksum=if_isset($_POST['dksum']);
		$a_id=if_isset($_POST['a_id']);
		$a_stk=if_isset($_POST['a_stk']);
		$a_txt=if_isset($_POST['a_txt']);
		$a_pris=if_isset($_POST['a_pris']);
		$a_pct=if_isset($_POST['a_pct']);
		$feriefra=if_isset($_POST['feriefra']); // indsat 20140627
		$ferietil=if_isset($_POST['ferietil']); // indsat 20140627
		#cho "telt_antal: $telt_antal<br>"; echo "gemt $gemt<br>";
 #cho "loendate: $loendate<br>";
		if ($opg_id && !$opg_nr) {
			$r=db_fetch_array(db_select("select nr from opgaver where id = '$opg_id'",__FILE__ . " linje " . __LINE__));
			$opg_nr=$r['nr']*1;
		}

		if (($loen_art=='akk_afr' || $loen_art=='akkord') && $sag_nr) {
		$r=db_fetch_array(db_select("select id,nummer from loen where (art='akk_afr' or art='akkord') and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and afvist = '' and id != '$id'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				$fejltxt="Der eksisterer allerede en uafsluttet akkordseddel (nr: $r[nummer]) for den ".$loendate." på sag nr: $sag_nr, opgave nr:$opg_nr!";
#				$sag_nr='0';
#				$sag_id=0;
				$opg_nr=0;
			}
		}
		if ($loen_art=='akktimer' && $sag_nr) {
#cho "select id from loen where (art='akktimer' or art='akkord') and loendate='".usdate($loendato)."' and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and (master_id='$id' or master_id='0' or master_id=NULL) and id != '$id'<br>";
			$r=db_fetch_array(db_select("select id,nummer from loen where (art='akktimer' or art='akkord') and loendate='".usdate($loendato)."' and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and afvist = '' and (master_id='$id' or master_id='0' or master_id=NULL) and id != '$id'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				$fejltxt="Der eksisterer allerede en uafsluttet akkordtimeseddel (nr: $r[nummer]) for den ".$loendate." på sag nr: $sag_nr, opgave nr:$opg_nr!";
#				$sag_nr='0';
#				$sag_id=0;
				$opg_nr=0;
			}
		}
		if ($afslut=isset($_POST['afslut']) && $afslut) {
			$afsluttet=date("U");
			$afsluttet_af=$brugernavn;
		} else $afsluttet_af=NULL;
		for ($x=0;$x<count($medarb_nr);$x++) {
			if (($skur1[$x] || $skur2[$x]) && $loen_art!='akk_afr') { #20130226 + 20130322 ( and afvist<'1')
				$q=db_select("select * from loen where (art='akktimer' or art='akkord' or art='timer') and loendate='$loendate' and id < '$id' and (afvist<'1' or afvist is NULL) order by id",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					$a=explode(chr(9),$r['ansatte']);
					if (in_array($ansat_id[$x],$a)) {
						list($s1,$s2)=explode("|",$r['skur']);
						$sk1=explode(chr(9),$s1);
						$sk2=explode(chr(9),$s2);
							for ($i=0;$i<count($a);$i++) {
							if ($a[$i]==$ansat_id[$x]) {
								$ret_skur[$x]=NULL;
								if ($sk1[$i]||$sk2[$i]){
									$ret_skur[$x]="off";
									$fejltxt="Der er allerede skur d. ".dkdato($loendate)." for medarb.nr: $medarb_nr[$x] på seddel $r[nummer]";
									#print "<BODY onLoad=\"javascript:alert('$txt')\">";
									$skur1[$x]=NULL;
									$skur2[$x]=NULL;
								}
							}
						}
					}
				}
			}

			if (!isset($medarb_navn[$x]))$medarb_navn[$x]=NULL;
			if ($loen_fordeling[$x]) $loen_fordeling[$x]*=1;
			if ($loen_date[$x]) $loen_datoer[$x]=usdate($loen_date[$x]);
			if ($loen_timer[$x]) $loen_timer[$x]=str_replace(",",".",$loen_timer[$x])*1;
			if ($loen_50pct[$x]) $loen_50pct[$x]=str_replace(",",".",$loen_50pct[$x])*1;
			if ($loen_100pct[$x]) $loen_100pct[$x]=str_replace(",",".",$loen_100pct[$x])*1;
			if ($skur1[$x]) {
				$skur1[$x]=$skur_sats1;
				$skur2[$x]=0;
			} elseif ($skur2[$x])	{
				$skur2[$x]=$skur_sats2;
				$skur1[$x]=0;
			} else {$skur1[$x]=0;$skur2[$x]=0;}
			if ($loen_km[$x]) $loen_km[$x]=str_replace(",",".",$loen_km[$x])*1;
			if 	(!$medarb_nr[$x] && !$medarb_navn[$x]) $ansat_id[$x]=0;
			if ($medarb_nr[$x]) {
				$r=db_fetch_array(db_select("select id,trainee,startdate from ansatte where nummer='$medarb_nr[$x]'",__FILE__ . " linje " . __LINE__));
				$ansat_id[$x]=$r['id']*1;
				if ($r['trainee']) {
					if ($loen_art=='akk_afr') $loen_fordeling[$x]=tjek_fordeling($ansat_id[$x],$r['startdate'],$loen_datoer[$x]); #20130905
					else $loen_fordeling[$x]=tjek_fordeling($ansat_id[$x],$r['startdate'],$loendate);
				}	else $loen_fordeling[$x]=100;
				if ($ansat_id[$x]) {
					if($ansatte){
						$ansatte.=chr(9).$ansat_id[$x];
						$loen.=chr(9).$loen_loen[$x];
						$fordeling.=chr(9).$loen_fordeling[$x];
						$timer.=chr(9).$loen_timer[$x];
						$t50pct.=chr(9).$loen_50pct[$x];
						$t100pct.=chr(9).$loen_100pct[$x];
						$skur_1.=chr(9).$skur1[$x];
						$skur_2.=chr(9).$skur2[$x];
						$korsel.=chr(9).$loen_km[$x];
						$datoer.=chr(9).$loen_datoer[$x];
					} else {
						$ansatte=$ansat_id[$x];
						$loen=$loen_loen[$x];
						$fordeling=$loen_fordeling[$x];
						$timer=$loen_timer[$x];
						$t50pct=$loen_50pct[$x];
						$t100pct=$loen_100pct[$x];
						$skur_1=$skur1[$x];
						$skur_2=$skur2[$x];
						$korsel=$loen_km[$x];
						$datoer=$loen_datoer[$x];
					}
				}
			}
#cho "fordeling $fordeling<br>";
			if ($medarb_navn[$x] && !$ansat_id[$x]) {
				$medarb_navn[$x]=db_escape_string($medarb_navn[$x]);
				$r=db_fetch_array(db_select("select id from ansatte where navn='$medarb_navn[$x]'",__FILE__ . " linje " . __LINE__));
				$ansat_id[$x]=$r['id']*1;
			} else $ansat_id[$x]=0;
			if ($ansat_id[$x]) {
				($ansatte)?$ansatte.=chr(9).$ansat_id[$x]:$ansatte=$ansat_id[$x];
			}
		}
		$skur=$skur_1."|".$skur_2;
		$korsel.="|$km_sats|$km_fra";
		$r=db_fetch_array(db_select("select id,ref from SAGER where sagsnr='$sag_nr'",__FILE__ . " linje " . __LINE__));
		$sag_id=$r['id']*1;
		$sag_ref=$r['ref'];
		if (!$oprettet) $oprettet=date('U');
		#		$loendate=usdate($loendato);
		
		/* Validering af lønindtastning */ #20150623-1
		if (!$loendato || $loendato=="01-01-1970") {
			$loendato="01-01-1970";
			$loendate=usdate($loendato);
			$datotext_errortxt="<span style=\"color: red;\">Dato ikke udfyld</span>";
			$datotext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			//print "<BODY onLoad=\"javascript:alert('Dato ikke udfyld')\">"; // laves om til css-validering???
		} else {
			$datotext_errortxt=NULL;
			$datotext_error=NULL;
		}
		if (strstr($loen_art,'akk') && !$sag_nr) { // Er ikke sikker på at det er nødvendigt at have 'aconto,regulering,timer' med???
			$sagsnr_errortxt="<span style=\"color: red;\">Sagsnr ikke valgt</span>";
			$sagsnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			//print "<BODY onLoad=\"javascript:alert('Sagsnr ikke valgt')\">"; // laves om til css-validering???
		} else {
			$sagsnr_errortxt=NULL;
			$sagsnr_error=NULL;
		}
		if ((strstr($loen_art,'akk') || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer') && !$opg_nr) {
			$opgnr_errortxt="<span style=\"color: red;\">Opgave ikke valgt</span>";
			$opgnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} else {
			$opgnr_errortxt=NULL;
			$opgnr_error=NULL;
		}
		if (!$feriefra && $ferietil) {
			$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' er ikke valgt</span>";
			$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} elseif ($feriefra && !$ferietil) {
			$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Til' er ikke valgt</span>";
			$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} elseif (!$feriefra && !$ferietil) {
			$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' og 'Til' er ikke valgt</span>";
			$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} else {
			$feriefratil_errortxt=NULL;
			$feriefra_error=NULL;
			$ferietil_error=NULL;
		}
		if(!$loen_tekst && ((strstr($loen_art,'akk')) || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer')) {
			$loentext_errortxt="<span style=\"color: red;\">Udført er ikke udfyldt</span>";
			$loentext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			//print "<BODY onLoad=\"javascript:alert('Udført er ikke udfyldt')\">"; // laves o til css-validering??
		} else {
			$loentext_errortxt=NULL;
			$loentext_error=NULL;
		}
		if ($id) {
			if ($loen_art=='aconto' || $loen_art=='regulering') $sum=usdecimal($dksum);
			if (!$afvis) {
				$qtxt="update loen set art='$loen_art',kategori='$listevalg',nummer='$loen_nr',sag_id='$sag_id',opg_id='$opg_id',sag_nr='$sag_nr',opg_nr='$opg_nr',oprettet='$oprettet',afsluttet='$afsluttet',afsluttet_af='$afsluttet_af',afvist='$afvist',afvist_af='$afvist_af',datoer='$datoer',ansatte='$ansatte',fordeling='$fordeling',loen='$loen',timer='$timer',t50pct='$t50pct',t100pct='$t100pct',skur='$skur',sum='$sum',loendate='$loendate',tekst='$loen_tekst',korsel='$korsel',sag_ref='$sag_ref',feriefra='$feriefra',ferietil='$ferietil' where id='$id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($opg_id!=$gl_opg_id && $gl_opg_id) { #20131004
					$qtxt="update loen set master_id='0' where id='$id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			if ($afslut && $loen_art=='akk_afr') {
				$qtxt="update loen set afsluttet='$afsluttet',afsluttet_af='$afsluttet_af',master_id='$id' where master_id='$id' or (sag_id='$sag_id' and art='akktimer' and afsluttet='' and id != '$id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				for ($i=0;$i<count($ansat_id);$i++) {
#cho __line__." $i $loen_date[$i] ".$loen_date[$i-1]." select * from loen where loendate='".usdate($loen_date[$i])."' and art = 'akktimer' and sag_id='$sag_id' and opg_id='$opg_id' and (master_id is NULL or master_id='0')<br>";
					if ($loen_date[$i] && $r=db_fetch_array(db_select("select * from loen where loendate='".usdate($loen_date[$i])."' and art = 'akktimer' and sag_id='$sag_id' and opg_id='$opg_id' and (master_id is NULL or master_id='0')",__FILE__ . " linje " . __LINE__))) {
#cho __line__." $i|$r[id]<br>";		
#						if ($i<1 || $loen_date[$i]!=$loen_date[$i-1]) { 20151215
							$t=explode(chr(9),$timer);
							$match=1;
#cho __line__." $r[id] -> $match<br>";		
							for ($n=0;$n<count($t);$n++) {
								if ($loen_timer[$n]!=$t[$n]) $match=0;
							}
#cho __line__." $match<br>";		
							if ($match) {
								$qtxt="update loen set master_id='$id' where id='$r[id]'";
#cho __line__." $qtxt<br>"	;
								db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
							}
#						}
					}
				}
#xit;
				transaktion("commit");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
			} elseif ($afvis && $afvist_pga) {
				$afvist_af=$brugernavn;
				$afvist=date('U');
				$afvist_pga=db_escape_string($afvist_pga);
#				db_modify("update loen set afsluttet='',afsluttet_af='' where master_id='$id'",__FILE__ . " linje " . __LINE__);
				db_modify("update loen set sum='$sum',afvist='$afvist',afvist_af='$afvist_af',afvist_pga='$afvist_pga',godkendt='' where id='$id'",__FILE__ . " linje " . __LINE__);
				$afvis_id=$id;
				$id=0;
			#				exit;
			}
		}
		if (!$id) {
			if (!$afvist) {
				$oprettet_af=$brugernavn;
				$oprettet=date('U');
				$r=db_fetch_array(db_select("select max(nummer) as nummer from loen",__FILE__ . " linje " . __LINE__));
				$loen_nr=$r['nummer']+1;
			} else { #20131004-2
				$qtxt="select skur from loen where id='$afvis_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$skur=$r['skur'];
			} // Har indsat 'afvist_pga' i insert, så man kan se årsagen til afvisningen på den nye seddel #20161031
			$qtxt="insert into loen (art,kategori,nummer,hvem,sag_nr,sag_id,oprettet,afsluttet,godkendt,afregnet,oprettet_af,ansatte,datoer,fordeling,loen,timer,t50pct,t100pct,skur,sum,loendate,tekst,korsel,opg_id,opg_nr,sag_ref,afvist,afvist_af,afvist_pga,feriefra,ferietil) values ('$loen_art','$listevalg','$loen_nr','','$sag_nr','$sag_id','$oprettet','','','','$oprettet_af','$ansatte','$datoer','$fordeling','$loen','$timer','$t50pct','$t100pct','$skur','$sum','$loendate','$loen_tekst','$korsel','$opg_id','$opg_nr','$sag_ref','','','$afvist_pga','$feriefra','$ferietil')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select max(id) as id from loen where nummer='$loen_nr'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
			if ($afvis && $afvist_pga) db_modify("update loen set afsluttet='',afsluttet_af='',master_id='0' where master_id='$afvis_id'",__FILE__ . " linje " . __LINE__); #20130531
		}
#cho "loen_art $loen_art<br>";
		if (($loen_art=='akk_afr' || $loen_art=='akkord')) {
			$akksum=0;
			$tr_antal=str_replace(",",".",$tr_antal)*1;
			$telt_antal=str_replace(",",".",$telt_antal)*1;
			if ($tr_id) {
#cho "update loen_enheder set op='$tr_antal',pris_op='$tr_pris' where id='$tr_id'<br>";
				db_modify("update loen_enheder set op='$tr_antal',pris_op='$tr_pris' where id='$tr_id'",__FILE__ . " linje " . __LINE__);
			} elseif ($tr_antal) {
				$tmp=$listevalg."|Transport";
#cho "A insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','-1','$tr_antal','0','0','0','0','0','0','0','0','0','0','0','$tmp','0')<br>";
				db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','-1','$tr_antal','0','0','0','0','0','0','0','0','0','$tr_pris','0','$tmp','0')",__FILE__ . " linje " . __LINE__);

				}
#cho "kategori $listevalg<br>";
			if ($listevalg=='7') {
				if ($telt_id && $telt_antal) {
#cho "update loen_enheder set op='$telt_antal',pris_op='$telt_pris' where id='$telt_id'<br>";
					db_modify("update loen_enheder set op='$telt_antal',pris_op='$telt_pris' where id='$telt_id'",__FILE__ . " linje " . __LINE__);
				} elseif ($telt_antal && $telt_pris) { #20150602
					$tmp=$listevalg."|Telt";
#cho "B insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','-2','$telt_antal','0','0','0','0','0','0','0','0','0','$telt_pris','0','$tmp','0')<br>";
					db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','-2','$telt_antal','0','0','0','0','0','0','0','0','0','$telt_pris','0','$tmp','0')",__FILE__ . " linje " . __LINE__);
				}
			}
			for ($x=0;$x<=count($vare_id);$x++) {
				$op[$x]=str_replace(",",".",$op[$x]);
				$ned[$x]=str_replace(",",".",$ned[$x]);
				$op_25[$x]=str_replace(",",".",$op_25[$x]);
				$ned_25[$x]=str_replace(",",".",$ned_25[$x]);
				$op_40[$x]=str_replace(",",".",$op_40[$x]);
				$ned_40[$x]=str_replace(",",".",$ned_40[$x]);
				$op_60[$x]=str_replace(",",".",$op_60[$x]);
				$ned_60[$x]=str_replace(",",".",$ned_60[$x]);
				$op_30m[$x]=str_replace(",",".",$op_30m[$x]);
				$ned_30m[$x]=str_replace(",",".",$ned_30m[$x]);

#cho "$enhed_id[$x] Op $op[$x] Ned $ned[$x]<br>";				
				$op[$x]*=1;$ned[$x]*=1;$op_25[$x]*=1;$ned_25[$x]*=1;$op_40[$x]*=1;$ned_40[$x]*=1;$op_60[$x]*=1;$ned_60[$x]*=1;$op_30m[$x]*=1;$ned_30m[$x]*=1;$pris_op[$x]*=1;$pris_ned[$x]*=1;
				$linjesum[$x]=$op[$x]*$pris_op[$x]+$op_25[$x]*$pris_op[$x]*0.25+$op_40[$x]*$pris_op[$x]*0.4+$op_60[$x]*$pris_op[$x]*0.6;
				$linjesum[$x]+=$ned[$x]*$pris_ned[$x]+$ned_25[$x]*$pris_ned[$x]*0.25+$ned_40[$x]*$pris_ned[$x]*0.4+$ned_60[$x]*$pris_ned[$x]*0.6;
				$akksum+=$linjesum[$x];
				if (isset($enhed_id[$x]) && $enhed_id[$x] && !$afvist) {
					if ($op[$x]||$ned[$x]) {
#cho "update loen_enheder set op='$op[$x]',ned='$ned[$x]',op_25='$op_25[$x]',ned_25='$ned_25[$x]',op_40='$op_40[$x]',ned_40='$ned_40[$x]',op_60='$op_60[$x]',ned_60='$ned_60[$x]',op_30m='$op_30m[$x]',ned_30m='$ned_30m[$x]',pris_op='$pris_op[$x]',pris_ned='$pris_ned[$x]',tekst='$vare_tekst[$x]',procent='0' where id='$enhed_id[$x]'";
						db_modify("update loen_enheder set op='$op[$x]',ned='$ned[$x]',op_25='$op_25[$x]',ned_25='$ned_25[$x]',op_40='$op_40[$x]',ned_40='$ned_40[$x]',op_60='$op_60[$x]',ned_60='$ned_60[$x]',op_30m='$op_30m[$x]',ned_30m='$ned_30m[$x]',pris_op='$pris_op[$x]',pris_ned='$pris_ned[$x]',tekst='$vare_tekst[$x]',procent='0' where id='$enhed_id[$x]'",__FILE__ . " linje " . __LINE__);
					}	else db_modify("delete from loen_enheder where id='$enhed_id[$x]'",__FILE__ . " linje " . __LINE__);
				} elseif (($op[$x] || $ned[$x]) && (!$afvist || $afvist_pga)) {
#cho "C insert into loen_enheder (loen_id,vare_id,op,ned,pris_op,pris_ned,tekst,procent) values ('$id','$vare_id[$x]','$op[$x]','$ned[$x]','$pris_op[$x]','$pris_ned[$x]','$vare_tekst[$x]','0')<br>";
					if (is_numeric($vare_id[$x])) db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr) values ('$id','$vare_id[$x]','$op[$x]','$ned[$x]','$op_25[$x]','$ned_25[$x]','$op_40[$x]','$ned_40[$x]','$op_60[$x]','$ned_60[$x]','$op_30m[$x]','$ned_30m[$x]','$pris_op[$x]','$pris_ned[$x]','$vare_tekst[$x]','0','$vare_nr[$x]')",__FILE__ . " linje " . __LINE__);
					}	
				}
				if ($afvist && $afvis_id && $id) {
#cho "select * from loen_enheder where loen_id='$afvis_id' and vare_id < '0'<br>";
					$q=db_select("select * from loen_enheder where loen_id='$afvis_id' and vare_id < '0'",__FILE__ . " linje " . __LINE__);
					while($r=db_fetch_array($q)){
#cho "D insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','$r[vare_id]','$r[op]','$r[ned]','$r[op_25]','$r[ned_25]','$r[op_40]','$r[ned_40]','$r[op_60]','$r[ned_60]','$r[op_30m]','$r[ned_30m]','$r[pris_op]','$r[pris_ned]','$r[tekst]','$r[procent]')<br>";					
						db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr) values ('$id','$r[vare_id]','$r[op]','$r[ned]','$r[op_25]','$r[ned_25]','$r[op_40]','$r[ned_40]','$r[op_60]','$r[ned_60]','$r[op_30m]','$r[ned_30m]','$r[pris_op]','$r[pris_ned]','$r[tekst]','$r[procent]','$r[varenr]')",__FILE__ . " linje " . __LINE__);
					}
				}
			#			db_modify("update loen set sum='$akksum' where id='$id'",__FILE__ . " linje " . __LINE__);
			for ($x=0;$x<count($a_stk);$x++) {
				$a_stk[$x]=usdecimal($a_stk[$x])*1;
				$a_pris[$x]=usdecimal($a_pris[$x])*1;
				$a_txt[$x]=db_escape_string(trim($a_txt[$x]));
				if ($a_pct[$x]=='') $a_pct[$x]=100;
				else $a_pct[$x]=usdecimal($a_pct[$x])*1;
				$akksum+=$a_stk[$x]*$a_pris[$x];
				if (isset($a_id[$x]) && $a_id[$x] && !$afvist) {
#cho "Stk: $a_stk[$x] ID: $a_id[$x]<br>";
					if ($a_stk[$x]) {
					#cho "update loen_enheder set op='$a_stk[$x]',ned='0',pris_op='$a_pris[$x]',pris_ned='0',tekst='$a_txt[$x]',procent='$a_pct[$x]' where id='$a_id[$x]'<br>";
						db_modify("update loen_enheder set op='$a_stk[$x]',ned='0',pris_op='$a_pris[$x]',pris_ned='0',tekst='$a_txt[$x]',procent='$a_pct[$x]' where id='$a_id[$x]'",__FILE__ . " linje " . __LINE__);
					} else {
#cho "delete from loen_enheder where id='$a_id[$x]'<br>";
						db_modify("delete from loen_enheder where id='$a_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
				} elseif ($a_stk[$x]) {
#cho "E insert into loen_enheder (loen_id,vare_id,op,ned,pris_op,pris_ned,tekst,procent) values ('$id','0','$a_stk[$x]','0','$a_pris[$x]','0','$a_txt[$x]','$a_pct[$x]')<br>";
					db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,pris_op,pris_ned,tekst,procent) values ('$id','0','$a_stk[$x]','0','$a_pris[$x]','0','$a_txt[$x]','$a_pct[$x]')",__FILE__ . " linje " . __LINE__);
				}
			}
		} # endif ($loen_art=='akk_afr')
		if ($afvis && $afvist_pga)	{ #20130905-2
			transaktion('commit');
			print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
			exit;
		}
	} elseif ($godkend=if_isset($_POST['godkend'])) {
		$id=if_isset($_POST['id']);
		$sag_id=if_isset($_POST['sag_id'])*1;
		$opg_id=if_isset($_POST['opg_id'])*1;
#cho "$sag_id ".$_POST['sag_id'],"<br>";
		$godkendt=date("U");
		$godkendt_af=$brugernavn;
		if ($id) {
			db_modify("update loen set godkendt='$godkendt',godkendt_af='$godkendt_af' where id='$id'",__FILE__ . " linje " . __LINE__);
			$qtxt="update loen set godkendt='$godkendt',godkendt_af='$godkendt_af' ";
			$qtxt.="where master_id='$id' and sag_id='$sag_id' and opg_id ='$opg_id' and afvist=''";# 20170524 Tilføjet  "and  afvist=''"
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			db_modify("update loen set master_id='0' where master_id='$id' and (sag_id!='$sag_id' or opg_id !='$opg_id')",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
			print "<BODY onLoad=\"javascript:alert('Sedlen er godkendt!')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
		}		
	} elseif ($slet=if_isset($_POST['slet'])) {
		if ($id=if_isset($_POST['id'])) {
			db_modify("delete from loen where id='$id'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from loen_enheder where loen_id='$id'",__FILE__ . " linje " . __LINE__);
		}
		transaktion('commit');
		print "<BODY onLoad=\"javascript:alert('Sedlen er slettet!')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
	}
#xit;
	transaktion('commit');
	$id*=1;
	$ansat_id=array();
	$loen_km=array();
	$r=db_fetch_array(db_select("select * from loen where id = '$id'",__FILE__ . " linje " . __LINE__));
	$loen_nr=$r['nummer']*1;
	$loen_tekst=$r['tekst'];
	$hvem=$r['hvem'];
	$sag_id=$r['sag_id']*1;
	$opg_id=$r['opg_id']*1;
	$sag_ref=$r['sag_ref'];
	$loendate=$r['loendate'];
	$oprettet=$r['oprettet'];
	$afsluttet=$r['afsluttet'];
	$godkendt=$r['godkendt'];
	$afvist=$r['afvist'];
	$oprettet_af=$r['oprettet_af'];
	$afsluttet_af=$r['afsluttet_af'];
	$godkendt_af=$r['godkendt_af'];
	$afvist_af=$r['afvist_af'];
	$afvist_pga=$r['afvist_pga'];
	$listevalg=$r['kategori'];
	$loen=$r['loen'];
	$sum=$r['sum'];
	$master_id=$r['master_id'];
#cho "S $sum<br>";	
	$loen_art=$r['art'];
	$feriefra=$r['feriefra']; // indsat 20140627
	$ferietil=$r['ferietil']; // indsat 20140627
	if ($loen_art=='akk_afr' && !$afsluttet) {
		$ansatte=NULL;
		$datoer=NULL;
		$fordeling=$r['fordeling'];
		$loen_fordeling=explode(chr(9),$fordeling);
		$timer=NULL;
		$t50pct=NULL;
		$t100pct=NULL;
		$loen_dato=NULL;
		$skur1=NULL;
		$skur2=NULL;
		$korsel=NULL;
#		$fordeling=NULL;
	} else {
		$ansatte=$r['ansatte'];
		$datoer=$r['datoer'];
		$fordeling=$r['fordeling'];
		$timer=$r['timer'];
		$t50pct=$r['t50pct'];
		$t100pct=$r['t100pct'];
		list($skur1,$skur2)=explode("|",$r['skur']);
		list($km,$km_sats,$km_fra)=explode("|",$r['korsel']);
#cho "$km,$km_sats,$km_fra<br>";
	if ($ansatte) {
			$ansat_id=explode(chr(9),$ansatte);
			$loen_fordeling=explode(chr(9),$fordeling);
			$loen_date=explode(chr(9),$datoer);
			$loen_loen=explode(chr(9),$loen);
			$loen_timer=explode(chr(9),$timer);
			$loen_50pct=explode(chr(9),$t50pct);
			$loen_100pct=explode(chr(9),$t100pct);
			$loen_skur1=explode(chr(9),$skur1);
			$loen_skur2=explode(chr(9),$skur2);
			$loen_km=explode(chr(9),$km);
		}
	}
	if ($loen_art=='akkord' || $loen_art=='akktimer'){ #Hvis masteren er afvist og master_id ikke er fjernet fjernes master_id så der kan opsamles igen
		if ($master_id) {
#cho "select nummer from loen where id='$master_id'<br>";
			if ($r2=db_fetch_array(db_select("select nummer from loen where id='$master_id' and sag_id='$sag_id' and opg_id='$opg_id'",__FILE__ . " linje " . __LINE__))) {
				$master_nr=$r2['nummer'];
#cho "Bundet på seddel nr $master_nr<br>";
			}	else {
#cho "update loen set master_id='0',godkendt='' where id='$id'<br>";
				db_modify("update loen set master_id='0' where id='$id'",__FILE__ . " linje " . __LINE__); #20161012 Fjernet godkendt='' da udbetalte sedler bliver afregnet igen.
				$master_id=NULL;
				$master_nr=NULL;
#				$godkendt=NULL; #20161012
			}
		}
	}
	if (!$afsluttet) {
		$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
		list($skur_sats1,$skur_sats2)=explode(chr(9),$r['box1']);
		$sygdom_sats=$r['box2'];
		$skole_sats=$r['box3'];
		$plads_sats=$r['box7'];
		list($traineemdr,$traineepct)=explode(chr(9),$r['box5']);
		list($km_sats,$km_fra)=explode(chr(9),$r['box6']);
		//list($overtid_50pct,$overtid_100pct)=explode(chr(9),$r['box8']);
	}
	
	if ($loen_art=='akk_afr' && $sag_id && !$afsluttet) {
	## 20130301 Finder ikke afvist selvom afvist er '' - derfor dette.	
#		if ($opg_id) $qtxt="select * from loen where sag_id = '$sag_id' and opg_id='$opg_id' and art='akktimer' and afvist='' and (master_id='$id' or master_id=0 or master_id is NULL) and id != '$id' order by loendate";
#		else $qtxt="select * from loen where sag_id = '$sag_id' and kategori = '$listevalg' and art='akktimer' and afsluttet='' and afvist='' and  and id != '$id' order by loendate";
		#20131003 tilføjet and opg_id='$opg_id'
		$qtxt="select * from loen where sag_id = '$sag_id' and opg_id='$opg_id' and art='akktimer' and id != '$id' and (master_id='$id' or master_id='0' or master_id is NULL) order by loendate";
#cho "$qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$y;
		while ($r=db_fetch_array($q)) {
#cho "ID $r[id]<br>";
		#cho "(".!trim($r['afvist'])." and (".!trim($r['afsluttet'])," || ".$r['opg_id']."==$opg_id))";
			if (!trim($r['afvist']) and ((!trim($r['afsluttet']) and $r['kategori']==$listevalg) || $r['opg_id']==$opg_id)) {	## 20130301 Query finder ikke afvist selvom afvist er '' - derfor dette.	
# 			if (!trim($r['afvist']) and !trim($r['afsluttet']) and $r['opg_id']==$opg_id)) {	## 20130301 Query finder ikke afvist selvom afvist er '' - derfor dette.	
				if ($ansatte){ # 20141103 
					$ansatte.=chr(9).$r['ansatte'];
					$fordeling.=chr(9).$r['fordeling'];
					$loen.=chr(9).$r['loen'];
					$timer.=chr(9).$r['timer'];
					$t50pct.=chr(9).$r['t50pct'];
					$t100pct.=chr(9).$r['t100pct'];
					list($s1,$s2)=explode("|",$r['skur']);
					$skur1.=chr(9).$s1;
					$skur2.=chr(9).$s2;
					list($k1,$km_sats,$km_fra)=explode("|",$r['korsel']);
					$km.=chr(9).$k1;
				} else {
					$ansatte=$r['ansatte'];
					$fordeling=$r['fordeling'];
					$loen=$r['loen'];
					$timer=$r['timer'];
					$t50pct=$r['t50pct'];
					$t100pct=$r['t100pct'];
					list($s1,$s2)=explode("|",$r['skur']);
					$skur1=$s1;
					$skur2=$s2;
					list($k1,$km_sats,$km_fra)=explode("|",$r['korsel']);
					$km=$k1;
				}
				for($x=0;$x<=substr_count($r['ansatte'],chr(9));$x++) ($ldate)?$ldate.=chr(9).$r['loendate']:$ldate=$r['loendate'];
				$tmp=array(); #20131003 + næste 4 linjer
				$tmp=explode(chr(9),$r['ansatte']);
				for($x=0;$x<count($tmp);$x++) { 
					($akk_nr)?$akk_nr.=chr(9).$r['nummer']:$akk_nr=$r['nummer'];
				}

			}
			#			$tmp=	
		}
		if ($ansatte) {
			$akkord_nr=explode(chr(9),$akk_nr); #20131003
			$ansat_id=explode(chr(9),$ansatte);
			$loen_fordeling=explode(chr(9),$fordeling);
			$loen_loen=explode(chr(9),$loen);
			$loen_timer=explode(chr(9),$timer);
			$loen_50pct=explode(chr(9),$t50pct);
			$loen_100pct=explode(chr(9),$t100pct);
			$loen_date=explode(chr(9),$ldate);
			$loen_skur1=explode(chr(9),$skur1);
			$loen_skur2=explode(chr(9),$skur2);
			$loen_km=explode(chr(9),$km);
		}
	}
	$x=0;
	$a_id=array();$a_vare_id=array();$a_stk=array();$a_txt=array();$a_pris=array();$a_pct=array();
	$q = db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id' and vare_id = '0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$a_id[$x]=$r['id'];
		$a_vare_id[$x]=$r['vare_id'];
		$a_stk[$x]=$r['op']*1;
		$a_txt[$x]=$r['tekst'];
		$a_pris[$x]=$r['pris_op']*1;
		$a_pct[$x]=$r['procent']*1;
		$x++;
	}
	if ($sag_id) {
		$x=0;
		$q = db_select("SELECT * FROM opgaver WHERE status != 'Ordrebekræftelse' AND status != 'Tilbud' AND status != 'Afsluttet' AND assign_to = 'sager' AND assign_id = '$sag_id' ORDER BY nr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$opgave_id[$x]=$r['id'];
			$opgave_nr[$x]=$r['nr'];
			$opgave_beskrivelse[$x]=$r['beskrivelse'];
			$x++;
		}
	}
	$aa_sum=0;
	$aa_v_id=array();
	$x=0;
	if ($loen_art=='akk_afr' || $loen_art=='akkord') {
		$q=db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['vare_id'],$aa_v_id) || $r['vare_id']<0) { #20130607 
				if ($r['vare_id']) $aa_v_id[$x]=$r['vare_id'];
				$aa_sum+=($r['op']*$r['pris_op']);
				$aa_sum+=($r['op_25']*$r['pris_op']*0.25);
				$aa_sum+=($r['op_40']*$r['pris_op']*0.4);
				$aa_sum+=($r['op_60']*$r['pris_op']*0.6);
				$aa_sum+=($r['op_30m']*$r['pris_op']*0.1);
				$aa_sum+=($r['ned']*$r['pris_ned']);
				$aa_sum+=($r['ned_25']*$r['pris_ned']*0.25);
				$aa_sum+=($r['ned_40']*$r['pris_ned']*0.4);
				$aa_sum+=($r['ned_60']*$r['pris_ned']*0.6);
				$aa_sum+=($r['ned_30m']*$r['pris_ned']*0.1);
				$x++;
#cho "AA 	$aa_sum  | $r[tekst] $r[op]*$r[pris_op] $r[ned]*$r[pris_ned]<br>";
			} else db_modify("delete from loen_enheder WHERE id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			
#cho "$r[vare_id] || $r[tekst] $r[op] $r[ned]<br>";
#cho "$r[op]*$r[pris_op] | $r[ned]*$r[pris_ned] |".$r['op']*$r['pris_op']."|".$r['ned']*$r['pris_ned']."| aa_sum $aa_sum<br>";
		}
	}
	$aa_sum80=$aa_sum*0.8;
	$aa_sum20=$aa_sum*0.2;
	$r=db_fetch_array(db_select("select sagsnr,udf_addr1 from sager where id = '$sag_id'",__FILE__ . " linje " . __LINE__));
	$sag_nr=$r['sagsnr'];
	$sag_addr=$r['udf_addr1'];

	for ($x=0;$x<count($ansat_id);$x++) {
		$ansat_id[$x]*=1;
#cho "select * from ansatte where id = '$ansat_id[$x]'<br>";
		$r=db_fetch_array(db_select("select * from ansatte where id = '$ansat_id[$x]'",__FILE__ . " linje " . __LINE__));
		$medarb_nr[$x]=$r['nummer'];
		$medarb_navn[$x]=$r['navn'];
#cho "$medarb_nr[$x] $medarb_navn[$x]<br>";
		$medarb_trainee[$x]=$r['trainee'];
		$medarb_startdate[$x]=$r['startdate'];
		$medarb_loen[$x]=str_replace(",",".",$r['loen'])*1;
		$medarb_extraloen[$x]=str_replace(",",".",$r['extraloen'])*1;
#cho "$medarb_trainee[$x] t $traineemdr $traineepct<br>";
	}
		
	($afsluttet || $godkendt)?$readonly="readonly=\"readonly\"":$readonly=NULL;
	($afsluttet)?$status="Afventer godk.":$status="Under indtast.";
	if($godkendt)$status="Godkendt";
	if($afvist)$status="Afvist";
	
	$y=0;
#	$q=db_select("select id,kodenr,art,box1 from grupper where art ='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
#	while ($r=db_fetch_array($q)) {
#		$y++;
#		$cat_id[$y]=$r['kodenr'];
#		$cat_navn[$y]=$r['box1'];
#	}
	$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$y]=$r['kodenr'];
		$cat_navn[$y]=$r['beskrivelse'];
		$y++;
	}

	$antal_cat=$y;
	if($loendate=='1970-01-01') { 
		$loendate='';
		$loendato='';
		$datotext_errortxt="<span style=\"color: red;\">Dato ikke udfyld</span>";
		$datotext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else { 
		$loendato=dkdato($loendate); 
		setlocale(LC_TIME, "danish"); 
		if ($loendate==NULL) {
			$loen_datotext=NULL;
		} else {
			$loen_datotext = strftime('%A den %d. %B %Y',strtotime($loendate));
			if ($db_encode=='UTF8') $loen_datotext=utf8_encode($loen_datotext); 
			$dato = date('d-m-y');
			$tid = date('H:i');
		}
	}
	/* Validering når lønseddel indlæses */ #20150623-1
	if (strstr($loen_art,'akk') && !$sag_nr) { 
		$sagsnr_errortxt="<span style=\"color: red;\">Sagsnr ikke valgt</span>";
		$sagsnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else {
		$sagsnr_errortxt=NULL;
		$sagsnr_error=NULL;
	}
	if ((strstr($loen_art,'akk') || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer') && !$opg_id) {
		$opgnr_errortxt="<span style=\"color: red;\">Opgave ikke valgt</span>";
		$opgnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else {
		$opgnr_errortxt=NULL;
		$opgnr_error=NULL;
	}
	if (!$feriefra && $ferietil) {
		$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' er ikke valgt</span>";
		$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} elseif ($feriefra && !$ferietil) {
		$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Til' er ikke valgt</span>";
		$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} elseif (!$feriefra && !$ferietil) {
		$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' og 'Til' er ikke valgt</span>";
		$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else {
		$feriefratil_errortxt=NULL;
		$feriefra_error=NULL;
		$ferietil_error=NULL;
	}
	if(!$loen_tekst && ((strstr($loen_art,'akk')) || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer')) {
		$loentext_errortxt="<span style=\"color: red;\">Udført er ikke udfyldt</span>";
		$loentext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;width: 560px;\"";
		//print "<BODY onLoad=\"javascript:alert('Udført er ikke udfyldt')\">"; // laves o til css-validering??
	} else {
		$loentext_errortxt=NULL;
		$loentext_error=NULL;
	}
	
#######################################
	print "<div id=\"printableArea\">\n";
	print "<form name=\"loen\" action=\"loen.php?funktion=ret_loen\" method=\"post\">
		<input type=\"hidden\" name=\"id\" value=\"$id\">
		<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">
		<input type=\"hidden\" name=\"opg_id\" value=\"$opg_id\">
		<input type=\"hidden\" name=\"gl_opg_id\" value=\"$opg_id\">
		<input type=\"hidden\" name=\"sag_nr\" value=\"$sag_nr\">
		<input type=\"hidden\" name=\"sag_ref\" value=\"$sag_ref\">
		<input type=\"hidden\" name=\"loen_nr\" value=\"$loen_nr\">
		<input type=\"hidden\" name=\"oprettet\" value=\"$oprettet\">
		<input type=\"hidden\" name=\"afsluttet\" value=\"$afsluttet\">
		<input type=\"hidden\" name=\"godkendt\" value=\"$godkendt\">
		<input type=\"hidden\" name=\"loen_tekst\" value=\"$loen_tekst\">"; #20150618
		if (!$afsluttet) {
			print "<input type=\"hidden\" name=\"skur_sats1\" value=\"$skur_sats1\">
			<input type=\"hidden\" name=\"skur_sats2\" value=\"$skur_sats2\">
			<input type=\"hidden\" name=\"km_sats\" value=\"$km_sats\">
			<input type=\"hidden\" name=\"km_fra\" value=\"$km_fra\">";
		}
		for($x=0;$x<count($a_id);$x++) print "<input type=\"hidden\" name=\"a_id[$x]\" value=\"$a_id[$x]\">";
		for ($x=0;$x<count($ansat_id);$x++) {
			print "<input type=\"hidden\" name=\"ansat_id[$x]\" value=\"$ansat_id[$x]\">"; 
			print "<input type=\"hidden\" name=\"loen_id[$x]\" value=\"$loen_id[$x]\">"; 
		}
		print "<div class=\"content\">
			<h3>Lønindtastning</h3>
				<div class=\"contentA\" style=\"#width: 758px;\">
					<div class=\"row\">
						<div class=\"leftSmall\">Dato: </div>
						<div class=\"rightSmall\"><input name=\"loendato\" id=\"datepicker\" type=\"text\" $readonly class=\"textMedium printBorderNone\" $datotext_error value=\"$loendato\"/></div><div class=\"rightNoWidth\"><p>$loen_datotext $datotext_errortxt</p></div> 
						<div class=\"clear\"></div>
					</div>
					<div class=\"row\">
						<div class=\"leftSmall\">Løntype:</div>
						<div class=\"rightLarge\">
							<select name=\"loen_art\" $readonly class=\"loen_art printSelect2\" style=\"width:100%;\">";
								if ($loen_art=='aconto') print "<option value=\"aconto\">Aconto</option>";
								elseif ($loen_art=='akktimer') print "<option value=\"akktimer\">Dyrtid</option>";
								elseif ($loen_art=='akk_afr') print "<option value=\"akk_afr\">Akkord afregning</option>";
								elseif ($loen_art=='akkord') print "<option value=\"akkord\">Akkord med dyrtid</option>";
								elseif ($loen_art=='timer') print "<option value=\"timer\">Timeløn</option>";
								elseif ($loen_art=='plads') print "<option value=\"plads\">Pladsarbejde</option>";
								elseif ($loen_art=='skole') print "<option value=\"skole\">Skoleophold</option>";
								elseif ($loen_art=='sygdom') print "<option value=\"sygdom\">Sygdom</option>";
								elseif ($loen_art=='barn_syg') print "<option value=\"barn_syg\">Barn syg</option>";
								elseif ($loen_art=='ferie') print "<option value=\"ferie\">Ferie</option>"; # 20140627
								elseif ($loen_art=='regulering') print "<option value=\"regulering\">Regulering</option>";
								else print "<option value=\"0\"></option>";
								# if ($loen_art!='aconto' && substr($sag_rettigheder,6,1)) print "<option value=\"aconto\">Aconto</option>"; 20161006
								if ($loen_art!='akktimer') print "<option value=\"akktimer\">Dyrtid</option>";
								if ($loen_art!='akk_afr') print "<option value=\"akk_afr\">Akkord afregning</option>";
								if ($loen_art!='akkord') print "<option value=\"akkord\">Akkord med dyrtid</option>";
								if ($loen_art!='timer') print "<option value=\"timer\">Timeløn</option>";
								if ($loen_art!='plads') print "<option value=\"plads\">Pladsarbejde</option>";
								if ($loen_art!='skole') print "<option value=\"skole\">Skoleophold</option>";
								if ($loen_art!='sygdom') print "<option value=\"sygdom\">Sygdom</option>";
								if ($loen_art!='barn_syg') print "<option value=\"barn_syg\">Barn syg</option>";
								if ($loen_art!='ferie') print "<option value=\"ferie\">Ferie</option>"; #20140627
								# if ($loen_art!='regulering' && substr($sag_rettigheder,6,1)) print "<option value=\"regulering\">Regulering</option>"; 20161006
							print "</select>
						</div>
						<div class=\"clear\"></div></div>";
						if ($loen_art=='akk_afr' || $loen_art=='akkord' || $loen_art=='akktimer') {
							if ($listevalg_ny && $listevalg_ny!=$listevalg && $gemt > 1) $listevalg=$listevalg_ny;
							print "<div class=\"row\"><div class=\"leftSmall\">Type: </div>
							<div class=\"rightLarge\">
								<select name=\"listevalg_ny\" $readonly class=\"akkordlistevalg printSelect2\" style=\"width: 100%;\">";
									if (!$listevalg) print "<option value=\"0\">Vælg type</option>";
									for ($y=0;$y<$antal_cat;$y++) {
										if ($cat_id[$y]==$listevalg) print "<option value=$cat_id[$y]>$cat_navn[$y]</option>";
									}						  
									if (!$readonly) {
										for ($y=0;$y<$antal_cat;$y++) {
											if ($cat_id[$y]!=$listevalg) print "<option value=$cat_id[$y]>$cat_navn[$y]</option>";
										}						  
									}
								print "</select>
							</div>
							<div class=\"clear\"></div></div>";
						} 
						print "<input type=\"hidden\" name=\"listevalg\" value=\"$listevalg\">";
#					print "</div>";
					if ($loen_art!='sygdom' && $loen_art!='barn_syg' &&  $loen_art!='skole' &&  $loen_art!='plads' && $loen_art!='ferie') { #20140627 
						print "<div class=\"row\">
							<div class=\"leftSmall\">Sag:</div>
							<div class=\"rightSmall\"><input type=\"text\" $readonly placeholder=\"Sags nr\" class=\"textMedium sagsnr printBorderNone printBg\" $sagsnr_error name=\"sag_nr\" value=\"$sag_nr\"></div>
							<div class=\"rightXLarge\"><input type=\"text\" $readonly placeholder=\"Sags addresse\" class=\"textXLong sagsaddr printBorderNone printBg\" $sagsnr_error name=\"sag_addr\" value=\"$sag_addr\"></div>
							<div class=\"rightNoWidth\"><p>$sagsnr_errortxt</p></div>
							<!--<div class=\"rightMedium\"><p id=\"message\">Ingen resultat fundet</p></div>-->
							<div class=\"clear\"></div>
						</div>";
						if ($sag_id && $opgave_id) {	
							print "<div class=\"row\">
								<div class=\"leftSmall\">Opgave:</div>
								<div class=\"rightNoWidth\"><select $readonly $opgnr_error name=\"opg_id\" class=\"printSelect2\">";
								for ($x=0;$x<count($opgave_id);$x++) {
									if ($opg_id==$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>"; 
								}
								if (!$opg_id) print "<option value=\"0\">&nbsp;</option>";
								for ($x=0;$x<count($opgave_id);$x++) {
									if ($opg_id!=$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>"; 
								}
								if ($opg_id) print "<option opg_id=\"0\">&nbsp;</option>";
								print "</select></div>
								<div class=\"rightNoWidth\"><p>$opgnr_errortxt</p></div>
								<!--<div class=\"rightMedium\"><p id=\"message\">Ingen resultat fundet</p></div>-->
								<div class=\"clear\"></div>
							</div>";
						}
					}
					if ($loen_art=='ferie') { #20140627
						print "<div class=\"row\">
							<div class=\"leftSmall\">Fra / Til: </div>
							<div class=\"rightSmall\"><input name=\"feriefra\" id=\"feriefra\" type=\"text\" $readonly class=\"textMedium printBorderNone printBg\" $feriefra_error value=\"$feriefra\"/></div>
							<div class=\"rightSmall\"><input name=\"ferietil\" id=\"ferietil\" type=\"text\" $readonly class=\"textMedium printBorderNone printBg\" $ferietil_error value=\"$ferietil\"/></div>
							<div class=\"rightNoWidth\"><p>$feriefratil_errortxt</p></div>
							<div class=\"clear\"></div>
						</div>";
					}
					if ($afvis) {
						print "<div class=\"row\">
							<div class=\"leftSmall\">Årsag til afvisning:</div>";
							print "<div class=\"right\"><textarea class=\"printTextArea textAreaLoen autosize\" name=\"afvist_pga\" cols=\"78\" rows=\"3\">$afvist_pga</textarea></div>
							<div class=\"clear\"></div>
						</div>";
					} else { #20140627
						print "<div class=\"row\">";
							if ($loen_art=='sygdom' || $loen_art=='barn_syg' || $loen_art=='skole' || $loen_art=='plads' || $loen_art=='ferie') print "<div class=\"leftSmall\">Bemærkn.:</div>";
							else print "<div class=\"leftSmall\">Udført:</div>";
							print "<div class=\"rightXXLarge\"><textarea $readonly $loentext_error class=\"printTextArea textAreaLoen autosize\" name=\"loen_tekst\" style=\"width:560px;\" cols=\"78\" rows=\"3\">$loen_tekst</textarea></div>
							<div class=\"rightNoWidth\"><p>$loentext_errortxt</p></div>
							<div class=\"clear\"></div>
						</div>";
					}
					if ($afvist_pga && $afvist) { // Tilføjet '&& $afvist', så det kun er de afviste sedler som ser det #20161031
						print "<div class=\"row\">
							<div class=\"leftSmall\">Årsag til afvisning:</div>";
							print "<div class=\"right\"><b style=\"color: #cd3300;padding-left: 4px;\">$afvist_pga</b></div>
							<div class=\"clear\"></div>
						</div>";
					}
					// Her er det kun de nye sedler efter de er blevet afvist #20161031
					// ----------------------------------
					if ($afvist_pga && !$afvist) { 
						print "<div class=\"row\">
							<div class=\"leftSmall\"><i>Afvist pga.:</i></div>";
							print "<div class=\"right\"><i style=\"color: #cd3300;padding-left: 4px;\">$afvist_pga</i></div>
							<div class=\"clear\"></div>
						</div>";
					}
					// ----------------------------------
					print "</div><!-- end of contentA -->";

				if ($oprettet) {
						print "<table border=\"0\" cellspacing=\"0\" width=\"780\">
						<tr>
							<td><b>Oprettet:</b></td><td>d.".date("d-m-Y",$oprettet)." kl. ".date("H:i",$oprettet)."</td>
							<td><b>af:</b> $oprettet_af</td>
							<td><b>Løbenr.:&nbsp;</b>$loen_nr</td>  
							<td><b>Status:&nbsp;</b>$status</td>
							
						</tr>";
					if ($afsluttet) {
						print "<tr><td><b>Overført:</b></td><td>d.".date("d-m-Y",$afsluttet)." kl. ".date("H:i",$afsluttet)."</td>
							<td><b>af:</b> $afsluttet_af</td></tr>";
					}
					if ($godkendt && !$afvist) { #20170524 Tilføjet '&& !$afvist'
						print "<tr><td><b>Godkendt:</b></td><td>d.".date("d-m-Y",$godkendt)." kl. ".date("H:i",$godkendt)."</td>
							<td><b>af:</b> $godkendt_af</td>";
 							if ($master_nr) print "<td><b>Afr. på&nbsp; : </b>$master_nr</td>"; #20151215 
							print "</tr>";
					}
					if ($afvist) {
						print "<tr><td><b>Afvist:</b></td><td>d.".date("d-m-Y",$afvist)." kl. ".date("H:i",$afvist)."</td>
							<td><b>af:</b> $afvist_af</td></tr>";
					}
					print "</table>";
				}
			print "</div><!-- end of content -->
			<div class=\"content\">
				<table class=\"akkordTable ansatteTable\">
					<thead class=\"akkordTableBorderBottom\">
					<tr>";
						if ($loen_art=='akk_afr') print "<th class=\"alignLeft\">Dato</th>";
						print "<th class=\"alignLeft\">Nr</th>
						<th class=\"alignLeft\">Navn</th>";
						if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') print "<th>Timer</th>";
						if ($loen_art=='akk_afr'||$loen_art=='akktimer'||$loen_art=='akkord'||$loen_art=='timer') {
							print "<th>50%</th>
							<th>100%</th>";
							print "<th width=\"36\" title=\"Skur lav sats ($skur_sats1)\">S(L)</th>";
							print "<th width=\"36\" title=\"Skur høj sats ($skur_sats2)\">S(H)</th>";
							print "<th>Km</th>";
							print "<th>Sum</th>";
							if ($loen_art=='timer') print "<th>Timetillæg</th>";
							else print "<th>Akkord</th>";
						}
						if ($loen_art=='aconto') print "<th>Aconto bel&oslash;b</th>";
						elseif ($loen_art=='regulering') print "<th>Bel&oslash;b</th>";
						elseif ($loen_art!='ferie') print "<th>I Alt</th>";
						//else print "<th>I Alt</th>";
						print "<!--<th width=\"20\"></th>-->
					</tr>
					</thead>
				<tbody class=\"akkordTableBody akkordTableBorderAll\">\n";
				
				$l_timer=0;
				for($x=0;$x<=count($ansat_id);$x++) {
				if (isset($loen_timer[$x])) {
#cho "$loen_fordeling[$x] :: $fordel_timer[$x]=$loen_timer[$x]*$loen_fordeling[$x]/100<br>";
				if ($loen_fordeling[$x]) $fordel_timer[$x]=$loen_timer[$x]*$loen_fordeling[$x]/100;
					else $fordel_timer[$x]=$loen_timer[$x];
#cho "$fordel_timer[$x]<br>";
					$l_timer+=$fordel_timer[$x];
					}
#cho "$loen_timer[$x] :: $fordel_timer[$x]<br>";
				}
				$f_sum=0;
				$t_sum=0;
				if ($loen_art!='aconto' && $loen_art!='regulering') $sum=0;
				$aa=count($ansat_id);
				if ($aa<1) $aa++;
				if ($loen_art=='akk_afr' || $readonly) {
					$beskyttet="readonly=\"readonly\"";
					$aa--;
				} elseif ($loen_art=='aconto' || $loen_art=='regulering') $aa=0;
				for($x=0;$x<=$aa;$x++) {
					$aa_belob[$x]=0;
#					$loen_sum[$x]=0;
					if ($loen_art!='akk_afr') $loen_date[$x]=$loendate;
					if (!isset($ansat_id[$x])) $ansat_id[$x]=NULL;
					if (!isset($medarb_nr[$x])) $medarb_nr[$x]=NULL;
					if (!isset($medarb_navn[$x])) $medarb_navn[$x]=NULL;
					if (!isset($loen_fordeling[$x])) $loen_fordeling[$x]=NULL;
					if (!isset($loen_loen[$x])) $loen_loen[$x]=0;
					if (!isset($loen_timer[$x])) $loen_timer[$x]=0;
					if (!isset($loen_50pct[$x])) $loen_50pct[$x]=0;
					if (!isset($loen_100pct[$x])) $loen_100pct[$x]=0;
					if (!isset($loen_date[$x])) $loen_date[$x]=NULL;
					if (!isset($loen_skur1[$x])) $loen_skur1[$x]=0;
					elseif ($loen_skur1[$x]>0) $l_skur1[$x]="checked=\"checked\"";
					else $l_skur1[$x]=NULL;
					if (!isset($loen_skur2[$x])) $loen_skur2[$x]=0;
					elseif ($loen_skur2[$x]>0) $l_skur2[$x]="checked=\"checked\""; 
					else $l_skur2[$x]=NULL;
					if (!isset($loen_km[$x])) $loen_km[$x]=0;
					if (!$afsluttet && $ansat_id[$x]) {
						if ($loen_art=='sygdom') $loen_loen[$x]=$sygdom_sats;
						elseif ($loen_art=='barn_syg') $loen_loen[$x]=$sygdom_sats;	
						elseif ($loen_art=='skole') $loen_loen[$x]=$skole_sats;
						elseif ($loen_art=='plads') $loen_loen[$x]=$plads_sats;
						elseif ($loen_art=='timer') $loen_loen[$x]=$medarb_loen[$x];#+$medarb_extraloen[$x];
						else $loen_loen[$x]=$medarb_extraloen[$x];
					} 
#cho "$t_belob[$x]=$loen_loen[$x]*$loen_timer[$x]+$loen_loen[$x]*$loen_50pct[$x]/2+$loen_loen[$x]*$loen_100pct[$x]<br>";
#					$t_belob[$x]=$loen_loen[$x]*$loen_timer[$x]+$loen_loen[$x]*$loen_50pct[$x]/2+$loen_loen[$x]*$loen_100pct[$x];
					$t_belob[$x]=$loen_loen[$x]*$loen_timer[$x]+$overtid_50pct*$loen_50pct[$x]+$overtid_100pct*$loen_100pct[$x];
#cho "$aa_sum aa $aa_belob[$x] $aa_sum/$l_timer*$fordel_timer[$x]<br>";
					if ($loen_timer[$x] && $l_timer) $aa_belob[$x]=$aa_sum/$l_timer*$fordel_timer[$x];
#cho "$aa_belob[$x]=$aa_sum/$l_timer*$fordel_timer[$x]<br>";
					$loen_sum[$x]=$t_belob[$x]+$aa_belob[$x]+$loen_skur1[$x]+$loen_skur2[$x];
#cho "$loen_sum[$x]<br>";
					if ($loen_date[$x] && ($loen_km[$x] || $skur1[$x] || $skur2[$x])){ #20180117
						$t_km=0;
						$tjek=0;
						$qtxt="select * from loen where (art='akktimer' or art='akkord' or art='timer') and loendate='$loen_date[$x]' and nummer < '$loen_nr' and afvist = '' order by id";
						$q=db_select($qtxt,__FILE__ . " linje " . __LINE__); # finder hvormeget kørsel personen har haft samme dag. (incl aktuelle seddel). 
						while ($r=db_fetch_array($q)) {
							$a=explode(chr(9),$r['ansatte']);
							if (in_array($ansat_id[$x],$a)) {
								$k=explode("|",$r['korsel']);
								for ($i=0;$i<count($a);$i++) { #20150623
									if ($a[$i]==$ansat_id[$x]) {
										$t_km+=$k[0];    
									}
								}
							}
							$tjek=1;
						} # 20150617 Flytte fra over '$fratræk' længere nede da km blev forkert
						if ($t_km==$loen_km[$x]) {
							if ($km_fra<=$loen_km[$x]) {
								$fratraek[$x]=$km_fra;
							}	else $fratraek[$x]=$loen_km[$x];
						} elseif ($t_km-$loen_km[$x]<$km_fra) $fratraek[$x]=$km_fra-$t_km; # 20150928 
						if ($fratraek[$x]<0) $fratraek[$x]=0;

					}
					if ($loen_km[$x] >= $fratraek[$x]) $loen_sum[$x]+=($loen_km[$x]-$fratraek[$x])*$km_sats; 
					else $fratraek[$x]=$loen_km[$x]; # 20151009
					if ($x<=count($ansat_id)) $sum+=$loen_sum[$x];
					if ($x<=count($ansat_id)) $timersum+=$loen_timer[$x]; # 20160819
					$t_sum+=$fordel_timer[$x];
					if (!$loen_loen[$x]) $loen_loen[$x]=NULL;
					if (!$loen_timer[$x]) $loen_timer[$x]=NULL;
					if (!$loen_50pct[$x]) $loen_50pct[$x]=NULL;
					if (!$loen_100pct[$x]) $loen_100pct[$x]=NULL;
					if (!$loen_km[$x]) $loen_km[$x]=NULL;
					if (!$loen_sum[$x]) $loen_sum[$x]=NULL;
					if ($loen_fordeling[$x] && $loen_fordeling[$x]<100) $medarb_navn[$x].=" (Under oplæring)";
					
					print "<tr>\n";
						if ($loen_art=='akk_afr') print "<td title=\"Akkord seddel nr: $akkord_nr[$x]\"><input type=\"text\" $beskyttet placeholder=\"Dato\" name=\"loen_date[$x]\" class=\"medarbejdernr printBorderNone\" value=\"".dkdato($loen_date[$x])."\" style=\"width:66px;\"></td>\n";
						print "<td><input type=\"text\" $beskyttet placeholder=\"Med. nr.\" name=\"medarb_nr[$x]\" class=\"medarbejdernr printBorderNone\" value=\"$medarb_nr[$x]\" style=\"width:56px;\"></td>
						<td><input type=\"text\" $beskyttet placeholder=\"Medarbejder navn\" name=\"medarb_navn[$x]\" class=\"medarbejdernavn printBorderNone\" value=\"$medarb_navn[$x]\" style=\"width:260px\">\n";
						if ($loen_art!='ferie') print "</td>\n";
						if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') print "<td class=\"alignRight\"><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_timer[$x]\" class=\"zeroValue alignRight printBorderNone\" value=\"".str_replace(".",",",$loen_timer[$x])."\" style=\"width:33px;\"></td>\n";
						if ($loen_art=='akk_afr'||$loen_art=='akktimer'||$loen_art=='akkord'||$loen_art=='timer') {
							print "<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_50pct[$x]\" class=\"alignRight printBorderNone\" value=\"".str_replace(".",",",$loen_50pct[$x])."\" style=\"width:33px;\"></td>
							<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_100pct[$x]\" class=\"alignRight printBorderNone\" value=\"".str_replace(".",",",$loen_100pct[$x])."\" style=\"width:33px;\"></td>\n";
							if ($beskyttet || $retskur[$x]) {
								print "<td class=\"alignCenter\"><input name=\"skur1[$x]\" disabled=\"disabled\" type=\"checkbox\" $l_skur1[$x]></td><td class=\"alignCenter\"><input name=\"skur2[$x]\" disabled=\"disabled\" type=\"checkbox\" $l_skur2[$x]></td>\n";
							} else print "<td class=\"alignCenter\"><input name=\"skur1[$x]\" type=\"checkbox\" $l_skur1[$x]></td><td class=\"alignCenter\"><input name=\"skur2[$x]\" type=\"checkbox\" $l_skur2[$x]></td>\n";
							print "<td title=\"Fratrukket $fratraek[$x] kilometer\"><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_km[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($loen_km[$x],2)."\" style=\"width:33px;\"></td>
							<td><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"t_belob[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($t_belob[$x],2)."\" style=\"width:50px;\"></td>
							<td><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"aa_belob[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($aa_belob[$x],2)."\" style=\"width:50px;\"></td>\n";
						}
//						<!--						<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"fordel_belob[$x]\" class=\"alignRight\" value=\"".dkdecimal($fordel_belob[$x])."\" style=\"width:60px;\"></td>-->
						if ($loen_art=='aconto' || $loen_art=='regulering') print "<td class=\"alignRight\" ><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"dksum\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($sum,2)."\" style=\"width:70px;\">\n";
						elseif ($loen_art!='ferie') print "<td class=\"alignRight\" ><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"loen_sum[$x]\" class=\"alignRight placeholderLoen printBorderNone\" value=\"".dkdecimal($loen_sum[$x],2)."\" style=\"width:50px;\">\n";
						print "<input type=\"hidden\" name=\"loen_loen[$x]\" value=\"$loen_loen[$x]\">\n";
						if ($beskyttet || $retskur[$x]) {
								print "<input name=\"skur1[$x]\" type=\"hidden\" value=\"$l_skur1[$x]\"><input name=\"skur2[$x]\" type=\"hidden\" value=\"$l_skur2[$x]\">\n";
						}
#						<!--<td><button class=\"xmark delRow \"></button></td>-->
					print "</td></tr>\n";
					
				}
				print "</tbody>\n";
				if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') { # 20140627,20160819
					print "<tbody class=\"akkordTableBody akkordTableBorderBottom\">";
					if ($loen_art=='timer' || $loen_art=='akktimer' || $loen_art=='akkord') {$colspan1=2;$colspan2=8;} 
					elseif ($loen_art=='akk_afr') {$colspan1=3;$colspan2=9;} 
					else {$colspan1=2;$colspan2=1;} 
					print "<tr><td colspan=\"1\"><b>Sum</b></td><td class=\"alignRight\" colspan=\"$colspan1\"><b>".str_replace(".",",",$timersum)."</b></td><td class=\"alignRight\" colspan=\"$colspan2\"><b>".dkdecimal($sum,2)."</b><input type=\"hidden\" name=\"sum\" value=\"$sum\"></td>";
					print "</tbody>";
					//print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">";
				}
				#cho "update loen set sum='$sum' where id='$id'<br>";
				if (!$afsluttet || $afslut) db_modify("update loen set sum='$sum' where id='$id'",__FILE__ . " linje " . __LINE__); #20130604

#				print "<tbody class=\"akkordTableBody\">
#					<tr>
#						<td colspan=\"8\"class=\"alignRight\">Tilføj ny række&nbsp;</td>
#						<td><button class=\"cross addRow\" ></button></td>
#					</tr>
#				</tbody> -->
		print "</table>  
		</div><!-- end of content -->";
		print "<div class=\"content link\">
		 
			<!--<h3><a id=\"aTag\" href=\"javascript:toggleAndChangeText();\">Vis akkordliste &#9658;</a></h3>-->";
			if (count($ansat_id) && $listevalg && ($loen_art=='akk_afr' || $loen_art=='akkord')) {
				print "<hr><h3><a id=\"aTag\" style=\"cursor:pointer;\">Vis akkordliste &#9658;</a></h3>
				<table class=\"akkordTableListe #akkordTableListeBody akkordlisteSort loenindtastning\"  border=\"0\" style=\"#cellspacing:0px;\" id=\"toggle\">
					<thead style=\"border-bottom: 1px solid #d3d3d3;\">
						<tr>
							<th rowspan=\"2\" width=\"30\">Op</th>
							<th rowspan=\"2\" width=\"30\">Ned</th>
							<th rowspan=\"2\">Betegnelse</th>
							<th rowspan=\"2\" class=\"alignRight\">Pris op</th>
							<th rowspan=\"2\" class=\"alignRight\">Pris ned</th>
							<th rowspan=\"2\" class=\"alignRight\" width=\"50px\">Sum</th>
							<th colspan=\"2\" width=\"25px\">25%</th>
							<th colspan=\"2\" width=\"25px\">40%</th>
							<th colspan=\"2\" width=\"25px\">60%</th>
							<th colspan=\"2\" width=\"25px\">+30m</th>
							<th rowspan=\"2\" class=\"alignRight\">Beløb</th>
						</tr>
						<tr class=\"akkordListeHead2\">
							<th>Op</th>
							<th>Ned</th>
							<th>Op</th>
							<th>Ned</th>
							<th>Op</th>
							<th>Ned</th>
							<th>Op</th>
							<th>Ned</th>
							</tr>
					</thead>";
					
				print "<tbody>"; 
				$sum=vis_liste($id,$listevalg,$afsluttet,$godkendt,$telt_antal);
				print "<tr>
					<td colspan=\"13\" class=\"tableSagerBorder\"><b>Lønlinjer ialt:</b></td>
					<td colspan=\"2\" align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>						
				</tr>
			</tbody>
			</table>"; 
			}
			print "</div><!-- end of content -->";

				if ($loen_art=='akk_afr' || $loen_art=='akkord') {
					print "<div class=\"content\">				 
						<hr>
						<h3>Andet</h3>
						<table class=\"akkordTable andetTable\">
								<thead>
									<tr>
										<th width=\"40\">Stk.</th>
										<th width=\"600\">Text</th>
										<th width=\"1\">Stykpris</th>
										<!--<th width=\"80\">Procentsats</th>-->
										<th class=\"alignRight\">Beløb</th>
										<!--<th width=\"20\"></th>-->
									</tr>
								</thead>
								<tbody class=\"akkordTableBody akkordTableBorderAll\">";
								$a_sum=0;	
								for($x=0;$x<=count($a_id);$x++) {
									if (!isset($a_stk[$x])) $a_stk[$x]=NULL;
									if (!isset($a_pris[$x])) $a_pris[$x]=NULL;
									if (!isset($a_txt[$x])) $a_txt[$x]=NULL;
#									if (!isset($a_pct[$x])) $a_pct[$x]=NULL;
									$a_linjesum[$x]=$a_stk[$x]*$a_pris[$x];
									$a_sum+=$a_linjesum[$x];
									print "<tr>
										<td><input type=\"text\" $readonly style=\"width:36px; text-align: right;\" class=\"printBorderNone\" name=\"a_stk[$x]\" value=\"".str_replace(".",",",$a_stk[$x])."\"></td>
										<td><input type=\"text\" $readonly style=\"width:596px; text-align: left;\" class=\"printBorderNone\" name=\"a_txt[$x]\" value=\"$a_txt[$x]\"></td>
										<td><input type=\"text\" $readonly style=\"width:76px; text-align: right;\" class=\"printBorderNone\" placeholder=\"0,00\" name=\"a_pris[$x]\" value=\"".dkdecimal($a_pris[$x],2)."\"></td>
										<!--<td><input type=\"text\" $readonly style=\"width:76px; text-align: right;\" placeholder=\"100%\" name=\"a_pct[$x]\" value=\"".str_replace(".",",",$a_pct[$x])."\"></td>-->
										<td class=\"alignRight\">".dkdecimal($a_linjesum[$x],2)."<input type=\"hidden\" name=\"a_id[$x]\" value=\"$a_id[$x]\"></td>
										<!--<td><button class=\"xmark delRow2\"></button></td>-->
									</tr>";
								}
								print "</tbody>
								<tbody class=\"akkordTableBody2 akkordTableBorderBottomAll\">

									<tr>
										<td colspan=\"3\"><b>Andet Ialt:</b></td>
										<td colspan=\"1\" class=\"alignRight\"><b>".dkdecimal($a_sum,2)."</b></td>
										
									</tr>
									<tr>
										<td colspan=\"3\"><b>Akkord Ialt:</b></td>
										<td colspan=\"1\" class=\"alignRight\"><b>".dkdecimal($sum,2)."</b></td>
									</tr>
									<tr>
										<td colspan=\"3\"><b>Til fordeling:</b></td>
										<td colspan=\"1\" class=\"alignRight\" style=\"#border-bottom: 3px double #444;\"><b>".dkdecimal($a_sum+$sum,2)."</b></td> 
								</tr>
								</tbody>
						</table>
					</div><!-- end of content -->";
					}
					print "<div class=\"content printDisplayNone\">
						<hr>";
					print "<div class=\"contentA\">";
						if (!$afsluttet) { # 20140627
							print "<input name=\"gem\" type=\"submit\" class=\"button gray medium\" value=\"Gem\" >";
							if (!$sum && !$a_sum && $id) print "<input name=\"slet\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Slet\" onclick=\"return confirm('Bekræft sletning')\">"; // Indsat $id, så slet først kommer frem efter der er trykket gem
							print "<input name=\"luk\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Luk\">";
							//if ((($loen_art && $loen_art!='akktimer') || $opg_id) && $sum) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='akktimer' || $loen_art=='akk_afr' || $loen_art=='akkord') && $sum && $loendato && $loen_tekst && ($opg_nr || ($sag_id && !$opgave_id)) && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='timer' || $loen_art=='aconto' || $loen_art=='regulering') && $sum && $loendato && $loen_tekst && (!$sag_id || $opg_nr || ($sag_id && !$opgave_id)) && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='plads' || $loen_art=='sygdom' || $loen_art=='barn_syg' || $loen_art=='skole') && $sum && $loendato && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if ($loen_art=='ferie' && $feriefra && $ferietil && $loendato && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if ($afs) { #20160830
								print "<input type=\"hidden\" name=\"fejltxt\" value=\"$fejltxt\">\n";
								print "<input type=\"hidden\" name=\"listevalg_ny\" value=\"$listevalg_ny\">\n";
								print "<input type=\"hidden\" name=\"afs\" value=\"$afs\">\n";
								print "<input type=\"hidden\" name=\"afslut\" value=\"1\">\n";
								print "<SCRIPT LANGUAGE=\"JavaScript\">document.forms[0].submit();</SCRIPT>";
							} elseif ($gem && $gemt<2) {
								print "<input type=\"hidden\" name=\"fejltxt\" value=\"$fejltxt\">\n";
								print "<input type=\"hidden\" name=\"listevalg_ny\" value=\"$listevalg_ny\">\n";
								print "<input type=\"hidden\" name=\"gem\" value=\"$gem\">\n";
								print "<input type=\"hidden\" name=\"gemt\" value=\"$gemt\">\n";
								print "<SCRIPT LANGUAGE=\"JavaScript\">document.forms[0].submit();</SCRIPT>";
							} elseif($_POST['fejltxt']) print tekstboks($_POST['fejltxt']);
						}
						if (substr($sag_rettigheder,6,1) && $afsluttet && !$godkendt && !$afvist) {
							print "<input name=\"godkend\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Godkend\" onclick=\"return confirm('Bekræft godkendelse')\">";
							print "<input name=\"afvis\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Afvis\" onclick=\"return confirm('Bekræft afvisning')\">";
						}
						if (substr($sag_rettigheder,6,1) && $godkendt && !$afregnet && !$afvist) { #20170524 Tilføjet '&& !$afvist' 
							print "<input name=\"afvis\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Afvis\" onclick=\"return confirm('Vil du afvise denne godkendte seddel???.')\">";
#							print "<input name=\"tilbagefoer\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Tilbagef&oslash;r\" onclick=\"return confirm('Vil du tilbageføre denne seddel?')\">";						
						}
						print "</div></div>
					</form>";	
				if ($afvis && !$afvist_pga) {
					$txt="Skriv årsag til afvisning og klik afvis igen!";
					print "<BODY onLoad=\"javascript:alert('$txt')\">";
				}	
	print "</div><!-- end of printableArea -->";
} # endfunc loen

function vis_liste($id,$listevalg,$afsluttet,$godkendt,$telt_antal) {
	
	global $bgcolor;

	$bgcolor2="#ffffff";
	$linjebg=$bgcolor2;

	$x=0;
#	$q=db_select("select id,kodenr,art,box1 from grupper where art ='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
#	while ($r=db_fetch_array($q)) {
#		$cat_id[$x]=$r['kodenr'];
#		$cat_navn[$x]=$r['box1'];
#		$x++;
#	}
	$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$x]=$r['kodenr'];
		$cat_navn[$x]=$r['beskrivelse'];
		$cat_nr[$x]='';
		for ($i=0;$i<strlen($cat_navn[$x]);$i++) {
			if (is_numeric(substr($cat_navn[$x],$i,1))) $cat_nr[$x].=substr($cat_navn[$x],$i,1);
			else break 1;
		}
		$x++;
	}

	$q=db_select("select id,kodenr from grupper where art ='V_CAT' and lower(box1)='transport'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$transport_id=$r['kodenr'];
	}

	$x=0;
	$y=0;
	$l_vare_id=array();
#cho "SELECT * FROM loen_enheder WHERE loen_id = '$id' order by tekst<br>";
	$q = db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id' ORDER BY varenr,tekst",__FILE__ . " linje " . __LINE__); // Har fjernet 'order by tekst' og ændret til 'order by vare_id'. Vil gerne have den samme rækkefølge som original liste | tilbageført 20140326 | tilføjet varenr 20140328
	while ($r = db_fetch_array($q)) {
		if ($r['vare_id']>0) {
			$l_id[$x]=$r['id'];
			$l_vare_id[$x]=$r['vare_id']*1;
			$l_vare_nr[$x]=$r['varenr']*1;
			$l_op[$x]=$r['op']*1;
			$l_ned[$x]=$r['ned']*1;
			$l_op_25[$x]=$r['op_25']*1;
			$l_ned_25[$x]=$r['ned_25']*1;
			$l_op_40[$x]=$r['op_40']*1;
			$l_ned_40[$x]=$r['ned_40']*1;
			$l_op_60[$x]=$r['op_60']*1;
			$l_ned_60[$x]=$r['ned_60']*1;
			$l_op_30m[$x]=$r['op_30m']*1;
			$l_ned_30m[$x]=$r['ned_30m']*1;
			$l_pris_op[$x]=$r['pris_op']*1;
			$l_pris_ned[$x]=$r['pris_ned']*1;
			list($l_liste[$x],$l_tekst[$x])=explode("|",$r['tekst']);
#			if (!$afsluttet) {
				$r2=db_fetch_array(db_select("SELECT beskrivelse,gruppe FROM varer WHERE id = '$l_vare_id[$x]'",__FILE__ . " linje " . __LINE__));
				if($r2['gruppe']) $l_liste[$x]=$r2['gruppe'];
				if($r2['beskrivelse']) $l_tekst[$x]=$r2['beskrivelse'];
				for ($c=0;$c<count($cat_id);$c++) {
					if ($l_liste[$x]==$cat_id[$c]) {
						$tmp=$cat_navn[$c]." - ";
						$l_tekst[$x]=str_replace($tmp,"",$l_tekst[$x]);
						$l_tekst[$x]="$cat_navn[$c] - $l_tekst[$x]";
					} 
				}	
#			}
			$x++;
		}	elseif($r['vare_id']=='-1') {
			$tr_id[$y]=$r['id'];
			$tr_antal[$y]=$r['op']*1;
			$tr_pris[$y]=$r['pris_op']*1;
			list($tr_liste[$y],$tr_tekst[$y])=explode("|",$r['tekst']);
			for ($c=0;$c<count($cat_id);$c++) {
				if ($tr_liste[$y]==$cat_id[$c]) {
					$tr_navn[$y]=$cat_navn[$c];
				} 
			}
			$tr_sum=$tr_antal[$y]*$tr_pris[$y];
			$y++;
		} elseif($r['vare_id']=='-2') {
			$telt_id=$r['id'];
			$telt_antal=$r['op']*1;
			$telt_pris=$r['pris_op']*1;
			for ($c=0;$c<count($cat_id);$c++) {
				if ($cat_id[$c]==7) {
					$telt_navn=$cat_navn[$c];
				} 
			}
			#			list($telt_pct,$telt_tekst)=explode("|",$r['tekst']);
			$z++;
		}
		#		if (is_numeric($l_liste[$x])) {
#			for ($y=0;$y<count($cat_id);$y++){
#				if($cat_id[$y]==$l_liste[$x]) $l_liste[$x]=$cat_navn[$y];
#			}
#		}
	}
	if (!$afsluttet) {
		$x=0;
		$tr_sum=0;
		$tmp=array();
#cho "SELECT * FROM varer WHERE id > '0' AND gruppe = '$listevalg' ORDER BY varenr ASC<br>";
		#		$q = db_select("SELECT * FROM varer WHERE id > '0' AND kategori LIKE '%$listevalg%' ORDER BY varenr ASC",__FILE__ . " linje " . __LINE__);
		$q = db_select("SELECT * FROM varer WHERE id > '0' AND gruppe = '$listevalg' ORDER BY varenr ASC",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$tmp=explode(chr(9),$r['kategori']);
	#			if (in_array($listevalg,$tmp)) {
				$vare_id[$x]=$r['id'];
				$vare_nr[$x]=$r['varenr'];
				$vare_beskrivelse[$x]=$r['beskrivelse'];
				$kategori[$x]=$r['kategori'];
				$montagepris[$x]=$r['montage'];
				$demontagepris[$x]=$r['demontage']; 
				$op[$x]=NULL;		
				$ned[$x]=NULL;		
				$enhed_id[$x]=NULL;		
				if (in_array($vare_id[$x],$l_vare_id)) {
					for ($y=0;$y<count($l_vare_id);$y++) {
						if ($vare_id[$x]==$l_vare_id[$y]){
							if (in_array($transport_id,$tmp)){
							$tr_sum+=$l_op[$y]*$montagepris[$x]*0.07;
								$tr_sum+=$l_ned[$y]*$demontagepris[$x]*0.14;
							}
							$enhed_id[$x]=$l_id[$y];
							$op[$x]=$l_op[$y];
							$ned[$x]=$l_ned[$y];
							$op_25[$x]=$l_op_25[$y];
							$ned_25[$x]=$l_ned_25[$y];
							$op_40[$x]=$l_op_40[$y];
							$ned_40[$x]=$l_ned_40[$y];
							$op_60[$x]=$l_op_60[$y];
							$ned_60[$x]=$l_ned_60[$y];
							$op_30m[$x]=$l_op_30m[$y];
							$ned_30m[$x]=$l_ned_30m[$y];
						}
					}	
				}
				$x++;
#			}
		}
		if ($afsluttet || $godkendt) {
			$readonly="readonly=\"readonly\"";
			$vis_alle=0;
		} else {
			$readonly=NULL;
			$vis_alle=1;
		}
	} else { #if ($afsluttet)
		$readonly="readonly=\"readonly\"";
		$vis_alle=0;
		for ($y=0;$y<count($l_vare_id);$y++) {
			$vare_id[$y]=$l_vare_id[$y];
			$montagepris[$y]=$l_pris_op[$y];
			$demontagepris[$y]=$l_pris_ned[$y];
			$op[$y]=$l_op[$y];
			$ned[$y]=$l_ned[$y];
			$op_25[$y]=$l_op_25[$y];
			$ned_25[$y]=$l_ned_25[$y];
			$op_40[$y]=$l_op_40[$y];
			$ned_40[$y]=$l_ned_40[$y];
			$op_60[$y]=$l_op_60[$y];
			$ned_60[$y]=$l_ned_60[$y];
			$op_30m[$y]=$l_op_30m[$y];
			$ned_30m[$y]=$l_ned_30m[$y];
			$vare_beskrivelse[$y]="$l_tekst[$y]";
			list($kategori[$y])=explode(".",$vare_beskrivelse[$y],2); #Skal bruges til at finde kat 03. telt
			$kategori[$y]*=1;
#cho "Besk $kategori[$y] $l_liste[$y]<br>";
		}
	}
	$sum=0;
	$z=0;
	$vist=array();
	$telt_tillaeg=0;
	$teltsum=0;
	for ($x=0;$x<count($vare_id);$x++) {
		$op[$x]*=1;$ned[$x]*=1;
		if ($vis_alle || $op[$x] || $ned[$x]) {
			$linjesum1[$x]=$op[$x]*$montagepris[$x];
			$linjesum1[$x]+=$ned[$x]*$demontagepris[$x];
			$linjesum2[$x]=$linjesum1[$x]+$op_25[$x]*$montagepris[$x]*0.25+$op_40[$x]*$montagepris[$x]*0.4+$op_60[$x]*$montagepris[$x]*0.6+$op_30m[$x]*$montagepris[$x]*0.06;
			$linjesum2[$x]+=$ned_25[$x]*$demontagepris[$x]*0.25+$ned_40[$x]*$demontagepris[$x]*0.4+$ned_60[$x]*$demontagepris[$x]*0.6+$ned_30m[$x]*$demontagepris[$x]*0.06;
			if ($l_liste[$x]==7) {
				$teltsum+=$linjesum1[$x];
			}
			$sum1+=$linjesum1[$x];
			$sum+=$linjesum2[$x];
			($linjebg==$bgcolor)?$linjebg=$bgcolor2:$linjebg=$bgcolor;
			($op[$x])?$op[$x]=str_replace(".",",",$op[$x]):$op[$x]=NULL;
			($ned[$x])?$ned[$x]=str_replace(".",",",$ned[$x]):$ned[$x]=NULL;
			($op_25[$x])?$op_25[$x]=str_replace(".",",",$op_25[$x]):$op_25[$x]=NULL;
			($ned_25[$x])?$ned_25[$x]=str_replace(".",",",$ned_25[$x]):$ned_25[$x]=NULL;
			($op_40[$x])?$op_40[$x]=str_replace(".",",",$op_40[$x]):$op_40[$x]=NULL;
			($ned_40[$x])?$ned_40[$x]=str_replace(".",",",$ned_40[$x]):$ned_40[$x]=NULL;
			($op_60[$x])?$op_60[$x]=str_replace(".",",",$op_60[$x]):$op_60[$x]=NULL;
			($ned_60[$x])?$ned_60[$x]=str_replace(".",",",$ned_60[$x]):$ned_60[$x]=NULL;
			($op_30m[$x])?$op_30m[$x]=str_replace(".",",",$op_30m[$x]):$op_30m[$x]=NULL;
			($ned_30m[$x])?$ned_30m[$x]=str_replace(".",",",$ned_30m[$x]):$ned_30m[$x]=NULL;
				print "<tr id=\"$vare_id[$x]\" bgcolor=\"$linjebg\" style=\"border-right: 1px solid #d3d3d3;border-left: 1px solid #d3d3d3;\">
				<td class=\"printBorderRight\"><input type=\"text\" $readonly style=\"width:30px; text-align:right;\" class=\"printBorderNone\" name=\"op[$z]\" value=\"$op[$x]\"></td>
				<td class=\"printBorderRight\"><input type=\"text\" $readonly style=\"width:30px; text-align:right;\" class=\"printBorderNone printBorderRight\" name=\"ned[$z]\" value=\"$ned[$x]\"></td>
				<td style=\"padding-left: 5px;\">$vare_beskrivelse[$x]</td>
				<td class=\"alignRight\" width=\"80\">".dkdecimal($montagepris[$x],2)."</td>
				<td class=\"alignRight\" width=\"80\">".dkdecimal($demontagepris[$x],2)."</td>
				<td class=\"alignRight printBorderRight\" style=\"padding: 0px 1px 0px 10px;\">".dkdecimal($linjesum1[$x],2)."</td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_25[$z]\" value=\"$op_25[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_25[$z]\" value=\"$ned_25[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_40[$z]\" value=\"$op_40[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_40[$z]\" value=\"$ned_40[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_60[$z]\" value=\"$op_60[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_60[$z]\" value=\"$ned_60[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"op_30m[$z]\" value=\"$op_30m[$x]\"></td>
				<td class=\"printBorderRight\" align=\"center\"><input type=\"text\" $readonly placeholder=\"\" style=\"width:25px; text-align:right;\" class=\"printBorderNone\" name=\"ned_30m[$z]\" value=\"$ned_30m[$x]\"></td>
				<td class=\"alignRight\" style=\"padding-right: 1px;\">".dkdecimal($linjesum2[$x],2)."";
			
			print "<input type=\"hidden\" name=\"vare_id[$z]\" value=\"$vare_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"varenr[$z]\" value=\"$vare_nr[$x]\">\n"; // indsat 20142803
			print "<input type=\"hidden\" name=\"enhed_id[$z]\" value=\"$enhed_id[$x]\">\n";
			print "<input type=\"hidden\" name=\"pris_op[$z]\" value=\"$montagepris[$x]\">\n";
			print "<input type=\"hidden\" name=\"pris_ned[$z]\" value=\"$demontagepris[$x]\">\n";
			print "<input type=\"hidden\" name=\"vare_tekst[$z]\" value=\"$listevalg|$vare_beskrivelse[$x]\">\n";
			print "</td></tr>\n";
			$z++;
			if ($afsluttet && $l_liste[$x]!=$l_liste[$x+1]) {
				for ($tr=0;$tr<count($tr_id);$tr++) {
					if ($tr_liste[$tr]==$l_liste[$x]) {
						print "<tr class=\"akkordListeTrans\"><td colspan=\"1\" style=\"text-align:right;\">$tr_antal[$tr]</td><td></td><td style=\"padding-left: 5px;\" colspan=\"12\">$tr_navn[$tr] Transport</td><td style=\"text-align:right;\">".dkdecimal($tr_antal[$tr]*$tr_pris[$tr],2)."</td></tr>";
						$sum+=$tr_antal[$tr]*$tr_pris[$tr];
					}
				}
#cho "$telt_antal*$sum1<br>";				
				if ($l_liste[$x]=='7') {
					print "<tr class=\"akkordListeTrans\">
						<td colspan=\"1\" style=\"text-align:right;\">1</td>
						<td></td>
						<td style=\"padding-left: 5px;\" colspan=\"12\">$telt_navn - Telt tillæg ".($telt_antal*100)."%</td>
						<td align=\"right\">".dkdecimal($telt_antal*$teltsum,2)."</td> 
					</tr>"; #20140810 ændret telt_pris til sum1 også 2 linjer herunder
					$telt_tillaeg=1;
					$sum+=$telt_antal*$teltsum;
				}
			}
		}
	}
	if (!$afsluttet) {
		print "<tr>
			<td colspan=\"2\" class=\"tableSagerBorder\"></td><td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Sum</b></td><td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum1,2)."</b></td>
			<td colspan=\"9\" align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>
		</tr>";
		$trans_antal=0;
		$trans_id=0;
	}
	if ($tr_sum && !$afsluttet){
		for ($tr=0;$tr<count($tr_id);$tr++) {
			if ($tr_liste[$tr]==$listevalg) {
				$trans_antal=$tr_antal[$tr];	
				$trans_id=$tr_id[$tr];
				$sum+=$tr_antal[$tr]*$tr_sum;
			}
		}
		print "<tr>
			<td colspan=\"2\" class=\"tableSagerBorder\">
				<input type=\"text\" $readonly style=\"width:25px; text-align:right;\" name=\"tr_antal\" value=\"$trans_antal\" class=\"printBorderNone\">
				<input type=\"hidden\" $readonly name=\"tr_id\" value=\"$trans_id\">
				<input type=\"hidden\" $readonly name=\"tr_pris\" value=\"$tr_sum\">
			</td>
			<td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Transport</b></td><td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($trans_antal*$tr_sum,2)."</b></td>
			<td colspan=\"9\" class=\"tableSagerBorder\">&nbsp;</td>
		</tr>
		<tr>
		<td colspan=\"2\"></td><td colspan=\"3\" style=\"padding-left: 5px;\"><b>Sum incl. transport</b></td>
		<td colspan=\"10\" align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>
		</tr>";
	} 
	if ($listevalg=='7' && !$telt_tillaeg) {
		$telt_tillaeg=1;
		$telt35=NULL;$telt40=NULL;$telt60=NULL;#$telt55=NULL;
		if (!$telt_antal) $telt_antal='0.35';
		if ($telt_antal=='0.35') $telt35='checked';
		if ($telt_antal=='0.4') $telt40='checked';
		//if ($telt_antal=='0.55') $telt55='checked';
		if ($telt_antal=='0.6') $telt60='checked';
		//else $telt35='checked';
		$sum+=$telt_antal*$sum1;
		#cho "telt35 $telt35<br>telt40 $telt40<br>telt55 $telt55<br>telt60 $telt60<br>";
#cho "<tr><td colspan='5'>update loen_enheder set pris_op=$sum1 WHERE loen_id = '$id' and vare_id='-2</td></tr>";
		#20140810 indsat linje herunder.
#		db_modify("update loen_enheder set pris_op=$sum1 WHERE loen_id = '$id' and vare_id='-2'",__FILE__ . " linje " . __LINE__);
		print "<tr>
			<td colspan=\"2\" class=\"tableSagerBorder\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.35\" $telt35></td>
			<td colspan=\"3\" class=\"tableSagerBorder\" style=\"padding-left: 5px;\"><b>Telt tillæg 35%</b></td><td align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\">";
			if ($telt35) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
			print "</td><td colspan=\"9\" class=\"tableSagerBorder\">&nbsp;</td>
		</tr>
		<tr bgcolor=\"$bgcolor\">
			<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.4\" $telt40></td>
			<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 40%</b></td><td align=\"right\">";
			if ($telt40) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
			print "</td><td colspan=\"9\">&nbsp;</td>
		</tr>";
		/*
		<tr>
			<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.55\" $telt55></td>
			<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 55%</b></td><td align=\"right\">";
			if ($telt55) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
			print "</td><td colspan=\"9\">&nbsp;</td>
		</tr>
		*/
		print "
		<tr>
			<td colspan=\"2\" align=\"center\"><input type=\"radio\" $readonly name=\"telt_antal\" value=\"0.6\" $telt60></td>
			<td colspan=\"3\" style=\"padding-left: 5px;\"><b>Telt tillæg 60%</b></td><td align=\"right\">";
			if ($telt60) print "<b>".dkdecimal($telt_antal*$sum1)."</b>";
			print "</td><td colspan=\"9\">";
			print "<input type=\"hidden\" $readonly name=\"telt_id\" value=\"$telt_id\">
				<input type=\"hidden\" $readonly name=\"telt_pris\" value=\"$sum1\">";
				if ($gemt>=1) print "<input type=\"hidden\" $readonly name=\"telt_antal\" value=\"$telt_antal\"> <!--#20151116-->"; #20170920
				print "
			</td>
		</tr>
		<tr>
		<td colspan=\"2\"></td><td colspan=\"3\" style=\"padding-left: 5px;\"><b>Sum incl. telt tillæg</b></td>
		<td colspan=\"10\" align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>
		</tr>";

	}
	if (!$afsluttet) {
#	} else {
	$c_sum=0;
	for ($x=0;$x<count($l_vare_id);$x++) {
		if (is_numeric($l_liste[$x]) && !in_array($l_vare_id[$x],$vare_id)){
			for ($c=0;$c<count($cat_id);$c++) {
				if ($l_liste[$x]==$cat_id[$c]) {
					$c_navn=$cat_navn[$c];
					$c_id=$cat_id[$c];
				} 
			}	
				$l_linjesum[$x]=$l_op[$x]*$l_pris_op[$x]+$l_op_25[$x]*$l_pris_op[$x]*0.25+$l_op_40[$x]*$l_pris_op[$x]*0.4+$l_op_60[$x]*$l_pris_op[$x]*0.6;
				$l_linjesum[$x]+=$l_ned[$x]*$l_pris_ned[$x]+$l_ned_25[$x]*$l_pris_ned[$x]*0.25+$l_ned_40[$x]*$l_pris_ned[$x]*0.4+$l_ned_60[$x]*$l_pris_ned[$x]*0.6;
				$sum+=$l_linjesum[$x];
				$c_sum+=$l_linjesum[$x];
				#cho "Sum $sum<br>";
				($l_op[$x])?$l_op[$x]=str_replace(".",",",$l_op[$x]):$l_op[$x]=NULL;
				($l_ned[$x])?$l_ned[$x]=str_replace(".",",",$l_ned[$x]):$l_ned[$x]=NULL;
				print "<input type=\"hidden\" name=\"vare_id[$z]\" value=\"$l_vare_id[$x]\">";
				print "<input type=\"hidden\" name=\"varenr[$z]\" value=\"$l_vare_nr[$x]\">"; // indsat 20142803
				print "<input type=\"hidden\" name=\"enhed_id[$z]\" value=\"$l_id[$x]\">";
				print "<input type=\"hidden\" name=\"pris_op[$z]\" value=\"$l_pris_op[$x]\">";
				print "<input type=\"hidden\" name=\"pris_ned[$z]\" value=\"$l_pris_ned[$x]\">";
				print "<input type=\"hidden\" name=\"vare_tekst[$z]\" value=\"$l_tekst[$x]\">";
				print "<input type=\"hidden\" name=\"op[$z]\" value=\"$l_op[$x]\">";
				print "<input type=\"hidden\" name=\"ned[$z]\" value=\"$l_ned[$x]\">";
				print "<input type=\"hidden\" name=\"op_25[$z]\" value=\"$l_op_25[$x]\">";
				print "<input type=\"hidden\" name=\"ned_25[$z]\" value=\"$l_ned_25[$x]\">";
				print "<input type=\"hidden\" name=\"op_40[$z]\" value=\"$l_op_40[$x]\">";
				print "<input type=\"hidden\" name=\"ned_40[$z]\" value=\"$l_ned_40[$x]\">";
				print "<input type=\"hidden\" name=\"op_60[$z]\" value=\"$l_op_60[$x]\">";
				print "<input type=\"hidden\" name=\"ned_60[$z]\" value=\"$l_ned_60[$x]\">";
				if ($l_op[$x] || $l_ned[$x]) {
					($linjebg==$bgcolor)?$linjebg=$bgcolor2:$linjebg=$bgcolor;
					print "<tr id=\"$vare_id[$x]\" bgcolor=\"$linjebg\" style=\"border: 1px solid #d3d3d3;\">
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned[$x]</td>
					<td style=\"padding-left: 5px;\">$l_tekst[$x]</td>
					<td class=\"alignRight\" width=\"80\">".dkdecimal($l_pris_op[$x],2)."</td>
					<td class=\"alignRight\" width=\"80\">".dkdecimal($l_pris_ned[$x],2)."</td>
				<td class=\"alignRight\" style=\"border-right: 1px solid #d3d3d3;text-align:right;padding: 0px 1px 0px 10px;\">".dkdecimal($l_linjesum[$x],2)."</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_25[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_25[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_40[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_40[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_60[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_60[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_op_30m[$x]</td>
					<td style=\"border-right: 1px solid #d3d3d3;text-align:right;padding-right: 1px;\">$l_ned_30m[$x]</td>
				<td class=\"alignRight\" style=\"padding-right: 1px;\">".dkdecimal($l_linjesum[$x],2)."</td>";
				print "</tr>";
			} else {
				print "<input type=\"hidden\" name=\"op[$z]\" value=\"0\">";
				print "<input type=\"hidden\" name=\"ned[$z]\" value=\"0\">";
			}
			$z++;
#		}
		}
		if ($l_liste[$x]!=$l_liste[$x+1]) {
			if ($c_id=='7' && !$telt_tillaeg) { # 20140810 indsat $telt_tillag for at undgå dobbentprint. 
				$telt_tillaeg=1;
				if (!$telt_antal)$telt_antal='0.4';
				if (!$telt_pris)$telt_pris=$c_sum;
				print "<tr>
					<td colspan=\"1\" style=\"#border-style:solid;#border-width:1px;text-align:right;\">1</td>
					<td></td>
					<td colspan=\"3\" style=\"padding-left: 5px;\"><b>$c_navn - Telt tillæg ".($telt_antal*100)."%</b></td>
					<td align=\"right\"><b>".dkdecimal($telt_antal*$telt_pris,2)."</b></td>
				</tr>";
				$c_sum+=$telt_antal*$telt_pris;
				$sum+=$telt_antal*$telt_pris;
			}
			for ($tr=0;$tr<count($tr_id);$tr++) {
			if ($tr_liste[$tr]==$c_id && !in_array($c_id,$tr_tjek)) {
				$tr_tjek[count($tr_tjek)]=$c_id;
				print "<tr>
				<td colspan=\"1\" style=\"#border-style:solid;#border-width:1px;text-align:right;padding-right: 1px;\">$tr_antal[$tr]</td>
				<td></td>
				<td colspan=\"3\" style=\"padding-left: 5px;\"><b>$c_navn - Transport</b></td>
				<td align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($tr_antal[$tr]*$tr_pris[$tr])."</b></td>
				</tr>";
				$c_sum+=$tr_antal[$tr]*$tr_pris[$tr];
				$sum+=$tr_antal[$tr]*$tr_pris[$tr];
			}
		}
		# 20140810 $c_id fjernet fra "print"  
		if ($c_id && $c_sum) print "<tr><td colspan=\"2\"></td><td colspan=\"12\" style=\"padding-left: 5px;\"><b>$c_navn i alt</b></td><td align=\"right\" style=\"padding-right: 1px;\"><b>".dkdecimal($c_sum)."</b></td></tr>";
		$c_sum=0;
		}
		}
	}
	return $sum;
}

function fod_log($string)
{
/*
	file_put_contents('/home/mols/logs/fodlog', $string, FILE_APPEND);
*/
}

function fod_laeg_steder_sammen($obj)
{
	$tmpudfaddrs = array_unique($obj->udfaddrs);
	$tmpudftitles = array_unique($obj->udftitles);

	$obj->udfaddr = '';
	foreach($tmpudfaddrs as $tmpudfaddr)
	{
		if ($obj->udfaddr)
			$obj->udfaddr .= ", ";

		$obj->udfaddr .= $tmpudfaddr;
	}

	$obj->udftitle = '';
	foreach($tmpudftitles as $tmpudftitle)
	{
		if ($obj->udftitle)
			$obj->udftitle .= ",\n";

		$obj->udftitle .= $tmpudftitle;
	}
}

function fod_dkdecimal($tal, $visallefelter)
{
	return dkdecimal($tal || $visallefelter ? $tal : "", 2);
}

function fod_felter_laeg_til($destobj, $srcobj)
{
	$destobj->akk_loen += $srcobj->akk_loen;
	$destobj->akktimer += $srcobj->akktimer;
	$destobj->ansat_sum += $srcobj->ansat_sum;
	$destobj->antal_100pct += $srcobj->antal_100pct;
	$destobj->antal_50pct += $srcobj->antal_50pct;
	$destobj->antal_barn_syg += $srcobj->antal_barn_syg;
	$destobj->antal_plads += $srcobj->antal_plads;
	$destobj->antal_skole += $srcobj->antal_skole;
	$destobj->antal_sygdom += $srcobj->antal_sygdom;
	$destobj->barn_syg += $srcobj->barn_syg;
	$destobj->dt_timer += $srcobj->dt_timer;
	$destobj->korsel_km += $srcobj->korsel_km;
	$destobj->korsel_kr += $srcobj->korsel_kr;
	$destobj->loen_100pct += $srcobj->loen_100pct;
	$destobj->loen_50pct += $srcobj->loen_50pct;
	$destobj->loen_dyrtid += $srcobj->loen_dyrtid;
	$destobj->loen_timeant += $srcobj->loen_timeant;
	$destobj->loen_timer += $srcobj->loen_timer;
	$destobj->plads += $srcobj->plads;
	$destobj->skole += $srcobj->skole;
	$destobj->skur1 += $srcobj->skur1;
	$destobj->skur2 += $srcobj->skur2;
	$destobj->sygdom += $srcobj->sygdom;
	$destobj->timeant50 += $srcobj->timeant50;
	$destobj->trainee += $srcobj->trainee;
	$destobj->tr_akktimer += $srcobj->tr_akktimer;
}

function fod_felter_nulstil($obj)
{
	$obj->akk_loen = 0;
	$obj->akktimer = 0;
	$obj->ansat_sum = 0;
	$obj->antal_100pct = 0;
	$obj->antal_50pct = 0;
	$obj->antal_barn_syg = 0;
	$obj->antal_plads = 0;
	$obj->antal_skole = 0;
	$obj->antal_sygdom = 0;
	$obj->barn_syg = 0;
	$obj->dt_timer = 0;
	$obj->korsel_km = 0;
	$obj->korsel_kr = 0;
	$obj->loen_100pct = 0;
	$obj->loen_50pct = 0;
	$obj->loen_dyrtid = 0;
	$obj->loen_timeant = 0;
	$obj->loen_timer = 0;
	$obj->plads = 0;
	$obj->skole = 0;
	$obj->skur1 = 0;
	$obj->skur2 = 0;
	$obj->sygdom = 0;
	$obj->timeant50 = 0;
	$obj->trainee = 0;
	$obj->tr_akktimer = 0;
}

function fod_felter_vis_ansat($fodansat, $periode, $vis)
{
	print "<td class=\"tableSagerEllipsis\" style=\"max-width:190px;\"><a href=\"loen.php?funktion=loenafregning&amp;ansat_id=$fodansat->id&amp;periode=$periode&amp;vis=$vis\" title=\"$fodansat->navn\">".str_replace(" ","&nbsp;",$fodansat->navn)."&nbsp;</a></td>\n";
}

function fod_felter_vis_dato($dato)
{
	print "<td style=\"white-space:nowrap;\">".dkdato($dato)."&nbsp;</td>";
}

function fod_felter_vis_sted($obj, $bold)
{
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	$udfaddr = isset($obj->udfaddr)?$obj->udfaddr:null;
	$udftitle = isset($obj->udftitle)?$obj->udftitle:null;

	print "<td title=\"$udftitle\">$startbold$udfaddr&nbsp;$endbold</td>";
}

function fod_felter_vis_titel($titel, $bold)
{
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	print "<td>$startbold$titel$endbold</td>";
}

function fod_felter_vis_beloeb($obj, $visallefelter, $bold)
{
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_timeant, $visallefelter)."\">$startbold".fod_dkdecimal($obj->loen_timer, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->akktimer, $visallefelter)."|".fod_dkdecimal($obj->dt_timer, $visallefelter)."\">$startbold".fod_dkdecimal($obj->loen_dyrtid, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_50pct, $visallefelter)."\">$startbold".fod_dkdecimal($obj->loen_50pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_100pct, $visallefelter)."\">$startbold".fod_dkdecimal($obj->loen_100pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Timer: ".fod_dkdecimal($obj->akktimer, $visallefelter)."\">$startbold".fod_dkdecimal($obj->akk_loen, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Km: ".fod_dkdecimal($obj->korsel_km, $visallefelter)."\">$startbold".fod_dkdecimal($obj->korsel_kr, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur1, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur2, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_plads, $visallefelter)."\">$startbold".fod_dkdecimal($obj->plads, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_sygdom, $visallefelter)."\">$startbold".fod_dkdecimal($obj->sygdom, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_barn_syg, $visallefelter)."\">$startbold".fod_dkdecimal($obj->barn_syg, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->antal_skole, $visallefelter)."\">$startbold".fod_dkdecimal($obj->skole, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->ansat_sum, $visallefelter)."&nbsp;$endbold</td>";
}

function fod_felter_vis_timer($obj, $visallefelter, $bold)
{
	$startbold = $bold?"<b>":"";
	$endbold = $bold?"</b>":"";

	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_timer, $visallefelter)."\">$startbold".fod_dkdecimal($obj->loen_timeant, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_dyrtid, $visallefelter)."\">$startbold".fod_dkdecimal($obj->dt_timer, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_50pct, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_50pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->loen_100pct, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_100pct, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Timer: ".fod_dkdecimal($obj->akktimer, $visallefelter)."\">$startbold".fod_dkdecimal($obj->akk_loen, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Km: ".fod_dkdecimal($obj->korsel_km, $visallefelter)."\">$startbold".fod_dkdecimal($obj->korsel_kr, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur1, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->skur2, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->plads, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_plads, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->sygdom, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_sygdom, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->barn_syg, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_barn_syg, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\" title=\"Sum: ".fod_dkdecimal($obj->skole, $visallefelter)."\">$startbold".fod_dkdecimal($obj->antal_skole, $visallefelter)."&nbsp;$endbold</td>";
	print "<td align=\"right\">$startbold".fod_dkdecimal($obj->ansat_sum, $visallefelter)."&nbsp;$endbold</td>";
}

function fod_indsaml_data($periode)
{
	global $overtid_50pct;
	global $overtid_100pct;

	$funcstarttime = microtime(true);

	fod_log('fod_indsaml_data:' . "\n");

	$predatoer = null;

	$alle_ansatte_id =  NULL;
	if (0 && $ansatvalg)
	{
		$qtxt = "SELECT * FROM ansatte WHERE id = '$ansatvalg'";
	}
	else
	{
		$qtxt = "SELECT * FROM ansatte ORDER BY navn";
	}

	$ansat_navn_by_id = array();

	$x = 0;
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q))
	{
		$ansatte_id[$x] = $r['id'];
		$ansat_navn_by_id[$r['id']] = $r['navn'];
		$x++;
	}

	$x = 0;
	$r = db_fetch_array(db_select("select box4 from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	$p_start[$x] = strtotime($r['box4']);
	while ($p_start[$x] <= date("U")+1209600)
	{
		$x++;
		$p_start[$x] = $p_start[$x-1]+1209600;
	}

	if (!$periode)
		$periode = $p_start[$x-2];

	$startdate = date("Y-m-d",$periode);
	$slutdate = date("Y-m-d",$periode+1209600);

	fod_log("startdate: " . $startdate . "\n");

	$x = 0;

	$tmp = $periode;
	for ($d = 0;$d<14;$d++)
	{
		$datoliste[$d] = date("Y-m-d",$tmp);
		$tmp += 86400;
	}
	$x = 0;
	$y = 0;
	$pre_d = array();
	$post_d = array();
	# 20130604 tilføjet: and afvist<'1'
	$qtxt = "SELECT * FROM loen";
	$qtxt.=" WHERE art='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";

	if (count($ansatte_id)==1)
		$qtxt.=" and ansatte like '%$ansatte_id[0]%'";

	$qtxt.=" order by loendate";
	#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q))
	{
		$ad = array();
		$datoer[$x] = $r['datoer'];
		$ad = explode(chr(9),$datoer[$x]);
		for ($d = 0;$d<count($ad);$d++)
		{
			if ($ad[$d] && $ad[$d]<$startdate && (!in_array($ad[$d],$pre_d)))
			{
				$pre_d[$x] = $ad[$d];
				$x++;
			}
		}
		for ($d = 0;$d<count($ad);$d++)
		{
			if ($ad[$d] && $ad[$d]>=$slutdate && (!in_array($ad[$d],$post_d)))
			{
				$post_d[$y] = $ad[$d];
#cho "PO $post_d[$y]<br>";
				$y++;
			}
		}
	}
	sort($pre_d);
	sort($post_d);
	for ($d = 0;$d<count($pre_d);$d++)
	{
		$predatoer.=$pre_d[$d];
		$datoliste[$d] = $pre_d[$d];
#cho "D1 $datoliste[$d]<br>";
	}
	$tmp = $periode;
	for ($d = count($pre_d);$d<14+count($pre_d);$d++)
	{
		$datoliste[$d] = date("Y-m-d",$tmp);
#cho "D2 $datoliste[$d]<br>";
		$tmp += 86400;
	}
	
	$a = count($datoliste);
	$b = count($datoliste)+count($post_d);
	for ($d = $a;$d<$b;$d++)
	{
		$postdatoer.=$post_d[$d-$a];
		$datoliste[$d] = $post_d[$d-$a];
#cho "D3 $datoliste[$d]<br>";
	}

	$foddage = array();

	$datoantal = count($datoliste);

	# 20130228 tilføjet: or art='akktimer'
	# 20130604 tilføjet: and afvist<'1'
	$x = 0;
	$qtxt = "SELECT id FROM loen";
	$qtxt.=" WHERE (art='akk_afr' or art='akktimer' or art='akkord' or art='timer' or art='plads' or art='sygdom' or art='barn_syg' or art='skole')";
	$qtxt.=" and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";

	if (count($ansatte_id)==1)
		$qtxt.=" and ansatte like '%$ansatte_id[0]%'";

	$qtxt.=" order by loendate";
	//if(something) $qtxt = "SELECT * FROM loen WHERE id='0'"

	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);

	while ($r = db_fetch_array($q))
	{
		$loen_id[$x] = $r['id'];
		$x++;
	}

	fod_log('count($loen_id): ' . count($loen_id) . "\n");

	list($l_enh_id, $l_akkord) = fod_indsaml_loenenheder_gammel();

	fod_log('count($l_akkord): ' . count($l_akkord) . "\n");

	$x = 0;
	$qtxt = "SELECT * FROM loen";
	$qtxt.=" WHERE (art='akk_afr' or art='akktimer' or art='akkord' or art='timer' or art='plads' or art='sygdom' or art='barn_syg' or art='skole')";
	$qtxt.=" and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";

	if (count($ansatte_id)==1)
		$qtxt.=" and ansatte like '%$ansatte_id[0]%'";

	$qtxt.=" order by loendate";
#	$qtxt = "SELECT * FROM loen WHERE art!='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and godkendt!='' and afregnet='' order by loendate";
#cho "$qtxt<br>";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q))
	{
		fod_log(
			'loenloop id ' . $r['id'] . "\n" . 
			'   sag_id: ' . $r['sag_id']  . "\n" .
			'   sag_nr: ' . $r['sag_nr']  . "\n" .
			'   opg_id: ' . $r['opg_id']  . "\n" .
			'   opg_nr: ' . $r['opg_nr']  . "\n" .
			'   loendate: ' . $r['loendate'] . "\n" .
			'   sum: ' . $r['sum'] . "\n" .
			'   ansatte: ' . $r['ansatte'] . "\n" .
			'   fordeling: ' . $r['fordeling'] . "\n" .
			'   kategori: ' . $r['kategori'] . "\n" .
			'   datoer: ' . $r['datoer'] . "\n");

		$ad = array();
		$ac = array();
		$af = array();
		$ans = array();
		$afregnet = $r['afregnet'];
		$afregnet_af = $r['afregnet_af'];
		$loen_id[$x] = $r['id'];
		$loen_nr[$x] = $r['nummer']*1;
#cho "No: $loen_nr[$x]<br>";
		$loen_tekst[$x] = $r['tekst'];
		$fordeling[$x] = $r['fordeling'];
		$loen_art[$x] = $r['art'];
		$hvem[$x] = $r['hvem'];
		$sag_id[$x] = $r['sag_id']*1;
		$sag_nr[$x] = $r['sag_nr']*1;
		$fod_opg_id[$x] = $r['opg_id']*1;
		$opg_nr[$x] = $r['opg_nr']*1;
		$loendato[$x] = dkdato($r['loendate']);
		($r['oprettet'])?$oprettet[$x] = $r['oprettet']:$oprettet[$x] = '';
		($r['afsluttet'])?$afsluttet[$x] = $r['afsluttet']:$afsluttet[$x] = '';
		($r['godkendt'])?$godkendt[$x] = $r['godkendt']:$godkendt[$x] = '';
		($r['afvist'])?$afvist[$x] = $r['afvist']:$afvist[$x] = '';
#cho "GS $godkendt[$x] $sag_nr[$x]<br>";
		$sum[$x] = $r['sum'];
		$ansatte[$x] = $r['ansatte'];
		$fordeling[$x] = $r['fordeling'];
		$kategori[$x] = $r['kategori'];
#		($predatoer)?$datoer[$x] = $predatoer.chr(9).$r['datoer']:$datoer[$x] = $r['datoer'];
		$datoer[$x] = $r['datoer'];
		$ans = explode(chr(9),$ansatte[$x]);
		//print_r($ans);
#cho "ansatte: $ansatte[$x]<br>";
		
		
#cho "ans $ans[0] $ansatte[$x]<br>";
		$aa = explode(chr(9),$r['loen']);
		$ad = explode(chr(9),$datoer[$x]);
#	for ($d = 0;$d<count($ad);$d++) {
#cho ", $ad[$d]";
#	}
#cho "<br>";
		$at = explode(chr(9),$r['timer']);
		$af = explode(chr(9),$fordeling[$x]);
#cho "F $fordeling[$x] $af[0] $af[1] $af[2]<br>";
		#cho "AT".$r['timer']."<br>";
		$a50=explode(chr(9),$r['t50pct']);
		$a100=explode(chr(9),$r['t100pct']);
		$loentimer[$x] = 0;
		$tr_timer[$x] = 0; #tr=trainee;
		$akkord[$x] = 0;
		for ($y = 0;$y<count($at);$y++)
		{
			if (!isset($ad[$y]))
				$ad[$y] = $r['loendate'];

			$loentimer[$x] += $at[$y];
		}
		list($km,$km_sats,$km_fra)=explode("|",$r['korsel']);
		$ak = explode(chr(9),$km);
		list($s1,$s2)=explode("|",$r['skur']);
		$as1=explode(chr(9),$s1);
		$as2=explode(chr(9),$s2);
		$timer[$x] = 0;
		$akkord[$x] = 0;
		$telt = 0;
		$teltsum = 0;

		if (isset($l_enh_id))
		{
			for ($l = 1;$l<=count($l_enh_id);$l++)
			{
				if (isset($loen_id[$x]) && isset($l_akkord[$l]) && $loen_id[$x]==$l_enh_id[$l])
					$akkord[$x] += $l_akkord[$l];
			}
		}
		$akk_timeloen[$x] = $akkord[$x]/$loentimer[$x];
		$tr_sum[$x] = 0;
		$fordelingssum[$x] = 0;
		$fordelingstimer[$x] = 0;
		for ($y = 0;$y<count($at);$y++)
		{
			if ($af[$y]!=100)
			{
				$tr_timer[$x] += $at[$y];
				$trtl = $akk_timeloen[$x]*80/100;
				$tr_sum[$x] += $trtl*$at[$y];
				$fordelingssum[$x] += $at[$y]*($akk_timeloen[$x]-$trtl);
				$fordelingstimer[$x] = $tr_timer[$x]*0.4+($loentimer[$x]-$tr_timer[$x])*0.6;
			}
		}

		for ($c = 0;$c<count($ans);$c++)
		{
			if (!$ad[$c])
				$ad[$c] = $r['loendate'];

			$tmptrainee = 0;
			$fodtmpakktimer = 0;
			$fodtmpkorsel_km = 0;

			if (!array_key_exists($ad[$c], $foddage))
			{
				$foddage[$ad[$c]] = new stdClass();
				$foddage[$ad[$c]]->ansatte = array();
			}

			$foddag = $foddage[$ad[$c]];

			if (!array_key_exists($ans[$c], $foddag->ansatte))
			{
				$foddag->ansatte[$ans[$c]] = new stdClass();
				$foddag->ansatte[$ans[$c]]->sager = array();
				$foddag->ansatte[$ans[$c]]->id = $ans[$c];
				$foddag->ansatte[$ans[$c]]->navn = $ansat_navn_by_id[$ans[$c]];
			}

			$fodansat = $foddag->ansatte[$ans[$c]];

			if (!array_key_exists($sag_id[$x], $fodansat->sager))
			{
				$fodansat->sager[$sag_id[$x]] = new stdClass();
				$fodansat->sager[$sag_id[$x]]->opgaver = array();
				$fodansat->sager[$sag_id[$x]]->sag_nr = $sag_nr[$x];
			}

			$fodsag = $fodansat->sager[$sag_id[$x]];

			if (!array_key_exists($fod_opg_id[$x], $fodsag->opgaver))
			{
				$fodsag->opgaver[$fod_opg_id[$x]] = new stdClass();
				$fodsag->opgaver[$fod_opg_id[$x]]->opg_nr = $opg_nr[$x];
				$fodsag->opgaver[$fod_opg_id[$x]]->udfaddrs = array();
				$fodsag->opgaver[$fod_opg_id[$x]]->udftitles = array();

				$fodopgave = $fodsag->opgaver[$fod_opg_id[$x]];
				fod_felter_nulstil($fodopgave);
			}

			$fodopgave = $fodsag->opgaver[$fod_opg_id[$x]];

			if ($sag_id[$x] == 0)
			{
				array_push($fodopgave->udfaddrs, $loen_art[$x]);
				array_push($fodopgave->udftitles, $loen_art[$x]);
			}

			if ($loen_art[$x]=='akktimer' || $loen_art[$x]=='akkord')
			{
				$fodopgave->loen_dyrtid += $aa[$c]*$at[$c];
				$fodopgave->dt_timer += $at[$c];
				$fodopgave->loen_50pct += $overtid_50pct*$a50[$c];
				$fodopgave->loen_100pct += $overtid_100pct*$a100[$c];
				$fodopgave->antal_50pct += $a50[$c];
				$fodopgave->antal_100pct += $a100[$c];
				$fodopgave->timeant50 += $at[$c];
			}
			if ($loen_art[$x]=='akk_afr' || $loen_art[$x]=='akkord') #20130305  Ændret elseif til if og tilføjet: "|| $loen_art[$x]=='akkord'"
			{
				$fodtmpakktimer += $at[$c];

				if($af[$c]<100)
				{
					$fodopgave->tr_akktimer += $at[$c];
					$fodopgave->trainee = 1;
					$tmptrainee = 1;
				}
			}
			elseif ($loen_art[$x]=='timer')
			{
				$fodopgave->loen_timeant += $at[$c];
				$fodopgave->loen_timer += $aa[$c]*$at[$c];
				$fodopgave->loen_50pct += $overtid_50pct*$a50[$c];
				$fodopgave->loen_100pct += $overtid_100pct*$a100[$c];
				$fodopgave->antal_50pct += $a50[$c];
				$fodopgave->antal_100pct += $a100[$c];
			}
			elseif ($loen_art[$x]=='plads')
			{
				$fodopgave->plads += $aa[$c]*$at[$c];
				$fodopgave->antal_plads += $at[$c];
			}
			elseif ($loen_art[$x]=='sygdom')
			{
				$fodopgave->sygdom += $aa[$c]*$at[$c];
				$fodopgave->antal_sygdom += $at[$c];
			}
			elseif ($loen_art[$x]=='barn_syg')
			{
				$fodopgave->barn_syg += $aa[$c]*$at[$c];
				$fodopgave->antal_barn_syg += $at[$c];
			}
			elseif ($loen_art[$x]=='skole')
			{
				$fodopgave->skole += $aa[$c]*$at[$c];
				$fodopgave->antal_skole += $at[$c];
			}
			if ($loen_art[$x]!='akk_afr')
			{
				$fodtmpkorsel_km += $ak[$c];

				$fodopgave->skur1 += $as1[$c];
				$fodopgave->skur2 += $as2[$c];
			}

			$fodopgave->akktimer += $fodtmpakktimer;

			if ($tmptrainee)
			{
				$fodopgave->akk_loen += ($akkord[$x]/$fordelingstimer[$x])*0.4*$fodtmpakktimer;
			}
			elseif ($fordelingstimer[$x])
			{
				$fodopgave->akk_loen += ($akkord[$x]/$fordelingstimer[$x])*0.6*$fodtmpakktimer;
			}
			else
			{
				$fodopgave->akk_loen += $akkord[$x]*$fodtmpakktimer/$loentimer[$x];
			}

			if ($fodtmpkorsel_km<$km_fra)
			{
				$fodtmpkorsel_km = 0;
				$fodtmpkorsel_kr = 0;
			}
			else
			{
				$fodtmpkorsel_km-=$km_fra;
				$fodtmpkorsel_kr = $fodtmpkorsel_km*$km_sats;
			}

			$fodopgave->korsel_km += $fodtmpkorsel_km;
			$fodopgave->korsel_kr += $fodtmpkorsel_kr;
		}

		$x++;
	}

	$fodtotal = new stdClass();
	fod_felter_nulstil($fodtotal);

	$fodtotal->dage = $foddage;

	foreach($foddage as $foddagid => $foddag)
	{
		fod_felter_nulstil($foddag);

		foreach($foddag->ansatte as $fodansatid => $fodansat)
		{
			fod_felter_nulstil($fodansat);

			foreach($fodansat->sager as $fodsagid => $fodsag)
			{
				fod_felter_nulstil($fodsag);
				$fodsag->udfaddrs = array();
				$fodsag->udftitles = array();

				foreach($fodsag->opgaver as $fodopgaveid => $fodopgave)
				{
					$fodopgave->ansat_sum = $fodopgave->loen_timer + $fodopgave->loen_dyrtid + $fodopgave->loen_50pct + $fodopgave->loen_100pct + $fodopgave->akk_loen + $fodopgave->skur1 + $fodopgave->skur2 + $fodopgave->plads + $fodopgave->sygdom + $fodopgave->barn_syg + $fodopgave->skole + $fodopgave->korsel_kr;

					if ($fodsagid != 0)
					{
						$r = db_fetch_array(db_select("SELECT udf_addr1 FROM sager WHERE id='$fodsagid'", __FILE__ . " linje " . __LINE__));
						array_push($fodopgave->udfaddrs, htmlspecialchars($r['udf_addr1']));
						array_push($fodopgave->udftitles, "Sag: $fodsag->sag_nr - Opgave: $fodopgave->opg_nr - " . htmlspecialchars($r['udf_addr1']));
					}

					$fodsag->udfaddrs = array_merge($fodsag->udfaddrs, $fodopgave->udfaddrs);
					$fodsag->udftitles = array_merge($fodsag->udftitles, $fodopgave->udftitles);

					fod_laeg_steder_sammen($fodopgave);
					fod_felter_laeg_til($fodsag, $fodopgave);
				}

				fod_laeg_steder_sammen($fodsag);
				fod_felter_laeg_til($fodansat, $fodsag);
			}

			fod_felter_laeg_til($foddag, $fodansat);
		}

		fod_felter_laeg_til($fodtotal, $foddag);
	}

	fod_log('count($sag_id): ' . count($sag_id) . "\n");
	for($fod=0;$fod<count($sag_id);$fod++)
	{
		fod_log('   $sag_id: ' . $sag_id[$fod] . "\n");
	}

	foreach($foddage as $foddagid => $foddag)
	{
		fod_log('dag ' . $foddagid . ':' . "\n");

		foreach($foddag->ansatte as $fodansatid => $fodansat)
		{
			fod_log('   fodansat ' . $fodansatid . ':' . "\n");

			fod_log('      navn: ' . $fodansat->navn . "\n");
			fod_log('      loen_dyrtid: ' . $fodansat->loen_dyrtid . "\n");

			foreach($fodansat->sager as $fodsagid => $fodsag)
			{
				fod_log('      sag ' . $fodsagid . ':' . "\n");

				fod_log('         sag_nr: ' . $fodsag->sag_nr . "\n");

				foreach($fodsag->opgaver as $fodopgaveid => $fodopgave)
				{
					fod_log('         opgave ' . $fodopgaveid . ':' . "\n");

					fod_log('            opg_nr: ' . $fodopgave->opg_nr . "\n");

					fod_log('            loen_dyrtid: ' . $fodopgave->loen_dyrtid . "\n");
					fod_log('            udfaddr: ' . $fodopgave->udfaddr . "\n");
					fod_log('            udftitle: ' . $fodopgave->udftitle . "\n");
				}
			}
		}
	}

	$funcendtime = microtime(true);

	fod_log('fod_indsaml_data: end: ' . ($funcendtime - $funcstarttime) . ' seconds' . "\n");

	return $fodtotal;
}

function fod_indsaml_loenenheder_gammel()
{
	$funcstarttime = microtime(true);

	$l_enh_id = array();
	$l_akkord = array();
	$l = 0;
	$q2=db_select("SELECT * FROM loen_enheder order by loen_id", __FILE__ . " linje " . __LINE__);

	fod_log('fod_indsaml_loenenheder_gammel: query done in: ' . (microtime(true) - $funcstarttime) . ' seconds' . "\n");

	while ($r2 = db_fetch_array($q2))
	{
		if ($l && isset($r2['loen_id']) && isset($l_enh_id[$l]) && $r2['loen_id']==$l_enh_id[$l])
		{
			$l_akkord[$l] += $r2['op']*$r2['pris_op'];
			$l_akkord[$l] += $r2['op_25']*$r2['pris_op']*0.25;
			$l_akkord[$l] += $r2['op_40']*$r2['pris_op']*0.4;
			$l_akkord[$l] += $r2['op_60']*$r2['pris_op']*0.6;
			$l_akkord[$l] += $r2['op_30m']*$r2['pris_op']*0.1;
			$l_akkord[$l] += $r2['ned']*$r2['pris_ned'];
			$l_akkord[$l] += $r2['ned_25']*$r2['pris_ned']*0.25;
			$l_akkord[$l] += $r2['ned_40']*$r2['pris_ned']*0.4;
			$l_akkord[$l] += $r2['ned_60']*$r2['pris_ned']*0.6;
			$l_akkord[$l] += $r2['ned_30m']*$r2['pris_ned']*0.1;
		}
		else
		{
			$l++;
			$l_enh_id[$l] = $r2['loen_id'];
			$l_akkord[$l] = $r2['op']*$r2['pris_op'];
			$l_akkord[$l] += ($r2['op_25']*$r2['pris_op']*0.25);
			$l_akkord[$l] += ($r2['op_40']*$r2['pris_op']*0.4);
			$l_akkord[$l] += ($r2['op_60']*$r2['pris_op']*0.6);
			$l_akkord[$l] += ($r2['op_30m']*$r2['pris_op']*0.1);
			$l_akkord[$l] += $r2['ned']*$r2['pris_ned'];
			$l_akkord[$l] += ($r2['ned_25']*$r2['pris_ned']*0.25);
			$l_akkord[$l] += ($r2['ned_40']*$r2['pris_ned']*0.4);
			$l_akkord[$l] += ($r2['ned_60']*$r2['pris_ned']*0.6);
			$l_akkord[$l] += ($r2['ned_30m']*$r2['pris_ned']*0.1);
		}
	}

	$funcendtime = microtime(true);

	fod_log('fod_indsaml_loenenheder_gammel: end: ' . ($funcendtime - $funcstarttime) . ' seconds' . "\n");

	return array($l_enh_id, $l_akkord);
}

function loenafregning() #Summeret lønafregning
{
	global $charset;
	global $sprog_id;
	global $bgcolor;
	global $brugernavn;
	global $ansat_id;
	global $sag_rettigheder;
	global $overtid_50pct;
	global $overtid_100pct;

	$funcstarttime = microtime(true);

	fod_log('loenafregning:' . "\n");

#cho "ansat_id $ansat_id<br>";
	$afregnet = NULL;
	$predatoer = NULL;
	
	$bgcolor2="#ffffff";
	$linjebg = $bgcolor2;

	$vis = if_isset($_GET['vis']);

	$afregn = if_isset($_POST['afregn']);
	if ($afregn)
	{
		$afregnet = if_isset($_POST['afregnet']);
		if ($afregnet)
		{
			$afregnet = usdate($afregnet);
			$afregnet = strtotime($afregnet);
		}
		else
		{
			$afreg = NULL;
		}
	}

	$periode = if_isset($_POST['periode']);
	if (!$periode)
		$periode = if_isset($_GET['periode']);

	$ansatvalg = if_isset($_GET['ansat_id']);

	$alle_ansatte_id = if_isset($_POST['alle_ansatte_id']);
	if (!$alle_ansatte_id)
		$alle_ansatte_id = if_isset($_GET['alle_ansatte_id']);

	$visalle = if_isset($_POST['visalle']);
	if (!$visalle)
		$visalle = if_isset($_GET['visalle']);

	$refresh = if_isset($_GET['refresh']);
	//if (!$refresh) $refresh=='on';
	//cho "refresh: $refresh<br>";
	if($alle_ansatte_id)
	{
		$alleid = explode(',',$alle_ansatte_id);
		$alleAnsatte_id = array_values(array_unique($alleid));
	}
	else
	{
		$alleAnsatte_id = array();
	}

	$alleAnsatte_id_total = count($alleAnsatte_id);
	
	if (!substr($sag_rettigheder,6,1))
		$ansatvalg = $ansat_id;

	$x = 0;
	$r = db_fetch_array(db_select("select box4 from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	$p_start[$x] = strtotime($r['box4']);
	while ($p_start[$x] <= date("U")+1209600)
	{
		$x++;
		$p_start[$x] = $p_start[$x-1]+1209600;
	}

	if (!$periode)
		$periode = $p_start[$x-2];

	$startdate = date("Y-m-d",$periode);
	$slutdate = date("Y-m-d",$periode+1209600);

	$tmp = $periode;
	for ($d = 0;$d<14;$d++)
	{
		$datoliste[$d] = date("Y-m-d",$tmp);
		$tmp += 86400;
	}

	fod_log("startdate: " . $startdate . "\n");

	if ($afregn)
	{
		# 20130604 tilføjet: and afvist<'1'
		$qtxt = "UPDATE loen set afregnet='$afregnet',afregnet_af='$brugernavn' WHERE (art='akk_afr' or art='akktimer' or art='akkord' or art='timer' or art='plads' or art='sygdom' or art='barn_syg' or art='skole') and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL) and (afregnet='' or afregnet is NULL)";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	$foddata = fod_indsaml_data($periode);
	$foddage = $foddata->dage;
	
	if ($visalle=='on') #20141003
	{
		print "<div id=\"printableArea\">\n";

		$key = 0;
		foreach ($alleAnsatte_id as $ansatvalg) // loop af ansatte
		{
			$key++;

			$datoliste = NULL;
			
			if ($ansatvalg)
			{
				$qtxt = "SELECT * FROM ansatte WHERE id = '$ansatvalg'";
			}
			else
			{
				$qtxt = "SELECT * FROM ansatte ORDER BY navn";
			}

			$fodansatinfos = array();

			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q))
			{
				$fodansatinfo = new stdClass();
				$fodansatinfo->id = $r['id'];
				$fodansatinfo->navn = $r['navn'];
				array_push($fodansatinfos, $fodansatinfo);
			}
			
			$x = 0;
			$y = 0;
			$pre_d = array();
			$post_d = array();
			# 20130604 tilføjet: and afvist<'1'
			$qtxt = "SELECT * FROM loen WHERE ";
			$qtxt.=" art='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";
			$qtxt.=" order by loendate";
			$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q))
			{
				$ad = array();
				$datoer[$x] = $r['datoer'];
				$ad = explode(chr(9),$datoer[$x]);
				for ($d = 0;$d<count($ad);$d++)
				{
					if ($ad[$d] && $ad[$d]<$startdate && (!in_array($ad[$d],$pre_d)))
					{
						$pre_d[$x] = $ad[$d];
						$x++;
					}
				}
				for ($d = 0;$d<count($ad);$d++)
				{
					if ($ad[$d] && $ad[$d]>=$slutdate && (!in_array($ad[$d],$post_d)))
					{
						$post_d[$y] = $ad[$d];
#cho "PO $post_d[$y]<br>";
						$y++;
					}
				}
			}
			sort($pre_d);
			sort($post_d);
			for ($d = 0;$d<count($pre_d);$d++)
			{
				$predatoer.=$pre_d[$d];
				$datoliste[$d] = $pre_d[$d];
#cho "D1 $datoliste[$d]<br>";
			}
			$tmp = $periode;
			for ($d = count($pre_d);$d<14+count($pre_d);$d++)
			{
				$datoliste[$d] = date("Y-m-d",$tmp);
#cho "D2 $datoliste[$d]<br>";
				$tmp += 86400;
			}
			
			$a = count($datoliste);
			$b = count($datoliste)+count($post_d);
			for ($d = $a;$d<$b;$d++)
			{
				$postdatoer.=$post_d[$d-$a];
				$datoliste[$d] = $post_d[$d-$a];
#cho "D3 $datoliste[$d]<br>";
			}
			
			$datoantal = count($datoliste);
			$x = 0;

			print "<div class=\"content\">\n";
				//print "<form name=\"loenafregning\" action=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;alle_ansatte_id=$alle_ansatte_id&amp;visalle=$visalle\" method=\"post\">\n";
					print "<div style=\"float:left; width:828px;#background-color:lightgreen;\">\n";
					//print "<div id=\"printableArea\">\n";
						$tmp = "<h2 class=\"printHeadline\">Lønopgørelse</h2>\n";
						if ($afregnet) $tmp.="<p class=\"printHeadline\">afregnet d. ".date("d-m-Y",$afregnet)." af $afregnet_af</p>\n";
						print "$tmp\n";
								print "<table width=\"100%\" border=\"0\" class=\"loenafregning\"><tbody>\n";
								print "<tr><td colspan=\"14\"><b>Periode&nbsp;&nbsp;</b>".date("d-m-Y",$periode)."&nbsp;&ndash;&nbsp;".date("d-m-Y",$periode+1209600-86400)."</td></tr>\n";
								/*print "<tr><td colspan=\"14\"><b>Periode&nbsp;&nbsp;</b><SELECT NAME=\"periode\" class=\"printSelect\" onchange=\"this.form.submit()\" >\n";
								for ($x = count($p_start)-3;$x>=0;$x--){
									if ($periode==$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
								}
								for ($x = count($p_start)-3;$x>=0;$x--){
									if ($periode!=$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
								}
								print "</SELECT></td></tr>\n";*/
								if ($vis=="belob")
								{
									print "<tr><td  colspan=\"14\"><b>Lønafregning beløb</b></td></tr>\n";
								}
								else
								{
									print "<tr><td  colspan=\"14\"><b>Lønafregning timer</b></td></tr>\n";
								}

								$vist = 0;

								$fodtotal = new stdClass();
								fod_felter_nulstil($fodtotal);

								for($d = 0;$d<$datoantal;$d++)
								{
									$foddagid = $datoliste[$d];
									$foddag = isset($foddage[$foddagid])?$foddage[$foddagid]:null;

									if ($foddag && $foddag->ansat_sum)
									{
										$foddagtotal = new stdClass();
										fod_felter_nulstil($foddagtotal);

										if (count($fodansatinfos)>1 || !$vist)
										{
											if (count($fodansatinfos)==1)
											{
												print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"15\">\n";
											}
											else
											{
												print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"14\">\n";
											}

											if (count($fodansatinfos)==1)
											{
												print "<h3 class=\"printHeadline\">" . $fodansatinfos[0]->navn . "</h3>\n";
												$vist = 1;
											}
											else
											{
												print "<b>".dkdato($datoliste[$d])."</b>\n";
											}
												print "</td></tr>
												<tr bgcolor=\"$linjebg\"><td><b>\n";
												(count($fodansatinfos)==1)?print "Dato":print "Navn";
										
											print "</b></td>";
												if (count($fodansatinfos)==1) print "<td align=\"center\"><b>Sted</b></td>";
												print "<td align=\"center\"><b>Timer</b></td>
												<td align=\"center\"><b>Dyrtid</b></td>
												<td align=\"center\"><b>50%</b></td>
												<td align=\"center\"><b>100%</b></td>
												<td align=\"center\"><b>Akkord</b></td>";
/*if ($vis=="belob")*/ print "<td align=\"center\"><b>Kørsel</b></td>"; # udkommenteret 20142503
												print "<td align=\"center\"><b>Skur&nbsp;1</b></td>
												<td align=\"center\"><b>Skur&nbsp;2</b></td>
												<td align=\"center\"><b>Plads</b></td>
												<td align=\"center\"><b>Sygdom</b></td>
												<td align=\"center\"><b>Barn&nbsp;syg</b></td>
												<td align=\"center\"><b>Skole</b></td>
												<td align=\"center\"><b>I&nbsp;alt</b></td>
											</tr>";
										}
										for ($y = 0;$y<count($fodansatinfos);$y++)
										{
											$fodansatid = $fodansatinfos[$y]->id;
											$fodansat = isset($foddag->ansatte[$fodansatid])?$foddag->ansatte[$fodansatid]:null;

											if ($fodansat && $fodansat->ansat_sum)
											{
												fod_felter_laeg_til($foddagtotal, $fodansat);

												foreach($fodansat->sager as $fodsag)
												{
													if (0)
													{
														foreach($fodsag->opgaver as $fodopgave)
														{
															($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
															print "<tr bgcolor=\"$linjebg\">";

															fod_felter_vis_dato($foddagid);
															fod_felter_vis_sted($fodopgave, false);

															if ($vis=="belob")
															{
																fod_felter_vis_beloeb($fodopgave, false, false);
															}
															else
															{
																fod_felter_vis_timer($fodopgave, false, false);
															}
														}
													}
													else
													{
														($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
														print "<tr bgcolor=\"$linjebg\">";

														fod_felter_vis_dato($foddagid);
														fod_felter_vis_sted($fodsag, false);

														if ($vis=="belob")
														{
															fod_felter_vis_beloeb($fodsag, false, false);
														}
														else
														{
															fod_felter_vis_timer($fodsag, false, false);
														}

														print "</tr>";
													}
												}
											}
										}

										fod_felter_laeg_til($fodtotal, $foddagtotal);
									}
								}
								
								if (count($fodansatinfos)==1)
								{
									print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr>";
								}
								else
								{
									print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr>";
								}

								($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
								print "<tr bgcolor=\"$linjebg\">";
								fod_felter_vis_titel('I alt', true);
								if ($vis=="belob")
								{
									if (count($fodansatinfos)==1)
										print "<td>&nbsp;</td>";

									fod_felter_vis_beloeb($fodtotal, true, true);
								}
								else
								{
									if (count($fodansatinfos)==1)
										print "<td>&nbsp;</td>";

									fod_felter_vis_timer($fodtotal, true, true);
								}
								print "</tr>
								<tr><td colspan=\"14\"><br></td></tr>";
								/*
								print "<tr class=\"printDisplayNone\"><td colspan=\"7\">
									<input type=submit style=\"width:80px\" value=\"Opdat&eacute;r\" class=\"button gray medium\" name=\"submitForm\" onclick=\"javascript:docChange = false;\">";
								print "<td colspan=\"7\" align=\"right\">";
								if (substr($sag_rettigheder,6,1) && !$afregnet && !$ansatvalg)
								{
									$afregnet = date("d-m-Y");
									print "
										<input type=\"text\" style=\"width:80px;\" value=\"$afregnet\" name=\"afregnet\">
										<input type=\"submit\" style=\"width:80px;\" value=\"Afregn\" class=\"button gray medium\" name=\"afregn\" onclick=\"javascript:docChange = false;\"></td>
										</tr>\n";
								}*/
								print "</tbody></table>\n";		
#					print "</div><!-- end of contentA -->\n";
							//print "</div><!-- end of printableArea -->\n";
							
							//print "<a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">vis alle&nbsp;</a>";
						print "</div><!-- end of full container -->\n";
					print "<div class=\"clear\"></div>\n";
				//print "</form>\n";
			print "</div>\n";
			
			if ($key<$alleAnsatte_id_total)
			{
				print "<div class=\"page-break\"><br /></div>\n";
			}
		} // loop af ansatte omkring hele funktionen
		print "</div><!-- end of printableArea -->\n";
	
	}
	else
	{
		//cho "else";
		$alle_ansatte_id =  NULL;
		if ($ansatvalg)
		{
			$qtxt = "SELECT * FROM ansatte WHERE id = '$ansatvalg'";
		}
		else
		{
			$qtxt = "SELECT * FROM ansatte ORDER BY navn";
		}

		$fodansatinfos = array();

		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q))
		{
			$fodansatinfo = new stdClass();
			$fodansatinfo->id = $r['id'];
			$fodansatinfo->navn = $r['navn'];
			array_push($fodansatinfos, $fodansatinfo);
		}
		
		$x = 0;
		$y = 0;
		$pre_d = array();
		$post_d = array();
		# 20130604 tilføjet: and afvist<'1'
		$qtxt = "SELECT * FROM loen";
		$qtxt.=" WHERE art='akk_afr' and loendate>='$startdate' and loendate<'$slutdate' and godkendt>='1' and (afvist<'1' or afvist is NULL)";

		if (count($fodansatinfos)==1)
			$qtxt.=" and ansatte like '%" . $fodansatinfos[0]->id . "%'";

		$qtxt.=" order by loendate";
		#cho "$qtxt<br>";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q))
		{
			$ad = array();
			$datoer[$x] = $r['datoer'];
			$ad = explode(chr(9),$datoer[$x]);
			for ($d = 0;$d<count($ad);$d++)
			{
				if ($ad[$d] && $ad[$d]<$startdate && (!in_array($ad[$d],$pre_d)))
				{
					$pre_d[$x] = $ad[$d];
					$x++;
				}
			}
			for ($d = 0;$d<count($ad);$d++)
			{
				if ($ad[$d] && $ad[$d]>=$slutdate && (!in_array($ad[$d],$post_d)))
				{
					$post_d[$y] = $ad[$d];
					$y++;
				}
			}
		}
		sort($pre_d);
		sort($post_d);
		for ($d = 0;$d<count($pre_d);$d++)
		{
			$datoliste[$d] = $pre_d[$d];
#cho "D1 $datoliste[$d]<br>";
		}
		$tmp = $periode;
		for ($d = count($pre_d);$d<14+count($pre_d);$d++)
		{
			$datoliste[$d] = date("Y-m-d",$tmp);
#cho "D2 $datoliste[$d]<br>";
			$tmp += 86400;
		}
		
		$a = count($datoliste);
		$b = count($datoliste)+count($post_d);
		for ($d = $a;$d<$b;$d++)
		{
			$datoliste[$d] = $post_d[$d-$a];
		}

		$datoantal = count($datoliste);

		# 20130228 tilføjet: or art='akktimer'
		# 20130604 tilføjet: and afvist<'1'

	print "<div class=\"content\">\n";
		print "<form name=\"loenafregning\" action=\"loen.php?funktion=loenafregning&amp;periode=$periode&amp;ansat_id=$ansatvalg&amp;vis=$vis&amp;refresh=on\" method=\"post\">\n";
			print "<div style=\"float:left; width:828px;#background-color:lightgreen;\">\n";
			print "<div id=\"printableArea\">\n";
				$tmp = "<h2 class=\"printHeadline\">Lønopgørelse</h2>\n";
				if (isset($afregnet) && $afregnet) $tmp.="<p class=\"printHeadline\">afregnet d. ".date("d-m-Y",$afregnet)." af $afregnet_af</p>\n";
				print "$tmp\n";
#					print "<div class=\"contentA\">\n";
						print "<table width='100%' border='0' class='loenafregning'><tbody>\n";
						print "<tr><td colspan='10'><b>Periode&nbsp;&nbsp;</b><SELECT NAME=\"periode\" class=\"printSelect\" onchange=\"this.form.submit()\">\n";# onchange=\"this.form.submit()
						for ($x = count($p_start)-3;$x>=0;$x--){
							if ($periode==$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
						}
						for ($x = count($p_start)-3;$x>=0;$x--){
							if ($periode!=$p_start[$x]) print "<OPTION value=\"$p_start[$x]\">".date("d-m-Y",$p_start[$x])."&nbsp;&ndash;&nbsp;".date("d-m-Y",$p_start[$x+1]-86400)."</option>\n";
						}
						print "</SELECT></td>";
						//print "<td colspan='4' align='right'><input type=submit style=\"width:80px\" value=\"Opdat&eacute;r\" class=\"button gray medium\" name=\"submitForm\" onclick=\"javascript:docChange = false;\"></td></tr>";
						//print "\n";
						if ($vis=="belob")
						{
							print "<tr><td  colspan=\"14\"><b>Lønafregning beløb</b></td></tr>\n";
						}
						else
						{
							print "<tr><td  colspan=\"14\"><b>Lønafregning timer</b></td></tr>\n";
						}
						$vist = 0;

						$fodtotal = new stdClass();
						fod_felter_nulstil($fodtotal);

						for($d = 0;$d<$datoantal;$d++)
						{
							$foddagid = $datoliste[$d];
							$foddag = isset($foddage[$foddagid])?$foddage[$foddagid]:null;

							if ($foddag && $foddag->ansat_sum)
							{
								$foddagtotal = new stdClass();
								fod_felter_nulstil($foddagtotal);

								if (count($fodansatinfos)>1 || !$vist)
								{
									if (count($fodansatinfos)==1)
									{
										print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"15\">\n";
									}
									else
									{
										print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr><tr><td colspan=\"14\">\n";
									}

									if (count($fodansatinfos)==1)
									{
										print "<h3 class=\"printHeadline\">" . $fodansatinfos[0]->navn . "</h3>\n";
										$vist = 1;
									}
									else
										print "<b>".dkdato($datoliste[$d])."</b>\n";

									print "</td></tr>
									<tr bgcolor=\"$linjebg\"><td><b>\n";

									(count($fodansatinfos)==1)?print "Dato":print "Navn";
								
									print "</b></td>";
										if (count($fodansatinfos)==1) print "<td align=\"center\"><b>Sted</b></td>";
										print "<td align=\"center\"><b>Timer</b></td>
										<td align=\"center\"><b>Dyrtid</b></td>
										<td align=\"center\"><b>50%</b></td>
										<td align=\"center\"><b>100%</b></td>
										<td align=\"center\"><b>Akkord</b></td>";
/*if ($vis=="belob")*/ print "<td align=\"center\"><b>Kørsel</b></td>"; # udkommenteret 20142503
										print "<td align=\"center\"><b>Skur&nbsp;1</b></td>
										<td align=\"center\"><b>Skur&nbsp;2</b></td>
										<td align=\"center\"><b>Plads</b></td>
										<td align=\"center\"><b>Sygdom</b></td>
										<td align=\"center\"><b>Barn&nbsp;syg</b></td>
										<td align=\"center\"><b>Skole</b></td>
										<td align=\"center\"><b>I&nbsp;alt</b></td>
									</tr>";
								}
								for ($y = 0;$y<count($fodansatinfos);$y++)
								{
									$fodansatid = $fodansatinfos[$y]->id;
									$fodansat = isset($foddag->ansatte[$fodansatid])?$foddag->ansatte[$fodansatid]:null;

									if ($fodansat && $fodansat->ansat_sum)
									{
										fod_felter_laeg_til($foddagtotal, $fodansat);

										$alle_ansatte_id .= $fodansatinfos[$y]->id . ',';
										
										if (count($fodansatinfos) != 1)
										{
											($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
											print "<tr bgcolor=\"$linjebg\">";

											fod_felter_vis_ansat($fodansat, $periode, $vis);

											if ($vis=="belob")
											{
												fod_felter_vis_beloeb($fodansat, false, false);
											}
											else
											{
												fod_felter_vis_timer($fodansat, false, false);
											}

											print "</tr>";
										}
										else
										{
											if ($fodansat)
											{
												foreach($fodansat->sager as $fodsag)
												{
													if (0)
													{
														foreach($fodsag->opgaver as $fodopgave)
														{
															($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
															print "<tr bgcolor=\"$linjebg\">";

															fod_felter_vis_dato($foddagid);
															fod_felter_vis_sted($fodopgave, false);

															if ($vis=="belob")
															{
																fod_felter_vis_beloeb($fodopgave, false, false);
															}
															else
															{
																fod_felter_vis_timer($fodopgave, false, false);
															}
														}
													}
													else
													{
														($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
														print "<tr bgcolor=\"$linjebg\">";

														fod_felter_vis_dato($foddagid);
														fod_felter_vis_sted($fodsag, false);

														if ($vis=="belob")
														{
															fod_felter_vis_beloeb($fodsag, false, false);
														}
														else
														{
															fod_felter_vis_timer($fodsag, false, false);
														}
													}
												}
											}
										}
									}
								}
								if (count($fodansatinfos)>1)
								{
									($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;
									print "<tr bgcolor=\"$linjebg\">";
									fod_felter_vis_titel('I alt', true);
									fod_felter_vis_timer($foddagtotal, true, true);
									print "</tr>";
								}

								fod_felter_laeg_til($fodtotal, $foddagtotal);
							}
						}
						
						if (count($fodansatinfos)==1)
						{
							print "<tr><td colspan=\"15\"><hr class=\"printHr\"></td></tr>";
						}
						else
						{
							print "<tr><td colspan=\"14\"><hr class=\"printHr\"></td></tr>";
						}

						($linjebg==$bgcolor)?$linjebg = $bgcolor2:$linjebg = $bgcolor;

						print "<tr bgcolor=\"$linjebg\">";

						fod_felter_vis_titel("I alt", true);

						if (count($fodansatinfos) == 1)
						{
							print "<td>&nbsp;</td>";
						}

						if ($vis=="belob")
						{
							fod_felter_vis_beloeb($fodtotal, true, true);
						}
						else
						{
							fod_felter_vis_timer($fodtotal, true, true);
						}

						print "</tr>";

						print "<tr><td colspan=\"14\"><br></td></tr>";
						print "<tr class=\"printDisplayNone\"><td colspan=\"7\">
							<input type=submit style=\"width:80px\" value=\"Opdat&eacute;r\" class=\"button gray medium\" name=\"submitForm\" onclick=\"javascript:docChange = false;\">";
						print "<td colspan=\"7\" align=\"right\">";
						if (substr($sag_rettigheder,6,1) && !$afregnet && !$ansatvalg) {
							$afregnet=date("d-m-Y");
							print "
								<input type=\"text\" style=\"width:80px;\" value=\"$afregnet\" name=\"afregnet\">
								<input type=\"submit\" style=\"width:80px;\" value=\"Afregn\" class=\"button gray medium\" name=\"afregn\" onclick=\"javascript:docChange = false;\"></td>
								</tr>\n";
						}
						print "</tbody></table>\n";		
#					print "</div><!-- end of contentA -->\n";
					print "</div><!-- end of printableArea -->\n";
					
					print "<input type=\"hidden\" name=\"alle_ansatte_id\" value=\"".rtrim($alle_ansatte_id, ",")."\">\n";
					#cho "alle_ansatte_id: $alle_ansatte_id<br>";
					#cho "periode: $periode";
					if ($refresh=='on') {
						$refresh = NULL;
						//print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode\">";
						
						#20150702
						print "<input type=\"hidden\" id=\"alle_ansatte_id\" value=\"".rtrim($alle_ansatte_id, ",")."\">\n";
						print "<input type=\"hidden\" id=\"periode\" value=\"$periode\">\n";
						
						print "<script type=\"text/javascript\">
							$(document).ready(function()
							{
								var alle_ansatte_id = $(\"#alle_ansatte_id\").val();
								var periode = $(\"#periode\").val();
								var dataString = 'alle_ansatte_id='+ alle_ansatte_id + '&periode=' + periode;
							
								$.ajax({
									type: \"POST\",
									url: \"ajax_loenafregning.php\",
									data: dataString,
									dataType: \"html\",
									cache: false,
									success: function(html) {
										$(\".loenafregningVis\").html(html);
									}
								});
								
							});
						</script>";
					}
					//print "<a href=\"loen.php?funktion=loenafregning&amp;alle_ansatte_id=".rtrim($alle_ansatte_id, ",")."&amp;periode=$periode&amp;visalle=on\">vis alle&nbsp;</a>";
				print "</div><!-- end of full container -->\n";
			print "<div class=\"clear\"></div>\n";
		print "</form>\n";
	print "</div>\n";
	}

	$funcendtime = microtime(true);

	fod_log('loenafregning: end: ' . ($funcendtime - $funcstarttime) . ' seconds' . "\n");
} #endfunc s_loenafregning



function satser() {
	global $charset;
	global $sprog_id;

	if (isset($_POST['gruppe_id']) && $gruppe_id=$_POST['gruppe_id']) {
		$skur1=usdecimal($_POST['skur1']);
		$skur2=usdecimal($_POST['skur2']);
		$sygdom=usdecimal($_POST['sygdom']);
		$skole=usdecimal($_POST['skole']);
		$plads=usdecimal($_POST['plads']);
		$periode=usdate($_POST['periode']);
		$traineemdr=usdecimal($_POST['traineemdr']);
		$traineepct=usdecimal($_POST['traineepct']);
		$km_sats=usdecimal($_POST['km_sats']);
		$km_fra=usdecimal($_POST['km_fra']);
		$overtid_50pct=usdecimal($_POST['overtid_50pct']);
		$overtid_100pct=usdecimal($_POST['overtid_100pct']);
		//barsel
		db_modify("update grupper set box1='".$skur1.chr(9).$skur2."',box2='$sygdom',box3='$skole',box4='$periode',box5='".$traineemdr.chr(9).$traineepct."',box6='".$km_sats.chr(9).$km_fra."',box7='$plads',box8='".$overtid_50pct.chr(9).$overtid_100pct."' where id='$gruppe_id'",__FILE__ . " linje " . __LINE__);
	}
	
	$gruppe_id=array();
	$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	$gruppe_id=$r['id'];
	list($skur1,$skur2)=explode(chr(9),$r['box1']);
	$sygdom=$r['box2'];
	$skole=$r['box3'];
	$periode=$r['box4'];
	list($traineemdr,$traineepct)=explode(chr(9),$r['box5']);
	list($km_sats,$km_fra)=explode(chr(9),$r['box6']);
	$plads=$r['box7'];
	list($overtid_50pct,$overtid_100pct)=explode(chr(9),$r['box8']);
	//barsel
	if (!$gruppe_id) {
		db_modify("insert into grupper (beskrivelse,kodenr,art) values ('Lønsatser','0','loen')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
		$gruppe_id=$r['id'];$plads=0;$skur1=0;$skur2=0;$sygdom=0;$skole=0;//barsel
	}
	print "<div class=\"content\">\n";
		print "<form name=\"loensatser\" action=\"loen.php?funktion=satser\" method=\"post\">\n";
			print "<div style=\"float:left; width:778px;\">\n";
				print "<h3>Satser</h3>\n";
				print "<div style=\"float:left; width:389px;\">\n";
					print "<div class=\"contentA\">\n";
						print "<div class=\"row\"><div class=\"leftLarge\">Skur - lav sats</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"skur1\" value=\"".dkdecimal($skur1,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\">Skur - høj sats</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"skur2\" value=\"".dkdecimal($skur2,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\">Sygdom, timesats</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"sygdom\" value=\"".dkdecimal($sygdom,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						//barsel
						print "<div class=\"row\"><div class=\"leftLarge\">Skole, timesats</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"skole\" value=\"".dkdecimal($skole,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\">Plads, timesats</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"plads\" value=\"".dkdecimal($plads,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\" title=\"Vælg dato for 1. dag i 1. lønperiode\">Periodestart</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"periode\" value=\"".dkdato($periode)."\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\" title=\"Oplæringperiode for nye medarbejdere uden branchekendskab\">Oplæringsperiode (mdr)</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"traineemdr\" value=\"".dkdecimal($traineemdr,2)."\"> mdr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\" title=\"Løn% i oplæringperiode\">Oplæringssats (%)</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"traineepct\" value=\"".dkdecimal($traineepct,2)."\"> %</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\" title=\"\">Kilometersats</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"km_sats\" value=\"".dkdecimal($km_sats,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\" title=\"\">Km beregnes efter</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"km_fra\" value=\"".dkdecimal($km_fra,2)."\"> km.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\">Overtidstillæg 50%</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"overtid_50pct\" value=\"".dkdecimal($overtid_50pct,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<div class=\"row\"><div class=\"leftLarge\">Overtidstillæg 100%</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge\" style=\"text-align:right;width:80px\" name=\"overtid_100pct\" value=\"".dkdecimal($overtid_100pct,2)."\"> kr.</div><div class=\"clear\"></div></div><!-- end of row -->\n";
						print "<input type=\"hidden\" name=\"gruppe_id\" value='$gruppe_id'>\n";
						print "<input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" class=\"button gray medium\" name=\"submit\" onclick=\"javascript:docChange = false;\">\n";
					print "</div><!-- end of contentA -->\n";
				print "</div>\n";
			print "</div><!-- end of full container -->\n";
			print "<div class=\"clear\"></div>\n";
	
		print "</form>\n";
	print "</div>\n";
}
function tjek_fordeling($ansat_id,$startdate,$loendate){
	list($sy,$sm,$sd)=explode("-",$startdate);
	$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
	list($traineemdr,$traineepct)=explode(chr(9),$r['box5']);
	$traineepct*=0.8333333;
	$lt=strtotime($loendate);
	$sm+=$traineemdr;
	if ($sm>12) {
		$sy++;
		$sm-=12;
	}
	$st=strtotime($sy."-".$sm."-".$sd);
	if ($lt <= $st) return($traineepct);
	else return('100');
}

?>
