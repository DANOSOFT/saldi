<?php
// #----------------- opdat_shop_kat.php -----ver 3.2.6---- 2012.01.14 ----------
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


include("shop_connect.php");
include("soapfunc.php");
include("saldi_connect.php");
list($errorcode,$s_id)=explode(chr(9),logon($regnskab,$brugernavn,$adgangskode));

$categories_id=array(); #Kategori id'er i shoppen;
$parent_id=array(); #Parent id'er i shoppen;
$categories_name=array(); #kategori navne i shoppen;	

$kat_id=array(); #Kategori id'er i saldi;
$kat_navn=array(); #kategori navne i saldi;
$kat_master=array(); #Parent id'er i saldi;
$shop_kat_id=array(); #De shop id'er der er registreret på kategorierne i saldi;


$x=0;
# echo "select categories.categories_id,categories.parent_id,categories_description.categories_name from categories,categories_description where categories_description.language_id='$language_id' and categories_description.categories_id=categories.categories_id order by categories.parent_id<br>";
$q=mysql_query("select categories.categories_id,categories.parent_id,categories_description.categories_name from categories,categories_description where categories_description.language_id='$language_id' and categories_description.categories_id=categories.categories_id order by categories.parent_id");
while ($r=mysql_fetch_array($q)) {
	if (trim($r['categories_name'])) {
		$x++;
		$categories_id[$x]=$r['categories_id'];
		$parent_id[$x]=$r['parent_id'];
		$categories_name[$x]=$r['categories_name'];
#		echo "$categories_id[$x] $categories_name[$x] $parent_id[$x]<br>";
	}
}
$svar=multiselect($s_id,"id,box1,box2,box3 from grupper where art='V_CAT' order by id");
list($fejl,$svar)=explode(chr(9),$svar);
if ($fejl) echo "$fejl -- $svar<br>";
if ($fejl) return ('1'.chr(9).$svar);
echo "<br><br><br><br><br><br>";

$filnavn="opdat_shop_kat.csv";
$hent=str_replace("../","",$svar);
$hent=$url.$hent;
$hent=str_replace("/soapserver","",$hent);
#echo "hent $hent<br>"; 
#echo "filnavn $filnavn<br>"; 

$fp1=fopen($hent,"r");
$fp2=fopen($filnavn,"w");
while($linje=fgets($fp1)){
	fwrite($fp2,$linje);
}
fclose($fp1);
fclose($fp2);

$fp=fopen($filnavn,'r');
$tmp=array();
while ($linje=fgets($fp)) {
	if ($y) {
		list($kat_id[$y],$kat_navn[$y],$kat_masters[$y],$shop_kat_id[$y])=explode(chr(9),$linje);
		$kat_id[$y]=trim($kat_id[$y]);
		$kat_navn[$y]=trim($kat_navn[$y]);
		$tmp=explode("<TAB>",$kat_masters[$y]);
		$z=count($tmp)-1;
		$kat_master[$y]=$tmp[$z];
		$shop_kat_id[$y]=trim($shop_kat_id[$y]);
# echo "ID: $kat_id[$y], Navn: $kat_navn[$y], Paren: $kat_master[$y], Shop ID: $shop_kat_id[$y]<br>";
	}
	$y++;
}

$v_cat_antal=$y;
fclose($fp);
for ($x=1;$x<=count($kat_id);$x++) {
	if (!$shop_kat_id[$x] || !in_array($shop_kat_id[$x],$categories_id)) $opret_i_shop[$x]=1;
# echo "$kat_navn[$x],$shop_kat_id[$x] => $opret_i_shop[$x]=1<br>";
}
# exit;
for ($x=1;$x<=count($kat_id);$x++) {
# echo "!$kat_master[$x] && $opret_i_shop[$x]<br>";
	if (!$kat_master[$x] && $opret_i_shop[$x]) {
echo "opretter $kat_navn[$x]<br>";
		mysql_query("insert into categories(parent_id,sort_order,date_added) values ('0','0','$datotid')");
		$r=mysql_fetch_array(mysql_query("select max(categories_id) as categories_id from categories where parent_id='0' and sort_order='0' and date_added='$datotid'"));
		$shop_kat_id[$x]=$r['categories_id'];
# echo "insert into categories_description(categories_id,language_id,categories_name) values ('$shop_kat_id[$x]','$language_id','$kat_navn[$x]')<br>";
		mysql_query("insert into categories_description(categories_id,language_id,categories_name) values ('$shop_kat_id[$x]','$language_id','$kat_navn[$x]')");
		list($fejl,$svar)=explode(chr(9),singleupdate($s_id,"grupper set box3='$shop_kat_id[$x]' where id = $kat_id[$x]"));
		if ($fejl) echo "$svar<br>";
		$opret_i_shop[$x]=0;
	}
}
for ($x=1;$x<=count($kat_id);$x++) {

	if ($kat_master[$x] && $opret_i_shop[$x]) {
# echo "$kat_master[$x] && $opret_i_shop[$x]<br>";
		for ($y=1;$y<=count($kat_id);$y++) {
# echo "$kat_master[$x]==$kat_id[$y]<br>";
			if ($kat_master[$x]==$kat_id[$y]) {
				$shop_kat_master[$x]=$shop_kat_id[$y];
				$shop_kat_master_name[$x]=$kat_navn[$x];
# echo "$shop_kat_master[$x] | $shop_kat_master_name[$x] | $shop_kat_id[$y]<br>"; 
			}
		}
		if ($shop_kat_master[$x]) {
			echo "opretter $kat_navn[$x] med parent $shop_kat_master[$x]<br>";
			mysql_query("insert into categories(parent_id,sort_order,date_added) values ('$shop_kat_master[$x]','0','$datotid')");
			$r=mysql_fetch_array(mysql_query("select max(categories_id) as categories_id from categories where parent_id='$shop_kat_master[$x]' and sort_order='0' and date_added='$datotid'"));
			$shop_kat_id[$x]=$r['categories_id'];
# echo "insert into categories_description(categories_id,language_id,categories_name) values ('$shop_kat_id[$x]','$language_id','$kat_navn[$x]')<br>";
			mysql_query("insert into categories_description(categories_id,language_id,categories_name) values ('$shop_kat_id[$x]','$language_id','$kat_navn[$x]')");
			list($fejl,$svar)=explode(chr(9),singleupdate($s_id,"grupper set box3='$shop_kat_id[$x]' where id = $kat_id[$x]"));
			if ($fejl) echo "$svar<br>";
			$opret_i_shop[$x]=0;
		}
	}
}
logoff($s_id);
 print "<body onload=\"javascript:window.close();\">";
?>