<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261105 CL/LH Created. Opening an item card (lager/varekort.php?id=13) and saving its Lokation logged PHP warnings from three of the card's includes (testlab round 1, warehouses-transfer-receipt, both legs).
//                showGroups.php read one debtor discount group past the last, and that read was what printed the empty option that clears an item's group.
//                showQtyDiscounts.php read the decimals of a whole number that has none.
//                showLocations.php read the location of a warehouse with no lagerstatus row.
//                Each include runs here as varekort.php runs it, in the caller's scope with the card's variables set, against a fake database (support/product_card_includes_database.php).
// 20261105 CL/LH Changing Aktuel and saving logged two more warnings in the same session (testlab round 1, warehouses-transfer-receipt, step 4).
//                showLocations.php read $linjebg before setting it when the item has stocklog rows.
//                hiddenVars.php printed $lagerantal on the stock change confirmation, a variable varekort.php never sets.
// 20261116 CL/ASR The card's questions on a changed Salgspris, Kostpris and Beskrivelse: an answer No only puts the old value back and holds the submit an Enter in the field or Gem's accesskey starts, and OK saves (testlab, pos-tenders-accounts: Kostpris could not be changed with Saldi's message box).
//                 Saldi's message box cancels a click the browser makes under an open question (an accesskey), and the card holds the submit while that question is open.
// 20261116 CL/ASR OK on those questions clicks Gem only when the card has it, as a user who may only read items gets no Gem (MB-62).
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ProductCardIncludesWarningsTest extends TestCase
{
    private const INCLUDES = __DIR__ . '/../../../lager/productCardIncludes/';

    protected function setUp(): void
    {
        require_once __DIR__ . '/support/product_card_includes_database.php';
        require_once __DIR__ . '/../../../includes/std_func.php';
        ProductCardIncludesDb::$plan = array();
        ProductCardIncludesDb::$writes = array();
    }

    /**
     * Includes one productCardIncludes file with $variables in its scope, as lager/varekort.php does.
     *
     * @param array<string, mixed> $variables
     * @return array{0: string, 1: array<int, string>} The output, and every warning, notice or deprecation raised.
     */
    private static function render(string $include, array $variables): array
    {
        $problems = array();
        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) use (&$problems): bool {
            $problems[] = basename($errfile) . ':' . $errline . ' ' . $errstr;
            return true;
        });
        ob_start();
        try {
            (static function (string $productCardInclude, array $productCardVariables): void {
                extract($productCardVariables);
                include $productCardInclude;
                // showLocations.php keeps its boxes in buffers since 20261114 (owner); varekort.php prints them.
                echo ($pcLocationHtml ?? '') . ($pcStockHtml ?? '') . ($pcSalesHtml ?? '');
            })(self::INCLUDES . $include, $variables);
        } finally {
            $html = (string) ob_get_clean();
            restore_error_handler();
        }
        return array($html, $problems);
    }

    /**
     * Renders showGroups.php for an item in varegruppe 1 and returns the Debitorrabatgrp. options.
     *
     * @param array<int, int> $dvrgNr Index 0 is the item's own group, 1.. the DVRG groups, as varekort.php builds it.
     * @param array<int, string> $dvrgNavn
     * @return array{0: array<int, array{0: string, 1: string}>, 1: array<int, string>} value/label pairs, and the problems.
     */
    private static function debtorDiscountOptions(array $dvrgNr, array $dvrgNavn): array
    {
        ProductCardIncludesDb::$plan = array(
            array("/^select beskrivelse,box10 from grupper where art='VG' and kodenr = '1'/", array(array('beskrivelse' => 'Varer', 'box10' => ''))),
            array('/information_schema\.columns/', array()),
            array("/from grupper where art='VG' and kodenr!='1'/", array(array('kodenr' => '2', 'beskrivelse' => 'Ydelser'))),
            array("/art='(VPG|VTG|VRG)'/", array()),
        );
        [$html, $problems] = self::render('showGroups.php', array(
            'sprog_id' => 1, 'gruppe' => 1, 'regnaar' => 1, 'operation' => 0, 'beholdning' => 0, 'batchItem' => 0,
            'samlevare' => '', 'dvrg_nr' => $dvrgNr, 'dvrg_navn' => $dvrgNavn,
            'prisgruppe' => 0, 'tilbudgruppe' => 0, 'rabatgruppe' => 0,
        ));
        self::assertSame(1, preg_match('/NAME="dvrg"[^>]*>(.*?)<\/SELECT>/s', $html, $select), 'no Debitorrabatgrp. select in: ' . $html);
        preg_match_all('/<option value="([^"]*)">([^<]*)<\/option>/', $select[1], $matches, PREG_SET_ORDER);
        $options = array_map(static fn (array $match): array => array($match[1], $match[2]), $matches);
        return array($options, $problems);
    }

    public function testAnItemWithoutADebtorDiscountGroupListsTheGroupsWithoutReadingPastTheLast(): void
    {
        // The lab's case: one DVRG group, and the item has none (dvrg 0).
        [$options, $problems] = self::debtorDiscountOptions(array(0 => 0, 1 => 5), array(1 => 'Engros'));

        self::assertSame(array(), $problems);
        self::assertSame(array(array('0', ''), array('5', '5 Engros')), $options);
    }

    public function testAnItemInADebtorDiscountGroupListsItFirstThenEachOtherGroupOnceThenAnEmptyOptionThatClearsIt(): void
    {
        [$options, $problems] = self::debtorDiscountOptions(array(0 => 7, 1 => 5, 2 => 7), array(1 => 'Engros', 2 => 'Detail'));

        self::assertSame(array(), $problems);
        // The empty option posts dvrg 0, which lager/varekort.php saves as no group.
        self::assertSame(array(array('7', '7 Detail'), array('5', '5 Engros'), array('0', '')), $options);
    }

    /**
     * @param array<int, string> $rabat
     * @param array<int, string> $antal
     * @return array{0: string, 1: array<int, string>}
     */
    private static function quantityDiscounts(array $rabat, array $antal): array
    {
        return self::render('showQtyDiscounts.php', array(
            'sprog_id' => 1, 'special_price' => 0, 'specialType' => '', 'm_type' => 'percent',
            'm_rabat_array' => $rabat, 'm_antal_array' => $antal, 'incl_moms' => 0, 'rabatgruppe' => 0,
        ));
    }

    public function testAnItemWithoutQuantityDiscountsShowsAnEmptyRowWithoutWarnings(): void
    {
        // varer.m_rabat and m_antal are '' for the lab's item; explode() makes them array('').
        [$html, $problems] = self::quantityDiscounts(array(''), array(''));

        self::assertSame(array(), $problems);
        self::assertStringContainsString("name='m_rabat_array[1]'value='0'", $html);
        self::assertStringContainsString("name='m_antal_array[1]'value='0'", $html);
    }

    public function testQuantityDiscountsInWholeAndDecimalNumbersKeepTheirDecimalsWithoutWarnings(): void
    {
        [$html, $problems] = self::quantityDiscounts(array('5', '2.25'), array('10', '25.5'));

        self::assertSame(array(), $problems);
        self::assertStringContainsString('name="m_rabat_array[0]" value=5>', $html);
        self::assertStringContainsString('name="m_antal_array[0]" value="10"', $html);
        self::assertStringContainsString('name="m_rabat_array[1]" value=2,25>', $html);
        self::assertStringContainsString('name="m_antal_array[1]" value="25,5"', $html);
        self::assertStringContainsString("name='m_rabat_array[2]'value='0'", $html);
    }

    public function testAWarehouseWithoutAStockRowShowsAnEmptyLocationWithoutWarnings(): void
    {
        // Hovedlager has stock and a location; Filial has never had a lagerstatus row for the item.
        ProductCardIncludesDb::$plan = array(
            array("/sum\(antal\) as antal from batch_kob where vare_id = '13' and lager = '1'/", array(array('antal' => '4'))),
            array("/sum\(antal\) as antal from batch_(kob|salg) where vare_id = '13'/", array(array('antal' => null))),
            array("/select lok1 from lagerstatus where vare_id = '13' and lager = '1'/", array(array('lok1' => 'A-12'))),
            array("/select lok1 from lagerstatus where vare_id = '13' and lager = '2'/", array()),
            array("/sum\(beholdning\) as beholdning from lagerstatus where vare_id = '13' and lager = '1'/", array(array('beholdning' => '4'))),
            array("/sum\(beholdning\) as beholdning from lagerstatus where vare_id = '13' and lager = '2'/", array(array('beholdning' => null))),
            array("/ordrer\.art = '(KO|DO)'/", array()),
            array('/from stocklog where item_id = 13/', array()),
        );
        [$html, $problems] = self::locations(array(1 => 'Hovedlager', 2 => 'Filial'), 4);

        self::assertSame(array(), $problems);
        self::assertStringContainsString('name="lagerlok[1]"value="A-12"', $html);
        self::assertStringContainsString('name="lagerlok[2]"value=""', $html);
    }

    /**
     * Renders showLocations.php for stock item 13 with the card's variables, against ProductCardIncludesDb::$plan.
     *
     * @param array<int, string> $warehouseNames
     * @return array{0: string, 1: array<int, string>}
     */
    private static function locations(array $warehouseNames, int $beholdning): array
    {
        return self::render('showLocations.php', array(
            'sprog_id' => 1, 'stockItem' => 'on', 'warehouseNames' => $warehouseNames, 'id' => 13,
            'vare_varianter' => array(), 'variantVarerId' => array(), 'location' => '', 'folgevarenr' => '',
            'operation' => 0, 'montage' => 0, 'demontage' => 0, 'min_lager' => 0, 'max_lager' => 0, 'volume_lager' => 0,
            'beholdning' => $beholdning, 'fifo' => null, 'samlevare' => '', 'bgcolor' => '#ffffff', 'bgcolor5' => '#e0e0f0',
            'provisionsfri' => '', 'shopurl' => null, 'shop_id' => null, 'publiceret' => '',
        ));
    }

    public function testAnItemWithStockLogRowsShowsTheLogTooltipWithoutWarnings(): void
    {
        // A confirmed stock change writes a stocklog row, and the card then links Aktuel to the log with a tooltip of the last five.
        ProductCardIncludesDb::$plan = array(
            array("/sum\(antal\) as antal from batch_kob where vare_id = '13' and lager = '1'/", array(array('antal' => '6'))),
            array("/sum\(antal\) as antal from batch_salg where vare_id = '13' and lager = '1'/", array(array('antal' => null))),
            array("/select lok1 from lagerstatus where vare_id = '13' and lager = '1'/", array(array('lok1' => 'A-12'))),
            array("/sum\(beholdning\) as beholdning from lagerstatus where vare_id = '13' and lager = '1'/", array(array('beholdning' => '6'))),
            array("/ordrer\.art = '(KO|DO)'/", array()),
            array('/from stocklog where item_id = 13/', array(
                array('username' => 'admin', 'initials' => 'LH', 'reason' => 'Optælling', 'correction' => '2', 'logtime' => '1793936009'),
                array('username' => 'admin', 'initials' => 'LH', 'reason' => 'Varemodtagelse', 'correction' => '4', 'logtime' => '1793935800'),
            )),
        );
        [$html, $problems] = self::locations(array(1 => 'Hovedlager'), 6);

        self::assertSame(array(), $problems);
        self::assertSame(1, preg_match("/overlib\('(.*?)', WIDTH=800\)/s", $html, $tooltip), 'no Log tooltip in: ' . $html);
        preg_match_all('/<tr (bgcolor=[^>]*)>/', $tooltip[1], $rows);
        // The header row has the page background, and the log rows alternate from there.
        self::assertSame(array('bgcolor=#ffffff', 'bgcolor=#e0e0f0', 'bgcolor=#ffffff'), $rows[1]);
        self::assertStringContainsString('<td>admin</td><td>LH</td><td align=right>2,00</td>', $tooltip[1]);
    }

    public function testTheStockChangeConfirmationPostsTheWarehouseCountWithoutWarnings(): void
    {
        // confirmStockChange.php includes hiddenVars.php when Aktuel changes and stock changes must be confirmed.
        // These are the card's variables at that point (lager/varekort.php:1239), where the warehouses are counted in $numberOfStocks.
        [$html, $problems] = self::render('hiddenVars.php', array(
            'beskrivelse' => array(0 => 'Testvare'), 'fokus' => '', 'gruppe' => 1, 'lev' => null, 'id' => 13, 'ordre_id' => 0,
            'publiceret' => '', 'returside' => '', 'varenr' => 'A1', 'vare_sprogantal' => 0, 'vare_lev_id' => null,
            'oldDescription' => 'Testvare', 'vare_tekst_id' => array(), 'vare_sprog_id' => array(),
            'warehouseNames' => array(1 => 'Hovedlager', 2 => 'Filial'), 'numberOfStocks' => 2,
            'lagerbeh' => array(1 => '4', 2 => '0'), 'ny_lagerbeh' => array(1 => '6', 2 => '0'),
        ));

        self::assertSame(array(), $problems);
        // The value the card's own form posts as lagerantal (lager/varekort.php:1682).
        self::assertStringContainsString("name='lagerantal' value='2'", $html);
        self::assertStringContainsString("name='ny_lagerbeh[1]' value=\"6\"", $html);
    }

    /** The text from $open (a '{') to its matching '}', both included. */
    private static function block(string $code, int $open): string
    {
        self::assertSame('{', $code[$open] ?? '', 'no block at ' . $open);
        $depth = 0;
        $length = strlen($code);
        for ($i = $open; $i < $length; $i++) {
            // strings and line comments may hold braces
            if ($code[$i] === '"' || $code[$i] === "'" || substr($code, $i, 2) === '//') {
                $end = strpos($code, $code[$i] === '/' ? "\n" : $code[$i], $i + 1);
                $i = ($end === false) ? $length : $end;
                continue;
            }
            if ($code[$i] === '{') $depth++;
            elseif ($code[$i] === '}' && --$depth === 0) return substr($code, $open, $i - $open + 1);
        }
        self::fail('unclosed block at ' . $open);
    }

    /**
     * The change listener lager/varekort.php puts on a field of the card, from the script at the end of the page. That script
     * is plain HTML after the closing PHP tag, so the file holds what the browser gets.
     *
     * @return array{0: string, 1: string} The branch for confirm() true, and the branch for false with the bodies of the
     *                                     script's own functions it calls.
     */
    private static function confirmBranches(string $field): array
    {
        $page = (string) file_get_contents(__DIR__ . '/../../../lager/varekort.php');
        self::assertSame(1, preg_match('/<script>(?:(?!<\/?script\b).)*?const salgspris\b.*?<\/script>/s', $page, $script), 'no listener script in lager/varekort.php');
        $code = $script[0];
        $listener = strpos($code, $field . '.addEventListener("change"');
        self::assertNotFalse($listener, 'no change listener on ' . $field);
        $ask = strpos($code, 'if (confirm(', $listener);
        self::assertNotFalse($ask, 'no confirm() in the listener on ' . $field);
        $yesAt = strpos($code, '{', $ask);
        $yes = self::block($code, $yesAt);
        self::assertSame(1, preg_match('/\G\s*else\s*/', $code, $else, 0, $yesAt + strlen($yes)), 'no else branch on ' . $field);
        $no = self::block($code, $yesAt + strlen($yes) + strlen($else[0]));
        preg_match_all('/\b([A-Za-z_]\w*)\s*\(/', $no, $calls);
        foreach (array_unique($calls[1]) as $name) {
            if (preg_match('/function\s+' . preg_quote($name, '/') . '\s*\([^)]*\)\s*/', $code, $def, PREG_OFFSET_CAPTURE)) {
                $no .= "\n" . self::block($code, $def[0][1] + strlen($def[0][0]));
            }
        }
        return array($yes, $no);
    }

    /** @return array<string, array{0: string, 1: string}> the listener's variable, and the variable with the value the card was opened with */
    public static function confirmedFields(): array
    {
        return array(
            'Salgspris' => array('salgspris', 'oldPrice'),
            'Kostpris' => array('kostpris', 'oldCost'),
            'Beskrivelse' => array('beskrivelse', 'oldDescription'),
        );
    }

    #[DataProvider('confirmedFields')]
    public function testAnsweringNoToAChangedValueOnlyPutsTheOldValueBackAndOkSaves(string $field, string $old): void
    {
        // Saldi's message box (javascript/saldi-dialog.js) answers confirm() in a change event with false at once and shows the
        // question; OK sets the new value again and repeats the change, and confirm() is then true. So the false branch runs
        // before the user answers: it may only put the old value back, never save, or the form is saved before the answer.
        [$yes, $no] = self::confirmBranches($field);

        self::assertMatchesRegularExpression('/\b' . $old . '\b/', $no, 'Annuller does not put the old value back');
        self::assertDoesNotMatchRegularExpression('/\.(click|submit|requestSubmit)\s*\(|\blocation\b|\bfetch\s*\(/', $no, 'Annuller saves or leaves the card');
        // An Enter in the field commits the change with the focus still in it, and the browser submits the form right after.
        self::assertMatchesRegularExpression('/addEventListener\(\s*["\']submit["\']/', $no, 'the submit after an Enter is not held for the answer');
        self::assertStringContainsString('preventDefault()', $no);
        // Gem's accesskey (Alt+G) moves the focus to Gem first, so the change runs with the focus out of the field, and the
        // browser clicks Gem straight after it. With Saldi's box the submit is held wherever the focus is.
        self::assertStringContainsString('window.SaldiDialog', $no, 'the submit after Gem\'s accesskey is not held for the answer');
        // Gem's accesskey pressed while the question is open: the card holds the submit as long as Saldi's question is on
        // screen, so the answer decides also with an older copy of the box from the browser's cache.
        self::assertStringContainsString('#sdlg-root [role=alertdialog]', $no, 'the submit while the question is open is not held for the answer');
        // OK (the browser's box, or the repeated change after OK in Saldi's box) saves the card through Gem.
        self::assertStringContainsString('[name=saveItem]', $yes);
        self::assertMatchesRegularExpression('/\.click\s*\(/', $yes);
    }

    #[DataProvider('confirmedFields')]
    public function testOkClicksGemOnlyWhenTheCardHasIt(string $field, string $old): void
    {
        // A user who may only read items gets the card without Gem (lager/varekort.php prints it only when !$noEdit, MB-62),
        // so document.querySelector("[name=saveItem]") is null there. OK clicks Gem only when it is found; an unguarded
        // click() throws a TypeError in the change listener.
        [$yes] = self::confirmBranches($field);

        self::assertSame(1, preg_match('/\b([A-Za-z_]\w*)\s*=\s*document\.querySelector\(\s*["\']\[name=saveItem\]["\']\s*\)/', $yes, $gem), 'OK does not look Gem up on ' . $field . ' (' . $old . ')');
        $name = preg_quote($gem[1], '/');
        $clicks = preg_match_all('/\.\s*click\s*\(/', $yes);
        $guarded = preg_match_all('/\bif\s*\(\s*' . $name . '\s*\)\s*\{?\s*' . $name . '\s*\.\s*click\s*\(|\b' . $name . '\s*\?\.\s*click\s*\(/', $yes);
        self::assertGreaterThan(0, $clicks, 'OK does not save through Gem');
        self::assertSame($clicks, $guarded, 'OK clicks Gem also when the card has none (a user who may only read items)');
    }

    public function testAClickTheBrowserMakesUnderAnOpenQuestionIsCancelled(): void
    {
        // An accesskey pressed while the question is open (Alt+G after Tab out of Kostpris): Chromium acts on accesskeys from
        // the key press, which the box's keydown handler cannot hold back, and clicks Gem under the box. Saldi's box cancels
        // a click the browser makes outside the box while a question is open; clicks a script makes (the change done again
        // after OK, Gem in the card's OK branch) and clicks on the box's own buttons go through.
        $js = (string) file_get_contents(__DIR__ . '/../../../javascript/saldi-dialog.js');
        self::assertSame(1, preg_match("/\\bw\\.addEventListener\\('click',\\s*function\\s*\\(e\\)\\s*/", $js, $at, PREG_OFFSET_CAPTURE), 'no click handler on the window in javascript/saldi-dialog.js');
        $open = $at[0][1] + strlen($at[0][0]);
        $handler = self::block($js, $open);
        self::assertSame(1, preg_match('/\G\s*,\s*true\s*\)/', $js, $capture, 0, $open + strlen($handler)), 'the click handler does not run in the capture phase, before the page\'s own');
        self::assertStringContainsString('e.isTrusted', $handler, 'a click a script makes is cancelled too');
        self::assertStringContainsString('.contains(e.target)', $handler, 'the box\'s own buttons are cancelled too');
        self::assertStringContainsString("'confirm'", $handler, 'not limited to an open question');
        self::assertStringContainsString('preventDefault()', $handler);
        self::assertStringContainsString('stopImmediatePropagation()', $handler);
    }

    public function testTheBoxShowsAQuestionTheWayTheCardLooksForIt(): void
    {
        // The card (lager/varekort.php) finds Saldi's open question by #sdlg-root [role=alertdialog].
        $js = (string) file_get_contents(__DIR__ . '/../../../javascript/saldi-dialog.js');
        self::assertStringContainsString("root.id = 'sdlg-root'", $js, 'the box is no longer #sdlg-root');
        self::assertStringContainsString("role=\"' + (ask ? 'alertdialog' : 'dialog')", $js, 'a question is no longer role=alertdialog');
    }
}
