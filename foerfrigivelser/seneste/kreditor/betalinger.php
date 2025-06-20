<?php
// ---------kreditor/betalinger.php----------Patch 1.9.4---------2008-04-16----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2008 DANOSOFT ApS
// -----------------------------------------------------------------------------------

@session_start();
$s_id=session_id();
		
$modulnr=12;	
$title="betalinger";	
		
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/settings.php");
include("../includes/std_func.php");
include("../includes/db_query.php");
include("../includes/forfaldsdag.php");

$liste_id=isset($_GET['liste_id'])? $_GET['liste_id']:Null;
#$sort=isset($_GET['sort'])? $_GET['sort']:Null;
#$rf=isset($_GET['rf'])? $_GET['rf']:Null;
#$vis=isset($_GET['vis'])? $_GET['vis']:Null;
$find=isset($_GET['find'])? $_GET['find']:Null;

if ($_POST) {
	$id=array();$erh=array();
	$slet_ugyldige=isset($_POST['slet_ugyldige'])? $_POST['slet_ugyldige']:Null;
#	$liste_id=($_POST['liste_id'])? $_POST['liste_id']:Null;
	$listenote=($_POST['listenote'])? $_POST['listenote']:Null;
	$udskriv=isset($_POST['udskriv'])? $_POST['udskriv']:Null;
	$id=isset($_POST['id'])? $_POST['id']:Null;
	$erh=isset($_POST['erh'])? $_POST['erh']:Null;
	$fra_kto=isset($_POST['fra_kto'])? $_POST['fra_kto']:Null;
	$egen_ref=isset($_POST['egen_ref'])? $_POST['egen_ref']:Null;
	$til_kto=isset($_POST['til_kto'])? $_POST['til_kto']:Null;
	$kort_ref=isset($_POST['kort_ref'])? $_POST['kort_ref']:Null;
	$modt_navn=isset($_POST['modt_navn'])? $_POST['modt_navn']:Null;
	$belob=isset($_POST['belob'])? $_POST['belob']:Null;
	$valuta=isset($_POST['valuta'])? $_POST['valuta']:Null;
	$betalingsdato=isset($_POST['betalingsdato'])? $_POST['betalingsdato']:Null;
	$slet=isset($_POST['slet'])? $_POST['slet']:Null;
	$ugyldig=isset($_POST['ugyldig'])? $_POST['ugyldig']:Null;
	$antal=isset($_POST['antal'])? addslashes($_POST['antal']):Null;

	for ($x=1;$x<=$antal;$x++) {
		if ($slet_ugyldige && $ugyldig[$x]) $slet[$x]='on';
		if ($slet[$x]=='on') db_modify("delete from betalinger where id='$id[$x]'");
		else db_modify("update betalinger set bet_type='$erh[$x]',fra_kto='$fra_kto[$x]',egen_ref='$egen_ref[$x]',til_kto='$til_kto[$x]',kort_ref='$kort_ref[$x]',modt_navn='$modt_navn[$x]',belob='$belob[$x]',valuta='$valuta[$x]',betalingsdato='$betalingsdato[$x]' where id='$id[$x]'");
	}
	db_modify("update betalingsliste set listenote='$listenote' where id='$liste_id'");
	if ($udskriv) db_modify("update betalingsliste set bogfort='V', bogfort_af='$brugernavn' where id='$liste_id'");
}

if (!$liste_id) $liste_id=0;
$linjebg=$bgcolor;
$erh_title= "ERH351 = FI kort 71\nERH352 = FI kort 04 & 15\nERH354 = FI kort 01 & 41\nERH355 = Bankoverf. med normal advisering\nERH356 = Bankoverf. med straks advisering\nERH357 = FI kort 73\nERH358 = FI kort 75\nERH400 = Udenlandsk overf&oslash;rsel\n";


?>
		<table width="100%" height="100%" border="0" cellspacing="0" cellpadding="0"><tbody>
		<tr><td height = "25" align="center" valign="top">
		<table width="100%" align="center" border="0" cellspacing="2" cellpadding="0"><tbody>
		<td width="10%" <?php echo $top_bund ?>><font face="Helvetica, Arial, sans-serif" color="#000066"><small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>
		<td width="80%" <?php echo $top_bund ?> ><font face="Helvetica, Arial, sans-serif" color="#000066"><small>Betalinger til bank</small></td>
		<td width="10%" <?php echo $top_bund ?> ><font face="Helvetica, Arial, sans-serif" color="#000066"><small><?php print "<a href=betalinger.php?liste_id=$liste_id&find=nye>Find nye</a>"?></small></td>

		</tbody></table>
		</td></tr>
		<tr><td valign="top">
		<table cellpadding="1" cellspacing="0" border="0" width="100%" valign = "top">
<?php
if (!$liste_id) {
	$tidspkt=microtime();
	$listedate=date("Y-m-d");
	db_modify("insert into betalingsliste(listedate, listenote, oprettet_af, hvem, tidspkt, bogfort) values ('$listedate', '$listenote', '$brugernavn', '$brugernavn', '$tidspkt', '-')");
	$r=db_fetch_array(db_select("select MAX(id) as id from betalingsliste where tidspkt='$tidspkt'"));
	$liste_id=$r['id'];
} 
if ($find) {
	$bilag_id_list=array();
	$ordre_id_list=array();
	$x=0; $y=0;
	$r=db_fetch_array(db_select("select bank_reg, bank_konto from adresser where art = 'S'"));
	$egen_bank=$r['bank_reg'].$r['bank_konto'];
	$q=db_select("select ordre_id, bilag_id from betalinger");
	while ($r=db_fetch_array($q)){
		if ($r['bilag_id']) {
			$x++;
			$bilag_id_list[$x]=$r['bilag_id']*1;
		}
		if ($r['ordre_id']) {
			$y++;
			$ordre_id_list[$y]=$r['ordre_id']*1;
		}
	}
	$q=db_select("select openpost.id as id, openpost.beskrivelse as egen_ref, openpost.amount as amount, openpost.valuta as valuta, 
							 openpost.faktnr as faktnr, openpost.transdate as transdate,openpost.bilag_id as bilag_id, 
							 adresser.erh as erh, openpost.refnr as refnr, openpost.kladde_id as kladde_id, adresser.bank_reg as modt_reg, adresser.bank_konto as modt_konto, adresser.firmanavn as modt_navn, 
	adresser.bank_fi as modt_fi, adresser.betalingsbet as betalingsbet, adresser.betalingsdage as betalingsdage
							from openpost, adresser where openpost.udlignet != 1 and openpost.amount < 0 and openpost.konto_id = adresser.id and adresser.art = 'K'");
	while ($r=db_fetch_array($q)){
		$ordre_id=0;
		$medtag=1;
		$kladde_id=$r['kladde_id']*1;
		$bilag_id=$r['bilag_id']*1;
		$refnr=$r['refnr']*1;
		if ($kladde_id && in_array($bilag_id,$bilag_id_list)) $medtag=0;
		if (!$kladde_id && in_array($refnr,$ordre_id_list)) $medtag=0;
		elseif (!$kladde_id) $ordre_id=$refnr;
		if ($medtag) {
			$modt_konto=$r['modt_reg'].$r['modt_konto'];
			if ($r['erh']) $erh=$r['erh'];
			elseif ($r['modt_fi']) $erh="ERH351";
			else 	{
				$erh="ERH356";
				if ($r['faktnr']) $kort_ref="Fakt: ".$r['faktnr'];
			}	
			if ($erh=="ERH351" || $erh=="ERH357" || $erh=="ERH358") $modt_konto = $r['modt_fi']; 
			$forfaldsdag=(str_replace("-","",forfaldsdag($r['transdate'], $r['betalingsbet'], $r['betalingsdage'])));
			$belob=dkdecimal($r['amount']*-1);
			$valuta=$r['valuta'];
			if (!$valuta) $valuta='DKK';
#echo "insert into betalinger(bet_type,fra_kto, egen_ref, til_kto, modt_navn, amount, betalingsdato, swift, valuta, bilag_id, ordre_id, liste_id) values ('$erh','$egen_bank','$r[egen_ref]','$modt_konto','$r[modt_navn]','$amount','$forfaldsdag', '$r[swift]', '$valuta', '$bilag_id', '$ordre_id','$liste_id')<br>";			
			db_modify("insert into betalinger(bet_type,fra_kto, egen_ref, til_kto, modt_navn, kort_ref, belob, betalingsdato, valuta, bilag_id, ordre_id, liste_id) values ('$erh','$egen_bank','$r[egen_ref]','$modt_konto','$r[modt_navn]','$kort_ref','$belob','$forfaldsdag', '$valuta', '$bilag_id', '$ordre_id','$liste_id')");
		}
	}
}
if ($udskriv) {
	$q=db_select("select * from betalinger where liste_id=$liste_id");
	while ($r=db_fetch_array($q)) {
		list($kort_ref,$k1,$k2,$k3,$k4,$k5,$k6)=betalingskontrol($erh[$x], $r['fra_kto'],$r['egen_ref'],$r['til_kto'],$r['kort_ref'],$r['belob'],$r['valuta'],$r['betalingsdato']);
		if($k1||$k2||$k3||$k4||$k5||$k6) $udskriv=NULL;
	}
}
if ($udskriv) {
	$filnavn="../temp/$db_id"."$bruger_id".".txt";
	$fp=fopen("$filnavn","w");
	$q=db_select("select * from betalinger where liste_id=$liste_id order by betalingsdato");
	while ($r=db_fetch_array($q)) {
		$linje="\"$r[bet_type]\",\"$r[fra_kto]\",\"$r[egen_ref]\",\"$r[til_kto]\",,\"01\",\"01\",,\"$r[belob]\",\"$r[betalingsdato]\",\"$r[kort_ref]\",,,,,,,,,\"N\"\r\n";
		fwrite($fp,$linje);
	}	
	fclose($fp);
	print "<tr><td colspan=3 height=200 widht=100%><br></td></tr>";
	print "<tr><td width=40%><br></td><td $top_bund title=\"Klik p� knappen for at &aring;bne betalingsfilen eller h&oslash;jreklik for at gemme\"> $font<a href='$filnavn'>Se / gem betalingsfil</a></td><td width=40%><br></td></tr>";
} else { 
	print "<form name=\"betalinger\" action=\"betalinger.php?liste_id=$liste_id\" method=\"post\">";
	$r=db_fetch_array(db_select("select listenote, bogfort from betalingsliste where id='$liste_id'"));
	$listenote=$r['listenote'];
	$bogfort=$r['bogfort'];
	print "<tr>";
	if ($bogfort!='-'){
		print "<td colspan=11 align=center><b>$font $listenote</b></td></tr>";
		print "<td colspan=11><small><small><hr></small></small></td></tr>";
	}	else {
		print "<td><small><b>$font <span title= 'Her kan skrives en bem&aelig;rkning til kladden'>Bem&aelig;rkning:</b></small></td>";
		print "<td colspan=10><input type=\"text\" size=95 name=listenote value=\"$listenote\"></td></tr>";
	}
	#print"<tr><td colspan=11><hr></td></tr>";
	print "<tr>
		<td>$font<small><span title=\"$erh_title\"><b>Betalingstype</b></span></small></td>
		<td>$font<small><b>Fra konto</b></small></td>
		<td>$font<small><b>Egen ref.</b></small></td>
		<td>$font<small><b>Modtager konto</b></small></td>
		<td>$font<small><b>Modtager ref.</b></small></td>
		<td>$font<small><b>Modtager</b></small></td>
		<td align=center>$font<small><b>Bel&oslash;b</b></small></td>
		<td align=center>$font<small><b>Valuta</b></small></td>
		<td align=center>$font<small><b>Betalingsdato</b></small></td>
		<td align=center><span title='Se i nyt vindue'>$font<small><b>Se</b></span></small></td>";
		if ($bogfort!='V') print "<td align=center><span title='Slet linjen fra listen'>$font<small><b>Slet</b></span></small></td>";
		print "</tr>";
#print"<tr><td colspan=11><hr></td></tr>";
	$x=0;
# echo "select betalinger.bet_type as bet_type,betalinger.fra_kto as fra_kto, betalinger.egen_ref as egen_ref, betalinger.til_kto as til_kto, betalinger.modt_navn as modt_navn, betalinger.kort_ref as kort_ref, betalinger.belob as belob, betalinger.betalingsdato as betalingsdato, kassekladde.bilag as bilag, ordrer.modtagelse as modtagelse from betalinger, ordrer, kassekladde where betalinger.liste_id=$liste_id and ordrer.id=betalinger.ordre_id and kassekladde.id=betalinger.bilag_id order by betalinger.betalingsdato<br>";
	$erh=array();
	$q=db_select("select * from betalinger where liste_id=$liste_id order by betalingsdato");
	while ($r=db_fetch_array($q)) {
		$x++;
		$erh[$x]=$r['bet_type'];
		$fra_kto[$x]=$r['fra_kto'];
		$egen_ref[$x]=$r['egen_ref'];
		$til_kto[$x]=$r['til_kto'];
		$kort_ref[$x]=$r['kort_ref'];
		$belob[$x]=$r['belob'];
		$valuta[$x]=$r['valuta'];
		$betalingsdato[$x]=$r['betalingsdato'];
		
		
		if ($r['ordre_id']) {
			$r2=db_fetch_array(db_select("select modtagelse from ordrer where id = '$r[ordre_id]'"));
		} elseif ($r['bilag_id']) {
			$r2=db_fetch_array(db_select("select kladde_id, bilag from kassekladde where id = '$r[bilag_id]'"));
		}
		if ($bogfort && $bogfort!='V') {
			list($kort_ref[$x],$k1,$k2,$k3,$k4,$k5,$k6)=betalingskontrol($erh[$x], $r['fra_kto'],$r['egen_ref'],$r['til_kto'],$r['kort_ref'],$r['belob'],$r['valuta'],$r['betalingsdato']);
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			if($k1||$k2||$k3||$k4||$k5||$k6) {
				print "<tr bgcolor=\"#FF0000\">";
				print "<input type=\"hidden\" name=\"ugyldig[$x]\" value=\"$r[id]\">";
			}
			else print "<tr bgcolor=\"$linjebg\">";
				print "<td><span title=\"$erh_title\"><SELECT NAME=erh[$x]>\n";
				if ($erh[$x]) print "<option>$erh[$x]</option>\n";
				if ($erh[$x]!='ERH351') print "<option>ERH351</option>\n";
				if ($erh[$x]!='ERH352') print "<option>ERH352</option>\n";
				if ($erh[$x]!='ERH354') print "<option>ERH354</option>\n";
				if ($erh[$x]!='ERH355') print "<option>ERH355</option>\n";
				if ($erh[$x]!='ERH356') print "<option>ERH356</option>\n";
				if ($erh[$x]!='ERH357') print "<option>ERH357</option>\n";
				if ($erh[$x]!='ERH358') print "<option>ERH358</option>\n";
				if ($erh[$x]!='ERH400') print "<option>ERH400</option>\n";
				print "<option>Slet</option>\n";
				print "</SELECT></span></td>\n";
				print "
					<td><span title=\"$k1\">$font<small><input type=\"text\" style=\"text-align:right\" name=\"fra_kto[$x]\" size=\"25\" value=\"$r[fra_kto]\"></small></span></td>
					<td><span title=\"\">$font<small><input type=\"text\" name=\"egen_ref[$x]\" size=\"60\" value=\"$r[egen_ref]\"></small></span></td>
					<td><span title=\"$k3\">$font<small><input type=\"text\" style=\"text-align:right\" name=\"til_kto[$x]\" size=\"25\" value=\"$r[til_kto]\"></small></span></td>
					<td><span title=\"$k2\">$font<small><input type=\"text\" style=\"text-align:right\" name=\"kort_ref[$x]\" size=\"25\" value=\"$kort_ref[$x]\"></small></span></td>
					<td><span title=\"\">$font<small><input type=\"text\" name=\"modt_navn[$x]\" size=\"40\" value=\"$r[modt_navn]\"></small></span></td>
					<td><span title=\"$k4\">$font<small><input type=\"text\" style=\"text-align:right\" name=\"belob[$x]\" size=\"15\" value=\"$r[belob]\"></small></span></td>
					<td><span title=\"$k5\">$font<small><input type=\"text\" style=\"text-align:right\" name=\"valuta[$x]\" size=\"5\" value=\"$r[valuta]\"></small></span></td>
					<td><span title=\"$k6\">$font<small><input type=\"text\" name=\"betalingsdato[$x]\" size=\"15\" value=\"$r[betalingsdato]\"></small></span></td>";
				if ($r['ordre_id'])	print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$r[ordre_id]','k_ordre','width=800,height=400,scrollbars=1,resizable=1')\">$font<small><span title=\"Se modtagelse i nyt vindue\"><u>M:$r2[modtagelse]</u></span></small></td>";
				else print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:kaskl=window.open('../finans/kassekladde.php?kladde_id=$r2[kladde_id]','kaskl','width=800,height=400,scrollbars=1,resizable=1')\">$font<small><span title=\"Se bilag i nyt vindue\"><u>B:$r2[bilag]</u></span></small></td>";		
				print	"<td><span title=\"Slet linje fra liste\">$font<small><input type=\"checkbox\" name=\"slet[$x]\" size=\"15\"></small></span></td>";
				print "</tr>";
				print "<input type=\"hidden\" name=\"id[$x]\" value=\"$r[id]\">";
				print "<input type=\"hidden\" name=\"antal\" value=\"$x\">";
		} else {
			if ($linjebg!=$bgcolor){$linjebg=$bgcolor; $color='#000000';}
			else {$linjebg=$bgcolor5; $color='#000000';}
			print "<tr bgcolor=\"$linjebg\">";
			print "<td>$erh[$x]</td><td>$r[fra_kto]</td><td>$r[egen_ref]</td><td>$r[til_kto]</td><td>$kort_ref[$x]</td><td>$r[modt_navn]</td><td align=right>$r[belob]</td><td align=center>$r[valuta]</td><td align=right>$r[betalingsdato]</td>";
				if ($r['ordre_id'])	print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$r[ordre_id]','k_ordre','width=800,height=400,scrollbars=1,resizable=1')\">$font<small><span title=\"Se modtagelse i nyt vindue\"><u>M:$r2[modtagelse]</u></span></small></td></tr>";
				else print "<td align=right onMouseOver=\"this.style.cursor = 'pointer'\"; onClick=\"javascript:kaskl=window.open('../finans/kassekladde.php?kladde_id=$r2[kladde_id]','kaskl','width=800,height=400,scrollbars=1,resizable=1')\">$font<small><span title=\"Se bilag i nyt vindue\"><u>B:$r2[bilag]</u></span></small></td></tr>";		
			
#	if ($r['ordre_id']) print "<td>$r[ordre_id]</td></tr>";
#			else print "<td>$r2[kladde_id]</td></tr>";
		}
	}
	print "<tr><td colspan=11 align=center>";
	if ($bogfort!='V') {
		print "<input type=submit accesskey=\"g\" value=\"Gem\" name=\"gem\">";
		if(!$k1&&!$k2&&!$k3&&!$k4&&!$k5&&!$k6) { 
			if ( strlen("which ps2pdf")) {
				print "&nbsp;<input type=submit accesskey=\"u\" value=\"Udskriv og luk\" name=\"udskriv\">";
			} else {
				print "&nbsp;<input type=submit accesskey=\"u\" value=\"Udskriv og luk\" name=\"udskriv\" disabled=\"disabled\">";
			}
		} else {
			print "<span title='Klik her for at fjerne alle ugyldige linjer'>&nbsp;<input type=submit accesskey=\"u\" value=\"Slet r&oslash;de\" name=\"slet_ugyldige\"></span>";
		}
	} else print "<input type=submit accesskey=\"u\" value=\"Udskriv\" name=\"udskriv\">";
}
	
print "</td></tr>";
print "</form>";
##############################################################################################################
function betalingskontrol($erh, $fra_kto,$egen_ref,$til_kto,$kort_ref,$belob,$valuta,$betalingsdato)
{
	$k1=NULL;$k2=NULL;$k3=NULL;$k4=NULL;$k5=NULL;$k6=NULL;
	if (!$fra_kto || !is_numeric($fra_kto)||strlen($fra_kto)!=14) $k1 = "Egen konto ikke gyldig";
		if ($erh=='ERH351'||erh=='ERH352'||$erh=='ERH358') {
			if (!$til_kto || !is_numeric($til_kto)||strlen($til_kto)!=8) $k3 = "Modtager konto ikke gyldig";
			if(!$kort_ref || !is_numeric($kort_ref)) $k2 = "Ugyldig betalingsidentifikation (modt. ref)";
		else{
			if ($erh=='ERH351') $len=15; #strlen af ERH351 skal vaere 15
			else $len=16;
			for($x=strlen($kort_ref);$x<$len;$x++) $kort_ref='0'.$kort_ref;
			for ($x=$len-1;$x>=0;$x--){ #Beregning af kontrolciffer.
				$y=substr($kort_ref,$x,1)*2;
				$x--;
				$y=substr($kort_ref,$x,1)*1;
			}
			while ($y>9) { #Reduktion af kontrolciffer
				$y=substr($y,0,1)+$y=substr($y,1,1);	
			}
			if (substr($kort_ref,-1) != $y) $kommentar = "Ugyldig betalingsidentifikation (modt. ref)";
		}
	} elseif ($erh=='ERH355'||$erh=='ERH356') {
		if (!$til_kto || !is_numeric($til_kto)||strlen($til_kto)!=14) $k3 = "Modtager konto ikke gyldig";
		if(!$kort_ref) $k2 = "Modt ref skal udfyldes";
	}
	if (usdecimal($belob)<0.01) $k4="Bel&oslash;b skal v�re st&oslash;rre end 0";
	if ($valuta!='DKK') $k5="Ugyldig valuta, kun DKK kan anvendes";	
	if (strlen($betalingsdato)!=8) $k6="ugyldig dato - skal v&aelig;re i formatet ddmmyyyy";
	$dag=substr($betalingsdato,0,2);
	$md=substr($betalingsdato,2,2);
	$aar=substr($betalingsdato,4);
	$bd=$aar.$md.$dag;
	$dd=date("Ymd");
	if ($dd>$bd) $k6="Betalingsdato er overskredet";
	if (!checkdate($md, $dag, $aar)) $k6="ugyldig dato - skal v&aelig;re i formatet ddmmyyyy";
	#	echo "$kort_ref,$kommentar -- ";
	return(array($kort_ref,$k1,$k2,$k3,$k4,$k5,$k6));
}

?>
</tbody>
</table>
	</td></tr>
</tbody></table>

</body></html>
