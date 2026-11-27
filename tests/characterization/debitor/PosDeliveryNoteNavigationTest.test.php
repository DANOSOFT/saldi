<?php
// 20261127 CDX/NTR Follow a completed POS sale through Følgeseddel printing and back to the same POS sale.

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class PosDeliveryNoteNavigationTest extends CharacterizationTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::rows("DELETE FROM grupper WHERE art='POS'");
        self::rows("INSERT INTO grupper (art,kodenr,fiscal_year,box1,box2,box3,box4,box5,box6,box7,box9)
            VALUES ('POS','1',$1,'1','58200','1','1','Dankort','58050','1','')", [self::$regnaar]);
        self::rows("INSERT INTO grupper (art,kodenr,fiscal_year,box3,box4,box5,box6,box7,box10,box12)
            VALUES ('POS','2',$1,'localhost','','','','','','on')", [self::$regnaar]);
        self::rows("INSERT INTO grupper (art,kodenr,fiscal_year,box2,box3)
            VALUES ('POS','3',$1,'10','')", [self::$regnaar]);
        self::rows("DELETE FROM grupper WHERE art='USET'");
        self::rows("INSERT INTO grupper (art,kodenr,box1,box2,box3,box4,box5)
            SELECT 'USET',id,'','','S','#eeeef0','' FROM brugere WHERE brugernavn='chartest'");
    }

    /**
     * @param array<string, int|string> $get
     * @return string The real page's HTML, including redirects when it exits.
     */
    private function page(string $page, array $get): string
    {
        $output = (string)tempnam(sys_get_temp_dir(), 'pos-delivery-print-');
        try {
            $request = json_encode(['get' => $get,
                'cookie' => ['saldi_pos' => '1', 'saldi_pfs' => '10', 'saldi_bordnr' => '']], JSON_THROW_ON_ERROR);
            $result = CharacterizationEnv::runChild(
                __DIR__ . '/../support/run_pos_print_page.php',
                [$page, CharacterizationEnv::sessionId(), $request, $output]
            );
            self::assertSame(0, $result['exit'], $result['stderr']);
            return (string)file_get_contents($output);
        } finally {
            unlink($output);
        }
    }

    /** @return DOMXPath Parsed output for finding the links the user can follow. */
    private function document(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="utf-8"?>' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return new DOMXPath($doc);
    }

    /** @return string Destination of a page's refresh redirect. */
    private function redirect(string $html): string
    {
        $refreshes = $this->document($html)->query('//meta[translate(@http-equiv,"REFSH","refsh")="refresh"]');
        self::assertGreaterThan(0, $refreshes->length, 'the print page must redirect to its next step');
        $content = $refreshes->item(0)->getAttribute('content');
        self::assertMatchesRegularExpression('/url\s*=\s*(.+)/i', $content);
        preg_match('/url\s*=\s*(.+)/i', $content, $match);
        return trim($match[1], " \t\n\r\0\x0B\"'");
    }

    /**
     * @param array<string, int|string> $query
     * @return string The PDF viewer's return destination.
     */
    private function backTarget(array $query): string
    {
        $viewer = $this->page('includes/udskriv.php', $query);
        $document = $this->document($viewer);
        self::assertSame(1, $document->query('//iframe[contains(@src,".pdf")]')->length, 'show the generated delivery note');
        $back = $document->query('//a[button[contains(.,"Tilbage")]] | //a[contains(.,"Luk")]');
        self::assertGreaterThan(0, $back->length, 'the PDF viewer must have a return control');
        return html_entity_decode($back->item(0)->getAttribute('href'), ENT_QUOTES, 'UTF-8');
    }

    /** @return array<string, array{string, string, string}> */
    public static function routes(): array
    {
        return [
            'POS sidebar' => ['S', 'PO', 'pos_ordre.php'],
            'POS top menu' => ['T', 'PO', 'pos_ordre.php'],
            'ordinary order sidebar' => ['S', 'DO', 'ordre.php'],
            'ordinary order top menu' => ['T', 'DO', 'ordreliste.php'],
        ];
    }

    #[DataProvider('routes')]
    public function testDeliveryNoteReturnsToItsEnvironment(string $menu, string $art, string $expectedPage): void
    {
        self::rows("UPDATE grupper SET box3=$1 WHERE art='USET'", [$menu]);
        $customer = self::$fx->debtor(['betalingsbet' => 'Kontant']);
        $item = self::$fx->item(['salgspris' => 100]);
        $sale = self::$fx->order($customer, [['item' => $item]], ['art' => $art, 'status' => 3, 'betalingsbet' => 'Kontant']);
        self::rows("UPDATE ordrer SET fakturanr='9001',fakturadate=CURRENT_DATE,tidspkt='12:00',felt_5='1',udskriv_til='PDF' WHERE id=$1", [$sale['id']]);
        self::rows('UPDATE ordrelinjer SET leveret=antal WHERE ordre_id=$1', [$sale['id']]);
        $query = ['id' => $sale['id'], 'valg' => 1, 'formular' => 3];
        if ($art === 'PO') {
            $html = $this->page('debitor/pos_ordre.php', ['id' => $sale['id'], 'kasse' => 1]);
            $links = $this->document($html)->query('//a[input[@value="Følgeseddel"]]');
            self::assertSame(1, $links->length, 'the completed sale must offer its delivery note');
            parse_str((string)parse_url($links->item(0)->getAttribute('href'), PHP_URL_QUERY), $query);
        }
        $selection = $this->page('debitor/udskriftsvalg.php', $query);
        $printUrl = $this->redirect($selection);
        parse_str((string)parse_url($printUrl, PHP_URL_QUERY), $query);
        $print = $this->page('debitor/formularprint.php', $query);
        $viewerUrl = $this->redirect($print);
        self::assertStringContainsString('includes/udskriv.php', $viewerUrl, 'Følgeseddel must open the generated document');
        parse_str((string)parse_url($viewerUrl, PHP_URL_QUERY), $query);
        self::assertSame($art, $query['art'] ?? null, 'the print handoff must retain the order type');
        $target = $this->backTarget($query);
        self::assertSame($expectedPage, basename((string)parse_url($target, PHP_URL_PATH)), 'wrong print return destination: ' . $target);
        if ($expectedPage === 'ordreliste.php') {
            return;
        }
        parse_str((string)parse_url($target, PHP_URL_QUERY), $backQuery);
        self::assertSame($sale['id'], (int)($backQuery['id'] ?? 0), 'return to the same sale');
        if ($art !== 'PO') {
            return;
        }
        $returned = $this->page('debitor/pos_ordre.php', $backQuery);
        self::assertSame(
            1,
            $this->document($returned)->query('//a[input[@value="Følgeseddel"]]')->length,
            'Tilbage must reopen the completed POS sale'
        );
        self::assertSame(3, (int)self::rows('SELECT status FROM ordrer WHERE id=$1', [$sale['id']])[0]['status']);

        foreach ([['returside' => '../debitor/ordreliste.php'], ['bulk' => 1], ['locat' => 'ordreliste.php']] as $context) {
            $target = $this->backTarget($context + $query);
            self::assertSame(
                'ordreliste.php',
                basename((string)parse_url($target, PHP_URL_PATH)),
                'printing from an order list must still return to that list'
            );
        }
        foreach (['pos_ordre.php', 'ordre.php'] as $origin) {
            $explicit = '../debitor/' . $origin . '?id=' . $sale['id'] . '&returside=ordreliste.php';
            self::assertSame(
                $explicit,
                $this->backTarget(['returside' => $explicit] + $query),
                'an explicit return to the originating order page must take priority over the POS default'
            );
        }
        unset($query['art']);
        $target = $this->backTarget($query);
        self::assertSame(
            'pos_ordre.php',
            basename((string)parse_url($target, PHP_URL_PATH)),
            'the database order type must also give the POS fallback'
        );
    }
}
