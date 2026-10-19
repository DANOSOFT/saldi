<?php
// --- includes/docsIncludes/poolUpload.php --- ver 5.0.0 --- 2026-10-05 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261004 LOE Reject identical pool uploads before conversion or extraction.
// 20261004 LOE Hold the pool lock only around the shared pool state, so an AI round-trip or a
//                 conversion cannot block other uploads, and read the duplicate message from
//                 findtekst() (5251) instead of a literal.
// 20261005 LOE SST-855 A field the extraction returned as null is treated as unread, so the file's
//                 own name and date are used again instead of being stored empty.
// 20261005 LOE SST-855 The upload lock sits in the tenant folder next to .pool-directories.lock, not
//                 inside the pool, so the REST attachment listing can neither show nor delete it.

require_once __DIR__ . '/../std_func.php';
require_once __DIR__ . '/FileReservation.php';
require_once __DIR__ . '/poolContentHash.php';
require_once __DIR__ . '/poolAmountNormalizer.php';
require_once __DIR__ . '/poolDateNormalizer.php';

/**
 * Prepare the pool table and both hash columns for browser/API entry points.
 *
 * @return void
 */
function poolUploadEnsureSchema() {
	global $db_type;
	$mysql = in_array($db_type, array('mysql', 'mysqli'), true);
	$schema = $mysql ? 'DATABASE()' : 'current_schema()';
	if (!db_fetch_array(db_select("SELECT table_name FROM information_schema.tables WHERE table_schema = $schema AND table_name = 'pool_files'", __FILE__ . ' line ' . __LINE__))) {
		$id = $mysql ? 'integer NOT NULL AUTO_INCREMENT' : 'serial NOT NULL';
		db_modify("CREATE TABLE IF NOT EXISTS pool_files (
			id $id PRIMARY KEY, filename varchar(255) NOT NULL UNIQUE,
			subject text, account varchar(50), amount varchar(50), norm_amount numeric(15,3),
			file_date varchar(50), invoice_number varchar(100), description text,
			manually_edited boolean NOT NULL DEFAULT false, currency varchar(10),
			updated timestamp DEFAULT CURRENT_TIMESTAMP,
			vendor_name text, vendor_cvr varchar(20), vendor_iban varchar(40),
			vendor_konto_id integer, vendor_match varchar(10), vendor_score numeric(4,3),
			content_sha256 char(64), source_sha256 char(64)
		)", __FILE__ . ' line ' . __LINE__);
	}
	if (!poolContentHashEnsureSchema() || !poolContentHashColumnExists(true, 'source_sha256')) {
		throw new RuntimeException('Kunne ikke klargøre puljens indholdskontrol.');
	}
}

/**
 * Serialize pool ingestion on the shared pool directory, including across PHP processes.
 *
 * @param string $poolDir Tenant's pool directory.
 * @return resource Exclusive lock; caller must fclose it in a finally block.
 */
function poolUploadLock($poolDir) {
	$poolDir = rtrim($poolDir, '/');
	if (!is_dir($poolDir)) {
		// Serialize recursive directory creation as well: simultaneous first uploads
		// must not both mkdir the same tenant pool and emit warnings.
		$ancestor = dirname($poolDir);
		while (!is_dir($ancestor) && dirname($ancestor) !== $ancestor) {
			$ancestor = dirname($ancestor);
		}
		$directoryLock = fopen($ancestor . '/.pool-directories.lock', 'c');
		if ($directoryLock === false) {
			throw new RuntimeException('Kunne ikke klargøre puljen.');
		}
		try {
			if (!flock($directoryLock, LOCK_EX)) {
				throw new RuntimeException('Kunne ikke låse puljens mappe.');
			}
			clearstatcache(true, $poolDir);
			if (!is_dir($poolDir) && !mkdir($poolDir, 0755, true)) {
				throw new RuntimeException('Kunne ikke oprette puljen.');
			}
		} finally {
			fclose($directoryLock);
		}
	}
	// The lock lives in the tenant folder, beside .pool-directories.lock, and never inside the pool
	// itself: the pool folder is what the REST attachment endpoint lists and allows to be deleted, so
	// a lock file in there would be listed as an attachment and could be removed by a client - which
	// would hand the next process a fresh file, and therefore a lock another process is not holding.
	// mkdir below creates the tenant folder as well, so it exists by the time the lock is opened.
	$lockDir = dirname($poolDir);
	$lock = fopen($lockDir . '/.uploads.lock', 'c');
	if ($lock === false) {
		throw new RuntimeException('Kunne ikke låse puljen til upload.');
	}
	if (!flock($lock, LOCK_EX)) {
		fclose($lock);
		throw new RuntimeException('Kunne ikke låse puljen til upload.');
	}
	return $lock;
}

/**
 * Backfill stored-file hashes without touching edited metadata or inventing image source hashes.
 *
 * @param string $poolDir Tenant's pool directory.
 * @return void
 */
function poolUploadBackfillHashes($poolDir) {
	$result = db_select("SELECT id, filename FROM pool_files WHERE content_sha256 IS NULL OR content_sha256 = ''", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($result)) {
		if (basename($row['filename']) !== $row['filename']) {
			continue;
		}
		$hash = poolContentHashForFile($poolDir . '/' . $row['filename']);
		if ($hash !== '') {
			db_modify("UPDATE pool_files SET content_sha256 = '" . db_escape_string($hash) . "' WHERE id = " . (int)$row['id'] . " AND (content_sha256 IS NULL OR content_sha256 = '')", __FILE__ . ' line ' . __LINE__);
		}
	}
}

/**
 * Find identical content, ignoring stale rows and also checking files not yet synchronized.
 *
 * @param string $poolDir Tenant's pool directory.
 * @param string $hash SHA-256 of the incoming original or stored bytes.
 * @return string|null Existing filename, or null when no live copy exists.
 */
function poolUploadFindDuplicate($poolDir, $hash) {
	$hashSql = db_escape_string($hash);
	$result = db_select("SELECT filename FROM pool_files WHERE content_sha256 = '$hashSql' OR source_sha256 = '$hashSql' ORDER BY id", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($result)) {
		if (basename($row['filename']) === $row['filename'] && is_file($poolDir . '/' . $row['filename'])) {
			return $row['filename'];
		}
	}
	// Rows with hashes have already been checked above. Read only unindexed files here.
	$indexed = array();
	$result = db_select("SELECT filename FROM pool_files WHERE content_sha256 IS NOT NULL AND content_sha256 != ''", __FILE__ . ' line ' . __LINE__);
	while ($row = db_fetch_array($result)) {
		$indexed[$row['filename']] = true;
	}
	$files = scandir($poolDir);
	if ($files === false) {
		throw new RuntimeException('Kunne ikke læse puljen.');
	}
	foreach ($files as $filename) {
		if (!isset($indexed[$filename]) && in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), array('pdf', 'xml', 'jpg', 'jpeg', 'png'), true)
			&& poolContentHashForFile($poolDir . '/' . $filename) === $hash) {
			return $filename;
		}
	}
	return null;
}

/**
 * An extracted field's value, or the fallback when the extraction returned nothing for it.
 *
 * extractInvoiceData() returns every key it knows, with null for a field it could not read, and
 * ifset() returns that null rather than its default because the key does exist. Both mean "nothing
 * was read", so the fallback - the file's own name or date - has to apply to them as well.
 *
 * @param array $data Extracted metadata.
 * @param string $key Field name.
 * @param mixed $fallback Value to use when the field is missing, null or empty.
 * @return mixed The extracted value, or the fallback.
 */
function poolUploadExtractedValue($data, $key, $fallback) {
	if (!is_array($data) || !array_key_exists($key, $data)) {
		return $fallback;
	}
	$value = $data[$key];
	if ($value === null || (is_scalar($value) && trim((string)$value) === '')) {
		return $fallback;
	}
	return $value;
}

/**
 * Insert a new pool row with its hashes; existing metadata remains untouched.
 *
 * @param string $path Saved document's absolute or relative path.
 * @param string $sourceHash Original upload SHA-256, or '' for an older file.
 * @param array|null $extracted Extracted invoice metadata.
 * @return void
 */
function poolUploadRegisterFile($path, $sourceHash = '', $extracted = null) {
	global $db_type;
	$filename = basename($path);
	$contentHash = poolContentHashForFile($path);
	if ($contentHash === '') {
		throw new RuntimeException('Kunne ikke beregne bilagets indholdshash.');
	}
	$data = is_array($extracted) ? $extracted : array();
	$amount = (string) poolUploadExtractedValue($data, 'amount', '');
	$normAmount = normalizePoolAmount($amount);
	$normSql = $normAmount === null ? 'NULL' : db_escape_string((string)$normAmount);
	$date = normalizeDateFormat((string) poolUploadExtractedValue($data, 'date', date('Y-m-d', filemtime($path))));
	$currency = normalizePoolCurrency(poolUploadExtractedValue($data, 'currency', ''));
	$values = array($filename, (string) poolUploadExtractedValue($data, 'vendor', pathinfo($filename, PATHINFO_FILENAME)), '', $amount,
		$date, (string) poolUploadExtractedValue($data, 'invoiceNumber', ''), (string) poolUploadExtractedValue($data, 'description', ''),
		$currency === null ? '' : $currency, $contentHash);
	$values = array_map(function ($value) { return "'" . db_escape_string($value) . "'"; }, $values);
	$sourceSql = $sourceHash === '' ? 'NULL' : "'" . db_escape_string($sourceHash) . "'";
	$conflict = in_array($db_type, array('mysql', 'mysqli'), true)
		? ' ON DUPLICATE KEY UPDATE id = id' : ' ON CONFLICT (filename) DO NOTHING';
	// A filename can have a stale row from a previous document. New uploads reserve a
	// filename before publishing, so the caller removes stale rows before publishing it.
	$sql = "INSERT INTO pool_files (filename, subject, account, amount, file_date, invoice_number, description, currency, content_sha256, source_sha256, norm_amount) VALUES ("
		. implode(', ', $values) . ", $sourceSql, $normSql)" . $conflict;
	db_modify($sql, __FILE__ . ' line ' . __LINE__);
	$row = db_fetch_array(db_select("SELECT content_sha256, source_sha256 FROM pool_files WHERE filename = '" . db_escape_string($filename) . "'", __FILE__ . ' line ' . __LINE__));
	if (!$row || $row['content_sha256'] !== $contentHash || ($sourceHash !== '' && $row['source_sha256'] !== $sourceHash)) {
		throw new RuntimeException('Kunne ikke registrere bilaget i puljen.');
	}
}

/**
 * Name the pool document a refused upload matches, in the user's language.
 *
 * @param string $filename Uploaded file's name.
 * @param string $existing Pool filename with identical content.
 * @return string Message for the upload summary.
 */
function poolUploadDuplicateMessage($filename, $existing) {
	global $sprog_id;
	return sprintf(findtekst('5257|%s ligger allerede i puljen som %s', $sprog_id), $filename, $existing);
}

/**
 * Save a browser upload once, using original bytes for identity before AI or conversion.
 *
 * Transport only: the posted $_FILES entry is moved to a private staging directory and handed to
 * poolUploadIngest(), which is where the pool logic lives and what the tests drive directly.
 *
 * @param array $upload PHP uploadedFile entry.
 * @param string $poolDir Tenant's pool directory.
 * @param bool $autoExtract Whether invoice extraction is enabled.
 * @param bool $renameFromInvoice Rename using vendor/date when extraction succeeds.
 * @return array{
 *   success: bool, duplicate?: bool, existing?: string, filename?: string,
 *   message: string, extracted?: array|null
 * }
 */
function poolUploadFile(array $upload, $poolDir, $autoExtract, $renameFromInvoice = true) {
	global $sprog_id;
	$stageDir = null;
	try {
		if ((ifset($upload, 'error', UPLOAD_ERR_NO_FILE)) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
			throw new RuntimeException('Upload mislykkedes. Prøv igen.');
		}
		$filename = basename($upload['name']);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		// Stage outside the pool so a page-load sync cannot insert partial files during AI work.
		$stageDir = sys_get_temp_dir() . '/saldi_pool_' . bin2hex(random_bytes(12));
		if (!mkdir($stageDir, 0700)) {
			throw new RuntimeException('Kunne ikke klargøre upload.');
		}
		$staged = $stageDir . '/upload.' . $ext;
		if (!move_uploaded_file($upload['tmp_name'], $staged)) {
			throw new RuntimeException('Kunne ikke gemme den uploadede fil.');
		}
		return poolUploadIngest($staged, $filename, $poolDir, $autoExtract, $renameFromInvoice);
	} catch (Throwable $error) {
		error_log('Pool upload failed: ' . $error->getMessage());
		return array('success' => false, 'message' => findtekst('5258|Bilaget kunne ikke uploades. Prøv igen.', $sprog_id));
	} finally {
		if ($stageDir !== null) {
			foreach ((array)glob($stageDir . '/*') as $leftover) {
				if (is_file($leftover)) {
					unlink($leftover);
				}
			}
			if (is_dir($stageDir)) {
				rmdir($stageDir);
			}
		}
	}
}

/**
 * Save one document into the pool, once.
 *
 * The bytes are identified before anything is written: the original file is hashed first (so an
 * image converted to PDF is still recognised by what was uploaded), the pool is checked for that
 * identity, and only then is the document extracted, converted, named and registered. The pool lock
 * is held around the shared state only - never while an AI round-trip or a conversion runs.
 *
 * @param string $sourcePath File holding the original bytes (an uploaded temp file).
 * @param string $filename Original file name, used for the extension and the pool name.
 * @param string $poolDir Tenant's pool directory.
 * @param bool $autoExtract Whether invoice extraction is enabled.
 * @param bool $renameFromInvoice Rename using vendor/date when extraction succeeds.
 * @return array{
 *   success: bool, duplicate?: bool, existing?: string, filename?: string,
 *   message: string, extracted?: array|null
 * }
 */
function poolUploadIngest($sourcePath, $filename, $poolDir, $autoExtract, $renameFromInvoice = true) {
	global $sprog_id;
	$lock = null;
	$converted = null;
	$reservation = null;
	$registered = false;
	try {
		$filename = basename($filename);
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if (!in_array($ext, array('pdf', 'xml', 'jpg', 'jpeg', 'png'), true)) {
			throw new RuntimeException('Filtypen er ikke tilladt.');
		}
		$sourceHash = poolContentHashForFile($sourcePath);
		if ($sourceHash === '') {
			throw new RuntimeException('Kunne ikke læse den uploadede fil.');
		}
		$lock = poolUploadLock($poolDir);
		try {
			poolUploadEnsureSchema();
			poolUploadBackfillHashes($poolDir);
			$duplicate = poolUploadFindDuplicate($poolDir, $sourceHash);
		} finally {
			fclose($lock);
			$lock = null;
		}
		if ($duplicate !== null) {
			return array('success' => false, 'duplicate' => true, 'existing' => $duplicate,
				'message' => poolUploadDuplicateMessage($filename, $duplicate));
		}
		$extracted = null;
		if ($autoExtract) {
			try {
				$extracted = extractInvoiceData($sourcePath, 'invoice-' . bin2hex(random_bytes(8)));
			} catch (Throwable $error) {
				error_log('Pool invoice extraction failed: ' . $error->getMessage());
			}
		}
		$storedPath = $sourcePath;
		$storedExt = $ext;
		if (in_array($ext, array('jpg', 'jpeg', 'png'), true)) {
			// The pool holds PDFs: the folder sync tracks PDF and XML only, so an image kept here
			// would be dropped from pool_files by the next sync while an upload of the same image is
			// still refused as a duplicate of a row that no longer exists. Convert with the
			// configured ImageMagick path and fail the upload when that yields no usable PDF.
			global $convert, $exec_path;
			$convertBin = (string)$convert;
			if ($convertBin === '') {
				$convertBin = rtrim((string)$exec_path, '/') . '/convert';
			}
			if ($convertBin === '/convert') {
				$convertBin = 'convert';
			}
			$converted = $sourcePath . '.pdf';
			$output = array();
			$status = 0;
			exec(escapeshellarg($convertBin) . ' ' . escapeshellarg($sourcePath) . ' ' . escapeshellarg($converted), $output, $status);
			$convertedSize = is_file($converted) ? filesize($converted) : false;
			if ($status !== 0 || $convertedSize === false || $convertedSize <= 0) {
				throw new RuntimeException('Kunne ikke konvertere billedet til PDF.');
			}
			$storedPath = $converted;
			$storedExt = 'pdf';
		}
		$contentHash = poolContentHashForFile($storedPath);
		$baseName = poolUploadSanitizeFilename(preg_replace('/\.pdf$/i', '', pathinfo($filename, PATHINFO_FILENAME)));
		if ($renameFromInvoice && is_array($extracted)) {
			$vendor = poolUploadSanitizeFilename((string)(ifset($extracted, 'vendor', '')));
			$date = poolUploadSanitizeFilename(normalizeDateFormat((string)(ifset($extracted, 'date', ''))));
			$baseName = $vendor !== '' && $date !== '' ? $vendor . '_' . $date : ($vendor !== '' ? $vendor : ($date !== '' ? $date : $baseName));
		}
		# Reserve, copy and register under the lock again: another request may have finished the same
		# upload while the extraction and the conversion above were running unlocked.
		$lock = poolUploadLock($poolDir);
		try {
			$duplicate = poolUploadFindDuplicate($poolDir, $sourceHash);
			if ($duplicate === null && $contentHash !== $sourceHash) {
				$duplicate = poolUploadFindDuplicate($poolDir, $contentHash);
			}
			if ($duplicate !== null) {
				return array('success' => false, 'duplicate' => true, 'existing' => $duplicate,
					'message' => poolUploadDuplicateMessage($filename, $duplicate));
			}
			$reservation = FileReservation::reserve($poolDir, $baseName === '' ? 'bilag' : $baseName, $storedExt, array('pdf', 'xml', 'jpg', 'jpeg', 'png', 'info'));
			if ($reservation === null) {
				throw new RuntimeException('Kunne ikke reservere et filnavn i puljen.');
			}
			// The reservation is an empty new file; remove any orphan row for its name.
			db_modify("DELETE FROM pool_files WHERE filename = '" . db_escape_string(basename($reservation->path())) . "'", __FILE__ . ' line ' . __LINE__);
			if (!copy($storedPath, $reservation->path())) {
				throw new RuntimeException('Kunne ikke gemme bilaget i puljen.');
			}
			poolUploadRegisterFile($reservation->path(), $sourceHash, $extracted);
			$registered = true;
		} finally {
			fclose($lock);
			$lock = null;
		}
		return array('success' => true, 'filename' => basename($reservation->path()), 'extracted' => $extracted,
			'message' => 'File uploaded successfully');
	} catch (Throwable $error) {
		error_log('Pool upload failed: ' . $error->getMessage());
		return array('success' => false, 'message' => findtekst('5258|Bilaget kunne ikke uploades. Prøv igen.', $sprog_id));
	} finally {
		if (!$registered && $reservation !== null) {
			$reservation->discard();
		}
		if ($converted !== null && is_file($converted)) {
			unlink($converted);
		}
		if (is_resource($lock)) {
			fclose($lock);
		}
	}
}

/**
 * Preserve the browser pool's filename transliteration and length limit.
 *
 * @param string $filename Original base name or extracted vendor/date.
 * @return string Safe filename component.
 */
function poolUploadSanitizeFilename($filename) {
	// Replace known extended Latin/Danish characters
	$translit = [
		'æ' => 'ae', 'Æ' => 'Ae',
		'ø' => 'oe', 'Ø' => 'Oe',
		'å' => 'aa', 'Å' => 'Aa',
		'ä' => 'ae', 'Ä' => 'Ae',
		'ö' => 'oe', 'Ö' => 'Oe',
		'ü' => 'ue', 'Ü' => 'Ue',
		'ß' => 'ss',
		'ñ' => 'n',  'Ñ' => 'N',
		'á' => 'a',  'Á' => 'A',
		'é' => 'e',  'É' => 'E',
		'í' => 'i',  'Í' => 'I',
		'ó' => 'o',  'Ó' => 'O',
		'ú' => 'u',  'Ú' => 'U'
	];
	$filename = strtr($filename, $translit);

	// Fallback transliteration for any remaining special chars
	$filename = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $filename);

	// Remove encoded scanner filename prefixes.
	$filename = preg_replace('/^(_{0,2}(UTF-8|ISO-8859-1)?_?Q_*)?/i', '', $filename);
	// Remove all but safe characters (replace with underscore)
	$filename = preg_replace('/[^\w\-\.]+/', '_', $filename);

	// Also explicitly replace spaces with underscores (to match _docPoolData.php behavior)
	$filename = str_replace(' ', '_', $filename);

	// Trim unwanted characters from ends
	$filename = trim($filename, " \t\n\r\0\x0B._");

	// Separate the name and extension
	$dot_position = strrpos($filename, '.');
	if ($dot_position !== false) {
		$name = substr($filename, 0, $dot_position);
		$ext = substr($filename, $dot_position); // Includes the dot
	} else {
		$name = $filename;
		$ext = '';
	}

	// Truncate the name part if longer than 54 characters
	if (strlen($name) > 54) {
		$name = substr($name, 0, 54);
	}

	return $name . $ext;
}
