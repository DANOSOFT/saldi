<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/get_accounts.php --- patch 0.0.1 --- 2026-06-01 ---
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
// 20260601 NTR - Initial version. Lists bank accounts via Aiia /v2/accounts.
/**
 * GET /v2/accounts — fetches all bank accounts for the authenticated user.
 * Each entry from accounts[] is stored in $_SESSION['aiia_account'] when selected.
 *
 * @return array{
 *   accounts: array<array{
 *     id:               string,      Opaque account identifier. Primary key for transactions and payments.
 *     providerId:       string,      Bank/provider identifier (e.g. "TestDataBank1").
 *     name:             string,      Human-readable account name (e.g. "Checking Account").
 *     number: array{
 *       bbanType:       string,      Country code for the BBAN format (e.g. "DK").
 *       bban:           string,      BBAN in display format (e.g. "0001-204386946").
 *       iban:           string,      Full IBAN.
 *       card:           string|null, Card number, or null if not applicable.
 *       bbanParsed: array{
 *         bankCode:     string,      Bank/registration code portion of the BBAN.
 *         accountNumber: string,     Account number portion of the BBAN.
 *       }
 *     },
 *     bookedBalance: array{
 *       value:          float,       Settled balance (cleared transactions only). Negative = overdrawn.
 *       currency:       string,      ISO 4217 currency code (e.g. "DKK").
 *     },
 *     availableBalance: array|null,  Balance including pending transactions, or null if unavailable.
 *     type:             string,      Account type (e.g. "Consumption", "Savings").
 *     features: array{
 *       queryable:         bool,     Whether transaction history can be fetched.
 *       psdPaymentAccount: bool,     Whether the account is a PSD2 payment account.
 *       paymentFrom:       bool,     Whether outgoing payments are supported.
 *       paymentTo:         bool,     Whether incoming payments are supported.
 *     }
 *   }>,
 *   pagingToken: string|null         Token for fetching the next page, or null if this is the last page.
 * }
 */

// Handle account selection POST — stores chosen account in session and returns to caller via history.back()
@session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['account'])) {
    $account = json_decode($_POST['account'], true);
    if (is_array($account)) {
        $_SESSION['aiia_account'] = $account;
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

@session_start();
$s_id = session_id();
include_once(__DIR__ . '/../includes/connect.php');
include_once(__DIR__ . '/../includes/online.php');
include_once(__DIR__ . '/../includes/std_func.php');
['needsInit' => $needsInit] = include_once(__DIR__ . '/includes/auth_check.php');
$OAuth = $_SESSION['OAuth'] ?? null;

if ($needsInit) {
    header('Location: login.php');
    exit;
}

$sql = "SELECT
            MAX(CASE WHEN var_name = 'Client ID'     THEN var_value END),
            MAX(CASE WHEN var_name = 'Client Secret' THEN var_value END)
        FROM settings
        WHERE var_grp = 'OAuth'
          AND var_name IN ('Client ID', 'Client Secret')";
[$clientID, $clientSecret] = db_fetch_row(db_select($sql, __FILE__ . " linje " . __LINE__, true));

$ch = curl_init('https://api.nordicapigateway.com/v2/accounts');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'X-Client-Id: '     . $clientID,
        'X-Client-Secret: ' . $clientSecret,
        'Authorization: Bearer ' . $OAuth['session']['accessToken'],
    ],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

$data      = json_decode($response, true);
$accounts  = $data['accounts'] ?? [];
$kladde_id = isset($_GET['kladde_id']) ? intval($_GET['kladde_id']) : 0;
if (!$kladde_id && isset($_GET['return'])) {
    parse_str((string) parse_url($_GET['return'], PHP_URL_QUERY), $returnParams);
    $kladde_id = intval($returnParams['kladde_id'] ?? 0);
}

$title = findtekst('1072|Kassekladde', $sprog_id) . ' - Aiia Choose Account';
include_once(__DIR__ . '/includes/page_header.php');
?>
<style>
    .accounts { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
    .account-card {
        background: #fff;
        border-radius: 10px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 1px 4px rgba(0,0,0,.08);
        cursor: pointer;
        transition: box-shadow .15s, transform .15s;
        border: 1px solid #e8eaf0;
    }
    .account-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.13); transform: translateY(-2px); }
    .account-name { font-size: 1rem; font-weight: 600; margin-bottom: .35rem; }
    .account-bban { font-size: .82rem; color: #666; margin-bottom: .75rem; letter-spacing: .02em; }
    .account-balance { font-size: 1.3rem; font-weight: 700; }
    .balance-positive { color: #1a8a4a; }
    .balance-negative { color: #c0392b; }
    .account-type { font-size: .75rem; color: #888; margin-top: .4rem; text-transform: uppercase; letter-spacing: .06em; }
</style>
<div class="topline">
    <a class="topline-btn" href="<?= htmlspecialchars("../finans/kassekladde.php?kladde_id=$kladde_id", ENT_QUOTES, $charset) ?>" accesskey="L"><?= $icon_back . findtekst('30|Tilbage', $sprog_id) ?></a>
    <div class="topline-center">
        <span class="center-title"><?= findtekst('1072|Kassekladde', $sprog_id) ?></span>
        <?php include(__DIR__ . '/includes/auth_check_icon.php'); ?>
    </div>
</div>
<div class="content-noside">

<?php if ($httpCode !== 200): ?>
    <div class="error">
        Failed to fetch accounts (HTTP <?= $httpCode ?>): <?= htmlspecialchars($data['error'] ?? $response) ?>
    </div>
<?php elseif (empty($accounts)): ?>
    <p style="color:#666">No accounts found.</p>
<?php else: ?>
    <div class="accounts">
    <?php foreach ($accounts as $account):
        $balance  = $account['bookedBalance']['value'] ?? null;
        $currency = $account['bookedBalance']['currency'] ?? '';
        $bban     = $account['number']['bban'] ?? ($account['number']['iban'] ?? '');
        $balClass = ($balance !== null && $balance < 0) ? 'balance-negative' : 'balance-positive';
        $balFmt   = $balance !== null
            ? number_format($balance, 2, ',', '.') . ' ' . $currency
            : '—';
    ?>
        <div class="account-card" data-account="<?= htmlspecialchars(json_encode($account), ENT_QUOTES) ?>">
            <div class="account-name"><?= htmlspecialchars($account['name']) ?></div>
            <div class="account-bban"><?= htmlspecialchars($bban) ?></div>
            <div class="account-balance <?= $balClass ?>"><?= htmlspecialchars($balFmt) ?></div>
            <div class="account-type"><?= htmlspecialchars($account['type'] ?? '') ?></div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
document.querySelectorAll('.account-card').forEach(function(card) {
    card.addEventListener('click', function() {
        fetch('get_accounts.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'account=' + encodeURIComponent(card.dataset.account)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                var returnUrl = new URLSearchParams(window.location.search).get('return');
                window.location.href = returnUrl || 'aiia_import.php';
            }
        });
    });
});
</script>

</div>
