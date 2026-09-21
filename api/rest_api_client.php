<?php
ob_start();
echo '<html><body>';
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------dht_saldi_api_client.php---ver. 1.0---2017-02-07--------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2017 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20260921 CDX/LUI Validate complete exports and persist acknowledged/unknown hops before removing a source.


if(!ini_get('allow_url_fopen') ) {
   echo 'allow_url_fopen not enabled<br>';
   exit;
}

$serverurl=getenv('SALDI_CLIENT_SERVER_URL') !== false ? getenv('SALDI_CLIENT_SERVER_URL') : ''; #f.eks https://ssl.saldi.dk/api
$db=getenv('SALDI_CLIENT_DB') !== false ? getenv('SALDI_CLIENT_DB') : ''; #Findes under Indstillinger ->  Diverse -> API
$saldiuser=getenv('SALDI_CLIENT_USER') !== false ? getenv('SALDI_CLIENT_USER') : ''; #Findes under Indstillinger ->  Diverse -> API
$api_key=getenv('SALDI_CLIENT_API_KEY') !== false ? getenv('SALDI_CLIENT_API_KEY') : ''; #Findes under Indstillinger ->  Diverse -> API
$ftp_url=''; #URL til ftp sted på webshop hvor filer kan hentes og afleveres
$ftp_user='';
$ftp_pw='';
$ftp_stock_file='lagerfil.csv';
$order_path=getenv('SALDI_CLIENT_ORDER_PATH') !== false ? getenv('SALDI_CLIENT_ORDER_PATH') : 'orderexport';
// Keep this local directory across retries, including FTP imports. A pending hop
// requires reconciliation against SALDI before its journal entry can be resolved;
// never delete a pending journal merely to retry a non-idempotent line request.
$state_path=getenv('SALDI_CLIENT_STATE_PATH') ?: __DIR__ . '/../temp/rest-api-client';
$fragt_varenr=getenv('SALDI_CLIENT_FREIGHT_SKU') !== false ? getenv('SALDI_CLIENT_FREIGHT_SKU') : 'A90'; # varenummer i saldi som bruges til fragt.


if (isset($_GET['get_stock']) && $_GET['get_stock']) {
	$select="varenr,beholdning";
	$from="varer";
	if ($_GET['get_stock']=='*') $where='';
	else $where=$_GET['get_stock'];
	$order_by="varenr";
	$limit='';
	$result=fetch_from_table($serverurl,$db,$api_key,$saldiuser,$select,$from,$where,$order_by,$limit);
  if (is_array($result)) {
		if ($ftp_url) $file = fopen ("ftp://$ftp_user:$ftp_pw@$ftp_url/$ftp_stock_file", "w");
		else $file = fopen ("$ftp_stock_file", "w");
		if ($file) {
			$rows=count($result);
			$cols=count($result[0]);
			fwrite ($file,'"'.$result[0][0].'","'.$result[0][1].'"'."\n");
			for ($x=1;$x<$rows;$x++){
				for ($y=0;$y<$cols;$y++) {
					if ($y) {
						fwrite ($file,$result[$x][$y]*1);
					} else fwrite ($file,'"'.$result[$x][$y].'",');
				}
				fwrite ($file,"\n");
			}
			fclose($file);
			echo "Done";
		} else echo "Cannot open stockfile";
	} else echo "$result<br>";
} elseif (!empty($_GET['put_new_orders'])) {
    $ftp_id = null;
    $source = null;
    try {
        if ($ftp_url) {
            $ftp_id = ftp_connect($ftp_url);
            if (!$ftp_id || !ftp_login($ftp_id, $ftp_user, $ftp_pw)) { throw new RuntimeException('Cannot connect to order export FTP.'); }
            $files = ftp_nlist($ftp_id, $order_path . '/*.csv');
            if ($files) {
                sort($files);
                $source = $files[0];
                $contents = file_get_contents('ftp://' . rawurlencode($ftp_user) . ':' . rawurlencode($ftp_pw) . '@' . $ftp_url . '/' . $source);
            }
        } else {
            $files = glob($order_path . '/*.csv');
            if ($files) { sort($files); $source = $files[0]; $contents = file_get_contents($source); }
        }
        if ($source === null) { throw new RuntimeException('File not found'); }
        if ($contents === false) { throw new RuntimeException('Cannot read order export; source retained.'); }
        restClientTransferOrders($contents, $ftp_url ? $ftp_url . '/' . $source : (realpath($source) ?: $source),
            $state_path, rtrim($serverurl, '/'), ['db'=>$db,'key'=>$api_key,'saldiuser'=>$saldiuser], $fragt_varenr);
        if (!$ftp_url && hash_file('sha256', $source) !== hash('sha256', $contents)) { throw new RuntimeException('Source changed during import; file retained.'); }
        // Journal remains after removal, so a failed delete or replayed file never repeats writes.
        if ($ftp_url ? !ftp_delete($ftp_id, $source) : !unlink($source)) { throw new RuntimeException('Import completed but source removal failed; progress retained.'); }
        echo 'Done';
    } catch (Throwable $error) {
        http_response_code(409);
        echo htmlspecialchars($error->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    } finally {
        if ($ftp_id) { ftp_close($ftp_id); }
    }
}
/** @return array<string,list<array<string,string>>> Validated 44-column export, grouped by order. */
function restClientReadOrders(string $contents): array {
    $columns = explode(',', 'Ordernr,Orderdate,OrderStatus,PurchasedWebsite,PaymentMethod,ShippingMethod,Subtotal,ShippingCost,GrandTotal,TotalTax,TotalPaid,TotalRefunded,ItemName,ItemSKU,ItemISBN,ItemStock,ItemPrice,CostPrice,ItemOrdered,ItemInvoiced,ItemSent,CustomerID,BillingFirstName,BillingLastName,BillingCompany,BillingEMail,BillingPhone,BillingAddress1,BillingAddress2,BillingCity,BillingPostcode,BillingState,BillingCountry,ShippingFirstName,ShippingLastName,ShippingCompany,ShippingEMail,ShippingPhone,ShippingAddress1,ShippingAddress2,ShippingCity,ShippingPostcode,ShippingState,ShippingCountry');
    $stream = fopen('php://temp', 'w+'); fwrite($stream, $contents); rewind($stream);
    $orders = []; $line = 0;
    try {
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null]) { continue; }
            $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', $values[0]);
            if ($line === 1 && strtolower(trim($values[0])) === 'ordernr') { continue; }
            if (count($values) !== count($columns)) { throw new RuntimeException("Invalid export column count at row $line; source retained."); }
            $row = array_combine($columns, array_map('trim', $values));
            foreach (['Ordernr','CustomerID'] as $field) {
                if (!ctype_digit($row[$field]) || (int)$row[$field] < 1) { throw new RuntimeException("Invalid $field at row $line; source retained."); }
            }
            foreach (['ShippingCost','GrandTotal','TotalTax','ItemPrice','ItemOrdered'] as $field) {
                if (!is_numeric($row[$field]) || !is_finite((float)$row[$field])) { throw new RuntimeException("Invalid $field at row $line; source retained."); }
            }
            if ($row['ItemSKU'] === '') { throw new RuntimeException("Missing ItemSKU at row $line; source retained."); }
            $format = str_contains($row['Orderdate'], '/') ? '!d/m/Y' : '!Y-m-d';
            $date = DateTimeImmutable::createFromFormat($format, $row['Orderdate']);
            $errors = DateTimeImmutable::getLastErrors();
            if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) { throw new RuntimeException("Invalid order date at row $line; source retained."); }
            $row['Orderdate'] = $date->format('Y-m-d');
            $id = $row['Ordernr'];
            if (isset($orders[$id])) {
                foreach ($columns as $index => $field) {
                    if ($index >= 12 && $index <= 20) { continue; }
                    if ($row[$field] !== $orders[$id][0][$field]) { throw new RuntimeException("Conflicting order header at row $line; source retained."); }
                }
            }
            $orders[$id][] = $row;
        }
    } finally { fclose($stream); }
    if (!$orders) { throw new RuntimeException('No valid order rows; source retained.'); }
    return $orders;
}

/** @param resource $journal Persist before each request; torn state fails closed on the next run. */
function restClientSaveState($journal, array $state): void {
    $data = json_encode($state, JSON_THROW_ON_ERROR);
    rewind($journal);
    if (!ftruncate($journal, 0) || fwrite($journal, $data) !== strlen($data) || !fflush($journal) || (function_exists('fsync') && !fsync($journal))) {
        throw new RuntimeException('Cannot persist import progress; source retained.');
    }
}

/** @return int Acknowledged remote object ID. An unknown outcome must never be replayed automatically. */
function restClientHop(string $endpoint, array $auth, array $params, string $step, &$state, $journal): int {
    $fingerprint = hash('sha256', json_encode($params, JSON_THROW_ON_ERROR));
    if (isset($state['steps'][$step])) {
        $saved = $state['steps'][$step];
        if (($saved['fingerprint'] ?? '') !== $fingerprint) { throw new RuntimeException('Import request changed; source retained for reconciliation.'); }
        if (($saved['status'] ?? '') !== 'done') { throw new RuntimeException('An earlier request has an unknown outcome; reconcile the pending import before retrying. Source retained.'); }
        return (int)$saved['id'];
    }
    $state['steps'][$step] = ['fingerprint'=>$fingerprint,'status'=>'pending'];
    restClientSaveState($journal, $state);
    $url = $endpoint . '/rest_api.php?' . http_build_query($auth + $params, '', '&', PHP_QUERY_RFC3986);
    $context = stream_context_create(['http'=>['timeout'=>30,'ignore_errors'=>true,'follow_location'=>0]]);
    // Network warnings may contain the credential-bearing URL; expose a fixed error instead.
    set_error_handler(static function () { throw new RuntimeException('API transport failed; source retained for reconciliation.'); });
    try { $response = file_get_contents($url, false, $context); }
    finally { restore_error_handler(); }
    $status = $http_response_header[0] ?? '';
    $value = json_decode((string)$response, true);
    if (!preg_match('/^HTTP\/\S+ 2[0-9]{2}(?: |$)/', $status) || (!is_int($value) && !is_string($value)) || !ctype_digit((string)$value) || (int)$value < 1) {
        throw new RuntimeException('API request was not acknowledged with a positive ID; source retained for reconciliation.');
    }
    $state['steps'][$step] = ['fingerprint'=>$fingerprint,'status'=>'done','id'=>(int)$value];
    restClientSaveState($journal, $state);
    return (int)$value;
}

/** Transfer a complete source; retain locked progress after failures and completed-source deletion. */
function restClientTransferOrders(string $contents, string $sourceIdentity, string $stateDirectory, string $serverurl, array $auth, string $freightSku): void {
    $orders = restClientReadOrders($contents); // Complete validation before any remote write.
    if (!preg_match('~^https?://~i', $serverurl) || empty($auth['db']) || empty($auth['key']) || empty($auth['saldiuser'])) {
        throw new RuntimeException('Configure the API connection before importing; source retained.');
    }
    if (!is_dir($stateDirectory) && !mkdir($stateDirectory, 0700, true)) { throw new RuntimeException('Cannot create import progress directory.'); }
    $identity = hash('sha256', json_encode([$serverurl,$auth['db'],$auth['saldiuser'],$sourceIdentity], JSON_THROW_ON_ERROR));
    $journal = fopen($stateDirectory . '/' . $identity . '.json', 'c+');
    if (!$journal) { throw new RuntimeException('Cannot open import progress journal.'); }
    try {
        if (!flock($journal, LOCK_EX | LOCK_NB)) { throw new RuntimeException('This source is already being imported; source retained.'); }
        $data = stream_get_contents($journal);
        $hash = hash('sha256', $contents);
        $state = $data === '' ? ['source_hash'=>$hash,'steps'=>[]] : json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state) || ($state['source_hash'] ?? '') !== $hash || !isset($state['steps']) || !is_array($state['steps'])) {
            throw new RuntimeException('Source or import journal changed; source retained for reconciliation.');
        }
        foreach ($orders as $orderId => $lines) {
            $r = $lines[0];
            $header = ['action'=>'insert_shop_order','shop_ordre_id'=>$orderId,'shop_addr_id'=>$r['CustomerID'],
                'firmanavn'=>$r['BillingCompany'],'addr1'=>$r['BillingAddress1'],'addr2'=>$r['BillingAddress2'],
                'postnr'=>$r['BillingPostcode'],'stat'=>$r['BillingState'],'bynavn'=>$r['BillingCity'],'land'=>$r['BillingCountry'],
                'tlf'=>$r['BillingPhone'],'email'=>$r['BillingEMail'],'ref'=>$auth['saldiuser'],
                'nettosum'=>(float)$r['GrandTotal']-(float)$r['TotalTax'],'momssum'=>$r['TotalTax'],
                'kontakt'=>$r['BillingFirstName'].' '.$r['BillingLastName'],'lev_firmanavn'=>$r['ShippingCompany'],
                'lev_addr1'=>$r['ShippingAddress1'],'lev_addr2'=>$r['ShippingAddress2'],'lev_postnr'=>$r['ShippingPostcode'],
                'lev_bynavn'=>$r['ShippingCity'],'lev_stat'=>$r['ShippingState'],'lev_land'=>$r['ShippingCountry'],
                'lev_tlf'=>$r['ShippingPhone'],'lev_email'=>$r['ShippingEMail'],'lev_kontakt'=>$r['ShippingFirstName'].' '.$r['ShippingLastName'],
                'betalingsbet'=>'Kreditkort','betalingsdage'=>0,'ordredate'=>$r['Orderdate'],'lev_date'=>$r['Orderdate'],
                'momssats'=>25,'valuta'=>'DKK','valutakurs'=>100,'gruppe'=>1,'afd'=>0,'projekt'=>'',
                'ekstra1'=>'','ekstra2'=>'','ekstra3'=>'','ekstra4'=>'','ekstra5'=>''];
            $saldiId = restClientHop($serverurl, $auth, $header, "$orderId:header", $state, $journal);
            foreach ($lines as $index => $line) {
                $params = ['action'=>'insert_shop_orderline','saldi_ordre_id'=>$saldiId,'varenr'=>$line['ItemSKU'],
                    'beskrivelse'=>$line['ItemName'],'antal'=>$line['ItemOrdered'],'pris'=>$line['ItemPrice'],'rabat'=>0];
                restClientHop($serverurl, $auth, $params, "$orderId:line:$index", $state, $journal);
            }
            if ($freightSku !== '') {
                restClientHop($serverurl, $auth, ['action'=>'insert_shop_orderline','saldi_ordre_id'=>$saldiId,'varenr'=>$freightSku,
                    'beskrivelse'=>'Fragt','antal'=>1,'pris'=>$r['ShippingCost'],'rabat'=>0,'momsfri'=>'on'], "$orderId:freight", $state, $journal);
            }
        }
        $state['complete'] = true;
        restClientSaveState($journal, $state);
    } finally { flock($journal, LOCK_UN); fclose($journal); }
}

function fetch_from_table($serverurl,$db,$api_key,$saldiuser,$select,$from,$where,$order_by,$limit) {
	$result = file_get_contents($serverurl."/rest_api.php?action=fetch_from_table&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&select=".urlencode($select)."&from=".urlencode($from)."&where=".urlencode($where)."&order_by=".urlencode($order_by)."&limit=".urlencode($limit));
  $result = json_decode($result, true);
	return $result;
}

function update_table($serverurl,$db,$api_key,$saldiuser,$update,$set,$where) {
	$result = file_get_contents($serverurl."/rest_api.php?action=update_tablee&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&update=".urlencode($update)."&set=".urlencode($set)."&where=".urlencode($where));
	$result = json_decode($result, true);
	if (!is_numeric($result)) {
		print "error: ".$result;
	} else {
		print "<table border='1'><tbody>";
		print "<td>".$result."</td>";
		print  "</tr>";
		print "</tbody></table>";
	}
	print "<br><a href=\"rest_api_client.php?update=$update&set=$set&where=$where\">Return to query page</a>";
}
function insert_into_table($serverurl,$db,$api_key,$saldiuser,$insert,$fields,$values) {
  $result = file_get_contents($serverurl."/rest_api.php?action=insert_into_table&db=$db&key=".urlencode($api_key)."&saldiuser=".urlencode($saldiuser)."&insert=".urlencode($insert)."&fields=".urlencode($fields)."&values=".urlencode($values));
  $result = json_decode($result, true);
	if (!is_numeric($result)) {
		print "error: ".$result;
	} else {
		print "<table border='1'><tbody>";
		print "<td>".$result."</td>";
		print  "</tr>";
		print "</tbody></table>";
	}
	print "<br><a href=\"rest_api_client.php?insert=$insert&fields=$fields&values=$values\">Return to query page</a>";
}

?>
 </body>
</html>
