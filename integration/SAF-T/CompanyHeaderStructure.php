<?php
include_once __DIR__ . '/AddressStructure.php';
include_once __DIR__ . '/ContactInformationStructure.php';
include_once __DIR__ . '/TaxIDStructure.php';
include_once __DIR__ . '/BankAccountStructure.php';
class CompanyHeaderStructure {
    /** M (either CVR or RegistrationNumber) */
    public string $CVR;
    /** M */
    public string $Name;
    /**
     * M
     * @var AddressStructure[]
     */
    public array $Address;
    /**
     * O
     * @var ContactInformationStructure[]|null
     */
    public ?array $Contact = null;
    /**
     * O
     * @var TaxIDStructure[]|null
     */
    public ?array $TaxRegistration = null;
    /**
     * M
     * @var BankAccountStructure[]
     */
    public array $BankAccount;

    public function __construct(SaftParameters $params) {
        $this->Address = [new AddressStructure($params)];
        $this->BankAccount = [new BankAccountStructure($params)];
    }
}
?>
