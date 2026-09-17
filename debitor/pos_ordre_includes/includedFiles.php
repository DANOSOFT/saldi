<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/includedFiles.php ---------- lap 3.7.7----2019.05.13-------
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
// LN 20190510 Move included files here


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");
include("../includes/posmenufunc.php");

include("pos_ordre_includes/gavekortfunk.php"); # 20181220

include("pos_ordre_includes/boxCountMethods/boxCount.php"); #20190219
include("pos_ordre_includes/boxCountMethods/printBoxCount.php"); #20190219
include("pos_ordre_includes/boxCountMethods/boxCountText.php"); #20190219

include("pos_ordre_includes/frontpage/itemTxt.php"); #20190219

include("pos_ordre_includes/report/reportSetup.php");
include("pos_ordre_includes/divFuncs/takeAway/setup.php"); # 20190603

include("pos_ordre_includes/helperFunc.php"); #20190219

include("pos_ordre_includes/posTxtPrint/posTxtPrintFunc.php"); #20190503

include("pos_ordre_includes/divFuncs/box/findBoxSaleFunc.php"); #20190503
include("pos_ordre_includes/divFuncs/box/printBoxTaskFunc.php"); #20190503

include("pos_ordre_includes/divFuncs/drawer/drawerStatusFunc.php"); #20190503
include("pos_ordre_includes/divFuncs/drawer/findDrawer.php"); #20190510
include("pos_ordre_includes/divFuncs/drawer/openDrawFunc.php"); #20190503

include("pos_ordre_includes/divFuncs/receipt/createPosorder.php"); #20190510
include("pos_ordre_includes/divFuncs/receipt/findReceipt.php"); #20190510
include("pos_ordre_includes/divFuncs/receipt/getVatArray.php"); # 20190603
include("pos_ordre_includes/divFuncs/receipt/payment.php"); #20190510
include("pos_ordre_includes/divFuncs/receipt/posvalutaFunc.php"); #20190503
include("pos_ordre_includes/divFuncs/receipt/proformaHandling.php"); # 20190527

include("pos_ordre_includes/divFuncs/settings/changeUser.php"); #20190510
include("pos_ordre_includes/divFuncs/settings/costumerDisplayFunc.php"); #20190503
include("pos_ordre_includes/divFuncs/settings/findStyleFunc.php"); #20190503
include("pos_ordre_includes/divFuncs/settings/updateAccount.php"); #20190510


include("pos_ordre_includes/divFuncs/depositFunc.php"); #20190510
include("pos_ordre_includes/divFuncs/findBalanceFunc.php"); #20190510
include("pos_ordre_includes/divFuncs/itemscan.php"); # 20190215
include("pos_ordre_includes/divFuncs/printWarningMessageFunc.php"); # 20190527
include("pos_ordre_includes/divFuncs/partPayment.php"); # 20190527

include("pos_ordre_includes/cashInventory/cashInventoryFunc.php"); #20190510
include("pos_ordre_includes/cashInventory/cashBoxCountFunc.php"); #20190510
include("pos_ordre_includes/cashInventory/cashBoxAccounting.php"); #20190510

include("pos_ordre_includes/showPosLines/showPosLinesFunc.php"); #20190510

include("pos_ordre_includes/exitFunc/exit.php"); #20190510

?>
