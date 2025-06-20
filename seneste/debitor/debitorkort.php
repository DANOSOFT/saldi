<?php
// ------------- debitor/debitorkort.php ------ lap 3.5.0 ----2015-01-23-----------
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
// Copyright (c) 2003-2015 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2012.10.23, ID slettes fra pbs_kunder hvis pbs ikke afmærket, søg 20121023
// 2012.10.24, Annulleret ændringer fra 2012.10.23 !!
// 2013.10.04, Indsat ENT_COMPAT,$charset); Søg 20131004
// 2014.05.07, Indsat db_escabe_string #20140507
// 2015.01.23 Indhente virksomhedsdata fra CVR via CVRapi - tak Niels Rune https://github.com/nielsrune

@session_start();
$s_id=session_id();

print "<script LANGUAGE=\"JavaScript\" SRC=\"../javascript/overlib.js\"></script>\n";

$modulnr=6;
$title="SALDI - debitorkort";
$css="../css/standard.css";

 include("../includes/connect.php");
 include("../includes/online.php");
 include("../includes/std_func.php");

 print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>\n";

 $id = if_isset($_GET['id']);
if (!$id) $id= if_isset($_GET['konto_id']);

 if($_GET['returside']){
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
} else {
	if ($popup) $returside="../includes/luk.php";
	else $returside="debitor.php";
}
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

if ($_POST){
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
		$betalingsdage=$_POST['betalingsdage']*1;
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
		
		$rabatgruppe=$_POST['rabatgruppe']*1;
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
		$cat_antal=$_POST['cat_antal']*1;
		$ny_kategori=$_POST['ny_kategori'];
		$rename_category=if_isset($_POST['rename_category']);

		$status=db_escape_string(trim($_POST['status']));
		$ny_status=db_escape_string(trim($_POST['ny_status']));
		$status_id=$_POST['status_id'];
		$status_beskrivelse=$_POST['status_beskrivelse'];
		$status_antal=count($status_id);
		$rename_status=if_isset($_POST['rename_status']);

		if ($gl_kontotype=='privat') {
			$firmanavn=trim($fornavn." ".$efternavn);
			$lev_firmanavn=trim($lev_fornavn." ".$lev_efternavn);
		}


#		if (!$pbs) $pbs_nr=NULL;
		($status=='new_status')?$new_status=1:$new_status=0;
		$status*=1;

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
 		$tmp2=$tmp2*1;
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
 	 	 	 	$id = $r[id];
				if ($kontakt) db_modify("insert into ansatte(konto_id, navn) values ('$id', '$kontakt')",__FILE__ . " linje " . __LINE__); 
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
#			if (!$pbs) db_modify("delete from pbs_kunder where konto_id = $id",__FILE__ . " linje " . __LINE__); # 2012103
 	 	}
	} else {
		db_modify("delete from adresser where id = $id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from shop_adresser where saldi_id = $id",__FILE__ . " linje " . __LINE__);
 		print "<meta http-equiv=\"refresh\" content=\"0;URL=debitor.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">\n";
 	}
}

if ($id > 0){
	$q = db_select("select * from adresser where id = '$id'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$kontonr=trim($r['kontonr']);
	$kontotype=trim($r['kontotype']);
	$firmanavn=htmlentities(trim($r['firmanavn']),ENT_COMPAT,$charset);
	$fornavn=htmlentities(trim($r['fornavn']),ENT_COMPAT,$charset);
	$efternavn=htmlentities(trim($r['efternavn']),ENT_COMPAT,$charset);
	$addr1=htmlentities(trim($r['addr1']),ENT_COMPAT,$charset);
	$addr2=htmlentities(trim($r['addr2']),ENT_COMPAT,$charset);
	$postnr=trim($r['postnr']);
	$bynavn=htmlentities(trim($r['bynavn']),ENT_COMPAT,$charset);
	$land=htmlentities(trim($r['land']),ENT_COMPAT,$charset);
	$lev_firmanavn=htmlentities(trim($r['lev_firmanavn']),ENT_COMPAT,$charset);
	$lev_fornavn=htmlentities(trim($r['lev_fornavn']),ENT_COMPAT,$charset);
	$lev_efternavn=htmlentities(trim($r['lev_efternavn']),ENT_COMPAT,$charset);
	$lev_addr1=htmlentities(trim($r['lev_addr1']),ENT_COMPAT,$charset);
	$lev_addr2=htmlentities(trim($r['lev_addr2']),ENT_COMPAT,$charset);
	$lev_postnr=trim($r['lev_postnr']);
	$lev_bynavn=htmlentities(trim($r['lev_bynavn']),ENT_COMPAT,$charset);
	$lev_land=htmlentities(trim($r['lev_land']),ENT_COMPAT,$charset);
	$lev_tlf=trim($r['lev_tlf']);
	$lev_email=trim($r['lev_email']);
	$lev_kontakt=htmlentities(trim($r['lev_kontakt']),ENT_COMPAT,$charset);#20131004
	$tlf=trim($r['tlf']);
	$fax=trim($r['fax']);
	$email=trim($r['email']);
	$mailfakt=trim($r['mailfakt']);
	$web=trim($r['web']);
	$kreditmax=$r['kreditmax'];
	$betalingsdage=$r['betalingsdage'];
	$betalingsbet=trim($r['betalingsbet']);
	$cvrnr=trim($r['cvrnr']);
	$ean=trim($r['ean']);
	$institution=htmlentities(trim($r['institution']),ENT_COMPAT,$charset);
	$notes=htmlentities(trim($r['notes']),ENT_COMPAT,$charset);
	$gruppe=trim($r['gruppe']);
	$rabatgruppe=$r['rabatgruppe']*1;
	$bank_konto=trim($r['bank_konto']);
	$bank_reg=trim($r['bank_reg']);
	if ($r['pbs']=='on') $pbs="checked";
	$pbs_nr=trim($r['pbs_nr']);
	$pbs_date=trim($r['pbs_date']);
	$kontoansvarlig=trim($r['kontoansvarlig']);
	$status=trim($r['status']);
	if (!$kontoansvarlig) $kontoansvarlig='0';
	($r['vis_lev_addr']) ? $vis_lev_addr='checked' : $vis_lev_addr=NULL;
	$felt_1 = htmlentities(trim($r['felt_1']),ENT_COMPAT,$charset);
	$felt_2 = htmlentities(trim($r['felt_2']),ENT_COMPAT,$charset);
	$felt_3 = htmlentities(trim($r['felt_3']),ENT_COMPAT,$charset);
	$felt_4 = htmlentities(trim($r['felt_4']),ENT_COMPAT,$charset);
	$felt_5 = htmlentities(trim($r['felt_5']),ENT_COMPAT,$charset);
	($r['lukket']) ? $lukket='checked' : $lukket='';
	$kategori=explode(chr(9),$r['kategori']);
	$kategori_antal=count($kategori);

} else {
	$r=db_fetch_array(db_select("select count(kontotype) as privat from adresser where kontotype = 'privat'",__FILE__ . " linje " . __LINE__));
	$privat=$r['privat'];
	$r=db_fetch_array(db_select("select count(kontotype) as erhverv from adresser where kontotype = 'erhverv'",__FILE__ . " linje " . __LINE__));
	$erhverv=$r['erhverv'];
	($privat>$erhverv)?$kontotype="privat":$kontotype="erhverv";
	$x=0;	
	$bb=array();
	$q=db_select("select distinct(betalingsbet) as betalingsbet from adresser",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$bb[$x]=$r['betalingsbet'];
		$x++;
	}
	$maxbb='Netto';
	for ($x=0;$x<count($bb);$x++) {
		$r=db_fetch_array(db_select("select count(betalingsbet) as betalingsbet from adresser where  art='D' and betalingsbet = '$bb[$x]'",__FILE__ . " linje " . __LINE__));
		$betbet[$x]=$r['betalingsbet'];
		if ($x && $betbet[$x]>$betbet[$x-1]) $maxbb=$bb[$x];
	}
	$bb=NULL;
	$x=0; $bd=array();
	$q=db_select("select distinct(betalingsdage) as betalingsdage from adresser",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$bd[$x]=$r['betalingsdage'];
		$x++;
	}
	if ($maxbb!='Kontant' && $maxbb!='Forud') {
		$maxbd=8;
		for ($x=0;$x<count($bd);$x++) {
			$r=db_fetch_array(db_select("select count(betalingsdage) as betalingsdage from adresser where art='D' and betalingsdage = '$bd[$x]'",__FILE__ . " linje " . __LINE__));
			$betdag[$x]=$r['betalingsdage'];
			if ($x && $betdag[$x]>$betdag[$x-1]) $maxbd=$bd[$x];
		}
	} else $maxbd=0;
	$bd=NULL; $x=NULL;
 	$id=0;
 	$betalingsbet=$maxbb;
 	$betalingsdage=$maxbd;
 	$kontoansvarlig='0';
	if (isset($_GET['kontonr'])) $kontonr=$_GET['kontonr'];
	if (isset($_GET['firmanavn'])) $firmanavn=$_GET['firmanavn'];
	if (isset($_GET['addr1'])) $addr1=$_GET['addr1'];
	if (isset($_GET['addr2'])) $addr2=$_GET['addr2'];
	if (isset($_GET['postnr'])) $postnr=$_GET['postnr'];
	if (isset($_GET['bynavn'])) $bynavn=$_GET['bynavn'];
	if (isset($_GET['land'])) $land=$_GET['land'];
	if (isset($_GET['kontakt'])) $kontakt=$_GET['kontakt'];
	if (isset($_GET['tlf'])) $tlf=$_GET['tlf'];
	$kategori_antal=0;
	if (!isset($vis_lev_addr)) $vis_lev_addr='checked';
	print "<BODY onLoad=\"javascript:docChange = true;\">\n";
	
}
$kreditmax=dkdecimal($kreditmax);

if ($r=db_fetch_array(db_select("select * from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__))) {
	$cat_id=explode(chr(9),$r['box1']);
	$cat_beskrivelse=explode(chr(9),$r['box2']);
	$cat_antal=count($cat_id);
	$status_id=explode(chr(9),$r['box3']);
	$status_beskrivelse=explode(chr(9),$r['box4']);
	$status_antal=count($status_id);
}	else db_modify("insert into grupper(beskrivelse,art) values ('Div DebitorInfo','DebInfo')",__FILE__ . " linje " . __LINE__); 


if ($kontotype=="privat") {
	if (!$fornavn && !$efternavn && $firmanavn) {
		list($fornavn,$efternavn)=explode(",",split_navn($firmanavn));
				list($lev_fornavn,$lev_efternavn)=explode(",",split_navn($lev_firmanavn));
		db_modify("update adresser set fornavn='".db_escape_string($fornavn)."',efternavn='".db_escape_string($efternavn)."' where id = '$id'",__FILE__ . " linje " . __LINE__);#20140507
	}
} 
######################## OUTPUT ######################

$tekst=findtekst(154,$sprog_id);
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; # TABEL 1 ->
print "<tr><td align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # TABEL 1.1 ->
if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>".findtekst(30,$sprog_id)."<!--tekst 30--></a></td>\n";
else print "<td $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L><!--tekst 154-->".findtekst(30,$sprog_id)."<!--tekst 30--></a></td>\n";
print "<td width=\"80%\"$top_bund>".findtekst(356,$sprog_id)."<!--tekst 356--></td>\n";
print "<td width=\"10%\"$top_bund><a href=\"javascript:confirmClose('debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=0','$tekst')\" accesskey=N><!--tekst 154-->".findtekst(39,$sprog_id)."<!--tekst 39--></a></td>\n";
print "</tbody></table>"; # <- TABEL 1.1
print "</td></tr>\n";
print "<tr><td align = center valign = center>\n";
print "<table cellpadding=\"0\" cellspacing=\"10\" border=\"1\"><tbody>\n"; # TABEL 1.2 ->

print "<form name=debitorkort action=debitorkort.php method=post>\n";
if($vis_lev_addr) {
	print "<input type=hidden name=\"felt_1\" value='$felt_1'>\n";
	print "<input type=hidden name=\"felt_2\" value='$felt_2'>\n";
	print "<input type=hidden name=\"felt_3\" value='$felt_3'>\n";
	print "<input type=hidden name=\"felt_4\" value='$felt_4'>\n";
	print "<input type=hidden name=\"felt_5\" value='$felt_5'>\n";
} else {
	print "<input type=hidden name=\"lev_firmanavn\" value='$lev_firmanavn'>\n";
	print "<input type=hidden name=\"lev_fornavn\" value='$lev_fornavn'>\n";
	print "<input type=hidden name=\"lev_efternavn\" value='$lev_efternavn'>\n";
	print "<input type=hidden name=\"lev_addr1\" value='$lev_addr1'>\n";
	print "<input type=hidden name=\"lev_addr2\" value='$lev_addr2'>\n";
	print "<input type=hidden name=\"lev_postnr\" value='$lev_postnr'>\n";
	print "<input type=hidden name=\"lev_bynavn\" value='$lev_bynavn'>\n";
	print "<input type=hidden name=\"lev_land\" value='$lev_land'>\n";
	print "<input type=hidden name=\"lev_tlf\" value='$lev_tlf'>\n";
	print "<input type=hidden name=\"lev_email\" value='$lev_email'>\n";
	print "<input type=hidden name=\"lev_kontakt\" value='$lev_kontakt'>\n";
}

print "<input type=hidden name=id value='$id'>\n";
print "<input type=hidden name=kontonr value='$kontonr'>\n";
print "<input type=hidden name=ordre_id value='$ordre_id'>\n";
print "<input type=hidden name=returside value='$returside'>\n";
print "<input type=hidden name=fokus value='$fokus'>\n";
print "<input type=hidden name=kontakt value='$kontakt'>\n";
print "<input type=hidden name=pbs_date value='$pbs_date'>\n";
# print "<input type=hidden name=pbs_nr value='$pbs_nr'>\n";
# print "<input type=hidden name=gl_pbs_nr value='$pbs_nr'>\n";
#print "<input type=hidden name=pbs value='$pbs'>\n";

print "<input type=hidden name=gl_kontotype value='$kontotype'>\n";
print "<tr><td colspan=2 align=center>Kundetype <select class=\"inputbox\" NAME=kontotype onchange=\"javascript:docChange = true;\">\n";
if ($kontotype=='privat') {

	print "<option value=privat>".findtekst(353,$sprog_id)."<!--tekst 353--></option>\n";
	print "<option value=erhverv>".findtekst(354,$sprog_id)."<!--tekst 354--></option>\n";
} else {	
	print "<option value=erhverv>".findtekst(354,$sprog_id)."<!--tekst 354--></option>\n";
	print "<option value=privat>".findtekst(353,$sprog_id)."<!--tekst 353--></option>\n";
}
print "</select></td>\n";
print "<td align=right>".findtekst(355,$sprog_id)."<!--tekst 355--><input class=\"inputbox\" type=\"checkbox\" name=\"vis_lev_addr\" $vis_lev_addr></td></tr>\n";
print "<tr><td valign=top height=250px><table border=0 width=100%><tbody>"; # TABEL 1.2.1 ->
$bg=$bgcolor5;
print "<tr bgcolor=$bg><td>".findtekst(357,$sprog_id)."<!--tekst 357--></td><td><input class=\"inputbox\" type=text size=25 name=ny_kontonr value=\"$kontonr\" onchange=\"javascript:docChange = true;\" title=\"Tast CVR-nr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
if ($kontotype=='privat') {
	print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(358,$sprog_id)."<!--tekst 358--></td><td><input class=\"inputbox\" type=text size=25 name=fornavn value=\"$fornavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(359,$sprog_id)."<!--tekst 359--></td><td><input class=\"inputbox\" type=text size=25 name=efternavn value=\"$efternavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(360,$sprog_id)."<!--tekst 360--></td><td><input class=\"inputbox\" type=text size=25 name=firmanavn value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(361,$sprog_id)."<!--tekst 361--></td><td><input class=\"inputbox\" type=text size=25 name=addr1 value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(362,$sprog_id)."<!--tekst 362--></td><td><input class=\"inputbox\" type=text size=25 name=addr2 value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(363,$sprog_id)."<!--tekst 363--></td><td><input class=\"inputbox\" type=text size=3 name=postnr value=\"$postnr\" onchange=\"javascript:docChange = true;\">\n";
print "<input class=\"inputbox\" type=text size=19 name=bynavn value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(364,$sprog_id)."<!--tekst 364--></td><td><input class=\"inputbox\" type=text size=25 name=land value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(365,$sprog_id)."<!--tekst 365--></td><td><input class=\"inputbox\" type=text size=22 name=email value=\"$email\" onchange=\"javascript:docChange = true;\">\n";
if ($email && $mailfakt) $mailfakt="checked";
print "<span title=\"".findtekst(366,$sprog_id)."\"><!--tekst 366--><input class=\"inputbox\" type=checkbox name=mailfakt $mailfakt></span></td></tr>\n";
if ($kontotype=='erhverv') {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(367,$sprog_id)."<!--tekst 367--></td><td><input class=\"inputbox\" type=text size=25 name=web value=\"$web\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(368,$sprog_id)."<!--tekst 368--></td>\n";
print "<td><select class=\"inputbox\" NAME=betalingsbet onchange=\"javascript:docChange = true;\" >\n";
print "<option>$betalingsbet</option>\n";
if ($betalingsbet!='Forud') 	{print "<option value=\"Forud\">".findtekst(369,$sprog_id)."<!--tekst 369--></option>"; }
if ($betalingsbet!='Kontant') 	{print "<option value=\"Kontant\">".findtekst(370,$sprog_id)."<!--tekst 370--></option>"; }
if ($betalingsbet!='Efterkrav') 	{print "<option value=\"Efterkrav\">".findtekst(371,$sprog_id)."<!--tekst 371--></option>"; }
if ($betalingsbet!='Netto'){print "<option value=\"Netto\">".findtekst(372,$sprog_id)."<!--tekst 372--></option>"; }
if ($betalingsbet!='Lb. md.'){print "<option value=\"Lb. md.\">".findtekst(373,$sprog_id)."<!--tekst 373--></option>";}
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}

elseif (!$betalingsdage) {$betalingsdage='Nul';}
if ($betalingsdage){
 	if ($betalingsdage=='Nul') {$betalingsdage=0;}
 	print "</SELECT>&nbsp;+<input class=\"inputbox\" type=text size=2 style=text-align:right name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>\n";
} else print "</SELECT></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(374,$sprog_id)."<!--tekst 374--></td>\n";
if (!$gruppe) {
	if (db_fetch_array(db_select("select id from grupper where art='DIV' and kodenr='2' and box1='on'",__FILE__ . " linje " . __LINE__))) $gruppe='0';
	else $gruppe=1;
}	
print "<td><select class=\"inputbox\" NAME=gruppe onchange=\"javascript:docChange = true;\">\n";
if ($gruppe) {	
	$r = db_fetch_array(db_select("select beskrivelse from grupper where art='DG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
	print "<option>$gruppe:$r[beskrivelse]</option>\n";
}
$q = db_select("select * from grupper where art='DG' and kodenr!='$gruppe' order by kodenr",__FILE__ . " linje " . __LINE__);

while ($r = db_fetch_array($q)){
 print "<option>$r[kodenr]:$r[beskrivelse]</option>\n";
}
print "</SELECT></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg>";
$x=0;
$q = db_select("select * from grupper where art='DRG' order by box1",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
	$x++;
	$drg_nr[$x]=$r['kodenr'];
	$drg_navn[$x]=$r['box1'];
}
if ($drg=$x) {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<td>".findtekst(375,$sprog_id)."<!--tekst 375--></td>\n";
	print "<td><select class=\"inputbox\" NAME=rabatgruppe onchange=\"javascript:docChange = true;\">\n";
	for ($x=1;$x<=$drg;$x++) {
		if ($rabatgruppe==$drg_nr[$x]) print "<option value=\"$rabatgruppe\">$drg_navn[$x]</option>\n";
	}
	print "<option value=\"0\"></option>\n";
	for ($x=1;$x<=$drg;$x++) {
		if ($rabatgruppe!=$drg_nr[$x]) print "<option value=\"$drg_nr[$x]\">$drg_navn[$x]</option>\n";
	}
	print "</SELECT></td></tr>\n";
} else print "<td colspan=\"2\"><br></td></tr>";
#print "<td><br></td>\n";
print "</tbody></table></td>"; # <- TABEL 1.2.1
print "<td valign=top><table border=0 width=100%><tbody>"; # TABEL 1.2.2 ->
$bg=$bgcolor5;
print "<tr bgcolor=$bg><td>".findtekst(376,$sprog_id)."<!--tekst 376--></td><td><input class=\"inputbox\" type=text size=10 name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\" title=\"Tast CVR-nr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(377,$sprog_id)."<!--tekst 377--></td><td><input class=\"inputbox\" type=text size=10 name=tlf value=\"$tlf\" onchange=\"javascript:docChange = true;\" title=\"Tast telefonnr. omsluttet af *, +, eller / for at importere data fra Erhvervsstyrelsen (Data leveres af CVR API)\" style=\"background-image: url('../img/search-white.png'); background-repeat: no-repeat; background-position: right;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(378,$sprog_id)."<!--tekst 378--></td><td><input class=\"inputbox\" type=text size=10 name=fax value=\"$fax\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
if ($kontotype=='erhverv') {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(379,$sprog_id)."<!--tekst 379--></td><td><input class=\"inputbox\" type=text size=10 name=ean value=\"$ean\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(380,$sprog_id)."<!--tekst 380--></td><td><input class=\"inputbox\" type=text size=10 name=institution value=\"$institution\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(381,$sprog_id)."<!--tekst 381--></td><td><input class=\"inputbox\" type=text size=10 name=kreditmax value=\"$kreditmax\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(382,$sprog_id)."<!--tekst 382--></td><td><input class=\"inputbox\" type=text size=10 name=bank_reg value=\"$bank_reg\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(383,$sprog_id)."<!--tekst 383--></td><td><input class=\"inputbox\" type=text size=10 name=bank_konto value=\"$bank_konto\"></td></tr>\n";
##################### PBS ##################### 
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
if ($pbs) {
	print "<tr bgcolor=$bg><td height=25px>".findtekst(384,$sprog_id)."<!--tekst 384--></td><td><input class=\"inputbox\" type=checkbox name=pbs $pbs><input class=\"inputbox\" size=\"8\" type=\"text\" name=\"pbs_nr\" value=\"$pbs_nr\"></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td height=25px>".findtekst(385,$sprog_id)."<!--tekst 385--></td><td><input class=\"inputbox\" type=checkbox name=pbs $pbs></td></tr>\n";
}
##################### KONTOANSVARLIG ##################### 
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(386,$sprog_id)."<!--tekst 386--></td>\n";
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__));
print "<td><select class=\"inputbox\" NAME=kontoansvarlig value=\"$kontoansvarlig\"  onchange=\"javascript:docChange = true;\">\n";
if ($r['initialer']) {
	$r = db_fetch_array(db_select("select initialer from ansatte where id='$kontoansvarlig'",__FILE__ . " linje " . __LINE__));
	print "<option>$r[initialer]</option>\n";
}
print "<option></option>\n";
if ($r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__))) $q = db_select("select id, initialer from ansatte where konto_id='$r[id]'",__FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)){
 	 print "<option>$r[initialer]</option>\n";
}
print "</SELECT></td></tr>\n";
##################### STATUS ##################### 
for ($x=0;$x<$status_antal;$x++) {
	print "<input type=\"hidden\" name=\"status_id[$x]\" value=\"$status_id[$x]\">";
	print "<input type=\"hidden\" name=\"status_beskrivelse[$x]\" value=\"$status_beskrivelse[$x]\">";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
if ($new_status) {
 print "<tr bgcolor=$bg title=\"".findtekst(497,$sprog_id)."\"><!--tekst 497--><td height=\"25px\">".findtekst(494,$sprog_id)."<!--tekst 494--></td><td><input class=\"inputbox\" type=text size=10 name=ny_status></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td title='".findtekst(496,$sprog_id)."'  height=\"25px\"><!--tekst 496-->".findtekst(494,$sprog_id)."<!--tekst 494--></td>\n";
	print "<td><select class=\"inputbox\" NAME=status onchange=\"javascript:docChange = true;\">\n";
	if (!$status) print "<option></option>\n";
	for ($x=0;$x<$status_antal;$x++) {
		if ($status==$status_id[$x]) print "<option value=\"$status_id[$x]\">$status_beskrivelse[$x]</option>\n";
	}
	for ($x=0;$x<$status_antal;$x++) {
		if ($status!=$status_id[$x]) print "<option value=\"$status_id[$x]\">$status_beskrivelse[$x]</option>\n";
	}
	if ($status) print "<option></option>\n";
	print "<option value=\"new_status\">".findtekst(495,$sprog_id)."<!--tekst 495--></option>\n";
	print "</SELECT></td></tr>\n";
}
##################### LUKKET ##################### 
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>".findtekst(387,$sprog_id)."<!--tekst 387--></td><td><input class=\"inputbox\" type=checkbox name=lukket $lukket></td></tr>\n";
print "</tbody></table></td>";# <- TABEL 1.2.2
print "<td valign=top><table border=0 width=100%><tbody>"; # TABEL 1.2.3 ->
$bg=$bgcolor5;
if ($vis_lev_addr) {
	print "<tr bgcolor=$bg><td colspan=2 align=center height=25px><b>Levering</b></td></tr>\n";
	if ($kontotype=='privat') {
		print "<input type=\"hidden\" name=\"lev_firmanavn\" value=\"$lev_firmanavn\">\n";
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
		print "<tr bgcolor=$bg><td>".findtekst(358,$sprog_id)."<!--tekst 358--></td><td><input class=\"inputbox\" type=text size=25 name=lev_fornavn value=\"$lev_fornavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
		print "<tr bgcolor=$bg><td>".findtekst(359,$sprog_id)."<!--tekst 359--></td><td><input class=\"inputbox\" type=text size=25 name=lev_efternavn value=\"$lev_efternavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	} else {
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
		print "<tr bgcolor=$bg><td>".findtekst(360,$sprog_id)."<!--tekst 360--></td><td><input class=\"inputbox\" type=text size=25 name=lev_firmanavn value=\"$lev_firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	}
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(361,$sprog_id)."<!--tekst 361--></td><td><input class=\"inputbox\" type=text size=25 name=lev_addr1 value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(362,$sprog_id)."<!--tekst 362--></td><td><input class=\"inputbox\" type=text size=25 name=lev_addr2 value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(363,$sprog_id)."<!--tekst 363--></td><td><input class=\"inputbox\" type=text size=3 name=lev_postnr value=\"$lev_postnr\" onchange=\"javascript:docChange = true;\">\n";
	print "<input class=\"inputbox\" type=text size=19 name=lev_bynavn value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(364,$sprog_id)."<!--tekst 364--></td><td><input class=\"inputbox\" type=text size=25 name=lev_land value=\"$lev_land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td  height=\"25px\">".findtekst(502,$sprog_id)."<!--tekst 502--></td><td height=\"25px\"><input class=\"inputbox\" type=text size=\"25px\" name=lev_kontakt value=\"$lev_kontakt\" onchange=\"javascript:docChange = true;\">\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>".findtekst(377,$sprog_id)."<!--tekst 377--></td><td><input class=\"inputbox\" type=text size=25 name=lev_tlf value=\"$lev_tlf\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td colspan=2 height=25px align=center><b>".findtekst(254,$sprog_id)."<!--tekst 254--></b></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(260,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 260-->".findtekst(255,$sprog_id)."<!--tekst 255--></td><td><input class=\"inputbox\" type=text name=\"felt_1\" size=\"25\" value=\"$felt_1\"></span></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(261,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 261-->".findtekst(256,$sprog_id)."<!--tekst 256--></td><td><input class=\"inputbox\" type=text name=\"felt_2\" size=\"25\" value=\"$felt_2\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(262,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 262-->".findtekst(257,$sprog_id)."<!--tekst 257--></td><td><input type=text class=\"inputbox\" name=\"felt_3\" size=\"25\" value=\"$felt_3\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(263,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 263-->".findtekst(258,$sprog_id)."<!--tekst 258--></td><td><input class=\"inputbox\" type=text name=\"felt_4\" size=\"25\" value=\"$felt_4\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(264,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\"><!--tekst 264-->".findtekst(259,$sprog_id)."<!--tekst 259--></td><td><input type=text class=\"inputbox\" name=\"felt_5\" size=\"25\" value=\"$felt_5\"></td></tr>\n";
}	
print "</tbody></table></td></tr>"; # <- TABEL 1.2.3
print "<tr><td colspan=3><table border=\"1\" width=\"100%\"><tbody>"; # TABEL 1.2.4 ->
print "<tr><td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.1 ->


$bg=$bgcolor5;
print "<tr bgcolor=$bg><td colspan=\"4\" valign=\"top\">".findtekst(388,$sprog_id)."<!--tekst 388--></td></tr>\n";
$x=0;
if (!$rename_category) {
	for ($x=0;$x<$cat_antal;$x++) {
#	if ($cat_id[$x]!=$rename_category) {
		$checked="";
		for ($y=0;$y<$kategori_antal;$y++) {
			if ($cat_id[$x]==$kategori[$y]) $checked="checked";
		}	
		print "<tr><td>$cat_beskrivelse[$x]</td>\n";
		$tekst=findtekst(395,$sprog_id);
		$tekst=str_replace('$firmanavn',$firmanavn,$tekst);
		print "<td title=\"$tekst\" align=\"center\"><!--tekst 395--><input type=\"checkbox\" name=\"cat_valg[$x]\" $checked></td>\n";
		print "<td title=\"".findtekst(396,$sprog_id)."\"><!--tekst 396--><a href=\"debitorkort.php?id=$id&rename_category=$cat_id[$x]\" onclick=\"return confirm('Vil du omd&oslash;be denne kategori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
		print "<td title=\"".findtekst(397,$sprog_id)."\"><!--tekst 396--><a href=\"debitorkort.php?id=$id&delete_category=$cat_id[$x]\" onclick=\"return confirm('Vil du slette denne kategori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
		print "</tr>\n";
		print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
		print "<input type=\"hidden\" name=\"cat_beskrivelse[$x]\" value=\"$cat_beskrivelse[$x]\">\n";
	}
}
if ($rename_category){
	for ($x=1;$x<=$cat_antal;$x++) {
		if ($rename_category==$cat_id[$x]) $ny_kategori=$cat_beskrivelse[$x];
		print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
		print "<input type=\"hidden\" name=\"cat_beskrivelse[$x]\" value=\"$cat_beskrivelse[$x]\">\n";
	}
	$tekst=findtekst(388,$sprog_id);
	$tekst=str_replace('$ny_kategori',$ny_kategori,$tekst);
	print "<tr><td colspan=\"4\">$tekst<!--tekst 388--></td></tr>\n";
	print "<input type=\"hidden\" name=\"rename_category\" value=\"$rename_category\">\n";
	print "<tr><td colspan=\"4\" title=\"Skriv det nye navn p&aring; kategorien her\"><input type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"$ny_kategori\"></td></tr>\n";
} else print "<tr><td colspan=\"4\" title=\"".findtekst(390,$sprog_id)."\"><!--tekst 390--><input class=\"inputbox\" type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"".findtekst(343,$sprog_id)."\"></td></tr>\n";
print "<input type=\"hidden\" name=\"cat_antal\" value=\"$cat_antal\">\n";

print "</tbody></table></td>";# <- TABEL 1.2.4.1
print "<td><table border=0><tbody>"; # TABEL 1.2.4.2 ->

$bg=$bgcolor5;
print "<tr bgcolor=$bg><td colspan=\"5\" valign=\"top\">".findtekst(391,$sprog_id)."<br><!--tekst 391--><textarea name=\"notes\" rows=\"6\" cols=\"85\">$notes</textarea></td></tr>\n";
#print "<tr><td> <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Kontaktperson</a></td><td><br></td>\n";
print "</tbody></table></td></tr>";# <- TABEL 1.2.4.2
print "<tr><td colspan=2><table border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.3 ->
	
print "<tr><td colspan=6><hr></td></tr>\n";
	if ($kontotype == 'erhverv') {
	print "<tr bgcolor=$bg><td colspan=6><b>".findtekst(392,$sprog_id)."<!--tekst 392--></b></td></tr>\n";
	if ($id) {
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
 		print "<tr bgcolor=$bg><td title=\"".findtekst(393,$sprog_id)."\"><!--tekst 393-->".findtekst(394,$sprog_id)."<!--tekst 394--></td><td>".findtekst(398,$sprog_id)."<!--tekst 398--></td><td title=\"".findtekst(399,$sprog_id)."\"><!--tekst 399-->".findtekst(400,$sprog_id)."<!--tekst 400--></td><td>".findtekst(401,$sprog_id)."<!--tekst 401--></td><td>".findtekst(402,$sprog_id)."<!--tekst 402--></td><td><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>".findtekst(39,$sprog_id)."<!--tekst 39--></a></td></tr>\n";
	 	$x=0;
 		$q = db_select("select * from ansatte where konto_id = '$id' order by posnr",__FILE__ . " linje " . __LINE__);
 		while ($r = db_fetch_array($q)){
 		 	$x++;
			($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
 		 	print "<tr bgcolor=$bg>\n";
 			print "<td width=10><input class=\"inputbox\" type=text size=1 name=posnr[$x] value=\"$x\"></td><td title=\"".htmlentities($r['notes'],ENT_COMPAT,$charset)."\"><a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id&id=$r[id]>".htmlentities($r['navn'],ENT_COMPAT,$charset)."</a></td>\n";
 			print "<td>$r[tlf]</td><td>$r[mobil]</td><td> $r[email]</td></tr>\n";
 			print "<input class=\"inputbox\" type=hidden name=ans_id[$x] value=$r[id]>\n";
 			if ($x==1) {print "<input class=\"inputbox\" type=hidden name=kontakt value='$r[navn]'>";}
		}
		print "<input type=hidden name=ans_ant value=$x>\n";
		print "<tr><td colspan=6><br></td></tr>\n";
	}
}
#print "<tr><td><br></td></tr>\n";
$q = db_select("select id from openpost where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ordrer where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ansatte where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
 	 	 
if ($slet=="NO") {print "<td colspan=6 align = center><input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>";} 	 	 
else {print "<td><br><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td><td><br></td><td><input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\" onclick=\"return confirm('Slet $firmanavn?')\"></td>";}
print "</form>\n";
#print "<tr><td colspan=5><hr></td></tr>\n";
print "</tbody></table></td></tr>";# <- TABEL 1.2.4.3
print "</tbody></table></td></tr>";# <- TABEL 1.2.4

print "</tbody></table></td></tr>"; # <- TABEL 1.2
print "<tr><td align = \"center\" valign = \"bottom\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"1\" cellpadding=\"0\"><tbody>"; # TABEL 1.3 ->
print "<td width=\"25%\" $top_bund>&nbsp;</td>\n";
$tekst=findtekst(130,$sprog_id);
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:historik=window.open('historikkort.php?id=$id&returside=../includes/luk.php','historik','".$jsvars."');historik.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(131,$sprog_id)."<!--tekst 131--></td>\n";
elseif ($returside!="historikkort.php") print "<td width=\"10%\" $top_bund title=\"$tekst\"><!--tekst 130--><a href=historikkort.php?id=$id&returside=debitorkort.php>".findtekst(131,$sprog_id)."<!--tekst 131--></td>\n";
else print "<td width=\"10%\" $top_bund title=\"$tekst\"><!--tekst 130--><a href=historikkort.php?id=$id>".findtekst(131,$sprog_id)."<!--tekst 131--></td>\n";
$tekst=findtekst(132,$sprog_id);
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:kontokort=window.open('rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../includes/luk.php','kontokort','".$jsvars."');kontokort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(133,$sprog_id)."<!--tekst 133--></td>\n";
else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><!--tekst 132--><a href=rapport.php?rapportart=kontokort&konto_fra=$kontonr&konto_til=$kontonr&returside=../debitor/debitorkort.php?id=$id>".findtekst(133,$sprog_id)."<!--tekst 133--></td>\n";
$tekst=findtekst(129,$sprog_id);
if (substr($rettigheder,5,1)=='1') {
	if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:d_ordrer=window.open('ordreliste.php?konto_id=$id&valg=faktura&returside=../includes/luk.php','d_ordrer','".$jsvars."');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(134,$sprog_id)."<!--tekst 134--></td>\n";
	else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><!--tekst 129--><a href=ordreliste.php?konto_id=$id&valg=faktura&returside=../debitor/debitorkort.php?id=$id>".findtekst(134,$sprog_id)."<!--tekst 134--></td>\n";
} else print "<td width=\"10%\" $top_bund><span style=\"color:#999;\">".findtekst(134,$sprog_id)."<!--tekst 134--></span></td>\n";
$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
$jobkort=$r['box7'];
if ($jobkort) {
	$tekst=findtekst(312,$sprog_id);#"Klik her for at &aring;bne listen med arbejdskort"
print "<td width=\"10%\" $top_bund title=\"$tekst\"><!--tekst 312--><a href=jobliste.php?konto_id=$id&returside=debitorkort.php>".findtekst(38,$sprog_id)."<!--tekst 38--></td>\n";
} else print "<td width=\"10%\"  $top_bund><span style=\"color:#999;\">".findtekst(38,$sprog_id)."<!--tekst 38--></span></td>\n";
print "<td width=\"25%\" $top_bund>&nbsp;</td>\n";
print "</td></tbody></table></td></tr>"; # <- TABEL 1.3
print "</tbody></table>"; # <- TABEL 1

function split_navn($firmanavn) {
	$y=0;
	$tmp=array();
	$tmp=explode(" ",$firmanavn);
	$x=count($tmp)-1;
	$efternavn=$tmp[$x];
	while($y<$x-1) {
		$fornavn.=$tmp[$y]." ";
		$y++;
	}
	$fornavn.=$tmp[$y];
	return ($fornavn.",".$efternavn);
}

print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/cvrapiopslag.js\"></script>\n";
?>
</body></html>
