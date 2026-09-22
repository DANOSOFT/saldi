<?php
// 20260921 CDX/LH Verify exact bundle acknowledgements and unchanged monetary return using real PostgreSQL.
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$dsn = getenv('SALDI_TEST_DSN') ?: getenv('SALDI_CHAR_PG_DSN');
if (!$dsn) { throw new RuntimeException('Set SALDI_TEST_DSN to an isolated PostgreSQL database'); }
$connection = pg_connect($dsn);
pg_query($connection, 'SET search_path TO pg_temp');
$db = 'bundle_2'; $db_type = 'pgsql'; $brugernavn = 'bundle-fixture'; $regnaar = 1;
$db_skriv_id = 2; $sqdb = 'master'; $webservice = true; $momssats = 25;
$afd = 0; $barcodeNew = ''; $folger = $formularsprog = $kundedisplay = $vis_saet = 0;
$status = 0; $tilfravalgNy = ''; $db_modify_fejl = false;
$work = sys_get_temp_dir() . '/bundle-' . bin2hex(random_bytes(6));
mkdir($work . '/api', 0700, true); mkdir($work . '/temp/' . $db, 0700, true);
chdir($work . '/api');
function get_relative() { return $GLOBALS['work'] . '/'; }
function chk4utf8($value) { return $value; }
$audits = [];
function db_log_append($path, $data, $mode = 'a') { $GLOBALS['audits'][] = $data; }
require_once(__DIR__ . '/../includes/db_query.php');
require_once(__DIR__ . '/../includes/std_func.php');
require_once(__DIR__ . '/../includes/shopOrderInput.php');
$source = file_get_contents(__DIR__ . '/../api/rest_api.php');
$start = strpos($source, 'function insert_shop_orderline(');
$end = strpos($source, "\nfunction fakturer_ordre(", $start);
eval(str_replace('__DIR__', var_export(dirname(__DIR__) . '/api', true), substr($source, $start, $end - $start)));
function bundleCheck($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS bundle: $message\n";
}
function bundleRows($sql) { return pg_fetch_all(pg_query($GLOBALS['connection'], $sql)); }
try {
    pg_query($connection, "CREATE TEMP TABLE ordrer(id int PRIMARY KEY,status int,momssats numeric,valutakurs numeric,art text,konto_id int,afd int)");
    pg_query($connection, "CREATE TEMP TABLE adresser(id int,gruppe int,rabatgruppe int)");
    pg_query($connection, "INSERT INTO ordrer VALUES(1,0,25,100,'DO',1,0)");
    pg_query($connection, "INSERT INTO adresser VALUES(1,1,0)");
    $text = 'varenr,varenr_alias,stregkode,beskrivelse,enhed,samlevare,specialtype,special_from_date,special_to_date,special_from_time,special_to_time,serienr,m_antal,publiceret';
    $numeric = 'salgspris,kostpris,campaign_cost,rabatgruppe,gruppe,dvrg,tier_price,special_price,beholdning,folgevare';
    $defs = [];
    foreach (explode(',', $text) as $name) { $defs[] = "$name text NOT NULL DEFAULT ''"; }
    foreach (explode(',', $numeric) as $name) { $defs[] = "$name numeric NOT NULL DEFAULT 0"; }
    pg_query($connection, 'CREATE TEMP TABLE varer(id int PRIMARY KEY,' . implode(',', $defs) . ')');
    pg_query($connection, "INSERT INTO varer(id,varenr,beskrivelse,enhed,gruppe,salgspris,kostpris,samlevare,beholdning) VALUES(1,'BUNDLE','Bundle','stk',1,100,0,'on',10),(2,'PART','Part','stk',1,100,20,'',10)");
    $defs = [];
    for ($i=1;$i<=11;$i++) { $defs[] = "box$i text NOT NULL DEFAULT ''"; }
    pg_query($connection, 'CREATE TEMP TABLE grupper(id serial,art text,kodenr int,fiscal_year int,' . implode(',', $defs) . ')');
    pg_query($connection, "INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2,box4,box7,box8,box9) VALUES('VG',1,1,'','','1000','','',''),('DG',1,1,'','','','','',''),('SM',1,1,'2100','25','','','',''),('DIV',3,1,'','0','','','','')");
    pg_query($connection, "CREATE TEMP TABLE kontoplan(kontonr int,regnskabsaar int,moms text); INSERT INTO kontoplan VALUES(1000,1,'S1')");
    pg_query($connection, "CREATE TEMP TABLE settings(var_name text,var_value text)");
    pg_query($connection, "CREATE TEMP TABLE variant_varer(id int,vare_id int,variant_type text,variant_stregkode text)");
    pg_query($connection, "CREATE TEMP TABLE styklister(indgaar_i int,vare_id int,antal numeric,posnr int); INSERT INTO styklister VALUES(1,2,2,1)");
    pg_query($connection, "CREATE TEMP TABLE rabat(vare int,debitor int,rabat numeric,rabatart text)");
    pg_query($connection, "CREATE TEMP TABLE varetilbud(vare_id int,ugedag int,startdag bigint,slutdag bigint,starttid text,sluttid text,salgspris numeric,kostpris numeric)");
    $text = 'varenr,enhed,beskrivelse,rabatart,projekt,kdo,serienr,samlevare,omvbet,lev_varenr,tilfravalg,barcode,momsfri';
    $numeric = 'ordre_id,vare_id,antal,rabat,procent,m_rabat,pris,vat_price,kostpris,momssats,posnr,folgevare,rabatgruppe,bogf_konto,vat_account,kred_linje_id,variant_id,leveres,saet,fast_db,lager';
    $defs = [];
    foreach (explode(',', $text) as $name) { $defs[] = "$name text DEFAULT ''"; }
    foreach (explode(',', $numeric) as $name) { $defs[] = "$name numeric DEFAULT 0"; }
    pg_query($connection, 'CREATE TEMP TABLE ordrelinjer(id serial PRIMARY KEY,' . implode(',', $defs) . ')');
    // A later same-order/same-item INSERT must not become the acknowledgement or receive the master update.
    pg_query($connection, <<<'SQL'
CREATE FUNCTION pg_temp.bundle_decoy() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
  IF NEW.vare_id=1 AND NEW.beskrivelse<>'decoy' THEN
    INSERT INTO ordrelinjer(ordre_id,vare_id,saet,samlevare,beskrivelse) VALUES(NEW.ordre_id,NEW.vare_id,NEW.saet,'decoy','decoy');
  END IF;
  RETURN NEW;
END $$;
CREATE TRIGGER bundle_decoy AFTER INSERT ON ordrelinjer FOR EACH ROW EXECUTE FUNCTION pg_temp.bundle_decoy();
SQL
    );
    $id = insert_shop_orderline('bundle-fixture',1,'','BUNDLE',2,'Bundle',100,'',0,1,'','','','');
    $row = bundleRows('SELECT id,vare_id,ordre_id,samlevare,saet FROM ordrelinjer WHERE id=' . (int)$id);
    bundleCheck((int)$id > 0 && count($row)===1 && $row[0]['vare_id']==='1' && $row[0]['ordre_id']==='1' && $row[0]['samlevare']==='on', 'API returns the exact bundle master line ID');
    bundleCheck(count(bundleRows("SELECT id FROM ordrelinjer WHERE beskrivelse<>'decoy'"))===2, 'bundle creates one component and one master');
    bundleCheck(bundleRows('SELECT antal FROM ordrelinjer WHERE vare_id=2')[0]['antal']==='4', 'component quantity preserves two bundles of two');
    bundleCheck((int)bundleRows('SELECT max(id) AS id FROM ordrelinjer')[0]['id']>(int)$id, 'later same-order insertion cannot replace the acknowledged identity');
    bundleCheck(bundleRows("SELECT samlevare FROM ordrelinjer WHERE beskrivelse='decoy'")[0]['samlevare']==='decoy', 'master metadata update targets only the returned identity');
    $previous = bundleRows('SELECT row_to_json(ordrelinjer)::text AS row FROM ordrelinjer WHERE id=' . (int)$id);
    $next = opret_saet(1,1,125,25,1,'on',1);
    bundleCheck($next>0 && $next!==$id && bundleRows('SELECT vare_id FROM ordrelinjer WHERE id=' . (int)$next)[0]['vare_id']==='1', 'repeated bundle returns its own new master identity');
    bundleCheck(bundleRows('SELECT row_to_json(ordrelinjer)::text AS row FROM ordrelinjer WHERE id=' . (int)$id)===$previous, 'previous master is preserved');
    $beforeZero=bundleRows('SELECT * FROM ordrelinjer ORDER BY id');
    bundleCheck(opret_saet(1,1,125,25,0,'on',1)===0 && bundleRows('SELECT * FROM ordrelinjer ORDER BY id')===$beforeZero, 'zero quantity has no positive acknowledgement or inserted rows');
    $captured = null;
    $sum = opret_ordrelinje(1,2,'PART',3,'',75,0,100,'PO','',100,0,'','','',0,10,0,'',1,__LINE__,$captured);
    bundleCheck((float)$sum===225.0 && $captured>0, 'optional identity capture preserves the existing monetary return: ' . json_encode([$sum,$captured]));
    $legacySum = opret_ordrelinje(1,2,'PART',3,'',75,0,100,'PO','',100,0,'','','',0,11,0,'',1,__LINE__);
    bundleCheck($legacySum===$sum, 'legacy callers still receive the monetary sum');
    bundleCheck(str_contains(json_encode($audits), 'RETURNING id'), 'exact identity uses audited db_modify INSERT RETURNING');
    bundleCheck(db_modify('UPDATE ordrer SET status=0 WHERE id=1', __FILE__ . ':' . __LINE__) === "0\tquery accepted", 'legacy db_modify result remains unchanged');
    // A component rejected before INSERT must never yield a positive bundle acknowledgement.
    pg_query($connection, "UPDATE ordrer SET status=4 WHERE id=1");
    $beforeRejected=bundleRows('SELECT * FROM ordrelinjer ORDER BY id');
    bundleCheck(opret_saet(1,1,125,25,1,'on',1)===0 && bundleRows('SELECT * FROM ordrelinjer ORDER BY id')===$beforeRejected, 'rejected component cannot acknowledge a partial or absent bundle');
} finally {
    foreach (glob($work . '/temp/' . $db . '/*') as $file) { unlink($file); }
    chdir(sys_get_temp_dir());
    rmdir($work . '/temp/' . $db); rmdir($work . '/temp'); rmdir($work . '/api'); rmdir($work);
}
