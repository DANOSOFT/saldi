<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/InvoicePaymentStore.php --- 2026.10.12
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
// 20261007 CL/LH Created: PDO access for invoice payments on a dedicated
//                connection, exception-based transactions with savepoints and
//                PostgreSQL advisory locks (pattern from the period-billing
//                branch's BillingStore).

// 20261012 CL/LH Serialize debtor authorization across durable commits and Stripe dispatch.

namespace Saldi\Stripe;

use PDO;
use RuntimeException;
use Throwable;

final class InvoicePaymentStore
{
    public PDO $pdo;
    private $clock;

    public function __construct(PDO $pdo, ?callable $clock = null)
    {
        $this->pdo = $pdo;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->clock = $clock ?? static fn() => time();
    }

    public function now(): int { return ($this->clock)(); }
    public function driver(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME); }

    public function query(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->pdo->prepare($sql);
        $q->execute($args);
        return $q;
    }

    /** @return array<string,mixed>|null */
    public function row(string $sql, array $args = []): ?array
    {
        return $this->query($sql, $args)->fetch() ?: null;
    }

    /** @return list<array<string,mixed>> */
    public function rows(string $sql, array $args = []): array
    {
        return $this->query($sql, $args)->fetchAll();
    }

    /** INSERT ... and return the new id on both PostgreSQL and SQLite. */
    public function insert(string $sql, array $args): int
    {
        if ($this->driver() === 'pgsql') return (int)$this->query($sql . ' RETURNING id', $args)->fetch()['id'];
        $this->query($sql, $args);
        return (int)$this->pdo->lastInsertId();
    }

    public function transaction(callable $operation)
    {
        $nested = $this->pdo->inTransaction();
        $name = 'stripe_' . bin2hex(random_bytes(6));
        if ($nested) {
            $this->pdo->exec('SAVEPOINT ' . $name);
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if (!$this->pdo->inTransaction()) {
                throw new RuntimeException('Transaktionen blev afsluttet af en indlejret operation');
            }
            if ($nested) {
                $this->pdo->exec('RELEASE SAVEPOINT ' . $name);
            } else {
                $this->pdo->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                if ($nested) {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT ' . $name);
                    $this->pdo->exec('RELEASE SAVEPOINT ' . $name);
                } else {
                    $this->pdo->rollBack();
                }
            }
            throw $e;
        }
    }

    public function inTransaction(): bool { return $this->pdo->inTransaction(); }

    public function lock(string $key): void
    {
        if (!$this->pdo->inTransaction()) {
            throw new RuntimeException('En betalingslås kræver en transaktion');
        }
        if ($this->driver() === 'pgsql') {
            $this->query('SELECT pg_advisory_xact_lock(hashtext(?))', [$key]);
        }
    }

    /**
     * Hold a PostgreSQL session lock across commits and remote calls. The finally
     * releases it on failures; PostgreSQL also releases it if the process dies.
     * SQLite serializes writes through its connection/transaction in fixtures.
     * @return mixed The callback's result.
     */
    public function serialized(string $key, callable $operation)
    {
        if ($this->driver() === 'pgsql') {
            $this->query('SELECT pg_advisory_lock(hashtext(?))', [$key]);
        }
        try {
            return $operation();
        } finally {
            if ($this->driver() === 'pgsql') {
                $this->query('SELECT pg_advisory_unlock(hashtext(?))', [$key]);
            }
        }
    }

    public function setting(string $key, string $default = ''): string
    {
        $r = $this->row("SELECT var_value FROM settings WHERE var_grp='stripe' AND var_name=?", [$key]);
        return ($r && $r['var_value'] !== null && $r['var_value'] !== '') ? (string)$r['var_value'] : $default;
    }

    /** 'test' or 'live'; every row is scoped by it so test data never meets live data. */
    public function mode(): string
    {
        $mode = $this->setting('mode');
        if (!in_array($mode, ['test', 'live'], true)) throw new RuntimeException('Stripe-tilstand (test/live) er ikke konfigureret');
        return $mode;
    }

    public function audit(string $kind, int $id, string $action, string $actor, array $data = []): void
    {
        $this->query('INSERT INTO stripe_invoice_audit (entity_type,entity_id,action,actor,data_json,created_at) VALUES (?,?,?,?,?,?)',
            [$kind, $id, $action, mb_substr($actor, 0, 100), self::json($data), $this->now()]);
    }

    public static function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
