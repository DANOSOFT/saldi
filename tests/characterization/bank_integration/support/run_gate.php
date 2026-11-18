<?php
// 20261118 CL/LH bankIntegrationAvailable() per company in its own PHP process: enabled.php pulls in crypt.php, which EnableBankingBackendTest loads from its own key tree.
// Usage: php run_gate.php <allowlist> <company> [<company> ...]; the SALDI_ENABLEBANKING_* variables come from the parent. Prints one "company=0|1" line per company.
require_once __DIR__ . '/../../../../bank_integration/includes/enabled.php';
$GLOBALS['enableBankingCompanies'] = (string)($argv[1] ?? '');
foreach (array_slice($argv, 2) as $company) {
    $GLOBALS['db'] = $company;
    echo $company . '=' . (bankIntegrationAvailable() ? '1' : '0') . "\n";
}
