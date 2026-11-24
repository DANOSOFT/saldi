<?php
// tests/characterization/systemdata/StamdataCvrLookupTest.test.php
//
// Stamdata loads the CVR lookup again (testlab round 2, company-masterdata,
// 2026-11-05).
//
// The old Stamdata (systemdata/stamkort.php) loaded javascript/cvrapiopslag.js:
// a CVR-nr. typed between *, + or / (or a phone number in Telefon) was looked
// up at cvrapi.dk and filled the company's name, address, postal code, town and
// phone. The page moved to systemdata/settingsSection.php?s=company.data, which
// loaded only settingsSection.js, and its fields are named f[company.data.cvr]
// and so on, where the lookup looks for cvrnr, firmanavn, ... - so typing
// *20756438* did nothing (no request, no field changed).
//
// The section names the lookup's fields ('cvr_lookup'), and the page hands the
// script those names (cvrFeltNavne) before it loads it. The test draws Stamdata
// the way a browser opens it and checks that every name the lookup is given is
// the input of the company column it fills: the test company's columns hold
// different values, and each input must show its own column's value.
// What the script does with the names in a browser is in
// e2e/stamdata-cvr-lookup.spec.mjs.
//
// Requires the docker-compose stack - skips cleanly otherwise.
//
// History:
// 20261105 CL/LH Created.
// 20261115 CL/ASR Integration: session id from CharacterizationEnv::sessionId() (master's per-class test databases, 2bd490c6).

require_once __DIR__ . '/../support/CharacterizationTestCase.php';

final class StamdataCvrLookupTest extends CharacterizationTestCase
{
    /** The columns the lookup fills (cvrapiopslag.js normaliseApiData()), each with a value no other column has. */
    private const COMPANY = [
        'cvrnr' => '20756438',
        'firmanavn' => 'Chartest CVR Firma ApS',
        'addr1' => 'Cvrvej 1',
        'addr2' => 'Bygning 2',
        'postnr' => '6100',
        'bynavn' => 'Haderslev',
        'tlf' => '70202020',
    ];

    /** @var array<string, string|null> the test tenant's company row before the suite */
    private static array $company = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$company = self::one('SELECT id, ' . implode(', ', array_keys(self::COMPANY)) . " FROM adresser WHERE art = 'S' ORDER BY id LIMIT 1") ?? [];
        if (self::$company === []) {
            self::markTestSkipped('the test tenant has no company row (adresser art S)');
        }
        self::write(self::COMPANY);
    }

    public static function tearDownAfterClass(): void
    {
        if (isset(self::$tenant) && self::$tenant && self::$company !== []) {
            $before = self::$company;
            unset($before['id']);
            self::write($before);
        }
        parent::tearDownAfterClass();
    }

    /** @param array<string, string|null> $values */
    private static function write(array $values): void
    {
        $set = [];
        $params = [];
        foreach ($values as $column => $value) {
            $params[] = $value;
            $set[] = $column . ' = $' . count($params);
        }
        $params[] = (int) self::$company['id'];
        self::rows('UPDATE adresser SET ' . implode(', ', $set) . ' WHERE id = $' . count($params), $params);
    }

    private function stamdata(): DOMXPath
    {
        $res = $this->runChildJson('run_settings_section_page.php', ['s=company.data', '', CharacterizationEnv::sessionId()]);
        $this->assertNotSame(302, $res['status'], 'Stamdata is drawn, not redirected');
        $this->assertStringContainsString('id="st-form"', $res['html'], 'Stamdata is drawn with its form');
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $res['html']);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return new DOMXPath($doc);
    }

    public function test_stamdata_loads_the_cvr_lookup_with_the_names_of_its_own_fields(): void
    {
        $x = $this->stamdata();

        $script = $x->query('//script[contains(@src, "javascript/cvrapiopslag.js")]');
        $this->assertSame(1, $script->length, 'Stamdata loads the CVR lookup once, as stamkort.php did');
        $this->assertGreaterThan(0, $x->query('preceding::script[contains(@src, "jquery")]', $script->item(0))->length, 'jQuery, which the lookup runs on, is loaded before it');

        $config = $x->query('preceding::script[not(@src)][contains(., "cvrFeltNavne")]', $script->item(0));
        $this->assertSame(1, $config->length, 'the field names are given to the lookup before it is loaded');
        $this->assertMatchesRegularExpression('/var cvrFeltNavne = (\{[^;]*\});/', $config->item(0)->textContent);
        preg_match('/var cvrFeltNavne = (\{[^;]*\});/', $config->item(0)->textContent, $m);
        $names = json_decode($m[1], true);
        $this->assertIsArray($names);
        $this->assertEqualsCanonicalizing(array_keys(self::COMPANY), array_keys($names), 'every field the lookup fills on stamkort.php is named');

        foreach (self::COMPANY as $column => $value) {
            $inputs = $x->query('//form[@id="st-form"]//input[@name="' . $names[$column] . '"][not(@type="hidden")][not(@readonly)]');
            $this->assertSame(1, $inputs->length, "$column is the name of one writable field in the Stamdata form ({$names[$column]})");
            $this->assertSame($value, $inputs->item(0)->getAttribute('value'), "the field named for $column ({$names[$column]}) is the company's $column");
        }
    }

    public function test_cvr_nr_and_telefon_say_how_to_start_the_lookup(): void
    {
        $x = $this->stamdata();
        foreach (['f[company.data.cvr]' => 'CVR-nr.', 'f[company.data.phone]' => 'telefonnr.'] as $name => $what) {
            $help = $x->query('//input[@name="' . $name . '"]/ancestor::div[contains(@class, "st-field")][1]//span[contains(@class, "st-help")]');
            $this->assertSame(1, $help->length, "$name has a help line");
            $this->assertStringContainsString("Tast $what omsluttet af *, +, eller /", $help->item(0)->textContent, "$name says how the lookup starts, as stamkort.php's tooltip did");
        }
    }
}
