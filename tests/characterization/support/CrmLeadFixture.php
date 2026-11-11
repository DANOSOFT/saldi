<?php
// 20261110 CDX/ASR Exercise CRM lead SQL against temporary PostgreSQL tables, isolated from all tenant data.
// 20261111 CDX/ASR Model authenticated legacy permissions and isolated CRM notification recipients.

/**
 * Uses the CI scratch connection SALDI_CHAR_PG_DSN. Only pg_temp is on the
 * search path: a missing fixture table fails rather than reading a real tenant.
 * No schema installation or migration runs, and closing the connection removes
 * every fixture. The application functions and their SQL are loaded unchanged.
 */
final class CrmLeadFixture
{
    /** @var resource|\PgSql\Connection */
    public $connection;

    public function __construct(string $dsn)
    {
        $this->connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
        if (!$this->connection) {
            throw new RuntimeException('Could not connect to SALDI_CHAR_PG_DSN.');
        }
        $GLOBALS['crmLeadFixture'] = $this;
        $GLOBALS['rettigheder'] = str_repeat('1', 30);
        $GLOBALS['db_type'] = 'postgresql';
        $GLOBALS['db_encode'] = 'UTF8';
        $GLOBALS['regnaar'] = 7;
        $GLOBALS['ansat_id'] = 11;
        $GLOBALS['bruger_id'] = 101;
        $GLOBALS['revisor'] = false;
        $GLOBALS['ansat_navn'] = 'CRM owner';
        $GLOBALS['brugernavn'] = 'crm-fixture';
        $GLOBALS['sprog_id'] = 1;
        $_SESSION = [];
        $this->query('SET search_path TO pg_temp');
        $this->query('CREATE TEMPORARY TABLE adresser (
            id serial PRIMARY KEY, art varchar(2), kontonr varchar(30), firmanavn varchar(90),
            addr1 varchar(60), addr2 varchar(60), postnr varchar(10), bynavn varchar(60), land varchar(60),
            tlf varchar(60), mobile varchar(15), email varchar(60), cvrnr varchar(20),
            status varchar(15), kontoansvarlig integer, oprettet date, kontaktet date, kontaktes date,
            lukket varchar(2), gruppe integer, betalingsbet varchar(15), betalingsdage integer DEFAULT 0,
            kreditmax numeric(15,3), rabat numeric(15,3), rabatgruppe integer, momskonto integer,
            mailfakt varchar(2), udskriv_til varchar(10), kontotype varchar(15), kontakt varchar(60),
            ean varchar(20), institution varchar(15), web varchar(60), notes text, felt_1 text,
            felt_2 text, felt_3 text, felt_4 text, felt_5 text, vis_lev_addr varchar(2),
            pbs varchar(2), stripe_fravalg varchar(2), kategori varchar(15), productlimit numeric(15,0)
        )');
        $boxes = implode(', ', array_map(static fn(int $i): string => 'box' . $i . ' text', range(1, 15)));
        $this->query("CREATE TEMPORARY TABLE grupper (
            id serial PRIMARY KEY, art text, kode text, kodenr integer, beskrivelse text, fiscal_year integer,
            inaktiv boolean DEFAULT FALSE, $boxes
        )");
        $this->query('CREATE TEMPORARY TABLE ansatte (
            id serial PRIMARY KEY, konto_id integer, navn text, initialer text, tlf text, mobil text,
            email text, posnr integer, lukket varchar(2)
        )');
        $this->query('CREATE TEMPORARY TABLE historik (
            id serial PRIMARY KEY, konto_id integer, kontakt_id integer, ansat_id integer, author_id integer,
            notat text, notedate date, kontaktet date, kontaktes date, completed boolean,
            completed_at date, type varchar(10), dokument text
        )');
        $this->query('CREATE TEMPORARY TABLE crm_deals (
            id serial PRIMARY KEY, konto_id integer NOT NULL, title varchar(120) NOT NULL,
            amount numeric(15,2) DEFAULT 0, stage varchar(10), expected_close date, ansat_id integer,
            state varchar(10) DEFAULT \'open\', stage_since date, created date, closed date,
            lost_reason text, ordre_id integer
        )');
        $this->query('CREATE TEMPORARY TABLE ordrer (
            id serial PRIMARY KEY, konto_id integer, art varchar(2), ordrenr integer, fakturanr text,
            status integer, sum numeric, valutakurs numeric, ordredate date, fakturadate date
        )');
        $this->query('CREATE TEMPORARY TABLE openpost (
            id serial PRIMARY KEY, konto_id integer, amount numeric, valutakurs numeric,
            forfaldsdate date, udlignet varchar(1)
        )');
        $this->query('CREATE TEMPORARY TABLE crm_participants (
            id serial PRIMARY KEY, kind varchar(1), ref_id integer, ansat_id integer
        )');
        $this->query('CREATE TEMPORARY TABLE crm_meetings (
            historik_id integer PRIMARY KEY, start_time varchar(5), duration integer,
            seq integer, invite_team integer, invite_contact integer
        )');
        $this->query('CREATE TEMPORARY TABLE brugere (id serial PRIMARY KEY, ansat_id integer, email text, rettigheder text, sprog_id integer)');
        $this->query('CREATE TEMPORARY TABLE crm_ical (bruger_id integer PRIMARY KEY, token varchar(64), created date)');
        $this->query('CREATE TEMPORARY TABLE crm_notifications (
            id serial PRIMARY KEY, event_key varchar(160) NOT NULL, bruger_id integer NOT NULL,
            title text, body text, url text, created date, expires date,
            seen boolean NOT NULL DEFAULT FALSE, UNIQUE (event_key, bruger_id)
        )');
        $this->query("INSERT INTO brugere (id, ansat_id, email, rettigheder, sprog_id) VALUES
            (101, 11, 'owner@example.invalid', '1111111111111111', 1),
            (102, 12, 'other@example.invalid', '1111112111111111', 1)");
        $this->query("INSERT INTO adresser (id, art, firmanavn) VALUES (1, 'S', 'Fixture company')");
        $this->query("INSERT INTO adresser (id, art, kontonr, firmanavn, cvrnr) VALUES
            (2, 'D', '1000', 'Existing debtor', '87654321'), (3, 'D', '1002', 'Other debtor', ''),
            (4, 'K', '1001', 'Existing supplier', '')");
        $this->query("SELECT setval(pg_get_serial_sequence('adresser', 'id'), 4)");
        $this->query("INSERT INTO ansatte (id, konto_id, navn, initialer, email) VALUES
            (11, 1, 'CRM owner', 'CO', 'owner@example.invalid'),
            (12, 1, 'Other owner', 'OO', 'other@example.invalid')");
        $this->query("INSERT INTO grupper (id, art, kodenr, beskrivelse, fiscal_year) VALUES
            (21, 'DG', 4, 'Current debtor group', 7),
            (22, 'DG', 4, 'Previous year debtor group', 6),
            (23, 'KG', 5, 'Supplier group', 7)");
        $this->query("INSERT INTO grupper (id, art, box3, box4) VALUES (24, 'DebInfo', '1', 'New lead')");
        $this->query("INSERT INTO grupper (id, art, kodenr, box1, box2) VALUES (25, 'SKN', 1, 'Sales', '1')");
        $this->query("INSERT INTO grupper (id, art, kodenr, fiscal_year, inaktiv) VALUES
            (26, 'DG', 5, 7, TRUE), (27, 'DG', 0, 7, FALSE)");
        require_once __DIR__ . '/../../../includes/std_func.php';
        require_once __DIR__ . '/../../../includes/crmFunctions.php';
    }

    /**
     * @param list<mixed> $params
     * @return resource|\PgSql\Result
     */
    public function query(string $sql, array $params = [])
    {
        $result = $params ? pg_query_params($this->connection, $sql, $params) : pg_query($this->connection, $sql);
        if (!$result) {
            throw new RuntimeException(pg_last_error($this->connection) . "\n" . $sql);
        }
        return $result;
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, string|null>>
     */
    public function rows(string $sql, array $params = []): array
    {
        return pg_fetch_all($this->query($sql, $params)) ?: [];
    }

    /**
     * The API owns transactions: a domain error rolls back every mutation.
     *
     * @param callable(): array<string, mixed> $action
     * @return array<string, mixed>
     */
    public function action(callable $action): array
    {
        $this->query('BEGIN');
        try {
            $result = $action();
            $this->query(isset($result['error']) ? 'ROLLBACK' : 'COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->query('ROLLBACK');
            throw $error;
        }
    }

    public function close(): void
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->query('ROLLBACK');
        }
        pg_close($this->connection);
    }
}

/** @return resource|\PgSql\Result */
function db_select(string $sql, string $source = '')
{
    return $GLOBALS['crmLeadFixture']->query(str_replace(' like ', ' ilike ', $sql));
}

/** Production db_modify() returns a success string after executing its query. */
function db_modify(string $sql, string $source = ''): string
{
    $GLOBALS['crmLeadFixture']->query(str_replace(' like ', ' ilike ', $sql));
    return "0\tquery accepted";
}

/** @return array<int|string, string|null>|false */
function db_fetch_array($result)
{
    return pg_fetch_array($result);
}

function db_escape_string($value): string
{
    return pg_escape_string($GLOBALS['crmLeadFixture']->connection, (string) $value);
}

function tbl_exists(string $table): bool
{
    return (bool) $GLOBALS['crmLeadFixture']->rows(
        'SELECT 1 FROM pg_class WHERE relnamespace = pg_my_temp_schema() AND relname = $1', [$table]
    );
}
