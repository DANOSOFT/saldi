<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/bank_integration/support/run_legacy.php --- ver 5.1.1 --- 2026-11-16 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
// 20261006 CDX/LH Created fixture-based backend acceptance coverage.
// 20261116 CL/LH Load the journal helpers opdater() calls on master (bilagNumber.php, journalSaveLock.php and the others kassekladde.php requires).

require_once __DIR__ . '/../../../../bank_integration/includes/ebClient.php';
require_once __DIR__ . '/EbTestEnv.php';
ebTestConnect();
$journalId = (int)$argv[2];
if ($argv[1] === 'save') {
    // Extract the actual legacy function with PHP tokens, without executing the page's top-level controller.
    $tokens = token_get_all(file_get_contents(CharacterizationEnv::repoRoot() . '/finans/kassekladde.php'));
    $capturing = false;
    $opened = false;
    $depth = 0;
    $code = '';
    foreach ($tokens as $i => $token) {
        if (!$capturing && is_array($token) && $token[0] === T_FUNCTION) {
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (is_array($tokens[$j]) && $tokens[$j][1] === 'opdater') {
                $capturing = true;
            }
        }
        if (!$capturing) {
            continue;
        }
        $code .= is_array($token) ? $token[1] : $token;
        if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
            $depth++;
            $opened = true;
        } elseif ($token === '}') {
            $depth--;
            if ($opened && $depth === 0) {
                break;
            }
        }
    }
    if (!$opened || $depth !== 0) {
        throw new RuntimeException('Could not extract opdater');
    }
    // opdater() relies on the journal helpers kassekladde.php loads at its top (voucher numbers, save lock, history).
    foreach (['journalHistory', 'invoiceReuse', 'saveReplay', 'accountCard', 'bilagNumber', 'journalSaveLock', 'sorting'] as $helper) {
        require_once CharacterizationEnv::repoRoot() . '/finans/kassekladde_includes/' . $helper . '.php';
    }
    eval($code);
    $baseCurrency = 'DKK';
    $egen_kto_id = 0;
    opdater($journalId);
    fwrite(STDOUT, "saved\n");
} elseif ($argv[1] === 'copy') {
    $_POST = ['kladde_id' => $journalId, 'source_id' => (int)$argv[3]];
    require __DIR__ . '/../../../../finans/kassekladde_includes/duplicate_line.php';
} else {
    throw new InvalidArgumentException('Unknown legacy test operation');
}
