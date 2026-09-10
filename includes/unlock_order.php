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
$allowedTables = array('ordrer', 'kladdeliste');
$table = isset($_POST['table']) ? $_POST['table'] : 'ordrer';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tidspkt = isset($_POST['tidspkt']) ? $_POST['tidspkt'] : '';
if ($id > 0 && $tidspkt !== '' && !empty($brugernavn) && in_array($table, $allowedTables)) {
    unlock_record($table, $id, $brugernavn, $tidspkt);
}
?>
