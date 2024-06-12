<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- systemdata/syssetupIncludes/saveData.php ---patch 4.1.0 ----2024-04-07 ---
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// -----------------------------------------------------------

if ($_POST){
	$id=if_isset($_POST['id']);
	$beskrivelse=if_isset($_POST['beskrivelse']);
	$kodenr=if_isset($_POST['kodenr']);
	$kode=if_isset($_POST['kode']);
	$art=if_isset($_POST['art']);
	$box1=if_isset($_POST['box1']);
	$box2=if_isset($_POST['box2']);
	$box3=if_isset($_POST['box3']);
	$box4=if_isset($_POST['box4']);
	$box5=if_isset($_POST['box5']);
	$box6=if_isset($_POST['box6']);
	$box7=if_isset($_POST['box7']);
	$box8=if_isset($_POST['box8']);
	$box9=if_isset($_POST['box9']);
	$box10=if_isset($_POST['box10']);
	$box11=if_isset($_POST['box11']);
	$box12=if_isset($_POST['box12']);
	$box13=if_isset($_POST['box13']);
	$box14=if_isset($_POST['box14']);
	$antal=if_isset($_POST['antal']);
#cho "Antal $antal<br>";
	$valg=if_isset($_POST['valg']);

	$s_art=array();
	$artantal=0;
#	transaktion('begin');
	$y=0;
	for($x=0; $x<=$antal; $x++) {
		$set=0;
		if (!isset ($box6[$x])) $box6[$x] = null;
		if (!isset ($box7[$x])) $box7[$x] = null;
		if (!isset ($box8[$x])) $box8[$x] = null;
		if (!isset ($box9[$x])) $box9[$x] = null;
		if (!isset ($box10[$x])) $box10[$x] = null;
		if (!isset ($box11[$x])) $box11[$x] = null;
		if (!isset ($box12[$x])) $box12[$x]= null;
		if (!isset ($box13[$x])) $box13[$x] = null;
		if (!isset ($box14[$x])) $box14[$x] = null;
		if (!isset ($box2[$x])) $box2[$x] = null;
		if (!isset ($box3[$x])) $box3[$x] = null;
		if (!isset ($box3[$y])) $box3[$y] = null;
		if (!isset ($box4[$x])) $box4[$x] = null;
		if (!isset ($box4[$y])) $box4[$y] = null;
		if (!isset ($box5[$x])) $box5[$x] = null;
		if (!isset ($box1[$x])) $box1[$x] = null;
		if (isset($art[$x])) $set=1;
		if (isset($beskrivelse[$x])) $set=1;
		if (isset($kodenr[$x])) $set=1;
		if (isset($id[$x])) $set=1;
		if ($set) {
			$id[$y]=(float)$id[$x];
			$kodenr[$y] = $kodenr[$x];
			if ($kodenr[$y] != '-') $kodenr[$y] = (int)$kodenr[$y];
			$kode[$y]=$kode[$x]; #20160118
			$beskrivelse[$y]=db_escape_string(trim($beskrivelse[$x]));
			$art[$y]=trim($art[$x]);
			$box1[$y]=trim($box1[$x]);
			$box2[$y]=trim($box2[$x]);
			$box3[$y]=trim($box3[$x]);
			$box4[$y]=trim($box4[$x]);
			$box5[$y]=trim($box5[$x]);
			$box6[$y]=trim($box6[$x]);
			$box7[$y]=trim($box7[$x]);
			$box8[$y]=trim($box8[$x]);
			$box9[$y]=trim($box9[$x]);
			$box10[$y]=trim($box10[$x]);
			$box11[$y]=trim($box11[$x]);
			$box12[$y]=trim($box12[$x]);
			$box13[$y]=trim($box13[$x]);
			$box14[$y]=trim($box14[$x]);
			$y++;
		}
	}
  transaktion('begin');
	for($x=0; $x<$y; $x++) {
	########## Til brug for sortering ########
		 if (($art[$x])&&(!in_array($art[$x],$s_art))) {
			$artantal++;
			$s_art[$artantal]=$art[$x];
			$s_kode[$artantal]=$kode[$x];
		}
		################################

		if (($art[$x]=='VG')&&($box8[$x]!='on')&&($box9[$x]=='on')) {
			$alerttext="Der kan kun f&oslash;res batchkontrol p&aring; lagerf&oslash;rte varer";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$box9[$x]='';
		}
		if ($art[$x]=='DG' || $art[$x]=='KG'){
			if (!$box3[$x]) $box3[$x]='DKK';
			if ($r=db_fetch_array(db_select("SELECT box2 FROM grupper where id='$id[$x]'",__FILE__ . " linje " . __LINE__))) {
			if($box2[$x] && $r['box2'] && $box2[$x]!=$r['box2']) {
				$genberegn=1;
				$gl_smlkto=$r['box2'] ;
				$z=0;
				$gruppesum=0;
				$qtxt="select id,kontonr from adresser where art = '".substr($art[$x],0,1)."' and gruppe='$kodenr[$x]'";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)){
					$adr_konto_id[$z]=$r['id'];
					$adr_kontonr[$z]=$r['kontonr'];
					$z++;
				}
				for ($z=0;$z<count($adr_konto_id);$z++){
					$qtxt="select amount,valutakurs from openpost where udlignet='0' and konto_id='$adr_konto_id[$z]'";
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while($r=db_fetch_array($q)){
						$gruppesum+=$r['amount']*100/$r['valutakurs'];
					}
				}
				$gruppesum=afrund($gruppesum,3);
				if ($gruppesum>0) $debkred='kredit';
				elseif($gruppesum<0)  $debkred='debet';
				$gruppesum=abs($gruppesum);
				if ($gruppesum){
					$posttekst="samlekonto D$kodenr[$x] flyttet fra konto $gl_smlkto til $box2[$x] af $brugernavn";
					$qtxt = "insert into transaktioner";
					$qtxt.= "(kontonr,bilag,transdate,logdate,logtime,beskrivelse,$debkred,";
					$qtxt.= "faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms) ";
					$qtxt.= "values ";
					$qtxt.= "('$gl_smlkto','0','".date("Y-m-d")."','".date("Y-m-d")."','".date("H:i")."','$posttekst','$gruppesum',";
					$qtxt.= "'0','0','0','0','','0','100','0','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					($debkred=='debet')?$debkred='kredit':$debkred='debet';
					$qtxt="insert into transaktioner";
					$qtxt.="(kontonr,bilag,transdate,logdate,logtime,beskrivelse,$debkred,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs,ordre_id,moms)";
					$qtxt.="values";
					$qtxt.="('$box2[$x]','0','".date("Y-m-d")."','".date("Y-m-d")."','".date("H:i")."','$posttekst','$gruppesum','0','0','0','0','','0','100','0','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				$qtxt="select valuta from kontoplan where regnskabsaar = '$regnaar' and kontonr='$box2[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($valutakode=$r['valuta']) {
					$qtxt="select box1 from grupper where art='VK' and kodenr='$valutakode'";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$valuta=$r['box1'];
					if (!$valuta) {
						$alerttext="Valuta $valuta eksisterer ikke";
						print tekstboks($alerttext);
						break 1;

					}
				}	else $valuta='DKK';
				$box3[$x]=$valuta;
			}}
			if ($art[$x]=='DG'&& $box6[$x]) $box6[$x]=usdecimal($box6[$x]);
			if ($art[$x]=='DG'&& $box6[$x]) $box7[$x]=usdecimal($box7[$x]);
		}
		if ($art[$x]=='VG' && $box8[$x]=='on' && $box10[$x]=='on') {
			$alerttext="Operationer kan ikke lagerf&oslash;res";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
			$box8[$x]=''; $box9[$x]='';
		}
		if ($art[$x]=='VPG') {
			list($box1[$x],
			$box2[$x],
			$box3[$x],
			$box4[$x])=explode(";", opdater_varer(
			$kodenr[$x],
			$art[$x],
			$box1[$x],
			$box2[$x],
			$box3[$x],
			$box4[$x]));
		}
		if ($art[$x]=='VTG') {
			list($box1[$x],
			$box2[$x],
			$box3[$x],
			$box4[$x])=explode(";", opdater_varer(
			$kodenr[$x],
			$art[$x],
			$box1[$x],
			$box2[$x],
			$box3[$x],
			$box4[$x]));
		}
		if ($art[$x]=='VRG') opdater_varer($kodenr[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x]);
		if (($art[$x]=='SM')||($art[$x]=='KM')||($art[$x]=='YM')||($art[$x]=='EM')||($art[$x]=='VK')) $box2[$x]=usdecimal($box2[$x]);
		if ($art[$x]=='VK' ) $box3[$x]=usdate($box3[$x]);
#		if ($art[$x]=='PRJ' ) $kodenr[$x]=$kodenr[$x]*1;
		if (!$fejl && ($kode[$x])||($id[$x])) {
			include_once('syssetupIncludes/functionCheckData.php');
			$fejl=tjek ($id [$x],$beskrivelse[$x],$kodenr[$x],$kode[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x],$box5[$x],$box6[$x],$box7[$x],$box8[$x],$box9[$x]);
			if (!$fejl && ($id[$x]==0)&&($kode[$x])&&($kodenr[$x])&&($art[$x])) {
				$qtxt = "SELECT id FROM grupper WHERE ";
				$qtxt.= "kodenr = '$kodenr[$x]' and kode = '$kode[$x]' and art = '$art[$x]' and fiscal_year = '$regnaar'";
				$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {
					$alerttxt = NULL;
					if ($art[$x]=='SM') $alerttxt = "Der findes allerede en salgsmomskonto med nr: $kodenr[$x]";
					elseif ($art[$x]=='KM') $alerttxt = "Der findes allerede en k&oslash;bssmomskonto med nr: $kodenr[$x]";
					elseif ($art[$x]=='YM') {
						$alerttxt = "Der findes allerede en konto til moms af ydelsesk&oslash;b i udlandet med nr: $kodenr[$x]";
					} elseif ($art[$x]=='EM') {
						$alerttxt = "Der findes allerede en konto til moms af varek&oslash; i udlandet med nr: $kodenr[$x]";
					} elseif ($art[$x]=='SD') $alerttxt = "Der findes allerede en debitor-samlekonto nr: $kodenr[$x]";
					elseif ($art[$x]=='KD') $alerttxt = "Der findes allerede en kreditor-samlekonto nr: $kodenr[$x]";
					if ($alerttxt) {
						print "<big><b>$alerttxt</b></big>";
						$nopdat=1;
					}
				}
				elseif ($art[$x]=='RA'){
					include_once('syssetupIncludes/functionNewYear');
					nytaar($beskrivelse[$x],$kodenr[$x],$kode[$x],$art[$x],$box1[$x],$box2[$x],$box3[$x],$box4[$x],$box5[$x],$box6[$x]);
				} elseif ($art[$x]!='PV') {
					$qtxt = "insert into grupper ";
					$qtxt.= "(beskrivelse,kodenr,kode,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14,fiscal_year) ";
					$qtxt.= "values ";
					$qtxt.= "('$beskrivelse[$x]','$kodenr[$x]','$kode[$x]','$art[$x]','$box1[$x]','$box2[$x]','$box3[$x]','$box4[$x]',";
					$qtxt.= "'$box5[$x]','$box6[$x]','$box7[$x]','$box8[$x]','$box9[$x]','$box10[$x]','$box11[$x]','$box12[$x]',";
					$qtxt.= "'$box13[$x]','$box14[$x]','$regnaar')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					if ($art[$x]=='LG'){
						if (!db_fetch_array(db_select("SELECT * FROM lagerstatus",__FILE__ . " linje " . __LINE__))) {
							$qtxt = "SELECT id,beholdning FROM varer WHERE beholdning !='0' order by id";
							$q1=db_select($qtxt,__FILE__ . " linje " . __LINE__);
							while ($r1=db_fetch_array($q1)) {
								$qtxt = "insert into lagerstatus (beholdning,vare_id,lager) values ('$r1[beholdning]','$r1[id]','0')";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							}
						}
					}
				}
			}
			elseif ((($id[$x]>0)&&($kodenr[$x])&&($kodenr[$x]!='-'))&&($art[$x])) { # &&(($box1[$x])||($box3[$x])||($art[$x]=='VK')))
			  if ($art[$x]=='PV') {db_modify("update grupper set box1 = '$box1[$x]',box2 = '$box2[$x]',box3 = '$box3[$x]' WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);}
				else {
					$qtxt = "update grupper set beskrivelse = '$beskrivelse[$x]',kode = '$kode[$x]',box1 = '$box1[$x]',box2 = '$box2[$x]',box3 = '$box3[$x]',box4 = '$box4[$x]',box5 = '$box5[$x]',box6 = '$box6[$x]',box7 = '$box7[$x]',box8 = '$box8[$x]',box9 = '$box9[$x]',box10 = '$box10[$x]',box11 = '$box11[$x]',box12 = '$box12[$x]',box13 = '$box13[$x]',box14 = '$box14[$x]' WHERE id = '$id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
				if ($art[$x]=='VK') { #ValutaKoder
				if ($r=db_fetch_array(db_select("select id,kurs from valuta where valdate = '$box3[$x]' and gruppe =	'$kodenr[$x]'",__FILE__ . " linje " . __LINE__))) {
						if ($r['kurs'] != $box2[$x]) db_modify("update valuta set kurs = '$box2[$x]' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
					} else db_modify("insert into valuta(gruppe,valdate,kurs) values ('$kodenr[$x]','$box3[$x]','$box2[$x]')",__FILE__ . " linje " . __LINE__);
				}
			} elseif ($id[$x]>0 && $kodenr[$x]=="-" && $art[$x]!='PV') {
			if ($art[$x]=='VPG') {
					if ($box1[$x]) db_modify("update varer set kostpris = $box1[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($box2[$x]) db_modify("update varer set salgspris = $box2[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($box3[$x]) db_modify("update varer set retail_price = $box3[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
					if ($box4[$x]) db_modify("update varer set tier_price = $box4[$x] WHERE prisgruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
				}
				if ($art[$x]=='LG') { #LagerGrupper
					$r1=db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE id=$id[$x]",__FILE__ . " linje " . __LINE__));
					$q2=db_select("SELECT beholdning,vare_id FROM lagerstatus WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
						$b2=$r2['beholdning']*1; # 20170405
						if ($r3=db_fetch_array(db_select("SELECT * FROM lagerstatus WHERE lager = '0' and vare_id = '$r2[vare_id]'",__FILE__ . " linje " . __LINE__))) {
							$b3=$r3['beholdning']*1; # 20170405
							db_modify("update lagerstatus set beholdning = $b3+$b2 WHERE id = $r3[id]",__FILE__ . " linje " . __LINE__);
						} elseif($b2) {
						db_modify("insert into lagerstatus (beholdning,vare_id,lager) values ('$b2','$r2[vare_id]','0')",__FILE__ . " linje " . __LINE__);
						}
					}
					db_modify("delete FROM lagerstatus WHERE lager = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					db_modify("update batch_kob set lager = 0 WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					db_modify("delete FROM grupper WHERE id = '$id[$x]'");
					$q1=db_select("SELECT kodenr FROM grupper WHERE art='LG' and kodenr > '$r1[kodenr]' order by kodenr",__FILE__ . " linje " . __LINE__);
					while ($r1=db_fetch_array($q1)) {
						db_modify("update lagerstatus set lager = $r1[kodenr]-1 WHERE lager = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
						db_modify("update batch_kob set lager = $r1[kodenr]-1 WHERE lager =  '$r1[kodenr]'",__FILE__ . " linje " . __LINE__);
					}
					if (!db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE art='LG'"))) db_modify("delete FROM lagerstatus",__FILE__ . " linje " . __LINE__);
				} elseif ($art[$x]=='SM'||$art[$x]=='KM'||$art[$x]=='YM'||$art[$x]=='EM') {
					$qtxt = "SELECT kodenr FROM grupper WHERE id=$id[$x]<br>";
					$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$tmp=substr($art[$x],0,1).$r1['kodenr'];
					if ($r1=db_fetch_array(db_select("SELECT id FROM kontoplan WHERE moms='$tmp' and regnskabsaar = $regnaar",__FILE__ . " linje " . __LINE__))) print "<BODY onLoad=\"javascript:alert('Der er referencer til $tmp i kontoplanen. $tmp ikke slettet!')\">";
					elseif ($r1=db_fetch_array(db_select("SELECT id FROM grupper WHERE (art='DG' or art = 'KG') and box1='$tmp' and fiscal_year = '$regnaar'",__FILE__ . " linje " . __LINE__))) print "<BODY onLoad=\"javascript:alert('Der er reference til $tmp i debitor-/kreditorgrupper. $tmp ikke slettet!')\">";
					else db_modify("delete FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				} elseif ($art[$x]=='VK') db_modify("delete FROM valuta WHERE gruppe = '$kodenr[$x]'",__FILE__ . " linje " . __LINE__);
				else {
					$r1=db_fetch_array(db_select("SELECT kodenr FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__));
					if ($art[$x]=='VG' && db_fetch_array(db_select("SELECT id FROM varer WHERE gruppe = '$r1[kodenr]'",__FILE__ . " linje " . __LINE__))) {
							print "<BODY onLoad=\"javascript:alert('Der er varer i varegruppe $r1[kodenr] - varegruppe ikke slettet!')\">";
					} else db_modify("delete FROM grupper WHERE id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	transaktion('commit');
	if ($genberegn) genberegn($regnaar);
}
?>
