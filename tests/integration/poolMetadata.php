<?php
// 20260914 CDX/LH Exercise document corrections against PostgreSQL temporary tables.
// Run with SALDI_TEST_PG_DSN (or libpq PG* environment variables plus SALDI_TEST_PG=1).
if (!getenv('SALDI_TEST_PG_DSN') && !getenv('SALDI_TEST_PG')) {
	fwrite(STDOUT, "SKIP: configure a PostgreSQL test connection\n");
	exit(0);
}
set_error_handler(static function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
$connection = pg_connect(getenv('SALDI_TEST_PG_DSN') ?: '');
function db_select($sql, $context = '') { return pg_query($GLOBALS['connection'], $sql); }
function db_modify($sql, $context = '') { return db_select($sql, $context); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['connection'], $value); }
function transaktion($action) { db_modify($action); }
require_once __DIR__ . '/../../includes/docsIncludes/poolMetadata.php';
db_modify("CREATE TEMP TABLE pool_files (id serial PRIMARY KEY, filename text UNIQUE, subject text, account text, amount text, norm_amount numeric, file_date text, invoice_number text, description text, currency text, updated timestamp DEFAULT CURRENT_TIMESTAMP, manually_edited boolean NOT NULL DEFAULT false)");
db_modify("CREATE TEMP TABLE kontoplan (kontonr integer, regnskabsaar integer, kontotype text, lukket text)");
db_modify("CREATE TEMP TABLE kassekladde (id integer, kladde_id integer, debet integer)");
db_modify("INSERT INTO kontoplan VALUES (1000,9,'D',NULL),(2000,9,'S',''),(3000,8,'D',''),(4000,9,'D','on'),(5000,9,'H','')");
db_modify("INSERT INTO kassekladde VALUES (1,10,0),(2,10,2000)");
db_modify("INSERT INTO pool_files (filename,subject,account,amount,file_date,invoice_number,description,currency) VALUES ('invoice.pdf','Original','1000','123,45','2026-09-14','INV-1','Original description','DKK')");
$checks = 0;
function check($condition, $label) {
	if (!$condition) { throw new RuntimeException($label); }
	$GLOBALS['checks']++;
}
function metadataRow() { return db_fetch_array(db_select("SELECT * FROM pool_files WHERE filename='invoice.pdf'")); }
function rejected($call, $code, $label) {
	$before = metadataRow();
	try {
		$call();
	} catch (InvalidArgumentException | RuntimeException $error) {
		check($error->getCode() === $code, "$label status");
		check(metadataRow() === $before, "$label rollback");
		return;
	}
	throw new RuntimeException("$label was accepted");
}
check(!poolMetadataIsManual(metadataRow()), "PostgreSQL false is not manually edited");
$oldVersion = poolMetadataVersion(metadataRow());
$result = poolMetadataSave('invoice.pdf', ['amount'=>'200.50'], false, null, 9);
check(!$result['skipped'] && metadataRow()['norm_amount'] === '200.5', 'Automatic extraction saved');
rejected(fn()=>poolMetadataSave('invoice.pdf', ['subject'=>'Stale'], true, $oldVersion, 9), 409, 'Stale editor');
$version = poolMetadataVersion(metadataRow());
$result = poolMetadataSave('invoice.pdf', ['account'=>'2000','amount'=>'0','file_date'=>'15.09.2026','invoice_number'=>'','description'=>"Supplier's correction"], true, $version, 9);
$row = metadataRow();
check(poolMetadataIsManual($row), 'Accepted state persisted');
check($row['account']==='2000' && $row['amount']==='0' && $row['norm_amount']==='0', 'Account and zero amount persisted');
check($row['file_date']==='2026-09-15' && $row['invoice_number']==='' && $row['description']==="Supplier's correction", 'Date, cleared reference, and escaped description persisted');
check($row['subject']==='Original' && $row['currency']==='DKK', 'Omitted fields preserved');
$before = $row;
$result = poolMetadataSave('invoice.pdf', ['subject'=>'Late AI','account'=>'1000','amount'=>'999'], false, null, 9);
check($result['skipped'] && metadataRow()===$before, 'Late automatic result cannot overwrite accepted data');
rejected(fn()=>poolMetadataSave('invoice.pdf', ['subject'=>'Other tab'], true, $version, 9), 409, 'Second stale manual editor');
rejected(fn()=>poolMetadataSave('invoice.pdf', [], true, null, 9), 409, 'Missing displayed version');
foreach (['unknown','3000','4000','5000','9999'] as $account) {
	rejected(fn()=>poolMetadataSave('invoice.pdf', ['account'=>$account], true, poolMetadataVersion(metadataRow()), 9), 422, "Invalid account $account");
}
foreach ([['amount'=>'invalid'],['file_date'=>'31-02-2026'],['description'=>['invalid']]] as $input) {
	rejected(fn()=>poolMetadataSave('invoice.pdf', $input, true, poolMetadataVersion(metadataRow()), 9), 422, 'Invalid metadata');
}
rejected(fn()=>poolMetadataSave('missing.pdf', [], false, null, 9), 409, 'Deleted document');
poolMetadataSave('invoice.pdf', ['amount'=>'','file_date'=>'','account'=>''], true, poolMetadataVersion(metadataRow()), 9);
$row = metadataRow();
check($row['amount']==='' && $row['norm_amount']===null && $row['file_date']==='' && $row['account']==='', 'Explicit blanks clear metadata');
check(poolJournalSuggestedAccount('1000','kassekladde',0,10,'',9)==='1000', 'New journal receives account');
check(poolJournalSuggestedAccount('1000','kassekladde',1,10,'',9)==='1000', 'Unclassified existing line receives account');
check(poolJournalSuggestedAccount('1000','kassekladde',2,10,'',9)==='', 'Existing classification preserved');
check(poolJournalSuggestedAccount('1000','kassekladde',0,10,'2000',9)==='', 'Explicit journal account preserved');
check(poolJournalSuggestedAccount('1000','ordre',1,10,'',9)==='', 'Other document sources unaffected');
rejected(fn()=>poolJournalSuggestedAccount('1000','kassekladde',1,11,'',9), 422, 'Wrong journal target');
printf("PASS: %d PostgreSQL metadata and account checks\n", $checks);
