<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// 20261007 MJ SST-830 Pins that a barcode fills the box a label template gives it.
//                  Converting barcode generation from PNG to SVG (#335) changed how the
//                  image behaves inside a fixed box: a PNG stretches to fill it, while an
//                  SVG with a viewBox and no preserveAspectRatio keeps its own ~9:1
//                  proportions and letterboxes instead. Knudepunktet's "Standard" template
//                  gives the image 80% x 2.15em - about 4.4:1 - so the code came out about
//                  half as tall as before, which is what the customer reported.
//
//                  Measured in a browser against the real generated SVG: in that box the
//                  barcode painted 41% of the height before and 88% after. 88% rather than
//                  100% is the vertical quiet zone from the 'p' => 2 option, which is
//                  intended and unchanged.
final class BarcodeAspectRatioTest extends TestCase
{
    /** The options barcode() in includes/std_func.php passes, minus the one under test. */
    private const BASE = ['w' => 285, 'h' => 32, 'p' => 2, 'ph' => 0, 'th' => 0, 'ts' => 0, 'bc' => ''];

    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../../includes/barcode.php';
    }

    /**
     * @param array<string, mixed> $extra Options on top of the base set.
     */
    private function render(string $symbology, string $data, array $extra = []): string
    {
        $generator = new \barcode_generator();
        return $generator->render_svg($symbology, $data, self::BASE + $extra);
    }

    /** Returns just the opening <svg ...> tag. */
    private function svgTag(string $svg): string
    {
        self::assertSame(1, preg_match('/<svg[^>]*>/', $svg, $m), 'no <svg> element was produced');
        return $m[0];
    }

    /**
     * Without the option the renderer behaves exactly as the upstream Kreative release, so
     * callers that have not asked for stretching are unaffected.
     */
    #[DataProvider('symbologies')]
    public function testWithoutTheOptionNoAspectRatioIsWritten(string $symbology, string $data): void
    {
        self::assertStringNotContainsString('preserveAspectRatio', $this->svgTag($this->render($symbology, $data)),
            'the renderer now writes preserveAspectRatio unasked, which changes upstream behaviour');
    }

    /** With it, the barcode is told to fill whatever box it is given. */
    #[DataProvider('symbologies')]
    public function testTheOptionWritesTheAttribute(string $symbology, string $data): void
    {
        self::assertStringContainsString('preserveAspectRatio="none"',
            $this->svgTag($this->render($symbology, $data, ['par' => 'none'])),
            'the barcode will letterbox inside a fixed box, which is the reported bug');
    }

    /** @return array<string, array{string, string}> */
    public static function symbologies(): array
    {
        return [
            // The customer's item number, and a valid EAN-13 - acceptance criterion 3.
            'code128, the reported item' => ['code128', 'kbfrh1282'],
            'code128, a Mit salg code' => ['code128', '291465000bb8'],
            'ean13' => ['ean13', '5701234567899'],
        ];
    }

    /**
     * The decisive point for scanning: stretching changes nothing about the encoding. Both
     * symbologies carry their data in relative bar widths, and every bar is scaled by the
     * same factor, so the rendered geometry must be identical apart from the attribute.
     */
    #[DataProvider('symbologies')]
    public function testStretchingDoesNotChangeASingleBar(string $symbology, string $data): void
    {
        $plain = $this->render($symbology, $data);
        $stretched = $this->render($symbology, $data, ['par' => 'none']);

        self::assertNotSame($plain, $stretched, 'the option had no effect at all');

        // Everything except the opening tag has to match byte for byte. The tag does not
        // start at offset 0 - render_svg() emits an XML prolog first - so locate it.
        $after = function (string $svg): string {
            $tag = $this->svgTag($svg);
            return substr($svg, strpos($svg, $tag) + strlen($tag));
        };
        self::assertSame($after($plain), $after($stretched),
            'the bars themselves changed, so the barcode may no longer scan');

        // And the viewBox is untouched, so the proportions being mapped are the same.
        foreach ([$plain, $stretched] as $svg) {
            self::assertStringContainsString('viewBox="0 0 285 32"', $this->svgTag($svg));
        }
    }

    /** An empty or absent value is treated as "not asked for", not as an empty attribute. */
    #[DataProvider('emptyValues')]
    public function testAnEmptyValueWritesNothing(mixed $value): void
    {
        $svg = $this->render('code128', 'kbfrh1282', ['par' => $value]);
        self::assertStringNotContainsString('preserveAspectRatio', $this->svgTag($svg));
    }

    /** @return array<string, array{mixed}> */
    public static function emptyValues(): array
    {
        return ['empty string' => ['']];
    }

    /** The value is escaped, since it ends up in an attribute. */
    public function testTheValueIsEscaped(): void
    {
        $svg = $this->render('code128', 'kbfrh1282', ['par' => 'none" onload="x']);
        $tag = $this->svgTag($svg);

        self::assertStringNotContainsString('onload="x', $tag, 'the attribute value is not escaped');
        self::assertStringContainsString('&quot;', $tag, 'the quote was not encoded');
    }

    /**
     * barcode() is what every label path calls, so the option has to be set there - the
     * renderer alone changes nothing. barcode() lives in includes/std_func.php, which pulls
     * in most of the application on include, so this is read from the source.
     */
    public function testBarcodeAsksForTheStretchedRendering(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../includes/std_func.php');
        self::assertNotFalse($src, 'could not read includes/std_func.php');

        $at = strpos($src, 'function barcode(');
        self::assertNotFalse($at, 'barcode() is gone from std_func.php');
        $end = strpos($src, 'return $svg_path;', $at);
        self::assertNotFalse($end, 'barcode() no longer returns the svg path');
        $body = substr($src, $at, $end - $at);

        self::assertStringContainsString("'par' => 'none',", $body,
            'barcode() no longer asks for the stretched rendering, so labels letterbox again');
        self::assertStringContainsString('render_svg(', $body,
            'barcode() no longer renders an SVG');
    }

    /**
     * The quiet zone above and below is deliberate and unchanged - it is why the barcode
     * paints about 88% of the box rather than exactly 100%, which should not be mistaken
     * for the bug coming back.
     */
    public function testTheVerticalQuietZoneIsUnchanged(): void
    {
        $src = file_get_contents(__DIR__ . '/../../../includes/std_func.php');
        $at = strpos($src, 'function barcode(');
        $body = substr($src, $at, strpos($src, 'return $svg_path;', $at) - $at);

        self::assertStringContainsString("'p'  => 2,", $body, 'the quiet zone changed size');
        self::assertStringContainsString("'h'  => 32,", $body, 'the rendered height changed');
    }
}
