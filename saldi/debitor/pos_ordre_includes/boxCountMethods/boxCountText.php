<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/boxCountMethods/boxCountText.php ---------- lap 3.7.7----2019.03.14-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190312 Make functions to count the money in the box



function setSpecifiedPrintHeaderTxt()
{
    if (getCountry() == "Switzerland") {
        return ["cashStatement" => "Cash-Anweisung", "boxNr" => "Box Nr:", "countBy" => "Gezahlt von:"];
    } else {
        return ["cashStatement" =>  "KASSEOPGØRELSE", "boxNr" => "Kasse nr:", "countBy" => "Optalt af:"];
    }
}

function setPrintHeaderTxt($FromCharset, $ToCharset, $fp, $dd, $tid, $kasse, $brugernavn)
{
    $txt = setSpecifiedPrintHeaderTxt();
    $kassopgorelse = $txt['cashStatement'];
    $tmp = iconv($FromCharset, $ToCharset,$kassopgorelse);
    fwrite($fp,"\n\n$tmp\n\n");
    fwrite($log,"\n\n$tmp\n\n");
    fwrite($fp,"Den $dd kl. $tid\n");
    fwrite($log,"Den $dd kl. $tid\n");
    fwrite($fp,"$txt[boxNr] $kasse\n");
    fwrite($log,"$txt[boxNr] $kasse\n");
    $tmp = iconv($FromCharset, $ToCharset,$brugernavn);
    fwrite($fp,"$txt[countBy] $tmp\n");
    fwrite($log,"$txt[countBy] $tmp\n");
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

