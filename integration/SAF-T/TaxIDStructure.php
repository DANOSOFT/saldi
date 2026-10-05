<?php
class TaxIDStructure {
    /** M */
    public string $TaxRegistrationNumber;
    /** MIFU */
    public ?string $TaxType = null;
    /** O */
    public ?string $TaxNumber = null;
    /** O */
    public ?string $TaxAuthority = null;
    /** MIFU */
    public ?string $Country = null;
    /** O */
    public ?string $TaxVerificationDate = null;
    public function __construct(SaftParameters $params) {
        
    }
}
?>
