<?php
@session_start();
$s_id = session_id();
include("../includes/connect.php");
include("../includes/online.php");
require_once __DIR__ . '/stdFunc/unlockRecord.php';

// 20260908 SZ SST-755: generalized from ordrer-only to a whitelisted table selector
// (kassekladde's kladdeliste table now also releases through here), and require the
// beacon's own $tidspkt to still match the DB row before clearing - so a stale tab's
// delayed beacon can't clobber a lock a newer tab has since acquired.
// 20260910 SZ SST-755 (CodeRabbit): delegate to unlock_record() instead of duplicating its
// SQL here - the duplicate had drifted to writing tidspkt=NULL for kladdeliste, but
// finans/kassekladde.php's acquire check uses isset($row['tidspkt']), which NULL fails and
// '' passes, so a beacon-released journal could never be reacquired. unlock_record() already
// gets this right (and already carries the DO/DK hvem-preserving case for 'ordrer').
// 20260923 SZ SST-755 (CodeRabbit): also accept lockToken - the pages now send this instead
// of tidspkt in their beacon payload (see refresh_lock_token() in unlockRecord.php), since
// tidspkt alone didn't distinguish two tabs open on the same record before either one saved.
// 20260924 SZ SST-755 (CodeRabbit): a beacon cached before this feature carries no lockToken
// at all, and unlock_record() used to skip the token check entirely whenever lockToken was
// null - so it could still clear a lock a newer, tokenized render now holds. Pass
// requireTokenForTokenized so a no-token beacon only matches a row that itself has no active
// token.
$allowedTables = array('ordrer', 'kladdeliste');
$table = isset($_POST['table']) ? $_POST['table'] : 'ordrer';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tidspkt = isset($_POST['tidspkt']) ? $_POST['tidspkt'] : '';
$lockToken = isset($_POST['lockToken']) ? $_POST['lockToken'] : '';
if ($id > 0 && ($tidspkt !== '' || $lockToken !== '') && !empty($brugernavn) && in_array($table, $allowedTables)) {
    unlock_record($table, $id, $brugernavn, $tidspkt !== '' ? $tidspkt : null, $lockToken !== '' ? $lockToken : null, true);
}
?>
