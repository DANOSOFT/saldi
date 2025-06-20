<?php

// ------------lager/optalling.php------------patch 3.6.2------2016.02.09---
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2016 DANOSOFT ApS
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

@session_start();
$s_id=session_id();
$css="../css/standard.css";
$title="lageropt&aelig;lling";
$modulnr=15;

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if ($popup) $returside="../includes/luk.php";
else $returside="rapport.php";

$importer=if_isset($_GET['importer']);
$nulstil=if_isset($_GET['nulstil']);
$lager=if_isset($_GET['lager']);
if (!$lager) $lager=if_isset($_POST['lager']);
if (!$lager) $lager=1; #20160211

$slet=if_isset($_GET['slet']);
$vare_id=if_isset($_GET['vare_id']);
$varenr=strtolower(if_isset($_GET['varenr']));
$bogfor=if_isset($_GET['bogfor']);
$gentael=if_isset($_GET['gentael']);

if ($bogfor) {
	$nulstil_ej_optalt=if_isset($_GET['nulstil_ej_optalt']);
	if ($_POST['nulstil_ej_optalt']) $nulstil_ej_optalt=if_isset($_POST['nulstil_ej_optalt']);
	$dato=if_isset($_GET['dato']); 
	if ($_POST['dato']) $dato=if_isset($_POST['dato']);
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
	if (!$varenr) $varenr=if_isset(db_escape_string($_POST['varenr']));
	$optalt=if_isset($_POST['optalt']);
	$beholdning=if_isset($_POST['beholdning']);
	$tidspkt=if_isset($_POST['tidspkt']);
	$dato=if_isset($_POST['dato']);
}
if (!$dato) $dato=if_isset($_POST['dato']);
if (!$dato) $dato=if_isset($_GET['dato']);
if (!$dato) { # 20141228
	if ($r=db_fetch_array(db_select("select max(tidspkt) as tidspkt from regulering where bogfort='0'",__FILE__ . " linje " . __LINE__))) {
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
($importer)? print "<a href=optalling.php>Afbryd</a>":print "<a href=optalling.php?importer=1>Importer</a>";
print "</a><br></td>\n";
print "</tbody></table name=\"tabel_1.1\"></td></tr>\n"; # <- tabel 1.1
if ($vis_ej_exist) $vis_ej_exist="<a href=\"../temp/$db/optael_ej_exist.txt\" target=\"blank\">Ikke oprettede varer</a>";
print "<tr><td>$vis_ej_exist<br></td></tr>\n";

if($importer) {
	importer($lager);
	exit;
}

#if(!db_fetch_array(db_select("select id from regulering where vare_id='$vare_id' and optalt='$optalt' and beholdning='$beholdning' and tidspkt='$tidspkt'",__FILE__ . " linje " . __LINE__))) {
#cho "($vare_id && ($optalt || $optalt=='0'))<br>";
if ($vare_id && ($optalt || $optalt=='0')) {
#	if ($optalt) { # remmet 20120913 saa det er muligt at optaelle til 0.
		$optalt=usdecimal($optalt);
		$beholdning*=1;
		$lager*=1;
#cho "select id from regulering where vare_id='$vare_id' and optalt='$optalt' and beholdning='$beholdning' and tidspkt='$tidspkt'<br>";
		$qtxt="select id from regulering where vare_id='$vare_id' and optalt='$optalt' and lager= '$lager' and beholdning='$beholdning' and tidspkt='$tidspkt' and bogfort='0'";
		if(!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$qtxt="insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt,variant_id,lager)";
			$qtxt.=" values ";
			$qtxt.="('$vare_id','$optalt','$beholdning','0','$tidspkt','0',$lager)";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#		}
	}
	$varenr=NULL;
}
print "<tr><td align=\"center\" width=\"100%\"><table name=\"tabel_1.2\" width=\"800px\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1.2

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

print "<form name=\"optalling\" action=\"optalling.php?gentael=$gentael\" method=\"post\">\n";
if ($varenr=trim($varenr)) {
	$fokus="optalt";
	print "<tr><td>Varenr</td><td><!-- Lager --></td><td>Beskrivelse</td><td align=\"left\">Beholdning ($dato)</td><td align=\"right\">Ny beholdning</td></tr>\n";
	if (!$r=db_fetch_array(db_select("select * from varer where varenr='$varenr' or stregkode='$varenr'",__FILE__ . " linje " . __LINE__))) {
		$r=db_fetch_array(db_select("select * from varer where lower(varenr)='".strtolower($varenr)."' or lower(stregkode)='".strtolower($varenr)."' or upper(varenr)='".strtoupper($varenr)."' or upper(stregkode)='".strtoupper($varenr)."'",__FILE__ . " linje " . __LINE__));
	}
	if ($r['id']) { # 20140717
		# 20140625 ->
		if ($date == date("Y-m-d")) { # 20141211
			$stmp="";
			$ktmp="";
		} else {
			$stmp="and salgsdate <= '$date'";
			$ktmp="and kobsdate <= '$date'";
		}
/*
		if ($lager || $lager=='0') {
			$stmp.=" and lager='$lager'";
			$ktmp.=" and lager='$lager'";
			if ($lager=='1') {
				$stmp.=" or lager is NULL";  
				$ktmp.=" or lager is NULL";
			}
		}
*/		
		$beholdning=0;
		$r2=db_fetch_array($q2=db_select("select sum(antal) as antal from batch_salg where vare_id='$r[id]' $stmp",__FILE__ . " linje " . __LINE__));
		$beholdning-=$r2['antal'];
		$r2=db_fetch_array($q2=db_select("select sum(antal) as antal from batch_kob where vare_id='$r[id]' $ktmp",__FILE__ . " linje " . __LINE__));
		$beholdning+=$r2['antal'];
		# <-20140625
	}
	print "<tr><td></td></tr>\n";
		
	$tmp=dkdecimal($beholdning*1); #20140103
	while(substr($tmp,-1)=='0') $tmp=substr($tmp,0,strlen($tmp)-1);
	if(substr($tmp,-1)==',') $tmp=substr($tmp,0,strlen($tmp)-1);
	print "<tr><td>$r[varenr]</td><td><!-- $lager --></td><td>$r[beskrivelse]<input type=\"hidden\" name=\"dato\" value=\"$dato\"></td>
	<td align=\"left\">$tmp</td><td align=\"right\"><input style=\"width:75px;text-align:right;\" type=\"text\" name=\"optalt\">\n";
	print "<input type=\"hidden\" name=\"varenr\" value='$r[varenr]'>\n";
	print "<input type=\"hidden\" name=\"lager\" value='$lager'>\n";
	print "<input type=\"hidden\" name=\"vare_id\" value='$r[id]'>\n";
	print "<input type=\"hidden\" name=\"beholdning\" value='$beholdning'></td></tr>\n";
} else {
	$fokus="varenr";
	print "<tr>
	<td align=\"center\">Dato</td><td align=\"center\"></td>";
	print "<td align=\"center\">Lager</td>";
	print "<td align=\"center\">Varenummer / Stregkode</td></tr>";
	print "<tr><td align=\"center\"><input style=\"width:100px;text-align:left;\" type=\"text\" name=\"dato\" value=\"".dkdato($date)."\"></td><td align=\"center\">";
#cho count($lagernr)."<br>";
#	if (count($lagernr)) {
#cho count($lagernr)."<br>";
#		print "<select style=\"width:100px;text-align:left;\" name=\"lager\">";
#		for ($i=0;$i<count($lagernr);$i++) {
#			if ($lager==$lagernr[$i]) print "<option value=\"$lagernr[$i]\">$lagernr[$i]:$lagernavn[$i]</option>";
#		}
#		for ($i=0;$i<count($lagernr);$i++) {
#			if ($lager!=$lagernr[$i]) print "<option value=\"$lagernr[$i]\">$lagernr[$i]:$lagernavn[$i]</option>";
##		print "</select>";	
#	}
	print "<td align=\"center\"><b>$lager</b></td><td align=\"center\"><input style=\"width:300px;text-align:left;\" type=\"text\" name=\"varenr\"></td>\n";
}
print "<input type=\"hidden\" name=\"tidspkt\" value=\"".date('YmdHis')."\">";

print "</tr><tr><td colspan=\"5\" align=\"center\"><input type=\"submit\" value=\"OK\"></form>";
if ($varenr) print "<a style=\"text-decoration:none\" href=optalling.php><input type=\"button\" value=\"Fortryd\"></a>";
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
	global $bgcolor;
	global $bgcolor2;
	global $dato;
	

	$lagervalue=0;
	$x=0;
	
	if ($vnr) $qtxt="select varer.id,varer.varenr,varer.beskrivelse,varer.kostpris,regulering.beholdning,regulering.tidspkt,regulering.optalt,regulering.lager from regulering,varer where varer.varenr='$vnr' and varer.id=regulering.vare_id and regulering.lager='$lager' and varer.id=regulering.vare_id and regulering.bogfort = '0' order by regulering.tidspkt";
	else $qtxt="select varer.id,varer.varenr,varer.beskrivelse,varer.kostpris,regulering.beholdning,regulering.tidspkt,regulering.optalt,regulering.variant_id,regulering.lager from regulering,varer where varer.id=regulering.vare_id and regulering.lager='$lager' and regulering.bogfort = '0' order by varer.varenr,regulering.tidspkt";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
			$beholddiff[$x]=0;
			if ($vnr && $x && $vare_id[$x]==$r['id'] && $variant_id[$x]==$r['variant_id']) {
				$optalt[$x]+=$r['optalt'];
			} else {
				$x++;
				$vare_id[$x]=$r['id'];	
				$varenr[$x]=$r['varenr'];
				$kostpris[$x]=$r['kostpris'];
				$beskrivelse[$x]=$r['beskrivelse'];
				$optalt[$x]=$r['optalt'];
				$beholdning[$x]=$r['beholdning']*1;
				$tidspkt[$x]=$r['tidspkt'];
				$variant_id[$x]=$r['variant_id'];
			if ($variant_id[$x]) {
				$r2=db_fetch_array(db_select("select variant_stregkode from variant_varer where id = '".$variant_id[$x]."'",__FILE__ . " linje " . __LINE__));
				$variant_stregkode[$x]=$r2['variant_stregkode'];
			}
		}
#		if (!$tidspkt[$x]) {
#			$tidspkt[$x]='201012262000';
#			db_modify("update regulering set tidspkt = '201012262000' where tidspkt is NULL or tidspkt = ''",__FILE__ . " linje " . __LINE__);
#		}
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
		$baggrund=$bgcolor2;
		print "<tr bgcolor=\"$baggrund\"><td>Varenr/Stregkode</td><td>Beskrivelse</td><td>Lager</td><td align=\"center\">Optalt&nbsp;dato</td><td align=\"right\">Beholdning</td><td align=\"right\">Optalt&nbsp;ant.</td><td align=\"right\">Kostpris</td><td align=\"right\">Lagerv&aelig;rdi</td><td align=\"right\">Lagerv&aelig;rdi&nbsp;sum</td><tr>\n";
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
#				if ($y==0) {
					if ($variant_stregkode[$x]) {
						print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td><b>".$lager."</b></td><td align=\"center\"><br></td><td align=\"right\"><br></td><td align=\"right\"><br></td><td colspan=\"4\"><br></td><tr>\n";
						print "<tr bgcolor=\"$baggrund\"><td>".$variant_stregkode[$x]."</td><td><b>".$lager."</b></td><td><br></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x])."</td><td align=\"right\">".dkdecimal($optalt[$x])."</td><tr>\n";
					} else print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td><b>".$lager."</b></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x])."</td><td align=\"right\">".dkdecimal($optalt[$x])."</td><td colspan=\"4\" align=\"right\" title=\"Klik her for at slette denne vare fra opt&aelig;llingen.\"><a style=\"text-decoration:none\" href=\"optalling.php?vare_id=$vare_id[$x]&varenr=$varenr[$x]&slet=y&gentael=$gentael\" onclick=\"return confirm('Vil du slette denne vare fra liste og opt&aelig;lle den igen?')\"><font color=\"#ff0000\"><b>X</b></font></a></td><tr>\n";
#				} else {
#					print "<tr bgcolor=\"$baggrund\"><td>".$variant_stregkode[$x]."</td><td><br></td><td>".$lager."<td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x])."</td><td align=\"right\">".dkdecimal($optalt[$x])."</td><tr>\n";
#				}
#				$y++;
#			}
			$kostsum=$kostpris[$x]*$sum;
			$lagervalue+=$kostsum;		
			print "<tr bgcolor=\"$baggrund\"><td><b>Optalt ialt</b></td><td><br></td><td><br></td><td><br></td><td align=\"right\"><b>".dkdecimal($beholdning[$x])."</b></td><td align=\"right\"><b>".dkdecimal($sum)."</b></td><td align=\"right\"><b>".dkdecimal($kostpris[$x])."</b></td><td align=\"right\"><b>".dkdecimal($kostsum)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue)."</b></td><tr>\n";
			$x++;
		} else {
			$aar=substr($tidspkt[$x],0,4);	
			$md=substr($tidspkt[$x],4,2);
			$dag=substr($tidspkt[$x],6,2);
			$time=substr($tidspkt[$x],8,2);
			$minut=substr($tidspkt[$x],10,2);
			$kostsum=$kostpris[$x]*$optalt[$x];
			$lagervalue+=$kostsum;
			print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td><b>".$lager."</b></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\"><b>".dkdecimal($beholdning[$x])."</b></td><td align=\"right\"><b>".dkdecimal($optalt[$x])."</b></td><td align=\"right\"><b>".dkdecimal($kostpris[$x])."</b></td><td align=\"right\"><b>".dkdecimal($kostsum)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue)."</b></td><td title=\"Klik her for at slette denne vare fra opt&aelig;llingen.\"><a  style=\"text-decoration:none\" href=\"optalling.php?vare_id=$vare_id[$x]&varenr=$varenr[$x]&slet=y\" onclick=\"return confirm('Vil du slette denne vare fra liste og opt&aelig;lle den igen?')\"><font color=\"#ff0000\"><b>X</b></font></a></td><tr>\n";
		}
		if (!$vnr) print "<tr><td colspan=\"8\"><hr></td></tr>";
	}
	$gruppe = NULL;
	$q=db_select("select kodenr from grupper where box8='on' and art='VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$tmp=$r['kodenr']*1;
		if ($gruppe) $gruppe.=" or gruppe = '".$tmp."'";
		else $gruppe="gruppe = '".$tmp."'";
	}
		if (!$vnr) {
		print "<tr><td colspan=\"8\"><hr></td></tr>";
		$VAREANTAL=0;
		if ($gruppe) $r=db_fetch_array(db_select("select count(id) as antal from varer where ($gruppe) and lukket != 'on'",__FILE__ . " linje " . __LINE__));
		$vareantal+=$r['antal'];
#		if ($antal) {	
			print "<tr><td colspan=\"8\">";
			if ($antal) print "Optalt ialt $antal varer udfra en samlet vareliste på $vareantal lagerf&oslash;rte varer.";
			print "Klik <a href=optalling.php?vis_ej_optalt=1&dato=$dato>her</a> for liste over ikke optalte varer";
			print "<td></tr>"; 
			print "<tr><td colspan=\"8\">";
			#21130109 - linjen herunder
			if ($antal) print "Klik <a href=\"optalling.php?nulstil=1\" onclick=\"return confirm('Vi du nulstille hele optællingen?')\">her</a> for at \"0-stille\" listen";
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
			$r2=db_fetch_array($q2=db_select("select sum(antal) as antal from batch_salg where vare_id='$r[id]' and salgsdate <= '$date'",__FILE__ . " linje " . __LINE__));
			$beholdning-=$r2['antal'];
			$r2=db_fetch_array($q2=db_select("select sum(antal) as antal from batch_kob where vare_id='$r[id]' and kobsdate <= '$date'",__FILE__ . " linje " . __LINE__));
			$beholdning+=$r2['antal'];
			if (!in_array($r['id'],$optalt)) {
				$kostsum=$beholdning*$r['kostpris'];
				$lagervalue+=$kostsum;
				($baggrund==$bgcolor)? $baggrund=$bgcolor2:$baggrund=$bgcolor;
				print "<tr bgcolor=\"$baggrund\"><td title=\"Klik her for at opt&aelig;lle denne vare.\"><b><a href=\"optalling.php?varenr=$r[varenr]&dato=$dato\">$r[varenr]</a></b></td><td><b>$r[beskrivelse]</b></td><td align=\"right\"><b>".dkdecimal($beholdning)."</b></td><td align=\"right\"><b>".dkdecimal($r['kostpris'])."</b></td><td align=\"right\"><b>".dkdecimal($kostsum)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue)."</b></td><tr>\n";
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
	global $regnaar;
	global $bruger_id;

	$r=db_fetch_array(db_select("select * from grupper where kodenr='$regnaar' and art='RA'",__FILE__ . " linje " . __LINE__));
	$startaar=$row['box2']*1;
	($startaar >= '2015')?$aut_lager='on':$aut_lager=NULL;
	$aut_lager='on';

	if ($bogfor<2) {
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\"><b><font color=\"ff0000\">OBS!</font></b> Denne funktion er ny og kun testet på enkelte regnskaber. </td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\">Sikkerhedskopier dit regnskab inden du bogfører og kontroller efterfølgende</td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\">Oplever du at noget ikke stemmer så kontakt straks Peter Rude på mobil 2066 9860</td></tr>\n";
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
	$q=db_select("select * from grupper where box8='on' and art='VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$x++;
		$gruppe[$x]=$r['kodenr'];
		$lagertraek[$x]=$r['box2'];
		$lagerregulering[$x]=$r['box5'];
		if ($lagertraek[$x] && !$lagerregulering[$x]) {
			echo "konto for lagerregulering ikke sat for varegruppe $gruppe[$x]<br>";
			$bogfor=0;
			return ("konto for lagerregulering ikke sat for varegruppe $gruppe[$x]");
		}
	}
	$gruppeantal=$x;
	$y=0;
	$x=0;
	$reg_vare_id=array();
	$reg_diff=array();
	$reguleres=array();
	$q=db_select("select * from regulering where bogfort='0' and lager='$lager' order by vare_id,id",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
#if ($r['vare_id']==3778) #cho $r['optalt']."<br>";
		if (!in_array($r['vare_id'],$reg_vare_id)) {
			$x++;
			$reg_vare_id[$x]=$r['vare_id'];
			$id=$reg_vare_id[$x];
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
#cho "$id Reguleres $reguleres[$id]<br>";
	}
	$reg_antal=$x;
	if ($y) {
		print "<tr><td colspan=\"8\" align=\"center\"><b><big>Følgende varer har ændret antal under optællingen og skal optælles igen.</big></b></td></tr>";
		print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
		for ($x=1;$x<=$reg_antal;$x++) {
			$id=$reg_vare_id[$x];
			if ($reg_diff[$id]) {
				$r=db_fetch_array(db_select("select varenr from varer where id='$id'",__FILE__ . " linje " . __LINE__));
				vis_optalling($lager,$r['varenr'],1);
			}
		}
		print "<tr><td colspan=8>Klik <a href=optalling.php?nulstil_ej_optalt=$nulstil_ej_optalt&dato=$dato&bogfor=$bogfor&lager=$lager&godkend_regdif=1>her</a> for at acceptere beholdning fra seneste optælling og regulere i forhold til dette</td></tr>";
		print "<script language=\"javascript\">\n";
		print "document.optalling.optalt.focus();\n";
		print "</script>\n";
		exit;
	}
		print "<tr><td colspan=\"8\" align=\"center\">Røde linjer er ikke optalte og reguleres til 0, grønne reguleres i henhold til optælling og hvide forbliver uændrede</td></tr>";
	print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
	print "<tr><td></td><td></td><td align=\"center\">Regulering</td><td align=\"center\">Regulering</td><td align=\"center\">Summeret</td></tr>";
	print "<tr><td>Varenr</td><td>Beskrivelse</td><td align=\"center\">antal</td><td align=\"center\">kostpris</td><td align=\"center\">regulering</td></tr>";
	$reguleret=0;
	transaktion('begin');
	$regulering=0;
	for($x=1;$x<=$gruppeantal;$x++) {
		if ($x>1) {
			print "<tr><td colspan=\"4\"><b><big>I alt reguleres for varegruppe ".$gruppe[$x-1]."</big></b></td><td align=\"right\"><b><big>".dkdecimal($regulering,2)."</big></b></td></tr>";
			print "<tr><td colspan=\"5\"><hr></td></tr>";
		}
		$regulering=0;
		print "<tr><td colspan=\"4\"><b><big>Vargruppe $gruppe[$x]</big></b></td></tr>";
		$tjek=0;
#cho "select * from varer where gruppe='$gruppe[$x]' and lukket != 'on' order by varenr<br>";
		$q=db_select("select * from varer where gruppe='$gruppe[$x]' and lukket != 'on' order by varenr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$id=$r['id'];
			$kostdiff=0;
#			$regulering=0;
#cho "tjek $tjek --> ID $id<br>";
#		}
#		if (!$x>10000) {
			if (!$reg_diff[$id]) {
				$kostpris=$r['kostpris'];
				$gl_beholdning[$id]=$r['beholdning'];
				$gl_kostsum=$r['beholdning']*$kostpris;
				$bgcolor="#ffffff";
				if ($lagerregulering[$x]) { # 20140615
					if (in_array($id,$reg_vare_id)) { 
						if ($reguleres[$id]) {
							$bgcolor="#00ff00";
							$ny_kostsum=$gl_kostsum+$reguleres[$id]*$r['kostpris'];
							$ny_beholdning[$id]=$gl_beholdning[$id]+$reguleres[$id];
							$kostdiff=$ny_kostsum-$gl_kostsum;
							$regulering+=$kostdiff;
						}
						if ($bogfor>1) {
							db_modify("update regulering set bogfort = '1' where bogfort = '0' and vare_id = '$id' and variant_id='0'",__FILE__ . " linje " . __LINE__);
#							} else {
#cho "<tr><td colspan=6>update regulering set bogfort = '1' where bogfort = '0' and vare_id = '$id'</td></tr>";
						}
					} elseif ($nulstil_ej_optalt && $gl_beholdning[$id]) {
						$bgcolor="#ff0000";
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$id' and kobsdate > '$transdate' and lager='$lager'",__FILE__ . " linje " . __LINE__));
						$gl_beholdning[$id]=$gl_beholdning[$id]-$r2['antal']*1;
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$id' and salgsdate > '$transdate' and lager='$lager'",__FILE__ . " linje " . __LINE__));
						$gl_beholdning[$id]=$gl_beholdning[$id]+$r2['antal']*1;
						$gl_kostsum=$gl_beholdning[$id]*$kostpris;
						$ny_kostsum=0;
						$ny_beholdning[$id]=0;
						$reguleres[$id]=$gl_beholdning[$id]*-1;
						$kostdiff=$ny_kostsum-$gl_kostsum;
						$regulering+=$kostdiff;
						if ($bogfor>1) {
							db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt,lager) values ('$id','0','$gl_beholdning[$id]','1','$tidspkt','$lager')",__FILE__ . " linje " . __LINE__);
#						} else {
#							cho "<tr><td colspan=6>insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt) values ('$id','0','$gl_beholdning[$id]','1','$tidspkt')</td></tr>";
						}
					}
# cho "$r[varenr] | regdiff $reg_diff[$id] | $kostdiff<br>";
					print "<tr bgcolor=\"$bgcolor\"><td>$r[varenr]</td><td>$r[beskrivelse]</td><td align=\"right\">".dkdecimal($reguleres[$id])."</td><td align=\"right\">".dkdecimal($kostdiff)."</td><td align=\"right\">".dkdecimal($regulering)."</td></tr>";
					if ($reguleres[$id]) {
					if ($bogfor>1) { #20150109			
							$reguleres[0]=$reguleres[$id]*-1;
							$ny_beholdning[$id]*=1;
							$qtxt="update varer set beholdning = '$ny_beholdning[$id]' where id = '$id'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							if ($reguleres[0] < 0) {
								$tmp=$reguleres[0]*-1;
								$qtxt="insert into batch_kob(vare_id,linje_id,kobsdate,fakturadate,ordre_id,antal,pris,rest,lager)";
								$qtxt.="values";
								$qtxt.="($id,'0','$transdate','$transdate','0','$tmp','$kostpris','$tmp','$lager')";
								db_modify($qtxt,__FILE__ . " linje " . __LINE__);
							} else {
								$y=0;
								$restsum=0;
								$qtxt="select id,rest from batch_kob where vare_id='$id' and kobsdate <= '$transdate' and rest > '0' and lager='$lager' ";
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
										$qtxt="insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)";
										$qtxt.="values";
										$qtxt.="('$bk_id[$y]','$id','0','$transdate','$transdate','0',$reguleres[0],'$kostpris','1','$lager')";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$restsum-=$reguleres[0];
										$reguleres[0]=0;
									} else {
										$qtxt="update batch_kob set rest = '0' where id = '$bk_id[$y]'";
										db_modify($qtxt,__FILE__ . " linje " . __LINE__);
										$qtxt="insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr,lager)";
										$qtxt.="values";
										$qtxt.="('$bk_id[$y]','$id','0','$transdate','$transdate','0',$bk_rest[$y],'$kostpris','1','$lager')";
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
#										('$id','0','$transdate','$transdate','0','0','$reguleres[0]','$kostpris','$lager[$id]')",__FILE__ . " linje " . __LINE__);
#									$r2=db_fetch_array(db_select("select max(id) as id from batch_kob where vare_id='$id' and linje_id=0",__FILE__ . " linje " . __LINE__));
									$qtxt="insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate,fakturadate, ordre_id, antal, pris, lev_nr,lager)"; 
									$qtxt.="values"; 
									$qtxt.="('0', '$id', '0', '$transdate', '$transdate', '0', '$reguleres[0]', '$kostpris', '1','$lager')";
									db_modify($qtxt,__FILE__ . " linje " . __LINE__);
								}
								
							}
						}
#cho "tjek $tjek --> ID $id<br>";	
					}
				}
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
					print "<tr><td colspan=\"6\">Konto $lagerregulering[$x] debiteres kr. ".dkdecimal($regulering)." som krediteres på konto $lagertraek[$x] pr. ".dkdato($transdate)."</td></tr>";
				} else {
					print "<tr><td colspan=\"6\">Konto $lagertraek[$x] debiteres kr. ".dkdecimal($regulering)." som krediteres på konto $lagerregulering[$x] pr. ".dkdato($transdate)."</td></tr>";
				}
			}
		}
	}

	
	$x=0;
	$reg_variant_id=array();
	$reg_diff=array();
	$reguleres=array();
#cho "select * from regulering where bogfort='0' order by variant_id,id<br>";
	$qtxt="select * from regulering where bogfort='0' and variant_id > '0' and lager='$lager' order by variant_id,id";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
#if ($r['vare_id']==3778) cho $r['optalt']." <br>";
		if (!in_array($r['variant_id'],$reg_variant_id)) {
			$x++;
			$reg_variant_id[$x]=$r['variant_id'];
			$id=$reg_variant_id[$x];
#cho "$id -> ";
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
#cho "$id Reguleres $reguleres[$id] $ny_beholdning[$id] $gl_beholdning[$id]<br>";
	}
	$reg_antal=$x;
	
	for ($x=1;$x<=$reg_antal;$x++){
		$id=$reg_variant_id[$x];
#cho "$id | $reguleres[$id] reg_diff[$id] $reg_diff[$id]<br>";
		if ($reguleres[$id]) {
#cho "update variant_varer set variant_beholdning = '$ny_beholdning[$id]' where id = $reg_variant_id[$x]<br>";
			if ($bogfor>1) {
				$qtxt="update variant_varer set variant_beholdning = '$ny_beholdning[$id]' where id = $reg_variant_id[$x]";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$qtxt="update regulering set bogfort = '1' where bogfort = '0' and variant_id = '$id' and lager='$lager'";	
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
	}
	
transaktion('commit');
	if ($bogfor==1) print "<tr><td colspan=\"6\">Klik <a href=optalling.php?bogfor=2&nulstil_ej_optalt=$nulstil_ej_optalt&dato=$dato&lager=$lager&godkend_regdif=$godkend_regdif>her</a> for endelig lagerregulering og bogføring pr. $dato</td></tr>";
	# else print "<tr><td colspan=\"6\">Lagerregulering udført.</td></tr>";
	else {
		print "<BODY onLoad=\"javascript:alert('Lagerregulering udført.')\">\n";
#xit;
		print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php\">";
	}
} # endfunc bogfor

########################################################################################################################
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
#cho "$id Reguleres $reguleres[$id]<br>";
	}
	$reg_antal=$x;

	if ($y) {
		print "<tr><td colspan=\"8\" align=\"center\"><b><big>Følgende varer har ændret antal under optællingen og skal optælles igen.</big></b></td></tr>";
		print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
		for ($x=1;$x<=$reg_antal;$x++) {
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

function importer($lager){
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
		$filnavn="../temp/".$db."/".$bruger_id.".csv";
		if(move_uploaded_file($_FILES['uploadfile']['tmp_name'], $filnavn)) {
			$fp=fopen("$filnavn","r");
			if ($fp) {
				$komma=1;$semikolon=1;$tab=1;
				while ($linje=trim(fgets($fp))) {
					if ($linje) {
						if (!strpos($linje,",")) $komma=0;
						if (!strpos($linje,";")) $semikolon=0;
						if (!strpos($linje,chr(9))) $tab=0;
					}
				}
				fclose($fp);
				if ($komma) $splitter=","; 	
				if ($semikolon) $splitter=";";
				if ($tab) $splitter=chr(9);
			}
			if (!$splitter) {
				print "<BODY onLoad=\"javascript:alert('Fejl i importfil - kan ikke opdeles i kolonner')\">\n";
				print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php?import=1\">";
			}
			$fp=fopen("$filnavn","r");
			if ($fp) {
				$fp2=fopen("../temp/$db/optael_ej_exist.txt","w");
				while ($linje=trim(fgets($fp))) {
				list($varenr,$antal)=explode($splitter,$linje);
					if (substr($varenr,0,1)=='"' && substr($varenr,-1,1)=='"') $varenr=substr($varenr,1,strlen($varenr)-2);
#					$varenr=strtolower($varenr);
					if (substr($antal,0,1)=='"' && substr($antal,-1,1)=='"') $antal=substr($antal,1,strlen($antal)-2);
					if (strpos($antal,",")) $antal=usdecimal($antal);
					if (is_numeric($antal)) {
						$vare_id=NULL;
						if ($r=db_fetch_array(db_select("select id from varer where varenr='$varenr'",__FILE__ . " linje " . __LINE__))) $vare_id=$r['id']*1;
						elseif ($r=db_fetch_array(db_select("select id from varer where lower(varenr)='".strtolower($varenr)."' or lower(stregkode)='".strtolower($varenr)."' or upper(varenr)='".strtoupper($varenr)."' or upper(stregkode)='".strtoupper($varenr)."'",__FILE__ . " linje " . __LINE__))) $vare_id=$r['id']*1;
						if ($vare_id) {
							$beholdning=0;
							$r=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$vare_id' and kobsdate<='$transdate'",__FILE__ . " linje " . __LINE__));
							$beholdning+=$r['antal'];
							$r=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$vare_id' and salgsdate<='$transdate'",__FILE__ . " linje " . __LINE__));
							$beholdning-=$r['antal'];
#							cho "*";
							db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt,variant_id,lager) values ('$vare_id','$antal','$beholdning','0','$tidspkt','0',$lager)",__FILE__ . " linje " . __LINE__);
							$indsat++;
						} elseif ($r=db_fetch_array(db_select("select id,vare_id from variant_varer where lower(variant_stregkode)='$varenr'",__FILE__ . " linje " . __LINE__))) {
							$variant_id=$r['id']*1;
							$vare_id=$r['vare_id']*1;
							$beholdning=0;
							$r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal from ordrelinjer,ordrer where ordrelinjer.ordre_id=ordrer.id and ordrelinjer.variant_id='$variant_id' and ordrer.levdate<='$transdate' and (ordrer.art='D_' or ordrer.art='PO')",__FILE__ . " linje " . __LINE__));
							$beholdning+=$r['antal'];
							$r=db_fetch_array(db_select("select sum(ordrelinjer.antal) as antal from ordrelinjer,ordrer where ordrelinjer.ordre_id=ordrer.id and ordrelinjer.variant_id='$variant_id' and ordrer.levdate<='$transdate' and (ordrer.art='KO' or ordrer.art='KK')",__FILE__ . " linje " . __LINE__));
							$beholdning-=$r['antal'];
							db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt,variant_id) values ('$vare_id','$antal','$beholdning','0','$tidspkt','$variant_id')",__FILE__ . " linje " . __LINE__);
							$indsat++;
						} else {
							$ej_indsat++;
							fwrite($fp2,"$varenr\n");
#							cho "*";
						}
					}
				}
				fclose($fp2);
				fclose($fp);
			}
			print "<BODY onLoad=\"javascript:alert('$indsat varenumre importeret i liste, $ej_indsat varenumre ikke fundet i vareliste')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php?vis_ej_exist=1\">";
		}
	} else {
		if (!$dato) $dato=date("d-m-Y");
		print "<form enctype=\"multipart/form-data\" action=\"optalling.php?importer=1\" method=\"POST\">";
		print "<tr><td width=100% align=center><table width=\"500px\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td width=100% align=center colspan=\"2\"><b><big>Import af lageropt&aelig;lling</big></b><br><hr></td></tr>";
		print "<tr><td width=100% colspan=\"2\">Listen skal best&aring; af 2 kolonner, adskilt af komma, semikolon eller tabulator.<br>";
		print "1. kolonne skal indeholde varenummer eller stregkode, 2. kolonne den optalte beholdning";
		print " Er der flere lagre, vælges hvilket lager optællingen vedrører<br>";
		print "Datoen skal være den dato hvor opt&aelig;llingen er sket. Hvis opt&aelig;llingen er sket ";
		print "mellem midnat og dagens 1. varebev&aelig;gelse skal anf&oslash;res den foreg&aring;ende dags dato.<br><hr></td></tr>";
		print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
		print "<tr><td>Dato for opt&aelig;lling</td><td><input class=\"inputbox\" style=\"text-align:left\" type=\"text\" name=\"dato\" value=\"$dato\"></td></tr>";
		$r=db_fetch_array(db_select("select count(kodenr) as lagerantal from grupper where art='LG'",__FILE__ . " linje " . __LINE__));
		if ($lagerantal=$r['lagerantal']){
			print "<tr><td>Vælg lager<select name=\"lager\">";
			for ($i=1;$i<=$lagerantal;$i++) print "<option>$i</option>";
			print "</select></tr>";
		} else print "<tr><td><input type=\"hidden\" name=\"lager\" value=\"1\"></td></tr>";
		print "<tr><td>V&aelig;lg datafil:</td><td><input class=\"inputbox\" name=\"uploadfile\" type=\"file\" /><br /></td></tr>";
		print "<tr><td><br></td></tr>";
		print "<tr><td></td><td align=center><input type=\"submit\" value=\"Hent\" /></td></tr>";
		print "<tr><td></form></td></tr>";
		print "</tbody></table>";
		print "</td></tr>";
	}

}
?>
</html>

