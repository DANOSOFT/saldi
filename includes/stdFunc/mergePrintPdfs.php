<?php
// 20260909 CDX/LH SST-780: Merge print pages without overwriting an input PDF.

/**
 * Publish a merged PDF under the document's original filename.
 * Inputs are preserved on failure; the destination may also be an input.
 *
 * @param string[] $inputs Ordered PDF paths.
 * @param string $destination Final document path.
 * @return void
 * @throws RuntimeException When merging or publishing fails.
 */
function mergePrintPdfs(array $inputs, $destination) {
	$temporary = tempnam(dirname($destination), 'saldi-pdf-');
	if ($temporary === false) {
		throw new RuntimeException('Cannot create temporary print PDF.');
	}
	try {
		$command = 'pdftk ' . implode(' ', array_map('escapeshellarg', $inputs));
		$command .= ' cat output ' . escapeshellarg($temporary) . ' dont_ask';
		system($command, $status);
		clearstatcache(true, $temporary);
		if ($status !== 0 || filesize($temporary) === 0) {
			throw new RuntimeException('Cannot merge print PDF pages.');
		}
		if (!rename($temporary, $destination)) {
			throw new RuntimeException('Cannot publish merged print PDF.');
		}
	} finally {
		if (file_exists($temporary)) {
			unlink($temporary);
		}
	}
}
