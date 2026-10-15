<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/sager/LoenTimerCharacterizationTest.test.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Cover web creation and rejection-copy modification logs and their guards.
// 20261007 CDX/LH Assert the restored tenant SQL trail on the web write path.

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
require_once __DIR__ . '/../support/CharacterizationEnv.php';
require_once __DIR__ . '/../../TestCredentials.php';

final class LoenTimerCharacterizationTest extends TestCase
{
    private static $conn;
    private static bool $ownsTenant = false;
    private static string $session;
    private static array $savedServer;

    public static function setUpBeforeClass(): void
    {
        if (CharacterizationEnv::unavailableReason() !== null) {
            return;
        }
        self::$savedServer = $_SERVER;
        $_SERVER['REQUEST_URI'] = '/saldi/index.php';
        CharacterizationEnv::assertSafeDatabases();
        self::$session = 't4b4a' . bin2hex(random_bytes(12));
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        $db = CharacterizationEnv::testDb();
        CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [$db]);
        CharacterizationEnv::rows($master, 'DROP DATABASE IF EXISTS ' . pg_escape_identifier($master, $db));
        CharacterizationEnv::rows($master, 'CREATE DATABASE ' . pg_escape_identifier($master, $db) . ' TEMPLATE ' . pg_escape_identifier($master, CharacterizationEnv::templateDb()));
        self::$ownsTenant = true;
        CharacterizationEnv::rows($master, 'DELETE FROM regnskab WHERE db=$1', [$db]);
        CharacterizationEnv::rows($master, "INSERT INTO regnskab(regnskab,dbhost,dbuser,db,version,sidst,brugerantal,posteringer,lukket,administrator)
            SELECT 'chartest',dbhost,dbuser,$1,version,sidst,brugerantal,1000000,'',administrator FROM regnskab WHERE db=$2", [$db, CharacterizationEnv::templateDb()]);
        CharacterizationEnv::rows($master, "INSERT INTO online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,language_id)
            VALUES($1,'loenchar',$2,$3,$4,1,$5,false,0)", [self::$session, $db, CharacterizationEnv::pgUser(), str_repeat('1', 50), (string)time()]);
        pg_close($master);
        self::$conn = CharacterizationEnv::connect($db);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            pg_close(self::$conn);
        }
        if (self::$ownsTenant) {
            $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
            CharacterizationEnv::rows($master, 'DELETE FROM online WHERE session_id=$1 AND db=$2', [self::$session, CharacterizationEnv::testDb()]);
            CharacterizationEnv::rows($master, 'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname=$1 AND pid<>pg_backend_pid()', [CharacterizationEnv::testDb()]);
            CharacterizationEnv::rows($master, 'DROP DATABASE ' . pg_escape_identifier($master, CharacterizationEnv::testDb()));
            CharacterizationEnv::rows($master, "DELETE FROM regnskab WHERE db=$1 AND regnskab='chartest'", [CharacterizationEnv::testDb()]);
            pg_close($master);
            $_SERVER = self::$savedServer;
        }
    }

    protected function setUp(): void
    {
        if (!self::$conn) {
            $this->markTestSkipped(CharacterizationEnv::unavailableReason());
        }
        require_once __DIR__ . '/../../../includes/sager/loenTimer.php';
        $GLOBALS['connection'] = self::$conn;
        foreach (['loen_enheder','loen','opgaver','sager','ansatte','brugere'] as $table) {
            CharacterizationEnv::rows(self::$conn, 'TRUNCATE ' . $table . ' RESTART IDENTITY CASCADE');
        }
        CharacterizationEnv::rows(self::$conn, "DELETE FROM grupper WHERE art IN ('loen','ANSAT')");
        CharacterizationEnv::rows(self::$conn, "INSERT INTO grupper(id,art,kodenr,box2) VALUES(94001,'ANSAT',1,'11111111')");
        CharacterizationEnv::rows(self::$conn, "INSERT INTO grupper(art,kodenr,box1,box2,box3,box4,box5,box6,box7,box8)
            VALUES('loen',1,$1,'150','120','2026-01-01',$2,$3,'110',$4)", ["0\t0", "6\t60", "3.50\t20", "60.25\t120.50"]);
        CharacterizationEnv::rows(self::$conn, "DELETE FROM settings WHERE var_grp='casePayment'");
        CharacterizationEnv::rows(self::$conn, "INSERT INTO settings(var_grp,var_name,var_description,var_value,user_id) VALUES('casePayment','hourTypes0','Special hours','210',0)");
        CharacterizationEnv::rows(self::$conn, "INSERT INTO ansatte(id,nummer,navn,loen,extraloen,trainee,startdate,gruppe,lukket) VALUES(1,101,'Test Employee',180,10,'','2026-04-01',94001,'')");
        CharacterizationEnv::rows(self::$conn, "INSERT INTO brugere(brugernavn,kode,ansat_id,rettigheder,regnskabsaar) VALUES('loenchar',$1,1,$2,1)", [password_hash(TestCredentials::password('loenchar'), PASSWORD_DEFAULT), str_repeat('1',50)]);
        CharacterizationEnv::rows(self::$conn, "INSERT INTO sager(id,sagsnr,beskrivelse,status,ref,udf_addr1) VALUES(1,'701','Test case','Montage','Case ref','Test address')");
        CharacterizationEnv::rows(self::$conn, "INSERT INTO opgaver(id,nr,assign_id,assign_to,beskrivelse,status) VALUES(1,1,1,'sager','Test task','Montage')");
    }

    /** @return iterable<string,array{string,string,string,string,string,string}> */
    public static function cases(): iterable
    {
        yield 'normal' => ['7,5','0','0','','', 'save'];
        yield 'overtime' => ['8','1','0,5','','', 'save'];
        yield 'hour type' => ['7,5','0','0','hourTypes0','', 'save'];
        yield 'trainee' => ['7,5','0','0','','on', 'save'];
        yield 'leader approval' => ['7,5','0','0','','', 'approve'];
        yield 'leader rejection' => ['7,5','0','0','','', 'reject'];
    }

    /** @return array{html:string,errors:string} */
    private function page(array $post, array $get = []): array
    {
        $base = tempnam(sys_get_temp_dir(), 'loenchar');
        $process = proc_open([PHP_BINARY, __DIR__ . '/../support/run_loen_page.php', json_encode(['get'=>$get + ['funktion'=>'ret_loen'], 'post'=>$post], JSON_THROW_ON_ERROR), self::$session],
            [0=>['file','/dev/null','r'], 1=>['file',$base,'w'], 2=>['file',$base . '.err','w']], $pipes);
        $exit = proc_close($process);
        $html = file_get_contents($base);
        $errors = file_get_contents($base . '.err');
        unlink($base);
        unlink($base . '.err');
        file_put_contents('/tmp/t4b4a-page-warnings.log', $errors, FILE_APPEND);
        self::assertSame(0, $exit, substr($errors, -4000));
        self::assertStringNotContainsString('query failed', $html);
        return ['html'=>$html,'errors'=>$errors];
    }

    /** Parse the actual successful form controls, including automatic hidden POST fields. @return array<string,mixed> */
    private function form(string $html): array
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($dom);
        $forms = $xpath->query('//form[@name="loen"]');
        self::assertSame(1, $forms->length, substr($html, -1500));
        $pairs = [];
        foreach ($xpath->query('.//input[@name] | .//select[@name] | .//textarea[@name]', $forms->item(0)) as $control) {
            $type = strtolower($control->getAttribute('type'));
            if (in_array($type, ['submit','button'], true) || (in_array($type, ['checkbox','radio'], true) && !$control->hasAttribute('checked'))) {
                continue;
            }
            if ($control->tagName === 'select') {
                $options = $xpath->query('.//option[@selected]', $control);
                if ($options->length === 0) {
                    $options = $xpath->query('.//option', $control);
                }
                $option = $options->item(0);
                $value = $option ? ($option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent) : '';
            } else {
                $value = $control->tagName === 'textarea' ? $control->textContent : $control->getAttribute('value');
            }
            $pairs[] = rawurlencode($control->getAttribute('name')) . '=' . rawurlencode($value);
        }
        parse_str(implode('&', $pairs), $post);
        return $post;
    }

    /** @return array<string,string|null> */
    private function row(int $id): array
    {
        $row = CharacterizationEnv::one(self::$conn, 'SELECT * FROM loen WHERE id=$1', [$id]);
        self::assertNotNull($row);
        foreach (['oprettet','afsluttet','godkendt','afvist','afregnet','udbetalt'] as $key) {
            if (is_numeric($row[$key]) && (int)$row[$key] > 0) {
                self::assertGreaterThan(time() - 120, (int)$row[$key], $key);
                self::assertLessThanOrEqual(time(), (int)$row[$key], $key);
                $row[$key] = '<stamp>';
            }
        }
        return $row;
    }

    /** @return string Current disposable tenant's legacy modification trail. */
    private function modificationLog(): string
    {
        $path = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb() . '/.ht_modify.log';
        clearstatcache(true, $path);
        return is_file($path) ? file_get_contents($path) : '';
    }

    /** @return void Assert one insert with the web actor, source and bound values. */
    private function assertBrowserInsertLogged(string $log, string $reason = ''): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        try {
            $tenant = CharacterizationEnv::one($master, 'SELECT id FROM regnskab WHERE db=$1', [CharacterizationEnv::testDb()]);
        } finally {
            pg_close($master);
        }
        self::assertGreaterThan(1, (int)$tenant['id'], 'The fixture must enable tenant modification logging');
        preg_match_all('/(-- loenchar [^\n]+: [^\n]*sager\/loenIncludes\/retLoen\.php linje \d+: '
            . (int)$tenant['id'] . '\n)(INSERT INTO loen \([^\n]+\) VALUES \([^\n]+\)[^\n]*;\n)/i', $log, $matches);
        self::assertCount(1, $matches[0], 'Creation and rejection-copy inserts must retain their modification trail');
        self::assertStringContainsString("'Employee''s work'", $matches[2][0]);
        self::assertStringNotContainsString('$1', $matches[2][0], 'The log must retain values, not unresolved bindings');
        if ($reason !== '') {
            self::assertStringContainsString("'" . $reason . "'", $matches[2][0]);
        }
    }

    #[DataProvider('cases')]
    public function testBrowserRoundTrip(string $hours, string $t50, string $t100, string $type, string $trainee, string $action): void
    {
        CharacterizationEnv::rows(self::$conn, 'UPDATE ansatte SET trainee=$1 WHERE id=1', [$trainee]);
        $post = ['gem'=>'Gem','id'=>'0','loen_art'=>'timer','listevalg'=>'0','loendato'=>'01-07-2026','sag_nr'=>'701','sag_id'=>'1','opg_id'=>'1','opg_nr'=>'1','loen_tekst'=>"Employee's work",
            'medarb_nr'=>['101'],'medarb_navn'=>['Test Employee'],'ansat_id'=>['1'],'loen_timer'=>[$hours],'loen_50pct'=>[$t50],'loen_100pct'=>[$t100],'loen_loen'=>['0'],'loen_fordeling'=>['100'],
            'loen_date'=>['01-07-2026'],'loen_km'=>['0'],'loen_mentor'=>['0'],'hourType'=>[$type],'a_stk'=>[]];
        $logBefore = $this->modificationLog();
        $html = $this->page($post)['html'];
        $this->assertBrowserInsertLogged(substr($this->modificationLog(), strlen($logBefore)));
        self::assertStringContainsString('document.forms[0].submit()', $html);
        $post = $this->form($html);
        self::assertSame('1', $post['gemt']);
        $html = $this->page($post)['html'];
        $post = $this->form($html);
        if ($type !== '') {
            // A new legacy insert omits hourtype; the browser selects it on the saved form.
            $post['hourType'][0] = $type;
            $post['gem'] = 'Gem';
            $post['gemt'] = '0';
            $html = $this->page($post)['html'];
            $post = $this->form($html);
            $html = $this->page($post)['html'];
            $post = $this->form($html);
        }
        $rows = ['saved'=>$this->row(1)];
        if ($action !== 'save') {
            unset($post['gem'], $post['gemt']);
            $post['afslut'] = 'Overfør';
            $html = $this->page($post)['html'];
            $post = $this->form($html);
            self::assertSame('1', $post['afs']);
            $html = $this->page($post)['html'];
            $post = $this->form($html);
            self::assertSame('2', $post['afs']);
            $html = $this->page($post)['html'];
            $post = $this->form($html);
            unset($post['afslut'], $post['afs']);
            $rows['submitted'] = $this->row(1);
            if ($action === 'approve') {
                $this->page($post + ['godkend'=>'Godkend']);
                $rows['approved'] = $this->row(1);
            } else {
                $logBefore = $this->modificationLog();
                $this->page($post + ['afvis'=>'Afvis','afvist_pga'=>'Please correct hours']);
                $this->assertBrowserInsertLogged(substr($this->modificationLog(), strlen($logBefore)), 'Please correct hours');
                $rows['rejected'] = $this->row(1);
                $rows['replacement'] = $this->row(2);
            }
        }
        $key = $action . '-' . ($type ?: ($trainee ?: ($t50 === '0' ? 'normal' : 'overtime')));
        $expected = json_decode(file_get_contents(__DIR__ . '/fixtures/' . $key . '.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($expected, $rows);
    }

    public function testModificationLogRetainsLegacyGuardAndLiteralValues(): void
    {
        require_once __DIR__ . '/../../../includes/db_query.php';
        $keys = ['db','sqdb','db_skriv_id','brugernavn'];
        $saved = array_intersect_key($GLOBALS, array_flip($keys));
        $cwd = getcwd();
        $requestUri = ifset($_SERVER, 'REQUEST_URI');
        $source = __FILE__ . ' linje ' . __LINE__;
        $logDirectory = CharacterizationEnv::repoRoot() . '/temp/' . CharacterizationEnv::testDb();
        if (!is_dir($logDirectory)) {
            mkdir($logDirectory, 0777, true);
        }
        chdir(CharacterizationEnv::repoRoot() . '/sager');
        $_SERVER['REQUEST_URI'] = '/saldi/sager/loen.php';
        $GLOBALS['db'] = CharacterizationEnv::testDb();
        $GLOBALS['brugernavn'] = 'loenchar';
        loen_timer_query('BEGIN');
        try {
            foreach ([null, 0, 1, 2] as $writeId) {
                $GLOBALS['db_skriv_id'] = $writeId;
                foreach (['tenant','master'] as $context) {
                    $GLOBALS['sqdb'] = $context === 'master' ? CharacterizationEnv::testDb() : CharacterizationEnv::masterDb();
                    $before = $this->modificationLog();
                    $text = "Quoted ' text; \\ path";
                    $id = loen_seddel_insert(['art'=>'timer','nummer'=>1,'tekst'=>$text,'hourtype'=>null], $source);
                    self::assertSame($text, $this->row($id)['tekst']);
                    $added = substr($this->modificationLog(), strlen($before));
                    if ($writeId === 2 && $context === 'tenant') {
                        self::assertStringContainsString(': ' . $source . ': 2' . "\n", $added);
                        self::assertStringContainsString('INSERT INTO loen (art,nummer,tekst,hourtype) VALUES ', $added);
                        self::assertStringContainsString(pg_escape_literal(self::$conn, $text), $added);
                        self::assertStringContainsString('NULL', $added);
                    } else {
                        self::assertSame('', $added, 'Keep db_modify logging disabled for master or db_skriv_id <= 1');
                    }
                }
            }
            $GLOBALS['sqdb'] = CharacterizationEnv::masterDb();
            $before = $this->modificationLog();
            $GLOBALS['db_skriv_id'] = 2;
            loen_seddel_insert(['art'=>'timer','nummer'=>1]);
            $added = substr($this->modificationLog(), strlen($before));
            self::assertStringContainsString(': ' . realpath(__DIR__ . '/../../../includes/sager/loenTimer.php') . ': 2' . "\n", $added);
            self::assertStringContainsString("INSERT INTO loen (art,nummer) VALUES ('timer','1') RETURNING id;\n", $added);
        } finally {
            loen_timer_query('ROLLBACK');
            foreach ($keys as $key) {
                if (array_key_exists($key, $saved)) {
                    $GLOBALS[$key] = $saved[$key];
                } else {
                    unset($GLOBALS[$key]);
                }
            }
            if ($requestUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $requestUri;
            }
            chdir($cwd);
        }
    }
    public function testRatesAreReadOnlyWithConfiguredEmptyAndMissingGroup(): void
    {
        foreach (['configured','empty','missing'] as $mode) {
            if ($mode === 'empty') {
                CharacterizationEnv::rows(self::$conn, "UPDATE grupper SET box8='' WHERE art='loen'");
            } elseif ($mode === 'missing') {
                CharacterizationEnv::rows(self::$conn, "DELETE FROM grupper WHERE art='loen'");
            }
            $before = CharacterizationEnv::rows(self::$conn, 'SELECT * FROM grupper ORDER BY id');
            $settings = CharacterizationEnv::rows(self::$conn, 'SELECT * FROM settings ORDER BY id');
            loen_timer_query('BEGIN READ ONLY');
            try {
                $rates = loen_rates();
            } finally {
                loen_timer_query('ROLLBACK');
            }
            self::assertSame($mode === 'configured' ? '60.25' : '59.18', $rates['overtime_50']);
            self::assertSame($mode === 'configured' ? '120.50' : '118.35', $rates['overtime_100']);
            self::assertSame(['label'=>'Special hours','rate'=>'210'], $rates['hour_types']['hourTypes0']);
            self::assertSame($before, CharacterizationEnv::rows(self::$conn, 'SELECT * FROM grupper ORDER BY id'));
            self::assertSame($settings, CharacterizationEnv::rows(self::$conn, 'SELECT * FROM settings ORDER BY id'));
        }
    }

    /** @return array<string,int|float|string|null> */
    private function fields(string $hours, string $t50, string $t100, ?string $type = null, bool $trainee = false): array
    {
        $amount = loen_timer_amount(['loen'=>'180'], $hours, $t50, $t100, $type, loen_rates());
        return ['art'=>'timer','kategori'=>0,'nummer'=>loen_next_number(),'hvem'=>'','sag_nr'=>701,'sag_id'=>1,
            'oprettet'=>(string)time(),'afsluttet'=>'','godkendt'=>'','afregnet'=>'','oprettet_af'=>'loenchar',
            'ansatte'=>'1','datoer'=>'','fordeling'=>(string)loen_fordeling(1,'2026-04-01','2026-07-01',$trainee),
            'loen'=>$amount['rate'],'timer'=>$hours,'t50pct'=>$t50,'t100pct'=>$t100,'mentor'=>'0.00','skur'=>'0|0',
            'sum'=>$amount['amount'],'loendate'=>'2026-07-01','tekst'=>"Employee's work",'korsel'=>'0|3.50|20',
            'opg_id'=>1,'opg_nr'=>1,'sag_ref'=>'Case ref','afvist'=>'','afvist_af'=>'','afvist_pga'=>'',
            'feriefra'=>'','ferietil'=>'','hourtype'=>$type ?: '', 'mentor_rate'=>0,'afsluttet_af'=>''];
    }

    public function testServiceFieldsMatchCharacterizedWebRowsInEveryColumn(): void
    {
        foreach ([['7.5','0.00','0.00',null,false,'save-normal'],['8','1','0.5',null,false,'save-overtime'],
            ['7.5','0.00','0.00','hourTypes0',false,'save-hourTypes0'],['7.5','0.00','0.00',null,true,'save-on']] as [$hours,$t50,$t100,$type,$trainee,$fixture]) {
            loen_timer_query('BEGIN');
            try {
                $id = loen_seddel_insert($this->fields($hours,$t50,$t100,$type,$trainee));
                $actual = $this->row($id);
                $expected = json_decode(file_get_contents(__DIR__ . '/fixtures/' . $fixture . '.json'), true, 512, JSON_THROW_ON_ERROR)['saved'];
                $expected['id'] = (string)$id;
                self::assertSame($expected, $actual);
            } finally {
                loen_timer_query('ROLLBACK');
            }
        }
    }

    public function testFallbackAmountMatchesBrowserWithoutRoundingThreeDecimalSum(): void
    {
        foreach (['empty','missing'] as $mode) {
            CharacterizationEnv::rows(self::$conn, 'TRUNCATE loen RESTART IDENTITY');
            if ($mode === 'empty') {
                CharacterizationEnv::rows(self::$conn, "UPDATE grupper SET box8='' WHERE art='loen'");
            } else {
                CharacterizationEnv::rows(self::$conn, "DELETE FROM grupper WHERE art='loen'");
            }
            $post = ['gem'=>'Gem','id'=>0,'loen_art'=>'timer','loendato'=>'01-07-2026','sag_nr'=>701,'sag_id'=>1,'opg_id'=>1,'opg_nr'=>1,'loen_tekst'=>"Employee's work",
                'medarb_nr'=>[101],'medarb_navn'=>['Test Employee'],'ansat_id'=>[1],'loen_timer'=>['8'],'loen_50pct'=>['1'],'loen_100pct'=>['0,5'],
                'loen_loen'=>['0'],'loen_date'=>['01-07-2026'],'loen_km'=>['0'],'loen_mentor'=>['0'],'a_stk'=>[]];
            $html = $this->page($post)['html'];
            $this->page($this->form($html));
            $web = $this->row(1);
            self::assertSame('1558.355', $web['sum']);
            loen_timer_query('BEGIN');
            try {
                $fields = $this->fields('8','1','0.5');
                // The missing-group web form packs absent km settings as empty strings.
                $fields['korsel'] = $mode === 'missing' ? '0||' : '0|3.50|20';
                $id = loen_seddel_insert($fields);
                $engine = $this->row($id);
                $web['id'] = (string)$id;
                $web['nummer'] = '2';
                self::assertSame($web, $engine);
            } finally {
                loen_timer_query('ROLLBACK');
            }
        }
    }

    public function testTraineeCutoffAndNonTraineeKeepLegacyPercentage(): void
    {
        self::assertEqualsWithDelta(49.999998, loen_fordeling(1,'2026-04-01','2026-10-01',true), 0.00000000001);
        self::assertSame(100.0, loen_fordeling(1,'2026-04-01','2026-10-02',true));
        self::assertSame(100.0, loen_fordeling(1,'2026-04-01','2026-07-01',false));
        self::assertEqualsWithDelta(49.999998, loen_fordeling(1,'2026-10-01','2027-04-01',true), 0.00000000001);
        self::assertSame(100.0, loen_fordeling(1,'2026-10-01','2027-04-02',true));
    }

    public function testValidationAndUnknownHourType(): void
    {
        self::assertSame(['date_missing','task_required','text_required','hours_invalid'], loen_timer_validate([]));
        $valid = ['date'=>'2026-07-01','task_id'=>1,'text'=>'Work','hours'=>'7.5'];
        self::assertSame([], loen_timer_validate($valid));
        foreach (['0','-1','24.001','NaN',[]] as $hours) {
            self::assertSame(['hours_invalid'], loen_timer_validate(array_replace($valid,['hours'=>$hours])));
        }
        self::assertSame(['date_missing'], loen_timer_validate(array_replace($valid,['date'=>'2026-02-30'])));
        self::assertSame(['date_missing'], loen_timer_validate(array_replace($valid,['date'=>'0000-01-01'])));
        self::assertSame(['hours_invalid'], loen_timer_validate($valid + ['overtime_50'=>'-1']));
        self::assertSame([], loen_timer_validate(array_replace($valid,['hours'=>'24','overtime_100'=>'0.5'])));
        self::assertSame(['rate'=>'175','amount'=>'1312.5'], loen_timer_amount(['loen'=>'180','current_rate'=>'175'],'7.5','0','0','retired-type',loen_rates()));
    }

    public function testPackedRowsRevealOnlyCallerPositionAndExactMembership(): void
    {
        CharacterizationEnv::rows(self::$conn, "INSERT INTO loen(art,nummer,loendate,ansatte,timer,t50pct,t100pct,hourtype,loen,sum)
            VALUES('timer',1,'2026-07-01',$1,$2,$3,$4,$5,$6,5000),('timer',2,'2026-07-02','11','24','0','0','other','999',999)",
            ["11\t1\t2","24\t7.5\t4","9\t1\t0","8\t0.5\t0","other\thourTypes0\tsecret","999\t180\t888"]);
        $rows = loen_employee_rows(1, ['date_from'=>'2026-07-01','date_to'=>'2026-07-02','limit'=>50]);
        self::assertCount(1,$rows);
        self::assertSame(3,$rows[0]['employee_count']);
        self::assertSame(1,$rows[0]['ansat_id']);
        self::assertSame('7.5',$rows[0]['timer']);
        self::assertSame('1',$rows[0]['t50pct']);
        self::assertSame('0.5',$rows[0]['t100pct']);
        self::assertSame('hourTypes0',$rows[0]['hourtype']);
        foreach (['ansatte','loen','sum','fordeling','korsel','mentor'] as $key) {
            self::assertArrayNotHasKey($key,$rows[0]);
        }
        self::assertSame([],loen_employee_rows(1,['date_from'=>'2026-07-02']));
        self::assertSame([],loen_employee_rows(1,['id'=>2]));
        self::assertSame([],loen_employee_rows(1,['sag_id'=>999]));
    }

    public function testAllocationRequiresTransactionAndRollbackKeepsCallerOwnership(): void
    {
        try {
            loen_next_number();
            self::fail('Allocation outside a transaction must throw');
        } catch (LogicException $error) {
            self::assertSame(PGSQL_TRANSACTION_IDLE,pg_transaction_status(self::$conn));
        }
        loen_timer_query('BEGIN');
        $number = loen_next_number();
        $id = loen_seddel_insert(['art'=>'timer','nummer'=>$number,'tekst'=>"Quoted ' text; SELECT 1",'hourtype'=>null]);
        self::assertSame("Quoted ' text; SELECT 1",CharacterizationEnv::one(self::$conn,'SELECT tekst FROM loen WHERE id=$1',[$id])['tekst']);
        self::assertSame(PGSQL_TRANSACTION_INTRANS,pg_transaction_status(self::$conn));
        loen_timer_query('ROLLBACK');
        self::assertSame([],CharacterizationEnv::rows(self::$conn,'SELECT * FROM loen'));
        loen_timer_query('BEGIN');
        try {
            self::assertSame($number,loen_next_number());
        } finally {
            loen_timer_query('ROLLBACK');
        }
    }

    public function testTenParallelAllocationsAndBrowserGetDistinctNumbers(): void
    {
        $barrier = tempnam(sys_get_temp_dir(),'loenbarrier');
        unlink($barrier);
        $workers = [];
        try {
            for ($i=0; $i<10; $i++) {
                $base = tempnam(sys_get_temp_dir(),'loenworker');
                $process = proc_open([PHP_BINARY,__DIR__.'/../support/run_loen_page.php',json_encode(['mode'=>'number','barrier'=>$barrier],JSON_THROW_ON_ERROR)],
                    [0=>['file','/dev/null','r'],1=>['file',$base,'w'],2=>['file',$base.'.err','w']],$pipes);
                $workers[] = [$process,$base];
            }
            touch($barrier);
            $post = ['gem'=>'Gem','id'=>0,'loen_art'=>'timer','loendato'=>'01-07-2026','sag_nr'=>701,'opg_id'=>1,'opg_nr'=>1,'loen_tekst'=>'Concurrent browser',
                'medarb_nr'=>[101],'medarb_navn'=>['Test Employee'],'ansat_id'=>[1],'loen_timer'=>['7,5'],'loen_50pct'=>['0'],'loen_100pct'=>['0'],'loen_loen'=>['0'],
                'loen_date'=>['01-07-2026'],'loen_km'=>['0'],'loen_mentor'=>['0'],'a_stk'=>[]];
            $this->page($post);
            $numbers = [];
            foreach ($workers as [$process,$base]) {
                self::assertSame(0,proc_close($process),file_get_contents($base.'.err'));
                $numbers[] = json_decode(file_get_contents($base),true,512,JSON_THROW_ON_ERROR)['number'];
            }
            self::assertCount(10,array_unique($numbers));
            $all = array_map('intval',array_column(CharacterizationEnv::rows(self::$conn,'SELECT nummer FROM loen ORDER BY nummer'),'nummer'));
            self::assertSame(range(1,11),$all);
        } finally {
            foreach ($workers as [$process,$base]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                }
                unlink($base);
                unlink($base.'.err');
            }
            unlink($barrier);
        }
    }

    public function testBrowserInsertBindsDecodedAuthenticatedUsername(): void
    {
        $master = CharacterizationEnv::connect(CharacterizationEnv::masterDb());
        CharacterizationEnv::rows(self::$conn, "UPDATE brugere SET brugernavn=$1 WHERE brugernavn='loenchar'", ["O'Brien"]);
        CharacterizationEnv::rows($master,'UPDATE online SET brugernavn=$1 WHERE session_id=$2 AND db=$3', ["O'Brien",self::$session,CharacterizationEnv::testDb()]);
        try {
            $post = ['gem'=>'Gem','id'=>0,'loen_art'=>'timer','loendato'=>'01-07-2026','sag_nr'=>701,'opg_id'=>1,'opg_nr'=>1,'loen_tekst'=>"Employee's work",
                'medarb_nr'=>[101],'medarb_navn'=>['Test Employee'],'ansat_id'=>[1],'loen_timer'=>['7,5'],'loen_50pct'=>['0'],'loen_100pct'=>['0'],'loen_loen'=>['0'],
                'loen_date'=>['01-07-2026'],'loen_km'=>['0'],'loen_mentor'=>['0'],'a_stk'=>[]];
            $html = $this->page($post)['html'];
            $this->page($this->form($html));
            $row = $this->row(1);
            self::assertSame("O'Brien",$row['oprettet_af']);
            self::assertSame("Employee's work",$row['tekst']);
        } finally {
            CharacterizationEnv::rows($master,"UPDATE online SET brugernavn='loenchar' WHERE session_id=$1 AND db=$2", [self::$session,CharacterizationEnv::testDb()]);
            pg_close($master);
        }
    }

}
