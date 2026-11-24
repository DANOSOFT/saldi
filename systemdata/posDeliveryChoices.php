<?php
// ---- systemdata/posDeliveryChoices.php --- ver 5.1.1 --- 2026.11.24 ---
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
// 20261124 CL/LH K33 The delivery choices the cashier picks on a sale (pos_leveringsvalg), on their own page now that PoS-valg is retired (#1191).
//                Indstillinger -> Kasse -> Skærm & kassemenuer -> Ekspedition links here (pos.screen.delivery_choices), only where the Shopify schema is installed.
//                The table and its save are the ones K33 put on PoS-valg (diverseIncludes/posSaleSettings.php), so a choice an order already uses still keeps its meaning.
//                CSRF and settings.pos write are checked before the save; every save redirects (PRG), and a refused row is shown on the next page.
@session_start();
$s_id = session_id();
ob_start();

$title = "Leveringsmåder i kassen";
$css = "../css/unified-components.css?v=20261108";
$permission_key = 'settings.pos';
$permission_level = 'read';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");
include_once(__DIR__ . "/../includes/settings/listView.php"); // settings_menu_html(), the left menu of every settings page
include_once(__DIR__ . "/diverseIncludes/posSaleSettings.php");

require_permission('settings.pos', 'read');
$canWrite = perm_can('settings.pos', 'write');

if (!isset($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$supported = pos_delivery_supported();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!isset($_POST['csrf_token']) || !hash_equals((string) $csrfToken, (string) $_POST['csrf_token'])) {
		audit_log('csrf', 'posDeliveryChoices.php');
		ob_end_clean();
		header('Location: posDeliveryChoices.php?msg=csrf');
		exit;
	}
	require_permission('settings.pos', 'write');
	// pos_save_delivery_choices() reports a refused or conflicting row with alert(), which prints a script. The other rows
	// are already committed, so the page still redirects (a reload must not post them again) and the script is shown
	// on the next page.
	ob_start();
	pos_save_delivery_choices($_POST);
	$_SESSION['pos_delivery_refusals'] = (string) ob_get_clean();
	audit_log('setting.changed', 'pos.screen.delivery_choices', 'indstilling', 'pos.screen.delivery_choices');
	ob_end_clean();
	if ($_SESSION['pos_delivery_refusals'] !== '') {
		header('Location: posDeliveryChoices.php?msg=refused');
	} else {
		header('Location: posDeliveryChoices.php?msg=saved');
	}
	exit;
}
$refusals = isset($_SESSION['pos_delivery_refusals']) ? (string) $_SESSION['pos_delivery_refusals'] : '';
unset($_SESSION['pos_delivery_refusals']);

$flash = array();
$msg = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
if ($msg === 'saved') {
	$flash[] = array('ok', 3322);
} elseif ($msg === 'csrf') {
	$flash[] = array('err', 5754);
}
if (!$canWrite) {
	$flash[] = array('info', 5755);
}
$charset = st_charset();
?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<style>
#pos-leveringsvalg td { padding: 4px 6px; font-size: 13px; vertical-align: middle; }
.pd-table { padding: 12px 20px; overflow-x: auto; }
.pd-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 14px 20px; border-top: 1px solid var(--st-grid); }
</style>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt(7203), 'UTF-8', $charset)) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb('pos', st_txt(7203), (int) $sprog_id), $charset) ?>
<div class="st-page" style="<?= st_h(st_accent_style((string) $buttonColor, (string) $buttonTxtColor)) ?>">
  <section class="st-phead">
    <div>
      <?= settings_back_button() ?>
      <h1><?= st_t(7203) ?></h1>
      <p class="st-lead"><?= st_t(7204) ?></p>
    </div>
  </section>
<?php if ($flash) { ?>
  <div class="st-notes">
	<?php foreach ($flash as $f) { ?>
    <div class="st-flash st-flash-<?= $f[0] ?>" role="<?= $f[0] === 'err' ? 'alert' : 'status' ?>"><?php if ($f[0] === 'info') { ?><i class='bx bx-lock-alt' aria-hidden="true"></i><?php } else { ?><i class="st-dotw st-dot-<?= $f[0] ?>" aria-hidden="true"></i><?php } ?><span><?= st_t($f[1]) ?></span></div>
	<?php } ?>
  </div>
<?php } ?>
  <div class="st-layout st-layout-list">
    <?= settings_menu_html(settings_hub_menu((int) $sprog_id, 'pos.screen'), (int) $sprog_id, array('label' => st_txt(7203))) ?>
    <div>
<?php if ($supported) { ?>
      <form class="st-card" method="post" action="posDeliveryChoices.php">
        <input type="hidden" name="csrf_token" value="<?= st_h($csrfToken) ?>">
        <fieldset style="border:0;margin:0;padding:0"<?= $canWrite ? '' : ' disabled' ?>>
          <div class="pd-table"><table><tbody>
<?php pos_render_delivery_choices_settings($sprog_id, st_charset() === 'UTF-8'); ?>
          </tbody></table></div>
          <div class="pd-foot">
            <button type="submit" class="st-btn st-btn-primary" accesskey="g"><?= st_t(3) ?></button>
          </div>
        </fieldset>
      </form>
<?php } ?>
    </div>
  </div>
</div>
<?= $refusals ?>
</body></html>
