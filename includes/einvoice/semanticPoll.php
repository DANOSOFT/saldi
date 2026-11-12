<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/einvoice/semanticPoll.php --- ver 5.1.1 --- 2026-11-12 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261112 CL/MMR SD-815 Created. The cron run behind tools/semanticStatusPoll.php: which regnskaber and send-log rows are asked about, the run's time and row budget, the lock that keeps two runs apart and the rotation that lets every regnskab have its turn.
//                 semantic_poll_incoming() is the place where the pull of incoming documents will be called.

include_once(__DIR__ . '/semanticClient.php');
include_once(__DIR__ . '/semanticSendLog.php');
include_once(__DIR__ . '/semanticStatus.php');
include_once(__DIR__ . '/semanticOnboarding.php');

if (!class_exists('SemanticPollBudget')) {
	/**
	 * How much one run may still do: a time limit and a number of send-log rows (one or two Semantic calls each).
	 * The clock is injectable, so the budget is tested without waiting.
	 */
	final class SemanticPollBudget {
		/** @var float */
		private $deadline;
		/** @var int */
		private $rowsLeft;
		/** @var callable(): float */
		private $clock;

		/**
		 * @param int                   $seconds Run time, from now.
		 * @param int                   $maxRows Send-log rows for the whole run.
		 * @param (callable(): float)|null $clock Seconds as a float; null = microtime(true).
		 */
		public function __construct($seconds, $maxRows, $clock = null) {
			$this->clock = is_callable($clock) ? $clock : static function () {
				return microtime(true);
			};
			$this->deadline = $this->now() + max(0, (int)$seconds);
			$this->rowsLeft = max(0, (int)$maxRows);
		}

		/**
		 * @return float
		 */
		public function now() {
			return (float)call_user_func($this->clock);
		}

		/**
		 * Seconds left, never below 0.
		 *
		 * @return float
		 */
		public function timeLeft() {
			return max(0.0, $this->deadline - $this->now());
		}

		/**
		 * @return int
		 */
		public function rowsLeft() {
			return $this->rowsLeft;
		}

		/**
		 * Why the run must stop: 'time', 'rows', or '' while it may go on.
		 *
		 * @return string
		 */
		public function reason() {
			if ($this->timeLeft() <= 0.0) {
				return 'time';
			}
			return ($this->rowsLeft <= 0) ? 'rows' : '';
		}

		/**
		 * @return bool
		 */
		public function exhausted() {
			return $this->reason() !== '';
		}

		/**
		 * Take one row from the budget before asking Semantic about it.
		 *
		 * @return bool False when the budget is used up (nothing is taken).
		 */
		public function takeRow() {
			if ($this->exhausted()) {
				return false;
			}
			$this->rowsLeft--;
			return true;
		}
	}
}

if (!function_exists('semantic_poll_defaults')) {
	/**
	 * The run's defaults, chosen for a cron line every 10 minutes: a run ends well within the interval.
	 *
	 * @return array{limit: int, maxAgeDays: int, budgetSeconds: int, maxRows: int}
	 */
	function semantic_poll_defaults() {
		return array('limit' => 50, 'maxAgeDays' => 30, 'budgetSeconds' => 240, 'maxRows' => 500);
	}
}

if (!function_exists('semantic_poll_arguments')) {
	/**
	 * Read the command line of tools/semanticStatusPoll.php.
	 *
	 * --db=<database> (repeatable) or --all; --limit=<rows per regnskab, 1..500>; --max-age-days=<1..365>;
	 * --budget=<seconds, 10..3600>; --max-rows=<rows per run, 1..10000>; --verbose; --help.
	 *
	 * @param list<string> $args $argv without the script name.
	 * @return array{dbs: list<string>, all: bool, limit: int, maxAgeDays: int, budgetSeconds: int, maxRows: int, verbose: bool, help: bool, error: string}
	 */
	function semantic_poll_arguments(array $args) {
		$defaults = semantic_poll_defaults();
		$out = array('dbs' => array(), 'all' => false, 'limit' => $defaults['limit'], 'maxAgeDays' => $defaults['maxAgeDays'],
			'budgetSeconds' => $defaults['budgetSeconds'], 'maxRows' => $defaults['maxRows'], 'verbose' => false, 'help' => false, 'error' => '');
		$numbers = array(
			'--limit=' => array('limit', 1, 500),
			'--max-age-days=' => array('maxAgeDays', 1, 365),
			'--budget=' => array('budgetSeconds', 10, 3600),
			'--max-rows=' => array('maxRows', 1, 10000),
		);
		foreach ($args as $arg) {
			$arg = (string)$arg;
			if ($arg === '--all') {
				$out['all'] = true;
				continue;
			}
			if ($arg === '--verbose' || $arg === '-v') {
				$out['verbose'] = true;
				continue;
			}
			if ($arg === '--help' || $arg === '-h') {
				$out['help'] = true;
				continue;
			}
			if (strncmp($arg, '--db=', 5) === 0) {
				$name = substr($arg, 5);
				if (!semantic_webhook_db_name_valid($name)) {
					$out['error'] = "not a database name: $name";
				} elseif (!in_array($name, $out['dbs'], true)) {
					$out['dbs'][] = $name;
				}
				continue;
			}
			$matched = false;
			foreach ($numbers as $prefix => $spec) {
				if (strncmp($arg, $prefix, strlen($prefix)) === 0) {
					$matched = true;
					$value = substr($arg, strlen($prefix));
					if (!preg_match('/^[0-9]{1,9}$/', $value)) {
						$out['error'] = "not a whole number: $arg";
					} else {
						$out[$spec[0]] = max($spec[1], min($spec[2], (int)$value));
					}
				}
			}
			if (!$matched) {
				$out['error'] = "unknown argument: $arg";
			}
		}
		if ($out['error'] === '' && !$out['help']) {
			if ($out['all'] && $out['dbs']) {
				$out['error'] = 'give --db=<database> or --all, not both';
			} elseif (!$out['all'] && !$out['dbs']) {
				$out['error'] = 'give --db=<database> or --all';
			}
		}
		return $out;
	}
}

if (!function_exists('semantic_poll_rotate')) {
	/**
	 * The regnskaber in the order this run visits them: sorted by name, starting after the one the previous run
	 * finished last. A run that stops on its budget therefore never starves the regnskaber at the end of the list.
	 *
	 * @param list<string> $dbs
	 * @param string       $lastDone The last regnskab the previous run finished ('' = start from the top).
	 * @return list<string>
	 */
	function semantic_poll_rotate(array $dbs, $lastDone) {
		$dbs = array_values(array_unique(array_map('strval', $dbs)));
		sort($dbs, SORT_STRING);
		$lastDone = (string)$lastDone;
		if ($lastDone === '' || !$dbs) {
			return $dbs;
		}
		$start = 0;
		foreach ($dbs as $index => $name) {
			if (strcmp($name, $lastDone) > 0) {
				$start = $index;
				break;
			}
			$start = $index + 1;
		}
		$start = $start % count($dbs);
		return array_merge(array_slice($dbs, $start), array_slice($dbs, 0, $start));
	}
}

if (!function_exists('semantic_poll_cutoff')) {
	/**
	 * The oldest created time still asked about, for einvoice_sendlog_find_open_sql().
	 *
	 * @param int $maxAgeDays
	 * @param int $now Unix time.
	 * @return string 'Y-m-d H:i:s' in PHP's time zone, as the send log's own query uses it.
	 */
	function semantic_poll_cutoff($maxAgeDays, $now) {
		return date('Y-m-d H:i:s', (int)$now - max(1, (int)$maxAgeDays) * 86400);
	}
}

if (!function_exists('semantic_poll_lock')) {
	/**
	 * Take the run lock without waiting: an exclusive flock() on a file. The operating system drops it when the
	 * process ends, also after a crash, so a stale lock file never blocks later runs.
	 *
	 * @param string $path
	 * @return array{handle: resource|null, reason: string} reason 'locked' (ours), 'busy' (another run has it) or 'open_failed'.
	 */
	function semantic_poll_lock($path) {
		$handle = @fopen((string)$path, 'c');
		if ($handle === false) {
			return array('handle' => null, 'reason' => 'open_failed');
		}
		if (!flock($handle, LOCK_EX | LOCK_NB)) {
			fclose($handle);
			return array('handle' => null, 'reason' => 'busy');
		}
		ftruncate($handle, 0);
		fwrite($handle, getmypid() . ' ' . date('Y-m-d H:i:s') . "\n");
		fflush($handle);
		return array('handle' => $handle, 'reason' => 'locked');
	}
}

if (!function_exists('semantic_poll_unlock')) {
	/**
	 * @param resource|null $handle From semantic_poll_lock().
	 * @return void
	 */
	function semantic_poll_unlock($handle) {
		if (is_resource($handle)) {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}
}

if (!function_exists('semantic_poll_state_read')) {
	/**
	 * The last regnskab the previous --all run finished.
	 *
	 * @param string $path
	 * @return string '' when unknown.
	 */
	function semantic_poll_state_read($path) {
		if (!is_file((string)$path)) {
			return '';
		}
		$value = trim((string)@file_get_contents((string)$path, false, null, 0, 200));
		return semantic_webhook_db_name_valid($value) ? $value : '';
	}
}

if (!function_exists('semantic_poll_state_write')) {
	/**
	 * @param string $path
	 * @param string $lastDone
	 * @return bool
	 */
	function semantic_poll_state_write($path, $lastDone) {
		$lastDone = (string)$lastDone;
		if ($lastDone !== '' && !semantic_webhook_db_name_valid($lastDone)) {
			return false;
		}
		return @file_put_contents((string)$path, $lastDone . "\n", LOCK_EX) !== false;
	}
}

if (!function_exists('semantic_poll_empty_result')) {
	/**
	 * @return array{checked: int, changed: int, failed: int, skipped: string, errors: list<string>}
	 */
	function semantic_poll_empty_result() {
		return array('checked' => 0, 'changed' => 0, 'failed' => 0, 'skipped' => '', 'errors' => array());
	}
}

if (!function_exists('semantic_poll_run')) {
	/**
	 * Visit the regnskaber in order while the budget lasts. The per-regnskab work is a callable, so the order, the
	 * budget and the bookkeeping are tested without databases.
	 *
	 * @param list<string>       $targets
	 * @param SemanticPollBudget $budget
	 * @param callable(string, SemanticPollBudget): array{checked: int, changed: int, failed: int, skipped: string, errors: list<string>} $pollTenant
	 * @return array{
	 *   visited: list<string>,      The regnskaber that were finished, in order.
	 *   lastDone: string,           The last of them ('' when none), for the next run's rotation.
	 *   stopped: string,            '' when every target was visited, else the budget's reason ('time' or 'rows').
	 *   checked: int,
	 *   changed: int,
	 *   failed: int,
	 *   errors: array<string, list<string>>, Per regnskab, only those with errors.
	 * }
	 */
	function semantic_poll_run(array $targets, SemanticPollBudget $budget, callable $pollTenant) {
		$out = array('visited' => array(), 'lastDone' => '', 'stopped' => '', 'checked' => 0, 'changed' => 0, 'failed' => 0, 'errors' => array());
		foreach ($targets as $target) {
			$target = (string)$target;
			if ($budget->exhausted()) {
				$out['stopped'] = $budget->reason();
				break;
			}
			$result = call_user_func($pollTenant, $target, $budget);
			$result = is_array($result) ? array_merge(semantic_poll_empty_result(), $result) : semantic_poll_empty_result();
			$out['checked'] += (int)$result['checked'];
			$out['changed'] += (int)$result['changed'];
			$out['failed'] += (int)$result['failed'];
			if ($result['errors']) {
				$out['errors'][$target] = array_values(array_map('strval', $result['errors']));
			}
			// A regnskab the budget cut short is not finished: the next run starts with it again.
			if ($result['skipped'] === 'budget') {
				$out['stopped'] = $budget->reason();
				break;
			}
			$out['visited'][] = $target;
			$out['lastDone'] = $target;
		}
		return $out;
	}
}

if (!function_exists('semantic_poll_exit_code')) {
	/**
	 * 0 = done (also when the budget ended the run; the next run goes on), 2 = some rows or regnskaber failed.
	 * 1 (usage or configuration) and 3 (another run holds the lock) are decided by the tool before a run starts.
	 *
	 * @param array{failed: int, errors: array<string, list<string>>} $run From semantic_poll_run().
	 * @return int
	 */
	function semantic_poll_exit_code(array $run) {
		return ((int)($run['failed'] ?? 0) > 0 || !empty($run['errors'])) ? 2 : 0;
	}
}

if (!function_exists('semantic_poll_report')) {
	/**
	 * What the tool writes. Errors go to stderr, at most $maxPerDb lines per regnskab, so a Semantic outage gives a
	 * short cron mail. The summary is written only with --verbose, so cron mails only when something is wrong.
	 *
	 * @param array{visited: list<string>, stopped: string, checked: int, changed: int, failed: int, errors: array<string, list<string>>} $run
	 * @param bool $verbose
	 * @param int  $maxPerDb
	 * @return array{stderr: list<string>, stdout: list<string>}
	 */
	function semantic_poll_report(array $run, $verbose, $maxPerDb = 5) {
		$out = array('stderr' => array(), 'stdout' => array());
		foreach ($run['errors'] as $db => $errors) {
			$shown = array_slice($errors, 0, max(1, (int)$maxPerDb));
			foreach ($shown as $error) {
				$out['stderr'][] = "semanticStatusPoll: $db: $error";
			}
			if (count($errors) > count($shown)) {
				$out['stderr'][] = "semanticStatusPoll: $db: ... and " . (count($errors) - count($shown)) . ' more';
			}
		}
		if ($verbose) {
			$summary = 'semanticStatusPoll: ' . count($run['visited']) . " regnskaber visited, {$run['checked']} rows checked, {$run['changed']} changed, {$run['failed']} failed";
			if ($run['stopped'] !== '') {
				$summary .= ', stopped early (' . ($run['stopped'] === 'time' ? 'time budget' : 'row budget') . ')';
			}
			$out['stdout'][] = $summary;
		}
		return $out;
	}
}

if (!function_exists('semantic_poll_table_exists')) {
	/**
	 * Whether the current tenant database has einvoice_sendlog (a regnskab not logged into since SD-815 has not).
	 *
	 * @param string $dbType
	 * @return bool
	 */
	function semantic_poll_table_exists($dbType) {
		$qtxt = "SELECT table_name FROM information_schema.tables WHERE table_name = 'einvoice_sendlog'";
		$qtxt .= einvoice_sendlog_is_mysql($dbType) ? " AND table_schema = DATABASE()" : " AND table_schema = current_schema()";
		return (bool)db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	}
}

if (!function_exists('semantic_poll_incoming')) {
	/**
	 * Hook for the pull of incoming documents (SD-815, incoming part). It is called once per regnskab registered at
	 * Semantic (copy guard 'same_db'), after its outgoing statuses, while the run's budget lasts.
	 *
	 * When includes/einvoice/semanticIncomingPoll.php exists, it is included; when it then defines
	 *   semantic_incoming_poll(SemanticClient $client, string $db, SemanticPollBudget $budget): array{checked: int, changed: int, failed: int, errors: list<string>}
	 * that function is called with the current tenant database open. It must take a row from $budget (takeRow())
	 * before every Semantic call and stop when that returns false. Until then this returns an empty result.
	 *
	 * @param SemanticClient     $client
	 * @param string             $db
	 * @param SemanticPollBudget $budget
	 * @param string|null        $hookFile Another file, for tests; null = semanticIncomingPoll.php next to this file.
	 * @return array{checked: int, changed: int, failed: int, skipped: string, errors: list<string>}
	 */
	function semantic_poll_incoming(SemanticClient $client, $db, SemanticPollBudget $budget, $hookFile = null) {
		$file = ($hookFile !== null) ? (string)$hookFile : __DIR__ . '/semanticIncomingPoll.php';
		if (!function_exists('semantic_incoming_poll') && is_file($file)) {
			include_once($file);
		}
		if (!function_exists('semantic_incoming_poll') || $budget->exhausted()) {
			return semantic_poll_empty_result();
		}
		$result = call_user_func('semantic_incoming_poll', $client, (string)$db, $budget);
		return is_array($result) ? array_merge(semantic_poll_empty_result(), $result) : semantic_poll_empty_result();
	}
}

if (!function_exists('semantic_poll_tenant')) {
	/**
	 * The work in one tenant database, which the caller has opened as the current connection: the open send-log rows
	 * (einvoice_sendlog_find_open_sql(): Semantic rows with a document id, an open status and created within
	 * $maxAgeDays, oldest first, at most $limit) are asked about one by one while the budget lasts, then the incoming hook.
	 * A regnskab not registered at Semantic is skipped, and so is a copy of one (semantic_copy_guard()), so a restored
	 * backup never calls Semantic.
	 *
	 * @param SemanticClient     $client
	 * @param string             $db
	 * @param string             $dbType     $db_type.
	 * @param array{limit: int, maxAgeDays: int} $options
	 * @param SemanticPollBudget $budget
	 * @param (callable(): int)|null $now    Unix time, for tests; null = time().
	 * @return array{checked: int, changed: int, failed: int, skipped: string, errors: list<string>} skipped '' (done),
	 *   'not_registered', 'copy', 'no_table' or 'budget' (stopped before the last row).
	 */
	function semantic_poll_tenant(SemanticClient $client, $db, $dbType, array $options, SemanticPollBudget $budget, $now = null) {
		$out = semantic_poll_empty_result();
		$guard = semantic_copy_guard(semantic_settings_read(), (string)$db);
		if ($guard['reason'] === 'not_registered' || $guard['reason'] === 'copy') {
			$out['skipped'] = $guard['reason'];
			return $out;
		}
		if (!$guard['allowed']) {
			// db_unknown: Semantic ids without the database they belong to; support must look at it.
			$out['skipped'] = $guard['reason'];
			$out['errors'][] = $guard['message'];
			return $out;
		}
		if (semantic_poll_table_exists($dbType)) {
			$time = is_callable($now) ? (int)call_user_func($now) : time();
			$cutoff = semantic_poll_cutoff((int)($options['maxAgeDays'] ?? 30), $time);
			$query = db_select(einvoice_sendlog_find_open_sql((int)($options['limit'] ?? 50), $cutoff, 'db_escape_string'), __FILE__ . " linje " . __LINE__);
			$rows = array();
			while ($query && ($r = db_fetch_array($query))) {
				$row = einvoice_sendlog_row($r);
				if ($row !== null) {
					$rows[] = $row;
				}
			}
			foreach ($rows as $row) {
				if (!$budget->takeRow()) {
					$out['skipped'] = 'budget';
					return $out;
				}
				$out['checked']++;
				$refreshed = semantic_status_refresh($client, $row);
				if (!$refreshed['ok']) {
					$out['failed']++;
					$out['errors'][] = 'send log ' . $row['id'] . ': ' . $refreshed['message'];
				} elseif ($refreshed['changed']) {
					$out['changed']++;
				}
			}
		} else {
			$out['skipped'] = 'no_table';
		}
		$incoming = semantic_poll_incoming($client, (string)$db, $budget);
		$out['checked'] += (int)$incoming['checked'];
		$out['changed'] += (int)$incoming['changed'];
		$out['failed'] += (int)$incoming['failed'];
		foreach ($incoming['errors'] as $error) {
			$out['errors'][] = 'incoming: ' . $error;
		}
		if ($incoming['skipped'] === 'budget') {
			$out['skipped'] = 'budget';
		}
		return $out;
	}
}
