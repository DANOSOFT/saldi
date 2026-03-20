<?php 
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/ordreliste.php --- patch 4.1.0 --- 2024-05-29 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//


$header = "nix";
$bg = "nix";
include("../includes/connect.php");
include("../includes/std_func.php");
$db = $_GET["db"];

$connection = db_connect($sqhost, $squser, $sqpass, $db);
$query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
$currentYear = date('Y');
$currentMonth = date('m');
$regnaar;
while($row = db_fetch_array($query)){
    $box1 = $row['box1']; // Starting month
    $box2 = $row['box2']; // Starting year
    $box3 = $row['box3']; // Ending month
    $box4 = $row['box4']; // Ending year
    $kodenr = $row['kodenr'];

    // Check if the current year and month fall within the range
    if (($currentYear > $box2 || ($currentYear == $box2 && $currentMonth >= $box1)) &&
        ($currentYear < $box4 || ($currentYear == $box4 && $currentMonth <= $box3))) {
        // The current year and month fall within the range
        $regnaar = $kodenr;
    }
}

$baseCurrency = get_settings_value("baseCurrency", "globals", "");
if($baseCurrency == ""){
    $baseCurrency = "DKK";
}
if(isset($_GET["getAllProducts"])){
    $query = db_select("SELECT * FROM rentalremote WHERE is_active = 1", __FILE__ . " linje " . __LINE__);
        $res = [];
        while ($row = db_fetch_array($query)) {
            // Filter out numeric keys
            $row = array_filter($row, function($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
        
            $productId = $row['product_id'];
        
            // Fetch product details
            $productQuery = db_select("SELECT beskrivelse, varenr, enhed, salgspris, fotonavn, m_antal, m_rabat, m_type FROM varer WHERE id = $productId", __FILE__ . " linje " . __LINE__);
            $res2 = db_fetch_array($productQuery);
            $row["product_name"] = $res2["beskrivelse"];
            $row["product_number"] = $res2["varenr"];
            $row["unit"] = $res2["enhed"];
            $row["price"] = $res2["salgspris"]*1.25;
            $row["m_antal"] = $res2["m_antal"];
            $row["m_rabat"] = $res2["m_rabat"];
            $row["m_type"] = $res2["m_type"];
            $row["sku"] = $res2["varenr"];
    
            // Fetch rental periods
            $periodsQuery = db_select("SELECT id, amount FROM rentalremoteperiods WHERE rentalremote_id = $row[id]", __FILE__ . " linje " . __LINE__);
            $row["periods"] = [];
            if ($periodsQuery) {
                while ($res3 = db_fetch_array($periodsQuery)) {
                    // Filter out numeric keys
                    $res3 = array_filter($res3, function($key) {
                        return !is_numeric($key);
                    }, ARRAY_FILTER_USE_KEY);
                    $row["periods"][] = $res3;
                }
            }
            $res[] = $row;
        }
        echo json_encode($res);
}

if(isset($_GET["getAllDates"])){
    $productId = $_GET["getAllDates"];
    // Move the condition related to rentalperiods into the ON clause of the LEFT JOIN
    $query = db_select("SELECT ri.id, rp.rt_from, rp.rt_to FROM rentalitems ri LEFT JOIN rentalperiod rp ON ri.id = rp.item_id AND (rp.rt_to > EXTRACT(EPOCH FROM CURRENT_DATE) OR rp.rt_to IS NULL) WHERE ri.product_id = $productId", __FILE__ . " linje " . __LINE__);
    
    $results = [];
    $itemIds = [];
    while($row = db_fetch_array($query)){
        // Filter out numeric keys
        $filtered_row = array();
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                $filtered_row[$key] = $value;
            }
        }
        $row = $filtered_row;
        $itemIds[] = $row["id"];
        $results[] = $row;
    }

    $query = db_select("SELECT item_id, rr_from, rr_to FROM rentalreserved WHERE item_id = ANY(ARRAY[" . implode(",", $itemIds) . "])", __FILE__ . " linje " . __LINE__);

    while($row = db_fetch_array($query)){
        // Filter out numeric keys
        $filtered_row = array();
        foreach ($row as $key => $value) {
            if (!is_numeric($key)) {
                $filtered_row[$key] = $value;
            }
        }
        $row = $filtered_row;
        $row['rt_from'] = $row['rr_from'];
        $row["rt_to"] = $row["rr_to"];
        $row["id"] = $row["item_id"];
        unset($row["rr_to"]);
        unset($row['rr_from']);
        unset($row["item_id"]);
        $results[] = $row;
    }
    
    echo json_encode($results);
}

if(isset($_GET["createBooking"])){
    $data = json_decode(file_get_contents('php://input'), true);
    // make timestamp to unix timestamp
    $from = strtotime($data["start_date"]);
    $to = strtotime($data["end_date"]);
    $expiryTime = time() + 240;
    $expiryTime = date('Y-m-d H:i:s', $expiryTime);
    // if there is closed dates between the selected dates extend the end date by the number of days closed
    while($row = db_fetch_array($query)){
        $closedDate = strtotime($row["day"]);
        if($closedDate >= $from && $closedDate <= $to){
            $to = strtotime("+1 day", $to);
        }
    }
    db_modify("INSERT INTO rentalperiod (item_id, cust_id, rt_from, rt_to, order_id, expiry_time) VALUES ($data[item_id], $data[cust_id], $from, $to, 0, TO_TIMESTAMP('$expiryTime', 'YYYY-MM-DD HH24:MI:SS'))", __FILE__ . " linje " . __LINE__);
    $query = db_select("SELECT id FROM rentalperiod WHERE item_id = $data[item_id] AND cust_id = $data[cust_id] AND rt_from = $from AND rt_to = $to", __FILE__ . " linje " . __LINE__);
    $data["booking_id"] = db_fetch_array($query)["id"];
    if($data["unit"] == "Dag"){
        $data["days"] = $data["weeks"];
    }else{
        $data["days"] = $data["weeks"] * 7;
    }
    $data["fromDate"] = date("Y-m-d", $from);
    $data["toDate"] = date("Y-m-d", $to);
    CreateOrder($data);
    echo json_encode(["id" => $data["booking_id"]]);
}

if(isset($_GET["updateBooking"])){
    $data = json_decode(file_get_contents("php://input"), true);
    $status = $data["status"];
    if($status != "approved"){
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $orderId = $res["order_id"];
        db_modify("DELETE FROM ordrer WHERE id = $orderId", __FILE__ . " linje " . __LINE__);
        db_modify("DELETE FROM rentalperiod WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        echo json_encode(["status" => "deleted"]);
        exit;
    }else{
        $query = db_select("SELECT * FROM rentalperiod WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        $res = db_fetch_array($query);
        $orderId = $res["order_id"];
        db_modify("UPDATE rentalperiod SET expiry_time = NULL WHERE id = $data[id]", __FILE__ . " linje " . __LINE__);
        fakturer_ordre($orderId, "email", 0);
        echo json_encode(["status" => "approved"]);
    }

}

if(isset($_GET["getOrder"])){
    $id = $_GET["getOrder"];
    $query = db_select("SELECT * FROM rentalperiod WHERE id = $id", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $query = db_select("SELECT * FROM ordrer WHERE id = $res[order_id]", __FILE__ . " linje " . __LINE__);
    $order = db_fetch_array($query);
    $query = db_select("SELECT * FROM ordrelinjer WHERE ordre_id = $res[order_id]", __FILE__ . " linje " . __LINE__);
    $orderLines = [];
    while($row = db_fetch_array($query)){
        $orderLines[] = $row;
    }
    $order["orderLines"] = $orderLines;
    echo json_encode($order);
}

if(isset($_GET["getClosedDates"])){
    $query = db_select("SELECT * FROM rentalclosed", __FILE__ . " linje " . __LINE__);
    $i = 0;
    if(db_num_rows($query) <= 0){
        echo json_encode(["msg" => "Der er ingen lukkede dage", "success" => false]);
        exit();
    }
    while($res = db_fetch_array($query)){
        $closedDays[$i]["id"] = $res["id"];
        $closedDays[$i]["date"] = $res["day"];
        $i++;
    }
    echo json_encode($closedDays);
}

if(isset($_GET["getUser"])){
    $id = $_GET["getUser"];
    $query = db_select("SELECT * FROM adresser WHERE id = $id", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    echo json_encode($res);
}

if(isset($_GET["getAllBookingsByUser"])){
    $id = $_GET["getAllBookingsByUser"];
    // make a join for rentalitem on item_id from rentalperiod to id in rentalitems
    $query = db_select("SELECT rp.id, rp.rt_from, rp.rt_to, ri.item_name FROM rentalperiod rp LEFT JOIN rentalitems ri ON rp.item_id = ri.id WHERE rp.cust_id = $id", __FILE__ . " linje " . __LINE__);
    $bookings = [];
    while($res = db_fetch_array($query)){
        $bookings[] = $res;
    }
    echo json_encode($bookings);
}

if(isset($_GET["getTerms"])){
    $query = db_select("SELECT trade_conditions FROM rentalpayment", __FILE__ . " linje " . __LINE__);
    $terms = db_fetch_array($query)["trade_conditions"];
    echo json_encode(["terms" => $terms]);
}

function opret_ordrelinje($id,$vare_id,$varenr,$antal,$beskrivelse,$pris,$rabat_ny,$procent,$art,$momsfri,$posnr,$linje_id,$incl_moms,$kdo,$rabatart,$kopi,$saet,$fast_db,$lev_varenr,$lager,$linje) { #20140426

    if (!$id) return("missing ordre ID");
    global $afd,$barcodeNew;
    global $db,$db_skriv_id;
    global $folger,$formularsprog; #20200109
    global $kundedisplay;	
    global $momssats;
    global $procentfakt;
    global $regnaar;
    global $sprog_id,$status;
    global $tilfravalgNy;
    global $vis_saet;
    global $webservice;
    global $voucherNumber;

        if (file_exists("../temp/$db/pos$id.txt")) unlink ("../temp/$db/pos$id.txt");

    if (isset($_POST['timestamp']) && $_POST['timestamp']) { #20240924
        $timestamp = $_POST['timestamp']."|".$varenr;	
        $fn = "../temp/$db/timestamp".$bruger_id.".txt";
        $preTimestamp = file_get_contents($fn);
        if ($timestamp == $preTimestamp) {
            return;
            exit;
        } else {
            file_put_contents($fn,$timestamp);
        }
    }
    
    if (isset($_SESSION['varenr_ny']) && $_SESSION['varenr_ny']) {
        if ($varenr == $barcodeNew) $varenr = $_SESSION['varenr_ny'];
        unset($_SESSION["varenr_ny"]);
    }

    if ($tilfravalgNy && !strpos($tilfravalgNy,chr(9))) {
        if (is_numeric($tilfravalgNy)) { # don't use is_int - returns false ???
            $qtxt = "select id from varer where id = '$tilfravalgNy'";
            if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $tilfravalgNy = '';
        } else $tilfravalgNy = '';
    }	
    if (!is_numeric($saet)) $saet=0;
    if ($procent=='') $procent=100;
    if (!is_numeric($fast_db)) $fast_db = 0;
    if (!is_numeric($rabat_ny)) $rabat_ny = 0;
    $b2b=$debitorgruppe=$debitorrabatgruppe=$omkunde=$valutakurs=0;
    if (!$afd) $afd=0;
    $dd=date("Y-m-d");
    $tt=date("H:i:s");
#	if (!is_numeric($pris)) $pris=0; #20130903 - fjernet 20140124

    if ($pris && $pris > 99999999) {
        return("Ulovlig v&aelig;rdi i prisfelt");
    }
#fwrite ($log, __line__." Regnaar $regnaar\n");
    if (!$regnaar) {
        $year=date("Y");
        $month=date("m");
        $del1="(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
        $del2="(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
        $del3="(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
        $qtxt="select kodenr from grupper where art='RA' and ($del1 or $del2 or $del3)"; #20190318
    #fwrite ($log, __line__." $qtxt\n");
        if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
            $regnaar=$r['kodenr']*1;
        #fwrite ($log, __line__." Regnaar $regnaar\n");
        } elseif ($r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'",__FILE__ . " linje " . __LINE__))) {
            $regnaar=$r['kodenr']*1;
        } else $regnaar=1;
    }
    $qtxt = "select ordrer.art as art,ordrer.status as status,ordrer.valutakurs as valutakurs,ordrer.afd as afd, ";
    $qtxt.= "adresser.gruppe as debitorgruppe,adresser.rabatgruppe as debitorrabatgruppe from ";
    $qtxt.= "adresser,ordrer where ordrer.id='$id'and adresser.id=ordrer.konto_id";
    if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        $debitorgruppe=$r['debitorgruppe'];
        $debitorrabatgruppe=$r['debitorrabatgruppe'];
        $valutakurs=$r['valutakurs'];
        $status=$r['status'];
        if (!$afd && $r['afd']) $afd=$r['afd'];
    }
    if (!$lager) {
        if ($afd) {
            $r=db_fetch_array(db_select("select box1 from grupper where kodenr='$afd' and art = 'AFD'",__FILE__ . " linje " . __LINE__));
            $lager=$r['box1'];
            if (!$lager) {
                $r=db_fetch_array(db_select("select kodenr from grupper where box1='$afd' and art = 'LG'",__FILE__ . " linje " . __LINE__));
                $lager=$r['kodenr']*1;
            } 
        } else $lager=0;
    }

    if (!$art) $art=$r['art']; #20140424b
    if ($status>=3) { #20131015
        return("Der kan ikke tilføjes linjer i en bogført ordre");
        exit;
    }
    $qtxt = "select box8,box9 from grupper where kodenr='$debitorgruppe' and art = 'DG' and fiscal_year = '$regnaar'";
    if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        $b2b=$r['box8'];
        $omkunde=$r['box9'];
    }
    $varenr=db_escape_string($varenr);
    $varenr_low=strtolower($varenr);
    $varenr_up=strtoupper($varenr);

    $variant_varer=array(); //20181223
    $x=0;
    $qtxt="SELECT distinct(vare_id) FROM variant_varer";
    $q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
    while ($r=db_fetch_array($q)) {
        $variant_varer[$x]=$r['vare_id'];
        $x++;
    }
    
    $qtxt="SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'";
    if (strlen($varenr)==12 && is_numeric($varenr)) $qtxt.=" or variant_stregkode='0$varenr'";
    if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        $vare_id=$r['vare_id'];
        $variant_type=$r['variant_type']*1;
        $variant_id=$r['id'];
        $qtxt="SELECT beskrivelse FROM variant_typer WHERE id = '$variant_type'";
        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $variantText=$r['beskrivelse'];
    } else {
        $variant_id=0;
        $variant_type=$variantText=NULL;
    }
    
    $string=NULL;
    if (isset($vare_id) && $vare_id) $string="select * from varer where id='$vare_id'";
    elseif ($varenr) {
        $string = "select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' ";
        $string.= "or varenr LIKE '$varenr' or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' ";
        $string.= "or stregkode LIKE '$varenr'";
        if (strlen($varenr)==12 && is_numeric($varenr)) $string.=" or stregkode='0$varenr'";
    } elseif ($id && $beskrivelse && $posnr) {
        $qtxt="insert into ordrelinjer ";
        $qtxt.="(ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,procent,m_rabat,pris,kostpris,momsfri,momssats,";
        $qtxt.="posnr,projekt,folgevare,rabatgruppe,bogf_konto,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare,omvbet,";
        $qtxt.="saet,fast_db,tilfravalg,lager) values ";
        $qtxt.="('$id','0','','','$beskrivelse','0','0','','100','0','0','0','','0','$posnr','0','0','0','0','0','','','0','0',";
        $qtxt.="'','$omvbet','$saet','$fast_db','',$lager)";
        fwrite($log, __linr__." $qtxt\n");
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
    } else {
        return ("Manglende varenr eller beskrivelse");
        exit;
    }

    #	fwrite($log,__line__." Pris $pris\n");
    if ($string && $r=db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
        $vare_id=$r['id'];
        $varenr=db_escape_string($r['varenr']);
        $enhed=db_escape_string($r['enhed']);
        $folgevare=(int)$r['folgevare'];
#		$tilfravalg=$r['tilfravalg'];
        $rabatgruppe=$r['rabatgruppe'];
        $varegruppe=(int)$r['gruppe'];
        $samlevare=$r['samlevare'];
        $varerabatgruppe=$r['dvrg']*1;
        if (!$pris && $b2b) $pris=(float)$r['tier_price'];
        $specialType=$r['specialtype'];
        $special_price=(float)$r['special_price'];
        $special_from_date=$r['special_from_date'];
        $special_to_date=$r['special_to_date'];
        $special_from_time=$r['special_from_time'];
        $special_to_time=$r['special_to_time'];
        $serienr=$r['serienr'];
        $beholdning=($r['beholdning'])*1;
        (strpos($r['m_antal'],';'))?list($m_antal,$temp)=explode(";",$r['m_antal'],2):$m_antal=$r['m_antal'];
        $m_antal=trim($m_antal);
        if (!is_numeric($m_antal)) $m_antal=0;
        if (!$varegruppe) {
            return("Varenr $varenr et ikke tilknyttet en varegruppe!");
        }
        if (!$variant_id && in_array($vare_id,$variant_varer)) { //20181223
            return('Brug stregkode ved variant_varer');
            exit;
        }
        if ($folgevare) {
            $qtxt="select varenr from varer where id='$folgevare'";
            if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $folgevare=0;
        }
        if (!$beskrivelse) {
            $beskrivelse=db_escape_string(trim($r['beskrivelse']));
            if ($formularsprog) {
                $r2=db_fetch_array(db_select("select kodenr from grupper where art='VSPR' and box1 = '$formularsprog'",__FILE__ . " linje " . __LINE__));
                $kodenr=$r2['kodenr']*1;
                $r2=db_fetch_array(db_select("select tekst from varetekster where sprog_id='$kodenr' and vare_id='$vare_id'",__FILE__ . " linje " . __LINE__));
                if ($r2['tekst']) $beskrivelse=db_escape_string($r2['tekst']);
            }
        }
#		if (!$posnr && $art!='PO' && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
        if (!$posnr && $r2=db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__))) {
            $posnr=$r2['posnr']+1;
        } elseif (!$posnr) $posnr=1;
        $qtxt = "select box4,box6,box7,box8 from grupper ";
        $qtxt.= "where art = 'VG' and kodenr = '$varegruppe' and fiscal_year = '$regnaar'";
        if (!$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
            $alerttekst=findtekst(320,$sprog_id)." $varenr ".findtekst(321,$sprog_id);
            return ("$alerttekst");
        }
        $bogfkto = $r2['box4'];
        $omvare = $r2['box6'];
        if (!$momsfri) $momsfri = $r2['box7']; #20170207
        $lagerfort = $r2['box8'];
        if (!$bogfkto) 	{
            $alerttekst=findtekst(319,$sprog_id)." ".$varegruppe."!";
          return ("$alerttekst");
        }
        if ($bogfkto && !$momsfri) {
            $qtxt="select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'";
            $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            if ($tmp=(int)substr($r2['moms'],1)) {
                $qtxt="select box1,box2 from grupper where art = 'SM' and kodenr = '$tmp' and fiscal_year = '$regnaar'";
                $r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                if ($r2['box1']) $vatAccount=$r2['box1']*1;
                if ($r2['box2']) $varemomssats=$r2['box2']*1;
            }	else {
                $varemomssats=$momssats;
                $vatAccount=0;
            }	
        } else {
            $varemomssats=0;
            $vatAccount=0;
        }
        $SpecialPeriod=0;

        if (($special_from_date < $dd || ($special_from_date == $dd && $special_from_time <= $tt)) && 
            ($special_to_date > $dd || ($special_to_date == $dd && $special_to_time >= $tt))) $SpecialPeriod=1;
        if ($SpecialPeriod && $special_price && $specialType=='percent') {
            if ($rabat_ny == 0) $rabat_ny=$special_price;
            $special_price=0;
        }

        if (!$pris) {
            $ugedag=date('N');
            $uxtid=date("U");
            $tidspkt=date("H:i:s");
            $qtxt = "select salgspris,kostpris from varetilbud where vare_id='$vare_id' and ugedag='$ugedag' ";
            $qtxt.= "and startdag<='$uxtid' and slutdag >='$uxtid' and starttid <='$tidspkt' and sluttid >='$tidspkt'";
            if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                $pris=$r2['salgspris'];
                $kostpris=$r2['kostpris'];
            } elseif ($SpecialPeriod && $special_price) {# 20161114
                $pris=$special_price;
                $kostpris=$r['campaign_cost']*1;
            } else {
                if ($pris!='0') $pris = (float)$r['salgspris']; #20140124
                $kostpris = (float)$r['kostpris'];
                if ($pris == 0 && $kostpris < 1) $fast_db=$kostpris;
            }
        }	elseif ($momsfri) {
            $kostpris=(float)$r['kostpris'];
        } else {
            $ms=$varemomssats;
            if ($momssats<$varemomssats) $varemomssats=$momssats;		
            if ($incl_moms && $varemomssats) $pris=$pris-($pris*$varemomssats/(100+$varemomssats)); # 20190111 fjernet: $art=='PO' && 
            else $pris*=1; #20140124
            $kostpris=$r['kostpris']*1;
        }
#		fwrite($log,__line__." Pris $pris\n");

        if ($pris && $r['salgspris']==0 && $kostpris<1 && $kostpris>0) {
            $fast_db=$kostpris;
            $kostpris=($pris-$pris*$rabat_ny/100)*$kostpris;
        } else $fast_db=0;
    } elseif (!$kopi) {
        if ($webservice) { #20150218
            if ($varenr) {
#				fwrite($log,__line__." Varenr: $varenr eksisterer ikke\n");
                return ("Varenr: $varenr eksisterer ikke");
            }	else {
                return ('0');
            }
            exit;
        } else {
            vareopslag($art,'varenr','beskrivelse',$id,'','','%'.$varenr.'%'); #20150215
            exit;
        }
        return ("Varenr: $varenr eksisterer ikke");
    }
    if (!is_numeric($rabatgruppe)) $rabatgruppe = 0;
    if (!is_numeric($varerabatgruppe)) $varerabatgruppe = 0;

    $vare_id*=1;
    $m_rabat=0;
    $rabat_ny*=1;
    
    $r2 = db_fetch_array(db_select("select box11 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
    $advar_negativ_lager=$r2['box11'];
    if ($art=='DO' && $lagerfort && !$webservice && $advar_negativ_lager) {  #20140131
        $r=db_fetch_array(db_select("select beholdning from varer where id='$vare_id'",__FILE__ . " linje " . __LINE__));
        $beholdning=$r['beholdning'];
        $r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal, sum(ordrelinjer.leveret) as leveret from ordrelinjer,ordrer where ordrelinjer.vare_id='$vare_id' and ordrelinjer.ordre_id=ordrer.id and ordrer.art='DO' and ordrer.status<3",__FILE__ . " linje " . __LINE__));
        $i_ordre=$r['antal']-$r['leveret'];
        $raadig=$beholdning-$i_ordre;
        $tmp=$antal*1;
        if (!$tmp) $tmp=1;
        if ($raadig<=$tmp) {
            $alerttxt="Beholdning:\\t".dkdecimal($beholdning,2)."\\nI ordre:\\t\\t".dkdecimal($i_ordre)."\\nTil rådighed:\\t".dkdecimal($raadig);
            alert($alerttxt);
        }
    }
    if ($linje_id && $art=='DO') $tmp="id='$linje_id'";
    elseif ($art=='PO') {
        $tmp = "vare_id = '$vare_id' and ordre_id='$id' and pris='$pris' and rabat='$rabat_ny' and variant_id='$variant_id' ";
        $tmp.= "and beskrivelse = '". db_escape_string($beskrivelse) ."' and tilfravalg='$tilfravalgNy' and barcode = '$barcodeNew'";
    }
#	fwrite ($log,__line__." $tmp\n");
    $qtxt="select rabat,posnr,id,antal from ordrelinjer where $tmp";
#	fwrite($log,__line__." $qtxt\n");
    if(!$folger && !$saet && ((!$kopi && $linje_id && $art=='DO') || $art=='PO') && $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20200109
        $antaldiff=$antal;
        $antal=$r['antal']+$antal;
        if (($art!='PO' || $antal) && $antaldiff && $r['id']) {
            if (abs($antal) < 100000000000) {
                db_modify("update ordrelinjer set m_rabat='0', antal=antal+$antaldiff where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
                if ($samlevare == 'on') {
                    db_modify("update ordrelinjer set antal=antal/$r[antal]*$antal where samlevare = '$linje_id'",__FILE__ . " linje " . __LINE__);
                }
                $r2=db_fetch_array(db_select("select sum(antal) as antal from ordrelinjer where vare_id='$vare_id' and pris='$pris' and rabat='0' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
                $tmpantal=$r2['antal'];
            }
            if ($m_antal && $tmpantal >= $m_antal) {
                m_rabat($r['id'],$vare_id,$r['posnr'],$tmpantal,$id,$pris);
            } else {
                db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
            }
        } elseif ($art=='PO' && $r['id']) db_modify("delete from ordrelinjer where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
    } else {
        if ($kopi || $rabat_ny) $rabat=$rabat_ny;
        else {
            if (!$debitorrabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DRG'",__FILE__ . " linje " . __LINE__))){
                $debitorrabatgruppe=$debitorgruppe;
            }
            if (!$varerabatgruppe && !db_fetch_array(db_select("select id from grupper where art='DVRG'",__FILE__ . " linje " . __LINE__))){
                $varerabatgruppe=$varegruppe;
            }
            if (!is_numeric($debitorrabatgruppe)) $debitorrabatgruppe=0;
            if ( !is_numeric($varerabatgruppe)  ) $varerabatgruppe=0;
            if (!isset($rabat)) $rabat = 0;
            $qtxt = "select rabat,rabatart from rabat where vare='$varerabatgruppe' and debitor='$debitorrabatgruppe'";
            if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
                $rabat=$r2['rabat'];
                $rabatart=$r2['rabatart'];
            }
        }
         ($linje_id && $art=='DK')?$kred_linje_id=$linje_id:$kred_linje_id='0';
        if (!$varemomssats && $varemomssats!='0') {
            ($momsfri)?$varemomssats='0':$varemomssats=$momssats;
        }
        $varemomssats*=1;
#		fwrite($log,__line__." Varemomssats $varemomssats\n");
        if ($valutakurs && $valutakurs!=100) {
            $pris=$pris*100/$valutakurs;
            $kostpris=$kostpris*100/$valutakurs;
        }
        if ($momsfri) $VatPrice=$pris;
        else $VatPrice=$pris+$pris*$varemomssats/100;
#		if ($variant_type) {
#			$varianter=explode(chr(9),$variant_type);
#			for ($y=0;$y<count($varianter);$y++) {
#				$qtxt="select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter";
#				$qtxt.=" where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id";
#				$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#				$beskrivelse.=", ".$r1['var_besk']; #.":".$r1['vt_besk'];
#			}
#		}
# exit;
        ($webservice) ?$leveres=$antal:$leveres=0; 
        if ($id && is_numeric($posnr)) {
            $momslog=fopen("../temp/$db/momslog.log","a");
            fwrite($momslog, "varenr $varenr - Varemoms $varemomssats Momskto $vatAccount\n");
            fclose ($momslog);
            if ($varemomssats && !$vatAccount) {
                $alerttxt= __line__." Manglende konto for salgsmoms (Varenr: $varenr indsat uden moms)";
                alert ($alerttxt);
                $varemomssats=0;
#				return ('0');
#				exit;
            }
            if (($samlevare && !$antal) || $antal=='') $antal=1;
            ($omkunde && $omvare)?$omvbet='on':$omvbet='';
            $antal*=1;
            $leveres*=1;
            if ($lager<1) $lager=1; 
            $posnr = abs($posnr); #20200813
#			if ($barcodeNew && !$serienr) $serienr = $barcodeNew;
            if ($art != 'PO' && $art != 'DK' && !$webservice && $variantText) $beskrivelse.= " $variantText"; #20211129
            $qtxt = "insert into ordrelinjer ";
            $qtxt.= "(ordre_id,vare_id,varenr,enhed,beskrivelse,antal,rabat,rabatart,procent,m_rabat,";
            $qtxt.= "pris,vat_price,kostpris,momsfri,momssats,posnr,projekt,folgevare,rabatgruppe,";
            $qtxt.= "bogf_konto,vat_account,kred_linje_id,kdo,serienr,variant_id,leveres,samlevare,";
            $qtxt.= "omvbet,saet,fast_db,lev_varenr,tilfravalg,lager,barcode) ";
            $qtxt.= "values ";
            $qtxt.= "('$id','$vare_id','$varenr','$enhed','$beskrivelse','$antal','$rabat','$rabatart','$procent','$m_rabat',";
            $qtxt.= "'$pris','$VatPrice','$kostpris','$momsfri','$varemomssats','$posnr','','$folgevare','$rabatgruppe',";
            $qtxt.= "'$bogfkto','$vatAccount','$kred_linje_id','$kdo','$serienr','$variant_id','$leveres','$samlevare',";
            $qtxt.= "'$omvbet','$saet','$fast_db','$lev_varenr','$tilfravalgNy','$lager','$barcodeNew')";
#			fwrite($log, __line__." $qtxt\n");
            if (abs($antal) < 100000000000) {
                db_modify($qtxt,__FILE__ . " linje " . __LINE__);
                if ($kundedisplay) {
                    kundedisplay($beskrivelse,$VatPrice*$antal,0); #20201206
                }
            }
            if ($samlevare && !$beholdning) {
                $r=db_fetch_array(db_select("select max(id) as id from ordrelinjer where vare_id='$vare_id' and ordre_id='$id'",__FILE__ . " linje " . __LINE__));
#				samlevare($id,$art,$r['id'],$vare_id,$antal); udkommenteret 20131129
            }
        }
#fclose($log);		
        # finder antal af varen på ordren.
        $qtxt = "select sum(antal) as antal from ordrelinjer where ";
        $qtxt.= "vare_id='$vare_id' and pris != 0 and rabat='0' and ordre_id='$id'";
        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $tmpantal=$r['antal'];
        if ($m_antal && $tmpantal >= $m_antal) {
            $qtxt = "select max(id) as id from ordrelinjer where ";
            $qtxt.= "vare_id='$vare_id' and pris != 0 and rabat='0' and ordre_id='$id'";
            $r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            m_rabat($r2['id'],$vare_id,0,$tmpantal,$id,$pris);
        }	else {
            db_modify("update ordrelinjer set m_rabat='0' where ordre_id = '$id' and vare_id = '$vare_id'",__FILE__ . " linje " . __LINE__);
        }
    }
    if ($vis_saet && $status) db_modify("update ordrer set felt_2='0' where id = '$id'",__FILE__ . " linje " . __LINE__);
    $sum=$pris*$antal;
    return($sum);
#	$varenr=$next_varenr;
#	$antal=NULL;
} # endfunc opret_orderlinje

function CreateOrder($data){
    global $regnaar;
    $query = db_select("SELECT box1, box2, box3, box4, kodenr FROM grupper WHERE art = 'RA'", __FILE__ . " linje " . __LINE__);
    $currentYear = date('Y');
    $currentMonth = date('m');
    $momssats = 0;

    $query = db_select("SELECT gruppe FROM varer WHERE varenr = '$data[sku]'", __FILE__ . " linje " . __LINE__);
    $gruppe = db_fetch_array($query)["gruppe"];

    $q_vg = db_select("SELECT beskrivelse, kodenr, box4, box7 FROM grupper WHERE fiscal_year = $regnaar AND art = 'VG' AND kodenr = $gruppe", __FILE__ . " linje " . __LINE__);
    $r_vg = db_fetch_array($q_vg);

    if ($r_vg && is_array($r_vg)) {
        # Get momssats, checks if it is not momsfri
        if ($r_vg["box7"] != "on") {
            $q_konto = db_select("SELECT moms FROM kontoplan WHERE regnskabsaar = $regnaar AND kontonr = {$r_vg['box4']}", __FILE__ . " linje " . __LINE__);
            $konto_result = db_fetch_array($q_konto);
            $momstype = $konto_result ? $konto_result["moms"] : null;

            if ($momstype) {
                $momstype = trim($momstype, 'S');
                $q_moms = db_select("SELECT box2 FROM grupper WHERE fiscal_year = $regnaar AND kode = 'S' AND kodenr = $momstype AND art = 'SM'", __FILE__ . " linje " . __LINE__);
                $moms_result = db_fetch_array($q_moms);
                $momssats = $moms_result ? $moms_result["box2"] : 0;
            } else {
                $momssats = 0;
            }
        } else {
            $momssats = 0;
        }
    } else {
        // Handle the case where $r_vg is not an array
        error_log("Failed to fetch data for gruppe: $gruppe");
    }
    
    $query = db_select("SELECT * FROM rentalitems WHERE id = $data[item_id]", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $query = db_select("SELECT invoice_date FROM rentalsettings", __FILE__ . " linje " . __LINE__);
    $invoiceDate = db_fetch_array($query)["invoice_date"];
    $product["product_id"] = $res["product_id"];
    $product["name"] = $res["item_name"];
    $query = db_select("SELECT * FROM varer WHERE id = $product[product_id]", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $product["description"] = $res["beskrivelse"];
    $product["product_number"] = $res["varenr"];
    $product["disc_type"] = $res["m_type"];
    $product["unit"] = $res["enhed"];
    if($product["unit"] == "Dag"){
        $weeks = floor($data["days"]);
    }else{
        $weeks = floor($data["days"]/7);
    }
    if(strtolower($res["enhed"]) == "dag"){
        $paidWeeks = $data["days"];
    }else{
        $paidWeeks = number_format($data["days"]/7, 2);
    }
    $discountPeriods = array();
    $discountArray = array();
        
    if($res["m_antal"] != "" && $res["m_rabat"] != "" && $res["m_antal"] != "0" && $res["m_rabat"] != "0"){
    if(strpos($res["m_antal"], ";")){
        $discountPeriods = explode(";", $res["m_antal"]);
        $discountArray = explode(";", $res["m_rabat"]);
    }else{
        $discountPeriods[0] = $res["m_antal"];
        $discountArray[0] = $res["m_rabat"];
    }
    $i = -1;
    foreach($discountPeriods as $period){
        if($period <= $weeks){
            $i++;
        }
    }
    if($i > -1){
        if($product["disc_type"] == "percent"){
            if(strtolower($res["enhed"]) == "dag"){
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $data["days"];
                $discountAmount = ($product["price"] * $discount) / 100;
                $rabatart = "percent";
            }else{
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $paidWeeks;
                $discountAmount = ($product["price"] * $discount) / 100;
                $rabatart = "percent";
            }
        }else{
            if(strtolower($res["enhed"]) == "dag"){
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $data["days"];
                $discountAmount = $discount * $data["days"];
                $rabatart = "amount";
            }else{
                $discount = $discountArray[$i];
                $product["price"] = $res["salgspris"] * $paidWeeks;
                $discountAmount =  $discount * $paidWeeks;
                $rabatart = "amount";
            }
        }
    }else{
        if(strtolower($res["enhed"]) == "dag"){
            $product["price"] = $res["salgspris"] * $data["days"];
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }else{
            $product["price"] = $res["salgspris"] * $paidWeeks;
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }
    }
    }else{
        if(strtolower($res["enhed"]) == "dag"){
            $product["price"] = $res["salgspris"] * $data["days"];
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }else{
            $product["price"] = $res["salgspris"] * $paidWeeks;
            $discountAmount = 0.00;
            $discount = 0.00;
            $rabatart = "";
        }
    }
    $sum = ($product["price"] - $discountAmount);
    $moms = ($product["price"] - $discountAmount) * 0.25;

    $basePrice = $res["salgspris"];
    
    
    $query = db_select("SELECT * FROM adresser WHERE id = $data[cust_id]", __FILE__ . " linje " . __LINE__);
    $res2 = db_fetch_array($query);
    $customer["id"] = $res2["id"];
    $customer["name"] = $res2["firmanavn"];
    $customer["account_number"] = $res2["kontonr"];
    $customer["phone"] = $res2["tlf"];
    $customer["email"] = $res2["email"];
    $customer["address"] = $res2["addr1"];
    $customer["zip"] = $res2["postnr"];
    $customer["city"] = $res2["bynavn"];
    $customer["art"] = "DO";
    $customer["valuta"] = "DKK";
    $customer["payment_condition"] = "netto";
    $customer["payment_days"] = 1;
    $customer["konto_id"] = $res2["id"];
    $enhed = $res["enhed"];
    if(strtolower($enhed) == "dag" && $data["days"] > 1){
        $enhed = "Dage";
    }elseif(strtoLower($enhed) == "uge" && $paidWeeks > 1){
        $enhed = "Uger";
    }
    $date = date("Y-m-d");
    $ordrenr = get_next_order_number('DO');
    if($invoiceDate){
        $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, fakturadate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES ('$customer[name]', '$customer[address]', '$customer[zip]', '$customer[city]', '$customer[email]', $customer[payment_days], '$customer[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], $momssats, '$data[toDate]', $moms)", __FILE__ . " linje " . __LINE__);
    }else{
        $query = db_modify("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, levdate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES ('$customer[name]', '$customer[address]', '$customer[zip]', '$customer[city]', '$customer[email]', $customer[payment_days], '$customer[account_number]', '$customer[art]', '$customer[valuta]', '$date', '$data[fromDate]', $ordrenr, $sum, 1, $customer[konto_id], $momssats, '$data[toDate]', $moms)", __FILE__ . " linje " . __LINE__);
    }
    $query = db_select("SELECT id FROM ordrer WHERE ordrenr = $ordrenr AND art LIKE 'D%'", __FILE__ . " linje " . __LINE__);
    $order_id = db_fetch_array($query)["id"];
    opret_ordrelinje($order_id, $product["product_id"], $product["product_number"], $paidWeeks, $product["description"], $basePrice, $discount, '100', 'D', '', '1', '', '', '', $rabatart, '0', '', '', '', '', __LINE__);
    db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 2, 0, 0, 0, $order_id, 'Stand: $product[name]')", __FILE__ . " linje " . __LINE__);
    db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 3, 0, 0, 0, $order_id, 'Udlejning: Fra $data[fromDate] til $data[toDate]')", __FILE__ . " linje " . __LINE__);
    if($discountAmount > 0){
        // reduce decimal to 2 digits
        $discountAmount = number_format($discountAmount, 2);
        // change from . to ,
        $discountAmount = str_replace(".", ",", $discountAmount);
        db_modify("INSERT INTO ordrelinjer (antal, posnr, pris, rabat, momssats, ordre_id, beskrivelse) VALUES (1, 4, 0, 0, 0, $order_id, 'Rabat $discountAmount kr.')", __FILE__ . " linje " . __LINE__);
    }
    db_modify("UPDATE rentalperiod SET order_id = $order_id WHERE id = $data[booking_id]", __FILE__ . " linje " . __LINE__);
}

function fakturer_ordre($saldi_id,$udskriv_til,$pos_betaling) {
	global $db,$db_skriv_id,$regnaar;
	$brugernavn = "Booking";
	$webservice = 1;
	
	/* include("../includes/ordrefunc.php"); */
	
	$log=fopen("../temp/$db/rest_api.log","a");
	fwrite($log,__line__." ".date("Y-m-d H:i:s")."\n");

	$qtxt="select * from ordrelinjer where ordre_id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$linjesum=0;
	while ($r=db_fetch_array($q)) {
		$linjesum+=$r['antal']*$r['pris']-($r['antal']*$r['pris']*$r['rabat']/100);
		fwrite($log,__line__." $linjesum+=$r[antal]*$r[pris]-($r[antal]*$r[pris]*$r[rabat]/100)\n");
	}
	$qtxt="select betalingsbet,tidspkt,sum,moms,felt_1,felt_2 from ordrer where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$ordresum=$r['sum'];
	$ordremoms=$r['moms'];
	$betalingsbet=$r['betalingsbet'];
	$betalingstype=$r['felt_1'];
	$betalingsum=$r['felt_2'];
	$tidspkt=$r['tidspkt'];
#	$r=db_fetch_array(db_select("select * from ordrer where id = '$saldi_id'",__FILE__ . " linje " . __LINE__));
#	$betalt=$r['sum']+$r['moms'];
#	$korttype=$r['felt_1'];
	$varesum=$varemoms=0;
	$qtxt="select antal,pris,rabat,momssats from ordrelinjer where ordre_id='$saldi_id' and vare_id > 0";
	fwrite($log,__line__." $qtxt\n");
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$linjepris=$r['antal']*($r['pris']-$r['pris']*$r['rabat']/100);
		fwrite($log,__line__." Svar : $linjepris=$r[antal]*($r[pris]-$r[pris]*$r[rabat]/100)\n");
		$linjemoms=$linjepris*$r['momssats']/100;
		$varesum+=afrund($linjepris,3);
		$varemoms+=afrund($linjemoms,3);
		fwrite($log,__line__." $varesum -> $varemoms\n");
	}
	fwrite($log,__line__." abs($ordresum-$varesum)>0.01 || abs($ordremoms-$varemoms)>0.01)\n");
	if (abs($ordresum-$varesum)>0.01 || abs($ordremoms-$varemoms)>0.01) {
		$svar='Error in amount ('.$ordresum.'+'.$ordremoms.') vs. item amount ('.$varesum.'+'.$varemoms.')';
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		return($svar);
		exit;
	}
	transaktion('begin');
	$qtxt="update ordrer set fakturadate=ordredate where id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	$qtxt="update ordrelinjer set leveres = antal where ordre_id='$saldi_id'";
	fwrite($log,__line__." $qtxt\n");
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
	$svar=levering($saldi_id,'on',NULL,'on');
	fwrite($log,__line__." Betalingsbet: $betalingsbet\n");
	if ($betalingsbet!='Forud' && $betalingsbet!='Lb. Md' && $betalingsbet!='Netto') {
		$betalingsdiff=abs($ordresum+$ordremoms-$betalingsum);
		if ($pos_betaling && $betalingsdiff >= 0.01) {
			fwrite($log,__line__." Ordresum : $ordresum\n");
			fwrite($log,__line__." Ordremoms : $ordremoms\n");
			fwrite($log,__line__." Betalingssum : $betalingsum\n");
			$svar='Error in amount ('.$ordresum.'+'.$ordremoms.') vs. paid amount ('.$betalingsum.') : diff '.$betalingsdiff;
		}
	}
	if ($svar=='OK') {
/*
		if ($pos_betaling) { #20190123
			$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs)values('$saldi_id','$betalingstype','$betalingsum','DKK','100')";
			$qtxt=chk4utf8($qtxt);
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			fwrite($log,__line__." Ordre ID $saldi_id faktureret ($svar)\n");
		}
*/		
		$svar=bogfor($saldi_id,'on');
		if ($tidspkt) {	
			$qtxt="update ordrer set tidspkt='$tidspkt' where id='$saldi_id'";
			fwrite($log,__line__." $qtxt\n");
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);	
		}
	} 
	if ($svar != 'OK') {
		fwrite($log,__line__." Svar : $svar\n");
		fclose($log);
		return($svar);
	}
	fclose ($log);
	transaktion ('commit');
	return($saldi_id); 
}