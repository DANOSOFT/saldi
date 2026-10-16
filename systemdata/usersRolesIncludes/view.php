<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- systemdata/usersRolesIncludes/view.php --- lap 5.0.0 --- 2026.10.11 ---
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
	$counts = ur_user_counts($vm);
	$back = ($vm['editUser'] !== null) ? $vm['linkPrefix'] . 'tab=users' : (($vm['tab'] === 'roles' && isset($_GET['rolle'])) ? $vm['linkPrefix'] . 'tab=roles' : 'settings.php');
	?>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<script>document.title = <?= json_encode(mb_convert_encoding(findtekst('5536|Brugere & roller', $sprog), 'UTF-8', $charset)) ?>;</script>
<?= function_exists('settings_breadcrumb') ? settings_breadcrumb_script(settings_breadcrumb('', findtekst('5536|Brugere & roller', $sprog), (int) $sprog), $charset) : '' ?>
<div class="ur-page">
  <a class="ur-back" accesskey="l" style="<?= $h(ur_back_style()) ?>" href="<?= $h($back) ?>"><i class='bx bx-arrow-back'></i><?= $t('5647|Tilbage') ?></a>

  <?php if ($vm['reviewCount'] > 0 && $vm['canWrite']) {
  	$sum = function_exists('perm_migration_summary') ? perm_migration_summary() : array('admin' => 0, 'custom' => 0);
  ?>
  <div class="ur-review-banner" id="ur-review-banner">
    <i class='bx bx-shield-quarter'></i>
    <div><b><?= $t('5954|Saldi har oversat jeres brugere til roller') ?></b><span><?= $h(sprintf(findtekst('5955|%s har fuld adgang som Administrator, %s fik en tilpasset rolle. Gennemgå fordelingen, så kun de rigtige har alt.', $sprog), $sum['admin'], $sum['custom'])) ?></span></div>
    <div class="ur-review-banner-actions">
      <a class="ur-btn ur-btn-light" href="<?= $link('tab=users&status=review') ?>"><?= $t('5880|Gennemgå') ?></a>
      <button class="ur-btn ur-btn-bare" type="button" onclick="document.getElementById('ur-review-banner').hidden = true"><?= $t('5881|Skjul') ?></button>
    </div>
  </div>
  <?php } ?>

  <header class="ur-head">
    <div>
      <h1><?= $t('5536|Brugere & roller') ?></h1>
      <p class="ur-sub"><?= $h(sprintf(findtekst('5960|%s brugere · %s administratorer · %s inviterede · %s lukkede', $sprog), $counts['all'], $counts['admins'], $counts['invited'], $counts['closed'])) ?></p>
    </div>
    <?php if ($vm['canWrite']) { ?>
    <div class="ur-head-actions">
      <a class="ur-btn" href="<?= $link('tab=users&bruger=0&mode=classic') ?>"><i class='bx bx-user-plus'></i><?= $t('5780|Opret bruger') ?></a>
      <a class="ur-btn ur-btn-primary" href="<?= $link('tab=users&bruger=0') ?>"><i class='bx bx-envelope'></i><?= $t('5779|Inviter bruger') ?></a>
    </div>
    <?php } ?>
  </header>

  <?php if ($flash) { ?>
  <div class="ur-flash ur-flash-<?= $h($flash['type']) ?>"><i class='bx <?= $flash['type'] === 'ok' ? 'bx-check-circle' : 'bx-error' ?>'></i><span><?= $h($flash['text']) ?></span></div>
  <?php } ?>
  <?php if (!$vm['tablesReady']) { ?>
  <div class="ur-flash ur-flash-err"><i class='bx bx-error'></i><span>Roles tables missing - log in again so includes/betweenUpdates.php can create them.</span></div>
  <?php } ?>

  <nav class="ur-tabs">
    <a class="<?= $vm['tab'] === 'users' ? 'on' : '' ?>" href="<?= $link('tab=users') ?>"><i class='bx bx-group'></i><?= $t('5549|Brugere') ?><span class="ur-count"><?= count($vm['users']) ?></span></a>
    <?php if ($vm['canRoles']) { ?><a class="<?= $vm['tab'] === 'roles' ? 'on' : '' ?>" href="<?= $link('tab=roles') ?>"><i class='bx bx-key'></i><?= $t('5550|Roller') ?><span class="ur-count"><?= count($vm['roles']) ?></span></a><?php } ?>
    <?php if ($vm['canAudit']) { ?><a class="<?= $vm['tab'] === 'log' ? 'on' : '' ?>" href="<?= $link('tab=log') ?>"><i class='bx bx-history'></i><?= $t('5796|Audit-log') ?></a><?php } ?>
  </nav>

  <?php
	if ($vm['tab'] === 'users') {
		ur_view_users($vm, $h, $t, $link, $roleName);
		if ($vm['editUser'] !== null) {
			ur_view_user_drawer($vm, $h, $t, $link, $roleName);
		}
	} elseif ($vm['tab'] === 'roles') {
		ur_view_roles($vm, $h, $t, $link, $roleName);
	} else {
		ur_view_log($vm, $h, $t, $link);
	}
	?>
</div>

<div class="ur-dlg-bg" id="ur-dlg" hidden>
  <div class="ur-dlg" role="dialog" aria-modal="true" aria-labelledby="ur-dlg-title">
    <h3 id="ur-dlg-title"></h3>
    <div id="ur-dlg-body"></div>
    <div class="ur-dlg-row" id="ur-dlg-row"></div>
  </div>
</div>
<template id="ur-dlg-cancel"><button type="button" class="ur-btn" data-dlg-close><?= $t('5940|Annullér') ?></button></template>
<?php ur_view_script($vm); ?>
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
  <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-bulk" class="ur-card">
    <input type="hidden" name="action" value="bulk_role">
    <div class="ur-bar">
      <div class="ur-search"><i class='bx bx-search'></i><input type="search" id="ur-search" placeholder="<?= $t('5917|Søg navn, brugernavn eller e-mail') ?>" autocomplete="off"></div>
      <select class="ur-select" id="ur-role-filter" aria-label="<?= $t('5553|Rolle') ?>">
        <option value=""><?= $t('5875|Alle roller') ?></option>
        <?php foreach ($vm['roles'] as $role) { ?><option value="<?= (int) $role['id'] ?>"><?= $roleName($role) ?></option><?php } ?>
      </select>
      <select class="ur-select" id="ur-status-filter" data-initial="<?= $h($vm['statusFilter'] === 'review' ? '' : $vm['statusFilter']) ?>" aria-label="<?= $t('5557|Status') ?>">
        <option value=""><?= $t('5915|Alle statusser') ?></option>
        <option value="active"><?= $t('5558|Aktiv') ?></option>
        <option value="invited"><?= $t('5778|Inviteret') ?></option>
        <option value="closed"><?= $t('5559|Lukket') ?></option>
      </select>
      <select class="ur-select" id="ur-2fa-filter" aria-label="2FA">
        <option value="">2FA: <?= $t('2498|Alle') ?></option>
        <option value="1"><?= $t('5873|Med 2FA') ?></option>
        <option value="0"><?= $t('5874|Uden 2FA') ?></option>
      </select>
      <?php if ($vm['reviewCount'] > 0) { ?>
      <select class="ur-select" id="ur-review-filter" data-initial="<?= $vm['statusFilter'] === 'review' ? '1' : '' ?>" aria-label="<?= $t('5882|Migreret, ikke bekræftet') ?>">
        <option value=""><?= $t('2498|Alle') ?></option>
        <option value="1"><?= $t('5882|Migreret, ikke bekræftet') ?></option>
      </select>
      <?php } ?>
    </div>

    <?php if ($vm['canWrite']) { ?>
    <div class="ur-bulkbar" id="ur-bulkbar" hidden>
      <b id="ur-bulk-n">0</b>&nbsp;<?= $t('5908|markeret') ?> ·
      <select class="ur-select ur-select-sm" name="role_id">
        <?php foreach ($vm['roles'] as $role) {
        	$assignable = perm_within_own(perm_levels_from_role($role['id']));
        ?><option value="<?= (int) $role['id'] ?>"<?= $assignable ? '' : ' disabled' ?>><?= $roleName($role) ?></option><?php } ?>
      </select>
      <button class="ur-btn ur-btn-primary ur-btn-sm" type="submit" name="bulk" value="role" data-applies="notself" data-bulk-confirm="<?= $t('5910|Tildel rolle') ?>"><?= $t('5910|Tildel rolle') ?></button>
      <button class="ur-btn ur-btn-sm" type="submit" name="bulk" value="close" data-applies="open" data-bulk-confirm="<?= $t('5764|Luk valgte') ?>" data-danger="1"><?= $t('2172|Luk') ?></button>
      <button class="ur-btn ur-btn-sm" type="submit" name="bulk" value="reopen" data-applies="closed" data-bulk-confirm="<?= $t('5765|Genåbn valgte') ?>"><?= $t('5912|Genåbn') ?></button>
      <button class="ur-btn ur-btn-sm" type="submit" name="bulk" value="resend" data-applies="invited" data-bulk-confirm="<?= $t('5781|Send invitation igen') ?>"><?= $t('5781|Send invitation igen') ?></button>
      <?php if ($vm['reviewCount'] > 0) { ?><button class="ur-btn ur-btn-sm" type="submit" name="bulk" value="suggest" data-applies="review" data-bulk-confirm="<?= $t('5887|Anvend forslag på valgte') ?>"><?= $t('5913|Anvend forslag') ?></button><?php } ?>
      <button class="ur-btn ur-btn-bare ur-btn-sm" type="button" id="ur-bulk-clear"><?= $t('5909|Ryd') ?></button>
    </div>
    <?php } ?>

    <div class="ur-table-wrap">
      <table class="ur-table" id="ur-users">
        <thead>
          <tr>
            <?php if ($vm['canWrite']) { ?><th class="ur-cb"><input type="checkbox" id="ur-check-all" aria-label="<?= $t('2498|Alle') ?>"></th><?php } ?>
            <th class="ur-sortable" data-sort><?= $t('5531|Navn') ?></th>
            <th class="ur-sortable" data-sort><?= $t('52|E-mail') ?></th>
            <th class="ur-sortable" data-sort><?= $t('5553|Rolle') ?></th>
            <th class="ur-sortable" data-sort>2FA</th>
            <th class="ur-sortable" data-sort><?= $t('5556|Sidst aktiv') ?></th>
            <th class="ur-sortable" data-sort><?= $t('5557|Status') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($vm['users'] as $u) {
          	$searchBlob = mb_strtolower($u['navn'] . ' ' . $u['brugernavn'] . ' ' . $u['email'] . ' ' . ($u['role'] ? perm_role_name($u['role'], $vm['sprogId']) : ''));
          	$status = $u['closed'] ? 'closed' : ($u['invited'] ? 'invited' : 'active');
          	$cardLink = $link('tab=users&bruger=' . $u['id']);
          	$badge = !$u['role'] ? 'none' : ($u['role_id'] === $vm['adminRoleId'] ? 'admin' : ($u['role']['system'] ? '' : 'custom'));
          ?>
          <tr data-search="<?= $h($searchBlob) ?>" data-status="<?= $status ?>"<?= $u['id'] === $vm['selfId'] ? ' data-self="1"' : '' ?> data-role="<?= (int) $u['role_id'] ?>" data-tfa="<?= $u['twofactor'] ? 1 : 0 ?>"<?= $u['review'] ? ' data-review="1"' : '' ?><?= ($vm['editUser'] && $vm['editUser']['id'] === $u['id']) ? ' class="on"' : '' ?>>
            <?php if ($vm['canWrite']) { ?><td class="ur-cb"><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" aria-label="<?= $h($u['navn']) ?>"></td><?php } ?>
            <td class="ur-name" data-v="<?= $h(mb_strtolower($u['navn'])) ?>">
              <a href="<?= $cardLink ?>"><b><?= $h($u['navn']) ?><?php if ($u['review']) { ?><span class="ur-pill"><?= $t('5883|Ikke bekræftet') ?></span><?php } ?><?php if ($u['isRevisor']) { ?><span class="ur-pill ur-pill-plain"><?= $t('2562|Revisor') ?></span><?php } ?></b><span><?= $h($u['brugernavn']) ?><?= $u['ansat_id'] > 0 ? ' · ' . $t('589|Ansat') : '' ?></span></a>
            </td>
            <td class="ur-mut"><?= $h($u['email']) ?></td>
            <td data-v="<?= $h($u['role'] ? mb_strtolower(perm_role_name($u['role'], $vm['sprogId'])) : '') ?>">
              <span class="ur-badge<?= $badge !== '' ? ' ur-badge-' . $badge : '' ?>"><?= $roleName($u['role']) ?></span>
              <?php if ($u['review'] && $u['suggestion'] && $u['suggestion']['id'] !== $u['role_id'] && $vm['canWrite'] && $u['id'] !== $vm['selfId']) { ?>
              <?php $sName = perm_role_name($u['suggestion'], $vm['sprogId']); ?>
              <button type="button" class="ur-sugg" data-suggest="<?= (int) $u['id'] ?>" data-dlg-title="<?= $h(sprintf(findtekst('5918|Bekræft rolle for %s', $vm['sprogId']), $u['navn'])) ?>" data-dlg-body="<?= $h(sprintf(findtekst('5919|Saldi foreslår %s: den standardrolle med færrest ekstra rettigheder, der dækker alt, brugeren havde før.', $vm['sprogId']), $sName)) ?>" data-dlg-ok="<?= $h(sprintf(findtekst('5921|Tildel %s', $vm['sprogId']), $sName)) ?>"><?= $t('5555|Forslag') ?>: <?= $h($sName) ?> →</button>
              <?php } ?>
            </td>
            <td data-v="<?= $u['twofactor'] ? 1 : 0 ?>"><i class='bx <?= $u['twofactor'] ? 'bxs-check-shield ur-ok' : 'bx-shield ur-off' ?>' title="2FA"></i></td>
            <td class="ur-mut" data-v="<?= $h($u['lastLogin']) ?>"><?= $u['lastLogin'] !== '' ? $h(substr($u['lastLogin'], 0, 16)) : $t('5905|Aldrig') ?></td>
            <td data-v="<?= $status ?>"><span class="ur-st ur-st-<?= $status ?>"><?= $u['closed'] ? $t('5559|Lukket') : ($u['invited'] ? $t('5778|Inviteret') : $t('5558|Aktiv')) ?></span></td>
            <td class="ur-rowact"><a class="ur-btn ur-btn-bare ur-btn-sm" href="<?= $cardLink ?>" aria-label="<?= $t('5567|Rediger') ?>"><i class='bx bx-edit'></i></a></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <div class="ur-empty" id="ur-empty" hidden><?= $t('5593|Ingen brugere fundet') ?></div>
    </div>
    <div class="ur-foot">
      <span id="ur-foot-text" data-format="<?= $t('5914|Viser %s af %s brugere') ?>"></span>
      <span class="ur-pager" id="ur-pager" hidden>
        <button class="ur-btn ur-btn-sm" type="button" data-page="-1"><i class='bx bx-chevron-left'></i><?= $t('5870|Forrige') ?></button>
        <span id="ur-pager-text" data-format="<?= $t('5872|%s–%s af %s') ?>"></span>
        <button class="ur-btn ur-btn-sm" type="button" data-page="1"><?= $t('5871|Næste') ?><i class='bx bx-chevron-right'></i></button>
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
  <a class="ur-drawer-bg" href="<?= $closeUrl ?>" aria-label="<?= $t('2172|Luk') ?>"></a>
  <aside class="ur-drawer" id="ur-drawer" data-close="<?= $closeUrl ?>" aria-labelledby="ur-drawer-title">
    <div class="ur-drawer-head">
      <h2 id="ur-drawer-title"><?= $isNew ? ($inviteNew ? $t('5779|Inviter bruger') : $t('5780|Opret bruger')) : $h($u['navn']) ?><?php if ($u['invited']) { ?> <span class="ur-st ur-st-invited"><?= $t('5778|Inviteret') ?></span><?php } ?></h2>
      <a class="ur-x" href="<?= $closeUrl ?>" aria-label="<?= $t('2172|Luk') ?>"><i class='bx bx-x'></i></a>
    </div>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" autocomplete="off" id="ur-user-form" class="ur-drawer-body">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
      <?php if ($isNew) { ?><input type="hidden" name="mode" value="<?= $inviteNew ? 'invite' : 'classic' ?>"><?php } ?>

      <?php if ($inviteNew) { ?><div class="ur-note"><?= $t('5788|Brugeren får en e-mail med et link og vælger selv sin adgangskode.') ?> <?= $t('5776|Linket gælder i 72 timer.') ?></div><?php } ?>
      <?php if ($vm['inviteLink'] !== '') { ?>
      <div class="ur-note ur-note-warn">
        <b><?= $t('5783|E-mailen kunne ikke sendes. Send dette link til brugeren.') ?></b>
        <input class="ur-input" type="text" readonly value="<?= $h($vm['inviteLink']) ?>" onfocus="this.select()">
      </div>
      <?php } ?>
      <?php if ($u['review'] && $vm['canWrite']) { ?>
      <div class="ur-note ur-note-warn">
        <span><?= $h(sprintf(findtekst('5884|Rollen er sat automatisk ud fra brugerens gamle rettigheder. Forslag: %s.', $vm['sprogId']), $u['suggestion'] ? perm_role_name($u['suggestion'], $vm['sprogId']) : '-')) ?></span>
        <span class="ur-note-actions">
          <?php if ($u['suggestion'] && $u['suggestion']['id'] !== $u['role_id'] && !$ownCard) { ?>
          <button class="ur-btn ur-btn-primary ur-btn-sm" type="submit" form="ur-confirm-use"><?= $h(sprintf(findtekst('5921|Tildel %s', $vm['sprogId']), perm_role_name($u['suggestion'], $vm['sprogId']))) ?></button>
          <?php } ?>
          <button class="ur-btn ur-btn-sm" type="submit" form="ur-confirm-keep"><?= $t('5920|Behold nuværende') ?></button>
        </span>
      </div>
      <?php } ?>

      <div class="ur-f">
        <label for="ur-email"><?= $t('52|E-mail') ?></label>
        <input class="ur-input" type="email" name="email" id="ur-email" value="<?= $h($u['email']) ?>"<?= $inviteNew ? ' required' : '' ?><?= $ro ?>>
      </div>
      <div class="ur-f">
        <label for="ur-brugernavn"><?= $t('225|Brugernavn') ?></label>
        <input class="ur-input" type="text" name="brugernavn" id="ur-brugernavn" maxlength="80" value="<?= $h($u['brugernavn']) ?>" required<?= $isNew ? $ro : ' readonly' ?>>
        <span class="ur-hint"><?= $isNew ? $t('5923|Kan ikke ændres senere.') : $t('5924|Kan ikke ændres.') ?></span>
      </div>
      <?php if ($isNew && !$inviteNew) { ?>
      <div class="ur-f">
        <label><?= $t('324|Adgangskode') ?></label>
        <input class="ur-input" type="password" name="kode" autocomplete="new-password" readonly data-nofill<?= $ro ?>>
      </div>
      <div class="ur-f">
        <label><?= $t('328|Gentag adgangskode') ?></label>
        <input class="ur-input" type="password" name="kode2" autocomplete="new-password" readonly data-nofill<?= $ro ?>>
      </div>
      <?php } ?>
      <div class="ur-f">
        <label for="ur-role"><?= $t('5553|Rolle') ?></label>
        <?php if ($ownCard || $lastAdmin) { ?><input type="hidden" name="role_id" value="<?= (int) $u['role_id'] ?>"><?php } ?>
        <select class="ur-select" name="role_id" id="ur-role"<?= ($ownCard || $lastAdmin) ? ' disabled' : $ro ?>>
          <?php if ($u['role_id'] === 0) { ?><option value="0" disabled selected><?= $t('5891|Vælg en rolle') ?></option><?php } ?>
          <?php foreach ($vm['roles'] as $role) {
          	$assignable = perm_within_own(perm_levels_from_role($role['id']));
          ?>
          <option value="<?= (int) $role['id'] ?>"<?= $role['id'] === $u['role_id'] ? ' selected' : '' ?><?= $assignable ? '' : ' disabled' ?>><?= $roleName($role) ?><?= $assignable ? '' : ' 🔒' ?></option>
          <?php } ?>
        </select>
        <span class="ur-hint"><?= $ownCard ? $t('5761|Du kan ikke ændre din egen rolle') : ($lastAdmin ? $t('5926|Sidste administrator. Regnskabet skal have mindst én.') : $t('5925|Kun roller inden for dine egne rettigheder kan vælges.')) ?></span>
      </div>
      <div class="ur-f">
        <label for="ur-ansat"><?= $t('5927|Kobling til ansat') ?></label>
        <select class="ur-select" name="ansat_id" id="ur-ansat"<?= $ro ?>>
          <option value="0">–</option>
          <?php foreach ($vm['employees'] as $e) { ?>
          <option value="<?= (int) $e['id'] ?>"<?= $e['id'] === $u['ansat_id'] ? ' selected' : '' ?>><?= $h(trim($e['initialer'] . ' ' . $e['navn'])) ?></option>
          <?php } ?>
        </select>
      </div>
      <div class="ur-f2">
        <div class="ur-f">
          <label for="ur-tfa"><?= $t('5928|Tofaktor') ?></label>
          <select class="ur-select" name="twofactor" id="ur-tfa"<?= $ro ?>>
            <option value="on"<?= $u['twofactor'] ? ' selected' : '' ?>><?= $t('5929|Til') ?></option>
            <option value=""<?= $u['twofactor'] ? '' : ' selected' ?>><?= $t('5930|Fra') ?></option>
          </select>
        </div>
        <div class="ur-f">
          <label><?= $t('5557|Status') ?></label>
          <select class="ur-select" disabled><option><?= $isNew ? ($inviteNew ? $t('5778|Inviteret') : $t('5558|Aktiv')) : ($u['closed'] ? $t('5559|Lukket') : ($u['invited'] ? $t('5778|Inviteret') : $t('5558|Aktiv'))) ?></option></select>
        </div>
      </div>
      <div class="ur-f">
        <label for="ur-tlf"><?= $t('37|Telefon') ?></label>
        <input class="ur-input" type="tel" name="tlf" id="ur-tlf" maxlength="16" value="<?= $h($u['tlf']) ?>"<?= $ro ?>>
        <span class="ur-hint"><?= $t('5516|Tofaktor-login kræver et telefonnummer eller en e-mail') ?></span>
      </div>
      <div class="ur-f">
        <label for="ur-ip"><?= $t('5931|IP-begrænsning') ?></label>
        <textarea class="ur-input" name="ip_address" id="ur-ip" rows="2" placeholder="<?= $t('5932|Én adresse pr. linje. Tom = ingen begrænsning.') ?>"<?= $ro ?>><?= $h(str_replace(',', "\n", $u['ip'])) ?></textarea>
      </div>
      <label class="ur-check"><input type="checkbox" name="revisor" value="on"<?= $u['isRevisor'] ? ' checked' : '' ?><?= $ro ?>><span><b><?= $t('5571|Denne bruger er regnskabets revisor') ?></b><small><?= $t('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang') ?></small></span></label>
      <?php if (!$isNew && $vm['canWrite']) { ?>
      <details class="ur-pw">
        <summary><?= $t('5935|Sæt ny adgangskode') ?></summary>
        <div class="ur-f2">
          <div class="ur-f"><label><?= $t('324|Adgangskode') ?></label><input class="ur-input" type="password" name="kode" autocomplete="new-password" readonly data-nofill></div>
          <div class="ur-f"><label><?= $t('328|Gentag adgangskode') ?></label><input class="ur-input" type="password" name="kode2" autocomplete="new-password" readonly data-nofill></div>
        </div>
      </details>
      <?php } ?>
      <label><?= $t('5933|Adgang fra rollen') ?></label>
      <div class="ur-access" id="ur-access" data-none="<?= $h(perm_level_label('none', $vm['sprogId'])) ?>" data-read="<?= $h(perm_level_label('read', $vm['sprogId'])) ?>" data-write="<?= $h(perm_level_label('write', $vm['sprogId'])) ?>">
        <?php foreach ($modules as $key => $label) { ?><div data-key="<?= $h($key) ?>"><span><?= $h($label) ?></span><span class="ur-lv-none"></span></div><?php } ?>
        <div data-admin hidden><span class="ur-lv-admin"><?= $t('5594|Farlige funktioner (kun administratorer som standard)') ?></span><span class="ur-lv-write"><?= $h(perm_level_label('write', $vm['sprogId'])) ?></span></div>
      </div>
      <script type="application/json" id="ur-access-data"><?= json_encode(array('admin' => $adminKeys, 'levels' => $vm['roleLevels'])) ?></script>
    </form>

    <?php if ($vm['canWrite']) { ?>
    <div class="ur-drawer-foot">
      <?php if ($isNew) { ?>
      <button class="ur-btn ur-btn-primary" type="submit" form="ur-user-form"><i class='bx <?= $inviteNew ? 'bx-send' : 'bx-save' ?>'></i><?= $inviteNew ? $t('5795|Send invitation') : $t('5952|Opret') ?></button>
      <a class="ur-btn ur-btn-bare" href="<?= $closeUrl ?>"><?= $t('5940|Annullér') ?></a>
      <?php } else { ?>
      <button class="ur-btn ur-btn-primary" type="submit" form="ur-user-form"><?= $t('3|Gem') ?></button>
      <?php if ($u['invited']) { ?>
      <button class="ur-btn" type="submit" form="ur-resend-form"><?= $t('5781|Send invitation igen') ?></button>
      <?php } elseif (!$u['closed'] && !$ownCard) { ?>
      <button class="ur-btn" type="submit" form="ur-reset-form"<?= $u['email'] === '' ? ' disabled title="' . $t('5864|Brugeren har ingen gyldig e-mail') . '"' : '' ?> data-confirm-form="<?= $t('5867|Send en midlertidig adgangskode til brugerens e-mail?') ?>"><?= $t('5868|Nulstil adgangskode') ?></button>
      <?php } ?>
      <span class="ur-spacer"></span>
      <?php if ($u['closed']) { ?>
      <button class="ur-btn" type="submit" form="ur-reopen-form"><?= $t('5912|Genåbn') ?></button>
      <?php } else { ?>
      <button class="ur-btn ur-btn-danger" type="button" id="ur-close-btn"<?= ($ownCard || $lastAdmin) ? ' disabled title="' . ($ownCard ? $t('5846|Du kan ikke lukke din egen bruger.') : $t('5926|Sidste administrator. Regnskabet skal have mindst én.')) . '"' : '' ?>><?= $t('5756|Luk bruger') ?></button>
      <?php } ?>
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
    <p><?= $t('5937|Brugeren kan ikke logge ind og tæller ikke i abonnementet. Historik og kontrolspor bevares, og brugeren kan genåbnes.') ?><?php if (!$u['hasLoggedIn']) { ?><br><br><?= $t('5938|Brugeren har aldrig logget ind og kan derfor også slettes helt.') ?><?php } ?></p>
    <div data-buttons>
      <?php if (!$u['hasLoggedIn']) { ?><button type="submit" class="ur-btn ur-btn-danger" form="ur-delete-form"><?= $t('5939|Slet helt') ?></button><?php } ?>
      <button type="submit" class="ur-btn ur-btn-danger" form="ur-close-form"><?= $t('5756|Luk bruger') ?></button>
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
  <div class="ur-roles">
    <div class="ur-card ur-rlist">
      <div class="ur-bar ur-bar-split">
        <b><?= $t('5550|Roller') ?></b>
        <?php if ($write) { ?>
        <span>
          <?php if ($role && $role['id'] > 0) { ?><button class="ur-btn ur-btn-sm" type="submit" form="ur-copy-form"><i class='bx bx-copy'></i><?= $t('5566|Kopiér') ?></button><?php } ?>
          <a class="ur-btn ur-btn-primary ur-btn-sm" href="<?= $link('tab=roles&rolle=0') ?>"><i class='bx bx-plus'></i><?= $t('5565|Ny rolle') ?></a>
        </span>
        <?php } ?>
      </div>
      <?php foreach ($vm['roles'] as $r) {
      	$count = isset($vm['roleCounts'][$r['id']]) ? $vm['roleCounts'][$r['id']] : 0;
      ?>
      <a class="ur-rrow<?= ($role && $role['id'] === $r['id']) ? ' on' : '' ?>" href="<?= $link('tab=roles&rolle=' . $r['id']) ?>">
        <span><b><?= $roleName($r) ?><?php if (!$r['system']) { ?> <span class="ur-badge ur-badge-custom"><?= $t('5942|Egen') ?></span><?php } ?></b><small><?= $h(perm_role_description($r, $vm['sprogId'])) ?></small></span>
        <span class="ur-rrow-n"><?= (int) $count ?> <i class='bx bx-user'></i></span>
      </a>
      <?php } ?>
    </div>

    <?php if ($role) {
    	$isNew = ($role['id'] === 0);
    	$count = (!$isNew && isset($vm['roleCounts'][$role['id']])) ? $vm['roleCounts'][$role['id']] : 0;
    	$name = $isNew ? '' : perm_role_name($role, $vm['sprogId']);
    	$rank = perm_level_rank();
    	$registry = permission_registry();
    ?>
    <div class="ur-card ur-matrix">
      <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-role-form" autocomplete="off">
        <input type="hidden" name="action" value="save_role">
        <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
        <div class="ur-m-head">
          <?php if ($role['system']) { ?>
          <h2><?= $h($name) ?></h2><input type="hidden" name="navn" value="<?= $h($role['navn']) ?>">
          <span class="ur-lock"><i class='bx bx-lock-alt'></i><?= $t('5943|Standardrolle · navn låst') ?></span>
          <?php } else { ?>
          <input class="ur-input ur-m-name" type="text" name="navn" maxlength="80" value="<?= $h($role['navn']) ?>" placeholder="<?= $t('5531|Navn') ?>" required<?= $write ? '' : ' disabled' ?>>
          <?php } ?>
          <span class="ur-spacer"></span>
          <?php if ($write && !$isNew) { ?>
            <?php if ($role['system']) { ?>
            <button class="ur-btn ur-btn-sm" type="submit" form="ur-reset-role-form"<?= $vm['roleChanged'] ? '' : ' disabled' ?> data-confirm-form="<?= $t('5767|Nulstil rollen til standard?') ?>"><i class='bx bx-reset'></i><?= $t('5717|Nulstil til standard') ?></button>
            <?php } else { ?>
            <button class="ur-btn ur-btn-sm ur-btn-danger" type="button" id="ur-del-role"><i class='bx bx-trash'></i><?= $t('1099|Slet') ?></button>
            <?php } ?>
          <?php } ?>
          <?php if ($write) { ?><button class="ur-btn ur-btn-primary ur-btn-sm" type="submit"><?= $t('3|Gem') ?></button><?php } ?>
        </div>
        <div class="ur-hint ur-m-desc">
          <?php if ($role['system']) { ?>
          <?= $h(perm_role_description($role, $vm['sprogId'])) ?><input type="hidden" name="beskrivelse" value="<?= $h($role['beskrivelse']) ?>">
          <?php } else { ?>
          <input class="ur-input" type="text" name="beskrivelse" maxlength="500" value="<?= $h($role['beskrivelse']) ?>" placeholder="<?= $t('5568|Beskrivelse') ?>"<?= $write ? '' : ' disabled' ?>>
          <?php } ?>
          <?php if (!$isNew) { ?> · <?= $count > 0 ? $h(sprintf(findtekst('5944|Bruges af %s brugere. Ændringer gælder fra deres næste sidevisning.', $vm['sprogId']), $count)) : $t('5945|Ingen brugere har denne rolle.') ?><?php } ?>
        </div>
        <div class="ur-m-cols"><span></span><span><?= $h(perm_level_label('none', $vm['sprogId'])) ?></span><span><?= $h(perm_level_label('read', $vm['sprogId'])) ?></span><span><?= $h(perm_level_label('write', $vm['sprogId'])) ?></span></div>
        <?php foreach (permission_groups() as $group => $groupLabel) {
        	$keys = array_filter($registry, function ($def) use ($group) { return $def['group'] === $group; });
        	if (!$keys) {
        		continue;
        	}
        ?>
        <div class="ur-grp<?= $group === 'settings' ? ' ur-grp-danger' : '' ?>" data-grp>
          <h4><span><?= $t($groupLabel) ?><?php if ($group === 'settings') { ?> <small>· <?= $t('5594|Farlige funktioner (kun administratorer som standard)') ?></small><?php } ?></span><?php if ($write) { $allRead = !array_filter($keys, function ($d) { return empty($d['readonly']); }); ?><button type="button" class="ur-link" data-all-write><?= $t($allRead ? '7071|Sæt alle til læs' : '5946|Sæt alle til skriv') ?></button><?php } ?></h4>
          <?php foreach ($keys as $key => $def) {
          	$level = isset($role['levels'][$key]) ? $role['levels'][$key] : 'none';
          	$own = isset($vm['ownLevels'][$key]) ? $vm['ownLevels'][$key] : 'none';
          	$adminLock = ($role['key'] === 'administrator' && in_array($key, UR_ADMIN_LOCKED, true));
          ?>
          <div class="ur-prow<?= !empty($def['block']) ? ' ur-prow-block' : '' ?>"<?= $key === 'dashboard.view' ? ' data-view-row' : '' ?><?= $adminLock ? ' title="' . $t('5971|Administrator-rollen beholder altid adgang til brugere og roller') . '"' : '' ?><?= !empty($def['derive_keys']) ? ' data-derive="' . $h(implode(',', $def['derive_keys'])) . '"' : '' ?>>
            <b><?= $t($def['label']) ?><?php if ($def['dangerous']) { ?> <i class='bx bx-lock-alt ur-mut'></i><?php } ?><?php if (!empty($def['block'])) { ?> <small class="ur-mut ur-block-hint" hidden><?= $t('7070|Brugeren kan se tallene her, men ikke åbne rapporten bag.') ?></small><small class="ur-mut ur-block-needs" hidden><?= $t('7069|Kræver Se Oversigt') ?></small><?php } ?></b>
            <?php foreach (array('none', 'read', 'write') as $opt) {
            	$locked = ($rank[$opt] > $rank[$own]) || ($adminLock && $opt !== 'write') || ($opt === 'write' && !empty($def['readonly']));
            ?>
            <span class="ur-lv"><input type="radio" name="level[<?= $h($key) ?>]" value="<?= $opt ?>" aria-label="<?= $t($def['label']) ?>: <?= $h(perm_level_label($opt, $vm['sprogId'])) ?>"<?= $level === $opt ? ' checked' : '' ?><?= ($locked || !$write) ? ' disabled' : '' ?>></span>
            <?php } ?>
          </div>
          <?php } ?>
        </div>
        <?php } ?>
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
          <div class="ur-f"><label><?= $t('5950|Flyt til') ?></label>
            <select class="ur-select" name="move_to">
              <?php foreach ($vm['roles'] as $r) {
              	if ($r['id'] === $role['id'] || !perm_within_own(perm_levels_from_role($r['id']))) {
              		continue;
              	}
              ?><option value="<?= (int) $r['id'] ?>"><?= $roleName($r) ?></option><?php } ?>
            </select>
          </div>
        </form>
        <div data-buttons><button type="submit" class="ur-btn ur-btn-danger" form="ur-del-role-form"><?= $t('5951|Flyt og slet') ?></button></div>
        <?php } else { ?>
        <h3><?= $t('5586|Slet rolle?') ?> <?= $h($name) ?></h3>
        <form method="post" action="<?= $h($vm['selfUrl']) ?>" id="ur-del-role-form"><input type="hidden" name="action" value="delete_role"><input type="hidden" name="id" value="<?= (int) $role['id'] ?>"></form>
        <div data-buttons><button type="submit" class="ur-btn ur-btn-danger" form="ur-del-role-form"><?= $t('1099|Slet') ?></button></div>
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
  <div class="ur-card">
    <form method="get" action="usersRoles.php" class="ur-bar">
      <?php if (strpos($vm['linkPrefix'], 'inframe=1') !== false) { ?><input type="hidden" name="inframe" value="1"><?php } ?>
      <input type="hidden" name="tab" value="log">
      <div class="ur-search"><i class='bx bx-search'></i><input type="search" name="q" value="<?= $h($f['q']) ?>" placeholder="<?= $t('5802|Søg i brugernavn og objekt…') ?>"></div>
      <select class="ur-select" name="type" aria-label="<?= $t('5582|Handling') ?>">
        <option value=""><?= $t('5800|Alle handlinger') ?></option>
        <?php foreach ($types as $key => $label) { ?><option value="<?= $h($key) ?>"<?= (!$f['wd'] && $key === $f['type']) ? ' selected' : '' ?>><?= $t($label) ?></option><?php } ?>
        <option value="wd"<?= $f['wd'] ? ' selected' : '' ?>><?= $t('5600|Ville være afvist') ?></option>
      </select>
      <select class="ur-select" name="bruger" aria-label="<?= $t('225|Brugernavn') ?>">
        <option value=""><?= $t('5799|Alle brugere') ?></option>
        <?php foreach ($vm['logOptions']['users'] as $name) { ?><option value="<?= $h($name) ?>"<?= $name === $f['bruger'] ? ' selected' : '' ?>><?= $h($name) ?></option><?php } ?>
      </select>
      <?php if ($vm['logOptions']['objects']) { ?>
      <select class="ur-select" name="objekt" aria-label="<?= $t('5806|Objekt') ?>">
        <option value=""><?= $t('5801|Alle objekter') ?></option>
        <?php foreach ($vm['logOptions']['objects'] as $o) { ?><option value="<?= $h($o) ?>"<?= $o === $f['objekt'] ? ' selected' : '' ?>><?= $h($o) ?></option><?php } ?>
      </select>
      <?php } ?>
      <input class="ur-input ur-date" type="date" name="fra" value="<?= $h($f['fra']) ?>" aria-label="<?= $t('5797|Fra') ?>" title="<?= $t('5797|Fra') ?>">
      <input class="ur-input ur-date" type="date" name="til" value="<?= $h($f['til']) ?>" aria-label="<?= $t('5798|Til') ?>" title="<?= $t('5798|Til') ?>">
      <button class="ur-btn ur-btn-primary ur-btn-sm" type="submit"><i class='bx bx-filter-alt'></i><?= $t('5803|Filtrér') ?></button>
      <a class="ur-btn ur-btn-bare ur-btn-sm" href="<?= $link('tab=log') ?>"><?= $t('5804|Nulstil filtre') ?></a>
      <?php if ($vm['canExport']) { ?><a class="ur-btn ur-btn-sm" href="<?= $link(ur_log_query($f, array('side' => 0)) . '&export=csv') ?>" download><i class='bx bx-download'></i><?= $t('5805|Eksportér CSV') ?></a><?php } ?>
    </form>
    <div class="ur-table-wrap">
      <table class="ur-table ur-aud">
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
            <td class="ur-mono"><?= $h(substr((string) $row['tidspunkt'], 0, 16)) ?></td>
            <td><?= $h($row['brugernavn']) ?></td>
            <td><span class="ur-k<?= $warn ? ' ur-k-warn' : '' ?>" title="<?= $h($handling) ?>"><?= $label !== '' ? $t($label) : $h($handling) ?></span></td>
            <td><?= $h(ur_log_object($row, $vm['userNames'])) ?></td>
            <td class="ur-mut"><?php if (mb_strlen($details) > 70) { ?><details><summary><?= $t('5959|Vis') ?></summary><pre><?= $h($details) ?></pre></details><?php } else { ?><?= $h($details) ?><?php } ?></td>
            <td class="ur-mut"><?= $h($row['ip']) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
      <?php if (!$vm['log']) { ?><div class="ur-empty"><?= $t('5584|Ingen hændelser endnu') ?></div><?php } ?>
    </div>
    <div class="ur-foot">
      <span><?= $h(sprintf(findtekst('5845|Viser %s–%s', $vm['sprogId']), $vm['log'] ? $f['side'] * UR_LOG_PAGE + 1 : 0, $f['side'] * UR_LOG_PAGE + count($vm['log']))) ?></span>
      <?php if ($f['side'] > 0 || $vm['logMore']) { ?>
      <span class="ur-pager">
        <?php if ($f['side'] > 0) { ?><a class="ur-btn ur-btn-sm" href="<?= $link(ur_log_query($f, array('side' => $f['side'] - 1))) ?>"><i class='bx bx-chevron-left'></i><?= $t('5808|Nyere') ?></a><?php } ?>
        <?php if ($vm['logMore']) { ?><a class="ur-btn ur-btn-sm" href="<?= $link(ur_log_query($f, array('side' => $f['side'] + 1))) ?>"><?= $t('5807|Ældre') ?><i class='bx bx-chevron-right'></i></a><?php } ?>
      </span>
      <?php } ?>
    </div>
  </div>

  <?php if ($vm['canRoles']) { ?>
  <details class="ur-card ur-enforce"<?= $mode === 'deny' ? ' open' : '' ?>>
    <summary><i class='bx bx-lock-alt'></i><?= $t('5595|Håndhævelse') ?> <span class="ur-count"><?= count($vm['unguarded']) + count($vm['wouldDeny']) ?></span></summary>
    <p class="ur-hint"><?= $t('5601|Skift til "Afvis" først, når listen over sider uden nøgle er tom, og alle "ville være afvist"-hændelser er forventede.') ?></p>
    <form method="post" action="<?= $h($vm['selfUrl']) ?>" class="ur-enforce-form">
      <input type="hidden" name="action" value="set_enforce">
      <label class="ur-check"><input type="radio" name="mode" value="log"<?= $mode === 'log' ? ' checked' : '' ?><?= $vm['canRolesWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5596|Log kun (intet blokeres)') ?></b></span></label>
      <label class="ur-check"><input type="radio" name="mode" value="deny"<?= $mode === 'deny' ? ' checked' : '' ?><?= $vm['canRolesWrite'] ? '' : ' disabled' ?>><span><b><?= $t('5597|Afvis (standard-afvis er slået til)') ?></b></span></label>
      <?php if ($vm['canRolesWrite']) { ?><button class="ur-btn ur-btn-primary ur-btn-sm" type="submit"><?= $t('3|Gem') ?></button><?php } ?>
    </form>
    <div class="ur-enforce-lists">
      <div>
        <h4><?= $t('5598|Sider uden rettighedsnøgle set i logperioden') ?> <span class="ur-count"><?= count($vm['unguarded']) ?></span></h4>
        <?php if ($vm['unguarded']) { ?>
        <ul class="ur-list"><?php foreach ($vm['unguarded'] as $r) { ?><li><code><?= $h($r['detaljer']) ?></code><span class="ur-mut"><?= (int) $r['antal'] ?> · <?= $h(substr((string) $r['sidst'], 0, 16)) ?></span></li><?php } ?></ul>
        <?php } else { ?><p class="ur-mut">–</p><?php } ?>
      </div>
      <div>
        <h4><?= $t('5600|Ville være afvist') ?> <span class="ur-count"><?= count($vm['wouldDeny']) ?></span></h4>
        <?php if ($vm['wouldDeny']) { ?>
        <ul class="ur-list"><?php foreach ($vm['wouldDeny'] as $r) { ?><li><code><?= $h($r['detaljer']) ?></code><span class="ur-mut"><?= (int) $r['antal'] ?> · <?= $h(substr((string) $r['sidst'], 0, 16)) ?></span></li><?php } ?></ul>
        <?php } else { ?><p class="ur-mut">–</p><?php } ?>
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
		ok.className = 'ur-btn ' + (danger ? 'ur-btn-danger' : 'ur-btn-primary');
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
			ok.className = 'ur-btn ur-btn-primary';
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
 * Back button in the user's theme colour, as on the other system pages.
 */
function ur_back_style(): string
{
	global $buttonColor, $buttonTxtColor;
	$bg = !empty($buttonColor) ? $buttonColor : '#114691';
	$fg = !empty($buttonTxtColor) ? $buttonTxtColor : '#ffffff';
	return 'background:' . $bg . ';color:' . $fg;
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

