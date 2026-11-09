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

final class LoenRatesRightTest extends TestCase
{
    private static function source(string $relative): string
    {
        $path = __DIR__ . '/../../../' . ltrim($relative, '/');
        $code = @file_get_contents($path);
        self::assertNotFalse($code, 'cannot read ' . $relative);

        return (string)$code;
    }

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

    public function testTheRatesPageAsksForTheApproverRight(): void
    {
        $loen = self::source('/sager/loen.php');

        $guard = strpos($loen, "if (\$funktion == 'satser' && !substr((string)\$sag_rettigheder, 6, 1)) {");
        self::assertNotFalse($guard, 'the rates page checks the right the menu asks for');

        $include = strpos($loen, "include('loenIncludes/rates.php');");
        self::assertNotFalse($include, 'the rates page still includes its view');
        self::assertLessThan($include, $guard, 'the right is checked before the include, so nothing is saved');

        self::assertStringContainsString('error_log(', substr($loen, $guard, 400), 'the refusal is logged');
        self::assertStringContainsString(
            "findtekst('8465|Du har ikke nogen rettigheder her - din aktivitet er blevet logget', \$sprog_id)",
            substr($loen, $guard, 400),
            'and shows the same text the rest of the system refuses with'
        );
    }

    public function testTheRatesIncludeRefusesASaveOnItsOwn(): void
    {
        $rates = self::source('/sager/loenIncludes/rates.php');

        $guard = strpos($rates, "if (isset(\$_POST['gruppe_id']) && !substr((string)\$sag_rettigheder, 6, 1)) {");
        self::assertNotFalse($guard, 'the include refuses a save without the approver right');

        $firstWrite = strpos($rates, 'db_modify(');
        self::assertNotFalse($firstWrite, 'the include still writes');
        self::assertLessThan($firstWrite, $guard, 'and refuses before the first write');

        self::assertStringContainsString("global \$sag_rettigheder;", $rates, 'the right is read from the session');
    }
}
