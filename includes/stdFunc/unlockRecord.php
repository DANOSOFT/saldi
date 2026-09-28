<?php
// 20260907 CDX/LH Keep the close endpoint's unlock operation within its supported tables.

/**
 * Release an order or journal lock, retaining the responsible user on sales orders.
 *
 * @param mixed $table Request-supplied table name; only exact allowlisted names are accepted.
 * @return void
 */
function unlock_record($table, int $id): void
{
    if (!$id || !in_array($table, ['kladdeliste', 'ordrer'], true)) {
        return;
    }

    if ($table === 'ordrer') {
        $query = "update ordrer set tidspkt='', hvem = case when art in ('DO','DK') then hvem else '' end where id=$id";
    } else {
        $query = "update kladdeliste set tidspkt='', hvem='' where id=$id";
    }
    db_modify($query, __FILE__ . ' linje ' . __LINE__);
}
