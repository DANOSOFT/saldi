<?php
/**
 * Either IBANNumber, or BankAccountNumber (with SortCode and optionally BankAccountName). Never both.
 */
class BankAccountStructure {
    /** M (either) */
    public ?string $IBANNumber = null;
    /** M (or) */
    public ?string $BankAccountNumber = null;
    /** O */
    public ?string $BankAccountName = null;
    /** M (or) */
    public ?string $SortCode = null;
    /** MIFU */
    public ?string $BIC = null;
    /** MIFU */
    public ?string $CurrencyCode = null;
    /** MIFU */
    public ?string $AccountID = null;
    public function __construct(SaftParameters $params) {
        
    }
}
?>
