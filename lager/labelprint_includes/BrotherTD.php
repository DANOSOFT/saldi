<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- /lager/labelprint_includes/BrotherTD.php --- lap 4.0.1 --- 2021-03-13 ---
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
// Copyright (c) 2021-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20210313 PHR Added logging 

if (!$ip) $ip = 'localhost';
$txt=implode('', file($filename));
$txt = utf8_decode($txt);
$fp=fopen("../temp/$db/BrTD.txt",'a');
fwrite ($fp, date("H:i:s")."\n$txt\n");
$txt = urlencode($txt);
print "<meta http-equiv=\"refresh\" content=\"0;URL='http://$ip/saldiLabelPrint.php?txt=$txt'\">\n";
exit;
?>
