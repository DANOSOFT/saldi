<?php
// includes/opdat_func/reminderAutomation.php — ver 5.1.1 — 2026.11.17
// Copyright (c) 2026 Danosoft ApS. GNU GPL version 2 or later.
// 20261117 CDX/ASR Durable, opt-in reminder queue; installed by betweenUpdates.php only.

/** @return void Install the PostgreSQL reminder scheduler tables; never called by a dry run. */
function reminder_automation_schema_ensure(): void
{
    if (in_array($GLOBALS['db_type'], array('mysql', 'mysqli'), true)) return;
    db_modify("CREATE TABLE IF NOT EXISTS reminder_automation_config (
        id integer PRIMARY KEY CHECK (id = 1), mode varchar(12) NOT NULL DEFAULT 'off',
        binding varchar(64) NOT NULL DEFAULT '', actor text NOT NULL DEFAULT '',
        updated_at timestamptz NOT NULL DEFAULT current_timestamp, last_run timestamptz,
        last_error text NOT NULL DEFAULT ''
    )", __FILE__ . ' line ' . __LINE__);
    db_modify("CREATE TABLE IF NOT EXISTS reminder_automation_jobs (
        order_id integer PRIMARY KEY, previous_id integer, mode varchar(12) NOT NULL,
        state varchar(12) NOT NULL, created_at timestamptz NOT NULL DEFAULT current_timestamp,
        attempted_at timestamptz, sent_at timestamptz, sent_date date,
        channel varchar(12) NOT NULL DEFAULT '', error text NOT NULL DEFAULT '', actor text NOT NULL DEFAULT ''
    )", __FILE__ . ' line ' . __LINE__);
    db_modify("CREATE UNIQUE INDEX IF NOT EXISTS reminder_automation_previous_idx ON reminder_automation_jobs(previous_id)
        WHERE previous_id IS NOT NULL", __FILE__ . ' line ' . __LINE__);
    db_modify("CREATE TABLE IF NOT EXISTS reminder_automation_events (
        id bigserial PRIMARY KEY, order_id integer, action varchar(30) NOT NULL,
        actor text NOT NULL, detail text NOT NULL DEFAULT '', created_at timestamptz NOT NULL DEFAULT current_timestamp
    )", __FILE__ . ' line ' . __LINE__);
}
