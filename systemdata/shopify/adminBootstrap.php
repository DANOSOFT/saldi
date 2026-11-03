<?php
// --- systemdata/shopify/adminBootstrap.php --- ver 5.1.0 --- 2026-11-03 ---
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
// Shared start of the Shopify admin pages (WP04): session and tenant, then the gate. A user
// without the Systemdata right, a tenant without the Shopify schema, a request with a NUL byte,
// and a POST from a read-only user, without the form's CSRF token or from another tenant's form
// stop here. A page sets $title, includes this file, handles its POST and renders with the
// helpers of adminCommon.php.
//
// 20261102 CL/MJ SD-761: created.
// 20261103 CL/MJ SD-761 review: a POST needs thisDb; a NUL byte is refused; $shopifyCanCreate for Danosoft's login.

// online.php prints the page head before a page handles its POST; buffering lets the redirect after a
// save and the 403 of a refused POST still send their headers.
ob_start();
@session_start();
$s_id = session_id();
$css = '../../css/standard.css';

include_once(__DIR__ . '/../../includes/std_func.php');
include(__DIR__ . '/../../includes/connect.php');
include(__DIR__ . '/../../includes/online.php');
require_once __DIR__ . '/../../includes/shopify/config.php';
require_once __DIR__ . '/adminCommon.php';
require_once __DIR__ . '/adminModel.php';
require_once __DIR__ . '/adminValidation.php';

/**
 * Set by includes/online.php:
 * @var string $rettigheder the user's rights string
 * @var string|null $revisor online.revisor (boolean): 't' (PostgreSQL) or '1' (MySQL) when the session was
 *   opened from the master admin (admin/aaben_regnskab.php); 'f', '0' or null otherwise
 * @var string $db the session's tenant
 */

$shopifyAccess = shopify_admin_access((string)($rettigheder ?? ''));
// Creating a shop is Danosoft's: each shop needs its own Shopify app. Editing one needs the Systemdata right only.
$shopifyCanCreate = $shopifyAccess === 'write' && in_array((string)($revisor ?? ''), array('t', '1'), true);
if ($shopifyAccess === 'none') {
	print "<p style='margin:20px'>" . shopify_admin_t('shopify.admin.no_access|Du har ikke adgang til Shopify-opsætningen.') . "</p>";
	exit;
}
// shopify_schema_ready() is false on MySQL, where the module does not run.
if (!shopify_schema_ready()) {
	print "<p style='margin:20px'>" . shopify_admin_t('shopify.admin.not_installed|Shopify-modulet er ikke installeret på dette regnskab.') . "</p>";
	exit;
}
// shopify_db_rows() refuses a NUL byte with an exception; here it is a plain 400.
$shopifyNul = false;
array_walk_recursive($_REQUEST, function ($value) use (&$shopifyNul) {
	$shopifyNul = $shopifyNul || (is_string($value) && strpos($value, "\0") !== false);
});
if ($shopifyNul) {
	http_response_code(400);
	exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($shopifyAccess !== 'write' || !shopify_admin_csrf_valid() || ($_POST['thisDb'] ?? null) !== $db)) {
	http_response_code(403);
	print "<p style='margin:20px'>" . shopify_admin_t('shopify.admin.post_refused|Ændringen blev afvist: du har kun læseadgang, eller formularen er udløbet. Genindlæs siden.') . "</p>";
	exit;
}
