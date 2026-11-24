<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/releaseNoticeFunc.php --- ver 5.1.1 --- 2026-11-24 ---
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
// 20261124 CL/PS SST-1040 Created. "Saldi er blevet opdateret" popup with a link to the changelog, shown once per user
//                per release marker (includes/releaseNotice.php) for a major release.
//                Every announced Saldi release (major or minor) is also a notification in the bell (notif_source_release()
//                in includes/notifications.php), and acknowledging the popup marks that notification as read.
//                Requires includes/std_func.php (get_settings_value / update_settings_value / findtekst).

if (!function_exists('release_notice_config')) {
	/**
	 * Read the release marker for one audience and the changelog URL from includes/releaseNotice.php.
	 *
	 * @param string $scope 'main' (normal Saldi menu) or 'sager' (Sager/Loen); anything else is 'main'.
	 *
	 * @return array{
	 *   scope: string,        The validated scope, 'main' or 'sager'.
	 *   id: string,           Current release marker for that scope; '' means nothing is announced.
	 *   url: string,          Changelog URL opened from the popup and the bell.
	 *   popup: bool,          Whether the release gets the popup: 'main' only when size is 'major', 'sager' always.
	 *   notify: bool,         Whether the release gets a bell notification: 'main' only (Sager/Loen has no bell).
	 *   settingName: string,  settings.var_name (var_grp 'releaseNotice') holding the user's seen marker.
	 * }
	 */
	function release_notice_config($scope = 'main') {
		$scope = ($scope === 'sager') ? 'sager' : 'main';
		$config = include __DIR__ . '/releaseNotice.php';
		if (!is_array($config)) {
			$config = array();
		}
		$id = trim((string)ifset($config, $scope, ''));
		$url = trim((string)ifset($config, 'url', ''));
		$announced = ($id !== '' && $url !== '');
		$major = (trim((string)ifset($config, 'size', 'minor')) === 'major');
		return array(
			'scope'       => $scope,
			'id'          => $id,
			'url'         => $url,
			'popup'       => $announced && ($scope === 'sager' || $major),
			'notify'      => $announced && $scope === 'main',
			'settingName' => ($scope === 'sager') ? 'seenIdSager' : 'seenId',
		);
	}
}

if (!function_exists('release_notice_pending')) {
	/**
	 * Whether the user still has to acknowledge the current release popup of one audience.
	 *
	 * @param int    $userId brugere.id of the logged-in user (online.php's $bruger_id).
	 * @param string $scope  'main' or 'sager', see release_notice_config().
	 *
	 * @return bool True if the popup should be shown; false for revisor/unknown users (id <= 0),
	 *              when the release is minor or not announced, or once the user has seen it.
	 */
	function release_notice_pending($userId, $scope = 'main') {
		$userId = (int)$userId;
		if ($userId <= 0 || !function_exists('get_settings_value')) {
			return false;
		}
		$config = release_notice_config($scope);
		if (!$config['popup']) {
			return false;
		}
		$seenId = (string)get_settings_value($config['settingName'], 'releaseNotice', '', $userId);
		return $seenId !== $config['id'];
	}
}

if (!function_exists('release_notice_mark_seen')) {
	/**
	 * Store that the user has acknowledged the current release popup of one audience, and mark
	 * the release's bell notification as read so the same news is not shown twice.
	 *
	 * The marker stored is always the server's current one, never a client-supplied value.
	 *
	 * @param int    $userId brugere.id of the logged-in user (online.php's $bruger_id).
	 * @param string $scope  'main' or 'sager', see release_notice_config().
	 *
	 * @return bool True if stored; false for revisor/unknown users or when there is no popup.
	 */
	function release_notice_mark_seen($userId, $scope = 'main') {
		global $sprog_id;
		$userId = (int)$userId;
		if ($userId <= 0 || !function_exists('update_settings_value')) {
			return false;
		}
		$config = release_notice_config($scope);
		if (!$config['popup']) {
			return false;
		}
		update_settings_value(
			$config['settingName'],
			'releaseNotice',
			db_escape_string($config['id']),
			'Last release notice (changelog popup) the user acknowledged',
			$userId
		);
		if ($config['notify'] && function_exists('notif_source_release') && function_exists('notif_mark_read')) {
			// The bell may not have created the item yet (its refresh is throttled), so create it first.
			$notificationId = notif_source_release(max(1, (int)$sprog_id));
			if ($notificationId > 0) {
				notif_mark_read($userId, $notificationId);
			}
		}
		return true;
	}
}

if (!function_exists('release_notice_print')) {
	/**
	 * Print the "Saldi er blevet opdateret" popup if the user has not acknowledged the
	 * current major release yet. Prints nothing otherwise.
	 *
	 * OK, or leaving the page while the popup is open (closing the tab, reloading, logging
	 * out), POSTs to index/releaseNoticeSeen.php, which stores the marker, so the popup only
	 * returns when includes/releaseNotice.php gets a new major release.
	 * The calling page must sit one directory below the web root (index/, sager/ ...).
	 *
	 * @param int    $userId brugere.id of the logged-in user (online.php's $bruger_id).
	 * @param string $scope  'main' or 'sager', see release_notice_config().
	 *
	 * @return void
	 */
	function release_notice_print($userId, $scope = 'main') {
		global $sprog_id;
		if (!release_notice_pending($userId, $scope)) {
			return;
		}
		$config = release_notice_config($scope);
		$langId = max(1, (int)$sprog_id);
		$url = htmlspecialchars($config['url'], ENT_QUOTES, 'UTF-8');
		$seenUrl = '../index/releaseNoticeSeen.php?scope=' . $config['scope'];
		?>
<div id="releaseNotice" class="releaseNotice-backdrop" role="dialog" aria-modal="true" aria-labelledby="releaseNoticeTitle">
	<div class="releaseNotice-box">
		<h2 id="releaseNoticeTitle"><?= findtekst('8601|Saldi er blevet opdateret', $langId) ?></h2>
		<p>
			<?= findtekst('8602|Se de seneste opdateringer og rettelser i vores changelog', $langId) ?>
			<a href="<?= $url ?>" target="_blank" rel="noopener"><?= findtekst('8603|her', $langId) ?></a>.
		</p>
		<button type="button" id="releaseNoticeOk"><?= 'OK' ?></button>
	</div>
</div>
<style>
	.releaseNotice-backdrop {
		position: fixed; inset: 0; z-index: 2147483000;
		display: flex; align-items: center; justify-content: center;
		background: rgba(0, 0, 0, 0.45);
		font-family: Arial, Helvetica, sans-serif;
	}
	.releaseNotice-box {
		max-width: 420px; width: calc(100% - 40px); box-sizing: border-box;
		padding: 24px 28px; border-radius: 8px;
		background: #fff; color: #222; text-align: center;
		box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
	}
	.releaseNotice-box h2 { margin: 0 0 12px; font-size: 20px; color: #222; }
	.releaseNotice-box p { margin: 0 0 20px; font-size: 15px; line-height: 1.4; color: #222; }
	.releaseNotice-box a { color: #114691; text-decoration: underline; }
	.releaseNotice-box button {
		min-width: 90px; padding: 8px 20px; border: 0; border-radius: 4px;
		background: #114691; color: #fff; font-size: 15px; cursor: pointer;
	}
	.releaseNotice-box button:hover { background: #0d3570; }
</style>
<script>
(function () {
	var notice = document.getElementById('releaseNotice');
	var ok = document.getElementById('releaseNoticeOk');
	if (!notice || !ok) {
		return;
	}
	// Only the top window shows it, so a page inside the main.php iframe does not show a second copy.
	if (window.self !== window.top) {
		notice.parentNode.removeChild(notice);
		return;
	}
	var seenUrl = <?= json_encode($seenUrl) ?>;
	var sent = false;
	function post() {
		return fetch(seenUrl, {method: 'POST', credentials: 'same-origin', keepalive: true});
	}
	// Closing the tab (or otherwise leaving the page) while the popup is open counts as seen too.
	// sendBeacon is delivered even while the tab is closing; fetch keepalive is the fallback.
	function markSeenOnLeave() {
		if (sent) {
			return;
		}
		sent = true;
		if (navigator.sendBeacon && navigator.sendBeacon(seenUrl)) {
			return;
		}
		post().catch(function () {});
	}
	ok.focus();
	ok.addEventListener('click', function () {
		notice.parentNode.removeChild(notice);
		window.removeEventListener('pagehide', markSeenOnLeave);
		if (sent) {
			return;
		}
		sent = true;
		post()
			.then(function () {
				// The bell (index/main.php) shows the release as read now instead of at its next poll.
				if (typeof window.topbarNotifLoad === 'function') {
					window.topbarNotifLoad();
				}
			})
			.catch(function () {});
	});
	window.addEventListener('pagehide', markSeenOnLeave);
})();
</script>
		<?php
	}
}
