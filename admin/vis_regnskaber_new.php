<?php
@session_start();
$s_id=session_id();

// --- admin/vis_regnskaber.php --- patch 4.0.4 --- 2021.09.16 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 20210328 PHR Some cleanup.
// 20210916 LOE Translated some texts

$css="../css/standard.css";
$title="vis regnskaber";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/grid.php");
$bruger_id=1;

db_modify("CREATE TABLE IF NOT EXISTS datatables (
    id SERIAL PRIMARY KEY, -- Auto increment integer
    user_id INTEGER NOT NULL,
    tabel_id CHARACTER VARYING(10),
    column_setup TEXT,
    search_setup TEXT,
    filter_setup TEXT,
    rowcount INTEGER,
    \"offset\" INTEGER,
    sort TEXT
)
", __FILE__ . " line " . __LINE__);

$saldiregnskab = NULL;
$lukket=array();

$rediger    = if_isset($_GET['rediger']);
$showClosed = if_isset($_GET['showClosed']);
$beregn     = if_isset($_GET['beregn']);
$sort       = if_isset($_GET['sort']);
$sort2      = if_isset($_GET['sort2']);
$desc       = if_isset($_GET['desc']);
$modulnr    = 102;

if ($db != $sqdb) {
	$alert = findtekst(1905, $sprog_id); #20210916
	print "<BODY onLoad=\"javascript:alert('$alert')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}

if ($beregn) {
	$tempScriptPath = "../temp/$sqdb/tmp.sh";
	$tempDbListPath = "../temp/dbliste.txt";

	// Create a temporary shell script to list databases
	file_put_contents($tempScriptPath, "#!/bin/sh\nexport PGPASSWORD='$sqpass'\npsql --username=$squser -l > $tempDbListPath\n");
	chmod($tempScriptPath, 0700);

	// Execute the script and remove it
	system("/bin/sh '$tempScriptPath'");
	unlink($tempScriptPath);

	// Read database list and clean up
	$dbEntries = file($tempDbListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	unlink($tempDbListPath);

	// Extract database names containing an underscore
	$existingDatabases = array_filter(array_map(function ($entry) {
		$parts = explode("|", $entry, 2);
		return (isset($parts[0]) && strpos($parts[0], "_") !== false) ? trim($parts[0]) : null;
	}, $dbEntries));

	$existingDatabases = array_values(array_filter($existingDatabases));
	print_r($existingDatabases);

	// Determine the cutoff date for transaction counting
	$cutoffDate = date("Y-m-d", strtotime("-1 year"));

	foreach ($existingDatabases as $index => $dbName) {
		if (in_array($dbName, $existingDatabases)) {
			$query = "SELECT datname FROM pg_database WHERE datname = '$dbName'";
			echo "$query<br>";

			if (db_fetch_array(db_select($query, __FILE__ . " linje " . __LINE__))) {
				echo "$dbName eksisterer<br>";
				db_connect($sqhost, $squser, $sqpass, $dbName, __FILE__ . " linje " . __LINE__);

				$tableCheckQuery = "SELECT * FROM pg_tables WHERE tablename='transaktioner'";
				if (db_fetch_array(db_select($tableCheckQuery, __FILE__ . " linje " . __LINE__))) {
					// Count transactions
					$transactionCountQuery = "SELECT count(id) AS transantal FROM transaktioner WHERE logdate >= '$cutoffDate'";
					$result = db_fetch_array(db_select($transactionCountQuery, __FILE__ . " linje " . __LINE__));
					$posteringer[$index] = (int) $result['transantal'];

					// Calculate the last time a invoice was uploaded
					$lastLogDateQuery = "SELECT max(logdate) AS logdate FROM transaktioner";
					if ($result = db_fetch_array(db_select($lastLogDateQuery, __FILE__ . " linje " . __LINE__))) {
						$sidst[$index] = strtotime($result['logdate']);
					}
					$lastBatchSaleQuery = "SELECT * FROM batch_salg ORDER BY id DESC LIMIT 1";
					if ($result = db_fetch_array(db_select($lastBatchSaleQuery, __FILE__ . " linje " . __LINE__))) {
						if (!empty($result['modtime']) && strtotime($result['modtime']) > $sidst[$index]) {
							$sidst[$index] = strtotime($result['modtime']);
						}
					}
				} else {
					$sidst[$index] = null;
				}

				include("../includes/connect.php");
			} else {
				echo "Opretter $dbName<br>";
				db_create($dbName);
			}
		} else {
			echo "Opretter $dbName<br>";
			db_create($dbName);
			$sidst[$index] = null;
		}
	}
}

$columns = array();

$columns[] =    array(
    "field" => "id",
    "headerName" => "id",
    "width" => 0.1,
);
$columns[] =    array(
    "field" => "regnskab",
    "headerName" => "Regnskabs navn",
);
$columns[] =    array(
    "field" => "db",
    "headerName" => "Database",
);

$columns[] =    array(
    "field" => "posteringer",
    "headerName" => "Posteringer",
    "algin" => "right",
    "type" => "number",
    "valueGetter" => function ($value, $row, $column) {
        return $value ? dkdecimal($value, 0) : 0;
    },
);
$columns[] =    array(
    "field" => "posteret",
    "headerName" => "Posteret",
    "type" => "number",
    "algin" => "right",
    "valueGetter" => function ($value, $row, $column) {
        return $value ? dkdecimal($value, 0) : 0;
    },
);
$columns[] =    array(
    "field" => "sms",
    "headerName" => "Sms",
    "type" => "number",
    "algin" => "right",
    "valueGetter" => function ($value, $row, $column) {
        return $value ? dkdecimal($value, 0) : 0;
    },
);
$columns[] =    array(
    "field" => "invoices",
    "headerName" => "Invoices",
    "type" => "number",
    "algin" => "right",
    "valueGetter" => function ($value, $row, $column) {
        return $value ? dkdecimal($value, 0) : 0;
    },
);
$columns[] =    array(
    "field" => "brugerantal",
    "headerName" => "Brugerantal",
    "algin" => "right",
    "type" => "number",
    "valueGetter" => function ($value, $row, $column) {
        return $value ? dkdecimal($value, 0) : 0;
    },
);
$columns[] = array(
    "field" => "sidst",
    "headerName" => "Sidst",
    "searchable" => false,
    "render" => function ($value, $row, $column) {
        if (!$value) {
            return "<td></td>";
        }

        $date = date("Y/m/d", $value); // Convert to US format
        $daysAgo = floor((time() - $value) / 86400); // Calculate days ago
        return "<td>{$date} ({$daysAgo} dage siden)</td>";
    },
);

$data = array(
    "table_name" => "regnskab",

    "query" => "SELECT * FROM regnskab
WHERE {{WHERE}} AND db != '$sqdb'
ORDER BY {{SORT}}
",

    "columns" => $columns,
    "filters" => array(),
);

print "<div style='width: 100%; height: calc(100vh - 30px - 34px - 16px);'>";
create_datagrid("regnskaber", $data);
print "</div>";
print "<a href=\"vis_regnskaber_new.php?beregn=1\">" . findtekst(1916, $sprog_id) . "</a>";