<?php
// 20261117 CDX/NTR Cover Danish order-text display/save and safe formatting in the real page paths.
// 20261118 CDX/NTR Cover CSRF rejection, bound saves, plain comparisons and LATIN9 lookup characters.
// 20261118 CDX/NTR Exercise shared LIKE normalization in both query entry points.
// 20261118 CDX/NTR Cover response status, lossless validation, SQL continuations and fresh driver captures.
// 20261118 CDX/NTR Cover account binding, malformed submissions, deleted templates and leading blank lines.

namespace OrderTextReviewFixture {
    final class Saved extends \RuntimeException {}
    final class Denied extends \RuntimeException {}

    function session_start() { return true; }
    function ifset($row, $key, $default = null) { return $row[$key] ?? $default; }
    function findtekst($key, $language = null) { return 'Fixture label'; }
    function findtekst_utf8($key, $language = null) { return explode('|', $key, 2)[1] ?? $key; }
    function fixtureOnline() {
        $GLOBALS['orderTextReviewOutputStarted'] = true;
        if (isset($_POST['thisDb']) && $_POST['thisDb'] !== $GLOBALS['db']) {
            // The real online.php clears request data after detecting an account switch.
            $_POST = $_GET = null;
        }
        print '<!-- online output -->';
    }
    function headers_sent() { return $GLOBALS['orderTextReviewOutputStarted']; }
    function http_response_code($code = null) {
        if ($code !== null) $GLOBALS['orderTextReviewHttpStatus'] = $code;
        return $GLOBALS['orderTextReviewHttpStatus'];
    }
    function db_select($sql, $where) { return $sql; }
    function db_fetch_array($query) {
        $text = $GLOBALS['orderTextReviewStored'];
        if (str_contains($query, 'from ordrer')) return ['sag_id' => 3, 'konto_id' => 4, 'nr' => $text];
        if (str_contains($query, 'from sager')) return array_fill_keys(['sagsnr', 'beskrivelse', 'udf_addr1', 'udf_postnr', 'udf_bynavn'], $text);
        if (str_contains($query, 'from ordretekster')) {
            if (!$GLOBALS['orderTextReviewTemplateExists']) return false;
            return str_starts_with($query, 'select id ') ? ['id' => 9] : ['tekst' => $text];
        }
        throw new \RuntimeException('Unexpected fixture query');
    }
    function get_relative() { return ''; }
    function file_exists($path) { return true; }
    function function_exists($name) { return \function_exists(__NAMESPACE__ . '\\' . $name) || \function_exists($name); }
    function alert($message) { throw new \RuntimeException($message); }
    function db_log_append($path, $lines) { return true; }
    function pg_parameter_status($connection, $name) { return $GLOBALS['orderTextReviewStandardStrings'] ?? 'on'; }
    function pg_last_error($connection) { return ''; }
    function mysqli_error($connection) { return ''; }
    function pg_affected_rows($result) {
        $GLOBALS['orderTextReviewCountDriver'] = __FUNCTION__;
        return $GLOBALS['orderTextReviewAffectedRows'];
    }
    function mysqli_affected_rows($connection) {
        $GLOBALS['orderTextReviewCountDriver'] = __FUNCTION__;
        return $GLOBALS['orderTextReviewAffectedRows'];
    }
    function injecttjek($sql) { return $sql; }
    function db_query_connection($global) { return null; }
    function audit_log($action, $details) {}
    function captureQuery($driverFunction, $sql, $params = null) {
        $GLOBALS['orderTextReviewDriverFunction'] = $driverFunction;
        $GLOBALS['orderTextReviewSql'] = $sql;
        unset($GLOBALS['orderTextReviewParams']);
        if ($params !== null) $GLOBALS['orderTextReviewParams'] = $params;
        return true;
    }
    function pg_query($connection, $sql) { return captureQuery(__FUNCTION__, $sql); }
    function pg_query_params($connection, $sql, $params) { return captureQuery(__FUNCTION__, $sql, $params); }
    function mysqli_query($connection, $sql) { return captureQuery(__FUNCTION__, $sql); }
    function mysqli_execute_query($connection, $sql, $params) { return captureQuery(__FUNCTION__, $sql, $params); }
    // Keep the old escape adapter so these regressions also reproduce failures on the original save path.
    function pg_escape_string($connection, $text) { return str_replace("'", "''", $text); }
}

namespace {
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

    require_once __DIR__ . '/../../../includes/db_query.php';

    final class OrderTextReviewTest extends TestCase
    {
        private const TEXT = "Æble øl å € ' <b>fed</b>";
        private const LATIN9_TEXT = "\xC6ble \xF8l \xE5 \xA4 ' <b>fed</b>";
        private array $globals = [];

        public static function setUpBeforeClass(): void
        {
            $source = file_get_contents(__DIR__ . '/../../../includes/db_query.php');
            $start = strpos($source, "if (!function_exists('db_escape_string'))");
            $end = strpos($source, "if (!function_exists('db_escape_like_pattern'))", $start);
            $escape = substr($source, $start, $end - $start);
            // Define the adapter's copy even when another suite has loaded the global helper.
            $escape = preg_replace('/^if[^\n]*\{\s*/', '', $escape);
            $escape = preg_replace('/}\s*$/', '', $escape);
            eval('namespace OrderTextReviewFixture;' . $escape);
            $start = strpos($source, "\tfunction db_modify(");
            $end = strpos($source, "if (!function_exists('db_select'))", $start);
            $modify = preg_replace('/}\s*$/', '', substr($source, $start, $end - $start));
            eval('namespace OrderTextReviewFixture;' . $modify);
            $start = strpos($source, "\tfunction db_select(");
            $end = strpos($source, "if (!function_exists('db_catalog_setval'))", $start);
            $select = preg_replace('/}\s*$/', '', substr($source, $start, $end - $start));
            $select = str_replace('function db_select(', 'function selectQuery(', $select);
            eval('namespace OrderTextReviewFixture;' . $select);
            $helper = __DIR__ . '/../../../includes/orderFuncIncludes/standardOrderText.php';
            if (is_file($helper)) {
                eval('namespace OrderTextReviewFixture;' . str_replace('exit;', 'throw new \\OrderTextReviewFixture\\Denied();', substr(file_get_contents($helper), 5)));
            }
        }

        protected function setUp(): void
        {
            foreach (['db_type', 'connection', 'db', 'db_skriv_id', '_GET', '_POST', '_SESSION', '_SERVER'] as $name) {
                $this->globals[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
            }
            $GLOBALS['db'] = '';
            $GLOBALS['db_skriv_id'] = 0;
            $GLOBALS['orderTextReviewTemplateExists'] = true;
            $GLOBALS['orderTextReviewAffectedRows'] = 1;
            $_SESSION = ['csrf_token' => 'fixture-csrf-token'];
        }

        protected function tearDown(): void
        {
            foreach (array_keys($GLOBALS) as $name) {
                if (str_starts_with($name, 'orderTextReview')) unset($GLOBALS[$name]);
            }
            foreach ($this->globals as $name => [$existed, $value]) {
                if ($existed) $GLOBALS[$name] = $value;
                else unset($GLOBALS[$name]);
            }
        }

        /** @return array<string, array{string, string}> */
        public static function encodings(): array
        {
            return ['UTF8' => ['UTF8', self::TEXT], 'LATIN9' => ['LATIN9', self::LATIN9_TEXT]];
        }

        private function editor(string $encoding, string $stored, ?string $posted = null, ?string $token = 'fixture-csrf-token', string $driver = 'postgres', ?array $postOverride = null, string $activeAccount = 'fixture_a'): string
        {
            $GLOBALS['orderTextReviewStored'] = $stored;
            $GLOBALS['db_type'] = $driver;
            $GLOBALS['connection'] = null;
            $GLOBALS['db'] = $activeAccount;
            $_GET = ['id' => 7, 'tekst_id' => 9];
            $_POST = $posted === null ? [] : ['opdater' => 'Gem', 'ordretekst' => $posted, 'thisDb' => $activeAccount];
            if ($token !== null) {
                $_POST['csrf_token'] = $token;
            }
            if ($postOverride !== null) $_POST = $postOverride;
            $_SERVER['REQUEST_METHOD'] = $posted === null ? 'GET' : 'POST';
            unset($_SERVER['HTTP_X_CSRF_TOKEN']);
            $GLOBALS['orderTextReviewOutputStarted'] = false;
            $GLOBALS['orderTextReviewHttpStatus'] = 200;
            $db_encode = $encoding;
            $db_type = $driver;
            $sprog_id = 1;
            $db = $activeAccount;
            $source = file_get_contents(__DIR__ . '/../../../debitor/ret_tekst_ordre.php');
            // Model online.php starting output so CSRF status checks must precede that include.
            $source = str_replace('include(__DIR__ . "/../includes/online.php");', 'fixtureOnline();', $source);
            // Isolate the real controller and view from session/database/header bootstrapping.
            $source = preg_replace('/^\s*(?:include|require)(?:_once)?\b[^\n]*;/m', '', $source);
            $source = str_replace('exit();', 'throw new \\OrderTextReviewFixture\\Saved();', $source);
            ob_start();
            try {
                eval('namespace OrderTextReviewFixture;' . substr($source, 5));
            } catch (\OrderTextReviewFixture\Saved $saved) {
                // The controller has completed a redirect or rejected the submitted request.
            } catch (\OrderTextReviewFixture\Denied $denied) {
                // The real CSRF validator rejected the request before saving.
            } finally {
                $html = ob_get_clean();
            }
            return $html;
        }

        #[DataProvider('encodings')]
        public function testEditorDisplaysDatabaseTextAsUtf8(string $encoding, string $stored): void
        {
            $html = $this->editor($encoding, $stored);
            self::assertStringContainsString("Æble øl å € &#039; &lt;b&gt;fed&lt;/b&gt;</textarea>", $html);
            self::assertStringContainsString('Tilbage til sag Æble øl å €', $html);
            self::assertTrue(mb_check_encoding($html, 'UTF-8'));
        }

        /** @return array<string, array{string, string, string}> */
        public static function saveCases(): array
        {
            $cases = [];
            foreach (['postgres', 'mysqli', 'mysql'] as $driver) {
                foreach (self::encodings() as $encoding => [$encoding, $stored]) {
                    $cases[$driver . '-' . $encoding] = [$driver, $encoding, $stored];
                }
            }
            return $cases;
        }

        #[DataProvider('saveCases')]
        public function testSavePreservesDatabaseBytesAndTextLiterals(string $driver, string $encoding, string $stored): void
        {
            $literal = " I like this; I ilike that; 'quoted' \\ path";
            $html = $this->editor($encoding, $stored, self::TEXT . $literal, 'fixture-csrf-token', $driver);
            $expected = $driver === 'postgres' ? 'update ordretekster set tekst = $1 where id = $2' : 'update ordretekster set tekst = ? where id = ?';
            self::assertSame($expected, $GLOBALS['orderTextReviewSql']);
            self::assertSame([$stored . $literal, 9], $GLOBALS['orderTextReviewParams']);
            self::assertSame('OrderTextReviewFixture\\' . ($driver === 'postgres' ? 'pg_query_params' : 'mysqli_execute_query'), $GLOBALS['orderTextReviewDriverFunction']);
            self::assertSame(200, $GLOBALS['orderTextReviewHttpStatus']);
            self::assertStringContainsString('URL=ordre.php?id=7&amp;ret_tekst=7', $html);
        }

        public function testEditorGeneratesAndRendersSessionToken(): void
        {
            $_SESSION = [];
            $html = $this->editor('UTF8', self::TEXT);
            self::assertArrayHasKey('csrf_token', $_SESSION);
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $_SESSION['csrf_token']);
            self::assertStringContainsString('name="csrf_token" value="' . $_SESSION['csrf_token'] . '"', $html);
            self::assertStringContainsString('name="thisDb" value="fixture_a"', $html);
        }

        /** @return array<string, array{array<string, mixed>}> */
        public static function malformedSubmissions(): array
        {
            $base = ['opdater' => 'Gem', 'csrf_token' => 'fixture-csrf-token', 'thisDb' => 'fixture_a'];
            return [
                'missing text' => [$base],
                'array text' => [$base + ['ordretekst' => ['wrong']]],
                'null text' => [$base + ['ordretekst' => null]],
                'numeric text' => [$base + ['ordretekst' => 123]],
                'missing account' => [['opdater' => 'Gem', 'csrf_token' => 'fixture-csrf-token', 'ordretekst' => 'changed']],
                'array account' => [array_replace($base, ['ordretekst' => 'changed', 'thisDb' => ['wrong']])],
                'empty account' => [array_replace($base, ['ordretekst' => 'changed', 'thisDb' => ''])],
            ];
        }

        #[DataProvider('malformedSubmissions')]
        public function testMalformedSubmissionCannotOverwriteText(array $post): void
        {
            $html = $this->editor('UTF8', self::TEXT, '', postOverride: $post);
            self::assertSame(400, $GLOBALS['orderTextReviewHttpStatus']);
            self::assertArrayNotHasKey('orderTextReviewSql', $GLOBALS);
            self::assertStringNotContainsString('URL=ordre.php', $html);
        }

        public function testExplicitEmptyStringCanClearTheTemplate(): void
        {
            $html = $this->editor('UTF8', self::TEXT, '');
            self::assertSame(['', 9], $GLOBALS['orderTextReviewParams']);
            self::assertStringContainsString('URL=ordre.php', $html);
        }

        public function testAccountSwitchCannotOverwriteAnotherAccountsTemplate(): void
        {
            $post = ['opdater' => 'Gem', 'csrf_token' => 'fixture-csrf-token', 'thisDb' => 'fixture_a', 'ordretekst' => 'From account A'];
            $html = $this->editor('UTF8', self::TEXT, 'From account A', postOverride: $post, activeAccount: 'fixture_b');
            self::assertArrayNotHasKey('orderTextReviewSql', $GLOBALS);
            self::assertStringContainsString('role="alert"', $html);
            self::assertStringNotContainsString('<form', $html);
            self::assertStringNotContainsString('URL=ordre.php', $html);
        }

        public function testMatchingAccountWithoutUnderscoreCanSave(): void
        {
            $html = $this->editor('UTF8', self::TEXT, 'same account', activeAccount: 'fixture');
            self::assertSame(['same account', 9], $GLOBALS['orderTextReviewParams']);
            self::assertStringContainsString('URL=ordre.php', $html);
        }

        /** @return array<string, array{string}> */
        public static function leadingBlankLines(): array
        {
            return ['one blank line' => ["\nÆble"], 'two blank lines' => ["\n\nÆble"]];
        }

        #[DataProvider('leadingBlankLines')]
        public function testTextareaPreservesLeadingBlankLines(string $stored): void
        {
            $html = $this->editor('UTF8', $stored);
            self::assertSame(1, preg_match('~<textarea\b[^>]*>(.*?)</textarea>~s', $html, $match));
            // HTML ignores exactly one LF directly after the textarea start tag.
            $value = str_starts_with($match[1], "\n") ? substr($match[1], 1) : $match[1];
            self::assertSame($stored, html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        /** @return array<string, array{string}> */
        public static function drivers(): array
        {
            return ['postgres' => ['postgres'], 'mysqli' => ['mysqli'], 'mysql' => ['mysql']];
        }

        #[DataProvider('drivers')]
        public function testDeletedTemplateRetainsSubmittedText(string $driver): void
        {
            $GLOBALS['orderTextReviewTemplateExists'] = false;
            $GLOBALS['orderTextReviewAffectedRows'] = 0;
            $html = $this->editor('UTF8', self::TEXT, 'Unsaved work', driver: $driver);
            self::assertStringNotContainsString('URL=ordre.php', $html);
            self::assertStringContainsString('role="alert"', $html);
            self::assertStringContainsString('Unsaved work</textarea>', $html);
            self::assertStringContainsString('Teksten findes ikke længere.', $html);
        }

        #[DataProvider('drivers')]
        public function testSameValueSaveStillSucceeds(string $driver): void
        {
            $GLOBALS['orderTextReviewAffectedRows'] = $driver === 'postgres' ? 1 : 0;
            $html = $this->editor('UTF8', self::TEXT, self::TEXT, driver: $driver);
            self::assertStringContainsString('URL=ordre.php', $html);
            self::assertStringNotContainsString('role="alert"', $html);
        }

        public function testMissingAndMismatchedCsrfTokensCannotSave(): void
        {
            foreach ([null, 'wrong-token'] as $token) {
                $html = $this->editor('UTF8', self::TEXT, 'Overwritten', $token);
                self::assertStringContainsString('403', $html);
                self::assertSame(403, $GLOBALS['orderTextReviewHttpStatus']);
                self::assertArrayNotHasKey('orderTextReviewSql', $GLOBALS);
                self::assertStringNotContainsString('URL=ordre.php', $html);
                self::assertStringNotContainsString('online output', $html);
            }
        }

        /** @return array<string, array{string}> */
        public static function unrepresentableText(): array
        {
            return [
                'curly apostrophe' => ['L’entreprise <b>Æble</b>'],
                'emoji' => ['Æble 🍎'],
                'missing LATIN1 character' => ['Pris ¼'],
                'malformed UTF8' => ["Invalid \xC3 text"],
            ];
        }

        #[DataProvider('unrepresentableText')]
        public function testLatin9RejectsLossySavesAndKeepsSubmittedText(string $text): void
        {
            $html = $this->editor('LATIN9', self::LATIN9_TEXT, $text);
            self::assertArrayNotHasKey('orderTextReviewSql', $GLOBALS);
            self::assertStringContainsString('role="alert"', $html);
            self::assertStringContainsString(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea>', $html);
            self::assertStringNotContainsString('URL=ordre.php', $html);
            self::assertStringContainsString('name="csrf_token" value="fixture-csrf-token"', $html);
        }

        public function testUtf8AcceptsCharactersUnavailableInLatin9(): void
        {
            $text = 'L’entreprise 🍎';
            $html = $this->editor('UTF8', self::TEXT, $text);
            self::assertSame([$text, 9], $GLOBALS['orderTextReviewParams']);
            self::assertStringContainsString('URL=ordre.php', $html);
        }

        public function testLegacyQueriesStillUseTheirExistingDriverPaths(): void
        {
            $sql = "update ordretekster set tekst = 'text' where tekst like 'match'";
            foreach (['postgres', 'mysqli', 'mysql'] as $driver) {
                $GLOBALS['db_type'] = $driver;
                unset($GLOBALS['orderTextReviewSql'], $GLOBALS['orderTextReviewDriverFunction']);
                $result = \OrderTextReviewFixture\db_modify($sql, __FILE__);
                self::assertArrayHasKey('orderTextReviewSql', $GLOBALS);
                self::assertSame('OrderTextReviewFixture\\' . ($driver === 'postgres' ? 'pg_query' : 'mysqli_query'), $GLOBALS['orderTextReviewDriverFunction']);
                self::assertSame($driver === 'postgres' ? str_replace(' like ', ' ilike ', $sql) : $sql, $GLOBALS['orderTextReviewSql']);
                self::assertArrayNotHasKey('orderTextReviewParams', $GLOBALS);
                self::assertArrayNotHasKey('orderTextReviewCountDriver', $GLOBALS);
                self::assertSame("0\tquery accepted", $result);
            }
        }

        #[DataProvider('drivers')]
        public function testWriteCanReportAffectedRows(string $driver): void
        {
            $GLOBALS['db_type'] = $driver;
            $GLOBALS['orderTextReviewAffectedRows'] = 3;
            $count = \OrderTextReviewFixture\db_modify("update ordretekster set tekst = 'text'", __FILE__, false, null, true);
            self::assertSame(3, $count);
            self::assertSame('OrderTextReviewFixture\\' . ($driver === 'postgres' ? 'pg_affected_rows' : 'mysqli_affected_rows'), $GLOBALS['orderTextReviewCountDriver']);
        }

        private function lookup(string $text, string $encoding = 'UTF8'): string
        {
            $source = file_get_contents(__DIR__ . '/../../../includes/ordrefunc.php');
            $start = strpos($source, 'function tekstopslag(');
            $end = strpos($source, "\nfunction ", $start + 1);
            $lookup = substr($source, $start, $end - $start);
            self::assertSame(1, preg_match('/^\s*print[^\n]*nl2br\([^\n]*;/m', $lookup, $statement));
            $r = ['id' => 9, 'tekst' => $text];
            $id = 7;
            $db_encode = $encoding;
            ob_start();
            try {
                eval('namespace OrderTextReviewFixture;' . $statement[0]);
            } finally {
                $html = ob_get_clean();
            }
            return $html;
        }

        public function testLookupRemovesMarkupAndAttributesButKeepsBoldItalicAndNewlines(): void
        {
            $html = $this->lookup("<b onclick='alert(1)'>fed</b> <I style='color:red'>kursiv</I>\n<img src=x onerror='alert(2)'><script>alert(3)</script> &lt;literal&gt;");
            self::assertStringContainsString('<b>fed</b> <i>kursiv</i><br />', $html);
            self::assertStringContainsString('&lt;literal&gt;', $html);
            self::assertStringNotContainsString('onclick', $html);
            self::assertStringNotContainsString('style=', $html);
            self::assertStringNotContainsString('<img', $html);
            self::assertStringNotContainsString('<script', $html);
        }

        public function testLookupDoesNotActivateEntityEncodedFormattingOrMarkup(): void
        {
            $html = $this->lookup('&lt;b&gt;literal&lt;/b&gt; &lt;img src=x onerror=alert(1)&gt; &amp;');
            self::assertStringContainsString('&lt;b&gt;literal&lt;/b&gt;', $html);
            self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt; &amp;', $html);
            self::assertStringNotContainsString('<b>', $html);
            self::assertStringNotContainsString('<img', $html);
        }

        #[DataProvider('encodings')]
        public function testLookupDisplaysDatabaseCharactersInItsDocumentEncoding(string $encoding, string $stored): void
        {
            $html = $this->lookup($stored, $encoding);
            if ($encoding !== 'UTF8') {
                $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
            }
            self::assertSame("Æble øl å € ' fed", trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }

        public function testLookupPreservesPlainComparisons(): void
        {
            $html = $this->lookup('Tolerance <5 mm; < 6 mm; <=7 mm; 9 > 8 <b>bold</b>');
            self::assertStringContainsString('Tolerance &lt;5 mm; &lt; 6 mm; &lt;=7 mm; 9 &gt; 8 <b>bold</b>', $html);
        }

        public function testLookupPreservesIncompleteNamedComparisons(): void
        {
            $html = $this->lookup('Load<max; level<limit <b>bold</b> end<name');
            self::assertStringContainsString('Load&lt;max; level&lt;limit <b>bold</b> end&lt;name', $html);
        }

        public function testLookupHandlesQuotedAngleBracketsInsideTagAttributes(): void
        {
            $html = $this->lookup('<b title="a > b">bold</b> <img title="x > y" onerror="alert(1)"> Load<max');
            self::assertStringContainsString('<b>bold</b>  Load&lt;max', $html);
            self::assertStringNotContainsString('onerror', $html);
            self::assertStringNotContainsString('title=', $html);
            self::assertStringNotContainsString('<img', $html);
        }

        public function testLookupPreservesAllLatin9ReplacementCharacters(): void
        {
            $text = '€ Š š Ž ž Œ œ Ÿ';
            $html = $this->lookup(mb_convert_encoding($text, 'ISO-8859-15', 'UTF-8'), 'LATIN9');
            $utf8 = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
            self::assertSame($text, trim(html_entity_decode(strip_tags($utf8), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        }
    }
}
