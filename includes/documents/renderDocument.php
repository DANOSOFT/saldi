<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/documents/renderDocument.php --- ver 5.1.0 --- 2026.10.30
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// Headless document rendering (WP11-1, plan P:597-616): a posted invoice or credit note as PDF,
// without a browser session, for the worker, the document link endpoint (L11b) and the REST API.
// formularprint() writes the print into a work directory of its own ($headless, core edit 6) and
// renderPrintBatch() turns it into one PDF with the brand's letterhead. The PDF is kept as
// temp/<db>/.ht_doc_<sha>.pdf (Apache denies .ht*), written under a lock, so concurrent renders
// of the same document produce it once and share it.
//
// 20261029 CL/SZ SD-751: created (WP11-1).
// 20261029 CL/SZ SD-751: a brand without a letterhead of its own renders without one, never on another brand's letterhead.
// 20261029 CL/SZ SD-751 review: $levNr reaches formularprint()'s global $lev_nr; docblock says which data a stored PDF freezes.
// 20261030 CL/SZ SD-751 review: the account (db_id) is in the cache key; a brand that isn't a plain file name is refused.

require_once __DIR__ . '/../stdFunc/renderPrintBatch.php';

/**
 * A document that cannot be rendered. Code NO_DOCUMENT: the order is no posted invoice or credit note,
 * so there is no document to give; otherwise the document exists but its brand has no layout or the
 * print or PDF conversion failed.
 */
final class DocumentRenderError extends RuntimeException
{
	public const NO_DOCUMENT = 1;
}

/**
 * Renders a posted invoice (formular 4) or credit note (5) of the connected tenant to PDF.
 * The order's art decides between the two, as in formularprint(). The brand is the order's sprog
 * (empty is dansk); a brand without its own layout is refused, never printed in another brand's
 * layout, and a form-editor language lock does not apply. The letterhead is the brand's own; a brand
 * without one renders without a letterhead, not on the default one (and so without the EPS logo,
 * which formularprint() leaves out whenever the company has a PDF letterhead). A PDF already rendered for the same order
 * data, document number, brand and layout rows is returned as is: a posted document stays as first
 * rendered. The key holds the order's customer, date and totals and the layout rows; a later change to
 * the company's Stamdata (eget_*, e.g. the bank account), the order's address or att fields, the
 * employee (ansat_*) or a letterhead file is not noticed (temp/<db>/.ht_doc_* can be deleted to render again).
 *
 * Needs the tenant connection and legacy globals ($db, $db_id, $regnaar, ...), e.g. from
 * shopify_bootstrap() or the REST API's connect. The working directory is restored afterwards.
 *
 * @param int $ordreId ordrer.id
 * @param int $formular 4 (invoice) or 5 (credit note)
 * @param int|null $levNr delivery number, set as formularprint()'s global $lev_nr for the print (part of the key)
 * @param array<string, mixed> $opts pdftk and ps2pdf (commands). pdftk defaults to the global $pdftk, else
 *   the master setting, else pdftk; ps2pdf to the master setting, else $exec_path/ps2pdf
 * @return array{
 *   path: string,      Absolute path of temp/<db>/.ht_doc_<sha>.pdf.
 *   filename: string,  Download name with the document's own number, e.g. fakt1042.pdf or kn1043.pdf.
 *   formular: int,     4 or 5, as printed.
 *   brand: string,     The layout language (lower-case ordrer.sprog).
 *   rendered: bool,    False when an earlier render was returned.
 * }
 * @throws InvalidArgumentException for a formular other than 4 or 5
 * @throws DocumentRenderError when the order can't be rendered
 */
function render_document(int $ordreId, int $formular, ?int $levNr = null, array $opts = []): array
{
	global $db, $db_id, $exec_path, $lev_nr, $pdftk;

	if ($formular !== 4 && $formular !== 5) {
		throw new InvalidArgumentException("render_document renders invoices (4) and credit notes (5), not formular $formular");
	}
	if (!function_exists('db_select') || !is_string($db) || !preg_match('/^[a-z0-9_]+$/', $db)) {
		throw new DocumentRenderError('no tenant connection');
	}
	$qtxt = "SELECT art, status, fakturanr, sprog, afd, konto_id, firmanavn, fakturadate, sum, moms FROM ordrer WHERE id = $ordreId";
	$order = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if (!$order || !in_array($order['art'], array('DO', 'DK'), true)) {
		throw new DocumentRenderError("order $ordreId is not an invoice or credit note", DocumentRenderError::NO_DOCUMENT);
	}
	$fakturanr = trim((string)$order['fakturanr']);
	if ((int)$order['status'] < 3 || $fakturanr === '') {
		throw new DocumentRenderError("order $ordreId is not posted", DocumentRenderError::NO_DOCUMENT);
	}
	$formular = $order['art'] === 'DK' ? 5 : 4;
	$brand = strtolower((string)$order['sprog']) ?: 'dansk'; // as formularprint() reads it, untrimmed
	if ($brand !== basename($brand)) {
		// the brand names the letterhead files in logolib/, so it never leaves the account's folder
		throw new DocumentRenderError("brand $brand is not a file name");
	}
	// formularprint() falls back to another layout when the brand has no line fields; refuse instead.
	$brandSql = "lower(sprog) = '" . db_escape_string($brand) . "'";
	$qtxt = "SELECT 1 FROM formularer WHERE formular = $formular AND art = 3 AND beskrivelse <> 'generelt' AND $brandSql LIMIT 1";
	if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		throw new DocumentRenderError("no layout for formular $formular in brand $brand");
	}
	// The layout's rows, so an edited layout renders a new PDF (the form editors update rows in place).
	$layout = hash_init('sha256');
	$q = db_select("SELECT * FROM formularer WHERE formular = $formular AND $brandSql ORDER BY id", __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($q)) {
		hash_update($layout, json_encode($row) . "\n");
	}
	$layout = hash_final($layout);

	$tool = (string)($opts['pdftk'] ?? ($pdftk ?: render_document_setting('pdftk', 'pdftk')));
	if (strpos($tool, '/') === false) {
		$tool = trim((string)shell_exec('command -v ' . escapeshellarg($tool) . ' 2>/dev/null'));
	}
	if ($tool === '' || !is_executable($tool)) {
		// formularprint() picks the PDF letterhead only when $pdftk is an existing file, as udskriv.php requires
		throw new DocumentRenderError('pdftk is not installed');
	}
	$ps2pdf = (string)($opts['ps2pdf'] ?? render_document_setting('ps2pdf', ($exec_path ?: '/usr/bin') . '/ps2pdf'));
	$pv = db_fetch_array(db_select("SELECT box2, box3 FROM grupper WHERE art = 'PV'", __FILE__ . " linje " . __LINE__));
	$psCommand = !empty($pv['box2']) ? $pv['box2'] : $ps2pdf;
	$html = empty($pv['box2']) && !empty($pv['box3']);

	$includes = dirname(__DIR__);
	$dir = dirname($includes) . "/temp/$db";
	if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
		throw new DocumentRenderError("cannot create $dir");
	}
	// The order's own data is in the key, so a restored backup's different invoice under the same id and
	// number never gets this one's PDF. The account is in it too: the letterhead comes from logolib/<db_id>/,
	// and two accounts can share one database.
	$sha = hash('sha256', implode('|', array($db, (int)$db_id, $ordreId, $formular, (int)$levNr, $fakturanr, $brand, $order['konto_id'],
		$order['firmanavn'], $order['fakturadate'], $order['sum'], $order['moms'], $layout)));
	$path = "$dir/.ht_doc_$sha.pdf";
	$out = array(
		'path' => $path,
		'filename' => ($formular === 5 ? 'kn' : 'fakt') . "$fakturanr.pdf",
		'formular' => $formular,
		'brand' => $brand,
		'rendered' => false,
	);

	$lockFile = "$dir/.ht_doc_$sha.lock";
	$lock = fopen($lockFile, 'c');
	if ($lock === false || !flock($lock, LOCK_EX)) {
		throw new DocumentRenderError("cannot lock document $sha");
	}
	if (fileowner($lockFile) === (function_exists('posix_geteuid') ? posix_geteuid() : getmyuid())) {
		chmod($lockFile, 0666); // the worker (cli) and Apache may run as different users
	}
	$cwd = getcwd();
	$globalPdftk = $pdftk;
	$globalLevNr = $lev_nr;
	$pdftk = $tool; // formularprint() reads it
	$lev_nr = $levNr; // formularprint() replaces its $lev_nr argument with this global
	$work = "$dir/.ht_doc_$sha." . bin2hex(random_bytes(6)) . '.work';
	try {
		clearstatcache(true, $path);
		if (is_file($path) && filesize($path) > 0) {
			return $out;
		}
		if (!mkdir($work, 0700)) {
			throw new DocumentRenderError("cannot create $work");
		}
		// formularprint() and formfunk.php include ../includes/..., read ../logolib and write ../temp
		chdir($includes);
		require_once $includes . '/formfunk.php';
		ob_start();
		try {
			$documents = formularprint($ordreId, $formular, $levNr, 'UTF-8', 'PDF', null, $work);
		} finally {
			ob_end_clean();
		}
		if (!is_array($documents) || $documents === array()) {
			throw new DocumentRenderError("order $ordreId: " . (is_string($documents) ? $documents : 'nothing was printed'));
		}
		foreach ($documents as $i => $document) {
			$documents[$i]['background'] = render_document_letterhead((string)($document['background'] ?? ''), $brand, $formular, (int)$order['afd'], (int)$db_id, dirname($includes) . '/logolib');
		}
		try {
			renderPrintBatch($work, $documents, 'document.pdf', $html, $psCommand, $tool, true);
		} catch (RuntimeException $e) {
			throw new DocumentRenderError("order $ordreId: " . $e->getMessage(), 0, $e);
		}
		if (!rename("$work/document.pdf", $path)) {
			throw new DocumentRenderError("cannot publish $path");
		}
		$out['rendered'] = true;
		return $out;
	} finally {
		if ($cwd !== false) {
			chdir($cwd);
		}
		$pdftk = $globalPdftk;
		$lev_nr = $globalLevNr;
		if (is_dir($work)) {
			foreach (scandir($work) as $file) {
				if ($file !== '.' && $file !== '..') {
					unlink("$work/$file");
				}
			}
			rmdir($work);
		}
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}

/**
 * The brand's own letterhead for a document, or '' for none. formularprint() takes the first letterhead
 * it finds, and for a brand other than dansk that can be the department's or the company's default
 * (dansk) letterhead; another brand's document is never printed on it.
 *
 * @param string $picked The letterhead formularprint() chose ('' for none).
 * @param string $logolib Absolute path of logolib/.
 */
function render_document_letterhead(string $picked, string $brand, int $formular, int $afd, int $dbId, string $logolib): string
{
	if ($brand === 'dansk' || $brand === 'danish') {
		return $picked; // formularprint() only looks at the unprefixed (dansk) letterheads for these
	}
	if ($picked !== '' && strpos(basename($picked), $brand . '_') === 0) {
		return $picked;
	}
	$type = $formular === 5 ? 'kreditnota_bg' : 'faktura_bg';
	$candidates = array();
	if ($afd > 0) {
		$candidates[] = "$logolib/$dbId/$afd/{$brand}_$type.pdf";
		$candidates[] = "$logolib/$dbId/$afd/{$brand}_bg.pdf";
	}
	$candidates[] = "$logolib/$dbId/{$brand}_$type.pdf";
	$candidates[] = "$logolib/$dbId/{$brand}_bg.pdf";
	foreach ($candidates as $candidate) {
		if (is_file($candidate)) {
			return $candidate;
		}
	}
	return '';
}

/** A tool path from the master settings (as includes/online.php reads them), else $default. */
function render_document_setting(string $name, string $default): string
{
	$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = '" . db_escape_string($name) . "'", __FILE__ . " linje " . __LINE__, true));
	return $r && trim((string)$r['var_value']) !== '' ? trim((string)$r['var_value']) : $default;
}
