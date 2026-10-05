<?php
include_once __DIR__ . '/SaftParameters.php';
include_once 'Header.php';
include_once 'MasterFiles.php';
include_once 'GeneralLedger.php';
include_once 'SourceDocuments.php';
class AuditFile {
    public Header $Header;
    public MasterFiles $MasterFiles;
    public GeneralLedgerEntries $GeneralLedgerEntries;
    public ?SourceDocuments $SourceDocuments = null;
    public function __construct(SaftParameters $params) {
        $this->Header = new Header($params);
        $this->MasterFiles = new MasterFiles();
        $this->GeneralLedgerEntries = new GeneralLedgerEntries();
        if ($params->includeSourceDocuments) {
            $this->SourceDocuments = new SourceDocuments();
        }
    }
}
?>