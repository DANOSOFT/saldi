<?php
// 20260921 CDX/LH Execute the production small-FX-difference insertion and balance guard against PostgreSQL.
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message) {
    throw new RuntimeException($message);
});
$dsn = getenv('SALDI_TEST_DSN') ?: getenv('SALDI_CHAR_PG_DSN');
if (!$dsn) {
    throw new RuntimeException('Set SALDI_TEST_DSN for an isolated PostgreSQL fixture');
}
$connection = pg_connect($dsn);
function db_select($sql, $where = '') {
    return pg_query($GLOBALS['connection'], $sql);
}
function db_modify($sql, $where = '') {
    return db_select($sql, $where);
}
function db_fetch_array($result) {
    return pg_fetch_assoc($result);
}
function transaktion($action) {
    if ($action !== 'rollback') {
        throw new RuntimeException('Unexpected transaction action');
    }
    pg_query($GLOBALS['connection'], 'ROLLBACK');
}
function checkCurrency($ok, $message) {
    if (!$ok) {
        throw new RuntimeException($message);
    }
    echo "PASS: $message\n";
}
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../finans/kassekladde_includes/journalPostingGuard.php');
$source = file_get_contents(__DIR__ . '/../finans/bogfor.php');
$start = strpos($source, 'if ($b_sum[$i] && abs($b_sum[$i]) < 0.1');
if ($start === false) {
    throw new RuntimeException('Production FX branch not found');
}
// Read precisely this if block; comments and string contents cannot close it.
$branch = ''; $depth = 0; $entered = false;
foreach (token_get_all('<?php ' . substr($source, $start)) as $token) {
    if (is_array($token) && $token[0] === T_OPEN_TAG) {
        continue;
    }
    $text = is_array($token) ? $token[1] : $token;
    $branch .= $text;
    if ($token === '{') {
        $entered = true; $depth++;
    } elseif ($token === '}') {
        $depth--;
        if ($entered && $depth === 0) {
            break;
        }
    }
}
$start = strpos($source, 'if ($postingBalanceError = journalPostedBalanceError(');
$end = strpos($source, 'if (abs($tjeksum)', $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Production generated-ledger guard not found');
}
// Replace termination only, so the real rollback can be inspected in this process.
$guard = str_replace('exit;', "throw new RuntimeException('guard-stopped');", substr($source, $start, $end-$start));
$columns = 'id serial, kontonr int,bilag int,transdate date,logdate date,logtime text,beskrivelse text,debet numeric(15,2) DEFAULT 0,kredit numeric(15,2) DEFAULT 0,faktura text,kladde_id int,afd int,ansat int,projekt int,valuta text,valutakurs numeric,ordre_id int,moms numeric';
db_select("CREATE TEMP TABLE transaktioner($columns); CREATE TEMP TABLE simulering($columns)");
foreach (['', 'on'] as $simuler) {
    $tabel = $simuler ? 'simulering' : 'transaktioner';
    foreach ([-0.09,-0.03,0,0.03,0.09,-0.10,0.10] as $difference) {
        foreach ([1800,0] as $differenceAccount) {
            $kladde_id = 99; $i = 1;
            $b_diffkonto = [1=>$differenceAccount]; $b_bilag = [1=>37]; $b_transdate = [1=>'2026-09-21'];
            $b_faktura = [1=>'FX-37']; $b_afd = [1=>2]; $b_ansat = [1=>3]; $b_projekt = [1=>4]; $b_ordre_id = [1=>0];
            $bvSum = [1=>0]; $logdate = '2026-09-21'; $logtime = '10:15';
            db_select('BEGIN');
            $credit = number_format(745.13-$difference, 2, '.', '');
            db_select("INSERT INTO $tabel(kontonr,bilag,transdate,debet,kredit,kladde_id,valuta,valutakurs) VALUES(5800,37,'2026-09-21',745.13,0,99,'EUR',745.13),(56100,37,'2026-09-21',0,$credit,99,'DKK',100)");
            $row = db_fetch_array(db_select("SELECT SUM(debet-kredit) AS difference FROM $tabel WHERE kladde_id=99"));
            $b_sum = [1=>(float)$row['difference']];
            $tjeksum = $b_sum[1];
            eval($branch);
            $needsRow = $difference != 0 && abs($difference) < 0.1 && $differenceAccount !== 0;
            $valid = $difference == 0 || $needsRow;
            $posted = db_fetch_array(db_select("SELECT COUNT(*) AS n,COALESCE(SUM(debet),0) AS d,COALESCE(SUM(kredit),0) AS c FROM $tabel WHERE beskrivelse='valutadiff'"));
            checkCurrency((int)$posted['n'] === (int)$needsRow, 'FX difference row appears only within existing tolerance and with configured account');
            if ($needsRow) {
                checkCurrency((float)$posted['d'] === (float)max(0,-$difference) && (float)$posted['c'] === (float)max(0,$difference), "signed $difference FX correction uses a nonnegative debit or credit");
                $metadata = db_fetch_array(db_select("SELECT kontonr,bilag,transdate,faktura,afd,ansat,projekt,valuta,valutakurs FROM $tabel WHERE beskrivelse='valutadiff'"));
                checkCurrency($metadata === ['kontonr'=>'1800','bilag'=>'37','transdate'=>'2026-09-21','faktura'=>'FX-37','afd'=>'2','ansat'=>'3','projekt'=>'4','valuta'=>'DKK','valutakurs'=>'100'], 'FX correction preserves voucher account date currency and dimensions');
            }
            $stopped = false;
            ob_start();
            try {
                eval($guard);
            } catch (RuntimeException $error) {
                if ($error->getMessage() !== 'guard-stopped') {
                    throw $error;
                }
                $stopped = true;
            } finally {
                $output = ob_get_clean();
            }
            checkCurrency($stopped === !$valid, 'generated-ledger guard accepts exact corrected balance and rejects material or unconfigured difference');
            if ($valid) {
                $balance = db_fetch_array(db_select("SELECT SUM(debet)-SUM(kredit) AS balance FROM $tabel"));
                checkCurrency((float)$balance['balance'] === 0.0 && $output === '' && pg_transaction_status($connection) === PGSQL_TRANSACTION_INTRANS, 'accepted correction balances actual stored ledger without committing caller transaction');
                db_select('ROLLBACK');
            } else {
                checkCurrency(pg_transaction_status($connection) === PGSQL_TRANSACTION_IDLE && strpos($output, 'afrunding') !== false, 'rejected correction executes production rollback and reports imbalance');
            }
            checkCurrency(pg_num_rows(db_select("SELECT * FROM $tabel")) === 0, 'rollback removes all generated original and FX rows');
        }
    }
}
