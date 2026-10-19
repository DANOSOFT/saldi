<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripePage.php --- 2026.08.20
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260820 CL/LH Created: shared customer-facing page shell for the Stripe
//                subscription endpoints (subscribe.php/tak.php/afbrudt.php).
//                Pure markup helpers - no DB, no session, no JS - so tak.php
//                stays deliberately dumb. Callers escape their own dynamic
//                values in $extraHtml; everything else is escaped here.
//                Fonts are a local system stack on purpose: these are public
//                EU-facing pages, so no Google Fonts (GDPR) and no CDNs.

// The integration is pinned to one tenant (stripeConfigDb), so the page
// identity is a constant: tak.php may not open a DB connection to look it up.
if (!defined('STRIPE_PAGE_SENDER'))  define('STRIPE_PAGE_SENDER', 'Danosoft ApS');
if (!defined('STRIPE_PAGE_SUPPORT')) define('STRIPE_PAGE_SUPPORT', 'faktura@danosoft.dk');

if (!function_exists('stripe_page')) {
	function stripe_page($title, $bodyHtml, $status = 200, $centered = false) {
		http_response_code($status);
		while (ob_get_level()) ob_end_clean();
		$cardClass = $centered ? 'card center' : 'card';
		print "<!DOCTYPE html><html lang='da'><head><meta charset='utf-8'>";
		print "<meta name='viewport' content='width=device-width, initial-scale=1'>";
		print "<meta name='robots' content='noindex,nofollow'>";
		print "<title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>";
		print "<style>
body{margin:0;padding:40px 16px 24px;background:#f3f5f9;font-family:'Inter','Segoe UI',system-ui,-apple-system,Arial,sans-serif;color:#1f2a44}
.page{max-width:560px;margin:0 auto;text-align:center}
.logo{height:30px;margin-bottom:26px}
.card{background:#fff;border:1px solid #e3e8f2;border-radius:14px;padding:36px 40px 30px;box-shadow:0 1px 2px rgba(22,62,128,.06),0 12px 32px rgba(22,62,128,.07);text-align:left}
.card.center{text-align:center}
h1{margin:0 0 10px;font-size:22px;line-height:1.3;font-weight:700;color:#163e80}
p{margin:0 0 12px;font-size:15px;line-height:1.6;color:#475069}
a{color:#2563eb}
.eyebrow{margin:0 0 6px;font-size:13px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#6b7590}
.badge{width:56px;height:56px;border-radius:50%;margin:0 auto 20px;display:flex;align-items:center;justify-content:center}
.badge.ok{background:#e8f7ee}.badge.stop{background:#f1f4f9}.badge.info{background:#eaf0fc}.badge.warn{background:#fbf3e6}
table{width:100%;border-collapse:collapse;margin:12px 0 24px}
th{font-size:12px;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#6b7590;text-align:left;padding:0 0 10px;border-bottom:1px solid #e3e8f2}
td{font-size:15px;color:#1f2a44;padding:13px 0;border-bottom:1px solid #eef1f7}
td.r,th.r{text-align:right;padding-left:12px;font-variant-numeric:tabular-nums}
tr.total td{font-weight:600;color:#163e80;border-top:2px solid #163e80;border-bottom:none;padding:14px 0 0}
tr.total td.r{font-size:16px;font-weight:700}
.btn{display:block;box-sizing:border-box;width:100%;background:#2563eb;color:#fff;text-align:center;text-decoration:none;font-size:15px;font-weight:600;font-family:inherit;padding:14px 24px;border:none;border-radius:10px;cursor:pointer}
.muted{font-size:13px;line-height:1.6;color:#8a93a8}
.note{margin:24px 0 0;padding-top:20px;border-top:1px solid #eef1f7;font-size:13px;line-height:1.6;color:#8a93a8}
.box{background:#f6f8fc;border:1px solid #e3e8f2;border-radius:10px;padding:16px 20px;margin:0 0 8px}
.box p{margin:0;font-size:14px}
.trust{display:flex;justify-content:center;align-items:center;gap:7px;margin-top:20px;padding-top:18px;border-top:1px solid #eef1f7;font-size:13px;color:#6b7590}
.site{margin:22px 0 0;font-size:13px;color:#8a93a8}
.site a{color:#6b7590}
@media (max-width:480px){body{padding:24px 12px 20px}.card{padding:28px 22px 24px}}
</style></head><body><div class='page'>";
		print "<img class='logo' src='../../img/SaldiMainLogo.png' alt='Saldi'>";
		print "<div class='" . $cardClass . "'>" . $bodyHtml . "</div>";
		print "<p class='site'>" . htmlspecialchars(STRIPE_PAGE_SENDER, ENT_QUOTES, 'UTF-8')
			. " &middot; <a href='mailto:" . htmlspecialchars(STRIPE_PAGE_SUPPORT, ENT_QUOTES, 'UTF-8') . "'>"
			. htmlspecialchars(STRIPE_PAGE_SUPPORT, ENT_QUOTES, 'UTF-8') . "</a></p>";
		print "</div></body></html>";
		exit;
	}

	// 56px tinted circle with a stroke icon: ok (green check), stop (grey cross),
	// info (blue speech bubble), warn (amber info mark).
	function stripe_page_badge($kind) {
		$icons = [
			'ok'   => "<svg width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='#16a34a' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><path d='M20 6L9 17l-5-5'/></svg>",
			'stop' => "<svg width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='#64748b' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='9'/><path d='M15 9l-6 6M9 9l6 6'/></svg>",
			'info' => "<svg width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='#2563eb' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M21 11.5a8.38 8.38 0 0 1-8.5 8.3 8.9 8.9 0 0 1-3.8-.85L3 20l1.1-5.2a8.1 8.1 0 0 1-.9-3.7A8.38 8.38 0 0 1 11.7 2.8h.8a8.35 8.35 0 0 1 8.5 8.2z'/></svg>",
			'warn' => "<svg width='28' height='28' viewBox='0 0 24 24' fill='none' stroke='#d97706' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='9'/><path d='M12 8v4.5'/><circle cx='12' cy='16' r='0.5' fill='#d97706'/></svg>",
		];
		if (!isset($icons[$kind])) $kind = 'warn';
		return "<div class='badge " . $kind . "'>" . $icons[$kind] . "</div>";
	}

	// Centered status card: badge, headline, paragraphs, optional raw extra
	// (button/contact box - CALLER escapes), muted note under a divider.
	function stripe_status_page($status, $kind, $headline, $texts, $extraHtml = '', $note = 'Din faktura er stadig gyldig og kan betales som hidtil.') {
		$h = stripe_page_badge($kind);
		$h .= "<h1>" . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . "</h1>";
		foreach ((array)$texts as $t) $h .= "<p>" . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . "</p>";
		if ($extraHtml !== '') $h .= $extraHtml;
		if ($note !== '') $h .= "<p class='note'>" . htmlspecialchars($note, ENT_QUOTES, 'UTF-8') . "</p>";
		stripe_page($headline, $h, $status, true);
	}
}
