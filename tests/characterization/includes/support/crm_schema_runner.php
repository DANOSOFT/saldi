<?php
// tests/characterization/includes/support/crm_schema_runner.php
//
// Child process for CrmSchemaLockTest: one login's crm_ensure_schema() (includes/betweenUpdates.php) in a schema of its own.
// betweenUpdates.php runs a login's updates when it is included, so its CRM functions are lifted out of its source; the CRM's
// helpers come from includes/crmFunctions.php. Prints "start" before and "done" after, one per line.
//
// argv: 1 = the SQL fixture database (TestDatabase::sqlFixture()) to connect to; the connection settings come from the
//           same SALDI_CHAR_* environment as the test's. 2 = the schema the CRM's tables go in.
//
// History:
// 20261124 CL/ASR Created.

if ($argc < 3 || !preg_match('/^[a-z_][a-z0-9_]*$/', $argv[2])) {
    fwrite(STDERR, "usage: php crm_schema_runner.php <database> <schema>\n");
    exit(2);
}
require __DIR__ . '/../../support/database/TestDatabase.php';
$GLOBALS['__crm_conn'] = TestDatabase::connect($argv[1]);
$GLOBALS['db_type'] = 'postgresql';

function db_select($q, $where = '')
{
    $r = @pg_query($GLOBALS['__crm_conn'], $q);
    if ($r === false) {
        // On a page db_select()/db_modify() show the error box and end the request.
        fwrite(STDERR, pg_last_error($GLOBALS['__crm_conn']) . " in: $q\n");
        exit(1);
    }
    return $r;
}
function db_modify($q, $where = '')
{
    db_select($q, $where);
    return '0';
}
function db_fetch_array($r)
{
    return pg_fetch_assoc($r);
}

db_select("SET search_path TO {$argv[2]}");
require __DIR__ . '/../../../../includes/crmFunctions.php';

$source = file_get_contents(__DIR__ . '/../../../../includes/betweenUpdates.php');
$tokens = token_get_all($source);
foreach (['crm_ensure_schema', 'crm_ensure_schema_steps', 'crm_migrate_legacy_followups'] as $name) {
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) {
            continue;
        }
        $depth = 0;
        for ($k = $j; $k < count($tokens); $k++) {
            $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            if ($text === '{' || (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($text === '}' && --$depth === 0) {
                eval(implode('', array_map(static fn($t) => is_array($t) ? $t[1] : $t, array_slice($tokens, $i, $k - $i + 1))));
                break 2;
            }
        }
    }
}
if (!function_exists('crm_ensure_schema')) {
    fwrite(STDERR, "crm_ensure_schema() is gone from includes/betweenUpdates.php\n");
    exit(1);
}

fwrite(STDOUT, "start\n");
crm_ensure_schema();
fwrite(STDOUT, "done\n");
