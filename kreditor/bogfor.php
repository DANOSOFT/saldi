<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/bogfor.php --- ver 5.1.1 --- 2026-11-13 ---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// -------------------------------------------------------------------------
//
// 2013.11.08 Fejl v. fifo og varetilgang på varekøb.Søg #20131108
// 2014.06.11 Indsat if($batch_id) da kostpriser i ordrelinjer ellers bliver ændret på alt som ikke har batch_kob_id v. negativt køb af vare som aldrig har været handlet.
// 2014.10.05 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 2014.11.17 Fejl v. fifo og vareafgang v. negativt varekøb.Søg #20141117
// 2015.04.22 Hvis samme vare købes til forskellige priser blev kostpris sat til sidste pris. Ændret så den nu sættes til snitprisen. Søg snitpris.
// 2017.04.04	PHR - Straksbogfør skelner nu mellem debitor og kreditorordrer. Dvs debitor;kreditor - Søg # 20170404
// 2017.10.26	PHR Udkommenteret 4 linjer da lagerførte varer fra udland blev bogført på varekøb DK
// 2020.06.21 PHR adresser.invoiced is updated when order is invoiced.
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20230616 PHR - php8
// 20230626 PHR - Outcommented section as it is overloading the system and I don't think it is neccesary.
// 20230824	PHR - Added call to productsIncludes & updateProductPrice. 
// 20240626 PHR Added 'fiscal_year' in queries
// 20250206 PHR Removed obsolete section that inserted values in accounts for product purchase and sale!
// 20260304 PHR Costprice is from now only updated on positive qty og price.
// 20260604 CL/PHR reads baseCountry from settings, passes to cvrnr_land/cvrnr_omr
// 20260908 CDX/LH Lock creditor orders before posting and update split purchase batches once (SST-765).
// 20261113 CDX/NTR Delegate supplier ledger posting to its dedicated module (SD-599).
// 20261113 CDX/NTR Select the supplier posting adapter at the ledger hand-off.

@session_start();
$s_id=session_id();
require_once __DIR__ . '/../includes/invoicePosting.php';

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

echo "<br>";


$afd = 0;

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
	print print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
}
// Serialize eligibility with order saves and receipts before reading dependent data.
transaktion("begin");
$qtxt = "select levdate, status from ordrer where id = '$id' for update";
$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if (!$r || $r['status'] > 2) {
	print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	transaktion("rollback");
	exit;
}
$status = $r['status'];

$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)){
	$year=substr(str_replace(" ","",$row['box2']),-2);
	$aarstart=str_replace(" ","",$year.$row['box1']);
	$year=substr(str_replace(" ","",$row['box4']),-2);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}

$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
$fifo=$r['box6'];
$r=db_fetch_array(db_select("select box6 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
$kostmetode=$r['box6'];


$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);
$art=$row['art'];
$konto_id=$row['konto_id'];
$kred_ord_id=$row['kred_ord_id'];
$levdate=$row['levdate'];
$valuta=$row['valuta'];
$projekt[0]=$row['projekt'];
$cvrnr=$row['cvrnr'];
$r_bc = db_fetch_array(db_select("select var_value from settings where var_name='baseCountry' limit 1",__FILE__ . " linje " . __LINE__));
$baseCountry = $r_bc['var_value'] ?: 'dk';
if ($valuta && $valuta!='DKK') {
	if ($r= db_fetch_array(db_select("select valuta.kurs as kurs, grupper.box3 as difkto from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr",__FILE__ . " linje " . __LINE__)." and valuta.valdate <= '$levdate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
		$valutakurs=$r['kurs']*1;
		$difkto=$r['difkto']*1;
		if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
			print "<BODY onLoad=\"javascript:alert('Kontonr $difkto (kursdiff) eksisterer ikke')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			transaktion("rollback");
			exit;
		}
	} else {
		$tmp = dkdato($levdate);
		print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $tmp')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		transaktion("rollback");
		exit;
	}
} else {
	$valuta='DKK';
	$valutakurs=100;
}
	
if (!$row['levdate']){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	transaktion("rollback");
	exit;
} elseif (!trim($row['fakturanr'])){
	print "<BODY onLoad=\"javascript:alert('Fakturanummer SKAL udfyldes og m&aring; ikke v&aelig;re 0')\">";
	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	transaktion("rollback");
	exit;
} else {
	$fejl=0;
	if ($row['levdate']<$row['ordredate']){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		transaktion("rollback");
		exit;
	}
	$levdate=$row['levdate'];
	list ($year, $month, $day) = explode ('-', $row['levdate']);
	$year=substr($year,-2);
	$ym=$year.$month;
	if (($ym<$aarstart)||($ym>$aarslut)){
		print "<BODY onLoad=\"javascript:alert('Leveringsdato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		transaktion("rollback");
		exit;
	}
	if ($fejl==0){
		echo "bogf&oslash;rer nu!........";
		$x=0;
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)){
			if (($row['posnr']>0)&&(strlen(trim(($row['varenr'])))>0)){
				$x++;
				$linje_id[$x]=$row['id'];
				$kred_linje_id[$x]=$row['kred_linje_id'];
				$varenr[$x]=$row['varenr'];
				$vare_id[$x]=$row['vare_id'];
				$antal[$x]=$row['antal'];
				if ($row['projekt']) $projekt[$x]=$row['projekt'];
				else $projekt[$x]=$projekt[0];
				$pris[$x]=$row['pris']-($row['pris']*$row['rabat']/100);
				if ($valutakurs) $dkpris[$x]=afrund(($pris[$x]*$valutakurs/100),3); # Omregning til DKK.
				else $dkpris[$x]=$pris[$x];
				$serienr[$x]=$row['serienr'];
				$samlevare[$x]=$row['samlevare'];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			$totQtyPos[$x]=0;
			$totPricePos[$x]=0;
			$totQtyNeg[$x]=0;
			$totPriceNeg[$x]=0;
			$qtxt = "select * from ordrelinjer where ordre_id = '$id' and vare_id = '$vare_id[$x]'";
			$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)){
				if ($r['antal'] > 0 && $r['pris'] > 0) {
					$totQtyPos[$x]+=$r['antal'];
					$totPricePos[$x]+=$r['pris']*$r['antal'];
				} else {
					$totQtyNeg[$x]+=$r['antal'];
					$totPriceNeg[$x]+=$r['pris']*$r['antal'];
				}
			}
			if ($totQtyPos[$x]) {
				$avPricePos[$x]=$totPricePos[$x]/$totQtyPos[$x];
				$avPricePos[$x]=$totPricePos[$x]/$totQtyPos[$x];
			} else {
				$avPricePos[$x]=$pris[$x];
			}
			if ($valutakurs) {
				$avPricePos[$x]=afrund(($avPricePos[$x]*$valutakurs/100),3); # Omregning til DKK.
			}
		}
		for ($x=1; $x<=$linjeantal; $x++) {
			$query = db_select("select id, gruppe,beholdning,kostpris,delvare,retail_price from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__); #rettet fra varenr til vare_id 20120612 grundet æøå problem i varenr.
			$row = db_fetch_array($query);
			$vare_id[$x]=$row['id'];
			$gruppe[$x]=$row['gruppe'];
			$beholdning[$x]=$row['beholdning'];
			$gl_kostpris[$x]=$row['kostpris'];
			$delvare[$x]=$row['delvare'];
			$gl_retail_price[$x]=$row['retail_price'];
		}
		for ($x=1; $x<=$linjeantal; $x++) {
			if (($vare_id[$x])&&($antal[$x]!=0)) {
				$qtxt = "select * from grupper where art='VG' and kodenr='$gruppe[$x]' and fiscal_year = '$regnaar'";
				$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$vgbeskrivelse=$row['beskrivelse']; $box1=trim($row['box1']); $box2=trim($row['box2']); $box3=trim($row['box3']); $box4=trim($row['box4']); $box8=trim($row['box8']); $box9=trim($row['box9']);
				$box11=trim($row['box11']);$box13=trim($row['box13']);
				if (!$box3) {
					print "<BODY onLoad=\"javascript:alert('Der er ikke opsat kontonummer for varek&oslash;b p&aring; varegruppen: $vgbeskrivelse.')\">";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					transaktion("rollback");
					exit;
				}
				$box1=$box3;
				$box2=$box4;
				if ($box11 && cvrnr_omr(cvrnr_land($cvrnr, $baseCountry), $baseCountry) == "EU") $bf_kto=$box11;
				elseif ($box13 && cvrnr_omr(cvrnr_land($cvrnr, $baseCountry), $baseCountry) == "UD") $bf_kto=$box13;
				else $bf_kto=$box3;
				if ($box8!='on'){
					db_modify("update ordrelinjer set bogf_konto='$bf_kto' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					db_modify("update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
				} else {
#					if ($box1) { #Box 1 er konto for lagertilgang, #udkommenteret 20171026
#						db_modify("update ordrelinjer set bogf_konto='$box1' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__); #udkommenteret 20171026
#					} else { #udkommenteret 20171026
						db_modify("update ordrelinjer set bogf_konto='$bf_kto' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
#					} #udkommenteret 20171026
					if ($antal[$x]>0) {
						$query = db_select("select * from batch_kob where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
						$hasPurchaseBatches = false;
						while ($row = db_fetch_array($query)) {
							// Preserve the batch id used by a later negative order line.
							$batch_id = (int) $row['id'];
							$hasPurchaseBatches = true;
						}
						if ($hasPurchaseBatches) {
							db_modify("update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
						}
						if ($fifo) {
								$bogf_beh=0;
							$q=db_select("select * from batch_kob where vare_id=$vare_id[$x] and linje_id!=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
							while ($r=db_fetch_array($q)){
								if ($r['fakturadate']) $bogf_beh+=$r['rest'];
							}
							
							if ($bogf_beh>0 && $kostmetode=='1' ) {
								$ny_kostpris=($bogf_beh*$gl_kostpris[$x]+$antal[$x]*$avPricePos[$x])/($bogf_beh+$antal[$x]);
							} else $ny_kostpris=$avPricePos[$x];
						} elseif ($beholdning[$x]>0 && $kostmetode=='1') $ny_kostpris=($beholdning[$x]*$gl_kostpris[$x]+$antal[$x]*$avPricePos[$x])/($beholdning[$x]+$antal[$x]);
						else $ny_kostpris=$avPricePos[$x];
						if ($kostmetode) {
							if ($ny_kostpris!=$gl_kostpris[$x] && $art=="KO") {
								include_once("../lager/productsIncludes/updateProductPrice.php");
								updateProductPrice($vare_id[$x],$ny_kostpris,$levdate);
								if ($delvare[$x] == 'on') {
									$costDiff = $ny_kostpris - $gl_kostpris[$x];
									$query = db_select("select retail_price from varer where id='$vare_id[$x]'", __FILE__ . " linje " . __LINE__);
									$row = db_fetch_array($query);
									$retailDiff = $row['retail_price'] - $gl_retail_price[$x];
									include_once('../lager/productCardIncludes/updateParentPrice.php');
									updateParentPrice($vare_id[$x], $costDiff, $retailDiff);
								}
							}
						}	
						$qtxt = "update vare_lev set kostpris='$avPricePos[$x]' where vare_id='$vare_id[$x]' and lev_id='$konto_id'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else {
						$batch_id=(int)$batch_id;
						$query = db_select("select * from batch_kob where vare_id=$vare_id[$x] and linje_id=$linje_id[$x]",__FILE__ . " linje " . __LINE__);
						while ($row = db_fetch_array($query)) {
						db_modify("update batch_kob set pris = '$dkpris[$x]', fakturadate='$levdate' where id=$row[id]",__FILE__ . " linje " . __LINE__);
						if ($batch_id) { #20140611
							$q2 = db_select("select linje_id from batch_salg where batch_kob_id=$batch_id",__FILE__ . " linje " . __LINE__);
								while ($r2 = db_fetch_array($q2)) {
									db_modify("update ordrelinjer set kostpris = '$dkpris[$x]' where id=$r2[linje_id]",__FILE__ . " linje " . __LINE__);
								}
							} # endif ($batch_id)
						}
					} # endif & else ($antal[$x]>0)
				}
			}
		}
		$modtagelse=1;
		$query = db_select("select modtagelse from ordrer order by modtagelse",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {	
			if ($row['modtagelse'] >=$modtagelse) {$modtagelse = $row['modtagelse']+1;}
		}
		$row = db_fetch_array($query = db_select("select box2 from grupper where art = 'RB'",__FILE__ . " linje " . __LINE__));
		if ($modtagelse==1) $modtagelse = $row['box2']*1;
		if ($modtagelse<1) $modtagelse=1;
		$qtxt = "update ordrer set status=3, fakturadate='$levdate', modtagelse = '$modtagelse', ";
		$qtxt.= "valuta = '$valuta', valutakurs = '$valutakurs' where id=$id";	
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update adresser set invoiced='$levdate' where id=$konto_id";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));
	if (strstr($r['box5'],';')) list($tmp,$straksbogfor)=explode(';',$r['box5']); # 20170404
		else $straksbogfor=$r['box5'];
		if ($straksbogfor) bogfor($id);
		transaktion("commit");
	}
}
print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";

/** @see SaldiInvoicePosting */
function bogfor($id) {
	$invoiceId = (int)$id;
	$row = db_fetch_array(db_select("select art from ordrer where id='$invoiceId'", __FILE__ . ' linje ' . __LINE__));
	invoice_posting_for_type($row['art'] ?? 'KO')->post($id, 'supplier');
}

?>
</tbody></table>
</td></tr>
</tbody></table>
</body></html>
