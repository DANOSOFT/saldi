<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------debitor/genfakturer.php-----ver 5.1.0-----2026-11-09------
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
// Copyright (c) 2003-2026 Danosoft ApS
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
// 20250815 PHR	Fiscal_year;
// 20261109 CL/SZ SST-926: a completed re-invoice skips invoices dated outside the fiscal year, as ordinary invoicing does.
//                The page now shows a receipt with the posted, sent and unsent counts, and says so when the pop-up is blocked.
// 20261109 CL/SZ SST-926 review: invoices with «Udskriv til» = Ingen stay out of the pop-up, and PBS counts only real handoffs.
//                The checked date is the one posted, a reload no longer re-runs the batch, and only the run's own pop-up can report.

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
	$genfaktRun=false;

	if ($afbryd==if_isset($_POST['Afbryd'])) {
 		print "<BODY onLoad=\"javascript:alert('Genfakturering afbrudt')\">";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=ordreliste.php\">";
		exit;
	}	elseif ($ok==if_isset($_POST['Ok'])) {
		$org_nr=if_isset($_POST['org_nr']);
		$komplet=if_isset($_POST['komplet']);
		$fakt_dato=if_isset($_POST['fakt_dato']);
		$opdat_pris=if_isset($_POST['opdat_pris']);
		$opdat_text=if_isset($_POST['opdat_text']);
		$slet_gfdato=if_isset($_POST['slet_gfdato']);
		$genfaktRun=true;

		$box1="$org_nr,$komplet,$fakt_dato,$opdat_pris,$slet_gfdato,$opdat_text";
		if ($gf_id)  db_modify("update grupper set box1='$box1' where id='$gf_id'",__FILE__ . " linje " . __LINE__);
		else db_modify("insert into grupper (beskrivelse,art,kodenr,box1) values ('Genfakturering','GF','$bruger_id','$box1')",__FILE__ . " linje " . __LINE__);

		$udskriv_antal=0;
		$udskriv='';
		$ny_liste='';
		$posted=array();
		$pbs_antal=0;
		$pbs_fejl=0;
		$ingen_antal=0;
		$skipped=array();
		$stopText='';
		$fiscalSpan=genfaktFiscalYearSpan($regnaar);
		for ($q=0; $q<$ordre_antal; $q++) {
			// bogfor() is called in webservice mode below, which skips its "invoice date outside fiscal year" check,
			// so a completed re-invoice checks the date here, before anything is copied or posted.
			if ($komplet) {
				$old=db_fetch_array(db_select("select fakturanr,firmanavn,nextfakt from ordrer where id='".(int)$ordre_id[$q]."'",__FILE__ . " linje " . __LINE__));
				$newDate=genfaktInvoiceDate($old ? $old['nextfakt'] : '',$fakt_dato);
				if (!$old || !genfaktDateInFiscalYear($newDate,$fiscalSpan)) {
					$skipped[]=array(
						'fakturanr'=>$old ? $old['fakturanr'] : '',
						'firmanavn'=>$old ? $old['firmanavn'] : '',
						'date'=>$newDate,
					);
					continue;
				}
			}
			list($id,$pbs)=explode(",",genfakt($ordre_id[$q],$org_nr,$fakt_dato,$opdat_pris,$opdat_text,$slet_gfdato,$regnaar,$komplet ? $newDate : null));

			if ($komplet) {
				levering($id,'on','on');
				$svar=bogfor($id,'on','on');
				if ($svar != 'OK') {
					if (strpos($svar,'invoicedate prior to')) $tekst="Genfaktureringsdato før fakturadato";
					else $tekst="Der er konstateret en ubalance i posteringssummen, kontakt venligst Danosoft på tlf. +45 46902208";
					// The invoices posted before this one still have to be sent, so the run stops and shows the receipt.
					$stopText=$tekst." (".html_entity_decode((string)$svar,ENT_QUOTES,'UTF-8').")";
					break;
				}
				$r=db_fetch_array(db_select("select fakturanr,udskriv_til from ordrer where id='".(int)$id."'",__FILE__ . " linje " . __LINE__));
				$posted[]=$r ? $r['fakturanr'] : '';
			} else {
				if ($ny_liste) $ny_liste.=",$id";
				else $ny_liste="$id";
			}
			if ($komplet && $pbs) {
				if (pbsfakt($id)) {
					$pbs_antal++;
				} else {
					$pbs_fejl++;
				}
			} elseif ($komplet && $r && $r['udskriv_til']=='ingen') {
				// formularprint() stops a whole batch at an invoice with «Udskriv til» = Ingen, so these stay out of the pop-up.
				$ingen_antal++;
			} else {
				if ($udskriv_antal) $udskriv.=",$id";
				else $udskriv ="$id";
				$udskriv_antal++;	
			}
		} 	
	}
	if ($genfaktRun && $komplet) {
		genfaktReceipt($posted,$pbs_antal,$pbs_fejl,$ingen_antal,$udskriv_antal,$udskriv,$skipped,$stopText);
	} else {
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
	
function genfakt($id,$org_nr,$fakt_dato,$opdat_pris,$opdat_text,$slet_gfdato,$regnaar,$checkedDate=null) {
	
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
		$cvrnr=str_replace(' ','',$cvrnr);
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
		// A completed run passes the date it checked against the fiscal year, so a run past midnight keeps it.
		$fakturadate=$checkedDate ? $checkedDate : genfaktInvoiceDate($r['nextfakt'],$fakt_dato);
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
				$qtxt = "select box4,box7 from grupper where art='VG' and kodenr='$gruppe' and fiscal_year = '$regnaar'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
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

/**
 * The invoice date a re-invoice gets: the old invoice's next-invoice date when
 * "use the next-invoice date" is chosen and set, otherwise today.
 *
 * @param string|null $nextfakt     The old invoice's ordrer.nextfakt (Y-m-d).
 * @param mixed       $useNextfakt  The fakt_dato choice from the form.
 * @return string Y-m-d
 */
function genfaktInvoiceDate($nextfakt,$useNextfakt) {
	if ($useNextfakt && $nextfakt) return $nextfakt;
	return date("Y-m-d");
}

/**
 * The first and last month of fiscal year $regnaar as YYYYMM, built the way bogfor() builds them.
 *
 * @param int $regnaar
 * @return array{start: string, end: string}|null Null when the fiscal year does not exist.
 */
function genfaktFiscalYearSpan($regnaar) {
	$qtxt="select box1,box2,box3,box4 from grupper where art='RA' and kodenr='".(int)$regnaar."'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if (!$r) return null;
	return array(
		'start'=>trim($r['box2']).sprintf('%02d',(int)$r['box1']),
		'end'=>trim($r['box4']).sprintf('%02d',(int)$r['box3']),
	);
}

/**
 * Whether an invoice date falls inside the fiscal year span, the check bogfor()
 * makes as "Fakturadato udenfor regnskabsår" outside webservice mode.
 *
 * @param string $date Y-m-d
 * @param array{start: string, end: string}|null $span From genfaktFiscalYearSpan().
 * @return bool
 */
function genfaktDateInFiscalYear($date,$span) {
	if (!$span) return false;
	$ym=substr($date,0,4).substr($date,5,2);
	return $ym>=$span['start'] && $ym<=$span['end'];
}

/**
 * Prints the receipt of a completed re-invoicing in the same window and opens the
 * sending/printing pop-up. A blocked pop-up is reported with a button that opens it,
 * and the pop-up reports back how many invoices it e-mailed.
 *
 * @param array<int, string> $posted  Invoice numbers posted in this run.
 * @param int    $pbsCount     Invoices added to a PBS delivery.
 * @param int    $pbsFailed    PBS invoices pbsfakt() could not add.
 * @param int    $noneCount    Invoices with «Udskriv til» = Ingen, which are not sent.
 * @param int    $printCount   Invoices to send or print in the pop-up.
 * @param string $printList    Comma-separated order ids for formularprint.php.
 * @param array<int, array{fakturanr: string, firmanavn: string, date: string}> $skipped
 *               Invoices not re-invoiced because the date is outside the fiscal year.
 * @param string $stopText     Why the run stopped early, or ''.
 * @return void
 */
function genfaktReceipt($posted,$pbsCount,$pbsFailed,$noneCount,$printCount,$printList,$skipped,$stopText) {
	global $charset,$sprog_id;

	$h=function($text) use ($charset) {
		return htmlspecialchars((string)$text,ENT_QUOTES,$charset ? $charset : 'UTF-8');
	};
	$numbers=array_values(array_filter($posted,'is_numeric'));
	$range='';
	if (count($numbers)) {
		$range=(min($numbers)==max($numbers)) ? ' ('.min($numbers).')' : ' ('.min($numbers).'-'.max($numbers).')';
	}

	print "<div id='genfaktReceipt' style='margin:20px;max-width:700px;font-size:14px'>\n";
	print "<h2>".$h(findtekst('reinvoice.receipt.title|Genfakturering udført',$sprog_id))."</h2>\n";
	if ($stopText) {
		print "<p style='color:#b00000;font-weight:bold'>".$h(findtekst('reinvoice.receipt.stopped|Genfaktureringen stoppede, før alle fakturaer var bogført:',$sprog_id))." ".$h($stopText)."</p>\n";
	}
	print "<table border='0' cellpadding='4'><tbody>\n";
	print "<tr><td>".$h(findtekst('reinvoice.receipt.posted|Bogførte fakturaer',$sprog_id))."</td><td align='right'><b>".count($posted)."</b>".$h($range)."</td></tr>\n";
	if ($pbsCount) {
		print "<tr><td>".$h(findtekst('reinvoice.receipt.pbs|Overført til PBS',$sprog_id))."</td><td align='right'><b>$pbsCount</b></td></tr>\n";
	}
	if ($pbsFailed) {
		print "<tr><td style='color:#b00000'>".$h(findtekst('reinvoice.receipt.pbs_failed|Ikke overført til PBS, se beskeden øverst',$sprog_id))."</td><td align='right'><b>$pbsFailed</b></td></tr>\n";
	}
	if ($noneCount) {
		print "<tr><td>".$h(findtekst('reinvoice.receipt.none|Udskriv til = Ingen, sendes ikke',$sprog_id))."</td><td align='right'><b>$noneCount</b></td></tr>\n";
	}
	if ($printCount) {
		print "<tr><td>".$h(findtekst('reinvoice.receipt.mails_sent|Sendt pr. e-mail',$sprog_id))."</td><td align='right'><b id='genfaktMails'>-</b></td></tr>\n";
		print "<tr><td>".$h(findtekst('reinvoice.receipt.printed|Vist som PDF til udskrift',$sprog_id))."</td><td align='right'><b id='genfaktPrints'>-</b></td></tr>\n";
		print "<tr><td>".$h(findtekst('reinvoice.receipt.not_sent|Ikke sendt eller udskrevet',$sprog_id))."</td><td align='right'><b id='genfaktNotSent'>$printCount</b></td></tr>\n";
	}
	print "</tbody></table>\n";

	if ($printCount) {
		$url="formularprint.php?id=-1&ordre_antal=$printCount&skriv=$printList&formular=4&kvittering=genfakt";
		print "<p id='genfaktStatus' style='font-weight:bold'></p>\n";
		print "<p id='genfaktNotSentHelp' style='display:none'>".$h(findtekst('reinvoice.receipt.not_sent_help|Send eller udskriv dem fra fakturalisten.',$sprog_id))."</p>\n";
		print "<p><input type='button' id='genfaktSend' style='display:none' value='".$h(findtekst('reinvoice.receipt.send_button|Send og udskriv fakturaerne',$sprog_id))."'></p>\n";
		print "<span id='genfaktTexts' style='display:none'";
		print " data-url='".$h($url)."' data-total='$printCount'";
		print " data-running='".$h(findtekst('reinvoice.receipt.running|Fakturaerne sendes i pop-up-vinduet. Luk det ikke, før det er færdigt.',$sprog_id))."'";
		print " data-blocked='".$h(findtekst('reinvoice.receipt.blocked|Fakturaerne er bogført, men IKKE sendt: browseren blokerede pop-up-vinduet. Klik på knappen herunder for at sende dem.',$sprog_id))."'";
		print " data-closed='".$h(findtekst('reinvoice.receipt.closed|Pop-up-vinduet blev lukket, før afsendelsen var bekræftet. Kontrollér i fakturalisten, hvilke fakturaer der er sendt.',$sprog_id))."'";
		print " data-done='".$h(findtekst('reinvoice.receipt.done|Afsendelsen er færdig.',$sprog_id))."'";
		print " data-failed='".$h(findtekst('reinvoice.receipt.failed|Afsendelsen stoppede med en fejl, se pop-up-vinduet. Fakturaerne herover er bogført, men de ikke sendte skal sendes igen.',$sprog_id))."'";
		print "></span>\n";
	}

	if (count($skipped)) {
		print "<p style='color:#b00000;font-weight:bold'>".$h(findtekst('reinvoice.receipt.skipped|Ikke genfaktureret, fordi fakturadatoen ligger uden for regnskabsåret:',$sprog_id))."</p>\n";
		print "<table border='0' cellpadding='4'><tbody>\n";
		foreach ($skipped as $s) {
			print "<tr><td>".$h($s['fakturanr'])."</td><td>".$h($s['firmanavn'])."</td><td>".$h(dkdato($s['date']))."</td></tr>\n";
		}
		print "</tbody></table>\n";
		print "<p>".$h(findtekst('reinvoice.receipt.skipped_help|Ret Genfakt.-datoen eller opret regnskabsåret, og genfakturér dem igen.',$sprog_id))."</p>\n";
	}
	print "<p><a href='ordreliste.php'>".$h(findtekst('reinvoice.receipt.back|Tilbage til fakturalisten',$sprog_id))."</a></p>\n";
	print "</div>\n";
	// Turn the POST into a plain history entry, so reloading the receipt shows the form again instead of re-running the batch.
	print "<script type='text/javascript'>if (window.history.replaceState) { window.history.replaceState(null, '', window.location.href); }</script>\n";

	if ($printCount) {
?>
<script type="text/javascript">
(function () {
	var texts = document.getElementById('genfaktTexts');
	var statusLine = document.getElementById('genfaktStatus');
	var sendButton = document.getElementById('genfaktSend');
	var total = parseInt(texts.getAttribute('data-total'), 10);
	var popup = null;
	var confirmed = false;

	function showStatus(key, color) {
		statusLine.textContent = texts.getAttribute('data-' + key);
		statusLine.style.color = color;
	}

	window.addEventListener('message', function (event) {
		if (event.origin !== window.location.origin || event.source !== popup || !event.data || event.data.type !== 'saldi-genfakt-print') {
			return;
		}
		confirmed = true;
		var mails = parseInt(event.data.mails, 10) || 0;
		var prints = parseInt(event.data.prints, 10) || 0;
		var notSent = Math.max(0, total - mails - prints);
		document.getElementById('genfaktMails').textContent = mails;
		document.getElementById('genfaktPrints').textContent = prints;
		document.getElementById('genfaktNotSent').textContent = notSent;
		document.getElementById('genfaktNotSentHelp').style.display = notSent ? '' : 'none';
		if (event.data.complete === false) {
			showStatus('failed', '#b00000');
		} else {
			showStatus('done', notSent ? '#b00000' : '#006000');
		}
	});

	function openPopup() {
		popup = window.open(texts.getAttribute('data-url'), '', 'statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes,location=1');
		if (!popup) {
			showStatus('blocked', '#b00000');
			sendButton.style.display = '';
			return;
		}
		sendButton.style.display = 'none';
		showStatus('running', '');
		var watch = setInterval(function () {
			if (confirmed) {
				clearInterval(watch);
			} else if (popup.closed) {
				clearInterval(watch);
				showStatus('closed', '#b00000');
			}
		}, 1000);
	}

	sendButton.onclick = openPopup;
	openPopup();
})();
</script>
<?php
	}
}
########################################################################################
?>
