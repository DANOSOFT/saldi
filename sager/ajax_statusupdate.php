<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	
	$bg="nix";
	$header='nix';

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	ini_set("display_errors", "1");
	$id=if_isset($_POST['sagid']);
	$status=if_isset($_POST['status']);
	

if ($id) {
	db_modify("update sager set status='$status' where id = '$id'",__FILE__ . " linje " . __LINE__);


	$r=db_fetch_array(db_select("select status from sager where id = '$id'",__FILE__ . " linje " . __LINE__));
	$status=$r['status'];
	/*
	$statcolor = NULL;
		if ($status=='Opmåling') $statcolor = "statcolor1";// color:#006600; green 
		if ($status=='Tilbud') $statcolor = "statcolor2";// color:#009900;
		if ($status=='Ordre modtaget') $statcolor = "statcolor3";// color:#00CC00;
		if ($status=='Montage') $statcolor = "statcolor4";// color:#C1BE00;
		if ($status=='Aflevering') $statcolor = "statcolor5";// color:#FF9900;
		if ($status=='Afmeldt') $statcolor = "statcolor6";// color:#FF6600;
		if ($status=='Demontage') $statcolor = "statcolor7";// color:#FF3300;
		if ($status=='Afsluttet') $statcolor = "statcolor8";// color:#FF0000; red
	*/
	$statcolor = NULL;
		if ($status=='Beregning') $statcolor = "statcolor_1";
		if ($status=='Tilbud') $statcolor = "statcolor_2";
		if ($status=='Ordrebekræftelse') $statcolor = "statcolor_3";
		if ($status=='Montage') $statcolor = "statcolor_4";
		if ($status=='Godkendt') $statcolor = "statcolor_5";
		if ($status=='Afmeldt') $statcolor = "statcolor_6";
		if ($status=='Drivgods') $statcolor = "statcolor_7";
		if ($status=='Afsluttet') $statcolor = "statcolor_8";
}

echo json_encode(array("status" => "$status", "statcolor" => "$statcolor"));
?>