<?php
// 20260921 CDX/LUI Execute production linjeopdat with temporary PostgreSQL tables.
// Requires an explicitly isolated SALDI_TEST_DSN; never uses application credentials.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$dsn = getenv('SALDI_TEST_DSN');
if (!$dsn) throw new RuntimeException('Set SALDI_TEST_DSN to an isolated PostgreSQL test database');
$GLOBALS['testdb'] = pg_connect($dsn);
if (!$GLOBALS['testdb']) throw new RuntimeException('Isolated PostgreSQL unavailable');
function db_select($sql, $trace) { return pg_query($GLOBALS['testdb'], $sql); }
function db_modify($sql, $trace) { if (!pg_query($GLOBALS['testdb'], $sql)) throw new RuntimeException('SQL failed'); return true; }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function sync_shop_vare($id, $variant, $warehouse) { $GLOBALS['syncCalls'][] = [$id,$variant,$warehouse]; }
function checkRow($sql, $expected) {
    $actual = pg_fetch_row(pg_query($GLOBALS['testdb'], $sql));
    if ($actual !== $expected) throw new RuntimeException('Unexpected database state: '.json_encode([$expected,$actual]));
}
$source = file_get_contents(__DIR__.'/../includes/ordrefunc.php');
$start = strpos($source, "\nfunction linjeopdat(");
$end = strpos($source, "\n} # endfunc linjeopdat", $start);
if ($start === false || $end === false) throw new RuntimeException('Production function boundaries missing');
eval(substr($source, $start, $end-$start+2));
pg_query($GLOBALS['testdb'], 'BEGIN');
try {
    pg_query($GLOBALS['testdb'], <<<'SQL'
CREATE TEMP TABLE grupper(art text,kodenr integer,fiscal_year integer,box1 text,box2 text,box3 text,box4 text,box8 text,box9 text);
CREATE TEMP TABLE ordrelinjer(id integer,leveret numeric,leveres numeric,bogf_konto integer,fast_db text,kostpris numeric);
CREATE TEMP TABLE varer(id integer,beholdning numeric,kostpris numeric,lukket text);
CREATE TEMP TABLE lagerstatus(id serial,vare_id integer,variant_id integer,lager integer,beholdning numeric);
CREATE TEMP TABLE batch_kob(id serial,vare_id integer,linje_id integer,ordre_id integer,antal numeric,rest numeric,pris numeric,lager integer,variant_id integer);
CREATE TEMP TABLE batch_salg(id serial,batch_kob_id integer,vare_id integer,linje_id integer,salgsdate date,ordre_id integer,antal numeric,pris numeric,lev_nr integer,lager integer,variant_id integer);
INSERT INTO grupper VALUES('VG',1,4,'','',4000,1100,'on','');
INSERT INTO varer VALUES(1,20,10,'');
INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES(1,0,1,20);
INSERT INTO batch_kob(vare_id,linje_id,ordre_id,antal,rest,pris,lager,variant_id) VALUES(1,99,99,20,20,10,1,0);
INSERT INTO ordrelinjer VALUES(1,NULL,2,NULL,'',10),(2,NULL,-2,NULL,'',10),(3,-1,-2,NULL,'',10);
SQL
    );
    $regnaar=4; $art='DO'; $levdate=$fakturadate='2025-08-22'; $lev_nr=1;
    linjeopdat(10,1,1,18,1,2,59.20,59.20,0,'',1,1,'',0,1100,0,1);
    checkRow('SELECT leveret::text,leveres::text FROM ordrelinjer WHERE id=1', ['2','0']);
    checkRow('SELECT v.beholdning::text,s.beholdning::text FROM varer v JOIN lagerstatus s ON s.vare_id=v.id', ['18','18']);
    checkRow('SELECT SUM(antal)::text FROM batch_salg WHERE ordre_id=10', ['2']);
    linjeopdat(10,1,1,16.75,1,1.25,59.20,59.20,0,'',1,1,'',0,1100,0,1);
    checkRow('SELECT leveret::text,leveres::text FROM ordrelinjer WHERE id=1', ['3.25','0']);
    checkRow('SELECT SUM(antal)::text FROM batch_salg WHERE ordre_id=10', ['3.25']);
    $art='DK';
    linjeopdat(11,1,2,18.75,1,-2,59.20,59.20,0,'',1,1,'',1,1100,0,1);
    checkRow('SELECT leveret::text,leveres::text FROM ordrelinjer WHERE id=2', ['-2','0']);
    checkRow('SELECT SUM(antal)::text FROM batch_salg WHERE ordre_id=11', ['-2']);
    checkRow('SELECT v.beholdning::text,s.beholdning::text FROM varer v JOIN lagerstatus s ON s.vare_id=v.id', ['18.75','18.75']);
    linjeopdat(12,1,3,20.75,1,-2,59.20,59.20,0,'',1,1,'',1,1100,0,1);
    checkRow('SELECT leveret::text,leveres::text FROM ordrelinjer WHERE id=3', ['-3','0']);
    if (count($GLOBALS['syncCalls'])!==4) throw new RuntimeException('Missing delivery sync boundary');
    echo "PASS: production stocked delivery and credit return; NULL initialization, signed accumulation, fractional repeat, batch/warehouse parity.\n";
} finally { pg_query($GLOBALS['testdb'], 'ROLLBACK'); }
