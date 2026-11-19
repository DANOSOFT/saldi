<?php
// 20261113 CDX/NTR Preserve the legacy posting-time report-number schema check when extracting invoice posting (SD-599).
// Existing compatibility behavior; schema changes continue to belong to the update chain.
// 20261113 CDX/NTR Scope the existing column lookup to the connected tenant schema.

$reportNumberSchema = in_array($GLOBALS['db_type'] ?? '', ['mysql', 'mysqli'], true) ? 'DATABASE()' : 'current_schema()';
$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_schema=$reportNumberSchema and table_name='transaktioner' and column_name='report_number'";
if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
	$qtxt = "ALTER TABLE transaktioner ADD COLUMN report_number int default 0";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
