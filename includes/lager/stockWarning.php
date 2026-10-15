<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/lager/stockWarning.php --- ver 5.0.0 --- 2026.10.05
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
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.

require_once __DIR__ . '/../std_func.php';

/**
 * The product minimum wins when positive; otherwise use the tenant's standard minimum.
 * @param array{min_lager?:numeric-string|float|int|null} $item Product fields.
 * @return float Effective minimum, including a zero or negative standard as on the web.
 */
function stock_warning_minimum(array $item, float $standardMin): float
{
    $minimum = (float)ifset($item, 'min_lager', 0);
    return $minimum > 0 ? $minimum : $standardMin;
}

/**
 * Shared web and scanner rule; the web passes stock after the proposed sale.
 * @param array{min_lager?:numeric-string|float|int|null} $item Product fields.
 * @return bool True only for tracked stock strictly below the effective minimum.
 */
function stock_warning_evaluate(array $item, float $stock, float $standardMin, bool $tracked): bool
{
    return $tracked && $stock < stock_warning_minimum($item, $standardMin);
}
