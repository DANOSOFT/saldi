<?php

// -------- api/kortbetaling.php----------lap 3.5.8 ----- 2015.08.30----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
//	2014.03.15 Indsat ,100 (procent) før ,'DO' i opret_ordrelinje grundet ændring af funktion
//	2014.04.26 Indsat ,$vare_id før ,$r['varenr'] i opret_ordrelinje grundet ændring af funktion
//	2015.08.30 Konto oprettes kun hvis den ikke allerede eksisterer. Søg efter $konto_id

@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/std_func.php");
include("bet_func.php");

$regnskab=if_isset($_GET['regnskab']);
$metode=if_isset($_GET['metode']);
if (!$metode) $metode='kort';
$valg=if_isset($_GET['valg']);
if (!$valg) $valg='donation';
$fakturanr=if_isset($_GET['fakturanr']);

#$brugernavn=if_isset($_POST['brugernavn']);
#$password=if_isset($_POST['password']);

$svar=logon($s_id,$regnskab,$brugernavn,$password,$sqhost,$squser,$sqpass,$sqdb);
#cho "Svar: ".$svar."<br>";
if ($metode=='PBS') $title='PBS tilmelding';
else $title='kortbetaling';

print "<html><head>
					<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
					<title>$title</title>
				</head>";
$css="../css/standard.css";
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";
#print "<a href=\"cookietest.php\">cookietest</a>"; 

if ($tilmeld=(if_isset($_POST['tilmeld']))) {
	include("../includes/ordrefunc.php");

	$belob=db_escape_string(trim(if_isset($_POST['belob'])));
	$vare_id=db_escape_string(trim(if_isset($_POST['vare_id'])));
	$interval=db_escape_string(trim(if_isset($_POST['interval'])));
	$bank_navn=db_escape_string(trim(if_isset($_POST['bank_navn'])));
	$bank_reg=db_escape_string(trim(if_isset($_POST['bank_reg'])));
	$bank_konto=db_escape_string(trim(if_isset($_POST['bank_konto'])));
	$kontakt=db_escape_string(trim(if_isset($_POST['kontakt'])));
	$cvrnr=db_escape_string(trim(if_isset($_POST['cvrnr'])));
	$firmanavn=db_escape_string(trim(if_isset($_POST['firmanavn'])));
	$addr1=db_escape_string(trim(if_isset($_POST['addr1'])));
	$addr2=db_escape_string(trim(if_isset($_POST['addr2'])));
	$postnr=db_escape_string(trim(if_isset($_POST['postnr'])));
	$bynavn=db_escape_string(trim(if_isset($_POST['bynavn'])));
	$email=db_escape_string(trim(if_isset($_POST['email'])));
	$tlf=db_escape_string(trim(if_isset($_POST['tlf'])));

	$alert=tjek($metode,$belob,$bank_navn,$bank_reg,$bank_konto,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf);
	if ($alert=='OK') {
		$konto_id=NULL;
		if ($cvrnr) {
			if ($r=db_fetch_array(db_select("select * from adresser where art='D' and cvrnr ='$cvrnr'",__FILE__ . " linje " . __LINE__))) {
				$konto_id=$r['id'];
				$kontonr=$r['kontonr'];
			} elseif ($r=db_fetch_array(db_select("select * from adresser where art='D' and tlf='$tlf' and addr1='$addr1' and postnr='$postnr'",__FILE__ . " linje " . __LINE__))) {
				$konto_id=$r['id'];
				$kontonr=$r['kontonr'];
			} else {
				$kontonr=1000;
				$x=0;
				$ktonr=array();
				$q=db_select("select * from adresser where art='D' and kontonr >='1000' order by kontonr",__FILE__ . " linje " . __LINE__);
				while($r=db_fetch_array($q)) {
					$ktonr[$x]=$r['kontonr'];
					$x++;
				}
				while (in_array($kontonr,$ktonr)) $kontonr++;
			}
			$gruppe=1;
			if (!$firmanavn) {
				$firmanavn=$kontakt;
				$kontakt=NULL;
				$kontotype='privat';
			} else {
				$kontotype='erhverv';
			}
			$art='D';
		 	($metode=='PBS')?$pbs='on':$pbs='';
			if ($konto_id) {
				$qtxt="update adresser set firmanavn='$firmanavn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',email='$email',";
				$qtxt.="cvrnr='$cvrnr',tlf='$tlf',kontakt='$kontakt',gruppe='$gruppe',kontotype='$kontotype',art='$art' where id='$konto_id'"; 
			} else {
				$qtxt="insert into adresser (kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,cvrnr,tlf,kontakt,gruppe,kontotype,art,bank_navn,";
				$qtxt.="bank_reg,bank_konto,pbs,pbs_nr) values ('$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$email','$cvrnr',";
				$qtxt.="'$tlf','$kontakt','$gruppe','$kontotype','$art','$bank_navn','$bank_reg','$bank_konto','$pbs','')";
			}
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			if ($konto_id && $bank_navn && $bank_reg=$bank_reg && $bank_konto) {
				db_modify("update adresser set bank_navn='$bank_navn',bank_reg='$bank_reg',bank_konto='$bank_konto' where id='$konto_id'",__FILE__ . " linje " . __LINE__);
			}
			if (!$konto_id) {
				$qtxt="select id from adresser where kontonr='$kontonr' and art = 'D'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$konto_id=$r['id'];
			}
			if ($konto_id && $kontakt) {
				if (!$r=db_fetch_array(db_select("select id from ansatte where konto_id='$konto_id' and navn ='$kontakt'",__FILE__ . " linje " . __LINE__))) {
					$qtxt="insert into ansatte(konto_id, navn) values ('$konto_id', '$kontakt')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			$qtxt="select max(ordrenr) as ordrenr from ordrer where art='DO'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$ordrenr=$r['ordrenr']+1;
			$ordredate=date("Y-m-d");
			$status=0;
			$art='DO';
			if ($metode=='PBS') $udskriv_til="PBS";
			else $udskriv_til="email";
			if ($ordre_id) $qtxt="update ordrer set konto_id='$konto_id',kontonr='$kontonr',ordrenr=$ordrenr,firmanavn=$firmanavn,addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',cvrnr='$cvrnr',email='$email',kontakt='$kontakt',art='$art',status='$status',udskriv_til='$udskriv_til',ordredate='$ordredate')";
			else $qtxt="insert into ordrer(konto_id,kontonr,ordrenr,firmanavn,addr1,addr2,postnr,bynavn,cvrnr,email,kontakt,art,status,udskriv_til,ordredate) values ('$konto_id','$kontonr','$ordrenr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$cvrnr','$email','$kontakt','$art','$status','$udskriv_til','$ordredate')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
			$r=db_fetch_array(db_select("select max(id) as id from ordrer where konto_id='$konto_id' and art = '$art'",__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
			if ($metode=='PBS') $txt="Tilmeldt PBS, betalingsinterval: $interval, beløb: $belob";
			else $txt="Oprettet til kortbetaling - betaling ikke gennemført";
			$txt=db_escape_string($txt);
			$korttxt=$txt;
			$qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr,vare_id) values ('$ordre_id','$txt','1','0')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
			#cho "vare_id $vare_id<br>";
			if ($vare_id) {
				$amount=usdecimal($belob);
				$qtxt="select * from varer where id = '$vare_id'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				opret_ordrelinje($ordre_id,$vare_id,$r['varenr'],1,$r['beskrivelse'],$amount,'0',100,'DO',$r['momsfri'],'2','0','0','','','');
			}
		} 
		if ($metode=='PBS') {
			$txt="Tak for din tilmelding";
			print "<BODY onLoad=\"javascript:alert('$txt')\">";
		} else {
			kortbetaling($regnskab,$ordrenr,$ordre_id,$amount,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf,$korttxt);
		}
	} else {
		print "<BODY onLoad=\"javascript:alert('$alert')\">";
		$alert=NULL;
	}
}
if (!$alert) {
	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$eget_firmanavn=$r['firmanavn'];
	
	$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
	$merchant_id=trim($r['box4']);$md5secret=trim($r['box5']);

	if ($ordre_id=if_isset($_GET['ordre_id'])) {
		$r = db_fetch_array(db_select("select * from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id'];
		$kontonr=$r['kontonr'];
		$kontakt=$r['kontakt'];
		$cvrnr=$r['cvrnr'];
		$firmanavn=$r['firmanavn'];
		$addr1=$r['addr1'];
		$addr2=$r['addr2'];
		$postnr=$r['postnr'];
		$bynavn=$r['bynavn'];
		$email=$r['email'];
		$r = db_fetch_array(db_select("select tlf from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__));
		$tlf=$r['tlf'];
		$r = db_fetch_array(db_select("select pris from ordrelinjer where ordre_id = '$ordre_id' and pris>'0'",__FILE__ . " linje " . __LINE__));
		$belob=dkdecimal($r['pris']);
	}
	
	$x=0;
	$qtxt="select id,beskrivelse from varer where publiceret='on' and lukket !='on' order by beskrivelse";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$vare_id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}
	print "<table><tbody>";
	print "<form name=\"kortbetaling\" action=\"kortbetaling.php?regnskab=$regnskab\" method=\"post\">";
	print "<tr><td colspan=\"2\"><big><b>Udfyld personlige oplysninger</b></big></td></tr>\n";
	if ($metode=='PBS') print "<tr><td colspan=\"2\"><b>Tilmelding til fast støtte via betalingsservice</b></td></tr>\n";
	else print "<tr><td colspan=\"2\"><b>Tilmelding til støtte en gang</b></td></tr>\n";
	print "<tr><td style=\"width:220px\">Beløb: *</td><td align=\"left\"><input style=\"width:100px\" type=\"text\" name=\"belob\" value=\"$belob\"><font color=\"gray\">Kr. (min. 50 kr.)</font></td></tr>\n";
	if ($metode != 'kort') {
		($interval=='maaned')?$maaned='checked':$maaned=NULL;
		($interval=='kvartal')?$kvartal='checked':$kvartal=NULL;
		($interval=='aar')?$aar='checked':$aar=NULL;
		if (!$interval) $kvartal='checked';
		print "<tr><td>Hvor tit vil du støtte: *</td><td align=\"right\">
			<input type=\"radio\" name=\"interval\" value=\"maaned\" $maaned>maaned
			<input type=\"radio\" name=\"interval\" value=\"kvartal\" $kvartal>kvartal
			<input type=\"radio\" name=\"interval\" value=\"aar\" $aar>aar
			</td></tr>\n";
	}
	print "<tr><td>Hvilket projekt vil du støtte</td>";
	print "<td align=\"right\"><select  style=\"width:200px\" name=\"vare_id\">";
	for ($x=0;$x<count($vare_id);$x++) {
		print "<option value=\"$vare_id[$x]\">$beskrivelse[$x]</option>";
	}
	print "</select>";
	if ($metode != 'kort') {
		print "<tr><td colspan=\"2\"><b>Bankoplysninger</b></td></tr>\n";
		print "<tr><td>Pengeinstitut: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bank_navn\" value=\"$bank_navn\"></td></tr>\n";
		print "<tr><td>Reg. nr.: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bank_reg\" value=\"$bank_reg\"></td></tr>\n";
		print "<tr><td>Konto nr.: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bank_konto\" value=\"$bank_konto\"></td></tr>\n";
	}
	print "<tr><td colspan=\"2\"><b>Person -/firmaoplysninger</b></td></tr>\n";
	print "<tr><td>Fulde navn: (Kontakt v. firma) *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"kontakt\" value=\"$kontakt\"></td></tr>\n";
	print "<tr><td>CPR/CVR nummer: </td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"cvrnr\" value=\"$cvrnr\"></td></tr>\n";
	print "<tr><td>Firmanavn:</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"firmanavn\" value=\"$firmanavn\"></td></tr>\n";
	print "<tr><td>Adresse: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"addr1\" value=\"$addr1\"></td></tr>\n";
	print "<tr><td>Adresse 2:</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"addr2\" value=\"$addr2\"></td></tr>\n";
	print "<tr><td>Post nr.: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"postnr\" value=\"$postnr\"></td></tr>\n";
	print "<tr><td>By: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bynavn\" value=\"$bynavn\"></td></tr>\n";
	print "<tr><td>Email: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"email\" value=\"$email\"></td></tr>\n";
	#print "<tr><td>Modtag mails fra Rotarys hjælpefond:</td><td><input type=\"text\" name=\"\"</td></tr>\n";
	print "<tr><td>Tlf:</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"tlf\" value=\"$tlf\"</td></tr>\n";
	if ($metode != 'kort') print "<tr><td colspan=\"2\"><input style=\"width:100%;\" type=\"submit\" name=\"tilmeld\" value=\"Tilmeld betalingsservice\"/></td></tr>\n";
	else print "<tr><td colspan=\"2\"><input style=\"width:100%;\" type=\"submit\" name=\"tilmeld\" value=\"Gå til kortbetaling\"/></td></tr>\n";
	print "</form></tbody></table>";
}
#print "<tr><td><small>+++ For at blive tilmeldt PBS skal CPR-nr./CVR-nr. opgives.<br>Endvidere, har du mulighed for at opnå skattefradrag,<br>når du giver et bidrag til $eget_firmanavn.<br>Hvis du vil have glæde af dit fradrag, skal du indberette<br>dit CPR-nr. eller CVR-nr. til $eget_firmanavn,<br>hvorefter $eget_firmanavn indberetter dette til SKAT.</small></td></tr>"; 
print "</html>";

function kortbetaling($regnskab,$ordernumber,$ordre_id,$sum,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf,$korttxt) {
?>
<script LANGUAGE="JavaScript" SRC="overlib.js"></script>
<script Language="JavaScript">
	function Form_Validator(theForm) {
		if (!theForm.betingelser.checked) {
			alert("Betingelser skal accepteres");
			theForm.betingelser.focus();
			return (false);
		}
		return (true);
	}
</script>
<?php

	while (strlen($ordernumber)<4) $ordernumber='0'.$ordernumber;  

	$r=db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$eget_firmanavn=$r['firmanavn'];
	$egen_addr1=$r['addr1'];
	$eget_postnr=$r['postnr'];
	$eget_bynavn=$r['bynavn'];
	$egen_tlf=$r['tlf'];
	$egen_email=$r['email'];
	$eget_cvrnr=$r['cvrnr'];

	$x=0;
	$q=db_select("select * from ordrelinjer where ordre_id='$ordre_id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$posnr[$x]==$r['posnr'];
		$vare_id[$x]=$r['vare_id'];
		$antal[$x]=$r['antal'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$pris[$x]=$r['pris'];
		$x++;
	}
	$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
	$merchant=trim($r['box4']);
	$md5secret=trim($r['box5']);

	$protocol='6';
	$msgtype='authorize';
#	$merchant='11637744';#89898978';
	$language='da';
#	$ordernumber = $arr_klub."-".$ordrenr;
	$amount=round($sum*100,2);
#cho "amount $amount<br>";
	$currency='DKK';
	$filnavn="$merchant.$ordernumber"."_ok.php";
	$fp=fopen("$filnavn","w");
	fwrite($fp, "<?php\n");
	fwrite($fp, "@session_start();\n");
	fwrite($fp, "$"."s_id=session_id();\n");
	fwrite($fp, "include(\"../includes/connect.php\");\n");
	fwrite($fp, "include(\"../includes/std_func.php\");\n");
	fwrite($fp, "include(\"bet_func.php\");\n");
	fwrite($fp, "$"."svar=logon("."$"."s_id,'".$regnskab."',"."$"."brugernavn,"."$"."password,"."$"."sqhost,"."$"."squser,"."$"."sqpass,"."$"."sqdb);\n");
	$txt="Betalt med kreditkort";
	$txt=db_escape_string($txt);
	fwrite($fp, "db_modify(".'"'."update ordrelinjer set beskrivelse = '".$txt."' where vare_id = '0' and ordre_id = '".$ordre_id."' and beskrivelse = '$korttxt'\",__FILE__ . \" linje \" . __LINE__);\n");
	fwrite($fp, "print \"<meta http-equiv=\\\"refresh\\\" content=\\\"0;URL=tak.php?id=$merchant.$ordernumber\\\">\";\n");
	fwrite($fp, "?>\n");
	fclose($fp);
	if ($_SERVER['HTTPS']) $ht_prot='https';
	else $ht_prot='http';
	list($tmp,$mappe,$tmp)=explode("/",$_SERVER['PHP_SELF']);
	$continueurl="$ht_prot://".$_SERVER['SERVER_NAME']."/$mappe/api/$filnavn";
#cho "$continueurl<br>";
	$cancelurl="$ht_prot://".$_SERVER['SERVER_NAME']."/$mappe/api/fejl.htm";
	$callbackurl="$ht_prot://".$_SERVER['SERVER_NAME']."/$mappe/api/callback.php"; //see http://quickpay.dk/clients/callback-quickpay.php.txt
#	$md5secret ='29p61DveBZ79c3144LW61lVz1qrwk2gfAFCxPyi5sn49m3Y3IRK5M6SN5d8a68u7';
#	$md5secret ='489e8edc32d5aa33191f81638bfdd160e4ce67ce9b5417ad3f40c73dbd6de9ed';
#cho "$merchant<br>";
#cho "$md5secret<br>";
	$md5check = md5($protocol.$msgtype.$merchant.$language.$ordernumber.$amount.$currency.$continueurl.$cancelurl.$callbackurl.$md5secret);
#cho $md5check;
	print "<table><tbody>
	<form action=\"https://secure.quickpay.dk/form/\" onsubmit=\"return Form_Validator(this)\" method=\"post\">
	<input type=\"hidden\" name=\"protocol\" value=\"$protocol\" />
	<input type=\"hidden\" name=\"msgtype\" value=\"$msgtype\" />
	<input type=\"hidden\" name=\"merchant\" value=\"$merchant\" />
	<input type=\"hidden\" name=\"language\" value=\"$language\" />
	<input type=\"hidden\" name=\"ordernumber\" value=\"$ordernumber\" />
	<input type=\"hidden\" name=\"amount\" value=\"$amount\" />
	<input type=\"hidden\" name=\"currency\" value=\"$currency\" />
	<input type=\"hidden\" name=\"continueurl\" value=\"$continueurl\" />
	<input type=\"hidden\" name=\"cancelurl\" value=\"$cancelurl\" />
	<input type=\"hidden\" name=\"callbackurl\" value=\"$callbackurl\" />
	<input type=\"hidden\" name=\"md5check\" value=\"$md5check\" />";
	
	$sum=0;
	print "<tr><td colspan=\"2\" style=\"text-align:center;\"><b>Betalingsinformation</b></td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td style=\"width:200px\"><b>Modtager<b></td><td style=\"width:200px\"></td></tr>";
	print "<tr><td style=\"width:200px\">Navn</td><td style=\"width:200px\">$eget_firmanavn</td></tr>";
	print "<tr><td style=\"width:200px\">Adresse</td><td style=\"width:200px\">$egen_addr1</td></tr>";
	print "<tr><td style=\"width:200px\"></td><td style=\"width:200px\">$eget_postnr $eget_bynavn</td></tr>";
	#cho "ECV $eget_cvrnr<br>";
	if ($eget_cvrnr) print "<tr><td style=\"width:200px;\">CVR:&nbsp;</td><td style=\"width:200px\">$eget_cvrnr</td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td style=\"width:200px\"><b>Afsender<b></td><td style=\"width:200px\"></td></tr>";
	IF ($medlemsnr) print "<tr><td style=\"width:200px\">Medlemsnummer</td><td style=\"width:200px\">$medlemsnr</td></tr>";
	print "<tr><td>Navn</td><td>$firmanavn</td></tr>";
	print "<tr><td>adresse</td><td>$addr1</td></tr>";
	print "<tr><td></td><td>$postnr $bynavn</td></tr>";
	print "<tr><td colspan=\"2\"><hr></td></tr>";
	print "<tr><td colspan=\"2\"><table><tbody>";
	print "<tr><td style=\"width:200px\"><b>Bestilt</b></td><td style=\"width:65px\" align=\"right\"><b>Antal</b></td><td style=\"width:65px\" align=\"right\"><b>Pris</b></td><td style=\"width:65px\" align=\"right\"><b>I alt</b></td></tr>";
	$y=count($vare_id);
	for($x=0;$x<$y;$x++) {
		if ($antal[$x]) {
			$dkantal=number_format($antal[$x],2,',','.');
			$dkantal=str_replace(",00","",$dkantal);
			print "<tr><td align=\"left\">$beskrivelse[$x]</td>
			<td align=\"right\">$dkantal</td>
			<td align=\"right\">".number_format($pris[$x],2,',','.')."</td>
			<td align=\"right\">".number_format($pris[$x]*$antal[$x],2,',','.')."</td></tr>";
		}	
		$sum+=$pris[$x]*$antal[$x];
	}
	print "<tr><td colspan=\"4\"><hr></td></tr>";
	print "<tr><td colspan=\"3\" align=\"left\"><b>I alt til betaling</b></td><td align=\"right\"><b>".number_format($sum,2,',','.')."</b></td></tr>";
	print "</tbody></table></td></tr>";
	$spantekst="<big>Klik her for at l&aelig;se handelsbetingelserne.</big>";
	print "<tr><td>Accepterer <span onmouseover=\"return overlib('$spantekst', WIDTH=800);\" onmouseout=\"return nd();\"><a onMouseOver=\"this.style.cursor = 'pointer'\" onClick=\"javascript:betingelser=window.open('betingelser.html','betingelser','left=10,top=10,width=400,height=400,scrollbars=1,resizable=1');betingelser.focus();\"><u>betingelser</u></a></span></td>\n";
	print "<td colspan= \"1\" align=\"right\"><input type=\"checkbox\" name=\"betingelser\" $betingelser></td></tr>";
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" value=\"Gå til betaling\" /><td></tr></form>";
	print "<form action=\"kortbetaling.php?regnskab=$regnskab&ordre_id=$ordre_id&ordernumber=$ordernumber\" method=\"post\">";#
	print "<tr><td colspan=\"2\"><input style=\"width:100%\" type=\"submit\" value=\"Fortryd\" /><td></tr></form>";
	print "</tbody></table>";
#	<input type=\"submit\" value=\"Open Quickpay payment window\" />";
exit;
}
?>
