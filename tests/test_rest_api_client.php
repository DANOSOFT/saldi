<?php
// 20260921 CDX/LUI Exercise the actual reference client against a disposable HTTP peer.
if (PHP_SAPI !== 'cli') { exit('CLI only'); }
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/saldi-client-' . bin2hex(random_bytes(6));
mkdir($fixture, 0700);
file_put_contents($fixture . '/rest_api.php', <<<'PEER'
<?php
$config = json_decode(file_get_contents(__DIR__ . '/peer.json'), true);
$log = __DIR__ . '/calls.jsonl';
$calls = is_file($log) ? count(file($log)) : 0;
file_put_contents($log, json_encode($_GET) . "\n", FILE_APPEND);
if (($config['fail_at'] ?? 0) === $calls + 1) {
    // A write may have happened before this HTTP failure: the client cannot safely replay it.
    http_response_code($config['status'] ?? 503); echo $config['body'] ?? 'unavailable'; exit;
}
header('Content-Type: application/json');
echo json_encode(1000 + $calls + 1);
PEER
);
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false); fclose($socket);
$server = proc_open([PHP_BINARY,'-S',$address,'-t',$fixture], [0=>['pipe','r'],1=>['file',$fixture . '/server.log','a'],2=>['file',$fixture . '/server.log','a']], $pipes);
fclose($pipes[0]);
function checkClient(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS REST client: $message\n";
}
function clientCalls(): array {
    global $fixture;
    return is_file($fixture . '/calls.jsonl') ? array_map(static fn($row) => json_decode($row, true), file($fixture . '/calls.jsonl')) : [];
}
function makeClientCsv(array $rows): string {
    $stream = fopen('php://temp', 'w+');
    foreach ($rows as $row) { fputcsv($stream, $row, ',', '"', ''); }
    rewind($stream); $contents = stream_get_contents($stream); fclose($stream); return $contents;
}
function runActualClient(string $directory): string {
    global $root, $fixture, $address;
    $environment = getenv();
    $environment['SALDI_CLIENT_SERVER_URL'] = 'http://' . $address;
    $environment['SALDI_CLIENT_DB'] = 'owned_client_fixture';
    $environment['SALDI_CLIENT_USER'] = 'owned_fixture';
    $environment['SALDI_CLIENT_API_KEY'] = 'fixture-only-key';
    $environment['SALDI_CLIENT_ORDER_PATH'] = $directory;
    $environment['SALDI_CLIENT_STATE_PATH'] = $directory . '/state';
    $code = 'error_reporting(E_ALL); set_error_handler(static function($level,$text) { throw new RuntimeException($text); }); $_GET=["put_new_orders"=>1]; require $argv[1];';
    $child = proc_open([PHP_BINARY,'-d','display_errors=1','-r',$code,$root . '/api/rest_api_client.php'], [1=>['pipe','w'],2=>['pipe','w']], $pipes, $fixture, $environment);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $status = proc_close($child);
    if ($status !== 0 || $errors !== '' || preg_match('/(?:Fatal error|Warning|Deprecated):/i', $output)) {
        throw new RuntimeException('Client PHP failure: ' . $output . $errors);
    }
    return $output;
}
function removeClientFixture(string $path): void {
    foreach (new FilesystemIterator($path) as $entry) {
        if ($entry->isDir() && !$entry->isLink()) { removeClientFixture($entry->getPathname()); }
        else { unlink($entry->getPathname()); }
    }
    rmdir($path);
}
try {
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        set_error_handler(static fn() => true);
        $connection = stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        restore_error_handler();
        if ($connection) { fclose($connection); $ready = true; break; }
        usleep(10000);
    }
    if (!$ready) { throw new RuntimeException('Peer did not start'); }
    $row = array_fill(0,44,'');
    foreach ([0=>'101',1=>'21/09/2026',7=>'25',8=>'150',9=>'25',12=>'Quoted, "item"',13=>'ITEM-1',16=>'100',18=>'1',21=>'501',22=>'Test',23=>'Customer',24=>'Owned company',25=>'fixture@example.invalid',26=>'12345678',27=>'Street 1',29=>'City',30=>'1000',32=>'DK',33=>'Test',34=>'Customer',35=>'Owned delivery',38=>'Street 2',40=>'City',41=>'1000',43=>'SE'] as $index=>$value) { $row[$index]=$value; }
    $other = $row; $other[0]='102'; $other[13]='ITEM-2';
    $second = $row; $second[13]='ITEM-3'; $second[18]='2';
    $contents = makeClientCsv([$row,$other,$second]);
    foreach ([0,1,2,4,7] as $failureAt) {
        $directory = $fixture . '/case-' . $failureAt; mkdir($directory,0700);
        $source = $directory . '/owned.csv'; file_put_contents($source,$contents);
        file_put_contents($fixture . '/peer.json',json_encode(['fail_at'=>$failureAt]));
        if (is_file($fixture . '/calls.jsonl')) { unlink($fixture . '/calls.jsonl'); }
        $output = runActualClient($directory);
        $calls = clientCalls();
        if ($failureAt === 0) {
            checkClient(!is_file($source) && count($calls) === 7 && str_contains($output,'Done'), 'all headers, product rows and freight acknowledge before source removal');
            $headers = array_values(array_filter($calls,static fn($call)=>$call['action']==='insert_shop_order'));
            $freight = array_values(array_filter($calls,static fn($call)=>($call['varenr']??'')==='A90'));
            checkClient(count($headers)===2 && $headers[0]['land']==='DK' && $headers[0]['lev_land']==='SE' && $headers[0]['ordredate']==='2026-09-21', 'country parameters and order dates reach the peer correctly');
            checkClient(count($freight)===2 && $calls[6]['varenr']==='A90' && $calls[1]['beskrivelse']==='Quoted, "item"', 'grouped interleaved orders receive freight including final order and preserve quoted CSV');
            file_put_contents($source,$contents);
            runActualClient($directory);
            checkClient(!is_file($source) && count(clientCalls())===7, 'replayed completed source performs no duplicate remote writes');
        } else {
            checkClient(is_file($source) && file_get_contents($source)===$contents && count($calls)===$failureAt && str_contains($output,'source retained'), "failure at hop $failureAt retains exact source");
            file_put_contents($fixture . '/peer.json','{}');
            $again = runActualClient($directory);
            checkClient(is_file($source) && count(clientCalls())===$failureAt && str_contains($again,'unknown outcome'), "retry after hop $failureAt failure cannot duplicate earlier or uncertain writes");
            $journal = json_decode(file_get_contents(glob($directory . '/state/*.json')[0]),true);
            checkClient(count(array_filter($journal['steps'],static fn($step)=>$step['status']==='done'))===$failureAt-1 && count(array_filter($journal['steps'],static fn($step)=>$step['status']==='pending'))===1, "hop $failureAt journal separates acknowledged IDs from uncertain outcome");
        }
    }
    foreach (['zero-id'=>'0', 'html-error'=>'<html>Error</html>', 'empty-response'=>''] as $name=>$reply) {
        $directory=$fixture . '/' . $name; mkdir($directory,0700);
        file_put_contents($directory . '/owned.csv',$contents);
        unlink($fixture . '/calls.jsonl');
        file_put_contents($fixture . '/peer.json',json_encode(['fail_at'=>2,'status'=>200,'body'=>$reply]));
        $output=runActualClient($directory);
        checkClient(is_file($directory . '/owned.csv') && count(clientCalls())===2 && str_contains($output,'not acknowledged'), "$name is never mistaken for a successful line insert");
    }
    $directory=$fixture . '/case-2';
    $changed=$row; $changed[16]='999';
    file_put_contents($directory . '/owned.csv',makeClientCsv([$changed]));
    $before=count(clientCalls()); $output=runActualClient($directory);
    checkClient(count(clientCalls())===$before && str_contains($output,'Source or import journal changed'), 'changed failed source cannot bypass pending progress and replay writes');
    foreach (['invalid-later-row','inconsistent-header'] as $invalid) {
        $directory=$fixture . '/' . $invalid; mkdir($directory,0700);
        $bad=$second;
        if ($invalid==='invalid-later-row') { $bad[18]='not-a-number'; } else { $bad[32]='NO'; }
        file_put_contents($directory . '/owned.csv',makeClientCsv([$row,$bad]));
        $before=count(clientCalls()); $output=runActualClient($directory);
        checkClient(is_file($directory . '/owned.csv') && count(clientCalls())===$before && str_contains($output,'source retained'), "$invalid rejects complete export before first request");
    }
} finally {
    proc_terminate($server); proc_close($server); removeClientFixture($fixture);
}
