<?php
// 20261125 CDX/ASR Run the real request gate in a child process so refusal must stop the following action.

require_once __DIR__ . '/../../../includes/permissions.php';
$scenario = $argv[1];
$GLOBALS['bruger_id'] = 1;
$GLOBALS['rettigheder'] = str_repeat('1', 16);
$GLOBALS['ent_runtime_id'] = 17;
$GLOBALS['ent_runtime_state'] = $scenario === 'legacy' ? array() : array(
	'plan_key' => 'finans', 'features' => array(), 'row' => array('enforcement' => $scenario === 'observe' ? 'observe' : 'enforce'),
);
$_SERVER['SCRIPT_NAME'] = $scenario === 'protected' ? '/app/kreditor/bogfor.php' : '/app/lager/varekort.php';
$_SERVER['REQUEST_METHOD'] = $scenario === 'read' ? 'GET' : 'POST';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_GET = array();
if ($scenario === 'shop-import') {
	require_once __DIR__ . '/../../../api/varesync.php';
	varesync(1); // Refusal must happen before the first database write, with no database adapter installed here.
}
if ($scenario === 'budget') {
	$_SERVER['SCRIPT_NAME'] = '/app/finans/budget.php';
	perm_enforce_request('finans.regnskab', 'read', false, null);
}
// No role schema: the real role layer uses the legacy rights above and defaults to log mode.
perm_enforce_request($scenario === 'protected' ? 'kreditor.ordre' : 'lager.varer', 'read', false, null);
echo 'CONTINUED';
