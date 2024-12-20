<?php
//                         ___   _   _   __  _
//                        / __| / \ | | |  \| |
//                        \__ \/ _ \| |_| | | |
//                        |___/_/ \_|___|__/|_|
//
// ------------- debitor/gavekortfunk.php -------- lap 3.7.2 -- 2018.12.10 --
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
// Copyright (c) 2004-2018 saldi.dk aps
// --------------------------------------------------------------------------
//
// 2018-12-10 CA  De første gavekortfunktioner til håndtering i PoS
// 2019-17-10 LN  Posen må ikke loades mere end to gange efter køb

use \Datetime;

function gavekortSetup()
{
    $temp = false;
    if ($temp == true) {
        global $db;
        global $bruger_id;
        if (isset($_SESSION['gavekort']) && $_SESSION['gavekort'] == "giftCard") {
            unset($_SESSION['gavekort']);
            $_SESSION['printedGiftCard'] = true;
            $pfnavn = "../temp/" . $db . "/" . $bruger_id . ".txt";
            $fp = fopen("$pfnavn", "w");
            $printserver = getPrintVariablesForGavekort();
            $masterData = getShopMasterData();
            $giftCards = getGiftCardInfo();
            include("gavekortReceipt.php");
        }

        handleGiftCardSession();
    }
}

function getGiftCardInfo()
{
    $id = $_GET['id'];
    $queryTxt = db_select("select * from ordrelinjer where ordre_id='$id'", __FILE__ . "linje" . __LINE__);
    while ($order = db_fetch_array($queryTxt)) {
        $description = $order['beskrivelse'];
        if ($description == "Gavekort") {
            $giftCard = $order;
        }
    }
    $orderQuery = db_select("select valuta, ordredate from ordrer where id='$id'", __FILE__ . "linje" . __LINE__);
    while ($order = db_fetch_array($orderQuery)) {
        $giftCard['valuta'] = $order['valuta'];
        $giftCard['orderDate'] = $order['ordredate'];
    }
    $tempDate = $giftCard['orderDate'];
    $newDate = date("d/m-Y", strtotime($tempDate));
    $newTime = date("d/m-Y", strtotime("$tempDate +2 weeks"));
    $giftCard['orderDate'] = $newDate;
    $giftCard['endDate'] = $newTime;

    return $giftCard;
}

function handleGiftCardSession()
{
    if (isset($_GET['id']) && isset($_POST['betaling']) && $_SESSION['printedGiftCard'] != true) {
        $id = $_GET['id'];
        $query = db_select("select * from ordrelinjer where ordre_id='$id'", __LINE__ . "linje" . __LINE__);
        while ($order = db_fetch_array($query)) {
            $description = $order['beskrivelse'];
            if ($description == "Gavekort") {
                $_SESSION['gavekort'] = "giftCard";
            }
        }
    } elseif ($_POST['beskrivelse_ny'] == "Gavekort") {
        unset($_SESSION['printedGiftCard']);
    }
}

function getShopMasterData()
{
    $queryTxt = db_select("select * from adresser where id='1'", __FILE__ . "linje" . __LINE__);
    $query = db_fetch_array($queryTxt);
    return $query;
}

function getPrintVariablesForGavekort()
{
    $r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
    $printer_ip=explode(chr(9),$r['box3']);
    $printserver=$printer_ip[0];
    return $printserver;
}































function gavekortsalg() {       # 20181030
        $gavekortbeloeb=0;
        $gavekortnr=0;
        $bonnr=0;
        $qtxt= "select count(*) as antal from gavekort";
        $r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $gavekortnr=$r[antal];
        do {
                $gavekortnr++;
                $qtxt="select count (*) as antal from gavekort where gavekortnr = '$gavekortnr'";
                $qtxt="select count (*) as antal from varer where gruppe = '$gavekortnr'";
                $r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
        } while ( $r[antal] );

#       while gavekortnr_fundet($gavekortnr) { 
#               $gavekortnr+1; 
#       } 
        # gavekortopdat($gavekortnr,$gavekortbeloeb,$bonnr); 
                #  
#       echo "<script>\n"; 
#       echo "window.alert(Gavekortsalg);\n"; 
#       echo "</script>\n"; 
        print "<table><tbody>\n";
        print "<tr><td width='30%'>";
        print "<table><tbody>\n";
        print "<h2>Gavekortsalg</h2>\n";
        print "<p>Gavekortsalg: Gavekortnummer ".$gavekortnr."</p>\n";

        print "<form name=\"gavekortsalg\" action=\"pos_ordre.php?id=$id&kasse=$kasse&gavekorttype=$gavekorttype&beloeb=$gavekortbeloeb&vareid_ny=4\" method=\"post\" autocomplete=\"off\">\n";
        print "Beløb: <input name=\"gavekortbeloeb\" type=\"text\" /><br />\n";
        print "Gavekortnummer: <input name=\"gavekortnr\" value=\"$gavekortnr\" type=\"text\" /><br />\n";
        print "<input name=\"vareid_ny\" type=\"text\" value=\"3\" /><br />\n";
#       print "<input value=\"Opret\" type=\"submit\" />\n";
#        print "</form>\n";
        print "</tbody></table></td>\n";
        print "</tr></tbody></table>\n";




        return;
} #endfunc gavekortsalg 

function nytgavekortnummer($gavekortnummer) {
    $gavekortnummerstart=10000;
        if ( $gavekortnummer > 0 ) {
                $gavekortnr = $gavekortnummer;
        } else {
                $qtxt= "select count(*) as antal from gavekort";
                $r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
                $gavekortnr=$gavekortnummerstart+$r[antal];
        }

	
        while (  findesgavekortnummer($gavekortnr) ) {
                $gavekortnr++;
        }
        return $gavekortnr;
}

function findesgavekortnummer($l_gavekortnummer) { # 20181213
        $findes=FALSE;
        $l_qtxt="select count (*) as antal from gavekort where gavekortnr = '$l_gavekortnummer'";
        $l_r=db_fetch_array($l_q=db_select($l_qtxt,__FILE__ . " linje " . __LINE__));
        return $l_r[antal];
} # endfunc gavekortnummerfindes i nytgavekortnummer

function er_gavekort($varenummer) { // bliver kaldt i itemscan.php, linie 293
#echo "<b>Vn: </b> ";
#        return (NULL);
#        exit;
        $er_gavekort=FALSE;
        $qtxt="select gavekort from varer where varenr ilike '$varenummer'";
        $r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $er_gavekort = $r['gavekort'];
#echo "<tr><td>Er gavekort: ".$er_gavekort."</td></tr>\n";
        return $er_gavekort;
} # endfunc er_gavekort 

function opretgavekort($gavekortnr,$ordrenr,$beloeb) {

	$qtxt="insert into gavekort ( gavekortnr ) values ( '$gavekortnr' )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from gavekort where gavekortnr = '$gavekortnr'";
	$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $gavekortid = $r['id'];
	$qtxt="insert into gavekortbrug ( gavekortid, ordre_id, saldo ) values ( '$gavekortid', '$ordrenr', '$beloeb' )";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	return $opretgavekort;
} # endfunc opretgavekort

function udskrivgavekort($bonnr) {
	$qtxt="select * from pg_tables where tablename='gavekortbrug'";
	if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt="CREATE TABLE gavekortbrug (id serial NOT NULL,gavekort_id integer,ordre_id integer,saldo numeric(15,3),PRIMARY KEY (id))";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	
#	$udskrivgavekort="Hejsa alle sammen";
	$udskrivgavekort="";
	$qtxt="select id from ordrer where fakturanr = '$bonnr'";
        $q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array($q);
        if (is_int($r['id'])) {
            $qtxt="select * from gavekortbrug where ordre_id = '$r[id]'";
            $q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
            while ($r=db_fetch_array($q)) {
                    $gavekortid=$r['gavekortid'];
                    $qtxt="select * from gavekort where id = '$gavekortid'";
                    $r2=db_fetch_array($q2=db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    $udskrivgavekort.=gavekortbon($r2['gavekortnr'], $r['saldo']);
            }
        }
	return $udskrivgavekort;
}

#function gavekortbon($gavekortbeloeb=500,$betalingskortid=2,$gavekortekstra="Jens",$gavekortnr=0) { 
function gavekortbon($gavekortnr,$gavekortbeloeb) {
#       $qtxt="select box5 from grupper where id = '78'";       # 20181029
#       $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#       $gavekorttekst=explode(chr(9),$r['box5']);
        # Bonklipkode
        $gavekortbon= ".................................................\n";
        $gavekortbon.="Gavekorttekst: \n";
#       $gavekortbon.=$gavekorttekst[$betalingskortid]."\n";
#       $gavekortbon.=$gavekortekstra."\n";
        $gavekortbon.="Gavekortnummer: $gavekortnr\n";
        $gavekortbon.="Beloeb: $gavekortbeloeb\n";
        $gavekortbon.=".................................................\n";
        return $gavekortbon;
} # endfunc gavekortbon

function gavekortopslag($gavekortnummer) {
	$gavekortopslag=FALSE;
	return $gavekortopslag;
}

?>
