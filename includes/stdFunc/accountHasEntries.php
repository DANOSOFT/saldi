<?php
// includes/stdFunc/accountHasEntries.php
//
// History:
// 20261105 CL/LH Created. Every path that deletes a debtor or creditor deleted an account that open posts and orders still pointed at.
//                The REST API (restapi/models/customers/CustomerModel.php), api/v2/addresses.php, the debtor and creditor cards and the Sager customer card now ask here first.

if (!function_exists('account_has_entries')) {
/**
 * Whether open posts or orders still point at an account. The debtor and creditor cards offer no
 * Slet for such an account (the debtor card offers Anonymisér): deleting its adresser row leaves them without an owner.
 *
 * @param int $accountId adresser.id
 * @return bool
 */
function account_has_entries($accountId) {
	$accountId = (int)$accountId;
	foreach (array('openpost', 'ordrer') as $table) {
		$q = db_select("select id from $table where konto_id = $accountId limit 1", __FILE__ . " linje " . __LINE__);
		if (db_fetch_array($q)) {
			return true;
		}
	}
	return false;
}
}

if (!function_exists('account_has_entries_alert')) {
/**
 * The alert a card prints when it keeps an account because account_has_entries() is true for it.
 * Needs findtekst() and jsString() (includes/std_func.php).
 *
 * @return string A <script> block that shows the text in the user's language.
 */
function account_has_entries_alert() {
	global $sprog_id;
	$text = findtekst('account.delete_has_entries|Kontoen har åbne poster eller ordrer og kan ikke slettes.', $sprog_id);
	return "<script>alert(" . jsString(array($text)) . ");</script>\n";
}
}
