<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileSchema.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Address B1 identity, migration and security review findings.
// 20261002 CL/LH Bound the advisory-lock wait (30 s) before the per-statement DDL lock timeout.
// 20261007 CDX/LH Restore the shared tenant SQL modification trail after writes.

require_once __DIR__ . '/../../includes/std_func.php';
require_once __DIR__ . '/../../includes/db_query.php';

/** Ordered migrations on the already-selected installed Saldi database. */
final class MobileSchema
{
    private $connection;
    private $mysql;
    private static $failedStep;

    public function __construct($connection = null)
    {
        $this->connection = $connection !== null ? $connection : ifset($GLOBALS, 'connection');
        $this->mysql = $this->connection instanceof mysqli;
        if (!$this->connection) {
            throw new RuntimeException('Mobile migrations require a selected database');
        }
    }

    /** Execute trusted migration SQL, failing before a marker can advance. */
    public function execute(string $sql): void
    {
        if ($this->mysql) {
            $sql = str_replace(['serial PRIMARY KEY', 'DEFAULT now()', 'token text NOT NULL', 'timestamptz'], ['integer AUTO_INCREMENT PRIMARY KEY', 'DEFAULT CURRENT_TIMESTAMP', 'token varchar(255) NOT NULL', 'timestamp'], $sql);
            $result = mysqli_query($this->connection, $sql);
        } else {
            $result = pg_query($this->connection, $sql);
        }
        db_modify_query_trail($this->connection, $sql, [], __FILE__, $result !== false,
            $result === false ? ($this->mysql ? mysqli_error($this->connection) : pg_last_error($this->connection)) : '');
        if ($result === false) {
            throw new RuntimeException('Mobile schema statement failed');
        }
    }

    /** @return list<array<string, mixed>> */
    public function rows(string $sql): array
    {
        $result = $this->mysql ? mysqli_query($this->connection, $sql) : pg_query($this->connection, $sql);
        db_modify_query_trail($this->connection, $sql, [], __FILE__, $result !== false,
            $result === false ? ($this->mysql ? mysqli_error($this->connection) : pg_last_error($this->connection)) : '');
        if ($result === false) {
            throw new RuntimeException('Mobile schema query failed');
        }
        $rows = [];
        while ($row = $this->mysql ? mysqli_fetch_assoc($result) : pg_fetch_assoc($result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Quoted SQL literal for backfilled server-side identity and marker values. */
    public function literal(string $value): string
    {
        $escaped = $this->mysql ? mysqli_real_escape_string($this->connection, $value) : pg_escape_string($this->connection, $value);
        return "'$escaped'";
    }

    /** Add a column without changing an existing column or its legacy data. */
    public function column(string $table, string $name, string $definition): void
    {
        if ($this->mysql) {
            $exists = $this->rows("SELECT column_name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='$table' AND column_name='$name'");
            if (!$exists) {
                $this->execute("ALTER TABLE $table ADD COLUMN $name $definition");
            }
        } else {
            $this->execute("ALTER TABLE $table ADD COLUMN IF NOT EXISTS $name $definition");
        }
    }

    /** Create a named index; names and expressions are trusted migration constants. */
    public function index(string $table, string $name, string $columns, bool $unique = false): void
    {
        $kind = $unique ? 'UNIQUE ' : '';
        if ($this->mysql) {
            if (!$this->rows("SELECT index_name FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='$table' AND index_name='$name'")) {
                $this->execute("CREATE {$kind}INDEX $name ON $table ($columns)");
            }
        } else {
            $this->execute("CREATE {$kind}INDEX IF NOT EXISTS $name ON $table ($columns)");
        }
    }

    /** Last failing step name only, suitable for the login wrapper's diagnostic. */
    public static function failedStep(): ?string
    {
        return self::$failedStep;
    }

    /** Correct pre-review local wall times; trusted table/column names come from migration steps. */
    public function timezoneColumn(string $table, string $column): void
    {
        if ($this->mysql) {
            return;
        }
        $rows = $this->rows("SELECT data_type FROM information_schema.columns WHERE table_schema=current_schema() AND table_name='$table' AND column_name='$column'");
        if (ifset($rows, [0, 'data_type']) === 'timestamp without time zone') {
            $this->execute("ALTER TABLE $table ALTER COLUMN $column TYPE timestamptz USING $column AT TIME ZONE 'Europe/Copenhagen'");
        }
    }

    /**
     * Installed tenant and registry databases already have settings. A current DB costs one SELECT.
     * Postgres steps and marker commit together; concurrent first logins serialize and recheck.
     * @param array<string, Closure> $steps Immutable names in application order.
     */
    public function run(string $scope, array $steps): void
    {
        self::$failedStep = null;
        $marker = $this->literal('mobile_' . $scope . '_step');
        $select = "SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name=$marker AND user_id=0 ORDER BY id DESC LIMIT 1";
        $rows = $this->rows($select);
        $last = array_key_last($steps);
        if (ifset($rows, [0, 'var_value'], '') === $last) {
            return;
        }
        $lock = $this->literal('saldi:mobile:' . $scope);
        if ($this->mysql) {
            $acquired = $this->rows("SELECT GET_LOCK(CONCAT($lock, ':', DATABASE()), 30) AS acquired");
            if ((int)ifset($acquired, [0, 'acquired'], 0) !== 1) {
                throw new RuntimeException('Mobile schema lock timeout');
            }
        } else {
            if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
                throw new RuntimeException('Pending mobile migrations require an idle connection');
            }
            $this->execute('BEGIN');
        }
        try {
            if (!$this->mysql) {
                // Bound the wait for a concurrent runner like the MySQL GET_LOCK(..., 30) path, then keep DDL lock waits short.
                $this->execute("SET LOCAL lock_timeout = '30s'");
                $this->execute("SELECT pg_advisory_xact_lock(hashtext($lock))");
                $this->execute("SET LOCAL lock_timeout = '5s'");
            }
            $rows = $this->rows($select);
            $current = ifset($rows, [0, 'var_value'], '');
            if ($current !== '' && !array_key_exists($current, $steps)) {
                throw new RuntimeException('Unknown mobile schema step: ' . $current);
            }
            $pending = $current === '';
            foreach ($steps as $name => $apply) {
                if (!$pending) {
                    $pending = $name === $current;
                    continue;
                }
                self::$failedStep = $name;
                $apply($this);
                self::$failedStep = null;
                $value = $this->literal($name);
                $this->execute("DELETE FROM settings WHERE var_grp='mobile' AND var_name=$marker AND user_id=0");
                $this->execute("INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ($marker,'mobile',$value,0)");
            }
            if (!$this->mysql) {
                $this->execute('COMMIT');
            }
        } catch (Throwable $e) {
            if (!$this->mysql) {
                $this->execute('ROLLBACK');
            }
            throw $e;
        } finally {
            if ($this->mysql) {
                $this->execute("SELECT RELEASE_LOCK(CONCAT($lock, ':', DATABASE()))");
            }
        }
    }
}
