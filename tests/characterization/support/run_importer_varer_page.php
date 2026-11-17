<?php
// tests/characterization/support/run_importer_varer_page.php
//
// Child-process runner for the item import (systemdata/importer_varer.php): it posts the form the preview's
// Importer button sends, so the page reads the file and writes varer the way it does for a user.
//
// Like the other legacy pages, importer_varer.php starts a session and includes connect/online/std_func at top
// level, and the import ends in exit, so it runs as a child process with the session CharacterizationEnv seeded.
//
// argv: 1 = CSV file path
//       2 = session id (must match the seeded online row)
//       3 = column mapping, comma-separated field names ('-' for an unused column)
//       4 = filterOption (all_products, only_existing_product, only_unknown_products)
//       5 = tegnset (UTF-8 or ISO-8859-1)
//
// Prints the page's HTML, then CHARTEST_PAGE_DONE; PHP warnings go to stderr. The test asserts on the database and on the page's text.
//
// History:
// 20261117 CL/LH Created for the campaign price import (Havemøbelland) and the Codex review of it.
if ($argc < 6) {
	fwrite(STDERR, "usage: php run_importer_varer_page.php <csv> <session_id> <mapping> <filterOption> <tegnset>\n");
	exit(2);
}
$mapping = explode(',', $argv[3]);
error_reporting(E_ALL); // to stderr, where the test checks that none comes from includes/itemImport.php
ini_set('display_errors', 'stderr');
ini_set('log_errors', '0');
$_SERVER['REQUEST_URI'] = '/saldi/systemdata/importer_varer.php';
$_SERVER['PHP_SELF'] = '/saldi/systemdata/importer_varer.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'chartest';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'import' => 'Importer',
	'filnavn' => $argv[1],
	'splitter' => 'Semikolon',
	'feltnavn' => $mapping,
	'feltantal' => (string)(count($mapping) - 1),
	'tegnset' => $argv[5],
	'itemGroup' => '',
	'filterOption' => $argv[4],
	'csvFile' => '',
);
ini_set('session.save_path', sys_get_temp_dir());
session_id($argv[2]);
register_shutdown_function(function () {
	while (ob_get_level()) ob_end_flush();
	fwrite(STDOUT, "\nCHARTEST_PAGE_DONE\n");
});
chdir(dirname(__DIR__, 3) . '/systemdata'); // the page uses ../includes/... relative includes
include 'importer_varer.php';
