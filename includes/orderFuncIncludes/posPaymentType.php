<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/orderFuncIncludes/posPaymentType.php ---ver 5.1.1 ----2026-11-25 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// -----------------------------------------------------------
// 20261125 CL/SS MB-49 Created: pos_payment_is_card(), shared by afslut()'s card-0 refusal and bogfor_nu()'s nothing-posted checks.

/**
 * Whether a POS payment type is a card, which must post on a card account.
 * Cash under either name ('Kontant', or 'Cash' / 'Cash on amount' on a Swiss till), on account ('Konto'),
 * and a pending terminal row ('!') are not.
 */
function pos_payment_is_card($paymentType): bool {
	$type = strtolower(trim((string)$paymentType));
	return $type !== '' && !in_array($type, array('kontant', 'konto', 'cash', 'cash on amount', '!'), true);
}
