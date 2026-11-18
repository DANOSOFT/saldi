<?php
// 20260914 CDX/LH Check journal save identity, retry, action and tenant/user boundaries.
// 20261116 CL/LH Cover Bankforbindelse request identity, journal reuse and replay redirects to the feed.
// Run: php tests/integration/journalSaveReplay.php
require_once __DIR__ . '/../../finans/kassekladde_includes/saveReplay.php';
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});

$checks = 0;
/** @return void */
function checkReplay($condition, $message) {
	if (!$condition) {
		throw new RuntimeException($message);
	}
	$GLOBALS['checks']++;
}

$session = [];
$post = ['save' => 'Gem', 'kladde_id' => '0', 'kk_save_token' => bin2hex(random_bytes(32)), 'belo1' => '12,50'];
$key = journalSaveRequestKey($post, 'tenant_a', 'user_a');
$formKey = journalSaveFormKey($post, 'tenant_a', 'user_a');
checkReplay(journalSavedRequest($session, $key) === null, 'First save must run');
checkReplay(journalSaveTarget($session, $formKey, 0) === 0, 'First save must allocate a journal');
journalRememberCreation($session, $formKey, 42);
checkReplay(journalSaveTarget($session, $formKey, 0) === 42, 'A failed first save must reuse the allocated journal');
checkReplay(journalSavedRequest($session, $key) === null, 'A failed save must be validated again');
journalRememberSave($session, $key, 42);
checkReplay(journalSavedRequest($session, $key) === 42, 'Identical completed POST must return the saved journal');

$bankSession = [];
$bankPost = $post;
unset($bankPost['save']);
$bankPost['bankfeed'] = 'Bankforbindelse';
$bankKey = journalSaveRequestKey($bankPost, 'tenant_a', 'user_a');
$bankFormKey = journalSaveFormKey($bankPost, 'tenant_a', 'user_a');
checkReplay($bankKey !== null, 'Bankforbindelse must get an explicit save request key');
checkReplay($bankFormKey !== null, 'Bankforbindelse must get a form key');
checkReplay($bankKey !== $key, 'Bankforbindelse and Gem requests must not collide');
journalRememberCreation($bankSession, $bankFormKey, 42);
checkReplay(journalSaveTarget($bankSession, $bankFormKey, (int)$bankPost['kladde_id']) === 42, 'Repeated Bankforbindelse on a new form must reuse its journal');
checkReplay(journalSavedRequest($bankSession, $bankKey) === null, 'Uncompleted Bankforbindelse must still save');
journalRememberSave($bankSession, $bankKey, 42);
checkReplay(journalSavedRequest($bankSession, journalSaveRequestKey($bankPost, 'tenant_a', 'user_a')) === 42, 'Repeated Bankforbindelse must find its completed save');
checkReplay(journalSaveReplayLocation($bankPost, 42) === '../bank_integration/feed.php?kladde_id=42', 'Repeated Bankforbindelse must redirect to the bank feed');
checkReplay(journalSaveReplayLocation($post, 42) === 'kassekladde.php?kladde_id=42&tjek=42', 'Repeated Gem must redirect to the journal');

$changed = $post;
$changed['belo1'] = '25,00';
$changedKey = journalSaveRequestKey($changed, 'tenant_a', 'user_a');
checkReplay(journalSavedRequest($session, $changedKey) === null, 'Editing a field must not be discarded');
journalRememberSave($session, $changedKey, 42);
checkReplay(journalSavedRequest($session, $key) === 42, 'An older completed request must not overwrite a later edit');
$newForm = $post;
$newForm['kk_save_token'] = bin2hex(random_bytes(32));
checkReplay(journalSavedRequest($session, journalSaveRequestKey($newForm, 'tenant_a', 'user_a')) === null, 'Independent new forms may have identical contents');
checkReplay(journalSaveTarget($session, journalSaveFormKey($newForm, 'tenant_a', 'user_a'), 0) === 0, 'Independent new form allocates a separate journal');
foreach ([['tenant_b', 'user_a'], ['tenant_a', 'user_b']] as [$tenant, $user]) {
	checkReplay(journalSavedRequest($session, journalSaveRequestKey($post, $tenant, $user)) === null, 'Completed saves must be scoped to tenant/user');
	checkReplay(journalSaveTarget($session, journalSaveFormKey($post, $tenant, $user), 0) === 0, 'Allocated journals must be scoped to tenant/user');
}
foreach (['copy2new', 'import', 'lookup', 'doPost', 'revert', 'offset', 'simulate', 'upload'] as $action) {
	checkReplay(journalSaveRequestKey($post + [$action => '1'], 'tenant_a', 'user_a') === null, 'Other actions must not become completed saves');
}
foreach ([null, [], 'invalid'] as $token) {
	$invalid = $post;
	$invalid['kk_save_token'] = $token;
	checkReplay(journalSaveRequestKey($invalid, 'tenant_a', 'user_a') === null, 'Invalid form token must be handled without warnings');
}
checkReplay(journalSaveTarget($session, $formKey, 55) === 55, 'Explicit journal id takes precedence over creation mapping');
for ($i = 0; $i < 200; $i++) {
	journalRememberSave($session, hash('sha256', (string)$i), $i + 1);
	journalRememberCreation($session, hash('sha256', (string)$i), $i + 1);
}
checkReplay(count($session['kk_completed_saves']) === 128, 'Completed history must be bounded');
checkReplay(count($session['kk_created_journals']) === 128, 'Creation history must be bounded');
echo "$checks journal save checks passed with warnings enabled.\n";
