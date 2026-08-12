<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/opretEmailFunc.php --- patch 5.0.0 --- 2026.08.04 ---
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// Central welcome-email module for accounts created from the website. The
// template, subject, sender and package names/prices are maintained by
// administration in admin/opret_email.php and read from here, so the hardcoded
// mails in the opret.php files can be retired.
//
// 20260804 Sawaneh New module. opret_email_send() answers with a stable error_code so
//                  callers that have a language context can translate it through
//                  opret_email_error_textid().
// 20260804 Sawaneh Subject and template are stored per language (emne_1/html_1, _2,
//                  _3). A language that has not been written yet falls back to
//                  Danish, so an empty mail is never sent.
// 20260805 Sawaneh opret_email_deliver() strips CR/LF/NUL from recipient, subject and
//                  sender before they reach mail(). A subject template containing
//                  {{navn}} could otherwise be used to inject mail headers through
//                  the posted customer name.
// 20260812 Sawaneh Review: placeholders are substituted in one preg_replace_callback()
//                  pass, so a customer value containing $0 or \1 is inserted literally.
//                  The subject decodes entities after strip_tags(). Added CSRF, log
//                  masking and protected-package helpers used by the editor and the
//                  send endpoint.

if (!defined('OPRET_EMAIL_SETTINGS_GRP')) {
	define('OPRET_EMAIL_SETTINGS_GRP', 'opret_email');
}

if (!function_exists('opret_email_setup')) {
	/**
	 * Creates the opret_pakker table and seeds the default settings rows.
	 *
	 * Every statement is existence-checked, so this is safe to call on every
	 * request. It deliberately does not live in includes/betweenUpdates.php:
	 * that file is only included from index/login.php after the master-database
	 * login has already exited (see index/login.php, the $regnskab == $sqdb
	 * branch), so it never runs against the master database where these rows
	 * belong. admin/admin_panel.php guards its own master-only column the same
	 * way.
	 *
	 * @return void
	 */
	function opret_email_setup()
	{
		$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = 'opret_pakker'";
		if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$qtxt = "CREATE TABLE opret_pakker (
				id serial PRIMARY KEY NOT NULL,
				kode varchar(30) NOT NULL,
				navn varchar(100) NOT NULL,
				pris numeric(12,2) NOT NULL DEFAULT 0,
				sorteringnr integer NOT NULL DEFAULT 0,
				aktiv boolean NOT NULL DEFAULT true)";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			db_modify("CREATE UNIQUE INDEX opret_pakker_kode_idx ON opret_pakker (kode)", __FILE__ . " linje " . __LINE__);

			// The three packages the website uses today. The price is left for
			// administration to set, so it never gets frozen into the code again.
			$sorteringnr = 0;
			foreach (array('finans' => 'Finans', 'professionel' => 'Professionel', 'business' => 'Business') as $kode => $navn) {
				$sorteringnr += 10;
				$qtxt = "INSERT INTO opret_pakker (kode, navn, pris, sorteringnr, aktiv) VALUES ";
				$qtxt .= "('" . db_escape_string($kode) . "', '" . db_escape_string($navn) . "', 0, $sorteringnr, true)";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}

		// Subject and template were originally stored without a language suffix.
		// Move them to _1 (Danish) rather than creating new rows, so a template
		// that has already been written is not left behind and forgotten.
		foreach (array('emne', 'html') as $base) {
			$qtxt = "SELECT id FROM settings WHERE var_name = '{$base}_1' AND var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				// Lowest id first, so the oldest row always becomes the Danish
				// version - otherwise the outcome would depend on whatever order
				// the database happens to return.
				$qtxt = "SELECT id FROM settings WHERE var_name = '$base' AND var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "' ORDER BY id LIMIT 1";
				if ($existing = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
					db_modify("UPDATE settings SET var_name = '{$base}_1' WHERE id = " . (int) $existing['id'], __FILE__ . " linje " . __LINE__);
				}
			}
			// Anything still under the old name is a leftover - including the case
			// where an earlier fault left several rows with the same name.
			$qtxt = "DELETE FROM settings WHERE var_name = '$base' AND var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}

		// Only Danish is seeded. English and Norwegian stay empty until
		// administration writes them - opret_email_settings() falls back to Danish
		// in the meantime.
		$defaults = array(
			'emne_1'   => array('Velkommen til Saldi', 'Subject of the welcome email (Danish)'),
			'afsender' => array('Saldi <info@saldi.dk>', 'Sender (From-header) of the welcome email - shared by all languages'),
			'html_1'   => array(opret_email_default_template(), 'HTML template for the welcome email (Danish) - edited in admin/opret_email.php'),
		);
		foreach ($defaults as $var_name => $default) {
			$qtxt = "SELECT id FROM settings WHERE var_name = '" . db_escape_string($var_name) . "' AND var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "'";
			if (!db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$qtxt = "INSERT INTO settings (var_name, var_grp, var_value, var_description) VALUES ";
				$qtxt .= "('" . db_escape_string($var_name) . "', '" . OPRET_EMAIL_SETTINGS_GRP . "', ";
				$qtxt .= "'" . db_escape_string($default[0]) . "', '" . db_escape_string($default[1]) . "')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
	}
}

if (!function_exists('opret_email_default_template')) {
	/**
	 * The template the page starts out with before administration edits it.
	 *
	 * @return string  Body HTML (without the wrapper from opret_email_wrap()).
	 */
	function opret_email_default_template()
	{
		$html  = '<h2>Velkommen til Saldi</h2>';
		$html .= '<p>Hej {{navn}}</p>';
		$html .= '<p>Tak for din oprettelse. Dit regnskab med pakken <strong>{{pakke_navn}}</strong> er nu klar til brug.</p>';
		// No full stop after {{pakke_pris}}: it is replaced with "199,00 kr.",
		// which already ends in one.
		$html .= '<p>Pris for {{pakke_navn}}: {{pakke_pris}}</p>';
		$html .= '<p>Du kan logge ind på <a href="https://saldi.dk">saldi.dk</a> med de oplysninger du angav ved oprettelsen.</p>';
		$html .= '<p>Har du spørgsmål, er du altid velkommen til at kontakte os.</p>';
		$html .= '<p>Med venlig hilsen<br>Saldi.dk ApS</p>';
		return $html;
	}
}

if (!function_exists('opret_email_placeholders')) {
	/**
	 * The placeholders the editor offers and opret_email_render() replaces.
	 *
	 * Single source of truth: admin/opret_email.php builds its insert-buttons
	 * from this list, so a new placeholder only has to be added here. The label
	 * is a findtekst() text id rather than a fixed string, so the buttons follow
	 * the user's language like the rest of the page.
	 *
	 * @return array<string, int>  Placeholder token (without braces) => text id.
	 */
	function opret_email_placeholders()
	{
		return array(
			'pakke_navn' => 5062, // Package name
			'pakke_pris' => 5063, // Package price
			'navn'       => 5073, // Customer name
			'cvrnr'      => 5074, // Company registration number
			'tlf'        => 37,   // Telephone
			'email'      => 5075, // Email address
		);
	}
}

if (!function_exists('opret_email_beskyttede_koder')) {
	/**
	 * The package codes the opret.php files on the website post to
	 * admin/opret_email_send.php.
	 *
	 * Deleting one of these leaves registration with a code that no longer
	 * resolves, so opret_email_send() answers 'unknown_package' and the customer
	 * gets no welcome email at all. The rows can still be renamed and repriced -
	 * only the code is fixed, because the website hardcodes it.
	 *
	 * @return array  Lowercase package codes that must not be deleted.
	 */
	function opret_email_beskyttede_koder()
	{
		return array('finans', 'professionel', 'business');
	}
}

if (!function_exists('opret_email_log_value')) {
	/**
	 * Makes a request-supplied value safe to write into a one-line log entry.
	 *
	 * error_log() writes whatever it is given, so CR/LF in - for instance - the
	 * posted package code would let a caller forge additional log lines and hide
	 * a rejected key among invented entries.
	 *
	 * @param string $value  Raw value from the request.
	 * @return string  The value with every control character replaced by a space.
	 */
	function opret_email_log_value($value)
	{
		return preg_replace('/[\x00-\x1F\x7F]/', ' ', (string) $value);
	}
}

if (!function_exists('opret_email_maskeret_email')) {
	/**
	 * Masks an email address for logging.
	 *
	 * The delivery log exists to make a leaked key or a misconfigured opret.php
	 * visible, which needs the domain and enough of the address to line an entry
	 * up with a customer - not the address itself. Server logs are kept outside
	 * the accounting database and have no retention rule, so the full address
	 * does not belong there.
	 *
	 * @param string $email  Address from the request.
	 * @return string  Masked address, e.g. 'j***@example.com'.
	 */
	function opret_email_maskeret_email($email)
	{
		$email = opret_email_log_value(trim((string) $email));
		$snabel = strrpos($email, '@');
		if ($email === '') {
			return '(tom)';
		}
		if ($snabel === false || $snabel === 0) {
			return '***';
		}
		return substr($email, 0, 1) . '***' . substr($email, $snabel);
	}
}

if (!function_exists('opret_email_csrf_token')) {
	/**
	 * Returns this session's token for the editor's state-changing calls,
	 * creating it on first use.
	 *
	 * @return string  Hex token, or '' when no session is running.
	 */
	function opret_email_csrf_token()
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			return '';
		}
		if (empty($_SESSION['opret_email_csrf'])) {
			$_SESSION['opret_email_csrf'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['opret_email_csrf'];
	}
}

if (!function_exists('opret_email_csrf_ok')) {
	/**
	 * Decides whether a state-changing ajax call really came from the editor.
	 *
	 * Three independent checks, because the session cookie alone proves nothing
	 * about who started the request:
	 *  - the token, which another origin cannot read out of the page;
	 *  - a JSON content type, which a cross-site HTML form cannot produce (a form
	 *    can only send urlencoded, multipart or text/plain);
	 *  - Origin, when the browser sends one, so a request from another host is
	 *    refused even if the first two were somehow satisfied.
	 *
	 * @return bool  True when the request is the editor's own.
	 */
	function opret_email_csrf_ok()
	{
		$type = strtolower(trim((string) if_isset($_SERVER, '', 'CONTENT_TYPE')));
		if (strpos($type, 'application/json') !== 0) {
			return false;
		}
		$modtaget = (string) if_isset($_SERVER, '', 'HTTP_X_CSRF_TOKEN');
		$forventet = (string) if_isset($_SESSION, '', 'opret_email_csrf');
		if ($forventet === '' || $modtaget === '' || !hash_equals($forventet, $modtaget)) {
			return false;
		}
		$origin = (string) if_isset($_SERVER, '', 'HTTP_ORIGIN');
		if ($origin !== '') {
			$vaert = (string) parse_url($origin, PHP_URL_HOST);
			$port  = parse_url($origin, PHP_URL_PORT);
			if ($port) {
				$vaert .= ':' . $port;
			}
			if (strcasecmp($vaert, (string) if_isset($_SERVER, '', 'HTTP_HOST')) !== 0) {
				return false;
			}
		}
		return true;
	}
}

if (!function_exists('opret_email_parse_price')) {
	/**
	 * Validates a price typed in Danish notation and converts it to a float.
	 *
	 * usdecimal() strips every '.' as a thousands separator, so "1299.50"
	 * would silently become 129950 and "abc" would become 0. Both are checked
	 * here instead, so a mistyped price is rejected rather than saved wrong.
	 *
	 * @param string $input  Raw field value, e.g. '1.299,50' or '1299,50'.
	 * @return float|null  The price, or null if the field isn't a valid amount.
	 */
	function opret_email_parse_price($input)
	{
		$input = trim((string) $input);
		if ($input === '') {
			return null;
		}
		$plain   = '/^[0-9]+(,[0-9]{1,2})?$/';                  // 1299 / 1299,50
		$grouped = '/^[0-9]{1,3}(\.[0-9]{3})+(,[0-9]{1,2})?$/'; // 1.299,50
		if (!preg_match($plain, $input) && !preg_match($grouped, $input)) {
			return null;
		}
		return (float) usdecimal($input);
	}
}

if (!function_exists('opret_email_rotate_api_key')) {
	/**
	 * Replaces the shared secret with a freshly generated one.
	 *
	 * Every opret.php still sending the old key gets a 403 from
	 * admin/opret_email_send.php afterwards, so the caller must tell the user to
	 * update those files.
	 *
	 * @return string  The new key, 40 hex characters.
	 */
	function opret_email_rotate_api_key()
	{
		$key = bin2hex(random_bytes(20));
		opret_email_save_setting('api_key', $key);
		return $key;
	}
}

if (!function_exists('opret_email_live_codes')) {
	/**
	 * The package codes the website actually links to today.
	 *
	 * Used by admin/opret_email.php to mark which packages are live and which
	 * ones only exist as preparation: the website cannot show new packages
	 * dynamically yet, so a package added in the panel reaches no customers
	 * until the site is extended.
	 *
	 * @return array<int, string>  Package codes.
	 */
	function opret_email_live_codes()
	{
		return array('finans', 'professionel', 'business');
	}
}

if (!function_exists('opret_email_languages')) {
	/**
	 * The languages a template can be written in.
	 *
	 * Same ids as includes/stdFunc/findTxt.php accepts (1 Dansk, 2 English,
	 * 3 Norsk). The display name of language N is findtekst(1, N) - text id 1's
	 * own translations are the language names.
	 *
	 * @return array<int, int>  Language ids.
	 */
	function opret_email_languages()
	{
		return array(1, 2, 3);
	}
}

if (!function_exists('opret_email_setting_name')) {
	/**
	 * The settings var_name holding one field for one language.
	 *
	 * @param string $base   'emne' or 'html'.
	 * @param int    $sprog  Language id; anything outside 1-3 becomes 1.
	 * @return string  e.g. 'html_2'.
	 */
	function opret_email_setting_name($base, $sprog)
	{
		$sprog = (int) $sprog;
		if (!in_array($sprog, opret_email_languages(), true)) {
			$sprog = 1;
		}
		return $base . '_' . $sprog;
	}
}

if (!function_exists('opret_email_settings')) {
	/**
	 * Reads subject, sender and body for one language in a single query.
	 *
	 * Subject and body are stored per language ('emne_1', 'html_2', ...) so
	 * administration can maintain a Danish, English and Norwegian welcome mail
	 * side by side. The sender is shared - it is an address, not prose.
	 *
	 * A language that has not been written yet falls back to Danish rather than
	 * sending an empty mail, and says so through 'fallback'.
	 *
	 * @param int $sprog  Language id, 1-3.
	 * @return array{
	 *   emne: string,      Subject for this language, or the Danish one.
	 *   afsender: string,  From-header, shared across languages.
	 *   html: string,      Body HTML for this language, or the Danish one.
	 *   fallback: bool,    True when this language is empty and Danish is used.
	 * }
	 */
	function opret_email_settings($sprog = 1)
	{
		$alle = array();
		$qtxt = "SELECT var_name, var_value FROM settings WHERE var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "'";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$alle[$r['var_name']] = (string) $r['var_value'];
		}

		$emne = trim((string) if_isset($alle, '', opret_email_setting_name('emne', $sprog)));
		$html = (string) if_isset($alle, '', opret_email_setting_name('html', $sprog));

		$fallback = false;
		if ((int) $sprog !== 1 && trim(strip_tags($html)) === '') {
			$html = (string) if_isset($alle, '', 'html_1');
			$emne = $emne !== '' ? $emne : trim((string) if_isset($alle, '', 'emne_1'));
			$fallback = true;
		}
		if ($emne === '') {
			$emne = trim((string) if_isset($alle, '', 'emne_1'));
		}

		return array(
			'emne'     => $emne,
			'afsender' => (string) if_isset($alle, '', 'afsender'),
			'html'     => $html,
			'fallback' => $fallback,
		);
	}
}

if (!function_exists('opret_email_save_setting')) {
	/**
	 * Writes one value in the opret_email settings group.
	 *
	 * @param string $var_name   One of 'emne', 'afsender', 'html'.
	 * @param string $var_value  The new value.
	 * @return void
	 */
	function opret_email_save_setting($var_name, $var_value)
	{
		$var_name = db_escape_string($var_name);
		$qtxt = "SELECT id FROM settings WHERE var_name = '$var_name' AND var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r) {
			$qtxt = "UPDATE settings SET var_value = '" . db_escape_string($var_value) . "' WHERE id = " . (int) $r['id'];
		} else {
			$qtxt = "INSERT INTO settings (var_name, var_grp, var_value) VALUES ";
			$qtxt .= "('$var_name', '" . OPRET_EMAIL_SETTINGS_GRP . "', '" . db_escape_string($var_value) . "')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
	}
}

if (!function_exists('opret_email_api_key')) {
	/**
	 * The shared secret admin/opret_email_send.php requires from callers.
	 *
	 * Generated on first use and then reused, so the opret.php files on ssl3
	 * can keep the same key. Rotating it is a matter of deleting the settings
	 * row - the next call to this function makes a new one.
	 *
	 * @param bool $generate  Create and store a key when none exists yet. The
	 *                        unauthenticated endpoint passes false so a caller
	 *                        without the key can't make it write to settings.
	 * @return string  40 hex characters, or '' if none is stored and $generate
	 *                 is false.
	 */
	function opret_email_api_key($generate = true)
	{
		$qtxt = "SELECT var_value FROM settings WHERE var_name = 'api_key' AND var_grp = '" . OPRET_EMAIL_SETTINGS_GRP . "'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r && strlen(trim((string) $r['var_value'])) >= 20) {
			return trim($r['var_value']);
		}
		if (!$generate) {
			return '';
		}

		$key = bin2hex(random_bytes(20));
		opret_email_save_setting('api_key', $key);
		return $key;
	}
}

if (!function_exists('opret_email_packages')) {
	/**
	 * Lists the accounting packages shown in the left-hand panel.
	 *
	 * @param bool $only_active  Skip packages marked inactive.
	 * @return array<int, array{
	 *   id: int,          Row id.
	 *   kode: string,     Stable code used by the website / opret.php.
	 *   navn: string,     Display name.
	 *   pris: float,      Price.
	 *   sorteringnr: int, Sort order in the panel.
	 *   aktiv: bool,      Whether the package is in use.
	 * }>
	 */
	function opret_email_packages($only_active = false)
	{
		$packages = array();
		$qtxt = "SELECT id, kode, navn, pris, sorteringnr, aktiv FROM opret_pakker";
		if ($only_active) {
			$qtxt .= " WHERE aktiv";
		}
		$qtxt .= " ORDER BY sorteringnr, id";
		$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$packages[] = array(
				'id'          => (int) $r['id'],
				'kode'        => $r['kode'],
				'navn'        => $r['navn'],
				'pris'        => (float) $r['pris'],
				'sorteringnr' => (int) $r['sorteringnr'],
				'aktiv'       => ($r['aktiv'] === true || $r['aktiv'] === 't' || $r['aktiv'] === '1' || $r['aktiv'] === 'on'),
			);
		}
		return $packages;
	}
}

if (!function_exists('opret_email_package')) {
	/**
	 * Looks up a single package by its code.
	 *
	 * @param string $kode  Package code, e.g. 'finans'.
	 * @return array|null  Same shape as one opret_email_packages() row, or null
	 *                     if no package has that code.
	 */
	function opret_email_package($kode)
	{
		foreach (opret_email_packages() as $package) {
			if ($package['kode'] === (string) $kode) {
				return $package;
			}
		}
		return null;
	}
}

if (!function_exists('opret_email_inline_styles')) {
	/**
	 * Converts the ql-* classes Quill emits into inline styles.
	 *
	 * Outlook.com and a few other clients strip <style> blocks, so alignment
	 * and size set in the editor would silently be lost. The classes are
	 * rewritten here instead of in the browser so the stored template stays
	 * exactly what the editor produced.
	 *
	 * @param string $html  Body HTML as stored.
	 * @return string  Body HTML with ql-* classes turned into style attributes.
	 */
	function opret_email_inline_styles($html)
	{
		$styles = array(
			'ql-align-center'  => 'text-align:center',
			'ql-align-right'   => 'text-align:right',
			'ql-align-justify' => 'text-align:justify',
			'ql-size-small'    => 'font-size:0.75em',
			'ql-size-large'    => 'font-size:1.5em',
			'ql-size-huge'     => 'font-size:2.5em',
		);
		for ($level = 1; $level <= 8; $level++) {
			$styles["ql-indent-$level"] = 'padding-left:' . (3 * $level) . 'em';
		}

		// Processed tag by tag rather than attribute by attribute: if the element
		// already carries a style attribute (typically written in source mode),
		// the new declarations have to be merged into it. Two style attributes on
		// one tag is invalid, and clients honour only the first.
		return preg_replace_callback(
			'/<([a-z][a-z0-9]*)\b([^>]*)>/i',
			function ($tag) use ($styles) {
				if (strpos($tag[2], 'ql-') === false) {
					return $tag[0];
				}

				$inline = array();
				$attrs = preg_replace_callback(
					'/\sclass="([^"]*)"/i',
					function ($match) use ($styles, &$inline) {
						$keep = array();
						foreach (preg_split('/\s+/', trim($match[1])) as $class) {
							if ($class === '') {
								continue;
							}
							if (isset($styles[$class])) {
								$inline[] = $styles[$class];
							} else {
								$keep[] = $class;
							}
						}
						return $keep ? ' class="' . implode(' ', $keep) . '"' : '';
					},
					$tag[2]
				);

				if (!$inline) {
					return $tag[0];
				}
				$nye = implode(';', $inline);

				if (preg_match('/\sstyle="([^"]*)"/i', $attrs)) {
					// The new declarations go last, so what was just chosen in the
					// editor wins over an older inline declaration.
					$attrs = preg_replace_callback(
						'/\sstyle="([^"]*)"/i',
						function ($match) use ($nye) {
							$eksisterende = rtrim(trim($match[1]), ';');
							return ' style="' . ($eksisterende === '' ? $nye : $eksisterende . ';' . $nye) . '"';
						},
						$attrs,
						1
					);
				} else {
					$attrs .= ' style="' . $nye . '"';
				}

				return '<' . $tag[1] . $attrs . '>';
			},
			$html
		);
	}
}

if (!function_exists('opret_email_render')) {
	/**
	 * Replaces the placeholders in the template with the customer's values.
	 *
	 * @param string $html     Body HTML template.
	 * @param array  $package  Package row from opret_email_package(), or null.
	 * @param array  $vars     Customer values keyed by placeholder name
	 *                         ('navn', 'cvrnr', 'tlf', 'email').
	 * @return string  Body HTML with every known placeholder substituted.
	 */
	function opret_email_render($html, $package, $vars = array())
	{
		$values = array(
			'pakke_navn' => $package ? $package['navn'] : '',
			'pakke_pris' => $package ? dkdecimal($package['pris'], 2) . ' kr.' : '',
		);
		foreach (array('navn', 'cvrnr', 'tlf', 'email') as $key) {
			$values[$key] = isset($vars[$key]) ? (string) $vars[$key] : '';
		}

		$erstatning = array();
		$navne = array();
		foreach (array_keys(opret_email_placeholders()) as $name) {
			$erstatning[$name] = htmlspecialchars(if_isset($values, '', $name), ENT_QUOTES, 'UTF-8');
			$navne[] = preg_quote($name, '/');
		}
		if (!$navne) {
			return $html;
		}
		// Both {{navn}} and {{ navn }} are accepted so a stray space typed in the
		// source view doesn't leave a raw placeholder in the mail.
		//
		// One callback pass, not preg_replace() with an array of patterns:
		// preg_replace() reads $0-$99, ${1} and \1 in the replacement as
		// backreferences, so a customer named 'A$0B' would insert the matched
		// placeholder text instead of the name. A callback return value is always
		// literal. A single pass also stops a value that itself contains
		// '{{navn}}' from being substituted again by a later pattern.
		$moenster = '/\{\{\s*(' . implode('|', $navne) . ')\s*\}\}/';
		return preg_replace_callback($moenster, function ($fundet) use ($erstatning) {
			return $erstatning[$fundet[1]];
		}, $html);
	}
}

if (!function_exists('opret_email_wrap')) {
	/**
	 * Wraps rendered body HTML in the email-safe document shell.
	 *
	 * A fixed 600px table with inline styles - the layout email clients
	 * actually agree on - rather than the CSS the admin page itself uses.
	 *
	 * @param string $body_html  Rendered body HTML.
	 * @param string $emne       Subject, used as the document title.
	 * @return string  Complete HTML document ready to send.
	 */
	function opret_email_wrap($body_html, $emne = '')
	{
		// The colour is kept out of $font so each cell can set its own without
		// ending up with two color declarations in one style attribute.
		$font = "font-family:Helvetica,Arial,sans-serif;line-height:1.6;";

		$html  = '<!DOCTYPE html><html lang="da"><head><meta charset="UTF-8">';
		$html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
		$html .= '<title>' . htmlspecialchars($emne, ENT_QUOTES, 'UTF-8') . '</title></head>';
		$html .= '<body style="margin:0;padding:0;background:#f7fafc;">';
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7fafc;">';
		$html .= '<tr><td align="center" style="padding:24px 12px;">';
		$html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:8px;overflow:hidden;">';
		$html .= '<tr><td style="background:#1a202c;padding:18px 28px;' . $font . 'color:#ffffff;font-size:18px;font-weight:bold;">SALDI</td></tr>';
		$html .= '<tr><td style="padding:28px;' . $font . 'color:#1a202c;font-size:15px;">' . $body_html . '</td></tr>';
		$html .= '<tr><td style="padding:18px 28px;background:#edf2f7;' . $font . 'color:#4a5568;font-size:12px;">';
		$html .= 'Saldi.dk ApS &middot; <a href="https://saldi.dk" style="color:#319795;">saldi.dk</a>';
		$html .= '</td></tr></table></td></tr></table></body></html>';
		return $html;
	}
}

if (!function_exists('opret_email_plaintext')) {
	/**
	 * Derives the plain-text alternative from the rendered body HTML.
	 *
	 * Takes the body only, never the document from opret_email_wrap(): that one
	 * carries a <title> and the header/footer cells, which would end up glued
	 * to the first line of the text part.
	 *
	 * @param string $body_html  Rendered body HTML.
	 * @return string  Plain text with one blank line between blocks.
	 */
	function opret_email_plaintext($body_html)
	{
		// The link text alone does not say where the link goes, so the address is
		// written out after it in the text/plain part.
		$text = preg_replace('/<a\b[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $body_html);
		$text = preg_replace('/<br\s*\/?>/i', "\n", $text);
		$text = preg_replace('/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n\n", $text);
		$text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$text = preg_replace('/[ \t]+/', ' ', $text);
		$text = preg_replace('/ *\n */', "\n", $text);
		return trim(preg_replace('/\n{3,}/', "\n\n", $text));
	}
}

if (!function_exists('opret_email_build')) {
	/**
	 * Builds the finished welcome email for one package.
	 *
	 * @param string $kode   Package code passed from the website.
	 * @param array  $vars   Customer values ('navn', 'cvrnr', 'tlf', 'email').
	 * @param int    $sprog  Language id 1-3 for the template to use.
	 * @return array{
	 *   emne: string,      Rendered subject.
	 *   afsender: string,  From-header.
	 *   html: string,      Complete HTML document.
	 *   tekst: string,     Plain-text alternative.
	 *   pakke: array|null, The package that was found, or null.
	 * }
	 */
	function opret_email_build($kode, $vars = array(), $sprog = 1)
	{
		$settings = opret_email_settings($sprog);
		$package = opret_email_package($kode);

		$body = opret_email_inline_styles($settings['html']);
		$body = opret_email_render($body, $package, $vars);
		// opret_email_render() escapes every value with htmlspecialchars() because
		// the body is HTML. The subject is plain text, so the entities have to come
		// back off again - otherwise a customer named 'Foo & Bar' gets a subject
		// reading 'Foo &amp; Bar'. Decoding cannot smuggle in a newline: the value
		// was escaped first, so a typed '&#10;' is now '&amp;#10;' and decodes back
		// to itself, and opret_email_header_value() strips CR/LF before mail().
		$emne = html_entity_decode(strip_tags(opret_email_render($settings['emne'], $package, $vars)), ENT_QUOTES, 'UTF-8');

		return array(
			'emne'     => $emne,
			'afsender' => $settings['afsender'] ? $settings['afsender'] : 'Saldi <info@saldi.dk>',
			'html'     => opret_email_wrap($body, $emne),
			'tekst'    => opret_email_plaintext($body),
			'pakke'    => $package,
		);
	}
}

if (!function_exists('opret_email_header_value')) {
	/**
	 * Strips anything that could break out of a mail header.
	 *
	 * opret_email_render() escapes placeholder values with htmlspecialchars(),
	 * which leaves CR and LF untouched - and the subject is handed straight to
	 * mail() as a header. A template with {{navn}} in the subject plus a posted
	 * name of "X\r\nBcc: victim@example.com" would otherwise inject headers on
	 * any system where mb_encode_mimeheader() is unavailable and the raw subject
	 * is used.
	 *
	 * @param string $value  Untrusted header value.
	 * @return string  The value with CR, LF and NUL replaced by spaces.
	 */
	function opret_email_header_value($value)
	{
		return trim(str_replace(array("\r", "\n", "\0"), ' ', (string) $value));
	}
}

if (!function_exists('opret_email_deliver')) {
	/**
	 * Sends one multipart/alternative mail.
	 *
	 * Deliberately not includes/std_func.php's send_email(): that helper calls
	 * mail() twice (once bare, once inside its if), which would send every
	 * customer two welcome mails.
	 *
	 * @param string $to        Recipient address.
	 * @param string $emne      Subject.
	 * @param string $html      HTML part.
	 * @param string $text      Plain-text part from opret_email_plaintext().
	 * @param string $afsender  From-header value.
	 * @return bool  Whether mail() accepted the message for delivery.
	 */
	function opret_email_deliver($to, $emne, $html, $text, $afsender)
	{
		// Sanitised here rather than at the call sites, so no caller can hand a
		// newline to mail() and inject a header of its own.
		$to       = opret_email_header_value($to);
		$emne     = opret_email_header_value($emne);
		$afsender = opret_email_header_value($afsender);

		$boundary = 'saldi_' . md5($to . $emne . microtime());

		$headers  = "From: $afsender\r\n";
		$headers .= "Reply-To: info@saldi.dk\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"";

		$body  = "--$boundary\r\n";
		$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= "$text\r\n\r\n";
		$body .= "--$boundary\r\n";
		$body .= "Content-Type: text/html; charset=UTF-8\r\n";
		$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$body .= "$html\r\n\r\n";
		$body .= "--$boundary--";

		// mb_encode_mimeheader keeps aeoe readable in clients that don't assume UTF-8.
		$emne_header = function_exists('mb_encode_mimeheader')
			? mb_encode_mimeheader($emne, 'UTF-8', 'B')
			: $emne;

		return mail($to, $emne_header, $body, $headers);
	}
}

if (!function_exists('opret_email_error_textid')) {
	/**
	 * Maps an opret_email_send() error code to its findtekst() text id.
	 *
	 * Kept here so the code-to-text-id table lives next to the codes it
	 * describes rather than being repeated in every page that shows them.
	 *
	 * @param string $code  Value of opret_email_send()'s 'error_code'.
	 * @return int  Text id for findtekst(), or 0 if the code is unknown - the
	 *              caller should then fall back to the 'error' text.
	 */
	function opret_email_error_textid($code)
	{
		$ids = array(
			'invalid_email'   => 5091,
			'unknown_package' => 5087,
			'empty_template'  => 5092,
			'mail_failed'     => 5093,
		);
		return isset($ids[$code]) ? $ids[$code] : 0;
	}
}

if (!function_exists('opret_email_send')) {
	/**
	 * Builds and sends the welcome email for a newly created account.
	 *
	 * This is the single entry point the opret.php files (via
	 * admin/opret_email_send.php) and the admin page's test button use.
	 *
	 * @param string $to     Recipient - the address the customer signed up with.
	 * @param string $kode   Package code identifying which opret.php ran.
	 * @param array  $vars   Customer values ('navn', 'cvrnr', 'tlf', 'email').
	 * @param int    $sprog  Language id 1-3; falls back to the Danish template
	 *                       when that language has not been written yet.
	 * @return array{
	 *   success: bool,      Whether the mail was handed to the mail transport.
	 *   error_code: string, Stable code for what failed, '' on success. One of
	 *                       'invalid_email', 'unknown_package', 'empty_template',
	 *                       'mail_failed'.
	 *   error: string,      Danish default text for the code, for logs and for
	 *                       the endpoint response. Callers with a language
	 *                       context should translate error_code instead - see
	 *                       opret_email_error_textid().
	 *   emne: string,       The subject that was used.
	 *   pakke: string,      Name of the package that was resolved, or ''.
	 * }
	 */
	function opret_email_send($to, $kode, $vars = array(), $sprog = 1)
	{
		$result = array('success' => false, 'error_code' => '', 'error' => '', 'emne' => '', 'pakke' => '');

		$fejl = function ($code) use (&$result) {
			$tekster = array(
				'invalid_email'   => 'Ugyldig emailadresse.',
				'unknown_package' => 'Ukendt pakke.',
				'empty_template'  => 'Skabelonen er tom - mailen blev ikke sendt.',
				'mail_failed'     => 'Mailserveren afviste beskeden.',
			);
			$result['error_code'] = $code;
			$result['error'] = $tekster[$code];
			return $result;
		};

		$to = trim((string) $to);
		if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
			return $fejl('invalid_email');
		}

		$mail = opret_email_build($kode, $vars, $sprog);
		if (!$mail['pakke']) {
			return $fejl('unknown_package');
		}
		if (trim(strip_tags($mail['html'])) === '') {
			return $fejl('empty_template');
		}

		$result['emne'] = $mail['emne'];
		$result['pakke'] = $mail['pakke']['navn'];
		$result['success'] = opret_email_deliver($to, $mail['emne'], $mail['html'], $mail['tekst'], $mail['afsender']);
		if (!$result['success']) {
			return $fejl('mail_failed');
		}
		return $result;
	}
}
?>
