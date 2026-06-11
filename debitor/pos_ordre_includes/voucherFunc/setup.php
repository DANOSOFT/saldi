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

function voucherSetup() {
	global $db;
	global $bruger_id;
	if (isset($_SESSION['voucher']) && $_SESSION['voucher'] == "giftCard") {
		unset($_SESSION['voucher']);
		$_SESSION['printedGiftCard'] = true;
		$pfnavn = "../temp/" . $db . "/" . $bruger_id . ".txt";
		$fp = fopen("$pfnavn", "w");
		$printserver = getPrintVariablesForGavekort();
		$masterData = getShopMasterData();
		#$giftCards = getGiftCardInfo();
		$voucherId = $_POST['modtagetvouchernummer'];
		$payed = $_POST['sum'];

		if (isset($voucherId) && is_int($voucherId)) {
			$queryTxt = db_fetch_array(db_select("select id from voucher where barcode='$voucherId'", __FILE__ . "linje" . __LINE__));
			$gk_id = $queryTxt['id'];
			$qtxt = "select amount,vat from voucheruse where voucher_id='$gk_id'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . "linje" . __LINE__));
			$gkAmount = $r['amount'];
			$gkvat = $r['vat'];
			print "Gavekort ID: " . $voucherId . ", beløb på gavekort: " . $gkAmount+$gkVat . ", betalt: " . $payed . "<br>";
			echo '<pre>'; print_r($_POST); echo '</pre>';
			if ($gkAmount < $payed) {
				alert("Der står ikke nok på det valgte gavekort");
			} else {
				$gkNewAmount = $gkAmount - $payed;
				#alert("amount: " . $gkNewAmount . ", id: " . $voucherId);
				$qtxt = "update voucheruse set amount='$gkNewAmount' where voucher_id='$voucherId'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				//include("voucherReceipt.php");
			}
		}
	}
	//handleGiftCardSession();
}
/*
function onAmountGkPay($orderId, $betaling) {
	global $betvaluta,$betvalkurs;

	if (($betaling == "Gavekort" ||	$betaling == "Gavekort på beløb") && !isset($_POST['giftcardNumber'])) {
		echo "<center>
			<div>
				<div>
					<h2>
						Du skal nu indtaste nummeret på det gavekort nok du vil betale $_POST[sum] med .
					</h2>
				</div>
				<form name='pos_ordre' action='pos_ordre.php' method='post'>
					<input type=\"text\" id='getGiftcardNumber' name=\"giftcardNumber\" placeholder='fx 11'>
					<input type=\"submit\" value=\"Indtast\">
					<input name='fokus' value='$_POST[fokus]' style='display: none;'>
					<input name='pre_bordnr' value='$_POST[pre_bordnr]' style='display: none;'>
					<input name='momssats' value='$_POST[momssats]' style='display: none;'>
					<input name='leveret' value='$_POST[leveret]' style='display: none;'>
					<input name='varenr_ny' value='$_POST[varenr_ny]' style='display: none;'>
					<input name='betvaluta' value='$_POST[betvaluta]' style='display: none;'>
					<input name='sum' value='$_POST[sum]' style='display: none;'>
					<input name='betaling' value='$_POST[betaling]' style='display: none;'>
<!--			<input name='modtaget' value='$_POST[modtaget]' style='display: none;'> -->
					<input name='price' value='$_POST[modtaget]' style='display: none;'>
				</form>
			</div>
		</center>";
		print "<script> 
			window.onload = function() { 
			$('#getGiftcardNumber').focus(); 
			}
		</script>";
		#echo "Post array: <br>";
		#echo '<pre>'; print_r($_POST); echo '</pre>';
		#echo "Get array: <br>";
		#echo '<pre>'; print_r($_GET); echo '</pre>';
		#echo "Id: $orderId <br>";
		#echo "Betaling: $betaling <br>";
		exit(0);
	} elseif (isset($_POST['giftcardNumber']) && $_POST['giftcardNumber'] >= '0') {
			include ("../debitor/pos_ordre_includes/voucherFunc/useVoucher.php");
			$amount = useVoucher($orderId)*1;
		if ($amount && $_COOKIE['giftcard']) {
			$qtxt="insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs) values ";
			$qtxt.="('$orderId','$betaling','$amount','$betvaluta','100')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	} elseif ($betaling == "Gavekort" || $betaling == "Gavekort på beløb") {
		alert ("Gavekort nummer ikke angivet");
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
		exit;
	}	
}

function handleGiftcard($orderId) {
	
	$gkNb = $_POST['giftcardNumber'];
	$query = db_select("select * from voucher where barcode='$gkNb'", __FILE__ . "linje" . __LINE__);
	$gkId = (int)db_fetch_array($query)['id'];
	if (!is_int($gkId)) {
		#alert("Der eksisterer ikke et gavekort med nummeret " . $gkNb . ", gk id: " . $gkId);
		alert("Der eksisterer ikke et gavekort med nummeret " . $gkNb);
		$_COOKIE['giftcard'] = false;
	} else {
		$price = str_replace('q','',$_POST['price']);
		$sum = $_POST['sum'];
		if (!$price) $price=$sum;
		$qtxt="select sum (amount) as amount, sum (vat) as vat from voucheruse where voucher_id='$gkId'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . "linje" . __LINE__));
		$amount = $r['amount'];
		$vat    = $r['vat'];
		$newAmount = $amount + $vat - $price;
		echo "$newAmount = $amount + $vat - $price<br>";
		if ($newAmount < 0 && $gkNb) {
			$tmp = $amount+$vat;
			#alert("Der står ikke nok på gavekortet, amount: " . $amount . ", pris: " . $price . ", gk id: " . $gkId);
			alert("Der står ikke nok på gavekortet, stående beløb: " . $tmp );
			$_COOKIE['giftcard'] = false;
		} elseif ($gkNb) {
			#alert("Betalingen bliver gennemført, pris: " . $price . ", gavekort beløb: " . $amount . ", gk id: " . $gkId);
			alert("Betalingen bliver gennemført");
			$subAmount = -1 * $price;
			$subVat    = afrund($subAmount / (($amount+$vat)/$vat),2);
			$subAmount-= $subVat;
			$qtxt = "insert into voucheruse (voucher_id, order_id, amount, vat) values ('$gkId', '$orderId', '$subAmount', '$subVat')";
			db_modify($qtxt, __FILE__ . "linje" . __LINE__);
			#db_modify("update voucheruse set amount='$newAmount' where voucher_id='$gkId'", __FILE__ . "linje" . __LINE__);
			$_COOKIE['giftcard'] = true;
			if ($price < $sum) return $price;
		}
	}
	return 0;
}
*/

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
                $_SESSION['voucher'] = "giftCard";
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



function voucherstatus($id,$konto_id) {
	if ($_POST['voucherstatus'] == "status") {
		$barcode = $_POST['giftcardNumber'];
		$qtxt="select id,item_id from voucher where barcode = '$barcode'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$gkId   = $r['id'];
		$itemId = $r['item_id'];
		#$gkUses = db_fetch_array(db_select("select amount from voucheruse where voucher_id = '$gkId'",__FILE__ . " linje " . __LINE__));
		if ($gkId) {
			$qtxt="select beskrivelse from varer where id = '$itemId'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$gkName = $r['beskrivelse'];
			$qtxt="select sum (amount+vat) as amount from voucheruse where voucher_id = '$gkId'";
			$gkUses = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$amount = $gkUses[0];
			$amount = number_format($amount, 2, ',', '.');
			$alertTxt="$gkName #" . $barcode . ": Saldo: $amount";
			#			print "<h2>Gavekort status</h2>\n";
		#print "<p> Gavekort id: " . $gkId . " </p>\n ";
#			print "<p> Gavekort nummer: " . $barcode . " </p>\n ";
#			print "Der står følgende beløb på kortet: " . $amount . " Dkk";
		} else {
			$alertTxt="Gavekort nummer: " . $barcode . ": ikke fundet";
#			print "<h2>Gavekort status</h2>\n";
		#print "<p> Gavekort id: " . $gkId . " </p>\n ";
#			print "<p> Gavekort nummer: " . $barcode . " eksisterer ikke</p>\n ";
		}
		alert($alertTxt);
	} else {
#		print "<h2>Gavekort status</h2>\n";
		print "<p>Indtast nummer:</p>\n";
		print "<form name=\"voucherstatus\" action=\"pos_ordre.php\" method=\"post\" autocomplete=\"off\">\n";
		print "Gavekort nummer: <input id='giftcardStatus' name=\"giftcardNumber\" type=\"text\" /><br />\n";
		print "<input value=\"status\" type=\"submit\" name=\"voucherstatus\"/>\n";
		print "</form>\n";
		print "<script>window.onload = function() { $('#giftcardStatus').focus(); } </script>";
	}
}
/*
function printVoucher_notUsed($orderId,$barcode) { #Moved to printVoucher.php 
	global $bruger_id,$db_id,$id,$printserver;

	$qtxt = "select firmanavn,addr1,addr2,postnr,bynavn,cvrnr,tlf from adresser where art='S'";
	$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$myName      = $r['firmanavn'];
	$myAddr1     = $r['addr1'];
	$myAddr2     = $r['addr2'];
	$myZip			=  $r['postnr'];
	$myCity			=  $r['bynavn'];
	$myVatNo		=  $r['cvrnr'];
	$myPhone		=  $r['tlf'];

	$v=0;
	if ($orderId) {
		$qtxt  = "select voucher_id,sum(amount+moms) as amount from voucheruse where order_id='$orderId' group by voucher_id";
		$q     = db_select($qtxt,__FILE__ . " linje " . __LINE__));
		while ($r = db_fetch_array($q)) {
			$voucherId[$v] = $r['voucher_id'];
			$amount[$v]    = $r['amount'];
			$v++;
		}
		for ($v=0;$v<count($voucherId[$v]);$v++) {
			$qtxt  = "select voucher.barcode,voucher.item_id,varer.beskrivelse from voucher,ordrelinjer where ";
			$qtxt  = "voucher.id='$voucherId[$v]' and orderlinjer.vare.id=voucher.item_id and ordrelinjer.ordre_id = voucher.order_id";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$itemId[$v]   = $r['item_id'];
			$barcode[$v]  = $r['barcode'];
			$itemName[$v] = $r['beskrivelse'];
		}
	} elseif ($barode[0]) {
		$qtxt   = "select * from voucher where barcode='$barode[0]'";
		$r      = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$itemId[$v]    = $r['item_id'];
		$voucherId[$v] = $r['id'];
	}
	if (file_exists("voucherPrint_$db_id.php")) include("pos_print/voucherPrint_$db_id.php");
	else include('pos_print/voucherPrint.php');
}
*/



function vouchersale() {       # 20181030
		#if ($_POST['vouchersale'] == "Opret") {
            #		echo '<pre>'; print_r($_POST); echo '</pre>';
            #		$amount = $_POST['gavekortbeloeb'];
            #	$cardId = $_POST['gavekortnr'];
            	#$productId = $_POST['vareid_ny'];
            #	echo "Beløb: " . $amount . ", gavekort id: " . $cardId . ", vare id: " . $productId;
            #		createVoucher($cardId, $amount);
		#} else {
            $qtxt = "select count (*) as numbers from voucher";
            $rows = db_fetch_array(db_select($qtxt,__FILE__ . "linje" . __LINE__))['numbers'] + 1;

            print "<table><tbody>\n";
            print "<h2>Gavekortsalg</h2>\n";
            #print "<p>Gavekortsalg: Gavekortnummer ".$rows."</p>\n";
            print "<p>Gavekortsalg:</p>\n";

            #print "<form name=\"vouchersale\" action=\"pos_ordre.php?id=$id&kasse=$kasse&gavekorttype=$gavekorttype&beloeb=$gavekortbeloeb&vareid_ny=4\" method=\"post\" autocomplete=\"off\">\n";
            print "Beløb: <input id='giftcardAmount' name=\"gavekortbeloeb\" type=\"text\" /><br />\n";
            print "<input id='giftcardNumber' name=\"gavekortnr\" value=\"$rows\" type=\"text\" style='display: none;' /><br />\n";
            #print "<input name=\"vareid_ny\" type=\"text\" value=\"3\" style='display: none;'/><br />\n";
            print "<input value=\"Opret\" type=\"submit\" name=\"vouchersale\" id='createGiftcard' />\n";
            #print "</form>\n";
            print "</tbody></table></td>\n";
            print "</tr></tbody></table>\n";
            print "<a href=\"pos_ordre.php?id=0&amp;giftcardAntal=1&amp;giftcardPris=200&amp;sidemenu=4&amp;vare_id=0&amp;vare_id_ny=4&amp;varenr_ny=&amp;pris_ny=&amp;folger=0&amp;fokus=varenr_ny&amp;bordnr=&amp;lager=&amp;tilfravalgNy=\">";
            print "<input type=\"button\" id='buyGiftcard' style=\"display: none;\" value=\"Gavekort\" wtx-context=\"C7E49D74-169C-40F9-83FE-EE280F0D3F10\">";
            print "</a>";
            print "<script> 
                        window.onload = function() { 
                            $('#giftcardAmount').focus(); 
                        }
                    </script>";
?>
            <script>
                $("#createGiftcard").click(function () {
                    $("#buyGiftcard").click();
                    let amount = $("#giftcardAmount").val();
                    let number = $("#giftcardNumber").val();
                    createCookie("giftcardValue", amount, "1");
                    createCookie("giftcardNumber", number, "1");
                    createCookie("giftcardTime", true, "1");
                });

                function createCookie(name, value, days) {
                    let expires;
                    if (days) {
                        const date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = "; expires=" + date.toGMTString();
                    } else {
                        expires = "";
                    }
                    document.cookie = escape(name) + "=" + escape(value) + expires + "; path=/";
                }
            </script>
<?php
    #}
        return;
} #endfunc vouchersale

function checkVoucherBuy($nbGc)
{
    #alert("check, noG: " . $nbGc);
    $cookieCheck = isset($_COOKIE['giftcardValue']) && isset($_COOKIE['giftcardNumber']) && isset($_COOKIE['giftcardTime']);
    if (is_numeric($nbGc) && $nbGc == 0 && $cookieCheck == true) {
        #alert("Unsetting cookies, number: " . $nbGc);
        #unset($_COOKIE['giftcardValue']);
        #unset($_COOKIE['giftcardNumber']);
        #unset($_COOKIE['giftcardTime']);
        #$_COOKIE["giftcardTime"] = false;
        #$_COOKIE["giftcardValue"] = false;
        #$_COOKIE["giftcardNumber"] = false;
        ?>
            <script>
                createCookie("giftcardTime", "", -1);
                createCookie("giftcardNumber", "", -1);
                createCookie("giftcardValue", "", -1);
                function createCookie(name, value, days) {
                    var expires;

                    if (days) {
                        var date = new Date();
                        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                        expires = "; expires=" + date.toGMTString();
                    } else {
                        expires = "";
                    }
                    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + expires + "; path=/";
                }
            </script>
        <?php
    }
}

function createVoucher_NotUsed($orderLineId) { "moved to createVoucher.php<br>";
	global $bruger_id,$db_id,$id,$printserver;
	
	if (!$printserver) {
		$qtxt = "select box3,box4,box5,box6 from grupper where art = 'POS' and kodenr='2'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
		$x=$kasse-1;
		$tmp=explode(chr(9),$r['box3']);
		$printserver=trim($tmp[$x]);
		if (!$printserver)$printserver='localhost';
		if ($printserver=='box' || $printserver=='saldibox') {
			$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
			if ($fp=fopen($filnavn,'r')) {
				$printserver=trim(fgets($fp));
				fclose ($fp);
			}
		}
	}
	
#    $lastOrderId=db_fetch_array(db_select("select id from ordrer order by id desc",__FILE__ . " linje " . __LINE__))[0];
#    $lastOrderId += 1;

	$qtxt = "select vare_id,ordre_id,antal,pris,momsfri,momssats,beskrivelse from ordrelinjer where id='$orderLineId'";
	$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$itemId      = $r['vare_id'];
	$orderId     = $r['ordre_id'];
	$amount      = $r['pris'];
	$description = $r['beskrivelse'];
	$qty         = $r['antal'];
	($r['momsfri'])?$vat=0:$vat=afrund($amount*$r['momssats']/100,2);
 	$qtxt = "select firmanavn,addr1,addr2,postnr,bynavn,cvrnr,tlf from adresser where art='S'";
	$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$myName      = $r['firmanavn'];
	$myAddr1     = $r['addr1'];
	$myAddr2     = $r['addr2'];
	$myZip			=  $r['postnr'];
	$myCity			=  $r['bynavn'];
	$myVatNo		=  $r['cvrnr'];
	$myPhone		=  $r['tlf'];
	for ($addBc=1;$addBc<=$qty;$addBc++) {
		$barcode = rand();
		$qtxt="insert into voucher (item_id,barcode) values ('$itemId','$barcode' )";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from voucher where barcode = '$barcode'";
		$r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$voucherId = $r['id'];
		$barcode = $voucherId . date('ymd');
		$qtxt="update voucher set barcode = '$barcode' where id = '$voucherId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="insert into voucheruse ( voucher_id, order_id, amount, vat ) values ( '$voucherId', '$orderId', '$amount', '$vat' )";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);

		if (file_exists("voucherPrint_$db_id.php")) include("pos_print/voucherPrint_$db_id.php");
		else include('pos_print/voucherPrint.php');
#		ob_flush();
#		sleep(1);
	}
}

function isVoucher($itemNo) { // bliver kaldt i itemscan.php, linie 293

    $isVoucher = NULL;
    $qtxt = "select id from varer where lower(varenr) = '". db_escape_string(strtolower($itemNo)) ."'";
    $r = db_fetch_array(db_select($qtxt,__FILE__ . "linje" . __LINE__));
		if ($itemId = $r['id']) {
			$qtxt = "select var_value from settings where var_name='voucherItems'";
			$r = db_fetch_array(db_select($qtxt,__FILE__ . "linje" . __LINE__));
			$voucherItems = explode(chr(9),$r['var_value']);
			for ($x=0;$x<count($voucherItems);$x++) {
				if ($itemId == $voucherItems[$x]) $isVoucher = $itemId;
			}
		}
/*
    ?>
        <script>
            var number = <?php echo json_encode($rows, JSON_HEX_TAG); ?>;
           // createCookie("giftcardValue", amount, "1");
            createCookie("giftcardNumber", number, "1");
            createCookie("giftcardTime", true, "1");
            function createCookie(name, value, days) {
                let expires;
                if (days) {
                    const date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toGMTString();
                } else {
                    expires = "";
                }
                document.cookie = escape(name) + "=" + escape(value) + expires + "; path=/";
            }
        </script>
    <?php
*/  
#echo "<b>Vn: </b> ";
#        return (NULL);
#        exit;
 #   if ($isVoucher) echo "er_gavekort";
/*
    $er_gavekort=FALSE;
    $qtxt="select gavekort from varer where varenr ilike '$varenummer'";
    $r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
    $er_gavekort = $r['gavekort'];
#echo "<tr><td>Er gavekort: ".$er_gavekort."</td></tr>\n";
*/
	return $isVoucher;
} # endfunc er_gavekort

function nytgavekortnummer($gavekortnummer) {
		echo "nytgavekortnummer:";
    $gavekortnummerstart=10000;
        if ( $gavekortnummer > 0 ) {
                $voucherNo = $gavekortnummer;
        } else {
                $qtxt= "select count(*) as antal from gavekort";
                $r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__));
                $voucherNo=$gavekortnummerstart+$r[antal];
        }

	
        while (  findesgavekortnummer($voucherNo) ) {
                $voucherNo++;
        }
        return $voucherNo;
}

function findesgavekortnummer($l_gavekortnummer) { # 20181213
				echo "findesgavekortnummer:";
        $findes=FALSE;
        $l_qtxt="select count (*) as antal from gavekort where gavekortnr = '$l_gavekortnummer'";
        $l_r=db_fetch_array($l_q=db_select($l_qtxt,__FILE__ . " linje " . __LINE__));
        return $l_r[antal];
} # endfunc gavekortnummerfindes i nytgavekortnummer


function udskrivgavekort($bonnr) {
	echo "udskrivgavekort:";
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
                    $voucherId=$r['gavekortid'];
                    $qtxt="select * from gavekort where id = '$voucherId'";
                    $r2=db_fetch_array($q2=db_select($qtxt,__FILE__ . " linje " . __LINE__));
                    $udskrivgavekort.=gavekortbon($r2['gavekortnr'], $r['saldo']);
            }
        }
	return $udskrivgavekort;
}

#function gavekortbon($gavekortbeloeb=500,$betalingskortid=2,$gavekortekstra="Jens",$voucherNo=0) { 
function gavekortbon($voucherNo,$gavekortbeloeb) {
#       $qtxt="select box5 from grupper where id = '78'";       # 20181029
#       $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#       $gavekorttekst=explode(chr(9),$r['box5']);
        # Bonklipkode
        $gavekortbon= ".................................................\n";
        $gavekortbon.="Gavekorttekst: \n";
#       $gavekortbon.=$gavekorttekst[$betalingskortid]."\n";
#       $gavekortbon.=$gavekortekstra."\n";
        $gavekortbon.="Gavekortnummer: $voucherNo\n";
        $gavekortbon.="Beloeb: $gavekortbeloeb\n";
        $gavekortbon.=".................................................\n";
        return $gavekortbon;
} # endfunc gavekortbon

function gavekortopslag($gavekortnummer) {
	echo "gavekortopslag:";
	$gavekortopslag=FALSE;
	return $gavekortopslag;
}

?>
