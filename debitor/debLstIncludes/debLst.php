<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor(debLstIncludes/debLst.php --- lap 4.1.1 --- 2025-09-14 ----
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20210812 MSC Implementing new top menu design 
// 20211102 MSC Implementing new top menu design 
// 20230401 PHR	Changed category viewing and fixed some errors
// 20250223 PHR if groups were set in 'visning' and search for one ore more debitors it went into endless loop.
// 20250822 PHR	$sortering set to kontonr if $sortering is an array
// 20250914 LOE Added code to update kontakt field in adresser table if match is found in ansatte table

include("../includes/pagination.php");
$qtxt = "select id,box1,box2,box11 from grupper where art = 'DLV' and kode ='$valg' and kodenr = '$bruger_id'";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$dg_liste=explode(chr(9),$r['box1']);
($r['box2'])?$cat_liste=explode(chr(9),$r['box2']):$cat_liste=array();

$udv1=$udvaelg;
$colspan=$vis_feltantal+1;
$dgcount=count($dg_liste);
if (!$dgcount) $dgcount=1; 
for($i=0;$i<$dgcount;$i++) {
	if ($dg_liste[$i]) {
		for($i2=0;$i2<=$dg_antal;$i2++) {
			if($dg_liste[$i]==$dg_id[$i2]) {
				if (!$start && !$lnr) {
#					print "<tr><td colspan=\"$colspan\"><hr></td>";
					$tmp=$start+$linjeantal;
				}
				if (!$cat_liste[0]) {
					print "<tr><td></td><td colspan=\"2\"><b>$dg_navn[$i2]</b></td></tr>\n";
					print "<tr><td colspan=\"$colspan\"><hr></td>";
				}
				$udv1=$udvaelg." and gruppe=$dg_kodenr[$i2]";	
				break 1;
			} 
		}	
	}
	$catcount=count($cat_liste);
	if (!$catcount) {
		$catcount=1; 
		$cat_liste[0] = '';
	}
	for($i3=0;$i3<$catcount;$i3++) {
		if ($cat_liste[$i3] || $cat_liste[$i3] == 0) {
			for($i4=0;$i4<=$cat_antal;$i4++	) {
				if($cat_liste[$i3]==$cat_id[$i4]) {
					if (!$start && !$lnr) {
						$tmp=$start+$linjeantal;
					}
					print "<tr><td colspan=\"$colspan\"><hr></td></tr><tr>";
					if (isset($i2) && isset($dg_navn[$i2]) && $dg_navn[$i2]) $tmp="<td colspan=\"2\"><b>$dg_navn[$i2]</b></td>";
					else $tmp=""; 
					print "<tr><td></td>$tmp<td colspan=\"2\"><b>$cat_beskrivelse[$i4]</b></td></tr>\n";
					print "<tr><td colspan=\"$colspan\"><hr></td>";
					$udv2=$udv1." and (kategori = '$cat_id[$i4]' or kategori LIKE '$cat_id[$i4]".chr(9)."%' ";
					$udv2.="or kategori LIKE '%".chr(9)."$cat_id[$i4]' or kategori LIKE '%".chr(9)."$cat_id[$i4]".chr(9)."%')";	#20160218
					break 1;
				} 
			}	
		}
		if (!count($cat_liste) || $cat_liste[0] == '') $udv2 = NULL;
		if ($udv2) $i=0; #20250223 added: if ($udv2)
		if (!$udv2) $udv2=$udv1;	
		if (!$udv2) $udv2=$udvaelg;	
		if (strpos($sortering,' ')) {
			list($a,$b) = explode(' ',$sortering);
			if (is_array($a)) $sortering = 'kontonr';
			if (is_array($b)) $sortering = $a;
		}
		if (strpos($sortering,'kontaktet desc')) $udv2.=' and adresser.kontaktet is not NULL';
		$qtxt="select * from adresser where art = 'D' $udv2 order by $sortering";
		$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row=db_fetch_array($query)) {
			$debitorkort="debitorkort".$row['id'];
			$debId[$i]=$row['id'];
			$mySale[$i]=$row['mysale'];
			$email[$i]=$row['email'];
			$kategori[$i]=explode(chr(9),$row['kategori']);
			$lnr++;
			if(($lnr>=$start && $lnr<$slut) || $udv2) { 
#				$debId[$i]=$row['id'];
#				$mySale[$i]=$row['mysale'];
#				$email[$i]=$row['email'];
				$adresseantal++;
				$javascript=$understreg=$hrefslut="";
				
				if ($valg=='kommission') {
					if ($mySale[$i]) {
						$tmp=trim($_SERVER['PHP_SELF'],'/');
						$txt = $row['id'] .'|'. $row['kontonr'] .'@'. $db .'@'. $_SERVER['HTTP_HOST'];
						$lnk=$myLink;
						for ($x=0;$x<strlen($txt);$x++) {
							$lnk.=dechex(ord(substr($txt,$x,1)));
							$understreg="<a class='kommission-link' href='$lnk' target='blank' >";
							$hrefslut="</a>";
						}
#						fwrite($myFile, $db.chr(9).$email[$i].chr(9).$lnk."\n");
					}
				} else {
					if ($valg == 'rental') $understreg="<a href=rental.php?tjek=$row[id]&customerId=$row[id]>";
					else $understreg="<a href=".$valg."kort.php?tjek=$row[id]&id=$row[id]&returside=debitor.php>";
					$hrefslut="</a>";
				}
				$linjetext="";
				if ($linjebg!=$bgcolor) {$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				print "<tr bgcolor=\"$linjebg\" onclick='window.location.href=\"".$valg."kort.php?tjek=$row[id]&id=$row[id]&returside=debitor.php\"'><td></td>\n";
				print "<td align=$justering[0] $javascript> $linjetext $understreg $row[kontonr]$hrefslut</span><br></td>\n";
				for ($x=1;$x<$vis_feltantal;$x++) {


					###############################20250914
					//check ansatte tabble where konto_id is adresser.id. if the is a match and not exists in adresser table, update kontakt in adresser table
					 
					$ps = db_select("SELECT * FROM ansatte WHERE konto_id = $row[id]", __FILE__ . " linje " . __LINE__);
					$ansat = db_fetch_array($ps);
					if($ansat && !$row['kontakt']){
						db_modify("UPDATE adresser SET kontakt = '".$ansat['navn']."' WHERE id = $row[id]", __FILE__ . " linje " . __LINE__);
						$row['kontakt'] = $ansat['navn'];
					}

					#############################
					print "<td align=$justering[$x]>";
					$tmp=$vis_felt[$x];
					if ($vis_felt[$x]=='kontoansvarlig') {
						for ($y=1;$y<=count($ansat_id);$y++) {
							if ($ansat_id[$y]==$row[$tmp]) print stripslashes($ansat_init[$y]);
						}
					} elseif ($vis_felt[$x]=='status') {
						for ($y=0;$y<=$status_antal;$y++) {
							if ($row[$tmp] && $status_id[$y]==$row[$tmp]) print stripslashes($status_beskrivelse[$y]);
						}
				} elseif ($vis_felt[$x]=='invoiced' || $vis_felt[$x]=='kontaktet' || $vis_felt[$x]=='kontaktes') {
					#} elseif ($vis_felt[$x]=='invoiced' || $vis_felt[$x]=='kontaktet' || $vis_felt[$x]=='kontaktes' || $vis_felt[$x]=='kontakt') { #20250914
						if ($row[$tmp]=='1970-01-01') print "";
						else print dkdato($row[$tmp]);
					} 
					elseif (substr($vis_felt[$x],0,4)=='cat_') {
						for ($c = 0; $c < count($kategori[$i]); $c++) {
							if ($vis_felt[$x] == 'cat_'.$kategori[$i][$c]) {
								if ($kategori[$i][$c] || $kategori[$i][$c] == '0') {
									print "&nbsp;<img src=\"../ikoner/checkmrk.png\" style=\"border: 0px solid;\">";
								} else {
									print "&nbsp;<img src=\"../ikoner/slet.png\" style=\"border: 0px solid; \">";
								}
							}
						}
					} else print "$row[$tmp]";
					print "</td>"; 
				}
				print "<input type=hidden name=adresse_id[$adresseantal] value=$row[id]>";
				if ($valg=='kommission') {
					if ($mySale[$i]) $mySale[$i]="checked='checked'";
					print "<td align='center'><input type='checkbox' name='mySale[$i]' $mySale[$i]></td>";
				}
				if ($valg=='kommission' || $valg=='historik') {
					print "<input type='hidden' name='debId[$i]' value='$debId[$i]'>";  
					($email[$i])?$dis=NULL:$dis="disabled title='email mangler på  konto'";
 					if (!$dis && isset($_POST['chooseAll']) && $_POST['chooseAll']) $dis = "checked='checked'";				
					if ($valg=='kommission') print "<td align='center'><input type='checkbox' name='invite[$i]' $dis></td>";
					else print "<td align='center'><label class='checkContainerOrdreliste'><input type='checkbox' name='mailTo[$i]' $dis><span class='checkmarkOrdreliste'></span></label></td>";
				} 
				$i++;
				print "</tr>";
/*
			} elseif($myFile && $mySale[$i]) {
				$txt = $row['id'] .'|'. $row['kontonr'] .'@'. $db .'@'. $_SERVER['HTTP_HOST'];
				$lnk=$myLink;
				for ($x=0;$x<strlen($txt);$x++) {
					$lnk.=dechex(ord(substr($txt,$x,1)));
				}
*/
			}
		}
		if ($search && !$lnr) {
			print "<tr><td colspan='$colspan' align='center'><b><big>Ingen debitorer opfylder de angivne søgekriterier</big></b></td></tr>";
		}
	} 
}

print "<td colspan='100' align=right>";
print  "</td></tr>"; 
?>
