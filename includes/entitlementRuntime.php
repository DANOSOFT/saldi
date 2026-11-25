<?php
// --- includes/entitlementRuntime.php --- ver 5.1.1 --- 2026.11.25 ---
// LICENSE: GNU GPL v2 or later. Copyright (c) 2026 Danosoft ApS
// 20261125 CDX/ASR Observe package access independently of role enforcement; no package keeps legacy access.

/** @return bool The update chain has completed the catalogue migration on this master. */
function ent_runtime_ready(): bool
{
	$master = (string) ($GLOBALS['sqdb'] ?? '');
	if (isset($GLOBALS['ent_ready'][$master])) return $GLOBALS['ent_ready'][$master];
	$row = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_grp = 'system' AND var_name = 'entitlement_policy'", __FILE__ . ' linje ' . __LINE__, true));
	return $GLOBALS['ent_ready'][$master] = ($row && $row['var_value'] === ent_policy()['revision']);
}

/** @return void Load using the authenticated account id, while leaving the active tenant connection alone. */
function ent_load(int $regnskabId): void
{
	$GLOBALS['ent_runtime_id'] = $regnskabId;
	$GLOBALS['ent_runtime_state'] = $regnskabId > 0 && ent_runtime_ready() ? ent_resolve($regnskabId) : array();
}

/** @return void Resolve an authenticated database name on the master (API and login have no db_id yet). */
function ent_load_database(string $database): void
{
	$row = db_fetch_array(db_select("SELECT id FROM regnskab WHERE db = '" . db_escape_string($database) . "'", __FILE__ . ' linje ' . __LINE__, true));
	ent_load($row ? (int) $row['id'] : 0);
	$GLOBALS['ent_runtime_database'] = $database;
}

/** @return bool Package access for a background integration using its already selected tenant database. */
function ent_integration_allowed(string $database, string $feature): bool
{
	if (($GLOBALS['ent_runtime_database'] ?? null) !== $database) ent_load_database($database);
	return ent_check($feature);
}

/** @return string|null Admission lock held from the concurrent-user count through the session insert. */
function ent_login_lock(): ?string
{
	if (!ent_enforcing() || ent_limit('max_users') === null) return null;
	$name = 'entitlement_login_' . (int) $GLOBALS['ent_runtime_id'];
	if (!db_lock($name, 30, true)) ent_upgrade('max_users');
	return $name;
}

/** @return int Distinct active logins on the master, excluding auditors and an optional reconnecting user. */
function ent_concurrent_users(string $database, string $exclude = ''): int
{
	$db = db_escape_string($database);
	$user = db_escape_string($exclude);
	$since = time() - 14400; // Same four-hour activity window as the existing login flow.
	$auditor = db_is_mysql() ? '(revisor IS NULL OR revisor = 0)' : 'revisor IS NOT true';
	$row = db_fetch_array(db_select("SELECT count(DISTINCT lower(brugernavn)) AS n FROM online WHERE db = '$db' AND $auditor AND logtime >= '$since' AND lower(brugernavn) <> lower('$user')", __FILE__ . ' linje ' . __LINE__, true));
	return (int) ($row['n'] ?? 0);
}

/** @return array Resolved state for the authenticated tenant. Never accepts an id from the request. */
function ent_current(): array
{
	if (!isset($GLOBALS['ent_runtime_state'])) {
		ent_load((int) ($GLOBALS['ent_runtime_id'] ?? $GLOBALS['db_id'] ?? 0));
	}
	return $GLOBALS['ent_runtime_state'];
}

/** @return bool True when this account has a package assignment. */
function ent_has_plan(): bool { return !empty(ent_current()['plan_key']); }

/** @return bool Effective catalogue allowance, even while enforcement is only observing. */
function ent_allows(string $feature): bool { return ent_policy_allows(ent_current(), $feature); }

/** @return int|null The account's resolved limit; null means unlimited (including legacy accounts without a package). */
function ent_limit(string $name): ?int
{
	$state = ent_current();
	if (empty($state['plan_key']) || !isset($state['limits'][$name])) return null;
	return (int) $state['limits'][$name];
}

/** @return bool Package mode is independent of permissions/enforce in the customer database. */
function ent_enforcing(): bool
{
	return ent_has_plan() && (ent_current()['row']['enforcement'] ?? 'observe') === 'enforce';
}

/**
 * Log once per account, feature, action and day in a browser session (once per API request).
 * The server log is always available, including login before a tenant audit_log is connected.
 *
 * @return void
 */
function ent_observe(string $event, string $key, array $details = array()): void
{
	$state = ent_current();
	$record = array('event' => $event, 'regnskab_id' => (int) ($GLOBALS['ent_runtime_id'] ?? 0),
		'plan' => $state['plan_key'] ?? null, 'key' => $key, 'mode' => ent_enforcing() ? 'enforce' : 'observe');
	$record['script'] = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
	$record['method'] = (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI');
	$record['details'] = $details;
	$slot = 'ent_' . hash('sha256', date('Y-m-d') . '|' . $record['regnskab_id'] . '|' . $event . '|' . $key . '|' . $record['script'] . '|' . $record['method']);
	if (isset($GLOBALS['ent_logged'][$slot]) || isset($_SESSION[$slot])) return;
	$GLOBALS['ent_logged'][$slot] = true;
	if (session_status() === PHP_SESSION_ACTIVE) $_SESSION[$slot] = true;
	error_log('[entitlement] ' . json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** @return bool True in observe mode; otherwise the actual package allowance. */
function ent_check(string $feature, array $details = array()): bool
{
	if (ent_allows($feature)) return true;
	$enforce = ent_enforcing();
	ent_observe($enforce ? 'entitlement.denied' : 'entitlement.would_deny', ent_feature_key($feature), $details);
	return !$enforce;
}

/**
 * Check creation against a live count. Reads/exports pass adding=0 and are never limited.
 * Posting volume is measured and logged, but never stops bookkeeping, tax or year-end work.
 *
 * @return bool
 */
function ent_check_limit(string $name, int $current, int $adding = 1): bool
{
	$limit = ent_limit($name);
	$total = $name === 'included_postings' ? $current : $current + $adding;
	if ($limit === null || $adding <= 0 || $total <= $limit) return true;
	$enforce = ent_enforcing() && $name !== 'included_postings';
	ent_observe($enforce ? 'entitlement.denied' : 'entitlement.would_deny', 'limit.' . $name,
		array('current' => $current, 'adding' => $adding, 'limit' => $limit));
	return !$enforce;
}

/** @return string Effective DIV/3 box4 value, without rewriting a customer's stored setting during observation. */
function ent_quick_invoice(string $stored): string
{
	if (!ent_has_plan() || !ent_allows('sales.quick_invoice_required') || $stored === 'on') return $stored;
	ent_observe(ent_enforcing() ? 'entitlement.forced' : 'entitlement.would_force', 'sales.quick_invoice_required');
	return ent_enforcing() ? 'on' : $stored;
}

/** @return bool Whether a customer may change an entitlement-controlled setting (legacy accounts keep their behavior). */
function ent_setting_change_allowed(string $key, $value): bool
{
	if (!ent_has_plan()) return true;
	$feature = null;
	if (strpos($key, 'integrations.webshop.') === 0) $feature = 'shop.integration';
	if (strpos($key, 'sales.discounts.') === 0 || $key === 'items.item_groups.price_groups') $feature = 'salg.prisgrupper';
	if ($feature !== null && !ent_check($feature)) return false;
	$restricted = $key === 'max_users' || ($key === 'sales.orders.quick_invoice' && !$value && ent_allows('sales.quick_invoice_required'));
	if (!$restricted) return true;
	ent_observe(ent_enforcing() ? 'entitlement.denied' : 'entitlement.would_deny', 'setting.' . $key);
	return !ent_enforcing();
}

/** @return void End a refused action with an upgrade message, without executing the page's mutations. */
function ent_upgrade(string $feature): void
{
	$lang = (int) ($GLOBALS['sprog_id'] ?? 1);
	$title = function_exists('findtekst') ? findtekst('entitlement.upgrade|Opgradér', $lang) : 'Opgradér';
	$message = function_exists('findtekst') ? findtekst('entitlement.upgrade_message|Denne funktion er ikke med i jeres pakke, eller pakkens grænse er nået. Kontakt Saldi for at ændre aftalen.', $lang) : 'Denne funktion er ikke med i jeres pakke, eller pakkens grænse er nået. Kontakt Saldi for at ændre aftalen.';
	$json = function_exists('perm_request_wants_json') && perm_request_wants_json();
	if ($json) {
		if (!headers_sent()) {
			http_response_code(403);
			header('Content-Type: application/json; charset=UTF-8');
		}
		echo json_encode(array('success' => false, 'error' => 'upgrade_required', 'message' => $message, 'feature' => $feature), JSON_UNESCAPED_UNICODE);
	} else {
		$escape = static function (string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); };
		$home = function_exists('perm_home_url') ? perm_home_url() : '../index/main.php';
		echo '<section role="status" style="max-width:36rem;margin:3rem auto;padding:2rem;font-family:system-ui">';
		echo '<h1>' . $escape($title) . '</h1><p>' . $escape($message) . '</p>';
		echo '<p><a href="https://saldi.dk/priser/" target="_blank" rel="noopener">' . $escape($title) . '</a></p>';
		echo '<a href="' . $escape($home) . '">Saldi</a></section>';
	}
	exit;
}
