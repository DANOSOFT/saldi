<?php
// includes/stdFunc/stamdataSmtp.php
//
// History:
// 20261105 CL/LH Created. Glemt adgangskode (index/glemt_kode.php, includes/glemt_kode.php) and user_send_mail() (includes/userFunctions.php) choose their mail server and sender here.
//                They logged in to Saldi's own mail server with a login left in felt_2/felt_3, and sent from kan_ikke_besvares@saldi.dk through the regnskab's own server.

if (!function_exists('stamdata_smtp')) {
/**
 * Point a PHPMailer that sends via SMTP at the mail server set under Indstillinger > E-mail, which
 * stamdata keeps in adresser art 'S': felt_1 server[:port], felt_2 user, felt_3 password, felt_4 ssl/tls.
 *
 * A server in felt_1 is the regnskab's own: the mail logs in there when felt_2 is set, uses felt_4, and is
 * sent from the regnskab's own address, the first stamdata e-mail, as sendMail.php sends from it. A server
 * that only takes its own senders refuses kan_ikke_besvares@saldi.dk.
 * Empty or localhost is Saldi's own mail server: anonymous SMTP from $localFrom, as before, so a login or
 * an encryption still in felt_2-felt_4 does not get the mail refused by a relay that offers no AUTH.
 *
 * @param object $mail PHPMailer, composer's class or the legacy one
 * @param array<string, mixed>|false|null $stamdata the adresser art 'S' row with email and felt_1-felt_4, or none
 * @param string $localFrom the sender on Saldi's own mail server
 * @return void
 */
function stamdata_smtp(object $mail, $stamdata, string $localFrom): void {
	$server = is_array($stamdata) ? trim((string)($stamdata['felt_1'] ?? '')) : '';
	$mail->SMTPAuth = false;
	$mail->From = $localFrom;
	if ($server === '' || strtolower($server) === 'localhost') {
		$mail->Host = 'localhost';
		return;
	}
	$mail->Host = $server;
	$user = trim((string)($stamdata['felt_2'] ?? ''));
	if ($user !== '') {
		$mail->SMTPAuth = true;
		$mail->Username = $user;
		$mail->Password = (string)($stamdata['felt_3'] ?? '');
	}
	$encryption = strtolower(trim((string)($stamdata['felt_4'] ?? '')));
	if ($encryption !== '') {
		$mail->SMTPSecure = $encryption;
	}
	$addresses = explode(';', str_replace(',', ';', (string)($stamdata['email'] ?? '')));
	$sender = trim($addresses[0]);
	if (filter_var($sender, FILTER_VALIDATE_EMAIL)) {
		$mail->From = $sender;
	}
}
}
