<?php
// ------------- debitor/debitorkort.php ----- (modul nr 6)------ lap 3.0.3 ----2010-05-31-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------

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

 $id = $_GET['id'];
 if($_GET['returside']){
 	$returside= $_GET['returside'];
 	$ordre_id = $_GET['ordre_id'];
 	$fokus = $_GET['fokus'];
} else {
	if ($popup) $returside="../includes/luk.php";
	else $returside="debitor.php";
}
if ($delete_category=if_isset($_GET['delete_category'])) {
	db_modify("delete from grupper where id = '$delete_category'",__FILE__ . " linje " . __LINE__);
}
$rename_category=if_isset($_GET['rename_category']);

if ($_POST){
 	$submit=addslashes(trim($_POST['submit']));
 	$id=$_POST['id'];
 	if ($submit!="Slet") {
		$notes=$_POST['notes'];
		if ( !$id && $notes ) {
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
		} else {
			$firmanavn=addslashes(trim($_POST['firmanavn']));
			$addr1=addslashes(trim($_POST['addr1']));
			$addr2=addslashes(trim($_POST['addr2']));
			$postnr=addslashes(trim($_POST['postnr']));
			$bynavn=addslashes(trim($_POST['bynavn']));
			$land=addslashes(trim($_POST['land']));
			$kontakt=addslashes(trim($_POST['kontakt']));
			$tlf=addslashes(trim($_POST['tlf']));
			$email=addslashes(trim($_POST['email']));
			$mailfakt=addslashes(trim($_POST['mailfakt']));
			$cvrnr=addslashes(trim($_POST['cvrnr']));
			$kontonr=addslashes(trim($_POST['kontonr']));
			$felt_1 = addslashes(trim($_POST['felt_1']));
			$notes=addslashes(trim($_POST['notes']));
		}
		$ny_kontonr=addslashes(trim($_POST['ny_kontonr']));
		$gl_kontotype=addslashes(trim($_POST['gl_kontotype']));
		$kontotype=addslashes(trim($_POST['kontotype']));
		$fornavn=addslashes(trim($_POST['fornavn']));
		$efternavn=addslashes(trim($_POST['efternavn']));
		$fax=addslashes(trim($_POST['fax']));
		$web=addslashes(trim($_POST['web']));
		$betalingsbet=addslashes(trim($_POST['betalingsbet']));
		$ean=addslashes(trim($_POST['ean']));
		$institution=addslashes(trim($_POST['institution']));
		$betalingsdage=$_POST['betalingsdage']*1;
		$kreditmax=usdecimal($_POST['kreditmax']);
		$felt_2 = addslashes(trim($_POST['felt_2']));
		$felt_3 = addslashes(trim($_POST['felt_3']));
		$felt_4 = addslashes(trim($_POST['felt_4']));
		$felt_5 = addslashes(trim($_POST['felt_5']));
		$lev_firmanavn=addslashes(trim($_POST['lev_firmanavn']));
		$lev_fornavn=addslashes(trim($_POST['lev_fornavn']));
		$lev_efternavn=addslashes(trim($_POST['lev_efternavn']));
		$lev_addr1=addslashes(trim($_POST['lev_addr1']));
		$lev_addr2=addslashes(trim($_POST['lev_addr2']));
		$lev_postnr=addslashes(trim($_POST['lev_postnr']));
		$lev_bynavn=addslashes(trim($_POST['lev_bynavn']));
		$lev_land=addslashes(trim($_POST['lev_land']));
		$lev_kontakt=addslashes(trim($_POST['lev_kontakt']));
		$lev_tlf=addslashes(trim($_POST['lev_tlf']));
		$lev_email=addslashes(trim($_POST['lev_email']));
		$vis_lev_addr=addslashes(trim($_POST['vis_lev_addr']));
		$lukket=addslashes(trim($_POST['lukket']));

		list ($gruppe) = split (':', $_POST['gruppe']);
		
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
		$cat_antal=$_POST['cat_antal'];
		$ny_kategori=$_POST['ny_kategori'];
		$rename_category=if_isset($_POST['rename_category']);
		
		if ($gl_kontotype=='privat') {
			$firmanavn=trim($fornavn." ".$efternavn);
			$lev_firmanavn=trim($lev_fornavn." ".$lev_efternavn);
		}
	
		######### Kategorier

		for ($x=1;$x<=$cat_antal;$x++) {
			if ($cat_valg[$x]) {
				($kategori)?$kategori.=chr(9).$cat_id[$x]:$kategori=$cat_id[$x];
			}
		}
		$tmp="Skriv evt. ny kategori her";
		if ($ny_kategori && $ny_kategori!=$tmp) {
			if (!$rename_category && $r=db_fetch_array($q=db_select("select id from grupper where art='DGCAT' and lower(box1) = '".addslashes(strtolower($ny_kategori))."'",__FILE__ . " linje " . __LINE__))) {
				print "<BODY onLoad=\"javascript:alert('Kategorien $ny_kategori eksisterer allerede')\">\n";
			} elseif ($rename_category) { 
				db_modify("update grupper set box1='".addslashes($ny_kategori)."' where id='$rename_category'",__FILE__ . " linje " . __LINE__); 
				$rename_category=0;
			} else { 
				db_modify("insert into grupper(beskrivelse,art,box1) values ('Debitorkategorier','DGCAT','".addslashes($ny_kategori)."')",__FILE__ . " linje " . __LINE__); 
				$r=db_fetch_array($q=db_select("select id from grupper where art='DGCAT' and lower(box1) = '".addslashes(strtolower($ny_kategori))."'",__FILE__ . " linje " . __LINE__));
				($kategori)?$kategori.=chr(9).$r['id']:$kategori=$r['id'];
			}	
		}
		######### Tjekker om kontonr er integer
 
 		$temp=str_replace(" ","",$ny_kontonr);
 		$tmp2='';
 		for ($x=0; $x<strlen($temp); $x++){
 		 	$y=substr($temp,$x,1);
 		 	if ((ord($y)<48)||(ord($y)>57)) {$y=0;}
 		 	$tmp2=$tmp2.$y;
 		}
 		$tmp2=$tmp2*1;
 		if ($tmp2!=$ny_kontonr) {print "<BODY onLoad=\"javascript:alert('Kontonummer m&aring; kun best&aring; af heltal uden mellemrum')\">";}
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
		if (!$firmanavn) print "<BODY onLoad=\"javascript:alert('Navn skal angives')\">\n";
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		if ($kontoansvarlig) {
			if ($r = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
				if ($r = db_fetch_array(db_select("select id from ansatte where initialer = '$kontoansvarlig' and konto_id='$r[id]'",__FILE__ . " linje " . __LINE__))) $kontoansvarlig=$r['id'];
			}
		} elseif ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr = '2' and box2 = 'on'",__FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Kundeansvarlig ikke valgt!')\">\n";
		}  
		if (!$kontoansvarlig) $kontoansvarlig='0';
		if (!$gruppe) {
			print "<BODY onLoad=\"javascript:alert('Debitorgruppe ikke valgt!')\">\n";
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
 			print "<BODY onLoad=\"javascript:alert('Kontonummer $ny_kontonr tildelt automatisk')\">\n";
		}
 	
############################
 		if(!$betalingsdage){$betalingsdage=0;}
 	 	if(!$kreditmax){$kreditmax=0;}
 	 	if ($id==0) {
 	 	 	$q = db_select("select id from adresser where kontonr = '$ny_kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__);
 	 	 	$r = db_fetch_array($q);
 	 	 	if ($r[id]) {
				print "<BODY onLoad=\"javascript:alert('Der findes allerede en debitor med Kontonr: $ny_kontonr')\">\n";
 	 	 	 	$id=0;
 	 	 	} elseif($ny_kontonr) {
				$oprettet=date("Y-m-d");
 	 	 	 	db_modify("insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,tlf,fax,email,mailfakt,web,betalingsdage,kreditmax,betalingsbet,cvrnr,ean,institution,notes,art,gruppe,kontoansvarlig,oprettet,bank_reg,bank_konto,pbs_nr,pbs,kontotype,fornavn,efternavn,lev_firmanavn,lev_fornavn,lev_efternavn,lev_addr1,lev_addr2,lev_postnr,lev_bynavn,lev_land,lev_kontakt,lev_tlf,lev_email,felt_1,felt_2,felt_3,felt_4,felt_5,vis_lev_addr,lukket,kategori,rabatgruppe) values ('$ny_kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', '$tlf', '$fax', '$email','$mailfakt', '$web', '$betalingsdage', '$kreditmax', '$betalingsbet', '$cvrnr', '$ean', '$institution', '$notes', 'D', '$gruppe', '$kontoansvarlig', '$oprettet','$bank_reg','$bank_konto','$pbs_nr','$pbs','$kontotype','$fornavn','$efternavn','$lev_firmanavn','$lev_fornavn','$lev_efternavn','$lev_addr1','$lev_addr2','$lev_postnr','$lev_bynavn','$lev_land','$lev_kontakt','$lev_tlf','$lev_email','$felt_1','$felt_2','$felt_3','$felt_4','$felt_5','$vis_lev_addr','$lukket','$kategori','$rabatgruppe')",__FILE__ . " linje " . __LINE__);
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
 	 	 	 	 	 print "<BODY onLoad=\"javascript:alert('Kontonummer findes allerede, ikke &aelig;ndret')\">\n";
 	 	 	 	} else {$kontonr=$ny_kontonr;}
 	 	 	}
			db_modify("update adresser set kontonr = '$kontonr', firmanavn = '$firmanavn', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', land = '$land', kontakt = '$kontakt', tlf = '$tlf', fax = '$fax', email = '$email', mailfakt = '$mailfakt', web = '$web', betalingsdage= '$betalingsdage', kreditmax = '$kreditmax', betalingsbet = '$betalingsbet', cvrnr = '$cvrnr', ean = '$ean', institution = '$institution', notes = '$notes', gruppe = '$gruppe', kontoansvarlig = '$kontoansvarlig',bank_reg='$bank_reg',bank_konto='$bank_konto', pbs_nr = '$pbs_nr', pbs = '$pbs',kontotype='$kontotype',fornavn='$fornavn',efternavn='$efternavn',lev_firmanavn='$lev_firmanavn',lev_fornavn='$lev_fornavn',lev_efternavn='$lev_efternavn',lev_addr1='$lev_addr1',lev_addr2='$lev_addr2',lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_land='$lev_land',lev_kontakt='$lev_kontakt',lev_tlf='$lev_tlf',lev_email='$lev_email',felt_1='$felt_1',felt_2='$felt_2',felt_3='$felt_3',felt_4='$felt_4',felt_5='$felt_5',vis_lev_addr='$vis_lev_addr',lukket='$lukket',kategori='$kategori',rabatgruppe='$rabatgruppe' where id = '$id'",__FILE__ . " linje " . __LINE__);
 	 	 	for ($x=1; $x<=$ans_ant; $x++) {
 	 	 	 	$y=trim($posnr[$x]);
 	 	 	 	if ($y && is_numeric($y) && $ans_id[$x]) db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);
 	 	 	 	elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte 	where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);}
 	 	 	 	else {print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en kontaktperson')\">";}
 	 	 	}
 	 	}
	} else {
		db_modify("delete from adresser where id = $id",__FILE__ . " linje " . __LINE__);
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
#	$kontakt=htmlentities(trim($r['kontakt']));
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
#	$rabatgruppe=trim($r['rabatgruppe']);
	$bank_konto=trim($r['bank_konto']);
	$bank_reg=trim($r['bank_reg']);
	if ($r['pbs']=='on') $pbs="checked";
	$pbs_nr=trim($r['pbs_nr']);
	$pbs_date=trim($r['pbs_date']);
	$kontoansvarlig=trim($r['kontoansvarlig']);
	if (!$kontoansvarlig) $kontoansvarlig='0';
	($r['vis_lev_addr']) ? $vis_lev_addr='checked' : $vis_lev_addr=NULL;
	$felt_1 = htmlentities(trim($r['felt_1']),ENT_COMPAT,$charset);
	$felt_2 = htmlentities(trim($r['felt_2']),ENT_COMPAT,$charset);
	$felt_3 = htmlentities(trim($r['felt_3']),ENT_COMPAT,$charset);
	$felt_4 = htmlentities(trim($r['felt_4']),ENT_COMPAT,$charset);
	$felt_5 = htmlentities(trim($r['felt_5']),ENT_COMPAT,$charset);
	($r['lukket']) ? $lukket='checked' : $lukket='';
	$kategori=split(chr(9),$r['kategori']);
	$kategori_antal=count($kategori);
} else {
 	$id=0;
 	$betalingsdage=8;
 	$betalingsbet="Netto";
	$kontoansvarlig='0';
	$kontonr=if_isset($_GET['kontonr']);
	$firmanavn=if_isset($_GET['firmanavn']);
	$addr1=if_isset($_GET['addr1']);
	$addr2=if_isset($_GET['addr2']);
	$postnr=if_isset($_GET['postnr']);
	$bynavn=if_isset($_GET['bynavn']);
	$land=if_isset($_GET['land']);
	$kontakt=if_isset($_GET['kontakt']);
	$tlf=if_isset($_GET['tlf']);
	$kategori_antal=0;
	$vis_lev_addr='checked';
	print "<BODY onLoad=\"javascript:docChange = true;\">\n";
	
}
$kreditmax=dkdecimal($kreditmax);


if (!$kontotype) $kontotype = "erhverv";
#elseif (!$kontotype) $kontotype = "privat";

if ($kontotype=="privat") {
	if (!$fornavn && !$efternavn && $firmanavn) {
	list($fornavn,$efternavn)=split(",",split_navn($firmanavn));
	list($lev_fornavn,$lev_efternavn)=split(",",split_navn($lev_firmanavn));
/*		
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
*/ 
	}
} 
######################## OUTPUT ######################

$tekst=findtekst(154,$sprog_id);
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n"; # TABEL 1 ->
print "<tr><td align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>"; # TABEL 1.1 ->
if ($popup) print "<td onClick=\"JavaScript:opener.location.reload();\" width=\"10%\" $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></td>\n";
else print "<td $top_bund><a href=\"javascript:confirmClose('$returside?returside=$returside&id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=L>Luk</a></td>\n";
print "<td width=\"80%\"$top_bund>Debitorkort</td>\n";
print "<td width=\"10%\"$top_bund><a href=\"javascript:confirmClose('debitorkort.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id','$tekst')\" accesskey=N>Ny</a><br></td>\n";
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
	print "<option value=privat>Privat</option>\n";
	print "<option value=erhverv>Erhverv</option>\n";
} else {	
	print "<option value=erhverv>Erhverv</option>\n";
	print "<option value=privat>Privat</option>\n";
}
print "</select></td>\n";
print "<td align=right>Vis leveringsadresse<input class=\"inputbox\" type=\"checkbox\" name=\"vis_lev_addr\" $vis_lev_addr></td></tr>\n";
print "<tr><td valign=top height=250px><table border=0 width=100%><tbody>"; # TABEL 1.2.1 ->
$bg=$bgcolor5;
print "<tr bgcolor=$bg><td>Kundenr.</td><td><input class=\"inputbox\" type=text size=25 name=ny_kontonr value=\"$kontonr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
if ($kontotype=='privat') {
	print "<input type=\"hidden\" name=\"firmanavn\" value=\"$firmanavn\">\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Fornavn</td><td><input class=\"inputbox\" type=text size=25 name=fornavn value=\"$fornavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Efternavn</td><td><input class=\"inputbox\" type=text size=25 name=efternavn value=\"$efternavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Firmanavn</td><td><input class=\"inputbox\" type=text size=25 name=firmanavn value=\"$firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Adresse</td><td><input class=\"inputbox\" type=text size=25 name=addr1 value=\"$addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Adresse2</td><td><input class=\"inputbox\" type=text size=25 name=addr2 value=\"$addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Postnr./By</td><td><input class=\"inputbox\" type=text size=3 name=postnr value=\"$postnr\" onchange=\"javascript:docChange = true;\">\n";
print "<input class=\"inputbox\" type=text size=19 name=bynavn value=\"$bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Land</td><td><input class=\"inputbox\" type=text size=25 name=land value=\"$land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>E-mail / brug mail</td><td><input class=\"inputbox\" type=text size=22 name=email value=\"$email\" onchange=\"javascript:docChange = true;\">\n";
if ($email && $mailfakt) $mailfakt="checked";
print "<span title=\"Afm&aelig;rk her hvis modtageren skal modtage tilbud, ordrer, fakturaer & rykker pr. mail\"><input class=\"inputbox\" type=checkbox name=mailfakt $mailfakt></span></td></tr>\n";
if ($kontotype=='erhverv') {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Hjemmeside</td><td><input class=\"inputbox\" type=text size=25 name=web value=\"$web\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Betalingsbetingelse</td>\n";
print "<td><select class=\"inputbox\" NAME=betalingsbet onchange=\"javascript:docChange = true;\" >\n";
print "<option>$betalingsbet</option>\n";
if ($betalingsbet!='Forud') 	{print "<option>Forud</option>"; }
if ($betalingsbet!='Kontant') 	{print "<option>Kontant</option>"; }
if ($betalingsbet!='Efterkrav') 	{print "<option>Efterkrav</option>"; }
if ($betalingsbet!='Netto'){print "<option>Netto</option>"; }
if ($betalingsbet!='Lb. md.'){print "<option>Lb. md.</option>";}
if (($betalingsbet=='Kontant')||($betalingsbet=='Efterkrav')||($betalingsbet=='Forud')) {$betalingsdage='';}

elseif (!$betalingsdage) {$betalingsdage='Nul';}
if ($betalingsdage){
 	if ($betalingsdage=='Nul') {$betalingsdage=0;}
 	print "</SELECT>&nbsp;+<input class=\"inputbox\" type=text size=2 style=text-align:right name=betalingsdage value=\"$betalingsdage\" onchange=\"javascript:docChange = true;\"></td>\n";
} else print "</SELECT></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Debitorgruppe</td>\n";
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
	print "<td>Rabatgruppe</td>\n";
	print "<td><select class=\"inputbox\" NAME=rabatgruppe onchange=\"javascript:docChange = true;\">\n";
	for ($x=1;$x<=$drg;$x++) {
		if ($rabatgruppe==$drg_nr[$x]) print "<option value=\"$rabatgruppe\">$drg_navn[$x]</option>\n";
	}
	print "<option value=\"0\"></option>\n";
	for ($x=1;$x<=$drg;$x++) {
		if ($rabatgruppe!=$drg_nr[$x]) print "<option value=\"$drg_nr[$x]\">$drg_navn[$x]</option>\n";
	}
	print "</SELECT></td></tr>\n";
} else print "<td colspan=\"2\"></td></tr>";
#print "<td><br></td>\n";
print "</tbody></table></td>"; # <- TABEL 1.2.1
print "<td valign=top><table border=0 width=100%><tbody>"; # TABEL 1.2.2 ->
$bg=$bgcolor5;
print "<tr bgcolor=$bg><td>CVR-nr.</td><td><input class=\"inputbox\" type=text size=10 name=cvrnr value=\"$cvrnr\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Telefon</td><td><input class=\"inputbox\" type=text size=10 name=tlf value=\"$tlf\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Telefax</td><td><input class=\"inputbox\" type=text size=10 name=fax value=\"$fax\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
if ($kontotype=='erhverv') {
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>EAN-nr.</td><td><input class=\"inputbox\" type=text size=10 name=ean value=\"$ean\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Institutionsnr.</td><td><input class=\"inputbox\" type=text size=10 name=institution value=\"$institution\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Kreditmax</td><td><input class=\"inputbox\" type=text size=10 name=kreditmax value=\"$kreditmax\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Bank reg.</td><td><input class=\"inputbox\" type=text size=10 name=bank_reg value=\"$bank_reg\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Bank konto</td><td><input class=\"inputbox\" type=text size=10 name=bank_konto value=\"$bank_konto\"></td></tr>\n";
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
if ($pbs) {
	print "<tr bgcolor=$bg><td height=25px>PBS/nr</td><td><input class=\"inputbox\" type=checkbox name=pbs $pbs><input class=\"inputbox\" size=\"8\" type=\"text\" name=\"pbs_nr\" value=\"$pbs_nr\"></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td height=25px>PBS</td><td><input class=\"inputbox\" type=checkbox name=pbs $pbs></td></tr>\n";
}
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td> Kundeansvarlig</td>\n";
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
($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
print "<tr bgcolor=$bg><td>Lukket</td><td><input class=\"inputbox\" type=checkbox name=lukket $lukket></td></tr>\n";
print "</tbody></table></td>";# <- TABEL 1.2.2
print "<td valign=top><table border=0 width=100%><tbody>"; # TABEL 1.2.3 ->
$bg=$bgcolor5;
if ($vis_lev_addr) {
	print "<tr bgcolor=$bg><td colspan=2 align=center height=25px><b>Levering</b></td></tr>\n";
	if ($kontotype=='privat') {
		print "<input type=\"hidden\" name=\"lev_firmanavn\" value=\"$lev_firmanavn\">\n";
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
		print "<tr bgcolor=$bg><td>Fornavn</td><td><input class=\"inputbox\" type=text size=25 name=lev_fornavn value=\"$lev_fornavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
		print "<tr bgcolor=$bg><td>Efternavn</td><td><input class=\"inputbox\" type=text size=25 name=lev_efternavn value=\"$lev_efternavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	} else {
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
		print "<tr bgcolor=$bg><td>Firmanavn</td><td><input class=\"inputbox\" type=text size=25 name=lev_firmanavn value=\"$lev_firmanavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	}
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Adresse</td><td><input class=\"inputbox\" type=text size=25 name=lev_addr1 value=\"$lev_addr1\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td> Adresse2</td><td><input class=\"inputbox\" type=text size=25 name=lev_addr2 value=\"$lev_addr2\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Postnr./By</td><td><input class=\"inputbox\" type=text size=3 name=lev_postnr value=\"$lev_postnr\" onchange=\"javascript:docChange = true;\">\n";
	print "<input class=\"inputbox\" type=text size=19 name=lev_bynavn value=\"$lev_bynavn\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td> Land</td><td><input class=\"inputbox\" type=text size=25 name=lev_land value=\"$lev_land\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>E-mail</td><td><input class=\"inputbox\" type=text size=22 name=lev_email value=\"$lev_email\" onchange=\"javascript:docChange = true;\">\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td>Telefon</td><td><input class=\"inputbox\" type=text size=25 name=lev_tlf value=\"$lev_tlf\" onchange=\"javascript:docChange = true;\"></td></tr>\n";
} else {
	print "<tr bgcolor=$bg><td colspan=2 height=25px align=center><b>".findtekst(254,$sprog_id)."</b></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(260,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(255,$sprog_id)."</td><td><input class=\"inputbox\" type=text name=\"felt_1\" size=\"25\" value=\"$felt_1\"></span></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(261,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(256,$sprog_id)."</td><td><input class=\"inputbox\" type=text name=\"felt_2\" size=\"25\" value=\"$felt_2\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(262,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(257,$sprog_id)."</td><td><input type=text class=\"inputbox\" name=\"felt_3\" size=\"25\" value=\"$felt_3\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(263,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(258,$sprog_id)."</td><td><input class=\"inputbox\" type=text name=\"felt_4\" size=\"25\" value=\"$felt_4\"></td></tr>\n";
	($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
	print "<tr bgcolor=$bg><td><span onmouseover=\"return overlib('".findtekst(264,$sprog_id)."', WIDTH=600);\" onmouseout=\"return nd();\">".findtekst(259,$sprog_id)."</td><td><input type=text class=\"inputbox\" name=\"felt_5\" size=\"25\" value=\"$felt_5\"></td></tr>\n";
}	
print "</tbody></table></td></tr>"; # <- TABEL 1.2.3
print "<tr><td colspan=3><table border=\"1\" width=\"100%\"><tbody>"; # TABEL 1.2.4 ->
print "<tr><td valign=\"top\"><table cellpadding=\"0\" cellspacing=\"1\" border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.1 ->

$x=0;
$q=db_select("select id,box1 from grupper where art='DGCAT' order by id",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$x++;
	$cat_id[$x]=$r['id'];
	$cat_beskrivelse[$x]=$r['box1'];
}
$cat_antal=$x;

$bg=$bgcolor5;
print "<tr bgcolor=$bg><td colspan=\"4\" valign=\"top\"> Kategorier</td></tr>\n";
$x=0;
if (!$rename_category) {
	for ($x=1;$x<=$cat_antal;$x++) {
#	if ($cat_id[$x]!=$rename_category) {
		$checked="";
		for ($y=0;$y<$kategori_antal;$y++) {
			if ($cat_id[$x]==$kategori[$y]) $checked="checked";
		}	
		print "<tr><td>$cat_beskrivelse[$x]</td>\n";
		print "<td title=\"S&aelig;t flueben her for at knytte $firmanavn til denne kategori\" align=\"center\"><input type=\"checkbox\" name=\"cat_valg[$x]\" $checked></td>\n";
		print "<td title=\"Klik her for at omd&oslash;be denne katagori\"><a href=\"debitorkort.php?id=$id&rename_category=$cat_id[$x]\" onclick=\"return confirm('Vil du omd&oslash;be denne katagori?')\"><img src=../ikoner/rename.png border=0></a></td>\n";
		print "<td title=\"Klik her for at slette denne katagori\"><a href=\"debitorkort.php?id=$id&delete_category=$cat_id[$x]\" onclick=\"return confirm('Vil du slette denne katagori?')\"><img src=../ikoner/delete.png border=0></a></td>\n";
		print "</tr>\n";
		print "<input type=\"hidden\" name=\"cat_id[$x]\" value=\"$cat_id[$x]\">\n";
	}
}
if ($rename_category){
	for ($x=1;$x<=$cat_antal;$x++) {
		if ($rename_category==$cat_id[$x]) $ny_kategori=$cat_beskrivelse[$x];
	}	
	print "<tr><td colspan=\"4\">Omd&oslash;b $ny_kategori til:</td></tr>\n";
	print "<input type=\"hidden\" name=\"rename_category\" value=\"$rename_category\">\n";
	print "<tr><td colspan=\"4\" title=\"Skriv det nye navn p&aring; kategorien her\"><input type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"$ny_kategori\"></td></tr>\n";
} else print "<tr><td colspan=\"4\" title=\"For at oprette en ny kategori skrives navnet p&aring; kategorien her\"><input type=\"text\" size=\"25\" name=\"ny_kategori\" value=\"Skriv evt. ny kategori her\"></td></tr>\n";
print "<input type=\"hidden\" name=\"cat_antal\" value=\"$cat_antal\">\n";

print "</tbody></table></td>";# <- TABEL 1.2.4.1
print "<td><table border=0><tbody>"; # TABEL 1.2.4.2 ->

$bg=$bgcolor5;
print "<tr bgcolor=$bg><td colspan=5 valign=top>Bem&aelig;rkning<br><textarea name=\"notes\" rows=\"6\" cols=\"85\">$notes</textarea></td></tr>\n";
#print "<tr><td> <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Kontaktperson</a></td><td><br></td>\n";
print "</tbody></table></td></tr>";# <- TABEL 1.2.4.2
print "<tr><td colspan=2><table border=\"0\" width=\"100%\"><tbody>"; # TABEL 1.2.4.3 ->
	
print "<tr><td colspan=6><hr></td></tr>\n";
	if ($kontotype == 'erhverv') {
	print "<tr bgcolor=$bg><td colspan=6><b>Kontaktpersoner</b></td></tr>\n";
	if ($id) {
		($bg==$bgcolor) ? $bg=$bgcolor5 : $bg=$bgcolor;
 		print "<tr bgcolor=$bg><td title=\"Positionsnummer. Prim&aelig;r kontakt har nummer 1\">Pos.</td><td>Kontakt</td><td title=\"Direkte telefonnummer eller lokalnummer\">Direkte/lokal</td><td>Mobil</td><td>E-mail</td><td> <a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$id>Ny</a></td></tr>\n";
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
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:historik=window.open('historikkort.php?id=$id&returside=../includes/luk.php','historik','".$jsvars."');historik.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(131,$sprog_id)."</td>\n";
elseif ($returside!="historikkort.php") print "<td width=\"10%\" $top_bund title=\"$tekst\"><a href=historikkort.php?id=$id&returside=debitorkort.php>".findtekst(131,$sprog_id)."</td>\n";
else print "<td width=\"10%\" $top_bund title=\"$tekst\"><a href=historikkort.php?id=$id>".findtekst(131,$sprog_id)."</td>\n";
$tekst=findtekst(132,$sprog_id);
if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:kontokort=window.open('rapport.php?rapportart=kontokort&kontonr=$kontonr&returside=../includes/luk.php','kontokort','".$jsvars."');kontokort.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(133,$sprog_id)."</td>\n";
else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><a href=rapport.php?rapportart=kontokort&kontonr=$kontonr&returside=../debitor/debitorkort.php?id=$id>".findtekst(133,$sprog_id)."</td>\n";
$tekst=findtekst(129,$sprog_id);
if (substr($rettigheder,5,1)=='1') {
	if ($popup) print "<td width=\"10%\" $top_bund onClick=\"javascript:d_ordrer=window.open('ordreliste.php?konto_id=$id&valg=faktura&returside=../includes/luk.php','d_ordrer','".$jsvars."');d_ordrer.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\" title=\"$tekst\">".findtekst(134,$sprog_id)."</td>\n";
	else print "<td width=\"10%\" $top_bund  title=\"$tekst\"><a href=ordreliste.php?konto_id=$id&valg=faktura&returside=../debitor/debitorkort.php?id=$id>".findtekst(134,$sprog_id)."</td>\n";
} else print "<td width=\"10%\" $top_bund><span style=\"color:#999;\">".findtekst(134,$sprog_id)."</span></td>\n";
$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
$jobkort=$r['box7'];
if ($jobkort) {
	$tekst=findtekst(312,$sprog_id);#"Klik her for at &aring;bne listen med arbejdskort"
print "<td width=\"10%\" $top_bund title=\"$tekst\"><a href=jobliste.php?konto_id=$id&returside=../debitor/debitorkort.php?id=$id>".findtekst(38,$sprog_id)."</td>\n";
} else print "<td width=\"10%\"  $top_bund><span style=\"color:#999;\">".findtekst(38,$sprog_id)."</span></td>\n";
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

?>
</body></html>
