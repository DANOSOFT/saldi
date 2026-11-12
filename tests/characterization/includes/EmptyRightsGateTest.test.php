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
// 20261110 LOE SST-967 The order page is pinned to the case, and the way back, the export and the pin are pinned. Every order selector in a request is checked against the case. The history page reads its ids as numbers, and the gate pins its save and delete to the proved customer. A valid id= no longer lets another id= through on the POST. A history row named on the POST is checked, and the copy and the line ids are bound to the admitted case. The credit reference is bound to the case, and every position assertion proves its guard exists first. The history form row and the price-list handler are scoped as well. The posted line and credit ids are stored as integers, and the copy binding is proven to be conditioned.

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

        // The statement must be exactly the module test and the character test, with no rights-presence term.
        self::assertMatchesRegularExpression(
            '/^\$no_module_right = \(\$modulnr\) && \(substr\(\(string\)\$rettigheder, \(int\)\$modulnr, 1\) < \'1\'\)$/',
            $line,
            'the gate is the module test plus the character test, and an empty rights string is read as no rights'
        );
        // '0' is what the Mit Salg session rows carry. The gate must NOT special-case it: a session row
        // with '0' would then reach every module page, which is a shop account reading the ledger. The one
        // page those sessions need opens itself instead (lager/labelprint.php, pinned below).
        self::assertStringNotContainsString("!== '0'", $line, 'the gate has no Mit Salg exception of its own');
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
        $block = substr($code, (int)$start, 6000);

        self::assertStringContainsString('$sag_workflow_page', $block, 'the page declares that it belongs to the workflow');
        // The check runs in the gate, because that is where the tenant database is open.
        self::assertStringContainsString('select konto_id from sager where id =', $block, 'the case is resolved');
        self::assertStringContainsString("\$_GET['konto_id'] = \$wf_customer;", $block,
            'and the customer comes from the case, not from the request');
        self::assertStringContainsString('select sag_id from ordrer where id =', $block,
            'a link to an order is traced back to its case');
        // The order decides the case: a request that names a different case must not borrow it.
        self::assertStringContainsString('$wf_order_sag = $wf_row ? (int) $wf_row[\'sag_id\'] : 0;', $block,
            'the order\'s own case is read');
        self::assertStringContainsString(
            '$wf_sag = (!$wf_order_sag || ($wf_sag && $wf_sag !== $wf_order_sag)) ? 0 : $wf_order_sag;',
            $block,
            'and a case that disagrees, or an order with no case, drops the exception'
        );
        self::assertStringContainsString('unset($wf_row, $wf_sag, $wf_order, $wf_order_sag, $wf_customer, $wf_selectors, $wf_source, $wf_selector, $wf_hist_id);', $block,
            'the temporaries are cleaned up with the others');
        // Every order the request names is checked, not only the one that decided the case: the page takes its
        // id from id or ordre_id on the GET or on the POST, and a valid id= with another id= in the body was a
        // way to reach the case's page and write on an order outside it.
        self::assertStringContainsString("foreach (array(array(\$_GET, 'id'), array(\$_POST, 'id'), array(\$_GET, 'ordre_id'), array(\$_POST, 'ordre_id')) as \$wf_source)", $block,
            'every order selector in the request is collected');
        self::assertStringContainsString('$wf_selectors[(int) $wf_source[0][$wf_source[1]]] = true;', $block,
            'and each one that is a number is checked');
        // The page reassigns its id from ordre_id and from kundeordnr as well as from id, and it writes to
        // that id, so every selector in the request has to name an order of the same case.
        self::assertStringContainsString("array(\$_GET, 'ordre_id')", $block, 'an ordre_id in the address is resolved too');
        self::assertStringContainsString("array(\$_POST, 'ordre_id')", $block, 'and one in the POST');
        self::assertStringContainsString('select max(id) as id from ordrer where kundeordnr =', $block,
            'kundeordnr is not an id, so the order it selects is looked up');
        self::assertStringContainsString('foreach (array_keys($wf_selectors) as $wf_selector) {', $block,
            'and each of them is checked against the admitted case');
        self::assertStringContainsString("if (!\$wf_row || (int) \$wf_row['sag_id'] !== \$wf_sag) {", $block,
            'an order of another case drops the exception');
        // debitor/ordre.php saves with $_POST['konto_id'], so the GET alone is not enough.
        self::assertStringContainsString("\$_POST['konto_id'] = \$wf_customer;", $block,
            'the save path is pinned to the case customer too');
        // The GET pin is only for a NEW order: with an order id in the request the order owns its customer,
        // and pinning the GET value makes the page take its "skift kontonr" branch and rewrite that order.
        self::assertStringContainsString('if (!$wf_order) {', $block, 'the GET pin is limited to a new order');
        self::assertMatchesRegularExpression(
            '/if \(!\$wf_order\) \{\s*\$_GET\[\'konto_id\'\] = \$wf_customer;\s*\}\s*\$_POST\[\'konto_id\'\] = \$wf_customer;/',
            $block,
            'the GET pin is alone in the condition and the POST pin follows outside it'
        );
        // The page needs to know which case admitted it, or it cannot check the orders it loads.
        self::assertStringContainsString('$sag_workflow_case_id = $wf_sag;', $block,
            'the admitted case is exported for the page');
        self::assertStringContainsString('select id from sager where konto_id =', $block,
            'a customer history needs a case for that customer');
        // The history page saves on $_POST['id'] and deletes a history row by its id, so both are pinned to
        // the customer the request proved, and only when the craft right is what admits the page.
        self::assertStringContainsString("\$_POST['id'] = \$wf_konto;", $block,
            'the customer the save path files on is pinned too');
        self::assertStringContainsString('select id from historik where id =', $block,
            'and a history row named by the request must belong to that customer');
        self::assertStringContainsString("array(\$_POST, 'historik_id')", $block, 'the POST selector is checked as well');
        $histScoped = (string)@file_get_contents(__DIR__ . '/../../../debitor/historikkort.php');
        self::assertStringContainsString("delete from historik where id = \$historik_id and konto_id = '\$id'", $histScoped,
            'the delete is scoped to the customer');
        self::assertStringContainsString("WHERE id = {\$inputs['historik_id']} AND konto_id = {\$inputs['id']}", $histScoped,
            'and so is the update');
        self::assertStringContainsString("substr((string)\$sag_rettigheder, 2, 1)", $block,
            'the right it uses is the one the Sager pages ask for');

        self::assertStringContainsString('if ($no_module_right && !$sag_workflow_allowed && !$mysale_label_ok)',
            $code, 'the refusal asks for all three: no module right, no workflow request, no Mit Salg proof');

        // The order page: the case must exist and the customer comes from it, not from the request.
        $ordre = @file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
        self::assertNotFalse($ordre, 'cannot read debitor/ordre.php');
        self::assertStringContainsString("\$sag_workflow_page = 'sag';", (string)$ordre, 'the order page declares the workflow');
        self::assertStringContainsString('$sag_workflow_sag', (string)$ordre, 'and passes the case it was asked for');
        self::assertStringContainsString('$sag_workflow_order', (string)$ordre, 'and the order, for a link that only carries one');
        self::assertStringContainsString('$sag_workflow_konto', (string)$ordre, 'and the customer the address names, so the gate can check it');
        self::assertLessThan(strpos((string)$ordre, 'include("../includes/online.php");'),
            strpos((string)$ordre, "\$sag_workflow_page = 'sag';"),
            'the declaration is there before online.php reads it');

        // The history page: only a customer the company has a case for.
        $hist = @file_get_contents(__DIR__ . '/../../../debitor/historikkort.php');
        self::assertNotFalse($hist, 'cannot read debitor/historikkort.php');
        self::assertStringContainsString("\$sag_workflow_page = 'customer';", (string)$hist, 'the history page declares it too');
        self::assertStringContainsString('$sag_workflow_konto', (string)$hist, 'and passes the customer it was asked for');
        // The history queries compare konto_id with the id directly, so the page reads it as a number.
        self::assertStringContainsString("\$id = (int)\$_GET['konto_id'];",
            (string)$hist, 'the customer id the page uses is a number');
        self::assertStringContainsString("'id' => (int)(\$post['id'] ?? 0)", (string)$hist,
            'and so is the one the save path reads');
        self::assertStringContainsString("'historik_id' => (int)(\$post['historik_id'] ?? 0)", (string)$hist,
            'and the history row it updates');
    }

    /**
     * The Mit Salg label page opens itself, and only for that session.
     *
     * mysale/mylabel.php and mysale/mysale.php create their session row with rettigheder = '0', and a shop
     * account must not reach the ledger, so the exception belongs here rather than in the gate.
     *
     * @return void
     */
    public function testTheLabelPageOpensForTheMitSalgSessionOnly(): void
    {
        // The page only declares itself; the gate holds the proof, because it has the tenant connection.
        $label = @file_get_contents(__DIR__ . '/../../../lager/labelprint.php');
        self::assertNotFalse($label, 'cannot read lager/labelprint.php');
        self::assertStringContainsString('$mysale_label_page = true;', (string)$label, 'the label page declares itself');
        self::assertLessThan(
            strpos((string)$label, 'include("../includes/online.php");'),
            strpos((string)$label, '$mysale_label_page = true;'),
            'and does so before including online.php'
        );

        $code = self::onlineSource();
        $start = strpos($code, '$mysale_label_ok = false;');
        self::assertNotFalse($start, 'the gate proves the Mit Salg session');
        $cond = substr($code, (int)$start, 600);
        self::assertStringContainsString('$mysale_label_page', $cond, 'only for a page that asked for it');
        self::assertStringContainsString("(string)\$online_rights === '0'", $cond, 'with the placeholder rights');
        self::assertStringContainsString("\$brugernavn !== 'booking'", $cond, 'not the internal booking account');
        self::assertStringContainsString('select id from brugere where brugernavn =', $cond,
            'and only for an account with no brugere row, which is what a shop account is');
        self::assertStringContainsString('$mysale_label_ok = !$mysale_row;', $cond, 'the proof is the missing row');
        self::assertStringContainsString('if ($no_module_right && !$sag_workflow_allowed && !$mysale_label_ok)',
            $code, 'and the refusal honours it');
    }

    /**
     * The order page checks every order it loads or saves against the admitted case.
     *
     * The order id can arrive as id, ordre_id, kundeordnr, one of the tekst lookups or on the POST, and the
     * page reassigns it more than once, so the check runs before the first write that uses it and again
     * immediately before the two big writes.
     *
     * @return void
     */
    public function testTheOrderPageKeepsEveryOrderInsideItsCase(): void
    {
        $ordre = (string)@file_get_contents(__DIR__ . '/../../../debitor/ordre.php');
        self::assertNotFalse($ordre, 'cannot read debitor/ordre.php');

        self::assertStringContainsString('function sag_workflow_order_ok($order_id)', $ordre, 'there is one check');
        self::assertStringContainsString("select sag_id from ordrer where id = '", $ordre, 'it reads the order\'s case');
        self::assertStringContainsString('function sag_workflow_refuse()', $ordre, 'and one refusal');
        // A page-created order belongs to the case by construction and is never refused.
        self::assertStringContainsString('$sst967_created_order = (int) $id;', $ordre, 'the created order is remembered');
        self::assertStringContainsString('(int)$sst967_created_order === (int)$order_id', $ordre,
            'and is always allowed');

        self::assertSame(5, substr_count($ordre, 'if ($id && !sag_workflow_order_ok($id)) {'),
            'the check also runs in the two handlers that write from the POST id alone');

        // The page takes its order id from id or ordre_id, on the GET or on the POST, and an empty id= must not
        // hide an ordre_id, so the declaration reads the first one that is a number.
        self::assertStringContainsString("array(\$_GET, 'id'), array(\$_POST, 'id'), array(\$_GET, 'ordre_id'), array(\$_POST, 'ordre_id')",
            $ordre, 'every selector the page reads is handed to the gate');
        self::assertStringContainsString("is_numeric(\$sst967_selector[0][\$sst967_selector[1]] ?? '')", $ordre,
            'and an empty value cannot hide the next selector');
        $copyBind = strpos($ordre, "if (\$sag_workflow_case_id) {\n\t\$sag_id = (int)\$sag_workflow_case_id;");
        $createCall = strpos($ordre, '$id = opret_ordre(');
        self::assertNotFalse($copyBind, 'the page case is replaced by the admitted one');
        self::assertNotFalse($createCall, 'the create call is still there');
        self::assertLessThan($createCall, $copyBind, 'and the replacement happens before the order is created');
        self::assertStringContainsString("\$sag_id = (int)\$sag_workflow_case_id;", $ordre, 'the copy is bound to the admitted case');
        self::assertStringContainsString("select id from ordrelinjer where ordre_id = '\$id'", $ordre,
            'and a posted line id of another order is dropped');
        self::assertStringContainsString("\$linje_id[\$sst967_k] = (int) \$sst967_v;", $ordre,
            'the posted line id is stored as the integer it was checked as');
        self::assertStringContainsString("\$kred_linje_id[\$sst967_k] = (int) \$sst967_v;", $ordre,
            'and so is the posted credit-line reference');
        self::assertStringContainsString("select ordrelinjer.id from ordrelinjer, ordrer where ordrelinjer.ordre_id = ordrer.id", $ordre,
            'and a posted credit-line reference must be a line of the admitted case');
        // Clearing the tenant's price-list flags is not case work, so the handler needs the Ordre right.
        $plGuard = strpos($ordre, "substr((string)\$rettigheder, (int)\$modulnr, 1) < '1'");
        $plWrite = strpos($ordre, "UPDATE grupper SET box4 = ''");
        self::assertNotFalse($plGuard, 'the price-list handler has a rights check');
        self::assertNotFalse($plWrite, 'the price-list write is still there');
        self::assertLessThan($plWrite, $plGuard, 'and the check runs before it clears the flags');
        $histFile = (string)@file_get_contents(__DIR__ . '/../../../debitor/historikkort.php');
        self::assertStringContainsString("\$id = (int)\$id;", $histFile, 'updateOprettet() casts the id it writes with');
        self::assertStringContainsString("select * from historik where id = '\$historik_id' and konto_id = '\$id'", $histFile,
            'the row the form is filled from is scoped to the customer too');

        $firstWrite = strpos($ordre, 'if (isset($_GET[\'vis_lev_addr\']) && $id) {');
        $guard = strpos($ordre, 'if ($id && !sag_workflow_order_ok($id)) {');
        self::assertNotFalse($firstWrite, 'the first order write is still there');
        self::assertNotFalse($guard, 'the check itself is still there');
        self::assertLessThan($firstWrite, $guard, 'the check runs before the first write that uses the id');

        $pinWrite = strpos($ordre, 'update ordrer set konto_id=\'$konto_id\',kontonr=\'$kontonr\'');
        self::assertNotFalse($pinWrite, 'the customer rewrite is still there');
        $guardBeforePin = strpos($ordre, 'if ($id && !sag_workflow_order_ok($id)) {', (int)$firstWrite);
        self::assertNotFalse($guardBeforePin, 'the check before the customer rewrite is still there');
        self::assertLessThan($pinWrite, $guardBeforePin, 'the check runs again before the customer rewrite');

        // The delivery-address endpoint writes from the POST id before the page reaches any check of its own.
        self::assertStringContainsString('if (!sag_workflow_order_ok($clear_id)) {', $ordre,
            'the delivery-address endpoint checks the id in the body on its own');
        self::assertLessThan(
            strpos($ordre, "UPDATE ordrer SET lev_navn=''"),
            strpos($ordre, 'if (!sag_workflow_order_ok($clear_id)) {'),
            'and does so before it clears that address'
        );

        $saveWrite = strpos($ordre, 'update ordrer set kontonr=\'$kontonr\',kundeordnr=\'$kundeordnr\',firmanavn=\'$firmanavn\'');
        self::assertNotFalse($saveWrite, 'the save write is still there');
        $guardBeforeSave = strpos($ordre, 'if ($id && !sag_workflow_order_ok($id)) {', (int)$pinWrite);
        self::assertNotFalse($guardBeforeSave, 'the check before the save write is still there');
        self::assertLessThan($saveWrite, $guardBeforeSave, 'and once more before the save write');
    }

    /**
     * A refused page must not enter the navigation stack.
     *
     * online.php records the request in the nav stack before the rights gate runs, so without this the
     * refused address stays on the stack and a back link on a later page returns to it.
     *
     * @return void
     */
    public function testARefusedPageNeverReachesTheNavStack(): void
    {
        // The gate sits above the user-settings block now, so a refused page exits before nav_push() runs.
        $code = self::onlineSource();
        $refusal = strpos($code, 'if ($no_module_right && !$sag_workflow_allowed && !$mysale_label_ok)');
        $push = strpos($code, 'nav_push(null,');
        self::assertNotFalse($refusal, 'the refusal is there');
        self::assertNotFalse($push, 'the page is still pushed onto the nav stack');
        self::assertLessThan($push, $refusal, 'the refusal runs before the push, so a refused page is never recorded');
        self::assertStringContainsString('exit;', substr($code, (int)$refusal, 2000), 'and it ends the request');
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
