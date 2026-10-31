<?php
// ---- index/invite.php --- lap 5.0.0 --- 2026.09.29 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260929 Sawaneh Roles stage 2 (Requirements_roles_stage2_EN.md §8.4): the page an invitation link
//                  opens. The invited user chooses a password and is then logged in the normal way.
// 20261001 Sawaneh MySQL: select the company database after connecting (as online.php).
// 20261014 Sawaneh Review: the auto-login after an invitation keeps the password in the session for one minute, not in the HTML.

@session_start();
if (empty($_SESSION['nonce'])) {
	$_SESSION['nonce'] = bin2hex(random_bytes(16));
}
if (empty($_SESSION['invite_csrf'])) {
	$_SESSION['invite_csrf'] = bin2hex(random_bytes(24));
}
$nonce = $_SESSION['nonce'];

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/db_query.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/../includes/tmpCode.php");

$sprog_id = (isset($_COOKIE['languageId']) && (int) $_COOKIE['languageId'] > 0) ? (int) $_COOKIE['languageId'] : 1;
$token = isset($_POST['t']) ? (string) $_POST['t'] : (isset($_GET['t']) ? (string) $_GET['t'] : '');

$invite = invite_lookup($token);
$state = $invite['state'];
$error = '';
$login = null;

if ($state === 'ok' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	// The login page trims the password, so the stored one must be trimmed too.
	$kode = isset($_POST['kode']) ? trim((string) $_POST['kode']) : '';
	$kode2 = isset($_POST['kode2']) ? trim((string) $_POST['kode2']) : '';
	if (!isset($_POST['csrf']) || !hash_equals($_SESSION['invite_csrf'], (string) $_POST['csrf'])) {
		$error = findtekst('5784|Linket er ikke længere gyldigt. Bed din administrator sende invitationen igen.', $sprog_id);
	} elseif (mb_strlen($kode) < 8) {
		$error = findtekst('5785|Adgangskoden skal være på mindst 8 tegn', $sprog_id);
	} elseif ($kode !== $kode2) {
		$error = findtekst('5589|Adgangskoden og gentagelsen er ikke ens', $sprog_id);
	} else {
		include_once(__DIR__ . "/../includes/userFunctions.php");
		$bruger_id = $invite['user']['id'];
		$brugernavn = $invite['user']['brugernavn'];
		user_set_first_password((int) $bruger_id, $kode, 'invite');
		unset($_SESSION['invite_csrf']);
		$_SESSION['invite_login'] = array('regnskab' => $invite['regnskab'], 'brugernavn' => $brugernavn, 'password' => $kode, 'at' => time());
		$login = array('regnskab' => $invite['regnskab'], 'brugernavn' => $brugernavn);
		$state = 'done';
	}
}

invite_view($state, $token, $invite, $error, $login, $sprog_id, $nonce);

/**
 * Find the company and the user an invitation token belongs to. Leaves the connection on the
 * company database when the token names one.
 *
 * @return array{state: string, regnskab: string, user: array<string, mixed>|null}
 */
function invite_lookup(string $token): array
{
	global $sqhost, $squser, $sqpass, $sqdb, $connection, $db_type, $db;
	$none = array('state' => 'invalid', 'regnskab' => '', 'user' => null);
	if (!preg_match('/^(\d{1,9})-([a-f0-9]{48})$/', $token, $m)) {
		return $none;
	}
	$r = db_fetch_array(db_select("select * from regnskab where id = " . (int) $m[1], __FILE__ . " linje " . __LINE__));
	if (!$r || !trim((string) $r['db']) || trim((string) $r['db']) === $sqdb || trim((string) ifset($r, 'lukket', ''))) {
		return $none;
	}
	$connection = db_connect("'$sqhost'", "'$squser'", "'$sqpass'", "'" . trim((string) $r['db']) . "'");
	// MySQL connects to the server only; the company database is selected separately (as in online.php).
	if ($connection && ($db_type === 'mysqli' || $db_type === 'mysql') && !mysqli_select_db($connection, trim((string) $r['db']))) {
		return $none;
	}
	$db = trim((string) $r['db']);
	if (!$connection) {
		return $none;
	}
	$hash = hash('sha256', $m[2]);
	$u = db_fetch_array(db_select("select * from brugere where tmp_kode like 'invite|%|$hash'", __FILE__ . " linje " . __LINE__));
	if (!$u || (string) ifset($u, 'kode', '') !== '') {
		return $none;
	}
	if (array_key_exists('status', $u) && in_array($u['status'], array('f', false, '0', 0), true)) {
		return $none;
	}
	$state = tmp_code_check($u['tmp_kode'], 'invite', $hash);
	if ($state === '') {
		return $none;
	}
	return array('state' => $state, 'regnskab' => (string) $r['regnskab'], 'user' => $u);
}

function invite_view(string $state, string $token, array $invite, string $error, $login, int $sprogId, string $nonce): void
{
	global $charset;
	$enc = !empty($charset) ? (string) $charset : 'UTF-8';
	$h = function ($s) use ($enc) {
		return htmlspecialchars((string) $s, ENT_QUOTES, $enc);
	};
	$t = function ($text) use ($sprogId, $h) {
		return $h(findtekst($text, $sprogId));
	};
	header('Content-Type: text/html; charset=' . $enc);
	header('Cache-Control: no-store');
	header('Referrer-Policy: no-referrer');
	?>
<!DOCTYPE html>
<html>
<head>
<meta charset="<?= $h($enc) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $t('5775|Vælg din adgangskode') ?></title>
<style nonce="<?= $h($nonce) ?>">
body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f2f4f8; color: #1c2431; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
.iv-card { width: min(420px, calc(100% - 32px)); background: #fff; border: 1px solid #e2e6ee; border-radius: 14px; padding: 28px; box-sizing: border-box; }
.iv-card h1 { font-size: 21px; margin: 0 0 6px; }
.iv-card p { color: #68738a; font-size: 14px; line-height: 1.5; margin: 0 0 18px; }
.iv-card label { display: block; font-size: 12.5px; font-weight: 600; margin: 12px 0 5px; }
.iv-card input[type=password] { width: 100%; height: 40px; border: 1px solid #d5dbe6; border-radius: 9px; padding: 0 11px; font-size: 14.5px; box-sizing: border-box; }
.iv-card button { margin-top: 20px; width: 100%; height: 42px; border: 0; border-radius: 9px; background: #114691; color: #fff; font-size: 14.5px; font-weight: 600; cursor: pointer; }
.iv-err { background: #fdf1ee; border: 1px solid #f3c4bc; color: #a4301d; border-radius: 9px; padding: 10px 12px; font-size: 13.5px; margin-bottom: 14px; }
</style>
</head>
<body>
<div class="iv-card">
	<?php if ($state === 'done' && is_array($login)) { ?>
  <h1><?= $t('5790|Adgangskoden er gemt') ?></h1>
  <p><?= $t('5791|Du bliver nu logget ind.') ?></p>
  <form id="iv-login" method="post" action="login.php">
    <input type="hidden" name="regnskab" value="<?= $h($login['regnskab']) ?>">
    <input type="hidden" name="brugernavn" value="<?= $h($login['brugernavn']) ?>">
    <input type="hidden" name="invite_login" value="1">
    <button type="submit"><?= $t('5789|Fortsæt') ?></button>
  </form>
  <script nonce="<?= $h($nonce) ?>">document.getElementById('iv-login').submit();</script>
	<?php } elseif ($state === 'ok') { ?>
  <h1><?= $t('5775|Vælg din adgangskode') ?></h1>
  <p><?= $h($invite['regnskab']) ?> · <?= $t('225|Brugernavn') ?>: <b><?= $h($invite['user']['brugernavn']) ?></b></p>
		<?php if ($error !== '') { ?><div class="iv-err" role="alert"><?= $h($error) ?></div><?php } ?>
  <form method="post" action="invite.php" autocomplete="off">
    <input type="hidden" name="t" value="<?= $h($token) ?>">
    <input type="hidden" name="csrf" value="<?= $h($_SESSION['invite_csrf']) ?>">
    <label for="iv-kode"><?= $t('324|Adgangskode') ?></label>
    <input id="iv-kode" type="password" name="kode" autocomplete="new-password" minlength="8" required autofocus>
    <label for="iv-kode2"><?= $t('328|Gentag adgangskode') ?></label>
    <input id="iv-kode2" type="password" name="kode2" autocomplete="new-password" minlength="8" required>
    <button type="submit"><?= $t('3|Gem') ?></button>
  </form>
	<?php } else { ?>
  <h1><?= $t('5792|Invitationen kan ikke bruges') ?></h1>
  <div class="iv-err" role="alert"><?= $t('5784|Linket er ikke længere gyldigt. Bed din administrator sende invitationen igen.') ?></div>
  <p><a href="index.php"><?= $t('5793|Til login') ?></a></p>
	<?php } ?>
</div>
</body>
</html>
	<?php
}
