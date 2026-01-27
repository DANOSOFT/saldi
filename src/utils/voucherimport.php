<?php
@session_start();
$s_id=session_id();
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
ini_set('display_errors',1);
$txt=file_get_contents("../temp/$db/vouchers.csv");
$lines=explode("\n",$txt);
for ($x=0;$x<count($lines);$x++) {
	list($barcode,$amount) = explode("\t",$lines[$x]);
	$amount = usdecimal($amount);
	echo "Barcode $barcode,Amount $amount<br>";
	$qtxt = "insert into voucher(item_id,barcode) values ('50','$barcode')";
	echo "$qtxt<br>";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($r=db_fetch_array(db_select("select id from voucher where barcode = '$barcode'",__FILE__ . " linje " . __LINE__))) {
		$id = $r['id'];
		$vat = $amount/4;
		db_modify("insert into voucheruse(voucher_id,order_id,amount,vat) values ($id,'0',$amount,$vat)",__FILE__ . " linje " . __LINE__);
	}
}
?>
