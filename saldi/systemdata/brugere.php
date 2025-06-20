<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/brugere.php------------- lap 3.7.9 -- 2019-04-15 --
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
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------
// 20150327 CA  Topmenudesign tilføjet                             søg 20150327
// 20161104	PHR	Ændret kryptering af adgangskode
// 2018.12.20 MSC - Rettet isset fejl
// 2019.02.21 MSC - Rettet topmenu design
// 2019.02.25 MSC - Rettet topmenu design
// 2019.03.21 PHR Added 'read only' attribut at 'varekort'
// 2019.04.15 PHR	Corrected an error in module order printet on screen, resulting in wrong rights to certain modules

@session_start();
$s_id=session_id();

$modulnr=1;
$title="Brugere";
$css="../css/standard.css";

$ansat_id=$rights=$roRights=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset ($colbg)) $colbg = NULL;
$modules=array('kontoplan','indstillinger','kassekladde','regnskab','finansrapport','debitorordre','debitorkonti','kreditorordre','kreditorkonti','varer','enheder','backup','debitorrapport','kreditorrapport','produktionsordre','varerapport');


if ($menu=='T') {  # 20150327 start
        include_once '../includes/top_header.php';
        include_once '../includes/top_menu.php';
        print "<div id=\"header\">\n";
        print "<div class=\"headerbtnLft\"></div>\n";
        print "</div><!-- end of header -->";
        print "<div id=\"leftmenuholder\">";
        include_once 'left_menu.php';
        print "</div><!-- end of leftmenuholder -->\n";
		print "<div class=\"maincontentLargeHolder\">\n";
        print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\"><tbody>";
} else {
	include("top.php");
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>"; 
}  # 20150327 stop

$ret_id=if_isset($_GET['ret_id']);
$slet_id=if_isset($_GET['slet_id']);

if (isset($_POST['submit'])) {
	$submit=if_isset($_POST['submit']);
	$id=if_isset($_POST['id']);
	$tmp=if_isset($_POST['random']);
	$brugernavn=trim(if_isset($_POST[$tmp]));
	$kode=trim(if_isset($_POST['kode']));
	$kode2=trim(if_isset($_POST['kode2']));
	$medarbejder=trim(if_isset($_POST['medarbejder']));
	$ansat_id=if_isset($_POST['ansat_id']);
	$rights=$_POST['rights'];
	$roRights=$_POST['roRights'];
	$rettigheder=NULL;
	for ($x=0;$x<16;$x++) {
		if (!isset($rights[$x])) $rights[$x]=NULL;
		if (!isset($roRights[$x])) $roRights[$x]=NULL;
		if ($roRights[$x]=='on') $rettigheder.='2';
		elseif ($rights[$x]=='on') $rettigheder.='1';
		else $rettigheder.='0';
	}
	$brugernavn=trim($brugernavn);
	if ($kode && $kode != $kode2) {
		$alerttext="Adgangskoder er ikke ens";
		print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
		$kode=NULL;
		$ret_id=$id;
	}
	$tmp=substr($medarbejder,0,1);
	$ansat_id[0]=$ansat_id[0]*1;
	if ((strstr($submit,'Tilf'))&&($brugernavn)) {
		$query = db_select("select id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$alerttext="Der findes allerede en bruger med brugenavn: $brugernavn!";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
#			print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $brugernavn!</td></tr>";
		}	else {
			if (!$regnaar) $regnaar=1;
			db_modify("insert into brugere (brugernavn,kode,rettigheder,regnskabsaar,ansat_id) values ('$brugernavn','$kode','$rettigheder','$regnaar',$ansat_id[0])",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from brugere where brugernavn = '$brugernavn' and kode = '$kode'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
	}
	if ($id && $kode) {
		if (strstr($kode,'**********')) {
			db_modify("update brugere set brugernavn='$brugernavn', rettigheder='$rettigheder', ansat_id=$ansat_id[0] where id=$id",__FILE__ . " linje " . __LINE__);
		} else {
			$kode=saldikrypt($id,$kode);
			db_modify("update brugere set brugernavn='$brugernavn', kode='$kode', rettigheder='$rettigheder', ansat_id=$ansat_id[0] where id=$id",__FILE__ . " linje " . __LINE__);
		}
	}
	elseif (($id)&&(!$kode)) {
		if ($ansat_id[0]) db_modify("update ansatte set lukket='on', slutdate='".date("Y-m-d")."' where id = '$ansat_id[0]'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from brugere where id = $id",__FILE__ . " linje " . __LINE__);
	}
}

print "<tr><td valign = 'top'>";
print "<table border=0><tbody><tr><td>"; # 20150327
print "<form name='bruger' action='brugere.php' method='post'>";
print "<table cellpadding='0' cellspacing='0' border='0' width='70%'><tbody>"; #B

print "<tr><td colspan='2'></td>";
print str_repeat("<td align='center' width='8px'><br></td>", 30);
print "</tr>";
$modules=array('Sikkerhedskopi','Debitorrapport','Varemodtagelse','Kreditorrapport','Varelager','Produktionsordrer','Kreditorkonti','Varerapport','Kreditorordrer','Debitorkonti','Debitorordrer','Finansrapport','Regnskab','Kassekladde','Indstillinger','Kontoplan');

$cs=14;
for ($x=0;$x<count($modules);$x++) {
print "<tr><td colspan = '$cs' align='right'> $modules[$x] &nbsp;</td>";
	if ($x <= 6) {
		print str_repeat("<td align='center'>|</td>",$x);
		$x++;
		print "<td colspan = '$cs' align='left'> &nbsp;$modules[$x]</td></tr>";
	} 
	else {
		print str_repeat("<td align='center'>|</td>",$x);
	}
	$cs--;
}
print "<tr><td colspan = $cs align='right'> &nbsp;</td>"; print str_repeat("<td align=center>|</td>", $x); 
print "<td colspan=9></td></tr>";

print "<tr><td><b>Navn &nbsp;</b></td><td><b>Brugernavn</b></td></tr>";
$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['id']!=$ret_id) {
		if ($row['ansat_id']) {
			$r2 = db_fetch_array(db_select("select initialer from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
		}	else {$r2['initialer']='';}
		print "<tr><td> $r2[initialer]&nbsp;</td><td><a href=brugere.php?ret_id=$row[id]> $row[brugernavn]</a></td>";
		for ($y=0; $y<=15; $y++) {
			($colbg!=$bgcolor)?$colbg=$bgcolor:$colbg=$bgcolor5;
			if ((substr($row['rettigheder'],$y,1)==2)) $color='yellow';
			elseif ((substr($row['rettigheder'],$y,1)==1)) $color='green';
			else $color='red';
			print "<td align='center' bgcolor='$colbg'><span style=\"color:$color;\"><big>*</big></span></td>";
		}
		print "</tr>";
	}
}
if ($ret_id) {
	$query = db_select("select * from brugere where id = $ret_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	print "<tr><td></td>";
	print "<input type=hidden name=id value=$row[id]>";
	$tmp="navn".rand(100,999);				#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value=$tmp>";	#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<td><input class='inputbox' type='text' size=20 name='$tmp' value=\"$row[brugernavn]\"></td>";
	print "</tr><tr><td></td><td>Adgang til</td>\n";
	for ($x=0;$x<16;$x++) {
		(substr($row['rettigheder'],$x,1)>=1)?$checked='checked':$checked=NULL;
		print "<td><input class='inputbox' type='checkbox' name=\"rights[$x]\" $checked>\n</td>";
	}
	print "</tr><tr><td></td><td>Kun se</td>";
	for ($x=0;$x<16;$x++) {
		(substr($row['rettigheder'],$x,1)==2)?$checked='checked':$checked=NULL;
		print "<td>";
		if ($x==9) print "<input class='inputbox' type='checkbox' name=\"roRights[$x]\" $checked>\n";
		else {
			print "<input disabled='disabled' class='inputbox' type='checkbox' name='roRights[$x]'>\n";
#			print "<input type=hidden name='roRights[$x]' value=''>\n";
		}
		print "</td>";
	}
	print "</tr>";
	print "<tr><td>Adgangskode</td><td><input class=\"inputbox\" type=password size=20 name=kode value='********************'></td></tr>";
	print "<tr><td>Gentag kode</td><td><input class=\"inputbox\" type=password size=20 name=kode2 value='********************'></td></tr>";
	$x=0;
	if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
		$ansat_id=array();
		$q2 = db_select("select * from ansatte where konto_id = $r2[id]  and lukket!='on' order by initialer",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$ansat_id[$x]=$r2['id'];
			$ansat_initialer[$x]=$r2['initialer'];
			if ($ansat_id[$x]==$row['ansat_id']) {
				$ansat_id[0]=$ansat_id[$x];
				$ansat_initialer[0]=$ansat_initialer[$x];
			}		 
#			print "<input type = hidden name=ansat_id[$x] value=$ansat_id[$x]>";
		}
	}
	$ansat_antal=$x;
	print "<tr><td> Medarbejder</td>";
	print "<td><SELECT NAME=ansat_id[0]>";
	print "<option value=\"$ansat_id[0]\">$ansat_initialer[0]</option>";
	for ($x=1; $x<=$ansat_antal; $x++) { 
		print "<option value=\"$ansat_id[$x]\">$ansat_initialer[$x]</option>";
	} 
	if ($medarbejder) print "<option></option>";
	print "</SELECT></td></tr>";
	print "</tbody></table></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	if ($menu=='T') {
		$class = "class='button blue medium'";
	} else {
		$class = "class='inputbox'";
	}
	print "<td colspan=12 align = center><input $class type=submit value=\"Opdat&eacute;r\" name=\"submit\"></td>";
} else {
	$tmp="navn".rand(100,999);				#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value = $tmp>";
	print "<tr><td> Ny&nbsp;bruger</td>";
	print "<td><input class=\"inputbox\" type=\"text\" size='20' name='$tmp'></td>";
	print "</tr><tr><td></td><td>Adgang</td>";
	for ($x=0;$x<16;$x++) {
		print "<td><input class='inputbox' type='checkbox' name=\"rights[$x]\"></td>\n";
	}
	print "</tr><tr><td></td><td>Kun se</td>";
	for ($x=0;$x<16;$x++) {
		print "<td>";
		if ($x==9) print "<input class='inputbox' type='checkbox' name='roRights[$x]'>\n";
		else {
			print "<input disabled='disabled' class='inputbox' type='checkbox' name='roRights[$x]'>\n";
		}
		print "</td>";
	}
	print "</tr>";
	print "<tr><td> Adgangskode</td><td><input class=\"inputbox\" type=password size=20 name=kode></td></tr>";
	print "<tr><td> Gentag kode</td><td><input class=\"inputbox\" type=password size=20 name=kode2></td></tr>";
	print "</tbody></table></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td colspan=12 align = center><input class='button green medium' type=submit value=\"Tilf&oslash;j\" name=\"submit\"></td>";
}
print "</tr>";
# print "</tbody></table></td></tr>";

?>
</tbody>
</table>
</td></tr>
</tbody></table>
<?php if ($menu=='T') print "</div> <!-- end of maincontent -->\n";  # 20150327 ?>
</body></html>
