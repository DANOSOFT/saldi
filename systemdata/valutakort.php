<?php
// -------------systemdata/valutakort.php-----patch 4.1.1 ----2025-05-07--
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
// ----------------------------------------------------------------------------
// 20130513 - Opdateret liste over valutakoder
// 20150313 CA  Topmenudesign tilføjet                             søg 20150313
// 20150327 CA  Dansk valutakode ændret DKR -> DKK                søg 20150327d
// 20150327 CA  Valutakoder opdateret fra ISO 4217 samt tilføjet XBT Bitcoin søg 20150327v
// 20160116	PHR	Kursgevinst / tab bogføres ved kursændringer og kursændringer blokeres hvis der er bogført efter anført dato søg 20160116
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20210706 LOE - Translated some  texts
// 20210708 LOE - Added this variable for javascript dialog box when ret is clicked and also added redirection to ../valuta.php
// 20210802 LOE - Translated title and alert texts
// 20220614 MSC - Implementing new design
// 20350507 PHR - PHP 8
// 20260911 MJ SST-769 Added an explicit "save rate without posting" action. The existing button
//             still saves and posts the adjustment; the new one writes rate history only, with no
//             transaktioner rows and no kontoplan change. Cast $_GET kodenr/id, which reached SQL raw.
// 20260914 CDX/LH Recalculate account balances only after posting a currency adjustment.
 
// 20261005 Sawaneh Currencies and rates live in Indstillinger » Finans » Valuta (settings redesign 4d, G2.3; audit V2-V9
//                  fixed there, a rate change shows its postings before they are booked); this page only redirects.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 2;
include("../includes/online.php");
$cur = isset($_GET['kodenr']) ? (int) $_GET['kodenr'] : 0;
header("Location: settingsSection.php?s=finance.currencies&moved=valutakort" . ($cur > 0 ? "&cur=$cur#tbl-rates-h" : ""));
exit;
