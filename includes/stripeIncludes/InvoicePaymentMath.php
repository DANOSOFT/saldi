<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentMath.php --- 2026.10.12
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
// 20261007 CL/LH Created: integer øre and Danish dates for invoice payments
//                (trimmed from the period-billing branch's BillingMath).

// 20261012 CL/LH Convert decimal database money to integer øre without floating-point arithmetic.

namespace Saldi\Stripe;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class InvoicePaymentMath
{
    /** A Saldi numeric column (e.g. "1234.5" or "-80") as whole øre. */
    public static function fromDb($value): int
    {
        return self::scaled($value, 2);
    }

    /** Convert a decimal to a signed scaled integer, rounding half away from zero. */
    private static function scaled($value, int $places): int
    {
        if ($value === null || $value === '') return 0;
        $decimal = is_float($value) ? sprintf('%.10F', $value) : trim((string)$value);
        if (preg_match('/^([+-]?)\./', $decimal, $leading)) $decimal = $leading[1] . '0' . substr($decimal, strlen($leading[1]));
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/', $decimal, $m)) throw new InvalidArgumentException('Ugyldigt beløb');
        $fraction = str_pad($m[3] ?? '', $places + 1, '0');
        $units = (int)$m[2] * (10 ** $places) + (int)substr($fraction, 0, $places) + ((int)$fraction[$places] >= 5 ? 1 : 0);
        if (!is_int($units)) throw new InvalidArgumentException('Beløbet er for stort');
        return $m[1] === '-' ? -$units : $units;
    }

    /** Invoice line value in øre, with quantity and discount kept as scaled integers. */
    public static function lineTotal($price, $quantity, $discount): int
    {
        $product = self::fromDb($price) * self::scaled($quantity, 3) * (100000 - self::scaled($discount, 3));
        if (!is_int($product) || abs($product) > PHP_INT_MAX - 50000000) throw new InvalidArgumentException('Fakturalinjen er for stor');
        $ore = intdiv(abs($product) + 50000000, 100000000);
        return $product < 0 ? -$ore : $ore;
    }

    public static function decimal(int $ore): string
    {
        return ($ore < 0 ? '-' : '') . intdiv(abs($ore), 100) . '.' . str_pad((string)(abs($ore) % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 1.234,50 */
    public static function amount(int $ore): string { return number_format($ore / 100, 2, ',', '.'); }

    /** 1.234,50 kr. */
    public static function display(int $ore): string { return self::amount($ore) . ' kr.'; }

    public static function date(string $date): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) throw new InvalidArgumentException('Ugyldig dato');
        return $date;
    }

    /** Y-m-d storage date shown as d-m-Y; '' for an empty or invalid date. */
    public static function danishDate(string $date): string
    {
        $date = substr(trim($date), 0, 10);
        try { self::date($date); } catch (InvalidArgumentException $e) { return ''; }
        return substr($date, 8, 2) . '-' . substr($date, 5, 2) . '-' . substr($date, 0, 4);
    }

    public static function localDate(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('Y-m-d');
    }

    public static function localDateTime(int $timestamp): string
    {
        return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Europe/Copenhagen'))->format('d-m-Y H:i');
    }
}
