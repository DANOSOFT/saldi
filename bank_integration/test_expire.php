<?php
// DEV/TEST ONLY — manipulates $_SESSION['OAuth'] timestamps for expiry testing.
// Do not deploy to production.

@session_start();

$action  = $_POST['action']  ?? null;
$seconds = max(1, intval($_POST['seconds'] ?? 10));

if ($action === 'delete_db') {
    include_once __DIR__ . '/../includes/connect.php';
    include_once __DIR__ . '/../includes/std_func.php';
    db_modify("DELETE FROM settings WHERE var_grp = 'OAuth' AND var_name = 'oauth_data'", __FILE__ . " linje " . __LINE__);
    $_SESSION['OAuth'] = null;
} elseif (!empty($_SESSION['OAuth'])) {
    switch ($action) {
        case 'expire_now':
            $_SESSION['OAuth']['login']['expires'] = (new DateTime('-1 second'))->format(DateTime::ATOM);
            break;
        case 'expire_in':
            $_SESSION['OAuth']['login']['expires'] = (new DateTime("+{$seconds} seconds"))->format(DateTime::ATOM);
            break;
        case 'clear':
            $_SESSION['OAuth'] = null;
            break;
    }
}

// Read current state for display
$OAuth   = $_SESSION['OAuth'] ?? null;
$now     = new DateTime();
$expires = $OAuth ? new DateTime($OAuth['login']['expires'])   : null;
$nextInt = $OAuth ? new DateTime($OAuth['session']['expires']) : null;

function diffLabel(?DateTime $now, ?DateTime $target): string {
    if (!$target) return '—';
    $diff = $target->getTimestamp() - $now->getTimestamp();
    if ($diff < 0) return '<span style="color:red">expired ' . abs($diff) . 's ago</span>';
    return '<span style="color:green">in ' . $diff . 's</span>';
}

include_once(__DIR__ . '/../../includes/connect.php');
include_once(__DIR__ . '/../../includes/online.php');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OAuth Expiry Test</title>
    <style>
        body { font-family: monospace; padding: 2em; }
        table { border-collapse: collapse; margin-bottom: 2em; }
        td, th { border: 1px solid #ccc; padding: .4em .8em; text-align: left; }
        th { background: #eee; }
        form { display: inline; }
        button { margin: .2em; padding: .3em .8em; cursor: pointer; }
        .section { margin-bottom: 2em; }
        hr { margin: 2em 0; }
    </style>
    <meta http-equiv="refresh" content="30">
</head>
<body>
<h2>OAuth Expiry Test <small style="font-size:.6em;color:#999">(auto-refreshes every 30 s)</small></h2>

<div class="section">
    <h3>Current session state</h3>
    <?php if (!$OAuth): ?>
        <p><strong>$_SESSION['OAuth'] is null / not set.</strong></p>
    <?php else: ?>
    <table>
        <tr><th>Field</th><th>Value</th><th>Relative</th></tr>
        <tr>
            <td>login.expires</td>
            <td><?= htmlspecialchars($OAuth['login']['expires']) ?></td>
            <td><?= diffLabel($now, $expires) ?></td>
        </tr>
        <tr>
            <td>session.expires</td>
            <td><?= htmlspecialchars($OAuth['session']['expires']) ?></td>
            <td><?= diffLabel($now, $nextInt) ?></td>
        </tr>
        <tr>
            <td>supportsUnattended</td>
            <td><?= htmlspecialchars(json_encode($OAuth['login']['supportsUnattended'] ?? null)) ?></td>
            <td>—</td>
        </tr>
        <tr>
            <td>loginToken</td>
            <td><?= empty($OAuth['login']['loginToken']) ? '(none)' : '(set)' ?></td>
            <td>—</td>
        </tr>
    </table>
    <?php endif; ?>
</div>

<div class="section">
    <h3>Icon preview</h3>
    <?php include __DIR__ . '/includes/auth_check_icon.php'; ?>
    <p style="font-size:.85em;color:#666">Status <?= $status_icon ?> —
        <?= ['', 'valid (1)', 'needs interaction (2)', 'expired / not set (3)'][$status_icon] ?>
    </p>
</div>

<hr>

<div class="section">
    <h3>Controls</h3>

    <p><strong>expires</strong></p>
    <form method="post">
        <input type="hidden" name="action" value="expire_now">
        <button>Set expired (now − 1 s)</button>
    </form>
    <form method="post">
        <input type="hidden" name="action" value="expire_in">
        <input type="number" name="seconds" value="<?= $seconds ?>" min="1" style="width:5em"> s from now
        <button>Set expires in N seconds</button>
    </form>

    <p style="margin-top:1em"><strong>Session</strong></p>
    <form method="post">
        <input type="hidden" name="action" value="clear">
        <button style="color:red">Clear $_SESSION['OAuth']</button>
    </form>
    <form method="post">
        <input type="hidden" name="action" value="delete_db">
        <button style="color:red">Delete token (DB + session)</button>
    </form>
</div>

<hr>
<p style="color:#999;font-size:.8em">Timestamp controls only modify <code>$_SESSION['OAuth']</code> — the DB row is untouched.<br>
"Delete token" removes both the DB row and the session.</p>
</body>
</html>
