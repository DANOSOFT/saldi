<?php
// --- debitor/cashCountHistory.php --- 2026-09-24 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20260917 CDX/PHR Authenticated cash count history by register and date, with browser printing.
// 20260917 CDX/PHR Send historical count copies through the normal POS receipt print path.
// 20260917 CDX/PHR Save decimal repairs before displaying clean report amounts.
// 20260917 CDX/PHR Select only configured registers, defaulting to the current POS register.
// 20260917 CL/LH Show the report's manual-control warning above the amounts.
// 20260924 LOE SD-657 The stored turnover line is left out of the list for users the setting keeps out.
/**
 * Session/account context supplied by includes/online.php.
 * @var string $db
 * @var int $regnaar
 * @var string $brugernavn
 */
ob_start();
session_start();
$s_id = session_id();
$modulnr = 5;
$title = 'Tidligere kasseoptællinger';
$css = '../css/standard.css';
require __DIR__ . '/../includes/connect.php';
require __DIR__ . '/../includes/online.php';
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/cashCountHistoryData.php';
require_once __DIR__ . '/cashCountHistoryPrint.php';

$printRequested = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['receipt']);
$printToken = $_POST['print_token'] ?? '';
if (empty($_SESSION['cash_count_print_token'])) {
    $_SESSION['cash_count_print_token'] = bin2hex(random_bytes(24));
}
$printError = '';

$year = (int)$regnaar;
$posConfiguration = db_fetch_array(db_select("select box1 from grupper where art='POS' and kodenr='1' and fiscal_year='$year'", __FILE__ . ' registers'));
$registerCount = max(0, (int)($posConfiguration['box1'] ?? 0));
$currentRegister = (int)($_COOKIE['saldi_pos'] ?? 1);
if ($currentRegister < 1 || $currentRegister > $registerCount) {
    $currentRegister = $registerCount > 0 ? 1 : 0;
}
$register = (int)($_GET['kasse'] ?? $currentRegister);
if ($register < 1 || $register > $registerCount) {
    $register = $currentRegister;
}
$report = max(0, (int)($_GET['rapport'] ?? 0));
$date = is_string($_GET['dato'] ?? null) ? $_GET['dato'] : '';
$dateError = '';
if ($date !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || !checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1]))) {
    $dateError = 'Angiv en gyldig dato.';
    $date = '';
}
$where = "h.type='Head line' and h.description like 'Cash count, box %'";
if ($register) {
    $where .= " and h.description='Cash count, box $register'";
} else {
    $where .= ' and 1=0';
}
if ($date !== '') {
    $where .= " and h.date='" . db_escape_string($date) . "'";
}
$headers = [];
$query = db_select("select h.id,h.date,h.description,h.report_number from report h where $where
    and exists (select 1 from report r where r.report_number=h.report_number and r.date=h.date and r.type='cashCount')
    order by h.date desc,h.id desc", __FILE__ . ' history');
while ($row = db_fetch_array($query)) {
    if (preg_match('/^Cash count, box (\d+)$/', $row['description'], $match)) {
        $row['register'] = (int)$match[1];
        $headers[] = $row;
    }
}
$selected = null;
foreach ($headers as $header) {
    if ((!$report && $selected === null) || (int)$header['report_number'] === $report) {
        $selected = $header;
        if ($report) {
            break;
        }
    }
}
$data = ['rows' => [], 'warning' => ''];
if ($selected) {
    try {
        $data = cashCountHistoryLoadAndRepair($selected['report_number'], $selected['date']);
    } catch (RuntimeException $error) {
        $printError = $error->getMessage();
        $selected = null;
    }
}
if ($printRequested) {
    if (!is_string($printToken) || !hash_equals($_SESSION['cash_count_print_token'], $printToken)) {
        $printError = 'Udskrivningen kunne ikke godkendes. Genindlæs siden og prøv igen.';
    } elseif (!$selected || !$report) {
        $printError = 'Vælg en gemt optælling før udskrivning.';
    } else {
        $year = (int)$regnaar;
        $configuration = db_fetch_array(db_select("select box3 from grupper where art='POS' and kodenr='2' and fiscal_year='$year'", __FILE__ . ' printer'));
        $printers = explode("\t", $configuration['box3'] ?? '');
        if (trim($printers[$selected['register'] - 1] ?? '') === '') {
            $printError = 'Der er ikke opsat en bonprinter til denne kasse i det aktive regnskabsår.';
        } else {
            $company = db_fetch_array(db_select("select firmanavn,cvrnr from adresser where art='S'", __FILE__ . ' receipt header'));
            try {
                $receipt = cashCountHistoryReceipt($selected, $data, $company ?: [], $brugernavn);
                $file = cashCountHistoryPrintFile($db, $receipt, $selected['register']);
                $destination = 'pos_ordre.php?' . http_build_query([
                    'id' => 0, 'kasse' => $selected['register'], 'udskriv_kasseopg' => $file
                ]);
                ob_end_clean();
                header('Location: ' . $destination, true, 303);
                exit;
            } catch (RuntimeException $error) {
                $printError = $error->getMessage();
            }
        }
    }
}
?>
<style>
.cash-history {max-width:900px;margin:25px auto;padding:20px;background:white;color:#222;font:16px sans-serif}
.cash-history table {width:100%;border-collapse:collapse;margin:20px 0}
.cash-history td,.cash-history th {padding:8px;border-bottom:1px solid #ddd;text-align:left}
.cash-history .amount {text-align:right;white-space:nowrap}
.cash-history input,.cash-history button,.cash-history select {padding:7px;margin:5px}
.cash-history .notice {padding:12px;background:#fff3cf}
@media print {.no-print {display:none!important}.cash-history {margin:0;padding:0;max-width:none}}
</style>
<main class="cash-history">
<h1>Tidligere kasseoptællinger</h1>
<?php if ($printError): ?><p class="notice"><?= cashCountHistoryEscape($printError) ?></p><?php endif; ?>
<div class="no-print">
<p><a href="pos_ordre.php?kasse=<?= $register ?: 1 ?>">Tilbage til kassen</a></p>
<form method="get">
<label>Kasse nr <select name="kasse" <?= $registerCount === 0 ? 'disabled' : '' ?>>
<?php if ($registerCount === 0): ?>
<option>Ingen kasser oprettet</option>
<?php endif; ?>
<?php for ($registerNumber = 1; $registerNumber <= $registerCount; $registerNumber++): ?>
<option value="<?= $registerNumber ?>" <?= $registerNumber === $register ? 'selected' : '' ?>><?= $registerNumber ?></option>
<?php endfor; ?>
</select></label>
<label>Optællingsdato <input type="date" name="dato" value="<?= cashCountHistoryEscape($date) ?>"></label>
<button type="submit">Find optællinger</button>
</form>
<?php if ($dateError): ?><p><?= cashCountHistoryEscape($dateError) ?></p><?php endif; ?>
<?php if ($headers): ?>
<form method="get">
<input type="hidden" name="kasse" value="<?= $register ?>">
<input type="hidden" name="dato" value="<?= cashCountHistoryEscape($date) ?>">
<label>Optælling <select name="rapport">
<?php foreach ($headers as $header): ?>
<option value="<?= (int)$header['report_number'] ?>" <?= $selected && $selected['id'] === $header['id'] ? 'selected' : '' ?>><?= cashCountHistoryEscape($header['date'] . ' — Kasse ' . $header['register'] . ' — Rapport ' . $header['report_number']) ?></option>
<?php endforeach; ?>
</select></label><button type="submit">Vis</button>
</form>
<?php endif; ?>
</div>
<?php if (!$selected): ?>
<p>Ingen gemt optælling fundet for det valgte.</p>
<?php else: ?>
<h2>Kasse <?= $selected['register'] ?> · <?= cashCountHistoryEscape($selected['date']) ?> · Rapport <?= (int)$selected['report_number'] ?></h2>
<p>Genskabt fra den gemte optælling. Mønter og sedler vises som antal; øvrige værdier som beløb.</p>
<?php if ($data['warning']): ?><p class="notice"><?= cashCountHistoryEscape($data['warning']) ?></p><?php endif; ?>
<table><thead><tr><th>Beskrivelse</th><th class="amount">Antal / beløb</th></tr></thead><tbody>
<?php $hide_turnover_row = hide_revenue(); #SD-657 ?>
<?php foreach ($data['rows'] as $row): ?>
<?php if (trim($row['description']) === '' && (float)$row['total'] == 0) { continue; } ?>
<?php if ($hide_turnover_row && strpos($row['description'], 'Dagens omsætning') !== false) { continue; } ?>
<tr><td><?= cashCountHistoryEscape($row['description']) ?></td><td class="amount"><?= number_format((float)$row['total'], 2, ',', '.') ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<form class="no-print" method="post" target="_blank" action="cashCountHistory.php?<?= cashCountHistoryEscape(http_build_query(['kasse' => $selected['register'], 'dato' => $selected['date'], 'rapport' => $selected['report_number']])) ?>">
<input type="hidden" name="print_token" value="<?= cashCountHistoryEscape($_SESSION['cash_count_print_token']) ?>">
<button type="submit" name="receipt" value="1">Udskriv til bonprinter</button>
</form>
<button class="no-print" type="button" onclick="window.print()">Udskriv / gem PDF</button>
<?php endif; ?>
</main>
</body></html>
