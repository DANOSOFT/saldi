<?php
// --- tests/test_pool_file_delete.php --- 2026-10-06 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261006 LOE Cover SST-858: a bilag stored with an upper case extension is deleted from the pulje
//                 folder together with its side files, and does not return from the next folder sync.
//
// Runs against the real database layer (db_select()/db_modify() from includes/connect.php), the same
// way the application does - no database abstraction of its own.
//
//   SALDI_TEST_TENANT_DB=<tenant database> php tests/test_pool_file_delete.php
//
// It works in a throwaway pool folder of its own and only ever touches pool_files rows whose file
// name starts with pooltest_. The suite skips itself (exit 0) when the tenant database is not
// reachable, like the other suites here.

chdir(__DIR__);

$_SERVER['REQUEST_URI'] = '/saldi/includes/documents.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

require_once __DIR__ . '/../includes/connect.php';

/** @var string $sqhost set by the gitignored includes/connect.php */
/** @var string $squser set by the gitignored includes/connect.php */
/** @var string $sqpass set by the gitignored includes/connect.php */
$tenantDb = getenv('SALDI_TEST_TENANT_DB') ?: 'tenant_db';

# The folder the pool code works in is "<docFolder>/<db>/pulje", so a throwaway root is all it takes
# to keep the suite out of the tenant's own pulje folder. Only the helper folder exists at this point:
# docPool.php runs its missing-row check when it is included, and that check returns immediately when
# the pulje folder is not there, which keeps the include out of the database.
$root = sys_get_temp_dir() . '/saldi_pool_delete_' . getmypid();
$docFolder = $root;
$db = $tenantDb;
$sprog_id = 1;
$puljePath = "$root/$tenantDb/pulje";
$helperPath = "$root/helpers";
@mkdir($helperPath, 0777, true);

require_once __DIR__ . '/../includes/docsIncludes/docPool.php';

$checks = 0;
$failures = array();
$skips = 0;

/** @return void Record one check. */
function poolTestCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) {
		$failures[] = $description;
	}
}

/** @return void Write a file into a folder. */
function poolTestWrite($folder, $name, $content = 'x') {
	file_put_contents("$folder/$name", $content);
}

/** @return array The file names in a folder, sorted, without the lock and without side files of none. */
function poolTestNames($folder) {
	$names = array();
	foreach ((array)scandir($folder) as $entry) {
		if ($entry === '.' || $entry === '..') continue;
		if (strpos($entry, '.lock') !== false) continue;
		if (is_file("$folder/$entry")) $names[] = $entry;
	}
	sort($names);
	return $names;
}

/** @return array The pool_files row for a file name, or an empty array. */
function poolTestRow($name) {
	$row = db_fetch_array(db_select("SELECT * FROM pool_files WHERE filename = '" . db_escape_string($name) . "'", __FILE__ . ' line ' . __LINE__));
	return $row ? $row : array();
}

/** @return void Remove a folder and everything in it. */
function poolTestRemoveTree($folder) {
	if (!is_dir($folder)) return;
	foreach ((array)scandir($folder) as $entry) {
		if ($entry === '.' || $entry === '..') continue;
		$path = "$folder/$entry";
		if (is_dir($path)) poolTestRemoveTree($path);
		else unlink($path);
	}
	rmdir($folder);
}

# ---- the name rule, no database needed ---------------------------------------------------
# The functions this change adds must not raise a PHP warning on any of the inputs below, including
# the refused ones - collect them and check at the end of the block.
$warnings = array();
set_error_handler(function ($severity, $message) use (&$warnings) {
	$warnings[] = $message;
	return true;
});

poolTestCheck(poolFileName('AArsoversigt.PDF') === 'AArsoversigt.PDF', 'a plain file name is kept as it is');
poolTestCheck(poolFileName('  faktura_7120.pdf ') === 'faktura_7120.pdf', 'surrounding space is trimmed');
poolTestCheck(poolFileName('../AArsoversigt.PDF') === '', 'a name with a directory part is refused');
poolTestCheck(poolFileName('pulje/x.pdf') === '', 'a name with a slash is refused');
poolTestCheck(poolFileName('..\\x.pdf') === '', 'a name with a backslash is refused');
poolTestCheck(poolFileName('..') === '' && poolFileName('.') === '' && poolFileName('') === '', 'empty and dot names are refused');

# ---- the helpers, on a folder of their own ----------------------------------------------
poolTestWrite($helperPath, 'AArsoversigt.PDF');
poolTestWrite($helperPath, 'AArsoversigt.info', "Emne\n");
poolTestWrite($helperPath, 'AArsoversigt.XML');
poolTestWrite($helperPath, 'Aarsoversigt.pdf');
poolTestWrite($helperPath, 'faktura_7120.pdf');
poolTestWrite($helperPath, 'X.PDF');
poolTestWrite($helperPath, 'X.info', "X\n");
poolTestWrite($helperPath, 'x.pdf');
poolTestWrite($helperPath, 'x.info', "x\n");
poolTestWrite($helperPath, 'notat.txt');
poolTestWrite($helperPath, 'Only_info.info');

$companions = poolFileCompanions($helperPath, 'AArsoversigt.PDF');
sort($companions);
# Aarsoversigt.pdf differs from AArsoversigt.PDF only in the case of one letter, so it is listed here
# as a file that shares the base name - but it is a bilag of its own, and the check below shows that
# deleting AArsoversigt.PDF leaves it alone.
poolTestCheck($companions === array('AArsoversigt.PDF', 'AArsoversigt.XML', 'AArsoversigt.info', 'Aarsoversigt.pdf'),
	'a .PDF bilag is found together with its .info and .XML side files');
poolTestCheck(poolDocumentExists($helperPath, 'AArsoversigt.PDF'), 'a .PDF bilag counts as a document that is there');
poolTestCheck(poolDocumentExists($helperPath, 'AArsoversigt.info'), 'and so does its side file, for the orphan cleanup');
poolTestCheck(!poolDocumentExists($helperPath, 'Only_info.info'), 'an .info file with no document does not');

$removed = poolDeleteFiles($helperPath, 'AArsoversigt.PDF');
poolTestCheck(count($removed['deleted']) === 3, 'deleting the bilag removes the file and both side files');
poolTestCheck($removed['missing'] === array() && $removed['refused'] === '', 'and reports nothing missing or refused');
poolTestCheck($removed['name'] === 'AArsoversigt.PDF', 'and reports the name the document was resolved to');
poolTestCheck(poolTestNames($helperPath) === array('Aarsoversigt.pdf', 'Only_info.info', 'X.PDF', 'X.info', 'faktura_7120.pdf', 'notat.txt', 'x.info', 'x.pdf'),
	'no other file in the folder is touched');

# a side file belongs to the pdf whose base name it spells exactly, so deleting X.PDF must leave the
# .info file of x.pdf - the metadata reader for x.pdf opens exactly x.info
poolTestCheck(poolSideFileOwner(array('X.PDF', 'x.pdf', 'x.info'), 'x.info') === 'x.pdf', 'a side file is owned by the pdf that spells its base name exactly');
poolTestCheck(poolSideFileOwner(array('X.PDF', 'X.info'), 'X.info') === 'X.PDF', 'and by the .PDF document when that is the spelling');
poolTestCheck(poolSideFileOwner(array('X.info'), 'X.info') === '', 'a side file with no pdf of that spelling has no owner');

$removed = poolDeleteFiles($helperPath, 'X.PDF');
poolTestCheck($removed['deleted'] === array('X.PDF', 'X.info'), 'a name that differs only in case from another bilag deletes only its own file and its own side file');
poolTestCheck(in_array('x.pdf', poolTestNames($helperPath), true), 'the other bilag with the same letters stays');
poolTestCheck(in_array('x.info', poolTestNames($helperPath), true), 'and so does the side file that belongs to it');
poolTestCheck(in_array('X.info', poolTestNames($helperPath), true) === false, 'while the side file of the deleted document has gone');

poolTestWrite($helperPath, 'Ghost.PDF');
$removed = poolDeleteFiles($helperPath, 'Ghost.pdf');
poolTestCheck($removed['deleted'] === array('Ghost.PDF'), 'a row left in lower case by the old rename still deletes its file');

$removed = poolDeleteFiles($helperPath, '../faktura_7120.pdf');
poolTestCheck($removed['refused'] !== '' && $removed['deleted'] === array(), 'a name carrying a path is refused and removes nothing');
poolTestCheck(in_array('faktura_7120.pdf', poolTestNames($helperPath), true), 'and the file it pointed at is still there');

$removed = poolDeleteFiles($helperPath, 'nothere.pdf');
poolTestCheck($removed['missing'] === array('nothere.pdf'), 'a name with no file is reported as missing');

poolTestCheck(poolFileName(NULL) === '' && poolFileName(array()) === '', 'a missing or odd value is refused without a warning');
restore_error_handler();
poolTestCheck($warnings === array(), 'the helpers raise no PHP warning (' . implode('; ', $warnings) . ')');

# ---- the folder sync and the database ---------------------------------------------------
$connection = @db_connect($sqhost, $squser, $sqpass, $db);
if (!$connection) {
	poolTestRemoveTree($root);
	fwrite(STDERR, "SKIP: tenant database '$tenantDb' is not reachable on this machine.\n");
	exit(0);
}
if (!db_fetch_array(db_select("SELECT table_name FROM information_schema.tables WHERE table_name = 'pool_files'", __FILE__ . ' line ' . __LINE__))) {
	poolTestRemoveTree($root);
	fwrite(STDERR, "SKIP: tenant database '$tenantDb' has no pool_files table.\n");
	exit(0);
}

# the pulje folder is created now, after the include, so the missing-row check it runs on inclusion
# had nothing to look at
@mkdir($puljePath, 0777, true);

$upperName = 'pooltest_AArsoversigt.PDF';
$upperInfo = 'pooltest_AArsoversigt.info';
$lowerName = 'pooltest_faktura_7120.pdf';
$ghostFile = 'pooltest_Ghost.PDF';
$ghostRowUpper = 'pooltest_Ghost.PDF';
$ghostRowLower = 'pooltest_Ghost.pdf';
$created = array($upperName, $upperInfo, $lowerName);
# rows this suite writes directly rather than through the folder sync: they are deleted at the end even
# when a check fails in between, so a broken run cannot leave them behind in the tenant
$createdRows = array($ghostRowUpper, $ghostRowLower);
$savedSkip = get_settings_value('skip_sync', 'docs', 0);

foreach ($created as $name) {
	db_modify("DELETE FROM pool_files WHERE filename = '" . db_escape_string($name) . "'", __FILE__ . ' line ' . __LINE__);
	# the two documents must not hold the same bytes: the pool recognises a second copy of a bilag by
	# its content and would drop the .pdf one as a duplicate of the .PDF one
	poolTestWrite($puljePath, $name, "content of $name\n");
}
# the folder sync runs only when its window has expired
update_settings_value('skip_sync', 'docs', 0, 'pooltest');
syncPuljeFilesToDatabase($docFolder, $db);

poolTestCheck(poolTestRow($upperName) !== array(), 'the folder sync registers a bilag stored as .PDF');
poolTestCheck(isset(poolTestRow($upperName)['filename']) && poolTestRow($upperName)['filename'] === $upperName,
	'and keeps its name, which is why a leftover file used to come back');
poolTestCheck(poolTestRow($lowerName) !== array(), 'and it registers a .pdf bilag');

# ---- the delete, as the pool page does it -----------------------------------------------
# The folder sync removes every .info file in the pool (its metadata lives in the database now), so
# the side file is put back after the sync to show the delete takes it along with the document.
poolTestWrite($puljePath, $upperInfo, "Emne\n");
poolTestCheck(is_file("$puljePath/$upperInfo"), 'the side file is in place before the delete');

$removed = poolDeleteDocument($puljePath, $upperName);
poolTestCheck(poolTestRow($upperName) === array(), 'deleting the .PDF bilag removes its row');
poolTestCheck(!is_file("$puljePath/$upperName"), 'and its file');
poolTestCheck(!is_file("$puljePath/$upperInfo"), 'and its side file, whose name differs from the document only in the extension');
poolTestCheck(count($removed['deleted']) === 2, 'both files are reported as deleted (got: ' . implode(', ', $removed['deleted']) . ')');
poolTestCheck(poolTestRow($lowerName) !== array(), 'the .pdf bilag next to it keeps its row');

# ---- and it does not come back ----------------------------------------------------------
update_settings_value('skip_sync', 'docs', 0, 'pooltest');
syncPuljeFilesToDatabase($docFolder, $db);
poolTestCheck(poolTestRow($upperName) === array(), 'the bilag does not return after the next folder sync');
poolTestCheck(poolTestNames($puljePath) === array($lowerName), 'nothing is left in the folder but the .pdf bilag');

# ---- the .pdf behaviour is unchanged ----------------------------------------------------
$removed = poolDeleteDocument($puljePath, $lowerName);
poolTestCheck(poolTestRow($lowerName) === array() && !is_file("$puljePath/$lowerName"), 'a .pdf bilag is deleted the same way');

# ---- a row whose spelling differs from the file ----------------------------------------
# The old rename could leave a row under a lower case name while the file kept its own spelling (and
# the table's unique key is case sensitive, so both spellings can sit in it). Deleting from either
# spelling has to take both rows: only one of them can be the document, and neither may be left
# pointing at a file that is gone.
db_modify("DELETE FROM pool_files WHERE filename IN ('" . db_escape_string($ghostRowUpper) . "', '" . db_escape_string($ghostRowLower) . "')", __FILE__ . ' line ' . __LINE__);
poolTestWrite($puljePath, $ghostFile, "ghost content\n");
db_modify("INSERT INTO pool_files (filename) VALUES ('" . db_escape_string($ghostRowUpper) . "')", __FILE__ . ' line ' . __LINE__);
db_modify("INSERT INTO pool_files (filename) VALUES ('" . db_escape_string($ghostRowLower) . "')", __FILE__ . ' line ' . __LINE__);
poolTestCheck(poolTestRow($ghostRowUpper) !== array() && poolTestRow($ghostRowLower) !== array(), 'both spellings of the row are in place');

$removed = poolDeleteDocument($puljePath, $ghostRowLower);
poolTestCheck(!is_file("$puljePath/$ghostFile"), 'deleting by the lower case name removes the file that carries the other spelling');
poolTestCheck($removed['name'] === $ghostFile, 'and reports the name it was resolved to');
poolTestCheck(poolTestRow($ghostRowUpper) === array(), 'the row with the file spelling is deleted');
poolTestCheck(poolTestRow($ghostRowLower) === array(), 'and so is the row that was asked for');

# ---- the rename's two rules, exercised rather than only read ----------------------------
# Two documents can share a base name in different cases. The rename acts on the selected pdf, or on
# the file whose base name the row spells exactly (what the old rename left behind), and moves that
# document's side file with it while leaving the other document alone.
$renameFiles = array('.', '..', 'X.PDF', 'X.info', 'x.pdf', 'x.info', 'notat.txt');
poolTestCheck(poolRenameTarget($renameFiles, 'X.PDF', 'X') === 'X.PDF', 'the rename acts on the selected pdf');
poolTestCheck(poolRenameTarget($renameFiles, 'X.pdf', 'X') === 'X.PDF', 'and on the file the row spells, when the row was left in the other case');
poolTestCheck(poolRenameTarget($renameFiles, 'x.pdf', 'x') === 'x.pdf', 'and on the lower case pdf when that is the selected one');
poolTestCheck(poolRenameTarget(array('x.pdf'), 'X.PDF', 'X') === '', 'a pdf whose base name differs in case is not picked up');
poolTestCheck(poolRenameTarget(array('X.info', 'notat.txt'), 'X.PDF', 'X') === '', 'a folder with no pdf of that base name has no target');

poolTestCheck(poolRenameMoves($renameFiles, 'X.PDF', 'X.PDF'), 'the selected pdf moves');
poolTestCheck(poolRenameMoves($renameFiles, 'X.PDF', 'X.info'), 'and its own side file moves with it');
poolTestCheck(!poolRenameMoves($renameFiles, 'X.PDF', 'x.pdf'), 'the pdf whose base name differs in case stays');
poolTestCheck(!poolRenameMoves($renameFiles, 'X.PDF', 'x.info'), 'and so does the side file that belongs to it');
poolTestCheck(!poolRenameMoves($renameFiles, '', 'X.PDF'), 'nothing moves when the rename has no target');
poolTestCheck(!poolRenameMoves($renameFiles, 'X.PDF', ''), 'and an empty name is never moved');

# ---- the sources that used to guess the extension ---------------------------------------
$source = file_get_contents(__DIR__ . '/../includes/docsIncludes/docPool.php');
poolTestCheck(strpos($source, "\$fileToDelete = \"\$puljePath/\$origBase.\$ext\";") === false,
	'the delete no longer builds the file name from the base plus an extension');
poolTestCheck(strpos($source, "\$oldFilename = \$origBase . '.pdf';") === false,
	'the rename no longer looks up a lower case .pdf row');
poolTestCheck(strpos($source, 'glob("$puljePath/*.pdf")') === false,
	'the missing-row check no longer uses a case sensitive glob');
poolTestCheck(strpos($source, '$pdfFile = "$puljePath/$baseName.pdf";') === false,
	'the orphan cleanup no longer tests for a lower case .pdf');
poolTestCheck(strpos($source, 'poolDeleteDocument($puljePath, $unlinkFile)') !== false,
	'the pool page deletes through the helper');
poolTestCheck(strpos($source, 'if (!poolRenameMoves($allFiles, $renameTarget, $file)) continue;') !== false,
	'the rename loop asks the rule, rather than repeating the pdf and side-file checks inline');
poolTestCheck(strpos($source, '$renameTarget = poolRenameTarget($allFiles, $poolFile, $origBase);') !== false,
	'and the pdf it acts on comes from the tested target rule');
poolTestCheck(strpos($source, '!hash_equals($rowHash, $targetHash)') !== false,
	'a target accepted on the base name alone has to match the content stored for the row');

# ---- cleanup ----------------------------------------------------------------------------
foreach (array_merge($created, $createdRows) as $name) {
	db_modify("DELETE FROM pool_files WHERE filename = '" . db_escape_string($name) . "'", __FILE__ . ' line ' . __LINE__);
}
update_settings_value('skip_sync', 'docs', $savedSkip, 'pooltest');
poolTestRemoveTree($root);

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . " ($tenantDb)\n";
foreach ($failures as $failure) {
	echo "  FAILED: $failure\n";
}
exit($failures ? 1 : 0);
