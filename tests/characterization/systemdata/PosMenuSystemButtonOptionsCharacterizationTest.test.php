<?php
// --- tests/characterization/systemdata/PosMenuSystemButtonOptionsCharacterizationTest.test.php --- 20261005 LOE ---
// Copyright (c) 2026 Danosoft ApS
// Licensed under the GNU General Public License, version 2 or later.
// 20261005 LOE SST-849 Pin that the POS menu editor offers each system-button value exactly once.
//
// CHARACTERIZATION suite for the option list in systemdata/posmenuer_includes/systemButtons.php - the
// dropdown an administrator picks a "Systemknap" from (funktion = 6, stored in pos_buttons.vare_id).
//
// The list offered "Sæt" twice: value 33, which the till renders as its Sæt button, and value 47, which
// the till renders as the terminal reconciliation ("Afstemning", includes/posmenufunc.php) and which
// navigates to payments/lane3000_afstemning.php. Choosing the second "Sæt" therefore saved a button that
// reconciles a payment terminal, and because both entries carried value 47 the editor could not show the
// administrator which one had been stored either.
//
// The invariant is that a value identifies exactly one button: the till maps value -> behaviour, so a
// value offered twice is a button whose behaviour depends on which duplicate was picked. The same list
// also existed a second time in the never-called input() function of systemdata/posmenuer.php, which is
// why one test here insists the list is defined in one place only.
//
// This suite reads the sources as text, so it needs no database and no tenant.

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PosMenuSystemButtonOptionsCharacterizationTest extends TestCase
{
    private const SYSTEM_BUTTONS = 'systemdata/posmenuer_includes/systemButtons.php';

    private static function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function source(string $relativePath): string
    {
        $path = self::repoRoot() . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The literal options the dropdown offers, as value => the labels printed for that value.
     *
     * Options whose value comes from a variable (the currency buttons) are not literals and are
     * deliberately not part of this map - they cannot collide with a fixed system-button value.
     *
     * @return array<string, string[]>
     */
    private static function literalOptions(): array
    {
        preg_match_all(
            "/<OPTION value='(\d+)'[^>]*>([^<]*)<\/OPTION>/i",
            self::source(self::SYSTEM_BUTTONS),
            $matches,
            PREG_SET_ORDER
        );

        $options = array();
        foreach ($matches as $match) {
            $options[$match[1]][] = trim($match[2]);
        }

        return $options;
    }

    public function testEverySystemButtonValueIsOfferedOnlyOnce(): void
    {
        $duplicated = array();
        foreach (self::literalOptions() as $value => $labels) {
            if (count($labels) > 1) {
                $duplicated[] = $value . ' (' . implode(' / ', $labels) . ')';
            }
        }

        self::assertSame(
            array(),
            $duplicated,
            'these values are offered more than once: ' . implode(', ', $duplicated)
        );
    }

    public function testTheSetOptionIsOfferedExactlyOnce(): void
    {
        $setValues = array();
        foreach (self::literalOptions() as $value => $labels) {
            if (in_array('Sæt', $labels, true)) {
                // array keys are integers for numeric strings, so cast back for a readable assertion
                $setValues[] = (string) $value;
            }
        }

        self::assertSame(array('33'), $setValues, 'the Sæt button must be offered once, as value 33');
    }

    public function testValueThirtyThreeIsTheSetButton(): void
    {
        $options = self::literalOptions();

        self::assertArrayHasKey('33', $options);
        self::assertSame(array('Sæt'), $options['33']);
    }

    public function testValueFortySevenIsTheTerminalReconciliation(): void
    {
        $options = self::literalOptions();

        self::assertArrayHasKey('47', $options);
        self::assertSame(
            array('Lane3000 afstemning'),
            $options['47'],
            'value 47 belongs to the terminal reconciliation, not to Sæt'
        );
    }

    /**
     * The list and the till have to agree on what a value means. This pins the other side of that
     * contract for the value the duplicate was fighting over.
     */
    public function testTheTillStillRendersValueFortySevenAsTheTerminalReconciliation(): void
    {
        self::assertMatchesRegularExpression(
            "/c == '47'\)\s*\{.{0,400}?lane3000_afstemning\.php/s",
            self::source('includes/posmenufunc.php'),
            'the till must keep mapping value 47 to the terminal reconciliation page'
        );
    }

    public function testNoOtherSourceFileOffersASetSystemButton(): void
    {
        $offenders = array();
        $directory = new RecursiveDirectoryIterator(self::repoRoot(), FilesystemIterator::SKIP_DOTS);
        $filtered = new RecursiveCallbackFilterIterator(
            $directory,
            static function (SplFileInfo $current): bool {
                if (!$current->isDir()) {
                    return true;
                }
                $name = $current->getFilename();

                return !in_array($name, array('.git', 'node_modules', 'tests', 'vendor'), true);
            }
        );

        foreach (new RecursiveIteratorIterator($filtered) as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = substr($file->getPathname(), strlen(self::repoRoot()) + 1);
            if ($relativePath === self::SYSTEM_BUTTONS) {
                continue;
            }
            if (strpos((string) file_get_contents($file->getPathname()), '>Sæt</OPTION>') !== false) {
                $offenders[] = $relativePath;
            }
        }

        self::assertSame(
            array(),
            $offenders,
            'the system-button list is defined in one place only; these files offer "Sæt" as well: '
                . implode(', ', $offenders)
        );
    }
}
