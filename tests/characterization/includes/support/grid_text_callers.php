<?php
// 20261125 CDX/NTR Observe journal account lookups and card links without bootstrapping the page.

namespace GridTextCallersFixture;

final class State
{
    public static array $queries = [];
    public static ?string $linkedAccount = null;
}

/** @return string SQL literal body with standard apostrophe escaping. */
function db_escape_string($value): string
{
    return str_replace("'", "''", (string) $value);
}

/** @return object Capture read-only lookup SQL. */
function db_select($sql, $source): object
{
    State::$queries[] = $sql;
    return new \stdClass();
}

/** @return array{firmanavn: string, beskrivelse: string} Synthetic account tooltip text. */
function db_fetch_array($result): array
{
    return ['firmanavn' => "Company's & <name>", 'beskrivelse' => "Company's & <name>"];
}

/** @return null No VAT override in these read-only fixtures. */
function get_saved_vat_code($row, $field)
{
    return null;
}

/** @return string Card URL built from the unmodified account number. */
function kk_account_card_link($type, $account, $journal): string
{
    State::$linkedAccount = $account;
    return 'card.php?' . http_build_query(['type' => $type, 'account' => $account, 'journal' => $journal]);
}
