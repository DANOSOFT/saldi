<?php
// 20261022 CDX/LH Private invoice artifacts, isolated renderer workspaces and atomic cache publication.
// 20261025 CL/ASR Page images of the invoice PDF for the payment page (stripe_invoice_page_images).

require_once __DIR__ . '/stripeSettings.php';

/** @return bool Whether an absolute path is inside the supplied root. */
function stripe_pdf_inside($path, $root) {
	if ($root === '/') return substr($path, 0, 1) === '/';
	$root = rtrim((string)$root, '/');
	return $root !== '' && ($path === $root || strpos($path, $root . '/') === 0);
}

/** @return string Canonical absolute path, including a not-yet-created suffix; empty for symlinks or traversal. */
function stripe_pdf_absolute($path) {
	if (!is_string($path) || substr($path, 0, 1) !== '/' || strpos($path, "\0") !== false
		|| preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) return '';
	$path = rtrim($path, '/');
	if ($path === '') return '/';
	$probe = $path;
	$suffix = '';
	while (!file_exists($probe)) {
		if (is_link($probe) || dirname($probe) === $probe) return '';
		$suffix = '/' . basename($probe) . $suffix;
		$probe = dirname($probe);
	}
	if (is_link($probe) || !is_dir($probe)) return '';
	$real = realpath($probe);
	return $real === false ? '' : rtrim($real, '/') . $suffix;
}

/** @return bool Create only application-owned artifact directories, with no world access. */
function stripe_pdf_mkdir($dir) {
	if (is_link($dir)) return false;
	if (!is_dir($dir)) {
		$parent = dirname($dir);
		if (!is_dir($parent) && !stripe_pdf_mkdir($parent)) return false;
		if (!is_writable($parent)) return false;
		// Concurrent first renders may both observe an absent directory. Treat only
		// an already-created directory as success; every other mkdir warning is an error.
		set_error_handler(function ($severity, $message, $file, $line) {
			throw new ErrorException($message, 0, $severity, $file, $line);
		}, E_WARNING);
		try {
			if (!mkdir($dir, 0750) && !is_dir($dir)) return false;
		} catch (ErrorException $error) {
			clearstatcache(true, $dir);
			if (!is_dir($dir) || is_link($dir)) throw $error;
		} finally {
			restore_error_handler();
		}
	}
	if (!is_writable($dir)) return false;
	if ((fileperms($dir) & 0777) !== 0750 && !chmod($dir, 0750)) return false;
	return true;
}

/** @return bool Install an Apache deny-all rule before placing any artifacts in a public fallback directory. */
function stripe_pdf_deny_http($dir) {
	$rule = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
		. "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
	$file = $dir . '/.htaccess';
	if (is_link($file)) return false;
	if (!is_file($file) || file_get_contents($file) !== $rule) {
		if (file_put_contents($file, $rule, LOCK_EX) !== strlen($rule)) return false;
	}
	return chmod($file, 0640);
}

/**
 * Prefer a configured private directory, then the OS private temporary area.
 * Apache deny-all storage is the last resort; web servers ignoring .htaccess need an equivalent server rule.
 *
 * @param array<int, string>|null $privateCandidates Optional ordered installation-specific private locations.
 * @return string Protected artifact root, or empty when storage cannot be secured.
 */
function stripe_invoice_pdf_root($privateCandidates = null) {
	$webRoot = realpath(dirname(__DIR__, 2));
	$documentRoot = !empty($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
	$install = substr(hash('sha256', $webRoot), 0, 24);
	$configured = (string)(getenv('SALDI_STRIPE_PDF_DIR') ?: stripe_setting('pdf_private_dir'));
	// A configured parent may be /tmp or a shared private volume: only chmod our own child.
	$candidates = $privateCandidates ?? array(
		$configured === '' ? '' : rtrim($configured, '/') . '/saldi-stripe-' . $install,
		rtrim(sys_get_temp_dir(), '/') . '/saldi-stripe-' . $install
	);
	foreach ($candidates as $candidate) {
		$dir = stripe_pdf_absolute($candidate);
		if ($dir === '' || stripe_pdf_inside($dir, $webRoot)
			|| ($documentRoot && stripe_pdf_inside($dir, $documentRoot))) continue;
		if (stripe_pdf_mkdir($dir)) return $dir;
	}
	$dir = $webRoot . '/temp/.stripe-private-' . $install;
	if (!stripe_pdf_mkdir($dir) || !stripe_pdf_deny_http($dir)) return '';
	return $dir;
}

/** @return string Tenant-isolated protected directory; empty on missing configuration/storage. */
function stripe_invoice_pdf_dir() {
	global $db;
	if (!is_string($db) || !preg_match('/^[a-z0-9_]{1,40}$/', $db)) return '';
	// Seal artifacts left by the previous cache and shared renderer before using the new store.
	foreach (array('stripe_pdf', '99999') as $legacyName) {
		$legacy = dirname(__DIR__, 2) . '/temp/' . $db . '/' . $legacyName;
		if (is_link($legacy)) return '';
		if (is_dir($legacy) && (!stripe_pdf_mkdir($legacy) || !stripe_pdf_deny_http($legacy))) return '';
	}
	$root = stripe_invoice_pdf_root();
	if ($root === '') return '';
	$dir = $root . '/' . $db;
	return stripe_pdf_mkdir($dir) ? $dir : '';
}

/** @return string Domain-separated opaque cache filename, unrelated to a usable invoice link. */
function stripe_invoice_pdf_filename($ordreId) {
	global $db;
	$secret = stripe_setting('link_secret');
	if ((int)$ordreId < 1 || strlen($secret) < 64) return '';
	return hash_hmac('sha256', 'pdf-cache:v1:' . $db . ':' . (int)$ordreId, $secret) . '.pdf';
}

/** @return string Canonical destination without requiring the PDF to exist yet. */
function stripe_invoice_pdf_target($ordreId) {
	$name = stripe_invoice_pdf_filename($ordreId);
	if ($name === '') return '';
	$dir = stripe_invoice_pdf_dir();
	return $dir === '' ? '' : $dir . '/' . $name;
}

/** @return bool A complete, readable PDF cache entry; symlink artifacts are refused. */
function stripe_invoice_pdf_valid($pdf) {
	clearstatcache(true, $pdf);
	return $pdf !== '' && !is_link($pdf) && is_file($pdf) && is_readable($pdf)
		&& filesize($pdf) > 100 && file_get_contents($pdf, false, null, 0, 4) === '%PDF';
}

/** @return resource|false Stable per-invoice lock, shared by mail caching and the entire cold render. */
function stripe_invoice_pdf_lock($ordreId) {
	$pdf = stripe_invoice_pdf_target($ordreId);
	if ($pdf === '' || is_link($pdf . '.lock')) return false;
	$handle = fopen($pdf . '.lock', 'c');
	if (!$handle) return false;
	if (!chmod($pdf . '.lock', 0640) || !flock($handle, LOCK_EX)) {
		fclose($handle);
		return false;
	}
	return $handle;
}

/**
 * Publish from a completed source while the caller holds the invoice lock.
 * Rename is on the same filesystem; readers see the previous file or the complete replacement.
 *
 * @return bool Whether a complete PDF was published with private permissions.
 */
function stripe_invoice_pdf_publish($ordreId, $sourcePdf) {
	if (!stripe_invoice_pdf_valid($sourcePdf)) return false;
	$pdf = stripe_invoice_pdf_target($ordreId);
	if ($pdf === '' || is_link($pdf)) return false;
	$temp = tempnam(dirname($pdf), '.publish-');
	if ($temp === false) return false;
	try {
		if (dirname($temp) !== dirname($pdf)) return false;
		return chmod($temp, 0640) && copy($sourcePdf, $temp) && chmod($temp, 0640)
			&& stripe_invoice_pdf_valid($temp) && rename($temp, $pdf);
	} finally {
		if (is_file($temp)) unlink($temp);
	}
}

/** @return bool Cache a form-engine PDF under the per-invoice lock. */
function stripe_invoice_pdf_cache($ordreId, $sourcePdf) {
	if (!stripe_invoice_pdf_valid($sourcePdf)) return false;
	$lock = stripe_invoice_pdf_lock($ordreId);
	if (!$lock) return false;
	try {
		return stripe_invoice_pdf_publish($ordreId, $sourcePdf);
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}

/** @return string Unique private workspace for one form-engine render. */
function stripe_invoice_pdf_work_dir() {
	$dir = stripe_invoice_pdf_dir();
	if ($dir === '') return '';
	$work = $dir . '/render-' . bin2hex(random_bytes(16));
	return mkdir($work, 0750) ? $work : '';
}

/** @return void Remove only the caller's generated workspace without following symlinks. */
function stripe_invoice_pdf_cleanup($dir) {
	if (!is_dir($dir) || is_link($dir)) return;
	foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $entry) {
		if ($entry->isDir() && !$entry->isLink()) {
			stripe_invoice_pdf_cleanup($entry->getPathname());
		} else {
			unlink($entry->getPathname());
		}
	}
	rmdir($dir);
}

/** @return string Available CLI interpreter, using PHP_BINARY, installation setting or PATH; empty if unavailable. */
function stripe_invoice_pdf_php() {
	if (defined('PHP_BINARY') && PHP_BINARY && in_array(PHP_SAPI, array('cli', 'cli-server'), true)
		&& is_executable(PHP_BINARY)) return PHP_BINARY;
	$configured = (string)stripe_setting('php_cli');
	if ($configured !== '' && is_file($configured) && is_executable($configured)) return $configured;
	// FPM's PHP_BINARY is php-fpm, which cannot execute a CLI script.
	if (defined('PHP_BINDIR') && is_executable(PHP_BINDIR . '/php')) return PHP_BINDIR . '/php';
	foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $dir) {
		if ($dir !== '' && substr($dir, 0, 1) === '/' && is_file($dir . '/php') && is_executable($dir . '/php')) return $dir . '/php';
	}
	return '';
}

/** @return string Cached or freshly rendered private PDF; empty on failure. */
function stripe_invoice_pdf_path($ordreId, $generate = true) {
	global $db;
	$pdf = stripe_invoice_pdf_target($ordreId);
	if ($pdf === '') return '';
	if (stripe_invoice_pdf_valid($pdf)) return $pdf;
	if (!$generate) return '';
	$php = stripe_invoice_pdf_php();
	if ($php === '') return '';
	$cmd = escapeshellarg($php) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/tools/stripe_invoice_pdf.php')
		. ' --db=' . escapeshellarg($db) . ' --id=' . (int)$ordreId;
	if (!empty($_SERVER['DOCUMENT_ROOT'])) {
		$cmd .= ' --document-root=' . escapeshellarg($_SERVER['DOCUMENT_ROOT']);
	}
	$cmd .= ' 2>&1';
	exec($cmd, $out, $rc);
	if ($rc !== 0) {
		stripe_log('pdf render failed for ' . (int)$ordreId . ': ' . implode(' ', $out));
		return '';
	}
	return stripe_invoice_pdf_valid($pdf) ? $pdf : '';
}

/** @return string Ghostscript binary for page images (installation setting or the usual paths); empty if unavailable. */
function stripe_invoice_gs() {
	foreach (array((string)stripe_setting('gs'), '/usr/bin/gs', '/usr/local/bin/gs') as $gs) {
		if ($gs !== '' && is_file($gs) && is_executable($gs)) return $gs;
	}
	return '';
}

/** @return array<int, string> Complete page-image set for one PDF version; empty unless its page count marker is present. */
function stripe_invoice_page_list($key) {
	clearstatcache(true, $key . '.pages');
	if (is_link($key . '.pages') || !is_file($key . '.pages')) return array();
	$count = (int)file_get_contents($key . '.pages');
	$pages = array();
	for ($n = 1; $n <= $count; $n++) {
		$png = $key . '.p' . $n . '.png';
		if (is_link($png) || !is_file($png) || !is_readable($png)) return array();
		$pages[] = $png;
	}
	return $pages;
}

/**
 * 20261025 CL/ASR The invoice PDF as page images for the payment page, so the customer sees the invoice exactly as
 * the PDF (logo, layout) and not a summary. Cached next to the private PDF and keyed by a hash of its content, so a
 * new PDF version gets new images; old images are removed. Rendered under the invoice lock in a private workspace.
 * A PDF longer than the page cap, or a failed render, gives no images (the page then shows the HTML summary and
 * Download PDF); a failed render is not retried for an hour.
 *
 * @param int $ordreId Saldi order id.
 * @param bool $generate Render the PDF and/or images when missing.
 * @return array<int, string> Page PNG paths in order; empty when there are none.
 */
function stripe_invoice_page_images($ordreId, $generate = true) {
	$maxPages = 20;
	$pdf = stripe_invoice_pdf_path($ordreId, $generate);
	if ($pdf === '') return array();
	$key = $pdf . '.' . substr((string)hash_file('sha256', $pdf), 0, 16);
	$pages = stripe_invoice_page_list($key);
	if ($pages || !$generate) return $pages;
	clearstatcache(true, $key . '.failed');
	if (is_file($key . '.failed') && filemtime($key . '.failed') > time() - 3600) return array();
	$gs = stripe_invoice_gs();
	if ($gs === '') return array();
	$lock = stripe_invoice_pdf_lock($ordreId);
	if (!$lock) return array();
	try {
		$pages = stripe_invoice_page_list($key);
		if ($pages) return $pages;
		$work = stripe_invoice_pdf_work_dir();
		if ($work === '') return array();
		try {
			// One page more than the cap tells whether the PDF is too long to show as images.
			$cmd = escapeshellarg($gs) . ' -q -dSAFER -dBATCH -dNOPAUSE -dLastPage=' . ($maxPages + 1)
				. ' -sDEVICE=png16m -r144 -dTextAlphaBits=4 -dGraphicsAlphaBits=4'
				. ' -sOutputFile=' . escapeshellarg($work . '/p%d.png') . ' ' . escapeshellarg($pdf) . ' 2>&1';
			exec($cmd, $out, $rc);
			foreach (array_merge(glob($pdf . '.*.p*.png') ?: array(), glob($pdf . '.*.pages') ?: array(), glob($pdf . '.*.failed') ?: array()) as $old) {
				if (!is_link($old) && is_file($old)) unlink($old);
			}
			if ($rc !== 0 || !is_file($work . '/p1.png') || is_file($work . '/p' . ($maxPages + 1) . '.png')) {
				if ($rc !== 0 || !is_file($work . '/p1.png')) stripe_log('invoice page images failed for ' . (int)$ordreId . ': ' . implode(' ', $out));
				file_put_contents($key . '.failed', (string)time(), LOCK_EX);
				return array();
			}
			for ($n = 1; $n <= $maxPages && is_file($work . '/p' . $n . '.png'); $n++) {
				if (!chmod($work . '/p' . $n . '.png', 0640) || !rename($work . '/p' . $n . '.png', $key . '.p' . $n . '.png')) return array();
			}
			// The count marker is written last: readers only see a complete set.
			if (file_put_contents($key . '.pages', (string)($n - 1), LOCK_EX) === false || !chmod($key . '.pages', 0640)) return array();
		} finally {
			stripe_invoice_pdf_cleanup($work);
		}
		return stripe_invoice_page_list($key);
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}
