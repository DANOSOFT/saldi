<?php
// 20260907 CDX/LH Keep the close endpoint's unlock operation within its supported tables.
// 20260908 SZ SST-755: added optional $brugernavn/$tidspkt match conditions - a release
//                  request now only takes effect while it still names the tab's own lock
//                  owner and the tidspkt it observed when it loaded, so a stale tab's
//                  delayed release can't clobber a lock a newer tab has since acquired.
// 20260923 SZ SST-755 (CodeRabbit): added $lockToken, matched separately from $tidspkt.
//                  $tidspkt is only refreshed on an explicit acquire/save, so two tabs open
//                  on the same record before either one saves still shared the same tidspkt
//                  and could release each other's lock. $lockToken is stamped fresh on every
//                  render via refresh_lock_token() below, so only the most recently rendered
//                  tab's link/beacon still matches.

/**
 * Release an order or journal lock, retaining the responsible user on sales orders.
 *
 * @param mixed $table Request-supplied table name; only exact allowlisted names are accepted.
 * @param string|null $brugernavn When given, only release the lock if it's still held by
 *   this user - guards against releasing a lock a different user has since acquired.
 * @param string|null $tidspkt When given, only release the lock if its tidspkt still
 *   matches - guards against a stale tab releasing a lock a newer tab has since acquired.
 * @param string|null $lockToken When given, only release the lock if its per-render
 *   lock_token still matches - guards against a different, still-open tab on the same
 *   record (which shares the same $tidspkt until it next saves) releasing this one's lock.
 * @return void
 */
function unlock_record($table, int $id, ?string $brugernavn = null, ?string $tidspkt = null, ?string $lockToken = null): void
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
    if ($lockToken !== null) {
        $where .= " and lock_token='" . db_escape_string($lockToken) . "'";
    }

    if ($table === 'ordrer') {
        $query = "update ordrer set tidspkt='', hvem = case when art in ('DO','DK') then hvem else '' end, lock_token='' where $where";
    } else {
        $query = "update kladdeliste set tidspkt='', hvem='', lock_token='' where $where";
    }
    db_modify($query, __FILE__ . ' linje ' . __LINE__);
}

/**
 * Stamp a fresh per-render token onto a lock this user currently holds. Called once per
 * page render (with the same random token reused for that render's exit-link and unload
 * beacon), so an older render's already-sent link/beacon carries a token this call has since
 * overwritten and simply fails to match - unlike $tidspkt, which only changes on an explicit
 * acquire or save, this changes on every render and so distinguishes "the tab that rendered
 * most recently" even when two tabs are open on the same record and neither has saved yet.
 *
 * @param string $table Only 'kladdeliste' or 'ordrer'; anything else is a no-op.
 * @param string $brugernavn Required non-empty - never stamps a token for an unauthenticated caller.
 * @return void
 */
function refresh_lock_token(string $table, int $id, string $brugernavn, string $token): void
{
    if (!$id || $brugernavn === '' || $token === '' || !in_array($table, ['kladdeliste', 'ordrer'], true)) {
        return;
    }
    db_modify(
        "update $table set lock_token='" . db_escape_string($token) . "' where id=$id and hvem='" . db_escape_string($brugernavn) . "'",
        __FILE__ . ' linje ' . __LINE__
    );
}
