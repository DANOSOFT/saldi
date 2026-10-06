<?php
// includes/stdFunc/dunningCutoff.php
//
// History:
// 20260828 Sawaneh Created. debitor/ny_rykker.php computed its ffdage1/2/3 cutoffs as
//                  date('U') - days*86400; a calendar day is 23 or 25 hours on the
//                  Europe/Copenhagen DST switch days, so a run within an hour of midnight
//                  after a switch landed on the neighbouring date and shifted dunning
//                  eligibility by a day. Calendar-day arithmetic here instead.

if (!function_exists('dunning_cutoff_date')) {
/**
 * Latest date (Y-m-d) a post may fall due and still be $graceDays overdue today.
 *
 * @param int                    $graceDays  ffdage1/2/3 from the DIV settings.
 * @param DateTimeInterface|null $now        Injected by tests; defaults to now in the
 *                                           configured timezone.
 * @return string
 */
function dunning_cutoff_date($graceDays, $now = null) {
	$now = ($now === null) ? new DateTimeImmutable('now') : DateTimeImmutable::createFromInterface($now);
	return $now->setTime(0, 0)->modify('-' . (int)$graceDays . ' days')->format('Y-m-d');
}
}
