<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- api/shopQty2saldi.php ---------- lap 3.8.5----2019.11.04-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk aps eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk aps
// ----------------------------------------------------------------------
// 
// 2018.04.24 Omskrevet variant delen så det inditificeres på variant_id i stedet for på stregkode så det er muligt at ændre stregkode på shop.
// 2018.06.26 Kontrol for stregkodedubletter. søg $strktjek
// 2019.06.19 Forbedret dubletkontrol.
// 2019.11.04 Added utf8_encode to $stregkode[$y] 20191104

@session_start();
$s_id=session_id();

#varesync();

function varesync() {
/*

global $db;

	$showtxt=NULL;
	
	$qtxt="select box4 from grupper where art='API'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']);
	$tmparray=explode("/",$api_fil);
	$lagerfil='';
	for ($x=0;$x<count($tmparray)-1;$x++) {
		$lagerfil.=$tmparray[$x]."/";
	}
	$lf=$lagerfil."files/shop_products.csv";
	$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
	system ("/usr/bin/wget --no-check-certificate --spider --header='$header' $api_fil?products_id=* &\n");
	system ("cd ../temp/$db/\nwget --no-check-certificate --header='$header' $lf\n");
	$indhold=file_get_contents("../temp/$db/shop_stock.csv");
	unlink("../temp/$db/shop_stock.csv");
	$linje=explode("\n",$indhold);
	$shop_encode='';
	for ($y=0;$y<count($linje);$y++){
		$shop_id[$y]=0;
		list($shop_id[$y],$qty[$y])=explode(";",$linje[$y]);
		$shop_id[$y]=trim($shop_id[$y],'"');
		$qty[$y]*=1;
	}
	for ($y=0;$y<count($linje);$y++) {
		$vare_id[$y]=NULL;
		$qtxt = "select saldi_id,saldi_variant from shop_varer where (shop_id='$shop_id[$y]' and shop_variant='0') ";
		$qtxt.= "or shop_variant= $shop_id[$y]";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($r['saldi_id']) $vare_id[$y]=$r['saldi_id'];
		if ($r['saldi_variant']) $variant_id[$y]=$r['saldi_variant'];
		if ($vare_id[$y]) {
			$qtxt="select id from lagerstatus where saldi_id='$vare_id[$y]' and variant_id=$variant_id[$y]";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r['id'])	$qtxt="update lagerstatus set beholdning='$qty[$y]' where id='$r[id]'";
			else {
				$qtxt = "insert into lagerstatus (saldi_id,variant_id,beholdning,lager) values ";
				$qtxt.= "('$vare_id[$y]','$variant_id[$y]','$qty[ $y]','0')";
			}
echo __line__." $qtxt<br>";
#			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}	
*/	
}

?>
