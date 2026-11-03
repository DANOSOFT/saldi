<?php
// includes/stdFunc/dimensionSqlFragment.php
//
// Builds the department, employee and project filter fragment the Finans reports append to their
// transaktioner query. The values arrive from GET/POST, so each one is cast or escaped here; before
// this the three reports carried their own copy of the block, and a project code with an apostrophe
// broke the query in all three (SST-936).
//
// History:
// 20261103 LOE SST-936 Extracted from kontokort(), regnskab() and moms_transaktioner().
//              The three copies can no longer drift, and the values are cast or escaped once.

if (!function_exists('dimensionSqlFragment')) {
	/**
	 * Builds the SQL fragment for the department, employee and project filters of a report.
	 *
	 * @param mixed  $afd        Department number, cast to int.
	 * @param mixed  $ansatFra   First employee id, cast to int.
	 * @param mixed  $ansatTil   Last employee id; only its presence selects the list form.
	 * @param mixed  $ansatteId  Comma-separated employee ids the report resolved, each cast to int.
	 * @param string $projektFra Lower bound, or the LIKE term when no upper bound is given.
	 * @param string $projektTil Upper bound for the project column.
	 * @param string $alias      Table alias to prefix the columns with, e.g. 't.'; '' for none.
	 *
	 * @return string The fragment, ready to append to the query, or '' when no filter was asked for.
	 */
	function dimensionSqlFragment($afd, $ansatFra, $ansatTil, $ansatteId, $projektFra, $projektTil, $alias = '')
	{
		$tabel = ($alias === '') ? '' : trim($alias, '.') . '.';
		$dele = array();

		if ($afd || $afd == '0') {
			if (preg_match('/^\d+$/', (string)$afd)) {
				$dele[] = "and " . $tabel . "afd = " . (int)$afd;
			} else {
				// A department that is not a whole number cannot match anything. Casting "0.5" or
				// "invalid" to 0 would select the postings without a department.
				$dele[] = "and 1 = 0";
			}
		}

		$ansatFraErTal = preg_match('/^\d+$/', (string)$ansatFra) === 1;
		$ansatTilErTal = preg_match('/^\d+$/', (string)$ansatTil) === 1;
		if ($ansatFra && $ansatTil) {
			if (!$ansatFraErTal || !$ansatTilErTal) {
				// A bound that is not a whole number names no employee: the list would be built, or
				// cut down, from a value the request never expressed. Both bounds are checked, because
				// a fractional upper bound used to fall back to the first employee alone.
				$dele[] = "and 1 = 0";
			} else {
				$ansatte = array();
				foreach (explode(",", (string)$ansatteId) as $ansatId) {
					if (preg_match('/^\d+$/', (string)$ansatId) && (int)$ansatId > 0) {
						$ansatte[] = (int)$ansatId;
					}
				}
				if (!$ansatte && (int)$ansatFra > 0) {
					$ansatte[] = (int)$ansatFra;
				}
				if ($ansatte) {
					$dele[] = "and " . $tabel . "ansat in (" . implode(",", $ansatte) . ")";
				}
			}
		} elseif ($ansatFra) {
			if ($ansatFraErTal) {
				$dele[] = "and " . $tabel . "ansat = " . (int)$ansatFra;
			} else {
				$dele[] = "and 1 = 0";
			}
		}

		$fra = str2low((string)$projektFra);
		$til = str2low((string)$projektTil);
		if ($fra && $til && $fra != $til) {
			$dele[] = "and lower(" . $tabel . "projekt) >= '" . db_escape_string($fra) . "'"
				. " and lower(" . $tabel . "projekt) <= '" . db_escape_string($til) . "'";
		} elseif ($fra) {
			// '?' is the report's own wildcard for one character; the trailing '?' becomes '%'.
			$moenster = str_replace("?", "_", $fra);
			if (substr($moenster, -1) == '_') {
				while (substr($moenster, -1) == '_') {
					$moenster = substr($moenster, 0, strlen($moenster) - 1);
				}
				$moenster = str2low($moenster) . "%";
			}
			$dele[] = "and lower(" . $tabel . "projekt) LIKE '" . db_escape_string($moenster) . "'";
		}

		return $dele ? implode(" ", $dele) . " " : '';
	}
}
