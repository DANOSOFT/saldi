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
 * Connection details are never literals here (doc/ai/convention_no_hardcoded_secrets.md): the
 * suite uses whatever the gitignored includes/connect.php says, exactly like the app does. The
 * "saldi_chartest" tenant database must already exist on that host, cloned from an installed
 * tenant; when it can't be reached the whole suite is skipped rather than errored.
 */
final class LabelTemplateEditableVisuallyCharacterizationTest extends TestCase
{
    private const TENANT_DB = 'saldi_chartest';
    private const LABEL_NAME = 'MB18CharacterizationLabel';

    private static string $repoRoot;
    private static string $originalCwd;
    private static bool $connected = false;

    public static function setUpBeforeClass(): void
    {
        // Must come BEFORE the require below - see the same note in
        // SaveLabelTextCharacterizationTest for why: an included file's top-level code runs in
        // the includer's scope, so connect.php's assignments would otherwise be trapped as
        // locals of this method instead of landing in the true global scope db_connect() reads.
        global $sqhost, $squser, $sqpass, $sqdb, $db_type, $db_encode, $connection, $db;

        self::$repoRoot = dirname(__DIR__, 3);
        self::$originalCwd = getcwd();

        $_SERVER['REQUEST_URI'] = '/saldi/systemdata/diverse.php';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        chdir(self::$repoRoot . '/systemdata');
        ob_start();
        require_once self::$repoRoot . '/systemdata/sys_div_func.php';
        $includeOutput = ob_get_clean();
        // Only whitespace is tolerated: the gitignored includes/connect.php is a per-developer
        // file and commonly ends with a closing PHP tag followed by a newline, which PHP emits
        // verbatim at include time.
        self::assertSame('', trim($includeOutput), 'sys_div_func.php emitted output at include time');

        $db = self::TENANT_DB;
        $connection = @db_connect($sqhost, $squser, $sqpass, $db);
        if (!$connection) {
            // tearDownAfterClass() is not guaranteed to run after a setUpBeforeClass() skip, so
            // restore the cwd here rather than relying on it to undo the chdir() above.
            chdir(self::$originalCwd);
            self::markTestSkipped(
                "tenant db '" . self::TENANT_DB . "' not reachable on host '$sqhost' as user '$squser' " .
                '- clone it from an installed tenant first'
            );
        }
        self::$connected = true;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$connected) {
            self::deleteTestLabel();
        }
        chdir(self::$originalCwd);
    }

    protected function setUp(): void
    {
        self::deleteTestLabel();
    }

    // saveLabelText() only ever writes account_id = '0'/null rows for a given labelname; scope
    // cleanup the same way so a same-named account-specific label in the cloned tenant survives.
    private static function deleteTestLabel(): void
    {
        $qtxt = "delete from labels where labelname = '" . self::LABEL_NAME . "'";
        $qtxt.= " and (account_id = '0' or account_id is null)";
        db_modify($qtxt, __FILE__ . ' linje ' . __LINE__);
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
        saveLabelText('box1', $labelName, generateLabelTemplate($formData), if_isset($postFields, 'sheet', 'labelType'));
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
