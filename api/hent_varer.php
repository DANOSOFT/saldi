<?php
// ------------- api/hent_varer.php ---------- lap 3.5.6----2015.09.08-------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// dog under iagttagelse af følgende:
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
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
//
// 20150908 Default varenr er nu varenr i stedet for vare_id.

#sleep (1);

@session_start();
$s_id=session_id();

print "<html>";
print "<head><title>Hent ocs_ordrer</title><meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8;\">";
print "<meta http-equiv=\"content-language\" content=\"da\">";
print "<meta name=\"google\" content=\"notranslate\">";
print "</head><body>";
print "<center>";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/ordrefunc.php");

$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
$encoding=$r['box7'];

$saldi_grp=if_isset($_POST['saldi_grp']);
$saldi_vnr=if_isset($_POST['saldi_vnr']);
#if ($saldi_vnr)
if (isset($_GET['shop_id'])) {
	$shop_id=$_GET['shop_id'];
	if (!$shop_id) {
		print "<input type=\"button\" value=\"Slut\" onclick=\"window.close\">";
		exit;
	}
} else $shop_id=NULL;

if (!$saldi_vnr && $shop_id) $saldi_vnr=$shop_id;
#cho "svnr $saldi_vnr<br>";
$shop_vnr=if_isset($_GET['shop_vnr']);
$saldi_id=if_isset($_POST['saldi_id']);
if (!$saldi_id) $saldi_id=if_isset($_GET['saldi_id']);
#$saldi_grp=if_isset($_GET['saldi_grp']);
$beskrivelse=if_isset($_GET['beskrivelse']);
$pris=if_isset($_GET['pris'])*1;
$specialpris=if_isset($_GET['specialpris'])*1;

if ($encoding!='UTF-8') {
	$beskrivelse=utf8_encode($beskrivelse);
	$shop_vnr=utf8_encode($shop_vnr);
}

$fp=fopen("log.txt","a");
fwrite($fp,__line__." ".date("H:i:s")."shop id $shop_id Besk $beskrivelse Pris $pris vnr $shop_vnr\n");
fclose ($fp);

$saldiurl="://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
if ($_SERVER['HTTPS']) $saldiurl="s".$saldiurl;
$saldiurl="http".$saldiurl;
$r=db_fetch_array(db_select("select box2 from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__));
$shopurl=trim($r['box2']);
$url=str_replace("/?","/hent_vare.php?",$shopurl);
$url.="&saldiurl=$saldiurl";
print "<table border=\"1\"><tbody>";

#cho "$saldi_id && $shop_id<br>";
#xit;
if ($saldi_id && $shop_id) {
	$qtxt="select id from shop_varer where saldi_id='$saldi_id'";
#cho __line__." $qtxt<br>";
#xit;
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['id']) $qtxt="update shop_varer set shop_id='$shop_id' where id ='$r[id]'";
	else $qtxt="insert into shop_varer(saldi_id,shop_id) values ('$saldi_id','$shop_id')";
#cho __line__." $qtxt";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$next_id=$shop_id;
#cho $url."<br>";	
$fp=fopen("log.txt","a");
fwrite($fp,__line__." ".date("H:i:s")."next id $next_id\n");
fclose ($fp);
	print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&id=0&next_id=$next_id\">";
#print __line__." <a href=$url&id=0&next_id=$next_id>next</a>";
	exit;
} elseif ($saldi_grp && $shop_id) {
	setcookie("saldi_grp",$saldi_grp,time()+60);
	$x=0;
	$q=db_select("select varenr,id from varer",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)){
		$vare_id[$x]=$r['id'];
		$varenr[$x]=$r['varenr'];
		$x++;
	}
	if (!in_array($saldi_vnr,$varenr)){
		$qtxt="insert into varer(varenr,beskrivelse,salgspris,special_price,gruppe,publiceret)values('$saldi_vnr','".db_escape_string($beskrivelse)."','$pris','$specialpris','$saldi_grp','on')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt="select id from varer where varenr='$saldi_vnr'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$saldi_id=$r['id'];
	} else {
		$qtxt="select id,beskrivelse from varer where varenr='$saldi_vnr'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$saldi_id=$r['id'];
		$saldi_besk=$r['beskrivelse'];
		print "<tr><td>Shop vare ID:</td><td>$shop_id</td></tr>";
		print "<tr><td>Shop vare nr:</td><td>$shop_vnr</td></tr>";
		print "<tr><td>Beskrivelse:</td><td>$beskrivelse</td></tr>";
		print "<tr><td>Pris:</td><td>".dkdecimal($pris)."</td></tr>";
		print "<tr><td>Tilbudspris:</td><td>".dkdecimal($specialpris)."</td></tr>";
		print "<tr><td colspan=\"2\">$saldi_vnr, $saldi_besk, er ikke ledigt<br>vælg venligst et andet eller klik ";
		print "<a href=\"hent_varer.php?saldi_id=$saldi_id&shop_id=$shop_id&shop_vnr=$shop_vnr&beskrivelse=$beskrivelse&pris=$pris&specialpris=$specialpris\">her</a> og overtag binding</td><td></tr>";
		$txt="";
		tilfoj($txt,$shop_id,$shop_vnr,$beskrivelse,$pris,$specialpris,$saldi_grp);
	}
	if ($saldi_id){
		$qtxt="insert into shop_varer(saldi_id,shop_id) values ('$r[id]','$shop_id')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$next_id=$shop_id;
		$linje=__line__;
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&next_id=$next_id&linje=$linje\">";
		exit;
	}
} elseif ($shop_id && $shop_vnr) {
	$qtxt="select * from shop_varer where shop_id='$shop_id'";
	if($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))){
		$qtxt="update varer set beskrivelse='".db_escape_string($beskrivelse)."',salgspris='$pris',special_price='$specialpris',publiceret='on' where id='$r[saldi_id]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$linje=__line__;
		if (isset($_COOKIE['saldi_shop_id']) && $_COOKIE['saldi_shop_id'] > $shop_id) {
			setcookie("saldi_shop_id","0",time()+60);
			print "<a href=$url&next_id=0&linje=$linje>next</a>";
			exit;
		} else {
			setcookie("saldi_shop_id",$shop_id,time()+60);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&next_id=$shop_id&linje=$linje\">";
			exit;
		}
	} elseif ($shop_vnr) {
		if($r=db_fetch_array(db_select("select * from varer where varenr='$shop_vnr'",__FILE__ . " linje " . __LINE__))) {
			$saldi_id=$r['id'];
			if(!$r=db_fetch_array(db_select("select * from shop_varer where saldi_id = '$saldi_id'",__FILE__ . " linje " . __LINE__))) {
				db_modify("insert into shop_varer (saldi_id,shop_id) values ('$saldi_id','$shop_id')",__FILE__ . " linje " . __LINE__);
				$linje=__line__;
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&next_id=$shop_id&linje=$linje\">";
				exit;
			}
		}
		
		$x=0;
		$qtxt="select * from shop_varer";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$shop_saldi_id[$x]=$r['saldi_id'];
			$x++;
		}		
		$x=0;
		if ($beskrivelse) $qtxt="select * from varer where beskrivelse like '%$beskrivelse%' or varenr='$shop_id'";
		else {
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&next_id=$shop_id&linje=$linje\">";
			exit;
		}
#cho "$qtxt<br>";
		$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			if (!in_array($r['id'],$shop_saldi_id)) {
				$forslag_id[$x]=$r['id'];
				$forslag_vnr[$x]=$r['varenr'];
				$forslag_besk[$x]=$r['beskrivelse'];
				$forslag_grp[$x]=$r['gruppe'];
				$x++;
			}
		}
		print "<tr><td>Shop vare ID:</td><td>$shop_id</td></tr>";
		print "<tr><td>Shop vare nr:</td><td>$shop_vnr</td></tr>";
		print "<tr><td>Beskrivelse:</td><td>$beskrivelse</td></tr>";
		print "<tr><td>Pris:</td><td>".dkdecimal($pris)."</td></tr>";
		print "<tr><td>Tilbudspris:</td><td>".dkdecimal($specialpris)."</td></tr>";
		if (count($forslag_id)>=1) { 
			print "<form name=\"hent_varer\" action=\"hent_varer.php?shop_id=$shop_id&shop_vnr=$shop_vnr&beskrivelse=$beskrivelse&pris=$pris&specialpris=$specialpris\" method=post autocomplete=\"off\">\n";
			print "<tr><td>Varen er ikke bundet til nogen vare i Saldi, men vi har følgende forslag:</td>";
			print "<td><select name=\"saldi_id\">"; 	
			for ($x=0;$x<count($forslag_id);$x++){
				print "<option value=\"$forslag_id[$x]\">$forslag_vnr[$x],$forslag_besk[$x]</option>";
			}
			print "</select></td></tr>";
			print "<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\"name=\"tilknyt\" value=\"Tilknyt\">&nbsp;<a href=$url&next_id=$shop_id><input type=\"button\" value=\"Spring over\"></td></tr>";
			print "</form>";
			if (count($forslag_vnr)==1 && $forslag_vnr[0]==$shop_id) {
				$qtxt="select id from shop_varer where saldi_id='$forslag_id[0]'";
				$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
				if ($r['id']) $qtxt="update shop_varer set shop_id='$shop_id' where id ='$r[id]'";
				else $qtxt="insert into shop_varer(saldi_id,shop_id) values ('$forslag_id[0]','$shop_id')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$next_id=$shop_id;
				$fp=fopen("log.txt","a");
				fwrite($fp,__line__." ".date("H:i:s")."next id $next_id\n");
				fclose ($fp);
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&id=0&next_id=$next_id\">";
				exit;
			} 
		} else {
			$tekst="Der er ikke fundet noget match i Saldi";
			tilfoj($tekst,$shop_id,$shop_vnr,$beskrivelse,$pris,$specialpris,$saldi_grp);
		}
		
	} else print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&next_id=$shop_id&linje=$linje\">";
} else {
#cho ">$next_id< && >$beskrivelse<<br>";
	if (!$next_id && !$beskrivelse) $next_id=0;
	#cho "$url -> $next_id<br>"; 
	$linje=__line__;
	if ($next_id || $next_id=='0') {
#xit;
$fp=fopen("log.txt","a");
fwrite($fp,__line__." ".date("H:i:s")."next id $next_id\n");
fclose ($fp);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$url&next_id=$next_id&linje=$linje\">";
#		print "<a href=$url&next_id=$next_id&linje=$linje>next</a>";
	} else print "<input type=\"button\" value=\"Slut\" onclick=\"window.close\">";
	exit;
}
function tilfoj($tekst,$shop_id,$shop_vnr,$beskrivelse,$pris,$specialpris,$saldi_grp) {
	global $url;

	$x=0;
	if (!$saldi_grp) $saldi_grp=$_COOKIE['saldi_grp'];
	$qtxt="select * from grupper where art='VG' order by ".nr_cast(kodenr)."";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)){
		$vg_id[$x]=$r['kodenr'];
		$vg_besk[$x]=$r['beskrivelse'];
		$x++;
	}
	print "<form name=hent_varer action=\"hent_varer.php?shop_id=$shop_id&shop_vnr=$shop_vnr&beskrivelse=$beskrivelse&pris=$pris&specialpris=$specialpris\" method=post autocomplete=\"off\">\n";
	print "<tr><td colspan=\"2\">$tekst</td></tr>";
	print "<tr><td colspan=\"2\">Vælg varegruppe for oprettelse eller gå videre til næste vare</td></tr>";
	print "<tr><td>Varenr</td><td><input type=\"text\" name=\"saldi_vnr\" placeholder=\"$shop_vnr\"><td></tr>";
	print "<tr><td>Varegruppe</td><td><select name=\"saldi_grp\">";
	if ($saldi_grp) {
		for($x=0;$x<count($vg_id);$x++){
			if ($vg_id[$x]==$saldi_grp) {
				$tmp=$vg_id[$x];
				while (strlen($tmp)<2) $tmp="0".$tmp;
				print "<option value=\"$vg_id[$x]\">$tmp: $vg_besk[$x]</option>";
			}
		}
	}
	for($x=0;$x<count($vg_id);$x++){
		$tmp=$vg_id[$x];
		while (strlen($tmp)<2) $tmp="0".$tmp;
		print "<option value=\"$vg_id[$x]\">$tmp: $vg_besk[$x]</option>";
	}
	print "</select></td></tr>";
	print "<tr><td colspan=\"2\" align=\"center\">";
	print "<input type=\"submit\" name=\"tilfoj\" value=\"Tilføj\">&nbsp;<a href=$url&next_id=$shop_id><input type=\"button\" value=\"Spring over\">";
	print "</td></tr>";
	print "</form>";
	exit;
}
print "</body></html>";
?>