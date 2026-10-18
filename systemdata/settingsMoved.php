<?php
// ---- systemdata/settingsMoved.php --- lap 5.0.0 --- 2026.09.29 ---
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
// 20260929 Sawaneh Settings redesign phase 4a (spec §8.10): "Hvor er...?" - the old menu next to
//                  where each item lives now, searchable. Generated from getSettingsMovedMap().
// 20261002 Sawaneh Hand-over 2 Oct (§8.0): page head with the filter on the right, no Back button, list in one card.
// 20261004 Sawaneh No longer gated by the Indstillinger bit; the list is filtered by the user's settings groups.

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int $sprog_id
 */

@session_start();
$s_id = session_id();

$title = "Indstillinger";
$css = "../css/unified-components.css?v=20261016b";
$modulnr = 0;
$permission_key = 'any';

include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
include(__DIR__ . "/../includes/std_func.php");
include_once(__DIR__ . "/settingsRegistry.php");
include_once(__DIR__ . "/../includes/settings/components.php");

settings_moved_view((int) $sprog_id);

function settings_moved_view(int $sprogId): void
{
	global $buttonColor, $buttonTxtColor;
	$groups = getSettingsGroups();
	$sections = getSettingsSections();
	$open = settings_accessible_groups();
	$accent = !empty($buttonColor) ? $buttonColor : '#114691';
	$accentTxt = !empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff';
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(st_txt(5725), 'UTF-8', st_charset())) ?>;</script>
<?= settings_breadcrumb_script(settings_breadcrumb('', st_txt(5725), (int) $GLOBALS['sprog_id']), st_charset()) ?>
<div class="st-page" style="<?= st_h(st_accent_style((string) $accent, (string) $accentTxt)) ?>">
  <section class="st-phead">
    <div>
      <h1><?= st_t(6023) ?></h1>
      <p class="st-lead"><?= st_t(6049) ?></p>
    </div>
    <label class="st-search" for="st-moved-search"><i class='bx bx-search' aria-hidden="true"></i><input type="search" id="st-moved-search" placeholder="<?= st_t(6050) ?>" autocomplete="off" aria-label="<?= st_t(913) ?>"></label>
  </section>
  <div class="st-card st-moved">
    <table>
      <thead><tr><th scope="col"><?= st_t(5726) ?></th><th scope="col"><?= st_t(5727) ?></th></tr></thead>
      <tbody>
		<?php foreach (getSettingsMovedMap() as $row) {
			$old = settings_legacy_text($row['old'], $sprogId);
			$targets = array();
			foreach ($row['to'] as $to) {
				list($group, $sectionId, $url) = $to;
				if ($group === 'personal') {
					$targets[] = array('text' => st_txt(5500), 'url' => 'personalSettings.php', 'personal' => true, 'moved' => true);
					continue;
				}
				if (!isset($groups[$group])) {
					continue;
				}
				$text = findtekst($groups[$group]['label'], $sprogId);
				$moved = false;
				if ($sectionId !== null && isset($sections[$sectionId])) {
					$text .= ' → ' . st_txt($sections[$sectionId]['label']);
					$url = settings_section_url($sectionId);
					$moved = true;
				}
				$targets[] = array('text' => $text, 'url' => isset($open[$group]) ? $url : '', 'personal' => false, 'moved' => $moved);
			}
			$search = mb_strtolower($old . ' ' . implode(' ', array_map(function ($t) { return $t['text']; }, $targets)));
			$search .= settings_legacy_all_languages($row['old']);
			foreach ($row['to'] as $to) {
				if (isset($groups[$to[0]])) {
					$search .= ' ' . settings_text_all_languages((int) $groups[$to[0]]['label']);
				}
				if ($to[1] !== null && isset($sections[$to[1]])) {
					$search .= ' ' . settings_text_all_languages($sections[$to[1]]['label']);
				}
			}
			?>
        <tr data-search="<?= st_h($search) ?>">
          <td><?= st_h($old) ?></td>
          <td><ul>
			<?php foreach ($targets as $t) { ?>
            <li><?php if ($t['url'] !== '') { ?><a href="<?= st_h($t['url']) ?>"><?= st_h($t['text']) ?></a><?php } else { ?><?= st_h($t['text']) ?><?php } ?>
				<?php if ($t['personal']) { ?> <span class="st-tag st-tag-personal"><?= st_t(5742) ?></span><?php } ?>
				<?php if (!$t['moved']) { ?> <span class="st-tag"><?= st_t(5750) ?></span><?php } ?>
            </li>
			<?php } ?>
          </ul></td>
        </tr>
		<?php } ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function () {
	var input = document.getElementById('st-moved-search');
	input.addEventListener('input', function () {
		var q = input.value.trim().toLowerCase();
		document.querySelectorAll('.st-moved tbody tr').forEach(function (tr) {
			tr.hidden = q !== '' && tr.dataset.search.indexOf(q) === -1;
		});
	});
})();
</script>
	<?php
}
