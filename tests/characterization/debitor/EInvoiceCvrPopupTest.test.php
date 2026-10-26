<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// 20261026 CL/MMR SD-774 Created. Pins when ordre.php renders and auto-opens the CVR popup, and how the popup saves: through debitor/einvoiceCvrSave.php (JSON), never by posting the order form.
//                 Codex round 2 found that an invoiced order in PDF mode had no popup script, so choosing Digitalt after Annuller never reopened it; rounds 2-3 asked for Post/Redirect/Get for the save, which the AJAX save makes unnecessary.
//                 einvoiceCvrPopupState(), einvoiceCvrSaveRequestProblem() and einvoiceCvrSaveJson() take plain values, so they are tested directly; ordre.php, the endpoint, the script and easyUblCvrMissing() are asserted on the source.
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
        $state = einvoiceCvrPopupState($order, $card, $redirectPending);

        self::assertSame($render, $state['render'], 'render');
        self::assertSame($autoOpen, $state['autoOpen'], 'autoOpen');
        self::assertSame((int)$order['id'], $state['orderId']);
    }

    public function testTheEndpointOnlySavesForALoggedInPost(): void
    {
        self::assertSame('method', einvoiceCvrSaveRequestProblem('GET', true));
        self::assertSame('method', einvoiceCvrSaveRequestProblem(null, true));
        self::assertSame('session', einvoiceCvrSaveRequestProblem('POST', false));
        self::assertSame('', einvoiceCvrSaveRequestProblem('POST', true));
    }

    /** @return array<string, array{array<string, mixed>, array<string, mixed>}> */
    public static function answers(): array
    {
        $saved = array('handled' => true, 'problem' => '', 'cvr' => '29190623', 'status' => 4, 'savedCard' => true, 'cardChanged' => false);
        return [
            'saved on an invoiced order' => [$saved, array('ok' => true, 'problem' => '', 'error' => '', 'cvr' => '29190623', 'open' => false, 'savedCard' => true, 'cardChanged' => false)],
            'saved on an open order'     => [array('status' => 0, 'savedCard' => false) + $saved, array('ok' => true, 'problem' => '', 'error' => '', 'cvr' => '29190623', 'open' => true, 'savedCard' => false, 'cardChanged' => false)],
            'card changed meanwhile'     => [array('savedCard' => false, 'cardChanged' => true) + $saved, array('ok' => true, 'problem' => '', 'error' => '', 'cvr' => '29190623', 'open' => false, 'savedCard' => false, 'cardChanged' => true)],
            'input refused'              => [array('problem' => 'invalid', 'cvr' => '') + $saved, array('ok' => false, 'problem' => 'invalid', 'error' => '', 'cvr' => '', 'open' => false, 'savedCard' => true, 'cardChanged' => false)],
            'order cannot take it'       => [array('handled' => false), array('ok' => false, 'problem' => '', 'error' => 'order', 'cvr' => '', 'open' => true, 'savedCard' => false, 'cardChanged' => false)],
        ];
    }

    /**
     * The JSON the popup gets back. Only a saved CVR is ok; a refused input names the problem, so the
     * popup shows the same text as its own check; an order that cannot take a CVR is an error.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $expected
     */
    #[DataProvider('answers')]
    public function testTheEndpointAnswersWithTheSaveResult(array $result, array $expected): void
    {
        self::assertSame($expected, einvoiceCvrSaveJson($result));
    }

    /**
     * The popup never posts the order form, so ordre.php has no CVR save path, no redirect for it and
     * renders as before. The endpoint sends only JSON (online.php's output is dropped), needs debitor
     * rights and saves only for a logged-in POST.
     */
    public function testTheSaveGoesThroughTheJsonEndpointOnly(): void
    {
        $ordre = (string)file_get_contents(self::ROOT . '/debitor/ordre.php');
        self::assertStringNotContainsString('einvoiceCvrSaveFromOrder(', $ordre, 'ordre.php saves the CVR from a form post again');
        self::assertStringNotContainsString("'einvoiceCvrSave'", $ordre);
        self::assertStringContainsString("\nordreside(\$id, \$regnskab);\nfunction ordreside(\$id, \$regnskab)\n", $ordre);

        $endpoint = (string)file_get_contents(self::ROOT . '/debitor/einvoiceCvrSave.php');
        $obStart = strpos($endpoint, 'ob_start();');
        $online = strpos($endpoint, '/../includes/online.php');
        $obClean = strpos($endpoint, 'ob_end_clean();');
        $json = strpos($endpoint, "header('Content-Type: application/json");
        $check = strpos($endpoint, 'einvoiceCvrSaveRequestProblem(');
        $save = strpos($endpoint, 'einvoiceCvrSaveFromOrder(');
        foreach (array('ob_start' => $obStart, 'online.php' => $online, 'ob_end_clean' => $obClean, 'json header' => $json, 'request check' => $check, 'save' => $save) as $what => $at) {
            self::assertNotFalse($at, "$what is gone from the endpoint");
        }
        self::assertLessThan($online, $obStart, 'online.php can print before the output buffer starts');
        self::assertGreaterThan($online, $obClean);
        self::assertLessThan($json, $obClean, 'online.php output can reach the JSON answer');
        self::assertLessThan($save, $check, 'the endpoint saves before it checks the request');
        self::assertMatchesRegularExpression('/\$modulnr = 5;/', $endpoint, 'the endpoint no longer needs debitor rights');
        self::assertMatchesRegularExpression('/\$header = "nix";/', $endpoint, 'online.php prints its page head into the endpoint');

        $js = (string)file_get_contents(self::ROOT . '/javascript/einvoiceCvrPopup.js');
        self::assertStringContainsString('fetch(', $js);
        self::assertStringNotContainsString('HTMLFormElement.prototype.submit', $js, 'the popup posts the order form again');
        self::assertStringContainsString('window.location.replace(', $js, 'an invoiced order is no longer loaded again with GET after the save');
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
