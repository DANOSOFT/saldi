<?php
// --- includes/crmPermissions.php --- ver 5.1.1 --- 2026.11.10 ---
// Copyright (c) 2026 Danosoft ApS
// 20261110 CDX/ASR Centralize strict CRM access, API read actions and live calendar-recipient checks.

require_once __DIR__ . '/permissions.php';

/** CRM grants are independent of debtor access and enforced in both rollout modes. */
function crm_can_read(): bool
{
	return perm_can('crm.access', 'read');
}

/** Whether the current user may change CRM data or calendar subscription tokens. */
function crm_can_write(): bool
{
	return perm_can('crm.access', 'write');
}

/**
 * Unknown actions fail closed. Token creation/revocation are writes even though the URL lookup only reads.
 * @param array<string, mixed> $input
 */
function crm_action_permission(string $action, array $input = array()): string
{
	if ($action === 'ical') {
		return in_array($input['mode'] ?? '', array('new', 'off'), true) ? 'write' : 'read';
	}
	return in_array($action, array('search', 'place', 'customer', 'data', 'busy'), true) ? 'read' : 'write';
}

/**
 * A CRM case link in the legacy sales-order GET flow needs the initiating CRM page's session token.
 * @param array<string, mixed> $input
 */
function crm_order_link_token_valid(array $input): bool
{
	$expected = $_SESSION['csrf_token'] ?? null;
	$provided = $input['crm_csrf'] ?? null;
	return is_string($expected) && $expected !== '' && is_string($provided) && hash_equals($expected, $provided);
}

/**
 * Live recipient check for sessionless feeds and notifications; null status is a legacy active account.
 * @param array{status?: mixed, rettigheder?: string} $user
 */
function crm_user_can_read(int $id, array $user): bool
{
	if ($id <= 0 || in_array($user['status'] ?? null, array(false, 'f', 0, '0'), true)) {
		return false;
	}
	$levels = perm_user_levels($id, (string) ($user['rettigheder'] ?? ''));
	return in_array($levels['crm.access'] ?? 'none', array('read', 'write'), true);
}
