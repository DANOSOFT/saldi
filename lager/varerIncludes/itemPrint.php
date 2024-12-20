<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- lager/varerIncludes/itemPrint.php --- lap 4.0.7 --- 2023-02-18 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2023 saldi.dk ApS
// ----------------------------------------------------------------------
 
function itemPrint($start, $slut, $sort, $udskriv, $udvalg) {

global $alle_varer;
global $b_startstjerne,$b_slutstjerne,$b_strlen,$beholdning,$beskrivelse,$bestilt,$bgcolor,$bgcolor5,$brugernavn;
global $charset,$csv,$csvfil;
global $lagerantal,$lev_kto,$lev_navn;
global $forslag;
global $href_vnr;
global $i_forslag,$i_ordre,$i_tilbud,$itemGroup;
global $jsvars;
global $makeSuggestion;
global $popup;
global $showTrademark,$stock;
global $v_startstjerne,$v_slutstjerne,$v_strlen,$varenummer,$vatOnItemCard,$vatRate,$vis_kostpriser,$vis_lukkede,$vis_K,$vis_lev,$vis_VG;

if (!isset($bestilt))   $bestilt   = array();
if (!isset($i_tilbud))  $i_tilbud  = array();
if (!isset($i_ordre))   $i_ordre   = array();
if (!isset($i_forslag)) $i_forslag = array();

$it_ordrenr=$io_ordrenr=$if_ordrenr=$b_ordrenr=array();

$color=$gb=NULL;
if (!isset ($tmp)) $tmp = NULL;
if (!isset ($varenr)) $varenr = NULL;
if (!isset ($lukket)) $lukket = NULL;
if (!isset ($id)) $id = NULL;
if (!isset ($enhed)) $enhed = NULL;
if (!isset ($notes)) $notes = NULL;
if (!isset ($description)) $description = NULL;
if (!isset ($gruppe)) $gruppe = NULL;
if (!isset ($vatPrice)) $vatPrice = NULL;

$tidspkt=time();

$z=0;$z1=0;
$varer_i_ordre=array();
$linjebg=NULL;
/*
$qtxt="select * from grupper where art='VV' and box1='$brugernavn'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$vis_VG=explode(",",$r['box2']);
	if ($r['box3']) $vis_K=explode(",",$r['box3']);
	else $vis_VG[0]=1;
	list($vis_lukkede,$vis_lev_felt,$vis_kostpriser,$href_vnr,$tmp,$showTrademark) = array_pad(explode(chr(9),$r['box4'],6), 6, null);
} else {
	$qtxt="insert into grupper (beskrivelse, art, box1, box2, box3, box4) values ('varevisning', 'VV', '$brugernavn', 'on', 'on', 'on')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
}
*/
if ($vis_lukkede!='on') {
	$udvalg=$udvalg. " and lukket != '1'"; 
}

if (!$vis_VG[0]) {
	if ($vis_VG[1]) {
		$udvalg=$udvalg. " and (gruppe = '$vis_VG[1]'";
		$x=2; 
		if (!isset ($vis_VG[$x])) $vis_VG[$x] = NULL;
		while ($vis_VG[$x]) {
			$udvalg=$udvalg. " or gruppe = '$vis_VG[$x]'";
			$x++;
		}
		$udvalg=$udvalg. ")";
	} else $udvalg=$udvalg. " and gruppe = ''";
}

if ($lev_kto || $lev_navn) {
	$x=1;
	if ($lev_kto) $qtxt="select id from adresser where art='K' and kontonr = '$lev_kto'";
	else {
		$tmp=str_replace("*","%",$lev_navn);
		$qtxt="select id from adresser where art='K' and ";
		$qtxt.="(firmanavn like '$tmp' or lower(firmanavn) like '".strtolower($tmp)."' or ";
		$qtxt.="upper(firmanavn) like '".strtoupper($tmp)."')";
	}
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$vis_K[$x]=$r['id'];
		$x++;
	}
	if (isset($vis_K[1]) && $vis_K[1]) $vis_K[0]=NULL;
}

if (!$vis_K[0]) {	
	$lev_vare_liste=array();	
	$x=1; 
	if (isset($vis_K[1])) {
		$tmp="where lev_id = '$vis_K[1]'";
		$x=2;
		while (isset($vis_K[$x])) {
			$tmp=$tmp." or lev_id = '$vis_K[$x]'"; 
			$x++;
		}	
	}  
	$y=0;
	$qtxt="select distinct vare_id from vare_lev $tmp";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$y++;
		$lev_vare_liste[$y]=$r['vare_id'];
	}
}
	$x=0;
	$lagergrupper=array();
	$q=db_select("select * from grupper where art='VG' and box8='on'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){ 
		$x++;
		$lagergrupper[$x]=$r['kodenr'];
#cho "$lagergrupper[$x]<br>";
	}

if (($stock||$makeSuggestion)&&!$udskriv) $varer_i_ordre=find_varer_i_ordre(); 
if (!$slut) $slut=$start+50; 
if ($beskrivelse||$varenummer||$makeSuggestion) $slut=999999;
$v=0;
$qtxt="select * from varer where id > 0 $udvalg order by $sort";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$id[$v]=$r['id'];
	$varenr[$v]=$r['varenr'];
	$enhed[$v]=$r['enhed'];
	$description[$v]=$r['beskrivelse'];
	$tradeMark[$v]=$r['trademark'];
	$salgspris[$v]=$r['salgspris'];
	$kostpris[$v]=$r['kostpris'];
	$beholdning[$v]=$r['beholdning'];
	$min_lager[$v]=$r['min_lager'];
	$max_lager[$v]=$r['max_lager'];
	$gruppe[$v]=$r['gruppe'];
	$notes[$v]=$r['notes'];
	$lukket[$v]=$r['lukket'];
	$vatPrice[$v]=$salgspris[$v];
	$v++;
}

for ($v=0;$v<count($varenr);$v++) {
	$z++;	# $z bruges som taeller til at kontrollere hvor mange linjer der indgaar i listen.
	$vis1=1;
	$vis2=1;
	if ($udskriv && $makeSuggestion && !$alle_varer) {
		if (isset($forslag[$v])) {
			$vis1=1; $vis2=1;
		} else $vis1=0;
	}
	// Her frasorteres varer som ikke kommer fra den valgte lev.	
	if ((isset($vis_K[1]) && $vis1==1 && isset($lev_vare_liste) && in_array($id[$v],$lev_vare_liste)) || $vis_K[0]); #gor intet
	elseif (!isset($vis_K[1]) && $vis1==1 && isset($lev_vare_liste) && !in_array($id[$v],$lev_vare_liste)); #gor intet
	elseif(!$makeSuggestion) {$vis1=0; $z--;}
	if ((isset($vis_K[1]) && $vis2==1 && isset($lev_vare_liste) && in_array($id[$v],$lev_vare_liste)) || $vis_K[0]); #gor intet
	elseif (!isset($vis_K[1]) && $vis2==1 && isset($lev_vare_liste) && !in_array($id[$v],$lev_vare_liste)); #gor intet
	else $vis2=0;
	// Her frasorteres varer i bestillingsforslag som ikke lagerfoerte - skal staa nederst i frasortering.	
	if ($makeSuggestion && !in_array($gruppe[$v],$lagergrupper)) {$vis1=0;$vis2=0;}	
// frasortering slut	
	if ((($z>=$start&&$z<$slut)||$makeSuggestion)&&$vis1==1&&$vis2==1){
	$z1++;
	if ($udskriv) {
		$y=0;
			($linjebg!=$bgcolor)?$linjebg=$bgcolor:$linjebg=$bgcolor5;
			($lukket[$v]=='1')?$color='red':$color='black';
			print "<tr bgcolor=\"$linjebg\">";
			if ($popup) { #20170920
				$kort="kort".$id[$v];
				$js="onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:$kort=window.open('varekort.php?opener=varer.php&amp;id=$id[$v]&amp;returside=../includes/luk.php','".$jsvars."');$kort.focus();\"";
			} elseif ($href_vnr) $js=NULL; 
			else $js="onMouseOver=\"this.style.cursor = 'pointer'\"; onclick=\"javascript:location.href='varekort.php?id=$id[$v]'\"";
#			if ($popup) print "<td </td>";
#			else print "<td><a href=\"varekort.php?id=$id[$v]&amp;returside=varer.php\"><FONT style=\"COLOR:$color;\">".htmlentities(stripslashes($varenr),ENT_COMPAT,$charset)."</font></a></td>";	
			if ($csv) {
				fwrite($csvfil,"\"".utf8_decode($varenr[$v])."\";\"".utf8_decode($enhed[$v])."\";\"".utf8_decode($description[$v])."\";");
			} else {
				print "<td $js><FONT style=\"COLOR:$color;\">";
				if ($href_vnr) print "<a href='varekort.php?id=$id[$v]'>";
				print htmlentities(stripslashes($varenr[$v]),ENT_COMPAT,$charset);
				if ($href_vnr) print "</a>";
				print "</font></td>";
				print "<td $js><FONT style=\"color:$color\">".htmlentities(stripslashes($enhed[$v]),ENT_COMPAT,$charset)."</font><br></td>";
				print "<td $js title='".$notes[$v]."'><FONT style=\"color:$color\">";
				print htmlentities(stripslashes($description[$v]),ENT_COMPAT,$charset);
				print "</font><br></td>";
				if ($showTrademark) print "<td>".htmlentities(stripslashes($tradeMark[$v]),ENT_COMPAT,$charset)."</td>";
			}
			if (!$vis_lev){
				if ($lagerantal>1 && !$makeSuggestion) {
					$r2=db_fetch_array(db_select("select sum(beholdning) as lagersum from lagerstatus where vare_id = $id[$v]",__FILE__ . " linje " . __LINE__));
					$diff=$beholdning[$v]-$r2['lagersum'];
					for ($x=1;$x<=$lagerantal; $x++) {
						$qtxt="select id, lager,lok1,beholdning from lagerstatus where vare_id = $id[$v] and lager = $x";
						$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$y=$r2['beholdning'];
						$lok=trim(utf8_decode($r2['lok1']));
						if ($csv) fwrite($csvfil,"\"".dkdecimal($y,2)."\";\"$lok\";");
						else {
							print "<td align=center>";
							if (in_array($gruppe[$v],$lagergrupper)) {
								if ($y >= 1) print "<span title= 'Flyt til andet lager'><a href='lagerflyt.php?lager=$x&vare_id=$id[$v]'>".dkdecimal($y,2)."</a>";
								else print dkdecimal($y,2);
							}
							print "</td>";
						}
					}
				} elseif ($csv) { 
					for ($x=1;$x<=$lagerantal; $x++) fwrite($csvfil,"\"0\";");
				}
				if (in_array($gruppe[$v],$lagergrupper)) {
				if ($makeSuggestion || $stock)	{
					$tmp=find_beholdning($id[$v],$udskriv);
					(isset($tmp[1]))?$i_tilbud[$z]   = $tmp[1]:$i_tilbud[$z]   = 0;
					(isset($tmp[5]))?$it_ordrenr[$z] = $tmp[5]:$it_ordrenr[$z] = 0;
					(isset($tmp[2]))?$i_ordre[$z]    = $tmp[2]:$i_ordre[$z]    = 0;
					(isset($tmp[6]))?$io_ordrenr[$z] = $tmp[6]:$io_ordrenr[$z] = 0;
					(isset($tmp[3]))?$i_forslag[$z]  = $tmp[3]:$i_forslag[$z]  = 0;
					(isset($tmp[7]))?$if_ordrenr[$z] = $tmp[7]:$if_ordrenr[$z] = 0;
					(isset($tmp[4]))?$bestilt[$z]    = $tmp[4]:$bestilt[$z]    = 0;
					(isset($tmp[8]))?$b_ordrenr[$z]  = $tmp[8]:$b_ordrenr[$z]  = 0;
				}

				if ($csv) fwrite($csvfil,"\"".dkdecimal($beholdning[$v],2)."\";");
				else {
					if ($stock) {
						($it_ordrenr[$z])?$title="title=\"Tilbud: $it_ordrenr[$z]\"":$title="title=\"\"";
						print "<td align=\"right\" $title>$i_tilbud[$z]</td>";
						($io_ordrenr[$z])?$title="title=\"Ordre: $io_ordrenr[$z]\"":$title="title=\"\"";
						print "<td align=\"right\" $title>$i_ordre[$z]</td>";
						($b_ordrenr[$z])?$title="title=\"Ordre: $b_ordrenr[$z]\"":$title="title=\"\"";
						print "<td align=\"right\" $title>$bestilt[$z]</td>";
					}
					print "<td align=right>".dkdecimal($beholdning[$v],2)."</td>";
				}
				if ($makeSuggestion){
					$tmp=$beholdning[$v]-$i_ordre[$z];
					if ($min_lager[$v]*1>$tmp || $alle_varer) {
						$gb=$gb+1;
						$genbestil[$z]=$max_lager[$v]-$beholdning[$v]+$i_ordre[$z];
						if ($genbestil[$z] < 0) $genbestil[$z]=0;	
						print "<td align=right><input class=\"inputbox\" type=\"text\" style=\"text-align:right;width:60px\" name=\"gb_antal_$gb\" value=\"$genbestil[$z]\"></td>";
						print "<input type=\"hidden\" name=\"gb_id_$gb\" value=\"$id[$v]\">";
						print "<input type=\"hidden\" name=\"genbestil_ant\" value=\"$gb\">";
					} else print "<td></td>";
				}
			} else print "<td></td>";
			} else print "<td></td>";
			if ($vatOnItemCard) {
				for($x=0;$x<count($itemGroup);$x++){
					if ($gruppe[$v]==$itemGroup[$x]) $vatPrice[$v]=$salgspris[$v]+=$salgspris[$v]/100*$vatRate[$x];
				}
			}
			if (!$makeSuggestion) {
#				$salgspris[$v]=dkdecimal($salgspris[$v]*(100+$incl_moms)/100,2);
				if ($csv) fwrite($csvfil,"\"".dkdecimal($kostpris[$v],2)."\";\"".dkdecimal($vatPrice[$v],2)."\"\n");
				else {
					print "<td align=right>".dkdecimal($vatPrice[$v],2)."<br></td>";
					if ($vis_kostpriser) print "<td align=right>".dkdecimal($kostpris[$v],2)."<br></td>";
				}
			}
			if ($vis_lev=='on') {
				$qtxt="select kostpris, lev_id, lev_varenr from vare_lev where vare_id = $id[$v] order by posnr";
				$query2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$row2 = db_fetch_array($query2);
				if ($row2['lev_id']) {
					$lev_varenr=$row2['lev_varenr'];
					$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]",__FILE__ . " linje " . __LINE__);
					$levrow = db_fetch_array($levquery);
					$kostpris=dkdecimal($row2['kostpris'],2);
				}
				elseif ($row['samlevare']=='on') {$kostpris=dkdecimal($row['kostpris'],2);}
				if (!$csv) print "<td align=right>$kostpris</td>";
				$query2 = db_select("select box8 from grupper where art='VG' and kodenr='$row[gruppe]'",__FILE__ . " linje " . __LINE__);
				$row2 =db_fetch_array($query2);
				if (($row2['box8']=='on')||($row['samlevare']=='on')){
					$ordre_id=array();
					$x=0;
					$query2 = db_select("select id from ordrer where status >= 1 and status < 3 and art = 'DO'",__FILE__ . " linje " . __LINE__);
					while ($row2 =db_fetch_array($query2)){
						$x++;
						$ordre_id[$x]=$row2['id'];
					}
					$x=0;
					$query2 = db_select("select id, ordre_id, antal from ordrelinjer where vare_id = $id[$v]",__FILE__ . " linje " . __LINE__);
					while ($row2 =db_fetch_array($query2)) {
						if (in_array($row2['ordre_id'],$ordre_id)) {
							$x=$x+$row2['antal'];	 
							$query3 = db_select("select antal from batch_salg where linje_id = $row2[id]",__FILE__ . " linje " . __LINE__);
							while ($row3=db_fetch_array($query3)) {$x=$x-$row3['antal'];}
						}
					}	
					$linjetext="<span title= 'Der er $x i ordre'>";
					if (!$csv) {
						print "<td align=right>$linjetext $beholdning[$v]</span></td>";		
						print "<td></td>";		
						print "<td>$levrow[kontonr] - ".htmlentities(stripslashes($levrow['firmanavn']),ENT_COMPAT,$charset)."</td>";
						print "<td>".htmlentities(stripslashes($lev_varenr),ENT_COMPAT,$charset)."</td>";
					}
				}
				elseif (!$csv) print "<td></td>";	 
			}
			if (!$csv) print "</tr>\n";
		} elseif ($makeSuggestion||$stock) {
			if (in_array($id[$v],$varer_i_ordre)) {
				$tmp=find_beholdning($id[$v],$udskriv);
					(isset($tmp[1]))?$i_tilbud[$z]   = $tmp[1]:$i_tilbud[$z]   = 0;
					(isset($tmp[5]))?$it_ordrenr[$z] = $tmp[5]:$it_ordrenr[$z] = 0;
					(isset($tmp[2]))?$i_ordre[$z]    = $tmp[2]:$i_ordre[$z]    = 0;
					(isset($tmp[6]))?$io_ordrenr[$z] = $tmp[6]:$io_ordrenr[$z] = 0;
					(isset($tmp[3]))?$i_forslag[$z]  = $tmp[3]:$i_forslag[$z]  = 0;
					(isset($tmp[7]))?$if_ordrenr[$z] = $tmp[7]:$if_ordrenr[$z] = 0;
					(isset($tmp[4]))?$bestilt[$z]    = $tmp[4]:$bestilt[$z]    = 0;
					(isset($tmp[8]))?$b_ordrenr[$z]  = $tmp[8]:$b_ordrenr[$z]  = 0;
			} else {
				$i_tilbud[$z]=0;
				$i_ordre[$z]=0;
				$i_forslag[$z]=0;
				$bestilt[$z]=0;
			}
			if (!$min_lager[$v])  $min_lager[$v]  = 0;
			if (!$beholdning[$v]) $beholdning[$v] = 0;
			if ($min_lager[$v]*1>($beholdning[$v]-$i_ordre[$z]+$i_forslag[$z]+$bestilt[$z])) {
			
				$genbestil[$z]=$max_lager[$v]-$beholdning[$v]+$i_ordre[$z]-($i_forslag[$z]+$bestilt[$z]);
				if ($makeSuggestion) {
					$forslag[$v]=$id[$v];
				}
			}
		}
	} elseif ($udskriv && $z>=$slut && !$makeSuggestion) break;
	if ($z>=$slut) {
		break;
	}
	if (time()-$tidspkt>120) { #20190901
		print "<BODY onLoad=\"javascript:alert('Timeout - reducer linjeantal')\">";
		break;
	}
}
return($z);
}# endfunc itemPrint
?>
