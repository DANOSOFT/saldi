<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/dashboard.php --- lap 4.1.1 --- 2025.08.13 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2024-2025 saldi.dk aps
// ----------------------------------------------------------------------
//20241004 MMK  
//20241018 LOE checks that some variables are set before using. 
//20250513 Sawaneh display number of users online. 
//20250805 LOE added close button to settings popup. and also added weekly graph snippet
@session_start();
$s_id = session_id();


$css = "../css/dashboard.css?v=1";
print "<title>Overblik</title>";

include ("../includes/std_func.php");
include ("../includes/connect.php");
# get superUsers
$qtxt = "SELECT brugernavn FROM brugere";
$result = db_select($qtxt, __FILE__ . " linje " . __LINE__);
$superUsers = array();
while ($row = db_fetch_array($result)) {
    $superUsers[] = $row['brugernavn'];
}
# Get database name of current online user
$qtxt = "SELECT db FROM online WHERE session_id='$s_id' limit 1";
$db = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

# Get list of active users
$superUsersPlaceholders = implode("','", $superUsers);
$qtxt = "SELECT brugernavn, logtime FROM online WHERE db='$db' AND brugernavn NOT IN ('$superUsersPlaceholders')";
$online_people = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

# Get amount of active users
$timestamp = (int) date("U") - (60*60);
$qtxt = "SELECT count(brugernavn) FROM online WHERE db='$db' AND logtime > '$timestamp' AND revisor is not true AND brugernavn NOT IN ('$superUsersPlaceholders')";
$online_people_amount = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

$newssnippet = get_settings_value("nyhed", "dashboard", "");

include ("../includes/online.php");
include ("../includes/stdFunc/dkDecimal.php");

$query = db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);

$box1 = (int)$row['box1'];
$box2 = (int)$row['box2'];
$box3 = (int)$row['box3'];
$box4 = (int)$row['box4'];

$startmaaned = if_isset($box1, 1);
$startaar = if_isset($box2, 2000);
$slutmaaned = if_isset($box3, 1);
$slutaar = if_isset($box4, 2001);

$slutdato=31;
$regnskabsaar=$row['beskrivelse'];

while (!checkdate($slutmaaned,$slutdato,$slutaar)){
	$slutdato=$slutdato-1;
	if ($slutdato<28) break 1;
}

$regnstart = $startaar. "-" . $startmaaned . "-" . '01';
$regnslut = $slutaar . "-" . $slutmaaned . "-" . $slutdato;

include ("dashboardIncludes/revenue_graph.php");
include ("dashboardIncludes/customer_graph.php");

include ("dashboardIncludes/pos_row.php");



function check_permissions($permarr) {
	global $rettigheder;
	$filtered = array_filter($permarr, function ($item) use ($rettigheder) {
		return (substr($rettigheder, $item, 1) == "1");
	});
	return !empty($filtered);
}



# If the user has finans -> regnskab or finans -> reports level access
 if (!check_permissions(array(3,4)) || is_null($regnaar) ) {
	$qtxt = "SELECT firmanavn FROM adresser WHERE art='S'";
	$name = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

	print "<div style='display: flex; flex-direction: column; padding: 2em 1em; gap: 2em;' class='content'>";

	# Titlebar
	print "<div style='display: flex; justify-content: space-between; flex-wrap: wrap'>";
	print "<h1>".findtekst('2574|Velkommen', $sprog_id)." - $name</h1>";
  	if (is_null($regnaar)) {
		print "<p>".findtekst('2575|Der er i øjeblikket intet aktivt regnskabsår. Aktivér et regnskabsår gennem System » Indstillinger » Regnskabsår', $sprog_id)."</p>";
	}
	print "<div style='display: flex; gap: 2em'>";
	$qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
	$state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$qtxt = "SELECT id FROM settings WHERE var_name = 'orderXpress' AND var_value='on'";
	$orderXpress = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($state) {
		print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../debitor/pos_ordre.php\"'>".findtekst('2149|Åbn kassesystem', $sprog_id)."</button>";
	} elseif ($orderXpress) {
		print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../sager/sager.php\"'>".findtekst('2150|Åbn sagsstyring', $sprog_id)."</button>";
	}

	print "</div>";
	print "</div>";
//	print "<p title='For at få adgang skal du aktivere finansmodulet for brugeren'>Du har ikke adgang til at se virksomhedsoversigten</p>";
	print "<img src='../img/Saldi_Main_Logo.png' style='position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 40%'></img>";
	exit;
 }


print '<script src="../javascript/chart.js"></script>';
print '<script src="../javascript/apexcharts.js"></script>';

function generateArray() {
    $result = array();
    for ($i = 0; $i < 24; $i++) {
        $key = str_pad($i, 2, '0', STR_PAD_LEFT);
        $result[$key] = 0;
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   $data = file_get_contents("php://input");
   update_settings_value("kontomin",         "dashboard_values", if_isset($_POST['kontomin'],          "0"),    "Show the revenue this month per last month");
   update_settings_value("kontomaks",        "dashboard_values", if_isset($_POST['kontomaks'],         "2000"), "Show the revenue this month per last month");
   update_settings_value("revweek",          "dashboard_toggles", if_isset($_POST['revweek'],          "off"),  "Show the revenue this week per last week");
   update_settings_value("revmonth",         "dashboard_toggles", if_isset($_POST['revmonth'],         "off"),  "Show the revenue this month per last month");
   update_settings_value("revyear",          "dashboard_toggles", if_isset($_POST['revyear'],          "off"),  "Show the revenue this year per last year");
   update_settings_value("ordercount",       "dashboard_toggles", if_isset($_POST['ordercount'],       "off"),  "Show the amount of orders currently active that are not older than 30 days");
   update_settings_value("onlineusers",      "dashboard_toggles", if_isset($_POST['onlineusers'],      "off"),  "Show the amount of orders currently active that are not older than 30 days");
   update_settings_value("revgraph",         "dashboard_toggles", if_isset($_POST['revgraph'],         "off"),  "Show the revenue graph");
   update_settings_value("customergraph",    "dashboard_toggles", if_isset($_POST['customergraph'],    "off"),  "Sho wthe customer graph per hour");
   update_settings_value("vatcount",         "dashboard_toggles", if_isset($_POST['vatcount'],         "off"),   "Show the vat");
   update_settings_value("varegrp_doughnut", "dashboard_toggles", if_isset($_POST['varegrpdoughnut'],  "off"),   "Show the sales of varegrupper in the year in a doughnut");
}

if (isset($_GET['close_snippet']) && $_GET['close_snippet'] == '1') {
   update_settings_value("closed_news_snippet", "dashboard", $newssnippet, "The newssnippet that was closed by the user");
}
if (isset($_GET['hidden']) && $_GET['hidden'] == '1') {
   update_settings_value("hide_dash", "dashboard", 1, "Weather or not the newssnippet is showen to the user", $user=$bruger_id);
}
if (isset($_GET['hidden']) && $_GET['hidden'] == '0') {
   update_settings_value("hide_dash", "dashboard", 0, "Weather or not the newssnippet is showen to the user", $user=$bruger_id);
}

$kontomin = get_settings_value("kontomin", "dashboard_values", 0);
$kontomaks = get_settings_value("kontomaks", "dashboard_values", 2000);

$revweek = get_settings_value("revweek", "dashboard_toggles", "on");
$revmonth = get_settings_value("revmonth", "dashboard_toggles", "on");
$revyear = get_settings_value("revyear", "dashboard_toggles", "on");
$ordercount = get_settings_value("ordercount", "dashboard_toggles", "on");
$onlineusers = get_settings_value("onlineusers", "dashboard_toggles", "off");
$revgraph = get_settings_value("revgraph", "dashboard_toggles", "on");
$customergraph = get_settings_value("customergraph", "dashboard_toggles", "off");
$vat_count = get_settings_value("vatcount", "dashboard_toggles", "on");
$varegrp_doughnut = get_settings_value("varegrp_doughnut", "dashboard_toggles", "on");

$closed_newssnippet = get_settings_value("closed_news_snippet", "dashboard", "");
$hide_dash = get_settings_value("hide_dash", "dashboard", "0", $user=$bruger_id);

/* 
# Omsætning i et tidsrum

SELECT SUM(T.kredit - T.debet)
FROM transaktioner T
WHERE T.transdate >= '2024-01-01'
AND T.transdate <= '2024-02-01'
AND T.kontonr < 2000;
 
# Ufakturerede ordrer
SELECT count(*) FROM "ordrer" WHERE "status" < '3' AND "ordredate" > '2024-03-09'

 */

function formatNumber($number, $dkFormat = true) {
    global $sprog_id;
    $suffix = '';
    if ($number >= 1000 && $number < 1000000) {
        $number = $number / 1000;
        $suffix = ' '.findtekst('2166|tusind', $sprog_id);
    } elseif ($number >= 1000000 && $number < 1000000000) {
        $number = $number / 1000000;
        $suffix = ' '.findtekst('2167|millioner', $sprog_id);
    } elseif ($number >= 1000000000 && $number < 1000000000000) {
        $number = $number / 1000000000;
        $suffix = ' '.findtekst('2168|milliarder', $sprog_id);
    } elseif ($number >= 1000000000000) {
	$number = $number / 1000000000000;
        $suffix = ' '.findtekst('2169|billioner', $sprog_id);
    }

    if ($dkFormat) {
        return dkDecimal($number, 2) . $suffix;
    }
    return $number . $suffix;
}

function key_value($title, $value, $description="") {
	print "
<div style='
	flex: 1;
	background-color: #fff;
	border-radius: 5px;
	box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
	padding: 1.4em 2em;
' class='keynumberbox'>
	<h4 style='margin: 0; color: #999'>$title</h4>
	<h2 style='margin: 0'>$value</h2>
	$description
</div>
";
}

$qtxt = "SELECT firmanavn FROM adresser WHERE art='S'";
$name = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

print "<div style='display: flex; flex-direction: column; padding: 2em 1em; gap: 2em;' class='content'>";

# Newsbar
if ((isset($closed_newssnippet) && $closed_newssnippet) != isset($newssnippet) && $newssnippet != '') {
	print "<div id='newsbar'><span><b>Nyt i saldi:</b> $newssnippet</span><span id='closebtn' onClick=\"document.location.href = 'dashboard.php?close_snippet=1'\">x</span></div>";
}

# Titlebar
print "<div style='display: flex; justify-content: space-between; flex-wrap: wrap; gap: 2em; align-items: center;'>";
print "<h1>".findtekst('2224|Oversigt', $sprog_id)." - $name</h1>";

print "<div style='display: flex; gap: 2em;'>";

# Regnaar selector
include "dashboardIncludes/regnaar.php";

print "<button style='padding: 1em; cursor: pointer' onclick='document.location.href = \"dashboard.php?hidden=". ($hide_dash === "1" ? "0" : "1") ."\"'>". ($hide_dash !== "1" ? findtekst('1132|Skjul', $sprog_id) : findtekst('1133|Vis', $sprog_id)) ." ".strtolower(findtekst('2224|Oversigt', $sprog_id))."</button>";
if ($hide_dash !== "1") print "<button style='padding: 1em; cursor: pointer' onclick='document.getElementById(\"settingpopup\").style.display = \"block\"'>".findtekst('2148|Rediger', $sprog_id). " " .strtolower(findtekst('2224|Oversigt', $sprog_id))."</button>";

# Kassesystem eller ej
$qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
$state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$qtxt = "SELECT id FROM settings WHERE var_name = 'orderXpress' AND var_value='on'";
$orderXpress = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($state) {
	print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../debitor/pos_ordre.php\"'>".findtekst('2149|Åbn kassesystem', $sprog_id)."</button>";
} elseif ($orderXpress) {
	print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../sager/sager.php\"'>".findtekst('2150|Åbn sagsstyring', $sprog_id)."</button>";
} else {
	print "<button style='padding: 1em; cursor: not-allowed' disabled>" .findtekst('2149|Åbn kassesystem', $sprog_id)."</button>";
}
	
print "</div>";
print "</div>";

if ($hide_dash === "1" || is_null($regnaar)) {
	exit;
}

print "<div style='display: flex; gap: 2em; flex-wrap: wrap'>";

# #######################################
#  Omsætning for ugen         
# #######################################
if( $revweek === "on") {
  include("./dashboardIncludes/revenue_week.php");
}

# #######################################
#
#	Samlet for Måneden
#
# #######################################

if ($revmonth === "on") {
	include("./dashboardIncludes/revenue_month.php");
}

# #######################################
#
#	Samlet for året
#
# #######################################

if ($revyear === "on") {
	include("./dashboardIncludes/revenue_year.php");
}

# #######################################
#
#	Åbne ordre
#
# #######################################

if ($ordercount === "on") {
	$currentDate = new DateTime();

	$currentDate->sub(new DateInterval('P30D'));
	$thirtyDaysAgo = $currentDate->format('Y-m-d');

  # Check hurtigfakt
  if (db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '3' and box4='on'",__FILE__ . " linje " . __LINE__))) {
    $qtxt = "SELECT count(*), COALESCE(sum(\"sum\"), 0) FROM ordrer WHERE status <= 3 AND art = 'DO' AND ordredate > '$thirtyDaysAgo'";
  } else {
    $qtxt = "SELECT count(*), COALESCE(sum(\"sum\"), 0) FROM ordrer WHERE status = 2 AND art = 'DO' AND ordredate > '$thirtyDaysAgo'";
  }

	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$data = db_fetch_array($q);
	$active_orders = formatNumber((int)$data[0], $dkFormat=false);
	$active_total = formatNumber($data[1]);
	key_value(findtekst('2378|Ufakturerede ordrer de sidste 30 dage', $sprog_id), $active_orders, "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'><span style='color: #999'>".findtekst('2381|Hvilket svarer til', $sprog_id)." <span style='color: 15b79f'>$active_total kr</span> ".findtekst('2382|ufaktureret', $sprog_id)."</span>");
}

# #######################################
#
#	Momsrapport
#
# #######################################

if ($vat_count == "on") {
	include ("dashboardIncludes/vat_func.php");
	vat_info($regnstart, $regnslut);
}

# #######################################
#
#	Online brugere
#
# #######################################

if ($onlineusers === "on") {
	key_value(findtekst('2379|Aktive medarbejdere', $sprog_id), $online_people_amount, "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'><span style='color: #999'>".findtekst('2380|Har været aktive inden for den sidste time', $sprog_id)."</span>");
}

# Close the contianer div
print "</div>";
print "<div style='display: flex; gap: 2em; flex-wrap: wrap'>";

# #######################################
#
#	Omsætningsgraf
#
# #######################################

if ($revgraph === "on") {
	revenue_graph($regnstart, $regnslut);
}
if ($customergraph === "on") {
	customer_graph();
}

if ($revweek === "on") {
  include ("dashboardIncludes/weekly_graph.php");
	weekly_graph($regnstart, $regnslut);
}

if ($varegrp_doughnut === "on") {
  include("dashboardIncludes/varegrp_doughnut.php");
  varegrp_doughnut( $regnstart, $regnslut);
}

print "</div>";
print "<div style='display: flex; gap: 2em; flex-wrap: wrap'>";

$qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
$state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($state) {
	pos_row();
}
print "</div>";
print "</div>";

// #######################################
// Editor Popup
// #######################################

print "
<div style='display: none' id='settingpopup'>
  <!-- Popup Background -->
  <div onclick=\"document.getElementById('settingpopup').style.display='none'\" style='top: 0; position: fixed; height: 100vh; width: 100vw; background-color: #00000030'></div>
  
  <!-- Popup Content -->
  <div style='width: 600px; position: absolute; left: 50%; top: 50%; background-color: #fff; transform: translate(-50%, -50%); padding: 2em; box-shadow: 0 4px 20px rgba(0,0,0,0.2); position: fixed;'>
    <!-- Close Button -->
    <button onclick=\"document.getElementById('settingpopup').style.display='none'\"
        style='position: absolute; top: 0.5em; right: 0.5em; border: none; background: transparent; font-size: 1.2em; cursor: pointer; color: #e00;'>&times;</button>

    
    <h3>" . findtekst('2151|Opsæt din oversigt', $sprog_id) . "</h3>
<form method='post'>
  <table>
        <!-- Kontonumre Section -->
    <tr>
      <th>".findtekst('2152|Kontonumre', $sprog_id)."</th>
      <th></th>
    </tr>
    <tr>
          <td>".findtekst('2153|Min.', $sprog_id)." ".strtolower(findtekst('1166|Omsætning', $sprog_id))."</td>
      <td><input type='text' name='kontomin' value='$kontomin' /></td>
          <td>".findtekst('2155|Er du i tvivl om dine kontotal?', $sprog_id)."</td>
    </tr>
    <tr>
          <td>".findtekst('2154|Maks.', $sprog_id)." ".strtolower(findtekst('1166|Omsætning', $sprog_id))."</td>
      <td><input type='text' name='kontomaks' value='$kontomaks' /></td>
          <td>".findtekst('2156|Se vores guide', $sprog_id)." <a href='https://site.saldi.dk/saldi-manualer/omsaetningstal' target='_blank'>".findtekst('2157|her', $sprog_id)."</a>.</td>
    </tr>
        
        <!-- Nøgletal Section -->
    <tr>
      <th>".findtekst('2158|Nøgletal', $sprog_id)."</th>
      <th></th>
    </tr>
    <tr>
          <td>".findtekst('2159|Omsætning for måneden', $sprog_id)."</td>
          <td><input type='checkbox' name='revmonth' " . ($revmonth === "on" ? "checked" : "") . " /></td>
    </tr>
    <tr>
      <td>".findtekst('2160|Omsætning for året', $sprog_id)."</td>
          <td><input type='checkbox' name='revyear' " . ($revyear === "on" ? "checked" : "") . " /></td>
    </tr>
    <tr>
          <td>".findtekst('2161|Ufakturerede ordrer', $sprog_id)."</td>
          <td><input type='checkbox' name='ordercount' " . ($ordercount === "on" ? "checked" : "") . " /></td>
    </tr>
    <tr>
      <td>".findtekst('2379|Aktive medarbejdere', $sprog_id)."</td>
          <td><input type='checkbox' name='onlineusers' " . ($onlineusers === "on" ? "checked" : "") . " /></td>
    </tr>
        <tr>
          <td>".findtekst('520|Momsangivelse', $sprog_id)."</td>
          <td><input type='checkbox' name='vatcount' " . ($vat_count === "on" ? "checked" : "") . " /></td>
        </tr>
        
        <!-- Grafer Section -->
    <tr>
      <th>".findtekst('2162|Grafer', $sprog_id)."</th>
      <th></th>
    </tr>
    <tr>
          <td>".findtekst('2678|Graf over ugentlig omsætning', $sprog_id)."</td> 
          <td><input type='checkbox' name='revweek' " . ($revweek === "on" ? "checked" : "") . " /></td>
    </tr>
    <tr>
      <td>".findtekst('2163|Omsætningsgraf', $sprog_id)."</td>
          <td><input type='checkbox' name='revgraph' " . ($revgraph === "on" ? "checked" : "") . " /></td>
    </tr>
    <tr>
      <td>".findtekst('2164|Kundefordeling', $sprog_id)."</td>
          <td><input type='checkbox' name='customergraph' " . ($customergraph === "on" ? "checked" : "") . " /></td>
        </tr>
        <tr>
          <td>".findtekst('2576|Varegruppeomsætning', $sprog_id)."</td>
          <td><input type='checkbox' name='varegrpdoughnut' " . ($varegrp_doughnut === "on" ? "checked" : "") . " /></td>
    </tr>
  </table> 
  <button type='submit'>".findtekst('3|Gem', $sprog_id)."</button>
</form>
  </div>
</div>
";

?>
