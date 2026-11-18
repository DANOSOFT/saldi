<?php
// tests/characterization/includes/support/run_formular_fax_to_mobile_mysql.php
//
// Child-process runner for FormularFaxToMobileMysqlTest: one SST-898 login on MySQL/MariaDB with the real
// includes/db_query.php and std_func.php wrappers, run from a temporary directory with temp/<db>.
//
// Usage: php run_formular_fax_to_mobile_mysql.php <socket> <database> <column charset> <connection charset> <db_type>
// <database> must be a fresh name of the form sst898_<8 hex digits>. The runner creates it (and stops if it exists
// already), and drops it at the end, also after an error, only when this run created it.
// Creates formularer and tekster in <column charset> (seeded over utf8mb4), switches the connection to
// <connection charset>, runs formularFaxToMobile() and formularFaxToMobileTekster() twice, checks the exact
// compare, and reads everything back over utf8mb4.
// Prints a single JSON object on the last line of stdout: {"first": [..], "second": [..], "exact": [..], "forms": [..],
// "texts": [..]} or {"error": "..."}.
//
// History:
// 20261118 CL/MMR SST-898 (review 10) Created.
// 20261118 CL/MMR SST-898 (review 11) Only a database this run created is dropped, also after an error; an existing one is never touched.
// 20261118 CL/MMR SST-898 (review 12) The name check uses \A and \z, so a trailing newline is refused before connecting.

if ($argc < 6) {
    fwrite(STDERR, "usage: php run_formular_fax_to_mobile_mysql.php <socket> <database> <column charset> <connection charset> <db_type>\n");
    exit(2);
}
list(, $socket, $database, $columnCharset, $connectionCharset, $db_type) = $argv;
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$out = [];
$error = null;
$connection = null;
$created = false;
$workDir = sys_get_temp_dir() . '/sst898my_' . getmypid() . '_' . bin2hex(random_bytes(4));
try {
    if (!preg_match('/\Asst898_[0-9a-f]{8}\z/', $database)) {
        throw new InvalidArgumentException("database name must be sst898_<8 hex digits>, got $database");
    }
    $columnCharset = preg_replace('/[^a-z0-9_]/', '', $columnCharset);
    $connection = mysqli_connect('localhost', getenv('SALDI_CHAR_MYSQL_USER') ?: 'root', getenv('SALDI_CHAR_MYSQL_PASS') ?: '', '', 0, $socket);
    mysqli_set_charset($connection, 'utf8mb4');
    // no IF NOT EXISTS: an existing database makes this fail, so it is never used or dropped
    mysqli_query($connection, "CREATE DATABASE `$database`");
    $created = true;
    mysqli_select_db($connection, $database);
    mkdir("$workDir/temp/$database", 0700, true);
    chdir($workDir);
    $_SERVER['REQUEST_URI'] = '/index/login.php';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $db = $sqdb = $database;
    $db_skriv_id = 0;
    $brugernavn = 'sst898_runner';
    require_once __DIR__ . '/../../../../includes/db_query.php';
    require_once __DIR__ . '/../../../../includes/std_func.php';
    require_once __DIR__ . '/../../../../includes/opdat_func/formularFaxToMobile.php';
    mysqli_query($connection, "CREATE TABLE formularer (id INT PRIMARY KEY, sprog VARCHAR(255), beskrivelse TEXT) CHARACTER SET $columnCharset");
    mysqli_query($connection, "CREATE TABLE tekster (id INT AUTO_INCREMENT PRIMARY KEY, tekst_id INT, sprog_id INT, tekst VARCHAR(255)) CHARACTER SET $columnCharset");
    foreach ([
        [1, 'Dansk', 'Fax: $egen_fax; Ærø'],
        [2, 'Dansk', 'Fax: $egen_fax'],
        [3, 'Spansk', 'Fax: $egen_fax; Señor'],
        [4, 'English', 'Fax: $egen_fax; Customers like this'],
        [5, null, 'Telefax: $egen_fax * Åbent'],
    ] as $row) {
        mysqli_execute_query($connection, 'INSERT INTO formularer VALUES (?, ?, ?)', $row);
    }
    foreach ([[378, 1, 'Telefax'], [378, 1, 'telefax'], [378, 1, 'Mobiltlf.'], [50, 2, 'Fax'], [50, 2, 'Mobile'], [5230, 1, 'Mobilnummer '], [5230, 3, 'Mobilnummer']] as $row) {
        mysqli_execute_query($connection, 'INSERT INTO tekster (tekst_id, sprog_id, tekst) VALUES (?, ?, ?)', $row);
    }
    mysqli_set_charset($connection, $connectionCharset);
    $out['first'] = [formularFaxToMobile(), formularFaxToMobileTekster()];
    $out['second'] = [formularFaxToMobile(), formularFaxToMobileTekster()];
    // the compare is exact: case and a trailing space do not match
    $info = formular_fax_to_mobile_connection();
    $out['exact'] = [];
    foreach (['Mobilnr.: $egen_mobile; Ærø', 'mobilnr.: $egen_mobile; ærø', 'Mobilnr.: $egen_mobile; Ærø '] as $value) {
        $value = $info['encoding'] === 'UTF-8' ? $value : mb_convert_encoding($value, (string)$info['encoding'], 'UTF-8');
        $count = db_fetch_array(db_select("SELECT COUNT(*) AS n FROM formularer WHERE " . formular_fax_to_mobile_same('beskrivelse', $value, $info['mysql_charset']), __FILE__ . " linje " . __LINE__));
        $out['exact'][] = (int)$count['n'];
    }
    mysqli_set_charset($connection, 'utf8mb4');
    $out['forms'] = array_column(mysqli_fetch_all(mysqli_query($connection, 'SELECT beskrivelse FROM formularer ORDER BY id'), MYSQLI_ASSOC), 'beskrivelse');
    $out['texts'] = mysqli_fetch_all(mysqli_query($connection, 'SELECT tekst_id, sprog_id, tekst FROM tekster ORDER BY id'), MYSQLI_NUM);
} catch (Throwable $e) {
    $error = get_class($e) . ': ' . $e->getMessage();
} finally {
    if ($created) {
        try {
            mysqli_query($connection, "DROP DATABASE `$database`");
        } catch (Throwable $e) {
            // keep the original error and add the cleanup failure
            $error = ($error === null ? '' : "$error | ") . 'cleanup: ' . get_class($e) . ': ' . $e->getMessage();
        }
    }
}
if ($error !== null) {
    $out['error'] = $error;
}
if (is_dir($workDir)) {
    chdir(sys_get_temp_dir());
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    rmdir($workDir);
}
echo json_encode($out, JSON_UNESCAPED_UNICODE) . "\n";
