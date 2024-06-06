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
$x=0;
$transId_1050=array();
$q=db_select("select * from transaktioner where kontonr = 1050 and moms = '0'",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)){
	if ($r['moms'] == 0) {
		$transId_1050[$x] = $r['id'];
		$transDate[$x]    = $r['transdate'];
		$debet[$x]        =  $r['debet'];
		$kredit[$x]       = $r['kredit'];
		$kasseNo[$x]      = $r['kasse_nr'];
		$reportNo[$x]     = $r['report_number'];
		$x++;
	}
}
$logdate = date('Y-m-d');
$logtime = date('H:i:s');
transaktion('begin');
for ($x=0;$x<count($transId_1050);$x++) {
	$qtxt = "select * from transaktioner where kontonr = '65025' and debet = '$kredit[$x]' and kredit = '$debet[$x]'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$transId_65025[$x] = $r['id'];
		$moms[$x] = ($debet[$x] - $kredit[$x]) * 0.2;
		$qtxt = "update transaktioner set debet = $debet[$x]*0.8, kredit = $kredit[$x]*0.8,";
		$qtxt.= "moms = $moms[$x] where id = $transId_1050[$x]";
echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "insert into transaktioner ";
		$qtxt.= "(bilag,transdate,beskrivelse,kontonr,faktura,debet,kredit,kladde_id,afd,logdate,logtime,";
		$qtxt.= "projekt,ansat,ordre_id,kasse_nr,report_number,moms) values ";
		$qtxt.= "('0','$transDate[$x]','Moms af kommisionssalg, Kasse $kasseNo[$x]','66100','0',$debet[$x]*0.2,$kredit[$x]*0.2,";
		$qtxt.= "0,'0','$logdate','$logtime',";
		$qtxt.= "'','0','0','$kasseNo[$x]','$reportNo[$x]','0')";
echo "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} else $transId_65025[$x] = '0';
}
$qtxt = "update transaktioner set kontonr = 1051 where 	kontonr = '1050' and moms = 0";
echo __line__." $qtxt<br>";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

$qtxt = "update transaktioner set kredit = kredit * 0.8, moms = kredit * -0.2 where kontonr = '1051' and moms = 0";
echo __line__." $qtxt<br>";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

$qtxt = "update transaktioner set debet = debet * 0.8, moms = debet * 0.2 where kontonr = '1051' and moms = 0";
echo __line__." $qtxt<br>";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

$qtxt = "update transaktioner set kredit = kredit * 0.8, moms = kredit * -0.2 where kontonr = '65026' and moms = 0";
echo __line__." $qtxt<br>";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

$qtxt = "update transaktioner set debet = debet * 0.8, moms = debet * 0.2 where kontonr = '65026' and moms = 0";
echo __line__." $qtxt<br>";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

transaktion('commit');

?>
