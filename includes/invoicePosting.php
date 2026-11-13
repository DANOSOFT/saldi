<?php
// 20261113 CDX/NTR Dispatch invoice posting and receivable preparation by effective order type (SD-599).
// 20261113 CDX/NTR Document posting parameters and required supplier/debtor receivable context shapes.

/**
 * Invoice ledger posting, after delivery and invoice-number assignment.
 *
 * Callers provide the initialized legacy database/session and fiscal-year globals,
 * own the transaction, and retain each engine's existing failure behavior. Supplier
 * posting still redirects, rolls back and exits on validation failures; debtor
 * posting returns its existing status string. Neither implementation commits.
 */
interface SaldiInvoicePosting
{
    /**
     * Post an eligible invoice using the initialized caller-owned transaction.
     *
     * @param int|string $id Order identifier, or the legacy comma-separated POS order list.
     * @param string $source Calling flow, including webservice or Dagsafslutning.
     * @return string|null Existing engine result; supplier success has no return value.
     */
    public function post($id, $source);

    /**
     * Prepare the open-item description and any credit-reference settlement.
     *
     * Supplier contexts contain invoiceNumber and receiptNumber; debtor contexts
     * provide the order, payment and reference fields assembled by their engine.
     *
     * @param array{invoiceNumber:mixed, receiptNumber:mixed}|array{
     *   invoiceNumber:mixed, openItem:mixed, orderId:mixed, source:mixed,
     *   webservice:mixed, paymentId:mixed, customerOrderNumber:mixed, paymentTerms:mixed,
     *   register:mixed, referenceOrderId:mixed, customerId:mixed, total:mixed,
     *   transactionDate:mixed
     * } $context Required supplier fields or the complete debtor/POS engine context.
     * @return array{description:string, settled:int, settlementDate:?string, settlementId:?int}
     */
    public function prepareReceivable(array $context): array;
}

/**
 * Resolve stored or effective art once, where an order enters a posting engine.
 *
 * @param string $art Effective order type: DO, DK, PO, KO or KK; legacy debtor types use sales.
 * @return SaldiInvoicePosting The adapter for the selected order type.
 */
function invoice_posting_for_type($art): SaldiInvoicePosting
{
    switch ($art) {
        case 'DK':
            return new SaldiCreditNotePosting();
        case 'PO':
            return new SaldiPosInvoicePosting();
        case 'KO':
            return new SaldiPurchaseInvoicePosting();
        case 'KK':
            return new SaldiPurchaseCreditPosting();
        default:
            // Other legacy debtor types retain their existing sales behavior.
            return new SaldiSalesInvoicePosting();
    }
}

class SaldiSalesInvoicePosting implements SaldiInvoicePosting
{
    public function post($id, $source)
    {
        require_once __DIR__ . '/salesInvoicePosting.php';
        return post_sales_invoice($id, $source);
    }

    public function prepareReceivable(array $context): array
    {
        if ($context['openItem']) {
            $description = 'Faktura - ' . $context['invoiceNumber'];
        } elseif ($context['webservice']) {
            $description = 'Web: ' . $context['customerOrderNumber'] . ', Fakt: ' . $context['invoiceNumber'];
        } elseif ($context['paymentTerms'] === 'Kontant') {
            $description = 'Kontantsalg: Faktura - ' . $context['invoiceNumber'];
        } else {
            $description = 'Kreditkort salg: Faktura - ' . $context['invoiceNumber'];
        }
        return ['description' => $description, 'settled' => 0, 'settlementDate' => null, 'settlementId' => null];
    }
}

final class SaldiCreditNotePosting extends SaldiSalesInvoicePosting
{
    public function prepareReceivable(array $context): array
    {
        if (!$context['openItem'] || !$context['referenceOrderId']) {
            return parent::prepareReceivable($context);
        }
        $result = ['description' => 'Kreditnota - ' . $context['invoiceNumber'], 'settled' => 0, 'settlementDate' => null, 'settlementId' => null];
        $referenceOrderId = (int)$context['referenceOrderId'];
        $customerId = (int)$context['customerId'];
        $oppositeAmount = -(float)$context['total'];
        $reference = db_fetch_array(db_select("select fakturanr,fakturadate from ordrer where id='$referenceOrderId'", __FILE__ . ' linje ' . __LINE__));
        $invoiceNumber = db_escape_string($reference['fakturanr']);
        $invoiceDate = db_escape_string($reference['fakturadate']);
        $match = db_fetch_array(db_select("select * from openpost where konto_id='$customerId' and amount='$oppositeAmount' and faktnr='$invoiceNumber' and transdate='$invoiceDate' and udlignet != '1'", __FILE__ . ' linje ' . __LINE__));
        if ($match) {
            $result['settlementDate'] = max($context['transactionDate'], $match['transdate']);
            $last = db_fetch_array(db_select('select max(udlign_id) as udlign_id from openpost', __FILE__ . ' linje ' . __LINE__));
            $result['settlementId'] = (int)$last['udlign_id'] + 1;
            $date = db_escape_string($result['settlementDate']);
            $settlementId = $result['settlementId'];
            db_modify("update openpost set udlignet='1',udlign_date='$date',udlign_id='$settlementId' where konto_id='$customerId' and amount='$oppositeAmount' and faktnr='$invoiceNumber' and transdate='$invoiceDate'", __FILE__ . ' linje ' . __LINE__);
            $result['settled'] = 1;
        }
        return $result;
    }
}

final class SaldiPosInvoicePosting extends SaldiSalesInvoicePosting
{
    public function prepareReceivable(array $context): array
    {
        $description = is_numeric($context['orderId']) ? 'Bon - ' . $context['invoiceNumber'] : 'Kontantsalg kasse - ' . $context['register'];
        if ($context['source'] === 'Dagsafslutning') {
            $description = $context['source'] . ' - kassenr: ' . $context['register'];
        } elseif ($context['webservice'] && $context['paymentId']) {
            $description = 'Web: ' . $context['customerOrderNumber'] . ', Bet.ID: ' . $context['paymentId'];
        }
        return ['description' => $description, 'settled' => 0, 'settlementDate' => null, 'settlementId' => null];
    }
}

class SaldiPurchaseInvoicePosting implements SaldiInvoicePosting
{
    public function post($id, $source)
    {
        require_once __DIR__ . '/purchaseInvoicePosting.php';
        return post_purchase_invoice($id);
    }

    public function prepareReceivable(array $context): array
    {
        return ['description' => 'Lev. fakt.nr:' . $context['invoiceNumber'] . ', modt.nr: ' . $context['receiptNumber'], 'settled' => 0, 'settlementDate' => null, 'settlementId' => null];
    }
}

final class SaldiPurchaseCreditPosting extends SaldiPurchaseInvoicePosting
{
    public function prepareReceivable(array $context): array
    {
        return ['description' => 'Lev. kn.nr: ' . $context['invoiceNumber'] . ', modt. nr ' . $context['receiptNumber'], 'settled' => 0, 'settlementDate' => null, 'settlementId' => null];
    }
}
