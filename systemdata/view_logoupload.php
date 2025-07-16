<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------systemdata/view_logoupload.php------------patch 3.6.7-----2017-02-10-------------
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
// Copyright (c) 2003-2016 saldi.dk aps
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();

	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");

	global $db_id;
	$current_sprog = isset($_GET['sprog']) ? $_GET['sprog'] : 'Dansk';
	#echo "$db_id";
	$url  = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
	$url .= $_SERVER['SERVER_NAME'];
	$url .= htmlspecialchars($_SERVER['REQUEST_URI']);
	$urlstr = dirname(dirname($url));
	$dataurl = isset($_SERVER['HTTPS']) ? 'https' : 'http';
	
	$baggrund=if_isset($_GET['vis']);


function find_background_file($db_id, $baggrund) {
    $file_path = "../logolib/$db_id/$baggrund.pdf";
    if (file_exists($file_path)) {
        return $baggrund;
    }
    
    if (strpos($baggrund, '_') !== false) {
        $parts = explode('_', $baggrund, 2);
        if (count($parts) == 2) {
            $default_file = $parts[1];
            $default_path = "../logolib/$db_id/$default_file.pdf";
            if (file_exists($default_path)) {
                return $default_file;
            }
        }
    }
    
    return false;
}

$actual_file = find_background_file($db_id, $baggrund);
	
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	// print "<td width=\"10%\" $top_bund height=\"1%\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=logoupload.php>Luk</a></td>";
	print "<td width=\"10%\" $top_bund height=\"1%\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"logoupload.php?sprog=$current_sprog\">Luk</a></td>";
	print "<td width=\"80%\" $top_bund align=\"center\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Udskrift</td>";
	print "<td width=\"10%\" $top_bund align = \"right\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">&nbsp;</td>";
	print "<tr><td width=\"100%\" height=\"100%\" align=\"center\" valign=\"top\" colspan=\"3\">";
	// print "<div style=\"height:100%;\">
	// <object style=\"width:50%;height:100%;\" data=\"$dataurl://docs.google.com/viewer?url=$urlstr%2Flogolib%2F$db_id%2F$baggrund.pdf&amp;embedded=true\"> <!-- bg skal være en variabel sent fra logoupload.php -->
	// 	<p>Din browser kan ikke vise denne fil. Hent filen herunder.</p>
	// 	<a href=\"../logolib/$db_id/bg.pdf</a> 
	// </object>
	// </div>";

	if ($actual_file) {
    print "<div style=\"height:100%;\">
    <object style=\"width:50%;height:100%;\" data=\"$dataurl://docs.google.com/viewer?url=$urlstr%2Flogolib%2F$db_id%2F$actual_file.pdf&amp;embedded=true\">
        <p>Din browser kan ikke vise denne fil. Hent filen herunder.</p>
        <a href=\"../logolib/$db_id/$actual_file.pdf\">Download PDF</a> 
    </object>
    </div>";
} else {
    print "<div style=\"height:100%; text-align:center; padding-top:50px;\">
        <h2>Fil ikke fundet</h2>
        <p>Filen '$baggrund.pdf' kunne ikke findes.</p>
        <p><a href=\"logoupload.php\">Tilbage til upload</a></p>
    </div>";
}
	
	print "</td></tr>";
	print "</tbody></table>";
	
  
?>