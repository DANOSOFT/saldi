<?php
// --------------------------------finans/bogfor.php--------lap 2.0.0g------2008.05.07----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
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

$title="Bogf&oslash;r kassekladde";

$funktion=$_GET['funktion'];
$kladde_id=$_GET['kladde_id'];
if (($_POST) && ($_POST['kladde_id'])) $kladde_id = $_POST['kladde_id'];
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdato.php");
include("../includes/usdate.php");
include("../includes/dkdecimal.php");
include("../includes/usdecimal.php");
include("../includes/db_query.php");
include("../includes/genberegn.php");

if ($kladde_id) {	
	$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
	if ($row[bogfort]!='-') {
		print "<BODY onLoad=\"javascript:alert('Kladden er allerede bogf&oslash;rt - kladden lukkes')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		exit;
	}
}
if ($funktion=='bogfor') {
	$overskrift="Bogf&oslash;r kassekladde $kladde_id";
	$href="<a href=kassekladde.php?kladde_id=$kladde_id accesskey=B>";
}
else {
	$overskrift="Simuleret bogf&oslash;ring, kladde $kladde_id";
	$href="<a href=../includes/luk.php accesskey=L>";
}
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$href Tilbage</a></small></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small>$overskrift</small></td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><small></a></small></td>";
	print "</tbody></table>";
	print "</td></tr>";
/* 
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"><


</head>
<body bgcolor="#339999" link="#000000" vlink="#000000" alink="#000000" center="">
<div align="center">
<?php
*/
if ($_POST) {
	$submit = (substr(trim($_POST['submit']),0,3));
	$kladde_id = $_POST['kladde_id'];
	$kladdenote = trim($_POST['kladdenote']);
	if ($submit=='Bog') {
		transaktion(begin);
		bogfor($kladde_id, $kladdenote);
		db_modify("delete from tmpkassekl where kladde_id = $kladde_id");
		transaktion(commit);
		print "<BODY onLoad=\"javascript=opener.location.reload();\">";
	}
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
}

$x=0;
$debetsum=0;
$kreditsum=0;
if ($kladde_id) {
	$posteringer=0;
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag");
	while ($row =	db_fetch_array($query)){
		$posteringer++;
		$bilag[$posteringer]=$row[bilag];
		$y=$row[bilag];
		$d_type[$posteringer]=trim($row['d_type']);
		$debet[$posteringer]=$row['debet']*1;
		$k_type[$posteringer]=trim($row['k_type']);
		$kredit[$posteringer]=$row['kredit']*1;
		$faktura[$posteringer]=trim($row['faktura']);
		$amount[$posteringer]=$row['amount']*1;
		$momsfri[$posteringer]=trim($row['momsfri']);
		if ($row['valuta'] && $row['valuta']!='DKK') $dkkamount[$posteringer]=valutaopslag($amount[$posteringer],$row['valuta'],$row['transdate']);
		else $dkkamount[$posteringer]=$amount[$posteringer];
		if($debet[$posteringer]) {
			$tjeksum[$y]= round($tjeksum[$y]+$dkkamount[$posteringer],2);
			$debetsum=$debetsum+$dkkamount[$posteringer];
		}
		if($kredit[$posteringer]) {
			$tjeksum[$y]=round($tjeksum[$y]-$dkkamount[$posteringer],2);
			$kreditsum=$kreditsum+$dkkamount[$posteringer];
		}
	}
}
# kontrollerer om der er tale om en debitor eller kreditor konto hvor der skal beregnes moms 
# Konti fra kontoplanen bliver forbigået i funktionen
for ($y=1; $y<=$posteringer; $y++) {
	if (strlen($debet[$y])>0){
		list ($debet[$y], $d_momsart[$y]) =gruppeopslag($d_type[$y], $debet[$y]);
	}
	if (strlen($kredit[$y])>0){
		list ($kredit[$y], $k_momsart[$y])=gruppeopslag($k_type[$y], $kredit[$y]);
	}
}

# Funktionen momsberegning finder momssatsen og beregner momsen. 
for ($y=1; $y<=$posteringer; $y++) {
	$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
	$debet[$y]=str_replace(" ","",$debet[$y]);
	$kredit[$y]=str_replace(" ","",$kredit[$y]);
	if ($debet[$y]>0) $d_amount[$y]=$dkkamount[$y];
	if ($kredit[$y]>0) $k_amount[$y]=$dkkamount[$y];
	if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)) {
	list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y]);
	}
	if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)){
		list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y]);
	}
}
/*
Alle posteringer loebes igennem igen - Hvis der er tale en en postering med EU moms er der en modkonto (x-modkto)- 
Hvis der samtidig er en modpostering flyttes modposteringen op "for enden" af posteringsrækken og antallet af posteringer oeges med en.
Denne flytning sker KUN naar den er tale om en dobbeltpostering hvor den ene eller begge er konti fra kontoplanen med EU moms
*/
for ($y=1; $y<=$posteringer; $y++) {
	if ($d_modkto[$y]>0) {	
		if ($k_moms[$y]) {
			$posteringer++;
			$k_momskto[$posteringer]=$k_momskto[$y];
			$k_moms[$posteringer]=$k_moms[$y];
		}
		$k_moms[$y]=$d_moms[$y];
		$k_momskto[$y]=$d_modkto[$y];
	}
	if ($k_modkto[$y]>0) {
		if ($d_moms[$y]) { 
			$posteringer++;
			$d_momskto[$posteringer]=$d_momskto[$y];
			$d_moms[$posteringer]=$d_moms[$y];
		}
		$d_moms[$y]=$k_moms[$y];
		$d_momskto[$y]=$k_modkto[$y];
	}
}
$kontoantal=0;
$kontoliste=array()	;
for ($y=1; $y<=$posteringer; $y++) {
	if ((!in_array($debet[$y], $kontoliste))&&($debet[$y]>0)) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$debet[$y];
	}
	if ((!in_array($kredit[$y], $kontoliste))&&($kredit[$y]>0)) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$kredit[$y];
	}
	if (($d_momskto[$y])&&(!in_array($d_momskto[$y], $kontoliste))) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$d_momskto[$y];
	}
	if (($k_momskto[$y])&&(!in_array($k_momskto[$y], $kontoliste))) {
		$kontoantal++;
		$kontoliste[$kontoantal]=$k_momskto[$y];
	}
}
sort($kontoliste);
$kontodebet=array();
$kontokredit=array();
for ($y=0; $y<$kontoantal; $y++) {
	for($z=1; $z<=$posteringer; $z++) {
		if ($kontoliste[$y]==$debet[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_amount[$z];}
		if ($kontoliste[$y]==$kredit[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_amount[$z];}
		if ($kontoliste[$y]==$d_momskto[$z]){$kontodebet[$y]=$kontodebet[$y]+$d_moms[$z];}
		if ($kontoliste[$y]==$k_momskto[$z]){$kontokredit[$y]=$kontokredit[$y]+$k_moms[$z];}
	}
}

print "<form name=kassekladde action=bogfor.php method=post>";
if ($funktion=='bogfor') {
	$query = db_select("select kladdenote from kladdeliste where id=$kladde_id");
	$row = db_fetch_array($query);
	print "<td align=center><small><b><font face=\"Helvetica, Arial, sans-serif\">Bem&aelig;rkning:&nbsp;</b></small><input type=text size=95 name=kladdenote value='$row[kladdenote]'></td>";
	print "</tr><tr><td><hr></td></tr>";
}
$d_sum=0; $k_sum=0;
print "<tr><td align = center><table border=1 cellspacing=0 cellpadding=0><tbody><tr><td width=60>$font Konto</td><td width=200>$font Beskrivelse</td><td width=60>$font Saldo</td><td width=60>$font Debet</td><td width=60>$font Kredit</td><td width=60>$font Ny saldo</td></tr>";
for ($y=0; $y<$kontoantal; $y++) {
$d_sum=$d_sum+$kontodebet[$y];
$k_sum=$k_sum+$kontokredit[$y];
	$query = db_select("select * from kontoplan where kontonr='$kontoliste[$y]' and regnskabsaar='$regnaar'");
	if ($row = db_fetch_array($query)) {
		$saldo=$row[saldo];
		$a=dkdecimal($saldo);
		$b=dkdecimal($kontodebet[$y]);
		$c=dkdecimal($kontokredit[$y]);
		$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
		$beskrivelse=addslashes($row[beskrivelse]);
		print "<tr><td>$font $kontoliste[$y]</td><td>$font $beskrivelse</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
	}
	else {
		print "<tr><td>$font $kontoliste[$y]</td><td>FINDES IKKE !!</td><td align=right>$font $a</td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right>$font $d</td></tr>";
		$fejltext = "OBS:Kontonr: $kontoliste[$y] FINDES IKKE !!";
	}
}
$b=dkdecimal($d_sum);
$c=dkdecimal($k_sum);
print "<tr><td><br></td><td>$font Kontrolsum</td><td align=right><br></td><td align=right>$font $b</td><td align=right>$font $c</td><td align=right><br></td></tr>";


if (abs($debetsum-$kreditsum)>0.0099)  {
	 print "<tr><td colspan=6><br></td></tr>";
	 for ($x=1; $x<=$posteringer; $x++) {
		$y=$bilag[$x];
		if ($tjeksum[$y]!=0) {
			 print "<tr><td align=center colspan=6>OBS: Der er en diff. p&aring; $tjeksum[$y] (bilag $y) </td></tr>";
			 $tjeksum[$y]=0;
		}
	 }
#	 $diff=round($debetsum-$kreditsum,2);
#	 print "<tr><td colspan=6><br></td></tr><tr><td align=center colspan=6>OBS: Der er en diff. p&aring; $diff!!</td></tr>";
} elseif ($b!=$c) print "<BODY onLoad=\"javascript:alert('Der er opst&aring;et en fejl, kontakt venligst Saldis udviklings team')\">";
elseif ($fejltext) print "<tr><td colspan=6><br></td></tr><tr><td align=center colspan=6>$fejltext</td></tr>";
else {
	$query = db_select("select * from kladdeliste where id = $kladde_id and bogfort = 'V'");
	if ($row = db_fetch_array($query)) {
		 print "Kladden er bogf&oslash;rt!";
		 genberegn($regnaar);
	}
	else {
		print "<input type=hidden name=kladde_id value=$kladde_id>";
		if ($funktion=='bogfor') print "<tr><td colspan=6><br></td></tr><tr><td colspan=6 align=center><input type=submit accesskey=\"b\" value=\"Bogf&oslash;r\" name=\"submit\"></td></tr>";
		else print "<tr><td colspan=6><br></td></tr><tr><td colspan=6 align=center><input type=submit accesskey=\"b\" value=\"&nbsp;&nbsp;Luk&nbsp;&nbsp;\" name=\"submit\"></td></tr>";
		print "</form>";
	}
}
print "</td></tr></tbody></table>";
######################################################################################################################################
function bogfor($kladde_id, $kladdenote)
{
	global $connection;
	global $regnaar;
	global $brugernavn;
	
	$tjeksum=0;
	$posteringer=0;
	$transantal=0;
	$transtjek=0;

	$d_momsart=array(); $k_momsart=array();
	db_modify("update kladdeliste set kladdenote = '$kladdenote' where id = '$kladde_id'");
	$y=0;
	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag");
	while ($row =	db_fetch_array($query)) {
		$y++;
		$postid[$y]=$row['id'];
		if ($row['debet']>0){$transantal++;}
		if ($row['kredit']>0){$transantal++;}
		$eufaktnr[$y]="!@&/(=bh#jH%Tf)D"; # maa ikke vaere en vaerdi som kan risikere at vaere et relt fakturanr.
#			$query = db_select("select * from kassekladde where id = '$postid[$y]'");
#			$row =db_fetch_array($query);
		$bilag[$y]=$row['bilag'];
		$beskrivelse[$y]=addslashes($row['beskrivelse']);
		$d_type[$y]=$row['d_type'];
		$debet[$y]=$row['debet'];
		$k_type[$y]=$row['k_type'];
		$kredit[$y]=$row['kredit'];
		$faktura[$y]=addslashes($row['faktura']);
		$amount[$y]=$row['amount'];;
		if ($row['valuta'] && $row['valuta']!='DKK') $dkkamount[$y]=valutaopslag($amount[$y],$row['valuta'],$row['transdate']);
		else $dkkamount[$y]=$amount[$y];
		$momsfri[$y]=$row['momsfri'];
		$afd[$y]=$row['afd'];
		$ansat[$y]=$row['ansat']*1;
		$projekt[$y]=$row['projekt']*1;
		$valuta[$y]=$row['valuta']*1;
		$ordre_id[$y]=$row['ordre_id']*1;
		$valutakurs[$y]=$row['valutakurs']*1;
		if (!$valutakurs[$y]) $valutakurs[$y]=100;
		$transdate[$y]=$row['transdate'];
		
# kontrollerer om der er tale om en debitor eller kreditor konto hvor der skal beregnes moms 
# Konti fra kontoplanen bliver forbigået i funktionen
		if (((strstr($d_type[$y],'D'))||(strstr($d_type[$y],'K')))&&($debet[$y]>0)) {
			openpost($d_type[$y], $debet[$y], $bilag[$y], $faktura[$y], $amount[$y], $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y]);
			list ($debet[$y], $d_momsart[$y]) =gruppeopslag($d_type[$y], $debet[$y]);
			if ($d_momsart[$y]=='E') $eufaktnr[$y]=$faktura[$y]; # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
		}
		if ((($k_type[$y]=='D')||($k_type[$y]=='K'))&&($kredit[$y]>0)) {
			openpost($k_type[$y], $kredit[$y], $bilag[$y], $faktura[$y], $amount[$y]*-1, $beskrivelse[$y], $transdate[$y], $postid[$y], $valuta[$y], $valutakurs[$y]);
			list ($kredit[$y], $k_momsart[$y])=gruppeopslag($k_type[$y], $kredit[$y]);
			if ($k_momsart[$y]=='E') $eufaktnr[$y]=$faktura[$y];  # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
		}
		$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
		$debet[$y]=str_replace(" ","",$debet[$y]);
		$kredit[$y]=str_replace(" ","",$kredit[$y]);
		$d_amount[$y]=0; $d_moms[$y]=0; $d_momskto[$y]=0; $d_modkto[$y]=0;
		$k_amount[$y]=0; $k_moms[$y]=0; $k_momskto[$y]=0; $k_modkto[$y]=0;
			if ($debet[$y]>0){$d_amount[$y]=$dkkamount[$y];}
		if ($kredit[$y]>0){$k_amount[$y]=$dkkamount[$y];}
		$logdate=date("Y-m-d");
		$logtime=date("H:i");
		list ($x, $month, $x)=split('-', $transdate[$y]);
		if (!$afd[$y]){$afd[$y]=0;}
		if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)&&(substr($momsart,0,1)!='E')) list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y]);
		if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)&&(substr($momsart,0,1)!='E')) list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y]);
	} # end while
	$posteringer=$y;
	for ($y=1; $y<=$posteringer; $y++) {
/*
Alle posteringer loebes igennem igen - Hvis der er tale en en postering med EU moms er der en modkonto (x-modkto)- 
Hvis der samtidig er en modpostering flyttes modposteringen op "for enden" af posteringsrækken og antallet af posteringer oeges med en.
Denne flytning sker KUN naar den er tale om en dobbeltpostering hvor den ene eller begge er konti fra kontoplanen med EU moms
*/
			if (($d_modkto[$y]>0)&&($eufaktnr[$y]!=$faktura[$y])){
				if ($k_moms[$y]) {
					$posteringer++;
					$k_momskto[$posteringer]=$k_momskto[$y];
					$k_moms[$posteringer]=$k_moms[$y];
					$bilag[$posteringer]=$bilag[$y];
					$beskrivelse[$posteringer]=$beskrivelse[$y];
					$faktura[$posteringer]=$faktura[$y];
					$afd[$posteringer]=$afd[$y];
					$transdate[$posteringer]=$transdate[$y];
					$ansat[$posteringer]=$ansat[$y];
					$projekt[$posteringer]=$projekt[$y];
					$ordre_id[$posteringer]=$ordre_id[$y];
					$valutakurs[$posteringer]=$valutakurs[$y];
					$valuta[$posteringer]=$valuta[$y];
				}
				$k_moms[$y]=$d_moms[$y];
				$k_momskto[$y]=$d_modkto[$y];
				$transantal++;
			}
			
			if (($k_modkto[$y]>0)&&($eufaktnr[$y]!=$faktura[$y])){
				if ($d_moms[$y]) { 
					$posteringer++;
					$d_momskto[$posteringer]=$d_momskto[$y];
					$d_moms[$posteringer]=$d_moms[$y];
					$bilag[$posteringer]=$bilag[$y];
					$beskrivelse[$posteringer]=$beskrivelse[$y];
					$faktura[$posteringer]=$faktura[$y];
					$afd[$posteringer]=$afd[$y];
					$transdate[$posteringer]=$transdate[$y];
					$transdate[$posteringer]=$transdate[$y];
					$ansat[$posteringer]=$ansat[$y];
					$projekt[$posteringer]=$projekt[$y];
					$ordre_id[$posteringer]=$ordre_id[$y];
					$valutakurs[$posteringer]=$valutakurs[$y];
					$valuta[$posteringer]=$valuta[$y];
				}
				$d_moms[$y]=$k_moms[$y];
				$d_momskto[$y]=$k_modkto[$y];
				$transantal++;
			}
			if ($d_momskto[$y]>0) $transantal++;
			
			if ($k_momskto[$y]>0) $transantal++; 
			if ($eufaktnr[$y]!=$faktura[$y]&&$d_momskto[$y]>0&&$k_momskto[$y]>0&&$d_momskto[$y]!=$k_momskto[$y]) $transantal--; # indsat 280807 grundet fejl ved konti (i kontoplan) m. eumoms 
# 07052008 tilføjet "&&$d_momskto[$y]!=$k_momskto[$y]" da man ellers ikke kan bogføre transaktioner hvor byde debet og kredit har ens momskonti.
			if ($debet[$y]>0) {
				$tjeksum=$tjeksum+$d_amount[$y];	
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)values($debet[$y], $bilag[$y], '$transdate[$y]', '$logdate', '$logtime', '$beskrivelse[$y]', '$d_amount[$y]', '$faktura[$y]', $kladde_id, '$afd[$y]', '$ansat[$y]', '$projekt[$y]', '$valuta[$y]', '$valutakurs[$y]', '$ordre_id[$y]')");
				$query = db_select("select * from transaktioner where kontonr='$debet[$y]' and bilag='$bilag[$y]' and transdate='$transdate[$y]' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and debet='$d_amount[$y]' and faktura='$faktura[$y]' and kladde_id='$kladde_id' and afd='$afd[$y]'");
				if ( db_fetch_array($query)) {
					$transtjek++;
					$query = db_select("select id, saldo from kontoplan where kontonr='$debet[$y]' and regnskabsaar=$regnaar");
					$row= db_fetch_array($query);
					$kasklid[$transtjek]=$row[id];
					$kasklmonth[$transtjek]=$row[saldo];
					$transamount[$transtjek]=$d_amount[$y];
#					$transmonth[$transtjek]=$month;
				}
				else {print "<tr><td>Der er sket en fejl ved bogf&oslash;ring af bilag: $bilag[$y], debetkonto: $debet[$y]!</td></tr>";}
			}
			if ($kredit[$y]>0) {
				$tjeksum=$tjeksum-$k_amount[$y];
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)values($kredit[$y], $bilag[$y], '$transdate[$y]', '$logdate', '$logtime', '$beskrivelse[$y]', '$k_amount[$y]', '$faktura[$y]', $kladde_id, '$afd[$y]', '$ansat[$y]', '$projekt[$y]', '$valuta[$y]', '$valutakurs[$y]', '$ordre_id[$y]')");
				$query = db_select("select * from transaktioner where kontonr='$kredit[$y]' and bilag=$bilag[$y] and transdate='$transdate[$y]' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and kredit='$k_amount[$y]' and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]");
				if ( db_fetch_array($query)) {
					$transtjek++;
					$query = db_select("select id, saldo from kontoplan where kontonr='$kredit[$y]' and regnskabsaar=$regnaar");
					$row= db_fetch_array($query);
					$kasklid[$transtjek]=$row['id'];
					$kasklmonth[$transtjek]=$row['saldo'];
					$transamount[$transtjek]=$k_amount[$y]*-1;
				}
				else {print "<tr><td>Der er sket en fejl ved bogfring af bilag: $bilag[$y], kreditkonto: $kredit[$y]!</td></tr>"; }
			}
			
			if ($d_momskto[$y]>0) {
				$tjeksum=$tjeksum+$d_moms[$y];
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)values($d_momskto[$y], $bilag[$y], '$transdate[$y]', '$logdate', '$logtime', '$beskrivelse[$y]', '$d_moms[$y]', '$faktura[$y]', $kladde_id, '$afd[$y]', '$ansat[$y]', '$projekt[$y]', '$valuta[$y]', '$valutakurs[$y]', '$ordre_id[$y]')");
				$query = db_select("select * from transaktioner where kontonr=$d_momskto[$y] and bilag=$bilag[$y] and transdate='$transdate[$y]' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and debet='$d_moms[$y]' and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]");
				if ( db_fetch_array($query)) {
					$transtjek++;
					 $query = db_select("select id, saldo from kontoplan where kontonr='$d_momskto[$y]' and regnskabsaar=$regnaar");
					 $row= db_fetch_array($query);
					 $kasklid[$transtjek]=$row['id'];
					 $kasklmonth[$transtjek]=$row['saldo'];
					 $transamount[$transtjek]=$d_moms[$y];
#					 $transmonth[$transtjek]=$month;
				 }
			 	else {print "<tr><td>Der er sket en fejl ved bogfring af bilag: $bilag[$y], debetkonto: $d_momskto[$y]!</td></tr>";}
			}
			if ($k_momskto[$y]>0) {
				$tjeksum=$tjeksum-$k_moms[$y];
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)values($k_momskto[$y], $bilag[$y], '$transdate[$y]', '$logdate', '$logtime', '$beskrivelse[$y]', '$k_moms[$y]', '$faktura[$y]', $kladde_id, '$afd[$y]', '$ansat[$y]', '$projekt[$y]', '$valuta[$y]', '$valutakurs[$y]', '$ordre_id[$y]')");
				$query = db_select("select * from transaktioner where kontonr=$k_momskto[$y] and bilag=$bilag[$y] and transdate='$transdate[$y]' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse[$y]' and kredit='$k_moms[$y]' and faktura='$faktura[$y]' and kladde_id=$kladde_id and afd=$afd[$y]");
				if ( db_fetch_array($query)){
					$transtjek++;
					$query = db_select("select id, saldo from kontoplan where kontonr='$k_momskto[$y]' and regnskabsaar=$regnaar");
					$row= db_fetch_array($query);
					$kasklid[$transtjek]=$row[id];
					$kasklmonth[$transtjek]=$row[saldo];
					$transamount[$transtjek]=$k_moms[$y]*-1;
#					$transmonth[$transtjek]=$month;
				}
				else {print "<tr><td>Der er sket en fejl ved bogfring af bilag: $bilag[$y], kreditkonto: $k_momskto[$y]!</td></tr>";}
			}
		}
		if (abs($tjeksum)<0.01) { # && $transtjek==$transantal){
			$dato=date("Y-m-d");
			 db_modify("update kladdeliste set bogfort = 'V', bogforingsdate = '$dato', bogfort_af = '$brugernavn' where id = '$kladde_id'");
			for ($x=1; $x<=$transtjek; $x++) {
				$query = db_select("select saldo from kontoplan where id='$kasklid[$x]'");
				$row= db_fetch_array($query);
				$temp=$row[saldo];
				if (!$temp) {$temp=0;}
				$transamount[$x]=($temp+$transamount[$x]);
				 db_modify("update kontoplan set saldo = $transamount[$x] where id = '$kasklid[$x]'");
			}
		} else {
			print "<tr><td align=center>$font Der er konstateret en afvigelse!\nKladde ikke bogf&oslash;rt\nKontakt venligst Saldi's udviklerteam!</td></tr>";
			exit;
		}
#	}
}
######################################################################################################################################
function openpost($art, $debet, $bilag, $faktura, $amount, $beskrivelse, $transdate, $bilag_id, $valutakode, $valutakurs){
	global $connection;
	global $regnaar;
	global $kladde_id;

## Finder kreditorens valuta;
	if ($valutakode) {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valutakode'"));
		$valuta=$r['box1'];
	} else $valuta='DKK';
	$udlignet=0;
	$dato=date("Y-m-d");
	$belob=$amount*-1;
	$debet=str_replace(" ","",$debet);
	$query = db_select("select id from adresser where kontonr = '$debet' and art ='$art'");
	while($row = db_fetch_array($query)){
		$konto_id=$row['id'];
		$query = db_select("select MAX(udlign_id) as udlign_id from openpost");
		if ($row = db_fetch_array($query)) $udlign_id=$row['udlign_id']+1;
		$query = db_select("select id from openpost where konto_id='$konto_id' and faktnr='$faktura' and amount='$belob' and udlignet!='1'");
		if ($row = db_fetch_array($query)) {
			db_modify("update openpost set udlignet = '1', udlign_date= '$transdate', udlign_id=$udlign_id where id = '$row[id]'");
			db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, refnr, beskrivelse, udlignet, transdate, kladde_id, udlign_date, udlign_id, bilag_id, valuta, valutakurs)values('$konto_id', '$debet', '$faktura', '$amount', '$bilag', '$beskrivelse', '1', '$transdate', '$kladde_id',  '$transdate', '$udlign_id', '$bilag_id', '$valuta', '$valutakurs')");
			$udlignet=1;
		}
	}
	if ($udlignet<1)	{
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, refnr, beskrivelse, udlignet, transdate, kladde_id, bilag_id, valuta, valutakurs)values('$konto_id', '$debet', '$faktura', '$amount', '$bilag', '$beskrivelse', '0', '$transdate', '$kladde_id', '$bilag_id', '$valuta', '$valutakurs')");
	}
}
######################################################################################################################################
function momsberegning($konto, $amount, $momsart, $kontrol) {
	global $connection;
	global $regnaar;

	$a=$momsart[0]; #Foerste tegn i strengen
	$b=$momsart[1]; #Andet tegn i strengen
	
	$r=db_fetch_array(db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'"));
	if (trim($r['moms'])) {
	if ($a=='E' && $b) {
		$c=$a.'M';
		$query = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
		if($row =	db_fetch_array($query)) { # Sï¿½er der moms pï¿½kontoen
			$q2 = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
			$x=$row['box2'];
			$moms=$amount/100*$x;
			$momskto=trim($row['box1']);
			$modkto=trim($row['box3']);
		}
	}
	else {	
		$query = db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'");
		if($row =	db_fetch_array($query)){
			$a=substr($row[moms],0,1);
			$b=substr($row[moms],1);
		}
#Hvis en momspligtig vare koebes i EU beregnes der EU moms. $kontrol er kun sat hvis der er tale om en kreditor
# og nedenstående træder således ikke i kraft naar der er tale om en finanskonto med EU moms.
		if ($a && $a!='E' && $kontrol[0]=='E') { 
			$a=$kontrol[0];	
			$b=$kontrol[1];
		} 
		$c=$a.'M';
		$query = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
		if($row =	db_fetch_array($query)) { # Saa er der moms paa kontoen
			$q2 = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
			$x=$row['box2'];
			if ($a=='E'){
				$moms=$amount/100*$x;
				$momskto=trim($row['box3']);
				$modkto=trim($row['box1']);
			}
			elseif ($kontrol[0]=='E'){
				$momskto=trim($row['box1']);
				$modkto=trim($row['box1']);
				$moms=$amount/100*$x;
			}
			else {
				$momskto=trim($row['box1']);
				$moms=$amount-($amount/((100+$x)/100));
				$amount=$amount-$moms;
			}
		}
	}} #else echo "forbigaet $konto, $amount, $momsart, $kontrol <br>";
	$svar=array($amount, $moms, $momskto, $modkto);
	return $svar;
}
######################################################################################################################################
function gruppeopslag($type, $konto)
{
	global $connection;
	
	if ($type=='D') $art='DG';
	elseif ($type=='K') $art='KG';
	if ($art){
	$tmp=substr($art,0,1);
		$query = db_select("select gruppe from adresser where kontonr = '$konto' and art='$tmp'");
		if ($row = db_fetch_array($query))	{
			$query = db_select("select box1, box2 from grupper where art='$art' and kodenr='$row[gruppe]'");
			if ($row =db_fetch_array($query)) {	
				$konto=$row['box2'];
				$momsart=$row['box1'];
			}
		}
	}
	$svar=array($konto, $momsart);
	return $svar;
}
######################################################################################################################################
function valutaopslag($amount, $valuta, $transdate)
{
	global $connection;
	global $fejltext;
	
	$r = db_fetch_array(db_select("select * from valuta where gruppe = '$valuta' and valdate <= '$transdate' order by valdate desc"));
	if ($r['kurs']) $amount=$amount*$r['kurs']/100;
	else {
		$r = db_fetch_array(db_select("select box1 from grupper where art = 'VK' and kodenr = '$valuta'"));
		$tmp=dkdato($transdate);
		$fejltext="---";
		print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for $r[box1] den $tmp')\">";	
	}
	return $amount;
}

?>
