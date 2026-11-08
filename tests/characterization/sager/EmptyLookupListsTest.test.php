<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Sager » Løn (sager/loen.php → loenliste()) draws the whole wage-slip list when a lookup query finds nothing
 * (testlab round 1, 2026-11-05).
 *
 * On test_39 (one user group, no employees, no cases, no wage slips) the page stopped at the Ansvarlig dropdown:
 * $ansvarlig was only ever filled inside the employee loop, so count($ansvarlig) got null and PHP 8 threw a
 * TypeError. The Medarb. dropdown below it, the group list fed to implode() and the lookups the slip rows count
 * had the same gap. sager/sager.php builds the same Ansvarlig list in sagsliste() and ret_sag().
 *
 * Pins: an empty tenant, a tenant where no user group has the create/edit-case right, and a slip whose case,
 * category and employees are all gone each render to the end of the page; a normal tenant still lists its
 * employees; both Ansvarlig blocks in sager.php end with an empty list and skip the query when nobody qualifies.
 * The database layer is a double that answers from fixture rows per query.
 *
 * Sager » Løn » Lønafregning (loen.php?funktion=loenafregning → loenafregning() → fod_indsaml_data() in
 * sager/loenIncludes/footer.php) had the same gap on test_39: $ansatte_id was only filled in the employee loop, so
 * count($ansatte_id) threw at footer.php:294. Pins: the settlement of a tenant without employees is drawn to the
 * end with no employee filter on the wage-slip queries, and a tenant with one employee keeps that filter.
 *
 * History:
 * 20261105 CL/LH Created.
 * 20261105 CL/LH The settlement (loenafregning) of a tenant without employees, and of one with a single employee.
 */
#[RunTestsInSeparateProcesses]
final class EmptyLookupListsTest extends TestCase
{
    private const RESPONSIBLE = 'from ansatte where konto_id=1 and gruppe IN';

    /** One user group with every right, as on test_39. */
    private const ALL_RIGHTS = ['id' => '5', 'beskrivelse' => 'Alle', 'box2' => '11111111'];

    /** A user group without the create/edit-case right (position 2 of box2). */
    private const NO_CASE_RIGHT = ['id' => '6', 'beskrivelse' => 'Montør', 'box2' => '10011111'];

    /**
     * Routes db_select()/db_fetch_array() to fixture rows; every query is recorded in $GLOBALS['__lookupQueries'].
     *
     * @param array<string, array<int, array<string, mixed>>> $tables rows per query, keyed by a fragment of its SQL
     */
    private static function stubDatabase(array $tables): void
    {
        $GLOBALS['__lookupTables'] = $tables;
        $GLOBALS['__lookupQueries'] = [];
        eval('
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") {
                $GLOBALS["__lookupQueries"][] = $q;
                foreach ($GLOBALS["__lookupTables"] as $fragment => $rows) {
                    if (strpos($q, $fragment) !== false) { return new ArrayIterator($rows); }
                }
                return new ArrayIterator([]);
            }
            function db_fetch_array($r) {
                if (!$r instanceof ArrayIterator || !$r->valid()) { return false; }
                $row = $r->current();
                $r->next();
                return $row;
            }
            function db_modify($q, $where = "") { return true; }
            function findtekst($key, $sprog_id = 1) { $parts = explode("|", (string) $key, 2); return $parts[1] ?? $parts[0]; }
        ');
    }

    /** @return string[] the queries sent for case responsibles */
    private static function responsibleQueries(): array
    {
        return array_values(array_filter($GLOBALS['__lookupQueries'], fn (string $q): bool => strpos($q, self::RESPONSIBLE) !== false));
    }

    /**
     * Runs loenliste() against the given rows and returns the page it prints.
     *
     * @param array<string, array<int, array<string, mixed>>> $tables rows per query, keyed by a fragment of its SQL
     */
    private static function renderWageList(array $tables): string
    {
        self::stubDatabase($tables + ['from brugere' => [['id' => '1', 'brugernavn' => 'e2etest']]]);
        require_once dirname(__DIR__, 3) . '/includes/std_func.php';
        require_once dirname(__DIR__, 3) . '/sager/loenliste.php';

        $GLOBALS['db'] = 'test_39';
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['brugernavn'] = 'e2etest';
        $GLOBALS['ansat_id'] = '';
        $GLOBALS['sag_rettigheder'] = '1111111';
        $_GET = [];
        $_POST = [];
        $_SESSION = [];

        ob_start();
        try {
            loenliste();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /**
     * The options of the named <select>, as value => label (the list writes some tags in upper case).
     *
     * @return array<string, string>
     */
    private static function options(string $html, string $name): array
    {
        self::assertSame(1, preg_match('~<select name="' . $name . '"[^>]*>(.*?)</select>~s', $html, $select), "the $name dropdown is drawn");
        preg_match_all('~<option value="([^"]*)">(.*?)</option>~is', $select[1], $found, PREG_SET_ORDER);
        $options = [];
        foreach ($found as $option) {
            $options[$option[1]] = trim(str_replace('&nbsp;', ' ', $option[2]));
        }
        return $options;
    }

    private static function assertWholePage(string $html): void
    {
        self::assertStringContainsString('<!-- end of paging_container -->', $html, 'the page is drawn to the end');
    }

    /** test_39: one group with every right, nobody employed, nothing booked. */
    public function test_a_tenant_without_employees_gets_the_whole_wage_list(): void
    {
        $html = self::renderWageList(["from grupper where art='brgrp'" => [self::ALL_RIGHTS]]);

        self::assertWholePage($html);
        self::assertSame(['' => ''], self::options($html, 's_ansvarlig'), 'Ansvarlig offers only the blank choice');
        self::assertSame(['' => ''], self::options($html, 's_ansat'), 'Medarb. offers only the blank choice');
        self::assertStringContainsString('Ingen resultater!', $html);
    }

    public function test_no_group_with_the_case_right_sends_no_empty_group_filter(): void
    {
        $html = self::renderWageList(["from grupper where art='brgrp'" => [self::NO_CASE_RIGHT]]);

        self::assertWholePage($html);
        self::assertSame(['' => ''], self::options($html, 's_ansvarlig'));
        self::assertSame([], self::responsibleQueries(), "no gruppe IN ('') query, which Postgres refuses for a numeric column");
    }

    /** A slip whose case, category and employees no longer exist is still listed. */
    public function test_a_slip_whose_lookups_find_nothing_is_still_listed(): void
    {
        $html = self::renderWageList([
            "from grupper where art='brgrp'" => [self::ALL_RIGHTS],
            'select * from loen ' => [[
                'id' => '9', 'nummer' => '41', 'tekst' => '', 'fordeling' => '', 'art' => 'timer', 'hvem' => '',
                'sag_id' => '3', 'sag_nr' => '12', 'opg_nr' => '1', 'sag_ref' => 'Gone', 'loendate' => '2026-11-02',
                'oprettet' => '1762000000', 'afsluttet' => '', 'godkendt' => '', 'afvist' => '', 'afregnet' => '',
                'sum' => '100', 'oprettet_af' => 'e2etest', 'ansatte' => '7', 'kategori' => '3', 'master_id' => '',
            ]],
        ]);

        self::assertWholePage($html);
        self::assertStringContainsString('<a href="loen.php?id=9&amp;funktion=ret_loen">', $html);
        self::assertStringNotContainsString('Ingen resultater', $html);
    }

    /**
     * Runs loenafregning() (the Lønafregning page, opened with refresh=on as the menu does) against the given rows.
     *
     * @param array<string, array<int, array<string, mixed>>> $tables rows per query, keyed by a fragment of its SQL
     */
    private static function renderSettlement(array $tables): string
    {
        self::stubDatabase($tables + ["from grupper where art='loen'" => [['box4' => '2026-01-05']]]);
        require_once dirname(__DIR__, 3) . '/includes/std_func.php';
        require_once dirname(__DIR__, 3) . '/sager/loenIncludes/footer.php';
        require_once dirname(__DIR__, 3) . '/sager/loenIncludes/afregning.php';

        $GLOBALS['db'] = 'test_39';
        $GLOBALS['sprog_id'] = 1;
        $GLOBALS['brugernavn'] = 'e2etest';
        $GLOBALS['ansat_id'] = '';
        $GLOBALS['sag_rettigheder'] = '1111111';
        $GLOBALS['bgcolor'] = '#eeeeee';
        $GLOBALS['charset'] = 'UTF-8';
        $GLOBALS['overtid_50pct'] = 0;
        $GLOBALS['overtid_100pct'] = 0;
        $_GET = ['funktion' => 'loenafregning', 'refresh' => 'on'];
        $_POST = [];

        ob_start();
        try {
            loenafregning();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /** @return string[] the wage-slip queries fod_indsaml_data() and the page send */
    private static function wageSlipQueries(): array
    {
        return array_values(array_filter($GLOBALS['__lookupQueries'], fn (string $q): bool => strpos($q, 'FROM loen ') !== false));
    }

    /** test_39: nobody employed, nothing booked. */
    public function test_a_tenant_without_employees_gets_the_whole_settlement(): void
    {
        $html = self::renderSettlement([]);

        self::assertStringContainsString('<!-- end of full container -->', $html, 'the settlement is drawn to the end');
        self::assertStringContainsString('I alt', $html, 'with its total row');
        $queries = self::wageSlipQueries();
        self::assertNotSame([], $queries);
        foreach ($queries as $q) {
            self::assertStringNotContainsString('ansatte like', $q, 'no employee filter without exactly one employee');
        }
    }

    /** The legacy filter: with exactly one employee in the tenant, the settlement reads only that employee's slips. */
    public function test_a_tenant_with_one_employee_keeps_the_employee_filter(): void
    {
        $html = self::renderSettlement(['FROM ansatte' => [['id' => '7', 'navn' => 'Jens Jensen']]]);

        self::assertStringContainsString('<!-- end of full container -->', $html);
        $filtered = array_filter(self::wageSlipQueries(), fn (string $q): bool => strpos($q, "ansatte like '%7%'") !== false);
        self::assertCount(4, $filtered, "fod_indsaml_data()'s three slip queries and the page's own");
    }

    public function test_a_tenant_with_employees_still_lists_them(): void
    {
        $html = self::renderWageList([
            "from grupper where art='brgrp'" => [self::ALL_RIGHTS, self::NO_CASE_RIGHT],
            'from ansatte where slutdate' => [['id' => '7', 'initialer' => 'JJ', 'navn' => 'Jens Jensen']],
            self::RESPONSIBLE => [['navn' => 'Anne Andersen', 'gruppe' => '5'], ['navn' => 'Bo Berg', 'gruppe' => '5']],
        ]);

        self::assertWholePage($html);
        self::assertSame(['' => '', 'Anne Andersen' => 'Anne Andersen', 'Bo Berg' => 'Bo Berg'], self::options($html, 's_ansvarlig'));
        self::assertSame(['' => '', '7' => '(JJ) Jens Jensen'], self::options($html, 's_ansat'));
        $responsible = self::responsibleQueries();
        self::assertCount(1, $responsible);
        self::assertStringContainsString("gruppe IN ('5')", $responsible[0], 'only groups with the create/edit-case right');
    }

    /**
     * sager.php is a page script (session, includes), so its two Ansvarlig blocks are lifted out and run here.
     *
     * @return array<string, array{string}>
     */
    public static function caseResponsibleBlocks(): array
    {
        return ['sagsliste()' => ['function sagsliste('], 'ret_sag()' => ['function ret_sag(']];
    }

    /**
     * The block from "// Query til ansvarlig" through the employee query and its loop, run against the given rows.
     *
     * @param array<string, array<int, array<string, mixed>>> $tables
     * @return array<int, string>|null $ansvarlig as the block leaves it
     */
    private static function runCaseResponsibleBlock(string $function, array $tables): ?array
    {
        $src = (string) file_get_contents(dirname(__DIR__, 3) . '/sager/sager.php');
        $start = strpos($src, '// Query til ansvarlig', (int) strpos($src, $function));
        self::assertNotFalse($start, "the Ansvarlig block is gone from $function");
        $query = strpos($src, self::RESPONSIBLE, $start);
        self::assertNotFalse($query);
        $end = strpos($src, "\n\t\n", $query);
        self::assertNotFalse($end);

        self::stubDatabase($tables);
        eval(substr($src, $start, $end - $start) . ';');
        return $ansvarlig ?? null;
    }

    #[DataProvider('caseResponsibleBlocks')]
    public function test_sager_without_case_responsibles_gets_an_empty_list(string $function): void
    {
        $ansvarlig = self::runCaseResponsibleBlock($function, ["from grupper where art='brgrp'" => [self::ALL_RIGHTS]]);

        self::assertSame([], $ansvarlig, 'the dropdown loops count() this list');
    }

    #[DataProvider('caseResponsibleBlocks')]
    public function test_sager_without_a_group_with_the_case_right_skips_the_query(string $function): void
    {
        $ansvarlig = self::runCaseResponsibleBlock($function, ["from grupper where art='brgrp'" => [self::NO_CASE_RIGHT]]);

        self::assertSame([], $ansvarlig);
        self::assertSame([], self::responsibleQueries());
    }

    #[DataProvider('caseResponsibleBlocks')]
    public function test_sager_lists_the_case_responsibles(string $function): void
    {
        $ansvarlig = self::runCaseResponsibleBlock($function, [
            "from grupper where art='brgrp'" => [self::ALL_RIGHTS, self::NO_CASE_RIGHT],
            self::RESPONSIBLE => [['navn' => 'Anne & Co', 'gruppe' => '5']],
        ]);

        self::assertSame(['Anne &amp; Co'], $ansvarlig);
        self::assertStringContainsString("gruppe IN ('5')", self::responsibleQueries()[0]);
    }
}
