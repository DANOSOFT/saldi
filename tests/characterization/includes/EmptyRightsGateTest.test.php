<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261109 LOE SST-967 Pins the rights gate that a user with an empty rights string used to skip.
//
//                  includes/online.php refuses a page when the user's character at the page's $modulnr
//                  position is below '1'. The first term of that condition used to be ($rettigheder), so
//                  an EMPTY rights string made the whole condition false and every page that sets
//                  $modulnr opened by address - 510 of 528 users at the reporting customer have such a
//                  string. The pages are session-gated and cannot be included from a test, so the rule is
//                  read out of the source, the way ReadonlyMenuTest does for MB-62.
//
// History:
// 20261109 LOE SST-967 created.
// 20261110 LOE SST-967 The gate is one computation, the Mit Salg session and the sag workflow are pinned.

final class EmptyRightsGateTest extends TestCase
{
    /**
     * Read the rights gate out of includes/online.php.
     *
     * @return string The source of the file.
     */
    private static function onlineSource(): string
    {
        $path = __DIR__ . '/../../../includes/online.php';
        $code = @file_get_contents($path);
        self::assertNotFalse($code, 'cannot read includes/online.php');

        return (string)$code;
    }

    /**
     * Return the line that decides whether a page is refused, or the empty string when it is not found.
     *
     * The condition is computed once, in $no_module_right, and both the navigation stack decision and the
     * refusal read it. Searching for the line that assigns it keeps this test pointed at the real gate even
     * though the same substr() check used to appear in the nav_push() call as well.
     *
     * @return string The line that computes $no_module_right.
     */
    private static function gateLine(): string
    {
        $code = self::onlineSource();
        $start = strpos($code, '$no_module_right = ');
        if ($start === false) {
            return '';
        }
        // The statement spans two lines; take it up to its terminating semicolon.
        $end = strpos($code, ';', (int)$start);
        if ($end === false) {
            return '';
        }

        return (string)preg_replace('/\s+/', ' ', substr($code, (int)$start, (int)$end - (int)$start));
    }

    /**
     * An empty rights string must not switch the gate off.
     *
     * @return void
     */
    public function testAnEmptyRightsStringIsReadAsNoRights(): void
    {
        $line = self::gateLine();
        self::assertNotSame('', $line, 'the rights gate is still there');

        // The old shape began with ($rettigheder) &&, which made the whole condition false for ''.
        self::assertStringNotContainsString(
            '($rettigheder) &&',
            $line,
            'the gate no longer asks whether the rights string is truthy'
        );
        self::assertStringContainsString(
            "substr((string)\$rettigheder, (int)\$modulnr, 1) < '1'",
            $line,
            'it asks for the character at the page module position, which is empty for an empty string'
        );
        self::assertStringContainsString('($modulnr)', $line, 'and keeps the module test, so a page without one is untouched');
        // '0' is what the Mit Salg sessions write; it is falsy, so it used to skip the gate, and
        // lager/labelprint.php must keep working for those sessions.
        // The Mit Salg sessions write '0' in the session row and have no brugere row, so the value the gate
        // asks about is the one the session carried, not the one the brugere lookup leaves behind.
        self::assertStringContainsString(
            "(string)\$online_rettigheder !== '0'",
            $line,
            'the Mit Salg placeholder session keeps working, as it did before'
        );
        self::assertStringContainsString(
            "\$online_rettigheder = \$r['rettigheder'];",
            self::onlineSource(),
            'the session rights are kept before the brugere lookup replaces them'
        );
    }

    /**
     * The refusal must end the request and keep a way back.
     *
     * @return void
     */
    public function testTheRefusalEndsTheRequestAndOffersAWayBack(): void
    {
        $code = self::onlineSource();
        $start = strpos($code, "substr((string)\$rettigheder, (int)\$modulnr, 1) < '1'");
        self::assertNotFalse($start, 'the gate is present');

        $end = strpos($code, '}', (int)strpos($code, 'exit;', $start));
        $body = (string)substr($code, (int)$start, (int)$end - (int)$start);

        self::assertStringContainsString('tekstboks(', $body, 'the refusal shows the standard box');
        self::assertStringContainsString('8465|', $body, 'with the no-rights text');
        self::assertStringContainsString('exit;', $body, 'and ends the request before the page runs');
        // The box hides itself when it is closed, and a refused page is only the box.
        self::assertStringContainsString('index/main.php', $body, 'the refusal offers a way back to the menu shell');
        self::assertStringContainsString('z-index:101', $body, 'and that link sits above the box');
        // The shell frames the page, so the link has to leave the frame instead of loading a menu inside it.
        self::assertStringContainsString('target=\\"_top\\"', $body, 'the way back leaves the shell iframe');
        // Where it goes: the landing rule index/login.php applies, because the shell alone is a dead end
        // for a user who only has craft rights (measured: no Sager entries in its menu).
        self::assertStringContainsString("\$refusal_target = 'index/main.php';", $body, 'the shell is the fallback');
        self::assertStringContainsString("\$refusal_target = 'sager/sager.php';", $body,
            'a user who may work cases goes to the case list');
        self::assertStringContainsString("\$refusal_target = 'sager/loen.php';", $body,
            'a user with only the payroll craft right goes to the payroll list');
        self::assertStringContainsString('get_relative() . $refusal_target', $body,
            'and the link is built from that target');
    }

    /**
     * A sag workflow page runs on the craft right, and only on a request it has proven.
     *
     * @return void
     */
    public function testASagWorkflowPageRunsOnTheCraftRight(): void
    {
        $code = self::onlineSource();
        $start = strpos($code, '$sag_workflow_allowed = ');
        self::assertNotFalse($start, 'the exception is computed next to the gate');
        $block = substr($code, (int)$start, 2400);

        self::assertStringContainsString('$sag_workflow_page', $block, 'the page declares that it belongs to the workflow');
        // The check runs in the gate, because that is where the tenant database is open.
        self::assertStringContainsString('select konto_id from sager where id =', $block, 'the case is resolved');
        self::assertStringContainsString("\$_GET['konto_id'] = \$wf_customer;", $block,
            'and the customer comes from the case, not from the request');
        self::assertStringContainsString('select sag_id from ordrer where id =', $block,
            'a link to an order is traced back to its case');
        // debitor/ordre.php saves with $_POST['konto_id'], so the GET alone is not enough.
        self::assertStringContainsString("\$_POST['konto_id'] = \$wf_customer;", $block,
            'the save path is pinned to the case customer too');
        self::assertStringContainsString('select id from sager where konto_id =', $block,
            'a customer history needs a case for that customer');
        self::assertStringContainsString("substr((string)\$sag_rettigheder, 2, 1)", $block,
            'the right it uses is the one the Sager pages ask for');

        self::assertStringContainsString('if ($no_module_right && !$sag_workflow_allowed)',
            $code, 'the refusal asks for both: no module right and no valid workflow request');

        // The order page: the case must exist and the customer comes from it, not from the request.
        $ordre = @file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
        self::assertNotFalse($ordre, 'cannot read debitor/ordre.php');
        self::assertStringContainsString("\$sag_workflow_page = 'sag';", (string)$ordre, 'the order page declares the workflow');
        self::assertStringContainsString('$sag_workflow_sag =', (string)$ordre, 'and passes the case it was asked for');
        self::assertStringContainsString('$sag_workflow_order =', (string)$ordre, 'and the order, for a link that only carries one');
        self::assertStringContainsString('$sag_workflow_konto =', (string)$ordre, 'and the customer the address names, so the gate can check it');
        self::assertLessThan(strpos((string)$ordre, 'include("../includes/online.php");'),
            strpos((string)$ordre, "\$sag_workflow_page = 'sag';"),
            'the declaration is there before online.php reads it');

        // The history page: only a customer the company has a case for.
        $hist = @file_get_contents(__DIR__ . '/../../../debitor/historikkort.php');
        self::assertNotFalse($hist, 'cannot read debitor/historikkort.php');
        self::assertStringContainsString("\$sag_workflow_page = 'customer';", (string)$hist, 'the history page declares it too');
        self::assertStringContainsString('$sag_workflow_konto =', (string)$hist, 'and passes the customer it was asked for');
    }

    /**
     * A refused page must not enter the navigation stack.
     *
     * online.php records the request in the nav stack before the rights gate runs, so without this the
     * refused address stays on the stack and a back link on a later page returns to it.
     *
     * @return void
     */
    public function testARefusedPageIsNotRecordedInTheNavStack(): void
    {
        $code = self::onlineSource();
        $start = strpos($code, 'nav_push(null,');
        self::assertNotFalse($start, 'the page is still pushed onto the nav stack');
        $call = substr($code, (int)$start, 300);

        self::assertStringContainsString(
            '$no_module_right && !$sag_workflow_allowed',
            $call,
            'the push skips a page the gate is about to refuse'
        );
    }

    /**
     * The gate must stay out of the pages that have no module number.
     *
     * Pages with $modulnr unset or 0 are not gated, which is why the six MB-63 pages need their own
     * check: this fix closes the empty-rights bypass, not the missing $modulnr.
     *
     * @return void
     */
    public function testPagesWithoutAModuleNumberStayOutsideTheGate(): void
    {
        $line = self::gateLine();
        self::assertStringContainsString('($modulnr) &&', $line, 'the module test comes first, so 0 and unset are skipped');

        // Evidence for the boundary: these pages set no $modulnr, so this gate never fires for them.
        foreach (array('/finans/saft.php', '/finans/regnskabbasis.php', '/finans/kontospec.php') as $relative) {
            $code = @file_get_contents(__DIR__ . '/../../..' . $relative);
            self::assertNotFalse($code, 'cannot read ' . $relative);
            self::assertStringNotContainsString('$modulnr', (string)$code, $relative . ' sets no module number, so MB-63 still owns it');
        }
    }
}
