<?php
class SelectionCriteriaStructure {
    /** O */
    public ?string $TaxReportingJurisdiction = null;
    /** MIFU */
    public ?string $CompanyEntity = null;
    /** M */
    public string $SelectionStartDate;
    /** M */
    public string $SelectionEndDate;
    /** O */
    public ?int $PeriodStart = null;
    /** O */
    public ?int $PeriodStartYear = null;
    /** O */
    public ?int $PeriodEnd = null;
    /** O */
    public ?int $PeriodEndYear = null;
    /** O */
    public ?string $DocumentType = null;
    /**
     * O
     * @var string[]|null
     */
    public ?array $OtherCriteria = null;
    
    public function __construct(SaftParameters $params) {
        $this->SelectionStartDate = $params->periodStart->format('Y-m-d');
        $this->SelectionEndDate = $params->periodEnd->format('Y-m-d');
    }
}
?>
