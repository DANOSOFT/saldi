<?php
// 20260909 CDX/LH SST-780: Exercise real pdftk merges and failure preservation.
// 20260910 CDX/LH Cover publication permissions, relative paths and captured failures.
// Run: php tests/integration/mergePrintPdfs.php (requires pdftk, ps2pdf and gs).
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
function checkPrintPdf($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

foreach (array('pdftk', 'ps2pdf', 'gs') as $tool) {
	exec('command -v ' . escapeshellarg($tool), $toolOutput, $toolStatus);
	checkPrintPdf($toolStatus === 0, "Missing required tool: $tool");
}

$originalDirectory = getcwd();
$originalUmask = umask(0022);
$directory = sys_get_temp_dir() . '/saldi print ' . bin2hex(random_bytes(6));
mkdir($directory);
try {
	$pages = array();
	foreach (array('First', 'Second', 'Third') as $index => $label) {
		$source = "$directory/page $index.ps";
		$pages[] = "$directory/page $index.pdf";
		file_put_contents($source, "%!PS\n/Helvetica findfont 20 scalefont setfont\n72 720 moveto ($label) show\nshowpage\n");
		passthru('ps2pdf ' . escapeshellarg($source) . ' ' . escapeshellarg($pages[$index]), $status);
		checkPrintPdf($status === 0, 'Fixture conversion failed.');
	}
	foreach (array('tilbud16288', 'ordrebek16601') as $name) {
		$destination = "$directory/$name.pdf";
		copy($pages[0], $destination);
		mergePrintPdfs(array($destination, $pages[1], $pages[2]), $destination);
		$metadata = shell_exec('pdftk ' . escapeshellarg($destination) . ' dump_data');
		checkPrintPdf(strpos($metadata, 'NumberOfPages: 3') !== false, 'Merged document must retain all three pages.');
		$text = shell_exec('gs -q -dBATCH -dNOPAUSE -sDEVICE=txtwrite -sOutputFile=- ' . escapeshellarg($destination));
		checkPrintPdf(preg_match('/First.*Second.*Third/s', $text) === 1, 'Merged page content/order changed.');
		checkPrintPdf(file_exists($pages[1]) && file_exists($pages[2]), 'Merge must preserve input pages.');
		echo "PASS: $name retains filename and all pages with destination also an input.\n";
	}

	$destination = "$directory/new document.pdf";
	mergePrintPdfs($pages, $destination);
	checkPrintPdf(file_exists($destination), 'New destination was not published.');
	echo "PASS: separate output path and paths containing spaces.\n";

	checkPrintPdf((fileperms($destination) & 0777) === 0644, 'Published PDF must be readable by the web server.');
	umask(0027);
	mergePrintPdfs($pages, $destination);
	clearstatcache(true, $destination);
	checkPrintPdf((fileperms($destination) & 0777) === 0640, 'Published mode must respect a restrictive umask.');
	umask(0022);
	chdir($directory);
	mergePrintPdfs($pages, './relative.pdf');
	chdir($originalDirectory);
	checkPrintPdf(file_exists("$directory/relative.pdf"), 'Relative destination was rejected.');
	echo "PASS: published permissions respect umask and relative directories resolve correctly.\n";

	$failed = false;
	try {
		mergePrintPdfs($pages, "$directory/missing/output.pdf");
	} catch (RuntimeException $error) {
		$failed = true;
	}
	checkPrintPdf($failed, 'Missing destination directory must be rejected.');
	echo "PASS: missing destination directory rejected before temporary-file fallback.\n";

	chmod($directory, 0555);
	clearstatcache();
	try {
		if (is_writable($directory)) {
			echo "SKIP: unwritable-directory check requires a non-root user.\n";
		} else {
			$failed = false;
			try {
				mergePrintPdfs($pages, $destination);
			} catch (RuntimeException $error) {
				$failed = true;
			}
			checkPrintPdf($failed, 'Unwritable destination directory must be rejected.');
			checkPrintPdf(glob("$directory/saldi-pdf-*") === array(), 'Rejected directory leaked a temporary PDF.');
			echo "PASS: unwritable destination directory rejected without falling back.\n";
		}
	} finally {
		chmod($directory, 0755);
	}

	$before = hash_file('sha256', $destination);
	file_put_contents("$directory/invalid.pdf", 'Invalid PDF fixture');
	foreach (array("$directory/missing.pdf", "$directory/invalid.pdf") as $badInput) {
		$failed = false;
		ob_start();
		try {
			mergePrintPdfs(array($destination, $badInput), $destination);
		} catch (RuntimeException $error) {
			$failed = true;
			checkPrintPdf(strpos($error->getMessage(), basename($badInput)) !== false, 'Merge diagnostics must identify the bad input.');
		} finally {
			$response = ob_get_clean();
		}
		checkPrintPdf($response === '', 'Merge diagnostics leaked into the HTTP response.');
		checkPrintPdf($failed, 'Invalid input must fail the merge.');
		checkPrintPdf(hash_file('sha256', $destination) === $before, 'Failed merge changed the existing document.');
		checkPrintPdf(glob("$directory/saldi-pdf-*") === array(), 'Temporary merge file was leaked.');
	}
	echo "PASS: missing/corrupt inputs preserve the document and clean temporary output.\n";
} finally {
	chdir($originalDirectory);
	umask($originalUmask);
	foreach (glob("$directory/*") as $path) {
		unlink($path);
	}
	rmdir($directory);
}
