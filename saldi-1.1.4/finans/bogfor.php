<?php
// --------------------------------finans/bogfor.php--------lap 1.1.5a------06.03.2008----
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

$tjeksum=array();
$fejltext=NULL;

include("../includes/std_func.php");

$funktion=if_isset($_GET['funktion']);
$kladde_id=if_isset($_GET['kladde_id']);
if (isset($_POST['kladde_id'])) {$kladde_id = $_POST['kladde_id'];}
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/genberegn.php");

if ($kladde_id) {	
	$row =db_fetch_array(db_select("select bogfort from kladdeliste where id = $kladde_id"));
	if ($row['bogfort']!='-') {
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
if ($_POST) {
	$submit = (substr(trim($_POST['submit']),0,3));
	$kladde_id = $_POST['kladde_id'];
	$kladdenote = trim($_POST['kladdenote']);
	if ($submit=='Bog') {
		transaktion('begin');
		bogfor($kladde_id, $kladdenote);
		db_modify("delete from tmpkassekl where kladde_id = $kladde_id");
		transaktion('commit');
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
		$bilag[$posteringer]=$row['bilag'];
		$y=$row['bilag'];
		$d_type[$posteringer]=$row['d_type'];
		$debet[$posteringer]=$row['debet'];
		$k_type[$posteringer]=$row['k_type'];
		$kredit[$posteringer]=$row['kredit'];
		$faktura[$posteringer]=$row['faktura'];
		$momsfri[$posteringer]=$row['momsfri'];
		if ($row['valuta']) {
			$r2 = db_fetch_array(db_select("select * from valuta where gruppe = '$row[valuta]' and valdate <= '$row[transdate]' order by valdate"));
			if ($r2['kurs']) $amount[$posteringer]=$row['amount']*$r2['kurs']/100;
			else print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for denne dato')\">";	
		} else $amount[$posteringer]=$row['amount'];
		if (!isset($tjeksum[$y])) $tjeksum[$y]=0;
		if($debet[$posteringer]) {
			$tjeksum[$y]= round($tjeksum[$y]+$amount[$posteringer],2);
			$debetsum=$debetsum+$amount[$posteringer];
		}
		if($kredit[$posteringer]) {
			$tjeksum[$y]=round($tjeksum[$y]-$amount[$posteringer],2);
			$kreditsum=$kreditsum+$amount[$posteringer];
		}
	}
}
for ($y=1; $y<=$posteringer; $y++) {
	if (strlen($debet[$y])>0){
		list ($debet[$y], $d_momsart[$y]) =gruppeopslag($d_type[$y], $debet[$y]);
	}
	if (strlen($kredit[$y])>0){
		list ($kredit[$y], $k_momsart[$y])=gruppeopslag($k_type[$y], $kredit[$y]);
	}
}
for ($y=1; $y<=$posteringer; $y++) {
	$d_modkto[$y]=NULL; $k_modkto[$y]=NULL;$d_momskto[$y]=NULL; $k_momskto[$y]=NULL; 
	$momsfri[$y]=str_replace(" ","",$momsfri[$y]);
	$debet[$y]=str_replace(" ","",$debet[$y]);
	$kredit[$y]=str_replace(" ","",$kredit[$y]);
	if ($debet[$y]>0) $d_amount[$y]=$amount[$y];
	if ($kredit[$y]>0) $k_amount[$y]=$amount[$y];
	if ((!$momsfri[$y])&&($debet[$y]>0)&&($d_amount[$y]>0)) {list ($d_amount[$y], $d_moms[$y], $d_momskto[$y], $d_modkto[$y])=momsberegning($debet[$y], $d_amount[$y], $d_momsart[$y], $k_momsart[$y]);}
	if ((!$momsfri[$y])&&($kredit[$y]>0)&&($k_amount[$y]>0)){list ($k_amount[$y], $k_moms[$y], $k_momskto[$y], $k_modkto[$y])=momsberegning($kredit[$y], $k_amount[$y], $k_momsart[$y], $d_momsart[$y]);}
	if ($d_modkto[$y]>0) {
		$k_moms[$y]=$d_moms[$y];
		$k_momskto[$y]=$d_modkto[$y];
	}
	if ($k_modkto[$y]>0) {
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
	if (!isset($kontoliste[$y]))	$kontoliste[$y]=NULL;
	if (!isset($kontokredit[$y]))	$kontokredit[$y]=NULL;
	if (!isset($kontodebet[$y]))	$kontodebet[$y]=NULL;
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
	print "<td align=center height=10%><small><b><font face=\"Helvetica, Arial, sans-serif\">Bem&aelig;rkning:&nbsp;</b></small><input type=text size=95 name=kladdenote value='$row[kladdenote]'></td>";
	print "</tr><tr><td height=1%><hr></td></tr>";
}
$d_sum=0; $k_sum=0;
print "<tr><td align = center><table border=1 cellspacing=0 cellpadding=0><tbody><tr><td width=60>$font Konto</td><td width=200>$font Beskrivelse</td><td width=60>$font Saldo</td><td width=60>$font Debet</td><td width=60>$font Kredit</td><td width=60>$font Ny saldo</td></tr>";
for ($y=0; $y<$kontoantal; $y++) {
$d_sum=$d_sum+$kontodebet[$y];
$k_sum=$k_sum+$kontokredit[$y];
	$query = db_select("select * from kontoplan where kontonr='$kontoliste[$y]' and regnskabsaar='$regnaar'");
	if ($row = db_fetch_array($query)) {
		$saldo=$row['saldo'];
		$a=dkdecimal($saldo);
		$b=dkdecimal($kontodebet[$y]);
		$c=dkdecimal($kontokredit[$y]);
		$d=dkdecimal($saldo+$kontodebet[$y]-$kontokredit[$y]);
		$beskrivelse=addslashes($row['beskrivelse']);
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
			print "<tr><td align=center colspan=6>$font OBS: Der er en diff. p&aring; $tjeksum[$y] (bilag: $y) </td></tr>";
			$tjeksum[$y]=0;
		}
	 }
#	 $diff=round('$debet'sum-$kreditsum,2);
#	 print "<tr><td colspan=6><br></td></tr><tr><td align=center colspan=6>OBS: Der er en diff. p&aring; $diff!!</td></tr>";
}
elseif ($b!=$c) print "<BODY onLoad=\"javascript:alert('Der er konstateret en afvigelse, kontakt venligst hotline - tlf. 4690 2208')\">";
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
	global $font;
	global $connection;
	global $regnaar;
	global $brugernavn;

	$posteringer=0; $transantal=0; $transtjek=0; $totalamount=0;
	$momsart=NULL;

	db_modify("update kladdeliste set kladdenote = '$kladdenote' where id = '$kladde_id'");

	$query = db_select("select * from kassekladde where kladde_id = $kladde_id order by bilag");
	while ($row =	db_fetch_array($query)) {
		$posteringer++;
		$postid[$posteringer]=$row['id'];
		if ($row['debet']>0) $transantal++;
		if ($row['kredit']>0) $transantal++;
	}
	if ($posteringer>0) {
 		db_modify("update kladdeliste set bogfort = '!' where id = '$kladde_id'");
		for ($i=1; $i<=$posteringer; $i++) {
			$d_momsart=''; $k_momsart='';
			$eufaktnr="@&/(=bh#jH%Tf)D"; # maa ikke vaere en vaerdi som kan risikere at vaere et relt fakturanr.
			$query = db_select("select * from kassekladde where id = '$postid[$i]'");
			$row =db_fetch_array($query);
			$bilag=$row['bilag'];
			$beskrivelse=addslashes($row['beskrivelse']);
			$d_type=$row['d_type'];
			$debet=$row['debet'];
			$k_type=$row['k_type'];
			$kredit=$row['kredit'];
			$faktura=addslashes($row['faktura']);
			if ($row['valuta']) {
			$r2 = db_fetch_array(db_select("select * from valuta where gruppe = '$row[valuta]' and valdate <= '$row[transdate]' order by valdate"));
			if ($r2['kurs']) {
				$amount=$row['amount']*$r2['kurs']/100;
				db_modify("update kassekladde set valutakurs = '$r2[kurs]' where id = '$postid[$i]'");
			}
			else print "<BODY onLoad=\"javascript:alert('Ups - ingen valutakurs for denne dato')\">";	
			} else $amount=$row['amount'];
			$momsfri=$row['momsfri'];
			$afd=$row['afd']*1;
			$ansat=$row['ansat']*1;
			$projekt=$row['projekt']*1;
			$valuta=$row['valuta']*1;
			$ordre_id=$row['ordre_id']*1;
			$valutakurs=$row['valutakurs']*1;
			$transdate = $row['transdate'];
		
			if (((strstr($d_type,'D'))||(strstr($d_type,'K')))&&($debet>0)) {
				openpost($d_type, $debet, $bilag, $faktura, $amount, $beskrivelse, $transdate);
				list ($debet, $d_momsart) =gruppeopslag($d_type, $debet);
				if ($d_momsart[0]=='E') $eufaktnr=$faktura; # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			}
			if ((($k_type=='D')||($k_type=='K'))&&($kredit>0)) {
				openpost($k_type, $kredit, $bilag, $faktura, $amount*-1, $beskrivelse, $transdate);
				list ($kredit, $k_momsart)=gruppeopslag($k_type, $kredit);
				if ($k_momsart[0]=='E') $eufaktnr=$faktura;  # Bruges laengere nede til at undgaa at transantal oeges v. eu momsposteringer.
			}
			

			$momsfri=str_replace(" ","",$momsfri);
			$debet=str_replace(" ","",$debet);
			$kredit=str_replace(" ","",$kredit);
			$d_amount=0; $d_moms=0; $d_momskto=0; $d_modkto=0;
			$k_amount=0; $k_moms=0; $k_momskto=0; $k_modkto=0;

			if ($debet>0){$d_amount=$amount;}
			if ($kredit>0){$k_amount=$amount;}
			$logdate=date("Y-m-d");
			$logtime=date("H:i");
			list ($x, $month, $x)=split('-', $transdate);
			if ((!$momsfri)&&($debet>0)&&($d_amount>0)&&(substr($momsart,0,1)!='E')) list ($d_amount, $d_moms, $d_momskto, $d_modkto)=momsberegning($debet, $d_amount, $d_momsart, $k_momsart);
			if ((!$momsfri)&&($kredit>0)&&($k_amount>0)&&(substr($momsart,0,1)!='E')) list ($k_amount, $k_moms, $k_momskto, $k_modkto)=momsberegning($kredit, $k_amount, $k_momsart, $d_momsart);
			if (($d_modkto>0)&&($eufaktnr!=$faktura)){
				$k_moms=$d_moms;
				$k_momskto=$d_modkto;
				$transantal++;
			}
			if (($k_modkto>0)&&($eufaktnr!=$faktura)){
				$d_moms=$k_moms;
				$d_momskto=$k_modkto;
				$transantal++;
			}
			if ($d_momskto>0) $transantal++;
			if ($k_momskto>0) $transantal++; 
			# indsat 280807 grundet fejl ved konti (i kontoplan) m. eumoms - tilføjet $eufaktnr!='@&/(=bh#jH%Tf)D' 121207 grundet fejl v. 2 momskonti paa samme linje
			if ($eufaktnr!='@&/(=bh#jH%Tf)D'&&$eufaktnr!=$faktura&&$d_momskto>0&&$k_momskto>0) $transantal--; 

			if ($debet>0) {
				$totalamount=$totalamount+$d_amount;
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id, afd, ansat, projekt, valuta, valutakurs, ordre_id)values('$debet', '$bilag', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$d_amount', '$faktura', '$kladde_id', '$afd', '$ansat', '$projekt', '$valuta', '$valutakurs', '$ordre_id')");
				$query = db_select("select * from transaktioner where kontonr='$debet' and bilag='$bilag' and transdate='$transdate' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse' and debet='$d_amount' and faktura='$faktura' and kladde_id='$kladde_id' and afd='$afd' and projekt = '$projekt'");
				if ( db_fetch_array($query)) {
					$transtjek++;
					$query = db_select("select id, saldo from kontoplan where kontonr='$debet' and regnskabsaar=$regnaar");
					$row= db_fetch_array($query);
					$kasklid[$transtjek]=$row['id'];
					$kasklmonth[$transtjek]=$row['saldo'];
					$transamount[$transtjek]=$d_amount;
				}
				else {print "<tr><td>Der er sket en fejl ved bogf&oslash;ring af bilag: '$bilag', debetkonto: $debet!</td></tr>";}
			}
			if ($kredit>0) {
				$totalamount=$totalamount-$k_amount;
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id, afd, ansat, projekt, valuta, valutakurs, ordre_id)values('$kredit', '$bilag', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$k_amount', '$faktura', '$kladde_id', '$afd', '$ansat', '$projekt', '$valuta', '$valutakurs', '$ordre_id')");
				$query = db_select("select * from transaktioner where kontonr='$kredit' and bilag=$bilag and transdate='$transdate' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse' and kredit='$k_amount' and faktura='$faktura' and kladde_id=$kladde_id and afd=$afd and projekt = '$projekt'");
				if ( db_fetch_array($query)) {
					$transtjek++;
					$query = db_select("select id, saldo from kontoplan where kontonr='$kredit' and regnskabsaar=$regnaar");
					$row= db_fetch_array($query);
					$kasklid[$transtjek]=$row['id'];
					$kasklmonth[$transtjek]=$row['saldo'];
					$transamount[$transtjek]=$k_amount*-1;
				}
				else {print "<tr><td>Der er sket en fejl ved bogfring af bilag: '$bilag', kreditkonto: $kredit!</td></tr>"; }
			}
			
			if ($d_momskto>0) {
				$d_moms=round($d_moms,2);
				$totalamount=$totalamount+$d_moms;
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id, afd, ansat, projekt, valuta, valutakurs, ordre_id)values($d_momskto, '$bilag', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$d_moms', '$faktura', '$kladde_id', '$afd', '$ansat', '$projekt', '$valuta', '$valutakurs', '$ordre_id')");
				$query = db_select("select * from transaktioner where kontonr=$d_momskto and bilag=$bilag and transdate='$transdate' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse' and debet='$d_moms' and faktura='$faktura' and kladde_id=$kladde_id and afd=$afd and projekt = '$projekt'");
				if ( db_fetch_array($query)) {
					$transtjek++;
					 $query = db_select("select id, saldo from kontoplan where kontonr='$d_momskto' and regnskabsaar=$regnaar");
					 $row= db_fetch_array($query);
					 $kasklid[$transtjek]=$row['id'];
					 $kasklmonth[$transtjek]=$row['saldo'];
					 $transamount[$transtjek]=$d_moms;
				 }
			 	else {print "<tr><td>Der er sket en fejl ved bogf&oslash;ring af bilag: '$bilag', debetkonto: $d_momskto!</td></tr>";}
			}
			if ($k_momskto>0) {
				$k_moms=round($k_moms,2);
				$totalamount=$totalamount-$k_moms;
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id, afd, ansat, projekt, valuta, valutakurs, ordre_id)values('$k_momskto', '$bilag', '$transdate', '$logdate', '$logtime', '$beskrivelse', '$k_moms', '$faktura', '$kladde_id', '$afd', '$ansat', '$projekt', '$valuta', '$valutakurs', '$ordre_id')");
				$query = db_select("select * from transaktioner where kontonr=$k_momskto and bilag=$bilag and transdate='$transdate' and logdate='$logdate' and logtime='$logtime' and beskrivelse='$beskrivelse' and kredit='$k_moms' and faktura='$faktura' and kladde_id=$kladde_id and afd=$afd and projekt = '$projekt'");
				if ( db_fetch_array($query)){
					$transtjek++;
					$query = db_select("select id, saldo from kontoplan where kontonr='$k_momskto' and regnskabsaar=$regnaar");
					$row= db_fetch_array($query);
					$kasklid[$transtjek]=$row['id'];
					$kasklmonth[$transtjek]=$row['saldo'];
					$transamount[$transtjek]=$k_moms*-1;
				}
				else {print "<tr><td>Der er sket en fejl ved bogf&oslash;ring af bilag: '$bilag', kreditkonto: $k_momskto!</td></tr>";}
			}
		}
		if ($transtjek==$transantal&&$totalamount==0){
			$dato=date("Y-m-d");
			 db_modify("update kladdeliste set bogfort = 'V', bogforingsdate = '$dato', bogfort_af = '$brugernavn' where id = '$kladde_id'");
			for ($x=1; $x<=$transtjek; $x++) {
				$query = db_select("select saldo from kontoplan where id='$kasklid[$x]'");
				$row= db_fetch_array($query);
				$temp=$row['saldo'];
				if (!$temp) {$temp=0;}
				$transamount[$x]=($temp+$transamount[$x]);
				 db_modify("update kontoplan set saldo = $transamount[$x] where id = '$kasklid[$x]'");
			}
		}
		else {
			print "<BODY onLoad=\"javascript:alert('(402) Der er konstateret en afvigelse, kontakt venligst hotline - tlf. 4690 2208')\">";
			exit;
		}
	}
}
######################################################################################################################################
function openpost($art, $debet, $bilag, $faktura, $amount, $beskrivelse, $transdate){
	global $connection;
	global $regnaar;
	global $kladde_id;

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
			db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, refnr, beskrivelse, udlignet, transdate, kladde_id, udlign_date, udlign_id)values('$konto_id', '$debet', '$faktura', '$amount', '$bilag', '$beskrivelse', '1', '$transdate', '$kladde_id',  '$transdate', '$udlign_id')");
			$udlignet=1;
		}
	}
	if ($udlignet<1)	{
echo "insert into openpost (konto_id, konto_nr, faktnr, amount, refnr, beskrivelse, udlignet, transdate, kladde_id)values('$konto_id', '$debet', '$faktura', '$amount', '$bilag', '$beskrivelse', '0', '$transdate', '$kladde_id')<br>";
		db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, refnr, beskrivelse, udlignet, transdate, kladde_id)values('$konto_id', '$debet', '$faktura', '$amount', '$bilag', '$beskrivelse', '0', '$transdate', '$kladde_id')");
	}
}
######################################################################################################################################
function momsberegning($konto, $amount, $momsart, $kontrol) {
	global $connection;
	global $regnaar;

	$momskto=0; $moms=0;
	if (!isset($amount)) $amount=null; 
	if (!isset($momsart[0]))$momsart[0]=null;
	if (!isset($momsart[1]))$momsart[1]=null;
	if (!isset($kontrol[0]))$kontrol[0]=null;

	$a=$momsart[0]; #Frste tegn i strengen
	$b=$momsart[1]; #Andet tegn i strengen
	$modkto=NULL;
	
	$r=db_fetch_array(db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'"));
	if (trim($r['moms'])) {
		if (($a=='E') && ($b)) {
			$c=$a.'M';
			$query = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
			if($row =	db_fetch_array($query)) { # Sï¿½er der moms pï¿½kontoen
#				$q2 = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
				$x=usdecimal($row['box2']);
				$moms=$amount/100*$x;
				$momskto=trim($row['box1']);
				$modkto=trim($row['box3']);
			}
		}
		else {	
			$query = db_select("select moms from kontoplan where kontonr='$konto' and regnskabsaar='$regnaar'");
			if($row =	db_fetch_array($query)){
				$a=substr($row['moms'],0,1);
				$b=substr($row['moms'],1);
			}
			$c=$a.'M';
			$query = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
			if($row =	db_fetch_array($query)) { # Sï¿½er der moms pï¿½kontoen
#				$q2 = db_select("select box1, box2, box3 from grupper where kode='$a' and kodenr='$b' and art='$c'");
				$x=usdecimal($row['box2']);
				if ($a=='E'){
					$moms=$amount/100*$x;
					$momskto=trim($row['box3']);
					$modkto=trim($row['box1']);
				}
				elseif ($kontrol[0]=='E'){
					$momskto=trim($row['box1']);
					$moms=$amount/100*$x;
				}
				else {
					$momskto=trim($row['box1']);
					$moms=$amount-($amount/((100+$x)/100));
					$amount=$amount-$moms;
				}
			}
		}
	}
	$svar=array($amount, $moms, $momskto, $modkto);
	return $svar;
}
######################################################################################################################################
function gruppeopslag($type, $konto)
{
	global $connection;
	
	$art=NULL;
	$momsart=NULL;

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
?>
