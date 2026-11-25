<?php
// 20261125 CDX/ASR Exercise package ceilings, observe mode, quotas, add-ons and immutable core access.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EntitlementPolicyTest extends TestCase
{
	private string $log;
	private string $previousLog;

	protected function setUp(): void
	{
		require_once __DIR__ . '/../../includes/entitlements.php';
		$this->previousLog = ini_get('error_log');
		$this->log = tempnam(sys_get_temp_dir(), 'saldi-ent-');
		ini_set('error_log', $this->log);
		$GLOBALS['ent_logged'] = array();
		$GLOBALS['ent_runtime_id'] = 17;
		$GLOBALS['ent_runtime_state'] = array();
	}

	protected function tearDown(): void
	{
		ini_set('error_log', $this->previousLog);
		unlink($this->log);
	}

	/** @return array Catalogue fixture in the same shape as the database reader. */
	private function catalog(): array
	{
		$data = ent_policy();
		$c = array('plans' => array(), 'features' => array(), 'addons' => array(), 'limits' => array());
		foreach ($data['plans'] as $key => $p) {
			$c['plans'][$key] = array('name' => $p[0], 'included_users' => $p[3], 'max_users' => $p[4],
				'included_postings' => $p[5], 'max_items' => $p[6], 'included_webshops' => $p[7],
				'price_ore' => $p[2], 'extra_user_price_ore' => $p[8], 'billing_interval' => 'month');
			$index = array_search($key, array_keys($data['plans']), true) + 3;
			foreach ($data['features'] as $f) {
				$c['features'][$key][$f[0]] = array('✓' => 'included', '✗' => 'none', 'B' => 'basic', 'A' => 'addon')[$f[$index]];
			}
			$c['limits'][$key] = array('max_webshops' => $data['limits'][$key]['max_webshops']);
		}
		foreach ($data['addons'] as $key => $a) $c['addons'][$key] = array('feature_key' => $a[1], 'available_on' => $a[4]);
		return $c;
	}

	/** @return array Resolved test account; installing it also exercises the public runtime API. */
	private function account(string $plan, string $mode = 'observe', array $addons = array(), array $overrides = array()): array
	{
		$rows = array_map(static fn($key) => array('addon_key' => $key, 'quantity' => 1, 'source' => 'manual'), $addons);
		return $GLOBALS['ent_runtime_state'] = ent_resolve_policy(array('plan_key' => $plan, 'enforcement' => $mode), $this->catalog(), $rows, $overrides);
	}

	public function testUnassignedAccountsKeepAllFeaturesAndUnlimitedPackageLimits(): void
	{
		self::assertTrue(ent_allows('pos.kasse'));
		self::assertTrue(ent_allows('not_in_catalog'));
		self::assertNull(ent_limit('max_users'));
		self::assertSame('', ent_quick_invoice(''));
		self::assertTrue(ent_setting_change_allowed('max_users', 100));
	}

	public static function packageLimits(): array
	{
		return array(['finans', 1, 500, 0, 0], ['professionel', 6, 5000, 10000, 1],
			['business', 12, 25000, null, 1], ['erp', null, null, null, null]);
	}

	#[DataProvider('packageLimits')]
	public function testLimitsKeepZeroAndUnlimitedDistinct(string $plan, ?int $users, ?int $postings, ?int $items, ?int $shops): void
	{
		$this->account($plan);
		self::assertSame($users, ent_limit('max_users'));
		self::assertSame($postings, ent_limit('included_postings'));
		self::assertSame($items, ent_limit('max_items'));
		self::assertSame($shops, ent_limit('max_webshops'));
	}

	public function testObservationLogsWithoutChangingAccessOrQuickInvoicing(): void
	{
		$this->account('finans');
		self::assertFalse(ent_allows('finans.budget'));
		self::assertTrue(ent_check('finans.budget'));
		self::assertTrue(ent_check('finans.budget'));
		self::assertSame('', ent_quick_invoice(''));
		self::assertTrue(ent_setting_change_allowed('max_users', 50));
		$log = file_get_contents($this->log);
		self::assertSame(1, substr_count($log, '"key":"finans.budget"'));
		self::assertStringContainsString('entitlement.would_deny', $log);
	}

	public function testEnforcementRefusesPaidFeaturesAndForcesQuickInvoicing(): void
	{
		$this->account('finans', 'enforce');
		self::assertFalse(ent_check('finans.budget'));
		self::assertSame('on', ent_quick_invoice(''));
		self::assertFalse(ent_setting_change_allowed('sales.orders.quick_invoice', false));
		self::assertTrue(ent_setting_change_allowed('sales.orders.quick_invoice', true));
		self::assertFalse(ent_setting_change_allowed('max_users', 50));
	}

	public function testCoreSurvivesOverridesAndQuotaExhaustion(): void
	{
		$overrides = array_map(static fn($key) => array('kind' => 'feature', 'key' => $key, 'value' => 'off'), ent_policy()['protected']);
		$this->account('finans', 'enforce', array(), $overrides);
		foreach (ent_policy()['protected'] as $feature) self::assertTrue(ent_allows($feature), $feature);
		self::assertTrue(ent_check_limit('included_postings', 100000));
		self::assertTrue(ent_check_limit('max_items', 100000, 0));
		self::assertFalse(ent_check_limit('max_items', 0));
		self::assertTrue(ent_check_limit('max_users', 0));
		self::assertFalse(ent_check_limit('max_users', 1));
	}

	public function testHigherPlansIncludeProfessionalAndBusinessFeatures(): void
	{
		$this->account('professionel');
		foreach (array('lager.varer', 'kreditor.ordre', 'lager.varemodtagelse', 'finans.budget', 'debitor.peppol') as $feature) self::assertTrue(ent_allows($feature));
		self::assertFalse(ent_allows('lager.optaelling'));
		foreach (array('business', 'erp') as $plan) {
			$this->account($plan);
			self::assertTrue(ent_allows('lager.optaelling'));
			self::assertTrue(ent_allows('lager.serienumre'));
			self::assertSame('', ent_quick_invoice(''));
		}
	}

	public function testPosAndShopNeedAnAddonEvenOnErp(): void
	{
		foreach (array('professionel', 'business', 'erp') as $plan) {
			$this->account($plan);
			self::assertFalse(ent_allows('pos.kasse'));
			self::assertFalse(ent_allows('pos.multilocation'));
			self::assertFalse(ent_allows('shop.integration'));
			$this->account($plan, 'observe', array('pos_location', 'webshop_simple'));
			self::assertTrue(ent_allows('pos.kasse'));
			self::assertTrue(ent_allows('shop.integration'));
			self::assertSame($plan !== 'professionel', ent_allows('pos.multilocation'));
		}
	}

	public function testUnavailableAddonDoesNotGrantFinancePos(): void
	{
		$this->account('finans', 'observe', array('pos_location', 'webshop_simple'));
		self::assertFalse(ent_allows('pos.kasse'));
		self::assertFalse(ent_allows('shop.integration'));
	}

	public function testAdvancedShopIncludesTheBasicConnectionWithoutWideningItsLimit(): void
	{
		$this->account('business', 'observe', array('webshop_advanced'));
		self::assertTrue(ent_allows('shop.integration'));
		self::assertTrue(ent_allows('shop.advanced'));
		self::assertSame(1, ent_limit('max_webshops'));
	}

	public function testExplicitOverridesApplyAfterPlanAndAddon(): void
	{
		$this->account('finans', 'observe', array('pos_location'), array(
			array('kind' => 'feature', 'key' => 'pos', 'value' => 'off'),
			array('kind' => 'limit', 'key' => 'max_users', 'value' => '*'),
			array('kind' => 'plan', 'value' => 'business'),
		));
		self::assertFalse(ent_allows('pos.kasse'));
		self::assertTrue(ent_allows('lager.optaelling'));
		self::assertNull(ent_limit('max_users'));
	}

	public function testDatabaseCatalogueOverridesSeedData(): void
	{
		$c = $this->catalog();
		$c['plans']['professionel']['max_users'] = 8;
		$c['features']['professionel']['finans.budget'] = 'none';
		$GLOBALS['ent_runtime_state'] = ent_resolve_policy(array('plan_key' => 'professionel'), $c, array(), array());
		self::assertSame(8, ent_limit('max_users'));
		self::assertFalse(ent_allows('finans.budget'));
	}

	public function testCoreRoutesAndSharedOrderScreenAreNotBlanketGated(): void
	{
		foreach (array('/finans/bogfor.php', '/finans/moms.php', '/finans/rapport.php', '/debitor/ordre.php', '/debitor/rykkerprint.php', '/lager/rapport.php') as $path) self::assertNull(ent_feature_for_page($path));
		self::assertNull(ent_feature_for_permission('debitor.ordre'));
		self::assertNull(ent_feature_for_permission('kreditor.konti'));
		self::assertSame('finans.budget', ent_feature_for_page('/asr/finans/budget.php'));
		self::assertSame('lager.varer', ent_feature_for_permission('lager.varer'));
	}

	public function testDowngradeKeepsReadExportAndBookkeepingAccess(): void
	{
		$this->account('finans', 'enforce');
		foreach (array('lager.varer', 'kreditor.ordre', 'lager.varemodtagelse') as $key) {
			self::assertTrue(ent_permission_allowed($key, 'read'));
			self::assertFalse(ent_permission_allowed($key, 'write'));
		}
		self::assertTrue(ent_permission_allowed('kreditor.ordre', 'write', '/app/kreditor/bogfor.php'));
		self::assertFalse(ent_permission_allowed('pos.kasse', 'read'), 'Opening the till can create a sale.');
	}

	public function testSettingsFollowThePackageWithoutLockingCoreIntegrations(): void
	{
		$this->account('finans', 'enforce');
		self::assertFalse(ent_setting_change_allowed('integrations.webshop.url', 'https://example.test'));
		self::assertFalse(ent_setting_change_allowed('sales.discounts.matrix', ''));
		self::assertTrue(ent_setting_change_allowed('integrations.rest_api.key', ''));
		self::assertSame('salg.prisgrupper', ent_feature_for_settings('sales.debtor_groups'));
		self::assertNull(ent_feature_for_settings('finance.vat'));
	}

	public function testActualRequestGateStopsBeforeMutationAndObservesWithoutStopping(): void
	{
		foreach (array('enforce', 'budget', 'shop-import', 'observe', 'legacy', 'read', 'protected') as $scenario) {
			$process = proc_open(array(PHP_BINARY, __DIR__ . '/support/entitlementRequest.php', $scenario), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
			self::assertIsResource($process);
			$output = stream_get_contents($pipes[1]);
			$errors = stream_get_contents($pipes[2]);
			fclose($pipes[1]);
			fclose($pipes[2]);
			self::assertSame(0, proc_close($process), $errors);
			self::assertDoesNotMatchRegularExpression('/(?:Warning|Fatal error|Deprecated):/', $errors . $output);
			if (in_array($scenario, array('enforce', 'budget', 'shop-import'), true)) {
				self::assertStringContainsString('upgrade_required', $output);
				self::assertStringNotContainsString('CONTINUED', $output);
			} else {
				self::assertSame('CONTINUED', $output, $scenario);
			}
			if ($scenario === 'observe') self::assertStringContainsString('entitlement.would_deny', $errors);
		}
	}
}
