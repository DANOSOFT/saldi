<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/lager/stockLog.php---------lap 4.1.1---2024-8-16 -----
// LICENS
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
// Copyright (c) 2020-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240816 PHR Translations

@session_start();
$s_id=session_id();

$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id = if_isset($_GET['id'])*1;
$usNa=array();
$linjebg=0;
$s=0;
$qtxt="select * from stocklog where item_id = '$id' order by id desc";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$usNa[$s]=$r['username'];
	$init[$s]=$r['initials'];
	$reas[$s]=str_replace('\n','<br>',$r['reason']);
	$corr[$s]=dkdecimal($r['correction']);
	$daTi[$s]=date("d-m-Y H:i",$r['logtime']);
	$s++;
}
$txt30  = findtekst(30,$sprog_id); // Tilbage
$txt647  = findtekst(647,$sprog_id); // Initialer
$txt916  = findtekst(916,$sprog_id); // Antal
$txt930  = findtekst(930,$sprog_id); // Tidspkt
$txt990  = findtekst(990,$sprog_id); // Bruger
$txt2124 = findtekst(2124,$sprog_id); // Årsag


($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
$txt = "<center><table>";
$txt.= "<tr bgcolor='$linjebg'><td>$txt990</td><td>$txt647</td><td style='width:800px'>$txt2124</td><td>$txt916</td>";
$txt.= "<td align='center' width='150px'>$txt930</td></tr>";
$txt.= "<tr bgcolor='$linjebg'><td colspan='5'></td></tr>";
for ($s=0;$s<count($usNa);$s++) {
	($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
	$txt.= "<tr bgcolor='$linjebg'><td>$usNa[$s]</td><td>$init[$s]</td><td>$reas[$s]</td><td align='right'>$corr[$s]</td>";
	$txt.= "<td align='right'>$daTi[$s]</td></tr>";
}
($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
$txt.= "<tr bgcolor='$linjebg'><td colspan='5'><br></td></tr>";
($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
$txt.= "<tr bgcolor='$linjebg'><td colspan='5' align='center'><a href=varekort.php?id=$id><button style='width:200px;'>$txt30</button></a></td></tr>";
$txt.= "</table>";
print $txt;
print "</html>";
?>
