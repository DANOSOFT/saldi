<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/brugere.php-----patch 4.0.8 ----2023-07-23-----
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20150327 CA  - Topmenudesign tilføjet                             søg 20150327
// 20161104	PHR	- Ændret kryptering af adgangskode
// 20181220 MSC - Rettet isset fejl
// 20190221 MSC - Rettet topmenu design
// 20190225 MSC - Rettet topmenu design
// 20190321 PHR - Added 'read only' attribut at 'varekort'
// 20190415 PHR - Corrected an error in module order printet on screen, resulting in wrong rights to certain modules
// 20200709 PHR - Various changes in variable names and user deletion.
// 20210711 LOE - Translated some texts to Norsk and English from Dansk
// 20210828 LOE - Added a functionality to enable users select language from user's page
// 20210831 LOE - Added more funtionalities
// 20210901 LOE - This block of code added to authenticate user IP
// 20210908 LOE - Added input box for IP addresses
// 20210909 LOE - Modified some codes relating to Ip
// 20211015 LOE - Modified some codes to adjust to IP moved to settings table
// 20220514 MSC - Implementing new design
// 20230316 PHR Replaced *1 by (int)

@session_start();
$s_id=session_id();

$modulnr=1;
$title="Brugere";
$css="../css/standard.css";

$employeeId=$rights=$roRights=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");

if (!isset ($colbg)) $colbg = NULL;
$da = str_replace(" ", "",(findtekst('1141|Debitorapport', $sprog_id)));
$ka = str_replace(" ", "",(findtekst('1140|Kreditorapport', $sprog_id)));

$kontoplan   	  =  lcfirst(findtekst('113|Kontoplan', $sprog_id));		 $indstillinger	  = lcfirst(findtekst('122|Indstillinger', $sprog_id)); #20210711
$kassekladde 	  =  lcfirst(findtekst('601|Kassekladde', $sprog_id));		 $regnskab		  = lcfirst(findtekst('322|Regnskab', $sprog_id));
$finansrapport    =  lcfirst(findtekst('895|Finansrapport', $sprog_id));	 $debitorordre	  = lcfirst(findtekst('1255|Debitorordre', $sprog_id));
$debitorkonti     =  lcfirst(findtekst('1256|Debitorkonti', $sprog_id)); 	 $kreditorordre   = lcfirst(findtekst('1257|Kreditorordre', $sprog_id));
$kreditorkonti    =  lcfirst(findtekst('1258|Kreditorkonti', $sprog_id));	 $varer 		  = lcfirst(findtekst('609|Varer', $sprog_id));
$enheder		  =  lcfirst(findtekst('1259|Enheder', $sprog_id));			 $backup		  = lcfirst(findtekst('521|Sikkerhedskopi', $sprog_id));
$debitorrapport   =  lcfirst($da);											 $kreditorrapport = lcfirst($ka);
$produktionsordre =  lcfirst(findtekst('1260|Produktionsordre', $sprog_id)); $varerapport	  = lcfirst(findtekst('965|Varerapport', $sprog_id));



$modules=array($kontoplan,$indstillinger,$kassekladde,$regnskab,$finansrapport,$debitorordre,$debitorkonti,
$kreditorordre,$kreditorkonti,$varer,$enheder,$backup,
$debitorrapport,$kreditorrapport,$produktionsordre,$varerapport);
#$modules=array('kontoplan','indstillinger','kassekladde','regnskab','finansrapport','debitorordre','debitorkonti','kreditorordre','kreditorkonti','varer','enheder','backup','debitorrapport','kreditorrapport','produktionsordre','varerapport');


if ($menu=='T') {  # 20150327 start
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
    print "<div id=\"leftmenuholder\">";
    include_once 'left_menu.php';
    print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class='divSys'>";
    print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTableSys\" width='100%'><tbody>";
} else {
	include("top.php");
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" align=\"center\"><tbody>"; 
}  # 20150327 stop

$ip_address = if_isset($_SERVER['REMOTE_ADDR']);
$proxy_ip = if_isset($_SERVER['HTTP_X_FORWARDED_FOR']);
$client_ip = if_isset($_SERVER['HTTP_CLIENT_IP']); #20210828

$addUser=if_isset($_POST['addUser']);
$deleteUser=if_isset($_POST['deleteUser']);
$id=if_isset($_POST['id']);
$updateUser=if_isset($_POST['updateUser']);
$ret_id=if_isset($_GET['ret_id']);
$slet_id=if_isset($_GET['slet_id']);
$yd = get_ip(); #20211015

#var_dump($yd, $db);
if ($addUser || $updateUser) {
	$tmp=if_isset($_POST['random']);
	$brugernavn=trim(if_isset($_POST[$tmp]));
	$kode=trim(if_isset($_POST['kode']));
	$kode2=trim(if_isset($_POST['kode2']));
	$tlf=trim(if_isset($_POST['tlf']));
	$medarbejder=trim(if_isset($_POST['medarbejder']));
	$employeeId=if_isset($_POST['employeeId']);
	// $restore_user = if_isset($_POST['ruser_ip']); #20210831
	$insert_ip = if_isset($_POST['insert_ip']); #20210908
	// $user_ip = if_isset($_POST['user_ip']); #20210831
	 $re_id=if_isset($_POST['re_id']); #20210909
	if($insert_ip){
	$user_ip=$insert_ip;
	// input_ip($user_ip, $id);
	} #20210908
	$afd = if_isset($_POST['afdeling']);
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
	$employeeId[0]=(int)$employeeId[0];
	if ($addUser && $brugernavn) {
		$query = db_select("select id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__);
		if ($row = db_fetch_array($query)) {
			$alerttext="Der findes allerede en bruger med brugenavn: $brugernavn!";
			print "<BODY onLoad=\"javascript:alert('$alerttext')\">";
#			print "<tr><td align=center>Der findes allerede en bruger med brugenavn: $brugernavn!</td></tr>";
		}	else {
			if (!$regnaar) $regnaar=1;
			$qtxt = "insert into brugere (brugernavn,kode,rettigheder,regnskabsaar,ansat_id,ip_address,tlf) ";
			$qtxt.= "values ('$brugernavn','$kode','$rettigheder','$regnaar',$employeeId[0],'$insert_ip','$tlf')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="select id from brugere where brugernavn = '$brugernavn' and kode = '$kode'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
	}
	if ($id && $kode && $brugernavn) {
		if (strstr($kode,'**********')) {
			db_modify("update brugere set brugernavn='$brugernavn', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf' where id=$id",__FILE__ . " linje " . __LINE__);
			update_settings_value('afd', 'brugerAfd', $afd, '', $id);
		} else {
			$kode=saldikrypt($id,$kode);
			db_modify("update brugere set brugernavn='$brugernavn', kode='$kode', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf' where id=$id",__FILE__ . " linje " . __LINE__);
			update_settings_value('afd', 'brugerAfd', $afd, '', $id);
		}
	}
	// if($restore_user){
	// 	restore_user_ip($restore_user, $re_id); #20210831 + 20210909
	// }
	

} elseif (($deleteUser)) {
	$qtxt="select ansat_id from brugere where id ='$id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['ansat_id']) { 
		$qtxt="update ansatte set lukket='on', slutdate='".date("Y-m-d")."' where id = '$r[ansat_id]'";
		db_modify($qtxt,__FiLE__ . " linje " . __LINE__);
	}
	db_modify("delete from brugere where id = $id",__FILE__ . " linje " . __LINE__);
}

print "<tr><td valign = 'top'>";
print "<table border=0 width='100%'><tbody><tr><td>"; # 20150327
print "<form name='bruger' action='brugere.php' method='post'>";
if ($menu=='T') {
	print "<table cellpadding='0' cellspacing='0' border='0' width='100%' class='dataTableSys'><tbody>"; #B
} else {
	print "<table cellpadding='0' cellspacing='0' border='0' width='70%'><tbody>"; #B
}

print "<tr><td colspan='2'></td>";
print str_repeat("<td align='center' width='8px'><br></td>", 30);
print "</tr>";
$da = str_replace(" ", "",(findtekst('1141|Debitorapport', $sprog_id))); #20210711
$ka = str_replace(" ", "",(findtekst('1140|Kreditorapport', $sprog_id)));

$Sikkerhedskopi = findtekst('521|Sikkerhedskopi', $sprog_id);   $Debitorrapport	   = findtekst('449|Debitorrapporter', $sprog_id);
$Varemodtagelse = findtekst('182|Varemodtagelse', $sprog_id);   $Kreditorrapport   = $ka;
$Varelager      = findtekst('1261|Varelager', $sprog_id);		$Produktionsordrer = findtekst('1260|Produktionsordre', $sprog_id);
$Kreditorkonti  = findtekst('1258|Kreditorkonti', $sprog_id);	$Varerapport	   = findtekst('965|Varerapport', $sprog_id);
$Kreditorordrer = findtekst('1257|Kreditorordre', $sprog_id);	$Debitorkonti	   = findtekst('1256|Debitorkonti', $sprog_id);
$Debitorordrer  = findtekst('1255|Debitorordre', $sprog_id);	$Finansrapport	   = findtekst('895|Finansrapport', $sprog_id);
$Regnskab		= findtekst('322|Regnskab', $sprog_id);			$Kassekladde	   = findtekst('601|Kassekladde', $sprog_id);
$Indstillinger  = findtekst('122|Indstillinger', $sprog_id);	$Kontoplan		   = findtekst('113|Kontoplan', $sprog_id);

#var_dump($Produksjonsordrer);

$modules=array($Sikkerhedskopi,$Debitorrapport,$Varemodtagelse,$Kreditorrapport,$Varelager,$Produktionsordrer,$Kreditorkonti,$Varerapport,
$Kreditorordrer,$Debitorkonti,$Debitorordrer,$Finansrapport,$Regnskab,$Kassekladde,$Indstillinger,$Kontoplan);
#$modules=array('Sikkerhedskopi','Debitorrapport','Varemodtagelse','Kreditorrapport','Varelager','Produktionsordrer','Kreditorkonti','Varerapport','Kreditorordrer','Debitorkonti','Debitorordrer','Finansrapport','Regnskab','Kassekladde','Indstillinger','Kontoplan');


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

print "<tr><td><b>".findtekst('2562|Revisor', $sprog_id)."</b></td><td><b>".findtekst('225|Brugernavn', $sprog_id)."</b></td></tr>"; 
$query = db_select("SELECT * FROM settings WHERE var_name = 'revisor' AND var_grp = 'system'", __FILE__ . " linje " . __LINE__);
if(db_num_rows($query) > 0){
	$r = db_fetch_array($query);
	$userId = $r['user_id'];
	if ($userId) {
		$disabled = "disabled";
	} else {
		$disabled = "";
	}
} else {
	$userId = 0;
	$disabled = "";
}
$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['id']!=$ret_id) {
		if ($row['ansat_id']) {
			$r2 = db_fetch_array(db_select("select initialer from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
		}	else {$r2['initialer']='';}
		print "<tr><td><input type='checkbox' name='revisor' id='$row[id]' $disabled " . (($userId == $row['id']) ? 'checked' : '') . "></td><td><a href=brugere.php?ret_id=$row[id]>";
		($row['brugernavn'])?print $row['brugernavn']:print '?';
		print "</a></td>";
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
?>
	<script>
		const checkbox = document.querySelectorAll("[name=revisor]")
		const db = "<?php echo $db; ?>";
		const confirmMessage = <?php echo json_encode(findtekst('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang, og du kan ikke ændre hvilken bruger der er revisor uden at kontakte Saldi support.', $sprog_id)); ?>;
		// event listener for checkboxes
		checkbox.forEach((el) => {
		el.addEventListener("change", () => {
			// Check if checkbox is selected
			if (el.checked) {
				if (confirm(confirmMessage)) {
					const res = fetch("brugereRevisor.php",
					{
						method: "POST",
						headers: {
							"Content-Type": "application/x-www-form-urlencoded"
						},
						body: "id=" + el.id + "&db=" + db
					}
				);
					window.location.reload();
				} else {
					// Remove selection if user cancels
					el.checked = false;
				}
			} else {
				// Do nothing if checkbox is de-selected (no confirm dialog)
				// No popup if checkbox is de-selected
			}
		});
	});
	</script>
<?php
if ($ret_id) {
	$query = db_select("select * from brugere where id = $ret_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$userName=$row['brugernavn'];
	// get afd from settings using settings function from std_func.php
	$afd = get_settings_value('afd', 'brugerAfd', 0, $row['id']);
	

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
	print "</tr><tr><td></td><td>".findtekst('2475|Kun visning', $sprog_id)."</td>";
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
	print "<tr><td>".findtekst('327|Adgangskode.', $sprog_id)."</td><td><input class=\"inputbox\" type=password size=20 name=kode value='********************'></td></tr>";
	print "<tr><td>".findtekst('328|Gentag adgangskode', $sprog_id)."</td><td><input class=\"inputbox\" type=password size=20 name=kode2 value='********************'></td></tr>";
	$x=0;
	if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
		$employeeId=array();
		$q2 = db_select("select * from ansatte where konto_id = $r2[id]  and lukket!='on' order by initialer",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$employeeId[$x]=$r2['id'];
			$employeeInitials[$x]=$r2['initialer'];
			if ($employeeId[$x]==$row['ansat_id']) {
				$employeeId[0]=$employeeId[$x];
				$employeeInitials[0]=$employeeInitials[$x];
			}		 
#			print "<input type = hidden name=employeeId[$x] value=$employeeId[$x]>";
		}
	}
	$ansat_antal=$x;
	print "<tr><td> ".findtekst('589|Ansat', $sprog_id)."</td>";
	print "<td><SELECT NAME=employeeId[0]>";
	print "<option value=\"$employeeId[0]\">$employeeInitials[0]</option>";
	for ($x=1; $x<=$ansat_antal; $x++) { 
		print "<option value=\"$employeeId[$x]\">$employeeInitials[$x]</option>";
	} 
	if ($medarbejder) print "<option></option>";
	print "</SELECT></td></tr>";

		print "<input type=hidden name=re_id value=$ret_id>"; #20210909+20211015
	
		###########################################20210831
	print "<tr><td>".findtekst('1904|Angiv brugerens tilladte IP adresser', $sprog_id)."</td><td><input class=\"inputbox\" type= text  name=insert_ip maxlength=49 value='$row[ip_address]' ></td></tr>"; #20210908
	print "<tr><td>Afdeling</td><td><select class=\"inputbox\" name=\"afdeling\">";
	$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if($r['kodenr']==$afd) {
			print "<option value=\"$r[kodenr]\" selected>$r[beskrivelse]</option>";
		} else {
			print "<option value=\"$r[kodenr]\">$r[beskrivelse]</option>";
		}
	}
	print "</select></td></tr>";
	print "<tr><td>Tlf (til 2fa):</td><td><input class=\"inputbox\" type=text size=20 name=tlf value='$row[tlf]'></td></tr>";
	if($row['twofactor'] == 't') {
		$twofactor = "checked";
	} else {
		$twofactor = "";
	}
	print "<tr><td>two factor authentication	</td><td><input class=\"inputbox\" type=\"checkbox\" name=\"twofactor\" $twofactor></td></tr>";
	print "</tbody></table></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	if ($menu=='T') {
		$class = "class='button blue medium'";	
	} else {
		$class = "class='inputbox'";
	}
	print "<td colspan='12' align = 'center'>";
	print "<input style='width:100px;background-color:44ff44;' type=submit value=\"".findtekst('1091|Opdater', $sprog_id)."\" name=\"updateUser\">&nbsp;";
	print "<input style='width:100px;background-color:ff4444;' type=submit value=\"".findtekst('1099|Slet', $sprog_id)."\" name=\"deleteUser\" onclick=\"confirm('Slet $userName?')\"></td>";
} else {
	$tmp="navn".rand(100,999);				#For at undgaa at browseren "husker" et forkert brugernavn.
	print "<input type=hidden name=random value = $tmp>";
	print "<tr><td> ".findtekst('333|Ny bruger', $sprog_id)."</td>";
	print "<td><input class=\"inputbox\" type=\"text\" size='20' name='$tmp'></td>";
	$s = findtekst('329|Adgang til', $sprog_id); $as = explode(" ", $s); 
	print "</tr><tr><td></td><td>$as[0]</td>";
	for ($x=0;$x<16;$x++) {
		print "<td><input class='inputbox' type='checkbox' name=\"rights[$x]\"></td>\n";
	}
	print "</tr><tr><td></td><td>".findtekst('2475|Kun visning', $sprog_id)."</td>";
	for ($x=0;$x<16;$x++) {
		print "<td>";
		if ($x==9) print "<input class='inputbox' type='checkbox' name='roRights[$x]'>\n";
		else {
			print "<input disabled='disabled' class='inputbox' type='checkbox' name='roRights[$x]'>\n";
		}
		print "</td>";
	}
	print "</tr>";
	print "<tr><td>Tlf (til 2fa):</td><td><input class=\"inputbox\" type=text size=20 name=tlf></td></tr>";
	print "<tr><td> ".findtekst('324|Adgangskode', $sprog_id)."</td><td><input class=\"inputbox\" type=password size=20 name=kode></td></tr>";
	print "<tr><td> ".findtekst('328|Gentag adgangskode', $sprog_id)."</td><td><input class=\"inputbox\" type=password size=20 name=kode2></td></tr>";
	print "</tbody></table></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td colspan=12 align = center>";
	if ($menu=='T') {
		print "<input style='width:200px;' class='blue medium button' type=submit value=\"".findtekst('1175|Tilføj', $sprog_id)."\" name=\"addUser\"></td>";
	} elseif ($menu=='S') {
		print "<input style='$buttonStyle; width:200px' type=submit value=\"".findtekst('1175|Tilføj', $sprog_id)."\" name=\"addUser\"></td>";
	} else {
		print "<input style='width:200px;background-color:#aaaaff;' type=submit value=\"".findtekst('1175|Tilføj', $sprog_id)."\" name=\"addUser\"></td>";
	}
}
print "</tr>";
# print "</tbody></table></td></tr>";

print "
</tbody>
</table>
</td></tr>
</tbody></table>
</div></div>
";

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>