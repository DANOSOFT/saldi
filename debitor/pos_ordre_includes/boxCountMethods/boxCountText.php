<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/boxCountMethods/boxCountText.php --patch 4.0.8 --2023-07-22--------------
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// LN 20190312 Make functions to count the money in the box
// 20220524 PHR Function myData


function setSpecifiedPrintHeaderTxt() {
    if (getCountry() == "Switzerland") {
        return ["cashStatement" => "Cash-Anweisung", "boxNr" => "Box Nr:", "countBy" => "Gezahlt von:"];
    } else {
        return ["cashStatement" =>  "KASSEOPGØRELSE", "boxNr" => "Kasse nr:", "countBy" => "Optalt af:"];
    }
}
function myData () {
	$qtxt = "select firmanavn, cvrnr from adresser where art = 'S'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	(isset($r['firmanavn']))?$myName = $r['firmanavn']:$myName = '?';
	(isset($r['cvrnr']))?$myVat = $r['cvrnr']:$myVat = '?';
	$qtxt = "select var_value from settings where var_name = 'globalId'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	(isset($r['var_value']))?$myId = $r['var_value']:$myId = '?';
	return("$myName\t$myVat\t$myId"); 
}


function setPrintHeaderTxt($FromCharset, $ToCharset, $fp, $dd, $tid, $kasse, $brugernavn) {
		global $sprog_id;
		$headerTxt = setSpecifiedPrintHeaderTxt();
    $kassopgorelse = $headerTxt['cashStatement'];
    $tmp = iconv($FromCharset, $ToCharset,$kassopgorelse);
    fwrite($fp,"\n\n$tmp\n\n");
 #   fwrite($log,"\n\n$tmp\n\n");
		$myData = explode("\t",myData());
    $tmp = iconv($FromCharset, $ToCharset,$myData[0]);
    fwrite($fp,"$tmp\n");
    $txt = findtekst(48,$sprog_id);
    while (strlen($txt) < 9) $txt.=' ';
    $tmp = iconv($FromCharset, $ToCharset,$myData[1]);
    fwrite($fp,"$txt : $tmp\n");
    fwrite($fp,"System ID : SaldiPOS $myData[2]\n\n");
    fwrite($fp,"Den $dd kl. $tid\n");
 #   fwrite($log,"Den $dd kl. $tid\n");
    fwrite($fp,"$headerTxt[boxNr] $kasse\n");
 #   fwrite($log,"$headerTxt[boxNr] $kasse\n");
    $tmp = iconv($FromCharset, $ToCharset,$brugernavn);
    fwrite($fp,"$headerTxt[countBy] $tmp\n");
 #   fwrite($log,"$headerTxt[countBy] $tmp\n");
}

function getSpecifiedSignatureText()
{   
    if (getCountry() == "Switzerland") {
        return ["signature" => "Unterschrift:______________________"];
    } else {
        return ["signature" =>  "Underskrift:______________________"];
    }
}

function setSignatureTxt($fp, $log)
{
    $txt = getSpecifiedSignatureText();
    $signature = $txt['signature'];
 	fwrite($fp,"\n\n $signature \n\n");
 	fwrite($log,"\n\n $signature \n\n");
}









?>

