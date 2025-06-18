<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();
if (isset($_GET['id'])) {
	$id=$_GET['id'];
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	enhedssum($id);
}
function enhedssum($id) {

	$x=0;
	$y=0;
	$sum=0;
	$l_vare_id=array();
	$q = db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id'",__FILE__ . " linje " . __LINE__); // Har fjernet 'order by
	while ($r = db_fetch_array($q)) {
		$l_id[$x]=$r['id'];
		$l_vare_id[$x]=$r['vare_id']*1;
		$l_vare_nr[$x]=$r['varenr']*1;
		$l_op[$x]=$r['op']*1;
		$l_ned[$x]=$r['ned']*1;
		$l_op_25[$x]=$r['op_25']*1;
		$l_ned_25[$x]=$r['ned_25']*1;
		$l_op_40[$x]=$r['op_40']*1;
		$l_ned_40[$x]=$r['ned_40']*1;
		$l_op_60[$x]=$r['op_60']*1;
		$l_ned_60[$x]=$r['ned_60']*1;
		$l_op_30m[$x]=$r['op_30m']*1;
		$l_ned_30m[$x]=$r['ned_30m']*1;
		$l_pris_op[$x]=$r['pris_op']*1;
		$l_pris_ned[$x]=$r['pris_ned']*1;
		$l_sum=0;
		if ($l_op[$x]) $l_sum+=$l_op[$x]*$l_pris_op[$x];
		if ($l_op_25[$x]) $l_sum+=$l_op_25[$x]*$l_pris_op[$x]*0.25;
		if ($l_op_40[$x]) $l_sum+=$l_op_40[$x]*$l_pris_op[$x]*0.4;
		if ($l_op_60[$x]) $l_sum+=$l_op_60[$x]*$l_pris_op[$x]*0.6;
		if ($l_op_30m[$x]) $l_sum+=$l_op_30m[$x]*$l_pris_op[$x]*0.06;
		if ($l_ned[$x]) $l_sum+=$l_ned[$x]*$l_pris_ned[$x];
		if ($l_ned_25[$x]) $l_sum+=$l_ned_25[$x]*$l_pris_ned[$x]*0.25;
		if ($l_ned_40[$x]) $l_sum+=$l_ned_40[$x]*$l_pris_ned[$x]*0.4;
		if ($l_ned_60[$x]) $l_sum+=$l_ned_60[$x]*$l_pris_ned[$x]*0.6;
		if ($l_ned_30m[$x]) $l_sum+=$l_ned_30m[$x]*$l_pris_ned[$x]*0.06;
		$sum+=$l_sum;
	}
	return ($sum);
}

?>