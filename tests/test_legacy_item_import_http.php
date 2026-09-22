<?php
// 20260921 CDX/LUI Verify cost-update CSRF rejection over HTTP after a large page header.
// 20260920 CDX/LUI Exercise real upload/session/preview requests without shared tenant mutations.
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/saldi-import-http-' . bin2hex(random_bytes(6));
foreach (['lager','includes','sessions'] as $directory) { mkdir($fixture . '/' . $directory, 0700, true); }
copy($root . '/lager/vareimport.php', $fixture . '/lager/vareimport.php');
copy($root . '/lager/opdater_kostpriser.php', $fixture . '/lager/opdater_kostpriser.php');
copy($root . '/includes/legacyItemImport.php', $fixture . '/includes/legacyItemImport.php');
file_put_contents($fixture . '/includes/std_func.php', '<?php require_once ' . var_export($root . '/includes/std_func.php', true) . ';');
file_put_contents($fixture . '/includes/online.php', '<?php $db="fixture"; $regnaar=1; print "<html><body>" . str_repeat("<!-- header output -->", 1000);');
file_put_contents($fixture . '/includes/connect.php', <<<'STUB'
<?php
function db_escape_string($value) { return str_replace("'", "''", (string)$value); }
function db_select($sql, $source) {
    if (str_starts_with($sql, "SELECT kodenr,beskrivelse FROM grupper")) { $rows = [['kodenr'=>1,'beskrivelse'=>'Test group']]; }
    elseif (str_starts_with($sql, "SELECT id,kontonr,firmanavn FROM adresser")) { $rows = [['id'=>7,'kontonr'=>'8000','firmanavn'=>'Test supplier']]; }
    elseif ($sql === "SELECT id FROM varer WHERE varenr='HTTP-OWNED'") { $rows = [['id'=>3]]; }
    elseif ($sql === 'SELECT id FROM vare_lev WHERE vare_id=3 AND lev_id=7') { $rows = []; }
    else { throw new RuntimeException('Unexpected SQL: ' . $sql); }
    return (object)['rows'=>$rows];
}
function db_fetch_array($result) { return array_shift($result->rows); }
function db_modify($sql, $source) { file_put_contents(__DIR__ . '/../writes.log', $sql . "\n", FILE_APPEND); return "0\t"; }
function transaktion($action) { file_put_contents(__DIR__ . '/../writes.log', $action . "\n", FILE_APPEND); }
STUB
);
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-d', 'output_buffering=0', '-d', 'display_errors=1', '-d', 'error_reporting=32767', '-d', 'session.save_path=' . $fixture . '/sessions', '-S', $address, '-t', $fixture],
    [0=>['pipe','r'],1=>['file',$fixture . '/server.log','a'],2=>['file',$fixture . '/server.log','a']], $pipes);
if (!is_resource($process)) { throw new RuntimeException('Could not start fixture HTTP server'); }
fclose($pipes[0]);
$cookie = '';
function requestImport(string $method, string $body = '', string $type = 'application/x-www-form-urlencoded', string $page = 'vareimport.php'): array {
    global $address, $cookie;
    $headers = "Content-Type: $type\r\n" . ($cookie ? "Cookie: $cookie\r\n" : '');
    $context = stream_context_create(['http'=>['method'=>$method,'content'=>$body,'header'=>$headers,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5]]);
    $html = file_get_contents('http://' . $address . '/lager/' . $page, false, $context);
    $responseHeaders = $http_response_header;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    if (preg_match('/(?:Fatal error|Warning|Deprecated|Parse error):/i', $html)) { throw new RuntimeException('PHP failure: ' . $html); }
    return [$responseHeaders[0], $html];
}
function assertImportHttp(bool $condition, string $description): void {
    if (!$condition) { throw new RuntimeException($description); }
    echo "PASS item import HTTP: $description\n";
}
function removeImportFixture(string $path): void {
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { removeImportFixture($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    rmdir($path);
}
try {
    // The server readiness probe avoids warning suppression in the actual requests.
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        set_error_handler(static fn() => true);
        $connection = stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        restore_error_handler();
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(10000);
    }
    if (!$ready) { throw new RuntimeException('Fixture server did not start'); }
    [$status, $html] = requestImport('GET');
    preg_match("/name='csrf_token' value='([^']+)'/", $html, $match);
    $csrf = $match[1] ?? '';
    assertImportHttp(str_contains($status, '200') && strlen($csrf) === 64, 'GET creates session CSRF form');
    $boundary = 'TestBoundary' . bin2hex(random_bytes(8));
    $body = "--$boundary\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n$csrf\r\n";
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"uploadedfile\"; filename=\"../../outside.csv\"\r\nContent-Type: text/csv\r\n\r\nHTTP-OWNED;\"Owner's; <item>\";12345;75,50;stk\n\r\n--$boundary--\r\n";
    [$status, $html] = requestImport('POST', $body, 'multipart/form-data; boundary=' . $boundary);
    preg_match("/name='filnavn' value='([^']+)'/", $html, $match);
    $token = $match[1] ?? '';
    $ownedFiles = glob($fixture . '/temp/fixture/item-import-*.csv');
    assertImportHttp(str_contains($status, '200') && strlen($token) === 32 && count($ownedFiles) === 1 && !str_contains($html, $fixture) && str_contains($html, '&lt;item&gt;'), 'multipart upload stores an owned file and escapes preview');
    $form = ['csrf_token'=>$csrf,'filnavn'=>$token,'splitter'=>'Semikolon','feltnavn'=>['Eget varenr.','Beskrivelse','Salgspris','Kostpris','Enhed'],'varegrp'=>1,'leverandor'=>'8000','rabat'=>'0','submit'=>'Vis'];
    [$status, $html] = requestImport('POST', http_build_query($form));
    assertImportHttp(str_contains($status, '200') && str_contains($html, 'value="Flyt"') && !is_file($fixture . '/writes.log'), 'mapped preview enables import without writes');
    $forged = $form; $forged['submit'] = 'Flyt'; $forged['filnavn'] = '../../outside.csv';
    [$status, $html] = requestImport('POST', http_build_query($forged));
    assertImportHttp(str_contains($html, 'Hent importfilen igen') && !is_file($fixture . '/writes.log'), 'forged file reference cannot write');
    $forged = $form; $forged['submit'] = 'Flyt'; $forged['csrf_token'] = 'forged';
    [$status, $html] = requestImport('POST', http_build_query($forged));
    assertImportHttp(str_contains($status, '403') && !is_file($fixture . '/writes.log'), 'forged CSRF cannot write');
    $form['submit'] = 'Flyt';
    [$status, $html] = requestImport('POST', http_build_query($form));
    $writes = file_get_contents($fixture . '/writes.log');
    assertImportHttp(str_contains($status, '303') && str_starts_with($writes, "begin\n") && str_ends_with($writes, "commit\n") && str_contains($writes, "Owner''s; <item>") && str_contains($writes, "VALUES (3,7,'',75.5)") && !is_file($ownedFiles[0]), 'valid import commits mapped supplier ID and removes owned upload');
    [$status, $html] = requestImport('GET');
    assertImportHttp(str_contains($html, '1 varelinjer er importeret.') && !str_contains($html, "name='filnavn'"), 'redirect shows completion and clears upload session');
    [$status, $html] = requestImport('POST', 'csrf_token=forged', 'application/x-www-form-urlencoded', 'opdater_kostpriser.php');
    assertImportHttp(str_contains($status, '403') && str_contains($html, 'Ugyldig formular') && file_get_contents($fixture . '/writes.log') === $writes, 'cost update returns HTTP 403 with output_buffering=0 and oversized authenticated header');
} finally {
    proc_terminate($process);
    proc_close($process);
    removeImportFixture($fixture);
}
