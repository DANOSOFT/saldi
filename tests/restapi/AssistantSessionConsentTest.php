<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AssistantSessionConsentTest.php --- ver 5.0.0 --- 2026.10.10
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
// 20261008 CL/LH Created for the mobile Assist contract.
// 20261010 CDX/LH Close the shared auth tenant connection before dropping the fixture.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/RestApiEnv.php';
require_once __DIR__ . '/../../restapi/core/MobileAuth.php';
require_once __DIR__ . '/../../restapi/core/MobileAuthRevocation.php';
require_once __DIR__ . '/../../includes/runtime/bootstrap.php';

final class AssistantSessionConsentTest extends TestCase
{
	private static $master;
	private static $tenant;
	private static SaldiDb $runtime;
	private static array $user;
	private static array $registry;
	private static int $id;
	private array $pair;
	private array $families = [];
	private const REF = '0123456789abcdef0123456789abcdef';
	public static function setUpBeforeClass(): void
	{
		if (($reason = RestApiEnv::unavailableReason()) !== null) { self::markTestSkipped($reason); }
		RestApiEnv::bootstrapTenant();
		global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection;
		require_once __DIR__ . '/../../includes/db_query.php';
		include __DIR__ . '/../../includes/connect.php';
		JWT::setSecret(_jwtLoadSecret());
		JWT::setClaimEnricher(null);
		self::$master = RestApiEnv::connect(RestApiEnv::masterDb());
		// Own the link used by in-process refreshes so teardown closes pg_connect's cached link.
		$previous = $connection;
		self::$tenant = db_connect($sqhost, $squser, $sqpass, RestApiEnv::testDb(), __FILE__);
		$connection = $previous;
		self::$id = RestApiEnv::regnskabId(RestApiEnv::accountOpen());
		self::$user = RestApiEnv::rows(self::$tenant, 'SELECT * FROM brugere WHERE brugernavn=$1', [RestApiEnv::user()])[0];
		self::$registry = RestApiEnv::rows(self::$master, 'INSERT INTO brugere(brugernavn,kode,rettigheder,status) VALUES ($1,$2,$3,true) RETURNING *', ['assist_revisor_' . bin2hex(random_bytes(8)), md5(TestCredentials::password('assistrevisor')), ',,,' . self::$id])[0];
		self::$runtime = SaldiDb::connect(saldi_runtime_config(), RestApiEnv::testDb());
		saldi_runtime_ensure_schema(self::$runtime);
	}
	public static function tearDownAfterClass(): void
	{
		if (isset(self::$runtime)) { self::$runtime->close(); }
		if (self::$master) {
			foreach ([Subject::build('account', (int)self::$user['id']), Subject::build('registry', (int)self::$registry['id'])] as $subject) {
				foreach (['refresh_sessions', 'auth_versions', 'auth_challenges'] as $table) {
					RestApiEnv::rows(self::$master, "DELETE FROM $table WHERE subject=$1", [$subject]);
				}
			}
			RestApiEnv::rows(self::$master, 'DELETE FROM brugere WHERE id=$1', [self::$registry['id']]);
			pg_close(self::$master); pg_close(self::$tenant);
		}
		saldi_runtime_reset();
		RestApiEnv::teardownTenant();
	}
	protected function setUp(): void
	{
		self::$runtime->exec("UPDATE brugere SET rettigheder = '1111111111111111' WHERE id = ?", [self::$user['id']]);
		self::$runtime->exec("DELETE FROM settings WHERE var_grp = 'assistant' AND var_name = 'improvement_consent'");
		self::$runtime->exec('DELETE FROM agent_grants');
		$ctx = saldi_runtime_context_for_user(self::$runtime, ['id' => self::$id], (int)self::$user['id'], 'user:' . self::$user['id']);
		SaldiSettings::set(self::$runtime, $ctx, 'assistant', 'tools_enabled', 'on');
		$this->pair = $this->create();
	}
	protected function tearDown(): void
	{
		foreach ($this->families as $family) {
			RestApiEnv::rows(self::$master, "DELETE FROM auth_rate_limits WHERE bucket_type='account' AND bucket_key=$1", [hash('sha256', 'assistant-session:' . $family)]);
		}
	}
	private function create(bool $revisor = false): array
	{
		$pair = (new MobileAuth())->create($revisor ? self::$registry : self::$user, $revisor ? 'registry' : 'account', self::$id, 'assist-test');
		$this->families[] = JWT::decode($pair['access_token'])['sid'];
		return $pair;
	}
	/** Real JWT gate and real endpoint, with deployment configuration isolated in a CLI child. @return array{status:int,json:array} */
	private function request(string $endpoint = 'session', string $method = 'POST', ?array $body = null, ?array $pair = null, string $url = 'https://chat.example/'): array
	{
		$process = proc_open([PHP_BINARY, __DIR__ . '/support/run_assist_b1.php'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
		self::assertIsResource($process);
		fwrite($pipes[0], json_encode(['token' => ($pair ?? $this->pair)['access_token'], 'method' => $method, 'endpoint' => $endpoint, 'body' => $body ?? ['embed_session' => self::REF, 'mcp' => true], 'chat_url' => $url, 'context_secret' => TestCredentials::password('assistcontext')]));
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); $status = (int)stream_get_contents($pipes[3]);
		foreach ([1, 2, 3] as $n) { fclose($pipes[$n]); }
		self::assertSame(0, proc_close($process), $errors);
		self::assertSame('', $errors);
		$json = json_decode($output, true);
		self::assertIsArray($json, 'Endpoint must return a JSON envelope');
		return ['status' => $status, 'json' => $json];
	}
	private function claims(string $token): array
	{
		$parts = explode('.', $token);
		self::assertSame(4, count($parts));
		$expected = rtrim(strtr(base64_encode(hash_hmac('sha256', implode('.', array_slice($parts, 0, 3)), TestCredentials::password('assistcontext'), true)), '+/', '-_'), '=');
		self::assertTrue(hash_equals($expected, $parts[3]), 'Context signature must verify');
		return json_decode(base64_decode(strtr($parts[2], '-_', '+/')), true);
	}
	public function testSessionHappyPathAndFamilyCapAcrossEmbedSessions(): void
	{
		$first = null;
		for ($i = 0; $i < 6; $i++) {
			$r = $this->request('session', 'POST', ['embed_session' => hash('md5', 'embed-' . $i), 'mcp' => true]);
			self::assertSame(200, $r['status']);
			$data = $r['json']['data'];
			self::assertSame('assist', $data['mode']);
			self::assertSame('https://chat.example', $data['chat_base_url']);
			self::assertNull($data['consent']);
			$claims = $this->claims($data['context_token']);
			self::assertSame(hash('md5', 'embed-' . $i), $claims['embed_session']);
			self::assertSame($claims['tenant_hash'], $data['tenant_hash']);
			self::assertSame(900, $claims['exp'] - $claims['iat']);
			self::assertSame($claims['exp'], $data['context_expires_at']);
			self::assertGreaterThan(time() + 28700, $data['mcp_expires_at']);
			$first ??= SaldiGrantService::parse($data['mcp_token'])['grant_id'];
		}
		self::assertSame(5, (int)self::$runtime->value("SELECT COUNT(*) FROM agent_grants WHERE revoked_at IS NULL AND kind = 'mobile'"));
		self::assertNotNull(self::$runtime->value('SELECT revoked_at FROM agent_grants WHERE id = ?', [$first]));
	}
	public function testForbiddenWithoutAssistantRightAndAssistDisabledConfiguration(): void
	{
		self::$runtime->exec("UPDATE brugere SET rettigheder = '' WHERE id = ?", [self::$user['id']]);
		$r = $this->request();
		self::assertSame(403, $r['status']); self::assertSame('FORBIDDEN', $r['json']['data']['error']);
		self::$runtime->exec("UPDATE brugere SET rettigheder = '0000000000000000' WHERE id = ?", [self::$user['id']]);
		self::assertSame('FORBIDDEN', $this->request()['json']['data']['error']);
		self::$runtime->exec("UPDATE brugere SET rettigheder = '1111111111111111' WHERE id = ?", [self::$user['id']]);
		$r = $this->request('session', 'POST', null, null, '');
		self::assertSame(403, $r['status']); self::assertSame('ASSIST_DISABLED', $r['json']['data']['error']);
	}
	public function testHelpWithMcpFalseOrToolsDisabled(): void
	{
		foreach ([false, true] as $mcp) {
			if ($mcp) { self::$runtime->exec("UPDATE settings SET var_value = 'off' WHERE var_grp = 'assistant' AND var_name = 'tools_enabled'"); }
			$r = $this->request('session', 'POST', ['embed_session' => self::REF, 'mcp' => $mcp]);
			self::assertSame(200, $r['status']); self::assertSame('help', $r['json']['data']['mode']);
			self::assertNull($r['json']['data']['mcp_token']); self::assertNull($r['json']['data']['mcp_expires_at']);
		}
		self::assertSame(0, (int)self::$runtime->value('SELECT COUNT(*) FROM agent_grants'));
	}
	public function testRevisorGetsNegativeActorGrantAndConsent(): void
	{
		$pair = $this->create(true);
		$r = $this->request('session', 'POST', null, $pair);
		self::assertSame(200, $r['status']);
		$claims = $this->claims($r['json']['data']['context_token']);
		$ctx = saldi_runtime_context_for_revisor((int)self::$registry['id'], RestApiEnv::testDb());
		self::assertTrue($ctx->isHuman()); self::assertSame(-((int)self::$registry['id']), $ctx->userId);
		self::assertSame($ctx->userHash(), $claims['user_hash']);
		self::assertSame('assist', $r['json']['data']['mode']);
		$grant = saldi_runtime_context_from_token($r['json']['data']['mcp_token']);
		self::assertSame($ctx->userId, $grant['ctx']->onBehalfOf()); $grant['db']->close();
		self::assertSame(200, $this->request('consent', 'POST', ['consent' => 'yes'], $pair)['status']);
		self::assertSame('yes', self::$runtime->value("SELECT var_value FROM settings WHERE var_name = 'improvement_consent' AND user_id = ?", [$ctx->userId]));
		self::assertSame('yes', $this->request('session', 'POST', null, $pair)['json']['data']['consent']);
		RestApiEnv::rows(self::$master, "UPDATE brugere SET rettigheder=',,,' WHERE id=$1", [self::$registry['id']]);
		try {
			self::assertSame(403, $this->request('consent', 'GET', null, $pair)['status']);
		} finally { RestApiEnv::rows(self::$master, 'UPDATE brugere SET rettigheder=$1 WHERE id=$2', [',,,' . self::$id, self::$registry['id']]); }
	}
	public function testConsentNullThenYesNoAndValidation(): void
	{
		self::assertSame(['consent' => null], $this->request('consent', 'GET')['json']['data']);
		foreach (['yes', 'no'] as $value) {
			$r = $this->request('consent', 'POST', ['consent' => $value]);
			self::assertSame(200, $r['status']); self::assertSame(['consent' => $value], $r['json']['data']);
			self::assertSame(['consent' => $value], $this->request('consent', 'GET')['json']['data']);
		}
		foreach ([[], ['consent' => true], ['consent' => 'on'], ['consent' => null]] as $body) {
			$r = $this->request('consent', 'POST', $body);
			self::assertSame(422, $r['status']); self::assertSame('VALIDATION_ERROR', $r['json']['data']['error']);
		}
		self::assertNull(self::$runtime->value("SELECT pos_id FROM settings WHERE var_name = 'improvement_consent'"));
	}
	public function testRevokedFamilyDeniedAndRevokeSessionsCleansGrants(): void
	{
		$r = $this->request(); self::assertSame(200, $r['status']);
		$family = JWT::decode($this->pair['access_token'])['sid'];
		RestApiEnv::rows(self::$master, 'UPDATE refresh_sessions SET revoked_at=now() WHERE family_id=$1', [$family]);
		$r = $this->request(); self::assertSame(401, $r['status']); self::assertSame('GRANT_REVOKED', $r['json']['data']['error']);
		revokeSessions(Subject::build('account', (int)self::$user['id']), 'logout_all', self::$id);
		self::assertSame(0, (int)self::$runtime->value("SELECT COUNT(*) FROM agent_grants WHERE kind = 'mobile' AND revoked_at IS NULL"));
	}
	public function testRefreshReuseRevokesMobileGrants(): void
	{
		self::assertSame(200, $this->request()['status']);
		$auth = new MobileAuth();
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		$p = JWT::decode($this->pair['refresh_token']);
		$first = $auth->refresh($p, 'assist-test');
		$auth->refresh(JWT::decode($first['refresh_token']), 'assist-test');
		try { $auth->refresh($p, 'assist-test'); self::fail('Old refresh accepted'); }
		catch (MobileAuthError $e) { self::assertSame('session_revoked', $e->error); }
		self::assertSame(0, (int)self::$runtime->value("SELECT COUNT(*) FROM agent_grants WHERE kind = 'mobile' AND revoked_at IS NULL"));
	}
	public function testThirtySessionRequestsPerFamilyPerMinute(): void
	{
		$family = JWT::decode($this->pair['access_token'])['sid'];
		$key = hash('sha256', 'assistant-session:' . $family);
		RestApiEnv::rows(self::$master, "INSERT INTO auth_rate_limits(bucket_type,bucket_key,tokens,updated_at) VALUES ('account',$1,0,now())", [$key]);
		$r = $this->request(); self::assertSame(429, $r['status']); self::assertSame('RATE_LIMITED', $r['json']['data']['error']);
		RestApiEnv::rows(self::$master, "UPDATE auth_rate_limits SET updated_at=now()-INTERVAL '2 seconds' WHERE bucket_key=$1", [$key]);
		self::assertSame(200, $this->request()['status']);
	}
	public function testSessionValidationAndAnonymousHttpRequests(): void
	{
		foreach ([[], ['embed_session' => 'bad', 'mcp' => true], ['embed_session' => self::REF, 'mcp' => 1]] as $body) {
			$r = $this->request('session', 'POST', $body); self::assertSame(422, $r['status']);
		}
		foreach (['session', 'consent'] as $endpoint) {
			$r = RestApiEnv::http('GET', '/restapi/endpoints/v1/assistant/' . $endpoint . '.php');
			self::assertSame(401, $r['status']); self::assertSame('UNAUTHENTICATED', $r['json']['data']['error']);
		}
	}
	public function testRegistryTokenRequiresATenantAndUsesCanonicalAuthenticationError(): void
	{
		$pair = (new MobileAuth())->create(self::$registry, 'registry', null, 'assist-test');
		$this->families[] = JWT::decode($pair['access_token'])['sid'];
		$r = $this->request('session', 'POST', null, $pair);
		self::assertSame(401, $r['status']);
		self::assertSame('UNAUTHENTICATED', $r['json']['data']['error']);
	}
}
