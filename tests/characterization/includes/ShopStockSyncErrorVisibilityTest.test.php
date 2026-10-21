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
final class ShopStockSyncErrorVisibilityTest extends TestCase
{
    private const STD_FUNC = __DIR__ . '/../../../includes/std_func.php';
    private const MODTAG = __DIR__ . '/../../../kreditor/modtag.php';
    private const VAREKORT = __DIR__ . '/../../../lager/varekort.php';
    private const ORDREFUNC = __DIR__ . '/../../../includes/ordrefunc.php';
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

    /** shopApiRequest() has to consult the body, not just the status line. */
    public function testTheRequestPathChecksTheBodyBeforeReportingSuccess(): void
    {
        $src = self::source(__DIR__ . '/../../../includes/stdFunc/shopApiRequest.php');
        $at = strpos($src, '$call[\'httpCode\'] < 400) {');
        self::assertNotFalse($at, 'the success branch of shopApiRequest() has moved');
        $tail = substr($src, $at, 1200);
        self::assertStringContainsString('shopApiResponseRejected($call[\'body\'])', $tail,
            'a 2xx is accepted without looking at the body again');
        self::assertLessThan(strpos($tail, '$result[\'ok\']    = true;'),
            strpos($tail, 'shopApiResponseRejected'),
            'the body is checked only after the call is already called a success');
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
     */
    #[DataProvider('callSites')]
    public function testEveryCallerKeepsTheSyncResult(string $path, string $label): void
    {
        $src = self::source($path);
        self::assertMatchesRegularExpression(
            '/=\s*(?:sync_shop_vare|lagerreguler)\s*\(/', $src,
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
        ) as list($path, $label, $expected)) {
            self::assertSame($expected, substr_count(self::source($path), 'shopSyncAlert('),
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
}
