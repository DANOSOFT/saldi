<?php
// includes/stdFunc/userIpAllowed.php
//
// History:
// 20261105 CL/LH Created. The web login (index/login.php) and the REST login and token refresh (restapi/endpoints/v1/auth/) check a user's IP limit here.
//                Only the web login read brugere.ip_address, so the REST API gave a user with an IP limit a token from any address.

if (!function_exists('user_ip_allowed')) {
/**
 * Whether a user may log in from an address, under the user's IP limit (brugere.ip_address, set under Brugere og
 * roller). The limit lists addresses separated by commas, as systemdata/usersRoles.php saves it; spaces, line breaks
 * and semicolons separate them too, as that page reads them. A limit without any address lets every address in.
 * The client's address must equal one of the listed addresses.
 *
 * @param string|null $allowedIps brugere.ip_address
 * @param string $ip the client's address ($_SERVER['REMOTE_ADDR'])
 * @return bool
 */
function user_ip_allowed($allowedIps, string $ip): bool {
	$allowed = preg_split('/[\s,;]+/', trim((string) $allowedIps), -1, PREG_SPLIT_NO_EMPTY);
	if (!$allowed) {
		return true;
	}
	return in_array($ip, $allowed, true);
}
}
