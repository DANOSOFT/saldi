<?php
// 20260920 CDX/LH Exercise both actual reminder posting paths and their rollback boundaries in PostgreSQL.
if (PHP_SAPI !== 'cli') exit('CLI only');
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
if (!getenv('SALDI_TEST_DSN')) exit("SKIP: set SALDI_TEST_DSN to an isolated PostgreSQL fixture.\n");
if (!isset($argv[1])) {
    foreach (array('openpost','rapportfunc') as $path) {
        $pipes = array();
        $child = proc_open(array(PHP_BINARY,__FILE__,$path),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        $output=stream_get_contents($pipes[1]); $errors=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($child);
        if ($status || $errors) throw new RuntimeException($output.$errors);
        echo $output;
    }
    // Tokenize the actual caller guards: formatting/comments must not affect the test.
    $tokens = token_get_all(file_get_contents(__DIR__.'/../debitor/ny_rykker.php'));
    $guards = [];
    foreach ($tokens as $index => $token) {
        if (!is_array($token) || $token[0] !== T_IF) {
            continue;
        }
        $condition = '';
        $depth = 0;
        $started = false;
        for ($cursor = $index + 1; $cursor < count($tokens); $cursor++) {
            $part = $tokens[$cursor];
            $text = is_array($part) ? $part[1] : $part;
            $condition .= $text;
            if ($part === '(') {
                $started = true;
                $depth++;
            } elseif ($part === ')') {
                $depth--;
                if ($started && $depth === 0) {
                    break;
                }
            }
        }
        if (strpos($condition, 'bogfor_rykker') === false) {
            continue;
        }
        $body = '';
        $depth = 0;
        for ($cursor++; $cursor < count($tokens); $cursor++) {
            $part = $tokens[$cursor];
            $body .= is_array($part) ? $part[1] : $part;
            if ($part === '{') {
                $depth++;
            } elseif ($part === '}') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            } elseif ($part === ';' && $depth === 0) {
                break;
            }
        }
        $guards[] = 'if' . $condition . $body;
    }
    if (count($guards) !== 2) {
        throw new RuntimeException('Expected both automatic reminder guards');
    }
    foreach ($guards as $index => $guard) {
        foreach ([false, 17] as $postingResult) {
            // Execute the real exit in a fresh child. The posting stub verifies that
            // marking paid is delegated to the same atomic posting call.
            $program = '$r = ["id" => 17]; function bogfor_rykker($id, $paid) {'
                . ' if ($id !== 17 || $paid !== true) { throw new RuntimeException("Invalid posting arguments"); }'
                . ' echo "POSTED;"; return ' . var_export($postingResult, true) . '; } '
                . $guard . ' echo "ADVANCED";';
            $pipes = [];
            $child = proc_open([PHP_BINARY, '-r', $program], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $status = proc_close($child);
            $expected = $postingResult === false ? 'POSTED;' : 'POSTED;ADVANCED';
            if ($status !== 0 || $errors !== '' || $output !== $expected) {
                throw new RuntimeException('Automatic reminder guard has wrong success/failure behavior: ' . $output . $errors);
            }
            echo "PASS: automatic guard $index " . ($postingResult === false ? 'stops on posting failure' : 'advances only after successful posting') . "\n";
        }
    }
    exit;
}
$path=$argv[1];if (!in_array($path,array('openpost','rapportfunc'),true))throw new RuntimeException('Unknown path');
$connection=pg_connect(getenv('SALDI_TEST_DSN'));
$db='dunning_regression';$db_type='pgsql';$brugernavn='test';$regnaar=2;$webservice=false;$db_modify_fejl=false;$db_transaktion_depth=0;
function get_relative(){return '/tmp/';}function db_log_append($path,$data,$mode='a'){}
function db_select($sql,$where='') { $result=pg_query($GLOBALS['connection'],$sql);if(!$result)throw new RuntimeException('Test SQL failed');return $result; }
function db_fetch_array($result){return pg_fetch_assoc($result);}
function db_modify($sql,$where='') {
    pg_send_query($GLOBALS['connection'],$sql);$result=pg_get_result($GLOBALS['connection']);while(pg_get_result($GLOBALS['connection'])!==false){}
    if(pg_result_status($result)===PGSQL_FATAL_ERROR){$GLOBALS['db_modify_fejl']=true;return false;}return $result;
}
require_once __DIR__.'/../includes/db_query.php';require_once __DIR__.'/../includes/std_func.php';
chdir(__DIR__.'/../debitor');
require_once __DIR__.'/../includes/'.$path.'.php';
function scalarDunning($sql){return pg_fetch_row(db_select($sql))[0];}
function checkDunning($condition,$message){if(!$condition)throw new RuntimeException($GLOBALS['path'].': '.$message);echo 'PASS: '.$GLOBALS['path'].': '.$message."\n";}
function callDunning($paid=false,$direct=false){ob_start();$result=$direct?bogfor_nu(1):bogfor_rykker(1,$paid);ob_end_clean();return $result;}
function fingerprintDunning(){
    $parts=array();foreach(array('ordrer','ordrelinjer','transaktioner','openpost') as $table)$parts[$table]=scalarDunning("SELECT COALESCE(json_agg(q ORDER BY id)::text,'[]') FROM $table q");return $parts;
}
db_select(<<<'SQL'
CREATE TEMP TABLE ordrer(id integer,art text,status integer,sum numeric,moms numeric,momssats numeric,konto_id integer,kontonr text,fakturanr text,fakturadate date,valuta text,valutakurs numeric,projekt integer,ref text,betalt text);
CREATE TEMP TABLE ordrelinjer(id integer,ordre_id integer,vare_id integer,antal numeric,pris numeric,rabat numeric,bogf_konto integer,posnr integer);
CREATE TEMP TABLE varer(id integer,gruppe integer);
CREATE TEMP TABLE grupper(id serial,art text,kodenr integer,fiscal_year integer,box1 text,box2 text,box3 text,box4 text,box8 text);
CREATE TEMP TABLE adresser(id integer,art text,gruppe integer);
CREATE TEMP TABLE kontoplan(id serial,kontonr integer,regnskabsaar integer);
CREATE TEMP TABLE ansatte(navn text,afd integer);
CREATE TEMP TABLE valuta(gruppe integer,kurs numeric,valdate date);
CREATE TEMP TABLE openpost(id serial,konto_id integer,konto_nr text,faktnr text,refnr integer,amount numeric(15,2),beskrivelse text,udlignet text,transdate date,kladde_id integer,valuta text,valutakurs numeric);
CREATE TEMP TABLE transaktioner(id serial,bilag integer,transdate date,beskrivelse text,kontonr integer,faktura text,debet numeric(15,2),kredit numeric(15,2),kladde_id integer,afd integer,logdate date,logtime text,projekt integer,ordre_id integer);
INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2,box3,box4,box8) VALUES
 ('RA',2,0,'4','2026','3','2027',''),('VG',1,1,'','','','9999','on'),('VG',1,2,'','','','1200',''),
 ('VG',2,2,'','','','1210','on'),('VG',3,2,'','','','1220',''),('DG',1,1,'','56999','','',''),('DG',1,2,'','56100','','','');
INSERT INTO kontoplan(kontonr,regnskabsaar) VALUES(1200,2),(1210,2),(1220,2),(56100,2),(9999,1),(56999,1);
INSERT INTO adresser VALUES(1,'D',1);INSERT INTO varer VALUES(1,1),(2,2),(3,3);
SQL);
function resetDunning(){
    db_select('TRUNCATE ordrer,ordrelinjer,transaktioner,openpost');
    db_select("UPDATE grupper SET box8='on' WHERE art='VG' AND kodenr=2; UPDATE grupper SET box4='1200' WHERE art='VG' AND kodenr=1 AND fiscal_year=2;");
    db_select("INSERT INTO ordrer VALUES(1,'R1',2,0,25,25,1,'1000','R1-001','2026-09-20','DKK',100,7,'',NULL);");
    db_select('INSERT INTO ordrelinjer VALUES(1,1,1,1,100,NULL,NULL,1),(2,1,NULL,NULL,NULL,NULL,NULL,2)');
    $GLOBALS['db_modify_fejl']=false;
}
resetDunning();
checkDunning(callDunning()===1,'ordinary fee posts through the actual entry point');
checkDunning(scalarDunning("SELECT sum||'|'||moms||'|'||momssats||'|'||status FROM ordrer")==='100|0|0|4','fee remains VAT-exempt even when header carried a VAT rate');
checkDunning(scalarDunning("SELECT string_agg(kontonr||':'||debet||':'||kredit,',' ORDER BY kontonr) FROM transaktioner")==='1200:0.00:100.00,56100:100.00:0.00','exact fee and debtor accounts come from selected fiscal year');
checkDunning(scalarDunning("SELECT amount||'|'||valuta||'|'||valutakurs||'|'||transdate FROM openpost")==='100.00|DKK|100|2026-09-20','receivable retains fee amount, base currency and posting date');
$before=fingerprintDunning();checkDunning(callDunning()===1 && fingerprintDunning()===$before,'retry of posted reminder is idempotent');
resetDunning();db_select('INSERT INTO ordrelinjer VALUES(3,1,2,1,50,0,NULL,3)');$before=fingerprintDunning();
checkDunning(callDunning(true)===false && fingerprintDunning()===$before,'mixed ordinary and stock fee rejects without header, account or paid-state mutation');
resetDunning();db_select("UPDATE grupper SET box4='' WHERE art='VG' AND kodenr=1 AND fiscal_year=2");$before=fingerprintDunning();
checkDunning(callDunning()===false && fingerprintDunning()===$before,'missing revenue account fails without partial preparation');
resetDunning();db_select("UPDATE ordrer SET fakturadate='2026-01-15'");$before=fingerprintDunning();
checkDunning(callDunning()===false && fingerprintDunning()===$before,'month before non-January fiscal-year start is rejected');
resetDunning();db_select("UPDATE ordrer SET valuta='UNKNOWN',valutakurs=0");$before=fingerprintDunning();
checkDunning(callDunning()===false && fingerprintDunning()===$before,'missing foreign exchange rate cannot create a partial fee');
resetDunning();db_select("UPDATE ordrer SET valuta='EUR',valutakurs=745;UPDATE ordrelinjer SET pris=100.01 WHERE id=1;INSERT INTO ordrelinjer VALUES(3,1,3,1,100.01,0,NULL,3)");
checkDunning(callDunning(true)===1,'foreign fees across revenue groups post successfully');
checkDunning(scalarDunning('SELECT SUM(debet)||\'|\'||SUM(kredit) FROM transaktioner')==='1490.15|1490.15','foreign rounded fee groups conserve the authoritative total');
checkDunning(scalarDunning("SELECT betalt||'|'||sum FROM ordrer")==='on|200.02','automatic advancement is committed with the correct source-currency fee');
resetDunning();db_select("UPDATE ordrer SET valuta='EUR',valutakurs=745;UPDATE ordrelinjer SET pris=100.01,antal=-1 WHERE id=1;INSERT INTO ordrelinjer VALUES(3,1,3,-1,100.01,0,NULL,3)");
checkDunning(callDunning()===1 && scalarDunning('SELECT amount FROM openpost')==='-1490.15','signed fee reversal preserves source and converted amounts');
checkDunning(scalarDunning('SELECT SUM(debet)-SUM(kredit) FROM transaktioner')==='0.00','signed fee posting remains balanced');
resetDunning();db_select('INSERT INTO ordrelinjer VALUES(3,1,3,-1,100,0,NULL,3)');
checkDunning(callDunning()===1 && scalarDunning('SELECT COUNT(*) FROM openpost')==='0','zero total signed fees create no artificial receivable');
checkDunning(scalarDunning('SELECT COUNT(*)||\'|\'||SUM(debet)||\'|\'||SUM(kredit) FROM transaktioner')==='2|100.00|100.00','zero total retains both offsetting account movements');
resetDunning();
db_select(<<<'SQL'
CREATE FUNCTION pg_temp.reject_dunning_credit() RETURNS trigger LANGUAGE plpgsql AS $body$
BEGIN IF NEW.kredit>0 THEN RAISE EXCEPTION 'synthetic late posting failure'; END IF; RETURN NEW; END $body$;
CREATE TRIGGER reject_dunning_credit BEFORE INSERT ON transaktioner FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_dunning_credit();
SQL);
$before=fingerprintDunning();checkDunning(callDunning(true)===false && fingerprintDunning()===$before,'late credit insert failure rolls back header, lines, receivable, debit and paid state');
checkDunning($db_transaktion_depth===0 && $webservice===false,'failed posting closes its transaction and restores caller error mode');
db_select('DROP TRIGGER reject_dunning_credit ON transaktioner');
resetDunning();
db_select("CREATE FUNCTION pg_temp.discard_dunning_posting() RETURNS trigger LANGUAGE plpgsql AS 'BEGIN RETURN NULL; END';CREATE TRIGGER discard_dunning_posting BEFORE INSERT ON transaktioner FOR EACH ROW EXECUTE FUNCTION pg_temp.discard_dunning_posting();");
$before=fingerprintDunning();checkDunning(callDunning()===false && fingerprintDunning()===$before,'an empty balanced ledger cannot substitute for the required fee legs');
db_select('DROP TRIGGER discard_dunning_posting ON transaktioner');
resetDunning();$before=fingerprintDunning();transaktion('begin');
checkDunning(callDunning()===1 && $db_transaktion_depth===1,'inner successful posting does not commit an outer transaction');
transaktion('rollback');checkDunning(fingerprintDunning()===$before,'outer rollback reverses the complete successful reminder');
resetDunning();$before=fingerprintDunning();transaktion('begin');db_modify('INSERT INTO ordrelinjer VALUES(3,1,2,1,50,0,NULL,3)');
checkDunning(callDunning(true)===false && $db_transaktion_depth===1,'inner validation failure leaves outer transaction rollback-only');
db_modify('UPDATE ordrer SET sum=999');transaktion('commit');
checkDunning(fingerprintDunning()===$before,'later outer writes cannot escape a failed reminder rollback');
resetDunning();checkDunning(callDunning(false,true)===1,'direct legacy bogfor_nu entry also uses atomic validation');
