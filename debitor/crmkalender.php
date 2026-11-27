<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/crmkalender.php --- ver 5.1.1 --- 2026.11.13 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261110 CDX/ASR Enforce CRM read/write on legacy history and CRM routes, including GET mutations.
// 20261110 CDX/ASR Route old CRM bookmarks to the current CRM and refuse retired mutations.
// 20261113 CDX/NTR Align the file header with the release version and history conventions.

$crmLegacyTab = 'cal';
require __DIR__ . '/../includes/crmLegacyRoute.php';
