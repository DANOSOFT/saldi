<?php
@session_start();
$s_id=session_id();

// --- admin/vis_regnskaber.php --- patch 4.1.1 --- 2025.05.03 ---
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
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
// 20210328 PHR Some cleanup.
// 20210916 LOE Translated some texts
// 20250201 Add hostname to psql
// 20250503 LOE Updated with improved if_isset func.

$css="../css/standard.css";
$title="vis regnskaber";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

$saldiregnskab = NULL;
$lukket=array();

$rediger    = if_isset($_GET, NULL, 'rediger');
$showClosed = if_isset($_GET, NULL, 'showClosed');
$beregn     = if_isset($_GET, NULL, 'beregn');
$sort       = if_isset($_GET, NULL, 'sort');
$sort2      = if_isset($_GET, NULL, 'sort2');
$desc       = if_isset($_GET, NULL, 'desc');

$modulnr    = 102;

if ($db != $sqdb) {
	$alert = findtekst('1905|Hmm du har vist ikke noget at gøre her! Dit IP nummer, brugernavn og regnskab er registreret!', $sprog_id); #20210916
	print "<BODY onLoad=\"javascript:alert('$alert')\">";
	print "<meta http-equiv=\"refresh\" content=\"1;URL=../index/logud.php\">";
	exit;
}
if (isset($_POST['submit'])) {
	$rediger="on";
	$db_antal = if_isset($_POST, NULL, 'db_antal');
	$id = if_isset($_POST, NULL, 'id');
	$gl_brugerantal = if_isset($_POST, NULL, 'gl_brugerantal');
	$gl_posteringer = if_isset($_POST, NULL, 'gl_posteringer');
	$brugerantal = if_isset($_POST, NULL, 'brugerantal');
	$posteringer = if_isset($_POST, NULL, 'posteringer');
	$gl_lukket = if_isset($_POST, NULL, 'gl_lukket');
	$lukket = if_isset($_POST, NULL, 'lukket');
	$gl_lukkes = if_isset($_POST, NULL, 'gl_lukkes');
	$lukkes = if_isset($_POST, NULL, 'lukkes');
	$gl_betalt_til = if_isset($_POST, NULL, 'gl_betalt_til');
	$betalt_til = if_isset($_POST, NULL, 'betalt_til');
	$gl_logintekst = if_isset($_POST, NULL, 'gl_logintekst');
	$logintekst = if_isset($_POST, NULL, 'logintekst');



	for ($x=1;$x<=$db_antal; $x++) {
		if (!isset($lukket[$x]) || !$lukkes[$x]) $lukkes[$x]="2099-12-31"; 
		else $lukkes[$x]=usdate($lukkes[$x]);
		if (!isset($betalt_til[$x]) || !$betalt_til[$x]) $betalt_til[$x]="2099-12-31"; 
		else $betalt_til[$x]=usdate($betalt_til[$x]);
			if (
				$gl_brugerantal[$x]!=$brugerantal[$x] ||
				$gl_posteringer[$x]!=$posteringer[$x] ||
				$gl_lukket[$x]!=$lukket[$x] ||
			 	$gl_lukkes[$x]!=$lukkes[$x] ||
				$gl_betalt_til[$x]!=$betalt_til[$x] ||
				$gl_logintekst[$x]!=$logintekst[$x]
			 ){
			if ($saldiregnskab) $qtxt="update regnskab set brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]',lukkes='$lukkes[$x]',betalt_til='$betalt_til[$x]',logintekst='$logintekst[$x]' where id = '$id[$x]'";
			else $qtxt="update regnskab set	brugerantal='$brugerantal[$x]',posteringer='$posteringer[$x]',lukket='$lukket[$x]'where id = '$id[$x]'";
			if ($id[$x]) db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
} else { # 2020090 can be removed  
	$qtxt="update regnskab set lukket='' where lukket is NULL";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
if ($menu=='S') {
	print "<table width='100%' height='100%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td align='center' valign='top' height='25px'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%'><a href=../index/admin_menu.php accesskey=L>
		    <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('30|Tilbage', $sprog_id)."</button></a></td>";

	print "<td width='80%' align='center' style='$topStyle'>".findtekst('340|Vis regnskaber', $sprog_id)."</td>";#Vis regnskaber
	print "<td width='5%' align = 'center' style='$topStyle'>";

	if ($showClosed) {
		print "<a href='vis_regnskaber.php?sort=$sort&rediger=$rediger'>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst('1906|Skjul \'Luk\'', $sprog_id)."</button></a>";#Skjul Luk
	} else {
		print "<a href='vis_regnskaber.php?sort=$sort&rediger=$rediger&showClosed=on'>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">"
			   .findtekst('1907|Vis \'Luk\'', $sprog_id)."</button></a>";#Vis Luk
	}
	print "</td><td align='center' style='$topStyle'>";

	if ($rediger) {
		print "<a href='vis_regnskaber.php?sort=$sort&showClosed=$showClosed' >
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('1908|Lås', $sprog_id)."</button></a>"; #Lås
	} else {
		print "<a href='vis_regnskaber.php?sort=$sort&showClosed=$showClosed&rediger=on' accesskey=R>
			   <button style='$butUpStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('1206|Ret', $sprog_id)."</button></a>";#Ret
	}
	print "</td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	print "<link rel='stylesheet' href='../css/vis_regnskaber.css'>";
	print "<table class='table2'><tbody>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\" height=\"25px\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=../index/admin_menu.php accesskey=L>".findtekst('30|Tilbage', $sprog_id)."</a></td>";
	print "<td width=\"75%\" $top_bund align=\"center\">".findtekst('340|Vis regnskaber', $sprog_id)."</td>";#Vis regnskaber
	print "<td width='5%' $top_bund><a href='license_manager.php'>Licens</a></td>";
	print "<td width=\"5%\" $top_bund align = \"center\">";
	if ($showClosed) print "<a href='vis_regnskaber.php?sort=$sort&rediger=$rediger'>".findtekst('1906|Skjul \'Luk\'', $sprog_id)." </a>";#Skjul Luk
	else print "<a href='vis_regnskaber.php?sort=$sort&rediger=$rediger&showClosed=on'>".findtekst('1907|Vis \'Luk\'', $sprog_id)." </a>";#Vis Luk
	print "</td><td $top_bund align = \"center\">";
	if ($rediger) print "<a href='vis_regnskaber.php?sort=$sort&showClosed=$showClosed' > ".findtekst('1908|Lås', $sprog_id)."</a>"; #Lås
	else print "<a href='vis_regnskaber.php?sort=$sort&showClosed=$showClosed&rediger=on' accesskey=R> ".findtekst('1206|Ret', $sprog_id)."</a>";#Ret
	print "</td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	#print "<table cellpadding=\"1\" cellspacing=\"3\" border=\"1\" style='width:50%;border-radius:5px;border-color:#cccccc;' ><tbody>";
	print "<link rel='stylesheet' href='../css/vis_regnskaber.css'>"; #20240503
	#print "<table class='table table-bordered with-inner-borders'><tbody>";
	print "<table class='table2'><tbody>";
}

$id=array(); $regnskab=array(); $db_navn=array();

if (!$sort) $sort='regnskab';
if (!$sort2) $sort2='id';
if ($sort==$sort2) {
	if (!$desc) {
		$order="order by $sort desc";
		$desc='on';
	} else {
		$order="order by $sort";
		$desc='';
	}
} else {
	$order="order by $sort,$sort2";
	$desc='';
}

print "<tr><td><b><a href=vis_regnskaber.php?sort=id&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>id</a></b></td>
	<td><b><a href=vis_regnskaber.php?sort=regnskab&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('2682|Regnskab', $sprog_id)."</a></b></td> 
	<td><a href=vis_regnskaber.php?sort=brugerantal&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('777|Brugere', $sprog_id)."</a></td>
	<td><a href=vis_regnskaber.php?sort=posteringer&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1910|Posteringer', $sprog_id)."</a></td>
	<td><a href=vis_regnskaber.php?sort=posteret&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1911|Posteret', $sprog_id)."</a></td>
	<td><a href=vis_regnskaber.php?sort=sidst&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1912|Sidst', $sprog_id)."</a></td>
	<td><a href=vis_regnskaber.php?sort=booking&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1116|Booking', $sprog_id)."</a></td>";
	
if ($showClosed) print "<td><a href=vis_regnskaber.php?sort=lukket&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('387|Lukket', $sprog_id)."</a></td>";
if ($saldiregnskab) {
	print "<td><a href=vis_regnskaber.php?sort=lukkes&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1913|Lukkes', $sprog_id)."</a></td>
		<td><a href=vis_regnskaber.php?sort=betalt_til&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1914|Betalt til', $sprog_id)."</a></td>
		<td><a href=vis_regnskaber.php?sort=logintekst&sort2=$sort&desc=$desc&rediger=$rediger&showClosed=$showClosed>".findtekst('1915|Logintekst', $sprog_id)."</a></td>";
}
print "</tr>";

$q = db_select("select * from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
$r = db_fetch_array($q);
list($admin,$oprette,$slette,$tmp)=explode(",",$r['rettigheder'],4);
$adgang_til=explode(",",$tmp);
$x=0;
$qtxt = "select * from regnskab where db != '$sqdb'";
if (!$showClosed) $qtxt.= " and lukket != 'on'"; 
$qtxt.= " $order";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($admin || in_array($r['id'],$adgang_til)) {
		$id[$x]=$r['id'];
		$regnskab[$x]=$r['regnskab'];
		$db_navn[$x]=$r['db'];
		$posteringer[$x]=$r['posteringer']*1;
		$posteret[$x]=$r['posteret']*1;
		$brugerantal[$x]=$r['brugerantal']*1;
		$sidst[$x]=$r['sidst'];
		$email[$x]=$r['email'];
#		$oprettet[$x]=date("d-m-Y",$r['oprettet']);
		($r['lukket'] == 'on')?$lukket[$x]='X':$lukket[$x]=NULL;
		($r['lukkes'] == 'on')?$lukkes[$x]='X':$lukkes[$x]=NULL;
		$betalt_til[$x] = if_isset($r, NULL, 'betalt_til');
		$logintekst[$x] = if_isset($r, NULL, 'logintekst');

		if($lukkes[$x]) $lukkes[$x]=dkdato($lukkes[$x]);
		if($betalt_til[$x]) $betalt_til[$x]=dkdato($betalt_til[$x]);
		$x++;
	}
}
if ($beregn) {
	$fp=fopen("../temp/$sqdb/tmp.sh","w");
	fwrite($fp,"#!/bin/sh\n");
	fwrite($fp,"export PGPASSWORD='$sqpass'\n");
	fwrite($fp,"psql --host=$sqhost --username=$squser -l > ../temp/dbliste.txt\n");
	fclose($fp);
	system("/bin/sh '../temp/$sqdb/tmp.sh'");
	unlink ("../temp/$sqdb/tmp.sh");
	$dbs=file("../temp/dbliste.txt");
	unlink("../temp/dbliste.txt");
	$l=0;
	for ($i=0;$i<count($dbs);$i++) {
		if (strpos($dbs[$i],"|") && strpos($dbs[$i],"_")) {
			list($tmp1,$tmp2)=explode("|",$dbs[$i],2);
			if (strpos($tmp1,"_")) {
				$dbliste[$l]=trim($tmp1);
				$l++;
			}
		}
	}
	$y=date("Y")-1;
	$m=date("m");
	$d=date("d");
	$dd=$y."-".$m."-".$d;
	for ($x=0;$x<count($id);$x++) {
		if (in_array($db_navn[$x],$dbliste)) {
			$qtxt = "SELECT datname FROM pg_database WHERE datname = '$db_navn[$x]'";
echo "$qtxt<br>";
			if (db_fetch_array($q = db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
echo "$db_navn[$x] eksisterer<br>";
				db_connect ("$sqhost", "$squser", "$sqpass", "$db_navn[$x]", __FILE__ . " linje " . __LINE__);
				$qtxt="select * from pg_tables where tablename='transaktioner'";
				if (db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$r=db_fetch_array(db_select("select count(id) as transantal from transaktioner where logdate >= '$dd'",__FILE__ . " linje " . __LINE__));
					$posteringer[$x]=$r['transantal']*1;
					if ($r=db_fetch_array(db_select("select max(logdate) as logdate from transaktioner",__FILE__ . " linje " . __LINE__))) {
						$sidst[$x]=strtotime($r['logdate']);
					} 
					if ($r=db_fetch_array(db_select("select * from batch_salg order by id desc limit 1",__FILE__ . " linje " . __LINE__))) {
						if (isset($r['modtime']) &&  $r['modtime']) {
							if (strtotime($r['modtime']) > $sidst[$x]) $sidst[$x]=strtotime($r['modtime']);
						}
					} 
				} else $sidst[$x]=NULL;
				include("../includes/connect.php");
			} else {
				echo "opretter $db_navn[$x]<br>";
				db_create($db_navn[$x]);
			}	
		} else {
			echo "opretter $db_navn[$x]<br>";
			db_create($db_navn[$x]);
			$sidst[$x]=NULL;
		}
	}
}
if ($rediger)	print "<form name=regnskaber action=vis_regnskaber.php method=post>";
	for ($x=0;$x<count($id);$x++) {
		if (!$sidst[$x]) $sidst[$x]=0;
		if ($rediger && isset($id[$x])) {
			print "<input type=hidden name=\"id[$x]\" value=\"$id[$x]\">";
			print "<input type=hidden name=\"gl_lukket[$x]\" value=\"$lukket[$x]\">";
			print "<input type=hidden name=\"gl_lukkes[$x]\" value=\"$lukkes[$x]\">";
			print "<input type=hidden name=\"gl_brugerantal[$x]\" value=\"$brugerantal[$x]\">";
			print "<input type=hidden name=\"gl_posteringer[$x]\" value=\"$posteringer[$x]\">";
			print "<input type=hidden name=\"gl_betalt_til[$x]\" value=\"$betalt_til[$x]\">";
			print "<input type=hidden name=\"gl_logintekst[$x]\" value=\"$logintekst[$x]\">";
			print "<tr><td align='right'> $id[$x]</td><td><a href=aaben_regnskab.php?db_id=$id[$x]>$regnskab[$x]</a></td>";
			print "<td><input type=text size=\"5\" style=\"text-align:right\" name=\"brugerantal[$x]\" value=\"$brugerantal[$x]\"></td>";
			print "<td><input type=text size=\"5\" style=\"text-align:right\" name=\"posteringer[$x]\" value=\"$posteringer[$x]\"</td>";
			print "<td align='right'>$posteret[$x]</td>";
			print "<td align='right'>".date("d-m-Y",$sidst[$x])."</td>";
			print "<td><input type='checkbox' name='booking[$x]' $booking[$x]></td>";
			if ($lukket[$x]) $lukket[$x]="checked";
			if ($showClosed) print "<td align=center><input type=checkbox name=lukket[$x] $lukket[$x]></td>";
			if ($saldiregnskab) {
				print "<td><input type=text size='8' style=\"text-align:right\" name=\"lukkes[$x]\" value=\"$lukkes[$x]\"</td>";
				print "<td><input type=text size='8' style=\"text-align:right\" name=\"betalt_til[$x]\" value=\"$betalt_til[$x]\"</td>";
				print "<td><input type=text size='25' style=\"text-align:right\" name=\"logintekst[$x]\" value=\"$logintekst[$x]\"</td>";
			}
			print "</tr>";
		} else {
#				if ($admin || in_array($r['id'],$adgang_til)) {
#					if ($beregn) echo "update regnskab set posteret='$posteringer[$x]' sidst='$sidst[$x]' where id='$id[$x]'<br>";
					if ($beregn) {
						$qtxt = "update regnskab set posteret='$posteringer[$x]',sidst='$sidst[$x]' where id='$id[$x]'";
						echo "$qtxt<br>";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					print "<tr><td align='right'> $id[$x]</td><td><a href=aaben_regnskab.php?db_id=$id[$x]>$regnskab[$x]</a></td>";
					print "<td>$brugerantal[$x]<br></td>";
					print "<td>$posteringer[$x]<br></td>";
					print "<td align='right'>$posteret[$x]<br></td>";
					print "<td align='right'>".date("d-m-Y",$sidst[$x])."<br></td>";
					print "<td align='center'>$lukket[$x]<br></td>";
					if ($saldiregnskab) {
						print "<td align='right'>$betalt_til[$x]<br></td>";
// 						print "<td align='right'>$lukkes[$x]<br></td>";
						print "<td align='right'>$logintekst[$x]<br></td>";
					}
					print "</tr>";
#				}
			}
			print "<input type=\"hidden\" name=\"db_antal\" value=\"$x\">";
#		}
#	}
}
if ($rediger) {
	if ($saldiregnskab) $colspan=10;
	else $colspan=7;
	print "<input type=hidden name=\"db_antal\" value=\"$x\">";
	print "<tr><td colspan=\"$colspan\" align=\"center\"><input type=\"submit\" value=\"".findtekst('898|Opdatér', $sprog_id)."\" name=\"submit\"></td></tr>";
	print "</form></tbody></table>";
} else {
	print "</tbody></table>";
	print "<a href=\"vis_regnskaber.php?beregn=1\">".findtekst('1916|Genberegn posteringer', $sprog_id)."</a>"; 
}
?>
</body></html>
