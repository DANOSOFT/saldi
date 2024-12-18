<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/dashboard.php --- lap 4.1.0 --- 2024.05.22 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------

@session_start();
$s_id = session_id();

$css = "../css/dashboard.css?v=2";
echo "<title>Overblik</title>";

include ("../includes/std_func.php");
include ("../includes/connect.php");

# Get database name of current online user
$qtxt = "SELECT db FROM online WHERE session_id='$s_id' limit 1";
$db = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

# Get list of active users
$qtxt = "SELECT brugernavn, logtime FROM online WHERE db='$db'";
$online_people = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

# Get amount of active users
$timestamp = (int) date("U") - (1*60*60*1000);
$qtxt = "SELECT count(brugernavn) FROM online WHERE db='$db' AND logtime > '$timestamp'";
$online_people_amount = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

$newssnippet = get_settings_value("nyhed", "dashboard", "");

include ("../includes/online.php");
include ("../includes/stdFunc/dkDecimal.php");

function check_permissions($permarr) {
	global $rettigheder;
	$filtered = array_filter($permarr, function ($item) use ($rettigheder) {
		return (substr($rettigheder, $item, 1) == "1");
	});
	return !empty($filtered);
}

# If the user has finans -> regnskab or finans -> reports level access
if (!check_permissions(array(3,4))) {
	$qtxt = "SELECT firmanavn FROM adresser WHERE art='S'";
	$name = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))[0];

	print "<div style='display: flex; flex-direction: column; padding: 2em 1em; gap: 2em; height: 100vh' class='content'>";

	# Titlebar
	print "<div style='display: flex; justify-content: space-between; flex-wrap: wrap'>";
	print "<h1>Velkommen - $name</h1>";
	print "<div style='display: flex; gap: 2em'>";
	$qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
	$state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$qtxt = "SELECT id FROM settings WHERE var_name = 'orderXpress' AND var_value='on'";
	$orderXpress = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	if ($state) {
		print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../debitor/pos_ordre.php\"'>Åben kassesystem</button>";
	} elseif ($orderXpress) {
		print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../sager/sager.php\"'>Åben sagsstyring</button>";
	} else {
		print "<button style='padding: 1em; cursor: not-allowed' disabled>Åben kassesystem</button>";
	}

	print "</div>";
	print "</div>";
//	print "<p title='For at få adgang skal du aktivere finansmodulet for brugeren'>Du har ikke adgang til at se virksomhedsoversigten</p>";
	print "<img src='../img/Saldi_Main_Logo.png' style='position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 40%'></img>";
	exit;
}


print '<script src="../javascript/chart.js"></script>';

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
   update_settings_value("kontomin", "dashboard_values", if_isset($_POST['kontomin'], "0"), "Show the revenue this month per last month");
   update_settings_value("kontomaks", "dashboard_values", if_isset($_POST['kontomaks'], "12000"), "Show the revenue this month per last month");

   update_settings_value("revmonth", "dashboard_toggles", if_isset($_POST['revmonth'], "off"), "Show the revenue this month per last month");
   update_settings_value("revyear", "dashboard_toggles", if_isset($_POST['revyear'], "off"), "Show the revenue this year per last year");
   update_settings_value("ordercount", "dashboard_toggles", if_isset($_POST['ordercount'], "off"), "Show the amount of orders currently active that are not older than 30 days");
   update_settings_value("onlineusers", "dashboard_toggles", if_isset($_POST['onlineusers'], "off"), "Show the amount of orders currently active that are not older than 30 days");
   update_settings_value("revgraph", "dashboard_toggles", if_isset($_POST['revgraph'], "off"), "Show the revenue graph");
   update_settings_value("customergraph", "dashboard_toggles", if_isset($_POST['customergraph'], "off"), "Sho wthe customer graph per hour");
}

if ($_GET['close_snippet'] == '1') {
   update_settings_value("closed_news_snippet", "dashboard", $newssnippet, "The newssnippet that was closed by the user");
}
if ($_GET['hidden'] == '1') {
   update_settings_value("hide_dash", "dashboard", 1, "Weather or not the newssnippet is showen to the user", $user=$bruger_id);
}
if ($_GET['hidden'] == '0') {
   update_settings_value("hide_dash", "dashboard", 0, "Weather or not the newssnippet is showen to the user", $user=$bruger_id);
}


$kontomin = get_settings_value("kontomin", "dashboard_values", 0);
$kontomaks = get_settings_value("kontomaks", "dashboard_values", 2000);

$revmonth = get_settings_value("revmonth", "dashboard_toggles", "on");
$revyear = get_settings_value("revyear", "dashboard_toggles", "on");
$ordercount = get_settings_value("ordercount", "dashboard_toggles", "on");
$onlineusers = get_settings_value("onlineusers", "dashboard_toggles", "off");
$revgraph = get_settings_value("revgraph", "dashboard_toggles", "on");
$customergraph = get_settings_value("customergraph", "dashboard_toggles", "off");

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



function revenue_graph() {
	# Omsætningsgraf
	global $kontomin;
	global $kontomaks;
	global $sprog_id;

	echo '
<div style="
	flex: 2;
	min-width: 500px;
	background-color: #fff;
	border-radius: 5px;
	box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
	padding: 1.4em 2em;
">
	<h4 style="margin: 0; color: #999">'.findtekst("2165|Din omsætning sammenlignet med sidste år, ekskl. moms", $sprog_id).'</h4>
	<div style="flex: 1; width: 100%">
	  <canvas id="myChart"></canvas>
	</div>
	</div>
	';

	$currentYear = date('Y');
	$lastYear = date('Y')-1;

	$revenue_now = [];
	$revenue_last = [];

	for ($month = 1; $month <= 12; $month++) {
	    $firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month, 1, $lastYear));
	    $lastDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $lastYear));

	    $q=db_select("
	SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
	FROM transaktioner T
	WHERE T.transdate >= '$firstDayOfMonth'
	AND T.transdate <= '$lastDayOfMonth'
	AND T.kontonr > $kontomin
	AND T.kontonr < $kontomaks
	",__FILE__ . " linje " . __LINE__);
	    $value = db_fetch_array($q)[0];
	    array_push($revenue_last, $value);

	    $firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month, 1, $currentYear));
	    $lastDayOfMonth = date('Y-m-d', mktime(0, 0, 0, $month + 1, 0, $currentYear));
	    $q=db_select("
	SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
	FROM transaktioner T
	WHERE T.transdate >= '$firstDayOfMonth'
	AND T.transdate <= '$lastDayOfMonth'
	AND T.kontonr > $kontomin
	AND T.kontonr < $kontomaks
	",__FILE__ . " linje " . __LINE__);
	    $value = db_fetch_array($q)[0];
	    array_push($revenue_now, $value);
	}


	echo "
	<script>
	  const ctx = document.getElementById('myChart');

	  new Chart(ctx, {
	    type: 'bar',
	    data: {
	      labels: ['Januar', 'Februar', 'Marts', 'April', 'Maj', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'December'],
	      datasets: [{
		label: '".findtekst('1166|Omsætning', $sprog_id)." $lastYear',
		data: ['";

	echo implode("','", $revenue_last);

	echo "'],
		borderWidth: 1
	      },
	      {
		label: '".findtekst('1166|Omsætning', $sprog_id)." $currentYear',
		data: ['";

	echo implode("','", $revenue_now);

	echo "'],
		borderWidth: 1
	      }
	]
	    },
	    options: {
	      interaction: {
      		mode: 'index',
	        intersect: false,
	      },
	      responsive: true,
	      maintainAspectRatio: false,
	      scales: {
		y: {
		  beginAtZero: true
		}
	      }
	    }
	  });
	</script>";
}

function customer_graph() {
	# Omsætningsgraf
	global $sprog_id;
	echo '
<div style="
	flex: 2;
	min-width: 500px;
	background-color: #fff;
	border-radius: 5px;
	box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
	padding: 1.4em 2em;
">
	<h4 style="margin: 0; color: #999">'.findtekst("2174|Antal kunder pr. time i gennemsnit over de sidste 30 dage", $sprog_id).'</h4>
	<div style="flex: 1; width: 100%">
	  <canvas id="customerChart"></canvas>
	</div>
	</div>
	';


	$weekdayDates = array(
	    'Monday'    => array(),
	    'Tuesday'   => array(),
	    'Wednesday' => array(),
	    'Thursday'  => array(),
	    'Friday'    => array(),
	    'Saturday'  => array(),
	    'Sunday'    => array()
	);

	$currentDate = new DateTime();

	for ($i = 0; $i < 30; $i++) {

	    $date = clone $currentDate;
	    $date->sub(new DateInterval('P'.$i.'D'));

	    $weekdayName = $date->format('l');

	    if (array_key_exists($weekdayName, $weekdayDates)) {
	        $weekdayDates[$weekdayName][] = $date->format('Y-m-d');

	    }
	}

	$weekdayValues = array(
	    'Monday'    => generateArray(),
	    'Tuesday'   => generateArray(),
	    'Wednesday' => generateArray(),
	    'Thursday'  => generateArray(),
	    'Friday'    => generateArray(),
	    'Saturday'  => generateArray(),
	    'Sunday'    => generateArray()
	);

        foreach ($weekdayDates as $weekday => $dates) {
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


	echo "
	<script>
	  const ctx2 = document.getElementById('customerChart');

	  new Chart(ctx2, {
	    type: 'line',
	    data: {
	      labels: ['00:00', '01:00','02:00','03:00','04:00','05:00','06:00','07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00','21:00','22:00','23:00'],
	      datasets: [";

	foreach ($weekdayValues as $weekday => $valuess) {
	      echo "{
		label: '$weekday',
		data: ['";
		echo implode("','", $valuess);
		echo "'],
			borderWidth: 1,
			pointStyle: false,
		      },";
	}


	echo "]
	    },
	    options: {
	      interaction: {
      		mode: 'index',
	        intersect: false,
	      },
	      responsive: true,
	      maintainAspectRatio: false,
	      scales: {
		y: {
		  beginAtZero: true,
		},
	      }
	    }
	  });
	</script>";
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

print "<div style='display: flex; flex-direction: column; padding: 2em 1em; gap: 2em; height: 100vh' class='content'>";

# Newsbar
if ($closed_newssnippet != $newssnippet && $newssnippet != '') {
	print "<div id='newsbar'><span><b>Nyt i saldi:</b> $newssnippet</span><span id='closebtn' onClick=\"document.location.href = 'dashboard.php?close_snippet=1'\">x</span></div>";
}

# Titlebar
print "<div style='display: flex; justify-content: space-between; flex-wrap: wrap; gap: 2em'>";
print "<h1>".findtekst('2224|Oversigt', $sprog_id)." - $name</h1>";
print "<div style='display: flex; gap: 2em'>";
print "<button style='padding: 1em; cursor: pointer' onclick='document.location.href = \"dashboard.php?hidden=". ($hide_dash === "1" ? "0" : "1") ."\"'>". ($hide_dash !== "1" ? findtekst('1132|Skjul', $sprog_id) : findtekst('1133|Vis', $sprog_id)) ." ".findtekst('2224|Oversigt', $sprog_id)."</button>";
if ($hide_dash !== "1") print "<button style='padding: 1em; cursor: pointer' onclick='document.getElementById(\"settingpopup\").style.display = \"block\"'>".findtekst('2148|Rediger', $sprog_id). " " .findtekst('2224|Oversigt', $sprog_id). "</button>";


# Kassesystem eller ej
$qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
$state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
$qtxt = "SELECT id FROM settings WHERE var_name = 'orderXpress' AND var_value='on'";
$orderXpress = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
if ($state) {
	print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../debitor/pos_ordre.php\"'>" .findtekst('2149|Åbn kassesystem', $sprog_id)."</button>";
} elseif ($orderXpress) {
	print "<button style='padding: 1em; cursor: pointer' onclick='parent.location.href=\"../sager/sager.php\"'>" .findtekst('2150|Åbn sagsstyring', $sprog_id)."</button>";
} else {
	print "<button style='padding: 1em; cursor: not-allowed' disabled>" .findtekst('2149|Åbn kassesystem', $sprog_id)."</button>";
}



print "</div>";
print "</div>";

if ($hide_dash === "1") {
        exit;
}

print "<div style='display: flex; gap: 2em; flex-wrap: wrap'>";

# #######################################
#
#	Samlet for Måneden
#
# #######################################

if ($revmonth === "on") {
	$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')));
	$currentDayMinusOne = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')));
	$q=db_select("
	SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
	FROM transaktioner T
	WHERE T.transdate <= '$currentDayMinusOne'
	AND T.transdate >= '$firstDayOfMonth'
	AND T.kontonr > $kontomin
	AND T.kontonr < $kontomaks
	",__FILE__ . " linje " . __LINE__);
	$revenue = db_fetch_array($q)[0];

	$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, date('m'), 1, date('Y')-1));
	$currentDayMinusOne = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')-1));
	$q=db_select("
	SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
	FROM transaktioner T
	WHERE T.transdate <= '$currentDayMinusOne'
	AND T.transdate >= '$firstDayOfMonth'
	AND T.kontonr > $kontomin
	AND T.kontonr < $kontomaks
	",__FILE__ . " linje " . __LINE__);
	$revenue_last = db_fetch_array($q)[0];
	$revenue_diff = $revenue - $revenue_last;
	$revenue_status = $revenue_diff > 0 ? 
		"<span style='color: #15b79f'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('3084|mere end sidste år til dato', $sprog_id)."</span>" 
		: 
		"<span style='color: #ea3c3c'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('3085|mindre end sidste år til dato', $sprog_id)."</span>";

	key_value(findtekst('3083|Omsætning denne måned, ekskl. moms', $sprog_id), formatNumber($revenue ? $revenue : 0)." kr", "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'>$revenue_status");
}

# #######################################
#
#	Samlet for året
#
# #######################################

if ($revyear === "on") {
	$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, 1, 1, date('Y')));
	$currentDayMinusOne = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')));
	$qtxt = "
	SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
	FROM transaktioner T
	WHERE T.transdate <= '$currentDayMinusOne'
	AND T.transdate >= '$firstDayOfMonth'
	AND T.kontonr > $kontomin
	AND T.kontonr < $kontomaks
	";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	$revenue = db_fetch_array($q)[0];

	$firstDayOfMonth = date('Y-m-d', mktime(0, 0, 0, 1, 1, date('Y')-1));
	$currentDayMinusOne = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')-1));
	$qtxt = "
	SELECT SUM(COALESCE(T.kredit, 0) - COALESCE(T.debet, 0))
	FROM transaktioner T
	WHERE T.transdate <= '$currentDayMinusOne'
	AND T.transdate >= '$firstDayOfMonth'
	AND T.kontonr > $kontomin
	AND T.kontonr < $kontomaks
	";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);

	$revenue_last = db_fetch_array($q)[0];
	$revenue_diff = $revenue - $revenue_last;
	$revenue_status = $revenue_diff > 0 ? 
		"<span style='color: #15b79f'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('3084|mere end sidste år til dato', $sprog_id)."</span>" 
		: 
		"<span style='color: #ea3c3c'>" . formatNumber(abs($revenue_diff)) . " kr</span> <span style='color: #999'>".findtekst('3085|mindre end sidste år til dato', $sprog_id)."</span>";

	key_value(findtekst('3082|Omsætning for året, ekskl. moms', $sprog_id), formatNumber($revenue ? $revenue : 0)." kr", "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'>$revenue_status");
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

	$q=db_select("SELECT count(*), COALESCE(sum(\"sum\"), 0) FROM ordrer WHERE status < 2 AND ordredate > '$thirtyDaysAgo'",__FILE__ . " linje " . __LINE__);
	$data = db_fetch_array($q);
	$active_orders = formatNumber((int)$data[0], $dkFormat=false);
	$active_total = formatNumber($data[1]);
	key_value(findtekst('3077|Ufakturerede ordrer de sidste 30 dage', $sprog_id), $active_orders, "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'><span style='color: #999'>".findtekst('3080|Hvilket svarer til', $sprog_id)." <span style='color: 15b79f'>$active_total kr</span> ".findtekst('3081|ufaktureret', $sprog_id)."</span>");

	# key_value("Gennemsnitlig rabat", "25%");
}

# #######################################
#
#	Online brugere
#
# #######################################

if ($onlineusers === "on") {
	key_value(findtekst('3078|Aktive medarbejdere', $sprog_id), $online_people_amount, "<hr style='margin: 1em 0em; background-color: #ddd; border: none; height: 1px'><span style='color: #999'>".findtekst('3079|Har været aktive inden for den sidste time', $sprog_id)."</span>");
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
	revenue_graph();
}
if ($customergraph === "on") {
	customer_graph();
}
print "</div>";
print "</div>";

print "
<div style='display: none' id='settingpopup'>
  <div style='top: 0; position: absolute; height: 100vh; width: 100vw; background-color: #00000030'>
  </div>
  <div style='width: 700px; position: absolute; left: 50%; top: 50%; background-color: #fff; transform: translate(-50%, -50%); padding: 2em'>
    <h3>".findtekst('2151|Opsæt din oversigt', $sprog_id)."</h3>

<form method='post'>
  <table>
    <tr>
      <th>".findtekst('2152|Kontonumre', $sprog_id)."</th>
      <th></th>
    </tr>
    <tr>
      <td>".findtekst('2153|Min.', $sprog_id)." ".strtolower(findtekst('1166|Omsætning', $sprog_id)).":</td>
      <td><input type='text' name='kontomin' value='$kontomin' /></td>
      <td>".findtekst('2155|Er du i tvivl om dine kontotal?', $sprog_id)."</td>
    </tr>
    <tr>
      <td>".findtekst('2154|Maks.', $sprog_id)." ".strtolower(findtekst('1166|Omsætning', $sprog_id)).":</td>
      <td><input type='text' name='kontomaks' value='$kontomaks' /></td>
      <td>".findtekst('2156|Se vores guide', $sprog_id)." <a href='https://site.saldi.dk/saldi-manualer/omsaetningstal' target='_blank'>".findtekst('2157|her', $sprog_id)."</a>.</td>
    </tr>
    <tr>
      <th>".findtekst('2158|Nøgletal', $sprog_id)."</th>
      <th></th>
    </tr>
    <tr>
      <td>".findtekst('2159|Omsætning for måneden', $sprog_id)."</td>
      <td><input type='checkbox' name='revmonth' ";
if ($revmonth === "on") {
	print "checked";
} 
print " /></td>
    </tr>
    <tr>
      <td>".findtekst('2160|Omsætning for året', $sprog_id)."</td>
      <td><input type='checkbox' name='revyear' ";
if ($revyear === "on") {
	print "checked";
} 
print " /></td>
    </tr>
    <tr>
      <td>".findtekst('2161|Ufakturerede ordrer', $sprog_id)."</td>
      <td><input type='checkbox' name='ordercount' ";
if ($ordercount === "on") {
	print "checked";
} 
print " /></td>
    </tr>
    <tr>
      <td>".findtekst('3078|Aktive medarbejdere', $sprog_id)."</td>
      <td><input type='checkbox' name='onlineusers' ";
if ($onlineusers === "on") {
	print "checked";
} 
print " /></td>
    </tr>
    <tr>
      <th>".findtekst('2162|Grafer', $sprog_id)."</th>
      <th></th>
    </tr>
    <tr>
      <td>".findtekst('2163|Omsætningsgraf', $sprog_id)."</td>
      <td><input type='checkbox' name='revgraph' ";
if ($revgraph === "on") {
	print "checked";
} 
print " /></td>
    </tr>
    <tr>
      <td>".findtekst('2164|Kundefordeling', $sprog_id)."</td>
      <td><input type='checkbox' name='customergraph' ";
if ($customergraph === "on") {
	print "checked";
} 
print " /></td>
    </tr>
  </table> 
  <button type='submit'>".findtekst('3|Gem', $sprog_id)."</button>
</form>
  </div>
</div>
";


?>
