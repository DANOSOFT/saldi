<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/loenIncludes/loen.php --- lap 4.0.8 --- 2023-10-05 ---
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
// Copyright (c) 2003-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20230703 PHR New up/down height add on's was omitted in new note (seddel) whe denied (afvist) 
// 20231005 PHR mentor and km now omittet on hoursalary (timeløn)  
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

function ret_loen() {
	global $brugernavn;
	global $db,$db_encode;
	global $sag_rettigheder;
	global $overtid_50pct,$overtid_100pct;
	global $mentor,$regnaar;
	
	$a_sum=$afvis=$afvist=$afvist_pga=$afs=$afregnet=$afslut=$afsluttet=$ansatte=NULL;
	$beskyttet=NULL;
	$datoer=$decimaler=NULL;
	$fejltxt=$feriefra=$ferietil=$fordel_timer=$fordeling=NULL;
	$godkendt = NULL;
	$korsel=NULL;
	$listevalg=$listevalg_ny=$loen=$loen_art=$loen_datotext=$loen_tekst=$loendate=NULL;
	$master_nr=NULL;
	$opg_nr=$opgave_id=$oprettet=NULL;
	$retskur=array();
	$s_loendateFra=$s_loendateTil=$sag_ref=$soeg=$skur_1=$skur_2=$skur_sats1=$skur_sats2=NULL;
	$t50pct=$t100pct=$timeArt=$timer=$timersum=NULL;
	
	$id = $loen_nr = $opg_id = $sag_id = $telt_antal = 0;
	
	$a_pct = $fratraek = $hourTypes = array();

	if ($luk=if_isset($_POST['luk'])) {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
		exit;
	}	
	$id=if_isset($_GET['id']);
	transaktion('begin');
	if ($gem=if_isset($_POST['gem'])|| $afslut=if_isset($_POST['afslut']) || $afvis=if_isset($_POST['afvis'])) {
		# 20160830 ->
		$afs=if_isset($_POST['afs']);
		$gemt=if_isset($_POST['gemt']);
		if ($afslut)
		{
			$afs++;
			if ($afs<3)
			{
				$gem=$afslut;
				$afslut=NULL;
			}
		}
		elseif ($gem) $gemt++;

		# <- 20160830
		$id=if_isset($_POST['id'],0);
		$listevalg=(int)if_isset($_POST['listevalg'],0);
		$listevalg_ny=if_isset($_POST['listevalg_ny'],0);
		$op=if_isset($_POST['op']);
		$ned=if_isset($_POST['ned']);
		$op_25    = if_isset($_POST['op_25']);
		$ned_25   = if_isset($_POST['ned_25']);
		$op_30    = if_isset($_POST['op_30']);
		$ned_30   = if_isset($_POST['ned_30']);
		$op_40    = if_isset($_POST['op_40']);
		$ned_40   = if_isset($_POST['ned_40']);
		$op_60    = if_isset($_POST['op_60']);
		$ned_60   = if_isset($_POST['ned_60']);
		$op_70    = if_isset($_POST['op_70']);
		$ned_70   = if_isset($_POST['ned_70']);
		$op_100   = if_isset($_POST['op_100']);
		$ned_100  = if_isset($_POST['ned_100']);
		$op_160   = if_isset($_POST['op_160']);
		$ned_160  = if_isset($_POST['ned_160']);
		$op_30m   = if_isset($_POST['op_30m']);
		$ned_30m  = if_isset($_POST['ned_30m']);
		$pris_op  = if_isset($_POST['pris_op'],array());
		$pris_ned = if_isset($_POST['pris_ned'],array());
		$vare_id=if_isset($_POST['vare_id'],array());
		$vare_nr=if_isset($_POST['varenr'],array()); // indsat 20142803
		$vare_tekst=if_isset($_POST['vare_tekst']);
		$tr_id=if_isset($_POST['tr_id']);
		$tr_antal=if_isset($_POST['tr_antal']);
		$tr_pris=if_isset($_POST['tr_pris']);
		$telt_id=if_isset($_POST['telt_id']);
		$telt_antal=if_isset($_POST['telt_antal']);
		$telt_pris=if_isset($_POST['telt_pris'],0);
		$enhed_id=if_isset($_POST['enhed_id']);
		$loen_nr=if_isset($_POST['loen_nr']);
		$loen_art=if_isset($_POST['loen_art']);
		$loen_tekst=db_escape_string(if_isset($_POST['loen_tekst']));
		$loen_ansatte=if_isset($_POST['ansatte']);
		$loen_date=if_isset($_POST['loen_date']);
		$loen_fordeling=if_isset($_POST['loen_fordeling'],array());
		$loen_timer=if_isset($_POST['loen_timer'],array());
		$loen_50pct=if_isset($_POST['loen_50pct'],array());
		$loen_100pct=if_isset($_POST['loen_100pct'],array());
		$loen_loen=if_isset($_POST['loen_loen'],array());
		$skur1       = if_isset($_POST['skur1'],array());
		$skur2       = if_isset($_POST['skur2'],array());
		$skur_sats1  = if_isset($_POST['skur_sats1'],0);
		$skur_sats2  = if_isset($_POST['skur_sats2'],0);
		$loen_km     = if_isset($_POST['loen_km'],array());
		$km_sats     = if_isset($_POST['km_sats']);
		$km_fra      = if_isset($_POST['km_fra']);
		$loen_mentor = if_isset($_POST['loen_mentor'],array());
		$mentorRate  = if_isset($_POST['mentorRate'],0);
		$hvem=db_escape_string(if_isset($_POST['hvem']));
		$sag_nr=if_isset($_POST['sag_nr'])*1;
		$sag_id=if_isset($_POST['sag_id'])*1;
		$sag_ref=if_isset($_POST['sag_ref']);
		$opg_nr=if_isset($_POST['opg_nr'],0);
		$gl_opg_id=if_isset($_POST['gl_opg_id'],0);
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
		$a_stk=if_isset($_POST['a_stk'],array());
		$a_txt=if_isset($_POST['a_txt']);
		$a_pris=if_isset($_POST['a_pris']);
		$a_pct=if_isset($_POST['a_pct'],array());
		$feriefra=if_isset($_POST['feriefra']); // indsat 20140627
		$ferietil=if_isset($_POST['ferietil']); // indsat 20140627
		$hourType=if_isset($_POST['hourType']);
		if ($opg_id && !$opg_nr) {
			$r=db_fetch_array(db_select("select nr from opgaver where id = '$opg_id'",__FILE__ . " linje " . __LINE__));
			$opg_nr=$r['nr']*1;
		}

		if (($loen_art=='akk_afr' || $loen_art=='akkord') && $sag_nr) {
				$qtxt =  "select id,nummer from loen where (art='akk_afr' or art='akkord') ";
				$qtxt.=  "and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and afvist = '' and id != '$id'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && $r['id']) {
								$fejltxt = "Der eksisterer allerede en uafsluttet akkordseddel (nr: $r[nummer]) ";
								$fejltxt.= "for den ".$loendate." på sag nr: $sag_nr, opgave nr:$opg_nr!";
								$sag_nr='0';
#				$sag_id=0;
								$opg_nr=0;
				}
		}
		if ($loen_art=='akktimer' && $sag_nr) {
			$r=db_fetch_array(db_select("select id,nummer from loen where (art='akktimer' or art='akkord') and loendate='".usdate($loendato)."' and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and afvist = '' and (master_id='$id' or master_id='0' or master_id=NULL) and id != '$id'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				$fejltxt="Der eksisterer allerede en uafsluttet akkordtimeseddel (nr: $r[nummer]) for den ".$loendate." på sag nr: $sag_nr, opgave nr:$opg_nr!";
#				$sag_nr='0';
#				$sag_id=0;
				$opg_nr=0;
			}
		}
		if ($afslut=isset($_POST['afslut']) && $afslut) {
			$afsluttet=date("U");
			$afsluttet_af=$brugernavn;
		} else $afsluttet_af=NULL;
		for ($x=0;$x<count($medarb_nr);$x++) {
			$skur1[$x] = if_isset($skur1[$x],0);
			$skur2[$x] = if_isset($skur2[$x],0);
			if (($skur1[$x] || $skur2[$x]) && $loen_art!='akk_afr') { #20130226 + 20130322 ( and afvist<'1')
				$qtxt = "select * from loen where (art='akktimer' or art='akkord' or art='timer') and loendate='$loendate' ";
				$qtxt.= "and id < '$id' and (afvist<'1' or afvist is NULL) order by id";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					$a=explode(chr(9),$r['ansatte']);
					if (in_array($ansat_id[$x],$a)) {
						list($s1,$s2)=explode("|",$r['skur']);
						$sk1=explode(chr(9),$s1);
						$sk2=explode(chr(9),$s2);
							for ($i=0;$i<count($a);$i++) {
							if ($a[$i]==$ansat_id[$x]) {
								$ret_skur[$x]=NULL;
								if ($sk1[$i]||$sk2[$i]){
									$ret_skur[$x]="off";
									$fejltxt="Der er allerede skur d. ".dkdato($loendate)." for medarb.nr: $medarb_nr[$x] på seddel $r[nummer]";
									#print "<BODY onLoad=\"javascript:alert('$txt')\">";
									$skur1[$x]=NULL;
									$skur2[$x]=NULL;
								}
							}
						}
					}
				}
			}

			if (!isset($medarb_navn[$x]))$medarb_navn[$x]=NULL;
			$loen_timer[$x]     = usdecimal(if_isset($loen_timer[$x],0),6);
			$loen_50pct[$x]     = usdecimal(if_isset($loen_50pct[$x],0));
			$loen_100pct[$x]    = usdecimal(if_isset($loen_100pct[$x],0));
			$loen_km[$x]        = usdecimal(if_isset($loen_km[$x],0));
			$loen_mentor[$x]    = usdecimal(if_isset($loen_mentor[$x],0));
			$loen_date[$x]      = if_isset($loen_date[$x],date('d-m-Y'));
			$loen_fordeling[$x] = if_isset($loen_fordeling[$x],0);
			if ($loen_fordeling[$x]) $loen_fordeling[$x] = (float)$loen_fordeling[$x];
			if ($loen_date[$x]) $loen_datoer[$x]=usdate($loen_date[$x]);
			if ($loen_timer[$x]) $loen_timer[$x]=str_replace(",",".",$loen_timer[$x]);
			if ($loen_50pct[$x]) $loen_50pct[$x]=str_replace(",",".",$loen_50pct[$x]);
			if ($loen_100pct[$x]) $loen_100pct[$x]=str_replace(",",".",$loen_100pct[$x]);
			if ($skur1[$x]) {
				$skur1[$x]=$skur_sats1;
				$skur2[$x]=0;
			} elseif ($skur2[$x])	{
				$skur2[$x]=$skur_sats2;
				$skur1[$x]=0;
			} else {$skur1[$x]=0;$skur2[$x]=0;}
			if ($loen_km[$x]) $loen_km[$x]=str_replace(",",".",$loen_km[$x])*1;
			if 	(!$medarb_nr[$x] && !$medarb_navn[$x]) $ansat_id[$x]=0;
			if (is_numeric($medarb_nr[$x])) {
				$r=db_fetch_array(db_select("select id,trainee,startdate from ansatte where nummer='$medarb_nr[$x]'",__FILE__ . " linje " . __LINE__));
				$ansat_id[$x]=$r['id']*1;
				if ($r['trainee']) {
					if ($loen_art=='akk_afr') $loen_fordeling[$x]=tjek_fordeling($ansat_id[$x],$r['startdate'],$loen_datoer[$x]); #20130905
					else $loen_fordeling[$x]=tjek_fordeling($ansat_id[$x],$r['startdate'],$loendate);
				}	else $loen_fordeling[$x]=100;
				if ($ansat_id[$x]) {
					if($ansatte){
						$ansatte.=   chr(9).$ansat_id[$x];
						$loen.=      chr(9).$loen_loen[$x];
						$fordeling.= chr(9).$loen_fordeling[$x];
						$timer.=     chr(9).$loen_timer[$x];
						$t50pct.=    chr(9).$loen_50pct[$x];
						$t100pct.=   chr(9).$loen_100pct[$x];
						$skur_1.=    chr(9).$skur1[$x];
						$skur_2.=    chr(9).$skur2[$x];
						$mentor.=    chr(9).$loen_mentor[$x];
						$korsel.=    chr(9).$loen_km[$x];
						$datoer.=    chr(9).$loen_datoer[$x];
						$timeArt.=   chr(9).if_isset($hourType[$x],0);
					} else {
						$ansatte   = $ansat_id[$x];
						$loen      = $loen_loen[$x];
						$fordeling = $loen_fordeling[$x];
						$timer     = $loen_timer[$x];
						$t50pct    = $loen_50pct[$x];
						$t100pct   = $loen_100pct[$x];
						$skur_1    = $skur1[$x];
						$skur_2    = $skur2[$x];
						$mentor    = $loen_mentor[$x];
						$korsel    = $loen_km[$x];
						$datoer    = $loen_datoer[$x];
						$timeArt   = if_isset($hourType[$x],0);
					}
				}
			}
			if ($medarb_navn[$x] && !$ansat_id[$x]) {
				$medarb_navn[$x]=db_escape_string($medarb_navn[$x]);
				$r=db_fetch_array(db_select("select id from ansatte where navn='$medarb_navn[$x]'",__FILE__ . " linje " . __LINE__));
				$ansat_id[$x]=$r['id']*1;
			} else $ansat_id[$x]=0;
			if ($ansat_id[$x]) {
				($ansatte)?$ansatte.=chr(9).$ansat_id[$x]:$ansatte=$ansat_id[$x];
			}
		}
		$skur=$skur_1."|".$skur_2;
		$korsel.="|$km_sats|$km_fra";
		$qtxt = "select id,ref from SAGER where sagsnr='$sag_nr'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sag_id  = $r['id'];
			$sag_ref = $r['ref'];
		}	else {
			$sag_id  = 0;
			$sag_ref = NULL;
		}
		if (!$oprettet) $oprettet=date('U');
		#		$loendate=usdate($loendato);
		
		/* Validering af lønindtastning */ #20150623-1
		if (!$loendato || $loendato=="01-01-1970") {
			$loendato="01-01-1970";
			$loendate=usdate($loendato);
			$datotext_errortxt="<span style=\"color: red;\">Dato ikke udfyld</span>";
			$datotext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			//print "<BODY onLoad=\"javascript:alert('Dato ikke udfyld')\">"; // laves om til css-validering???
		} else {
			$datotext_errortxt=NULL;
			$datotext_error=NULL;
		}
		if (strstr($loen_art,'akk') && !$sag_nr) { // Er ikke sikker på at det er nødvendigt at have 'aconto,regulering,timer' med???
			$sagsnr_errortxt="<span style=\"color: red;\">Sagsnr ikke valgt</span>";
			$sagsnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			//print "<BODY onLoad=\"javascript:alert('Sagsnr ikke valgt')\">"; // laves om til css-validering???
		} else {
			$sagsnr_errortxt=NULL;
			$sagsnr_error=NULL;
		}
		if ((strstr($loen_art,'akk') || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer') && !$opg_nr) {
			$opgnr_errortxt="<span style=\"color: red;\">Opgave ikke valgt</span>";
			$opgnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} else {
			$opgnr_errortxt=NULL;
			$opgnr_error=NULL;
		}
		if (!$feriefra && $ferietil) {
			$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' er ikke valgt</span>";
			$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} elseif ($feriefra && !$ferietil) {
			$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Til' er ikke valgt</span>";
			$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} elseif (!$feriefra && !$ferietil) {
			$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' og 'Til' er ikke valgt</span>";
			$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		} else {
			$feriefratil_errortxt=NULL;
			$feriefra_error=NULL;
			$ferietil_error=NULL;
		}
		if(!$loen_tekst && ((strstr($loen_art,'akk')) || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer')) {
			$loentext_errortxt="<span style=\"color: red;\">Udført er ikke udfyldt</span>";
			$loentext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
			//print "<BODY onLoad=\"javascript:alert('Udført er ikke udfyldt')\">"; // laves o til css-validering??
		} else {
			$loentext_errortxt=NULL;
			$loentext_error=NULL;
		}
		if ($id) {
			if ($loen_art=='aconto' || $loen_art=='regulering') $sum=usdecimal($dksum);
			if ($loen_art != 'akk_afr') $datoer = ''; # 20240409
			$qtxt = "select afvist, afvist_af from loen where id='$id'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$afvist = $r['afvist'];
			$afvist_af = $r['afvist_af'];
			if ($afvist && $afvist_af) {
				alert("Denne seddel er afvist af $afvist_af");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
				exit;
			}
			if (!$afvis) {
				$qtxt = "update loen set art='$loen_art',kategori='$listevalg',nummer='$loen_nr',sag_id='$sag_id',opg_id='$opg_id',";
				$qtxt.= "sag_nr='$sag_nr',opg_nr='$opg_nr',oprettet='$oprettet',afsluttet='$afsluttet',afsluttet_af='$afsluttet_af',";
				$qtxt.= "afvist='$afvist',afvist_af='$afvist_af',datoer='$datoer',ansatte='$ansatte',fordeling='$fordeling',";
				$qtxt.= "loen='$loen',timer='$timer',t50pct='$t50pct',t100pct='$t100pct',skur='$skur',mentor='$mentor',";
				$qtxt.= "mentor_rate='$mentorRate',sum='$sum',loendate='$loendate',tekst='$loen_tekst',korsel='$korsel',";
				$qtxt.= "sag_ref='$sag_ref',feriefra='$feriefra',ferietil='$ferietil',hourType='$timeArt' where id='$id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($opg_id!=$gl_opg_id && $gl_opg_id) { #20131004
					$qtxt="update loen set master_id='0' where id='$id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			if ($afslut && $loen_art=='akk_afr') {
				$qtxt="update loen set afsluttet='$afsluttet',afsluttet_af='$afsluttet_af',master_id='$id' where master_id='$id' or (sag_id='$sag_id' and art='akktimer' and afsluttet='' and id != '$id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				for ($i=0;$i<count($ansat_id);$i++) {
					if ($loen_date[$i] && $r=db_fetch_array(db_select("select * from loen where loendate='".usdate($loen_date[$i])."' and art = 'akktimer' and sag_id='$sag_id' and opg_id='$opg_id' and (master_id is NULL or master_id='0')",__FILE__ . " linje " . __LINE__))) {
#						if ($i<1 || $loen_date[$i]!=$loen_date[$i-1]) { 20151215
							$t=explode(chr(9),$timer);
							$match=1;
							for ($n=0;$n<count($t);$n++) {
								if ($loen_timer[$n]!=$t[$n]) $match=0;
							}
							if ($match) {
								$qtxt="update loen set master_id='$id' where id='$r[id]'";
								db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
							}
#						}
					}
				}
#xit;
				transaktion("commit");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
			} elseif ($afvis && $afvist_pga) {
				$afvist_af=$brugernavn;
				$afvist=date('U');
				$afvist_pga=db_escape_string($afvist_pga);
#				db_modify("update loen set afsluttet='',afsluttet_af='' where master_id='$id'",__FILE__ . " linje " . __LINE__);
				$qtxt = "update loen set sum='$sum',afvist='$afvist',afvist_af='$afvist_af',";
				$qtxt.= "afvist_pga='$afvist_pga',godkendt='' where id='$id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$afvis_id=$id;
				$id=0;
			#				exit;
			}
		}
		if (!$id) {
			if (!$afvist) {
				$oprettet_af=$brugernavn;
				$oprettet=date('U');
				$r=db_fetch_array(db_select("select max(nummer) as nummer from loen",__FILE__ . " linje " . __LINE__));
				$loen_nr=$r['nummer']+1;
			} else { #20131004-2
				$qtxt="select skur from loen where id='$afvis_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$skur=$r['skur'];
			} // Har indsat 'afvist_pga' i insert, så man kan se årsagen til afvisningen på den nye seddel #20161031
			$qtxt="insert into loen (art,kategori,nummer,hvem,sag_nr,sag_id,oprettet,afsluttet,godkendt,afregnet,oprettet_af,ansatte,datoer,fordeling,loen,timer,t50pct,t100pct,mentor,skur,sum,loendate,tekst,korsel,opg_id,opg_nr,sag_ref,afvist,afvist_af,afvist_pga,feriefra,ferietil) values ('$loen_art','$listevalg','$loen_nr','','$sag_nr','$sag_id','$oprettet','','','','$oprettet_af','$ansatte','$datoer','$fordeling','$loen','$timer','$t50pct','$t100pct','$mentor','$skur','$sum','$loendate','$loen_tekst','$korsel','$opg_id','$opg_nr','$sag_ref','','','$afvist_pga','$feriefra','$ferietil')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select max(id) as id from loen where nummer='$loen_nr'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
			if ($afvis && $afvist_pga) db_modify("update loen set afsluttet='',afsluttet_af='',master_id='0' where master_id='$afvis_id'",__FILE__ . " linje " . __LINE__); #20130531
		}
		if (($loen_art=='akk_afr' || $loen_art=='akkord')) {
			$akksum=0;
			$tr_antal   = (float)str_replace(",",".",$tr_antal);
			$telt_antal = (float)str_replace(",",".",$telt_antal);
		if ($tr_id) {
				$qtxt = "update loen_enheder set op='$tr_antal',pris_op='$tr_pris' where id='$tr_id'";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} elseif ($tr_antal) {
				$tmp=$listevalg."|Transport";
				$qtxt = "insert into loen_enheder ";
				$qtxt.= "(loen_id,vare_id,op,ned,op_25,ned_25,op_30,ned_30,op_40,ned_40,op_60,ned_60,op_70,ned_70,";
				$qtxt.= "op_100,ned_100,op_160,ned_160,op_30m,ned_30m,pris_op   ,pris_ned,tekst,procent) values ";
				$qtxt.= "('$id','-1','$tr_antal','0','0','0','0','0','0','0','0','0','0','0',";
				$qtxt.= "'0','0','0','0','0','0','$tr_pris','0','$tmp','0')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		if ($listevalg=='7' || ($db != 'stillads_5' && $listevalg=='11') || ($db == 'stillads_5' && $listevalg=='13')) {
				if ($telt_id && $telt_antal) {
					$qtxt = "update loen_enheder set op='$telt_antal',pris_op='$telt_pris' where id='$telt_id'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} elseif ($telt_antal && $telt_pris) { #20150602
					$tmp=$listevalg."|Telt";
					$qtxt = "insert into loen_enheder ";
					$qtxt.= "(loen_id,vare_id,op,ned,op_25,ned_25,op_30,ned_30,op_40,ned_40,op_60,ned_60,op_70,ned_70,";
					$qtxt.= "op_100,ned_100,op_160,ned_160,op_30m,ned_30m,pris_op,pris_ned,tekst,procent) values ";
					$qtxt.= "('$id','-2','$telt_antal','0','0','0','0','0','0','0','0','0','0','0',";
					$qtxt.= "'0','0','0','0','0','0','$telt_pris','0','$tmp','0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			for ($x=0;$x<count($vare_id);$x++) {
				$op[$x]      = (int)str_replace(",",".",if_isset($op[$x],0));
				$ned[$x]     = (int)str_replace(",",".",if_isset($ned[$x],0));
				$op_25[$x]   = (int)str_replace(",",".",if_isset($op_25[$x],0));
				$ned_25[$x]  = (int)str_replace(",",".",if_isset($ned_25[$x],0));
				$op_30[$x]   = (int)str_replace(",",".",if_isset($op_30[$x],0));
				$ned_30[$x]  = (int)str_replace(",",".",if_isset($ned_30[$x],0));
				$op_40[$x]   = (int)str_replace(",",".",if_isset($op_40[$x],0));
				$ned_40[$x]  = (int)str_replace(",",".",if_isset($ned_40[$x],0));
				$op_60[$x]   = (int)str_replace(",",".",if_isset($op_60[$x],0));
				$ned_60[$x]  = (int)str_replace(",",".",if_isset($ned_60[$x],0));
				$op_70[$x]   = (int)str_replace(",",".",if_isset($op_70[$x],0));
				$ned_70[$x]  = (int)str_replace(",",".",if_isset($ned_70[$x],0));
				$op_100[$x]  = (int)str_replace(",",".",if_isset($op_100[$x],0));
				$ned_100[$x] = (int)str_replace(",",".",if_isset($ned_100[$x],0));
				$op_160[$x]  = (int)str_replace(",",".",if_isset($op_160[$x],0));
				$ned_160[$x] = (int)str_replace(",",".",if_isset($ned_160[$x],0));
				$op_30m[$x]  = (int)str_replace(",",".",if_isset($op_30m[$x],0));
				$ned_30m[$x] = (int)str_replace(",",".",if_isset($ned_30m[$x],0));

				
#				$op[$x]*=1;$ned[$x]*=1;$op_25[$x]*=1;$ned_25[$x]*=1;$op_40[$x]*=1;$ned_40[$x]*=1;$op_60[$x]*=1;$ned_60[$x]*=1;$op_30m[$x]*=1;$ned_30m[$x]*=1;$pris_op[$x]*=1;$pris_ned[$x]*=1;
				$pris_op[$x]  = afrund($pris_op[$x],2);
				$pris_ned[$x]  = afrund($pris_ned[$x],2);
				$linjesum[$x] = $op[$x]      * $pris_op[$x];
				$linjesum[$x]+= $op_25[$x]   * $pris_op[$x]*0.25;
				$linjesum[$x]+= $op_30[$x]   * $pris_op[$x]*0.30;
				$linjesum[$x]+= $op_40[$x]   * $pris_op[$x]*0.4;
				$linjesum[$x]+= $op_60[$x]   * $pris_op[$x]*0.6;
				$linjesum[$x]+= $op_70[$x]   * $pris_op[$x]*0.70;
				$linjesum[$x]+= $op_100[$x]  * $pris_op[$x]*1;
				$linjesum[$x]+= $op_160[$x]  * $pris_op[$x]*1.6;
				$linjesum[$x]+= $ned[$x]     * $pris_ned[$x];
				$linjesum[$x]+= $ned_25[$x]  * $pris_ned[$x]*0.25;
				$linjesum[$x]+= $ned_30[$x]  * $pris_ned[$x]*0.30;
				$linjesum[$x]+= $ned_40[$x]  * $pris_ned[$x]*0.4;
				$linjesum[$x]+= $ned_60[$x]  * $pris_ned[$x]*0.6;
				$linjesum[$x]+= $ned_70[$x]  * $pris_ned[$x]*0.70;
				$linjesum[$x]+= $ned_100[$x] * $pris_ned[$x]*1;
				$linjesum[$x]+= $ned_160[$x] * $pris_ned[$x]*1.6;
				$akksum+=$linjesum[$x];
				if (isset($enhed_id[$x]) && $enhed_id[$x] && !$afvist) {
					if ($op[$x]||$ned[$x]) {
						$qtxt = "update loen_enheder set ";
						$qtxt.= "op='$op[$x]',ned='$ned[$x]',op_25='$op_25[$x]',ned_25='$ned_25[$x]',";
						$qtxt.= "op_30='$op_30[$x]',ned_30='$ned_30[$x]',op_40='$op_40[$x]',ned_40='$ned_40[$x]',";
						$qtxt.= "op_60='$op_60[$x]',ned_60='$ned_60[$x]',op_70='$op_70[$x]',ned_70='$ned_70[$x]',";
						$qtxt.= "op_100='$op_100[$x]',ned_100='$ned_100[$x]',op_160='$op_160[$x]',ned_160='$ned_160[$x]',";
						$qtxt.= "op_30m='$op_30m[$x]',ned_30m='$ned_30m[$x]',pris_op='$pris_op[$x]',";
						$qtxt.= "pris_ned='$pris_ned[$x]',tekst='$vare_tekst[$x]',procent='0' where id='$enhed_id[$x]'";
					}	else $qtxt = "delete from loen_enheder where id='$enhed_id[$x]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} elseif (($op[$x] || $ned[$x]) && (!$afvist || $afvist_pga)) {
					if (is_numeric($vare_id[$x])) {
						$qtxt = "insert into loen_enheder ";
						$qtxt.= "(loen_id,vare_id,op,ned,op_25,ned_25,op_30,ned_30,op_40,ned_40,op_60,ned_60,op_70,ned_70,";
						$qtxt.= "op_100,ned_100,op_160,ned_160,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr) values ";
						$qtxt.= "('$id','$vare_id[$x]','$op[$x]','$ned[$x]','$op_25[$x]','$ned_25[$x]','$op_30[$x]','$ned_30[$x]',";
						$qtxt.= "'$op_40[$x]','$ned_40[$x]','$op_60[$x]','$ned_60[$x]','$op_70[$x]','$ned_70[$x]',";
						$qtxt.= "'$op_100[$x]','$ned_100[$x]','$op_160[$x]','$ned_160[$x]','$op_30m[$x]','$ned_30m[$x]',";
						$qtxt.= "'$pris_op[$x]','$pris_ned[$x]','$vare_tekst[$x]','0','$vare_nr[$x]')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			}
				if ($afvist && $afvis_id && $id) {
					$qtxt = "select * from loen_enheder where loen_id='$afvis_id' and vare_id < '0'";
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while($r=db_fetch_array($q)){
						$qtxt = "insert into loen_enheder ";
						$qtxt.= "(loen_id,vare_id,op,ned,op_25,ned_25,op_30,ned_30,op_40,ned_40,op_60,ned_60,op_70,ned_70,";
						$qtxt.= "op_100,ned_100,op_160,ned_160,op_30m,ned_30m,pris_op,pris_ned,tekst,procent,varenr) values ";
						$qtxt.= "('$id','$r[vare_id]','". (float)$r['op'] ."','". (float)$r['ned'] ."',";
						$qtxt.= "'". (float)$r['op_25'] ."','". (float)$r['ned_25'] ."','". (float)$r['op_30'] ."',";
						$qtxt.= "'". (float)$r['ned_30'] ."','". (float)$r['op_40'] ."','". (float)$r['ned_40'] ."',";
						$qtxt.= "'". (float)$r['op_60'] ."','". (float)$r['ned_60'] ."','". (float)$r['op_70'] ."',";
						$qtxt.= "'". (float)$r['ned_70'] ."','". (float)$r['op_100'] ."','". (float)$r['ned_100'] ."',";
						$qtxt.= "'". (float)$r['op_160'] ."','". (float)$r['ned_160'] ."','". (float)$r['op_30m'] ."',";
						$qtxt.= "'". (float)$r['ned_30m'] ."','". (float)$r['pris_op'] ."','". (float)$r['pris_ned'] ."',";
						$qtxt.= "'$r[tekst]','$r[procent]','$r[varenr]')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				}
			#			db_modify("update loen set sum='$akksum' where id='$id'",__FILE__ . " linje " . __LINE__);
			for ($x=0;$x<count($a_stk);$x++) {
				$a_stk[$x]=usdecimal($a_stk[$x])*1;
				$a_pris[$x]=usdecimal($a_pris[$x])*1;
				$a_txt[$x]=db_escape_string(trim($a_txt[$x]));
				if (!isset($a_pct[$x]) || $a_pct[$x]=='') $a_pct[$x]=100;
				else $a_pct[$x]=usdecimal($a_pct[$x]);
				$akksum+=$a_stk[$x]*$a_pris[$x];
				if (isset($a_id[$x]) && $a_id[$x] && !$afvist) {
					if ($a_stk[$x]) {
						db_modify("update loen_enheder set op='$a_stk[$x]',ned='0',pris_op='$a_pris[$x]',pris_ned='0',tekst='$a_txt[$x]',procent='$a_pct[$x]' where id='$a_id[$x]'",__FILE__ . " linje " . __LINE__);
					} else {
						db_modify("delete from loen_enheder where id='$a_id[$x]'",__FILE__ . " linje " . __LINE__);
					}
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
	} elseif ($godkend=if_isset($_POST['godkend'])) {
		$id=if_isset($_POST['id']);
		$sag_id=if_isset($_POST['sag_id'])*1;
		$opg_id=if_isset($_POST['opg_id'])*1;
		$godkendt=date("U");
		$godkendt_af=$brugernavn;
		if ($id) {
			db_modify("update loen set godkendt='$godkendt',godkendt_af='$godkendt_af' where id='$id'",__FILE__ . " linje " . __LINE__);
			$qtxt="update loen set godkendt='$godkendt',godkendt_af='$godkendt_af' ";
			$qtxt.="where master_id='$id' and sag_id='$sag_id' and opg_id ='$opg_id' and afvist=''";# 20170524 Tilføjet  "and  afvist=''"
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			db_modify("update loen set master_id='0' where master_id='$id' and (sag_id!='$sag_id' or opg_id !='$opg_id')",__FILE__ . " linje " . __LINE__);
			transaktion('commit');
			print "<BODY onLoad=\"javascript:alert('Sedlen er godkendt!')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
		}		
	} elseif ($slet=if_isset($_POST['slet'])) {
		if ($id=if_isset($_POST['id'])) {
			db_modify("delete from loen where id='$id'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from loen_enheder where loen_id='$id'",__FILE__ . " linje " . __LINE__);
		}
		transaktion('commit');
		print "<BODY onLoad=\"javascript:alert('Sedlen er slettet!')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
	}
#xit;
	transaktion('commit');
	$id = (int)$id;
	$ansat_id=array();
	$loen_km=array();
	if ($r=db_fetch_array(db_select("select * from loen where id = '$id'",__FILE__ . " linje " . __LINE__))) {
		$loen_nr    = (int)$r['nummer'];
		$loen_tekst = $r['tekst'];
		$hvem       = $r['hvem'];
		$sag_id     = (int)$r['sag_id'];
		$opg_id     = (int)$r['opg_id'];
		$sag_ref    = $r['sag_ref'];
		$loendate=$r['loendate'];
		$oprettet=$r['oprettet'];
		$afsluttet=$r['afsluttet'];
		$godkendt=$r['godkendt'];
		$afvist=$r['afvist'];
		$oprettet_af=$r['oprettet_af'];
		$afsluttet_af=$r['afsluttet_af'];
		$godkendt_af=$r['godkendt_af'];
		$afvist_af=$r['afvist_af'];
		$afvist_pga=$r['afvist_pga'];
		$listevalg=$r['kategori'];
		$loen=$r['loen'];
		$sum=$r['sum'];
		$master_id=$r['master_id'];
		$loen_art=$r['art'];
		$feriefra=$r['feriefra']; // indsat 20140627
		$ferietil=$r['ferietil']; // indsat 20140627
		if ($loen_art=='akk_afr' && !$afsluttet) {
			$ansatte   = NULL;
			$datoer    = NULL;
			$fordeling = $r['fordeling'];
			$loen_fordeling = explode(chr(9),$fordeling);
			$timer     = NULL;
			$t50pct    = NULL;
			$t100pct   = NULL;
			$loen_dato = NULL;
			$skur1     = array();
			$skur2     = array();
			$korsel    = NULL;
#			$fordeling = NULL;
		} else { 
			$ansatte   = $r['ansatte'];
			$datoer    = $r['datoer'];
			$fordeling = $r['fordeling'];
			$timer     = $r['timer'];
			$t50pct    = $r['t50pct'];
			$t100pct   = $r['t100pct'];
			$ht        = $r['hourtype'];
			$mentor    = $r['mentor'];
			$mentorRate= $r['mentor_rate'];
			list($skur1,$skur2)        = explode("|",$r['skur']);
			list($km,$km_sats,$km_fra) = explode("|",$r['korsel']);
		if ($ansatte) {
				$ansat_id       = explode(chr(9),$ansatte);
				$loen_fordeling = explode(chr(9),$fordeling);
				$loen_date      = explode(chr(9),$datoer);
				$loen_loen      = explode(chr(9),$loen);
				$loen_timer     = explode(chr(9),$timer);
				$loen_50pct     = explode(chr(9),$t50pct);
				$loen_100pct    = explode(chr(9),$t100pct);
				$loen_skur1     = explode(chr(9),$skur1);
				$loen_skur2     = explode(chr(9),$skur2);
				$loen_mentor    = explode(chr(9),$mentor);
				$loen_km        = explode(chr(9),$km);
				$loen_timeArt   = explode(chr(9),$ht);
			}
		}
	}
	$qtxt = "select var_value from settings where var_name = 'hideSalary'";
	if ($p=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))	$hideSalary = $p['var_value'];
	else $hideSalary = NULL;

	if ($loen_art=='akkord' || $loen_art=='akktimer'){ #Hvis masteren er afvist og master_id ikke er fjernet fjernes master_id så der kan opsamles igen
		if ($master_id) {
			if ($r2=db_fetch_array(db_select("select nummer from loen where id='$master_id' and sag_id='$sag_id' and opg_id='$opg_id'",__FILE__ . " linje " . __LINE__))) {
				$master_nr=$r2['nummer'];
			}	else {
				db_modify("update loen set master_id='0' where id='$id'",__FILE__ . " linje " . __LINE__); #20161012 Fjernet godkendt='' da udbetalte sedler bliver afregnet igen.
				$master_id=NULL;
				$master_nr=NULL;
#				$godkendt=NULL; #20161012
			}
		}
	}
	if (!$afsluttet) {
		$r=db_fetch_array(db_select("select * from grupper where art='loen'",__FILE__ . " linje " . __LINE__));
		list($skur_sats1,$skur_sats2)=explode(chr(9),$r['box1']);
		$sygdom_sats=$r['box2'];
		$skole_sats=$r['box3'];
		$plads_sats=$r['box7'];
		list($traineemdr,$traineepct)=explode(chr(9),$r['box5']);
		list($km_sats,$km_fra)=explode(chr(9),$r['box6']);
		//list($overtid_50pct,$overtid_100pct)=explode(chr(9),$r['box8']);
		$qtxt = "select var_value from settings where var_name = 'mentorRate'";
		if ($p=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))	$mentorRate = (float)$p['var_value'];
		else $mentorRate = 0;
	}

	if ($loen_art=='akk_afr' && $sag_id && !$afsluttet) {
	## 20130301 Finder ikke afvist selvom afvist er '' - derfor dette.	
#		if ($opg_id) $qtxt="select * from loen where sag_id = '$sag_id' and opg_id='$opg_id' and art='akktimer' and afvist='' and (master_id='$id' or master_id=0 or master_id is NULL) and id != '$id' order by loendate";
#		else $qtxt="select * from loen where sag_id = '$sag_id' and kategori = '$listevalg' and art='akktimer' and afsluttet='' and afvist='' and  and id != '$id' order by loendate";
		#20131003 tilføjet and opg_id='$opg_id'
		$qtxt="select * from loen where sag_id = '$sag_id' and opg_id='$opg_id' and art='akktimer' and id != '$id' and (master_id='$id' or master_id='0' or master_id is NULL) order by loendate";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$y;
		while ($r=db_fetch_array($q)) {
			if (!trim($r['afvist']) and ((!trim($r['afsluttet']) and $r['kategori']==$listevalg) || $r['opg_id']==$opg_id)) {	## 20130301 Query finder ikke afvist selvom afvist er '' - derfor dette.	
# 			if (!trim($r['afvist']) and !trim($r['afsluttet']) and $r['opg_id']==$opg_id)) {	## 20130301 Query finder ikke afvist selvom afvist er '' - derfor dette.	
				if ($ansatte){ # 20141103 
					$ansatte.=   chr(9).$r['ansatte'];
					$fordeling.= chr(9).$r['fordeling'];
					$loen.=      chr(9).$r['loen'];
					$timer.=     chr(9).$r['timer'];
					$t50pct.=    chr(9).$r['t50pct'];
					$t100pct.=   chr(9).$r['t100pct'];
					$mentor.=    chr(9).$r['mentor'];
					list($s1,$s2) = explode("|",$r['skur']);
					$skur1.= chr(9).$s1;
					$skur2.= chr(9).$s2;
					list($k1,$km_sats,$km_fra)=explode("|",$r['korsel']);
					$km.= chr(9).$k1;
				} else {
					$ansatte   = $r['ansatte'];
					$fordeling = $r['fordeling'];
					$loen      = $r['loen'];
					$timer     = $r['timer'];
					$t50pct    = $r['t50pct'];
					$t100pct   = $r['t100pct'];
					$mentor    = $r['mentor'];
					list($s1,$s2) = explode("|",$r['skur']);
					$skur1 = $s1;
					$skur2 = $s2;
					list($k1,$km_sats,$km_fra) = explode("|",$r['korsel']);
					$km = $k1;
				}
				for($x=0;$x<=substr_count($r['ansatte'],chr(9));$x++) ($ldate)?$ldate.=chr(9).$r['loendate']:$ldate=$r['loendate'];
				$tmp=array(); #20131003 + næste 4 linjer
				$tmp=explode(chr(9),$r['ansatte']);
				for($x=0;$x<count($tmp);$x++) { 
					($akk_nr)?$akk_nr.=chr(9).$r['nummer']:$akk_nr=$r['nummer'];
				}

			}
			#			$tmp=	
		}
		if ($ansatte) {
			$akkord_nr=explode(chr(9),$akk_nr); #20131003
			$ansat_id=explode(chr(9),$ansatte);
			$loen_fordeling=explode(chr(9),$fordeling);
			$loen_loen=explode(chr(9),$loen);
			$loen_timer=explode(chr(9),$timer);
			$loen_50pct=explode(chr(9),$t50pct);
			$loen_100pct=explode(chr(9),$t100pct);
			$loen_date=explode(chr(9),$ldate);
			$loen_skur1=explode(chr(9),$skur1);
			$loen_skur2=explode(chr(9),$skur2);
			$loen_mentor=explode(chr(9),$mentor);
			$loen_km=explode(chr(9),$km);
		}
	}
	$x=0;
	$a_id=array();$a_vare_id=array();$a_stk=array();$a_txt=array();$a_pris=array();$a_pct=array();
	$qtxt="SELECT * FROM loen_enheder WHERE loen_id = '$id' and vare_id = '0'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$a_id[$x]=$r['id'];
		$a_vare_id[$x]=$r['vare_id'];
		$a_stk[$x]=$r['op']*1;
		$a_txt[$x]=$r['tekst'];
		$a_pris[$x]=$r['pris_op']*1;
		$a_pct[$x]=$r['procent']*1;
		$x++;
	}
	if ($sag_id) {
		$x=0;
		if ($afsluttet) $qtxt = "SELECT * FROM opgaver where id = '$opg_id'"; #20240617
		elseif ($opg_id) {
			$qtxt = "SELECT * FROM opgaver where id = '$opg_id' OR ";
			$qtxt.= "(status != 'Ordrebekræftelse' AND status != 'Tilbud' ";
			$qtxt.= "AND status != 'Afsluttet' AND assign_to = 'sager' AND assign_id = '$sag_id') ORDER BY nr";
		} else {
			$qtxt = "SELECT * FROM opgaver WHERE status != 'Ordrebekræftelse' AND status != 'Tilbud' ";
			$qtxt.= "AND status != 'Afsluttet' AND assign_to = 'sager' AND assign_id = '$sag_id' ORDER BY nr";
		}
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$opgave_id[$x]=$r['id'];
			$opgave_nr[$x]=$r['nr'];
			$opgave_beskrivelse[$x]=$r['beskrivelse'];
			$x++;
		}
	}
	$aa_sum=0;
	$aa_v_id=array();
	$x=0;
	if ($loen_art=='akk_afr' || $loen_art=='akkord') {
		$q=db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (!in_array($r['vare_id'],$aa_v_id) || $r['vare_id']<0) { #20130607 
				if ($r['vare_id']) $aa_v_id[$x]=$r['vare_id'];
				$aa_sum+=($r['op']*$r['pris_op']);
				$aa_sum+=($r['op_25']*$r['pris_op']*0.25);
				$aa_sum+=($r['op_30']*$r['pris_op']*0.30);
				$aa_sum+=($r['op_40']*$r['pris_op']*0.4);
				$aa_sum+=($r['op_60']*$r['pris_op']*0.6);
				$aa_sum+=($r['op_70']*$r['pris_op']*0.70);
				$aa_sum+=($r['op_100']*$r['pris_op']*1);
				$aa_sum+=($r['op_160']*$r['pris_op']*1.6);
				$aa_sum+=($r['op_30m']*$r['pris_op']*0.1);
				$aa_sum+=($r['ned']*$r['pris_ned']);
				$aa_sum+=($r['ned_25']*$r['pris_ned']*0.25);
				$aa_sum+=($r['ned_30']*$r['pris_ned']*0.30);
				$aa_sum+=($r['ned_40']*$r['pris_ned']*0.4);
				$aa_sum+=($r['ned_60']*$r['pris_ned']*0.6);
				$aa_sum+=($r['ned_70']*$r['pris_ned']*0.70);
				$aa_sum+=($r['ned_100']*$r['pris_ned']*1);
				$aa_sum+=($r['ned_160']*$r['pris_ned']*1.6);
				$aa_sum+=($r['ned_30m']*$r['pris_ned']*0.1);
				$x++;
			} else db_modify("delete from loen_enheder WHERE id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			
		}
	}
	$aa_sum80=$aa_sum*0.8;
	$aa_sum20=$aa_sum*0.2;
	$qtxt = "select sagsnr,udf_addr1 from sager where id = '$sag_id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$sag_nr=$r['sagsnr'];
		$sag_addr=$r['udf_addr1'];
	} else {
		$sag_nr   = 0;
		$sag_addr = NULL;
	}
	
	$x=0;
	$qtxt="select * from settings where var_grp='casePayment'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$hourTypes[$x]=$r['var_name'];
		$hourDescription[$x]=$r['var_description'];
		$hourValue[$x]=$r['var_value'];
		$x++;
	}


	for ($x=0;$x<count($ansat_id);$x++) {
		$ansat_id[$x]*=1;
		$r=db_fetch_array(db_select("select * from ansatte where id = '$ansat_id[$x]'",__FILE__ . " linje " . __LINE__));
		$medarb_nr[$x]=$r['nummer'];
		$medarb_navn[$x]=$r['navn'];
		$medarb_trainee[$x]=$r['trainee'];
		$medarb_startdate[$x]=$r['startdate'];
		$medarb_loen[$x]=str_replace(",",".",$r['loen'])*1;
		$medarb_extraloen[$x]=str_replace(",",".",$r['extraloen'])*1;
	}
		
	($afsluttet || $godkendt)?$readonly="readonly=\"readonly\"":$readonly=NULL;
	($afsluttet)?$status="Afventer godk.":$status="Under indtast.";
	if($godkendt)$status="Godkendt";
	if($afvist)$status="Afvist";
	
	$y=0;
#	$q=db_select("select id,kodenr,art,box1 from grupper where art ='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
#	while ($r=db_fetch_array($q)) {
#		$y++;
#		$cat_id[$y]=$r['kodenr'];
#		$cat_navn[$y]=$r['box1'];
#	}
	if (!$regnaar) { # 20240409
		$qtxt = "select max(kodenr) as kodenr from grupper where art = 'RA'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$regnaar = $r['kodenr'];
	}
	$qtxt = "select id,kodenr,beskrivelse from grupper where ";
	$qtxt.= "art ='VG' and box10='on' and fiscal_year = '$regnaar' order by beskrivelse";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$y]=$r['kodenr'];
		$cat_navn[$y]=$r['beskrivelse'];
		$y++;
	}

	$antal_cat=$y;
	$datotext_error = $datotext_errortxt = NULL;
	if($loendate=='1970-01-01') { 
		$loendate='';
		$loendato='';
		$datotext_errortxt="<span style=\"color: red;\">Dato ikke udfyld</span>";
		$datotext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else { 
/*
		$weekdays = array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday');
		$ugedage  = array('Mandag','Tirsdag','Onsdag','Torsdag','Fredag','Lørdag','Søndag');
		$months   = array('January','February','March','May','June', 'July','October');
		$maaneder = array('Januar','Februar','Marts','Maj','Juni', 'Juli','Oktober');
*/
		$loendato=dkdato($loendate);
		setlocale(LC_TIME, 'da_DK.UTF-8');
#		setlocale(LC_ALL, );
		if ($loendate==NULL) {
			$loen_datotext=NULL;
		} else {

			$fmt = new IntlDateFormatter(
				'da_DK',
				IntlDateFormatter::FULL,
				IntlDateFormatter::NONE,
				'Europe/Copenhagen'
			);
			$fmt->setPattern('EEEE d. MMMM y');
			$loen_datotext = $fmt->format(new DateTime($loendate));
			if ($db_encode=='UTF8') $loen_datotext=mb_convert_encoding($loen_datotext, 'UTF-8', 'ISO-8859-1'); 

			$dato = date('d-m-y');
			$tid = date('H:i');
		}
	}
	/* Validering når lønseddel indlæses */ #20150623-1
	if (strstr($loen_art,'akk') && !$sag_nr) { 
		$sagsnr_errortxt="<span style=\"color: red;\">Sagsnr ikke valgt</span>";
		$sagsnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else {
		$sagsnr_errortxt=NULL;
		$sagsnr_error=NULL;
	}
	if ((strstr($loen_art,'akk') || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer') && !$opg_id) {
		$opgnr_errortxt="<span style=\"color: red;\">Opgave ikke valgt</span>";
		$opgnr_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else {
		$opgnr_errortxt=NULL;
		$opgnr_error=NULL;
	}
	if (!$feriefra && $ferietil) {
		$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' er ikke valgt</span>";
		$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} elseif ($feriefra && !$ferietil) {
		$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Til' er ikke valgt</span>";
		$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} elseif (!$feriefra && !$ferietil) {
		$feriefratil_errortxt="<span style=\"color: red;\">Ferie 'Fra' og 'Til' er ikke valgt</span>";
		$feriefra_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
		$ferietil_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else {
		$feriefratil_errortxt=NULL;
		$feriefra_error=NULL;
		$ferietil_error=NULL;
	}
	if(!$loen_tekst && ((strstr($loen_art,'akk')) || $loen_art=='aconto' || $loen_art=='regulering' || $loen_art=='timer')) {
		$loentext_errortxt="<span style=\"color: red;\">Udført er ikke udfyldt</span>";
		$loentext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;width: 560px;\"";
		//print "<BODY onLoad=\"javascript:alert('Udført er ikke udfyldt')\">"; // laves o til css-validering??
	} else {
		$loentext_errortxt=NULL;
		$loentext_error=NULL;
	}
	
#######################################
	print "<div id=\"printableArea\">\n";
	print "<form name=\"loen\" action=\"loen.php?funktion=ret_loen\" method=\"post\">
		<input type=\"hidden\" name=\"id\" value=\"$id\">
		<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">
		<input type=\"hidden\" name=\"opg_id\" value=\"$opg_id\">
		<input type=\"hidden\" name=\"gl_opg_id\" value=\"$opg_id\">
		<input type=\"hidden\" name=\"sag_nr\" value=\"$sag_nr\">
		<input type=\"hidden\" name=\"sag_ref\" value=\"$sag_ref\">
		<input type=\"hidden\" name=\"loen_nr\" value=\"$loen_nr\">
		<input type=\"hidden\" name=\"oprettet\" value=\"$oprettet\">
		<input type=\"hidden\" name=\"afsluttet\" value=\"$afsluttet\">
		<input type=\"hidden\" name=\"godkendt\" value=\"$godkendt\">
		<input type=\"hidden\" name=\"loen_tekst\" value=\"$loen_tekst\">"; #20150618
		if (!$afsluttet) {
			print "<input type=\"hidden\" name=\"skur_sats1\" value=\"$skur_sats1\">
			<input type=\"hidden\" name=\"skur_sats2\" value=\"$skur_sats2\">
			<input type=\"hidden\" name=\"km_sats\" value=\"$km_sats\">
			<input type=\"hidden\" name=\"km_fra\" value=\"$km_fra\">
			<input type=\"hidden\" name=\"mentorRate\" value=\"$mentorRate\">";
		}
		for($x=0;$x<count($a_id);$x++) print "<input type=\"hidden\" name=\"a_id[$x]\" value=\"$a_id[$x]\">";
		for ($x=0;$x<count($ansat_id);$x++) {
			print "<input type=\"hidden\" name=\"ansat_id[$x]\" value=\"$ansat_id[$x]\">"; 
			# print "<input type=\"hidden\" name=\"loen_id[$x]\" value=\"$loen_id[$x]\">"; 
		}
		if ($db=='stillads_14' || $db=='udvikling_2') {
			$loenart_1=array('akkord','timer','torretid','plads','skole','sygdom','barn_syg','ferie');
			$loenart_2=array('Timepris Akkord','Timeløn','Timeløn Tørretid','Pladsarbejde','Skoleophold','Sygdom','Barn syg','Ferie');
		} else {
			$loenart_1=array('aconto','akktimer','akk_afr','akkord','timer','plads','skole','sygdom','barn_syg','ferie');
			$loenart_2=array('Aconto','Dyrtid','Akkord afregning','Akkord med dyrtid','Timeløn','Pladsarbejde','Skoleophold','Sygdom','Barn syg','Ferie');
		}
		print "<div class=\"content\">
			<h3>Lønindtastning</h3>
				<div class=\"contentA\" style=\"#width: 758px;\">
					<div class=\"row\">
						<div class=\"leftSmall\">Dato: </div>
						<div class=\"rightSmall\"><input name=\"loendato\" id=\"datepicker\" type=\"text\" $readonly class=\"textMedium printBorderNone\" $datotext_error value=\"$loendato\"/></div><div class=\"rightNoWidth\"><p>$loen_datotext $datotext_errortxt</p></div> 
						<div class=\"clear\"></div>
					</div>
					<div class=\"row\">
						<div class=\"leftSmall\">Løntype:</div>
						<div class=\"rightLarge\">
							<select name=\"loen_art\" $readonly class=\"loen_art printSelect2\" style=\"width:100%;\">";
								for ($l=0;$l<count($loenart_1);$l++){
									if ($loen_art==$loenart_1[$l]) print "<option value=\"$loenart_1[$l]\">$loenart_2[$l]</option>";
								}
								for ($l=0;$l<count($loenart_1);$l++){
									if ($loen_art!=$loenart_1[$l]) print "<option value=\"$loenart_1[$l]\">$loenart_2[$l]</option>";
								}
/*
								if ($loen_art=='aconto') print "<option value=\"aconto\">Aconto</option>";
								elseif ($loen_art=='akktimer') print "<option value=\"akktimer\">Dyrtid</option>";
								elseif ($loen_art=='akk_afr') print "<option value=\"akk_afr\">Akkord afregning</option>";
								elseif ($loen_art=='akkord') print "<option value=\"akkord\">Akkord med dyrtid</option>";
								elseif ($loen_art=='timer') print "<option value=\"timer\">Timeløn</option>";
								elseif ($loen_art=='plads') print "<option value=\"plads\">Pladsarbejde</option>";
								elseif ($loen_art=='skole') print "<option value=\"skole\">Skoleophold</option>";
								elseif ($loen_art=='sygdom') print "<option value=\"sygdom\">Sygdom</option>";
								elseif ($loen_art=='barn_syg') print "<option value=\"barn_syg\">Barn syg</option>";
								elseif ($loen_art=='ferie') print "<option value=\"ferie\">Ferie</option>"; # 20140627
								elseif ($loen_art=='regulering') print "<option value=\"regulering\">Regulering</option>";
								else print "<option value=\"0\"></option>";
}
								# if ($loen_art!='aconto' && substr($sag_rettigheder,6,1)) print "<option value=\"aconto\">Aconto</option>"; 20161006
								if ($loen_art!='akktimer') print "<option value=\"akktimer\">Dyrtid</option>";
								if ($loen_art!='akk_afr') print "<option value=\"akk_afr\">Akkord afregning</option>";
								if ($loen_art!='akkord') print "<option value=\"akkord\">Akkord med dyrtid</option>";
								if ($loen_art!='timer') print "<option value=\"timer\">Timeløn</option>";
								if ($loen_art!='plads') print "<option value=\"plads\">Pladsarbejde</option>";
								if ($loen_art!='skole') print "<option value=\"skole\">Skoleophold</option>";
								if ($loen_art!='sygdom') print "<option value=\"sygdom\">Sygdom</option>";
								if ($loen_art!='barn_syg') print "<option value=\"barn_syg\">Barn syg</option>";
								if ($loen_art!='ferie') print "<option value=\"ferie\">Ferie</option>"; #20140627
								# if ($loen_art!='regulering' && substr($sag_rettigheder,6,1)) print "<option value=\"regulering\">Regulering</option>"; 20161006
*/
							print "</select>
						</div>
						<div class=\"clear\"></div></div>";
						if ($loen_art=='akk_afr' || $loen_art=='akkord' || $loen_art=='akktimer') {
							if ($listevalg_ny && $listevalg_ny!=$listevalg && $gemt > 1) $listevalg=$listevalg_ny;
							print "<div class=\"row\"><div class=\"leftSmall\">Type: </div>
							<div class=\"rightLarge\">
								<select name=\"listevalg_ny\" $readonly class=\"akkordlistevalg printSelect2\" style=\"width: 100%;\">";
									if (!$listevalg) print "<option value=\"0\">Vælg type</option>";
									for ($y=0;$y<$antal_cat;$y++) {
										if ($cat_id[$y]==$listevalg) print "<option value=$cat_id[$y]>$cat_navn[$y]</option>";
									}						  
									if (!$readonly) {
										for ($y=0;$y<$antal_cat;$y++) {
											if ($cat_id[$y]!=$listevalg) print "<option value=$cat_id[$y]>$cat_navn[$y]</option>";
										}						  
									}
								print "</select>
							</div>
							<div class=\"clear\"></div></div>";
						} 
						print "<input type=\"hidden\" name=\"listevalg\" value=\"$listevalg\">";
#					print "</div>";
					if ($loen_art!='sygdom' && $loen_art!='barn_syg' &&  $loen_art!='skole' &&  $loen_art!='plads' && $loen_art!='ferie') { #20140627 
					if (!$sag_nr) $sag_nr = NULL;
					print "<div class=\"row\">
							<div class=\"leftSmall\">Sag:</div>
							<div class=\"rightSmall\"><input type=\"text\" $readonly placeholder=\"Sags nr\" class=\"textMedium sagsnr printBorderNone printBg\" $sagsnr_error name=\"sag_nr\" value=\"$sag_nr\"></div>
							<div class=\"rightXLarge\"><input type=\"text\" $readonly placeholder=\"Sags addresse\" class=\"textXLong sagsaddr printBorderNone printBg\" $sagsnr_error name=\"sag_addr\" value=\"$sag_addr\"></div>
							<div class=\"rightNoWidth\"><p>$sagsnr_errortxt</p></div>
							<!--<div class=\"rightMedium\"><p id=\"message\">Ingen resultat fundet</p></div>-->
							<div class=\"clear\"></div>
						</div>";
						if ($sag_id && $opgave_id) {	
							print "<div class=\"row\">
								<div class=\"leftSmall\">Opgave:</div>
								<div class=\"rightNoWidth\"><select $readonly $opgnr_error name=\"opg_id\" class=\"printSelect2\">";
								for ($x=0;$x<count($opgave_id);$x++) {
									if ($opg_id==$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>"; 
								}
								if (!$opg_id) print "<option value=\"0\">&nbsp;</option>";
								for ($x=0;$x<count($opgave_id);$x++) {
									if ($opg_id!=$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>"; 
								}
								if ($opg_id) print "<option opg_id=\"0\">&nbsp;</option>";
								print "</select></div>
								<div class=\"rightNoWidth\"><p>$opgnr_errortxt</p></div>
								<!--<div class=\"rightMedium\"><p id=\"message\">Ingen resultat fundet</p></div>-->
								<div class=\"clear\"></div>
							</div>";
						}
					}
					if ($loen_art=='ferie') { #20140627
						print "<div class=\"row\">
							<div class=\"leftSmall\">Fra / Til: </div>
							<div class=\"rightSmall\"><input name=\"feriefra\" id=\"feriefra\" type=\"text\" $readonly class=\"textMedium printBorderNone printBg\" $feriefra_error value=\"$feriefra\"/></div>
							<div class=\"rightSmall\"><input name=\"ferietil\" id=\"ferietil\" type=\"text\" $readonly class=\"textMedium printBorderNone printBg\" $ferietil_error value=\"$ferietil\"/></div>
							<div class=\"rightNoWidth\"><p>$feriefratil_errortxt</p></div>
							<div class=\"clear\"></div>
						</div>";
					}
					if ($afvis) {
						print "<div class=\"row\">
							<div class=\"leftSmall\">Årsag til afvisning:</div>";
							print "<div class=\"right\"><textarea class=\"printTextArea textAreaLoen autosize\" name=\"afvist_pga\" cols=\"78\" rows=\"3\">$afvist_pga</textarea></div>
							<div class=\"clear\"></div>
						</div>";
					} else { #20140627
						print "<div class=\"row\">";
							if ($loen_art=='sygdom' || $loen_art=='barn_syg' || $loen_art=='skole' || $loen_art=='plads' || $loen_art=='ferie') print "<div class=\"leftSmall\">Bemærkn.:</div>";
							else print "<div class=\"leftSmall\">Udført:</div>";
							print "<div class=\"rightXXLarge\"><textarea $readonly $loentext_error class=\"printTextArea textAreaLoen autosize\" name=\"loen_tekst\" style=\"width:560px;\" cols=\"78\" rows=\"3\">$loen_tekst</textarea></div>
							<div class=\"rightNoWidth\"><p>$loentext_errortxt</p></div>
							<div class=\"clear\"></div>
						</div>";
					}
					if ($afvist_pga && $afvist) { // Tilføjet '&& $afvist', så det kun er de afviste sedler som ser det #20161031
						print "<div class=\"row\">
							<div class=\"leftSmall\">Årsag til afvisning:</div>";
							print "<div class=\"right\"><b style=\"color: #cd3300;padding-left: 4px;\">$afvist_pga</b></div>
							<div class=\"clear\"></div>
						</div>";
					}
					// Her er det kun de nye sedler efter de er blevet afvist #20161031
					// ----------------------------------
					if ($afvist_pga && !$afvist) { 
						print "<div class=\"row\">
							<div class=\"leftSmall\"><i>Afvist pga.:</i></div>";
							print "<div class=\"right\"><i style=\"color: #cd3300;padding-left: 4px;\">$afvist_pga</i></div>
							<div class=\"clear\"></div>
						</div>";
					}
					// ----------------------------------
					print "</div><!-- end of contentA -->";

				if ($oprettet) {
						print "<table border=\"0\" cellspacing=\"0\" width=\"780\">
						<tr>
							<td><b>Oprettet:</b></td><td>d.".date("d-m-Y",$oprettet)." kl. ".date("H:i",$oprettet)."</td>
							<td><b>af:</b> $oprettet_af</td>
							<td><b>Løbenr.:&nbsp;</b>$loen_nr</td>  
							<td><b>Status:&nbsp;</b>$status</td>
							
						</tr>";
					if ($afsluttet) {
						print "<tr><td><b>Overført:</b></td><td>d.".date("d-m-Y",$afsluttet)." kl. ".date("H:i",$afsluttet)."</td>
							<td><b>af:</b> $afsluttet_af</td></tr>";
					}
					if ($godkendt && !$afvist) { #20170524 Tilføjet '&& !$afvist'
						print "<tr><td><b>Godkendt:</b></td><td>d.".date("d-m-Y",$godkendt)." kl. ".date("H:i",$godkendt)."</td>
							<td><b>af:</b> $godkendt_af</td>";
 							if ($master_nr) print "<td><b>Afr. på&nbsp; : </b>$master_nr</td>"; #20151215 
							print "</tr>";
					}
					if ($afvist) {
						print "<tr><td><b>Afvist:</b></td><td>d.".date("d-m-Y",$afvist)." kl. ".date("H:i",$afvist)."</td>
							<td><b>af:</b> $afvist_af</td></tr>";
					}
					print "</table>";
				}
			print "</div><!-- end of content -->
			<div class=\"content\">
				<table class=\"akkordTable ansatteTable\">
					<thead class=\"akkordTableBorderBottom\">
					<tr>";
						if ($loen_art=='akk_afr') print "<th class=\"alignLeft\">Dato</th>";
						print "<th class=\"alignLeft\">Nr</th>
						<th class=\"alignLeft\">Navn</th>";
						if ($loen_art=='timer') print "<th title='Anvendes hvis der anvendes anden sats end medarbejderens timeløn'>Type</th>"; 
						if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') print "<th>Timer</th>";
						if ($loen_art=='akk_afr'||$loen_art=='akktimer'||$loen_art=='akkord'||$loen_art=='timer') {
							print "<th>50%</th>
							<th>100%</th>";
							print "<th width='36' title='Skur lav sats (".dkdecimal($skur_sats1)."'>S(L)</th>";
							print "<th width='36' title='Skur høj sats (".dkdecimal($skur_sats2)."'>S(H)</th>";
							if ($loen_art!='timer') { //20231005
								print "<th width='36' title='Mentortillæg (".dkdecimal($mentorRate).")'>Mentor</th>";
								print "<th>Km</th>";
							}
							print "<th>Sum</th>";
							if ($loen_art=='timer') print "<th>Timetillæg</th>";
							else print "<th>Akkord</th>";
						}
						if ($loen_art=='aconto') print "<th>Aconto bel&oslash;b</th>";
						elseif ($loen_art=='regulering') print "<th>Bel&oslash;b</th>";
						elseif ($loen_art!='ferie') print "<th>I Alt</th>";
						//else print "<th>I Alt</th>";
						print "<!--<th width='20'></th>-->
					</tr>
					</thead>
				<tbody class='akkordTableBody akkordTableBorderAll'>\n";
				
				$l_timer=0;
				for($x=0;$x<=count($ansat_id);$x++) {
					$fordel_timer[$x] = 0;
				if (isset($loen_timer[$x])) {
					if ($loen_fordeling[$x]) $fordel_timer[$x]=(float)$loen_timer[$x]*(float)$loen_fordeling[$x]/100;
					else $fordel_timer[$x]=$loen_timer[$x];
					$fordel_timer[$x] = (float)$fordel_timer[$x];
					$l_timer+=(float)$fordel_timer[$x];
					}
				}
				$f_sum=0;
				$t_sum=0;
				if ($loen_art!='aconto' && $loen_art!='regulering') $sum=0;
				$aa=count($ansat_id);
				if ($aa<1) $aa++;
				if ($loen_art=='akk_afr' || $readonly) {
					$beskyttet="readonly='readonly'";
					$aa--;
				} elseif ($loen_art=='aconto' || $loen_art=='regulering') $aa=0;
				for($x=0; $x<=$aa;$x++) { # Must be <=
					$aa_belob[$x]=0;
#					$loen_sum[$x]=0;
					if ($loen_art!='akk_afr') $loen_date[$x]=$loendate;
					if (!isset($ansat_id[$x])) $ansat_id[$x]=NULL;
					if (!isset($medarb_nr[$x])) $medarb_nr[$x]=NULL;
					if (!isset($medarb_navn[$x])) $medarb_navn[$x]=NULL;
					if (!isset($loen_fordeling[$x])) $loen_fordeling[$x]=NULL;
					if (!isset($loen_loen[$x])) $loen_loen[$x]=0;
					if (!isset($loen_timer[$x])) $loen_timer[$x]=0;
					$loen_50pct[$x] = if_isset($loen_50pct[$x],0);
					if (!isset($loen_100pct[$x])) $loen_100pct[$x]=0;
					if (!isset($loen_date[$x])) $loen_date[$x]=NULL;
					$l_skur1[$x]=NULL;
					if (!isset($loen_skur1[$x]) || !$loen_skur1[$x]) $loen_skur1[$x]=0;
					elseif ($loen_skur1[$x]>0) $l_skur1[$x]="checked='checked'";
					$l_skur2[$x]=NULL;
					if (!isset($loen_skur2[$x]) || !$loen_skur2[$x]) $loen_skur2[$x]=0;
					elseif ($loen_skur2[$x]>0) $l_skur2[$x]="checked='checked'"; 
#					$l_mentor[$x]=NULL;
#					if (!isset($loen_mentor[$x])) $loen_mentor[$x]=0;
#					elseif ($loen_mentor[$x]>0) $l_mentor[$x]="checked='checked'"; 
					$loen_mentor[$x] = (float)if_isset($loen_mentor[$x],0);
					$loen_km[$x] = (float)if_isset($loen_km[$x],0);
					if (!$afsluttet && $ansat_id[$x]) {
						if ($loen_art=='sygdom') $loen_loen[$x]=$sygdom_sats;
						elseif ($loen_art=='barn_syg') $loen_loen[$x]=$sygdom_sats;	
						elseif ($loen_art=='skole') $loen_loen[$x]=$skole_sats;
						elseif ($loen_art=='plads') $loen_loen[$x]=$plads_sats;
						elseif ($loen_art=='timer') {
							if ( $loen_timeArt[$x] ) {
								for ( $h=0; $h < count($hourTypes); $h++) {
									if ( $loen_timeArt[$x] == $hourTypes[$h]) $loen_loen[$x]=$hourValue[$h];
								}	
							} else $loen_loen[$x]=$medarb_loen[$x];#+$medarb_extraloen[$x];
						} else $loen_loen[$x]=$medarb_extraloen[$x];
					}
					if (!is_numeric($loen_loen[$x]))  	$loen_loen[$x]  = 0;
					if (!is_numeric($loen_timer[$x]))  $loen_timer[$x]  = 0;
					if (!is_numeric($loen_100pct[$x])) $loen_100pct[$x] = 0;
					$loen_mentor[$x] = (float)$loen_mentor[$x];

					if (!isset($skur1[$x])) $skur1[$x] = 0;	
					if (!isset($skur2[$x])) $skur2[$x] = 0;

					$t_belob[$x] =
					$loen_loen[$x]  * $loen_timer[$x] +
					$overtid_50pct  * $loen_50pct[$x] +
					$overtid_100pct * $loen_100pct[$x];
					if ($loen_mentor[$x]) {
						$t_belob[$x] += $mentorRate * $loen_mentor[$x];
					}
					if ($loen_timer[$x] && $l_timer) $aa_belob[$x]=$aa_sum/$l_timer*$fordel_timer[$x];
					$loen_sum[$x]=
					$t_belob[$x]+
					$aa_belob[$x]+
					$loen_skur1[$x]+
					$loen_skur2[$x];
					if ($loen_date[$x] && ($loen_km[$x] || $skur1[$x] || $skur2[$x])){ #20180117
						$t_km=0;
						$tjek=0;
						$qtxt = "select * from loen where (art='akktimer' or art='akkord' or art='timer') ";
						$qtxt.= "and loendate='$loen_date[$x]' and nummer < '$loen_nr' and afvist = '' order by id";
						$q=db_select($qtxt,__FILE__ . " linje " . __LINE__); # finder hvormeget kørsel personen har haft samme dag. (incl aktuelle seddel). 
						while ($r=db_fetch_array($q)) {
							$a=explode(chr(9),$r['ansatte']);
							if (in_array($ansat_id[$x],$a)) {
								$k=explode("|",$r['korsel']);
								for ($i=0;$i<count($a);$i++) { #20150623
									if ($a[$i]==$ansat_id[$x]) {
										if ($k[0]) $t_km+=(float)$k[0];
									}
								}
							}
							$tjek=1;
						} # 20150617 Flytte fra over '$fratræk' længere nede da km blev forkert
						if ($t_km==$loen_km[$x]) {
							if ($km_fra<=$loen_km[$x]) {
								$fratraek[$x]=$km_fra;
							}	else $fratraek[$x]=$loen_km[$x];
						} elseif ($t_km-$loen_km[$x]<$km_fra) $fratraek[$x]=$km_fra-$t_km; # 20150928 
						if ($fratraek[$x]<0) $fratraek[$x]=0;
					}
					$fratraek[$x] = if_isset($fratraek[$x],0); 
					(float)$km_sats;
					if ($loen_km[$x] >= $fratraek[$x]) $loen_sum[$x]+=($loen_km[$x]-$fratraek[$x])*$km_sats; 
					else $fratraek[$x]=$loen_km[$x]; # 20151009
					if ($x<=count($ansat_id)) $sum+=$loen_sum[$x];
					if ($x<=count($ansat_id)) $timersum += $loen_timer[$x]; # 20160819
						$t_sum+=$fordel_timer[$x];
					if (!isset($loen_loen[$x])   || !$loen_loen[$x])   $loen_loen[$x]=0;
					if (!isset($loen_timer[$x])  || !$loen_timer[$x])  $loen_timer[$x]=0;
					if (!isset($loen_50pct[$x])  || !$loen_50pct[$x])  $loen_50pct[$x]=0;
					if (!isset($loen_100pct[$x]) || !$loen_100pct[$x]) $loen_100pct[$x]=0;
					if (!isset($loen_mentor[$x]) || !$loen_mentor[$x]) $loen_mentor[$x]=0;
					if (!isset($loen_km[$x])      || !$loen_km[$x])     $loen_km[$x]=0;
					if (!isset($loen_sum[$x])    || !$loen_sum[$x])    $loen_sum[$x]=0;
					if ($loen_fordeling[$x] && $loen_fordeling[$x]<100) $medarb_navn[$x].=" (Under oplæring)";
					print "<tr>\n";
						if ($loen_art=='akk_afr') print "<td title='Akkord seddel nr: $akkord_nr[$x]'><input type='text' $beskyttet placeholder='Dato' name='loen_date[$x]' class='medarbejdernr printBorderNone' value='".dkdato($loen_date[$x])."' style='width:66px;'></td>\n";
						print "<td><input type='text' $beskyttet placeholder='Med. nr.' name='medarb_nr[$x]' class='medarbejdernr printBorderNone' value='$medarb_nr[$x]' style='width:56px;'></td>
						<td><input type='text' $beskyttet placeholder='Medarbejder navn' name='medarb_navn[$x]' class='medarbejdernavn printBorderNone' value='$medarb_navn[$x]' style='width:260px'>\n";
						if ($loen_art!='ferie') print "</td>\n";
						if ($loen_art=='timer') {
							print "<td>";
							if (count($hourTypes)) {
								print "<select style='width:75px;' name='hourType[$x]'>";
								if (!$loen_timeArt[$x]) print "<option value=''></option>";
								for ($h=0;$h<count($hourTypes);$h++) {
									if ($loen_timeArt[$x]==$hourTypes[$h]) print "<option value='$hourTypes[$h]'>$hourDescription[$h]</option>";
								}
								if ($loen_timeArt[$x]) print "<option value=''></option>";
								for ($h=0;$h<count($hourTypes);$h++) {
									if ($loen_timeArt[$x]!=$hourTypes[$h]) print "<option value='$hourTypes[$h]'>$hourDescription[$h]</option>";
								}
								print "</select>";
							}
							print "</td>";
						}
						if ($loen_timer[$x]   == 0.000) $loen_timer[$x]  = 0;
						if ($loen_50pct[$x]   == 0.00)  $loen_50pct[$x]  = 0;
						if ($loen_100pct[$x] == 0.00)  $loen_100pct[$x] = 0;
						if ($loen_mentor[$x]  == 0.00)  $loen_mentor[$x] = 0;

						if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') {
							print "<td class=\"alignRight\"><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_timer[$x]\" 
								class=\"zeroValue alignRight printBorderNone\" value=\"". str_replace(".",",",$loen_timer[$x]) ."\"
								style=\"width:33px;\"></td>\n";
						}
						if ($loen_art=='akk_afr'||$loen_art=='akktimer'||$loen_art=='akkord'||$loen_art=='timer') {
							print "<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_50pct[$x]\" 
								class=\"alignRight printBorderNone\" value=\"". str_replace(".",",",$loen_50pct[$x]) ."\"
								style=\"width:33px;\"></td>";
							print "<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_100pct[$x]\" 
								class=\"alignRight printBorderNone\" value=\"". str_replace(".",",",$loen_100pct[$x]) ."\"
								style=\"width:33px;\"></td>\n";
							if ($beskyttet) { #($beskyttet || $retskur[$x])
								print "<td class='alignCenter'><input name='skur1[$x]' disabled='disabled' 
									type='checkbox' $l_skur1[$x]></td>";
								print "<td class='alignCenter'><input name='skur2[$x]' disabled='disabled' 
									type='checkbox' $l_skur2[$x]></td>\n";
#								print "<td class='alignCenter'><input name='loen_mentor[$x]' disabled='disabled' 
#									type='checkbox' $l_mentor[$x]></td>\n";
							} else {
								print "<td class=\"alignCenter\"><input name=\"skur1[$x]\" type=\"checkbox\" $l_skur1[$x]></td>";
								print "<td class=\"alignCenter\"><input name=\"skur2[$x]\" type=\"checkbox\" $l_skur2[$x]></td>\n";
#								print "<td class=\"alignCenter\"><input name=\"loen_mentor[$x]\" type=\"checkbox\" $l_mentor[$x]></td>\n";
							}
							if ($loen_art!='timer') { //20231005
								print "<td title=\"\"><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_mentor[$x]\"
								class=\"alignRight printBorderNone\" value=\"". str_replace(".",",",$loen_mentor[$x]) ."\"
								style=\"width:33px;\"></td>\n";
								print "<td title=\"Fratrukket $fratraek[$x] kilometer\"><input type=\"text\" $beskyttet placeholder=\"0,00\"
								name=\"loen_km[$x]\" class=\"alignRight printBorderNone\" value=\"". str_replace(".",",",$loen_km[$x]). "\"
								style=\"width:33px;\"></td>\n";
							}
							if($hideSalary && $loen_art == 'timer'){
							print "<td><input type=\"text\" readonly=\"readonly\"  name=\"hideSalary\" class=\"alignRight printBorderNone\" value=\"\" disabled=\"disabled\" style=\"width:50px;\"></td>\n";
							print "<input type=\"hidden\" name=\"t_belob[$x]\" value=\"$loen_loen[$x]\">\n";
							print "<input type=\"hidden\" name=\"mentorRate\" value=\"$mentorRate\">\n";
							}
							else {
							print "<td><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"t_belob[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($t_belob[$x],2)."\" style=\"width:50px;\"></td>\n";
							}
							print "<td><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"aa_belob[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($aa_belob[$x],2)."\" style=\"width:50px;\"></td>\n";
						}
//						<!--<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"fordel_belob[$x]\" class=\"alignRight\" value=\"".dkdecimal($fordel_belob[$x])."\" style=\"width:60px;\"></td>-->
						if ($loen_art=='aconto' || $loen_art=='regulering') print "<td class=\"alignRight\" ><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"dksum\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($sum,2)."\" style=\"width:70px;\">\n";
						elseif ($loen_art!='ferie') {
						
						if($hideSalary && $loen_art == 'timer'){
							print "<td class=\"alignRight\" ><input type=\"text\" readonly=\"readonly\" name=\"hideSalary2\" class=\"alignRight placeholderLoen printBorderNone\" value=\"\" disabled=\"disabled\" style=\"width:50px;\">\n";
							print "<input type=\"hidden\" name=\"loen_sum[$x]\" value=\"".dkdecimal($loen_sum[$x],2)."\">\n";
							}
							else {
							print "<td class=\"alignRight\" ><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"loen_sum[$x]\" class=\"alignRight placeholderLoen printBorderNone\" value=\"".dkdecimal($loen_sum[$x],2)."\" style=\"width:50px;\">\n";
							}


						print "<input type=\"hidden\" name=\"loen_loen[$x]\" value=\"$loen_loen[$x]\">\n";
						}
						if ($beskyttet) {  #($beskyttet || $retskur[$x])
								print "<input name=\"skur1[$x]\" type=\"hidden\" value=\"$l_skur1[$x]\">
								<input name=\"skur2[$x]\" type=\"hidden\" value=\"$l_skur2[$x]\">";
#								<input name=\"loen_mentor[$x]\" type=\"hidden\" value=\"$l_mentor[$x]\">\n";
						}
#						<!--<td><button class=\"xmark delRow \"></button></td>-->
					print "</td></tr>\n";
					
				}
				print "</tbody>\n";
				if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') { # 20140627,20160819
					print "<tbody class=\"akkordTableBody akkordTableBorderBottom\">";
					if ($loen_art=='akktimer' || $loen_art=='akkord') {$colspan1=2;$colspan2=9;} 
					elseif ($loen_art=='akk_afr' || $loen_art=='timer') {$colspan1=3;$colspan2=10;} 
					else {$colspan1=2;$colspan2=1;}
					
					if($hideSalary && $loen_art == 'timer'){
							print "<tr><td colspan=\"1\"><b>Sum</b></td>
								<td class=\"alignRight\" colspan=\"$colspan1\"><b>". str_replace(".",",",$timersum). "</b></td>
								<td class=\"alignRight\" colspan=\"$colspan2\">
									<input type=\"hidden\" name=\"hiddenSum\" value=\"$sum\">
								</td>";
							print "<input type=\"hidden\" name=\"sum\" value=\"".dkdecimal($sum,2)."\">\n";
							}
							else {
							print "<tr><td colspan=\"1\"><b>Sum</b></td>
							<td class=\"alignRight\" colspan=\"$colspan1\"><b>". str_replace(".",",",$timersum) ."</b></td>
							<td class=\"alignRight\" colspan=\"$colspan2\"><b>".dkdecimal($sum,2)."</b>
								<input type=\"hidden\" name=\"sum\" value=\"$sum\">
							</td>";
							}
				
					print "</tbody>";
					//print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">";
				}
				if (!$afsluttet || $afslut) db_modify("update loen set sum='$sum' where id='$id'",__FILE__ . " linje " . __LINE__); #20130604

#				print "<tbody class=\"akkordTableBody\">
#					<tr>
#						<td colspan=\"8\"class=\"alignRight\">Tilføj ny række&nbsp;</td>
#						<td><button class=\"cross addRow\" ></button></td>
#					</tr>
#				</tbody> -->
		print "</table>  
		</div><!-- end of content -->";
		if ($godkendt && $loen_art == 'akkord') $sum = 0; # else sum is doubled
		print "<div class=\"content link\">
			<!--<h3><a id=\"aTag\" href=\"javascript:toggleAndChangeText();\">Vis akkordliste &#9658;</a></h3>-->";
			if (count($ansat_id) && $listevalg && ($loen_art=='akk_afr' || $loen_art=='akkord')) {
				print "<hr><h3><a id=\"aTag\" style=\"cursor:pointer;\">Vis akkordliste &#9658;</a></h3>
				<table class=\"akkordTableListe #akkordTableListeBody akkordlisteSort loenindtastning\"  border=\"0\" style=\"#cellspacing:0px;\" id=\"toggle\">
					<thead style=\"border-bottom: 1px solid #d3d3d3;\">
						<tr>
							<th rowspan=\"2\" width=\"30\">Op</th>
							<th rowspan=\"2\" width=\"30\">Ned</th>
							<th rowspan=\"2\">Betegnelse</th>
							<th rowspan=\"2\" class=\"alignRight\">Pris op</th>
							<th rowspan=\"2\" class=\"alignRight\">Pris ned</th>
							<th rowspan=\"2\" class=\"alignRight\" width=\"50px\">Sum</th>";
						if ($listevalg < 1) print "
							<th colspan=\"2\" width=\"25px\">25%</th>
							<th colspan=\"2\" width=\"25px\">40%</th>
							<th colspan=\"2\" width=\"25px\">60%</th>
							<th colspan=\"2\" width=\"25px\">+30m</th>";
						else print "
							<th colspan=\"2\" width=\"25px\">30%</th>
							<th colspan=\"2\" width=\"25px\">70%</th>
							<th colspan=\"2\" width=\"25px\">100%</th>
							<th colspan=\"2\" width=\"25px\">160%</th>";
						print "<th rowspan=\"2\" class=\"alignRight\">Beløb</th>
						</tr>
						<tr class=\"akkordListeHead2\">
							<th>Op</th>
							<th>Ned</th>
							<th>Op</th>
							<th>Ned</th>
							<th>Op</th>
							<th>Ned</th>
							<th>Op</th>
							<th>Ned</th>
							</tr>
					</thead>";
					
				print "<tbody>"; 
				include('loenIncludes/visListe.php');
				$sum=vis_liste($id,$listevalg,$afsluttet,$godkendt,$telt_antal);
				print "<tr>
					<td colspan=\"13\" class=\"tableSagerBorder\"><b>Lønlinjer ialt:</b></td>
					<td colspan=\"2\" align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum,2)."</b></td>						
				</tr>
			</tbody>
			</table>"; 
			}
			print "</div><!-- end of content -->";

				if ($loen_art=='akk_afr' || $loen_art=='akkord') {
					print "<div class=\"content\">				 
						<hr>
						<h3>Andet</h3>
						<table class=\"akkordTable andetTable\">
								<thead>
									<tr>
										<th width=\"40\">Stk.</th>
										<th width=\"600\">Text</th>
										<th width=\"1\">Stykpris</th>
										<!--<th width=\"80\">Procentsats</th>-->
										<th class=\"alignRight\">Beløb</th>
										<!--<th width=\"20\"></th>-->
									</tr>
								</thead>
								<tbody class=\"akkordTableBody akkordTableBorderAll\">";
								$a_sum=0;	
								for($x=0;$x<=count($a_id);$x++) { # <= is as it must be to get an empty line.
									if (!isset($a_stk[$x])) $a_stk[$x]=NULL;
									if (!isset($a_pris[$x])) $a_pris[$x]=NULL;
									if (!isset($a_txt[$x])) $a_txt[$x]=NULL;
#									if (!isset($a_pct[$x])) $a_pct[$x]=NULL;
									$a_linjesum[$x]=$a_stk[$x]*$a_pris[$x];
									$a_sum+=$a_linjesum[$x];
#									$a_id[$x] = if_isset($a_id[$x],NULL);
									print "<tr>
										<td><input type=\"text\" $readonly style=\"width:36px; text-align: right;\" 
										class=\"printBorderNone\" name=\"a_stk[$x]\" value=\"".str_replace(".",",",$a_stk[$x])."\"></td>
										<td><input type=\"text\" $readonly 
										style=\"width:596px; text-align: left;\" 
										class=\"printBorderNone\" name=\"a_txt[$x]\" value=\"$a_txt[$x]\"></td>
										<td><input type=\"text\" $readonly 
										style=\"width:76px; text-align: right;\" class=\"printBorderNone\" placeholder=\"0,00\" 
										name=\"a_pris[$x]\" value=\"".dkdecimal($a_pris[$x],2)."\"></td>";
#										<!--<td><input type=\"text\" $readonly 
#										style=\"width:76px; text-align: right;\" placeholder=\"100%\" 
#										name=\"a_pct[$x]\" value=\"".str_replace(".",",",$a_pct[$x])."\"></td>-->
									print "<td class=\"alignRight\">".dkdecimal($a_linjesum[$x],2);
									if (isset($a_id[$x])) print "<input type=\"hidden\" name=\"a_id[$x]\" value=\"$a_id[$x]\">";
									print "</td>
										<!--<td><button class=\"xmark delRow2\"></button></td>-->";
									print "</tr>";
								}
								print "</tbody>
								<tbody class=\"akkordTableBody2 akkordTableBorderBottomAll\">
									<tr>
										<td colspan=\"3\"><b>Andet Ialt:</b></td>
										<td colspan=\"1\" class=\"alignRight\"><b>".dkdecimal($a_sum,2)."</b></td>
									</tr>
									<tr>
										<td colspan=\"3\"><b>Akkord Ialt:</b></td>
										<td colspan=\"1\" class=\"alignRight\"><b>".dkdecimal($sum,2)."</b></td>
									</tr>
									<tr>
										<td colspan=\"3\"><b>Til fordeling:</b></td>
										<td colspan=\"1\" class=\"alignRight\" style=\"#border-bottom: 3px double #444;\"><b>".dkdecimal($a_sum+$sum,2)."</b></td> 
								</tr>
								</tbody>
						</table>
					</div><!-- end of content -->";
					}
					print "<div class=\"content printDisplayNone\">
						<hr>";
					print "<div class=\"contentA\">";
						if (!$afsluttet) { # 20140627
							print "<input name=\"gem\" type=\"submit\" class=\"button gray medium\" value=\"Gem\" >";
							if (!$sum && !$a_sum && $id) {
								print "<input name=\"slet\" type=\"submit\" class=\"button gray medium textSpaceLarge\" 
								value=\"Slet\" onclick=\"return confirm('Bekræft sletning')\">"; // Indsat $id, så slet først kommer frem efter der er trykket gem
							}
							print "<input name=\"luk\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Luk\">";
							//if ((($loen_art && $loen_art!='akktimer') || $opg_id) && $sum) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='akktimer' || $loen_art=='akk_afr' || $loen_art=='akkord') 
							&& $sum && $loendato && $loen_tekst && ($opg_nr || ($sag_id && !$opgave_id)) && (!empty($medarb_nr[0]))) {
								print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							}
							if (($loen_art=='timer' || $loen_art=='aconto' || $loen_art=='regulering') 
							&& $sum && $loendato && $loen_tekst && (!$sag_id || $opg_nr || ($sag_id && !$opgave_id)) 
							&& (!empty($medarb_nr[0]))) {
								print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" 
								value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							}
							if (($loen_art=='plads' || $loen_art=='sygdom' || $loen_art=='barn_syg' || $loen_art=='skole') 
							&& $sum && $loendato && (!empty($medarb_nr[0]))) {
								print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" 
								value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							}
							if ($loen_art=='ferie' && $feriefra && $ferietil && $loendato && (!empty($medarb_nr[0]))) {
								print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" 
								value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							}
							if ($afs) { #20160830
								print "<input type=\"hidden\" name=\"fejltxt\" value=\"$fejltxt\">\n";
								print "<input type=\"hidden\" name=\"listevalg_ny\" value=\"$listevalg_ny\">\n";
								print "<input type=\"hidden\" name=\"afs\" value=\"$afs\">\n";
								print "<input type=\"hidden\" name=\"afslut\" value=\"1\">\n";
								print "<SCRIPT LANGUAGE=\"JavaScript\">document.forms[0].submit();</SCRIPT>";
							} elseif ($gem && $gemt<2) {
								print "<input type=\"hidden\" name=\"fejltxt\" value=\"$fejltxt\">\n";
								print "<input type=\"hidden\" name=\"listevalg_ny\" value=\"$listevalg_ny\">\n";
								print "<input type=\"hidden\" name=\"gem\" value=\"$gem\">\n";
								print "<input type=\"hidden\" name=\"gemt\" value=\"$gemt\">\n";
								print "<SCRIPT LANGUAGE=\"JavaScript\">document.forms[0].submit();</SCRIPT>";
							} elseif(isset($_POST['fejltxt']) && $_POST['fejltxt']) print tekstboks($_POST['fejltxt']);
						}
						if (substr($sag_rettigheder,6,1) && $afsluttet && !$godkendt && !$afvist) {
							print "<input name=\"godkend\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Godkend\" onclick=\"return confirm('Bekræft godkendelse')\">";
							print "<input name=\"afvis\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Afvis\" onclick=\"return confirm('Bekræft afvisning')\">";
						}
						if (substr($sag_rettigheder,6,1) && $godkendt && !$afregnet && !$afvist) { #20170524 Tilføjet '&& !$afvist' 
							print "<input name=\"afvis\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Afvis\" onclick=\"return confirm('Vil du afvise denne godkendte seddel???.')\">";
#							print "<input name=\"tilbagefoer\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Tilbagef&oslash;r\" onclick=\"return confirm('Vil du tilbageføre denne seddel?')\">";						
						}
						print "</div></div>
					</form>";	
				if ($afvis && !$afvist_pga) {
					$txt="Skriv årsag til afvisning og klik afvis igen!";
					print "<BODY onLoad=\"javascript:alert('$txt')\">";
				}	
	print "</div><!-- end of printableArea -->";
} # endfunc ret_loen

?>
