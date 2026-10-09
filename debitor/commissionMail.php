<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/commissionMail.php -----patch 5.1.0 ----206-09-15--------------
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
// Copyright (c) 2003-2026 Danosoft.dk ApS
// ----------------------------------------------------------------------
// Kommission view - separate file for better grid differentiation
// 20260629 PHR/CL Make "Vælg alle" check invite boxes in the grid view.
// 20260915 CDX/PHR Restore server sender/Reply-To and own SMTP for MySale invitations.

// 20260915 CDX/PHR Configure MySale invitation sender and transport from shop stamdata.

/**
 * Build an invitation mailer without sending mail.
 *
 * @return \PHPMailer\PHPMailer\PHPMailer
 */
function commissionMail(array $sender, $database, $serverName, $charset) {
	require_once(__DIR__ . '/../../vendor/autoload.php');

	$mail = new \PHPMailer\PHPMailer\PHPMailer();
	$mail->CharSet = $charset;
	$mail->isHTML(true);
	$smtp = trim((string) ($sender['felt_1'] ?? ''));
	$shopEmails = explode(';', str_replace(',', ';', (string) ($sender['email'] ?? '')));
	$replyTo = trim($shopEmails[0]);
	$senderName = (string) ($sender['firmanavn'] ?? '');

	if ($smtp !== '' && strcasecmp($smtp, 'localhost') !== 0) {
		$mail->isSMTP();
		$mail->Host = $smtp;
		$mail->Timeout = 10;
		$mail->Username = (string) ($sender['felt_2'] ?? '');
		$mail->Password = (string) ($sender['felt_3'] ?? '');
		$mail->SMTPAuth = $mail->Username !== '';
		$mail->SMTPSecure = (string) ($sender['felt_4'] ?? '');
		$from = $replyTo;
	} else {
		$mail->isMail();
		$from = $database . '@' . $serverName;
	}

	if (!$mail->setFrom($from, $senderName)) {
		throw new \InvalidArgumentException('Ugyldig afsenderadresse til invitation.');
	}
	// Use the same address for the envelope sender; replies go to the shop.
	$mail->Sender = $from;
	if ($replyTo !== '' && !$mail->addReplyTo($replyTo, $senderName)) {
		throw new \InvalidArgumentException('Ugyldig svaradresse til invitation.');
	}
	return $mail;
}
