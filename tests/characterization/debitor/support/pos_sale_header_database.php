<?php
// 20261021 CL/MJ SD-758 Run the POS sale-header queries against an isolated in-memory database.

namespace Saldi\Tests\PosSaleHeader;

use PDO;

function db_select($sql, $source = null) {
    $GLOBALS['posSaleHeaderSql'][] = $sql;
    return $GLOBALS['posSaleHeaderDb']->query($sql);
}

function db_fetch_array($statement) {
    if ($statement === false) {
        return false;
    }
    return $statement->fetch(PDO::FETCH_ASSOC);
}

function db_modify($sql, $source = null) {
    $GLOBALS['posSaleHeaderSql'][] = $sql;
    $GLOBALS['posSaleHeaderDb']->exec($sql);
    return "0\tquery accepted";
}

function db_escape_string($value) {
    return str_replace("'", "''", (string)$value);
}

/** The subset of includes/std_func.php's ifset() these functions use: array key with a default. */
function ifset($arrayOrVar, $key = null, $default = null) {
    if ($key === null) {
        return $arrayOrVar ?? $default;
    }
    if (is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar)) {
        return $arrayOrVar[$key];
    }
    return $default;
}

function findtekst($text, $language) {
    return explode('|', (string)$text, 2)[1] ?? (string)$text;
}
