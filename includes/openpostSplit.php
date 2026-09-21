<?php
// 20260921 CDX/LH Split either invoice or payment atomically without changing its sign or total claim.
// 20260921 CDX/LH Retain invoice references on invoice remainders; keep payment surplus unallocated.

/**
 * Save an open-item reference and optionally reduce its displayed amount.
 * The existing screen displays some foreign items in the account currency;
 * conversionRate is its source-to-display factor. Lock and compare the stored
 * amount before accepting a reduction so a retry cannot split a stale form.
 *
 * @return array{ok: bool, split: bool, error: string}
 */
function saldiSaveOpenpostSplit($postId, $expectedAmount, $newAmount, $invoice, $conversionRate = 1)
{
    global $db_modify_fejl, $webservice;
    $error = 'Posteringen kunne ikke opdateres; ingen ændringer er gemt';
    if (!ctype_digit((string)$postId) || (int)$postId < 1 || !is_scalar($invoice)) {
        return ['ok' => false, 'split' => false, 'error' => $error];
    }
    foreach ([$expectedAmount, $newAmount, $conversionRate] as $value) {
        if (!is_numeric($value) || !is_finite((float)$value)) {
            return ['ok' => false, 'split' => false, 'error' => 'Ugyldigt beløb eller valutakurs'];
        }
    }
    $expectedAmount = afrund((float)$expectedAmount, 2);
    $newAmount = afrund((float)$newAmount, 2);
    $conversionRate = (float)$conversionRate;
    $split = $newAmount != $expectedAmount;
    if ($conversionRate <= 0 || ($split && ($newAmount == 0 || $newAmount * $expectedAmount <= 0
        || abs($newAmount) >= abs($expectedAmount)))) {
        return ['ok' => false, 'split' => false, 'error' => 'Beløbet skal have samme fortegn og være større end nul og mindre end det oprindelige beløb i absolut værdi'];
    }
    $invoice = trim((string)$invoice);
    if ($split && $invoice === '') {
        return ['ok' => false, 'split' => false, 'error' => 'For at opsplitte skal posteringen tilknyttes et gyldigt fakturanummer'];
    }
    $id = (int)$postId;
    $oldWebservice = $webservice ?? false;
    $webservice = true;
    $open = false;
    try {
        if (!transaktion('begin')) {
            throw new RuntimeException($error);
        }
        $open = true;
        if ($db_modify_fejl) {
            throw new RuntimeException($error);
        }
        $result = db_select("SELECT * FROM openpost WHERE id=$id FOR UPDATE", __FILE__ . ' linje ' . __LINE__);
        $row = $result ? db_fetch_array($result) : false;
        if (!$row || (string)$row['udlignet'] === '1' || (int)$row['udlign_id'] > 0
            || afrund((float)$row['amount'] * $conversionRate, 2) != $expectedAmount) {
            throw new RuntimeException('Posteringen er ændret eller udlignet; genindlæs før opdatering');
        }
        $write = static function ($sql) use ($error) {
            global $db_modify_fejl;
            $result = db_modify($sql, __FILE__ . ' linje ' . __LINE__);
            if ($result === false || $db_modify_fejl || (is_string($result) && substr($result, 0, 2) === "1\t")) {
                throw new RuntimeException($error);
            }
        };
        if ($split) {
            $sourceAmount = afrund($newAmount / $conversionRate, 2);
            $storedAmount = (float)$row['amount'];
            if ($sourceAmount == 0 || $sourceAmount * $storedAmount <= 0 || abs($sourceAmount) >= abs($storedAmount)) {
                throw new RuntimeException('Beløbet kan ikke opsplittes med denne valutakurs');
            }
            $accountId = (int)$row['konto_id'];
            $accountResult = db_select("SELECT art FROM adresser WHERE id=$accountId", __FILE__ . ' linje ' . __LINE__);
            $account = $accountResult ? db_fetch_array($accountResult) : false;
            $accountType = $account ? substr((string)$account['art'], 0, 1) : '';
            if (!in_array($accountType, ['D', 'K'], true)) {
                throw new RuntimeException('Kontoens type kunne ikke bestemmes; posteringen er ikke opsplittet');
            }
            // Positive debtor and negative creditor items are invoice claims.
            // A payment surplus remains unallocated as in the existing workflow.
            $invoiceRemainder = ($accountType === 'D' && $storedAmount > 0)
                || ($accountType === 'K' && $storedAmount < 0);
            $remainderInvoiceSql = $invoiceRemainder ? 'faktnr' : "''";
            // Copy provenance in SQL, including NULL dates and apostrophes. The
            // remainder retains the invoice origin and never creates GL entries.
            $optionalColumns = '';
            foreach (['uxtid', 'betal_id', 'betalings_id'] as $column) {
                if (array_key_exists($column, $row)) {
                    $optionalColumns .= ',' . $column;
                }
            }
            $write("INSERT INTO openpost (konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,kladde_id,udlign_id,valuta,valutakurs,bilag_id,projekt,forfaldsdate$optionalColumns)
                SELECT konto_id,konto_nr,$remainderInvoiceSql,amount-($sourceAmount),refnr,beskrivelse,'0',transdate,kladde_id,0,valuta,valutakurs,bilag_id,projekt,forfaldsdate$optionalColumns FROM openpost WHERE id=$id");
            $write("UPDATE openpost SET amount=$sourceAmount WHERE id=$id");
        }
        $invoice = db_escape_string($invoice);
        $write("UPDATE openpost SET faktnr='$invoice' WHERE id=$id");
        if (!transaktion('commit') || $db_modify_fejl) {
            $open = false;
            throw new RuntimeException($error);
        }
        $open = false;
        return ['ok' => true, 'split' => $split, 'error' => ''];
    } catch (Throwable $exception) {
        if ($open) {
            transaktion('rollback');
        }
        return ['ok' => false, 'split' => false, 'error' => $exception->getMessage()];
    } finally {
        $webservice = $oldWebservice;
    }
}
