<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/ui/SaldiLegacySalesLineTotalsTest.test.php --- ai/mcp-assistant --- 2026.10.08
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
//
// Legacy display arithmetic keeps priced services in sales and credit totals.
//
// 20261005 CDX/LH Created regression coverage for priced service order display.
// 20261008 CL/LH ordrelinjer() calls order_line_amounts(); load it from includes/orders/orderTotals.php.

declare(strict_types=1);

namespace Saldi\Tests\LegacySalesLineTotals;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Round with the legacy half-up convention. @return float */
function afrund($value, $precision)
{
    return round($value, $precision, PHP_ROUND_HALF_UP);
}

/** Unit-price formatting does not affect accumulated totals. @return string */
function formatOrderUnitPrice($value)
{
    return (string) $value;
}

/** Array lookup with the std_func.php semantics order_line_amounts() relies on. @return mixed */
function ifset($arrayOrVar, $key = null, $default = null)
{
    return is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar) ? $arrayOrVar[$key] : $default;
}

/** Danish display formatting does not affect accumulated totals. @return string */
function dkdecimal($value, $precision)
{
    return number_format($value, $precision, ',', '.');
}

final class SaldiLegacySalesLineTotalsTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'service invoice' => [null, 0, 50, 2, 10, 25, '', 90, 22.5, 2],
            'service credit' => [null, 0, 50, -2, 10, 25, '', -90, -22.5, -2],
            'reduced VAT credit' => [null, 0, 50, -2, 10, 10, '', -90, -9, -2],
            'VAT-free service' => [null, 0, 50, -2, 10, 25, 'on', -90, 0, -2],
            'zero-priced service keeps quantity' => [null, 0, 0, 2, 0, 25, '', 0, 0, 2],
            'description remains unpriced' => [null, 0, null, 1, 0, 25, '', 0, 0, 0],
            'product rounding unchanged' => ['P1', 1, 125.005, 2.5, 12.5, 25, '', 273.448, 68.362, 2.5],
        ];
    }

    #[DataProvider('cases')]
    public function testLegacyDisplayKeepsPricedServiceAndProductTotals(
        ?string $varenr,
        int $vare_id,
        ?float $pris,
        float $antal,
        float $rabat,
        float $varemomssats,
        string $momsfri,
        float $expectedSum,
        float $expectedVat,
        float $expectedQuantity
    ): void {
        // Execute the production arithmetic, without loading the page controller or its DB writes.
        $source = file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
        $function = strpos($source, 'function ordrelinjer(');
        $start = strpos($source, '$pris_num =', $function);
        $end = strpos($source, '// Disable fields for OT orders', $start);
        self::assertNotFalse($start);
        self::assertNotFalse($end);
        $body = substr($source, $start, $end - $start);
        if (!function_exists(__NAMESPACE__ . '\\order_line_amounts')) {
            $totals = file_get_contents(__DIR__ . '/../../../includes/orders/orderTotals.php');
            $fnStart = strpos($totals, 'function order_line_amounts(');
            $fnEnd = strpos($totals, "\n}\n", $fnStart);
            self::assertNotFalse($fnStart);
            self::assertNotFalse($fnEnd);
            eval('namespace ' . __NAMESPACE__ . '; ' . substr($totals, $fnStart, $fnEnd + 3 - $fnStart));
        }
        $x = 1;
        $reserveret = [];
        $beskrivelse = 'Service';
        $folgevare = $saet = $ny_pos = 0;
        $samlevare = '';
        $brugsamletpris = $readonly = $procentfakt = $incl_moms = false;
        $kostpris = $sum = $moms = $dbsum = $blandet_moms = 0;
        $procent = 100;
        $m_rabat = $rabatgruppe = 0;
        $rabatart = 'percent';
        $momssats = 25;
        $art = $antal < 0 ? 'DK' : 'DO';
        eval('namespace ' . __NAMESPACE__ . '; ' . $body);
        self::assertEqualsWithDelta($expectedSum, $sum, 0.0000001);
        self::assertEqualsWithDelta($expectedVat, $moms, 0.0000001);
        self::assertEqualsWithDelta($expectedQuantity, $antal, 0.0000001);
    }
}
