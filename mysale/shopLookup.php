<?php
// 20260915 CDX/PHR Shop/customer lookup for the existing MySale portal.
// 20260915 CDX/PHR Clear customer state on logout while retaining the Saldi staff login.
// 20260917 CL/LH Ignore customer rows (rettigheder='0') when resolving the staff session.

/**
 * @return bool Whether a valid customer logout was requested.
 */
function mySaleCustomerLogout(array &$session, $token) {
	if (!is_string($token) || empty($session['mySaleLogoutToken'])
		|| !hash_equals($session['mySaleLogoutToken'], $token)) {
		return false;
	}
	foreach (array('mySalePw', 'mySaleAcId', 'mySale', 'linkLog', 'mySaleLogoutToken') as $key) {
		unset($session[$key]);
	}
	return true;
}

/** @return void */
function mySaleRemoveCustomerSession($sessionId) {
	$session = db_escape_string($sessionId);
	// Only remove temporary customer entries, never the logged-in staff entry.
	db_modify("DELETE FROM online WHERE session_id='$session' AND rettigheder='0' AND regnskabsaar='0'", __FILE__ . ' ' . __LINE__, true);
}

/** @return void */
function mySaleCustomerLogoutButton($token) {
	?>
	<form method="post" action="mysale.php" style="text-align:right;padding:12px;">
		<input type="hidden" name="action" value="logout_customer">
		<input type="hidden" name="logoutToken" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
		<button type="submit" style="padding:10px 20px;cursor:pointer;"><?= 'Log af' ?></button>
	</form>
	<?php
}

/**
 * Resolve the active Saldi staff session and look up a debtor in its tenant.
 *
 * @return array{shop: string, error: string, redirect: string}
 */
function mySaleShopLookup($sessionId, $customerNumber, $host, callable $connectTenant) {
	$result = array('shop' => '', 'error' => '', 'redirect' => '');
	$session = db_escape_string($sessionId);
	// Customer portal rows share the session_id but carry rights='0'; skip them so a
	// newer customer row cannot shadow the staff login (same filter as mysale.php).
	$q = db_select("SELECT db,rettigheder,logtime FROM online WHERE session_id='$session' AND rettigheder <> '0' ORDER BY logtime DESC LIMIT 1", __FILE__ . ' ' . __LINE__, true);
	$login = db_fetch_array($q);
	// Customer portal sessions have rights='0'; they are not a Saldi staff login.
	if (!$login || empty($login['db']) || empty($login['rettigheder'])
		|| (int)$login['logtime'] < time() - 86400) {
		$result['redirect'] = '../index/index.php';
		return $result;
	}
	$tenant = db_escape_string($login['db']);
	$q = db_select("SELECT regnskab,db,lukket,lukkes FROM regnskab WHERE db='$tenant'", __FILE__ . ' ' . __LINE__, true);
	$account = db_fetch_array($q);
	if (!$account || $account['lukket'] === 'on'
		|| (!empty($account['lukkes']) && $account['lukkes'] !== '2099-12-31'
			&& strtotime($account['lukkes']) <= strtotime(date('Y-m-d')))) {
		$result['error'] = 'Regnskabet er lukket eller findes ikke. Kontakt butikken.';
		return $result;
	}
	$now = time();
	db_modify("UPDATE online SET logtime='$now' WHERE session_id='$session'", __FILE__ . ' ' . __LINE__, true);
	$result['shop'] = $account['regnskab'];
	if ($customerNumber === '') {
		return $result;
	}
	if (!ctype_digit($customerNumber)) {
		$result['error'] = 'Indtast et gyldigt kundenummer.';
		return $result;
	}
	if (!$connectTenant($account['db'])) {
		$result['error'] = 'Butikken kunne ikke åbnes. Prøv igen senere.';
		return $result;
	}
	$number = db_escape_string($customerNumber);
	$q = db_select("SELECT id,kontonr FROM adresser WHERE kontonr='$number' AND art='D' ORDER BY id LIMIT 2", __FILE__ . ' ' . __LINE__);
	$customer = db_fetch_array($q);
	if (!$customer || db_fetch_array($q)) {
		$result['error'] = 'Kundenummeret blev ikke fundet entydigt. Kontrollér nummeret eller kontakt butikken.';
		return $result;
	}
	$id = bin2hex((int)$customer['id'] . '|' . $customer['kontonr'] . '@' . $account['db'] . '@' . $host);
	$result['redirect'] = 'mysale.php?id=' . $id;
	return $result;
}

/**
 * @param array{shop: string, error: string, redirect: string} $lookup
 * @return void
 */
function mySaleShopLookupForm($customerNumber, array $lookup) {
	$escape = function ($text) { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); };
	?>
<!doctype html>
<html lang="da">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Mit salg</title>
	<style>
		body { font-family: Arial, sans-serif; background: #eeeef0; margin: 0; color: #222; }
		main { max-width: 360px; margin: 10vh auto; padding: 24px; background: white; border-radius: 8px; }
		label, input, button { display: block; box-sizing: border-box; width: 100%; margin-top: 12px; }
		input, button { padding: 12px; font: inherit; }
		button { background: #114691; color: white; border: 0; border-radius: 4px; cursor: pointer; }
		.error { color: #a21c1c; }
	</style>
</head>
<body><main>
	<h1><?= 'Mit salg' ?></h1>
	<?php if ($lookup['error'] !== '') { ?>
		<p class="error" role="alert"><?= $escape($lookup['error']) ?></p>
	<?php } ?>
	<?php if ($lookup['shop'] !== '') { ?>
		<h2><?= $escape($lookup['shop']) ?></h2>
		<form method="post" action="mysale.php">
			<label for="kundenummer"><?= 'Kundenummer' ?></label>
			<input id="kundenummer" name="kundenummer" inputmode="numeric" pattern="[0-9]+" value="<?= $escape($customerNumber) ?>" required autofocus>
			<button type="submit"><?= 'Fortsæt' ?></button>
		</form>
	<?php } ?>
</main></body></html>
	<?php
}
