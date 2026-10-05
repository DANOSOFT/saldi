<?php
include_once __DIR__ . '/PersonNameStructure.php';
class ContactInformationStructure {
    /**
     * MIFU
     * @var PersonNameStructure[]|null
     */
    public ?array $ContactPerson = null;
    /**
     * MIFU
     * @var string[]|null
     */
    public ?array $Telephone = null;
    /**
     * MIFU
     * @var string[]|null
     */
    public ?array $Fax = null;
    /**
     * MIFU
     * @var string[]|null
     */
    public ?array $Email = null;
    /**
     * MIFU
     * @var string[]|null
     */
    public ?array $Website = null;
    /**
     * MIFU
     * @var string[]|null
     */
    public ?array $MobilePhone = null;
    public function __construct(SaftParameters $params) {
        
    }
}
?>
