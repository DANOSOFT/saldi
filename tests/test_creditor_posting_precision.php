<?php
// 20260921 CDX/LH Exercise actual creditor entry VAT and posting with real PostgreSQL cents.
error_reporting(E_ALL);
set_error_handler(function($severity,$message){throw new RuntimeException($message);});
if (!getenv('SALDI_TEST_DSN')) { exit("SKIP: set SALDI_TEST_DSN to an isolated PostgreSQL fixture.\n"); }
$c=pg_connect(getenv('SALDI_TEST_DSN'));$GLOBALS['purchaseConnection']=$c;
require_once __DIR__ . '/../includes/creditorPostingTotals.php';
function db_select($sql,$where=''){return pg_query($GLOBALS['purchaseConnection'],$sql);}
function db_modify($sql,$where=''){return db_select($sql);}
function db_fetch_array($result){return pg_fetch_assoc($result);}
function findtekst($number,$language){return 'Fixture account validation';}
function transaktion($action){return db_select($action);}
$box=implode(',',array_map(fn($n)=>"box$n text DEFAULT ''",range(1,6)));
db_select("CREATE TEMP TABLE grupper(art text,kodenr int,fiscal_year int,$box);
CREATE TEMP TABLE ordrer(id int,art text,konto_id int,kontonr text,firmanavn text,modtagelse int,fakturadate date,fakturanr text,ordrenr int,projekt text,valuta text,valutakurs numeric,moms numeric(15,3),momssats numeric,sum numeric(15,3),omvbet text,ref text,status int);
CREATE TEMP TABLE ordrelinjer(id int,ordre_id int,vare_id int,bogf_konto int,projekt text,posnr int,pris numeric,antal numeric,rabat numeric,omvbet text,momsfri text,varenr text);
CREATE TEMP TABLE adresser(id int,gruppe int);
CREATE TEMP TABLE ansatte(id int,navn text,afd int);
CREATE TEMP TABLE kontoplan(id int,kontonr int,regnskabsaar int,lukket text);
CREATE TEMP TABLE openpost(id serial,konto_id int,konto_nr text,faktnr text,amount numeric(15,3),beskrivelse text,udlignet text,transdate date,kladde_id int,refnr int,valuta text,valutakurs numeric,projekt text);
CREATE TEMP TABLE transaktioner(id serial,bilag text,transdate date,beskrivelse text,kontonr int,faktura text,debet numeric(15,3),kredit numeric(15,3),kladde_id int,afd int,logdate date,logtime text,projekt text,ansat int,ordre_id int);
INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2) VALUES('KG',1,4,'K1','68000'),('KM',1,4,'66200','25'),('RB',1,0,'','');
INSERT INTO adresser VALUES(1,1);INSERT INTO ansatte VALUES(1,'Fixture employee',0);
INSERT INTO kontoplan VALUES(1,68000,4,'');
INSERT INTO ordrer VALUES(1,'KO',1,'41001','Synthetic supplier',1,CURRENT_DATE-3,'PF-REPRO',1,'PROJECT-H','DKK',100,0,25,261.25,'','Fixture employee',2);
INSERT INTO ordrelinjer VALUES(1,1,1,2100,'',1,12.25,5,0,'','','ITEM-A'),(2,1,2,2110,'',2,20,10,0,'','','ITEM-B');");
$regnaar=4;$sprog_id=0;$valuta='DKK';$valutakurs=100;$difkto=79900;
$view=file_get_contents(__DIR__.'/../kreditor/orderIncludes/openOrderLines.php');
$start=strpos($view,'$moms=creditorVatTotal(');$end=strpos($view,"\nif (\$art=='KK') {",$start);
if($start===false||$end===false)throw new RuntimeException('Actual creditor saved VAT block missing');
$GLOBALS['savedVatCode']=substr($view,$start,$end-$start);
$source=file_get_contents(__DIR__.'/../kreditor/bogfor.php');$start=strpos($source,'function bogfor($id)');$end=strpos($source,'?>',$start);
eval(substr($source,$start,$end-$start));
function scalarPurchase($sql){return pg_fetch_result(db_select($sql),0,0);}
function checkPurchase($value,$message){if(!$value)throw new RuntimeException($message);echo "PASS: $message\n";}
function resetPurchase($currency='DKK',$rate=100,$art='KO',$kind='normal') {
    db_select('TRUNCATE transaktioner,openpost');
    db_select("UPDATE ordrer SET status=2,sum=261.25,moms=0,momssats=25,omvbet='',valuta='$currency',valutakurs=$rate,art='$art' WHERE id=1");
    db_select("UPDATE ordrelinjer SET pris=CASE WHEN id=1 THEN 12.25 ELSE 20 END,antal=CASE WHEN id=1 THEN 5 ELSE 10 END,rabat=0,momsfri='',omvbet='',bogf_konto=CASE WHEN id=1 THEN 2100 ELSE 2110 END");
    db_select("UPDATE grupper SET box1='K1',box6='' WHERE art='KG';DELETE FROM grupper WHERE art IN ('SM','EM','YM')");
    $sum=$momssum=261.25;$momssats=25;$id=1;
    if($kind==='discount') {db_select('UPDATE ordrelinjer SET rabat=10 WHERE id=1');$sum=$momssum=255.13;}
    if($kind==='mixed') {db_select("UPDATE ordrelinjer SET momsfri='on' WHERE id=2");$momssum=61.25;}
    if($kind==='reverse') {
        db_select("UPDATE ordrelinjer SET omvbet='on' WHERE id=2;UPDATE ordrer SET omvbet='on';UPDATE grupper SET box6='S1' WHERE art='KG';INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2) VALUES('SM',1,4,'66300','25')");
        $momssum=61.25;
    }
    if($kind==='eu' || $kind==='eu-services') {
        db_select("UPDATE grupper SET box1='E1' WHERE art='KG';INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2,box3) VALUES('EM',1,4,'66300','25','66200');UPDATE ordrer SET momssats=0");
        $momssats=0;
        if ($kind === 'eu-services') {
            db_select("UPDATE grupper SET box1='Y1' WHERE art='KG';UPDATE grupper SET art='YM' WHERE art='EM'");
        }
    }
    if($kind==='small') {db_select('UPDATE ordrelinjer SET pris=0.01,antal=1');$sum=$momssum=0.02;}
    if($kind==='one-account') {db_select('UPDATE ordrelinjer SET bogf_konto=2100');}
    if($art==='KK') {db_select('UPDATE ordrelinjer SET antal=-antal');$sum=-$sum;$momssum=-$momssum;}
    eval($GLOBALS['savedVatCode']);
}
$cases=[
 ['normal','DKK',100,'KO','65.310','-326.560','326.560'],
 ['discount','DKK',100,'KO','63.780','-318.910','318.910'],
 ['mixed','DKK',100,'KO','15.310','-276.560','276.560'],
 ['normal','DKK',100,'KK','-65.310','326.560','326.560'],
 ['one-account','DKK',100,'KO','65.310','-326.560','326.560'],
 ['normal','EUR',745,'KO','65.310','-326.560','2432.870'],
 ['normal','EUR',745,'KK','-65.310','326.560','2432.870'],
 ['small','EUR',745,'KO','0.010','-0.030','0.220'],
 ['reverse','DKK',100,'KO','15.310','-276.560','326.560'],
 ['eu','DKK',100,'KO','0.000','-261.250','326.560'],
 ['eu','EUR',745,'KO','0.000','-261.250','2432.870'],
 ['eu-services','EUR',745,'KO','0.000','-261.250','2432.870'],
 ['eu','EUR',745,'KK','0.000','261.250','2432.870'],
 ['reverse','EUR',745,'KO','15.310','-276.560','2432.870'],
];
foreach($cases as [$kind,$currency,$rate,$art,$vat,$open,$base]) {
    resetPurchase($currency,$rate,$art,$kind);
    checkPurchase(scalarPurchase('SELECT moms FROM ordrer')===$vat,"$kind/$currency/$art actual entry screen stores source VAT cents");
    transaktion('BEGIN');$result=bogfor(1);transaktion('COMMIT');
    checkPurchase($result===true && scalarPurchase('SELECT status FROM ordrer')==='4',"$kind/$currency/$art actual posting succeeds");
    checkPurchase(scalarPurchase('SELECT amount FROM openpost')===$open,"$kind/$currency/$art exact signed supplier amount is retained in source currency");
    checkPurchase(scalarPurchase('SELECT SUM(debet)||\'|\'||SUM(kredit) FROM transaktioner')===$base.'|'.$base,"$kind/$currency/$art exact base-currency debit/credit totals conserve cents");
    checkPurchase(scalarPurchase('SELECT COUNT(*) FROM transaktioner WHERE debet<>ROUND(debet,2) OR kredit<>ROUND(kredit,2)')==='0',"$kind/$currency/$art no hidden fractional cent survives");
    checkPurchase(scalarPurchase("SELECT COUNT(*) FROM transaktioner WHERE projekt IS DISTINCT FROM 'PROJECT-H' OR transdate IS DISTINCT FROM CURRENT_DATE-3 OR ordre_id<>1") === '0', "$kind/$currency/$art invoice project/date/relationship retained on every ledger leg");
    $expectedVat = in_array($kind, ['eu', 'eu-services', 'reverse']) ? '65.310' : ltrim($vat, '-');
    if ($currency === 'EUR') {
        $expectedVat = $kind === 'small' ? '0.070' : '486.560';
    }
    $vatSide = $art === 'KK' ? 'kredit' : 'debet';
    $oppositeSide = $art === 'KK' ? 'debet' : 'kredit';
    checkPurchase(scalarPurchase("SELECT $vatSide FROM transaktioner WHERE kontonr=66200") === $expectedVat && scalarPurchase("SELECT $oppositeSide FROM transaktioner WHERE kontonr=66200") === '0.000', "$kind/$currency/$art exact input VAT goes to the configured account and side");
    checkPurchase(scalarPurchase("SELECT COUNT(*) FROM transaktioner WHERE kontonr NOT IN (68000,2100,2110,66200,66300,79900)") === '0', "$kind/$currency/$art no unexpected ledger account");
    $purchaseAmounts = $currency === 'EUR' ? ['456.310', '1490.000'] : ['61.250', '200.000'];
    if ($kind === 'discount') {
        $purchaseAmounts[0] = '55.130';
    } elseif ($kind === 'small') {
        $purchaseAmounts = ['0.070', '0.070'];
    } elseif ($kind === 'one-account') {
        $purchaseAmounts = ['261.250'];
    }
    foreach ($purchaseAmounts as $index => $expectedAmount) {
        $account = $index === 0 ? 2100 : 2110;
        checkPurchase(scalarPurchase("SELECT $vatSide FROM transaktioner WHERE kontonr=$account") === $expectedAmount && scalarPurchase("SELECT $oppositeSide FROM transaktioner WHERE kontonr=$account") === '0.000', "$kind/$currency/$art purchase group $account retains its exact amount and side");
    }
    if($kind==='small') checkPurchase(scalarPurchase('SELECT debet FROM transaktioner WHERE kontonr=79900')==='0.010','one-cent FX rounding residual uses the existing difference account');
}
resetPurchase();db_select('UPDATE ordrer SET moms=65.313');transaktion('BEGIN');$result=bogfor(1);transaktion('COMMIT');
checkPurchase($result===true && scalarPurchase('SELECT moms FROM ordrer')==='65.310' && scalarPurchase('SELECT amount FROM openpost')==='-326.560','legacy third-decimal draft normalizes to authoritative cents');
foreach(['sum=sum+1','moms=moms+1'] as $wrong) {
    resetPurchase();db_select('UPDATE ordrer SET '.$wrong);
    $before=scalarPurchase('SELECT row_to_json(o)::text FROM ordrer o');
    transaktion('BEGIN');db_select('UPDATE ordrer SET status=3');
    ob_start();$result=bogfor(1);ob_end_clean();
    checkPurchase($result===false && scalarPurchase('SELECT row_to_json(o)::text FROM ordrer o')===$before && scalarPurchase('SELECT COUNT(*) FROM openpost')==='0' && scalarPurchase('SELECT COUNT(*) FROM transaktioner')==='0','material header mismatch rejects and rolls back earlier preparation');
}
resetPurchase();
db_select("CREATE FUNCTION pg_temp.discard_purchase_post() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RETURN NULL; END';CREATE TRIGGER discard_purchase BEFORE INSERT ON transaktioner FOR EACH ROW EXECUTE FUNCTION pg_temp.discard_purchase_post();");
transaktion('BEGIN');ob_start();$result=bogfor(1);ob_end_clean();
checkPurchase($result===false && scalarPurchase('SELECT status FROM ordrer')==='2' && scalarPurchase('SELECT COUNT(*) FROM openpost')==='0','empty balanced ledger cannot masquerade as a posted invoice');

db_select('DROP TRIGGER discard_purchase ON transaktioner');
resetPurchase();
db_select("CREATE FUNCTION pg_temp.corrupt_purchase_post() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN IF NEW.kontonr=66200 THEN NEW.debet=NEW.debet+0.01; END IF; RETURN NEW; END';CREATE TRIGGER corrupt_purchase BEFORE INSERT ON transaktioner FOR EACH ROW EXECUTE FUNCTION pg_temp.corrupt_purchase_post();");
transaktion('BEGIN');ob_start();$result=bogfor(1);ob_end_clean();
checkPurchase($result===false && scalarPurchase('SELECT status FROM ordrer')==='2' && scalarPurchase('SELECT COUNT(*) FROM openpost')==='0' && scalarPurchase('SELECT COUNT(*) FROM transaktioner')==='0','a one-cent persisted ledger imbalance rolls back every posting write');
