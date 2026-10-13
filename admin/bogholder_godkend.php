<?php
// --- admin/bogholder_godkend.php --- ver 5.1.0 --- 2026.10.12 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. The accountant panel's one-time links (no login), like godkend.php for proposals:
//                  - an invited employee creates their login in the administration layer (partner_invites, employee);
//                  - an invited customer user creates their login in the customer account (partner_invites,
//                    customer_user; reuses the placeholder/replaced user's row when one is given);
//                  - the customer (or Saldi, when the account has no e-mail) approves or rejects a bookkeeper's
//                    access request (partner_access_requests);
//                  - the customer accepts or rejects taking over payment (partner_requests, payer_to_customer).
//                  Token = 48 hex chars from random_bytes; expired or decided items show a notice only.
include("../includes/connect.php");
if (!function_exists('db_select')) include("../includes/db_query.php");
include("../includes/std_func.php");
include("../includes/partnerScope.php");
include("../includes/partnerPanel.php");
$brugernavn = 'gæst'; $sprog_id = 1;
bp_tables_ensure();
$t = preg_replace('/[^a-f0-9]/', '', (string)if_isset($_REQUEST, '', 't'));
$kind = ''; $row = null;
if (strlen($t) == 48) {
	if ($row = db_fetch_array(db_select("select i.*, p.name as pname, r.regnskab, r.db as rdb from partner_invites i join partners p on p.id = i.partner_id left join regnskab r on r.id = i.regnskab_id where i.token = '$t'", __FILE__ . " linje " . __LINE__))) $kind = 'invite';
	elseif ($row = db_fetch_array(db_select("select a.*, p.name as pname, p.cvr as pcvr, r.regnskab from partner_access_requests a join partners p on p.id = a.partner_id join regnskab r on r.id = a.regnskab_id where a.token = '$t'", __FILE__ . " linje " . __LINE__))) $kind = 'access';
	elseif ($row = db_fetch_array(db_select("select q.*, p.name as pname, r.regnskab from partner_requests q join partners p on p.id = q.partner_id join regnskab r on r.id = q.regnskab_id where q.token = '$t'", __FILE__ . " linje " . __LINE__))) $kind = 'request';
}
$msg = ''; $done = '';
if ($kind == 'invite' && $row['status'] == 'sent' && $row['expires_at'] < date('Y-m-d')) { db_modify("update partner_invites set status = 'expired' where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__); $row['status'] = 'expired'; }
if ($kind == 'access' && $row['status'] == 'open' && $row['expires_at'] < date('Y-m-d')) { db_modify("update partner_access_requests set status = 'expired', decided_at = now() where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__); $row['status'] = 'expired'; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $row) {
	if ($kind == 'invite' && $row['status'] == 'sent') {
		$un = trim((string)if_isset($_POST, '', 'brugernavn')); $pw = (string)if_isset($_POST, '', 'kode'); $pw2 = (string)if_isset($_POST, '', 'kode2');
		if ($un === '' || is_input_too_long($un)) $msg = 'Skriv et brugernavn (højst 80 tegn).';
		elseif (strlen($pw) < 8) $msg = 'Adgangskoden skal være mindst 8 tegn.';
		elseif ($pw !== $pw2) $msg = 'De to adgangskoder er ikke ens.';
		elseif ($row['kind'] == 'employee') {
			if (!partner_login_free($un)) $msg = 'Brugernavnet er optaget – vælg et andet.';
			else {
				$r = db_fetch_array(db_select("insert into brugere (brugernavn, rettigheder, status, email) values ('".db_escape_string($un)."', ',,,', true, '".db_escape_string($row['email'])."') returning id", __FILE__ . " linje " . __LINE__)); $bid = (int)$r['id'];
				db_modify("update brugere set kode = '".db_escape_string(saldikrypt($bid, $pw))."' where id = '$bid'", __FILE__ . " linje " . __LINE__);
				$scope = ($row['scope'] && $row['scope'] != 'all') ? 'selected' : 'all';
				db_modify("insert into partner_users (bruger_id, partner_id, partner_role, rights, scope, name) values ('$bid', '".(int)$row['partner_id']."', '".($row['partner_role'] == 'owner' ? 'owner' : 'employee')."', '".db_escape_string((string)$row['rights'])."', '$scope', '".db_escape_string((string)$row['name'])."')", __FILE__ . " linje " . __LINE__);
				if ($scope == 'selected') foreach (array_filter(array_map('intval', explode(',', $row['scope']))) as $rid) db_modify("insert into partner_user_access (bruger_id, regnskab_id) values ('$bid', '$rid')", __FILE__ . " linje " . __LINE__);
				db_modify("update partner_invites set status = 'accepted', accepted_at = now(), result_user_id = '$bid' where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__);
				partner_log('partner.invite_accepted', null, (int)$row['partner_id'], "medarbejder $un (".$row['email'].")"); partner_log('partner.employee_added', null, (int)$row['partner_id'], "$un (".($row['partner_role'] == 'owner' ? 'owner' : 'employee')."; ".$row['rights']."; ".($scope == 'selected' ? 'udvalgte' : 'alle').") via invitation");
				$done = "<h1>Dit login er oprettet</h1><p>Du er nu medarbejder hos <b>".bp_h($row['pname'])."</b> i Saldi. Log ind med brugernavnet <b>".bp_h($un)."</b> og din adgangskode.</p><p><a class=\"vr-btn vr-primary\" href=\"../index/index.php?regnskab=".rawurlencode($sqdb)."\">Gå til login</a></p>";
			}
		} else { # customer user in the customer account
			global $sqhost, $squser, $sqpass;
			if (!$row['rdb'] || !db_exists($row['rdb']) || !@db_connect($sqhost, $squser, $sqpass, $row['rdb'], __FILE__ . " linje " . __LINE__)) $msg = 'Regnskabet kunne ikke åbnes. Kontakt din bogholder.';
			else {
				$ex = db_fetch_array(db_select("select id from brugere where brugernavn = '".db_escape_string($un)."'", __FILE__ . " linje " . __LINE__));
				$rep = (int)$row['replaces_user_id'];
				if ($ex && (int)$ex['id'] != $rep) { include("../includes/connect.php"); $msg = 'Brugernavnet findes allerede i regnskabet – vælg et andet.'; }
				else {
					$rights = str_repeat($row['role'] === 'readonly' ? '2' : '1', 21);
					if ($rep && db_fetch_array(db_select("select 1 from brugere where id = '$rep'", __FILE__ . " linje " . __LINE__))) { # reuse the seat: the replaced/placeholder user becomes this user
						db_modify("update brugere set brugernavn = '".db_escape_string($un)."', kode = '".db_escape_string(saldikrypt($rep, $pw))."', rettigheder = '$rights', email = '".db_escape_string($row['email'])."' where id = '$rep'", __FILE__ . " linje " . __LINE__); $nid = $rep;
					} else {
						$ry = db_fetch_array(db_select("select max(kodenr) as k from grupper where art = 'RA'", __FILE__ . " linje " . __LINE__)); $aar = ($ry && $ry['k']) ? (int)$ry['k'] : 1;
						$r = db_fetch_array(db_select("insert into brugere (brugernavn, kode, rettigheder, regnskabsaar, email) values ('".db_escape_string($un)."', '', '$rights', '$aar', '".db_escape_string($row['email'])."') returning id", __FILE__ . " linje " . __LINE__)); $nid = (int)$r['id'];
						db_modify("update brugere set kode = '".db_escape_string(saldikrypt($nid, $pw))."' where id = '$nid'", __FILE__ . " linje " . __LINE__);
					}
					include("../includes/connect.php");
					if ($rep) db_modify("delete from online where db = '".db_escape_string($row['rdb'])."'", __FILE__ . " linje " . __LINE__); # the replaced login is signed out
					db_modify("update partner_invites set status = 'accepted', accepted_at = now(), result_user_id = '$nid' where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__);
					partner_log('partner.invite_accepted', (int)$row['regnskab_id'], (int)$row['partner_id'], "kundebruger $un (".$row['email'].")");
					partner_log($rep ? 'ledger.user_replaced' : 'ledger.user_created', (int)$row['regnskab_id'], (int)$row['partner_id'], "$un (".($row['role'] === 'readonly' ? 'kun læsning' : 'alle rettigheder').")".($rep ? " – overtog bruger $rep" : '').' via invitation');
					$done = "<h1>Dit login er oprettet</h1><p>Du kan nu logge ind i regnskabet <b>".bp_h($row['regnskab'])."</b> med brugernavnet <b>".bp_h($un)."</b> og din adgangskode.</p><p><a class=\"vr-btn vr-primary\" href=\"../index/index.php?regnskab=".rawurlencode($row['regnskab'])."\">Gå til login</a></p>";
				}
			}
		}
	} elseif ($kind == 'access' && $row['status'] == 'open' && isset($_POST['decision'])) {
		$by = ($row['sent_to'] == bp_saldi_mail()) ? 'Saldi (link)' : 'kunde (link)';
		$err = bp_access_request_decide($t, $_POST['decision'] == 'accept', $by); if ($err) $msg = $err; else $row['status'] = $_POST['decision'] == 'accept' ? 'approved' : 'rejected';
	} elseif ($kind == 'request' && $row['status'] == 'open' && isset($_POST['decision'])) {
		$err = bp_request_decide($t, $_POST['decision'] == 'accept', 'kunde (link)'); if ($err) $msg = $err; else $row['status'] = $_POST['decision'] == 'accept' ? 'approved' : 'rejected';
	}
}

print "<!DOCTYPE html><html lang=\"da\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><meta name=\"robots\" content=\"noindex\"><title>Saldi</title><link rel=\"stylesheet\" href=\"../css/operator_panel.css?v=5.2.1\"><style>body{background:#eef3fb;margin:0;font:14px/1.5 Inter,\"Segoe UI\",system-ui,sans-serif;color:#0b1a3a}.gk{max-width:560px;margin:60px auto;padding:0 20px}.gk .vr-card{padding:26px 28px;--vr-user:#014294;--vr-user-text:#fff}.gk h1{font-size:22px;margin:0 0 6px}.gk p{color:#5b6b85;margin:0 0 14px}.gk label{display:block;font-size:13px;color:#5b6b85;margin:0 0 12px}.gk .vr-inp{width:100%;box-sizing:border-box;margin-top:5px}.gk .err{color:#c2410c}</style></head><body><div class=\"gk\"><div style=\"font-weight:700;font-size:20px;margin-bottom:16px\">Saldi</div><div class=\"vr-card\">";
$f = fn($x) => bp_h($x);
if ($done) print $done;
elseif (!$row) print "<h1>Linket er ugyldigt</h1><p>Linket findes ikke eller er allerede brugt. Kontakt den, der sendte dig det.</p>";
elseif ($kind == 'invite') {
	if ($row['status'] == 'accepted') print "<h1>Invitationen er allerede brugt</h1><p>Login er oprettet. Brug login-siden, eller bed om en ny invitation, hvis du har glemt dit brugernavn.</p>";
	elseif ($row['status'] == 'revoked') print "<h1>Invitationen er tilbagekaldt</h1><p>".$f($row['pname'])." har trukket invitationen tilbage.</p>";
	elseif ($row['status'] == 'expired') print "<h1>Invitationen er udløbet</h1><p>Linket gjaldt til ".bp_date($row['expires_at']).". Bed ".$f($row['pname'])." om en ny.</p>";
	else {
		if ($row['kind'] == 'employee') print "<h1>Velkommen til ".$f($row['pname'])."</h1><p>Du er inviteret som ".($row['partner_role'] == 'owner' ? 'ejer' : 'medarbejder')." og får adgang til ".($row['scope'] && $row['scope'] != 'all' ? count(array_filter(explode(',', $row['scope']))).' udvalgte kunder' : 'alle firmaets kunder')." i Saldi. Vælg brugernavn og adgangskode.</p>";
		else print "<h1>Dit login til ".$f($row['regnskab'])."</h1><p>".$f($row['pname'])." har sat adgang op til dig i regnskabet. Vælg brugernavn og adgangskode.</p>";
		if ($row['message']) print "<p><i>".$f($row['message'])."</i></p>";
		print ($msg ? "<p class=\"err\">".$f($msg)."</p>" : "")."<form method=\"post\"><input type=\"hidden\" name=\"t\" value=\"".$f($t)."\"><label>Brugernavn<input class=\"vr-inp\" name=\"brugernavn\" maxlength=\"80\" value=\"".$f((string)if_isset($_POST, '', 'brugernavn'))."\" required autofocus></label><label>Adgangskode (mindst 8 tegn)<input class=\"vr-inp\" type=\"password\" name=\"kode\" minlength=\"8\" required></label><label>Gentag adgangskode<input class=\"vr-inp\" type=\"password\" name=\"kode2\" minlength=\"8\" required></label><p style=\"font-size:12.5px\">Linket gælder til ".bp_date($row['expires_at']).".</p><button type=\"submit\" class=\"vr-btn vr-primary\">Opret login</button></form>";
	}
} elseif ($kind == 'access') {
	if ($row['status'] == 'approved') print "<h1>Adgangen er givet</h1><p><b>".$f($row['pname'])."</b> kan nu arbejde i ".$f($row['regnskab']).". Adgangen kan altid fjernes igen af Saldi eller bogholderen.</p>";
	elseif ($row['status'] == 'rejected') print "<h1>Anmodningen er afvist</h1><p>Der er ikke ændret noget for ".$f($row['regnskab']).".</p>";
	elseif ($row['status'] == 'expired') print "<h1>Anmodningen er udløbet</h1><p>Linket gjaldt til ".bp_date($row['expires_at']).". Bed bogholderen om en ny anmodning, hvis det stadig er aktuelt.</p>";
	elseif ($row['status'] == 'cancelled') print "<h1>Anmodningen er trukket tilbage</h1><p>".$f($row['pname'])." har annulleret anmodningen.</p>";
	else {
		print "<h1>".$f($row['pname'])." beder om adgang til ".$f($row['regnskab'])."</h1><p>".$f($row['pname']).($row['pcvr'] ? " (CVR ".$f($row['pcvr']).")" : "")." vil gerne arbejde i jeres regnskab. Giver I adgang, kan deres medarbejdere åbne og bogføre i regnskabet. Jeres pakke, betaling og andre bogholderes adgang ændres ikke, og adgangen kan altid fjernes igen.</p>".($row['message'] ? "<p><i>".$f($row['message'])."</i></p>" : "").($msg ? "<p class=\"err\">".$f($msg)."</p>" : "");
		print "<form method=\"post\" style=\"display:flex;gap:10px\"><input type=\"hidden\" name=\"t\" value=\"".$f($t)."\"><button type=\"submit\" name=\"decision\" value=\"accept\" class=\"vr-btn vr-primary\">Giv adgang</button><button type=\"submit\" name=\"decision\" value=\"reject\" class=\"vr-btn\">Afvis</button></form>";
	}
} else { # payer request
	if ($row['status'] == 'approved') print "<h1>Tak – I overtager betalingen</h1><p>Saldi fakturerer jer direkte for ".$f($row['regnskab'])." fra næste periode. I hører fra Saldi med detaljerne.</p>";
	elseif ($row['status'] == 'rejected') print "<h1>Forslaget er afvist</h1><p>".$f($row['pname'])." betaler fortsat for ".$f($row['regnskab']).".</p>";
	elseif ($row['status'] == 'cancelled') print "<h1>Forslaget er trukket tilbage</h1><p>".$f($row['pname'])." har annulleret forslaget.</p>";
	else {
		print "<h1>".$f($row['pname'])." foreslår, at I selv betaler for ".$f($row['regnskab'])."</h1><p>I dag betaler ".$f($row['pname'])." for jeres Saldi-abonnement. Accepterer I, fakturerer Saldi jer direkte fra næste periode. Intet ændres, før I accepterer.</p>".($row['details'] ? "<p><i>".$f($row['details'])."</i></p>" : "").($msg ? "<p class=\"err\">".$f($msg)."</p>" : "");
		print "<form method=\"post\" style=\"display:flex;gap:10px\"><input type=\"hidden\" name=\"t\" value=\"".$f($t)."\"><button type=\"submit\" name=\"decision\" value=\"accept\" class=\"vr-btn vr-primary\">Accepter</button><button type=\"submit\" name=\"decision\" value=\"reject\" class=\"vr-btn\">Afvis</button></form>";
	}
}
print "</div><p style=\"font-size:12px;color:#8a97ad;margin-top:14px\">Saldi · Danosoft ApS</p></div></body></html>";
?>
