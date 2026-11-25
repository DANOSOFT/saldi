<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * The POS close's own messages are valid UTF-8 on a LATIN9 ledger (debitor/pos_ordre_includes/boxCountMethods/
 * posSaleFiscalYear.php posTextUtf8(), posTextHtml(); pre-check on #1199, 2026-11-25).
 *
 * posbogfor() (debitor/pos_ordre.php) refuses a count or warns after it with a message that joins its own UTF-8 texts
 * with a ledger's texts, such as a fiscal year's name. On a LATIN9 ledger those are single-byte, json_encode() refused
 * the mix, and the alert came up blank, so the warning about sales left unposted was not seen.
 *
 * Pins: a fiscal year name read from a LATIN9 ledger is converted to UTF-8 on its own (posDbText()); a mix of UTF-8 and
 * Windows-1252 bytes becomes valid UTF-8 with both kept as written; valid UTF-8 is left as it is; the HTML form is ASCII
 * (numeric entities) and escaped.
 *
 * History:
 * 20261125 CL/ASR Created.
 */
final class PosCloseTextTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../debitor/pos_ordre_includes/boxCountMethods/posSaleFiscalYear.php';
    }

    public function test_a_message_with_a_latin9_fiscal_year_name_is_valid_utf8_with_both_parts_as_written(): void
    {
        // "Kasseoptællingen ... " from the code (UTF-8), the year "Regnskabsår 2025/26" as a LATIN9 ledger stores it
        $message = "Kasseoptællingen er ikke bogført: " . mb_convert_encoding('Regnskabsår 2025/26 €', 'Windows-1252', 'UTF-8');
        $this->assertFalse(mb_check_encoding($message, 'UTF-8'));
        $utf8 = posTextUtf8($message);
        $this->assertSame('Kasseoptællingen er ikke bogført: Regnskabsår 2025/26 €', $utf8);
        $this->assertNotFalse(json_encode($utf8), 'the alert would be blank');
    }

    public function test_a_latin9_fiscal_year_name_is_converted_where_it_is_read(): void
    {
        // Converted on its own, a name cannot be taken for UTF-8 by a neighbour's bytes: Å» stays Å», not Ż
        $GLOBALS['db_encode'] = 'LATIN9';
        $this->assertSame('Regnskab «BLÅ» 2025 €', posDbText(mb_convert_encoding('Regnskab «BLÅ» 2025 €', 'ISO-8859-15', 'UTF-8')));
        $this->assertSame('Regnskabsår 2026', posDbText('Regnskabsår 2026'), 'a name a LATIN9 ledger holds as UTF-8 is kept');
        $GLOBALS['db_encode'] = 'UTF8';
        $this->assertSame('Regnskabsår 2026', posDbText('Regnskabsår 2026'));
        unset($GLOBALS['db_encode']);
    }

    public function test_valid_utf8_is_kept(): void
    {
        $this->assertSame('Kasse 1: Ølstue «å»', posTextUtf8('Kasse 1: Ølstue «å»'));
        $this->assertSame('', posTextUtf8(''));
    }

    public function test_the_html_form_is_ascii_and_escaped(): void
    {
        $html = posTextHtml("<b>Årets</b> resultat & " . mb_convert_encoding('Østergade', 'Windows-1252', 'UTF-8'));
        $this->assertMatchesRegularExpression('/^[\x00-\x7F]*$/', $html);
        $this->assertSame('<b>Årets</b> resultat & Østergade', html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        $this->assertStringNotContainsString('<b>', $html);
    }
}
