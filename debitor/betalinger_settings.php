<?php
// --- debitor/betalinger_settings.php --- Payment Date Settings ---
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
// Copyright (c) 2003-2023 saldi.dk aps
// -----------------------------------------------------------------------------------
// 20261002 Sawaneh Retired (settings redesign G2.5): the payment days live under Finans → Kassekladde & betalinger.
//                  The links in the payment lists still point here, so this page only redirects.

header('Location: ../systemdata/settingsSection.php?s=finance.cash_journal&moved=betalinger#sub-due');
exit;
