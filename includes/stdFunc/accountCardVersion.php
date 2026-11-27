<?php
// includes/stdFunc/accountCardVersion.php
//
// History:
// 20261105 CL/LH Created. A debtor or creditor card saved from a form drawn before a colleague's save wrote that save's fields back to their old values (testlab concurrent-editing DEVY-2: the colleague's Bemærkning was blanked).
//                The debtor card, the Sager customer card and both creditor cards now carry the version of the values they were drawn with, and their Gem compares it and writes in one transaction that locks the account first.
//                A Gem of an existing card whose form carries no version is refused too: PHP drops the fields after max_input_vars (1000), so a version printed after the contact persons could be lost while the fields before it were saved.
// 20261115 CL/ASR Integration: account_card_columns() and account_card_version() no longer name debitor/debitorkort.php as a writer; decision T2 gave the debtor card master's MB-82 check (debitorkortIncludes/staleSave.php).

if (!function_exists('account_card_columns')) {
/**
 * The adresser columns a debtor or creditor card's Gem writes from its form: debitor/debkort_save.php (the Sager
 * customer card), kreditor/kreditorkort.php and includes/kreditorkort.php. The debtor card (debitor/debitorkort.php)
 * uses its own check (debitor/debitorkortIncludes/staleSave.php, MB-82) and not this list.
 * Columns other pages keep up to date (saldo, invoiced, bank_unconfirmed, modtime) are not here:
 * a change to them is not one the card would undo.
 *
 * @return list<string>
 */
function account_card_columns() {
	return array(
		'kontonr', 'firmanavn', 'fornavn', 'efternavn', 'addr1', 'addr2', 'postnr', 'bynavn', 'land', 'kontakt',
		'tlf', 'mobile', 'email', 'mailfakt', 'web', 'betalingsdage', 'kreditmax', 'betalingsbet', 'cvrnr', 'ean',
		'institution', 'notes', 'gruppe', 'kontoansvarlig', 'bank_navn', 'bank_reg', 'bank_konto', 'bank_fi', 'erh',
		'swift', 'pbs_nr', 'pbs', 'kontotype', 'lev_firmanavn', 'lev_fornavn', 'lev_efternavn', 'lev_addr1', 'lev_addr2',
		'lev_postnr', 'lev_bynavn', 'lev_land', 'lev_kontakt', 'lev_tlf', 'lev_email', 'felt_1', 'felt_2', 'felt_3',
		'felt_4', 'felt_5', 'vis_lev_addr', 'lukket', 'stripe_fravalg', 'kategori', 'rabatgruppe', 'status',
		'productlimit', 'enduser_type',
	);
}
}

if (!function_exists('account_card_version')) {
/**
 * The version of an account card: a hash of its account_card_columns() and, when $contacts is given, of the
 * account's kontakt_emails and delivery_addresses rows. No card passes them now: the debtor card, which saves those
 * rows, uses its own check (debitor/debitorkortIncludes/staleSave.php, MB-82).
 * A column the tenant does not have (enduser_type before the packaging module) is left out, NULL counts as '',
 * and the rows count in id order, whatever order they were read in.
 *
 * @param array<int|string, mixed>|false|null $account the adresser row (db_fetch_array()); false or [] for no account
 * @param array<string, list<array<int|string, mixed>>>|null $contacts ['kontakt_emails' => rows, 'delivery_addresses' => rows]
 *        to include those rows, null for a card without them
 * @return string '' when there is no account
 */
function account_card_version($account, $contacts = null) {
	if (!is_array($account) || !$account) {
		return '';
	}
	$card = array();
	foreach (account_card_columns() as $column) {
		if (array_key_exists($column, $account)) {
			$card[$column] = (string)$account[$column];
		}
	}
	if ($contacts !== null) {
		foreach (array('kontakt_emails', 'delivery_addresses') as $table) {
			$card[$table] = array();
			foreach ($contacts[$table] ?? array() as $row) {
				$fields = array();
				foreach ($row as $name => $value) {
					if (is_string($name)) {
						$fields[$name] = (string)$value;
					}
				}
				$card[$table][(int)$row['id']] = $fields;
			}
			ksort($card[$table]);
		}
	}
	return md5(serialize($card));
}
}

if (!function_exists('account_card_version_field')) {
/**
 * The hidden field that carries the card's version in its form, for account_card_begin_save().
 * It is the version of the rows the form's fields were drawn from: a card read again for this field could already
 * hold a colleague's save that the fields do not show, and the old fields would then pass as current.
 * A value the page itself writes to the card while drawing it goes into $account too.
 * Print it at the top of the form, before any control the form repeats per row (contact persons, categories): PHP
 * keeps only the first max_input_vars (1000) fields of a post, and a card with many rows still saves the fields
 * before the cut.
 *
 * @param array<int|string, mixed>|false|null $account the adresser row the form was drawn from, [] for a new card
 * @param array<string, list<array<int|string, mixed>>>|null $contacts as for account_card_version()
 * @return string
 */
function account_card_version_field($account, $contacts = null) {
	return "<input type=hidden name=card_version value='" . account_card_version($account, $contacts) . "'>\n";
}
}

if (!function_exists('account_card_stored_version')) {
/**
 * The version of the card as it is stored, read by account_card_begin_save() and account_card_end_save().
 *
 * @param int $id adresser.id
 * @param bool $withContacts with the debtor card's kontakt_emails and delivery_addresses rows
 * @return string
 */
function account_card_stored_version($id, $withContacts) {
	$id = (int)$id;
	$account = db_fetch_array(db_select("select * from adresser where id = $id", __FILE__ . " linje " . __LINE__));
	$contacts = null;
	if ($withContacts) {
		$contacts = array();
		foreach (array('kontakt_emails' => 'konto_id', 'delivery_addresses' => 'account_id') as $table => $owner) {
			$contacts[$table] = array();
			$q = db_select("select * from $table where $owner = $id", __FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$contacts[$table][] = $r;
			}
		}
	}
	return account_card_version($account, $contacts);
}
}

if (!function_exists('account_card_begin_save')) {
/**
 * Opens the transaction a card's Gem writes in, and says whether it may write.
 *
 * The transaction's first statement locks the account's adresser row, held until account_card_end_save() commits:
 * a colleague's Gem of the same card waits here until this one is written, and then compares its form with this
 * save. Comparing first and locking later would let both pass the comparison before either has written, and the
 * second write would put the first one's fields back.
 * Nothing may be read in the transaction before the lock. MySQL and MariaDB (InnoDB, REPEATABLE READ) read a whole
 * transaction from the snapshot of its first plain read, so a read before the lock would hide a save committed while
 * this one waited for it. A locking read takes no snapshot. PostgreSQL (READ COMMITTED) takes one per statement.
 *
 * Refused, with the transaction rolled back: a form drawn before the card was last saved (the posted card_version
 * is not the stored one), unless it is the same form this session saved last and nobody has saved the card since
 * (a double click, or a reload of the page the save answered with).
 * Refused before the transaction: a form without a valid version, as nothing says which card it was drawn from. That
 * is a post PHP cut at max_input_vars before the version, or a form drawn before cards carried one. Writing it would
 * put back whatever it holds over a colleague's save, and blank the fields it lost.
 * account_card_stale_alert() says which of the two refused the Gem.
 * A new card (id 0) opens no transaction.
 *
 * Call it before anything of the save is written, and account_card_end_save() on every way out of the save.
 *
 * @param int|string $id adresser.id
 * @param array<string, mixed> $form the posted form ($_POST)
 * @param bool $withContacts with the debtor card's kontakt_emails and delivery_addresses rows
 * @return bool false when the save is refused
 */
function account_card_begin_save($id, array $form, $withContacts = false) {
	global $account_card_open_save, $account_card_refusal;
	$account_card_refusal = '';
	$id = (int)$id;
	if ($id <= 0) {
		return true;
	}
	$posted = isset($form['card_version']) && is_string($form['card_version']) ? $form['card_version'] : '';
	if (!preg_match('/^[0-9a-f]{32}$/', $posted)) {
		$account_card_refusal = 'unversioned';
		return false;
	}
	transaktion('begin');
	db_fetch_array(db_select("select id from adresser where id = $id for update", __FILE__ . " linje " . __LINE__));
	$stored = account_card_stored_version($id, $withContacts);
	if ($posted !== $stored) {
		$saved = $_SESSION['account_card_saves'][account_card_session_key($id)] ?? array();
		$resent = is_array($saved) && ($saved['from'] ?? null) === $posted && ($saved['form'] ?? null) === md5(serialize($form))
			&& ($saved['to'] ?? null) === $stored;
		if (!$resent) {
			transaktion('rollback');
			$account_card_refusal = 'stale';
			return false;
		}
	}
	$account_card_open_save = array('id' => $id, 'from' => $posted, 'form' => md5(serialize($form)), 'withContacts' => (bool)$withContacts);
	return true;
}
}

if (!function_exists('account_card_end_save')) {
/**
 * Ends the transaction account_card_begin_save() opened: commits it, or rolls it back when a write failed on a path
 * where db_modify() returns instead of exiting ($db_modify_fejl). Does nothing when no card save is open.
 * With $saved, the session notes this form's save, so that account_card_begin_save() lets the same form through
 * again while the card is as this save left it.
 *
 * @param bool $saved false on a way out that did not write the card (a refused field, a move to kontofusion.php)
 * @return void
 */
function account_card_end_save($saved = true) {
	global $account_card_open_save, $db_modify_fejl;
	if (empty($account_card_open_save)) {
		return;
	}
	$save = $account_card_open_save;
	$account_card_open_save = null;
	if (!empty($db_modify_fejl)) {
		transaktion('rollback');
		return;
	}
	if ($saved && session_status() === PHP_SESSION_ACTIVE) {
		$_SESSION['account_card_saves'][account_card_session_key($save['id'])] = array(
			'from' => $save['from'],
			'form' => $save['form'],
			'to' => account_card_stored_version($save['id'], $save['withContacts']),
		);
	}
	transaktion('commit');
}
}

if (!function_exists('account_card_session_key')) {
/**
 * The key of an account's last save in the session; a session can move to another regnskab.
 *
 * @param int|string $id adresser.id
 * @return string
 */
function account_card_session_key($id) {
	global $db;
	return $db . ':' . (int)$id;
}
}

if (!function_exists('account_card_stale_alert')) {
/**
 * The alert a card prints when account_card_begin_save() refuses its Gem, optionally followed by a move to $goTo:
 * the card was saved since the form was drawn, or the form carried no version.
 * Needs findtekst() and jsString() (includes/std_func.php).
 *
 * @param string $goTo address to open after the alert, '' to stay on the page
 * @return string A <script> block that shows the text in the user's language.
 */
function account_card_stale_alert($goTo = '') {
	global $sprog_id, $account_card_refusal;
	if (($account_card_refusal ?? '') === 'unversioned') {
		$text = findtekst('account.card_form_incomplete|Kortet er ikke gemt, fordi formularen ikke kom helt frem. Kortet vises nu, som det er gemt, så du kan indtaste dine ændringer igen.', $sprog_id);
	} else {
		$text = findtekst('account.card_saved_since_opened|Kortet er blevet gemt af en anden eller i et andet vindue, efter du åbnede det. Dine ændringer er ikke gemt. Kortet vises nu, som det er gemt, så du kan indtaste dem igen.', $sprog_id);
	}
	$script = "alert(" . jsString(array($text)) . ");";
	if ($goTo !== '') {
		$script .= " window.location.href = " . jsString(array($goTo)) . ";";
	}
	return "<script>$script</script>\n";
}
}
