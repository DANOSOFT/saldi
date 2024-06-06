<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/rentalIncludes/showCustomerList.php --- lap 4.0.1 --- 2021-03-14 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2021 Saldi.dk ApS
// ----------------------------------------------------------------------


print "<div style='float:left;'>";
print "<a href='rental.php?show=customer&showPeriodFrom=$prePeriod'>Forrige</a><br>";
print "<br><a href='../lager/varer.php'>Varer</a>";
print "<br><a href='../debitor/debitor.php?valg=rental'>Kunder</a></div>";
print "<div style='width:90%;float:left;text-align:center'>$txt</div>";
print "<div style='float:right;text-align:right'>";
print "<a href='rental.php?&show=customer&showPeriodFrom=$nextPeriod'>Næste</a>";
print "</div>";
#print "<a href='rental.php?show=customer&&showPeriodFrom=$showPeriodFrom&page=rtSettings'><img src='../img/settings20x20.png'></a>";
print "<table align = 'center'><tbody>";
print "<tr><td colspan='10'><b><a href='../lager/varer.php'</a></b></td></tr>";
$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
print "<tr bgcolor='$linjebg'><td>Måned</td>";
for ($d=$showPeriodFrom;$d<$maxTo;$d+=$H24) {
	print "<td style='width:15px'>";
	if ($d==$showPeriodFrom) print date('m',$d);
	if (date('d',$d)=='1') print date('m',$d);
	print "</td>";
}
$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
print "</tr><tr bgcolor='$linjebg'><td>Konto</td><td>Navn</td>";
for ($d=$showPeriodFrom;$d<$maxTo;$d+=$H24) {
	print "<td style='width:15px'>";
	print date('d',$d);
	print "</td>";

}
print "</tr>";
for ($x=0;$x<count($rpCustId);$x++) {
	$setgray = 0;
	$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
	print "<tr bgcolor='$linjebg'>";
	print "<td><a href='rental.php?rtItemId=$rtItemId&modRt=$rtId[$x]&customerId=$rpCustId[$x]'>$rpCustNo[$x]</a></td>";
	print "<td title='$rpCustName[$x]'>". substr($rpCustName[$x],0,10) ."</td>";
	for ($d=$showPeriodFrom;$d<$maxTo;$d+=$H24) {
		$title   = "Klik i et felt for at starte booking";
		$tdcolor = 'green';
		$tdtxt   = NULL;
		$onclick = "onclick = 'window.location.href=\"rental.php?rtItemId=$rtItemId&thisRtId=$rtId[$x]"; 
		if($rtPeriodFrom && $rtPeriodFrom < $d) $onclick.= "&rtPeriodFrom=$rtPeriodFrom&rtPeriodTo=$d'";
		else $onclick.= "&rtPeriodFrom=$d'";
		$onclick.= "\"";
#		if ($thisRtId == $rtId[$x]) echo $rpFrom[$x][$y] ." < $d<br>";
#		if ($rpTo[$x][$y] && $rtPeriodFrom >= $rpTo[$x][$y]) {
#		echo $rpTo[$x][$y] ."  == $d<br>";
#		if ($rpTo[$x][$y] == $d) $setgray = 1;
#		}
	if ($rtPeriodFrom && ($thisRtId!=$rpRtId[$x] || $rtPeriodFrom > $d || $setgray))  {
			$title = "Kan ikke vælges";
			$tdcolor = 'grey';
			$tmp     = $rpId[$x][$y]; 
			$onclick = NULL; 
		} elseif ($thisRtId==$rtId[$x] && date('ymd',$rtPeriodFrom)  ==  date('ymd',$d)) {
			$title   = "Vælg slutdato";
			$tdcolor = 'red'; 
			$onclick = "$onclick = 'window.location.href=\"rental.php?rtItemId=$rtItemId[$x]&thisRtId=$rtId[$x]";
			$onclick.= "&rtPeriodTo=$d&rtPeriodFrom=$rtPeriodFrom\"'"; 
			$tdtxt='->';
		} else {
			for ($y=0;$y<count($rpId[$x]);$y++) {
				if ($rpFrom[$x][$y] <= $d && $rpTo[$x][$y] >= $d) {
					$title = $rpCustNo[$x][$y] ." : ". $rpCustName[$x][$y] ."(". date("d.m.y",$rpFrom[$x][$y]) ." - ";
					$title.= date("d.m.y",$rpTo[$x][$y]) .") Klik for at rette";
					$tdcolor = 'red';
					$tmp     = $rpId[$x][$y]; 
					$onclick = "onclick = 'window.location.href=\"rental.php?rtItemId=$rtItemId[$x]&rtId=$rtId[$x]&editRpId=$tmp\"'";
				}
#				if ($rpTo[$x][$y] < $d+60*60*24) $setgray = 1;
			}
		}
		print "<td title='$title' style='width:15px;background-color:$tdcolor' $onclick>";
		print $tdtxt;
		print "</td>\n";
	}
	print "</tr>\n";
}
print "<tr><td><a href='rental.php?rtItemId=$rtItemId&newRt=1'>Tilføj enhed</a></td>";
print "</tr>";
print "</tbody></table>";

?>

