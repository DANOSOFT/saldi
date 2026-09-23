<?php
// 20260911 LOE Cover SD-685: the grid's stored setup separates display text from data —
// 20260916 LOE Cover SD-685 review: legacy headers are kept unless the code produces them.
//                filter selections keyed independently of their labels, and column
//                headers/description following the code (translations included).
// 20260923 LOE SD-685 review: a setup saved before the visibility flags is normalised when the grid loads.

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// grid.php defines these functions unconditionally, so it is loaded in its own process.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class GridSetupTranslationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 3) . '/includes/grid.php';
    }

    private function option($checked, $optionKey, $name)
    {
        $option = array('name' => $name, 'checked' => $checked, 'sqlOn' => '', 'sqlOff' => 'x');
        if ($optionKey !== null) {
            $option['optionKey'] = $optionKey;
        }

        return $option;
    }

    private function filter($options, $filterKey, $name)
    {
        $filter = array('filterName' => $name, 'joinOperator' => 'and', 'options' => $options);
        if ($filterKey !== null) {
            $filter['filterKey'] = $filterKey;
        }

        return array($filter);
    }

    private function codeColumn($field, $headerName, $description = '', $options = array())
    {
        return array_merge(array(
            'field' => $field, 'headerName' => $headerName, 'description' => $description,
            'width' => 1, 'align' => 'left', 'hidden' => false,
        ), $options);
    }

    // ---------------------------------------------------------------- filters

    public function testSelectionKeyedByFilterKeysSurvivesTranslationAndLanguageSwitch(): void
    {
        $savedInDanish = array('misc' => array('show_discontinued' => 'checked'));
        $translatedPage = $this->filter(array($this->option('', 'show_discontinued', 'Show discontinued')), 'misc', 'Misc');

        $resolved = updateCheckedValues($translatedPage, $savedInDanish);

        self::assertSame('checked', $resolved[0]['options'][0]['checked']);
    }

    public function testLegacyTextKeyedRowIsStillHonoured(): void
    {
        $savedByText = array('Diverse' => array('Vis udgåede' => 'checked'));
        $untranslatedPage = $this->filter(array($this->option('checked', null, 'Vis udgåede')), null, 'Diverse');

        $resolved = updateCheckedValues($untranslatedPage, $savedByText);

        self::assertSame('checked', $resolved[0]['options'][0]['checked']);
    }

    public function testTranslatedPageWithoutKeysFallsBackToTheDeclaredDefault(): void
    {
        $savedByText = array('Diverse' => array('Vis udgåede' => 'checked'));
        $translatedPage = $this->filter(array($this->option('', null, 'Show discontinued')), null, 'Misc');

        $resolved = updateCheckedValues($translatedPage, $savedByText);

        self::assertSame('', $resolved[0]['options'][0]['checked']);
    }

    public function testFilterPanelNamesTheFieldAfterTheKeysWhenDeclared(): void
    {
        $filters = $this->filter(array($this->option('', 'show_discontinued', 'Show discontinued')), 'misc', 'Misc');

        ob_start();
        render_filters('ordrestat', $filters, $filters);
        $html = ob_get_clean();

        self::assertStringContainsString("name='filter[ordrestat][misc][show_discontinued]'", $html);
        self::assertStringContainsString("type='hidden'", $html); // SD-686: unticked options are submitted
    }

    public function testFilterPanelFallsBackToTheDisplayTextWhenNoKeyIsDeclared(): void
    {
        $filters = $this->filter(array($this->option('checked', null, 'Vis udgåede')), null, 'Diverse');

        ob_start();
        render_filters('ordrestat', $filters, $filters);
        $html = ob_get_clean();

        self::assertStringContainsString("name='filter[ordrestat][Diverse][Vis udgåede]'", $html);
    }

    // ---------------------------------------------------------------- columns

    public function testTranslatedHeadersReachAUserWhoSavedTheSetupInAnotherLanguage(): void
    {
        $code = array(
            $this->codeColumn('varenr', 'Item No.', '', array('headerTexts' => array('Vare Nr.'))),
            $this->codeColumn('momspris', 'Sales price', '(incl. VAT)', array('headerTexts' => array('Salgspris'))),
        );
        $savedInDanish = array(
            array('field' => 'varenr', 'headerName' => 'Vare Nr.', 'description' => '', 'width' => 1, 'align' => 'left'),
            array('field' => 'momspris', 'headerName' => 'Salgspris', 'description' => '(inkl. moms)', 'width' => 0.5, 'align' => 'right'),
        );

        $merged = merge_column_setup($savedInDanish, $code);

        self::assertSame('Item No.', $merged[0]['headerName'], 'A stored header the code also produces is not a rename - the code text must not freeze.');
        self::assertSame('Sales price', $merged[1]['headerName']);
        self::assertSame('(incl. VAT)', $merged[1]['description'], 'The sub-header must follow the code too.');
        self::assertSame('', $merged[0]['customHeaderName'], 'nothing to prefill: the stored text was the code\'s own');
    }

    /**
     * SD-685 review: the same storage shape is also how a genuine personal rename
     * used to be saved, so a stored header the code could not have produced is kept
     * as customHeaderName instead of being dropped.
     */
    public function testALegacyHeaderTheCodeCouldNotProduceIsKeptAsARename(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.', '', array('headerTexts' => array('Vare Nr.'))));
        $saved = array(array('field' => 'varenr', 'headerName' => 'Mit navn'));

        $merged = merge_column_setup($saved, $code);

        self::assertSame('Mit navn', $merged[0]['headerName']);
        self::assertSame('Mit navn', $merged[0]['customHeaderName'], 'the editor needs the raw text for prefill');
    }

    /**
     * A column whose header is not resolved from translations - a literal in the pool,
     * or one built from tenant data like the warehouse columns - has no set of code
     * forms to compare against, so a stored value the code does not produce cannot be
     * proven stale. It is kept as the user's own wording rather than dropped, which is
     * the deliberate trade-off: an older code text kept by mistake is visible and
     * clearable in the editor, a discarded rename is gone for good.
     */
    public function testALegacyRenameOnAColumnWithoutDeclaredCodeTextsIsKept(): void
    {
        $code = array($this->codeColumn('varenr', 'Vare Nr.'));
        $saved = array(array('field' => 'varenr', 'headerName' => 'Mit navn'));

        $merged = merge_column_setup($saved, $code);

        self::assertSame('Mit navn', $merged[0]['headerName']);
        self::assertSame('Mit navn', $merged[0]['customHeaderName'], 'the editor needs the raw text for prefill');
    }

    public function testUserOrderAndWidthPreferencesAreKept(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('beskrivelse', 'Name'));
        $saved = array(
            array('field' => 'beskrivelse', 'width' => 3, 'align' => 'left'),
            array('field' => 'varenr', 'width' => 2, 'align' => 'right'),
        );

        $merged = merge_column_setup($saved, $code);

        self::assertSame(array('beskrivelse', 'varenr'), array_column($merged, 'field'));
        self::assertSame(2, $merged[1]['width']);
        self::assertSame('right', $merged[1]['align']);
    }

    /**
     * SD-685 review: a setup saved after the editor started writing the per-row 'visible' flag is
     * current, so a column the code has added since does reach the user.
     */
    public function testAColumnAddedToTheCodeAppearsOnceTheSetupCarriesVisibilityFlags(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(array('field' => 'varenr', 'visible' => true, 'width' => 1, 'align' => 'left'));

        $merged = merge_column_setup($saved, $code);

        self::assertContains('kategori', array_column($merged, 'field'));
    }

    /**
     * SD-685 review: rows saved before the flag existed cannot say "removed" - the editor dropped
     * the row instead - so an absent code column was removed by the user and must stay out. Such a
     * setup is normalised once when the grid loads, which writes the flags and ends this state.
     */
    public function testAColumnTheUserRemovedBeforeTheFlagsExistedStaysHidden(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(array('field' => 'varenr', 'headerName' => 'Item No.', 'width' => 1, 'align' => 'left'));

        $merged = merge_column_setup($saved, $code);

        self::assertSame(array('varenr'), array_column($merged, 'field'));
    }

    public function testALegacySetupIsNormalisedWithTheColumnsTheUserRemovedRecorded(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(array('field' => 'varenr', 'headerName' => 'Mit navn', 'width' => 2, 'align' => 'right'));

        $rows = normalize_legacy_column_setup($saved, $code);

        self::assertSame(array('varenr', 'kategori'), array_column($rows, 'field'));
        self::assertTrue($rows[0]['visible'], 'the user keeps what the setup already had');
        self::assertSame('Mit navn', $rows[0]['headerName'], 'and their own wording with it');
        self::assertSame(2, $rows[0]['width']);
        self::assertFalse($rows[1]['visible'], 'a code column the setup did not have is recorded as removed');
        self::assertTrue($rows[1]['addedSinceSetup'], 'and is marked as such: it is not the same as a deletion');
    }

    public function testNormalisingALegacySetupIsIdempotent(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $rows = normalize_legacy_column_setup(array(array('field' => 'varenr')), $code);

        self::assertSame($rows, normalize_legacy_column_setup($rows, $code));
        self::assertFalse(grid_setup_is_legacy($rows), 'once written, the setup is current and is not normalised again');
    }

    /**
     * A grid without a column editor cannot contain a deletion, so its merge keeps appending the
     * columns the code has (grid_account_lookup.php passes the flag as false).
     */
    public function testAGridWithNoEditorAppendsColumnsEvenForALegacySetup(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(array('field' => 'varenr', 'width' => 1, 'align' => 'left'));

        self::assertSame(array('varenr'), array_column(merge_column_setup($saved, $code), 'field'));
        self::assertSame(array('varenr', 'kategori'), array_column(merge_column_setup($saved, $code, false), 'field'));
    }

    /**
     * SD-685 review: the editor lists the columns the normalisation hid, and only those - a column
     * the user removed on purpose keeps its absence, because that absence was their own choice.
     */
    public function testOnlyTheColumnsTheMigrationInventedAreOfferedBack(): void
    {
        $setup = array(
            array('field' => 'varenr', 'visible' => true),
            array('field' => 'kategori', 'visible' => false, 'addedSinceSetup' => true),
            array('field' => 'pris', 'visible' => false),
        );

        $offered = grid_setup_added_since_setup($setup);

        self::assertSame(array('kategori'), array_column($offered, 'field'));
        self::assertSame(array(), grid_setup_added_since_setup(array()));
        self::assertSame(array(), grid_setup_added_since_setup(array(array('field' => 'pris', 'visible' => false))));
    }

    public function testALegacySetupIsRecognisedByTheMissingVisibilityFlags(): void
    {
        self::assertFalse(grid_setup_is_legacy(array()), 'a user without a stored setup has nothing to normalise');
        self::assertTrue(grid_setup_is_legacy(array(array('field' => 'varenr'))));
        self::assertFalse(grid_setup_is_legacy(array(
            array('field' => 'varenr'),
            array('field' => 'kategori', 'visible' => false),
        )), 'one row with the flag means the setup was written by the current editor');
    }

    public function testAColumnTheUserRemovedStaysHidden(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'), $this->codeColumn('kategori', 'Categories'));
        $saved = array(
            array('field' => 'varenr', 'width' => 1, 'align' => 'left'),
            array('field' => 'kategori', 'visible' => false, 'width' => 1, 'align' => 'left'),
        );

        $merged = merge_column_setup($saved, $code);

        self::assertNotContains('kategori', array_column($merged, 'field'));
    }

    public function testAStoredFieldThatNoLongerExistsInTheCodeIsDropped(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'));
        // A current row, so the assertion is about the stale stored field and not about the
        // legacy rule: this setup has the flag, so 'varenr' is expected to be appended.
        $saved = array(array('field' => 'gammelt', 'visible' => true, 'width' => 1, 'align' => 'left'));

        $merged = merge_column_setup($saved, $code);

        self::assertSame(array('varenr'), array_column($merged, 'field'));
    }

    public function testACodeHiddenColumnIsShownOnlyWhenTheUserHasIt(): void
    {
        $code = array($this->codeColumn('skjult', 'Internal', '', array('hidden' => true)));

        self::assertSame(array(), merge_column_setup(array(), $code));
        self::assertSame(array('skjult'), array_column(merge_column_setup(array(array('field' => 'skjult', 'width' => 1)), $code), 'field'));
    }

    public function testAUserHeaderOverridesTheCodeTextButAnEmptyOneFallsBackToIt(): void
    {
        $code = array($this->codeColumn('varenr', 'Item No.'));

        $custom = merge_column_setup(array(array('field' => 'varenr', 'customHeaderName' => 'Mit navn')), $code);
        self::assertSame('Mit navn', $custom[0]['headerName']);
        self::assertSame('Mit navn', $custom[0]['customHeaderName'], 'The editor needs the raw user text for prefill.');

        $empty = merge_column_setup(array(array('field' => 'varenr', 'customHeaderName' => '')), $code);
        self::assertSame('Item No.', $empty[0]['headerName']);
        self::assertSame('', $empty[0]['customHeaderName']);
    }
}
