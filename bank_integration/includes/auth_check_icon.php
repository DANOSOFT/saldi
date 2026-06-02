<?php
// Accessed directly via a link. Never included.
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/auth_check_icon.php --- patch 0.0.1 --- 2026-05-26 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20260526 NTR - Initial version. Shows the authentication status icon.

    include_once(__DIR__ . '/auth_check.php');

    global $bruger_id;
    $show_status = false;
    $r = db_fetch_array(db_select(
        "SELECT var_value FROM settings WHERE var_name = 'show_status' AND var_grp = 'bank_integration' AND user_id = '$bruger_id'",
        __FILE__ . " linje " . __LINE__
    ));
    if (!$r) $r = db_fetch_array(db_select(
        "SELECT var_value FROM settings WHERE var_name = 'show_status' AND var_grp = 'bank_integration' LIMIT 1",
        __FILE__ . " linje " . __LINE__
    ));
    if ($r) $show_status = $r['var_value'] == '1';

    if (!$show_status) return;

    $OAuth = isset($_SESSION['OAuth']) ? $_SESSION['OAuth'] : null;

    $now              = new DateTime();
    $needsInit        = ($OAuth === null || $OAuth === false);
    $needsInteraction = false;

    if (!$needsInit) {
        $expires          = new DateTime($OAuth['login']['expires']);
        $next_interaction = new DateTime($OAuth['session']['expires']);
        $supportsUnattended = isset($OAuth['login']['supportsUnattended']) && $OAuth['login']['supportsUnattended'] === true;

        if ($now >= $expires) {
            $needsInit = true;
        } elseif (
            $now >= $next_interaction &&
            isset($OAuth['login']['loginToken']) &&
            $supportsUnattended
        ) {
            include(__DIR__ . '/auth_check.php');
            $needsInteraction = true;
        }
    }

    $status_icons = [
        1 => '../ikoner/circle_check_filled.png', // authenticated and valid
        2 => '../ikoner/circle_check_filled_yellow.png', // needs interaction but not expired yet
        3 => '../ikoner/circle_no_check_filled.png', // not authenticated or expired
    ];

    // TODO Translations
    $status_titles = [
        1 => 'Logged in',
        2 => 'Refresh login',
        3 => 'Bank login',
    ];

    if ($needsInit) {
        $status_icon = 3;
    } elseif ($needsInteraction) {
        $status_icon = 2;
    } else {
        $status_icon = 1;
    }

    $icon_id = 'auth_status_' . uniqid();

?>
<a class="auth-status-icon" id="<?= $icon_id ?>_link" href="../bank_integration/login.php" title="<?= $status_titles[$status_icon] ?>">
    <span class="auth-login-label"><?= 'Login: ' // TODO: Translation ?></span><img id="<?= $icon_id ?>" src="<?= $status_icons[$status_icon] ?>" alt="Auth status">
</a>
<?php if ($status_icon === 1 || $status_icon === 2): ?>
<script>
(function() {
    const img  = document.getElementById('<?= $icon_id ?>');
    const link = document.getElementById('<?= $icon_id ?>_link');
    const icons = {
        2: '<?= $status_icons[2] ?>',
        3: '<?= $status_icons[3] ?>',
    };
    const titles = {
        2: '<?= $status_titles[2] ?>',
        3: '<?= $status_titles[3] ?>',
    };
    function setStatus(s) { img.src = icons[s]; link.title = titles[s]; }
    <?php
    // JS setTimeout silently overflows and fires immediately above 2^31-1 ms (~24.8 days).
    // Skip timers that are too far in the future — a page reload will re-evaluate them.
    $jsMaxMs       = 2147483647;
    $msToExpires   = ($expires->getTimestamp()        - $now->getTimestamp()) * 1000;
    $msToInteraction = ($next_interaction->getTimestamp() - $now->getTimestamp()) * 1000;
    ?>
    <?php if ($msToExpires > 0 && $msToExpires <= $jsMaxMs): ?>
    setTimeout(function() { setStatus(3); }, <?= $msToExpires ?>);
    <?php endif; ?>
    <?php if ($status_icon === 1 && $supportsUnattended && $msToInteraction > 0 && $msToInteraction <= $jsMaxMs): ?>
    setTimeout(function() { setStatus(2); }, <?= $msToInteraction ?>);
    <?php endif; ?>
})();
</script>
<?php endif; ?>