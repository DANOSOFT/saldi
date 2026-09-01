<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opdat_4.3.php --- patch 5.0.0 --- 2026-09-01 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2022-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260702 NTR Initial version of opdat_4.3.php with update steps for version 4.3.0.
// 20260721 CL/SZ Added pool_files.norm_amount column + index + one-time backfill, and a
//                 one-time guarded attempt at CREATE EXTENSION pg_trgm, for the Bilagsmatch
//                 scoring engine rewrite.
// 20260727 NTR Removed current version parameter, as it was incorrect and resulted in no update.
//              opdat_func now automatically fetches the version from the database.
// 20260901 PHR Movet context to opdat_4.2.php
if (!function_exists('opdat_4_3')) {
	function opdat_4_3($majorNo, $subNo, $fixNo){
	return ;
}}
?>
