<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/stdFunc/shopApiRequest.php';

// 20261016 CL/MJ SST-876 Pins that a refused webshop stock update is detected and reported.
//
//                  Havemoebelland's stock stopped reaching DirekteImport.dk, and later
//                  havemoebelland.dk too, after receiving supplier orders. Saldi showed
//                  nothing wrong for days. Two reasons, both pinned here: every caller
//                  discarded what sync_shop_vare() returned, and a shop answering http 200
//                  counted as success whatever its body said.
//
//                  The bridge script between Saldi and the shop is not in this repository
//                  (.gitignore), so whether a particular shop accepted a figure cannot be
//                  tested here. What is testable is that Saldi notices and says so, which
//                  is what nobody could see.
//
//                  sync_shop_vare() and the pages that call it are legacy page-scope code
//                  that opens sessions and needs a tenant database, so those parts are read
//                  from the source or lifted and evaluated rather than executed.
// 20261028 CDX/MJ SST-876 Document that receipts preserve full adapter metadata synchronization.
final class ShopStockSyncErrorVisibilityTest extends TestCase
{
    private const STD_FUNC = __DIR__ . '/../../../includes/std_func.php';
    private const MODTAG = __DIR__ . '/../../../kreditor/modtag.php';
    private const VAREKORT = __DIR__ . '/../../../lager/varekort.php';
    private const ORDREFUNC = __DIR__ . '/../../../includes/ordrefunc.php';
    private const LEVERING = __DIR__ . '/../../../debitor/levering.php';
    private const MASSEFAKT = __DIR__ . '/../../../debitor/massefakt.php';
    private const TEKSTER = __DIR__ . '/../../../importfiler/tekster.csv';

    /** @var array<string, string> */
    private static array $src = array();

    private static function source(string $path): string
    {
        if (!isset(self::$src[$path])) {
            $text = file_get_contents($path);
            self::assertNotFalse($text, "could not read $path");
            self::$src[$path] = $text;
        }
        return self::$src[$path];
    }

    /** The block of sync_shop_vare() from its signature to its closing marker. */
    private static function syncShopVare(): string
    {
        $src = self::source(self::STD_FUNC);
        $at = strpos($src, 'function sync_shop_vare(');
        self::assertNotFalse($at, 'sync_shop_vare() is gone');
        $end = strpos($src, '#endfunc sync_shop_vare()', $at);
        self::assertNotFalse($end, 'the end marker of sync_shop_vare() is gone');
        return substr($src, $at, $end - $at);
    }

    /** Just the variant half, which is what a received order line with a variant takes. */
    private static function variantBranch(): string
    {
        $block = self::syncShopVare();
        $at = strpos($block, 'if ($variant_id) {');
        self::assertNotFalse($at, 'the variant branch is gone');
        $end = strpos($block, '} else {', $at);
        self::assertNotFalse($end, 'the variant branch no longer has a non-variant counterpart');
        return substr($block, $at, $end - $at);
    }

    // ---------------------------------------------------------------- the detector

    /** @return array<string, array{string}> */
    public static function acceptedBodies(): array
    {
        return array(
            'the bare stock figure the adapters answer' => array('5'),
            'a figure with surrounding whitespace'      => array("  3  \n"),
            'an empty body'                             => array(''),
            'a json stock payload'                      => array('{"stock":5,"stockCount":5}'),
            'a plain OK'                                => array('OK'),
            'json saying success'                       => array('{"success":true,"stock":5}'),
        );
    }

    /**
     * A working adapter must keep counting as success.
     *
     * This is the half that decides whether the change is safe to deploy: every existing
     * installation answers one of these, and treating any of them as an error would turn
     * every sync on every account into a visible failure.
     */
    #[DataProvider('acceptedBodies')]
    public function testAnAcceptedAnswerIsNotTreatedAsARejection(string $body): void
    {
        self::assertSame('', shopApiResponseRejected($body),
            'a working shop answer must not be reported as a failure');
    }

    /** @return array<string, array{string}> */
    public static function rejectedBodies(): array
    {
        return array(
            'json denying success'      => array('{"success":false,"message":"no such product"}'),
            'json denying ok'           => array('{"ok":false}'),
            'json with an error field'  => array('{"error":"unknown sku"}'),
            'json with an errors list'  => array('{"errors":["bad token"]}'),
            'json with a nested 403'    => array('{"data":{"status":403}}'),
            'json with status failed'   => array('{"status":"failed"}'),
            'a php error line'          => array('Error: could not reach shop'),
            'a php warning page'        => array('<br /><b>Warning</b>: fopen failed'),
        );
    }

    /** A shop that answers 200 and then says no is a failure, not a success. */
    #[DataProvider('rejectedBodies')]
    public function testARejectionInsideA200IsDetected(string $body): void
    {
        self::assertNotSame('', shopApiResponseRejected($body),
            'this body denies the update, so it must not count as a successful sync');
    }

    /** The source of one of the two senders, from its signature to the end of its wrapper. */
    private static function senderBody(string $name): string
    {
        $src = self::source(__DIR__ . '/../../../includes/stdFunc/shopApiRequest.php');
        $at = strpos($src, 'function ' . $name . '(');
        self::assertNotFalse($at, $name . '() is gone');
        $end = strpos($src, "\nif (!function_exists(", $at);
        return substr($src, $at, ($end === false ? strlen($src) : $end) - $at);
    }

    /**
     * A 2xx is never called a success until the body has been judged.
     *
     * The judgement lives in shopApiOutcome() rather than inline, because there are now two
     * senders - one call at a time and all three shops at once - and two senders judging a
     * shop's answer by two rules is the defect this ticket is about. So this pins the
     * classifier itself and then pins that neither sender can report success around it.
     */
    public function testA200IsNeverASuccessUntilTheBodyIsJudged(): void
    {
        $answer = array('error' => '', 'httpCode' => 200, 'transient' => false, 'unreachable' => false);
        self::assertSame('ok', shopApiOutcome(array('body' => '5') + $answer)['state'],
            'the bare figure a working adapter answers no longer counts as success');
        self::assertSame('refused', shopApiOutcome(array('body' => '{"success":false}') + $answer)['state'],
            'a 200 that denies the update counts as a success again');
        self::assertSame('failed', shopApiOutcome(array('body' => '', 'error' => 'curl error 7: refused',
            'httpCode' => 0, 'transient' => true, 'unreachable' => true))['state'],
            'a call that never reached the shop is not reported as failed');

        foreach (array('shopApiRequest', 'shopApiRequestParallel') as $sender) {
            $body = self::senderBody($sender);
            self::assertStringContainsString('shopApiOutcome(', $body,
                $sender . '() judges a shop answer on its own again');
            $guard   = strpos($body, "\$outcome['state'] === 'ok'");
            $success = strpos($body, "['ok']    = true;");
            self::assertNotFalse($guard, $sender . '() no longer asks the classifier whether the call succeeded');
            self::assertNotFalse($success, $sender . '() no longer reports success at all');
            self::assertLessThan($success, $guard,
                $sender . '() reports success before the body has been judged');
        }
    }

    /**
     * std_func.php has to finish loading.
     *
     * A return placed between the end of a function and the end of the
     * if (!function_exists(...)) wrapper around it sits at include scope, where it stops
     * the rest of the file being read: every function declared later silently ceases to
     * exist, with no error and nothing for php -l to see. lagerreguler()'s new return value
     * landed there first, which took out everything below it. saldikrypt() is declared
     * immediately after lagerreguler(), so it is the canary for that one line.
     */
    public function testStdFuncFinishesLoading(): void
    {
        ob_start();
        require_once self::STD_FUNC;
        ob_end_clean();
        foreach (array('sync_shop_vare', 'shopSyncFailed', 'shopSyncAlert', 'lagerreguler', 'saldikrypt') as $fn) {
            self::assertTrue(function_exists($fn),
                "$fn() is undefined, so including std_func.php stopped before the end of the file");
        }
    }

    // ------------------------------------------------------- telling failure from idle

    /** @return array<string, array{string, bool}> */
    public static function syncResults(): array
    {
        return array(
            'a clean run'                  => array('OK', false),
            'no webshop configured'        => array('no api', false),
            'the item is not stock-kept'   => array('no stock', false),
            'one shop refused'             => array('sync errors: 1', true),
            'three shops refused'          => array('sync errors: 3', true),
        );
    }

    /**
     * An account with no webshop, or an item that is not stock-managed, is not an error.
     *
     * Reporting those would put a warning on every receipt in most installations, so the
     * distinction is what makes the new message tolerable.
     */
    #[DataProvider('syncResults')]
    public function testOnlyARefusalCountsAsAFailure(string $result, bool $expected): void
    {
        self::assertSame($expected, $this->shopSyncFailed($result),
            "sync_shop_vare() answering '$result' must " . ($expected ? '' : 'not ') . 'count as a failure');
    }

    /** Evaluates the real shopSyncFailed() body rather than restating its rule. */
    private function shopSyncFailed(string $result): bool
    {
        static $fn = null;
        if ($fn === null) {
            $src = self::source(self::STD_FUNC);
            $at = strpos($src, 'function shopSyncFailed($result) {');
            self::assertNotFalse($at, 'shopSyncFailed() is gone, so callers cannot tell a failure apart');
            $open = strpos($src, '{', $at);
            $end = strpos($src, "\n\t}", $open);
            self::assertNotFalse($end, 'shopSyncFailed() no longer closes where expected');
            $body = substr($src, $open + 1, $end - $open - 1);
            $fn = eval('return function ($result) {' . $body . '};');
        }
        return (bool)$fn($result);
    }

    // ------------------------------------------------------------- the call sites

    /** @return array<string, array{string, string}> */
    public static function callSites(): array
    {
        return array(
            'the goods receipt'   => array(self::MODTAG, 'kreditor/modtag.php'),
            'the product card'    => array(self::VAREKORT, 'lager/varekort.php'),
            'invoicing and api'   => array(self::ORDREFUNC, 'includes/ordrefunc.php'),
        );
    }

    /**
     * No caller may throw the answer away.
     *
     * This is the defect itself: all four call sites used to invoke sync_shop_vare() as a
     * bare statement, so a shop refusing every update for a week left no trace anywhere.
     *
     * A caller may now mark its lines and send once at the end instead of sending per line, so
     * on those it is shopSyncFlush()'s answer that has to be kept. shopSyncMark() returns
     * nothing and sends nothing, so it is the one call allowed to stand on its own.
     */
    #[DataProvider('callSites')]
    public function testEveryCallerKeepsTheSyncResult(string $path, string $label): void
    {
        $src = self::source($path);
        self::assertMatchesRegularExpression(
            '/=\s*(?:sync_shop_vare|lagerreguler|shopSyncFlush)\s*\(/', $src,
            "$label does not keep what the sync answered");
        self::assertStringContainsString('shopSyncFailed(', $src,
            "$label never asks whether the sync failed");
        // Anchored per line: a lookbehind placed before the newline tests the end of the
        // previous line instead, which let a bare call through while this looked strict.
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*sync_shop_vare\s*\(/m', $src,
            "$label still calls sync_shop_vare() as a bare statement");
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*lagerreguler\s*\(/m', $src,
            "$label still calls lagerreguler() as a bare statement");
        self::assertDoesNotMatchRegularExpression(
            '/^[ \t]*shopSyncFlush\s*\(/m', $src,
            "$label sends the document's updates without keeping the answer");
    }

    /**
     * A caller that flushes has to mark something first.
     *
     * Flushing an empty list answers OK, so a caller that stopped marking its lines would sync
     * nothing at all and report success - the same silence this ticket is about, arrived at from
     * the other side. A mutation run found this one: removing the mark from the goods receipt
     * left every test green.
     */
    public function testACallerThatFlushesMarksItsLinesFirst(): void
    {
        foreach (array(
            array(self::MODTAG, 'kreditor/modtag.php'),
            array(self::ORDREFUNC, 'includes/ordrefunc.php'),
        ) as list($path, $label)) {
            $src = self::source($path);
            self::assertStringContainsString('shopSyncFlush(', $src, "$label no longer sends anything");
            self::assertStringContainsString('shopSyncMark(', $src,
                "$label flushes an empty list, so it syncs nothing and says it went fine");
        }
        // Only the receipt can be checked by position: its mark is in the line loop and its
        // flush is below it. In ordrefunc.php the order on the page is the other way round -
        // levering() holds the flush and is declared above linjeopdat(), which does the marking
        // and is called from inside it - so the order that matters there is pinned by
        // testTheSaleMarksItsLinesAndSendsOnce() instead.
        $receipt = self::source(self::MODTAG);
        self::assertLessThan(strpos($receipt, 'shopSyncFlush('), strpos($receipt, 'shopSyncMark('),
            'the receipt flushes before it marks, so a line is sent a receipt late');
    }

    /** A refusal has to leave a trace an operator can find afterwards. */
    #[DataProvider('callSites')]
    public function testEveryCallerLeavesATrace(string $path, string $label): void
    {
        self::assertStringContainsString('error_log(', self::source($path),
            "$label reports a refused stock update nowhere");
    }

    /**
     * The pages with a user in front of them say so on screen, at every exit.
     *
     * The count is pinned rather than just "mentions it somewhere", because varekort.php
     * leaves by three different routes and each has to carry the message itself: the
     * stock-change confirmation redirects straight away, an assembled item hands over to
     * vareproduktion.php and exits, and the ordinary save carries on rendering. Losing one of
     * them is the whole defect again on that route.
     *
     * The third one was found by CodeRabbit on PR #809: a variant refusal sets the flag while
     * the variants are saved, and the assembled-item branch then exits before the check at the
     * end of the block, so the refusal reached the log and nothing else.
     */
    public function testThePagesWithAUserShowTheMessageAtEveryExit(): void
    {
        foreach (array(
            array(self::MODTAG, 'kreditor/modtag.php', 1),
            array(self::VAREKORT, 'lager/varekort.php', 3),
            // SST-876 step 4, the sale. levering.php has one exit that has committed by then;
            // massefakt.php has two, because it invoices ten orders and then hands over to
            // itself for the next ten, and anything printed after that refresh is never written.
            array(self::LEVERING, 'debitor/levering.php', 1),
            array(self::MASSEFAKT, 'debitor/massefakt.php', 2),
        ) as list($path, $label, $expected)) {
            $src = self::source($path);
            $says = substr_count($src, 'shopSyncAlert(') + substr_count($src, 'shopSyncAlertFailedItems(');
            self::assertSame($expected, $says,
                "$label should tell the user at $expected place(s) and does not");
        }
    }

    /**
     * The assembled-item route warns before it hands over, not after.
     *
     * That branch prints a meta refresh and exits, so the warning has to be printed above the
     * refresh: anything after it is never written at all. Verified in a browser against the
     * real output - the dialog appears and the hand-over to vareproduktion.php follows once it
     * is dismissed.
     */
    public function testTheAssembledItemRouteWarnsBeforeItLeaves(): void
    {
        $src = self::source(self::VAREKORT);

        $at = strpos($src, 'URL=vareproduktion.php');
        self::assertNotFalse($at, 'the assembled-item hand-over is gone from the product card');
        // The branch starts at the nearest preceding samlevare test.
        $branchAt = strrpos(substr($src, 0, $at), 'if ($samlevare) {');
        self::assertNotFalse($branchAt, 'the hand-over no longer sits in an $samlevare branch');

        // Everything between the branch opening and the refresh - so a call moved below the
        // refresh, where it would never be written, does not satisfy this.
        $branch = substr($src, $branchAt, $at - $branchAt);
        self::assertStringContainsString('shopSyncAlert(', $branch,
            'the assembled-item route exits without telling the user a webshop refused the figure');
        self::assertStringContainsString('error_log(', $branch,
            'the assembled-item route leaves no trace of the refusal in the log either');
        // Still conditional: an assembled item that synced fine must not be warned about.
        self::assertStringContainsString('$shopSyncFailed', $branch,
            'the assembled-item route warns on every save, not only when a shop refused');
    }

    /**
     * Every warning has to name an item.
     *
     * Found in review. The product card leaves by three routes and all of them carry the
     * message, but the stock-change confirmation route passed $varenr, which still holds the
     * NULL from the top of the file there - it is only read from the post further down - so
     * that one screen said "...i webshoppen for:" and stopped. The Gem route was fine because
     * by then it has been read.
     *
     * Rather than pin the two call sites as they are, this checks the property that was
     * violated: whatever each call passes must have been given a value before that point, and
     * the NULL initialisation at the top of the file does not count as one.
     */
    public function testEveryWarningNamesAnItem(): void
    {
        $src = self::source(self::VAREKORT);
        self::assertNotFalse(preg_match_all(
            '/shopSyncAlert\(\s*(?:\(string\)\s*)?(\$[A-Za-z_][A-Za-z0-9_]*)/',
            $src, $hits, PREG_OFFSET_CAPTURE | PREG_SET_ORDER));
        self::assertCount(3, $hits,
            'the product card should warn at all three of its exits');

        foreach ($hits as $hit) {
            [$var, ] = $hit[1];
            $callAt = $hit[0][1];
            self::assertTrue(self::assignedBefore($src, $var, $callAt),
                "the warning is passed $var, which has no value yet at that point, so it names no item");
        }

        // The confirmation route takes the number off a row that is fetched there anyway, so
        // that query has to actually select it - otherwise the variable exists and is empty,
        // which the check above cannot see.
        $at = strpos($src, '$stockChangeItemNo = ');
        self::assertNotFalse($at, 'the confirmation route no longer reads the item number');
        self::assertMatchesRegularExpression(
            '/select\s+[^"]*\bvarenr\b[^"]*from varer where id=\$id/',
            substr($src, max(0, $at - 500), 500),
            'the row fetched on that route no longer selects varenr, so the warning names nothing');
    }

    /**
     * Does $var get a real value - not just the file's NULL initialiser - before $before?
     *
     * Only an assignment operator counts. Searching for "$var =" as text also matched
     * "$var ==" and "$var ===", so a comparison anywhere earlier in the file satisfied this
     * and hid the very regression it exists to catch (CodeRabbit, PR #809). The pattern
     * allows the compound forms, which do assign, and refuses ==, ===, !=, <= and >=. It
     * also no longer depends on a space before the operator.
     */
    private static function assignedBefore(string $src, string $var, int $before): bool
    {
        $pattern = '/' . preg_quote($var, '/') . '\s*(?:\.|\+|-|\*|\/|%|\?\?)?=(?!=)/';
        if (!preg_match_all($pattern, $src, $hits, PREG_OFFSET_CAPTURE)) {
            return false;
        }
        foreach ($hits[0] as $hit) {
            $at = $hit[1];
            if ($at >= $before) {
                continue;
            }
            $semi = strpos($src, ';', $at);
            $statement = $semi === false ? '' : substr($src, $at, $semi - $at);
            // "$varenr = $variant = $vis_kost = NULL;" assigns nothing usable.
            if ($statement !== '' && !preg_match('/=\s*NULL\s*$/i', $statement)) {
                return true;
            }
        }
        return false;
    }

    /**
     * That one sentence is translated and cannot break out of the markup around it.
     *
     * It carries item numbers, which are free text, and it is printed inside an onLoad
     * attribute wrapping a JavaScript call - two layers that both need escaping.
     */
    public function testTheMessageIsTranslatedAndEscaped(): void
    {
        $src = self::source(self::STD_FUNC);
        $at = strpos($src, 'function shopSyncAlert(');
        self::assertNotFalse($at, 'the shared alert is gone');
        $body = substr($src, $at, 700);
        self::assertMatchesRegularExpression("/findtekst\('5442\|/", $body,
            'the message is not read through findtekst(), so it cannot be translated');
        self::assertStringContainsString('jsString(', $body,
            'the item numbers are not escaped for the JavaScript string');
        self::assertStringContainsString('htmlspecialchars(', $body,
            'the JavaScript literal is not escaped for the HTML attribute it sits in');
    }

    /**
     * An item number cannot break out of the markup the warning is printed into.
     *
     * It is printed as a BODY onLoad attribute wrapping a JavaScript call, and item numbers
     * are free text. Checked against the real output in a browser with the item number
     * DIB038BW"</script><x>&' - it arrived as literal text in the dialog. Two encoders do
     * that: jsString()'s JSON_HEX_TAG and JSON_HEX_QUOT turn <, > and " into <, >
     * and ", and htmlspecialchars() then protects the attribute. Both are load-bearing,
     * and JSON_HEX_TAG is pinned where it lives because it is in a different file.
     */
    public function testAnItemNumberCannotBreakOutOfTheWarning(): void
    {
        $jsString = file_get_contents(__DIR__ . '/../../../includes/stdFunc/jsString.php');
        self::assertNotFalse($jsString, 'could not read includes/stdFunc/jsString.php');
        self::assertStringContainsString('JSON_HEX_TAG', $jsString,
            'jsString() no longer encodes < and >, so an item number can close the element it sits in');
        self::assertStringContainsString('JSON_HEX_QUOT', $jsString,
            'jsString() no longer encodes the double quote the attribute is delimited with');
    }

    /** A shop refusing the update must never undo a receipt that is otherwise correct. */
    public function testARefusalDoesNotRollBackTheReceipt(): void
    {
        $src = self::source(self::MODTAG);
        $commit = strpos($src, 'transaktion("commit")');
        self::assertNotFalse($commit, 'modtag.php no longer commits where expected');
        $report = strpos($src, '$shopSyncItems = implode(');
        self::assertNotFalse($report, 'the receipt no longer reports failed shops');
        self::assertGreaterThan($commit, $report,
            'the warning is produced before the commit, so it can still be rolled back');
        // Everything from the reporting block to the end of the file: the goods are already
        // committed there, so nothing in it may undo them.
        self::assertStringNotContainsString('rollback', substr($src, $report),
            'a shop refusing the stock update rolls the goods receipt back');
    }

    // --------------------------------------------------------- what a variant publishes

    /** A variant sends a total, like the non-variant branch and the bulk run both do. */
    public function testAVariantPublishesATotalStock(): void
    {
        $branch = self::variantBranch();
        // Matched as an actual parameter, not just the words: the explaining comment beside
        // it mentions 'totalStock' too, which satisfied a plain substring check on its own.
        self::assertMatchesRegularExpression(
            "/'totalStock'\s*=>\s*\\\$variantTotalStock/", $branch,
            'a variant still publishes no total, so a shop keeping one figure per product never learns it');
        self::assertStringContainsString('sum(beholdning)', $branch,
            'the variant total is not summed across warehouses');
    }

    /** ...but never the parent sku, or one variant overwrites the whole product's stock. */
    public function testAVariantNeverFallsBackToTheParentSku(): void
    {
        $branch = self::variantBranch();
        self::assertStringNotContainsString("'itemNo'", $branch,
            "a variant must not send the parent itemNo - lager/shopStockIncludes/sync.php pins the same rule");
    }

    /** The per-warehouse figure must be the warehouse that was asked about. */
    public function testTheVariantStockIsReadForTheWarehouseItWasAskedAbout(): void
    {
        $branch = self::variantBranch();
        self::assertMatchesRegularExpression(
            "/from lagerstatus where variant_id='\\\$variant_id' and lager='\\\$lager'/", $branch,
            'the variant figure is read without a warehouse filter again, so an arbitrary row is published');
    }

    // ------------------------------------------------------------- the quieter defects

    /** An empty fiscal year must not turn into a silent "this item is not stock-kept". */
    public function testTheStockKeepingLookupCastsTheFiscalYear(): void
    {
        $block = self::syncShopVare();
        self::assertStringContainsString("AND fiscal_year = '\" . (int)\$regnaar . \"'", $block,
            'fiscal_year is interpolated raw again: an empty $regnaar makes this a syntax error '
            . "and the function then answers 'no stock' as if the item were not stock-managed");
    }

    /** The shop_id fallback that read a failed query's row is gone. */
    public function testTheDeadShopIdFallbackIsGone(): void
    {
        $block = self::syncShopVare();
        self::assertStringNotContainsString('is_integer($itemNo)', $block,
            "the elseif reading \$r['itemNo'] out of a failed query is back");
    }

    /** modtag.php compares one warehouse, not the whole array, and builds valid SQL. */
    public function testTheReceiptsWarehouseLookupIsSound(): void
    {
        $src = self::source(self::MODTAG);
        self::assertStringNotContainsString('($lager > 1)?', $src,
            'the warehouse lookup compares the whole $lager array against 1 again');
        self::assertStringNotContainsString("lager <= '1", $src,
            'the branch appending an unterminated SQL string literal is back');
    }

    // ------------------------------------------------------------------ the text

    /** The message exists in all three languages, or the user sees "Tekst nr: 5442". */
    public function testTheMessageIsTranslated(): void
    {
        $raw = file_get_contents(self::TEKSTER);
        self::assertNotFalse($raw);
        $row = null;
        foreach (explode("\n", $raw) as $line) {
            $f = explode("\t", rtrim($line, "\r"));
            if (isset($f[0]) && $f[0] === '5442') {
                $row = $f;
                break;
            }
        }
        self::assertNotNull($row, 'importfiler/tekster.csv has no row 5442');
        foreach (array(1 => 'Dansk', 2 => 'English', 3 => 'Norsk') as $col => $lang) {
            self::assertNotSame('', trim($row[$col] ?? ''), "row 5442 has no $lang text");
        }
    }

    // ------------------------------------------------- which endpoint counts as degraded

    /**
     * Runs a call against an address that refuses the connection at once.
     *
     * These two tests execute the real shopApiRequest(), which is the only way to see the
     * static $degraded map: nothing reads it out. 127.0.0.1 with a closed port answers
     * ECONNREFUSED immediately, so this costs milliseconds and needs no network. The php
     * error log is pointed at a temporary file for the duration, because every failure here
     * is deliberate and would otherwise be printed by the test run.
     *
     * @return array{ok: bool, skipped: bool, error: string}
     */
    private function callDeadEndpoint(string $url): array
    {
        $previous = ini_get('error_log');
        $sink = tempnam(sys_get_temp_dir(), 'shopsynctest');
        ini_set('error_log', $sink !== false ? $sink : '/dev/null');
        try {
            $res = shopApiRequest($url, array('update_stock' => '1'), null, array(
                'context'        => 'degradation test',
                'retries'        => 0,
                'connectTimeout' => 1,
                'timeout'        => 1,
            ));
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
            if ($sink !== false) {
                @unlink($sink);
            }
        }
        return $res;
    }

    /**
     * One dead adapter must not stop the shop next to it.
     *
     * box4, box5 and box6 are normally three adapter scripts in one hosting account, so they
     * differ only in the path. Keying the degradation on scheme and host alone meant the first
     * unreachable adapter skipped the other two for the rest of the request - every remaining
     * call reported 'endpoint degraded earlier in this request' without being tried once.
     * That is Havemoebelland's complaint with a different cause.
     */
    public function testOneDeadAdapterDoesNotDegradeAnotherOnTheSameHost(): void
    {
        $first = $this->callDeadEndpoint('http://127.0.0.1:9/adapter-one.php');
        self::assertFalse($first['ok'], 'a closed port answered successfully');
        self::assertFalse($first['skipped'], 'the first call to an endpoint cannot be a skip');

        $second = $this->callDeadEndpoint('http://127.0.0.1:9/adapter-two.php');
        self::assertFalse($second['skipped'],
            'the second webshop was skipped because the first one is dead: the degradation key '
            . 'has lost the path again, so adapters sharing a host share a failure state');
        self::assertStringNotContainsString('degraded', $second['error'],
            'the second webshop was refused on the first one\'s failure');
    }

    /** The same adapter twice still degrades, or the skip saves nobody a timeout. */
    public function testTheSameDeadAdapterIsSkippedTheSecondTime(): void
    {
        $first = $this->callDeadEndpoint('http://127.0.0.1:1/adapter-one.php');
        self::assertFalse($first['skipped'], 'the first call to an endpoint cannot be a skip');

        $second = $this->callDeadEndpoint('http://127.0.0.1:1/adapter-one.php');
        self::assertTrue($second['skipped'],
            'an endpoint that proved unreachable is being called again: every item in the '
            . 'request now pays its connect timeout');
        self::assertStringContainsString('degraded', $second['error'],
            'the skip no longer says why it skipped');
    }

    // ------------------------------------------------ how many calls one item is worth

    /**
     * Every shop is called in one round, not one after the other.
     *
     * Three shops answered in turn cost three times one shop's latency, and an ordinary item is
     * three sends, so an item was eight serial calls. That is what used the 30 second budget up:
     * invoice 230508 made 108 calls on its first line and skipped everything after it.
     */
    public function testEveryShopIsCalledInOneRoundRatherThanInTurn(): void
    {
        $block = self::syncShopVare();
        self::assertStringNotContainsString('shopApiRequest($endpoint', $block,
            'sync_shop_vare() is calling the shops one at a time again');
        self::assertStringNotContainsString('foreach (array($api_fil, $api_fil2, $api_fil3) as $endpoint)', $block,
            'a per-endpoint send loop is back in sync_shop_vare()');
        self::assertStringContainsString('shopApiRequestParallel(', $block,
            'the sends no longer go through the parallel sender');
    }

    /**
     * A stock change sends the stock calls only.
     *
     * Sales and regulations use stock-only mode, so update_price, sku
     * and the per-set costPrice call have nothing to say. Eight calls become three. The product
     * card keeps the default, because Gem is where a price actually changes.
     */
    public function testAStockChangeDoesNotResendPricesAndSkus(): void
    {
        $block = self::syncShopVare();
        foreach (array('update_price', 'sku vare_id', 'costPrice part') as $send) {
            $at = strpos($block, '"sync_shop_vare ' . $send);
            self::assertNotFalse($at, "the $send send has moved");
            $before = substr($block, 0, $at);
            $guard = strrpos($before, "if (\$mode !== 'stock')");
            self::assertNotFalse($guard, "the $send send is made even when only stock changed");
            // The guard has to be the nearest thing above the send, not one from further up.
            self::assertLessThan(200, strlen($before) - $guard,
                "the $send send is no longer the one that guard protects");
        }
        $stock = strpos($block, '"sync_shop_vare update_stock vare_id');
        self::assertNotFalse($stock, 'the stock send has moved');
        $before = substr($block, 0, $stock);
        self::assertGreaterThan(200, strlen($before) - (int)strrpos($before, "if (\$mode !== 'stock')"),
            'the stock send is now skipped on a stock change, which leaves nothing to send at all');
    }

    /**
     * The prices are still read in stock mode, because the stock send is built from them.
     *
     * Only the sending is skipped. Skipping the query would send the stock call with empty
     * prices, which is a worse bug than the one being fixed.
     */
    public function testTheStockSendStillCarriesThePricesItAlwaysDid(): void
    {
        $block = self::syncShopVare();
        $query = strpos($block, "'update_price' => \$shop_id,");
        $guard = strpos($block, "if (\$mode !== 'stock')");
        self::assertNotFalse($query, 'the price parameters are gone');
        self::assertNotFalse($guard, 'the mode no longer decides anything');
        self::assertLessThan($guard, $query,
            'the price query is now inside the mode guard, so a stock change sends empty prices');
        foreach (array('salesPrice', 'costPrice', 'retailPrice', 'barcode') as $param) {
            self::assertStringContainsString("'" . $param . "'", $block,
                "the stock send lost its $param parameter");
        }
    }

    /** A set shared by several lines of one document is sent once, with its final figure. */
    public function testASetSharedBySeveralLinesIsSentOnce(): void
    {
        $block = self::syncShopVare();
        self::assertStringContainsString('&shopSyncSentSets()', $block,
            'nothing remembers which sets this document has already sent');
        // Shared rather than static: ten orders in one mass invoicing request are ten documents
        // with ten sets of figures, so the guard has to be cleared between them.
        self::assertStringNotContainsString('static $partsSent', $block,
            'the guard is a static again, so the second order in a request skips its sets');
        self::assertStringContainsString('shopSyncFlushRunning()', $block,
            'a direct sync can inherit the sets a flush in the same request already sent');
        self::assertStringContainsString('isset($partsSent[$partKey])', $block,
            'a set shared by two lines is sent once per line again');
        $guard = strpos($block, 'isset($partsSent[$partKey])');
        $send  = strpos($block, '"sync_shop_vare update_stock part');
        self::assertLessThan($send, $guard, 'the set is sent before anything checks whether it already was');
    }

    /** A send that never reached the shop leaves the item on the queue for the next request. */
    public function testAnUndeliveredUpdateIsQueued(): void
    {
        $block = self::syncShopVare();
        self::assertStringContainsString('shopSyncQueueAdd(', $block,
            'a stock update that could not be delivered is dropped again');
        self::assertStringContainsString('shopSyncWorthRetrying($res)', $block,
            'every failure is queued, including the ones no retry can fix');
        $guard = strpos($block, 'shopSyncWorthRetrying($res)');
        $queue = strpos($block, 'shopSyncQueueAdd(');
        self::assertLessThan($queue, $guard, 'the item is queued before anything asks whether that helps');
        self::assertStringContainsString('shopSyncNoteFailedItem(', $block,
            'the item number of a failed send is no longer collected for the screen');
    }

    // ------------------------------------------------------- the sale, SST-876 step 4

    /**
     * The sale sends once, when every line is posted.
     *
     * linjeopdat() used to sync the line it had just posted, so an order of five lines was five
     * syncs and every set those lines belong to was sent once per line. It marks now, and
     * levering() sends when the loop is done - which is also the only moment the figures are
     * final.
     */
    public function testTheSaleMarksItsLinesAndSendsOnce(): void
    {
        $src = self::source(self::ORDREFUNC);
        self::assertStringContainsString('shopSyncMark($vare_id, $variant_id, $lager', $src,
            'linjeopdat() no longer marks the line it posted');
        self::assertSame(1, substr_count($src, 'shopSyncFlush('),
            'the sale sends its updates somewhere other than once at the end of levering()');
        $mark  = strpos($src, 'shopSyncMark($vare_id');
        $flush = strpos($src, 'shopSyncFlush(');
        self::assertNotFalse($mark);
        self::assertNotFalse($flush);
        self::assertLessThan($mark, $flush,
            'levering() sends after linjeopdat() in the file, so the flush is inside the line loop');
        // The name still appears in the file's history header, so this looks at the function.
        $body = substr($src, $mark);
        $end = strpos($body, '# endfunc linjeopdat');
        self::assertNotFalse($end, 'linjeopdat() no longer ends where it says it does');
        self::assertStringNotContainsString('sync_shop_vare(', substr($body, 0, $end),
            'linjeopdat() still sends the line it posted, outside the flush');
    }

    /**
     * The paths with nobody watching stay quiet.
     *
     * The rest api, the webshop order import and mysale all reach levering(), and a popup in a
     * machine-to-machine call is at best ignored and at worst breaks the response. Their
     * failures are in the php error log and on the queue, which is what step 2 is for.
     */
    public function testThePathsWithNoUserDoNotTryToAlert(): void
    {
        foreach (array(
            'api/rest_api.php', 'api/hent_ordrer.php', 'mysale/api.php',
        ) as $path) {
            $full = __DIR__ . '/../../../' . $path;
            if (!is_file($full)) {
                continue;
            }
            $src = self::source($full);
            self::assertStringNotContainsString('shopSyncAlert', $src,
                "$path would print a javascript alert into a machine-to-machine answer");
        }
        // levering() is shared by both kinds of caller, so it must not alert on its own behalf.
        self::assertStringNotContainsString('shopSyncAlert', self::source(self::ORDREFUNC),
            'levering() alerts by itself, so the rest api prints a popup too');
    }
}
