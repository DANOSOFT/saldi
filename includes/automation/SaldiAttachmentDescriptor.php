<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiAttachmentDescriptor.php --- ver 5.0.0 --- 2026.10.10
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261008 CL/LH Add chat attachments and bank-line record context.
// 20261010 CDX/LH Apply the bank upload byte limit without the model argument cap.
// 20261010 CDX/LH Validate all upload contents before storage and preserve bank amounts and canonical owners.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';
require_once __DIR__ . '/../tools/SaldiBankTools.php';
require_once __DIR__ . '/../../restapi/core/Rights.php';
require_once __DIR__ . '/../../restapi/core/Subject.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../docsIncludes/invoiceExtractionApi.php';
require_once __DIR__ . '/../db_query.php';

/** Shared, server-selected storage and bounded extraction for web and mobile chat uploads. */
final class SaldiAttachmentDescriptor
{
	public const MAX_FILES = 5;
	public const MAX_BYTES = 15 * 1024 * 1024;
	public const MAX_BANK_BYTES = 2 * 1024 * 1024;

	/** @return never */
	private static function invalid(string $reason): void
	{
		throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid chat attachment', ['reason' => $reason]);
	}

	/** Normalize PHP's single-file and file[] multipart layouts without trusting client MIME or size. @return list<array{name:string,tmp_name:string,error:int}> */
	public static function files(array $files): array
	{
		$file = ifset($files, 'file');
		if (count($files) !== 1 || !is_array($file)) {
			self::invalid('invalid_files');
		}
		$names = ifset($file, 'name');
		$names = is_array($names) ? $names : [$names];
		if (count($names) < 1 || count($names) > self::MAX_FILES) {
			self::invalid('too_many_files');
		}
		$result = [];
		foreach ($names as $key => $name) {
			$path = is_array(ifset($file, 'tmp_name')) ? ifset($file['tmp_name'], $key) : ifset($file, 'tmp_name');
			$error = is_array(ifset($file, 'error')) ? ifset($file['error'], $key) : ifset($file, 'error');
			if (!is_string($name) || !is_string($path) || !is_int($error)) {
				self::invalid('invalid_files');
			}
			$result[] = ['name' => mb_substr(basename(str_replace('\\', '/', $name)), 0, 255), 'tmp_name' => $path, 'error' => $error];
		}
		return $result;
	}

	/** Detect supported bank formats structurally; extension and submitted MIME never select a parser. @return array{mime:string,bank:bool,content:string} */
	public static function sniff(string $content): array
	{
		$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);
		if (in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
			return ['mime' => $mime, 'bank' => false, 'content' => $content];
		}
		if (strlen($content) > self::MAX_BANK_BYTES) {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Bank statement too large', ['reason' => 'too_large', 'limit_bytes' => self::MAX_BANK_BYTES]);
		}
		if (str_contains($content, "\0")) {
			self::invalid('unsupported_type');
		}
		if (preg_match('/(?:^|\r?\n):20:/', $content) && preg_match('/(?:^|\r?\n):61:/', $content)) {
			return ['mime' => 'text/plain', 'bank' => true, 'content' => self::mt940($content)];
		}
		if (str_starts_with(ltrim(preg_replace('/^\xEF\xBB\xBF/', '', $content)), '<')) {
			if (!preg_match('/<(?:[\w.-]+:)?BkToCstmrStmt\b/', $content)) {
				self::invalid('unsupported_type');
			}
			SaldiBankStatementParser::parse($content);
			return ['mime' => 'application/xml', 'bank' => true, 'content' => $content];
		}
		try {
			$parsed = SaldiBankStatementParser::parse($content);
			if (!$parsed['rows']) {
				self::invalid('unsupported_type');
			}
		} catch (SaldiRuntimeError $e) {
			self::invalid('unsupported_type');
		}
		return ['mime' => 'text/csv', 'bank' => true, 'content' => $content];
	}

	/** Convert a bounded MT940 statement to canonical CSV for SaldiBankTools::import, preserving signs and decimal precision. @return string */
	private static function mt940(string $content): string
	{
		if (preg_match_all('/(?:^|\r?\n):60[FM]:/', $content) !== 1
			|| !preg_match('/(?:^|\r?\n):60[FM]:[CD][0-9]{6}([A-Z]{3})[0-9,]+/', $content, $balance)) {
			self::invalid('invalid_bank_file');
		}
		$rows = [];
		foreach (preg_split('/\r\n|\n|\r/', $content) as $line) {
			if (str_starts_with($line, ':61:')) {
				if (!preg_match('/^:61:([0-9]{6})(?:[0-9]{4})?(RC|RD|C|D)(?:[A-Z])?([0-9]+,[0-9]{0,3})([A-Z][A-Z0-9]{3})(.*)$/D', $line, $m)) {
					self::invalid('invalid_bank_file');
				}
				$year = (int)substr($m[1], 0, 2);
				$date = sprintf('%04d-%s-%s', $year < 70 ? $year + 2000 : $year + 1900, substr($m[1], 2, 2), substr($m[1], 4, 2));
				if (SaldiBankStatementParser::date($date) === null) {
					self::invalid('invalid_bank_file');
				}
				$amount = str_ends_with($m[3], ',') ? $m[3] . '00' : $m[3];
				$rows[] = [$date, $m[5], (in_array($m[2], ['D', 'RC'], true) ? '-' : '') . $amount, $balance[1], explode('//', $m[5], 2)[0]];
			} elseif (str_starts_with($line, ':86:') && $rows) {
				$rows[count($rows) - 1][1] = substr($line, 4);
			} elseif ($rows && $line !== '' && !str_starts_with($line, ':') && !in_array($line, ['-', '-}'], true)) {
				$rows[count($rows) - 1][1] .= ' ' . $line;
			}
		}
		if (!$rows || count($rows) > 5000) {
			self::invalid('invalid_bank_file');
		}
		$stream = fopen('php://temp', 'w+');
		try {
			fputcsv($stream, ['date', 'description', 'amount', 'currency', 'reference'], ';', '"', '');
			foreach ($rows as $row) {
				fputcsv($stream, $row, ';', '"', '');
			}
			rewind($stream);
			return stream_get_contents($stream);
		} finally {
			fclose($stream);
		}
	}

	/** Validate multipart metadata, detected types and permissions before storage. @return array{attachments:list<array{kind:string,id:string,filename:string,mime:string,bytes:int,extracted:array{text:?string,status:string,lines?:int}}>} */
	public static function upload(SaldiTenantContext $ctx, SaldiDb $db, array $files, ?string $purpose = null): array
	{
		if ($purpose !== null && $purpose !== 'chat') {
			self::invalid('invalid_purpose');
		}
		$prepared = [];
		foreach (self::files($files) as $file) {
			if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
				self::invalid('too_large');
			}
			if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
				self::invalid('invalid_files');
			}
			$content = file_get_contents($file['tmp_name'], false, null, 0, self::MAX_BYTES + 1);
			if ($content === false || $content === '') {
				self::invalid('invalid_files');
			}
			$type = self::sniff($content);
			if (strlen($content) > self::MAX_BYTES) {
				self::invalid('too_large');
			}
			$bankAccount = null;
			if ($type['bank']) {
				SaldiPermissionService::require($ctx, $db, 'journal.draft');
				$bankAccount = SaldiSettings::getUser($db, $ctx, 'assistant', 'bank_account', '');
				if (trim($bankAccount) === '') {
					self::invalid('bank_account_missing');
				}
				$parsed = SaldiBankStatementParser::parse($type['content']);
				$warnings = array_diff($parsed['warnings'], [SaldiText::get(null, 6180, 1, 'Filen er konverteret fra Windows-1252 til UTF-8.')]);
				if (!$parsed['rows'] || $warnings) {
					self::invalid('invalid_bank_file');
				}
			} else {
				SaldiPermissionService::require($ctx, $db, 'documents.read');
				if (!Rights::allows($ctx->rights, 'bilag', 'own', null, $ctx->userId < 0)) {
					throw new SaldiRuntimeError('PERMISSION_DENIED', 'Permission denied');
				}
			}
			if (!$type['bank']) {
				try {
					$type['content'] = self::pdf($content);
				} catch (AttachmentException $e) {
					self::invalid($e->error === 'payload_too_large' ? 'too_large' : 'invalid_document');
				}
			}
			$prepared[] = ['file' => $file, 'type' => $type, 'content' => $type['bank'] ? $content : null, 'account' => $bankAccount];
		}
		unset($content, $type);
		$attachments = [];
		foreach ($prepared as $entry) {
			if ($entry['type']['bank']) {
				$result = SaldiBankTools::importUpload($ctx, $db, ['content' => $entry['type']['content'], 'bank_account' => $entry['account'], 'filename' => $entry['file']['name']])['data'];
				$text = implode("\n", array_map(static fn(array $line): string => $line['date'] . ' ' . $line['description'] . ' ' . $line['amount'] . ' ' . $line['currency'], $result['lines']));
				$attachments[] = ['kind' => 'bank_file', 'id' => $result['batch_id'], 'filename' => $entry['file']['name'], 'mime' => $entry['type']['mime'], 'bytes' => strlen($entry['content']), 'extracted' => ['text' => mb_substr($text, 0, 4000), 'lines' => count($result['lines']), 'status' => 'ok']];
			} else {
				$attachments[] = self::document($ctx, $db, $entry['type']['content'], $entry['file']['name']);
			}
		}
		return ['attachments' => $attachments];
	}

	/** Use the existing GD converter, or the web pool's ImageMagick converter with bounded resources. @return string Validated PDF bytes. */
	private static function pdf(string $content): string
	{
		$mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);
		if ($mime === 'application/pdf' || extension_loaded('gd')) {
			return AttachmentUpload::pdf($content);
		}
		$image = getimagesizefromstring($content);
		if (!$image || $image[0] * $image[1] > AttachmentUpload::MAX_PIXELS) {
			self::invalid('invalid_document');
		}
		if (!is_executable('/usr/bin/convert') || !is_executable('/usr/bin/timeout') || !is_executable('/usr/bin/prlimit')) {
			self::invalid('invalid_document');
		}
		$path = tempnam(sys_get_temp_dir(), 'assist_image_');
		if ($path === false) {
			throw new RuntimeException('Unable to create image conversion file');
		}
		$pdfPath = $path . '.pdf';
		try {
			if (file_put_contents($path, $content) !== strlen($content)) {
				throw new RuntimeException('Unable to write image conversion file');
			}
			$command = ['/usr/bin/timeout', '12', '/usr/bin/prlimit', '--as=536870912', '--cpu=10', '--fsize=16777216', '--', '/usr/bin/convert', '-limit', 'memory', '64MiB', '-limit', 'map', '128MiB', ($mime === 'image/png' ? 'png:' : 'jpeg:') . $path . '[0]', 'pdf:' . $pdfPath];
			$process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
			if (!is_resource($process) || proc_close($process) !== 0 || !is_file($pdfPath)) {
				self::invalid('invalid_document');
			}
			return AttachmentUpload::pdf(file_get_contents($pdfPath));
		} finally {
			unlink($path);
			if (is_file($pdfPath)) {
				unlink($pdfPath);
			}
		}
	}

	/** Store validated PDF bytes in the same durable pool as the attachment endpoint, running the web extractor before publication. @return array{kind:string,id:string,filename:string,mime:string,bytes:int,extracted:array{text:?string,status:string}} */
	private static function document(SaldiTenantContext $ctx, SaldiDb $db, string $pdf, string $filename): array
	{
		$cfg = saldi_runtime_config();
		$quote = static fn(string $value): string => "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
		$dsn = 'host=' . $quote($cfg['host']) . ' port=' . (int)$cfg['port'] . ' dbname=' . $quote($ctx->db) . ' user=' . $quote($cfg['user']) . ' password=' . $quote($cfg['password']);
		$connection = pg_connect($dsn, PGSQL_CONNECT_FORCE_NEW);
		if ($connection === false) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Attachment storage unavailable');
		}
		$hadConnection = array_key_exists('connection', $GLOBALS);
		$previous = ifset($GLOBALS, 'connection');
		$previousDependencies = ifset($GLOBALS, 'invoiceExtractionApiDependencies');
		$path = null;
		try {
			$GLOBALS['connection'] = $connection;
			$path = sys_get_temp_dir() . '/assist_' . bin2hex(random_bytes(16)) . '.pdf';
			if (file_put_contents($path, $pdf, LOCK_EX) !== strlen($pdf)) {
				throw new RuntimeException('Unable to write extraction file');
			}
			$dependencies = is_array($previousDependencies) ? $previousDependencies : [];
			$dependencies += ['key_resolver' => static fn() => saldi_runtime_master()->value("SELECT var_value FROM settings WHERE var_grp = 'app_api' AND var_name = 'apikey' ORDER BY id DESC LIMIT 1"), 'url_resolver' => static function (): string {
				$url = (string)saldi_runtime_master()->value("SELECT var_value FROM settings WHERE var_grp = 'app_api' AND var_name = 'extract_url' ORDER BY id DESC LIMIT 1");
				return preg_match('~^https://~i', $url) ? $url : 'https://wuweiworkai.com/extract-invoice';
			}];
			$GLOBALS['invoiceExtractionApiDependencies'] = $dependencies;
			$extracted = extractInvoiceData($path);
			$metadata = $extracted === null ? [] : ['vendor' => $extracted['vendor'], 'total_amount' => $extracted['amount'], 'invoice_date' => $extracted['date'], 'invoice_number' => $extracted['invoiceNumber'], 'invoice_description' => $extracted['description'], 'currency' => $extracted['currency'], 'vendor_vat_number' => $extracted['vendorCvr'], 'vendor_iban' => $extracted['vendorIban'], 'vendor_bank_reg' => $extracted['vendorBankReg'], 'vendor_bank_account' => $extracted['vendorBankKonto'], 'customer_vat_number' => $extracted['customerCvr']];
			$hex = bin2hex(random_bytes(16));
			$clientId = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20);
			$owner = Subject::build($ctx->userId < 0 ? 'registry' : 'account', abs($ctx->userId));
			$store = new DurableAttachmentModel($connection, $ctx->db, $owner);
			$result = $store->upload(['content' => $pdf, 'sha256' => hash('sha256', $pdf), 'size' => strlen($pdf), 'client_id' => $clientId, 'extracted_data' => $metadata, 'accountnr' => '', 'subject' => $filename]);
			$text = $extracted === null ? null : implode("\n", array_filter(array_map(static fn($value): string => (string)$value, array_intersect_key($extracted, array_flip(['vendor', 'amount', 'date', 'invoiceNumber', 'description', 'currency']))), static fn(string $value): bool => $value !== ''));
			return ['kind' => 'document', 'id' => (string)$result['row']['id'], 'filename' => $filename, 'mime' => 'application/pdf', 'bytes' => strlen($pdf), 'extracted' => ['text' => $text === null ? null : mb_substr($text, 0, 4000), 'status' => $text === null ? 'failed' : 'ok']];
		} catch (AttachmentException $e) {
			self::invalid($e->error === 'payload_too_large' ? 'too_large' : 'invalid_document');
		} finally {
			if ($path !== null && is_file($path)) {
				unlink($path);
			}
			if ($hadConnection) {
				$GLOBALS['connection'] = $previous;
			} else {
				unset($GLOBALS['connection']);
			}
			$GLOBALS['invoiceExtractionApiDependencies'] = $previousDependencies;
			pg_close($connection);
		}
	}
}
