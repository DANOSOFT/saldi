<?php
// 20260924 CDX/PHR Replay journal 2698 in temporary tables and verify balance rollback.
// Use a test_34 fixture containing journal 2698; all test writes target temporary tables.
if (!getenv('SALDI_CHAR_DSN')) {
    echo "SKIP: set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS for the test_34 fixture.\n";
    exit;
}
$pdo = new PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER'), getenv('SALDI_CHAR_PGPASS'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$repositoryRoot = dirname(__DIR__);
function db_select($sql,$context=null){return $GLOBALS['pdo']->query($sql);}
function db_fetch_array($result){return $result->fetch(PDO::FETCH_ASSOC);}
function db_modify($sql,$context=null){
 $result=$GLOBALS['pdo']->exec($sql);
 if(!empty($GLOBALS['injectFault']) && strpos($sql,"'valutadiff'")!==false){
  $GLOBALS['pdo']->exec("UPDATE ".$GLOBALS['targetTable']." SET debet=-abs(debet),kredit=-abs(kredit) WHERE kladde_id=2698 AND kontonr=7960");
 }
 return $result;
}
function db_escape_string($s){return substr($GLOBALS['pdo']->quote((string)$s),1,-1);}
function transaktion($action){$GLOBALS['pdo']->exec($action);}
$source=file_get_contents($repositoryRoot . '/finans/bogfor.php');
$start=strpos($source,'function get_saved_vat_override(');$end=strpos($source,'$funktion=',$start);
eval(substr($source,$start,$end-$start));
$functionStart = strpos($source, 'function bogfor(');
$functions = substr($source, $functionStart, strrpos($source, "if (\$menu=='T')") - $functionStart);
$functions=str_replace('__DIR__',var_export($repositoryRoot . '/finans',true),$functions);
eval(preg_replace('/\?>\s*$/','',$functions));
require_once $repositoryRoot . '/includes/std_func.php';
$regnaar=(int)$pdo->query("SELECT kodenr FROM grupper WHERE art='RA' AND box2='2026' ORDER BY kodenr DESC LIMIT 1")->fetchColumn();
$brugernavn='posting-regression';$kladde_id=2698;
// Shadow every table the posting writes, without borrowing production sequences.
foreach(['kassekladde','kladdeliste','kontoplan','openpost','transaktioner','simulering','tmpkassekl'] as $table){
 $where=in_array($table,['kassekladde','tmpkassekl'])?' WHERE kladde_id=2698':($table==='kladdeliste'?' WHERE id=2698':(in_array($table,['transaktioner','simulering','openpost'])?' WHERE false':''));
 $pdo->exec("CREATE TEMP TABLE $table AS SELECT * FROM public.$table$where");
 $max=(int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM $table")->fetchColumn();
 $pdo->exec("CREATE TEMP SEQUENCE test_{$table}_ids START $max; ALTER TABLE $table ALTER COLUMN id SET DEFAULT nextval('pg_temp.test_{$table}_ids')");
}
// Keep the active year as the last year so this fixture cannot touch persistent opening balances.
$pdo->exec('CREATE TEMP TABLE grupper AS SELECT * FROM public.grupper');
$pdo->exec("DELETE FROM grupper WHERE art='RA' AND kodenr>$regnaar");
$pdo->exec("UPDATE kladdeliste SET bogfort='' WHERE id=2698");
$pdo->exec('UPDATE kontoplan SET saldo=0');
foreach([false,true] as $simulation){
 foreach([false,true] as $fault){
  $injectFault=$fault;$targetTable=$simulation?'simulering':'transaktioner';
  $pdo->beginTransaction();
  $error=bogfor(2698,'Regression 2698',$simulation?'on':'');
  $balance=$pdo->query("SELECT sum(COALESCE(debet,0)-COALESCE(kredit,0)) FROM $targetTable WHERE kladde_id=2698")->fetchColumn();
  $correction=$pdo->query("SELECT debet,kredit FROM $targetTable WHERE kladde_id=2698 AND kontonr=7960")->fetch(PDO::FETCH_ASSOC);
  if($fault){if(!$error || (float)$balance!=-0.02)throw new Exception('Fault not detected');}
  else {if($error || (float)$balance!=0 || (float)$correction['debet']!=0.01)throw new Exception('Posting not corrected: '.$error);}
  $pdo->rollBack();
  if((int)$pdo->query("SELECT count(*) FROM $targetTable")->fetchColumn()!==0 || (int)$pdo->query('SELECT count(*) FROM openpost')->fetchColumn()!==0 || $pdo->query('SELECT bogfort FROM kladdeliste WHERE id=2698')->fetchColumn()!=='')throw new Exception('Rollback left changes');
  echo 'PASS ', $simulation?'simulation':'posting', $fault?' injected sign error rejected; rollback clean':' correction debit 0.01; balanced; rollback clean',"\n";
 }
}
// Execute the controller's real transaction branches with an injected write defect.
foreach([false,true] as $simulation){
 $injectFault=true;$targetTable=$simulation?'simulering':'transaktioner';
 $bogfor=true;$simuler=$simulation;$kladdenote='Controller rollback test';$popup=false;$db_modify_fejl=false;
 if($simulation){
  $start=strpos($source,"\t\ttransaktion('begin');",strpos($source,'} elseif ($simuler) {'));
  $end=strpos($source,"\n\t}\n\tif (\$funktion",$start);
 }else{
  $call=strpos($source,"\$postingError = bogfor(\$kladde_id, \$kladdenote,'');");
  $start=strrpos(substr($source,0,$call),"transaktion('begin');");
  $end=strpos($source,"\n\t\t}\n\t} elseif (\$simuler)",$start);
 }
 if($start===false||$end===false)throw new Exception('Controller block not found');
 ob_start();eval(substr($source,$start,$end-$start));$output=ob_get_clean();
 if($pdo->inTransaction() || (int)$pdo->query("SELECT count(*) FROM $targetTable")->fetchColumn()!==0 || (int)$pdo->query('SELECT count(*) FROM openpost')->fetchColumn()!==0 || strpos($output,'balancerer ikke')===false)throw new Exception('Controller did not roll back');
 echo 'PASS actual ', $simulation?'simulation':'posting'," controller rolled back all writes and reported the balance error\n";
}

// Reverse every journal side to exercise the opposite rounding direction.
$pdo->exec('UPDATE kassekladde SET (d_type,debet,k_type,kredit)=(k_type,kredit,d_type,debet)');
$injectFault=false;$targetTable='transaktioner';
$pdo->beginTransaction();
$error=bogfor(2698,'Reverse rounding regression','');
$balance=$pdo->query('SELECT SUM(COALESCE(debet,0)-COALESCE(kredit,0)) FROM transaktioner WHERE kladde_id=2698')->fetchColumn();
$correction=$pdo->query('SELECT debet,kredit FROM transaktioner WHERE kladde_id=2698 AND kontonr=7960')->fetch(PDO::FETCH_ASSOC);
if($error || (float)$balance!=0 || (float)$correction['kredit']!=0.01 || (float)$correction['debet']!=0)throw new Exception('Reverse rounding failed');
$pdo->rollBack();
echo "PASS opposite rounding direction posts credit 0.01 and balances\n";
