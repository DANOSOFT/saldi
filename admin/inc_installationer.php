<?php
// --- admin/inc_installationer.php --- ver 5.1.0 --- 2026.10.12 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261012 CL/ASR New. "Egne installationer": customers who run Saldi themselves (open source) and have a support
//                  agreement with us. They are not ledgers in this master, so they live in their own table
//                  self_hosted. The operator registers contact, URL, version, support level, agreement period and
//                  price, and the billing-ledger customer; payments come from the billing ledger through the same
//                  REST API as for hosted ledgers (bill_fetch/bill_classify), cached for the list and overview.
//                  Shown as a tab on admin_panel.php (ltab=egne) with a card per installation (?inst_id=N).

if (!function_exists('inst_tables_ensure')) {
function inst_tables_ensure() {
	static $d = false; if ($d) return; $d = true;
	if (db_fetch_array(db_select("SELECT 1 FROM information_schema.tables WHERE table_name = 'self_hosted'", __FILE__ . " linje " . __LINE__))) return;
	db_modify("CREATE TABLE IF NOT EXISTS self_hosted (id SERIAL PRIMARY KEY, name varchar(120) NOT NULL, cvr varchar(20), contact_name varchar(120), contact_email varchar(120), contact_phone varchar(40), url varchar(255), version varchar(20), support_level varchar(20), support_from date, support_to date, price_ore int, billing_interval varchar(10) NOT NULL DEFAULT 'year', billing_kontonr varchar(30), note text, status varchar(10) NOT NULL DEFAULT 'active', pay_status varchar(12), outstanding_ore bigint, overdue_n int, oldest_due date, last_invoice date, last_paid date, checked_at timestamp, created timestamp NOT NULL DEFAULT now(), created_by text, updated_at timestamp)", __FILE__ . " linje " . __LINE__);
}
function inst_levels() { return array('basis' => vr_t('Basis','Basic'), 'udvidet' => vr_t('Udvidet','Extended'), 'premium' => 'Premium', 'andet' => vr_t('Andet','Other')); }
function inst_states() { return array('active' => vr_t('Aktiv aftale','Active agreement'), 'paused' => vr_t('Sat på pause','Paused'), 'ended' => vr_t('Ophørt','Ended')); }
function inst_log($id, $action, $details) { partner_log($action, null, null, "[installation $id] ".$details); }
function inst_all($status = null) { inst_tables_ensure(); $o = array(); $q = db_select("select * from self_hosted".($status ? " where status = '".db_escape_string($status)."'" : "")." order by name", __FILE__ . " linje " . __LINE__); while ($r = db_fetch_array($q)) $o[] = $r; return $o; }
function inst_get($id) { inst_tables_ensure(); return db_fetch_array(db_select("select * from self_hosted where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__)); }
function inst_latest_version() { global $version; if (empty($version) && file_exists("../includes/version.php")) include("../includes/version.php"); return isset($version) ? (string)$version : ''; }
// Fields from POST → SQL set list
function inst_fields() {
	$f = array();
	foreach (array('name','cvr','contact_name','contact_email','contact_phone','url','version','billing_kontonr','note') as $k) $f[$k] = "'".db_escape_string(trim((string)if_isset($_POST, '', $k)))."'";
	$lv = (string)if_isset($_POST, '', 'support_level'); $f['support_level'] = "'".(isset(inst_levels()[$lv]) ? $lv : '')."'";
	foreach (array('support_from','support_to') as $k) { $v = trim((string)if_isset($_POST, '', $k)); $f[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? "'$v'" : 'NULL'; }
	$pr = trim((string)if_isset($_POST, '', 'price')); $f['price_ore'] = $pr === '' ? 'NULL' : (string)(int)round((float)str_replace(array('.', ','), array('', '.'), $pr) * 100);
	$f['billing_interval'] = "'".(if_isset($_POST, 'year', 'billing_interval') == 'month' ? 'month' : 'year')."'";
	return $f;
}
function inst_actions(&$notes, &$id) {
	if (!isset($_POST['inst_action'])) return;
	inst_tables_ensure(); $a = $_POST['inst_action'];
	if ($a == 'create' || $a == 'update') {
		if (trim((string)if_isset($_POST, '', 'name')) === '') { $notes[] = vr_t('Navn mangler','Name missing'); return; }
		$f = inst_fields();
		if ($a == 'create') {
			db_modify("insert into self_hosted (".implode(', ', array_keys($f)).", created_by) values (".implode(', ', $f).", '".db_escape_string(ent_actor())."')", __FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select max(id) as id from self_hosted", __FILE__ . " linje " . __LINE__)); $id = (int)$r['id'];
			inst_log($id, 'install.created', trim($_POST['name'])); $notes[] = vr_t('Installationen er oprettet','Installation created');
		} else {
			$set = array(); foreach ($f as $k => $v) $set[] = "$k = $v";
			db_modify("update self_hosted set ".implode(', ', $set).", updated_at = now() where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__);
			inst_log($id, 'install.updated', trim($_POST['name'])); $notes[] = vr_t('Gemt','Saved');
		}
	} elseif ($a == 'status' && $id) {
		$st = (string)if_isset($_POST, '', 'status'); if (!isset(inst_states()[$st])) return;
		db_modify("update self_hosted set status = '$st', updated_at = now() where id = '".(int)$id."'", __FILE__ . " linje " . __LINE__);
		inst_log($id, 'install.status', $st); $notes[] = inst_states()[$st];
	}
}
// Payments: fetch from the billing ledger, store summary on the row.
function inst_payments($row) {
	$search = $row['billing_kontonr'] ?: ($row['cvr'] ?: $row['name']);
	$data = bill_fetch($search);
	if (!isset($data['error'])) {
		$c = $data['customer']; $dd = (int)($c['betaling']['betalingsdage'] ?? 0); $open = 0; $openN = 0; $ovN = 0; $oldest = null; $last = null; $lastPaid = null;
		foreach ($data['invoices'] as $i) { list($st, $due, $tot) = bill_classify($i, $dd); if (!$last && !empty($i['invoiceDate'])) $last = substr($i['invoiceDate'],0,10); if ($st == 'paid' && !$lastPaid && !empty($i['invoiceDate'])) $lastPaid = substr($i['invoiceDate'],0,10); if ($st != 'paid') { $open += $tot; $openN++; if ($st == 'overdue') { $ovN++; if (!$oldest || $due < $oldest) $oldest = $due; } } }
		$status = $ovN ? 'overdue' : ($openN ? 'pending' : ($data['invoices'] ? 'paid' : 'none'));
		db_modify("update self_hosted set pay_status = '$status', outstanding_ore = '".(int)round($open*100)."', overdue_n = '$ovN', oldest_due = ".($oldest ? "'$oldest'" : 'NULL').", last_invoice = ".($last ? "'$last'" : 'NULL').", last_paid = ".($lastPaid ? "'$lastPaid'" : 'NULL').", checked_at = now() where id = '".(int)$row['id']."'", __FILE__ . " linje " . __LINE__);
	}
	return array($search, $data);
}
function inst_pay_badge($st) {
	$t = array('paid' => vr_t('Betalt','Paid'), 'pending' => vr_t('Afventer','Pending'), 'overdue' => vr_t('Forfalden','Overdue'), 'none' => vr_t('Ingen fakturaer','No invoices')); $d = array('paid' => 'vr-ok', 'pending' => 'vr-warn', 'overdue' => 'vr-bad', 'none' => 'vr-off');
	return $st && isset($t[$st]) ? "<span class=\"vr-st\"><span class=\"vr-dot ".$d[$st]."\"></span>".$t[$st]."</span>" : "<span class=\"vr-mut2\">".vr_t('ikke tjekket','not checked')."</span>";
}
function inst_agreement($r) { # text + dot for the agreement period
	if ($r['status'] == 'ended') return array('vr-off', vr_t('Ophørt','Ended'));
	if ($r['status'] == 'paused') return array('vr-warn', vr_t('Pause','Paused'));
	if (!$r['support_to']) return array('vr-ok', vr_t('Løbende','Ongoing'));
	$days = (int)floor((strtotime($r['support_to']) - strtotime(date('Y-m-d'))) / 86400);
	if ($days < 0) return array('vr-bad', vr_t('Udløbet','Expired').' '.date('d-m-Y', strtotime($r['support_to'])));
	if ($days <= 30) return array('vr-warn', vr_t('Udløber','Expires').' '.date('d-m-Y', strtotime($r['support_to'])).' ('.$days.' '.vr_t('dage','days').')');
	return array('vr-ok', vr_t('Til','Until').' '.date('d-m-Y', strtotime($r['support_to'])));
}
function inst_form($r, $action) {
	global $sprog_id;
	$v = fn($k) => vr_h($r[$k] ?? '');
	print "<form method=\"post\" class=\"vr-form\" style=\"max-width:none\"><input type=\"hidden\" name=\"inst_action\" value=\"$action\">";
	print "<div class=\"vr-fgrid\"><label>".vr_t('Navn','Name')."<input class=\"vr-inp\" name=\"name\" value=\"".$v('name')."\" required></label><label>CVR<input class=\"vr-inp\" name=\"cvr\" value=\"".$v('cvr')."\"></label>";
	print "<label>".vr_t('Kontaktperson','Contact')."<input class=\"vr-inp\" name=\"contact_name\" value=\"".$v('contact_name')."\"></label><label>E-mail<input class=\"vr-inp\" type=\"email\" name=\"contact_email\" value=\"".$v('contact_email')."\"></label>";
	print "<label>".vr_t('Telefon','Phone')."<input class=\"vr-inp\" name=\"contact_phone\" value=\"".$v('contact_phone')."\"></label><label>".vr_t('Adresse (URL)','Address (URL)')."<input class=\"vr-inp\" name=\"url\" value=\"".$v('url')."\" placeholder=\"https://saldi.firma.dk\"></label>";
	print "<label>".vr_t('Saldi-version','Saldi version')."<input class=\"vr-inp\" name=\"version\" value=\"".$v('version')."\" placeholder=\"".vr_h(inst_latest_version())."\"></label><label>".vr_t('Supportniveau','Support level')."<select name=\"support_level\" class=\"vr-sel\"><option value=\"\">–</option>"; foreach (inst_levels() as $k => $t) print "<option value=\"$k\"".(($r['support_level'] ?? '') == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select></label>";
	print "<label>".vr_t('Aftale fra','Agreement from')."<input class=\"vr-inp\" type=\"date\" name=\"support_from\" value=\"".$v('support_from')."\"></label><label>".vr_t('Aftale til (tom = løbende)','Agreement until (empty = ongoing)')."<input class=\"vr-inp\" type=\"date\" name=\"support_to\" value=\"".$v('support_to')."\"></label>";
	print "<label>".vr_t('Pris ekskl. moms','Price excl. VAT')."<input class=\"vr-inp\" name=\"price\" value=\"".(isset($r['price_ore']) && $r['price_ore'] !== null && $r['price_ore'] !== '' ? number_format($r['price_ore']/100, 2, ',', '.') : '')."\" placeholder=\"0,00\"></label><label>".vr_t('Fakturering','Billing')."<select name=\"billing_interval\" class=\"vr-sel\"><option value=\"year\"".(($r['billing_interval'] ?? 'year') == 'year' ? " selected" : "").">".vr_t('Årligt','Yearly')."</option><option value=\"month\"".(($r['billing_interval'] ?? '') == 'month' ? " selected" : "").">".vr_t('Månedligt','Monthly')."</option></select></label></div>";
	print "<label>".vr_t('Kundenr. i faktureringsregnskabet','Billing customer no.')."<input class=\"vr-inp\" name=\"billing_kontonr\" value=\"".$v('billing_kontonr')."\" placeholder=\"".vr_t('ellers søges på CVR, derefter navn','else matched by CVR, then name')."\"></label>";
	print "<label>".vr_t('Note','Note')."<textarea class=\"vr-inp\" name=\"note\" style=\"height:70px;padding:8px 12px\">".$v('note')."</textarea></label>";
	print "<div><button type=\"submit\" class=\"vr-btn vr-primary\">".($action == 'create' ? vr_t('Tilføj installation','Add installation') : findtekst('3|Gem', $sprog_id))."</button></div></form>";
}
function inst_list_render() {
	$all = inst_all(); $latest = inst_latest_version();
	$act = count(array_filter($all, fn($r) => $r['status'] == 'active'));
	print "<section class=\"vr-sect\"><h2>".vr_t('Egne installationer','Self-hosted installations')." <small>$act ".vr_t('aktive aftaler','active agreements')." · ".vr_t('kunder der selv kører Saldi og har en supportaftale med os','customers running Saldi themselves with a support agreement')."</small></h2><div class=\"vr-card\">";
	print "<div class=\"vr-bar\"><label class=\"vr-search\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.2\" stroke-linecap=\"round\"><circle cx=\"11\" cy=\"11\" r=\"7\"/><path d=\"M20 20l-3.5-3.5\"/></svg><input type=\"search\" placeholder=\"".vr_t('Søg på navn, CVR eller adresse','Search by name, CVR or address')."\" oninput=\"var q=this.value.toLowerCase();document.querySelectorAll('#instRows tr[data-q]').forEach(function(t){t.hidden=q&&t.getAttribute('data-q').indexOf(q)<0;})\"></label><span class=\"vr-grow\"></span><span class=\"vr-hint\">".vr_t('Nyeste Saldi-version','Latest Saldi version').": ".vr_h($latest)."</span></div>";
	print "<table class=\"vr-t\"><thead><tr><th>".vr_t('Installation','Installation')."</th><th>".vr_t('Version','Version')."</th><th>".vr_t('Support','Support')."</th><th>".vr_t('Aftale','Agreement')."</th><th class=\"vr-r\">".vr_t('Pris','Price')."</th><th>".vr_t('Betaling','Payment')."</th></tr></thead><tbody id=\"instRows\">";
	foreach ($all as $r) {
		list($dot, $txt) = inst_agreement($r); $old = $r['version'] && $latest && version_compare($r['version'], $latest, '<');
		$href = "admin_panel.php?inst_id=".(int)$r['id'];
		print "<tr class=\"".($r['status'] == 'ended' ? 'vr-closed' : '')."\" data-q=\"".vr_h(mb_strtolower($r['name'].' '.$r['cvr'].' '.$r['url'].' '.$r['contact_name'], 'UTF-8'))."\" onclick=\"location.href='$href'\" style=\"cursor:pointer\"><td class=\"vr-nm\"><a href=\"$href\">".vr_h($r['name'])."</a><small>".vr_h($r['url'] ?: ($r['contact_name'] ?: '–'))."</small></td>";
		print "<td class=\"vr-mut\">".($r['version'] ? vr_h($r['version']).($old ? " <span class=\"vr-dot vr-warn\" title=\"".vr_t('Ældre end nyeste version','Older than the latest version')."\"></span>" : '') : '–')."</td><td class=\"vr-mut\">".vr_h(inst_levels()[$r['support_level']] ?? '–')."</td><td><span class=\"vr-st\"><span class=\"vr-dot $dot\"></span>".vr_h($txt)."</span></td>";
		print "<td class=\"vr-r vr-num\">".($r['price_ore'] !== null && $r['price_ore'] !== '' ? number_format($r['price_ore']/100, 0, ',', '.')." kr./".($r['billing_interval'] == 'month' ? 'md.' : vr_t('år','yr')) : '–')."</td><td>".inst_pay_badge($r['pay_status']).((int)$r['outstanding_ore'] ? " <span class=\"vr-mut2\">· ".number_format($r['outstanding_ore']/100, 0, ',', '.')." kr.</span>" : "")."</td></tr>";
	}
	if (!$all) print "<tr><td colspan=\"6\" class=\"vr-mut\" style=\"padding:24px 20px\">".vr_t('Ingen egne installationer registreret endnu. Tilføj den første nedenfor.','No self-hosted installations yet. Add the first one below.')."</td></tr>";
	print "</tbody></table></div></section>";
	print "<section class=\"vr-sect\" id=\"ny\" style=\"margin-top:22px\"><h2>".vr_t('Tilføj installation','Add installation')."</h2><div class=\"vr-card\">"; inst_form(array(), 'create'); print "</div></section>";
}
function inst_card_render($r, $tab, $notes) {
	$id = (int)$r['id']; list($dot, $txt) = inst_agreement($r); $latest = inst_latest_version();
	$lead = "<span class=\"vr-st\"><span class=\"vr-dot $dot\"></span>".vr_h($txt)."</span> · ".vr_t('egen installation','self-hosted').($r['version'] ? ' · Saldi '.vr_h($r['version']) : '').($r['support_level'] ? ' · '.vr_h(inst_levels()[$r['support_level']] ?? '') : '');
	vr_open(array(array('Administrationspanel', 'admin_panel.php'), array(vr_t('Regnskaber','Accounts'), 'admin_panel.php?ltab=egne'), $r['name']), $r['name'], $lead, $r['url'] ? "<a class=\"vr-btn\" href=\"".vr_h($r['url'])."\" target=\"_blank\" rel=\"noopener\">".vr_t('Åbn installation','Open installation')."</a>" : '', vr_t('egen installation','self-hosted'));
	vr_note($notes);
	$tu = fn($t) => "admin_panel.php?inst_id=$id&tab=$t";
	vr_tabs(array('oversigt' => array(vr_t('Oversigt','Overview'), $tu('oversigt'), null), 'betalinger' => array(vr_t('Betalinger','Payments'), $tu('betalinger'), null), 'log' => array(vr_t('Log','Log'), $tu('log'), null)), $tab);
	if ($tab == 'betalinger') {
		list($search, $data) = inst_payments($r); $r = inst_get($id);
		print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Betalingsstatus','Payment status')."</h2><div class=\"vr-card vr-kv\">";
		print "<div><span>".vr_t('Status','Status')."</span><b>".inst_pay_badge($r['pay_status'])."</b></div><div><span>".vr_t('Udestående','Outstanding')."</span><b>".number_format(((int)$r['outstanding_ore'])/100, 2, ',', '.')." DKK".((int)$r['overdue_n'] ? " <span class=\"vr-mut2\">· ".(int)$r['overdue_n']." ".vr_t('forfaldne, ældste','overdue, oldest')." ".date('d-m-Y', strtotime($r['oldest_due']))."</span>" : "")."</b></div>";
		print "<div><span>".vr_t('Senest betalte faktura','Last paid invoice')."</span><b>".($r['last_paid'] ? date('d-m-Y', strtotime($r['last_paid'])) : '–')."</b></div><div><span>".vr_t('Næste fakturering','Next invoice')."</span><b>".($r['last_invoice'] ? date('d-m-Y', strtotime(($r['billing_interval'] == 'month' ? '+1 month' : '+1 year'), strtotime($r['last_invoice']))).' <span class="vr-mut2">· '.vr_t('forventet','expected').'</span>' : '–')."</b></div>";
		print "<div><span>".vr_t('Faktureringskunde','Billing customer')."</span><b>".(isset($data['customer']) ? vr_h($data['customer']['firmanavn'] ?? '')." <span class=\"vr-mut2\">· ".vr_t('kundenr.','no.')." ".vr_h($data['customer']['kontonr'])."</span>" : '<span class="vr-mut2">'.vr_t('ikke fundet','not found').'</span>')."</b></div></div>".(isset($data['error']) ? "<p class=\"vr-sub\" style=\"margin-top:10px;color:var(--warn-ink)\">".vr_h($data['error'])." <span class=\"vr-mut2\">(".vr_t('søgt på','searched by')." ".vr_h($search).")</span></p>" : "")."</section>";
		print "<section class=\"vr-sect\"><h2>".vr_t('Aftale','Agreement')."</h2><div class=\"vr-card vr-kv\"><div><span>".vr_t('Pris','Price')."</span><b>".($r['price_ore'] !== null && $r['price_ore'] !== '' ? number_format($r['price_ore']/100, 2, ',', '.')." DKK / ".($r['billing_interval'] == 'month' ? vr_t('måned','month') : vr_t('år','year')) : '–')."</b></div><div><span>".vr_t('Periode','Period')."</span><b>".($r['support_from'] ? date('d-m-Y', strtotime($r['support_from'])) : '–')." – ".($r['support_to'] ? date('d-m-Y', strtotime($r['support_to'])) : vr_t('løbende','ongoing'))."</b></div></div></section></div>";
		$inv = isset($data['invoices']) ? $data['invoices'] : array(); $dd = isset($data['customer']) ? (int)($data['customer']['betaling']['betalingsdage'] ?? 0) : 0;
		print "<section class=\"vr-sect\" style=\"margin-top:22px\"><h2>".vr_t('Faktura- og betalingshistorik','Invoice and payment history')." <small>".count($inv)."</small></h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Faktura','Invoice')."</th><th>".vr_t('Dato','Date')."</th><th>".vr_t('Forfald','Due')."</th><th class=\"vr-r\">".vr_t('Beløb inkl. moms','Amount incl. VAT')."</th><th>".vr_t('Status','Status')."</th></tr></thead><tbody>";
		foreach ($inv as $i) { list($st, $due, $tot) = bill_classify($i, $dd); print "<tr onclick=\"openInvoice(".(int)$i['id'].")\" style=\"cursor:pointer\"><td class=\"vr-nm\"><a>#".vr_h($i['invoiceNo'] ?? $i['orderNo'] ?? '-')."</a></td><td class=\"vr-mut\">".(!empty($i['invoiceDate']) ? date('d-m-Y', strtotime($i['invoiceDate'])) : '–')."</td><td class=\"vr-mut\">".($due ? date('d-m-Y', strtotime($due)) : '–')."</td><td class=\"vr-r vr-num\">".number_format($tot, 2, ',', '.')."</td><td>".inst_pay_badge($st)."</td></tr>"; }
		if (!$inv) print "<tr><td colspan=\"5\" class=\"vr-mut\" style=\"padding:20px\">".(isset($data['error']) ? vr_h($data['error']) : vr_t('Ingen fakturaer','No invoices'))."</td></tr>";
		print "</tbody></table></div></section>";
		print "<div class=\"vr-scrim\" id=\"invScrim\" onclick=\"closeInvoice()\"></div><div class=\"vr-dlg\" id=\"invDlg\" style=\"width:720px;max-width:95vw\"><h3 id=\"invTitle\">…</h3><p id=\"invMeta\"></p><div id=\"invBody\"></div><div class=\"vr-bs\"><button type=\"button\" class=\"vr-btn\" onclick=\"closeInvoice()\">".vr_t('Luk','Close')."</button></div></div>";
		print <<<'INVJS'
<script>
function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
function nf(v){return Number(v||0).toLocaleString('da-DK',{minimumFractionDigits:2,maximumFractionDigits:2});}
function openInvoice(id){const s=document.getElementById('invScrim'),d=document.getElementById('invDlg');if(!d)return;s.classList.add('vr-on');d.classList.add('vr-open');document.getElementById('invTitle').textContent='Henter…';document.getElementById('invMeta').textContent='';document.getElementById('invBody').innerHTML='';fetch('admin_panel.php?ajax_invoice_id='+id).then(r=>r.json()).then(x=>{if(x.error){document.getElementById('invTitle').textContent='Fejl';document.getElementById('invBody').textContent=x.error;return;}const inv=x.invoice;document.getElementById('invTitle').textContent=inv.companyName||'Faktura';let ds='-';if(inv.invoiceDate){const p=inv.invoiceDate.split('-');if(p.length===3)ds=p[2]+'-'+p[1]+'-'+p[0];}document.getElementById('invMeta').textContent='Faktura #'+(inv.invoiceNo||inv.orderNo||'-')+' · '+ds;let h='<table class="vr-t"><thead><tr><th>Varenr</th><th>Beskrivelse</th><th class="vr-r">Antal</th><th class="vr-r">Pris</th><th class="vr-r">I alt</th></tr></thead><tbody>';(inv.lines||[]).forEach(l=>{if(!l.description&&!l.sku)return;const q=(l.quantity!=null?l.quantity:'');h+='<tr><td class="vr-mut">'+esc(l.sku||'')+'</td><td>'+esc(l.description||'')+'</td><td class="vr-r">'+esc(q)+' '+esc(l.unit||'')+'</td><td class="vr-r">'+(l.price?nf(l.price):'')+'</td><td class="vr-r">'+((l.quantity&&l.price)?nf(l.quantity*l.price):'')+'</td></tr>';});const sum=parseFloat((inv.economic&&inv.economic.sum)||0),vat=parseFloat((inv.economic&&inv.economic.vat)||0);h+='</tbody></table><div class="vr-kv" style="margin-top:10px"><div><span>Subtotal ekskl. moms</span><b>'+nf(sum)+' DKK</b></div><div><span>Moms</span><b>'+nf(vat)+' DKK</b></div><div><span>I alt</span><b>'+nf(sum+vat)+' DKK</b></div></div>';document.getElementById('invBody').innerHTML=h;}).catch(()=>{document.getElementById('invTitle').textContent='Fejl';});}
function closeInvoice(){document.getElementById('invScrim').classList.remove('vr-on');document.getElementById('invDlg').classList.remove('vr-open');}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeInvoice();});
</script>
INVJS;

	} elseif ($tab == 'log') {
		print "<section class=\"vr-sect\"><h2>".vr_t('Log','Log')."</h2><div class=\"vr-card\"><table class=\"vr-t\"><thead><tr><th>".vr_t('Tidspunkt','Time')."</th><th>".vr_t('Hvem','Who')."</th><th>".vr_t('Hvad','What')."</th><th>".vr_t('Detaljer','Details')."</th></tr></thead><tbody>";
		$q = db_select("select l.*, b.brugernavn from partner_log l left join brugere b on b.id = l.actor_bruger_id where l.action like 'install.%' and l.details like '[installation $id]%' order by l.id desc limit 200", __FILE__ . " linje " . __LINE__); $n = 0;
		while ($x = db_fetch_array($q)) { $n++; print "<tr><td class=\"vr-mut vr-num\">".vr_h(substr($x['ts'],0,16))."</td><td>".vr_h($x['brugernavn'])."</td><td>".vr_h($x['action'])."</td><td class=\"vr-mut\">".vr_h(preg_replace('/^\[installation \d+\] /', '', $x['details']))."</td></tr>"; }
		if (!$n) print "<tr><td colspan=\"4\" class=\"vr-mut\">".vr_t('Ingen hændelser endnu','No events yet')."</td></tr>";
		print "</tbody></table></div></section>";
	} else {
		print "<div class=\"vr-grid2\"><section class=\"vr-sect\"><h2>".vr_t('Installation','Installation')."</h2><div class=\"vr-card vr-kv\">";
		$old = $r['version'] && $latest && version_compare($r['version'], $latest, '<');
		foreach (array(array(vr_t('Kontaktperson','Contact'), $r['contact_name'] ? vr_h($r['contact_name']) : '–'), array('E-mail', $r['contact_email'] ? "<a href=\"mailto:".vr_h($r['contact_email'])."\">".vr_h($r['contact_email'])."</a>" : '–'), array(vr_t('Telefon','Phone'), $r['contact_phone'] ? vr_h($r['contact_phone']) : '–'), array('CVR', $r['cvr'] ? vr_h($r['cvr']) : '–'), array(vr_t('Adresse','Address'), $r['url'] ? "<a href=\"".vr_h($r['url'])."\" target=\"_blank\" rel=\"noopener\">".vr_h($r['url'])."</a>" : '–'), array(vr_t('Saldi-version','Saldi version'), ($r['version'] ? vr_h($r['version']) : '–').($old ? " <span class=\"vr-pill\">".vr_t('nyeste er','latest is')." ".vr_h($latest)."</span>" : '')), array(vr_t('Betaling','Payment'), inst_pay_badge($r['pay_status']).($r['checked_at'] ? " <span class=\"vr-mut2\">· ".vr_t('tjekket','checked')." ".vr_h(substr($r['checked_at'],0,10))."</span>" : '')), array(vr_t('Note','Note'), $r['note'] ? nl2br(vr_h($r['note'])) : '–')) as $row) print "<div><span>$row[0]</span><b>$row[1]</b></div>";
		print "</div>";
		print "<h2 style=\"margin-top:18px\">".vr_t('Status på aftalen','Agreement status')."</h2><div class=\"vr-card\"><form method=\"post\" class=\"vr-inline\" style=\"padding:14px 20px\"><input type=\"hidden\" name=\"inst_action\" value=\"status\"><select name=\"status\" class=\"vr-sel\">"; foreach (inst_states() as $k => $t) print "<option value=\"$k\"".($r['status'] == $k ? " selected" : "").">".vr_h($t)."</option>"; print "</select><button type=\"submit\" class=\"vr-btn\">".vr_t('Gem status','Save status')."</button></form></div></section>";
		print "<section class=\"vr-sect\"><h2>".vr_t('Rediger oplysninger','Edit details')."</h2><div class=\"vr-card\">"; inst_form($r, 'update'); print "</div></section></div>";
	}
	vr_close(); print "</body></html>";
}
}
?>
