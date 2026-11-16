<?php
// 20261113 CDX/NTR Format account activity from Unix seconds or legacy calendar dates without passing text to date().

/**
 * Format regnskab.sidst for the admin account views.
 * @param int|string|null $value Stored Unix seconds, an ISO date/datetime, or a missing activity value.
 * @return string Date as d-m-Y, or '-' when no valid activity date is available.
 */
function lastActivityDate($value): string
{
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '-';
    }
    if (is_numeric($value)) {
        $timestamp = (int) $value;
    } else {
        // Legacy test registry rows used calendar dates instead of Unix seconds.
        if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})(?:[ T].*)?$/D', $value, $parts)
            || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || str_contains($value, "\0")) {
            return '-';
        }
        $timestamp = strtotime($value);
    }
    return $timestamp === false || $timestamp === 0 ? '-' : date('d-m-Y', $timestamp);
}
