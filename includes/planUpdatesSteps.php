<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/planUpdatesSteps.php --- ai/mcp-assistant --- 2026.10.08
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
//
// Shared, ordered schema steps for trusted runtime and login entrypoints.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH An existing table gets its missing indexes, so an interrupted step repairs itself.
// 20261002 CDX/LH Add companion shared foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

declare(strict_types=1);

if (!function_exists('saldi_plan_update_steps')) {
	/** @return array<string,callable(string,callable,callable):void> */
	function saldi_plan_update_steps(): array
	{
		$definitions = [
			'agent_grants' => ['token_hash', [
				"CREATE TABLE agent_grants ( id serial PRIMARY KEY, principal varchar(96) NOT NULL, label varchar(100) NOT NULL, user_id integer NOT NULL, user_hash char(32) NOT NULL, kind varchar(16) NOT NULL, scopes varchar(64) NOT NULL, token_hash char(64) NOT NULL, session_ref varchar(32), family_id varchar(64), created_at timestamp NOT NULL, expires_at timestamp NOT NULL, revoked_at timestamp, revoked_by integer, last_used_at timestamp, use_count integer NOT NULL DEFAULT 0, max_uses integer )",
				"CREATE UNIQUE INDEX agent_grants_token_hash_idx ON agent_grants (token_hash)",
				"CREATE INDEX agent_grants_user_idx ON agent_grants (user_id, kind)",
			]],
			'inbox_items' => ['open_key', [
				"CREATE TABLE inbox_items ( id serial PRIMARY KEY, origin varchar(16) NOT NULL, kind varchar(48) NOT NULL, revision integer NOT NULL DEFAULT 1, content_digest char(64) NOT NULL, subject_type varchar(32), subject_id varchar(64), title varchar(255) NOT NULL, payload text NOT NULL, evidence text, proposed_rev varchar(64), status varchar(16) NOT NULL DEFAULT 'open', attention varchar(16) NOT NULL DEFAULT 'ambient', visibility varchar(16) NOT NULL DEFAULT 'owner', assignee_permission varchar(48), owner_user_id integer, created_by varchar(96) NOT NULL, grant_id integer, request_key varchar(160), dedupe_key varchar(128), open_key varchar(180), due_at timestamp, expires_at timestamp, decided_by integer, decided_at timestamp, decision_note varchar(255), result text, created_at timestamp NOT NULL, updated_at timestamp NOT NULL )",
				"CREATE UNIQUE INDEX inbox_items_open_key_idx ON inbox_items (open_key)",
				"CREATE UNIQUE INDEX inbox_items_request_key_idx ON inbox_items (request_key)",
				"CREATE INDEX inbox_items_status_idx ON inbox_items (status, kind)",
				"CREATE INDEX inbox_items_owner_idx ON inbox_items (owner_user_id, status)",
			]],
			'decision_log' => ['idempotency_key', [
				"CREATE TABLE decision_log ( id serial PRIMARY KEY, created_at timestamp NOT NULL, event varchar(24) NOT NULL, inbox_item_id integer, kind varchar(48), subject_type varchar(32), subject_id varchar(64), actor varchar(96) NOT NULL, on_behalf_of integer, approved_by integer, grant_id integer, idempotency_key varchar(128), engine varchar(64), model varchar(96), cost_usd numeric(12,6), detail text, undo_ref varchar(128) )",
				"CREATE UNIQUE INDEX decision_log_idem_idx ON decision_log (idempotency_key)",
				"CREATE INDEX decision_log_item_idx ON decision_log (inbox_item_id)",
			]],
			'tool_calls' => ['args_hash', [
				"CREATE TABLE tool_calls ( id serial PRIMARY KEY, created_at timestamp NOT NULL, grant_id integer, principal varchar(96) NOT NULL, user_id integer, tool varchar(64) NOT NULL, args_hash char(64) NOT NULL, status varchar(16) NOT NULL, error_code varchar(32), duration_ms integer, result_count integer )",
				"CREATE INDEX tool_calls_grant_idx ON tool_calls (grant_id, created_at)",
			]],
			'assistant_memory' => ['retracted_at', [
				"CREATE TABLE assistant_memory ( id serial PRIMARY KEY, scope varchar(16) NOT NULL, user_id integer, fact varchar(300) NOT NULL, source_principal varchar(96) NOT NULL, source_note varchar(255), inbox_item_id integer, approved_by integer, created_at timestamp NOT NULL, retracted_at timestamp, retracted_by integer, retract_reason varchar(255) )",
				"CREATE INDEX assistant_memory_scope_idx ON assistant_memory (scope, user_id)",
			]],
			'automation_jobs' => ['lease_token', [
				"CREATE TABLE automation_jobs ( id serial PRIMARY KEY, job_type varchar(64) NOT NULL, subject_type varchar(32), subject_id varchar(64), payload text NOT NULL, status varchar(16) NOT NULL DEFAULT 'pending', run_after timestamp NOT NULL, dedupe_key varchar(128), attempts integer NOT NULL DEFAULT 0, max_attempts integer NOT NULL DEFAULT 3, lease_token varchar(64), locked_until timestamp, last_error text, result text, created_at timestamp NOT NULL, finished_at timestamp )",
				"CREATE UNIQUE INDEX automation_jobs_dedupe_idx ON automation_jobs (dedupe_key)",
				"CREATE INDEX automation_jobs_due_idx ON automation_jobs (status, run_after)",
			]],

			'posting_rules' => ['match_type', [
				"CREATE TABLE posting_rules ( id serial PRIMARY KEY, status varchar(16) NOT NULL DEFAULT 'active', match_type varchar(16) NOT NULL, match_value varchar(100) NOT NULL, account varchar(15) NOT NULL, vat_code varchar(8) NOT NULL DEFAULT '', description varchar(100), note varchar(255), inbox_item_id integer, created_by varchar(96) NOT NULL, approved_by integer, created_at timestamp NOT NULL, retired_at timestamp, retired_by integer, retire_reason varchar(255) )",
				"CREATE INDEX posting_rules_match_idx ON posting_rules (status, match_type, match_value)",
			]],
			'assist_events' => ['event', [
				"CREATE TABLE assist_events ( id serial PRIMARY KEY, created_at timestamp NOT NULL, user_id integer, event varchar(32) NOT NULL, subject varchar(64), source varchar(16), detail text )",
				"CREATE INDEX assist_events_event_idx ON assist_events (event, created_at)",
			]],
			'automation_state' => ['state_key', [
				"CREATE TABLE automation_state ( state_key varchar(128) PRIMARY KEY, state_value text NOT NULL, updated_at timestamp NOT NULL )",
			]],
			'bank_import_lines' => ['line_hash', [
				"CREATE TABLE bank_import_lines ( id serial PRIMARY KEY, batch_id varchar(32) NOT NULL, line_no integer NOT NULL, bank_account varchar(15) NOT NULL, transdate date NOT NULL, description varchar(255) NOT NULL, amount numeric(15,3) NOT NULL, currency varchar(3) NOT NULL, balance numeric(15,3), reference varchar(100), line_hash char(64) NOT NULL, status varchar(16) NOT NULL DEFAULT 'new', proposal text, inbox_item_id integer, created_by integer NOT NULL, created_at timestamp NOT NULL )",
				"CREATE INDEX bank_import_lines_batch_idx ON bank_import_lines (batch_id, line_no)",
				"CREATE INDEX bank_import_lines_hash_idx ON bank_import_lines (line_hash)",
			]],
		];
		$steps = [];
		foreach ($definitions as $table => [$signature, $statements]) {
			$prefix = $table === 'bank_import_lines' ? 's92_' : (in_array($table, ['posting_rules', 'assist_events', 'automation_state'], true) ? 's91_' : 's90_');
			$steps[$prefix . $table] = static function (string $dbType, callable $exec, callable $selectOne) use ($table, $signature, $statements): void {
				$pg = !in_array($dbType, ['mysql', 'mysqli'], true);
				$schema = $pg ? 'current_schema()' : 'DATABASE()';
				if ($selectOne("SELECT 1 FROM information_schema.tables WHERE table_schema = $schema AND table_name = '$table'")) {
					if (!$selectOne("SELECT 1 FROM information_schema.columns WHERE table_schema = $schema AND table_name = '$table' AND column_name = '$signature'")) {
						throw new RuntimeException('incompatible existing table ' . $table);
					}
					// Repair a run that stopped after CREATE TABLE: add any index that is still missing.
					foreach ($statements as $sql) {
						if (!preg_match('/^CREATE (?:UNIQUE )?INDEX ([a-z0-9_]+) ON /', $sql, $m)) {
							continue;
						}
						$index = $m[1];
						$present = $pg
							? $selectOne("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = '$table' AND indexname = '$index'")
							: $selectOne("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = '$table' AND index_name = '$index'");
						if (!$present) {
							$exec($sql);
						}
					}
					return;
				}
				foreach ($statements as $sql) {
					if (!$pg) {
						$sql = str_replace(['serial PRIMARY KEY', 'timestamp', 'numeric'], ['int NOT NULL AUTO_INCREMENT PRIMARY KEY', 'datetime', 'decimal'], $sql);
					}
					$exec($sql);
				}
			};
		}
		$steps['s93_agent_grants_family_id'] = static function (string $dbType, callable $exec, callable $selectOne): void {
			$pg = !in_array($dbType, ['mysql', 'mysqli'], true);
			$schema = $pg ? 'current_schema()' : 'DATABASE()';
			if (!$selectOne("SELECT 1 FROM information_schema.columns WHERE table_schema = $schema AND table_name = 'agent_grants' AND column_name = 'family_id'")) {
				$exec('ALTER TABLE agent_grants ADD COLUMN family_id varchar(64)');
			}
			$index = $pg
				? $selectOne("SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = 'agent_grants' AND indexname = 'agent_grants_family_idx'")
				: $selectOne("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'agent_grants' AND index_name = 'agent_grants_family_idx'");
			if (!$index) {
				$exec('CREATE INDEX agent_grants_family_idx ON agent_grants (family_id, kind)');
			}
		};
		return $steps;
	}
}
