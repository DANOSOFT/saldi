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
// 20260924 SZ SST-755 (CodeRabbit): refresh_lock_token() now also requires the observed
//                  tidspkt, so a stale render can't overwrite a token a concurrent tidspkt
//                  change (a fresh acquire/save) has since replaced. Both functions now also
//                  check lock_token_column_exists() first - betweenUpdates.php adds the
//                  column at login/account-open, not on every request, so a session already
//                  in flight could otherwise hit a SQL error on a column that doesn't exist
//                  yet. Added $requireTokenForTokenized for luk.php/unlock_order.php: a
//                  release request that carries no lockToken at all (an old cached link, from
//                  before this feature) must only succeed against a row that itself has no
//                  active token - not skip the check entirely, or it can clear a lock a newer,
//                  tokenized render now holds. An explicit lock_token of '' (the sweep in
//                  kreditor/ordreliste.php passes this for a legacy NULL column value) matches
//                  the same "no active token" state.

if (!function_exists('lock_token_column_exists')) {
    /**
     * Whether $table currently has the lock_token column on this tenant.
     *
     * @param string $table
     * @return bool
     */
    function lock_token_column_exists(string $table): bool
    {
        global $db_type;
        $qtxt = "SELECT column_name FROM information_schema.columns"
            . " WHERE table_name = '" . db_escape_string($table) . "'"
            . " AND column_name = 'lock_token'"
            . (in_array($db_type, ['mysql', 'mysqli'], true)
                ? " AND table_schema = DATABASE()"
                : " AND table_schema = current_schema()");
        $q = db_select($qtxt, __FILE__ . ' linje ' . __LINE__);
        return (bool) ($q && db_fetch_array($q));
    }
}

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
 *   An empty string matches a row whose lock_token is empty or legacy NULL.
 * @param bool $requireTokenForTokenized When true and no $lockToken is given, only release a
 *   row whose own lock_token is empty or NULL - a caller that never observed a token (e.g. an
 *   old cached link, from before this feature) must not bypass a token a newer render has
 *   since stamped.
 * @return void
 */
function unlock_record($table, int $id, ?string $brugernavn = null, ?string $tidspkt = null, ?string $lockToken = null, bool $requireTokenForTokenized = false): void
{
    if (!$id || !in_array($table, ['kladdeliste', 'ordrer'], true)) {
        return;
    }

    $hasLockToken = lock_token_column_exists($table);
    if (!$hasLockToken) {
        // Migration hasn't reached this tenant yet - degrade to pre-token matching instead of
        // referencing a column that doesn't exist.
        $lockToken = null;
        $requireTokenForTokenized = false;
    }

    $where = "id=$id";
    if ($brugernavn !== null) {
        $where .= " and hvem='" . db_escape_string($brugernavn) . "'";
    }
    if ($tidspkt !== null) {
        $where .= " and tidspkt='" . db_escape_string($tidspkt) . "'";
    }
    if ($lockToken !== null) {
        if ($lockToken === '') {
            $where .= " and (lock_token is null or lock_token='')";
        } else {
            $where .= " and lock_token='" . db_escape_string($lockToken) . "'";
        }
    } elseif ($requireTokenForTokenized) {
        $where .= " and (lock_token is null or lock_token='')";
    }

    $lockTokenSet = $hasLockToken ? ", lock_token=''" : '';
    if ($table === 'ordrer') {
        $query = "update ordrer set tidspkt='', hvem = case when art in ('DO','DK') then hvem else '' end$lockTokenSet where $where";
    } else {
        $query = "update kladdeliste set tidspkt='', hvem=''$lockTokenSet where $where";
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
 * @param string $tidspkt The tidspkt this caller observed when it loaded the row - required so
 *   a stale render can't overwrite a token a concurrent tidspkt change has since replaced.
 * @return void
 */
function refresh_lock_token(string $table, int $id, string $brugernavn, string $token, string $tidspkt): void
{
    if (!$id || $brugernavn === '' || $token === '' || $tidspkt === '' || !in_array($table, ['kladdeliste', 'ordrer'], true)) {
        return;
    }
    if (!lock_token_column_exists($table)) {
        return;
    }
    db_modify(
        "update $table set lock_token='" . db_escape_string($token) . "' where id=$id and hvem='" . db_escape_string($brugernavn) . "' and tidspkt='" . db_escape_string($tidspkt) . "'",
        __FILE__ . ' linje ' . __LINE__
    );
}
