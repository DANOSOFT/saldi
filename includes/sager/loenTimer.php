<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/sager/loenTimer.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Preserve the web modification trail for bound seddel inserts.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/../db_query.php';
require_once __DIR__ . '/../std_func.php';

/** @return \PgSql\Connection Tenant connection supplied by the page or EngineContext. */
function loen_timer_connection(): \PgSql\Connection
{
    $connection = ifset($GLOBALS, 'connection');
    if (!$connection instanceof \PgSql\Connection) {
        throw new LogicException('Time entries require a selected PostgreSQL tenant');
    }
    return $connection;
}

/**
 * Execute bound SQL without the legacy print/exit error boundary.
 * @param list<int|float|string|null> $params
 * @param ?string $source Legacy page source for the trail; defaults to this engine file.
 * @return list<array<string,string|null>>
 */
function loen_timer_query(string $sql, array $params = [], ?string $source = null): array
{
    $connection = loen_timer_connection();
    if (!pg_send_query_params($connection, $sql, $params)) {
        db_modify_query_trail($connection, $sql, $params, $source ?? __FILE__, false, pg_last_error($connection));
        throw new RuntimeException('Time entry database command failed');
    }
    $result = pg_get_result($connection);
    while (pg_get_result($connection) !== false) {
    }
    $ok = $result !== false && in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true);
    db_modify_query_trail($connection, $sql, $params, $source ?? __FILE__, $ok,
        $ok ? '' : ($result ? pg_result_error($result) : pg_last_error($connection)));
    if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
        throw new RuntimeException('Time entry database command failed');
    }
    return pg_result_status($result) === PGSQL_TUPLES_OK ? pg_fetch_all($result) : [];
}

/**
 * Allocate the next number under a transaction-scoped tenant lock.
 * The caller must insert the seddel before committing; allocation alone does not reserve a number.
 * @return int
 */
function loen_next_number(): int
{
    if (pg_transaction_status(loen_timer_connection()) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Time entry number allocation requires an open transaction');
    }
    loen_timer_query("SELECT pg_advisory_xact_lock(hashtext('saldi:loen_nummer'))");
    $rows = loen_timer_query('SELECT COALESCE(MAX(nummer),0) + 1 AS nummer FROM loen');
    return (int)$rows[0]['nummer'];
}

/**
 * Preserve tjek_fordeling's inclusive trainee cutoff and its original month rollover arithmetic.
 * The employee id is retained for the shared engine signature; trainee identity is caller-resolved.
 * @return float
 */
function loen_fordeling(int $ansatId, ?string $startdate, string $loendate, bool $trainee): float
{
    if (!$trainee || !$startdate) {
        return 100.0;
    }
    $rows = loen_timer_query("SELECT box5 FROM grupper WHERE art='loen'");
    $parts = explode(chr(9), (string)ifset($rows, [0,'box5'], ''));
    $months = (float)ifset($parts, 0, 0);
    $percentage = (float)ifset($parts, 1, 0) * 0.8333333;
    [$year, $month, $day] = array_map('intval', explode('-', $startdate));
    $month += $months;
    if ($month > 12) {
        $year++;
        $month -= 12;
    }
    $cutoff = strtotime($year . '-' . $month . '-' . $day);
    return strtotime($loendate) <= $cutoff ? $percentage : 100.0;
}

/**
 * Read the web's overtime and hour-type settings without default-setting writes.
 * @return array{
 *   overtime_50:string,
 *   overtime_100:string,
 *   hour_types:array<string,array{label:string,rate:string}>
 * }
 */
function loen_rates(): array
{
    $rows = loen_timer_query("SELECT box8 FROM grupper WHERE art='loen'");
    $box = (string)ifset($rows, [0,'box8'], '');
    $overtime = $box === '' ? ['59.18','118.35'] : explode(chr(9), $box);
    $rates = [
        'overtime_50' => (string)(is_numeric(ifset($overtime, 0)) ? $overtime[0] : 0),
        'overtime_100' => (string)(is_numeric(ifset($overtime, 1)) ? $overtime[1] : 0),
        'hour_types' => [],
    ];
    foreach (loen_timer_query("SELECT var_name,var_description,var_value FROM settings WHERE var_grp='casePayment'") as $row) {
        $rates['hour_types'][$row['var_name']] = [
            'label' => (string)$row['var_description'],
            'rate' => (string)$row['var_value'],
        ];
    }
    return $rates;
}

/**
 * Compute only the timer wage and overtime rule, excluding shed, km and mentor.
 * Retain calculation precision: loen.sum has three decimals and the legacy web does not round to ore here.
 * An unknown nonempty hour type keeps current_rate, as the web's rate-selection loop does.
 * @param array{loen:int|float|string,current_rate?:int|float|string} $employee
 * @param array{overtime_50:string,overtime_100:string,hour_types:array<string,array{label:string,rate:string}>} $rates
 * @return array{rate:string,amount:string}
 */
function loen_timer_amount(array $employee, string $hours, string $t50, string $t100, ?string $hourType, array $rates): array
{
    $rate = $hourType
        ? ifset($rates, ['hour_types',$hourType,'rate'], ifset($employee, 'current_rate', 0))
        : str_replace(',', '.', (string)ifset($employee, 'loen', 0));
    $rate = is_numeric($rate) ? (float)$rate : 0.0;
    $hours = is_numeric($hours) ? (float)$hours : 0.0;
    $t50 = is_numeric($t50) ? (float)$t50 : 0.0;
    $t100 = is_numeric($t100) ? (float)$t100 : 0.0;
    $amount = $rate * $hours + (float)$rates['overtime_50'] * $t50 + (float)$rates['overtime_100'] * $t100;
    return ['rate' => (string)$rate, 'amount' => (string)$amount];
}

/**
 * Validate a service entry without changing the web's permissive form-save checks.
 * @param array{date?:mixed,task_id?:mixed,text?:mixed,hours?:mixed,overtime_50?:mixed,overtime_100?:mixed} $entry
 * @return list<'date_missing'|'task_required'|'text_required'|'hours_invalid'>
 */
function loen_timer_validate(array $entry): array
{
    $errors = [];
    $date = ifset($entry, 'date', '');
    $parsed = is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
        ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
    if (!$parsed || (int)$parsed->format('Y') < 1 || $parsed->format('Y-m-d') !== $date || $date === '1970-01-01') {
        $errors[] = 'date_missing';
    }
    $task = ifset($entry, 'task_id', 0);
    if ((!is_int($task) && (!is_string($task) || !preg_match('/^[1-9][0-9]*$/D', $task))) || (int)$task < 1) {
        $errors[] = 'task_required';
    }
    $text = ifset($entry, 'text', '');
    if (!is_string($text) || trim($text) === '') {
        $errors[] = 'text_required';
    }
    $hoursInvalid = false;
    foreach (['hours','overtime_50','overtime_100'] as $key) {
        $value = ifset($entry, $key, $key === 'hours' ? null : '0');
        if (!is_numeric($value) || !is_finite((float)$value) || ($key === 'hours' ? ((float)$value <= 0 || (float)$value > 24) : (float)$value < 0)) {
            $hoursInvalid = true;
        }
    }
    if ($hoursInvalid) {
        $errors[] = 'hours_invalid';
    }
    return $errors;
}

/**
 * Insert trusted, validated seddel fields in the caller's transaction; preserve omitted-column defaults.
 * Fields are raw values, never SQL-escaped fragments. Null remains SQL NULL; empty strings remain empty.
 * Rejection copies deliberately retain the original nummer; new sedler call loen_next_number first.
 * @param array<string,int|float|string|null> $fields Named loen columns excluding id.
 * @param string|null $source Web file/line for db_modify-compatible logging; null for service-only callers.
 * @return int Inserted id (RETURNING), without a MAX(id) race.
 */
function loen_seddel_insert(array $fields, ?string $source = null): int
{
    if (pg_transaction_status(loen_timer_connection()) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Time entry insertion requires an open transaction');
    }
    $allowed = ['art','kategori','nummer','hvem','sag_nr','sag_id','oprettet','afsluttet','godkendt','afregnet',
        'oprettet_af','ansatte','datoer','fordeling','loen','timer','t50pct','t100pct','mentor','skur','sum','loendate',
        'tekst','korsel','opg_id','opg_nr','sag_ref','afvist','afvist_af','afvist_pga','feriefra','ferietil',
        'hourtype','mentor_rate','afsluttet_af','godkendt_af','master_id','udbetalt','afregnet_af'];
    if (!$fields || array_diff(array_keys($fields), $allowed)) {
        throw new InvalidArgumentException('Unsupported time entry insert field');
    }
    $params = [];
    foreach ($fields as $value) {
        if ($value !== null && !is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException('Invalid time entry insert value');
        }
        $params[] = $value;
    }
    $names = implode(',', array_keys($fields));
    $binds = implode(',', array_map(static fn(int $index): string => '$' . $index, range(1, count($params))));
    $rows = loen_timer_query("INSERT INTO loen ($names) VALUES ($binds) RETURNING id", $params, $source);
    return (int)$rows[0]['id'];
}

/**
 * Project only the caller's position of a packed timer seddel. Payroll amounts and other employees never leave this helper.
 * Stable ordering is loendate DESC, id DESC; membership uses exact tab-delimited tokens.
 * @param array{id?:int,date_from?:string,date_to?:string,sag_id?:int,limit?:int,offset?:int} $filter
 * @return list<array{
 *   id:string,nummer:string,kategori:string|null,loendate:string,sag_id:string|null,sag_nr:string|null,
 *   opg_id:string|null,opg_nr:string|null,tekst:string|null,art:string,oprettet:string|null,
 *   oprettet_af:string|null,afsluttet:string|null,afsluttet_af:string|null,godkendt:string|null,
 *   godkendt_af:string|null,afvist:string|null,afvist_af:string|null,afvist_pga:string|null,
 *   afregnet:string|null,afregnet_af:string|null,udbetalt:string|null,
 *   ansat_id:int,employee_count:int,timer:string,t50pct:string,t100pct:string,hourtype:string|null
 * }>
 */
function loen_employee_rows(int $ansatId, array $filter = []): array
{
    if ($ansatId < 1) {
        throw new InvalidArgumentException('Invalid time entry employee');
    }
    $where = ["art='timer'", '$1 = ANY(string_to_array(ansatte, chr(9)))'];
    $params = [(string)$ansatId];
    foreach (['id'=>'id','date_from'=>'loendate >=','date_to'=>'loendate <=','sag_id'=>'sag_id'] as $key => $expression) {
        if (array_key_exists($key, $filter)) {
            $params[] = in_array($key, ['id','sag_id'], true) ? (int)$filter[$key] : (string)$filter[$key];
            $where[] = $expression . (in_array($key, ['id','sag_id'], true) ? ' = ' : ' ') . '$' . count($params);
        }
    }
    $shared = 'id,nummer,kategori,loendate,sag_id,sag_nr,opg_id,opg_nr,tekst,art,oprettet,oprettet_af,afsluttet,afsluttet_af,godkendt,godkendt_af,afvist,afvist_af,afvist_pga,afregnet,afregnet_af,udbetalt';
    $sql = "SELECT $shared,ansatte,timer,t50pct,t100pct,hourtype FROM loen WHERE " . implode(' AND ', $where) . ' ORDER BY loendate DESC,id DESC';
    if (array_key_exists('limit', $filter)) {
        $sql .= ' LIMIT ' . max(1, min(100, (int)$filter['limit']));
    }
    $sql .= ' OFFSET ' . max(0, (int)ifset($filter, 'offset', 0));
    $result = [];
    foreach (loen_timer_query($sql, $params) as $row) {
        $employees = explode(chr(9), $row['ansatte']);
        $index = array_search((string)$ansatId, $employees, true);
        foreach (['timer','t50pct','t100pct','hourtype'] as $column) {
            $values = explode(chr(9), (string)$row[$column]);
            $row[$column] = ifset($values, $index, $column === 'hourtype' ? null : '0');
        }
        unset($row['ansatte']);
        $row['ansat_id'] = $ansatId;
        $row['employee_count'] = count($employees);
        $result[] = $row;
    }
    return $result;
}
