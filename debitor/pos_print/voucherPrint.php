<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_print/voucherPrint.php -- lap 3.9.9 -- 2021-01-26 --
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
// Copyright (c) 2021 saldi.dk aps
// --------------------------------------------------------------------------
$dd=date("Y-m-d");
include ("pos_ordre_includes/posTxtPrint/wrapText.php");
include ("pos_ordre_includes/posTxtPrint/escPosBarcode.php");
if (!isset($addBc)) $addBc = 1;
(isset($labelWidth))?$width=$labelWidth:$width=48;
if (!isset($FromCharset)) $FromCharset = 'UTF-8';
if (!isset($ToCharset)) $ToCharset = "cp865";

for ($v=0;$v<count($barcode);$v++) {
#for ($b=0;$b<count($barcode[$v]);$b++) {
$bon.= chr(29)."V".chr(66)."\n"; # feed & cut
/*
$txt="www.shop.dk";
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
$bon.= "$txt\n\n");
*/
$txt = chr(27).'a'.chr(01); #center
$txt.= chr(29).'!'.chr(32); # Triple width
$txt.= iconv($FromCharset, $ToCharset,trim($itemName[$v]));
$txt.= chr(29).'!'.chr(00); # Normal
#$txt.= chr(27).'a'.chr(00); #center
$bon.= "$txt\n";
$txt = chr(29).'!'.chr(16); # Double width
$txt.= iconv($FromCharset, $ToCharset,trim("Kr ".dkdecimal($amount[$v])));
$txt.= chr(29).'!'.chr(00); # Normal
$bon.= "$txt\n\n";
$txt = chr(27).chr(33).chr(1); # condensed
$txt.= iconv($FromCharset, $ToCharset,trim('#'. $barcode[$v]));;
$txt.= chr(27).chr(33).chr(0); # Normal
$bon.= "$txt\n";
$bon.= escPosBarcode($barcode[$v]);
$txt = "$myName";
$txt = iconv($FromCharset, $ToCharset,trim($txt));
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
$bon.= "$txt\n";
$txt = "$myAddr1";
$txt = iconv($FromCharset, $ToCharset,trim($txt));
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
$bon.= "$txt\n";
$txt = "$myZip $myCity";
$txt = iconv($FromCharset, $ToCharset,trim($txt));
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
$bon.= "$txt\n";
$txt = "Tlf.: $myPhone ";
$txt = iconv($FromCharset, $ToCharset,trim($txt));
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
$bon.= "$txt\n";
$txt = "CVR.: $myVatNo";
$txt = iconv($FromCharset, $ToCharset,trim($txt));
while(strlen($txt) < $width) $txt=" ".$txt." ";
if (strlen($txt) > $width) $txt=substr($txt,0,$width);
$bon.= "$txt\n";
#}
}


?>
