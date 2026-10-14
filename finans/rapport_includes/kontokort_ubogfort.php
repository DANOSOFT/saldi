<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- finans/rapport_includes/kontokort_ubogfort.php --- ver 5.0.0 --- 2026-09-30 ---
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
//
// 20260930 CL/SZ SD-699: Created. Entry point for the report type "Kontokort med u-bogført".

include_once(__DIR__ . '/kontokort.php');

/**
 * "Kontokort med u-bogført": the kontokort plus what every journal that is not posted yet will post.
 *
 * finans/rapport.php calls the function named after the report type, so this entry point exists for
 * rapportart=kontokort_ubogfort. kontokort() sees that report type and adds the unposted rows; its paging
 * links go through finans/kontokort_standalone.php with the same rapportart.
 */
function kontokort_ubogfort($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
                            $dato_fra, $dato_til, $konto_fra, $konto_til, $rapportart,
                            $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
                            $simulering, $lagerbev) {
	kontokort($regnaar, $maaned_fra, $maaned_til, $aar_fra, $aar_til,
	          $dato_fra, $dato_til, $konto_fra, $konto_til, 'kontokort_ubogfort',
	          $ansat_fra, $ansat_til, $afd, $projekt_fra, $projekt_til,
	          $simulering, $lagerbev);
}
