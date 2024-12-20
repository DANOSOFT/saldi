<?php
if (!function_exists('findDueDate')) {
function findDueDate($id) {
print "<!--function findDueDate start-->";
	$qtxt = "select fakturadate, betalingsbet, betalingsdage from ordrer where id = '$id'";
echo "$qtxt<br>";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['fakturadate']) {
	 $invoiceDate = $r['fakturadate'];
	 $payTerms    = $r['betalingsbet'];
	 $payDays     = $r['betalingsdage'];
	} else return '';
	list($faktaar, $faktmd, $faktdag) = explode("-", $invoiceDate);
	$dueYear=$faktaar;
	$dueMonth=$faktmd;
	$dueDay=$faktdag;
	$endDay=31;
	if (($invoiceDate)&&($payTerms=="Netto"||$payTerms=="Lb. md.")) {
		while (!checkdate($dueMonth, $endDay, $dueYear)) {
			$endDay--;
			if ($endDay<27) break 1;
		}
		if ($payTerms!="Netto"){$dueDay=$endDay;} # Saa maa det vaere lb. md
		$dueDay=$dueDay+$payDays;
		while ($dueDay>$endDay) {
			$dueMonth++;
			if ($dueMonth>12) {
				$dueYear++;
				$dueMonth=1;
			}
			$dueDay=$dueDay-$endDay;
			$endDay=31;
			while (!checkdate($dueMonth, $endDay, $dueYear)) {
				$endDay--;
				if ($endDay<27) break 1;
			}
		}
	}
	$dueDate=$dueYear."-".$dueMonth."-".$dueDay;
	return $dueDate;
print "<!--function find_forfaldsdato slut-->";
}}
?>
