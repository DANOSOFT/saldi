<?php
@session_start();
$s_id=session_id();

// Denne fil er tilknyttet funktion medarbejderhandbog og  i vis_mappe.php
// Her opdateres 'mappe og mappebilag' med den ny rækkefølge som bliver sendt fra jquery.tablednd.0.7.min.js 

$bg="nix";
$header='nix';
$modulnr=0;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");



if (isset($_POST['orders'])) {

	$orders = explode('&', $_POST['orders']);
	$array = array();
	
	foreach($orders as $item) {
		$item = explode('=', $item);
		$item = explode('_', $item[1]);
		$array[] = $item[1];
	}
	
	switch ($_GET['mode']) {
		case 'mappe':
			foreach($array as $key => $value) {
				$key = $key + 1;
				db_modify("UPDATE mappe SET sort='$key' WHERE id = '$value'",__FILE__ . " linje " . __LINE__);
			}
			break;
		case 'mappebilag';
			foreach($array as $key => $value) {
				$key = $key + 1;
				db_modify("UPDATE mappebilag SET sort='$key' WHERE id = '$value'",__FILE__ . " linje " . __LINE__);
			}
			break;
		case 'ansatmappe':
			foreach($array as $key => $value) {
				$key = $key + 1;
				db_modify("UPDATE ansatmappe SET sort='$key' WHERE id = '$value'",__FILE__ . " linje " . __LINE__);
			}
			break;
		case 'ansatmappebilag';
			foreach($array as $key => $value) {
				$key = $key + 1;
				db_modify("UPDATE ansatmappebilag SET sort='$key' WHERE id = '$value'",__FILE__ . " linje " . __LINE__);
			}
			break;
	}
}
?>