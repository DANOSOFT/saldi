<?php
// -----------------finans/hentordrer.php------------lap 2.1.5-------2010.03.28 -------
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
$title="Hent ordrer";
$css="../css/standard.css";

include("../includes/connect.php");
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
			$q = db_select("select id from ordrer where status=3 order by art, fakturanr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$x=0;
				$q2=db_select("select id from kassekladde where kladde_id='$kladde_id'",__FILE__ . " linje " . __LINE__);
				while ($r2=db_fetch_array($q2)) $x++;
				if ($x > 300) Print "<BODY onLoad=\"javascript:alert('Max antal kladdelinjer n&aring;et!')\">";
				else flytordre($kladde_id, $r[id]);
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
	print "<tr><td align=center><table border=1 cellspacing=0 cellpadding=0 width=80%><tbody>";
	
	print "<tr><td>Dato</td><td>Beskrivelse</td><td><br></td><td>Debet</td><td><br></td><td>Kredit</td><td>Fakturanr</td>
		<td align=center>Bel&oslash;b</td><td align=center>Valuta</td><td align=center><a href=hentordrer.php?kladde_id=$kladde_id&flyt=alle>Flyt alle</a></td></tr>";
	$query = db_select("select ordrer.* from ordrer where status=3 order by fakturadate",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$x++;
		$id[$x]=$row['id'];
		$art[$x]=$row['art'];
		$konto_id[$x]=trim($row['konto_id']);
		$kontonr[$x]=trim($row['kontonr']);
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
	}
	$ordreantal=$x;
	
	if (!$ordreantal) print "<meta http-equiv=\"refresh\" content=\"0;URL=kassekladde.php?kladde_id=$kladde_id\">";	

	for ($x=1;$x<=$ordreantal;$x++){
		print "<tr><td></td></tr>";
		$query = db_select("select ordrelinjer.*, kontoplan.beskrivelse as kontonavn from ordrelinjer, kontoplan where kontoplan.kontonr=ordrelinjer.bogf_konto and kontoplan.regnskabsaar='$regnaar' and ordrelinjer.ordre_id=$id[$x];",__FILE__ . " linje " . __LINE__);
		$y=0;
		$bogf_konto = array();
		while ($row = db_fetch_array($query)){
			$bogf_kto=$row['bogf_konto'];
			if($valuta[$x]!='DKK' && $row['posnr']<0) $bogf_kto=$bogf_kto*-1; #Ordrelinjer med negativt posnr skal bogfores i DKK og derfor isoleres
			$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			if (!in_array($bogf_kto, $bogf_konto))	{
				$y++;
				$posnr[$y]=$row['posnr'];
				$bogf_konto[$y]=trim($bogf_kto);
				$pris[$y]=$row['pris']*$row['antal']-round(($row['pris']*$row['antal']*$row['rabat']/100),2);
			} 
			else{
				for ($a=1; $a<=$y; $a++){
					if ($bogf_konto[$a]==$bogf_kto) {
						$pris[$a]=$pris[$a]+($row['pris']*$row['antal']-round(($row['pris']*$row['antal']*$row['rabat']/100),2));
					}
				}		 
			}
		}
		if ($valuta[$x]!='DKK') {
			for($a=1;$a<=$y;$a++) $bogf_konto[$a]=abs($bogf_konto[$a]);
		}
		if (substr($art[$x],0,1)=='K'){
			if ($sum[$x] < 0) {
				$dksum=dkdecimal($sum[$x]*-1);
				print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>K<br></td><td>$kontonr[$x]<br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td>";
			} else {
				$dksum=dkdecimal($sum[$x]);
				print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>K<br></td><td>$kontonr[$x]<br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td>";
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
						print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
					elseif ($pris[$y]>0) {
						 $tmp=dkdecimal($pris[$y]);
						 print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
				}
			}
			$query = db_select("select gruppe from adresser where id='$konto_id[$x]';",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]';",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$box1=substr(trim($row['box1']),1,1);
			if (substr(trim($row['box1']),0,1)=='E') {
				$query = db_select("select grupper.box1, grupper.box2, grupper.box3, kontoplan.beskrivelse as kontonavn from grupper, kontoplan where grupper.art='EM' and grupper.kodenr='$box1' and kontoplan.kontonr = grupper.box1 and kontoplan.regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$box1=trim($row['box3']); # Ser lidt forvirrende ud, men den er go nok - fordi kbsmomsen ligger i box 3 v. udenlandsmoms.
				$moms[$x]=$sum[$x]/100*$row['box2'];			
				$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
				$tmp=dkdecimal($moms[$x]);
				if ($moms[$x]> 0) {
					print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$row[box1]<br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$valuta[$x]<br></td></tr>";
				} elseif ($moms[$x]< 0) {
					print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$row[box3]<br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$tmp<br></td><td align=right>$valuta[$x]<br></td></tr>";
				}
				$row = db_fetch_array(db_select("select beskrivelse as kontonavn from kontoplan where kontonr = $box1 and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__));
				$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			}
			else {
				$query = db_select("select grupper.box1, kontoplan.beskrivelse as kontonavn from grupper, kontoplan  where kontoplan.kontonr=".nr_cast("grupper.box1")." and kontoplan.regnskabsaar='$regnaar' and art='KM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$box1=trim($row[box1])*1;
				$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			}
			if ($moms[$x]<0){
				$dkmoms=dkdecimal($moms[$x]*-1);
				print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$box1</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";
			}
			elseif ($moms[$x]>0) {
				$dkmoms=dkdecimal($moms[$x]);
				print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$box1</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";
			}
		} else{ 
			if ($sum[$x]<0){
			$dksum=dkdecimal($sum[$x]*-1);
				print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>D<br></td><td>$kontonr[$x]<br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td>";
			}
			else {
				$dksum=dkdecimal($sum[$x]);
				print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>D<br></td><td>$kontonr[$x]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dksum<br></td><td align=right>$valuta[$x]<br></td>";
			}
			print "<td align=center><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a><br></td></tr>";
			
			$ordrelinjer=$y;
			for ($y=1;$y<=$ordrelinjer;$y++){
				if ($bogf_konto[$y])	{
					$kontoart[$y]=$art[$x];
					if ($posnr[$y]<0) $tmpvaluta='DKK';
					else $tmpvaluta=$valuta[$x];
					if ($pris[$y]<0)	{
						$pris[$y]=$pris[$y]*-1;
						$pris[$y]=dkdecimal($pris[$y]);
						print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$pris[$y]<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
					else {			
						$pris[$y]=dkdecimal($pris[$y]);
						print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$bogf_konto[$y]</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$pris[$y]<br></td><td align=right>$tmpvaluta<br></td></tr>";
					}
				}
			}
			$query = db_select("select gruppe from adresser where id='$konto_id[$x]';",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$box1=substr(trim($row[box1]),1,1);
			$query = db_select("select grupper.box1, kontoplan.beskrivelse as kontonavn from grupper, kontoplan  where kontoplan.kontonr=".nr_cast("grupper.box1")." and kontoplan.regnskabsaar='$regnaar' and art='SM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			$box1=trim($row['box1']);
			$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			$dkmoms=dkdecimal($moms[$x]);
			if (($art[$x]=='DO')&&($moms[$x]!=0)) {print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td><br></td><td><br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$box1</span><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";}
			elseif ($moms[$x]!=0) {print "<tr><td>$fakturadato[$x]<br></td><td>$firmanavn[$x]<br></td><td>F<br></td><td><span title='$kontonavn[$x]'>$box1</span><br></td><td><br></td><td><br></td><td>$fakturanr[$x]<br></td><td align=right>$dkmoms<br></td><td align=right>$valuta[$x]<br></td></tr>";} 
		}
		$bilag=$bilag+1;
	}
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
	$query = db_select("select * from ordrer where status=3 and id='$ordre_id' order by fakturadate",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		list ($year, $month, $day) = split ('-', $row['fakturadate']);
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
			if ($valuta && $valuta!='DKK') {
				$r=db_fetch_array(db_select("select kodenr from grupper where box1='$valuta' and art='VK'",__FILE__ . " linje " . __LINE__));
				$valuta=$r['kodenr']*1;
			} else $valuta=0;
			$projekt[0]=$row['projekt']*1;
			$moms=$row['moms']*1;
			$sum=$row['sum']+$moms;
			$ordreantal=$x;
			$q=db_select("select id, afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__);
			$r= db_fetch_array($q);
			$afd=$r['afd']*1;
			$ansat=$r['id']*1;
			if ((substr($art,0,1)=='K')&&($modtagelse>0)&&($modtbill==1)) $bilag=$modtagelse;
			elseif ((substr($art,0,1)=='D')&&($no_faktbill==1)) $bilag='0';
			elseif ((substr($art,0,1)=='D')&&($faktbill==1)) $bilag=trim($fakturanr);
			else {
				$tmp1=substr($aarstart,0,4)."-".substr($aarstart,4,2)."-01";
				$tmp2=31;
				while (!checkdate(substr($aarslut,4,2),$tmp2,substr($aarslut,0,4))) {
					$tmp2--;
					if ($tmp2<28) break;
				}
				$tmp2=substr($aarslut,0,4)."-".substr($aarslut,4,2)."-".$tmp2;
				$row = db_fetch_array(db_select("select MAX(bilag) as bilag from kassekladde where transdate>='$tmp1' and transdate<='$tmp2'",__FILE__ . " linje " . __LINE__));
				$bilag=$row['bilag']+1;
			}
			$p=0;	
			$q = db_select("select distinct(projekt) from ordrelinjer where ordre_id=$id and vare_id >	'0'",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$p++;
				$projekt[$p]=$r['projekt']*1;
			}
			$projektantal=$p;
			if (substr($art,0,1)=='K') {
				if (substr($art,1,1)=='K') $beskrivelse ="Lev. Kreditnota - ".$fakturanr;
				else $beskrivelse ="Lev.Faktura - ".$fakturanr;
				$beskrivelse=addslashes($beskrivelse);	
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'K', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				for ($p=1;$p<=$projektantal;$p++) {	
					$query = db_select("select * from ordrelinjer where ordre_id='$id' and projekt = '$projekt[$p]'",__FILE__ . " linje " . __LINE__);
					$y=0;
					$bogf_konto = array();
					while ($row = db_fetch_array($query)) {
						$bogf_kto=$row['bogf_konto'];
						if($valuta!='DKK' && $row['posnr']<0) $bogf_kto=$bogf_kto*-1;
						if (!in_array($bogf_kto, $bogf_konto)) {	
							$y++;
							$posnr[$y]=$row['posnr'];
							$bogf_konto[$y]=trim($bogf_kto);
							$pris[$y]=$row[pris]*$row['antal']-round(($row['pris']*$row['antal']*$row['rabat']/100),2);
						}	else {
							for ($a=1; $a<=$y; $a++) {
								if ($bogf_konto[$a]==$bogf_kto) {
									$pris[$a]=$pris[$a]+($row['pris']*$row['antal']-round(($row['pris']*$row['antal']*$row['rabat']/100),2));
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
				$query = db_select("select gruppe from adresser where id='$konto_id';",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]';",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$box1=substr(trim($row['box1']),1,1);
					
				if (substr(trim($row['box1']),0,1)=='E') {
					$query = db_select("select box1, box2, box3 from grupper where art='EM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$box1=trim($row['box3']); # Ser lidt forvirrende ud, men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
					$moms=$sum/100*$row['box2']; #moms af varekoeb i udland beregnes
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$row[box1]', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				}	else {
					$query = db_select("select box1 from grupper where art='KM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$box1=trim($row['box1']);
				}
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
			}	else {
				if ($art=='RB') $beskrivelse ="Rykker  - ".$ordrenr;
				elseif (substr($art,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
				else $beskrivelse ="Faktura - ".$fakturanr;
				
				if ($sum[$x]<0) {
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into kassekladde (bilag,transdate,beskrivelse,d_type,debet,faktura,amount,momsfri,kladde_id,afd,valuta,projekt,ansat,ordre_id,forfaldsdate) values ('$bilag','$transdate','$beskrivelse','D','$kontonr','$fakturanr','$sum','on','$kladde_id','$afd','$valuta','$projekt[0]','$ansat','$ordre_id','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
				}
				for ($p=1;$p<=$projektantal;$p++) {	
					$query = db_select("select * from ordrelinjer where ordre_id=$id and projekt = '$projekt[$p]';",__FILE__ . " linje " . __LINE__);
					$y=0;
					$bogf_konto = array();
					while ($row = db_fetch_array($query)) {
						$bogf_kto=$row['bogf_konto'];
						if($valuta!='DKK' && $row['posnr']<0) $bogf_kto=$bogf_kto*-1;
						if (!in_array($bogf_kto, $bogf_konto)) {
							$y++;
							$posnr[$y]=$row['posnr'];
							$bogf_konto[$y]=$bogf_kto;
							$pris[$y]=$row['pris']*$row['antal']-round(($row['pris']*$row['antal']*$row['rabat']/100),2);
						}
						else {
							for ($a=1; $a<=$y; $a++) {
								if ($bogf_konto[$a]==$bogf_kto) {
									$pris[$a]=$pris[$a]+($row['pris']*$row['antal']-round(($row['pris']*$row['antal']*$row['rabat']/100),2));
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
				if ($art!='RB') {
					$query = db_select("select gruppe from adresser where id='$konto_id';",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$box1=substr(trim($row[box1]),1,1);
					$query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					$box1=trim($row[box1]);
					if ($moms && $box1) db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt[0]', '$ansat', '$ordre_id')",__FILE__ . " linje " . __LINE__);
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
######################################################################################################################################
