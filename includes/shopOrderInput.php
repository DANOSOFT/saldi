<?php
// 20260920 CDX/LH Strict webshop identifiers and ungrouped decimal amounts.

function saldiShopIntegerId($value, $allowZero = false) {
    if (!is_string($value) && !is_int($value)) {
        return false;
    }
    $text = (string)$value;
    if (!preg_match('/^[0-9]+$/D', $text)) {
        return false;
    }
    $digits = ltrim($text, '0');
    if ($digits === '') {
        return $allowZero;
    }
    $maximum = (string)PHP_INT_MAX;
    return strlen($digits) < strlen($maximum) ||
        (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) <= 0);
}

function saldiShopDecimal($value) {
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return null;
    }
    if ($value === '' || $value === null) {
        return null;
    }
    $text = trim((string)$value);
    if (!preg_match('/^[+-]?[0-9]+(?:[.,][0-9]+)?$/D', $text)) {
        return null;
    }
    $amount = (float)str_replace(',', '.', $text);
    return is_finite($amount) ? $amount : null;
}
