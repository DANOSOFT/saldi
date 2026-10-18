<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- payments/mobilepay/mobilepay.php --- lap 4.1.0 --- 2024.02.27 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 20240209 PHR Added indbetaling
// 20240227 PHR Added $printfile and call to saldiprint.php
// 20260916 Sawaneh Declared $permission_key (roles & permissions, phase 3)
// 20261001 Sawaneh The old webhook secret is removed before the new one is stored (one row per setting).
// 20261002 Sawaneh Started from Indstillinger » Integrationer » MobilePay (phase 4b): needs settings.integrations write,
//                  prints nothing, goes back to the drawer with webhook=ok or webhook=fail (the reason in the session).
//                  The commented-out list/delete experiments and the stray blank line before <?php are gone.

@session_start();
$s_id = session_id();

include ("../../includes/connect.php");
$permission_key = 'system.indstillinger';
include ("../../includes/online.php");
include ("../../includes/std_func.php");
include_once(__DIR__ . "/../settingsRegistry.php");
include_once(__DIR__ . "/../../includes/settings/components.php");
include_once(__DIR__ . "/../../includes/settings/integrations.php");

if (function_exists('require_permission')) {
	require_permission('settings.integrations', 'write');
}
$back = "../settingsSection.php?s=integrations.connections&item=mobilepay";

function mobilepay_webhook_fail(string $reason, string $back): void
{
	$_SESSION['settings_error'] = $reason;
	header("Location: $back&webhook=fail");
	exit;
}

$cfg = settings_mobilepay_config();
if ($cfg['client_id'] === '') {
	mobilepay_webhook_fail('client_id', $back);
}
$accessToken = settings_mobilepay_token($cfg);
if ($accessToken === '') {
	mobilepay_webhook_fail('accesstoken', $back);
}

$data = json_encode(array(
    'url' => "https://$_SERVER[SERVER_NAME]/pos/debitor/payments/mobilepay/webhook_recive.php?db=" . $db,
    'events' => ['epayments.payment.authorized.v1', 'user.checked-in.v1', 'epayments.payment.cancelled.v1', 'epayments.payment.aborted.v1', 'epayments.payment.expired.v1', 'epayments.payment.terminated.v1']
));
$res = settings_curl('https://api.vipps.no/webhooks/v1/webhooks', settings_mobilepay_headers($cfg, $accessToken), $data);
$webhook = json_decode($res['body'], true);
if ($res['status'] !== 201 || !is_array($webhook) || empty($webhook['secret'])) {
	mobilepay_webhook_fail('webhook: HTTP ' . $res['status'] . ($res['error'] !== '' ? ' ' . $res['error'] : ''), $back);
}
// A new webhook replaces the old secret (one row per setting).
db_modify("delete from settings where var_name = 'webhook_secret' and var_grp = 'mobilepay'", __FILE__ . " linje " . __LINE__);
$qtxt = "insert into settings (var_name, var_grp, var_value, var_description) values ('webhook_secret', 'mobilepay', '" . db_escape_string($webhook['secret']) . "', 'The secret for the mobilepay webhook')";
db_modify($qtxt, __FILE__ . " linje " . __LINE__);
if (function_exists('audit_log')) {
	audit_log('setting.action', 'integrations.mobilepay.connect_webhook');
}
header("Location: $back&webhook=ok");
exit;
