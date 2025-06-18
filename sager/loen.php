<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loen.php --- lap 4.0.8 --- 2023-05-22 ---
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
// Copyright (c) 2003-2023 saldi.dk aps
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
// 20180612 PHR - Lønarter lægges nu i array så der kan tilrettes andre brancher (blot en start) #20180612
// 20191001 PHR - Added $hourTypes etc. in loen_art 'timer'  
// 20220601 DAPE - Added $hideSalary to hide employee's salary from sum
// 20230428 PHR - Added op & ned 30,70,100 & 160% and 47+70% tent addon. + php8 compatiblity
// 20230501 PHR - More php8 compatiblity
// 20230502 PHR - Error in query
// 20230522 PHR - Workaround for stillads_5 as tent is in list 13 and all others have tent in 11
// 20230524 PHR - Moved functions to folder loenIncludes.
// 20240220 PK - Added javascript that prevent user from double click.

@session_start();
$s_id = session_id();

/*
ini_set("log_errors", 1);
ini_set("error_log", "/home/mols/logs/php-loen");
*/

$bg = "nix";
$header = 'nix';

$menu_sager = NULL;
$menu_planlaeg = NULL;
$menu_dagbog = NULL;
$menu_kunder = NULL;
$menu_loen = 'id="menuActive"';
$menu_ansatte = NULL;
$menu_certificering = NULL;
$menu_medarbejdermappe = NULL;

$modulnr = 0;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("loenliste.php");
include('loenIncludes/footer.php');


$qtxt = "SELECT table_name FROM information_schema.columns WHERE table_name='loen_enheder' and column_name = 'op_70'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN op_30 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN ned_30 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN op_70 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN ned_70 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN op_100 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN ned_100 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN op_160 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	$qtxt = "ALTER TABLE loen_enheder ADD COLUMN ned_160 numeric(15,3)";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

$r = db_fetch_array(db_select("select * from grupper where art = 'loen'", __FILE__ . " linje " . __LINE__));
list($overtid_50pct, $overtid_100pct) = explode(chr(9), $r['box8']);
$overtid = $r['box8'];
$qtxt = "select * from settings where var_grp = 'salary' and var_name = 'mentor'";
($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) ? $mentor = $r['var_value'] : $mentor = 0;

if ($overtid == NULL) {
	$overtid_50pct = '59.18';
	$overtid_100pct = '118.35';
	db_modify("update grupper set box8='" . $overtid_50pct . chr(9) . $overtid_100pct . "' where art = 'loen'", __FILE__ . " linje " . __LINE__);
}

$funktion = if_isset($_GET['funktion']);
if (!$funktion)
	$funktion = "loenliste";
$sort = if_isset($_GET['sort']);
$nysort = if_isset($_GET['nysort']);
$vis = if_isset($_GET['vis']);

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
include("../includes/sagsmenu.php");
print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
if (substr($sag_rettigheder, 2, 1))
	print "<a href=\"sager.php\" title=\"Sager\"><img src=\"../img/home.png\" alt=\"Sager\" class=\"home\" /></a>\n";
else
	print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
print "</li>";
if ($funktion == 'ret_loen') {
	include('loenIncludes/retLoen.php');
	print "<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li>Lønindtastning</li>";
} elseif ($funktion == 'loenafregning') {
	include('loenIncludes/afregning.php');
	print "<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li>Lønafregning</li>";
} elseif ($funktion == 'satser') {
	include('loenIncludes/rates.php');
	print "<li><a href=\"loen.php\" title=\"Lønliste\">Løn</a></li>
					<li>Satser</li>";
} else {
	include('loenIncludes/visListe.php');
	print "
				<!--<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
				<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
				<li>Current page</li>-->
				<li>Løn</li>";
}
if ($funktion == 'ret_loen')
	print "<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>"; #20150623-2
if ($funktion == 'loenafregning')
	print "<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>";
print "
			</ul>
			</div><!-- end of breadcrumbbar -->
				<div id=\"leftmenuholder\">";
include("leftmenu.php");
print "</div><!-- end of leftmenuholder -->

				<div class=\"maincontent\">";
$funktion();
print "</div><!-- end of maincontent -->

		</div><!-- end of wrapper -->

<script type=\"text/javascript\">
	// Prevent Double Submits
	document.querySelectorAll('form').forEach(form => {
		form.addEventListener('submit', (e) => {
		// Prevent if already submitting
		if (form.classList.contains('is-submitting')) {
			e.preventDefault();
		}
		
		// Add class to hook our visual indicator on
		form.classList.add('is-submitting');
	});
});
</script>
<script type=\"text/javascript\" src=\"../javascript/jquery.loen.js\"></script>
</body>
</html>";


function tjek_fordeling($ansat_id, $startdate, $loendate)
{
	list($sy, $sm, $sd) = explode("-", $startdate);
	$r = db_fetch_array(db_select("select * from grupper where art='loen'", __FILE__ . " linje " . __LINE__));
	list($traineemdr, $traineepct) = explode(chr(9), $r['box5']);
	$traineepct *= 0.8333333;
	$lt = strtotime($loendate);
	$sm += $traineemdr;
	if ($sm > 12) {
		$sy++;
		$sm -= 12;
	}
	$st = strtotime($sy . "-" . $sm . "-" . $sd);
	if ($lt <= $st)
		return ($traineepct);
	else
		return ('100');
}

?>
