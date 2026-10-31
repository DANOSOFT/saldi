<?php
// ---- index/dashboardIncludes/roleReview.php --- lap 5.0.0 --- 2026.09.30 ---
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
// 20260930 Sawaneh Roles stage 2 (Requirements_roles_stage2_EN.md §6.3): the "Review roles" card for
//                  administrators after the migration to roles, until every migrated role is
//                  confirmed or the card is hidden.

/**
 * Card on the dashboard. Hiding is per administrator.
 */
function role_review_card(int $brugerId, int $sprogId): void
{
	if (!function_exists('perm_review_pending') || !perm_can('settings.users.manage', 'write')) {
		return;
	}
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	if (isset($_POST['role_review_hide'], $_POST['csrf']) && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf'])) {
		update_settings_value('review_card_hidden', 'permissions', '1', 'Review roles card hidden by this administrator', $brugerId);
	}
	$pending = count(perm_review_pending());
	if ($pending === 0 || get_settings_value('review_card_hidden', 'permissions', '0', $brugerId) === '1') {
		return;
	}
	$sum = perm_migration_summary();
	$h = function ($s) {
		return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
	};
	$text = sprintf(findtekst('5879|Saldi har oversat jeres %s brugere til roller. %s har fuld adgang som Administrator, %s fik en tilpasset rolle. Gennemgå fordelingen.', $sprogId), $sum['total'], $sum['admin'], $sum['custom']);
	?>
<div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px 18px;background:#fff;border:1px solid #f4dfb2;border-left:4px solid #e8a013;border-radius:10px;padding:16px 20px;">
  <div style="flex:1;min-width:240px">
    <b style="display:block;margin-bottom:4px"><?= $h(findtekst('5892|Gennemgå roller', $sprogId)) ?></b>
    <span style="color:#555"><?= $h($text) ?></span>
  </div>
  <a href="../systemdata/usersRoles.php?inframe=1&amp;tab=users&amp;status=review" style="background:#114691;color:#fff;border-radius:8px;padding:8px 16px;text-decoration:none;font-weight:600"><?= $h(findtekst('5880|Gennemgå', $sprogId)) ?></a>
  <form method="post" action="dashboard.php" style="margin:0">
    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf_token']) ?>">
    <button type="submit" name="role_review_hide" value="1" style="background:none;border:1px solid #d5dbe6;border-radius:8px;padding:8px 14px;cursor:pointer"><?= $h(findtekst('5881|Skjul', $sprogId)) ?></button>
  </form>
</div>
	<?php
}
