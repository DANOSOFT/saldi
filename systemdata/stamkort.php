<?php
// -- ---------systemdata/stamdata.php --- patch 5.0.0 --- 2026-04-24 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 2012.08.21 Tilføjet leverandørservice - PBS
// 2014.11.20 Opdater mastersystem ved ændring af email.
// 2015.01.23 Indhente virksomhedsdata fra CVR via CVRapi - tak Niels Rune https://github.com/nielsrune
// 20150331 CA  Topmenudesign tilføjet søg 20150331
// 2018.12.20 MSC - Rettet isset fejl
// 20190304 Set countryConfig depending on the users permission
// 20210628 LOE Translated some texts to English and Norsk
// 20230530 PHR Employee no is now shown.
// 20230803 LOE Initialized some varibles and made some modifications
// 20260424 PHR Added thisDb to prevent admins updating in the wrong accunt
// 20260820 Sawaneh Added IBAN and SWIFT fields (included on e-invoices when filled in)
// 20261005 Sawaneh G7.1: the employees moved to Indstillinger » Organisation » Ansatte, so this page only redirects (before any
//                  output): ?ansatte=1 to the employee list, everything else to Stamdata (G1.1).

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
include("../includes/online.php");
if (!empty($_GET['ansatte'])) {
	header("Location: settingsSection.php?s=organisation.employees&moved=stamkort");
} else {
	header("Location: settingsSection.php?s=company.data&moved=stamkort");
}
exit;
