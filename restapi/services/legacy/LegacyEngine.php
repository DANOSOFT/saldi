<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/legacy/LegacyEngine.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/../../core/Idempotency.php';

/** Server-selected identity and date-derived accounting context; no browser session is required. */
final class EngineContext
{
    public $connection;
    public string $db;
    public int $dbId;
    public string $username;
    public int $userId;
    public int $languageId;
    public int $regnaar;
    public string $baseCurrency;

    public function __construct($connection, string $db, int $dbId, string $username, int $userId, ?string $date, int $languageId = 0)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $db) || $dbId <= 0 || $userId <= 0 || $username === '') {
            throw new InvalidArgumentException('Invalid engine identity');
        }
        $configOnly = $date === null;
        $date = $date ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Copenhagen')) : false;
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_date']);
        }
        $store = new MobileSchema($connection);
        $years = $store->rows("SELECT kodenr,box1,box2,box3,box4 FROM grupper WHERE art='RA' ORDER BY kodenr DESC");
        $regnaar = null;
        $month = (int)$parsed->format('n');
        $year = (int)$parsed->format('Y');
        foreach ($years as $row) {
            // Match the web's month-inclusive RA boundaries, including non-calendar years.
            $start = (int)$row['box2'] * 12 + (int)$row['box1'];
            $end = (int)$row['box4'] * 12 + (int)$row['box3'];
            if ($year * 12 + $month >= $start && $year * 12 + $month <= $end) {
                $regnaar = (int)$row['kodenr'];
                break;
            }
        }
        if ($regnaar === null && $configOnly && $years !== []) {
            $regnaar = (int)$years[0]['kodenr'];
        }
        if ($regnaar === null) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'outside_fiscal_year']);
        }
        $currency = $store->rows("SELECT var_value FROM settings WHERE var_name='baseCurrency' ORDER BY id DESC LIMIT 1");
        $this->connection = $connection;
        $this->db = $db;
        $this->dbId = $dbId;
        $this->username = $username;
        $this->userId = $userId;
        $this->languageId = $languageId;
        $this->regnaar = $regnaar;
        $this->baseCurrency = trim((string)ifset($currency, [0,'var_value'], '')) ?: 'DKK';
    }

    /**
     * Resolve configuration using today's Copenhagen fiscal year, otherwise the newest RA year.
     * A null internal date selects configuration without validating a posting date.
     * @return self Server-selected configuration context; fails when the tenant has no RA years.
     */
    public static function forConfig($connection, string $db, int $dbId, string $username, int $userId, int $languageId = 0): self
    {
        return new self($connection, $db, $dbId, $username, $userId, null, $languageId);
    }

    /** @return array<string,mixed> Globals otherwise supplied by connect.php/online.php. */
    public function globals(): array
    {
        return ['connection' => $this->connection, 'db' => $this->db, 'db_type' => 'postgresql',
            'db_id' => $this->dbId, 'db_skriv_id' => $this->dbId, 'regnaar' => $this->regnaar,
            'brugernavn' => $this->username, 'bruger_id' => $this->userId, 'sprog_id' => $this->languageId,
            'baseCurrency' => $this->baseCurrency, 'webservice' => 1];
    }
}

/** Wrap already-characterised, result-returning engines. A buffer cannot catch a legacy exit. */
final class LegacyEngine
{
    private const ROOT = __DIR__ . '/../../..';
    private const ENGINES = [
        'orders' => ['includes/ordrefunc.php', ['bogfor','bogfor_nu']],
        'openpost' => ['includes/openpost.php', ['openpost','bogfor_nu','bogfor_rykker']],
        'reports' => ['includes/rapportfunc.php', ['openpost','bogfor_nu','bogfor_rykker']],
        'documents' => ['includes/formfunk.php', ['formularprint','rykkerprint']],
        'kladde' => ['includes/finans/kladdePosting.php', ['kladde_bogfor','kladde_openpost']],
    ];
    private static array $loaded = [];
    private static bool $running = false;

    /**
     * Execute with caller-owned transaction state and discard all engine output.
     * Engines must use their callerOwnsTransaction flag. This wrapper never commits.
     * @return mixed The callback's domain result.
     */
    public static function run(EngineContext $context, callable $operation)
    {
        if (self::$running) {
            throw new LogicException('Nested legacy engine contexts are forbidden');
        }
        if (!$context->connection instanceof \PgSql\Connection) {
            throw new LogicException('LegacyEngine requires PostgreSQL');
        }
        $transaction = pg_transaction_status($context->connection);
        if (!in_array($transaction, [PGSQL_TRANSACTION_IDLE, PGSQL_TRANSACTION_INTRANS], true)) {
            throw new LogicException('Legacy engine requires a usable transaction');
        }
        $cwd = getcwd();
        $depth = ifset($GLOBALS, 'db_transaktion_depth', 0);
        $keys = array_merge(array_keys($context->globals()), ['db_transaktion_depth','db_modify_fejl']);
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = [array_key_exists($key, $GLOBALS), ifset($GLOBALS, $key)];
        }
        $buffers = ob_get_level();
        self::$running = true;
        try {
            foreach ($context->globals() as $key => $value) {
                $GLOBALS[$key] = $value;
            }
            $GLOBALS['db_transaktion_depth'] = $transaction === PGSQL_TRANSACTION_INTRANS ? max(1, (int)$depth) : 0;
            $expectedDepth = $GLOBALS['db_transaktion_depth'];
            $transactionId = $transaction === PGSQL_TRANSACTION_INTRANS
                ? (new MobileSchema($context->connection))->rows('SELECT txid_current() AS id')[0]['id'] : null;
            $GLOBALS['db_modify_fejl'] = false;
            if (!chdir(self::ROOT . '/debitor')) {
                throw new RuntimeException('Legacy working directory unavailable');
            }
            // Empty output handler also discards explicit flushes from an engine.
            ob_start(static fn(string $output): string => '');
            $result = $operation();
            if (pg_transaction_status($context->connection) !== $transaction
                || $GLOBALS['db_transaktion_depth'] !== $expectedDepth) {
                throw new LogicException('Legacy engine changed caller-owned transaction state');
            }
            if ($transactionId !== null && (new MobileSchema($context->connection))->rows('SELECT txid_current() AS id')[0]['id'] !== $transactionId) {
                throw new LogicException('Legacy engine replaced caller-owned transaction');
            }
            if ($GLOBALS['db_modify_fejl']) {
                throw new RuntimeException('Legacy engine write failed');
            }
            return $result;
        } finally {
            // If an idle caller's engine opened a transaction, clean up only that leaked work.
            if ($transaction === PGSQL_TRANSACTION_IDLE && pg_transaction_status($context->connection) !== PGSQL_TRANSACTION_IDLE) {
                pg_query($context->connection, 'ROLLBACK');
            }
            while (ob_get_level() > $buffers) {
                ob_end_clean();
            }
            if ($cwd !== false) {
                chdir($cwd);
            }
            foreach ($saved as $key => [$exists, $value]) {
                if ($exists) {
                    $GLOBALS[$key] = $value;
                } else {
                    unset($GLOBALS[$key]);
                }
            }
            self::$running = false;
        }
    }

    /** Load an allowlisted function library after checking incompatible symbols before PHP can fatal. */
    public static function load(string $engine): void
    {
        if (!self::$running) {
            throw new LogicException('Load engines inside LegacyEngine::run');
        }
        if (!isset(self::ENGINES[$engine])) {
            throw new InvalidArgumentException('Unknown legacy engine');
        }
        if (isset(self::$loaded[$engine])) {
            return;
        }
        [$relative, $symbols] = self::ENGINES[$engine];
        $path = realpath(self::ROOT . '/' . $relative);
        if ($path === false) {
            throw new LogicException('Legacy engine extraction is not installed');
        }
        foreach ($symbols as $symbol) {
            if (function_exists($symbol) && (new ReflectionFunction($symbol))->getFileName() !== $path) {
                throw new LogicException('Legacy engine collision: ' . $symbol);
            }
        }
        foreach (self::$loaded as $loaded => $unused) {
            if (array_intersect($symbols, self::ENGINES[$loaded][1])) {
                throw new LogicException('Incompatible legacy engines');
            }
        }
        // Libraries can consume include-injected page variables at top level.
        (static function (string $enginePath): void {
            extract($GLOBALS, EXTR_SKIP);
            require_once $enginePath;
        })($path);
        self::$loaded[$engine] = true;
    }
}
