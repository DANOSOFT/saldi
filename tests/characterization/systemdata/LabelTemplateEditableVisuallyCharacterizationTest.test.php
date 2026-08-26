<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * CHARACTERIZATION suite for labelTemplateEditableVisually() (systemdata/sys_div_func.php),
 * pinning the MB-18 fix: the visual label editor's "Gem/opdatér" save always fully regenerates
 * a label's HTML from its own narrow field model (cols/rows/margins/checkboxes/5 custom text
 * lines) via generateLabelTemplate(). parseLabelTemplate() only understands that same narrow
 * shape, so any template built outside it - every one of Saldi's shipped Brother/Dymo import
 * templates included - has content (transform/rotate, real dimensions, ...) that's invisible
 * to the parser and gets silently discarded the moment the visual editor saves, regardless of
 * which single field the user actually changed.
 *
 * labelTemplateEditableVisually($labelText) is the guard added to stop this: it checks whether
 * generateLabelTemplate(parseLabelTemplate($labelText)) reproduces $labelText, i.e. whether
 * nothing about it lives outside the visual editor's model. systemdata/diverse.php's $saveLabel
 * branch refuses to save whenever this is false, and systemdata/sys_div_func.php's labels()
 * forces raw-HTML editing mode for such labels instead of offering the destructive visual editor.
 *
 * This suite pins both the guard function itself and the end-to-end save decision it drives -
 * reproducing the exact "user changes one setting" scenario from the ticket, confirmed
 * red (destroys the label) against the pre-fix code and green (refuses, preserves it) here.
 *
 * Requires a local Postgres reachable with includes/connect.php's checked-in dev credentials
 * (localhost/postgres), and a "saldi_chartest" database already cloned from the saldidb
 * template - the same tenant convention used by the MB-16/MB-17 suites.
 */
final class LabelTemplateEditableVisuallyCharacterizationTest extends TestCase
{
    private const TENANT_DB = 'saldi_chartest';
    private const LABEL_NAME = 'MB18CharacterizationLabel';

    // includes/connect.php's own checked-in dev defaults - see the same note in
    // NewlabelCharacterizationTest / SaveLabelTextCharacterizationTest for why these are
    // hardcoded here rather than read back off connect.php's own globals.
    private const PG_HOST = 'localhost';
    private const PG_USER = 'postgres';
    private const PG_PASS = 'saul3112';

    private static string $repoRoot;
    private static string $originalCwd;

    public static function setUpBeforeClass(): void
    {
        self::$repoRoot = dirname(__DIR__, 3);
        self::$originalCwd = getcwd();

        $_SERVER['REQUEST_URI'] = '/saldi/systemdata/diverse.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        chdir(self::$repoRoot . '/systemdata');
        ob_start();
        require_once self::$repoRoot . '/systemdata/sys_div_func.php';
        $includeOutput = ob_get_clean();
        self::assertSame('', $includeOutput, 'sys_div_func.php emitted output at include time');

        global $db, $connection;
        $db = self::TENANT_DB;
        $connection = db_connect(self::PG_HOST, self::PG_USER, self::PG_PASS, $db);
    }

    public static function tearDownAfterClass(): void
    {
        db_modify("delete from labels where labelname = '" . self::LABEL_NAME . "'", __FILE__ . ' linje ' . __LINE__);
        chdir(self::$originalCwd);
    }

    protected function setUp(): void
    {
        db_modify("delete from labels where labelname = '" . self::LABEL_NAME . "'", __FILE__ . ' linje ' . __LINE__);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function shippedImportTemplates(): array
    {
        return [
            'Brother 22606'          => ['BrotherLabel22606.txt'],
            'Brother 22606 mit salg' => ['BrotherLabel22606MS.txt'],
            'Dymo 11354'             => ['DymoLabelArt11354.txt'],
            'Dymo 11354 mit salg'    => ['DymoLabelArt11354MS.txt'],
        ];
    }

    /**
     * Every label template Saldi ships via the "Ny Label" -> "Opret" flow uses CSS
     * (transform/rotate, custom margins/widths) that parseLabelTemplate() doesn't model -
     * confirming the guard actually catches the templates customers hit this bug with.
     */
    #[DataProvider('shippedImportTemplates')]
    public function testShippedImportTemplatesAreNotEditableVisually(string $file): void
    {
        $template = file_get_contents(self::$repoRoot . '/importfiler/' . $file);
        self::assertNotFalse($template, "fixture $file should exist");

        self::assertFalse(labelTemplateEditableVisually($template));
    }

    public function testATemplateBuiltEntirelyByTheVisualEditorIsEditableVisually(): void
    {
        $template = generateLabelTemplate($this->visualEditorFormData(['txtlen' => 50]));

        self::assertTrue(labelTemplateEditableVisually($template));
    }

    public function testAnEmptyTemplateIsEditableVisually(): void
    {
        // A brand new label has nothing to lose yet.
        self::assertTrue(labelTemplateEditableVisually(''));
    }

    /**
     * SirRolin's PR #508 review: deleting a 'Standard' label's raw HTML entirely and saving
     * (leaving the stored labeltext truly empty) incorrectly stayed locked out of the visual
     * editor on the next page load. The cause was upstream of this guard function itself -
     * sys_div_func.php's labels() fills the display-only raw-HTML textarea for an empty
     * 'Standard' label with a hardcoded placeholder template (written in the labels table's
     * older $beskrivelse/$pris variable names, predating generateLabelTemplate()'s
     * $minbeskrivelse/$minpris), and was running THIS guard against that placeholder instead of
     * against what loadLabelText() actually returned. This test pins the fact that drives the
     * bug: the placeholder itself is not visually editable, so it must never be the value
     * labelTemplateEditableVisually() is asked to judge - labels() now saves the stored text
     * to a separate variable before the placeholder substitution and guards on that instead.
     */
    public function testTheEmptyStandardDisplayPlaceholderIsNotItselfVisuallyEditable(): void
    {
        $placeholder = '$cols=1;
$rows=1;
$txtlen=50;
<top>
<style>
#main {
width: 100%;
overflow:hidden;
margin-top: 7mm;
margin-bottom: 0mm;
margin-right: 0mm;
margin-left: 3mm;}

p {
width: 38.1mm;
display: inline-block;
height: 21.2mm;
padding-bottom:0px;
margin-top: 0mm;
margin-bottom: 0mm;
margin-right: 0mm;
margin-left: 1mm;
font-size: 12px}

img {
width: 90%;
height: 5mm;
margin-left:-4px}
</style>
<div id="main">
</top>

<p>
$varenr<br>
$beskrivelse<br>
Pris $pris<br>
<img src=\'$img\'><br>
</p>

<bottom>
</div>
/bottom;';

        self::assertFalse(
            labelTemplateEditableVisually($placeholder),
            "labels()'s 'Standard'-and-empty display placeholder must never be passed to this guard"
        );
    }

    /**
     * The literal MB-18 scenario: a customer's imported Brother label, and a visual-editor
     * save where only ONE setting (txtlen) is actually submitted - mirroring
     * systemdata/diverse.php's $saveLabel branch, which now checks this guard first. Before
     * the fix this save proceeded unconditionally and replaced the real 50mm/rotate(0deg)
     * Brother layout with generateLabelTemplate()'s generic 38.1mm/no-rotate defaults.
     */
    public function testSavingOneSettingOnAnImportedLabelIsRefusedAndNothingIsLost(): void
    {
        $importedTemplate = file_get_contents(self::$repoRoot . '/importfiler/BrotherLabel22606.txt');
        saveLabelText('box1', self::LABEL_NAME, $importedTemplate, 'sheet');

        $this->attemptGuardedVisualSave(self::LABEL_NAME, ['txtlen' => '40']);

        $after = loadLabelText('box1', self::LABEL_NAME);
        self::assertSame($importedTemplate, $after['labeltext'], 'an unsafe save must leave the stored template completely untouched');
        self::assertStringContainsString('rotate(0deg)', $after['labeltext']);
    }

    /**
     * The guard must not block genuine visual-editor labels: building one, changing one field,
     * and saving again must actually persist that change while preserving the rest.
     */
    public function testSavingOneSettingOnAVisualEditorLabelSucceedsAndPreservesTheRest(): void
    {
        $original = generateLabelTemplate($this->visualEditorFormData([
            'cols' => 2, 'txtlen' => 50, 'show_pris' => true,
        ]));
        saveLabelText('box1', self::LABEL_NAME, $original, 'sheet');

        $this->attemptGuardedVisualSave(self::LABEL_NAME, ['txtlen' => '40']);

        $after = loadLabelText('box1', self::LABEL_NAME);
        self::assertStringContainsString('$cols=2;', $after['labeltext'], 'unrelated field must survive the save');
        self::assertStringContainsString('$txtlen=40;', $after['labeltext'], 'the changed field must actually be applied');
        self::assertStringContainsString('minpris', $after['labeltext'], 'unrelated field must survive the save');
    }

    /**
     * Mirrors systemdata/diverse.php's $saveLabel branch together with labels()'s form
     * pre-fill: refuse when the label's CURRENT template isn't reproducible by the visual
     * editor's model; otherwise the real browser form (pre-filled from parseLabelTemplate(),
     * per labels()) submits every field, with only $postFields actually changed by the "user".
     */
    private function attemptGuardedVisualSave(string $labelName, array $postFields): void
    {
        $existing = loadLabelText('box1', $labelName);
        if (!labelTemplateEditableVisually($existing['labeltext'])) {
            return;
        }
        $formData = $this->visualEditorFormData(array_merge(parseLabelTemplate($existing['labeltext']), $postFields));
        saveLabelText('box1', $labelName, generateLabelTemplate($formData), if_isset($postFields['labelType'], 'sheet'));
    }

    private function visualEditorFormData(array $overrides): array
    {
        $formData = array_merge([
            'cols' => 1, 'rows' => 1, 'txtlen' => 50,
            'width' => '38.1', 'height' => '21.2', 'font_size' => '12',
            'margin_top' => '7', 'margin_left' => '3',
            'show_varenr' => true, 'show_varemrk' => false,
            'show_beskrivelse' => true, 'show_pris' => false, 'show_barcode' => true,
            'varenr_font_size' => '12', 'varemrk_font_size' => '12',
            'beskrivelse_font_size' => '12', 'pris_font_size' => '12',
        ], $overrides);
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($formData["custom_text_$i"])) $formData["custom_text_$i"] = '';
            if (!isset($formData["custom_text_{$i}_size"])) $formData["custom_text_{$i}_size"] = $formData['font_size'];
        }
        return $formData;
    }
}
