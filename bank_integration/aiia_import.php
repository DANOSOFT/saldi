<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/aiia_import.php --- patch 0.0.1 --- 2026-06-01 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY WARRANTY OF ANY KIND.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20260601 NTR - Initial version. Fetches and displays transactions for a
//                selected bank account via Aiia /v2/accounts/{id}/transactions.

@session_start();
$s_id = session_id();
include_once(__DIR__ . '/../includes/connect.php');
include_once(__DIR__ . '/../includes/online.php');
include_once(__DIR__ . '/../includes/std_func.php');
['needsInit' => $needsInit, 'needsInteraction' => $needsInteraction] = include_once(__DIR__ . '/includes/auth_check.php');
$OAuth = $_SESSION['OAuth'] ?? null;

$kladde_id = isset($_GET['kladde_id']) ? intval($_GET['kladde_id']) : 0;

if ($needsInit || $needsInteraction) {
    $self = 'aiia_import.php?kladde_id=' . $kladde_id;
    header('Location: login.php?return=' . urlencode($self));
    exit;
}

if (isset($_GET['change_account'])) {
    unset($_SESSION['aiia_account']);
    $self = 'aiia_import.php?kladde_id=' . $kladde_id;
    header('Location: get_accounts.php?kladde_id=' . $kladde_id . '&return=' . urlencode($self));
    exit;
}

if (empty($_SESSION['aiia_account'])) {
    $self = 'aiia_import.php?kladde_id=' . $kladde_id;
    header('Location: get_accounts.php?kladde_id=' . $kladde_id . '&return=' . urlencode($self));
    exit;
}

$account = $_SESSION['aiia_account'];

// --- Handle import POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['import'])) {
    $defaultAccount = isset($_POST['debet']) ? db_escape_string(trim($_POST['debet'])) : '0';
    if($defaultAccount === '') {
        $defaultAccount = '0';
    }
    if (!$kladde_id) {
        $tidspkt   = microtime();
        $row       = db_fetch_array(db_select("SELECT MAX(id) AS id FROM kladdeliste", __FILE__ . " linje " . __LINE__));
        $kladde_id = (int) $row['id'] + 1;
        $kladdedate = date("Y-m-d");
        db_modify("INSERT INTO kladdeliste (id, kladdenote, kladdedate, bogfort, hvem, oprettet_af, tidspkt) VALUES ('$kladde_id', '', '$kladdedate', '-', '$brugernavn', '$brugernavn', '$tidspkt')", __FILE__ . " linje " . __LINE__);
    }

    [$next_bilag] = db_fetch_row(
        db_select("SELECT COALESCE(MAX(bilag), 0) + 1 FROM kassekladde WHERE kladde_id = '$kladde_id'",
                  __FILE__ . " linje " . __LINE__)
    );
    $next_bilag = (int) $next_bilag;

    [$next_lobenr] = db_fetch_row(
        db_select("SELECT COALESCE(MAX(lobenr), 0) + 1 FROM tmpkassekl WHERE kladde_id = '$kladde_id'",
                  __FILE__ . " linje " . __LINE__)
    );
    $next_lobenr = (int) $next_lobenr;

    $imported = 0;
    foreach ($_POST['import'] as $encoded) {
        $tx = json_decode($encoded, true);
        if (!is_array($tx)) continue;

        $transdate   = db_escape_string($tx['date']   ?? date('Y-m-d'));
        $beskrivelse = db_escape_string($tx['text']   ?? '');
        $faktura     = db_escape_string($tx['id']     ?? '');
        $amount      = abs((float) ($tx['amount'] ?? 0));
        $isDebit     = ($tx['amount'] ?? 0) <= 0;
        $bilag       = $next_bilag++;
        $lobenr      = $next_lobenr++;

        $debet  = !$isDebit ? $defaultAccount : '0';
        $kredit = $isDebit  ? $defaultAccount : '0';

        $sql = "INSERT INTO kassekladde (bilag, transdate, beskrivelse, faktura, amount, kladde_id, debet, kredit)
                VALUES ('$bilag', '$transdate', '$beskrivelse', '$faktura', '$amount', '$kladde_id', '$debet', '$kredit')
                RETURNING id";
        $res = db_select($sql, __FILE__ . " linje " . __LINE__);
        $row = db_fetch_row($res);
        $new_id = (int) ($row[0] ?? 0);

        if ($new_id) {
            // Also insert into tmpkassekl so the row appears immediately in the kassekladde display
            $sql2 = "INSERT INTO tmpkassekl (lobenr, id, bilag, transdate, beskrivelse, faktura, amount, kladde_id, debet, kredit)
                     VALUES ('$lobenr', '$new_id', '$bilag', '$transdate', '$beskrivelse', '$faktura', '$amount', '$kladde_id', '$debet', '$kredit')";
            db_modify($sql2, __FILE__ . " linje " . __LINE__);
            $imported++;
        } else {
            error_log("[aiia_import] INSERT failed — bilag=$bilag transdate=$transdate kladde_id=$kladde_id");
        }
    }

    unset($_SESSION['aiia_account']);
    header('Location: ../finans/kassekladde.php?kladde_id=' . $kladde_id);
    exit;
}

// --- Date filter ---
$now = new DateTime();

$currentQ  = (int) ceil((int)$now->format('n') / 3);
$lastQ     = $currentQ - 1;
$lastQYear = (int)$now->format('Y');
if ($lastQ === 0) { $lastQ = 4; $lastQYear--; }
$defaultFrom = sprintf('%04d-%02d-01', $lastQYear, ($lastQ - 1) * 3 + 1);
$defaultTo   = $now->format('Y-m-d');

$fromDate = (isset($_GET['fromDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fromDate']))
    ? $_GET['fromDate'] : $defaultFrom;
$toDate   = (isset($_GET['toDate'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['toDate']))
    ? $_GET['toDate']   : $defaultTo;

$hasFetch     = isset($_GET['fromDate']);
$transactions = [];
$httpCode     = null;
$response     = null;
$data         = null;

if ($hasFetch) {
    $sql = "SELECT
                MAX(CASE WHEN var_name = 'Client ID'     THEN var_value END),
                MAX(CASE WHEN var_name = 'Client Secret' THEN var_value END)
            FROM settings
            WHERE var_grp = 'OAuth'
              AND var_name IN ('Client ID', 'Client Secret')";
    [$clientID, $clientSecret] = db_fetch_row(db_select($sql, __FILE__ . " linje " . __LINE__, true));

    $accountId       = urlencode($account['id']);
    $pagingToken     = null;
    $fetchingCounter = 0;

    do {
        $fetchingCounter++;
        $url = "https://api.nordicapigateway.com/v2/accounts/{$accountId}/transactions?fromDate={$fromDate}&toDate={$toDate}";
        if ($pagingToken !== null) {
            $url .= '&pagingToken=' . urlencode($pagingToken);
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'X-Client-Id: '     . $clientID,
                'X-Client-Secret: ' . $clientSecret,
                'Authorization: Bearer ' . $OAuth['session']['accessToken'],
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $data         = json_decode($response, true);
        $page         = $data['transactions'] ?? [];
        $transactions = array_merge($transactions, $page);
        $pagingToken  = $data['pagingToken'] ?? null;

    } while ($httpCode === 200 && $pagingToken !== null && $fetchingCounter < 100);

    $transactions = array_values(array_filter($transactions, fn($tx) => ($tx['date'] ?? '') <= $toDate));
}

$defaultAccount = isset($_GET['debet']) ? db_escape_string(trim($_GET['debet'])) : '';

$title = findtekst('1072|Kassekladde', $sprog_id) . ' - Aiia Import Transactions';
include_once(__DIR__ . '/includes/page_header.php');
?>
<style>
    #luk-btn { width: 5%; }
    .date-filter { display: flex; gap: .75rem; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; }
    .date-filter label { display: flex; align-items: center; gap: .4rem; font-size: .9rem; }
    .date-filter input[type=date], .date-filter input[type=text] { border: 1px solid #ccc; border-radius: 6px; padding: .35rem .6rem; font-size: .9rem; }
    .toolbar { display: flex; gap: .5rem; align-items: center; margin-bottom: .75rem; flex-wrap: wrap; }
    table { border-collapse: separate; border-spacing: 0 3px; width: 100%; }
    th { background-color: rgba(17,70,145,0.09); font-size: .85rem; font-weight: 600; border-radius: 0; padding: .45rem .65rem; white-space: nowrap; border-right: 1px solid rgba(0,0,0,0.5); }
    th:first-child { border-radius: 8px 0 0 8px; }
    th:last-child  { border-radius: 0 8px 8px 0; border-right: none; }
    td { background-color: rgba(255,255,255,0.5); border-radius: 0; padding: .4rem .65rem; border-right: 1px solid rgba(0,0,0,0.5); }
    td:first-child { border-radius: 8px 0 0 8px; }
    td:last-child  { border-radius: 0 8px 8px 0; border-right: none; }
    tbody tr:hover td { background-color: rgba(255,255,255,0.85); }
    tbody tr.row-highlight td { background-color: rgba(17,70,145,0.12); }
    tbody tr.row-highlight:hover td { background-color: rgba(17,70,145,0.18); }
    th.cb { width: 2rem; text-align: center; }
    td.cb { text-align: center; vertical-align: middle; }
    .col-right { text-align: right; }
    .col-center { text-align: center; }
    .col-nowrap { white-space: nowrap; }
    .col-text { width: 99%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 1px; }
    .amount-pos { color: #1a8a4a; font-weight: 600; }
    .amount-neg { color: #c0392b; font-weight: 600; }
    .btn-import { background: #114691; color: #fff; border: none; padding: .5rem 1.25rem; border-radius: 6px; cursor: pointer; font-size: .9rem; font-weight: 600; }
    .btn-import:hover { background: #0d3470; }
    .btn-selall { background: #fff; border: 1px solid #ccc; padding: .4rem .9rem; border-radius: 6px; cursor: pointer; font-size: .85rem; }
    .pagination { display: flex; gap: .5rem; align-items: center; margin-top: .75rem; }
    .pagination button { background: #fff; border: 1px solid #ccc; padding: .35rem .8rem; border-radius: 6px; cursor: pointer; font-size: .85rem; }
    .pagination button:hover:not(:disabled) { background: #f5f5f5; }
    .pagination button:disabled { opacity: .4; cursor: default; }
    .pagination-info { font-size: .85rem; color: #555; }
    .btn-selall:hover { background: #f5f5f5; }
</style>
<link rel="stylesheet" href="../css/accountAutocomplete.css">
<script src="../javascript/accountAutocomplete.js" defer></script>
<?php
$backTarget = !empty($_SESSION['aiia_account'])
    ? htmlspecialchars("aiia_import.php?kladde_id=$kladde_id&change_account=1", ENT_QUOTES, $charset)
    : ($kladde_id
        ? htmlspecialchars("../finans/kassekladde.php?kladde_id=$kladde_id", ENT_QUOTES, $charset)
        : htmlspecialchars('../finans/kladdeliste.php', ENT_QUOTES, $charset));
$bban = htmlspecialchars($account['number']['bban'] ?? '', ENT_QUOTES, $charset);
$subTitle = $bban;
if ($hasFetch) {
    $subTitle .= ($subTitle ? ' &nbsp;·&nbsp; ' : '') . "$fromDate &nbsp;→&nbsp; $toDate";
}
?>
<div class="topline">
    <a id="luk-btn" class="topline-btn" href="<?= $backTarget ?>" accesskey="L"><?= $icon_back . findtekst('30|Tilbage', $sprog_id) ?></a>
    <div class="topline-center">
        <span class="center-title">
            <?= findtekst('1072|Kassekladde', $sprog_id) ?>
            &nbsp;•&nbsp; <?= htmlspecialchars($account['name']) ?>
            <span style="font-size:.8em;font-weight:400;opacity:.75;">&nbsp;<?= $subTitle ?></span>
        </span>
        <?php include(__DIR__ . '/includes/auth_check_icon.php'); ?>
    </div>
</div>
<div class="content-noside">

<form method="get" action="aiia_import.php" class="date-filter">
    <input type="hidden" name="kladde_id" value="<?= $kladde_id ?>">
    <label>Fra <input type="date" name="fromDate" value="<?= htmlspecialchars($fromDate) ?>" lang="da"></label>
    <label>Til <input type="date" name="toDate"   value="<?= htmlspecialchars($toDate) ?>"   lang="da"></label>
    <label><?= 'Modkonto:' // TODO: Translation ?> <input type="text" name="debet" id="debet-filter" value="<?= htmlspecialchars($defaultAccount) ?>" style="width:6rem"></label>
    <button type="submit" class="btn-import">Hent</button>
</form>

<?php if (!$hasFetch): ?>
<?php elseif ($httpCode !== 200): ?>
    <div class="error">
        Failed to fetch transactions (HTTP <?= $httpCode ?>): <?= htmlspecialchars($data['error'] ?? $response) ?>
    </div>
<?php elseif (empty($transactions)): ?>
    <p style="color:#666">No transactions found from <?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?>.</p>
<?php else: ?>
<form method="post" action="aiia_import.php?kladde_id=<?= $kladde_id ?>">
    <input type="hidden" name="debet" id="debet-import" value="<?= htmlspecialchars($defaultAccount) ?>">
    <div class="toolbar">
        <button type="button" class="btn-selall" id="selAll">Select all</button>
        <button type="button" class="btn-selall" id="selNone">Select none</button>
        <button type="submit" class="btn-import">Import selected into kassekladde</button>
    </div>
    <div id="table-wrap">
    <table>
        <thead>
            <tr>
                <th class="cb"><input type="checkbox" id="masterCb"></th>
                <th class="col-center col-nowrap">Date</th>
                <th class="col-text">Text</th>
                <th class="col-right">Amount</th>
                <th class="col-left">Currency</th>
                <th class="col-center">Type</th>
                <th class="col-right col-nowrap">Balance after</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($transactions as $tx):
            $amount   = $tx['amount']['value'] ?? null;
            $currency = $tx['amount']['currency'] ?? '';
            $amtClass = ($amount !== null && $amount < 0) ? 'amount-neg' : 'amount-pos';
            $amtFmt   = $amount !== null ? number_format($amount, 2, ',', '.') : '—';
            $balance  = isset($tx['balance']['value'])
                ? number_format($tx['balance']['value'], 2, ',', '.') . ' ' . ($tx['balance']['currency'] ?? '')
                : '—';
            $importPayload = json_encode([
                'date'      => $tx['date'] ?? date('Y-m-d'),
                'text'      => $tx['text'] ?? $tx['remittanceInformation'] ?? '',
                'amount'    => $amount,
                'id'        => $tx['id'] ?? '',
            ]);
        ?>
            <tr>
                <td class="cb">
                    <input type="checkbox" name="import[]" value="<?= htmlspecialchars($importPayload, ENT_QUOTES) ?>">
                </td>
                <td class="col-center col-nowrap"><?= htmlspecialchars($tx['date'] ?? '—') ?></td>
                <td class="col-text" title="<?= htmlspecialchars($tx['text'] ?? $tx['remittanceInformation'] ?? '') ?>"><?= htmlspecialchars($tx['text'] ?? $tx['remittanceInformation'] ?? '—') ?></td>
                <td class="col-right <?= $amtClass ?>"><?= htmlspecialchars($amtFmt) ?></td>
                <td><?= htmlspecialchars($currency) ?></td>
                <td class="col-center"><?= htmlspecialchars($tx['type'] ?? '—') ?></td>
                <td class="col-right col-nowrap"><?= htmlspecialchars($balance) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</form>
<?php endif; ?>

<script>
document.getElementById('debet-filter')?.addEventListener('input', function() {
    const hidden = document.getElementById('debet-import');
    if (hidden) hidden.value = this.value;
});
document.getElementById('masterCb')?.addEventListener('change', function() {
    document.querySelectorAll('input[name="import[]"]').forEach(cb => cb.checked = this.checked);
});

const PAGE_SIZE = 21;
const txRows = Array.from(document.querySelectorAll('tbody tr'));
let currentPage = 0;

function pageCount() { return Math.ceil(txRows.length / PAGE_SIZE); }

function showPage(n) {
    currentPage = n;
    txRows.forEach((row, i) => {
        row.style.display = (i >= n * PAGE_SIZE && i < (n + 1) * PAGE_SIZE) ? '' : 'none';
    });
    const prev = document.getElementById('page-prev');
    const next = document.getElementById('page-next');
    const info = document.getElementById('page-info');
    if (prev) prev.disabled = n === 0;
    if (next) next.disabled = n >= pageCount() - 1;
    if (info) info.textContent = `Side ${n + 1} af ${pageCount()}  (${txRows.length} posteringer)`;
}

if (txRows.length > PAGE_SIZE) {
    const bar = document.createElement('div');
    bar.className = 'pagination';
    bar.innerHTML = '<button type="button" id="page-prev">← Forrige</button>'
        + '<span id="page-info" class="pagination-info"></span>'
        + '<button type="button" id="page-next">Næste →</button>';
    document.querySelector('table').before(bar);
    document.getElementById('page-prev').addEventListener('click', () => showPage(currentPage - 1));
    document.getElementById('page-next').addEventListener('click', () => showPage(currentPage + 1));
    showPage(0);
    const wrap = document.getElementById('table-wrap');
    wrap.style.minHeight = wrap.offsetHeight + 'px';
}

txRows.forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', function(e) {
        setHighlight(txRows.indexOf(this));
        if (e.target.type === 'checkbox') return;
        const cb = this.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = !cb.checked;
    });
});
document.getElementById('selAll')?.addEventListener('click', function() {
    document.querySelectorAll('input[name="import[]"]').forEach(cb => cb.checked = true);
    document.getElementById('masterCb').checked = true;
});
document.getElementById('selNone')?.addEventListener('click', function() {
    document.querySelectorAll('input[name="import[]"]').forEach(cb => cb.checked = false);
    document.getElementById('masterCb').checked = false;
});

// --- Keyboard shortcuts ---
const fetchedFrom = <?= json_encode($hasFetch ? $fromDate : null) ?>;
const fetchedTo   = <?= json_encode($hasFetch ? $toDate   : null) ?>;
let highlightedIdx = -1;

function setHighlight(idx) {
    if (idx < 0 || idx >= txRows.length) return;
    txRows.forEach((r, i) => r.classList.toggle('row-highlight', i === idx));
    highlightedIdx = idx;
    const targetPage = Math.floor(idx / PAGE_SIZE);
    if (targetPage !== currentPage) showPage(targetPage);
    txRows[idx].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function selectAll(checked) {
    document.querySelectorAll('input[name="import[]"]').forEach(cb => cb.checked = checked);
    const mc = document.getElementById('masterCb');
    if (mc) mc.checked = checked;
}

document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'k') {
        e.preventDefault();
        document.getElementById('debet-filter')?.focus();
        return;
    }

    const inInput = ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName);

    if (e.key === 'Enter' && inInput && document.activeElement?.closest('.date-filter')) {
        e.preventDefault();
        const fromVal = document.querySelector('[name="fromDate"]')?.value ?? '';
        const toVal   = document.querySelector('[name="toDate"]')?.value ?? '';
        const needsFetch = fetchedFrom === null || fromVal !== fetchedFrom || toVal !== fetchedTo;
        if (needsFetch) {
            document.activeElement.closest('form').submit();
        } else {
            const first = txRows.find(r => r.style.display !== 'none');
            if (first) setHighlight(txRows.indexOf(first));
        }
        return;
    }

    if (inInput) return;

    switch (e.key) {
        case 'ArrowLeft':
            e.preventDefault();
            if (currentPage > 0) {
                if (highlightedIdx >= 0) setHighlight(Math.max(0, highlightedIdx - PAGE_SIZE));
                else showPage(currentPage - 1);
            }
            break;
        case 'ArrowRight':
            e.preventDefault();
            if (currentPage < pageCount() - 1) {
                if (highlightedIdx >= 0) setHighlight(Math.min(txRows.length - 1, highlightedIdx + PAGE_SIZE));
                else showPage(currentPage + 1);
            }
            break;
        case 'ArrowUp':
            e.preventDefault();
            setHighlight(highlightedIdx <= 0 ? 0 : highlightedIdx - 1);
            break;
        case 'ArrowDown':
            e.preventDefault();
            setHighlight(highlightedIdx < txRows.length - 1 ? highlightedIdx + 1 : highlightedIdx);
            break;
        case ' ':
            e.preventDefault();
            if (highlightedIdx >= 0) {
                const cb = txRows[highlightedIdx].querySelector('input[type="checkbox"]');
                if (cb) cb.checked = !cb.checked;
            }
            break;
        case '+':
            selectAll(true);
            break;
        case '-':
            selectAll(false);
            break;
        case 'Enter':
            e.preventDefault();
            if (e.ctrlKey) {
                document.querySelector('button.btn-import[type="submit"]')?.click();
            } else if (highlightedIdx >= 0) {
                const cb = txRows[highlightedIdx].querySelector('input[type="checkbox"]');
                if (cb) cb.checked = !cb.checked;
            }
            break;
    }
});
</script>

</div>
