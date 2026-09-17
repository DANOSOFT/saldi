<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------debitor/rentalIncludes/showItemList.php-------------lap 4.0.1-----2021-03-14-----
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
// 20220901 MSC - Implementing new design

print "<div style='display: flex; flex-direction: column;'>";
print "<div style='display: flex; flex-direction: row;'><div style=' width:10%;'>";
print "<a href='rental.php?rtItemId=$rtItemId&thisRtId=$thisRtId&showPeriodFrom=$prePeriod&";
print "customerId=$customerId'>Forrige</a></div>";
print "<div style='width:90%;text-align:center;'>$txt</div>";
print "<div style='text-align:right; width:10%;'>";
print "<a href='rental.php?rtItemId=$rtItemId&thisRtId=$thisRtId&showPeriodFrom=$nextPeriod&";
print "rtPeriodFrom=$rtPeriodFrom&customerId=$customerId'>Næste</a>";
print "</div></div>";
print "<div style='display: flex; flex-direction: row;'><div style='width:5%; text-align:center;'>";
if ($customerId) print "<br><a href='rental.php?rtItemId=$rtItemId&thisRtId=$thisRtId&showPeriodFrom=$thisPeriod&customerId=*'>Alle</a>";
print "<a href='../lager/varer.php'>Varer</a><br>";
print "<a href='../debitor/debitor.php?valg=rental'>Kunder</a></div>";
print "<div style='margin-left:10px; width:95%;'><table align = 'center' width='100%' class='dataTableSmall'><tbody>";
print "<tr><td colspan='10'><b>";
#print "<a href='rental.php?rtItemId=$rtItemId&thisRtId=$thisRtIdd&&showPeriodFrom=$showPeriodFrom&page=rtSettings&customerId=$customerId'>";
if ($customerId) {
	print "<a href='debitor.php?valg=rental'>$customerName</a> - ";
}
print "<a href='rental.php?rtItemId=$rtItemId&thisRtId=$thisRtIdd&&showPeriodFrom=$showPeriodFrom&page=rtSettings&customerId=$customerId'>";
	print "$itemName</a>";
print "</b></td></tr>";
$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
print "<tr bgcolor='$linjebg'><td>Måned</td>";
for ($d=$showPeriodFrom;$d<$maxTo;$d+=$H24) {
	print "<td style='width:15px'>";
	if ($d==$showPeriodFrom) print date('m',$d);
	if (date('d',$d)=='1') print date('m',$d);
	print "</td>";
}
$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
print "</tr><tr bgcolor='$linjebg'><td>Dato</td>";
for ($d=$showPeriodFrom;$d<$maxTo;$d+=$H24) {
	print "<td style='width:15px'>";
	print date('d',$d);
	print "</td>";
}
print "</tr>";
for ($x=0;$x<count($rtId);$x++) {
	$setgrey = 0;
	$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
	print "<tr bgcolor='$linjebg'>";
	print "<td><a href='rental.php?rtItemId=$rtItemId&modRt=$rtId[$x]&editRtName=$rtName[$x]&customerId=$customerId'>$rtName[$x]</a></td>";
	for ($d=$showPeriodFrom;$d<$maxTo;$d+=$H24) {
		$title   = "Klik i et felt for at starte booking";
		$tdcolor = 'green';
		$tdtxt   = NULL;
		$onclick = "onclick ='window.location.href=\"rental.php?rtItemId=$rtItemId&thisRtId=$rtId[$x]&customerId=$customerId"; 
		if($rtPeriodFrom && $rtPeriodFrom < $d) $onclick.= "&rtPeriodFrom=$rtPeriodFrom&rtPeriodTo=$d";
		else $onclick.= "&rtPeriodFrom=$d";
		$onclick.= "\"'";
#		if ($thisRtId == $rtId[$x]) echo $rpFrom[$x][$y] ." < $d<br>";
#		if ($rpTo[$x][$y] && $rtPeriodFrom >= $rpTo[$x][$y]) {
#		echo $rpTo[$x][$y] ."  == $d<br>";
#		if ($rpTo[$x][$y] == $d) $setgrey = 1;
#		}

		if ($rtPeriodFrom && ($thisRtId!=$rtId[$x] || $rtPeriodFrom > $d || $setgrey == 1))  {
		$title = "Kan ikke vælges";
			$tdcolor = 'grey';
			$tmp     = $rpId[$x][$y]; 
			$onclick = NULL; 
		} elseif ($thisRtId==$rtId[$x] && date('ymd',$rtPeriodFrom)  ==  date('ymd',$d)) {
			$title   = "Vælg slutdato";
			$tdcolor = 'red';
			$onclick = "onclick = 'window.location.href=\"rental.php?rtItemId=$rtItemId[$x]&thisRtId=$rtId[$x]&customerId=$customerId";
			$onclick.= "&rtPeriodTo=$d&rtPeriodFrom=$rtPeriodFrom\"'"; 
			$tdtxt='->';
		} else {
			for ($y=0;$y<count($rpId[$x]);$y++) {
				if ($rpFrom[$x][$y] <= $d && $rpTo[$x][$y] >= $d) {
					if ($customerId && $customerId != $rpCustId[$x][$y]) {
						$title = $rpCustNo[$x][$y] ." : ". $rpCustName[$x][$y] ."(". date("d.m.y",$rpFrom[$x][$y]) ." - ";
						$title.= date("d.m.y",$rpTo[$x][$y]) .") Resereveret";
						$tdcolor = 'grey';
						$setgrey = 1;
						$tmp     = $rpId[$x][$y]; 
						$onclick = NULL; 
					} else {
						$title = $rpCustNo[$x][$y] ." : ". $rpCustName[$x][$y] ."(". date("d.m.y",$rpFrom[$x][$y]) ." - ";
						$title.= date("d.m.y",$rpTo[$x][$y]) .") Klik for at rette";
						$tdcolor = 'red';
						$tmp     = $rpId[$x][$y]; 
						$onclick = "onclick = 'window.location.href=\"rental.php?";
						$onclick.= "rtItemId=$rtItemId[$x]&customerId=$customerId&rtId=$rtId[$x]&editRpId=$tmp\"'";
					}
				}
#				if ($rpTo[$x][$y] < $d+60*60*24) $setgrey = 1;
			}
		}
		print "<td title='$title' style='width:15px;background-color:$tdcolor' $onclick>";
		print $tdtxt;
		print "</td>\n";
	}
	print "</tr>\n";
}
print "<tr><td><a href='rental.php?rtItemId=$rtItemId&newRt=1&customerId=$customerId'>Tilføj enhed</a></td>";
print "</tr>";
print "</tbody></table></div></div></div>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>

