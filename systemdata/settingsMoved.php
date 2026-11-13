<?php
// ---- systemdata/settingsMoved.php --- ver 5.1.1 --- 2026.11.13 ---
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
// 20260929 SS Settings redesign phase 4a (spec §8.10): "Hvor er...?" - the old menu next to where each item lives now, searchable.
//             Generated from getSettingsMovedMap().
// 20261002 SS Hand-over 2 Oct (§8.0): page head with the filter on the right, no Back button, list in one card.
// 20261004 SS No longer gated by the Indstillinger bit; the list is filtered by the user's settings groups.
// 20261101 CL/ASR Settings hub "C - Fold ud": each old menu item points at its place on the hub (group › sub-group › row), from settings_hub_moved_rows().
//                 The classic menu and the first new settings' names that moved or were renamed are both listed; left-out items say they are found by search.
//                 The page is built from css/saldi-kit.css under .sk and has the Tilbage button above its title like every settings page (owner, 1 Nov 2026).
// 20261101 CL/ASR Tilbage has 8 px to the title and Alt+L, as on the other settings pages.
// 20261102 CL/ASR Tilbage goes to the hub with Alt+L from settings_back_button(); css/saldi-kit.css (?v=20261102) places it, so the page's own rule is gone.
// 20261108 CL/ASR css/saldi-theme.css linked as ?v=8 and css/saldi-kit.css as ?v=20261108: the page ground is Saldi's classic page colour #eeeef0, the panels and fields stay white (owner, 8 Nov 2026).
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int $sprog_id
 */

@session_start();
$s_id = session_id();

$title = "Indstillinger";
$css = "../css/saldi-theme.css?v=8";
$modulnr = 0;
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
print "<link rel='stylesheet' href='../css/saldi-kit.css?v=20261108'>\n";
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");

settings_moved_view((int) $sprog_id);

function settings_moved_view(int $sprogId): void
{
	$rows = settings_hub_moved_rows($sprogId);
	usort($rows, function ($a, $b) {
		return strcmp(mb_strtolower($a['old']), mb_strtolower($b['old']));
	});
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt(6023), 'UTF-8', st_charset())) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb('', st_txt(5725), $sprogId), st_charset()) ?>
<div class="sk sk-page">
  <section class="sk-head">
    <div class="sk-head-tx">
      <?= settings_back_button() ?>
      <h1 class="sk-title"><?= st_t(6023) ?></h1>
      <p class="sk-lead"><?= st_t(6049) ?></p>
    </div>
    <div class="sk-head-acts">
      <div class="sk-search"><i class='bx bx-search' aria-hidden="true"></i><input type="search" class="sk-input" id="sm-search" placeholder="<?= st_t(6050) ?>" autocomplete="off" aria-label="<?= st_t(913) ?>"></div>
    </div>
  </section>
  <section class="sk-panel">
    <div class="sk-table-wrap">
      <table class="sk-table sk-table-flush" id="sm-table">
        <thead><tr><th scope="col"><?= st_t(5726) ?></th><th scope="col"><?= st_t(5727) ?></th></tr></thead>
        <tbody>
		<?php foreach ($rows as $row) { ?>
          <tr data-search="<?= st_h($row['search']) ?>">
            <td class="sk-wrap"><?= st_h($row['old']) ?></td>
            <td class="sk-wrap">
			<?php foreach ($row['to'] as $i => $to) { ?>
				<?= $i > 0 ? '<br>' : '' ?><?php if ($to['url'] !== '') { ?><a href="<?= st_h($to['url']) ?>"><?= st_h($to['path']) ?></a><?php } else { ?><?= st_h($to['path']) ?><?php } ?>
				<?php if ($to['tag'] === 'personal') { ?> <span class="sk-tag"><?= st_t(5742) ?></span><?php } elseif ($to['tag'] === 'out') { ?> <span class="sk-tag"><?= st_t(7747) ?></span><?php } ?>
			<?php } ?>
            </td>
          </tr>
		<?php } ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<script>
(function () {
	var input = document.getElementById('sm-search');
	input.addEventListener('input', function () {
		var q = input.value.trim().toLowerCase();
		document.querySelectorAll('#sm-table tbody tr').forEach(function (tr) {
			tr.hidden = q !== '' && tr.dataset.search.indexOf(q) === -1;
		});
	});
})();
</script>
	<?php
}
