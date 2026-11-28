<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------------------finans/hentordrer.php-------ver 5.1.1----2026-11-19-----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20130210 Break ændret til break 1
// 20170303 PHR fjernet et par semikolons fra db_select som gav injektionsadvarsel.
// 20170321 PHR Blokeret POS ordrer. Det giver bare bøvl. 20170321
// 20260916 SS Declared $permission_key (roles & permissions, phase 3)
// 20261105 CL/LH Flyt til kladde no longer dies on split(), which PHP 7 removed; the invoice date is split with explode().
// 20261113 CDX/NTR Align the file header with the release version and history conventions.
// 20261117 CL/ASR Flyt til kladde writes a sales invoice's lines as bogfor_nu() posts them: a discount in kr (rabatart 'amount') and procent count, each account is rounded to whole øre and an øre left over goes to the OreDif account.
//                 The 5 øre limit is for the lines at 3 decimals, as in bogfor_nu(), and the whole difference the rounding leaves goes to OreDif (10 or more projects of half an øre each left the voucher unbalanced).
//                 Without an OreDif account (kontotype D or S in the year's kontoplan) the invoice is not moved and stays at status 3, with bogfor_nu()'s message.
//                 A sales invoice whose lines would not balance is not moved either and stays at status 3; bogfor.php checks only the journal's total.
//                 A credit note's VAT line and a supplier credit note's supplier, purchase VAT and EU VAT lines are on the opposite side instead of negative.
//                 Flyt alle and flytordre() skip POS orders (art PO), which wait for the register close; the list already did.
//                 With vis_saet (POS/2 box12), where the register close takes sales orders, the list, Flyt alle and flytordre() also skip a sales order paid at a till (felt_1 set, numeric felt_2, felt_4, felt_5), which waits for the register close too.
//                 Flyt alle reads the order id as $r['id']; $r[id] is a fatal "Undefined constant" error in PHP 8.
//                 The list shows the amounts Flyt til kladde writes, and flytordre() casts the request's kladde_id and ordre_id to integers.
// 20261119 CL/ASR Flyt til kladde writes what immediate posting writes for EU services, VAT-free customers and foreign currency, and moves an invoice once.
//                 A supplier in an EU services group (Y, YM) gets the reverse-charge VAT of kreditor/bogfor.php on invoices and credit notes, and the list shows it.
//                 A customer or supplier group without a VAT code no longer queries kodenr '' (an SQL error on PostgreSQL): an invoice without VAT is moved without a VAT line, one with VAT stays at status 3.
//                 A foreign-currency sales invoice's revenue, VAT and rounding lines are in DKK, converted before rounding, with the difference on the currency's difference account, as bogfor_nu() posts them.
//                 flytordre() locks the order row and moves the order only at status 3, so a second move of the same order at the same time shows a message and writes nothing.
//                 The list and flytordre() read the creditor, customer and VAT groups (KG, DG, EM, YM, KM, SM) of the fiscal year the invoice is posted in ($regnaar), as immediate posting and the journal's posting read them, so a group set up for several years no longer takes another year's rate or accounts (YM 1 at 25 % in another year and 20 % in this one wrote 25.00 instead of 20.00 VAT on a 100.00 purchase).

@session_start();
$s_id=session_id();
$title="Hent ordrer";
$css="../css/standard.css";

include("../includes/connect.php");
$permission_key = 'finans.kassekladde';
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/db_query.php");

$kladde_id = $_POST['kladde_id'];
$antal_ny=$_POST['antal_ny'];
#	$h=$antal_ny*10;




if($_GET){
	$kladde_id=$_GET['kladde_id'];
	$ordre_id=$_GET['ordre_id'];
	$flyt=$_GET['flyt'];

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Hent ordrer</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";

	$x=0;
	$q=db_select("select id from kassekladde where kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) $x++;
	if ($x > 300) {
		Print "<BODY onLoad=\"javascript:alert('Max antal kladdelinjer n&aring;et!')\">";
	} else {
		if (($kladde_id)&&($ordre_id)) {
			flytordre($kladde_id, $ordre_id);
		}
		if (($kladde_id)&&($flyt=='alle'))	{
#		flytordre($kladde_id, $ordre_id);
			$q = db_select("select id, art, felt_1, felt_2, felt_4, felt_5 from ordrer where art != 'PO' and status=3 order by art, fakturanr",__FILE__ . " linje " . __LINE__); #20261117
			while ($r = db_fetch_array($q)) {
				if (til_dagsafslutning($r)) continue; #20261117
				$x=0;
				$q2=db_select("select id from kassekladde where kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
				while ($r2=db_fetch_array($q2)) $x++;
				if ($x > 300) Print "<BODY onLoad=\"javascript:alert('Max antal kladdelinjer n&aring;et!')\">";
				else flytordre($kladde_id, $r['id']); #20261117
			}
			print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";
		}
	}
	if ($kladde_id)	{
		hentordrer($kladde_id);
	}
	print "</tbody></table>";
	print "</div>";
}
################################################################################################################
function hentordrer($kladde_id) {
	global $regnaar;
	global $connection;
	global $aarstart;
	global $aarslut;
	global $bgcolor;
	global $bgcolor2;

	$ksnr=array();

	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$year=trim($row['box2']);
			$aarstart=trim($year.$row['box1']);
			$year=trim($row['box4']);
			$aarslut=trim($year.$row['box3']);
		}
	}
	$x=0;
	$ks=0;
	print "<tr><td align=center><table border=1 cellspacing=0 cellpadding=0 width=80%><tbody>";

	print "<tr><td>Dato</td><td>Beskrivelse</td><td><br></td><td>Debet</td><td><br></td><td>Kredit</td><td>Fakturanr</td>
		<td align=center>Bel&oslash;b</td><td align=center>Valuta</td><td align=center><a href=hentordrer.php?kladde_id=$kladde_id&flyt=alle>Flyt alle</a></td></tr>";

	$query = db_select("select * from ordrer where art != 'PO' and status='3' order by fakturadate,fakturanr",__FILE__ . " linje " . __LINE__); #20170321
	while ($row = db_fetch_array($query)){
		if (til_dagsafslutning($row)) continue; #20261117
		$x++;
		$id[$x]=$row['id'];
		$art[$x]=$row['art'];
		$konto_id[$x]=$row['konto_id']*1;
		$kontonr[$x]=trim($row['kontonr']);
		if (!in_array($kontonr[$x],$ksnr)) {
			$ks++;
			$ksnr[$ks]=$kontonr[$x];
		}
		$firmanavn[$x]=trim($row['firmanavn']);
		$fakturadato[$x]=dkdato($row['fakturadate']);
		$fakturanr[$x]=trim($row['fakturanr']);
		$valuta[$x]=trim($row['valuta']);
		if ($valuta[$x]) $valutakurs[$x]=$row['valutakurs']*1;
		else {
			$valutakurs[$x]=100;
			$valuta[$x]='DKK';
		}
		$moms[$x]=$row['moms']*1;
		$sum[$x]=$row['sum']+$moms[$x];
		if (!$konto_id[$x]){
			$firmanavn[$x]="Kassebon";
			$betalt[$x]=$row['betalt'];
			$retur[$x]=$betalt[$x]-$sum[$x];
			$betaling1[$x]=$row['felt_1'];
			$betalt1[$x]=$row['felt_2']*1;
			$betaling2[$x]=$row['felt_3'];
			$betalt2[$x]=$row['felt_4']*1;

			$kasse[$x]=$row['felt_5']*1;
			$kortnavn[$x]=array();
			$kortkto[$x]=array();
			$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='$kasse[$x]'",__FILE__ . " linje " . __LINE__));
			$tmparray=explode(chr(9),$r['box2']);
			$kontonr[$x]=$tmparray[$kasse[$x]-1];
			if (!in_array($kontonr[$x],$ksnr)) {
				$ks++;
				$ksnr[$ks]=$kontonr[$x];
			}
			$kortnavn[$x]=explode(chr(9),$r['box5']);
			$kortkto[$x]=explode(chr(9),$r['box6']);
			$tmparray=explode(chr(9),$r['box7']);
			$momsgruppe[$x]=$tmparray[$kasse[$x]-1];
			$z=0;
			if ($betaling1[$x]=='Kontant') {
				$kortkto1[$x]=$kontonr[$x];
				$betalt1[$x]=$betalt1[$x]-$retur[$x];
				$retur[$x]=0;
			}
			if ($betaling2[$x]=='Kontant') {
				$kortkto2[$x]=$kontonr[$x];
				$betalt2[$x]=$betalt2[$x]-$retur[$x];
				$retur[$x]=0;
			}
			while($kortkto[$x][$z]) {
				if (!in_array($kontonr[$x],$ksnr)) {
					$ks++;
					$ksnr[$ks]=$kortkto[$x][$z];
				}
				if ($betaling1[$x]==$kortnavn[$x][$z]) $kortkto1[$x]=$kortkto[$x][$z];
				if ($betaling2[$x]==$kortnavn[$x][$z]) $kortkto2[$x]=$kortkto[$x][$z];
				$z++;
			}
#		$r=db_fetch_array(db_select("select box1 from grupper where art='SM' and kodenr='$momskode'",__FILE__ . " linje " . __LINE__));
#		$momsgruppe[$x]=trim($r['box1']);
		}
	}
	$ordreantal=$x;

	if (!$ordreantal) print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";

	for ($x=1;$x<=$ordreantal;$x++){
		if ($bg!=$bgcolor) $bg=$bgcolor;
		else $bg=$bgcolor2;

		print "<tr bgcolor=$bg><td></td></tr>";
		$query = db_select("select ordrelinjer.*, kontoplan.beskrivelse as kontonavn from ordrelinjer, kontoplan where kontoplan.kontonr=ordrelinjer.bogf_konto and kontoplan.regnskabsaar='$regnaar' and ordrelinjer.ordre_id=$id[$x]",__FILE__ . " linje " . __LINE__);
		$y=0;
		$bogf_konto = array();
		while ($row = db_fetch_array($query)){
			$bogf_kto=$row['bogf_konto'];
			if (!in_array($kontonr[$x],$ksnr)) {
				$ks++;
				$ksnr[$ks]=$kortkto[$x][$z];
			}
			if($valuta[$x]!='DKK' && $row['posnr']<0) $bogf_kto=$bogf_kto*-1; #Ordrelinjer med negativt posnr skal bogfores i DKK og derfor isoleres
			$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			# 20261117 the amount Flyt til kladde writes: on a sales order a discount in kr per unit (rabatart 'amount')
			# and the line's procent count, as in bogfor_nu(); a supplier order keeps the percent discount of kreditor/bogfor.php
			$salg=(substr($art[$x],0,1)!='K');
			if ($salg && $row['rabatart']=='amount') $linjesum=$row['pris']*$row['antal']-($row['rabat']*$row['antal']);
			else $linjesum=$row['pris']*$row['antal']-($row['pris']*$row['antal']*$row['rabat']/100);
			if ($salg && ($row['procent'] || $row['procent']!='')) $linjesum=$linjesum*$row['procent']/100;
			if (!in_array($bogf_kto, $bogf_konto))	{
				$y++;
				$posnr[$y]=$row['posnr'];
				$bogf_konto[$y]=trim($bogf_kto);
				$pris[$y]=afrund($linjesum,3);
			}	else {
				for ($a=1; $a<=$y; $a++){
					if ($bogf_konto[$a]==$bogf_kto) {
						$pris[$a]=$pris[$a]+afrund($linjesum,3);
					}
				}
			}
		}
		if ($valuta[$x]!='DKK') {
			for($a=1;$a<=$y;$a++) $bogf_konto[$a]=abs($bogf_konto[$a]);
		}
		$ksantal=$ks;
#################### Kreditorordrer ###################
		if (substr($art[$x],0,1)=='K'){
			for ($ks=1;$ks<=$ksantal;$ks++) {
				if ($ksnr[$ks]==$kontonr[$x]) $ksum[$ks]=$ksum[$ks]-$sum[$x];
			}
			if ($sum[$x] < 0) {
				$dksum=dkdecimal($sum[$x]*-1);
				print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>K<br></td><td>$kontonr[$x]<br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td>";
			} else {
				$dksum=dkdecimal($sum[$x]);
				print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>K<br></td><td>$kontonr[$x]<br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td>";
			}
			print "<td align=center><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a><br></td></tr>";
			$ordrelinjer=$y;
			for ($y=1;$y<=$ordrelinjer;$y++)	{
				if ($bogf_konto[$y])	{
					if ($posnr[$y]<0) $tmpvaluta='DKK';
					else $tmpvaluta=$valuta[$x];
					$kontoart[$y]=$art[$x];

					if ($pris[$y]<0){
						$tmp=dkdecimal($pris[$y]*-1);
						print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
					elseif ($pris[$y]>0) {
						 $tmp=dkdecimal($pris[$y]);
						 print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
				}
			}
			$query = db_select("select gruppe from adresser where id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			# 20261119 the groups of the fiscal year Flyt til kladde reads them for (flytordre())
			$query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			# 20261119 E (EU goods, EM) and Y (EU services, YM) show the reverse-charge VAT Flyt til kladde writes, a credit note's
			# VAT owed debited on the same account; the cast VAT code queries no kodenr '' for a group without one.
			$momsart=substr(trim((string)($row['box1'] ?? '')),0,1);
			$box1=(int)substr(trim((string)($row['box1'] ?? '')),1,1);
			if ($momsart=='E' || $momsart=='Y') {
				$query = db_select("select grupper.box1, grupper.box2, grupper.box3, kontoplan.beskrivelse as kontonavn from grupper, kontoplan where grupper.art='".$momsart."M' and grupper.kodenr='$box1' and grupper.fiscal_year='$regnaar' and kontoplan.kontonr=".nr_cast("grupper.box1")." and kontoplan.regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$box1=(int)($row['box3'] ?? 0); # Ser lidt forvirrende ud, men den er go nok - fordi kbsmomsen ligger i box 3 v. udenlandsmoms.
				$moms[$x]=$sum[$x]/100*(float)($row['box2'] ?? 0);
				$kontonavn[$x]=str_replace(" ","&nbsp;",(string)($row['kontonavn'] ?? ''));
				$tmp=dkdecimal(abs($moms[$x]));
				if ($moms[$x]> 0) {
					print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$row[box1]<br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$valuta[$x]<br></td></tr>";
				} elseif ($moms[$x]< 0) {
					print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$row[box1]<br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$valuta[$x]<br></td></tr>";
				}
				$row = db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr = $box1 and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
				$kontonavn[$x]=str_replace(" ","&nbsp;",(string)($row['kontonavn'] ?? ''));
			} else {
				$query = db_select("select grupper.box1, kontoplan.beskrivelse as kontonavn from grupper, kontoplan  where kontoplan.kontonr=".nr_cast("grupper.box1")." and kontoplan.regnskabsaar='$regnaar' and art='KM' and kodenr='$box1' and grupper.fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$box1=(int)($row['box1'] ?? 0);
				$kontonavn[$x]=str_replace(" ","&nbsp;",(string)($row['kontonavn'] ?? ''));
			}
			if ($moms[$x]<0){
				$dkmoms=dkdecimal($moms[$x]*-1);
				print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$box1</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";
			} elseif ($moms[$x]>0) {
				$dkmoms=dkdecimal($moms[$x]);
				print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$box1</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";
			}
		} else { #################### Debitor / POS ordrer ####################
			for ($ks=1;$ks<=$ksantal;$ks++) {
				if ($ksnr[$ks]==$kontonr[$x]) $ksum[$ks]=$ksum[$ks]+$sum[$x];
				if ($ksnr[$ks]==$kortkto1[$x]) $ksum[$ks]=$ksum[$ks]+$sum[$x];
				if ($ksnr[$ks]==$kortkto2[$x]) $ksum[$ks]=$ksum[$ks]+$sum[$x];
			}
			if ($sum[$x]<0) {
				$dksum=dkdecimal($sum[$x]*-1);
				if ($art[$x]!='PO') {
					$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$kontonr[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
					print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>D<br></td><td title='$r[kontonavn]'>$kontonr[$x]<br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td></tr>";
				} else {
					$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$kontonr[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
					print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td title='$r[kontonavn]'>$kontonr[$x]<br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td></tr>";
				}
			} elseif($sum[$x]>0)  {
				$dksum=dkdecimal($sum[$x]);
				if ($art[$x]!='PO') {
					$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$kontonr[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
					print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>D<br></td><td title='$r[kontonavn]'>$kontonr[$x]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td></tr>";
				} else {
					if ($betaling1[$x]) {
						$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$kortkto1[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
						print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td title='$r[kontonavn]'>$kortkto1[$x]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$betalt1[$x]<br></td><td align=right>$valuta[$x]<br></td></tr>";
					}
					if ($betaling2[$x])  {
						$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$kortkto2[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
						print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td title='$r[kontonavn]'>$kortkto2[$x]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$betalt2[$x]<br></td><td align=right>$valuta[$x]<br></td></tr>";
					}
					if ($retur[$x])  {
						$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$kontonr[$x]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
						print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td title='$r[kontonavn]'>$kontonr[$x]<br></td><td>$fakturanr[$x]<br></td><td align=right>$retur[$x]<br></td><td align=right>$valuta[$x]<br></td></tr>";
					}
				}
			}

			$ordrelinjer=$y;
			for ($y=1;$y<=$ordrelinjer;$y++){
				if ($bogf_konto[$y])	{
					$kontoart[$y]=$art[$x];
					if ($posnr[$y]<0) $tmpvaluta='DKK';
					else $tmpvaluta=$valuta[$x];
					if ($pris[$y]<0) {
						$pris[$y]=$pris[$y]*-1;
						$pris[$y]=dkdecimal($pris[$y]);
						print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$pris[$y]<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
					else {
						$r=db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr= '$bogf_konto[$y]' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
						$pris[$y]=dkdecimal($pris[$y]);
						print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$r[kontonavn]'>$bogf_konto[$y]</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$pris[$y]<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
				}
			}
			if ($art[$x]!='PO') {
				$r=db_fetch_array(db_select("select gruppe from adresser where id='$konto_id[$x]'",__FILE__ . " linje " . __LINE__));
				$r = db_fetch_array(db_select("select box1 from grupper where art='DG' and kodenr='$r[gruppe]' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__));
				$momsgruppe[$x]=substr(trim((string)($r['box1'] ?? '')),1); # 20261119 no row when the group has none for the year
			}
			# 20261119 the VAT group is cast: a customer group without a VAT code queries no kodenr '', which PostgreSQL refuses
			$r=db_fetch_array(db_select("select grupper.box1, kontoplan.beskrivelse as kontonavn from grupper, kontoplan  where kontoplan.kontonr=".nr_cast("grupper.box1")." and kontoplan.regnskabsaar='$regnaar' and grupper.art='SM' and grupper.kodenr='".(int)$momsgruppe[$x]."' and grupper.fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__));
			$bfkto=trim((string)($r['box1'] ?? ''));
			$kontonavn[$x]=str_replace(" ","&nbsp;",(string)($r['kontonavn'] ?? ''));
			$dkmoms=dkdecimal($moms[$x]);
			if (($art[$x]=='DO')&&($moms[$x]!=0)) {print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bfkto</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";}
			elseif (($art[$x]=='PO')&&($moms[$x]!=0)) {print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bfkto</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";}
			elseif ($moms[$x]!=0) {print "<tr bgcolor=$bg><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bfkto</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";}
		}
		print "<tr bgcolor=$bg><td colspan=\"9\"><br></td><td align=center><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a><br></td></tr>";

		$bilag=$bilag+1;
	}
#			for ($ks=1;$ks<=$ksantal;$ks++) {
#				if ($ksum[$ks]) print "<tr><td>$ksnr[$ks]</td><td>$ksum[$ks]</td></tr>";
#			}
	print "</tbody></table></td></tr>";
exit;
}
######################################################################################################################################
function flytordre($kladde_id, $ordre_id) {
	global $regnaar;
	global $connection;
	global $aarstart;
	global $aarslut;

	include("../includes/forfaldsdag.php");

	$kladde_id = (int)$kladde_id; # 20261117 both come from the request
	$ordre_id = (int)$ordre_id;
	transaktion("begin");
	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$year=trim($row['box2']);
			$aarstart=trim($year.$row['box1']);
			$year=trim($row['box4']);
			$aarslut=trim($year.$row['box3']);
		}
	}
	$query = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		if (trim($row['box3'])=="on") $faktbill=1;
		else {$faktbill=0;}
		if (trim($row['box4'])=="on") $modtbill=1;
		else $modtbill=0;
		if (trim($row['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}
		else $no_faktbill=0;
	}
	$x=0;
	# 20261119 The move claims the order: its row stays locked until the move commits or rolls back. A second Flyt til
	# kladde of the same order (another user, another journal) waits here, then reads the committed status and writes
	# nothing once the first has moved it. The status is tested on the locked row itself: on MySQL/MariaDB a plain
	# select would still see the order as it was before the wait.
	$query = db_select("select * from ordrer where id='$ordre_id' for update",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	if ($row && $row['art'] != 'PO' && $row['status'] >= 4) {
		$tmp = $row['ordrenr'];
		print "<BODY onLoad=\"javascript:alert('Ordre $tmp er allerede flyttet til en kladde eller bogf&oslash;rt og flyttes ikke igen')\">";
	}
	if ($row && $row['art'] != 'PO' && $row['status'] == 3 && !til_dagsafslutning($row)) { #20261117
		list ($year, $month, $day) = explode('-', $row['fakturadate']);
		$ym=$year.$month;
		if (($ym>=$aarstart)&&($ym<=$aarslut)) {
			$id=$row['id'];
			$art=$row['art'];
			$konto_id=$row['konto_id'];
			$kontonr=str_replace(" ","",$row['kontonr']);
			$firmanavn=trim($row['firmanavn']);
			$modtagelse=$row['modtagelse'];
			$transdate=($row['fakturadate']);
			$forfaldsdate=usdate(forfaldsdag($transdate,$row['betalingsbet'],$row['betalingsdage']));
			$fakturanr=addslashes($row['fakturanr']);
			$ordrenr=$row['ordrenr'];
			$valuta=$row['valuta'];
			$valutakode=$valuta;
			if ($valuta && $valuta!='DKK') {
				$r=db_fetch_array(db_select("select kodenr from grupper where box1='$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
				$valuta=$r['kodenr']*1;
			} else $valuta=0;
			$projekt[0]=$row['projekt'];
			$moms=$row['moms']*1;
			$sum=$row['sum']+$moms;
			$ordreantal=$x;
			$q=db_select("select id, afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__);
			$r= db_fetch_array($q);
			$afd=$r['afd']*1;
			$ansat=$r['id']*1;

			if (!$konto_id){
				$firmanavn="Kontantsalg";
				$betalt=$row['betalt'];
				$retur=$betalt-$sum;
				$betaling1=$row['felt_1'];
				$betalt1=$row['felt_2']*1;
				$betaling2=$row['felt_3'];
				$betalt2=$row['felt_4']*1;
				$kasse=$row['felt_5']*1;
				$kortnavn=array();
				$kortkto=array();
				$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr='$kasse'",__FILE__ . " linje " . __LINE__));
				$tmparray=explode(chr(9),$r['box2']);
				$kontonr=$tmparray[$kasse-1];
				$kortnavn=explode(chr(9),$r['box5']);
				$kortkto=explode(chr(9),$r['box6']);
				$tmparray=explode(chr(9),$r['box7']);
				$momsgruppe=$tmparray[$kasse-1];
				$z=0;
				if ($betaling1=='Kontant') {
					$kortkto1=$kontonr;
					$betalt1=$betalt1-$retur;
					$retur=0;
				}
				if ($betaling2=='Kontant') {
					$kortkto2=$kontonr;
					$betalt2=$betalt2-$retur;
					$retur=0;
				}
				while($kortkto[$z]) {
					if ($betaling1==$kortnavn[$z]) $kortkto1=$kortkto[$z];
					if ($betaling2==$kortnavn[$z]) $kortkto2=$kortkto[$z];
					$z++;
				}
				$r=db_fetch_array(db_select("select box1 from grupper where art='SM' and kodenr='$momskode'",__FILE__ . " linje " . __LINE__));
				$box1=trim($r['box1']);
			}
			if (substr($art,0,1)=='K' && $modtagelse>0 && $modtbill==1) $bilag=$modtagelse;
			elseif ((substr($art,0,1)=='D' || $art=='PO') && $no_faktbill==1) $bilag='0';
			elseif ((substr($art,0,1)=='D' || $art=='PO') && $faktbill==1) $bilag=trim($fakturanr);
			else {
				$tmp1=substr($aarstart,0,4)."-".substr($aarstart,4,2)."-01";
				$tmp2=31;
				while (!checkdate(substr($aarslut,4,2),$tmp2,substr($aarslut,0,4))) {
					$tmp2--;
					if ($tmp2<28) break 1; #20130210
				}
				$tmp2=substr($aarslut,0,4)."-".substr($aarslut,4,2)."-".$tmp2;
				$row = db_fetch_array(db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$tmp1' and transdate<='$tmp2'",__FILE__ . " linje " . __LINE__));
				$bilag=$row['bilag']+1;
			}
			$p=0;
			$q = db_select("select distinct(projekt) from ordrelinjer where ordre_id=$id and vare_id >	'0'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$p++;
				$projekt[$p]=$r['projekt'];
			}
			$projektantal=$p;
			if (substr($art,0,1)=='K') {
				if (substr($art,1,1)=='K') $beskrivelse ="Lev. Kreditnota - ".$fakturanr;
				else $beskrivelse ="Lev.Faktura - ".$fakturanr;
				$beskrivelse=addslashes($beskrivelse);
				if ($sum<0) { # 20261117 a credit note debits the supplier, as kreditor/bogfor.php posts it
					$tmp=$sum*-1;
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'K', '$kontonr', '$fakturanr', '$tmp', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'K', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				}
				for ($p=1;$p<=$projektantal;$p++) {
					$qtxt="select * from ordrelinjer where ordre_id='$id' and projekt = '$projekt[$p]'";
					$query = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					$y=0;
					$bogf_konto = array();
					while ($row = db_fetch_array($query)) {
						$bogf_kto=$row['bogf_konto'];
						if($valuta!='DKK' && $row['posnr']<0) $bogf_kto=$bogf_kto*-1;
						if (!in_array($bogf_kto, $bogf_konto)) {
							$y++;
							$posnr[$y]=$row['posnr'];
							$bogf_konto[$y]=trim($bogf_kto);
							$pris[$y]=afrund($row['pris']*$row['antal']-($row['pris']*$row['antal']*$row['rabat']/100),3);
						}	else {
							for ($a=1; $a<=$y; $a++) {
								if ($bogf_konto[$a]==$bogf_kto) {
									$pris[$a]=$pris[$a]+afrund($row['pris']*$row['antal']-($row['pris']*$row['antal']*$row['rabat']/100),3);
								}
							}
						}
					}
					if ($valuta) {
						for($a=1;$a<=$y;$a++) $bogf_konto[$a]=abs($bogf_konto[$a]);
					}
					$ordrelinjer=$y;
					for ($y=1;$y<=$ordrelinjer;$y++) {
						if ($bogf_konto[$y]) {
							if ($posnr[$y]<0) $tmpvaluta=0;
							else $tmpvaluta=$valuta;
							$kontoart[$y]=$art;
							if ($pris[$y]<0) {
								$pris[$y]=$pris[$y]*-1;
								db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt[$p]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
							}
							elseif ($pris[$y]>0) {
								db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt[$p]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
							}
						}
					}
				}
				$query = db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				# 20261119 The groups are a fiscal year's settings (a row per year). They are read for the year the invoice is
				# posted in, $regnaar (the move refuses an invoice dated outside it), as kreditor/bogfor.php, bogfor_nu() and the
				# journal's posting read them: another year's row may hold another rate or other accounts.
				$query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$kreditorgruppe=trim((string)($row['box1'] ?? ''));
				# 20261119 The group's VAT code: E is EU goods (EM), Y is EU services (YM), any other letter Danish VAT (KM). For
				# E and Y the VAT is reverse charged as kreditor/bogfor.php posts it: VAT owed on the group's box1 account,
				# purchase VAT on its box3 account. The number is cast, so a group without a VAT code queries no kodenr ''.
				$momsart=substr($kreditorgruppe,0,1);
				$box1=(int)substr($kreditorgruppe,1,1);
				$eukto='';
				if ($momsart=='E' || $momsart=='Y') {
					$query = db_select("select box1, box2, box3 from grupper where art='".$momsart."M' and kodenr='$box1' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$box1=trim((string)($row['box3'] ?? '')); # Ser lidt forvirrende ud, men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
					$eukto=trim((string)($row['box1'] ?? ''));
					$moms=$sum/100*(float)($row['box2'] ?? 0); #moms af varekoeb i udland beregnes
				}	else {
					$query = db_select("select box1 from grupper where art='KM' and kodenr='$box1' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$box1=trim((string)($row['box1'] ?? ''));
				}
				# 20261119 VAT without an account to post it on is not moved: the invoice stays at status 3.
				if ($moms && (!$box1 || (($momsart=='E' || $momsart=='Y') && !$eukto))) {
					transaktion("rollback");
					print "<BODY onLoad=\"javascript:alert('Kreditorgruppen er ikke tilknyttet en momsgruppe med momskonti - fakturaen er ikke flyttet til kladden')\">";
					return;
				}
				if ($eukto) {
					if ($moms<0) { # 20261117 credit note: opposite side, as kreditor/bogfor.php posts it
						$tmp=$moms*-1;
						db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$eukto', '$fakturanr', '$tmp', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
					} else {
						db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$eukto', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
					}
				}
				# 20261119 a group without a VAT code gets no VAT line, as kreditor/bogfor.php posts no VAT for it
				if ($box1 && $moms<0) { # 20261117 credit note: the purchase VAT is credited, as kreditor/bogfor.php posts it
					$tmp=$moms*-1;
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$tmp', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				} elseif ($box1) {
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				}
			}	else { #################### Debitor / POS ordrer ####################
				if ($art=='RB') $beskrivelse ="Rykker  - ".$ordrenr;
				elseif (substr($art,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
				elseif ($art=='PO') $beskrivelse ="Kassebon - ".$fakturanr;
				else $beskrivelse ="Faktura - ".$fakturanr;

				# 20261119 An invoice in a foreign currency: the debtor line stays in its currency (the open item), and finans/bogfor.php
				# converts it at the rate table's rate of its date, which invoicing stored as the order's rate. The revenue, VAT and
				# rounding lines are written in DKK at that rate, converted before they are rounded, as bogfor_nu() posts them.
				$kurs=100;
				if ($valuta) {
					$r=db_fetch_array(db_select("select kurs from valuta where gruppe='$valuta' and valdate <= '$transdate' order by valdate desc",__FILE__ . " linje " . __LINE__));
					$kurs=($r)?(float)$r['kurs']:0;
					if (!$kurs) {
						transaktion("rollback");
						$tmp=dkdato($transdate);
						print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valutakode den $tmp (fakturadatoen) - fakturaen er ikke flyttet til kladden')\">";
						return;
					}
				}
				if ($sum<0) {
					if ($art=='PO') $dk='F';
					else $dk='D';
					$tmp=$sum*-1; #indsat 50.02.11 grunder vejl v. hentning af kreditnota
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', '$dk', '$kontonr', '$fakturanr', '$tmp', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				} else {
					if ($art=='PO')	{
						if ($betaling1 && $betalt1) db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,faktura,amount,momsfri,kladde_id,afd,valuta,projekt,ansat,ordre_id,forfaldsdate) values ('$bilag','$transdate','$beskrivelse','F','$kortkto1','$fakturanr','$betalt1','on','$kladde_id','$afd','$valuta','$projekt[0]','$ansat','$ordre_id','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
						if ($betaling2 && $betalt2) db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,faktura,amount,momsfri,kladde_id,afd,valuta,projekt,ansat,ordre_id,forfaldsdate) values ('$bilag','$transdate','$beskrivelse','F','$kortkto2','$fakturanr','$betalt2','on','$kladde_id','$afd','$valuta','$projekt[0]','$ansat','$ordre_id','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
						if ($retur) db_modify("insert into kassekladde (bilag,transdate,beskrivelse,k_type,kredit,faktura,amount,momsfri,kladde_id,afd,valuta,projekt,ansat,ordre_id,forfaldsdate) values ('$bilag','$transdate','$beskrivelse','F','$kontonr','$fakturanr','$retur','on','$kladde_id','$afd','$valuta','$projekt[0]','$ansat','$ordre_id','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
					} else {
						db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,faktura,amount,momsfri,kladde_id,afd,valuta,projekt,ansat,ordre_id,forfaldsdate) values ('$bilag','$transdate','$beskrivelse','D','$kontonr','$fakturanr','$sum','on','$kladde_id','$afd','$valuta','$projekt[0]','$ansat','$ordre_id','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
					}
				}
				$linjesum_i_alt=$linjesum_uafrundet=0; # 20261117
				for ($p=1;$p<=$projektantal;$p++) {
					$query = db_select("select * from ordrelinjer where ordre_id=$id and projekt = '$projekt[$p]'",__FILE__ . " linje " . __LINE__);
					$y=0;
					$bogf_konto = array();
					while ($row = db_fetch_array($query)) {
						$bogf_kto=$row['bogf_konto'];
						if($valuta!='DKK' && $row['posnr']<0) $bogf_kto=$bogf_kto*-1;
						# 20261117 the line's net amount as bogfor_nu() in includes/ordrefunc.php posts it:
						# a discount in kr per unit (rabatart 'amount') or in percent, then the line's procent
						if ($row['rabatart']=='amount') $linjesum=$row['pris']*$row['antal']-($row['rabat']*$row['antal']);
						else $linjesum=$row['pris']*$row['antal']-($row['pris']*$row['antal']*$row['rabat']/100);
						if ($row['procent'] || $row['procent']!='') $linjesum=$linjesum*$row['procent']/100;
						if (!in_array($bogf_kto, $bogf_konto)) {
							$y++;
							$posnr[$y]=$row['posnr'];
							$bogf_konto[$y]=$bogf_kto;
							$pris[$y]=afrund($linjesum,3);
						}
						else {
							for ($a=1; $a<=$y; $a++) {
								if ($bogf_konto[$a]==$bogf_kto) {
									$pris[$a]=$pris[$a]+afrund($linjesum,3);
								}
							}
						}
					}
					if ($valuta) {
						for($a=1;$a<=$y;$a++) $bogf_konto[$a]=abs($bogf_konto[$a]);
					}
					$ordrelinjer=$y;
					for ($y=1;$y<=$ordrelinjer;$y++) {
						if ($bogf_konto[$y]) {
							$tmpvaluta=0; # 20261119 DKK: lines with negative posnr are in DKK, the others are converted below
							if ($posnr[$y]>=0) $linjesum_uafrundet+=$pris[$y]; # 20261117 3 decimals, as bogfor_nu()'s control sums
							if ($valuta && $posnr[$y]>=0) $pris[$y]=flyt_dkk($pris[$y],$kurs); # 20261119 converted, then whole øre
							else $pris[$y]=afrund($pris[$y],2); # 20261117 whole øre per account, as bogfor_nu() posts it
							if ($posnr[$y]>=0) $linjesum_i_alt+=$pris[$y];
							if ($pris[$y]<0){
								$pris[$y]=$pris[$y]*-1;
								db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt[$p]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
							}
							elseif ($pris[$y]>0) {
								db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt[$p]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
							}
						}
					}
				}
				$momsdkk=($valuta)?flyt_dkk($moms,$kurs):$moms; # 20261119 the VAT line's amount in DKK
				if ($art!='RB') {
					if ($art!='PO') {
						$r=db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
						$debitorgruppe=(int)($r['gruppe'] ?? 0);
						# 20261119 the customer group and its VAT group of the year the invoice is posted in, as bogfor_nu() reads them
						$r=db_fetch_array(db_select("select box1 from grupper where art='DG' and kodenr='$debitorgruppe' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__));
						$momsgruppe=substr(trim((string)($r['box1'] ?? '')),1,1);
					}
					# 20261119 A customer group without a VAT code (allowed for VAT-free customers) has no VAT group: kodenr='' is
					# refused by PostgreSQL, so the group is cast and an invoice without VAT is moved without a VAT line, as
					# bogfor_nu() posts it. An invoice with VAT is not moved, as bogfor_nu() refuses it; it stays at status 3.
					$r=db_fetch_array(db_select("select box1 from grupper where art='SM' and kodenr='".(int)$momsgruppe."' and fiscal_year='$regnaar'",__FILE__ . " linje " . __LINE__));
					$box1=trim((string)($r['box1'] ?? ''));
					if ($momsdkk && !$box1) {
						transaktion("rollback");
						print "<BODY onLoad=\"javascript:alert('Debitorgruppe $debitorgruppe er ikke tilknyttet en momsgruppe med momskonto - fakturaen er ikke flyttet til kladden')\">";
						return;
					}
					if ($momsdkk<0 && $box1) { # 20261117 a credit note debits the VAT, as bogfor_nu() posts it
						$tmp=$momsdkk*-1;
						db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$tmp', 'on', '$kladde_id', '$afd', '0', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
					} elseif ($momsdkk && $box1) db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$momsdkk', 'on', '$kladde_id', '$afd', '0', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				}
				# 20261117 Rounding each account to whole øre can leave the lines øre from the invoice total when the invoice
				# has several accounts or projects, up to half an øre for each; bogfor_nu() posts that difference on the øre
				# account (OreDif), and so does the journal. As in bogfor_nu(), the 5 øre limit is for the lines at 3 decimals
				# against the invoice (its first øre block), and the whole difference the rounding leaves is written, with no
				# limit (its last block); it is whole øre when the invoice total is.
				# Without an øre account bogfor_nu() refuses the invoice; so does the move, and also when the account is not an
				# operating or status account (kontotype D or S, the accounts bogfor_nu() posts to) in the year's chart of
				# accounts: nothing is written and the order stays at status 3, so it can be fetched once the account is set.
				# 20261119 In a foreign currency the 5 øre limit is in the invoice's currency, and the difference is in DKK: the
				# debtor line as finans/bogfor.php converts it against the DKK lines written above. bogfor_nu() posts that
				# difference on the currency's difference account (grupper VK box3), and so does the journal.
				$sumdkk=($valuta)?afrund($sum*$kurs/100,2):$sum;
				if (abs(afrund($sum-$moms-$linjesum_uafrundet,2))<0.05) $diff=afrund($sumdkk-$momsdkk-$linjesum_i_alt,3);
				else $diff=0;
				if ($diff) {
					if ($valuta) {
						$r=db_fetch_array(db_select("select box3 from grupper where art='VK' and kodenr='$valuta'",__FILE__ . " linje " . __LINE__));
						$difkto=(int)($r['box3'] ?? 0);
						$tmp="Manglende konto til valutadifferencer for $valutakode - Se indstillinger -> valuta";
					} else {
						$r=db_fetch_array(db_select("select box2 from grupper where art='OreDif'",__FILE__ . " linje " . __LINE__));
						$difkto=(int)$r['box2'];
						$tmp="Manglende kontonummer til &oslash;redifferencer - Se indstillinger -> diverse -> &oslash;rediff";
					}
					if ($difkto && !db_fetch_array(db_select("select id from kontoplan where (kontotype = 'D' or kontotype = 'S') and kontonr = '$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) $difkto=0;
					if (!$difkto) {
						transaktion("rollback");
						print "<BODY onLoad=\"javascript:alert('$tmp')\">";
						return;
					}
					if ($diff>0) {
						db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$difkto', '$fakturanr', '$diff', 'on', '$kladde_id', '$afd', '0', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
					} else {
						$tmp=$diff*-1;
						db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$difkto', '$fakturanr', '$tmp', 'on', '$kladde_id', '$afd', '0', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
					}
				}
				# 20261117 The invoice's voucher must balance. finans/bogfor.php checks the journal's total only, so two vouchers
				# that are off by the same amount the opposite way (an invoice and its credit note) would be posted as they are.
				# When the lines written above do not balance in their currency (lines with negative posnr are in DKK), for
				# instance lines that differ from the invoice total by 5 øre or more (bogfor_nu() refuses those too), nothing
				# is written and the order stays at status 3.
				# 20261119 The check is in DKK: the debtor line in the invoice's currency counts as finans/bogfor.php converts it.
				$qtxt="select valuta, sum(case when d_type != '' then amount else 0 end) as debet, sum(case when k_type != '' then amount else 0 end) as kredit ";
				$qtxt.="from kassekladde where kladde_id='$kladde_id' and ordre_id='$ordre_id' group by valuta";
				$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				$debet=$kredit=0;
				while ($r=db_fetch_array($q)) {
					if ((int)$r['valuta']) {
						$debet+=afrund($r['debet']*$kurs/100,2);
						$kredit+=afrund($r['kredit']*$kurs/100,2);
					} else {
						$debet+=$r['debet'];
						$kredit+=$r['kredit'];
					}
				}
				if (afrund($debet-$kredit,3)) {
					transaktion("rollback");
					$tmp="d=".dkdecimal($debet,3).", k=".dkdecimal($kredit,3);
					print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, ID $id ordre $ordrenr, $tmp - fakturaen er ikke flyttet til kladden')\">";
					return;
				}
			}
			db_modify("update ordrer set status=4 where id=$id",__FILE__ . " linje " . __LINE__);
			db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0",__FILE__ . " linje " . __LINE__);
		}
		else {Print "<BODY onLoad=\"javascript:alert('Ordredato uden for regnskabs&aring;r!')\">";}
	}
	transaktion("commit");
#exit;
}
# 20261117 An order paid at a till (debitor/ordre.php's kasse fields: felt_1 payment type, felt_2 and felt_4 amounts,
# felt_5 the till) waits at status 3 for the register close (debitor/pos_ordre.php), which posts it as a POS sale.
# The register close takes sales orders (art D*) only with vis_saet (grupper POS/2 box12, which also shows the kasse
# fields on the order); without it felt_1..felt_5 are free order fields and the order is moved as before.
# bogfor_nu() in includes/ordrefunc.php treats an order as POS by the same field test (#20171004). Hent ordrer leaves it.
function til_dagsafslutning($r) {
	global $regnaar;
	static $vis_saet=NULL;

	if ($vis_saet===NULL) {
		$qtxt="select box12 from grupper where art='POS' and kodenr='2' and fiscal_year='$regnaar'";
		($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$vis_saet=trim($r2['box12']):$vis_saet='';
	}
	if (!$vis_saet || substr($r['art'],0,1)!='D') return false;
	return ($r['felt_1'] && is_numeric($r['felt_2']) && is_numeric($r['felt_4']) && is_numeric($r['felt_5']) && (int)$r['felt_5'] > 0);
}
# 20261119 An amount in an invoice's foreign currency as bogfor_nu() in includes/ordrefunc.php posts it in DKK: converted at
# the rate (per 100 units) to 3 decimals, then rounded to whole øre. Rounding before the conversion moved up to half an
# øre of the currency, several øre in DKK, onto the revenue (2.01 EUR less 50 % at 746: 7.53 instead of 7.50 and 0.03).
function flyt_dkk($belob, $kurs) {
	return afrund(afrund($belob*$kurs/100,3),2);
}
function pos_moms($momskode) {
	$query = db_select("select box1 from grupper where art='SM' and kodenr='$momskode'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$box1=trim($row['box1']);
	if ($moms > 0) {$kredit=$moms; $debet='0';}
	else {$kredit='0'; $debet=$moms*-1;}
	if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.
	$kredit=afrund($kredit,3);$debet=afrund($debet,3);
	$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
	$diff=afrund($d_kontrol-$k_kontrol,3);
	$absdiff=abs($diff);
	if ($moms && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
		if ($debet > 0) {
			$debet=$debet+$diff;
			$d_kontrol=$d_kontrol+$diff;
		} elseif ($kredit > 0) {
			$kredit=$kredit+$diff;
			$k_kontrol=$k_kontrol+$diff;
		}
	}
	$moms=afrund($moms,2);
	return($moms);
}
######################################################################################################################################
