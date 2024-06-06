<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -- debitor/pos_ordre_includes/posTxtPrint/posTxtFunctions.php -- lap 3.7.4 -- 2020.06.24--
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
// Copyright (c) 2003-2020 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20190506 LN Get data from ordrelinjer and adresser
// 20200624 PHR function getProductInfo - $tfvare[$fv]*=1;

function getAdressVar() {
	global $addr1,$addr2;
	global $bynavn;
	global $country,$cvrnr;
	global $firmanavn;
	global $postnr;
	global $tlf;
	
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr=$r['postnr'];
	$bynavn=$r['bynavn'];
	$tlf=$r['tlf'];
	$cvrnr=$r['cvrnr'];
	$country=$r['land'];
}

function setAmountTxt($country) {
	global $charset;
	
    if ($country == 'Switzerland') { #LN 20190212 To make the correct receipt according to the country
        $belob="beträge";
    } else if ($country == 'Norway') {
        $belob = "beløp";
    } else {
        $belob = "beløb";
    }
    $ToCharset = "cp865";
    $FromCharset = $charset;
#    $convert = new ConvertCharset();
    $belob = iconv($FromCharset, $ToCharset,$belob);
    return $belob;
}

function setReceiptAsCopied($orderQuery, $type, $id) {
    if (isset($orderQuery['copied']) && $type == "standard") {
        if ($orderQuery['copied'] == 't') {	 	
            $doNotPrint = "copied";
            return $doNotPrint;
        } else {
            $copied = 1;
            db_modify("update ordrer set copied='$copied' where id = $id", __FILE__ . "linje" . __LINE__);
            return "";
        }
    } elseif($type == "standard") {	
        $copied = 0;
         db_modify("update ordrer set copied='$copied' where id = $id",__FILE__ . " linje " . __LINE__);		
        return "";
    }
}


function useCorrectCharset() {
    global $firmanavn;
    global $addr1;
    global $addr2;
    global $bynavn;
    global $tlf;
    global $charset,$cvrnr;
    global $kundenavn;
    global $kundeaddr1;
    global $kundeby;
    global $ref;
    
	$ToCharset = "cp865";
	$FromCharset = $charset;
    
	if ($firmanavn) $firmanavn = iconv($FromCharset, $ToCharset,$firmanavn);
	if ($addr1)     $addr1     = iconv($FromCharset, $ToCharset,$addr1);
	if ($addr2)     $addr2     = iconv($FromCharset, $ToCharset,$addr2);
	if ($bynavn)    $bynavn    = iconv($FromCharset, $ToCharset,$bynavn);
	if ($tlf)       $tlf       = iconv($FromCharset, $ToCharset,$tlf);
	if ($cvrnr)     $cvrnr     = iconv($FromCharset, $ToCharset,$cvrnr);
    if ($kundenavn)  $kundenavn  = iconv($FromCharset, $ToCharset,$kundenavn);
	if ($kundeaddr1) $kundeaddr1 = iconv($FromCharset, $ToCharset,$kundeaddr1);
	if ($kundeby)    $kundeby    = iconv($FromCharset, $ToCharset,$kundeby);
	if ($ref)        $ref        = iconv($FromCharset, $ToCharset,$ref);
}

/*
function getFromCharset() {
    global $db_encode;
	if ($db_encode=="UTF8") {
        $FromCharset = "UTF-8";
    }
	else {
        $FromCharset = "iso-8859-15";
    }
    return $FromCharset;
}
*/

function getProductInfo($tfvare) {
	global $beskrivelse;
	global $dkkpris;
	global $antal;
	global $f_momssats;

	$tfvare[$fv]*=1;
	if (!$tfvare[$fv]) return;
	$qtxt="select varenr,beskrivelse,salgspris,gruppe from varer where id = '$tfvare[$fv]'";
	$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$qtxt="select box4, box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'";
	$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$f_bogfkto=$r2['box4'];
	$f_momsfri=$r2['box7'];
	$tmp=afrund($antal[$x]*$r1['salgspris'],2);
	$sum+=$tmp;
	if (!$f_momsfri){
		$qtxt = "select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$kodenr=substr($r2['moms'],1);
		$qtxt="select box2 from grupper where kodenr = '$kodenr' and art = 'SM'";
		$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$f_momssats=$r2['box2']*1;
		$incl_moms+=afrund($tmp+$tmp*$f_momssats/100,2);
		$moms+=$tmp*$f_momssats/100;
	} else $f_momssats=0;
	if ($folgevare[$x] || $r1['salgspris']!=0) {
		$x++;
		$antal[$x]=$antal[$x-1];
		$beskrivelse[$x]=$r1['beskrivelse'];
		$dkkpris[$x]=dkdecimal(($r1['salgspris']+$r1['salgspris']*$f_momssats/100)*$antal[$x],2);
		while(strlen($antal[$x])<3) $beskrivelse[$x]=" ".$beskrivelse[$x];
		if ($beskrivelse[$x]) $beskrivelse[$x] = iconv($FromCharset, $ToCharset,$beskrivelse[$x]);
		while(strlen($beskrivelse[$x])<26) $beskrivelse[$x]=$beskrivelse[$x]." ";
		while(strlen($dkkpris[$x])<9) $dkkpris[$x]=" ".$dkkpris[$x];
	}
}


?>

