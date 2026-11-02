<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/signupIncludes/signupPage.php --- ver 5.0.0 --- 2026-10-14 ---
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
// The page frame of the signup flow's own pages (activation, "check your inbox", new link):
// a white card on a light background like Saldi's login page. Design and states:
// docs/oprettelsesflow/aktiveringsside-preview.html in the landing-page repo. Inline CSS and
// scripts carry a per-request nonce and the page sends a Content-Security-Policy with it.
//
// 20261014 CL/PS Created.

/** @var string $copyright from includes/version.php (via includes/connect.php) */

if (!function_exists('signup_nonce')) {
	/**
	 * The CSP nonce for this request's inline style and script.
	 *
	 * @return string
	 */
	function signup_nonce() {
		static $nonce = null;
		if ($nonce === null) {
			$nonce = bin2hex(random_bytes(16));
		}
		return $nonce;
	}
}

if (!function_exists('signup_page_css')) {
	/**
	 * @return string the stylesheet of the card layout
	 */
	function signup_page_css($assets) {
		$a = signup_h($assets);
		return <<<CSS
:root { --ink:#121017; --body:#3B4759; --muted:#55616F; --line:#D3D3D3; --divider:#E3DCD2; --brand:#4F37F5; --brand-hover:#3F2AD6; --link:#143A70;
  --ok:#2E7D4F; --ok-bg:#E6F2EA; --warn:#9A5A1B; --warn-bg:#FBF1E6; --err:#B42318; --err-bg:#FDECEA; --page:#F6F6F8; }
@font-face { font-family:'Saldi Grotesk'; font-weight:400; src:url('$a/fonts/SaldiGrotesk-Regular.woff2') format('woff2'); }
@font-face { font-family:'Saldi Grotesk'; font-weight:500; src:url('$a/fonts/SaldiGrotesk-Medium.woff2') format('woff2'); }
@font-face { font-family:'Saldi Grotesk'; font-weight:600; src:url('$a/fonts/SaldiGrotesk-SemiBold.woff2') format('woff2'); }
* { box-sizing:border-box; } html, body { margin:0; }
body { background:var(--page); color:var(--body); font-family:'Saldi Grotesk', Arial, Helvetica, sans-serif; }
.wrap { min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px 16px 24px; }
.card { width:100%; max-width:440px; background:#fff; border-radius:10px; box-shadow:0 2px 16px rgba(18,16,23,.08); padding:28px 28px 32px; }
.logo { display:block; height:28px; width:auto; }
h1 { margin:22px 0 0; font-size:22px; line-height:1.25; font-weight:600; color:var(--ink); letter-spacing:-0.01em; }
.lead { margin:8px 0 0; font-size:15px; line-height:1.55; }
.summary { margin:22px 0 0; padding:14px 16px; border:1px solid var(--divider); border-radius:8px; background:#FAF9F7; font-size:14px; line-height:1.5; }
.summary .row { display:flex; gap:12px; align-items:baseline; padding:3px 0; }
.summary .k { flex:none; width:92px; color:var(--muted); }
.summary .v { flex:1; min-width:0; color:var(--ink); font-weight:600; overflow-wrap:anywhere; }
.summary .v.plain { font-weight:400; }
.summary .edit { flex:none; font:inherit; font-size:13px; color:var(--link); background:none; border:0; padding:0; text-decoration:underline; cursor:pointer; }
.field { margin:18px 0 0; }
label { display:block; font-size:14px; color:var(--ink); margin:0 0 6px; }
.input { position:relative; }
.input select { width:100%; height:44px; padding:0 12px; font:inherit; font-size:16px; color:var(--ink); border:1px solid var(--line); border-radius:7.5px; background:#fff; }
.input input { width:100%; height:44px; padding:0 44px 0 12px; font:inherit; font-size:16px; color:var(--ink); border:1px solid var(--line); border-radius:7.5px; background:#fff; }
.input input:focus { outline:2px solid var(--brand); outline-offset:1px; border-color:var(--brand); }
.input input[aria-invalid="true"], .input input[data-state="error"] { border-color:var(--err); }
.input input[data-state="ok"] { border-color:var(--ok); }
.eye { position:absolute; right:6px; top:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border:0; background:none; color:var(--muted); cursor:pointer; border-radius:6px; }
.help { margin:6px 0 0; font-size:13px; line-height:1.5; color:var(--muted); }
.check { display:flex; gap:6px; align-items:center; margin:6px 0 0; font-size:13px; color:var(--muted); }
.check.ok { color:var(--ok); } .check.err { color:var(--err); }
.reqs { list-style:none; margin:10px 0 0; padding:0; display:flex; flex-direction:column; gap:6px; }
.req { display:flex; align-items:center; gap:8px; font-size:13px; line-height:1.4; color:var(--muted); }
.req-icon { flex:none; width:18px; height:18px; border-radius:50%; border:1.5px solid #B9B4AC; display:flex; align-items:center; justify-content:center; position:relative; }
.req[data-state="ok"] { color:var(--ok); } .req[data-state="ok"] .req-icon { border:0; background:var(--ok); }
.req[data-state="ok"] .req-icon::after { content:""; width:8px; height:4px; margin-top:-2px; border-left:2px solid #fff; border-bottom:2px solid #fff; transform:rotate(-45deg); }
.req[data-state="error"] { color:var(--err); } .req[data-state="error"] .req-icon { border:0; background:var(--err); }
.req[data-state="error"] .req-icon::before, .req[data-state="error"] .req-icon::after { content:""; position:absolute; width:9px; height:2px; background:#fff; }
.req[data-state="error"] .req-icon::before { transform:rotate(45deg); } .req[data-state="error"] .req-icon::after { transform:rotate(-45deg); }
.pw-status { margin:10px 0 0; padding:8px 12px; border-radius:8px; font-size:13px; line-height:1.5; color:var(--muted); background:#F4F3F1; }
.pw-status[data-state="progress"] { color:#6B3E12; background:var(--warn-bg); }
.pw-status[data-state="ok"] { color:#1E5A37; background:var(--ok-bg); font-weight:600; }
.pw-status[data-state="error"] { color:var(--err); background:var(--err-bg); font-weight:600; }
.notice { display:flex; gap:10px; margin:8px 0 0; padding:10px 12px; border-radius:8px; font-size:13px; line-height:1.5; }
.notice.ok { background:var(--ok-bg); color:#1E5A37; } .notice.warn { background:var(--warn-bg); color:#6B3E12; } .notice.err { background:var(--err-bg); color:var(--err); }
.suggest { margin-top:6px; font:inherit; font-size:13px; font-weight:600; color:var(--link); background:#fff; border:1px solid var(--divider); border-radius:999px; padding:4px 12px; cursor:pointer; }
.btn { display:flex; align-items:center; justify-content:center; width:100%; height:46px; margin:24px 0 0; font:inherit; font-size:15px; font-weight:600; color:#fff; background:var(--brand); border:0; border-radius:7.5px; cursor:pointer; text-decoration:none; }
.btn:hover { background:var(--brand-hover); }
.btn:focus-visible, .edit:focus-visible, .suggest:focus-visible, .eye:focus-visible, .link:focus-visible { outline:2px solid var(--brand); outline-offset:2px; }
.btn[disabled] { opacity:.5; cursor:not-allowed; }
.fine { margin:14px 0 0; font-size:13px; line-height:1.5; color:var(--muted); text-align:center; }
.link { color:var(--link); }
.icon-circle { width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin-top:22px; }
.icon-circle.ok { background:var(--ok-bg); color:var(--ok); } .icon-circle.warn { background:var(--warn-bg); color:var(--warn); } .icon-circle.err { background:var(--err-bg); color:var(--err); }
.icon-circle + h1 { margin-top:14px; }
.spinner { width:18px; height:18px; margin-right:10px; border:2px solid rgba(255,255,255,.4); border-top-color:#fff; border-radius:50%; animation:spin .8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .spinner { animation:none; } }
.footer { margin-top:28px; font:12px/1.5 Arial, Helvetica, sans-serif; color:var(--muted); text-align:center; }
[hidden] { display:none !important; }
CSS;
	}
}

if (!function_exists('signup_page')) {
	/**
	 * Sends a complete page with the card layout and ends the request.
	 *
	 * @param string $title   the <title>
	 * @param string $content HTML inside the card (already escaped where needed)
	 * @param string $script  inline JavaScript for this page (gets the CSP nonce)
	 * @param int    $status  HTTP status
	 * @return void
	 */
	function signup_page($title, $content, $script = '', $status = 200) {
		global $copyright;
		$nonce = signup_nonce();
		$urls = signup_urls('business');
		http_response_code($status);
		header('Content-Type: text/html; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Frame-Options: DENY');
		header('Referrer-Policy: no-referrer');
		header("Content-Security-Policy: default-src 'none'; img-src https: data:; font-src https:; style-src 'nonce-$nonce'; script-src 'nonce-$nonce'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
		$foot = $copyright ? 'Copyright © ' . signup_h($copyright) : 'Saldi · Danosoft ApS';
		echo '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
			. '<title>' . signup_h($title) . ' – Saldi</title>'
			. '<style nonce="' . $nonce . '">' . signup_page_css($urls['assets']) . '</style></head><body>'
			. '<main class="wrap"><div class="card"><img class="logo" src="' . signup_h($urls['assets']) . '/saldi-logo.png" alt="Saldi">'
			. $content
			. '</div><p class="footer">' . $foot . '</p></main>';
		if ($script !== '') {
			echo '<script nonce="' . $nonce . '">' . $script . '</script>';
		}
		echo '</body></html>';
		exit;
	}
}

if (!function_exists('signup_help_line')) {
	/**
	 * "Brug for hjælp? Ring på ... eller skriv til ..." as used under the buttons.
	 *
	 * @return string
	 */
	function signup_help_line() {
		$phone = signup_setting('support_phone', '46 90 22 08');
		$mail = signup_setting('support_email', 'info@saldi.dk');
		return '<p class="fine">Brug for hjælp? Ring på <a class="link" href="tel:+45' . signup_h(preg_replace('/\D/', '', $phone)) . '">' . signup_h($phone) . '</a> eller skriv til <a class="link" href="mailto:' . signup_h($mail) . '">' . signup_h($mail) . '</a>.</p>';
	}
}

if (!function_exists('signup_icon')) {
	/**
	 * @param string $kind 'ok' | 'warn' | 'err'
	 * @return string the round status icon above a heading
	 */
	function signup_icon($kind) {
		$svg = ($kind === 'ok')
			? '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>'
			: (($kind === 'warn')
				? '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>'
				: '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>');
		return '<div class="icon-circle ' . $kind . '" aria-hidden="true">' . $svg . '</div>';
	}
}
