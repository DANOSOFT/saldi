<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor(debLstIncludes/debLst.php --- lap 4.0.1 --- 2021-03-23 ----
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
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
$dd   = date('U');
$H24  = 60*60*24;
$dd60 = $dd+$H24*60;
$udv1=$udvaelg;
$colspan=$vis_feltantal+1;
$dgcount=count($dg_liste);
(!$dgcount)?$dgcount=1:NULL; 
for($x=0;$x<$dgcount;$x++) {
	if ($dg_liste[$x]) {
		for($x2=0;$x2<=$dg_antal;$x2++) {
			if($dg_liste[$x]==$dg_id[$x2]) {
				if (!$start && !$lnr) {
#					print "<tr><td colspan=\"$colspan\"><hr></td>";
					$tmp=$start+$linjeantal;
				}
				if (!$cat_liste[0]) {
					print "<tr><td></td><td colspan=\"2\"><b>$dg_navn[$x2]</b></td></tr>\n";
					print "<tr><td colspan=\"$colspan\"><hr></td>";
				}
				$udv1=$udvaelg." and gruppe=$dg_kodenr[$x2]";	
				break 1;
			} 
		}
	}
	$catcount=count($cat_liste);
	(!$catcount)?$catcount=1:NULL; 
	for($x3=0;$x3<$catcount;$x3++) {
		if ($cat_liste[$x3]) {
			for($x4=0;$x4<=$cat_antal;$x4++	) {
				if($cat_liste[$x3]==$cat_id[$x4]) {
					if (!$start && !$lnr) {
						$tmp=$start+$linjeantal;
					}
					print "<tr><td colspan=\"$colspan\"><hr></td></tr><tr>";
					if ($dg_navn[$x2]) $tmp="<td colspan=\"2\"><b>$dg_navn[$x2]</b></td>";
					else $tmp=""; 
					print "<tr><td></td>$tmp<td colspan=\"2\"><b>$cat_beskrivelse[$x4]</b></td></tr>\n";
					print "<tr><td colspan=\"$colspan\"><hr></td>/tr>";
					$udv2=$udv1." and (kategori = '$cat_id[$x4]' or kategori LIKE '$cat_id[$x4]".chr(9)."%' ";
					$udv2.="or kategori LIKE '%".chr(9)."$cat_id[$x4]' or kategori LIKE '%".chr(9)."$cat_id[$x4]".chr(9)."%')";	#20160218
					break 1;
				} 
			}	
		}
		$tmp=$colspan + 1;
		print "<tr><td colspan='$tmp'><table><tbody>";
		$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
		print "<tr bgcolor='$linjebg'><td>Måned</td><td></td>";
		for ($d=$dd;$d<$dd60;$d+=$H24) {
			print "<td style='width:15px'>";
			if ($d==$showPeriodFrom) print date('m',$d);
			if (date('d',$d)=='1') print date('m',$d);
			print "</td>";
		}
		$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
		print "</tr><tr bgcolor='$linjebg'><td>Dato</td><td></td>";
		for ($d=$dd;$d<$dd60;$d+=$H24) {
			print "<td style='width:15px'>";
			print date('d',$d);
			print "</td>";
		}
		print "</tr>";

		$x=0;
		if (!$udv2) $udv2=$udv1;	
		if (!$udv2) $udv2=$udvaelg;
		$qtxt="select * from adresser where art = 'D' $udv2 order by $sortering";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$rpCustId[$x]=$r['id'];
			$rpCustNo[$x]=$r['kontonr'];
			$rpCustName[$x]=$r['firmanavn'];
			$lnr++;
			if(($lnr>=$start && $lnr<$slut) || $udv2) {
				$rpId[$x]   = array();
				$rpRtId[$x] = array();
				$rpFrom[$x] = array();
				$rpTo[$x]   = array();
				$qtxt = "select * from rentalperiod where ((rt_from >= '$dd' and rt_from <= '$dd60') or ";
				$qtxt.= "(rt_to >= '$dd' and rt_to <= '$dd60')) and rt_cust_id = $rpCustId[$x]"; 
				$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__); 
				while ($r2=db_fetch_array($q2)) {
					$rpId[$x][$y]          = $r2['id'];
					$rpRtId[$x][$y]        = $r2['rt_id'];
					$rpFrom[$x][$y]        = $r2['rt_from'];
					$rpTo[$x][$y]          = $r2['rt_to'];
					$y++;
				}
				$setgray = 0;
				$linjebg=linjefarve($linjebg,$bgcolor,$bgcolor5,'0','0');
				print "<tr bgcolor='$linjebg'>";
				print "<td><a href='rental.php?rtItemId=$rtItemId&modRt=$rtId[$x]&customerId=$rpCustId[$x]'>$rpCustNo[$x]</a></td>";
				if (strlen($rpCustName[$x]) > 10) $tmp=substr(utf8_decode($rpCustName[$x]),0,10)."..";
				else $tmp = utf8_decode($rpCustName[$x]);
				print "<td title='$rpCustName[$x]'>". utf8_encode($tmp) ."</td>";
				for ($d=$dd;$d<$dd60;$d+=$H24) {
					$title   = "Ingen booking";
					$tdcolor = 'green';
					$tdtxt   = NULL;
					$onclick.= "\"";
					for ($y=0;$y<count($rpId[$x]);$y++) {
						if ($rpFrom[$x][$y] <= $d && $rpTo[$x][$y] >= $d) {
							$title = $rpCustNo[$x] ." : ". $rpCustName[$x] ."(". date("d.m.y",$rpFrom[$x][$y]) ." - ";
							$title.= date("d.m.y",$rpTo[$x][$y]) .")";
							$tdcolor = 'red';
							$tmp     = $rpId[$x][$y]; 
						}
					}
					print "<td title='$title' style='width:15px;background-color:$tdcolor' $onclick>";
					print $tdtxt;
					print "</td>\n";
				}
				print "</tr>\n";
		}}
		print "</tbody></table></td></tr>";
		if ($search && !$lnr) {
			print "<tr><td colspan='$colspan' align='center'><b><big>Ingen debitorer opfylder de angivne søgekriterier</big></b></td></tr>";
		}
	} 
}	
?>
