<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/finans/bankLines.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Normalize CSV source encoding for identity keys while retaining storage bytes.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/../db_query.php';
require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/../mobileUpdates.php';

/** @return \PgSql\Connection Tenant link supplied by online.php or EngineContext. */
function bank_line_connection(): \PgSql\Connection
{
    $connection = ifset($GLOBALS, 'connection');
    if (!$connection instanceof \PgSql\Connection) {
        throw new RuntimeException('Bank line identity requires PostgreSQL');
    }
    return $connection;
}

/** @return list<array<string,mixed>> Checked parameterized rows; never changes transaction ownership. */
function bank_line_query(string $sql, array $params = []): array
{
    $connection = bank_line_connection();
    if (!pg_send_query_params($connection, $sql, $params)) {
        db_modify_query_trail($connection, $sql, $params, __FILE__, false, pg_last_error($connection));
        throw new RuntimeException('Bank line database command failed');
    }
    $result = pg_get_result($connection);
    while (pg_get_result($connection) !== false) {
    }
    $ok = $result !== false && in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true);
    db_modify_query_trail($connection, $sql, $params, __FILE__, $ok,
        $ok ? '' : ($result ? pg_result_error($result) : pg_last_error($connection)));
    if (!$result || !in_array(pg_result_status($result), [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK], true)) {
        throw new RuntimeException('Bank line database command failed');
    }
    return pg_num_fields($result) > 0 ? pg_fetch_all($result) : [];
}

/** @return string Canonical two-decimal money used by identity keys and the numeric(15,2) columns. */
function bank_line_money($value): string
{
    if (!is_numeric($value) || !is_finite((float)$value)) {
        throw new InvalidArgumentException('Invalid bank amount');
    }
    $money = number_format((float)$value, 2, '.', '');
    return $money === '-0.00' ? '0.00' : $money;
}

/**
 * CSV counts equal normalized tuples within one file; Aiia uses its account and transaction ids.
 *
 * @param array{account_kontonr?:int,transdate?:string,date?:string,amount?:string|float,text?:string,text_encoding?:string,balance?:string|float|null,account_id?:string,provider_ref?:string,id?:string} $line Raw import values, before SQL escaping or counter-posting transformations; text_encoding defaults to UTF-8.
 * @return string SHA-256 identity, scoped additionally by account_kontonr in the database.
 */
function bank_line_key(array $line, string $source, int $occurrence): string
{
    if ($source === 'aiia') {
        $account = (string)ifset($line, 'account_id', '');
        $reference = (string)ifset($line, 'provider_ref', ifset($line, 'id', ''));
        if ($account === '' || $reference === '') {
            throw new InvalidArgumentException('Aiia identity requires account and transaction ids');
        }
        return hash('sha256', 'aiia|' . $account . '|' . $reference);
    }
    if ($source !== 'csv' || $occurrence < 0) {
        throw new InvalidArgumentException('Invalid bank identity source or occurrence');
    }
    $text = mb_convert_encoding((string)ifset($line, 'text', ''), 'UTF-8', (string)ifset($line, 'text_encoding', 'UTF-8'));
    $text = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
    $balance = ifset($line, 'balance');
    return hash('sha256', implode('|', [
        'csv', (int)ifset($line, 'account_kontonr', 0),
        (string)ifset($line, 'transdate', ifset($line, 'date', '')),
        bank_line_money(ifset($line, 'amount', 0)), $text,
        $balance === null || $balance === '' ? '' : bank_line_money($balance), $occurrence,
    ]));
}

/**
 * Begin provenance for an import attempt; each re-import has a new row even when identities recur.
 * Call the schema runner on an idle link before opening the import transaction.
 *
 * @return int Import id; this helper neither starts nor commits a transaction.
 */
function bank_import_begin(string $source, int $kontonr, int $kladdeId, ?string $fileSha256, string $actor): int
{
    if (!in_array($source, ['csv','aiia'], true) || $kontonr < 0 || $kladdeId <= 0
        || ($fileSha256 !== null && !preg_match('/^[a-f0-9]{64}$/', $fileSha256))) {
        throw new InvalidArgumentException('Invalid bank import');
    }
    $rows = bank_line_query('INSERT INTO bank_line_imports(source,account_kontonr,kladde_id,file_sha256,imported_by)
        VALUES ($1,$2,$3,$4,$5) RETURNING id', [$source,$kontonr,$kladdeId,$fileSha256,$actor]);
    return (int)$rows[0]['id'];
}

/**
 * Register one original bank transaction and every row it created, including card-fee splits.
 * ON CONFLICT followed by a separate SELECT observes concurrent committed imports at READ COMMITTED.
 *
 * @param array{transdate:string,amount:string|float,text:string,text_encoding?:string,currency:string,balance?:string|float|null,occurrence?:int,account_id?:string,provider_ref?:string} $line CSV occurrence is one based; missing balance stays null; text retains the database client encoding for storage.
 * @param list<int> $kassekladdeIds Persisted journal row ids from this bank transaction.
 * @return int Durable bank identity id. Requires a caller-owned transaction; never stores matched.
 */
function bank_line_register(int $importId, array $line, array $kassekladdeIds): int
{
    if (pg_transaction_status(bank_line_connection()) !== PGSQL_TRANSACTION_INTRANS) {
        throw new LogicException('Bank line registration requires a caller transaction');
    }
    $imports = bank_line_query('SELECT * FROM bank_line_imports WHERE id=$1 FOR UPDATE', [$importId]);
    if (!$imports || !$kassekladdeIds) {
        throw new InvalidArgumentException('Missing bank import or journal rows');
    }
    $import = $imports[0];
    $line['account_kontonr'] = (int)$import['account_kontonr'];
    $key = bank_line_key($line, $import['source'], (int)ifset($line, 'occurrence', 1));
    $date = (string)ifset($line, 'transdate', '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $currency = strtoupper((string)ifset($line, 'currency', ''));
    if (!$parsed || $parsed->format('Y-m-d') !== $date || !preg_match('/^[A-Z]{3}$/', $currency)) {
        throw new InvalidArgumentException('Invalid bank date or currency');
    }
    $balance = ifset($line, 'balance');
    bank_line_query('INSERT INTO bank_lines(account_kontonr,line_key,source,provider_ref,transdate,amount,currency,text,balance,first_import_id)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10) ON CONFLICT (account_kontonr,line_key) DO NOTHING', [
        (int)$import['account_kontonr'],$key,$import['source'],ifset($line, 'provider_ref'),$date,
        bank_line_money($line['amount']),$currency,(string)$line['text'],
        $balance === null || $balance === '' ? null : bank_line_money($balance),$importId,
    ]);
    $rows = bank_line_query('SELECT id FROM bank_lines WHERE account_kontonr=$1 AND line_key=$2', [(int)$import['account_kontonr'],$key]);
    $id = (int)$rows[0]['id'];
    foreach (array_unique($kassekladdeIds) as $journalId) {
        $linked = bank_line_query('INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id)
            SELECT $1,k.id,$3 FROM kassekladde k WHERE k.id=$2 AND k.kladde_id=$4
            ON CONFLICT (bank_line_id,kassekladde_id) DO NOTHING RETURNING kassekladde_id',
            [$id,(int)$journalId,$importId,(int)$import['kladde_id']]);
        if (!$linked && !bank_line_query('SELECT 1 FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id
            WHERE l.bank_line_id=$1 AND k.id=$2 AND k.kladde_id=$3', [$id,(int)$journalId,(int)$import['kladde_id']])) {
            throw new InvalidArgumentException('Journal row does not belong to this import');
        }
    }
    bank_line_query('UPDATE bank_line_imports SET line_count=line_count+1 WHERE id=$1', [$importId]);
    return $id;
}

/**
 * Live relationships override stored workflow state; orphan journal links cannot match a document.
 *
 * @return string SQL expression over trusted bank_lines alias b, shared by single-row and list reads.
 */
function bank_line_state_sql(): string
{
    return "CASE WHEN EXISTS (SELECT 1 FROM bank_line_journal_links l
        JOIN kassekladde k ON k.id=l.kassekladde_id JOIN documents d ON d.source='kassekladde' AND d.source_id::text=k.id::text
        WHERE l.bank_line_id=b.id) THEN 'matched'
        WHEN b.state='received' AND NOT EXISTS (SELECT 1 FROM pool_files p
            WHERE (p.id=b.pool_file_id OR p.bank_line_id=b.id) AND p.status IN ('ubehandlet','attached'))
        THEN CASE WHEN EXISTS (SELECT 1 FROM reminders r JOIN notifications n ON n.reminder_id=r.id
            JOIN notification_recipients nr ON nr.notification_id=n.id
            WHERE r.bank_line_id=b.id AND nr.resolved_at IS NULL) THEN 'requested' ELSE 'open' END
        ELSE b.state END";
}

/**
 * @param array{id:int} $row Stored bank row.
 * @return string Current effective state, without persisting it.
 */
function bank_line_effective_state(array $row): string
{
    $rows = bank_line_query('SELECT ' . bank_line_state_sql() . ' AS effective_state FROM bank_lines b WHERE b.id=$1', [(int)$row['id']]);
    if (!$rows) {
        throw new InvalidArgumentException('Bank line is missing');
    }
    return $rows[0]['effective_state'];
}

/**
 * Read outgoing bank identities with the newest still-existing journal row and derived workflow state.
 * Missing means open/requested, with a live journal row, no document and no active answering bilag.
 *
 * @param array{state?:string,account?:int,account_kontonr?:int} $filter State defaults to missing; account is optional.
 * @return array{items:list<array<string,mixed>>,total:int} Raw bank columns plus stored_state, effective state, kassekladde_id, kladde_id, bilag, bogfort; B3b owns the REST projection.
 */
function bank_lines_missing(array $filter, int $limit, int $offset): array
{
    if ($limit < 1 || $limit > 100 || $offset < 0) {
        throw new InvalidArgumentException('Invalid bank paging');
    }
    $state = (string)ifset($filter, 'state', 'missing');
    if (!in_array($state, ['missing','open','requested','received','matched','ignored','all'], true)) {
        throw new InvalidArgumentException('Invalid bank state');
    }
    $where = "b.amount < 0";
    $params = [];
    if (ifset($filter, 'account', ifset($filter, 'account_kontonr')) !== null) {
        $params[] = (int)ifset($filter, 'account', ifset($filter, 'account_kontonr'));
        $where .= ' AND b.account_kontonr=$1';
    }
    $effective = bank_line_state_sql();
    if ($state === 'missing') {
        $where .= " AND ($effective) IN ('open','requested') AND j.kassekladde_id IS NOT NULL
            AND NOT EXISTS (SELECT 1 FROM pool_files p WHERE (p.id=b.pool_file_id OR p.bank_line_id=b.id)
                AND p.status IN ('ubehandlet','attached'))";
    } elseif ($state !== 'all') {
        $params[] = $state;
        $where .= ' AND (' . $effective . ')=$' . count($params);
    }
    $from = 'FROM bank_lines b LEFT JOIN LATERAL (SELECT k.id AS kassekladde_id,k.kladde_id,k.bilag,kl.bogfort
        FROM bank_line_journal_links l JOIN kassekladde k ON k.id=l.kassekladde_id
        LEFT JOIN kladdeliste kl ON kl.id=k.kladde_id WHERE l.bank_line_id=b.id
        ORDER BY l.created_at DESC,k.id DESC LIMIT 1) j ON true WHERE ' . $where;
    // Count and page share one SQL snapshot, including an empty page beyond the end.
    $rows = bank_line_query('WITH selected AS (SELECT b.*,b.state AS stored_state,(' . $effective . ') AS effective_state,
        j.kassekladde_id,j.kladde_id,j.bilag,j.bogfort ' . $from . '),
        page AS (SELECT * FROM selected ORDER BY transdate DESC,id DESC LIMIT ' . $limit . ' OFFSET ' . $offset . ')
        SELECT (SELECT count(*) FROM selected) AS total,
        COALESCE((SELECT json_agg(page) FROM page),\'[]\'::json) AS items', $params);
    $items = json_decode($rows[0]['items'], true, 512, JSON_THROW_ON_ERROR);
    foreach ($items as &$item) {
        $item['state'] = $item['effective_state'];
        $item['amount'] = bank_line_money($item['amount']);
        $item['balance'] = $item['balance'] === null ? null : bank_line_money($item['balance']);
    }
    return ['items' => $items, 'total' => (int)$rows[0]['total']];
}
