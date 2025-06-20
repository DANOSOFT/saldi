<?php
// ----------debitor/ret_genfakt.php----------lap 3.2.7-----2012-02-05-------
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

print "<script>
	function fokuser(that, fgcolor, bgcolor){
	that.style.color = fgcolor;
	that.style.backgroundColor = bgcolor;
	document.forms[0].fokus.value=that.name; }
	function defokuser(that, fgcolor, bgcolor){
	that.style.color = fgcolor;
	that.style.backgroundColor = bgcolor;}
</script>";

@session_start();
$s_id=session_id();
$title="Ret abonnementsordrer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");
include("../includes/var2str.php");

$incl_moms='';
$art='DO';
$gl_dato=NULL;$ny_dato=NULL;
$gl_genfakt=NULL;$ny_genfakt=NULL;


$returside=if_isset($_GET['returside']);
if ($popup) $returside="../includes/luk.php";
elseif (!$returside) $returside="ordreliste.php";

print "<table name=\"tabel_1\" width=\"100%\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1 ->
print "<tr><td width=\"100%\"><table name=\"tabel_1.1\" width=\"100%\" cellspacing=\"2\"  border=\"0\"><tbody>\n"; # tabel 1.1 ->
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>\n";
print "<td width=80% $top_bund>$title</td>\n";
print "<td width=10% $top_bund><br></td>\n";
print "</tbody></table name=\"tabel_1.1\"></td></tr>\n"; # <- tabel 1.1

$ordreliste=array();
if ($_GET['ordreliste']) {
	$ordreliste=explode(",",$_GET['ordreliste']);
	$ordreantal=sizeof($ordreliste);
} elseif ($_POST['ordreliste']) {
	$submit=$_POST['submit'];
	$fokus=$_POST['fokus'];
	$id[$x]=$_POST['id'];
	$firmanavn=$_POST['firmanavn'];
	$fakturadato=$_POST['fakturadato'];
	$genfakt=$_POST['genfakt'];
	$udskriv_til=$_POST['udskriv_til'];
	$email=$_POST['email'];
	$ordreliste=$_POST['ordreliste'];
	$projekt=$_POST['projekt'];
	$ordreantal=sizeof($ordreliste);

#	$posnr=$_POST['posnr'];
	$linjeantal=$_POST['linjeantal'];
	$ordre_id=$_POST['ordre_id'];
	$linje_id=$_POST['linje_id'];
#	$varenr=$_POST['varenr'];
#	$dkantal=$_POST['dkantal'];
#	$beskrivelse=$_POST['beskrivelse'];
#	$dkpris=$_POST['dkpris'];
#	$dkrabat=$_POST['dkrabat'];
#	$kdo=$_POST['kdo'];
#	$linjeantal=sizeof($posnr)+sizeof($ordreliste);
	$linjeantal=sizeof($posnr)+$linjeantal;

	for ($x=0 ; $x<=$linjeantal ; $x++) {
		$posnr[$x]=$_POST['posnr_'.$x];
		$varenr[$x]=$_POST['varenr_'.$x];
		$dkantal[$x]=$_POST['dkantal_'.$x];
		$beskrivelse[$x]=$_POST['beskrivelse_'.$x];
		$dkpris[$x]=$_POST['dkpris_'.$x];
		$dkrabat[$x]=$_POST['dkrabat_'.$x];
		$kdo[$x]=$_POST['kdo_'.$x];
	}

	for ($x=0 ; $x<=$ordreantal ; $x++) {
		if ($ordreliste[$x]) {
			$firmanavn[$x]=$_POST['firmanavn_'.$x];
			$fakturadato[$x]=$_POST['fakturadato_'.$x];
			if (substr($fakturadato[$x],-2)=="*=") {
				$r=db_fetch_array(db_select("select fakturadate from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__));
				$gl_dato=dkdato($r['fakturadate']);
				$fakturadato[$x]=(str_replace("*=","",$fakturadato[$x]));
				$ny_dato=dkdato(usdate($fakturadato[$x]));
			} elseif ($gl_dato && $ny_dato && $fakturadato[$x]==$gl_dato) {
				$fakturadato[$x]=$ny_dato;
			}
			$genfakt[$x]=$_POST['genfakt_'.$x];
			if (substr($genfakt[$x],-2)=="*=") {
				$r=db_fetch_array(db_select("select nextfakt from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__));
				$gl_genfakt=dkdato($r['nextfakt']);
				$genfakt[$x]=(str_replace("*=","",$genfakt[$x]));
				$ny_genfakt=dkdato(usdate($genfakt[$x]));
			} elseif ($gl_genfakt && $ny_genfakt && $genfakt[$x]==$gl_genfakt) {
				$genfakt[$x]=$ny_genfakt;
			}
			$udskriv_til[$x]=$_POST['udskriv_til_'.$x];
			$email[$x]=$_POST['email_'.$x];
			$betalingsbet[$x]=$_POST['betalingsbet_'.$x];
			$betalingsdage[$x]=$_POST['betalingsdage_'.$x]*1;
			if (substr($betalingsdage[$x],-2)=="*=") {
				$r=db_fetch_array(db_select("select betalingsbet,betalingsdage from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__));
				$gl_betalingsbet=$r['betalingsbet'];
				$gl_betalingsdage=$r['betalingsdage'];
				$betalingsdage[$x]=(str_replace("*=","",$betalingsdage[$x]));
				$ny_betalingsbet=$betalingsbet[$x];
				$ny_betalingsdage=$betalingsdage[$x];
			} elseif ($gl_betalingsbet && $ny_betalingsbet && $betalingsbet[$x]==$gl_betalingsbet && $betalingsdage[$x]==$gl_betalingsdage) {
				$betalingsbet[$x]=$ny_betalingsbet;
				$betalingsdage[$x]=$ny_betalingsdage;
			}
			if (!$betalingsbet[$x]) $betalingsbet[$x]='Netto';
			$firmanavn[$x]=addslashes($firmanavn[$x]);
			$email[$x]=addslashes($email[$x]);
			$fakturadate=usdate($fakturadato[$x]);
			$nextfakt=usdate($genfakt[$x]);
			($udskriv_til[$x]=='email')?$mail_fakt='on':$mail_fakt='';
			($udskriv_til[$x]=='PBS_BS' || $udskriv_til[$x]=='PBS_FI')?$pbs='on':$pbs='';
#echo "update ordrer set firmanavn='$firmanavn[$x]',fakturadate='$fakturadate',nextfakt='$nextfakt',email='$email[$x]',udskriv_til='$udskriv_til[$x]',mail_fakt='$mail_fakt',projekt='$projekt[$x]' where id='$ordreliste[$x]'<br>";
			db_modify("update ordrer set firmanavn='$firmanavn[$x]',fakturadate='$fakturadate',nextfakt='$nextfakt',email='$email[$x]',udskriv_til='$udskriv_til[$x]',mail_fakt='$mail_fakt',projekt='$projekt[$x]',betalingsbet='$betalingsbet[$x]',betalingsdage='$betalingsdage[$x]' where id='$ordreliste[$x]'",__FILE__ . " linje " . __LINE__);
		}
	}

	for ($x=1 ; $x<=$linjeantal ; $x++) {
		if ($posnr[$x]=='-') {
			if ($linje_id[$x]) db_modify("delete from ordrelinjer where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
		} else {
#			if ($ordreliste[$x])
			$posnr[$x]=$posnr[$x]*1;
			if ($dkantal[$x]=='') $dkantal[$x]=1; 
			$antal=usdecimal($dkantal[$x]);
			$pris=usdecimal($dkpris[$x]);
			$rabat=usdecimal($dkrabat[$x]);
			$beskrivelse[$x]=trim(addslashes($beskrivelse[$x]));
			if (!$projekt[$x]) $projekt[$x]='';
# if ($linje_id[$x]) echo "update ordrelinjer set posnr='$posnr[$x]',antal='$antal',beskrivelse='$beskrivelse[$x]',pris='$pris',rabat='$rabat',kdo='$kdo[$x]',projekt='$projekt[$x]' where id='$linje_id[$x]'<br>";
			if ($linje_id[$x]) db_modify("update ordrelinjer set posnr='$posnr[$x]',antal='$antal',beskrivelse='$beskrivelse[$x]',pris='$pris',rabat='$rabat',kdo='$kdo[$x]',projekt='$projekt[$x]' where id='$linje_id[$x]'",__FILE__ . " linje " . __LINE__);
			elseif ($varenr[$x]) {
				opret_ordrelinje($ordre_id[$x],$varenr[$x],$antal,$beskrivelse[$x],$pris,$rabat[$x],$art,$momsfri,$posnr[$x],$linje_id[$x],$incl_moms,$kdo[$x]);
#				indset_varenr($varenr[$x],$posnr[$x],$antal,$beskrivelse[$x],$pris,$rabat,$ordre_id[$x]);
			} elseif ($beskrivelse[$x])  {
				db_modify("insert into ordrelinjer (posnr,antal,beskrivelse,pris,rabat,ordre_id,kdo) values ('$posnr[$x]','0','$beskrivelse[$x]','0','0','$ordre_id[$x]','$kdo[$x]')",__FILE__ . " linje " . __LINE__);
			}
		}   
	}
	if ($submit=='Fakturer') {
		$y=0;
		$udskriv=NULL;
		for ($x=0 ; $x<=$ordreantal ; $x++) {
			if ($ordreliste[$x]) {
				$y++;
				levering($ordreliste[$x],'on','on');
				$svar=bogfor($ordreliste[$x],'on','on');
				if ($svar=='OK') {
					if ($pbs) pbsfakt($ordreliste[$x]);
					elseif ($udskriv) $udskriv.=",$ordreliste[$x]";
					else $udskriv="$ordreliste[$x]";
				} elseif ($ordreantal) {
					if (strpos($svar,'invoicedate prior to')) $tekst="Genfaktureringsdato før fakturadato";
					else $tekst="Der er konstateret en ubalance i posteringssummen,\\nkontakt venligst Danosoft på tlf. +45 46902208";
					print "<BODY onLoad=\"javascript:alert('$tekst')\">\n";
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$returside\">";
					exit;
				}
			}
		}
		if ($udskriv) {
			print "<BODY onLoad=\"JavaScript:window.open('formularprint.php?id=-1&ordre_antal=$y&skriv=$udskriv&formular=4' , '' , ',statusbar=no,menubar=no,titlebar=no,toolbar=no,scrollbars=yes, location=1');\">";
			exit;
		}
	}
}
##########################################################################################################################################
$r=db_fetch_array(db_select("select art,pbs_nr,pbs from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
$lev_pbs_nr=$r['pbs_nr'];
$lev_pbs=$r['pbs'];

$y=-1;
for ($x=0;$x<$ordreantal;$x++) {
		if ($r=db_fetch_array(db_select("select * from ordrer where id = '$ordreliste[$x]' and status<'3'",__FILE__ . " linje " . __LINE__))) {
		$y++;
		$id[$y]=$r['id']*1;
		$konto_id[$y]=$r['konto_id'];
		$kontonr[$y]=$r['kontonr'];
		$firmanavn[$y]=stripslashes(htmlentities(trim($r['firmanavn']),ENT_COMPAT,$charset));
		$fakturadato[$y]=dkdato($r['fakturadate']);
		$genfakt[$y]=dkdato($r['nextfakt']);
		$betalingsbet[$y]=$r['betalingsbet'];
		$betalingsdage[$y]=$r['betalingsdage'];
		$udskriv_til[$y]=$r['udskriv_til'];
		$email[$y]=$r['email'];
		$projekt[$y]=$r['projekt'];
		if (!$email[$y] && $udskriv_til[$y]=='email') $udskriv_til[$y]='PDF';
		$r=db_fetch_array(db_select("select pbs_nr,pbs from adresser where id = '$konto_id[$y]'",__FILE__ . " linje " . __LINE__));
		$pbs[$y]=$r['pbs'];
		$pbs_nr[$y]=$r['pbs_nr'];
	}
} $ordreantal=$y;

##########################################################################################################################################
print "<form name=\"ret_genfakt\" action=\"ret_genfakt.php?returside=$returside\" method=\"post\">\n";
$onfocus="onfocus=\"fokuser(this,'#000000','#EFEFEF');\" onblur=\"defokuser(this,'#000000','#FFFFFF');\"";
print "<tr><td align=\"center\" width=\"100%\"><table border=\"1\"><tbody><tr><td>";
for ($x=0 ; $x<=$ordreantal ; $x++) {
	print "<input type=\"hidden\" name=\"ordreliste[$x]\" value=\"$id[$x]\">";
	print "<input type=\"hidden\" name=\"projekt[$x]\" value=\"$projekt[$x]\">";
	print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"1\"><tbody>";
	print "<tr>";
	print "<td align=\"center\">Kontonr</td>";
	print "<td align=\"center\">Firmanavn</td>";
	print "<td align=\"center\">Fakturadato</td>";
	print "<td align=\"center\">Genfakt</td>";
	print "<td align=\"center\">Betalingsbet.</td>";
	print "<td align=\"center\">Email</td>";
	print "<td align=\"center\">udskriv_til</td>";
	print "</tr><tr>";
	print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:left;width:100px\" value=\"$kontonr[$x]\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:300px\" name=\"firmanavn_$x\" value=\"$firmanavn[$x]\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:90px\" name=\"fakturadato_$x\" value=\"$fakturadato[$x]\"></td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:90px\" name=\"genfakt_$x\" value=\"$genfakt[$x]\"></td>";
	print "<td><select class=\"inputbox\" $onfocus style=\"text-align:left;width:70px\" name=\"betalingsbet_$x\">";

	if (!$betalingsbet) $betalingsbet="Netto";
	print "<option>$betalingsbet[$x]</option>\n";
	if ($betalingsbet[$x]!='Forud') 	{print "<option>Forud</option>\n";}
	if ($betalingsbet[$x]!='Kontant')	{print "<option>Kontant</option>\n";}
	if ($betalingsbet[$x]!='Efterkrav')	{print "<option>Efterkrav</option>\n";}
	if ($betalingsbet[$x]!='Netto'){print "<option>Netto</option>\n";}
	if ($betalingsbet[$x]!='Lb. md.'){print "<option>Lb. md.</option>\n";}
	if (($betalingsbet[$x]=='Kontant')||($betalingsbet[$x]=='Efterkrav')||($betalingsbet[$x]=='Forud')) {$betalingsdage[$x]='';}
	elseif (!$betalingsdage[$x]) {$betalingsdage[$x]='Nul';}
	if ($betalingsdage[$x])	{
		if ($betalingsdage[$x]=='Nul') {$betalingsdage[$x]=0;}
		print "</SELECT>+<input class=\"inputbox\" $onfocus type=\"text\" size=\"1\" style=\"text-align:right\" name=\"betalingsdage_$x\" value=\"$betalingsdage[$x]\"></td>\n";
	}
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:center;width:90px\" name=\"email_$x\" value=\"$email[$x]\"></td>";
	print "<td><select class=\"inputbox\" $onfocus style=\"text-align:left;width:70px\" name=\"udskriv_til_$x\">";
	if ($udskriv_til[$x]) print "<option>$udskriv_til[$x]</option>\n";
	if ($udskriv_til[$x]!="PDF") print "<option>PDF</option>\n";
	if ($udskriv_til[$x]!="email" && $email) print "<option>email</option>\n";
	if ($lev_pbs_nr) {
		if ($udskriv_til[$x]!="PBS_FI" && $lev_pbs!='B') print "<option>PBS_FI</option>\n";
		$tmp=$pbs_nr[$x]*1;
		if ($tmp && $udskriv_til[$x]!="PBS_BS") print "<option title=\"Opkr&aelig;ves via PBS betalingsservice\">PBS_BS</option>\n";
	}
	if ($udskriv_til[$x]!="oioxml" && strlen($ean)==13) print "<option title=\"Kun ved fakturering/kreditering.\">oioxml</option>\n";
	print "</select</td>";
	print "</tbody></table></td></tr>";
	print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\"><tbody>";
	print "<tr>";
	print "<td align=\"center\">Pos<td>";
	print "<td align=\"center\">Varenr<td>";
	print "<td align=\"center\">Antal<td>";
	print "<td align=\"center\">Beskrivelse<td>";
	print "<td align=\"center\">Pris<td>";
	print "<td align=\"center\">Rabat<td>";
	print "<td align=\"center\">I alt<td>";
	print "<td align=\"center\" title=\"Kun denne ordre! Hvis dette felt er afm&aelig;rket medtages linjen ikke ved n&aelig;ste genfakturering\">kdo<td>";
	print "</tr><tr>";
	$posnr=0;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id[$x]' order by posnr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$y++;
		$posnr++;
		$linje_id[$y]=$r['id'];
		$varenr[$y]=$r['varenr'];
		$dkantal[$y]=dkdecimal($r['antal']);
		$beskrivelse[$y]=stripslashes(htmlentities(trim($r['beskrivelse']),ENT_COMPAT,$charset));
		$dkpris[$y]=dkdecimal($r['pris']);
		$dkrabat[$y]=dkdecimal($r['rabat']);
		$projekt[$y]=$r['projekt'];
		if ($r['kdo']) $kdo[$y]='checked';
		else $kdo[$y]='';

		$linjesum=dkdecimal($r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100))	;
		print "<input type=\"hidden\" name=\"linje_id[$y]\" value=\"$linje_id[$y]\">";
		print "<input type=\"hidden\" name=\"ordre_id[$y]\" value=\"$id[$x]\">";
		print "<input type=\"hidden\" name=\"projekt[$y]\" value=\"$projekt[$y]\">";
		print "<tr>";
		print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:30px\" name=\"posnr_$y\" value=\"$posnr\"><td>";
		if ($varenr[$y]) {
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:80px\" name=\"varenr_$y\" value=\"$varenr[$y]\"><td>";
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkantal_$y\" value=\"$dkantal[$y]\"><td>";
		} else {
			print "<td><input class=\"inputbox\" $onfocus readonly=\"readonly\" style=\"width:80px\"><td>";
			print "<td><input class=\"inputbox\" $onfocus readonly=\"readonly\" style=\"width:50px\"><td>";
		}
		$title=var2str($beskrivelse[$y],$id[$x]);
		print "<td title=\"$title\"><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:600px\" name=\"beskrivelse_$y\" value='$beskrivelse[$y]'><td>";
		if ($varenr[$y]) {
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:80px\" name=\"dkpris_$y\" value=\"$dkpris[$y]\"><td>";
			print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkrabat_$y\" value=\"$dkrabat[$y]\"><td>";
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right;width:80px\" value=\"$linjesum\"><td>";
		} else {
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"width:80px\"><td>";
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"width:50px\"><td>";
			print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"width:80px\"><td>";
		}
 		print "<td title=\"Hvis dette felt er afm&aelig;rket medtages linjen ikke ved n&aelig;ste genfakturering\" align = \"center\">
			<input class=\"inputbox\" type=\"checkbox\" $onfocus name=\"kdo_$y\" $kdo[$y]><td>";
		print "</tr>";
	}
	$y++;
	$posnr++;
	print "<input type=\"hidden\" name=\"ordre_id[$y]\" value=\"$id[$x]\">";
	print "<tr>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:30px\" name=\"posnr_$y\" value=\"$posnr\"><td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:80px\" name=\"varenr_$y\"><td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkantal_$y\"><td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:left;width:600px\" name=\"beskrivelse_$y\"><td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:80px\" name=\"dkpris_$y\"><td>";
	print "<td><input class=\"inputbox\" $onfocus type=\"text\" style=\"text-align:right;width:50px\" name=\"dkrabat_$y\"><td>";
	print "<td><input class=\"inputbox\" readonly=\"readonly\" style=\"text-align:right;width:80px\"><td>";
	print "<td title=\"Hvis dette felt er afm&aelig;rket medtages linjen ikke ved n&aelig;ste genfakturering\" align = \"center\">
		<input class=\"inputbox\" $onfocus type=\"checkbox\" name=\"kdo_$y\" checked><td>";
	print "</tr>";
	print "</tbody></table></td></tr>";
}
print "<input type=\"hidden\" name=\"fokus\" id=\"fokus\">";
print "<input type=\"hidden\" name=\"linjeantal\" value=\"$y\">";
print "<td align=\"center\" width=$width><input type=\"submit\" style=\"width:80px\" accesskey=\"g\" value=\"Gem\" name=\"submit\" onclick=\"javascript:docChange = false;\">\n";
print "&nbsp;<input type=\"submit\" style=\"width:80px\" accesskey=\"f\" value=\"Fakturer\" name=\"submit\" onclick=\"javascript:docChange = false;\"></td>\n";
print "</tbody></table></td></tr>";
print "</tbody></table>";

print "<script language=\"javascript\">";
print "document.ret_genfakt.$fokus.focus()";;
print "</script>";
?>
