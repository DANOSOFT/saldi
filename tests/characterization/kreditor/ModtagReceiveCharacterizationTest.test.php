<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/kreditor/ModtagReceiveCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Cover exact large receipt quantities and calculated batch rests at PHP precision 14.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../support/CharacterizationEnv.php';

require_once __DIR__ . '/../support/ModificationTrail.php';

final class ModtagReceiveCharacterizationTest extends TestCase
{
    private static $conn;
    private static string $session;
    private static int $year;
    private array $fixture;

    public static function setUpBeforeClass(): void
    {
        if (($reason = CharacterizationEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        CharacterizationEnv::bootstrapTenant();
        self::$conn = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        self::$year = (int)CharacterizationEnv::one(self::$conn, "SELECT max(kodenr) AS y FROM grupper WHERE art='RA'")['y'];
        self::$session = 't4b2a' . bin2hex(random_bytes(12));
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        CharacterizationEnv::rows($master, "INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,'chartest',$2,$3,$4,$5,$6,false,0)",
            [self::$session, CharacterizationEnv::testDb(), CharacterizationEnv::pgUser(), str_repeat('9',50), self::$year, (string)time()]);
        pg_close($master);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id=$1', [self::$session]);
            pg_close($master);
            pg_close(self::$conn);
            CharacterizationEnv::teardownTenant();
        }
    }

    private function rows(string $sql, array $params = []): array
    {
        return CharacterizationEnv::rows(self::$conn, $sql, $params);
    }

    protected function setUp(): void
    {
        foreach (['reservation','serienr','batch_salg','batch_kob','stocklog','lagerstatus','ordrelinjer','ordrer','variant_varer','varer'] as $table) {
            $this->rows('DELETE FROM ' . $table);
        }
        $this->rows("DELETE FROM grupper WHERE art='API' OR (art='VG' AND kodenr=91882)");
        $this->rows("DELETE FROM settings WHERE var_name='confirmStockChange'");
        $this->rows("UPDATE grupper SET box1='01',box2='2026',box3='12',box4='2026' WHERE art='RA' AND kodenr=$1", [self::$year]);
        $this->rows("INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2,box3,box4,box8,box9) VALUES('VG',91882,$1,'','','4000','1000','on','')", [self::$year]);
        $this->rows("INSERT INTO grupper(art,kodenr,box4,box5,box6) VALUES('API',1,'http://127.0.0.1/receipt-stub','','')");
        $product = (int)$this->rows("INSERT INTO varer(varenr,beskrivelse,gruppe,beholdning,kostpris,salgspris,serienr,delvare,has_due_date)
            VALUES('T4-RECEIPT','Receipt item',91882,4,12.5,20,'','',false) RETURNING id")[0]['id'];
        $order = (int)$this->rows("INSERT INTO ordrer(art,status,ordrenr,ordredate,levdate,ref) VALUES('KO',1,91882,'2026-10-01','2026-10-06','chartest') RETURNING id")[0]['id'];
        $line = (int)$this->rows("INSERT INTO ordrelinjer(ordre_id,posnr,varenr,vare_id,antal,leveres,leveret,serienr,variant_id,lager,kred_linje_id)
            VALUES($1,1,'T4-RECEIPT',$2,10,6,0,'',0,2,0) RETURNING id", [$order,$product])[0]['id'];
        $this->fixture = ['product' => $product, 'order' => $order, 'line' => $line];
        $dir = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
        if (!is_dir($dir)) {
            mkdir($dir,0770,true);
        }
    }

    public function testWebReceiptAppendsRenderedModificationTrail(): void
    {
        $before = ModificationTrail::read(CharacterizationEnv::testDb());
        $this->receive();
        foreach (['UPDATE ordrelinjer', 'INSERT INTO batch_kob', 'UPDATE varer', 'INSERT INTO lagerstatus'] as $sql) {
            $added = ModificationTrail::assertWrite(CharacterizationEnv::testDb(), $before, $sql . '\b');
            self::assertStringNotContainsString('$1', $added, 'Bound parameters must be rendered for replay');
        }
    }

    private function receive(string $mode = 'page', bool $rollback = false): array
    {
        $r = CharacterizationEnv::runChild(__DIR__ . '/../support/run_modtag_page.php',
            [json_encode(['order_id' => $this->fixture['order'], 'mode' => $mode, 'rollback' => $rollback], JSON_THROW_ON_ERROR), self::$session]);
        self::assertSame(0,$r['exit'],$r['stderr'] . $r['stdout']);
        $out = json_decode($r['stdout'],true,512,JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('Fatal error',$out['html'],$r['stderr']);
        return $out;
    }

    /** @return array<string,list<array>> Stable business rows, excluding clock-dependent log/modtime fields. */
    private function snapshot(): array
    {
        $result = [];
        foreach (['ordrelinjer' => 'id,ordre_id,leveres,bogf_konto',
            'batch_kob' => 'vare_id,variant_id,linje_id,ordre_id,kobsdate,antal,rest,lager,due_date,batch_no',
            'lagerstatus' => 'vare_id,variant_id,lager,beholdning', 'varer' => 'id,beholdning',
            'variant_varer' => 'id,variant_beholdning', 'reservation' => '*', 'serienr' => '*',
            'stocklog' => 'item_id,username,initials,correction,reason', 'batch_salg' => '*',
            'transaktioner' => '*', 'openpost' => '*', 'kontoplan' => '*'] as $table => $columns) {
            $result[$table] = $this->rows("SELECT $columns FROM $table ORDER BY 1");
        }
        return $result;
    }

    public static function receiptCases(): array
    {
        return array_map(static fn($name) => [$name], array_combine(
            ['stock warehouse 2','two partial receipts','non-stock','batch reservation','due-date','missing batch','missing serials','missing delivery','outside year','stocklog setting','variant'],
            ['stock','partial','nonstock','reservation','due','batch_missing','serial_missing','date_missing','outside','log','variant']));
    }

    #[DataProvider('receiptCases')]
    public function testLegacyReceiptRowsAndAlerts(string $case): void
    {
        $f = $this->fixture;
        if (in_array($case,['reservation','batch_missing'],true)) {
            $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=91882");
        }
        if ($case === 'reservation') {
            $this->rows("UPDATE ordrelinjer SET batch_batch_no='0' WHERE id=$1", [$f['line']]);
            $this->rows('INSERT INTO reservation(linje_id,batch_kob_id,batch_salg_id,vare_id,antal,lager) VALUES($1,0,99,$2,2,2)', [$f['line'],$f['product']]);
        } elseif ($case === 'due') {
            $this->rows('UPDATE varer SET has_due_date=true WHERE id=$1',[$f['product']]);
            $this->rows("UPDATE ordrelinjer SET batch_due_date='2026-12-01',batch_batch_no='0' WHERE id=$1",[$f['line']]);
        } elseif ($case === 'serial_missing') {
            $this->rows("UPDATE ordrelinjer SET serienr='on' WHERE id=$1",[$f['line']]);
        } elseif ($case === 'date_missing') {
            $this->rows('UPDATE ordrer SET levdate=NULL WHERE id=$1',[$f['order']]);
        } elseif ($case === 'outside') {
            $this->rows("UPDATE ordrer SET levdate='2027-01-01' WHERE id=$1",[$f['order']]);
        } elseif ($case === 'nonstock') {
            $this->rows("UPDATE grupper SET box8='' WHERE art='VG' AND kodenr=91882");
        } elseif ($case === 'log') {
            $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('confirmStockChange','items','off')");
        } elseif ($case === 'variant') {
            $v = $this->rows("INSERT INTO variant_varer(vare_id,variant_beholdning,variant_stregkode) VALUES($1,2,'EAN5701234567890') RETURNING id",[$f['product']])[0]['id'];
            $this->rows('UPDATE ordrelinjer SET variant_id=$1 WHERE id=$2',[$v,$f['line']]);
        } elseif ($case === 'partial') {
            $this->rows('UPDATE ordrelinjer SET leveres=2 WHERE id=$1',[$f['line']]);
        }
        $before = $this->snapshot();
        $out = $this->receive();
        $messages = ['batch_missing' => 'Batchoplysninger ikke udfyldt', 'serial_missing' => 'Serienumre ikke udfyldt',
            'date_missing' => 'Leveringsdato ikke udfyldt', 'outside' => 'Leveringsdato udenfor regnskabs&aring;r'];
        if (isset($messages[$case])) {
            self::assertStringContainsString("fejltekst('" . $messages[$case] . "')",$out['html']);
            self::assertSame($before,$this->snapshot());
            self::assertSame([], $out['calls']);
            self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
            return;
        }
        if ($case === 'partial') {
            self::assertSame('2.000',$this->rows('SELECT antal FROM batch_kob')[0]['antal']);
            $this->rows('UPDATE ordrelinjer SET leveres=4 WHERE id=$1',[$f['line']]);
            $this->receive();
        }
        $after = $this->snapshot();
        self::assertSame('0.000',$after['ordrelinjer'][0]['leveres']);
        self::assertEquals(6,array_sum(array_column($after['batch_kob'],'antal')));
        self::assertCount($case === 'partial' ? 2 : 1,$after['batch_kob']);
        foreach ($after['batch_kob'] as $batch) {
            self::assertSame((string)$f['line'],$batch['linje_id']);
            self::assertSame((string)$f['order'],$batch['ordre_id']);
            self::assertSame('2026-10-06',$batch['kobsdate']);
            self::assertSame('2',$batch['lager']);
            self::assertSame($case === 'due' ? '2026-12-01' : null,$batch['due_date']);
            self::assertSame(in_array($case,['due','reservation'],true) ? '0' : null,$batch['batch_no']);
        }
        self::assertSame($case === 'nonstock' ? 4.0 : 10.0,(float)$after['varer'][0]['beholdning']);
        self::assertCount($case === 'nonstock' ? 0 : 1,$after['lagerstatus']);
        if ($case !== 'nonstock') {
            self::assertSame('2',$after['lagerstatus'][0]['lager']);
            self::assertSame(6.0,(float)$after['lagerstatus'][0]['beholdning']);
        }
        self::assertSame($case === 'nonstock' ? null : ($case === 'reservation' ? '4.000' : ($case === 'partial' ? '2.000' : '6.000')),$after['batch_kob'][0]['rest']);
        if ($case === 'reservation') {
            self::assertSame($before['reservation'], $after['reservation']);
        }
        if ($case === 'variant') {
            self::assertSame(8.0,(float)$after['variant_varer'][0]['variant_beholdning']);
            self::assertSame($after['variant_varer'][0]['id'],$after['batch_kob'][0]['variant_id']);
            self::assertSame($after['variant_varer'][0]['id'],$after['lagerstatus'][0]['variant_id']);
        }
        if ($case === 'log') {
            self::assertSame([['item_id' => (string)$f['product'],'username' => 'chartest','initials' => '',
                'correction' => '6.000','reason' => 'indkøbsordre 91882']],$after['stocklog']);
        } else {
            self::assertSame([],$after['stocklog']);
        }
        self::assertSame($before['serienr'],$after['serienr']);
        self::assertSame($before['batch_salg'],$after['batch_salg']);
        foreach (['transaktioner','openpost','kontoplan'] as $table) {
            self::assertSame($before[$table],$after[$table]);
        }
        self::assertStringContainsString('URL=ordre.php?id=' . $f['order'],$out['html']);
        self::assertStringNotContainsString('<BODY onLoad=', $out['html']);
        self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
        if ($case === 'nonstock') {
            self::assertSame([],$out['calls']);
        } else {
            self::assertCount($case === 'variant' ? 1 : 3,$out['calls']);
            $stock = $out['calls'][array_key_last($out['calls'])]['params'];
            self::assertSame('2',(string)$stock['stockno']);
            self::assertSame($case === 'partial' ? 2.0 : 6.0,(float)$stock['stock']);
        }
    }

    public static function refusalCases(): array
    {
        return [
            'closed order' => ['order_closed','Hmmm - har du brugt browserens opdater eller tilbageknap???'],
            'missing delivery' => ['delivery_date_missing','Leveringsdato ikke udfyldt'],
            'date before order' => ['delivery_date_before_order','Leveringsdato er f&oslash;r ordredato'],
            'outside fiscal year' => ['outside_fiscal_year','Leveringsdato udenfor regnskabs&aring;r'],
            'missing serials' => ['serial_numbers_missing','Serienumre ikke udfyldt'],
            'missing batch data' => ['batch_info_missing','Batchoplysninger ikke udfyldt'],
            'unselected return serials' => ['serial_numbers_not_selected','Serienumre ikke valgt'],
            'unconfigured group after another line' => ['product_group_missing','Varenr MISSING (Pos nr: 2) er ikke tilnykttet nogen varegruppe, modtagelse afbrudt'],
            'reservation exceeds quantity' => ['reservations_exceed_receipt','Der er reserveret flere varer end der modtages - foretag proiritering'],
            'return without batch' => ['batch_not_selected','Batch ikke valgt'],
            'missing order' => ['order_missing',''],
        ];
    }

    public static function largeQuantityCases(): array
    {
        $cases = [];
        foreach (['123456789012.345' => '123456789012.344', '999999999999.999' => '999999999999.998'] as $quantity => $remainder) {
            foreach (['stock_new', 'stock_existing', 'nonstock', 'reservation_new', 'reservation_existing', 'reconcile', 'reconcile_same_order', 'return'] as $path) {
                foreach (['page', 'engine'] as $mode) {
                    $cases[$quantity . ' ' . $path . ' ' . $mode] = [$quantity, $remainder, $path, $mode];
                }
            }
        }
        return $cases;
    }

    #[DataProvider('largeQuantityCases')]
    public function testLargeDecimalReceiptIsPersistedExactly(string $quantity, string $remainder, string $path, string $mode): void
    {
        $f = $this->fixture;
        $isReturn = $path === 'return';
        $received = $isReturn ? '-0.001' : $quantity;
        $this->rows('UPDATE varer SET beholdning=0 WHERE id=$1', [$f['product']]);
        $this->rows('UPDATE ordrelinjer SET antal=$1,leveres=$2 WHERE id=$3', [$quantity,$received,$f['line']]);
        $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('confirmStockChange','items','on')");
        $variant = null;
        if (str_starts_with($path, 'stock_')) {
            $variant = $this->rows('INSERT INTO variant_varer(vare_id,variant_beholdning) VALUES($1,0) RETURNING id', [$f['product']])[0]['id'];
            $this->rows('UPDATE ordrelinjer SET variant_id=$1 WHERE id=$2', [$variant,$f['line']]);
            if ($path === 'stock_existing') {
                $this->rows('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,$2,2,0)', [$f['product'],$variant]);
            }
        } elseif ($path === 'nonstock') {
            $this->rows("UPDATE grupper SET box8='' WHERE art='VG' AND kodenr=91882");
        } elseif (str_starts_with($path, 'reservation_')) {
            $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=91882");
            $this->rows("UPDATE ordrelinjer SET batch_batch_no='0' WHERE id=$1", [$f['line']]);
            $batchId = 0;
            if ($path === 'reservation_existing') {
                $batchId = (int)$this->rows('INSERT INTO batch_kob(vare_id,linje_id,ordre_id,antal,rest,lager) VALUES($1,$2,$3,0,0,2) RETURNING id',
                    [$f['product'],$f['line'],$f['order']])[0]['id'];
            }
            $this->rows('INSERT INTO reservation(linje_id,batch_kob_id,batch_salg_id,vare_id,antal,lager) VALUES($1,$2,99,$3,0.001,2)',
                [$f['line'],$batchId,$f['product']]);
        } elseif ($path === 'reconcile') {
            $this->rows('INSERT INTO batch_kob(vare_id,linje_id,ordre_id,antal,rest,lager) VALUES($1,0,0,0,-0.001,2)', [$f['product']]);
        } elseif ($path === 'reconcile_same_order') {
            $this->rows('INSERT INTO batch_kob(vare_id,linje_id,ordre_id,antal,rest,lager) VALUES($1,0,$2,0,-0.001,2)', [$f['product'],$f['order']]);
        } elseif ($isReturn) {
            $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=91882");
            $this->rows('UPDATE ordrelinjer SET kred_linje_id=-1 WHERE id=$1', [$f['line']]);
            // A fractional return leaves a large calculated rest on the old batch's update path.
            $this->rows('INSERT INTO batch_kob(vare_id,linje_id,ordre_id,antal,rest,lager) VALUES($1,-1,0,$2,$2,2)', [$f['product'],$quantity]);
        }
        $before = $this->snapshot();
        $out = $this->receive($mode);
        if ($mode === 'engine') {
            self::assertNull($out['result']['reason'] ?? $out['result']['exception'] ?? null, json_encode($out['result']));
            self::assertSame($received, $out['result']['lines'][0]['received']);
            self::assertSame('', $out['engine_html']);
        } else {
            self::assertStringContainsString('URL=ordre.php?id=' . $f['order'], $out['html']);
        }
        self::assertSame(PGSQL_TRANSACTION_IDLE, $out['transaction']);
        self::assertSame('0.000', $this->rows('SELECT leveres FROM ordrelinjer WHERE id=$1', [$f['line']])[0]['leveres']);
        $batch = $this->rows('SELECT antal,rest FROM batch_kob WHERE linje_id=$1 ORDER BY id DESC', [$f['line']])[0];
        self::assertSame($path === 'reconcile' ? $remainder : $received, $batch['antal']);
        self::assertSame($path === 'nonstock' ? null : ($isReturn || $path === 'reconcile_same_order' ? '0.000' :
            (str_starts_with($path, 'reservation_') || $path === 'reconcile' ? $remainder : $quantity)), $batch['rest']);
        if ($isReturn) {
            self::assertSame($remainder, $this->rows('SELECT rest FROM batch_kob WHERE linje_id=-1')[0]['rest']);
        } elseif ($path === 'reconcile') {
            self::assertSame(['antal' => '0.001','rest' => '0.000'], $this->rows('SELECT antal,rest FROM batch_kob ORDER BY id')[0]);
        } elseif ($path === 'reconcile_same_order') {
            self::assertSame($remainder, $this->rows('SELECT rest FROM batch_kob WHERE linje_id=0')[0]['rest']);
        }
        self::assertSame($path === 'nonstock' ? '0.000' : $received, $this->rows('SELECT beholdning FROM varer WHERE id=$1', [$f['product']])[0]['beholdning']);
        if ($path !== 'nonstock') {
            self::assertSame($received, $this->rows('SELECT beholdning FROM lagerstatus WHERE vare_id=$1', [$f['product']])[0]['beholdning']);
        }
        if ($variant !== null) {
            self::assertSame($quantity, $this->rows('SELECT variant_beholdning FROM variant_varer WHERE id=$1', [$variant])[0]['variant_beholdning']);
        }
        self::assertSame($received, $this->rows('SELECT correction FROM stocklog WHERE item_id=$1', [$f['product']])[0]['correction']);
        $after = $this->snapshot();
        foreach (['transaktioner','openpost','kontoplan'] as $table) {
            self::assertSame($before[$table], $after[$table]);
        }
    }

    #[DataProvider('refusalCases')]
    public function testEveryRefusalReturnsTextAndCallerRollsBackAllRows(string $reason, string $message): void
    {
        $f = $this->fixture;
        switch ($reason) {
            case 'order_closed':
                $this->rows('UPDATE ordrer SET status=3 WHERE id=$1',[$f['order']]);
                break;
            case 'delivery_date_missing':
                $this->rows('UPDATE ordrer SET levdate=NULL WHERE id=$1',[$f['order']]);
                break;
            case 'delivery_date_before_order':
                $this->rows("UPDATE ordrer SET levdate='2026-09-30' WHERE id=$1",[$f['order']]);
                break;
            case 'outside_fiscal_year':
                $this->rows("UPDATE ordrer SET levdate='2027-01-01' WHERE id=$1",[$f['order']]);
                break;
            case 'serial_numbers_missing':
                $this->rows("UPDATE ordrelinjer SET serienr='on' WHERE id=$1",[$f['line']]);
                break;
            case 'batch_info_missing':
                $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=91882");
                break;
            case 'serial_numbers_not_selected':
                $this->rows("UPDATE ordrer SET art='KK' WHERE id=$1",[$f['order']]);
                $this->rows("UPDATE ordrelinjer SET leveres=-2,serienr='on' WHERE id=$1",[$f['line']]);
                break;
            case 'product_group_missing':
                $missing = $this->rows("INSERT INTO varer(varenr,gruppe,beholdning,has_due_date) VALUES('MISSING',91883,0,false) RETURNING id")[0]['id'];
                $this->rows("INSERT INTO ordrelinjer(ordre_id,posnr,varenr,vare_id,antal,leveres,lager) VALUES($1,2,'MISSING',$2,1,1,2)",[$f['order'],$missing]);
                $this->rows("INSERT INTO settings(var_name,var_grp,var_value) VALUES('confirmStockChange','items','off')");
                break;
            case 'reservations_exceed_receipt':
                $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=91882");
                $this->rows("UPDATE ordrelinjer SET batch_batch_no='0' WHERE id=$1",[$f['line']]);
                $this->rows('INSERT INTO reservation(linje_id,batch_kob_id,batch_salg_id,vare_id,antal,lager) VALUES($1,0,-99,$2,7,2)',[$f['line'],$f['product']]);
                break;
            case 'batch_not_selected':
                $this->rows("UPDATE grupper SET box9='on' WHERE art='VG' AND kodenr=91882");
                $this->rows('UPDATE ordrelinjer SET leveres=-2 WHERE id=$1',[$f['line']]);
                break;
            case 'order_missing':
                $this->rows('DELETE FROM ordrer WHERE id=$1',[$f['order']]);
                break;
        }
        $before = $this->snapshot();
        $out = $this->receive('engine');
        self::assertSame(['reason' => $reason,'message' => $message,'lines' => [],'sync' => [],
            'calls_before_commit' => 0,'open_transaction' => PGSQL_TRANSACTION_INTRANS],$out['result']);
        self::assertSame('',$out['engine_html']);
        self::assertSame([],$out['calls']);
        self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
        self::assertSame($before,$this->snapshot(),'Caller rollback must undo every line, stock, batch and log write');
    }

    public function testDeferredSyncRunsAfterCommitAndReceiptMatchesWebRows(): void
    {
        $out = $this->receive('engine');
        $f = $this->fixture;
        self::assertSame(['reason' => null,'message' => null,
            'lines' => [['linje_id' => $f['line'],'vare_id' => $f['product'],'variant_id' => 0,'lager' => 2,'received' => '6']],
            'sync' => [['vare_id' => $f['product'],'variant_id' => 0,'lager' => 2]],
            'calls_before_commit' => 0,'open_transaction' => PGSQL_TRANSACTION_INTRANS],$out['result']);
        self::assertSame('',$out['engine_html']);
        self::assertCount(3,$out['calls']);
        self::assertSame([PGSQL_TRANSACTION_IDLE],array_values(array_unique(array_column($out['calls'],'transaction'))));
        self::assertSame('6.000',$this->rows('SELECT antal FROM batch_kob')[0]['antal']);
        self::assertSame('10.000',$this->rows('SELECT beholdning FROM varer')[0]['beholdning']);
        self::assertSame('6.000',$this->rows('SELECT beholdning FROM lagerstatus')[0]['beholdning']);
    }

    public function testCallerCanRollBackSuccessfulDeferredReceiptWithoutShopCalls(): void
    {
        $before = $this->snapshot();
        $out = $this->receive('engine',true);
        self::assertNull($out['result']['reason']);
        self::assertSame(PGSQL_TRANSACTION_INTRANS,$out['result']['open_transaction']);
        self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
        self::assertSame([],$out['calls']);
        self::assertSame($before,$this->snapshot());
    }

    public function testEngineRequiresCallerTransaction(): void
    {
        $before = $this->snapshot();
        $out = $this->receive('no_transaction');
        self::assertSame(LogicException::class,$out['result']['exception']);
        self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
        self::assertSame([],$out['calls']);
        self::assertSame($before,$this->snapshot());
    }

    public function testDatabaseExceptionAfterStockWriteCanBeRolledBackWithoutOutput(): void
    {
        $this->rows("CREATE FUNCTION receipt_fail_batch() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'receipt fixture failure'; END $$");
        $this->rows('CREATE TRIGGER receipt_fail_batch BEFORE INSERT ON batch_kob FOR EACH ROW EXECUTE FUNCTION receipt_fail_batch()');
        try {
            $before = $this->snapshot();
            $out = $this->receive('engine');
            self::assertSame(RuntimeException::class,$out['result']['exception']);
            self::assertSame(PGSQL_TRANSACTION_INERROR,$out['result']['open_transaction']);
            self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
            self::assertSame('',$out['engine_html']);
            self::assertSame([],$out['calls']);
            self::assertSame($before,$this->snapshot());
        } finally {
            $this->rows('DROP TRIGGER receipt_fail_batch ON batch_kob');
            $this->rows('DROP FUNCTION receipt_fail_batch()');
        }
    }


    public function testMalformedPostingAccountFailsAndRollsBackInsteadOfBecomingZero(): void
    {
        $this->rows("UPDATE grupper SET box3='not-an-account' WHERE art='VG' AND kodenr=91882");
        $before = $this->snapshot();
        $out = $this->receive('engine');
        self::assertSame(RuntimeException::class,$out['result']['exception']);
        self::assertSame(PGSQL_TRANSACTION_INERROR,$out['result']['open_transaction']);
        self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
        self::assertSame('',$out['engine_html']);
        self::assertSame([],$out['calls']);
        self::assertSame($before,$this->snapshot());
    }

    public function testDueDateReadErrorThrowsAndLeavesRollbackToCaller(): void
    {
        $this->rows('ALTER TABLE varer RENAME COLUMN has_due_date TO receipt_test_due_date');
        try {
            $before = $this->snapshot();
            $out = $this->receive('engine');
            self::assertSame(RuntimeException::class,$out['result']['exception']);
            self::assertSame(PGSQL_TRANSACTION_INERROR,$out['result']['open_transaction']);
            self::assertSame(PGSQL_TRANSACTION_IDLE,$out['transaction']);
            self::assertSame('',$out['engine_html']);
            self::assertSame([],$out['calls']);
            self::assertSame($before,$this->snapshot());
        } finally {
            $this->rows('ALTER TABLE varer RENAME COLUMN receipt_test_due_date TO has_due_date');
        }
    }

    public static function stockInterleavings(): array
    {
        return ['same warehouse, initially absent' => [2], 'different warehouse of same item' => [3]];
    }

    #[DataProvider('stockInterleavings')]
    public function testReceiptWaitsForItemWriterAndPreservesBothStockEffects(int $otherWarehouse): void
    {
        $f = $this->fixture;
        $owner = CharacterizationEnv::connect(CharacterizationEnv::testDb());
        $process = null;
        $pipes = [];
        try {
            CharacterizationEnv::rows($owner,'BEGIN');
            CharacterizationEnv::rows($owner,"SELECT pg_advisory_xact_lock(hashtext('saldi:stock'),$1::integer)",[$f['product']]);
            CharacterizationEnv::rows($owner,'INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,0,$2,10)',[$f['product'],$otherWarehouse]);
            CharacterizationEnv::rows($owner,'UPDATE varer SET beholdning=beholdning+10 WHERE id=$1',[$f['product']]);
            $process = proc_open([PHP_BINARY,__DIR__ . '/../support/run_modtag_page.php',
                json_encode(['order_id' => $f['order'],'mode' => 'engine'],JSON_THROW_ON_ERROR),self::$session],
                [0 => ['pipe','r'],1 => ['pipe','w'],2 => ['pipe','w']],$pipes);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $deadline = microtime(true) + 5;
            do {
                $waiting = $this->rows("SELECT pid FROM pg_locks WHERE locktype='advisory' AND NOT granted AND objid=$1::oid
                    AND database=(SELECT oid FROM pg_database WHERE datname=current_database())",[$f['product']]);
                if ($waiting) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(1,$waiting,'Receipt must wait on the shared item lock before reading stock');
            self::assertSame([], $this->rows('SELECT * FROM batch_kob'), 'Waiting receipt must not write a batch');
            self::assertSame('6.000',$this->rows('SELECT leveres FROM ordrelinjer')[0]['leveres']);
            CharacterizationEnv::rows($owner,'COMMIT');
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0,proc_close($process),$stderr . $stdout);
            $process = null;
            $out = json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
            self::assertNull($out['result']['reason']);
            self::assertSame(20.0,(float)$this->rows('SELECT beholdning FROM varer')[0]['beholdning']);
            $warehouses = $this->rows('SELECT lager,beholdning FROM lagerstatus ORDER BY lager');
            self::assertSame($otherWarehouse === 2
                ? [['lager' => '2','beholdning' => '16.000']]
                : [['lager' => '2','beholdning' => '6.000'],['lager' => '3','beholdning' => '10.000']],$warehouses);
        } finally {
            if (pg_transaction_status($owner) !== PGSQL_TRANSACTION_IDLE) {
                CharacterizationEnv::rows($owner,'ROLLBACK');
            }
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($process);
            }
            pg_close($owner);
        }
    }

}
