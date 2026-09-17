<?php
// 20260910 CDX/LH Characterize print conversion, missing primary pages and failure cleanup.
// Run: php tests/integration/udskrivFailure.php (requires pdftk, ps2pdf and gs).
// Runs the actual conversion/error-handler block without a database or customer data.
// WeasyPrint is replaced by a fixture converter to inject conversion/merge failures.
require_once __DIR__ . '/../../includes/stdFunc/mergePrintPdfs.php';

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Fail the integration check when a print invariant is violated.
 *
 * @param bool $condition Expected invariant.
 * @param string $message Failure description.
 * @return void
 * @throws RuntimeException When the invariant does not hold.
 */
function checkPrintFlow($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

if (($argv[1] ?? '') === '--run') {
	$root = $argv[2];
	$mode = $argv[3];
	chdir("$root/includes");
	putenv("PATH=$root/bin:" . getenv('PATH'));
	$db = 'tenant';
	$ps_fil = 'tenant/job/tilbud123';
	$ps2pdf = 'ps2pdf';
	$r = array('box2' => '', 'box3' => $mode === 'html' ? 'on' : '');
	$udskrift = '';
	$udfil = null;
	$returside = '../debitor/ordre.php?id=123&label="test"';
	$sprog_id = 1;
	$log = fopen("$root/print.log", 'w');
	/**
	 * Return a translation fallback without loading the database-backed translator.
	 *
	 * @param string $key Translation ID and fallback text.
	 * @param int $language Language ID.
	 * @return string
	 */
	function findtekst($key, $language) {
		return explode('|', $key, 2)[1];
	}
	$source = file_get_contents(__DIR__ . '/../../includes/udskriv.php');
	// Match real tabs without coupling the fixture to the rest of the legacy page.
	$start = strpos($source, "\t\t\$printIntermediateFiles = array();");
	checkPrintFlow($start !== false, 'Print-flow block start could not be located.');
	$end = strpos($source, "\n\tif (\$zx)", $start);
	checkPrintFlow($end !== false, 'Print-flow block end could not be located.');
	eval(substr($source, $start, $end - $start));
	fclose($log);
	echo 'PRINT_FLOW_COMPLETE';
	exit;
}

foreach (array('pdftk', 'ps2pdf', 'gs') as $tool) {
	exec('command -v ' . escapeshellarg($tool), $toolOutput, $toolStatus);
	checkPrintFlow($toolStatus === 0, "Missing required tool: $tool");
}

$root = sys_get_temp_dir() . '/saldi-print-flow-' . bin2hex(random_bytes(6));
mkdir("$root/temp/tenant/job", 0777, true);
mkdir("$root/includes");
mkdir("$root/bin");
$job = "$root/temp/tenant/job";
try {
	file_put_contents("$root/fixture.ps", "%!PS\n/Helvetica findfont 20 scalefont setfont\n72 720 moveto (ExtraPage) show\nshowpage\n");
	exec('ps2pdf ' . escapeshellarg("$root/fixture.ps") . ' ' . escapeshellarg("$root/fixture.pdf"), $output, $status);
	checkPrintFlow($status === 0, 'Cannot create fixture PDF.');
	$converter = '#!' . PHP_BINARY . "\n<?php\n" . <<<'CONVERTER'
$input = file_get_contents($argv[3]);
if ($input === 'FAIL' || $input === 'CORRUPT') {
	file_put_contents($argv[4], 'Invalid PDF fixture');
	exit($input === 'FAIL' ? 1 : 0);
}
copy(__DIR__ . '/../fixture.pdf', $argv[4]);
CONVERTER;
	file_put_contents("$root/bin/weasyprint", $converter);
	chmod("$root/bin/weasyprint", 0755);

	$cases = array(
		array('html', 'success'),
		array('ps', 'success'),
		array('ps', 'missing-primary'),
		array('ps', 'empty-primary'),
		array('ps', 'stale-primary'),
		array('html', 'conversion-failure'),
		array('html', 'primary-conversion-failure'),
		array('ps', 'conversion-failure'),
		array('ps', 'ps-conversion-failure'),
		array('html', 'merge-failure'),
		array('ps', 'merge-failure'),
	);
	foreach ($cases as list($mode, $scenario)) {
		foreach (glob("$job/*") as $file) {
			unlink($file);
		}
		file_put_contents("$job/unrelated.pdf", 'Keep unrelated document');
		$source = "$job/tilbud123." . ($mode === 'html' ? 'htm' : 'ps');
		if (!in_array($scenario, array('missing-primary', 'stale-primary'), true)) {
			file_put_contents($source, $scenario === 'empty-primary' ? '' : ($scenario === 'primary-conversion-failure' ? 'FAIL' : ($scenario === 'ps-conversion-failure' ? 'Invalid PostScript fixture' : ($mode === 'html' ? 'OK' : str_replace('ExtraPage', 'PrimaryPage', file_get_contents("$root/fixture.ps"))))));
		}
		if ($scenario === 'stale-primary') {
			copy("$root/fixture.pdf", "$job/tilbud123.pdf");
		}
		if ($mode === 'html' && strpos($scenario, 'failure') !== false) {
			copy("$root/fixture.ps", "$job/tilbud123.ps");
		}
		file_put_contents("$job/tilbud123_2.htm", $scenario === 'conversion-failure' ? 'FAIL' : ($scenario === 'merge-failure' ? 'CORRUPT' : 'OK'));
		file_put_contents("$job/tilbud123_3.htm", 'OK');
		$response = array();
		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --run ' . escapeshellarg($root) . ' ' . escapeshellarg($mode) . ' 2>&1', $response, $status);
		$response = implode("\n", $response);
		checkPrintFlow($status === 0, "$mode/$scenario: $response");
		if (strpos($scenario, 'failure') !== false) {
			checkPrintFlow(strpos($response, 'PDF-udskriften kunne ikke oprettes') !== false, "$mode/$scenario: missing friendly error");
			checkPrintFlow(strpos($response, 'id=123&amp;label=&quot;test&quot;') !== false, "$mode/$scenario: return link is not escaped");
			checkPrintFlow(strpos($response, 'PRINT_FLOW_COMPLETE') === false, "$mode/$scenario: failed request continued");
			checkPrintFlow(strpos($response, 'Error:') === false && strpos($response, 'Uncaught') === false, "$mode/$scenario: uncaught error");
			checkPrintFlow(strpos(file_get_contents("$root/print.log"), 'Print failed: Cannot') !== false, "$mode/$scenario: failure was not logged");
			if (!in_array($scenario, array('ps-conversion-failure', 'primary-conversion-failure'), true)) {
				checkPrintFlow(is_file("$job/tilbud123.pdf"), "$mode/$scenario: existing primary PDF was removed");
			}
		} else {
			checkPrintFlow($response === 'PRINT_FLOW_COMPLETE', "$mode/$scenario: $response");
			$metadata = shell_exec('pdftk ' . escapeshellarg("$job/tilbud123.pdf") . ' dump_data');
			$pageCount = $scenario === 'success' ? 3 : 2;
			checkPrintFlow(strpos($metadata, "NumberOfPages: $pageCount") !== false, "$mode/$scenario: wrong page count");
		}
		checkPrintFlow(glob("$job/*.htm") === array() && glob("$job/*.ps") === array(), "$mode/$scenario: source files leaked");
		checkPrintFlow(glob("$job/*_*.pdf") === array() && glob("$job/saldi-pdf-*") === array(), "$mode/$scenario: intermediate PDFs leaked");
		checkPrintFlow(file_get_contents("$job/unrelated.pdf") === 'Keep unrelated document', "$mode/$scenario: unrelated document changed");
		echo "PASS: $mode/$scenario\n";
	}
} finally {
	$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($files as $file) {
		if ($file->isDir()) {
			rmdir($file->getPathname());
		} else {
			unlink($file->getPathname());
		}
	}
	rmdir($root);
}
