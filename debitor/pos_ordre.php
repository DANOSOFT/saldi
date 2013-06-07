<?php
// ------------- debitor/pos_ordre.php ---------- lap 3.2.3----2013.05.07-------
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
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2013 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 2013-03-10. Tilføjet mulighed for at give rabat på varer uden pris ved at skrive "r" efter prisen. Søg 20130310
// 2013.05.07 - Tilføjet visning af kostpris v. mus over pris.

@session_start();
$s_id=session_id();
ob_start();

$modulnr=5;
$title="POS_ordre";
$css="../css/pos.css";
$betaling=NULL; $betaling2=NULL; $konto_id=NULL; $next_varenr=NULL;
$fokus="varenr_ny";
$valuta='DKK';$valutakurs='100';


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");


$projekt=NULL;
$tid=date("H:i");
$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 < box8) and (box7<'$tid' and box8>'$tid')",__FILE__ . " linje " . __LINE__));
$projekt=$r['box9'];
if (!$projekt) {
	$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 > box8) and ((box7>'$tid' and box8>'$tid') or (box7<'$tid' and box8<'$tid'))",__FILE__ . " linje " . __LINE__));
	$projekt=$r['box9'];
}

$returside=(if_isset($_GET['returside']));
if (!$returside) {
	if ($popup) $returside="../includes/luk.php";
	else $returside="../index/menu.php";
}
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$kasse = if_isset($_GET['kasse']);
$menu_id = if_isset($_GET['menu_id']);
$kassebeholdning = if_isset($_GET['kassebeholdning']);
if ($kasse && $kassebeholdning) kassebeholdning($kasse);
if (!$kasse || $kasse == "?") $kasse=find_kasse($kasse);
elseif ($kasse=="opdat") {
	$kasse=$_POST['kasse'];
	setcookie("saldi_pos",$kasse,time()+60*60*24*30);
}
ob_end_flush();
$bon = trim(strtoupper(if_isset($_POST['bon'])));
if ($bon=='S') {
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where felt_5='$kasse'",__FILE__ . " linje " . __LINE__));
	if ($id=$r['id'])	$r=db_fetch_array(db_select("select fakturanr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$bon=trim($r['fakturanr']);
} elseif ($bon) {
	$r=db_fetch_array(db_select("select id from ordrer where fakturanr = '$bon'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
} else $id = if_isset($_GET['id'])*1;
$vare_id = if_isset($_GET['vare_id'])*1;
$vare_id_ny = if_isset($_GET['vare_id_ny'])*1;
if ($vare_id_ny && !$vare_id) {
	$vare_id=$vare_id_ny;
} elseif ($vare_id_ny && $vare_id) {
	if (!$id) $id=opret_ordre(NULL,$kasse);
	$r=db_fetch_array(db_select("select varenr, salgspris from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
#cho "A opret_ordrelinje($id,$r[varenr],1,'',$r[pris_ny],0,'PO','','','0','on','','','0')<br>";
	$linje_id=opret_ordrelinje($id,$r['varenr'],1,'',$r['pris_ny'],0,'PO','','','0','on','','','0');
	$vare_id=$vare_id_ny;
}
$funktion = if_isset($_GET['funktion']);
if ($funktion) {
	$sort = if_isset($_GET['sort'])*1;
	$funktion ('PO',$sort,$fokus, $id,"","","");
}
$spec_func = if_isset($_GET['spec_func']);
if ($spec_func) {
	$kode = if_isset($_POST['kode']);
	include("../includes/spec_func.php");
	$svar=$spec_func('xx',$id,$kode);
	if (!is_numeric($svar)) {
		print "<BODY onLoad=\"javascript:alert('$svar')\">";
	}	else $konto_id=$svar;
}
$kontonr = if_isset($_POST['kontonr'])*1;
if (!$konto_id) $konto_id = if_isset($_GET['konto_id'])*1;
if ($konto_id || $kontonr) {
	$id=opdater_konto($konto_id,$kontonr,$id);
	$r=db_fetch_array(db_select("select momssats,sum,betalt,betalingsbet from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$betalingsbet=$r['betalingsbet'];
	$momssats=$r['momssats']*1;
	if ($betalingsbet!='Kontant') $modtaget=$r['betalt']*1;
	$sum=$r['sum']*1;
	$betaling='ukendt';
#	if ($modtaget <= $sum) $id=afslut($id,'konto',$modtaget);
#	else $betaling='ukendt';
}

if ($vare_id) {
	$r=db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$varenr_ny=$r['varenr'];
} elseif (sizeof($_POST)>1) {
	$afslut=if_isset($_POST['afslut']);
	$ny_bruger=if_isset($_POST['ny_bruger']);
	$kode=if_isset($_POST['kode']);
	$indbetaling=if_isset($_POST['indbetaling']);
	$sum=if_isset($_POST['sum']);
	$betaling=if_isset($_POST['betaling']);
	$betaling2=if_isset($_POST['betaling2']);
	$kontonr=if_isset($_POST['kontonr']);
	$modtaget=if_isset($_POST['modtaget']);
	$modtaget2=if_isset($_POST['modtaget2']);
	$kundeordnr=if_isset($_POST['kundeordnr']);
	$fokus=if_isset($_POST['fokus']);
 	$pris_ny=if_isset($_POST['pris_ny']);
 	$antal_ny=strtolower(trim(if_isset($_POST['antal_ny'])));
	$varenr_ny=db_escape_string(trim(if_isset($_POST['varenr_ny'])));
	$momssats=(if_isset($_POST['momssats']));
	$rabat_ny=if_isset($_POST['rabat_ny']);

	if (substr($betaling,0,9) == "Kontant p") {
		$antal_ny=1;
		if ($id && $varenr_ny) {
			$r=db_fetch_array(db_select("select salgspris from varer where varenr = '$varenr_ny'",__FILE__ . " linje " . __LINE__));
#cho "B opret_ordrelinje($id,$varenr_ny,1,'',$r[pris_ny],0,'PO','','','0','on','','','0')<br>";
			$linje_id=opret_ordrelinje($id,$varenr_ny,1,'',$r[pris_ny],0,'PO','','','0','on','','','0');
			$varenr_ny=NULL;
		}
	}
	if (strtolower($antal_ny)=='a') {
		$antal_ny=1;
		$afslut=NULL;
	}
	$sum*=1;
	#cho "update ordrer set kundeordnr = '$kundeordnr',sum='$sum', betalt='$betalt',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse' where id='$id'<br>";
	if ($kundeordnr && $id) db_modify("update ordrer set kundeordnr = '$kundeordnr' where id='$id'",__FILE__ . " linje " . __LINE__);

#cho "betalt=$betalt fok $fokus<br>";
	if (strstr($pris_ny,",")) { #Skaerer orebelob ned til 2 cifre.
		list($kr,$ore)=explode(",",$pris_ny);
		$ore=substr($ore,0,2);
		$pris_ny=$kr.",".$ore;
	}
	if(isset($_POST['ny']) && $_POST['ny'] == "Ny kunde") {
		$id=0;
		$kontonr=0;
		$menu_id=NULL;
		$bon=NULL;
	}
	if(isset($_POST['udskriv']) && $_POST['udskriv'] == "Udskriv") {
		$momssats=$momssats*1;
#		include("../includes/formfunk.php");
		pos_txt_print($id,$betaling,$modtaget,$indbetaling);
	}
	if(isset($_POST['krediter'])) {
		$ny_id=krediter_pos($id);
	  print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$ny_id\">";
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
	} elseif ($fokus=="varenr_ny" && $varenr_ny=='v') {
		vareopslag('PO',"",$fokus, $id,"","","");
	} elseif ($fokus=="pris_ny" && substr($pris_ny,-1)=='r') {
		$pris_ny=substr($pris_ny,0,strlen($pris_ny)-1);
		$fokus="rabat_ny";
	} elseif (isset($_POST['forfra']) && $id) {
		$r=db_fetch_array(db_select("select status from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		if ($r['status']<3) {
			db_modify ("update ordrer set konto_id='0', kontonr='',firmanavn='',addr1='',addr2='',postnr='',bynavn='',land='',betalingsdage='0',betalingsbet='Kontant',
				cvrnr='',ean='',institution='',email='',kontakt='',art='PO',valuta='DKK',valutakurs='100',kundeordnr='' where id = '$id'",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__);
			$varenr_ny=''; $antal_ny=''; $modtaget=''; $betaling=''; $indbetaling=''; $fokus="varenr_ny";
		}
	} elseif (substr($modtaget,-1)=='t') $betaling="";
#	elseif (substr($modtaget,-1)=='d' && !$betaling) $betaling="creditcard";
	elseif (substr($modtaget,-1)=='c' && !$betaling) $betaling="kontant";
	elseif (substr($modtaget,-1)=='k' || $betaling == "konto") {
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1));
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=substr($modtaget,0,strlen($modtaget)-1);
		if (!$modtaget || !$kontonr) kontoopslag('PO',"",$fokus, $id,"","","");
	} elseif (isset($_POST['kontoopslag'])) {
		kontoopslag('PO',"","varenr_ny",$id,"","","","","","","");
	}
	if ($indbetaling) {
			$indbetaling=str_replace("a","",$indbetaling);
			$tmp=trim(str_replace(".","",$indbetaling));
			$tmp=str_replace(",",".",$tmp);
#cho "$tmp $indbetaling $modtaget<br>"; 
			if (is_numeric($tmp)) {
				$indbetaling=usdecimal($indbetaling);
				$modtaget=usdecimal($modtaget);
			if ($indbetaling>$modtaget) {
				print "<BODY onLoad=\"javascript:alert('Indbetaling kan ikke v&aelig;re større end beløbet der modtages')\">";
				$indbetaling=$modtaget;
			}
		}
#cho "$tmp $indbetaling $modtaget<br>"; 
#exit;
	}	elseif ($betaling) {
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1));
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=usdecimal(substr($modtaget,0,strlen($modtaget)-1));
		else $modtaget=usdecimal($modtaget);
		$modtaget=$modtaget*1;
		if (!$modtaget) $modtaget=$sum;
		if (substr($modtaget2,0,1)=='+') $modtaget2=$sum+usdecimal(substr($modtaget2,1,strlen($modtaget2)-1));
		elseif (!is_numeric(substr($modtaget2,-1))) $modtaget2=usdecimal(substr($modtaget2,0,strlen($modtaget2)-1));
		else $modtaget2=usdecimal($modtaget2);
		$modtaget2=$modtaget2*1;
#		if (!$modtaget2) $modtaget2=$sum;
	}
	$betalt=$modtaget+$modtaget2;
	if (($betalt && is_numeric($betalt))||(!$sum && $afslut=="Afslut")) {
		if (!$sum && $afslut=="Afslut"){
			$betaling="kontant";
		}
		$afslut="OK";
		if (!is_numeric($sum)) $afslut=NULL;
		if ($betalt < $sum && !$indbetaling) $afslut=NULL;
		if (!$betaling)  $afslut=NULL;
		if (substr($betaling,0,9)=="Kontant p") $afslut=NULL;
		if ($betaling=="ukendt") $afslut=NULL;
		if ($betaling2 && $betaling2=="ukendt") $afslut=NULL;
		if ($modtaget2 && (!$betaling2 || $betaling2=="ukendt")) $afslut=NULL;
		if ($afslut=="OK") {
			 $svar=afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling);
			if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">";
 			else {
			  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">";
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
		} elseif ($antal_ny && !is_numeric($tmp) || $tmp>99999) { # Sï¿œ er der skannet et varenummer ind som antal
				$next_varenr=$antal_ny;
				$antal_ny=1;
		} elseif ($fokus=="antal_ny") {
			if ($antal_ny=="0") $varenr_ny = NULL;
			elseif (!strlen($antal_ny)) $antal_ny=1;
			else $antal_ny=usdecimal($antal_ny);
		}
 		if ($varenr_ny && $antal_ny && $fokus!="pris_ny" && $fokus!="rabat_ny") {
			if (!$id) $id=opret_ordre(NULL,$kasse);
			if ($id && !is_numeric($id)) {
				print "<BODY onLoad=\"javascript:alert('$id')\">";
			} else {
#cho "264  $id,$varenr_ny,$antal_ny,'',usdecimal($pris_ny),$rabat_ny<br>";
 				$linje_id=opret_ordrelinje($id,$varenr_ny,$antal_ny,'',usdecimal($pris_ny),$rabat_ny,'PO','','','0','on','','','0');
				if ($linje_id && !is_numeric($linje_id)) {
					print "<BODY onLoad=\"javascript:alert('$linje_id')\">";
					$fokus="pris_ny";
				} else {
					$varenr_ny=$next_varenr;
					$antal_ny=NULL;
		#			$sum=0;
				}
			}
		} elseif ($varenr_ny) $sum=find_pris($varenr_ny);
#		else $sum=0;
	}
}

############################
$x=0;
if (!$id) {
	$dd=date("Y-m-d");
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where status < '3' and art = 'PO' and ref = '$brugernavn' and ordredate = '$dd'",__FILE__ . " linje " . __LINE__));
	$id=$r['id']*1;
}
if ($ny_bruger && $ny_bruger!=$brugernavn) skift_bruger($ny_bruger,$kode);
if (!isset($momssats)) $momssats=find_momssats($id,$kasse);
print "<table width=\"100%\" height=\"100%\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\"><tbody>\n";
print "<tr><td valign=\"top\" width=50%><table width=\"100%\"><tbody>\n";
print "<form name=pos_ordre action=pos_ordre.php?id=$id&menu_id=$menu_id method=post>\n";
if ($id && $betaling) $sum=betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2);
elseif (!$indbetaling) list($varenr_ny,$pris_ny,$status)=explode(chr(9),varescan($id,$momssats,$varenr_ny,$antal_ny,$pris_ny));
else indbetaling($id,$indbetaling,$modtaget);
if (substr($betaling,0,9) == "Kontant p") {
	$betaling='Kontant';
	$modtaget=$sum;
	$svar=afslut($id,$betaling,NULL,$modtaget,0,NULL);
	if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">";
	else print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">";
}
if ($varenr_ny=='fejl') fejl($id,"$status");
if (!$id) print "<tr><td height=\"50%\" colspan=\"4\" align=\"center\" valign=\"middle\"><b><div style=\"font-size:100mm;color:$bgcolor2;\">$kasse</div></b></td></tr>";
print "</tbody></table></td>\n";
print "<td valign=\"top\"><table width=\"100%\" border=\"0\"><tbody><td align=\"center\">\n";
print "<tr><td colspan=\"2\" valign=\"top\" height=\"1%\"><table width=\"100%\" border=\"0\"><tbody>\n";
hoved($kasse);
# kassebeholdning($kasse);
print "</tbody></table></td></tr>\n";
#print "</FORM><form name=tastatur action=pos_ordre.php?id=$id method=post>\n";
tastatur($status);
print "</FORM>";
print "</tbody></table></td></tr>\n";
print "<tr><td colspan=\"2\" valign=\"top\" height=\"1%\" align=\"center\"><table width=\"100%\" border=\"0\"><tbody>\n";
if ($status<3) menubuttons($id,$menu_id,$vare_id);
print "</td></tbody></table></td></tr>\n";
print "</td></tbody></table></td></tr>\n";
#print "<tr><td colspan=2 width=\"100%\" height=\"1%\"><table width=\"100%\" height=\"100%\" border=\"0\"><tbody>\n";
#footer ($kasse);
#print "</tbody></table></td></tr>\n";
# print "</tbody></table></td>\n";


function afslut ($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling) {
# "afslut($id,$betaling,$modtaget,$indbetaling)";
#exit;

	global $charset;
	global $bruger_id;
	global $kasse;
	global $regnaar;
	
#	include("../includes/formfunk.php");
	$projekt=NULL;
	$tid=date("H:i");
	$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 < box8) and (box7<'$tid' and box8>'$tid')",__FILE__ . " linje " . __LINE__));
	$projekt=$r['box9'];
	if (!$projekt) {
		$r=db_fetch_array(db_select("select box9 from grupper where art='POSBUT' and (box7 > box8) and ((box7>'$tid' and box8>'$tid') or (box7<'$tid' and box8<'$tid'))",__FILE__ . " linje " . __LINE__));
		$projekt=$r['box9'];
	}
	$hurtigfakt='on';
	$moms=0;
	$dd=date("Y-m-d");
	$r=db_fetch_array(db_select("select konto_id,status,fakturanr,momssats,betalingsbet from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats'];
	$status=$r['status'];
	$konto_id=$r['konto_id'];
	$betalingsbet=$r['betalingsbet'];
	$x=0;

	if ($status<3) {
		$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
		$rabatvareid=$r['box8'];
		$q=db_select("select * from ordrelinjer where ordre_id = '$id' order by rabatgruppe, id desc ",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$x++;
			$linje_id[$x]=$r['id'];
			$pris[$x]=$r['pris'];
			$antal[$x]=$r['antal'];
			$momsfri[$x]=$r['momsfri'];
			$varemomssats[$x]=$r['momssats'];
			$folgevare[$x]=$r['folgevare'];
			$rabat[$x]=$r['rabat'];
			$rabatart[$x]=$r['rabatart'];
			$rabatgruppe[$x]=$r['rabatgruppe'];
			if ($rabatgruppe[$x]) {
				if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
					$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
					$rabatantal[$x-1]=0;
				} else $rabatantal[$x]=$antal[$x];
			} else $rabatantal[$x]=0;
			$m_rabat[$x]=$r['m_rabat']*-1;
		}
		$linjeantal=$x;
		$pos=0;

		for($x=1;$x<=$linjeantal;$x++) {
			$pos++;
			db_modify("update ordrelinjer set posnr='$pos',projekt='$projekt' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			if ($rabatart[$x]=='amount') {
				$tmp=afrund(($pris[$x]-$rabat[$x])*$antal[$x],2);
			} else $tmp=afrund($pris[$x]*$antal[$x]-($pris[$x]*$antal[$x]/100*$rabat[$x]),2);
			$sum+=$tmp;
			if (!$momsfri[$x]) $moms+=$tmp/100*$varemomssats[$x];
########################################################################
			if ($folgevare[$x]) {
				$pos++;
				$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__));
				$r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				$f_bogfkto=$r2['box4'];
				$f_momsfri=$r2['box7'];
				if (!$f_momsfri){
					$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
					$kodenr=substr($r['moms'],1);
					$r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
					$f_momssats=$r2['box2']*1;
				}
#cho "insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momssats,momsfri,posnr,projekt) values ('$id','$folgevare[$x]', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$r[salgspris]','0','$f_momssats','$f_momsfri','$pos','0')<br>";
				db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momssats,momsfri,posnr,projekt) values ('$id','$folgevare[$x]', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$r[salgspris]','0','$f_momssats','$f_momsfri','$pos','$projekt')",__FILE__ . " linje " . __LINE__);
#				print "<tr><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$r[beskrivelse]</td><td align=\"right\">".dkdecimal($r['salgspris'])."</td><td align=\"right\">".dkdecimal($antal[$x]*$r['salgspris'])."</td>";
				$sum+=afrund($antal[$x]*$r['salgspris'],2);
			}
			if ($rabatantal[$x]) {
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
					if ($grupperabat) {
						$pos++;
						$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
						$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
						$momsfri = $r2['box7'];
						db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$rabatvareid', '$r[varenr]', '$r[beskrivelse]', '$rabatantal[$x]','0','$grupperabat','0','$r[momsfri]','$pos','$projekt')",__FILE__ . " linje " . __LINE__);
						$sum+=afrund($grupperabat*$rabatantal[$x],2);
					}
				} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
					$pos++;
					if ($rabatvareid && $r=db_fetch_array(db_select("select id,varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))) {
						$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
						$momsfri = $r2['box7'];
						db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$rabatvareid', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$m_rabat[$x]','0','$r[momsfri]','$pos','$projekt')",__FILE__ . " linje " . __LINE__);
						$sum+=afrund($m_rabat[$x]*$antal[$x],2);
					} else print "<BODY onLoad=\"javascript:alert('Manglende varenr for rabat')\">";
				}
#			}
		}

		$fakturanr=1;
		$q=db_select("select fakturanr from ordrer where art = 'PO'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
			$tmp=$r['fakturanr']*1;
			if ($tmp>$fakturanr) $fakturanr=$tmp;
		}
		$fakturanr++;
		$sum*=1; $moms*=1;
		$betalt=$modtaget+$modtaget2;
		
		if ($konto_id && ($betalingsbet!='Kontant' || $indbetaling)) {
			$saldo=0;
			$q=db_select("select * from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$saldo=$saldo+$r['amount'];
			}
			$betaling2=$saldo;
			if ($indbetaling) {
			$modtaget2=$saldo-$indbetaling;
			$sum=$indbetaling;
			$moms='0';
		} else $modtaget2=$saldo+$sum;
	}
	if ($betaling=='Kontant' && !$betaling2) { 
#cho "select box2 from grupper where art='OreDif'<br>";
		$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
		if ($difkto=$r['box2']){
#cho "D $difkto<br>";
			$afrundet=pos_afrund($sum);
#cho "E $afrundet<br>";
			if ($modtaget==$sum) {
				$modtaget=$afrundet;
#				$sum=$afrundet;
			}
		}
	}
#cho "B $betaling - $modtaget - $sum - $afrundet<br>";
#xit;

$modtaget=afrund($modtaget,2);
$modtaget2=afrund($modtaget2,2);

$tidspkt=date("Y-m-d H:i");
#cho "update ordrer set levdate = '$dd',fakturadate = '$dd',fakturanr = '$fakturanr',sum='$sum', moms='$moms', betalt='$betalt',status='2',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse',tidspkt='$tidspkt',projekt='$projekt' where id='$id'<br>"; 
db_modify("update ordrer set levdate = '$dd',fakturadate = '$dd',fakturanr = '$fakturanr',sum='$sum', moms='$moms', betalt='$betalt',status='2',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse',tidspkt='$tidspkt',projekt='$projekt' where id='$id'",__FILE__ . " linje " . __LINE__);
		if (!$indbetaling) {
			$svar=levering($id,'on','','');
#cho "Svar1 $svar<br>";
				if ($svar != 'OK') return ($svar);
			$svar=bogfor($id,'');
#cho "Svar2 $svar<br>";
			if ($svar != 'OK') return ($svar);
		} else {
			$svar=bogfor_indbetaling($id,'');
			if ($svar != 'OK') return ($svar);
		}

	}
	if (db_fetch_array(db_select("select id from grupper where art = 'POS' and kodenr = '1' and box10='on'",__FILE__ . " linje " . __LINE__))) {
		pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling);
	} else {
		$pfnavn="../temp/".$db."/".$bruger_id.".txt";
		$fp=fopen("$pfnavn","w");
		fclose($fp);
		$tmp="/temp/".$db."/".$bruger_id.".txt";
		$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
		$url=str_replace("/debitor/pos_ordre.php","",$url);
		if ($_SERVER[HTTPS]) $url="s".$url;
		$url="http".$url;
		print "<BODY onLoad=\"JavaScript:window.open('http://localhost/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=1' , '' , '$jsvars');\">";
	}
	return(NULL);
}

function betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2) {
	global $fokus;
	global $kontonr;

	$fokus="modtaget";
	if ($id) {
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id']*1;
		$kontonr=$r['kontonr'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$post_by=$r['postnr']." ".$r['bynavn'];
		$kundeordnr=$r['kundeordnr'];
		$status=$r['status'];
		$betalingsbet=$r['betalingsbet'];
		if ($r['lukket']) $betalingsbet='Kontant';
		$ref=$r['ref'];
		if ($konto_id) {
			print "<tr><td><b>$kontonr</b>";
			if ($kundeordnr) print "&nbsp;&nbsp;&nbsp; Rekv.nr: $kundeordnr";
			print "</td></tr>\n";
			print "<tr><td colspan=\"2\"><b>D $firmanavn</b></td></tr>\n";
			if ($betalingsbet=='Kontant')print "<tr><td colspan=\"2\"><b>Ingen kredit</b></td>"; 
		}
		print "<tr><td width=\"50%\"><table width=\"100%\"><tbody>";
		print "<tr><td>Varenummer</td><td align=\"right\">Antal</td><td>Varenavn</td><td align=\"right\">Pris</td><td align=\"right\">Sum</td></tr>\n";
		print "<tr><td colspan=\"5\"><hr></td></tr>\n";
		$sum=vis_pos_linjer($id,$momssats,$status);
		if ($kontonr && $betalingsbet!='Kontant') $modtaget=$sum;
		if ($modtaget) $retur=$modtaget-$sum;
	}
	print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">";
	print "<input type=\"hidden\" name = \"betaling\" value=\"$betaling\">";
	print "<input type=\"hidden\" name = \"sum\" value=\"$sum\">";
	if ($modtaget) $tmp=dkdecimal($modtaget);
	else $tmp="";
#cho "$kontonr && $betalingsbet!='Kontant'<br>";
#exit;
	if ($kontonr && $betalingsbet!='Kontant') print "<input type=\"hidden\" name=\"modtaget\" value=\"$tmp\">";
	elseif(substr($betaling,0,9)!='Kontant p') {
		print "<tr><td>$betaling</td><td colspan= \"4\" align=right><input class=\"inputbox\" type=\"text\" size=\"15\" style=\"text-align:right\" name = \"modtaget\" value=\"$tmp\"></td></tr>\n";
		if ($betaling != "ukendt" && ($retur<0 || $modtaget2)) {$color="color: rgb(255, 0, 0);";
			if ($modtaget2) $tmp=dkdecimal($modtaget2);
			else $tmp="";
			if (!$betaling2) $betaling2="ukendt";
			$fokus="modtaget2";
			$retur=$retur+$modtaget2;
			print "<tr><td>$betaling2</td><td colspan= \"4\" align=right><input class=\"inputbox\" type=\"text\" size=\"15\" style=\"text-align:right\" name = \"modtaget2\" value=\"$tmp\"></td></tr>\n";
		} else $color="color: rgb(0, 0, 0);";
		print "<tr><td>Retur</td><td colspan= \"4\" align=right><span style=\"$color\">".dkdecimal($retur)."</span></td></tr>\n";
	}
	print "<td colspan=\"5\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	print "</tbody></table>";
#cho "SUM $sum<br>";
	return($sum);
}

function skift_bruger($ny_bruger,$kode) {
	global $brugernavn;
	global $s_id;
	global $db;

	$kode=md5($kode);
	if ($r=db_fetch_array(db_select("select id from brugere where brugernavn ='$ny_bruger' and kode = '$kode'",__FILE__ . " linje " . __LINE__))) {
		include("../includes/connect.php");
		db_modify("update online set brugernavn='$ny_bruger' where session_id='$s_id' and db = '$db'",__FILE__ . " linje " . __LINE__);
		$brugernavn=$ny_bruger;
		print "<input type=\"hidden\" name=\"brugernavn\" value=\"$brugernavn\">";
		include("../includes/online.php");
	} else print "<BODY onLoad=\"javascript:alert('Forkert adgangskode')\">";
}

function varescan ($id,$momssats,$varenr_ny,$antal_ny,$pris_ny) {
	global $fokus;
	global $kontonr;
	global $sum;

	if ($id) {
		$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id'];
		$kontonr=$r['kontonr'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$post_by=$r['postnr']." ".$r['bynavn'];
		$status=$r['status'];
		$kundeordnr=$r['kundeordnr'];
		$betalingsbet=$r['betalingsbet'];
		if (!$r['firmanavn']) $betalingsbet='Kontant';
		if ($status >= 3) {
			$fakturanr=$r['fakturanr'];
			$kasse=$r['felt_5'];
			$fakturadato=dkdato(substr($r['fakturadate'],0,10));
			$tidspkt=substr($r['tidspkt'],-5);
			if (!$tidspkt) {
				$r2=db_fetch_array(db_select("select logtime from transaktioner where ordre_id = '$id'",__FILE__ . " linje " . __LINE__));
				$tidspkt=substr($r2['logtime'],0,5);
				$tmp=$r['fakturadate']." ".$tidspkt;
#cho "update ordrer set tidspkt = '$tmp' where id = '$id'<br>";
				db_modify("update ordrer set tidspkt = '$tmp' where id = '$id'",__FILE__ . " linje " . __LINE__);
			}
		}
		if ($status >= 3) {
			$betaling=$r['felt_1'];
			$modtaget=$r['felt_2'];
			$betaling2=$r['felt_3'];
			$modtaget2=$r['felt_4'];
		} else {
			$fakturanr=NULL;
			$fakturadato=NULL;
			$kasse=NULL;
			$tidspkt=NULL;
		}
		($r['ref'])?$ref=$r['ref']:$ref=$brugernavn;

		if ($ref) {
			if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
				$ansat_id=$r['ansat_id']*1;
				if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
			}
		}
	}
	if ($kontonr && $betalingsbet!='Kontant') {
		$r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$kreditmax=$r['kreditmax'];
		$r=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$saldo=$r['saldo'];
	}

	if ($varenr_ny) {
		$varenr_ny=db_escape_string($varenr_ny);
		$varenr_low=strtolower($varenr_ny);
		$varenr_up=strtoupper($varenr_ny);
#cho "SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'<br>";
		if ($r=db_fetch_array(db_select("SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr_up'",__FILE__ . " linje " . __LINE__))) {
			$vare_id=$r['vare_id'];
			$variant_type=$r['variant_type'];
			$variant_id=$r['id'];
		} else {
			$variant_id=0;
			$variant_type='';
		}
		if ($vare_id) $string="select * from varer where id='$vare_id'";
		else $string="select * from varer where lower(varenr) = '$varenr_low' or upper(varenr) = '$varenr_up' or varenr LIKE '$varenr_ny' or lower(stregkode) = '$varenr_low' or upper(stregkode) = '$varenr_up' or stregkode LIKE '$varenr_ny'";
#cho "streng ".$string."<br>";
		if ($r=db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
#		  $varenr_ny=db_escape_string($r['varenr']);
		  $beskrivelse[0]=$r['beskrivelse'];
		  $kostpris[0]=$r['kostpris'];
			$pris[0]=find_pris($r['varenr'])*1;
		  if ($pris[0]) $pris[0]=dkdecimal($pris[0]);
			else $pris[0]="";

			if ($fokus!="pris_ny" && $fokus!="rabat_ny") $fokus="antal_ny";
		} else return ("fejl".chr(9)."".chr(9)."Varenr: $varenr_ny eksisterer ikke");
		if ($variant_type) {
			$varianter=explode(chr(9),$variant_type);
			for ($y=0;$y<count($varianter);$y++) {
				$r1=db_fetch_array(db_select("select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id",__FILE__ . " linje " . __LINE__));
				$beskrivelse[0].=", ".$r1['var_besk'].":".$r1['vt_besk'];
			}
		}
	} else $fokus="varenr_ny";
	if ($kontonr) {
		print "<tr><td><b>$kontonr</b></td><td colspan=\"2\">";
		if ($status<3) print "Rekv.nr:&nbsp; <input type=\"text\" size=\"15\" name=\"kundeordnr\" value=\"$kundeordnr\">";
		elseif ($kundeordnr) print "&nbsp; Rekv.nr:&nbsp; $kundeordnr</td>";
		if ($status>=3) print "</td><td colspan=\"2\" align=\"right\">Ekspedient: $ref | Bon: $fakturanr</td>";
		print "</tr>\n<tr><td colspan=\"2\"><b>$firmanavn</b></td>";
		if ($status>=3) print "<td colspan=\"4\" align=\"right\">Kasse: $kasse | $fakturadato kl. $tidspkt</td></tr>";
		if ($betalingsbet=='Kontant')print "<tr><td colspan=\"2\"><b>Ingen kredit</b></td>"; 
	} else {
		print "<tr><td colspan=\"5\" align=\"right\">Ekspedient: $ref | Bon: $fakturanr</td></tr>";
		print "<tr><td colspan=\"5\" align=\"right\">Kasse: $kasse | $fakturadato kl. $tidspkt</td></tr>";
	}
	print "<tr><td width=\"10%\" height=\"25px\" valign=\"bottom\">Varenummer</td><td width=\"2%\" valign=\"bottom\">Antal</td><td valign=\"bottom\">Varenavn</td><td align=\"right\" valign=\"bottom\">Pris</td>\n";
 	if ($fokus=="rabat_ny") print "<td align=\"right\" valign=\"bottom\">Rabat</td></tr>";
 	else print "<td align=\"right\" valign=\"bottom\">Sum</td></tr>";
	print "<tr><td colspan=\"5\"><hr></td></tr>\n";
	if ($status < 3) {
		print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">";
		#print "<input type=\"hidden\" name = \"vare_id\" value=\"$vare_id[0]\">";
		print "<input type=\"hidden\" name = \"momssats\" value=\"$momssats\">";
		print "<tr><td width=\"30px\"><input class=\"inputbox\" type=\"text\" size=\"15\" name = \"varenr_ny\" value=\"$varenr_ny\"></td>";
		if ($varenr_ny) {
			print "<td width=\"7px\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"2\" name = \"antal_ny\" value=\"$antal_ny\"></td><td>".$beskrivelse[0]."</td>";
			if ($antal_ny) print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0])."\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name = \"pris_ny\" value=\"$pris[0]\"></td>";
			else {
				print "<input type=hidden name=\"pris_ny\" value=\"$pris[0]\">";
				print "<td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[0])."\">$pris[0]</td>";
			}
			if ($pris_ny && $fokus=="rabat_ny") {
				$r=db_fetch_array(db_select("select box8 from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
				$rabatvareid=$r['box8']*1;
				if (db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__))) {
					print "<td align=\"right\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name = \"rabat_ny\">%</td>";
				} else {
					$txt="Manglende varenr til rabat";
					print "<BODY onLoad=\"javascript:alert('$txt')\">";
					return($txt);
				}
			} else {
					print "<input type=hidden name=\"rabat_ny\" value=\"$rabat[0]\">";
					print "<td align=\"right\">$rabat[0]</td>";
			}

		}
		print "</tr>";
	}
	$sum=vis_pos_linjer($id,$momssats,$status);
	if ($konto_id && $kreditmax && $sum > $kreditmax - $saldo) {
		$ny_saldo=$saldo+$sum;
		$txt = "Kreditmax: ".dkdecimal($kreditmax)."\\nGl. saldo :  ".dkdecimal($saldo)."\\nNy saldo :  ".dkdecimal($ny_saldo);
		print "<BODY onLoad=\"javascript:alert('$txt')\">";

	}
	print "<input type=\"hidden\" name = \"sum\" value = \"$sum\">";
	if ($status >= 3 && $sum) {
		$tmp=dkdecimal($modtaget);
		print "<tr><td>$betaling</td><td colspan=\"4\" align=\"right\">$tmp</td></tr>";
		if ($betalt<$sum && $betaling != "Konto") {
		$tmp=dkdecimal($modtaget2);
		print "<tr><td>$betaling2</td><td colspan=\"4\" align=\"right\">$tmp</td></tr>";
		}
		$tmp=dkdecimal($modtaget+$modtaget2-$sum);
		if ($betaling != "Konto" || $betalingsbet=='Kontant') print "<tr><td>Retur</td><td colspan=\"4\" align=\"right\"><b>$tmp</b></td></tr>";
	} elseif ($status >= 3) {
		$r=db_fetch_array($q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		print "<tr><td>Saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($r[felt_3])."</td></tr>\n";
		$indbetaling=($r['felt_4']-$r['felt_3'])*-1;
		print "<tr><td>Indbetaling</td><td colspan=\"4\" align=\"right\">".dkdecimal($indbetaling)."</td></tr>\n";
		print "<tr><td>$r[felt_1]</td><td colspan=\"4\" align=\"right\">".dkdecimal($r['felt_2'])."</td></tr>\n";
		$ny_saldo=$r['felt_4'];
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo)."</td></tr>\n";
		$retur=$r['felt_2']+$r['felt_4']-$r['felt_3'];
		print "<tr><td>Retur</td><td  colspan=\"4\" align=\"right\">".dkdecimal($retur)."</td></tr>\n";
	}
	print "<tr><td colspan=\"5\" align=\"right\"><input  STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	if ($konto_id && $status<3 && $betalingsbet!='Kontant') {
		print "<tr><td>Gl. saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($saldo)."</td></tr>";
		$ny_saldo=$saldo+$sum;
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo)."</td></tr>";
		if ($kreditmax) {
			print "<tr><td>Kreditmax</td><td colspan=\"4\" align=\"right\">".dkdecimal($kreditmax	)."</td></tr>";
		}
	}
	return ($varenr_ny.chr(9).$pris_ny.chr(9).$status);
}

function opret_ordre($konto_id,$kasse){
	global $brugernavn;
	if ($r=db_fetch_array($q = db_select("select ordrenr from ordrer where art='PO' order by ordrenr desc",__FILE__ . " linje " . __LINE__))) {
		$ordrenr=$r['ordrenr']+1;
	} else $ordrenr=1;
	$ordredate=date("Y-m-d");
	$tidspkt=date("U");
	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$kasseantal=$r['box1']*1;
	$moms=explode(chr(9),$r['box7']);
	$x=$kasse-1;
	if ($moms[$x]){
		$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$moms[$x]'",__FILE__ . " linje " . __LINE__));
		$momssats=$r['box2'];
	} else $momssats='0';
	db_modify ("insert into ordrer
		(ordrenr,konto_id, kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,betalingsdage,betalingsbet,cvrnr,ean,institution,email,mail_fakt,notes,art,ordredate,momssats,hvem,tidspkt,ref,valuta,sprog,kontakt,pbs,status)
			values
		('$ordrenr','0','$kontonr','$firmanavn','','','','','','0','Kontant','','','','','','$notes','PO','$ordredate','$momssats','$brugernavn','$tidspkt','$brugernavn','DKK','','','','0')",__FILE__ . " linje " . __LINE__);

	$r=db_fetch_array(db_select("select id from ordrer where hvem='$brugernavn' and tidspkt='$tidspkt' order by id desc",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	return($id);
} # endfunc opret_ordre()

function indbetaling($id,$indbetaling,$modtaget,$modtaget2,$betaling) {

	global $fokus;
	global $status;

	$fokus="indbetaling";
	$saldo=0;
	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$status=$r['status'];
	$kontonr=$r['kontonr'];
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr_by=$r['postnr']." ".$r['bynavn'];
	if ($status<3) {
		$q=db_select("select * from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$saldo=$saldo+$r['amount'];
		}
		list($a,$b)=explode(",",$indbetaling);
		if (!$indbetaling || !is_numeric($indbetaling)) {
			$indbetaling=$saldo;
			$modtaget='';
			$modtaget2='';
		}
		if ($modtaget+$modtaget2-$indbetaling>0) $retur=dkdecimal($modtaget+$modtaget2-$indbetaling);
		else $retur="0,00";
	} else {
		$saldo=$r['felt_3'];
		$indbetaling=$r['sum'];
		$retur=$r['felt_2']-$indbetaling;
	}
	$ny_saldo=dkdecimal($saldo-$indbetaling);
	$saldo=dkdecimal($saldo);
	$indbetaling=dkdecimal($indbetaling);
	if ($modtaget) {
		$modtaget=dkdecimal($modtaget);
		$fokus="modtaget";
	}
	if ($modtaget2) {
		$modtaget2=dkdecimal($modtaget2);
		$fokus="modtaget";
	}
	print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
	print "<tr><td><b>$kontonr</b></td></tr>\n";
	print "<tr><td><b>C $firmanavn</b></td></tr>\n";
	print "<tr><td><b>$addr1</b></td></tr>\n";
	print "<tr><td><b>$addr2</b></td></tr>\n";
	print "<tr><td><b>$postnr_by</b></td></tr>\n";
	print "<tr><td colspan=2 width=400px><hr></td></tr>\n";
#	while (strlen($saldo) < 10) $saldo=" ".$saldo;
	print "<tr><td>Saldo</td><td align=\"right\">$saldo</td></tr>\n";
	print "<tr><td>Indbetaling</td>";
	if ($status<3) print "<td align=\"right\"><input class=\"inputbox\" type=text size=8 style=\"text-align:right\" name=\"indbetaling\" value=\"$indbetaling\"></td></tr>\n";
	else print "<td align=\"right\">$indbetaling</td></tr>\n";
	if ($status<3) print "<tr><td>Betalt</td><td align=\"right\"><input class=\"inputbox\" type=text size=8 style=\"text-align:right\" name=\"modtaget\" value=\"$modtaget\"></td></tr>\n";
	else print "<tr><td>Betalt</td><td align=\"right\">$modtaget</td></tr>\n";
	print "<tr><td>Ny saldo</td><td align=\"right\">$ny_saldo</td></tr>\n";
	print "<tr><td>Retur</td><td align=\"right\">$retur</td></tr>\n";
  print "<td colspan=\"5\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
}

function vis_pos_linjer($id,$momssats,$status) {

	global $varelinjer;
	global $bgcolor;
	global $bgcolor5;

	$linjebg=$bgcolor;

	$id=$id*1;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id' and posnr >= 0 order by rabatgruppe, id desc",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
		$x++;
		$vare_id[$x]=$r['id'];
		$posnr[$x]=$r['posnr'];
		$varenr[$x]=$r['varenr'];
		$beskrivelse[$x]=stripslashes($r['beskrivelse']);
		$pris[$x]=$r['pris'];
		$kostpris[$x]=$r['kostpris'];
		$antal[$x]=$r['antal'];
		$folgevare[$x]=$r['folgevare'];
		$rabatgruppe[$x]=$r['rabatgruppe'];
		$rabat[$x]=$r['rabat']*1;
		$rabatart[$x]=$r['rabatart'];
		$m_rabat[$x]=$r['m_rabat']*-1;
		$momsfri[$x]=trim($r['momsfri']);
		$varemomssats[$x]=trim($r['momssats']);
		if ($rabatgruppe[$x]) {
			if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
				$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
				$rabatantal[$x-1]=0;
			} else $rabatantal[$x]=$antal[$x];
		} else $rabatantal[$x]=0;
		if ($varemomssats[$x] & $momsfri[$x]!='on') {
			$pris[$x]=$pris[$x]+$pris[$x]/100*$varemomssats[$x];
			if ($m_rabat[$x]) $m_rabat[$x]=$m_rabat[$x]+$m_rabat[$x]/100*$varemomssats[$x];
		}
	}
	$varelinjer=$x;

	for ($x=1;$x<=$varelinjer;$x++) {
		($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
		if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>$varenr[$x]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$beskrivelse[$x]</td><td align=\"right\" title=\"Kostpris ex. moms: ".dkdecimal($kostpris[$x])."\">".dkdecimal($pris[$x])."</td><td align=\"right\">".dkdecimal($pris[$x]*$antal[$x])."</td>";
		$sum+=afrund($pris[$x]*$antal[$x],2);
		if ($rabat[$x]) {
			($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
			if ($rabatart[$x]=="amount") {
				if ($varemomssats[$x] & $momsfri[$x]!='on') $tmp=afrund($rabat[$x]+$rabat[$x]/100*$varemomssats[$x],2)*-1;
				else $tmp=afrund($rabat[$x],2)*-1;
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>Rabat</td><td align=\"right\">".dkdecimal($tmp)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x])."</td>";
				$sum+=afrund($tmp*$antal[$x],2);
			} else {
				$tmp=afrund($pris[$x]*$rabat[$x]/-100,2);
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$rabat[$x]% rabat</td><td align=\"right\">".dkdecimal($tmp)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x])."</td>";
				$sum+=afrund($tmp*$antal[$x],2);
			}
		}
		if ($status < 3) {
			if ($folgevare[$x]) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__));
				$r2 = db_fetch_array(db_select("select box4, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
				$f_bogfkto=$r2['box4'];
				$f_momsfri=$r2['box7'];
				if ($f_momsfri){
					$f_momssats=0;
					$f_pris=$r['salgspris'];
					} else {
				#cho "select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'<br>";
					$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$f_bogfkto' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
					$kodenr=substr($r2['moms'],1);
#cho "select box2 from grupper where kodenr = '$kodenr' and art = 'SM'<br>";
					$r2 = db_fetch_array(db_select("select box2 from grupper where kodenr = '$kodenr' and art = 'SM'",__FILE__ . " linje " . __LINE__));
					$f_momssats=$r2['box2']*1;
					$f_pris=$r['salgspris']+$r['salgspris']*$f_momssats/100;
				}
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>".stripslashes($r['beskrivelse'])."</td><td align=\"right\">".dkdecimal($r['salgspris'])."</td><td align=\"right\">".dkdecimal($antal[$x]*$r['salgspris'])."</td>";
				$sum+=afrund($antal[$x]*$r['salgspris'],2);
			}
			if ($rabatantal[$x]) {
				($linjebg!=$bgcolor5)?$linjebg=$bgcolor5:$linjebg=$bgcolor;
				list($grupperabat,$rabattype)=explode(";",grupperabat($rabatantal[$x],$rabatgruppe[$x]));
				if ($grupperabat) {
					if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>rabat</td><td align=\"right\">".dkdecimal($rabatantal[$x])."</td><td>Rabat</td><td align=\"right\">".dkdecimal($grupperabat)."</td><td align=\"right\">".dkdecimal($grupperabat*$rabatantal[$x])."</td>";
					$sum+=afrund($grupperabat*$rabatantal[$x],2);
				}
			} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
				if ($posnr[$x]) print "<tr bgcolor=\"$linjebg\"><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>".stripslashes($r['beskrivelse'])."</td><td align=\"right\">".dkdecimal($f_pris)."</td><td align=\"right\">".dkdecimal($antal[$x]*$f_pris)."</td>";
				$sum+=afrund($m_rabat[$x]*$antal[$x],2);
			}
		}		
	}
	$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
	($difkto=$r['box2'])?$afrundet=pos_afrund($sum):$afrundet=0;
	print "<tr><td colspan=\"5\"><hr></td></tr>\n";
	if ($sum) print "<tr><td>I alt</td><td align=\"right\"></td><td></td><td align=\"right\"></td><td align=\"right\">".dkdecimal($sum)."</td></tr>\n";
	if ($afrundet) print "<tr><td>Afrundet</td><td align=\"right\"></td><td></td><td align=\"right\"></td><td align=\"right\">".dkdecimal($afrundet)."</td></tr>\n";
return($sum);

}

function pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling) {

#cho "$id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling<br>";

	global $db;
	global $db_id;
	global $brugernavn;
	global $bruger_id;
	global $momssats;
	global $db_encode;

#	$udskriv_bon=1;
 	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$convert = new ConvertCharset();

	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr=$r['postnr'];
	$bynavn=$r['bynavn'];
	$tlf=$r['tlf'];
	$cvrnr=$r['cvrnr'];

	$belob="beløb";
	if ($firmanavn) $firmanavn = $convert ->Convert($firmanavn, $FromCharset, $ToCharset);
	if ($addr1) $addr1 = $convert ->Convert($addr1, $FromCharset, $ToCharset);
	if ($addr2) $addr2 = $convert ->Convert($addr2, $FromCharset, $ToCharset);
	if ($bynavn) $bynavn = $convert ->Convert($bynavn, $FromCharset, $ToCharset);
	if ($tlf) $tlf = $convert ->Convert($tlf, $FromCharset, $ToCharset);
	if ($cvrnr) $cvrnr = $convert ->Convert($cvrnr, $FromCharset, $ToCharset);

	if ($belob) $belob = $convert ->Convert($belob, $FromCharset, $ToCharset);

	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$kontonr=$r['kontonr'];
	$kundenavn=$r['firmanavn'];
	$kundeaddr1=$r['addr1'];
	$kundepostnr=$r['postnr'];
	$kundeby=$r['bynavn'];
	$kundeordnr=$r['kundeordnr'];
	$fakturadate=$r['fakturadate'];
	$fakturanr=$r['fakturanr'];
	$betalingsbet=$r['betalingsbet'];
	$fakturadato=dkdato($r['fakturadate']);
	$sum=$r['sum'];
	$moms=$r['moms'];
	$momssats=$r['momssats'];
	$betaling=$r['felt_1'];
	$modtaget=$r['felt_2']*1;
	$betaling2=$r['felt_3'];
	$modtaget2=$r['felt_4']*1;
	$betalt=$modtaget+$modtaget2;
	$ref=$r['ref'];
	$kasse=$r['felt_5'];
	$tidspkt=$r['tidspkt'];
	$dkdato=dkdato(substr($tidspkt,0,10));
	$tid=substr($tidspkt,-5);
	if (!$tid) $tid=date("H:i");
	if (!$betaling) $betaling="Betalt";
	if ($ref) {
		if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			$ansat_id=$r['ansat_id']*1;
			if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
	  }
	}
	if ($kundenavn) $kundenavn = $convert ->Convert($kundenavn, $FromCharset, $ToCharset);
	if ($kundeaddr1) $kundeaddr1 = $convert ->Convert($kundeaddr1, $FromCharset, $ToCharset);
	if ($kundeby) $kundeby = $convert ->Convert($kundeby, $FromCharset, $ToCharset);
	if ($ref) $ref = $convert ->Convert($ref, $FromCharset, $ToCharset);

	$x=0;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id' and posnr > 0 order by posnr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$x++;
		if ($r['momsfri']!='on') $pris=$r['pris']+$r['pris']/100*$momssats;
		else $pris=$r['pris'];
		if (strtoupper($r['varenr'])=='INDBETALING') {
			$pris=$pris*-1;
			$sum=$sum*-1;
		}
		$rabat[$x]=$r['rabat']*1;
		$rabatart[$x]=$r['rabatart'];
		$beskrivelse[$x]=$r['beskrivelse'];
		if ($beskrivelse[$x]) $beskrivelse[$x]= $convert ->Convert($beskrivelse[$x], $FromCharset, $ToCharset);
		$antal[$x]=$r['antal']*1;
		$dkkpris[$x]=dkdecimal($pris*$antal[$x]);
		while(strlen($dkkpris[$x])<9){
			$dkkpris[$x]=" ".$dkkpris[$x];
		}
		while(strlen($antal[$x])<3){
			$antal[$x]=" ".$antal[$x];
		}
		if (strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
		while(strlen($beskrivelse[$x])<26){
			$beskrivelse[$x]=$beskrivelse[$x]." ";
		}
		if ($rabat[$x]) {
			$y=$x;
			$x++;
			$antal[$x]=$antal[$y];
			if ($rabatart[$y]=='amount') {
				$beskrivelse[$x]="Rabat";
				$pris=$rabat[$y]*-1;
			} else {
				$beskrivelse[$x]="Rabat ".$rabat[$y]."%";
				$pris=$r['pris']/100*$rabat[$y]*-1;
			}
			if ($r['momsfri']!='on') $pris+=$pris/100*$momssats;
			$dkkpris[$x]=dkdecimal($pris*$r['antal']);
			while(strlen($dkkpris[$x])<9){
				$dkkpris[$x]=" ".$dkkpris[$x];
			}
			while(strlen($antal[$x])<3){
				$antal[$x]=" ".$antal[$x];
			}
			if (strlen($beskrivelse[$x])>26) $beskrivelse[$x]=substr($beskrivelse[$x],0,25);
			while(strlen($beskrivelse[$x])<26){
				$beskrivelse[$x]=$beskrivelse[$x]." ";
			}
		}
		$linjeantal=$x;
	}
	$sum+=$moms;
	if ($konto_id) {
		if (!$x) $indbetaling=$sum;
		$gl_saldo=dkdecimal($betaling2);
		$ny_saldo=dkdecimal($modtaget2);
	}
	if ($indbetaling) $retur=$modtaget-$indbetaling;
	else $retur=$betalt-$sum;
	$dkksum=dkdecimal($sum);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
	}
	$dkkretur=dkdecimal($retur);
	while(strlen($dkkretur)<9){
		$dkkretur=" ".$dkkretur;
	}
	$betalt=dkdecimal($betalt);
	while(strlen($betalt)<9){
		$betalt=" ".$betalt;
	}
	while(strlen($betaling)<19){
		$betaling=$betaling." ";
	}
	while(strlen($betaling2)<19){
		$betaling2=$betaling2." ";
	}
	$dkkmodtaget=dkdecimal($modtaget);
	while(strlen($dkkmodtaget)<9){
		$dkkmodtaget=" ".$dkkmodtaget;
	}
	if ($modtaget2) {
		$dkkmodtaget2=dkdecimal($dkkmodtaget2);
		while(strlen($dkkmodtaget2)<9){
			$dkkmodtaget2=" ".$dkkmodtaget2;
		}
	}
	$dkksum=dkdecimal($sum);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
	}
	$dkkmoms=dkdecimal($moms);
	while(strlen($dkkmoms)<9){
		$dkkmoms=" ".$dkkmoms;
	}
	$filnavn="pos_print/pos_print_".$db_id.".php";
	if (file_exists("$filnavn")) include("$filnavn");
	else include("pos_print/pos_print.php");
	fclose($fp);
}

function opdater_konto($konto_id,$kontonr,$id) {
#Opdaterer kontoinformation på ordren
	global $kasse;
	global $kundeordnr;
	if (!$id) $id=opret_ordre(0,$kasse);
	$konto_id*=1;
	$kontonr*=1;
	$r=db_fetch_array(db_select("select status from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$status=$r['status'];
	if ($status < 3 && ($konto_id || $kontonr)) {
		if ($konto_id) $r=db_fetch_array(db_select("select * from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		else $r=db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr' and art = 'D'",__FILE__ . " linje " . __LINE__));
		if ($konto_id=$r['id']) {
			if ($r['lukket']) {
				$betalingsbet='Kontant';
				$betalingsdage='0';
			} else {
				($r['betalingsbet'])?$betalingsbet=$r['betalingsbet']:$betalingsbet='Kontant';
				$betalingsdage=$r['betalingsdage']*1;
			}
		  db_modify ("update ordrer set konto_id='$konto_id', kontonr='$r[kontonr]',firmanavn='$r[firmanavn]',addr1='$r[addr1]',addr2='$r[addr2]',
					postnr='$r[postnr]',bynavn='$r[bynavn]',land='$r[land]',betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$r[cvrnr]',
			ean='$r[ean]',institution='$r[institution]',email='$r[email]',kontakt='$r[kontakt]',art='PO',valuta='DKK',valutakurs='100' where id = '$id'",__FILE__ . " linje " . __LINE__);
	  }
	}
	return($id);
} # endfunc opdater_konto()


function hoved($kasse) {
	global $regnskab;
	global $brugernavn;
	global $bruger_id;
	global $id;
	global $db;
	global $db_id;
	global $bon;
	global $sum;
	global $returside;

	if ($kasse=="?") find_kasse($kasse);
	$x=0;
	$q=db_select("select brugernavn from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$x++;
		$loginnavn[$x]=$r['brugernavn'];
	}
	$brugerantal=$x;
	print "<tr>";
	print "<td width=\"96%\" height=\"25px\" valign=\"bottom\"><b>$regnskab</b> Kasse: <a href=pos_ordre.php?id=$id&kasse=?>$kasse</a> | ";
	print "<a href=pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on>Kasseopt&aelig;lling</a><br>";
	print "Ekspedient<select class=\"inputbox\" NAME=\"ny_bruger\">";
	print "<option>$brugernavn</option>";
	for ($x=1;$x<=$brugerantal;$x++) {
		if ($loginnavn[$x] != $brugernavn) print "<option>$loginnavn[$x]</option>";
	}
	print "</option>";
	if ($status>=3 && !$bon && $id) {
		$r=db_fetch_array($q=db_select("select fakturanr from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
		$bon=$r['fakturanr'];
	}

	print "<input class=\"inputbox\" type=\"password\" size=\"10\" name=\"kode\" value=\"        \">";
	print "<span title=\"Skriv bon nummeret på den bon som skal genkaldes elles 'S' for sidste bon fra denne kasse\">  | Bon <input class=\"inputbox\" type=\"text\" name=\"bon\" size=\"6\" value=\"$bon\"></span><br><hr></td>";
	print "<td width=\"4%\" align=\"right\" valign=\"top\"><a href='$returside'><div class=\"luk\"></div></a></td></tr>\n";
	print "</tr>\n";
}

/*
	function hoved($kasse) {
	global $regnskab;
	global $brugernavn;
	global $id;
	global $db;
	global $db_id;

	if ($kasse=="?") find_kasse($kasse);
	print "<tr>";
	print "<td width=\"96%\" height=\"25px\" valign=\"bottom\"><b>$regnskab</b> Kasse: <a href=pos_ordre.php?id=$id&kasse=?>$kasse</a> | ";
	print "Ekspedient: <a href=../includes/relogin.php?regnskab=$regnskab&bruger_id=$bruger_id&db_$db&db_id=$db_id>$brugernavn</a> | ";
	print "<a href=pos_ordre.php?id=$id&kasse=$kasse&kassebeholdning=on>Kasseopt&aelig;lling</a><br><hr></td>";
	print "<td width=\"4%\" align=\"right\" valign=\"top\"><a href=../includes/luk.php><div class=\"luk\"></div></a></td></tr>\n";


print "</tr>\n";

}
*/
function find_kasse($kasse) {

      if ($kasse!="?" && isset($_COOKIE['saldi_pos'])) {
		return(stripslashes($_COOKIE['saldi_pos']));
	} else {
		print "<form name=pos_ordre action=pos_ordre.php?kasse=opdat method=post>";
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS'",__FILE__ . " linje " . __LINE__));
		$kasseantal=$r['box1']*1;
		print "V&aelig;lg kasse<SELECT NAME=kasse>";
		for($x=1;$x<=$kasseantal;$x++) {
			print	"<option value=\"$x\">$x</option>";
		}
		print "</SELECT></td>";;
		print "<INPUT TYPE=\"submit\" NAME=\"submit\"VALUE=\"OK\">";
		print "</form>";
	}
	exit;
}

function tastatur($status) {

	global $id;
	global $bon;
	global $fokus;
	global $sum;
	global $modtaget;
	global $modtaget2;
	global $kontonr;
	global $varelinjer;
	global $varenr_ny;
	global $indbetaling;
	global $betalingsbet;

	$sum=afrund($sum,2);
	$modtaget=afrund($modtaget,2);
	$modtaget2=afrund($modtaget2,2);

	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kortantal=$r['box4']*1;
	$korttyper=explode(chr(9),$r['box5']);
	$vis_kontoopslag=$r['box11'];
	$vis_hurtigknap=$r['box12'];
	$vis_indbetaling=$r['box14'];
	$timeout=$r['box13']*1;

	print "<input type=hidden name=\"sum\" value=\"$sum\">";
	print "<input type=hidden name=\"kontonr\" value=\"$kontonr\">";

	print "<TR><TD height=\"100%\" valign=\"top\"  align=\"center\"><TABLE BORDER=\"0\" CELLPADDING=\"4\" CELLSPACING=\"4\"><TBODY>";
	print "<TR>";
	if ($status < 3) {
		$stil="STYLE=\"width: 4.5em;height: 2em;font-size:150%;\"";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"one\"   VALUE=\"1\" OnClick=\"pos_ordre.$fokus.value += '1';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"two\"   VALUE=\"2\" OnCLick=\"pos_ordre.$fokus.value += '2';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"three\" VALUE=\"3\" OnClick=\"pos_ordre.$fokus.value += '3';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"plus\"  VALUE=\"+\" OnClick=\"pos_ordre.$fokus.value += '+';pos_ordre.$fokus.focus();\"></TD>";
		print "</TR><TR>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"four\"  VALUE=\"4\" OnClick=\"pos_ordre.$fokus.value += '4';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"five\"  VALUE=\"5\" OnCLick=\"pos_ordre.$fokus.value += '5';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"six\"   VALUE=\"6\" OnClick=\"pos_ordre.$fokus.value += '6';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"minus\" VALUE=\"-\" OnClick=\"pos_ordre.$fokus.value += '-';pos_ordre.$fokus.focus();\"></TD>";
		print "</TR><TR>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"seven\" VALUE=\"7\" OnClick=\"pos_ordre.$fokus.value += '7';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"eight\" VALUE=\"8\" OnCLick=\"pos_ordre.$fokus.value += '8';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"nine\"  VALUE=\"9\" OnClick=\"pos_ordre.$fokus.value += '9';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"times\" VALUE=\"x\" OnClick=\"pos_ordre.$fokus.value += '*'\"></TD>";
		print "</TR><TR>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\",\" OnClick=\"pos_ordre.$fokus.value += ',';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"zero\"  VALUE=\"0\" OnClick=\"pos_ordre.$fokus.value += '0';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"DoIt\"  VALUE=\"=\" OnClick=\"pos_ordre.$fokus.value = eval(pos_ordre.$fokus.value);pos_ordre.$fokus.focus();\"></TD>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"div\"   VALUE=\"/\" OnClick=\"pos_ordre.$fokus.value += '/';pos_ordre.$fokus.focus();\"></TD>";
		print "</TR><TR>";
		print "<TD><INPUT TYPE=\"button\" $stil NAME=\"clear\" VALUE=\"Ryd\" OnClick=\"pos_ordre.$fokus.value = '';pos_ordre.$fokus.focus();\"></TD>";
		if ($id) {
			print "<TD><INPUT TYPE=\"submit\" $stil NAME=\"afslut\"VALUE=\"Afslut\" OnClick=\"pos_ordre.$fokus.value += 'a';pos_ordre.$fokus.focus();\"></TD>";
			print "<TD onclick=\"return confirm('Slet alt og start forfra')\"><INPUT TYPE=\"submit\" $stil NAME=\"forfra\"VALUE=\"Forfra\" OnClick=\"pos_ordre.$fokus.value += 'f';pos_ordre.$fokus.focus();\"></TD>";
		} else print "<TD COLSPAN=\"2\"></TD>";
		if ($fokus=='modtaget') {
			print "<TD onclick=\"return confirm('Tilbage til varescanning')\"><INPUT TYPE=\"submit\" $stil NAME=\"tilbage\"VALUE=\"Tilbage\" OnClick=\"pos_ordre.$fokus.value += 't';pos_ordre.$fokus.focus();\"></TD>";
			print "</TR><TR>";
			print "<TD COLSPAN=\"3\"></TD>";
		}
		print "<TR><TD COLSPAN=\"4\"><HR></TD></tr>\n";
		$stil2="STYLE=\"width: 9.5em;height: 2em;font-size:150%;\"";
		print "<TR>";
#cho "$fokus=='modtaget' && $modtaget>=$sum && !$indbetaling<br>";
		if ($fokus=='varenr_ny') print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"varer\"VALUE=\"Varer\" OnClick=\"pos_ordre.$fokus.value += 'v';pos_ordre.$fokus.focus();\"></TD>";
		elseif ($fokus=='antal_ny' || $fokus=='pris_ny') { #20130310 Tilføjet: || $fokus=='pris_ny' 
			if ($fokus=='antal_ny') print "<TD COLSPAN=\"1\"><INPUT TYPE=\"submit\" $stil NAME=\"pris\"VALUE=\"Pris\" OnClick=\"pos_ordre.$fokus.value += 'p';pos_ordre.$fokus.focus();\"></TD>";
			else print "<TD COLSPAN=\"1\"></TD>";
			print "<TD COLSPAN=\"1\"><INPUT TYPE=\"submit\" $stil NAME=\"rabat\"VALUE=\"Rabat\" OnClick=\"pos_ordre.$fokus.value += 'r';pos_ordre.$fokus.focus();\"></TD>";
		} elseif ($fokus=='modtaget' && $modtaget>=$sum && !$indbetaling && $betalingsbet != 'Kontant') {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD>";
		} elseif ($fokus=='modtaget2' && $modtaget+$modtaget2>=$sum && !$indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling2\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD>";
		}	elseif ($indbetaling && $modtaget >= $indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Kontant\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD>";
		} else print "<TD colspan=2></TD>";
		print "<TD colspan=2><INPUT TYPE=\"submit\" $stil2 NAME=\"OK\"  VALUE=\"Enter\"></TD></tr>\n";
		if ($vis_hurtigknap && $fokus=='antal_ny') print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Kontant p&aring; bel&oslash;b\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD>";
		if ($vis_kontoopslag && !$varenr_ny && !$indbetaling) print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"kontoopslag\" VALUE=\"Kontoopslag\"></TD></tr>\n";
		if ((($fokus=='modtaget' || $fokus=='modtaget2') && (!$kontonr || $betalingsbet=='Kontant')) || ($indbetaling && $modtaget>=$indbetaling && $kontonr)) {
			for($x=0;$x<$kortantal;$x++) {
				($fokus=='modtaget2')?$tmp="betaling2":$tmp="betaling";
#cho "beta $tmp<br>";
				print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"$korttyper[$x]\" OnClick=\"pos_ordre.$fokus.value += 'd';pos_ordre.$fokus.focus();\"></TD></tr>\n";
			}
			if (!$indbetaling) {
				if ($fokus=='modtaget2') $tmp="betaling2";
				else $tmp="betaling";
				print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"Kontant\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD></tr>\n";
			}
#			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD></tr>\n";
		} elseif ($id && $kontonr && !$varelinjer && !$indbetaling)
		if ($vis_indbetaling) print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"indbetaling\" VALUE=\"Indbetaling\" OnClick=\"pos_ordre.$fokus.value += 'i';pos_ordre.$fokus.focus();\"></TD>";
	} else {
#		print "<input type=\"hidden\" name=\"bon\" value = \"\">";
		$stil2="STYLE=\"width: 9.5em;height: 2em;font-size:150%;\"";
		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"udskriv\"VALUE=\"Udskriv\"></TD>";
		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"ny\"VALUE=\"Ny kunde\"></TD></TR>";
		print "<TD COLSPAN=\"4\"><br></TD></TR>";
		print "<TD COLSPAN=\"4\"><br></TD></TR>";
		print "<TD COLSPAN=\"4\" align=\"center\"><INPUT TYPE=\"submit\" $stil2 NAME=\"krediter\"VALUE=\"Korrektion\"></TD>";
#		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"ny\"VALUE=\"Ny kunde\"></TD>";
		if ($timeout && !$bon) print "<meta http-equiv=\"refresh\" content=\"$timeout;URL=pos_ordre.php?id=0\">";
	}
	print "</tr>\n";
	print "</TBODY></TABLE></TD></tr>\n";
}

function menubuttons($id,$menu_id,$vare_id) {
	global $bgcolor2;
	global $fokus;
	global $pris_ny;
	global $varenr_ny;

	$tid=date("H:i");
	if (!$menu_id) {
	$r=db_fetch_array(db_select("select kodenr from grupper where art='POSBUT' and (box7 < box8) and (box7<='$tid' and box8>='$tid')",__FILE__ . " linje " . __LINE__));
		$menu_id=$r['kodenr'];
	if (!$menu_id) { #her tages højde for at slut tidspkt kan være mindre en starttidspkt
		$r=db_fetch_array(db_select("select kodenr from grupper where art='POSBUT' and (box7 > box8) and ((box7>='$tid' and box8>='$tid') or (box7<='$tid' and box8<='$tid'))",__FILE__ . " linje " . __LINE__));
		$menu_id=$r['kodenr'];
	}
	}

	$q=db_select("select * from grupper where art='POSBUT' and kodenr='$menu_id'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$menuid=$r['kodenr'];
	$beskrivelse=$r['box1'];
	$cols=$r['box2'];
	$rows=$r['box3'];
	$height=$r['box4'];
	$width=$r['box5'];
	$fontsize=$r['box10'];
	if (!$fontsize) $fontsize=$height*$width/200;
/*
	print "<style type=\"text/css\">";
	print "table a {display:block;width:100%;height:100%;}";
	print "</style>";
*/
#print "
#";
	print "<table border=\"0\" cellspacing=\"5\" cellpadding=\"1\"><tbody>"; # table 1 ->
	print "<tr><td colspan=\"$cols\" align=\"center\" bgcolor=\"$bgcolor2\">$beskrivelse</td></tr>";

for ($x=1;$x<=$rows;$x++) {
	print "<tr>";
	for ($y=1;$y<=$cols;$y++) {
#		menu_id,row,col,beskrivelse,color,funktion,vare_id,colspan,rowspan
		$r=db_fetch_array(db_select("select * from pos_buttons where menu_id=$menuid and row='$x' and col='$y'",__FILE__ . " linje " . __LINE__));
		$a=$r['beskrivelse'];
		$b=$r['color'];
		$c=$r['vare_id']*1;
		$d=$r['funktion']*1;
		if ($a) {
			$knap="<input type=\"button\" style=\"width:".$width."px;height:".$height."px;text-align:center;font-size:".$fontsize."px; background-color:#$b;\" value= \"$a\">";
			print "<td>";
#			($fokus='antal')?$vnr=varenr_ny=$varenr_ny:$vnr=varenr_ny=$varenr_ny;
			if (!$d || $d==1) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&menu_id=$menu_id&vare_id=$vare_id&vare_id_ny=$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus>$knap</a>";
			elseif ($d==2) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&vare_id=$vare_id&menu_id=$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus>$knap</a>";
			elseif ($d==3) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&konto_id=$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus>$knap</a>";
			elseif ($d==4) print "<a style=\"text-decoration:none\" href=pos_ordre.php?id=$id&spec_func=spec_$c&varenr_ny=$varenr_ny&pris_ny=$pris_ny&fokus=$fokus>$knap</a>";
			print "</td>";
		}
	}
	print "</tr>";
}
print "</tbody></table>"; # <- table 1
} # function menubuttons

function fejl ($id,$fejltekst) {
  print "<BODY onLoad=\"javascript:alert('$fejltekst')\">";
  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">";

}

function posbogfor ($kasse) {

	$x=0;
#cho "select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' and konto_id= '0' and art = 'PO' and status='3' order by fakturadate<br>";
	$q=db_select("select distinct(fakturadate) as fakturadate from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' order by fakturadate",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['fakturadate']) {
			$x++;
			$fakturadate[$x]=$r['fakturadate'];
		}
	}
	$x=0;
#cho "select distinct(felt_1) as betaling from ordrer where felt_5='$kasse' and konto_id= '0' and art = 'PO' and status='3' order by felt_1<br>";
	$q=db_select("select distinct(felt_1) as betaling from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' order by felt_1",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['betaling']) {
			$x++;
			$betaling[$x]=$r['betaling'];
		}
	}
	$x=0;
#cho "select distinct(felt_3) as betaling2 from ordrer where felt_5='$kasse' and konto_id= '0' and art = 'PO' and status='3' order by felt_3<br>";
	$q=db_select("select distinct(felt_3) as betaling2 from ordrer where felt_5='$kasse' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3' order by felt_3",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['betaling2']) {
			$x++;
			$betaling2[$x]=$r['betaling2'];
		}
	}
	for ($x=1;$x<=count($fakturadate);$x++) {
#cho "X $x<br>";
		for ($y=0;$y<=count($betaling);$y++) {
#cho "Y $y<br>";
			for ($z=0;$z<=count($betaling2);$z++) {
#cho "Z $z<br>";
				$id=NULL;
#cho "select id from ordrer where felt_5='$kasse' and fakturadate='$fakturadate[$x]' and felt_1='$betaling[$y]' and felt_3='$betaling2[$z]' and konto_id= '0' and art = 'PO' and status='3'<br>";
				$q=db_select("select id from ordrer where felt_5='$kasse' and fakturadate='$fakturadate[$x]' and felt_1='$betaling[$y]' and felt_3='$betaling2[$z]' and (konto_id='0' or betalingsbet='Kontant') and art = 'PO' and status='3'",__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					if($id) $id.=",".$r['id'];
					else $id=$r['id'];
				}
				$r = db_fetch_array(db_select("select box9 from grupper where art='POS' and kodenr='1'",__FILE__ . " linje " . __LINE__));
				if($r['box9'] && $id) {
					transaktion('begin');
					bogfor_nu("$id","");
					transaktion('commit');
				}
			}
		}
	}
}

function kassebeholdning ($kasse) {
	global $bruger_id;
	global $db;
	global $db_encode;
	$dd=date("Y-m-d");
	$tid=date("H:m");

	posbogfor($kasse);

 	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF8") $FromCharset = "UTF-8";
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865";
	$convert = new ConvertCharset();

	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$kassopgorelse="KASSEOPGØRELSE";
	$tmp = $convert ->Convert($kassopgorelse, $FromCharset, $ToCharset);

	fwrite($fp,"\n\n$tmp\n\n");
	fwrite($fp,"Den $dd kl. $tid\n");
	fwrite($fp,"Kasse nr: $kasse\n");

	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kassekonti=explode(chr(9),$r['box2']);
	$kortantal=$r['box4']*1;
	$kortnavn=explode(chr(9),$r['box5']);
	$kortkonto=explode(chr(9),$r['box6']);
	$straksbogfor=$r['box9'];

	$kasse--;
	$r=db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate < '$dd' and kontonr = '$kassekonti[$kasse]'",__FILE__ . " linje " . __LINE__));
	$byttepenge=$r['debet']-$r['kredit'];
	$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kassekonti[$kasse]'",__FILE__ . " linje " . __LINE__));
	$tilgang=$r['debet']-$r['kredit'];

	if (!$straksbogfor) {
#		$r=db_fetch_array(db_select("select sum(ordrelinjer.pris) as pris from ordrelinjer,ordrer where  ordrer.status = '3' and ordrer.art = 'PO' and ordrer.fakturadate < '$dd' and ordrelinjer.ordre_id=ordrer.id and ordrelinjer.bogf_konto = '$kassekonti[$kasse]'",__FILE__ . " linje " . __LINE__));
		$r=db_fetch_array(db_select("select sum(sum+moms) as sum from ordrer where  status = '3' and art = 'PO' and fakturadate < '$dd'",__FILE__ . " linje " . __LINE__));
		$byttepenge+=$r['pris'];
#		$r=db_fetch_array(db_select("select sum(ordrelinjer.pris) as pris from ordrelinjer,ordrer where  ordrer.status = '3' and ordrer.art = 'PO' and ordrer.fakturadate = '$dd' and ordrelinjer.ordre_id=ordrer.id and ordrelinjer.bogf_konto = '$kassekonti[$kasse]'",__FILE__ . " linje " . __LINE__));
		$r=db_fetch_array(db_select("select sum(sum+moms) as sum from ordrer where  status = '3' and art = 'PO' and fakturadate = '$dd'",__FILE__ . " linje " . __LINE__));
		$tilgang+=$r['sum'];
	}

	if ($kortantal) {
#		$kortsum[]=0;
		fwrite($fp,"\n\nSalg paa kort\n\n");
		for ($x=0;$x<$kortantal;$x++) {
			if ($kortkonto[$x]) {
				$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kortkonto[$x]'",__FILE__ . " linje " . __LINE__));
				$kortsum[$x]=dkdecimal($r['debet']-$r['kredit']);
				if (!$straksbogfor) {
#cho "select sum(ordrelinjer.pris) as pris from ordrelinjer,ordrer where  ordrer.status = '3' and ordrer.art = 'PO' and ordrer.fakturadate < '$dd' and ordrelinjer.ordre_id=ordrer.id and ordrelinjer.bogf_konto = '$kortkonto[$x]'<br>";
#					$r=db_fetch_array(db_select("select sum(sum+moms) as sum from ordrer where  status = '3' and art = 'PO' and fakturadate = '$dd'",__FILE__ . " linje " . __LINE__));
#					$totalsum+=$r['pris'];
					$q=db_select("select felt_2 from ordrer where  status = '3' and art = 'PO' and fakturadate < '$dd' and felt_1='$kortnavn[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) $byttepenge-=$r['felt_2'];
					$q=db_select("select felt_4 from ordrer where  status = '3' and art = 'PO' and fakturadate < '$dd' and felt_3='$kortnavn[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) $byttepenge-=$r['felt_4'];
					$q=db_select("select felt_2 from ordrer where  status = '3' and art = 'PO' and fakturadate = '$dd' and felt_1='$kortnavn[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) $kortsum[$x]+=$r['felt_2'];
					$q=db_select("select felt_4 from ordrer where  status = '3' and art = 'PO' and fakturadate = '$dd' and felt_3='$kortnavn[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) $kortsum[$x]+=$r['felt_4'];
					$tilgang-=$kortsum[$x];
				}
#				fwrite($fp,"$kortnavn[$x]: $tmp\n\n");
			}
		}
	}
	$kassesum=dkdecimal($byttepenge+$tilgang);
	$byttepenge=dkdecimal($byttepenge);
	$tilgang=dkdecimal($tilgang);

	fwrite($fp,"Beholdning primo: $byttepenge\n\n");
	fwrite($fp,"Dagens indbetalinger $tilgang\n\n");
	fwrite($fp,"Beholdning ultimo $kassesum\n\n");
	if ($kortantal) {
		for ($x=0;$x<$kortantal;$x++) {
			if ($kortkonto[$x]) {
				fwrite($fp,"\n\nSalg paa kort\n\n");
				fwrite($fp,"$kortnavn[$x]: $kortsum[$x]\n\n");
			}
		}
	}
	fwrite($fp,"\n\n\n");

	fclose($fp);
	$tmp="/temp/".$db."/".$bruger_id.".txt";
	$url="://".$_SERVER['SERVER_NAME'].=$_SERVER['PHP_SELF'];
	$url=str_replace("/debitor/pos_ordre.php","",$url);
	if ($_SERVER[HTTPS]) $url="s".$url;
	$url="http".$url;
	print "<BODY onLoad=\"JavaScript:window.open('http://localhost/saldiprint.php?printfil=$tmp&url=$url&bruger_id=$bruger_id&bonantal=1' , '' , '$jsvars');\">";

}
if (!$varenr_ny && $fokus!='modtaget' && $fokus!='modtaget2' && $fokus!='indbetaling') $fokus="varenr_ny";
#cho "fokus $fokus<br>";
?>
</body></html>
<script language="javascript">
document.pos_ordre.<?php echo $fokus?>.focus();
</script>
