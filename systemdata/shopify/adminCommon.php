<?php
// --- systemdata/shopify/adminCommon.php --- ver 5.1.0 --- 2026-11-02 ---
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
// Helpers of the Shopify admin pages (WP04), functions only: the Systemdata right (offset 1 of
// rettigheder: '1' may change, '2' may only read, anything else is refused), escaping and texts,
// the CSRF token shared with the other Systemdata forms, the message carried across a
// Post/Redirect/Get and the page header with the Shopify tabs.
//
// 20261102 CL/MJ SD-761: created.

/** 'write', 'read' or 'none', from the Systemdata right at offset 1. */
function shopify_admin_access(string $rettigheder): string {
	$right = substr($rettigheder, 1, 1);
	if ($right === '1') {
		return 'write';
	}
	return $right === '2' ? 'read' : 'none';
}

/** Escapes a value for HTML text and attribute values. */
function shopify_admin_h($value): string {
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A findtekst() 'key|fallback' token, translated and escaped for HTML. */
function shopify_admin_t(string $token): string {
	global $sprog_id;
	return shopify_admin_h(findtekst($token, $sprog_id));
}

/** The session's CSRF token, shared with the other Systemdata forms. */
function shopify_admin_csrf_token(): string {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

/**
 * The hidden fields every form posts: the CSRF token, and the tenant the form was opened in.
 * includes/online.php drops a POST whose thisDb is not the session's current tenant, so a form
 * left open while switching tenants in another tab cannot write into the other tenant.
 */
function shopify_admin_form_fields(): string {
	global $db;
	return "<input type='hidden' name='csrf_token' value='" . shopify_admin_h(shopify_admin_csrf_token()) . "'>\n"
		. "<input type='hidden' name='thisDb' value='" . shopify_admin_h($db) . "'>\n";
}

/** Whether the posted form carries the session's CSRF token. */
function shopify_admin_csrf_valid(): bool {
	$token = $_SESSION['csrf_token'] ?? '';
	return is_string($token) && $token !== '' && hash_equals($token, (string)($_POST['csrf_token'] ?? ''));
}

/** Stores a message for the page after the redirect and redirects (Post/Redirect/Get). */
function shopify_admin_redirect(string $url, string $messageToken): void {
	$_SESSION['shopify_admin_flash'] = $messageToken;
	header('Location: ' . $url, true, 303);
	exit;
}

/** Prints the header, the Shopify tabs and the message left by the last redirect. */
function shopify_admin_header(string $titleToken, string $active): void {
	$tabs = array(
		'shops.php' => 'shopify.admin.tab.shops|Shops',
		'lagre.php' => 'shopify.admin.tab.lagre|Lagre',
	);
	print "<table width='100%' cellpadding='4' cellspacing='0'><tr>";
	print "<td width='170px'><a href='../diverse.php'><button type='button' style='width:100%'>" . shopify_admin_t('30|Tilbage') . "</button></a></td>";
	print "<td align='center'><b>" . shopify_admin_t($titleToken) . "</b></td><td width='170px'></td></tr></table>\n";
	print "<p style='margin:8px'>";
	foreach ($tabs as $url => $token) {
		$label = shopify_admin_t($token);
		print $url === $active ? "<b>$label</b> &nbsp; " : "<a href='" . shopify_admin_h($url) . "'>$label</a> &nbsp; ";
	}
	print "</p>\n";
	if (!empty($_SESSION['shopify_admin_flash'])) {
		print "<p style='margin:8px;color:#060'>" . shopify_admin_t((string)$_SESSION['shopify_admin_flash']) . "</p>\n";
		unset($_SESSION['shopify_admin_flash']);
	}
}

/** A findtekst token as a red error line, escaped. */
function shopify_admin_error(string $token): string {
	return "<span style='color:#c00'>" . shopify_admin_t($token) . "</span>";
}
