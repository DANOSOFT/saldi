<?php
// 20261111 CDX/NTR List or explicitly delete only verified retained PHPUnit databases.

require_once __DIR__ . '/../tests/characterization/support/database/TestDatabase.php';

$delete = false;
$database = null;
try {
    for ($index = 1; $index < $argc; $index++) {
        if ($argv[$index] === '--delete') {
            $delete = true;
        } elseif ($argv[$index] === '--database' && isset($argv[$index + 1])) {
            $database = $argv[++$index];
        } elseif ($argv[$index] === '--help') {
            echo "List verified retained databases: php tools/phpunit-databases.php\n"
                . "Delete those databases:          php tools/phpunit-databases.php --delete\n"
                . "Limit either operation:          --database <printed-database-name>\n"
                . "Uses SALDI_CHAR_PGHOST/PGUSER/PGPASS/MASTER_DB. Active and unmarked databases are excluded.\n";
            exit(0);
        } else {
            throw new RuntimeException('Unknown or incomplete option: ' . $argv[$index]);
        }
    }
    $identities = RetainedTestDatabases::listing($database);
    if ($database !== null && $identities === []) {
        throw new RuntimeException('No verified retained PHPUnit database matches ' . $database);
    }
    foreach ($identities as $identity) {
        if ($delete) {
            RetainedTestDatabases::delete($identity['database']);
        }
        echo ($delete ? 'Deleted ' : 'Retained ') . $identity['database'] . ' UUID=' . $identity['uuid']
            . ' group=' . $identity['test_group'] . ' created=' . $identity['created_at'] . "\n";
    }
    if ($identities === []) {
        echo "No verified retained PHPUnit databases found.\n";
    } elseif (!$delete) {
        echo "List only; use --delete to remove these verified retained databases.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
