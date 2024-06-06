<?php
@session_start();
$s_id=session_id();

include("../includes/std_func.php");
include("../includes/connect.php");
$unixtime = date('U');
#$logdatetime=array();
#$sum=array();
$qtxt = "insert into online (session_id, brugernavn, db, dbuser, logtime,language_id,revisor,rettigheder) values ";
$qtxt.= "('$s_id', 'phr', '$sqdb', '$squser', '$unixtime','1','1','11111111111111111111111')";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);


$i=0;
$qtxt = "select id,db from regnskab where sidst > '1672545892' and id > '1' order by id";
echo "$qtxt<br>";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$dbName[$i] = $r['db'];
#cho "db $dbName[$i]<br>";
	$i++;
}
for ($i=0;$i<count($dbName);$i++) {
	$qtxt = "update online set db='$dbName[$i]' where session_id = '$s_id'";
#cho "$qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	include("../includes/online.php");
	$j=0;
	$ordre_id[$i]=array();
	$qtxt = "select * from transaktioner where kontonr = '0' and bilag = '0' and ordre_id > '0'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$transId[$i][$j] = $r['id'];
		$ordre_id[$i][$j] = $r['ordre_id'];
		$moms[$i][$j] = $r['moms'];
		echo "Tjek db $dbName[$i] ".$transId[$i][$j]." ".$ordre_id[$i][$j]." ". $moms[$i][$j] ."<br>";
	}
	include("../includes/connect.php");
}
$qtxt = "update online set db='$sqdb' where session_id = '$s_id'";
#$qtxt = "delete from online where session_id = '$s_id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

/*
$i=0;
if (count($ordre_id)) {
	$qtxt = "select box1 from grupper where art = 'SM'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
			 = $r['ordre_id'];
		$transId[$i] = $r['id'];
		$i++;
	}
}
for ($i=0;$i<count($ordre_id);$i++) {
	$qtxt = "select konto_id from ordrer where id = $ordre_id[$i]";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$konto_id[$i]	 = $r['konto_id'];
}
for ($i=0;$i<count($ordre_id);$i++) {
	$qtxt = "select gruppe from adresser where id = $konto_id[$i]";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$gruppe[$i]	 = $r['gruppe'];
}
for ($i=0;$i<count($ordre_id);$i++) {
	$qtxt = "select gruppe from adresser where id = $konto_id[$i]";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$gruppe[$i]	 = $r['gruppe'];
}


 if ($vis) {
	echo "ID $db_id<br>";
	if ($bogfor) echo "Bogfører $bogfor<br>";
	else echo "Bogfører ikke<br>";
}

if ($bogfor) transaktion('begin');



#sleep(2600);
if ($bogfor) transaktion('commit');
*/
?>
