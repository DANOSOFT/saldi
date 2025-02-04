<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------ includes/opdat_kostpriser.php ------- lap 3.7.1 -- 2018-04-03 --
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2018 saldi.dk ApS
// ----------------------------------------------------------------------------
// 2017.02.29 PHR Tilføjet gennemsnitspriser. (metode 1)
// 2018.03.27 PHR Stykliste kostpriser efterreguleres.
// 2018.04.03 PHR Kun lagerførte varer opdateres herefter!


@session_start();
$s_id=session_id();

include("../includes/var_def.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
		<head>
			<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
</head>";
$grptxt=NULL;
$qtxt="select kodenr from grupper where box8='on'";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	($grptxt)?$grptxt.=" or gruppe = '$r[kodenr]'":$grptxt="gruppe = '$r[kodenr]'"; 
}
if ($grptxt) {
	$grptxt="and (".$grptxt;
	$grptxt.=")";
}

$vare_id=if_isset($_GET['vare_id'])*1;
$metode=if_isset($_GET['metode'])*1;

print "<center>$vare_id</center>";

if ($metode=='1') {
	$qtxt="select id,beholdning from varer where id > '$vare_id' and beholdning > '0' and samlevare != 'on' and lukket !='on' $grptxt order by id limit 1";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$beholdning=$r['beholdning'];
		$vare_id=$r['id'];
		$kobt=0;
		$kobssum=0;
		$pris=0;
		$qtxt="select id,vare_id,pris,antal,kobsdate from batch_kob where vare_id = '$vare_id' and linje_id != '0' and antal > '0' order by vare_id,kobsdate desc";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
			while ($kobt <= $beholdning && $r=db_fetch_array($q)) {
			if ($kobt+$r['antal']<=$beholdning) {
					$kobssum+=$r['antal']*$r['pris'];
					$kobt+=$r['antal'];
					$kobsdate=$r['kobsdate'];
				} else {
					$rest=$beholdning-$kobt;
					$kobssum+=$rest*$r['pris'];
					$kobt+=$rest;
					$kobsdate=$r['kobsdate'];
				}
			}
			if ($kobt) $pris=$kobssum/$kobt;
			if ($pris && $kobt) {
			if ($r=db_fetch_array(db_select("select id,kostpris,transdate from kostpriser where vare_id='$vare_id' order by transdate desc limit 1",__FILE__ . " linje " . __LINE__))) {	
				if ($r['transdate'] < $kobsdate && $r['kostpris'] != $pris) $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$pris','$kobsdate')";
				elseif ($r['transdate'] == '2015-01-01' && $r['kostpris'] != $pris) $qtxt="update kostpriser set kostpris='$pris', transdate = '$kobsdate' where id = '$r[id]'";
				elseif ($r['transdate'] == $kobsdate && $r['kostpris'] != $pris) $qtxt="update kostpriser set kostpris='$pris' where id = '$r[id]'";
			} else $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$pris','$kobsdate')";
			if ($qtxt) db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
			$qtxt="update varer set kostpris='$pris' where id='$vare_id' and samlevare !='on' and lukket !='on'";
			if ($pris > 0) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#			exit;
		}  
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/opdat_kostpriser.php?vare_id=$vare_id&metode=$metode\">";
		exit;
	} else { # så er der ikke flere varer.
		stklst();
#		print "<body onload=\"javascript:window.close();\">";
	}
} elseif ($metode=='2') {
	$qtxt="select id from varer where id > '$vare_id' and samlevare != 'on' and lukket !='on' $grptxt order by id limit 1";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$vare_id=$r['id'];
		$qtxt="select id,vare_id,pris,kobsdate from batch_kob where vare_id = '$vare_id' and linje_id != '0' and antal > '0'";
		$qtxt.="order by kobsdate desc limit 1";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$id=$r['id'];
			$vare_id=$r['vare_id']; # Den er go nok- skal være næste varenr
			$pris=$r['pris'];
			$kobsdate=$r['kobsdate'];
			$qtxt=NULL;
			$qtxt="select id,kostpris,transdate from kostpriser where vare_id='$vare_id' order by transdate desc limit 1";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {	
				if ($r['transdate'] < $kobsdate && $r['kostpris'] != $pris) $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$pris','$kobsdate')";
				elseif ($r['transdate'] == '2015-01-01' && $r['kostpris'] != $pris) $qtxt="update kostpriser set kostpris='$pris', transdate = '$kobsdate' where id = '$id'";
				elseif ($r['transdate'] == $kobsdate && $r['kostpris'] != $pris) $qtxt="update kostpriser set kostpris='$pris' where id = '$id'";
			} else $qtxt="insert into kostpriser (vare_id,kostpris,transdate) values ('$vare_id','$pris','$kobsdate')";
			if ($qtxt) db_modify("$qtxt",__FILE__ . " linje " . __LINE__);
			$qtxt="update varer set kostpris='$pris' where id='$vare_id' and samlevare !='on' and lukket !='on'";
			if ($pris > 0) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/opdat_kostpriser.php?vare_id=$vare_id&metode=$metode\">";
			exit;
		} else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/opdat_kostpriser.php?vare_id=$vare_id&metode=$metode\">";
			exit;
		} 
	} else { # så er der ikke flere varer.
		stklst();
	}
} #else print "<body onload=\"javascript:window.close();\">";
function stklst() {
	include("../includes/stykliste.php");
	$qtxt="select id from varer where samlevare='on' and lukket != 'on'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$vare_id=$r['id'];
		$pris=stykliste($r['id'],'','');
		$qtxt="update varer set kostpris='$pris' where id='$vare_id'";
		if ($pris > 0) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	print "<body onload=\"javascript:window.close();\">";
}



print "</html>";
?>