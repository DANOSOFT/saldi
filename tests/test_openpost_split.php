<?php
// 20260921 CDX/LH Actual controller, invoice split and settlement with PostgreSQL rollback controls.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message) { throw new RuntimeException($message); });
$dsn=getenv('SALDI_CHAR_PG_DSN') ?: getenv('SALDI_TEST_DSN');
if (!$dsn) throw new RuntimeException('Set SALDI_CHAR_PG_DSN to an isolated PostgreSQL fixture');
$connection=pg_connect($dsn);$db='split_test';$db_type='pgsql';$brugernavn='split-test';$db_modify_fejl=false;$db_transaktion_depth=0;$webservice=false;
function get_relative(){return '/tmp/';}function db_log_append($path,$data,$mode='a'){}
function db_select($sql,$where=''){return pg_query($GLOBALS['connection'],$sql);}
function db_fetch_array($r){return pg_fetch_assoc($r);}
function db_modify($sql,$where=''){
 pg_send_query($GLOBALS['connection'],$sql);$r=pg_get_result($GLOBALS['connection']);while(pg_get_result($GLOBALS['connection'])!==false){}
 if(pg_result_status($r)===PGSQL_FATAL_ERROR){$GLOBALS['db_modify_fejl']=true;return false;}return $r;
}
require_once __DIR__.'/../includes/db_query.php';
ob_start();require_once __DIR__.'/../includes/std_func.php';ob_end_clean();
require_once __DIR__.'/../includes/openpostSplit.php';
if (($argv[1] ?? '') === '--worker') {
    $schema=$argv[2];
    if (!preg_match('/^split_race_[a-z0-9_]+$/',$schema)) throw new RuntimeException('Invalid schema');
    db_select("SET search_path TO $schema");
    echo json_encode(saldiSaveOpenpostSplit(1,504,4,'101'));
    exit;
}

function checkSplit($ok,$message){if(!$ok)throw new RuntimeException($message);echo "PASS: $message\n";}
function scalarSplit($sql){return pg_fetch_row(db_select($sql))[0];}
function splitState(){return scalarSplit('SELECT json_agg(o ORDER BY id)::text FROM openpost o');}
db_select(<<<'SQL'
CREATE TEMP TABLE openpost(id serial PRIMARY KEY,konto_id int,konto_nr text,faktnr text,amount numeric(15,3),refnr int,beskrivelse text,udlignet text,transdate date,kladde_id int,udlign_id int,valuta text,valutakurs numeric,bilag_id int,projekt text,forfaldsdate date,udlign_date date);
CREATE TEMP TABLE transaktioner(id serial,amount numeric);
CREATE TEMP TABLE adresser(id integer PRIMARY KEY,art text);
INSERT INTO adresser VALUES(7,'D'),(9,'D');
SQL);
function resetSplit($amount=504,$currency='DKK',$rate=100){
 $GLOBALS['db_modify_fejl']=false;db_select("UPDATE adresser SET art='D'");db_select('TRUNCATE openpost RESTART IDENTITY');
 db_select("INSERT INTO openpost VALUES(1,7,'1007','101',$amount,88,'Customer''s invoice','0','2025-01-04',11,0,'$currency',$rate,33,'p1','2025-02-04',NULL),(2,7,'1007','101',-4,89,'payment-test B','0','2025-12-31',19,0,'DKK',100,34,'p1',NULL,NULL),(3,9,'1009','unrelated',999,90,'untouched','0','2025-01-01',12,0,'DKK',100,35,'p2',NULL,NULL)");
 db_select("SELECT setval(pg_get_serial_sequence('openpost','id'),3)");
}
$source=file_get_contents(getenv('SALDI_SPLIT_SOURCE') ?: __DIR__.'/../includes/udlign_openpost.php');
$start=strpos($source,"if (isset(\$_POST['submit'])) {");$end=strpos($source,"\n} else {\n\t\$post_id[0]=\$_GET",$start);
$controller=substr($source,$start,$end-$start)."\n}";
function requestSplit($id,$expected,$new,$invoice='101',$submit='Opdater',$factor=1,$currency='DKK',$basis='DKK'){
 $_POST=['submit'=>$submit,'post_id'=>[0=>$id],'konto_id'=>[0=>7],'udlign'=>[1=>'on'],'kontrol'=>[1=>'on'],'dato_fra'=>'2025-01-01','dato_til'=>'2025-12-31','konto_fra'=>'1007','konto_til'=>'1007','retur'=>'report.php','returside'=>'report.php','layout'=>'','diff'=>0,'dkkdiff'=>0,'maxdiff'=>0,'diffkto'=>0,'diffdato'=>'31-12-2025','diffbilag'=>0,'faktnr'=>[0=>$invoice],'amount'=>[0=>$expected],'basisvaluta'=>$basis,'valuta'=>[0=>$currency],'omregningskurs'=>[0=>$factor],'belob'=>str_replace('.',',',(string)$new),'id'=>[0=>$id]];
 ob_start();eval($GLOBALS['controller']);$output=ob_get_clean();return [$submit,$output];
}
resetSplit();$before=splitState();
foreach([[2,-4,4],[2,-4,0],[2,-4,-5],[1,504,-4],[1,504,0],[1,504,505]] as $values){
 [$action,$output]=requestSplit(...array_merge($values,['changed-reference','Udlign']));
 checkSplit($action==='opdater' && strpos($output,'role="alert"')!==false && splitState()===$before,'Controller rejects sign change, zero or increase before reference/split/settlement writes');
}
foreach(['DG'=>504,'KG'=>-504] as $art=>$value){
 $a=strpos($source,"if ((\$art=='DG'",strpos($source,'$spantekst="Hvis der skrives'));$b=strpos($source,"\nif (\$diff!=0)",$a);
 $amount=[$value];ob_start();eval(substr($source,$a,$b-$a));$html=ob_get_clean();
 checkSplit(strpos($html,'type = "text"')!==false && strpos($html,'name=belob')!==false,"Actual $art invoice view exposes normal editable amount");
}
resetSplit();$unrelated=scalarSplit('SELECT row_to_json(o)::text FROM openpost o WHERE id=3');
[$action,$output]=requestSplit(1,504,4);
checkSplit($action==='opdater' && $output==='','Invoice reduction saves via controller and requires separate settlement');
checkSplit(scalarSplit("SELECT string_agg(amount::text,',' ORDER BY id) FROM openpost WHERE konto_id=7")==='4.000,-4.000,500.000','Invoice504 splits to invoice4 and remainder500 alongside unchanged payment-4');
checkSplit(scalarSplit("SELECT COUNT(*) FROM openpost n JOIN openpost o ON o.id=1 WHERE n.id=4 AND n.faktnr=o.faktnr AND n.refnr=o.refnr AND n.beskrivelse=o.beskrivelse AND n.transdate=o.transdate AND n.forfaldsdate=o.forfaldsdate AND n.kladde_id=o.kladde_id AND n.bilag_id=o.bilag_id AND n.projekt=o.projekt AND n.valuta=o.valuta AND n.valutakurs=o.valutakurs")==='1','Remainder preserves invoice provenance, date, currency, due date and apostrophes');
$after=splitState();requestSplit(1,504,4);checkSplit(splitState()===$after,'Repeated stale split cannot create another remainder');
checkSplit(scalarSplit('SELECT row_to_json(o)::text FROM openpost o WHERE id=3')===$unrelated,'Unrelated open item remains byte-for-byte unchanged');
$post_id=[1,2];$udlign=['on','on'];$postantal=1;$diffdato='31-12-2025';$dkkdiff=0;$diffkto=0;$diff=0;
$retur='report.php';$dato_fra='2025-01-01';$dato_til='2025-12-31';$konto_fra=$konto_til='1007';$layout='';
ob_start();include __DIR__.'/../includes/alignOpenpostIncludes/doAlign.php';ob_end_clean();
checkSplit(scalarSplit("SELECT COUNT(*) FROM openpost WHERE id IN(1,2) AND udlignet='1' AND udlign_id>0 AND udlign_date='2025-12-31'")==='2' && scalarSplit('SELECT SUM(amount) FROM openpost WHERE id IN(1,2)')==='0.000' && scalarSplit("SELECT COUNT(*) FROM openpost WHERE id=4 AND udlignet='0' AND udlign_id=0 AND udlign_date IS NULL AND amount=500")==='1','Actual Udlign closes4/-4 at latest date;500 claim stays open');
checkSplit(scalarSplit('SELECT COUNT(*) FROM transaktioner')==='0','Split and zero-difference settlement do not rewrite general ledger');
$after=splitState();requestSplit(1,4,2);checkSplit(splitState()===$after,'Already settled item cannot be split');
// Future invoice-number payments must find the remaining claim automatically.
$matchingSource=file_get_contents(__DIR__.'/../includes/genberegn.php');
$a=strpos($matchingSource,"if (!function_exists('equalizeMatchingRecords'))");
eval(substr($matchingSource,$a,strpos($matchingSource,'?>',$a)-$a));
$baseCurrency='DKK';
db_select("INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,udlignet,udlign_id,transdate,valuta) VALUES(7,'1007','101',-500,'0',0,'2026-01-15','DKK')");
equalizeMatchingRecords();
checkSplit(scalarSplit("SELECT COUNT(*) FROM openpost WHERE amount IN(500,-500) AND konto_id=7 AND udlignet='1' AND udlign_id>0 AND udlign_date='2026-01-15'")==='2','Actual automatic matcher closes invoice remainder against later correctly referenced payment');
// Execute the report's actual due-date grouping branch for the remaining invoice.
resetSplit();requestSplit(1,504,4);
$r=db_fetch_array(db_select('SELECT * FROM openpost WHERE id=4'));$faktnr=[];$f=0;
$agingSource=file_get_contents(__DIR__.'/../includes/openpost.php');
$a=strpos($agingSource,"if (\$r['faktnr'] && !in_array(\$r['faktnr'],\$faktnr))");
$b=strpos($agingSource,'$oid=$r[',$a);
eval(substr($agingSource,$a,$b-$a));
checkSplit($forfaldsdag==='2025-02-04','Actual aged-open-item report retains invoice due date rather than treating remainder as unallocated payment');
foreach (['D'=>-504,'K'=>504] as $type=>$signedAmount) {
    resetSplit($signedAmount);db_select("UPDATE adresser SET art='$type' WHERE id=7");
    requestSplit(1,$signedAmount,$signedAmount>0 ? 4 : -4);
    checkSplit(scalarSplit("SELECT faktnr FROM openpost WHERE id=4")==='' && scalarSplit('SELECT SUM(amount) FROM openpost WHERE id IN(1,4)')===($signedAmount>0?'504.000':'-504.000'),'Payment surplus keeps legacy blank invoice reference and conserved amount');
}
resetSplit();db_select("DELETE FROM adresser WHERE id=7");$before=splitState();
requestSplit(1,504,4);
checkSplit(splitState()===$before,'Missing account type fails before any split or reference mutation');
db_select("INSERT INTO adresser VALUES(7,'D')");
resetSplit(-504);db_select("UPDATE adresser SET art='K' WHERE id=7");requestSplit(1,-504,-4);checkSplit(scalarSplit("SELECT string_agg(amount::text,',' ORDER BY id) FROM openpost WHERE id IN(1,4)")==='-4.000,-500.000','Creditor invoice reduction preserves signed total');
checkSplit(scalarSplit("SELECT faktnr FROM openpost WHERE id=4")==='101','Creditor invoice remainder retains original invoice number');
resetSplit(100,'EUR',745);requestSplit(1,745,74.5,'101','Opdater',7.45,'EUR');checkSplit(scalarSplit("SELECT string_agg(amount::text||':'||valuta||':'||valutakurs,',' ORDER BY id) FROM openpost WHERE id IN(1,4)")==='10.000:EUR:745,90.000:EUR:745','Foreign display conversion preserves source total and exchange rate');
resetSplit(504.001);requestSplit(1,504,4);checkSplit(scalarSplit('SELECT SUM(amount) FROM openpost WHERE id IN(1,4)')==='504.001','Historical thousandth is conserved in remainder');
// Default PHP precision14 must not truncate a valid NUMERIC(15,3) amount in SQL.
$oldPrecision=ini_get('precision');ini_set('precision','14');
foreach (['','-'] as $sign) {
    resetSplit($sign.'999999999999.990');
    [$action,$output]=requestSplit(1,$sign.'999999999999.99',$sign.'999999999999.98');
    checkSplit($output==='' && scalarSplit("SELECT string_agg(amount::text,',' ORDER BY id) FROM openpost WHERE id IN(1,4)")===$sign.'999999999999.980,'.$sign.'0.010','Large signed cent reduction persists exact amount and exact one-cent remainder');
    $before=splitState();
    [$action,$output]=requestSplit(1,$sign.'999999999999.98',$sign.'999999999999.99','changed-reference','Udlign');
    checkSplit($action==='opdater' && $output!=='' && splitState()===$before,'Large signed one-cent increase rejects with exact state preservation');
}
ini_set('precision',$oldPrecision);
resetSplit();db_select('ALTER TABLE openpost ADD CONSTRAINT reject_late_update CHECK(id<>1 OR amount<>4)');$before=splitState();
[$action,$output]=requestSplit(1,504,4,'new-reference','Udlign');checkSplit($action==='opdater' && splitState()===$before && strpos($output,'ingen ændringer')!==false,'Failure after remainder insert rolls back original, reference and new remainder');
db_select('ALTER TABLE openpost DROP CONSTRAINT reject_late_update');
resetSplit();$before=splitState();transaktion('begin');$result=saldiSaveOpenpostSplit(1,504,4,'101');transaktion('rollback');checkSplit($result['ok'] && splitState()===$before,'Nested split rolls back with caller transaction');

// Execute actual cross-currency display calculations before the POST regression.
$a=strpos($source, '$dagskurs=$r2[');
$b=strpos($source, "\n\t} elseif (\$basisvaluta=='DKK')",$a);
$r2=['kurs'=>750];$amount=[100];$valutakurs=[700];$valuta=['USD'];$beskrivelse=['invoice'];$dkkamount=[700];
eval(substr($source,$a,$b-$a));
resetSplit(100,'USD',700);$before=splitState();
[$action,$output]=requestSplit(1,afrund($amount[0],2),afrund($amount[0],2),'101','Opdater',$omregningskurs[0],'USD','EUR');
checkSplit($output==='' && splitState()===$before,'Actual foreign-to-foreign display factor allows unchanged form submission');
checkSplit($dkkamount[0]===700,'Cross-currency display retains the original source DKK amount');
requestSplit(1,afrund($amount[0],2),46.67,'101','Opdater',$omregningskurs[0],'USD','EUR');
checkSplit(scalarSplit("SELECT string_agg(amount::text,',' ORDER BY id) FROM openpost WHERE id IN(1,4)")==='50.000,50.000','Cross-currency invoice reduction preserves USD total from EUR display');
db_select('ALTER TABLE openpost ADD COLUMN uxtid bigint, ADD COLUMN betal_id text, ADD COLUMN betalings_id text');
// Optional schema-era fields must be copied without interpreting their values.
db_select("UPDATE openpost SET uxtid=123456,betal_id='remittance',betalings_id=NULL WHERE id=1");
$result=saldiSaveOpenpostSplit(1,50,25,'101');
checkSplit($result['ok'] && scalarSplit("SELECT COUNT(*) FROM openpost WHERE id>4 AND uxtid=123456 AND betal_id='remittance' AND betalings_id IS NULL")==='1','Remainder preserves available remittance metadata and NULL values');

// Independent PHP/PG sessions submit the same form; row locking permits one split only.
$schema='split_race_'.getmypid().'_'.bin2hex(random_bytes(4));
db_select("CREATE SCHEMA $schema");
try {
    db_select("CREATE TABLE $schema.adresser AS SELECT * FROM adresser");
    db_select("CREATE TABLE $schema.openpost (LIKE openpost INCLUDING DEFAULTS)");
    db_select("CREATE SEQUENCE $schema.openpost_id START 2");
    db_select("ALTER TABLE $schema.openpost ALTER COLUMN id SET DEFAULT nextval('$schema.openpost_id')");
    db_select("INSERT INTO $schema.openpost SELECT * FROM openpost WHERE id=1");
    db_select("UPDATE $schema.openpost SET amount=504,udlignet='0',udlign_id=0,udlign_date=NULL");
    $workers=[];
    foreach ([1,2] as $index) {
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--worker',$schema],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $workers[]=[$process,$pipes];
    }
    $results=[];
    foreach ($workers as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0 || $err!=='') throw new RuntimeException($out.$err);
        $results[]=json_decode($out,true)['ok'];
    }
    sort($results);
    checkSplit($results===[false,true] && scalarSplit("SELECT COUNT(*)||':'||SUM(amount) FROM $schema.openpost")==='2:504.000','Independent concurrent requests create exactly one conserved split');
} finally {
    db_select("DROP TABLE $schema.adresser");db_select("DROP TABLE $schema.openpost");db_select("DROP SEQUENCE $schema.openpost_id");db_select("DROP SCHEMA $schema");
}
