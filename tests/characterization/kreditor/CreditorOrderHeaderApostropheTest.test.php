<?php
// 20261120 CL/SZ MB-139 Pins that a creditor order keeps a header value with an apostrophe, on screen and through Gem.
// 20261126 CL/SZ MB-139 A value with a byte the charset can't read shows with a replacement character, not as an empty field.
//
// kreditor/orderIncludes/openOrderData.php printed each header value raw inside value='...', so O'Brien Trading showed as O and Gem saved O over the full name.
// Once the full value reached the server, postnr, bynavn, lev_postnr, lev_bynavn, cvrnr and kundeordnr went into update ordrer unescaped and the whole save failed.
// Both files print or write on include, so the blocks are lifted from the source and run against stubs.
namespace CreditorOrderHeaderApostropheFixture {

    function findtekst($key, $sprog_id)
    {
        $parts = explode('|', (string)$key, 2);
        return $parts[1] ?? $parts[0];
    }

    function db_escape_string($value)
    {
        return str_replace("'", "''", (string)$value);
    }

    /** Same contract as includes/std_func.php ifset() for the array cases the lifted code uses. */
    function ifset($arrayOrVar, $key = null, $default = null)
    {
        return is_array($arrayOrVar) && array_key_exists($key, $arrayOrVar) ? $arrayOrVar[$key] : $default;
    }

    /** The real one reads a postcode file; the tests always send a city, so it only has to exist. */
    function bynavn($postnr)
    {
        return null;
    }

    function usdate($date)
    {
        return (string)$date;
    }
}

namespace Saldi\Tests\CreditorOrderHeaderApostrophe {

    use PHPUnit\Framework\TestCase;

    final class CreditorOrderHeaderApostropheTest extends TestCase
    {
        private const ORDRE = __DIR__ . '/../../../kreditor/ordre.php';
        private const OPEN_ORDER_DATA = __DIR__ . '/../../../kreditor/orderIncludes/openOrderData.php';
        private const FIXTURE = 'CreditorOrderHeaderApostropheFixture';

        /** The values from the ticket, plus one per field so every input is covered. */
        private const VALUES = array(
            'firmanavn'   => "O'Brien Trading",
            'addr1'       => "St. Mary's Road",
            'addr2'       => "Unit 3 'B'",
            'postnr'      => "10'00",
            'bynavn'      => "King's Lynn",
            'land'        => "Côte d'Ivoire",
            'email'       => "o'brien@example.test",
            'cvrnr'       => "12'34",
            'lev_navn'    => "O'Hara & Søn",
            'lev_addr1'   => "Queen's Way 1",
            'lev_addr2'   => "Bldg 'C'",
            'lev_postnr'  => "20'00",
            'lev_bynavn'  => "St. John's",
            'lev_kontakt' => "Ann O'Hara",
            'kundeordnr'  => "PO'7",
        );

        /** Source between $from and $before, with the stubbed functions pointed at the fixture namespace. */
        private static function slice(string $file, string $from, string $before): string
        {
            $src = file_get_contents($file);
            self::assertNotFalse($src, "could not read $file");
            $at = strpos($src, $from);
            self::assertNotFalse($at, "the block starting \"$from\" is gone from " . basename($file));
            $end = strpos($src, $before, $at);
            self::assertNotFalse($end, "the block no longer reaches \"$before\" in " . basename($file));
            $block = substr($src, $at, $end - $at);
            foreach (array('findtekst', 'db_escape_string', 'ifset', 'bynavn', 'usdate') as $fn) {
                $block = preg_replace('/(?<![\w\\\\>$])(?<!function )' . $fn . '\(/', '\\' . self::FIXTURE . '\\' . $fn . '(', $block);
            }
            return $block;
        }

        /**
         * Prints the header inputs of an open order with the given values.
         *
         * @param array<string, string> $values Variable name => value, as ordreside() reads them from ordrer.
         * @return string The printed HTML.
         */
        private function renderHeader(array $values, string $charset): string
        {
            $blocks = array(
                self::slice(self::OPEN_ORDER_DATA, 'print "<tr><td>".findtekst(360,$sprog_id)', 'print "</tbody></table></td>";'),
                self::slice(self::OPEN_ORDER_DATA, "print \"<td><input class='inputbox' type='text' style='width:110px;' name='email'", "\n"),
                self::slice(self::OPEN_ORDER_DATA, "print \"<td><input class='inputbox' type='text' style='width:110px;' name=cvrnr", "\n"),
                self::slice(self::OPEN_ORDER_DATA, "print \"<tr><td>\".findtekst('360|Firmanavn'", 'print "</td></tr></tbody></table></td>";'),
            );
            $sprog_id = 1;
            // The order Att. (kontakt) input is printed by the same block but is fixed by MB-136 (#1057), not here, so it is not asserted.
            $kontakt = '';
            extract($values);
            ob_start();
            foreach ($blocks as $block) {
                eval($block);
            }
            return (string)ob_get_clean();
        }

        /**
         * The value a browser would put in each named input.
         *
         * @return array<string, string>
         */
        private static function inputValues(string $html, string $charset): array
        {
            $doc = new \DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML('<meta http-equiv="content-type" content="text/html; charset=' . $charset . '"><table>' . $html . '</table>');
            libxml_clear_errors();
            $values = array();
            foreach ($doc->getElementsByTagName('input') as $input) {
                $values[$input->getAttribute('name')] = $input->getAttribute('value');
            }
            return $values;
        }

        /** The ticket's case: every header field shows the whole value, not the text before the apostrophe. */
        public function testHeaderInputsShowTheWholeValue(): void
        {
            $shown = self::inputValues($this->renderHeader(self::VALUES, 'UTF-8'), 'UTF-8');

            foreach (self::VALUES as $field => $value) {
                self::assertArrayHasKey($field, $shown, "the $field input is no longer printed");
                self::assertSame($value, $shown[$field], "$field is cut or changed on screen");
            }
        }

        /**
         * A non-UTF-8 install (db_encode LATIN9) runs with $charset ISO-8859-1.
         * Escaping with the wrong charset returns an empty string for ø, which Gem would then save.
         */
        public function testHeaderInputsKeepLatin1Letters(): void
        {
            $latin1 = array_map(function ($v) {
                return mb_convert_encoding($v, 'ISO-8859-1', 'UTF-8');
            }, self::VALUES);

            $shown = self::inputValues($this->renderHeader($latin1, 'ISO-8859-1'), 'ISO-8859-1');

            self::assertSame("O'Hara & Søn", $shown['lev_navn']);
            self::assertSame("Côte d'Ivoire", $shown['land']);
        }

        /**
         * A value with a byte the charset can't read (legacy data on a MySQL UTF-8 install) still shows its text,
         * with a replacement character, instead of an empty input that Gem would save over the value.
         */
        public function testAnUnreadableByteDoesNotEmptyTheField(): void
        {
            $values = self::VALUES;
            $values['lev_navn'] = "Hj\xe6lp ApS";
            $shown = self::inputValues($this->renderHeader($values, 'UTF-8'), 'UTF-8');

            self::assertSame("Hj\u{FFFD}lp ApS", $shown['lev_navn']);
        }

        /** A NULL column (or a value never set on a new order) prints an empty input without a deprecation. */
        public function testNullValuesPrintEmptyInputs(): void
        {
            $nulls = array_fill_keys(array_keys(self::VALUES), null);
            $shown = self::inputValues($this->renderHeader($nulls, 'UTF-8'), 'UTF-8');

            foreach (array_keys(self::VALUES) as $field) {
                self::assertSame('', $shown[$field], "$field should be empty");
            }
        }

        /**
         * Gem: the POST read block and the update ordrer it builds.
         *
         * @param array<string, string> $post
         */
        private function saveSql(array $post): string
        {
            $read = self::slice(self::ORDRE, "\$firmanavn = db_escape_string(trim(ifset(\$_POST, 'firmanavn')));", "\$betalingsbet = ifset(\$_POST, 'betalingsbet');");
            $update = self::slice(self::ORDRE, "\$qtxt=\"update ordrer set firmanavn=", "db_modify(\$qtxt");

            $_POST = $post;
            eval($read);

            $id = 1;
            $betalingsdage = $betalingsbet = $momssats = $notes = $art = $status = $ref = $lager = $afd = '';
            $fakturanr = $lev_adr = $brugernavn = $tidspkt = $valuta = $valutakurs = $udskriv_til = '';
            // email is read further down the POST block (already escaped there), outside this slice.
            $mail_subj = $mail_text = $email = '';
            $projekt = array('');
            $formularsprog = null;
            $qtxt = '';
            eval($update);
            return $qtxt;
        }

        /**
         * What the open-order form posts for the header: every input, as a browser sends it.
         *
         * @return array<string, string>
         */
        private static function post(): array
        {
            return self::VALUES + array('kontakt' => "Bo O'Neill", 'ordredato' => '', 'levdato' => '');
        }

        /** Every header value the form sends lands in update ordrer as one escaped SQL string. */
        public function testGemEscapesEveryHeaderValue(): void
        {
            $post = self::post();
            $sql = $this->saveSql($post);

            $saved = self::VALUES + array('kontakt' => $post['kontakt']);
            unset($saved['email']);
            foreach ($saved as $field => $value) {
                self::assertStringContainsString("$field='" . str_replace("'", "''", $value) . "'", $sql,
                    "$field goes into update ordrer unescaped, so the save fails or writes a cut value");
            }
            // Outside the escaped pairs, every quote opens or closes a value.
            self::assertSame(0, substr_count(str_replace("''", '', $sql), "'") % 2, 'update ordrer has an unbalanced quote');
        }

        /** The order account lookup escapes what it is given, so postnr and bynavn must still reach it unescaped. */
        public function testLookupStillGetsTheTypedCity(): void
        {
            $read = self::slice(self::ORDRE, "\$firmanavn = db_escape_string(trim(ifset(\$_POST, 'firmanavn')));", "\$betalingsbet = ifset(\$_POST, 'betalingsbet');");
            $_POST = self::post();
            eval($read);

            self::assertSame("King's Lynn", $bynavn);
            self::assertSame("10'00", $postnr);
        }
    }
}
