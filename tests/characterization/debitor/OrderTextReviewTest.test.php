<?php
// 20261117 CDX/NTR Cover Danish order-text display/save and safe formatting in the real page paths.

namespace OrderTextReviewFixture {
    final class Saved extends \RuntimeException {}

    function session_start() { return true; }
    function ifset($row, $key, $default = null) { return $row[$key] ?? $default; }
    function findtekst($key, $language = null) { return 'Fixture label'; }
    function db_select($sql, $where) { return $sql; }
    function db_fetch_array($query) {
        $text = $GLOBALS['orderTextReviewStored'];
        if (str_contains($query, 'from ordrer')) return ['sag_id' => 3, 'konto_id' => 4, 'nr' => $text];
        if (str_contains($query, 'from sager')) return array_fill_keys(['sagsnr', 'beskrivelse', 'udf_addr1', 'udf_postnr', 'udf_bynavn'], $text);
        if (str_contains($query, 'from ordretekster')) return ['tekst' => $text];
        throw new \RuntimeException('Unexpected fixture query');
    }
    function db_modify($sql, $where) { $GLOBALS['orderTextReviewSql'] = $sql; }
    // Native-driver adapter: the production db_escape_string() still performs its real recoding.
    function pg_escape_string($connection, $text) { return str_replace("'", "''", $text); }
}

namespace {
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\TestCase;

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
            $helper = __DIR__ . '/../../../includes/orderFuncIncludes/standardOrderText.php';
            if (is_file($helper)) {
                eval('namespace OrderTextReviewFixture;' . substr(file_get_contents($helper), 5));
            }
        }

        protected function setUp(): void
        {
            foreach (['db_type', 'connection', '_GET', '_POST'] as $name) {
                $this->globals[$name] = [array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
            }
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['orderTextReviewStored'], $GLOBALS['orderTextReviewSql']);
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

        private function editor(string $encoding, string $stored, ?string $posted = null): string
        {
            $GLOBALS['orderTextReviewStored'] = $stored;
            $GLOBALS['db_type'] = 'postgres';
            $GLOBALS['connection'] = null;
            $_GET = ['id' => 7, 'tekst_id' => 9];
            $_POST = $posted === null ? [] : ['opdater' => 'Gem', 'ordretekst' => $posted];
            $db_encode = $encoding;
            $source = file_get_contents(__DIR__ . '/../../../debitor/ret_tekst_ordre.php');
            // Isolate the real controller and view from session/database/header bootstrapping.
            $source = preg_replace('/^\s*(?:include|require)(?:_once)?\b[^\n]*;/m', '', $source);
            $source = str_replace('exit();', 'throw new \\OrderTextReviewFixture\\Saved();', $source);
            ob_start();
            try {
                eval('namespace OrderTextReviewFixture;' . substr($source, 5));
            } catch (\OrderTextReviewFixture\Saved $saved) {
                // The controller has printed the return-to-order redirect and completed its save.
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

        #[DataProvider('encodings')]
        public function testSavePreservesDatabaseBytesAndEscapesQuotes(string $encoding, string $stored): void
        {
            $html = $this->editor($encoding, $stored, self::TEXT);
            $expected = "update ordretekster set tekst='" . str_replace("'", "''", $stored) . "' where id = '9'";
            self::assertSame($expected, $GLOBALS['orderTextReviewSql']);
            self::assertStringContainsString('URL=ordre.php?id=7&amp;ret_tekst=7', $html);
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
        public function testLookupPreservesItsExistingDocumentEncoding(string $encoding, string $stored): void
        {
            $html = $this->lookup($stored, $encoding);
            $expected = $encoding === 'UTF8' ? "Æble øl å € ' fed" : "\xC6ble \xF8l \xE5 \xA4 ' fed";
            self::assertSame($expected, trim(htmlspecialchars_decode(strip_tags($html), ENT_QUOTES)));
        }
    }
}
