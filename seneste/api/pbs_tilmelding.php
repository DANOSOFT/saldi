<?php

// -------- shop/pbs_tilmelding.php----------lap 3.4.0 ----- 2014.03.15----------
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
// Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------

// 2013.10.13 Kontrol af cvr.nr.
// 2014.03.15 Indsat ,100 (procent) før ,'DO' i opret_ordrelinje grundet ændring af funktion


@session_start();
$s_id=session_id();


include("../includes/connect.php");
include("../includes/std_func.php");

$regnskab=if_isset($_GET['regnskab']);
#$brugernavn=if_isset($_POST['brugernavn']);
#$password=if_isset($_POST['password']);

$svar=logon($s_id,$regnskab,$brugernavn,$password,$sqhost,$squser,$sqpass,$sqdb);
#cho "Svar: ".$svar."<br>";

print "<html><head>
					<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
					<title>PBS tilmelding</title>
				</head>";
$css="../css/standard.css";
print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">";


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


	$cvrnr=str_replace("-","",$cvrnr);
	$cvrnr=str_replace(" ","",$cvrnr);
	$alert=tjek($belob,$bank_navn,$bank_reg,$bank_konto,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf);
	if ($alert=='OK') {
		$kontonr=1000;
		$x=0;
		$ktonr=array();
		$q=db_select("select * from adresser where art='D' and kontonr >='1000' order by kontonr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$ktonr[$x]=$r['kontonr'];
			$x++;
		}
		while (in_array($kontonr,$ktonr)){
			$kontonr++;
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
		$qtxt="insert into adresser(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,cvrnr,tlf,kontakt,gruppe,kontotype,art,bank_navn,bank_reg,bank_konto,pbs,pbs_nr) values ('$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$email','$cvrnr','$tlf','$kontakt','$gruppe','$kontotype','$art','$bank_navn','$bank_reg','$bank_konto','on','')";
		#cho "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from adresser where kontonr='$kontonr' and art = 'D'";
		#cho "$qtxt<br>";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$konto_id=$r['id'];
	#cho "konto_id $konto_id<br>";
		if ($konto_id) {
			if ($kontakt) {
				$qtxt="insert into ansatte(konto_id, navn) values ('$konto_id', '$kontakt')";
				#cho "$qtxt<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
			}
			$qtxt="select max(ordrenr) as ordrenr from ordrer where art='DO'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$ordrenr=$r['ordrenr']+1;
			$ordredate=date("Y-m-d");
			$status=0;
			$art='DO';
			$qtxt="insert into ordrer(konto_id,kontonr,ordrenr,firmanavn,addr1,addr2,postnr,bynavn,email,kontakt,art,status,udskriv_til,ordredate) values ('$konto_id','$kontonr','$ordrenr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$email','$kontakt','$art','$status','PBS','$ordredate')";
#cho "$qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
			$r=db_fetch_array(db_select("select max(id) as id from ordrer where konto_id='$konto_id' and art = '$art'",__FILE__ . " linje " . __LINE__));
			$ordre_id=$r['id'];
			$txt="Tilmeldt PBS, betalingsinterval: $interval, beløb: $belob";
			$txt=db_escape_string($txt);
#cho "$txt<br>";
			$qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr) values ('$ordre_id','$txt','1')";
#cho "$qtxt<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
#cho "vare_id $vare_id<br>";
			if ($vare_id) {
				$amount=usdecimal($belob);
				$qtxt="select * from varer where id = '$vare_id'";
#cho "$qtxt<br>";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#cho "opret_ordrelinje($ordre_id,$r[varenr],$r[antal],$r[beskrivelse],$amount,'0',100,'DO',$r[momsfri],'2','0','0','','','')<br>";
				opret_ordrelinje($ordre_id,$r['varenr'],1,$r['beskrivelse'],$amount,'0',100,'DO',$r['momsfri'],'2','0','0','','','');
			}
		
		
		} 
		$txt="Tak for din tilmelding";
		print "<BODY onLoad=\"javascript:alert('$txt')\">";

	} else {
		print "<BODY onLoad=\"javascript:alert('$alert')\">";
		$alert=NULL;
	}
}
if (!$alert) {
	$x=0;
	$qtxt="select id,beskrivelse from varer where publiceret='on' and lukket !='on' order by beskrivelse";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$vare_id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}

	print "<table><tbody>";
	print "<form name=\"pbs_tilmelding\" action=\"pbs_tilmelding.php?regnskab=$regnskab\" method=\"post\">";
	print "<tr><td colspan=\"2\"><big><b>Udfyld personlige oplysninger</b></big></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Tilmelding til fast støtte via betalingsservice</b></td></tr>\n";
	print "<tr><td  style=\"width:220px\">Beløb: *</td><td align=\"left\"><input style=\"width:100px\" type=\"text\" name=\"belob\" value=\"$belob\"><font color=\"gray\">Kr. (min. 50 kr.)</font></td></tr>\n";
	($interval=='maaned')?$maaned='checked':$maaned=NULL;
	($interval=='kvartal')?$kvartal='checked':$kvartal=NULL;
	($interval=='aar')?$aar='checked':$aar=NULL;
	if (!$interval) $kvartal='checked';
	print "<tr><td>Hvor tit vil du støtte: *</td><td align=\"right\">
		<input type=\"radio\" name=\"interval\" value=\"maaned\" $maaned>maaned
		<input type=\"radio\" name=\"interval\" value=\"kvartal\" $kvartal>kvartal
		<input type=\"radio\" name=\"interval\" value=\"aar\" $aar>aar
		</td></tr>\n";
	print "<tr><td>Hvilket projekt vil du støtte</td>";
	print "<td align=\"right\"><select  style=\"width:200px\" name=\"vare_id\">";
	for ($x=0;$x<count($vare_id);$x++) {
		print "<option value=\"$vare_id[$x]\">$beskrivelse[$x]</option>";
	}
	print "</select>";
	print "<tr><td colspan=\"2\"><b>Bankoplysninger</b></td></tr>\n";
	print "<tr><td>Pengeinstitut: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bank_navn\" value=\"$bank_navn\"></td></tr>\n";
	print "<tr><td>Reg. nr.: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bank_reg\" value=\"$bank_reg\"></td></tr>\n";
	print "<tr><td>Konto nr.: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bank_konto\" value=\"$bank_konto\"></td></tr>\n";
	print "<tr><td colspan=\"2\"><b>Person -/firmaoplysninger</b></td></tr>\n";
	print "<tr><td>Fulde navn: (Kontakt v. firma) *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"kontakt\" value=\"$kontakt\"></td></tr>\n";
	print "<tr><td>CPR/CVR nummer: * +++</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"cvrnr\" value=\"$cvrnr\"></td></tr>\n";
	print "<tr><td>Firmanavn:</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"firmanavn\" value=\"$firmanavn\"></td></tr>\n";
	print "<tr><td>Adresse: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"addr1\" value=\"$addr1\"></td></tr>\n";
	print "<tr><td>Adresse 2:</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"addr2\" value=\"$addr2\"></td></tr>\n";
	print "<tr><td>Post nr.: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"postnr\" value=\"$postnr\"></td></tr>\n";
	print "<tr><td>By: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bynavn\" value=\"$bynavn\"></td></tr>\n";
	print "<tr><td>Email: *</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"email\" value=\"$email\"></td></tr>\n";
	#print "<tr><td>Modtag mails fra Rotarys hjælpefond:</td><td><input type=\"text\" name=\"\"</td></tr>\n";
	print "<tr><td>Tlf:</td><td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"tlf\" value=\"$tlf\"</td></tr>\n";
	print "<tr><td colspan=\"2\"><input style=\"width:100%;\" type=\"submit\" name=\"tilmeld\" value=\"Tilmeld betalingsservice\"/></td></tr>\n";
	print "</form></tbody></table>";
}
print "</html>";

function logon($s_id,$regnskab,$brugernavn,$password,$sqhost,$squser,$sqpass,$sqdb) {
	$password=md5($password);
	$unixtime=date("U");
	include("../includes/db_query.php");
	include ("../includes/connect.php");
#	db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
#cho "select * from regnskab where regnskab = '$regnskab'";
	if ($r=db_fetch_array(db_select("select * from regnskab where regnskab = '$regnskab'",__FILE__ . " linje " . __LINE__))) {
		if ($db = trim($r['db'])) {
			$connection = db_connect ($sqhost,$squser,$sqpass,$db);
			if ($connection) {
#cho "select id from brugere where brugernavn='PBS_TILMELDING'<br>";
				$r=db_fetch_array(db_select("select id from brugere where brugernavn='PBS_TILMELDING'",__FILE__ . " linje " . __LINE__));
				if ($r['id']) {
				#				db_modify("insert into online (session_id, brugernavn, db, dbuser) values ('$s_id', '$brugernavn', '$db', '$squser')",__FILE__ . " linje " . __LINE__);
#				include ("../includes/online.php");
#				if ($r = db_fetch_array(db_select("select * from brugere where brugernavn = '$brugernavn' and kode='$password'",__FILE__ . " linje " . __LINE__))) {
#					$rettigheder=trim($r['rettigheder']);
#					$regnskabsaar=$r['regnskabsaar']*1;
#				include ("../includes/connect.php");
#			$fp=fopen("../temp/.ht_$db.log","a");
#			fwrite($fp,"-- ".$brugernavn." ".date("Y-m-d H:i:s").": OK jeg er inde ".$s_id."\n");
#			fclose($fp);
#					db_modify("update online set regnskabsaar='$regnskabsaar', rettigheder='$rettigheder' where session_id='$s_id'",__FILE__ . " linje " . __LINE__);
					$return='0'.chr(9).$s_id;
				} else {
#					db_modify("delete from online where  session_id = '$s_id'",__FILE__ . " linje " . __LINE__);
					$return="1".chr(9)."Username or password error";
				}	
			} else $return="1".chr(9)."Connection to database failed";
		} else $return="1".chr(9)."Unknown financial report";
	} else return $return="1".chr(9)."Unknown financial report";
	return ($return);
}
function tjek($belob,$bank_navn,$bank_reg,$bank_konto,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf) {
	$alert='OK';
	if (!$belob || $belob<50) $alert="Beløb skal være min. 50,-"; 
	if (!$bank_navn) $alert="Pengeinstitut navn ikke angivet";
	if (!$bank_reg) $alert="Reg nr. navn ikke angivet";
	if (!$bank_konto) $alert="Konto nr. navn ikke angivet";
	if (!$kontakt && $firmanavn) $alert="Kontakt ikke angivet";
	elseif (!$kontakt) $alert="Navn ikke angivet";
	if (!$cvrnr && $firmanavn) $alert="Cvr nr. ikke angivet";
	if (strlen($cvrnr)!='10') {
		if ($firmanavn) $alert="Cvr nr. skal bestå af 10 cifre";
		else $alert="Cpr nr. skal bestå af 10 cifre";
	}
	elseif (!$cvrnr) $alert="Cpr nr. ikke angivet";
	if (!$addr1) $alert="Adresse ikke angivet"; 
	if (!$postnr) $alert="Postnr ikke angivet"; 
	if (!$bynavn) $alert="By ikke angivet"; 
	if (!$email) $alert="email ikke angivet"; 
	return ("$alert");
}
	
?>
