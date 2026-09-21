<?php
// 20260920 CDX/LH Exercise the page's actual save-completion block through HTTP redirects.
// Starts an isolated local PHP server and uses temporary files instead of tenant data.
error_reporting(E_ALL);
$root = dirname(__DIR__);
$source = file_get_contents($root . '/finans/kassekladde.php');
$start = strpos($source, 'if (!$fejl && $kladde_id) {' . "\n\topdater(\$kladde_id);");
$end = strpos($source, "\n/*", $start);
if ($start === false || $end === false) throw new RuntimeException('Journal completion block not found');
$completion = substr($source, $start, $end - $start);
$dir = sys_get_temp_dir() . '/saldi-journal-redirect-' . bin2hex(random_bytes(8));
mkdir($dir, 0700);
$bootstrap = '<?php' . "\n" .
    'require_once ' . var_export($root . '/includes/std_func.php', true) . ';' . "\n" .
    'require_once ' . var_export($root . '/finans/kassekladde_includes/journalSaveRedirect.php', true) . ';' . "\n";
$fixture = <<<'CODE'
error_reporting(E_ALL);
ob_start();
session_start();
$kladde_id = 42;
$fejl = isset($_POST['invalid']);
$db_modify_fejl = isset($_POST['write_error']);
$submit = $_POST['submit'] ?? null;
$fokus = $_POST['fokus'] ?? '';
$vat_reset_notice = $_POST['notice'] ?? '';
function opdater($journalId) {
    if ($_POST && empty($_POST['write_error'])) {
        $count = is_file(__DIR__ . '/writes') ? (int)file_get_contents(__DIR__ . '/writes') : 0;
        file_put_contents(__DIR__ . '/writes', $count + 1);
    }
}
function initializePositions($journalId) {}
function db_modify($sql, $trace) { return "0\tquery accepted"; }
CODE;
file_put_contents($dir . '/kassekladde.php', $bootstrap . $fixture . "\n" . $completion . <<<'CODE'

header('Content-Type: application/json');
echo json_encode(['notice'=>$vat_reset_notice,'writes'=>is_file(__DIR__ . '/writes') ? (int)file_get_contents(__DIR__ . '/writes') : 0]);
CODE
);
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-S', $address, '-t', $dir],
    [0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Failed to start isolated HTTP server');
fclose($pipes[0]);
/** @return array{status: int, headers: array, body: string} */
function journalRequest($url, $post = null, $cookie = '') {
    $headers = 'Cookie: ' . $cookie . "\r\n";
    $options = ['method'=>$post === null ? 'GET' : 'POST', 'follow_location'=>0, 'ignore_errors'=>true, 'timeout'=>5];
    if ($post !== null) {
        $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $options['content'] = http_build_query($post);
    }
    $options['header'] = $headers;
    $body = file_get_contents($url, false, stream_context_create(['http'=>$options]));
    if ($body === false) throw new RuntimeException('HTTP request failed');
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $match);
    $result = [];
    foreach (array_slice($http_response_header,1) as $line) {
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) $result[strtolower($parts[0])] = trim($parts[1]);
    }
    return ['status'=>(int)$match[1], 'headers'=>$result, 'body'=>$body];
}
try {
    $ready = false;
    for ($i=0;$i<50;$i++) {
        if (is_file($dir . '/server.log') && strpos(file_get_contents($dir . '/server.log'), 'started') !== false) {
            $ready = true;
            break;
        }
        usleep(20000);
    }
    if (!$ready) throw new RuntimeException('Isolated server did not start');
    $base = 'http://' . $address . '/';
    $saved = journalRequest($base . 'kassekladde.php?kksort=amount&kkdir=desc', ['submit'=>'save','fokus'=>'belo3','notice'=>'VAT restored']);
    if ($saved['status'] !== 303 || !isset($saved['headers']['location'])) throw new RuntimeException('Successful save did not redirect');
    $cookie = explode(';', $saved['headers']['set-cookie'])[0];
    $location = $saved['headers']['location'];
    $read = journalRequest($base . $location, null, $cookie);
    $data = json_decode($read['body'], true);
    if ($read['status'] !== 200 || $data !== ['notice'=>'VAT restored','writes'=>1]) throw new RuntimeException('Redirect lost notice or replayed the save');
    $refresh = journalRequest($base . $location, null, $cookie);
    if (json_decode($refresh['body'], true) !== ['notice'=>'','writes'=>1]) throw new RuntimeException('Refresh repeated save or notice');
    foreach (['invalid','write_error'] as $failure) {
        $rejected = journalRequest($base . 'kassekladde.php', ['submit'=>'save',$failure=>1], $cookie);
        if ($rejected['status'] !== 200 || isset($rejected['headers']['location'])) throw new RuntimeException('Failed save redirected away from its errors');
    }
    $lookup = journalRequest($base . 'kassekladde.php', ['submit'=>'lookup'], $cookie);
    if (isset($lookup['headers']['location'])) throw new RuntimeException('Lookup was redirected');
    echo "PASS: actual save-completion HTTP block redirects successful saves, keeps notices once, makes refresh safe and retains failure/lookup responses.\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (glob($dir . '/*') as $path) unlink($path);
    rmdir($dir);
}
