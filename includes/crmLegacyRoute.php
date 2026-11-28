<?php
// --- includes/crmLegacyRoute.php --- ver 5.1.1 --- 2026.11.10 ---
// Copyright (c) 2026 Danosoft ApS
// 20261110 CDX/ASR Keep old CRM bookmarks usable without maintaining unprotected legacy mutation handlers.

/** @var string $crmLegacyTab Fixed target tab supplied by the legacy entry script. */
@session_start();
$s_id = session_id();
ob_start();
$header = 'nix';
$css = '';
$title = 'CRM';
$modulnr = 0;
$permission_key = 'crm.access';
$crmLegacyWrites = !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', array('GET', 'HEAD'), true)
    || ($_GET['handling'] ?? '') === 'slet' || ($_GET['action'] ?? '') === 'toggle';
$permission_level = $crmLegacyWrites ? 'write' : 'read';
include __DIR__ . '/connect.php';
include __DIR__ . '/online.php';
include_once __DIR__ . '/std_func.php';
require_permission('crm.access', $permission_level);

$params = array('tab' => in_array($crmLegacyTab ?? '', array('follow', 'cal', 'cust', 'act'), true) ? $crmLegacyTab : 'follow');
$customer = (int) ($_GET['konto_id'] ?? $_GET['id'] ?? 0);
if ($customer > 0 && ($crmLegacyTab ?? '') === 'cust') {
    $params['drawer'] = $customer;
}
foreach (array('inframe', 'popup') as $flag) {
    if (!empty($_GET[$flag])) {
        $params[$flag] = '1';
    }
}
$url = '../debitor/crm.php?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Cache-Control: no-store');
if ($crmLegacyWrites) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    header('Content-Type: text/html; charset=utf-8');
    $message = findtekst('crm.legacy.use_current|Denne gamle CRM-handling er udgået. Åbn CRM for at fortsætte.', (int) ($sprog_id ?? 1));
    echo '<p>' . htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8') . '</p><p><a href="'
        . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">CRM</a></p>';
    exit;
}
header('Location: ' . $url, true, 302);
exit;
