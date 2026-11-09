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
     * Return the gate's condition line, or the empty string when it is not found.
     *
     * @return string The line that opens the refusal.
     */
    private static function gateLine(): string
    {
        foreach (preg_split('/\R/', self::onlineSource()) as $line) {
            if (strpos($line, 'substr(') !== false && strpos($line, '$modulnr') !== false
                && strpos($line, "< '1'") !== false && strpos($line, 'texteboks') === false) {
                return trim($line);
            }
        }

        return '';
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
        $call = substr($code, (int)$start, 400);

        self::assertStringContainsString(
            "substr((string)\$rettigheder, (int)\$modulnr, 1) < '1'",
            $call,
            'the push treats a page without the right as a popup, which is not recorded'
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
