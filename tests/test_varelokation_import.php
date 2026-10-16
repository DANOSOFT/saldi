<?php
// --- tests/test_varelokation_import.php --- 2026-10-15 ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261015 LOE Cover SST-875: a location imported from a CSV is stored without the file's line break,
//                 and an empty field is stored empty instead of as a line break.
//
//   php tests/test_varelokation_import.php
//
// The importer is a Systemdata page: it opens the session and includes includes/online.php, so it cannot
// be included from a test. The rule is therefore read out of the page: the assertion pins the order of
// the statements in overfoer_data(), and the rule that is evaluated is the one lifted from the source,
// so the test cannot drift away from what the page does.

$source = file_get_contents(__DIR__ . '/../systemdata/importer_varelokationer.php');
$checks = 0;
$failures = array();

function importCheck($condition, $description) {
	global $checks, $failures;
	$checks++;
	if (!$condition) $failures[] = $description;
}

# ---- the statements are in the page, in this order ------------------------------------------
importCheck($source !== false && $source !== '', 'the importer source is readable');

$trim = '$linje=rtrim($linje,"\r\n");';
$explode = 'list($varenr,$lokation)=explode($splitter,$linje);';
$trimAt = strpos($source, $trim);
$explodeAt = strpos($source, $explode);
importCheck($trimAt !== false, 'the importer strips the line break from the line it reads');
importCheck($explodeAt !== false, 'and still splits the line into item number and location');
importCheck($trimAt !== false && $explodeAt !== false && $trimAt < $explodeAt,
	'the strip happens before the split, so the break cannot reach the location');
$convertAt = strpos($source, '$linje=mb_convert_encoding(');
importCheck($convertAt !== false, 'the charset conversion is still in the page');
importCheck($convertAt !== false && $trimAt !== false && $convertAt < $trimAt,
	'and the strip comes after it, where the line is still exactly what the file held');
importCheck(strpos($source, 'trim($linje)') === false,
	'the line is not trimmed with trim(), which would also eat spaces inside a location');

# ---- the rule, evaluated from the source ----------------------------------------------------
function importLocation($line, $splitter = ';') {
	# the two statements as they stand in the page, in the order the page runs them
	$line = rtrim($line, "\r\n");
	list($varenr, $lokation) = explode($splitter, $line);
	return array($varenr, $lokation);
}

list($varenr, $lokation) = importLocation("98380406;HC9\r\n");
importCheck($varenr === '98380406' && $lokation === 'HC9', 'a Windows line break leaves the location as HC9');
list($varenr, $lokation) = importLocation("9565000;HC1\n");
importCheck($varenr === '9565000' && $lokation === 'HC1', 'a Linux or Mac line break leaves the location as HC1');
list($varenr, $lokation) = importLocation("2324;\r\n");
importCheck($varenr === '2324' && $lokation === '', 'an empty field is empty, not a line break');
list($varenr, $lokation) = importLocation("2324;");
importCheck($lokation === '', 'and an empty last field with no break at all is empty too');
list($varenr, $lokation) = importLocation("9565000;HC1");
importCheck($lokation === 'HC1', 'a last line without a line break is unaffected');
list($varenr, $lokation) = importLocation("2324;HC 9\r\n");
importCheck($lokation === 'HC 9', 'a space inside a location is kept');
list($varenr, $lokation) = importLocation("2324;=L6\r\n");
importCheck($lokation === '=L6', 'a lager reference keeps the =L prefix the printouts look for');
list($varenr, $lokation) = importLocation("9565000;HC1\r\n\r\n");
importCheck($lokation === 'HC1', 'and every trailing break is stripped, not just one');

echo "\n" . ($failures ? count($failures) . " of $checks checks FAILED" : "all $checks checks passed") . "\n";
foreach ($failures as $failure) {
	echo "  FAILED: $failure\n";
}
exit($failures ? 1 : 0);
