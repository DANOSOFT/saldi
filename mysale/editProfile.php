<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/editProfile.php --- lap 4.0.8 --- 2023-04-05	 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2021-2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20210829 PHR	varoius minor changes
// 20210908 PHR Password can now be empty.
// 20220212 PHR Cleanup
// 20230406 PHR	Minor changes in memberShip (only used by pos_38)

if ($accountId && isset($_POST['addr1'])) {	
	$qtxt = "update adresser set ";
#	$qtxt.= "password = '".db_escape_string(trim($_POST['pw1']))   ."' ";
	$qtxt.= "addr1    = '".db_escape_string(trim($_POST['addr1']))   ."', ";
	$qtxt.= "addr2    = '".db_escape_string(trim($_POST['addr2']))   ."', ";
	$qtxt.= "postnr   = '".db_escape_string(trim($_POST['zip']))  ."', ";
	$qtxt.= "bynavn   = '".db_escape_string(trim($_POST['city']))  ."', ";
	$qtxt.= "tlf    = '".db_escape_string(trim($_POST['phone']))   ."', ";
	$qtxt.= "email    = '".db_escape_string(trim($_POST['email']))   ."', ";
	$qtxt.= "bank_reg = '".db_escape_string(trim($_POST['bankReg']))."', ";
	$qtxt.= "bank_konto = '".db_escape_string(trim($_POST['bankKto']))."' ";
	$qtxt.= "where id = '$accountId'";
	db_modify($qtxt, __FILE__ . " line " . __LINE__);
}
if (isset($_POST['pw1'])) {	
	$pw1     = $_POST['pw1'];
	$pw2     = $_POST['pw2'];	
	if ($pw1 != '******' && $pw1 == $pw2) {
		($pw1)?$encPw = saldikrypt($accountId,$pw1):$encPw=NULL;

		$qtxt = "update adresser set password = '$encPw' where id = '$accountId'";
		db_modify($qtxt, __FILE__ . " line " . __LINE__);
		if ($pw1) $txt="Adgangskode opdateret";
		else $txt = "";
		print "<center><big><span style='color:red;'>$txt</span></big></center>";
	} elseif  ($pw1 == '******') {
		print "<center><big><span style='color:red;'>Adgangskode ikke ændret</span></big></center>";
	} else {
		print "<center><big><span style='color:red;'>Adgangskoder er ikke ens</span></big></center>";
	}
}
$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name='password'";
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	db_modify("ALTER table adresser ADD column password varchar(50)",__FILE__ . " linje " . __LINE__);
}
$memberShip = NULL;
$qtxt = "select var_value from settings where  var_name='medlemSetting' or var_name='memberShip'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) { #20220729
	$memberShip = $r['var_value'];
#	if ($db != 'pos_38') $memberShip= NULL;
}
$qtxt="select * from adresser where id = '$accountId'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	$accountNo = $r['kontonr'];
	$name      = $r['firmanavn'];
	$addr1     = $r['addr1'];
	$addr2     = $r['addr2'];
	$zip       = $r['postnr'];
	$city      = $r['bynavn'];
	$email     = $r['email'];
	$bankReg   = $r['bank_reg'];
	$bankKto   = $r['bank_konto'];
	($memberShip)?$member = $r['medlem']:$member = NULL;
	($memberShip)?$mCats = $r['kategori']:$mCats = NULL;
	$phone     = $r['tlf'];
	$password  = $r['password'];
	($password)?$showPw='******':$showPw=''; 
}
$memberOutput = 'Inaktiv';
if ($mCats || $mCats == '0') {
	$mCat = explode(chr(9),$mCats);
	$r=db_fetch_array(db_select("select box1,box2 from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
	$catId=explode(chr(9),$r['box1']);
	$catName=explode(chr(9),$r['box2']);
	if (in_array('Aktiv',$catName)) { #
		for ($x=0;$x<count($catId);$x++) {
			if (strtolower($catName[$x]) == 'aktiv' && in_array($catId[$x],$mCat)) $memberOutput = 'Aktiv';
		}
	}
}
/*
if (strpos($mCats, '1') !== false){
    $memberOutput = 'Aktiv';
}
else{
    $memberOutput = 'Inaktiv';
}
*/
($mobile)?$width='400':$width='200';
print "<center><table border = '0' style = 'width:". $width * 2 ."px'>";
print "<br>Her har du mulighed for at se dine stamoplysninger<br>og lægge adgangskode på din profil<br><br>";
print "<form action='mysale.php?id=$id&sort=$sort' method='post'>";
print "<input style='width:". $width * 2 ."px' value='Tilbage' type = 'submit'>";
print "</form>";
print "<form action='mysale.php?id=$id&sort=$sort&editProfile=1' method='post'>";
if ($memberShip) { # most be corrected by DAPE
	if ($memberOutput == 'Aktiv') {
		print "<tr><td  style = 'width:". $width ."px'>Status</td><td bgcolor='#90EE90'>$memberOutput</td></tr>";
	} else {
		print "<tr><td  style = 'width:". $width ."px'>Status</td><td bgcolor='#C85B5B'>$memberOutput</td></tr>";
	}
}
print "<tr bgcolor='$lineColor'><td  style = 'width:". $width ."px'>Kontonr</td><td>$accountNo</td></tr>";
print "<tr><td>Navn</td><td>$name</td></tr>";
print "<tr bgcolor='$lineColor'><td>Addresse</td>";
print "<td><input style=\"width:200px\" type=\"text\" name=\"addr1\" value=\"$addr1\"</td></tr>\n";
print "<tr><td></td><td><input style=\"width:200px\" type=\"text\" name=\"addr2\" value=\"$addr2\"</td></tr>\n";
print "<tr bgcolor='$lineColor'><td>Postnr / By</td>";
print "<td align=\"right\"><input style=\"width:50px\" type=\"text\" name=\"zip\" value=\"$zip\">";
print "<input style=\"width:150px\" type=\"text\" name=\"city\" value=\"$city\"</td></tr>\n";
print "<tr><td>tlf</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"phone\" value=\"$phone\"</td></tr>\n";
print "<tr bgcolor='$lineColor'><td>e-mail</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"email\" value=\"$email\"</td></tr>\n";
print "<tr><td>Bank Reg. nr: (Til udbetaling)</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bankReg\" value=\"$bankReg\"</td></tr>\n";
print "<tr bgcolor='$lineColor'><td>Bank Konto nr: (Til udbetaling)</td>";
print "<td align=\"right\"><input style=\"width:200px\" type=\"text\" name=\"bankKto\" value=\"$bankKto\"</td></tr>\n";
print "<tr><td>Password</td>";	
print "<td><input style='width:". $width ."px' name = 'pw1' type = 'text' value = '$showPw'</td></tr>";
print "<tr bgcolor='$lineColor'><td>Gentag password</td><td>";
print "<input style='width:". $width ."px' name = 'pw2' type = 'text' value = '$showPw'</td></tr>";
print "<tr><td colspan = '2' align='center'>";
print "<input style='width:". $width *2 ."px' name = 'save' value='Gem' type = 'submit'></td></tr>";
print "</form>";
print "</table></center>";
?>
