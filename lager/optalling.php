<?php

// -- lager/optalling.php ------------------- patch 4.0.7 -- 2024-01-18 --
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20120913 Der kan nu optaelles til 0
// 20130109 Hele optællingen kan nu slettes - søg 20130109
// 20131119 Tilføjet variantvarer i importfunktion. Søg variant_id
// 20140103	db_escape_string indsat - Søg db_escape_string
// 20140103	Hvis der er 2 forskellige vnr som er ens med små bogstaver (Løsdel != løsdel) blev kun løsdel fundet. Søg 20140103
// 20140615 Ændret if ($lagertraek[$x]) til if ($lagerregulering[$x]) da varer som ikke blev bogført på lager ikke blev reguleret {# 20140615
// 20140626 Rettet datofunktion så datoen sættes tid sidste dato i aktivt regnskabsår, hvis aktivt regsnkabsår er får dd. 
// 20140717 Fejl i vareopslag hvis vare ikke eksisterer. Søg 20140717
// 20141211 Hvis optællingsdato = dd søges efter alle varer - også varer uden salgs-/kobsdate eller hvor datoen ligger i fremtiden.
// 20150103 Lid tilretninger med datoer og aut_lager. 
// 20150109	Antal blev skrevet i batch salg i stedet for i batck køb ven opregulering.
// 20160209	Omskrivning så lagre nu optælles enkelvis.
// 20160211 Fejl hvis kun 1 lager. 
// 20161005 En del rettelser i forb med flere lagre.
// 20161015 Flere rettelser i forb med flere lagre.
// 20161031 Opdatering af batch_kob_salg samt rettelse af fejl af søgestreng. # 20161031
// 20161031 Function opdat_behold - trækker nu antal fra batch_kob og batch_salg da der kan være fejl i lagerstatus.
// 20170915	Opdat behold satte negativt antal på forbrugsvarer.Søg 20170915
// 20180703 $reg_variant_id[$x] ændret fra $r['variant_id'] til $r['vare_id'].".".$r['variant_id'] 20180703
// 20180703 Hovedvare for varer med varianter fjernet fra batch kob & salg samt lagerstatus  20180703
// 20200629	PHR Speed optimizing.Look for 20200629 twice
// 20200905 PHR varenr not found wher seraching for barcode. Inserted: or stregkode = '$varenr'
// 20200905 PHR Updating api now witten to apilog and & removed from  exec command as call was interrupted?
// 20230224 CA  Case insensitive and 'like' search using % and _. Searching for _vd% gets DVD, Vd, dvd-player
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding
 
@session_start();
$s_id=session_id();

ini_set('max_execution_time', 1200);

$css="../css/standard.css";
$title="lageropt&aelig;lling";
$modulnr=15;
$lagernr=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="rapport.php";

$importer=if_isset($_GET['importer']);
$nulstil=if_isset($_GET['nulstil']);
$lager=if_isset($_GET['lager']);
if (isset($_POST['lager'])) $lager=$_POST['lager'];
if (!isset($lager)) $lager=1; #20160211

$slet=if_isset($_GET['slet']);
$vare_id=if_isset($_GET['vare_id']);
$variant_id=if_isset($_GET['variant_id']);
$varenr=strtolower(if_isset($_GET['varenr']));
$bogfor=if_isset($_GET['bogfor']);
$gentael=if_isset($_GET['gentael']);
$dato=if_isset($_GET['dato']);

db_modify("update varer set lukket = '' where lukket is NULL",__FILE__ . " linje " . __LINE__);
db_modify("update batch_kob set variant_id = '0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
db_modify("update batch_salg set variant_id = '0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);

if ($bogfor) {
	$qtxt="select box4 from grupper where art='API'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$api_fil=trim($r['box4']);
	$nulstil_ej_optalt=if_isset($_GET['nulstil_ej_optalt']);
	if (isset($_POST['nulstil_ej_optalt']) && $_POST['nulstil_ej_optalt']) $nulstil_ej_optalt=$_POST['nulstil_ej_optalt'];
	$dato=if_isset($_GET['dato']); 
	if (isset($_POST['dato'])) $dato=$_POST['dato'];
	$godkend_regdif=if_isset($_GET['godkend_regdif']);
} else $bogfor=0;
$vis_ej_optalt=if_isset($_GET['vis_ej_optalt']);
$vis_ej_exist=if_isset($_GET['vis_ej_exist']);

if ($slet && $vare_id && $varenr) {
	db_modify("delete from regulering where vare_id='$vare_id' and bogfort='0'",__FILE__ . " linje " . __LINE__);
#	print "<BODY onLoad=\"javascript:alert('Varenr: $varenr er slettet fra optællingslisten')\">\n";
	$vare_id=0;
} elseif ($nulstil) { #20130109
	db_modify("delete from regulering where bogfort='0' and lager='$lager'",__FILE__ . " linje " . __LINE__);
} else {
	$vare_id=if_isset($_POST['vare_id']);
	if (!$varenr) {
		$varenr=strtolower(db_escape_string(if_isset($_POST['varenr'])));
		if ($varenr) {
			$qtxt="select variant_varer.id from varer,variant_varer where lower(varer.varenr) like '$varenr' and variant_varer.vare_id=varer.id limit 1"; # 20230224
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				alert("varenr: $varenr indeholder varianter og kan kun reguleres på varekort eller gennem csv fil");  # 20230224
				$varenr=NULL;
			}
			if ($varenr) {
				$qtxt="select varenr from varer where lower(varenr) like '$varenr' or stregkode='$varenr'";
					if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $varenr=$r['varenr'];
				else {
					alert("varenr: $varenr ikke fundet");
					$varenr=NULL;
				}
			}
		}
	}
	$optalt=if_isset($_POST['optalt']);
	$beholdning=if_isset($_POST['beholdning']);
	$tidspkt=if_isset($_POST['tidspkt']);
	$dato=if_isset($_POST['dato']);

	
	
}
if (!$dato) $dato=if_isset($_POST['dato']);
if (!$dato) $dato=if_isset($_GET['dato']);
if (!$dato) { # 20141228
	$r=db_fetch_array(db_select("select max(tidspkt) as tidspkt from regulering where bogfort='0' and lager=$lager",__FILE__ . " linje " . __LINE__));
	if ($r['tidspkt']) {
		$yy=substr($r['tidspkt'],0,4);
		$mm=substr($r['tidspkt'],4,2);
		$dd=substr($r['tidspkt'],6,2);
		$dato="$dd-$mm-$yy";
	}
}
if (!$dato) { # 20140625
	$q = db_select("select * from grupper where art = 'RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$regnslut=$r['box4']."-".$r['box3']."-31";
		if(date("Y-m-d")>$regnslut) $dato=dkdato($regnslut);
		else $dato=date("d-m-Y");
	}
}
if ($dato) { # 20140625
	$tidspkt=str_replace("-","",usdate($dato))."235959";
}
$date=usdate($dato); # 20140625

$x=0;
$q=db_select("select * from grupper where art='LG' order by kodenr",__FILE__ . " linje " . __LINE__);
while($r=db_fetch_array($q)){
	$lagernr[$x]=$r['kodenr'];
	$lagernavn[$x]=$r['beskrivelse'];
	$x++;
}
$vnr=$varenr;
print "<table name=\"tabel_1\" width=\"100%\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1 ->
print "<tr><td width=\"100%\"><table name=\"tabel_1.1\" width=\"100%\" cellspacing=\"2\"  border=\"0\"><tbody>\n"; # tabel 1.1 ->
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>\n";
print "<td width=80% $top_bund>$title</td>\n";
print "<td width=10% $top_bund>";
($importer)? print "<a href=optalling.php>Afbryd</a>":print "<a href=optalling.php?importer=1&lager=$lager&dato=$dato>Importer</a>";
print "</a><br></td>\n";
print "</tbody></table name=\"tabel_1.1\"></td></tr>\n"; # <- tabel 1.1
if ($vis_ej_exist) $vis_ej_exist="<a href=\"../temp/$db/optael_ej_exist.txt\" target=\"blank\">Ikke oprettede varer</a>";
print "<tr><td>$vis_ej_exist<br></td></tr>\n";

if($importer) {
	importer($lager,$dato);
	exit;
}

#if(!db_fetch_array(db_select("select id from regulering where vare_id='$vare_id' and optalt='$optalt' and beholdning='$beholdning' and tidspkt='$tidspkt'",__FILE__ . " linje " . __LINE__))) {
if ($vare_id && ($optalt || $optalt=='0')) {
#	if ($optalt) { # remmet 20120913 saa det er muligt at optaelle til 0.
		$optalt=usdecimal($optalt,2);
		$beholdning*=1;
		$lager*=1;
		$variant_id*=1;
		$qtxt="select id from regulering where vare_id='$vare_id' and variant_id='$variant_id' and optalt='$optalt' and lager= '$lager' and beholdning='$beholdning' and tidspkt='$tidspkt' and bogfort='0'";
		if(!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt="insert into regulering (vare_id,variant_id,optalt,beholdning,bogfort,tidspkt,lager)";
			$qtxt.=" values ";
			$qtxt.="('$vare_id','$variant_id','$optalt','$beholdning','0','$tidspkt',$lager)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#		}
	}
	$varenr=NULL;
}
print "<tr><td align=\"center\" width=\"100%\"><table name=\"tabel_1.2\" width=\"800px\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1.2

if (!$lager) $lager=1;
/*
if (!$lager) {
	$r=db_fetch_array(db_select("select count(kodenr) as lagerantal from grupper where art='LG'",__FILE__ . " linje " . __LINE__));
	if ($lagerantal=$r['lagerantal']){
		print "<form name=\"optalling\" action=\"optalling.php\" method=\"post\">\n";
		print "<tr><td>Vælg lager<select name=\"lager\">";
		for ($i=1;$i<=$lagerantal;$i++) print "<option>$i</option>";
		print "</select>";
		print "<input type=\"submit\" value=\"OK\"></td></tr>";	
		print "</form>";
		exit;
	} else $lager=1;
}
*/

print "<form name=\"optalling\" action=\"optalling.php?gentael=$gentael&lager=$lager\" method=\"post\">\n";
if ($varenr=trim($varenr)) {
	$fokus="optalt";

	print "<tr><td>Varenr</td><td><!-- Lager --></td><td>Beskrivelse</td><td align=\"left\">Beholdning ($dato)</td><td align=\"right\">Ny beholdning</td></tr>\n";
	if (!$r=db_fetch_array(db_select("select * from varer where varenr='$varenr' or stregkode='$varenr'",__FILE__ . " linje " . __LINE__))) {
		$r=db_fetch_array(db_select("select * from varer where lower(varenr)='".strtolower($varenr)."' or lower(stregkode)='".strtolower($varenr)."' or upper(varenr)='".strtoupper($varenr)."' or upper(stregkode)='".strtoupper($varenr)."'",__FILE__ . " linje " . __LINE__));
	}
	if ($r['id']) { # 20140717 + 20161031
		# 20140625 ->
		db_modify("update batch_kob set lager='0' where lager is NULL",__FILE__ . " linje " . __LINE__); 
		db_modify("update batch_salg set lager='0' where lager is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("update lagerstatus set lager='0' where lager is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("update batch_kob set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__); 
		db_modify("update batch_salg set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		db_modify("update lagerstatus set variant_id='0' where variant_id is NULL",__FILE__ . " linje " . __LINE__);
		if ($date == date("Y-m-d")) { # 20141211
			$stmp="";
			$ktmp="";
		} else {
			$stmp="and salgsdate <= '$date'";
			$ktmp="and kobsdate <= '$date'";
		}

		$x=0;
		$qtxt="select kodenr from grupper where art='LG' order by kodenr";
		$q2=(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		while ($r2=db_fetch_array($q2)){
			$lagernr[$x]=$r['kodenr'];
			$x++;
		}
		if (!count($lagernr)) {
			db_modify("update batch_kob set lager='1' where lager != '1'",__FILE__ . " linje " . __LINE__);
			db_modify("update batch_salg set lager='1' where lager != '1'",__FILE__ . " linje " . __LINE__);
			db_modify("update lagerstatus set lager='1' where lager != '1'",__FILE__ . " linje " . __LINE__);
		} else if ($lager=='1') {
			db_modify("update batch_kob set lager='1' where lager = '0'",__FILE__ . " linje " . __LINE__);
			db_modify("update batch_salg set lager='1' where lager = '0'",__FILE__ . " linje " . __LINE__);
			db_modify("update lagerstatus set lager='1' where lager = '0'",__FILE__ . " linje " . __LINE__);
			$stmp.=" and lager = '1'";  
			$ktmp.=" and lager = '1'";
		} elseif ($lager) {
			$stmp.=" and lager='$lager'";
			$ktmp.=" and lager='$lager'";
		}
		$beholdning=0;
		$qtxt="select sum(antal) as antal from batch_kob where vare_id='$r[id]' $ktmp";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$beholdning+=$r2['antal'];
		$qtxt="select sum(antal) as antal from batch_salg where vare_id='$r[id]' $stmp";
		$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$beholdning-=$r2['antal'];
		
		$l=0;
		$q2=db_select("select id from lagerstatus where vare_id='$r[id]' and lager='$lager' order by id",__FILE__ . " linje " . __LINE__);
		while($r2=db_fetch_array($q2)) {
			$ls_id[$l]=$r2['id'];
			$l++; 
		}
		for($l=1;$l<count($ls_id);$l++) {
			db_modify("delete from lagerstatus where id = '$ls_id[$l]'",__FILE__ . " linje " . __LINE__);
		}
		if ($ls_id[0]) $qtxt="update lagerstatus set beholdning='$beholdning' where id='$ls_id[0]'";
		else $qtxt="insert into lagerstatus (vare_id,variant_id,beholdning,lager) values ('$r[id]','0','$beholdning','$lager')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		
		# <-20140625
	}
	
	print "<tr><td></td></tr>\n";
		
	$tmp=dkdecimal($beholdning*1,2); #20140103
	while(substr($tmp,-1)=='0') $tmp=substr($tmp,0,strlen($tmp)-1);
	if(substr($tmp,-1)==',') $tmp=substr($tmp,0,strlen($tmp)-1);
	print "<tr><td>$r[varenr]</td><td><!-- $lager --></td><td>$r[beskrivelse]<input type=\"hidden\" name=\"dato\" value=\"$dato\"></td>
	<td align=\"left\">$tmp</td><td align=\"right\"><input style=\"width:75px;text-align:right;\" type=\"text\" name=\"optalt\">\n";
	print "<input type=\"hidden\" name=\"varenr\" value='$r[varenr]'>\n";
	print "<input type=\"hidden\" name=\"lager\" value='$lager'>\n";
	print "<input type=\"hidden\" name=\"vare_id\" value='$r[id]'>\n";
	print "<input type=\"hidden\" name='tidspkt' value='".date('YmdHis')."'>\n";
	print "<input type=\"hidden\" name=\"beholdning\" value='$beholdning'></td></tr>\n";
} else {
	$fokus="varenr";
	print "<tr>
	<td align=\"center\">Dato</td><td align=\"center\"></td>";
	if (count($lagernr)) print "<td align=\"center\">Lager</td>";
	print "<td align=\"center\">Varenummer / Stregkode</td></tr>";
	print "<tr><td align=\"center\"><input style=\"width:100px;text-align:left;\" type=\"text\" name=\"dato\" value=\"".dkdato($date)."\"></td><td align=\"center\">";
	if (count($lagernr)) {
		print "<td align=\"center\"><select style=\"width:100px;text-align:left;\" name=\"lager\">";
		for ($i=0;$i<count($lagernr);$i++) {
			if ($lager==$lagernr[$i]) print "<option value=\"$lagernr[$i]\">$lagernr[$i]:$lagernavn[$i]</option>";
		}
		for ($i=0;$i<count($lagernr);$i++) {
			if ($lager!=$lagernr[$i]) print "<option value=\"$lagernr[$i]\">$lagernr[$i]:$lagernavn[$i]</option>";
		}
		print "</select></td>";	
	}
	
	$titletxt="Opslag tager ikke hensyn til store og små bogstaver. ";
	$titletxt.="Ønskes opslag på blot en del af varenummeret, så brug % for et antal vilkårlige tegn og _ for et enkelt vilkårligt tegn.";
	$titletxt.="Eksempel: d_d% vil finde dvd, Dvd-afspiller og Dadida.";
	print "<td align=\"center\" title=\"$titletxt\"><input style=\"width:300px;text-align:left;\" type=\"text\" name=\"varenr\"></td>\n";
}

print "</tr><tr><td colspan=\"5\" align=\"center\"><input type=\"submit\" value=\"OK\"></form>";
if ($varenr) print "<a style=\"text-decoration:none\" href=optalling.php?lager=$lager><input type=\"button\" value=\"Fortryd\"></a>";
print "</td></tr>\n";
print "</tbody></table  name=\"tabel_1.2\"></td></tr>\n"; # <- tabel 1.2
print "<tr><td align=\"center\" width=\"100%\"><hr></td></tr>";
print "<tr><td align=\"center\" width=\"100%\"><table name=\"tabel_1.3\" width=\"800px\" cellspacing=\"2\" border=\"0\"><tbody>\n"; # tabel 1.3 ->
if ($gentael) gentael($lager);
if ($bogfor) bogfor($lager,$nulstil_ej_optalt,$dato,$bogfor,$godkend_regdif);
elseif($vis_ej_optalt) {
$optalt=vis_ej_optalt($lager);
}else $optalt=vis_optalling($lager,$vnr,0);

if ($optalt>=1) {
	if (!$dato) $dato=date('d-m-Y'); # 20140625
	print "<form name=\"optalling\" action=\"optalling.php?bogfor=1&lager=$lager\" method=\"post\">\n";
	print "<td colspan=\"7\">Dato for opt&aelig;lling</td><td><input type=\"text\" name=\"dato\" value=\"$dato\"></td></tr>\n";
	print "<td colspan=\"7\">Sæt beholdning til 0 for alle ikke optalte varer på lager $lager</td><td><input type=\"checkbox\" name=\"nulstil_ej_optalt\"></td></tr>\n";
	print "<td colspan=\"8\"><input style=\"width:100%;\" type=submit value=Bogf&oslash;r></form></td></tr>\n";
}
########################################################################################
print "</tbody></table name=\"tabel_1.3\"></td></tr>\n"; # <- tabel 1.3
print "</tbody></table name=\"tabel_1\"></td></tr>\n"; # <- tabel 1
if ($fokus) {
	print "<script language=\"javascript\">\n";
	print "document.optalling.$fokus.focus();\n";
	print "</script>\n";
}


function vis_optalling($lager,$vnr,$gentael) {
	global $bgcolor,$bgcolor2;
	global $regnaar;
	global $dato;


	$behold=$kpris=$lagervalue=$opt=$vareantal=0;
	$x=0;
	
#pdat_behold();
	if ($lager<=1) db_modify("update lagerstatus set lager = '1' where lager < '1'",__FILE__ . " linje " . __LINE__);	
#	return (NULL);
	$vare_id[0]=NULL;
	if ($vnr) $qtxt="select varer.id,varer.varenr,varer.beskrivelse,varer.kostpris,regulering.beholdning,regulering.tidspkt,regulering.optalt,regulering.variant_id,regulering.lager from regulering,varer where varer.varenr='$vnr' and varer.id=regulering.vare_id and regulering.lager='$lager' and varer.id=regulering.vare_id and regulering.bogfort = '0' order by regulering.tidspkt";
	else $qtxt="select varer.id,varer.varenr,varer.beskrivelse,varer.kostpris,regulering.beholdning,regulering.tidspkt,regulering.optalt,regulering.variant_id,regulering.lager from regulering,varer where varer.id=regulering.vare_id and regulering.lager='$lager' and regulering.bogfort = '0' order by varer.varenr,regulering.tidspkt";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$beholddiff[$x]=0;
		if ($vnr && $x && $vare_id[$x]==$r['id'] && $variant_id[$x] && $variant_id[$x]==$r['variant_id']) {
			$optalt[$x]+=$r['optalt'];
		} else {
			$x++;
			$vare_id[$x]=$r['id'];	
			$variant_id[$x]=$r['variant_id']*1;
			$varenr[$x]=$r['varenr'];
			$kostpris[$x]=$r['kostpris'];
			$beskrivelse[$x]=$r['beskrivelse'];
			$optalt[$x]=$r['optalt'];
			$tidspkt[$x]=$r['tidspkt'];
			$variant_id[$x]=$r['variant_id']*1;
			$variant_stregkode[$x]=NULL;
			$beholdning[$x]=$r['beholdning']*1;
			#if ($variant_id[$x]) 
			$qtxt="select id,beholdning from lagerstatus where vare_id = '".$vare_id[$x]."' and variant_id = '".$variant_id[$x]."' and lager='$lager'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r2['id']) {
				$beholdning[$x]=$r2['beholdning']*1;
			} else {
				$qtxt="insert into lagerstatus (vare_id,variant_id,lager) values ('$vare_id[$x]', '$variant_id[$x]', '$lager')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
			if ($variant_id[$x]) {
				$r2=db_fetch_array(db_select("select variant_stregkode from variant_varer where id = '".$variant_id[$x]."'",__FILE__ . " linje " . __LINE__));
				$variant_stregkode[$x]=$r2['variant_stregkode'];
			}
		}
	}	
	$antal=$x;
	$x=0;
	$q=db_select("select id,variant_stregkode from variant_varer order by id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$var_id[$x]=$r['id'];
		$var_streg[$x]=$r['variant_stregkode'];
		$x++;
	}
for ($x=1;$x<=$antal;$x++) {
}
	for ($x=1;$x<=$antal;$x++) {
		$baggrund=$bgcolor2;
		if ($vare_id[$x] != $vare_id[$x-1]) print "<tr bgcolor=\"$baggrund\"><td>Varenr/Stregkode</td><td>Beskrivelse</td><td>Lager</td><td align=\"center\">Optalt&nbsp;dato</td><td align=\"right\">Beholdning</td><td align=\"right\">Optalt&nbsp;ant.</td><td align=\"right\">Kostpris</td><td align=\"right\">Lagerv&aelig;rdi</td><td align=\"right\">Lagerv&aelig;rdi&nbsp;sum</td><tr>\n";
		($baggrund==$bgcolor)? $baggrund=$bgcolor2:$baggrund=$bgcolor;
		$y=0;
		$sum=0;
		if ($optalt[$x]) {
#			while($optalt[$x]) {
				$aar=substr($tidspkt[$x],0,4);	
				$md=substr($tidspkt[$x],4,2);
				$dag=substr($tidspkt[$x],6,2);
				$time=substr($tidspkt[$x],8,2);
				$minut=substr($tidspkt[$x],10,2);
				$sum+=$optalt[$x];
				if ($vare_id[$x] != $vare_id[$x-1]) {
					if ($variant_stregkode[$x]) {
						print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td><b>".$lager."</b></td><td align=\"center\"><br></td><td align=\"right\"><br></td><td align=\"right\"><br></td><td colspan=\"4\"><br></td><tr>\n";
						print "<tr bgcolor=\"$baggrund\"><td>".$variant_stregkode[$x]."</td><td><br></td><td>".$lager."</td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x],2)."</td><td align=\"right\">".dkdecimal($optalt[$x])."</td><tr>\n";
					} else {
						print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td><b>".$lager."</b></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x],2)."</td><td align=\"right\">".dkdecimal($optalt[$x],2)."</td><td colspan=\"4\" align=\"right\" title=\"Klik her for at slette denne vare fra opt&aelig;llingen.\"><a style=\"text-decoration:none\" href=\"optalling.php?vare_id=$vare_id[$x]&varenr=$varenr[$x]&slet=y&lager=$lager&gentael=$gentael\" onclick=\"return confirm('Vil du slette denne vare fra liste og opt&aelig;lle den igen?')\"><font color=\"#ff0000\"><b>X</b></font></a></td><tr>\n";
					}
					$kpris=$kostpris[$x];
					$behold=$beholdning[$x];
					$opt=$optalt[$x];
				} else {
					print "<tr bgcolor=\"$baggrund\"><td>".$variant_stregkode[$x]."</td><td><br></td><td>".$lager."<td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x],2)."</td><td align=\"right\">".dkdecimal($optalt[$x])."</td><tr>\n";
						$kpris+=$kostpris[$x];
						$behold+=$beholdning[$x];
						$opt+=$optalt[$x];
				}
#				$y++;
#			}
			$kostsum=$kostpris[$x]*$sum;
			$lagervalue+=$kostsum;		
			if ($vare_id[$x] != $vare_id[$x+1]) print "<tr bgcolor=\"$baggrund\"><td><b>Optalt ialt</b></td><td><br></td><td><br></td><td><br></td><td align=\"right\"><b>".dkdecimal($behold,2)."</b></td><td align=\"right\"><b>".dkdecimal($opt,2)."</b></td><td align=\"right\"><b>".dkdecimal($kpris,2)."</b></td><td align=\"right\"><b>".dkdecimal($kostsum,2)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue,2)."</b></td><tr>\n";
#			$x++;
		} else {
			$aar=substr($tidspkt[$x],0,4);	
			$md=substr($tidspkt[$x],4,2);
			$dag=substr($tidspkt[$x],6,2);
			$time=substr($tidspkt[$x],8,2);
			$minut=substr($tidspkt[$x],10,2);
			$kostsum=$kostpris[$x]*$optalt[$x];
			$lagervalue+=$kostsum;
			print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td><b>".$lager."</b></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\"><b>".dkdecimal($beholdning[$x],2)."</b></td><td align=\"right\"><b>".dkdecimal($optalt[$x],2)."</b></td><td align=\"right\"><b>".dkdecimal($kostpris[$x],2)."</b></td><td align=\"right\"><b>".dkdecimal($kostsum,2)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue,2)."</b></td><td title=\"Klik her for at slette denne vare fra opt&aelig;llingen.\"><a  style=\"text-decoration:none\" href=\"optalling.php?vare_id=$vare_id[$x]&varenr=$varenr[$x]&slet=y&lager=$lager\" onclick=\"return confirm('Vil du slette denne vare fra liste og opt&aelig;lle den igen?')\"><font color=\"#ff0000\"><b>X</b></font></a></td><tr>\n";
		}
#		if (!$vnr) print "<tr><td colspan=\"8\"><hr></td></tr>";
	}
	$gruppe = NULL;
	$qtxt = "select kodenr from grupper where box8='on' and art='VG' and fiscal_year = '$regnaar' order by kodenr";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$tmp=$r['kodenr']*1;
		if ($gruppe) $gruppe.=" or gruppe = '".$tmp."'";
		else $gruppe="gruppe = '".$tmp."'";
	}
		if (!$vnr) {
		print "<tr><td colspan=\"8\"><hr></td></tr>";
		$vareantal=0;
		if ($gruppe) $r=db_fetch_array(db_select("select count(id) as antal from varer where ($gruppe) and lukket != 'on'",__FILE__ . " linje " . __LINE__));
		$vareantal+=$r['antal'];
#		if ($antal) {	
			print "<tr><td colspan=\"8\">";
			if ($antal) print "Optalt ialt $antal varer udfra en samlet vareliste på $vareantal lagerf&oslash;rte varer.";
			print "Klik <a href=optalling.php?vis_ej_optalt=1&dato=$dato&lager=$lager>her</a> for liste over ikke optalte varer";
			print "<td></tr>"; 
			print "<tr><td colspan=\"8\">";
			#21130109 - linjen herunder
			if ($antal) print "Klik <a href=\"optalling.php?nulstil=1&lager=$lager&dato=$dato\" onclick=\"return confirm('Vi du nulstille hele optællingen?')\">her</a> for at \"0-stille\" listen";
			print "<td></tr>"; 
#		}
	}
	print "<tr><td colspan=\"8\"><hr></td></tr>";
	return ($antal);
#	return($x);
} # vis_optalling
#######################################################################################################
function vis_ej_optalt($lager) {
	global $bgcolor;
	global $bgcolor2;
	global $dato; # 20140625
	
	$date=usdate($dato); # 20140625

	$optalt=array();
	$x=0;
	$q=db_select("select vare_id from regulering where bogfort='0'",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$x++;
		$optalt[$x]=$r['vare_id'];
	}
	$gruppe = NULL;
	$q=db_select("select kodenr from grupper where box8='on' and art='VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($gruppe) $gruppe.=" or gruppe = '".$r['kodenr']."'";
		else $gruppe="gruppe = '".$r['kodenr']."'";
	}
	if ($gruppe) { #20140625
		print "<tr bgcolor=\"$baggrund\"><td colspan=\"7\" align=\"center\"><b><big>----- Ikke optalte varer pr $dato -----</b></big></td><tr>\n";
		print "<tr bgcolor=\"$baggrund\"><td>Varenr</td><td>Beskrivelse</td><td align=\"right\">Beholdning</td><td align=\"right\">Kostpris</td><td align=\"right\">Lagerv&aelig;rdi</td><td align=\"right\">Lagerv&aelig;rdi&nbsp;sum</td><tr>\n";

		$q=db_select("select * from varer where ($gruppe) and lukket != 'on' order by varenr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$beholdning=0;
			$r2=db_fetch_array($q2=db_select("select sum(antal) as antal from batch_salg where vare_id='$r[id]' and salgsdate <= '$date' and lager='$lager'",__FILE__ . " linje " . __LINE__));
			$beholdning-=$r2['antal'];
			$r2=db_fetch_array($q2=db_select("select sum(antal) as antal from batch_kob where vare_id='$r[id]' and kobsdate <= '$date' and lager='$lager'",__FILE__ . " linje " . __LINE__));
			$beholdning+=$r2['antal'];
			if (!in_array($r['id'],$optalt)) {
				$kostsum=$beholdning*$r['kostpris'];
				$lagervalue+=$kostsum;
				($baggrund==$bgcolor)? $baggrund=$bgcolor2:$baggrund=$bgcolor;
				print "<tr bgcolor=\"$baggrund\"><td title=\"Klik her for at opt&aelig;lle denne vare.\"><b><a href=\"optalling.php?varenr=$r[varenr]&dato=$dato\">$r[varenr]</a></b></td><td><b>$r[beskrivelse]</b></td><td align=\"right\"><b>".dkdecimal($beholdning,2)."</b></td><td align=\"right\"><b>".dkdecimal($r['kostpris'],2)."</b></td><td align=\"right\"><b>".dkdecimal($kostsum,2)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue,2)."</b></td><tr>\n";
			}
		}
	} else { #20140625
		print "<BODY onLoad=\"javascript:alert('Ingen lagerførte varer.')\">\n";
		return(0);
	}
	$x=count($optalt);
	return($x);
} # vis_ej_optalt()
###########################################################################################################
function bogfor($lager,$nulstil_ej_optalt,$dato,$bogfor,$godkend_regdif) {
	global $bruger_id,$db,$regnaar;
	
	$r=db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
	$startaar=$r['box2']*1;
	($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;
	$aut_lager='on';
	
	if ($bogfor<2) {
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\">Sikkerhedskopier dit regnskab inden du bogfører og kontroller efterfølgende</td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\">Oplever du at noget ikke stemmer så ring straks på mobil 2066 9860</td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\"><hr width=\"80%\"></td></tr>\n";
	}

	$transdate=usdate($dato);
	$dato=dkdato($transdate);
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$tidspkt=date('YmdHis');

#xit;	
#	$bogfor=1;
	$x=0;
	$q=db_select("select * from grupper where box8='on' and art='VG' and fiscal_year = '$regnaar' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$gruppe[$x]=$r['kodenr'];
		$lagertraek[$x]=$r['box2'];
		$lagerregulering[$x]=$r['box5'];
#		if ($lagertraek[$x] && !$lagerregulering[$x]) {
#			echo "konto for lagerregulering ikke sat for varegruppe $gruppe[$x]<br>";
#			$bogfor=0;
#			return ("konto for lagerregulering ikke sat for varegruppe $gruppe[$x]");
#		}
		$x++;
	}
	$y=0;
	$x=-1;
	$reg_vare_id=array();
	$reg_diff=array();
	$reguleres=array();
	$reg_variant_id=array();
	$qtxt="select * from regulering where bogfort='0' and lager='$lager' order by vare_id,variant_id,id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if (!in_array($r['vare_id'],$reg_vare_id) || !in_array($r['variant_id'],$reg_variant_id)) {
			$x++;
			$reg_vare_id[$x]=$r['vare_id'];
			($r['variant_id'])?$reg_variant_id[$x]=$r['vare_id'].".".$r['variant_id']:$reg_variant_id[$x]=NULL; #20180703
			$id=$reg_vare_id[$x];			
			if($reg_variant_id[$x]) $id=$reg_variant_id[$x];
			$reg_id[$x]=$id;
			$reg_diff[$id]=0;
			$gl_beholdning[$id]=$r['beholdning'];
			$ny_beholdning[$id]=$r['optalt'];
		} else {
			$ny_beholdning[$id]+=$r['optalt'];
			if ($godkend_regdif) {
				$gl_beholdning[$id]=$r['beholdning'];
			} elseif ($r['beholdning']!=$gl_beholdning[$id]) {
				$y++;
				$reg_diff[$id]=1;
			}
		}
		$reguleres[$id]=$ny_beholdning[$id]-$gl_beholdning[$id];
	}
#xit;	
	if ($y) {
		print "<tr><td colspan=\"8\" align=\"center\"><b><big>Følgende varer har ændret antal under optællingen og skal optælles igen.</big></b></td></tr>";
		print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
		for ($x=0;$x<count($reg_vare_id);$x++) {
			$id=$reg_vare_id[$x];
			if ($reg_diff[$id]) {
				if (is_integer($id)) {
					$r=db_fetch_array(db_select("select varenr from varer where id='$id'",__FILE__ . " linje " . __LINE__));
					vis_optalling($lager,$r['varenr'],1);
				} else {
					list($a,$b)=explode(".",$id);
					$r=db_fetch_array(db_select("select variant_stregkode as stregkode from variant_varer where id='$b'",__FILE__ . " linje " . __LINE__));
					vis_optalling($lager,$r['stregkode'],1);
				}
			}
		}
		print "<tr><td colspan=8>Klik <a href=optalling.php?nulstil_ej_optalt=$nulstil_ej_optalt&dato=$dato&bogfor=$bogfor&lager=$lager&godkend_regdif=1>her</a> for at acceptere beholdning fra seneste optælling og regulere i forhold til dette</td></tr>";
		print "<script language=\"javascript\">\n";
		print "document.optalling.optalt.focus();\n";
		print "</script>\n";
		exit;
	}
	print "<tr><td colspan=\"8\" align=\"center\">Røde linjer er ikke optalte og reguleres til 0, grønne reguleres i henhold til optælling.</td></tr>";
	print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
	print "<tr><td></td><td></td><td align=\"center\">Regulering</td><td align=\"center\">Regulering</td><td align=\"center\">Summeret</td></tr>";
	print "<tr><td>Varenr</td><td>Beskrivelse</td><td align=\"center\">antal</td><td align=\"center\">kostpris</td><td align=\"center\">regulering</td></tr>";
	$reguleret=0;
	transaktion('begin');
	$regulering=0;
	for($x=0;$x<count($gruppe);$x++) {
		if ($x>=1) {
			print "<tr><td colspan=\"4\"><b><big>I alt reguleres for varegruppe ".$gruppe[$x-1]."</big></b></td><td align=\"right\"><b><big>".dkdecimal($regulering,2)."</big></b></td></tr>";
			print "<tr><td colspan=\"5\"><hr></td></tr>";
		}
		$regulering=0;
		print "<tr><td colspan=\"4\"><b><big>Vargruppe $gruppe[$x]</big></b></td></tr>";
		$vare_id=array();
		$variant_id=array();
		$tjek=0;
		$v=0;
/*		
		$qtxt="select varer.id as vare_id,varer.varenr,varer.kostpris,varer.beskrivelse,varer.beholdning,";
		$qtxt.="variant_varer.id as variant_id,variant_varer.variant_stregkode as stregkode";
		$qtxt.=" from varer,variant_varer ";
		$qtxt.="where varer.gruppe='$gruppe[$x]' and varer.lukket != 'on' ";
		$qtxt.="and ((varer.varianter!='' and variant_varer.vare_id = varer.id) or varer.varianter!='') ";
		$qtxt.="order by varer.varenr,variant_varer.variant_stregkode";
*/		
		$qtxt="select * from varer where gruppe='$gruppe[$x]' and lukket != 'on' order by varenr";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
		if ($nulstil_ej_optalt || in_array($r['id'],$reg_vare_id)) { #20200629
				$z=0;
				$qtxt="select * from variant_varer where vare_id='$r[id]' order by variant_stregkode";
				$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while($r2=db_fetch_array($q2)) {
					$variant_id[$v]=$r2['id'];
					$vare_id[$v]=$r['id'];
					$beholdning[$v]=$r2['variant_beholdning'];
					$stregkode[$v]=$r2['variant_stregkode'];
					$beskrivelse[$v]=$r['beskrivelse'];
					$varegruppe[$v]=$r['gruppe'];
					$varenr[$v]=$r['varenr'];
					$kostpris[$v]=$r['kostpris']*1;
					$v++;
					$z++;
				}
				if ($z==0) {
					$variant_id[$v]=0;
					$vare_id[$v]=$r['id'];
					$beholdning[$v]=$r['beholdning'];
					$beskrivelse[$v]=$r['beskrivelse'];
					$varegruppe[$v]=$r['gruppe'];
					$stregkode[$v]=NULL;
					$varenr[$v]=$r['varenr'];
					$kostpris[$v]=$r['kostpris']*1;
					$v++;
				}
			}
		}
		for ($v=0;$v<count($vare_id);$v++) {
			$id=$vare_id[$v];
			if ($variant_id[$v]) $id.=".".$variant_id[$v];
			$kostdiff=0;
			if (!isset($reg_diff[$id])) $reg_diff[$id]=0;
			if (!$reg_diff[$id]) {
				$qtxt="select sum(antal) as beholdning from batch_kob where vare_id='$vare_id[$v]' and variant_id='$variant_id[$v]' and lager = '$lager' and kobsdate <= '$transdate'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$gl_beholdning[$id]=$r2['beholdning'];
				$qtxt="select sum(antal) as beholdning from batch_salg where vare_id='$vare_id[$v]' and variant_id='$variant_id[$v]' and lager = '$lager' and salgsdate <= '$transdate'";
				$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$gl_beholdning[$id]-=$r2['beholdning'];
				$gl_kostsum=$beholdning[$v]*$kostpris[$v];
				$bgcolor="#ffffff";
#				if ($lagerregulering[$x]) { # 20140615
				if (in_array($id,$reg_id) || in_array($id,$reg_variant_id)) { 
					if ($reguleres[$id]) {
							$bgcolor="#00ff00";
							$ny_kostsum=$gl_kostsum+$reguleres[$id]*$kostpris[$v];
							$ny_beholdning[$id]=$gl_beholdning[$id]+$reguleres[$id];
							$kostdiff=$ny_kostsum-$gl_kostsum;
							$regulering+=$kostdiff;
						}
						if ($bogfor>1) {
/*
						if (is_integer($id)) {
							 $a=$id;
							 $b=0;
							}else list($a,$b)=explode(".",$id);
*/
							$qtxt="update regulering set bogfort = '1' where bogfort = '0' and vare_id = '$vare_id[$v]' and variant_id='$variant_id[$v]'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#							} else {
						}
					} elseif ($nulstil_ej_optalt && $gl_beholdning[$id]) {
						$bgcolor="#ff0000";
#						$qtxt="select sum(antal) as antal from batch_kob where vare_id='$id' and kobsdate > '$transdate' and lager='$lager'";
#						$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#						$gl_beholdning[$id]=$gl_beholdning[$id]-$r2['antal']*1;
#						$qtxt="select sum(antal) as antal from batch_salg where vare_id='$id' and salgsdate > '$transdate' and lager='$lager'";
#						$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
#						$gl_beholdning[$id]=$gl_beholdning[$id]+$r2['antal']*1;
						$gl_kostsum=$gl_beholdning[$id]*$kostpris[$v];
						$ny_kostsum=0;
						$ny_beholdning[$id]=0;
						$reguleres[$id]=$gl_beholdning[$id]*-1;
						$kostdiff=$ny_kostsum-$gl_kostsum;
						$regulering+=$kostdiff;
						if ($bogfor>1) {
							db_modify("insert into regulering (vare_id,variant_id,optalt,beholdning,bogfort,tidspkt,lager) values ('$vare_id[$v]','$variant_id[$v]','0','$gl_beholdning[$id]','1','$tidspkt','$lager')",__FILE__ . " linje " . __LINE__);
#						} else {
#							cho "<tr><td colspan=6>insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt) values ('$id','0','$gl_beholdning[$id]','1','$tidspkt')</td></tr>";
						}
					}
					if (isset($reguleres[$id]) && $reguleres[$id]) {
						($stregkode[$v])?$tmp=$stregkode[$v]:$tmp=$varenr[$v];
						print "<tr bgcolor=\"$bgcolor\"><td>$tmp $varegruppe[$v]</td><td>$beskrivelse[$v]</td><td align=\"right\">".dkdecimal($reguleres[$id],2)."</td><td align=\"right\">".dkdecimal($kostdiff,2)."</td><td align=\"right\">".dkdecimal($regulering,2)."</td></tr>";
						if ($bogfor>1) { #20150109			
							$reguleres[0]=$reguleres[$id]*-1;
/*
							$ny_beholdning[$id]*=1;
							if (is_integer($id)) {
							 $a=$id;
							 $b=0;
							}else list($a,$b)=explode(".",$id);
*/							 
							$qtxt="select id from lagerstatus where vare_id = '$vare_id[$v]' and variant_id = '$variant_id[$v]' and lager = '$lager'";
							$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
							if ($r['id']) $qtxt="update lagerstatus set beholdning = '$ny_beholdning[$id]' where id = '$r[id]'";
							elseif ($ny_beholdning[$id]) $qtxt="insert into lagerstatus (vare_id,variant_id,lager,beholdning) values ('$vare_id[$v]','$variant_id[$v]','$lager','$ny_beholdning[$id]')";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							if ($reguleres[0] < 0) {
								$tmp=$reguleres[0]*-1;
								$qtxt="insert into batch_kob(vare_id,linje_id,kobsdate,fakturadate,ordre_id,antal,pris,rest,lager,variant_id)";
								$qtxt.="values";
								$qtxt.="($vare_id[$v],'0','$transdate','$transdate','0','$tmp','$kostpris[$v]','$tmp','$lager','$variant_id[$v]')";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							} else {
								$y=0;
								$restsum=0;
								$qtxt="select id,rest from batch_kob where vare_id='$vare_id[$v]' and variant_id='$variant_id[$v]'  and kobsdate <= '$transdate' and rest > '0' and lager='$lager' ";
								$qtxt.="order by kobsdate";
								$q2=db_select($qtxt,__FILE__ . " linje " . __LINE__);
								while($r2=db_fetch_array($q2)) {
									$bk_id[$y]=$r2['id'];
									$bk_rest[$y]=$r2['rest'];
									$restsum+=$bk_rest[$y];
									$y++;
								}
								$y=0;
								while($y<count($bk_id) && $reguleres[0] && $restsum) { 
									if ($reguleres[0]<=$bk_rest[$y]) {
										$bk_rest[$y]-=$reguleres[0];
										$qtxt="update batch_kob set rest = '$bk_rest[$y]' where id = '$bk_id[$y]'";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$qtxt="insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,";
										$qtxt.="lager,variant_id)";
										$qtxt.="values";
										$qtxt.="('$bk_id[$y]','$vare_id[$v]','0','$transdate','$transdate','0',$reguleres[0],'$kostpris[$v]','1',";
										$qtxt.="'$lager','$variant_id[$v]')";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$restsum-=$reguleres[0];
										$reguleres[0]=0;
									} else {
										$qtxt="update batch_kob set rest = '0' where id = '$bk_id[$y]'";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$qtxt="insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager,variant_id)";
										$qtxt.="values";
										$qtxt.="('$bk_id[$y]','$vare_id[$v]','0','$transdate','$transdate','0',$bk_rest[$y],'$kostpris[$v]','1','$lager','$variant_id[$v]')";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$restsum-=$bk_rest[$y];
										$reguleres[0]-=$bk_rest[$y];
										$bk_rest[$y]=0;
										}
									$y++;
								}
								
								if ($reguleres[0]) {
									$lager*=1;
#									db_modify("insert into batch_kob(vare_id,linje_id,kobsdate,fakturadate,ordre_id, antal,rest,pris,lager) 
#										values 
#										('$vare_id[$v]','0','$transdate','$transdate','0','0','$reguleres[0]','$kostpris','$lager[$id]')",__FILE__ . " linje " . __LINE__);
#									$r2=db_fetch_array(db_select("select max(id) as id from batch_kob where vare_id='$id' and linje_id=0",__FILE__ . " linje " . __LINE__));
									$qtxt="insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate,fakturadate, ordre_id, antal, pris, lev_nr,lager,variant_id)"; 
									$qtxt.="values"; 
									$qtxt.="('0', '$vare_id[$v]', '0', '$transdate', '$transdate', '0', '$reguleres[0]', '$kostpris[$v]', '1','$lager','$variant_id[$v]')";
									db_modify($qtxt,__FILE__ . " linje " . __LINE__);
								}
								
							}
						}
					}
#				} else print "<BODY onLoad=\"javascript:alert('Manglende kontonummer for lagerregulering (Varegruppe $gruppe[$x])')\">\n";
			}
		}
		if (!$aut_lager) {
			if ($lagertraek[$x] && $bogfor>1) {
				if ($regulering < 0) {
					$regulering*=-1;
					$qtxt="insert into transaktioner (kontonr,bilag,transdate,logdate,logtime,beskrivelse,";
					$qtxt.="debet,faktura,kladde_id,afd,ansat,projekt,valuta,valutakurs, ordre_id)";
					$qtxt.="values";
					$qtxt.="($lagerregulering[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling, lager $lager ($brugernavn)',";
					$qtxt.="'$regulering', '', '0', '0', '0', '0', '1', '100', '0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse,";
					$qtxt.="kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)";
					$qtxt.="values";
					$qtxt.="($lagertraek[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling, lager $lager ($brugernavn)',";
					$qtxt.="'$regulering', '', '0', '0', '0', '0', '1', '100', '0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} elseif ($regulering > 0) {
					$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse,";
					$qtxt.="kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)";
					$qtxt.="values";
					$qtxt.="($lagerregulering[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling, lager $lager ($brugernavn)',";
					$qtxt.="'$regulering', '', '0', '0', '0', '0', '1', '100', '0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse,";
					$qtxt.="debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)";
					$qtxt.="values";
					$qtxt.="($lagertraek[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling, lager $lager ($brugernavn)',";
					$qtxt.="'$regulering', '', '0', '0', '0', '0', '1', '100', '0')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			} elseif ($lagertraek[$x]) {
				if ($regulering < 0) {
					$regulering*=-1;
					print "<tr><td colspan=\"6\">Konto $lagerregulering[$x] debiteres kr. ".dkdecimal($regulering,2)." som krediteres på konto $lagertraek[$x] pr. ".dkdato($transdate)."</td></tr>";
				} else {
					print "<tr><td colspan=\"6\">Konto $lagertraek[$x] debiteres kr. ".dkdecimal($regulering,2)." som krediteres på konto $lagerregulering[$x] pr. ".dkdato($transdate)."</td></tr>";
				}
			}
		}
	}

	
	$x=0;
	$reg_variant_id=array();
	$reg_diff=array();
	$reguleres=array();
	$qtxt="select * from regulering where bogfort='0' and variant_id > '0' and lager='$lager' order by variant_id,id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if (!in_array($r['variant_id'],$reg_variant_id)) {
			$x++;
			$reg_variant_id[$x]=$r['variant_id'];
			$id=$reg_variant_id[$x];
			$reg_diff[$id]=0;
			$gl_beholdning[$id]=$r['beholdning'];
			$ny_beholdning[$id]=$r['optalt'];
		} else {
			$ny_beholdning[$id]+=$r['optalt'];
			if ($godkend_regdif) {
				$gl_beholdning[$id]=$r['beholdning'];
			} elseif ($r['beholdning']!=$gl_beholdning[$id]) {
				$y++;
				$reg_diff[$id]=1;
			}
		}
		$reguleres[$id]=$ny_beholdning[$id]-$gl_beholdning[$id];
	}
	$reg_antal=$x;
	for ($x=0;$x<count($reg_vare_id);$x++){
			if (isset($reg_variant_id[$x])) {
			$id=$reg_variant_id[$x];
			if ($reguleres[$id]) {
				if ($bogfor>1) {
					$qtxt="update variant_varer set variant_beholdning = '$ny_beholdning[$id]' where id = $reg_variant_id[$x]";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt="update regulering set bogfort = '1' where bogfort = '0' and variant_id = '$id' and lager='$lager'";	
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
#exit;	
	 if ($bogfor>1) opdat_behold($reg_vare_id); # $reg_vare_id ADDED 20200629
	 transaktion('commit');
	if ($bogfor==1) print "<tr><td colspan=\"6\">Klik <a href=optalling.php?bogfor=2&nulstil_ej_optalt=$nulstil_ej_optalt&dato=$dato&lager=$lager&godkend_regdif=$godkend_regdif>her</a> for endelig lagerregulering pr. $dato</td></tr>";
	# else print "<tr><td colspan=\"6\">Lagerregulering udført.</td></tr>";
	else {
		print "<BODY onLoad=\"javascript:alert('Lagerregulering udført.')\">\n";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php?dato=$dato&lager=$lager\">";
	}
} # endfunc bogfor

########################################################################################################################
function opdat_behold($reg_vare_id) {
	global $api_fil,$db,$nulstil_ej_optalt;
	echo "opdaterer beholdning<br>";
	
	$x=0; # hele select 20170915 
	$qtxt="select kodenr from grupper where box8 = 'on' order by kodenr"; 
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$v_grp[$x]=$r['kodenr'];
		$x++;		
	}
	$x=0;
	$lager[0]='1';
	$qtxt="select kodenr from grupper where art = 'LG' order by kodenr"; 
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$lager[$x]=$r['kodenr'];
		$x++;		
	}
	$x=0;
	$q=db_select("select id,beholdning,gruppe from varer order by id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($nulstil_ej_optalt || in_array($r['id'],$reg_vare_id)) { #20200629
			$vare_id[$x]=$r['id'];
			$gruppe[$x]=$r['gruppe'];
			$beholdning[$x]=$r['beholdning'];
			$variant_id[$x]=array();
			$x++;		
		}
	}
	$tmp=0;
	$q=db_select("select * from variant_varer order by vare_id,id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($tmp!=$r['vare_id']) $v=0;
		for ($x=0;$x<count($vare_id);$x++){
			if ($r['vare_id'] == $vare_id[$x]) {
				$tmp=$r['vare_id'];
				$variant_id[$x][$v]=$r['id'];
				$variant_beholdning[$x][$v]=$r['variant_beholdning'];
				$v++;
			}
		}
	}
	$qtxt="select * from lagerstatus order by vare_id,variant_id,lager,id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$l=0;
		for ($x=0;$x<count($vare_id);$x++){
			for ($v=0;$v<count($variant_id[$x]);$v++){
				if ($r['vare_id'] == $vare_id[$x] && $r['variant_id'] == $variant_id[$x][$v]) {
					$ls_id[$x][$v][$l]=$r['id'];
					$ls_beholdning[$x][$v][$l]=$r['beholdning'];
					$l++;
				}
			}
		}
	}
	for ($x=0;$x<count($vare_id);$x++){
		$ny_beholdning=0;
		if (in_array($gruppe[$x],$v_grp)) { #if tilføjet 20170915
			if (count($variant_id[$x])){
				# -> 20180703
				$qtxt="delete from lagerstatus where vare_id='$vare_id[$x]' and variant_id=0";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="delete from batch_kob where vare_id='$vare_id[$x]' and variant_id=0";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="delete from batch_salg where vare_id='$vare_id[$x]' and variant_id=0";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				# <- 20180703
				for ($v=0;$v<count($variant_id[$x]);$v++) {
				$ny_variant_beholdning=0;
					for ($l=0;$l<count($lager);$l++) {
						$ny_lager_beholdning=0;
						$qtxt="select sum(antal) as ny_beholdning from batch_kob where vare_id='$vare_id[$x]' and variant_id='".$variant_id[$x][$v]."'";
						if ($lager[$l]<=1) $qtxt.="and lager<='1'"; 
						else $qtxt.="and lager='".$lager[$l]."'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$ny_beholdning+=$r['ny_beholdning']*1;
						$ny_variant_beholdning+=$r['ny_beholdning']*1;
						$ny_lager_beholdning+=$r['ny_beholdning']*1;
						$qtxt="select sum(antal) as ny_beholdning from batch_salg where vare_id='$vare_id[$x]' and variant_id='".$variant_id[$x][$v]."' and lager='".$lager[$l]."'";
						$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
						$ny_beholdning-=$r['ny_beholdning']*1;
						$ny_variant_beholdning-=$r['ny_beholdning']*1;
						$ny_lager_beholdning-=$r['ny_beholdning']*1;
						
						if ($ny_lager_beholdning != $ls_beholdning[$x][$v][$l]) {
							if ($ls_id[$x][$v][$l]) {
								$qtxt="update lagerstatus set beholdning='$ny_lager_beholdning' where id='".$ls_id[$x][$v][$l]."'";
							} else {
								$qtxt="insert into lagerstatus (lager,vare_id,variant_id,beholdning) values ('$lager[$l]','$vare_id[$x]','".$variant_id[$x][$v]."','$ny_lager_beholdning')";
							}
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
					if ($ny_variant_beholdning != $variant_beholdning[$x][$v]) {
						$qtxt="update variant_varer set variant_beholdning='$ny_variant_beholdning' where id='".$variant_id[$x][$v]."'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
/*
						if ($api_fil) {
							$qtxt="select shop_variant from shop_varer where saldi_id='$vare_id[$x]' and saldi_variant='".$variant_id[$x][$v]."'";
							if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
								$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
								$txt = "/usr/bin/curl ";
#								$txt = "/usr/bin/wget --spider --no-check-certificate --header='$header' ";
								$txt.= "'$api_fil?update_stock=$r[shop_variant]&stock=".$ny_variant_beholdning ."'";
								exec ("$txt > /dev/null 2>&1 &\n");
							}
						}
*/
					}
				}
			} else {
				$qtxt="select sum(antal) as ny_beholdning from batch_kob where vare_id='$vare_id[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$ny_beholdning+=$r['ny_beholdning']*1;
				$qtxt="select sum(antal) as ny_beholdning from batch_salg where vare_id='$vare_id[$x]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				$ny_beholdning-=$r['ny_beholdning']*1;
			}
		}
		if ($ny_beholdning != $beholdning[$x]) {
			$qtxt="update varer set beholdning='$ny_beholdning' where id='$vare_id[$x]'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
		}
/*
		if ($api_fil) {
			$qtxt="select shop_id from shop_varer where saldi_id='$vare_id[$x]'";
			if ($r=db_fetch_array($q=db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$apilog=fopen("../temp/$db/rest_aip_log","a");
				fwrite ($apilog, __file__." ".__line__." opdaterer vare_id:$vare_id[$x] ship_id:$r[shop_id] til $ny_beholdning stk\n")	;
				fclose ($apilog);
				$header="User-Agent: Mozilla/5.0 Gecko/20100101 Firefox/23.0";
				$txt = "/usr/bin/curl ";
#				$txt = "/usr/bin/wget --spider --no-check-certificate --header='$header' ";
				$txt.= "'$api_fil?update_stock=$r[shop_id]&stock=".$ny_beholdning ."'";
				exec ("$txt > /dev/null 2>&1\n");
			}
		}
*/
	}
}

function gentael($lager){

	$y=0;
	$x=0;
	$reg_vare_id=array();
	$reg_diff=array();
	$reguleres=array();
	$q=db_select("select * from regulering where bogfort='0' order by vare_id,id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if (!in_array($r['vare_id'],$reg_vare_id)) {
			$x++;
			$reg_vare_id[$x]=$r['vare_id'];
			$id=$reg_vare_id[$x];
			$reg_diff[$id]=0;
			$gl_beholdning[$id]=$r['beholdning'];
			$ny_beholdning[$id]=$r['optalt'];
		} else {
			$ny_beholdning[$id]+=$r['optalt'];
			if ($r['beholdning']!=$gl_beholdning[$id]) {
				$y++;
				$reg_diff[$id]=1;
			}
		}
#		if ($reg_diff[$id]) {
#			cho "$id | $r[beholdning] == $ny_beholdning[$id]<br>"; 
#			if ($r['beholdning'] == $ny_beholdning[$id]) {
#				$reg_diff[$id]=0;
#				$y--;
#			}
#		}
		$reguleres[$id]=$ny_beholdning[$id]-$gl_beholdning[$id];
	}
	$reg_antal=$x;

	if ($y) {
		print "<tr><td colspan=\"8\" align=\"center\"><b><big>Følgende varer har ændret antal under optællingen og skal optælles igen.</big></b></td></tr>";
		print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
		for ($x=0;$x<count($reg_vare_id);$x++) {
			$id=$reg_vare_id[$x];
			if ($reg_diff[$id]) {
				$r=db_fetch_array(db_select("select varenr from varer where id='$id'",__FILE__ . " linje " . __LINE__));
				if ($y>1) vis_optalling($lager,$r['varenr'],1);
				else vis_optalling($lager,$r['varenr'],0);
			}
		}
		print "<script language=\"javascript\">\n";
		print "document.optalling.optalt.focus();\n";
		print "</script>\n";
		exit;
	}
}

function importer($lager,$dato){
	global $charset;
	global $db;
	global $bruger_id;


	$indsat=0;
	$ej_indsat=0;
	$splitter=NULL;

	$transdate=usdate($_POST['dato']);
	list($y,$m,$d)=explode("-",$transdate);
	$tidspkt=$y.$m.$d."2359";
	if (basename($_FILES['uploadfile']['name'])) {
#	ob_implicit_flush(true);
#	ob_start();
#	#cho "Vent";
#	print tekstboks("Vent - det kan tage lang tid !!");
#	ob_end_flush();
	$filnavn="../temp/".$db."/".$bruger_id.".csv";
		if(move_uploaded_file($_FILES['uploadfile']['tmp_name'], $filnavn)) {
			$fp=fopen("$filnavn","r");
			if ($fp) {
				$komma=0;$semikolon=0;$kolon=0;$tab=0;
				while ($linje=trim(fgets($fp))) {
					if ($linje) {
						if (strpos($linje,",")) $komma++;
						if (strpos($linje,";")) $semikolon++;
						if (strpos($linje,":")) $kolon++;
						if (strpos($linje,chr(9))) $tab++;
					}
				}
				fclose($fp);
				if ($komma > $semikolon && $komma > $kolon && $komma > $tab) $splitter=",";
				elseif ($semikolon > $komma && $semikolon > $kolon && $semikolon > $tab) $splitter=";";
				elseif ($kolon > $komma && $kolon > $semikolon && $kolon > $tab) $splitter=":";
				elseif ($tab > $komma && $tab > $semikolon && $tab > $kolon) $splitter=chr(9);
			}
			if (!$splitter) {
				print "<BODY onLoad=\"javascript:alert('Fejl i importfil - kan ikke opdeles i kolonner')\">\n";
				print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php?import=1\">";
			}
			db_modify("update varer set lukket='' where lukket is NULL",__FILE__ . " linje " . __LINE__);
			if (!$lager) {
				db_modify("update batch_kob set lager='0' where lager is NULL",__FILE__ . " linje " . __LINE__);
				db_modify("update batch_salg set lager='0' where lager is NULL",__FILE__ . " linje " . __LINE__);
			} elseif ($lager==1) { 
				db_modify("update batch_kob set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
				db_modify("update batch_salg set lager='1' where lager='0' or lager is NULL",__FILE__ . " linje " . __LINE__);
			}
			$x=0;
			$q=db_select("select id,stregkode,varenr from varer",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)){
				$v_id[$x]=$r['id'];
				$v_str[$x]=$r['stregkode'];
				$v_nr[$x]=$r['varenr'];
				$v_var_id[$x]=0;
				$x++;
			}
			
			$x=0;
			$q=db_select("select id,variant_stregkode,vare_id from variant_varer",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)){
				$var_id[$x]=$r['id'];
				$var_str[$x]=$r['variant_stregkode'];
				$var_v_id[$x]=$r['vare_id'];
				$x++;
			}
			$fp=fopen("$filnavn","r");
			if ($fp) {
				$fp2=fopen("../temp/$db/optael_ej_exist.txt","w");
				while ($linje=trim(fgets($fp))) {
					list($varenr,$antal)=explode($splitter,$linje);
					if (substr($varenr,0,1)=='"' && substr($varenr,-1,1)=='"') $varenr=substr($varenr,1,strlen($varenr)-2);
#					$varenr=strtolower($varenr);
					if (substr($antal,0,1)=='"' && substr($antal,-1,1)=='"') $antal=substr($antal,1,strlen($antal)-2);
					$tmp=mb_convert_encoding($varenr, 'UTF-8', 'ISO-8859-1');
					if (strpos($tmp,'æ') || strpos($tmp,'ø') || strpos($tmp,'å') || strpos($tmp,'Æ') || strpos($tmp,'Ø') || strpos($tmp,'Å')) {
						$varenr=$tmp;
					}

					if (strpos($antal,",")) $antal=usdecimal($antal);
					if (is_numeric($antal)) {
						$vare_id=NULL;
						for ($x=0;$x<count($v_id);$x++) {
							if ($v_str[$x]==$varenr)	{
								$vare_id=$v_id[$x];
								$variant_id=0;
							}
						}
						if (!$vare_id) {
							for ($x=0;$x<count($v_id);$x++) {
								if ($v_nr[$x]==$varenr) {
									$vare_id=$v_id[$x];
									$variant_id=0;
								}
							}
						}
						if (!$vare_id) {
							for ($x=0;$x<count($v_id);$x++) {
								if (strtolower($v_nr[$x])==strtolower($varenr))	{
									$vare_id=$v_id[$x];
									$variant_id=0;
								}
							}
						}
						if (!$vare_id && is_numeric($varenr)) {
							for ($x=0;$x<count($v_id);$x++) {
								if (is_numeric($v_nr[$x]) && $v_nr[$x]*1==$varenr*1) {
									$vare_id=$v_id[$x];
									$variant_id=0;
								}
							}
						}
						if (!$vare_id) {
							for ($x=0;$x<count($var_id);$x++) {
								if (strtolower($var_str[$x])==strtolower($varenr)) {
									$vare_id=$var_v_id[$x];
									$variant_id=$var_id[$x];
								}
							}
						}
						#						if ($r=db_fetch_array(db_select("select id from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__))) $vare_id=$r['id']*1;
#						elseif ($r=db_fetch_array(db_select("select id from varer where lower(varenr)='".strtolower($varenr)."' or lower(stregkode)='".strtolower($varenr)."' or upper(varenr)='".strtoupper($varenr)."' or upper(stregkode)='".strtoupper($varenr)."'",__FILE__ . " linje " . __LINE__))) $vare_id=$r['id']*1;
						if ($vare_id) {
							$beholdning=0;
							$qtxt="select sum(antal) as antal from batch_kob where vare_id='$vare_id' and variant_id='$variant_id' and kobsdate<='$transdate'"; 
							if ($lager <= 1) $qtxt.=" and lager<='1'";
							else $qtxt.=" and lager='$lager'";
							$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
							$beholdning+=$r['antal'];
							$qtxt="select sum(antal) as antal from batch_salg where vare_id='$vare_id' and variant_id='$variant_id' and salgsdate<='$transdate'";
							if ($lager <= 1) $qtxt.=" and lager<='1'";
							else $qtxt.=" and lager='$lager'";
							$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
							$beholdning-=$r['antal'];
#							cho "*";
							$qtxt="insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt,variant_id,lager) values ";
							$qtxt.="('$vare_id','$antal','$beholdning','0','$tidspkt','$variant_id',$lager)";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							$qtxt="update lagerstatus set beholdning = '$beholdning' where vare_id='$vare_id' and lager='$lager' and variant_id='$variant_id'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							$indsat++;
						} elseif ($r=db_fetch_array(db_select("select id,vare_id from variant_varer where lower(variant_stregkode)='". db_escape_string($varenr) ."'",__FILE__ . " linje " . __LINE__))) {
							$variant_id=$r['id']*1;
							$vare_id=$r['vare_id']*1;
							$beholdning=0;
							$r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal from ordrelinjer,ordrer where ordrelinjer.ordre_id=ordrer.id and ordrelinjer.variant_id='$variant_id' and ordrer.levdate<='$transdate' and (ordrer.art='D_' or ordrer.art='PO')",__FILE__ . " linje " . __LINE__));
							$beholdning+=$r['antal'];
							$r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal from ordrelinjer,ordrer where ordrelinjer.ordre_id=ordrer.id and ordrelinjer.variant_id='$variant_id' and ordrer.levdate<='$transdate' and (ordrer.art='KO' or ordrer.art='KK')",__FILE__ . " linje " . __LINE__));
							$beholdning-=$r['antal'];
							db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt,variant_id,lager) values ('$vare_id','$antal','$beholdning','0','$tidspkt','$variant_id','$lager')",__FILE__ . " linje " . __LINE__);
							$indsat++;
						} else {
							$ej_indsat++;
							fwrite($fp2,"$varenr\n");
#							cho "*";
						}
					}
if (is_array($variant_id)) exit;
				}
				fclose($fp2);
				fclose($fp);
			}
			print "<BODY onLoad=\"javascript:alert('$indsat varenumre importeret i liste, $ej_indsat varenumre ikke fundet i vareliste')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php?vis_ej_exist=1&lager=$lager\">";
		}
	} else {
		if (!$dato) $dato=date("d-m-Y");
		print "<form enctype=\"multipart/form-data\" action=\"optalling.php?importer=1&lager=$lager&dato=$dato\" method=\"POST\">";
		print "<tr><td width=100% align=center><table width=\"500px\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td width=100% align=center colspan=\"2\"><b><big>Import af lageropt&aelig;lling til lager $lager</big></b><br><hr></td></tr>";
		print "<tr><td width=100% colspan=\"2\">Listen skal best&aring; af 2 kolonner, adskilt af komma, semikolon eller tabulator.<br>";
		print "1. kolonne skal indeholde varenummer eller stregkode, 2. kolonne den optalte beholdning";
#		print " Er der flere lagre, vælges hvilket lager optællingen vedrører<br>";
		print "Datoen skal være den dato hvor opt&aelig;llingen er sket. Hvis opt&aelig;llingen er sket ";
		print "mellem midnat og dagens 1. varebev&aelig;gelse skal anf&oslash;res den foreg&aring;ende dags dato.<br><hr></td></tr>";
		print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
		print "<tr><td>Dato for opt&aelig;lling</td><td><input class=\"inputbox\" style=\"text-align:left\" type=\"text\" name=\"dato\" value=\"$dato\"></td></tr>";
#		$r=db_fetch_array(db_select("select count(kodenr) as lagerantal from grupper where art='LG'",__FILE__ . " linje " . __LINE__));
#		if ($lagerantal=$r['lagerantal']){
#			print "<tr><td>Vælg lager<select name=\"lager\">";
#			for ($i=1;$i<=$lagerantal;$i++) print "<option>$i</option>";
		print "<tr><td>Lager</td><td><b>$lager</b></td></tr>";
		print "<tr><td><input type=\"hidden\" name=\"lager\" value=\"$lager\"></td></tr>";
		print "<tr><td>V&aelig;lg datafil:</td><td><input class=\"inputbox\" name=\"uploadfile\" type=\"file\" /><br /></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td></td><td align=center><input type=\"submit\" value=\"Hent\"/></td></tr>";
		print "<tr><td></form></td></tr>";
		print "</tbody></table>";
		print "</td></tr>";
	}

}
?>
</html>

