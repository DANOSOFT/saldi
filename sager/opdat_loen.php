<?php
function opdat_loen ($listevalg,$sum,$gem,$afslut,$afvis) {
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
	$id=if_isset($_GET['id']);
	transaktion('begin');

		$id=if_isset($_POST['id']);
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
#		$sum=if_isset($_POST['sum'])*1;
		$a_sum=if_isset($_POST['a_sum'])*1;
#cho "A $a_sum<br>";
		$dksum=if_isset($_POST['dksum']);
		$feriefra=if_isset($_POST['feriefra']); // indsat 20140627
		$ferietil=if_isset($_POST['ferietil']); // indsat 20140627
 //#cho "loendate: $loendate<br>";
		if ($opg_id && !$opg_nr) {
			$r=db_fetch_array(db_select("select nr from opgaver where id = '$opg_id'",__FILE__ . " linje " . __LINE__));
			$opg_nr=$r['nr']*1;
		}

		if (($loen_art=='akk_afr' || $loen_art=='akkord') && $sag_nr) {
		$r=db_fetch_array(db_select("select id,nummer from loen where (art='akk_afr' or art='akkord') and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and afvist = '' and id != '$id'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				$txt="Der eksisterer allerede en uafsluttet akkordseddel (nr: $r[nummer]) til $listevalg for den ".$loendate." på sag nr: $sag_nr, opgave nr:$opg_nr!";
				print "<BODY onLoad=\"javascript:alert('$txt')\">";
				$sag_nr='0';
				$sag_id=0;
			}
		}
		if ($loen_art=='akktimer' && $sag_nr) {
			$r=db_fetch_array(db_select("select id,nummer from loen where (art='akktimer' or art='akkord') and loendate='".usdate($loendato)."' and sag_nr = '$sag_nr' and opg_nr = '$opg_nr' and afsluttet = '' and afvist = '' and (master_id='$id' or master_id='0' or master_id=NULL) and id != '$id'",__FILE__ . " linje " . __LINE__));
			if ($r['id']) {
				$txt="Der eksisterer allerede en uafsluttet akkordtimeseddel (nr: $r[nummer]) til $listevalg for den ".$loendate." på sag nr: $sag_nr, opgave nr:$opg_nr!";
				print "<BODY onLoad=\"javascript:alert('$txt')\">";
				$sag_nr='0';
				$sag_id=0;
			}
		}
		if ($afslut=isset($_POST['afslut']) && $afslut) {
			$afsluttet=date("U");
			$afsluttet_af=$brugernavn;
		} else $afsluttet_af=NULL;
		for ($x=0;$x<count($medarb_nr);$x++) {
			if (($skur1[$x] || $skur2[$x]) && $loen_art!='akk_afr') { #20130226 + 20130322 ( and afvist<'1')
				$q=db_select("select * from loen where (art='akktimer' or art='akkord' or art='timer') and loendate='$loendate' and id < '$id' and (afvist<'1' or afvist is NULL) order by id",__FILE__ . " linje " . __LINE__);
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
									$txt="Der er allerede skur d. ".dkdato($loendate)." for medarb.nr: $medarb_nr[$x] på seddel $r[nummer]";
									print "<BODY onLoad=\"javascript:alert('$txt')\">";
									$skur1[$x]=NULL;  
									$skur2[$x]=NULL;
								}
							}
						}
					}
				}
			}

			if (!isset($medarb_navn[$x]))$medarb_navn[$x]=NULL;
			if ($loen_fordeling[$x]) $loen_fordeling[$x]*=1;
			if ($loen_date[$x]) $loen_datoer[$x]=usdate($loen_date[$x]);
			if ($loen_timer[$x]) $loen_timer[$x]=str_replace(",",".",$loen_timer[$x])*1;
			if ($loen_50pct[$x]) $loen_50pct[$x]=str_replace(",",".",$loen_50pct[$x])*1;
			if ($loen_100pct[$x]) $loen_100pct[$x]=str_replace(",",".",$loen_100pct[$x])*1;
			if ($skur1[$x]) {
				$skur1[$x]=$skur_sats1;
				$skur2[$x]=0;
			} elseif ($skur2[$x])	{
				$skur2[$x]=$skur_sats2;
				$skur1[$x]=0;
			} else {$skur1[$x]=0;$skur2[$x]=0;}
			if ($loen_km[$x]) $loen_km[$x]=str_replace(",",".",$loen_km[$x])*1;
			if 	(!$medarb_nr[$x] && !$medarb_navn[$x]) $ansat_id[$x]=0;
			if ($medarb_nr[$x]) {
				$r=db_fetch_array(db_select("select id,trainee,startdate from ansatte where nummer='$medarb_nr[$x]'",__FILE__ . " linje " . __LINE__));
				$ansat_id[$x]=$r['id']*1;
				if ($r['trainee']) {
					if ($loen_art=='akk_afr') $loen_fordeling[$x]=tjek_fordeling($ansat_id[$x],$r['startdate'],$loen_datoer[$x]); #20130905
					else $loen_fordeling[$x]=tjek_fordeling($ansat_id[$x],$r['startdate'],$loendate);
				}	else $loen_fordeling[$x]=100;
				if ($ansat_id[$x]) {
					if($ansatte){
						$ansatte.=chr(9).$ansat_id[$x];
						$loen.=chr(9).$loen_loen[$x];
						$fordeling.=chr(9).$loen_fordeling[$x];
						$timer.=chr(9).$loen_timer[$x];
						$t50pct.=chr(9).$loen_50pct[$x];
						$t100pct.=chr(9).$loen_100pct[$x];
						$skur_1.=chr(9).$skur1[$x];
						$skur_2.=chr(9).$skur2[$x];
						$korsel.=chr(9).$loen_km[$x];
						$datoer.=chr(9).$loen_datoer[$x];
					} else {
						$ansatte=$ansat_id[$x];
						$loen=$loen_loen[$x];
						$fordeling=$loen_fordeling[$x];
						$timer=$loen_timer[$x];
						$t50pct=$loen_50pct[$x];
						$t100pct=$loen_100pct[$x];
						$skur_1=$skur1[$x];
						$skur_2=$skur2[$x];
						$korsel=$loen_km[$x];
						$datoer=$loen_datoer[$x];
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
		$r=db_fetch_array(db_select("select id,ref from SAGER where sagsnr='$sag_nr'",__FILE__ . " linje " . __LINE__));
		$sag_id=$r['id']*1;
		$sag_ref=$r['ref'];
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
			if (!$afvis) {
				if ($listevalg!=$nyt_listevalg);
				$qtxt="update loen set art='$loen_art',kategori='$listevalg',nummer='$loen_nr',sag_id='$sag_id',opg_id='$opg_id',sag_nr='$sag_nr',opg_nr='$opg_nr',oprettet='$oprettet',afsluttet='$afsluttet',afsluttet_af='$afsluttet_af',afvist='$afvist',afvist_af='$afvist_af',datoer='$datoer',ansatte='$ansatte',fordeling='$fordeling',loen='$loen',timer='$timer',t50pct='$t50pct',t100pct='$t100pct',skur='$skur',sum='$sum',loendate='$loendate',tekst='$loen_tekst',korsel='$korsel',sag_ref='$sag_ref',feriefra='$feriefra',ferietil='$ferietil' where id='$id'";
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
							$t=explode(chr(9),$timer);
							$match=1;
							for ($n=0;$n<count($t);$n++) {
								if ($loen_timer[$n]!=$t[$n]) $match=0;
							}
							if ($match) {
								$qtxt="update loen set master_id='$id' where id='$r[id]'";
								db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
							}
					}
				}
				transaktion("commit");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
			} elseif ($afvis && $afvist_pga) {
				$afvist_af=$brugernavn;
				$afvist=date('U');
				$afvist_pga=db_escape_string($afvist_pga);
#				db_modify("update loen set afsluttet='',afsluttet_af='' where master_id='$id'",__FILE__ . " linje " . __LINE__);
				db_modify("update loen set sum='$sum',afvist='$afvist',afvist_af='$afvist_af',afvist_pga='$afvist_pga',godkendt='' where id='$id'",__FILE__ . " linje " . __LINE__);
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
			}
			$qtxt="insert into loen (art,kategori,nummer,hvem,sag_nr,sag_id,oprettet,afsluttet,godkendt,afregnet,oprettet_af,ansatte,datoer,fordeling,loen,timer,t50pct,t100pct,skur,sum,loendate,tekst,korsel,opg_id,opg_nr,sag_ref,afvist,afvist_af,feriefra,ferietil) values ('$loen_art','$listevalg','$loen_nr','','$sag_nr','$sag_id','$oprettet','','','','$oprettet_af','$ansatte','$datoer','$fordeling','$loen','$timer','$t50pct','$t100pct','$skur','$sum','$loendate','$loen_tekst','$korsel','$opg_id','$opg_nr','$sag_ref','','','$feriefra','$ferietil')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select max(id) as id from loen where nummer='$loen_nr'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
			if ($afvis && $afvist_pga) db_modify("update loen set afsluttet='',afsluttet_af='',master_id='0' where master_id='$afvis_id'",__FILE__ . " linje " . __LINE__); #20130531
		}
		if ($afvis && $afvist_pga)	{ #20130905-2
			transaktion('commit');
			print "<meta http-equiv=\"refresh\" content=\"0;URL=loen.php?funktion=loenliste\">";
			exit;
		}
#xit;
	transaktion('commit');
	return ($id);
}

?>
