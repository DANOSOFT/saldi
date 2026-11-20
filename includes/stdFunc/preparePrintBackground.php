<?php
// 20261120 CDX/NTR Isolate A4 background preparation from the print page for direct integration tests.

/**
 * Fit the first non-A4 background page to an A4 PDF and reuse valid current caches.
 * Conversion failure preserves both the upload and any existing cache.
 *
 * @param string $background Uploaded PDF path.
 * @return string Prepared cache path, or the original background when preparation is unnecessary or fails.
 */
function preparePrintBackground(string $background): string
{
	if (function_exists('shell_exec')) {
		$pinf = shell_exec("pdfinfo " . escapeshellarg($background) . " 2>/dev/null");
		if ($pinf && strpos($pinf, '(A4)') === false) {
			$a4c = preg_replace('/\.pdf$/i', '', $background) . '.a4.pdf';
			$cachedInfo = is_file($a4c) ? shell_exec('pdfinfo ' . escapeshellarg($a4c) . ' 2>/dev/null') : '';
			$cacheReady = $cachedInfo && strpos($cachedInfo, '(A4)') !== false && filemtime($a4c) >= filemtime($background);
			if (!$cacheReady) {
				$a4c_tmp = $a4c . '.' . getmypid() . '.new';
				$normalizationOutput = array();
				exec('gs -q -dSAFER -dBATCH -dNOPAUSE -sDEVICE=pdfwrite -sPAPERSIZE=a4 -dFIXEDMEDIA -dPDFFitPage -dFirstPage=1 -dLastPage=1 -sOutputFile=' . escapeshellarg($a4c_tmp) . ' ' . escapeshellarg($background) . ' 2>&1', $normalizationOutput, $normalizationStatus);
				if ($normalizationStatus === 0 && is_file($a4c_tmp) && file_get_contents($a4c_tmp, false, null, 0, 5) === '%PDF-') {
					$cacheReady = rename($a4c_tmp, $a4c);
				} else {
					error_log('A4 background conversion failed: ' . implode(' | ', $normalizationOutput));
				}
				if (is_file($a4c_tmp)) {
					unlink($a4c_tmp);
				}
			}
			if ($cacheReady) {
				$background = $a4c;
			}
		}
	}
	return $background;
}
