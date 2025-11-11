<?php

$start_time = microtime(true);
$log_file = "../temp/$db/regnskab_performance.log";

function log_performance($message, $start_time = null) {
    global $log_file;
    
    if (empty($log_file)) {
        return microtime(true);
    }
    
    $current_time = microtime(true);
    if ($start_time) {
        $elapsed = round(($current_time - $start_time) * 1000, 2);
        $message .= " (took {$elapsed}ms)";
    }
    $timestamp = date('Y-m-d H:i:s');
    
    @file_put_contents($log_file, "[$timestamp] REGNSKAB: $message\n", FILE_APPEND | LOCK_EX);
    return $current_time;
}

log_performance("Regnskab table setup started");

// Get accounting year info
$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$startmaaned=$row['box1'];
$startaar=$row['box2'];
$slutmaaned=$row['box3'];
$slutaar=$row['box4'];
$slutdato=31;
$regnskabsaar=$row['beskrivelse'];

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
    $slutdato=$slutdato-1;
    if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

// Column configuration
$columns_start = microtime(true);
$columns = array();

$columns[] = array(
    "field" => "kontonr",
    "headerName" => findtekst('804|Kontonr', $sprog_id),
    "width" => "1",
    "searchable" => true,
    "sortable" => true,
    "render" => function ($value, $row, $column) {
        if ($row['kontotype'] == 'H' || $row['kontotype'] == 'X') {
            return "<td><b>$value</b></td>";
        }
        $cursor = $row['ultimo'] != 0 ? "pointer" : "no-drop";
        return "<td style='cursor: $cursor'>$value</td>";
    }
);

$columns[] = array(
    "field" => "beskrivelse",
    "headerName" => findtekst('805|Kontonavn', $sprog_id),
    "width" => "3",
    "searchable" => true,
    "sortable" => true,
    "render" => function ($value, $row, $column) {
        if ($row['kontotype'] == 'H' || $row['kontotype'] == 'X') {
            return "<td colspan='13'><b>$value</b></td>";
        }
        $text = $row['kontotype'] == "Z" ? "Sumkonti $row[fra_kto] - $row[til_kto]" : "";
        return "<td title='$text'>$value</td>";
    }
);

if ($vis_valuta) {
    $columns[] = array(
        "field" => "valutanavn",
        "headerName" => findtekst(776, $sprog_id),
        "width" => "0.5",
        "align" => "center",
        "searchable" => true,
        "sortable" => true
    );
}

$columns[] = array(
    "field" => "primo",
    "headerName" => findtekst(1229, $sprog_id),
    "description" => findtekst(1625, $sprog_id),
    "width" => "1",
    "type" => "number",
    "align" => "right",
    "searchable" => true,
    "sortable" => true
);

// Add month columns dynamically
for ($z=1; $z<=$maanedantal; $z++) {
    $columns[] = array(
        "field" => "md_$z",
        "headerName" => "MD $z",
        "width" => "1",
        "type" => "number",
        "align" => "right",
        "searchable" => true,
        "sortable" => false,
        "render" => function ($value, $row, $column) use ($z) {
            if ($row['kontotype'] == 'Z') {
                return "<td align='right'>$value</td>";
            }
            return "<td align='right'><a href='kontospec.php?kontonr=$row[kontonr]&month=$z'>$value</a></td>";
        }
    );
}

$columns[] = array(
    "field" => "ultimo",
    "headerName" => findtekst('2373|I alt', $sprog_id),
    "width" => "1",
    "type" => "number",
    "align" => "right",
    "searchable" => true,
    "sortable" => true
);

log_performance("Column configuration completed", $columns_start);

// No filters for now - can be added later
$filters = array();

// Row style function
$rowStyleFn = function($row) {
    if ($row['kontotype'] == "Z") {
        return "background-color: #ffaaaa;";
    }
    return "";
};

log_performance("Regnskab table setup completed");
?>