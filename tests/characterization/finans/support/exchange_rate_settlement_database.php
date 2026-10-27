<?php
// 20261021 CL/MJ MB-79 Run the open-post settlement queries against an isolated in-memory database.

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
