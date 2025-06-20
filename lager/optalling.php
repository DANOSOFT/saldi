<?php

// ------------lager/lageroptalling.php------------patch 3.2.1------2011.06.21---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------


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

if ($slet && $vare_id && $varenr) {
	db_modify("delete from regulering where vare_id='$vare_id' and bogfort='0'",__FILE__ . " linje " . __LINE__);
#	print "<BODY onLoad=\"javascript:alert('Varenr: $varenr er slettet fra optællingslisten')\">\n";
	$vare_id=0;
} else {
	$vare_id=if_isset($_POST['vare_id']);
	if (!$varenr) $varenr=strtolower(if_isset($_POST['varenr']));
	$optalt=if_isset($_POST['optalt']);
	$beholdning=strtolower(if_isset($_POST['beholdning']));
	$tidspkt=if_isset($_POST['tidspkt']);
}
$vnr=$varenr;
print "<table name=\"tabel_1\" width=\"100%\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1 ->
print "<tr><td width=\"100%\"><table name=\"tabel_1.1\" width=\"100%\" cellspacing=\"2\"  border=\"0\"><tbody>\n"; # tabel 1.1 ->
print "<td width=10% $top_bund><a href=$returside accesskey=L>Luk</a></td>\n";
print "<td width=80% $top_bund>$title</td>\n";
print "<td width=10% $top_bund>";
($importer)? print "<a href=optalling.php>Afbryd</a>":print "<a href=optalling.php?importer=1>Importer</a>";
print"</a><br></td>\n";
print "</tbody></table name=\"tabel_1.1\"></td></tr>\n"; # <- tabel 1.1
print "<tr><td><br></td></tr>\n";

if ($importer) {
	importer();
	exit;
}
if ($vare_id && ($beholdning || $beholdning=='0') && ($optalt || $optalt=='0')) {
#	$optalt=$optalt-$beholdning;
	if ($optalt) {
		$optalt=usdecimal($optalt);
#		if($r=db_fetch_array(db_select("select id from regulering where vare_id='$vare_id' and bogfort='0'",__FILE__ . " linje " . __LINE__))) {
#			db_modify("update regulering set optalt='$optalt' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
#		} else {
			if(!db_fetch_array(db_select("select id from regulering where vare_id='$vare_id' and optalt='$optalt' and beholdning='$beholdning' and tidspkt='$tidspkt'",__FILE__ . " linje " . __LINE__))) {
				db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt) values ('$vare_id','$optalt','$beholdning','0','$tidspkt')",__FILE__ . " linje " . __LINE__);

			}
#		}
	}
	$varenr=NULL;
}
print "<tr><td align=\"center\" width=\"100%\"><table name=\"tabel_1.2\" width=\"800px\" cellspacing=\"2\" border=\"0\"><tbody>\n"; #tabel 1.2
print "<form name=\"optalling\" action=\"optalling.php?gentael=$gentael\" method=\"post\">\n";
if ($varenr=trim($varenr)) {
	$fokus="optalt";
	print "<tr><td>Varenr</td><td>Beskrivelse</td><td align=\"right\">Beholdning</td><td align=\"right\">Ny beholdning</td></tr>\n";
	$r=db_fetch_array(db_select("select * from varer where lower(varenr)='$varenr' or lower(stregkode)='$varenr'",__FILE__ . " linje " . __LINE__));
	print "<tr><td></td></tr>\n";
#echo "$r[id]<br>";
	print "<tr><td>$r[varenr]</td><td>$r[beskrivelse]</td><td align=\"right\">$r[beholdning]</td><td align=\"right\"><input style=\"width:75px;text-align:right;\" type=\"text\" name=\"optalt\"></td></tr>\n";
	print "<tr><td><input type=\"hidden\" name=\"varenr\" value='$r[varenr]'></td></tr>\n";
	print "<tr><td><input type=\"hidden\" name=\"vare_id\" value='$r[id]'></td></tr>\n";
	print "<tr><td><input type=\"hidden\" name=\"beholdning\" value='$r[beholdning]'></td>\n";
} else {
	$fokus="varenr";
	print "<tr><td>Varenummer / Stregkode</td><td align=\"right\"><input style=\"width:300px;text-align:left;\" type=\"text\" name=\"varenr\"></td>\n";
}
print "<input type=\"hidden\" name=\"tidspkt\" value=\"".date('YmdHis')."\">";

print "<td align=\"right\"><input type=\"submit\" value=\"OK\"></form>";
if ($varenr) print "<a style=\"text-decoration:none\" href=optalling.php><input type=\"button\" value=\"Fortryd\"></a>";
print "</td></tr>\n";
print "</tbody></table  name=\"tabel_1.2\"></td></tr>\n"; # <- tabel 1.2
print "<tr><td align=\"center\" width=\"100%\"><hr></td></tr>";
print "<tr><td align=\"center\" width=\"100%\"><table name=\"tabel_1.3\" width=\"800px\" cellspacing=\"2\" border=\"0\"><tbody>\n"; # tabel 1.3 ->
if ($gentael) gentael();
if ($bogfor) bogfor($nulstil_ej_optalt,$dato,$bogfor,$godkend_regdif);
elseif($vis_ej_optalt) $optalt=vis_ej_optalt();
else $optalt=vis_optalling($vnr,0);

if ($optalt>2) {
	$dato=date('d-m-Y');
	print "<form name=\"optalling\" action=\"optalling.php?bogfor=1\" method=\"post\">\n";
	print "<td colspan=\"7\">Dato for opt&aelig;lling</td><td><input type=\"text\" name=\"dato\" value=\"$dato\"></td></tr>\n";
	print "<td colspan=\"7\">Sæt beholdning til 0 for alle ikke optalte varer</td><td><input type=\"checkbox\" name=\"nulstil_ej_optalt\"></td></tr>\n";
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


function vis_optalling($vnr,$gentael) {
	global $bgcolor;
	global $bgcolor2;

	$lagervalue=0;

	$x=0;
	if ($vnr) $q=db_select("select varer.id,varer.varenr,varer.beskrivelse,varer.kostpris,regulering.beholdning,regulering.tidspkt,regulering.optalt from regulering,varer where (lower(varer.varenr)='$vnr' or lower(varer.stregkode)='$vnr') and varer.id=regulering.vare_id and regulering.bogfort = '0'order by regulering.tidspkt",__FILE__ . " linje " . __LINE__);
	else $q=db_select("select varer.id,varer.varenr,varer.beskrivelse,varer.kostpris,regulering.beholdning,regulering.tidspkt,regulering.optalt from regulering,varer where varer.id=regulering.vare_id and regulering.bogfort = '0' order by varer.varenr,regulering.tidspkt",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if ($r['varenr']!=$varenr[$x]) {
			$y=0;
			$beholddiff[$x]=0;
			$x++;
			$vare_id[$x]=$r['id'];	
			$varenr[$x]=$r['varenr'];	
			$kostpris[$x]=$r['kostpris'];
			$beskrivelse[$x]=$r['beskrivelse'];
			$optalt[$x][$y]=$r['optalt'];
			$beholdning[$x][$y]=$r['beholdning'];
			$tidspkt[$x][$y]=$r['tidspkt'];
		}	else {
			$y++;
			$optalt[$x][$y]=$r['optalt'];
			$beholdning[$x][$y]=$r['beholdning'];
			$tidspkt[$x][$y]=$r['tidspkt'];
			$beholddiff[$x]+=$beholdning[$x][$y]-$beholdning[$x][$y-1];
		}
		if (!$tidspkt[$x][$y]) {
			$tidspkt[$x][$y]='201012262000';
			db_modify("update regulering set tidspkt = '201012262000' where tidspkt is NULL or tidspkt = ''",__FILE__ . " linje " . __LINE__);
		}
	}	
	$antal=$x;
	for ($x=1;$x<=$antal;$x++) {
		$baggrund=$bgcolor2;
		print "<tr bgcolor=\"$baggrund\"><td>Varenr</td><td>Beskrivelse</td><td align=\"center\">Optalt&nbsp;dato</td><td align=\"right\">Beholdning</td><td align=\"right\">Optalt&nbsp;ant.</td><td align=\"right\">Kostpris</td><td align=\"right\">Lagerv&aelig;rdi</td><td align=\"right\">Lagerv&aelig;rdi&nbsp;sum</td><tr>\n";
		($baggrund==$bgcolor)? $baggrund=$bgcolor2:$baggrund=$bgcolor;
		$y=0;
		$sum=0;
		if ($optalt[$x][$y+1]) {	
			while($optalt[$x][$y]) {
				$aar=substr($tidspkt[$x][$y],0,4);	
				$md=substr($tidspkt[$x][$y],4,2);
				$dag=substr($tidspkt[$x][$y],6,2);
				$time=substr($tidspkt[$x][$y],8,2);
				$minut=substr($tidspkt[$x][$y],10,2);
				$sum+=$optalt[$x][$y];
				if ($y==0) {
					print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x][$y])."</td><td align=\"right\">".dkdecimal($optalt[$x][$y])."</td><td colspan=\"4\" align=\"right\" title=\"Klik her for at slette denne vare fra opt&aelig;llingen.\"><a style=\"text-decoration:none\" href=\"optalling.php?vare_id=$vare_id[$x]&varenr=$varenr[$x]&slet=y&gentael=$gentael\" onclick=\"return confirm('Vil du slette denne vare fra liste og opt&aelig;lle den igen?')\"><font color=\"#ff0000\"><b>X</b></font></a></td><tr>\n";
				} else {
					print "<tr bgcolor=\"$baggrund\"><td><br></td><td><br></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\">".dkdecimal($beholdning[$x][$y])."</td><td align=\"right\">".dkdecimal($optalt[$x][$y])."</td><tr>\n";
				} 
				$y++;
			}
			$kostsum=$kostpris[$x]*$sum;
			$lagervalue+=$kostsum;		
			print "<tr bgcolor=\"$baggrund\"><td><b>Optalt ialt</b></td><td><br></td><td align=\"right\"><b>".dkdecimal($beholdning[$x][$y])."</b></td><td align=\"right\"></td><td align=\"right\"><b>".dkdecimal($sum)."</b></td><td align=\"right\"><b>".dkdecimal($kostpris[$x])."</b></td><td align=\"right\"><b>".dkdecimal($kostsum)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue)."</b></td><tr>\n";
		} else {
			$aar=substr($tidspkt[$x][$y],0,4);	
			$md=substr($tidspkt[$x][$y],4,2);
			$dag=substr($tidspkt[$x][$y],6,2);
			$time=substr($tidspkt[$x][$y],8,2);
			$minut=substr($tidspkt[$x][$y],10,2);
			$kostsum=$kostpris[$x]*$optalt[$x][$y];
			$lagervalue+=$kostsum;
			print "<tr bgcolor=\"$baggrund\"><td><b>$varenr[$x]</b></td><td><b>$beskrivelse[$x]</b></td><td align=\"center\">$dag.$md.$aar&nbsp;$time:$minut</td><td align=\"right\"><b>".dkdecimal($beholdning[$x][$y])."</b></td><td align=\"right\"><b>".dkdecimal($optalt[$x][$y])."</b></td><td align=\"right\"><b>".dkdecimal($kostpris[$x])."</b></td><td align=\"right\"><b>".dkdecimal($kostsum)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue)."</b></td><td title=\"Klik her for at slette denne vare fra opt&aelig;llingen.\"><a  style=\"text-decoration:none\" href=\"optalling.php?vare_id=$vare_id[$x]&varenr=$varenr[$x]&slet=y\" onclick=\"return confirm('Vil du slette denne vare fra liste og opt&aelig;lle den igen?')\"><font color=\"#ff0000\"><b>X</b></font></a></td><tr>\n";
		}
		print "<tr><td colspan=\"8\"><hr></td></tr>";
	}

	$gruppe = NULL;
	$q=db_select("select kodenr from grupper where box8='on' and art='VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$tmp=$r['kodenr']*1;
		if ($gruppe) $gruppe.=" or gruppe = '".$tmp."'";
		else $gruppe="gruppe = '".$tmp."'";
	}
#		if ($antal > 1) {
		print "<tr><td colspan=\"8\"><hr></td></tr>";
		$VAREANTAL=0;
		if ($gruppe) $r=db_fetch_array(db_select("select count(id) as antal from varer where ($gruppe) and lukket != 'on'",__FILE__ . " linje " . __LINE__));
		$vareantal+=$r['antal'];
#		if ($antal) {	
			print "<tr><td colspan=\"8\">";
			if ($antal) print "Optalt ialt $antal varer udfra en samlet vareliste på $vareantal lagerf&oslash;rte varer.";
			print "Klik <a href=optalling.php?vis_ej_optalt=1>her</a> for liste over ikke optalte varer";
			print "<td></tr>"; 
#		}
#	}
	print "<tr><td colspan=\"8\"><hr></td></tr>";
	return($x);
} # vis_optalling
#######################################################################################################
function vis_ej_optalt() {
	global $bgcolor;
	global $bgcolor2;

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

	print "<tr bgcolor=\"$baggrund\"><td colspan=\"7\" align=\"center\"><b><big>----- Ikke optalte varer -----</b></big></td><tr>\n";
	print "<tr bgcolor=\"$baggrund\"><td>Varenr</td><td>Beskrivelse</td><td align=\"right\">Beholdning</td><td align=\"right\">Kostpris</td><td align=\"right\">Lagerv&aelig;rdi</td><td align=\"right\">Lagerv&aelig;rdi&nbsp;sum</td><tr>\n";

	$q=db_select("select * from varer where ($gruppe) and lukket != 'on' order by varenr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		if (!in_array($r['id'],$optalt)) {
			$kostsum=$r['beholdning']*$r['kostpris'];
			$lagervalue+=$kostsum;
			($baggrund==$bgcolor)? $baggrund=$bgcolor2:$baggrund=$bgcolor;
			print "<tr bgcolor=\"$baggrund\"><td title=\"Klik her for at opt&aelig;lle denne vare.\"><b><a href=\"optalling.php?varenr=$r[varenr]\">$r[varenr]</a></b></td><td><b>$r[beskrivelse]</b></td><td align=\"right\"><b>".dkdecimal($r['beholdning'])."</b></td><td align=\"right\"><b>".dkdecimal($r['kostpris'])."</b></td><td align=\"right\"><b>".dkdecimal($kostsum)."</b></td><td align=\"right\"><b>".dkdecimal($lagervalue)."</b></td><tr>\n";
		}
	}
	$x=count($optalt);
	return($x);
} # vis_ej_optalt()
###########################################################################################################
function bogfor($nulstil_ej_optalt,$dato,$bogfor,$godkend_regdif) {
	if ($bogfor<2) {
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\"><b><font color=\"ff0000\">OBS!</font></b> Denne funktion er ny og kun testet på enkelte regnskaber. </td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\">Sikkerhedskopier dit regnskab inden du bogfører og kontroller efterfølgende</td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\">Oplever du at noget ikke stemmer så kontakt straks Peter Rude på mobil 2066 9860</td></tr>\n";
		print "<tr><td colspan =\"8\" align=\"center\" width=\"100%\"><hr width=\"80%\"></td></tr>\n";
	}
	global $bruger_id;

	$transdate=usdate($dato);
	$dato=dkdato($transdate);
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$tidspkt=date('YmdHis');

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
			if ($godkend_regdif) {
				$gl_beholdning[$id]=$r['beholdning'];
			} elseif ($r['beholdning']!=$gl_beholdning[$id]) {
				$y++;
				$reg_diff[$id]=1;
			}
		}
#		if ($reg_diff[$id]) {
#			echo "$id | $r[beholdning] == $ny_beholdning[$id]<br>"; 
#			if ($r['beholdning'] == $ny_beholdning[$id]) {
#				$reg_diff[$id]=0;
#				$y--;
#			}
#		}
		$reguleres[$id]=$ny_beholdning[$id]-$gl_beholdning[$id];
# echo "$id Reguleres $reguleres[$id]<br>";
	}
	$reg_antal=$x;
	if ($y) {
		print "<tr><td colspan=\"8\" align=\"center\"><b><big>Følgende varer har ændret antal under optællingen og skal optælles igen.</big></b></td></tr>";
		print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
		for ($x=1;$x<=$reg_antal;$x++) {
			$id=$reg_vare_id[$x];
			if ($reg_diff[$id]) {
				$r=db_fetch_array(db_select("select varenr from varer where id='$id'",__FILE__ . " linje " . __LINE__));
				vis_optalling($r['varenr'],1);
			}
		}
		print "<tr><td colspan=8>Klik <a href=optalling.php?nulstil_ej_optalt=$nulstil_ej_optalt&dato=$dato&bogfor=$bogfor&godkend_regdif=1>her</a> for at acceptere beholdning fra seneste optælling og regulere i forhold til dette</td></tr>";
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
	for($x=1;$x<=$gruppeantal;$x++) {
		$regulering=0;
		$q=db_select("select * from varer where gruppe='$gruppe[$x]' and lukket != 'on' order by varenr",__FILE__ . " linje " . __LINE__);
		while($r=db_fetch_array($q)) {
			$id=$r['id'];
			$kostdiff=0;
			if (!$reg_diff[$id]) {
				$kostpris=$r['kostpris'];
				$gl_beholdning[$id]=$r['beholdning'];
				$gl_kostsum=$r['beholdning']*$kostpris;
				$bgcolor="#ffffff";
				if ($lagertraek[$x]) {
					if (in_array($id,$reg_vare_id)) {
						if ($reguleres[$id]) {
							$bgcolor="#00ff00";
							$ny_kostsum=$gl_kostsum+$reguleres[$id]*$r['kostpris'];
							$ny_beholdning[$id]=$gl_beholdning[$id]+$reguleres[$id];
							$kostdiff=$ny_kostsum-$gl_kostsum;
							$regulering+=$kostdiff;
						}
						if ($bogfor>1) {
							db_modify("update regulering set bogfort = '1' where bogfort = '0' and vare_id = '$id'",__FILE__ . " linje " . __LINE__);
#							} else {
#							echo "<tr><td colspan=6>update regulering set bogfort = '1' where bogfort = '0' and vare_id = '$id'</td></tr>";
						}
					} elseif ($nulstil_ej_optalt && $gl_beholdning[$id]) {
						$bgcolor="#ff0000";
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_kob where vare_id='$id' and kobsdate > '$transdate'",__FILE__ . " linje " . __LINE__));
						$gl_beholdning[$id]=$gl_beholdning[$id]-$r2['antal']*1;
						$r2=db_fetch_array(db_select("select sum(antal) as antal from batch_salg where vare_id='$id' and salgsdate > '$transdate'",__FILE__ . " linje " . __LINE__));
						$gl_beholdning[$id]=$gl_beholdning[$id]+$r2['antal']*1;
						$gl_kostsum=$gl_beholdning[$id]*$kostpris;
						$ny_kostsum=0;
						$ny_beholdning[$id]=0;
						$reguleres[$id]=$gl_beholdning[$id]*-1;
						$kostdiff=$ny_kostsum-$gl_kostsum;
						$regulering+=$kostdiff;
						if ($bogfor>1) {
							db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt) values ('$id','0','$gl_beholdning[$id]','1','$tidspkt')",__FILE__ . " linje " . __LINE__);
#						} else {
#							echo "<tr><td colspan=6>insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt) values ('$id','0','$gl_beholdning[$id]','1','$tidspkt')</td></tr>";
						}
					}
# echo "$r[varenr] | regdiff $reg_diff[$id] | $kostdiff<br>";
					print "<tr bgcolor=\"$bgcolor\"><td>$r[varenr]</td><td>$r[beskrivelse]</td><td align=\"right\">".dkdecimal($reguleres[$id])."</td><td align=\"right\">".dkdecimal($kostdiff)."</td><td align=\"right\">".dkdecimal($regulering)."</td></tr>";
					if ($kostdiff) {
					if ($bogfor>1) {			
							$reguleres[0]=$reguleres[$id]*-1;
							db_modify("update varer set beholdning = '$ny_beholdning[$id]' where id = '$id'",__FILE__ . " linje " . __LINE__);
							db_modify("insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr)
								values
							('0',$id,'0','$transdate','$transdate','0',$reguleres[0],'$kostpris','1')",__FILE__ . " linje " . __LINE__);
#						} else {
#							$reguleres[0]=$reguleres[$id]*-1;
#							echo "<tr><td colspan=6>update varer set beholdning = '$ny_beholdning[$id]' where id = '$id'</td></tr>";
#							echo "<tr><td colspan=6>insert into batch_salg(batch_kob_id,vare_id,linje_id,salgsdate,fakturadate,ordre_id,antal,pris,lev_nr)
#								values
#							('0',$id,'0','$transdate','$transdate','0',$reguleres[0],'$kostpris','1')</td></tr>";
						}
					}
				}
			}
		}
		if ($lagertraek[$x] && $bogfor>1) {
			if ($regulering < 0) {
				$regulering*=-1;
			db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
						values
					($lagerregulering[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
						values
					($lagertraek[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
						values
			 		($lagerregulering[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
				db_modify("insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
					values
				($lagertraek[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($lagertraek[$x]) {
			if ($regulering < 0) {
				$regulering*=-1;
				print "<tr><td colspan=\"6\">Konto $lagerregulering[$x] debiteres kr. ".dkdecimal($regulering)." som krediteres på konto $lagertraek[$x] pr. ".dkdato($transdate)."</td></tr>";
#				echo "<tr><td colspan=6>insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
#						values
#					($lagerregulering[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')</td></tr>";
#				echo "<tr><td colspan=6>insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
#						values
#					($lagertraek[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')</td></tr>";
			} else {
				print "<tr><td colspan=\"6\">Konto $lagertraek[$x] debiteres kr. ".dkdecimal($regulering)." som krediteres på konto $lagerregulering[$x] pr. ".dkdato($transdate)."</td></tr>";
#				echo "<tr><td colspan=6>insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, kredit, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
#						values
#			 		($lagerregulering[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')</td></tr>";
#				echo "<tr><td colspan=6>insert into transaktioner (kontonr, bilag, transdate, logdate, logtime, beskrivelse, debet, faktura, kladde_id,afd, ansat, projekt, valuta, valutakurs, ordre_id)
#					values
#				($lagertraek[$x], '0', '$transdate', '$logdate', '$logtime', 'Lageroptælling ($brugernavn)', '$regulering', '', '0', '0', '0', '0', '1', '100', '0')</td></tr>";
			}
		}
	}
	transaktion('commit');

	if ($bogfor==1) print "<tr><td colspan=\"6\">Klik <a href=optalling.php?bogfor=2&nulstil_ej_optalt=$nulstil_ej_optalt&dato=$dato&godkend_regdif=$godkend_regdif>her</a> for endelig lagerregulering og bogføring pr. $dato</td></tr>";
	# else print "<tr><td colspan=\"6\">Lagerregulering udført.</td></tr>";
	else {
		print "<BODY onLoad=\"javascript:alert('Lagerregulering udført.')\">\n";
		print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php\">";
	}
} # bogfor

########################################################################################################################
function gentael(){

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
#			echo "$id | $r[beholdning] == $ny_beholdning[$id]<br>"; 
#			if ($r['beholdning'] == $ny_beholdning[$id]) {
#				$reg_diff[$id]=0;
#				$y--;
#			}
#		}
		$reguleres[$id]=$ny_beholdning[$id]-$gl_beholdning[$id];
# echo "$id Reguleres $reguleres[$id]<br>";
	}
	$reg_antal=$x;

	if ($y) {
		print "<tr><td colspan=\"8\" align=\"center\"><b><big>Følgende varer har ændret antal under optællingen og skal optælles igen.</big></b></td></tr>";
		print "<tr><td colspan=\"8\" align=\"center\"><hr></td></tr>";
		for ($x=1;$x<=$reg_antal;$x++) {
			$id=$reg_vare_id[$x];
			if ($reg_diff[$id]) {
				$r=db_fetch_array(db_select("select varenr from varer where id='$id'",__FILE__ . " linje " . __LINE__));
				if ($y>1) vis_optalling($r['varenr'],1);
				else vis_optalling($r['varenr'],0);
			}
		}
		print "<script language=\"javascript\">\n";
		print "document.optalling.optalt.focus();\n";
		print "</script>\n";
		exit;
	}
}

function importer(){
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
				while ($linje=trim(fgets($fp))) {
				list($varenr,$antal)=explode($splitter,$linje);
					if (substr($varenr,0,1)=='"' && substr($varenr,-1,1)=='"') $varenr=substr($varenr,1,strlen($varenr)-2);
					$varenr=strtolower($varenr);
					if (substr($antal,0,1)=='"' && substr($antal,-1,1)=='"') $antal=substr($antal,1,strlen($antal)-2);
					if (strpos($antal,",")) $antal=usdecimal($antal);
					if (is_numeric($antal)) {
						if ($r=db_fetch_array(db_select("select id from varer where lower(varenr)='$varenr' or lower(stregkode)='$varenr'",__FILE__ . " linje " . __LINE__))) {
							$vare_id=$r['id']*1;
							$beholdning=0;
							$r=db_fetch_array(db_select("select antal from batch_kob where vare_id='$vare_id' and kobsdate<='$transdate'",__FILE__ . " linje " . __LINE__));
							$beholdning+=$r['antal'];
							$r=db_fetch_array(db_select("select antal from batch_salg where vare_id='$vare_id' and salgsdate<='$transdate'",__FILE__ . " linje " . __LINE__));
							$beholdning-=$r['antal'];
#							echo "$varenr,$beholdning,$antal<br>";
						db_modify("insert into regulering (vare_id,optalt,beholdning,bogfort,tidspkt) values ($vare_id,$antal,'$beholdning','0','$tidspkt')",__FILE__ . " linje " . __LINE__);
							$indsat++;
						} else {
							$ej_indsat++;
							echo "$varenr eksisiterer ikke<br>";
						}
					}
				}
				fclose($fp);
			}
			print "<BODY onLoad=\"javascript:alert('$indsat varenumre importeret i liste, $ej_indsat varenumre ikke fundet i vareliste')\">\n";
			print "<meta http-equiv=\"refresh\" content=\"1;URL=optalling.php\">";
		}
	} else {
		$dato=date("d-m-Y");
		print "<form enctype=\"multipart/form-data\" action=\"optalling.php?importer=1\" method=\"POST\">";
		print "<tr><td width=100% align=center><table width=\"500px\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
		print "<tr><td width=100% align=center colspan=\"2\"><b><big>Import af lageropt&aelig;lling</big></b><br><hr></td></tr>";
		print "<tr><td width=100% colspan=\"2\">Listen skal best&aring; af 2 kolonner adskilt af komma, semikolon eller tabulator.<br>";
		print "1. kolonne skal indeholde varenummer, 2. kolonne den optalte beholdning.<br>";
		print "Datoen skal være den dato hvor opt&aelig;llingen er sket. Hvis opt&aelig;llingen er sket ";
		print "mellem midnat og dagens 1. varebev&aelig;gelse skal anf&oslash;res den foreg&aring;ende dags dato.<br><hr></td></tr>";
		print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"100000\">";
		print "<tr><td>Dato for opt&aelig;lling</td><td><input class=\"inputbox\" style=\"text-align:left\" type=\"text\" name=\"dato\" value=\"$dato\"></td></tr>";
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

