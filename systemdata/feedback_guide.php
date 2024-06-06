<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------------- systemdata/diverse.php ------------------ ver 4.0.5 -- 2022-05-14 --
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
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20230306 MSC - Edited screenshot message

@session_start();
$s_id=session_id();
ob_start();

$title="Guide: Udfyld feedback form";
$modulnr=1;
$diffkto=NULL;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");


include_once '../includes/top_header.php';
include_once '../includes/top_menu.php';
print "<div id=\"header\">"; 
print "<div class=\"headerbtnLft headLink\"><a href=feedbackmail.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
print "<div class=\"headerTxt\">$title</div>";     
print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
print "</div>";
print "<div class='content-noside'>";

print "<center>";

print "
<img src='feedback/navn.png'><br>
Dette felt skal udfyldes med dit navn.<br>
<br><br>
<img src='feedback/email.png'><br>
Dette felt skal udfyldes med din email.<br>
<br><br>
<img src='feedback/regnskab.png'><br>
Skriv navnet på regnskabet, ved en uforudset hændelse. Dette felt skal udfyldes.<br>
<br><br>
<img src='feedback/browser.png'><br>
Skriv navnet på den internet browser du bruger. Dette felt skal udfyldes.<br>
<br><br>
<img src='feedback/link.png'><br>
Her skal du kopiere linket til siden, hvor du har fundet den uforudset hændelse. Dette felt skal udfyldes.<br>
<br>
<img src='feedback/copylink.png'><br>
Markér og højre klik i adressebaren, herefter vælger du <b>Kopier</b><br>
<br>
<img src='feedback/pastelink.png'><br>
Højre klik i <b>Link til siden</b> feltet og vælg <b>Indsæt</b><br>
<br>
<img src='feedback/pastedlink.png'><br>
Linket vil nu blive sat ind i <b>Link til siden</b> feltet.<br>
<br>
";

$user_agent = $_SERVER['HTTP_USER_AGENT'];

function getOS() { 

    global $user_agent;

    $os_platform  = "Unknown OS Platform";

    $os_array     = array(
                          '/windows nt 10/i'      =>  'Windows 10',
                          '/windows nt 6.3/i'     =>  'Windows 8.1',
                          '/windows nt 6.2/i'     =>  'Windows 8',
                          '/windows nt 6.1/i'     =>  'Windows 7',
                          '/windows nt 6.0/i'     =>  'Windows Vista',
                          '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
                          '/windows nt 5.1/i'     =>  'Windows XP',
                          '/windows xp/i'         =>  'Windows XP',
                          '/windows nt 5.0/i'     =>  'Windows 2000',
                          '/windows me/i'         =>  'Windows ME',
                          '/win98/i'              =>  'Windows 98',
                          '/win95/i'              =>  'Windows 95',
                          '/win16/i'              =>  'Windows 3.11',
                          '/macintosh|mac os x/i' =>  'Mac OS X',
                          '/mac_powerpc/i'        =>  'Mac OS 9',
                          '/linux/i'              =>  'Linux',
                          '/ubuntu/i'             =>  'Ubuntu',
                          '/iphone/i'             =>  'iPhone',
                          '/ipod/i'               =>  'iPod',
                          '/ipad/i'               =>  'iPad',
                          '/android/i'            =>  'Android',
                          '/blackberry/i'         =>  'BlackBerry',
                          '/webos/i'              =>  'Mobile'
                    );

    foreach ($os_array as $regex => $value)
        if (preg_match($regex, $user_agent))
            $os_platform = $value;

    return $os_platform;
}

$user_os        = getOS();

print_r($device_details);


print "<br><b style='font-size:20px;'>Hvordan tager man et skærmbillede?</b><br>";

if ($user_os=='Linux') {
print "<b>Linux:</b> <br>
Tryk på PrtSc knapeen på dit tastatur, hvor efter du kan finde filen i din billed mappe<br><br>";
} 

if ($user_os=='Windows 10' or $user_os=='Windows 8.1' or $user_os=='Windows 7' or $user_os=='Windows Vista' or $user_os=='Windows Server 2003/XP x64' or $user_os=='Windows XP') {
print "<b>Windows:</b> <br>
Hold Windows knapeen og tryk derefter på PrtSc knappen på dit tastatur, hvorefter du kan finde screenshottet i din billede mappe under en mappe kaldt skærmbilleder";
}

if ($user_os=='Mac OS X' or $user_os=='Mac OS 9') {
print "<b>Mac:</b> <br>
Tryk og hold shift og command og 3 nede, hvorefter du kan finde screenshottet i din billedmappe";

}

print "
<img src='feedback/choosefile.png'><br>
Klik på <b>Vælg fil</b> udfra <b>Vedhæft screenshot/billede</b> feltet.<br>
<br>
<img src='feedback/screenshotfolder.png'><br>
Gå ind i din billedmappe og find mappen som indeholder dine <b>Skærmbilleder</b>. Klik ind i mappen.<br>
<br>
<img src='feedback/screenshotfolder_in.png'><br>
Find det skærmbillede du vil vedhæfte og markér og tryk på vælg eller dobble klik på filen.<br>
<br>
<img src='feedback/choosenfile.png'><br>
Skærmbilledet er nu valgt og filnavnet fremkommer ud for <b>Vælg fil</b> knappen.<br>
<br>
";

print "</center>";

include_once '../includes/topmenu/footer.php';

?>