<?php
// tests/characterization/finans/support/run_refused_rate_difference.php
//
// Child process for ExchangeRateSettlementTest: bookRateDifference() ends the request when the difference
// account cannot be posted to, so it runs here rather than in PHPUnit's process. It writes one journal row
// in a transaction, as bogfor() has by then, asks for a difference on an account that is not in the chart
// of accounts, and goes on to the next posting step if the call returns. On the way out it prints, as JSON
// on the last line, the transaction calls made and the rows the ledger holds.
//
// argv: 1 = SQLite database file, prepared by the test
//       2 = PHP file holding the settlement functions lifted from finans/bogfor.php
//
// History:
// 20261121 CL/LH MB-123 Created.

namespace Saldi\Tests\ExchangeRateSettlement;

use PDO;

require __DIR__ . '/exchange_rate_settlement_database.php';
require dirname(__DIR__, 4) . '/includes/stdFunc/jsString.php';

$db = new PDO('sqlite:' . $argv[1]);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$GLOBALS['exchangeRateSettlementDb'] = $db;
$GLOBALS['exchangeRateSettlementSql'] = [];
$GLOBALS['exchangeRateSettlementTransactions'] = [];
$GLOBALS['regnaar'] = 2026;
$GLOBALS['db_encode'] = 'UTF8';
require $argv[2];

register_shutdown_function(static function () use ($db): void {
    echo "\n" . json_encode([
        'transactions' => $GLOBALS['exchangeRateSettlementTransactions'],
        'rows' => (int)$db->query('SELECT count(*) FROM transaktioner')->fetchColumn(),
    ]) . "\n";
});

$line = ['bilag' => '2', 'transdate' => '2026-10-01', 'faktura' => 'F-745', 'afd' => '', 'ansat' => '0',
    'projekt' => '', 'ordre_id' => '0', 'amount' => 1000.03];
transaktion('begin');
db_modify("INSERT INTO transaktioner (kontonr,bilag,debet,kredit,kladde_id) VALUES ('56100','2','7460','0','4002')");
bookRateDifference(10.00, '6710', '999999', 'transaktioner', 4002, $line, 'EUR');
echo "CONTINUED\n";
db_modify("INSERT INTO transaktioner (kontonr,bilag,debet,kredit,kladde_id) VALUES ('58000','2','0','7460','4002')");
transaktion('commit');
