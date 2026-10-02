<?php
// --- includes/docsIncludes/poolDuplicateMarker.php ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20260925 LOE MB-42 A bilag that reached the pool twice carries the same fakturanr, amount and
//                  date under two filenames, and the customer could not see that they were one
//                  invoice. A content hash cannot help with those historic rows (nor when the scan
//                  read the metadata off a different copy), so the list groups them on the three
//                  fields the accountant himself matches on. Kept out of _docPoolData.php so it can
//                  be tested without a database.

if (!function_exists('poolMarkDuplicates')) {
	/**
	 * Mark every pool row that repeats another row's identity.
	 *
	 * Rows are grouped on fakturanr + amount + the date part of file_date, and each member of a group
	 * of two or more gets duplicateOf listing the other members' filenames. A row missing any of the
	 * three fields is never grouped: without all three there is nothing an accountant could match on,
	 * and grouping on two of them would flag every invoice of the same amount on the same day.
	 *
	 * file_date is a varchar that has held two shapes ('Y-m-d H:i:s' and 'Y-m-d'), so it is compared
	 * on its date part only, in PHP, and the two shapes of the same day do group together.
	 *
	 * @param array $rows Rows as _docPoolData.php builds them: filename, invoiceNumber, amount, date.
	 * @return array The same rows, with duplicateOf set on the members of every group.
	 */
	function poolMarkDuplicates($rows)
	{
		$groups = array();
		foreach ($rows as $index => $row) {
			$invoice = trim(isset($row['invoiceNumber']) ? (string) $row['invoiceNumber'] : '');
			$amount = trim(isset($row['amount']) ? (string) $row['amount'] : '');
			$date = substr(trim(isset($row['date']) ? (string) $row['date'] : ''), 0, 10);
			if ($invoice === '' || $amount === '' || $date === '') {
				continue;
			}
			$groups[$invoice . '|' . $amount . '|' . $date][] = $index;
		}

		foreach ($groups as $group) {
			if (count($group) < 2) {
				continue;
			}
			foreach ($group as $index) {
				$others = array();
				foreach ($group as $other) {
					if ($other !== $index) {
						$others[] = $rows[$other]['filename'];
					}
				}
				$rows[$index]['duplicateOf'] = $others;
			}
		}

		return $rows;
	}
}
