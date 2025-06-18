<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$dd=date("Y-m-d");

$x = 0;
$vareId=array();
#$qtxt = "SELECT kostpriser.vare_id,kostpriser.kostpris,kostpriser.transdate FROM kostpriser";
#$qtxt.= ",varer where kostpriser.vare_id = varer.id and kostpriser.kostpris > '0' and varer.lukket = '0' order by transdate desc";
$qtxt = "SELECT vare_id,kostpris,transdate FROM kostpriser where kostpris > '0' order by transdate desc";

$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if (!in_array($r['vare_id'],$vareId)) {
		$vareId[$x]    = $r['vare_id'];
		$kostpris[$x]  = $r['kostpris'];
		$transdate[$x] = $r['transdate'];
		$x++;
	}
}
$x=0;
$qtxt = "SELECT * FROM kostpriser where kostpris = 0  order by transdate desc";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if (in_array($r['vare_id'],$vareId)) {
		for ($y=0;$y<count($vareId);$y++) {
			if ($r['vare_id'] == $vareId[$y] && $r['transdate'] > $transdate[$y]) {
				$qtxt = "delete from kostpriser where id = '$r[id]'";
				echo "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt = "update varer set kostpris = $kostpris[$y] where id = '$vareId[$y]'";
				echo "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}	
	}
}
$x=0;
$qtxt = "SELECT * FROM varer where kostpris = 0";
echo "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if (in_array($r['id'],$vareId)) {
		for ($y=0;$y<count($vareId);$y++) {
			if ($r['id'] == $vareId[$y]) {
				$qtxt = "update varer set kostpris = $kostpris[$y] where id = '$vareId[$y]'";
				echo "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}	
	}
}
?>
