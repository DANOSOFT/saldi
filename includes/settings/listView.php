<?php
// ---- includes/settings/listView.php --- lap 5.0.0 --- 2026.10.02 ---
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
// 20261002 Sawaneh Settings redesign phase 4b batch 2 (hand-over 2 Oct, mock-ups 07/08): a section of 'kind' list.
//                  Rows inside one card per sub-section - badge, title and one line, computed status, one button - and a
//                  drawer per integration with its own form, actions and change history. Rendered by
//                  systemdata/settingsSection.php; behaviour in javascript/settingsList.js.
// 20261004 Sawaneh G10.1: group headings inside a drawer, a status without a dot ('plain').
// 20261004 Sawaneh G4.3: rows from the database (names shown as typed), an empty state and a page-level add button.
// 20261016 Sawaneh The left menu is the group's sections like every other section kind; the list's own blocks sit as a
//                  second level under the current one (Adam's test feedback: Employees, price lists, tills and cards looked moved).

include_once(__DIR__ . '/integrations.php');

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
	$subs = array();
	foreach ($section['subsections'] as $sub => $label) {
		if (isset($section['sub_module'][$sub]) && !settings_has_module($section['sub_module'][$sub])) {
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
	$otherTabs = $c['tabs'];
	$config = $c['config'] + array(
		'openItem' => $openItem, 'fieldItem' => $fieldItem,
		'discardTitle' => st_txt(6144), 'discardBody' => st_txt(6145), 'discardVerb' => st_txt(6146), 'wait' => st_txt(6160), 'close' => st_txt(6128),
		'doneFlatpay' => st_txt(6134), 'doneVibrant' => st_txt(6136),
	);
	foreach (array('discardTitle', 'discardBody', 'discardVerb', 'wait', 'close', 'doneFlatpay', 'doneVibrant') as $k) {
		$config[$k] = mb_convert_encoding($config[$k], 'UTF-8', $charset);
	}
	$dots = array('ok' => 'st-dot-ok', 'err' => 'st-dot-err', 'off' => '', 'soon' => '', 'plain' => '');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt($section['label']), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb((string) $section['group'], st_txt($section['label']), $sprogId), $charset, isset($section['return_to']) ? (string) $section['return_to'] : '', isset($section['return_label']) ? (string) $section['return_label'] : '') ?>
<div class="st-page st-page-list" style="<?= st_h(st_accent_style((string) $c['accent'], (string) $c['accentTxt'])) ?>">
  <a class="st-skip" href="#st-list"><?= st_t(5751) ?></a>
  <section class="st-phead">
    <div>
      <h1><?= st_t($section['label']) ?><?= st_section_help($section) ?></h1>
      <p class="st-lead"><?= st_t($section['lead']) ?>. <?= st_t(5706) ?> <?= st_h(st_company()) ?>.</p>
    </div>
	<?php if (!empty($section['add_action']) && $canWrite) { ?>
    <form method="post" action="<?= st_h($c['selfUrl']) ?>">
      <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
      <input type="hidden" name="action" value="run">
      <input type="hidden" name="key" value="<?= st_h($section['add_action']) ?>">
      <button type="submit" class="st-btn st-btn-primary"><i class='bx bx-plus' aria-hidden="true"></i><?= st_t($defs[$section['add_action']]['label']) ?></button>
    </form>
	<?php } ?>
  </section>

	<?php if ($c['movedText'] !== '' || $c['flash']) { ?>
  <div class="st-notes">
		<?php if ($c['movedText'] !== '') { ?>
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

  <div class="st-layout st-layout-list">
    <nav class="st-tabs" aria-label="<?= st_t($section['label']) ?>">
      <select class="st-tabs-select" aria-label="<?= st_t($section['label']) ?>" onchange="if (this.value) { window.location = this.value; }">
			<?php foreach ($otherTabs as $tab) { ?>
        <option value="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' selected' : '' ?>><?= st_h($tab['label']) ?></option>
				<?php if ($tab['id'] === $sectionId) { foreach ($subs as $sub => $label) { ?>
        <option value="#sub-<?= st_h($sub) ?>">&nbsp;&nbsp;<?= st_t($label) ?></option>
				<?php } } ?>
			<?php } ?>
      </select>
      <ul>
			<?php foreach ($otherTabs as $tab) { ?>
        <li><a href="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' class="on" aria-current="page"' : '' ?>><span><?= st_h($tab['label']) ?></span><?= $tab['id'] === $sectionId ? '<i class="bx bx-chevron-right" aria-hidden="true"></i>' : '' ?></a></li>
				<?php if ($tab['id'] === $sectionId && count($subs) > 1) { ?>
        <li><ul class="st-tabs-sub">
					<?php $first = true; foreach ($subs as $sub => $label) { ?>
          <li><a href="#sub-<?= st_h($sub) ?>" data-sub="<?= st_h($sub) ?>"<?= $first ? ' class="on"' : '' ?>><span><?= st_t($label) ?></span></a></li>
					<?php $first = false; } ?>
        </ul></li>
				<?php } ?>
			<?php } ?>
      </ul>
    </nav>

    <div class="st-list" id="st-list">
		<?php foreach ($subs as $sub => $label) { ?>
      <section class="st-sect" id="sub-<?= st_h($sub) ?>">
        <h2><?= st_t($label) ?></h2>
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

	<?php foreach ($section['items'] as $id => $it) {
		if (!empty($it['soon']) || !isset($subs[$it['sub']])) {
			continue;
		}
		$fields = array();
		$actions = array();
		foreach (isset($byItem[$id]) ? $byItem[$id] : array() as $key => $def) {
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
  <aside class="st-drawer" id="dw-<?= st_h($id) ?>" role="dialog" aria-modal="true" aria-labelledby="dwt-<?= st_h($id) ?>" data-item="<?= st_h($id) ?>" hidden>
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
						'locked'   => st_locked($def),
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
              <span class="st-h-m"><span><?= st_h($row['brugernavn']) ?> · <?= st_h(st_local_time((string) $row['tidspunkt'], 'j/n H:i')) ?></span><?php if (!$secret && $canWrite && st_field_access($def) === 'write' && !st_locked($def)) { ?><button type="button" class="st-tl" data-restore="<?= (int) $row['id'] ?>" data-value="<?= st_h(st_display_value($def, (string) $row['old_value'])) ?>"><?= st_t(5711) ?></button><?php } ?></span>
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
<script src="../javascript/settingsList.js?v=3"></script>
	<?php
}
