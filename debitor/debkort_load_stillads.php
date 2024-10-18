<?php
$slet=NULL;

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
	$lev_kontakt=htmlentities(trim($r['lev_kontakt']));
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
 	$id=0;
 	$betalingsdage=8;
 	$betalingsbet="Netto";
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
	#if (!isset($vis_lev_addr)) $vis_lev_addr='checked';
	#print "<BODY onLoad=\"javascript:docChange = true;\">\n";
	
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
		db_modify("update adresser set fornavn='$fornavn',efternavn='$efternavn' where id = '$id'",__FILE__ . " linje " . __LINE__);
	}
} 

$q = db_select("select id from openpost where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ordrer where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";
$q = db_select("select id from ansatte where konto_id = '$id'",__FILE__ . " linje " . __LINE__);
if (db_fetch_array($q)) $slet="NO";

print "<input type=hidden name=id value='$id'>\n";
print "<input type=hidden name=\"addr1\" value='$addr1'>\n";
print "<input type=hidden name=\"addr2\" value='$addr2'>\n";
print "<input type=hidden name=\"bank_konto\" value='$bank_konto'>\n";
print "<input type=hidden name=\"bank_reg\" value='$bank_reg'>\n";
print "<input type=hidden name=\"betalingsbet\" value='$betalingsbet'>\n";
print "<input type=hidden name=\"betalingsdage\" value='$betalingsdage'>\n";
print "<input type=hidden name=\"bynavn\" value='$bynavn'>\n";
print "<input type=hidden name=\"cvrnr\" value='$cvrnr'>\n";
print "<input type=hidden name=\"ean\" value='$ean'>\n";
print "<input type=hidden name=\"efternavn\" value='$efternavn'>\n";
print "<input type=hidden name=\"email\" value='$email'>\n";
print "<input type=hidden name=\"fax\" value='$fax'>\n";
print "<input type=hidden name=\"firmanavn\" value='$firmanavn'>\n";
print "<input type=hidden name=\"fornavn\" value='$fornavn'>\n";
print "<input type=hidden name=\"gruppe\" value='$gruppe'>\n";
print "<input type=hidden name=\"institution\" value='$institution'>\n";
print "<input type=hidden name=\"kontoansvarlig\" value='$kontoansvarlig'>\n";
print "<input type=hidden name=\"kontonr\" value='$kontonr'>\n";
print "<input type=hidden name=\"kontotype\" value='$kontotype'>\n";
print "<input type=hidden name=\"kreditmax\" value='$kreditmax'>\n";
print "<input type=hidden name=\"land\" value='$land'>\n";
print "<input type=hidden name=\"lev_addr1\" value='$lev_addr1'>\n";
print "<input type=hidden name=\"lev_addr2\" value='$lev_addr2'>\n";
print "<input type=hidden name=\"lev_bynavn\" value='$lev_bynavn'>\n";
print "<input type=hidden name=\"lev_efternavn\" value='$lev_efternavn'>\n";
print "<input type=hidden name=\"lev_email\" value='$lev_email'>\n";
print "<input type=hidden name=\"lev_firmanavn\" value='$lev_firmanavn'>\n";
print "<input type=hidden name=\"lev_fornavn\" value='$lev_fornavn'>\n";
print "<input type=hidden name=\"lev_kontakt\" value='$lev_kontakt'>\n";
print "<input type=hidden name=\"lev_land\" value='$lev_land'>\n";
print "<input type=hidden name=\"lev_postnr\" value='$lev_postnr'>\n";
print "<input type=hidden name=\"lev_tlf\" value='$lev_tlf'>\n";
print "<input type=hidden name=\"mailfakt\" value='$mailfakt'>\n";
print "<input type=hidden name=\"notes\" value='$notes'>\n";
print "<input type=hidden name=\"pbs_date\" value='$pbs_date'>\n";
print "<input type=hidden name=\"pbs_nr\" value='$pbs_nr'>\n";
print "<input type=hidden name=\"postnr\" value='$postnr'>\n";
print "<input type=hidden name=\"rabatgruppe\" value='$rabatgruppe'>\n";
print "<input type=hidden name=\"status\" value='$status'>\n";
print "<input type=hidden name=\"tlf\" value='$tlf'>\n";
print "<input type=hidden name=\"web\" value='$web'>\n";
print "<input type=hidden name=\"felt_1\" value='$felt_1'>\n";
print "<input type=hidden name=\"felt_2\" value='$felt_2'>\n";
print "<input type=hidden name=\"felt_3\" value='$felt_3'>\n";
print "<input type=hidden name=\"felt_4\" value='$felt_4'>\n";
print "<input type=hidden name=\"felt_5\" value='$felt_5'>\n";
print "<input type=hidden name=ordre_id value='$ordre_id'>\n";
print "<input type=hidden name=returside value='$returside'>\n";
print "<input type=hidden name=fokus value='$fokus'>\n";
print "<input type=hidden name=kontakt value='$kontakt'>\n";
print "<input type=hidden name=pbs_date value='$pbs_date'>\n";
print "<input type=hidden name=gl_kontotype value='$kontotype'>\n";
?>