<?php
// ------------includes/usdate.php----lap 1.1.4------13.12.07 --------------------
	// LICENS
	//
	// Dette program er fri software. Du kan gendistribuere det og / eller
	// modificere det under betingelserne i GNU General Public License (GPL)
	// som er udgivet af The Free Software Foundation; enten i version 2
	// af denne licens eller en senere version efter eget valg
	//
	// Dette program er udgivet med haab om at det vil vaere til gavn,
	// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
	// GNU General Public Licensen for flere detaljer.
	//
	// En dansk oversaettelse af licensen kan laeses her:
	// http://www.fundanemt.com/gpl_da.html
	//
	// Copyright (c) 2004-2007 DANOSOFT ApS
// ----------------------------------------------------------------------

if (!function_exists('usdate')) {
	function usdate($date) {
		if (strlen($date) == 6) {
			global $id;
			$g1=substr($date,0,2);
			$g2=substr($date,2,2);
			$g3=substr($date,4,2);
			$date=$g1."-".$g2."-".$g3;
		} 
		if (!isset($date) || !$date) $date=date("d-m-Y");
		list ($day, $month, $year) = split ('-', $date);

		$year=$year*1;
		$month=$month*1;
		$day=$day*1;

		if ($year<10){$year='0'.$year;}
		if ($month<10){$month='0'.$month;}
		if ($day<10){$day='0'.$day;}
		 
		 if ($day>28) {
			while (!checkdate($month,$day,$year)){
				$day=$day-1;
				if ($day<28){break;}
			}
		 }
		 
		if ($year < 80) {$year = "20".$year;}
		elseif ($year < 100) {$year = "19".$year;}

		if (checkdate($month, $day, $year)) {$date = $year . "-" . $month . "-" . $day;}
		else {$date=date("Y-m-d");}
		
		return $date;
	}
}
?>
