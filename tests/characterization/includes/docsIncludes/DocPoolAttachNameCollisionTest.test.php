<?php

// 20261014 MJ SST-868 Attaching a pool document to an order or journal line that already holds a
//                  document of the same name did nothing, and said nothing. Havemøbelland split
//                  one container invoice across four creditor orders; AI scan had named every
//                  upload ZHEJIANG_JIAYUAN_HOUSEHOLD_CO._LTD_2026-09-30.pdf (SST-854), three of
//                  the orders already had a file by that name, and the commercial invoice could
//                  not be attached to any of them. The pool reloaded with the file still in it.
//
//                  Three halves, because the bug had three parts. The target name is lifted out
//                  of insertDoc.php and reserved against a real filesystem. The move block is
//                  lifted and run, so the landed name reaching documents.filename and the pool
//                  name reaching the pool_files cleanup are observed rather than asserted about.
//                  And the client's redirect match is taken from docPool.php and run against the
//                  redirect insertDoc.php really writes - they disagreed, which is why a failed
//                  attach and a successful one looked identical.
//
// strict_types is off: the lifted code is legacy and eval'd code inherits the caller's setting.

namespace DocPoolFixture {

    /** Records every write instead of running it. */
    final class Db
    {
        /** @var array<int, string> */
        public static array $modified = array();
        /** @var array<string, array|false> */
        public static array $rows = array();

        public static function reset(): void
        {
            self::$modified = array();
            self::$rows = array();
        }

        /** @return array<int, string> every statement matching $needle */
        public static function matching(string $needle): array
        {
            $out = array();
            foreach (self::$modified as $sql) {
                if (stripos($sql, $needle) !== false) {
                    $out[] = $sql;
                }
            }
            return $out;
        }
    }

    function db_modify($sql, $context = null)
    {
        Db::$modified[] = (string)$sql;
        return true;
    }

    function db_select($sql, $context = null)
    {
        return (string)$sql;
    }

    function db_fetch_array($result)
    {
        return isset(Db::$rows[$result]) ? Db::$rows[$result] : false;
    }

    function db_escape_string($value)
    {
        return str_replace("'", "''", (string)$value);
    }

    /** The lifted block calls this on failure; the real one exits, so this records and throws. */
    function insertDocAttachFailed($reservation = null)
    {
        if ($reservation) {
            $reservation->discard();
        }
        throw new \RuntimeException('insertDocAttachFailed');
    }

    function findtekst($textId, $languageID)
    {
        $at = strpos((string)$textId, '|');
        return $at === false ? '#' . $textId : substr((string)$textId, $at + 1);
    }
}

namespace {

    use PHPUnit\Framework\TestCase;

    final class DocPoolAttachNameCollisionTest extends TestCase
    {
        private const ROOT = __DIR__ . '/../../../..';
        private const INSERT = self::ROOT . '/includes/docsIncludes/insertDoc.php';
        private const POOL = self::ROOT . '/includes/docsIncludes/docPool.php';

        /** The name AI scan gave every upload for this supplier and date. */
        private const NAME = 'ZHEJIANG_JIAYUAN_HOUSEHOLD_CO._LTD_2026-09-30.pdf';

        private static string $tmp = '';

        private static function read(string $path): string
        {
            $src = file_get_contents($path);
            self::assertNotFalse($src, 'could not read ' . basename($path));
            return $src;
        }

        /**
         * insertDoc.php runs code on include, so the two helpers are lifted out of it. Its
         * require of FileReservation is relative to its own directory, which an eval here would
         * resolve against this file instead, so that one path is rewritten.
         */
        public static function setUpBeforeClass(): void
        {
            self::$tmp = sys_get_temp_dir() . '/sst868_' . getmypid();
            if (function_exists('DocPoolFixture\insertDocFreeTarget')) {
                return;
            }
            $src = self::read(self::INSERT);
            $at = strpos($src, "if (!function_exists('insertDocFreeTarget')) {");
            self::assertNotFalse($at, 'insertDocFreeTarget() is gone from insertDoc.php');
            $end = strpos($src, "if (!function_exists('insertDocUpdateKassekladdeLine')) {", $at);
            self::assertNotFalse($end, 'the helper block no longer sits above insertDocUpdateKassekladdeLine');
            $block = substr($src, $at, $end - $at);

            $real = realpath(self::ROOT . '/includes/docsIncludes/FileReservation.php');
            self::assertNotFalse($real, 'includes/docsIncludes/FileReservation.php is gone');
            $rewritten = str_replace(
                "require_once(__DIR__ . '/FileReservation.php');",
                "require_once('" . $real . "');",
                $block
            );
            self::assertNotSame($block, $rewritten, 'the helper no longer requires FileReservation');

            // A class name does not fall back to the global namespace the way a function does,
            // so FileReservation has to be qualified for the eval'd copy.
            $rewritten = str_replace('FileReservation::reserve(', '\FileReservation::reserve(', $rewritten);

            // the real insertDocAttachFailed() exits; the fixture namespace already defines one
            $rewritten = preg_replace(
                "#if \(!function_exists\('insertDocAttachFailed'\)\) \{.*#s", '', $rewritten);
            eval('namespace DocPoolFixture; ' . $rewritten);
        }

        protected function setUp(): void
        {
            \DocPoolFixture\Db::reset();
            $this->rmrf(self::$tmp);
            mkdir(self::$tmp . '/pulje', 0777, true);
            mkdir(self::$tmp . '/creditor/orders/324252', 0777, true);
        }

        protected function tearDown(): void
        {
            $this->rmrf(self::$tmp);
        }

        private function rmrf(string $dir): void
        {
            if (!is_dir($dir)) {
                return;
            }
            foreach (scandir($dir) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = "$dir/$entry";
                is_dir($path) ? $this->rmrf($path) : @unlink($path);
            }
            @rmdir($dir);
        }

        // ------------------------------------------------------------------ the reserved name

        public function testAFreeNameIsUsedAsIs(): void
        {
            $res = \DocPoolFixture\insertDocFreeTarget(self::$tmp . '/creditor/orders/324252', self::NAME);

            self::assertNotNull($res, 'no name could be reserved in an empty directory');
            self::assertSame(self::NAME, $res->baseName() . '.' . $res->ext(),
                'a document whose name is free should keep it');
        }

        /** The reported case: the order already holds the packing list under this name. */
        public function testATakenNameIsSuffixedInsteadOfRefused(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';
            file_put_contents("$dir/" . self::NAME, 'the packing list');

            $res = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);

            self::assertNotNull($res, 'a taken name is still being refused, which is the bug');
            self::assertSame('ZHEJIANG_JIAYUAN_HOUSEHOLD_CO._LTD_2026-09-30_1.pdf',
                $res->baseName() . '.' . $res->ext(),
                'the suffixed name is not the one the pool itself would pick');
            self::assertSame('the packing list', file_get_contents("$dir/" . self::NAME),
                'the document already on the order was overwritten');
        }

        /** Three documents from one supplier on one date all land. */
        public function testSuffixingContinuesPastTheFirstCollision(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';
            $names = array();
            for ($i = 0; $i < 3; $i++) {
                $res = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);
                self::assertNotNull($res, "reservation $i failed");
                $names[] = $res->baseName() . '.' . $res->ext();
            }

            self::assertSame(array(
                'ZHEJIANG_JIAYUAN_HOUSEHOLD_CO._LTD_2026-09-30.pdf',
                'ZHEJIANG_JIAYUAN_HOUSEHOLD_CO._LTD_2026-09-30_1.pdf',
                'ZHEJIANG_JIAYUAN_HOUSEHOLD_CO._LTD_2026-09-30_2.pdf',
            ), $names, 'the suffix does not keep counting');
        }

        /** A reservation is a placeholder, so the name cannot be handed out twice. */
        public function testAReservedNameIsNotHandedOutAgain(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';
            $first = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);
            self::assertFileExists($first->path(), 'the reservation left no placeholder to protect the name');
            $second = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);

            self::assertNotSame($first->path(), $second->path(),
                'two attachments were given the same target path');
        }

        /** A name with nothing to suffix around is reported rather than turned into "name.". */
        public function testANameWithoutAnExtensionIsRefused(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';

            self::assertNull(\DocPoolFixture\insertDocFreeTarget($dir, 'scanned_invoice'),
                'a name with no extension should be refused, not reserved');
            self::assertNull(\DocPoolFixture\insertDocFreeTarget($dir, ''),
                'an empty name should be refused');
            self::assertSame(array('.', '..'), array_values(scandir($dir)),
                'a refused reservation left something behind');
        }

        /** The target path is built from the reservation, so a traversing name cannot escape. */
        public function testATraversingNameCannotEscapeTheTargetDirectory(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';

            $res = \DocPoolFixture\insertDocFreeTarget($dir, '../../evil.pdf');

            self::assertNotNull($res);
            self::assertSame('evil.pdf', $res->baseName() . '.' . $res->ext(),
                'the reservation kept a path in the name');
            self::assertSame($dir, $res->dir());
        }

        // ------------------------------------------------------------------ the move

        /**
         * The move block, lifted from insertDoc.php and run against the fixture filesystem. It
         * ends at the second insertDocAttachFailed(), which the fixture namespace turns into an
         * exception so a failure is observable instead of an exit.
         */
        private function runMove(array $vars): array
        {
            $src = self::read(self::INSERT);
            $at = strpos($src, '$insertDocMoved = false;');
            self::assertNotFalse($at, 'the move block no longer starts at $insertDocMoved');
            // Both failure branches end in the same call; the outer one is the single-tab line,
            // and its marker is a substring of the inner two-tab line, so the newline is part of
            // the anchor or the lift stops at the inner brace and leaves the outer if unclosed.
            $endMarker = "\n\t} else insertDocAttachFailed(\$reservation);\n";
            $end = strpos($src, $endMarker, $at);
            self::assertNotFalse($end, 'the move block no longer ends on its outer failure branch');
            $block = substr($src, $at, $end - $at + strlen($endMarker));

            extract($vars);
            $failed = false;
            try {
                eval('namespace DocPoolFixture; ' . $block);
            } catch (\RuntimeException $e) {
                self::assertSame('insertDocAttachFailed', $e->getMessage());
                $failed = true;
            }
            return array('failed' => $failed, 'moved' => isset($insertDocMoved) ? $insertDocMoved : null);
        }

        /**
         * @param string $landedName the name the reservation handed out
         * @return array the runMove() result
         */
        private function attach(string $poolName, string $landedName, $reservation): array
        {
            return $this->runMove(array(
                'insertFile' => 1,
                'docFolder' => self::$tmp,
                'poolFileName' => $poolName,
                'fileName' => $landedName,
                'showDoc' => self::$tmp . '/creditor/orders/324252/' . $landedName,
                'filePath' => '/creditor/orders/324252',
                'source' => 'creditorOrder',
                'sourceId' => 324252,
                'globalId' => 1,
                'userId' => 7,
                'kladde_id' => 0,
                'reservation' => $reservation,
            ));
        }

        /** The whole bug, end to end: the second document lands beside the first. */
        public function testTheCollidingDocumentIsAttachedUnderTheFreeName(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';
            file_put_contents("$dir/" . self::NAME, 'the packing list');
            file_put_contents(self::$tmp . '/pulje/' . self::NAME, 'the commercial invoice');
            $res = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);
            $landed = $res->baseName() . '.' . $res->ext();

            $out = $this->attach(self::NAME, $landed, $res);

            self::assertFalse($out['failed'], 'the attach reported a failure');
            self::assertTrue($out['moved'], 'the move did not happen');
            self::assertSame('the commercial invoice', file_get_contents("$dir/$landed"),
                'the commercial invoice did not land on the order');
            self::assertSame('the packing list', file_get_contents("$dir/" . self::NAME),
                'the document already on the order was replaced');
            self::assertFileDoesNotExist(self::$tmp . '/pulje/' . self::NAME,
                'the file is still in the pool, which is the reported symptom');
        }

        /**
         * The two names must not be mixed up: documents.filename has to be the name on disk, or
         * the attachment cannot be opened, while the pool cleanup has to use the pool's name, or
         * the row and the .info file are left behind.
         */
        public function testTheLandedNameIsRecordedAndThePoolNameIsCleanedUp(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';
            file_put_contents("$dir/" . self::NAME, 'the packing list');
            file_put_contents(self::$tmp . '/pulje/' . self::NAME, 'the commercial invoice');
            file_put_contents(self::$tmp . '/pulje/'
                . pathinfo(self::NAME, PATHINFO_FILENAME) . '.info', 'metadata');
            $res = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);
            $landed = $res->baseName() . '.' . $res->ext();

            $this->attach(self::NAME, $landed, $res);

            $inserts = \DocPoolFixture\Db::matching('insert into documents');
            self::assertCount(1, $inserts, 'the attachment was not recorded once');
            self::assertStringContainsString("'$landed'", $inserts[0],
                'documents.filename does not hold the name the file actually has');
            self::assertStringNotContainsString("'" . self::NAME . "'", $inserts[0],
                'documents.filename still holds the name the file was asked for');

            $deletes = \DocPoolFixture\Db::matching('DELETE FROM pool_files');
            self::assertCount(1, $deletes, 'the pool row was not cleaned up');
            self::assertStringContainsString("'" . self::NAME . "'", $deletes[0],
                'the pool row is being deleted by the landed name, so the real row survives');

            self::assertFileDoesNotExist(self::$tmp . '/pulje/'
                . pathinfo(self::NAME, PATHINFO_FILENAME) . '.info',
                'the pool .info file was left behind');
        }

        /** A pool file that is not there is a failure now, not a silent success. */
        public function testAMissingPoolFileFails(): void
        {
            $dir = self::$tmp . '/creditor/orders/324252';
            $res = \DocPoolFixture\insertDocFreeTarget($dir, self::NAME);
            $landed = $res->baseName() . '.' . $res->ext();

            $out = $this->attach(self::NAME, $landed, $res);

            self::assertTrue($out['failed'], 'a missing pool file still reports success');
            self::assertSame(array(), \DocPoolFixture\Db::matching('insert into documents'),
                'an attachment was recorded for a file that never moved');
            self::assertFileDoesNotExist("$dir/$landed",
                'the reservation placeholder was left behind as an empty attachment');
        }

        // ------------------------------------------------------------------ the client contract

        /** The regex docPool.php uses, taken from its source so this cannot drift. */
        private function clientRedirectPattern(): string
        {
            $pool = self::read(self::POOL);
            self::assertSame(1, preg_match('#const redirectMatch = text\.match\((/.+/)\);#', $pool, $m),
                'the redirect match is gone from docPool.php');
            return trim($m[1]);
        }

        /** The redirect insertDoc.php writes, taken from its source. */
        private function serverRedirect(): string
        {
            $insert = self::read(self::INSERT);
            self::assertStringContainsString(
                'echo "window.location.replace(\'" . addslashes($redirectUrl) . "\');";', $insert,
                'insertDoc.php no longer writes the redirect as a call');
            return "window.location.replace('documents.php?source=creditorOrder&sourceId=324252&showDoc=x.pdf');";
        }

        /** The two sides have to agree, or leaving the pool is indistinguishable from reloading it. */
        public function testTheClientRecognisesTheRedirectTheServerWrites(): void
        {
            $pattern = $this->clientRedirectPattern();
            $emitted = $this->serverRedirect();

            self::assertSame(1, preg_match($pattern, $emitted, $m),
                "the client pattern $pattern does not match what insertDoc.php writes");
            self::assertSame('documents.php?source=creditorOrder&sourceId=324252&showDoc=x.pdf', $m[1],
                'the matched group is not the url, so the client would navigate to the wrong place');
        }

        /** It must keep matching the assignment form it was originally written for. */
        public function testTheAssignmentFormStillMatches(): void
        {
            self::assertSame(1, preg_match($this->clientRedirectPattern(),
                "window.location.href = 'documents.php?x=1';", $m));
            self::assertSame('documents.php?x=1', $m[1]);
        }

        /** The group the client reads has to be the group the pattern captures. */
        public function testTheClientReadsTheCapturedGroup(): void
        {
            $pool = self::read(self::POOL);
            $pattern = $this->clientRedirectPattern();
            // "\(" in the pattern text is a literal paren and "(?" opens a non-capturing group;
            // neither is a capture, so both are excluded from the count.
            $groups = preg_match_all('/(?<!\\\\)\((?!\?)/', $pattern);

            self::assertSame(1, $groups,
                'the pattern has more than one capturing group, so redirectMatch[1] may not be the url');
            self::assertStringContainsString('window.location.replace(redirectMatch[1]);', $pool,
                'the client reads a group the pattern does not capture');
        }

        /** The failure channel: a reason, carried in a field both sides name the same way. */
        public function testTheFailureChannelIsWiredOnBothSides(): void
        {
            $insert = self::read(self::INSERT);
            $at = strpos($insert, 'function insertDocAttachFailed(');
            self::assertNotFalse($at, 'insertDocAttachFailed() is gone');
            $body = substr($insert, $at, strpos($insert, "\n\t}\n}", $at) - $at);

            self::assertStringContainsString('http_response_code(500)', $body,
                'a failed attach no longer answers with an error status, so the client treats it as success');
            self::assertStringContainsString("'message'", $body, 'the failure carries no message field');
            self::assertStringContainsString('findtekst(', $body, 'the failure message is not translated');
            self::assertStringContainsString('discard()', $body,
                'the reserved placeholder is left behind when the move fails');

            // Scoped to the error handler: docPool.php parses JSON elsewhere too, so a
            // file-wide search would still pass with this handler gutted.
            $handler = $this->clientErrorHandler();
            self::assertStringContainsString('JSON.parse(text)', $handler,
                'the client no longer reads the failure body');
            self::assertStringContainsString('payload.message', $handler,
                'the client reads a different field than insertDoc.php writes');
        }

        /** The insert call's non-2xx handler, sliced out of docPool.php. */
        private function clientErrorHandler(): string
        {
            $pool = self::read(self::POOL);
            $at = strpos($pool, "console.error('Insert failed. Response:'");
            self::assertNotFalse($at, "the insert call's error handler is gone from docPool.php");
            $end = strpos($pool, '}).catch(() => {', $at);
            self::assertNotFalse($end, "the error handler no longer ends on its catch");
            return substr($pool, $at, $end - $at);
        }

        /**
         * Every source branch has to reserve its target name. The tests above call
         * insertDocFreeTarget() directly and lift the move block, so neither would notice a
         * branch that stopped calling it and went back to refusing a taken name.
         *
         * @return array<string, array{string, string}> source => [first line, last line]
         */
        public static function sourceBranches(): array
        {
            // Single-quoted on purpose: these are source text, and the variable names in them
            // would otherwise interpolate into nothing.
            return array(
                'creditorOrder' => array(
                    'if ($docFolder && $source == \'creditorOrder\') {',
                    '} elseif ($docFolder && $source == \'debitorOrdrer\') {'),
                'debitorOrdrer' => array(
                    '} elseif ($docFolder && $source == \'debitorOrdrer\') {',
                    '} elseif ($docFolder && $source == \'kassekladde\') {'),
                'kassekladde' => array(
                    '$path = "$docFolder/finance/$safeKladde/$safeSource/";',
                    '$filePath = "/finance/$safeKladde/$safeSource";'),
            );
        }

        #[\PHPUnit\Framework\Attributes\DataProvider('sourceBranches')]
        public function testEverySourceBranchReservesItsTargetName(string $firstLine, string $lastLine): void
        {
            $src = self::read(self::INSERT);
            $at = strpos($src, $firstLine);
            self::assertNotFalse($at, "this source branch is gone: $firstLine");
            $end = strpos($src, $lastLine, $at + strlen($firstLine));
            self::assertNotFalse($end, "this source branch no longer ends on: $lastLine");
            $block = substr($src, $at, $end - $at);

            self::assertStringContainsString('insertDocFreeTarget(', $block,
                'this branch no longer reserves a free target name, so a taken name is refused again');
            self::assertStringContainsString("\$fileName = \$reservation->baseName() . '.' . \$reservation->ext();", $block,
                'this branch does not take the landed name from the reservation, so the file is '
                . 'written under the name that was already taken');
            self::assertStringNotContainsString('allready exists', $block,
                'this branch alerts on a collision again, and the pool never shows that alert');
            // Consulting the target's existence is how a collision came to mean "do nothing".
            // The reservation is what decides the name, so nothing here may test for the file -
            // the file_exists() calls that remain guard directory creation and name no file.
            self::assertSame(0, preg_match('/file_exists\([^)]*\$fileName/', $block),
                'this branch tests whether the target file exists again, which is what made a '
                . 'name collision silently skip the attach');
        }

        /**
         * The redirect is guarded by whether the move happened, not by whether something exists
         * at the target. The old guard was the bug in miniature: with a colliding name the old
         * document satisfied it, so a failed attach answered with the success redirect.
         */
        public function testTheRedirectIsGuardedByTheMoveResult(): void
        {
            $src = self::read(self::INSERT);
            $at = strpos($src, "\n" . 'if ($insertDocMoved) {');
            self::assertNotFalse($at,
                'the redirect is no longer guarded by the move result, so an untouched target can '
                . 'still answer with the success redirect');
            self::assertStringContainsString('window.location.replace', substr($src, $at, 2000),
                'the guard found is not the one in front of the redirect');
        }

        /** The message has to exist in the import file, or findtekst() returns "Tekst nr: n". */
        public function testTheFailureTextIsInTheImportFile(): void
        {
            $insert = self::read(self::INSERT);
            self::assertSame(1, preg_match("/findtekst\('(\d+)\|/", $insert, $m),
                'insertDocAttachFailed() does not resolve its message through a text id');
            $id = $m[1];

            $csv = self::read(self::ROOT . '/importfiler/tekster.csv');
            self::assertSame(1, preg_match_all("/^$id\t/m", $csv),
                "text $id is missing from importfiler/tekster.csv, or defined more than once");
        }
    }
}
