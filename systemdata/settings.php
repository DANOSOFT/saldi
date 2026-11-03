<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/settings.php --- ver 5.1.0 --- 2026.11.03 ---
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
// 20261101 CL/ASR Settings hub "C - Fold ud", the owner's choice of 1 Nov 2026 (_prototypes/settings_hub/?v=c), built on the hub model in includes/settings/hub.php.
//                 Four groups (Virksomhed, Finans, Salg, Køb & lager) as panels in columns, each column stacking its own panels; each group has four sub-group rows with an icon, a short text and their count or status.
//                 A click, Enter or Space folds a row open in place and shows its pages as links; several can be open, the open rows are remembered in the browser, all are closed the first time.
//                 The search filters in place: it folds open the sub-groups with hits, marks the word, names the old menu name a row was found by, and adds single settings from settingsSearch.php under their row.
//                 Without the POS licence Kasse is a locked row; "Kræver opmærksomhed" moved to the row it is about.
//                 No zebra rows (owner); a Tilbage button above the title as on every settings page; behaviour in javascript/settingsHub.js.
// 20261101 CL/ASR Review: while searching only a sub-group's name is marked, as in the mock (its short text is not searched).
//                 The hub script link has a new version (the script now also runs on latin-1 pages).
//                 A search hit on a page this company lacks (e.g. Stripe abonnement outside the operator ledger) no longer shows the row it would sit in.
// 20261102 CL/ASR Tilbage (Alt+L) leaves the settings system: the newest page outside it in the navigation stack, skipping old addresses that redirect here, else Oversigt.
// 20261102 CL/ASR No Tilbage on the hub itself (owner): it is the settings start page; the sub-pages keep theirs.
//                 The kit and css/settingsHub.css are linked as ?v=20261102 (the kit places Tilbage now).
// 20261103 CL/ASR QA: settings_hub_json() converts a single string too on latin-1 installations (array_walk_recursive() on a string was a fatal error).

/**
 * Injected by ../includes/connect.php and ../includes/online.php, included below:
 * @var int    $sprog_id
 * @var string $db_encode
 * @var int    $bruger_id
 */

@session_start();
$s_id = session_id();

$title = "Indstillinger";
$css = "../css/settingsHub.css?v=20261102";
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
$hubReadOnly = (bool) $hubGroups;
foreach ($hubGroups as $hubGroup) {
	if (!function_exists('perm_can') || perm_can($hubGroup['def']['permission'], 'write')) {
		$hubReadOnly = false;
		break;
	}
}
$hubModel = settings_hub_model((int) $sprog_id);
settings_hub_view(array(
	'model'    => $hubModel,
	'readOnly' => $hubReadOnly,
	'notice'   => ((int) $bruger_id > 0) && $hubModel['groups'] && get_settings_value('transition_banner', 'settings_ui', '', (int) $bruger_id) !== 'dismissed' && settings_show_transition_banner((int) $bruger_id),
	'company'  => function_exists('st_company') ? st_company() : '',
	'csrf'     => (string) $_SESSION['csrf_token'],
), (int) $sprog_id);

/**
 * A count or status (kind ''|'ok'|'warn'|'err', text) as the kit shows it: a dot in the tone plus text, plain text without a tone.
 *
 * @param array{kind: string, text: string}|null $st
 */
function settings_hub_status_html(?array $st): string
{
	$tones = array('warn' => 'sk-warn', 'err' => 'sk-bad', 'ok' => 'sk-ok');
	if (!$st || (string) $st['text'] === '') {
		return '';
	}
	$text = '<span class="sh-st">' . st_h($st['text']) . '</span>';
	return isset($tones[$st['kind']]) ? '<span class="sk-status ' . $tones[$st['kind']] . '">' . $text . '</span>' : $text;
}

/**
 * Plain data for javascript/settingsHub.js: ASCII JSON (non-ASCII as \u escapes), so it is the same in a latin-1 and a UTF-8 page.
 *
 * @param mixed $value
 */
function settings_hub_json($value): string
{
	if (st_charset() !== 'UTF-8') {
		if (is_string($value)) {
			$value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
		} elseif (is_array($value)) {
			array_walk_recursive($value, function (&$s) {
				if (is_string($s)) {
					$s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
				}
			});
		}
	}
	return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * The page (mock C). Texts of the model are resolved and not escaped: every one goes through st_h here.
 *
 * @param array<string, mixed> $vm model (settings_hub_model), readOnly, notice, company, csrf
 */
function settings_hub_view(array $vm, int $sprogId): void
{
	$t = function (string $text): string {
		return st_h(st_txt($text));
	};
	$groups = $vm['model']['groups'];
	// the row a section or registry key belongs to, for single settings and pages the search endpoint finds
	$rowIds = array();
	foreach ($groups as $group) {
		foreach ($group['subs'] as $sub) {
			foreach ($sub['rows'] as $row) {
				$rowIds[$row['id']] = true;
			}
		}
	}
	$keyRow = array();
	foreach (settings_hub_index() as $key => $loc) {
		// a page of its own that this company lacks (Stripe outside the operator ledger, bank, booking) must not light up its row;
		// another name for a page (an alias) always leads to the row
		$def = settings_hub_page_def((string) $key);
		if (isset($rowIds[$loc['row']]) && ($def === null || $def['alias_of'] !== '' || settings_hub_page_available($def))) {
			$keyRow[$key] = $loc['row'];
		}
	}
	$cfg = array(
		'store'   => 'saldi.settingsHub.open',
		'keyRow'  => $keyRow,
		'leftOut' => $vm['model']['leftOut'],
		't'       => array(
			'noMatch' => st_txt('7743|Intet match i %s'),
			'was'     => st_txt('7744|tidligere “%s”'),
			'out'     => st_txt('7747|Ikke på forsiden – findes med søgning'),
			'count'   => st_txt('6040|%s resultater'),
			'none'    => st_txt('6042|Ingen resultater. Prøv et andet ord, eller se "Hvor er de gamle menupunkter?"'),
		),
	);
	// two columns as the page is first drawn (Virksomhed over Salg, Finans over Køb & lager); the script switches to four or one by the width
	$cols = array(array(), array());
	foreach ($groups as $i => $group) {
		$cols[$i % 2][] = $i;
	}
	$cols = array_values(array_filter($cols));
	?>
<link rel='stylesheet' href='../css/saldi-theme.css?v=7'><link rel='stylesheet' href='../css/saldi-kit.css?v=20261102'>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= settings_hub_json(st_txt('122|Indstillinger')) ?>;</script>
<?php if (function_exists('page_help')) { page_help(array('shortcuts' => '[data-keys]')); } ?>
<?= settings_breadcrumb_script(settings_breadcrumb('', '', $sprogId), st_charset()) ?>
<div class="sk sk-page sh-page" id="sh-hub">
  <section class="sk-head">
    <div class="sk-head-tx">
      <h1 class="sk-title"><?= $t('122|Indstillinger') ?></h1>
      <p class="sk-lead"><?= st_h(sprintf(st_txt('6020|Gælder for alle i %s. Dine personlige valg finder du under dit navn øverst til højre.'), $vm['company'])) ?></p>
    </div>
    <?php if ($groups) { ?>
    <div class="sk-head-acts">
      <div class="sk-search sh-search" role="search">
        <i class='bx bx-search' aria-hidden="true"></i><input type="search" class="sk-input" id="sh-search" placeholder="<?= $t('6039|Søg – fx moms, GLS eller et gammelt menunavn') ?>" aria-label="<?= $t('5663|Søg i indstillinger…') ?>" aria-controls="sh-grid" autocomplete="off" spellcheck="false"><kbd aria-hidden="true">/</kbd>
      </div>
    </div>
    <?php } ?>
  </section>

  <?php if (!$groups) { ?>
  <div class="sk-note sk-warn"><i class='bx bx-lock-alt' aria-hidden="true"></i><b><?= $t('5665|Du har ikke adgang til nogen indstillinger. Kontakt en administrator.') ?></b></div>
  <?php } elseif ($vm['readOnly']) { ?>
  <div class="sk-note sk-info" role="status"><i class='bx bx-lock-alt' aria-hidden="true"></i><span><b><?= $t('6031|Du har læseadgang') ?></b> <span class="sk-muted"><?= $t('6032|Du kan se indstillingerne, men ikke ændre dem. Kontakt en administrator.') ?></span></span></div>
  <?php } ?>

  <?php if ($vm['notice']) { ?>
  <form class="sk-note sk-info sh-notice" method="post" action="settings.php">
    <i class='bx bx-info-circle' aria-hidden="true"></i>
    <span><b><?= $t('6021|Ny struktur') ?></b> <span class="sk-muted"><?= $t('6022|Indstillingerne er samlet i færre, tydeligere grupper. Alt er her stadig – søg efter det gamle menunavn, så finder du det nye sted.') ?></span></span>
    <input type="hidden" name="csrf_token" value="<?= st_h($vm['csrf']) ?>">
    <input type="hidden" name="dismiss" value="1">
    <button type="submit" class="sk-btn sk-btn-ghost sk-btn-sm"><?= $t('5746|Skjul') ?></button>
  </form>
  <?php } ?>

  <?php if ($groups) { ?>
  <div class="sh-grid" id="sh-grid" data-layout="c2" style="--sh-n: <?= count($cols) ?>">
    <?php foreach ($cols as $col) { ?>
    <div class="sh-col">
      <?php foreach ($col as $gi) {
      	settings_hub_panel($groups[$gi], $gi, $cfg['t']['noMatch']);
      } ?>
    </div>
    <?php } ?>
  </div>

  <section class="sk-panel sh-panel sh-other" id="sh-other" aria-labelledby="sh-other-t" hidden>
    <div class="sk-panel-h"><h2 class="sk-panel-t" id="sh-other-t"><?= $t('7747|Ikke på forsiden – findes med søgning') ?></h2></div>
    <ul class="sh-links sh-other-list" id="sh-other-list"></ul>
  </section>
  <p class="sk-sr" id="sh-live" role="status" aria-live="polite"></p>

  <p class="sh-foot"><a href="settingsMoved.php"><?= $t('6023|Hvor er de gamle menupunkter?') ?></a><button type="button" class="sk-link" data-keys><?= $t('6024|Tastaturgenveje') ?></button></p>
  <?php } ?>

  <div class="sk-scrim" id="sh-backdrop" hidden></div>
  <div class="sk-dialog" id="sh-dialog" role="dialog" aria-modal="true" aria-labelledby="sh-dialog-title" hidden>
    <h3 class="sk-dialog-t" id="sh-dialog-title"><?= $t('6024|Tastaturgenveje') ?></h3>
    <ul class="sk-kv sh-keys">
      <li><span><?= $t('5663|Søg i indstillinger…') ?></span><span><kbd>/</kbd></span></li>
      <li><span><?= $t('7746|Gå til første træf') ?></span><span><kbd>Enter</kbd></span></li>
      <li><span><?= $t('2755|Ryd søgning') ?></span><span><kbd>Esc</kbd></span></li>
      <li><span><?= $t('7745|Fold ud eller sammen') ?></span><span><kbd>Enter</kbd> / <kbd><?= $t('7748|Mellemrum') ?></kbd></span></li>
      <li><span><?= $t('7749|Næste række') ?></span><span><kbd>Tab</kbd></span></li>
      <li><span><?= $t('5647|Tilbage') ?></span><span><kbd>Alt ←</kbd></span></li>
    </ul>
    <div class="sk-dialog-f"><button type="button" class="sk-btn sk-btn-primary" id="sh-dialog-close"><?= $t('2172|Luk') ?></button></div>
  </div>
  <script type="application/json" id="sh-cfg"><?= settings_hub_json($cfg) ?></script>
  <script src="../javascript/settingsHub.js?v=20261101d"></script>
</div>
	<?php
}

/**
 * One group as a panel: its sub-groups as rows that fold open into their pages (a locked row when the company lacks the module).
 *
 * @param array<string, mixed> $group from settings_hub_model()
 */
function settings_hub_panel(array $group, int $index, string $noMatch): void
{
	$gid = (string) $group['id'];
	?>
      <section class="sk-panel sh-panel" id="sh-g-<?= st_h($gid) ?>" data-i="<?= $index ?>" aria-labelledby="sh-gt-<?= st_h($gid) ?>">
        <div class="sk-panel-h"><h2 class="sk-panel-t" id="sh-gt-<?= st_h($gid) ?>"><?= st_h($group['label']) ?></h2><span class="sk-sub"><?= st_h($group['description']) ?></span></div>
        <div class="sh-acc">
        <?php foreach ($group['subs'] as $sub) {
        	$sid = (string) $sub['id'];
        	if ($sub['locked'] !== '') { ?>
          <div class="sh-item sh-locked" data-sub="<?= st_h($sid) ?>">
            <div class="sk-list-row sk-off" aria-disabled="true">
              <i class='bx <?= st_h($sub['icon']) ?> sk-list-ic' aria-hidden="true"></i>
              <span class="sk-list-tx"><b class="sk-list-t" data-hl><?= st_h($sub['label']) ?></b><span class="sk-list-d"><?= st_h($sub['locked']) ?></span></span>
              <span class="sk-list-meta"></span>
              <i class='bx bx-lock-alt sk-list-chev' aria-hidden="true"></i>
            </div>
          </div>
        <?php 	continue;
        	}
        	// the status of the row; a warning that needs the administrator (settings_attention) speaks for the row when nothing else warns
        	$status = $sub['status'];
        	if (!$status || !in_array($status['kind'], array('warn', 'err'), true)) {
        		foreach ($sub['rows'] as $row) {
        			if ($row['attention']) {
        				$status = array('kind' => (string) $row['attention']['kind'], 'text' => (string) $row['attention']['title']);
        				break;
        			}
        		}
        	}
        ?>
          <div class="sh-item" data-sub="<?= st_h($sid) ?>">
            <button type="button" class="sk-list-row sh-btn" id="sh-b-<?= st_h($sid) ?>" aria-expanded="false" aria-controls="sh-p-<?= st_h($sid) ?>">
              <i class='bx <?= st_h($sub['icon']) ?> sk-list-ic' aria-hidden="true"></i>
              <span class="sk-list-tx"><b class="sk-list-t" data-hl><?= st_h($sub['label']) ?></b><span class="sk-list-d"><?= st_h($sub['description']) ?></span></span>
              <span class="sk-list-meta"<?= $status ? ' title="' . st_h($status['text']) . '"' : '' ?>><?= settings_hub_status_html($status) ?></span>
              <i class='bx bx-chevron-down sk-list-chev sh-chev' aria-hidden="true"></i>
            </button>
            <div class="sh-body" id="sh-p-<?= st_h($sid) ?>" hidden>
              <ul class="sh-links">
              <?php foreach ($sub['rows'] as $row) {
              	$attention = $row['attention'];
              	// the attention line below says the same as a warning badge, with the button that fixes it
              	$badge = ($attention && $row['badge'] && in_array($row['badge']['kind'], array('warn', 'err'), true)) ? null : $row['badge'];
              ?>
                <li class="sh-row" data-row="<?= st_h($row['id']) ?>" data-terms="<?= st_h($row['terms']) ?>" data-old="<?= st_h(settings_hub_json(array_values($row['old']))) ?>">
                  <a class="sh-link" href="<?= st_h($row['url']) ?>"><span class="sh-link-tx"><span class="sh-link-t" data-hl><?= st_h($row['label']) ?></span><span class="sh-link-d" data-hl><?= st_h($row['hint']) ?></span></span><span class="sk-list-meta"><?= settings_hub_status_html($badge) ?></span><i class='bx bx-chevron-right sk-list-chev' aria-hidden="true"></i></a>
                  <?php if ($attention) { ?>
                  <div class="sh-attn"><?= settings_hub_status_html(array('kind' => (string) $attention['kind'], 'text' => (string) $attention['title'])) ?><a class="sk-btn sk-btn-sm" href="<?= st_h($attention['url']) ?>"><?= st_h($attention['button']) ?></a></div>
                  <?php } ?>
                  <?php if (count($row['pages']) > 1) { ?>
                  <ul class="sh-deep" hidden>
                    <?php foreach (array_slice($row['pages'], 1) as $page) { ?>
                    <li class="sh-page2" data-terms="<?= st_h(mb_strtolower((string) $page['label'], st_charset())) ?>" hidden><a class="sh-link sh-deep-link" href="<?= st_h($page['url']) ?>"><i class='bx bx-subdirectory-right sh-deep-ic' aria-hidden="true"></i><span class="sh-link-tx"><span class="sh-link-t" data-hl><?= st_h($page['label']) ?></span></span><i class='bx bx-chevron-right sk-list-chev' aria-hidden="true"></i></a></li>
                    <?php } ?>
                  </ul>
                  <?php } ?>
                </li>
              <?php } ?>
              </ul>
            </div>
          </div>
        <?php } ?>
        </div>
        <p class="sh-empty" hidden><?= st_h(sprintf($noMatch, $group['label'])) ?></p>
      </section>
	<?php
}
