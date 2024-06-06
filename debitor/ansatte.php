<?php

// --- debitor/ansatte.php --- lap 3.0.7 --- 2022.12.28----------
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
// Copyright (c) 2003-2022 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20221229 PHR Some cleanup

@session_start();
$s_id=session_id();

$modulnr=6;
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

 if ($_GET){
 	 $id = $_GET['id'];
 	 $returside= $_GET['returside'];
 	 $ordre_id = $_GET['ordre_id'];
 	 $fokus = $_GET['fokus'];
 	$konto_id=$_GET['konto_id'];
 }

if ($_POST){
 	$id=$_POST['id'];
 	$submit=addslashes(trim($_POST['submit']));
 	$konto_id=$_POST['konto_id'];
 	$navn=addslashes(trim($_POST['navn']));
 	$addr1=addslashes(trim($_POST['addr1']));
 	$addr2=addslashes(trim($_POST['addr2']));
 	$postnr=addslashes(trim($_POST['postnr']));
 	$bynavn=addslashes(trim($_POST['bynavn']));
 	$tlf=addslashes(trim($_POST['tlf']));
 	$fax=addslashes(trim($_POST['fax']));
 	$mobil=addslashes(trim($_POST['mobil']));
 	$email=addslashes(trim($_POST['email']));
 	$cprnr=addslashes(trim($_POST['cprnr']));
 	$notes=addslashes(trim($_POST['notes']));
 	$ordre_id = $_GET['ordre_id'];
 	$returside=$_POST['returside'];
 	$fokus=$_POST['fokus'];

 	if ($submit=="Slet") {
 	 	if ($id) db_modify("delete from ansatte where id = '$id'",__FILE__ . " linje " . __LINE__); 
 		print "<meta http-equiv=\"refresh\" content=\"0;URL=debitorkort.php?returside=$returside&ordre_id=$ordre_id&id=$konto_id&fokus=$fokus\">";
 	} else {
		if ($postnr && !$bynavn) $bynavn=bynavn($postnr);
 	 	if (($id==0)&&($navn)){
 	 	 	$query = db_modify("insert into ansatte (navn, konto_id, addr1, addr2, postnr, bynavn, tlf, fax, mobil, email, cprnr, notes, lukket) values ('$navn', '$konto_id', '$addr1', '$addr2', '$postnr', '$bynavn', '$tlf', '$fax', '$mobil', '$email', '$cprnr', '$notes', '')",__FILE__ . " linje " . __LINE__);
 	 	 	$query = db_select("select id from ansatte where konto_id = '$konto_id' and navn='$navn' order by id desc",__FILE__ . " linje " . __LINE__);
 	 	 	$row = db_fetch_array($query);
 	 	 	$id = $row['id'];
 	 	}
 	 	elseif ($id > 0){
			db_modify("update ansatte set navn = '$navn', konto_id = '$konto_id', addr1 = '$addr1', addr2 = '$addr2', postnr = '$postnr', bynavn = '$bynavn', email = '$email', tlf = '$tlf', fax = '$fax', mobil = '$mobil', cprnr = '$cprnr', notes = '$notes', lukket = '' where id = '$id'",__FILE__ . " linje " . __LINE__);
 	 	}
 	}
}

$query = db_select("select firmanavn from adresser where id = '$konto_id'",__FILE__ . " linje " . __LINE__);
$row = db_fetch_array($query);

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
print "<td width=\"10%\" $top_bund>$font<a href=debitorkort.php?returside=$returside&id=$konto_id&fokus=$fokus accesskey=L>Luk</a></td>";
print "<td width=\"80%\" $top_bund>$font$row[firmanavn] - Ansatte</td>";
print "<td width=\"10%\" $top_bund>$font<a href=ansatte.php?returside=$returside&ordre_id=$ordre_id&fokus=$fokus&konto_id=$konto_id accesskey=N>Ny</a><br></td>";
print "</tbody></table>";
print "</td></tr>";
print "<td align = center valign = center>";
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";


if ($id > 0){
 	$query = db_select("select * from ansatte where id = '$id'",__FILE__ . " linje " . __LINE__);
 	$row = db_fetch_array($query);
 	$konto_id=$row['konto_id'];
 	$navn=htmlentities($row['navn'],ENT_COMPAT,$charset);
 	$addr1=htmlentities($row['addr1'],ENT_COMPAT,$charset);
 	$addr2=htmlentities($row['addr2'],ENT_COMPAT,$charset);
 	$postnr=htmlentities($row['postnr'],ENT_COMPAT,$charset);
 	$bynavn=htmlentities($row['bynavn'],ENT_COMPAT,$charset);
 	$email=htmlentities($row['email'],ENT_COMPAT,$charset);
 	$tlf=htmlentities($row['tlf'],ENT_COMPAT,$charset);
 	$fax=htmlentities($row['fax'],ENT_COMPAT,$charset);
 	$mobil=htmlentities($row['mobil'],ENT_COMPAT,$charset);
 	$cprnr=htmlentities($row['cprnr'],ENT_COMPAT,$charset);
 	$notes=htmlentities($row['notes'],ENT_COMPAT,$charset);
}
else{$id=0;}
print "<form name=ansatte action=ansatte.php method=post>";
print "<input type=hidden name=id value=\"$id\">";
print "<input type=hidden name=konto_id value=\"$konto_id\">";
print "<input type=hidden name=ordre_id value=\"$ordre_id\">";
print "<input type=hidden name=returside value=\"$returside\">";
print "<input type=hidden name=fokus value=\"$fokus\">";


print "<td>Navn</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=navn value=\"$navn\"></td></tr>";
print "<tr><td>Adresse</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=addr1 value=\"$addr1\"></td>";
print "<td><br></td>";
print "<td>Adresse2</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=addr2 value=\"$addr2\"></td></tr>";
print "<tr><td>Postnr</td><td><br></td><td><input class=\"inputbox\" type=text size=6 name=postnr value=\"$postnr\"></td>";
print "<td><br></td>";
print "<td>By</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=bynavn value=\"$bynavn\"></td></tr>";
print "<tr><td>E-mail</td><td><br></td><td><input class=\"inputbox\" type=text size=25 name=email value=\"$email\"></td>";
print "<td><br></td>";
#print "<td>CVR. nr.</td><td><br></td><td><input type=text size=10 name=cprnr value=\"$cprnr\"></td></tr>";
print "<td>Mobil</td><td><br></td><td><input class=\"inputbox\" type=text size=10 name=mobil value=\"$mobil\"></td></tr>";
print "<tr><td>Lokalnr.</td><td><br></td><td><input class=\"inputbox\" type=text size=10 name=tlf value=\"$tlf\"></td>";
print "<td><br></td>";
print "<td>Lokal fax</td><td><br></td><td><input type=text class=\"inputbox\" size=10 name=fax value=\"$fax\"></td></tr>";
print "<td><br></td>";
print "<tr><td valign=top>Bem&aelig;rkning</td><td colspan=7><textarea class=\"inputbox\" name=\"notes\" rows=\"3\" cols=\"85\">$notes</textarea></td></tr>";
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";
print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem\" name=\"submit\"></td><td><br></td><td align = center><input type=submit accesskey=\"s\" value=\"Slet\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
<tr><td align = "center" valign = "bottom">
 	 	<table width="100%" align="center" border="0" cellspacing="0" cellpadding="0"><tbody>
 	 	 	<td width="100%"><font face="Helvetica, Arial, sans-serif" color="#000066"><div class=top_bund><br></div></td>
 	 	</tbody></table>
</td></tr>
</tbody></table>
</body></html>
