<?php
// includes/opdat_func/reminderDispatch.php — ver 5.1.1 — 2026.11.26
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261126 ASR Review GPT-R-4: reminder_dispatch, the day a reminder first went out (mailed or printed), for renteloven § 9 b's 10 days between reminders; installed by betweenUpdates.php.
// 20261126 ASR Review GPT-G-7: reminder_dispatch_since_ensure() records the first reminder (ordrer.id) made while reminder_dispatch is there, so a later reminder without a row is known not to have been sent.

require_once __DIR__ . '/databaseDialect.php';

/**
 * Install reminder_dispatch: one row per reminder order (ordrer.id, art R1-R3) with the day it first went out, by mail or
 * on paper, written by reminder_dispatch_record() (includes/reminderLegal.php). Classic reminders had no such date: the
 * 10 days to the next reminder with a fee were counted from the day the reminder was made. PostgreSQL and MySQL.
 * Runs at every login until the table is there; on PostgreSQL two first logins at once take turns (advisory lock), as
 * CREATE TABLE IF NOT EXISTS can fail for the second one there.
 *
 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
 * @return void
 */
function reminder_dispatch_schema_ensure(string $dbType): void
{
    $qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = 'reminder_dispatch'" . opdat_schema_filter($dbType);
    if (db_fetch_array(db_select($qtxt, __FILE__ . ' line ' . __LINE__))) return;
    $pg = !opdat_is_mysql($dbType);
    if ($pg) db_select("SELECT pg_advisory_lock(hashtext('saldi_reminder_dispatch'))", __FILE__ . ' line ' . __LINE__);
    try {
        db_modify("CREATE TABLE IF NOT EXISTS reminder_dispatch (order_id integer NOT NULL PRIMARY KEY, sent_date date NOT NULL,
            channel varchar(12) NOT NULL DEFAULT '')", __FILE__ . ' line ' . __LINE__);
    } finally {
        if ($pg) db_select("SELECT pg_advisory_unlock(hashtext('saldi_reminder_dispatch'))", __FILE__ . ' line ' . __LINE__);
    }
}

/**
 * Record from which reminder on Saldi records the day reminders go out: reminder_dispatch_since holds one row (id 1) whose
 * first_order_id is the highest ordrer.id when it is recorded, plus 1. reminder_dispatch_record() writes a row for every
 * reminder mailed or printed from then on, so a reminder with that id or a higher one and no row (and no other record of
 * being sent) was never sent: it does not count towards renteloven § 9 b's three fees and ten days for its invoices
 * (reminder_claim_sent_date() in includes/reminderLegal.php). An older reminder may have gone out before Saldi recorded it
 * and still counts from its date. Order ids, not dates, make the line: a reminder's date can be set back by hand.
 * Recorded once, after reminder_dispatch exists; runs at every login until the row is there. PostgreSQL and MySQL.
 *
 * @param string $dbType $db_type from includes/connect.php ('postgresql', 'mysql' or 'mysqli').
 * @return void
 */
function reminder_dispatch_since_ensure(string $dbType): void
{
    $exists = static function (string $table) use ($dbType): bool {
        return (bool)db_fetch_array(db_select("SELECT table_name FROM information_schema.tables WHERE table_name = '$table'" . opdat_schema_filter($dbType), __FILE__ . ' line ' . __LINE__));
    };
    if (!$exists('reminder_dispatch')) return;
    if ($exists('reminder_dispatch_since') && db_fetch_array(db_select("SELECT id FROM reminder_dispatch_since WHERE id = 1", __FILE__ . ' line ' . __LINE__))) return;
    $mysql = opdat_is_mysql($dbType);
    if (!$mysql) db_select("SELECT pg_advisory_lock(hashtext('saldi_reminder_dispatch'))", __FILE__ . ' line ' . __LINE__);
    try {
        db_modify("CREATE TABLE IF NOT EXISTS reminder_dispatch_since (id integer NOT NULL PRIMARY KEY, first_order_id integer NOT NULL,
            recorded_on date NOT NULL)", __FILE__ . ' line ' . __LINE__);
        db_modify("INSERT " . ($mysql ? "IGNORE " : "") . "INTO reminder_dispatch_since (id, first_order_id, recorded_on)
            SELECT 1, coalesce(max(id), 0) + 1, current_date FROM ordrer" . ($mysql ? "" : " ON CONFLICT (id) DO NOTHING"), __FILE__ . ' line ' . __LINE__);
    } finally {
        if (!$mysql) db_select("SELECT pg_advisory_unlock(hashtext('saldi_reminder_dispatch'))", __FILE__ . ' line ' . __LINE__);
    }
}
