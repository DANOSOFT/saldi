<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$ordre_id = explode ("\n","102391
102898
104230
104318
106141
108765
111006
115252
115558
107183
110845
111147
111852
113709
113898
114504
111422
112647
110025
113401
93379
96743
99967
100258
100320
101662
102318
103106
103566
103854
103978
105069
105113
105608
106930
110936
111378
112422
113239
114146
114205
114553
115030");
for ($i=0;$i<count($ordre_id);$i++) {
	$qtxt = "delete from pbs_ordrer where ordre_id = '$ordre_id[$i]'";
	echo "$qtxt<br>";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}

?>
