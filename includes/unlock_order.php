<?php
@session_start();
$s_id = session_id();
include("../includes/connect.php");
include("../includes/online.php");

// 20260908 SZ SST-755: generalized from ordrer-only to a whitelisted table selector
// (kassekladde's kladdeliste table now also releases through here), and require the
// beacon's own $tidspkt to still match the DB row before clearing - so a stale tab's
// delayed beacon can't clobber a lock a newer tab has since acquired.
$allowedTables = array('ordrer', 'kladdeliste');
$table = isset($_POST['table']) ? $_POST['table'] : 'ordrer';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$tidspkt = isset($_POST['tidspkt']) ? $_POST['tidspkt'] : '';
if ($id > 0 && $tidspkt !== '' && !empty($brugernavn) && in_array($table, $allowedTables)) {
    if ($table == 'ordrer') {
        // 20260908 SZ SST-755 fix: the actual lock is $tidspkt - always clear it. Only $hvem is
        // display-only ("Performed by") for DO/DK (debitor) art since 2026-06-30 and is preserved
        // for those - but finans/ordre.php's own orders are ALSO art='DO'/'DK' and DO use tidspkt
        // as a real lock, so excluding the whole row (as an earlier version of this fix did) broke
        // finansbilag's beacon release entirely. Mirrors includes/luk.php's identical case.
        $qtxt = "UPDATE ordrer SET hvem = case when art in ('DO','DK') then hvem else '' end, tidspkt = '' WHERE id = '$id' AND hvem = '$brugernavn' AND tidspkt = '" . db_escape_string($tidspkt) . "'";
    } else {
        $qtxt = "UPDATE kladdeliste SET hvem = '', tidspkt = NULL WHERE id = '$id' AND hvem = '$brugernavn' AND tidspkt = '" . db_escape_string($tidspkt) . "'";
    }
    db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}
?>
