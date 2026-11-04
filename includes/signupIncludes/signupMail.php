<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/signupIncludes/signupMail.php --- ver 5.1.0 --- 2026-11-02 ---
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
// The welcome mail of the signup flow ("Aktivér jeres Saldi-regnskab") and the shorter "new
// activation link" mail: one template for every package, HTML and plain text, UTF-8, images on
// absolute https addresses (assets_url setting). Design: docs/oprettelsesflow/velkomstmail-preview.html
// in the landing-page repo.
//
// Transport: PHPMailer when a composer autoload with it can be found (installation root or the
// directory above it), using the SMTP_* environment variables of the docker stack or the
// smtp_* signup settings - with TLS verification on; without an SMTP host PHPMailer hands the
// mail to the local sendmail. Without PHPMailer a multipart message is built by hand for mail().
// No mail ever contains a password; the activation link is the only secret.
//
// 20261014 CL/PS Created.
// 20261102 CL/ASR The 'mail' limit is claimed by the callers before sending instead of recorded after it.

if (!function_exists('signup_mail_vars')) {
	/**
	 * The variables the templates use, from a pending row and its (clear-text) token.
	 *
	 * @param array  $row   signup_pending row
	 * @param string $token activation token
	 * @return array<string, string>
	 */
	function signup_mail_vars(array $row, $token) {
		$pakker = signup_packages();
		$p = ifset($pakker, $row['pakke'], $pakker['business']);
		$urls = signup_urls($row['pakke']);
		$days = max(1, (int)signup_setting('token_days', '7'));
		$navn = trim((string)$row['navn']);
		$fornavn = ($navn !== '') ? explode(' ', $navn)[0] : '';
		return array(
			'fornavn' => $fornavn,
			'navn' => $navn,
			'firmanavn' => (string)$row['firmanavn'],
			'regnskab' => (string)$row['regnskab'],
			'navn_fra_cvr' => signup_name_from_cvr($row) ? '1' : '',
			'email' => (string)$row['email'],
			'pakke' => $p['name'],
			'pris' => (string)$p['price'],
			'aktiveringslink' => signup_base_url() . '/signup/aktiver.php?t=' . rawurlencode($token),
			'link_udloeb' => ($days == 1) ? '1 dag' : $days . ' dage',
			'nyt_link_url' => $urls['nytlink'] . '?t=' . rawurlencode($token),
			'login_url' => $urls['login'],
			'priser_url' => $urls['priser'],
			'guide_url' => $urls['guide'],
			'assets_url' => $urls['assets'],
			'support_phone' => signup_setting('support_phone', '46 90 22 08'),
			'support_email' => signup_setting('support_email', 'info@saldi.dk'),
			'trial_days' => signup_setting('trial_days', '30'),
			'footer' => 'Saldi · Danosoft ApS · Østergade 24B, 3200 Helsinge',
		);
	}
}

if (!function_exists('signup_mail_subject')) {
	/**
	 * @param string $variant 'welcome' | 'newlink'
	 * @return string
	 */
	function signup_mail_subject($variant) {
		return ($variant === 'newlink') ? 'Nyt aktiveringslink til jeres Saldi-regnskab' : 'Aktivér jeres Saldi-regnskab';
	}
}

if (!function_exists('signup_mail_html')) {
	/**
	 * The HTML version of the mail.
	 *
	 * @param array  $v       from signup_mail_vars()
	 * @param string $variant 'welcome' | 'newlink'
	 * @return string
	 */
	function signup_mail_html(array $v, $variant = 'welcome') {
		$h = 'signup_h';
		$newlink = ($variant === 'newlink');
		$font = "font-family:'Saldi Grotesk',Arial,Helvetica,sans-serif;";
		$muted = 'color:#55616F;';
		$body = 'color:#3B4759;';
		$ink = 'color:#121017;';
		$link = 'color:#143A70;text-decoration:underline;';
		$phoneTel = preg_replace('/\D/', '', $v['support_phone']);
		$preheader = $newlink
			? 'Her er jeres nye aktiveringslink. Prøveperioden starter, når I aktiverer regnskabet.'
			: 'Aktivér jeres regnskab – jeres ' . $h($v['trial_days']) . ' dages gratis prøveperiode starter, når I gør.';
		$heading = $newlink ? 'Jeres nye aktiveringslink' : 'Velkommen til Saldi' . ($v['fornavn'] !== '' ? ', ' . $h($v['fornavn']) : '');
		$intro = $newlink
			? 'I bad om et nyt link. Aktivér regnskabet for <strong class="d-text" style="' . $ink . '">' . $h($v['firmanavn']) . '</strong>, og vælg jeres adgangskode – så starter jeres ' . $h($v['trial_days']) . ' dages gratis prøveperiode. Det gamle link virker ikke længere.'
			: 'Tak for jeres oprettelse. Aktivér regnskabet for <strong class="d-text" style="' . $ink . '">' . $h($v['firmanavn']) . '</strong>, og vælg jeres adgangskode – så starter jeres ' . $h($v['trial_days']) . ' dages gratis prøveperiode.';

		$out = '<!doctype html><html lang="da"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . $h(signup_mail_subject($variant)) . '</title>'
			. '<style>@media (max-width:620px){.m-pad{padding-left:20px !important;padding-right:20px !important;}.m-h1{font-size:26px !important;}.m-btn a{display:block !important;}}'
			. '@media (prefers-color-scheme:dark){.d-bg{background:#14121A !important;}.d-card{background:#1D1A24 !important;border-color:#2E2937 !important;}.d-soft{background:#24202D !important;border-color:#2E2937 !important;}'
			. '.d-text{color:#F5F2EE !important;}.d-body{color:#CFC9C2 !important;}.d-muted{color:#A9A299 !important;}.d-link{color:#B7A2F0 !important;}.d-logo-light{display:none !important;}.d-logo-dark{display:inline-block !important;}}'
			. '</style></head><body style="margin:0;padding:0;background:#FAF9F7;">'
			. '<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">' . $preheader . '&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="d-bg" style="background:#FAF9F7;"><tr><td align="center" style="padding:32px 12px;">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">'
			// logo
			. '<tr><td class="m-pad" style="padding:0 32px 20px;">'
			. '<img src="' . $h($v['assets_url']) . '/saldi-logo.png" width="112" alt="Saldi" class="d-logo-light" style="display:inline-block;width:112px;height:auto;border:0;">'
			. '<img src="' . $h($v['assets_url']) . '/saldi-logo-white.png" width="112" alt="Saldi" class="d-logo-dark" style="display:none;width:112px;height:auto;border:0;">'
			. '</td></tr>'
			// card
			. '<tr><td class="d-card" style="background:#FFFFFF;border:1px solid #E3DCD2;border-radius:8px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
			. '<tr><td class="m-pad" style="padding:40px 40px 8px;' . $font . '">'
			. '<h1 class="m-h1 d-text" style="margin:0;font-size:30px;line-height:1.15;font-weight:600;letter-spacing:-0.02em;' . $ink . '">' . $heading . '</h1>'
			. '<p class="d-body" style="margin:16px 0 0;font-size:16px;line-height:1.6;' . $body . '">' . $intro . '</p>'
			. '</td></tr>'
			// button
			. '<tr><td class="m-pad m-btn" style="padding:24px 40px 8px;">'
			. '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" href="' . $h($v['aktiveringslink']) . '" style="height:52px;v-text-anchor:middle;width:280px;" arcsize="12%" stroke="f" fillcolor="#1A66B8"><center style="color:#FFFFFF;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">Aktivér regnskabet</center></v:roundrect><![endif]-->'
			. '<!--[if !mso]><!--><a href="' . $h($v['aktiveringslink']) . '" style="display:inline-block;background:#1A66B8;background-image:linear-gradient(135deg,#1A66B8,#7A2CBC);color:#FFFFFF;' . $font . 'font-size:16px;font-weight:600;line-height:52px;padding:0 32px;border-radius:6px;text-decoration:none;text-align:center;">Aktivér regnskabet</a><!--<![endif]-->'
			. '</td></tr>'
			. '<tr><td class="m-pad" style="padding:8px 40px 0;' . $font . '"><p class="d-muted" style="margin:0;font-size:13px;line-height:1.55;' . $muted . '">Linket virker i ' . $h($v['link_udloeb']) . ' og kan kun bruges én gang. Er det udløbet? <a href="' . $h($v['nyt_link_url']) . '" class="d-link" style="' . $link . '">Få et nyt link</a>.</p></td></tr>'
			// login box
			. '<tr><td class="m-pad" style="padding:28px 40px 0;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="d-soft" style="background:#F7F4EF;border:1px solid #E3DCD2;border-radius:8px;"><tr><td style="padding:20px 24px;' . $font . '">'
			. '<p class="d-muted" style="margin:0 0 10px;font-size:12px;line-height:1.4;letter-spacing:0.12em;text-transform:uppercase;' . $muted . '">Jeres login</p>'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-size:15px;line-height:1.6;">'
			. '<tr><td class="d-muted" style="padding:2px 16px 2px 0;' . $muted . 'white-space:nowrap;">Regnskab</td><td class="d-text" style="padding:2px 0;' . $ink . 'font-weight:600;">' . $h($v['regnskab']) . '</td></tr>'
			. '<tr><td class="d-muted" style="padding:2px 16px 2px 0;' . $muted . 'white-space:nowrap;">Brugernavn</td><td class="d-text" style="padding:2px 0;' . $ink . 'font-weight:600;">' . $h($v['email']) . '</td></tr>'
			. '<tr><td class="d-muted" style="padding:2px 16px 2px 0;' . $muted . 'white-space:nowrap;">Adgangskode</td><td class="d-body" style="padding:2px 0;' . $body . '">Vælges, når I aktiverer regnskabet</td></tr>'
			. '</table>'
			. ($v['navn_fra_cvr'] !== '' ? '<p class="d-muted" style="margin:10px 0 0;font-size:13px;line-height:1.5;' . $muted . '">Navnet er hentet fra CVR og bruges, når I logger ind. I kan rette det, når I aktiverer regnskabet.</p>' : '')
			. '</td></tr></table></td></tr>';
		if (!$newlink) {
			$out .= '<tr><td class="m-pad" style="padding:32px 40px 0;' . $font . '">'
				. '<h2 class="d-text" style="margin:0 0 12px;font-size:18px;line-height:1.3;font-weight:600;' . $ink . '">Sådan kommer I i gang</h2>'
				. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:15px;line-height:1.55;">'
				. '<tr><td valign="top" style="width:28px;padding:6px 0;color:#8B33D4;font-weight:600;">1</td><td class="d-body" style="padding:6px 0;' . $body . '"><strong class="d-text" style="' . $ink . '">Aktivér regnskabet</strong> med knappen ovenfor, og vælg jeres adgangskode.</td></tr>'
				. '<tr><td valign="top" style="width:28px;padding:6px 0;color:#8B33D4;font-weight:600;">2</td><td class="d-body" style="padding:6px 0;' . $body . '"><strong class="d-text" style="' . $ink . '">Brug jeres egne data.</strong> Importér kontoplan, kunder og varer – <a href="' . $h($v['guide_url']) . '" class="d-link" style="' . $link . '">se hvordan i brugervejledningen</a>.</td></tr>'
				. '<tr><td valign="top" style="width:28px;padding:6px 0;color:#8B33D4;font-weight:600;">3</td><td class="d-body" style="padding:6px 0;' . $body . '"><strong class="d-text" style="' . $ink . '">Spørg Saldi Assistenten.</strong> Chatten inde i Saldi svarer på spørgsmål ud fra brugervejledningen, mens I arbejder. Vores danske support hjælper også på telefon og mail.</td></tr>'
				. '</table></td></tr>';
		}
		$out .= '<tr><td class="m-pad" style="padding:28px 40px 0;' . $font . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-top:1px solid #E3DCD2;"><tr><td style="padding:24px 0 0;">'
			. '<h2 class="d-text" style="margin:0 0 8px;font-size:18px;line-height:1.3;font-weight:600;' . $ink . '">Om jeres prøveperiode</h2>'
			. '<p class="d-body" style="margin:0;font-size:15px;line-height:1.6;' . $body . '">I får ' . $h($v['trial_days']) . ' dage gratis på <strong class="d-text" style="' . $ink . '">' . $h($v['pakke']) . '</strong>, regnet fra I aktiverer regnskabet. Derefter fortsætter kontoen på ' . $h($v['pakke']) . ' til ' . $h($v['pris']) . ' kr./md. ekskl. moms, medmindre I skifter pakke eller opsiger inden prøveperiodens udløb. I bliver først faktureret efter prøveperioden, og der er ingen binding.</p>'
			. '<p style="margin:12px 0 0;font-size:15px;line-height:1.6;"><a href="' . $h($v['priser_url']) . '" class="d-link" style="' . $link . 'font-weight:600;">Se pakker og priser</a></p>'
			. '</td></tr></table></td></tr>'
			. '<tr><td class="m-pad" style="padding:28px 40px 40px;' . $font . '">'
			. '<p class="d-body" style="margin:0;font-size:15px;line-height:1.6;' . $body . '">Brug for hjælp? Ring på <a href="tel:+45' . $h($phoneTel) . '" class="d-link" style="color:#143A70;text-decoration:none;font-weight:600;white-space:nowrap;">' . $h($v['support_phone']) . '</a>, eller skriv til <a href="mailto:' . $h($v['support_email']) . '" class="d-link" style="color:#143A70;text-decoration:none;font-weight:600;">' . $h($v['support_email']) . '</a>.</p>'
			. '<p class="d-body" style="margin:16px 0 0;font-size:15px;line-height:1.6;' . $body . '">Venlig hilsen<br><strong class="d-text" style="' . $ink . '">Saldi-teamet</strong></p>'
			. '</td></tr>'
			. '</table></td></tr>'
			// footer
			. '<tr><td class="m-pad" style="padding:24px 32px 0;font-family:Arial,Helvetica,sans-serif;">'
			. '<p class="d-muted" style="margin:0;font-size:12px;line-height:1.6;' . $muted . '">I modtager denne mail, fordi ' . $h($v['email']) . ' blev brugt til at oprette et Saldi-regnskab. Var det ikke jer, kan I se bort fra mailen – regnskabet bliver ikke aktiveret.</p>'
			. '<p class="d-muted" style="margin:12px 0 0;font-size:12px;line-height:1.6;' . $muted . '">' . $h($v['footer']) . '</p>'
			. '</td></tr>'
			. '</table></td></tr></table></body></html>';
		return $out;
	}
}

if (!function_exists('signup_mail_text')) {
	/**
	 * The plain-text version of the mail.
	 *
	 * @param array  $v       from signup_mail_vars()
	 * @param string $variant 'welcome' | 'newlink'
	 * @return string
	 */
	function signup_mail_text(array $v, $variant = 'welcome') {
		$newlink = ($variant === 'newlink');
		$t = ($newlink ? 'Jeres nye aktiveringslink' : 'Velkommen til Saldi' . ($v['fornavn'] !== '' ? ', ' . $v['fornavn'] : '')) . "\n\n";
		$t .= ($newlink
			? "I bad om et nyt link. Aktivér regnskabet for {$v['firmanavn']}, og vælg jeres adgangskode – så starter jeres {$v['trial_days']} dages gratis prøveperiode. Det gamle link virker ikke længere."
			: "Tak for jeres oprettelse. Aktivér regnskabet for {$v['firmanavn']}, og vælg jeres adgangskode – så starter jeres {$v['trial_days']} dages gratis prøveperiode.") . "\n\n";
		$t .= "Aktivér regnskabet:\n{$v['aktiveringslink']}\n\n";
		$t .= "Linket virker i {$v['link_udloeb']} og kan kun bruges én gang. Er det udløbet, kan I få et nyt link her:\n{$v['nyt_link_url']}\n\n";
		$t .= "JERES LOGIN\nRegnskab:    {$v['regnskab']}\nBrugernavn:  {$v['email']}\nAdgangskode: Vælges, når I aktiverer regnskabet\n";
		$t .= ($v['navn_fra_cvr'] !== '' ? "Navnet er hentet fra CVR og bruges, når I logger ind. I kan rette det, når I aktiverer regnskabet.\n" : '') . "\n";
		if (!$newlink) {
			$t .= "SÅDAN KOMMER I I GANG\n";
			$t .= "1. Aktivér regnskabet med linket ovenfor, og vælg jeres adgangskode.\n";
			$t .= "2. Brug jeres egne data. Importér kontoplan, kunder og varer – se hvordan i brugervejledningen: {$v['guide_url']}\n";
			$t .= "3. Spørg Saldi Assistenten. Chatten inde i Saldi svarer på spørgsmål ud fra brugervejledningen, mens I arbejder. Vores danske support hjælper også på telefon og mail.\n\n";
		}
		$t .= "OM JERES PRØVEPERIODE\n";
		$t .= "I får {$v['trial_days']} dage gratis på {$v['pakke']}, regnet fra I aktiverer regnskabet. Derefter fortsætter kontoen på {$v['pakke']} til {$v['pris']} kr./md. ekskl. moms, medmindre I skifter pakke eller opsiger inden prøveperiodens udløb. I bliver først faktureret efter prøveperioden, og der er ingen binding.\n";
		$t .= "Se pakker og priser: {$v['priser_url']}\n\n";
		$t .= "Brug for hjælp? Ring på {$v['support_phone']}, eller skriv til {$v['support_email']}.\n\nVenlig hilsen\nSaldi-teamet\n\n--\n";
		$t .= "I modtager denne mail, fordi {$v['email']} blev brugt til at oprette et Saldi-regnskab. Var det ikke jer, kan I se bort fra mailen – regnskabet bliver ikke aktiveret.\n{$v['footer']}\n";
		return $t;
	}
}

if (!function_exists('signup_mail_transport')) {
	/**
	 * The transport settings: SMTP_* environment variables (docker stack) first, then the smtp_*
	 * signup settings.
	 *
	 * @return array{host: string, port: int, user: string, pass: string, secure: string, from: string, from_name: string, reply_to: string, bcc: string}
	 */
	function signup_mail_transport() {
		$env = function ($name, $setting, $default = '') {
			$v = getenv($name);
			return ($v !== false && $v !== '') ? $v : signup_setting($setting, $default);
		};
		$starttls = getenv('SMTP_STARTTLS');
		$tls = getenv('SMTP_TLS');
		$secure = signup_setting('smtp_secure', '');
		if ($starttls === 'on') {
			$secure = 'tls';
		} elseif ($tls === 'on') {
			$secure = 'ssl';
		}
		return array(
			'host' => $env('SMTP_HOST', 'smtp_host'),
			'port' => (int)$env('SMTP_PORT', 'smtp_port', '587'),
			'user' => $env('SMTP_USER', 'smtp_user'),
			'pass' => $env('SMTP_PASS', 'smtp_pass'),
			'secure' => $secure,
			'from' => $env('SMTP_FROM', 'from_email', 'noreply@saldi.dk'),
			'from_name' => signup_setting('from_name', 'Saldi'),
			'reply_to' => signup_setting('reply_to', signup_setting('support_email', 'info@saldi.dk')),
			'bcc' => signup_setting('bcc', ''),
		);
	}
}

if (!function_exists('signup_phpmailer_available')) {
	/**
	 * Loads PHPMailer if a composer autoload with it exists in the installation root or the
	 * directory above it (the shared vendor/ on the saldi servers).
	 *
	 * @return bool
	 */
	function signup_phpmailer_available() {
		if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
			return true;
		}
		foreach (array(__DIR__ . '/../../vendor/autoload.php', __DIR__ . '/../../../vendor/autoload.php') as $auto) {
			if (file_exists($auto)) {
				require_once $auto;
				if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
					return true;
				}
			}
		}
		return false;
	}
}

if (!function_exists('signup_send_mail')) {
	/**
	 * Sends one HTML + text mail. Returns false (and logs) when the transport refused it.
	 *
	 * @param string $to
	 * @param string $toName
	 * @param string $subject
	 * @param string $html
	 * @param string $text
	 * @return bool
	 */
	function signup_send_mail($to, $toName, $subject, $html, $text) {
		$t = signup_mail_transport();
		if (signup_phpmailer_available()) {
			try {
				$mail = new PHPMailer\PHPMailer\PHPMailer(true);
				$mail->CharSet = 'UTF-8';
				$mail->Encoding = 'quoted-printable';
				if ($t['host'] !== '') {
					$mail->isSMTP();
					$mail->Host = $t['host'];
					$mail->Port = $t['port'];
					$mail->SMTPAuth = ($t['user'] !== '');
					if ($t['user'] !== '') {
						$mail->Username = $t['user'];
						$mail->Password = $t['pass'];
					}
					$mail->SMTPSecure = $t['secure']; // '' lets PHPMailer upgrade to STARTTLS when offered; certificates are verified
					$mail->SMTPAutoTLS = true;
					$mail->Timeout = 15;
				} else {
					$mail->isMail();
				}
				$mail->setFrom($t['from'], $t['from_name']);
				if ($t['reply_to'] !== '') {
					$mail->addReplyTo($t['reply_to'], $t['from_name']);
				}
				$mail->addAddress($to, $toName);
				if ($t['bcc'] !== '') {
					$mail->addBCC($t['bcc']);
				}
				$mail->isHTML(true);
				$mail->Subject = $subject;
				$mail->Body = $html;
				$mail->AltBody = $text;
				$mail->send();
				return true;
			} catch (Throwable $e) {
				signup_log('mail to ' . $to . ' failed: ' . $e->getMessage());
				return false;
			}
		}
		// No PHPMailer: build the multipart message for mail() (sendmail) by hand.
		$boundary = 'saldi-' . bin2hex(random_bytes(12));
		$encName = '=?UTF-8?B?' . base64_encode($t['from_name']) . '?=';
		$headers = "From: $encName <{$t['from']}>\r\n";
		if ($t['reply_to'] !== '') {
			$headers .= "Reply-To: {$t['reply_to']}\r\n";
		}
		if ($t['bcc'] !== '') {
			$headers .= "Bcc: {$t['bcc']}\r\n";
		}
		$headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
		$bodyOut = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
			. "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
			. "--$boundary--\r\n";
		$ok = mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $bodyOut, $headers);
		if (!$ok) {
			signup_log('mail() to ' . $to . ' failed');
		}
		return $ok;
	}
}

if (!function_exists('signup_send_activation_mail')) {
	/**
	 * Renders and sends the welcome mail (or the "new link" mail) for a pending signup and counts it.
	 *
	 * @param array  $row     signup_pending row
	 * @param string $token   the activation token in clear text
	 * @param string $variant 'welcome' | 'newlink'
	 * @return bool
	 */
	function signup_send_activation_mail(array $row, $token, $variant = 'welcome') {
		$v = signup_mail_vars($row, $token);
		$ok = signup_send_mail($row['email'], $row['navn'], signup_mail_subject($variant), signup_mail_html($v, $variant), signup_mail_text($v, $variant));
		if ($ok) {
			signup_mark_mailed($row['id']); // the 'mail' limit is claimed by the caller before sending (opret.php, nytlink.php)
		}
		return $ok;
	}
}
