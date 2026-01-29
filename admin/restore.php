<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------admin/restore.php--------lap 5.0.0------2026-01-29-----------
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20160609 PHR if ($POST) fungerer ikke mere, hvis ikke det angives hvad der postes.  
// 20200308 PHR Varius changes related til Centos 8 / mariadb /postgresql 9x
// 20222706 MSC - Implementing new design
// 20250201 Add hostname to psql
// 20250201 removed init of $uploadedfile which was never used
// 20250201 $brugernavn is never set near the end of the restore function
// 20250426 LOE Modified the javascript confirm function, added language cookie for the sprog_Id and used for updating some parts.
// 20250427 LOE Now accepts .sql file if available.
// 20250428 LOE When converting from mysql to postgres, users have the option to fill in the auth details.
// 20250503 LOE - reordered mix-up text_id from tekster.csv in findtekst()
// 20250504 LOE Updated to allow for mysql db conversion to psql, default texts if tekster table not found yet; must backup first
// 20250511 LOE Various changes to ehance user's experience
// 20260127 LOE Updated migrateMySQLToPostgreSQL for some isolated fixes.
// 20260129 PHR Added some str_replace  and a call to connect.php before lookup in 'regnskab'
@session_start();
$s_id=session_id();
ini_set('display_errors',0);


?>
<script LANGUAGE="JavaScript">
<!--

function confirmSubmit(messageProvider) {
    var message = typeof messageProvider === 'function' ? messageProvider() : messageProvider;
    var agree = confirm(message);
    return agree;
}

// -->
</script>
<?php

include("../includes/connect.php");
include("../includes/std_func.php");

$title=findtekst('1247|Indlæs sikkerhedskopi', $sprog_id);
$modulnr=11;
$css="../css/standard.css";
$backupdate=$backupdb=$backupver=$backupnavn=$filnavn=$menu=$regnskab=$timezone=$popup=NULL;

if (isset($_GET['db']) && $_GET['db']) {
	$db=$sqdb;
	$tmpDb=$_GET['db'];
	if (!db_exists($tmpDb)) {
		db_create($tmpDb);		
	}
	if (!$regnskab) {
		include("../includes/connect.php");
		$r=db_fetch_array(db_select("select * from regnskab where db='$tmpDb'",__FILE__ . " linje " . __LINE__));
		$regnskab=$r['regnskab'];
	}
	$db=$tmpDb;
	db_connect($sqhost, $squser, $sqpass, $db, "");
	print "<head><title>$title</title><meta http-equiv=\"content-type\" content=\"text/html; charset=$charset;\">\n";
	print "<meta http-equiv=\"content-language\" content=\"da\">\n";
	print "<meta name=\"google\" content=\"notranslate\"></head>\n";
	
} else include("../includes/online.php");	
include("../includes/std_func.php");
if(isset($_COOKIE['languageId'])){
	$sprog_id = $_COOKIE['languageId'];
}
 
 

if ($popup) $returside="../includes/luk.php";
else $returside="../index/menu.php";

if (!file_exists("../temp/$db")) mkdir("../temp/$db", 0775);

#####################
$translations = [
    2422 => [
        1 => "Du er ved at overskrive dit regnskab",
        2 => "You are overwriting your account.",
        3 => "Du overskriver kontoen din."
    ],
    2423 => [
        1 => "med en sikkerhedskopi af regnskabet",
        2 => "with a backup copy of the accounts",
        3 => "med en sikkerhetskopi av kontoene"
    ],
    2424 => [
        1 => "fra den",
        2 => "from the",
        3 => "fra den"
    ],
    2425 => [
        1 => "Bemærk at alle brugere skal være logget ud",
        2 => "Please note that all users must be logged out.",
        3 => "Vær oppmerksom på at alle brukere må være logget ut."
    ],
    2426 => [
        1 => "med en sikkerhedskopi fra den",
        2 => "with a backup copy from the",
        3 => "med en sikkerhetskopi fra"
    ],
    2427 => [
        1 => "Der er sket en fejl under hentningen - prøv venligst igen.",
        2 => "An error occurred during the download - please try again.",
        3 => "Det oppsto en feil under nedlastingen – prøv på nytt."
	],
	1360 => [
		1 => "Indlæs",
		2 => "Load",
		3 => "Laste"
	],
	1364 => [
		1 => "V&aelig;lg datafil",
		2 => "Select data file",
		3 => "Velg datafil"
	]
	
];


####################

include("../includes/topline_settings.php");

print "<div align=\"center\">";
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=backup.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst('30|Tilbage', $sprog_id)."</a></div>";     
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">&nbsp;&nbsp;&nbsp;</div>";     
	print "</div>";
	print "<div class='content-noside'>";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class='divSys'>";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTableSys\"><tbody>"; # -> 1
} elseif ($menu=='S') {
	print "<table width='100%' height='30%' border='0' cellspacing='0' cellpadding='0'><tbody>";
	print "<tr><td height = '25' align='center' valign='top'>";
	print "<table width='100%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>";

	print "<td width='10%'<a href='$returside' accesskey=L>";
	print "<button style='$buttonStyle; width:100%'onMouseOver=\"this.style.cursor='pointer'\">".findtekst('2172|Luk', $sprog_id)."</button></a></td>";

	print "<td width='80%' style='$topStyle' align='center'>".findtekst('1247|Indlæs sikkerhedskopi', $sprog_id)."</td>";

	print "<td width='10%' style='$topStyle' align='center'<br></td>";
	print "</tbody></table>";
	print "</td></tr>";
} else {
	print "<table width=\"100%\" height=\"30%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"$returside\" accesskey=L>".findtekst('2172|Luk', $sprog_id)."</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('1247|Indlæs sikkerhedskopi', $sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
}
    $upFn = if_isset($_FILES, NULL, 'uploadedfile') ? if_isset($_FILES['uploadedfile'], NULL, 'name') : NULL;

	if($upFn || if_isset($_POST,NULL,'filnavn')) { # 20160609
		
		############################# check for the file types first.
		include("../includes/connect.php");
		if($upFn)$filename = basename($_FILES['uploadedfile']['name']);
		else $filename = NULL; 
		if(!$filename && $filename = if_isset($_POST, NULL, 'filnavn') ); 

		$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		
		
		if ($extension !== 'sdat' && $extension !== 'sql') {
			echo "<script>alert('Only .sdat or .sql files are allowed.');</script>";
			header("Refresh: 1; URL=" . $_SERVER['REQUEST_URI']);
			exit;
		}
		
		###########################
		
	if ($restore=if_isset($_POST, NULL,'restore')) {
		
		if ($restore=='OK') {
			$backup_encode=if_isset($_POST, NULL, 'backup_encode');
			$backup_dbtype=if_isset($_GET, NULL, 'backup_dbtype');
			$filnavn=if_isset($_POST, NULL,'filnavn');
			restore($filnavn,$backup_encode,$backup_dbtype);
		} else {
		
			if (!if_isset($_POST,NULL,'mysql_db') ) {
				if(isset($filnavn)){
					unlink($filnavn);
				}elseif(isset($filename))unlink($filename);
			}
			
		} 
	}
	$upFe = if_isset($_FILES, NULL, 'uploadedfile') ? if_isset($_FILES['uploadedfile'], NULL, 'error') : NULL;
	if ($upFe) {
		$fejl = $upFe ;
	} else {
			$fejl = false;
	}
	if ($fejl) {
		switch ($fejl) {
			case 1: print "<BODY onLoad=\"javascript:alert('Filen er for stor - Kontroller upload_max_filesize i php.ini')\">";
			case 2: print "<BODY onLoad=\"javascript:alert('Filen er for stor - er det en SALDI-sikkerhedskopi?')\">";
		}
	}
	
	if ($filnavn=if_isset($_POST,NULL,'filnavn') && if_isset($_POST,NULL,'mysql_db')) {
		if($extension=='sql'){
		
			// Full path of file and its name
			$backupfil = $_POST['filnavn'];
			
			// Move the uploaded file from temporary storage to the desired location
			if (isset($_POST['mysql_db']) && isset($_POST['mysql_pass']) && $db_type == "postgresql") {					
				// Retrieve MySQL connection details from form
				
				$mysqlDb = $_POST['mysql_db'];
				$mysqlPass = $_POST['mysql_pass'];
	
				$mysqlHost = $sqhost;
				$mysqlUser = $_POST['mysql_user'];
	
				// Call the migration function
				migrateMySQLToPostgreSQL($sqhost, $squser, $sqpass, $db, $mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb, $backupfil);
			
			}
		}
	}
	$formSz = true;	
	$upFn1 = if_isset($_FILES, NULL, 'uploadedfile') ? if_isset($_FILES['uploadedfile'], NULL, 'name') : NULL;
	if ($upFn1 && basename($upFn1)) {
		
		$filnavn="../temp/".$db."/restore.gz";
		$tmp=$_FILES['uploadedfile']['tmp_name'];
		if($extension=='sql'){
			$tmp = $_FILES['uploadedfile']['tmp_name'];
			error_log("temp file;".$tmp);
			$target_file = "../temp/".$db."/";
			// Define the full path where the file will be stored
			$backupfil = $target_file . basename($_FILES['uploadedfile']['name']);
			// Move the uploaded file from temporary storage to the desired location
			if (move_uploaded_file($tmp, $backupfil)) {
				
				// Check if "MySQL Dump" is in the first line
				$handle = fopen($backupfil, 'r');
				if ($handle) {
					
					$result = findDumpInFirstThreeLines($handle);
					if(stripos(trim($result), 'MySQL dump') !== false) {
						//call mysql input function
						$formSz=false;
					 renderRestoreForm($db, $backupfil, $restoreV = 'Submit');
						
							###########
					}elseif(stripos(trim($result), 'PostgreSQL') !== false){
						$backup_encode=if_isset($_POST, NULL, 'backup_encode');
						$backup_dbtype=if_isset($_GET, NULL, 'backup_dbtype');
						restore($backupfil,$backup_encode,$backup_dbtype);
					}
					fclose($handle);
				} else {
					echo "Failed to open the file.";
					exit;
				}	
			}else{
				echo "Unable to move";
			}
		}else{
			system ("rm -rf ../temp/".$db."/*");
			if(move_uploaded_file($tmp, $filnavn)) {
				##########
				
				error_log('Filename: '.$filnavn);
				#system ("gunzip $filnavn");
				// Validate file existence
				if (!is_file($filnavn)) {
					exit("❌ File not found: $filnavn\n");
				}

				// Detect MIME type
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mimeType = finfo_file($finfo, $filnavn);
				finfo_close($finfo);

				// Build command based on file type
				$commands = [
					'application/gzip'   => "gunzip " . escapeshellarg($filnavn),
					'application/x-gzip' => "gunzip " . escapeshellarg($filnavn),
					'application/x-tar'  => "tar -xf " . escapeshellarg($filnavn),
				];

				if (isset($commands[$mimeType])) {
					
					system($commands[$mimeType], $exitCode);
					if ($exitCode === 0) {
						error_log("✅ Extraction successful.\n");
					} else {
						error_log("❌ Extraction failed with exit code $exitCode.\n");
					}
				} else {
					echo "⚠️Unsupported or unknown file type: $mimeType\n";
				}

				#################
				$filnavn=str_replace(".gz","",$filnavn);
				
				if (file_exists($filnavn)) system ("cd ../temp/$db\n/bin/tar -xf restore");
				else system ("cd ../temp/$db\n/bin/tar -xf restore.gz");
				$infofil="../temp/".$db."/temp/backup.info";
				$fp=fopen($infofil,"r");
				if ($fp) {
					$linje=trim(fgets($fp));
					list($backupdate,$backupdb,$backupver,$backupnavn,$backup_encode,$backup_dbtype)=explode(chr(9),$linje);
					$backupfil="../temp/".$db."/temp/".$backupdb.".sql";
					$backupdato=substr($backupdate,6,2)."-".substr($backupdate,4,2)."-".substr($backupdate,0,4);
					$backuptid=substr($backupdate,-4,2).":".substr($backupdate,-2,2);
				}
				fclose($fp);
				unlink($infofil);
			
				########################ConvertingMysqlDB
				$handle = fopen($backupfil, 'r');
				if ($handle) {
					
					$result = findDumpInFirstThreeLines($handle);
					if(stripos(trim($result), 'MySQL dump') !== false) {
						//call mysql input function
						$formSz=false;
						error_log('Back2 Up file ;'.$backupfil);
					 renderRestoreForm($db, $backupfil, $restoreV = 'Submit');
					}
					($handlfclosee);
				}
			    #######################
			
			if($formSz==true){
				print "<form name=restore action=restore.php?db=$db&backup_dbtype=$backup_dbtype method=post>";
				print "<tr><td valign=middle align=center><table><tbody>";
				$backupnavn=trim($backupnavn);
				$regnskab=trim($regnskab);
				if ($backupnavn && $backupnavn!=$regnskab) {
					print "<tr><td colspan=2>".findtekst('2422|Du er ved at overskrive dit regnskab', $sprog_id).": $regnskab<br>".findtekst('2423|med en sikkerhedskopi af regnskabet', $sprog_id).": $backupnavn "."fra den"."$backupdato kl. $backuptid.</td></tr>";	
					print "<input type=\"hidden\" name=\"backup_encode\" value=\"$backup_encode\">";
					print "<input type=\"hidden\" name=\"filnavn\" value=\"$backupfil\">";
				} elseif ($backupdate) {
					print "<tr><td colspan=2>".findtekst('2422|Du er ved at overskrive dit regnskab', $sprog_id).": $regnskab<br>".findtekst('2426|med en sikkerhedskopi fra den', $sprog_id)."$backupdato kl. $backuptid.</td></tr>";	
					print "<input type=\"hidden\" name=\"backup_encode\" value=\"$backup_encode\">";
					print "<input type=\"hidden\" name=\"filnavn\" value=\"$backupfil\">";
				} else {
					print "<tr><td colspan=2>".findtekst('2422|Du er ved at overskrive dit regnskab', $sprog_id).": $regnskab.</td></tr>";	
					print "<input type=\"hidden\" name=\"filnavn\" value=\"$filnavn\">";
				}
				print "<tr><td colspan=2><hr></td></tr>";	
				print "<tr><td align=center><input type=submit value=\"OK\" name=\"restore\"></td><td align=center><input type=submit value=\"Afbryd\" name=\"restore\"></td><tr>";
				print "</tbody></table></td></tr>";
				print "</form>";
			}

			} else {
				echo findtekst(2427, $sprog_id); //an error occured
			}
		}


	}	else upload($db);
} else upload($db);
print "</tbody></table></div>";
################################################################################################################
function upload($db){
	global $sprog_id;
	global $connection;
	global $translations;
	global $db_type;

	if ($db_type=='mysql' or $db_type=='mysqli') {
		echo '<span style="color:red;">This is not available yet!</span>';
		exit;
	}
	
	
	
		$textup = $translations[2422][$sprog_id];
		$textc = $translations[2425][$sprog_id];
		$load = $translations[1360][$sprog_id];
		$selectdfil = $translations[1364][$sprog_id];
	

	error_log("Textup: ".$load);
	

	print "<tr><td width=100% align=center><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<form enctype=\"multipart/form-data\" action=\"restore.php?db=$db\" method=\"POST\">";
#	print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"99999999\">";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center>".$textc."</td></tr>";
	print "<tr><td width=100% align=center><br></td></tr>";
	print "<tr><td width=100% align=center><hr width=50%></td></tr>";
	print "<tr><td width=100% align=center></td></tr>";
	print "<tr><td width=100% align=center>\"".$selectdfil."\": <input class=\"inputbox\" NAME=\"uploadedfile\" type=\"file\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td align=center><input type=\"submit\" value=\"".$load."\" onClick=\"return confirmSubmit(" . htmlspecialchars(json_encode($textup), ENT_QUOTES) . ")\"></td></tr>";
	print "<tr><td></form></td></tr>";
	print "</tbody></table>";
	print "</td></tr>";
}

function restore($filnavn,$backup_encode,$backup_dbtype){

	global $connection;
	global $s_id;
	global $regnskab;
	global $db;
	global $sqdb;
	global $squser;
	global $sqpass;
	global $sqhost;
	global $db_encode;
	global $db_type;
	global $charset;
	global $sprog_id;
	
	if (!$db_encode) $db_encode="LATIN9";
	if (!$backup_encode) $backup_encode="UTF8";
	if (!$db_type) $db_type="postgresql";
	if (!$backup_dbtype) $backup_dbtype="postgresql";
	
	$filnavn2="../temp/$db/restore.sql";

	$restore="";
	$fp=fopen("$filnavn","r");
	$fp2=fopen("$filnavn2","w");
	
	
	if ($fp) {
		while (!feof($fp)) {
			if ($linje=fgets($fp)) {
					if ($db_type=='mysql' or $db_type=='mysqli') {
					if (strpos($linje, "MySQL dump")) $dump = "OK";
				} elseif (strpos($linje, "PostgreSQL database dump")) $dump = "OK";
				if (strpos(strtolower($linje), "drop database")) {
					$restore = "NUL";
				}
				if (strpos(strtolower($linje), "drop database")) {
					$restore = "NUL";
				}
				if (strpos(strtolower($linje), "create database")) {
					$restore = "NUL";
				}
				if (strpos(strtolower($linje), "\\connect")) {
					$restore = "NUL";
				}
				if ($backup_encode!=$db_encode) {
					if ($db_encode=="UTF8" && $backup_encode=="LATIN9") {
						$linje=str_replace("SET client_encoding = 'LATIN9';","SET client_encoding = 'UTF8';",$linje);
						$ny_linje=utf8_encode($linje);
					}	elseif ($db_encode=="LATIN9" && $backup_encode=="UTF8") {
						$linje=str_replace("SET client_encoding = 'UTF8';","SET client_encoding = 'LATIN9';",$linje);
						$ny_linje=utf8_decode($linje);
					} else {
						$restore = "NUL";
					}
				} else $ny_linje=$linje;
			} else $ny_linje='';
			fwrite($fp2,"$ny_linje"); 
		}	
		if (!$restore && $dump) $restore="OK";
	} else echo "$filnavn ikke fundet";
	fclose($fp);
	fclose($fp2);
	if ($restore=='OK') {
		if ($db_type=='mysql') {
			mysql_select_db("$sqdb");
		} else if ($db_type=='mysqli') { #RG_mysqli
			$connection = db_connect ("$sqhost", "$squser", "$sqpass", "$sqdb");
			mysqli_select_db($connection, $sqdb);
		} 
		
		// else {
		// 	db_close($connection);
		// }
		db_modify("delete from online where db='$db'",__FILE__ . " linje " . __LINE__);
		db_modify("update regnskab set version = '' where db='$db'",__FILE__ . " linje " . __LINE__);
		db_modify("DROP DATABASE $db",__FILE__ . " linje " . __LINE__);
		db_create($db);
		print "<!-- Saldi-kommentar for at skjule uddata til siden \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
		$mysql = $psql = NULL;
		if (substr($db_type,0,5)=='mysql') {
			if (file_exists("/usr/bin/mysql")) $mysql = "/usr/bin/mysql";
			elseif (file_exists("/bin/mysql")) $mysql = "/usr/mysql";
			else echo "mysql not found<br>";
			if ($mysql) system("$mysql -u $squser --password=$sqpass -h $sqhost $db < $filnavn2");
		} else {
			if (file_exists("/usr/bin/psql")) $psql = "/usr/bin/psql";
			elseif (file_exists("/bin/psql")) $psql = "/usr/psql";
			else echo "psql not found<br>";
			if ($psql) system("export PGPASSWORD=$sqpass\n$psql -h $sqhost -U $squser $db < $filnavn2");
		}
		db_close($connection);
		print "<BODY ONLOAD=\"javascript:alert('Regnskabet er genskabt. Du skal logge ind igen!')\">";
	
		unlink($filnavn);
		unlink($filnavn2);
		print "--> \n"; # Indsat da svar fra pg_dump kan resultere i besked genereres
		if ($popup) {
			print "<BODY ONLOAD=\"JavaScript:opener.location.reload();\"";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../includes/luk.php\">";
		} else print "<meta http-equiv=\"refresh\" content=\"0;URL=../index/index.php?regnskab=".htmlentities($regnskab,ENT_COMPAT,$charset)."\">";
	 
	} else {
		unlink($filnavn);
		unlink($filnavn2);
		print "<BODY ONLOAD=\"javascript:alert('Det er ikke en SALDI-sikkerhedskopi, som fors&oslash;ges indl&aelig;st')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=backup.php\">";
	}
	
	print "</tbody></table>";
	}
	

print "</div></div></div>";
##########################
function renderRestoreForm($db, $backupfil, $restoreV = 'Submit') {
	echo '<p style="color: red; font-weight: bold;">Please note: This operation may take up to 12 minutes to complete.</p>';

    echo "<form name='restore' action='restore.php?db=$db' method='post'>";
    echo "<input type='hidden' name='filnavn' value='$backupfil'>";
    echo "<table cellpadding='5' cellspacing='0' border='0' align='center'>";
    echo "<tr><td colspan='2'><hr></td></tr>";

    echo "<tr>";
    echo "<td><label for='mysql_db'>MySQL Database Name:</label></td>";
    echo "<td><input type='text' name='mysql_db' required></td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td><label for='mysql_user'>MySQL Database User:</label></td>";
    echo "<td><input type='text' name='mysql_user' required></td>";
    echo "</tr>";

    echo "<tr>";
    echo "<td><label for='mysql_pass'>MySQL Password:</label></td>";
    echo "<td><input type='password' name='mysql_pass' required></td>";
    echo "</tr>";

    echo "<tr><td colspan='2'><hr></td></tr>";

    echo "<tr>";
    echo "<td align='center'><input type='submit' value='$restoreV' name='restore'></td>";
    echo "<td align='center'><input type='submit' value='Afbryd' name='restore'></td>";
    echo "</tr>";

    echo "</table>";
    echo "</form>";
}
function findDumpInFirstThreeLines($handle) {
    // Read the first three lines
    $firstLine = fgets($handle);
    $secondLine = fgets($handle);
    $thirdLine = fgets($handle);

    // Check for the string "dump" in each line
    if (strpos($firstLine, 'dump') !== false) {
        $result = $firstLine;
    } elseif (strpos($secondLine, 'dump') !== false) {
        $result = $secondLine;
    } elseif (strpos($thirdLine, 'dump') !== false) {
        $result = $thirdLine;
    } else {
        $result = null;
    }

    // Close the file
    fclose($handle);

    return $result;
}

#++++++++++++++++++++
function migrateMySQLToPostgreSQL(
    $pgHost, $pgUser, $pgPass, $pgDb,
    $mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb,
    $backupfil
) {
    /* Check backup file */
    $backUpDir = "../temp/backup/$pgDb/";
    foreach (glob($backUpDir . $pgDb . '*.sdat') as $file) break;

    if (!isset($file)) {
        die("DEBUG: No backup found for database $pgDb");
    }

    /* Ensure MySQL database exists */
    $conn = mysqli_connect($mysqlHost, $mysqlUser, $mysqlPass);
    if (!$conn) die("MySQL connect failed: " . mysqli_connect_error());

    $res = mysqli_query($conn, "SHOW DATABASES LIKE '$mysqlDb'");
    if (mysqli_num_rows($res) == 0) {
        echo "DEBUG: Creating MySQL database $mysqlDb\n";
        mysqli_query(
            $conn,
            "CREATE DATABASE `$mysqlDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );
    }
    mysqli_close($conn);

    /* Import MySQL backup */
    exec(
        "mysql -u$mysqlUser -p$mysqlPass $mysqlDb < " . escapeshellarg($backupfil),
        $out,
        $ret
    );
    if ($ret !== 0) die("DEBUG: MySQL import failed");

    /* Recreate PostgreSQL database */
    $pgConn1 = pg_connect("host=$pgHost user=$pgUser password=$pgPass");
    if (!$pgConn1) die(pg_last_error());

    pg_query($pgConn1, "
        SELECT pg_terminate_backend(pid)
        FROM pg_stat_activity
        WHERE datname = '$pgDb' AND pid <> pg_backend_pid()
    ");
    pg_query($pgConn1, "DROP DATABASE IF EXISTS \"$pgDb\"");
    pg_query($pgConn1, "CREATE DATABASE \"$pgDb\"");
    pg_close($pgConn1);

    /* Connect to PostgreSQL and MySQL */
    $pgConn = pg_connect("host=$pgHost dbname=$pgDb user=$pgUser password=$pgPass");
    if (!$pgConn) die(pg_last_error());

    $mysqlConn = new mysqli($mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb);
    if ($mysqlConn->connect_error) die($mysqlConn->connect_error);

    $tablesResult = $mysqlConn->query("SHOW TABLES");
    if (!$tablesResult) die($mysqlConn->error);

    pg_query($pgConn, "BEGIN");

    $sequenceTracking = [];
    $createdTables = [];

    try {

        /* Convert and create tables */
        while ($table = $tablesResult->fetch_row()) {
            $origTable = $table[0];
            $tableName = strtolower($origTable);

            echo "DEBUG: Processing table $tableName\n";

            $exists = pg_fetch_row(pg_query(
                $pgConn,
                "SELECT EXISTS (
                    SELECT 1 FROM information_schema.tables
                    WHERE table_schema='public' AND table_name='$tableName'
                )"
            ))[0];

            if ($exists === 't') continue;

            $ct = $mysqlConn->query("SHOW CREATE TABLE `$origTable`")->fetch_row()[1];

            /* Normalize identifiers */
            $ct = str_replace('`', '"', $ct);
            $ct = str_replace("\"$origTable\"", "\"$tableName\"", $ct);

            /* Remove MySQL-only table options */
            $ct = preg_replace('/ENGINE\s*=\s*\w+/i', '', $ct);
            $ct = preg_replace('/DEFAULT\s+CHARSET\s*=\s*\w+/i', '', $ct);
            $ct = preg_replace('/AUTO_INCREMENT\s*=\s*\d+/i', '', $ct);
            $ct = preg_replace('/ON\s+UPDATE\s+CURRENT_TIMESTAMP/i', '', $ct);
            $ct = preg_replace('/\s+UNSIGNED\b/i', '', $ct);

            /* Remove column-level charset and collation */
            $ct = preg_replace(
                '/\s+CHARACTER\s+SET\s+\w+(\s+COLLATE\s+[\w_]+)?/i',
                '',
                $ct
            );
            $ct = preg_replace('/\s+COLLATE\s+[\w_]+/i', '', $ct);

            /* AUTO_INCREMENT → SERIAL / BIGSERIAL (no PK here) */
            $ct = preg_replace_callback(
                '/"(\w+)"\s+(bigint|int)(?:\(\d+\))?\s+NOT\s+NULL\s+AUTO_INCREMENT/i',
                function ($m) use (&$sequenceTracking, $tableName) {
                    $type = strtolower($m[2]) === 'bigint' ? 'BIGSERIAL' : 'SERIAL';
                    $sequenceTracking[] = [$tableName, $m[1]];
                    return "\"{$m[1]}\" $type";
                },
                $ct
            );

            $ct = preg_replace('/\s+AUTO_INCREMENT\b/i', '', $ct);

            /* Type conversions */
            $ct = preg_replace('/ENUM\s*\([^)]+\)/i', 'TEXT', $ct);
            $ct = preg_replace('/tinyint\s*\(\s*1\s*\)/i', 'BOOLEAN', $ct);
            $ct = preg_replace('/\btinyint\b/i', 'SMALLINT', $ct);
            $ct = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $ct);
            $ct = preg_replace('/decimal\s*\(\s*(\d+)\s*,\s*0\s*\)/i', 'DECIMAL($1)', $ct);


			// Remove MySQL integer length specifiers: int(11), bigint(20), etc.
			$ct = preg_replace(
				'/\b(int|integer|bigint|smallint|mediumint|tinyint)\s*\(\s*\d+\s*\)/i',
				'$1',
				$ct
			);

			// Remove DEFAULT NULL (PostgreSQL default is NULL)
			$ct = preg_replace('/\s+DEFAULT\s+NULL/i', '', $ct);

            /* Remove MySQL indexes */
            $ct = preg_replace('/UNIQUE\s+KEY\s+"?[\w_]+"\s*\([^)]+\)/i', '', $ct);
            $ct = preg_replace('/KEY\s+"?[\w_]+"\s*\([^)]+\)/i', '', $ct);

            /* Final cleanup */
            $ct = preg_replace('/\s+/', ' ', $ct);
            $ct = preg_replace('/,\s*,+/', ',', $ct);

            while (preg_match('/,\s*\)/', $ct)) {
                $ct = preg_replace('/,\s*\)/', ')', $ct);
            }

            $ct = rtrim($ct, " ;");

						$ct = str_replace('"','',$ct);
						$ct = str_replace('COLLATE=latin1_swedish_ci','',$ct);

            if (!pg_query($pgConn, $ct)) {
                pg_query($pgConn, "ROLLBACK");
                die("PG ERROR:\n" . pg_last_error($pgConn) . "\n\n$ct");
            }

            $createdTables[$tableName] = true;

            /* Copy data */
            $data = $mysqlConn->query("SELECT * FROM `$origTable`");
            if (!$data || $data->num_rows === 0) continue;

            while ($row = $data->fetch_assoc()) {
                $cols = array_keys($row);
                $vals = array_map(fn($v) => $v === '' ? null : $v, array_values($row));
                $ph   = array_map(fn($i) => '$' . ($i + 1), array_keys($cols));

                $sql = 'INSERT INTO public."' . $tableName . '" (' .
                       implode(',', $cols) . ') VALUES (' .
                       implode(',', $ph) . ')';

                if (!pg_query_params($pgConn, $sql, $vals)) {
                    pg_query($pgConn, "ROLLBACK");
                    die("INSERT ERROR: " . pg_last_error($pgConn));
                }
            }
        }

        /* Fix sequences */
        foreach ($sequenceTracking as [$t, $c]) {
            $seq = pg_fetch_row(pg_query(
                $pgConn,
                "SELECT pg_get_serial_sequence('public.\"$t\"','$c')"
            ))[0];

            $max = pg_fetch_row(pg_query(
                $pgConn,
                "SELECT MAX(\"$c\") FROM public.\"$t\""
            ))[0] ?? 1;

            pg_query($pgConn, "SELECT setval('$seq', $max)");
        }

        pg_query($pgConn, "COMMIT");
        echo "DEBUG: Migration completed successfully\n";

    } catch (Throwable $e) {
        pg_query($pgConn, "ROLLBACK");
        die("EXCEPTION: " . $e->getMessage());
    }

    /* Cleanup */
    $mysqlConn->close();
    pg_close($pgConn);
    system("rm -rf ../temp/$pgDb/*");
		print "<meta http-equiv=\"refresh\" content=\"4;URL=../index/logud.php\">";
}




#++++++++++++++++++++
#####################


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>

