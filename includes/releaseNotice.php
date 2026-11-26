<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/releaseNotice.php --- ver 5.1.1 --- 2026-11-24 ---
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
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261124 CL/PS SST-1040 Created. Release markers for the "Saldi er blevet opdateret" popup and bell notification.
//
// HOW TO ANNOUNCE A RELEASE
// -------------------------
// Set the marker to the release date (e.g. '2026-12-03') in the deploy that should be
// announced, and choose its size:
//
//   'main' + 'size' - everyone on the normal Saldi menu (index/main.php):
//       'major' = popup once per user AND a notification in the bell.
//                 Use for: a larger new feature, a new module or tool, a significant
//                 change to the user experience, or changes to how users work in Saldi.
//       'minor' = notification in the bell only (smaller fixes and changes).
//
//   'sager' - users of Sager/Loen (pages using includes/sagsmenu.php), i.e. the
//       scaffolding companies. Those pages have no bell, so this marker only gives the
//       popup: set it only for a major change to Sager/Loen.
//
// Do NOT change a marker for urgent hotfixes or one-off fixes; those are deployed silently.
// '' means nothing is announced. The markers are only compared for equality and are
// independent of $version in includes/version.php (which tracks DB updates).

return array(
	'url'   => 'https://saldi.dk/changelog',
	'main'  => '',
	'size'  => 'minor',
	'sager' => '',
);
