<?php
// 20260920 CDX/LH Verify the actual sale posting loop against expected EUR and DKK ledger amounts.
// No application bootstrap or database; SQL writes are captured at the database boundary.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/salesPostingVat.php';

function db_select($sql, $trace) {
    if (strpos($sql, "posnr>='0'") !== false) {
        preg_match("/projekt='([^']*)'/", $sql, $project);
        return new ArrayIterator(array_values(array_filter($GLOBALS['postingLines'], function ($line) use ($project) { return !isset($project[1]) || $line['projekt'] === $project[1]; })));
    }
    if (strpos($sql, "posnr<'0'") !== false) {
        return new ArrayIterator([]);
    }
    throw new RuntimeException('Unexpected read: ' . $sql);
}
function db_fetch_array($rows) {
    if (!$rows->valid()) return false;
    $row = $rows->current();
    $rows->next();
    return $row;
}
function db_modify($sql, $trace) {
    if (preg_match('/insert into transaktioner\s*\((.*?)\)\s*values\s*\((.*?)\)/i', $sql, $match)) {
        $columns = array_map('trim', explode(',', $match[1]));
        $values = array_map(function ($v) { return trim($v, " '\t\n\r"); }, explode(',', $match[2]));
        $GLOBALS['postedRows'][] = array_combine($columns, $values);
    } elseif (strpos($sql, 'update kontoplan set saldo=') !== 0) {
        throw new RuntimeException('Unexpected write: ' . $sql);
    }
    return "0\tquery accepted";
}

/** @return array{rows: array, debitControl: float, creditControl: float} */
function captureSalePosting($net, $rate, $vatRate, $quantity = 1, $lines = null, $invoiceVat = null) {
    $GLOBALS['postingLines'] = [[
        'bogf_konto'=>1200, 'vat_account'=>66100, 'rabatart'=>'',
        'pris'=>$net / $quantity, 'antal'=>$quantity, 'rabat'=>0, 'procent'=>'',
        'momssats'=>$vatRate, 'momsfri'=>$vatRate ? '' : 'on', 'projekt'=>'', 'posnr'=>1
    ]];
    if ($lines !== null) $GLOBALS['postingLines'] = $lines;
    $invoiceVat = $invoiceVat ?? afrund($net * $vatRate / 100, 2);
    $saleVatAllocations = $rate != 100 ? salesPostingVatAllocations($GLOBALS['postingLines'], $invoiceVat, $rate) : null;
    $GLOBALS['postedRows'] = [];
    $source = file_get_contents(__DIR__ . '/../includes/ordrefunc.php');
    $start = strpos($source, "\tfor (\$t = 1; \$t <= 2; \$t++) {", strpos($source, "\nfunction bogfor_nu("));
    $end = strpos($source, "\n\t\$moms = afrund(\$moms, 2);", $start);
    if ($start === false || $end === false) throw new RuntimeException('Sale posting loop not found');
    $id = 42;
    $projekt = array_merge([''], array_values(array_unique(array_column($GLOBALS['postingLines'], 'projekt'))));
    $projektantal = count($projekt)-1;
    $valutakurs = $rate;
    $maxdif = 2;
    $art = 'DO';
    $indbetaling = false;
    $d_kontrol = $k_kontrol = 0;
    $transdate = $logdate = '2025-10-28';
    $beskrivelse = 'Precision regression';
    $fakturanr = 101;
    $afd = $ansat = $kasse = 0;
    $regnaar = 4;
    $logtime = '12:00';
    $sum = afrund($net, 2) + $invoiceVat;
    $kontonr = 56100;
    $hmlog = fopen('php://memory', 'w');
    $receivableStart = strpos($source, "\t\$sum = afrund(\$sum, 3);", strpos($source, "\nfunction bogfor_nu("));
    $receivableEnd = strpos($source, "\n\tif (\$valutakurs)\n\t\t\$maxdif = 2;", $receivableStart);
    eval(substr($source, $receivableStart, $receivableEnd - $receivableStart));
    eval(substr($source, $start, $end - $start));
    return ['rows'=>$GLOBALS['postedRows'], 'debitControl'=>$d_kontrol, 'creditControl'=>$k_kontrol];
}
$cases = [
    [999.99, 745, 25, 3, 7449.93, 1862.50],
    [-999.99, 745, 25, 3, -7449.93, -1862.50],
    [999.99, 100, 25, 3, 999.99, 250.00],
    [-999.99, 100, 25, 3, -999.99, -250.00],
    [1000, 745, 25, 1, 7450.00, 1862.50],
    [999.99, 745, 0, 3, 7449.93, 0.00],
    [0.01, 745, 25, 1, 0.07, 0.00],
    [0.03, 745, 25, 1, 0.22, 0.07],
];
foreach ($cases as [$net,$rate,$vatRate,$quantity,$expectedNet,$expectedVat]) {
    $result = captureSalePosting($net,$rate,$vatRate,$quantity);
    $actual = [];
    $debit = $credit = 0;
    foreach ($result['rows'] as $row) {
        $actual[(int)$row['kontonr']] = (float)$row['kredit'] - (float)$row['debet'];
        $debit += (float)$row['debet'];
        $credit += (float)$row['kredit'];
    }
    if (abs(($actual[1200] ?? 0) - $expectedNet) > 0.00001 || abs(($actual[66100] ?? 0) - $expectedVat) > 0.00001) {
        throw new RuntimeException('Wrong posted invoice components: ' . json_encode([$net,$rate,$actual]));
    }
    $expectedGross = round((round($net, 2) + round($net * $vatRate / 100, 2)) * $rate / 100, 2);
    if (abs(($actual[56100] ?? 0) + $expectedGross) > 0.00001) throw new RuntimeException('Wrong receivable conversion');
    if (abs($debit - $result['debitControl']) > 0.00001 || abs($credit - $result['creditControl']) > 0.00001) {
        throw new RuntimeException('Control totals differ from actual ledger writes');
    }
    $revenueRows = array_values(array_filter($result['rows'], function ($row) { return (int)$row['kontonr'] === 1200; }));
    if (abs((float)$revenueRows[0]['moms'] + $expectedVat) > 0.00001) {
        throw new RuntimeException('Revenue VAT annotation differs from posted VAT');
    }
}
echo 'PASS: ' . count($cases) . " sale/credit-note currency cases; VAT components, annotations and ledger controls agree.\n";

function multiLine($account, $vatAccount, $price, $rate = 25, $project = '') {
    return ['bogf_konto'=>$account,'vat_account'=>$vatAccount,'rabatart'=>'','pris'=>$price,
        'antal'=>1,'rabat'=>0,'procent'=>'','momssats'=>$rate,'momsfri'=>'','projekt'=>$project,'posnr'=>1];
}
$exemptCredit = multiLine(1200,66100,-100);
$exemptCredit['momsfri'] = 'on';
$multiCases = [
    [[multiLine(1200,66100,100),$exemptCredit],0,25,[66100=>186.25]],
    [[multiLine(1200,66100,1000.02),multiLine(1210,66100,1000.02)],2000.04,500.01,[66100=>3725.07]],
    [[multiLine(1200,66100,-1000.02),multiLine(1210,66100,-1000.02)],-2000.04,-500.01,[66100=>-3725.07]],
    [[multiLine(1200,66100,1000.02),multiLine(1200,66110,1000.02,10)],2000.04,350.01,[66100=>1862.57,66110=>745.00]],
    [[multiLine(1200,66100,-1000.02),multiLine(1200,66110,-1000.02,10)],-2000.04,-350.01,[66100=>-1862.57,66110=>-745.00]],
    [[multiLine(1200,66100,1000.02),multiLine(1210,66100,1000.02,10)],2000.04,350.01,[66100=>2607.57]],
    [[multiLine(1200,66100,1000.02,25,'A'),multiLine(1200,66100,1000.02,25,'B')],2000.04,500.01,[66100=>3725.07]],
];
foreach ($multiCases as [$lines,$net,$invoiceVat,$expectedVatAccounts]) {
    $result = captureSalePosting($net,745,25,1,$lines,$invoiceVat);
    $actual = [];
    $vatAnnotations = 0;
    $debit = $credit = 0;
    foreach ($result['rows'] as $row) {
        $account = (int)$row['kontonr'];
        $actual[$account] = ($actual[$account] ?? 0) + (float)$row['kredit'] - (float)$row['debet'];
        $vatAnnotations += (float)($row['moms'] ?? 0);
        $debit += (float)$row['debet'];
        $credit += (float)$row['kredit'];
    }
    foreach ($expectedVatAccounts as $account=>$expected) {
        if (abs(($actual[$account] ?? 0)-$expected)>0.00001) throw new RuntimeException('Multi-group VAT mismatch: '.json_encode($actual));
    }
    if (abs($vatAnnotations + array_sum($expectedVatAccounts))>0.00001) throw new RuntimeException('Multi-group revenue VAT annotations mismatch');
    if (abs($debit-$result['debitControl'])>0.00001 || abs($credit-$result['creditControl'])>0.00001) throw new RuntimeException('Multi-group controls differ from ledger writes');
}
$projects = [multiLine(1200,66100,1000.02,25,'A'),multiLine(1200,66100,1000.02,25,'B')];
$allocation = salesPostingVatAllocations($projects,500.01,745);
if (count($allocation)!==2 || abs(array_sum($allocation)-3725.07)>0.00001) throw new RuntimeException('Projects multiply VAT rounding');
$badHeaderRejected = false;
try { salesPostingVatAllocations($projects,510,745); } catch (RuntimeException $error) { $badHeaderRejected = true; }
if (!$badHeaderRejected) throw new RuntimeException('Material VAT discrepancy was redistributed');
echo 'PASS: '.count($multiCases)." multi-revenue/rate/VAT-account sale and credit cases; project allocation and material-mismatch rejection.\n";
