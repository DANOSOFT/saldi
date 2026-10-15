<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/AssistantInboxTest.php --- ver 5.0.0 --- 2026.10.10
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
// 20261010 CDX/LH Cover rollback and durable audit history for failed mobile approvals.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T3EventTestEnv.php';
require_once __DIR__ . '/../../includes/automation/SaldiInboxService.php';
require_once __DIR__ . '/../../includes/automation/approvalCard.php';

final class AssistantInboxTest extends TestCase
{
	private static SaldiDb $db;
	private SaldiTenantContext $ctx;
	private SaldiInboxService $inbox;
	private string $token;

	public static function setUpBeforeClass(): void
	{
		if (($reason = RestApiEnv::unavailableReason()) !== null) {
			self::markTestSkipped($reason);
		}
		T3EventTestEnv::setup();
		self::$db = SaldiDb::connect(saldi_runtime_config(), RestApiEnv::testDb());
		saldi_runtime_ensure_schema(self::$db);
	}
	public static function tearDownAfterClass(): void
	{
		if (isset(self::$db)) {
			self::$db->close();
			T3EventTestEnv::teardown();
		}
	}
	protected function setUp(): void
	{
		T3EventTestEnv::reset();
		foreach (['decision_log', 'inbox_items', 'assistant_memory'] as $table) {
			self::$db->exec('DELETE FROM ' . $table);
		}
		$this->ctx = saldi_runtime_context_for_user(self::$db, ['id' => T3EventTestEnv::$tenantId], (int)T3EventTestEnv::$account['id'], 'user:' . T3EventTestEnv::$account['id']);
		$this->inbox = new SaldiInboxService(self::$db, SaldiKindRegistry::default());
		$this->token = T3EventTestEnv::token();
	}
	/** @return array{status:int,json:?array,body:string} */
	private function request(string $endpoint, string $method = 'GET', ?array $body = null, array $query = [], ?string $token = null): array
	{
		return RestApiEnv::http($method, '/restapi/endpoints/v1/assistant/' . $endpoint . '.php?' . http_build_query($query), $body,
			['Authorization: Bearer ' . ($token ?? $this->token)]);
	}
	/** @return array */
	private function task(string $attention = 'ambient'): array
	{
		return $this->inbox->create($this->ctx, 'followup.task', ['title' => 'Se på opgaven', 'details' => 'Læs oplysningerne'], ['attention' => $attention]);
	}
	/** @return array */
	private function memory(): array
	{
		return $this->inbox->create($this->ctx, 'memory.fact', ['scope' => 'company', 'fact' => 'Vi ser på rapporterne hver måned']);
	}
	/** @return array */
	private function decision(array $item, string $op = 'approve', ?string $clientId = null): array
	{
		return ['id' => $item['id'], 'digest' => $item['digest'], 'tenant_hash' => $this->ctx->tenantHash(), 'op' => $op, 'client_id' => $clientId ?? MobileAuth::uuid()];
	}
	private function conflict(array $response, string $reason): void
	{
		self::assertSame(409, $response['status'], $response['body']);
		self::assertSame('CONFLICT', $response['json']['data']['error']);
		self::assertSame($reason, $response['json']['data']['details']['reason']);
	}
	public function testTabsCountsDetailAndPaginationKeepVisibilityAndServerRendering(): void
	{
		$normal = $this->task();
		$this->task('silent');
		$interrupt = $this->task('interrupt');
		$closed = $this->task();
		$this->inbox->decide($this->ctx, $closed['id'], 'reject', $closed['digest'], $this->ctx->tenantHash());
		$hidden = $this->task();
		self::$db->exec('UPDATE inbox_items SET owner_user_id = ? WHERE id = ?', [999999, $hidden['id']]);
		foreach (['open' => 2, 'silent' => 1, 'decided' => 1] as $tab => $total) {
			$r = $this->request('inbox', 'GET', null, ['tab' => $tab, 'limit' => 1]);
			self::assertSame(200, $r['status'], $r['body']);
			self::assertSame($total, $r['json']['data']['total']);
			self::assertSame(['open' => 2, 'silent' => 1, 'decided' => 1], $r['json']['data']['counts']);
			self::assertCount(1, $r['json']['data']['items']);
			self::assertArrayNotHasKey('payload', $r['json']['data']['items'][0]);
		}
		self::assertSame($normal['id'], $this->request('inbox', 'GET', null, ['offset' => 1])['json']['data']['items'][0]['id']);
		self::assertSame(0, $this->request('inbox', 'GET', null, ['kind' => 'memory.fact'])['json']['data']['total']);
		self::assertSame(['open' => 2, 'interrupt' => 1], $this->request('count')['json']['data']);
		$r = $this->request('item', 'GET', null, ['id' => $normal['id']]);
		self::assertSame(200, $r['status'], $r['body']);
		self::assertSame('normal', $r['json']['data']['attention']);
		self::assertSame($normal['digest'], $r['json']['data']['digest']);
		self::assertSame($this->ctx->tenantHash(), $r['json']['data']['tenant_hash']);
		self::assertSame('Læs oplysningerne', $r['json']['data']['payload_view'][0]['value']);
		self::assertSame(['approve', 'reject', 'snooze', 'dismiss'], $r['json']['data']['ops']);
		self::assertNull($r['json']['data']['undo_until']);
		self::assertArrayNotHasKey('payload', $r['json']['data']);
		self::assertSame(404, $this->request('item', 'GET', null, ['id' => $hidden['id']])['status']);
		self::assertSame('interrupt', $this->request('item', 'GET', null, ['id' => $interrupt['id']])['json']['data']['attention']);
	}
	public function testApproveRejectSnoozeDismissAndClientReplayReturnTheFirstOutcome(): void
	{
		foreach (['approve' => 'applied', 'reject' => 'rejected', 'snooze' => 'open', 'dismiss' => 'cancelled'] as $op => $status) {
			$item = $this->task();
			$body = $this->decision($item, $op);
			$r = $this->request('decide', 'POST', $body);
			self::assertSame(200, $r['status'], $r['body']);
			self::assertSame($status, $r['json']['data']['item']['status']);
			if ($op === 'snooze') {
				self::assertSame('silent', $r['json']['data']['item']['attention']);
				$this->request('decide', 'POST', $this->decision($item, 'dismiss'));
			}
			$body['digest'] = str_repeat('0', 64);
			$replay = $this->request('decide', 'POST', $body);
			self::assertSame(200, $replay['status'], $replay['body']);
			self::assertSame($r['json']['data'], $replay['json']['data']);
		}
		self::assertSame(1, (int)self::$db->value("SELECT COUNT(*) FROM decision_log WHERE event = 'applied'"));
	}

	public function testFailedMobileApprovalKeepsItemOpenAndPersistsEachAuditEntry(): void
	{
		$registry = SaldiKindRegistry::default();
		$registry->register(new class extends SaldiBaseInboxKind {
			public function name(): string { return 'followup.task'; }
			public function actionClass(): string { return 'read_only'; }
			public function proposePermission(): string { return 'followup.draft'; }
			public function decidePermission(array $item): string { return 'inbox.read'; }
			public function validate(SaldiTenantContext $ctx, SaldiDb $db, array $input): array
			{
				return $this->normalized($ctx, ['title' => 'Se på opgaven'], 'Se på opgaven');
			}
			public function apply(SaldiTenantContext $ctx, SaldiDb $db, array $item): array
			{
				$db->insert('settings', ['var_name' => 'mobile_rolled_back_marker']);
				throw new SaldiRuntimeError('CONFLICT', 'Journal in use', ['reason' => 'journal_in_use']);
			}
		});
		$inbox = new SaldiInboxService(self::$db, $registry);
		$item = $inbox->create($this->ctx, 'followup.task', []);
		$clientId = MobileAuth::uuid();
		for ($attempt = 1; $attempt <= 2; $attempt++) {
			try {
				$inbox->mobileDecide($this->ctx, $item['id'], 'approve', $item['digest'], $this->ctx->tenantHash(), $clientId);
				self::fail('Approval should fail');
			} catch (SaldiRuntimeError $error) {
				self::assertSame('CONFLICT', $error->errorCode);
				self::assertSame('journal_in_use', $error->detail['reason']);
			}
			self::assertSame('open', $inbox->get($this->ctx, $item['id'])['status']);
			self::assertNull(self::$db->value("SELECT id FROM settings WHERE var_name = 'mobile_rolled_back_marker'"));
			$logs = self::$db->all("SELECT detail, idempotency_key FROM decision_log WHERE inbox_item_id = ? AND event = 'apply_failed' ORDER BY id", [$item['id']]);
			self::assertCount($attempt, $logs);
			self::assertSame('CONFLICT', json_decode($logs[$attempt - 1]['detail'], true)['error_code']);
		}
		self::assertNotSame($logs[0]['idempotency_key'], $logs[1]['idempotency_key']);
		self::assertSame(0, (int)self::$db->value("SELECT COUNT(*) FROM decision_log WHERE event IN ('applied', 'revised')"));
		$this->conflict($this->request('decide', 'POST', array_replace($this->decision($item), ['digest' => str_repeat('0', 64)])), 'digest');
		self::assertCount(2, self::$db->all("SELECT id FROM decision_log WHERE event = 'apply_failed'"));
	}
	public function testEveryDecisionConflictAndInvalidClientIdAreStable(): void
	{
		$item = $this->task();
		$body = $this->decision($item);
		$this->conflict($this->request('decide', 'POST', array_replace($body, ['digest' => str_repeat('0', 64)])), 'digest');
		$this->conflict($this->request('decide', 'POST', array_replace($body, ['tenant_hash' => str_repeat('0', 32)])), 'tenant_changed');
		self::assertSame(422, $this->request('decide', 'POST', array_replace($body, ['client_id' => '']))['status']);
		self::assertSame(200, $this->request('decide', 'POST', $body)['status']);
		$this->conflict($this->request('decide', 'POST', $this->decision($item)), 'already_decided');
		$this->conflict($this->request('decide', 'POST', array_replace($body, ['tenant_hash' => str_repeat('0', 32)])), 'tenant_changed');
	}
	public function testUndoInsideOutsideWindowAndAfterChangeChecksBothBindings(): void
	{
		$item = $this->memory();
		$approved = $this->request('decide', 'POST', $this->decision($item));
		self::assertSame(200, $approved['status'], $approved['body']);
		self::assertNotNull($approved['json']['data']['item']['undo_until']);
		$bound = array_intersect_key($this->decision($item), array_flip(['id', 'digest', 'tenant_hash']));
		$this->conflict($this->request('undo', 'POST', array_replace($bound, ['digest' => str_repeat('0', 64)])), 'digest');
		$this->conflict($this->request('undo', 'POST', array_replace($bound, ['tenant_hash' => str_repeat('0', 32)])), 'tenant_changed');
		self::assertSame('undone', $this->request('undo', 'POST', $bound)['json']['data']['item']['status']);
		self::assertNotNull(self::$db->value('SELECT retracted_at FROM assistant_memory WHERE id = ?', [$approved['json']['data']['result']['memory_id']]));
		$this->conflict($this->request('undo', 'POST', $bound), 'not_undoable');
		$item = $this->memory();
		self::assertSame(200, $this->request('decide', 'POST', $this->decision($item))['status']);
		self::$db->exec('UPDATE inbox_items SET decided_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 301), $item['id']]);
		self::assertNull($this->request('item', 'GET', null, ['id' => $item['id']])['json']['data']['undo_until']);
		$this->conflict($this->request('undo', 'POST', array_intersect_key($this->decision($item), array_flip(['id', 'digest', 'tenant_hash']))), 'not_undoable');
		$item = $this->task();
		$this->request('decide', 'POST', $this->decision($item));
		$this->conflict($this->request('undo', 'POST', array_intersect_key($this->decision($item), array_flip(['id', 'digest', 'tenant_hash']))), 'not_undoable');
	}
	public function testRightsReadOnlyDetailsAndRevisorLabels(): void
	{
		$item = $this->memory();
		self::$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', ['0010000000000000', $this->ctx->userId]);
		$r = $this->request('item', 'GET', null, ['id' => $item['id']]);
		self::assertSame(200, $r['status'], $r['body']);
		self::assertFalse($r['json']['data']['can_decide']);
		self::assertSame([], $r['json']['data']['ops']);
		$r = $this->request('decide', 'POST', $this->decision($item));
		self::assertSame(403, $r['status'], $r['body']);
		self::assertSame('PERMISSION_DENIED', $r['json']['data']['error']);
		self::$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', [str_repeat('0', 16), $this->ctx->userId]);
		foreach (['inbox', 'item', 'count', 'decide', 'undo'] as $endpoint) {
			$r = $this->request($endpoint, in_array($endpoint, ['decide', 'undo'], true) ? 'POST' : 'GET', $this->decision($item), ['id' => $item['id']]);
			self::assertSame(403, $r['status'], $r['body']);
			self::assertSame('FORBIDDEN', $r['json']['data']['error']);
		}
		$r = $this->request('decide', 'POST', $this->decision($item), [], T3EventTestEnv::token(true));
		self::assertSame(200, $r['status'], $r['body']);
		self::assertSame('Revisor', $r['json']['data']['item']['decided_by_label']);
		self::assertSame(-T3EventTestEnv::$registryUser['id'], (int)self::$db->value('SELECT decided_by FROM inbox_items WHERE id = ?', [$item['id']]));
		$webItem = $this->inbox->get($this->ctx, $item['id']);
		self::assertStringContainsString('Revisor', saldi_approval_card_html($webItem, ['db' => self::$db]));
	}
	public function testInterruptIsOncePrivateAndPreferenceOffSuppressesPush(): void
	{
		self::$db->exec("INSERT INTO push_tokens(user_id,subject,principal,device_id,db_name,token,platform) VALUES(?,?,'account','assist-phone',?,?,'ios')",
			[$this->ctx->userId, T3EventTestEnv::$subject, RestApiEnv::testDb(), 'ExpoPushToken[' . bin2hex(random_bytes(16)) . ']']);
		$item = $this->task('interrupt');
		self::assertSame(1, (int)self::$db->value("SELECT COUNT(*) FROM notifications WHERE type = 'inbox_interrupt'"));
		self::assertSame(1, (int)self::$db->value('SELECT COUNT(*) FROM notification_recipients'));
		self::assertSame(1, (int)self::$db->value('SELECT COUNT(*) FROM push_dispatch'));
		$notice = self::$db->one('SELECT * FROM notifications');
		self::assertSame('assistant', $notice['created_by']);
		self::assertSame('Saldi Assist har et nyt fund', $notice['title']);
		self::assertSame('Åbn Opgaver for at se det.', $notice['message']);
		self::assertSame('inbox_item', $notice['ref_type']);
		self::assertSame($item['id'], (int)$notice['ref_id']);
		SaldiInterruptNotifier::notify(self::$db, $this->ctx, self::$db->one('SELECT * FROM inbox_items WHERE id = ?', [$item['id']]));
		SaldiInterruptNotifier::flush(self::$db, $this->ctx, $item['id']);
		self::assertSame(1, (int)self::$db->value('SELECT COUNT(*) FROM notifications'));
		$r = RestApiEnv::http('GET', '/restapi/endpoints/v1/notifications/preferences.php', null, ['Authorization: Bearer ' . $this->token]);
		self::assertSame(200, $r['status'], $r['body']);
		$preference = array_column($r['json']['data']['items'], null, 'type')['assistant_interrupt'];
		self::assertSame(['type' => 'assistant_interrupt', 'enabled' => true, 'label' => 'Forslag og fund fra Saldi Assist'], $preference);
		$r = RestApiEnv::http('PUT', '/restapi/endpoints/v1/notifications/preferences.php', ['client_id' => MobileAuth::uuid(), 'items' => [['type' => 'assistant_interrupt', 'enabled' => false]]], ['Authorization: Bearer ' . $this->token]);
		self::assertSame(200, $r['status'], $r['body']);
		$this->task('interrupt');
		self::assertSame(1, (int)self::$db->value('SELECT COUNT(*) FROM notifications'));
		self::assertSame(1, (int)self::$db->value('SELECT COUNT(*) FROM push_dispatch'));
	}
	public function testRolledBackInterruptAndMissingMobileTablesAreHarmless(): void
	{
		try {
			self::$db->transaction(function (): void {
				$this->task('interrupt');
				self::assertSame(0, (int)self::$db->value('SELECT COUNT(*) FROM notifications'));
				throw new RuntimeException('Roll back the fixture');
			});
		} catch (RuntimeException $error) {
			self::assertSame('Roll back the fixture', $error->getMessage());
		}
		self::assertSame(0, (int)self::$db->value('SELECT COUNT(*) FROM mobile_events'));
		self::assertSame(0, (int)self::$db->value('SELECT COUNT(*) FROM inbox_items'));
		self::$db->exec('ALTER TABLE push_dispatch RENAME TO assist_hidden_dispatch');
		try {
			$item = $this->task('interrupt');
			self::assertSame('open', $item['status']);
			self::assertSame(0, (int)self::$db->value('SELECT COUNT(*) FROM notifications'));
		} finally {
			self::$db->exec('ALTER TABLE assist_hidden_dispatch RENAME TO push_dispatch');
		}
	}
	public function testExpiredItemsCommitExpiryAndReplaySurvivesUndo(): void
	{
		foreach (['approve', 'snooze', 'dismiss'] as $op) {
			$item = $this->inbox->create($this->ctx, 'followup.task', ['title' => 'En udløbet opgave'], ['expires_at' => '2000-01-01 00:00:00']);
			$this->conflict($this->request('decide', 'POST', $this->decision($item, $op)), 'already_decided');
			self::assertSame('expired', self::$db->value('SELECT status FROM inbox_items WHERE id = ?', [$item['id']]));
		}
		$item = $this->memory();
		$body = $this->decision($item);
		$first = $this->request('decide', 'POST', $body);
		self::assertSame(200, $first['status'], $first['body']);
		$undo = $this->request('undo', 'POST', array_intersect_key($body, array_flip(['id', 'digest', 'tenant_hash'])));
		self::assertSame(200, $undo['status'], $undo['body']);
		$replay = $this->request('decide', 'POST', $body);
		self::assertSame(200, $replay['status'], $replay['body']);
		self::assertSame($first['json']['data'], $replay['json']['data']);
		self::assertSame('undone', self::$db->value('SELECT status FROM inbox_items WHERE id = ?', [$item['id']]));
	}
	public function testFindingRowsAndInterruptRecipientsFollowCurrentReadPermissions(): void
	{
		$rule = SaldiTenantContext::forSystem($this->ctx->installId, $this->ctx->regnskabId, $this->ctx->db, $this->ctx->dbType, 'rule:low_stock', $this->ctx->fiscalYear, 'DKK');
		self::$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', ['0010000000000000', $this->ctx->userId]);
		$item = $this->inbox->create($rule, 'routine.finding', ['routine' => 'low_stock', 'scheduled_date' => gmdate('Y-m-d'), 'count' => 1,
			'rows' => [['label' => 'En vare', 'amount' => '42.00', 'currency' => 'DKK']]], ['attention' => 'interrupt']);
		self::assertSame(0, (int)self::$db->value('SELECT COUNT(*) FROM notifications'));
		self::assertSame(404, $this->request('item', 'GET', null, ['id' => $item['id']])['status']);
		self::$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', ['0000000002000000', $this->ctx->userId]);
		$r = $this->request('item', 'GET', null, ['id' => $item['id']]);
		self::assertSame(200, $r['status'], $r['body']);
		self::assertCount(1, $r['json']['data']['rows']);
		self::assertContains('En vare', $r['json']['data']['rows'][0]);
		self::assertContains('42,00 DKK', $r['json']['data']['rows'][0]);
		self::assertSame(200, $this->request('decide', 'POST', $this->decision($item, 'dismiss'))['status']);
	}
	public function testMissingKindModuleMakesOwnedItemsReadOnlyAndRefusesDecisions(): void
	{
		$item = $this->task();
		self::$db->exec('UPDATE inbox_items SET kind = ?, payload = ? WHERE id = ?', ['journal.draft_entry', saldi_runtime_json(['kladde_id' => null, 'lines' => []]), $item['id']]);
		self::$db->exec('UPDATE brugere SET rettigheder = ? WHERE id = ?', ['1000000000000000', $this->ctx->userId]);
		$r = $this->request('item', 'GET', null, ['id' => $item['id']]);
		self::assertSame(200, $r['status'], $r['body']);
		self::assertFalse($r['json']['data']['can_decide']);
		$r = $this->request('decide', 'POST', $this->decision($item, 'reject'));
		self::assertSame(403, $r['status'], $r['body']);
		self::assertSame('PERMISSION_DENIED', $r['json']['data']['error']);
		self::assertSame('open', self::$db->value('SELECT status FROM inbox_items WHERE id = ?', [$item['id']]));
	}
	public function testRevisorOwnedTasksRemainPrivateAndStoreTheNegativeActor(): void
	{
		$revisor = saldi_runtime_context_for_revisor((int)T3EventTestEnv::$registryUser['id'], self::$db->name());
		$item = $this->inbox->create($revisor, 'followup.task', ['title' => 'Revisor ser på opgaven'], ['attention' => 'interrupt']);
		self::assertSame($revisor->userId, $item['owner_user_id']);
		self::assertSame(0, (int)self::$db->value('SELECT COUNT(*) FROM notifications'));
		self::assertSame(404, $this->request('item', 'GET', null, ['id' => $item['id']])['status']);
		$token = T3EventTestEnv::token(true);
		self::assertSame(200, $this->request('item', 'GET', null, ['id' => $item['id']], $token)['status']);
		$r = $this->request('decide', 'POST', $this->decision($item), [], $token);
		self::assertSame(200, $r['status'], $r['body']);
		self::assertSame('Revisor', $r['json']['data']['item']['decided_by_label']);
		$cancel = $this->inbox->create($revisor, 'followup.task', ['title' => 'Revisor trækker opgaven tilbage']);
		self::assertSame('cancelled', $this->inbox->cancel($revisor, $cancel['id'])['status']);
	}
	public function testInputValidationAuthenticationAndUnsupportedMethods(): void
	{
		foreach ([['tab' => 'wrong'], ['limit' => 0], ['limit' => 101], ['offset' => -1], ['kind' => 'unknown']] as $query) {
			$r = $this->request('inbox', 'GET', null, $query);
			self::assertSame(422, $r['status'], $r['body']);
		}
		self::assertSame(422, $this->request('item', 'GET', null, ['id' => '1 OR 1=1'])['status']);
		self::assertSame(422, $this->request('decide', 'POST', [])['status']);
		foreach (['inbox', 'item', 'count', 'decide', 'undo'] as $endpoint) {
			$r = $this->request($endpoint, 'GET', null, [], '');
			self::assertSame(401, $r['status'], $r['body']);
			self::assertSame('UNAUTHENTICATED', $r['json']['data']['error']);
		}
		self::assertSame(405, $this->request('count', 'POST', [])['status']);
	}
}
