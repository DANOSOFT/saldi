<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/tofaktor.php --- ver 5.1.0 --- 2026.11.24 ---
// Included fra login.php
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
// Copyright (c) 2024-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261114 CL/ASR The page is a document of its own and says where the code went (text message or e-mail, the address masked). New: Send en ny kode, and the code the other way when the user has both a phone number and an e-mail.
//                 The values sent back with the form are escaped, the whole code can be pasted, and the texts follow the login's language.
// 20261114 CL/ASR The logo is the dark-gradient logo for light backgrounds from the final logo pack of October 2026 (img/saldi-moerk-logo-lys-baggrund.svg).
// 20261114 CL/ASR css/login.css ?cssver=3: the button has the dark gradient of the logo.
// 20261114 CL/ASR css/login.css ?cssver=4: a light sheen slides across the button from left to right when it is pressed.
// 20261124 CL/ASR Comments state the current rule; no code change.

/**
 * The two-factor step of index/login.php, which includes this file and ends the request.
 *
 * @var string                $regnskab      The ledger, as posted.
 * @var string                $brugernavn    The user name, as stored.
 * @var string                $password      The password just accepted; the form sends it again with the code.
 * @var string                $charset       The page charset (index/login.php).
 * @var int|string            $tfaLang       Language id of the login (1 da, 2 en, 3 no).
 * @var array<string, string> $tfaMethods    'sms' and/or 'email' => where a code can go (includes/twoFactor.php).
 * @var string                $tfaMethod     The way the latest code was sent.
 * @var string                $tfaNotice     '', 'new', 'expired' or 'wait'.
 * @var string                $tfaError      '', 'wrong' or 'sendfail'.
 * @var string                $tfaSendOutput What sending the text message printed (send_sms() notices as script tags).
 */
$tfaLangId = in_array((int) $tfaLang, array(2, 3), true) ? (int) $tfaLang : 1;
$tfaCharset = (isset($charset) && $charset) ? $charset : 'UTF-8';
$tfaH = function ($value) use ($tfaCharset) {
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, $tfaCharset);
};
$tfaT = function ($text) use ($tfaLangId, $tfaH) {
	return $tfaH(findtekst($text, $tfaLangId));
};
$tfaTo = ($tfaMethod === 'sms') ? twofactor_mask_phone($tfaMethods['sms']) : twofactor_mask_email(isset($tfaMethods['email']) ? $tfaMethods['email'] : '');
$tfaSentText = ($tfaMethod === 'sms')
	? findtekst('login.tfa.sent_sms|Vi har sendt en kode på SMS til %s.', $tfaLangId)
	: findtekst('login.tfa.sent_email|Vi har sendt en kode på e-mail til %s.', $tfaLangId);
$tfaOther = '';
foreach ($tfaMethods as $tfaOne => $tfaDestination) {
	if ($tfaOne !== $tfaMethod) {
		$tfaOther = $tfaOne;
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=<?= $tfaH($tfaCharset) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $tfaT('login.tfa.title|Tofaktor-login') ?></title>
<link rel="stylesheet" type="text/css" href="../css/login.css?cssver=4">
<link rel="shortcut icon" href="../../index/favicon.ico">
</head>
<body class="loginPage">
<div id="main">
	<div class="loginHolder">
		<div class="loginBox">
			<div class="loginForm">
				<a href="https://saldi.dk"><img class="logoimg" src="../img/saldi-moerk-logo-lys-baggrund.svg" width="100" alt="Saldi"></a>
				<div class="loginIntro">
					<h2><?= $tfaT('login.tfa.title|Tofaktor-login') ?></h2>
<?php if ($tfaError !== 'sendfail') { ?>
					<p><?= $tfaH(sprintf($tfaSentText, $tfaTo)) ?> <?= $tfaT('login.tfa.valid|Koden gælder i 3 minutter.') ?></p>
<?php } ?>
<?php if ($tfaError === 'wrong') { ?>
					<p class="loginMsg loginMsgErr" role="alert"><?= $tfaT('login.tfa.wrong|Koden er forkert. Prøv igen.') ?></p>
<?php } elseif ($tfaError === 'sendfail') { ?>
					<p class="loginMsg loginMsgErr" role="alert"><?= $tfaT('login.tfa.send_failed|Koden kunne ikke sendes. Prøv igen, eller vælg en anden måde.') ?></p>
<?php } elseif ($tfaNotice === 'expired') { ?>
					<p class="loginMsg" role="status"><?= $tfaT('login.tfa.expired|Koden er udløbet. Vi har sendt en ny.') ?></p>
<?php } elseif ($tfaNotice === 'new') { ?>
					<p class="loginMsg" role="status"><?= $tfaT('login.tfa.new_sent|Vi har sendt en ny kode.') ?></p>
<?php } elseif ($tfaNotice === 'wait') { ?>
					<p class="loginMsg" role="status"><?= $tfaT('login.tfa.wait|Der er lige sendt en kode. Vent et øjeblik, før du beder om en ny.') ?></p>
<?php } ?>
				</div>
				<form method="POST" action="login.php" id="faform" autocomplete="off">
					<input type="hidden" name="regnskab" value="<?= $tfaH($regnskab) ?>">
					<input type="hidden" name="brugernavn" value="<?= $tfaH($brugernavn) ?>">
					<input type="hidden" name="password" value="<?= $tfaH($password) ?>">
					<input type="hidden" name="tfa_method" value="<?= $tfaH($tfaMethod) ?>">
					<div id="fa-row">
<?php for ($tfaI = 1; $tfaI <= 6; $tfaI++) { ?>
						<input class="textinput" type="text" inputmode="numeric" pattern="[0-9]*" id="code_<?= $tfaI ?>" name="code_<?= $tfaI ?>" tabindex="<?= $tfaI ?>" aria-label="<?= $tfaT('login.tfa.code|Kode') ?> <?= $tfaI ?>"<?= $tfaI === 1 ? ' autocomplete="one-time-code"' : ' autocomplete="off"' ?>>
<?php } ?>
					</div>
					<div class="loginAction">
						<button class="button blue" type="submit" id="fa-submit" tabindex="7"><?= $tfaT('login.tfa.submit|Log ind') ?></button>
					</div>
					<div class="loginLinks">
						<button class="linkbutton" type="submit" name="tfa_action" value="resend" tabindex="8"><?= $tfaT('login.tfa.resend|Send en ny kode') ?></button>
<?php if ($tfaOther === 'email') { ?>
						<button class="linkbutton" type="submit" name="tfa_action" value="email" tabindex="9"><?= $tfaT('login.tfa.use_email|Send koden på e-mail i stedet') ?></button>
<?php } elseif ($tfaOther === 'sms') { ?>
						<button class="linkbutton" type="submit" name="tfa_action" value="sms" tabindex="9"><?= $tfaT('login.tfa.use_sms|Send koden på SMS i stedet') ?></button>
<?php } ?>
						<a class="linkbutton" href="index.php" tabindex="10"><?= $tfaT('login.back|Tilbage til login') ?></a>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
<?= $tfaSendOutput ?>
<script>
	// One digit per box: typing moves on, Backspace moves back, a pasted code fills all six, and the sixth digit sends the form.
	(function () {
		var form = document.getElementById('faform');
		var boxes = [];
		for (var i = 1; i <= 6; i++) {
			boxes.push(document.getElementById('code_' + i));
		}
		function fill(from, digits) {
			for (var n = 0; n < digits.length && from + n < boxes.length; n++) {
				boxes[from + n].value = digits.charAt(n);
			}
			var next = Math.min(from + digits.length, boxes.length - 1);
			boxes[next].focus();
			if (boxes.every(function (box) { return box.value !== ''; })) {
				form.submit();
			}
		}
		boxes.forEach(function (box, index) {
			box.addEventListener('input', function () {
				var digits = box.value.replace(/\D/g, '');
				box.value = '';
				if (digits !== '') {
					fill(index, digits);
				}
			});
			box.addEventListener('paste', function (event) {
				var text = (event.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
				if (text !== '') {
					event.preventDefault();
					fill(index, text);
				}
			});
			box.addEventListener('keydown', function (event) {
				if (event.key === 'Backspace' && box.value === '' && index > 0) {
					boxes[index - 1].value = '';
					boxes[index - 1].focus();
					event.preventDefault();
				}
			});
		});
		window.addEventListener('load', function () {
			boxes[0].focus();
		});
	})();
</script>
</body>
</html>
