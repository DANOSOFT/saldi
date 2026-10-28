<?php
// tools/ci/check-skips.php - fail the CI job on a skipped test that is not
// on the allowlist below.
//
// Most DB-backed suites skip themselves when their settings are missing, and a
// skipped suite looks exactly like a passing one. Until 2026-10-28 the job ran
// without SALDI_CHAR_PGPASS, so every characterization suite skipped and seven
// of their tests had been failing unseen. This turns that state into a red job.
//
// Usage: php tools/ci/check-skips.php <phpunit --log-junit file>

$allowed = [
	// The container runs as root, and root ignores directory permissions.
	'FileReservationTest::testReturnsNullForUnwritableDirectory',
];

$file = $argv[1] ?? '';
if ($file === '' || !is_readable($file)) {
	fwrite(STDERR, "check-skips: cannot read the JUnit file '$file'\n");
	exit(2);
}
$xml = simplexml_load_file($file);
if ($xml === false) {
	fwrite(STDERR, "check-skips: $file is not valid XML\n");
	exit(2);
}

// A report with no test cases means discovery ran nothing; that is not a pass.
$cases = count($xml->xpath('//testcase'));
if ($cases === 0) {
	fwrite(STDERR, "check-skips: $file has no test cases\n");
	exit(1);
}

$skipped = 0;
$unexpected = [];
foreach ($xml->xpath('//testcase[skipped]') as $case) {
	$skipped++;
	$name = (string) $case['class'] . '::' . (string) $case['name'];
	// A data-provider case is named "<method> with data set <name>".
	$method = preg_replace('/ with data set .*$/', '', $name);
	if (!in_array($method, $allowed, true)) {
		$unexpected[] = "$name ({$case['file']})";
	}
}

if ($unexpected) {
	fwrite(STDERR, count($unexpected) . " test(s) skipped that are expected to run:\n");
	foreach ($unexpected as $line) {
		fwrite(STDERR, "  $line\n");
	}
	fwrite(STDERR, "Pass the settings they need in .github/workflows/tests.yml, or add them to the allowlist in tools/ci/check-skips.php with the reason.\n");
	exit(1);
}
echo "check-skips: $cases tests, $skipped skipped, all on the allowlist\n";
