<?php
// ------------- debitor/pos_ordre.php ---------- lap 2.1.8----2010-04-16-------
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
ob_start();

$modulnr=5;
$title="POS_ordre";
$css="../css/pos.css";
$betaling=NULL; $betaling2=NULL; $next_varenr=NULL;
$fokus="varenr_ny"; 
$valuta='DKK';$valutakurs='100';


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

$returside=(if_isset($_GET['returside']));
if (!$returside) {
	if ($popup) $returside="../includes/luk.php";
	else $returside="../index/logud.php";
}
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

$kasse = if_isset($_GET['kasse']);
$kassebeholdning = if_isset($_GET['kassebeholdning']);
if ($kasse && $kassebeholdning) kassebeholdning($kasse);
if (!$kasse || $kasse == "?") $kasse=find_kasse($kasse);
elseif ($kasse=="opdat") {
	$kasse=$_POST['kasse'];
	setcookie("saldi_pos",$kasse,time()+60*60*24*30);
}
ob_end_flush();
$bon = strtoupper(if_isset($_POST['bon']));
if ($bon=='S') {
	$r=db_fetch_array(db_select("select max(id) as id from ordrer where felt_5='$kasse'",__FILE__ . " linje " . __LINE__));
	if ($id=$r['id'])	$r=db_fetch_array(db_select("select fakturanr from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$bon=$r['fakturanr'];
} elseif ($bon) {
	$r=db_fetch_array(db_select("select id from ordrer where fakturanr = '$bon'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
} else $id = if_isset($_GET['id'])*1;
$vare_id = if_isset($_GET['vare_id'])*1;
$funktion = if_isset($_GET['funktion']);
if ($funktion) {
	$sort = if_isset($_GET['sort'])*1;
	$funktion ('PO',$sort,$fokus, $id,"","","");
}
$kontonr = if_isset($_POST['kontonr'])*1;
$konto_id = if_isset($_GET['konto_id'])*1;
if ($konto_id || $kontonr) {
	$id=opdater_konto($konto_id,$kontonr,$id);
	$r=db_fetch_array(db_select("select momssats,sum,betalt from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']*1;
	$modtaget=$r['betalt']*1;
	$sum=$r['sum']*1;
	$betaling='ukendt';
#	if ($modtaget <= $sum) $id=afslut($id,'konto',$modtaget);
#	else $betaling='ukendt';
}	

if ($vare_id) {
	$r=db_fetch_array(db_select("select varenr from varer where id = '$vare_id'",__FILE__ . " linje " . __LINE__));
	$varenr_ny=$r['varenr'];
} elseif (sizeof($_POST)>1) {
	$ny_bruger=if_isset($_POST['ny_bruger']);
	$kode=if_isset($_POST['kode']);
	$indbetaling=if_isset($_POST['indbetaling']);
	$sum=if_isset($_POST['sum']);
	$betaling=if_isset($_POST['betaling']);
	$betaling2=if_isset($_POST['betaling2']);
#echo "B2$betaling2<br>";
	$kontonr=if_isset($_POST['kontonr']);
	$modtaget=if_isset($_POST['modtaget']);
	$modtaget2=if_isset($_POST['modtaget2']);
	$kundeordnr=if_isset($_POST['kundeordnr']);
	$fokus=if_isset($_POST['fokus']);
 	$pris_ny=if_isset($_POST['pris_ny']);
 	$antal_ny=strtolower(trim(if_isset($_POST['antal_ny'])));
	$varenr_ny=addslashes(trim(if_isset($_POST['varenr_ny'])));
	$momssats=(if_isset($_POST['momssats']));
	$rabat_ny=if_isset($_POST['rabat_ny']);

	$sum*=1;
	#	echo "update ordrer set kundeordnr = '$kundeordnr',sum='$sum', betalt='$betalt',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse' where id='$id'<br>";
	if ($kundeordnr && $id) db_modify("update ordrer set kundeordnr = '$kundeordnr' where id='$id'",__FILE__ . " linje " . __LINE__);


#echo "betalt=$betalt fok $fokus<br>";

	if (strstr($pris_ny,",")) { #Skaerer orebelob ned til 2 cifre.
		list($kr,$ore)=split(",",$pris_ny);
		$ore=substr($ore,0,2);
		$pris_ny=$kr.",".$ore;
	}
	if(isset($_POST['ny']) && $_POST['ny'] == "Ny kunde") {
		$id=0;
		$kontonr=0;
		$bon=NULL;
	}
	if(isset($_POST['udskriv']) && $_POST['udskriv'] == "Udskriv") {
		$momssats=$momssats*1;
#		include("../includes/formfunk.php");
		pos_txt_print($id,$betaling,$modtaget,$indbetaling);
	}
	if ($fokus=="antal_ny" && $antal_ny!='0' && !$pris_ny) $antal_ny.="p";
	if ($fokus=="pris_ny" && $pris_ny!='f') $fokus="antal_ny";
	if ($fokus=="rabat_ny" && $pris_ny!='f') $fokus="antal_ny";
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
		db_modify ("update ordrer set konto_id='0', kontonr='',firmanavn='',addr1='',addr2='',postnr='',bynavn='',land='',betalingsdage='0',betalingsbet='Kontant',
			cvrnr='',ean='',institution='',email='',kontakt='',art='PO',valuta='DKK',valutakurs='100',kundeordnr='' where id = '$id'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where ordre_id='$id'",__FILE__ . " linje " . __LINE__);
		$varenr_ny=''; $antal_ny=''; $modtaget=''; $betaling=''; $fokus="varenr_ny"; 
	} elseif (substr($modtaget,-1)=='t') $betaling="";
	elseif (substr($modtaget,-1)=='d' && !$betaling) $betaling="creditcard";
	elseif (substr($modtaget,-1)=='c' && !$betaling) $betaling="kontant";
	elseif (substr($modtaget,-1)=='k' || $betaling == "konto") {
		if (substr($modtaget,0,1)=='+') $modtaget=$sum+usdecimal(substr($modtaget,1,strlen($modtaget)-1));
		elseif (!is_numeric(substr($modtaget,-1))) $modtaget=substr($modtaget,0,strlen($modtaget)-1);
		if (!$modtaget || !$kontonr) kontoopslag('PO',"",$fokus, $id,"","","");
	} elseif (isset($_POST['kontoopslag'])) {
		kontoopslag('PO',"","varenr_ny",$id,"","","");
	}
	if ($indbetaling) {	
			$tmp=trim(str_replace(".","",$indbetaling));
			$tmp=str_replace(",",".",$tmp);
			if (is_numeric($tmp)) {
				$indbetaling=usdecimal($indbetaling);
				$modtaget=usdecimal($modtaget);
		} 
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
	if ($betalt && is_numeric($betalt)) {
		$afslut="OK";
# echo "$betalt | $sum | $betaling<br>";
		if ($betalt < $sum || !$betaling) $afslut=NULL;
		if ($betaling=="ukendt") $afslut=NULL;
		if ($betaling2 && $betaling2=="ukendt") $afslut=NULL;
		if ($modtaget2 && (!$betaling2 || $betaling2=="ukendt")) $afslut=NULL;
		if ($afslut=="OK") {
#		if ($betalt >= $sum && $betaling && $betaling2 && $betaling!="ukendt" && $betaling2!="ukendt") {
# echo "$sum $betalt && $modtaget<br>";
#exit;
			 $svar=afslut($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling);
			if ($svar) print "<BODY onLoad=\"javascript:alert('$svar')\">";
 			else {
			  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">";
#				$id=0;
#				$kontonr=0;
#				$konto_id=0;
#				$indbetaling="";
			}
		}
	} else {
		if ($varenr_ny == "a") {
			$betaling="ukendt";
			$varenr_ny=NULL;
		} elseif ($antal_ny == "a") {
			$betaling="ukendt";
			$antal_ny=1;
		} elseif (strlen($antal_ny)>6) { # Så er der skannet et varenummer ind som antal
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
 				$linje_id=opret_ordrelinje($id,$varenr_ny,$antal_ny,'',usdecimal($pris_ny),$rabat_ny,'PO','','','0','on');	
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
if ($ny_bruger && $ny_bruger!=$brugernavn) skift_bruger($ny_bruger,$kode);
if (!isset($momssats)) $momssats=find_momssats($id,$kasse);
print "<table width=\"100%\" height=\"100%\" border=\"1\" cellpadding=\"0\" cellspacing=\"0\"><tbody>\n";
print "<tr><td valign=\"top\" height=\"100%\"><table width=\"100%\"><tbody>\n";
print "<form name=pos_ordre action=pos_ordre.php?id=$id method=post>\n";
if ($id && $betaling && $sum) $sum=betaling($id,$momssats,$betaling,$betaling2,$modtaget,$modtaget2);
elseif (!$indbetaling) list($varenr_ny,$pris_ny,$status)=split(chr(9),varescan($id,$momssats,$varenr_ny,$antal_ny,$pris_ny));
else indbetaling($id,$indbetaling,$modtaget);
if ($varenr_ny=='fejl') fejl($id,$status);
print "</tbody></table></td>\n";
print "<td valign=\"top\"><table width=\"100%\" height=\"100%\" border=\"0\"><tbody><td align=\"center\">\n";
print "<tr><td colspan=\"2\" valign=\"top\" height=\"1%\"><table width=\"100%\"><tbody>\n";
hoved($kasse);
# kassebeholdning($kasse);
print "</tbody></table></td></tr>\n";
#print "</FORM><form name=tastatur action=pos_ordre.php?id=$id method=post>\n";
tastatur($status);
print "</FORM>";
print "</tbody></table></td></tr>\n";
#print "<tr><td colspan=2 width=\"100%\" height=\"1%\"><table width=\"100%\" height=\"100%\" border=\"0\"><tbody>\n";
#footer ($kasse);
#print "</tbody></table></td></tr>\n";
# print "</tbody></table></td>\n";
print "</td></tbody></table></td></tr>\n";


function afslut ($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling) {

# "afslut($id,$betaling,$modtaget,$indbetaling)";
#exit;

	global $charset;
	global $bruger_id;
	global $kasse;
	
#	include("../includes/formfunk.php");

	$hurtigfakt='on';
	$moms=0;
	$dd=date("Y-m-d");
	$r=db_fetch_array(db_select("select konto_id,status,fakturanr,momssats from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats'];
	$status=$r['status'];
	$konto_id=$r['konto_id'];
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
				$folgevare[$x]=$r['folgevare'];
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
				db_modify("update ordrelinjer set posnr='$pos' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				$sum+=afrund($pris[$x]*$antal[$x],2);
	
#########################################################################	
				if ($folgevare[$x]) {
					$pos++;
					$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$folgevare[$x]'",__FILE__ . " linje " . __LINE__));
					$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
					$momsfri = $r2['box7'];
					db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$folgevare[$x]', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$r[salgspris]','0','$r[momsfri]','$pos','0')",__FILE__ . " linje " . __LINE__);
	#				print "<tr><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$r[beskrivelse]</td><td align=\"right\">".dkdecimal($r['salgspris'])."</td><td align=\"right\">".dkdecimal($antal[$x]*$r['salgspris'])."</td>";
					$sum+=afrund($antal[$x]*$r['salgspris'],2);
				}
				if ($rabatantal[$x]) {
					list($grupperabat,$rabattype)=split(";",grupperabat($rabatantal[$x],$rabatgruppe[$x])); 
					if ($grupperabat) {
						$pos++;
						$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
						$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
						$momsfri = $r2['box7'];
						db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$rabatvareid', '$r[varenr]', '$r[beskrivelse]', '$rabatantal[$x]','0','$grupperabat','0','$r[momsfri]','$pos','0')",__FILE__ . " linje " . __LINE__);
						$sum+=afrund($grupperabat*$rabatantal[$x],2);
					}
				} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
					$pos++;
					$r=db_fetch_array(db_select("select id,beskrivelse,salgspris,gruppe from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
					$r2 = db_fetch_array(db_select("select box6, box7 from grupper where art = 'VG' and kodenr = '$r[gruppe]'",__FILE__ . " linje " . __LINE__));
					$momsfri = $r2['box7'];
					db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,beskrivelse,antal,m_rabat,pris,kostpris,momsfri,posnr,projekt) values ('$id','$rabatvareid', '$r[varenr]', '$r[beskrivelse]', '$antal[$x]','0','$m_rabat[$x]','0','$r[momsfri]','$pos','0')",__FILE__ . " linje " . __LINE__);
					$sum+=afrund($m_rabat[$x]*$antal[$x],2);
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
		if ($konto_id) {
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
		db_modify("update ordrer set levdate = '$dd',fakturadate = '$dd',fakturanr = '$fakturanr',sum='$sum', moms='$moms', betalt='$betalt',status='2',felt_1='$betaling',felt_2='$modtaget',felt_3='$betaling2',felt_4='$modtaget2',felt_5='$kasse' where id='$id'",__FILE__ . " linje " . __LINE__);
		if (!$indbetaling) {
			$svar=levering($id,'on','','');
			if ($svar != 'OK') return ($svar);
			$svar=bogfor($id,'');
			if ($svar != 'OK') return ($svar);
		} else {
			$svar=bogfor_indbetaling($id,'');
			if ($svar != 'OK') return ($svar);
		}
		
	}
	pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling);
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
		if ($konto_id) {
			print "<tr><td><b>$kontonr</b>";
			if ($kundeordnr) print "&nbsp;&nbsp;&nbsp; Rekv.nr: $kundeordnr";
			print "</td></tr>\n";
			print "<tr><td colspan=\"2\"><b>$firmanavn</b></td></tr>\n";	
		}
		print "<tr><td width=\"50%\"><table width=\"100%\"><tbody>";
		print "<tr><td>Varenummer</td><td align=\"right\">Antal</td><td>Varenavn</td><td align=\"right\">Pris</td><td align=\"right\">Sum</td></tr>\n";
		print "<tr><td colspan=\"5\"><hr></td></tr>\n";
		$sum=vis_pos_linjer($id,$momssats,$status);
		if ($kontonr) $modtaget=$sum;
		if ($modtaget) $retur=$modtaget-$sum;
	}
	print "<input type=\"hidden\" name = \"fokus\" value=\"$fokus\">";
	print "<input type=\"hidden\" name = \"betaling\" value=\"$betaling\">";
	print "<input type=\"hidden\" name = \"sum\" value=\"$sum\">";
	if ($modtaget) $tmp=dkdecimal($modtaget);
	else $tmp="";
	if ($kontonr) print "<input type=\"hidden\" name=\"modtaget\" value=\"$tmp\">"; 
	else {
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
		if ($status >= 3) {
			$betaling=$r['felt_1'];
			$modtaget=$r['felt_2'];
			$betaling2=$r['felt_3'];
			$modtaget2=$r['felt_4'];
		}
	}
	if ($kontonr) {
		$r=db_fetch_array(db_select("select kreditmax from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$kreditmax=$r['kreditmax'];
		$r=db_fetch_array(db_select("select sum(amount) as saldo from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$saldo=$r['saldo'];
	}
	if ($varenr_ny) {
	  $varenr_ny=strtoupper($varenr_ny);
		if ($r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris,folgevare from varer where upper (stregkode) = '$varenr_ny' or upper(varenr) = '$varenr_ny'",__FILE__ . " linje " . __LINE__))) {
		  $varenr_ny=addslashes($r['varenr']);
		  $beskrivelse[0]=$r['beskrivelse'];
			$pris[0]=find_pris($varenr_ny)*1;
		  if ($pris[0]) $pris[0]=dkdecimal($pris[0]);
			else $pris[0]="";
			
			if ($fokus!="pris_ny" && $fokus!="rabat_ny") $fokus="antal_ny";
		} else return ("fejl".chr(9)."Varenr: $varenr_ny eksisterer ikke");
	} else $fokus="varenr_ny";
	if ($kontonr) {
		print "<tr><td><b>$kontonr</b></td><td colspan=4>";
		if ($status<3) print "Rekv.nr:&nbsp; <input type=\"text\" size=\"15\" name=\"kundeordnr\" value=\"$kundeordnr\">";
		elseif ($kundeordnr) print "&nbsp; Rekv.nr:&nbsp; $kundeordnr";
		print "</td></tr>\n";
		print "<tr><td colspan=\"2\"><b>$firmanavn</b></td></tr>\n";	
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
			print "<td width=\"7px\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"2\" name = \"antal_ny\" value=\"$antal_ny\"></td><td>$beskrivelse[0]</td>";
			if ($antal_ny) print "<td align=\"right\"><input class=\"inputbox\" type=\"text\" style=\"text-align:right\" size=\"4\" name = \"pris_ny\" value=\"$pris[0]\"></td>";
			else {
				print "<input type=hidden name=\"pris_ny\" value=\"$pris[0]\">";
				print "<td align=\"right\">$pris[0]</td>";
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
		if ($betaling != "Konto") print "<tr><td>Retur</td><td colspan=\"4\" align=\"right\"><b>$tmp</b></td></tr>"; 
	} elseif ($status >= 3) {
		$r=db_fetch_array($q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
		print "<tr><td>Saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($r[felt_3])."</td></tr>\n";
		$indbetaling=($r['felt_4']-$r['felt_3'])*-1;
		print "<tr><td>Indbetaling</td><td colspan=\"4\" align=\"right\">".dkdecimal($indbetaling)."</td></tr>\n";
		print "<tr><td>Betalt</td><td colspan=\"4\" align=\"right\">".dkdecimal($r[felt_2])."</td></tr>\n";
		$ny_saldo=$r['felt_4'];
		print "<tr><td>Ny saldo</td><td colspan=\"4\" align=\"right\">".dkdecimal($ny_saldo)."</td></tr>\n";
		$retur=$r['felt_2']+$r['felt_4']-$r['felt_3'];
		print "<tr><td>Retur</td><td  colspan=\"4\" align=\"right\">".dkdecimal($retur)."</td></tr>\n";
	}
	print "<tr><td colspan=\"5\" align=\"right\"><input  STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
	if ($konto_id && $status<3) {
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
	$fokus="indbetaling";
	$saldo=0;
	$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['konto_id'];
	$kontonr=$r['kontonr'];
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr_by=$r['postnr']." ".$r['bynavn'];
	$q=db_select("select * from openpost where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$saldo=$saldo+$r['amount'];
	}
	list($a,$b)=split(",",$indbetaling);
	if (!$indbetaling || !is_numeric($indbetaling)) {
		$indbetaling=$saldo;
		$modtaget='';
		$modtaget2='';
	}
	$retur=dkdecimal($modtaget+$modtaget2-$indbetaling);
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
	print "<tr><td><b>$firmanavn</b></td></tr>\n";
	print "<tr><td><b>$addr1</b></td></tr>\n";
	print "<tr><td><b>$addr2</b></td></tr>\n";
	print "<tr><td><b>$postnr_by</b></td></tr>\n";
	print "<tr><td colspan=2 width=400px><hr></td></tr>\n";
#	while (strlen($saldo) < 10) $saldo=" ".$saldo;
	print "<tr><td>Saldo</td><td align=\"right\">$saldo</td></tr>\n";
	print "<tr><td>Indbetaling</td><td align=\"right\"><input type=text size=8 style=\"text-align:right\" name=\"indbetaling\" value=\"$indbetaling\"></td></tr>\n";
	print "<tr><td>Betalt</td><td align=\"right\"><input type=text size=8 style=\"text-align:right\" name=\"modtaget\" value=\"$modtaget\"></td></tr>\n";
	print "<tr><td>Ny saldo</td><td align=\"right\">$ny_saldo</td></tr>\n";
	print "<tr><td>Retur</td><td align=\"right\">$retur</td></tr>\n";
  print "<td colspan=\"5\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
}

function vis_pos_linjer($id,$momssats,$status) {
		
	global $varelinjer;
	$id=$id*1;	
	$q=db_select("select * from ordrelinjer where ordre_id = '$id' and posnr > 0 order by rabatgruppe, id desc",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
		$x++;
		$vare_id[$x]=$r['id'];
		$varenr[$x]=$r['varenr'];
		$beskrivelse[$x]=addslashes($r['beskrivelse']);
		$pris[$x]=$r['pris'];
		$kostpris[$x]=$r['kostpris'];
		$antal[$x]=$r['antal'];
		$folgevare[$x]=$r['folgevare'];
		$rabatgruppe[$x]=$r['rabatgruppe'];
		$rabat[$x]=$r['rabat'];
		$m_rabat[$x]=$r['m_rabat']*-1;
		$momsfri[$x]=trim($r['momsfri']);
		$varemomssats[$x]=trim($r['momssats']);
		if ($rabatgruppe[$x]) {
			if ($rabatgruppe[$x]==$rabatgruppe[$x-1]) {
				$rabatantal[$x]=$antal[$x]+$rabatantal[$x-1];
				$rabatantal[$x-1]=0;
			} else $rabatantal[$x]=$antal[$x];
		} else $rabatantal[$x]=0;
		if ($varemomssats & $momsfri[$x]!='on') {
			$pris[$x]=$pris[$x]+$pris[$x]/100*$momssats;
			if ($m_rabat[$x]) $m_rabat[$x]=$m_rabat[$x]+$m_rabat[$x]/100*$momssats;
		}	
	}
	$varelinjer=$x;
	
	for ($x=1;$x<=$varelinjer;$x++) {
		print "<tr><td>$varenr[$x]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$beskrivelse[$x]</td><td align=\"right\">".dkdecimal($pris[$x])."</td><td align=\"right\">".dkdecimal($pris[$x]*$antal[$x])."</td>";
		$sum+=afrund($pris[$x]*$antal[$x],2);
		if ($rabat[$x]) {
			$tmp=afrund($pris[$x]*$rabat[$x]/-100,2);
			print "<tr><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$rabat[$x]% rabat</td><td align=\"right\">".dkdecimal($tmp)."</td><td align=\"right\">".dkdecimal($tmp*$antal[$x])."</td>";
			$sum+=afrund($tmp*$antal[$x],2);
		}
		if ($folgevare[$x] && $status < 3) {
			$r=db_fetch_array(db_select("select varenr,beskrivelse,salgspris from varer where id = '$folgevare[$x]' order by id desc",__FILE__ . " linje " . __LINE__));
			print "<tr><td>$r[varenr]</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>$r[beskrivelse]</td><td align=\"right\">".dkdecimal($r['salgspris'])."</td><td align=\"right\">".dkdecimal($antal[$x]*$r['salgspris'])."</td>";
			$sum+=afrund($antal[$x]*$r['salgspris'],2);
		}
		if ($rabatantal[$x]) {
			list($grupperabat,$rabattype)=split(";",grupperabat($rabatantal[$x],$rabatgruppe[$x])); 
			if ($grupperabat) {
				print "<tr><td>rabat</td><td align=\"right\">".dkdecimal($rabatantal[$x])."</td><td>Rabat</td><td align=\"right\">".dkdecimal($grupperabat)."</td><td align=\"right\">".dkdecimal($grupperabat*$rabatantal[$x])."</td>";
				$sum+=afrund($grupperabat*$rabatantal[$x],2);
			}
		} elseif ($m_rabat[$x] && !$rabatgruppe[$x]) {
			print "<tr><td>rabat</td><td align=\"right\">".dkdecimal($antal[$x])."</td><td>Rabat</td><td align=\"right\">".dkdecimal($m_rabat[$x])."</td><td align=\"right\">".dkdecimal($m_rabat[$x]*$antal[$x])."</td>";
			$sum+=afrund($m_rabat[$x]*$antal[$x],2);
		}
	}
	print "<tr><td colspan=\"5\"><hr></td></tr>\n";
	if ($sum) print "<tr><td>I alt</td><td align=\"right\"></td><td></td><td align=\"right\"></td><td align=\"right\">".dkdecimal($sum)."</td></tr>\n";
return($sum);

}

function pos_txt_print($id,$betaling,$betaling2,$modtaget,$modtaget2,$indbetaling) {
	
	global $db;
	global $db_id;
	global $brugernavn;
	global $bruger_id;
	global $momssats;
	global $db_encode;
	
 	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF8") $FromCharset = "UTF-8"; 
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865"; 
	$convert = new ConvertCharset();

	$tid=date("H:i");
	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$r=db_fetch_array(db_select("select * from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
	$firmanavn=$r['firmanavn'];
	$addr1=$r['addr1'];
	$addr2=$r['addr2'];
	$postnr=$r['postnr'];
	$bynavn=$r['bynavn'];
	$belob="beløb";
	if ($firmanavn) $firmanavn = $convert ->Convert($firmanavn, $FromCharset, $ToCharset); 
	if ($addr1) $addr1 = $convert ->Convert($addr1, $FromCharset, $ToCharset); 
	if ($addr2) $addr2 = $convert ->Convert($addr2, $FromCharset, $ToCharset); 
	if ($bynavn) $bynavn = $convert ->Convert($bynavn, $FromCharset, $ToCharset); 
	
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
	if (!$betaling) $betaling="Betalt";
	if ($ref) {
		if ($r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			if ($r=db_fetch_array(db_select("select navn from ansatte where id = '$r[ansat_id]'",__FILE__ . " linje " . __LINE__))) $ref=$r['navn'];
	  }
	}
	if ($kundenavn) $kundenavn = $convert ->Convert($kundenavn, $FromCharset, $ToCharset); 
	if ($kundeaddr1) $kundeaddr1 = $convert ->Convert($kundeaddr1, $FromCharset, $ToCharset); 
	if ($kundeby) $kundeby = $convert ->Convert($kundeby, $FromCharset, $ToCharset); 
	if ($ref) $ref = $convert ->Convert($ref, $FromCharset, $ToCharset); 
	
	$x=0;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id' and posnr > 0 order by id desc",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$x++;
		if ($r['momsfri']!='on') $pris=$r['pris']+$r['pris']/100*$momssats;
		else $pris=$r['pris'];
		if (strtoupper($r['varenr'])=='INDBETALING') {
			$pris=$pris*-1;
			$sum=$sum*-1;
		}
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
		$linjeantal=$x;
	}
	$sum+=$moms;
	if ($konto_id) {
		if (!$x) $indbetaling=$sum;
		$gl_saldo=dkdecimal($betaling2);
		$ny_saldo=dkdecimal($modtaget2);
	}
	$retur=$betalt-$sum;
	$dkksum=dkdecimal($sum);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
	}
	$retur=dkdecimal($retur);
	while(strlen($retur)<9){
		$retur=" ".$retur;
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
	$modtaget=dkdecimal($modtaget);
	while(strlen($modtaget)<9){
		$modtaget=" ".$modtaget;
	}
	if ($modtaget2) {
		$modtaget2=dkdecimal($modtaget2);
		while(strlen($modtaget2)<9){
			$modtaget2=" ".$modtaget2;
		}
	}
	$dkksum=dkdecimal($sum);
	while(strlen($dkksum)<9){
		$dkksum=" ".$dkksum;
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
		else $r=db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr'",__FILE__ . " linje " . __LINE__));
		if ($konto_id=$r['id']) {
		  db_modify ("update ordrer set konto_id='$konto_id', kontonr='$r[kontonr]',firmanavn='$r[firmanavn]',addr1='$r[addr1]',addr2='$r[addr2]',
					postnr='$r[postnr]',bynavn='$r[bynavn]',land='$r[land]',betalingsdage='$r[betalingsdage]',betalingsbet='$r[betalingsbet]',cvrnr='$r[cvrnr]',
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
	print "<input type=\"password\" size=\"10\" name=\"kode\" value=\"        \">";
	print " | Bon <input type=\"text\" name=\"bon\" size=\"6\" value=\"$bon\"><br><hr></td>";
	print "<td width=\"4%\" align=\"right\" valign=\"top\"><a href=../includes/luk.php><div class=\"luk\"></div></a></td></tr>\n";
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
	global $fokus;
	global $sum;
	global $modtaget;
	global $modtaget2;
	global $kontonr;
	global $varelinjer;
	global $varenr_ny;
	global $indbetaling;

	$sum=afrund($sum,2);
	$modtaget=afrund($modtaget,2);
	$modtaget2=afrund($modtaget2,2);
	
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
		print "<TD><INPUT TYPE=\"submit\" $stil NAME=\"afslut\"VALUE=\"Afslut\" OnClick=\"pos_ordre.$fokus.value += 'a';pos_ordre.$fokus.focus();\"></TD>";
		print "<TD onclick=\"return confirm('Slet alt og start forfra')\"><INPUT TYPE=\"submit\" $stil NAME=\"forfra\"VALUE=\"Forfra\" OnClick=\"pos_ordre.$fokus.value += 'f';pos_ordre.$fokus.focus();\"></TD>";
		if ($fokus=='modtaget') {
			print "<TD onclick=\"return confirm('Tilbage til varescanning')\"><INPUT TYPE=\"submit\" $stil NAME=\"tilbage\"VALUE=\"Tilbage\" OnClick=\"pos_ordre.$fokus.value += 't';pos_ordre.$fokus.focus();\"></TD>";
			print "</TR><TR>";
			print "<TD COLSPAN=3></TD>";
		}
		print "<TR><TD COLSPAN=4><HR></TD></tr>\n";
		$stil2="STYLE=\"width: 9.5em;height: 2em;font-size:150%;\"";
		print "<TR>";
# echo "$fokus=='modtaget' && $modtaget>=$sum && !$indbetaling<br>";
		if ($fokus=='varenr_ny') print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"varer\"VALUE=\"Varer\" OnClick=\"pos_ordre.$fokus.value += 'v';pos_ordre.$fokus.focus();\"></TD>";
		elseif ($fokus=='antal_ny') {
			print "<TD COLSPAN=\"1\"><INPUT TYPE=\"submit\" $stil NAME=\"pris\"VALUE=\"Pris\" OnClick=\"pos_ordre.$fokus.value += 'p';pos_ordre.$fokus.focus();\"></TD>";
			print "<TD COLSPAN=\"1\"><INPUT TYPE=\"submit\" $stil NAME=\"rabat\"VALUE=\"Rabat\" OnClick=\"pos_ordre.$fokus.value += 'r';pos_ordre.$fokus.focus();\"></TD>";
		} elseif ($fokus=='modtaget' && $modtaget>=$sum && !$indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD>";		
		} elseif ($fokus=='modtaget2' && $modtaget+$modtaget2>=$sum && !$indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling2\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD>";		
		}	elseif ($indbetaling && $modtaget >= $indbetaling) {
			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Kontant\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD>";		
		} else print "<TD colspan=2></TD>";
		print "<TD colspan=2><INPUT TYPE=\"submit\" $stil2 NAME=\"OK\"  VALUE=\"Enter\"></TD></tr>\n";
		if (!$varenr_ny && !$indbetaling) print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"kontoopslag\" VALUE=\"Kontoopslag\"></TD></tr>\n";		
		if ((($fokus=='modtaget' || $fokus=='modtaget2') && !$kontonr) || ($indbetaling && $modtaget==$indbetaling && $kontonr)) {
			$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
			$kortantal=$r['box4']*1;
			$korttyper=explode(chr(9),$r['box5']);
			for($x=0;$x<$kortantal;$x++) {
				if ($fokus=='modtaget2') $tmp="betaling2";
				else $tmp="betaling";
#echo "beta $tmp<br>";
				print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"$korttyper[$x]\" OnClick=\"pos_ordre.$fokus.value += 'd';pos_ordre.$fokus.focus();\"></TD></tr>\n";		
			}
			if (!$indbetaling) {
				if ($fokus=='modtaget2') $tmp="betaling2";
				else $tmp="betaling";
				print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=$tmp VALUE=\"Kontant\" OnClick=\"pos_ordre.$fokus.value += 'c';pos_ordre.$fokus.focus();\"></TD></tr>\n";		
			}
#			print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"betaling\" VALUE=\"Konto\" OnClick=\"pos_ordre.$fokus.value += 'k';pos_ordre.$fokus.focus();\"></TD></tr>\n";		
		} elseif ($id && $kontonr && !$varelinjer && !$indbetaling) 
		print "<TR><TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"indbetaling\" VALUE=\"Indbetaling\" OnClick=\"pos_ordre.$fokus.value += 'i';pos_ordre.$fokus.focus();\"></TD>";		
	} else {
#		print "<input type=\"hidden\" name=\"bon\" value = \"\">";
		$stil2="STYLE=\"width: 9.5em;height: 2em;font-size:150%;\"";
		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"udskriv\"VALUE=\"Udskriv\"></TD>";
		print "<TD COLSPAN=\"2\"><INPUT TYPE=\"submit\" $stil2 NAME=\"ny\"VALUE=\"Ny kunde\"></TD>";
	}
	print "</tr>\n";
	print "</TBODY></TABLE></TD></tr>\n";
}
function fejl ($id,$fejltekst) {
  print "<BODY onLoad=\"javascript:alert('$fejltekst')\">";
  print "<meta http-equiv=\"refresh\" content=\"0;URL=$php_self\">";
  
}

function kassebeholdning ($kasse) {
	global $bruger_id;
	global $db;
	global $db_encode;
	$dd=date("Y-m-d");
	$tid=date("H:m");

 	include("../includes/ConvertCharset.class.php");
	if ($db_encode=="UTF-8") $FromCharset = "UTF-8"; 
	else $FromCharset = "iso-8859-15";
	$ToCharset = "cp865"; 
	$convert = new ConvertCharset();
	
	$pfnavn="../temp/".$db."/".$bruger_id.".txt";
	$fp=fopen("$pfnavn","w");
	$tmp = $convert ->Convert('KASSEOPGØRELSE', $FromCharset, $ToCharset);
	
	fwrite($fp,"\n\n$tmp\n\n");
	fwrite($fp,"Den $dd kl. $tid\n");
	fwrite($fp,"Kasse nr: $kasse\n");

	$kasse--;
	$r = db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$kassekonti=explode(chr(9),$r['box2']);
	$kortantal=$r['box4']*1;
	$kortnavn=explode(chr(9),$r['box5']);
	$kortkonto=explode(chr(9),$r['box6']);
	
	$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where kontonr = '$kassekonti[$kasse]'",__FILE__ . " linje " . __LINE__));
	$byttepenge=$r['debet']-$r['kredit'];
	$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kassekonti[$kasse]'",__FILE__ . " linje " . __LINE__));
	$tilgang=$r['debet']-$r['kredit'];
	$kassesum=dkdecimal($byttepenge+$tilgang);
	$byttepenge=dkdecimal($byttepenge);
	$tilgang=dkdecimal($tilgang);
	
	fwrite($fp,"Beholdning primo: $byttepenge\n\n");
	fwrite($fp,"Dagens indbetalinger $tilgang\n\n");
	fwrite($fp,"Beholdning ultimo $kassesum\n\n");
	
	if ($kortantal) {
	fwrite($fp,"\n\nSalg paa kort\n\n");
		for ($x=0;$x<$kortantal;$x++) {
			if ($kortkonto[$x]) {
				$r = db_fetch_array(db_select("select sum(debet) as debet,sum(kredit) as kredit from transaktioner where transdate = '$dd' and kontonr = '$kortkonto[$x]'",__FILE__ . " linje " . __LINE__));
				$tmp=dkdecimal($r['debet']-$r['kredit']);
				fwrite($fp,"$kortnavn[$x]: $tmp\n\n");
			}	
		}
	}
	fwrite($fp,"\n\n\n");
	
	fclose($fp);
	$tmp="/temp/".$db."/".$bruger_id.".txt";
	print "<BODY onLoad=\"JavaScript:window.open('http://localhost/saldiprint.php?printfil=$tmp&bruger_id=$bruger_id' , '' , '$jsvars');\">";
}

if (!$varenr_ny && $fokus!='modtaget' && $fokus!='modtaget2' && $fokus!='indbetaling') $fokus="varenr_ny";
#echo "fokus $fokus<br>";
?>	
</body></html>
<script language="javascript">
document.pos_ordre.<?php echo $fokus?>.focus();
</script>
