<?php
// 20261125 CDX/NTR Pin safe text rendering and highlighting across the general, customer-order and supplier-order grids.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GridCellTextTest extends TestCase
{
    /** @return array<string, array{string}> Every production row renderer. */
    public static function grids(): array
    {
        return [
            'general' => ['includes/grid.php'],
            'customer orders' => ['includes/orderFuncIncludes/grid_order.php'],
            'supplier orders' => ['includes/kreditorOrderFuncIncludes/creditor_orderlist_grid.php'],
        ];
    }

    #[DataProvider('grids')]
    public function testTextIsEscapedBeforeItReachesACustomRenderer(string $path): void
    {
        $raw = '<img src=x onerror=alert(1)> Pump & fittings';
        $rowSeen = null;
        $column = ['field' => 'name', 'type' => 'text', 'render' => static function ($value, $row) use (&$rowSeen): string {
            $rowSeen = $row['name'];
            return "<td><a href='item.php'>$value</a></td>";
        }];
        $html = self::render($path, $column, $raw, 'Pump');
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringContainsString('&amp; fittings', $html);
        self::assertStringContainsString('<span style="background-color:#FF0">Pump</span>', $html);
        self::assertStringContainsString("<a href='item.php'>", $html);
        self::assertSame($raw, $rowSeen, 'The raw row remains available for URL, SQL and comparison logic');
    }

    #[DataProvider('grids')]
    public function testDefaultCellsKeepLiteralEntitiesAndSafeHighlighting(string $path): void
    {
        $column = ['field' => 'name', 'type' => 'text', 'align' => 'left', 'render' => static fn($value): string => "<td>$value</td>"];
        $html = self::render($path, $column, 'A &amp; B < C', '&amp;');
        self::assertSame('<td>A <span style="background-color:#FF0">&amp;amp;</span> B &lt; C</td>', $html);
    }

    #[DataProvider('grids')]
    public function testAStoredHighlightSpanCannotBecomeTrustedMarkup(string $path): void
    {
        $payload = '<span style="background-color:#FF0"><img src=x onerror=alert(1)></span>';
        $column = ['field' => 'name', 'type' => 'text', 'render' => static fn($value): string => "<td>$value</td>"];
        $html = self::render($path, $column, $payload, 'img');
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;span', $html);
        self::assertStringContainsString('<span style="background-color:#FF0">img</span>', $html);
    }

    #[DataProvider('grids')]
    public function testTextReturnedByAValueGetterIsAlsoEscaped(string $path): void
    {
        $column = ['field' => 'name', 'type' => 'text', 'valueGetter' => static fn($value): string => $value . '<svg onload=alert(1)>', 'render' => static fn($value): string => "<td>$value</td>"];
        self::assertSame('<td>name&lt;svg onload=alert(1)&gt;</td>', self::render($path, $column, 'name', ''));
    }

    #[DataProvider('grids')]
    public function testMissingTypeDefaultsToSafeText(string $path): void
    {
        $column = ['field' => 'name', 'render' => static fn($value): string => "<td>$value</td>"];
        self::assertSame('<td>&lt;b&gt;name&lt;/b&gt;</td>', self::render($path, $column, '<b>name</b>', ''));
    }

    #[DataProvider('grids')]
    public function testDropdownTextIsEscapedAndBooleanRenderersKeepTheirInputs(string $path): void
    {
        $column = ['field' => 'name', 'type' => 'dropdown', 'render' => static fn($value): string => "<td>$value</td>"];
        self::assertSame('<td>A &amp; B</td>', self::render($path, $column, 'A & B', ''));
        $column['render'] = static fn($value): string => $value === false ? '<td>✗</td>' : '<td>✓</td>';
        self::assertSame('<td>✗</td>', self::render($path, $column, false, ''));
    }

    /** @return string HTML emitted by the real row renderer, without the grid's request/bootstrap code. */
    private static function render(string $path, array $column, $value, string $term): string
    {
        $root = dirname(__DIR__, 3);
        $helper = $root . '/includes/grid/text.php';
        if (is_file($helper)) {
            require_once $helper;
        }
        $namespace = 'GridCellTextFixture' . md5($path);
        $function = $namespace . '\\render_table_row';
        if (!function_exists($function)) {
            $source = file_get_contents($root . '/' . $path);
            preg_match('/function render_table_row\([^\n]*\) \{.*?^\}/ms', substr($source, strrpos($source, 'function render_table_row(')), $match);
            eval('namespace ' . $namespace . ';' . $match[0]);
        }
        ob_start();
        try {
            $function([$column], ['name' => $value], ['name' => $term]);
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
