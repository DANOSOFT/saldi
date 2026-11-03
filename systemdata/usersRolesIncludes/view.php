<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/usersRolesIncludes/view.php --- ver 5.1.0 --- 2026.11.03 ---
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
// 20260916 Sawaneh View functions for systemdata/usersRoles.php (users, roles matrix, audit log).
// 20260928 Sawaneh Back button top-left in the theme colour, as elsewhere in the system (was Close top-right).
// 20260929 Sawaneh Roles stage 2 (§8.4): Invite user / Create user, status Invited, resend invitation.
// 20260930 Sawaneh Roles stage 2 (§7.2): audit log filters, search, pages, CSV export, readable actions.
// 20260930 Sawaneh Back goes one step up: from a user or role card to its list, from a list to the settings
//                  front page (the history fallback landed on the old VAT page);
//                  password fields on the user card are not filled in by the browser; bulk results say how
//                  many were done and why the rest were skipped; own card says why it cannot be closed.
// 20260930 Sawaneh Roles stage 2 (§8.1, §8.2): reset password, role and 2FA filters, sorting, pages of 50.
// 20260930 Sawaneh Roles stage 2 (§6.3): review of migrated roles in the list, on the card and in bulk.
// 20260930 Sawaneh Layout after Adam's prototype_brugere_roller.html: review banner, toolbar card with a bulk bar
//                  that appears on selection, user drawer, role list next to the matrix, dialogs instead of
//                  browser confirms.
// 20261001 Sawaneh Messages for a user above the actor's own access and for an expired form (CSRF); the Administrator
//                  role's users and roles rows cannot be lowered.
// 20261002 Sawaneh The page tells the shell its breadcrumb trail (settings redesign §8.0).
// 20261009 Sawaneh Oversigt group (front-page addendum 4 §3): block rows under Se Oversigt, disabled while it is off, read-only radios, module note.
// 20261011 Sawaneh Block rows under Se Oversigt = Ingen are greyed but still submitted, so their levels survive a save.
// 20261016 Sawaneh Review (PR #803): the enforcement card says that read is enforced only in deny mode. Text 7099.
// 20261031 CL/ASR Fallback text 2563 matches importfiler/tekster.csv.
// 20261101 CL/ASR New design (prototype D, as the dashboard): the page is built from css/saldi-kit.css under one .sk root, with the kit's head, tabs, panels, filter bars, 24 px tables, status dots, drawer and dialog, and the role matrix as a table.
//                 Same texts, controls, forms and URLs; one filled button per view (the role's Gem on the Roles tab).
// 20261101 CL/ASR Review 2: the e-mail cell carries the address as its title, as a long one now ends in an ellipsis.
// 20261101 CL/ASR Tilbage is the shared settings button (settings_back_button(): small kit button, arrow, above the title), as on every settings page.
//                 A user or role card still goes back to its list; a list goes where settings_back_link() says. Alt+L kept.
//                 The review banner sits under the head, so Tilbage keeps its place at the top.
// 20261102 CL/ASR settings_back_button() adds Alt+L itself; a list's Tilbage goes to the hub. The kit and usersRoles.css are linked as ?v=20261102 (the kit places Tilbage).
// 20261102 CL/ASR The standard settings layout (settings_layout_open(), includes/settings/layout.php; owner, 2 Nov 2026: every page reached from Indstillinger keeps the left menu): the menu of Virksomhed with Brugere & roller marked under Brugere & ansatte, and the tabs, review banner, notices, table, role matrix, audit log, drawer and dialogs in the content column beside it.
//                 Tilbage, title, the counts and Opret/Inviter bruger form the same head as the section pages; the layout prints the breadcrumb and the document title.
//                 usersRoles.css is linked after the layout's stylesheets, as ?v=20261102b.
// 20261102 CL/ASR Brugere & ansatte (owner, 2 Nov 2026: "Ét punkt, 'Brugere & ansatte', og én liste med alle"; one card with Stamdata and
//                 Login og rolle; one button "Tilføj person"; Roller, Audit-log and Ekstra felter in the menu). Small hooks into
//                 includes/settings/people.php, which holds the logic:
//                 - the users tab is the one list "Brugere & ansatte": the employees without a login are rows too (ur_people_rows_before/_rest,
//                   no tick box), a status filter "Ingen login", the counts with their singulars (ur_people_lead), a click anywhere on a row
//                   opens the card (data-href), the line under a name is initials · username, a chevron instead of the edit icon;
//                 - the user drawer is the person card: tabs Stamdata | Login og rolle under its head (ur_people_card_head), wider; an
//                   employee without a login has its own card with "Giv adgang" (ur_people_card_nologin); closing goes to the list;
//                 - the head's one button is "Tilføj person" (its dialog: ur_people_add_dialog); Roller and Audit-log have none and no tab bar
//                   (the left menu holds Roller and Audit-log); the title follows the tab (Brugere & ansatte / Roller / Audit-log);
//                 - Tilbage on the log opened by "Se log" from a card goes back to that card (ret=user_<id>, a whitelist).
//                 "Kobling til ansat" names a closed employee "(Fratrådt)" and marks one that already has a login; closing a login linked
//                 to an employee says that the employee is marked as resigned too (user_close() does that).
// 20261103 CL/ASR The one list is titled Brugere, the name of its row under the heading Brugere & roller (owner, 3 Nov 2026).
// 20261103 CL/ASR QA: Nulstil til standard is disabled for a role above the editor's own rights; the revisor checkbox is disabled once a
//                 revisor is designated; Audit-log times on PHP's clock in the format 3/11-2026 14:05.

/**
 * @param array<string, mixed> $vm From ur_view_model().
 */
function ur_view(array $vm): void
{
	$charset = $vm['charset'];
	$sprog = (int) $vm['sprogId'];
	$h = function ($s) use ($charset): string {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$t = function (string $text) use ($sprog, $h): string {
		return $h(findtekst($text, $sprog));
	};
	$link = function (string $query) use ($vm, $h): string {
		return $h($vm['linkPrefix'] . $query);
	};
	$roleName = function (?array $role) use ($sprog, $t): string {
		return $role ? htmlspecialchars(perm_role_name($role, $sprog), ENT_QUOTES, 'UTF-8') : $t('5554|Ingen rolle');
	};
	$flash = ($vm['msg'] === 'bulk') ? ur_bulk_flash($vm['act'], (int) $vm['done'], $vm['why'], $sprog) : ur_flash($vm['msg'], $sprog);
	if (!$flash) {
		$flash = ur_people_flash(); // a message from the card's Stamdata tab (an employee deleted there)
	}
	$counts = ur_user_counts($vm);
	// Tilbage goes one step up: from a person card or a role card to its list, from the log opened on a card to that card; from a list
	// as on every settings page (settings_back_link()).
	$back = (($vm['editUser'] !== null || $vm['person'] !== null) && $vm['tab'] === 'users') ? $vm['linkPrefix'] . 'tab=users' : (($vm['tab'] === 'roles' && isset($_GET['rolle'])) ? $vm['linkPrefix'] . 'tab=roles' : '');
	if ($vm['logBack'] !== '') {
		$back = $vm['linkPrefix'] . $vm['logBack'];
	}
	// The hub entry of the tab shown (after the permission fallback), so the menu marks Brugere & ansatte, Roller or Audit-log.
	$at = array('users' => 'brugere', 'roles' => 'roller', 'log' => 'audit_log');
	$at = $at[$vm['tab']];
	$pageTitle = array('users' => '5549|Brugere', 'roles' => '5550|Roller', 'log' => '5796|Audit-log');
	// One button in the head, on the list only: Tilføj person (Roller and Audit-log are reached from the left menu).
	$headActs = $vm['tab'] === 'users' ? ur_people_add_button($vm, $t) : '';
	// The standard layout of a settings page (includes/settings/layout.php): breadcrumb, document title, Tilbage, title, the counts
	// as the lead and the head's buttons, then the left menu of Virksomhed with Brugere & roller marked and the page in the content
	// column. Its stylesheets (fonts, the kit, the settings components, boxicons) are printed first, so css/usersRoles.css comes after them.
	print settings_layout_assets(true);
	print "<link rel='stylesheet' href='../css/usersRoles.css?v=20261102b'>\n";
	settings_layout_open(array(
		'at'           => $at,
		'kit'          => true,
		'class'        => 'ur-page',
		'title'        => findtekst($pageTitle[$vm['tab']], $sprog),
		'lead'         => $vm['tab'] === 'users' ? ur_people_lead($vm, $counts) : false,
		'back'         => $back,
		'actions_html' => $headActs,
	));
	?>
  <?php if ($vm['reviewCount'] > 0 && $vm['canWrite']) {
  	$sum = function_exists('perm_migration_summary') ? perm_migration_summary() : array('admin' => 0, 'custom' => 0);
  ?>
  <div class="sk-note sk-info ur-review" id="ur-review-banner">
    <i class='bx bx-shield-quarter'></i>
    <div class="sk-grow"><b><?= $t('5954|Saldi har oversat jeres brugere til roller') ?></b> <span class="sk-muted"><?= $h(sprintf(findtekst('5955|%s har fuld adgang som Administrator, %s fik en tilpasset rolle. Gennemgå fordelingen, så kun de rigtige har alt.', $sprog), $sum['admin'], $sum['custom'])) ?></span></div>
    <div class="ur-note-acts">
      <a class="sk-btn sk-btn-sm" href="<?= $link('tab=users&status=review') ?>"><?= $t('5880|Gennemgå') ?></a>
      <button class="sk-btn sk-btn-ghost sk-btn-sm" type="button" onclick="document.getElementById('ur-review-banner').hidden = true"><?= $t('5881|Skjul') ?></button>
    </div>
  </div>
  <?php } ?>

  <?php if ($flash) { ?>
  <div class="sk-note <?= $flash['type'] === 'ok' ? 'sk-ok' : 'sk-bad' ?>" role="status"><i class='bx <?= $flash['type'] === 'ok' ? 'bx-check-circle' : 'bx-error' ?>'></i><span><?= $h($flash['text']) ?></span></div>
  <?php } ?>
  <?php if (!$vm['tablesReady']) { ?>
  <div class="sk-note sk-bad"><i class='bx bx-error'></i><span>Roles tables missing - log in again so includes/betweenUpdates.php can create them.</span></div>
  <?php } ?>

  <?php
	if ($vm['tab'] === 'users') {
		ur_view_users($vm, $h, $t, $link, $roleName);
		if ($vm['editUser'] !== null) {
			ur_view_user_drawer($vm, $h, $t, $link, $roleName);
		} elseif ($vm['person'] !== null) {
			ur_people_card_nologin($vm, $h, $t, $link);
		}
	} elseif ($vm['tab'] === 'roles') {
		ur_view_roles($vm, $h, $t, $link, $roleName);
	} else {
		ur_view_log($vm, $h, $t, $link);
	}
	?>

  <div class="sk-modal" id="ur-dlg" hidden>
    <div class="sk-dialog" role="dialog" aria-modal="true" aria-labelledby="ur-dlg-title">
      <h3 class="sk-dialog-t" id="ur-dlg-title"></h3>
      <div class="sk-dialog-b" id="ur-dlg-body"></div>
      <div class="sk-dialog-f" id="ur-dlg-row"></div>
    </div>
  </div>
<?php ur_people_add_dialog($vm, $h, $t, $link); ?>
<?php settings_layout_close(); ?>
<template id="ur-dlg-cancel"><button type="button" class="sk-btn" data-dlg-close><?= $t('5940|Annullér') ?></button></template>
<?php ur_view_script($vm); ?>
<?php ur_people_assets($vm, $t); ?>
	<?php
}

/**
 * @return array{all: int, admins: int, invited: int, closed: int}
 */
function ur_user_counts(array $vm): array
{
	$c = array('all' => count($vm['users']), 'admins' => 0, 'invited' => 0, 'closed' => 0);
	foreach ($vm['users'] as $u) {
		if ($u['closed']) {
			$c['closed']++;
			continue;
		}
		if ($u['invited']) {
			$c['invited']++;
		}
		if ($u['role_id'] > 0 && $u['role_id'] === $vm['adminRoleId']) {
			$c['admins']++;
		}
	}
	return $c;
}

function ur_view_users(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	?>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-bulk" class="sk-panel">
    <input type="hidden" name="action" value="bulk_role">
    <div class="sk-bar">
      <label class="sk-search"><i class='bx bx-search'></i><input class="sk-input" type="search" id="ur-search" placeholder="<?= $t('7791|Søg navn, initialer, brugernavn eller e-mail') ?>" autocomplete="off"></label>
      <select class="sk-input" id="ur-role-filter" aria-label="<?= $t('5553|Rolle') ?>">
        <option value=""><?= $t('5875|Alle roller') ?></option>
        <?php foreach ($vm['roles'] as $role) { ?><option value="<?= (int) $role['id'] ?>"><?= $roleName($role) ?></option><?php } ?>
      </select>
      <select class="sk-input" id="ur-status-filter" data-initial="<?= $h($vm['statusFilter'] === 'review' ? '' : $vm['statusFilter']) ?>" aria-label="<?= $t('5557|Status') ?>">
        <option value=""><?= $t('5915|Alle statusser') ?></option>
        <option value="active"><?= $t('5558|Aktiv') ?></option>
        <option value="invited"><?= $t('5778|Inviteret') ?></option>
        <option value="closed"><?= $t('5559|Lukket') ?></option>
        <?= ur_people_status_option($vm, $t) ?>
      </select>
      <select class="sk-input" id="ur-2fa-filter" aria-label="2FA">
        <option value="">2FA: <?= $t('2498|Alle') ?></option>
        <option value="1"><?= $t('5873|Med 2FA') ?></option>
        <option value="0"><?= $t('5874|Uden 2FA') ?></option>
      </select>
      <?php if ($vm['reviewCount'] > 0) { ?>
      <select class="sk-input" id="ur-review-filter" data-initial="<?= $vm['statusFilter'] === 'review' ? '1' : '' ?>" aria-label="<?= $t('5882|Migreret, ikke bekræftet') ?>">
        <option value=""><?= $t('2498|Alle') ?></option>
        <option value="1"><?= $t('5882|Migreret, ikke bekræftet') ?></option>
      </select>
      <?php } ?>
    </div>

    <?php if ($vm['canWrite']) { ?>
    <div class="sk-bar sk-bar-sel" id="ur-bulkbar" hidden>
      <span><b id="ur-bulk-n">0</b>&nbsp;<?= $t('5908|markeret') ?></span><span class="sk-bar-sep"></span>
      <select class="sk-input sk-input-sm" name="role_id">
        <?php foreach ($vm['roles'] as $role) {
        	$assignable = perm_within_own(perm_levels_from_role($role['id']));
        ?><option value="<?= (int) $role['id'] ?>"<?= $assignable ? '' : ' disabled' ?>><?= $roleName($role) ?></option><?php } ?>
      </select>
      <button class="sk-btn sk-btn-sm" type="submit" name="bulk" value="role" data-applies="notself" data-bulk-confirm="<?= $t('5910|Tildel rolle') ?>"><?= $t('5910|Tildel rolle') ?></button>
      <span class="sk-bar-sep"></span>
      <button class="sk-btn sk-btn-sm sk-btn-danger" type="submit" name="bulk" value="close" data-applies="open" data-bulk-confirm="<?= $t('5764|Luk valgte') ?>" data-danger="1"><?= $t('2172|Luk') ?></button>
      <button class="sk-btn sk-btn-sm" type="submit" name="bulk" value="reopen" data-applies="closed" data-bulk-confirm="<?= $t('5765|Genåbn valgte') ?>"><?= $t('5912|Genåbn') ?></button>
      <button class="sk-btn sk-btn-sm" type="submit" name="bulk" value="resend" data-applies="invited" data-bulk-confirm="<?= $t('5781|Send invitation igen') ?>"><?= $t('5781|Send invitation igen') ?></button>
      <?php if ($vm['reviewCount'] > 0) { ?><button class="sk-btn sk-btn-sm" type="submit" name="bulk" value="suggest" data-applies="review" data-bulk-confirm="<?= $t('5887|Anvend forslag på valgte') ?>"><?= $t('5913|Anvend forslag') ?></button><?php } ?>
      <span class="sk-grow"></span>
      <button class="sk-link" type="button" id="ur-bulk-clear"><?= $t('5909|Ryd') ?></button>
    </div>
    <?php } ?>

    <div class="sk-table-wrap">
      <table class="sk-table sk-table-flush ur-users" id="ur-users">
        <thead>
          <tr>
            <?php if ($vm['canWrite']) { ?><th class="sk-c ur-cb"><input class="sk-check" type="checkbox" id="ur-check-all" aria-label="<?= $t('2498|Alle') ?>"></th><?php } ?>
            <th class="sk-sort" data-sort><?= $t('5531|Navn') ?></th>
            <th class="sk-sort" data-sort><?= $t('52|E-mail') ?></th>
            <th class="sk-sort" data-sort><?= $t('5553|Rolle') ?></th>
            <th class="sk-sort sk-c" data-sort>2FA</th>
            <th class="sk-sort" data-sort><?= $t('5556|Sidst aktiv') ?></th>
            <th class="sk-sort" data-sort><?= $t('5557|Status') ?></th>
            <th class="sk-act"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vm['users'] as $u) {
            ur_people_rows_before($vm, $u, $h, $t, $link);
            $searchBlob = mb_strtolower($u['navn'] . ' ' . (!empty($vm['canOrg']) ? $u['initialer'] : '') . ' ' . $u['brugernavn'] . ' ' . $u['email'] . ' ' . ($u['role'] ? perm_role_name($u['role'], $vm['sprogId']) : ''));
          	$status = $u['closed'] ? 'closed' : ($u['invited'] ? 'invited' : 'active');
            $cardLink = $link(ur_people_row_link($vm, $u));
          	$badge = !$u['role'] ? 'sk-bad' : ($u['role_id'] === $vm['adminRoleId'] ? 'sk-info' : ($u['role']['system'] ? '' : 'sk-warn'));
          	$tone = $u['closed'] ? 'sk-off' : ($u['invited'] ? 'sk-warn' : 'sk-ok');
          ?>
          <tr data-search="<?= $h($searchBlob) ?>" data-status="<?= $status ?>"<?= $u['id'] === $vm['selfId'] ? ' data-self="1"' : '' ?> data-role="<?= (int) $u['role_id'] ?>" data-tfa="<?= $u['twofactor'] ? 1 : 0 ?>"<?= $u['review'] ? ' data-review="1"' : '' ?> data-href="<?= $cardLink ?>"<?= ($vm['editUser'] && $vm['editUser']['id'] === $u['id']) ? ' class="on"' : '' ?>>
            <?php if ($vm['canWrite']) { ?><td class="sk-c ur-cb"><input class="sk-check" type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" aria-label="<?= $h($u['navn']) ?>"></td><?php } ?>
            <td class="ur-name" data-v="<?= $h(mb_strtolower($u['navn'])) ?>">
              <a class="ur-name-a" href="<?= $cardLink ?>"><?= $h($u['navn']) ?></a><?= ur_people_user_sub($vm, $u, $h, $t) ?><?php if ($u['review']) { ?><span class="sk-tag sk-warn"><?= $t('5883|Ikke bekræftet') ?></span><?php } ?><?php if ($u['isRevisor']) { ?><span class="sk-tag"><?= $t('2562|Revisor') ?></span><?php } ?>
            </td>
            <td class="sk-muted" title="<?= $h($u['email']) ?>"><?= $h($u['email']) ?></td>
            <td data-v="<?= $h($u['role'] ? mb_strtolower(perm_role_name($u['role'], $vm['sprogId'])) : '') ?>">
              <span class="sk-tag<?= $badge !== '' ? ' ' . $badge : '' ?>"><?= $roleName($u['role']) ?></span>
              <?php if ($u['review'] && $u['suggestion'] && $u['suggestion']['id'] !== $u['role_id'] && $vm['canWrite'] && $u['id'] !== $vm['selfId']) { ?>
              <?php $sName = perm_role_name($u['suggestion'], $vm['sprogId']); ?>
              <button type="button" class="sk-link ur-sugg" data-suggest="<?= (int) $u['id'] ?>" data-dlg-title="<?= $h(sprintf(findtekst('5918|Bekræft rolle for %s', $vm['sprogId']), $u['navn'])) ?>" data-dlg-body="<?= $h(sprintf(findtekst('5919|Saldi foreslår %s: den standardrolle med færrest ekstra rettigheder, der dækker alt, brugeren havde før.', $vm['sprogId']), $sName)) ?>" data-dlg-ok="<?= $h(sprintf(findtekst('5921|Tildel %s', $vm['sprogId']), $sName)) ?>"><?= $t('5555|Forslag') ?>: <?= $h($sName) ?> →</button>
              <?php } ?>
            </td>
            <td class="sk-c" data-v="<?= $u['twofactor'] ? 1 : 0 ?>"><i class='bx <?= $u['twofactor'] ? 'bxs-check-shield ur-tfa-on' : 'bx-shield ur-tfa-off' ?> ur-tfa' title="2FA"></i></td>
            <td class="sk-muted" data-v="<?= $h($u['lastLogin']) ?>"><?= $u['lastLogin'] !== '' ? $h(substr($u['lastLogin'], 0, 16)) : $t('5905|Aldrig') ?></td>
            <td data-v="<?= $status ?>"><span class="sk-status <?= $tone ?>"><?= $u['closed'] ? $t('5559|Lukket') : ($u['invited'] ? $t('5778|Inviteret') : $t('5558|Aktiv')) ?></span></td>
            <td class="sk-act"><a class="sk-btn sk-btn-ghost sk-btn-icon sk-btn-sm" href="<?= $cardLink ?>" aria-label="<?= $t('6157|Åbn') ?>" title="<?= $t('6157|Åbn') ?>"><i class='bx bx-chevron-right'></i></a></td>
          </tr>
          <?php } ?>
          <?php ur_people_rows_rest($vm, $h, $t, $link); ?>
        </tbody>
      </table>
      <div class="sk-empty" id="ur-empty" hidden><?= $t('7781|Ingen matcher søgningen') ?></div>
    </div>
    <div class="sk-foot">
      <span id="ur-foot-text" data-format="<?= $t('7780|Viser %s af %s') ?>"></span>
      <span class="sk-grow"></span>
      <span class="ur-pager" id="ur-pager" hidden>
        <button class="sk-btn sk-btn-sm" type="button" data-page="-1"><i class='bx bx-chevron-left'></i><?= $t('5870|Forrige') ?></button>
        <span id="ur-pager-text" data-format="<?= $t('5872|%s–%s af %s') ?>"></span>
        <button class="sk-btn sk-btn-sm" type="button" data-page="1"><?= $t('5871|Næste') ?><i class='bx bx-chevron-right'></i></button>
      </span>
    </div>
  </form>
  <?php if ($vm['canWrite']) { ?>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-suggest-form" hidden>
    <input type="hidden" name="action" value="confirm_role"><input type="hidden" name="id" value=""><input type="hidden" name="use" value="1">
  </form>
  <?php } ?>
	<?php
}

function ur_view_user_drawer(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	$u = $vm['editUser'];
	$isNew = ($u['id'] === 0);
	$inviteNew = ($isNew && $vm['newMode'] === 'invite');
	$ro = $vm['canWrite'] ? '' : ' disabled';
	$ownCard = (!$isNew && $u['id'] === $vm['selfId']);
	$lastAdmin = (!$isNew && function_exists('user_is_last_admin') && user_is_last_admin($u['id']));
	$closeUrl = $link('tab=users');
	$modules = array();
	foreach (permission_registry() as $key => $def) {
		if ($def['legacy']) {
			$modules[$key] = findtekst($def['label'], $vm['sprogId']);
		}
	}
	$adminKeys = array();
	foreach (permission_registry() as $key => $def) {
		if ($def['group'] === 'settings' && $def['dangerous']) {
			$adminKeys[] = $key;
		}
	}
	?>
  <a class="sk-scrim" href="<?= $closeUrl ?>" aria-label="<?= $t('2172|Luk') ?>"></a>
  <aside class="sk-drawer ur-drawer" id="ur-drawer" data-close="<?= $closeUrl ?>"<?= ur_people_drawer_attrs($vm) ?> aria-labelledby="ur-drawer-title">
    <div class="sk-drawer-h">
      <h2 class="sk-drawer-t" id="ur-drawer-title"><?= $isNew ? ($u['navn'] !== '' ? $h($u['navn']) : ($inviteNew ? $t('5779|Inviter bruger') : $t('5780|Opret bruger'))) : $h($u['navn']) ?><?php if ($u['invited']) { ?> <span class="sk-status sk-warn ur-drawer-st"><?= $t('5778|Inviteret') ?></span><?php } ?></h2>
      <a class="sk-btn sk-btn-ghost sk-btn-icon" href="<?= $closeUrl ?>" aria-label="<?= $t('2172|Luk') ?>" title="<?= $t('2172|Luk') ?>"><i class='bx bx-x'></i></a>
    </div>
    <?php ur_people_card_head($vm, $h, $t, $link); ?>
    <form method="post" action="<?= $h($vm['userFormUrl']) ?>" autocomplete="off" id="ur-user-form" class="sk-drawer-b">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <?php if ($isNew) { ?><input type="hidden" name="mode" value="<?= $inviteNew ? 'invite' : 'classic' ?>"><?php } ?>

      <?php if ($inviteNew) { ?><div class="sk-note sk-info"><span><?= $t('5788|Brugeren får en e-mail med et link og vælger selv sin adgangskode.') ?> <?= $t('5776|Linket gælder i 72 timer.') ?></span></div><?php } ?>
      <?php if ($vm['inviteLink'] !== '') { ?>
      <div class="sk-note sk-warn ur-note-col">
        <b><?= $t('5783|E-mailen kunne ikke sendes. Send dette link til brugeren.') ?></b>
        <input class="sk-input" type="text" readonly value="<?= $h($vm['inviteLink']) ?>" onfocus="this.select()">
      </div>
      <?php } ?>
      <?php if ($u['review'] && $vm['canWrite']) { ?>
      <div class="sk-note sk-warn ur-note-col">
        <span><?= $h(sprintf(findtekst('5884|Rollen er sat automatisk ud fra brugerens gamle rettigheder. Forslag: %s.', $vm['sprogId']), $u['suggestion'] ? perm_role_name($u['suggestion'], $vm['sprogId']) : '-')) ?></span>
        <span class="ur-note-acts">
          <?php if ($u['suggestion'] && $u['suggestion']['id'] !== $u['role_id'] && !$ownCard) { ?>
          <button class="sk-btn sk-btn-sm" type="submit" form="ur-confirm-use"><?= $h(sprintf(findtekst('5921|Tildel %s', $vm['sprogId']), perm_role_name($u['suggestion'], $vm['sprogId']))) ?></button>
          <?php } ?>
          <button class="sk-btn sk-btn-sm" type="submit" form="ur-confirm-keep"><?= $t('5920|Behold nuværende') ?></button>
        </span>
      </div>
      <?php } ?>

      <div class="sk-field">
        <label class="sk-field-lbl" for="ur-email"><?= $t('52|E-mail') ?></label>
        <input class="sk-input" type="email" name="email" id="ur-email" value="<?= $h($u['email']) ?>"<?= $inviteNew ? ' required' : '' ?><?= $ro ?>>
      </div>
      <div class="sk-field">
        <label class="sk-field-lbl" for="ur-brugernavn"><?= $t('225|Brugernavn') ?></label>
        <input class="sk-input" type="text" name="brugernavn" id="ur-brugernavn" maxlength="80" value="<?= $h($u['brugernavn']) ?>" required<?= $isNew ? $ro : ' readonly' ?>>
        <span class="sk-field-help"><?= $isNew ? $t('5923|Kan ikke ændres senere.') : $t('5924|Kan ikke ændres.') ?></span>
      </div>
      <?php if ($isNew && !$inviteNew) { ?>
      <div class="sk-field">
        <label class="sk-field-lbl"><?= $t('324|Adgangskode') ?></label>
        <input class="sk-input" type="password" name="kode" autocomplete="new-password" readonly data-nofill<?= $ro ?>>
      </div>
      <div class="sk-field">
        <label class="sk-field-lbl"><?= $t('328|Gentag adgangskode') ?></label>
        <input class="sk-input" type="password" name="kode2" autocomplete="new-password" readonly data-nofill<?= $ro ?>>
      </div>
      <?php } ?>
      <div class="sk-field">
        <label class="sk-field-lbl" for="ur-role"><?= $t('5553|Rolle') ?></label>
        <?php if ($ownCard || $lastAdmin) { ?><input type="hidden" name="role_id" value="<?= (int) $u['role_id'] ?>"><?php } ?>
        <select class="sk-input" name="role_id" id="ur-role"<?= ($ownCard || $lastAdmin) ? ' disabled' : $ro ?>>
          <?php if ($u['role_id'] === 0) { ?><option value="0" disabled selected><?= $t('5891|Vælg en rolle') ?></option><?php } ?>
          <?php foreach ($vm['roles'] as $role) {
          	$assignable = perm_within_own(perm_levels_from_role($role['id']));
          ?>
          <option value="<?= (int) $role['id'] ?>"<?= $role['id'] === $u['role_id'] ? ' selected' : '' ?><?= $assignable ? '' : ' disabled' ?>><?= $roleName($role) ?><?= $assignable ? '' : ' 🔒' ?></option>
          <?php } ?>
        </select>
        <span class="sk-field-help"><?= $ownCard ? $t('5761|Du kan ikke ændre din egen rolle') : ($lastAdmin ? $t('5926|Sidste administrator. Regnskabet skal have mindst én.') : $t('5925|Kun roller inden for dine egne rettigheder kan vælges.')) ?></span>
      </div>
      <div class="sk-field">
        <label class="sk-field-lbl" for="ur-ansat"><?= $t('5927|Kobling til ansat') ?></label>
        <select class="sk-input" name="ansat_id" id="ur-ansat"<?= $ro ?>>
          <option value="0">–</option>
          <?php foreach ($vm['employees'] as $e) { ?>
          <option value="<?= (int) $e['id'] ?>"<?= $e['id'] === $u['ansat_id'] ? ' selected' : '' ?>><?= $h(trim($e['initialer'] . ' ' . $e['navn'])) ?><?= !empty($e['left']) ? ' (' . $t('660|Fratrådt') . ')' : '' ?><?= (isset($e['logins']) && $e['logins'] > ($e['id'] === $u['ansat_id'] ? 1 : 0)) ? ' · ' . $t('7764|har login') : '' ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="sk-fields-2">
        <div class="sk-field">
          <label class="sk-field-lbl" for="ur-tfa"><?= $t('5928|Tofaktor') ?></label>
          <select class="sk-input" name="twofactor" id="ur-tfa"<?= $ro ?>>
            <option value="on"<?= $u['twofactor'] ? ' selected' : '' ?>><?= $t('5929|Til') ?></option>
            <option value=""<?= $u['twofactor'] ? '' : ' selected' ?>><?= $t('5930|Fra') ?></option>
          </select>
        </div>
        <div class="sk-field">
          <label class="sk-field-lbl"><?= $t('5557|Status') ?></label>
          <select class="sk-input" disabled><option><?= $isNew ? ($inviteNew ? $t('5778|Inviteret') : $t('5558|Aktiv')) : ($u['closed'] ? $t('5559|Lukket') : ($u['invited'] ? $t('5778|Inviteret') : $t('5558|Aktiv'))) ?></option></select>
        </div>
      </div>
      <div class="sk-field">
        <label class="sk-field-lbl" for="ur-tlf"><?= $t('37|Telefon') ?></label>
        <input class="sk-input" type="tel" name="tlf" id="ur-tlf" maxlength="16" value="<?= $h($u['tlf']) ?>"<?= $ro ?>>
        <span class="sk-field-help"><?= $t('5516|Tofaktor-login kræver et telefonnummer eller en e-mail') ?></span>
      </div>
      <div class="sk-field">
        <label class="sk-field-lbl" for="ur-ip"><?= $t('5931|IP-begrænsning') ?></label>
        <textarea class="sk-input ur-ip" name="ip_address" id="ur-ip" rows="2" placeholder="<?= $t('5932|Én adresse pr. linje. Tom = ingen begrænsning.') ?>"<?= $ro ?>><?= $h(str_replace(',', "\n", $u['ip'])) ?></textarea>
      </div>
      <label class="ur-checkrow"><input class="sk-check" type="checkbox" name="revisor" value="on"<?= $u['isRevisor'] ? ' checked' : '' ?><?= !empty($vm['revisorSet']) ? ' disabled' : $ro ?>><span><b><?= $t('5571|Denne bruger er regnskabets revisor') ?></b><small><?= $t('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang, og du kan ikke ændre hvilken bruger der er revisor uden at kontakte Saldi support.') ?></small></span></label>
      <?php if (!$isNew && $vm['canWrite']) { ?>
      <details class="ur-pw">
        <summary><?= $t('5935|Sæt ny adgangskode') ?></summary>
        <div class="sk-fields-2">
          <div class="sk-field"><label class="sk-field-lbl"><?= $t('324|Adgangskode') ?></label><input class="sk-input" type="password" name="kode" autocomplete="new-password" readonly data-nofill></div>
          <div class="sk-field"><label class="sk-field-lbl"><?= $t('328|Gentag adgangskode') ?></label><input class="sk-input" type="password" name="kode2" autocomplete="new-password" readonly data-nofill></div>
        </div>
      </details>
      <?php } ?>
      <div class="sk-field">
        <span class="sk-field-lbl" id="ur-access-lbl"><?= $t('5933|Adgang fra rollen') ?></span>
        <ul class="sk-kv ur-access" id="ur-access" aria-labelledby="ur-access-lbl" data-none="<?= $h(perm_level_label('none', $vm['sprogId'])) ?>" data-read="<?= $h(perm_level_label('read', $vm['sprogId'])) ?>" data-write="<?= $h(perm_level_label('write', $vm['sprogId'])) ?>">
          <?php foreach ($modules as $key => $label) { ?><li data-key="<?= $h($key) ?>"><span><?= $h($label) ?></span><span class="ur-lv-none"></span></li><?php } ?>
          <li data-admin hidden><span class="ur-lv-admin"><?= $t('5594|Farlige funktioner (kun administratorer som standard)') ?></span><span class="ur-lv-write"><?= $h(perm_level_label('write', $vm['sprogId'])) ?></span></li>
        </ul>
      </div>
      <script type="application/json" id="ur-access-data"><?= json_encode(array('admin' => $adminKeys, 'levels' => $vm['roleLevels'])) ?></script>
    </form>

    <?php if ($vm['canWrite']) { ?>
    <div class="sk-drawer-f">
      <?php if ($isNew) { ?>
      <span class="sk-grow"></span>
      <a class="sk-btn sk-btn-ghost" href="<?= $closeUrl ?>"><?= $t('5940|Annullér') ?></a>
      <button class="sk-btn sk-btn-primary" type="submit" form="ur-user-form"><i class='bx <?= $inviteNew ? 'bx-send' : 'bx-save' ?>'></i><?= $inviteNew ? $t('5795|Send invitation') : $t('5952|Opret') ?></button>
      <?php } else { ?>
      <?php if ($u['closed']) { ?>
      <button class="sk-btn" type="submit" form="ur-reopen-form"><?= $t('5912|Genåbn') ?></button>
      <?php } else { ?>
      <button class="sk-btn sk-btn-danger" type="button" id="ur-close-btn"<?= ($ownCard || $lastAdmin) ? ' disabled title="' . ($ownCard ? $t('5846|Du kan ikke lukke din egen bruger.') : $t('5926|Sidste administrator. Regnskabet skal have mindst én.')) . '"' : '' ?>><?= $t('5756|Luk bruger') ?></button>
      <?php } ?>
      <span class="sk-grow"></span>
      <?php if ($u['invited']) { ?>
      <button class="sk-btn" type="submit" form="ur-resend-form"><?= $t('5781|Send invitation igen') ?></button>
      <?php } elseif (!$u['closed'] && !$ownCard) { ?>
      <button class="sk-btn" type="submit" form="ur-reset-form"<?= $u['email'] === '' ? ' disabled title="' . $t('5864|Brugeren har ingen gyldig e-mail') . '"' : '' ?> data-confirm-form="<?= $t('5867|Send en midlertidig adgangskode til brugerens e-mail?') ?>"><?= $t('5868|Nulstil adgangskode') ?></button>
      <?php } ?>
      <button class="sk-btn sk-btn-primary" type="submit" form="ur-user-form"><?= $t('3|Gem') ?></button>
      <?php } ?>
    </div>
    <?php } ?>
  </aside>

  <?php if ($vm['canWrite'] && !$isNew) { ?>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-resend-form" hidden><input type="hidden" name="action" value="resend_invite"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-reset-form" hidden><input type="hidden" name="action" value="reset_password"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-reopen-form" hidden><input type="hidden" name="action" value="reopen_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-close-form" hidden><input type="hidden" name="action" value="close_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-delete-form" hidden><input type="hidden" name="action" value="delete_user"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"></form>
  <?php if ($u['review']) { ?>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-confirm-use" hidden><input type="hidden" name="action" value="confirm_role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="use" value="1"></form>
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-confirm-keep" hidden><input type="hidden" name="action" value="confirm_role"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="use" value="0"></form>
  <?php } ?>
  <template id="ur-close-dlg">
    <h3><?= $h(sprintf(findtekst('5936|Luk %s?', $vm['sprogId']), $u['navn'])) ?></h3>
    <p><?= $t('5937|Brugeren kan ikke logge ind og tæller ikke i abonnementet. Historik og kontrolspor bevares, og brugeren kan genåbnes.') ?><?php if ($u['ansat_id'] > 0) { ?><br><br><?= $h(sprintf(findtekst('7761|Lukkes brugeren, markeres den koblede ansatte, %s, også som fratrådt.', $vm['sprogId']), $u['navn'])) ?><?php } ?><?php if (!$u['hasLoggedIn']) { ?><br><br><?= $t('5938|Brugeren har aldrig logget ind og kan derfor også slettes helt.') ?><?php } ?></p>
    <div data-buttons>
      <?php if (!$u['hasLoggedIn']) { ?><button type="submit" class="sk-btn sk-btn-danger" form="ur-delete-form"><?= $t('5939|Slet helt') ?></button><?php } ?>
      <button type="submit" class="sk-btn sk-btn-danger" form="ur-close-form"><?= $t('5756|Luk bruger') ?></button>
    </div>
  </template>
  <?php } ?>
  <?php if ($inviteNew) { ?>
  <script>
  (function () {
  	var mail = document.getElementById('ur-email'), name = document.getElementById('ur-brugernavn'), touched = false;
  	if (!mail || !name) { return; }
  	name.addEventListener('input', function () { touched = true; });
  	mail.addEventListener('input', function () { if (!touched) { name.value = mail.value.split('@')[0].slice(0, 80); } });
  })();
  </script>
  <?php } ?>
	<?php
}

function ur_view_roles(array $vm, callable $h, callable $t, callable $link, callable $roleName): void
{
	$role = $vm['editRole'];
	$write = $vm['canRolesWrite'];
	?>
  <div class="sk-split ur-roles" style="--sk-side-w: 18rem">
    <section class="sk-panel ur-rlist">
      <div class="sk-panel-h">
        <h2 class="sk-panel-t"><?= $t('5550|Roller') ?></h2>
        <?php if ($write) { ?>
        <span class="sk-panel-tools">
          <?php if ($role && $role['id'] > 0) { ?><button class="sk-btn sk-btn-sm" type="submit" form="ur-copy-form"><i class='bx bx-copy'></i><?= $t('5566|Kopiér') ?></button><?php } ?>
          <a class="sk-btn sk-btn-sm" href="<?= $link('tab=roles&rolle=0') ?>"><i class='bx bx-plus'></i><?= $t('5565|Ny rolle') ?></a>
        </span>
        <?php } ?>
      </div>
      <div class="sk-list sk-list-stack">
      <?php foreach ($vm['roles'] as $r) {
      	$count = isset($vm['roleCounts'][$r['id']]) ? $vm['roleCounts'][$r['id']] : 0;
      	$isOn = ($role && $role['id'] === $r['id']);
      ?>
        <a class="sk-list-row<?= $isOn ? ' on' : '' ?>" href="<?= $link('tab=roles&rolle=' . $r['id']) ?>"<?= $isOn ? ' aria-current="true"' : '' ?>>
          <span class="sk-list-tx"><span class="sk-list-t"><?= $roleName($r) ?><?php if (!$r['system']) { ?> <span class="sk-tag sk-warn"><?= $t('5942|Egen') ?></span><?php } ?></span><span class="sk-list-d"><?= $h(perm_role_description($r, $vm['sprogId'])) ?></span></span>
          <span class="sk-list-meta"><?= (int) $count ?> <i class='bx bx-user'></i></span>
        </a>
      <?php } ?>
      </div>
    </section>

    <?php if ($role) {
    	$isNew = ($role['id'] === 0);
    	$count = (!$isNew && isset($vm['roleCounts'][$role['id']])) ? $vm['roleCounts'][$role['id']] : 0;
    	$name = $isNew ? '' : perm_role_name($role, $vm['sprogId']);
    	$rank = perm_level_rank();
    	$registry = permission_registry();
    ?>
    <div class="sk-main">
      <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-role-form" class="sk-panel ur-matrix" autocomplete="off">
        <input type="hidden" name="action" value="save_role">
        <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
        <div class="sk-panel-h">
          <?php if ($role['system']) { ?>
          <h2 class="sk-panel-t"><?= $h($name) ?></h2><input type="hidden" name="navn" value="<?= $h($role['navn']) ?>">
          <span class="sk-sub ur-lock"><i class='bx bx-lock-alt'></i><?= $t('5943|Standardrolle · navn låst') ?></span>
          <?php } else { ?>
          <input class="sk-input sk-input-sm ur-m-name" type="text" name="navn" maxlength="80" value="<?= $h($role['navn']) ?>" placeholder="<?= $t('5531|Navn') ?>" aria-label="<?= $t('5531|Navn') ?>" required<?= $write ? '' : ' disabled' ?>>
          <?php } ?>
          <span class="sk-panel-tools">
          <?php if ($write && !$isNew) { ?>
            <?php if ($role['system']) { ?>
            <button class="sk-btn sk-btn-sm" type="submit" form="ur-reset-role-form"<?= ($vm['roleChanged'] && perm_within_own(perm_levels_from_role((int) $role['id']))) ? '' : ' disabled' ?> data-confirm-form="<?= $t('5767|Nulstil rollen til standard?') ?>"><i class='bx bx-reset'></i><?= $t('5717|Nulstil til standard') ?></button>
            <?php } else { ?>
            <button class="sk-btn sk-btn-sm sk-btn-danger" type="button" id="ur-del-role"><i class='bx bx-trash'></i><?= $t('1099|Slet') ?></button>
            <?php } ?>
          <?php } ?>
          <?php if ($write) { ?><button class="sk-btn sk-btn-sm sk-btn-primary" type="submit"><?= $t('3|Gem') ?></button><?php } ?>
          </span>
        </div>
        <div class="sk-panel-note ur-m-desc">
          <?php if ($role['system']) { ?>
          <?= $h(perm_role_description($role, $vm['sprogId'])) ?><input type="hidden" name="beskrivelse" value="<?= $h($role['beskrivelse']) ?>">
          <?php } else { ?>
          <input class="sk-input sk-input-sm" type="text" name="beskrivelse" maxlength="500" value="<?= $h($role['beskrivelse']) ?>" placeholder="<?= $t('5568|Beskrivelse') ?>" aria-label="<?= $t('5568|Beskrivelse') ?>"<?= $write ? '' : ' disabled' ?>>
          <?php } ?>
          <?php if (!$isNew) { ?> · <?= $count > 0 ? $h(sprintf(findtekst('5944|Bruges af %s brugere. Ændringer gælder fra deres næste sidevisning.', $vm['sprogId']), $count)) : $t('5945|Ingen brugere har denne rolle.') ?><?php } ?>
        </div>
        <div class="sk-table-wrap">
          <table class="sk-table sk-table-flush ur-mx">
            <thead><tr><th></th><th class="sk-c ur-lvh"><?= $h(perm_level_label('none', $vm['sprogId'])) ?></th><th class="sk-c ur-lvh"><?= $h(perm_level_label('read', $vm['sprogId'])) ?></th><th class="sk-c ur-lvh"><?= $h(perm_level_label('write', $vm['sprogId'])) ?></th></tr></thead>
            <?php foreach (permission_groups() as $group => $groupLabel) {
            	$keys = array_filter($registry, function ($def) use ($group) { return $def['group'] === $group; });
            	if (!$keys) {
            		continue;
            	}
            ?>
            <tbody class="ur-grp" data-grp>
              <tr class="sk-tr-sub<?= $group === 'settings' ? ' sk-bad' : '' ?>"><td colspan="4"><div class="ur-grp-h"><span><?= $t($groupLabel) ?><?php if ($group === 'settings') { ?> <span class="ur-grp-d">· <?= $t('5594|Farlige funktioner (kun administratorer som standard)') ?></span><?php } ?></span><?php if ($write) { $allRead = !array_filter($keys, function ($d) { return empty($d['readonly']); }); ?><button type="button" class="sk-link" data-all-write><?= $t($allRead ? '7071|Sæt alle til læs' : '5946|Sæt alle til skriv') ?></button><?php } ?></div></td></tr>
              <?php foreach ($keys as $key => $def) {
              	$level = isset($role['levels'][$key]) ? $role['levels'][$key] : 'none';
              	$own = isset($vm['ownLevels'][$key]) ? $vm['ownLevels'][$key] : 'none';
              	$adminLock = ($role['key'] === 'administrator' && in_array($key, UR_ADMIN_LOCKED, true));
              ?>
              <tr class="ur-prow<?= !empty($def['block']) ? ' ur-prow-block' : '' ?>"<?= $key === 'dashboard.view' ? ' data-view-row' : '' ?><?= $adminLock ? ' title="' . $t('5971|Administrator-rollen beholder altid adgang til brugere og roller') . '"' : '' ?><?= !empty($def['derive_keys']) ? ' data-derive="' . $h(implode(',', $def['derive_keys'])) . '"' : '' ?>>
                <td class="sk-wrap ur-plbl"><?= $t($def['label']) ?><?php if ($def['dangerous']) { ?> <i class='bx bx-lock-alt ur-plock'></i><?php } ?><?php if (!empty($def['block'])) { ?> <small class="ur-block-hint" hidden><?= $t('7070|Brugeren kan se tallene her, men ikke åbne rapporten bag.') ?></small><small class="ur-block-needs" hidden><?= $t('7069|Kræver Se Oversigt') ?></small><?php } ?></td>
                <?php foreach (array('none', 'read', 'write') as $opt) {
                	$locked = ($rank[$opt] > $rank[$own]) || ($adminLock && $opt !== 'write') || ($opt === 'write' && !empty($def['readonly']));
                ?>
                <td class="sk-c ur-lv"><input class="sk-radio" type="radio" name="level[<?= $h($key) ?>]" value="<?= $opt ?>" aria-label="<?= $t($def['label']) ?>: <?= $h(perm_level_label($opt, $vm['sprogId'])) ?>"<?= $level === $opt ? ' checked' : '' ?><?= ($locked || !$write) ? ' disabled' : '' ?>></td>
                <?php } ?>
              </tr>
              <?php } ?>
            </tbody>
            <?php } ?>
          </table>
        </div>
      </form>
      <?php if ($write && !$isNew) { ?>
      <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-copy-form" hidden><input type="hidden" name="action" value="copy_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"></form>
      <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-reset-role-form" hidden><input type="hidden" name="action" value="reset_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"></form>
      <?php if (!$role['system']) { ?>
      <template id="ur-del-role-dlg">
        <?php if ($count > 0) { ?>
        <h3><?= $h(sprintf(findtekst('5948|%s bruges af %s brugere', $vm['sprogId']), $name, $count)) ?></h3>
        <p><?= $t('5949|Flyt brugerne til en anden rolle først.') ?></p>
        <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-del-role-form">
          <input type="hidden" name="action" value="delete_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
          <div class="sk-field"><label class="sk-field-lbl" for="ur-move-to"><?= $t('5950|Flyt til') ?></label>
            <select class="sk-input" name="move_to" id="ur-move-to">
              <?php foreach ($vm['roles'] as $r) {
              	if ($r['id'] === $role['id'] || !perm_within_own(perm_levels_from_role($r['id']))) {
              		continue;
              	}
              ?><option value="<?= (int) $r['id'] ?>"><?= $roleName($r) ?></option><?php } ?>
            </select>
          </div>
        </form>
        <div data-buttons><button type="submit" class="sk-btn sk-btn-danger" form="ur-del-role-form"><?= $t('5951|Flyt og slet') ?></button></div>
        <?php } else { ?>
        <h3><?= $t('5586|Slet rolle?') ?> <?= $h($name) ?></h3>
        <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-del-role-form"><input type="hidden" name="action" value="delete_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"></form>
        <div data-buttons><button type="submit" class="sk-btn sk-btn-danger" form="ur-del-role-form"><?= $t('1099|Slet') ?></button></div>
        <?php } ?>
      </template>
      <?php } ?>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
	<?php
}

function ur_view_log(array $vm, callable $h, callable $t, callable $link): void
{
	$mode = $vm['enforceMode'];
	$f = $vm['logFilter'];
	$types = array(
		'login'      => '5810|Login og logud',
		'session'    => '5811|Sessioner',
		'user'       => '5549|Brugere',
		'role'       => '5550|Roller',
		'permission' => '5812|Rettigheder og sikkerhed',
		'setting'    => '122|Indstillinger',
	);
	?>
  <section class="sk-panel">
    <form method="get" action="usersRoles.php" class="sk-bar ur-log-bar">
      <?php if (strpos($vm['linkPrefix'], 'inframe=1') !== false) { ?><input type="hidden" name="inframe" value="1"><?php } ?>
      <input type="hidden" name="tab" value="log">
      <label class="sk-search"><i class='bx bx-search'></i><input class="sk-input" type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="<?= $t('5802|Søg i brugernavn og objekt…') ?>"></label>
      <select class="sk-input" name="type" aria-label="<?= $t('5582|Handling') ?>">
        <option value=""><?= $t('5800|Alle handlinger') ?></option>
        <?php foreach ($types as $key => $label) { ?><option value="<?= $h($key) ?>"<?= (!$f['wd'] && $key === $f['type']) ? ' selected' : '' ?>><?= $t($label) ?></option><?php } ?>
        <option value="wd"<?= $f['wd'] ? ' selected' : '' ?>><?= $t('5600|Ville være afvist') ?></option>
      </select>
      <select class="sk-input" name="bruger" aria-label="<?= $t('225|Brugernavn') ?>">
        <option value=""><?= $t('5799|Alle brugere') ?></option>
        <?php foreach ($vm['logOptions']['users'] as $name) { ?><option value="<?= $h($name) ?>"<?= $name === $f['bruger'] ? ' selected' : '' ?>><?= $h($name) ?></option><?php } ?>
      </select>
      <?php if ($vm['logOptions']['objects']) { ?>
      <select class="sk-input" name="objekt" aria-label="<?= $t('5806|Objekt') ?>">
        <option value=""><?= $t('5801|Alle objekter') ?></option>
        <?php foreach ($vm['logOptions']['objects'] as $o) { ?><option value="<?= $h($o) ?>"<?= $o === $f['objekt'] ? ' selected' : '' ?>><?= $h($o) ?></option><?php } ?>
      </select>
      <?php } ?>
      <input class="sk-input ur-date" type="date" name="fra" value="<?= $h($f['fra']) ?>" aria-label="<?= $t('5797|Fra') ?>" title="<?= $t('5797|Fra') ?>">
      <input class="sk-input ur-date" type="date" name="til" value="<?= $h($f['til']) ?>" aria-label="<?= $t('5798|Til') ?>" title="<?= $t('5798|Til') ?>">
      <button class="sk-btn" type="submit"><i class='bx bx-filter-alt'></i><?= $t('5803|Filtrér') ?></button>
      <a class="sk-btn sk-btn-ghost" href="<?= $link('tab=log') ?>"><?= $t('5804|Nulstil filtre') ?></a>
      <?php if ($vm['canExport']) { ?><span class="sk-bar-sep"></span><a class="sk-btn" href="<?= $link(ur_log_query($f, array('side' => 0)) . '&export=csv') ?>" download><i class='bx bx-download'></i><?= $t('5805|Eksportér CSV') ?></a><?php } ?>
    </form>
    <div class="sk-table-wrap">
      <table class="sk-table sk-table-flush ur-aud">
        <thead><tr><th><?= $t('5581|Tidspunkt') ?></th><th><?= $t('225|Brugernavn') ?></th><th><?= $t('5582|Handling') ?></th><th><?= $t('5806|Objekt') ?></th><th><?= $t('5583|Detaljer') ?></th><th>IP</th></tr></thead>
        <tbody>
          <?php foreach ($vm['log'] as $row) {
          	$handling = (string) $row['handling'];
          	$label = ur_action_label($handling);
          	$details = (string) $row['detaljer'];
          	if (!empty($row['setting_key'])) {
          		$details = trim((string) $row['setting_key'] . ': ' . (string) ifset($row, 'old_value', '') . ' → ' . (string) ifset($row, 'new_value', '') . ' ' . $details);
          	}
          	$warn = (strpos($handling, 'permission') === 0 || strpos($handling, 'failed') !== false || in_array($handling, array('denied', 'csrf', 'would-deny', 'login.ip_blocked', 'unguarded'), true));
          ?>
          <tr>
            <td class="sk-muted"><?= $h(function_exists('st_local_time') ? st_local_time((string) $row['tidspunkt'], 'j/n-Y H:i') : substr((string) $row['tidspunkt'], 0, 16)) ?></td>
            <td><?= $h($row['brugernavn']) ?></td>
            <td><span class="sk-tag<?= $warn ? ' sk-warn' : '' ?>" title="<?= $h($handling) ?>"><?= $label !== '' ? $t($label) : $h($handling) ?></span></td>
            <td><?= $h(ur_log_object($row, $vm['userNames'])) ?></td>
            <td class="sk-muted ur-det"><?php if (mb_strlen($details) > 70) { ?><details><summary><?= $t('5959|Vis') ?></summary><pre><?= $h($details) ?></pre></details><?php } else { ?><?= $h($details) ?><?php } ?></td>
            <td class="sk-muted"><?= $h($row['ip']) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <?php if (!$vm['log']) { ?><div class="sk-empty"><?= $t('5584|Ingen hændelser endnu') ?></div><?php } ?>
    </div>
    <div class="sk-foot">
      <span><?= $h(sprintf(findtekst('5845|Viser %s–%s', $vm['sprogId']), $vm['log'] ? $f['side'] * UR_LOG_PAGE + 1 : 0, $f['side'] * UR_LOG_PAGE + count($vm['log']))) ?></span>
      <?php if ($f['side'] > 0 || $vm['logMore']) { ?>
      <span class="sk-grow"></span>
      <span class="ur-pager">
        <?php if ($f['side'] > 0) { ?><a class="sk-btn sk-btn-sm" href="<?= $link(ur_log_query($f, array('side' => $f['side'] - 1))) ?>"><i class='bx bx-chevron-left'></i><?= $t('5808|Nyere') ?></a><?php } ?>
        <?php if ($vm['logMore']) { ?><a class="sk-btn sk-btn-sm" href="<?= $link(ur_log_query($f, array('side' => $f['side'] + 1))) ?>"><?= $t('5807|Ældre') ?><i class='bx bx-chevron-right'></i></a><?php } ?>
      </span>
      <?php } ?>
    </div>
  </section>

  <?php if ($vm['canRoles']) { ?>
  <details class="sk-panel ur-enforce"<?= $mode === 'deny' ? ' open' : '' ?>>
    <summary class="sk-panel-h"><i class='bx bx-lock-alt ur-enf-ic'></i><h2 class="sk-panel-t"><?= $t('5595|Håndhævelse') ?></h2><span class="sk-n"><?= count($vm['unguarded']) + count($vm['wouldDeny']) ?></span><span class="sk-panel-tools"><i class='bx bx-chevron-down ur-chev'></i></span></summary>
    <p class="sk-panel-note"><?= $t('5601|Skift til "Afvis" først, når listen over sider uden nøgle er tom, og alle "ville være afvist"-hændelser er forventede.') ?></p>
    <p class="sk-panel-note"><?= $t('7099|Læseadgang håndhæves kun i "Afvis": i "Log kun" kan en bruger med læseadgang stadig ændre på de sider, der endnu bruger den gamle rettighedskontrol.') ?></p>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-enforce-form">
      <input type="hidden" name="action" value="set_enforce">
      <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="mode" value="log"<?= $mode === 'log' ? ' checked' : '' ?><?= $vm['canRolesWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5596|Log kun (intet blokeres)') ?></b></span></label>
      <label class="sk-check-lbl"><input class="sk-radio" type="radio" name="mode" value="deny"<?= $mode === 'deny' ? ' checked' : '' ?><?= $vm['canRolesWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5597|Afvis (standard-afvis er slået til)') ?></b></span></label>
      <?php if ($vm['canRolesWrite']) { ?><button class="sk-btn sk-btn-sm" type="submit"><?= $t('3|Gem') ?></button><?php } ?>
    </form>
    <div class="ur-enforce-lists">
      <div>
        <h4><?= $t('5598|Sider uden rettighedsnøgle set i logperioden') ?> <span class="sk-n"><?= count($vm['unguarded']) ?></span></h4>
        <?php if ($vm['unguarded']) { ?>
        <ul class="ur-list"><?php foreach ($vm['unguarded'] as $r) { ?><li><code><?= $h($r['detaljer']) ?></code><span class="sk-muted"><?= (int) $r['antal'] ?> · <?= $h(substr((string) $r['sidst'], 0, 16)) ?></span></li><?php } ?></ul>
        <?php } else { ?><p class="sk-muted ur-none">–</p><?php } ?>
      </div>
      <div>
        <h4><?= $t('5600|Ville være afvist') ?> <span class="sk-n"><?= count($vm['wouldDeny']) ?></span></h4>
        <?php if ($vm['wouldDeny']) { ?>
        <ul class="ur-list"><?php foreach ($vm['wouldDeny'] as $r) { ?><li><code><?= $h($r['detaljer']) ?></code><span class="sk-muted"><?= (int) $r['antal'] ?> · <?= $h(substr((string) $r['sidst'], 0, 16)) ?></span></li><?php } ?></ul>
        <?php } else { ?><p class="sk-muted ur-none">–</p><?php } ?>
      </div>
    </div>
  </details>
  <?php } ?>
	<?php
}

/**
 * Behaviour of the page: list filters, sorting and pages, the bulk bar, dialogs, the drawer.
 */
function ur_view_script(array $vm): void
{
	?>
<script>
	/* Oversigt (front-page addendum 4 §3): the block rows need Se Oversigt; a block without its module key gets a note. */
	window.urDashRows = function () {
		var first = document.querySelector('.ur-prow-block'), form = first && first.closest('form'); if (!form) return;
		var lv = function (k) { var c = form.querySelector('input[name="level[' + k + ']"]:checked'); return c ? c.value : 'none'; };
		var viewOn = lv('dashboard.view') !== 'none';
		form.querySelectorAll('.ur-prow-block').forEach(function (row) {
			row.classList.toggle('ur-prow-off', !viewOn);
			var needs = row.querySelector('.ur-block-needs'); if (needs) needs.hidden = viewOn;
			row.querySelectorAll('input[type="radio"]').forEach(function (r) { if (!r.dataset.lockedByOwn) r.dataset.lockedByOwn = r.disabled ? '1' : '0'; r.disabled = r.dataset.lockedByOwn === '1'; r.readOnly = !viewOn; r.tabIndex = viewOn ? 0 : -1; r.setAttribute('aria-disabled', viewOn ? 'false' : 'true'); });
			var hint = row.querySelector('.ur-block-hint'), from = (row.dataset.derive || '').split(',').filter(Boolean), on = row.querySelector('input[type="radio"]:checked');
			if (hint) { var rowOn = viewOn && on && on.value !== 'none'; var anyModule = from.indexOf('*') >= 0 || from.some(function (k) { return lv(k) !== 'none'; }); hint.hidden = !(rowOn && !anyModule); }
		});
	};
	document.addEventListener('change', function (e) { if (e.target && e.target.name && e.target.name.indexOf('level[') === 0) window.urDashRows(); });
	window.urDashRows();
(function () {
	// ---------------------------------------------------------------- dialog
	var dlg = document.getElementById('ur-dlg');
	function openDialog(title, bodyHtml, buttons) {
		document.getElementById('ur-dlg-title').textContent = title;
		var body = document.getElementById('ur-dlg-body');
		body.innerHTML = bodyHtml;
		var row = document.getElementById('ur-dlg-row');
		row.innerHTML = '';
		row.appendChild(document.getElementById('ur-dlg-cancel').content.cloneNode(true));
		(buttons || []).forEach(function (b) { row.appendChild(b); });
		dlg.hidden = false;
		var focus = row.querySelector('button:last-child');
		if (focus) { focus.focus(); }
	}
	function openTemplate(id) {
		var tpl = document.getElementById(id);
		if (!tpl) { return; }
		var frag = tpl.content.cloneNode(true);
		var h = frag.querySelector('h3'), btns = frag.querySelector('[data-buttons]');
		var title = h ? h.textContent : '';
		if (h) { h.remove(); }
		var buttons = btns ? Array.prototype.slice.call(btns.children) : [];
		if (btns) { btns.remove(); }
		var wrap = document.createElement('div');
		wrap.appendChild(frag);
		openDialog(title, '', buttons);
		document.getElementById('ur-dlg-body').appendChild(wrap);
	}
	function closeDialog() { dlg.hidden = true; }
	dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target.closest('[data-dlg-close]')) { closeDialog(); } });
	function confirmThen(text, danger, onOk) {
		var ok = document.createElement('button');
		ok.type = 'button';
		ok.className = 'sk-btn ' + (danger ? 'sk-btn-danger' : 'sk-btn-primary');
		ok.textContent = 'OK';
		ok.addEventListener('click', function () { closeDialog(); onOk(); });
		openDialog(text, '', [ok]);
	}
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Escape') { return; }
		if (!dlg.hidden) { closeDialog(); return; }
		var drawer = document.getElementById('ur-drawer');
		if (drawer) { window.location.href = drawer.dataset.close; }
	});
	// Buttons that submit a form after a question.
	document.querySelectorAll('[data-confirm-form]').forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.preventDefault();
			var form = document.getElementById(b.getAttribute('form'));
			confirmThen(b.dataset.confirmForm, false, function () { form.submit(); });
		});
	});
	var closeBtn = document.getElementById('ur-close-btn');
	if (closeBtn) { closeBtn.addEventListener('click', function () { openTemplate('ur-close-dlg'); }); }
	var delRole = document.getElementById('ur-del-role');
	if (delRole) { delRole.addEventListener('click', function () { openTemplate('ur-del-role-dlg'); }); }

	// ---------------------------------------------------------------- users list
	var search = document.getElementById('ur-search');
	var statusFilter = document.getElementById('ur-status-filter');
	var roleFilter = document.getElementById('ur-role-filter');
	var tfaFilter = document.getElementById('ur-2fa-filter');
	var reviewFilter = document.getElementById('ur-review-filter');
	var tbody = document.querySelector('#ur-users tbody');
	var pager = document.getElementById('ur-pager');
	var PAGE = 50, page = 0;
	function rows() { return Array.prototype.slice.call(document.querySelectorAll('#ur-users tbody tr[data-search]')); }
	function applyFilters(keepPage) {
		if (!tbody) { return; }
		if (keepPage !== true) { page = 0; }
		var q = search ? search.value.toLowerCase() : '';
		var st = statusFilter ? statusFilter.value : '';
		var rl = roleFilter ? roleFilter.value : '';
		var tf = tfaFilter ? tfaFilter.value : '';
		var rv = reviewFilter ? reviewFilter.value : '';
		var all = rows();
		var hits = all.filter(function (tr) {
			tr.hidden = true;
			return tr.dataset.search.indexOf(q) !== -1 && (st === '' || tr.dataset.status === st)
				&& (rl === '' || tr.dataset.role === rl) && (tf === '' || tr.dataset.tfa === tf) && (rv === '' || tr.dataset.review === '1');
		});
		var pages = Math.max(1, Math.ceil(hits.length / PAGE));
		if (page >= pages) { page = pages - 1; }
		hits.forEach(function (tr, i) { tr.hidden = i < page * PAGE || i >= (page + 1) * PAGE; });
		var empty = document.getElementById('ur-empty');
		if (empty) { empty.hidden = hits.length > 0; }
		var foot = document.getElementById('ur-foot-text');
		if (foot) { foot.textContent = foot.dataset.format.replace('%s', hits.length).replace('%s', all.length); }
		if (pager) {
			pager.hidden = hits.length <= PAGE;
			var txt = document.getElementById('ur-pager-text');
			txt.textContent = txt.dataset.format.replace('%s', page * PAGE + 1).replace('%s', Math.min(hits.length, (page + 1) * PAGE)).replace('%s', hits.length);
			pager.querySelector('[data-page="-1"]').disabled = page === 0;
			pager.querySelector('[data-page="1"]').disabled = page >= pages - 1;
		}
	}
	if (pager) {
		pager.addEventListener('click', function (e) {
			var b = e.target.closest('[data-page]');
			if (b) { page += parseInt(b.dataset.page, 10); applyFilters(true); }
		});
	}
	[statusFilter, reviewFilter].forEach(function (el) { if (el && el.dataset.initial) { el.value = el.dataset.initial; } });
	[search, statusFilter, roleFilter, tfaFilter, reviewFilter].forEach(function (el) {
		if (el) { el.addEventListener(el === search ? 'input' : 'change', applyFilters); }
	});
	document.querySelectorAll('#ur-users th[data-sort]').forEach(function (th) {
		th.addEventListener('click', function () {
			var dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
			document.querySelectorAll('#ur-users th[data-sort]').forEach(function (o) { o.removeAttribute('aria-sort'); });
			th.setAttribute('aria-sort', dir);
			var col = th.cellIndex;
			var val = function (tr) { var td = tr.cells[col]; return (td.dataset.v !== undefined ? td.dataset.v : td.textContent).trim().toLowerCase(); };
			rows().sort(function (a, b) { return (dir === 'ascending' ? 1 : -1) * val(a).localeCompare(val(b), undefined, { numeric: true }); })
				.forEach(function (tr) { tbody.appendChild(tr); });
			applyFilters(true);
		});
	});
	applyFilters();

	// ---------------------------------------------------------------- bulk bar
	// A bulk action is only offered when it applies to at least one ticked user, and the
	// question counts only those users.
	function applies(tr, rule) {
		var st = tr.dataset.status, self = tr.dataset.self === '1';
		if (rule === 'notself') { return !self; }
		if (rule === 'open') { return !self && st !== 'closed'; }
		if (rule === 'review') { return tr.dataset.review === '1' && !self; }
		return st === rule;
	}
	function checked() { return Array.prototype.slice.call(document.querySelectorAll('#ur-users tbody input[name="ids[]"]:checked')); }
	function applicable(rule) { return checked().filter(function (cb) { return applies(cb.closest('tr'), rule); }).length; }
	function refreshBulk() {
		var bar = document.getElementById('ur-bulkbar');
		if (!bar) { return; }
		var n = checked().length;
		bar.hidden = n === 0;
		document.getElementById('ur-bulk-n').textContent = n;
		bar.querySelectorAll('[data-applies]').forEach(function (b) { b.disabled = applicable(b.dataset.applies) === 0; });
		checked().forEach(function (cb) { cb.closest('tr').classList.add('sel'); });
		document.querySelectorAll('#ur-users tbody input[name="ids[]"]:not(:checked)').forEach(function (cb) { cb.closest('tr').classList.remove('sel'); });
	}
	document.querySelectorAll('[data-bulk-confirm]').forEach(function (b) {
		b.addEventListener('click', function (e) {
			e.preventDefault();
			var n = b.dataset.applies ? applicable(b.dataset.applies) : checked().length;
			if (n === 0) { return; }
			confirmThen(b.dataset.bulkConfirm + ' (' + n + ')?', b.dataset.danger === '1', function () {
				var form = document.getElementById('ur-bulk');
				var hidden = document.createElement('input');
				hidden.type = 'hidden'; hidden.name = 'bulk'; hidden.value = b.value;
				form.appendChild(hidden);
				form.submit();
			});
		});
	});
	document.addEventListener('change', function (e) { if (e.target.name === 'ids[]') { refreshBulk(); } });
	var all = document.getElementById('ur-check-all');
	if (all) {
		all.addEventListener('change', function () {
			document.querySelectorAll('#ur-users tbody tr:not([hidden]) input[name="ids[]"]').forEach(function (cb) { cb.checked = all.checked; });
			refreshBulk();
		});
	}
	var clear = document.getElementById('ur-bulk-clear');
	if (clear) {
		clear.addEventListener('click', function () {
			checked().forEach(function (cb) { cb.checked = false; });
			if (all) { all.checked = false; }
			refreshBulk();
		});
	}
	refreshBulk();

	// Suggested role from the list: confirm, then assign it.
	document.querySelectorAll('[data-suggest]').forEach(function (b) {
		b.addEventListener('click', function () {
			var form = document.getElementById('ur-suggest-form');
			var ok = document.createElement('button');
			ok.type = 'button';
			ok.className = 'sk-btn sk-btn-primary';
			ok.textContent = b.dataset.dlgOk;
			ok.addEventListener('click', function () { form.elements.id.value = b.dataset.suggest; form.submit(); });
			var p = document.createElement('p');
			p.textContent = b.dataset.dlgBody;
			openDialog(b.dataset.dlgTitle, '', [ok]);
			document.getElementById('ur-dlg-body').appendChild(p);
		});
	});

	// ---------------------------------------------------------------- drawer
	// Browsers fill a saved login into the password fields of another user's card; the fields
	// stay read-only until the administrator clicks into them.
	document.querySelectorAll('input[data-nofill]').forEach(function (i) {
		i.addEventListener('focus', function () { i.removeAttribute('readonly'); }, { once: true });
	});
	var access = document.getElementById('ur-access'), accessData = document.getElementById('ur-access-data'), rolePick = document.getElementById('ur-role');
	if (access && accessData && rolePick) {
		var data = JSON.parse(accessData.textContent);
		var render = function () {
			var levels = data.levels[rolePick.value] || {};
			access.querySelectorAll('[data-key]').forEach(function (row) {
				var lv = levels[row.dataset.key] || 'none';
				var cell = row.lastElementChild;
				cell.className = 'ur-lv-' + lv;
				cell.textContent = access.dataset[lv];
			});
			access.querySelector('[data-admin]').hidden = !data.admin.some(function (k) { return levels[k] === 'write'; });
		};
		rolePick.addEventListener('change', render);
		render();
	}

	// ---------------------------------------------------------------- role matrix
	document.querySelectorAll('[data-all-write]').forEach(function (b) {
		b.addEventListener('click', function () {
			b.closest('[data-grp]').querySelectorAll('input[type="radio"][value="write"]:not(:disabled)').forEach(function (r) { r.checked = true; });
			b.closest('[data-grp]').querySelectorAll('.ur-prow-block input[type="radio"][value="read"]:not(:disabled), .ur-prow[data-view-row] input[type="radio"][value="read"]:not(:disabled)').forEach(function (r) { r.checked = true; });
			if (window.urDashRows) window.urDashRows();
		});
	});
})();
</script>
	<?php
}

/**
 * Result of a bulk action: how many were really changed, and why the rest were not.
 *
 * @param array<string, int> $why reason => number of users
 * @return array{type: string, text: string}
 */
function ur_bulk_flash(string $act, int $done, array $why, int $sprog): array
{
	$doneText = array(
		'close'  => '5849|%s brugere er lukket.',
		'reopen' => '5850|%s brugere er genåbnet.',
		'resend' => '5851|%s invitationer er sendt igen.',
		'role'   => '5852|%s brugere har fået ny rolle.',
		'suggest'=> '5888|%s brugere er bekræftet.',
	);
	$reasonText = array(
		'already'    => array('close' => '5854|%s var allerede lukket.', 'reopen' => '5855|%s var allerede aktive.'),
		'unchanged'  => '5856|%s havde allerede rollen.',
		'notreview'  => '5894|%s var allerede bekræftet.',
		'notinvited' => '5857|%s er ikke inviteret og fik ingen invitation.',
		'mailfailed' => '5858|%s invitationer kunne ikke sendes. Åbn brugeren for at få linket.',
		'self'       => '5846|Du kan ikke lukke din egen bruger.',
		'ownrole'    => '5761|Du kan ikke ændre din egen rolle',
		'lastadmin'  => '5760|Regnskabet skal have mindst én administrator.',
		'escalation' => '5577|Du kan ikke tildele flere rettigheder, end du selv har',
		'above'      => '5968|Du kan ikke ændre brugere med flere rettigheder end dig selv',
	);
	$act = isset($doneText[$act]) ? $act : 'role';
	$parts = array($done > 0 ? sprintf(findtekst($doneText[$act], $sprog), $done) : findtekst('5853|Ingen brugere blev ændret.', $sprog));
	foreach ($why as $reason => $count) {
		if (!isset($reasonText[$reason])) {
			continue;
		}
		$text = is_array($reasonText[$reason]) ? (isset($reasonText[$reason][$act]) ? $reasonText[$reason][$act] : '') : $reasonText[$reason];
		if ($text !== '') {
			$parts[] = rtrim(strpos($text, '%s') !== false ? sprintf(findtekst($text, $sprog), $count) : findtekst($text, $sprog), '.') . '.';
		}
	}
	return array('type' => $done > 0 ? 'ok' : 'err', 'text' => implode(' ', $parts));
}

function ur_flash(string $msg, int $sprog): ?array
{
	$map = array(
		'usersaved'   => array('ok',  '5572|Brugeren er gemt'),
		'userdeleted' => array('ok',  '5573|Brugeren er slettet'),
		'rolesaved'   => array('ok',  '5574|Rollen er gemt'),
		'roledeleted' => array('ok',  '5575|Rollen er slettet'),
		'assigned'    => array('ok',  '5580|Rollerne er tildelt'),
		'roleinuse'   => array('err', '5576|Rollen kan ikke slettes, mens brugere har den'),
		'escalation'  => array('err', '5577|Du kan ikke tildele flere rettigheder, end du selv har'),
		'above'       => array('err', '5968|Du kan ikke ændre brugere med flere rettigheder end dig selv'),
		'csrf'        => array('err', '5969|Siden var for gammel til at gemme. Prøv igen.'),
		'adminlock'   => array('err', '5970|Administrator-rollen skal altid have skriveadgang til brugere og roller'),
		'duplicate'   => array('err', '5578|Brugernavnet findes allerede'),
		'self'        => array('err', '5579|Du kan ikke slette din egen bruger'),
		'pwmismatch'  => array('err', '5589|Adgangskoden og gentagelsen er ikke ens'),
		'pwrequired'  => array('err', '5590|Adgangskode er påkrævet for en ny bruger'),
		'name'        => array('err', '5149|Brugernavnet må højst være 80 tegn'),
		'enforce'     => array('ok',  '5599|Håndhævelsen er ændret'),
		'userclosed'  => array('ok',  '5758|Brugeren er lukket'),
		'userreopened'=> array('ok',  '5759|Brugeren er genåbnet'),
		'bulkdone'    => array('ok',  '5769|Brugerne er opdateret'),
		'rolereset'   => array('ok',  '5763|Rollen er nulstillet til standard'),
		'lastadmin'   => array('err', '5760|Regnskabet skal have mindst én administrator.'),
		'ownrole'     => array('err', '5761|Du kan ikke ændre din egen rolle'),
		'hasloggedin' => array('err', '5762|Brugeren har været logget ind og kan derfor kun lukkes, ikke slettes'),
		'norole'      => array('err', '5771|Vælg en rolle først'),
		'invited'     => array('ok',  '5782|Invitationen er sendt'),
		'invitelink'  => array('err', '5783|E-mailen kunne ikke sendes. Send dette link til brugeren.'),
		'emailrequired' => array('err', '5786|En gyldig e-mail er påkrævet for at invitere'),
		'notinvited'  => array('err', '5794|Brugeren har allerede valgt en adgangskode'),
		'noaccess'    => array('err', '5809|Du har ikke adgang til dette'),
		'already'     => array('err', '5853|Ingen brugere blev ændret.'),
		'confirmed'   => array('ok',  '5889|Rollen er bekræftet'),
		'notreview'   => array('err', '5896|Brugerens rolle er allerede bekræftet'),
		'resetsent'   => array('ok',  '5863|En midlertidig adgangskode er sendt til brugerens e-mail'),
		'noemail'     => array('err', '5864|Brugeren har ingen gyldig e-mail'),
		'mailfailed'  => array('err', '5865|E-mailen kunne ikke sendes. Tjek e-mailopsætningen under Indstillinger.'),
		'closed'      => array('err', '5866|Brugeren er lukket. Genåbn brugeren først.'),
		'badip'       => array('err', '5906|En eller flere IP-adresser er ugyldige'),
		'iptoolong'   => array('err', '5907|Der er ikke plads til så mange IP-adresser (højst 45 tegn)'),
	);
	if (!isset($map[$msg])) {
		return null;
	}
	return array('type' => $map[$msg][0], 'text' => findtekst($map[$msg][1], $sprog));
}

/**
 * Readable name of an audit action (text id), or '' for an action without one.
 */
function ur_action_label(string $handling): string
{
	$map = array(
		'login'                 => '5813|Logget ind',
		'login.success'         => '5813|Logget ind',
		'login.failed'          => '5814|Login afvist',
		'login.2fa_failed'      => '5815|Forkert tofaktor-kode',
		'login.ip_blocked'      => '5816|IP-adresse afvist',
		'logout'                => '5817|Logget ud',
		'session.revisor_open'  => '5818|Regnskab åbnet fra admin',
		'session.forced_logout' => '5819|Bruger logget ud af en anden',
		'user.created'          => '5820|Bruger oprettet',
		'user.updated'          => '5821|Bruger ændret',
		'user.deactivated'      => '5822|Bruger lukket',
		'user.reactivated'      => '5823|Bruger genåbnet',
		'user.deleted'          => '5824|Bruger slettet',
		'user.role_changed'     => '5825|Brugerens rolle skiftet',
		'user.invited'          => '5826|Bruger inviteret',
		'user.invite_resent'    => '5827|Invitation sendt igen',
		'user.password_set'     => '5828|Adgangskode valgt',
		'user.password'         => '5829|Adgangskode ændret',
		'user.password_reset'   => '5869|Adgangskode nulstillet',
		'user.role_confirmed'   => '5890|Rolle bekræftet',
		'user.twofactor'        => '5830|Tofaktor-login ændret',
		'user.2fa_changed'      => '5830|Tofaktor-login ændret',
		'user.contact'          => '5831|Kontaktoplysninger ændret',
		'user.settings'         => '5832|Personlige indstillinger ændret',
		'role.created'          => '5833|Rolle oprettet',
		'role.updated'          => '5834|Rolle ændret',
		'role.deleted'          => '5835|Rolle slettet',
		'role.reset'            => '5836|Rolle nulstillet',
		'permission.would_deny' => '5600|Ville være afvist',
		'would-deny'            => '5600|Ville være afvist',
		'unguarded'             => '5837|Side uden rettighedsnøgle',
		'denied'                => '5838|Adgang afvist',
		'csrf'                  => '5839|Ugyldig formular (CSRF)',
		'permissions.mode'      => '5599|Håndhævelsen er ændret',
		'setting.change'        => '5840|Indstilling ændret',
		'setting.changed'       => '5840|Indstilling ændret',
		'setting.reverted'      => '6309|Indstilling gendannet',
		'setting.action'        => '5841|Indstillingshandling udført',
		'setting.row_created'   => '6310|Indstillingsrække oprettet',
		'setting.row_deleted'   => '6311|Indstillingsrække slettet',
		'revisor.set'           => '5842|Revisor valgt',
		'revisor.unset'         => '5843|Revisor fjernet',
		'audit.exported'        => '5844|Audit-log eksporteret',
	);
	return isset($map[$handling]) ? $map[$handling] : '';
}

/**
 * What an entry is about: a user by name, else type and id.
 *
 * @param array<int, string> $userNames
 */
function ur_log_object(array $row, array $userNames): string
{
	$type = (string) ifset($row, 'objekt_type', '');
	$id = (string) ifset($row, 'objekt_id', '');
	if ($type === 'bruger' && isset($userNames[(int) $id])) {
		return $userNames[(int) $id];
	}
	if ($type === '' && !empty($row['setting_key'])) {
		return (string) $row['section'];
	}
	return trim($type . ' ' . $id);
}

