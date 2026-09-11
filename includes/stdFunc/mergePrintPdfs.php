<?php
// 20260909 CDX/LH SST-780: Merge print pages without overwriting an input PDF.
// 20260910 CDX/LH Validate atomic publication, restore permissions and capture merge errors.

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
	$directory = realpath(dirname($destination));
	if ($directory === false || !is_dir($directory) || !is_writable($directory)) {
		throw new RuntimeException('Cannot create temporary print PDF.');
	}
	$temporary = tempnam($directory, 'saldi-pdf-');
	if ($temporary === false) {
		throw new RuntimeException('Cannot create temporary print PDF.');
	}
	try {
		if (realpath(dirname($temporary)) !== $directory) {
			throw new RuntimeException('Cannot create temporary print PDF.');
		}
		$command = 'pdftk ' . implode(' ', array_map('escapeshellarg', $inputs));
		$command .= ' cat output ' . escapeshellarg($temporary) . ' dont_ask';
		exec($command . ' 2>&1', $output, $status);
		clearstatcache(true, $temporary);
		if ($status !== 0 || !is_file($temporary) || filesize($temporary) === 0) {
			throw new RuntimeException('Cannot merge print PDF pages: ' . implode(' | ', $output));
		}
		// Set the published mode before rename so permission failures preserve the destination.
		if (!chmod($temporary, 0666 & ~umask())) {
			throw new RuntimeException('Cannot set merged print PDF permissions.');
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
