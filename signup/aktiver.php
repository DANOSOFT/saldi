<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- signup/aktiver.php --- ver 5.1.0 --- 2026-11-02 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// The activation page the welcome mail links to (plan.md, section 3; design:
// docs/oprettelsesflow/aktiveringsside-preview.html). ?t=<token> identifies the pending signup.
//
// States: Aktivér (confirm the account name, choose a password), Ret navn, Navn optaget,
// Link udløbet (send a new link), Allerede aktiveret (go to login), Aktiveret (the account was
// created; the customer is logged in through index/login.php).
//
// On POST the name is normalised and checked for uniqueness, the password is checked (length,
// not the customer's own details, not a known leaked password), and signup_activate() creates the
// account in one go. The password is only ever posted over https and stored hashed; it is never
// mailed or put in a url.
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR Trims the password like index/login.php.
//                 Activation attempts are claimed (recorded, then counted), and max_activations_per_day caps new accounts for the server.
//                 A signup whose account build failed three times shows a "call us" page.

chdir(__DIR__);
include(__DIR__ . "/../includes/signupIncludes/signupBootstrap.php");

$method = ifset($_SERVER, 'REQUEST_METHOD', 'GET');
$token = (string)ifset($_REQUEST, 't', '');
$row = signup_find_pending_by_token($token);
$urls = signup_urls($row ? $row['pakke'] : 'business');

if (!$row) {
	signup_page('Linket er ugyldigt',
		signup_icon('warn') . '<h1>Linket virker ikke</h1>'
		. '<p class="lead">Aktiveringslinket er ikke gyldigt. Måske blev det ikke kopieret helt, eller også er der sendt et nyere link. Skriv jeres e-mail, så sender vi et nyt.</p>'
		. '<form method="post" action="nytlink.php"><div class="field"><label for="email">E-mail</label><div class="input"><input id="email" name="email" type="email" autocomplete="email" required></div></div>'
		. '<button type="submit" class="btn">Send et nyt link</button></form>'
		. '<p class="fine">Har I allerede aktiveret regnskabet? <a class="link" href="' . signup_h($urls['login']) . '">Log ind</a></p>', '', 404);
}

if ($row['state'] === 'activated') {
	$login = $urls['login'] . (strpos($urls['login'], '?') === false ? '?' : '&') . 'regnskab=' . rawurlencode($row['regnskab']);
	signup_page('Regnskabet er allerede aktiveret',
		signup_icon('ok') . '<h1>Regnskabet er allerede aktiveret</h1>'
		. '<p class="lead">Log ind med jeres e-mail og den adgangskode, I valgte. Regnskabet er udfyldt for jer.</p>'
		. '<a class="btn" href="' . signup_h($login) . '">Gå til login</a>'
		. '<p class="fine"><a class="link" href="' . signup_h(dirname($urls['login']) . '/glemt_kode.php') . '">Glemt adgangskode?</a></p>');
}

if ($row['state'] === 'failed') {
	signup_page('Regnskabet kunne ikke oprettes',
		signup_icon('warn') . '<h1>Regnskabet kunne ikke oprettes</h1>'
		. '<p class="lead">Der gik noget galt flere gange, da vi oprettede regnskabet. Ring til os, så hjælper vi jer videre.</p>'
		. signup_help_line(), '', 500);
}

if ($row['state'] === 'activating') {
	signup_page('Regnskabet oprettes',
		signup_icon('ok') . '<h1>Regnskabet er ved at blive oprettet</h1>'
		. '<p class="lead">Det tager normalt under et minut. Opdatér siden om lidt.</p>'
		. '<a class="btn" href="aktiver.php?t=' . signup_h(rawurlencode($token)) . '">Opdatér</a>' . signup_help_line());
}

if ($row['state'] !== 'valid') {
	// expired, or replaced by a newer link
	$days = max(1, (int)signup_setting('token_days', '7'));
	$why = ($row['state'] === 'replaced') ? 'Der er sendt et nyere aktiveringslink, så dette virker ikke længere.' : 'Aktiveringslinket gælder i ' . $days . ' dage.';
	signup_page('Linket er udløbet',
		signup_icon('warn') . '<h1>Linket er udløbet</h1>'
		. '<p class="lead">' . signup_h($why) . ' Vi kan sende et nyt link til <strong>' . signup_h(signup_mask_email($row['email'])) . '</strong>, så I kan aktivere regnskabet.</p>'
		. '<form method="post" action="nytlink.php"><input type="hidden" name="t" value="' . signup_h($token) . '"><button type="submit" class="btn">Send et nyt link</button></form>'
		. '<p class="fine">Har I allerede aktiveret regnskabet? <a class="link" href="' . signup_h($urls['login']) . '">Log ind</a></p>', '', 410);
}

// ---- state 'valid': show the form, or activate -------------------------------------------------

$pakker = signup_packages();
$p = ifset($pakker, $row['pakke'], $pakker['business']);
$view = array(
	'name' => $row['regnskab'],
	'editing' => false,
	'nameStatus' => '',      // '' | 'ok' | 'taken' | 'empty'
	'suggestion' => '',
	'pwReason' => '',        // '' | 'short' | 'long' | 'personal' | 'pwned' | 'empty'
	'error' => '',
);

if ($method === 'POST') {
	if (!signup_claim_event('aktiver', 30, 3600)) {
		signup_page('For mange forsøg', signup_icon('warn') . '<h1>For mange forsøg</h1><p class="lead">Prøv igen om lidt.</p>' . signup_help_line(), '', 429);
	}
	$name = signup_normalize_name((string)ifset($_POST, 'regnskab', $row['regnskab']));
	// index/login.php trims the password it is given, so a leading or trailing space here would make the
	// new account impossible to log in to.
	$password = trim((string)ifset($_POST, 'password', ''));
	$view['name'] = $name;
	$view['editing'] = (mb_strtolower($name) !== mb_strtolower($row['regnskab']));
	$ok = true;
	if ($name === '') {
		$view['nameStatus'] = 'empty';
		$view['editing'] = true;
		$ok = false;
	} else {
		$avail = signup_name_available($name);
		if (!$avail['available']) {
			$view['nameStatus'] = 'taken';
			$view['suggestion'] = $avail['suggestion'];
			$view['editing'] = true;
			$ok = false;
		} else {
			$view['nameStatus'] = 'ok';
		}
	}
	if ($password === '') {
		$view['pwReason'] = 'empty';
		$ok = false;
	} else {
		$pw = signup_validate_password($password, array('email' => $row['email'], 'firmanavn' => $row['firmanavn'], 'regnskab' => $name));
		if (!$pw['ok']) {
			$view['pwReason'] = $pw['reason'];
			$ok = false;
		}
	}
	// Global ceiling on new accounts (each one is a database on the shared server): above it the form says so
	// and the log says why; sales can raise max_activations_per_day.
	if ($ok && signup_count_events('aktiveret', 86400, null, true) >= (int)signup_setting('max_activations_per_day', '30')) {
		signup_log('max_activations_per_day reached; activation of signup ' . (int)$row['id'] . ' refused');
		$view['error'] = 'Vi kan ikke oprette flere regnskaber lige nu. Ring til os, så opretter vi det for jer.';
		$ok = false;
	}
	if ($ok) {
		$result = signup_activate($row, $name, $password);
		if ($result['ok']) {
			signup_log_event('aktiveret', $row['email']);
			signup_create_billing_order($row, $result['regnskab'], $result['trial_end']);
			signup_activated_page($row, $result, $password, $urls);
		}
		if ($result['error'] === 'taken') {
			$view['nameStatus'] = 'taken';
			$view['suggestion'] = $result['suggestion'];
			$view['editing'] = true;
		} elseif ($result['error'] === 'state' || $result['error'] === 'failed') {
			header('Location: aktiver.php?t=' . rawurlencode($token));
			exit;
		} else {
			$view['error'] = 'Regnskabet kunne ikke oprettes lige nu. Vi har registreret fejlen. Prøv igen om lidt, eller ring til os.';
		}
	}
}

signup_activation_form($row, $p, $token, $view);

/**
 * Renders the activation form in one of its states and ends the request.
 *
 * @param array  $row   signup_pending row
 * @param array  $p     package (name, price)
 * @param string $token
 * @param array  $view  array{name, editing, nameStatus, suggestion, pwReason, error}
 * @return void
 */
function signup_activation_form(array $row, array $p, $token, array $view) {
	$h = 'signup_h';
	$days = signup_setting('trial_days', '30');
	$pwMessages = array(
		'empty' => 'Vælg en adgangskode for at aktivere regnskabet.',
		'short' => 'Adgangskoden er for kort – den skal være mindst 10 tegn.',
		'long' => 'Adgangskoden er for lang – højst 200 tegn.',
		'personal' => 'Adgangskoden må ikke være jeres e-mail eller firmanavn. Vælg en anden.',
		'pwned' => 'Den adgangskode er kendt fra datalæk. Vælg en anden.',
	);
	$pwReason = $view['pwReason'];
	$pwState = ($pwReason !== '') ? 'error' : 'pending';
	$pwText = ($pwReason !== '') ? $pwMessages[$pwReason] : 'Alle tegn er tilladt – også mellemrum og specialtegn.';
	$reqLen = ($pwReason === 'short' || $pwReason === 'empty') ? 'error' : 'pending';
	$reqCommon = ($pwReason === 'personal' || $pwReason === 'pwned') ? 'error' : 'pending';
	$editing = $view['editing'];
	$ns = $view['nameStatus'];
	$undoHidden = ($ns === 'taken' && mb_strtolower($view['name']) === mb_strtolower($row['regnskab']));

	$content = '<form id="v-aktiver" method="post" action="aktiver.php" novalidate>'
		. '<input type="hidden" name="t" value="' . $h($token) . '">'
		. '<h1>Aktivér jeres regnskab</h1>'
		. '<p class="lead">Vælg en adgangskode, så er I klar. Jeres ' . $h($days) . ' dages gratis prøveperiode starter, når I aktiverer.</p>';
	if ($view['error'] !== '') {
		$content .= '<div class="notice err" role="alert">' . $h($view['error']) . '</div>';
	}
	$content .= '<div class="summary">'
		. '<div class="row" id="name-row"><span class="k">Regnskab</span><span class="v" id="name-text">' . $h($view['name']) . '</span>'
		. '<button type="button" class="edit" id="edit-name"' . ($editing ? ' hidden' : '') . '>Ret navn</button>'
		. '<button type="button" class="edit" id="undo-name" aria-label="' . (signup_name_from_cvr($row) ? 'Fortryd – brug navnet fra CVR' : 'Fortryd – brug det oprindelige navn') . '"' . (($editing && !$undoHidden) ? '' : ' hidden') . '>Fortryd</button></div>'
		. '<div class="row"><span class="k">Brugernavn</span><span class="v">' . $h($row['email']) . '</span></div>'
		. '<div class="row"><span class="k">Pakke</span><span class="v plain">' . $h($p['name']) . ' · gratis i ' . $h($days) . ' dage</span></div>'
		. '</div>'
		. '<div class="field" id="name-field"' . ($editing ? '' : ' hidden') . '>'
		. '<label for="regnskab">Regnskabsnavn</label>'
		. '<div class="input"><input id="regnskab" name="regnskab" type="text" maxlength="60" value="' . $h($view['name']) . '" autocomplete="organization" aria-describedby="regnskab-help regnskab-status" data-cvr="' . $h($row['regnskab']) . '"' . (($ns === 'taken' || $ns === 'empty') ? ' aria-invalid="true"' : '') . '></div>'
		. '<p class="help" id="regnskab-help">Navnet bruges, når I logger ind. Det kan også ændres senere under Indstillinger.</p>'
		. '<div id="regnskab-status" aria-live="polite">'
		. '<p class="check ok" id="name-ok"' . ($ns === 'ok' ? '' : ' hidden') . '><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5 9-10"/></svg>Navnet er ledigt</p>'
		. '<p class="check" id="name-checking" hidden>Tjekker om navnet er ledigt …</p>'
		. '<p class="check err" id="name-empty"' . ($ns === 'empty' ? '' : ' hidden') . '>Skriv et navn til regnskabet.</p>'
		. '<div class="notice warn" id="name-taken"' . ($ns === 'taken' ? '' : ' hidden') . '><div>Navnet <strong id="taken-name">' . $h($view['name']) . '</strong> er allerede i brug. Vælg et andet<span id="taken-tail">'
		. ($view['suggestion'] !== '' ? ', eller<br><button type="button" class="suggest" id="use-suggestion" data-name="' . $h($view['suggestion']) . '">Brug "' . $h($view['suggestion']) . '"</button>' : '.')
		. '</span></div></div>'
		. '</div></div>'
		. '<div class="field"><label for="pw">Vælg adgangskode</label>'
		. '<div class="input"><input id="pw" name="password" type="password" autocomplete="new-password" minlength="10" maxlength="200" aria-describedby="pw-reqs pw-status" aria-invalid="' . ($pwReason !== '' ? 'true' : 'false') . '" data-state="' . ($pwReason !== '' ? 'error' : '') . '" required>'
		. '<button type="button" class="eye" id="toggle-pw" aria-label="Vis adgangskode" aria-pressed="false"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>'
		. '<ul class="reqs" id="pw-reqs" aria-label="Krav til adgangskoden">'
		. '<li class="req" id="req-len" data-state="' . $reqLen . '"><span class="req-icon" aria-hidden="true"></span><span class="req-text">Mindst 10 tegn</span></li>'
		. '<li class="req" id="req-common" data-state="' . $reqCommon . '"><span class="req-icon" aria-hidden="true"></span><span class="req-text">Ikke en kendt eller almindelig adgangskode</span></li>'
		. '</ul>'
		. '<p class="pw-status" id="pw-status" data-state="' . $pwState . '" aria-live="polite">' . $h($pwText) . '</p>'
		. '</div>'
		. '<button type="submit" class="btn" id="submit">Aktivér og log ind</button>'
		. signup_help_line()
		. '</form>';

	$script = <<<'JS'
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var token = document.querySelector('input[name="t"]').value;
  var CVR = $('regnskab').dataset.cvr, nameTimer, nameReq = 0;
  function nameStatus(st, name, suggestion) {
    clearTimeout(nameTimer);
    $('name-ok').hidden = st !== 'ok'; $('name-checking').hidden = st !== 'checking';
    $('name-empty').hidden = st !== 'empty'; $('name-taken').hidden = st !== 'taken';
    $('regnskab').setAttribute('aria-invalid', st === 'taken' || st === 'empty' ? 'true' : 'false');
    if (st === 'taken') {
      $('taken-name').textContent = name;
      var tail = $('taken-tail');
      tail.textContent = suggestion ? ', eller' : '.';
      if (suggestion) {
        tail.appendChild(document.createElement('br'));
        var b = document.createElement('button'); b.type = 'button'; b.className = 'suggest'; b.id = 'use-suggestion';
        b.dataset.name = suggestion; b.textContent = 'Brug "' + suggestion + '"';
        b.addEventListener('click', function () { setName(this.dataset.name); $('regnskab').focus(); });
        tail.appendChild(b);
      }
    }
  }
  function checkName(name) {
    if (!name.trim()) { return nameStatus('empty'); }
    nameStatus('checking');
    var mine = ++nameReq;
    nameTimer = setTimeout(function () {
      fetch('tjeknavn.php?t=' + encodeURIComponent(token) + '&navn=' + encodeURIComponent(name), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (mine !== nameReq) { return; } if (d.name) { $('name-text').textContent = d.name; } nameStatus(d.available ? 'ok' : 'taken', d.name || name, d.suggestion); })
        .catch(function () { if (mine === nameReq) { nameStatus('ok'); } });
    }, 400);
  }
  function setName(name) { $('regnskab').value = name; $('name-text').textContent = name; checkName(name); }
  $('edit-name').addEventListener('click', function () {
    $('name-field').hidden = false; this.hidden = true; $('undo-name').hidden = false; $('regnskab').focus();
  });
  $('undo-name').addEventListener('click', function () {
    setName(CVR); $('name-field').hidden = true; this.hidden = true; $('edit-name').hidden = false; $('edit-name').focus();
  });
  $('regnskab').addEventListener('input', function () { $('name-text').textContent = this.value; checkName(this.value); });
  var sug = $('use-suggestion'); if (sug) { sug.addEventListener('click', function () { setName(this.dataset.name); $('regnskab').focus(); }); }
  $('toggle-pw').addEventListener('click', function () {
    var on = $('pw').type === 'password'; $('pw').type = on ? 'text' : 'password';
    this.setAttribute('aria-pressed', on ? 'true' : 'false'); this.setAttribute('aria-label', on ? 'Skjul adgangskode' : 'Vis adgangskode');
  });
  var MIN = 10, attempted = $('pw-status').dataset.state === 'error';
  function setReq(id, state) { $(id).dataset.state = state; }
  function setStatus(state, text) { $('pw-status').dataset.state = state; $('pw-status').textContent = text; }
  function validatePw() {
    var v = $('pw').value, len = Array.from(v).length, input = $('pw'), state;
    var lenOk = len >= MIN;
    setReq('req-len', lenOk ? 'ok' : (attempted ? 'error' : 'pending'));
    setReq('req-common', len ? (lenOk ? 'pending' : 'pending') : (attempted ? 'error' : 'pending'));
    if (!len) { state = attempted ? 'error' : 'pending'; setStatus(state, attempted ? 'Vælg en adgangskode for at aktivere regnskabet.' : 'Alle tegn er tilladt – også mellemrum og specialtegn.'); }
    else if (!lenOk) { state = attempted ? 'error' : 'progress'; setStatus(state, (attempted ? 'Adgangskoden er for kort – ' : '') + 'mangler ' + (MIN - len) + ' tegn.'); }
    else { state = 'ok'; setStatus(state, 'Længden er fin. Om adgangskoden er kendt fra datalæk, tjekker vi, når I aktiverer.'); }
    input.dataset.state = state === 'ok' ? 'ok' : (state === 'error' ? 'error' : '');
    input.setAttribute('aria-invalid', state === 'error' ? 'true' : 'false');
    return lenOk;
  }
  $('pw').addEventListener('input', validatePw);
  $('v-aktiver').addEventListener('submit', function (e) {
    attempted = true;
    if (!validatePw()) { e.preventDefault(); $('pw').focus(); return; }
    $('submit').disabled = true; $('submit').innerHTML = '<span class="spinner" aria-hidden="true"></span>Opretter regnskabet …';
  });
})();
JS;
	signup_page('Aktivér jeres regnskab', $content, $script);
}

/**
 * "Regnskabet er klar": the account exists; the customer is posted to index/login.php with the
 * details they just chose (the same auto-login form index/index.php uses for remembered logins).
 *
 * @param array  $row      signup_pending row
 * @param array  $result   from signup_activate()
 * @param string $password the password just chosen (posted to the login, never shown)
 * @param array  $urls     from signup_urls()
 * @return void
 */
function signup_activated_page(array $row, array $result, $password, array $urls) {
	$h = 'signup_h';
	$end = strtotime($result['trial_end']);
	$months = array(1 => 'januar', 'februar', 'marts', 'april', 'maj', 'juni', 'juli', 'august', 'september', 'oktober', 'november', 'december');
	$endTxt = (int)date('j', $end) . '. ' . $months[(int)date('n', $end)] . ' ' . date('Y', $end);
	$login = signup_base_url() . '/index/login.php';
	$content = signup_icon('ok') . '<h1>Regnskabet er klar</h1>'
		. '<p class="lead">Velkommen til Saldi. Jeres prøveperiode løber til <strong>' . $h($endTxt) . '</strong>.</p>'
		. '<form id="login" method="post" action="' . $h($login) . '">'
		. '<input type="hidden" name="regnskab" value="' . $h($result['regnskab']) . '">'
		. '<input type="hidden" name="brugernavn" value="' . $h($row['email']) . '">'
		. '<input type="hidden" name="password" value="' . $h($password) . '">'
		. '<input type="hidden" name="languageId" value="">'
		. '<input type="hidden" name="vent" value="">'
		. '<button type="submit" class="btn" id="go"><span class="spinner" aria-hidden="true"></span>Logger jer ind …</button>'
		. '</form>'
		. '<p class="fine">Sker der ikke noget? <a class="link" href="' . $h($urls['login'] . (strpos($urls['login'], '?') === false ? '?' : '&') . 'regnskab=' . rawurlencode($result['regnskab'])) . '">Gå til login</a></p>';
	$script = "setTimeout(function () { document.getElementById('login').submit(); }, 1200);";
	signup_page('Regnskabet er klar', $content, $script);
}
