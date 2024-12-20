<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------lager/ret_varenr.php-------------patch 3.9.1 -- 20200612 ----------
// LICENS
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 2015.12.08 Tilføjet flet funtion til sammenlægning af varer.
// 2018.05.15 Kontrol mod flet af styklister og vares som indgår.
// 2020.06.12 PHR Added pattern match on item no (varenr).

@session_start();
$s_id=session_id();

$title="Ret varenummer";
$modulnr=9;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (isset($_GET['id'])) $id = $_GET['id'];
elseif(isset($_POST['id'])) {
	$id = $_POST['id'];
	$varenr = $_POST['varenr'];
	$stregkode = $_POST['stregkode'];
	$nyt_varenr = db_escape_string(trim($_POST['nyt_varenr']));
}

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>\n";
print "<tr><td height = \"25\" align=\"center\" valign=\"top\">\n";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>\n";
print "<tr><td width=\"10%\" $top_bund><a href=varekort.php?id=$id accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund align=\"center\">$title</td>";
print "<td width=\"10%\" $top_bund align=\"right\"><br></td></tr>";
print "</tbody></table>";
print "</td></tr>\n";
print "<tr><td>\n";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=100% valign = \"center\" align = \"center\"><tbody>\n";

if (($nyt_varenr) && ($nyt_varenr!=$varenr)) {
	$qtxt = "select id from varer where varenr = '$nyt_varenr' or stregkode = '$nyt_varenr'";
echo "$qtxt<br>";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		print tekstboks('Varenummer: $nyt_varenr er i brug, varenummer ikke &aelig;ndret');
	}	elseif (substr($nyt_varenr,0,1)=='=') {
		$fletvnr=substr($nyt_varenr,1);
echo "$qtxt<br>";
		if ($varenr == $fletvnr || $stregkode == $fletvnr) {
			$txt="Varenummer: $varenr kan ikke sammenlægges med sig selv";
			print "javascript:alert(\"$txt\")";
			print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
			exit;
		} elseif ($r=db_fetch_array(db_select("select id from styklister where vare_id = '$id' or indgaar_i = '$id'",__FILE__ . " linje " . __LINE__))) {
			$txt="Varenummer: $varenr er del af en stykliste og kan ikke sammenlægges med andre varer";
			alert($txt);
			print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
			exit;
#		} elseif ($r=db_fetch_array(db_select("select styklister.id from styklister,varer where varer.varenr = '$fletvnr' or varer.stregkode = '$fletvnr' and styklister.vare_id = varer.id or styklister.indgaar_i = varer.id",__FILE__ . " linje " . __LINE__))) {
#			$txt="Varenummer: $fletvnr er del af en stykliste hvorfor $varenr ikke kan ikke sammenlægges med denne";
#			print tekstboks("$txt");
#			print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
#			exit;
		} elseif ($r=db_fetch_array(db_select("select id,varenr,stregkode from varer where varenr = '$fletvnr' or stregkode = '$fletvnr'",__FILE__ . " linje " . __LINE__))) {
			$txt="Varenummer:$varenr sammenlægges med vnr $r[varenr]";
			if ($r['stregkode']) $txt.=" (Stregkode $r[stregkode])";
			alert($txt);
			flet($id,$varenr,$r['id'],$r['varenr'],'');
			exit;
		} elseif ($r=db_fetch_array(db_select("select id,vare_id from variant_varer where variant_stregkode = '$fletvnr'",__FILE__ . " linje " . __LINE__))) {
			$qtxt="select varenr from varer where id = '$r[vare_id]'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$txt="Varenummer:$varenr sammenlægges med variant $fletvnr under varenr $r2[varenr]";#
			alert($txt);
			flet($id,$varenr,$r['id'],$r['varenr'],$fletvnr);
			exit;
		} else {
			$txt="Varenr $fletvnr ikke fundet";
			alert($txt);
			print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
			exit;
		}
	}	else {
		db_modify("update varer set varenr='$nyt_varenr' where id='$id'",__FILE__ . " linje " . __LINE__);
		$x=0;
		$q=db_select("select ordrelinjer.id as ordrelinje_id, ordrer.art as art, ordrer.ordrenr as ordrenr from ordrelinjer, ordrer where ordrer.status<3 and ordrelinjer.ordre_id = ordrer.id and ordrelinjer.vare_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			db_modify("update ordrelinjer set varenr='$nyt_varenr' where id='$r[ordrelinje_id]'",__FILE__ . " linje " . __LINE__);
			if ($x==1) echo "<tr><td>Varenummer rettet i f&oslash;lgende ordrer: $r[ordrenr]";
			else echo ", $r[ordrenr]";
		}
		if ($x>=1)echo "</td></tr><tr><td><hr></td></tr>";
		print "<BODY onLoad=\"javascript:alert('Varenummer er rettet fra $varenr til $nyt_varenr')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$id\">";

	}
} elseif ($nyt_varenr) {
	$txt="Varenummer: $varenr kan ikke sammenlægges med sig selv";
	alert($txt);
	print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";

}

if ($r=db_fetch_array(db_select("select varenr,stregkode from varer where id = '$id'",__FILE__ . " linje " . __LINE__))) {
	$varenr=$r['varenr'];
	$stregkode=$r['stregkode'];
}
print "<form name=ret_varenr action=ret_varenr.php method=post>"
;
print "<tr><td align=center>Varenummer rettes i alle uafsluttede ordrer, tilbud, indk&oslash;bsforslag og indk&oslash;bsordrer</td></tr>";
print "<tr><td align=center>Bem&aelig;rk at hvis der er brugere som er ved at redigere en ordre kan dette bevirke at varenummeret ikke &aelig;ndres</td></tr>";
print "<tr><td align=center>i den p&aring;g&aelig;ldende ordre. Det anbefales derfor at tilse at &oslash;vrige brugere lukker alle ordrevinduer.</td></tr>";
print "<tr><td align=center>&AElig;ndring af varenummer har ingen indflydelse p&aring; varestatestik eller andet, bortset fra at varen vil figurere</td></tr>";
print "<tr><td align=center>med det gamle varenummer i ordrer som er afsluttet f&oslash;r &aelig;ndringsdatoen.</td></tr>";
print "<tr><td align=center><hr></td></tr>";
print "<tr><td align=center>Det er også muligt at sammenlægger 2 varenumre til 1. Hef skal du skrive det varenummer som du vil lægge denne</td></tr>";
print "<tr><td align=center>ind i og sætter et lighedstegn foran, f.eks.: '=100' </td></tr>";
print "<tr><td align=center>Så vil al historik mm, varebeholdning og evt.leverandør og shop bindinger blive lagt sammen til 1 vare, og varenr $varenummer vil blive slettet</td></tr>";

print "<tr><td align=center><hr width=50%></td></tr>";
print "<tr><td align=center>Ret varenummer $varenr til: ";
print "<input type='text' name='nyt_varenr' pattern='^[a-zA-Z0-9=+._ -][a-zA-Z0-9+._ -]+' width='30' value='$varenr'></td></tr>";
print "<tr><td align=center>Tilladte tegn er: a-z A-Z 0-9 . + - _</td></tr>";
print "<input type=hidden name='id' value='$id'>";
print "<input type=hidden name='varenr' value=\"$varenr\">";
print "<input type=hidden name='stregkode' value=\"$stregkode\">";
print "<tr><td align=center><input type='submit' value='Ret' name=\"submit\"></td></tr>";
print "</form>";

print "</tbody></table";
print "</td></tr>\n";
print "</tbody></table";

function flet($id,$varenr,$flet_id,$flet_vnr,$stregkode){
// $id: ID for den vare som skal indgå i anden vare
// $varenr: Varenr for den vare som skal indgå i anden vare
// $flet_id: ID for den vare som denne vare skal indgå i.
// $flet_vnr: Varenr for den vare som denne vare skal indgå i.
// $stregkode: Stregkode for den variant som denne vare skal indgå i.

$fletbeholdning=0;

	$qtxt="update varer set beholdning = 0 where beholdning is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	if ($stregkode) { # if barcode is set the product is a variant.
		$qtxt="select id,vare_id from variant_varer where variant_stregkode='$stregkode'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$flet_variant_id=$r['id'];
		$flet_vare_id=$r['vare_id'];
		$qtxt="select id,shop_id,shop_variant from shop_varer where saldi_variant='$flet_variant_id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$shop_id=$r['shop_id'];
		transaktion('begin');
		print tekstboks("Varenummer: $varenr sammenlægges med $stregkode");
		$qtxt="select beholdning from varer where id = '$id'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['beholdning']) {
		$fletbeholdning=$r['beholdning'];
		$qtxt="update variant_varer set variant_beholdning=variant_beholdning+$fletbeholdning where id = '$flet_variant_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="update varer set beholdning=beholdning+$fletbeholdning where id = '$flet_vare_id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$qtxt="update batch_salg set vare_id = '$flet_vare_id',variant_id = '$flet_variant_id' where vare_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="update batch_kob set vare_id = '$flet_vare_id',variant_id = '$flet_variant_id' where vare_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="update ordrelinjer set vare_id = '$flet_vare_id', varenr = '$flet_vnr' where vare_id = '$id' and variant_id='0'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="update vare_lev set vare_id = '$flet_vare_id' where vare_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from lagerstatus where vare_id = '$flet_vare_id'  and variant_id = '0'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) $qtxt="update lagerstatus set beholdning=beholdning+$fletbeholdning where id = '$r[id]'";
	else $qtxt="insert into lagerstatus (vare_id,variant_id,lager,beholdning) values ('$flet_vare_id','0','1','$fletbeholdning')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="select id from lagerstatus where vare_id = '$flet_vare_id'  and variant_id = '$flet_variant_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) $qtxt="update lagerstatus set beholdning=beholdning+$fletbeholdning where id = '$r[id]'";
	else $qtxt="insert into lagerstatus (vare_id,variant_id,lager,beholdning) values ('$flet_vare_id','$flet_variant_id','1','$fletbeholdning')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt="delete from lagerstatus where vare_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	// Tjekker om der findes en shop relation for den vare som varen skal indgå i.
	$qtxt="select id from shop_varer where saldi_id = '$flet_vare_id' and saldi_variant = '$flet_variant_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
// Findes der ikke en shop relation ændres en eventuel relationen dit 'den nye' vare
	if (!$r['id']) {
		$qtxt="update shop_varer set saldi_id = '$flet_vare_id', saldi_variant = '$flet_variant_id' where saldi_id = '$id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$qtxt="delete from varer where id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$url="varekort.php?id=$flet_vare_id";
#xit;
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$url\">";
	exit;
}
$qtxt = "select id,shop_id from shop_varer where saldi_id = '$id'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { # Then the product has a shup relation
	$shop_id=$r['shop_id'];
	$r=db_fetch_array(db_select("select samlevare from varer where id = '$flet_id'",__FILE__ . " linje " . __LINE__));
	$fletsamlevare=$r['samlevare'];
	$qtxt = "select id,shop_id from shop_varer where saldi_id = '$flet_id'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		if ($shop_id!=$r['shop_id']) {
			print tekstboks("Varenummer: $varenr har en shop_relation til shop vare med id: $shop_id og $fletvnr relaterer til shop vare $r[shop_id]<br> Sammenlægning kan ikke gennemføres");
				print "<meta http-equiv=\"refresh\" content=\"2;URL=varekort.php?id=$id\">";
				exit;
			}
		}
	}
	transaktion('begin');
	print tekstboks("Varenummer: $varenr sammenlægges med $fletvnr");
	$r=db_fetch_array(db_select("select beholdning from varer where id = '$id'",__FILE__ . " linje " . __LINE__));
	if ($r['beholdning']) {
		$fletbeholdning=$r['beholdning'];
		if ($fletsamlevare) {
			$x=0;
			$q=db_select("select * from styklister where indgaar_i='$flet_id'");
			while ($r=db_fetch_array($q)) {
				$vare_id[$x]=$r['vare_id'];
				$antal[$x]=$r['antal'];
				$x++;
			}
			for ($x=0;$x<count($vare_id);$x++){
				$qtxt = "select gruppe,beholdning from varer where id='$vare_id[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$qtxt = "select box8 from grupper where art='VG' and kodenr='$r[gruppe]'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r2['box8']=='on') {
					$ny_beholdning=$r['beholdning']+($antal[$x]*$fletbeholdning);
					$qtxt = "update varer set beholdning = '$ny_beholdning' where id ='$vare_id[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}	else db_modify("update varer set beholdning=beholdning+$r[beholdning] where id = '$flet_id'",__FILE__ . " linje " . __LINE__);
	}
	db_modify("update batch_salg set vare_id = '$flet_id' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("update batch_kob set vare_id = '$flet_id' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("update ordrelinjer set vare_id = '$flet_id', varenr = '$flet_vnr' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	db_modify("update vare_lev set vare_id = '$flet_id' where vare_id = '$id'",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("select id from shop_varer where saldi_id = '$flet_id'",__FILE__ . " linje " . __LINE__));
	if (!$r['saldi_id']) db_modify("update shop_varer set saldi_id = '$flet_id' where saldi_id = '$id'",__FILE__ . " linje " . __LINE__);
	$i=0;
	$lsId = array();
	$qtxt = "select * from lagerstatus where vare_id = '$id' and beholdning != 0";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$lsId[$i]      = (int)$r['id'];
		$lsQty[$i]     = (float)$r['beholdning'];
		$lsStockNo[$i] = (int)$r['lager'];
		$lsVariant[$i] = (int)$r['variant_id'];
		$i++;
	}
	for ($i=0;$i<count($lsId);$i++) {
		$qtxt = "select id from lagerstatus where vare_id = '$flet_id' and lager = '$lsStockNo[$i]' ";
		$qtxt.= "and variant_id = '$lsVariant[$i]'";
echo "$qtxt<br>";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt = "update lagerstatus set beholdning = beholdning+$lsQty[$i] where id = '$r[id]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			$qtxt = "update lagerstatus set vare_id = '$flet_id' where id = '$lsId[$i]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	$qtxt="delete from lagerstatus where vare_id = '$id'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	db_modify("delete from varer where id = '$id'",__FILE__ . " linje " . __LINE__);
	transaktion('commit');
	print "<meta http-equiv=\"refresh\" content=\"0;URL=varekort.php?id=$flet_id\">";
}

?>
