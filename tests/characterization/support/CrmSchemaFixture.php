<?php
// 20261110 CDX/ASR Run the CRM updater against session-local PostgreSQL tables only.
// 20261111 CDX/ASR Keep CRM notification migration tables in the session-local schema.

/** Production migration SQL runs with pg_temp as the only writable search path. */
final class CrmSchemaFixture
{
    public PgSql\Connection $connection;
    /** @var list<string> */
    public array $writes = [];
    public ?string $failOn = null;
    public ?string $failResultOn = null;

    public function __construct(string $dsn)
    {
        $connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        if (!$connection) {
            throw new RuntimeException('Could not connect to SALDI_CHAR_PG_DSN.');
        }
        $this->connection = $connection;
        $GLOBALS['crmSchemaFixture'] = $this;
        $GLOBALS['db_type'] = 'postgresql';
        $this->query('SET search_path TO pg_temp');
        $this->query('CREATE TEMPORARY TABLE historik (
            id serial PRIMARY KEY, konto_id integer, kontakt_id integer, ansat_id integer,
            notat text, notedate date, kontaktet date, kontaktes date, dokument text
        )');
        $this->query('CREATE TEMPORARY TABLE settings (
            id serial PRIMARY KEY, var_name text, var_grp text, var_value text, var_description text,
            user_id integer, pos_id integer
        )');
        require_once __DIR__ . '/../../../includes/opdat_func/crmSchema.php';
        require_once __DIR__ . '/../../../includes/crmFunctions.php';
    }

    public function query(string $sql): PgSql\Result
    {
        $result = pg_query($this->connection, $sql);
        if (!$result) {
            throw new RuntimeException(pg_last_error($this->connection));
        }
        return $result;
    }

    /** @return list<array<string, string|null>> */
    public function rows(string $sql): array
    {
        return pg_fetch_all($this->query($sql)) ?: [];
    }

    /** Only persistence changes: production CREATE TABLE becomes session-local CREATE TEMPORARY TABLE. */
    public function modify(string $sql, string $source): string
    {
        if ($this->failOn !== null && str_contains($sql, $this->failOn)) {
            throw new RuntimeException('Injected CRM migration failure.');
        }
        if ($this->failResultOn !== null && str_contains($sql, $this->failResultOn)) {
            // Reproduce db_modify's legacy update-source behavior: a failure can look successful.
            return str_contains($source, 'includes/opdat') ? "0\tquery accepted" : "1\tinjected write failure";
        }
        $this->writes[] = $sql;
        $sql = preg_replace('/\bCREATE TABLE (crm_(?:deals|meetings|participants|ical|notifications))\b/', 'CREATE TEMPORARY TABLE $1', $sql);
        $this->query($sql);
        return "0\tquery accepted";
    }

    public function close(): void
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->query('ROLLBACK');
        }
        pg_close($this->connection);
        unset($GLOBALS['crmSchemaFixture']);
    }
}

function db_select(string $sql, string $source = ''): PgSql\Result
{
    return $GLOBALS['crmSchemaFixture']->query($sql);
}

function db_modify(string $sql, string $source = ''): string
{
    return $GLOBALS['crmSchemaFixture']->modify($sql, $source);
}

/** @return array<int|string, string|null>|false */
function db_fetch_array(PgSql\Result $result)
{
    return pg_fetch_array($result);
}

function db_escape_string($value): string
{
    return pg_escape_string($GLOBALS['crmSchemaFixture']->connection, (string) $value);
}
