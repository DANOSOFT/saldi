<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AssistantUploadRecordTest.php --- ver 5.0.0 --- 2026.10.10
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
// 20261008 CL/LH Exercise chat upload storage, staging and revision-bound records.
// 20261010 CDX/LH Cover bank upload byte limits on mobile and web.
// 20261010 CDX/LH Cover repeated file parts, bank amounts, ownership, validation and record envelopes.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthRevocation.php';
require_once __DIR__ . '/../../includes/runtime/bootstrap.php';
require_once __DIR__ . '/../../includes/automation/SaldiAttachmentDescriptor.php';
require_once __DIR__ . '/../../includes/assist/RecordService.php';
require_once __DIR__ . '/../../includes/mobileUpdates.php';

final class AssistantUploadRecordTest extends TestCase
{
	private static $master;
	private static $tenant;
	private static SaldiDb $db;
	private static array $user;
	private static string $token;
	private static string $session;
	private static string $csrf;
	private static string $key;
	private static $process;
	private static string $log;
	private static string $url;
	private static int $tenantId;
	private array $paths = [];

	public static function setUpBeforeClass(): void
	{
		if (($reason = RestApiEnv::unavailableReason()) !== null) {
			self::markTestSkipped($reason);
		}
		RestApiEnv::bootstrapTenant();
		self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
		self::$tenant = RestApiEnv::connect(RestApiEnv::testDb());
		mobileTenantUpdates(self::$tenant);
		global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;
		include __DIR__ . '/../../includes/connect.php';
		JWT::setSecret(_jwtLoadSecret());
		JWT::setClaimEnricher(null);
		self::$db = SaldiDb::connect(saldi_runtime_config(), RestApiEnv::testDb());
		saldi_runtime_ensure_schema(self::$db);
		self::$tenantId = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
		self::$user = RestApiEnv::rows(self::$tenant, 'UPDATE brugere SET regnskabsaar=4,language_id=1,rettigheder=$1 WHERE brugernavn=$2 RETURNING *', [str_repeat('1', 16), RestApiEnv::user()])[0];
		self::$db->exec('DELETE FROM kontoplan WHERE kontonr = 5500 AND regnskabsaar = 4');
		self::$db->insert('kontoplan', ['kontonr' => 5500, 'regnskabsaar' => 4, 'kontotype' => 'S', 'lukket' => '', 'beskrivelse' => 'Test bank']);
		self::$db->exec("DELETE FROM grupper WHERE art='RA' AND kodenr=4");
		self::$db->insert('grupper', ['art' => 'RA', 'kodenr' => 4, 'box1' => '1', 'box2' => '2025', 'box3' => '12', 'box4' => '2025', 'box5' => 'on', 'box10' => '']);
		self::$token = (new MobileAuth())->create(self::$user, 'account', self::$tenantId, 'assist-upload-test')['access_token'];
		self::$session = bin2hex(random_bytes(16));
		self::$csrf = bin2hex(random_bytes(32));
		self::$key = TestCredentials::password('assistb3');
		RestApiEnv::rows(self::$master, 'INSERT INTO online(session_id,db,brugernavn,regnskabsaar,logtime,revisor) VALUES($1,$2,$3,4,$4,false)', [self::$session, RestApiEnv::testDb(), RestApiEnv::user(), time()]);
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		$address = stream_socket_get_name($socket, false);
		fclose($socket);
		self::$url = 'http://' . $address;
		self::$log = tempnam(sys_get_temp_dir(), 'assist_b3_log_');
		$env = getenv();
		$env['SALDI_B3_TEST_KEY'] = self::$key;
		$env['SALDI_B3_TEST_SESSION'] = self::$session;
		$env['SALDI_B3_TEST_CSRF'] = self::$csrf;
		$env['SALDI_ASSIST_CONTEXT_SECRET'] = TestCredentials::password('assistb3context');
		self::$process = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=16M', '-d', 'post_max_size=90M', '-d', 'display_errors=1', '-S', $address, __DIR__ . '/support/assist_b3_router.php'], [0 => ['file', '/dev/null', 'r'], 1 => ['file', self::$log, 'a'], 2 => ['file', self::$log, 'a']], $pipes, dirname(__DIR__, 2), $env);
		self::assertIsResource(self::$process);
		for ($i = 0; $i < 100; $i++) {
			$curl = curl_init(self::$url);
			curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT_MS => 100]);
			$ready = curl_exec($curl) !== false;
			curl_close($curl);
			if ($ready) {
				return;
			}
			usleep(10000);
		}
		throw new RuntimeException('Upload test server unavailable');
	}

	public static function tearDownAfterClass(): void
	{
		if (is_resource(self::$process)) {
			proc_terminate(self::$process);
			proc_close(self::$process);
		}
		if (isset(self::$log)) {
			unlink(self::$log);
		}
		if (isset(self::$db)) {
			$directory = dirname(poolPuljePath(RestApiEnv::testDb()));
			if (is_dir($directory)) {
				$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
				foreach ($iterator as $file) {
					$file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
				}
				rmdir($directory);
			}
			self::$db->close();
			RestApiEnv::rows(self::$master, 'DELETE FROM online WHERE session_id=$1', [self::$session]);
			pg_close(self::$master);
			pg_close(self::$tenant);
		}
		saldi_runtime_reset();
		RestApiEnv::teardownTenant();
	}
	protected function setUp(): void
	{
		self::$db->exec('UPDATE brugere SET rettigheder=? WHERE id=?', [str_repeat('1', 16), self::$user['id']]);
		$ctx = saldi_runtime_context_for_user(self::$db, ['id' => self::$tenantId], (int)self::$user['id'], 'user:' . self::$user['id']);
		SaldiSettings::setUser(self::$db, $ctx, 'assistant', 'bank_account', '5500');
	}
	protected function tearDown(): void
	{
		foreach ($this->paths as $path) {
			unlink($path);
		}
		$this->paths = [];
	}
	/** @return string Complete small PDF with a valid cross-reference table. */
	private function pdf(): string
	{
		$objects = ['<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>', "<< /Length 0 >>\nstream\n\nendstream"];
		$pdf = "%PDF-1.4\n";
		$offsets = [];
		foreach ($objects as $i => $object) {
			$offsets[] = strlen($pdf);
			$pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen($pdf);
		$pdf .= "xref\n0 5\n0000000000 65535 f \n";
		foreach ($offsets as $offset) {
			$pdf .= sprintf("%010d 00000 n \n", $offset);
		}
		return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
	}
	/** @return array{status:int,json:array} Real multipart transport: PHP validates is_uploaded_file. */
	private function upload(array $contents, string $route = '/upload', array $extraHeaders = [], string $name = 'misleading.exe', bool $repeated = false): array
	{
		$parts = ['purpose' => 'chat'];
		$boundary = 'assist_' . bin2hex(random_bytes(16));
		$multipart = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"purpose\"\r\n\r\nchat\r\n";
		foreach ($contents as $i => $content) {
			if ($repeated) {
				$multipart .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"file[]\"; filename=\"" . $name . "\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $content . "\r\n";
				continue;
			}
			$path = tempnam(sys_get_temp_dir(), 'assist_b3_file_');
			file_put_contents($path, $content);
			$this->paths[] = $path;
			$parts[count($contents) === 1 ? 'file' : 'file[' . $i . ']'] = new CURLFile($path, 'application/octet-stream', $name);
		}
		if ($repeated) {
			$extraHeaders[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
			return $this->request($route, $multipart . '--' . $boundary . "--\r\n", $extraHeaders);
		}
		return $this->request($route, $parts, $extraHeaders);
	}
	private function request(string $route, array|string $body, array $extraHeaders = []): array
	{
		$headers = ['X-B3-Test: ' . self::$key, 'Authorization: Bearer ' . self::$token, 'Origin: ' . self::$url];
		foreach ($extraHeaders as $extra) {
			$name = strtolower(strtok($extra, ':'));
			$headers = array_values(array_filter($headers, static fn(string $header): bool => strtolower(strtok($header, ':')) !== $name));
			$headers[] = $extra;
		}
		if ($route === '/record') {
			$headers[] = 'Content-Type: application/json';
		}
		$curl = curl_init(self::$url . $route);
		curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $route === '/record' ? json_encode($body) : $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30]);
		$output = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);
		$json = json_decode((string)$output, true);
		self::assertIsArray($json, (string)$output);
		return ['status' => $status, 'json' => $json];
	}
	public function testPdfAndPngUsePoolAndExtractorAndTrimUtf8Text(): void
	{
		$png = file_get_contents(__DIR__ . '/../includes/fixtures/assist/tiny.png');
		foreach ([$this->pdf(), $png] as $content) {
			$r = $this->upload([$content]);
			self::assertSame(200, $r['status'], json_encode($r['json']));
			$a = $r['json']['data']['attachments'][0];
			self::assertSame('document', $a['kind']);
			self::assertSame('application/pdf', $a['mime']);
			self::assertMatchesRegularExpression('/^[0-9]+$/', $a['id']);
			self::assertSame('ok', $a['extracted']['status']);
			self::assertSame(4000, mb_strlen($a['extracted']['text']));
			$row = self::$db->one('SELECT * FROM pool_files WHERE id=?', [$a['id']]);
			self::assertSame('misleading.exe', $a['filename']);
			self::assertSame('ubehandlet', $row['status']);
			self::assertSame('12.50', $row['amount']);
			$owner = Subject::build('account', (int)self::$user['id']);
			self::assertSame($owner, $row['uploaded_by']);
			$store = new DurableAttachmentModel(self::$tenant, RestApiEnv::testDb(), $owner);
			self::assertSame((int)$a['id'], (int)$store->find(['id' => $a['id']])['id']);
			self::assertContains((int)$a['id'], array_map('intval', array_column($store->listing(100, 0, null, true)['items'], 'id')));
			$stored = file_get_contents(dirname(poolPuljePath(RestApiEnv::testDb())) . '/' . $row['storage_path']);
			self::assertStringStartsWith('%PDF-', $stored);
			self::assertSame(strlen($stored), $a['bytes']);
		}
	}

	public function testRepeatedFileArrayPartsReturnEveryDescriptorOnMobileAndWeb(): void
	{
		foreach ([['/upload', []], ['/web', ['X-Saldi-Csrf: ' . self::$csrf]]] as [$route, $headers]) {
			foreach ([1, 2, 5] as $count) {
				$r = $this->upload(array_fill(0, $count, $this->pdf()), $route, $headers, repeated: true);
				self::assertSame(200, $r['status'], json_encode($r['json']));
				$data = $route === '/web' ? $r['json'] : $r['json']['data'];
				self::assertCount($count, $data['attachments']);
				self::assertCount($count, array_unique(array_column($data['attachments'], 'id')));
			}
		}
	}
	public function testMt940WholeAmountsPreserveEveryMovement(): void
	{
		foreach ([['/upload', []], ['/web', ['X-Saldi-Csrf: ' . self::$csrf]]] as [$route, $headers]) {
			$mt = ":20:TEST\n:60F:C250604DKK100,\n:61:250604D12,NTRFWHOLE\n:61:250605C1,25NTRFFRACTION\n:61:250606RC2,NTRFREVERSE\n:62F:C250606DKK87,25\n";
			$r = $this->upload([$mt], $route, $headers);
			self::assertSame(200, $r['status'], json_encode($r['json']));
			$data = $route === '/web' ? $r['json'] : $r['json']['data'];
			$a = $data['attachments'][0];
			self::assertSame(3, $a['extracted']['lines']);
			self::assertSame('ok', $a['extracted']['status']);
			$lines = self::$db->all('SELECT amount FROM bank_import_lines WHERE batch_id=? ORDER BY line_no', [$a['id']]);
			self::assertSame(['-12.000', '1.250', '-2.000'], array_column($lines, 'amount'));
		}
	}
	public function testBankParseWarningsRejectUploadBeforeAnyStorage(): void
	{
		foreach ([['/upload', []], ['/web', ['X-Saldi-Csrf: ' . self::$csrf]]] as [$route, $headers]) {
			$beforePool = self::$db->value('SELECT count(*) FROM pool_files');
			$beforeBank = self::$db->value('SELECT count(*) FROM bank_import_lines');
			foreach (["date;description;amount\n2025-06-04;Valid;12.50\n2025-06-05;Invalid;broken\n",
				":20:TEST\n:60F:C250604DKK100,00\n:61:250604D12,50NTRFVALID\n:61:250605D1234567890123,NTRFTOOLARGE\n"] as $bank) {
				$r = $this->upload([$this->pdf(), $bank], $route, $headers);
				self::assertSame(422, $r['status'], json_encode($r['json']));
				$data = $route === '/web' ? $r['json'] : $r['json']['data'];
				self::assertSame('invalid_bank_file', $data['details']['reason']);
				self::assertSame($beforePool, self::$db->value('SELECT count(*) FROM pool_files'));
				self::assertSame($beforeBank, self::$db->value('SELECT count(*) FROM bank_import_lines'));
			}
		}
	}
	public function testTruncatedLaterPdfDoesNotPublishEarlierDocuments(): void
	{
		foreach ([['/upload', []], ['/web', ['X-Saldi-Csrf: ' . self::$csrf]]] as [$route, $headers]) {
			$before = self::$db->value('SELECT count(*) FROM pool_files');
			for ($attempt = 0; $attempt < 2; $attempt++) {
				$r = $this->upload([$this->pdf(), "%PDF-1.4\nbroken"], $route, $headers);
				self::assertSame(422, $r['status'], json_encode($r['json']));
				$data = $route === '/web' ? $r['json'] : $r['json']['data'];
				self::assertSame('invalid_document', $data['details']['reason']);
				self::assertSame($before, self::$db->value('SELECT count(*) FROM pool_files'));
			}
		}
	}
	public function testBankFormatsOnlyStageAndNeverCreateDurableBankOrJournalRows(): void
	{
		$before = [];
		foreach (['bank_lines', 'kassekladde', 'transaktioner', 'inbox_items'] as $table) {
			$before[$table] = self::$db->value('SELECT count(*) FROM ' . $table);
		}
		$csv = "Dato;Tekst;Beløb\n04-06-2025;Betaling Æble;12,50\n";
		$xml = file_get_contents(__DIR__ . '/../includes/fixtures/bank/camt053.xml');
		$mt = ":20:TEST\n:25:TESTACCOUNT\n:60F:C250604DKK100,00\n:61:250604D12,50NTRFREF\n:86:Betaling Æble\n:61:250605RC1,00NTRFREVERSE\n:62F:C250605DKK86,50\n";
		foreach ([$csv, mb_convert_encoding($csv, 'Windows-1252', 'UTF-8'), $xml, $mt] as $content) {
			$r = $this->upload([$content]);
			self::assertSame(200, $r['status'], json_encode($r['json']));
			$a = $r['json']['data']['attachments'][0];
			self::assertSame('bank_file', $a['kind']);
			self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a['id']);
			self::assertSame('ok', $a['extracted']['status']);
			$lines = self::$db->all('SELECT * FROM bank_import_lines WHERE batch_id=? ORDER BY line_no', [$a['id']]);
			self::assertCount($a['extracted']['lines'], $lines);
			self::assertNotEmpty($lines);
			foreach ($lines as $line) {
				self::assertNull($line['inbox_item_id']);
			}
			if ($content === $mt) {
				self::assertSame(['-12.500', '-1.000'], array_column($lines, 'amount'));
				self::assertSame(['REF', 'REVERSE'], array_column($lines, 'reference'));
			}
		}
		foreach ($before as $table => $count) {
			self::assertSame($count, self::$db->value('SELECT count(*) FROM ' . $table));
		}
	}
	public function testValidationReasonsAndCountAreStable(): void
	{
		foreach ([['plain unsupported', 'unsupported_type'], [str_repeat('x', 15 * 1024 * 1024 + 1), 'too_large']] as [$content, $reason]) {
			$r = $this->upload([$content]);
			self::assertSame(422, $r['status']);
			self::assertSame('VALIDATION_ERROR', $r['json']['data']['error']);
			self::assertSame($reason, $r['json']['data']['details']['reason']);
		}
		$r = $this->upload(array_fill(0, 6, $this->pdf()));
		self::assertSame(422, $r['status']);
		self::assertSame('too_many_files', $r['json']['data']['details']['reason']);
		self::$db->exec("DELETE FROM settings WHERE var_grp='assistant' AND var_name='bank_account' AND user_id=?", [self::$user['id']]);
		$r = $this->upload(["date;description;amount\n2025-06-04;Test;12.50\n"]);
		self::assertSame(422, $r['status']);
		self::assertSame('bank_account_missing', $r['json']['data']['details']['reason']);
		$ctx = saldi_runtime_context_for_user(self::$db, ['id' => self::$tenantId], (int)self::$user['id'], 'user:' . self::$user['id']);
		SaldiSettings::setUser(self::$db, $ctx, 'assistant', 'bank_account', '5500');
		$largeBank = "date;description;amount\n2025-06-04;" . str_repeat('x', 2097153 - strlen("date;description;amount\n2025-06-04;;12.50\n")) . ";12.50\n";
		$r = $this->upload([$largeBank]);
		self::assertSame(422, $r['status']);
		self::assertSame('too_large', $r['json']['data']['details']['reason']);
		self::assertSame(2097152, $r['json']['data']['details']['limit_bytes']);
	}
	public function testBankUploadByteLimitOnMobileAndWeb(): void
	{
		foreach ([['/upload', []], ['/web', ['X-Saldi-Csrf: ' . self::$csrf]]] as [$route, $headers]) {
			foreach ([["date;description;amount\n2025-06-04;", ";12.50\n"],
				[":20:TEST\n:60F:C250604DKK100,00\n:61:250604D12,50NTRFREF\n:86:", "\n:62F:C250604DKK87,50\n"]] as [$prefix, $suffix]) {
				$content = $prefix . str_repeat('x', 1572864 - strlen($prefix) - strlen($suffix)) . $suffix;
				self::assertSame(1572864, strlen($content));
				$r = $this->upload([$content], $route, $headers);
				self::assertSame(200, $r['status'], json_encode($r['json']));
				$data = $route === '/web' ? $r['json'] : $r['json']['data'];
				self::assertSame('bank_file', $data['attachments'][0]['kind']);
				self::assertSame(1572864, $data['attachments'][0]['bytes']);
				self::assertSame(1, $data['attachments'][0]['extracted']['lines']);
				$content = $prefix . str_repeat('x', 2097153 - strlen($prefix) - strlen($suffix)) . $suffix;
				$r = $this->upload([$content], $route, $headers);
				self::assertSame(422, $r['status']);
				$data = $route === '/web' ? $r['json'] : $r['json']['data'];
				self::assertSame('VALIDATION_ERROR', $data['error']);
				self::assertSame(['reason' => 'too_large', 'limit_bytes' => 2097152], $data['details']);
			}
		}
	}

	public function testFiveFilesAndAllFileValidationBeforeStorageAndBankTextCap(): void
	{
		$csv = "date;description;amount\n";
		for ($i = 0; $i < 40; $i++) {
			$csv .= '2025-06-04;' . str_repeat('Æ', 200) . ";12.50\n";
		}
		$r = $this->upload(array_fill(0, 5, $csv));
		self::assertSame(200, $r['status'], json_encode($r['json']));
		self::assertCount(5, $r['json']['data']['attachments']);
		foreach ($r['json']['data']['attachments'] as $attachment) {
			self::assertSame(4000, mb_strlen($attachment['extracted']['text']));
		}
		$before = self::$db->value('SELECT count(*) FROM pool_files');
		$r = $this->upload([$this->pdf(), 'unsupported']);
		self::assertSame(422, $r['status']);
		self::assertSame($before, self::$db->value('SELECT count(*) FROM pool_files'));
	}
	public function testUnsafeXmlAndMultistatementMt940AreRejected(): void
	{
		$xml = '<!DOCTYPE Document [<!ENTITY x SYSTEM "file:///etc/passwd">]><Document><BkToCstmrStmt>&x;</BkToCstmrStmt></Document>';
		self::assertSame(422, $this->upload([$xml])['status']);
		$mt = ":20:T\n:60F:C250604DKK100,00\n:61:250604D12,50NTRFT\n:60F:C250604EUR100,00\n:61:250604D12,50NTRFT\n";
		self::assertSame(422, $this->upload([$mt])['status']);
	}

	public function testPermissionChecksAndReadOnlyDocuments(): void
	{
		foreach ([['0000000100000000', 'date;description;amount' . "\n2025-06-04;Test;12.50\n", 403], ['2000000000000000', $this->pdf(), 403], ['0000000200000000', $this->pdf(), 200], ['0000000000000000', $this->pdf(), 403]] as [$rights, $content, $status]) {
			self::$db->exec('UPDATE brugere SET rettigheder=? WHERE id=?', [$rights, self::$user['id']]);
			$r = $this->upload([$content]);
			self::assertSame($status, $r['status'], json_encode($r['json']));
		}
	}
	public function testWebTwinCsrfAndSameDescriptorShape(): void
	{
		foreach ([[], ['X-Saldi-Csrf: bad'], ['X-Saldi-Csrf: ' . self::$csrf, 'Origin: https://foreign.test']] as $headers) {
			self::assertSame(403, $this->upload([$this->pdf()], '/web', $headers)['status']);
		}
		$r = $this->upload([$this->pdf()], '/web', ['X-Saldi-Csrf: ' . self::$csrf]);
		self::assertSame(200, $r['status'], json_encode($r['json']));
		self::assertSame('document', $r['json']['attachments'][0]['kind']);
	}
	public function testFailedExtractionStoresDocumentAndReportsNullText(): void
	{
		$r = $this->upload([$this->pdf()], '/extract-failed');
		self::assertSame(200, $r['status']);
		self::assertSame(['text' => null, 'status' => 'failed'], $r['json']['data']['attachments'][0]['extracted']);
	}
	public function testBankLineGrantReadRevisionAndDeniedPermission(): void
	{
		$import = self::$db->insert('bank_line_imports', ['source' => 'csv', 'account_kontonr' => 5500, 'kladde_id' => 1, 'imported_by' => 'account:' . self::$user['id']]);
		$id = self::$db->insert('bank_lines', ['account_kontonr' => 5500, 'line_key' => hash('sha256', random_bytes(16)), 'source' => 'csv', 'transdate' => '2025-06-04', 'amount' => '-12.50', 'currency' => 'DKK', 'text' => 'Syntetisk banklinje', 'first_import_id' => $import]);
		$body = ['operation' => 'grant', 'kind' => 'bank_line', 'record_id' => $id, 'embed_session' => str_repeat('a', 32)];
		$r = $this->request('/record', $body);
		self::assertSame(200, $r['status'], json_encode($r['json']));
		$grant = $r['json'];
		self::assertSame('2025-06-04', $grant['date']);
		self::assertSame('-12.50', $grant['amount']);
		self::assertNull($grant['matched']);
		self::assertSame('bank_line', SaldiAssistRecordAuth::verify($grant['grant'], 'k2026a', TestCredentials::password('assistb3context'), time())['kind']);
		$read = $this->request('/record', ['operation' => 'get_bank_line_context', 'revision' => $grant['revision']], ['Authorization: Bearer ' . $grant['grant']]);
		self::assertSame(200, $read['status'], json_encode($read['json']));
		self::assertSame($grant['revision'], $read['json']['revision']);
		self::assertArrayHasKey('kind', $read['json']);
		self::assertSame('bank_line', $read['json']['kind']);
		self::assertSame($id, $read['json']['record_id']);
		self::assertSame('saved', $read['json']['basis']);
		self::assertSame('get_bank_line_context', $read['json']['operation']);
		self::assertSame('unmatched', $read['json']['status']);
		self::assertNotFalse(strtotime($read['json']['checked_at']));
		foreach (['date', 'text', 'amount', 'currency', 'matched'] as $field) {
			self::assertSame($grant[$field], $read['json'][$field]);
		}
		$missing = $body;
		$missing['record_id'] = 2147483647;
		self::assertSame(404, $this->request('/record', $missing)['status']);
		$journal = self::$db->insert('kladdeliste', ['kladdenote' => 'B3 test', 'bogfort' => '-']);
		$line = self::$db->insert('kassekladde', ['kladde_id' => $journal, 'bilag' => 1, 'transdate' => '2025-06-04', 'beskrivelse' => 'Test', 'amount' => '12.50']);
		self::$db->insert('documents', ['source' => 'kassekladde', 'source_id' => $line, 'filename' => 'test.pdf']);
		self::$db->exec('INSERT INTO bank_line_journal_links(bank_line_id,kassekladde_id,import_id) VALUES(?,?,?)', [$id, $line, $import]);
		$matched = $this->request('/record', $body);
		self::assertSame(200, $matched['status']);
		self::assertSame(['kind' => 'journal', 'id' => $journal], $matched['json']['matched']);
		self::assertNotSame($grant['revision'], $matched['json']['revision']);
		$matchedRead = $this->request('/record', ['operation' => 'get_bank_line_context', 'revision' => $matched['json']['revision']], ['Authorization: Bearer ' . $matched['json']['grant']]);
		self::assertSame(200, $matchedRead['status'], json_encode($matchedRead['json']));
		self::assertSame('matched', $matchedRead['json']['status']);
		self::assertSame(['kind' => 'journal', 'id' => $journal], $matchedRead['json']['matched']);
		self::$db->exec('UPDATE bank_lines SET text=? WHERE id=?', ['Ændret', $id]);
		self::assertSame(409, $this->request('/record', ['operation' => 'get_bank_line_context', 'revision' => $grant['revision']], ['Authorization: Bearer ' . $grant['grant']])['status']);
		self::$db->exec('UPDATE brugere SET rettigheder=? WHERE id=?', ['2000000000000000', self::$user['id']]);
		self::assertSame(403, $this->request('/record', $body)['status']);
		self::assertSame(403, $this->request('/record', ['operation' => 'get_bank_line_context', 'revision' => $grant['revision']], ['Authorization: Bearer ' . $grant['grant']])['status']);
	}
}
