<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/itemId.php ---------- lap 3.7.7----2019.05.13-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190513 Move the handling of vare_id here

if ($vare_id) {
	$r=db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$varenr_ny=$r['varenr'];
} elseif (sizeof($_POST)>1) {
	$ny_bruger=if_isset($_POST['ny_bruger']);
	$kode=if_isset($_POST['kode']);
	countReturns($id, $kasse);
	if (isset($_SESSION['creditType'])) {
        countCorrection($id, $kasse);
	}
	$indbetal=if_isset($_POST['indbetal']);
	if ($indbetal || $afslut) {
		$qtxt="select kodenr from grupper where art = 'POSBUT' and box6='A'";
		if ($afd) $qtxt.=" and (box12='$afd' or box12='') order by box12 desc limit 1";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $sidemenu=$r['kodenr'];
	} else $indbetaling=if_isset($_POST['indbetaling']);
	$sum=if_isset($_POST['sum']);
	$afrundet=if_isset($_POST['afrundet']);
	$betaling=if_isset($_POST['betaling']);
	if (substr($betaling,0,9)=="Betalings" && !strpos($betaling,'på beløb')) $betaling='Betalingskort'; #20170914
	elseif (substr($betaling,0,8)=="Bet.kort") $betaling='Betalingskort på beløb';
	$betaling2=if_isset($_POST['betaling2']);
	$kontonr=if_isset($_POST['kontonr']);
	$modtaget=if_isset($_POST['modtaget']);
	$betvaluta=if_isset($_POST['betvaluta']);
	$betvalkurs=if_isset($_POST['betvalkurs']);
	$rest=if_isset($_POST['rest']); #20161010
	$kundeordnr=if_isset($_POST['kundeordnr']);
	$fokus=if_isset($_POST['fokus']);
	$varenr_ny=db_escape_string(trim(if_isset($_POST['varenr_ny'])));
	$lager_ny=if_isset($_POST['lager_ny']);
	if (count($lagernr)) {
		if ($lager_ny && !is_numeric($lager_ny)) {
			for ($l=0;$l<count($lagernr);$l++) {
				if (strtolower($lager_ny) == strtolower($lagernavn[$l])) $lager_ny=$lagernr[$l];
			}
		}
		if ($lager_ny && !is_numeric($lager_ny)) {
			print tekstboks ("Lager >$lager_ny< ikke fundet");
			$lager_ny=$afd_lager;
		}
		if (!$lager_ny) $lager_ny=$afd_lager;
		$lager_ny=$lager_ny*1;
	}
	if ($varenr_ny=='t') {
		$varenr_ny=NULL;
		$sidemenu=NULL;
	}
	else $afslut=if_isset($_POST['afslut']);

	$leveret=if_isset($_POST['leveret']);
	$antal_ny=strtolower(trim(if_isset($_POST['antal_ny'])));
 	if (if_isset($_POST['antal'])) { #20140623
		if (!$antal_ny && $antal_ny!='0') $antal_ny=$_POST['antal'];
		elseif ($antal_ny=='p' || $antal_ny=='r' || $antal_ny=='a') $antal_ny=$_POST['antal'].$antal_ny;
		if ($varenr_ny!='v') $fokus='antal_ny';
	}
 	$pris_ny=if_isset($_POST['pris_ny']);
 	if (!$pris_ny && if_isset($_POST['pris_old'])) {
		$pris_ny=$_POST['pris_old'];
	}
 	if (if_isset($_POST['pris'])|| $pris_ny) { #20140814 -> 20161013 tilføjet $pris_ny ellers fungerer den ikke med 0 pris?
		countPriceCorrectionSetup($pris_ny, $_POST['pris_old']);
		if (!$pris_ny && $pris_ny!='0') $pris_ny=$_POST['pris'];
		elseif ($pris_ny=='p' || $pris_ny=='r' || $pris_ny=='a') {
			$antal_ny.=$pris_ny;
 			$pris_ny=$_POST['pris'];
		} elseif (substr($pris_ny,-1)=='p' || substr($pris_ny,-1)=='r' || substr($pris_ny,-1)=='a') {
			$antal_ny.=substr($pris_ny,-1);
			$pris_ny=substr($pris_ny,0,strlen($pris_ny)-1);
		}
		if ($varenr_ny!='v') $fokus='antal_ny';
	}
	$beskrivelse_ny=db_escape_string(trim(if_isset($_POST['beskrivelse_ny'])));
	$momssats=(if_isset($_POST['momssats']));
	$rabat_ny=if_isset($_POST['rabat_ny']);
#xit;
	if (!$rabat_ny && $rabat_ny!='0' && if_isset($_POST['rabat_old'])) $rabat_ny=$_POST['rabat_old'];
	if (strpos($betaling,'på beløb')) {
		if (!$id) $id=opret_posordre(NULL,$kasse);
		$antal_ny=1;
		if ($id && $varenr_ny && strlen($varenr_ny)>1) {
			$qtxt="select id,salgspris,beskrivelse,samlevare from varer where varenr = '$varenr_ny' or stregkode='$varenr_ny'";
			if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or stregkode='0$varenr_ny'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['samlevare']) {
				opret_saet($id,$r['id'],$pris_ny,$momssats,$antal_ny,$lager_ny);
			} else $linje_id=opret_ordrelinje($id,$r['id'],$varenr_ny,1,'',usdecimal($pris_ny,2),0,100,'PO','','','0','on','','','','','','0',$lager_ny,__LINE__); #20140226
		}
		$varenr_ny=NULL;
		if ($kundedisplay) {
			kundedisplay('beskrivelse',$r['pris_ny']*$r['antal'],0);
		}
	}
	if (strtolower($antal_ny)=='a') {
		$antal_ny=1;
		$afslut=NULL;
	}

	$sum*=1;
	if ($kundeordnr && $id) db_modify("update ordrer set kundeordnr = '$kundeordnr' where id='$id'",__FILE__ . " linje " . __LINE__);

	if (strstr($pris_ny,",")) { #Skaerer orebelob ned til 2 cifre.
		list($kr,$ore)=explode(",",$pris_ny);
		$ore=substr($ore,0,2);
		$pris_ny=$kr.",".$ore;
	}
	if(isset($_POST['ny']) && $_POST['ny'] == "Ny kunde") {
	  print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php\">\n";
	  exit;
	}


	include("pos_ordre_includes/posTxtPrint/posPrintType.php");

	if(isset($_POST['skuffe'])) { #LN 20190218 Remove check of what the skuffe index equals, because we now have different languages
		aabn_skuffe($id,$kasse);
	}
	if(isset($_POST['krediter'])) {
		list($ny_id,$samlet_pris)=explode(";",krediter_pos($id)); #20170622-1
        print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id&samlet_pris=$samlet_pris\">\n"; #20170622-1
        $_SESSION['creditType'] = 'krediter';		# LN 20190206
    } elseif(isset($_POST['return'])) {		# LN 20190206
        list($ny_id,$samlet_pris)=explode(";",krediter_pos($id)); #20170622-1
        print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id&samlet_pris=$samlet_pris\">\n"; #20170622-1
        $_SESSION['creditType'] = 'return';
    } elseif(isset($_POST['Udskriv'])) {
        $_SESSION['creditType'] = 'printReceipt';
	}
	if ($fokus=="antal_ny" && $antal_ny!='0' && !$pris_ny) $antal_ny.="p";
	if ($fokus=="pris_ny" && $pris_ny!='f' && substr($pris_ny,-1)!='r') $fokus="antal_ny"; #20130310 tilføjet: "&& substr($pris_ny,-1)!='r'" samt 2 næste linjer
	if ($fokus=="pris_ny" && $pris_ny!='f' && substr($pris_ny,-1)=='r') {
		$pris_ny=str_replace("r","",$pris_ny);
		$fokus='rabat_ny';
	} elseif ($fokus=="rabat_ny" && $pris_ny!='f') $fokus="antal_ny";
	if ($fokus=="antal_ny" && (substr($antal_ny,-1)=='p' || substr($antal_ny,-1)=='r')) {
		if (substr($antal_ny,-1)=='p') $fokus='pris_ny';
		else $fokus='rabat_ny';
		if (strlen($antal_ny)>1) $antal_ny=substr($antal_ny,0,strlen($antal_ny)-1);
		else $antal_ny=1;
	} elseif ($fokus=="varenr_ny" && ($varenr_ny=='a' || $varenr_ny=='v' || strlen($varenr_ny)>1)) {
		if ($varenr_ny=='v') {
			vareopslag('PO',"",'varenr', $id,"","$ref","");
			} elseif (!$id && $varenr_ny=='a') { #20161014-3
			$varenr_ny=NULL;
		} elseif (strlen($varenr_ny)>1) {
			$qtxt="SELECT id,vare_id,variant_type FROM variant_varer WHERE variant_stregkode = '$varenr_ny'"; #20160220
			if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or variant_stregkode='0$varenr_ny'";
			if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$qtxt="select id from varer where varenr = '$varenr_ny' or lower(varenr) = '".strtolower($varenr_ny)."'";
				$qtxt.=" or lower(stregkode) = '".strtolower($varenr_ny)."'";
				if (strlen($varenr_ny)==12 && is_numeric($varenr_ny)) $qtxt.=" or stregkode='0$varenr_ny'";
				if(!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				vareopslag('PO',"",'beskrivelse', $id,"","$ref","*$varenr_ny*");
				}
			}
		}
	}
	if ($fokus=="pris_ny" && substr($pris_ny,-1)=='r') {
		$pris_ny=substr($pris_ny,0,strlen($pris_ny)-1);
		$fokus="rabat_ny";
	} elseif (isset($_POST['forfra']) && $id) {
		if(isset($_SESSION['creditType'])) {
			unset($_SESSION['creditType']);
		}
		hent_shop_ordrer('','');
		$r=db_fetch_array(db_select("select sum(amount) as amount from pos_betalinger where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
		if($r['amount']) { #20180704
			print "<table align='center' width='100%'><tbody>";
			print "<tr><td><br></td></tr>";
			print "<tr><td align='center'><big>Der er modtaget ". dkdecimal($r['amount']) ." på denne bestilling</big></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td align='center'><big>Bestillingen kan ikke nulstilles</big></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td align='center'><input type=\"button\" style=\"width:100px;\" onclick=\"window.location.href='pos_ordre.php?id=$id'\" value=\"OK\"></td></tr>\n";
			print "</tbody></table>";
			exit;
		} elseif ($_POST['sum']) {
			$price = $_POST['sum']*1;
			db_modify("insert into deleted_order (price, kasse, ordre_id) values ('$price', '$kasse', '$id')",__FILE__." linje ".__LINE__);
		}
		$r=db_fetch_array(db_select("select status from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$status=$r['status'];
		if ($status < 3) {
			$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$moms=explode(chr(9),$r['box7']);
			$x=$kasse-1;
			if ($moms[$x]){
				$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$moms[$x]'",__FILE__ . " linje " . __LINE__));
				$momssats=$r['box2'];
			} else $momssats='0';
			#$nr*=1;
			$dd=date("Y-m-d");
			$qtxt="update ordrer set konto_id='0', kontonr='',firmanavn='',addr1='',addr2='',postnr='',bynavn='',land='',betalingsdage='0',";
			$qtxt.="betalingsbet='Kontant',cvrnr='',ean='',institution='',email='',kontakt='',art='PO',valuta='DKK',valutakurs='100',";
			$qtxt.="kundeordnr='',ordredate='$dd',hvem='',momssats='$momssats',ref='' where id = '$id'";
			db_modify ($qtxt,__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			$varenr_ny=''; $antal_ny=''; $modtaget=''; $betaling=''; $indbetaling=''; $fokus="varenr_ny";
			$r=db_fetch_array(db_select("select id from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
		if ($kundedisplay) kundedisplay('','','1');
	} elseif (substr($modtaget,-1)=='t' || substr($modtaget2,-1)=='t') $betaling="";
#	elseif (substr($modtaget,-1)=='d' && !$betaling) $betaling="creditcard";
	elseif (substr($modtaget,-1)=='c' && !$betaling) $betaling="kontant";
	elseif (substr($modtaget,-1)=='g' && !$betaling) $betaling="gavekort";
	elseif (substr($modtaget,-1)=='k' || $betaling == "konto") {
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1),2);
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=substr($modtaget,0,strlen($modtaget)-1);
		if (!$modtaget || !$kontonr) pos_kontoopslag('PO',"",$fokus, $id,"","","");
	} elseif (isset($_POST['debitoropslag']) || isset($_POST['kreditoropslag'])) {
		(isset($_POST['debitoropslag']))?$tmp='PO':$tmp='KO';
		kontoopslag($tmp,"","varenr_ny",$id,"","","","","","","");
	} elseif (isset($_POST['stamkunder']) || isset($_GET['stamkunder'])) {
		stamkunder('PO',"","varenr_ny",$id,"","","","","","","",$sum);
	} elseif (isset($_POST['kontoudtog'])) {
		kontoudtog($id,$konto_id);
	} elseif (isset($_POST['gavekortsalg'])) {
		gavekortsalg($id,$konto_id);
	} elseif (isset($_POST['gavekortstatus'])) {
		gavekortstatus($id,$konto_id);
	}

    include("pos_ordre_includes/deposit.php"); #20190513

	$modtaget*=1;
	$betalt=$modtaget+$modtaget2;
	if ($betaling=='Konto' && $sum && !$modtaget*1) $modtaget=$sum;

	if ($delbetaling) {
		if ($betaling=='Kontant') $modtaget=pos_afrund($modtaget,$difkto,'');
	}
	if (($betalt || ($afslut=='on') && is_numeric($betalt))||(!$sum && ($afslut || $betaling))) { #20150522 + 20161014 20161017
		if (!$indbetaling && !$sum && $afslut=="Afslut" && !$betaling){
			$betaling="ukendt";
		}
		$afslut="OK";
		if (!is_numeric($sum)) $afslut=NULL;
		if (!$sum && !$betaling) $afslut=NULL;
		if (!$betaling)  $afslut=NULL;
		if (strpos($betaling,'på beløb')) $afslut=NULL;
		if ($betaling=="ukendt") $afslut=NULL;
		if ($betaling2 && $betaling2=="ukendt") $afslut=NULL;
		if ($modtaget2 && (!$betaling2 || $betaling2=="ukendt")) $afslut=NULL;
		if ($indbetaling && !$modtaget) $afslut=NULL;
	if ($afslut=="OK") {
			 $svar=afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,NULL,NULL);
			if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
 			else {
			  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";
			}
		} elseif ($delbetaling && $betaling!='ukendt') {
			 $svar=delbetal($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling,NULL,NULL);
			if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
 			else {
			  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">\n";
			}
		}
	} else {
		$tmp=str_replace(",",".",$antal_ny);
		if ($varenr_ny == "a") {
			$betaling="ukendt";
			$varenr_ny=NULL;
		} elseif ($antal_ny == "a") {
			$betaling="ukendt";
			$antal_ny=1;
		} elseif ($antal_ny && !is_numeric($tmp) || $tmp>99999) { # Så er der skannet et varenummer ind som antal
				$next_varenr=$antal_ny;
				$antal_ny=1;
		} elseif ($fokus=="antal_ny") {
			if ($antal_ny=="0") $varenr_ny = NULL;
			elseif (!strlen($antal_ny)) $antal_ny=1;
			else $antal_ny=usdecimal($antal_ny,2);
		} elseif ($antal_ny=="0" && if_isset($_POST['antal'])) $varenr_ny = NULL; #20140623
 		if ($varenr_ny && $antal_ny && $fokus!="pris_ny" && $fokus!="rabat_ny") {
			if (!$id) {
				$id=opret_posordre(NULL,$kasse);
			}
			if ($id && !is_numeric($id)) {
				alert("$id");
			} else {
				if (strlen($rabat_ny)>1 && substr($rabat_ny,-1)=='*') { #20140828-2
					$rabat_ny*=1;
					db_modify("update ordrelinjer set rabat='$rabat_ny' where ordre_id='$id' and vare_id >'0' and rabat=0",__FILE__ . " linje " . __LINE__);
				}
				$r=db_fetch_array(db_select("select id,samlevare from varer where varenr = '$varenr_ny'",__FILE__ . " linje " . __LINE__));
				if ($r['samlevare']) opret_saet($id,$r['id'],usdecimal($pris_ny,2),$momssats,$antal_ny,'on',$lager_ny);
				else $svar=opret_ordrelinje($id,'',$varenr_ny,$antal_ny,'',usdecimal($pris_ny,2),usdecimal($rabat_ny,2),100,'PO','','','0','on','','','','','','0',$lager_ny,__LINE__); #20140226 + 20140814
				if (usdecimal($pris_ny,2) == 0.00) $obstxt="Obs, vare $varenr_ny sælges til kr 0,00";
				if ($svar && !is_numeric($svar)) {
					print "<BODY onLoad=\"javascript:alert('$svar')\">\n";
					$fokus="pris_ny";
				} else {
					$r=db_fetch_array(db_select("select max(id) as linje_id from ordrelinjer where ordre_id = '$id' and varenr='$varenr_ny'",__FILE__ . " linje " . __LINE__));
					if ($r['linje_id'] && isset($leveret[0]) && is_numeric($leveret[0])) db_modify("update ordrelinjer set leveret='$leveret[0]' where id='$r[linje_id]'",__FILE__ . " linje " . __LINE__);
					$varenr_ny=$next_varenr;
					$tmp=$antal_ny; #Til kundedisplay
					$antal_ny=NULL;
				}
 				if ($kundedisplay) {
 					kundedisplay($beskrivelse_ny,usdecimal($pris_ny,2)*$tmp,0);
				}
			}
		} elseif ($varenr_ny) $sum=find_pris($varenr_ny);
	}
}



?>
