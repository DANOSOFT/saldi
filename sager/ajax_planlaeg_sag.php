<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	
	$bg="nix";
	$header='nix';

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	$opg_id=if_isset($_POST['opg_id']);
	$id=if_isset($_POST['id']);
	$planfra=if_isset($_POST['planfra']);
	$plantil=if_isset($_POST['plantil']);
	//$op=if_isset($_POST['op']);
	
/*
if ($id>0 && ($op==1)) {
	db_modify("update sager set planfraop='$planfra', plantilop='$plantil' where id = '$id'",__FILE__ . " linje " . __LINE__);
	//echo "id: $id;";
}
if ($id>0 && ($op==0)) {
	db_modify("update sager set planfraned='$planfra', plantilned='$plantil' where id = '$id'",__FILE__ . " linje " . __LINE__);
}*/
if ($id>0) {
	db_modify("update sager set planfraop='".usdate($planfra)."', plantilop='".usdate($plantil)."' where id = '$id'",__FILE__ . " linje " . __LINE__);
}
if ($opg_id>0) {
	db_modify("update opgaver set opg_planfra='".usdate($planfra)."', opg_plantil='".usdate($plantil)."' where id = '$opg_id'",__FILE__ . " linje " . __LINE__);
	//echo "opg_id: $opg_id;";
}

?>