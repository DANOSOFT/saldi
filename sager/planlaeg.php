<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/planlaeg.php --- lap 4.0.8 --- 2023-09-27---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

$bg="nix";
$header='nix';

$menu_sager=NULL;
$menu_planlaeg='id="menuActive"';
$menu_dagbog=NULL;
$menu_kunder=NULL;
$menu_loen=NULL;
$menu_ansatte=NULL;
$menu_certificering=NULL;
$menu_medarbejdermappe=NULL;

$modulnr=0;
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");



//$sag_id=if_isset($_GET['sag_id']);
//$konto_id=if_isset($_GET['konto_id']);
$funktion=if_isset($_GET['funktion']);
if (!$funktion) $funktion="planlaeg";  

	
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
		
		
function planlaeg() {

		global $sag_rettigheder;
		
		print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>
				<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->";
				
				print "<li>Planlægning</li>
			</ul>
		</div><!-- end of breadcrumbbar -->\n";

		//print "<div class=\"maincontentLargeHolder\">\n";
		print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
		print "<tbody>\n";
		print "<tr><td width=\"100%\" align=\"center\">\n";
		print "<table width=\"500\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" class=\"kontrolskema_liste\" >\n";
		print "<tbody>\n";
		print "<tr><td colspan=\"2\" width=\"100%\" align=\"center\"><h4>Planlægning menu</h4></td></tr>\n";
		print "<tr><td colspan=\"2\" width=\"100%\" align=center><br>\n";
		print "</tbody>\n";
		print "<tbody class=\"dataTableZebra dataTableTopBorder\">\n";
		print "<tr><td>Planlægning sager</td><td class=\"alignRight\"><a href=\"planlaeg_sager.php\" title=\"Gå til planlægning sager her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		print "<tr><td>Planlægning opgaver</td><td class=\"alignRight\"><a href=\"planlaeg_opgaver.php\" title=\"Gå til planlægning opgaver her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		//print "<tr><td>Planlægning opgaver2</td><td class=\"alignRight\"><a href=\"planlaeg_opgaver2.php\" title=\"Gå til planlægning opgaver her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		//print "<tr><td>Planlægning skema</td><td class=\"alignRight\"><a href=\"planlaeg_skema.php\" title=\"Gå til planlægning skema her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		print "<tr><td>Planlægning beregning</td><td class=\"alignRight\"><a href=\"planlaeg_beregning.php\" title=\"Gå til planlægning beregning her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		
		//print "<tr><td>Planlægning af ansatte</td><td class=\"alignRight\"><a href=\"planlaeg_ansatte.php\" title=\"Gå til planlægning af ansatte her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		//print "<tr><td>Planlægning af sager og opgaver</td><td class=\"alignRight\"><a href=\"planlaeg_sageropg.php\" title=\"Gå til planlægning af sager og opgaver her!\" class=\"button blue small\">Vælg</a></td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
		print "</td></tr>\n";
		print "</tbody>\n";
		print "</table>\n";
}
		
		
		
		
		
		
?>
