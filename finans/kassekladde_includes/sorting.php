<?php
// --- finans/kassekladde_includes/sorting.php --- 20261005 LOE SST-856 ---
//
// Which sorting the cash journal remembers, and when it is allowed to change.
//
// The chosen column and direction are stored per user in grupper ART='KASKL' kode='1' (box1 = column,
// box4 = direction) and applied to every later load. The form action URL carried kksort without kkdir,
// and the page read a missing direction as ascending, so every Gem, Enter, Opslag, Udlign, Simuler and
// Bogfoer silently wrote ascending over a descending choice. It only became visible on the next load -
// F5, returning from a clip, or attaching a bilag from the pool - where MB-41's pin no longer ordered
// the rows.
//
// The rules are decided here, without any database access, so they can be characterized directly:
//   - only a click on a header link may replace the saved sorting, because that is the only request
//     that carries both a whitelisted column and a direction
//   - a column the header links cannot send falls back to the journal's default order, so a stored or
//     posted value the page does not know cannot pick an arbitrary ORDER BY

/**
 * @return string[] The sort keys a header link may send.
 */
function kk_sort_columns() {
	return array('transdate,bilag', 'amount', 'bilag,transdate', 'pos');
}

/**
 * @param string|null $kksort Sort key from a request or from the saved row.
 * @return string That key when a header link can send it, otherwise the journal's default order.
 */
function kk_sort_key($kksort) {
	return in_array($kksort, kk_sort_columns(), true) ? $kksort : 'bilag,transdate';
}

/**
 * @param string|null $kkdir Direction from a request or from the saved row.
 * @return string 'asc' or 'desc'.
 */
function kk_sort_direction($kkdir) {
	return $kkdir === 'desc' ? 'desc' : 'asc';
}

/**
 * The sorting this request is allowed to save.
 *
 * @param string|null $kksort Sort key from the request.
 * @param string|null $kkdir  Direction from the request.
 * @return array|null array('sort' => ..., 'dir' => ...) for a header click, null for anything else -
 *                    a form action carries kksort without a direction and must save nothing.
 */
function kk_sort_click($kksort, $kkdir) {
	if (!in_array($kksort, kk_sort_columns(), true)) {
		return null;
	}
	if ($kkdir !== 'asc' && $kkdir !== 'desc') {
		return null;
	}
	return array('sort' => $kksort, 'dir' => $kkdir);
}
