<?php
// ---- includes/settings/listView.php --- ver 5.1.0 --- 2026.11.02 ---
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
// 20261002 Sawaneh Settings redesign phase 4b batch 2 (hand-over 2 Oct, mock-ups 07/08): a section of 'kind' list.
//                  Rows inside one card per sub-section - badge, title and one line, computed status, one button - and a
//                  drawer per integration with its own form, actions and change history. Rendered by
//                  systemdata/settingsSection.php; behaviour in javascript/settingsList.js.
// 20261004 Sawaneh G10.1: group headings inside a drawer, a status without a dot ('plain').
// 20261004 Sawaneh G4.3: rows from the database (names shown as typed), an empty state and a page-level add button.
// 20261016 Sawaneh The left menu is the group's sections like every other section kind; the list's own blocks sit as a
//                  second level under the current one (Adam's test feedback: Employees, price lists, tills and cards looked moved).
// 20261101 CL/ASR New design (prototype D): the page's root carries the kit's scope class sk; the look is css/unified-components.css on the kit's tokens.
// 20261101 CL/ASR Settings hub "C - Fold ud": settings_menu_html(), the left menu of every settings page (the three section views and
//                 the frame of the old pages, systemdata/top.php): the page's hub group as its sub-groups, each a heading over its
//                 rows, the current row marked, a row's second page under it, no zebra (owner, 1 Nov 2026). The list's own blocks
//                 stay a second level under the current page. Tilbage (settings_back_button()) above the title.
// 20261101 CL/ASR Review: a page that is not on the hub lists only the hub groups the user can open something in (as the hub).
//                 Tilbage carries Alt+L, as on the other settings pages.
// 20261102 CL/ASR Tilbage goes to the hub, or the module page the gear came from; settings_back_button() adds Alt+L itself.
// 20261102 CL/ASR settings_menu_html() is also the menu of the standard settings layout (includes/settings/layout.php), which any settings page,
//                 sub-page or module page opened from Indstillinger uses. Its urls are rebased on a page outside systemdata/ (settings_menu_base()):
//                 admin/backup.php and rental/settings.php would have opened admin/settings.php and rental/settingsSection.php.
// 20261102 CL/ASR Brugere & ansatte (owner, 2 Nov 2026: one item, one list, one card): in the left menu a 'direct' sub-group's heading is the link
//                 to its one row (marked on the list and on a card), and the row's other pages (Roller, Audit-log, Ekstra felter) are the rows
//                 under it; the narrow-screen select lists the heading once with its pages under it (no optgroup that repeats the name).
//                 A list section may show one of its blocks only ('only_sub', no h2): Ekstra felter is the employee section's extra-fields
//                 block as a page of its own ('page_label', 'page_lead', 'hub_at'), the employee list of a user without usersRoles.php leaves
//                 it out. 'add_label' names the head's add button. Embedded ('embed', the Stamdata tab of the person card in usersRoles.php,
//                 in an iframe): only the open item's drawer, without page head, menu, breadcrumb or list; the page with no such item (a
//                 deleted employee) only tells the card it is gone. A list section with 'row_click' opens a row from anywhere on it.
//                 settingsList.js?v=7. Other list sections are unchanged.
// 20261102 CL/ASR Review: an empty 'page_lead' leaves the lead's first sentence out (Ekstra felter: the row's help says it).

include_once(__DIR__ . '/integrations.php');

if (!function_exists('settings_menu_html')):

/**
 * The left menu of a settings page (hub "C - Fold ud", owner 1 Nov 2026): the page's hub group as its sub-groups, each a
 * heading (icon + name) over its rows, the current row (or the row's second page) marked, no zebra. A row's second page
 * (Kontoindstillinger, Kasseoptælling, POS-menuer ...) sits indented under it. Narrow screens get the same menu as a select.
 * Used by the section views (settingsSection.php, rowsView.php, this file), the frame of the old pages (top.php) and the
 * standard layout of every other settings page (settings_layout_open(), includes/settings/layout.php), so they all show the same menu. A page that is not on the hub (an old page, a left-out entry) lists the hub's groups.
 *
 * @param array<string, mixed> $menu settings_hub_menu() (includes/settings/hub.php); texts plain, escaped here
 * @param array<string, mixed> $opts 'anchors' => [block id => plain label]: the page's own blocks under the current item
 *                                   (a list section, scroll-followed by settingsList.js through a[data-sub]);
 *                                   'dot' => true: the unsaved-changes dot (#st-dot) in the current item;
 *                                   'before' => html above the panel (the frame's search field);
 *                                   'label' => the page's name, for the select when no item is current;
 *                                   'base' => prefix for the menu's urls (relative to systemdata/, as the hub's); default
 *                                   settings_menu_base(): '' in systemdata/, '../systemdata/' on a page in another folder
 */
function settings_menu_html(array $menu, int $sprogId, array $opts = array()): string
{
	$anchors = isset($opts['anchors']) && is_array($opts['anchors']) ? $opts['anchors'] : array();
	$base = isset($opts['base']) ? (string) $opts['base'] : settings_menu_base();
	// The hub's urls are relative to systemdata/; '../dir/page.php' and absolute ones fit every page one folder down already.
	$url = function (string $u) use ($base): string {
		if ($base === '' || $u === '' || $u[0] === '/' || $u[0] === '#' || strpos($u, '../') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $u)) {
			return $u;
		}
		return $base . $u;
	};
	$flat = !$menu;
	if ($flat) {
		$title = settings_hub_text('122|Indstillinger', $sprogId);
		$rows = array();
		foreach (getSettingsHub() as $groupId => $group) {
			// Only the groups the hub shows this user: one row they may open in a page the company has.
			$canOpen = false;
			foreach ($group['subs'] as $hubSub) {
				foreach ($hubSub['rows'] as $hubRow) {
					$rowState = settings_hub_row_state($hubRow);
					if ($rowState['open']) {
						$canOpen = true;
						break 2;
					}
				}
			}
			if (!$canOpen) {
				continue;
			}
			$rows[] = array('id' => $groupId, 'label' => settings_hub_text($group['label'], $sprogId), 'hint' => settings_hub_text($group['description'], $sprogId), 'url' => 'settings.php?g=' . rawurlencode($groupId), 'current' => false, 'pages' => array());
		}
		$subs = array(array('id' => 'groups', 'label' => '', 'icon' => '', 'locked' => '', 'current' => false, 'rows' => $rows));
	} else {
		$title = (string) $menu['group']['label'];
		$subs = $menu['subs'];
	}
	$dotHtml = !empty($opts['dot']) ? '<i class="st-dot" id="st-dot" hidden aria-hidden="true"></i>' : '';
	// $deep: one step further in, under a level-2 page, or under a row whose second page follows (they must not look alike).
	$anchorList = function (bool $deep) use ($anchors): string {
		if (count($anchors) < 2) {
			return '';
		}
		$out = '<ul class="st-menu-anchors' . ($deep ? ' st-menu-deep' : '') . '">';
		$first = true;
		foreach ($anchors as $id => $label) {
			$out .= '<li><a class="st-menu-row st-menu-l2' . ($first ? ' on' : '') . '" href="#sub-' . st_h($id) . '" data-sub="' . st_h($id) . '"><span>' . st_h($label) . '</span></a></li>';
			$first = false;
		}
		return $out . '</ul>';
	};
	$link = function (string $href, string $label, string $hint, bool $on, string $cls) use ($dotHtml, $url): string {
		return '<a class="st-menu-row' . $cls . ($on ? ' on' : '') . '" href="' . st_h($url($href)) . '"' . ($hint !== '' ? ' title="' . st_h($hint) . '"' : '')
			. ($on ? ' aria-current="page"' : '') . '><span>' . st_h($label) . '</span>' . ($on ? $dotHtml : '') . '</a>';
	};

	$anchorOpts = '';
	foreach (count($anchors) > 1 ? $anchors : array() as $id => $label) {
		$anchorOpts .= '<option value="#sub-' . st_h($id) . '">&nbsp;&nbsp;&nbsp;&nbsp;' . st_h($label) . '</option>';
	}

	$panel = '';
	$options = '';
	$anyCurrent = false;
	foreach ($subs as $sub) {
		$subId = (string) $sub['id'];
		$head = '';
		if ((string) $sub['label'] !== '') {
			$head = '<p class="st-menu-subh" id="st-menu-' . st_h($subId) . '">' . ((string) $sub['icon'] !== '' ? '<i class="bx ' . st_h($sub['icon']) . '" aria-hidden="true"></i>' : '')
				. '<span>' . st_h($sub['label']) . '</span>' . ($sub['locked'] !== '' ? '<i class="bx bx-lock-alt st-menu-lock" aria-hidden="true"></i>' : '') . '</p>';
		}
		if ($sub['locked'] !== '') {
			// No licence for any of the sub-group's pages (Kasse without the POS licence): shown, not a link.
			$panel .= '<div class="st-menu-sub is-off">' . $head . '<p class="st-menu-off">' . st_h($sub['locked']) . '</p></div>';
			$options .= '<optgroup label="' . st_h($sub['label']) . '"><option value="" disabled>' . st_h($sub['locked']) . '</option></optgroup>';
			continue;
		}
		if (!empty($sub['direct']) && $sub['rows']) {
			// The heading is the link to the sub-group's one row (Brugere & ansatte), marked on its own page; the row's other pages are
			// the rows under it. A page named as the heading (the employee list another user gets as the row) is the heading, not a row.
			$row = $sub['rows'][0];
			$pages = $row['pages'];
			$headOn = !empty($row['current']) && (!$pages || !empty($pages[0]['current']));
			$items = '';
			$subOpts = '';
			foreach ($pages as $i => $page) {
				if ($i === 0) {
					continue;
				}
				$pageOn = !empty($page['current']);
				if ((string) $page['label'] === (string) $sub['label']) {
					$headOn = $headOn || $pageOn;
					continue;
				}
				$anyCurrent = $anyCurrent || $pageOn;
				$items .= '<li>' . $link((string) $page['url'], (string) $page['label'], '', $pageOn, '') . ($pageOn ? $anchorList(false) : '') . '</li>';
				$subOpts .= '<option value="' . st_h($url((string) $page['url'])) . '"' . ($pageOn ? ' selected' : '') . '>&nbsp;&nbsp;' . st_h($page['label']) . '</option>' . ($pageOn ? $anchorOpts : '');
			}
			$anyCurrent = $anyCurrent || $headOn;
			$headLink = '<a class="st-menu-subh st-menu-head' . ($headOn ? ' on' : '') . '" id="st-menu-' . st_h($subId) . '" href="' . st_h($url((string) $row['url'])) . '"'
				. ((string) $row['hint'] !== '' ? ' title="' . st_h($row['hint']) . '"' : '') . ($headOn ? ' aria-current="page"' : '') . '>'
				. ((string) $sub['icon'] !== '' ? '<i class="bx ' . st_h($sub['icon']) . '" aria-hidden="true"></i>' : '')
				. '<span>' . st_h($sub['label']) . '</span>' . ($headOn ? $dotHtml : '') . '</a>';
			$panel .= '<div class="st-menu-sub st-menu-direct' . (!empty($sub['current']) ? ' is-current' : '') . '">' . $headLink . ($headOn ? $anchorList(false) : '')
				. ($items !== '' ? '<ul class="st-menu-rows" aria-labelledby="st-menu-' . st_h($subId) . '">' . $items . '</ul>' : '') . '</div>';
			$options .= '<option value="' . st_h($url((string) $row['url'])) . '"' . ($headOn ? ' selected' : '') . '>' . st_h($sub['label']) . '</option>' . ($headOn ? $anchorOpts : '') . $subOpts;
			continue;
		}
		$items = '';
		$subOpts = '';
		foreach ($sub['rows'] as $row) {
			$pages = $row['pages'];
			$rowOn = !empty($row['current']) && (!$pages || !empty($pages[0]['current']));
			$sub2 = '';
			$pageOpts = '';
			foreach ($pages as $i => $page) {
				if ($i === 0) {
					continue;
				}
				$pageOn = !empty($page['current']);
				$anyCurrent = $anyCurrent || $pageOn;
				$sub2 .= '<li>' . $link((string) $page['url'], (string) $page['label'], '', $pageOn, ' st-menu-l2') . ($pageOn ? $anchorList(true) : '') . '</li>';
				$pageOpts .= '<option value="' . st_h($url((string) $page['url'])) . '"' . ($pageOn ? ' selected' : '') . '>&nbsp;&nbsp;' . st_h($page['label']) . '</option>' . ($pageOn ? $anchorOpts : '');
			}
			$anyCurrent = $anyCurrent || $rowOn;
			$items .= '<li' . (!empty($row['current']) ? ' class="is-current"' : '') . '>' . $link((string) $row['url'], (string) $row['label'], (string) $row['hint'], $rowOn, '')
				. ($rowOn ? $anchorList($sub2 !== '') : '') . ($sub2 !== '' ? '<ul class="st-menu-pages">' . $sub2 . '</ul>' : '') . '</li>';
			$subOpts .= '<option value="' . st_h($url((string) $row['url'])) . '"' . ($rowOn ? ' selected' : '') . '>' . st_h($row['label']) . '</option>' . ($rowOn ? $anchorOpts : '') . $pageOpts;
		}
		$panel .= '<div class="st-menu-sub' . (!empty($sub['current']) ? ' is-current' : '') . '">' . $head
			. '<ul class="st-menu-rows' . ($head === '' ? ' st-menu-flat' : '') . '"' . ($head !== '' ? ' aria-labelledby="st-menu-' . st_h($subId) . '"' : '') . '>' . $items . '</ul></div>';
		$options .= (string) $sub['label'] !== '' ? '<optgroup label="' . st_h($sub['label']) . '">' . $subOpts . '</optgroup>' : $subOpts;
	}
	$placeholder = $anyCurrent ? '' : '<option value="" selected>' . st_h(isset($opts['label']) ? (string) $opts['label'] : $title) . '</option>';
	return '<nav class="st-tabs st-menu" aria-label="' . st_h($title) . '">'
		. '<select class="st-tabs-select" aria-label="' . st_h($title) . '" onchange="if (this.value) { window.location = this.value; }">' . $placeholder . $options . '</select>'
		. (isset($opts['before']) ? (string) $opts['before'] : '')
		. '<div class="st-menu-panel"><p class="st-menu-h">' . st_h($title) . '</p>' . $panel
		. ($flat ? '' : '<a class="st-menu-all" href="' . st_h($url('settings.php')) . '"><i class="bx bx-grid-alt" aria-hidden="true"></i><span>' . st_h(settings_hub_text('5666|Alle indstillinger', $sprogId)) . '</span></a>')
		. '</div></nav>';
}

/**
 * The prefix that makes the hub's urls (relative to systemdata/) work on the running page: '' in systemdata/,
 * '../systemdata/' on a settings page in another folder (admin/backup.php, rental/settings.php).
 */
function settings_menu_base(): string
{
	$script = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
	return ($script === '' || basename(dirname($script)) === 'systemdata') ? '' : '../systemdata/';
}

endif;

/**
 * @param array<string, mixed> $c everything the section view computed (see settings_section_view)
 */
function settings_list_render(array $c): void
{
	$section = $c['section'];
	$defs = $c['defs'];
	$state = $c['state'];
	$canWrite = $c['canWrite'];
	$sectionId = $c['sectionId'];
	$sprogId = $c['sprogId'];
	$charset = $c['charset'];
	$openItem = $c['item'];
	if (($state['errors'] || $state['conflict']) && isset($_POST['item']) && isset($section['items'][$_POST['item']])) {
		$openItem = (string) $_POST['item'];
	}
	// Embedded (the Stamdata tab of the person card in usersRoles.php): only the open item's drawer. One block only ('only_sub'):
	// Ekstra felter as a page of its own, or the employee list without it.
	$embed = !empty($section['embed']);
	$onlySub = isset($section['only_sub']) ? (string) $section['only_sub'] : '';
	$pageLabel = isset($section['page_label']) ? $section['page_label'] : $section['label'];
	$pageLead = isset($section['page_lead']) ? $section['page_lead'] : $section['lead'];
	$subs = array();
	foreach ($section['subsections'] as $sub => $label) {
		if (isset($section['sub_module'][$sub]) && !settings_has_module($section['sub_module'][$sub])) {
			continue;
		}
		if ($onlySub !== '' && $sub !== $onlySub) {
			continue;
		}
		$subs[$sub] = $label;
	}
	$byItem = array();
	$fieldItem = array();
	foreach ($defs as $key => $def) {
		if (isset($def['item'])) {
			$byItem[$def['item']][$key] = $def;
			$fieldItem[$key] = $def['item'];
		}
	}
	$setAt = SettingsService::lastChanged($sectionId);
	$status = array();
	foreach ($section['items'] as $id => $it) {
		$status[$id] = settings_integration_status($id, $it, $setAt);
	}
	$history = SettingsService::history($sectionId, 60);
	$anchors = array();
	foreach ($subs as $sub => $label) {
		$anchors[$sub] = st_txt($label);
	}
	$config = $c['config'] + array(
		'openItem' => $openItem, 'fieldItem' => $fieldItem, 'embed' => $embed, 'saved' => $embed && isset($_GET['saved']),
		'discardTitle' => st_txt(6144), 'discardBody' => st_txt(6145), 'discardVerb' => st_txt(6146), 'wait' => st_txt(6160), 'close' => st_txt(6128),
		'doneFlatpay' => st_txt(6134), 'doneVibrant' => st_txt(6136),
	);
	foreach (array('discardTitle', 'discardBody', 'discardVerb', 'wait', 'close', 'doneFlatpay', 'doneVibrant') as $k) {
		$config[$k] = mb_convert_encoding($config[$k], 'UTF-8', $charset);
	}
	$dots = array('ok' => 'st-dot-ok', 'err' => 'st-dot-err', 'off' => '', 'soon' => '', 'plain' => '');
	if ($embed && ($openItem === '' || !isset($section['items'][$openItem]) || !isset($subs[$section['items'][$openItem]['sub']]))) {
		// No such item (the employee was deleted, or the address names none): the card closes to the list, which shows the message.
		foreach ($c['flash'] as $f) {
			if ($f[0] === 'ok' || $f[0] === 'err') {
				$_SESSION['people_flash'] = array($f[0], (string) $f[1]);
				break;
			}
		}
		?>
<div class="sk st-page st-embed" hidden></div>
<script>if (window.parent !== window) { window.parent.postMessage({ type: 'saldi:stamdata', event: 'gone' }, window.location.origin); }</script>
		<?php
		return;
	}
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<?php if (!$embed) { ?>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt($pageLabel), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb((string) $section['group'], st_txt($pageLabel), $sprogId, isset($section['hub_at']) ? (string) $section['hub_at'] : ''), $charset, isset($section['return_to']) ? (string) $section['return_to'] : '', isset($section['return_label']) ? (string) $section['return_label'] : '') ?>
<?php } ?>
<div class="sk st-page st-page-list<?= $embed ? ' st-embed' : '' ?>" style="<?= st_h(st_accent_style((string) $c['accent'], (string) $c['accentTxt'])) ?>">
	<?php if (!$embed) { ?>
  <a class="st-skip" href="#st-list"><?= st_t(5751) ?></a>
  <section class="st-phead">
    <div>
      <?= settings_back_button($section['return_to'] !== '' ? '..' . $section['return_to'] : '') ?>
      <h1><?= st_t($pageLabel) ?><?= st_section_help($section) ?></h1>
      <p class="st-lead"><?= (string) $pageLead !== '' ? st_t($pageLead) . '. ' : '' ?><?= st_t(5706) ?> <?= st_h(st_company()) ?>.</p>
    </div>
	<?php if (!empty($section['add_action']) && $canWrite && empty($section['no_add'])) { ?>
    <form method="post" action="<?= st_h($c['selfUrl']) ?>">
      <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
      <input type="hidden" name="action" value="run">
      <input type="hidden" name="key" value="<?= st_h($section['add_action']) ?>">
      <button type="submit" class="st-btn st-btn-primary"><i class='bx bx-plus' aria-hidden="true"></i><?= st_t(isset($section['add_label']) ? $section['add_label'] : $defs[$section['add_action']]['label']) ?></button>
    </form>
	<?php } ?>
  </section>
	<?php } ?>

	<?php if (($c['movedText'] !== '' && !$embed) || $c['flash']) { ?>
  <div class="st-notes">
		<?php if ($c['movedText'] !== '' && !$embed) { ?>
    <div class="st-toast" role="status"><i class="st-dotw st-dot-acc" aria-hidden="true"></i><span><?= st_h($c['movedText']) ?> <a href="settingsMoved.php"><?= st_t(5722) ?></a></span><button type="button" class="st-toast-close" data-dismiss aria-label="<?= st_t(6128) ?>">×</button></div>
		<?php } ?>
		<?php foreach ($c['flash'] as $f) { ?>
		<?php if ($f[0] === 'key') { ?>
    <div class="st-flash st-flash-ok st-flash-key" role="status"><i class='bx bx-key' aria-hidden="true"></i><span><b><?= st_t(6129) ?>:</b> <code><?= st_h($f[1]) ?></code><br><?= st_t(6130) ?></span></div>
		<?php continue; } ?>
    <div class="st-flash st-flash-<?= $f[0] ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><?php if ($f[0] === 'info') { ?><i class='bx bx-lock-alt' aria-hidden="true"></i><?php } ?><span><?= st_h($f[1]) ?></span></div>
		<?php } ?>
  </div>
	<?php } ?>

	<?php if (!$embed) { ?>
  <div class="st-layout st-layout-list">
    <?= settings_menu_html($c['menu'], $sprogId, array('anchors' => $anchors, 'label' => st_txt($pageLabel))) ?>

    <div class="st-list" id="st-list"<?= !empty($section['row_click']) ? ' data-rowclick' : '' ?>>
		<?php foreach ($subs as $sub => $label) { ?>
      <section class="st-sect" id="sub-<?= st_h($sub) ?>">
			<?php if ($onlySub === '') { ?>
        <h2><?= st_t($label) ?></h2>
			<?php } ?>
        <div class="st-card">
			<?php $shownInSub = 0; foreach ($section['items'] as $id => $it) {
				if ($it['sub'] !== $sub) {
					continue;
				}
				$shownInSub++;
				$st = $status[$id];
				?>
          <div class="st-irow st-irow-<?= st_h($st['kind']) ?>" data-item="<?= st_h($id) ?>">
            <span class="st-ibadge" aria-hidden="true"><?= st_h($it['abbr']) ?></span>
            <div class="st-tx"><b><?= !empty($it['literal']) ? st_h($it['label']) : st_label($it['label']) ?></b><span><?= !empty($it['literal']) ? st_h($it['desc']) : st_t($it['desc']) ?></span></div>
            <span class="st-imeta st-imeta-<?= st_h($st['kind']) ?>"><?php if ($st['kind'] !== 'soon' && $st['kind'] !== 'plain') { ?><i class="st-dotw <?= $dots[$st['kind']] ?>" aria-hidden="true"></i><?php } ?><?= st_h($st['text']) ?></span>
				<?php if ($st['kind'] === 'soon') { ?>
            <span class="st-ibtn-space"></span>
				<?php } else { ?>
            <button type="button" class="st-btn<?= $st['kind'] === 'ok' ? ' st-btn-quiet' : '' ?>" data-open="<?= st_h($id) ?>" aria-haspopup="dialog"><?= $canWrite ? st_t($st['button']) : st_t(6143) ?></button>
				<?php } ?>
          </div>
			<?php } ?>
			<?php if (!$shownInSub && isset($section['empty_text'])) { ?>
          <p class="st-h-empty st-list-empty"><?= st_t($section['empty_text']) ?></p>
			<?php } ?>
        </div>
      </section>
		<?php } ?>
    </div>
  </div>
	<?php } ?>

	<?php foreach ($section['items'] as $id => $it) {
		if (!empty($it['soon']) || !isset($subs[$it['sub']]) || ($embed && $id !== $openItem)) {
			continue;
		}
		$fields = array();
		$actions = array();
		foreach (isset($byItem[$id]) ? $byItem[$id] : array() as $key => $def) {
			if ($embed && !empty($def['embed_hide'])) {
				continue; // the card's Login og rolle tab shows it
			}
			if ($def['type'] === 'action') {
				$actions[$key] = $def;
			} else {
				$fields[$key] = $def;
			}
		}
		$errors = array();
		foreach ($state['errors'] as $key => $textId) {
			if (isset($fields[$key])) {
				$errors[$key] = $textId;
			}
		}
		$rows = array();
		foreach ($history as $row) {
			if (isset($fields[(string) $row['setting_key']])) {
				$rows[] = $row;
			}
		}
		$rows = array_slice($rows, 0, 10);
		?>
  <aside class="st-drawer" id="dw-<?= st_h($id) ?>"<?= $embed ? '' : ' role="dialog" aria-modal="true"' ?> aria-labelledby="dwt-<?= st_h($id) ?>" data-item="<?= st_h($id) ?>"<?= $embed ? '' : ' hidden' ?>>
    <form class="st-dform" method="post" action="<?= st_h($c['selfUrl']) ?>" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="version" value="<?= (int) $c['version'] ?>">
      <input type="hidden" name="item" value="<?= st_h($id) ?>">
      <header class="st-dhead">
        <div>
          <h2 id="dwt-<?= st_h($id) ?>"><?= !empty($it['literal']) ? st_h($it['label']) : st_label($it['label']) ?></h2>
          <p><?= st_t(6125) ?>.</p>
        </div>
        <button type="button" class="st-iconbtn" data-close aria-label="<?= st_t(6128) ?>"><i class='bx bx-x' aria-hidden="true"></i></button>
      </header>
      <div class="st-dbody">
			<?php if ($errors) { ?>
        <ul class="st-errorlist" aria-label="<?= st_t(5731) ?>">
				<?php foreach ($errors as $key => $textId) { ?>
          <li><a href="#<?= st_h($key) ?>"><?= st_t($defs[$key]['label']) ?>: <?= st_t($textId) ?></a></li>
				<?php } ?>
        </ul>
			<?php } ?>
			<?php if ($fields) { ?>
        <div class="st-card st-card-flat">
				<?php $lastGroup = null; foreach ($fields as $key => $def) {
					$groupHeading = '';
					if (isset($def['group_label'])) {
						$g = sprintf(st_txt($def['group_label'][0]), $def['group_label'][1]);
						if ($g !== $lastGroup) {
							$groupHeading = $g;
							$lastGroup = $g;
						}
					}
					st_render_field($def, array(
						'group_heading' => $groupHeading,
						'in_group' => isset($def['group_label']),
						'value'    => isset($c['values'][$key]) ? $c['values'][$key] : '',
						'original' => isset($c['originals'][$key]) ? $c['originals'][$key] : '',
						'error'    => isset($state['errors'][$key]) ? $state['errors'][$key] : null,
						'readonly' => !$canWrite,
						'locked'   => st_locked($def, isset($c['values'][$key]) ? (string) $c['values'][$key] : null),
						'visible'  => st_visible($def, $c['values']),
						'mine'     => ($state['conflict'] && isset($state['conflict']['mine'][$key])),
						'set_at'   => isset($setAt[$key]) ? $setAt[$key] : '',
					));
				} ?>
        </div>
			<?php } ?>
			<?php if ($actions && $canWrite) { ?>
        <h3 class="st-dh3"><?= st_t(3285) ?></h3>
        <div class="st-card st-card-flat">
				<?php foreach ($actions as $def) {
					st_render_action($def, $canWrite, st_visible($def, $c['values']));
				} ?>
        </div>
			<?php } ?>
        <details class="st-dhist">
          <summary><?= st_t(5710) ?></summary>
          <div class="st-card st-card-flat">
				<?php foreach ($rows as $row) {
					$key = (string) $row['setting_key'];
					$def = $defs[$key];
					$secret = ($def['type'] === 'secret' || st_field_access($def) === 'none');
					?>
            <div class="st-h">
              <a class="st-h-field" href="#<?= st_h($key) ?>"><?= st_t($def['label']) ?></a>
              <span class="st-h-ch"><?php if ($secret) { ?><?= st_t(5713) ?><?php } else { ?><s><?= st_h(st_display_value($def, (string) $row['old_value'])) ?></s> → <?= st_h(st_display_value($def, (string) $row['new_value'])) ?><?php } ?></span>
              <span class="st-h-m"><span><?= st_h($row['brugernavn']) ?> · <?= st_h(st_local_time((string) $row['tidspunkt'], 'j/n H:i')) ?></span><?php if (!$secret && $canWrite && st_field_access($def) === 'write' && !st_locked($def, null, st_form_value($def, (string) $row['old_value']))) { ?><button type="button" class="st-tl" data-restore="<?= (int) $row['id'] ?>" data-value="<?= st_h(st_display_value($def, (string) $row['old_value'])) ?>"><?= st_t(5711) ?></button><?php } ?></span>
            </div>
				<?php } ?>
				<?php if (!$rows) { ?>
            <p class="st-h-empty"><?= st_t(5712) ?></p>
				<?php } ?>
          </div>
        </details>
      </div>
      <footer class="st-dfoot">
        <span class="st-status" data-status><i class="st-dotw st-dot-warn" aria-hidden="true"></i><span role="status"></span></span>
        <span class="st-grow"></span>
        <button type="button" class="st-btn st-btn-quiet" data-close><?= st_t(5) ?></button>
			<?php if ($canWrite && $fields) { ?>
        <button type="submit" class="st-btn st-btn-primary" data-save disabled><?= st_t(6045) ?></button>
			<?php } ?>
      </footer>
    </form>
  </aside>
	<?php } ?>
  <div class="st-scrim" id="st-scrim" hidden></div>

  <div class="st-backdrop" id="st-backdrop" hidden></div>
  <div class="st-dialog" id="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-dialog-title" hidden>
    <h3 id="st-dialog-title"></h3>
    <p id="st-dialog-body"></p>
    <form method="post" action="<?= st_h($c['selfUrl']) ?>" id="st-dialog-form">
      <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
      <input type="hidden" name="action" value="">
      <input type="hidden" name="key" value="">
      <input type="hidden" name="entry" value="">
      <label class="st-dialog-pw" id="st-dialog-pw" hidden><span><?= st_t(6546) ?></span><input class="st-input" type="password" name="password" autocomplete="current-password" aria-label="<?= st_t(6544) ?>"></label>
      <input type="hidden" name="item" value="">
      <div class="st-dialog-btns">
        <button type="button" class="st-btn st-btn-quiet" id="st-dialog-cancel"><?= st_t(5) ?></button>
        <button type="submit" class="st-btn st-btn-primary" id="st-dialog-ok"></button>
      </div>
    </form>
  </div>
  <div class="st-snack" id="st-snack" role="status" hidden></div>
</div>
<script>window.SALDI_SETTINGS = <?= json_encode($config) ?>;</script>
<script src="../javascript/settingsList.js?v=7"></script>
	<?php
}
