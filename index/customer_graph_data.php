<?php
// filepath: /var/www/html/pblm/index/dashboardIncludes/customer_graph_data.php

// Start with a clean output buffer to avoid any other content
ob_clean();
ob_start();

// Include necessary files for database connection
@session_start();
$s_id = session_id();

require_once('../includes/connect.php');
require_once('../includes/online.php');

// Function to generate empty hour array
function generateArray() {
    $array = array();
    for($i = 0; $i < 24; $i++) {
        $hour = str_pad($i, 2, '0', STR_PAD_LEFT);
        $array[$hour] = 0;
    }
    return $array;
}

// Get days from POST request
$days = 30; // Default
if (isset($_POST['days'])) {
    $days = intval($_POST['days']);
}
$days = max(1, min($days, 365)); // Ensure days is between 1 and 365

$weekdayDates = array(
    'Monday' => array(),
    'Tuesday' => array(),
    'Wednesday' => array(),
    'Thursday' => array(),
    'Friday' => array(),
    'Saturday' => array(),
    'Sunday' => array()
);

$currentDate = new DateTime();
for ($i = 0; $i < $days; $i++) {
    $date = clone $currentDate;
    $date->sub(new DateInterval('P'.$i.'D'));
    $weekdayName = $date->format('l');
    if (array_key_exists($weekdayName, $weekdayDates)) {
        $weekdayDates[$weekdayName][] = $date->format('Y-m-d');
    }
}

$weekdayValues = array(
    'Monday' => generateArray(),
    'Tuesday' => generateArray(),
    'Wednesday' => generateArray(),
    'Thursday' => generateArray(),
    'Friday' => generateArray(),
    'Saturday' => generateArray(),
    'Sunday' => generateArray()
);

foreach ($weekdayDates as $weekday => $dates) {
    if (empty($dates)) continue;
    
    $clause = implode("' OR ordredate='", $dates);
    $q = db_select("
        SELECT SUBSTRING(tidspkt, 1, 2) AS hour_range, COUNT(*) AS count
        FROM ordrer
        WHERE (ordredate='$clause')
        GROUP BY hour_range
    ", __FILE__ . " linje " . __LINE__);
    
    while ($r = db_fetch_array($q)) {
        $hour_range = $r['hour_range'];
        $count = $r['count'] / sizeof($dates);
        $weekdayValues[$weekday][$hour_range] = $count;
    }
}

// Format data for ApexCharts heatmap
$heatmapSeries = [];
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$hours = ['00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', 
          '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'];

// Find max value for better color scaling
$maxValue = 0;
foreach ($weekdayValues as $values) {
    foreach ($values as $value) {
        if ($value > $maxValue) {
            $maxValue = $value;
        }
    }
}

// Build series data for each weekday
foreach ($weekdays as $index => $weekday) {
    $data = [];
    foreach ($hours as $hour) {
        $value = isset($weekdayValues[$weekday][$hour]) ? $weekdayValues[$weekday][$hour] : 0;
        $data[] = [
            'x' => $hour . ':00',
            'y' => round($value, 1)
        ];
    }
    
    $heatmapSeries[] = [
        'name' => $weekday,
        'data' => $data
    ];
}
// Clear any output that might have been generated
ob_end_clean();


// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'series' => $heatmapSeries,
    'maxValue' => $maxValue
]);
?>