<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/einvoiceProvider.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. Indstillinger > Diverse > E-faktura (sektion einvoice_valg): the regnskab's e-invoice provider, settings provider/einvoice = 'easyubl' or 'semantic'.
//                 Shown only when the server has Semantic set up and SALDI_SEMANTIC_PROVIDER_CHOICE is on; saved only with Indstillinger rights and the page's CSRF token.
//                 Semantic can not be chosen while the regnskab is registered at EasyUBL (semantic_onboarding_decision()).

include_once(__DIR__ . '/../../includes/einvoice/semanticClient.php');
include_once(__DIR__ . '/../../includes/einvoice/semanticOnboarding.php');

if (!function_exists('einvoice_provider_options')) {
	/**
	 * The values the setting provider/einvoice may have. The first is the default.
	 *
	 * @return list<string>
	 */
	function einvoice_provider_options() {
		return array('easyubl', 'semantic');
	}
}

if (!function_exists('einvoice_provider_normalize')) {
	/**
	 * A posted or stored provider value, whitelisted.
	 *
	 * @param mixed $value
	 * @return string|null One of einvoice_provider_options(), or null for anything else.
	 */
	function einvoice_provider_normalize($value) {
		if (!is_string($value)) {
			return null;
		}
		$value = strtolower(trim($value));
		return in_array($value, einvoice_provider_options(), true) ? $value : null;
	}
}

if (!function_exists('einvoice_provider_flag_on')) {
	/**
	 * Whether the server flag SALDI_SEMANTIC_PROVIDER_CHOICE says on.
	 *
	 * @param string|null $value semantic_env('SALDI_SEMANTIC_PROVIDER_CHOICE').
	 * @return bool
	 */
	function einvoice_provider_flag_on($value) {
		return is_string($value) && in_array(strtolower(trim($value)), array('1', 'on', 'yes', 'true'), true);
	}
}

if (!function_exists('einvoice_provider_choice_visible')) {
	/**
	 * Whether this server shows the provider choice at all: Semantic must be set up (semantic_credentials(); nothing
	 * of it is shown) and the launch flag SALDI_SEMANTIC_PROVIDER_CHOICE must be on, so customers do not see the
	 * choice on a server that only has the connection for tests.
	 *
	 * @param string|null $flag        Null = semantic_env('SALDI_SEMANTIC_PROVIDER_CHOICE').
	 * @param bool|null   $configured  Null = semantic_credentials() !== null.
	 * @return bool
	 */
	function einvoice_provider_choice_visible($flag = null, $configured = null) {
		$flag = ($flag !== null) ? $flag : semantic_env('SALDI_SEMANTIC_PROVIDER_CHOICE');
		if (!einvoice_provider_flag_on($flag)) {
			return false;
		}
		$configured = ($configured !== null) ? (bool)$configured : (semantic_credentials() !== null);
		return $configured;
	}
}

if (!function_exists('einvoice_provider_can_edit')) {
	/**
	 * Whether the user may change the setting: the Indstillinger right (position 1 of online.rettigheder, the module
	 * systemdata/diverse.php runs as). Stricter than online.php, which lets an empty rights string through.
	 *
	 * @param mixed $rettigheder
	 * @return bool
	 */
	function einvoice_provider_can_edit($rettigheder) {
		return is_string($rettigheder) && substr($rettigheder, 1, 1) === '1';
	}
}

if (!function_exists('einvoice_provider_state')) {
	/**
	 * Where the regnskab is registered, and whether Semantic may be chosen (the check semantic_ensure_sender() makes
	 * before onboarding: a regnskab registered at EasyUBL, or a copy of a regnskab, is refused).
	 *
	 * @param array<string, string> $semanticSettings From semantic_settings_read().
	 * @param string                $easyUblCompanyId settings companyID/easyUBL ('' when none).
	 * @param string                $db               The current database.
	 * @return array{easyUbl: bool, semantic: bool, semanticAllowed: bool, reason: string} reason from
	 *   semantic_onboarding_decision() when Semantic is refused ('registered_elsewhere', 'copy', 'db_unknown'), else ''.
	 */
	function einvoice_provider_state(array $semanticSettings, $easyUblCompanyId, $db) {
		$easyUblCompanyId = trim((string)$easyUblCompanyId);
		$decision = semantic_onboarding_decision($semanticSettings, $easyUblCompanyId, (string)$db);
		$refused = ($decision['action'] === 'refuse');
		return array(
			'easyUbl' => $easyUblCompanyId !== '' && $easyUblCompanyId !== '00000000-0000-0000-0000-000000000000',
			'semantic' => trim((string)($semanticSettings['companyId'] ?? '')) !== '',
			'semanticAllowed' => !$refused,
			'reason' => $refused ? (string)$decision['reason'] : '',
		);
	}
}

if (!function_exists('einvoice_provider_save_decision')) {
	/**
	 * Whether a posted choice is saved. Pure, so every guard is tested without a database.
	 *
	 * @param array{
	 *   visible: bool,          einvoice_provider_choice_visible().
	 *   rettigheder: mixed,     online.rettigheder of the user.
	 *   sessionToken: mixed,    $_SESSION['csrf_token'].
	 *   postedToken: mixed,     $_POST['csrf_token'].
	 *   posted: mixed,          $_POST['einvoiceProvider'].
	 *   current: string,        einvoice_provider_current().
	 *   state: array{semanticAllowed: bool, reason: string}, einvoice_provider_state().
	 * } $request
	 * @return array{save: bool, value: string|null, reason: string} reason 'hidden', 'no_rights', 'csrf', 'invalid',
	 *   'unchanged', 'refused_<semantic_onboarding_decision() reason>' or 'saved'.
	 */
	function einvoice_provider_save_decision(array $request) {
		$out = array('save' => false, 'value' => null, 'reason' => '');
		if (empty($request['visible'])) {
			$out['reason'] = 'hidden';
			return $out;
		}
		if (!einvoice_provider_can_edit($request['rettigheder'] ?? null)) {
			$out['reason'] = 'no_rights';
			return $out;
		}
		$sessionToken = $request['sessionToken'] ?? null;
		$postedToken = $request['postedToken'] ?? null;
		if (!is_string($sessionToken) || $sessionToken === '' || !is_string($postedToken) || !hash_equals($sessionToken, $postedToken)) {
			$out['reason'] = 'csrf';
			return $out;
		}
		$value = einvoice_provider_normalize($request['posted'] ?? null);
		if ($value === null) {
			$out['reason'] = 'invalid';
			return $out;
		}
		$out['value'] = $value;
		if ($value === einvoice_provider_normalize($request['current'] ?? 'easyubl')) {
			$out['reason'] = 'unchanged';
			return $out;
		}
		$state = $request['state'] ?? array();
		if ($value === 'semantic' && empty($state['semanticAllowed'])) {
			$out['reason'] = 'refused_' . (string)($state['reason'] ?? 'unknown');
			return $out;
		}
		$out['save'] = true;
		$out['reason'] = 'saved';
		return $out;
	}
}

if (!function_exists('einvoice_provider_current')) {
	/**
	 * The regnskab's provider; anything not whitelisted counts as the default, as semantic_provider_enabled() reads it.
	 *
	 * @return string
	 */
	function einvoice_provider_current() {
		$value = einvoice_provider_normalize(get_settings_value('provider', 'einvoice', 'easyubl'));
		return ($value === null) ? 'easyubl' : $value;
	}
}

if (!function_exists('einvoice_provider_read_state')) {
	/**
	 * einvoice_provider_state() for the current tenant database.
	 *
	 * @param string $db
	 * @return array{easyUbl: bool, semantic: bool, semanticAllowed: bool, reason: string}
	 */
	function einvoice_provider_read_state($db) {
		$r = db_fetch_array(db_select("SELECT var_value FROM settings WHERE var_name = 'companyID' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__));
		return einvoice_provider_state(semantic_settings_read(), $r ? (string)$r['var_value'] : '', (string)$db);
	}
}

if (!function_exists('einvoice_provider_text')) {
	/**
	 * The page's texts (findtekst keys einvoice.settings.*), not escaped.
	 *
	 * @param int|string $sprog_id
	 * @return array<string, string>
	 */
	function einvoice_provider_text($sprog_id) {
		$keys = array(
			'title' => 'einvoice.settings.title|E-faktura',
			'provider' => 'einvoice.settings.provider|Send e-fakturaer gennem',
			'easyubl' => 'einvoice.settings.provider_easyubl|EasyUBL (standard)',
			'semantic' => 'einvoice.settings.provider_semantic|Semantic (Peppol direkte)',
			'warning' => 'einvoice.settings.warning|Vælg kun Semantic, hvis regnskabet ikke allerede er tilmeldt e-faktura hos EasyUBL. Et regnskab, der er tilmeldt hos den ene udbyder, kan ikke sende gennem den anden, før saldi.dk support har flyttet tilmeldingen.',
			'stateEasyUbl' => 'einvoice.settings.state_easyubl|Tilmeldt hos EasyUBL',
			'stateSemantic' => 'einvoice.settings.state_semantic|Tilmeldt hos Semantic',
			'yes' => 'einvoice.settings.yes|Ja',
			'no' => 'einvoice.settings.no|Nej',
			'refused_registered_elsewhere' => 'einvoice.settings.refused_registered_elsewhere|Regnskabet er tilmeldt e-faktura hos EasyUBL. Semantic kan ikke vælges, før saldi.dk support har flyttet tilmeldingen.',
			'refused_copy' => 'einvoice.settings.refused_copy|Dette regnskab er en kopi af et andet regnskab. Semantic kan ikke vælges i en kopi.',
			'refused_db_unknown' => 'einvoice.settings.refused_db_unknown|E-faktura-tilmeldingen mangler oplysning om, hvilket regnskab den hører til. Kontakt saldi.dk support.',
			'backWarning' => 'einvoice.settings.back_warning|Regnskabet er tilmeldt hos Semantic. Vælges EasyUBL, tilmelder EasyUBL regnskabet ved næste afsendelse. Kontakt saldi.dk support, før der skiftes.',
			'confirm' => 'einvoice.settings.confirm_semantic|Send e-fakturaer gennem Semantic? Regnskabet må ikke allerede være tilmeldt e-faktura hos EasyUBL.',
			'saved' => 'einvoice.settings.saved|Indstillingen er gemt.',
			'unchanged' => 'einvoice.settings.unchanged|Indstillingen er uændret.',
			'invalid' => 'einvoice.settings.invalid|Ugyldigt valg. Indstillingen er ikke gemt.',
			'csrf' => 'einvoice.settings.session_expired|Sessionen er udløbet. Indstillingen er ikke gemt. Prøv igen.',
			'no_rights' => 'einvoice.settings.no_rights|Du har ikke rettighed til at ændre indstillingen.',
		);
		$texts = array();
		foreach ($keys as $name => $key) {
			$texts[$name] = (string)findtekst($key, $sprog_id);
		}
		$texts['save'] = (string)findtekst('3|Gem', $sprog_id);
		return $texts;
	}
}

if (!function_exists('einvoiceProviderHandlePost')) {
	/**
	 * Save a posted choice and redirect back to the page (Post/Redirect/Get). Runs from systemdata/diverse.php before
	 * the page prints its menu; returns without doing anything when the request is not this form.
	 *
	 * @param string               $method  $_SERVER['REQUEST_METHOD'].
	 * @param string               $sektion $_GET['sektion'].
	 * @param array<string, mixed> $post    $_POST.
	 * @param mixed                $rettigheder online.rettigheder (online.php).
	 * @param string               $db      The current database.
	 * @return void Exits after the redirect.
	 */
	function einvoiceProviderHandlePost($method, $sektion, array $post, $rettigheder, $db) {
		if ($method !== 'POST' || $sektion !== 'einvoice_valg') {
			return;
		}
		$visible = einvoice_provider_choice_visible();
		$decision = einvoice_provider_save_decision(array(
			'visible' => $visible,
			'rettigheder' => $rettigheder,
			'sessionToken' => $_SESSION['csrf_token'] ?? null,
			'postedToken' => $post['csrf_token'] ?? null,
			'posted' => $post['einvoiceProvider'] ?? null,
			'current' => $visible ? einvoice_provider_current() : 'easyubl',
			'state' => $visible ? einvoice_provider_read_state($db) : array(),
		));
		if ($decision['save']) {
			update_settings_value('provider', 'einvoice', db_escape_string((string)$decision['value']), 'SD-815 E-invoice provider: easyubl (default) or semantic');
		}
		if ($visible) {
			$_SESSION['einvoiceProviderNotice'] = $decision['reason'];
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Location: diverse.php?sektion=einvoice_valg');
		exit;
	}
}

if (!function_exists('einvoiceProviderPage')) {
	/**
	 * The sektion's rows inside diverse.php's table. Prints nothing when the choice is hidden on this server.
	 *
	 * @param int|string $sprog_id
	 * @param string     $csrfToken $_SESSION['csrf_token'].
	 * @param string     $db
	 * @return void
	 */
	function einvoiceProviderPage($sprog_id, $csrfToken, $db) {
		if (!einvoice_provider_choice_visible()) {
			return;
		}
		$text = einvoice_provider_text($sprog_id);
		$html = static function ($value) {
			return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
		};
		$current = einvoice_provider_current();
		$state = einvoice_provider_read_state($db);
		$notice = (string)($_SESSION['einvoiceProviderNotice'] ?? '');
		unset($_SESSION['einvoiceProviderNotice']);

		print "<tr><td colspan='4'><hr></td></tr>\n";
		print "<tr><td colspan='4'><b>" . $html($text['title']) . "</b></td></tr>\n";
		if ($notice !== '') {
			$noticeText = $text[$notice] ?? $text['invalid'];
			$colour = ($notice === 'saved' || $notice === 'unchanged') ? 'green' : 'red';
			print "<tr><td colspan='4' style='color:$colour'>" . $html($noticeText) . "</td></tr>\n";
		}
		print "<tr><td>" . $html($text['stateEasyUbl']) . "</td><td colspan='3'>" . $html($state['easyUbl'] ? $text['yes'] : $text['no']) . "</td></tr>\n";
		print "<tr><td>" . $html($text['stateSemantic']) . "</td><td colspan='3'>" . $html($state['semantic'] ? $text['yes'] : $text['no']) . "</td></tr>\n";
		print "<tr><td colspan='4'><br></td></tr>\n";

		$confirm = json_encode($text['confirm'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		// The form sits between table rows, so the radios belong to it without being inside it: read them through elements.
		$onsubmit = "var r=this.elements['einvoiceProvider'], v=r ? r.value : '';"
			. "return !(v==='semantic' && this.getAttribute('data-current')!=='semantic') || confirm($confirm);";
		print "<form name='einvoiceProvider' action='diverse.php?sektion=einvoice_valg' method='post' autocomplete='off' data-current='" . $html($current) . "' onsubmit=\"" . $html($onsubmit) . "\">\n";
		print "<input type='hidden' name='csrf_token' value='" . $html($csrfToken) . "'>\n";
		print "<tr><td valign='top'>" . $html($text['provider']) . "</td><td colspan='3'>\n";
		foreach (einvoice_provider_options() as $option) {
			$disabled = ($option === 'semantic' && !$state['semanticAllowed'] && $current !== 'semantic') ? ' disabled' : '';
			$checked = ($option === $current) ? ' checked' : '';
			print "<label><input type='radio' name='einvoiceProvider' value='" . $html($option) . "'$checked$disabled> " . $html($text[$option]) . "</label><br>\n";
		}
		print "</td></tr>\n";
		print "<tr><td></td><td colspan='3' style='max-width:520px'><i>" . $html($text['warning']) . "</i></td></tr>\n";
		if (!$state['semanticAllowed'] && isset($text['refused_' . $state['reason']])) {
			print "<tr><td></td><td colspan='3' style='color:red'>" . $html($text['refused_' . $state['reason']]) . "</td></tr>\n";
		}
		if ($current === 'semantic' && $state['semantic']) {
			print "<tr><td></td><td colspan='3' style='color:red'>" . $html($text['backWarning']) . "</td></tr>\n";
		}
		print "<tr><td colspan='4'><br></td></tr>\n";
		print "<tr><td colspan='4'><input type='submit' value='" . $html($text['save']) . "'></td></tr>\n";
		print "</form>\n";
	}
}
