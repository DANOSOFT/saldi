<?php

// ------ sager/loen.php-------lap 3.6.0 ------2015-12-16------13:22---------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2015 Danosoft ApS
// ----------------------------------------------------------------------
// 2013-04-10 Indsat sessionsvariabler til søgning
// 2013-04-22 Indsat sessionsvariabler til sortering, rettet variabler til søgning og tilrette søgning på afregnede
// 2013-07-29 Ombyttet med nedenstående da godkendte og derefter afviste sedler blev grønne
// 2013-08-08 Overfører afviste men ikke overførte sedler på afvisningstidspkt.
// 2013-10-23 Viser sedler som man selv har oprettet #20131023
// 2014-03-31 Det er nu muligt at søge på sager og opgave. Søg 20143103
// 2014-06-27 Tilføjet 'ferie' til 'loen_art'. Søg 20140627
// 2015-05-13 Det er nu muligt at søge fra En given dato til Anden given dato i 'Løn dato'. Søg $s_loendateFra eller $s_loendateTil
// 2015-12-16 Afregningsseddel nr vises som title v mouseover på seddel nr. Søg master_nr
// 2016-10-11 Har fjernet Aconto + Regulering fra type. Søg 20161011 
// 2016-11-07 Montør ser alle sine sedler og ikke kun dem som ligger indenfor de første 500 linjer. Søg 20161107
// 2016-11-08 Har fjernet medarbejder som er aftrådt fra dropdown og sortering er nu efter navn. Søg 20161108
// 2017-08-04 Finder ud af om feltet loendate er tomt. jQuery læser hidden field og fjerner link til akkordlister. Søg 20170804
// 2017-08-11 Finder ud af om feltet sagnr er tomt. jQuery læser hidden field og fjerner link til sag og akkordlister. Søg 20170811
// 20180612 PHR - Separat array for art_navn & alias så system kan tilrettes andre brancher (blot en start) #20180612


function loenliste() {
	global $db;

	$soeg = $s_loendateFra = $s_loendateTil  = NULL;
	
	$sag_id=if_isset($_GET['sag_id']);
	$opgave_id=if_isset($_GET['opgave_id']);
	$loendate=if_isset($_GET['loendate']);
	$loendateFra=if_isset($_GET['loendateFra']);
	$loendateTil=if_isset($_GET['loendateTil']);
	
	$s_nummer=if_isset($_POST['s_nummer']);
	$s_loendate=if_isset($_POST['s_loendate']);
	$s_loendateFra=if_isset($_POST['s_loendateFra']);
	$s_loendateTil=if_isset($_POST['s_loendateTil']);
	$s_oprettet=if_isset($_POST['s_oprettet']);
	$s_afsluttet=if_isset($_POST['s_afsluttet']);
	$s_godkendt=if_isset($_POST['s_godkendt']);
	$s_afregnet=if_isset($_POST['s_afregnet']);
	$s_sag_nr=if_isset($_POST['s_sag_nr']);
	$s_art=if_isset($_POST['s_art']);
	//$s_kategori=if_isset($_POST['s_kategori']);
	$s_ansvarlig=if_isset($_POST['s_ansvarlig']);
	$s_ansat=if_isset($_POST['s_ansat']);
	$s_sum=if_isset($_POST['s_sum']);
	$s_limit=if_isset($_POST['s_limit']);
//echo "Løndato: $s_loendate Fra: $s_loendateFra Til: $s_loendateTil<br>";

	if (isset($_POST['find']) && $_POST['find']) {
		$_SESSION['s_nummer']=$s_nummer;
		$_SESSION['s_loendate']=$s_loendate;
		$_SESSION['s_loendateFra']=$s_loendateFra;
		$_SESSION['s_loendateTil']=$s_loendateTil;
		$_SESSION['s_oprettet']=$s_oprettet;
		$_SESSION['s_afsluttet']=$s_afsluttet;
		$_SESSION['s_godkendt']=$s_godkendt;
		$_SESSION['s_afregnet']=$s_afregnet;
		$_SESSION['s_sag_nr']=$s_sag_nr;
		$_SESSION['s_art']=$s_art;
		//$_SESSION['s_kategori']=$s_kategori;
		$_SESSION['s_ansvarlig']=$s_ansvarlig;
		$_SESSION['s_ansat']=$s_ansat;
		$_SESSION['s_sum']=$s_sum;
		$_SESSION['s_limit']=$s_limit;
	} else {
		$s_nummer      = if_isset($_SESSION['s_nummer']);
		$s_loendate    = if_isset($_SESSION['s_loendate']);
		$s_loendateFra = if_isset($_SESSION['s_loendateFra']);
		$s_loendateTil = if_isset($_SESSION['s_loendateTil']);
		$s_oprettet    = if_isset($_SESSION['s_oprettet']);
		$s_afsluttet   = if_isset($_SESSION['s_afsluttet']);
		$s_godkendt    = if_isset($_SESSION['s_godkendt']);
		$s_afregnet    = if_isset($_SESSION['s_afregnet']);
		$s_sag_nr      = if_isset($_SESSION['s_sag_nr']);
		$s_art         = if_isset($_SESSION['s_art']);
		//$s_kategori= if_isset($_SESSION['s_kategori']);
		$s_ansvarlig   = if_isset($_SESSION['s_ansvarlig']);
		$s_ansat       = if_isset($_SESSION['s_ansat']);
		$s_sum         = if_isset($_SESSION['s_sum']);
		$s_limit       = if_isset($_SESSION['s_limit']);
	}
	
	#$art_navn[0]='regulering'; #20161011
	#$art_alias[0]='Regulering';
	if ($db=='stillads_14' || $db=='udvikling_2') { #20180612
		$art_navn=array('akkord','timer','torretid','plads','skole','sygdom','barn_syg','ferie');
		$art_alias=array('Timepris Akkord','Timeløn','Timeløn Tørretid','Pladsarbejde','Skoleophold','Sygdom','Barn syg','Ferie');
	} else {
		$art_navn[0]='akktimer';
		$art_alias[0]='Dyrtid'; //Akkord timer
		$art_navn[1]='akk_afr';
		$art_alias[1]='Akkord afregning';
		$art_navn[2]='akkord';
		$art_alias[2]='Akkord med dyrtid'; //Akkord
		$art_navn[3]='timer'; 
		$art_alias[3]='Timeløn'; //Løntimer
		$art_navn[4]='plads'; 
		$art_alias[4]='Plads';
		$art_navn[5]='skole'; 
		$art_alias[5]='Skoleophold';
		$art_navn[6]='sygdom'; 
		$art_alias[6]='Sygdom';
		$art_navn[7]='barn_syg'; 
		$art_alias[7]='Barn syg';
		#$art_navn[9]='aconto'; #20161011
		#$art_alias[9]='Aconto';
		$art_navn[8]='ferie'; #20140627
		$art_alias[8]='Ferie';
	}
	global $brugernavn;
	global $ansat_id;
	global $sag_rettigheder;
	
	$id=array();
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('nummer','loendate','oprettet','afsluttet','godkendt','afregnet','sag_nr','art','sag_ref','ansatte','sum');// ændre 'oprettet_af' til 'sag_ref'
	$vis=if_isset($_GET['vis']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	
	if ($vis=='overforte') $overforte=$vis;
	elseif ($vis=='godkendte') $godkendte=$vis;
	elseif ($vis=='afviste') $afviste=$vis;
	elseif ($vis=='betalte') $betalte=$vis;
	
	
	if ($nysort && $nysort==$sort) {
		$sort=$nysort."%20desc";
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="desc":$sortstyle[$key]="";
		}
	}else{ 
		$sort=$nysort;
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="asc":$sortstyle[$key]="";
		}
	}
	
	if ($_GET['nysortstyle']) {
		$_SESSION['loen_nummer']=$sortstyle[0];
		$_SESSION['loen_loendate']=$sortstyle[1];
		$_SESSION['loen_oprettet']=$sortstyle[2];
		$_SESSION['loen_afsluttet']=$sortstyle[3];
		$_SESSION['loen_godkendt']=$sortstyle[4];
		$_SESSION['loen_afregnet']=$sortstyle[5];
		$_SESSION['loen_sag_nr']=$sortstyle[6];
		$_SESSION['loen_art']=$sortstyle[7];
		$_SESSION['loen_sag_ref']=$sortstyle[8];
		$_SESSION['loen_ansatte']=$sortstyle[9];
		$_SESSION['loen_sum']=$sortstyle[10];
	} else {
		$sortstyle[0]  = if_isset($_SESSION['loen_nummer']);
		$sortstyle[1]  = if_isset($_SESSION['loen_loendate']);
		$sortstyle[2]  = if_isset($_SESSION['loen_oprettet']);
		$sortstyle[3]  = if_isset($_SESSION['loen_afsluttet']);
		$sortstyle[4]  = if_isset($_SESSION['loen_godkendt']);
		$sortstyle[5]  = if_isset($_SESSION['loen_afregnet']);
		$sortstyle[6]  = if_isset($_SESSION['loen_sag_nr']);
		$sortstyle[7]  = if_isset($_SESSION['loen_art']);
		$sortstyle[8]  = if_isset($_SESSION['loen_sag_ref']);
		$sortstyle[9]  = if_isset($_SESSION['loen_ansatte']);
		$sortstyle[10] = if_isset($_SESSION['loen_sum']);
	}
	
	if ($_GET['vis']) {
		$_SESSION['overforte']=$overforte;
		$_SESSION['godkendte']=$godkendte;
		$_SESSION['afviste']=$afviste;
		$_SESSION['betalte']=$betalte;
	} else {
		$overforte = if_isset($_SESSION['overforte']);
		$godkendte = if_isset($_SESSION['godkendte']);
		$afviste   = if_isset($_SESSION['afviste']);
		$betalte   = if_isset($_SESSION['betalte']);
	}
	
	if ($unsetsort) {
		unset($_SESSION['loen_sort'],
					$_SESSION['loen_nummer'],$sortstyle[0],
					$_SESSION['loen_loendate'],$sortstyle[1],
					$_SESSION['loen_oprettet'],$sortstyle[2],
					$_SESSION['loen_afsluttet'],$sortstyle[3],
					$_SESSION['loen_godkendt'],$sortstyle[4],
					$_SESSION['loen_afregnet'],$sortstyle[5],
					$_SESSION['loen_sag_nr'],$sortstyle[6],
					$_SESSION['loen_art'],$sortstyle[7],
					$_SESSION['loen_sag_ref'],$sortstyle[8],
					$_SESSION['loen_ansatte'],$sortstyle[9],
					$_SESSION['loen_sum'],$sortstyle[10],
					$_SESSION['s_nummer'],$s_nummer,
					$_SESSION['s_loendate'],$s_loendate,
					$_SESSION['s_loendateFra'],$s_loendateFra,
					$_SESSION['s_loendateTil'],$s_loendateTil,
					$_SESSION['s_oprettet'],$s_oprettet,
					$_SESSION['s_afsluttet'],$s_afsluttet,
					$_SESSION['s_godkendt'],$s_godkendt,
					$_SESSION['s_afregnet'],$s_afregnet,
					$_SESSION['s_sag_nr'],$s_sag_nr,
					$_SESSION['s_art'],$s_art,
					$_SESSION['s_ansvarlig'],$s_ansvarlig,
					$_SESSION['s_ansat'],$s_ansat,
					$_SESSION['s_sum'],$s_sum,
					$_SESSION['s_limit'],$s_limit,
					$_SESSION['overforte'],$overforte,
					$_SESSION['godkendte'],$godkendte,
					$_SESSION['afviste'],$afviste,
					$_SESSION['betalte'],$betalte
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if (isset($sort) && $sort) $_SESSION['loen_sort']=$sort;
	else $sort = if_isset($_SESSION['loen_sort']);
	if (!$sort) $sort="nummer%20desc";
	
	$sqlsort=urldecode($sort);
	
	//echo "Limit: $s_limit";
	$limitarray=array('500','1000','2500','5000','10000','NULL');
	$limitnavn=array('500','1000','2500','5000','10000','Alle');
	
	($s_limit)?$limit=$s_limit:$limit='500';
	
	$x=0;
#	$q=db_select("select id,kodenr,art,box1 from grupper where art ='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
#	while ($r=db_fetch_array($q)) {
#		$cat_id[$x]=$r['kodenr'];
#		$cat_navn[$x]=$r['box1'];
#		$x++;
#	}
	$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$x]=$r['kodenr'];
		$cat_navn[$x]=$r['beskrivelse'];
		$x++;
	}
	// Query til ansatte
	$x=0;
	$q=db_select("select id,initialer,navn from ansatte where slutdate ='9999-12-31' order by navn",__FILE__ . " linje " . __LINE__); #20161108
	while($r=db_fetch_array($q)) {
		$ansatte_id[$x]=$r['id'];
		$ansatte_init[$x]=$r['initialer'];
		$ansatte_navn[$x]=$r['navn'];
		if ($ansat_id==$ansatte_id[$x]) $ansat_init=$ansatte_init[$x]; #20131023
		$x++;
	}
	$x=0;
	$q=db_select("select id,brugernavn from brugere",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$brugere_id[$x]=$r['id'];
		$brugere_brugernavn[$x]=$r['brugernavn'];
		$x++;
	}
	$x=0;
	$q=db_select("select id,sagsnr,ref,udf_addr1 from sager",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$sagid[$x]=$r['id']; // Bruges til at overfører 'ref' fra sager til 'loen'. Kan udkommenteres efter brug!!
		$ref[$x]=$r['ref']; // Bruges til at overfører 'ref' fra sager til 'loen'. Kan udkommenteres efter brug!!
		$sagsnr[$x]=$r['sagsnr'];
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$x++;
	}
	
	// Query til ansvarlig
	$x=0;
	$q=db_select("select * from grupper where art='brgrp'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$gruppe_id[$x]=$r['id']*1;
		$beskrivelse[$x]=$r['beskrivelse'];
		$rettigheder[$x]=(substr($r['box2'],2,1)); //finder opret/ret sag rettighed
		if($rettigheder[$x]==1) $gruppeid[$x]=$gruppe_id[$x]; // finder de gruppe_id'er som har rettighed til opret/ret sag
	} 
	
	$in_str = "'".implode("', '", $gruppeid)."'"; // formatere '$gruppeid[]' til f.eks. '52','77' osv.
	
	$x=0;
	$q=db_select("select * from ansatte where konto_id=1 and gruppe IN ($in_str) order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ansvarlig[$x]=htmlspecialchars($r['navn']);
		$gruppe[$x]=$r['gruppe']; // gruppe_id fra 'grupper'
		$x++;
	}
	/*
	print "<pre>";
	print "gruppe_id:\n";
	print_r ($gruppe_id);
	print "\nbeskrivelse:\n";
	print_r ($beskrivelse);
	print "\nrettigheder:\n";
	print_r ($rettigheder);
	print "\nopret sag rettigheder:\n";
	print_r ($gruppeid);
	print "\ngruppe:\n";
	print_r ($gruppe);
	print "\nansvarlig:\n";
	print_r ($ansvarlig);
	print "</pre>";
	*/
	if ($sag_id && !$opgave_id) {
		//echo "sag_id1: $sag_id ";
		$r=db_fetch_array(db_select("select sagsnr from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$s_sag_nr=$r['sagsnr']*1;
	} elseif ($sag_id && $opgave_id) {
		//echo "sag_id1: $sag_id opgave_id1: $opgave_id ";
	}
	/*
	if ($vis=='overforte') $where="where afsluttet !='' and godkendt = '' and afvist = ''"; //$overforte
	elseif ($vis=='godkendte') $where="where godkendt !='' and art != 'akktimer' and afregnet = ''"; //$godkendte
	elseif ($vis=='afviste') $where="where afvist !=''"; //$afviste
	elseif ($vis=='betalte') $where="where afregnet !=''"; //$betalte
	else $where='';
	*/
	
	if ($overforte) $where="where afsluttet !='' and godkendt = '' and afvist = ''";
	elseif ($godkendte) $where="where godkendt !='' and art != 'akktimer' and afregnet = ''";
	elseif ($afviste) $where="where afvist !=''";
	elseif ($betalte) $where="where afregnet !=''";
	else $where='';
	
	if (!substr($sag_rettigheder,6,1) && !$s_ansat) { #20161107
		//echo "$brugernavn $ansat_id<br>";
		$tmp1=$ansat_id.chr(9);
		$tmp2=chr(9).$ansat_id;
		$tmp3=chr(9).$ansat_id.chr(9);
		$where.= " and (ansatte LIKE '$tmp1%' or ansatte LIKE '%$tmp2' or ansatte LIKE '%$tmp3%' or ansatte = '$ansat_id')";
		//echo "where = $where<br>";
	}
	
	if ($s_nummer) $where.= " and nummer='$s_nummer'";  
	//if ($s_loendate) $where.= " and loendate='".usdate($s_loendate)."'";
	if ($s_loendate) {
		if ($s_loendateFra && $s_loendateTil) {
			$where.= " and (loendate>='".usdate($s_loendateFra)."' and loendate<='".usdate($s_loendateTil)."')";
		} else {
			$where.= " and loendate='".usdate($s_loendateFra)."'";
		}
	} else {
		unset($_SESSION['s_loendateFra'],$s_loendateFra,$_SESSION['s_loendateTil'],$s_loendateTil);
	}
	if ($s_oprettet) {
		$tmp1=strtotime(usdate($s_oprettet));
		$tmp2=strtotime(usdate($s_oprettet))+86400;
		$where.= " and (oprettet>'".$tmp1."' and oprettet<'".$tmp2."')";
	}
	if ($s_afsluttet) {
		$tmp1=strtotime(usdate($s_afsluttet));
		$tmp2=strtotime(usdate($s_afsluttet))+86400;
		$where.= " and (afsluttet>'".$tmp1."' and afsluttet<'".$tmp2."')";
	}
	if ($s_godkendt) {
		$tmp1=strtotime(usdate($s_godkendt));
		$tmp2=strtotime(usdate($s_godkendt))+86400;
		$where.= " and (godkendt>'".$tmp1."' and godkendt<'".$tmp2."')";
	}
	if ($s_afregnet) {
		$tmp1=strtotime(usdate($s_afregnet));
		$tmp2=strtotime(usdate($s_afregnet))+86400;
		$where.= " and (afregnet>'".$tmp1."' and afregnet<'".$tmp2."')";
	}
	//echo "s_sag_nr: $s_sag_nr ";
	//if ($s_sag_nr) $where.= " and sag_nr='$s_sag_nr'";
	
	if ($s_sag_nr) { #20143103
		if ((strpos($s_sag_nr,'-') !== false) && is_numeric(substr($s_sag_nr, strpos($s_sag_nr, "-") + 1))) {
			list($sagnr, $opgnr) = explode("-", "$s_sag_nr", 2);
			($sagnr == NULL)?$sagnr ='':$and_sagnr = "and sag_nr='$sagnr'";
			$where.= " $and_sagnr and opg_nr='$opgnr'";
			//echo "sag_nr: $sagnr og opg_nr: $opgnr";
		} elseif (!is_numeric($s_sag_nr)) {
			//echo "ikke et nummer";
			$where.= "and sag_nr=NULL";
			
		} else {
			$where.= " and sag_nr='$s_sag_nr'";
			
		}
	}
	
	if ($s_art) $where.= " and art='$s_art'";
	
	//if ($s_kategori) $where.= " and kategori='$s_kategori'";
	if ($s_ansvarlig) $where.= " and sag_ref='$s_ansvarlig'";// [SKAL VÆRE SAGS-ANSVARLIG] søgning skal være 'sag_ref'
	if ($s_ansat) {
		$tmp1=$s_ansat.chr(9);
		$tmp2=chr(9).$s_ansat;
		$tmp3=chr(9).$s_ansat.chr(9);
		$where.= " and (ansatte LIKE '$tmp1%' or ansatte LIKE '%$tmp2' or ansatte LIKE '%$tmp3%' or ansatte = '$s_ansat')";
	}
	if ($s_sum) $where.= " and sum='".usdecimal($s_sum)."'";
	$where=trim($where);
	if (substr($where,0,3)=='and') $where=" where ".substr($where,4);
	$x=0;
	$q=db_select("select * from loen $where order by $sqlsort limit $limit",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$id[$x]=$r['id'];
		$loen_nr[$x]=$r['nummer']*1;
		$loen_tekst[$x]=$r['tekst'];
		$fordeling[$x]=$r['fordeling'];
		$loen_art[$x]=$r['art'];
		$hvem[$x]=$r['hvem'];
		$sag_id[$x]=$r['sag_id']*1;
		$sag_nr[$x]=$r['sag_nr']*1;
		$opg_nr[$x]=$r['opg_nr']*1;
		$sag_ref[$x]=$r['sag_ref']; 
		$loendato[$x]=dkdato($r['loendate']);
		($r['oprettet'])?$oprettet[$x]=$r['oprettet']:$oprettet[$x]='';
		($r['afsluttet'])?$afsluttet[$x]=$r['afsluttet']:$afsluttet[$x]='';
		($r['godkendt'])?$godkendt[$x]=$r['godkendt']:$godkendt[$x]='';
		($r['afvist'])?$afvist[$x]=$r['afvist']:$afvist[$x]='';
		($r['afregnet'])?$afregnet[$x]=$r['afregnet']:$afregnet[$x]='';
		$sum[$x]=$r['sum'];
		$oprettet_af[$x]=$r['oprettet_af'];
		$ansatte[$x]=$r['ansatte'];
		$ansatliste[$x]=array();
		$ansatliste[$x]=explode(chr(9),$ansatte[$x]);
		$ansatliste[$x]=array_map('trim',$ansatliste[$x]);
		$fordeling[$x]=$r['fordeling'];
		$kategori[$x]=$r['kategori'];
		$master_id[$x]=$r['master_id'];
		if ($afvist[$x] && !$afsluttet[$x]) db_modify("update loen set afsluttet='$afvist[$x]' where id='$id[$x]'",__FILE__ . " linje " . __LINE__); 
		if ($master_id[$x]) { #20151216
			$r2=db_fetch_array(db_select("select nummer from loen where id='$master_id[$x]'",__FILE__ . " linje " . __LINE__));
			$master_nr[$x]="Afregnet på seddel ".$r2['nummer'];
		} else $master_nr[$x]=NULL;
		#20130808
		$x++;
	}
	//echo "where = $where<br>";
	//echo "sql = select * from loen $where order by $sqlsort limit $limit<br>";
	/*
	// Script der overfører 'ref' fra sager til loen. Skal udkommenteres efter opdatering!!
	for ($x=1;$x<count($id);$x++) {
		if (!$sagid[$x]==NULL) {
			db_modify("update loen set sag_ref = '$ref[$x]' where sag_id = '$sagid[$x]'",__FILE__ . " linje " . __LINE__);
		}
	}
	*/
	//echo "overforte: $overforte<br>";echo "godkendte: $godkendte<br>";echo "afviste: $afviste<br>";echo "betalte: $betalte<br>";
	//echo "SessionLøndato: $_SESSION[s_loendate] SessionFra: $_SESSION[s_loendateFra] SessionTil: $_SESSION[s_loendateTil]<br>";
	//echo "s_sag_nr $s_sag_nr<br>";
	
	// Finder ud af om loendate-feltet er tomt
	(!empty($_SESSION['s_loendate']))?$loendateVis = "on":$loendateVis = NULL; #20170804
	print "<input type=\"hidden\" id=\"loendateVis\" value=\"$loendateVis\"/>";
	//echo "loendateVis $loendateVis<br>";
	
	// Finder ud af om sagnr feltet er tomt
	(!empty($_SESSION['s_sag_nr']))?$sagnrVis = "on":$sagnrVis = NULL; #20170811
	print "<input type=\"hidden\" id=\"sagnrVis\" value=\"$sagnrVis\"/>";
	//echo "sagnrVis $sagnrVis<br>";
	
	// Title til loendate
	if ($s_loendate) {
		if ($s_loendateFra && $s_loendateTil) {
			$s_loendateTitle = "Fra:	$s_loendateFra\nTil:	$s_loendateTil";
		} else {
			$s_loendateTitle = "$s_loendateFra";
		}
	} else {
		$s_loendateTitle = NULL;
	}
	$s_loendateFra = if_isset($s_loendateFra);
	$s_loendateTil = if_isset($s_loendateTil);
	
	print "<div class=\"contentkundehead\">
		<ul id=\"sort\">
			<li>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=nummer&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:40px\">Nr</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=loendate&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:69px\">Løn dato</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=oprettet&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:69px\">Oprettet</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=afsluttet&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:69px\">Overført</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=godkendt&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:69px\">Godk/Afv</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=afregnet&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[5]\" class=\"felt06 $sortstyle[5]\" style=\"width:69px\">Betalt</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=sag_nr&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[6]\" class=\"felt07 $sortstyle[6]\" style=\"width:100px\">Sag</a>       
				<a href=\"loen.php?funktion=loenliste&amp;nysort=art&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[7]\" class=\"felt08 $sortstyle[7]\" style=\"width:80px\">Type</a>       
				<a href=\"loen.php?funktion=loenliste&amp;nysort=sag_ref&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[8]\" class=\"felt09 $sortstyle[8]\" style=\"width:80px\">Ansvarlig</a>       
				<a href=\"loen.php?funktion=loenliste&amp;nysort=ansatte&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[9]\" class=\"felt10 $sortstyle[9]\" style=\"width:70px\">Medarb.</a>
				<a href=\"loen.php?funktion=loenliste&amp;nysort=sum&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[10]\" class=\"felt11 $sortstyle[10]\" style=\"width:57px\">Sum</a>
			</li>
		</ul>
	</div>";
	print "<form name=\"loenliste\" action=\"loen.php?funktion=loenliste&amp;sort=$sort&amp;soeg=$soeg\" method=\"post\">\n
		<div class=\"contentkundesearch\" style=\"border-bottom:1px solid white;\">
		<ul>
			<li>
				<div class=\"felt01\" style=\"width:50px;margin-left:-10px;\"><input name=\"s_nummer\" value=\"$s_nummer\" type=\"text\" class=\"textinputloen\" style=\"width:46px\"></div>
				<div class=\"felt02\" style=\"width:69px\"><input name=\"s_loendate\" value=\"$s_loendate\" type=\"text\" class=\"textinputloen\" title=\"$s_loendateTitle\" id=\"datepicker01\" style=\"width:65px\"><input name=\"s_loendateFra\" value=\"$s_loendateFra\" type=\"hidden\" id=\"datepickerFra\"><input name=\"s_loendateTil\" value=\"$s_loendateTil\" type=\"hidden\" id=\"datepickerTil\"></div>
				<div class=\"felt03\" style=\"width:69px\"><input name=\"s_oprettet\" value=\"$s_oprettet\" type=\"text\" class=\"textinputloen\" id=\"datepicker02\" style=\"width:65px\"></div>
				<div class=\"felt04\" style=\"width:69px\"><input name=\"s_afsluttet\" value=\"$s_afsluttet\" type=\"text\" class=\"textinputloen\" id=\"datepicker03\" style=\"width:65px\"></div>
				<div class=\"felt05\" style=\"width:69px\"><input name=\"s_godkendt\" value=\"$s_godkendt\" type=\"text\" class=\"textinputloen\" id=\"datepicker04\" style=\"width:65px\"></div>
				<div class=\"felt06\" style=\"width:69px\"><input name=\"s_afregnet\" value=\"$s_afregnet\" type=\"text\" class=\"textinputloen\" style=\"width:65px\"></div>
				<div class=\"felt07\" style=\"width:100px\"><input name=\"s_sag_nr\" value=\"$s_sag_nr\" type=\"text\" class=\"textinputloen\" title=\"Ved søgning efter opgave i sag, tastes opgavenummer\nefter sagsnummer adskilt med '-'. F.eks. 12345-1\" style=\"width:96px\"></div>       
				<div class=\"felt08\" style=\"width:80px\"><select name=\"s_art\" class=\"selectinputloen\" style=\"width:76px\">\n";
				for ($i=0;$i<count($art_navn);$i++) {
					if ($s_art==$art_navn[$i]) print "<option value=\"$art_navn[$i]\">$art_alias[$i]</option>\n"; 
				}
				if (!$s_art) print "<option value=\"\">&nbsp;</option>\n";
				for ($i=0;$i<count($art_navn);$i++) {
					if ($s_art!=$art_navn[$i]) print "<OPTION value=\"$art_navn[$i]\">$art_alias[$i]</option>\n"; 
				}
				if ($s_art) print "<option value=\"\">&nbsp;</option>\n";
				print	"</select></div>\n";
				/*
				print "
				<div class=\"felt09\" style=\"width:80px\"><select name=\"s_kategori\" class=\"selectinputloen\" style=\"width:76px\">";// Original med kategori

				for ($i=0;$i<count($cat_id);$i++) {
					if ($cat_id[$i] && $cat_id[$i]==$s_kategori) print "<OPTION value=\"$cat_id[$i]\">$cat_navn[$i]</option>"; 
				}
				if (!$s_kategori) print "<OPTION value=\"\"></option>";
				for ($i=0;$i<count($cat_id);$i++) {
					if ($cat_id[$i] && $cat_id[$i]!=$s_kategori) print "<OPTION value=\"$cat_id[$i]\">$cat_navn[$i]</option>"; 
				}
				if ($s_kategori) print "<OPTION value=\"\"></option>";
				print "</select></div>";
				*/
				print "
				<div class=\"felt09\" style=\"width:80px\"><select name=\"s_ansvarlig\" class=\"selectinputloen\" style=\"width:76px\">\n";// Skal ændres til ansvarlig

				for ($i=0;$i<count($ansvarlig);$i++) {
					if ($ansvarlig[$i]==$s_ansvarlig) print "<OPTION value=\"$ansvarlig[$i]\">$ansvarlig[$i]&nbsp;</option>\n"; 
				}
				if (!$ansvarlig[$i]) print "<OPTION value=\"\">&nbsp;</option>\n";
				for ($i=0;$i<count($ansvarlig);$i++) {
					if ($ansvarlig[$i]!=$s_ansvarlig) print "<OPTION value=\"$ansvarlig[$i]\">$ansvarlig[$i]&nbsp;</option>\n"; 
				}
				if ($s_ansvarlig) print "<OPTION value=\"\">&nbsp;</option>\n";
				print "</select></div>\n";
				
				print "
				<div class=\"felt10\" style=\"width:70px\"><select name=\"s_ansat\" class=\"selectinputloen\" style=\"width:66px\">\n";
					for ($i=0;$i<count($ansatte_init);$i++) {
						if ($ansatte_init[$i] && $ansatte_id[$i]==$s_ansat) print "<OPTION value=\"$ansatte_id[$i]\">($ansatte_init[$i]) $ansatte_navn[$i]</option>\n";
					}
					if (!$s_ansat) print "<OPTION value=\"\">&nbsp;</option>\n";
					//print "<OPTION value=\"\">&nbsp;</option>\n";
					for ($i=0;$i<count($ansatte_init);$i++) {
						if ($ansatte_init[$i] && $ansatte_id[$i]!=$s_ansat) print "<OPTION value=\"$ansatte_id[$i]\">($ansatte_init[$i]) $ansatte_navn[$i]</option>\n";
					}
					if ($s_ansat) print "<OPTION value=\"\">&nbsp;</option>\n";
				print "</select></div>       
				<div class=\"felt11\" style=\"#width:85px;margin-right:-20px;\"><input name=\"s_sum\" value=\"$s_sum\" type=\"text\" class=\"textinputloen\" style=\"width:52px\"><div style=\"margin-left:3px;float:right;\"><input class=\"button gray smallx\" type=\"submit\" name=\"find\" value=\"Søg\"></div></div>
			</li>
		</ul>
	</div>
	
	<div style=\"height:25px;padding:5px 12px 0 12px;background-color: #F2F2F2;\">
		<span><a href=\"loen.php?funktion=loenliste&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
		<div style=\"float:right;\">
		<p style=\"float:left;\">Vælg antal viste linjer:&nbsp;</p>
		<select name=\"s_limit\" class=\"selectinputloen\" style=\"width:76px;\">\n";
		
			for ($i=0;$i<count($limitarray);$i++) {
					if ($s_limit==$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
				}
				for ($i=0;$i<count($limitarray);$i++) {
					if ($s_limit!=$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
				}
				
			print "
		</select>
		</div>
	</div>
	
	</form>\n";
	(count($id)<=250)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.loen.js, under pagination
	print "
	<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\" style=\"background-color: #F2F2F2;\"></div>
		<div class=\"contentkunde\">
		
		<ul id=\"things\" class=\"paging_content\">";
		
		if ($id==NULL) {
		print "<li><i>Ingen resultat!</i></li>";
		} else {
			for ($x=0;$x<count($id);$x++) {
				$vis=0;
				if (in_array($ansat_id,$ansatliste[$x]) || substr($sag_rettigheder,6,1)) $vis=1;
				if ($ansat_init==$oprettet_af[$x]) $vis=1; #20131023 
				if (!$vis=="alle" && $loen_art[$x]=='akk_timer' && ($afsluttet[$x] || $afvist[$x])) $vis=0; 
				if ($vis) { //echo "$vis";
					$kat=NULL;
					if ($kategori[$x]) {
						for ($y=0;$y<count($cat_id);$y++) {
						if ($kategori[$x]==$cat_id[$y]) $kat=$cat_navn[$y];
						}
					}
					$ansv=NULL;// Her skal der være ansvarlig
					if ($oprettet_af[$x]) {
						for ($y=0;$y<count($brugere_id);$y++) {
						if ($oprettet_af[$x]==$brugere_brugernavn[$y]) $ansv=$brugere_brugernavn[$y];
						}
					}
					$ans=NULL;
					$ansttl=NULL;
					if ($ansatte[$x]) {
						for ($y=0;$y<count($ansatte_id);$y++) {
							if ($ansatte_id[$y] && in_array($ansatte_id[$y],$ansatliste[$x])) {
								($ans)?$ans.=",".$ansatte_init[$y]:$ans=$ansatte_init[$y];
								($ansttl)?$ansttl.=",\n(".$ansatte_init[$y].")&nbsp;".$ansatte_navn[$y]:$ansttl="(".$ansatte_init[$y].")&nbsp;".$ansatte_navn[$y];
							}
						}
					}
					
					$sag=NULL;$addr=NULL;
					if ($sag_nr[$x]) {
						for ($y=0;$y<count($sagsnr);$y++) {
						if ($sag_nr[$x]==$sagsnr[$y]) {
								$addr=$udf_addr1[$y];
							}
						} 
					} 
					$art='';
					for ($i=0;$i<count($art_navn);$i++) {
						if ($loen_art[$x]==$art_navn[$i]) $art=$art_alias[$i];	
					}
					($oprettet[$x])?$opr=date("d-m-Y",$oprettet[$x]):$opr='';
					($afsluttet[$x])?$afs=date("d-m-Y",$afsluttet[$x]):$afs='';
					($godkendt[$x])?$godk=date("d-m-Y",$godkendt[$x]):$godk='';
					($afvist[$x])?$afv=date("d-m-Y",$afvist[$x]):$afv='';
					($afregnet[$x])?$afr=date("d-m-Y",$afregnet[$x]):$afr='';
					$belob=dkdecimal($sum[$x]);
					if ($afv) $color="red"; #20130729 Ombyttet med nedenstående da godkendte og derefter afviste sedler blev grønne
					elseif ($godk) $color="green"; #20130729
					else $color="black";
					print "<li><a href=\"loen.php?id=$id[$x]&amp;funktion=ret_loen\">\n";
					print "<span class=\"felt01\" style=\"width:40px;color:$color;text-align:right\" title=\"$master_nr[$x]\">$loen_nr[$x]&nbsp;</span>\n";
					print "<span class=\"felt02\" style=\"width:69px;color:$color;\">$loendato[$x]&nbsp;</span>\n";
					print "<span class=\"felt03\" style=\"width:69px;color:$color;\">$opr&nbsp;</span>\n";
					print "<span class=\"felt04\" style=\"width:69px;color:$color;\">$afs&nbsp;</span>\n";
					if (!$afv) print "<span class=\"felt05\" style=\"width:69px;color:$color;\">$godk&nbsp;</span>\n";
					else print "<span class=\"felt05\" style=\"width:69px;color:$color;\">$afv&nbsp;</span>\n";
					print "<span class=\"felt06\" style=\"width:69px;color:$color;\">$afr&nbsp;</span>\n";
					print "<span class=\"felt07\" style=\"width:100px;color:$color;\" title=\"Sag: $sag_nr[$x] - Opgave: $opg_nr[$x]\n$addr\">$sag_nr[$x]-$opg_nr[$x] $addr&nbsp;</span>\n";
					print "<span class=\"felt08\" style=\"width:80px;color:$color;\" title=\"$art\">$art&nbsp;</span>\n";
					print "<span class=\"felt09\" style=\"width:80px;color:$color;\" title=\"$sag_ref[$x]\">$sag_ref[$x]&nbsp;</span>\n";
					print "<span class=\"felt10\" style=\"width:70px;color:$color;\" title=\"$ansttl\">$ans&nbsp;</span>\n";
					print "<span class=\"felt11\" style=\"width:65px;color:$color;text-align:right\" title=\"$belob\">$belob&nbsp;</span>\n";
					print "</a></li>";
				}
			}
		}
		print "</ul>
	</div><!-- end of contentkunde -->
	<div class=\"page_navigation $abortlist\"></div>
	</div><!-- end of paging_container -->";
}
?>
