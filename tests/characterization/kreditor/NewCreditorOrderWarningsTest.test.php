<?php
// tests/characterization/kreditor/NewCreditorOrderWarningsTest.test.php
//
// Regression test for the testlab finding DEVY-7 (warehouses-transfer-receipt,
// round 1): Kreditorer -> Ordre -> Ny and the first Gem of the new purchase
// order logged PHP warnings.
//
// The order list's top line read $hreftext, which kreditor/ordreliste.php never
// sets. A new order rendered fields only an opened order sets (afd, email,
// kundeordnr, udskriv_til, projekt[0]), and the page's console line read
// $bogfor, which only a save sets. An item picked on the blank order, which has
// no supplier and so no order, read the order that was never created. The
// first Gem read the creditor group's currency from the wrong row, and
// insertAccount() wrote contact, delivery address and e-mail it never set. An
// opened order read $_GET['kontonr'], $attachId and $firstEmployee unset.
// With the group's currency read from the right row, the save has to look up
// the exchange rate after the supplier, or a draft that takes a foreign
// supplier by Kontonr. stores that currency at the posted currency's rate.
//
// Drives the pages as a browser does (support/run_kreditor_order_page.php):
// open the order list, open Ny, pick an item on the blank order by the
// autocomplete's URL, save the blank form with a supplier's Kontonr. as it was
// drawn, and open the saved order. A DKK draft whose Firmanavn is cleared and
// a blank order given a varenr both take a EUR supplier on Gem, and must store
// EUR with EUR's rate; without a EUR rate the draft stores 0, as a rate of 0
// does. No request may raise a warning from the files the fix covers
// (doc/ai/convention_testing_error_visibility.md), except the varenr save,
// whose new line raises warnings of its own. Apart from a foreign supplier's
// currency and rate, what the saves store is what they stored before.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class NewCreditorOrderWarningsTest extends CharacterizationTestCase
{
    /** A warning from a file starting with one of these fails the request. */
    private const OWN = ['kreditor/ordre.php:', 'kreditor/orderIncludes/', 'includes/kreditorOrderFuncIncludes/topLine.php:'];

    /** @var array{id:int, kontonr:string} */
    private array $creditor;
    /** @var array{id:int, varenr:string, salgspris:float, kostpris:float, gruppe:int} */
    private array $item;
    private string $firmanavn = '';
    /** ordrer.id before the test: everything above it is the test's own */
    private int $lastOrderId = 0;
    private int $projectGroup = 0;
    /** @var list<int> grupper.id of the currency and creditor group moveCreditorToEuroGroup() adds */
    private array $euroGroups = [];
    /** kodenr of the currency moveCreditorToEuroGroup() adds, whose rates go in valuta */
    private int $euroCurrency = 0;
    private static bool $userSettingsMade = false;

    protected function setUp(): void
    {
        if (!self::$userSettingsMade) {
            // The session user's first request ever creates its user settings (grupper USET) and leaves
            // $popup unset, which ordre.php then reads. One request up front keeps that first-request
            // warning out of whichever test runs first, also when a single test runs.
            $this->runChildJson('run_kreditor_order_page.php', ['ordreliste.php', 'valg=ordrer', '', CharacterizationEnv::SESSION_ID]);
            self::$userSettingsMade = true;
        }
        $this->firmanavn = 'chartest devy7 ' . bin2hex(random_bytes(3));
        $this->creditor = self::$fx->creditor(['firmanavn' => $this->firmanavn]);
        $this->item = self::$fx->item();
        // With a project group the order header has a Projekt field, which reads projekt[0].
        $row = self::one("INSERT INTO grupper (beskrivelse, art, kodenr, fiscal_year) VALUES ('chartest projekt', 'PRJ', '1', $1) RETURNING id", [self::$regnaar]);
        $this->projectGroup = (int)$row['id'];
        $max = self::one('SELECT COALESCE(max(id), 0) AS id FROM ordrer');
        $this->lastOrderId = (int)$max['id'];
    }

    protected function tearDown(): void
    {
        self::rows('DELETE FROM ordrelinjer WHERE ordre_id > $1 OR vare_id = $2', [$this->lastOrderId, $this->item['id']]);
        self::rows('DELETE FROM ordrer WHERE id > $1', [$this->lastOrderId]);
        self::rows('DELETE FROM vare_lev WHERE vare_id = $1', [$this->item['id']]);
        self::rows('DELETE FROM varer WHERE id = $1', [$this->item['id']]);
        if ($this->euroGroups !== []) {
            self::rows('DELETE FROM valuta WHERE gruppe = $1', [$this->euroCurrency]);
            self::rows('DELETE FROM grupper WHERE id = ANY($1::int[])', ['{' . implode(',', $this->euroGroups) . '}']);
        }
        self::rows('DELETE FROM adresser WHERE id = $1', [$this->creditor['id']]);
        self::rows('DELETE FROM grupper WHERE id = $1', [$this->projectGroup]);
    }

    /**
     * One request to a purchase order page, which may raise no warning from the files of this fix.
     * Deprecations are not checked: these requests also pass null to strpos() and str_replace().
     *
     * @param array<string, string> $post the form's fields, or [] for a GET
     * @return string the page's HTML
     */
    private function page(string $page, string $query, array $post = []): string
    {
        $res = $this->runChildJson('run_kreditor_order_page.php', [$page, $query, http_build_query($post), CharacterizationEnv::SESSION_ID]);
        $own = array_values(array_filter($res['warnings'], static function (string $warning): bool {
            foreach (self::OWN as $prefix) {
                if (str_starts_with($warning, $prefix)) {
                    return true;
                }
            }
            return false;
        }));
        $this->assertSame([], $own, ($post === [] ? 'GET' : 'POST') . " kreditor/$page?$query raised warnings");
        return $res['html'];
    }

    /**
     * The fields a browser posts for the order form as it was drawn: every named input except the
     * buttons and unticked boxes, the selected (or first) option of each select, and each textarea.
     *
     * @return array<string, string>
     */
    private static function formFields(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $fields = [];
        foreach ($doc->getElementsByTagName('input') as $input) {
            $name = $input->getAttribute('name');
            $type = strtolower($input->getAttribute('type'));
            if ($name === '' || in_array($type, ['submit', 'button', 'image', 'reset'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
                continue;
            }
            $fields[$name] = $input->getAttribute('value');
        }
        foreach ($doc->getElementsByTagName('select') as $select) {
            $name = $select->getAttribute('name');
            if ($name === '') {
                continue;
            }
            $chosen = null;
            foreach ($select->getElementsByTagName('option') as $option) {
                $value = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
                if ($chosen === null || $option->hasAttribute('selected')) {
                    $chosen = $value;
                }
                if ($option->hasAttribute('selected')) {
                    break;
                }
            }
            $fields[$name] = (string)$chosen;
        }
        foreach ($doc->getElementsByTagName('textarea') as $textarea) {
            if ($textarea->getAttribute('name') !== '') {
                $fields[$textarea->getAttribute('name')] = $textarea->textContent;
            }
        }
        return $fields;
    }

    /** @return list<array<string, string>> the orders this test's requests created */
    private function newOrders(): array
    {
        return self::rows('SELECT * FROM ordrer WHERE id > $1 ORDER BY id', [$this->lastOrderId]);
    }

    /**
     * Moves the test's creditor to a new creditor group that buys in EUR (K1, currency EUR), with a
     * EUR currency group whose rate is 745 from 2020 on, as Indstillinger -> Valuta and
     * Kreditorgrupper set them up.
     */
    private function moveCreditorToEuroGroup(): void
    {
        $vk = self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'VK'");
        $this->euroCurrency = (int)$vk['kodenr'];
        $row = self::one("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, fiscal_year) VALUES ('chartest euro', '', $1, 'VK', 'EUR', '745', $2) RETURNING id",
            [$this->euroCurrency, self::$regnaar]);
        $this->euroGroups[] = (int)$row['id'];
        self::rows("INSERT INTO valuta (gruppe, valdate, kurs) VALUES ($1, '2020-01-01', 745)", [$this->euroCurrency]);

        $kg = self::one("SELECT COALESCE(max(kodenr), 0) + 1 AS kodenr FROM grupper WHERE art = 'KG'");
        $row = self::one("INSERT INTO grupper (beskrivelse, kode, kodenr, art, box1, box2, box3, fiscal_year) VALUES ('chartest euro leverandoerer', 'K', $1, 'KG', 'K1', '7440', 'EUR', $2) RETURNING id",
            [(int)$kg['kodenr'], self::$regnaar]);
        $this->euroGroups[] = (int)$row['id'];
        self::rows('UPDATE adresser SET gruppe = $1 WHERE id = $2', [(int)$kg['kodenr'], $this->creditor['id']]);
    }

    public function test_the_order_list_opens_without_a_warning_from_its_top_line(): void
    {
        $html = $this->page('ordreliste.php', 'valg=ordrer');

        $this->assertStringContainsString("href='ordre.php?returside=ordreliste.php'", $html, 'the list offers Ny');
    }

    public function test_a_new_order_opens_without_warnings(): void
    {
        $html = $this->page('ordre.php', 'returside=ordreliste.php');

        $this->assertStringContainsString("name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' value=''", $html);
        $this->assertStringContainsString('<input type="hidden" name="afd" value="">', $html);
        $this->assertStringContainsString("name='email' value=''", $html);
        $this->assertStringContainsString("<option value='PDF' selected='selected'>PDF</option>", $html);
        $this->assertStringContainsString("name='kundeordnr' value=''", $html);
        $this->assertStringContainsString("name='projekt[0]' value=''", $html);
        $this->assertSame([], $this->newOrders(), 'opening Ny creates no order');
    }

    public function test_an_item_picked_on_the_blank_order_raises_no_warning_and_creates_no_order(): void
    {
        // kreditorOrdreAutocomplete.js sends the blank order's empty id along with the item.
        $html = $this->page('ordre.php', 'id=&vare_id=' . $this->item['id']);

        $this->assertStringContainsString("name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' value=''", $html, 'the blank order is drawn again');
        $this->assertSame([], $this->newOrders(), 'without a supplier there is no order to put the item on');
        $this->assertSame([], self::rows('SELECT id FROM ordrelinjer WHERE vare_id = $1', [$this->item['id']]));
    }

    public function test_saving_the_blank_order_with_a_supplier_number_creates_the_order_without_warnings(): void
    {
        $fields = self::formFields($this->page('ordre.php', 'returside=ordreliste.php'));
        $this->assertArrayHasKey('kontonr', $fields, 'the blank order has a Kontonr. field');
        $fields['kontonr'] = $this->creditor['kontonr'];
        $fields['save'] = 'Gem';

        // The form's action is ordre.php, so the save carries no query string.
        $html = $this->page('ordre.php', '', $fields);

        $orders = $this->newOrders();
        $this->assertCount(1, $orders, 'the save creates one order');
        $order = $orders[0];
        $this->assertSame([(string)$this->creditor['id'], $this->creditor['kontonr'], $this->firmanavn, 'KO', '0'],
            [$order['konto_id'], $order['kontonr'], $order['firmanavn'], $order['art'], $order['status']]);
        // The supplier's group is Danske Leverandører, K1 with currency DKK.
        $this->assertSame('DKK', $order['valuta']);
        // As before: insertAccount() takes none of these from the supplier.
        $this->assertSame(['', '', '', '', '', '', '', ''],
            [$order['kontakt'], $order['email'], $order['lev_navn'], $order['lev_addr1'], $order['lev_addr2'],
             $order['lev_postnr'], $order['lev_bynavn'], $order['lev_kontakt']]);
        $this->assertStringContainsString("readonly=readonly style='width:250px' name='kontonr' onfocus='document.forms[0].fokus.value=this.name;' value='{$this->creditor['kontonr']}'", $html,
            'the saved order is drawn with its supplier');
    }

    public function test_opening_a_saved_order_raises_no_warning(): void
    {
        $order = self::$fx->creditorOrder($this->creditor, [], ['status' => 0, 'firmanavn' => $this->firmanavn]);

        $html = $this->page('ordre.php', 'id=' . $order['id'] . '&returside=ordreliste.php');

        $this->assertStringContainsString("<input type=\"hidden\" name=\"id\" value=\"{$order['id']}\">", $html);
        $this->assertStringContainsString("value='{$this->creditor['kontonr']}'", $html);
    }

    /**
     * Opens a DKK draft of the test's creditor, empties Firmanavn and presses Gem. An empty Firmanavn makes
     * the save look the supplier up by Kontonr., which gives the order its creditor group's currency.
     *
     * @return array{html: string, order: array<string, string>} the page the save drew and the stored order
     */
    private function saveDraftWithFirmanavnCleared(): array
    {
        $order = self::$fx->creditorOrder($this->creditor, [], ['status' => 0, 'firmanavn' => $this->firmanavn]);
        $fields = self::formFields($this->page('ordre.php', 'id=' . $order['id'] . '&returside=ordreliste.php'));
        $this->assertSame(['DKK', $this->firmanavn, $this->creditor['kontonr']],
            [$fields['valuta'] ?? null, $fields['firmanavn'] ?? null, $fields['kontonr'] ?? null], 'the draft is drawn in DKK with its supplier');
        $fields['firmanavn'] = '';
        $fields['save'] = 'Gem';

        $html = $this->page('ordre.php', '', $fields);

        $row = self::one('SELECT firmanavn, valuta, valutakurs FROM ordrer WHERE id = $1', [$order['id']]);
        $this->assertNotNull($row, 'the draft is still there');
        return ['html' => $html, 'order' => $row];
    }

    public function test_a_dkk_draft_that_takes_a_euro_supplier_by_kontonr_stores_eur_at_eurs_rate(): void
    {
        $this->moveCreditorToEuroGroup();

        $saved = $this->saveDraftWithFirmanavnCleared();

        $row = $saved['order'];
        $this->assertSame([$this->firmanavn, 'EUR', 745.0], [$row['firmanavn'], $row['valuta'], (float)$row['valutakurs']],
            "the supplier's name and currency, at the currency's rate rather than the posted DKK's 100");
    }

    public function test_a_draft_that_takes_a_supplier_whose_currency_has_no_rate_is_saved_at_rate_0_with_an_alert(): void
    {
        $this->moveCreditorToEuroGroup();
        self::rows('DELETE FROM valuta WHERE gruppe = $1', [$this->euroCurrency]);

        $saved = $this->saveDraftWithFirmanavnCleared();

        $this->assertStringContainsString("alert('Der er ikke nogen valutakurs for EUR den ", $saved['html']);
        $row = $saved['order'];
        $this->assertSame([$this->firmanavn, 'EUR', 0.0], [$row['firmanavn'], $row['valuta'], (float)$row['valutakurs']],
            'saved as a rate of 0 is, which posting refuses, rather than failing the header update');
    }

    public function test_a_blank_order_given_the_item_of_a_euro_supplier_stores_eur_at_eurs_rate(): void
    {
        $this->moveCreditorToEuroGroup();
        self::rows("INSERT INTO vare_lev (posnr, lev_id, vare_id, lev_varenr, kostpris) VALUES (1, $1, $2, '', 10)", [$this->creditor['id'], $this->item['id']]);
        $fields = self::formFields($this->page('ordre.php', 'returside=ordreliste.php'));
        $this->assertSame('', $fields['valuta'] ?? null, 'the blank order is drawn without a currency, which the save rates at 100');
        // A varenr typed on the blank order makes the save create the order for the item's first supplier.
        $fields['vare0'] = $this->item['varenr'];
        $fields['save'] = 'Gem';

        // Adding a line by its varenr raises warnings of its own on any order (ordre.php's $variant_id,
        // openOrderLines.php's $box9), outside this fix, so this save's warnings are not checked.
        $this->runChildJson('run_kreditor_order_page.php', ['ordre.php', '', http_build_query($fields), CharacterizationEnv::SESSION_ID]);

        $orders = $this->newOrders();
        $this->assertCount(1, $orders, 'the save creates one order');
        $this->assertSame([(string)$this->creditor['id'], 'EUR', 745.0], [$orders[0]['konto_id'], $orders[0]['valuta'], (float)$orders[0]['valutakurs']],
            "the item's supplier and its currency, at the currency's rate rather than the blank currency's 100");
    }
}
