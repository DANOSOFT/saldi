<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/takeAway/setup.php ---------- lap 3.7.7----2019.07.09-------
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
// LN 20190709 Make function that sets the "Gem bestilling" site for the take away

include("getOrderShow.php");
include("getOrderFuncs.php");
include("showSaveOrder.php");
include("finishedOrder.php");
include("saveOrder.php");
include("keyboard.php");
include("timeField.php");
include("radioFields.php");
include("helper.php");
include("detailedFinishedTakeAwayOrder.php");
include("deletedOrders.php");

function takeAwaySetup()
{
    global $db;
  	global $printserver;
    global $bruger_id;
    if (isset($_POST['saveOrder']) && $_POST['saveOrder'] == "Gem bestilling") {
        showSaveOrderPage();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "Gem bestilling") {
        handleSavingOrder();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "Slet") {
        handleDeleteOrder();
    } elseif (isset($_POST['submit']) && ($_POST['submit'] == "PrintKitchenReceipt" || $_POST['submit'] == "Print")) {
        printKitchenReceipt();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "Historik") {
        showFinishedOrders();
    } elseif (isset($_POST['submit']) && ($_POST['submit'] == 'Rediger' || $_POST['submit'] == 'Afslut')) {
        $ordreId = handleEditOrDelete();
        $_GET['id'] = $ordreId;
    } elseif (isset($_POST['getOrder']) && $_POST['getOrder'] == "Hent bestilling") {
        showGetOrderPage();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "backToGetOrder") {
        showGetOrderPage();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "showDetailedTakeAwayOrder") {
        showDetailedFinishedOrder();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "receiptReprint") {
        reprintReceipt();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "showDeletedTakeAwayOrders") {
        deletedOrders();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "reviveOrder") {
        reviveOrder();
    } elseif (isset($_POST['submit']) && $_POST['submit'] == "permanentDelete") {
        permanentDeleteOrder();
    } else {
        unset($_SESSION['takeAwayOrderNr']);
    }
}

function permanentDeleteOrder()
{
    $orderId = $_POST['idButton'];
    db_modify("delete from ordrer where ordrenr='$orderId'", __LINE__ . "linje" . __LINE__);
    showDeletedOrders();
}

function reviveOrder()
{
    $orderId = $_POST['idButton'];
    db_modify("update ordrer set status='-1' where ordrenr='$orderId'", __LINE__ . "linje" . __LINE__);
    showGetOrderPage();
}

function deletedOrders()
{
    showDeletedOrders();
}

function reprintReceipt()
{
    if (isPrinterDisabled() == false) {
        $ordrenr = $_POST['orderNr'];
        $pfnavn="../temp/".$db."/".$bruger_id.".txt";
        $fp=fopen("$pfnavn","w");
        $printserver = getprintVariables();
        $query = getOrderFromDatabase($ordrenr, true);
        $header = getShopInfo();
        include("receiptReprint.php");
    }
}

function printKitchenReceipt()
{
    if (isPrinterDisabled() == false) {
        $ordrenr = $_POST['orderNr'];
        $pfnavn="../temp/".$db."/".$bruger_id.".txt";
        $fp=fopen("$pfnavn","w");
        $printserver = getprintVariables();
        $query = getOrderFromDatabase($ordrenr);
        include("kitchenReprint.php");
    }
}

function showDetailedFinishedOrder()
{
    setDetailedFinishedOrderSite();
    exit;
}

function handleEditOrDelete()
{
    unset($_GET['id']);
    unset($_SESSION['takeAwayOrderNr']);
    $ordrenr = $_POST['orderNr'];
    $_SESSION['takeAwayOrderNr'] = $ordrenr;
    $query = db_select("select id from ordrer where ordrenr='$ordrenr'", __LINE__ . "linje" . __LINE__);
    $ordreId = db_fetch_array($query)['id'];
    return $ordreId;
}

function showSaveOrderPage()
{
    $ordreId = $_GET['id'];
    $products = getReceiptProducts($ordreId);
    if (empty($products)) {
        alert("Der er ingen varer på regningen");
    } else {
        $savingSetup = calculateSaveOrderSetup($products);
        saveOrderPage($products, $savingSetup['size'], $savingSetup['info'], $savingSetup['ordre_id']);
        exit;
    }
}

function handleSavingOrder()
{
    $_SESSION['saveOrder'] = "Gem bestilling";
    $printserver = getprintVariables();
    formatDate();
    saveTheOrder();
    if (isPrinterDisabled() == false) {
        $pfnavn="../temp/".$db."/".$bruger_id.".txt";
        $fp=fopen("$pfnavn","w");
        $id = $_POST['takeAwayOrderId'];
        $ordreNr = db_fetch_array(db_select("select ordrenr from ordrer where id='$id'", __FILE__ . "linje" . __LINE__))['ordrenr'];
        include("kitchenPrint.php");
    }
    $id = $_POST['takeAwayOrderId'];
    resetOrder($id);
}

function handleDeleteOrder()
{
    //Insted of deleting the row from ordrer, we update the status field
    $ordrenr = $_POST['orderNr'];
    db_modify("update ordrer set status='-2' where ordrenr='$ordrenr'", __LINE__ . "linje" . __LINE__);
//    db_modify("delete from ordrer where ordrenr='$ordrenr'", __LINE__ . "linje" . __LINE__);
    showGetOrderPage();
    exit;
}

function isPrinterDisabled()
{
    $queryTxt = "select var_value from settings where var_name='deactivateBonprint'";
    $query = db_select($queryTxt, __LINE__ . "linje" . __LINE__);
    $disabledPrinter = db_fetch_array($query)['var_value'];
    return $disabledPrinter;
}



?>
