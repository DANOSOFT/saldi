<?php
// --- includes/partnerScope.php --- ver 5.1.0 --- 2026.10.10 ---
// LICENSE: GNU GPL v2 or later; see admin/vis_regnskaber.php for the full notice.
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Partner layer (bogholder/revisor firms and koncern parents) over regnskaber, phase P1 of
//                  Requirements_accountant_and_operator_panels_EN.md. Master-only. Creates its own tables on
//                  first use and never alters existing tables: the link user -> partner lives in partner_users,
//                  not as a column on brugere. Scope resolution replaces the adgang_til list in
//                  brugere.rettigheder for users who belong to a partner; everyone else keeps the legacy rule.

if (!function_exists('partner_tables_ensure')) {

function partner_tables_ensure() {
	static $done = false;
	if ($done) return;
	$done = true;
	$r = db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'partner_log'", __FILE__ . " linje " . __LINE__));
	if ($r) return;
	$sql = array(
		"CREATE TABLE IF NOT EXISTS partners (id SERIAL PRIMARY KEY, kind varchar(10) NOT NULL DEFAULT 'bogholder', name varchar(120) NOT NULL, cvr varchar(20), home_regnskab_id int, status varchar(10) NOT NULL DEFAULT 'active', can_create boolean NOT NULL DEFAULT false, can_manage_features boolean NOT NULL DEFAULT true, can_manage_users boolean NOT NULL DEFAULT true, created timestamp NOT NULL DEFAULT now(), created_by int)",
		"CREATE TABLE IF NOT EXISTS partner_users (bruger_id int PRIMARY KEY, partner_id int NOT NULL, partner_role varchar(20) NOT NULL DEFAULT 'employee', created timestamp NOT NULL DEFAULT now())",
		"CREATE TABLE IF NOT EXISTS regnskab_partners (id SERIAL PRIMARY KEY, regnskab_id int NOT NULL, partner_id int NOT NULL, access varchar(10) NOT NULL DEFAULT 'full', since timestamp NOT NULL DEFAULT now(), until timestamp, created_by int, note text)",
		"CREATE UNIQUE INDEX IF NOT EXISTS regnskab_partners_active ON regnskab_partners(regnskab_id, partner_id) WHERE until IS NULL",
		"CREATE UNIQUE INDEX IF NOT EXISTS regnskab_partners_owner ON regnskab_partners(regnskab_id) WHERE until IS NULL AND access = 'owner'",
		"CREATE TABLE IF NOT EXISTS partner_user_access (bruger_id int NOT NULL, regnskab_id int NOT NULL, PRIMARY KEY (bruger_id, regnskab_id))",
		"CREATE TABLE IF NOT EXISTS partner_log (id SERIAL PRIMARY KEY, ts timestamp NOT NULL DEFAULT now(), actor_bruger_id int, actor_partner_id int, impersonated_by int, regnskab_id int, partner_id int, action varchar(60) NOT NULL, details text)",
	);
	foreach ($sql as $q) db_modify($q, __FILE__ . " linje " . __LINE__);
}
// 20261011 CL/ASR Group billing on the partner (own table): 'individual' (each ledger pays) or 'consolidated' (billed via
//                  billing_regnskab_id, default the home ledger). Per-ledger exceptions live in regnskab_billing.payer_regnskab_id.
function partner_billing_cols_ensure() {
	static $d = false; if ($d) return; $d = true;
	if (!db_fetch_array(db_select("SELECT 1 FROM information_schema.columns WHERE table_name = 'partners' AND column_name = 'billing_mode'", __FILE__ . " linje " . __LINE__)))
		db_modify("ALTER TABLE partners ADD COLUMN billing_mode varchar(12) NOT NULL DEFAULT 'individual', ADD COLUMN billing_regnskab_id int", __FILE__ . " linje " . __LINE__);
}
// Move a ledger from one partner to another: the old link gets an end date, a new one starts. Nothing on the ledger
// (lifecycle, snapshots, log, entitlements) is touched, so history stays with the ledger.
function partner_move_ledger($rid, $from_pid, $to_pid, $access, $note) {
	$rid = (int)$rid; $from_pid = (int)$from_pid; $to_pid = (int)$to_pid;
	$to = db_fetch_array(db_select("select * from partners where id = '$to_pid' and status = 'active'", __FILE__ . " linje " . __LINE__)); if (!$to) return 'Ukendt partner';
	if ($access == 'owner' && db_fetch_array(db_select("select 1 from regnskab_partners where regnskab_id = '$rid' and access = 'owner' and until is null and partner_id <> '$from_pid'", __FILE__ . " linje " . __LINE__))) return 'Regnskabet har allerede en anden ejer';
	db_modify("update regnskab_partners set until = now() where regnskab_id = '$rid' and partner_id = '$from_pid' and until is null", __FILE__ . " linje " . __LINE__);
	db_modify("insert into regnskab_partners (regnskab_id, partner_id, access, created_by, note) values ('$rid', '$to_pid', '".db_escape_string($access)."', '".(int)partner_current_user()['id']."', '".db_escape_string($note)."')", __FILE__ . " linje " . __LINE__);
	db_modify("delete from partner_user_access where regnskab_id = '$rid' and bruger_id in (select bruger_id from partner_users where partner_id = '$from_pid')", __FILE__ . " linje " . __LINE__);
	partner_log('partner.ledger_moved', $rid, $to_pid, "fra partner $from_pid til ".$to['name']." ($access)".($note ? " – $note" : ''));
	return '';
}

// The master user row of the current session.
function partner_current_user() {
	global $brugernavn;
	static $u = null;
	if ($u !== null) return $u;
	$r = db_fetch_array(db_select("select id, brugernavn, rettigheder from brugere where brugernavn = '" . db_escape_string($brugernavn) . "'", __FILE__ . " linje " . __LINE__));
	$u = $r ? $r : array('id' => 0, 'brugernavn' => $brugernavn, 'rettigheder' => '');
	list($u['admin'], $u['oprette'], $u['slette'], $tmp) = array_pad(explode(",", $u['rettigheder'], 4), 4, '');
	$u['adgang_til'] = array_filter(array_map('trim', explode(",", $tmp)));
	$u['is_operator'] = ($u['admin'] == 'on');
	$p = db_fetch_array(db_select("select pu.partner_id, pu.partner_role, p.kind, p.name, p.can_create, p.home_regnskab_id from partner_users pu join partners p on p.id = pu.partner_id where pu.bruger_id = '" . (int)$u['id'] . "' and p.status = 'active'", __FILE__ . " linje " . __LINE__));
	$u['partner'] = $p ? $p : null;
	return $u;
}

// Ledger ids the current user may see. NULL = all (operator).
function partner_ledgers() {
	$u = partner_current_user();
	if ($u['is_operator']) return null;
	$ids = array();
	if ($u['partner']) {
		$pid = (int)$u['partner']['partner_id'];
		$scoped = array();
		$q = db_select("select regnskab_id from partner_user_access where bruger_id = '" . (int)$u['id'] . "'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) $scoped[] = (int)$r['regnskab_id'];
		$q = db_select("select regnskab_id from regnskab_partners where partner_id = '$pid' and until is null", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) { $id = (int)$r['regnskab_id']; if (!$scoped || in_array($id, $scoped)) $ids[] = $id; }
		if ($u['partner']['home_regnskab_id'] && $u['partner']['partner_role'] == 'owner') $ids[] = (int)$u['partner']['home_regnskab_id'];
		return array_values(array_unique($ids));
	}
	foreach ($u['adgang_til'] as $a) if ($a === '*') return null; elseif ((int)$a) $ids[] = (int)$a; # legacy rule
	return $ids;
}

// $what: open | users | features | create | manage (operator-only)
function partner_can($regnskab_id, $what = 'open') {
	$u = partner_current_user();
	if ($u['is_operator']) return true;
	if ($what == 'manage') return false;
	if ($what == 'create') return $u['partner'] && $u['partner']['can_create'] === 't';
	$ids = partner_ledgers();
	if ($ids === null) return true;
	return in_array((int)$regnskab_id, $ids);
}

function partner_log($action, $regnskab_id = null, $partner_id = null, $details = '') {
	$u = partner_current_user();
	$pid = $partner_id !== null ? (int)$partner_id : ($u['partner'] ? (int)$u['partner']['partner_id'] : 'NULL');
	$rid = $regnskab_id !== null ? (int)$regnskab_id : 'NULL';
	db_modify("insert into partner_log (actor_bruger_id, actor_partner_id, regnskab_id, partner_id, action, details) values ('" . (int)$u['id'] . "', " . ($u['partner'] ? (int)$u['partner']['partner_id'] : 'NULL') . ", $rid, " . ($pid === 'NULL' ? 'NULL' : "'$pid'") . ", '" . db_escape_string($action) . "', '" . db_escape_string($details) . "')", __FILE__ . " linje " . __LINE__);
}

// Partners (active links) per ledger: array regnskab_id => array of ['id','name','kind','access']
function partner_links_by_ledger() {
	$out = array();
	$q = db_select("select rp.regnskab_id, rp.access, p.id, p.name, p.kind from regnskab_partners rp join partners p on p.id = rp.partner_id where rp.until is null and p.status = 'active' order by p.name", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out[(int)$r['regnskab_id']][] = $r;
	return $out;
}

function partner_all($kind = null) {
	$out = array();
	$q = db_select("select * from partners where status = 'active'" . ($kind ? " and kind = '" . db_escape_string($kind) . "'" : "") . " order by name", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $out[] = $r;
	return $out;
}

// One-off, idempotent: turn legacy adgang_til lists into partners. Returns number of partners created.
function partner_migrate_adgang_til() {
	$n = 0;
	$q = db_select("select id, brugernavn, rettigheder from brugere order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		list($a, $o, $s, $tmp) = array_pad(explode(",", $r['rettigheder'], 4), 4, '');
		$ids = array_filter(array_map('intval', explode(",", $tmp)));
		if (strpos($tmp, '*') !== false || !$ids) continue;
		if (db_fetch_array(db_select("select 1 from partner_users where bruger_id = '" . (int)$r['id'] . "'", __FILE__ . " linje " . __LINE__))) continue;
		db_modify("insert into partners (kind, name, created_by) values ('bogholder', '" . db_escape_string($r['brugernavn']) . "', '" . (int)partner_current_user()['id'] . "')", __FILE__ . " linje " . __LINE__);
		$p = db_fetch_array(db_select("select max(id) as id from partners", __FILE__ . " linje " . __LINE__));
		$pid = (int)$p['id'];
		db_modify("insert into partner_users (bruger_id, partner_id, partner_role) values ('" . (int)$r['id'] . "', '$pid', 'owner')", __FILE__ . " linje " . __LINE__);
		foreach ($ids as $id) db_modify("insert into regnskab_partners (regnskab_id, partner_id, created_by, note) values ('$id', '$pid', '" . (int)partner_current_user()['id'] . "', 'migreret fra adgang_til')", __FILE__ . " linje " . __LINE__);
		partner_log('partner.migrated', null, $pid, $r['brugernavn'] . ': ' . implode(',', $ids));
		$n++;
	}
	return $n;
}

// 20261012 CL/ASR Partner created together with its own ledger (opret.php "Regnskabstype"). $kind: bogholder|revisor|koncern.
// $login = array(username, password) creates a master login as partner owner (refused if the username exists in master).
function partner_kinds() { return array('bogholder', 'revisor', 'koncern'); }
function partner_login_free($username) { return !db_fetch_array(db_select("select 1 from brugere where brugernavn = '".db_escape_string($username)."'", __FILE__ . " linje " . __LINE__)); }
function partner_create_for_ledger($rid, $kind, $name, $cvr, $can_create, $login = null) {
	if (!in_array($kind, partner_kinds(), true)) return array(0, 'Ukendt type');
	partner_tables_ensure();
	db_modify("insert into partners (kind, name, cvr, home_regnskab_id, can_create, created_by) values ('$kind', '".db_escape_string($name)."', '".db_escape_string($cvr)."', '".(int)$rid."', ".($can_create ? 'true' : 'false').", '".(int)partner_current_user()['id']."')", __FILE__ . " linje " . __LINE__);
	$r = db_fetch_array(db_select("select max(id) as id from partners", __FILE__ . " linje " . __LINE__)); $pid = (int)$r['id'];
	partner_log('partner.created', (int)$rid, $pid, "$kind: $name (med eget regnskab)");
	if ($login && $login[0] !== '' && strlen($login[1]) >= 6) {
		if (!partner_login_free($login[0])) return array($pid, 'Login findes allerede i masterlaget – medarbejder kan tilføjes på partnerkortet');
		db_modify("insert into brugere (brugernavn, rettigheder, status) values ('".db_escape_string($login[0])."', ',,,', true)", __FILE__ . " linje " . __LINE__);
		$u = db_fetch_array(db_select("select id from brugere where brugernavn = '".db_escape_string($login[0])."' order by id desc limit 1", __FILE__ . " linje " . __LINE__)); $bid = (int)$u['id'];
		db_modify("update brugere set kode = '".db_escape_string(saldikrypt($bid, $login[1]))."' where id = '$bid'", __FILE__ . " linje " . __LINE__);
		db_modify("insert into partner_users (bruger_id, partner_id, partner_role) values ('$bid', '$pid', 'owner')", __FILE__ . " linje " . __LINE__);
		partner_log('partner.employee_added', (int)$rid, $pid, "login ".$login[0]." (ejer)");
	}
	return array($pid, '');
}

} // function_exists
?>
