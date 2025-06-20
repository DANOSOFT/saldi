<?php
// #----------------- opdat_vare.php -----ver 3.2.6---- 2012.01.14 ----------
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
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------


$vare_id=$_GET['vare_id'];
$shop_id=$_GET['shop_id'];
$varenr=$_GET['varenr'];
$beskrivelse=$_GET['beskrivelse'];
#cho "$beskrivelse<br>";
$notes=$_GET['notes'];
$kat_id=explode(chr(9),$_GET['kat_id']);
$parent_kat_id==explode(chr(9),$_GET['parent_kat_id']);
$kat=explode(chr(9),$_GET['kat']);
$parent_kat==explode(chr(9),$_GET['parent_kat']);
$shop_kat_id=explode(chr(9),$_GET['shop_kat_id']);
$parent_shop_kat_id=explode(chr(9),$_GET['parent_shop_kat_id']);
$publiceret=$_GET['publiceret'];
$salgspris=$_GET['salgspris'];
$beholdning=$_GET['beholdning'];
$datotid=date("Y-m-d H:i:s");

#cho count($shop_kat_id)."<br>";
for($i=0;$i<=count($shop_kat_id);$i++) {
	#cho $shop_kat_id[$i]."<br>";
}

($publiceret)?$status=1:$status=0;
$beskrivelse=utf8_decode($beskrivelse);
# $beskrivelse=htmlkod($beskrivelse);
# $notes=htmlkod($notes);

#cho "ID: $vare_id<br>Varenr: $varenr<br>Beskrivelse: $beskrivelse<br>Shop ID: $shop_id<br>";

for ($i=0;$i<count($kategori);$i++) {
	#cho "Kategori: $kategori[$i]<br>Shop kat ID: $shop_kat_id[$i]<br>";
}
if ($vare_id&&$varenr&&$beskrivelse) {
	include("shop_connect.php");
	include("soapfunc.php");
#	logon($regnskab,$brugernavn,$adgangskode);

	$x=0;
	$shop_lang_id[$x]=$language_id;
	$q=mysql_query("select languages_id from languages where languages_id != '$language_id'") or die(mysql_error());
	while ($r=mysql_fetch_array($q)) {
		$x++;
		$shop_lang_id[$x]=$r['languages_id'];
	}
	$shop_lang_antal=$x;

	if ($shop_id && $r=mysql_fetch_array(mysql_query("select products_model from products where products_id = '$shop_id'"))){
		$products_id=$shop_id;
#cho "produkt_id<br>";
	} else {
		if (!$r=mysql_fetch_array(mysql_query("select products_id from products where products_model = '$varenr'"))){
			mysql_query("insert into products(products_model,products_date_added,products_status) values ('$varenr','$datotid','1')");
			$r=mysql_fetch_array(mysql_query("select products_id from products where products_model = '$varenr'"));
			$products_id=$r['products_id'];
			mysql_query("insert into products_description(products_id,products_viewed,language_id) values ('$products_id','0','$language_id'");
		} else $products_id=$r['products_id'];
	}
	for ($x=1;$x<=$shop_lang_antal;$x++) { #Tjekker om produktbeskrivelser er oprettet i alle sprog og opretter hvis den ikke er.
		if (!mysql_fetch_array(mysql_query("select * from products_description where products_id='$products_id' and language_id='$shop_lang_id[$x]'"))) {
			mysql_query("insert into products_description(products_id,products_viewed,language_id) values ('$products_id','0','$shop_lang_id[$x]')");
		}
	}

	$fp=fopen("vareopdat.log","a");
	fwrite($fp,"update products set products_model='$varenr', products_price='$salgspris',products_quantity='$beholdning',products_tax_class_id='$products_tax_class_id',products_status='$status' where products_id='$products_id'\r\n");
	fwrite($fp,"update products_description set products_name='$beskrivelse',products_description,='$notes' where products_id='$products_id' and language_id='$language_id'\r\n");
	fclose($fp); 
#cho "update products set products_model='$varenr',products_price='$salgspris',products_quantity='$beholdning',products_tax_class_id='$products_tax_class_id' where products_id='$products_id'<br>";
	$tmp=mysql_query("update products set products_model='$varenr', products_price='$salgspris',products_quantity='$beholdning',products_tax_class_id='$products_tax_class_id',products_status='$status' where products_id='$products_id'");
	if (!$tmp) die('Fejl 1: ' . mysql_error());
#cho "update products_description set products_name='$beskrivelse',products_description,='$notes' where products_id='$products_id' and language_id='$language_id'<br>"; 
	$tmp=mysql_query("update products_description set products_name='$beskrivelse' where products_id='$products_id' and language_id='$language_id'");
	if (!$tmp) die('Fejl 2: ' . mysql_error());
	for($i=0;$i<count($shop_kat_id);$i++) {
		$shop_kat_id[$i]*=1;
#cho "i = $i $shop_kat_id[$i]<br>";
		if (!$r=mysql_fetch_array(mysql_query("select * from products_to_categories where categories_id='$shop_kat_id[$i]' and products_id='$products_id'"))){
#cho "insert into products_to_categories(categories_id,products_id) values ('$shop_kat_id[$i]','$products_id')";
			$tmp=mysql_query("insert into products_to_categories(categories_id,products_id) values ('$shop_kat_id[$i]','$products_id')");
			if (!$tmp) die('Fejl 3: ' . mysql_error());
		}
	}	
#cho "update products_description(products_id='$products_id',products_name='$beskrivelse',products_description,='$notes') where products_id='$products_id' and language_id='$language_id'";
#cho "select * from products_description where products_id = '$products_id' and language_id='$language_id'<br>";
	if (!$r=mysql_fetch_array(mysql_query("select * from products_description where products_id = '$products_id' and language_id='$language_id'"))) {
		#cho "insert into products_description(products_id,products_viewed) values ('$products_id','0')<br>";
		$tmp=mysql_query("insert into products_description (products_id,language_id,products_viewed) values ('$products_id','$language_id','0')");
		if (!$tmp) die('Fejl 4: ' . mysql_error());
	}
#cho "update products_description set products_id='$products_id',products_name='$beskrivelse',products_description,='$notes' where products_id='$products_id' and language_id='$language_id'";
	$tmp=mysql_query("update products_description set products_id='$products_id',products_name='$beskrivelse' where products_id='$products_id' and language_id='$language_id'");
	if (!$tmp) die('Fejl 5: ' . mysql_error());
	
	if (!$shop_id) {
		include("saldi_connect.php");
#		include("soapfunc.php");
		list($errorcode,$s_id)=explode(chr(9),logon($regnskab,$brugernavn,$adgangskode));
		list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"shop_varer (shop_id,saldi_id) values ('$products_id','$vare_id')"));
		logoff($s_id);
	}
	

	
} else echo "vare ID mangler";



function htmlkod($ord){
	$ord=str_replace("æ","&aelig;",$ord);
	$ord=str_replace("ø","&oslash;",$ord);
	$ord=str_replace("å","&aring;",$ord);
	$ord=str_replace("Æ","&Aelig;",$ord);
	$ord=str_replace("Ø","&Oslash;",$ord);
	$ord=str_replace("Å","&Aring;",$ord);
	return($ord);
}
print "<body onload=\"javascript:window.close();\">";
?>