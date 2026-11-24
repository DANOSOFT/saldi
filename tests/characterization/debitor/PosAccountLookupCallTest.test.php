<?php
// 20261124 CL/MJ MB-188 The till's payment on account (or an amount ending in k) without a customer
// opens the customer lookup. It called pos_kontoopslag(), which was never defined, so the till
// stopped with a fatal error. The branch is read from the shipped files: every function it calls
// has to exist in the repo, and the lookup gets as many arguments as kontoopslag() takes.

namespace Saldi\Tests\PosCallSites;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PosAccountLookupCallTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function files(): array
    {
        return [
            'pos_ordre.php' => ['debitor/pos_ordre.php'],
            'itemId.php' => ['debitor/pos_ordre_includes/itemId.php'],
        ];
    }

    #[DataProvider('files')]
    public function testTheAccountBranchCallsTheLookupThatExists(string $file): void
    {
        $call = $this->lookupCall($file);

        self::assertSame('kontoopslag', $call['name'], 'the lookup the debitoropslag branch next to it uses');
        self::assertSame($this->parameterCount('kontoopslag'), $call['args'], 'kontoopslag() has no optional parameters');
        self::assertSame("'PO'", $call['first'], 'a POS lookup');
    }

    /**
     * The function called on the line after "if (!$modtaget || !$kontonr)" in the account branch.
     *
     * @return array{name: string, args: int, first: string}
     */
    private function lookupCall(string $file): array
    {
        $code = (string) file_get_contents($this->root() . '/' . $file);
        self::assertSame(1, preg_match('/==\s*\'k\'\s*\|\|\s*\$betaling\s*==\s*"konto"/', $code, $m, PREG_OFFSET_CAPTURE), "$file has the account branch");
        $start = $m[0][1];
        $at = strpos($code, '!$kontonr)', $start);
        self::assertNotFalse($at);
        $tokens = token_get_all('<?php ' . substr($code, $at + strlen('!$kontonr)'), 600));
        $name = null;
        $depth = 0;
        $args = 0;
        $first = '';
        foreach ($tokens as $token) {
            if ($name === null) {
                if (is_array($token) && $token[0] === T_STRING) {
                    $name = $token[1];
                }
                continue;
            }
            if ($token === '(') {
                $depth++;
                if ($depth === 1) {
                    $args = 1;
                    continue;
                }
            } elseif ($token === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($token === ',' && $depth === 1) {
                $args++;
                continue;
            }
            if ($depth === 1 && $args === 1 && !(is_array($token) && $token[0] === T_WHITESPACE)) {
                $first .= is_array($token) ? $token[1] : $token;
            }
        }
        self::assertNotNull($name, "$file calls a function there");

        return ['name' => (string) $name, 'args' => $args, 'first' => $first];
    }

    /**
     * The parameter count of the function as the till loads it: includes/ordrefunc.php includes
     * orderFuncIncludes/accountLookup.php (the creditor and jobkort pages have their own kontoopslag()).
     */
    private function parameterCount(string $function): int
    {
        $code = (string) file_get_contents($this->root() . '/includes/orderFuncIncludes/accountLookup.php');
        $found = preg_match('/function\s+' . preg_quote($function, '/') . '\s*\(([^)]*)\)/', $code, $m);
        self::assertSame(1, $found, "$function() is defined in includes/orderFuncIncludes/accountLookup.php");
        self::assertStringNotContainsString('=', $m[1], 'no optional parameters');

        return substr_count($m[1], '$');
    }

    private function root(): string
    {
        return dirname(__DIR__, 3);
    }
}
