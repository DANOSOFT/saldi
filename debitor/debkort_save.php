<?php
// ---------------------debitor/debkort_save.php----lap 3.4.1---2014-05-05---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2014.05.05 indsat  >>|| $_POST['firmanavn']<< da man ikke kunne oprette nye kunder. (PHR - Danosoft) Søg20140505

if ($delete_category=if_isset($_GET['delete_category'])) {
	$r=db_fetch_array(db_select("select * from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
	$cat_id=explode(chr(9),$r['box1']);
	$cat_beskrivelse=explode(chr(9),$r['box2']);
	$cat_antal=count($cat_id);
	for ($x=0;$x<$cat_antal;$x++) {
		if ($cat_id[$x]!=$delete_category) {
			($box1)?$box1.=chr(9).$cat_id[$x]:$box1=$cat_id[$x];
			($box2)?$box2.=chr(9).db_escape_string($cat_beskrivelse[$x]):$box2=db_escape_string($cat_beskrivelse[$x]);
		}
	}
	$delete_category=0;
	db_modify("update grupper set box1='$box1',box2='$box2' where art = 'DebInfo'",__FILE__ . " linje " . __LINE__);  
}
$rename_category=if_isset($_GET['rename_category']);
if ($_POST['id'] || $_POST['firmanavn']) { #20140505
	$submit=db_escape_string(trim($_POST['submit']));
 	$id=$_POST['id'];
 	if ($submit!="Slet") {
		$notes=$_POST['notes'];
		$firmanavn=db_escape_string(trim($_POST['firmanavn']));
		$addr1=db_escape_string(trim($_POST['addr1']));
		$addr2=db_escape_string(trim($_POST['addr2']));
		$postnr=db_escape_string(trim($_POST['postnr']));
		$bynavn=db_escape_string(trim($_POST['bynavn']));
		$land=db_escape_string(trim($_POST['land']));
		$kontakt=db_escape_string(trim($_POST['kontakt']));
		$tlf=db_escape_string(trim($_POST['tlf']));
		$email=db_escape_string(trim($_POST['email']));
		$mailfakt=db_escape_string(trim($_POST['mailfakt']));
		$cvrnr=db_escape_string(trim($_POST['cvrnr']));
		$kontonr=db_escape_string(trim($_POST['kontonr']));
		$felt_1 = db_escape_string(trim($_POST['felt_1']));
		$notes=db_escape_string(trim($_POST['notes']));
/*
		if ( !$id && !$firmanavn && !$kontonr && $notes ) {
			$noteslinjer = explode("\n", $notes);
			$firmanavn = felt_fra_tekst("Firma: ", $noteslinjer);
			$addr1   = felt_fra_tekst("Adresse: ", $noteslinjer);
			$addr2   = felt_fra_tekst("         ", $noteslinjer);
			$postnr = preg_replace("/^[^ ]* ([^ ]*) .*$/", "$1", felt_fra_tekst("Postnr.By: ", $noteslinjer));
			$bynavn = preg_replace("/^[^ ]* [^ ]* (.*)$/", "$1", felt_fra_tekst("Postnr.By: ", $noteslinjer));
			$email = felt_fra_tekst("e-mail: ", $noteslinjer);
			$cvrnr = str_replace(" ", "", felt_fra_tekst("Cvr: ", $noteslinjer));
			$tlf = str_replace("+45", "", str_replace(" ", "", felt_fra_tekst("Telefon: ", $noteslinjer)));
			$felt_1 = felt_fra_tekst("Regnskab: ", $noteslinjer);
			$kontakt = felt_fra_tekst("Navn: ", $noteslinjer);
			$land = "DK";
			$mailfakt = 1;
			$notes = "";
		}
*/
		$ny_kontonr=db_escape_string(trim($_POST['ny_kontonr']));
		$gl_kontotype=db_escape_string(trim($_POST['gl_kontotype']));
		$kontotype=db_escape_string(trim($_POST['kontotype']));
		$fornavn=db_escape_string(trim($_POST['fornavn']));
		$efternavn=db_escape_string(trim($_POST['efternavn']));
		$fax=db_escape_string(trim($_POST['fax']));
		$web=db_escape_string(trim($_POST['web']));
		$betalingsbet=db_escape_string(trim($_POST['betalingsbet']));
		$ean=db_escape_string(trim($_POST['ean']));
		$institution=db_escape_string(trim($_POST['institution']));
		$betalingsdage=(int)$_POST['betalingsdage'];
		$kreditmax=usdecimal($_POST['kreditmax']);
		$felt_2 = db_escape_string(trim($_POST['felt_2']));
		$felt_3 = db_escape_string(trim($_POST['felt_3']));
		$felt_4 = db_escape_string(trim($_POST['felt_4']));
		$felt_5 = db_escape_string(trim($_POST['felt_5']));
		$lev_firmanavn=db_escape_string(trim($_POST['lev_firmanavn']));
		$lev_fornavn=db_escape_string(trim($_POST['lev_fornavn']));
		$lev_efternavn=db_escape_string(trim($_POST['lev_efternavn']));
		$lev_addr1=db_escape_string(trim($_POST['lev_addr1']));
		$lev_addr2=db_escape_string(trim($_POST['lev_addr2']));
		$lev_postnr=db_escape_string(trim($_POST['lev_postnr']));
		$lev_bynavn=db_escape_string(trim($_POST['lev_bynavn']));
		$lev_land=db_escape_string(trim($_POST['lev_land']));
		$lev_kontakt=db_escape_string(trim($_POST['lev_kontakt']));
		$lev_tlf=db_escape_string(trim($_POST['lev_tlf']));
		$lev_email=db_escape_string(trim($_POST['lev_email']));
		$vis_lev_addr=db_escape_string(trim($_POST['vis_lev_addr']));
		$lukket=db_escape_string(trim($_POST['lukket']));
		list ($gruppe) = explode (':', $_POST['gruppe']);
		
		$rabatgruppe=(int)$_POST['rabatgruppe'];
		$kontoansvarlig=$_POST['kontoansvarlig'];
 		$bank_reg=$_POST['bank_reg'];
 		$bank_konto=$_POST['bank_konto'];
 		$pbs_nr=$_POST['pbs_nr'];
		$pbs=$_POST['pbs'];
 		$ordre_id=$_POST['ordre_id'];
 		$returside=$_POST['returside'];
 		$fokus=$_POST['fokus'];
 		$posnr=$_POST['posnr'];
 		$ans_id=$_POST['ans_id'];
 		$ans_ant=$_POST['ans_ant'];
		
		$cat_valg=$_POST['cat_valg'];
		$cat_id=$_POST['cat_id'];
		$cat_beskrivelse=$_POST['cat_beskrivelse'];
		$cat_antal=(int)$_POST['cat_antal'];
		$ny_kategori=$_POST['ny_kategori'];
		$rename_category=if_isset($_POST['rename_category']);

		$status=db_escape_string(trim($_POST['status']));
		$ny_status=db_escape_string(trim($_POST['ny_status']));
		$status_id=if_isset($_POST['status_id'],array());
		$status_beskrivelse=$_POST['status_beskrivelse'];
		$status_antal=count($status_id);
		$rename_status=if_isset($_POST['rename_status']);

		if ($gl_kontotype=='privat') {
			$firmanavn=trim($fornavn." ".$efternavn);
			$lev_firmanavn=trim($lev_fornavn." ".$lev_efternavn);
		}

#		if (!$pbs) $pbs_nr=NULL; #20121023
		($status=='new_status')?$new_status=1:$new_status=0;
		$status = (int)$status;

		if (substr($ny_kontonr,0,1)=="=") {
			$ny_kontonr=str_replace("=","",$ny_kontonr);

			print "<meta http-equiv=\"refresh\" content=\"0;URL=kontofusion.php?returside=$returside&ordre_id=$ordre_id&id=$id&fokus=$fokus&kontonr=$ny_kontonr\">\n";
			exit;
		}
		if (!$id && !$firmanavn && !$ny_kontonr) {
			if (findtekst(255,$sprog_id)=='Regnskab' && $felt_1>1 && is_numeric($felt_1)) {
				include("../includes/connect.php");
				if ($r=db_fetch_array($q=db_select("select * from regnskab where id='$felt_1'",__FILE__ . " linje " . __LINE__))) {
					$regnskab=db_escape_string($r['regnskab']);
					if ($r=db_fetch_array($q=db_select("select * from kundedata where regnskab='$regnskab' or regnskab_id='$felt_1'",__FILE__ . " linje " . __LINE__))) {
						$ny_kontonr=db_escape_string($r['tlf']);
						$firmanavn=db_escape_string($r['firmanavn']);
						$felt=db_escape_string($r['regnskab']);
						$addr1=db_escape_string($r['addr1']);
						$addr2=db_escape_string($r['addr2']);
						$postnr=db_escape_string($r['postnr']);
						$land=db_escape_string($r['land']);
						$land=db_escape_string($r['land']);	
						$kontakt=db_escape_string($r['kontakt']);
						$tlf=db_escape_string($r['tlf']);
						$email=db_escape_string($r['email']);
						$cvrnr=db_escape_string($r['cvrnr']);
						$kontonr=db_escape_string($r['kontonr']);
						$notes=db_escape_string($r['notes']);
						$mailfakt='on';
						$gruppe=4;
					}
					$felt_1.=" : $regnskab"; 
				}
			 include("../includes/online.php");
			}
		}
		######### Kategorier

		for ($x=0;$x<$cat_antal;$x++) {
			if ($cat_valg[$x]) {
				($kategori)?$kategori.=chr(9).$cat_id[$x]:$kategori=$cat_id[$x];
			}
		}
		$tmp=findtekst(343,$sprog_id);
		if ($ny_kategori && $ny_kategori!=$tmp) {
			if (!$rename_category && in_array($ny_kategori,$cat_beskrivelse)) {
				$alerttekst=findtekst(344,$sprog_id);
			} else {
				if (!$rename_category) {
					$x=1;
					while(in_array($x,$cat_id)) $x++; #finder laveste ledige vaerdi
					$cat_id[$cat_antal]=$x;
					$cat_beskrivelse[$cat_antal]=$ny_kategori;
					$cat_antal++;
				}
				$box1=NULL;$box2=NULL;
				for ($x=0;$x<$cat_antal;$x++) {
					if ($cat_id[$x]==$rename_category) $cat_beskrivelse[$x]=$ny_kategori;
					($box1)?$box1.=chr(9).$cat_id[$x]:$box1=$cat_id[$x];
					($box2)?$box2.=chr(9).db_escape_string($cat_beskrivelse[$x]):$box2=db_escape_string($cat_beskrivelse[$x]);
				}
				$rename_category=0;
				db_modify("update grupper set box1='$box1',box2='$box2' where art = 'DebInfo'",__FILE__ . " linje " . __LINE__);  
			}
		}
		######### Status

		for ($x=0;$x<$status_antal;$x++) {
			if ($status_valg[$x]) {
				($status)?$status.=chr(9).$status_id[$x]:$status=$status_id[$x];
			}
		}
		if ($ny_status) {
			if (!$rename_status && in_array($ny_status,$status_beskrivelse)) {
				$alerttekst=findtekst(344,$sprog_id);
			} else {
				if (!$rename_status) {
					$x=1;
					while(in_array($x,$status_id)) $x++; #finder laveste ledige vaerdi
					$status=$x;
					$status_id[$status_antal]=$x;
					$status_beskrivelse[$status_antal]=$ny_status;
					$status_antal++;
				}
				$box3=NULL;$box4=NULL;
			}
		}
		for ($x=0;$x<$status_antal;$x++) {
			if ($status_id[$x]==$rename_status) $status_beskrivelse[$x]=$ny_status;
			if ($status_id[$x] != $status && !$r=db_fetch_array($q=db_select("select id from adresser where status='$status_id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$status_id[$x]=NULL;
				$status_beskrivelse[$x]=NULL;
			} else {
				($box3)?$box3.=chr(9).$status_id[$x]:$box3=$status_id[$x];
				($box4)?$box4.=chr(9).$status_beskrivelse[$x]:$box4=$status_beskrivelse[$x];
			}
		}
		$rename_status=0;
		db_modify("update grupper set box3='$box3',box4='$box4' where art = 'DebInfo'",__FILE__ . " linje " . __LINE__);  

		######### Tjekker om kontonr er integer
 
 		$temp=str_replace(" ","",$ny_kontonr);
 		$tmp2='';
 		for ($x=0; $x<strlen($temp); $x++){
 		 	$y=substr($temp,$x,1);
 		 	if ((ord($y)<48)||(ord($y)>57)) {$y=0;}
 		 	$tmp2=$tmp2.$y;
 		}
 		$tmp2=(int)$tmp2;
 		if ($tmp2!=$ny_kontonr) {
			$alerttekst=findtekst(345,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 345-->";
		}
 		$ny_kontonr=$tmp2;
/* 	
		if ($pbs) {
			if (!is_numeric($bank_reg)||strlen($bank_reg)!=4) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('Bank reg skal best&aring; af et tal p&aring; 4 cifre for at PBS kan aktiveres')\">\n";
			} elseif (!is_numeric($bank_konto)||strlen($bank_konto)!=10) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('Bank konto skal best&aring; af et tal p&aring; 10 cifre for at PBS kan aktiveres')\">\n";
			} elseif (!is_numeric($cvrnr)||strlen($cvrnr)!=8) {
				$pbs="";	
				print "<BODY onLoad=\"javascript:alert('CVR nr skal best&aring; af et tal p&aring; 8 cifre for at PBS kan aktiveres')\">\n";
			}
		}
*/		
 
		if (!$firmanavn) {
			$alerttekst=findtekst(346,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 346-->\n";
	}
	if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
	if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		if ($kontoansvarlig) {
			if ($r = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
				if ($r = db_fetch_array(db_select("select id from ansatte where initialer = '$kontoansvarlig' and konto_id='$r[id]'",__FILE__ . " linje " . __LINE__))) $kontoansvarlig=$r['id'];
			}
		} elseif ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box2 = 'on'",__FILE__ . " linje " . __LINE__))) {
			$alerttekst=findtekst(347,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 347-->\n";
		}
		if (!$kontoansvarlig) $kontoansvarlig='0';
		if (!$gruppe) {
			$alerttekst=findtekst(348,$sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 348-->\n";
			$gruppe='0';
		}  
 	## Tildeler aut kontonr hvis det ikke er angivet
	 	$ktoliste=array();
 		if (($firmanavn)&&(($ny_kontonr < 1)||(!$ny_kontonr))) {
 		 	if (!$id) {$id="0";}
 		 	$x=0;
 		 	$q = db_select("select kontonr from adresser where art = 'D' and id != $id order by kontonr",__FILE__ . " linje " . __LINE__);
 		 	while ($r = db_fetch_array($q)) {
 		 	 	$x++;
 		 	 	$ktoliste[$x]=$r['kontonr'];
 			}
 			$ny_kontonr=1000;
 			while(in_array($ny_kontonr, $ktoliste)) $ny_kontonr++;
			$alerttekst=findtekst(349,$sprog_id);
				$alerttekst=str_replace('$ny_kontonr',$ny_kontonr,$alerttekst);
			print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 349-->\n";
	}
 	
############################
 		if(!$betalingsdage){$betalingsdage=0;}
 	 	if(!$kreditmax){$kreditmax=0;}
 	 	if ($id==0) {
 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__);
 	 	 	$r = db_fetch_array($q);
 	 	 	if ($r['id']) {
				$alerttekst=findtekst(350,$sprog_id);
				$alerttekst=str_replace('$ny_kontonr',$ny_kontonr,$alerttekst);
				print "<BODY onLoad=\"javascript:alert('$alerttekst')\">";#<!--tekst 350-->\n";
 	 	 	 	$id=0;
 	 	 	} elseif($ny_kontonr) {
				$oprettet=date("Y-m-d");
 	 	 	 	db_modify("insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,mailfakt,web,betalingsdage,kreditmax,betalingsbet,cvrnr,ean,institution,notes,art,gruppe,kontoansvarlig,oprettet,bank_reg,bank_konto,pbs_nr,pbs,kontotype,fornavn,efternavn,lev_firmanavn,lev_fornavn,lev_efternavn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_kontakt,lev_tlf,lev_email,felt_1,felt_2,felt_3,felt_4,felt_5,vis_lev_addr,lukket,kategori,rabatgruppe,status) values ('$ny_kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$tlf', '$fax', '$email','$mailfakt', '$web', '$betalingsdage', '$kreditmax', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'D', '$gruppe', '$kontoansvarlig', '$oprettet','$bank_reg','$bank_konto','$pbs_nr','$pbs','$kontotype','$fornavn','$efternavn','$lev_firmanavn','$lev_fornavn','$lev_efternavn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_land','$lev_kontakt','$lev_tlf','$lev_email','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$vis_lev_addr','$lukket','$kategori','$rabatgruppe','$status')",__FILE__ . " linje " . __LINE__);
 	 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__);
 	 	 	 	$r = db_fetch_array($q);
 	 	 	 	$id = $r['id'];
				$qtxt = "insert into ansatte(konto_id, navn) values ('$id', '$kontakt')";
				if ($kontakt && $id) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
 	 	} elseif ($id > 0) {
 	 	 	if ($ny_kontonr!=$kontonr) {
 	 	 	 	$q = db_select("select kontonr from adresser where art = 'D' order by kontonr",__FILE__ . " linje " . __LINE__);
 	 	 	 	while ($r = db_fetch_array($q)) {
 	 	 	 	 	$x++;
 	 	 	 	 	$ktoliste[$x]=$r[kontonr];
 	 	 	 	}
 	 	 	 	if (in_array($ny_kontonr, $ktoliste)) {
					$alerttekst=findtekst(351,$sprog_id);
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 351-->\n";
	 	 	 	} else {$kontonr=$ny_kontonr;}
 	 	 	}
			db_modify("update adresser set kontonr = '$kontonr', firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', mailfakt = '$mailfakt', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', ean = '$ean', institution = '$institution', notes = '$notes', gruppe = '$gruppe', kontoansvarlig = '$kontoansvarlig',bank_reg='$bank_reg',bank_konto='$bank_konto', pbs_nr = '$pbs_nr', pbs = '$pbs',kontotype='$kontotype',fornavn='$fornavn',efternavn='$efternavn',lev_firmanavn='$lev_firmanavn',lev_fornavn='$lev_fornavn',lev_efternavn='$lev_efternavn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_land='$lev_land',lev_kontakt='$lev_kontakt',lev_tlf='$lev_tlf',lev_email='$lev_email',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',vis_lev_addr='$vis_lev_addr',lukket='$lukket',kategori='$kategori',rabatgruppe='$rabatgruppe',status='$status' where id = '$id'",__FILE__ . " linje " . __LINE__);
 	 	 	for ($x=1; $x<=$ans_ant; $x++) {
 	 	 	 	$y=trim($posnr[$x]);
 	 	 	 	if ($y && is_numeric($y) && $ans_id[$x]) db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);
 	 	 	 	elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte 	where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);}
 	 	 	 	else {
					$alerttekst=findtekst(352,$sprog_id);
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 352-->\n";
				}
 	 	 	}
#			if (!$pbs) {
# 	 	 	 	if ($r=db_fetch_array(db_select("select id from pbs_kunder where konto_id = '$id'",__FILE__ . " linje " . __LINE__))) {
#					
#					db_modify("delete from pbs_kunder where konto_id = $id",__FILE__ . " linje " . __LINE__); #20121023
#				}
#			}
		}
	} else {
		db_modify("delete from adresser where id = $id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from shop_adresser where saldi_id = $id",__FILE__ . " linje " . __LINE__);
 		print "<meta http-equiv=\"refresh\" content=\"0;URL=debitor.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">\n";
 	}
}

?>
