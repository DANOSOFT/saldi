<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261026 CL/MMR SD-774 Created. Pins when ordre.php renders and auto-opens the CVR popup, the redirect after a CVR saved on an invoiced order (Post/Redirect/Get), and that the save runs before any output.
//                 Codex round 2 found that an invoiced order in PDF mode had no popup script, so choosing Digitalt after Annuller never reopened it, and that the save rendered its POST directly.
//                 einvoiceCvrPopupState() and einvoiceCvrSavedRedirectUrl() take plain arrays, so they are tested directly; the order of steps in ordre.php and the parameters of easyUblCvrMissing() are asserted on the source.
#[RunTestsInSeparateProcesses]
final class EInvoiceCvrPopupTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    /** A municipal GLN, the one the SST-860 tests use. */
    private const GLN = '5798009003553';

    protected function setUp(): void
    {
        require_once self::ROOT . '/includes/std_func.php';
        require_once self::ROOT . '/debitor/orderIncludes/einvoiceCvr.php';
    }

    /** @return array<string, array{array<string, mixed>, array<string, string>, bool, bool, bool}> */
    public static function orders(): array
    {
        $posted = array('id' => 166784, 'art' => 'DO', 'status' => 4, 'digital_status' => 'Error', 'ean' => self::GLN, 'cvrnr' => '', 'land' => 'Danmark');
        $open = array('status' => 0, 'digital_status' => '') + $posted;
        $noCard = array('cvrnr' => '', 'ean' => '', 'land' => '');
        return [
            'invoiced, Digitalt, no CVR'               => [array('udskriv_til' => 'Digitalt') + $posted, $noCard, false, true, true],
            'invoiced, PDF, no CVR (round 2 F1)'       => [array('udskriv_til' => 'PDF') + $posted, $noCard, false, true, false],
            'invoiced, PDF, placeholder CVR'           => [array('udskriv_til' => 'PDF', 'cvrnr' => '-') + $posted, $noCard, false, true, false],
            'invoiced, PDF, CVR on the card'           => [array('udskriv_til' => 'PDF') + $posted, array('cvrnr' => '29189595') + $noCard, false, false, false],
            'invoiced, PDF, CVR on the order'          => [array('udskriv_til' => 'PDF', 'cvrnr' => '29190623') + $posted, $noCard, false, false, false],
            'invoiced, PDF, foreign receiver'          => [array('udskriv_til' => 'PDF', 'land' => 'Sverige') + $posted, $noCard, false, false, false],
            'invoiced, PDF, page about to redirect'    => [array('udskriv_til' => 'PDF') + $posted, $noCard, true, false, false],
            'invoiced, already delivered'              => [array('udskriv_til' => 'Digitalt', 'digital_status' => 'Sent') + $posted, $noCard, false, false, false],
            'open order, PDF, no CVR'                  => [array('udskriv_til' => 'PDF') + $open, $noCard, false, true, false],
            'open order, PDF, CVR on the card'         => [array('udskriv_til' => 'PDF') + $open, array('cvrnr' => '29189595') + $noCard, false, false, false],
            'open order, Digitalt, no CVR'             => [array('udskriv_til' => 'Digitalt') + $open, $noCard, false, true, true],
        ];
    }

    /**
     * The script must be on the page whenever choosing Digitalt can lead to the popup: on an
     * invoiced order the select submits the form, and the script has to forget an earlier
     * Annuller before that reload, or the popup stays closed for the rest of the session.
     *
     * @param array<string, mixed>  $order
     * @param array<string, string> $card
     */
    #[DataProvider('orders')]
    public function testThePopupScriptIsOnThePageWheneverDigitaltCanNeedIt(array $order, array $card, bool $redirectPending, bool $render, bool $autoOpen): void
    {
        $state = einvoiceCvrPopupState($order, $card, $redirectPending, array());

        self::assertSame($render, $state['render'], 'render');
        self::assertSame($autoOpen, $state['autoOpen'], 'autoOpen');
        self::assertSame((int)$order['id'], $state['orderId']);
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>, string}> */
    public static function saves(): array
    {
        $saved = array('handled' => true, 'problem' => '', 'status' => 4, 'ordreId' => 166784);
        return [
            'invoiced order, saved'                 => [$saved, array('id' => '166784'), 'ordre.php?id=166784'],
            'keeps sag_id and returside'            => [$saved, array('id' => '1', 'sag_id' => '12', 'returside' => 'ordreliste.php'), 'ordre.php?id=166784&sag_id=12&returside=ordreliste.php'],
            'returside is encoded'                  => [$saved, array('returside' => '../x.php?a=1&b=2'), 'ordre.php?id=166784&returside=..%2Fx.php%3Fa%3D1%26b%3D2'],
            'array parameters are dropped'          => [$saved, array('sag_id' => array('1'), 'returside' => array('x')), 'ordre.php?id=166784'],
            'rejected input renders the error'      => [array('problem' => 'invalid') + $saved, array(), ''],
            'open order keeps its own Gem flow'     => [array('status' => 1) + $saved, array(), ''],
            'not handled'                           => [array('handled' => false) + $saved, array(), ''],
            'no save at all'                        => [array(), array(), ''],
        ];
    }

    /**
     * A CVR saved on an invoiced order redirects back to the order, so a refresh does not post
     * the save again. A rejected input wrote nothing and is shown again with its error.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $query
     */
    #[DataProvider('saves')]
    public function testASavedCvrOnAnInvoicedOrderRedirects(array $result, array $query, string $expected): void
    {
        self::assertSame($expected, einvoiceCvrSavedRedirectUrl($result, $query));
    }

    /**
     * In ordre.php the save and its redirect come before anything is printed, and before the
     * order's own POST handling, which on an open order saves cvrnr from the form again.
     */
    public function testOrdrePhpSavesAndRedirectsBeforeAnyOutput(): void
    {
        $src = (string)file_get_contents(self::ROOT . '/debitor/ordre.php');
        $firstOutput = null;
        foreach (token_get_all($src) as $t) {
            if (is_array($t) && in_array($t[0], array(T_PRINT, T_ECHO, T_INLINE_HTML), true)
                && !($t[0] === T_INLINE_HTML && trim($t[1]) === '')) {
                $firstOutput = $t[2];
                break;
            }
        }
        self::assertNotNull($firstOutput, 'ordre.php prints nothing?');

        $lines = explode("\n", $src);
        $find = static function (string $needle) use ($lines): int {
            foreach ($lines as $i => $line) {
                if (strpos($line, $needle) !== false && strpos(ltrim($line), '//') !== 0) {
                    return $i + 1;
                }
            }
            return PHP_INT_MAX;
        };
        $save = $find('einvoiceCvrSaveFromOrder(');
        $redirect = $find("header('Location: ' . \$einvoiceCvrRedirect, true, 303)");
        $orderPost = $find("if ((\$b_submit || isset(\$_POST['udskriv_til'])) && \$id = \$_POST['id'])");

        self::assertLessThan($firstOutput, $save, "the popup's CVR save runs after output has started (line $firstOutput)");
        self::assertLessThan($firstOutput, $redirect, 'the redirect after a saved CVR is gone or comes after output');
        self::assertGreaterThan($save, $redirect);
        self::assertLessThan($orderPost, $save, "the order's own POST handling runs before the popup's CVR is put into \$_POST['cvrnr']");
    }

    /** easyUblCvrMissing() gets its context as parameters; peppol.php hands it over through sendInvoice(). */
    public function testTheMissingCvrPageTakesItsContextAsParameters(): void
    {
        $api = (string)file_get_contents(self::ROOT . '/debitor/api.php');
        $a = strpos($api, 'function easyUblCvrMissing(');
        $b = strpos($api, 'function sendInvoice(', (int)$a);
        self::assertNotFalse($a);
        self::assertNotFalse($b);
        $body = substr($api, $a, $b - $a);
        self::assertStringContainsString('function easyUblCvrMissing($ean, $orderId, $db, $dbEncode, $sprogId)', $body);
        self::assertDoesNotMatchRegularExpression('/^\s*global\s/m', $body, 'easyUblCvrMissing() reaches shared state through global again');

        $peppol = (string)file_get_contents(self::ROOT . '/debitor/peppol.php');
        foreach (array('invoice', 'creditnote') as $type) {
            self::assertMatchesRegularExpression('/sendInvoice\(\$id, "' . $type . '", array\("db_encode" => /', $peppol,
                "peppol.php no longer hands its encoding and language to sendInvoice() for $type");
        }
    }
}
