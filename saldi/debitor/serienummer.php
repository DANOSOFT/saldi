<?php
// --------- /debitor/serienummer.php-----------patch 3.5.8----2015.09.29----------
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
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20150929	Serienumre kan nu ændres og tilføjes.


@session_start();
$s_id=session_id();

$title="serienummer";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$batch_kob_id=if_isset($_GET['batch_kob_id'])*1;
$linje_id=if_isset($_GET['linje_id'])*1;

if ($_POST['submit']) {
	$submit=trim($_POST['submit']);
	$vare_id=$_POST['vare_id'];
	$kred_linje_id=$_POST['kred_linje_id'];
	$ordre_id=$_POST['ordre_id'];
	$antal=$_POST['antal'];
	$leveres=$_POST['leveres'];
	$serienr=$_POST["serienr"];
	$sn_id=$_POST['sn_id'];
	$sn_antal=$_POST['sn_antal'];
	$sn_tjek=$_POST['sn_tjek'];
	$valg=$_POST['valg'];
	$art=trim($_POST['art']);
	$retur_linje_id=$_POST['retur_linje_id'];

	if (!$sn_tjek) $sn_tjek=array();
	if ($_POST['status']<3)	{
		$y=0;
		if ($antal>0) {
			for ($x=1; $x<=$sn_antal; $x++) {
				if (trim($valg[$x])=="on" && $y<$antal) {
					$y++;
					db_modify("update serienr set serienr='$serienr[$x]',salgslinje_id='$linje_id' where id='$sn_id[$x]'",__FILE__ . " linje " . __LINE__);
				} elseif ($y>$antal) db_modify("update serienr set salgslinje_id=0 where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
				elseif ($sn_id[$x]) db_modify("update serienr set serienr='$serienr[$x]',salgslinje_id=0 where id='$sn_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
			if ($serienr[0]) {
				db_modify("insert into serienr (vare_id,kobslinje_id,salgslinje_id,batch_kob_id,batch_salg_id,serienr) values ('$vare_id','0','$linje_id','0','0','$serienr[0]')",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($art=='DK') {
			for ($x=1; $x<=$sn_antal; $x++) {
#cho "Valg $valg[$x]<br>";
				if (trim($valg[$x])=="on") {
					$y++;
#cho "update serienr set salgslinje_id=$kred_linje_id*-1 where id=$sn_id[$x]<br>";
					db_modify("update serienr set salgslinje_id=$kred_linje_id*-1 where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
				} elseif ($sn_id[$x]) {
#cho "update serienr set salgslinje_id=$kred_linje_id where id=$sn_id[$x]<br>";
					db_modify("update serienr set salgslinje_id=$kred_linje_id where id=$sn_id[$x]",__FILE__ . " linje " . __LINE__);
				}
			}
		} else {
			for ($x=1; $x<=$sn_antal; $x++) {
				if (trim($valg[$x])=="on") {
					$y++;
					if ($retur_linje_id[$x] > 0) {
						db_modify("update serienr set salgslinje_id=$retur_linje_id[$x]*-1 where id='$sn_id[$x]'",__FILE__ . " linje " . __LINE__);
#						db_modify("insert into serienr (vare_id,kobslinje_id,salgslinje_id,batch_kob_id,batch_salg_id,serienr) values ('$vare_id','$linje_id','0','0','0','$serienr')",__FILE__ . " linje " . __LINE__);
					}
				} elseif ($sn_id[$x]) {
					db_modify("update serienr set salgslinje_id=".abs($retur_linje_id[$x])." where id='$sn_id[$x]'",__FILE__ . " linje " . __LINE__);
#					db_modify("delete from serienr where linje_id='$linje_id' and serienr='$serienr'",__FILE__ . " linje " . __LINE__);
				}
			}
		} 
	}
}
#cho "submit $submit<br>";
if ($submit=="Luk") print "<body onload=\"javascript:window.close();\">";

$antal=0;
$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
if ($row = db_fetch_array($query)) {
	$ordre_id=$row['ordre_id'];
	$antal=$row['antal'];
	$leveres=$row['leveres'];
	$posnr=$row['posnr'];
	$vare_id=$row['vare_id'];
	$varenr=$row['varenr'];
	$kred_linje_id=$row['kred_linje_id'];

	$query = db_select("select konto_id,status, art from ordrer where id = '$ordre_id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$konto_id=$row['konto_id'];
	$status=$row['status'];
	$art=$row['art'];
}

print "<table cellpadding=\"0\" cellspacing=\"0\" border=\"1\" valign = \"top\" align=\"center\"><tbody>";
print "<tr><td align=center><big><b>Posnr: $posnr - Varenr: $varenr</td></tr>";
print "<form name=ordre serienr.php?linje_id=$linje_id method=post>";
print "<tr><td><table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" valign = \"top\" align=\"center\" width=\"100%\"><tbody>";
print "<tr><td><b>Serienr.</td><td align=\"center\"><b>K&oslash;bsordre</td></tr>";
print "<tr><td colspan=3><hr></td></tr>";
if ($antal>0) {
	$x=0;
	$kl_id=array();
	$q = db_select("select kobslinje_id from serienr where vare_id='$vare_id'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kl_id[$x]=$r['kobslinje_id'];
		$x++;
	}
	$q = db_select("select * from batch_kob where vare_id='$vare_id' and rest > '0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if (!in_array($r['linje_id'],$kl_id)) {
			for ($x=0;$x<$r['rest'];$x++) {
				db_modify("insert into serienr (vare_id,kobslinje_id,salgslinje_id,batch_kob_id,batch_salg_id,serienr) values ('$vare_id','$r[linje_id]','0','0','0','')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
	$sn_antal=0;
	if ($art=='DO' && !db_fetch_array(db_select("select * from serienr where vare_id='$vare_id' and batch_salg_id > 0 and salgslinje_id='$linje_id' order by serienr",__FILE__ . " linje " . __LINE__))){
		if ($r=db_fetch_array(db_select("select batch_kob.linje_id from batch_kob,batch_salg where batch_salg.linje_id='$linje_id' and batch_kob.id=batch_salg.batch_kob_id",__FILE__ . " linje " . __LINE__))) {
			db_modify("update serienr set salgslinje_id='$linje_id' where kobslinje_id='$r[linje_id]' and salgslinje_id='0'",__FILE__ . " linje " . __LINE__);
		}
	}
	$query = db_select("select * from serienr where vare_id=$vare_id and batch_salg_id > 0 and salgslinje_id=$linje_id order by serienr",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)) {
		$kobsordre=db_fetch_array(db_select("select ordre_id from ordrelinjer where id='$row[kobslinje_id]'",__FILE__ . " linje " . __LINE__));
		$kobsordre=$kobsordre['ordre_id'];
		$sn_antal++;
		print "<tr><td>$row[serienr]</td><td onClick=\"window.open('../kreditor/ordre.php?id=$kobsordre')\"; align=right>$kobsordre</td></tr>";
	}
	if ($sn_antal<$antal){
		$gem=1;
		
		$q = db_select("select * from serienr where batch_kob_id='$batch_kob_id' and vare_id='$vare_id' and (salgslinje_id=0 or salgslinje_id='$linje_id') order by serienr",__FILE__ . " linje " . __LINE__);
		$x=0;
		while ($r = db_fetch_array($q)) {
			$r2=db_fetch_array(db_select("select ordrer.ordrenr, ordrer.id from ordrelinjer,ordrer where ordrelinjer.id='$r[kobslinje_id]' and ordrer.id=ordrelinjer.ordre_id",__FILE__ . " linje " . __LINE__));
			$kobsordre_id=$r2['id'];
			$kobsordre=$r2['ordrenr'];
			if ($status < 3) {
				$sn_antal++;
				$tmp='';
				if ($r['salgslinje_id']>0) $tmp= "checked";
				print "<tr><td><input type=\"text\" name=\"serienr[$sn_antal]\" value=\"$r[serienr]\"></td>";
				print "<td onClick=\"window.open('../kreditor/ordre.php?id=$kobsordre_id')\"; align=\"center\"><u>$kobsordre</u></td>";
				print "<td><input type=\"checkbox\" name=\"valg[$sn_antal]\" $tmp></td></tr>";
				print "<input type=\"hidden\" name=\"sn_id[$sn_antal]\" value=\"$r[id]\">";
				#print "<input type=\"hidden\" name=\"serienr[$sn_antal]\" value='$r[serienr]'>";
			} else {
				if ($r['salgslinje_id']>0){print "<tr><td>$r[serienr]</td></tr>";}
			}
			$x++;
		} if (!$x){
			print "<tr><td><input type=\"text\" name=\"serienr[$sn_antal]\" value=\"\"></td>";
			print "<td></td>";
			print "<td><input type=\"checkbox\" name=\"valg[$sn_antal]\" checked></td></tr>";
			print "<input type=\"hidden\" name=\"sn_id[$sn_antal]\" value=\"0\">";
		}
		
	}
} else { #Varer tages retur - evt kreditnota;
	$sn_antal=0;
	$tmp=$kred_linje_id*-1;
	if ($art=='KO') {
		$qtxt="select * from serienr where salgslinje_id!='$kred_linje_id' and (salgslinje_id='$kred_linje_id' or salgslinje_id='$tmp') order by serienr";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($q)) {
			$r2 =db_fetch_array(db_select("select id from serienr where salgslinje_id='$linje_id' and serienr='$row[serienr]' order by id desc",__FILE__ . " linje " . __LINE__));
			if (($status < 3)&&(!$r2['id'])) {
			$sn_antal++;
			$tmp='';
			if ($row['salgslinje_id']<0) $tmp= "checked";
			print "<tr><td>$row[serienr]</td><td><input type=\"checkbox\" name=\"valg[$sn_antal]\" $tmp></td></tr> ";
			print "<input type=\"hidden\" name=\"sn_id[$sn_antal]\" value=\"$row[id]\">";
			print "<input type=\"hidden\" name=\"serienr[$sn_antal]\" value=\"$row[serienr]\">";
		} else {
			if ($row['salgslinje_id']<0){print "<tr><td>$row[serienr]</td></tr>";}
		}
	}
} else {
		$qtxt="select serienr.salgslinje_id,serienr.id,serienr.serienr from serienr,ordrer,ordrelinjer where ordrer.art='DO'";
		$qtxt.=" and ordrer.status>='3' and ordrer.konto_id='$konto_id' and ordrelinjer.ordre_id=ordrer.id and ordrelinjer.vare_id='$vare_id'";
		$qtxt.=" and abs(serienr.salgslinje_id)=ordrelinjer.id";
		$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$sn_antal++;
			if ($r['salgslinje_id']<0) $tmp= "checked";
			print "<tr><td>$r[serienr]</td><td><input type=\"checkbox\" name=\"valg[$sn_antal]\" $tmp></td></tr> ";
			print "<input type=\"hidden\" name=\"sn_id[$sn_antal]\" value=\"$r[id]\">";
			print "<input type=\"hidden\" name=\"serienr[$sn_antal]\" value=\"$r[serienr]\">";
			print "<input type=\"hidden\" name=\"retur_linje_id[$sn_antal]\" value=\"$r[salgslinje_id]\">";
		}
	}
}

print "</td></tr>";
print "<input type=\"hidden\" name=\"antal\" value='$antal'>";
print "<input type=\"hidden\" name=\"kred_linje_id\" value='$kred_linje_id'>";
print "<input type=\"hidden\" name=\"vare_id\" value='$vare_id'>";
print "<input type=\"hidden\" name=\"ordre_id\" value='$ordre_id'>";
print "<input type=\"hidden\" name=\"sn_antal\" value='$sn_antal'>";
print "<input type=\"hidden\" name=\"leveres\" value='$leveres'>";
print "<input type=\"hidden\" name=\"status\" value='$status'>";
print "<input type=\"hidden\" name=\"art\" value='$art'>";
print "</tbody></table>";
print "<tr><td align=center><input type=submit value=\"Luk\" name=\"submit\">&nbsp;<input type=submit value=\"Gem\" name=\"submit\"></td></tr>";
print "</form> </tr>";
print "</td></tr></tbody></table>";
print "</form>";

?>
