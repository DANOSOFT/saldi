<?php
@session_start();
$s_id = session_id();

$bg     = "nix";
$header = 'nix';

$menu_sager            = NULL;
$menu_planlaeg         = NULL;
$menu_dagbog           = NULL;
$menu_kunder           = NULL;
$menu_loen             = NULL;
$menu_ansatte          = NULL;
$menu_certificering    = 'id="menuActive"';
$menu_medarbejdermappe = NULL;

$modulnr = 0;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

//$sag_id=if_isset($_GET['sag_id']);
//$konto_id=if_isset($_GET['konto_id']);
$funktion = if_isset($_GET['funktion']);
if (!$funktion) $funktion = "certificeringliste";  

	
		global $brugernavn;
		global $db;
		global $regnskab;
		global $ansat_navn;
		
		include_once '../includes/top_header_sager_small.php';
		include_once '../includes/sagsmenu.php';
		
		$funktion();
		//print "</div><!-- end of maincontentLargeHolder -->\n";
		print "</div><!-- end of wrapper2 -->\n";
		print "</body>\n";
		print "</html>\n";
		
		
function certificeringliste() {

		global $sag_rettigheder;
		global $sprog_id;
		
		print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"".findtekst('2781|Hjem', $sprog_id)."\"><img src=\"../img/home.png\" alt=\"".findtekst('2781|Hjem', $sprog_id)."\" class=\"home\" /></a>";
					else print "<a href=\"#\" title=\"".findtekst('2781|Hjem', $sprog_id)."\"><img src=\"../img/home.png\" alt=\"".findtekst('2781|Hjem', $sprog_id)."\" class=\"home\" /></a>";
					print "</li>
				<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->";
				
				print "<li>".findtekst('2777|Certificering', $sprog_id)."</li>
			</ul>
		</div><!-- end of breadcrumbbar -->\n";

		//print "<div class=\"maincontentLargeHolder\">\n";
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		print "<tbody>\n";
		print "<tr><td width=\"100%\" align=\"center\">\n";
		print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"kontrolskema_liste\" >\n";
		print "<tbody>\n";
		print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\"><h4>".findtekst('3156|Certificeringsmenu', $sprog_id)."</h4></td></tr>\n";
		print "<tr><td colspan=\"2\" width=\"100%\" align=center><br>\n";
		print "</tbody>\n";
		print "<tbody class=\"dataTableZebra dataTableTopBorder\">\n";
		print "<tr><td>".findtekst('3157|Kontrolskemaer', $sprog_id)."</td><td class=\"alignRight\"><a href=\"kontrolskemaer.php\" title=\"".findtekst('3158|Gå til kontrolskemaer her', $sprog_id)."!\" class=\"button blue small\">".findtekst('586|Vælg', $sprog_id)."</a></td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
		print "</td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
}
		
		
		
		
		
		
?>