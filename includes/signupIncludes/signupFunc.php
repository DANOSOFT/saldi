<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/signupIncludes/signupFunc.php --- ver 5.1.0 --- 2026-11-02 ---
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
// Self-service signup ("oprettelsesflow"): the library behind signup/*.php.
//
//   form (saldi.dk)  ->  signup/opret.php   pending row + welcome mail with a one-time link
//                    ->  signup/aktiver.php  the customer confirms the account name, picks a
//                                            password; the account is created and they are logged in
//
// Everything here runs against the master database (includes/connect.php) and the tables it
// creates itself: signup_pending (one row per form submission, only a hash of the activation
// token), signup_events (rate limiting) and signup_cvr_cache. The account's tenant database is built
// with opret_build_tenant() (includes/opretfunc.php), the same build admin/opret.php and
// api/signup_api.php use through create_regnskab().
//
// Configuration lives in the master 'settings' table, var_grp = 'signup' (see signup_setting()
// for the keys and defaults), overridable with environment variables SALDI_SIGNUP_<KEY>; the mail
// transport also honours the docker stack's SMTP_* variables. No secrets in this file.
//
// All functions are plain functions guarded by function_exists(), so the file can be included
// from a test without a database; only the functions that query call db_*().
//
// 20261014 CL/PS Created (docs/oprettelsesflow/plan.md on saldi.dk's landing-page repo).
// 20261102 CL/ASR Ported to master: the tenant database is built with opret_build_tenant() (includes/opretfunc.php) instead of a copy of it.
//                 Each package gets its own users and postings; 0 (unlimited) was used for every package.
//                 The trial ends: regnskab.lukkes is set to the end of the trial plus trial_grace_days (14).
//                 The billing order sends postalCode (the REST api has no zipCode) and is skipped without a phone number.
//                 Finans links to this installation's login, not saldi.dk/mini.
//                 The name check asks ssl3's locator, like index/login.php.
//                 The tables come from includes/betweenUpdates.php; signup_schema_ready() replaces the first-use DDL.
//                 Limits are claimed (recorded, then counted), IPv6 is counted per /64, and mailboxes are normalised (signup_email_key).
//                 base_url is required; links never come from the Host header, and POSTs from foreign origins are refused.
//                 The locator is asked before LOCK TABLE regnskab is taken.
//                 signup_master_connect() closes the tenant connection like create_regnskab(), so a failed build is dropped; three failures set status 'failed'.
//                 E-mail and name fit regnskab/adresser (60) and the usernames index/login.php accepts; names carry no backslashes or web addresses.
//                 lukkes_kommentar explains the closing date in the admin panel, and the billing payload sends only plain text.
//                 A longer e-mail is refused instead of cut to 60; posts the browser marks Sec-Fetch-Site same-origin pass the origin check.
//                 max_activations_per_day is counted under the regnskab lock; a failed reservation is logged and noted on the signup.
//                 The CVR cache is written with an upsert, and IPv4-mapped client addresses count as their IPv4 address.
//                 A CVR number is looked up with master's cvrLookupCompany() (Datafordeleren, the server's key, no daily quota); cvrapi.dk only searches names, with master's User-Agent and refusal pause, and an ended company is not found by either.

/** @var string $sqhost  from includes/connect.php */
/** @var string $squser  from includes/connect.php */
/** @var string $sqpass  from includes/connect.php */
/** @var string $sqdb    from includes/connect.php: the master database */
/** @var string $db_type from includes/connect.php */

if (!function_exists('signup_packages')) {
	/**
	 * The packages a customer can sign up for, keyed by the value the form sends.
	 *
	 * Names and prices follow the price page (plan.md, "Pakker"): the old Mini/Basis/Proff names
	 * are not used anywhere in the new flow. 'sku' is the item number used on the billing order.
	 *
	 * @return array<string, array{name: string, price: int, sku: string, system: string}>
	 */
	function signup_packages() {
		return array(
			// brugere/posteringer: what the package includes (content/pricing.json on saldi.dk). They become the
			// account's brugerantal/posteringer; 0 would mean "unlimited" (admin/admin_panel.php).
			'finans'       => array('name' => 'Finans',       'price' => 99,  'sku' => 'FINANS',       'brugere' => 1, 'posteringer' => 500),
			'professionel' => array('name' => 'Professionel', 'price' => 199, 'sku' => 'PROFESSIONEL', 'brugere' => 1, 'posteringer' => 5000),
			'business'     => array('name' => 'Business',     'price' => 499, 'sku' => 'BUSINESS',     'brugere' => 1, 'posteringer' => 25000),
		);
	}
}

if (!function_exists('signup_package_limits')) {
	/**
	 * Users and postings the new account gets for its package (the package's included amounts, or the
	 * brugere_<pakke> / posteringer_<pakke> settings). Never 0: in 'regnskab' 0 means unlimited.
	 *
	 * @param string $pakke package key from signup_packages(); unknown keys get Business
	 * @return array{brugerantal: int, posteringer: int}
	 */
	function signup_package_limits($pakke) {
		$pakker = signup_packages();
		$key = isset($pakker[$pakke]) ? $pakke : 'business';
		$p = $pakker[$key];
		$brugere = (int)signup_setting('brugere_' . $key, (string)$p['brugere']);
		$posteringer = (int)signup_setting('posteringer_' . $key, (string)$p['posteringer']);
		return array(
			'brugerantal' => $brugere > 0 ? $brugere : (int)$p['brugere'],
			'posteringer' => $posteringer > 0 ? $posteringer : (int)$p['posteringer'],
		);
	}
}

if (!function_exists('signup_trial_dates')) {
	/**
	 * Trial period of a new account. The trial ends after $trialDays; the account closes $graceDays
	 * later (regnskab.lukkes, enforced at login by includes/online.php) unless sales has extended or
	 * cleared the date by then.
	 *
	 * @param int $trialDays days of trial (at least 1)
	 * @param int $graceDays days from the end of the trial until the account closes (at least 0)
	 * @param int $today     timestamp of "today" (default: now)
	 * @return array{start: string, end: string, lukkes: string} Y-m-d dates
	 */
	function signup_trial_dates($trialDays, $graceDays, $today = null) {
		$today = $today === null ? time() : (int)$today;
		$start = date('Y-m-d', $today);
		$end = date('Y-m-d', strtotime($start . ' +' . max(1, (int)$trialDays) . ' days'));
		$lukkes = date('Y-m-d', strtotime($end . ' +' . max(0, (int)$graceDays) . ' days'));
		return array('start' => $start, 'end' => $end, 'lukkes' => $lukkes);
	}
}

if (!function_exists('signup_setting')) {
	/**
	 * Reads one configuration value: environment variable SALDI_SIGNUP_<KEY> first, then the
	 * master settings table (var_grp = 'signup'), then the built-in default.
	 *
	 * Keys and defaults:
	 *   base_url          absolute url of this installation, e.g. https://ssl3.saldi.dk/finans (REQUIRED)
	 *   assets_url        where the mail/page images and fonts live (https://saldi.dk/assets)
	 *   landing_url       the website (https://saldi.dk); priser_url, guide_url derive from it
	 *   login_url         login page (default <base_url>/index/index.php); every package is created on this installation
	 *   login_url_finans  optional separate login page for Finans (default: login_url)
	 *   from_email, from_name, reply_to, bcc, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure
	 *   allowed_origins   comma-separated origins allowed to call signup/opret.php from a browser
	 *   cvr_api_url, cvr_user_agent, cvr_cache_days  name search at cvrapi.dk (CVR numbers: includes/cvrLookup.php)
	 *   locator_url (https://ssl3.saldi.dk/locator/locator.php), locator_check (on/off), hibp_check (on/off)
	 *   billing_api_base  REST api base for the billing order ('' = no order is created)
	 *   trial_days (30), trial_grace_days (14): the account closes (regnskab.lukkes) this many days after the
	 *                     trial unless sales extends it; token_days (7), cleanup_days (14), max_mails (5)
	 *   brugere_<pakke>, posteringer_<pakke>  override the package's included users / postings
	 *   support_phone, support_email
	 *
	 * @param string $name    key as listed above
	 * @param string $default returned when neither the environment nor the table has a value
	 * @return string
	 */
	function signup_setting($name, $default = '') {
		static $cache = null;
		$env = getenv('SALDI_SIGNUP_' . strtoupper($name));
		if ($env !== false && $env !== '') {
			return $env;
		}
		if ($cache === null) {
			$cache = array();
			// Only with an open connection: library code (and the unit tests) may call this before connect.php ran.
			if (function_exists('db_select') && function_exists('tbl_exists') && !empty($GLOBALS['connection']) && tbl_exists('settings')) {
				$q = db_select("SELECT var_name, var_value FROM settings WHERE var_grp = 'signup'", __FILE__ . " linje " . __LINE__, true);
				while ($r = db_fetch_array($q)) {
					$cache[$r['var_name']] = (string)$r['var_value'];
				}
			}
		}
		if (isset($cache[$name]) && $cache[$name] !== '') {
			return $cache[$name];
		}
		return $default;
	}
}

if (!function_exists('signup_base_url')) {
	/**
	 * Absolute url of the installation root without a trailing slash, e.g. https://ssl3.saldi.dk/finans,
	 * from the base_url setting ('' when it is not set).
	 *
	 * @return string
	 */
	function signup_base_url() {
		// Required setting. It is never derived from the request: the Host header is chosen by the caller, and
		// the activation links in the mails are built from this value (signupBootstrap.php refuses to run without it).
		return rtrim(signup_setting('base_url'), '/');
	}
}

if (!function_exists('signup_urls')) {
	/**
	 * The links used in mails and pages for one package.
	 *
	 * @param string $pakke package key from signup_packages()
	 * @return array{login: string, priser: string, guide: string, assets: string, landing: string, nytlink: string}
	 */
	function signup_urls($pakke) {
		$landing = rtrim(signup_setting('landing_url', 'https://saldi.dk'), '/');
		// Every package is created on this installation, so its own login page is the default for all of them.
		// (Finans used to point at https://saldi.dk/mini, which is not part of the new saldi.dk website.)
		$login = signup_setting('login_url', signup_base_url() . '/index/index.php');
		if ($pakke === 'finans') {
			$login = signup_setting('login_url_finans', $login);
		}
		return array(
			'login'   => $login,
			'priser'  => signup_setting('priser_url', $landing . '/priser/'),
			'guide'   => signup_setting('guide_url', $landing . '/vejledninger/'),
			'assets'  => rtrim(signup_setting('assets_url', $landing . '/assets'), '/'),
			'landing' => $landing,
			'nytlink' => signup_base_url() . '/signup/nytlink.php',
		);
	}
}

if (!function_exists('signup_h')) {
	/**
	 * HTML-escapes a value for output (UTF-8, quotes included).
	 *
	 * @param mixed $s
	 * @return string
	 */
	function signup_h($s) {
		return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}

if (!function_exists('signup_q')) {
	/**
	 * Quotes a value for interpolation into SQL (db_escape_string in single quotes).
	 *
	 * @param mixed $v
	 * @return string
	 */
	function signup_q($v) {
		return "'" . db_escape_string((string)$v) . "'";
	}
}

if (!function_exists('signup_client_ip')) {
	/**
	 * The client's address as seen by this server (no proxy headers: they can be forged).
	 *
	 * @return string
	 */
	function signup_client_ip() {
		$ip = (string)ifset($_SERVER, 'REMOTE_ADDR', '');
		$bin = @inet_pton($ip);
		if ($bin !== false && strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
			return inet_ntop(substr($bin, 12)); // IPv4-mapped (::ffff:a.b.c.d): one IPv4 client, not one /64 for all
		}
		if ($bin !== false && strlen($bin) === 16) {
			// One IPv6 connection usually has a whole /64, so the limits count per /64, not per address.
			return inet_ntop(substr($bin, 0, 8) . str_repeat("\0", 8)) . '/64';
		}
		return substr($ip, 0, 45);
	}
}

if (!function_exists('signup_log')) {
	/**
	 * Writes one line to the web server's error log, prefixed so it can be grepped.
	 *
	 * @param string $msg
	 * @return void
	 */
	function signup_log($msg) {
		error_log('signup: ' . $msg);
	}
}

if (!function_exists('signup_column_exists')) {
	/**
	 * Whether a column exists in a table of the current connection's database.
	 *
	 * @param string $table
	 * @param string $column
	 * @param bool   $global query the master database (db_select's $global)
	 * @return bool
	 */
	function signup_column_exists($table, $column, $global = true) {
		global $db_type;
		$schema = ($db_type == 'mysql' || $db_type == 'mysqli') ? 'DATABASE()' : 'current_schema()';
		$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name = " . signup_q($table) . " AND column_name = " . signup_q($column)
			. " AND table_schema = $schema";
		return (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, $global));
	}
}

if (!function_exists('signup_schema_ready')) {
	/**
	 * Whether the flow's master-database tables and the trial columns on regnskab exist. They are
	 * created by includes/betweenUpdates.php (the database convention: no DDL in pages or includes),
	 * which runs at every login, so on a live server they appear with the first login after deploy.
	 *
	 * @return bool
	 */
	function signup_schema_ready() {
		foreach (array('signup_pending', 'signup_events', 'signup_cvr_cache') as $table) {
			if (!tbl_exists($table)) {
				return false;
			}
		}
		foreach (array('trial_start', 'trial_end', 'pakke') as $col) {
			if (!signup_column_exists('regnskab', $col)) {
				return false;
			}
		}
		return signup_column_exists('signup_pending', 'navn_fra_cvr');
	}
}

if (!function_exists('signup_name_from_cvr')) {
	/**
	 * Whether the proposed account name of a pending signup came from the CVR register, so mails
	 * and pages may say so. Rows from before the column existed fall back to "a CVR number was given".
	 *
	 * @param array $row signup_pending row
	 * @return bool
	 */
	function signup_name_from_cvr(array $row) {
		$flag = ifset($row, 'navn_fra_cvr', null);
		if ($flag !== null && $flag !== '') {
			return (int)$flag === 1;
		}
		return trim((string)ifset($row, 'cvrnr', '')) !== '';
	}
}

if (!function_exists('signup_log_event')) {
	/**
	 * Records an event for rate limiting (see signup_count_events()).
	 *
	 * @param string $kind  'opret' | 'mail' | 'cvr' | 'navn' | 'aktiver'
	 * @param string $email
	 * @return void
	 */
	function signup_log_event($kind, $email = '') {
		$qtxt = "INSERT INTO signup_events (kind, ip, email) VALUES (" . signup_q($kind) . ", " . signup_q(signup_client_ip()) . ", " . signup_q(signup_email_key($email)) . ")";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
	}
}

if (!function_exists('signup_email_key')) {
	/**
	 * The mailbox an address delivers to, for the per-address limits: lower case, without a +tag, and
	 * without dots for Gmail (one inbox answers to all of those spellings).
	 *
	 * @param string $email
	 * @return string
	 */
	function signup_email_key($email) {
		$email = mb_strtolower(trim((string)$email));
		$at = strrpos($email, '@');
		if ($at === false) {
			return $email;
		}
		$local = substr($email, 0, $at);
		$domain = substr($email, $at + 1);
		$plus = strpos($local, '+');
		if ($plus !== false) {
			$local = substr($local, 0, $plus);
		}
		if ($domain === 'gmail.com' || $domain === 'googlemail.com') {
			$local = str_replace('.', '', $local);
			$domain = 'gmail.com';
		}
		return $local . '@' . $domain;
	}
}

if (!function_exists('signup_claim_event')) {
	/**
	 * Counts an attempt against a limit and says whether it is within it. The attempt is recorded first
	 * and counted afterwards, so requests that arrive at the same moment cannot all pass a check made
	 * before any of them was recorded (at most $max get through). Refused attempts count too.
	 *
	 * @param string      $kind    event kind, see signup_log_event()
	 * @param int         $max     attempts allowed in the window
	 * @param int         $seconds the window
	 * @param string|null $email   count per mailbox (signup_email_key) instead of per client address
	 * @param bool        $all     count every client (a global cap)
	 * @return bool true = within the limit
	 */
	function signup_claim_event($kind, $max, $seconds, $email = null, $all = false) {
		signup_log_event($kind, (string)$email);
		return signup_count_events($kind, $seconds, $email, $all) <= (int)$max;
	}
}

if (!function_exists('signup_count_events')) {
	/**
	 * Number of events of one kind within the last $seconds, for this client address or for an
	 * e-mail address.
	 *
	 * @param string      $kind
	 * @param int         $seconds
	 * @param string|null $email count by e-mail instead of by client address
	 * @return int
	 */
	function signup_count_events($kind, $seconds, $email = null, $all = false) {
		global $db_type;
		$seconds = (int)$seconds;
		$since = ($db_type == 'mysql' || $db_type == 'mysqli')
			? "ts > DATE_SUB(NOW(), INTERVAL $seconds SECOND)"
			: "ts > now() - interval '$seconds seconds'";
		$who = $all ? "1 = 1" : (($email !== null) ? "email = " . signup_q(signup_email_key($email)) : "ip = " . signup_q(signup_client_ip()));
		$qtxt = "SELECT count(*) AS n FROM signup_events WHERE kind = " . signup_q($kind) . " AND $who AND $since";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
		return (int)ifset($r, 'n', 0);
	}
}

// ---------------------------------------------------------------------------------------------
// Validation and normalisation (pure functions)
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_clean_text')) {
	/**
	 * Trims, collapses whitespace and strips control characters from a form value.
	 *
	 * @param mixed $s
	 * @param int   $max maximum length in characters
	 * @return string
	 */
	function signup_clean_text($s, $max) {
		$s = is_string($s) ? $s : '';
		if (!mb_check_encoding($s, 'UTF-8')) {
			$s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
		}
		$s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
		$s = trim(preg_replace('/\s+/u', ' ', (string)$s));
		return mb_substr($s, 0, $max);
	}
}

if (!function_exists('signup_valid_email')) {
	/**
	 * Whether a string is a usable e-mail address (syntax plus a dotted domain).
	 *
	 * @param string $email
	 * @return bool
	 */
	function signup_valid_email($email) {
		// The address becomes the administrator's username and regnskab.email (varchar 60). index/login.php
		// strips ' & " \ ; < > from usernames, so an address with one of them could never log in.
		if (mb_strlen($email) > 60 || !filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\'&"\\\\;<>\s]/', $email)) {
			return false;
		}
		$domain = substr(strrchr($email, '@'), 1);
		return (bool)preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $domain);
	}
}

if (!function_exists('signup_validate_form')) {
	/**
	 * Validates the fields the website's form sends (plan.md, section 1). The CVR lookup is not
	 * done here: signup/opret.php calls signup_lookup_cvr() with the cleaned data afterwards.
	 *
	 * @param array $in raw $_POST (or decoded JSON body) with name, cvr, company, email, phone,
	 *                  pakke, source
	 * @return array{ok: bool, errors: array<string, string>, data: array{navn: string, cvr: string,
	 *               firmanavn: string, email: string, tlf: string, pakke: string, source: string}}
	 */
	function signup_validate_form(array $in) {
		$errors = array();
		// Lengths as the account stores them (tenant adresser.kontakt 60, firmanavn 90). Backslashes are dropped:
		// the legacy injection check (includes/db_query.php injecttjek) misreads them and aborts the account build.
		$navn = str_replace('\\', '', signup_clean_text(ifset($in, 'name', ''), 60));
		$firma = str_replace('\\', '', signup_clean_text(ifset($in, 'company', ''), 90));
		// Read the e-mail in full: cutting it to 60 here could turn it into another valid address (.com -> .co).
		$email = signup_clean_text(ifset($in, 'email', ''), 254);
		$tlf = signup_clean_text(ifset($in, 'phone', ''), 40);
		$pakke = strtolower(signup_clean_text(ifset($in, 'pakke', 'business'), 20));
		$source = preg_replace('/[^a-z0-9_-]/', '', strtolower(signup_clean_text(ifset($in, 'source', ''), 40)));
		$cvr = preg_replace('/\D/', '', (string)ifset($in, 'cvr', ''));

		if (mb_strlen($navn) < 2) {
			$errors['name'] = 'Skriv jeres navn.';
		}
		// Name and company are repeated in the welcome mail; a link there would turn the form into a way of
		// sending links from Saldi's address.
		foreach (array('name' => $navn, 'company' => $firma) as $field => $value) {
			if (preg_match('~://|www\.|https?:~i', $value)) {
				$errors[$field] = 'Skriv navnet uden webadresser.';
			}
		}
		if (mb_strlen($email) > 60) {
			$errors['email'] = 'E-mailadressen må højst være 60 tegn.';
		} elseif (!signup_valid_email($email)) {
			$errors['email'] = 'Skriv en gyldig e-mailadresse.';
		}
		if ($tlf !== '' && !preg_match('/^\+?[0-9 ().-]{6,39}$/', $tlf)) {
			$errors['phone'] = 'Telefonnummeret ser ikke rigtigt ud.';
		}
		if ($cvr !== '' && !preg_match('/^\d{8}$/', $cvr)) {
			$errors['cvr'] = 'Et dansk CVR-nummer har 8 cifre.';
		}
		if ($cvr === '' && mb_strlen($firma) < 2) {
			$errors['cvr'] = 'Skriv jeres CVR-nummer eller virksomhedsnavn.';
		}
		if (!isset(signup_packages()[$pakke])) {
			$errors['pakke'] = 'Ukendt pakke.';
		}
		return array(
			'ok' => !$errors,
			'errors' => $errors,
			'data' => array('navn' => $navn, 'cvr' => $cvr, 'firmanavn' => $firma, 'email' => $email, 'tlf' => $tlf, 'pakke' => $pakke, 'source' => $source),
		);
	}
}

if (!function_exists('signup_normalize_name')) {
	/**
	 * Normalises a proposed account name (plan.md, section 3): letters (incl. æøå), digits, space,
	 * full stop and hyphen are kept; '&' becomes "og"; quotes are dropped; everything else becomes
	 * a space; whitespace is collapsed; at most 60 characters (regnskab.regnskab is varchar(60)).
	 *
	 * @param string $name
	 * @return string
	 */
	function signup_normalize_name($name) {
		$name = signup_clean_text($name, 200);
		$name = str_replace('&', ' og ', $name);
		$name = str_replace(array("'", '"', '`', '´', '’', '“', '”'), '', $name);
		$name = preg_replace('/[^\p{L}\p{M}\p{N} .\-]/u', ' ', $name);
		$name = trim(preg_replace('/\s+/u', ' ', (string)$name), " .-");
		return mb_substr($name, 0, 60);
	}
}

if (!function_exists('signup_name_variant')) {
	/**
	 * The n'th fallback for a taken account name: "Nordlys Interiør ApS 2", "... 3", keeping the
	 * result within 60 characters.
	 *
	 * @param string $name normalised name
	 * @param int    $n    2, 3, ...
	 * @return string
	 */
	function signup_name_variant($name, $n) {
		$suffix = ' ' . (int)$n;
		return mb_substr($name, 0, 60 - mb_strlen($suffix)) . $suffix;
	}
}

if (!function_exists('signup_validate_password')) {
	/**
	 * Password rules (plan.md, section 3, in line with NIST SP 800-63B): at least 10 characters,
	 * at most 200, any character allowed, not the customer's own e-mail or company name, and not
	 * a known leaked password (Have I Been Pwned, see signup_password_pwned()).
	 *
	 * @param string $pw
	 * @param array  $context array{email?: string, firmanavn?: string, regnskab?: string}
	 * @param bool   $checkPwned also ask Have I Been Pwned (needs network)
	 * @return array{ok: bool, reason: string} reason: '' | 'short' | 'long' | 'personal' | 'pwned'
	 */
	function signup_validate_password($pw, array $context = array(), $checkPwned = true) {
		$pw = (string)$pw;
		$len = mb_strlen($pw);
		if ($len < 10) {
			return array('ok' => false, 'reason' => 'short');
		}
		if ($len > 200) {
			return array('ok' => false, 'reason' => 'long');
		}
		$flat = mb_strtolower(preg_replace('/\s+/u', '', $pw));
		$personal = array();
		foreach (array('email', 'firmanavn', 'regnskab') as $key) {
			$v = mb_strtolower(preg_replace('/\s+/u', '', (string)ifset($context, $key, '')));
			if ($v !== '') {
				$personal[] = $v;
			}
		}
		if (!empty($context['email']) && strpos($context['email'], '@') !== false) {
			$personal[] = mb_strtolower(substr($context['email'], 0, strpos($context['email'], '@')));
		}
		foreach ($personal as $v) {
			// the password must not be the customer's own e-mail/company name, nor be built around it
			if ($flat === $v || (mb_strlen($v) >= 5 && strpos($flat, $v) !== false)) {
				return array('ok' => false, 'reason' => 'personal');
			}
		}
		if (strpos($flat, 'saldi') !== false && $len < 14) {
			return array('ok' => false, 'reason' => 'personal');
		}
		if ($checkPwned && signup_setting('hibp_check', 'on') !== 'off' && signup_password_pwned($pw) === true) {
			return array('ok' => false, 'reason' => 'pwned');
		}
		return array('ok' => true, 'reason' => '');
	}
}

if (!function_exists('signup_password_pwned')) {
	/**
	 * Asks Have I Been Pwned whether a password appears in known breaches, with k-anonymity: only
	 * the first five characters of the SHA-1 hash leave the server.
	 *
	 * @param string $pw
	 * @return bool|null true = found, false = not found, null = the service could not be reached
	 */
	function signup_password_pwned($pw) {
		$sha = strtoupper(sha1($pw));
		$prefix = substr($sha, 0, 5);
		$suffix = substr($sha, 5);
		$ch = curl_init('https://api.pwnedpasswords.com/range/' . $prefix);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 4,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_HTTPHEADER => array('Add-Padding: true', 'User-Agent: Saldi signup'),
		));
		$body = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($body === false || $code != 200) {
			signup_log("pwnedpasswords unavailable (http $code)");
			return null;
		}
		foreach (explode("\n", $body) as $line) {
			$parts = explode(':', trim($line));
			if (count($parts) == 2 && $parts[0] === $suffix && (int)$parts[1] > 0) {
				return true;
			}
		}
		return false;
	}
}

// ---------------------------------------------------------------------------------------------
// CVR lookup (server side, cached)
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_lookup_cvr')) {
	/**
	 * Looks a company up in the CVR register by number or name.
	 *
	 * A CVR number goes to master's lookup (includes/cvrLookup.php, cvrLookupCompany()): Datafordeleren, the
	 * register's own service, with the server's API key (setting datafordeler / apikey in the master database),
	 * free and without a daily quota, cached in temp/cvrcache. This is the lookup opret.php relies on. Without
	 * the key, or while Datafordeleren is down, it yields 'error' and the form goes on with the typed number.
	 *
	 * A company name (only a suggestion for the website's form) goes to cvrapi.dk's search (cvr_api_url), with a
	 * cache in signup_cvr_cache; its free quota is small and per server address, and when it is used up
	 * the name search says 'error'.
	 *
	 * @param string $q 8-digit CVR number or a company name (at least 3 characters)
	 * @return array{status: string, vat?: string, name?: string, address?: string, address2?: string,
	 *               zipcode?: string, city?: string} status: 'found' | 'notfound' | 'error'
	 */
	function signup_lookup_cvr($q) {
		global $db_type;
		$q = signup_clean_text($q, 120);
		$digits = preg_replace('/\D/', '', $q);
		$isCvr = (bool)preg_match('/^\d{8}$/', $digits);
		if (!$isCvr && mb_strlen($q) < 3) {
			return array('status' => 'notfound');
		}
		if ($isCvr) {
			return signup_cvr_result(cvrLookupCompany($digits, 6));
		}
		$key = 'name:' . mb_strtolower($q);
		if (mb_strlen($key) > 120) { // signup_cvr_cache.q is VARCHAR(120)
			$key = 'name#' . sha1($key);
		}
		// The same server address and quota as master's cvrapi.dk lookups (phone numbers on the kreditor card): its
		// pause after a refusal holds here too, so more calls cannot turn a used-up quota into a ban.
		if (cvrLookupPaused('') !== null) {
			return array('status' => 'error');
		}
		$days = max(1, (int)signup_setting('cvr_cache_days', '7'));
		$fresh = ($db_type == 'mysql' || $db_type == 'mysqli') ? "fetched > DATE_SUB(NOW(), INTERVAL $days DAY)" : "fetched > now() - interval '$days days'";
		$r = db_fetch_array(db_select("SELECT result FROM signup_cvr_cache WHERE q = " . signup_q($key) . " AND $fresh", __FILE__ . " linje " . __LINE__, true));
		if ($r && ($cached = json_decode($r['result'], true)) && isset($cached['status'])) {
			return $cached;
		}
		$url = rtrim(signup_setting('cvr_api_url', 'https://cvrapi.dk/api'), '/') . '?country=dk&search=' . rawurlencode($q);
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 6,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_USERAGENT => signup_setting('cvr_user_agent', cvrLookupUserAgent()),
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$d = ($body !== false) ? json_decode($body, true) : null;
		$given = (is_array($d) && !empty($d['error'])) ? preg_replace('/[^A-Z_]/', '', strtoupper((string)$d['error'])) : '';
		if (is_array($d) && !empty($d['vat']) && $given === '') {
			// mapped like a CVR number's answer: an ended company is not found, a c/o address as on a letter
			$result = signup_cvr_result(array('ok' => true, 'error' => null, 'dissolved' => cvrLookupDissolved($d), 'company' => cvrLookupCompanyFields($d)));
		} elseif ($given === 'NOT_FOUND') {
			$result = array('status' => 'notfound');
		} else {
			$error = ($given !== '') ? $given : ($code === 403 ? 'BLOCKED' : 'UNAVAILABLE');
			signup_log("cvr name search failed (http $code, $error)");
			if ($body !== false && ($code >= 400 || $given !== '')) {
				cvrLookupPause($error, ''); // a quota stops the server's cvrapi.dk calls until tomorrow, a refusal for an hour
			}
			return array('status' => 'error');
		}
		// One statement, so parallel lookups of the same new query do not collide on the key (each failure mails fejl@).
		$json = signup_q(json_encode($result, JSON_UNESCAPED_UNICODE));
		$upsert = ($db_type == 'mysql' || $db_type == 'mysqli')
			? " ON DUPLICATE KEY UPDATE result = VALUES(result), fetched = NOW()"
			: " ON CONFLICT (q) DO UPDATE SET result = EXCLUDED.result, fetched = now()";
		db_modify("INSERT INTO signup_cvr_cache (q, result) VALUES (" . signup_q($key) . ", $json)" . $upsert, __FILE__ . " linje " . __LINE__, true);
		return $result;
	}
}

if (!function_exists('signup_cvr_result')) {
	/**
	 * cvrLookupCompany()'s answer (includes/cvrLookup.php) in signup_lookup_cvr()'s form. An ended company is not
	 * found: Datafordeleren does not return it, and cvrapi.dk's flag for it counts the same.
	 *
	 * @param array $lookup array{ok: bool, error: string|null, dissolved: bool, company: array|null}
	 * @return array{status: string, vat?: string, name?: string, address?: string, address2?: string,
	 *               zipcode?: string, city?: string}
	 */
	function signup_cvr_result(array $lookup) {
		$c = ifset($lookup, 'company', null);
		if (!empty($lookup['ok']) && is_array($c) && empty($lookup['dissolved'])) {
			return array(
				'status' => 'found',
				'vat' => (string)ifset($c, 'cvrnr', ''),
				'name' => signup_clean_text(ifset($c, 'firmanavn', ''), 90),
				'address' => signup_clean_text(ifset($c, 'addr1', ''), 60),
				'address2' => signup_clean_text(ifset($c, 'addr2', ''), 60),
				'zipcode' => signup_clean_text(ifset($c, 'postnr', ''), 10),
				'city' => signup_clean_text(ifset($c, 'bynavn', ''), 60),
			);
		}
		if (!empty($lookup['dissolved']) || in_array(ifset($lookup, 'error', ''), array('NOT_FOUND', 'INVALID'), true)) {
			return array('status' => 'notfound');
		}
		return array('status' => 'error');
	}
}

// ---------------------------------------------------------------------------------------------
// Account-name availability
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_name_taken_locally')) {
	/**
	 * Whether an account with this name exists on this server (case-insensitive).
	 *
	 * @param string $name
	 * @return bool
	 */
	function signup_name_taken_locally($name) {
		$qtxt = "SELECT id FROM regnskab WHERE lower(regnskab) = " . signup_q(mb_strtolower($name));
		return (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
	}
}

if (!function_exists('signup_name_taken_remote')) {
	/**
	 * Asks the central locator (the one index/login.php uses for unknown names) whether the name
	 * is in use on another server.
	 *
	 * @param string $name
	 * @return bool|null true = taken, false = free, null = could not be determined (locator off,
	 *                   unreachable, or this server is not on its allow-list)
	 */
	function signup_name_taken_remote($name) {
		if (signup_setting('locator_check', 'on') === 'off') {
			return null;
		}
		// Same locator as index/login.php (includes/locatorClient.php). saldi.dk itself is the website and no
		// longer answers locator requests once its DNS moves to the new website host.
		$url = signup_setting('locator_url', 'https://ssl3.saldi.dk/locator/locator.php') . '?action=getLocation&dbAlias=' . rawurlencode($name);
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_HTTPHEADER => array('Accept: application/json'),
		));
		$body = curl_exec($ch);
		curl_close($ch);
		$d = ($body !== false) ? json_decode($body, true) : null;
		if (!is_array($d) || !isset($d['status'])) {
			static $warned = false;
			if (!$warned) {
				signup_log('locator gave no usable answer: ' . substr((string)$body, 0, 80));
				$warned = true;
			}
			return null;
		}
		return ($d['status'] === 'success');
	}
}

if (!function_exists('signup_name_available')) {
	/**
	 * Normalises a name and checks that it is free here and in the locator; proposes the first
	 * free variant when it is not.
	 *
	 * @param string $name
	 * @return array{name: string, available: bool, suggestion: string, remote_unknown: bool}
	 */
	function signup_name_available($name) {
		$name = signup_normalize_name($name);
		if ($name === '') {
			return array('name' => '', 'available' => false, 'suggestion' => '', 'remote_unknown' => false);
		}
		$remote = signup_name_taken_remote($name);
		$taken = signup_name_taken_locally($name) || $remote === true;
		$suggestion = '';
		if ($taken) {
			for ($n = 2; $n < 50; $n++) {
				$v = signup_name_variant($name, $n);
				if (!signup_name_taken_locally($v) && signup_name_taken_remote($v) !== true) {
					$suggestion = $v;
					break;
				}
			}
		}
		return array('name' => $name, 'available' => !$taken, 'suggestion' => $suggestion, 'remote_unknown' => ($remote === null));
	}
}

// ---------------------------------------------------------------------------------------------
// Pending signups and tokens
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_new_token')) {
	/**
	 * A fresh activation token: 32 random bytes, base64url without padding (43 characters).
	 *
	 * @return string
	 */
	function signup_new_token() {
		return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
	}
}

if (!function_exists('signup_token_hash')) {
	/**
	 * What is stored for a token: its SHA-256 (hex). The token itself only exists in the mail.
	 *
	 * @param string $token
	 * @return string
	 */
	function signup_token_hash($token) {
		return hash('sha256', (string)$token);
	}
}

if (!function_exists('signup_create_pending')) {
	/**
	 * Stores a validated form submission and issues its first activation token.
	 *
	 * @param array $data array{navn, email, tlf, cvrnr, firmanavn, addr1, addr2, postnr, bynavn,
	 *                    regnskab, pakke, source, navn_fra_cvr: int 1|0}
	 * @return array{id: int, token: string, resend: string} the token and the resend key go to the
	 *               mail and the "check your inbox" page respectively; only hashes are stored
	 */
	function signup_create_pending(array $data) {
		$token = signup_new_token();
		$resend = signup_new_token();
		$days = max(1, (int)signup_setting('token_days', '7'));
		$cols = array('navn', 'email', 'tlf', 'cvrnr', 'firmanavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'regnskab', 'pakke', 'source');
		$values = array();
		foreach ($cols as $c) {
			$values[] = signup_q(ifset($data, $c, ''));
		}
		$cols[] = 'navn_fra_cvr';
		$values[] = (int)(bool)ifset($data, 'navn_fra_cvr', 0);
		$qtxt = "INSERT INTO signup_pending (" . implode(',', $cols) . ", token_hash, token_expires, resend_hash, ip) VALUES ("
			. implode(',', $values) . ", " . signup_q(signup_token_hash($token)) . ", " . signup_q(date('Y-m-d H:i:s', time() + $days * 86400)) . ", "
			. signup_q(signup_token_hash($resend)) . ", " . signup_q(signup_client_ip()) . ")";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
		$r = db_fetch_array(db_select("SELECT id FROM signup_pending WHERE token_hash = " . signup_q(signup_token_hash($token)), __FILE__ . " linje " . __LINE__, true));
		return array('id' => (int)ifset($r, 'id', 0), 'token' => $token, 'resend' => $r['id'] . '.' . $resend);
	}
}

if (!function_exists('signup_pending_state')) {
	/**
	 * The state of a pending row as the activation page sees it.
	 *
	 * @param array  $row       signup_pending row
	 * @param string $tokenHash hash of the token the visitor presented
	 * @return string 'valid' | 'expired' | 'activated' | 'activating' | 'replaced' | 'invalid'
	 */
	function signup_pending_state(array $row, $tokenHash) {
		if ($row['status'] === 'activated') {
			return 'activated';
		}
		if ($row['status'] === 'activating') {
			return 'activating';
		}
		if ($row['status'] === 'failed') {
			return 'failed';
		}
		if ($row['token_hash'] !== $tokenHash) {
			return ($row['old_token_hash'] === $tokenHash) ? 'replaced' : 'invalid';
		}
		if (strtotime((string)$row['token_expires']) < time()) {
			return 'expired';
		}
		return 'valid';
	}
}

if (!function_exists('signup_find_pending_by_token')) {
	/**
	 * Finds the pending signup a token (current or just-replaced) belongs to.
	 *
	 * @param string $token
	 * @return array|null the row plus 'state' (see signup_pending_state()), or null
	 */
	function signup_find_pending_by_token($token) {
		$token = (string)$token;
		if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
			return null;
		}
		$h = signup_token_hash($token);
		$qtxt = "SELECT * FROM signup_pending WHERE token_hash = " . signup_q($h) . " OR old_token_hash = " . signup_q($h) . " ORDER BY id DESC LIMIT 1";
		$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
		if (!$row) {
			return null;
		}
		$row['state'] = signup_pending_state($row, $h);
		return $row;
	}
}

if (!function_exists('signup_find_pending_by_resend')) {
	/**
	 * Finds a pending signup by the resend key the "check your inbox" page holds ("<id>.<key>").
	 *
	 * @param string $resend
	 * @return array|null
	 */
	function signup_find_pending_by_resend($resend) {
		if (!preg_match('/^(\d{1,10})\.([A-Za-z0-9_-]{43})$/', (string)$resend, $m)) {
			return null;
		}
		$qtxt = "SELECT * FROM signup_pending WHERE id = " . (int)$m[1] . " AND resend_hash = " . signup_q(signup_token_hash($m[2]));
		$row = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__, true));
		return $row ?: null;
	}
}

if (!function_exists('signup_reissue_token')) {
	/**
	 * Issues a new activation token for a pending signup and invalidates the old one (it is kept
	 * as old_token_hash so the "new link" page can still recognise it, but it can no longer
	 * activate).
	 *
	 * @param array $row signup_pending row
	 * @return string the new token
	 */
	function signup_reissue_token(array $row) {
		$token = signup_new_token();
		$days = max(1, (int)signup_setting('token_days', '7'));
		$qtxt = "UPDATE signup_pending SET old_token_hash = token_hash, token_hash = " . signup_q(signup_token_hash($token))
			. ", token_expires = " . signup_q(date('Y-m-d H:i:s', time() + $days * 86400)) . " WHERE id = " . (int)$row['id'];
		db_modify($qtxt, __FILE__ . " linje " . __LINE__, true);
		return $token;
	}
}

if (!function_exists('signup_mark_mailed')) {
	/**
	 * Counts a sent mail on the pending row (for the max_mails limit).
	 *
	 * @param int $id
	 * @return void
	 */
	function signup_mark_mailed($id) {
		db_modify("UPDATE signup_pending SET mails_sent = mails_sent + 1, last_mail = " . signup_q(date('Y-m-d H:i:s')) . " WHERE id = " . (int)$id, __FILE__ . " linje " . __LINE__, true);
	}
}

if (!function_exists('signup_may_resend')) {
	/**
	 * Whether another activation mail may go to this pending signup: not activated, fewer than
	 * max_mails sent, and at least a minute since the last one.
	 *
	 * @param array $row
	 * @return bool
	 */
	function signup_may_resend(array $row) {
		if ($row['status'] !== 'pending') {
			return false;
		}
		if ((int)$row['mails_sent'] >= max(1, (int)signup_setting('max_mails', '5'))) {
			return false;
		}
		if ($row['last_mail'] && strtotime($row['last_mail']) > time() - 60) {
			return false;
		}
		return true;
	}
}

if (!function_exists('signup_mask_email')) {
	/**
	 * "mette@nordlys.dk" -> "me•••@nordlys.dk", for pages that must not reveal the full address.
	 *
	 * @param string $email
	 * @return string
	 */
	function signup_mask_email($email) {
		$at = strpos($email, '@');
		if ($at === false) {
			return '•••';
		}
		return mb_substr(substr($email, 0, $at), 0, 2) . '•••' . substr($email, $at);
	}
}

// ---------------------------------------------------------------------------------------------
// Activation: create the account
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_master_connect')) {
	/**
	 * Points $connection back at the master database (opret_build_tenant() leaves it on the tenant).
	 *
	 * @return void
	 */
	function signup_master_connect() {
		global $connection, $db, $sqhost, $squser, $sqpass, $sqdb, $non_global_connection;
		// As create_regnskab() does: close the tenant connection first. An open session on the tenant would
		// make the rollback's DROP DATABASE fail ("being accessed by other users"), and MySQL needs the
		// database selected explicitly.
		if (!empty($connection)) {
			db_close($connection);
		}
		$non_global_connection = null;
		$connection = db_connect($sqhost, $squser, $sqpass, $sqdb, __FILE__ . " linje " . __LINE__);
		db_select_database($connection, $sqdb);
		$db = $sqdb;
		$GLOBALS['db'] = $sqdb;
	}
}

if (!function_exists('signup_activate')) {
	/**
	 * Creates the account for a pending signup: reserves the name and the 'regnskab' row in one
	 * master transaction (the name check and the insert are not separable, unlike admin/opret.php),
	 * creates and seeds the tenant database with opret_build_tenant() (includes/opretfunc.php), stores
	 * the company address, sets the package limits and the trial period (closing date = end of trial +
	 * trial_grace_days) and marks the signup as activated. On a failure after the reservation the
	 * tenant database and the master row are removed again.
	 *
	 * The caller has validated the password and shown the name check to the customer; the final
	 * uniqueness check happens here, inside the lock.
	 *
	 * @param array  $row      signup_pending row in state 'valid'
	 * @param string $regnskab the account name the customer confirmed (normalised)
	 * @param string $password the password in clear text; stored only as saldikrypt() hash
	 * @return array{ok: bool, error: string, regnskab: string, regnskab_id: int, trial_end: string,
	 *               suggestion: string} error: '' | 'taken' | 'state' | 'create' | 'failed' (3rd failed build)
	 *               | 'ceiling' (max_activations_per_day reached)
	 */
	function signup_activate(array $row, $regnskab, $password) {
		global $sqhost, $squser, $sqpass, $sqdb, $db, $db_type, $db_modify_fejl;
		$fail = array('ok' => false, 'error' => '', 'regnskab' => $regnskab, 'regnskab_id' => 0, 'trial_end' => '', 'suggestion' => '');
		$regnskab = signup_normalize_name($regnskab);
		if ($regnskab === '') {
			$fail['error'] = 'taken';
			return $fail;
		}
		$id = (int)$row['id'];
		$dates = signup_trial_dates((int)signup_setting('trial_days', '30'), (int)signup_setting('trial_grace_days', '14'));
		$trialStart = $dates['start'];
		$trialEnd = $dates['end'];
		$limits = signup_package_limits($row['pakke']);

		// 1. Ask the locator first: an HTTPS call must not run while LOCK TABLE regnskab is held, since every
		//    login on the server waits for that lock. Then reserve the name and the master row atomically.
		if (signup_name_taken_remote($regnskab) === true) {
			$fail['error'] = 'taken';
			$avail = signup_name_available($regnskab);
			$fail['suggestion'] = $avail['suggestion'];
			return $fail;
		}
		// A failure while reserving is logged and noted on the signup; the page then says the error was registered.
		$reserveFailed = function () use ($id, $fail) {
			global $db_modify_fejl;
			transaktion('rollback');
			signup_log("activation $id: could not reserve the account in the master database");
			$db_modify_fejl = false;
			db_modify("UPDATE signup_pending SET fejl = " . signup_q(date('Y-m-d H:i:s') . ' reserve failed') . " WHERE id = $id", __FILE__ . " linje " . __LINE__);
			$fail['error'] = 'create';
			return $fail;
		};
		transaktion('begin');
		$db_modify_fejl = false;
		if ($db_type != 'mysql' && $db_type != 'mysqli') {
			db_modify("LOCK TABLE regnskab IN SHARE ROW EXCLUSIVE MODE", __FILE__ . " linje " . __LINE__);
		}
		$cur = db_fetch_array(db_select("SELECT status FROM signup_pending WHERE id = $id FOR UPDATE", __FILE__ . " linje " . __LINE__));
		if (!$cur || $cur['status'] !== 'pending') {
			transaktion('rollback');
			$fail['error'] = 'state';
			return $fail;
		}
		// The server's ceiling on new accounts (each one is a database on the shared server): signups that reserved
		// an account in the last 24 hours. Counted under the regnskab lock, which every activation takes, so
		// parallel activations cannot all pass it (MySQL has no such lock here). Sales can raise the setting.
		$since = signup_q(date('Y-m-d H:i:s', time() - 86400));
		$n = db_fetch_array(db_select("SELECT count(*) AS n FROM signup_pending WHERE status IN ('activating', 'activated') AND activated > $since", __FILE__ . " linje " . __LINE__));
		if ((int)ifset($n, 'n', 0) >= (int)signup_setting('max_activations_per_day', '30')) {
			transaktion('rollback');
			signup_log("max_activations_per_day reached; activation of signup $id refused");
			$fail['error'] = 'ceiling';
			return $fail;
		}
		if (signup_name_taken_locally($regnskab)) {
			transaktion('rollback');
			$fail['error'] = 'taken';
			$avail = signup_name_available($regnskab);
			$fail['suggestion'] = $avail['suggestion'];
			return $fail;
		}
		// brugerantal/posteringer from the package (0 would be unlimited); lukkes closes the account after the
		// trial and its grace period, unless sales extends it (includes/online.php enforces it at login).
		$qtxt = "INSERT INTO regnskab (regnskab, dbhost, dbuser, brugerantal, posteringer, email, bilag, pakke, trial_start, trial_end, lukkes) VALUES ("
			. signup_q($regnskab) . ", " . signup_q($sqhost) . ", " . signup_q($squser) . ", " . (int)$limits['brugerantal'] . ", " . (int)$limits['posteringer'] . ", "
			. signup_q($row['email']) . ", '0', " . signup_q($row['pakke']) . ", " . signup_q($trialStart) . ", " . signup_q($trialEnd) . ", " . signup_q($dates['lukkes']) . ")";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		if ($db_modify_fejl) { // before any further statement: on PostgreSQL the transaction is aborted now
			return $reserveFailed();
		}
		$r = db_fetch_array(db_select("SELECT id FROM regnskab WHERE regnskab = " . signup_q($regnskab) . " ORDER BY id DESC LIMIT 1", __FILE__ . " linje " . __LINE__));
		$regnskabId = (int)ifset($r, 'id', 0);
		$newDb = $sqdb . '_' . $regnskabId;
		if (!$regnskabId || $db_modify_fejl) {
			return $reserveFailed();
		}
		db_modify("UPDATE regnskab SET db = " . signup_q($newDb) . " WHERE id = $regnskabId", __FILE__ . " linje " . __LINE__);
		if ($db_modify_fejl) {
			return $reserveFailed();
		}
		if (signup_column_exists('regnskab', 'lukkes_kommentar')) { // created by admin/admin_panel.php
			$note = 'Prøveperiode via saldi.dk til ' . $trialEnd . '. Lukkes ' . $dates['lukkes'] . ', medmindre datoen ryddes eller flyttes.';
			db_modify("UPDATE regnskab SET lukkes_kommentar = " . signup_q($note) . " WHERE id = $regnskabId", __FILE__ . " linje " . __LINE__);
		}
		// activated = when the account was reserved (counted by the ceiling above); set again when it is ready.
		db_modify("UPDATE signup_pending SET status = 'activating', activated = " . signup_q(date('Y-m-d H:i:s')) . ", regnskab = " . signup_q($regnskab) . " WHERE id = $id", __FILE__ . " linje " . __LINE__);
		if ($db_modify_fejl) {
			return $reserveFailed();
		}
		transaktion('commit');

		// 2. Create and seed the tenant database with master's tenant build (includes/opretfunc.php), the one
		//    admin/opret.php and api/signup_api.php use through create_regnskab(). The master row is ours
		//    (reserved above together with the name check), so only the tenant part runs here.
		$company = array(
			'cvrnr' => db_escape_string($row['cvrnr']), 'kontakt' => db_escape_string($row['navn']), 'firmanavn' => db_escape_string($row['firmanavn']),
			'addr1' => db_escape_string($row['addr1']), 'addr2' => db_escape_string($row['addr2']), 'postnr' => db_escape_string($row['postnr']),
			'bynavn' => db_escape_string($row['bynavn']), 'tlf' => db_escape_string($row['tlf']), 'email' => db_escape_string($row['email']),
		);
		$db = $newDb; // db_select/db_modify(..., true) now mean "the master" (db_query_connection)
		$ok = false;
		ob_start(); // the legacy setup prints progress/markup (formularimport.php); nothing of it belongs on this page
		try {
			// opret_build_tenant() reads the account and the company address from globals, as create_regnskab() sets them.
			$GLOBALS['regnskab'] = db_escape_string($regnskab);
			$GLOBALS['db_id'] = $regnskabId;
			$GLOBALS['fra_formular'] = '1';
			foreach ($company as $key => $value) {
				$GLOBALS[$key] = $value;
			}
			$db_modify_fejl = false;
			opret_build_tenant($sqhost, $squser, $sqpass, $newDb, db_escape_string($row['email']), $password, 'on');
			$ok = empty($db_modify_fejl);
			if ($ok) {
				// the user who logs in: e-mail as username, phone for later two-factor use
				db_modify("UPDATE brugere SET email = " . signup_q($row['email']) . ", tlf = " . signup_q(mb_substr($row['tlf'], 0, 16)) . " WHERE brugernavn = " . signup_q($row['email']), __FILE__ . " linje " . __LINE__);
				$chk = db_fetch_array(db_select("SELECT id FROM brugere WHERE brugernavn = " . signup_q($row['email']) . " AND kode IS NOT NULL", __FILE__ . " linje " . __LINE__));
				$ok = (bool)$chk;
			}
		} catch (Throwable $e) {
			signup_log("activation $id: " . $e->getMessage());
			$ok = false;
		}
		ob_end_clean();
		signup_master_connect();
		if (!$ok) {
			signup_log("activation $id failed while creating $newDb; rolling back");
			$db_modify_fejl = false;
			db_modify("DROP DATABASE IF EXISTS " . $newDb, __FILE__ . " linje " . __LINE__);
			if ($db_modify_fejl) {
				signup_log("LEFTOVER DATABASE $newDb from signup $id could not be dropped; drop it by hand");
			}
			db_modify("DELETE FROM regnskab WHERE id = $regnskabId", __FILE__ . " linje " . __LINE__);
			// A build that keeps failing for the same signup is not retried from the link forever.
			signup_log_event('createfail', 'signup' . $id);
			$failedBefore = signup_count_events('createfail', 30 * 86400, 'signup' . $id) >= 3;
			$status = $failedBefore ? 'failed' : 'pending';
			db_modify("UPDATE signup_pending SET status = '$status', activated = NULL, fejl = " . signup_q(date('Y-m-d H:i:s') . ' create failed') . " WHERE id = $id", __FILE__ . " linje " . __LINE__);
			$fail['error'] = $failedBefore ? 'failed' : 'create';
			return $fail;
		}

		// 3. Done. The token hash stays so the link can still show "already activated - go to login";
		//    status 'activated' is what stops it from ever activating again. No more mails for it.
		$qtxt = "UPDATE signup_pending SET status = 'activated', activated = " . signup_q(date('Y-m-d H:i:s')) . ", regnskab_id = $regnskabId, "
			. "resend_hash = NULL WHERE id = $id";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		signup_log("activated signup $id as regnskab $regnskabId ($regnskab)");
		return array('ok' => true, 'error' => '', 'regnskab' => $regnskab, 'regnskab_id' => $regnskabId, 'trial_end' => $trialEnd, 'suggestion' => '');
	}
}

// ---------------------------------------------------------------------------------------------
// Billing order (plan.md, section 5) - best effort, never blocks the activation
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_billing_order_payload')) {
	/**
	 * The order the billing account's REST api gets for a new account (restapi/services/OrderService.php,
	 * whose English field names map to the Danish columns: postalCode -> postnr, phone -> telefon, ...).
	 * The api requires a phone number and uses it to find or create the customer account, so without one
	 * no order is built: a shared placeholder would put different customers on the same account.
	 *
	 * @param array  $row         signup_pending row
	 * @param string $regnskab    the account name as created
	 * @param string $trialEnd    Y-m-d
	 * @param string $packageName e.g. 'Professionel'
	 * @return array|null the order, or null when the signup has no phone number
	 */
	function signup_billing_order_payload(array $row, $regnskab, $trialEnd, $packageName) {
		$phone = trim((string)ifset($row, 'tlf', ''));
		if ($phone === '') {
			return null;
		}
		// The values come from a public form: the billing order gets them as plain text only (no quotes, backslashes
		// or semicolons), the same characters index/login.php keeps out of user input.
		$safe = function ($value) {
			return trim(str_replace(array("'", '"', '\\', ';'), '', (string)$value));
		};
		return array(
			'companyName' => $safe($row['firmanavn']), 'contact' => $safe($row['navn']), 'email' => $safe($row['email']), 'phone' => $safe($phone), 'cvrNo' => $safe($row['cvrnr']),
			'address1' => $safe($row['addr1']), 'address2' => $safe($row['addr2']), 'postalCode' => $safe($row['postnr']), 'city' => $safe($row['bynavn']),
			'vatRate' => 25, 'orderDate' => date('Y-m-d'), 'invoiceDate' => $trialEnd,
			'notes' => "Saldi " . $packageName . " - regnskab: " . $safe($regnskab) . "\nPrøveperiode til $trialEnd, faktureres derefter månedligt",
		);
	}
}

if (!function_exists('signup_create_billing_order')) {
	/**
	 * Creates the order that bills the package after the trial, through the REST api of the
	 * billing account (billing_api_base setting; the credentials are the 'internal_api' settings
	 * the legacy forms used). Invoice date = trial_end, no payment link. TLS is verified.
	 *
	 * @param array  $row        signup_pending row
	 * @param string $regnskab   the account name as created
	 * @param string $trialEnd   Y-m-d
	 * @return bool true when the order and its line were created, false otherwise (logged)
	 */
	function signup_create_billing_order(array $row, $regnskab, $trialEnd) {
		$base = rtrim(signup_setting('billing_api_base'), '/');
		if (!$base) {
			return false;
		}
		$pakker = signup_packages();
		$p = ifset($pakker, $row['pakke'], $pakker['business']);
		$sku = signup_setting('billing_sku_' . $row['pakke'], $p['sku']);
		$order = signup_billing_order_payload($row, $regnskab, $trialEnd, $p['name']);
		if ($order === null) {
			signup_log("billing order skipped for regnskab $regnskab: no phone number (the billing api requires one and uses it as the customer key); create the order by hand");
			return false;
		}
		$cred = array();
		$q = db_select("SELECT var_name, var_value FROM settings WHERE var_grp = 'internal_api'", __FILE__ . " linje " . __LINE__, true);
		while ($r = db_fetch_array($q)) {
			$cred[$r['var_name']] = $r['var_value'];
		}
		if (empty($cred['saldi_api_user']) || empty($cred['saldi_api_pass']) || empty($cred['saldi_api_account'])) {
			signup_log('billing order skipped: internal_api settings missing');
			return false;
		}
		$post = function ($url, $payload, $token = null) {
			$headers = array('Content-Type: application/json');
			if ($token) {
				$headers[] = 'Authorization: Bearer ' . $token;
			}
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode($payload),
				CURLOPT_HTTPHEADER => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT => 20,
			));
			$body = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$err = curl_error($ch);
			curl_close($ch);
			return array($code, ($body !== false) ? json_decode($body, true) : null, $err);
		};
		list($code, $auth, $err) = $post($base . '/auth/login.php', array('username' => $cred['saldi_api_user'], 'password' => $cred['saldi_api_pass'], 'account_name' => $cred['saldi_api_account']));
		$token = (is_array($auth) && !empty($auth['data']['access_token'])) ? $auth['data']['access_token'] : null;
		if (!$token) {
			signup_log("billing login failed (http $code $err)");
			return false;
		}
		list($code, $res, $err) = $post($base . '/debitor/orders/index.php', $order, $token);
		$orderId = (is_array($res) && !empty($res['data']['id'])) ? (int)$res['data']['id'] : 0;
		if (!$orderId) {
			signup_log("billing order failed (http $code $err)");
			return false;
		}
		$line = array('orderId' => $orderId, 'sku' => $sku, 'description' => 'Saldi ' . $p['name'] . ' pr. måned', 'quantity' => 1, 'price' => $p['price'], 'vatFree' => 0);
		list($code, $res, $err) = $post($base . '/debitor/orderlines/index.php', $line, $token);
		if ($code < 200 || $code >= 300) {
			signup_log("billing order line failed for order $orderId (http $code $err)");
			return false;
		}
		signup_log("billing order $orderId created for regnskab $regnskab");
		return true;
	}
}

// ---------------------------------------------------------------------------------------------
// HTTP helpers for the endpoints
// ---------------------------------------------------------------------------------------------

if (!function_exists('signup_allowed_origins')) {
	/**
	 * Origins that may call the flow from a browser: the allowed_origins setting (the website) and this
	 * installation's own origin, taken from base_url (never from the request's Host header).
	 *
	 * @return string[]
	 */
	function signup_allowed_origins() {
		$allowed = array_filter(array_map('trim', explode(',', signup_setting('allowed_origins', 'https://saldi.dk,https://www.saldi.dk'))));
		$own = parse_url(signup_base_url());
		if (!empty($own['scheme']) && !empty($own['host'])) {
			// as a browser writes it: lower case, no default port
			$scheme = strtolower($own['scheme']);
			$port = (isset($own['port']) && !($scheme === 'https' && (int)$own['port'] === 443) && !($scheme === 'http' && (int)$own['port'] === 80)) ? ':' . (int)$own['port'] : '';
			$allowed[] = $scheme . '://' . strtolower($own['host']) . $port;
		}
		return array_values($allowed);
	}
}

if (!function_exists('signup_origin_allowed')) {
	/**
	 * Whether a POST may come from where the browser says it comes from. A form on another website can
	 * post to opret.php/nytlink.php without CORS (a "simple request"), which would let any site make its
	 * visitors' browsers send Saldi mails; browsers always send Origin on a POST, so a foreign one is refused.
	 * A post the browser itself marks as coming from this site (Sec-Fetch-Site: same-origin, which a page cannot
	 * set) passes whatever its Origin says: that covers the flow's own forms when the site is reached under another
	 * host name, or a page that hides its address ("Origin: null"). Requests without Origin (no browser, or an old
	 * one) are judged by Sec-Fetch-Site and the rate limits.
	 *
	 * @return bool
	 */
	function signup_origin_allowed() {
		$origin = (string)ifset($_SERVER, 'HTTP_ORIGIN', '');
		$site = (string)ifset($_SERVER, 'HTTP_SEC_FETCH_SITE', '');
		if ($site === 'same-origin') {
			return true;
		}
		if ($origin === '' || $origin === 'null') {
			return $origin === '' && $site !== 'cross-site';
		}
		return in_array($origin, signup_allowed_origins(), true);
	}
}

if (!function_exists('signup_refuse_foreign_origin')) {
	/**
	 * Ends a POST whose Origin is not allowed (signup_origin_allowed()) with 403, as JSON or as a page.
	 *
	 * @param bool $json answer as JSON
	 * @return void
	 */
	function signup_refuse_foreign_origin($json) {
		if (signup_origin_allowed()) {
			return;
		}
		signup_log('POST from a foreign origin refused: ' . substr((string)ifset($_SERVER, 'HTTP_ORIGIN', ''), 0, 100));
		$msg = 'Formularen kan kun sendes fra saldi.dk.';
		if ($json) {
			signup_json(array('ok' => false, 'errors' => array('_' => $msg)), 403);
		}
		signup_page('Ikke tilladt', signup_icon('warn') . '<h1>Ikke tilladt</h1><p class="lead">' . signup_h($msg) . '</p>' . signup_help_line(), '', 403);
	}
}

if (!function_exists('signup_cors')) {
	/**
	 * Answers CORS for the website's form: the request's Origin must be on the allowed_origins
	 * list (plus this host). Ends a preflight (OPTIONS) request.
	 *
	 * @return void
	 */
	function signup_cors() {
		$origin = (string)ifset($_SERVER, 'HTTP_ORIGIN', '');
		if ($origin !== '') {
			if (in_array($origin, signup_allowed_origins(), true)) {
				header('Access-Control-Allow-Origin: ' . $origin);
				header('Vary: Origin');
				header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
				header('Access-Control-Allow-Headers: Content-Type');
				header('Access-Control-Max-Age: 600');
			}
		}
		if (ifset($_SERVER, 'REQUEST_METHOD', '') === 'OPTIONS') {
			http_response_code(204);
			exit;
		}
	}
}

if (!function_exists('signup_wants_json')) {
	/**
	 * Whether the client expects JSON (fetch() from the website) rather than an HTML page.
	 *
	 * @return bool
	 */
	function signup_wants_json() {
		$accept = (string)ifset($_SERVER, 'HTTP_ACCEPT', '');
		$ctype = (string)ifset($_SERVER, 'CONTENT_TYPE', '');
		return strpos($accept, 'application/json') !== false || strpos($ctype, 'application/json') !== false || ifset($_GET, 'format', '') === 'json';
	}
}

if (!function_exists('signup_input')) {
	/**
	 * The request body as an array: decoded JSON when the client sent JSON, otherwise $_POST.
	 *
	 * @return array
	 */
	function signup_input() {
		if (strpos((string)ifset($_SERVER, 'CONTENT_TYPE', ''), 'application/json') !== false) {
			$d = json_decode((string)file_get_contents('php://input'), true);
			return is_array($d) ? $d : array();
		}
		return $_POST;
	}
}

if (!function_exists('signup_json')) {
	/**
	 * Sends a JSON response and ends the request.
	 *
	 * @param array $data
	 * @param int   $status HTTP status
	 * @return void
	 */
	function signup_json(array $data, $status = 200) {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
}
