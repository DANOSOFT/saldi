<?php
// #----------------- opdat_saldi_kat.php -----ver 3.2.9---- 2012.08.13 ----------
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
echo "language_id $language_id<br>";
include("soapfunc.php");

$next=$_GET['next']*1;

list($errorcode,$s_id)=explode(chr(9),logon($regnskab,$brugernavn,$adgangskode));
$starttid=date("U");
$x=0;
#echo "select categories.categories_id,categories.parent_id,categories_description.categories_name from categories,categories_description where categories_description.language_id='$language_id' and categories_description.categories_id=categories.categories_id order by categories.parent_id<br>";
$q=mysql_query("select categories.categories_id,categories.parent_id,categories_description.categories_name from categories,categories_description where categories_description.language_id='$language_id' and categories_description.categories_id=categories.categories_id order by categories.parent_id,categories_id");
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
$tmp=array();
$tmp=explode("/",$svar);
$filnavn="opdat_saldi_kat.tmp";
#$hent=str_replace("../","",$svar);
#$hent="https://ssl.saldi.dk/finans/".$hent;
$hent=$url.$hent;
$hent=str_replace("/soapserver","",$hent);
#if(file_exists($filnavn)) unlink($filnavn); 
#unlink($filnavn);
#system("wget ".$hent);
$fp1=fopen($hent,"r");
$fp2=fopen($filnavn,"w");
while($linje=fgets($fp1)){
	fwrite($fp2,$linje);
}
fclose($fp1);
fclose($fp2);

$y=0;
$fp=fopen($filnavn,'r');
while ($linje=fgets($fp)) {
	if ($y) {
		list($kat_id[$y],$kat_navn[$y],$kat_master[$y],$shop_kat_id[$y])=explode(chr(9),$linje);
	}
	$y++;
}
$v_cat_antal=$y;
fclose($fp);
for ($x=1;$x<=count($categories_id);$x++) {
	$saldi_id[$x]=NULL;
	$opdater_saldi[$x]=1;
	$opret_i_saldi[$x]=1;
# echo "Saldi id: $saldi_id[$x] - Opdat: $opdater_saldi[$x] - Opret: $opret_i_saldi[$x]<br>";
	for ($y=1;$y<=count($kat_id);$y++) {
		if ($shop_kat_id[$y] && $shop_kat_id[$y]==$categories_id[$x]){
			$opret_i_saldi[$x]=0;
			$saldi_id[$x]=$kat_id[$y];
			if ($categories_name[$x] == $kat_navn[$y]) $opdater_saldi[$x]=0; 
		}
	}
}

for ($x=1;$x<=count($categories_id);$x++) {
	if (!$parent_id[$x] && $opret_i_saldi[$x]) {
echo "opretter $categories_name[$x]<br>";
			list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"grupper (beskrivelse,art,box1,box2,box3) values ('Varekategorier','V_CAT','".addslashes($categories_name[$x])."','0','$categories_id[$x]')"));
			if ($fejl) echo "fejl $svar<br>";
			$saldi_id[$x]=$svar;
			$opret_i_saldi[$x]=0;
	}
}

$looptjek=0;
while (in_array(0,$opret_i_saldi)&&$looptjek<10) {
	$looptjek++;
	for ($x=1;$x<=count($categories_id);$x++) {
		if ($parent_id[$x]) {
			for ($y=1;$y<=count($categories_id);$y++) {
				if ($parent_id[$x]==$categories_id[$y] && !$saldi_master_id[$x]){
					$tmp=$x-1;
					if ($saldi_id[$y]==$saldi_master_id[$tmp]) $saldi_master_id[$x]=$saldi_id[$y];
					else {
#echo "$y select box2 from grupper where id = '$saldi_id[$y]'<br>";
						list($fejl,$svar)=explode(chr(9),singleselect($s_id,"box2 from grupper where id = '$saldi_id[$y]'"),2);
						if ($fejl) echo ($svar);
						if (trim($svar)) $saldi_master_id[$x]=$svar."<TAB>".$saldi_id[$y];
						else $saldi_master_id[$x]=$saldi_id[$y];
#						$sa_id[$x]=$saldi_id[$y];
					}
#echo "$x master_id $saldi_master_id[$x]<br>";
				}
			}
		}
	}
	for ($x=1;$x<=count($categories_id);$x++) {
		if ($parent_id[$x] && $opret_i_saldi[$x]) {
echo "opretter $categories_name[$x] med master_id ".str_replace("<TAB>","->",$saldi_master_id[$x])."<br>";
# echo "grupper (beskrivelse,art,box1,box2,box3) values ('Varekategorier','V_CAT','".addslashes($categories_name[$x])."','$saldi_master_id[$x]','$categories_id[$x]')<br>";
				list($fejl,$svar)=explode(chr(9),singleinsert($s_id,"grupper (beskrivelse,art,box1,box2,box3) values ('Varekategorier','V_CAT','".addslashes($categories_name[$x])."','$saldi_master_id[$x]','$categories_id[$x]')"));
				if ($fejl) echo "fejl $svar<br>";
				$saldi_master_id[$x]=$svar;
				$opret_i_saldi[$x]=0;
		}
	}
	$tmp=date("U")-20;
	if ($starttid<$tmp) {
		echo "tiden udl&oslash;bet<br>";
		exit;
	}
}
logoff($s_id);
?>