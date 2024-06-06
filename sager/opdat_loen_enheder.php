<?php
function opdat_loen_enheder ($id) {
	global $db_encode;
	global $brugernavn;
	global $sag_rettigheder;
	global $overtid_50pct;
	global $overtid_100pct;
	
	$afvis=NULL;$ansatte=NULL;$afsluttet=NULL;
	$beskyttet=NULL;
	$fordeling=NULL;$fratraek=array();
	$oprettet=NULL;
	$t50pct=NULL;$t100pct=NULL;$timer=NULL;
 
	if ($luk=if_isset($_POST['luk'])) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
		exit;
	}	
	transaktion('begin');
	if ($gem=if_isset($_POST['gem'])|| $afslut=if_isset($_POST['afslut']) || $afvis=if_isset($_POST['afvis'])) {
		$id=if_isset($_POST['id']);
		$listevalg=if_isset($_POST['listevalg'])*1;
		$nyt_listevalg=if_isset($_POST['nyt_listevalg'])*1;
		$op=if_isset($_POST['op']);
		$ned=if_isset($_POST['ned']);
		$op_25=if_isset($_POST['op_25']);
		$ned_25=if_isset($_POST['ned_25']);
		$op_40=if_isset($_POST['op_40']);
		$ned_40=if_isset($_POST['ned_40']);
		$op_60=if_isset($_POST['op_60']);
		$ned_60=if_isset($_POST['ned_60']);
		$op_30m=if_isset($_POST['op_30m']);
		$ned_30m=if_isset($_POST['ned_30m']);
		$pris_op=if_isset($_POST['pris_op']);
		$pris_ned=if_isset($_POST['pris_ned']);
		$vare_id=if_isset($_POST['vare_id']);
		$vare_nr=if_isset($_POST['varenr']); // indsat 20142803
		$vare_tekst=if_isset($_POST['vare_tekst']);
		$tr_id=if_isset($_POST['tr_id']);
		$tr_antal=if_isset($_POST['tr_antal']);
		$tr_pris=if_isset($_POST['tr_pris']);
		$telt_id=if_isset($_POST['telt_id']);
		$telt_antal=if_isset($_POST['telt_antal']);
		$telt_pris=if_isset($_POST['telt_pris']);
		$enhed_id=if_isset($_POST['enhed_id']);
		$loen_nr=if_isset($_POST['loen_nr']);
		$loen_art=if_isset($_POST['loen_art']);
		$loen_tekst=db_escape_string(if_isset($_POST['loen_tekst']));
		$loen_ansatte=if_isset($_POST['ansatte']);
		$loen_date=if_isset($_POST['loen_date']);
		$loen_fordeling=if_isset($_POST['loen_fordeling']);
		$loen_timer=if_isset($_POST['loen_timer']);
		$loen_50pct=if_isset($_POST['loen_50pct']);
		$loen_100pct=if_isset($_POST['loen_100pct']);
		$loen_loen=if_isset($_POST['loen_loen']);
		$skur1=if_isset($_POST['skur1']);
		$skur2=if_isset($_POST['skur2']);
		$skur_sats1=if_isset($_POST['skur_sats1']);
		$skur_sats2=if_isset($_POST['skur_sats2']);
		$loen_km=if_isset($_POST['loen_km']);
		$km_sats=if_isset($_POST['km_sats']);
		$km_fra=if_isset($_POST['km_fra']);
		$hvem=db_escape_string(if_isset($_POST['hvem']));
		$sag_nr=if_isset($_POST['sag_nr'])*1;
		$sag_id=if_isset($_POST['sag_id'])*1;
		$sag_ref=if_isset($_POST['sag_ref']);
		$opg_nr=if_isset($_POST['opg_nr'])*1;
		$gl_opg_id=if_isset($_POST['gl_opg_id'])*1;
		$opg_id=if_isset($_POST['opg_id'])*1;
		$loendato=if_isset($_POST['loendato']);
		$loendate=usdate($loendato);
		$oprettet=if_isset($_POST['oprettet']);
		$oprettet_af=if_isset($_POST['oprettet_af']);
		$afsluttet=if_isset($_POST['afsluttet']);
		$godkendt=if_isset($_POST['godkendt']);
		$godkendt_af=if_isset($_POST['godkendt_af']);
		$afvist=if_isset($_POST['afvist']);
		$afvist_af=if_isset($_POST['afvist_af']);
		$afvist_pga=if_isset($_POST['afvist_pga']);
#		$tilbagefoer=if_isset($_POST['tilbagefoer']);
		$loen_id=if_isset($_POST['loen_id']);
		$ansat_id=if_isset($_POST['ansat_id']);
		$medarb_nr=if_isset($_POST['medarb_nr']);
		$medarb_navn=if_isset($_POST['medarb_navn']);
		$sum=if_isset($_POST['sum'])*1;
		$dksum=if_isset($_POST['dksum']);
		$a_id=if_isset($_POST['a_id']);
		$a_stk=if_isset($_POST['a_stk']);
		$a_txt=if_isset($_POST['a_txt']);
		$a_pris=if_isset($_POST['a_pris']);
		$a_pct=if_isset($_POST['a_pct']);
		$feriefra=if_isset($_POST['feriefra']); // indsat 20140627
		$ferietil=if_isset($_POST['ferietil']); // indsat 20140627
		if ($opg_id && !$opg_nr) {
			$r=db_fetch_array(db_select("select nr from opgaver where id = '$opg_id'",__FILE__ . " linje " . __LINE__));
			$opg_nr=$r['nr']*1;
		}
		if (($loen_art=='akk_afr' || $loen_art=='akkord')) {
			$akksum=0;
			$tr_antal=str_replace(",",".",$tr_antal)*1;
			$telt_antal=str_replace(",",".",$telt_antal)*1;
			if ($tr_id) {
				db_modify("update loen_enheder set op='$tr_antal',pris_op='$tr_pris' where id='$tr_id'",__FILE__ . " linje " . __LINE__);
			} elseif ($tr_antal) {
				$tmp=$listevalg."|Transport"; 
				db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','-1','$tr_antal','0','0','0','0','0','0','0','0','0','$tr_pris','0','$tmp','0')",__FILE__ . " linje " . __LINE__);
			}
#cho "kategori $listevalg<br>";
			if ($listevalg=='7') {
				if ($telt_id && $telt_antal) { 
					db_modify("update loen_enheder set op='$telt_antal',pris_op='$telt_pris' where id='$telt_id'",__FILE__ . " linje " . __LINE__);
				} elseif ($telt_antal && $telt_pris) { #20150602
					$tmp=$listevalg."|Telt";
					db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ('$id','-2','$telt_antal','0','0','0','0','0','0','0','0','0','$telt_pris','0','$tmp','0')",__FILE__ . " linje " . __LINE__);
				}
			}
			$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
			$y=0;
			while ($r=db_fetch_array($q)) {
				$cat_id[$y]=$r['kodenr'];
				$cat_navn[$y]=$r['beskrivelse'];
				$y++;
			}

			for ($x=0;$x<=count($vare_id);$x++) {
				$op[$x]=str_replace(",",".",$op[$x]);
				$ned[$x]=str_replace(",",".",$ned[$x]);
				$op_25[$x]=str_replace(",",".",$op_25[$x]);
				$ned_25[$x]=str_replace(",",".",$ned_25[$x]);
				$op_40[$x]=str_replace(",",".",$op_40[$x]);
				$ned_40[$x]=str_replace(",",".",$ned_40[$x]);
				$op_60[$x]=str_replace(",",".",$op_60[$x]);
				$ned_60[$x]=str_replace(",",".",$ned_60[$x]);
				$op_30m[$x]=str_replace(",",".",$op_30m[$x]);
				$ned_30m[$x]=str_replace(",",".",$ned_30m[$x]);
			
				$op[$x]*=1;$ned[$x]*=1;$op_25[$x]*=1;$ned_25[$x]*=1;$op_40[$x]*=1;$ned_40[$x]*=1;$op_60[$x]*=1;$ned_60[$x]*=1;$op_30m[$x]*=1;$ned_30m[$x]*=1;$pris_op[$x]*=1;$pris_ned[$x]*=1;
				$linjesum[$x]=$op[$x]*$pris_op[$x]+$op_25[$x]*$pris_op[$x]*0.25+$op_40[$x]*$pris_op[$x]*0.4+$op_60[$x]*$pris_op[$x]*0.6;
				$linjesum[$x]+=$ned[$x]*$pris_ned[$x]+$ned_25[$x]*$pris_ned[$x]*0.25+$ned_40[$x]*$pris_ned[$x]*0.4+$ned_60[$x]*$pris_ned[$x]*0.6;
				$akksum+=$linjesum[$x];
				
#cho "$vare_tekst[$x] -> List >".substr($vare_tekst[$x],0,1)."<<br>";
				if (substr($vare_tekst[$x],0,1)=='7'){
					$teltsum+=$op[$x]*$pris_op[$x];
					#cho "$vare_id[$x] $vare_nr[$x] $op[$x]*$pris_op[$x] -> TS $teltsum<br>";
				}
				
				if (isset($enhed_id[$x]) && $enhed_id[$x] && !$afvist) {
					if ($op[$x]||$ned[$x]) {
						$qtxt="update loen_enheder set op='$op[$x]',ned='$ned[$x]',";
						$qtxt.="op_25='$op_25[$x]',ned_25='$ned_25[$x]',op_40='$op_40[$x]',ned_40='$ned_40[$x]',";
						$qtxt.="op_60='$op_60[$x]',ned_60='$ned_60[$x]',op_30m='$op_30m[$x]',ned_30m='$ned_30m[$x]',";
						$qtxt.="pris_op='$pris_op[$x]',pris_ned='$pris_ned[$x]',tekst='$vare_tekst[$x]',procent='0' where id='$enhed_id[$x]'";
					}	else $qtxt="delete from loen_enheder where id='$enhed_id[$x]'";

#cho "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} elseif (($op[$x] || $ned[$x]) && (!$afvist || $afvist_pga)) {
					if (is_numeric($vare_id[$x])) db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr) values ('$id','$vare_id[$x]','$op[$x]','$ned[$x]','$op_25[$x]','$ned_25[$x]','$op_40[$x]','$ned_40[$x]','$op_60[$x]','$ned_60[$x]','$op_30m[$x]','$ned_30m[$x]','$pris_op[$x]','$pris_ned[$x]','$vare_tekst[$x]','0','$vare_nr[$x]')",__FILE__ . " linje " . __LINE__);
				}	
			}
			if (is_numeric($teltsum)) db_modify("update loen_enheder set pris_op='$teltsum' where vare_id='-2' and loen_id='$id'",__FILE__ . " linje " . __LINE__);
			if ($afvist && $afvis_id && $id) {
				$q=db_select("select * from loen_enheder where loen_id='$afvis_id' and vare_id < '0'",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)){
					db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,op_25,ned_25,op_40,ned_40,op_60,ned_60,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr) values ('$id','$r[vare_id]','$r[op]','$r[ned]','$r[op_25]','$r[ned_25]','$r[op_40]','$r[ned_40]','$r[op_60]','$r[ned_60]','$r[op_30m]','$r[ned_30m]','$r[pris_op]','$r[pris_ned]','$r[tekst]','$r[procent]','$r[varenr]')",__FILE__ . " linje " . __LINE__);
				}
			}

			
			
			$q=db_select("select id,kodenr from grupper where art ='V_CAT' and lower(box1)='transport'",__FILE__ . " linje " . __LINE__);
			if ($r=db_fetch_array($q)) {
				$transport_id=$r['kodenr'];
			}
			$x=0;
			$q = db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id' ORDER BY varenr,tekst",__FILE__ . " linje " . __LINE__); // Har fjernet 'order by tekst' og ændret til 'order by vare_id'. Vil gerne have den samme rækkefølge som original liste | tilbageført 20140326 | tilføjet varenr 20140328
			while ($r = db_fetch_array($q)) {
				if ($r['vare_id']>0) {
					$l_id[$x]=$r['id'];
					$l_vare_id[$x]=$r['vare_id']*1;
					$l_vare_nr[$x]=$r['varenr']*1;
					$l_op[$x]=$r['op']*1;
					$l_ned[$x]=$r['ned']*1;
					$l_op_25[$x]=$r['op_25']*1;
					$l_ned_25[$x]=$r['ned_25']*1;
					$l_op_40[$x]=$r['op_40']*1;
					$l_ned_40[$x]=$r['ned_40']*1;
					$l_op_60[$x]=$r['op_60']*1;
					$l_ned_60[$x]=$r['ned_60']*1;
					$l_op_30m[$x]=$r['op_30m']*1;
					$l_ned_30m[$x]=$r['ned_30m']*1;
					$l_pris_op[$x]=$r['pris_op']*1;
					$l_pris_ned[$x]=$r['pris_ned']*1;
					list($l_liste[$x],$l_tekst[$x])=explode("|",$r['tekst']);
#			if (!$afsluttet) {
					$r2=db_fetch_array(db_select("SELECT beskrivelse,gruppe FROM varer WHERE id = '$l_vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					if($r2['gruppe']) $l_liste[$x]=$r2['gruppe'];
					if($r2['beskrivelse']) $l_tekst[$x]=$r2['beskrivelse'];
					for ($c=0;$c<count($cat_id);$c++) {
						if ($l_liste[$x]==$cat_id[$c]) {
							$tmp=$cat_navn[$c]." - ";
							$l_tekst[$x]=str_replace($tmp,"",$l_tekst[$x]);
							$l_tekst[$x]="$cat_navn[$c] - $l_tekst[$x]";
						} 
					}	
					$x++;
				}
			}
			$q = db_select("SELECT * FROM varer WHERE id > '0' AND gruppe = '$listevalg' ORDER BY varenr ASC",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$tmp=explode(chr(9),$r['kategori']);
	#			if (in_array($listevalg,$tmp)) {
				$vare_id[$x]=$r['id'];
				$montagepris[$x]=$r['montage'];
				$demontagepris[$x]=$r['demontage']; 
				$op[$x]=NULL;		
				$ned[$x]=NULL;		
				$enhed_id[$x]=NULL;		
				if (in_array($vare_id[$x],$l_vare_id)) {
					for ($y=0;$y<count($l_vare_id);$y++) {
						if ($vare_id[$x]==$l_vare_id[$y]){
							if (in_array($transport_id,$tmp)){
								$tr_sum+=$l_op[$y]*$montagepris[$x]*0.07;
								$tr_sum+=$l_ned[$y]*$demontagepris[$x]*0.14;
							}
						}
					}	
				}
				$x++;
			}
			if (is_numeric($tr_sum)) db_modify("update loen_enheder set pris_op = '$tr_sum' where vare_id='-1' and loen_id='$id'",__FILE__ . " linje " . __LINE__);
			for ($x=0;$x<count($a_stk);$x++) {
				$a_stk[$x]=usdecimal($a_stk[$x])*1;
				$a_pris[$x]=usdecimal($a_pris[$x])*1;
				$a_txt[$x]=db_escape_string(trim($a_txt[$x]));
				if ($a_pct[$x]=='') $a_pct[$x]=100;
				else $a_pct[$x]=usdecimal($a_pct[$x])*1;
				$akksum+=$a_stk[$x]*$a_pris[$x];
				if (isset($a_id[$x]) && $a_id[$x] && !$afvist) {
					if ($a_stk[$x]) {
						$qtxt="update loen_enheder set op='$a_stk[$x]',ned='0',pris_op='$a_pris[$x]',pris_ned='0',tekst='$a_txt[$x]',procent='$a_pct[$x]' where id='$a_id[$x]'";
					} else {
						$qtxt="delete from loen_enheder where id='$a_id[$x]'";
					}
					#cho "$qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} elseif ($a_stk[$x]) {
					db_modify("insert into loen_enheder (loen_id,vare_id,op,ned,pris_op,pris_ned,tekst,procent) values ('$id','0','$a_stk[$x]','0','$a_pris[$x]','0','$a_txt[$x]','$a_pct[$x]')",__FILE__ . " linje " . __LINE__);
				}
			}
		} # endif ($loen_art=='akk_afr')
		if ($afvis && $afvist_pga)	{ #20130905-2
			transaktion('commit');
			print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
			exit;
		}
	} #xit;
	transaktion('commit');
}
?>