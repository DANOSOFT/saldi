<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/ordre.php --- lap 4.0.7 --- 2022.11.06 ---
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
//
// Copyright (c) 2003-2022 saldi.dk aps
// ----------------------------------------------------------------------

// 20120814 søg 20120814
// 20130618 Tilføjet udskrivning af indkøbsforslag, rekvisition & lev-faktura
// 20130624 Rettet bug - Blank side ved kreditering...
// 20130816 Rettet bug - Blank side ved kopiering... 20130816
// 20130919 Alle forekomster af round ændret til afrund
// 20140319 addslashes erstattet med db_escape_string
// 20141005 Div i forbindelse med omvendt betalingspligt, samt nogle generelle ændringer således at varereturnering nu bogføres
//	som negativt køb og ikke som salg.
// 20141104 Varemomssats indsættes ved oprettelse af ordrelinjer, søg varemomssats.
// 20141107 Momsats var ikke sat, så vǘaremomssats kunne ikke sættes.
// 20150209 Ved negativt lager var det ikke muligt at hjemkøbe mindre en det antal det manglede på lager.  20150209
// 20150415	Omvbet på ordrelinje forsvandt ved gem # 201504015
// 20170505 Mange småforbedringer samt tilføjelse af afdeling og lager.
// 20180305 htmlentities foran beskrivelse og varenr. 20180305 
// 20200827 PHR Added protection against delete if items recieved. 20200827
// 20201002	PHR Orderline will no be created if no id.
// 20201021 changed from '=substr($fokus,4)' to '=0' as $focus is 'varenr'?;
// 20210514 LOE	These texts were translated but not entered here previously
// 20210716 LOE Translation of title tags , and general fixing of some bugs
// 20211125 PHR Added link to document and done some cleanup 
// 20211201 PHR error in check for item group corrected. 
// 20211201 PHR $_GET['vare_id'] removed from 120 as it is in line 125
// 20220124 PHR	several translation issues rgarding submit.
// 20220124 PHR replaced 'vareOpslag' with 'lookup' everywhere
// 20220331 PHR changed various if statements from 'Kopi' & 'Kred' to 'copy' & 'credit' 
// 20220627 MSC - Implementing new design
// 20220629 MSC - Implementing new design
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20220124 MLH added debitor lookup funcionality
// 20220124 MLH added kundeordnr / Rekv.nr.
// 20220124 MLH added udskriv_til, email and mail_fakt
// 20230105 MLH added mail_text and mail_subj

@session_start();
$s_id=session_id();

?>
	<script type="text/javascript">
	<!--
	var linje_id=0;
	var antal=0;
	function serienummer(linje_id, antal) {
		window.open("serienummer.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
	function batch(linje_id, antal) {
		window.open("batch.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
	}
//		 -->
	</script>

	<script type="text/javascript">
	<!--
	function fejltekst(tekst) {
		alert(tekst);
		window.location.replace("../includes/luk.php?");
	}
	-->
	</script>
<?php
$title="Kreditorordre";
$css="../css/std.css";
$modulnr=7;

$id=$konto_id=$debitor_id=$projekt=0;
$hurtigfakt=$krediteret=$labelprint=$lev_adr=$momssum=$negativt_lager=$sort=$submit=NULL;
$antal[0]=$beskrivelse[0]=$enhed[0]=$lev_varenr[0]=$pris[0]=$rabat[0]=NULL;
$batch=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$returside = if_isset($_GET['returside']);

#if ($popup) $returside="../includes/luk.php";
#elseif (!$returside) $returside="../kreditor/ordreliste.php";
if (!$returside || $returside=="ordreliste.php") $returside="../kreditor/ordreliste.php";

$tidspkt=date("U");
print "<script language=\"javascript\" type=\"text/javascript\" src=\"../javascript/confirmclose.js\"></script>";

if (isset($_GET['tjek']) && $tjek=$_GET['tjek'])	{
	$qtxt = "select tidspkt, hvem from ordrer where status < 3 and id = '$tjek' and hvem != '$brugernavn'";
	if ($row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$orderTime  = $row['tidspkt'];
		if (!$orderTime) $orderTime = 0; 
		if ( $tidspkt-($orderTime) < 3600 && $row['hvem'] && $row['hvem'] != $brugernavn ) {
			print "<BODY onLoad=\"javascript:alert('Ordren er i brug af $row[hvem]')\">";
			if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}
		else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
	}
	else {db_modify("update ordrer set hvem = '$brugernavn', tidspkt='$tidspkt' where id = '$tjek'",__FILE__ . " linje " . __LINE__);}
}

$qtxt = "select box4 from grupper where art = 'DIV' and kodenr = '2'";
if ($r=db_fetch_array(db_SELECT($qtxt,__FILE__ . " linje " . __LINE__))) $hurtigfakt=$r['box4'];
$qtxt = "select box9 from grupper where art = 'DIV' and kodenr = '3'";
if ($r=db_fetch_array(db_SELECT($qtxt,__FILE__ . " linje " . __LINE__))) $negativt_lager=$r['box9'];
$qtxt = "select box1 from grupper where art = 'LABEL'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $labelprint=$r['box1'];

if(isset($_GET['id']))       $id=$_GET['id'];  #20210716 This is used to correct undefined index error on the former code
if(isset($_GET['vis']))      $vis=$_GET['vis'];
if(isset($_GET['sort']))     $_GET['sort'];
if(isset($_GET['fokus']))    $fokus=$_GET['fokus'];
if(isset($_GET['funktion'])) $submit=$_GET['funktion'];
if(isset($_GET['kontakt']))  $kontakt=$_GET['kontakt'];

if     (isset($_POST['copy']) && $_POST['copy'])       $submit = 'copy';
elseif (isset($_POST['credit']) && $_POST['credit'])   $submit = 'credit';
elseif (isset($_POST['lookup']) && $_POST['lookup'])   $submit = 'lookup';
elseif (isset($_POST['postNow']) && $_POST['postNow']) $submit = 'postNow';
elseif (isset($_POST['print']) && $_POST['print'])     $submit = 'print';
elseif (isset($_POST['receive']) && $_POST['receive']) $submit = 'receive';
elseif (isset($_POST['return']) && $_POST['return'])   $submit = 'receive';
elseif (isset($_POST['save']) && $_POST['save'])       $submit = 'save';
elseif (isset($_POST['split']) && $_POST['split'])     $submit = 'split';


$lager=if_isset($_GET['lager']);
$konto_id=if_isset($_GET['konto_id']);
if (!$id && $konto_id) $id = indset_konto(0, $konto_id);
if ((!empty($kontakt))&&($id)) db_modify("update ordrer set kontakt='$kontakt' where id=$id",__FILE__ . " linje " . __LINE__);

$debitor_id=if_isset($_GET['debitor_id']); #20221104
if ($id && $debitor_id) $id = indset_debitor($id, $debitor_id); #20221104

if(isset($_GET['vare_id']) && $_GET['vare_id']) { #20210716 
	$vare_id[0]=db_escape_string($_GET['vare_id']);
	$linjenr=0; # 20201021 changed from substr($fokus,4)*1;
	if ($id) {
		$query = db_select("select konto_id, kontonr, status,omvbet from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$omlev=$row['omvbet'];
#cho "$omlev=$row[omvbet]<br>";
		if ($row['status']>2) {
			print "Hmmm - har du brugt browserens opdater eller tilbageknap???";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
			exit;
		}
		$konto_id=$row['konto_id'];
		$query = db_select("select posnr from ordrelinjer where ordre_id = '$id' order by posnr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) $posnr[0]=$row['posnr']+1;
		else $posnr[0]=1;
	}
	else $posnr[0]=1;

	$query = db_select("select * from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$varenr[0]    = $row['varenr'];
		$serienr[0]   = $row['serienr'];
		$samlevare[0] = $row['samlevare'];
		if (!$beskrivelse[0]) $beskrivelse[0] = $row['beskrivelse'];
		if (!$enhed[0])       $enhed[0]       = $row['enhed'];
		if (!$pris[0])        $pris[0]        = $row['kostpris'];
		if (!$rabat)         $rabat           = $row['rabat'];
	}
	if ((!$pris[0] || !$lev_varenr[0]) && $vare_id[0] && $konto_id) {
		$query = db_select("select * from vare_lev where vare_id = '$vare_id[0]' and lev_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$pris[0]=$row['kostpris'];
			$lev_varenr[0]=$row['lev_varenr'];
		}
	}
	if (!$id) $id = indset_konto($id, $konto_id);
	$pris[0]=$pris[0]*1;
	if(!$antal[0]) $antal[0]=1;
	if (!$rabat) $rabat=0;

	$r=db_fetch_array(db_select("select momssats,valuta,ordredate from ordrer where id='$id'",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']; #20141107
	$valuta=$r['valuta'];
	$ordredate=$r['ordredate'];
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$pris[0]=$pris[0]*100/$r['kurs'];
		} else {
			$tmp = dkdato($ordredate);
			print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
		}
	}

	if ($linjenr=='0' && $konto_id) { # 20201002
		if ($serienr[0]) $antal[0]=afrund($antal[0],0);
		if ($vare_id[0]) {
			if ($r1 = db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
				$r2 = db_fetch_array(db_select("select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
				$bogfkto[0] = $r2['box4'];
				$omvare[0] = $r2['box6'];
				$momsfri[0] = $r2['box7'];
				$lagerfort[0] = $r2['box8'];
			}
			# if (($omvare[0]!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
			if ($bogfkto[0] && !$momsfri[0]) {
				$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto[0]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
				if ($tmp=trim($r2['moms'])) { # f.eks S3
					$tmp=substr($tmp,1); #f.eks 3
					$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
					if ($r2['box2']) $varemomssats[0]=$r2['box2']*1;
				}	else $varemomssats[0]=$momssats;
			} elseif (!$momsfri[0]) $varemomssats[0]=$momssats;
			else $varemomssats[0]=$momssats;
			if ($samlevare[0]) { 
				samlevare($id,$art,$vare_id[0],$antal[0]);
			} else {
				($omlev && $omvare[0])?$omvbet[0]='on':$omvbet[0]='';
				$lager*=1;
				$qtxt = "insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,pris,lev_varenr,serienr,antal,momsfri,samlevare,omvbet,momssats,lager) values ";
				$qtxt.= "('$id','$posnr[0]','".db_escape_string($varenr[0])."','$vare_id[0]','".db_escape_string($beskrivelse[0])."',";
				$qtxt.= "'$enhed[0]','$pris[0]','".db_escape_string($lev_varenr[0])."','".db_escape_string($serienr[0])."','$antal[0]',";
				$qtxt.= "'$momsfri[0]','".db_escape_string($samlevare[0])."','$omvbet[0]','$varemomssats[0]','$lager')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
$status = null;
if(isset($_POST['status'])) $status=$_POST['status'];
if ((is_numeric($status) && $status<3) || (isset($_POST['credit'])) || isset($_POST['copy'])) { #20130816
	if (isset ($POST['credit']) && $POST['credit']) $submit = 'credit';
	elseif (isset ($POST['copy']) && $POST['copy']) $submit = 'copy';
	$fokus 		 = if_isset($_POST['fokus']);
	$id          = if_isset($_POST['id']);
	$ordrenr     = if_isset($_POST['ordrenr']);
	$kred_ord_id = if_isset($_POST['kred_ord_id']);
	$art         = if_isset($_POST['art']);
	$konto_id    = if_isset($_POST['konto_id']);
	$kontonr     = if_isset($_POST['kontonr']);
	$firmanavn   = db_escape_string(trim(if_isset($_POST['firmanavn'])));
	$addr1       = db_escape_string(trim(if_isset($_POST['addr1'])));
	$addr2       = db_escape_string(trim(if_isset($_POST['addr2'])));
	$postnr      = trim(if_isset($_POST['postnr']));
	$bynavn      = trim(if_isset($_POST['bynavn']));
	if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
	$land        = db_escape_string(trim(if_isset($_POST['land'])));
	$kontakt     = db_escape_string(trim(if_isset($_POST['kontakt'])));
	$lev_navn    = db_escape_string(trim(if_isset($_POST['lev_navn'])));
	$lev_addr1   = db_escape_string(trim(if_isset($_POST['lev_addr1'])));
	$lev_addr2   = db_escape_string(trim(if_isset($_POST['lev_addr2'])));
	$lev_postnr  = if_isset($_POST['lev_postnr']);
	$lev_bynavn  = trim(if_isset($_POST['lev_bynavn']));
	if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
	$lev_kontakt = db_escape_string(trim(if_isset($_POST['lev_kontakt'])));
	$kundeordnr  = db_escape_string(trim(if_isset($_POST['kundeordnr'])));
	$ordredate   = usdate(if_isset($_POST['ordredato']));
	$levdate     = usdate(trim(if_isset($_POST['levdato'])));
	$email       = db_escape_string(trim(if_isset($_POST['email'])));
	$udskriv_til = trim(if_isset($_POST['udskriv_til']));
	$mail_subj=db_escape_string(if_isset($_POST['mail_subj'])); #20230105
	$mail_text=db_escape_string(str_replace("\n","<br>",if_isset($_POST['mail_text']))); #20230105
	$cvrnr       = trim(if_isset($_POST['cvrnr']));
	$betalingsbet  = if_isset($_POST['betalingsbet']);
	$betalingsdage = if_isset($_POST['betalingsdage']);
	$valuta      = if_isset($_POST['valuta']);
	$projekt     = if_isset($_POST['projekt']);
	$lev_adr     = trim(if_isset($_POST['lev_adr']));
	$sum         = if_isset($_POST['sum']);
	$linjeantal  = if_isset($_POST['linjeantal']);
	$linje_id    = if_isset($_POST['linje_id']);
	$kred_linje_id = if_isset($_POST['kred_linje_id']);
	$vare_id     = if_isset($_POST['vare_id']);
	$posnr       = if_isset($_POST['posnr']);
	$status      = if_isset($_POST['status']);
	$godkend     = if_isset($_POST['godkend']);
	$kreditnota  = if_isset($_POST['kreditnota']);
	$ref         = trim(if_isset($_POST['ref']));
	$afd         = trim(if_isset($_POST['afd']));
	$lager       = trim(if_isset($_POST['lager']));
	$fakturanr = db_escape_string(trim(if_isset($_POST['fakturanr'])));
	$momssats    = if_isset($_POST['momssats']);
	$lev_varenr  = if_isset($_POST['lev_varenr']);
	$momsfri     = if_isset($_POST['momsfri']);
	$serienr     = if_isset($_POST['serienr']);
	$omvbet      = if_isset($_POST['omvbet']);
	if (!$afd) $afd = 0;
	$varemomssats= if_isset($_POST['$varemomssats']); #20141106
	if (!$betalingsdage)  $betalingsdage = 0;
	if (!$momssats)       $momssats = 0;
	$momssats = usdecimal($momssats);
	if(!isset($sletslut)){ $sletslut=null;}
	if(!isset($sletstart)){ $sletstart=null;}   #20210716
	if(!isset($tidl_lev)){ $tidl_lev=null;}
	if(!isset($leveret)){ $leveret=null;}
	if(!isset($notes)){ $notes=null;}
	if(!isset($afd_nr)){ $afd_nr=null;}

	if ($kred_ord_id) {
		$r=db_fetch_array(db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__));
		$kred_ord_nr=$r['ordrenr'];
	}
	if ($valuta && $valuta!='DKK') {
		$qtxt = "select kodenr from grupper where art = 'VK' and box1 = '$valuta'";
		$r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$valutakode = $r['kodenr'];
		$ordredato = dkdato($ordredate);
		$qtxt = "select kurs from valuta where valdate <= '$ordredate' and gruppe = '$valutakode' ";
		$qtxt.= "order by valuta.valdate limit 1";
		if ($r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs']*1; #20120814 *1 + naeste linje tilfojet.
			if (!$valutakurs) print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredato')\">";
		} else {
			print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredato')\">";
		}
	} else $valutakurs=100;
		if ($momssats > 0 && $konto_id) {
		$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
		$gruppe=$r['gruppe']*1;
		$r = db_fetch_array(db_select("select box1,box2,box9 from grupper where art = 'KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__));
		$box1=substr(trim($r['box1']),0,1);
		(trim($r['box9']))?$omlev='on':$omlev='';
		if (!$box1 || $box1=='E') {
			$momssats=0;	# Erhvervelsesmoms beregnes automatisk ved bogforing.
			if ($box1) $tekst = "Erhvervelsesmoms beregnes automatisk ved bogf&oslash;ring.";
			else $tekst = "Leverand&oslash;rgruppen er ikke tilknyttet en momsgruppe";
		#	print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		} elseif (!$box1 || $box1=='Y') {
			$momssats=0;	# Ydelsesmoms beregnes automatisk ved bogforing.
			if ($box1) $tekst = "Ydelsesmoms beregnes automatisk ved bogf&oslash;ring.<br>";
			else $tekst = "Leverand&oslash;rgruppen er ikke tilknyttet en momsgruppe";
			print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		}
	}

		
	if (isset($_POST['delete']) && $_POST['delete'])	{
		$qtxt="select id from batch_kob where ordre_id='$id' limit 1";
		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20200827
			alert ('der ér modtaget varer på denne ordre, slet afbrudt');
		} else {	
			$qtxt="select dokument from ordrer where id='$id'"; # 20211121
			if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)) && file_exists("../bilag/$db/scan/$r[dokument]")) { 
				unlink ("../bilag/$db/scan/$r[dokument]");
			}
			db_modify("delete from ordrelinjer where ordre_id=$id",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}
	}

	transaktion("begin");

	for ($x=0;$x<=$linjeantal;$x++) {
		$solgt[$x]=0;
		$y="posn".$x;
		$posnr_ny[$x]=if_isset($_POST[$y]);
		if ($posnr_ny[$x] && $posnr_ny[$x]!="-" && $posnr_ny[$x]!="->" && $posnr_ny[$x]!="<-" && !strpos($posnr_ny[$x],'+')) {
			$posnr_ny[$x]=afrund(100*str_replace(",",".",$posnr_ny[$x]),0);
		}
		$y="vare".$x;
		$varenr[$x]=db_escape_string(if_isset($_POST[$y]));
		$y="anta".$x;
		$antal[$x]=if_isset($_POST[$y]);
		if ($antal[$x]){
			$antal[$x]=usdecimal($antal[$x],2);
			if ($art=='KK') $antal[$x]=$antal[$x]*-1;
		}
		$y="leve".$x;
		$leveres[$x]=if_isset($_POST[$y]);
		if ($leveres[$x]){
			$leveres[$x]=usdecimal($leveres[$x],2);
			if ($art=='KK') $leveres[$x]=$leveres[$x]*-1;
		}
		$y="beskrivelse".$x;
		$beskrivelse[$x]=db_escape_string(if_isset($_POST[$y]));
		$y="pris".$x;
		$pris[$x]=usdecimal(if_isset($_POST[$y]),2);
		$y="raba".$x;
		$rabat[$x]=usdecimal(if_isset($_POST[$y]),2);
		if ($x>0 && !$rabat[$x]) $rabat=0;
#			$y="ialt".$x;
#			$ialt[$x]=if_isset($_POST[$y]);
		if ($godkend == "on" && $status==0) $leveres[$x]=$antal[$x];
		if (!$sletslut && $posnr_ny[$x]=="->") $sletstart=$x;
		if ($sletstart && $posnr_ny[$x]=="<-") $sletslut=$x;
#			$projekt[$x]=$projekt[$x];
	}
	if ($sletstart && $sletslut && $sletstart<$sletslut) {
		for ($x=$sletstart; $x<=$sletslut; $x++) {
			$posnr_ny[$x]="-";
		}
	}
	if (isset($_POST['moveOrderLines']) && $_POST['moveOrderLines']) {
		include("orderIncludes/moveOrderLines.php");
	}

	$bogfor=1;
	if (!$sum){$sum=0;}
	if (!$status){$status=0;}


	#Kontrol mod brug af browserens "tilbage" knap og mulighed for 2 x bogfring af samme ordre
	if ($id) {
		$query = db_select("select status from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			if ($row['status']!=$status) {
				print "Hmmm -a $row[status] - b $status har du brugt browserens tilbageknap?";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
				exit;
			}
		}
	}
	if ($submit == 'credit') $art='KK';
	if ($submit == 'credit'|| $submit == 'copy') {
		if ($art!='KK') {
			$id='';
			$status=0;
		}
		else	{
			$kred_ord_id=$id;
			$id='';
			$status=0;
		}
	}
	elseif (!$art) {$art='KO';}
	if ($godkend == "on") {
		if ($status==0) {$status=1;}
		elseif ($status==1) {$status=2;}
	}
	if (strlen($ordredate)<6){$ordredate=date("Y-m-d");}
	if (($kontonr)&&(!$firmanavn)) {
		$query = db_select("select * from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$konto_id=$row['id'];
			$firmanavn=db_escape_string($row['firmanavn']);
			$addr1=db_escape_string($row['addr1']);
			$addr2=db_escape_string($row['addr2']);
			$postnr=$row['postnr'];
			$bynavn=db_escape_string($row['bynavn']);
			$land=db_escape_string($row['land']);
		 	$kontakt=db_escape_string($row['kontakt']);
			$betalingsdage=$row['betalingsdage'];
			$betalingsbet=$row['betalingsbet'];
			$cvrnr=$row['cvrnr'];
			$email=db_escape_string($row['email']);
			$mail_fakt=$row['mailfakt'];
			if ($email && $mail_fakt) $udskriv_til="email";
			if (!$$email || !$udskriv_til) $udskriv_til="PDF";
			$notes=db_escape_string($row['notes']);
			$gruppe=$row['gruppe'];
		}
		if ($gruppe) {
			$query = db_select("select box1,box3,box9 from grupper where art='KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$omlev=$row['box9'];
#cho "omlev $omlev<br>";				
			if (substr($row['box1'],0,1)=='K') {
 				$tmp= substr($row['box1'],1,1)*1;
				$valuta=$r['box3'];
				$query = db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$momssats=trim($row['box2'])*1;
			} elseif (substr($row['box1'],0,1)=='E') {
				$momssats='0.00';
			} elseif (substr($row['box1'],0,1)=='Y') { 
				$momssats='0.00';
			}
#				if (!$momssats) {
#					print "<BODY onLoad=\"javascript:alert('Kreditorgrupper forkert opsat')\">";
#					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php?id=$id\">";
#					exit;
#				}
		} elseif ($konto_id) print "<BODY onLoad=\"javascript:alert('Kreditor ikke tilknyttet en kreditorgruppe')\">";
	}
	if (!$id&&!$konto_id&&!$firmanavn&&$varenr[0]) {
		$varenr[0]=strtoupper($varenr[0]);
		$qtxt = "SELECT variant_type,vare_id FROM variant_varer WHERE upper(variant_stregkode) = '$varenr[0]'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$vare_id[0]=$r['vare_id'];
		$variant_type[0]=$r['variant_type'];
	} else $variant_type[0]='';
	if ($variant_type[0] && $vare_id[0]) $string="select varer.id as vare_id, vare_lev.lev_id as konto_id, adresser.firmanavn as firmanavn from vare_lev,varer,adresser where varer.id = '$vare_id[0]' and vare_lev.vare_id = '$vare_id[0]' and adresser.id = vare_lev.lev_id order by vare_lev.posnr";
	else $string="select varer.id as vare_id, vare_lev.lev_id as konto_id, adresser.firmanavn as firmanavn from vare_lev,varer,adresser where (upper(varer.varenr) = '$varenr[0]' or upper(varer.stregkode) = '$varenr[0]') and vare_lev.vare_id = varer.id and adresser.id = vare_lev.lev_id order by vare_lev.posnr";

		$r=db_fetch_array(db_select($string,__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id'];
		$firmanavn=$r['firmanavn'];
		$id = indset_konto($id, $konto_id);
	}
	if ((!$id)&&($konto_id)&&($firmanavn)) {
		$qtxt = "select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc";
		if ($row = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {$ordrenr=$row['ordrenr']+1;}
		else {$ordrenr=1;}
#			if ($row= db_fetch_array(db_select("select ansat_id from brugere where brugernavn='$brugernavn'",__FILE__ . " linje " . __LINE__))) {
#				if ($row= db_fetch_array(db_select("select afd from ansatte where id='$row[ansat_id]'",__FILE__ . " linje " . __LINE__))) {
#					if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager_id=$row['kodenr'];}
#				}
#			}
#			if (!$lager_id) {$lager_id='0';}
		if (!$kred_ord_id) $kred_ord_id = 0;
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
		if ($lev_postnr && !$lev_bynavn) $lev_bynavn=bynavn($lev_postnr);
		$qtxt="insert into ordrer ";
		$qtxt.="(ordrenr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,land,kontakt,lev_navn,lev_addr1,";
		$qtxt.="lev_addr2,lev_postnr,lev_bynavn,lev_kontakt,kundeordnr,betalingsdage,betalingsbet,cvrnr,email,mail_fakt,udskriv_til,notes,art,ordredate,";
		$qtxt.="momssats,status,ref,afd,lager,sum,lev_adr,hvem,tidspkt,valuta,kred_ord_id,omvbet)";
		$qtxt.=" values ";
		$qtxt.="($ordrenr,$konto_id,'$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$land','$kontakt','$lev_navn','$lev_addr1',";
		$qtxt.="'$lev_addr2','$lev_postnr','$lev_bynavn','$lev_kontakt','$kundeordnr','$betalingsdage','$betalingsbet','$cvrnr','$email','$mail_fakt','$udskriv_til','$notes','$art','$ordredate',";
		$qtxt.="'$momssats',$status,'$brugernavn','$afd','$lager','$sum','$lev_adr','$brugernavn','$tidspkt','$valuta','$kred_ord_id','$omlev')";
		$qtxt.="";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select max(id) as id from ordrer where kontonr='$kontonr' and ordredate='$ordredate'";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		if ($r=db_fetch_array($q)) $id=$r['id'];
	} elseif(($konto_id)&&($firmanavn)) {
		$sum=0;
		for($x=1; $x<=$linjeantal; $x++) {
			$antal[$x]=afrund($antal[$x],2);
			
			if (!$varenr[$x]) {$antal[$x]=0; $pris[$x]=0; $rabat[$x]=0;}
			elseif ($antal[$x]<0 && $art!='KK' && !$negativt_lager) {
				$query = db_select("select gruppe, beholdning from varer where varenr = '$varenr[$x]' or stregkode = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				if (!$row['beholdning']){$row['beholdning']=0;}
				if ($row['beholdning']-$antal[$x]<0) {
					$tmp=abs($antal[$x]);
					list($a,$b)=explode(",",dkdecimal($row['beholdning'],2));
					if ($b*1) $a=$a.",".$b*1;
					print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $antal[$x] n&aring;r lagerbeholdningen er $a ! (Varenr: $varenr[$x])')\">";
					$bogfor=0;
				}
			}
			elseif (($art=='KK')&&($kred_ord_id)) { ###################	 Kreditnota ####################

				if (!$vare_id[$x]) $vare_id[$x]=find_vare_id($varenr[$x]);
				if (!$hurtigfakt && $vare_id[$x]) {
					$r = db_fetch_array(db_select("select grupper.box8, grupper.box9 from grupper,varer where varer.id = '$vare_id[$x]' and grupper.kodenr = varer.gruppe and grupper.art='VG'",__FILE__ . " linje " . __LINE__));
					$batch[$x]=$r['box9'];
					if ($r['box8'] == 'on') {
						$rest=0;
						if ($batch[$x]) $query = db_select("select id, rest, lager from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
						else $query = db_select("select id, antal, lager from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
						while ($row = db_fetch_array($query)) {
							if ($batch && $row['rest']) $rest=$rest+$row['rest'];
							else $rest=$rest+$row['antal'];
							$llager[$x]=$row['lager'];
						}
						$tmp=$leveres[$x]*-1;
						if (($rest<$tmp)&&($llager[$x]<='0')) {
							if ($batch[$x]) print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r der er $rest tilbage fra ordre nr: $kred_ord_nr! (Varenr: $varenr[$x])')\">";
							else print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r der er k&oslash;bt $rest på ordre nr: $kred_ord_nr! (Varenr: $varenr[$x])')\">";
							$bogfor=0;
						} elseif (!$negativt_lager) {
							$r = db_fetch_array(db_select("select beholdning from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
							if ($r['beholdning']<$tmp) {
								print "<BODY onLoad=\"javascript:alert('Du kan ikke returnere $tmp n&aring;r lagerbeholdningen er $r[beholdning]! (Varenr: $varenr[$x])')\">";
								$bogfor=0;
							}
						}
					}
				} elseif (!$vare_id[$x] && $varenr[$x]) { 
					print "<BODY onLoad=\"javascript:alert('Varenr: $varenr[$x] eksisterer ikke??')\">";
					$bogfor=0;
				}
				if ($antal[$x]>0) {
					print "<BODY onLoad=\"javascript:alert('Du kan ikke kreditere et negativt antal (Varenr: $varenr[$x])')\">";
					$antal[$x]=$antal[$x]*-1;
					$bogfor=0;
				}
			} ############################ Kreditnota slut ######################
			if (!$vare_id[$x]){$vare_id[$x]=find_vare_id($varenr[$x]);}
			if (($posnr_ny[$x]=="-")&&($status<1)) {
				$query = db_select("select * from batch_kob where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Du kan ikke slette varelinje $posnr_ny[$x] da der &eacute;r solgt vare(r) fra denne batch')\">";}
				else {
					db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
			elseif ($submit != 'copy') {
				if (!$antal[$x]) $antal[$x]=1;
				if ($antal[$x] > 99999999) {
					alert ("Ulovlig værdi i Antal ($antal[$x])");
					$antal[$x] = 1;
				}
				if ($status>0) {
					$tidl_lev[$x]=0;
					if ($vare_id[$x]) {
						if ($serienr[$x]) {
							$sn_antal=0;
							$query = db_select("select * from serienr where kobslinje_id = '$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
							while ($row = db_fetch_array($query)){$sn_antal++;}
							if (($sn_antal>0)&&($antal[$x]<$sn_antal)) {
								 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re mindre end antal registrerede serienr!')\">";
								$antal[$x]=$sn_antal;
							}
							$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
							while ($row = db_fetch_array($query)){$sn_antal--;}
							if (($sn_antal<0)&&($antal[$x]>$sn_antal)&&($art!='KK'))	{
								 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re st&oslash;rre end antal serienr!')\">";
								$antal[$x]=$sn_antal;
							}
							$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' order by serienr",__FILE__ . " linje " . __LINE__);
							while ($row = db_fetch_array($query)){$sn_antal++;}
							if (($sn_antal>0)&&($antal[$x]<$sn_antal)&&($art=='KK'))	{
								 print "<BODY onLoad=\"javascript:alert('Posnr: $posnr_ny[$x] - $varenr[$x] Antal kan ikke v&aelig;re mindre end antal serienr!')\">";
								 $antal[$x]=$sn_antal;
							}
						}
						$status=2;
						$reserveret[$x]=0;
						$query = db_select("select * from reservation where linje_id = $linje_id[$x] and batch_salg_id!=0",__FILE__ . " linje " . __LINE__);
						while ($row = db_fetch_array($query))$reserveret[$x]=$reserveret[$x]+$row['antal'];
						$reserveret[$x]=afrund($reserveret[$x],2);
						if ($antal[$x]>=0 && $antal[$x]<$reserveret[$x]) {
							print "<BODY onLoad=\"javascript:alert('Der er $reserveret[$x] reservationer p&aring; varenr. $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $reserveret[$x]!')\">";
							$antal[$x]=$reserveret[$x]; $submit='save'; $status=1;
						}
						$tidl_lev[$x]=0;
						$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						while($row = db_fetch_array($query)){
							$tidl_lev[$x]+=$row['antal'];
							$solgt[$x]=-$row['rest'];
						}
						$tidl_lev[$x]=afrund($tidl_lev[$x],2);
						if ($posnr_ny[$x]=="-") {
							if ($tidl_lev[$x]!=0) $posnr_ny[$x]=0;
							elseif ($solgt[$x]!=0) $posnr_ny[$x]=0;
							elseif ($reserveret[$x]!=0) {
								$posnr_ny[$x]=$posnr[$x];
								print "<BODY onLoad=\"javascript:alert('Varenr: $varenr[$x] Der er reserveret varer fra denne varelinje - linjen kan ikke slettes!')\">";
							}
							else {
								db_modify("delete from reservation where linje_id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
								db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
								$posnr_ny[$x]=1;
							}
							if (!$posnr_ny[$x]) {
								$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__));
								$posnr_ny[$x]=$r['posnr'];
							}
						} elseif ($antal[$x]>0) {
/* 20150209			if ($antal[$x]<$solgt[$x]) { 20150309
								print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $solgt[$x]!')\">";
								$antal[$x]=$solgt[$x]; $submit = 'save'; $status=1;
							} */
							if ($antal[$x]<$tidl_lev[$x]) {
								print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!')\">";
								$antal[$x]=$tidl_lev[$x]; $submit='save'; $status=1;
							}
							if ($leveres[$x]>$antal[$x]-$tidl_lev[$x]) {
								$temp=$antal[$x]-$tidl_lev[$x];
								print "<BODY onLoad=\"javascript:alert('Varenr. $varenr[$x]: antal klar til modtagelse &aelig;ndret fra $leveres[$x] til $temp!')\">";
								$leveres[$x]=$temp; $submit = 'save'; $status=1;
							}
							elseif ($leveres[$x]<0) {
								$temp=0;
								print "<BODY onLoad=\"javascript:alert('Varenr. $varenr[$x]: modtag &aelig;ndret fra $leveres[$x] til $tidl_lev[$x]!')\">";
								$leveres[$x]=$temp; $submit = 'save'; $status=1;
							}
						} else {
							$tidl_lev[$x]=0;
							$query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
							while($row = db_fetch_array($query)) $tidl_lev[$x]+=$row['antal'];
#cho __LINE__." $tidl_lev[$x]<br>"; 								
							if ($antal[$x]>$tidl_lev[$x]) {
								print "<BODY onLoad=\"javascript:alert('Varenr. $varenr[$x]: antal &aelig;ndret fra $antal[$x] til $tidl_lev[$x]!')\">";
								$antal[$x]=$tidl_lev[$x]; $submit = 'save'; $status=1;
							}
							if ($leveres[$x]<$antal[$x]+$tidl_lev[$x]) {
								$tmp1=$leveres[$x]*-1;
								$tmp2=abs($antal[$x]+$tidl_lev[$x]);

								print "<BODY onLoad=\"javascript:alert('Posnr $posnr_ny[$x] :return&eacute;r &aelig;ndret fra $tmp1 til $tmp2!')\">";
								$leveres[$x]=$antal[$x]+$tidl_lev[$x]; $submit = 'save'; $status=1;
							}
							elseif ($leveres[$x] > 0) {
								$tmp1=$leveres[$x]*-1;
								$tmp2=0;
								print "<BODY onLoad=\"javascript:alert('Varenr $varenr[$x]: return&eacute;r &aelig;ndret fra $tmp1 til $tmp2!')\">";
								$leveres[$x]=0; $submit = 'save'; $status=1;
							}
						}
						if (afrund($antal[$x]-$tidl_lev[$x],2)) $status=1;
					} elseif ($posnr_ny[$x]=="-") {
						db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
						$posnr_ny[$x]=1;
					}
				}
				if (!$leveres[$x]){$leveres[$x]=0;}
				$sum=$sum+($pris[$x]-($pris[$x]/100*$rabat[$x]))*$antal[$x];
				if ((!strpos($posnr_ny[$x], '+'))&&($id)) {
					$posnr_ny[$x]=afrund($posnr_ny[$x],0);
					if ($posnr_ny[$x]>=1) {
						db_modify("update ordrelinjer set posnr='$posnr_ny[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
					} else {
						print "<BODY onLoad=\"javascript:alert('Hint! Du skal s&aelig;tte et - (minus) som pos nr for at slette en varelinje')\">";
					}
				}
				if (($status<2)||(($antal[$x]>0)&&($status==2)&&($antal[$x]>=$tidl_lev[$x]))||(($antal[$x]<0)&&($status==2)&&($antal[$x]<=$tidl_lev[$x]))) {
					if ($serienr[$x]) $antal[$x]=afrund($antal[$x],0);
					if (!isset($tidl_lev[$x])) $tidl_lev[$x]=0;
					if (!isset($projekt[$x]))  $projekt[$x]=0;
						if ($omvbet[$x]) $omvbet[$x]='on';
					$qtxt = "update ordrelinjer set beskrivelse='$beskrivelse[$x]', antal='$antal[$x]', leveres='$leveres[$x]', ";
					$qtxt.= "leveret='$tidl_lev[$x]', pris='$pris[$x]', rabat='$rabat[$x]', projekt='$projekt[$x]', omvbet='$omvbet[$x]', ";
					$qtxt.= "lager='$lager' where id='$linje_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} 
#					if ($leveret[$x]!=$tidl_lev[$x]) {
#						db_modify("update ordrelinjer set leveret='$tidl_lev[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
#					}
#					if ((strpos($posnr_ny[$x], '+'))&&($id)) indsaet_linjer($id, $linje_id[$x], $posnr_ny[$x]);
			}
		}
		if ( $posnr_ny[0] > 0 && $submit != 'lookup' ) {
			if ($varenr[0]) {
				$varenr[0]=strtoupper($varenr[0]);
				if ($r=db_fetch_array(db_select("SELECT id,vare_id,variant_type FROM variant_varer WHERE upper(variant_stregkode) = '$varenr[0]'",__FILE__ . " linje " . __LINE__))) {
					$vare_id[0]=$r['vare_id'];
					$variant_type[0]=$r['variant_type'];
					$variant_id[0]=$r['id'];
				} else {
						$variant_type[0]=0;
						$variant_type[0]='';
				}
				if ($variant_type[0] && $vare_id[0]) $string="SELECT * FROM varer WHERE id = '$vare_id[0]'";
				else $string="SELECT * FROM varer WHERE upper(varenr) = '$varenr[0]' or upper(stregkode) = '$varenr[0]'";
				if ($r0 = db_fetch_array(db_select("$string",__FILE__ . " linje " . __LINE__))) {
#						$variant_type[0]=$r0['variant'];
					$vare_id[0]=$r0['id'];
					$varenr[0]=db_escape_string($r0['varenr']);
					$serienr[0]=trim($r0['serienr']);
					$samlevare[0]=trim($r0['samlevare']);
					if (!$beskrivelse[0]) $beskrivelse[0]=db_escape_string($r0['beskrivelse']);
					if (!$enhed[0])$enhed[0]=db_escape_string($r0['enhed']);
					if (!$rabat[0]) $rabat[0]=$r0['rabat'];
					if (!$antal[0]) $antal[0]=1;
					if (!$rabat[0]) $rabat[0]=0;
					if ($antal[0] > 99999999) {
						alert ("Ulovlig værdi i \"Antal\" ($antal[0])");
						$antal[0] = 1;
					}
					if (!$lev_varenr[0]) {
						if (!$konto_id) {
							if ($r1=db_fetch_array(db_select("select * from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__))) {
								$konto_id=$r1['id'];
							}
						}
						if ($r1=db_fetch_array(db_select("select * from vare_lev where vare_id = '$vare_id[0]' and lev_id = '$konto_id'",__FILE__ . " linje " . __LINE__))) {
							if (!$pris[0]) $pris[0]=$r1['kostpris'];
							$lev_varenr[0]=db_escape_string($r1['lev_varenr']);
						}
					}
					if (!$pris[0]) $pris[0]=$r0['kostpris'];
					$pris[0]=$pris[0]*1;
					if ($valuta && $valuta!='DKK') {
						if ($r1=db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=grupper.kodenr::INT and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
							$pris[0]=$pris[0]*100/$r1['kurs'];
						} else {
							$tmp = dkdato($ordredate);
							print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
						}
					}
					if ($serienr[0]) $antal[0]=afrund($antal[0],0);
					if ($r1=db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[0]'",__FILE__ . " linje " . __LINE__))) {
						$qtxt = "select box4,box6,box7,box8 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'";
						$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$bogfkto[0] = $r2['box4'];
						$omvare[0] = $r2['box6'];
						$momsfri[0] = $r2['box7'];
						$lagerfort[0] = $r2['box8'];
					}
					# if (($omvare[0]!=NULL)&&($rabatsats>$r2['box6'])) $rabatsats=$r2['box6'];
					if ($bogfkto[0] && !$momsfri[0]) {
						$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto[0]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
						if ($tmp=trim($r2['moms'])) { # f.eks S3
							$tmp=substr($tmp,1); #f.eks 3
							$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
							if ($r2['box2']) $varemomssats[0]=$r2['box2']*1;
						}	else $varemomssats[0]=$momssats;
					} elseif (!$momsfri[0]) $varemomssats[0]=$momssats;
					else $varemomssats[0]=$momssats;
					if (!isset($variant_type[0])) $variant_type[0] = 0;
					if ($variant_type[0]) {
						$varianter=explode(chr(9),$variant_type[0]);
						for ($y=0;$y<count($varianter);$y++) {
							$qtxt = "select variant_typer.beskrivelse as vt_besk,varianter.beskrivelse as var_besk from variant_typer,varianter ";
							$qtxt.= "where variant_typer.id = '$varianter[$y]' and variant_typer.variant_id=varianter.id";
							$r1=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
							$beskrivelse[0].=", ".$r1['var_besk'].":".$r1['vt_besk'];
						}
					}
					if ($samlevare[0]) {
						samlevare($id,$art,$vare_id[0],$antal[0]);
					} else {
						($omlev && $omvare[0])?$omvbet[0]='on':$omvbet[0]=''; #20150415
						$qtxt = "insert into ordrelinjer "; 
						$qtxt.= "(ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,";
						$qtxt.= "rabat,serienr,lev_varenr,momsfri,variant_id,samlevare,omvbet,momssats,lager) values ";
						$qtxt.= "('$id','$posnr_ny[0]','$varenr[0]','$vare_id[0]','$beskrivelse[0]','$enhed[0]','$antal[0]',";
						$qtxt.= "'$pris[0]','$rabat[0]','$serienr[0]','$lev_varenr[0]','$momsfri[0]','$variant_id[0]',";
						$qtxt.= "'$samlevare[0]','$omvbet[0]',$varemomssats[0],'$lager')";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				} else {
					$submit='Lookup';
				}
			if ($status==2) $status=1;
			}
			elseif ($beskrivelse[0]) {
				$qtxt = "insert into ordrelinjer (ordre_id, posnr, beskrivelse) values ('$id', '$posnr_ny[0]', '$beskrivelse[0]')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				if ($status==2) $status=1;
			}
		}
		$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$qtxt="update ordrer set firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',";
			$qtxt.="land='$land',kontakt='$kontakt',lev_navn='$lev_navn',	lev_addr1='$lev_addr1',	lev_addr2='$lev_addr2',";
			$qtxt.="lev_postnr='$lev_postnr',lev_bynavn='$lev_bynavn',lev_kontakt='$lev_kontakt',kundeordnr='$kundeordnr',";
			$qtxt.="betalingsdage='$betalingsdage',betalingsbet='$betalingsbet',cvrnr='$cvrnr',momssats='$momssats',";
			$qtxt.="notes='$notes',art='$art',ordredate='$ordredate',email='$email',udskriv_til='$udskriv_til',";
			$qtxt.="mail_subj='$mail_subj',mail_text='$mail_text',"; #20230105 
			if (strlen($levdate)>=6)$qtxt.="levdate='$levdate',";
			if (!isset($projekt[0])) $projekt[0] = 0; 
			$qtxt.="status=$status,ref='$ref',afd='$afd',lager='$lager',fakturanr='$fakturanr',lev_adr='$lev_adr',hvem = '$brugernavn',";
			$qtxt.="tidspkt='$tidspkt',valuta='$valuta',valutakurs='$valutakurs',projekt='$projekt[0]'";
			$qtxt.=" where id=$id";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		else {
			$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"javascript:alert('Ordren er overtaget af $row[hvem]')\">";}
			if ($popup) print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
			else print "<meta http-equiv=\"refresh\" content=\"0;URL=ordreliste.php\">";
		}
	}
	if (($godkend=='on')&&($status==2)) {
		$opret_ny=0;
		for($x=1; $x<=$linjeantal; $x++) {
			if ($antal[$x]!=$tidl_lev[$x]) $opret_ny=1;
		}
		if ($opret_ny==1)	{
			
			$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			$qtxt = "insert into ordrer ";
			$qtxt.= "(ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, ";
			$qtxt.= "lev_navn,	lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, kundeordnr, betalingsdage, ";
			$qtxt.= "betalingsbet, cvrnr, email, mail_fakt, udskriv_til, notes, art, ordredate, momssats, status, ref, sum, lev_adr, valuta) values ";
			$qtxt.= "($ordrenr, $konto_id, '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$kontakt', ";
			$qtxt.= "'$lev_navn',	'$lev_addr1',	'$lev_addr2',	'$lev_postnr',	'$lev_bynavn', '$lev_kontakt', '$kundeordnr', '$betalingsdage', ";
			$qtxt.= "'$betalingsbet', '$cvrnr', '$email', '$mail_fakt', '$udskriv_til', '$notes', '$art', '$ordredate', '$momssats', 1, '$ref', '$sum', '$lev_adr', '$valuta')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$query = db_select("select id from ordrer where ordrenr='$ordrenr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$ny_id=$row[id];
			$ny_sum=0;
			for($x=1; $x<=$linjeantal; $x++) {
				if (!isset($enhed[$x])) $enhed[$x] = NULL;
				if ($antal[$x]!=$tidl_lev[$x]) {
					$diff[$x]=$antal[$x]-$tidl_lev[$x];
					$antal[$x]=$tidl_lev[$x];
					if ($serienr[$x]) $antal[$x]=afrund($antal[$x],0);
					$r1 =	db_fetch_array(db_select("select gruppe from varer where id = '$vare_id[$x]'",__FILE__ . " linje " . __LINE__));
					$r2 = db_fetch_array(db_select("select box6,box7 from grupper where art = 'VG' and kodenr = '$r1[gruppe]'",__FILE__ . " linje " . __LINE__));
					$bogfkto[$x] = $r2['box4'];
					(trim($r2['box6']))?$omvare[$x]='on':$omvare[$x]='';
					$momsfri[$x] = $r2['box7'];
					$lagerfort[$x] = $r2['box8'];
					if ($bogfkto[$x] && !$momsfri[$x]) {
						$r2 = db_fetch_array(db_select("select moms from kontoplan where kontonr = '$bogfkto[$x]' and regnskabsaar = '$regnaar'",__FILE__ . " linje " . __LINE__));
						if ($tmp=trim($r2['moms'])) { # f.eks S3
							$tmp=substr($tmp,1); #f.eks 3
							$r2 = db_fetch_array(db_select("select box2 from grupper where art = 'SM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__));
							if ($r2['box2']) $varemomssats[$x]=$r2['box2']*1;
						}	else $varemomssats[$x]=$momssats;
					} elseif (!$momsfri[$x]) $varemomssats[$x]=$momssats;
					else $varemomssats[$x]=$momssats;
			
					db_modify("insert into ordrelinjer (ordre_id, posnr, varenr, vare_id, beskrivelse, enhed, antal, pris, rabat, serienr, lev_varenr, momsfri,projekt,momssats,lager) values ('$ny_id', '$posnr_ny[$x]', '$varenr[$x]', '$vare_id[$x]', '$beskrivelse[$x]', '$enhed[$x]', '$diff[$x]', '$pris[$x]', '$rabat[$x]', '$serienr[$x]', '$lev_varenr[$x]','$momsfri[$x]','$projekt[$x]','$varemomssats[$x]','$lager')",__FILE__ . " linje " . __LINE__);
					db_modify("update ordrelinjer set antal=$antal[$x] where id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
					$ny_sum=$ny_sum+$diff[$x]*($pris[$x]-$pris[$x]*$rabat[$x]/100);
				}
			}
			db_modify("update ordrer set sum=$ny_sum where id = $ny_id",__FILE__ . " linje " . __LINE__);
		}
	}
	if ($submit == 'copy' || $submit == 'credit') {
#			if ($kred_ord_id) {
#				db_modify("update ordrer set kred_ord_id='$kred_ord_id' where id='$id'",__FILE__ . " linje " . __LINE__);}
		for($x=1; $x<=$linjeantal; $x++) {
			if (!isset($enhed[$x])) $enhed[$x] = NULL;
#				$posnr[$x]=$x;	
			if (!$vare_id[$x] && $varenr[$x]) {
				$query = db_select("select id from varer where varenr = '$varenr[$x]' or stregkode = '$varenr[$x]'",__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($query)) $vare_id[$x]=$row['id'];
			}
			if ($submit == 'credit' && $vare_id[$x] && !$hurtigfakt) {
				$antal[$x]=0;
				$query = db_select("select rest from batch_kob where vare_id = '$vare_id[$x]' and ordre_id = $kred_ord_id",__FILE__ . " linje " . __LINE__);
				while ($row = db_fetch_array($query)) $antal[$x]=$antal[$x]-$row['rest'];
			} elseif ($hurtigfakt && $submit == 'credit') $antal[$x]=$antal[$x]*-1;
			if ($serienr[$x]) $serienr[$x]="on";
#				if (!isset($varemomssats[$x])) $varemomssats[$x] = '';
			if (!isset($varemomssats[$x]) || $varemomssats[$x] == '') $varemomssats[$x]=find_varemomssats($linje_id[$x]); #20141106
			if ($vare_id[$x]) {
				$qtxt = "insert into ordrelinjer (ordre_id,posnr,varenr,vare_id,beskrivelse,enhed,antal,pris,rabat,serienr,lev_varenr,momsfri,kred_linje_id,momssats,lager) values ('$id',
				'$x',
				'$varenr[$x]',
				'$vare_id[$x]',
				'$beskrivelse[$x]',
				'$enhed[$x]',
				$antal[$x],
				'$pris[$x]',
				'$rabat[$x]',
				'$serienr[$x]',
				'$lev_varenr[$x]',
				'$momsfri[$x]',
				'$linje_id[$x]',
				'$varemomssats[$x]',
				'$lager')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into ordrelinjer (ordre_id,posnr,beskrivelse,enhed) values ('$id','$x','$beskrivelse[$x]','$enhed[$x]')",__FILE__ . " linje " . __LINE__);
			}
		}
	} 
	$vis=1;
transaktion("commit");
}
if ($submit == 'print') {
	$id=if_isset($_POST['id']);
	$status=if_isset($_POST['status']);
	$ps_fil="formularprint.php";
	if ($status<=1) $formular=12;
	if ($status==2) $formular=13;
	if ($status>2) $formular=14;
	$udskriv_til=(($_POST['udskriv_til'] && $_POST['email'])?$_POST['udskriv_til']:'PDF');
	//$udskriv_til='PDF';
  print "<meta http-equiv=\"refresh\" content=\"0;URL=$ps_fil?id=$id&formular=$formular&udskriv_til=$udskriv_til\">\n";
}
########################## OPSLAG / lookup ################################

if ($submit == 'lookup') {
	if (($fokus=='kontonr')&&(!$id)) kontoopslag($sort, $fokus, $id, $kontonr); #20221104 changed
	if (($fokus=='firmanavn')&&(!$id)) kontoopslag($sort, $fokus, $id, $firmanavn); #20221104 changed
	if (($fokus=='addr1')&&(!$id)) kontoopslag($sort, $fokus, $id, $addr1); #20221104 changed
	if (($fokus=='addr2')&&(!$id)) kontoopslag($sort, $fokus, $id, $addr2); #20221104 changed
	if (($fokus=='postnr')&&(!$id)) kontoopslag($sort, $fokus, $id, $postnr); #20221104 changed
	if (($fokus=='bynavn')&&(!$id)) kontoopslag($sort, $fokus, $id, $bynavn); #20221104 changed
	if ($fokus=='kontakt') ansatopslag($sort, $fokus, $id, $vis); #20221104 changed
	if ((strstr($fokus,'vare'))&&($art!='DK')) vareopslag($sort, 'varenr', $id, $vis, $ref, $varenr[0],$lager);
	if ((strstr($fokus,'besk'))&&($art!='DK')) vareopslag($sort, 'beskrivelse', $id, $vis, $ref, $beskrivelse[0],$lager);
	#20221104 BEGIN
	if (($fokus=='lev_navn')&&($id)) debitoropslag($sort, $fokus, $id, $lev_navn);
	if (($fokus=='lev_addr1')&&($id)) debitoropslag($sort, $fokus, $id, $lev_addr1);
	if (($fokus=='lev_addr2')&&($id)) debitoropslag($sort, $fokus, $id, $lev_addr2);
	if (($fokus=='lev_bynavn')&&($id)) debitoropslag($sort, $fokus, $id, $lev_bynavn);
	if (($fokus=='lev_postnr')&&($id)) debitoropslag($sort, $fokus, $id, $lev_postnr);
	if (($fokus=='lev_kontakt')&&($id)) debitoropslag($sort, $fokus, $id, $lev_kontakt);
	#20221104 END
	# TODO else redirect
}

##########################BOGFOR################################

if ( $submit == 'postNow' && $bogfor!=0 && $status==2 ) {
	if ($valuta && $valuta!='DKK') {
		$qtxt = "select kodenr from grupper where art='VK' and box1='$valuta' ";
		if ($r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$valutaGroup = $r['kodenr'];
			$qtxt = "select valuta.kurs from valuta where gruppe = $valutaGroup and  valdate <= '$ordredate' ";
			$qtxt.= "order by valuta.valdate desc limit 1";
			if ($r= db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$valutakurs=$r['kurs'];
			} else {
				$valutakurs = NULL;
			}
		}
	} else $valutakurs=100;
	if (!$valutakurs) {
		$tmp = dkdato($ordredate);
		print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $ordredate')\">";
	} elseif(!$fakturanr) print "<BODY onLoad=\"javascript:alert('Fakturanummer mangler')\">";
	else {
		db_modify("update ordrer set valutakurs = '$valutakurs' where id = '$id'",__FILE__ . " linje " . __LINE__);
		$linjeantal=0;
		$q = db_select("select id from ordrelinjer where ordre_id = '$id' order by posnr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$linjeantal++;
			$linje_id[$linjeantal]=$r['id'];
		}
		for ($x=1;$x<=$linjeantal;$x++) {
			db_modify("update ordrelinjer set posnr = '$x' where id = '$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
		}
		if (!$linjeantal) print "<BODY onLoad=\"javascript:alert('Du kan ikke fakturere uden ordrelinjer')\">";
		else print "<meta http-equiv=\"refresh\" content=\"0;URL=bogfor.php?id=$id\">";
	}
}
if ( ($submit=='receive' || $submit=='return') && $bogfor!=0 ) {
	$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	if (!$row = db_fetch_array($query)) {Print "Du kan ikke modtage uden ordrelinjer";}
	else {print "<meta http-equiv=\"refresh\" content=\"0;URL=modtag.php?id=$id\">";}
}
if ($popup) print "<meta http-equiv=\"refresh\" content=\"3600;URL=../includes/luk.php\">";
else print "<meta http-equiv=\"refresh\" content=\"3600;URL=ordreliste.php\">";
ordreside($id);


######################################################################################################################################

function ordreside($id) {

	global $art;
	global $bogfor,$brugernavn;
	global $db,$fokus;
	global $krediteret;
	global $labelprint;
	global $momssum;
	global $returside;
	global $sort,$sprog_id,$submit;

	if(!isset($salgspris)){ $salgspris=null;} 
	if(!isset($antal)){ $antal=null;} 
	if(!isset($gruppe)){ $gruppe=null;} 
	$afd_nr = array();
	$ordre_id=0;

	$r=db_fetch_array(db_SELECT("select box4 from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$hurtigfakt=$r['box4'];


	if (!$id) $fokus='kontonr';
	print "<form name=ordre action=ordre.php method=post>";
	if ($id)	{
		$q = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q);
		$ordre_id      = $r['id'];
		$kontonr       = $r['kontonr'];
		$konto_id      = $r['konto_id'];
		$firmanavn     = $r['firmanavn'];
		$addr1         = $r['addr1'];
		$addr2         = $r['addr2'];
		$postnr        = $r['postnr'];
		$bynavn        = $r['bynavn'];
		$land          = $r['land'];
		$kontakt       = $r['kontakt'];
		$lev_navn      = $r['lev_navn'];
		$lev_addr1     = $r['lev_addr1'];
		$lev_addr2     = $r['lev_addr2'];
		$lev_postnr    = $r['lev_postnr'];
		$lev_bynavn    = $r['lev_bynavn'];
		$lev_kontakt   = $r['lev_kontakt'];
		$kundeordnr    = $r['kundeordnr'];
		$email         = $r['email'];
		if ($email && $r['udskriv_til']) $udskriv_til = $r['udskriv_til'];
		else $udskriv_til = 'PDF';
		$mail_subj=$r['mail_subj']; #20230105
		$mail_text=str_replace("<br>","\n",$r['mail_text']); #20230105
		$cvrnr         = $r['cvrnr'];
		$ean           = $r['ean'];
		$institution   = $r['institution'];
		$betalingsbet  = $r['betalingsbet'];
		$betalingsdage = $r['betalingsdage'];
		$valuta        = $r['valuta'];
		$projekt[0]    = $r['projekt'];
		$valutakurs    = $r['valutakurs'];
		$modtagelse    = $r['modtagelse'];
		$ref           = trim($r['ref']);
		$afd           = $r['afd'];
		$lager         = $r['lager'];
		$fakturanr     = $r['fakturanr'];
		$lev_adr       = $r['lev_adr'];
		$ordrenr       = $r['ordrenr'];
		$kred_ord_id   = $r['kred_ord_id'];
		if($r['ordredate']) $ordredato=dkdato($r['ordredate']);
		else $ordredato = date("d-m-y");
		if ($r['levdate']) $levdato=dkdato($r['levdate']);
		$momssats      = $r['momssats'];
		$status        = $r['status'];
		if (!$status) $status=0;
		$art           = $r['art'];
		$omlev         = $r['omvbet'];
		$document      = $r['dokument'];
		if (!$valuta) {
			$valuta      = 'DKK';
			$valutakurs  = 100;
		}
		$x = 0;
		$query = db_select("select id, ordrenr from ordrer where kred_ord_id = '$id' and art ='KK'",__FILE__ . " linje " . __LINE__);
		while ($row2 = db_fetch_array($query)) {
			$x++;
			if ($x>1) $krediteret = $krediteret.", ";
			$krediteret=$krediteret."<a href=ordre.php?id=$row2[id]>$row2[ordrenr]</a>";
		}
		if ($status<3) $fokus='vare0';
		else $fokus='';
	}
	$x=0;	
	if ($r=db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
		$q2=db_select("select navn,afd from ansatte where konto_id = '$r[id]' and lukket != 'on' order by navn",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
			$ansatte_navn[$x]=$r2['navn'];
			$ansatte_afd[$x]=$r2['afd'];
			if ($ref && $ref==$ansatte_navn[$x] && !$afd && $ansatte_afd[$x]) $afd=$ansatte_afd[$x];
			$x++;
		} 
	} else alert ("Stamdata mangler");
	$x=0;
	$q=db_select("select kodenr,beskrivelse,box1 from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$afd_nr[$x]=$r['kodenr'];
		$afd_navn[$x]=$r['beskrivelse'];
		$afd_lager[$x]=$r['box1'];
		if ($afd && $afd==$afd_nr[$x] && !$lager && $afd_lager[$x]) $lager=$afd_lager[$x];
		$x++;
	}
	if ($ref && $afd){
		$qtxt="select kodenr from grupper where box1='$afd' and art='LG'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$lager=$r['kodenr'];
		}
	}
	$lager*=1;
	$x=0;
	$q=db_select("select kodenr,beskrivelse from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$lager_nr[$x]=$r['kodenr'];
		$lager_navn[$x]=$r['beskrivelse'];
		$x++;
	}
	if ($submit == 'credit' || $art=='KK') {
		$query = db_select("select ordrenr from ordrer where id = '$kred_ord_id'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query);
		sidehoved($id, "$returside", "", "", "Leverand&oslash;r kreditnota $ordrenr (kreditering af ordre nr: <a href=ordre.php?id=$kred_ord_id>$row2[ordrenr]</a>)");
	}
	elseif ($krediteret) {sidehoved($id, "$returside", "", "", "Leverand&oslash;rordre $ordrenr (krediteret p&aring; KN nr: $krediteret)");}
	else {sidehoved($id, "$returside", "", "", "Leverand&oslash;rordre $ordrenr");}

	if (!$status) $status=0;
	print "<input type=\"hidden\" name=\"ordrenr\" value=\"$ordrenr\">";
	print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
	print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
	print "<input type=\"hidden\" name=\"art\" value=\"$art\">";
#	print "<input type=\"hidden\" name=momssats value=$momssats>";
	print "<input type=\"hidden\" name=\"konto_id\" value=\"$konto_id\">";
	print "<input type=\"hidden\" name=\"kred_ord_id\" value=\"$kred_ord_id\">";
	print "<input type=\"hidden\" name=\"afd\" value=\"$afd\">";
	print "<input type=\"hidden\" name=\"lager\" value=\"$lager\">";

	if ($status>=3) {
		include("orderIncludes/closedOrder.php");
	} else {
		if ($submit == 'split') {
			include('orderIncludes/splitOrder.php');
		} else {
			include("orderIncludes/openOrder.php");
			include('orderIncludes/openOrderLines.php');
		}
	}
	print "</tbody></table></td></tr>\n";
	print "</form>";
	print "</tbody></table></td></tr></tbody></table></td></tr>\n";
	print "<tr><td></td></tr>\n";

		
}# end function ordreside
######################################################################################################################################
function kontoopslag($sort, $fokus, $id, $find){

	global $bgcolor,$bgcolor5;
	global $charset;
	global $memu;
	global $sprog_id;

 	if ($menu=='T') {
 		include_once '../includes/top_header.php';
 		include_once '../includes/top_menu.php';
 	}
 	
	sidehoved($id, "../kreditor/ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top' class='dataTable'>";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=kontonr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(357,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=firmanavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(138,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=addr1&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(648,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=addr2&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(362,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=postnr&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(36,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=bynavn&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(1055,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=land&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(364,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=kontakt&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(632,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=kontoOpslag&x=$x&fokus=$fokus&id=$id>Telefon</b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) {$sort = 'firmanavn';}

	$qtxt = "select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'K' ";
	
	if ($find) {
		$find=str_replace("*","%",$find);
		if (($fokus=='postnr')||($fokus=='kontonr')) $qtxt.= "AND $fokus='".$find."' ";
		else $qtxt.= "AND $fokus ILIKE '%".$find."%' ";
	}

	$qtxt.= "order by $sort";
	 
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kontonr=str_replace(" ","",$r['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=ordre.php?fokus=$fokus&id=$id&konto_id=$r[id]>$r[kontonr]</a></td>";
		print "<td>".htmlentities($r['firmanavn'],ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities( $r['addr1'],ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities( $r['addr2'],ENT_COMPAT,$charset)."</td>";
		print "<td> $r[postnr]</td>";
		print "<td>".htmlentities( $r['bynavn'],ENT_COMPAT,$charset)."</td>";
		print "<td> ".htmlentities($r['land'],ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities( $r['kontakt'],ENT_COMPAT,$charset)."</td>";
		print "<td> $r[tlf]</td>";
		print "</tr>\n";
	}
	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
#	} else {
#		include_once '../includes/oldDesign/footer.php';
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
#20221104 BEGIN
######################################################################################################################################
function debitoropslag($sort, $fokus, $id, $find){

	global $bgcolor,$bgcolor5;
	global $charset;
	global $memu;
	global $sprog_id;

 	if ($menu=='T') {
 		include_once '../includes/top_header.php';
 		include_once '../includes/top_menu.php';
 	}
 	
	sidehoved($id, "../kreditor/ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding='1' cellspacing='1' border='0' width='100%' valign = 'top' class='dataTable'>";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=kontonr&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(357,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=firmanavn&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(138,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=addr1&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(648,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=addr2&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(362,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=postnr&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(36,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=bynavn&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(1055,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=land&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(364,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=kontakt&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>".findtekst(632,$sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=debitorOpslag&x=$x&fokus=$fokus&id=$id>Telefon</b></td>";
	print" </tr>\n";

	$qtxt = "select id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, tlf from adresser where art = 'D' ";

	if ($find) {
		$find=str_replace("*","%",$find);
		if ($fokus=='lev_navn') $qtxt.= "AND (firmanavn ILIKE '%$find%' OR kontonr='$find') ";
		if ($fokus=='lev_addr1') $qtxt.= "AND addr1 ILIKE '%$find%' ";
		if ($fokus=='lev_addr2') $qtxt.= "AND addr2 ILIKE '%$find%' ";
		if ($fokus=='lev_bynavn') $qtxt.= "AND bynavn ILIKE '%$find%' ";
		if ($fokus=='lev_kontakt') $qtxt.= "AND kontakt ILIKE '%$find%' ";		
		if ($fokus=='lev_postnr') $qtxt.= "AND postnr='$find' ";
	}

	$sort = $_GET['sort'];
	if (!$sort) {$sort = 'firmanavn';}
	$qtxt.= "order by $sort";
	 
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kontonr=str_replace(" ","",$r['kontonr']);
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href=ordre.php?fokus=$fokus&id=$id&debitor_id=$r[id]>$r[kontonr]</a></td>";
		print "<td>".htmlentities($r['firmanavn'],ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities( $r['addr1'],ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities( $r['addr2'],ENT_COMPAT,$charset)."</td>";
		print "<td> $r[postnr]</td>";
		print "<td>".htmlentities( $r['bynavn'],ENT_COMPAT,$charset)."</td>";
		print "<td> ".htmlentities($r['land'],ENT_COMPAT,$charset)."</td>";
		print "<td>".htmlentities( $r['kontakt'],ENT_COMPAT,$charset)."</td>";
		print "<td> $r[tlf]</td>";
		print "</tr>\n";
	}
	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
#	} else {
#		include_once '../includes/oldDesign/footer.php';
	}
	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
#20221104 END
######################################################################################################################################
function ansatopslag($sort, $fokus, $id){

	global $bgcolor,$bgcolor5;
	global $charset;
	global $memu;
	global $sprog_id;

 	if ($menu=='T') {
 		include_once '../includes/top_header.php';
 		include_once '../includes/top_menu.php';
 	}
	
	sidehoved($id, "../kreditor/ordre.php", "../kreditor/kreditorkort.php", $fokus, "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	print"<table cellpadding='1' cellspacing='1' border='0	' width='100%' valign = 'top' class='dataTable'>";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=navn&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Navn</b></td>";
	print"<td><b><a href=ordre.php?sort=tlf&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Lokal</b></td>";
	print"<td><b><a href=ordre.php?sort=mobil&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>Mobil</b></td>";
	print"<td><b><a href=ordre.php?sort=email&funktion=ansatOpslag&x=$x&fokus=$fokus&id=$id>E-mail</b></td>";
	print" </tr>\n";


	$sort = $_GET['sort'];
	if (!$sort) $sort = "navn";
	if (!$id) $id = '0'; # <- 2009.05.10

	$query = db_select("select konto_id from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id = $row['konto_id']*1; # <- 2009.05.10

	$query = db_select("select * from ansatte where konto_id = $konto_id order by $sort",__FILE__ . " linje " . __LINE__); # <- 2009.05.10
	while ($row = db_fetch_array($query))	{
		if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
		else {$linjebg=$bgcolor5; $color='#000000';}
		print "<tr bgcolor=\"$linjebg\">";
		print "<td><a href='ordre.php?fokus=$fokus&id=$id&kontakt=$row[navn]'>".htmlentities($row['navn'],ENT_COMPAT,$charset)."</a></td>";
		print "<td> $row[tlf]</td>";
		print "<td> $row[mobil]</td>";
		print "<td> $row[email]</td>";
		print "</tr>\n";
	}
	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
#	} else {
#		include_once '../includes/oldDesign/footer.php';
	}

print "</tbody></table></td></tr></tbody></table>";
exit;
}
######################################################################################################
function vareopslag($sort, $fokus, $id, $vis, $ref, $find,$lager) {
	global $bgcolor,$bgcolor5;
	global $charset;
	global $konto_id,$kontonr;
	global $linjebg;
	global $menu;
	global $sprog_id,$x; #20210716

	if ($menu=='T') {
 		include_once '../includes/top_header.php';
 		include_once '../includes/top_menu.php';
 	}

	if (!$konto_id) {
		if ((!$kontonr)&&($id))	{
			$query = db_select("select kontonr from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $kontonr=trim($row[kontonr]);
		}
		if ($kontonr) {
			$query = db_select("select id from adresser where kontonr = '$kontonr' and art = 'K'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) $konto_id=$row['id'];
		}
	}

	sidehoved($id, "../kreditor/ordre.php", "../lager/varekort.php", "$fokus&leverandor=$konto_id", "Leverand&oslash;rordre $id");
#	print"<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#	print"<tr><td valign=\"top\">";
	$listeantal=0;
	if ($id) {
		$q=db_select("select id,beskrivelse from grupper where art='PL' and box4='on' and box1='$konto_id' order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$listeantal++;
			$prisliste[$listeantal]=$r['id'];
			$listenavn[$listeantal]=$r['beskrivelse'];
		}
		print "<table cellpadding='1' cellspacing='1' border='0' width='100%' valign='top' class='dataTable'><tbody><tr>";
		if ($listeantal) {
			print "<form name=\"prisliste\" action=\"../includes/prislister.php?start=0&ordre_id=$id&fokus=$fokus\" method=\"post\">";
			print "<td><select name=prisliste>";
			for($x=1;$x<=$listeantal;$x++) print "<option value=\"$prisliste[$x]\">$listenavn[$x]</option>";
			print "</select><input type = 'submit' style = 'width:120px;'name=\"prislist\" value=\"Vis\"></td>"; 
		}
	}

	print"<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">";
	print"<tbody><tr>";
	print"<td><b><a href=ordre.php?sort=varenr&funktion=lookup&x=$x&fokus=$fokus&id=$id&vis=$vis&lager=$lager>".findtekst(917, $sprog_id)."</a></b></td>";
	print"<td><b> ".findtekst(945, $sprog_id)."</b></td>";
	print"<td><b><a href=ordre.php?sort=beskrivelse&funktion=lookup&x=$x&fokus=$fokus&id=$id&vis=$vis&lager=$lager>".findtekst(914,$sprog_id)."</a></b></td>";
	print"<td align=right><b><a href=ordre.php?sort=salgspris&funktion=lookup&x=$x&fokus=$fokus&id=$id&vis=$vis&lager=$lager>".findtekst(949, $sprog_id)."</a></b></td>";
	print"<td align=right><b> ".findtekst(950, $sprog_id)."</b></td>";
	print"<td align=right><b> ".findtekst(980, $sprog_id)."</b></td>";
#	print"<td width=2%></td>";
	print"<td align><b> ".findtekst(966, $sprog_id)."</b></td>";
	if ($kontonr)	{
		if ($vis) {print"<td align=right><a href=ordre.php?sort=$sort&funktion=lookup&x=$x&fokus=$fokus&id=$id&lager=$lager><span title='".findtekst(1517, $sprog_id)."'>".findtekst(565, $sprog_id)."</span></a></td>";}
		else {print"<td align=right><a href=ordre.php?sort=$sort&funktion=lookup&x=$x&fokus=$fokus&id=$id&vis=1&lager=$lager><span title='".findtekst(1518, $sprog_id)."'>".findteskt(1519, $sprog_id)."</span></a></td>";}
	}
		print" </tr>\n";

	$sort = if_isset($_GET['sort']);
	if (!$sort) $sort = 'varenr';


	$vare_id=array();
	if (($vis)&&($konto_id)) {
		$temp=" and lev_id = ".$konto_id;
	}

	$y=0;
	$skjul_vare_id=array();
	$vis_vare_id=array();
	$query = db_select("select * from vare_lev",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$y++;
		if (!$konto_id || !$vis || $row['lev_id']==$konto_id || $row['lev_id']=='0') {
			$vis_vare_id[$y]=$row['vare_id'];
		}	else $skjul_vare_id[$y]=$row['vare_id'];
	}

	if (!$sort) $sort = 'varenr';

	if (!$kontonr){$x++;}
	elseif ($x>1) {print "<td colspan=9><hr></td>";}
	if ($find) {
		$find=str_replace("*","%",$find);
		if ($fokus=='varenr'){
			$query = db_select("select * from varer where lukket != '1' AND $fokus='".$find."' order by $sort",__FILE__ . " linje " . __LINE__);
		} else {
			$query = db_select("select * from varer where lukket != '1' AND $fokus ILIKE '%".$find."%' order by $sort",__FILE__ . " linje " . __LINE__);
		}
	}
	else {
		$query = db_select("select * from varer where lukket != '1' order by $sort",__FILE__ . " linje " . __LINE__);
	}
	$vist=0;
	while ($row = db_fetch_array($query)) {
		$vare_id=$row['id'];
		if (($konto_id && !in_array($vare_id,$skjul_vare_id)) || in_array($vare_id,$vis_vare_id)) {
			$varenr=db_escape_string(trim($row['varenr']));
			$x=0;
			$query2 = db_select("select * from vare_lev where vare_id = $row[id] $temp",__FILE__ . " linje " . __LINE__);
			while ($row2 = db_fetch_array($query2)) {
				$x++;
				$y++;
				if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
				else {$linjebg=$bgcolor5; $color='#000000';}
				print "<tr bgcolor=\"$linjebg\">";
				print "<td><a href=\"ordre.php?vare_id=$vare_id&fokus=$fokus&konto_id=$row2[lev_id]&id=$id&lager=$lager\">".htmlentities($varenr,ENT_COMPAT,$charset)."</a></td>";
				print "<td>$row[enhed]<br></td>";
				print "<td> $row[beskrivelse]<br></td>";
				$salgspris=dkdecimal($row['salgspris'],2);
				print "<td align=right> $salgspris<br></td>";
				$kostpris=dkdecimal($row2['kostpris'],2);
				print "<td align=right> $kostpris<br></td>";
				if ($lager>=1){
					$q2 = db_select("select * from batch_kob where vare_id=$vare_id and rest>0 and lager=$lager",__FILE__ . " linje " . __LINE__);
					while ($r2 = db_fetch_array($q2)) {
						$q3 = db_select("select * from reservation where batch_kob_id=$r2[id]",__FILE__ . " linje " . __LINE__);
						while ($r3 = db_fetch_array($q3)) {$reserveret=$reserveret+$r3[antal];}
					}
					$linjetext="<span title= '".findtekst(1520, $sprog_id).": $reserveret'>";
					if ($r2= db_fetch_array(db_select("select beholdning from lagerstatus where vare_id=$row[id] and lager=$lager",__FILE__ . " linje " . __LINE__))) {
						print "<td align=right>$linjetext $r2[beholdning] &nbsp;</span></td>";
					} else print "<td align=right>$linjetext 0 &nbsp;</span></td>";
				}
				else {print "<td align=right> $row[beholdning] &nbsp;</td>"; }
#			print "<td></td>";

				$levquery = db_select("select kontonr, firmanavn from adresser where id=$row2[lev_id]",__FILE__ . " linje " . __LINE__);
				if ($levrow = db_fetch_array($levquery)){print "<td> ".htmlentities($levrow['firmanavn'],ENT_COMPAT,$charset)."</td>";}
				else {print "<td></td>";}
				print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]&lager=$lager\">Ret</a></td>";
				print "</tr>\n";
				$vist=1;
			}
#			if ($konto_id && $y==1) print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?vare_id=$vare_id&fokus=$fokus&konto_id=$row2[lev_id]&id=$id\">";
		}

		if ($kontonr && !$vist && $row['samlevare']!='on' && !in_array($vare_id,$skjul_vare_id)) {

#		if ((!in_array($row[id], $vare_id))&&($vist==0)&&($row['samlevare']!='on')&&($konto_id)) {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			print "<td><a href=\"ordre.php?vare_id=$vare_id&fokus=$fokus&id=$id&lager=$lager\">$row[varenr]</a></td>";
			print "<td>$row[enhed]<br></td>";
			print "<td> ".htmlentities($row['beskrivelse'],ENT_COMPAT,$charset)."<br></td>";
			$salgspris=dkdecimal($row['salgspris'],2);
			print "<td align=right> $salgspris<br></td>";
			$kostpris=dkdecimal($row['kostpris'],2);
			print "<td align=right> $kostpris<br></td>";
			print "<td></td><td></td>";
			print "<td align=right><a href=\"../lager/varekort.php?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus&id=$row[id]&lager=$lager\">Ret</a></td>";
			print "</tr>\n";
		}
	}
	if ($menu=='T') {
		include_once '../includes/topmenu/footer.php';
#	} else {
#		include_once '../includes/oldDesign/footer.php';
	}

	print "</tbody></table></td></tr></tbody></table>";
	exit;
}
######################################################################################################################################
function sidehoved($id, $returside, $kort, $fokus, $tekst) {
	global $bgcolor2;
	global $color;
	global $menu;
	global $sprog_id;
	global $top_bund;

	$title= 'Leverandør ordre';
	$alerttekst=findtekst(154,$sprog_id);

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	if ($kort) print "<div class=\"headerbtnLft headLink\"><a href=../kreditor/ordre.php?id=$id&fokus=$fokus accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";
	else print "<div class=\"headerbtnLft headLink\"><a href=\"javascript:confirmClose('../includes/luk.php?returside=$returside&tabel=ordrer&id=$id','$alerttekst')\" accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";
	print "<div class=\"headerTxt\">$title</div>";     	
	if (($kort!="../lager/varekort.php" && $returside != "ordre.php")&&($id)) {print "<div class=\"headerbtnRght headLink\"><a accesskey=N href=\"javascript:confirmClose('ordre.php?returside=ordreliste.php','$alerttekst')\" title='Klik her for at lave ny ordre'><i class='fa fa-plus-square fa-lg'></i></a></div>";}
	else if (($kort=="../lager/varekort.php" && $returside == "ordre.php")&&($id)) {print "<div class=\"headerbtnRghtheadLink\"><a accesskey=N href=\"$kort?returside=$returside&ordre_id=$id\"  title='Klik her for at lave ny ordre'><i class='fa fa-plus-square fa-lg'></i></a></div>";}
	elseif ($kort=="../kreditor/kreditorkort.php") {
		print "<div class=\"headerbtnRght headLink\"><a accesskey=V href=kreditorvisning.php title='Klik her for at ændre visning'><i class='fa fa-gear fa-lg'></i></a> &nbsp; <a accesskey=N href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" title='Klik her for at lave ny ordre'><i class='fa fa-plus-square fa-lg'></i></a></div>";
	}
	elseif (($id)||($kort!="../lager/varekort.php")) {print "<div class=\"headerbtnRght headLink\"><a accesskey=N href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" title='Klik her for at lave ny ordre'><i class='fa fa-plus-square fa-lg'></i></a></div>";}
	else {print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";}
	print "</div>";
	print "<div class='content-noside'>";
} else {
		print "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"><html><head><title>".findtekst(547,$sprog_id)."</title><meta http-equiv=\"content-type\" content=\"text/html; charset=ISO-8859-1\"></head>";
		print "<body bgcolor=\"#339999\" link=\"#000000\" vlink=\"#000000\" alink=\"#000000\" center=\"\">";
		print "<div align=\"center\">";

		print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
		print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
#	if ($returside != "ordre.php") {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$returside?tabel=ordrer&id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
#	else {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('ordre.php?id=$id','$alerttekst')\" accesskey=L>Luk</a></td>";}
		if ($kort) print "<td width=\"10%\" $top_bund> $color<a href=../kreditor/ordre.php?id=$id&fokus=$fokus accesskey=L>Luk</a></td>";
		else print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('../includes/luk.php?returside=$returside&tabel=ordrer&id=$id','$alerttekst')\" accesskey=L>".findtekst(30, $sprog_id)."</a></td>";
		print "<td width=\"80%\" $top_bund> $color$tekst</td>";
		if (($kort!="../lager/varekort.php" && $returside != "ordre.php")&&($id)) {print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('ordre.php?returside=ordreliste.php','$alerttekst')\" accesskey=N>".findtekst(39, $sprog_id)."</a></td>";}
		else if (($kort=="../lager/varekort.php" && $returside == "ordre.php")&&($id)) {print "<td width=\"10%\" $top_bund> $color<a href=\"$kort?returside=$returside&ordre_id=$id\" accesskey=N>".findtekst(39, $sprog_id)."</a></td>";}
		elseif ($kort=="../kreditor/kreditorkort.php") {
			print "<td width=\"5%\"$top_bund onClick=\"javascript:kreditor_vis=window.open('kreditorvisning.php','kreditor_vis','scrollbars=1,resizable=1');kreditor_vis.focus();\" onMouseOver=\"this.style.cursor = 'pointer'\"> <span title='".findtekst(1521, $sprog_id)."'><u>".findtekst(813, $sprog_id)."</u></span></td>"; #20210716
			print "<td width=\"5%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>".findtekst(39, $sprog_id)."</a></td>";
		}	elseif (($id)||($kort!="../lager/varekort.php")) {
		print "<td width=\"10%\" $top_bund> $color<a href=\"javascript:confirmClose('$kort?returside=../kreditor/ordre.php&ordre_id=$id&fokus=$fokus','$alerttekst')\" accesskey=N>".findtekst(39, $sprog_id)."</a></td>";
	}
	else {print "<td width=\"10%\" $top_bund><br></td>";}
	print "</tbody></table>";
	print "</td></tr>\n";
	print "<tr><td valign=\"top\" align=center>";
}
}
######################################################################################################################################
function indset_konto($id, $konto_id) {
	global $art;
	global $brugernavn;
	$tidspkt=date("U");

	$id=$id*1;
	$konto_id=$konto_id*1;
	$query = db_select("select * from adresser where id = '$konto_id' and art='K'",__FILE__ . " linje " . __LINE__); #20221104 added  and art='K'
	if ($row = db_fetch_array($query))
	{
		$kontonr=trim($row['kontonr']);
		$firmanavn=db_escape_string(trim($row['firmanavn']));
		$addr1=db_escape_string(trim($row['addr1']));
		$addr2=db_escape_string(trim($row['addr2']));
		$postnr=trim($row['postnr']);
		$bynavn=db_escape_string(trim($row['bynavn']));
		$land=db_escape_string(trim($row['land']));
		$betalingsdage=$row['betalingsdage'];
		$betalingsbet=trim($row['betalingsbet']);
		$cvrnr=trim($row['cvrnr']);
		$email=db_escape_string($row['email']);
		$mail_fakt=db_escape_string(trim($row['mailfakt']));
		if ($email && $mail_fakt) $udskriv_til="email";
		else $udskriv_til="PDF";
		$notes=db_escape_string(trim($row['notes']));
		$gruppe=trim($row['gruppe']);
		$valuta=trim($row['valuta']); # 20221104
	}
	if ($gruppe) {
		$query = db_select("select box1, box3,box9 from grupper where art='KG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		if (!$valuta) $valuta=trim($row['box3']); # 20221104
		$omlev=trim($row['box9']);
		if (substr($row['box1'],0,1)=='K') {
			$tmp= substr($row['box1'],1,1)*1;
			$query = db_select("select box2 from grupper where art='KM' and kodenr = '$tmp'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$momssats=trim($row['box2'])*1;
		} elseif (substr($row['box1'],0,1)=='E') {
			$momssats='0.00';
		} elseif (substr($row['box1'],0,1)=='Y') { 
			$momssats='0.00';
		}
	} elseif ($konto_id) print "<BODY onLoad=\"javascript:alert('Kreditor er ikke tilknyttet en kreditorgruppe')\">";
	$momssats=$momssats*1;
	if ((!$id)&&($firmanavn)) {
		$ordredate=date("Y-m-d");
		$query = db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$ordrenr=$row['ordrenr']+1;}
		else {$ordrenr=1;}

		db_modify("insert into ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, betalingsdage, betalingsbet, cvrnr, email, mail_fakt, udskriv_til, notes, art, ordredate, momssats, status, hvem, tidspkt, valuta, omvbet) values ($ordrenr, '$konto_id', '$kontonr', '$firmanavn', '$addr1', '$addr2', '$postnr', '$bynavn', '$land', '$betalingsdage', '$betalingsbet', '$cvrnr', '$email', '$mail_fakt', '$udskriv_til', '$notes', 'KO', '$ordredate', '$momssats', '0', '$brugernavn', '$tidspkt', '$valuta','$omlev')",__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where kontonr='$kontonr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$id=$row[id];}
	}
	elseif($firmanavn) {
		$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			db_modify("update ordrer set konto_id=$konto_id, kontonr='$kontonr', firmanavn='$firmanavn', addr1='$addr1', addr2='$addr2', postnr='$postnr', bynavn='$bynavn', land='$land', betalingsdage='$betalingsdage', betalingsbet='$betalingsbet', cvrnr='$cvrnr', momssats='$momssats', email='$email', mail_fakt='$mail_fakt', udskriv_til='$udskriv_til', notes='$notes', hvem = '$brugernavn', tidspkt='$tidspkt', valuta='$valuta' where id=$id",__FILE__ . " linje " . __LINE__);
		}
		else {
			$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"fejltekst('Ordren er overtaget af $row[hvem]')\">";}
			else {print "<BODY onLoad=\"fejltekst('Du er blevet smidt af')\">";}
		}
	}
	return $id;
}
#20221104 BEGIN
######################################################################################################################################
function indset_debitor($id, $debitor_id) {
	global $art;
	global $brugernavn;
	$tidspkt=date("U");

	$id=$id*1;
	$debitor_id=$debitor_id*1;
	$query = db_select("select * from adresser where id = '$debitor_id' and art='D'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query))
	{
		$lev_navn=db_escape_string(trim($row['firmanavn']));
		$lev_addr1=db_escape_string(trim($row['addr1']));
		$lev_addr2=db_escape_string(trim($row['addr2']));
		$lev_postnr=trim($row['postnr']);
		$lev_bynavn=db_escape_string(trim($row['bynavn']));
		$lev_kontakt=db_escape_string(trim($row['kontakt']));
	}
	if ((!$id)&&($lev_navn)) {
		$ordredate=date("Y-m-d");
		$query = db_select("select ordrenr from ordrer where art='KO' or art='KK' order by ordrenr desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$ordrenr=$row['ordrenr']+1;}
		else {$ordrenr=1;}

		db_modify("insert into ordrer (ordrenr, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_kontakt, art, ordredate, status, hvem, tidspkt) values ($ordrenr, '$lev_navn', '$lev_addr1', '$lev_addr2', '$lev_postnr', '$lev_bynavn', '$lev_kontakt', 'KO', '$ordredate', '0', '$brugernavn', '$tidspkt')",__FILE__ . " linje " . __LINE__);
		$query = db_select("select id from ordrer where ordrenr='$ordrenr' and ordredate='$ordredate' order by id desc",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {$id=$row[id];}
	}
	elseif($lev_navn) {
		$query = db_select("select tidspkt from ordrer where id=$id and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			db_modify("update ordrer set lev_navn='$lev_navn', lev_addr1='$lev_addr1', lev_addr2='$lev_addr2', lev_postnr='$lev_postnr', lev_bynavn='$lev_bynavn', lev_kontakt='$lev_kontakt', hvem = '$brugernavn', tidspkt='$tidspkt' where id=$id",__FILE__ . " linje " . __LINE__);
		}
		else {
			$query = db_select("select hvem from ordrer where id=$id",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {print "<BODY onLoad=\"fejltekst('Ordren er overtaget af $row[hvem]')\">";}
			else {print "<BODY onLoad=\"fejltekst('Du er blevet smidt af')\">";}
		}
	}
	return $id;
}
#20221104 END
######################################################################################################################################
function find_vare_id ($varenr) {
	if ($r=db_fetch_array(db_select("select id from varer where varenr = '$varenr' or stregkode = '$varenr'",__FILE__ . " linje " . __LINE__))) {
		return ($row['id']);
	}	else return (0);
}
######################################################################################################################################
function samlevare($id,$art,$v_id,$leveres) {
	global $lager;
	if ($art=='KO') {
		include ("../includes/fuld_stykliste.php");
		list($vare_id,$stk_antal,$antal) = fuld_stykliste($v_id, '', 'basisvarer');
		for ($x=1; $x<=$antal; $x++) {
			if ($r=db_fetch_array(db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__))) {
				$stk_antal[$x]=$stk_antal[$x]*$leveres;
				db_modify("insert into ordrelinjer (ordre_id,varenr,vare_id,beskrivelse,antal,leveres,pris,posnr,lager) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', '$r[kostpris]','100',$lager)",__FILE__ . " linje " . __LINE__);
			}
		}
	} 
/*
else {
#cho "select antal,posnr from ordrelinjer where id='$linje_id'<br>";
		$r=db_fetch_array(db_select("select antal,posnr,kred_linje_id from ordrelinjer where id='$linje_id'",__FILE__ . " linje " . __LINE__));
		$antal=$r['antal']*1;
		$posnr=$r['posnr']*1;
		$kred_linje_id=$r['kred_linje_id']*1;
#cho "$antal select id,antal from ordrelinjer where id='$kred_linje_id'<br>";
		if ($antal && $r=db_fetch_array(db_select("select id,antal from ordrelinjer where id='$kred_linje_id'",__FILE__ . " linje " . __LINE__))) {
			$org_antal=$r['antal'];
#cho "select * from ordrelinjer where samlevare='$r[id]'<br>";
			$q=db_select("select * from ordrelinjer where samlevare='$r[id]'",__FILE__ . " linje " . __LINE__);
			while ($r=db_fetch_array($q)) {
				$ny_antal=afrund($r['antal']*$org_antal/$antal,2);
#cho "insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, posnr) 
					values 
				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', '$r[pris]', '$r[posnr]' )<br>";
				db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, posnr) 
					values 
				('$id', '$r[varenr]', '$r[vare_id]', '$r[beskrivelse]', '$ny_antal', '$ny_antal', '$r[pris]', '$r[posnr]' )",__FILE__ . " linje " . __LINE__);
			}
		}
	}
*/
#exit;
}
##############################################################################
function indsaet_linjer($ordre_id, $linje_id, $posnr) {
	$posnr = str_replace('+',':',$posnr); #jeg ved ikke hvorfor, men den vil ikke splitte med "+"
	list ($posnr, $antal) = explode (':', $posnr);
	db_modify("update ordrelinjer set posnr='$posnr' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
	for ($x=1; $x<=$antal; $x++) {
		db_modify("insert into ordrelinjer (posnr, ordre_id) values ('$posnr', '$ordre_id')",__FILE__ . " linje " . __LINE__);
	}
}
if ($fokus) {
	print "<script language=\"javascript\">";
	print "document.ordre.$fokus.focus();";
	print "</script>";
}
print "</tbody></table>
</td></tr>
</tbody></table>";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
#} else {
#	include_once '../includes/topmenu/footer.php';
}

?>
