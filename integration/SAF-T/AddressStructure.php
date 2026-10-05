<?php
class AddressStructure {
    /** M */
    public string $StreetName;
    /** MIFU */
    public ?string $Number = null;
    /** O */
    public ?string $AdditionalAddressDetail = null;
    /** O */
    public ?string $Building = null;
    /** M */
    public string $City;
    /** M */
    public string $PostalCode;
    /** O */
    public ?string $Region = null;
    /** M */
    public string $Country;
    /** O */
    public ?string $AddressType = null;
    public function __construct(SaftParameters $params) {
        
    }
}
?>
