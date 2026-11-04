<?php
// ---- index/dashboardIncludes/roleReview.php --- ver 5.1.0 --- 2026.11.04 ---
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
// 20260930 SS Roles stage 2 (Requirements_roles_stage2_EN.md §6.3): the "Review roles" card for administrators after the migration to roles, until every migrated role is confirmed or the card is hidden.
// 20261101 CL/ASR The card uses the dashboard's classes (prototype D panel, the kit's buttons) instead of inline styles.
// 20261103 CL/ASR QA: texts are escaped in the page's charset (htmlspecialchars() with UTF-8 blanked latin-1 texts).
// 20261104 CL/ASR Final QA: the card counts the users, the administrators and the roles to review from the users as they are now (perm_migration_summary_live()); the stored summary missed the users seeded as Administrator and said 0.

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
	$sum = perm_migration_summary_live();
	$charset = (isset($GLOBALS['db_encode']) && $GLOBALS['db_encode'] !== 'UTF8') ? 'ISO-8859-1' : 'UTF-8';
	$h = function ($s) use ($charset) {
		return htmlspecialchars((string) $s, ENT_QUOTES, $charset);
	};
	$text = sprintf(findtekst('5879|Saldi har oversat jeres %s brugere til roller. %s har fuld adgang som Administrator, %s fik en tilpasset rolle. Gennemgå fordelingen.', $sprogId), $sum['total'], $sum['admin'], $sum['custom']);
	?>
<div class="db-notice">
  <div class="db-notice-t">
    <b><?= $h(findtekst('5892|Gennemgå roller', $sprogId)) ?></b>
    <span><?= $h($text) ?></span>
  </div>
  <a class="btn btn-sm btn-primary" href="../systemdata/usersRoles.php?inframe=1&amp;tab=users&amp;status=review"><?= $h(findtekst('5880|Gennemgå', $sprogId)) ?></a>
  <form method="post" action="dashboard.php">
    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf_token']) ?>">
    <button class="btn btn-sm btn-secondary" name="role_review_hide" value="1"><?= $h(findtekst('5881|Skjul', $sprogId)) ?></button>
  </form>
</div>
	<?php
}
