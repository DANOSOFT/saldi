<?php
// ---- includes/settings/rowsView.php --- lap 5.0.0 --- 2026.10.05 ---
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
// 20261006 Sawaneh Row actions may show per-row confirm text ('confirm_args') and ask for one value ('input', posted as arg).
// 20261006 Sawaneh §8.13: an empty table with a standard set offers "Opret dansk standardsæt" and "Tilføj selv".
// 20261005 Sawaneh 4d: read-only columns and locked rows, row actions (with password for danger-zone ones), a parent
//                  picker above a filtered table, a create card below a table and the confirmation of posted amounts.
// 20261005 Sawaneh Settings redesign phase 4c (spec §8.2, mock-up 04): a section of 'kind' rows. One card per table:
//                  column headers, every cell an input, an always-available "Tilføj" row, trash (with the usage count)
//                  and the inaktiv eye; ordinary fields of the section above the tables; year selector for per-year
//                  tables. Behaviour in javascript/settingsRows.js.
// 20261008 Sawaneh A table with 'hide_empty' and no rows is not shown (old discounts in G3.5).
// 20261008 Sawaneh A table's 'confirm_skip' adds a second confirm button (master's SST-769 Gem kurs uden bogføring).

include_once(__DIR__ . '/rows.php');

/**
 * @param array<string, mixed> $c everything the section view computed, plus 'tables', 'year', 'years', 'rowsPosted'
 */
function settings_rows_render(array $c): void
{
	$section = $c['section'];
	$defs = $c['defs'];
	$state = $c['state'];
	$canWrite = $c['canWrite'];
	$sectionId = $c['sectionId'];
	$sprogId = $c['sprogId'];
	$charset = $c['charset'];
	$tables = $c['tables'];
	$year = $c['year'];
	$subs = array();
	foreach ($section['subsections'] as $sub => $label) {
		$subs[$sub] = $label;
	}
	$fieldsBySub = array();
	foreach ($defs as $key => $def) {
		if (!in_array($def['type'], array('action', 'mini'), true)) {
			$fieldsBySub[$def['sub']][$key] = $def;
		}
	}
	$tablesBySub = array();
	foreach ($tables as $tableId => $t) {
		$tablesBySub[$t['sub']][$tableId] = $t;
	}
	$history = SettingsService::history($sectionId, 20);
	$otherTabs = $c['tabs'];
	$posted = isset($c['rowsPosted']) ? $c['rowsPosted'] : array();
	$errors = $state['errors'];
	$config = $c['config'] + array(
		'deleteTitle' => st_txt(6416), 'deleteBody' => st_txt(6417), 'deleteVerb' => st_txt(1099), 'cannotTitle' => st_txt(6414), 'usedBody' => st_txt(6415),
		'inactiveVerb' => st_txt(6433), 'close' => st_txt(2172), 'newTag' => st_txt(6429), 'seeded' => st_txt(6826),
	);
	foreach ($config as $k => $v) {
		$config[$k] = is_string($v) ? mb_convert_encoding($v, 'UTF-8', $charset) : $v;
	}
	$fiscal = false;
	foreach ($tables as $t) {
		if ($t['fiscal']) {
			$fiscal = true;
		}
	}
	$yearUrl = function (int $y) use ($c): string {
		return preg_replace('/([?&])year=[0-9]*/', '$1', $c['selfUrl']) . '&year=' . $y;
	};
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt($section['label']), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb((string) $section['group'], st_txt($section['label']), $sprogId), $charset, isset($section['return_to']) ? (string) $section['return_to'] : '', isset($section['return_label']) ? (string) $section['return_label'] : '') ?>
<div class="st-page st-page-rows" style="<?= st_h(st_accent_style((string) $c['accent'], (string) $c['accentTxt'])) ?>">
  <a class="st-skip" href="#st-form"><?= st_t(5751) ?></a>
  <section class="st-phead">
    <div>
      <h1><?= st_t($section['label']) ?><?= st_section_help($section) ?></h1>
      <p class="st-lead"><?php if (!empty($section['lead'])) { ?><?= st_t($section['lead']) ?> <?php } ?><?= st_t(5706) ?> <?= st_h(st_company()) ?>.</p>
    </div>
	<?php if ($fiscal) { ?>
    <div class="st-scope">
      <label for="st-year"><?= st_t(6439) ?></label>
      <select class="st-input st-input-short" id="st-year" onchange="if (this.value) { window.location = this.value; }">
			<?php foreach ($c['years'] as $y) { ?>
        <option value="<?= st_h($yearUrl($y)) ?>"<?= $y === $year ? ' selected' : '' ?>><?= $y ?></option>
			<?php } ?>
      </select>
    </div>
	<?php } ?>
  </section>

	<?php if ($c['movedText'] !== '' || $c['flash']) { ?>
  <div class="st-notes">
		<?php if ($c['movedText'] !== '') { ?>
    <div class="st-toast" role="status"><i class="st-dotw st-dot-acc" aria-hidden="true"></i><span><?= st_h($c['movedText']) ?> <a href="settingsMoved.php"><?= st_t(5722) ?></a></span><button type="button" class="st-toast-close" data-dismiss aria-label="<?= st_t(2172) ?>">×</button></div>
		<?php } ?>
		<?php foreach ($c['flash'] as $f) { ?>
    <div class="st-flash st-flash-<?= $f[0] ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><i class="st-dotw st-dot-<?= $f[0] === 'err' ? 'err' : 'ok' ?>" aria-hidden="true"></i><span><?= st_h($f[1]) ?></span><?php if (isset($f[2])) { ?> <form method="post" action="<?= st_h($c['selfUrl']) ?>" class="st-inline"><input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>"><input type="hidden" name="action" value="row_inactive"><input type="hidden" name="table" value="<?= st_h($f[2]['table']) ?>"><input type="hidden" name="id" value="<?= (int) $f[2]['id'] ?>"><input type="hidden" name="value" value="<?= $f[2]['value'] ?>"><button type="submit" class="st-tl"><?= st_t(6436) ?></button></form><?php } ?></div>
		<?php } ?>
  </div>
	<?php } ?>

  <div class="st-layout">
    <nav class="st-tabs" aria-label="<?= st_h(html_entity_decode(findtekst($c['group']['label'], $sprogId), ENT_QUOTES | ENT_HTML5, $charset)) ?>">
      <select class="st-tabs-select" onchange="if (this.value) { window.location = this.value; }">
			<?php foreach ($otherTabs as $tab) { ?>
        <option value="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' selected' : '' ?>><?= st_h($tab['label']) ?></option>
			<?php } ?>
      </select>
      <ul>
			<?php foreach ($otherTabs as $tab) { ?>
        <li><a href="<?= st_h($tab['url']) ?>"<?= $tab['id'] === $sectionId ? ' class="on" aria-current="page"' : '' ?>><span><?= st_h($tab['label']) ?></span><?= $tab['id'] === $sectionId ? '<i class="st-dot" id="st-dot" hidden aria-hidden="true"></i>' : '' ?></a></li>
			<?php } ?>
      </ul>
    </nav>

    <form class="st-form st-rows-form" id="st-form" method="post" action="<?= st_h($c['selfUrl']) ?>" autocomplete="off" novalidate>
      <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="version" value="<?= (int) $c['version'] ?>">
			<?php if ($errors) { ?>
      <div class="st-errors" role="alert">
        <b><?= st_t(5731) ?></b>
        <ul>
				<?php foreach ($errors as $key => $textId) {
					$target = strpos($key, '/') !== false ? 'c-' . str_replace(array('/', '.'), '-', $key) : $key;
					$label = $key;
					if (strpos($key, '/') !== false) {
						list($tid, $rid, $col) = explode('/', $key, 3);
						$label = (isset($tables[$tid]) ? st_txt($tables[$tid]['label']) . ' · ' . st_txt($tables[$tid]['columns'][$col]['label']) : $key);
					} elseif (isset($defs[$key])) {
						$label = st_txt($defs[$key]['label']);
					}
					?>
          <li><a href="#<?= st_h($target) ?>"><?= st_h($label) ?>: <?= st_t($textId) ?></a></li>
				<?php } ?>
        </ul>
      </div>
			<?php } ?>
			<?php if (!empty($c['confirm'])) { ?>
      <div class="st-confirm" role="alert">
        <b><?= st_t(6597) ?></b>
        <ul>
				<?php foreach ($c['confirm'] as $line) { ?>
          <li><?= st_h($line) ?></li>
				<?php } ?>
        </ul>
        <p><?= st_t(6600) ?></p>
        <button type="submit" class="st-btn st-btn-primary" name="confirmed" value="1"><?= st_t(6599) ?></button>
				<?php foreach ($tables as $t) {
					if (!empty($t['confirm_skip'])) { ?>
        <button type="submit" class="st-btn" name="confirmed" value="skip"><?= st_t($t['confirm_skip'][0]) ?></button>
        <p><?= st_t($t['confirm_skip'][1]) ?></p>
				<?php break; }
				} ?>
      </div>
			<?php } ?>
			<?php if ($state['conflict']) { ?>
      <div class="st-flash st-flash-err" role="alert"><i class="st-dotw st-dot-err" aria-hidden="true"></i><span><?= st_h(sprintf(st_txt(5749), $state['conflict']['by'], st_local_time((string) $state['conflict']['at'], 'H:i'))) ?></span></div>
			<?php } ?>

			<?php foreach ($subs as $sub => $label) { ?>
      <section class="st-sect" id="sub-<?= st_h($sub) ?>">
				<?php if (!empty($fieldsBySub[$sub])) { ?>
        <h2><?= st_t($label) ?></h2>
        <div class="st-card">
				<?php foreach ($fieldsBySub[$sub] as $key => $def) {
					st_render_field($def, array(
						'value'    => isset($c['values'][$key]) ? $c['values'][$key] : '',
						'original' => isset($c['originals'][$key]) ? $c['originals'][$key] : '',
						'error'    => isset($errors[$key]) ? $errors[$key] : null,
						'readonly' => !$canWrite,
						'locked'   => st_locked($def),
						'visible'  => st_visible($def, $c['values']),
						'mine'     => false,
						'set_at'   => '',
					));
				} ?>
        </div>
				<?php } ?>
				<?php foreach (isset($tablesBySub[$sub]) ? $tablesBySub[$sub] : array() as $tableId => $t) {
					settings_rows_table($c, $tableId, $t, $posted, $errors);
				} ?>
      </section>
			<?php } ?>

		<?php if ($canWrite) { ?>
      <footer class="st-savebar" id="st-savebar" hidden>
        <div class="st-savebar-in">
          <span class="st-status"><i class="st-dotw st-dot-warn" aria-hidden="true"></i><span id="st-status" role="status"></span></span>
          <span class="st-savebar-btns">
            <kbd aria-hidden="true">Ctrl S</kbd>
            <button type="button" class="st-btn st-btn-quiet" id="st-undo" disabled><?= st_t(159) ?></button>
            <button type="submit" class="st-btn st-btn-primary" id="st-save"><?= st_t(6045) ?></button>
          </span>
        </div>
      </footer>
		<?php } ?>
    </form>
<?php if (function_exists('settings_rows_create_forms')) { settings_rows_create_forms($c); } ?>

    <aside class="st-hist" aria-labelledby="st-hist-title">
      <h2 id="st-hist-title"><?= st_t(5710) ?></h2>
      <div class="st-card">
			<?php foreach ($history as $row) {
				$key = (string) $row['setting_key'];
				$parts = explode('.', $key, 2);
				$t = isset($tables[$parts[0]]) ? $tables[$parts[0]] : null;
				$label = $t ? st_txt($t['label']) : (isset($defs[$key]) ? st_txt($defs[$key]['label']) : $key);
				if ($t && isset($parts[1])) {
					$label .= ' · ' . (isset($t['columns'][$parts[1]]) ? st_txt($t['columns'][$parts[1]]['label']) : (isset($t['row_actions'][$parts[1]]) ? st_txt($t['row_actions'][$parts[1]]['label']) : $parts[1]));
				}
				?>
        <div class="st-h">
          <span class="st-h-field"><?= st_h($label) ?></span>
          <span class="st-h-ch"><?php if ($row['handling'] === 'setting.row_created') { ?><?= st_t(6429) ?><?php } elseif ($row['handling'] === 'setting.row_deleted') { ?><s><?= st_t(1099) ?></s><?php } else { ?><?php $isBool = $t && isset($parts[1], $t['columns'][$parts[1]]) && $t['columns'][$parts[1]]['type'] === 'bool'; $show = function ($v) use ($isBool, $t, $parts) { return $isBool ? (settings_rows_form_value($t['columns'][$parts[1]], (string) $v) !== '' ? '✓' : '–') : (string) $v; }; ?><s><?= st_h($show($row['old_value'])) ?></s> → <?= st_h($show($row['new_value'])) ?><?php } ?></span>
          <span class="st-h-m"><span><?= st_h($row['brugernavn']) ?> · <?= st_h(st_local_time((string) $row['tidspunkt'], 'j/n H:i')) ?></span></span>
        </div>
			<?php } ?>
			<?php if (!$history) { ?>
        <p class="st-h-empty"><?= st_t(5712) ?></p>
			<?php } ?>
			<?php if (function_exists('perm_can') && perm_can('settings.audit.read', 'read')) { ?>
        <div class="st-h-foot"><a class="st-tl" href="usersRoles.php?tab=log"><?= st_t(6046) ?></a></div>
			<?php } ?>
      </div>
    </aside>
  </div>

  <div class="st-backdrop" id="st-backdrop" hidden></div>
  <div class="st-dialog" id="st-dialog" role="dialog" aria-modal="true" aria-labelledby="st-dialog-title" hidden>
    <h3 id="st-dialog-title"></h3>
    <p id="st-dialog-body"></p>
    <form method="post" action="<?= st_h($c['selfUrl']) ?>" id="st-dialog-form">
      <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
      <input type="hidden" name="action" value="">
      <input type="hidden" name="table" value="">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="value" value="">
      <label class="st-dialog-pw" id="st-dialog-arg" hidden><span></span><input class="st-input" type="text" name="arg" autocomplete="off" inputmode="numeric"></label>
      <label class="st-dialog-pw" id="st-dialog-pw" hidden><span><?= st_t(6546) ?></span><input class="st-input" type="password" name="password" autocomplete="current-password"></label>
      <div class="st-dialog-btns">
        <button type="button" class="st-btn st-btn-quiet" id="st-dialog-cancel"><?= st_t(5) ?></button>
        <button type="submit" class="st-btn st-btn-primary" id="st-dialog-ok"></button>
      </div>
    </form>
  </div>
  <div class="st-snack" id="st-snack" role="status" hidden></div>
</div>
<script>window.SALDI_SETTINGS = <?= json_encode($config) ?>;</script>
<script src="../javascript/settingsRows.js?v=4"></script>
	<?php
}

/**
 * One table card: header, rows, the template for a new row and the footer with the add button.
 */
function settings_rows_table(array $c, string $tableId, array $t, array $posted, array $errors): void
{
	$canWrite = $c['canWrite'];
	$rows = settings_rows_load($t, $c['year']);
	if ($t['hide_empty'] && !$rows) {
		return;
	}
	$postedRows = isset($posted[$tableId]) && is_array($posted[$tableId]) ? $posted[$tableId] : array();
	$hasInactive = false;
	foreach ($rows as $row) {
		if ($row['inactive']) {
			$hasInactive = true;
		}
	}
	$editable = array();
	foreach ($t['columns'] as $col => $def) {
		if ($def['type'] !== 'derived') {
			$editable[] = $col;
		}
	}
	$cell = function (string $rowId, string $col, array $def, string $value, array $row = null) use ($tableId, $canWrite, $errors) {
		$id = 'c-' . str_replace('.', '-', $tableId . '-' . $rowId . '-' . $col);
		$name = 'r[' . $tableId . '][' . $rowId . '][' . $col . ']';
		$err = isset($errors[$tableId . '/' . $rowId . '/' . $col]) ? (int) $errors[$tableId . '/' . $rowId . '/' . $col] : 0;
		$attrs = ' id="' . st_h($id) . '" name="' . st_h($name) . '" data-orig="' . st_h($value) . '"' . ($err ? ' aria-invalid="true" title="' . st_t($err) . '"' : '');
		$ro = !$canWrite || $def['readonly'] || ($row && !empty($row['locked'])) || ($row && ($def['create_only'] || $def['transient']));
		if ($def['type'] === 'code' && $row && $row['usage'] > 0) {
			$ro = true;
			$attrs .= ' title="' . st_t(6420) . '"';
		} elseif ($def['locked_if_used'] && $row && $row['usage'] > 0) {
			$ro = true;
			$attrs .= ' title="' . st_t(is_int($def['locked_if_used']) ? $def['locked_if_used'] : 6467) . '"';
		}
		if ($def['transient'] && $row) {
			// Used only when the row is created (the template of a new background); nothing to show afterwards.
			echo '<td class="st-rc st-rc-derived"><span></span></td>';
			return;
		}
		if ($def['type'] === 'derived') {
			echo '<td class="st-rc st-rc-derived st-rc-col-' . st_h($col) . '"><span>' . st_h($row ? settings_rows_derived((string) $def['derive'], $row) : '') . '</span></td>';
			return;
		}
		echo '<td class="st-rc st-rc-' . st_h($def['type']) . ($err ? ' st-rc-err' : '') . '">';
		if ($def['type'] === 'bool') {
			if (!$ro) {
				echo '<input type="hidden" name="' . st_h($name) . '" value="">';
			}
			echo '<input type="checkbox" class="st-rcheck"' . $attrs . ' value="1"' . ($value !== '' ? ' checked' : '') . ($ro ? ' disabled' : '') . '>';
		} elseif ($def['type'] === 'select') {
			echo '<select class="st-rin"' . $attrs . ($ro ? ' disabled' : '') . '>';
			$opts = st_options($def);
			if ($value !== '' && !array_key_exists($value, $opts)) {
				// A stored value the list no longer offers stays visible (and is kept unless changed).
				echo '<option value="' . st_h($value) . '" selected>' . st_h($value) . '</option>';
			}
			foreach ($opts as $v => $label) {
				echo '<option value="' . st_h((string) $v) . '"' . ((string) $v === $value ? ' selected' : '') . '>' . st_option_label($def, $label) . '</option>';
			}
			echo '</select>';
		} else {
			$mode = in_array($def['type'], array('code', 'account', 'decimal'), true) ? ' inputmode="decimal"' : ($def['type'] === 'date' ? ' placeholder="dd-mm-åååå"' : '');
			echo '<input type="text" class="st-rin' . (in_array($def['type'], array('code', 'account', 'decimal'), true) ? ' st-rin-num' : '') . '"' . $attrs . ' value="' . st_h($value) . '"' . $mode . ($ro ? ' readonly' : '') . '>';
		}
		echo '</td>';
	};
	?>
        <div class="st-rhead">
          <h2 id="tbl-<?= st_h($tableId) ?>-h"><?= st_t($t['label']) ?></h2>
					<?php if ($t['help'] !== null) { ?><p class="st-rhelp"><?= function_exists('settings_rows_help_text') ? st_h(settings_rows_help_text($t)) : st_t($t['help']) ?></p><?php } ?>
					<?php if (is_array($t['filter'])) { ?>
          <div class="st-scope st-rfilter">
            <label for="st-f-<?= st_h($tableId) ?>"><?= st_t($t['filter']['label']) ?></label>
            <select class="st-input st-input-short" id="st-f-<?= st_h($tableId) ?>" onchange="if (this.value) { window.location = this.value; }">
						<?php foreach ($t['filter']['options'] as $v => $lab) { ?>
              <option value="<?= st_h(preg_replace('/([?&])' . $t['filter']['param'] . '=[0-9]*/', '$1', $c['selfUrl']) . '&' . $t['filter']['param'] . '=' . (int) $v) ?>"<?= (int) $v === (int) $t['filter']['value'] ? ' selected' : '' ?>><?= st_h($lab) ?></option>
						<?php } ?>
            </select>
          </div>
					<?php } ?>
        </div>
        <div class="st-card st-rows" data-table="<?= st_h($tableId) ?>" data-inactive="<?= $t['inactive'] ? '1' : '0' ?>">
          <table class="st-rt" aria-labelledby="tbl-<?= st_h($tableId) ?>-h"<?= (!$rows && !$postedRows) ? ' hidden' : '' ?>>
            <thead>
              <tr>
							<?php foreach ($t['columns'] as $col => $def) { ?>
                <th scope="col" class="st-rc-<?= st_h($def['type']) ?>"<?= $def['help'] !== null ? ' title="' . st_t($def['help']) . '"' : '' ?>><?= st_t($def['label']) ?></th>
							<?php } ?>
                <th scope="col" class="st-ra"></th>
              </tr>
            </thead>
            <tbody>
						<?php foreach ($rows as $id => $row) {
							$rowId = (string) $id;
							$cells = isset($postedRows[$rowId]) && is_array($postedRows[$rowId]) ? $postedRows[$rowId] : null;
							$code = trim((string) $row['cells'][$t['code_col']]);
							?>
              <tr class="<?= $row['inactive'] ? 'st-row-inactive' : '' ?>" data-row="<?= st_h($rowId) ?>" data-code="<?= st_h($code) ?>" data-use="<?= st_h($row['usage_text']) ?>"<?= $row['inactive'] && !$hasInactive ? '' : '' ?>>
							<?php foreach ($t['columns'] as $col => $def) {
								$value = $cells !== null && isset($cells[$col]) ? (string) $cells[$col] : settings_rows_form_value($def, (string) $row['cells'][$col]);
								$cell($rowId, $col, $def, $value, $row);
							} ?>
                <td class="st-ra">
				<?php if ($canWrite) { ?>
				<?php foreach ($t['row_actions'] as $an => $a) {
					if (!function_exists('settings_rows_action_visible') || !settings_rows_action_visible($an, $row)) {
						continue;
					}
					if (!empty($a['href'])) { ?><a class="st-ricon st-ricon-txt" href="<?= st_h(sprintf($a['href'], (int) $id)) ?>"><?= st_t($a['label']) ?></a><?php continue; }
					$body = !empty($a['confirm_args']) && function_exists('settings_rows_action_args') ? st_h(vsprintf(st_txt($a['confirm']), settings_rows_action_args((string) $a['confirm_args'], $row))) : (!empty($a['confirm']) ? st_t($a['confirm']) : '');
					?><button type="button" class="st-ricon st-ricon-txt<?= !empty($a['danger']) ? ' st-ricon-danger' : '' ?>" data-row-action="<?= st_h($an) ?>"<?= !empty($a['confirm_title']) ? ' data-title="' . st_h(sprintf(st_txt($a['confirm_title']), $code)) . '" data-body="' . $body . '"' : '' ?><?= !empty($a['input']) ? ' data-input="' . st_t($a['input']) . '"' : '' ?><?= !empty($a['danger']) ? ' data-danger="1"' : '' ?><?= !empty($a['danger_zone']) ? ' data-password="1"' : '' ?> data-verb="<?= st_t($a['label']) ?>"><?= st_t($a['label']) ?></button><?php
				} ?>
				<?php if ($t['inactive']) { ?><button type="button" class="st-ricon" data-inactive="<?= $row['inactive'] ? '0' : '1' ?>" title="<?= st_t($row['inactive'] ? 6434 : 6433) ?>" aria-label="<?= st_t($row['inactive'] ? 6434 : 6433) ?>"><i class='bx <?= $row['inactive'] ? 'bx-show' : 'bx-hide' ?>' aria-hidden="true"></i></button><?php } ?>
				<?php if (empty($row['locked'])) { ?><button type="button" class="st-ricon st-ricon-del" data-del title="<?= st_t(1099) ?>" aria-label="<?= st_t(1099) ?>"><i class='bx bx-trash' aria-hidden="true"></i></button><?php } ?>
				<?php } ?>
                </td>
              </tr>
						<?php } ?>
						<?php foreach ($postedRows as $rowId => $cells) {
							if (strpos((string) $rowId, 'n') !== 0 || !is_array($cells)) {
								continue;
							}
							?>
              <tr class="st-new" data-row="<?= st_h((string) $rowId) ?>" data-code="">
							<?php foreach ($t['columns'] as $col => $def) {
								$cell((string) $rowId, $col, $def, isset($cells[$col]) ? (string) $cells[$col] : '');
							} ?>
                <td class="st-ra"><button type="button" class="st-ricon st-ricon-del" data-del title="<?= st_t(1099) ?>" aria-label="<?= st_t(1099) ?>"><i class='bx bx-trash' aria-hidden="true"></i></button></td>
              </tr>
						<?php } ?>
            </tbody>
          </table>
					<?php if (!$rows && !$postedRows) {
						$standard = ($canWrite && !$t['no_add']) ? settings_rows_standard($t) : array(); ?>
          <div class="st-rempty"><b><?= st_t($t['empty']) ?></b><?php if ($standard) { ?>
            <span class="st-rseed"><button type="button" class="st-btn st-btn-primary" data-seed="<?= st_h(json_encode($standard, JSON_INVALID_UTF8_SUBSTITUTE)) ?>"><?= st_t(6824) ?></button>
            <button type="button" class="st-btn" data-add><?= st_t(6825) ?></button></span><?php } ?></div>
					<?php } ?>
					<?php if ($canWrite && !$t['no_add'] && (!is_array($t['filter']) || !empty($t['filter']['value']))) { ?>
          <template data-row-template>
            <tr class="st-new" data-row="__N__" data-code="">
						<?php foreach ($t['columns'] as $col => $def) {
							$cell('__N__', $col, $def, '');
						} ?>
              <td class="st-ra"><button type="button" class="st-ricon st-ricon-del" data-del title="<?= st_t(1099) ?>" aria-label="<?= st_t(1099) ?>"><i class='bx bx-trash' aria-hidden="true"></i></button></td>
            </tr>
          </template>
          <div class="st-rfoot">
            <button type="button" class="st-btn" data-add><i class='bx bx-plus' aria-hidden="true"></i><?= st_t($t['add']) ?></button>
            <span class="st-rhint"><?= st_t(6421) ?></span>
						<?php if ($t['inactive'] && $hasInactive) { ?>
            <label class="st-rtoggle"><input type="checkbox" data-show-inactive> <?= st_t(6432) ?></label>
						<?php } ?>
						<?php if ($t['fiscal'] && !$rows && !$postedRows) {
							$prev = null;
							foreach ($c['years'] as $y) {
								if ($y < $c['year'] && ($prev === null || $y > $prev)) {
									$prev = $y;
								}
							}
							if ($prev !== null) { ?>
            <form method="post" action="<?= st_h($c['selfUrl']) ?>" class="st-inline">
              <input type="hidden" name="csrf_token" value="<?= st_h($c['csrfToken']) ?>">
              <input type="hidden" name="action" value="row_copy_year">
              <input type="hidden" name="table" value="<?= st_h($tableId) ?>">
              <input type="hidden" name="from_year" value="<?= $prev ?>">
              <button type="submit" class="st-btn st-btn-quiet"><?= st_h(sprintf(st_txt(6440), $prev)) ?></button>
            </form>
						<?php } } ?>
          </div>
					<?php } ?>
        </div>
					<?php if ($t['create'] !== null && function_exists('settings_rows_create_card')) {
						settings_rows_create_card((string) $t['create'], $c, $tableId, $t);
					} ?>
	<?php
}
