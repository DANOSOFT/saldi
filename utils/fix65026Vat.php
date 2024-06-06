<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

#$logdatetime=array();
#$sum=array();
$bogfor=if_isset($_GET['bogfor']);
$vis=if_isset($_GET['vis']);
$email=if_isset($_GET['email']);

$x=0;
$total=0;
$subtotal=0;
$fejl=0;

/*
if ($vis) {
	echo "ID $db_id<br>";
	if ($bogfor) echo "Bogfører $bogfor<br>";
	else echo "Bogfører ikke<br>";
}
*/

#if ($bogfor) transaktion('begin');

$qtxt = "select transdate, sum(debet) as debet, sum(kredit) as kredit from transaktioner ";
$qtxt.= "group by transdate order by transdate"; 
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	$transDate[$x] = $r['transdate'];
	$debet[$x]     =  $r['debet'];
	$kredit[$x]    = $r['kredit'];
	$diff[$x]      = afrund($debet[$x] - $kredit[$x],2);
	$x++;
}
for ($x=0;$x<count($transDate);$x++) {
#cho "$transDate[$x] | $diff[$x]<br>";
	if ($diff[$x]) {
		$qtxt = "select id from transaktioner where transdate = '$transDate[$x]' and debet = $diff[$x]";
echo "$qtxt<br>";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			echo "$transDate[$x] | $r[id] | $diff[$x]<br>";
			$qtxt = "update transaktioner set debet = 0 where id = '$r[id]'";
#			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$diff[$x] = 0;
		}
	}
}
for ($x=0;$x<count($transDate);$x++) {
echo __line__." $transDate[$x] | $diff[$x]<br>";
	if ($diff[$x]) {
		$tsum=$y=0;
		$transId = array();
		$qtxt = "select id,debet from transaktioner where transdate = '$transDate[$x]' and debet < $diff[$x] order by id";
echo __line__." $qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
		$transId[$y] = $r['id'];
		$transAmount[$y] = $r['debet'];
		$y++;
#			echo "$transDate[$x] | $r[id] | $diff[$x]<br>";
#			$qtxt = "update transaktioner set debet = 0 where id = '$r[id]'";
#			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#			$diff[$x] = 0;
		}
		for ($y=0;$y<count($transId);$y++) {
			if ($transAmount[$y] == $diff[$x]) {
				echo __line__." MATCH $transDate[$x] $diff[$x]<br>";
					$qtxt = "update transaktioner set debet = 0 where id = '$transId[$y]'";
echo "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else {
				for ($z=$y+1;$z<count($transId);$z++) {
					if($transAmount[$y] + $transAmount[$z]  == $diff[$x]) {
						echo __line__." MATCH $transDate[$x] | $transAmount[$y] + $transAmount[$z] = $diff[$x]<br>";
						$qtxt = "update transaktioner set debet = 0 where id = '$transId[$y]' and debet = $transAmount[$y]";
echo "$qtxt<br>";
#							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						$qtxt = "update transaktioner set debet = 0 where id = '$transId[$z]' and debet = $transAmount[$z]";
echo "$qtxt<br>";
#							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}					
				}
			}
		}
	}
}

# transaktion('commit');

?>
