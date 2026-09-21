<?php
// 20260920 CDX/LH Preserve unfinished saved journal entries by rejecting posting before any cleanup.

/**
 * A journal closes as a whole; incomplete saved content must stay editable.
 * Empty placeholders have no description, invoice, amount or account.
 *
 * @return string|null Actionable validation error, or null when posting may continue.
 */
function journalPostingIncompleteError($journalId) {
    $journalId = (int) $journalId;
    $sql = "SELECT id, bilag FROM kassekladde WHERE kladde_id = $journalId
        AND COALESCE(debet, 0) = 0 AND COALESCE(kredit, 0) = 0
        AND (COALESCE(amount, 0) != 0 OR TRIM(COALESCE(beskrivelse, '')) != ''
            OR TRIM(COALESCE(faktura, '')) != '') ORDER BY bilag, id";
    $rows = db_select($sql, __FILE__ . ' line ' . __LINE__);
    $vouchers = [];
    while ($row = db_fetch_array($rows)) {
        $vouchers[] = (string) (int) $row['bilag'];
    }
    if (!$vouchers) return null;
    return 'Kladden indeholder ufærdige linjer uden konto i bilag ' . implode(', ', array_unique($vouchers))
        . '. Udfyld eller slet linjerne før simulering eller bogføring. Ingen linjer er slettet eller bogført.';
}

/**
 * Check actual generated rows before committing, preserving the journal-wide
 * balancing rule and valid balanced entries with three-decimal VAT amounts.
 *
 * @return string|null Actionable rounding error, or null when generated rows balance.
 */
function journalPostedBalanceError($journalId, $simulation) {
    $journalId = (int)$journalId;
    $table = $simulation ? 'simulering' : 'transaktioner';
    $sql = "SELECT ROUND(COALESCE(SUM(COALESCE(debet,0)-COALESCE(kredit,0)),0),2) AS difference
        FROM $table WHERE kladde_id=$journalId";
    $row = db_fetch_array(db_select($sql, __FILE__ . ' line ' . __LINE__));
    $difference = (float)$row['difference'];
    if (abs($difference) < 0.005) return null;
    return 'Bogføring afbrudt: afrunding giver en difference på ' . number_format($difference,2,',','.')
        . ' i kladde ' . $journalId . '. Kontroller beløb og valutakurser, og afstem kladden til øre.'
        . ' Ingen posteringer eller saldi er gemt; kladden er stadig åben.';
}
