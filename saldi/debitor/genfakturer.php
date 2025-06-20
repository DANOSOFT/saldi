<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------debitor/genfakturer.php-----lap 3.8.9-----2020-02-20------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// Erstattet addslashes med db_escape_string
// 2014.03.17 Tilføjet procent til "insert into ordrelinjer... 
// 2016.08.25 Kontonr blev ikke opdatere hvid der var blevet skiftet kontonr på kunde inden genfakt 21060825
// 2017.01.03 Funktion find_nextfakt flyttet til ../includes/ordrefunc.php
// 2018.01.05 Tilføjet opdatering af varetekster. $opdat_text 
// 2019.10.22 PHR - Added update of 'varenr' if changed at 'varekort.
// 2020.01.13 PHR - Added  check for vat settings - search '$vatAccount'
// 2020.02.03 PHR - Critical '$vatAccount' was insertet instead of vat_account. 
// 2020.02.20 PHR - $cvrnr set to '' if not valid; 

@session_start();
$s_id=session_id();

$id=$_GET['id'];
$css="../css/standard.css";

if ($id==-1){	# Saa er der flere fakturaer
	$ordre_antal = $_GET['ordre_antal'];
	$ordreliste = $_GET['genfakt'];
	$ordre_id = explode(",",$ordreliste);
} else {
	$ordre_id[0]=$id;
	$ordre_antal=1;	
}

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

#include("../includes/db_query.php");
#include("levering.php");
#include("bogfor.php");
include("pbsfakt.php");

$r=db_fetch_array(db_select("select id,box1 from grupper where art = 'GF' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
$gf_id=$r['id'];
list($org_nr,$komplet,$fakt_dato,$opdat_pris,$slet_gfdato,$opdat_text) = explode(",",$r['box1']);
if ($org_nr) {$org_nr_on='checked';$org_nr_off='';}
else {$org_nr_on='';$org_nr_off='checked';}
if ($komplet) {$komplet_on='checked';$komplet_off='';}
else {$komplet_on='';$komplet_off='checked';}
if ($fakt_dato) {$fakt_dato_on='checked';$fakt_dato_off='';}
else {$fakt_dato_on='';$fakt_dato_off='checked';}
if ($opdat_pris) {$opdat_pris_on='checked';$opdat_pris_off='';}
else {$opdat_pris_on='';$opdat_pris_off='checked';}
if ($opdat_text) {$opdat_text_on='checked';$opdat_text_off='';}
else {$opdat_text_on='';$opdat_text_off='checked';}
if ($slet_gfdato) {$slet_gfdato_on='checked';$slet_gfdato_off='';}
else {$slet_gfdato_on='';$slet_gfdato_off='checked';}

if (!$gf_id) {
	$org_nr_on='checked';
	$komplet_off='checked';
	$fakt_dato_on='checked';
	$opdat_pris_on='checked';
	$opdat_text_on='checked';
	$slet_gfdato_on='checked';
}

if ($_POST) {
	$ok=findtekst(80,$sprog_id);
	
	$year=date("Y");
	$month=date("m");
	$del1="(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
	$del2="(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
	$del3="(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
	$qtxt="select kodenr from grupper where art='RA' and ($del1 or $del2 or $del3)";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$regnaar=$r['kodenr']*1;
	} elseif ($r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='RA'",__FILE__ . " linje " . __LINE__))) {
		$regnaar=$r['kodenr']*1;
	} else $regnaar=1;

	$afbryd=findtekst(81,$sprog_id);
	if ($afbryd==if_isset($_POST[$afbryd])) {
 		print "<BODY onLoad=\"javascript:alert('Genfakturering afbrudt')\">";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">";
		exit;
	}	elseif ($ok==if_isset($_POST[$ok])) {	
		$org_nr=if_isset($_POST['org_nr']);
		$komplet=if_isset($_POST['komplet']);
		$fakt_dato=if_isset($_POST['fakt_dato']);
		$opdat_pris=if_isset($_POST['opdat_pris']);
		$opdat_text=if_isset($_POST['opdat_text']);
		$slet_gfdato=if_isset($_POST['slet_gfdato']);

		$box1="$org_nr,$komplet,$fakt_dato,$opdat_pris,$slet_gfdato,$opdat_text";
		if ($gf_id)  db_modify("update grupper set box1='$box1' where id='$gf_id'",__FILE__ . " linje " . __LINE__);
		else db_modify("insert into grupper (beskrivelse,art,kodenr,box1) values ('Genfakturering','GF','$bruger_id','$box1')",__FILE__ . " linje " . __LINE__);

		$udskriv_antal=0;
		$ny_liste='';
		for ($q=0; $q<$ordre_antal; $q++) {
			list($id,$pbs)=explode(",",genfakt($ordre_id[$q],$org_nr,$fakt_dato,$opdat_pris,$opdat_text,$slet_gfdato,$regnaar));

			if ($komplet) {
				levering($id,'on','on');
				$svar=bogfor($id,'on','on');
				if ($svar != 'OK') {
					if (strpos($svar,'invoicedate prior to')) $tekst="Genfaktureringsdato før fakturadato";
					else $tekst="Der er konstateret en ubalance i posteringssummen,\\nkontakt venligst Danosoft på tlf. +45 46902208";
					print "<BODY onLoad=\"javascript:alert('$tekst')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
					exit;
				}
			} else {
				if ($ny_liste) $ny_liste.=",$id";
				else $ny_liste="$id";
			}
			if ($komplet && $pbs) {
				pbsfakt($id);
			} else {
				if ($udskriv_antal) $udskriv.=",$id";
				else $udskriv ="$id";
				$udskriv_antal++;	
			}
		} 	
	}
	if ($udskriv && $komplet) print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$udskriv_antal&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
	else {
		print "<meta http-equiv=\"refresh\" content=\"1;URL=ret_genfakt.php?ordreliste=$ny_liste\">";	
	}
	
} else {
	print "<form name=genfakturer action=genfakturer.php?id=$id&ordre_antal=$ordre_antal&genfakt=$ordreliste method=post>";
	print "<table border=0><tbody>";
	print "<tr><td>".findtekst(82,$sprog_id)."</td><td align=center>".findtekst(83,$sprog_id)."</td><td align=center>".findtekst(84,$sprog_id)."</td></tr>";
	print "<tr><td title='".findtekst(68,$sprog_id)."'>".findtekst(69,$sprog_id)."</td><td align=center><input type=radio name=org_nr value=1 title='".findtekst(70,$sprog_id)."' $org_nr_on></td><td align=center><input type=radio name=org_nr value=0 title='".findtekst(71,$sprog_id)."' $org_nr_off></td></tr>";
	print "<tr><td title='".findtekst(72,$sprog_id)."'>".findtekst(73,$sprog_id)."</td><td align=center><input type=radio name=komplet value=1 title='".findtekst(74,$sprog_id)."' $komplet_on></td><td align=center><input type=radio name=komplet value=0 title='".findtekst(75,$sprog_id)."' $komplet_off></td></tr>";
	print "<tr><td title='".findtekst(76,$sprog_id)."'>".findtekst(77,$sprog_id)."</td><td align=center><input type=radio name=fakt_dato value=1 title='".findtekst(78,$sprog_id)."' $fakt_dato_on></td><td align=center	><input type=radio name=fakt_dato value=0 title='".findtekst(79,$sprog_id)."' $fakt_dato_off></td></tr>";
	print "<tr><td title='".findtekst(85,$sprog_id)."'>".findtekst(86,$sprog_id)."</td><td align=center><input type=radio name=opdat_pris value=1 title='".findtekst(87,$sprog_id)."' $opdat_pris_on></td><td align=center	><input type=radio name=opdat_pris value=0 title='".findtekst(88,$sprog_id)."' $opdat_pris_off></td></tr>";
	print "<tr><td title='".findtekst(843,$sprog_id)."'>".findtekst(844,$sprog_id)."</td><td align=center><input type=radio name=opdat_text value=1 title='".findtekst(845,$sprog_id)."' $opdat_text_on></td><td align=center	><input type=radio name=opdat_text value=0 title='".findtekst(846,$sprog_id)."' $opdat_text_off></td></tr>";
	print "<tr><td title='".findtekst(220,$sprog_id)."'>".findtekst(221,$sprog_id)."</td><td align=center><input type=radio name=slet_gfdato value=1 title='".findtekst(222,$sprog_id)."' $slet_gfdato_on></td><td align=center	><input type=radio name=slet_gfdato value=0 title='".findtekst(223,$sprog_id)."' $slet_gfdato_off></td></tr>";
	print "<tr><td colspan=3 align=center><input type=submit name=Ok value=".findtekst(80,$sprog_id).">&nbsp;<input type=submit name=Afbryd value=".findtekst(81,$sprog_id)."></td></tr>";
	print "</tbody></table>";
	print "</form>";
}
	
function genfakt($id,$org_nr,$fakt_dato,$opdat_pris,$opdat_text,$slet_gfdato,$regnaar) {
	
	transaktion('begin');
	if ($r=db_fetch_array(db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__))){
		$pbs=$r['pbs'];
		$konto_id=$r['konto_id'];
		$firmanavn=db_escape_string($r['firmanavn']);
		$addr1=db_escape_string($r['addr1']);
		$addr2=db_escape_string($r['addr2']);
		$bynavn=db_escape_string($r['bynavn']);
		$land=db_escape_string($r['land']);
		$cvrnr=db_escape_string($r['cvrnr']);
		if ($cvrnr && !is_numeric(substr($cvrnr,2))) {
			$cvrnr='';
			alert("fejl i CVR nr for $firmanavn\\rCVR nr fjernet"); 
		}
		$ean=db_escape_string($r['ean']);
		$sprog=db_escape_string($r['sprog']);
		$valuta=db_escape_string($r['valuta']);
		$projekt=db_escape_string($r['projekt']);
		$institution=db_escape_string($r['institution']);
		$notes=db_escape_string($r['notes']);
		$ref=db_escape_string($r['ref']);
		$kontakt=db_escape_string($r['kontakt']);
		$kundeordnr=db_escape_string($r['kundeordnr']);
		$lev_navn=db_escape_string($r['lev_navn']);
		$lev_addr1=db_escape_string($r['lev_addr1']);
		$lev_addr2=db_escape_string($r['lev_addr2']);
		$lev_bynavn=db_escape_string($r['lev_bynavn']);
		$email=db_escape_string($r['email']);
		$udskriv_til=db_escape_string($r['udskriv_til']);
		if (strstr($udskriv_til,'PBS')) $udskriv_til='PBS';
		$procenttillag=db_escape_string($r['procenttillag']);
		if ($r['nextfakt']) $tmp=$r['nextfakt'];
		else $tmp=date("Y-m-d");			
		$nextfakt=find_nextfakt($r['fakturadate'],$tmp);
		if ($fakt_dato) $fakturadate=$r['nextfakt'];
		else $fakturadate=date("Y-m-d");
		if (!$fakturadate) $fakturadate=date("Y-m-d");
		if ($org_nr) $ordrenr=$r['ordrenr'];
		else {
			$r2=db_fetch_array(db_select("select MAX(ordrenr) as ordrenr from ordrer where art='DO' or art='DK'",__FILE__ . " linje " . __LINE__));
			$ordrenr=$r2['ordrenr']+1;
		}
		$r2=db_fetch_array(db_select("select kontonr from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__)); #20160825
		$kontonr=$r2['kontonr'];
		$qtxt = "insert into ordrer ";
		$qtxt.= "(ordrenr, konto_id, kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,";
		$qtxt.= "institution,notes,art,ordredate,momssats,moms,ref,valuta,sprog,kontakt,kundeordnr,lev_navn,lev_addr1,lev_addr2,";
		$qtxt.= "lev_postnr,lev_bynavn,levdate,fakturadate,nextfakt,sum,status,projekt,email,mail_fakt,pbs,udskriv_til,procenttillag)";
		$qtxt.= " values ";
		$qtxt.= "('$ordrenr','$konto_id','$kontonr','$firmanavn','$addr1','$addr2','$r[postnr]','$bynavn','$land','$r[betalingsdage]',";
		$qtxt.= "'$r[betalingsbet]','$cvrnr','$ean','$institution','$notes','$r[art]','$r[ordredate]','$r[momssats]','$r[moms]','$ref','$valuta',";
		$qtxt.= "'$sprog','$kontakt','$kundeordnr','$lev_navn','$lev_addr1','$lev_addr2','$r[lev_postnr]','$lev_bynavn','$fakturadate',";
		$qtxt.= "'$fakturadate','$nextfakt','$r[sum]','2','$projekt','$email','$r[mail_fakt]','$pbs','$udskriv_til','$procenttillag')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r2=db_fetch_array(db_select("select id from ordrer where ordrenr='$ordrenr' and nextfakt='$nextfakt' and (art='DO' or art='DK') order by id desc",__FILE__ . " linje " . __LINE__));
		$ny_id=$r2['id'];
		$sum=0;
		$x=0;
		$q=db_select("select * from ordrelinjer where ordre_id = $id and (kdo!='on' or kdo is NULL) order by posnr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			($r['projekt'])?$projekt=$r['projekt']:$projekt='';
			$lev_varenr=$r['lev_varenr'];
			if ($r['vare_id']){
				$r2=db_fetch_array(db_select("select varenr,gruppe from varer where id='$r[vare_id]'",__FILE__ . " linje " . __LINE__));
				$gruppe=$r2['gruppe'];
				$varenr=$r2['varenr'];
				$r2=db_fetch_array(db_select("select box4,box7 from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
				$bogfkto = $r2['box4'];
				$momsfri=$r2['box7'];
				if ($opdat_pris) {
					$r2=db_fetch_array(db_select("select salgspris,kostpris from varer where id='$r[vare_id]'",__FILE__ . " linje " . __LINE__));
					$pris=$r2['salgspris']*1;
					$kostpris=$r2['kostpris']*1;
					$sum=$sum+$r['antal']*$pris-($r['antal']*$pris*$r['rabat']/100);
				} else {
					$pris=$r['pris']*1;
					$kostpris=$r['kostpris']*1;
				}
				if ($opdat_text) {
					$r2=db_fetch_array(db_select("select beskrivelse from varer where id='$r[vare_id]'",__FILE__ . " linje " . __LINE__));
					$beskrivelse=$r2['beskrivelse'];
				} else {
					$beskrivelse=$r['beskrivelse'];
				}
				if ($r['vare_id']){
					
				}
				if ($bogfkto && !$momsfri) {
					$qtxt="select moms from kontoplan where kontonr = '$bogfkto' and regnskabsaar = '$regnaar'";
					$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					if ($tmp=trim($r2['moms'])) { # f.eks S3
						$tmp=substr($tmp,1); #f.eks 3
						$qtxt="select box1,box2 from grupper where art = 'SM' and kodenr = '$tmp'";
						$r2 = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						if ($r2['box1']) $vatAccount=$r2['box1']*1;
						if ($r2['box2']) $varemomssats=$r2['box2']*1;
					}	else {
						$varemomssats=$momssats;
						$vatAccount=0;
					}	
				} else {
					$varemomssats=0;
					$vatAccount=0;
				}
				if (!$momsfri && !$vatAccount && $varemomssats) {
					$alerttxt = "Varegruppe $gruppe som er momsbelagt er tilknyttet konto $bogfkto. (varenr $varenr)\\n";
					$alerttxt.= "Konto $bogfkto er ikke momsbelagt i regnskabsår $regnaar.\\n";
					$alerttxt.= "Genfakturering afbrudt";
					alert ($alerttxt);
					print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">";
					exit;
				}
/*
				if ($varemomssats && !$vatAccount) {
					$alerttxt="Manglende konto for salgsmoms. (Varenr: $varenr) \\nGenfakturering afbrudt";
					alert ($alerttxt);
					print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">";
					exit;
				}
*/				
				$qtxt="insert into ordrelinjer ";
				$qtxt.="(ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,procent,lev_varenr,momsfri,samlevare,kostpris";
				$qtxt.=",leveres,projekt,bogf_konto) ";
				$qtxt.="values ";
				$qtxt.="('$ny_id','$r[posnr]','".db_escape_string($varenr)."','$r[vare_id]','".db_escape_string($beskrivelse)."','$r[enhed]','$r[antal]',";
				$qtxt.="'$pris','$r[rabat]','$r[procent]','".db_escape_string($lev_varenr)."','$momsfri','$r[samlevare]','$kostpris','$r[antal]',";
				$qtxt.="'".db_escape_string($projekt)."','$bogfkto')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}	else {
				$qtxt="insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$ny_id','$r[posnr]','".db_escape_string($r['beskrivelse'])."')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}	
		if ($opdat_pris) db_modify("update ordrer set sum=$sum where id='$ny_id'",__FILE__ . " linje " . __LINE__);	
		if ($slet_gfdato) db_modify("update ordrer set nextfakt=NULL where id='$id'",__FILE__ . " linje " . __LINE__);	
	}
	transaktion('commit');
#exit;
	$tmp=$ny_id.",".$pbs;
	return($tmp);
}	
########################################################################################
?>
