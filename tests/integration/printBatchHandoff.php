<?php
// 20260914 CDX/LH SST-789: Characterize generator-to-converter session handoff without a database.
// Run: php tests/integration/printBatchHandoff.php
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function checkHandoff($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
function if_isset($array, $default, $key) { return $array[$key] ?? $default; }

$source = file_get_contents(__DIR__ . '/../../includes/formfunk.php');
$start = strpos($source, "\t\t} elseif (\$nomailantal > 0) {");
checkHandoff($start !== false, 'Cannot locate non-email output branch');
$start = strpos($source, "\n", $start) + 1;
$end = strpos($source, "\t\t} elseif (\$popup)", $start);
checkHandoff($end !== false, 'Cannot locate output branch end');
$outputBranch = substr($source, $start, $end - $start);
$converter = file_get_contents(__DIR__ . '/../../includes/udskriv.php');
$start = strpos($converter, '$ps_fil        =');
$end = strpos($converter, '$valg          =', $start);
checkHandoff($start !== false && $end !== false, 'Cannot locate converter batch gate');
$batchGate = substr($converter, $start, $end - $start);

foreach (array(1, 4) as $count) {
    $_SESSION = array();
    $mappe = '../temp/tenant/7';
    $printfilnavn = 'fakt104';
    $printBatchName = 'faktura';
    $printBatchDocuments = array();
    for ($i = 1; $i <= $count; $i++) $printBatchDocuments[] = array('name' => 'fakt' . (100 + $i), 'background' => '', 'pages' => $i);
    $locat = '';
    $id = 104;
    $udskriv_til = 'PDF';
    $art = 'DO';
    $background_pdf_path = '';
    $returside = '../debitor/ordreliste.php?valg=faktura&search=test';
    ob_start();
    eval($outputBranch);
    $html = ob_get_clean();
    preg_match('/URL=([^\"]+)/', $html, $match);
    parse_str(parse_url(html_entity_decode($match[1]), PHP_URL_QUERY), $request);
    checkHandoff($request['returside'] === $returside, 'Return URL changed');
    checkHandoff($request['ps_fil'] === ($count === 1 ? 'tenant/7/fakt104' : 'tenant/7/faktura-batch'), 'Wrong output filename');
    $_GET = $request;
    $db = 'tenant';
    $bruger_id = 7;
    eval($batchGate);
    checkHandoff($printBatch === ($count === 1 ? null : $printBatchDocuments), 'Ordered documents lost in session handoff');
    if ($count > 1) {
        foreach (array(array('other', 7), array('tenant', 8)) as list($db, $bruger_id)) {
            eval($batchGate);
            checkHandoff($printBatch === null, 'Batch accepted for another tenant/user');
        }
        $db = 'tenant';
        $bruger_id = 7;
        $_GET['ps_fil'] = 'tenant/7/fakt104';
        eval($batchGate);
        checkHandoff($printBatch === null, 'Batch accepted for another document');
    }
    echo "PASS: $count-document handoff and tenant/user/document scope\n";
}
