<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/posTxtPrint/varHandle.php ---------- lap 3.7.4----2019.05.08-------
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
// 06.05.2019 LN Get data from ordrelinjer and adresser

function getAdressVar()
{
	global $firmanavn;
	global $addr1;
	global $addr2;
	global $postnr;
	global $bynavn;
	global $tlf;
	global $cvrnr;
	global $country;
	
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

function setAmountTxt($country)
{
    if ($country == 'Switzerland') { #LN 20190212 To make the correct receipt according to the country
        $belob="beträge";
    } else if ($country == 'Norway') {
        $belob = "beløp";
    } else {
        $belob = "beløb";
    }
    $ToCharset = "cp865";
    $FromCharset = getFromCharset();
    $convert = new ConvertCharset();
    $belob = iconv($FromCharset, $ToCharset,$belob);
    return $belob;
}

function setReceiptAsCopied($orderQuery, $type, $id)
{
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


function useCorrectCharset()
{
    global $firmanavn;
    global $addr1;
    global $addr2;
    global $bynavn;
    global $tlf;
    global $cvrnr;
    global $kundenavn;
    global $kundeaddr1;
    global $kundeby;
    global $ref;
    
	$ToCharset = "cp865";
	$FromCharset = getFromCharset();
	$convert = new ConvertCharset();
    
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

function getFromCharset()
{
    global $db_encode;
	if ($db_encode=="UTF8") {
        $FromCharset = "UTF-8";
    }
	else {
        $FromCharset = "iso-8859-15";
    }
    return $FromCharset;
}


function getProductInfo($tfvare)
{
    global $beskrivelse;
    global $dkkpris;
    global $antal;
    global $f_momssats;

    $qtxt="select varenr,beskrivelse,salgspris,gruppe from varer where id = '$tfvare[$fv]'";
    $r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
    $r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
    $f_bogfkto=$r2['box4'];
    $f_momsfri=$r2['box7'];
    $tmp=afrund($antal[$x]*$r1['salgspris'],2);
    $sum+=$tmp;
    if (!$f_momsfri){
        $r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
        $kodenr=substr($r2['moms'],1);
        $r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
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

