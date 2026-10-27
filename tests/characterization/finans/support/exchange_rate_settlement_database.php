<?php
// 20261021 CL/MJ MB-79 Run the open-post settlement queries against an isolated in-memory database.
// 20261027 CL/LH MB-79 Stubs for findtekst() and postingLineAmounts(), which the confirmation page's lookups call.

namespace Saldi\Tests\ExchangeRateSettlement;

use PDO;

function db_select($sql, $source = null) {
    $GLOBALS['exchangeRateSettlementSql'][] = $sql;
    return $GLOBALS['exchangeRateSettlementDb']->query($sql);
}

function db_fetch_array($statement) {
    if ($statement === false) {
        return false;
    }
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function db_modify($sql, $source = null) {
    $GLOBALS['exchangeRateSettlementSql'][] = $sql;
    $GLOBALS['exchangeRateSettlementDb']->exec($sql);
    return "0\tquery accepted";
}

function db_escape_string($value) {
    return str_replace("'", "''", (string)$value);
}

function afrund($tal, $decimaler) {
    return round((float)$tal, (int)$decimaler);
}

function dkdecimal($tal, $decimaler = null) {
    return number_format((float)$tal, $decimaler === null ? 2 : (int)$decimaler, ',', '.');
}

function transaktion($action) {
    $GLOBALS['exchangeRateSettlementTransactions'][] = $action;
}

// 20261027 CL/LH MB-79 rateDifferenceAccountError() names the account through findtekst(); the stub returns
// the default text, as an installation without a translation does.
function findtekst($text, $language = null) {
    $parts = explode('|', (string)$text, 2);
    return $parts[1] ?? $parts[0];
}

/**
 * The parts of finans/bogfor_includes/postingRules.php postingLineAmounts() that journalRateDifferences()
 * reads - the side types, the control accounts, the rate and the difference account - from the fixture's
 * adresser, grupper and valuta tables.
 */
function postingLineAmounts($row, $preview = false) {
    $db = $GLOBALS['exchangeRateSettlementDb'];
    $line = ['d_type' => $row['d_type'], 'k_type' => $row['k_type'], 'debet' => (string)$row['debet'],
        'kredit' => (string)$row['kredit'], 'diffkonto' => null, 'valutakurs' => null];
    if (!$row['debet']) {
        $line['d_type'] = 'F';
    }
    if (!$row['kredit']) {
        $line['k_type'] = 'F';
    }
    foreach (['d' => 'debet', 'k' => 'kredit'] as $side => $field) {
        $type = $line[$side . '_type'];
        if (($type == 'D' || $type == 'K') && $row[$field] > 0) {
            $stmt = $db->prepare("SELECT g.box2 FROM adresser a JOIN grupper g ON g.art = ? AND g.kodenr = a.gruppe
                WHERE a.kontonr = ? AND a.art = ?");
            $stmt->execute([$type . 'G', (string)$row[$field], $type]);
            $line[$field] = (string)$stmt->fetchColumn();
        }
    }
    if ($row['valuta']) {
        $stmt = $db->prepare("SELECT kurs FROM valuta WHERE gruppe = ? AND valdate <= ? ORDER BY valdate DESC LIMIT 1");
        $stmt->execute([$row['valuta'], $row['transdate']]);
        $line['valutakurs'] = (float)$stmt->fetchColumn();
        $stmt = $db->prepare("SELECT box3 FROM grupper WHERE art = 'VK' AND kodenr = ?");
        $stmt->execute([$row['valuta']]);
        $line['diffkonto'] = (string)$stmt->fetchColumn();
    }
    return $line;
}
