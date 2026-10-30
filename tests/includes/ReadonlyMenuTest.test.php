<?php
// tests/includes/ReadonlyMenuTest.test.php
//
// MB-62: a "View only" (2) user sees Items in the menus (it already opens the pages), and the
// pages offer nothing that writes: the item list has no Ny button, the card only read controls,
// the two renumbering pages refuse before they write, and the stock move and the bill-of-materials
// drag endpoint refuse on the server.
//
// The checks themselves sit in support/readonlyMenuChecks.php, so the same list runs from the
// command line while a page is being edited.
//
// History:
// 20261030 LOE MB-62: moved here from tests/test_readonly_menu.php, where phpunit.xml never ran it.
//                 The file name carries the class name, or PHPUnit 11 looks for a class called
//                 "readonlyMenu" and reports "Class readonlyMenu cannot be found".

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/support/readonlyMenuChecks.php';

final class ReadonlyMenuTest extends TestCase
{
    public function testTheMenusOfferItemsToAReadOnlyUser(): void
    {
        list($checks, $failures) = readonly_menu_checks();

        $this->assertGreaterThanOrEqual(30, $checks, 'the check list ran');
        $this->assertSame(array(), $failures, "failed checks:\n - " . implode("\n - ", $failures));
    }
}
