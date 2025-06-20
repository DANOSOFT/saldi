<?php
@session_start();
$s_id=session_id();


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/db_query.php");

transaktion('begin');

if (!db_fetch_array(db_select("SELECT attrelid FROM pg_attribute where attname='udlign_id'"))) {
	db_modify("ALTER TABLE ansatte ADD provision numeric");
	db_modify("ALTER TABLE openpost ADD udlign_id integer");
	db_modify("ALTER TABLE openpost ADD udlign_date date");
	db_modify("UPDATE openpost SET udlign_id = '0'");
}
$y=0;
for ($i=1;$i<=5;$i++) {

	$kontrol=array();
	$x=0;
	if ($i<=2) $q1=db_select("SELECT id, faktnr, amount, konto_id from openpost where faktnr != '' and udlignet='1' and udlign_id = '0' order by transdate");
	elseif ($i==3) $q1=db_select("SELECT id, amount, konto_id from openpost where faktnr != '' and udlignet='1' and udlign_id = '0' order by transdate");
	elseif ($i==4) $q1=db_select("SELECT id, amount, konto_id from openpost where faktnr = '' and udlignet='1' and udlign_id = '0' order by transdate");
	elseif ($i==5) $q1=db_select("SELECT id, amount, konto_id from openpost where udlignet='1' and udlign_id = '0' order by transdate");

	while ($r1=db_fetch_array($q1)) {
	$tmp=$r1['konto_id'].$r1['amount'];
		if (!in_array($tmp, $kontrol)) {
			$x++;
			$id[$x]=$r1['id'];
			$faktnr[$x]=$r1['faktnr'];
			$amount[$x]=$r1['amount']*-1;
			$konto_id[$x]=$r1['konto_id'];
		}
	}
	$faktantal=$x;

	for ($x=1; $x<=$faktantal; $x++) {
		if ($i==1) $q1=db_select("SELECT id, transdate, faktnr from openpost where faktnr = '$faktnr[$x]' and id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and udlignet='1' and udlign_id = '0'");
                elseif ($i==2) $q1=db_select("SELECT id, transdate from openpost where faktnr = '' and id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and  udlign_id = '0' and udlignet='1'");
		elseif ($i<=4) $q1=db_select("SELECT id, transdate from openpost where id != '$id[$x]' and amount='$amount[$x]' and konto_id='$konto_id[$x]' and  udlign_id = '0' and udlignet='1'");
		elseif ($i==5) $q1=db_select("SELECT id, transdate from openpost where id = '$id[$x]'");
		$z=0;
		while ($r1=db_fetch_array($q1)) {
			$z++;
			$id2=$r1['id'];	
			$transdate=$r1['transdate'];	
		}
		if ($z==1) {
			$y++;
			db_modify("update openpost set udlign_id='$y', udlign_date='$transdate' where id='$id[$x]'");
			db_modify("update openpost set udlign_id='$y', udlign_date='$transdate' where id='$id2'");
		}
	}	
}
echo "$y posteringer opdateret<br>";
transaktion('commit');

?>

