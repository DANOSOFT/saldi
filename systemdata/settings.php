<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/settings.php --- lap 5.0.0 --- 2026.09.28 ---
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
// Copyright (c) 2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20260928 Sawaneh Settings front page (parent spec S1/S3/S4): search on top and one card per
//                  settings group the user may open, generated from systemdata/settingsRegistry.php.
// 20260929 Sawaneh Phase 4a (settings redesign spec §8.5, §8.10, §8.13, §8.14): search finds single settings and
//                  old menu names, transition banner, "Hvor er...?" link, PoS licence card, shortcuts.
// 20261001 Sawaneh Phase 4a §8.13: optional modules shown on or off with Aktivér, computed status badges per card.
// 20261006 Sawaneh §8.8 Getting started: a "Kom godt i gang" card (progress, reopen the guide, bring back a hidden checklist)
//                  while the welcome guide is not completed, and a link in the footer afterwards.
// 20261002 Sawaneh Hand-over 2 Oct (A1, settings redesign §8.0): groups as rows in three labelled lists instead of tiles,
//                  "Kræver opmærksomhed" above them, status as a dot plus text, search results in a dropdown, no Back
//                  button (the shell shows the breadcrumb). Optional modules are shown inside their group, not here.
// 20261004 Sawaneh Search results grouped by group with at most 8 until "Vis alle" (§8.5); the transition banner
//                  only for users with activity from before the new settings landed (§8.10).
// 20261004 Sawaneh Open to anyone with read on a settings group, not only the old Indstillinger bit (decision 16).

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $sprog_id
 * @var string $db_encode
 * @var string $buttonColor
 * @var string $buttonTxtColor
 */

@session_start();
$s_id = session_id();

$title = "Indstillinger";
$css = "../css/settingsHub.css?v=20261004";
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
include_once(__DIR__ . "/../includes/onboarding.php");
$onbState = onb_get('onboarding_state');
$hubOnboarding = ($onbState !== '' && onb_can_run()) ? array('state' => $onbState, 'done' => onb_done_count(), 'total' => count(onb_steps_def()), 'next' => onb_next_step(), 'steps' => (bool) onb_steps()) : null;
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
	'onboarding' => $hubOnboarding,
), (int) $sprog_id, (string) $db_encode);

/**
 * @param array<string, mixed> $vm groups, status, attention, readOnly, notice, posLocked, company, csrf, onboarding
 */
function settings_hub_view(array $vm, int $sprogId, string $dbEncode): void
{
	global $buttonColor, $buttonTxtColor;
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
	$accent = st_accent_style(!empty($buttonColor) ? (string) $buttonColor : '#114691', !empty($buttonTxtColor) ? (string) $buttonTxtColor : '#ffffff');
	$dots = array('warn' => 'sh-dot-warn', 'err' => 'sh-dot-err', 'ok' => 'sh-dot-ok');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= $js('122|Indstillinger') ?>;</script>
<?php if (function_exists('page_help')) { page_help(array('shortcuts' => '[data-keys]')); } ?>
<?= settings_breadcrumb_script(settings_breadcrumb('', '', $sprogId), $charset) ?>
<div class="sh-page" style="<?= $h($accent) ?>">
  <section class="sh-phead">
    <div>
      <h1><?= $t('122|Indstillinger') ?></h1>
      <p class="sh-lead"><?= $h(sprintf(findtekst('6020|Gælder for alle i %s. Dine personlige valg finder du under dit navn øverst til højre.', $sprogId), $vm['company'])) ?></p>
    </div>
    <div class="sh-search">
      <label for="sh-search"><i class='bx bx-search' aria-hidden="true"></i><input type="search" id="sh-search" placeholder="<?= $t('6039|Søg – fx moms, GLS eller et gammelt menunavn') ?>" aria-label="<?= $t('5663|Søg i indstillinger…') ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sh-res" aria-autocomplete="list"><kbd aria-hidden="true">/</kbd></label>
      <div class="sh-res" id="sh-res" role="listbox" hidden></div>
    </div>
  </section>

  <?php if (!$groups) { ?>
  <div class="sh-attn"><div><i class='bx bx-lock-alt' aria-hidden="true"></i><div class="sh-tx"><b><?= $t('5665|Du har ikke adgang til nogen indstillinger. Kontakt en administrator.') ?></b></div></div></div>
  <?php } elseif ($vm['readOnly']) { ?>
  <div class="sh-attn" role="status"><div><i class='bx bx-lock-alt' aria-hidden="true"></i><div class="sh-tx"><b><?= $t('6031|Du har læseadgang') ?></b><span><?= $t('6032|Du kan se indstillingerne, men ikke ændre dem. Kontakt en administrator.') ?></span></div></div></div>
  <?php } ?>

  <?php $onb = $vm['onboarding']; if ($onb && $onb['state'] !== 'completed') {
  	$onbNext = $onb['next'] !== '' ? $onb['next'] : 'profile';
  	$onbDef = onb_steps_def();
  ?>
  <section class="sh-sect">
    <div class="sh-attn">
      <div>
        <i class='bx bx-rocket' aria-hidden="true"></i>
        <div class="sh-tx"><b><?= $t('6748|Kom godt i gang') ?></b><span><?= $h(sprintf(findtekst('6750|%s af %s trin færdige', $sprogId), $onb['done'], $onb['total'])) ?></span></div>
        <?php if ($onb['state'] === 'hidden') { ?>
        <form method="post" action="../index/onboarding.php" style="margin:0"><input type="hidden" name="csrf_token" value="<?= $h($vm['csrf']) ?>"><input type="hidden" name="action" value="unhide"><input type="hidden" name="back" value="settings"><button type="submit" class="sh-btn"><?= $t('6780|Vis tjekliste på oversigten') ?></button></form>
        <?php } ?>
        <a class="sh-btn" href="../index/onboarding.php?step=<?= $h($onbNext) ?>" onclick="if (window.parent && window.parent.saldiOnboardingOpen) { window.parent.saldiOnboardingOpen('<?= $h($onbNext) ?>'); return false; }"><?= $onb['next'] !== '' ? $h(sprintf(findtekst('6752|Fortsæt: %s', $sprogId), findtekst($onbDef[$onb['next']][0], $sprogId))) : $t('6758|Gennemse opsætning') ?></a>
      </div>
    </div>
  </section>
  <?php } ?>

  <?php if ($vm['attention']) { ?>
  <section class="sh-sect">
    <h2><?= $t('6016|Kræver opmærksomhed') ?></h2>
    <div class="sh-attn">
      <?php foreach ($vm['attention'] as $item) { ?>
      <div>
        <i class="sh-dot <?= $dots[$item['kind']] ?>" aria-hidden="true"></i>
        <div class="sh-tx"><b><?= $h($item['title']) ?></b><span><?= $h($item['sub']) ?></span></div>
        <a class="sh-btn" href="<?= $h($item['url']) ?>"><?= $h($item['button']) ?></a>
      </div>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

  <div class="sh-cols">
    <?php foreach (settings_group_lists() as $listId => $list) {
    	$rows = array_values(array_filter($list['groups'], function ($g) use ($groups) { return isset($groups[$g]); }));
    	$locked = ($listId === 'trade' && $vm['posLocked'] && !isset($groups['pos']));
    	if (!$rows && !$locked) {
    		continue;
    	}
    ?>
    <section class="sh-sect">
      <h2><?= $t($list['label']) ?></h2>
      <div class="sh-card">
        <?php foreach ($rows as $group) {
        	$g = $groups[$group];
        	$st = isset($vm['status'][$group]) ? $vm['status'][$group] : null;
        ?>
        <a class="sh-row" href="<?= $h($g['entries'][0]['url']) ?>">
          <i class='bx <?= $h($g['def']['icon']) ?> sh-ic' aria-hidden="true"></i>
          <span class="sh-tx"><b><?= $t($g['def']['label']) ?></b><span><?= $t($g['def']['description']) ?></span></span>
          <span class="sh-meta<?= ($st && $st['kind'] !== '') ? ' sh-meta-' . $h($st['kind']) : '' ?>"><?php if ($st) { ?><?php if (isset($dots[$st['kind']])) { ?><i class="sh-dot sh-dot-s <?= $dots[$st['kind']] ?>" aria-hidden="true"></i><?php } ?><?= $h($st['text']) ?><?php } ?></span>
          <i class='bx bx-chevron-right sh-chev' aria-hidden="true"></i>
        </a>
        <?php } ?>
        <?php if ($locked) { ?>
        <div class="sh-row sh-row-off" aria-disabled="true">
          <i class='bx bx-store-alt sh-ic' aria-hidden="true"></i>
          <span class="sh-tx"><b><?= $t('2226|Kasse') ?></b><span><?= $t('5740|Kræver kasselicens — kontakt Saldi') ?></span></span>
          <span class="sh-meta"></span>
          <i class='bx bx-lock-alt sh-chev' aria-hidden="true"></i>
        </div>
        <?php } ?>
      </div>
    </section>
    <?php } ?>

    <?php if ($vm['notice'] && $groups) { ?>
    <section class="sh-sect">
      <h2><?= $t('6021|Ny struktur') ?></h2>
      <div class="sh-notice">
        <p><?= $t('6022|Indstillingerne er samlet i færre, tydeligere grupper. Alt er her stadig – søg efter det gamle menunavn, så finder du det nye sted.') ?></p>
        <div class="sh-ls">
          <a href="settingsMoved.php"><?= $t('6023|Hvor er de gamle menupunkter?') ?><i class='bx bx-right-arrow-alt' aria-hidden="true"></i></a>
          <button type="button" data-keys><?= $t('6024|Tastaturgenveje') ?><i class='bx bx-right-arrow-alt' aria-hidden="true"></i></button>
        </div>
        <form method="post" action="settings.php">
          <input type="hidden" name="csrf_token" value="<?= $h($vm['csrf']) ?>">
          <input type="hidden" name="dismiss" value="1">
          <button type="submit" class="sh-btn sh-btn-quiet"><?= $t('5746|Skjul') ?></button>
        </form>
      </div>
    </section>
    <?php } ?>
  </div>

  <?php if ($groups && !$vm['notice']) { ?>
  <p class="sh-foot"><a href="settingsMoved.php"><?= $t('6023|Hvor er de gamle menupunkter?') ?></a><?php if ($vm['onboarding'] && $vm['onboarding']['state'] === 'completed' && $vm['onboarding']['steps']) { ?><a href="../index/onboarding.php?step=done" onclick="if (window.parent && window.parent.saldiOnboardingOpen) { window.parent.saldiOnboardingOpen('done'); return false; }"><?= $t('6748|Kom godt i gang') ?></a><?php } ?><button type="button" data-keys><?= $t('6024|Tastaturgenveje') ?></button></p>
  <?php } ?>

  <div class="sh-backdrop" id="sh-backdrop" hidden></div>
  <div class="sh-dialog" id="sh-dialog" role="dialog" aria-modal="true" aria-labelledby="sh-dialog-title" hidden>
    <h3 id="sh-dialog-title"><?= $t('6024|Tastaturgenveje') ?></h3>
    <div class="sh-keys">
      <div><?= $t('5663|Søg i indstillinger…') ?><kbd>/</kbd></div>
      <div><?= $t('3|Gem') ?><kbd>Ctrl S</kbd></div>
      <div><?= $t('2172|Luk') ?><kbd>Esc</kbd></div>
      <div><?= $t('5647|Tilbage') ?><kbd>Alt ←</kbd></div>
    </div>
    <div class="sh-dialog-btns"><button type="button" class="sh-btn sh-btn-primary" id="sh-dialog-close"><?= $t('2172|Luk') ?></button></div>
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
			head.className = 'sh-none';
			head.textContent = txt.none;
			res.appendChild(head);
		} else {
			head.className = 'sh-cnt';
			head.textContent = txt.count.replace('%s', hits.length);
			res.appendChild(head);
			var lastGroup = null;
			grouped().forEach(function (row, i) {
				var hit = row.hit;
				if (row.group !== lastGroup) {
					var gh = document.createElement('div');
					gh.className = 'sh-grp';
					gh.textContent = row.group;
					res.appendChild(gh);
					lastGroup = row.group;
				}
				var a = document.createElement('a');
				a.className = 'sh-it' + (i === sel ? ' sh-sel' : '');
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
