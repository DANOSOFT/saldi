<?php
// 20260920 CDX/LH Verify journal save redirects preserve display context without replaying actions.
error_reporting(E_ALL);
require_once __DIR__ . '/../finans/kassekladde_includes/journalSaveRedirect.php';
$url = journalSaveRedirectUrl(42, 'belo3', [
    'returside'=>'kontospec.php', 'kksort'=>'amount', 'kkdir'=>'desc', 'popup'=>1,
    'funktion'=>'indsaet_linjer', 'id'=>99, 'bilag'=>9260, 'tjek'=>42, 'beskrivelse'=>'replayed',
]);
parse_str(parse_url($url, PHP_URL_QUERY), $params);
$expected = ['kladde_id'=>'42','returside'=>'kontospec.php','kksort'=>'amount','kkdir'=>'desc','popup'=>'1','fokus'=>'belo3'];
if ($params !== $expected) throw new RuntimeException('Save destination lost display context or retained mutations');
$url = journalSaveRedirectUrl(42, "belo3\r\nInjected: yes", ['returside'=>['invalid'], 'sort'=>"amount\r\nX: yes"]);
if (strpos($url, "\r") !== false || strpos($url, "\n") !== false || strpos($url, 'fokus=') !== false) {
    throw new RuntimeException('Redirect contains raw header characters or invalid focus');
}
echo "PASS: journal destination preserves safe display parameters and excludes mutation requests.\n";
