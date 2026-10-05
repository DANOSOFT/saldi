<?php
include_once __DIR__ . '/SaftParameters.php';
include_once __DIR__ . '/CompanyHeaderStructure.php';
include_once __DIR__ . '/SelectionCriteriaStructure.php';
class Header {
    /** M */
    public string $AuditFileVersion = '2.1';
    /** M */
    public string $AuditFileCountry = 'DK';
    /** O */
    public string $AuditFileRegion = 'DK-85';
    /** M */
    public string $AuditFileDateCreated;
    /** M */
    public string $SoftwareCompanyName = 'Saldi.DK ApS';
    /** M */
    public string $SoftwareID = 'Saldi.DK';
    /** M */
    public string $SoftwareVersion;
    /** M */
    public CompanyHeaderStructure $Company;
    /** M */
    public string $DefaultCurrencyCode;
    /** M */
    public SelectionCriteriaStructure $SelectionCriteria;
    /** O */
    public ?string $HeaderComment = null;
    /** O */
    public ?string $TaxAccountingBasis = null;
    /** M */
    public string $TaxEntity;
    /** MIFU */
    public ?string $UserID = null;
    public function __construct(SaftParameters $params) {
        $this->AuditFileDateCreated = date('Y-m-d');
        include __DIR__ . '/../../includes/version.php';
        $this->SoftwareVersion = $version;
        $this->Company = new CompanyHeaderStructure($params);
        $this->SelectionCriteria = new SelectionCriteriaStructure($params);
    }
}
?>
