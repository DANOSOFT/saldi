<?php
// 20260929 CDX/PHR Verify one-time layout initialization, explicit switching and read-only rendering.
if (!getenv('SALDI_CHAR_DSN')) {
    echo "SKIP: set SALDI_CHAR_DSN, SALDI_CHAR_PGUSER and SALDI_CHAR_PGPASS.\n";
    exit;
}
require_once __DIR__ . '/../includes/std_func.php';
require_once __DIR__ . '/../includes/formFuncIncludes/htmlLayoutVersion.php';
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$pdo = new PDO(getenv('SALDI_CHAR_DSN'), getenv('SALDI_CHAR_PGUSER'), getenv('SALDI_CHAR_PGPASS'), array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
function db_select($sql, $context) { $GLOBALS['reads']++; return $GLOBALS['pdo']->query($sql); }
function db_fetch_array($result) { return $result->fetch(PDO::FETCH_BOTH); }
function db_modify($sql, $context) { $GLOBALS['writes']++; return $GLOBALS['pdo']->exec($sql); }
function checkLayoutVersion($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
$reads = $writes = 0;
$pdo->beginTransaction();
try {
    $pdo->exec('CREATE TEMP TABLE settings (var_name text,var_grp text,var_value text,var_description text)');
    $pdo->exec('CREATE TEMP TABLE grupper (id integer,art text,kodenr text,box3 text)');
    $db = 'not_initialized';
    checkLayoutVersion(formHtmlLayoutVersion() === 1 && $writes === 0, 'Printing before migration conservatively keeps the old layout without writes');
    $before = $reads;
    formHtmlLayoutVersion();
    checkLayoutVersion($before === $reads, 'Version lookup is cached instead of queried for every text or line');
    foreach (array('on'=>'1', ''=>'2', '0'=>'2') as $enabled=>$expected) {
        $pdo->exec('DELETE FROM settings; DELETE FROM grupper');
        $stmt = $pdo->prepare("INSERT INTO grupper VALUES (1,'PV','1',?)");
        $stmt->execute(array((string)$enabled));
        initializeFormHtmlLayoutVersion('postgresql');
        $db = 'tenant_' . $enabled;
        checkLayoutVersion(formHtmlLayoutVersion() === (int)$expected, 'Initial layout matches existing HTML selection: ' . var_export($enabled, true));
        initializeFormHtmlLayoutVersion('postgresql');
        checkLayoutVersion((int)$pdo->query('SELECT count(*) FROM settings')->fetchColumn() === 1, 'Running migration twice does not duplicate the setting');
        saveFormHtmlLayoutVersion('2');
        initializeFormHtmlLayoutVersion('postgresql');
        checkLayoutVersion($pdo->query('SELECT var_value FROM settings')->fetchColumn() === '2', 'An explicit upgrade survives later logins');
        saveFormHtmlLayoutVersion('1');
        initializeFormHtmlLayoutVersion('postgresql');
        checkLayoutVersion($pdo->query('SELECT var_value FROM settings')->fetchColumn() === '1', 'Returning to the old layout survives later logins');
        $before = $writes;
        foreach (array(null, '', '3', array('2'), "2' OR 1=1") as $invalid) { saveFormHtmlLayoutVersion($invalid); }
        checkLayoutVersion($writes === $before, 'Missing/invalid selections never reset the layout or enter SQL');
    }
    $pdo->exec('DELETE FROM settings; DELETE FROM grupper');
    initializeFormHtmlLayoutVersion('postgresql');
    checkLayoutVersion($pdo->query('SELECT var_value FROM settings')->fetchColumn() === '2', 'A new account without print settings starts on the new layout');
} finally {
    $pdo->rollBack();
}
