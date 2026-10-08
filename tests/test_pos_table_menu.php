<?php
// 20260925 CDX/PHR Cover table-button selection, integer SQL IDs and the translated fallback.
require_once __DIR__ . '/../includes/std_func.php';
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function db_select($sql, $context) {
    $GLOBALS['queries'][] = $sql;
    if ($sql === 'SELECT id FROM table_plan ORDER BY id LIMIT 1') {
        $tables = $GLOBALS['tables'];
        ksort($tables);
        $id = key($tables);
        return $id === null ? false : array(0=>$id, 'id'=>$id);
    }
    if (!preg_match('/^select name from table_plan where id = (-?\d+)$/D', $sql, $match)) {
        throw new RuntimeException('Table lookup must contain only an integer ID');
    }
    $name = $GLOBALS['tables'][(int)$match[1]] ?? null;
    return $name === null ? false : array(0=>$name, 'name'=>$name);
}
function db_fetch_array($row) { return $row; }
function findtekst($key, $language) {
    if ($key !== '2267|Vælg bord' || $language !== 2) {
        throw new RuntimeException('Unexpected translation request');
    }
    return 'Choose table';
}
$source = file_get_contents(__DIR__ . '/../includes/posmenufunc.php');
$start = strpos($source, "\$bordnr = ifset(\$_GET, 'bordnr', -1);");
if ($start === false) {
    throw new RuntimeException('Table selection block not found');
}
// Stop after the lookup/fallback branch, before the enclosing old/new-system branch closes.
$end = strpos($source, "\n\t\t\t\t\t\t\t}", $start);
$selection = substr($source, $start, $end - $start);
$cases = array(
    array('GET takes precedence', array('bordnr'=>'2'), array('saldi_bordnr'=>'1'), array(1=>'One',2=>'Two'), 2, 'Two'),
    array('Missing GET uses cookie', array(), array('saldi_bordnr'=>'2'), array(2=>'Two'), 2, 'Two'),
    array('Missing GET and cookie defaults to one', array(), array(), array(1=>'One'), 1, 'One'),
    array('Sentinel selects first existing table', array('bordnr'=>'-1'), array('saldi_bordnr'=>'-1'), array(4=>'Four',9=>'Nine'), 4, 'Four'),
    array('Empty table plan uses translated fallback', array('bordnr'=>'-1'), array('saldi_bordnr'=>'-1'), array(), 0, 'Choose table'),
    array('Deleted table uses translated fallback', array('bordnr'=>'77'), array(), array(1=>'One'), 77, 'Choose table'),
    array('Empty selection is a valid integer lookup', array('bordnr'=>''), array(), array(1=>'One'), 0, 'Choose table'),
    array('Non-numeric cookie uses a valid integer lookup', array(), array('saldi_bordnr'=>'invalid'), array(1=>'One'), 0, 'Choose table'),
    array('SQL text cannot alter the lookup', array('bordnr'=>'2 OR 1=1'), array(), array(1=>'One',2=>'Two'), 2, 'Two'),
);
foreach ($cases as [$label, $_GET, $_COOKIE, $tables, $expectedId, $expectedText]) {
    $queries = array();
    $sprog_id = 2;
    $bordnr = $txt = null;
    eval($selection);
    if ($bordnr !== $expectedId || $txt !== $expectedText) {
        throw new RuntimeException($label);
    }
    echo "PASS: $label\n";
}
