<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/settings.php --- ver 5.1.0 --- 2026.11.01 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260928 Sawaneh Settings front page (parent spec S1/S3/S4): search on top and one card per
//                  settings group the user may open, generated from systemdata/settingsRegistry.php.
// 20260929 Sawaneh Phase 4a (settings redesign spec §8.5, §8.10, §8.13, §8.14): search finds single settings and
//                  old menu names, transition banner, "Hvor er...?" link, PoS licence card, shortcuts.
// 20261001 Sawaneh Phase 4a §8.13: optional modules shown on or off with Aktivér, computed status badges per card.
// 20261002 Sawaneh Hand-over 2 Oct (A1, settings redesign §8.0): groups as rows in three labelled lists instead of tiles,
//                  "Kræver opmærksomhed" above them, status as a dot plus text, search results in a dropdown, no Back
//                  button (the shell shows the breadcrumb). Optional modules are shown inside their group, not here.
// 20261004 Sawaneh Search results grouped by group with at most 8 until "Vis alle" (§8.5); the transition banner
//                  only for users with activity from before the new settings landed (§8.10).
// 20261004 Sawaneh Open to anyone with read on a settings group, not only the old Indstillinger bit (decision 16).
// 20261101 CL/ASR New design (prototype D, as index/dashboard.php): the page is built from css/saldi-kit.css under .sk.
//                 The group lists are panels with a grey header band and 24 px zebra rows, the search is the kit search with its dropdown, notes and the shortcuts dialog are kit components, and the one filled button is navy.
//                 Same content, order, texts, links and behaviour.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $sprog_id
 * @var string $db_encode
 */

@session_start();
$s_id = session_id();

$title = "Indstillinger";
$css = "../css/settingsHub.css?v=20261101d";
$modulnr = 0; // access is per settings group (settings_require_any_access below), not the old Indstillinger bit
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");

settings_require_any_access();
if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['csrf_token'], $_POST['dismiss']) && hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token']) && (int) $bruger_id > 0) {
		update_settings_value('transition_banner', 'settings_ui', 'dismissed', 'Settings transition banner dismissed', (int) $bruger_id);
	}
	header('Location: settings.php');
	exit;
}

$hubGroups = settings_accessible_groups();
$hubAttention = settings_attention($hubGroups, (int) $sprog_id);
$hubReadOnly = (bool) $hubGroups;
foreach ($hubGroups as $hubGroup) {
	if (!function_exists('perm_can') || perm_can($hubGroup['def']['permission'], 'write')) {
		$hubReadOnly = false;
		break;
	}
}
settings_hub_view(array(
	'groups'    => $hubGroups,
	'status'    => settings_group_status($hubGroups, settings_optional_modules(), $hubAttention, (int) $sprog_id),
	'attention' => $hubAttention,
	'readOnly'  => $hubReadOnly,
	'notice'    => ((int) $bruger_id > 0) && get_settings_value('transition_banner', 'settings_ui', '', (int) $bruger_id) !== 'dismissed' && settings_show_transition_banner((int) $bruger_id),
	'posLocked' => (function_exists('perm_can') && perm_can('settings.pos', 'read') && !settings_has_module('pos')),
	'company'   => function_exists('st_company') ? st_company() : '',
	'csrf'      => (string) $_SESSION['csrf_token'],
), (int) $sprog_id, (string) $db_encode);

/**
 * @param array<string, mixed> $vm groups, status, attention, readOnly, notice, posLocked, company, csrf
 */
function settings_hub_view(array $vm, int $sprogId, string $dbEncode): void
{
	$charset = ($dbEncode === 'UTF8') ? 'UTF-8' : 'ISO-8859-1';
	$h = function ($s) use ($charset): string {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$t = function (string $text) use ($sprogId, $h): string {
		return $h(findtekst($text, $sprogId));
	};
	$js = function (string $text) use ($sprogId, $charset): string {
		return json_encode(mb_convert_encoding(findtekst($text, $sprogId), 'UTF-8', $charset), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	};
	$groups = $vm['groups'];
	// status kind (settings_group_status / settings_attention) -> kit tone: a dot in that colour before the text
	$tones = array('warn' => 'sk-warn', 'err' => 'sk-bad', 'ok' => 'sk-ok');
	?>
<link rel='stylesheet' href='../css/saldi-theme.css?v=7'><link rel='stylesheet' href='../css/saldi-kit.css?v=20261101b'>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= $js('122|Indstillinger') ?>;</script>
<?php if (function_exists('page_help')) { page_help(array('shortcuts' => '[data-keys]')); } ?>
<?= settings_breadcrumb_script(settings_breadcrumb('', '', $sprogId), $charset) ?>
<div class="sk sk-page sh-page">
  <section class="sk-head">
    <div class="sk-head-tx">
      <h1 class="sk-title"><?= $t('122|Indstillinger') ?></h1>
      <p class="sk-lead"><?= $h(sprintf(findtekst('6020|Gælder for alle i %s. Dine personlige valg finder du under dit navn øverst til højre.', $sprogId), $vm['company'])) ?></p>
    </div>
    <div class="sk-head-acts">
      <div class="sk-search sh-search">
        <i class='bx bx-search' aria-hidden="true"></i><input type="search" class="sk-input" id="sh-search" placeholder="<?= $t('6039|Søg – fx moms, GLS eller et gammelt menunavn') ?>" aria-label="<?= $t('5663|Søg i indstillinger…') ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sh-res" aria-autocomplete="list"><kbd aria-hidden="true">/</kbd>
        <div class="sk-pop sh-res" id="sh-res" role="listbox" hidden></div>
      </div>
    </div>
  </section>

  <?php if (!$groups) { ?>
  <div class="sk-note sk-warn"><i class='bx bx-lock-alt' aria-hidden="true"></i><b><?= $t('5665|Du har ikke adgang til nogen indstillinger. Kontakt en administrator.') ?></b></div>
  <?php } elseif ($vm['readOnly']) { ?>
  <div class="sk-note sk-info" role="status"><i class='bx bx-lock-alt' aria-hidden="true"></i><span><b><?= $t('6031|Du har læseadgang') ?></b> <span class="sk-muted"><?= $t('6032|Du kan se indstillingerne, men ikke ændre dem. Kontakt en administrator.') ?></span></span></div>
  <?php } ?>

  <?php if ($vm['attention']) { ?>
  <section class="sk-panel sh-panel">
    <div class="sk-panel-h"><h2 class="sk-panel-t"><?= $t('6016|Kræver opmærksomhed') ?></h2></div>
    <div class="sk-list sh-attn">
      <?php foreach ($vm['attention'] as $item) { ?>
      <div class="sk-list-row">
        <span class="sk-list-ic sh-attn-ic" aria-hidden="true"><span class="sk-dot <?= isset($tones[$item['kind']]) ? $tones[$item['kind']] : '' ?>"></span></span>
        <span class="sk-list-tx"><b class="sk-list-t"><?= $h($item['title']) ?></b><span class="sk-list-d"><?= $h($item['sub']) ?></span></span>
        <a class="sk-btn sk-btn-sm" href="<?= $h($item['url']) ?>"><?= $h($item['button']) ?></a>
      </div>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

  <div class="sk-cols sh-cols">
    <?php foreach (settings_group_lists() as $listId => $list) {
    	$rows = array_values(array_filter($list['groups'], function ($g) use ($groups) { return isset($groups[$g]); }));
    	$locked = ($listId === 'trade' && $vm['posLocked'] && !isset($groups['pos']));
    	if (!$rows && !$locked) {
    		continue;
    	}
    ?>
    <section class="sk-panel sh-panel">
      <div class="sk-panel-h"><h2 class="sk-panel-t"><?= $t($list['label']) ?></h2></div>
      <div class="sk-list">
        <?php foreach ($rows as $group) {
        	$g = $groups[$group];
        	$st = isset($vm['status'][$group]) ? $vm['status'][$group] : null;
        ?>
        <a class="sk-list-row" href="<?= $h($g['entries'][0]['url']) ?>">
          <i class='bx <?= $h($g['def']['icon']) ?> sk-list-ic' aria-hidden="true"></i>
          <span class="sk-list-tx"><b class="sk-list-t"><?= $t($g['def']['label']) ?></b><span class="sk-list-d"><?= $t($g['def']['description']) ?></span></span>
          <span class="sk-list-meta"><?php if ($st) { ?><?php if (isset($tones[$st['kind']])) { ?><span class="sk-status <?= $tones[$st['kind']] ?>"><?= $h($st['text']) ?></span><?php } else { ?><?= $h($st['text']) ?><?php } ?><?php } ?></span>
          <i class='bx bx-chevron-right sk-list-chev' aria-hidden="true"></i>
        </a>
        <?php } ?>
        <?php if ($locked) { ?>
        <div class="sk-list-row sk-off" aria-disabled="true">
          <i class='bx bx-store-alt sk-list-ic' aria-hidden="true"></i>
          <span class="sk-list-tx"><b class="sk-list-t"><?= $t('2226|Kasse') ?></b><span class="sk-list-d"><?= $t('5740|Kræver kasselicens — kontakt Saldi') ?></span></span>
          <span class="sk-list-meta"></span>
          <i class='bx bx-lock-alt sk-list-chev' aria-hidden="true"></i>
        </div>
        <?php } ?>
      </div>
    </section>
    <?php } ?>

    <?php if ($vm['notice'] && $groups) { ?>
    <section class="sk-panel sh-panel">
      <div class="sk-panel-h"><h2 class="sk-panel-t"><?= $t('6021|Ny struktur') ?></h2></div>
      <p class="sk-panel-note sh-notice-tx"><?= $t('6022|Indstillingerne er samlet i færre, tydeligere grupper. Alt er her stadig – søg efter det gamle menunavn, så finder du det nye sted.') ?></p>
      <div class="sk-list sh-ls">
        <a class="sk-list-row" href="settingsMoved.php"><span class="sk-list-tx"><span class="sk-list-t"><?= $t('6023|Hvor er de gamle menupunkter?') ?></span></span><i class='bx bx-right-arrow-alt sk-list-chev' aria-hidden="true"></i></a>
        <button type="button" class="sk-list-row" data-keys><span class="sk-list-tx"><span class="sk-list-t"><?= $t('6024|Tastaturgenveje') ?></span></span><i class='bx bx-right-arrow-alt sk-list-chev' aria-hidden="true"></i></button>
      </div>
      <form class="sk-foot" method="post" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?= $h($vm['csrf']) ?>">
        <input type="hidden" name="dismiss" value="1">
        <button type="submit" class="sk-btn sk-btn-ghost sk-btn-sm"><?= $t('5746|Skjul') ?></button>
      </form>
    </section>
    <?php } ?>
  </div>

  <?php if ($groups && !$vm['notice']) { ?>
  <p class="sh-foot"><a href="settingsMoved.php"><?= $t('6023|Hvor er de gamle menupunkter?') ?></a><button type="button" class="sk-link" data-keys><?= $t('6024|Tastaturgenveje') ?></button></p>
  <?php } ?>

  <div class="sk-scrim" id="sh-backdrop" hidden></div>
  <div class="sk-dialog" id="sh-dialog" role="dialog" aria-modal="true" aria-labelledby="sh-dialog-title" hidden>
    <h3 class="sk-dialog-t" id="sh-dialog-title"><?= $t('6024|Tastaturgenveje') ?></h3>
    <ul class="sk-kv sh-keys">
      <li><span><?= $t('5663|Søg i indstillinger…') ?></span><span><kbd>/</kbd></span></li>
      <li><span><?= $t('3|Gem') ?></span><span><kbd>Ctrl S</kbd></span></li>
      <li><span><?= $t('2172|Luk') ?></span><span><kbd>Esc</kbd></span></li>
      <li><span><?= $t('5647|Tilbage') ?></span><span><kbd>Alt ←</kbd></span></li>
    </ul>
    <div class="sk-dialog-f"><button type="button" class="sk-btn sk-btn-primary" id="sh-dialog-close"><?= $t('2172|Luk') ?></button></div>
  </div>
</div>

<script>
(function () {
	var input = document.getElementById('sh-search'), res = document.getElementById('sh-res');
	var txt = { count: <?= $js('6040|%s resultater') ?>, none: <?= $js('6042|Ingen resultater. Prøv et andet ord, eller se "Hvor er de gamle menupunkter?"') ?>, all: <?= $js('6393|Vis alle (%s)') ?> };
	var hits = [], sel = 0, timer = null, legacyLabel = '', personalLabel = '', expanded = false, LIMIT = 8;

	// Results grouped by their group, in the order the best hit of each group ranks; at most 8 until "Vis alle" (spec §8.5).
	function grouped() {
		var order = [], byGroup = {};
		hits.forEach(function (hit) {
			var g = (hit.path || '').split(' › ')[0];
			if (!byGroup[g]) { byGroup[g] = []; order.push(g); }
			byGroup[g].push(hit);
		});
		var out = [], n = 0;
		order.forEach(function (g) {
			byGroup[g].forEach(function (hit) {
				if (expanded || n < LIMIT) { out.push({ group: g, hit: hit }); n++; }
			});
		});
		return out;
	}
	function visible() { return grouped().map(function (x) { return x.hit; }); }

	function mark(parent, text, q) {
		var i = text.toLowerCase().indexOf(q);
		if (q === '' || i < 0) { parent.appendChild(document.createTextNode(text)); return; }
		parent.appendChild(document.createTextNode(text.slice(0, i)));
		var m = document.createElement('mark');
		m.textContent = text.slice(i, i + q.length);
		parent.appendChild(m);
		parent.appendChild(document.createTextNode(text.slice(i + q.length)));
	}
	function close() { res.hidden = true; res.textContent = ''; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); }
	function render(q) {
		res.textContent = '';
		var head = document.createElement('div');
		if (!hits.length) {
			head.className = 'sk-pop-note sh-none';
			head.textContent = txt.none;
			res.appendChild(head);
		} else {
			head.className = 'sk-pop-note sh-cnt';
			head.textContent = txt.count.replace('%s', hits.length);
			res.appendChild(head);
			var lastGroup = null;
			grouped().forEach(function (row, i) {
				var hit = row.hit;
				if (row.group !== lastGroup) {
					var gh = document.createElement('div');
					gh.className = 'sk-pop-h';
					gh.textContent = row.group;
					res.appendChild(gh);
					lastGroup = row.group;
				}
				var a = document.createElement('a');
				a.className = 'sk-opt' + (i === sel ? ' on' : '');
				a.href = hit.url;
				a.id = 'sh-it-' + i;
				a.setAttribute('role', 'option');
				a.setAttribute('aria-selected', i === sel ? 'true' : 'false');
				var b = document.createElement('b');
				mark(b, hit.label, q);
				a.appendChild(b);
				if (hit.path) {
					var s = document.createElement('span');
					if (hit.personal) { s.className = 'sh-per'; }
					s.textContent = hit.path;
					a.appendChild(s);
				}
				if (hit.legacy) {
					var em = document.createElement('em');
					em.appendChild(document.createTextNode(legacyLabel + ' '));
					mark(em, hit.legacy, q);
					a.appendChild(em);
				}
				res.appendChild(a);
			});
			if (!expanded && hits.length > LIMIT) {
				var more = document.createElement('button');
				more.type = 'button';
				more.className = 'sh-more';
				more.textContent = txt.all.replace('%s', hits.length);
				more.addEventListener('click', function () { expanded = true; render(q); });
				res.appendChild(more);
			}
			input.setAttribute('aria-activedescendant', 'sh-it-' + sel);
		}
		res.hidden = false;
		input.setAttribute('aria-expanded', 'true');
	}
	function lookup() {
		var q = input.value.trim().toLowerCase();
		window.clearTimeout(timer);
		if (q.length < 2) { hits = []; close(); return; }
		timer = window.setTimeout(function () {
			fetch('settingsSearch.php?search=' + encodeURIComponent(q), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (input.value.trim().toLowerCase() !== q) { return; }
					legacyLabel = data.legacyLabel || '';
					personalLabel = data.personalLabel || '';
					hits = (data.results || []).map(function (r) { return { label: r.label, url: r.url, path: r.group || '', legacy: r.legacy || '', personal: false }; })
						.concat((data.fields || []).map(function (f) { return { label: f.label, url: f.url, path: f.personal ? f.group : (f.group + (f.section ? ' › ' + f.section : '')), legacy: f.legacy || '', personal: !!f.personal }; }));
					sel = 0;
					expanded = false;
					render(q);
				})
				.catch(function () {});
		}, 150);
	}
	input.addEventListener('input', lookup);
	input.addEventListener('focus', function () { if (input.value.trim().length >= 2) { lookup(); } });
	input.addEventListener('blur', function () { window.setTimeout(close, 150); });
	input.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { input.value = ''; hits = []; close(); input.blur(); return; }
		if (res.hidden || !hits.length) { return; }
		if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
			e.preventDefault();
			sel = Math.max(0, Math.min(visible().length - 1, sel + (e.key === 'ArrowDown' ? 1 : -1)));
			render(input.value.trim().toLowerCase());
			var cur = document.getElementById('sh-it-' + sel);
			if (cur && cur.scrollIntoView) { cur.scrollIntoView({ block: 'nearest' }); }
		} else if (e.key === 'Enter') {
			e.preventDefault();
			window.location = visible()[sel].url;
		}
	});
	res.addEventListener('mousedown', function (e) { e.preventDefault(); });
	res.addEventListener('click', function (e) { var a = e.target.closest('a'); if (a) { window.location = a.getAttribute('href'); } });
	res.addEventListener('mousedown', function (e) { if (e.target.closest('.sh-more')) { e.preventDefault(); } });

	var dialog = document.getElementById('sh-dialog'), backdrop = document.getElementById('sh-backdrop'), opener = null;
	function toggleDialog(open, from) {
		dialog.hidden = !open;
		backdrop.hidden = !open;
		if (open) { opener = from; document.getElementById('sh-dialog-close').focus(); }
		else if (opener) { opener.focus(); }
	}
	document.querySelectorAll('[data-keys]').forEach(function (b) { b.addEventListener('click', function () { toggleDialog(true, b); }); });
	backdrop.addEventListener('click', function () { toggleDialog(false); });
	document.getElementById('sh-dialog-close').addEventListener('click', function () { toggleDialog(false); });
	dialog.addEventListener('keydown', function (e) { if (e.key === 'Tab') { e.preventDefault(); document.getElementById('sh-dialog-close').focus(); } });
	document.addEventListener('keydown', function (e) {
		var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName);
		if (e.key === '/' && !typing) { e.preventDefault(); input.focus(); }
		if (e.key === 'Escape' && !dialog.hidden) { toggleDialog(false); }
	});
})();
</script>
	<?php
}
