<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of saveOrderOptions() (systemdata/diverseIncludes/saveOrderOptions.php), the save
 * of Diverse > Ordrerelaterede valg that SD-786 moved out of systemdata/diverse.php.
 *
 * Pins what the save writes: the settings rows (update_settings_value()), grupper DIV 3 box2-box14,
 * grupper DIV 5 box6/box8 and orderNoteEnabled, plus the alerts for unknown item numbers. The database
 * layer is a double that records every statement; db_escape_string() is addslashes(), so escaping
 * shows up in the recorded SQL.
 *
 * History:
 * 20261029 CL/MMR Created (SD-786, Codex round 2: characterize the extracted save).
 */
#[RunTestsInSeparateProcesses]
final class SaveOrderOptionsTest extends TestCase
{
    /** The settings the save may touch, in the order it writes them. Nothing else may be written. */
    private const SETTINGS = [
        'debitoripad', 'pluklisteEmail', 'porto_varnr', 'showDB', 'showDG', 'lockedInvoiceButton', 'ordreAutocomplete',
        'gs1_parsing', 'ourRefStockSwitch', 'stockWarningEnabled', 'showBothAddrExtra', 'showCostEstimateNotice',
        'vatPrivateCustomers', 'vatBusinessCustomers', 'hideRevenue', 'debitoripad',
    ];

    /**
     * @param array<string, string> $items varenr => id the double knows
     */
    private static function boot(array $items = ['R1' => '501', "S'2" => '502'], bool $div3 = true, bool $div5 = true, bool $note = true): void
    {
        $GLOBALS['__items'] = $items;
        $GLOBALS['__rows'] = ['div3' => $div3 ? '30' : null, 'div5' => $div5 ? '50' : null, 'note' => $note ? '70' : null];
        $GLOBALS['__modify'] = [];
        $GLOBALS['__settings'] = [];
        eval('
            function ifset($arr, $key = null, $default = null) {
                return (is_array($arr) && array_key_exists($key, $arr) && $arr[$key] !== null) ? $arr[$key] : $default;
            }
            function db_escape_string($s) { return addslashes((string) $s); }
            function db_select($q, $where = "") {
                if (preg_match("/from varer WHERE varenr = \'((?:[^\'\\\\\\\\]|\\\\\\\\.)*)\'/", $q, $m)) {
                    $nr = stripslashes($m[1]);
                    return (object) ["rows" => isset($GLOBALS["__items"][$nr]) ? [["id" => $GLOBALS["__items"][$nr]]] : []];
                }
                if (strpos($q, "kodenr=\'3\'") !== false) return (object) ["rows" => $GLOBALS["__rows"]["div3"] ? [["id" => $GLOBALS["__rows"]["div3"]]] : []];
                if (strpos($q, "kodenr=\'5\'") !== false) return (object) ["rows" => $GLOBALS["__rows"]["div5"] ? [["id" => $GLOBALS["__rows"]["div5"]]] : []];
                if (strpos($q, "orderNoteEnabled") !== false) return (object) ["rows" => $GLOBALS["__rows"]["note"] ? [["id" => $GLOBALS["__rows"]["note"]]] : []];
                return (object) ["rows" => []];
            }
            function db_fetch_array($r) { return array_shift($r->rows) ?? false; }
            function db_modify($q, $where = "") { $GLOBALS["__modify"][] = $q; return true; }
            function update_settings_value($name, $group, $value, $description, $user = null) {
                $GLOBALS["__settings"][] = [$name, $group, $value, $user];
            }
            function findtekst($key, $sprog_id) { return explode("|", $key, 2)[1]; }
        ');
        require dirname(__DIR__, 3) . '/systemdata/diverseIncludes/saveOrderOptions.php';
    }

    /** @return array<string, string> setting name => last stored value */
    private static function settings(): array
    {
        $out = [];
        foreach ($GLOBALS['__settings'] as [$name, , $value]) {
            $out[$name] = $value;
        }
        return $out;
    }

    private static function modify(string $needle): string
    {
        foreach ($GLOBALS['__modify'] as $q) {
            if (strpos($q, $needle) !== false) {
                return $q;
            }
        }
        self::fail("no statement containing $needle; got: " . implode(' | ', $GLOBALS['__modify']));
    }

    public function testEveryFieldTickedAndFilled(): void
    {
        self::boot();
        $alerts = saveOrderOptions([
            'vatPrivateCustomers' => 'on', 'vatBusinessCustomers' => 'on', 'hideRevenue' => 'on',
            'box2' => 'R1', 'box3' => 'Følgeseddel', 'box4' => 'on', 'straks_deb' => 'on', 'straks_kred' => 'on', 'box6' => 'on',
            'box7' => '1000', 'box8' => 'on', 'box9' => 'on', 'box10' => '1010', 'box11' => 'on', 'box12' => 'on',
            'procenttillag' => '5', 'procentvare' => 'P1', 'box14' => 'on', 'kostmetode' => '1', 'saetvarenr' => "S'2",
            'orderNoteEnabled' => 'on', 'debitoripad' => 'on', 'portovarenr' => 'PORTO', 'showDB' => 'on', 'showDG' => 'on',
            'pluklisteEmail' => 'lager@example.dk', 'lockPayment' => 'on', 'ordreAutocomplete' => 'on', 'gs1_parsing' => 'on',
            'ourRefStockSwitch' => 'on', 'stockWarningEnabled' => 'on', 'showCostEstimateNotice' => 'on', 'showBothAddrExtra' => 'on',
        ], 1, 7);
        self::assertSame([], $alerts);
        self::assertSame(self::SETTINGS, array_column($GLOBALS['__settings'], 0), 'only the known settings, in this order');
        $s = self::settings();
        self::assertSame('on', $s['showCostEstimateNotice']);
        self::assertSame('on', $s['hideRevenue']);
        self::assertSame('lager@example.dk', $s['pluklisteEmail']);
        self::assertSame('PORTO', $s['porto_varnr']);
        self::assertContains(['ordreAutocomplete', 'ordre', 'on', 7], $GLOBALS['__settings'], 'ordreAutocomplete is per user');
        self::assertSame(
            "update grupper set box2='501',box3='Følgeseddel',box4='on',box5='on;on',box6='on',box7='1000',box8='on',box9='on',"
            . "box10='1010',box11='on',box12='on',box13='5\tP1',box14='on' WHERE id = '30'",
            self::modify("WHERE id = '30'")
        );
        self::assertSame("update grupper set box6='1',box8='502' WHERE id = '50'", self::modify("WHERE id = '50'"));
        self::assertSame("update settings set var_value='on' WHERE id='70'", self::modify("WHERE id='70'"));
    }

    public function testNothingTickedStoresEmptyValuesAndOff(): void
    {
        self::boot();
        self::assertSame([], saveOrderOptions([], 1, 7));
        $s = self::settings();
        self::assertSame('off', $s['showCostEstimateNotice'], 'the new box is stored as off');
        self::assertSame('off', $s['hideRevenue']);
        self::assertSame('', $s['showDB']);
        self::assertSame('', $s['vatPrivateCustomers']);
        self::assertSame(
            "update grupper set box2='',box3='',box4='',box5=';',box6='',box7='',box8='',box9='',box10='',box11='',box12='',"
            . "box13='\t',box14='' WHERE id = '30'",
            self::modify("WHERE id = '30'")
        );
        self::assertSame("update grupper set box6='',box8='0' WHERE id = '50'", self::modify("WHERE id = '50'"));
        self::assertSame("update settings set var_value='' WHERE id='70'", self::modify("WHERE id='70'"));
    }

    public function testQuotesAndDanishLettersAreEscapedNotLost(): void
    {
        self::boot();
        saveOrderOptions(['box3' => "Kid's æøå", 'pluklisteEmail' => "o'hara@example.dk", 'kostmetode' => "1'"], 1, 7);
        self::assertStringContainsString("box3='Kid\\'s æøå'", self::modify("WHERE id = '30'"));
        self::assertSame("o\\'hara@example.dk", self::settings()['pluklisteEmail']);
        self::assertSame("update grupper set box6='1\\'',box8='0' WHERE id = '50'", self::modify("WHERE id = '50'"));
    }

    public function testUnknownItemNumbersAreReportedAndKeptAsTyped(): void
    {
        self::boot();
        $alerts = saveOrderOptions(['box2' => "X'9", 'box14' => 'on', 'saetvarenr' => 'NOPE'], 1, 7);
        self::assertSame(["Varenr. X'9 eksisterer ikke.", 'Varenummer for sæt eksisterer ikke'], $alerts);
        $div3 = self::modify("WHERE id = '30'");
        self::assertStringContainsString("box2='X\\'9'", $div3, 'an unknown rabatvarenr is kept as typed, escaped');
        self::assertStringContainsString("box14='on'", $div3, 'a typed rabatvarenr still counts for samlet pris');
        self::assertStringContainsString("box8='0'", self::modify("WHERE id = '50'"));
    }

    public function testSamletPrisWithoutARabatItemIsRefused(): void
    {
        self::boot();
        $alerts = saveOrderOptions(['box14' => 'on'], 1, 7);
        self::assertSame(['Samlet pris forudsætter at der er et varenr. til rabat'], $alerts);
        self::assertStringContainsString("box14=''", self::modify("WHERE id = '30'"));
    }

    public function testMissingRowsAreInserted(): void
    {
        self::boot(['R1' => '501'], false, false, false);
        saveOrderOptions(['box2' => 'R1', 'kostmetode' => '2', 'orderNoteEnabled' => 'on'], 1, 7);
        self::assertSame(
            "insert into grupper (beskrivelse,kodenr,art,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14) "
            . "values ('Div_valg (Ordrer)','3','DIV','501','','',';','','','','','','','','\t','')",
            self::modify("'Div_valg (Ordrer)'")
        );
        self::assertStringContainsString("values ('Div_valg','5','DIV','','','','','','','2','','','','','','')", self::modify("'Div_valg','5'"));
        self::assertSame("insert into settings (var_name, var_value) values ('orderNoteEnabled','on')", self::modify('insert into settings'));
    }

    public function testItemLookupsEscapeTheTypedNumber(): void
    {
        self::boot();
        saveOrderOptions(['box2' => "R1' or '1'='1", 'saetvarenr' => "S'2"], 1, 7);
        self::assertStringContainsString("box2='R1\\' or \\'1\\'=\\'1'", self::modify("WHERE id = '30'"), 'no row matched the injected number');
        self::assertStringContainsString("box8='502'", self::modify("WHERE id = '50'"), "S'2 was found through the escaped lookup");
    }
}
