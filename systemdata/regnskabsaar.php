<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//
// --- systemdata/regnskabsaar.php --- ver 5.0.0 --- 2026-07-30 --
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------------
// 20150327 CA  Topmenudesign tilføjet                             søg 20150327
// 20161202 PHR Små designændringer
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20210709 LOE - Translated some of the texts
// 20210805 LOE - Updated the title texts
// 20220103 PHR - "Set all" now updates online.php.
// 20220501 PHR - Corrected error in set all.
// 20240524 PHR - Fiscal year can now be deleted.
// 20250503 LOE reordered mix-up text_id from tekster.csv in findtekst()
// 20250903 PHR	Changed 5 year calculation to include months.
// 20260130 PHR - Improved check for empty fiscal year before deletion and used ICONSVG icons.
// 20260730 MJ Tilfoejede Momsperioder-knap; fjernede linket fra finans-sidebaren i main.php
// 20260801 MJ Sat begge knapper til samme bredde (200px)
// 20260814 Sawaneh SST-705 Current fiscal year shows Lukket when bookkeeping is
//                  not allowed (box5) and GET params are int-cast before SQL
// 20260930 Sawaneh check_permissions() moved to includes/std_func.php (roles spec §4.4).
// 20261005 Sawaneh Fiscal years live in Indstillinger » Virksomhed » Regnskabsår (settings redesign 4d, G1.2; audit F1-F3
//                  fixed by the new list); this page only redirects.

@session_start();
$s_id = session_id();
include("../includes/connect.php");
$modulnr = 1;
include("../includes/online.php");
header("Location: settingsSection.php?s=company.fiscal_years&moved=regnskabsaar");
exit;
