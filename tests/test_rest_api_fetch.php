<?php
// Copyright (c) 2026 Danosoft ApS
// 20261026 CL/LH Cover the legacy fetch_from_table() parser ported from live: fixed integration
//                shapes, the read grammar, the table allowlist and rejected SQL fragments.
if (!function_exists('db_escape_string')) {
    function db_escape_string($value)
    {
        return str_replace("'", "''", $value);
    }
}
require_once(__DIR__ . '/../api/rest_api_fetch.php');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$checks = 0;
function checkFetch($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $GLOBALS['checks']++;
    echo "PASS: $message\n";
}

checkFetch(restApiFetchRequest('varenr, beholdning', 'varer', '') === ['kind' => 'stock'], 'Stock export shape is recognised');
checkFetch(restApiFetchRequest('id', 'varer', "varenr='AB''C'") === ['kind' => 'item', 'sku' => "AB'C"], 'WooCommerce item lookup decodes a doubled apostrophe');
checkFetch(restApiFetchRequest('id', 'varer', "varenr='A\\' or 1=1 --'") === null, 'Item lookup rejects a backslash');
checkFetch(restApiFetchRequest('id', 'shop_varer', "shop_id='12' and shop_variant='34'") === ['kind' => 'variant', 'shop_id' => '12', 'shop_variant' => '34'], 'Shop variant lookup is recognised');
checkFetch(restApiFetchRequest('id', 'shop_varer', "shop_id='12' and shop_variant='3a'") === null, 'Shop variant lookup accepts digits only');
checkFetch(restApiFetchRequest(array('id'), 'varer', '') === null, 'Array input is rejected');

checkFetch(restApiFetchSql('id, varenr', 'varer', "varenr = 'X1' and beholdning > 0", 'varenr desc', '10 offset 5')
    === "SELECT \"id\", \"varenr\" FROM \"varer\" WHERE \"varenr\" = 'X1' AND \"beholdning\" > 0 ORDER BY \"varenr\" DESC LIMIT 10 offset 5",
    'A simple read compiles to quoted identifiers and an escaped literal');
checkFetch(restApiFetchSql('count(*)', 'ordrer', "art in ('DO','DK')") === "SELECT COUNT(*) FROM \"ordrer\" WHERE \"art\" IN ('DO', 'DK')", 'Aggregates and IN lists compile');
checkFetch(restApiFetchSql('*', 'varer', "beskrivelse like 'O''Brien%'") === "SELECT * FROM \"varer\" WHERE \"beskrivelse\" LIKE 'O''Brien%'", 'Apostrophes in literals stay escaped');
checkFetch(restApiFetchSql('*', 'brugere') === null, 'A table outside the allowlist is rejected');
checkFetch(restApiFetchSql('*', 'mysale') === null, 'mysale is not readable from an untrusted address');
checkFetch(restApiFetchSql('*', 'mysale', '', '', '', '91.235.100.32') === 'SELECT * FROM "mysale"', 'mysale is readable from the trusted host');
foreach (array(
    array('id', 'varer', "1=1 union select passwd from brugere"),
    array('id', 'varer', "id = (select max(id) from varer)"),
    array('id', 'varer', "id = 1; delete from varer"),
    array('id', 'varer', "id = 1 -- comment"),
    array('id', 'varer', "id = pg_sleep(5)"),
    array('id', 'varer', "varenr = 'x\\'"),
    array('pg_read_file(1)', 'varer', ''),
    array('brugere.passwd', 'varer', ''),
) as $case) {
    checkFetch(restApiFetchSql($case[0], $case[1], $case[2]) === null, 'Rejected: ' . $case[0] . ' / ' . $case[2]);
}
checkFetch(restApiFetchSql('id', 'varer', '', '', '10; drop table varer') === null, 'A non-numeric limit is rejected');
checkFetch(restApiFetchSql('id', 'varer', '', 'id; drop table varer') === null, 'Order by accepts field names only');
echo "$checks checks passed.\n";
