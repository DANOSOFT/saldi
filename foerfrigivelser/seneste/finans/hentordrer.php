<?php
// -----------------finans/hentordrer.php------------lap 1.9.2a-------07.04.2008----------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Hent ordrer";

if ((!$sqhost)||(!$dbuser)||(!$db)){
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/dkdato.php");
	include("../includes/dkdecimal.php");
	include("../includes/db_query.php");
}
$kladde_id = $_POST['kladde_id'];
$antal_ny=$_POST['antal_ny'];
#	$h=$antal_ny*10;




if($_GET){
	$kladde_id=$_GET['kladde_id'];
	$ordre_id=$_GET['ordre_id'];
	$flyt=$_GET['flyt'];

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font<small><a href=kassekladde.php?kladde_id=$kladde_id accesskey=L>Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">$font<small>Hent ordrer</small></td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"></td>";
	print "</tbody></table>";
	print "</td></tr>";

	$x=0;
	$q=db_select("select id from kassekladde where kladde_id='$kladde_id'");
	while ($r  =db_fetch_array($q)) $x++;
	if ($x > 300) {
		Print "<BODY onLoad=\"javascript:alert('Max antal kladdelinjer nået!')\">";
	} else {
		if (($kladde_id)&&($ordre_id)) {
			flytordre($kladde_id, $ordre_id);
		}
		if (($kladde_id)&&($flyt=='alle'))	{
#		flytordre($kladde_id, $ordre_id);
			$q = db_select("select id from ordrer where status=3 order by art, fakturanr");
			while ($r = db_fetch_array($q)) {
				$x=0;
				$q2=db_select("select id from kassekladde where kladde_id='$kladde_id'");
				while ($r2=db_fetch_array($q2)) $x++;
				if ($x > 300) Print "<BODY onLoad=\"javascript:alert('Max antal kladdelinjer nået!')\">";
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
	global $font;

	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
		if ($row = db_fetch_array($query))
		{
			$year=substr(str_replace(" ","",$row['box2']),-2);
			$aarstart=str_replace(" ","",$year.$row['box1']);
			$year=substr(str_replace(" ","",$row['box4']),-2);
			$aarslut=str_replace(" ","",$year.$row['box3']);
		}
	}
	$x=0;
	print "<tr><td align=center>$font<small><table border=1 cellspacing=0 cellpadding=0 width=80%><tbody>";
	
	print "<tr><td>$font<small>Dato</td><td>$font<small>Beskrivelse</td><td>$font<small><br></td><td>$font<small>Debet</td><td>$font<small><br></td><td>$font<small>Kredit</td><td>$font<small>Fakturanr</td>
		<td align=center>$font<small>Bel&oslash;b</td><td align=center>$font<small>Valuta</td><td align=center>$font<small><a href=hentordrer.php?kladde_id=$kladde_id&flyt=alle>Flyt alle</a></td></tr>";
	$query = db_select("select ordrer.* from ordrer where status=3");
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
			$valuta='DKK';
		}
#		if ($row[moms]) {$moms[$x]=$row[moms];}
#		else {$moms[$x]=round($row[sum]*$row[momssats]/100,2);}
		$moms[$x]=$row['moms']*1;
		$sum[$x]=$row['sum']+$moms[$x];
	}
	$ordreantal=$x;

	for ($x=1;$x<=$ordreantal;$x++){
		print "<tr><td>$font<small></td></tr>";
		$query = db_select("select ordrelinjer.*, kontoplan.beskrivelse as kontonavn from ordrelinjer, kontoplan where kontoplan.kontonr=ordrelinjer.bogf_konto and kontoplan.regnskabsaar='$regnaar' and ordrelinjer.ordre_id=$id[$x];");
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
				print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small>K</td><td>$font<small>$kontonr[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dksum</td><td align=right>$font<small>A $valuta[$x]</td>";
			} else {
				$dksum=dkdecimal($sum[$x]);
				print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>K</td><td>$font<small>$kontonr[$x]</td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dksum</td><td align=right>$font<small>B $valuta[$x]</td>";
			}
			print "<td align=center>$font<small><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a></td></tr>";
			$ordrelinjer=$y;
			for ($y=1;$y<=$ordrelinjer;$y++)	{
				if ($bogf_konto[$y])	{
					if ($posnr[$y]<0) $tmpvaluta='DKK';
					else $tmpvaluta=$valuta[$x];
					$kontoart[$y]=$art[$x];
					if ($pris[$y]<0){
						$tmp=dkdecimal($pris[$y]*-1);
						print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$bogf_konto[$y]</span></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$tmp</td><td align=right>$font<small>C $tmpvaluta</td></tr>";
					}
					elseif ($pris[$y]>0) {
						 $tmp=dkdecimal($pris[$y]);
						 print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$bogf_konto[$y]</span></td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$tmp</td><td align=right>$font<small>D $tmpvaluta</td></tr>";
					}
				}
			}
			$query = db_select("select gruppe from adresser where id='$konto_id[$x]';");
			$row = db_fetch_array($query);
			$query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]';");
			$row = db_fetch_array($query);
			$box1=substr(trim($row['box1']),1,1);
			if ($moms[$x] && substr(trim($row['box1']),0,1)=='E') {
				$query = db_select("select grupper.box1, grupper.box2, grupper.box3, kontoplan.beskrivelse as kontonavn, kontoplan from grupper where kontoplan.kontonr=grupper.box1 and kontoplan.regnskabsaar='$regnaar' and art='EM' and kodenr='$box1'");
				$row = db_fetch_array($query);
				$box1=trim($row[box3]); # Ser lidt forvirrende ud, men den er go nok - fordi kbsmomsen ligger i box 3 v. udenlandsmoms.
				$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
#				$moms[$x]=$sum[$x]/100*$row[box2]; #moms af varekb i udlander beregnes
				$tmp=dkdecimal($moms[$x]);
				if ($moms[$x]>0) {
					print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>F</td><td>$font<small>$row[box1]</td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$tmp</td><td align=right>$font<small>E $valuta[$x]</td></tr>";
				} else {
					print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small>F</td><td>$font<small>$row[box1]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$tmp</td><td align=right>$font<small>F $valuta[$x]</td></tr>";
				}
			}
			else {
				$query = db_select("select grupper.box1, kontoplan.beskrivelse as kontonavn from grupper, kontoplan  where kontoplan.kontonr=grupper.box1 and kontoplan.regnskabsaar='$regnaar' and art='KM' and kodenr='$box1'");
				$row = db_fetch_array($query);
				$box1=trim($row[box1])*1;
				$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			}
			if ($moms[$x]<0){
				$dkmoms=dkdecimal($moms[$x]*-1);
				print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$box1</span></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dkmoms</td><td align=right>$font<small>G $valuta[$x]</td></tr>";
			}
			elseif ($moms[$x]>0) {
				$dkmoms=dkdecimal($moms[$x]);
				print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$box1</span></td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dkmoms</td><td align=right>$font<small>H $valuta[$x]</td></tr>";
			}
		} else{ 
			if ($sum[$x]<0){
			$dksum=dkdecimal($sum[$x]*-1);
				print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>D</td><td>$font<small>$kontonr[$x]</td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dksum</td><td align=right>$font<small>I $valuta[$x]</td>";
			}
			else {
				$dksum=dkdecimal($sum[$x]);
				print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small>D</td><td>$font<small>$kontonr[$x]</span></td><td>$font<br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dksum</td><td align=right>$font<small>J $valuta[$x]</td>";
			}
			print "<td align=center>$font<small><a href=hentordrer.php?kladde_id=$kladde_id&ordre_id=$id[$x]>Flyt til kladde</a></td></tr>";
			
			$ordrelinjer=$y;
			for ($y=1;$y<=$ordrelinjer;$y++){
				if ($bogf_konto[$y])	{
					$kontoart[$y]=$art[$x];
					if ($posnr[$y]<0) $tmpvaluta='DKK';
					else $tmpvaluta=$valuta[$x];
					if ($pris[$y]<0)	{
						$pris[$y]=$pris[$y]*-1;
						$pris[$y]=dkdecimal($pris[$y]);
						print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$bogf_konto[$y]</span></td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$pris[$y]</td><td align=right>$font<small>K $tmpvaluta</td></tr>";
					}
					else {			
						$pris[$y]=dkdecimal($pris[$y]);
						print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$bogf_konto[$y]</span></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$pris[$y]</td><td align=right>$font<small>L $tmpvaluta</td></tr>";
					}
				}
			}
			$query = db_select("select gruppe from adresser where id='$konto_id[$x]';");
			$row = db_fetch_array($query);
			$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';");
			$row = db_fetch_array($query);
			$box1=substr(trim($row[box1]),1,1);
			$query = db_select("select grupper.box1, kontoplan.beskrivelse as kontonavn from grupper, kontoplan  where kontoplan.kontonr=grupper.box1 and kontoplan.regnskabsaar='$regnaar' and art='SM' and kodenr='$box1'");
			$row = db_fetch_array($query);
			$box1=trim($row['box1']);
			$kontonavn[$x]=str_replace(" ","&nbsp;",$row['kontonavn']);
			$dkmoms=dkdecimal($moms[$x]);
			if (($art[$x]=='DO')&&($moms[$x]!=0)) {print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>$firmanavn[$x]</td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$box1</span></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dkmoms</td><td align=right>$font<small>M $valuta[$x]</td></tr>";}
			elseif ($moms[$x]!=0) {print "<tr><td>$font<small>$posnr[$y] $fakturadato[$x]</td><td>$font<small>N $firmanavn[$x]</td><td>$font<small>F</td><td>$font<small><span title='$kontonavn[$x]'>$box1</span></td><td>$font<small><br></td><td>$font<small><br></td><td>$font<small>$fakturanr[$x]</td><td align=right>$font<small>$dkmoms</td><td align=right>$font<small>N $valuta[$x]</td></tr>";} 
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

	transaktion("begin");
	if (!$aarstart) {
		$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'");
		if ($row = db_fetch_array($query)) {
			$year=substr(str_replace(" ","",$row['box2']),-2);
			$aarstart=str_replace(" ","",$year.$row['box1']);
			$year=substr(str_replace(" ","",$row['box4']),-2);
			$aarslut=str_replace(" ","",$year.$row['box3']);
		}
	}
	$query = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'");
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
	$query = db_select("select * from ordrer where status=3 and id='$ordre_id'");
	if ($row = db_fetch_array($query)) {
		list ($year, $month, $day) = split ('-', $row['fakturadate']);
		$year=substr($year,-2);
		$ym=$year.$month;
		if (($ym>=$aarstart)&&($ym<=$aarslut)) {
			$id=$row['id'];
			$art=$row['art'];
			$konto_id=$row['konto_id'];
			$kontonr=str_replace(" ","",$row['kontonr']);
			$firmanavn=trim($row['firmanavn']);
			$modtagelse=$row['modtagelse'];
			$transdate=($row['fakturadate']);
			$fakturanr=addslashes($row['fakturanr']);
			$ordrenr=$row['ordrenr'];
			$valuta=$row['valuta'];
			if ($valuta && $valuta!='DKK') {
				$r=db_fetch_array(db_select("select kodenr from grupper where box1='$valuta' and art='VK'"));
				$valuta=$r['kodenr']*1;
			} else $valuta=0;
			$projekt=$row['projekt']*1;
#			if ($row['moms']) {$moms=$row['moms'];}
#			else {$moms=round($row['sum']*$row['momssats']/100,2);}
			$moms=$row['moms']*1;
			$sum=$row['sum']+$moms;
			$ordreantal=$x;
			$q=db_select("select id, afd from ansatte where navn = '$row[ref]'");
			$r= db_fetch_array($q);
			$afd=$r['afd']*1;
			$ansat=$r['id']*1;
			if ((substr($art,0,1)=='K')&&($modtagelse>0)&&($modtbill==1)) $bilag=$modtagelse;
			elseif ((substr($art,0,1)=='D')&&($no_faktbill==1)) $bilag='0';
			elseif ((substr($art,0,1)=='D')&&($faktbill==1)) $bilag=trim($fakturanr);
			else {
				$row = db_fetch_array(db_select("select MAX(bilag) as bilag from kassekladde"));
				$bilag=$row['bilag']+1;
			}
			if (substr($art,0,1)=='K') {
				if (substr($art,1,1)=='K') $beskrivelse ="Lev. Kreditnota - ".$fakturanr;
				else $beskrivelse ="Lev.Faktura - ".$fakturanr;
				$beskrivelse=addslashes($beskrivelse);	
#echo "A insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'K', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')<br>";
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'K', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')");
				$query = db_select("select * from ordrelinjer where ordre_id=$id;");
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
						$kontoart[$y]=$art;
						if ($pris[$y]<0) {		 
							$pris[$y]=$pris[$y]*-1;
#echo "B insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')<br>";
							db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')");
						}
						elseif ($pris[$y]>0) {
#echo "C insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')<br>";
							db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')");
							}
					}
				}
				$query = db_select("select gruppe from adresser where id='$konto_id';");
				$row = db_fetch_array($query);
				$query = db_select("select box1 from grupper where art='KG' and kodenr='$row[gruppe]';");
				$row = db_fetch_array($query);
				$box1=substr(trim($row['box1']),1,1);
				
				if (substr(trim($row['box1']),0,1)=='E') {
					$query = db_select("select box1, box2, box3 from grupper where art='EM' and kodenr='$box1'");
					$row = db_fetch_array($query);
					$box1=trim($row['box3']); # Ser lidt forvirrende ud, men den er go nok - fordi koebsmomsen ligger i box 3 v. udenlandsmoms.
					$moms=$sum/100*$row['box2']; #moms af varekoeb i udland beregnes
#echo "D insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$row[box1]', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')<br>";
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$row[box1]', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')");
				}
				else {
					$query = db_select("select box1 from grupper where art='KM' and kodenr='$box1'");
					$row = db_fetch_array($query);
					$box1=trim($row['box1']);
				}
#echo "E insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')<br>";
				db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')");
			}	else {
				if ($art=='RB') $beskrivelse ="Rykker  - ".$ordrenr;
				elseif (substr($art,1,1)=='K') $beskrivelse ="Kreditnota - ".$fakturanr;
				else $beskrivelse ="Faktura - ".$fakturanr;
				
				if ($sum[$x]<0) {
#echo "F insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')<br>";
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')");
				} else {
#echo "G insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')<br>";
					db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'D', '$kontonr', '$fakturanr', '$sum', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')");
				}
				$query = db_select("select * from ordrelinjer where ordre_id=$id;");
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
#echo "insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')<br>";
							db_modify("insert into kassekladde (bilag, transdate, beskrivelse, d_type, debet, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')");
						}
						elseif ($pris[$y]>0) {
#echo "insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')<br>";
							db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$bogf_konto[$y]', '$fakturanr', '$pris[$y]', 'on', '$kladde_id', '$afd', '$tmpvaluta', '$projekt', '$ansat', '$ordre_id')");
						}
					}
				}
				if ($art!='RB') {
					$query = db_select("select gruppe from adresser where id='$konto_id';");
					$row = db_fetch_array($query);
					$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';");
					$row = db_fetch_array($query);
					$box1=substr(trim($row[box1]),1,1);
					$query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'");
					$row = db_fetch_array($query);
					$box1=trim($row[box1]);
					if ($moms && $box1) db_modify("insert into kassekladde (bilag, transdate, beskrivelse, k_type, kredit, faktura, amount, momsfri, kladde_id, afd, valuta, projekt, ansat, ordre_id) values ('$bilag', '$transdate', '$beskrivelse', 'F', '$box1', '$fakturanr', '$moms', 'on', '$kladde_id', '$afd', '$valuta', '$projekt', '$ansat', '$ordre_id')");
				}
			}

			db_modify("update ordrer set status=4 where id=$id");
			db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0");
		}
		else {Print "<BODY onLoad=\"javascript:alert('Ordredato uden for regnskabs&aring;r!')\">";}
	}
	transaktion("commit");
}
######################################################################################################################################
