<?php
// 20260909 CDX/LH SST-780: Exercise real pdftk merges and failure preservation.
// Run: php tests/integration/mergePrintPdfs.php (requires pdftk and ps2pdf).
require_once __DIR__ . '/../../includes/stdFunc/mergePrintPdfs.php';

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

function checkPrintPdf($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
}

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

	$before = hash_file('sha256', $destination);
	file_put_contents("$directory/invalid.pdf", 'Invalid PDF fixture');
	foreach (array("$directory/missing.pdf", "$directory/invalid.pdf") as $badInput) {
		$failed = false;
		try {
			mergePrintPdfs(array($destination, $badInput), $destination);
		} catch (RuntimeException $error) {
			$failed = true;
		}
		checkPrintPdf($failed, 'Invalid input must fail the merge.');
		checkPrintPdf(hash_file('sha256', $destination) === $before, 'Failed merge changed the existing document.');
		checkPrintPdf(glob("$directory/saldi-pdf-*") === array(), 'Temporary merge file was leaked.');
	}
	echo "PASS: missing/corrupt inputs preserve the document and clean temporary output.\n";
} finally {
	foreach (glob("$directory/*") as $path) {
		unlink($path);
	}
	rmdir($directory);
}
