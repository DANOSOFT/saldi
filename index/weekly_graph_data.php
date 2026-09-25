<?php
// filepath: ../index/dashboardIncludes/weekly_graph_data.php
// 20260921 CL/SZ MB-50: int-cast kontomin/kontomaks/regnaar before SQL interpolation
//                so a missing/malformed request no longer produces a raw DB error alert.
// 20260921 CL/SZ MB-50: guard the startLabel grupper lookup against a missing row,
//                matching the existing slutLabel guard (CodeRabbit).

// Start with a clean output buffer to avoid any other content
ob_clean();
ob_start();

// Include necessary files for database connection
@session_start();
$s_id = session_id();

require_once('../includes/connect.php');
require_once('../includes/online.php');


$kontomin = intval($_GET['kontomin'] ?? 0);
$kontomaks = intval($_GET['kontomaks'] ?? 0);
$regnaar = intval($_GET['regnaar'] ?? 0);
$regnstart = $_GET['regstart'] ?? null; //start of fiscal year
$regnslut = $_GET['regslut'] ?? null; //end of fiscal year

$selectedWeek = isset($_GET['week']) ? (int)$_GET['week'] : 1;

// Get fiscal year labels
    $qtxt = "SELECT beskrivelse FROM grupper WHERE kodenr='$regnaar' AND art='RA'";
    $r = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    if ($r) {
        $startResult = db_fetch_array($r);
        $startLabel = $startResult ? $startResult["beskrivelse"] : NULL;
    } else {
        $startLabel = "";
    }
    $qtxt = "SELECT beskrivelse FROM grupper WHERE kodenr='".($regnaar-1)."' AND art='RA'";
    $r = db_select($qtxt, __FILE__ . " linje " . __LINE__);
    if ($r) {
        $slutResult = db_fetch_array($r);
        $slutLabel = $slutResult ? $slutResult["beskrivelse"] : NULL; 
    } else {
        $slutLabel = "";
    }

$startYear = date('Y', strtotime($regnstart));
// Compute Monday of selected week


function getMondayOfISOWeek($week, $year) {
    $date = new DateTime();
    $date->setISODate($year, $week); // ISO Week number sets to Monday
    return strtotime($date->format('Y-m-d'));
}


$dayLabels = [findtekst('2671|Mandag', $sprog_id), findtekst('2672|Tirsdag', $sprog_id), findtekst('2673|Onsdag', $sprog_id), findtekst('2674|Torsdag', $sprog_id), findtekst('2675|Fredag', $sprog_id), findtekst('2676|Lørdag', $sprog_id), findtekst('2677|Søndag', $sprog_id)];

$revenue_now = [];
$revenue_last = [];

$regWeek = date('W', strtotime($selectedWeek));
$regYear = date('o', strtotime($startYear));


$mondayThisYear = getMondayOfISOWeek($selectedWeek, $regYear);
$mondayLastYear = getMondayOfISOWeek($selectedWeek, $regYear - 1);

for ($i = 0; $i < 7; $i++) {
    $currentDay = date('Y-m-d', strtotime("+$i days", $mondayThisYear));
    $sameDayLastYear = date('Y-m-d', strtotime("+$i days", $mondayLastYear));

    // This year's revenue
    $qxt = "
        SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
        FROM transaktioner T
        WHERE T.transdate = '$currentDay'
        AND T.kontonr >= $kontomin
        AND T.kontonr <= $kontomaks
    ";
    $q = db_select($qxt, __FILE__ . " linje " . __LINE__);
    $value = db_fetch_array($q)[0];
    $revenue_now[] = $value ?: 0;
    $currentDay1[] = $currentDay;
    $sameDayLastYear1[] = $sameDayLastYear;

    // Last year's same weekday
    $qxt = "
        SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
        FROM transaktioner T
        WHERE T.transdate = '$sameDayLastYear'
        AND T.kontonr >= $kontomin
        AND T.kontonr <= $kontomaks
    ";
    $q = db_select($qxt, __FILE__ . " linje " . __LINE__);
    $value = db_fetch_array($q)[0];
    $revenue_last[] = $value ?: 0;
   
}


// Clear any output that might have been generated 
ob_end_clean();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'dayLabels' => $dayLabels, 
    'revenue_now' => $revenue_now,
    'revenue_last' => $revenue_last,
    'startLabel' => $sameDayLastYear,
    'slutLabel' => $currentDay,
    'dayLastYear'=>$sameDayLastYear1,
    'currentDay' => $currentDay1
]);
?>