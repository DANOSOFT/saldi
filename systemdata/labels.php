<?php
// ---- systemdata/labels.php --- lap 5.0.0 --- 2026.10.10 ---
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
// 20261010 Sawaneh Settings G5.8 Mærkater (Varer & lager): item label templates with the visual editor and raw HTML,
//                  new label from a shipped template, delete with a dialog, Standard kept. CSRF + PRG + audit log.
//                  Address labels (LABEL box2) have no reader and are not offered (spec G5.8); their data is kept.
//                  The old diverse.php?sektion=labels redirects here.
// 20261016 Sawaneh The left menu is the group's sections like every settings page; the label list is a column in the content.

@session_start();
$s_id = session_id();
ob_start();

$title = "Mærkater";
$css = "../css/unified-components.css?v=20261016";
$permission_key = 'settings.items';
$permission_level = 'read';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");
include_once(__DIR__ . "/../includes/labelTemplates.php");

require_permission('settings.items', 'read');
$canWrite = perm_can('settings.items', 'write');

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$names = label_names();
$current = isset($_GET['l']) ? (string) $_GET['l'] : 'Standard';
if (!isset($names[$current])) {
	$current = 'Standard';
}
$go = function (string $name, string $msg, array $extra = array()) {
	ob_end_clean();
	header('Location: labels.php?' . http_build_query(array('l' => $name, 'msg' => $msg) + $extra));
	exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])) {
		audit_log('csrf', 'labels.php');
		$go($current, 'csrf');
	}
	require_permission('settings.items', 'write');
	$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
	$name = isset($_POST['name']) ? (string) $_POST['name'] : '';
	if ($action === 'create') {
		$template = isset($_POST['template']) ? (string) $_POST['template'] : '';
		$templates = label_templates();
		$file = __DIR__ . '/../importfiler/' . $template;
		if (!label_name_valid($name) || !isset($templates[$template]) || !is_file($file)) {
			$go($current, 'invalid', array('new' => 1, 'n' => mb_substr($name, 0, 40)));
		}
		foreach (array_keys($names) as $existing) {
			if (strcasecmp((string) $existing, $name) === 0) {
				$go($current, 'exists', array('new' => 1, 'n' => $name));
			}
		}
		saveLabelText('box1', $name, (string) file_get_contents($file), 'sheet');
		audit_log('setting.row_created', 'Mærkat ' . $name . ' (' . $template . ')', 'labels', $name);
		$go($name, 'created');
	}
	if (!isset($names[$name])) {
		$go($current, '');
	}
	if ($action === 'save_visual') {
		if (!saveVisualLabelEdit('box1', $name, $_POST)) {
			$go($name, 'refused', array('mode' => 'raw'));
		}
		audit_log('setting.changed', 'Mærkat ' . $name, 'labels', $name);
		$go($name, 'saved');
	}
	if ($action === 'save_raw') {
		$type = (isset($_POST['labelType']) && $_POST['labelType'] === 'label') ? 'label' : 'sheet';
		saveLabelText('box1', $name, isset($_POST['rawHTML']) ? (string) $_POST['rawHTML'] : '', $type);
		audit_log('setting.changed', 'Mærkat ' . $name . ' (HTML)', 'labels', $name);
		$go($name, 'saved', array('mode' => 'raw'));
	}
	if ($action === 'delete' && strcasecmp($name, 'Standard') !== 0) {
		db_modify("delete from labels where labelname = '" . db_escape_string($name) . "' and (account_id = '0' or account_id is null)", __FILE__ . " linje " . __LINE__);
		audit_log('setting.row_deleted', 'Mærkat ' . $name, 'labels', $name);
		$go('Standard', 'deleted');
	}
	$go($name, '');
}

$label = loadLabelText('box1', $current);
$stored = (string) $label['labeltext'];
$type = ((string) $label['labeltype'] === 'label') ? 'label' : 'sheet';
$editable = labelTemplateEditableVisually($stored);
$raw = !$editable || (isset($_GET['mode']) && $_GET['mode'] === 'raw');
$p = parseLabelTemplate($stored);

$flash = array();
$msgs = array('saved' => array('ok', 7085), 'created' => array('ok', 7086), 'deleted' => array('ok', 7087), 'exists' => array('err', 7088),
	'invalid' => array('err', 7095), 'refused' => array('err', 5079), 'csrf' => array('err', 5754));
$msg = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
if (isset($msgs[$msg])) {
	$flash[] = $msgs[$msg];
}
if (!$editable && $msg !== 'refused') {
	$flash[] = array('warn', 5078);
}
if (!$canWrite) {
	$flash[] = array('info', 5755);
}
$movedText = (isset($_GET['moved']) && $_GET['moved'] === 'labels') ? st_txt(5720) . ' ' . settings_legacy_text(array(782, 791), (int) $sprog_id) . '. ' . st_txt(5721) : '';
$openNew = $canWrite && !empty($_GET['new']);
$newName = isset($_GET['n']) ? (string) $_GET['n'] : '';
$dis = $canWrite ? '' : ' disabled';
$charset = st_charset();
$self = 'labels.php?l=' . rawurlencode($current);

$num = function (string $name, $value, string $unit = '', int $min = 0, int $max = 0) use ($dis) {
	$range = $max ? ' min="' . $min . '" max="' . $max . '"' : '';
	$typeAttr = $max ? 'number' : 'text';
	return '<input class="st-input st-input-short" type="' . $typeAttr . '" name="' . $name . '" value="' . st_h((string) $value) . '"' . $range . ' inputmode="decimal"' . $dis . '>'
		. ($unit !== '' ? '<span class="st-unit">' . $unit . '</span>' : '');
};
$field = function (string $label, string $control) {
	return '<div class="st-field"><div class="st-tx"><span class="st-label">' . $label . '</span></div><div class="st-ctl">' . $control . '</div></div>';
};
$typeSelect = '<select class="st-input st-select" name="labelType"' . $dis . '><option value="sheet"' . ($type === 'sheet' ? ' selected' : '') . '>' . st_t(2547) . '</option>'
	. '<option value="label"' . ($type === 'label' ? ' selected' : '') . '>' . st_t(1315) . '</option></select>';
?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
.lb-el { display: grid; grid-template-columns: minmax(0, 1fr) 64px 150px; align-items: center; gap: 12px; padding: 10px 20px; border-top: 1px solid var(--st-grid); font-size: 14px; }
.lb-el.lb-head { font-size: 12px; font-weight: 600; color: var(--st-text-2); text-transform: uppercase; letter-spacing: .04em; }
.lb-line { grid-template-columns: 64px minmax(0, 1fr) 150px; }
.lb-line .st-input { width: 100%; }
.lb-el input[type=checkbox] { width: 18px; height: 18px; }
.lb-raw { padding: 16px 20px; display: flex; flex-direction: column; gap: 10px; }
.lb-raw textarea { width: 100%; min-height: 420px; font: 12.5px/1.5 ui-monospace, SFMono-Regular, Menlo, monospace; }
.lb-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 14px 20px; border-top: 1px solid var(--st-grid); }
.lb-foot > div { display: flex; gap: 8px; align-items: center; }
.lb-new { margin: 12px 0 0; }
.lb-sect { display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 24px; align-items: start; }
.lb-list h2, .lb-editor h2 { margin: 0 0 8px; font-size: 13px; font-weight: 600; }
.lb-names { margin: 0; padding: 0; border-left: 0; }
.lb-names a { display: flex; justify-content: space-between; gap: 8px; min-height: 36px; padding: 6px 10px; border-radius: 8px; color: var(--st-text); text-decoration: none; }
.lb-names a:hover { background: var(--st-surface-2); }
.lb-names a.on { background: var(--st-surface); box-shadow: 0 0 0 1px var(--st-border); font-weight: 500; }
.lb-editor { min-width: 0; display: flex; flex-direction: column; gap: 12px; }
@media (max-width: 900px) { .lb-sect { grid-template-columns: 1fr; } }
.st-dialog .st-field-col { display: flex; flex-direction: column; gap: 6px; margin: 0 0 14px; font-size: 13px; }
.st-dialog .st-field-col .st-input { width: 100%; }
</style>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt(791), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb('items', st_txt(791), (int) $sprog_id), $charset) ?>
<div class="st-page" style="<?= st_h(st_accent_style((string) $buttonColor, (string) $buttonTxtColor)) ?>">
  <section class="st-phead">
    <div>
      <h1><?= st_t(791) ?></h1>
      <p class="st-lead"><?= st_t(7075) ?></p>
    </div>
  </section>

<?php if ($movedText !== '' || $flash) { ?>
  <div class="st-notes">
	<?php if ($movedText !== '') { ?>
    <div class="st-toast" role="status"><i class="st-dotw st-dot-acc" aria-hidden="true"></i><span><?= st_h($movedText) ?> <a href="settingsMoved.php"><?= st_t(5722) ?></a></span></div>
	<?php } ?>
	<?php foreach ($flash as $f) { ?>
    <div class="st-flash st-flash-<?= $f[0] ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><?php if ($f[0] === 'info') { ?><i class='bx bx-lock-alt' aria-hidden="true"></i><?php } else { ?><i class="st-dotw st-dot-<?= $f[0] ?>" aria-hidden="true"></i><?php } ?><span><?= st_t($f[1]) ?></span></div>
	<?php } ?>
  </div>
<?php } ?>

  <div class="st-layout st-layout-list">
    <nav class="st-tabs" aria-label="<?= st_t(5650) ?>">
      <ul>
	<?php foreach (settings_section_tabs('items', (int) $sprog_id) as $tab) { $on = $tab['id'] === 'labels'; ?>
        <li><a href="<?= st_h($tab['url']) ?>"<?= $on ? ' class="on" aria-current="page"' : '' ?>><span><?= st_h($tab['label']) ?></span><?= $on ? '<i class="bx bx-chevron-right" aria-hidden="true"></i>' : '' ?></a></li>
	<?php } ?>
      </ul>
    </nav>

    <div class="st-sect lb-sect">
      <div class="lb-list">
        <h2><?= st_t(791) ?></h2>
        <ul class="st-tabs-sub lb-names">
	<?php foreach ($names as $n => $t) { ?>
          <li><a class="<?= (string) $n === $current ? 'on' : '' ?>" href="labels.php?l=<?= st_h(rawurlencode((string) $n)) ?>"<?= (string) $n === $current ? ' aria-current="page"' : '' ?>><span><?= st_h((string) $n) ?></span><span class="st-muted"><?= $t === 'label' ? st_t(1315) : st_t(2547) ?></span></a></li>
	<?php } ?>
        </ul>
	<?php if ($canWrite) { ?>
        <button type="button" class="st-btn lb-new" data-open="lb-new"><i class='bx bx-plus' aria-hidden="true"></i><?= st_t(7076) ?></button>
	<?php } ?>
      </div>
      <div class="lb-editor">
      <h2><?= st_h($current) ?></h2>
	<?php if ($current === 'Standard') { ?>
      <p><?= st_t(7093) ?></p>
	<?php } ?>
      <form class="st-card" method="post" action="<?= st_h($self) ?>" id="lb-form">
        <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
        <input type="hidden" name="name" value="<?= st_h($current) ?>">
	<?php if ($raw) { ?>
        <input type="hidden" name="action" value="save_raw">
        <div class="st-grouphead"><?= st_t(7083) ?></div>
        <div class="lb-raw">
          <span class="st-help"><?= st_t(5057) ?></span>
          <textarea class="st-input st-textarea" name="rawHTML" spellcheck="false"<?= $canWrite ? '' : ' readonly' ?>><?= st_h($stored) ?></textarea>
        </div>
        <?= $field(st_t(7092), $typeSelect) ?>
	<?php } else { ?>
        <input type="hidden" name="action" value="save_visual">
        <div class="st-grouphead"><?= st_t(7091) ?></div>
        <?= $field(st_t(7092), $typeSelect) ?>
        <?= $field(st_t(2758), $num('cols', $p['cols'], '', 1, 10)) ?>
        <?= $field(st_t(7077), $num('rows', $p['rows'], '', 1, 20)) ?>
        <?= $field(st_t(2504), $num('txtlen', $p['txtlen'], '', 10, 100)) ?>
        <?= $field(st_t(2411), $num('width', $p['width'], 'mm')) ?>
        <?= $field(st_t(1790), $num('height', $p['height'], 'mm')) ?>
        <?= $field(st_t(6291), $num('font_size', $p['font_size'], 'px')) ?>
        <?= $field(st_t(7078), $num('margin_top', $p['margin_top'], 'mm')) ?>
        <?= $field(st_t(7079), $num('margin_left', $p['margin_left'], 'mm')) ?>
        <div class="st-grouphead"><?= st_t(7080) ?></div>
        <div class="lb-el lb-head"><span></span><span><?= st_t(1133) ?></span><span><?= st_t(6291) ?></span></div>
		<?php foreach (array('varenr' => 320, 'varemrk' => 7081, 'beskrivelse' => 914, 'pris' => 915, 'barcode' => 2016) as $el => $tid) { ?>
        <div class="lb-el"><span id="lb-el-<?= $el ?>"><?= st_t($tid) ?></span><input type="checkbox" name="show_<?= $el ?>" aria-labelledby="lb-el-<?= $el ?>"<?= !empty($p['show_' . $el]) ? ' checked' : '' ?><?= $dis ?>><span><?= $el === 'barcode' ? '' : $num($el . '_font_size', $p[$el . '_font_size'], 'px', 6, 72) ?></span></div>
		<?php } ?>
        <div class="st-grouphead"><?= st_t(7082) ?></div>
		<?php for ($i = 1; $i <= 5; $i++) { ?>
        <div class="lb-el lb-line"><span><?= st_t(3266) ?> <?= $i ?></span><input class="st-input" type="text" name="custom_text_<?= $i ?>" value="<?= st_h(isset($p['custom_text_' . $i]) ? $p['custom_text_' . $i] : '') ?>" aria-label="<?= st_t(3266) ?> <?= $i ?>"<?= $dis ?>><span><?= $num('custom_text_' . $i . '_size', isset($p['custom_text_' . $i . '_size']) ? $p['custom_text_' . $i . '_size'] : $p['font_size'], 'px', 6, 72) ?></span></div>
		<?php } ?>
	<?php } ?>
        <div class="lb-foot">
          <div>
	<?php if ($raw && $editable) { ?>
            <a class="st-tl" href="<?= st_h($self) ?>"><?= st_t(7084) ?></a>
	<?php } elseif (!$raw) { ?>
            <a class="st-tl" href="<?= st_h($self . '&mode=raw') ?>"><?= st_t(7083) ?></a>
	<?php } ?>
          </div>
	<?php if ($canWrite) { ?>
          <div>
		<?php if ($current !== 'Standard') { ?>
            <button type="button" class="st-btn st-btn-danger" data-open="lb-del"><?= st_t(1099) ?></button>
		<?php } ?>
            <button type="submit" class="st-btn st-btn-primary" accesskey="g"><?= st_t(3) ?></button>
          </div>
	<?php } ?>
        </div>
      </form>
      </div>
    </div>
  </div>

<?php if ($canWrite) { ?>
  <div class="st-backdrop" id="lb-backdrop" hidden></div>
	<?php if ($current !== 'Standard') { ?>
  <div class="st-dialog" id="lb-del" role="dialog" aria-modal="true" aria-labelledby="lb-del-t" hidden>
    <h3 id="lb-del-t"><?= st_h(sprintf(st_txt(7089), $current)) ?></h3>
    <p><?= st_t(7090) ?></p>
    <form method="post" action="<?= st_h($self) ?>">
      <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="name" value="<?= st_h($current) ?>">
      <div class="st-dialog-btns">
        <button type="button" class="st-btn st-btn-quiet" data-close autofocus><?= st_t(5) ?></button>
        <button type="submit" class="st-btn st-btn-danger-solid"><?= st_t(1099) ?></button>
      </div>
    </form>
  </div>
	<?php } ?>
  <div class="st-dialog" id="lb-new" role="dialog" aria-modal="true" aria-labelledby="lb-new-t" hidden>
    <h3 id="lb-new-t"><?= st_t(7076) ?></h3>
    <form method="post" action="<?= st_h($self) ?>">
      <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
      <input type="hidden" name="action" value="create">
      <label class="st-field-col"><span><?= st_t(138) ?></span><input class="st-input" type="text" name="name" value="<?= st_h($newName) ?>" maxlength="40" pattern="[a-zA-Z0-9+.\-]+" required autofocus><span class="st-help"><?= st_t(7094) ?></span></label>
      <label class="st-field-col"><span><?= st_t(803) ?></span><select class="st-input st-select" name="template">
		<?php foreach (label_templates() as $file => $tid) { ?>
        <option value="<?= st_h($file) ?>"><?= st_t($tid) ?></option>
		<?php } ?>
      </select></label>
      <div class="st-dialog-btns">
        <button type="button" class="st-btn st-btn-quiet" data-close><?= st_t(5) ?></button>
        <button type="submit" class="st-btn st-btn-primary"><?= st_t(1232) ?></button>
      </div>
    </form>
  </div>
<?php } ?>
</div>
<script>
(function () {
	var backdrop = document.getElementById('lb-backdrop'), open = null, form = document.getElementById('lb-form'), dirty = false;
	function show(id) {
		var d = document.getElementById(id);
		if (!d || !backdrop) { return; }
		open = d; d.hidden = false; backdrop.hidden = false;
		var f = d.querySelector('[autofocus]'); if (f) { f.focus(); }
	}
	function hide() { if (open) { open.hidden = true; backdrop.hidden = true; open = null; } }
	document.querySelectorAll('[data-open]').forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-open')); }); });
	document.querySelectorAll('[data-close]').forEach(function (b) { b.addEventListener('click', hide); });
	if (backdrop) { backdrop.addEventListener('click', hide); }
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
	if (form) {
		form.addEventListener('input', function () { dirty = true; });
		form.addEventListener('change', function () { dirty = true; });
		form.addEventListener('submit', function () { dirty = false; });
		window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
	}
	document.querySelectorAll('.st-dialog form').forEach(function (f) { f.addEventListener('submit', function () { dirty = false; }); });
<?php if ($openNew) { ?>
	show('lb-new');
<?php } ?>
})();
</script>
</body></html>
