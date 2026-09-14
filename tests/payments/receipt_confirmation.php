<?php
/**
 * Exercise the real receipt scripts with a stubbed session/database and temporary files.
 * Run as an unprivileged user: php tests/payments/receipt_confirmation.php
 */
declare(strict_types=1);

$root = sys_get_temp_dir() . '/saldi-receipt-test-' . bin2hex(random_bytes(8));
mkdir($root . '/debitor/payments', 0777, true);
mkdir($root . '/includes/stdFunc', 0777, true);
mkdir($root . '/temp', 0777, true);
foreach (['lane3000_afstemning.php', 'save_receipt.php', 'print_receipt.php'] as $file) {
    copy(__DIR__ . '/../../debitor/payments/' . $file, $root . '/debitor/payments/' . $file);
}
file_put_contents($root . '/includes/connect.php', '<?php');
file_put_contents($root . '/includes/std_func.php', '<?php');
file_put_contents($root . '/includes/stdFunc/dkDecimal.php', '<?php');
file_put_contents($root . '/includes/stdFunc/usDecimal.php', '<?php');
file_put_contents($root . '/includes/online.php', <<<'STUB'
<?php
$db = $_GET['scenario'];
if (in_array($db, ['unauthenticated', 'unauthorized'], true)) {
    echo '<html>Access denied</html>';
    exit;
}
if (($json['confirm_saved'] ?? false) && ($modulnr ?? null) !== 5) {
    throw new RuntimeException('POS authorization module not selected');
}
$regnaar = '2026 OR 1=1';
$sprog_id = 1;
$printserver = 'localhost';
$_COOKIE['saldi_pos'] = 1;
function db_select($query, $where) {
    if ($_GET['scenario'] === 'page' && !str_contains($query, "fiscal_year = '2026'")) {
        throw new RuntimeException('Fiscal year was not cast before the query');
    }
    return $query;
}
function db_fetch_array($query) {
    if (str_contains($query, 'box3')) return ['box3' => 'localhost'];
    if (str_contains($query, 'box4')) return ["terminal\"</script>"];
    return ['firmanavn' => 'Test shop', 'addr1' => 'Test street', 'postnr' => '1234', 'bynavn' => 'Test town', 'cvrnr' => '12345678'];
}
function findtekst($label, $language) { return explode('|', $label, 2)[1]; }
function get_settings_value($name, $group, $default, $user, $pos) {
    if (!is_int($pos)) throw new RuntimeException('POS ID must be an integer');
    return bin2hex(random_bytes(16)) . '"</script>';
}
STUB
);

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = $root . '/server.log';
$process = proc_open([PHP_BINARY, '-d', 'error_reporting=32767', '-d', 'display_errors=1', '-S', $address, '-t', $root],
    [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
fclose($pipes[0]);

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}

function removeTree($path) {
    if (is_dir($path)) {
        chmod($path, 0777);
        foreach (scandir($path) as $name) {
            if ($name !== '.' && $name !== '..') removeTree($path . '/' . $name);
        }
        rmdir($path);
    } else {
        unlink($path);
    }
}

try {
    // Wait for a socket, without sending any real terminal or database requests.
    $ready = false;
    for ($i = 0; $i < 100; $i++) {
        $socket = stream_socket_client('tcp://' . $address, $errno, $error, 0.01, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT);
        $read = null; $write = [$socket]; $except = null;
        if (stream_select($read, $write, $except, 0, 10000) && stream_socket_get_name($socket, true)) {
            $ready = true;
            fclose($socket);
            break;
        }
        fclose($socket);
        usleep(10000);
    }
    check($ready, 'test server started');
    $page = file_get_contents('http://' . $address . '/debitor/payments/lane3000_afstemning.php?scenario=page&id=123%22%3Cscript%3E');
    check(substr_count($page, '<script>') === 1 && substr_count($page, '</script>') === 1, 'PHP safely serializes credentials and terminal IDs into JavaScript');
    check(str_contains($page, '?id=123&godkendt=afvist'), 'Back uses a numeric order ID');
    check(!str_contains($page, 'Warning') && !str_contains($page, 'Deprecated'), 'page renders without PHP warnings under E_ALL');
    foreach (['success', 'print_failure', 'raw_failure', 'legacy', 'unauthenticated', 'unauthorized'] as $scenario) {
        $directory = $root . '/temp/' . $scenario;
        if ($scenario === 'print_failure') {
            mkdir($directory . '/receipt_1.txt', 0777, true);
        } elseif ($scenario === 'raw_failure') {
            mkdir($directory, 0555);
            check(!is_writable($directory), 'raw-write failure fixture is read-only (run as an unprivileged user)');
        }
        $data = "Indsamlet 0\nTotal 0,00\n";
        $body = ['data' => $data, 'id' => 123, 'type' => 'move3500'];
        if ($scenario !== 'legacy') $body['confirm_saved'] = true;
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'header' => 'Content-Type: application/json',
            'content' => json_encode($body), 'ignore_errors' => true,
        ]]);
        $result = file_get_contents('http://' . $address . '/debitor/payments/save_receipt.php?scenario=' . $scenario, false, $context);
        if ($scenario === 'success') {
            check(json_decode($result, true) === ['saved' => true], 'success returns a clean JSON acknowledgement with E_ALL enabled');
            check(str_contains($http_response_header[0], '200'), 'success returns HTTP 200');
            check(json_decode(file_get_contents($directory . '/receipt_123.txt'), true) === $data, 'raw receipt is saved');
            check(str_contains(file_get_contents($directory . '/receipt_1.txt'), 'Total 0,00'), 'print-ready receipt is saved');
        } elseif ($scenario === 'legacy') {
            check(str_contains($result, '<pre>') && !str_contains($result, '"saved"'), 'legacy caller keeps its existing response');
            check(str_contains(file_get_contents($directory . '/receipt_1.txt'), 'Total 0,00'), 'legacy caller still prepares its receipt');
        } elseif (in_array($scenario, ['unauthenticated', 'unauthorized'], true)) {
            check(!str_contains($result, '"saved":true') && !is_dir($directory), $scenario . ' cannot save or receive success');
        } else {
            check(str_contains($http_response_header[0], '500'), $scenario . ' returns HTTP 500');
            check(str_contains($result, '"saved":false') && !str_contains($result, '"saved":true'), $scenario . ' never acknowledges success');
            check(str_contains($result, 'Warning'), $scenario . ' leaves filesystem warnings visible');
        }
    }
    $logs = file_get_contents($log);
    check(!preg_match('/Undefined|Deprecated|Fatal error/', $logs), 'no undefined-variable warnings, deprecations or fatal errors');
} finally {
    proc_terminate($process);
    proc_close($process);
    removeTree($root);
}
