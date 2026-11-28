<?php
// includes/stdFunc/orderLinePositions.php
//
// History:
// 20261105 CL/LH Created. Two users adding lines to one order gave the new lines the same posnr, and a save from a form drawn before a colleague's save moved one of the form's lines onto the colleague's new line (testlab concurrent-posting DEVY-1: 100, 100, then 100, 200, 200, 300).
//                The sales and purchase order forms carry the ids of the lines they were drawn with, and their save keeps the lines it did not show on positions of their own, under a lock on the order.
//                A sales order's sets are numbered under that lock too, and before the save looks for clashes, so the numbering cannot put a set row back on a line the save moved.
//                finans/ordre.php, the older copy of the sales order page, uses the same functions; it stores Pos as it is (1, 2, 3), so order_line_new_position() takes the step between positions.
//                So does debitor/ret_genfakt.php, which edits the lines of several subscription orders in one form.
//                A position these functions pick stays within the posnr column, which is smallint in regnskaber from Saldi's first versions (order_lines_position_max()).

if (!function_exists('order_lines_drawn')) {
/**
 * The ids of an order's lines, for the hidden field order_lines_drawn of its form: a line of the order that is not
 * among them when the form is saved was added or moved into the order after the form was drawn, so the form did not
 * show it. Read it before the lines the form draws. A line added in between is then drawn but not in the field, and
 * order_lines_added_since() leaves out the lines a form posts.
 *
 * @param int $orderId ordrer.id
 * @return string the ids in ascending order, separated by commas; '' for an order without lines
 */
function order_lines_drawn($orderId) {
	$orderId = (int)$orderId;
	$ids = array();
	$q = db_select("select id from ordrelinjer where ordre_id = $orderId order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ids[] = (int)$r['id'];
	}
	return implode(',', $ids);
}
}

if (!function_exists('order_lines_lock')) {
/**
 * Locks the order's header row until the caller's transaction ends, so two saves of one order write their lines and
 * positions one after the other: the second save reads the lines the first one committed. Call it as the first
 * statement of the transaction: on MySQL a transaction reads the snapshot of its first plain read, which must come
 * after the lock.
 *
 * @param int $orderId ordrer.id
 * @return void
 */
function order_lines_lock($orderId) {
	$orderId = (int)$orderId;
	if ($orderId > 0) {
		db_fetch_array(db_select("select id from ordrer where id = $orderId for update", __FILE__ . " linje " . __LINE__));
	}
}
}

if (!function_exists('order_lines_position_max')) {
/**
 * The highest posnr ordrelinjer.posnr holds. Regnskaber created by Saldi's first versions have the column as smallint
 * (32767, includes/opdat_0.php), later ones as integer. A position the functions below pick stays at or below it, so the
 * database does not refuse the save. Read once per request.
 *
 * @return int
 */
function order_lines_position_max() {
	static $max = null;
	if ($max === null) {
		$qtxt = "select data_type as data_type from information_schema.columns where table_name = 'ordrelinjer' and column_name = 'posnr'" . saldi_schema_filter();
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$type = $r ? strtolower(trim((string)$r['data_type'])) : '';
		$limits = array('tinyint' => 127, 'smallint' => 32767, 'mediumint' => 8388607, 'int' => 2147483647, 'integer' => 2147483647, 'bigint' => PHP_INT_MAX);
		// A column that could not be read is taken as the smallest one Saldi has made.
		$max = $limits[$type] ?? ($type === '' ? 32767 : 2147483647);
	}
	return $max;
}
}

if (!function_exists('order_lines_added_since')) {
/**
 * The lines of an order its form did not show: not among the ids the form was drawn with (order_lines_drawn), and not
 * posted by the form. That is a line a colleague added, and a line moved in from another order, which keeps its id.
 * Call it under order_lines_lock() (or another lock on the order row), before the save writes a line, so the lines the
 * save adds itself are not among them.
 *
 * @param int $orderId ordrer.id
 * @param mixed $drawn the posted order_lines_drawn. A form without it (drawn before the field existed) counts no line
 *        as unseen, and the save works as it did.
 * @param mixed $postedLineIds the posted linje_id[] of the lines the form showed
 * @return list<int> ordrelinjer.id
 */
function order_lines_added_since($orderId, $drawn, $postedLineIds) {
	$orderId = (int)$orderId;
	if (!$orderId || !is_string($drawn) || !preg_match('/^[0-9,]*$/', $drawn)) {
		return array();
	}
	$shown = array();
	foreach (explode(',', $drawn) as $lineId) {
		if ($lineId !== '') {
			$shown[(int)$lineId] = true;
		}
	}
	foreach (is_array($postedLineIds) ? $postedLineIds : array() as $lineId) {
		$shown[(int)$lineId] = true;
	}
	$added = array();
	$q = db_select("select id from ordrelinjer where ordre_id = $orderId order by id", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!isset($shown[(int)$r['id']])) {
			$added[] = (int)$r['id'];
		}
	}
	return $added;
}
}

if (!function_exists('order_lines_listed')) {
/**
 * The positions of the lines an order form lists: posnr above 0, and not a part of a set (a numeric samlevare points at
 * the set's line, which is the one listed).
 *
 * @param int $orderId ordrer.id
 * @return array<int, int> posnr by ordrelinjer.id
 */
function order_lines_listed($orderId) {
	$orderId = (int)$orderId;
	$lines = array();
	$q = db_select("select id, posnr, samlevare from ordrelinjer where ordre_id = $orderId and posnr > 0", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!is_numeric($r['samlevare'])) {
			$lines[(int)$r['id']] = (int)$r['posnr'];
		}
	}
	return $lines;
}
}

if (!function_exists('order_line_new_position')) {
/**
 * The posnr for the new line a form saves. Pos left at the number the form offered (or set higher) puts the line after every
 * line the form showed, so it also goes after the lines added since the form was drawn when one of them sits at or after
 * that position, one step after the last of them or, where the column ends sooner, on the highest posnr it holds. When the
 * last of them already sits there, the line keeps the form's position and order_lines_keep_added_apart() moves the line
 * on it. Any other position is the form's: it is a place among the lines the form showed.
 *
 * @param int $orderId ordrer.id
 * @param list<int> $addedSince order_lines_added_since()
 * @param mixed $posnr the form's posnr for the new line; a value that is not a position is returned as it is
 * @param int $step what one Pos is in posnr: 100 on debitor/ordre.php and kreditor/ordre.php, 1 on finans/ordre.php
 * @return mixed
 */
function order_line_new_position($orderId, array $addedSince, $posnr, $step = 100) {
	if (!$addedSince || !is_numeric($posnr) || $posnr <= 0) {
		return $posnr;
	}
	$added = array_flip($addedSince);
	$lastShown = $last = 0;
	foreach (order_lines_listed($orderId) as $lineId => $linePos) {
		$last = max($last, $linePos);
		if (!isset($added[$lineId])) {
			$lastShown = max($lastShown, $linePos);
		}
	}
	$max = order_lines_position_max();
	if ($posnr > $lastShown && $last >= $posnr && $last < $max) {
		return min($last + $step, $max);
	}
	return $posnr;
}
}

if (!function_exists('order_sets_number')) {
/**
 * Numbers the rows of each set (sæt) of a sales order the way debitor/ordre.php shows sets: the set's first row, in
 * samlevare and id order, keeps its posnr and the others follow it by one each. Moved here from debitor/ordre.php, which
 * calls it on every opening of an order while the order is not invoiced. Call it under order_lines_lock().
 *
 * @param int $orderId ordrer.id
 * @return void
 */
function order_sets_number($orderId) {
	$orderId = (int)$orderId;
	$saets = $posnr = array();
	$x = 0;
	$qtxt = "select id,saet,samlevare,posnr from ordrelinjer where ordre_id=$orderId and saet>0 order by saet,samlevare,id";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (in_array($r['saet'], $saets)) {
			$posnr[$x]++;
			db_modify("update ordrelinjer set posnr='$posnr[$x]' where id='$r[id]'", __FILE__ . " linje " . __LINE__);
		} else {
			$x++;
			$saets[$x] = $r['saet'];
			$posnr[$x] = $r['posnr'];
		}
	}
}
}

if (!function_exists('order_lines_keep_added_apart')) {
/**
 * After a save, gives the lines its form did not show positions of their own. The save writes the positions the form
 * showed (1, 2, 3 ..., x 100 on debitor/ordre.php and kreditor/ordre.php), and one of them can be the position of a line
 * the form never showed.
 * With $numberSets the order's sets are numbered first, as debitor/ordre.php numbers them after the save, and the rows of
 * a set count as one block, from the set's first position to its last. A set the form did not show moves as a whole.
 * The lines and sets are taken in position order, the form's before an unseen one on the same position. An unseen one
 * that starts on or before the end of the one before it, or overlaps a line or set the form showed, moves to the first
 * position after the one before it where it overlaps nothing the form showed. So it stays next to where it was, and the
 * form's lines keep the positions the save gave them. Where that runs past the highest posnr the column holds
 * (order_lines_position_max()), it goes to the first place from posnr 1 where it overlaps no line or set at all.
 * Call it under order_lines_lock(), in the transaction that wrote the lines.
 *
 * @param int $orderId ordrer.id
 * @param list<int> $addedSince order_lines_added_since(), from before the save wrote a line
 * @param bool $numberSets the order numbers its sets (debitor/ordre.php with Vis sæt, POS box12)
 * @return void
 */
function order_lines_keep_added_apart($orderId, array $addedSince, $numberSets = false) {
	if (!$addedSince) {
		return;
	}
	$orderId = (int)$orderId;
	if ($numberSets) {
		order_sets_number($orderId);
	}
	$added = array_flip($addedSince);
	// A unit is a listed line, or every row of a set: start and end position, whether the form showed it, its rows.
	$units = array();
	$q = db_select("select id, posnr, samlevare, saet from ordrelinjer where ordre_id = $orderId", __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$lineId = (int)$r['id'];
		$linePos = (int)$r['posnr'];
		if ($numberSets && (int)$r['saet'] > 0) {
			$key = 'saet' . (int)$r['saet'];
		} elseif ($linePos > 0 && !is_numeric($r['samlevare'])) {
			$key = 'line' . $lineId;
		} else {
			continue;
		}
		if (!isset($units[$key])) {
			$units[$key] = array('start' => $linePos, 'end' => $linePos, 'seen' => false, 'first' => $lineId, 'rows' => array());
		}
		$unit = &$units[$key];
		$unit['start'] = min($unit['start'], $linePos);
		$unit['end'] = max($unit['end'], $linePos);
		$unit['seen'] = $unit['seen'] || !isset($added[$lineId]);
		$unit['first'] = min($unit['first'], $lineId);
		$unit['rows'][$lineId] = $linePos;
		unset($unit);
	}
	$taken = array();
	foreach ($units as $key => $unit) {
		if ($unit['start'] <= 0) {
			unset($units[$key]);
		} elseif ($unit['seen']) {
			$taken[] = array($unit['start'], $unit['end']);
		}
	}
	$clashes = static function ($from, $to) use ($taken) {
		foreach ($taken as list($start, $end)) {
			if ($from <= $end && $to >= $start) {
				return true;
			}
		}
		return false;
	};
	// The first start from posnr 1 where a block of $length + 1 positions overlaps none of $occupied and ends within $max.
	$firstFree = static function (array $occupied, $length, $max) {
		usort($occupied, static function (array $a, array $b) {
			return $a[0] <=> $b[0];
		});
		$start = 1;
		foreach ($occupied as list($from, $to)) {
			if ($from > $start + $length) {
				break;
			}
			$start = max($start, $to + 1);
		}
		return $start + $length <= $max ? $start : null;
	};
	uasort($units, static function (array $a, array $b) {
		return array($a['start'], $a['seen'] ? 0 : 1, $a['first']) <=> array($b['start'], $b['seen'] ? 0 : 1, $b['first']);
	});
	$max = order_lines_position_max();
	$standing = array(); // where each unit stands now: start and end
	foreach ($units as $key => $unit) {
		$standing[$key] = array($unit['start'], $unit['end']);
	}
	$before = 0;
	foreach ($units as $key => $unit) {
		if (!$unit['seen'] && ($unit['start'] <= $before || $clashes($unit['start'], $unit['end']))) {
			$length = $unit['end'] - $unit['start'];
			$start = $before + 1;
			while ($clashes($start, $start + $length)) {
				$start++;
			}
			if ($start + $length > $max) {
				// No room after it within the column: the first place where it overlaps no other line or set.
				$others = $standing;
				unset($others[$key]);
				$start = $firstFree($others, $length, $max);
			}
			if ($start !== null) {
				foreach ($unit['rows'] as $lineId => $linePos) {
					$linePos += $start - $unit['start'];
					db_modify("update ordrelinjer set posnr = $linePos where id = $lineId", __FILE__ . " linje " . __LINE__);
				}
				$unit['end'] += $start - $unit['start'];
				$standing[$key] = array($start, $unit['end']);
			}
		}
		$before = max($before, $unit['end']);
	}
}
}
