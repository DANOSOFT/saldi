<?php
// -- -------------systemdata/valuta.php------------- ver 4.1.1 -- 2026-01-22 --
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
// Copyright (c) 2003-2026 Saldi.DK ApS
// ----------------------------------------------------------------------------
// 20150313 CA  Topmenudesign tilføjet                             søg 20150313
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20210706 LOE std_func.php missing file included and also translated some of the texts
// 20260122 PHR - Restyled page with modern design, added POS checkbox
// 20261005 Sawaneh Currencies live in Indstillinger » Finans » Valuta (settings redesign 4d, G2.3; audit V1, V10 gone with
//                  the new page); this page only redirects.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 2;
include("../includes/online.php");
header("Location: settingsSection.php?s=finance.currencies&moved=valuta");
exit;
