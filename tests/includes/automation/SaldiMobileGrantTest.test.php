<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/automation/SaldiMobileGrantTest.test.php --- ver 5.0.0 --- 2026.10.10
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
// 20261010 CDX/LH Expect the public mobile grant revocation code.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeDbTestCase.php';
require_once __DIR__ . '/../../../restapi/core/MobileAuth.php';
final class SaldiMobileGrantTest extends SaldiRuntimeDbTestCase
{
	private array $families = [];
	private array $registryIds = [];
	private function family(SaldiTenantContext $ctx): string
	{
		$family = MobileAuth::uuid();
		$this->env->master->insert('refresh_sessions', ['jti_hash' => hash('sha256', random_bytes(32)), 'family_id' => $family, 'subject' => 'runtime-test/' . ($ctx->userId < 0 ? 'registry/' : 'account/') . abs($ctx->userId), 'principal' => $ctx->userId < 0 ? 'registry' : 'account', 'tenant_id' => $ctx->regnskabId, 'device_id' => 'grant-test', 'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400), 'auth_version' => 0]);
		$this->families[] = $family;
		return $family;
	}
	protected function tearDown(): void
	{
		foreach ($this->families as $family) { $this->env->master->exec('DELETE FROM refresh_sessions WHERE family_id = ?', [$family]); }
		foreach ($this->registryIds as $id) { $this->env->master->exec('DELETE FROM brugere WHERE id = ?', [$id]); }
		parent::tearDown();
	}
	private function verify(string $token): array
	{
		$open = saldi_runtime_context_from_token($token); $open['db']->close(); return $open;
	}
	public function testLiveMobileGrantReadsCurrentRightsAndDiesWithFamily(): void
	{
		$this->env->enable(); $ctx = $this->env->ctx(); $family = $this->family($ctx);
		$service = new SaldiGrantService($this->env->master, $this->env->db, $ctx);
		$issued = $service->issueMobile($family, bin2hex(random_bytes(16)));
		self::assertSame('mobile', $issued['grant']['kind']); self::assertSame($family, $issued['grant']['family_id']);
		self::assertSame('Saldi Assist (app)', $issued['grant']['label']); self::assertSame('read draft', $issued['grant']['scopes']);
		self::assertSame(2000, (int)$issued['grant']['max_uses']);
		$this->env->db->exec("UPDATE brugere SET rettigheder = '2222222222222222' WHERE id = 910001");
		$open = $this->verify($issued['token']); self::assertSame('2222222222222222', $open['ctx']->rights);
		foreach (['expires_at = now()', "expires_at = '2000-01-01'", 'revoked_at = now()'] as $state) {
			$this->env->master->exec("UPDATE refresh_sessions SET $state WHERE family_id = ?", [$family]);
			$e = $this->error('GRANT_REVOKED', fn() => $this->verify($issued['token']));
			self::assertSame('GRANT_REVOKED', $e->detail['reason']); self::assertSame(401, $e->httpStatus());
			$this->env->master->exec('UPDATE refresh_sessions SET expires_at = ?, revoked_at = NULL WHERE family_id = ?', [gmdate('Y-m-d H:i:s', time() + 86400), $family]);
		}
		$this->env->master->exec('DELETE FROM refresh_sessions WHERE family_id = ?', [$family]);
		$this->error('GRANT_REVOKED', fn() => $this->verify($issued['token']));
		self::assertSame(1, (int)$this->env->db->value('SELECT use_count FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
	}
	public function testIssuanceRequiresToolsAndMatchingFamilyAndBoundsAcrossSessions(): void
	{
		$ctx = $this->env->ctx(); $family = $this->family($ctx);
		$service = new SaldiGrantService($this->env->master, $this->env->db, $ctx);
		$this->error('DISABLED', fn() => $service->issueMobile($family, bin2hex(random_bytes(16))));
		$this->env->enable();
		$this->error('VALIDATION_ERROR', fn() => $service->issueMobile('bad', bin2hex(random_bytes(16))));
		$this->error('VALIDATION_ERROR', fn() => $service->issueMobile($family, 'bad'));
		$this->error('GRANT_REVOKED', fn() => $service->issueMobile(MobileAuth::uuid(), bin2hex(random_bytes(16))));
		$other = new SaldiGrantService($this->env->master, $this->env->db, $this->env->ctx(910002));
		$this->error('GRANT_REVOKED', fn() => $other->issueMobile($family, bin2hex(random_bytes(16))));
		$first = null;
		$firstToken = null;
		for ($i = 0; $i < 6; $i++) {
			$row = $service->issueMobile($family, bin2hex(random_bytes(16)));
			$first ??= $row['grant']['id'];
			$firstToken ??= $row['token'];
		}
		self::assertSame(5, (int)$this->env->db->value("SELECT COUNT(*) FROM agent_grants WHERE kind = 'mobile' AND revoked_at IS NULL"));
		self::assertNotNull($this->env->db->value('SELECT revoked_at FROM agent_grants WHERE id = ?', [$first]));
		self::assertSame('GRANT_REVOKED', $this->error('GRANT_REVOKED', fn() => $this->verify($firstToken))->detail['reason']);
	}
	public function testRevisorMembershipEnabledStateAndNegativePrivateSettings(): void
	{
		$this->env->enable();
		$id = $this->env->master->insert('brugere', ['brugernavn' => 'revisor-test-' . bin2hex(random_bytes(8)), 'kode' => password_hash(TestCredentials::password('revisor'), PASSWORD_DEFAULT), 'rettigheder' => ',,,' . $this->env->regnskab['id'], 'status' => true]);
		$this->registryIds[] = $id;
		$ctx = saldi_runtime_context_for_revisor($id, $this->env->name);
		self::assertSame(-$id, $ctx->userId); self::assertTrue($ctx->isHuman()); self::assertSame('revisor:' . $id, $ctx->username);
		$family = $this->family($ctx); $service = new SaldiGrantService($this->env->master, $this->env->db, $ctx);
		$issued = $service->issueMobile($family, bin2hex(random_bytes(16)));
		self::assertSame(-$id, (int)$issued['grant']['user_id']); self::assertSame(-$id, $this->verify($issued['token'])['ctx']->onBehalfOf());
		self::assertSame('', SaldiSettings::getUser($this->env->db, $ctx, 'assistant', 'improvement_consent', ''));
		foreach (['yes', 'no'] as $value) { SaldiSettings::setUser($this->env->db, $ctx, 'assistant', 'improvement_consent', $value); self::assertSame($value, SaldiSettings::getUser($this->env->db, $ctx, 'assistant', 'improvement_consent')); }
		$this->error('VALIDATION_ERROR', fn() => SaldiSettings::setUser($this->env->db, $ctx, 'assistant', 'improvement_consent', 'on'));
		self::assertNull($this->env->db->value("SELECT pos_id FROM settings WHERE var_name = 'improvement_consent' AND user_id = ?", [-$id]));
		self::assertSame('', SaldiSettings::getUser($this->env->db, $this->env->ctx(), 'assistant', 'improvement_consent', ''));
		$this->env->master->exec("UPDATE brugere SET rettigheder = ',,,' WHERE id = ?", [$id]);
		$this->error('PERMISSION_DENIED', fn() => saldi_runtime_context_for_revisor($id, $this->env->name));
		$this->error('PERMISSION_DENIED', fn() => $this->verify($issued['token']));
		$this->env->master->exec("UPDATE brugere SET rettigheder = '1,,,', status = false WHERE id = ?", [$id]);
		$this->error('PERMISSION_DENIED', fn() => saldi_runtime_context_for_revisor($id, $this->env->name));
		$this->env->master->exec('UPDATE brugere SET status = true WHERE id = ?', [$id]);
		self::assertTrue(saldi_runtime_context_for_revisor($id, $this->env->name)->isHuman());
	}
}
