<?php
function vis_loen($id) {
	global $charset;
	global $sprog_id;
	global $bgcolor;
	global $brugernavn;
	global $ansat_id;
	global $sag_rettigheder;

	$id*=1;
	$ansat_id=array();
	$loen_km=array();
	$r=db_fetch_array(db_select("select * from loen where id = '$id'",__FILE__ . " linje " . __LINE__));
	$loen_nr=$r['nummer']*1;
	$loen_tekst=$r['tekst'];
	$hvem=$r['hvem'];
	$sag_id=$r['sag_id']*1;
	$opg_id=$r['opg_id']*1;
	$sag_ref=$r['sag_ref'];
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
	$opg_nr=$r['opg_nr'];
	$opgave_id=$r['opg_id'];
	$master_id=$r['master_id'];
#cho "S $sum<br>";	
	$loen_art=$r['art'];
	$feriefra=$r['feriefra']; // indsat 20140627
	$ferietil=$r['ferietil']; // indsat 20140627
	if ($loen_art=='akk_afr' && !$afsluttet) {
		$ansatte=NULL;
		$datoer=NULL;
		$fordeling=$r['fordeling'];
		$loen_fordeling=explode(chr(9),$fordeling);
		$timer=NULL;
		$t50pct=NULL;
		$t100pct=NULL;
		$loen_dato=NULL;
		$skur1=NULL;
		$skur2=NULL;
		$korsel=NULL;
#		$fordeling=NULL;
	} else {
		$ansatte=$r['ansatte'];
		$datoer=$r['datoer'];
		$fordeling=$r['fordeling'];
		$timer=$r['timer'];
		$t50pct=$r['t50pct'];
		$t100pct=$r['t100pct'];
		list($skur1,$skur2)=explode("|",$r['skur']);
		list($km,$km_sats,$km_fra)=explode("|",$r['korsel']);
#cho "$km,$km_sats,$km_fra<br>";
	if ($ansatte) {
			$ansat_id=explode(chr(9),$ansatte);
			$loen_fordeling=explode(chr(9),$fordeling);
			$loen_date=explode(chr(9),$datoer);
			$loen_loen=explode(chr(9),$loen);
			$loen_timer=explode(chr(9),$timer);
			$loen_50pct=explode(chr(9),$t50pct);
			$loen_100pct=explode(chr(9),$t100pct);
			$loen_skur1=explode(chr(9),$skur1);
			$loen_skur2=explode(chr(9),$skur2);
			$loen_km=explode(chr(9),$km);
		} 
	}
	if ($loen_art=='akkord' || $loen_art=='akktimer'){
		if ($master_id) {
#cho "select nummer from loen where id='$master_id'<br>";
			if ($r2=db_fetch_array(db_select("select nummer from loen where id='$master_id' and sag_id='$sag_id' and opg_id='$opg_id'",__FILE__ . " linje " . __LINE__))) {
				$master_nr=$r2['nummer'];
#cho "Bundet på seddel nr $master_nr<br>";
			}	else {
#cho "update loen set master_id='0',godkendt='' where id='$id'<br>";
				db_modify("update loen set master_id='0',godkendt='' where id='$id'",__FILE__ . " linje " . __LINE__);
				$master_id=NULL;
				$master_nr=NULL;
				$godkendt=NULL;
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
	}
	
	if ($loen_art=='akk_afr' && $sag_id && !$afsluttet) {
	## 20130301 Finder ikke afvist selvom afvist er '' - derfor dette.	
#		if ($opg_id) $qtxt="select * from loen where sag_id = '$sag_id' and opg_id='$opg_id' and art='akktimer' and afvist='' and (master_id='$id' or master_id=0 or master_id is NULL) and id != '$id' order by loendate";
#		else $qtxt="select * from loen where sag_id = '$sag_id' and kategori = '$listevalg' and art='akktimer' and afsluttet='' and afvist='' and  and id != '$id' order by loendate";
		#20131003 tilføjet and opg_id='$opg_id' 
		$qtxt="select * from loen where sag_id = '$sag_id' and opg_id='$opg_id' and art='akktimer' and id != '$id' and (master_id='$id' or master_id='0' or master_id is NULL) order by loendate";
#cho "$qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		$y;
		while ($r=db_fetch_array($q)) {
#cho "ID $r[id]<br>";
		#cho "(".!trim($r['afvist'])." and (".!trim($r['afsluttet'])," || ".$r['opg_id']."==$opg_id))";
			if (!trim($r['afvist']) and ((!trim($r['afsluttet']) and $r['kategori']==$listevalg) || $r['opg_id']==$opg_id)) {	## 20130301 Query finder ikke afvist selvom afvist er '' - derfor dette.	
# 			if (!trim($r['afvist']) and !trim($r['afsluttet']) and $r['opg_id']==$opg_id)) {	## 20130301 Query finder ikke afvist selvom afvist er '' - derfor dette.	
				if ($ansatte){ # 20141103 
					$ansatte.=chr(9).$r['ansatte'];
					$fordeling.=chr(9).$r['fordeling'];
					$loen.=chr(9).$r['loen'];
					$timer.=chr(9).$r['timer'];
					$t50pct.=chr(9).$r['t50pct'];
					$t100pct.=chr(9).$r['t100pct'];
					list($s1,$s2)=explode("|",$r['skur']);
					$skur1.=chr(9).$s1;
					$skur2.=chr(9).$s2;
					list($k1,$km_sats,$km_fra)=explode("|",$r['korsel']);
					$km.=chr(9).$k1;
				} else {
					$ansatte=$r['ansatte'];
					$fordeling=$r['fordeling'];
					$loen=$r['loen'];
					$timer=$r['timer'];
					$t50pct=$r['t50pct'];
					$t100pct=$r['t100pct'];
					list($s1,$s2)=explode("|",$r['skur']);
					$skur1=$s1;
					$skur2=$s2;
					list($k1,$km_sats,$km_fra)=explode("|",$r['korsel']);
					$km=$k1;
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
			$loen_km=explode(chr(9),$km);
		}
	}
	$x=0;
	$a_id=array();$a_vare_id=array();$a_stk=array();$a_txt=array();$a_pris=array();$a_pct=array();
	$q = db_select("SELECT * FROM loen_enheder WHERE loen_id = '$id' and vare_id = '0'",__FILE__ . " linje " . __LINE__);
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
		$q = db_select("SELECT * FROM opgaver WHERE status != 'Ordrebekræftelse' AND status != 'Tilbud' AND status != 'Afsluttet' AND assign_to = 'sager' AND assign_id = '$sag_id' ORDER BY nr",__FILE__ . " linje " . __LINE__);
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
				$aa_sum+=($r['op_40']*$r['pris_op']*0.4);
				$aa_sum+=($r['op_60']*$r['pris_op']*0.6);
				$aa_sum+=($r['op_30m']*$r['op_30m']*0.1);
				$aa_sum+=($r['ned']*$r['pris_ned']);
				$aa_sum+=($r['ned_25']*$r['pris_ned']*0.25);
				$aa_sum+=($r['ned_40']*$r['pris_ned']*0.4);
				$aa_sum+=($r['ned_60']*$r['pris_ned']*0.6);
				$aa_sum+=($r['ned_30m']*$r['pris_ned']*0.1);
				$x++;
#cho "AA 	$aa_sum  | $r[tekst] $r[op]*$r[pris_op] $r[ned]*$r[pris_ned]<br>";
			} else db_modify("delete from loen_enheder WHERE id = '$r[id]'",__FILE__ . " linje " . __LINE__);
			
#cho "$r[vare_id] || $r[tekst] $r[op] $r[ned]<br>";
#cho "$r[op]*$r[pris_op] | $r[ned]*$r[pris_ned] |".$r['op']*$r['pris_op']."|".$r['ned']*$r['pris_ned']."| aa_sum $aa_sum<br>";
		}
	}
	$aa_sum80=$aa_sum*0.8;
	$aa_sum20=$aa_sum*0.2;
	$r=db_fetch_array(db_select("select sagsnr,udf_addr1 from sager where id = '$sag_id'",__FILE__ . " linje " . __LINE__));
	$sag_nr=$r['sagsnr'];
	$sag_addr=$r['udf_addr1'];

	for ($x=0;$x<count($ansat_id);$x++) {
		$ansat_id[$x]*=1;
#cho "select * from ansatte where id = '$ansat_id[$x]'<br>";
		$r=db_fetch_array(db_select("select * from ansatte where id = '$ansat_id[$x]'",__FILE__ . " linje " . __LINE__));
		$medarb_nr[$x]=$r['nummer'];
		$medarb_navn[$x]=$r['navn'];
#cho "$medarb_nr[$x] $medarb_navn[$x]<br>";
		$medarb_trainee[$x]=$r['trainee'];
		$medarb_startdate[$x]=$r['startdate'];
		$medarb_loen[$x]=str_replace(",",".",$r['loen'])*1;
		$medarb_extraloen[$x]=str_replace(",",".",$r['extraloen'])*1;
#cho "$medarb_trainee[$x] t $traineemdr $traineepct<br>";
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
	$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$y]=$r['kodenr'];
		$cat_navn[$y]=$r['beskrivelse'];
		$y++;
	}

	$antal_cat=$y;
	if($loendate=='1970-01-01') { 
		$loendate='';
		$loendato='';
		$datotext_errortxt="<span style=\"color: red;\">Dato ikke udfyld</span>";
		$datotext_error="style=\"border: 1px solid red;-webkit-padding-before: 1px;-webkit-padding-after: 1px;-webkit-padding-start: 1px;-webkit-padding-end: 1px;\"";
	} else { 
		$loendato=dkdato($loendate); 
		setlocale(LC_TIME, "danish"); 
		if ($loendate==NULL) {
			$loen_datotext=NULL;
		} else {
			$loen_datotext = strftime('%A den %d. %B %Y',strtotime($loendate));
			if ($db_encode=='UTF8') $loen_datotext=utf8_encode($loen_datotext); 
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
	print "<form name=\"loen\" action=\"loen.php?id=$id&funktion=ret_loen\" method=\"post\">
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
			<input type=\"hidden\" name=\"km_fra\" value=\"$km_fra\">";
		}
		for($x=0;$x<count($a_id);$x++) print "<input type=\"hidden\" name=\"a_id[$x]\" value=\"$a_id[$x]\">";
		for ($x=0;$x<count($ansat_id);$x++) {
			print "<input type=\"hidden\" name=\"ansat_id[$x]\" value=\"$ansat_id[$x]\">"; 
			print "<input type=\"hidden\" name=\"loen_id[$x]\" value=\"$loen_id[$x]\">"; 
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
								if ($loen_art!='aconto' && substr($sag_rettigheder,6,1)) print "<option value=\"aconto\">Aconto</option>";
								if ($loen_art!='akktimer') print "<option value=\"akktimer\">Dyrtid</option>";
								if ($loen_art!='akk_afr') print "<option value=\"akk_afr\">Akkord afregning</option>";
								if ($loen_art!='akkord') print "<option value=\"akkord\">Akkord med dyrtid</option>";
								if ($loen_art!='timer') print "<option value=\"timer\">Timeløn</option>";
								if ($loen_art!='plads') print "<option value=\"plads\">Pladsarbejde</option>";
								if ($loen_art!='skole') print "<option value=\"skole\">Skoleophold</option>";
								if ($loen_art!='sygdom') print "<option value=\"sygdom\">Sygdom</option>";
								if ($loen_art!='barn_syg') print "<option value=\"barn_syg\">Barn syg</option>";
								if ($loen_art!='ferie') print "<option value=\"ferie\">Ferie</option>"; #20140627
								if ($loen_art!='regulering' && substr($sag_rettigheder,6,1)) print "<option value=\"regulering\">Regulering</option>";
							print "</select>
						</div>
						<div class=\"clear\"></div></div>";
						if ($loen_art=='akk_afr' || $loen_art=='akkord' || $loen_art=='akktimer') {
							print "<div class=\"row\"><div class=\"leftSmall\">Type:</div>
							<div class=\"rightLarge\">
								<select name=\"nyt_listevalg\" $readonly class=\"akkordlistevalg printSelect2\" style=\"width: 100%;\">";
									if (!$listevalg) print "<option value=\"0\">Vælg type</option>";
									for ($y=0;$y<$antal_cat;$y++) {
										if ($cat_id[$y]==$listevalg) print "<option value=$cat_id[$y]>$cat_navn[$y]</option>";
									}						  
									if (!$readonly) {
										for ($y=0;$y<$antal_cat;$y++) {
											if ($cat_id[$y]!=$listevalg) print "<option value=$cat_id[$y]>$cat_navn[$y]</option>";
										}						  
									}
								print "</select><input type=\"hidden\" name=\"listevalg\" value=\"$listevalg\">
							</div>
							<div class=\"clear\"></div></div>";
						} else print "<input type=\"hidden\" name=\"listevalg\" value=\"$listevalg\">";
#					print "</div>";
					if ($loen_art!='sygdom' && $loen_art!='barn_syg' &&  $loen_art!='skole' &&  $loen_art!='plads' && $loen_art!='ferie') { #20140627 
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
					if ($afvist_pga) {
						print "<div class=\"row\">
							<div class=\"leftSmall\">Årsag til afvisning:</div>";
							print "<div class=\"right\"><b style=\"color: #cd3300;padding-left: 4px;\">$afvist_pga</b></div>
							<div class=\"clear\"></div>
						</div>";
					}
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
					if ($godkendt) {
						print "<tr><td><b>Godkendt:</b></td><td>d.".date("d-m-Y",$godkendt)." kl. ".date("H:i",$godkendt)."</td>
							<td><b>af:</b> $godkendt_af</td>";
 							if ($master_nr) print"<td><b>Afr. på&nbsp; : </b>$master_nr</td>"; #20151215 
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
						if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') print "<th>Timer</th>";
						if ($loen_art=='akk_afr'||$loen_art=='akktimer'||$loen_art=='akkord'||$loen_art=='timer') {
							print "<th>50%</th>
							<th>100%</th>";
							print "<th width=\"36\" title=\"Skur lav sats ($skur_sats1)\">S(L)</th>";
							print "<th width=\"36\" title=\"Skur høj sats ($skur_sats2)\">S(H)</th>";
							print "<th>Km</th>";
							print "<th>Sum</th>";
							if ($loen_art=='timer') print "<th>Timetillæg</th>";
							else print "<th>Akkord</th>";
						}
						if ($loen_art=='aconto') print "<th>Aconto bel&oslash;b</th>";
						elseif ($loen_art=='regulering') print "<th>Bel&oslash;b</th>";
						elseif ($loen_art!='ferie') print "<th>I Alt</th>";
						//else print "<th>I Alt</th>";
						print "<!--<th width=\"20\"></th>-->
					</tr>
					</thead>
				<tbody class=\"akkordTableBody akkordTableBorderAll\">\n";
				
				$l_timer=0;
				for($x=0;$x<=count($ansat_id);$x++) {
				if (isset($loen_timer[$x])) {
#cho "$loen_fordeling[$x] :: $fordel_timer[$x]=$loen_timer[$x]*$loen_fordeling[$x]/100<br>";
				if ($loen_fordeling[$x]) $fordel_timer[$x]=$loen_timer[$x]*$loen_fordeling[$x]/100;
					else $fordel_timer[$x]=$loen_timer[$x];
#cho "$fordel_timer[$x]<br>";
					$l_timer+=$fordel_timer[$x];
					}
#cho "$loen_timer[$x] :: $fordel_timer[$x]<br>";
				}
				$f_sum=0;
				$t_sum=0;
				if ($loen_art!='aconto' && $loen_art!='regulering') $sum=0;
				$aa=count($ansat_id);
				if ($aa<1) $aa++;
				if ($loen_art=='akk_afr' || $readonly) {
					$beskyttet="readonly=\"readonly\"";
					$aa--;
				} elseif ($loen_art=='aconto' || $loen_art=='regulering') $aa=0;
				for($x=0;$x<=$aa;$x++) {
					$aa_belob[$x]=0;
#					$loen_sum[$x]=0;
					if ($loen_art!='akk_afr') $loen_date[$x]=$loendate;
					if (!isset($ansat_id[$x])) $ansat_id[$x]=NULL;
					if (!isset($medarb_nr[$x])) $medarb_nr[$x]=NULL;
					if (!isset($medarb_navn[$x])) $medarb_navn[$x]=NULL;
					if (!isset($loen_fordeling[$x])) $loen_fordeling[$x]=NULL;
					if (!isset($loen_loen[$x])) $loen_loen[$x]=0;
					if (!isset($loen_timer[$x])) $loen_timer[$x]=0;
					if (!isset($loen_50pct[$x])) $loen_50pct[$x]=0;
					if (!isset($loen_100pct[$x])) $loen_100pct[$x]=0;
					if (!isset($loen_date[$x])) $loen_date[$x]=NULL;
					if (!isset($loen_skur1[$x])) $loen_skur1[$x]=0;
					elseif ($loen_skur1[$x]>0) $l_skur1[$x]="checked=\"checked\"";
					else $l_skur1[$x]=NULL;
					if (!isset($loen_skur2[$x])) $loen_skur2[$x]=0;
					elseif ($loen_skur2[$x]>0) $l_skur2[$x]="checked=\"checked\""; 
					else $l_skur2[$x]=NULL;
					if (!isset($loen_km[$x])) $loen_km[$x]=0;
					if (!$afsluttet && $ansat_id[$x]) {
						if ($loen_art=='sygdom') $loen_loen[$x]=$sygdom_sats;
						elseif ($loen_art=='barn_syg') $loen_loen[$x]=$sygdom_sats;	
						elseif ($loen_art=='skole') $loen_loen[$x]=$skole_sats;
						elseif ($loen_art=='plads') $loen_loen[$x]=$plads_sats;
						elseif ($loen_art=='timer') $loen_loen[$x]=$medarb_loen[$x];#+$medarb_extraloen[$x];
						else $loen_loen[$x]=$medarb_extraloen[$x];
					} 
#cho "$t_belob[$x]=$loen_loen[$x]*$loen_timer[$x]+$loen_loen[$x]*$loen_50pct[$x]/2+$loen_loen[$x]*$loen_100pct[$x]<br>";
#					$t_belob[$x]=$loen_loen[$x]*$loen_timer[$x]+$loen_loen[$x]*$loen_50pct[$x]/2+$loen_loen[$x]*$loen_100pct[$x];
					$t_belob[$x]=$loen_loen[$x]*$loen_timer[$x]+$overtid_50pct*$loen_50pct[$x]+$overtid_100pct*$loen_100pct[$x];
#cho "$aa_sum aa $aa_belob[$x] $aa_sum/$l_timer*$fordel_timer[$x]<br>";
					if ($loen_timer[$x] && $l_timer) $aa_belob[$x]=$aa_sum/$l_timer*$fordel_timer[$x];
#cho "$aa_belob[$x]=$aa_sum/$l_timer*$fordel_timer[$x]<br>";
					$loen_sum[$x]=$t_belob[$x]+$aa_belob[$x]+$loen_skur1[$x]+$loen_skur2[$x];
#cho "$loen_sum[$x]<br>";
					if ($loen_km[$x] || $skur1[$x] || $skur2[$x]){ #&& $loen_art!='akk_afr') {
						$t_km=0;
						$tjek=0;
						
						$qtxt="select * from loen where (art='akktimer' or art='akkord' or art='timer') and loendate='$loen_date[$x]' and id < '$id' and afvist = '' order by id";
#cho "$qtxt<br>";			
						$q=db_select($qtxt,__FILE__ . " linje " . __LINE__); # finder hvormeget kørsel personen har haft samme dag. (incl aktuelle seddel). 
						while ($r=db_fetch_array($q)) {
#cho "ID $r[id]<br>";						
							$a=explode(chr(9),$r['ansatte']);
							if (in_array($ansat_id[$x],$a)) {
								$k=explode("|",$r['korsel']);
#cho "$r[id]:$k[0] -> ";		
								for ($i=0;$i<count($a);$i++) { #20150623
									if ($a[$i]==$ansat_id[$x]) {
#cho "$t_km -> ";
										$t_km+=$k[0];    
#cho "$t_km<br>";
									}
								}
							}
							$tjek=1;
						} # 20150617 Flytte fra over '$fratræk' længere nede da km blev forkert
#cho "$t_km -> $loen_km[$x]<br>";
						if ($t_km==$loen_km[$x]) {
							if ($km_fra<=$loen_km[$x]) {
								$fratraek[$x]=$km_fra;
							}	else $fratraek[$x]=$loen_km[$x];
						} elseif ($t_km-$loen_km[$x]<$km_fra) $fratraek[$x]=$km_fra-$t_km; # 20150928 
					}
					if ($loen_km[$x] >= $fratraek[$x]) $loen_sum[$x]+=($loen_km[$x]-$fratraek[$x])*$km_sats; 
					else $fratraek[$x]=$loen_km[$x]; # 20151009
					if ($x<=count($ansat_id)) $sum+=$loen_sum[$x];
					$t_sum+=$fordel_timer[$x];
					if (!$loen_loen[$x]) $loen_loen[$x]=NULL;
					if (!$loen_timer[$x]) $loen_timer[$x]=NULL;
					if (!$loen_50pct[$x]) $loen_50pct[$x]=NULL;
					if (!$loen_100pct[$x]) $loen_100pct[$x]=NULL;
					if (!$loen_km[$x]) $loen_km[$x]=NULL;
					if (!$loen_sum[$x]) $loen_sum[$x]=NULL;
					if ($loen_fordeling[$x] && $loen_fordeling[$x]<100) $medarb_navn[$x].=" (Under oplæring)";
					
					print "<tr>\n";
						if ($loen_art=='akk_afr') print "<td title=\"Akkord seddel nr: $akkord_nr[$x]\"><input type=\"text\" $beskyttet placeholder=\"Dato\" name=\"loen_date[$x]\" class=\"medarbejdernr printBorderNone\" value=\"".dkdato($loen_date[$x])."\" style=\"width:66px;\"></td>\n";
						print "<td><input type=\"text\" $beskyttet placeholder=\"Med. nr.\" name=\"medarb_nr[$x]\" class=\"medarbejdernr printBorderNone\" value=\"$medarb_nr[$x]\" style=\"width:56px;\"></td>
						<td><input type=\"text\" $beskyttet placeholder=\"Medarbejder navn\" name=\"medarb_navn[$x]\" class=\"medarbejdernavn printBorderNone\" value=\"$medarb_navn[$x]\" style=\"width:260px\">\n";
						if ($loen_art!='ferie') print "</td>\n";
						if ($loen_art!='aconto' && $loen_art!='regulering' && $loen_art!='ferie') print "<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_timer[$x]\" class=\"zeroValue alignRight printBorderNone\" value=\"".str_replace(".",",",$loen_timer[$x])."\" style=\"width:33px;\"></td>\n";
						if ($loen_art=='akk_afr'||$loen_art=='akktimer'||$loen_art=='akkord'||$loen_art=='timer') {
							print "<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_50pct[$x]\" class=\"alignRight printBorderNone\" value=\"".str_replace(".",",",$loen_50pct[$x])."\" style=\"width:33px;\"></td>
							<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_100pct[$x]\" class=\"alignRight printBorderNone\" value=\"".str_replace(".",",",$loen_100pct[$x])."\" style=\"width:33px;\"></td>\n";
							if ($beskyttet || $retskur[$x]) {
								print "<td class=\"alignCenter\"><input name=\"skur1[$x]\" disabled=\"disabled\" type=\"checkbox\" $l_skur1[$x]></td><td class=\"alignCenter\"><input name=\"skur2[$x]\" disabled=\"disabled\" type=\"checkbox\" $l_skur2[$x]></td>\n";
							} else print "<td class=\"alignCenter\"><input name=\"skur1[$x]\" type=\"checkbox\" $l_skur1[$x]></td><td class=\"alignCenter\"><input name=\"skur2[$x]\" type=\"checkbox\" $l_skur2[$x]></td>\n";
							print "<td title=\"Fratrukket $fratraek[$x] kilometer\"><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"loen_km[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($loen_km[$x])."\" style=\"width:33px;\"></td>
							<td><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"t_belob[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($t_belob[$x])."\" style=\"width:50px;\"></td>
							<td><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"aa_belob[$x]\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($aa_belob[$x])."\" style=\"width:50px;\"></td>\n";
						}
//						<!--						<td><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"fordel_belob[$x]\" class=\"alignRight\" value=\"".dkdecimal($fordel_belob[$x])."\" style=\"width:60px;\"></td>-->
						if ($loen_art=='aconto' || $loen_art=='regulering') print "<td class=\"alignRight\" ><input type=\"text\" $beskyttet placeholder=\"0,00\" name=\"dksum\" class=\"alignRight printBorderNone\" value=\"".dkdecimal($sum)."\" style=\"width:70px;\">\n";
						elseif ($loen_art!='ferie') print "<td class=\"alignRight\" ><input type=\"text\" readonly=\"readonly\" placeholder=\"0,00\" name=\"loen_sum[$x]\" class=\"alignRight placeholderLoen printBorderNone\" value=\"".dkdecimal($loen_sum[$x])."\" style=\"width:50px;\">\n";
						print "<input type=\"hidden\" name=\"loen_loen[$x]\" value=\"$loen_loen[$x]\">\n";
						if ($beskyttet || $retskur[$x]) {
								print "<input name=\"skur1[$x]\" type=\"hidden\" value=\"$l_skur1[$x]\"><input name=\"skur2[$x]\" type=\"hidden\" value=\"$l_skur2[$x]\">\n";
						}
#						<!--<td><button class=\"xmark delRow \"></button></td>-->
					print "</td></tr>\n";
					
				}
				print "</tbody>\n";
				if ($loen_art!='ferie') { # 20140627
					print "<tbody class=\"akkordTableBody akkordTableBorderBottom\">";
					if ($loen_art=='timer' || $loen_art=='akktimer' || $loen_art=='akkord')$colspan=9;
					elseif ($loen_art=='akk_afr')$colspan=10;
					else $colspan=3;
					print "<tr><td colspan=\"2\"><b>Sum</b></td><td class=\"alignRight\" colspan=\"$colspan\"><b>".dkdecimal($sum)."</b><input type=\"hidden\" name=\"sum\" value=\"$sum\"></td>";
					print "</tbody>";
					//print "<input type=\"hidden\" name=\"sum\" value=\"$sum\">";
				}
				#cho "update loen set sum='$sum' where id='$id'<br>";
				if (!$afsluttet || $afslut) db_modify("update loen set sum='$sum' where id='$id'",__FILE__ . " linje " . __LINE__); #20130604

#				print "<tbody class=\"akkordTableBody\">
#					<tr>
#						<td colspan=\"8\"class=\"alignRight\">Tilføj ny række&nbsp;</td>
#						<td><button class=\"cross addRow\" ></button></td>
#					</tr>
#				</tbody> -->
		print "</table>  
		</div><!-- end of content -->";
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
							<th rowspan=\"2\" class=\"alignRight\" width=\"50px\">Sum</th>
							<th colspan=\"2\" width=\"25px\">25%</th>
							<th colspan=\"2\" width=\"25px\">40%</th>
							<th colspan=\"2\" width=\"25px\">60%</th>
							<th colspan=\"2\" width=\"25px\">+30m</th>
							<th rowspan=\"2\" class=\"alignRight\">Beløb</th>
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
				$sum=vis_liste($id,$listevalg,$afsluttet,$godkendt);
#cho "sum fra liste $sum<br>";
				print "<tr>
					<td colspan=\"13\" class=\"tableSagerBorder\"><b>Lønlinjer ialt:</b></td>
					<td colspan=\"2\" align=\"right\" class=\"tableSagerBorder\" style=\"padding-right: 1px;\"><b>".dkdecimal($sum)."</b></td>						
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
								for($x=0;$x<=count($a_id);$x++) {
									if (!isset($a_stk[$x])) $a_stk[$x]=NULL;
									if (!isset($a_pris[$x])) $a_pris[$x]=NULL;
									if (!isset($a_txt[$x])) $a_txt[$x]=NULL;
#									if (!isset($a_pct[$x])) $a_pct[$x]=NULL;
									$a_linjesum[$x]=$a_stk[$x]*$a_pris[$x];
									$a_sum+=$a_linjesum[$x];
									print "<tr>
										<td><input type=\"text\" $readonly style=\"width:36px; text-align: right;\" class=\"printBorderNone\" name=\"a_stk[$x]\" value=\"".str_replace(".",",",$a_stk[$x])."\"></td>
										<td><input type=\"text\" $readonly style=\"width:596px; text-align: left;\" class=\"printBorderNone\" name=\"a_txt[$x]\" value=\"$a_txt[$x]\"></td>
										<td><input type=\"text\" $readonly style=\"width:76px; text-align: right;\" class=\"printBorderNone\" placeholder=\"0,00\" name=\"a_pris[$x]\" value=\"".dkdecimal($a_pris[$x])."\"></td>
										<!--<td><input type=\"text\" $readonly style=\"width:76px; text-align: right;\" placeholder=\"100%\" name=\"a_pct[$x]\" value=\"".str_replace(".",",",$a_pct[$x])."\"></td>-->
										<td class=\"alignRight\">".dkdecimal($a_linjesum[$x])."<input type=\"hidden\" name=\"a_id[$x]\" value=\"$a_id[$x]\"></td>
										<!--<td><button class=\"xmark delRow2\"></button></td>-->
									</tr>";
								}
								print "</tbody>
								<tbody class=\"akkordTableBody2 akkordTableBorderBottomAll\">

									<tr>
										<td colspan=\"3\"><b>Andet Ialt:</b></td>
										<td colspan=\"1\" class=\"alignRight\"><b>".dkdecimal($a_sum)."
										<input type=\"hidden\" name=\"a_sum\" value=\"$a_sum\"></b></td>
										
									</tr>
									<tr>
										<td colspan=\"3\"><b>Akkord Ialt:</b></td>
										<td colspan=\"1\" class=\"alignRight\"><b>".dkdecimal($sum)."
										<input type=\"hidden\" name=\"sum\" value=\"$sum\"></b></td>
									</tr>
									<tr>
										<td colspan=\"3\"><b>Til fordeling:</b></td>
										<td colspan=\"1\" class=\"alignRight\" style=\"#border-bottom: 3px double #444;\">
										<b>".dkdecimal($a_sum+$sum)."</b></td> 
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
							if (!$sum && !$a_sum && $id) print "<input name=\"slet\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Slet\" onclick=\"return confirm('Bekræft sletning')\">"; // Indsat $id, så slet først kommer frem efter der er trykket gem
							print "<input name=\"luk\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Luk\">";
							//if ((($loen_art && $loen_art!='akktimer') || $opg_id) && $sum) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='akktimer' || $loen_art=='akk_afr' || $loen_art=='akkord') && $sum && $loendato && $loen_tekst && ($opg_nr || ($sag_id && !$opgave_id)) && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='timer' || $loen_art=='aconto' || $loen_art=='regulering') && $sum && $loendato && $loen_tekst && (!$sag_id || $opg_nr || ($sag_id && !$opgave_id)) && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if (($loen_art=='plads' || $loen_art=='sygdom' || $loen_art=='barn_syg' || $loen_art=='skole') && $sum && $loendato && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
							if ($loen_art=='ferie' && $feriefra && $ferietil && $loendato && (!empty($medarb_nr[0]))) print "<input name=\"afslut\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Overfør\" onclick=\"return confirm('Bekræft overførsel')\">";
						}
#cho "(substr($sag_rettigheder,6,1) && $afsluttet && !$godkendt && !$afvist)<br>";						
						if (substr($sag_rettigheder,6,1) && $afsluttet && !$godkendt && !$afvist) {
							print "<input name=\"godkend\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Godkend\" onclick=\"return confirm('Bekræft godkendelse')\">";
							print "<input name=\"afvis\" type=\"submit\" class=\"button gray medium textSpaceLarge\" value=\"Afvis\" onclick=\"return confirm('Bekræft afvisning')\">";
						}
						if (substr($sag_rettigheder,6,1) && $godkendt && !$afregnet) {
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
}

?>
