<?php
// --- admin/inc_overblik.php --- ver 5.1.1 --- 2026.11.09 ---
// LICENSE: GNU GPL v2 or later; see vis_regnskaber.php for the full notice. Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261010 CL/ASR New. Area 5: a small commercial overview on the operator's Overblik page.
//                 Four figures from data the panel already has: active ledgers by plan (regnskab + regnskab_entitlements), MRR from the catalogue prices (plan + add-ons, yearly/12; "ERP by agreement" counts 0 and is listed), customers with overdue payments (cached summary in regnskab_billing, filled when the Betalinger tab is viewed and by the nightly job), and ledgers at >= 80 % of a limit (latest usage_snapshots vs. effective limits).
//                 Not an analytics platform.
// 20261109 CL/ASR "Near limits" no longer resolves every ledger: ledgers with an active plan or limit override, or a plan change that has fallen due, still go through ent_resolve() (which also applies the due change), the rest read their plan's limits from the catalogue (review #785).
//                 Add-ons and feature/add-on overrides do not change the user, entry or item limits, so the figures are the same as before.
//                 ov_data() and ov_render() each have their own function_exists() guard and are laid out with one statement per line and a comment per section; the printed page is unchanged (review #785).
//                 MRR counts an on/off add-on once and multiplies only counted add-ons by their quantity, the same rule as the Abonnement tab (review #785).

if (!function_exists('ov_data')) {
	// The overview's figures, computed from the master database on every call.
	function ov_data() {
		global $sqdb;

		ent_tables_ensure();
		bill_cache_ensure();
		$cat = ent_catalog();
		$d = array(
			'active' => 0,
			'closed' => 0,
			'by_plan' => array(),
			'no_plan' => 0,
			'mrr_ore' => 0,
			'mrr_n' => 0,
			'erp_n' => 0,
			'canceled' => 0,
			'overdue' => array(),
			'overdue_sum_ore' => 0,
			'checked' => 0,
			'near' => array(),
			'snap_date' => null,
		);

		// Every ledger except the master, with its plan, and the add-ons that have not expired
		$ledgers = array();
		$q = db_select("select r.id, r.regnskab, r.lukket, e.plan_key, e.subscription_status from regnskab r left join regnskab_entitlements e on e.regnskab_id = r.id where r.db != '".db_escape_string($sqdb)."'", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ledgers[(int)$r['id']] = $r;
		}
		$addons = array();
		$q = db_select("select regnskab_id, addon_key, quantity from regnskab_addons where expires_at is null or expires_at >= current_date", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$addons[(int)$r['regnskab_id']][] = $r;
		}

		// Active ledgers by plan, and MRR: the plan's monthly price (a yearly price / 12) plus the add-ons' catalogue prices
		foreach ($cat['plans'] as $k => $p) {
			$d['by_plan'][$k] = array('name' => $p['name'], 'n' => 0, 'mrr_ore' => 0);
		}
		foreach ($ledgers as $id => $r) {
			if ($r['lukket'] == 'on') {
				$d['closed']++;
				continue;
			}
			$d['active']++;
			if ($r['subscription_status'] == 'canceled') $d['canceled']++;

			$pk = $r['plan_key'];
			if (!$pk || !isset($cat['plans'][$pk])) {
				$d['no_plan']++;
				continue;
			}
			$d['by_plan'][$pk]['n']++;
			$p = $cat['plans'][$pk];
			if ($p['price_ore'] === null) { # ERP: price by agreement, counted but not priced
				$d['erp_n']++;
				continue;
			}

			$m = ($p['billing_interval'] == 'year') ? (int)round($p['price_ore'] / 12) : (int)$p['price_ore'];
			if (isset($addons[$id])) {
				foreach ($addons[$id] as $a) {
					if (!isset($cat['addons'][$a['addon_key']])) continue;
					$ad = $cat['addons'][$a['addon_key']];
					if ($ad['price_ore'] === null) continue;
					$m += (int)$ad['price_ore'] * (ent_addon_counted($ad) ? max(1, (int)$a['quantity']) : 1);
				}
			}
			$d['mrr_ore'] += $m;
			$d['mrr_n']++;
			$d['by_plan'][$pk]['mrr_ore'] += $m;
		}

		// Customers with overdue or failed payments, from the cached billing summary
		$q = db_select("select b.*, r.regnskab from regnskab_billing b join regnskab r on r.id = b.regnskab_id where b.checked_at is not null and coalesce(r.lukket,'') != 'on' order by b.oldest_due nulls last, b.outstanding_ore desc", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$d['checked']++;
			if (in_array($r['pay_status'], array('overdue','failed'))) {
				$d['overdue'][] = $r;
				$d['overdue_sum_ore'] += (int)$r['outstanding_ore'];
			}
		}

		// Ledgers near a limit: the latest usage snapshot per ledger against the ledger's limits
		$q = db_select("select distinct on (regnskab_id) * from usage_snapshots order by regnskab_id, taken_at desc", __FILE__ . " linje " . __LINE__);
		$snaps = array();
		while ($r = db_fetch_array($q)) {
			$snaps[(int)$r['regnskab_id']] = $r;
			if (!$d['snap_date'] || $r['taken_at'] > $d['snap_date']) $d['snap_date'] = $r['taken_at'];
		}
		# only these can differ from the plan's own limits (same rules as ent_resolve)
		$full = array();
		$q = db_select("select regnskab_id from regnskab_overrides where kind in ('plan','limit') and (expires_at is null or expires_at >= current_date) union select regnskab_id from regnskab_plan_changes where applied_at is null and cancelled_at is null and effective_from <= current_date", __FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$full[(int)$r['regnskab_id']] = true;
		}
		$int = fn($v) => $v === null ? null : (int)$v;
		foreach ($snaps as $id => $sn) {
			if (!isset($ledgers[$id]) || $ledgers[$id]['lukket'] == 'on' || !$ledgers[$id]['plan_key']) continue;

			if (isset($full[$id])) {
				$e = ent_resolve($id);
				if (!$e['plan']) continue;
				$plan = $e['plan'];
				$lims = $e['limits'];
			} elseif (isset($cat['plans'][$ledgers[$id]['plan_key']])) {
				$plan = $cat['plans'][$ledgers[$id]['plan_key']];
				$lims = array(
					'max_users' => $int($plan['max_users']),
					'included_postings' => $int($plan['included_postings']),
					'max_items' => $int($plan['max_items']),
				);
			} else {
				continue;
			}

			$measured = array(
				'max_users' => array($sn['users_active'], vr_t('brugere','users')),
				'included_postings' => array($sn['postings_12m'], vr_t('posteringer','entries')),
				'max_items' => array($sn['items'], vr_t('varer','items')),
			);
			foreach ($measured as $k => $pair) {
				$lim = isset($lims[$k]) ? $lims[$k] : null;
				if ($lim === null || $lim <= 0 || $pair[0] === null) continue;
				$pct = (int)round($pair[0] / $lim * 100);
				if ($pct >= 80) {
					$d['near'][] = array('id' => $id, 'name' => $ledgers[$id]['regnskab'], 'plan' => $plan['name'], 'what' => $pair[1], 'used' => (int)$pair[0], 'limit' => (int)$lim, 'pct' => $pct);
				}
			}
		}
		usort($d['near'], fn($a, $b) => $b['pct'] <=> $a['pct']);

		return $d;
	}
}

if (!function_exists('ov_render')) {
	// Prints the overview: four figures, active accounts by plan, customers with overdue payments and accounts near their limits.
	function ov_render() {
		global $sprog_id;

		$d = ov_data();
		$kr = fn($ore) => number_format($ore / 100, 0, ',', '.').' kr.';

		// The four figures
		print "<section class=\"vr-sect\"><h2>".vr_t('Kommercielt overblik','Commercial overview')." <small>".vr_t('beregnet nu fra panelets data','computed now from the panel data')."</small></h2><div class=\"vr-card\"><div class=\"vr-stats\">";
		$stat = fn($label, $val, $sub, $href) => "<a class=\"vr-stat\" href=\"".vr_h($href)."\"><span>".$label."</span><b>".$val."</b><small>".$sub."</small></a>";
		$ni = function_exists('inst_all') ? count(inst_all('active')) : 0;

		$sub = $d['no_plan'].' '.vr_t('uden pakke','without plan').' · '.$d['closed'].' '.vr_t('lukkede','closed');
		if ($ni) $sub .= ' · '.$ni.' '.vr_t('egne installationer','self-hosted');
		print $stat(vr_t('Aktive regnskaber','Active accounts'), vr_num($d['active']), $sub, '../admin/operator_panel.php?ltab=alle');

		$sub = $d['mrr_n'].' '.vr_t('betalende pakker','paying plans');
		if ($d['erp_n']) $sub .= ' · '.$d['erp_n'].' ERP '.vr_t('efter aftale','by agreement');
		if ($d['canceled']) $sub .= ' · '.$d['canceled'].' '.vr_t('opsagt','cancelled');
		print $stat('MRR', $kr($d['mrr_ore']), $sub, '../admin/operator_panel.php?ltab=alle');

		$sub = $kr($d['overdue_sum_ore']).' '.vr_t('udestående','outstanding').' · '.$d['checked'].' '.vr_t('kunder tjekket','customers checked');
		print $stat(vr_t('Forfaldne betalinger','Overdue payments'), vr_num(count($d['overdue'])), $sub, '#ov-overdue');

		$sub = $d['snap_date'] ? vr_t('måling','measured').' '.vr_h($d['snap_date']) : vr_t('ingen måling endnu','no measurement yet');
		print $stat(vr_t('Tæt på grænser','Near limits'), vr_num(count($d['near'])), $sub, '#ov-near');
		print "</div></div></section>";

		// Active accounts by plan
		print "<div class=\"vr-grid2\" style=\"margin-top:22px\">";
		$tot = max(1, $d['active']);
		print "<section class=\"vr-sect\"><h2>".vr_t('Aktive regnskaber fordelt på pakker','Active accounts by plan')."</h2><div class=\"vr-card\"><table class=\"vr-t\">";
		print "<thead><tr><th>".vr_t('Pakke','Plan')."</th><th class=\"vr-r\">".vr_t('Regnskaber','Accounts')."</th><th style=\"width:140px\"></th><th class=\"vr-r\">MRR</th></tr></thead><tbody>";
		foreach ($d['by_plan'] as $k => $p) {
			$pct = (int)round($p['n'] / $tot * 100);
			print "<tr><td>".vr_h($p['name'])."</td><td class=\"vr-r vr-num\">".vr_num($p['n'])."</td>";
			print "<td><span class=\"vr-b\" style=\"width:110px\"><i style=\"width:$pct%\"></i></span> <span class=\"vr-mut2\" style=\"font-size:12px\">$pct %</span></td>";
			print "<td class=\"vr-r vr-num\">".($p['mrr_ore'] ? $kr($p['mrr_ore']) : '<span class="vr-mut2">–</span>')."</td></tr>";
		}
		$pct = (int)round($d['no_plan'] / $tot * 100);
		print "<tr><td class=\"vr-mut\">".vr_t('Ingen pakke endnu','No plan yet')."</td><td class=\"vr-r vr-num\">".vr_num($d['no_plan'])."</td>";
		print "<td><span class=\"vr-b\" style=\"width:110px\"><i style=\"width:$pct%\"></i></span> <span class=\"vr-mut2\" style=\"font-size:12px\">$pct %</span></td>";
		print "<td class=\"vr-r vr-mut2\">–</td></tr>";
		print "</tbody></table>";
		print "<div class=\"vr-foot\"><span>".vr_t('MRR = pakkens månedspris plus tillæg fra kataloget. Årlige aftaler regnes pr. måned. Ekstra brugere og posteringsforbrug er ikke med.','MRR = monthly plan price plus add-ons from the catalogue. Yearly plans counted per month. Extra users and posting overage excluded.')."</span></div></div></section>";

		// Customers with overdue payments (the first 8)
		print "<section class=\"vr-sect\" id=\"ov-overdue\"><h2>".vr_t('Kunder med forfaldne betalinger','Customers with overdue payments')." <small>".count($d['overdue'])."</small></h2><div class=\"vr-card\"><table class=\"vr-t\">";
		print "<thead><tr><th>".vr_t('Kunde','Customer')."</th><th class=\"vr-r\">".vr_t('Udestående','Outstanding')."</th><th>".vr_t('Ældste forfald','Oldest due')."</th><th>".vr_t('Status','Status')."</th></tr></thead><tbody>";
		foreach (array_slice($d['overdue'], 0, 8) as $o) {
			$url = "../admin/operator_panel.php?regnskab_id=".(int)$o['regnskab_id']."&tab=betalinger";
			print "<tr onclick=\"location.href='$url'\" style=\"cursor:pointer\">";
			print "<td class=\"vr-nm\"><a href=\"$url\">".vr_h($o['regnskab'])."</a></td>";
			print "<td class=\"vr-r vr-num\">".$kr((int)$o['outstanding_ore'])."</td>";
			print "<td class=\"vr-mut\">".($o['oldest_due'] ? date('d-m-Y', strtotime($o['oldest_due'])) : '–')."</td>";
			print "<td><span class=\"vr-st\"><span class=\"vr-dot vr-bad\"></span>".($o['pay_status'] == 'failed' ? vr_t('Fejlet','Failed') : vr_t('Forfalden','Overdue'))."</span></td></tr>";
		}
		if (!$d['overdue']) {
			$empty = $d['checked'] ? vr_t('Ingen forfaldne betalinger blandt de tjekkede kunder.','No overdue payments among the checked customers.') : vr_t('Ingen kunder er tjekket endnu. Status hentes, når en kundes Betalinger-fane åbnes, og af det natlige job.','No customers checked yet. Status is fetched when a customer Betalinger tab is opened, and by the nightly job.');
			print "<tr><td colspan=\"4\" class=\"vr-mut\" style=\"padding:18px 20px\">".$empty."</td></tr>";
		}
		print "</tbody></table></div></section></div>";

		// Accounts near their limits (the first 10)
		print "<section class=\"vr-sect\" id=\"ov-near\" style=\"margin-top:22px\"><h2>".vr_t('Regnskaber tæt på deres grænser','Accounts near their limits')." <small>&ge; 80 % · ".count($d['near'])."</small></h2><div class=\"vr-card\"><table class=\"vr-t\">";
		print "<thead><tr><th>".vr_t('Regnskab','Account')."</th><th>".vr_t('Pakke','Plan')."</th><th>".vr_t('Grænse','Limit')."</th><th class=\"vr-r\">".vr_t('Forbrug','Usage')."</th><th style=\"width:160px\">".vr_t('Udnyttelse','Utilisation')."</th></tr></thead><tbody>";
		foreach (array_slice($d['near'], 0, 10) as $n) {
			$url = "../admin/operator_panel.php?regnskab_id=".(int)$n['id']."&tab=forbrug";
			print "<tr onclick=\"location.href='$url'\" style=\"cursor:pointer\">";
			print "<td class=\"vr-nm\"><a href=\"$url\">".vr_h($n['name'])."</a></td>";
			print "<td class=\"vr-mut\">".vr_h($n['plan'])."</td>";
			print "<td>".vr_h($n['what'])."</td>";
			print "<td class=\"vr-r vr-num\">".vr_num($n['used'])." / ".vr_num($n['limit'])."</td>";
			print "<td><span class=\"vr-b\" style=\"width:110px\"><i class=\"".($n['pct'] >= 100 ? 'vr-hi' : 'vr-mid')."\" style=\"width:".min(100, $n['pct'])."%\"></i></span> <span class=\"vr-mut2\" style=\"font-size:12px\">".$n['pct']." %</span></td></tr>";
		}
		if (!$d['near']) {
			print "<tr><td colspan=\"5\" class=\"vr-mut\" style=\"padding:18px 20px\">".vr_t('Ingen regnskaber med pakke er over 80 % af en grænse i seneste måling.','No accounts with a plan are above 80 % of a limit in the latest measurement.')."</td></tr>";
		}
		print "</tbody></table>";
		print "<div class=\"vr-foot\"><span>".vr_t('Grænser: brugere, posteringer 12 mdr. og varer mod gældende grænse. Kun regnskaber med pakke.','Limits: users, entries 12 m and items against the effective limit. Plans only.')."</span></div></div></section>";
	}
}
?>
