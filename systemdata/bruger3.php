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
$css="../css/brugere-modern.css";

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


if ($menu=='T') {
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
    print "</div>";
	print "<div class=\"maincontentLargeHolder\">";
} else {
	include("top.php");
}

print "<div class='brugere-modern-container'>";

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
	$email=trim(if_isset($_POST['email']));
	$medarbejder=trim(if_isset($_POST['medarbejder']));
	$employeeId=if_isset($_POST['employeeId']);
	$twofactor=if_isset($_POST['twofactor']);
	if($twofactor){
		$twofactor = 't';  // PostgreSQL boolean true
	} else {
		$twofactor = 'f';  // PostgreSQL boolean false
	}
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
			$qtxt = "insert into brugere (brugernavn,kode,rettigheder,regnskabsaar,ansat_id,ip_address,tlf,twofactor,email) ";
			$qtxt.= "values ('$brugernavn','$kode','$rettigheder','$regnaar',$employeeId[0],'$insert_ip','$tlf','$twofactor','$email')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="select id from brugere where brugernavn = '$brugernavn' and kode = '$kode'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
	}
	if ($id && $kode && $brugernavn) {
		if (strstr($kode,'**********')) {
			db_modify("update brugere set brugernavn='$brugernavn', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf', twofactor = '$twofactor', email = '$email' where id=$id",__FILE__ . " linje " . __LINE__);
			update_settings_value('afd', 'brugerAfd', $afd, '', $id);
		} else {
			$kode=saldikrypt($id,$kode);
			db_modify("update brugere set brugernavn='$brugernavn', kode='$kode', rettigheder='$rettigheder', ansat_id=$employeeId[0], ip_address = '$insert_ip', tlf = '$tlf', twofactor = '$twofactor', email = '$email' where id=$id",__FILE__ . " linje " . __LINE__);
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

// Module names
$modules = array(
	findtekst('113|Kontoplan', $sprog_id),
	findtekst('122|Indstillinger', $sprog_id),
	findtekst('601|Kassekladde', $sprog_id),
	findtekst('322|Regnskab', $sprog_id),
	findtekst('895|Finansrapport', $sprog_id),
	findtekst('1255|Debitorordre', $sprog_id),
	findtekst('1256|Debitorkonti', $sprog_id),
	findtekst('1257|Kreditorordre', $sprog_id),
	findtekst('1258|Kreditorkonti', $sprog_id),
	findtekst('609|Varer', $sprog_id),
	findtekst('1259|Enheder', $sprog_id),
	findtekst('521|Sikkerhedskopi', $sprog_id),
	findtekst('449|Debitorrapporter', $sprog_id),
	str_replace(" ", "", findtekst('1140|Kreditorrapport', $sprog_id)),
	findtekst('1260|Produktionsordre', $sprog_id),
	findtekst('965|Varerapport', $sprog_id)
);

// Get revisor settings
$query = db_select("SELECT * FROM settings WHERE var_name = 'revisor' AND var_grp = 'system'", __FILE__ . " linje " . __LINE__);
if(db_num_rows($query) > 0){
	$r = db_fetch_array($query);
	$userId = $r['user_id'];
	$disabled = $userId ? "disabled" : "";
} else {
	$userId = 0;
	$disabled = "";
}

// Header
print "<div class='brugere-header'>";
print "<div>";
print "<h1>".findtekst('225|Brugere', $sprog_id)."</h1>";
print "<p class='subtitle'>".findtekst('2564|Administrer brugere og deres rettigheder', $sprog_id)."</p>";
print "</div>";
print "<div class='brugere-header-actions'>";
if (!$ret_id) {
	print "<a href='brugere.php?ret_id=new' class='btn btn-primary'>➕ ".findtekst('333|Ny bruger', $sprog_id)."</a>";
}
print "</div>";
print "</div>";

// Content grid
$gridClass = ($ret_id) ? 'brugere-content editing' : 'brugere-content';
print "<div class='$gridClass'>";

// Users list
print "<div class='users-list'>";
print "<div class='users-list-header'>";
print "<h2>".findtekst('225|Brugere', $sprog_id)."</h2>";

$userCount = db_num_rows(db_select("select id from brugere", __FILE__ . " linje " . __LINE__));
print "<span class='users-count'>$userCount ".findtekst('2565|brugere', $sprog_id)."</span>";
print "</div>";

// Permissions legend
print "<div class='permissions-legend'>";
print "<div class='legend-item'><span class='legend-dot none'></span> ".findtekst('2566|Ingen adgang', $sprog_id)."</div>";
print "<div class='legend-item'><span class='legend-dot access'></span> ".findtekst('329|Adgang', $sprog_id)."</div>";
print "<div class='legend-item'><span class='legend-dot readonly'></span> ".findtekst('2475|Kun visning', $sprog_id)."</div>";
print "</div>";

$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
while ($row = db_fetch_array($query)) {
	if ($row['id']!=$ret_id) {
		$activeClass = '';
		$isRevisor = ($userId == $row['id']);
		
		// Get employee info
		$employeeInitials = '';
		if ($row['ansat_id']) {
			$r2 = db_fetch_array(db_select("select initialer from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
			$employeeInitials = $r2['initialer'] ?? '';
		}
		
		print "<div class='user-card $activeClass' onclick=\"window.location='brugere.php?ret_id=$row[id]'\">";
		print "<div class='user-card-header'>";
		print "<div class='user-info'>";
		print "<h3 class='user-name'>";
		print "<input type='checkbox' name='revisor' id='$row[id]' $disabled " . ($isRevisor ? 'checked' : '') . " onclick='event.stopPropagation()'>";
		print htmlspecialchars($row['brugernavn'] ?: '?');
		if ($isRevisor) {
			print " <span class='revisor-badge'>⭐ ".findtekst('2562|Revisor', $sprog_id)."</span>";
		}
		print "</h3>";
		print "<div class='user-meta'>";
		if ($employeeInitials) {
			print "<span>👤 $employeeInitials</span>";
		}
		if ($row['twofactor'] == 't') {
			print "<span>🔐 2FA</span>";
		}
		print "</div>";
		print "</div>";
		print "</div>";
		
		// Permissions grid
		print "<div class='permissions-grid'>";
		for ($y=0; $y<=15; $y++) {
			$permLevel = substr($row['rettigheder'],$y,1);
			$indicatorClass = 'none';
			if ($permLevel == '2') $indicatorClass = 'readonly';
			elseif ($permLevel == '1') $indicatorClass = 'access';
			
			print "<div class='permission-item'>";
			print "<span class='permission-indicator $indicatorClass'></span>";
			print "<span class='permission-name' title='".htmlspecialchars($modules[$y])."'>".htmlspecialchars($modules[$y])."</span>";
			print "</div>";
		}
		print "</div>";
		print "</div>";
	}
}
print "</div>"; // users-list
?>
<?php
if ($ret_id && $ret_id != 'new') {
	// Edit user form
	$query = db_select("select * from brugere where id = $ret_id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$userName = $row['brugernavn'];
	$afd = get_settings_value('afd', 'brugerAfd', 0, $row['id']);
	
	print "<div class='user-form-card'>";
	print "<form name='bruger' action='brugere.php' method='post'>";
	print "<input type='hidden' name='id' value='$row[id]'>";
	$tmp = "navn".rand(100,999);
	print "<input type='hidden' name='random' value='$tmp'>";
	
	print "<div class='user-form-header'>";
	print "<h2>".findtekst('2567|Rediger bruger', $sprog_id)."</h2>";
	print "<p class='subtitle'>".htmlspecialchars($userName)."</p>";
	print "</div>";
	
	// Basic info
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('225|Brugernavn', $sprog_id)."</label>";
	print "<input class='form-input' type='text' name='$tmp' value=\"".htmlspecialchars($row['brugernavn'])."\" required>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('327|Adgangskode', $sprog_id)."</label>";
	print "<input class='form-input' type='password' name='kode' value='********************'>";
	print "<p class='form-hint'>".findtekst('2568|Lad stå som stjerner for at beholde nuværende', $sprog_id)."</p>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('328|Gentag adgangskode', $sprog_id)."</label>";
	print "<input class='form-input' type='password' name='kode2' value='********************'>";
	print "</div>";
	
	// Employee selection
	$employeeId = array();
	$employeeInitials = array();
	if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
		$q2 = db_select("select * from ansatte where konto_id = $r2[id] and lukket!='on' order by initialer",__FILE__ . " linje " . __LINE__);
		$x = 0;
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$employeeId[$x] = $r2['id'];
			$employeeInitials[$x] = $r2['initialer'];
			if ($employeeId[$x] == $row['ansat_id']) {
				$employeeId[0] = $employeeId[$x];
				$employeeInitials[0] = $employeeInitials[$x];
			}
		}
		$ansat_antal = $x;
		
		print "<div class='form-group'>";
		print "<label class='form-label'>".findtekst('589|Ansat', $sprog_id)."</label>";
		print "<select class='form-select' name='employeeId[0]'>";
		print "<option value='".($employeeId[0] ?? '')."'>".($employeeInitials[0] ?? '')."</option>";
		for ($x=1; $x<=$ansat_antal; $x++) {
			print "<option value='$employeeId[$x]'>$employeeInitials[$x]</option>";
		}
		print "</select>";
		print "</div>";
	}
	
	// Department
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('2569|Afdeling', $sprog_id)."</label>";
	print "<select class='form-select' name='afdeling'>";
	$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$selected = ($r['kodenr'] == $afd) ? 'selected' : '';
		print "<option value='$r[kodenr]' $selected>".htmlspecialchars($r['beskrivelse'])."</option>";
	}
	print "</select>";
	print "</div>";
	
	// IP addresses
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('1904|Tilladte IP adresser', $sprog_id)."</label>";
	print "<input class='form-input' type='text' name='insert_ip' maxlength='49' value='".htmlspecialchars($row['ip_address'])."' placeholder='192.168.1.1, 10.0.0.1'>";
	print "<p class='form-hint'>".findtekst('2570|Kommasepareret liste', $sprog_id)."</p>";
	print "</div>";
	
	// Phone
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('2571|Telefon (til 2FA)', $sprog_id)."</label>";
	print "<input class='form-input' type='text' name='tlf' value='".htmlspecialchars($row['tlf'])."'>";
	print "</div>";
	
	// Email
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('2572|Email (til 2FA)', $sprog_id)."</label>";
	print "<input class='form-input' type='email' name='email' value='".htmlspecialchars($row['email'])."'>";
	print "</div>";
	
	// 2FA checkbox
	$twofactorChecked = ($row['twofactor'] == 't') ? 'checked' : '';
	print "<div class='form-group'>";
	print "<label class='form-checkbox-group'>";
	print "<input class='form-checkbox' type='checkbox' name='twofactor' $twofactorChecked>";
	print "<span class='form-checkbox-label'>".findtekst('2573|Aktiver to-faktor autentificering', $sprog_id)."</span>";
	print "</label>";
	print "</div>";
	
	// Permissions
	print "<div class='permissions-editor'>";
	print "<h3>".findtekst('2574|Modulrettigheder', $sprog_id)."</h3>";
	print "<table class='permissions-table'>";
	print "<thead><tr>";
	print "<th>".findtekst('2575|Modul', $sprog_id)."</th>";
	print "<th>".findtekst('329|Adgang', $sprog_id)."</th>";
	print "<th>".findtekst('2475|Kun visning', $sprog_id)."</th>";
	print "</tr></thead><tbody>";
	
	for ($x=0; $x<16; $x++) {
		$hasAccess = (substr($row['rettigheder'],$x,1) >= 1);
		$isReadOnly = (substr($row['rettigheder'],$x,1) == 2);
		$accessChecked = $hasAccess ? 'checked' : '';
		$readOnlyChecked = $isReadOnly ? 'checked' : '';
		$readOnlyDisabled = ($x != 9) ? 'disabled' : '';
		
		print "<tr>";
		print "<td>".htmlspecialchars($modules[$x])."</td>";
		print "<td class='permission-checkbox-wrapper'><input type='checkbox' name='rights[$x]' $accessChecked></td>";
		print "<td class='permission-checkbox-wrapper'><input type='checkbox' name='roRights[$x]' $readOnlyChecked $readOnlyDisabled></td>";
		print "</tr>";
	}
	
	print "</tbody></table>";
	print "</div>";
	
	// Form actions
	print "<div class='form-actions'>";
	print "<button type='submit' name='updateUser' class='btn btn-success'>💾 ".findtekst('1091|Opdater', $sprog_id)."</button>";
	print "<button type='submit' name='deleteUser' class='btn btn-danger' onclick=\"return confirm('".findtekst('2576|Er du sikker på at du vil slette', $sprog_id)." $userName?')\">🗑️ ".findtekst('1099|Slet', $sprog_id)."</button>";
	print "</div>";
	
	print "</form>";
	print "</div>";
	
} elseif ($ret_id == 'new') {
	// Add new user form
	print "<div class='user-form-card'>";
	print "<form name='bruger' action='brugere.php' method='post'>";
	$tmp = "navn".rand(100,999);
	print "<input type='hidden' name='random' value='$tmp'>";
	
	print "<div class='user-form-header'>";
	print "<h2>".findtekst('333|Ny bruger', $sprog_id)."</h2>";
	print "<p class='subtitle'>".findtekst('2577|Opret en ny bruger', $sprog_id)."</p>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('225|Brugernavn', $sprog_id)."</label>";
	print "<input class='form-input' type='text' name='$tmp' required>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('324|Adgangskode', $sprog_id)."</label>";
	print "<input class='form-input' type='password' name='kode' required>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('328|Gentag adgangskode', $sprog_id)."</label>";
	print "<input class='form-input' type='password' name='kode2' required>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('2571|Telefon (til 2FA)', $sprog_id)."</label>";
	print "<input class='form-input' type='text' name='tlf'>";
	print "</div>";
	
	print "<div class='form-group'>";
	print "<label class='form-label'>".findtekst('2572|Email (til 2FA)', $sprog_id)."</label>";
	print "<input class='form-input' type='email' name='email'>";
	print "</div>";
	
	// Permissions
	print "<div class='permissions-editor'>";
	print "<h3>".findtekst('2574|Modulrettigheder', $sprog_id)."</h3>";
	print "<table class='permissions-table'>";
	print "<thead><tr>";
	print "<th>".findtekst('2575|Modul', $sprog_id)."</th>";
	print "<th>".findtekst('329|Adgang', $sprog_id)."</th>";
	print "<th>".findtekst('2475|Kun visning', $sprog_id)."</th>";
	print "</tr></thead><tbody>";
	
	for ($x=0; $x<16; $x++) {
		$readOnlyDisabled = ($x != 9) ? 'disabled' : '';
		print "<tr>";
		print "<td>".htmlspecialchars($modules[$x])."</td>";
		print "<td class='permission-checkbox-wrapper'><input type='checkbox' name='rights[$x]'></td>";
		print "<td class='permission-checkbox-wrapper'><input type='checkbox' name='roRights[$x]' $readOnlyDisabled></td>";
		print "</tr>";
	}
	
	print "</tbody></table>";
	print "</div>";
	
	print "<div class='form-actions'>";
	print "<button type='submit' name='addUser' class='btn btn-primary'>➕ ".findtekst('1175|Tilføj', $sprog_id)."</button>";
	print "<a href='brugere.php' class='btn btn-secondary'>✕ ".findtekst('2578|Annuller', $sprog_id)."</a>";
	print "</div>";
	
	print "</form>";
	print "</div>";
}

print "</div>"; // brugere-content
print "</div>"; // brugere-modern-container

// Revisor checkbox script
?>
<script>
const checkboxes = document.querySelectorAll("[name=revisor]");
const db = "<?php echo $db; ?>";
const confirmMessage = <?php echo json_encode(findtekst('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang, og du kan ikke ændre hvilken bruger der er revisor uden at kontakte Saldi support.', $sprog_id)); ?>;

checkboxes.forEach((el) => {
	el.addEventListener("change", (e) => {
		if (el.checked) {
			if (confirm(confirmMessage)) {
				fetch("brugereRevisor.php", {
					method: "POST",
					headers: {"Content-Type": "application/x-www-form-urlencoded"},
					body: "id=" + el.id + "&db=" + db
				}).then(() => window.location.reload());
			} else {
				el.checked = false;
			}
		}
	});
});
</script>
<?php

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>