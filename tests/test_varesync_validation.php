<?php
// 20260921 CDX/LUI Exercise complete product preflight and HTTP diagnostics with isolated PostgreSQL tables.
// 20260921 CDX/LUI Reject late invalid costs without writes; preserve optional cost create/update semantics.
namespace SaldiVaresyncValidationTest;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new \RuntimeException($message); });

function db_select($sql, $location = '') {
    $result = pg_query($GLOBALS['validationConnection'], $sql);
    if (!$result) { throw new \RuntimeException(pg_last_error($GLOBALS['validationConnection'])); }
    return $result;
}
function db_modify($sql, $location = '') { $GLOBALS['importWrites']++; return db_select($sql, $location); }
function db_fetch_array($result) { return pg_fetch_assoc($result); }
function db_num_rows($result) { return pg_num_rows($result); }
function db_escape_string($value) { return pg_escape_string($GLOBALS['validationConnection'], (string)$value); }
function shell_exec($command) { return ''; }
function system($command) {
    foreach (['shop_products.csv' => 'validationProducts', 'shop_variants.csv' => 'validationVariants'] as $name => $key) {
        if (strpos($command, '/files/' . $name) !== false) {
            file_put_contents('../temp/' . $GLOBALS['db'] . '/' . $name, $GLOBALS[$key]);
        }
    }
    return '';
}
function lagerreguler($id, $qty, $unused, $unused2, $date, $variant) {
    $GLOBALS['stockCalls']++;
    db_modify('UPDATE varer SET beholdning=' . (float)$qty . ' WHERE id=' . (int)$id);
}
function dkdecimal($value) { return number_format((float)$value, 2, ',', '.'); }
function scalar($sql) { return pg_fetch_row(db_select($sql))[0]; }
function snapshot() {
    $out = [];
    foreach (['varer', 'shop_varer', 'variant_typer', 'variant_varer', 'varianter'] as $table) {
        $out[$table] = scalar("SELECT md5(coalesce(string_agg(row_to_json(t)::text,',' ORDER BY id),'')) FROM $table t");
    }
    return $out;
}
function check($ok, $message) { if (!$ok) { throw new \RuntimeException($message); } }

if (PHP_SAPI === 'cli-server') {
    // Isolated test transport only, never an application authentication substitute.
    if (!getenv('SALDI_VALIDATION_HTTP_TOKEN') || !hash_equals(getenv('SALDI_VALIDATION_HTTP_TOKEN'), $_SERVER['HTTP_X_FIXTURE_TOKEN'] ?? '')) {
        http_response_code(403); exit;
    }
    $GLOBALS['validationConnection'] = pg_connect(getenv('SALDI_TEST_DSN'));
    require_once __DIR__ . '/../includes/std_func.php';
    eval('namespace SaldiVaresyncValidationTest; ' . preg_replace('/^<\?php|\?>\s*$/', '', file_get_contents(__DIR__ . '/../api/varesync.php')));
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $work = sys_get_temp_dir() . '/saldi-csv-validation-' . bin2hex(random_bytes(6));
    $GLOBALS['db'] = 'validation'; $GLOBALS['brugernavn'] = 'validation';
    mkdir($work . '/api', 0700, true); mkdir($work . '/temp/validation', 0700, true);
    chdir($work . '/api');
    try {
        db_select('BEGIN');
        db_select("CREATE TEMP TABLE varer(id serial PRIMARY KEY,varenr text,stregkode text,beskrivelse text,salgspris numeric,kostpris numeric,gruppe numeric,beholdning numeric,lukket text,min_lager int,varianter int DEFAULT 0,special_price numeric,special_from_date date,special_to_date date)");
        db_select("CREATE TEMP TABLE shop_varer(id serial PRIMARY KEY,saldi_id int,shop_id int,saldi_variant int,shop_variant int)");
        db_select("CREATE TEMP TABLE grupper(art text,box4 text)");
        db_select("INSERT INTO grupper VALUES ('API','http://isolated-fixture.invalid/api.php')");
        db_select("CREATE TEMP TABLE settings(var_name text,var_grp text,var_value text)");
        db_select("CREATE TEMP TABLE varianter(id serial PRIMARY KEY,beskrivelse text)");
        db_select("CREATE TEMP TABLE variant_typer(id serial PRIMARY KEY,variant_id int,beskrivelse text)");
        db_select("CREATE TEMP TABLE variant_varer(id serial PRIMARY KEY,vare_id int,variant_type int,variant_beholdning numeric,variant_stregkode text,lager int,variant_salgspris numeric,variant_kostpris numeric,variant_vejlpris numeric,variant_id int)");
        db_select("INSERT INTO varer(varenr,stregkode,beskrivelse,salgspris,kostpris,gruppe,beholdning) VALUES ('EXISTING','OLD','Preserve',10,4,1,3)");
        db_select("INSERT INTO shop_varer(saldi_id,shop_id,saldi_variant,shop_variant) VALUES (1,99,NULL,NULL)");
        $GLOBALS['validationProducts'] = $input['csv'];
        $GLOBALS['validationVariants'] = "varenr;parent_id;variant_id;stregkode;variant;variant_type;variant_text\n";
        $GLOBALS['importWrites'] = $GLOBALS['stockCalls'] = 0;
        $before = snapshot();
        ob_start();
        try { $result = varesync($input['mode']); } finally { $message = ob_get_clean(); }
        $after = snapshot();
        $rows = json_decode(scalar("SELECT json_agg(row_to_json(t) ORDER BY id) FROM (SELECT v.id,v.varenr,v.salgspris::text AS price,v.kostpris::text AS cost,v.gruppe::text AS product_group,s.shop_id FROM varer v LEFT JOIN shop_varer s ON s.saldi_id=v.id) t"), true);
        header('Content-Type: application/json');
        echo json_encode(['result' => $result, 'message' => $message, 'unchanged' => $before === $after,
            'write_calls' => $GLOBALS['importWrites'], 'stock_calls' => $GLOBALS['stockCalls'], 'rows' => $rows], JSON_THROW_ON_ERROR);
    } finally {
        db_select('ROLLBACK');
        foreach (glob($work . '/temp/validation/*') as $file) { unlink($file); }
        chdir(sys_get_temp_dir());
        rmdir($work . '/temp/validation'); rmdir($work . '/temp'); rmdir($work . '/api'); rmdir($work);
    }
    exit;
}
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
if (!getenv('SALDI_TEST_DSN')) { throw new \RuntimeException('SALDI_TEST_DSN is required'); }
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($listener, false); fclose($listener);
$token = bin2hex(random_bytes(24));
$log = tempnam(sys_get_temp_dir(), 'csv-validation-server-');
$env = getenv(); $env['SALDI_VALIDATION_HTTP_TOKEN'] = $token;
$process = proc_open([PHP_BINARY, '-S', $address, __FILE__], [0 => ['pipe','r'],1 => ['file',$log,'a'],2 => ['file',$log,'a']], $pipes, __DIR__, $env);
check(is_resource($process), 'HTTP fixture server starts'); fclose($pipes[0]);
function request($address, $token, $csv, $mode) {
    $context = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'timeout' => 10,
        'header' => "Content-Type: application/json\r\nX-Fixture-Token: $token\r\n",
        'content' => json_encode(['csv' => $csv, 'mode' => $mode], JSON_THROW_ON_ERROR)]]);
    $response = file_get_contents('http://' . $address . '/', false, $context);
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $match);
    return [(int)$match[1], json_decode($response, true, 512, JSON_THROW_ON_ERROR)];
}
try {
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $ready = false;
        set_error_handler(function () {});
        try { $probe = stream_socket_client('tcp://' . $address, $errno, $error, 0.1); } finally { restore_error_handler(); }
        if ($probe) { fclose($probe); $ready = true; break; }
        usleep(20000);
    }
    check($ready, 'HTTP fixture server ready');
    $header = "id;varenr;stregkode;salgspris;beskrivelse;gruppe;tilbud;notes\r\n";
    $badRows = [
        'salgspris' => '101;BAD-PRICE;B1;not-a-price;Rejected;1;;',
        'gruppe' => '101;BAD-GROUP;B1;123.45;Rejected;invalid-group;;',
        'shop_id' => ';MISSING-ID;B1;123.45;Rejected;1;;',
    ];
    foreach ([1, 2] as $mode) {
        foreach ($badRows as $field => $bad) {
            // A valid first row would change/create data if validation were interleaved with writes.
            $valid = $mode === 1 ? '102;NEW-FIRST;B2;10.01;New;1;;' : '99;EXISTING;OLD;10.01;Changed;1;;';
            [$status,$body] = request($address,$token,"\xEF\xBB\xBF" . $header . $valid . "\r\n\r\n" . $bad . "\r\n",$mode);
            $sku = explode(';',$bad)[1];
            check($status === 422 && $body['result'] === false, "$field mode $mode rejects through HTTP");
            check(str_contains($body['message'], "CSV row 4, SKU $sku: invalid $field"), "$field reports physical row, SKU and field");
            check($body['unchanged'] && $body['write_calls'] === 0 && $body['stock_calls'] === 0, "$field mode $mode preserves all products/mappings/stock before any write");
        }
    }
    $costHeader = "id;varenr;stregkode;salgspris;kostpris;beskrivelse;gruppe;tilbud;notes\r\n";
    foreach ([1, 2] as $mode) {
        foreach (['not-a-cost', '1e999', 'NaN', '12,34'] as $invalidCost) {
            $valid = $mode === 1 ? '102;NEW-FIRST;B2;10.01;5;New;1;;' : '99;EXISTING;OLD;10.01;5;Changed;1;;';
            [$status,$body] = request($address,$token,$costHeader . $valid . "\r\n\r\n101;BAD-COST;B1;123.45;$invalidCost;Rejected;1;;\r\n",$mode);
            check($status === 422 && $body['result'] === false && str_contains($body['message'], 'CSV row 4, SKU BAD-COST: invalid kostpris'), "Invalid cost $invalidCost mode $mode has exact HTTP/row/SKU/field diagnostic");
            check($body['unchanged'] && $body['write_calls'] === 0 && $body['stock_calls'] === 0, "Late invalid cost $invalidCost mode $mode preserves complete batch before writes");
        }
        foreach (['' => '0', '0' => '0', '-0.125' => '-0.125', '12.340' => '12.340', '+12.5' => '12.5', '1e2' => '100'] as $cost => $expectedCost) {
            $sku = $mode === 1 ? 'NEW-COST' : 'EXISTING';
            $id = $mode === 1 ? 101 : 99;
            [$status,$body] = request($address,$token,$costHeader . "$id;$sku;OLD;10;$cost;Preserve;1;;\r\n",$mode);
            $row = $body['rows'][$mode === 1 ? 1 : 0];
            // Existing update semantics: omitted/zero/negative cost does not
            // replace a product's cost; positive cost updates it exactly.
            $expected = $mode === 2 && (float)$cost <= 0 ? '4' : $expectedCost;
            check($status === 200 && $row['cost'] === $expected && count($body['rows']) === ($mode === 1 ? 2 : 1), "Supported cost $cost mode $mode preserves exact existing semantics");
        }
    }
    [$status,$body] = request($address,$token,';BAD-FIRST;B1;123.45;Rejected;1;;',1);
    check($status === 422 && str_contains($body['message'],'CSV row 1, SKU BAD-FIRST: invalid shop_id') && $body['unchanged'], 'Headerless first record is validated');
    foreach (['123.450' => '123.450', '0' => '0', '-0.125' => '-0.125', '+12.5' => '12.5', '1e2' => '100'] as $price => $expectedPrice) {
        [$status,$body] = request($address,$token,$header . ";EAN-FALLBACK;EAN123;$price;Valid;1;;\n;456;B2;$price;Valid numeric SKU;1;;\n",1);
        check($status === 200 && count($body['rows']) === 3, "Valid price $price imports both identity fallbacks");
        check($body['rows'][1]['shop_id'] === 123 && $body['rows'][2]['shop_id'] === 456, 'EAN and numeric-SKU fallback retain shop identity');
        check($body['rows'][1]['price'] === $expectedPrice && $body['rows'][2]['price'] === $expectedPrice, 'Valid decimal/exponent/zero price is preserved');
    }
    [$status,$body] = request($address,$token,$header . '101;BAD-<b>&;B1;invalid;Rejected;1;;',1);
    check($status === 422 && str_contains($body['message'], 'BAD-&lt;b&gt;&amp;') && !str_contains($body['message'], 'BAD-<b>'), 'SKU diagnostic is HTML escaped');
    echo "PASS: actual importer HTTP 422 row/SKU/field diagnostics; create/update mixed-batch no-write atomicity including late invalid cost; optional cost semantics; headerless records; valid numeric prices and EAN/numeric-SKU fallback\n";
} finally {
    proc_terminate($process); proc_close($process); unlink($log);
}
