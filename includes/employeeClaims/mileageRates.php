<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/employeeClaims/mileageRates.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/../../restapi/core/Idempotency.php';

/** Convert a nonnegative decimal string to exact minor units. @return int */
function mileage_units($value, int $decimals, int $maximum): int
{
    if (!is_string($value) || !preg_match('/\A(0|[1-9][0-9]{0,8})(?:\.([0-9]{1,' . $decimals . '}))?\z/', $value, $parts)) {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'mileage_invalid']);
    }
    $units = (int)$parts[1] * (10 ** $decimals) + (int)str_pad($parts[2] ?? '', $decimals, '0');
    if ($units > $maximum) {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'mileage_invalid']);
    }
    return $units;
}

/** @return string Exact decimal representation of nonnegative minor units. */
function mileage_decimal(int $units, int $decimals): string
{
    $scale = 10 ** $decimals;
    return intdiv($units, $scale) . '.' . str_pad((string)($units % $scale), $decimals, '0', STR_PAD_LEFT);
}

/** @return array{year:int,rate:?string,rate_low:?string,threshold_km:int|float,configured:bool} */
function mileage_rate(int $year, $connection = null): array
{
    if ($year < 1 || $year > 9999) {
        throw new MobileT2Error('validation_failed', 422, ['reason'=>'year_invalid']);
    }
    $store = new MobileSchema($connection);
    $values = [];
    foreach ($store->rows("SELECT var_name,var_value FROM settings WHERE var_grp='mobile_marken' AND user_id=0 ORDER BY id") as $row) {
        $values[$row['var_name']] = (string)$row['var_value'];
    }
    $result = ['year'=>$year, 'rate'=>null, 'rate_low'=>null, 'threshold_km'=>20000, 'configured'=>false];
    if (!isset($values['km_rate_'.$year], $values['km_rate_low_'.$year])
        || $values['km_rate_'.$year] === '' || $values['km_rate_low_'.$year] === '') {
        return $result;
    }
    try {
        $high = mileage_units($values['km_rate_'.$year], 2, 100000);
        $low = mileage_units($values['km_rate_low_'.$year], 2, 100000);
        $threshold = mileage_units($values['km_threshold_'.$year] ?? '20000', 1, 100000000);
    } catch (MobileT2Error $error) {
        return $result;
    }
    if ($high <= 0 || $low > $high) {
        return $result;
    }
    return ['year'=>$year, 'rate'=>mileage_decimal($high,2), 'rate_low'=>mileage_decimal($low,2),
        'threshold_km'=>$threshold % 10 === 0 ? intdiv($threshold,10) : $threshold / 10, 'configured'=>true];
}

/** Lock the employee's annual allowance in a caller-owned transaction (issue and withdrawal). */
function mileage_lock(string $scope, int $year, $connection = null): void
{
    $connection = $connection ?? ifset($GLOBALS, 'connection');
    if (!$connection instanceof PgSql\Connection || pg_transaction_status($connection) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Mileage allocation requires a caller-owned transaction');
    }
    if ($scope === '' || strlen($scope) > 255 || $year < 1 || $year > 9999) {
        throw new InvalidArgumentException('Invalid mileage scope');
    }
    $store = new MobileSchema($connection);
    $store->execute('SELECT pg_advisory_xact_lock(hashtext(' . $store->literal('saldi:mileage:'.$scope) . '),' . (int)$year . ')');
}

/**
 * Compute trip amounts in date/id order; caller persists allocations before releasing the lock.
 * Preview reads the same rule without taking the issue lock.
 * @param list<array{id:int,trip_date:string,km:string}> $trips
 * @return array{year:int,km_high:string,km_low:string,amount:string,lines:list<array{trip_id:int,date:string,km:string,km_high:string,km_low:string,rate:string,rate_low:string,amount:string}>}
 */
function mileage_allocate(string $scope, int $year, array $trips, $connection = null, bool $preview = false): array
{
    if (!$preview) {
        mileage_lock($scope, $year, $connection);
    }
    $store = new MobileSchema($connection);
    $rate = mileage_rate($year, $connection);
    if (!$rate['configured']) {
        throw new MobileT2Error('not_configured', 422, ['what'=>'km_rate_'.$year]);
    }
    $consumed = $store->rows('SELECT COALESCE(SUM(a.km_high),0)::text AS km FROM mobile_mileage_allocations a
        JOIN mobile_mileage_statements s ON s.id=a.statement_id WHERE a.scope=' . $store->literal($scope)
        . ' AND a.year=' . (int)$year . " AND s.state <> 'withdrawn'")[0]['km'];
    $remaining = max(0, (int)round($rate['threshold_km'] * 10) - mileage_units($consumed,1,PHP_INT_MAX));
    $highRate = mileage_units($rate['rate'],2,100000);
    $lowRate = mileage_units($rate['rate_low'],2,100000);
    usort($trips, static fn(array $a,array $b): int => [$a['trip_date'],(int)$a['id']] <=> [$b['trip_date'],(int)$b['id']]);
    $lines = [];
    $highTotal = $lowTotal = $amountTotal = 0;
    foreach ($trips as $trip) {
        if ((int)substr($trip['trip_date'],0,4) !== $year) {
            throw new InvalidArgumentException('Trip outside allocation year');
        }
        $km = mileage_units($trip['km'],1,20000);
        $high = min($remaining,$km);
        $low = $km-$high;
        $remaining -= $high;
        $amount = intdiv($high*$highRate + $low*$lowRate + 5,10);
        $lines[] = ['trip_id'=>(int)$trip['id'], 'date'=>$trip['trip_date'], 'km'=>mileage_decimal($km,1),
            'km_high'=>mileage_decimal($high,1), 'km_low'=>mileage_decimal($low,1), 'rate'=>$rate['rate'],
            'rate_low'=>$rate['rate_low'], 'amount'=>mileage_decimal($amount,2)];
        $highTotal += $high;
        $lowTotal += $low;
        $amountTotal += $amount;
    }
    return ['year'=>$year,'km_high'=>mileage_decimal($highTotal,1),'km_low'=>mileage_decimal($lowTotal,1),
        'amount'=>mileage_decimal($amountTotal,2),'lines'=>$lines];
}
