<?php

// -----------lager/rapport.php------------patch 3.3.3-------2013.08.27----
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
// 2013.02.10 Break ændret til break 1
// 2013.03.18 $modulnr ændret fra 12  til 15
// 2013.08.27 Større omskrivning for bedre datovalg og detaljeringsgrad 


	@session_start();
	$s_id=session_id();
	$css="../css/standard.css";
 
	$title="Varerapport";
	$modulnr=15;
  
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/forfaldsdag.php");
#	include("../includes/db_query.php");

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (isset($_POST['submit']) && $_POST['submit']) {
	$submit=strtolower(trim($_POST['submit']));
	$varegruppe=trim($_POST['varegruppe']);
	$date_from=usdate($_POST['dato_fra']);
	$date_to=usdate($_POST['dato_til']);
#	$md=$_POST['md'];
	$varenr = $_POST['varenr'];
	$varenavn = $_POST['varenavn'];
	$detaljer = $_POST['detaljer'];
	
	$varenr = trim($varenr);
	$varenavn = trim($varenavn);
} else {
	$varegruppe=if_isset($_GET['varegruppe']);
	$date_from=if_isset($_GET['date_from']);
	$date_to=if_isset($_GET['date_to']);
	$varenr=if_isset($_GET['varenr']);
	$varenavn=if_isset($_GET['varenavn']);
	$detaljer = $_GET['detaljer'];
	$submit=if_isset($_GET['submit']);
}
#$md[1]="januar"; $md[2]="februar"; $md[3]="marts"; $md[4]="april"; $md[5]="maj"; $md[6]="juni"; $md[7]="juli"; $md[8]="august"; $md[9]="september"; $md[10]="oktober"; $md[11]="november"; $md[12]="december";

#if (strstr($varegruppe, "ben post")) {$varegruppe="openpost";}
#cho "$date_from, $date_to, $varenr, $varenavn, $varegruppe,$detaljer<br>";
if ($submit == 'ok') varegruppe ($date_from, $date_to, $varenr, $varenavn, $varegruppe,$detaljer); 
elseif ($submit == 'lagerstatus') print print "<meta http-equiv=\"refresh\" content=\"0;URL=lagerstatus.php?varegruppe=$varegruppe\">";
elseif (strpos($submit,'ageropt')) print print "<meta http-equiv=\"refresh\" content=\"0;URL=optalling.php?varegruppe=$varegruppe\">";
else 	forside ($date_from,$date_to,$varenr,$varenavn,$varegruppe,$detaljer);
#cho "$submit($regnaar, $date_from, $date_to, $varenr, $varenavn, $varegruppe)";

#############################################################################################################
function forside($date_from,$date_to,$varenr,$varenavn,$varegruppe,$detaljer)
{

	#global $connection;
	global $brugernavn;
	global $top_bund;
	global $md;
	global $returside;
	global $popup;
	global $jsvars;

#	$regnaar=$regnaar*1; #fordi den er i tekstformat og skal vaere numerisk
	($date_from)?$dato_fra=dkdato($date_from):$dato_fra="01-01-".date("Y");
	($date_to)?$dato_til=dkdato($date_to):$dato_til=date("d-m-Y");
	if (!$varenr) $varenr="*";
	if (!$varenavn) $varenavn="*";
	if ($detaljer) $detaljer='checked';
	
#	if (!$regnaar) {
#		$query = db_select("select regnskabsaar from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
#		$row = db_fetch_array($query);
#		$regnaar = $row['regnskabsaar'];
#	}
#	$query = db_select("select * from grupper where art = 'RA' order by box2",__FILE__ . " linje " . __LINE__);
#	$x=0;
#	while ($row = db_fetch_array($query)){
#		$x++;
#		$regnaar_id[$x]=$row['id'];
#		$regn_beskrivelse[$x]=$row['beskrivelse'];
#		$start_md[$x]=$row['box1']*1;
#		$start_aar[$x]=$row['box2']*1;
#		$slut_md[$x]=$row['box3']*1;
#		$slut_aar[$x]=$row['box4']*1;
#		$regn_kode[$x]=$row['kodenr'];
#		if ($regnaar==$row['kodenr']){$aktiv=$x;}
#	}
#	$antal_regnaar=$x;

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\" align=\"center\"><tbody>"; #A
	print "<tr><td width=100%>";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund><a href=$returside accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Varerapport - forside</td>";
	print "<td width=\"10%\" $top_bund><br></td></tr>";
	print "</tbody></table></td></tr>"; #B slut
	print "</tr><tr><td height=\"60%\" \"width=100%\" align=\"center\" valign=\"bottom\">";
#	print "<form name=regnskabsaar action=rapport.php method=post>";
#	print "<table cellpadding=\"1\" cellspacing=\"5\" border=\"1\"><tbody>";
#	print "<tr><td align=center><h3>Rapporter<br></h3></td></tr>";
#	print "<tr><td align=center><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\" width=\"100%\"><tbody>";
#	print "<tr><td> Regnskabs&aring;r</td><td width=100><select class=\"inputbox\" name=regnaar>";
#	print "<option>$regnaar - $regn_beskrivelse[$aktiv]</option>";
#	for ($x=1; $x<=$antal_regnaar;$x++) {
#		if ($x!=$aktiv) {print "<option>$regn_kode[$x] - $regn_beskrivelse[$x]</option>";}
#	}
	
#	print "</td><td width=100 align=center><input type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
#	print "</form>";
	print "<form name=rapport action=rapport.php method=post>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<tr><td align=\"center\" colspan=\"3\"><h3>Varerapport<br></h3></td></tr>";
	print "<td> Varegruppe </td><td colspan=\"2\"><select class=\"inputbox\" name=\"varegruppe\">";
	if ($varegruppe) print "<option>$varegruppe</option>";
	if ($varegruppe!="0:Alle") print "<option>0:Alle</option>";
	$query = db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		if ($varegruppe!=$row['kodenr'].":".$row['beskrivelse']) print "<option>$row[kodenr]:$row[beskrivelse]</option>";
	}
	print "</select>Detaljeret <input type=\"checkbox\" name=\"detaljer\" $detaljer>"; 
	print "</td></tr>";
	
	print "<tr>";
	print "	<td> Periode</td>";
	print "	<td><input class=\"inputbox\" type=\"text\" name=\"dato_fra\" value=\"$dato_fra\"></td>";
	print "	<td><input class=\"inputbox\" type=\"text\" name=\"dato_til\" value=\"$dato_til\"></td>";
	print "	</tr>";
	print "<tr><td> Varenr</td><td colspan=\"2\"><input class=\"inputbox\" name=\"varenr\" value=\"$varenr\"></td></tr>";
	print "<tr><td> Varenavn</td><td colspan=\"2\"><input class=\"inputbox\" name=\"varenavn\" value=\"$varenavn\"></td></tr>";
	print "<tr><td colspan=5 align=center><input type=submit value=\"  OK  \" name=\"submit\"></td></tr>";
	print "</tbody></table>";
	print "<tr><td ALIGN=\"center\" Valign=\"top\" height=39%><table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>\n";
#	if ($popup) {
#		print "<tr><td ALIGN=center onClick=\"javascript:lagerstatus=window.open('lagerstatus.php','lagerstatus','$jsvars');lagerstatus.focus();\"><span title='Se lagerstatus p&aring; vilk&aring;rlig dato'><input type=\"submit\" style=\"width:120px;\" value=\"Lagerstatus\" name=\"submit\"></span></td>";
#		print "<td ALIGN=center onClick=\"javascript:optalling=window.open('optalling.php','optalling','$jsvars');optalling.focus();\"><span title='Funktion til opt&aelig;lling og regulering af varelager'><input type=\"submit\" style=\"width:120px;\" value=\"Lageropt&aelig;lling\" name=\"submit\"></span></td></tr>";
#	} else {
		print "";
		print "<tr><td ALIGN=center><span title='Se lagerstatus p&aring; vilk&aring;rlig dato'><input style=\"width:120px;\" type=submit value=\"Lagerstatus\" name=\"submit\"></span></td>";
		print "<td ALIGN=center><span title='Funktion til opt&aelig;lling og regulering af varelager'><input style=\"width:120px;\" type=submit value=\"Lageropt&aelig;lling\" name=\"submit\"></span></td></tr>";
#	}
	print "</form>";
	print "</tbody></table>\n";
	print "</td></tr>";
	
}

##################################################################################################
function varegruppe($date_from,$date_to,$varenr,$varenavn,$varegruppe,$detaljer)
{
#	global $connection;
	global $top_bund;
	global $md;
	global $returside;
	global $jsvars;
	

	list($gruppenr, $tmp)=explode(":",$varegruppe); 

#	if ($returside) $luk= "<a accesskey=L href=\"$returside\">";
#	else 
	$luk= "<a accesskey=L href=\"rapport.php?varegruppe=$varegruppe&date_from=$date_from&date_to=$date_to&varenr=$varenr&varenavn=$varenavn&detaljer=$detaljer\">";

	print "<table width = 100% cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";
	print "<tr><td colspan=\"8\" height=\"8\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"3\" cellpadding=\"0\"><tbody>"; #B
	print "<td width=\"10%\" $top_bund>$luk Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Rapport - varesalg</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>"; #B slut
	print "</td></tr>";

	$r = db_fetch_array(db_select("select box9 from grupper where kodenr ='$gruppenr' and art='VG'",__FILE__ . " linje " . __LINE__));
	$batch_kontrol=$r['box9'];
	
	
	
	$kontonr=array();
	$x=0;
	$tmp="";
	if ($gruppenr) $tmp = "where ".nr_cast(gruppe)."=$gruppenr"; 
	if ($varenr && $varenr != '*') {
		if (strstr($varenr, "*")) {
			if (substr($varenr,0,1)=='*') $varenr="%".substr($varenr,1);
			if (substr($varenr,-1,1)=='*') $varenr=substr($varenr,0,strlen($varenr)-1)."%";
		} 
		$low=strtolower($varenr);
		$upp=strtoupper($varenr);
		if ($tmp) $tmp.=" and (varenr LIKE '$varenr' or lower(varenr) LIKE '$low' or upper(varenr) LIKE '$upp')";
		else $tmp =  "where (varenr LIKE '$varenr' or lower(varenr) LIKE '$low' or upper(varenr) LIKE '$upp')";
	}
	if ($varenavn && $varenavn != '*') {
		if (strstr($varenavn, "*")) {
			if (substr($varenavn,0,1)=='*') $varenavn="%".substr($varenavn,1);
			if (substr($varenavn,-1,1)=='*') $varenavn=substr($varenavn,0,strlen($varenavn)-1)."%";
		} 
		$low=strtolower($varenavn);
		$upp=strtoupper($varenavn);
		if ($tmp) $tmp.=" and (beskrivelse LIKE '$varenavn' or lower(beskrivelse) LIKE '$low' or upper(beskrivelse) LIKE '$upp')";
		else $tmp =  "where (beskrivelse LIKE '$varenavn' or lower(beskrivelse) LIKE '$low' or upper(beskrivelse) LIKE '$upp')";
	}
	$qtxt="select id from varer $tmp order by beskrivelse";
#cho "$qtxt<br>";
#xit;
	$x=0;
	$query = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		$x++;
		$vare_id[$x]=$row['id'];
#cho "A $vare_id[$x]<br>";
	}
	$v_id=array();
	$x=0;
	# finder alle konti med bevaegelser i den anfoerte periode eller aabne poster fra foer perioden
	$query = db_select("select  batch_salg.vare_id,batch_salg.pris from batch_salg,varer where fakturadate>='$date_from' and fakturadate<='$date_to' and batch_salg.vare_id = varer.id order by varer.beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		if ((in_array(trim($row['vare_id']), $vare_id))&&(!in_array(trim($row['vare_id']), $v_id)))
		{
			$x++;
			$v_id[$x]=trim($row['vare_id']);
#cho "B $v_id[$x]<br>";
		}
	}
 #cho "select vare_id, pris from batch_kob where fakturadate>='$date_from' and fakturadate<='$date_to' order by vare_id<br>";	
	$query = db_select("select batch_kob.vare_id,batch_kob.pris from batch_kob,varer where batch_kob.fakturadate>='$date_from' and batch_kob.fakturadate<='$date_to' and batch_kob.vare_id = varer.id order by varer.beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		if ((in_array(trim($row['vare_id']), $vare_id))&&(!in_array(trim($row['vare_id']), $v_id))) {
			$x++;
			$v_id[$x]=trim($row['vare_id']);
#cho "C $v_id[$x]<br>";
			}
	}
	$vareantal=$x;
	
#	 print "<tr><td colspan=8><hr></td></tr>";
#	print "<tr><td width=10%> Dato</td><td width=10%> Bilag</td><td width=50%> Tekst</td><td width=10% align=right> Debet</td><td width=10% align=right> Kredit</td><td width=10% align=right> Saldo</td></tr>";


	$totantal=0;
	if (!$detaljer) print "<tr><td>Varenr.</td><tD>Enhed</td><td>Beskrivelse</td><td align=\"right\"> Antal</td><td align=\"right\"%> K&oslash;bspris</td><td align=\"right\"> Salgspris</td><td align=\"right\"> DB</td><td align=right width=12.5%> DG</td></tr>";
	for ($x=1; $x<=$vareantal; $x++) {
		$r = db_fetch_array(db_select("select * from varer where id=$v_id[$x]",__FILE__ . " linje " . __LINE__));
		#		print "<tr><td colspan=8><hr></td></tr>";
		if ($detaljer) {
			print "<tr><td colspan=8><hr></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td colspan=3> $r[varenr]</td></tr>";
			print "<tr><td colspan=3> $r[enhed]</td></tr>";
			print "<tr><td colspan=3> $r[beskrivelse]</td></tr>";
			print "<tr><td><br></td></tr>";
			print "<tr><td width=12.5%> Dato</td><td align=right width=12.5%> Antal</td><td align=right width=12.5%> K&oslash;bsordre</td><td align=right width=12.5%> Salgsordre</td><td align=right width=12.5%> K&oslash;bspris</td><td align=right width=12.5%> Salgspris</td><td align=right width=12.5%> DB</td><td align=right width=12.5%> DG</td></tr>";
			print "<tr><td colspan=8><hr></td></tr>";
		}
		$kontosum=0;
		$z=0;
		$kobsliste=array();
		$query = db_select("select * from batch_salg where vare_id=$v_id[$x] and fakturadate<='$date_to' and fakturadate>='$date_from' order by fakturadate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row['ordre_id']) {
				$q1 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
				$r1 = db_fetch_array($q1); 
			}
			if ($row['batch_kob_id']>0) {
				$z++;
				$q2 = db_select("select * from batch_kob where id=$row[batch_kob_id]",__FILE__ . " linje " . __LINE__);
				$r2 = db_fetch_array($q2); 
					if ($r2['ordre_id']) {
					$kobsliste[$z]="$r2[ordre_id]"; 
					$q3 = db_select("select ordrenr from ordrer where id=$r2[ordre_id]",__FILE__ . " linje " . __LINE__);
					$r3 = db_fetch_array($q3); 
				}
			}
			if ($detaljer) print "<tr><td> ".dkdato($row['fakturadate'])."</td><td align=right> ".dkdecimal($row['antal'])."</td><td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$r2[ordre_id]','k_ordre','width=800,height=400,$jsvars')\"> <u>$r3[ordrenr]</u></td><td align=right onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]','k_ordre','width=800,height=400,$jsvars')\"> <u>$r1[ordrenr]</u></td>";
			$kobspris=$r2['pris']*$row['antal'];	 
			$kobssum+=$kobspris;
			if ($detaljer) print "<td align=right> ".dkdecimal($kobspris)."</td>";
			$salgspris=$row['pris']*$row['antal'];	 
			$salgssum+=$salgspris;
			if ($detaljer) print "<td align=right> ".dkdecimal($salgspris)."</td>";
			$db=$salgspris-$kobspris;
			if ($detaljer) print "<td align=right> ".dkdecimal($db)."</td>";
			if ($salgspris!=0) $dg=$db*100/$salgspris;
			else $dg=0;
			if ($detaljer) print "<td align=right> ".dkdecimal($dg)."</td>";
			$antal+=$row['antal'];
		}

		$query = db_select("select * from batch_kob where vare_id=$v_id[$x] and fakturadate<='$date_to' and fakturadate>='$date_from' order by fakturadate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			if ($row[ordre_id]) {
				$q2 = db_select("select ordrenr, art from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
				$r2 = db_fetch_array($q2); 
			}
#cho "$batch_kontrol<br>";			
			if (($batch_kontrol && strstr($r1['art'],'DK') && !in_array($row['ordre_id'],$kobsliste)) || (!$batch_kontrol)) {
			if ($detaljer) print "<tr><td> ".dkdato($row['fakturadate'])."</td><td align=right> $row[antal]</td><td align=right onClick=\"javascript:k_ordre=window.open('../kreditor/ordre.php?id=$r2[ordre_id]','k_ordre','$jsvars')\"> <u>$r2[ordrenr]</u></td><td align=right onClick=\"javascript:k_ordre=window.open('../debitor/ordre.php?id=$row[ordre_id]','k_ordre','$jsvars')\"> <u>$r1[ordrenr]</u></td>";
			$kobspris=$row['pris']*$row['antal'];	 
			$kobssum=$kobssum+$kobspris;
			if ($detaljer) print "<td align=right> ".dkdecimal($kobspris)."</td>";
			$salgspris=0;	 
			$salgssum=$salgssum+$salgspris;
			if ($detaljer) print "<td align=right> ".dkdecimal($salgspris)."</td>";
			$db=$salgspris-$kobspris;
			if ($detaljer) print "<td align=right> ".dkdecimal($db)."</td>";
			if ($salgspris!=0) {$dg=$db*100/$salgspris;}
			else {$dg=100;}
			$tmp=dkdecimal($dg);
			if ($detaljer) print "<td align=right> ".dkdecimal($dg)."</td>";
			$antal=$antal+$row['antal'];
			}
		}
		print "<tr><td colspan=8><hr></td></tr><tr><td>";
		if (!$detaljer) print	"$r[varenr]: </td><td>$r[enhed]</td><td>$r[beskrivelse]<td align=right> ".dkdecimal($antal)."</td>";
		else print "</td><td align=right> ".dkdecimal($antal)."</td>";
		if ($detaljer) print "<td></td><td></td>";
		print "<td align=right> ".dkdecimal($kobssum)."</td>";
		print "<td align=right> ".dkdecimal($salgssum)."</td>";
		$db=$salgssum-$kobssum;
		print "<td align=right> ".dkdecimal($db)."</td>";
		if ($salgssum!=0) {$dg=$db*100/$salgssum;}
		else {$dg=100;}
		print "<td align=right> ".dkdecimal($dg)."</td></tr>";
		$totalkob=$totalkob+$kobssum;
		$totalsalg=$totalsalg+$salgssum;
		$totantal+=$antal;
	
		$antal=0;
		$kobssum=0;
		$salgssum=0;

	}
	print "<tr><td colspan=8><hr></td></tr>";
	print "<tr><td></td><td></td><td></td><td align=\"right\"><b>";
	if (!$detaljer) print dkdecimal($totantal);
	print "</b></td><td align=right> <b>".dkdecimal($totalkob)."</b></td>";
	print "<td align=right> <b>".dkdecimal($totalsalg)."</b></td>";
	$db=$totalsalg-$totalkob;
	print "<td align=right> <b>".dkdecimal($db)."</b></td>";
	if ($totalsalg!=0) {$dg=$db*100/$totalsalg;}
	else {$dg=100;}
	print "<td align=right> <b>".dkdecimal($dg)."</b></td></tr>";
	print "<tr><td colspan=8><hr></td></tr>";
	print "</tbody></table>";
}
#############################################################################################################

?>
</html>

