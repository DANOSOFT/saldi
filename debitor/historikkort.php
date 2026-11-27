<?php
// --- debitor/historikkort.php --- ver 5.1.0 --- 2026.11.10 ---
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
// 20190213 MSC - Rettet isset fejl og db fejl + rettet topmenu design til
// 20190215 MSC - Rettet topmenu design
// 20210728 LOE - Updated some texts with translated ones 
// 20220719 MSC - Implementing new design
// 20241009 MMK - Added datepicker functionalaty
// 20250808 PHR - Added $id to returside 
// 20251121 LOE - Modified icons to SVG format and buttons to fit the new design
// 20260904 Sawaneh WP-1.3c: luk.php returside now set on the popup=1 request flag, not the popup preference
// 20261101 CL/SZ MB-129 Saving a note no longer fails when a contact has an apostrophe in the name: the posted names are escaped before the lookup.
// 20261110 CDX/ASR Enforce CRM read/write on legacy history and CRM routes, including GET mutations.
// 20261110 CDX/ASR Route old CRM bookmarks to the current CRM and refuse retired mutations.

$crmLegacyTab = 'cust';
require __DIR__ . '/../includes/crmLegacyRoute.php';
