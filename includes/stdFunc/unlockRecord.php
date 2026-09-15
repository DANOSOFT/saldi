<?php
// 20260907 CDX/LH Keep the close endpoint's unlock operation within its supported tables.
// 20260908 SZ SST-755: added optional $brugernavn/$tidspkt match conditions - a release
//                  request now only takes effect while it still names the tab's own lock
//                  owner and the tidspkt it observed when it loaded, so a stale tab's
//                  delayed release can't clobber a lock a newer tab has since acquired.

/**
 * Release an order or journal lock, retaining the responsible user on sales orders.
 *
 * @param mixed $table Request-supplied table name; only exact allowlisted names are accepted.
 * @param string|null $brugernavn When given, only release the lock if it's still held by
 *   this user - guards against releasing a lock a different user has since acquired.
 * @param string|null $tidspkt When given, only release the lock if its tidspkt still
 *   matches - guards against a stale tab releasing a lock a newer tab has since acquired.
 * @return void
 */
function unlock_record($table, int $id, ?string $brugernavn = null, ?string $tidspkt = null): void
{
    if (!$id || !in_array($table, ['kladdeliste', 'ordrer'], true)) {
        return;
    }

    $where = "id=$id";
    if ($brugernavn !== null) {
        $where .= " and hvem='" . db_escape_string($brugernavn) . "'";
    }
    if ($tidspkt !== null) {
        $where .= " and tidspkt='" . db_escape_string($tidspkt) . "'";
    }

    if ($table === 'ordrer') {
        $query = "update ordrer set tidspkt='', hvem = case when art in ('DO','DK') then hvem else '' end where $where";
    } else {
        $query = "update kladdeliste set tidspkt='', hvem='' where $where";
    }
    db_modify($query, __FILE__ . ' linje ' . __LINE__);
}
