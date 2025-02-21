<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------ systemdata/syssetup_skriv_formtabel.php -- lap 4.1.0 -- 2024-05-01 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20181102 PHR Oprydning, udefinerede variabler.
// 20190221 MSC - Rettet isset fejl
// 20200308 PHR - Added $stockIO
// 20240604 PHR - PHP8

function skriv_formtabel($a,$x,$y,$art,$id,$k,$kodenr,$beskrivelse,$box1,$b1,$box2,$b2,$box3,$b3,$box4,$b4,$box5,$b5,$box6,$b6,$box7,$b7,$box8,$b8,$box9,$b9,$box10,$b10,$box11,$b11,$box12,$b12,$box13,$b13,$box14,$b14) {

print "<!-- skriv_formtabel.php begin -->\n";

global $charset,$feltbredde,$stockIO,$valg;

if (!isset ($b)) $b = null;
	for ($i=1; $i<=$x; $i++) {
		if (!isset($art[$i])) $art[$i]=NULL;
		if ($art[$i]=='MR') $momsrapport=$i;
		if (($art[$i]=='SM' || $art[$i]=='KM' || $art[$i]=='YM' || $art[$i]=='EM' || $art[$i]=='VK') && $box2!='-') $box2[$i]=dkdecimal($box2[$i],2);
		if ($art[$i]=='DG' && isset($box6[$i])) $box6[$i]=dkdecimal($box6[$i],2);
		if ($art[$i]=='DG' && isset($box7[$i])) $box7[$i]=dkdecimal($box7[$i],2);
		if ($art[$i]=='VPG') {
			if (isset($box1[$i]) && $box1[$i]) $box1[$i]=dkdecimal($box1[$i],2);
			if (isset($box2[$i]) && $box2[$i]) $box2[$i]=dkdecimal($box2[$i],2);
			if (isset($box3[$i]) && $box3[$i]) $box3[$i]=dkdecimal($box3[$i],2);
			if (isset($box4[$i]) && $box4[$i]) $box4[$i]=dkdecimal($box4[$i],2);
		}
		if ($art[$i]=='VTG') {
			if (isset($box1[$i]) && $box1[$i]) $box1[$i]=dkdecimal($box1[$i],2);
			if (isset($box2[$i]) && $box2[$i]) $box2[$i]=dkdecimal($box2[$i],2);
			if (isset($box3[$i]) && $box3[$i]) $box3[$i]=dkdato($box3[$i]);
			if (isset($box4[$i]) && $box4[$i]) $box4[$i]=dkdato($box4[$i]);
		}
		if ($valg=='projekter'||$art[$i]=='PRJ') $size=$feltbredde*10;
		else $size=20;
		$size.="px";
		if ($art[$i]==$a){
			print "<tr><td>";
			print "$k</td>\n";
			$titletxt="Dette felt kan ikke &aelig;ndres. Dog&nbsp;kan&nbsp;du&nbsp;slette&nbsp;hele&nbsp;linjen&nbsp;ved&nbsp;at&nbsp;&aelig;ndre&nbsp;indholdet&nbsp;i&nbsp;feltet&nbsp;til&nbsp;et&nbsp;-&nbsp;(minus)."; 
			print "<td><input class=\"inputbox\" title=\"$titletxt\" type=\"text\" style=\"text-align:right;width:$size\" name=\"kodenr[$i]\" value=\"$kodenr[$i]\"></td>\n";
			print "<td><input class=\"inputbox\" type=\"text\" size=\"40\" name=\"beskrivelse[$i]\" value=\"$beskrivelse[$i]\"></td>\n";
			if (($box1!="-") &&($b1!="checkbox")){
				if ($art[$i]=='VRG') {
					print "<td title=\"".titletxt($art[$i],'box1')."\"><SELECT NAME=box1[$i] style=\"width: 4em\">";
					if ($box1[$i] == 'amount') {
						print "<option value=\"amount\">kr</option>";
						print "<option value=\"percent\">%</option>";
					} else {
						print "<option value=\"percent\">%</option>";
						print "<option value=\"amount\">kr</option>";
					}
					print "</SELECT></td>\n";
				} elseif ($art[$i]=='AFD') {
					$l=0;
					$l_nr = array();
					$q=db_select("select * from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)){
#						$l_id[$l]=$r['id'];
						$l_besk[$l]=$r['beskrivelse'];
						if (!$box1[$i] && $l_nr[$l]==$kodenr[$i]) $box1[$i]=$l_nr[$l];
						$l++;
					}
					if (count($l_nr)) {
						print "<td title=\"".titletxt($art[$i],'box1')."\"><SELECT NAME=box1[$i] style=\"width:".$b[$i]."em\">";
						for ($l=0;$l<count($l_nr);$l++) {
							if ($box1[$i] == $l_nr[$l]) print "<option value=\"$l_nr[$l]\">$l_nr[$l] : $l_besk[$l]</option>";
						}
						for ($l=0;$l<count($l_nr);$l++) {
							if ($box1[$i] != $l_nr[$l]) print "<option value=\"$l_nr[$l]\">$l_nr[$l] : $l_besk[$l]</option>";
						}
						print "</SELECT></td>\n";
					}
				} else {
					print "<td title=\"".titletxt($art[$i],'box1')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b1\" name=\"box1[$i]\" value=\"$box1[$i]\"></td>\n";
				}
			} elseif($b1=="checkbox") {
				if (strstr($box1[$i],'on')) {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box1[$i]\" checked></td>\n";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box1[$i]\"></td>\n";}
			}
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if (($box2!="-") &&($b2!="checkbox")) {
				print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b2\" name=\"box2[$i]\" value=\"$box2[$i]\"></td>\n";
			}	elseif($b2=="checkbox"){
				if (strstr($box2[$i],'on')){
					print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box2[$i]\" checked></td>\n";
				} else print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box2[$i]\"></td>\n";
			}
			print "<input type = \"hidden\" name=\"id[$i]\" value=\"$id[$i]\"><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if (($box3!="-") &&($b3!="checkbox")){
				($art[$i]=='DG' || $art[$i]=='KG')?$readonly='readonly="readonly"':$readonly=NULL;
				print "<td title=\"".titletxt($art[$i],'box3')."\"><input class=\"inputbox\" $readonly type=\"text\" style=\"text-align:right\" size=\"$b3\" name=\"box3[$i]\" value=\"$box3[$i]\"></td>\n";
			} elseif($b3=="checkbox"){
				if (strstr($box3[$i],'on')){print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box3[$i]\" checked></td>\n";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box3[$i]\"></td>\n";}
			}
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if (($box4!="-") &&($b4!="checkbox")){print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b4\" name=\"box4[$i]\" value=\"$box4[$i]\"></td>\n";}
			elseif($b4=="checkbox"){
				if (strstr($box4[$i],'on')){print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box4[$i]\" \"checked\"></td>\n";}
				else {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box4[$i]\"></td>\n";}
			}
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">";
			if ($box5!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b5\" name=\"box5[$i]\" value=\"$box5[$i]\"></td>\n";}
#			if ($box6!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b6\" name=\"box6[$i]\" value=\"$box6[$i]\"></td>\n";}
			if (($box6!="-")&&($b6!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b6\" name=\"box6[$i]\" value=\"$box6[$i]\"></td>\n";}
			elseif($b6=="checkbox"){
				if (strstr($box6[$i],'on')){print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box6[$i]\" checked></td>\n";}
				else {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box6[$i]\"></td>\n";}
			}
			if (($box7!="-")&&($b7!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b7\" name=\"box7[$i]\" value=\"$box7[$i]\"></td>\n";}
			elseif($b7=="checkbox"){
				if (strstr($box7[$i],'on')){print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box7[$i]\" checked></td>\n";}
				else {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box7[$i]\"></td>\n";}
			}
			if (($box8!="-")&&($b8!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b8\" name=\"box8[$i]\" value=\"$box8[$i]\"></td>\n";}
			elseif($b8=="checkbox"){
				if (strstr($box8[$i],'on')){print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box8[$i]\" checked></td>\n";}
				else {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box8[$i]\"></td>\n";}
			}
			if (($box9!="-")&&($b9!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b9\" name=\"box9[$i]\" value=\"$box9[$i]\"></td>\n";}
			elseif($b9=="checkbox"){
				if (strstr($box9[$i],'on')){print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box9[$i]\" checked></td>\n";}
				else {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box9[$i]\"></td>\n";}
			}
			if (($box10!="-")&&($b10!="checkbox")) {
				print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b10\" name=\"box10[$i]\" value=\"$box10[$i]\"></td>\n";
			}	elseif($b10=="checkbox") {
				if (strstr($box10[$i],'on')) {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=box10[$i] checked></td>\n";
				}	else {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box10[$i]\"></td>\n";
				}
			}
			if (($box11!="-")&&($b11!="checkbox")) {
				print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b11 name=\"box11[$i]\" value=\"$box11[$i]\"></td>\n";
			} elseif($b11=="checkbox") {
				if (strstr($box11[$i],'on')){
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box11[$i]\" checked></td>\n";
				}	else {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box11[$i]\"></td>\n";
				}
			}
			if (($box12!="-")&&($b12!="checkbox")) {
				print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b12 name=\"box12[$i]\" value=\"$box12[$i]\"></td>\n";
			} elseif($b12=="checkbox"){
				if (strstr($box12[$i],'on')) {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box12[$i]\" checked></td>\n";
				} else {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box12[$i]\"></td>\n";
				}
			}
			if (($box13!="-")&&($b13!="checkbox")) {
				print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b13 name=\"box13[$i]\" value=\"$box13[$i]\"></td>\n";
			} elseif($b13=="checkbox"){
				if (strstr($box13[$i],'on')){
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box13[$i]\" checked></td>\n";
				}	else {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box13[$i]\"></td>\n";
				}
			}
			if (($box14!="-")&&($b14!="checkbox")) {
				print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b14 name=\"box14[$i]\" value=\"$box14[$i]\"></td>\n";
			} elseif($b14=="checkbox") {
				if (strstr($box14[$i],'on')){
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box14[$i]\" checked></td>\n";
				} else {
					print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box14[$i]\"></td>\n";
				}
			}
			print "</tr>\n";
			print "<input type = \"hidden\" name=id[$i] value='$id[$i]'><input type = \"hidden\" name=\"art[$i]\" value=\"$art[$i]\"><input type = \"hidden\" name=\"kode[$i]\" value=\"$k\">\n";
		}
	}
	if (($k!='R')||(!$momsrapport)) {
		$y++;
		if (!isset($art[$y])) $art[$y]=NULL; 
		print "<tr>";
		print "<td>$k</td>\n";
		print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:$size\" name=\"kodenr[$y]\"></td>\n";
		print "<td><input class=\"inputbox\" type=\"text\" size=\"40\" name=\"beskrivelse[$y]\"></td>\n";
		if (($box1!="-")&&($b1!="checkbox")) {
				if ($art[$y]=='VRG') {
					print "<td title=\"zz ".titletxt($art[$y],'box1')."\"><SELECT NAME=box1[$i] style=\"width: 4em\">";
					print "<option value=\"amount\">kr</option>";
					print "<option value=\"percent\">%</option>";
					print "</SELECT></td>\n";
				} elseif ($art[$y]=='AFD') {
					print "<td></td>\n";
				} else {
					print "<td title=\"".titletxt($art[$y],'box1')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b1\" name=\"box1[$y]\"></td>\n";
				}
#			print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b1 name=box1[$y]></td>\n";
		} elseif($b1=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box1[$y]\"></td>\n";}
		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type = \"hidden\" name=\"art[$y]\" value=\"$a\">\n";
		if (($box2!="-")&&($b2!="checkbox")) {print "<td title=\"".titletxt($art[$y],'box2')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b2\" name=\"box2[$y]\"></td>\n";}
		elseif($b2=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box2[$y]\"></td>\n";}
		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type = \"hidden\" name=\"art[$y]\" value=\"$a\">\n";
		if (($box3!="-")&&($b3!="checkbox")) {
			($art[$y]=='DG' || $art[$y]=='KG')?$readonly='readonly="readonly"':$readonly=NULL;
			print "<td title=\"".titletxt($art[$y],'box3')."\"><input class=\"inputbox\" $readonly type=\"text\" style=\"text-align:right\" size=\"$b3\" name=\"box3[$y]\"></td>\n";
		}	elseif($b3=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box3[$y]\"></td>\n";}
		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type = \"hidden\" name=\"art[$y]\" value=\"$a\">\n";
		if (($box4!="-")&&($b4!="checkbox")) {print "<td title=\"".titletxt($art[$y],'box4')."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"$b4\" name=\"box4[$y]\"></td>\n";}
		elseif($b4=="checkbox") {print "<td><input class=\"inputbox\" type=\"checkbox\" name=\"box4[$y]\"></td>\n";}
		print "<input type = hidden name=\"id[$y]\" value='0'><input type = hidden name=\"kode[$y]\" value='$k'><input type = hidden name=\"art[$y]\" value=\"$a\">\n";
		if ($box5!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b5 name=\"box5[$y]\"></td>\n";}
#		if ($box6!="-") {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b6 name=\"box6[$y]\"></td>\n";}
		if (($box6!="-")&&($b6!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b6 name=\"box6[$y]\"></td>\n";}
		elseif($b6=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box6[$y]\"></td>\n";}
		if (($box7!="-")&&($b7!="checkbox")) {print "<td><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b7 name=\"box7[$y]\"></td>\n";}
		elseif($b7=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box7[$y]\"></td>\n";}
		if (($box8!="-")&&($b8!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b8 name=\"box8[$y]\"></td>\n";}
		elseif($b8=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box8[$y]\"></td>\n";}
		if (($box9!="-")&&($b9!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b9 name=\"box9[$y]\"></td>\n";}
		elseif($b9=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box9[$y]\"></td>\n";}
		if (($box10!="-")&&($b10!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b10 name=\"box10[$y]\"></td>\n";}
		elseif($b10=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box10[$y]\"></td>\n";}
		if (($box11!="-")&&($b11!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b11 name=\"box11[$y]\"></td>\n";}
		elseif($b11=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box11[$y]\"></td>\n";}
		if (($box12!="-")&&($b12!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b12 name=\"box12[$y]\"></td>\n";}
		elseif($b12=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box12[$y]\"></td>\n";}
		if (($box13!="-")&&($b13!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b13 name=\"box13[$y]\"></td>\n";}
		elseif($b13=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box13[$y]\"></td>\n";}
		if (($box14!="-")&&($b14!="checkbox")) {print "<td align=\"center\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=$b14 name=\"box14[$y]\"></td>\n";}
		elseif($b14=="checkbox") {print "<td align=\"center\"><input class=\"inputbox\" type=\"checkbox\" name=\"box14[$y]\"></td>\n";}

		print "<input type = \"hidden\" name=\"id[$y]\" value='0'><input type = \"hidden\" name=\"kode[$y]\" value='$k'><input type =\"hidden\" name=\"art[$y]\" value=\"$a\">\n";
		print "</tr>\n";
	}
	print "<!-- skriv_formtabel.php end -->\n";
	return $y;
}
