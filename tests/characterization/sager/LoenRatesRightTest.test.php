<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// 20261109 LOE SST-969 Pins the rights check on the payroll rates page.
//
//                  sager/loen.php calls the function the request names ($funktion()), and its rates page
//                  saved for whoever reached it. The pages are session-gated and cannot be included from a
//                  test, so the rules are read out of their source, the way ReadonlyMenuTest does for
//                  MB-62: a whitelist of the four page functions, the approver right the menu already asks
//                  for, and a save in the include that asks for the same right.
//
// History:
// 20261109 LOE SST-969 created.
// 20261109 LOE SST-969 the refusals have to end the request, so the guards are checked for that too.

final class LoenRatesRightTest extends TestCase
{
    /**
     * Read a page from the repository.
     *
     * @param string $relative Path from the repository root, with a leading slash.
     *
     * @return string The file's source.
     */
    private static function source(string $relative): string
    {
        $path = __DIR__ . '/../../../' . ltrim($relative, '/');
        $code = @file_get_contents($path);
        self::assertNotFalse($code, 'cannot read ' . $relative);

        return (string)$code;
    }

    /**
     * Return the block a guard opens with, from its condition to the closing brace.
     *
     * Counting braces is enough here: the two guards are a few lines of plain statements, without a
     * string that carries a brace.
     *
     * @param string $code  Source that contains the guard.
     * @param string $guard The guard's first line, up to and including the opening brace.
     *
     * @return string The guard's body, or the empty string when the guard is absent.
     */
    private static function guardBody(string $code, string $guard): string
    {
        $start = strpos($code, $guard);
        if ($start === false) {
            return '';
        }
        $depth = 0;
        $length = strlen($code);
        for ($i = $start; $i < $length; $i++) {
            if ($code[$i] === '{') {
                $depth++;
            } elseif ($code[$i] === '}' && --$depth === 0) {
                return substr($code, $start, $i - $start + 1);
            }
        }

        return '';
    }

    /**
     * Only the page's own functions may be reached through a request.
     *
     * @return void
     */
    public function testOnlyThePageFunctionsCanBeCalled(): void
    {
        $loen = self::source('/sager/loen.php');

        // $funktion() runs the name the request sent, so the name has to be one of the file's own functions.
        self::assertStringContainsString(
            "\$loen_funktioner = array('loenliste', 'ret_loen', 'loenafregning', 'satser');",
            $loen,
            'the page functions are named in one list'
        );
        self::assertStringContainsString(
            'if (!in_array($funktion, $loen_funktioner, true)) {',
            $loen,
            'and a name outside that list is replaced'
        );

        $whitelist = strpos($loen, '$loen_funktioner = array(');
        $call = strpos($loen, '$funktion();');
        self::assertNotFalse($call, 'the page still calls the function the request names');
        self::assertLessThan($call, $whitelist, 'the list is applied before the call, not after it');
    }

    /**
     * The page refuses a user without the approver right, and the refusal ends the request.
     *
     * The guard is only a refusal if it leaves before the include: a guard that logs and then falls
     * through still draws the form, so the body is checked for the exit, not just for its position.
     *
     * @return void
     */
    public function testTheRatesPageRefusesAndLeavesBeforeTheForm(): void
    {
        $loen = self::source('/sager/loen.php');

        $guardLine = "if (\$funktion == 'satser' && !substr((string)\$sag_rettigheder, 6, 1)) {";
        $body = self::guardBody($loen, $guardLine);
        self::assertNotSame('', $body, 'the rates page checks the right the menu asks for');

        $include = strpos($loen, "include('loenIncludes/rates.php');");
        self::assertNotFalse($include, 'the rates page still includes its view');
        self::assertLessThan($include, strpos($loen, $guardLine), 'the right is checked before the include');

        self::assertStringContainsString('exit;', $body, 'and the refusal ends the request before the form is drawn');
        self::assertStringContainsString('error_log(', $body, 'the attempt is logged');
        self::assertStringContainsString(
            "findtekst('8465|Du har ikke nogen rettigheder her - din aktivitet er blevet logget', \$sprog_id)",
            $body,
            'and it shows the text the rest of the system refuses with'
        );
    }

    /**
     * The include refuses a save without the approver right, and the refusal returns before any write.
     *
     * @return void
     */
    public function testTheRatesIncludeRefusesASaveOnItsOwn(): void
    {
        $rates = self::source('/sager/loenIncludes/rates.php');

        $guardLine = "if (isset(\$_POST['gruppe_id']) && !substr((string)\$sag_rettigheder, 6, 1)) {";
        $body = self::guardBody($rates, $guardLine);
        self::assertNotSame('', $body, 'the include refuses a save without the approver right');
        self::assertStringContainsString('return;', $body, 'and the refusal returns before the form is drawn');

        $firstWrite = strpos($rates, 'db_modify(');
        self::assertNotFalse($firstWrite, 'the include still writes');
        self::assertLessThan($firstWrite, strpos($rates, $guardLine), 'the save is refused before the first write');

        self::assertStringContainsString("global \$sag_rettigheder;", $rates, 'the right is read from the session');
        self::assertStringContainsString('error_log(', $body, 'and the refused save is logged');
    }
}
