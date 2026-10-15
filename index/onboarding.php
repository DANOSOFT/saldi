<?php
// ---- index/onboarding.php --- lap 5.0.0 --- 2026.10.06 ---
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
// 20261006 Sawaneh Onboarding part 1 (Requirements_onboarding_welcome_EN.md): the welcome guide, one page with ?step=,
//                  shown in the shell overlay (index/main.php). Næste saves through the existing logic (company fields
//                  through the settings definitions of Firmaoplysninger, the logo through fe_logo_store(), the colour as
//                  a personal setting, the fiscal year through settings_fiscal_year_set_first()) and marks the step done.
//                  Stage 2: step 4 invoice (bank, payment terms, next invoice number, sender, footer, live preview, logo on
//                  the invoice), step 5 invitations through user_invite() (source 'onboarding'), step 6 summary with a
//                  test invoice printed from a rolled-back order.
// 20261008 Sawaneh Step 3 VAT period values month|quarter|halfyear, as vat.period in G2.2 (settings decision 19).
// 20261008 Sawaneh Spec 2026-10-07 step 6 / §7a: Sally takes over - the import note is replaced by her message, the first
// 20261015 Sawaneh Send en testfaktura shows a spinner and locks the footer while the PDF is made. Text 7098.
//                  Gå til Saldi (once per company, onboarding handover_done) opens SALDI Assist with the hand-over context.

@session_start();
$s_id = session_id();
ob_start();

$title = "Saldi";
$header = 'nix';
$bg = 'nix';
$permission_key = 'any';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once("../systemdata/settingsRegistry.php");
include_once("../includes/settings/SettingsService.php");
include_once("../includes/settings/components.php");
include_once("../includes/settings/actions.php");
include_once("../includes/settings/rowHooks.php");
include_once("../includes/formEditorState.php");
include_once("../includes/onboarding.php");
include_once("../includes/userFunctions.php");

$h = function ($s) {
	return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
};
$tx = function ($t) use ($sprog_id) {
	return is_int($t) ? st_txt($t) : findtekst($t, $sprog_id);
};

if (!onb_can_run()) {
	ob_end_clean();
	print "<!DOCTYPE html><html><head><meta charset='utf-8'></head><body style='font-family:system-ui,sans-serif;padding:2em'>"
		. $h($tx('6756|Opsætningen er ikke færdig – bed administrator om at gøre den færdig.')) . "</body></html>";
	exit;
}

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$def = onb_steps_def();
$step = isset($_REQUEST['step']) ? (string) $_REQUEST['step'] : '';
if (!isset($def[$step]) || !$def[$step][3]) {
	$step = onb_next_step();
	if ($step === '') {
		$step = 'profile';
	}
}

$companyFields = array(
	'cvrnr' => 'company.data.cvr', 'firmanavn' => 'company.data.name', 'addr1' => 'company.data.address1', 'addr2' => 'company.data.address2',
	'postnr' => 'company.data.zip', 'bynavn' => 'company.data.city', 'tlf' => 'company.data.phone', 'email' => 'company.data.email',
);
$invoiceFields = array(
	'bank_reg' => 'company.data.bank_reg', 'bank_konto' => 'company.data.bank_account', 'iban' => 'company.data.iban', 'swift' => 'company.data.swift',
	'payment_terms' => 'company.data.payment_terms', 'payment_days' => 'company.data.payment_days',
);
$systems = array('e-conomic' => 'e-conomic', 'uniconta' => 'Uniconta', 'dinero' => 'Dinero', 'billy' => 'Billy', 'spreadsheet' => '6772|Regneark', 'other' => '6773|Andet');

/**
 * The fiscal-year step is read-only once anything is posted or a second year exists (spec step 3).
 */
function onb_fiscal_locked(): bool
{
	return settings_fy_count("select count(*) as n from grupper where art = 'RA'") > 1 || settings_fy_count("select count(*) as n from transaktioner") > 0;
}

function onb_first_year(): array
{
	$r = db_fetch_array(db_select("select * from grupper where art = 'RA' order by cast(kodenr as integer) limit 1", __FILE__ . " linje " . __LINE__));
	$y = (int) date('Y');
	if (!$r) {
		return array(sprintf('%04d-01', $y), sprintf('%04d-12', $y));
	}
	return array(sprintf('%04d-%02d', (int) $r['box2'], (int) $r['box1']), sprintf('%04d-%02d', (int) $r['box4'], (int) $r['box3']));
}

function onb_company_web(): string
{
	$r = db_fetch_array(db_select("select web from adresser where art = 'S' order by id limit 1", __FILE__ . " linje " . __LINE__));
	return $r ? (string) $r['web'] : '';
}

/**
 * Check posted fields against their settings definitions (the same rules as the settings page) and return the
 * stored values that change. $map is posted name => setting key; fields the user may not change are left out.
 *
 * @return array<string, string> setting key => raw value
 */
function onb_fields_check(array $defs, array $map, array $posted, array &$errors): array
{
	$toSave = array();
	foreach ($map as $name => $key) {
		if (!isset($posted[$name], $defs[$key]) || st_field_access($defs[$key]) !== 'write') {
			continue;
		}
		$res = st_posted_to_raw($defs[$key], $posted[$name], $posted);
		if ($res['error'] !== null) {
			$errors[$name] = st_txt($res['error']);
		} elseif ($res['raw'] !== SettingsService::raw($key)) {
			$toSave[$key] = $res['raw'];
		}
	}
	return $toSave;
}

function onb_fields_save(array $defs, array $toSave): void
{
	transaktion('begin');
	foreach ($toSave as $key => $raw) {
		SettingsService::saveRaw($key, $raw);
	}
	transaktion('commit');
	foreach ($toSave as $key => $raw) {
		if (!empty($defs[$key]['on_save'])) {
			settings_after_save($defs[$key], $raw);
		}
	}
	settings_after_section_save();
}

$errors = array();
$posted = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])) {
		if (function_exists('audit_log')) {
			audit_log('csrf', 'onboarding.php');
		}
		ob_end_clean();
		header('Location: onboarding.php?step=' . rawurlencode($step));
		exit;
	}
	$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
	if ($action === 'hide') {
		onb_set('onboarding_state', 'hidden');
		ob_end_clean();
		header('Location: dashboard.php');
		exit;
	}
	if ($action === 'unhide') {
		$all = count(onb_steps()) >= count(onb_steps_def());
		onb_set('onboarding_state', $all ? 'completed' : 'started');
		ob_end_clean();
		header('Location: ' . (isset($_POST['back']) && $_POST['back'] === 'settings' ? '../systemdata/settings.php' : 'dashboard.php'));
		exit;
	}
	if (onb_get('onboarding_state') === 'new') {
		onb_start((int) $bruger_id);
	}
	foreach ($_POST as $k => $v) {
		if (is_string($v)) {
			$posted[$k] = trim($v);
		}
	}

	if ($action === 'next' && $step === 'profile') {
		$source = isset($posted['source']) && $posted['source'] === 'switch' ? 'switch' : 'new';
		$system = isset($posted['system'], $systems[$posted['system']]) ? $posted['system'] : '';
		onb_set('onboarding_source', $source === 'new' ? 'new' : ($system !== '' ? $system : 'switch'));
		onb_set('onboarding_role', isset($posted['role']) && $posted['role'] === 'accountant' ? 'accountant' : 'owner');
	}

	if ($action === 'next' && $step === 'company') {
		$defs = settings_section_definitions('company.data');
		if (isset($posted['firmanavn']) && $posted['firmanavn'] === '') {
			$errors['firmanavn'] = $tx('6767|Firmanavn skal udfyldes');
		}
		$toSave = onb_fields_check($defs, $companyFields, $posted, $errors);
		$web = isset($posted['web']) ? $posted['web'] : '';
		if (mb_strlen($web) > 60) {
			$errors['web'] = st_txt(6511);
		}
		if (!$errors && isset($_FILES['logo']) && (int) $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
			$res = fe_logo_store($db_id, (string) $_FILES['logo']['tmp_name'], (int) $_FILES['logo']['size']);
			if (!$res['ok']) {
				$errors['logo'] = $res['error'] === 'store' ? $tx('6776|Logoet kunne ikke gemmes. Prøv igen.') : $tx('6765|Logoet skal være en png- eller jpg-fil på højst 5 MB.');
			} else {
				onb_invoice_logo($db_id);
			}
		}
		if (!$errors) {
			onb_fields_save($defs, $toSave);
			if ($web !== onb_company_web()) {
				db_modify("update adresser set web = '" . db_escape_string($web) . "' where art = 'S'", __FILE__ . " linje " . __LINE__);
			}
			$colour = isset($posted['colour']) ? $posted['colour'] : '';
			if ($colour === 'custom') {
				$colour = isset($posted['colour_custom']) ? $posted['colour_custom'] : '';
			}
			$colour = strtolower(ltrim($colour, '#'));
			if ($colour !== '' && $colour !== onb_colour_get((int) $bruger_id) && onb_colour_set((int) $bruger_id, $colour)) {
				$_SESSION['onb_colour_changed'] = 1;
			}
		}
	}

	if ($action === 'next' && $step === 'fiscal') {
		if (!onb_fiscal_locked()) {
			$s = isset($posted['fy_start']) ? $posted['fy_start'] : '';
			$e = isset($posted['fy_end']) ? $posted['fy_end'] : '';
			if (!preg_match('/^(\d{4})-(\d{2})$/', $s, $ms) || !preg_match('/^(\d{4})-(\d{2})$/', $e, $me)) {
				$errors['fy'] = $tx('6766|Ugyldig dato');
			} else {
				$res = settings_fiscal_year_set_first((int) $ms[2], (int) $ms[1], (int) $me[2], (int) $me[1]);
				if ($res['error'] !== null) {
					$errors['fy'] = $res['error_args'] ? vsprintf(st_txt($res['error']), $res['error_args']) : st_txt($res['error']);
				}
			}
		}
		if (!$errors) {
			onb_vat_set(!isset($posted['vat_reg']) || $posted['vat_reg'] !== 'off', isset($posted['vat_period']) ? $posted['vat_period'] : '');
		}
	}

	if ($action === 'next' && $step === 'invoice') {
		$defs = settings_section_definitions('company.data');
		$terms = isset($posted['terms']) ? explode('|', $posted['terms']) : array();
		if (count($terms) === 2) {
			$posted['payment_terms'] = $terms[0];
			$posted['payment_days'] = $terms[1];
		}
		$toSave = onb_fields_check($defs, $invoiceFields, $posted, $errors);
		$mailDefs = settings_section_definitions('documents.email');
		$mailSave = onb_fields_check($mailDefs, array('sender_email' => 'documents.email.sender_email.0'), $posted, $errors);
		$nextNo = onb_next_invoice_check($posted, $errors);
		if (!$errors) {
			onb_fields_save($defs, $toSave);
			onb_fields_save($mailDefs, $mailSave);
			onb_footer_set(isset($posted['footer']) ? $posted['footer'] : '');
			if ($nextNo !== null) {
				onb_next_invoice_set($nextNo);
			}
			onb_invoice_logo($db_id);
			if (isset($posted['then']) && $posted['then'] === 'editor') {
				onb_mark($step, 'done');
				ob_end_clean();
				header('Location: onboarding.php?go=editor');
				exit;
			}
		}
	}

	if ($action === 'next' && $step === 'users' && onb_can_invite()) {
		$invites = array();
		$names = array();
		for ($i = 0; $i < 3; $i++) {
			$n = isset($posted["u_name$i"]) ? $posted["u_name$i"] : '';
			$m = isset($posted["u_email$i"]) ? $posted["u_email$i"] : '';
			$role = isset($posted["u_role$i"]) ? (int) $posted["u_role$i"] : 0;
			if ($n === '' && $m === '') {
				continue;
			}
			$e = user_invite_error($n, $m, $role);
			if ($e === '' && in_array(mb_strtolower($n), $names, true)) {
				$e = 'duplicate';
			}
			if ($e !== '') {
				$msg = array('name' => '5149|Brugernavnet må højst være 80 tegn', 'norole' => '5771|Vælg en rolle først', 'emailrequired' => '5786|En gyldig e-mail er påkrævet for at invitere',
					'duplicate' => '5578|Brugernavnet findes allerede', 'escalation' => '5577|Du kan ikke tildele flere rettigheder, end du selv har');
				$errors["u$i"] = $n === '' ? $tx('6798|Skriv et navn') : $tx($msg[$e]);
			}
			$names[] = mb_strtolower($n);
			$invites[] = array('brugernavn' => $n, 'kode' => '', 'role_id' => $role, 'ansat_id' => 0, 'ip_address' => '', 'tlf' => '', 'email' => $m, 'twofactor' => false, 'regnskabsaar' => (int) $regnaar);
		}
		if (!$errors) {
			$flash = array();
			foreach ($invites as $data) {
				$r = user_invite($data, 'onboarding');
				if (user_invite_mail((int) $r['id'], (string) $r['token'], (int) $sprog_id)) {
					$flash[] = array('ok', sprintf($tx('6799|Invitation sendt til %s'), $data['email']));
				} else {
					$flash[] = array('link', $data['brugernavn'] . ': ' . user_invite_link((string) $r['token']));
				}
			}
			onb_set('onboarding_invited', (string) ((int) onb_get('onboarding_invited') + count($invites)));
			$_SESSION['onb_flash'] = $flash;
		}
	}

	if ($action === 'testinvoice' && $step === 'done') {
		$res = onb_test_invoice();
		$flash = array();
		if (!$res['ok']) {
			$flash[] = array('err', $tx('6800|Testfakturaen kunne ikke laves. Tjek fakturaformularen under Indstillinger.'));
		} else {
			$me = db_fetch_array(db_select("select email from brugere where id = " . (int) $bruger_id, __FILE__ . " linje " . __LINE__));
			$to = $me ? trim((string) $me['email']) : '';
			if ($to !== '' && user_send_mail($to, $tx('6801|Din testfaktura fra Saldi'), '<p>' . user_mail_h($tx('6802|Her er en testfaktura med din fakturaformular. Den er ikke bogført eller gemt.')) . '</p>', $res['file'])) {
				$flash[] = array('ok', sprintf($tx('6803|Testfakturaen er sendt til %s.'), $to));
			}
			$flash[] = array('pdf', $res['url']);
		}
		$_SESSION['onb_flash'] = $flash;
		ob_end_clean();
		header('Location: onboarding.php?step=done');
		exit;
	}

	if (($action === 'next' || $action === 'skip') && !$errors) {
		onb_mark($step, $action === 'next' ? 'done' : 'skipped');
		$nb = onb_neighbours($step);
		// Spec §7a: the first "Gå til Saldi" hands over to Sally, once per company.
		$sally = ($step === 'done' && $action === 'next' && onb_get('handover_done') === '');
		if ($sally) {
			onb_set('handover_done', '1');
		}
		ob_end_clean();
		header('Location: onboarding.php?' . ($nb[1] !== '' ? 'step=' . rawurlencode($nb[1]) : 'closed=1' . ($sally ? '&sally=1' : '')));
		exit;
	}
}

$closed = isset($_GET['closed']);
$colourChanged = !empty($_SESSION['onb_colour_changed']);
if ($closed) {
	unset($_SESSION['onb_colour_changed']);
}

$flash = isset($_SESSION['onb_flash']) ? $_SESSION['onb_flash'] : array();
unset($_SESSION['onb_flash']);
$steps = onb_steps();
$userColour = onb_colour_get((int) $bruger_id);
$logoUrl = onb_logo_url($db_id);
$companyName = SettingsService::raw('company.data.name');
$railKeys = array();
foreach ($def as $k => $d) {
	if ($k !== 'welcome' && $k !== 'done') {
		$railKeys[] = $k;
	}
}
$nb = onb_neighbours($step);
$val = function (string $name, string $current) use ($posted) {
	return isset($posted[$name]) ? $posted[$name] : $current;
};
$err = function (string $name) use ($errors, $h) {
	return isset($errors[$name]) ? "<div class='onb-errors'>" . $h($errors[$name]) . "</div>" : '';
};
$icon = array(
	'new' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
	'switch' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h13l-3-3M20 17H7l3 3"/></svg>',
	'owner' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg>',
	'accountant' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>',
	'wave' => '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12v6a3 3 0 0 1-3 3H9a5 5 0 0 1-5-5V9a1.5 1.5 0 0 1 3 0v4"/><path d="M7 13V5a1.5 1.5 0 0 1 3 0v7"/><path d="M10 12V4a1.5 1.5 0 0 1 3 0v8"/><path d="M13 12V6a1.5 1.5 0 0 1 3 0v6"/><path d="M16 12v-1a1.5 1.5 0 0 1 3 0v1"/></svg>',
	'check' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>',
	'x' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
);

ob_end_clean();
?>
<!DOCTYPE html>
<html class="onb-page" lang="da">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php print $h($tx('6748|Kom godt i gang')); ?></title>
<link rel="stylesheet" href="../css/onboarding.css?v=2">
<script>function onbBusy(b){ var f = b.form; setTimeout(function(){ f.querySelectorAll('button').forEach(function(x){ x.disabled = true; }); b.classList.add('busy'); b.innerHTML = "<span class='onb-spin'></span>" + b.dataset.busy; }, 0); }</script>
<script src="../javascript/jquery-1.8.0.min.js"></script>
</head>
<body>
<script>
function onbClose() {
	var reload = <?php print $colourChanged ? 'true' : 'false'; ?>;
	if (window.parent && window.parent !== window && window.parent.saldiOnboardingClose) {
		window.parent.saldiOnboardingClose(reload);
	} else {
		location.href = 'dashboard.php';
	}
}
function onbGo(url) {
	if (window.parent && window.parent !== window && window.parent.saldiOnboardingGo) {
		window.parent.saldiOnboardingGo(url);
	} else {
		location.href = url;
	}
}
</script>
<?php if ($closed) { ?>
<script><?php if (!empty($_GET['sally'])) { ?>if (window.parent && window.parent !== window && window.parent.saldiAssistHandover) { window.parent.saldiAssistHandover(<?php print json_encode(onb_handover_context(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>); }
<?php } ?>onbClose();</script>
</body></html>
<?php exit; } ?>
<?php if (isset($_GET['go']) && $_GET['go'] === 'editor') { ?>
<script>onbGo('../systemdata/formularkort.php');</script>
</body></html>
<?php exit; } ?>
<div class="onb" style="--brand:#<?php print $h($userColour); ?>">
<form class="onb-modal" method="post" action="onboarding.php" enctype="multipart/form-data" id="onbForm">
	<input type="hidden" name="csrf_token" value="<?php print $h($csrfToken); ?>">
	<input type="hidden" name="step" value="<?php print $h($step); ?>">
	<aside class="onb-rail">
		<div class="onb-rail-logo"><?php
			if ($logoUrl !== '') {
				print "<img src='" . $h($logoUrl) . "' alt='' id='railLogo'>";
			}
			print "<span>" . $h($companyName !== '' ? $companyName : 'SALDI') . "</span><small>" . $h($tx('6699|Opsætning')) . "</small>";
		?></div>
		<?php
		foreach ($railKeys as $i => $k) {
			$st = isset($steps[$k]) ? $steps[$k] : '';
			$cls = $k === $step ? 'on' : ($st === 'done' ? 'done' : ($st === 'skipped' ? 'skip' : ''));
			$mark = ($st === 'done' && $k !== $step) ? $icon['check'] : (string) ($i + 1);
			$label = "<i>$mark</i>" . $h($tx($def[$k][0]));
			if ($def[$k][3]) {
				print "<a class='onb-rs $cls' href='onboarding.php?step=" . $h($k) . "'>$label</a>";
			} else {
				print "<span class='onb-rs $cls' title='" . $h($tx('6797|Bed din administrator om at invitere kolleger')) . "'>$label</span>";
			}
		}
		?>
		<div class="onb-rail-foot"><?php print $h($tx('6700|Du kan altid lukke og fortsætte senere. Intet går tabt.')); ?></div>
	</aside>
	<div class="onb-pane">
		<div class="onb-head">
			<span class="n"><?php
				$pos = array_search($step, $railKeys, true);
				if ($pos !== false && $step !== 'done') {
					print $h(sprintf($tx('6701|Trin %s af %s'), $pos + 1, count($railKeys)));
				}
			?></span>
			<button type="button" class="onb-x" title="<?php print $h($tx('6702|Luk og fortsæt senere')); ?>" onclick="onbClose()"><?php print $icon['x']; ?></button>
		</div>
		<div class="onb-body">
<?php
if ($step === 'welcome') {
	print "<div class='hero'><div class='mark'>{$icon['wave']}</div>";
	print "<h2>" . $h($tx('6707|Hej, og velkommen til Saldi')) . "</h2>";
	print "<p>" . $h($tx('6708|Lad os få dig godt og trygt i gang, så du får en god oplevelse. Det tager ca. 10 minutter, og du kan til enhver tid lukke og fortsætte senere.')) . "</p>";
	print "<div class='chips'><span class='chip'>🏢 " . $h($tx('6709|Dit firma og logo')) . "</span><span class='chip'>📅 " . $h($tx('6691|Regnskabsår og moms')) . "</span>"
		. "<span class='chip'>🧾 " . $h($tx('6693|Din faktura')) . "</span><span class='chip'>👥 " . $h($tx('6710|Dine kolleger')) . "</span></div></div>";
}

if ($step === 'profile') {
	$src = onb_get('onboarding_source');
	$source = $val('source', ($src === '' || $src === 'new') ? 'new' : 'switch');
	$system = $val('system', isset($systems[$src]) ? $src : '');
	$role = $val('role', onb_get('onboarding_role') === 'accountant' ? 'accountant' : 'owner');
	print "<h2>" . $h($tx('6687|Hvem er du?')) . "</h2><p class='lead'>" . $h($tx('6711|To hurtige spørgsmål, så guiden kan tilpasse sig dig.')) . "</p>";
	print "<label class='l'>" . $h($tx('6712|Din situation')) . "</label><div class='cards'>";
	foreach (array('new' => array('6713|Ny virksomhed', '6714|Vi starter fra en ren tavle med Saldis standardopsætning.'),
		'switch' => array('6715|Skifter fra et andet system', '6716|Vi hjælper med nummerserier og, i næste del, import af dine data.')) as $k => $t) {
		print "<label class='card'><input type='radio' name='source' value='$k'" . ($source === $k ? ' checked' : '') . " onchange='onbSource()'><div class='ic'>{$icon[$k]}</div><div><b>" . $h($tx($t[0])) . "</b><span>" . $h($tx($t[1])) . "</span></div></label>";
	}
	print "</div><div class='f' id='systemBox' style='max-width:340px" . ($source === 'switch' ? '' : ';display:none') . "'><label class='l'>" . $h($tx('6717|Hvilket system kommer du fra?')) . "</label><select name='system'><option value=''>–</option>";
	foreach ($systems as $k => $t) {
		print "<option value='" . $h($k) . "'" . ($system === $k ? ' selected' : '') . ">" . $h(strpos($t, '|') ? $tx($t) : $t) . "</option>";
	}
	print "</select></div>";
	print "<label class='l'>" . $h($tx('6718|Din rolle')) . "</label><div class='cards'>";
	foreach (array('owner' => array('6719|Jeg driver virksomheden', '6720|Vi foreslår at invitere din bogholder senere.'),
		'accountant' => array('6721|Jeg er bogholder/revisor for virksomheden', '6722|Vi foreslår at invitere ejeren senere.')) as $k => $t) {
		print "<label class='card'><input type='radio' name='role' value='$k'" . ($role === $k ? ' checked' : '') . "><div class='ic'>{$icon[$k]}</div><div><b>" . $h($tx($t[0])) . "</b><span>" . $h($tx($t[1])) . "</span></div></label>";
	}
	print "</div>";
}

if ($step === 'company') {
	$defs = settings_section_definitions('company.data');
	print "<h2>" . $h($tx('6723|Din virksomhed og dit udtryk')) . "</h2><p class='lead'>" . $h($tx('6724|Slå dit CVR-nummer op, så udfylder vi det meste. Du kan rette alt.')) . "</p>";
	print "<div class='g2'><div>";
	$field = function (string $name, string $type = 'text') use ($companyFields, $defs, $val, $err, $h) {
		$key = $companyFields[$name];
		$d = $defs[$key];
		$ro = st_field_access($d) !== 'write' ? ' readonly' : '';
		$max = !empty($d['maxlength']) ? " maxlength='" . (int) $d['maxlength'] . "'" : '';
		return "<div class='f'><label class='l'>" . $h(st_txt($d['label'])) . "</label><input type='$type' name='$name' value='" . $h($val($name, SettingsService::raw($key))) . "'$max$ro>" . $err($name) . "</div>";
	};
	$cvr = $val('cvrnr', SettingsService::raw('company.data.cvr'));
	print "<div class='f'><label class='l'>" . $h(st_txt($defs['company.data.cvr']['label'])) . "</label><div style='display:flex;gap:8px'><input type='text' name='cvrnr' value='" . $h($cvr) . "' maxlength='15' placeholder='12345678'>"
		. "<button type='button' onclick='onbCvr()'>" . $h($tx('6725|Slå op')) . "</button></div><div class='hint'>" . $h($tx('6726|Henter navn og adresse fra CVR-registret.')) . "</div>" . $err('cvrnr') . "</div>";
	print $field('firmanavn') . $field('addr1') . $field('addr2');
	print "<div class='g2' style='gap:10px'>" . $field('postnr') . $field('bynavn') . "</div>";
	print "<div class='g2' style='gap:10px'>" . $field('tlf') . $field('email', 'email') . "</div>";
	print "<div class='f'><label class='l'>" . $h($tx('367|Hjemmeside')) . "</label><input type='text' name='web' maxlength='60' value='" . $h($val('web', onb_company_web())) . "'>" . $err('web') . "</div>";
	print "</div><div>";
	print "<div class='f'><label class='l'>Logo</label><label class='logo-drop'><img id='logoPrev' src='" . $h($logoUrl) . "' alt=''" . ($logoUrl === '' ? " style='display:none'" : '') . ">"
		. $h($tx('6727|Træk dit logo hertil eller klik for at vælge')) . "<br><span style='font-size:11px;opacity:.7'>" . $h($tx('6728|png eller jpg')) . "</span>"
		. "<input type='file' name='logo' accept='image/png,image/jpeg' style='display:none' onchange='onbLogo(this)'></label>" . $err('logo') . "</div>";
	$colour = $val('colour', $userColour);
	$preset = in_array($colour, onb_colours(), true);
	print "<div class='f'><label class='l'>" . $h($tx('6729|Farve')) . "</label><div class='swatches'>";
	foreach (onb_colours() as $c) {
		print "<label class='sw' style='background:#$c'><input type='radio' name='colour' value='$c'" . ($colour === $c ? ' checked' : '') . " onchange='onbBrand(\"#$c\")'></label>";
	}
	print "<label style='margin:0 0 0 4px;font-weight:500;color:var(--onb-mut);cursor:pointer'><input type='radio' name='colour' value='custom' id='colourCustom' style='display:none'" . ($preset ? '' : ' checked') . ">"
		. $h($tx('6730|Egen farve')) . " <input type='color' name='colour_custom' value='#" . $h(preg_match('/^[0-9a-f]{6}$/', ltrim($colour, '#')) ? ltrim($colour, '#') : $userColour) . "' oninput='document.getElementById(\"colourCustom\").checked=true;onbBrand(this.value)' style='width:26px;height:26px;border:none;background:none;vertical-align:middle;cursor:pointer;padding:0'></label>";
	print "</div><div class='hint'>" . $h($tx('6731|Farven bruges i menuen og på knapperne. Du ser det med det samme.')) . "</div></div>";
	print "<div class='note'>" . $h($tx('6732|Logo og farve kan altid ændres under Indstillinger.')) . "</div>";
	print "</div></div>";
}

if ($step === 'fiscal') {
	$locked = onb_fiscal_locked();
	$fy = onb_first_year();
	$vat = onb_vat_get();
	$vatReg = $val('vat_reg', $vat['registered'] ? 'on' : 'off');
	$vatPeriod = $val('vat_period', $vat['period']);
	print "<h2>" . $h($tx('6691|Regnskabsår og moms')) . "</h2><p class='lead'>" . $h($tx('6733|De to ting, der er besværlige at ændre, når du først har bogført. Tag dem nu, hvis du kan.')) . "</p>";
	print "<div class='g2'><div>";
	if ($locked) {
		print "<div class='note'>" . $h($tx('6746|Regnskabet har allerede et regnskabsår med posteringer. Det ændres under Regnskabsår.')) . "<br><br>"
			. "<button type='button' onclick=\"onbGo('../systemdata/settingsSection.php?s=company.fiscal_years')\">" . $h($tx('6747|Åbn Regnskabsår')) . " &rarr;</button></div>";
	} else {
		print "<div class='g2' style='gap:10px'><div class='f'><label class='l'>" . $h($tx('6734|Regnskabsåret starter')) . "</label><input type='month' name='fy_start' id='fyStart' value='" . $h($val('fy_start', $fy[0])) . "' onchange='onbFy()'></div>"
			. "<div class='f'><label class='l'>" . $h($tx('6735|Regnskabsåret slutter')) . "</label><input type='month' name='fy_end' id='fyEnd' value='" . $h($val('fy_end', $fy[1])) . "' onchange='this.dataset.touched=1'></div></div>" . $err('fy');
	}
	print "<div class='f'><label class='l'>" . $h($tx('6736|Er virksomheden momsregistreret?')) . "</label><div class='seg'>"
		. "<label><input type='radio' name='vat_reg' value='on'" . ($vatReg !== 'off' ? ' checked' : '') . " onchange='onbVat()'>" . $h($tx('6737|Ja')) . "</label>"
		. "<label><input type='radio' name='vat_reg' value='off'" . ($vatReg === 'off' ? ' checked' : '') . " onchange='onbVat()'>" . $h($tx('6738|Nej')) . "</label></div></div>";
	print "<div class='f' id='vatBox'" . ($vatReg === 'off' ? " style='display:none'" : '') . "><label class='l'>" . $h($tx('6739|Momsperiode')) . "</label><select name='vat_period'>";
	foreach (array('month' => '6740|Månedlig', 'quarter' => '6741|Kvartalsvis', 'halfyear' => '6742|Halvårlig') as $k => $t) {
		print "<option value='$k'" . ($vatPeriod === $k ? ' selected' : '') . ">" . $h($tx($t)) . "</option>";
	}
	print "</select><div class='hint'>" . $h($tx('6743|Står på din registreringsbekræftelse fra Skattestyrelsen.')) . "</div></div>";
	print "</div><div><div class='note warn' style='margin-top:22px'><b>" . $h($tx('6744|Hvorfor nu?')) . "</b><br>"
		. $h($tx('6745|Regnskabsår og momsperiode styrer, hvordan alle posteringer grupperes. Springer du over, markerer tjeklisten trinnet som Vigtigt.')) . "</div></div></div>";
}

if ($step === 'invoice') {
	$cd = function ($k) {
		return SettingsService::raw('company.data.' . $k);
	};
	$src = onb_get('onboarding_source');
	$curTerms = $cd('payment_terms') !== '' ? $cd('payment_terms') : 'Netto';
	$curDays = ($cd('payment_terms') !== '' && (int) $cd('payment_days') > 0) ? (int) $cd('payment_days') : 8;
	$presets = array('Netto|8', 'Netto|14', 'Netto|30', 'Lb. md.|15', 'Kontant|0');
	$current = in_array($curTerms, array('Netto', 'Lb. md.'), true) ? $curTerms . '|' . $curDays : $curTerms . '|0';
	if (!in_array($current, $presets, true)) {
		array_unshift($presets, $current);
	}
	$termLabel = function (string $v) use ($tx) {
		list($b, $d) = explode('|', $v);
		if ($b === 'Netto') return sprintf($tx('6781|Netto %s dage'), $d);
		if ($b === 'Lb. md.') return sprintf($tx('6782|Løbende måned + %s dage'), $d);
		$names = array('Kontant' => '370|Kontant', 'Forud' => '369|Forud', 'Efterkrav' => '371|Efterkrav');
		return isset($names[$b]) ? $tx($names[$b]) : $b;
	};
	$terms = $val('terms', $current);
	$mailDefs = settings_section_definitions('documents.email');
	$sender = isset($mailDefs['documents.email.sender_email.0']) ? SettingsService::raw('documents.email.sender_email.0') : '';
	print "<h2>" . $h($tx('6693|Din faktura')) . "</h2><p class='lead'>" . $h($tx('6786|Det, en faktura skal indeholde for at være korrekt. Layoutet kan tilpasses senere i formulareditoren.')) . "</p>";
	print "<div class='g2' style='grid-template-columns:1fr 1.05fr;gap:12px 26px'><div>";
	print "<div class='g2' style='gap:10px'><div class='f'><label class='l'>" . $h($tx('2227|Reg. nr.')) . "</label><input type='text' name='bank_reg' maxlength='15' placeholder='1234' value='" . $h($val('bank_reg', $cd('bank_reg'))) . "' oninput='onbInv()'>" . $err('bank_reg') . "</div>"
		. "<div class='f'><label class='l'>" . $h($tx('43|Kontonr.')) . "</label><input type='text' name='bank_konto' maxlength='15' placeholder='1234567890' value='" . $h($val('bank_konto', $cd('bank_account'))) . "' oninput='onbInv()'>" . $err('bank_konto') . "</div></div>";
	$abroad = $val('iban', $cd('iban')) !== '' || $val('swift', $cd('swift')) !== '' || isset($errors['iban']) || isset($errors['swift']);
	print "<details class='more'" . ($abroad ? ' open' : '') . "><summary>" . $h($tx('6787|Sælger du til udlandet?')) . "</summary><div class='g2' style='gap:10px'>"
		. "<div class='f'><label class='l'>IBAN</label><input type='text' name='iban' maxlength='40' value='" . $h($val('iban', $cd('iban'))) . "' oninput='onbInv()'>" . $err('iban') . "</div>"
		. "<div class='f'><label class='l'>" . $h($tx('2228|SWIFT nr.')) . "</label><input type='text' name='swift' maxlength='15' value='" . $h($val('swift', $cd('swift'))) . "' oninput='onbInv()'>" . $err('swift') . "</div></div></details>";
	print "<div class='f'><label class='l'>" . $h($tx('6788|Standard betalingsbetingelse')) . "</label><select name='terms' onchange='onbInv()'>";
	foreach ($presets as $p) {
		print "<option value='" . $h($p) . "'" . ($terms === $p ? ' selected' : '') . ">" . $h($termLabel($p)) . "</option>";
	}
	print "</select><div class='hint'>" . $h($tx('6779|Bruges som standard på nye kunder. Kan ændres pr. kunde.')) . "</div>" . $err('payment_days') . "</div>";
	if ($src !== '' && $src !== 'new') {
		$sysName = isset($systems[$src]) ? (strpos($systems[$src], '|') ? $tx($systems[$src]) : $systems[$src]) : $tx('6791|det gamle system');
		print "<div class='f'><label class='l'>" . $h($tx('6789|Næste fakturanummer')) . "</label><input type='text' name='next_invoice' inputmode='numeric' placeholder='10482' value='" . $h($val('next_invoice', '')) . "' oninput='onbInv()'>"
			. "<div class='hint'>" . $h(sprintf($tx('6790|Fortsæt din nummerrække fra %s.'), $sysName)) . "</div>" . $err('next_invoice') . "</div>";
	}
	print "<div class='f'><label class='l'>" . $h($tx('6172|Afsender-e-mail')) . "</label><input type='email' name='sender_email' maxlength='60' placeholder='faktura@ditfirma.dk' value='" . $h($val('sender_email', $sender)) . "'>"
		. "<div class='hint'>" . $h($tx('6783|Egen SMTP-server sættes op under Indstillinger → E-mail.')) . "</div>" . $err('sender_email') . "</div>";
	print "<div class='f'><label class='l'>" . $h($tx('6784|Bundtekst (valgfri)')) . "</label><input type='text' name='footer' maxlength='120' placeholder='" . $h($tx('6795|Tak for handlen')) . "' value='" . $h($val('footer', onb_footer_get())) . "' oninput='onbInv()'></div>";
	print "<button type='submit' class='onb-link' name='then' value='editor' onclick=\"document.getElementById('onbAction').value='next'\">" . $h($tx('6785|Tilpas layout i formulareditoren')) . " &rarr;</button>";
	print "</div><div><div class='prev'><div class='cap'><span>" . $h($tx('3276|Forhåndsvisning')) . "</span><i>&#9679; " . $h($tx('6792|Opdateres live')) . "</i></div><div class='inv'>";
	$name = $cd('name') !== '' ? $cd('name') : 'SALDI';
	print "<div class='top'><div>" . ($logoUrl !== '' ? "<img src='" . $h($logoUrl) . "' alt=''>" : "<b style='font-size:15px;color:var(--brand)'>" . $h($name) . "</b>") . "</div>"
		. "<div class='co'>" . $h($name) . "<br>" . $h($cd('address1')) . "<br>" . $h(trim($cd('zip') . ' ' . $cd('city'))) . "<br>CVR " . $h($cd('cvr')) . "</div></div>";
	print "<h4>" . $h($tx('643|Faktura')) . " <span id='pvNo'>1</span></h4><div style='color:#555'>" . $h($tx('6793|Kunde A/S')) . " &middot; " . date('d-m-Y') . " &middot; <span id='pvTerms'></span></div>";
	print "<table><tr><th>" . $h($tx('914|Beskrivelse')) . "</th><th>" . $h($tx('916|Antal')) . "</th><th style='text-align:right'>" . $h($tx('934|Beløb')) . "</th></tr>"
		. "<tr><td>" . $h($tx('6794|Konsulentydelse')) . "</td><td>10</td><td style='text-align:right'>10.000,00</td></tr>"
		. "<tr><td>" . $h($tx('770|Moms')) . " 25 %</td><td></td><td style='text-align:right'>2.500,00</td></tr></table>";
	print "<div class='tot'>" . $h($tx('2373|I alt')) . " DKK 12.500,00</div>";
	print "<div class='pay'>" . $h($tx('935|Betaling')) . ": <span id='pvTerms2'></span><br>" . $h($tx('662|Bank')) . ": " . $h($tx('2227|Reg. nr.')) . " <span id='pvReg'></span> &middot; " . $h($tx('43|Kontonr.')) . " <span id='pvKonto'></span><span id='pvIban'></span></div>";
	print "<div class='foot' id='pvFoot'></div></div></div></div></div>";
}

foreach ($flash as $f) {
	if ($f[0] === 'ok') {
		print "<div class='flash'>" . $h($f[1]) . "</div>";
	} elseif ($f[0] === 'link') {
		print "<div class='onb-errors'>" . $h($tx('5783|E-mailen kunne ikke sendes. Send dette link til brugeren.')) . "<br><code style='word-break:break-all'>" . $h($f[1]) . "</code></div>";
	} elseif ($f[0] === 'pdf') {
		print "<div class='flash'><a class='onb-link' href='" . $h($f[1]) . "' target='_blank' rel='noopener'>" . $h($tx('6804|Åbn testfakturaen (PDF)')) . " &rarr;</a></div>";
	} else {
		print "<div class='onb-errors'>" . $h($f[1]) . "</div>";
	}
}

if ($step === 'users') {
	$owner = onb_get('onboarding_role') !== 'accountant';
	$suggest = $owner ? array('bogholder', 'revisor', 'salg') : array('administrator', 'salg', 'kunvisning');
	$roles = function_exists('perm_roles') ? perm_roles() : array();
	print "<h2>" . $h($tx('6695|Inviter kolleger')) . "</h2><p class='lead'>" . $h($owner ? $tx('6805|Har du en bogholder eller revisor? Inviter dem nu, så de kan hjælpe med resten.') : $tx('6806|Inviter ejeren, så de kan følge med og godkende.')) . " " . $h($tx('6807|De får en mail og vælger selv kodeord.')) . "</p>";
	print "<div class='userrow head'><span>" . $h($tx('6808|Navn')) . "</span><span>" . $h($tx('52|E-mail')) . "</span><span>" . $h($tx('6809|Rolle')) . "</span></div>";
	for ($i = 0; $i < 3; $i++) {
		$def = function_exists('perm_role_id_by_key') ? perm_role_id_by_key($suggest[$i]) : 0;
		$sel = (int) $val("u_role$i", (string) $def);
		$ph = $i === 0 ? ($owner ? $tx('6810|Din bogholder') : $tx('6811|Ejeren')) : '';
		print "<div class='userrow'><input type='text' name='u_name$i' maxlength='80' placeholder='" . $h($ph) . "' value='" . $h($val("u_name$i", '')) . "'>"
			. "<input type='email' name='u_email$i' maxlength='60' placeholder='" . ($i === 0 ? 'navn@firma.dk' : '') . "' value='" . $h($val("u_email$i", '')) . "'><select name='u_role$i'>";
		foreach ($roles as $role) {
			$ok = perm_within_own(perm_levels_from_role((int) $role['id']));
			print "<option value='" . (int) $role['id'] . "'" . ($sel === (int) $role['id'] ? ' selected' : '') . ($ok ? '' : ' disabled') . ">" . $h(perm_role_name($role, (int) $sprog_id)) . "</option>";
		}
		print "</select></div>" . $err("u$i");
	}
	print "<div class='hint' style='margin-top:2px'>" . $h($tx('6812|Bogholder = finans, kassekladde og rapporter. Revisor = kun læseadgang. Administrator = alt, inkl. brugere.')) . "</div>";
	print "<div class='note'>" . $h($tx('6813|Flere brugere, egne roller og finjustering under Indstillinger → Brugere & roller. Du kan ikke give en rolle med flere rettigheder end din egen.')) . "</div>";
}

if ($step === 'done') {
	$src = onb_get('onboarding_source');
	print "<h2>" . $h($tx('6814|Sådan, du er i gang')) . " 🎉</h2><p class='lead'>" . $h($tx('6815|Her er, hvad vi har sat op. Alt kan ændres under Indstillinger.')) . "</p><ul class='sum'>";
	foreach ($railKeys as $k) {
		$st = isset($steps[$k]) ? $steps[$k] : '';
		$ok = $st === 'done';
		print "<li><span><span class='onb-st" . ($ok ? ' done' : '') . "'>" . ($ok ? $icon['check'] : '') . "</span>" . $h($tx($def[$k][0])) . "</span>";
		if ($ok) {
			print "<span class='ok'>" . $h($tx('6697|Færdig')) . "</span>";
		} elseif ($def[$k][3]) {
			print "<a href='onboarding.php?step=" . $h($k) . "'>" . $h($tx('6816|Fortsæt')) . "</a>";
		} else {
			print "<span class='hint'>" . $h($tx('6797|Bed din administrator om at invitere kolleger')) . "</span>";
		}
		print "</li>";
	}
	print "</ul>";
	// Spec 2026-10-07 §5 step 6 / §7a: Sally takes over from here.
	$sysName = ($src !== '' && $src !== 'new') ? (isset($systems[$src]) ? (strpos($systems[$src], '|') ? $tx($systems[$src]) : $systems[$src]) : $tx('6791|det gamle system')) : '';
	print "<div class='note' style='margin-top:18px;max-width:540px'><b>" . $h($tx('6912|Sally hjælper dig videre herfra')) . "</b><br>"
		. $h($sysName !== '' ? sprintf($tx('6913|Sally er Saldis assistent (knappen SALDI Assist øverst). Hun hjælper dig med at hente dine data fra %s og viser dig rundt i Saldi.'), $sysName)
			: $tx('6914|Sally er Saldis assistent (knappen SALDI Assist øverst). Hun viser dig rundt i Saldi og hjælper, når du går i stå.')) . "</div>";
}
?>
		</div>
		<div class="onb-foot">
			<input type="hidden" name="action" id="onbAction" value="next">
<?php
if ($step === 'welcome') {
	print "<span class='sp'></span><button type='button' class='ghost' onclick='onbClose()'>" . $h($tx('6702|Luk og fortsæt senere')) . "</button>"
		. "<button type='submit' class='pri'>" . $h($tx('6703|Kom i gang')) . " &rarr;</button>";
} elseif ($step === 'done') {
	print "<button type='button' class='ghost' onclick=\"location.href='onboarding.php?step=" . $h($nb[0]) . "'\">&larr; " . $h($tx('6704|Tilbage')) . "</button><span class='sp'></span>"
		. "<button type='submit' formnovalidate id='onbTestInvoice' data-busy=\"" . $h($tx('7098|Laver testfaktura …')) . "\" onclick=\"document.getElementById('onbAction').value='testinvoice'; onbBusy(this);\">" . $h($tx('6819|Send en testfaktura til mig')) . "</button>"
		. "<button type='submit' class='pri' onclick=\"document.getElementById('onbAction').value='next'\">" . $h($tx('6820|Gå til Saldi')) . " &rarr;</button>";
} else {
	$back = $nb[0] !== '' && $nb[0] !== 'welcome' ? "onclick=\"location.href='onboarding.php?step=" . $h($nb[0]) . "'\"" : 'disabled';
	print "<button type='button' class='ghost' $back>&larr; " . $h($tx('6704|Tilbage')) . "</button><span class='sp'></span>"
		. "<button type='submit' class='ghost' formnovalidate onclick=\"document.getElementById('onbAction').value='skip'\">" . $h($tx('6705|Spring over')) . "</button>"
		. "<button type='submit' class='pri' onclick=\"document.getElementById('onbAction').value='next'\">" . $h($tx('6706|Næste')) . " &rarr;</button>";
}
?>
		</div>
	</div>
</form>
</div>
<script>
function onbBrand(c) { document.querySelector('.onb').style.setProperty('--brand', c); }
function onbSource() {
	var sw = document.querySelector('input[name=source][value=switch]');
	document.getElementById('systemBox').style.display = sw && sw.checked ? '' : 'none';
}
function onbVat() {
	var off = document.querySelector('input[name=vat_reg][value=off]');
	document.getElementById('vatBox').style.display = off && off.checked ? 'none' : '';
}
function onbFy() {
	var s = document.getElementById('fyStart'), e = document.getElementById('fyEnd');
	var m = /^(\d{4})-(\d{2})$/.exec(s.value);
	if (!m || e.dataset.touched) return;
	var y = parseInt(m[1], 10), mo = parseInt(m[2], 10) - 1;
	if (mo === 0) { mo = 12; } else { y += 1; }
	e.value = y + '-' + (mo < 10 ? '0' : '') + mo;
}
function onbLogo(input) {
	if (!input.files || !input.files[0]) return;
	var r = new FileReader();
	r.onload = function (ev) {
		var img = document.getElementById('logoPrev');
		img.src = ev.target.result; img.style.display = '';
		var rail = document.getElementById('railLogo');
		if (rail) rail.src = ev.target.result;
	};
	r.readAsDataURL(input.files[0]);
}
function onbInv() {
	var f = document.getElementById('onbForm');
	if (!f || !f.terms) return;
	var v = function (n) { return f[n] ? f[n].value.trim() : ''; };
	var sel = f.terms.options[f.terms.selectedIndex];
	var terms = sel ? sel.text : '';
	document.getElementById('pvTerms').textContent = terms;
	document.getElementById('pvTerms2').textContent = terms;
	document.getElementById('pvReg').textContent = v('bank_reg') || '____';
	document.getElementById('pvKonto').textContent = v('bank_konto') || '__________';
	document.getElementById('pvIban').textContent = v('iban') ? ' · IBAN ' + v('iban') + (v('swift') ? ' · SWIFT ' + v('swift') : '') : '';
	document.getElementById('pvNo').textContent = v('next_invoice') || '1';
	document.getElementById('pvFoot').textContent = v('footer');
}
onbInv();
<?php if ($step === 'company') { ?>
var cvrLookupProxy = '../sager/cvrLookupProxy.php';
var cvrAutoFelter = ['cvrnr'];
var cvrTekster = <?php print json_encode(array(
	'fejl' => findtekst('3374|CVR-opslaget kunne ikke gennemføres. Udfyld felterne manuelt.', $sprog_id),
	'QUOTA_EXCEEDED' => findtekst('3375|Kvoten for CVR-opslag er opbrugt.', $sprog_id),
	'NOT_FOUND' => findtekst('3376|CVR-nummeret blev ikke fundet.', $sprog_id),
	'INVALID_VAT' => findtekst('3377|CVR-nummeret er ikke gyldigt.', $sprog_id),
	'soeger' => findtekst('3378|Søger...', $sprog_id),
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
function onbCvr() {
	var f = $('[name=cvrnr]');
	var v = (f.val() || '').replace(/\D/g, '');
	if (v.length !== 8) { cvrFejl(f, 'INVALID_VAT'); return; }
	f.val(v);
	cvrSidste = null;
	cvrOpslag(f, v, 'vat');
}
<?php } ?>
</script>
<?php if ($step === 'company') { ?>
<script src="../javascript/cvrapiopslag.js"></script>
<?php } ?>
</body>
</html>
