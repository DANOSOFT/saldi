<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/brugerNewest.php-----patch 5.0.0 ----2024-12-19-----
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// Modern redesign of user management interface
// Same functionality as brugere.php but with improved UX/UI

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

$kontoplan   	  =  lcfirst(findtekst('113|Kontoplan', $sprog_id));		 $indstillinger	  = lcfirst(findtekst('122|Indstillinger', $sprog_id));
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

// Get module display names
$da = str_replace(" ", "",(findtekst('1141|Debitorapport', $sprog_id)));
$ka = str_replace(" ", "",(findtekst('1140|Kreditorapport', $sprog_id)));

$Sikkerhedskopi = findtekst('521|Sikkerhedskopi', $sprog_id);   $Debitorrapport	   = findtekst('449|Debitorrapporter', $sprog_id);
$Varemodtagelse = findtekst('182|Varemodtagelse', $sprog_id);   $Kreditorrapport   = $ka;
$Varelager      = findtekst('1261|Varelager', $sprog_id);		$Produktionsordrer = findtekst('1260|Produktionsordre', $sprog_id);
$Kreditorkonti  = findtekst('1258|Kreditorkonti', $sprog_id);	$Varerapport	   = findtekst('965|Varerapport', $sprog_id);
$Kreditorordrer = findtekst('1257|Kreditorordre', $sprog_id);	$Debitorkonti	   = findtekst('1256|Debitorkonti', $sprog_id);
$Debitorordrer  = findtekst('1255|Debitorordre', $sprog_id);	$Finansrapport	   = findtekst('895|Finansrapport', $sprog_id);
$Regnskab		= findtekst('322|Regnskab', $sprog_id);			$Kassekladde	   = findtekst('601|Kassekladde', $sprog_id);
$Indstillinger  = findtekst('122|Indstillinger', $sprog_id);	$Kontoplan		   = findtekst('113|Kontoplan', $sprog_id);

$moduleNames=array($Sikkerhedskopi,$Debitorrapport,$Varemodtagelse,$Kreditorrapport,$Varelager,$Produktionsordrer,$Kreditorkonti,$Varerapport,
$Kreditorordrer,$Debitorkonti,$Debitorordrer,$Finansrapport,$Regnskab,$Kassekladde,$Indstillinger,$Kontoplan);

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
    print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class='divSys'>";
} else {
	include("top.php");
}

$ip_address = if_isset($_SERVER['REMOTE_ADDR']);
$proxy_ip = if_isset($_SERVER['HTTP_X_FORWARDED_FOR']);
$client_ip = if_isset($_SERVER['HTTP_CLIENT_IP']);
$yd = get_ip();

$addUser=if_isset($_POST['addUser']);
$deleteUser=if_isset($_POST['deleteUser']);
$id=if_isset($_POST['id']);
$updateUser=if_isset($_POST['updateUser']);
$ret_id=if_isset($_GET['ret_id']);
$slet_id=if_isset($_GET['slet_id']);
$add=if_isset($_GET['add']);

// Process form submissions
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
		$twofactor = 't';
	} else {
		$twofactor = 'f';
	}
	$insert_ip = if_isset($_POST['insert_ip']);
	$re_id=if_isset($_POST['re_id']);
	if($insert_ip){
		$user_ip=$insert_ip;
	}
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
		}	else {
			if (!$regnaar) $regnaar=1;
			$qtxt = "insert into brugere (brugernavn,kode,rettigheder,regnskabsaar,ansat_id,ip_address,tlf,twofactor,email) ";
			$qtxt.= "values ('$brugernavn','$kode','$rettigheder','$regnaar',$employeeId[0],'$insert_ip','$tlf','$twofactor','$email')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			$qtxt="select id from brugere where brugernavn = '$brugernavn' and kode = '$kode'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
			// Redirect to main page after successful add
			header("Location: brugerNewest1.php");
			exit;
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
		// Redirect to main page after successful update
		header("Location: brugerNewest1.php");
		exit;
	}
} elseif (($deleteUser)) {
	$qtxt="select ansat_id from brugere where id ='$id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	if ($r['ansat_id']) { 
		$qtxt="update ansatte set lukket='on', slutdate='".date("Y-m-d")."' where id = '$r[ansat_id]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	db_modify("delete from brugere where id = $id",__FILE__ . " linje " . __LINE__);
	// Redirect to main page after successful delete
	header("Location: brugerNewest1.php");
	exit;
}

// System-aligned CSS
?>
<style>
.user-management-container {
	width: 100%;
	font-family: 'Montserrat', Arial, sans-serif;
	font-size: 12px;
	width: 90%;
}

.user-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
	padding: 10px 15px;
	background-color: <?php echo $buttonColor; ?>;
	border: 1px solid #C5C5C5;
	border-radius: 5px;
}

.user-header h1 {
	margin: 0;
	font-size: 14px;
	font-weight: bold;
	font-family: 'Montserrat', Arial, sans-serif;
	color: <?php echo $buttonTxtColor; ?>;
	text-transform: uppercase;
}

.users-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 10px;
	margin-bottom: 15px;
}

.user-card {
	background-color: #ffffff;
	border: 1px solid #C5C5C5;
	border-radius: 5px;
	padding: 10px;
	font-family: 'Montserrat', Arial, sans-serif;
	font-size: 12px;
}

.user-card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
	padding-bottom: 8px;
	border-bottom: 1px solid #C5C5C5;
}

.user-name {
	font-size: 13px;
	font-weight: bold;
	color: black;
	text-decoration: none;
	font-family: 'Montserrat', Arial, sans-serif;
}

.user-name:hover {
	color: #1792A2;
	text-decoration: underline;
}

.revisor-badge {
	display: inline-block;
	padding: 2px 8px;
	background-color: #ffd700;
	color: #333;
	border: 1px solid #C5C5C5;
	font-size: 10px;
	font-weight: bold;
	text-transform: uppercase;
}

.permissions-grid {
	display: grid;
	grid-template-columns: repeat(4, 1fr);
	gap: 5px;
	margin-top: 10px;
}

.permission-item {
	display: flex;
	flex-direction: column;
	align-items: center;
	padding: 5px;
	background-color: #f5f5f5;
	border: 1px solid #C5C5C5;
}

.permission-label {
	font-size: 8px;
	text-align: center;
	color: #666;
	margin-bottom: 3px;
	line-height: 1.1;
	font-family: 'Montserrat', Arial, sans-serif;
}

.permission-indicator {
	width: 12px;
	height: 12px;
	border: 1px solid #C5C5C5;
	position: relative;
}

.permission-indicator.full {
	background-color: #28a745;
	border-color: #28a745;
}

.permission-indicator.readonly {
	background-color: #ffc107;
	border-color: #ffc107;
}

.permission-indicator.none {
	background-color: #dc3545;
	border-color: #dc3545;
}

.user-form-container {
	background-color: #ffffff;
	border: 1px solid #C5C5C5;
	border-radius: 5px;
	padding: 15px;
	margin-top: 15px;
}

.form-section {
	margin-bottom: 20px;
}

.form-section-title {
	font-size: 13px;
	font-weight: bold;
	color: black;
	margin-bottom: 10px;
	padding-bottom: 5px;
	border-bottom: 2px solid #C5C5C5;
	font-family: 'Montserrat', Arial, sans-serif;
	text-transform: uppercase;
}

.form-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 10px;
}

.form-group {
	display: flex;
	flex-direction: column;
}

.form-label {
	font-size: 12px;
	font-weight: normal;
	color: black;
	margin-bottom: 3px;
	font-family: 'Montserrat', Arial, sans-serif;
}

.form-input {
	padding: 5px;
	border: 1px solid #C5C5C5;
	border-radius: 3px;
	font-size: 12px;
	font-family: 'Montserrat', Arial, sans-serif;
	background-color: #ffffff;
}

.form-input:focus {
	outline: none;
	border-color: #1792A2;
}

.form-select {
	padding: 5px;
	border: 1px solid #C5C5C5;
	border-radius: 3px;
	font-size: 12px;
	background-color: #ffffff;
	cursor: pointer;
	font-family: 'Montserrat', Arial, sans-serif;
}

.form-select:focus {
	outline: none;
	border-color: #1792A2;
}

.checkbox-group {
	display: flex;
	align-items: center;
	gap: 5px;
}

.checkbox-group input[type="checkbox"] {
	width: 16px;
	height: 16px;
	cursor: pointer;
}

.permissions-section {
	margin-top: 20px;
}

.permissions-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 10px;
	font-family: 'Montserrat', Arial, sans-serif;
	font-size: 12px;
	border: 1px solid #C5C5C5;
}

.permissions-table th {
	text-align: left;
	padding: 8px;
	background-color: #e8e8e8;
	font-weight: bold;
	color: black;
	border-bottom: 2px solid #C5C5C5;
	font-family: 'Montserrat', Arial, sans-serif;
	font-size: 12px;
}

.permissions-table td {
	padding: 8px;
	border-bottom: 1px solid #C5C5C5;
	font-family: 'Montserrat', Arial, sans-serif;
	font-size: 12px;
}

.permissions-table tbody tr:nth-child(even) {
	background-color: #f5f5f5;
}

.module-name {
	font-weight: normal;
	color: black;
}

.checkbox-cell {
	text-align: center;
}

.checkbox-cell input[type="checkbox"] {
	width: 16px;
	height: 16px;
	cursor: pointer;
}

.button-group {
	display: flex;
	gap: 10px;
	margin-top: 20px;
	padding-top: 15px;
	border-top: 1px solid #C5C5C5;
}

.btn {
	padding: 8px 15px;
	border: 1px solid #C5C5C5;
	border-radius: 3px;
	font-size: 12px;
	font-weight: normal;
	cursor: pointer;
	text-decoration: none;
	display: inline-block;
	font-family: 'Montserrat', Arial, sans-serif;
	text-align: center;
}

.btn-primary {
	background-color: #1792A2;
	color: white;
	border-color: #1792A2;
}

.btn-primary:hover {
	background-color: #147a88;
}

.btn-danger {
	background-color: #dc3545;
	color: white;
	border-color: #dc3545;
}

.btn-danger:hover {
	background-color: #c82333;
}

.btn-secondary {
	background-color: #6c757d;
	color: white;
	border-color: #6c757d;
}

.btn-secondary:hover {
	background-color: #5a6268;
}

.btn-success {
	background-color: #28a745;
	color: white;
	border-color: #28a745;
}

.btn-success:hover {
	background-color: #218838;
}

.empty-state {
	text-align: center;
	padding: 40px 20px;
	color: #666;
	font-family: 'Montserrat', Arial, sans-serif;
}

.empty-state-icon {
	font-size: 36px;
	margin-bottom: 15px;
	opacity: 0.5;
}

@media (max-width: 768px) {
	.users-grid {
		grid-template-columns: 1fr;
	}
	
	.form-grid {
		grid-template-columns: 1fr;
	}
	
	.permissions-grid {
		grid-template-columns: repeat(2, 1fr);
	}
}
</style>

<div class="user-management-container">
	<?php
	// Only show form if editing or adding
	if ($ret_id || $add):
		// Show back button and form
		?>
		<div class="user-header">
			<h1><?php echo ($ret_id) ? findtekst('1091|Rediger bruger', $sprog_id) : findtekst('333|Ny bruger', $sprog_id); ?></h1>
			<a href="brugerNewest1.php" class="btn btn-secondary">← <?php echo findtekst('1090|Tilbage', $sprog_id); ?></a>
		</div>
		<div class="user-form-container">
	<?php else: 
		// Main page - show users only
		?>
		<div class="user-header">
			<h1><?php echo findtekst('225|Brugere', $sprog_id); ?></h1>
			<a href="brugerNewest1.php?add=1" class="btn btn-success">+ <?php echo findtekst('333|Ny bruger', $sprog_id); ?></a>
		</div>

		<?php
		// Get revisor user
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

		// Display users
		$query = db_select("select * from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
		$userCount = 0;
		?>
		
		<div class="users-grid">
			<?php
			while ($row = db_fetch_array($query)) {
				$userCount++;
				if ($row['ansat_id']) {
					$r2 = db_fetch_array(db_select("select initialer from ansatte where id = $row[ansat_id]",__FILE__ . " linje " . __LINE__));
				} else {
					$r2['initialer']='';
				}
				?>
				<div class="user-card">
					<div class="user-card-header">
						<a href="brugerNewest1.php?ret_id=<?php echo $row['id']; ?>" class="user-name">
							<?php echo ($row['brugernavn']) ? htmlspecialchars($row['brugernavn']) : '?'; ?>
						</a>
						<?php if ($userId == $row['id']): ?>
							<span class="revisor-badge">★ <?php echo findtekst('2562|Revisor', $sprog_id); ?></span>
						<?php endif; ?>
					</div>
					
					<?php if ($r2['initialer']): ?>
						<div style="color: #666; font-size: 13px; margin-bottom: 10px;">
							<?php echo htmlspecialchars($r2['initialer']); ?>
						</div>
					<?php endif; ?>
					
					<div class="permissions-grid">
						<?php
						for ($y=0; $y<=15; $y++) {
							$permission = substr($row['rettigheder'],$y,1);
							$moduleName = isset($moduleNames[$y]) ? $moduleNames[$y] : "Module $y";
							$shortName = strlen($moduleName) > 8 ? substr($moduleName, 0, 8) . '..' : $moduleName;
							
							$indicatorClass = 'none';
							if ($permission == '2') {
								$indicatorClass = 'readonly';
							} elseif ($permission == '1') {
								$indicatorClass = 'full';
							}
							?>
							<div class="permission-item" title="<?php echo htmlspecialchars($moduleName); ?>">
								<div class="permission-label"><?php echo htmlspecialchars($shortName); ?></div>
								<div class="permission-indicator <?php echo $indicatorClass; ?>"></div>
							</div>
							<?php
						}
						?>
					</div>
					
					<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
						<?php if ($userId != $row['id']): ?>
							<label class="checkbox-group">
								<input type="checkbox" name="revisor" id="<?php echo $row['id']; ?>" <?php echo $disabled; ?> <?php echo ($userId == $row['id']) ? 'checked' : ''; ?>>
								<span style="font-size: 12px; color: #666;"><?php echo findtekst('2562|Revisor', $sprog_id); ?></span>
							</label>
						<?php else: ?>
							<span style="font-size: 12px; color: #666;"><?php echo findtekst('2562|Revisor', $sprog_id); ?></span>
						<?php endif; ?>
						<a href="brugerNewest1.php?ret_id=<?php echo $row['id']; ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;">
							<?php echo findtekst('1091|Rediger', $sprog_id); ?>
						</a>
					</div>
				</div>
				<?php
			}
			?>
		</div>

		<?php if ($userCount == 0): ?>
			<div class="empty-state">
				<div class="empty-state-icon">👤</div>
				<p>Ingen brugere fundet</p>
				<a href="brugerNewest1.php?add=1" class="btn btn-success" style="margin-top: 20px;">+ <?php echo findtekst('333|Ny bruger', $sprog_id); ?></a>
			</div>
		<?php endif; ?>
		
		<script>
			const checkbox = document.querySelectorAll("[name=revisor]");
			const db = "<?php echo $db; ?>";
			const confirmMessage = <?php echo json_encode(findtekst('2563|Vil du gøre denne bruger til revisor? Kun én bruger kan have revisoradgang, og du kan ikke ændre hvilken bruger der er revisor uden at kontakte Saldi support.', $sprog_id)); ?>;
			
			checkbox.forEach((el) => {
				el.addEventListener("change", () => {
					if (el.checked) {
						if (confirm(confirmMessage)) {
							fetch("brugereRevisor.php", {
								method: "POST",
								headers: {
									"Content-Type": "application/x-www-form-urlencoded"
								},
								body: "id=" + el.id + "&db=" + db
							}).then(() => {
								window.location.reload();
							});
						} else {
							el.checked = false;
						}
					}
				});
			});
		</script>
		
	<?php endif; ?>
	
	<?php
	// User form (edit or add) - only show if ret_id or add is set
	if ($ret_id || $add):
	?>
		<form name='bruger' action='brugerNewest1.php' method='post'>
			<?php if ($ret_id): ?>
				<?php
				$query = db_select("select * from brugere where id = $ret_id",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$userName=$row['brugernavn'];
				$afd = get_settings_value('afd', 'brugerAfd', 0, $row['id']);
				?>
				
				<div class="form-section">
					<h2 class="form-section-title"><?php echo findtekst('1091|Rediger bruger', $sprog_id); ?></h2>
					
					<input type="hidden" name="id" value="<?php echo $row['id']; ?>">
					<?php
					$tmp="navn".rand(100,999);
					print "<input type=hidden name=random value=$tmp>";
					?>
					
					<div class="form-grid">
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('225|Brugernavn', $sprog_id); ?></label>
							<input class="form-input" type="text" name="<?php echo $tmp; ?>" value="<?php echo htmlspecialchars($row['brugernavn']); ?>" required>
						</div>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('327|Adgangskode', $sprog_id); ?></label>
							<input class="form-input" type="password" name="kode" value="********************" placeholder="Lad stå for at beholde nuværende">
						</div>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('328|Gentag adgangskode', $sprog_id); ?></label>
							<input class="form-input" type="password" name="kode2" value="********************" placeholder="Lad stå for at beholde nuværende">
						</div>
						
						<?php
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
							}
						}
						$ansat_antal=$x;
						?>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('589|Ansat', $sprog_id); ?></label>
							<select class="form-select" name="employeeId[0]">
								<option value="<?php echo $employeeId[0]; ?>"><?php echo htmlspecialchars($employeeInitials[0]); ?></option>
								<?php for ($x=1; $x<=$ansat_antal; $x++): ?>
									<option value="<?php echo $employeeId[$x]; ?>"><?php echo htmlspecialchars($employeeInitials[$x]); ?></option>
								<?php endfor; ?>
							</select>
						</div>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('1904|Angiv brugerens tilladte IP adresser', $sprog_id); ?></label>
							<input class="form-input" type="text" name="insert_ip" maxlength="49" value="<?php echo htmlspecialchars($row['ip_address']); ?>">
						</div>
						
						<div class="form-group">
							<label class="form-label">Afdeling</label>
							<select class="form-select" name="afdeling">
								<?php
								$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
								while ($r = db_fetch_array($q)) {
									$selected = ($r['kodenr']==$afd) ? 'selected' : '';
									echo "<option value=\"$r[kodenr]\" $selected>".htmlspecialchars($r['beskrivelse'])."</option>";
								}
								?>
							</select>
						</div>
						
						<div class="form-group">
							<label class="form-label" title="Hvis telefon og email er udfyldt, vil 2fa sendes til tlf og ikke email">Tlf (til 2fa):</label>
							<input class="form-input" type="text" name="tlf" value="<?php echo htmlspecialchars($row['tlf']); ?>">
						</div>
						
						<div class="form-group">
							<label class="form-label" title="Hvis telefon og email er udfyldt, vil 2fa sendes til tlf og ikke email">Email (til 2fa):</label>
							<input class="form-input" type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>">
						</div>
						
						<div class="form-group">
							<label class="checkbox-group">
								<input type="checkbox" name="twofactor" <?php echo ($row['twofactor'] == 't') ? 'checked' : ''; ?>>
								<span>Two factor authentication</span>
							</label>
						</div>
					</div>
				</div>
				
				<div class="permissions-section">
					<h2 class="form-section-title"><?php echo findtekst('329|Adgang til', $sprog_id); ?></h2>
					
					<table class="permissions-table">
						<thead>
							<tr>
								<th>Modul</th>
								<th class="checkbox-cell"><?php echo findtekst('329|Adgang til', $sprog_id); ?></th>
								<th class="checkbox-cell"><?php echo findtekst('2475|Kun visning', $sprog_id); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php for ($x=0;$x<16;$x++): ?>
								<tr>
									<td class="module-name"><?php echo htmlspecialchars($moduleNames[$x]); ?></td>
									<td class="checkbox-cell">
										<?php
										$checked = (substr($row['rettigheder'],$x,1)>=1) ? 'checked' : '';
										?>
										<input type="checkbox" name="rights[<?php echo $x; ?>]" <?php echo $checked; ?>>
									</td>
									<td class="checkbox-cell">
										<?php
										$checked = (substr($row['rettigheder'],$x,1)==2) ? 'checked' : '';
										if ($x==9) {
											echo "<input type='checkbox' name='roRights[$x]' $checked>";
										} else {
											echo "<input type='checkbox' name='roRights[$x]' disabled>";
										}
										?>
									</td>
								</tr>
							<?php endfor; ?>
						</tbody>
					</table>
				</div>
				
				<input type="hidden" name="re_id" value="<?php echo $ret_id; ?>">
				
				<div class="button-group">
					<button type="submit" name="updateUser" class="btn btn-primary">
						<?php echo findtekst('1091|Opdater', $sprog_id); ?>
					</button>
					<button type="submit" name="deleteUser" class="btn btn-danger" onclick="return confirm('Slet <?php echo htmlspecialchars($userName); ?>?');">
						<?php echo findtekst('1099|Slet', $sprog_id); ?>
					</button>
					<a href="brugerNewest1.php" class="btn btn-secondary"><?php echo findtekst('1090|Annuller', $sprog_id); ?></a>
				</div>
				
			<?php else: ?>
				<?php
				$tmp="navn".rand(100,999);
				print "<input type=hidden name=random value = $tmp>";
				?>
				
				<div class="form-section">
					<h2 class="form-section-title"><?php echo findtekst('333|Ny bruger', $sprog_id); ?></h2>
					
					<div class="form-grid">
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('225|Brugernavn', $sprog_id); ?></label>
							<input class="form-input" type="text" name="<?php echo $tmp; ?>" required>
						</div>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('324|Adgangskode', $sprog_id); ?></label>
							<input class="form-input" type="password" name="kode" required>
						</div>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('328|Gentag adgangskode', $sprog_id); ?></label>
							<input class="form-input" type="password" name="kode2" required>
						</div>
						
						<div class="form-group">
							<label class="form-label">Tlf (til 2fa):</label>
							<input class="form-input" type="text" name="tlf">
						</div>
						
						<div class="form-group">
							<label class="form-label">Email (til 2fa):</label>
							<input class="form-input" type="email" name="email">
						</div>
						
						<div class="form-group">
							<label class="checkbox-group">
								<input type="checkbox" name="twofactor">
								<span>Two factor authentication</span>
							</label>
						</div>
						
						<?php
						$x=0;
						$employeeId=array();
						$employeeInitials=array();
						if ($r2 = db_fetch_array(db_select("select id from adresser where art = 'S'",__FILE__ . " linje " . __LINE__))) {
							$q2 = db_select("select * from ansatte where konto_id = $r2[id]  and lukket!='on' order by initialer",__FILE__ . " linje " . __LINE__);
							while ($r2 = db_fetch_array($q2)) {
								$x++;
								$employeeId[$x]=$r2['id'];
								$employeeInitials[$x]=$r2['initialer'];
							}
						}
						$ansat_antal=$x;
						?>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('589|Ansat', $sprog_id); ?></label>
							<select class="form-select" name="employeeId[0]">
								<option value="0"></option>
								<?php for ($x=1; $x<=$ansat_antal; $x++): ?>
									<option value="<?php echo $employeeId[$x]; ?>"><?php echo htmlspecialchars($employeeInitials[$x]); ?></option>
								<?php endfor; ?>
							</select>
						</div>
						
						<div class="form-group">
							<label class="form-label"><?php echo findtekst('1904|Angiv brugerens tilladte IP adresser', $sprog_id); ?></label>
							<input class="form-input" type="text" name="insert_ip" maxlength="49">
						</div>
						
						<div class="form-group">
							<label class="form-label">Afdeling</label>
							<select class="form-select" name="afdeling">
								<option value="0"></option>
								<?php
								$q = db_select("select * from grupper where art = 'AFD'",__FILE__ . " linje " . __LINE__);
								while ($r = db_fetch_array($q)) {
									echo "<option value=\"$r[kodenr]\">".htmlspecialchars($r['beskrivelse'])."</option>";
								}
								?>
							</select>
						</div>
					</div>
				</div>
				
				<div class="permissions-section">
					<h2 class="form-section-title"><?php echo findtekst('329|Adgang til', $sprog_id); ?></h2>
					
					<table class="permissions-table">
						<thead>
							<tr>
								<th>Modul</th>
								<th class="checkbox-cell"><?php echo findtekst('329|Adgang til', $sprog_id); ?></th>
								<th class="checkbox-cell"><?php echo findtekst('2475|Kun visning', $sprog_id); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php for ($x=0;$x<16;$x++): ?>
								<tr>
									<td class="module-name"><?php echo htmlspecialchars($moduleNames[$x]); ?></td>
									<td class="checkbox-cell">
										<input type="checkbox" name="rights[<?php echo $x; ?>]">
									</td>
									<td class="checkbox-cell">
										<?php
										if ($x==9) {
											echo "<input type='checkbox' name='roRights[$x]'>";
										} else {
											echo "<input type='checkbox' name='roRights[$x]' disabled>";
										}
										?>
									</td>
								</tr>
							<?php endfor; ?>
						</tbody>
					</table>
				</div>
				
				<div class="button-group">
					<button type="submit" name="addUser" class="btn btn-primary">
						<?php echo findtekst('1175|Tilføj', $sprog_id); ?>
					</button>
					<a href="brugerNewest1.php" class="btn btn-secondary"><?php echo findtekst('1090|Annuller', $sprog_id); ?></a>
				</div>
			<?php endif; ?>
		</form>
	</div>
	<?php endif; ?>
</div>

<?php
if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>

