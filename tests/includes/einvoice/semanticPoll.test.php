<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/semanticPoll.test.php --- ver 5.1.1 --- 2026-11-12 ---
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
// 20261112 CL/MMR SD-815 Created. The cron run of tools/semanticStatusPoll.php without databases or Semantic: arguments, the rotation, the row selection, the time and row budget, the lock and the report.
//                 Also the MySQL guard for the send log's texts (no 4-byte characters over a utf8 connection).

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/semanticPoll.php';

final class semanticPoll extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = array();

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = array();
    }

    private function tempPath(string $name): string
    {
        $path = sys_get_temp_dir() . '/sd815-poll-' . bin2hex(random_bytes(6)) . '-' . $name;
        $this->tempFiles[] = $path;
        return $path;
    }

    /**
     * A clock the test moves by hand.
     *
     * @return array{0: callable(): float, 1: \Closure(float): void}
     */
    private function clock(float $start = 1000.0): array
    {
        $now = $start;
        $read = function () use (&$now): float {
            return $now;
        };
        $advance = function (float $seconds) use (&$now): void {
            $now += $seconds;
        };
        return array($read, $advance);
    }

    public function testArgumentDefaultsSuitCron(): void
    {
        $args = semantic_poll_arguments(array('--all'));
        $this->assertSame('', $args['error']);
        $this->assertTrue($args['all']);
        $this->assertFalse($args['verbose']);
        $this->assertSame(50, $args['limit']);
        $this->assertSame(30, $args['maxAgeDays']);
        $this->assertSame(240, $args['budgetSeconds']);
        $this->assertSame(500, $args['maxRows']);
    }

    /**
     * @return array<string, array{0: list<string>, 1: string, 2: int|null}>
     */
    public static function numberArguments(): array
    {
        return array(
            'limit kept' => array(array('--all', '--limit=20'), 'limit', 20),
            'limit capped' => array(array('--all', '--limit=9999'), 'limit', 500),
            'limit at least 1' => array(array('--all', '--limit=0'), 'limit', 1),
            'age kept' => array(array('--all', '--max-age-days=7'), 'maxAgeDays', 7),
            'age capped' => array(array('--all', '--max-age-days=1000'), 'maxAgeDays', 365),
            'budget at least 10 s' => array(array('--all', '--budget=1'), 'budgetSeconds', 10),
            'budget capped at an hour' => array(array('--all', '--budget=99999'), 'budgetSeconds', 3600),
            'rows kept' => array(array('--all', '--max-rows=42'), 'maxRows', 42),
            'not a number' => array(array('--all', '--limit=1e3'), 'limit', null),
            'negative' => array(array('--all', '--budget=-5'), 'budgetSeconds', null),
        );
    }

    /**
     * @param list<string> $argv
     */
    #[DataProvider('numberArguments')]
    public function testNumberArgumentsAreBounded(array $argv, string $key, ?int $expected): void
    {
        $args = semantic_poll_arguments($argv);
        if ($expected === null) {
            $this->assertNotSame('', $args['error']);
            return;
        }
        $this->assertSame('', $args['error']);
        $this->assertSame($expected, $args[$key]);
    }

    public function testArgumentErrors(): void
    {
        $this->assertSame('give --db=<database> or --all', semantic_poll_arguments(array())['error']);
        $this->assertStringContainsString('not both', semantic_poll_arguments(array('--all', '--db=test_1'))['error']);
        $this->assertStringContainsString('unknown argument', semantic_poll_arguments(array('--all', '--force'))['error']);
        $this->assertStringContainsString('not a database name', semantic_poll_arguments(array("--db=test_1'; DROP TABLE x"))['error']);
        $this->assertSame('', semantic_poll_arguments(array('--help'))['error'], '--help alone is no error');
        $this->assertTrue(semantic_poll_arguments(array('-h'))['help']);
        $named = semantic_poll_arguments(array('--db=test_1', '--db=test_2', '--db=test_1', '-v'));
        $this->assertSame(array('test_1', 'test_2'), $named['dbs'], 'a repeated name is polled once');
        $this->assertTrue($named['verbose']);
    }

    public function testRotationStartsAfterTheLastFinishedRegnskab(): void
    {
        $dbs = array('test_3', 'test_1', 'test_2', 'test_10');
        $this->assertSame(array('test_1', 'test_10', 'test_2', 'test_3'), semantic_poll_rotate($dbs, ''), 'sorted by name');
        $this->assertSame(array('test_2', 'test_3', 'test_1', 'test_10'), semantic_poll_rotate($dbs, 'test_10'));
        $this->assertSame(array('test_1', 'test_10', 'test_2', 'test_3'), semantic_poll_rotate($dbs, 'test_3'), 'after the last one: from the top');
        $this->assertSame(array('test_3', 'test_1', 'test_10', 'test_2'), semantic_poll_rotate($dbs, 'test_25'), 'a regnskab deleted since: the next name after it');
        $this->assertSame(array('test_1', 'test_10', 'test_2', 'test_3'), semantic_poll_rotate($dbs, 'test_99'));
        $this->assertSame(array(), semantic_poll_rotate(array(), 'test_1'));
        $this->assertSame(array('a', 'b'), semantic_poll_rotate(array('b', 'a', 'b'), ''), 'duplicates once');
    }

    public function testCutoffIsMaxAgeDaysBack(): void
    {
        $now = mktime(12, 0, 0, 11, 12, 2026);
        $this->assertSame(date('Y-m-d H:i:s', $now - 30 * 86400), semantic_poll_cutoff(30, $now));
        $this->assertSame(date('Y-m-d H:i:s', $now - 86400), semantic_poll_cutoff(0, $now), 'at least one day');
    }

    public function testSelectionAsksOnlyForOpenSemanticRowsWithinTheAgeOldestFirst(): void
    {
        $escape = function (string $value): string {
            return str_replace("'", "''", $value);
        };
        // 30 days of 86400 s cross the end of summer time in Europe/Copenhagen: pin the zone for a fixed expectation.
        $zone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            $sql = einvoice_sendlog_find_open_sql(50, semantic_poll_cutoff(30, mktime(12, 0, 0, 11, 12, 2026)), $escape);
        } finally {
            date_default_timezone_set($zone);
        }
        $this->assertStringContainsString("provider = 'semantic'", $sql);
        $this->assertStringContainsString("semantic_doc_id IS NOT NULL AND semantic_doc_id <> ''", $sql);
        $this->assertStringContainsString("created >= '2026-10-13 12:00:00'", $sql);
        $this->assertStringContainsString('ORDER BY id ASC LIMIT 50', $sql);
        foreach (einvoice_sendlog_open_statuses() as $status) {
            $this->assertStringContainsString("'$status'", $sql);
        }
        foreach (array('Sent', 'Received', 'Confirmed', 'Approved', 'Rejected', 'Error', 'SendFailed', 'ValidationFailed') as $final) {
            $this->assertStringNotContainsString("'$final'", $sql, "$final is final for the poll");
        }
        $this->assertStringNotContainsString('xml,', $sql, 'the archived XML is never read by the poll');
    }

    public function testBudgetStopsOnTime(): void
    {
        list($clock, $advance) = $this->clock();
        $budget = new \SemanticPollBudget(60, 100, $clock);
        $this->assertFalse($budget->exhausted());
        $this->assertSame(60.0, $budget->timeLeft());
        $advance(59.5);
        $this->assertTrue($budget->takeRow());
        $advance(0.5);
        $this->assertTrue($budget->exhausted());
        $this->assertSame('time', $budget->reason());
        $this->assertFalse($budget->takeRow());
        $this->assertSame(99, $budget->rowsLeft(), 'a refused row is not counted');
        $this->assertSame(0.0, $budget->timeLeft());
    }

    public function testBudgetStopsOnRows(): void
    {
        list($clock) = $this->clock();
        $budget = new \SemanticPollBudget(60, 2, $clock);
        $this->assertTrue($budget->takeRow());
        $this->assertTrue($budget->takeRow());
        $this->assertFalse($budget->takeRow());
        $this->assertSame('rows', $budget->reason());
    }

    public function testRunVisitsEveryRegnskabWhileTheBudgetLasts(): void
    {
        list($clock, $advance) = $this->clock();
        $budget = new \SemanticPollBudget(100, 1000, $clock);
        $seen = array();
        $run = semantic_poll_run(array('a', 'b', 'c'), $budget, function (string $db, \SemanticPollBudget $budget) use (&$seen, $advance): array {
            $seen[] = $db;
            $advance(10);
            $budget->takeRow();
            return array('checked' => 1, 'changed' => ($db === 'b') ? 1 : 0, 'failed' => 0, 'skipped' => '', 'errors' => array());
        });
        $this->assertSame(array('a', 'b', 'c'), $seen);
        $this->assertSame(array('a', 'b', 'c'), $run['visited']);
        $this->assertSame('c', $run['lastDone']);
        $this->assertSame('', $run['stopped']);
        $this->assertSame(3, $run['checked']);
        $this->assertSame(1, $run['changed']);
        $this->assertSame(0, semantic_poll_exit_code($run));
    }

    public function testRunStopsWhenTimeIsUpAndTheNextRunGoesOn(): void
    {
        list($clock, $advance) = $this->clock();
        $budget = new \SemanticPollBudget(25, 1000, $clock);
        $poll = function (string $db) use ($advance): array {
            $advance(10);
            return array('checked' => 1);
        };
        $run = semantic_poll_run(array('a', 'b', 'c', 'd'), $budget, $poll);
        $this->assertSame(array('a', 'b', 'c'), $run['visited'], 'c started with 5 s left and is finished');
        $this->assertSame('time', $run['stopped']);
        $this->assertSame(0, semantic_poll_exit_code($run), 'a budget stop is no error');
        $this->assertSame(array('d', 'a', 'b', 'c'), semantic_poll_rotate(array('a', 'b', 'c', 'd'), $run['lastDone']), 'd comes first next time');
    }

    public function testARegnskabCutShortIsNotFinished(): void
    {
        list($clock) = $this->clock();
        $budget = new \SemanticPollBudget(100, 3, $clock);
        $poll = function (string $db, \SemanticPollBudget $budget): array {
            $checked = 0;
            for ($i = 0; $i < 2; $i++) {
                if (!$budget->takeRow()) {
                    return array('checked' => $checked, 'skipped' => 'budget');
                }
                $checked++;
            }
            return array('checked' => $checked);
        };
        $run = semantic_poll_run(array('a', 'b', 'c'), $budget, $poll);
        $this->assertSame(array('a'), $run['visited']);
        $this->assertSame('a', $run['lastDone'], 'b is polled first in the next run');
        $this->assertSame('rows', $run['stopped']);
        $this->assertSame(3, $run['checked']);
    }

    public function testFailuresGiveExitCodeTwoAndAShortReport(): void
    {
        list($clock) = $this->clock();
        $budget = new \SemanticPollBudget(100, 1000, $clock);
        $poll = function (string $db): array {
            if ($db === 'b') {
                $errors = array();
                for ($i = 1; $i <= 8; $i++) {
                    $errors[] = "send log $i: HTTP 503";
                }
                return array('checked' => 8, 'failed' => 8, 'errors' => $errors);
            }
            return array('checked' => 1);
        };
        $run = semantic_poll_run(array('a', 'b', 'c'), $budget, $poll);
        $this->assertSame(array('a', 'b', 'c'), $run['visited'], 'a failing regnskab does not stop the others');
        $this->assertSame(2, semantic_poll_exit_code($run));
        $quiet = semantic_poll_report($run, false);
        $this->assertSame(array(), $quiet['stdout'], 'cron gets no summary');
        $this->assertCount(6, $quiet['stderr'], 'five errors and a count of the rest');
        $this->assertSame('semanticStatusPoll: b: send log 1: HTTP 503', $quiet['stderr'][0]);
        $this->assertSame('semanticStatusPoll: b: ... and 3 more', $quiet['stderr'][5]);
        $verbose = semantic_poll_report($run, true);
        $this->assertSame(array('semanticStatusPoll: 3 regnskaber visited, 10 rows checked, 0 changed, 8 failed'), $verbose['stdout']);
    }

    public function testAQuietRunPrintsNothing(): void
    {
        list($clock) = $this->clock();
        $run = semantic_poll_run(array('a'), new \SemanticPollBudget(100, 10, $clock), function (): array {
            return array('skipped' => 'not_registered');
        });
        $this->assertSame(array('stderr' => array(), 'stdout' => array()), semantic_poll_report($run, false));
        $this->assertSame(0, semantic_poll_exit_code($run));
    }

    public function testAnErrorWithoutAFailedRowStillCounts(): void
    {
        list($clock) = $this->clock();
        $run = semantic_poll_run(array('a'), new \SemanticPollBudget(100, 10, $clock), function (): array {
            return array('skipped' => 'db_unknown', 'errors' => array('E-faktura-registreringen mangler oplysning'));
        });
        $this->assertSame(2, semantic_poll_exit_code($run));
    }

    public function testLockKeepsASecondRunOut(): void
    {
        $path = $this->tempPath('lock');
        $first = semantic_poll_lock($path);
        $this->assertSame('locked', $first['reason']);
        $this->assertIsResource($first['handle']);
        $second = semantic_poll_lock($path);
        $this->assertSame('busy', $second['reason']);
        $this->assertNull($second['handle']);
        semantic_poll_unlock($first['handle']);
        $third = semantic_poll_lock($path);
        $this->assertSame('locked', $third['reason'], 'free again after the first run');
        semantic_poll_unlock($third['handle']);
        $this->assertFileExists($path, 'a left-over lock file does not block: flock() is what counts');
        $again = semantic_poll_lock($path);
        $this->assertSame('locked', $again['reason']);
        semantic_poll_unlock($again['handle']);
    }

    public function testLockInAMissingDirectoryFails(): void
    {
        $result = semantic_poll_lock(sys_get_temp_dir() . '/sd815-no-such-dir-' . bin2hex(random_bytes(6)) . '/lock');
        $this->assertSame('open_failed', $result['reason']);
        $this->assertNull($result['handle']);
    }

    public function testStateRoundTripAcceptsOnlyDatabaseNames(): void
    {
        $path = $this->tempPath('state');
        $this->assertSame('', semantic_poll_state_read($path), 'no file yet');
        $this->assertTrue(semantic_poll_state_write($path, 'test_42'));
        $this->assertSame('test_42', semantic_poll_state_read($path));
        $this->assertFalse(semantic_poll_state_write($path, '../etc'));
        $this->assertSame('test_42', semantic_poll_state_read($path), 'a refused name is not written');
        file_put_contents($path, "bad name; rm\n");
        $this->assertSame('', semantic_poll_state_read($path), 'a damaged file means start from the top');
    }

    public function testMysqlSendLogTextHasNoFourByteCharacters(): void
    {
        $escape = function (string $value): string {
            return str_replace("'", "''", $value);
        };
        $column = array('type' => 'string', 'max' => 2000);
        $text = "\u{0141}\u{00F3}d\u{017A} \u{2713} \u{1F600} looks like O'Brien";
        $this->assertSame("'\u{0141}\u{00F3}d\u{017A} \u{2713} \u{FFFD} looks like O''Brien'", einvoice_sendlog_sql_value($text, $column, $escape, 'mysqli'));
        $this->assertStringContainsString("\u{1F600}", einvoice_sendlog_sql_value($text, $column, $escape, 'pgsql'), 'PostgreSQL keeps it');
    }

    private function noCallClient(): \SemanticClient
    {
        return new \SemanticClient('https://semantic.example.invalid', bin2hex(random_bytes(8)), bin2hex(random_bytes(8)), function (): array {
            throw new \LogicException('the fake pull makes no Semantic call');
        });
    }

    /**
     * @param list<int> $written
     * @return array{get: callable, set: callable}
     */
    private static function checkpoint(?int $stored, array &$written): array
    {
        return array(
            'get' => function () use ($stored): ?int {
                return $stored;
            },
            'set' => function ($time) use (&$written): void {
                $written[] = (int)$time;
            },
        );
    }

    /**
     * A fake semantic_incoming_poll(): asks $mayContinue $wants times (one per Semantic call) and returns $result.
     *
     * @param array<string, mixed> $result
     * @param list<mixed> $call
     */
    private static function pull(int $wants, array $result, ?array &$call): callable
    {
        return function ($since, $limit, $client, $db, $store, $mayContinue) use ($wants, $result, &$call): array {
            $call = array($since, $limit, $db, 0);
            for ($i = 0; $i < $wants; $i++) {
                if (!$mayContinue()) {
                    $result['stopped'] = true;
                    $result['complete'] = false;
                    break;
                }
                $call[3]++;
            }
            return $result + array('listed' => 0, 'handled' => 0, 'failed' => 0, 'newest' => null, 'resumeFrom' => null,
                'complete' => true, 'stopped' => false, 'messages' => array());
        };
    }

    public function testIncomingPullStartsEightDaysBackTheFirstTimeAndMovesTheCheckpoint(): void
    {
        list($clock) = $this->clock();
        $written = array();
        $call = null;
        $budget = new \SemanticPollBudget(100, 10, $clock);
        $pull = self::pull(3, array('listed' => 3, 'handled' => 2, 'failed' => 1, 'newest' => '2026-11-12 12:00:00',
            'resumeFrom' => '2026-11-12 08:00:00', 'messages' => array('Dokument x: fejl')), $call);
        $result = semantic_poll_incoming($this->noCallClient(), 'test_42', $budget, $pull, function (): int {
            return 1700000000;
        }, self::checkpoint(null, $written));
        $this->assertSame(date('Y-m-d H:i:s', 1700000000 - 8 * 86400), $call[0], 'no checkpoint: 8 days back');
        $this->assertSame('test_42', $call[2]);
        $this->assertSame(3, $call[3]);
        $this->assertSame(7, $budget->rowsLeft(), 'one row per Semantic call the pull made');
        $this->assertSame(3, $result['checked']);
        $this->assertSame(2, $result['changed']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(array('Dokument x: fejl'), $result['errors']);
        $this->assertSame(array((int)strtotime('2026-11-12 08:00:00')), $written, 'never past the failed document, not the newest');
    }

    public function testCheckpointWindowAndNoMoveAfterAnIncompletePull(): void
    {
        list($clock) = $this->clock();
        $stored = (int)strtotime('2026-11-12 10:00:00');
        $written = array();
        $call = null;
        semantic_poll_incoming($this->noCallClient(), 'test_42', new \SemanticPollBudget(100, 10, $clock),
            self::pull(1, array('newest' => '2026-11-12 11:00:00', 'complete' => false), $call), null, self::checkpoint($stored, $written));
        $this->assertSame('2026-11-12 09:00:00', $call[0], 'an hour before the checkpoint');
        $this->assertSame(array(), $written, 'an incomplete pull never moves the checkpoint');

        semantic_poll_incoming($this->noCallClient(), 'test_42', new \SemanticPollBudget(100, 10, $clock),
            self::pull(1, array('newest' => '2026-11-12 09:30:00'), $call), null, self::checkpoint($stored, $written));
        $this->assertSame(array(), $written, 'never backwards');

        semantic_poll_incoming($this->noCallClient(), 'test_42', new \SemanticPollBudget(100, 10, $clock),
            self::pull(1, array('newest' => '2026-11-12 11:00:00'), $call), null, self::checkpoint($stored, $written));
        $this->assertSame(array((int)strtotime('2026-11-12 11:00:00')), $written);
    }

    public function testIncomingPullStopsWhenTheBudgetIsUsed(): void
    {
        list($clock) = $this->clock();
        $written = array();
        $call = null;
        $budget = new \SemanticPollBudget(100, 2, $clock);
        $result = semantic_poll_incoming($this->noCallClient(), 'test_42', $budget,
            self::pull(5, array('newest' => '2026-11-12 11:00:00'), $call), null, self::checkpoint(null, $written));
        $this->assertSame(2, $call[3], 'two calls allowed, the third is refused before it starts');
        $this->assertSame('budget', $result['skipped']);
        $this->assertSame(array(), $written);

        $call = null;
        $empty = new \SemanticPollBudget(100, 0, $clock);
        $this->assertSame('budget', semantic_poll_incoming($this->noCallClient(), 'test_42', $empty,
            self::pull(1, array(), $call), null, self::checkpoint(null, $written))['skipped']);
        $this->assertNull($call, 'no budget, no pull');
    }

    public function testIncomingPullStopsWhenTheTimeIsUsed(): void
    {
        $now = 1000.0;
        $clock = function () use (&$now): float {
            return $now;
        };
        $written = array();
        $budget = new \SemanticPollBudget(1, 100, $clock);
        $pull = function ($since, $limit, $client, $db, $store, $mayContinue) use (&$now): array {
            $calls = 0;
            while ($mayContinue()) {
                $calls++;
                $now += 10.0;
            }
            return array('listed' => $calls, 'handled' => 0, 'failed' => 0, 'newest' => null, 'resumeFrom' => null,
                'complete' => false, 'stopped' => true, 'messages' => array());
        };
        $result = semantic_poll_incoming($this->noCallClient(), 'test_42', $budget, $pull, null, self::checkpoint(null, $written));
        $this->assertSame(1, $result['checked'], 'one call started inside the second, none after the time ran out');
        $this->assertSame('budget', $result['skipped']);
    }
}
