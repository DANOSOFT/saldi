<?php
// tests/shopify/support/run_bootstrap.php
//
// Child-process runner for the bootstrap and the DB adapter (SD-734). Loads db_query.php and
// the master connection the way an entry script does after connect.php, then runs one mode:
//   cli         shopify_bootstrap(<db>) and report the context and the legacy globals
//   web         shopify_bootstrap(null): the tenant comes from SALDI_SHOPIFY_DB in the environment
//   rows        bind awkward values through shopify_db_rows() and read them back
//   badparam    an array parameter must be refused before anything is sent
//   tx          nested transactions: the inner failure rolls back only the inner work
//   tx_fail     a failing outer transaction leaves nothing behind
//   tx_aborted  work inside an aborted transaction is refused
//   tx_unhandled  an op that swallows a failed statement cannot be committed
//   tx_early_commit  an op that ends the transaction itself cannot be committed
//   datetime    DateTime parameters land as wall-clock time in PHP's zone
//   errors      a failed statement reports the primary message and SQLSTATE, not the bound values
//   nul         a NUL byte in a parameter or in the statement is refused
//   stacked     two statements in one string are refused, also without parameters
// Prints JSON on stdout. Errors from the module are reported as {"error": ...} with exit 1.
//
// History:
// 20261022 Sawaneh SD-734: created.

if ($argc < 3) {
    fwrite(STDERR, "usage: php run_bootstrap.php <cli|web|rows|badparam|tx|tx_fail|tx_aborted|tx_unhandled|tx_early_commit|datetime|errors|nul|stacked> <test_db>\n");
    exit(2);
}
$mode = $argv[1];
$testDb = $argv[2];

require_once __DIR__ . '/ShopifyEnv.php';
ShopifyEnv::assertSafeTestDb($testDb);

function alert($msg)
{
    fwrite(STDERR, "alert: $msg\n");
    exit(3);
}

function out(array $data, int $exit = 0): void
{
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit($exit);
}

// What includes/connect.php provides: the master connection and its credentials.
$db_type = 'postgresql';
$db_encode = 'UTF8';
$sqhost = ShopifyEnv::pgHost();
$squser = ShopifyEnv::pgUser();
$sqpass = ShopifyEnv::pgPass();
$sqdb = ShopifyEnv::masterDb();
$db = $sqdb;
$db_skriv_id = null;
$brugernavn = '';
$webservice = false;

if ($mode === 'web') {
    // What Apache gives api/shopify/webhook.php: a depth-4 URI with the script's own directory as cwd,
    // two levels below the install, so db_query.php's get_relative() lands on the install's temp/.
    $_SERVER['REQUEST_URI'] = '/saldi/api/shopify/webhook.php';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    chdir(ShopifyEnv::repoRoot() . '/tests/shopify');
}

require_once ShopifyEnv::repoRoot() . '/includes/db_query.php';
$connection = db_connect($sqhost, $squser, $sqpass, $sqdb);
require_once ShopifyEnv::repoRoot() . '/includes/shopify/bootstrap.php';
require_once ShopifyEnv::repoRoot() . '/includes/shopify/db.php';

try {
    switch ($mode) {
        case 'cli':
        case 'web':
            $ctx = shopify_bootstrap($mode === 'cli' ? $testDb : null);
            out([
                'context' => $ctx,
                'globals' => [
                    'db' => $db, 'db_id' => $db_id, 'db_skriv_id' => $db_skriv_id, 'regnskab' => $regnskab,
                    'regnaar' => $regnaar, 'brugernavn' => $brugernavn, 'bruger_id' => $bruger_id, 'sprog_id' => $sprog_id,
                    'webservice' => $webservice,
                ],
                'cwd' => basename(getcwd()),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
                'connected_to' => pg_dbname($connection),
                'legacy_select_works' => (bool)db_fetch_array(db_select("SELECT 1 AS ok FROM grupper WHERE art = 'RA' LIMIT 1", __FILE__ . " linje " . __LINE__)),
            ]);
            // no break: out() exits
        case 'rows':
            shopify_bootstrap($testDb);
            $values = [
                'quote' => "O'Brien \"the\" \\ one; DROP TABLE shopify_shops; -- \$1 \$2",
                'unicode' => 'Mårum æøå 😀',
                'empty' => '',
            ];
            $rows = shopify_db_rows(
                'INSERT INTO shopify_shops (shop_key, domain, navn, afd, is_primary, go_live_at) VALUES ($1, $2, $3, $4, $5, $6) RETURNING id, shop_key, navn, afd, is_primary, go_live_at',
                ['q', 'q.myshopify.com', $values['quote'], 7, true, new DateTimeImmutable('2026-01-02 03:04:05', new DateTimeZone('UTC'))]
            );
            shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, navn, afd) VALUES ($1, $2, $3, $4)', ['u', 'u.myshopify.com', $values['unicode'], null]);
            shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, navn) VALUES ($1, $2, $3)', ['e', 'e.myshopify.com', $values['empty']]);
            $back = shopify_db_rows('SELECT shop_key, navn, afd, is_primary FROM shopify_shops WHERE shop_key IN ($1, $2, $3) ORDER BY shop_key', ['q', 'u', 'e']);
            $none = shopify_db_rows('SELECT id FROM shopify_shops WHERE shop_key = $1', ['missing']);
            $literal = shopify_db_rows('SELECT $1::text AS v', ['$2']);
            $count = shopify_db_rows('SELECT count(*) AS n FROM shopify_shops');
            out(['returning' => $rows, 'back' => $back, 'none' => $none, 'literal' => $literal[0]['v'], 'count' => (int)$count[0]['n'], 'values' => $values]);
            // no break: out() exits
        case 'badparam':
            shopify_bootstrap($testDb);
            try {
                shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain) VALUES ($1, $2)', [['not', 'scalar'], 'x.myshopify.com']);
                out(['refused' => false, 'count' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n']]);
            } catch (InvalidArgumentException $e) {
                out(['refused' => true, 'message' => $e->getMessage(), 'count' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n']]);
            }
            // no break: out() exits
        case 'tx':
            shopify_bootstrap($testDb);
            $innerError = null;
            $result = shopify_db_transaction(function () use (&$innerError, $connection) {
                shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('outer', 'outer.myshopify.com')");
                try {
                    shopify_db_transaction(function () {
                        shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('inner', 'inner.myshopify.com')");
                        shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('inner', 'dup.myshopify.com')");
                    });
                } catch (ShopifyDbError $e) {
                    $innerError = $e->getMessage();
                }
                $statusAfterInner = pg_transaction_status($connection);
                shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('after', 'after.myshopify.com')");
                return ['status_after_inner' => $statusAfterInner, 'returned' => 42];
            });
            $keys = array_column(shopify_db_rows('SELECT shop_key FROM shopify_shops ORDER BY shop_key'), 'shop_key');
            out(['inner_error' => $innerError, 'status_after_inner_intrans' => $result['status_after_inner'] === PGSQL_TRANSACTION_INTRANS,
                'returned' => $result['returned'], 'keys' => $keys, 'status_end_idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE]);
            // no break: out() exits
        case 'tx_fail':
            shopify_bootstrap($testDb);
            $caught = null;
            try {
                shopify_db_transaction(function () {
                    shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('outer', 'outer.myshopify.com')");
                    shopify_db_transaction(function () {
                        shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('inner', 'inner.myshopify.com')");
                    });
                    throw new RuntimeException('outer failure');
                });
            } catch (RuntimeException $e) {
                $caught = $e->getMessage();
            }
            out(['caught' => $caught, 'count' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n'],
                'status_end_idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE]);
            // no break: out() exits
        case 'tx_unhandled':
            shopify_bootstrap($testDb);
            try {
                shopify_db_transaction(function () use ($connection) {
                    shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('kept', 'kept.myshopify.com')");
                    @pg_query($connection, 'SELECT no_such_column FROM shopify_shops');
                    return 'op returned normally';
                });
                out(['threw' => false, 'count' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n']]);
            } catch (ShopifyDbError $e) {
                out(['threw' => true, 'message' => $e->getMessage(), 'count' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n'],
                    'status_end_idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE]);
            }
            // no break: out() exits
        case 'tx_early_commit':
            shopify_bootstrap($testDb);
            $out = [];
            try {
                shopify_db_transaction(function () {
                    shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('early', 'early.myshopify.com')");
                    transaktion('commit');
                    return 'op returned normally';
                });
                $out['own'] = ['threw' => false];
            } catch (Throwable $e) {
                $out['own'] = ['threw' => true, 'class' => get_class($e), 'message' => $e->getMessage(),
                    'count' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n'],
                    'status_idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE];
            }
            // Caller-held transaction: the op's commit commits the caller's uncommitted row as well.
            try {
                shopify_db_transaction(function () {
                    shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('callers', 'callers.myshopify.com')");
                    shopify_db_transaction(function () {
                        shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('inner', 'inner.myshopify.com')");
                        transaktion('commit');
                    });
                    return 'outer returned normally';
                });
                $out['nested'] = ['threw' => false];
            } catch (Throwable $e) {
                $out['nested'] = ['threw' => true, 'class' => get_class($e),
                    'keys' => array_column(shopify_db_rows('SELECT shop_key FROM shopify_shops ORDER BY shop_key'), 'shop_key'),
                    'status_idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE];
            }
            // A second, well-behaved transaction after the misuse still works.
            shopify_db_transaction(fn() => shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('later', 'later.myshopify.com')"));
            $out['later_count'] = (int)shopify_db_rows("SELECT count(*) AS n FROM shopify_shops WHERE shop_key = 'later'")[0]['n'];
            out($out);
            // no break: out() exits
        case 'datetime':
            shopify_bootstrap($testDb);
            $utc = new DateTimeImmutable('2026-10-22 13:00:00', new DateTimeZone('UTC'));
            $ny = new DateTimeImmutable('2026-10-22 09:00:00', new DateTimeZone('America/New_York'));
            shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, go_live_at) VALUES ($1, $2, $3), ($4, $5, $6)', ['utc', 'utc.myshopify.com', $utc, 'ny', 'ny.myshopify.com', $ny]);
            $rows = shopify_db_rows('SELECT shop_key, go_live_at FROM shopify_shops WHERE shop_key IN ($1, $2) ORDER BY shop_key', ['utc', 'ny']);
            out(['php_tz' => date_default_timezone_get(), 'expected' => $utc->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s'), 'rows' => $rows]);
            // no break: out() exits
        case 'errors':
            shopify_bootstrap($testDb);
            $secret = 'shpat_SECRET_TOKEN_123';
            shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain) VALUES ($1, $2)', [$secret, 's.myshopify.com']);
            $out = [];
            try {
                shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain) VALUES ($1, $2)', [$secret, 'other.myshopify.com']);
            } catch (ShopifyDbError $e) {
                $out['unique'] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate, 'constraint' => $e->constraint];
            }
            try {
                shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, legacy_slot) VALUES ($1, $2, $3)', [$secret . '_2', 'c.myshopify.com', 9]);
            } catch (ShopifyDbError $e) {
                $out['check'] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate, 'constraint' => $e->constraint];
            }
            try {
                shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain) VALUES ($1, NULL)', [$secret . '_3']);
            } catch (ShopifyDbError $e) {
                $out['notnull'] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate];
            }
            try {
                shopify_db_rows('SELECT * FROM no_such_table WHERE x = $1', [$secret]);
            } catch (ShopifyDbError $e) {
                $out['missing'] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate];
            }
            foreach (['int' => 'SELECT $1::integer AS v', 'date' => 'SELECT $1::date AS v', 'bool' => 'SELECT $1::boolean AS v',
                'tsquery' => 'SELECT $1::tsquery AS v', 'regclass' => 'SELECT $1::regclass AS v', 'regtype' => 'SELECT $1::regtype AS v',
                'interval' => 'SELECT $1::interval AS v', 'json' => 'SELECT $1::json AS v'] as $name => $cast) {
                try {
                    shopify_db_rows($cast, [$name === 'tsquery' ? "$secret &" : ($name === 'json' ? '{' . $secret : $secret)]);
                } catch (ShopifyDbError $e) {
                    $out["cast_$name"] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate];
                }
            }
            try {
                shopify_db_rows('SELECT $1::integer AS v', ['99999999999999' . $secret]);
            } catch (ShopifyDbError $e) {
                $out['cast_range'] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate];
            }
            shopify_db_rows('CREATE TABLE shopify_test_deferred (k text, CONSTRAINT shopify_test_deferred_k UNIQUE (k) DEFERRABLE INITIALLY DEFERRED)');
            try {
                shopify_db_transaction(function () use ($secret) {
                    shopify_db_rows('INSERT INTO shopify_test_deferred (k) VALUES ($1), ($1)', [$secret]);
                    return 'inserted';
                });
                $out['deferred'] = ['message' => 'no error'];
            } catch (ShopifyDbError $e) {
                $out['deferred'] = ['message' => $e->getMessage(), 'sqlstate' => $e->sqlstate, 'constraint' => $e->constraint,
                    'rows_left' => (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_test_deferred')[0]['n'],
                    'status_idle' => pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE];
            }
            $out['usable_after'] = (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n'];
            out($out);
            // no break: out() exits
        case 'stacked':
            shopify_bootstrap($testDb);
            $out = [];
            try {
                shopify_db_rows("INSERT INTO shopify_shops (shop_key, domain) VALUES ('one', 'one.myshopify.com'); INSERT INTO shopify_shops (shop_key, domain) VALUES ('one', 'two.myshopify.com')");
                $out['stacked'] = 'accepted';
            } catch (ShopifyDbError $e) {
                $out['stacked'] = $e->getMessage();
                $out['sqlstate'] = $e->sqlstate;
            }
            $out['count'] = (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n'];
            $out['plain_select_ok'] = shopify_db_rows('SELECT 1 AS one')[0]['one'] === '1';
            out($out);
            // no break: out() exits
        case 'nul':
            shopify_bootstrap($testDb);
            $out = [];
            try {
                shopify_db_rows('INSERT INTO shopify_shops (shop_key, domain, navn) VALUES ($1, $2, $3)', ['n', 'n.myshopify.com', "before\0after"]);
                $out['param'] = 'accepted';
            } catch (InvalidArgumentException $e) {
                $out['param'] = $e->getMessage();
            }
            try {
                shopify_db_rows("SELECT 1 AS a\0, 2 AS b");
                $out['sql'] = 'accepted';
            } catch (InvalidArgumentException $e) {
                $out['sql'] = $e->getMessage();
            }
            $out['count'] = (int)shopify_db_rows('SELECT count(*) AS n FROM shopify_shops')[0]['n'];
            out($out);
            // no break: out() exits
        case 'tx_aborted':
            shopify_bootstrap($testDb);
            pg_query($connection, 'BEGIN');
            @pg_query($connection, 'SELECT no_such_column FROM shopify_shops');
            try {
                shopify_db_transaction(fn() => shopify_db_rows('SELECT 1'));
                out(['refused' => false]);
            } catch (ShopifyDbError $e) {
                pg_query($connection, 'ROLLBACK');
                out(['refused' => true, 'message' => $e->getMessage()]);
            }
            // no break: out() exits
        default:
            fwrite(STDERR, "unknown mode $mode\n");
            exit(2);
    }
} catch (ShopifyBootstrapError | ShopifyDbError $e) {
    out(['error' => $e->getMessage(), 'class' => get_class($e)], 1);
}
