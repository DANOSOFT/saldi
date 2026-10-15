<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/automation/SaldiMailer.php --- ai/mcp-assistant --- 2026.10.02
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// CLI-safe tenant mail configuration and a testable delivery boundary; no body logging.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

final class SaldiMailer
{
	private ?Closure $transport;
	public function __construct(?callable $transport = null)
	{
		$this->transport = $transport === null ? null : Closure::fromCallable($transport);
	}
	/** @return array{status:string,error?:string} */
	public function send(SaldiDb $db, string $to, string $subject, string $text, ?string $html = null): array
	{
		if (getenv('SALDI_MAIL_DISABLED') === '1') {
			return ['status' => 'skipped'];
		}
		$company = $db->one("SELECT email, firmanavn, felt_1, felt_2, felt_3, felt_4 FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1") ?? [];
		$config = [
			'host' => trim((string)($company['felt_1'] ?? '')),
			'user' => (string)($company['felt_2'] ?? ''), 'password' => (string)($company['felt_3'] ?? ''),
			'encryption' => (string)($company['felt_4'] ?? ''),
			'from_email' => trim(SaldiSettings::get($db, 'email_settings', 'sender_email', '')) ?: trim((string)($company['email'] ?? '')),
			'from_name' => trim(SaldiSettings::get($db, 'email_settings', 'sender_name', '')) ?: trim((string)($company['firmanavn'] ?? '')),
		];
		if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $subject . $config['from_name'])) {
			return ['status' => 'failed', 'error' => 'invalid mail address or header'];
		}
		$message = ['to' => $to, 'subject' => $subject, 'text' => $text, 'html' => $html ?? '<html><body><p>' . nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p></body></html>'];
		try {
			$sent = $this->transport ? ($this->transport)($config, $message) : $this->deliver($config, $message);
			return $sent === true ? ['status' => 'sent'] : ['status' => 'failed', 'error' => 'mail delivery failed'];
		} catch (Throwable) {
			return ['status' => 'failed', 'error' => 'mail delivery failed'];
		}
	}
	/** Deliver only through the locally installed PHPMailer. */
	private function deliver(array $config, array $message): bool
	{
		$root = dirname(__DIR__, 2);
		foreach ([$root . '/../vendor/autoload.php', $root . '/vendor/autoload.php'] as $autoload) {
			if (is_file($autoload)) {
				require_once $autoload;
				if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
					break;
				}
			}
		}
		$mail = new \PHPMailer\PHPMailer\PHPMailer(true);
		$mail->CharSet = 'UTF-8';
		$mail->SMTPDebug = 0;
		$mail->Timeout = 10;
		if ($config['host'] === '' || strtolower($config['host']) === 'localhost') {
			$mail->isMail();
		} else {
			$mail->isSMTP();
			$mail->Host = $config['host'];
			$mail->SMTPAuth = $config['user'] !== '';
			$mail->Username = $config['user'];
			$mail->Password = $config['password'];
			$mail->SMTPSecure = $config['encryption'];
		}
		$mail->setFrom($config['from_email'], $config['from_name']);
		$mail->addAddress($message['to']);
		$mail->isHTML(true);
		$mail->Subject = $message['subject'];
		$mail->Body = $message['html'];
		$mail->AltBody = $message['text'];
		return $mail->send();
	}
}
