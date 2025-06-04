<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---------------admin/restore.php--------lap 4.1.1------2025-05-16-----------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
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
$title="SALDI - genindl&aelig;s sikkerhedskopi";
$modulnr=11;
$css="../css/standard.css";
$backupdate=$backupdb=$backupver=$backupnavn=$filnavn=$menu=$regnskab=$timezone=$popup=NULL;

include("../includes/connect.php");
if (isset($_GET['db']) && $_GET['db']) {
	$db=$sqdb;
	$tmpDb=$_GET['db'];
	if (!db_exists($tmpDb)) {
		db_create($tmpDb);		
	}
	if (!$regnskab) {
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

print "<div align=\"center\">";
if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\"><a href=backup.php accesskey=L title='Klik her for at komme tilbage'><i class='fa fa-close fa-lg'></i> &nbsp;".findtekst(30,$sprog_id)."</a></div>";     
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
} else {
	print "<table width=\"100%\" height=\"30%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"$returside\" accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">Indl&aelig;s sikkerhedskopi</td>";
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
						echo "❌ Extraction failed (exit code: $exitCode)\n";
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
					print "<tr><td colspan=2>".findtekst(2422, $sprog_id).": $regnskab<br>".findtekst(2423, $sprog_id).": $backupnavn "."fra den"."$backupdato kl. $backuptid.</td></tr>";	
					print "<input type=\"hidden\" name=\"backup_encode\" value=\"$backup_encode\">";
					print "<input type=\"hidden\" name=\"filnavn\" value=\"$backupfil\">";
				} elseif ($backupdate) {
					print "<tr><td colspan=2>".findtekst(2422, $sprog_id).": $regnskab<br>".findtekst(2426, $sprog_id)."$backupdato kl. $backuptid.</td></tr>";	
					print "<input type=\"hidden\" name=\"backup_encode\" value=\"$backup_encode\">";
					print "<input type=\"hidden\" name=\"filnavn\" value=\"$backupfil\">";
				} else {
					print "<tr><td colspan=2>".findtekst(2422, $sprog_id).": $regnskab.</td></tr>";	
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
function migrateMySQLToPostgreSQL($pgHost, $pgUser, $pgPass, $pgDb, $mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb, $backupfil) {
   
	 // Check if the backup file exists
	#########
	 $backUpDir = "../temp/backup/$pgDb/";
				if (is_dir($backUpDir)) {
					foreach (glob($backUpDir . $pgDb . '*.sdat') as $file) {
						
						break; 
					}
				}
	$filename = basename($file); // remove any path
	if (!str_starts_with($filename, $pgDb) && !str_ends_with($filename, '.sdat')) {
		#die("Error: No backup found for the PostgreSQL database '$pgDb'. Migration aborted.\n");

			echo "<script type='text/javascript'>
				alert('Error: No backup found for the PostgreSQL database \"$pgDb\". Migration aborted.');
				window.location.href = 'backup.php';
			</script>";

			die(" Migration aborted.\n");
			
	}
	
	################

		// Connect to MySQL server
		#$conn = mysqli_connect($mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb);;
		$conn = mysqli_connect($mysqlHost, $mysqlUser, $mysqlPass);

		if (!$conn) {
			print "script>alert('MySQL connection failed: "  . "');</script>";

			die("Connection failed: " . mysqli_connect_error() . "\n");
		}

		// Check if the database exists
		$result = mysqli_query($conn, "SHOW DATABASES LIKE '$mysqlDb'");
		if (mysqli_num_rows($result) == 0) {
			// Create the database
			if (mysqli_query($conn, "CREATE DATABASE `$mysqlDb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
				echo "Database '$mysqlDb' created successfully.\n";
			} else {
				die("Error creating database: " . mysqli_error($conn) . "\n");
			}
		} else {
			echo "Database '$mysqlDb' already exists.\n";
		}

		mysqli_close($conn);

		// Import the SQL dump using system call
		$command = "mysql -u$mysqlUser -p$mysqlPass $mysqlDb < " . escapeshellarg($backupfil);
		exec($command, $output, $result);

		if ($result === 0) {
			echo "SQL file imported successfully.\n";
		} else {
			error_log("backUpfil; ".$backupfil);
			  die("Failed to import fileExit code: $result\n");
		}

		###############
	
	// Connect to PostgreSQL without specifying the database name
    $pgConn1 = pg_connect("host=$pgHost user=$pgUser password=$pgPass");

    if (!$pgConn1) {
        die("PostgreSQL connection failed: " . pg_last_error());
    }


    
    $dbCheckQuery = "SELECT 1 FROM pg_database WHERE datname = '$pgDb'";
    $dbCheckResult = pg_query($pgConn1, $dbCheckQuery);
	db_modify("delete from online where db='$pgDb'",__FILE__ . " linje " . __LINE__);
	db_modify("update regnskab set version = '' where db='$pgDb'",__FILE__ . " linje " . __LINE__);

		#############
		if (pg_num_rows($dbCheckResult) > 0) {
			// Terminate connections to it
			 $terminateSql = "
				SELECT pg_terminate_backend(pid)
				FROM pg_stat_activity
				WHERE datname = " . pg_escape_literal($pgConn1, $pgDb) . "
				AND pid <> pg_backend_pid();
			";
			pg_query($pgConn1, $terminateSql);
		
			// Drop the database
			pg_query($pgConn1, "DROP DATABASE \"$pgDb\"");
			// Refresh the check
			$dbCheckResult = pg_query($pgConn1, "SELECT 1 FROM pg_database WHERE datname = '$pgDb'");
		}
		############

    if (pg_num_rows($dbCheckResult) == 0) {
        // Database doesn't exist, create it
        $createDbQuery = "CREATE DATABASE $pgDb";
        $createDbResult = pg_query($pgConn1, $createDbQuery);
        if (!$createDbResult) {
            die("Error creating PostgreSQL database: " . pg_last_error());
        }
        echo "PostgreSQL database '$pgDb' created successfully.\n";
    } else {
        echo "PostgreSQL database '$pgDb' already exists.\n";
    }

    // Close the initial connection and reconnect to the specific PostgreSQL database
    pg_close($pgConn1);

    // Reconnect to PostgreSQL with the specific database
    $pgConn = pg_connect("host=$pgHost dbname=$pgDb user=$pgUser password=$pgPass connect_timeout=5");

    if (!$pgConn) {
        die("PostgreSQL connection failed: " . pg_last_error());
    }

    //  Connect to MySQL
    $mysqlConn = new mysqli($mysqlHost, $mysqlUser, $mysqlPass, $mysqlDb);
    if ($mysqlConn->connect_error) {
        die("MySQL connection failed: " . $mysqlConn->connect_error);
    }

    //  Get all tables from MySQL
    $tablesResult = $mysqlConn->query("SHOW TABLES");
    if (!$tablesResult) {
        die("Error fetching tables from MySQL: " . $mysqlConn->error);
    }

    //  Start the PostgreSQL transaction
    pg_query($pgConn, "BEGIN");  // Begin the transaction
	$sequenceTracking = []; // Store table + column pairs to update later 
	try {
		
		// Loop through each table and transfer schema and data
		while ($table = $tablesResult->fetch_row()) {
			$tableName = $table[0];

			###############Check first if the table already exists

			$checkTableSQL = "SELECT to_regclass('public.\"$tableName\"')";
			$checkResult = pg_query($pgConn, $checkTableSQL);
			$checkRow = pg_fetch_row($checkResult);
			
			if ($checkRow[0] !== null) {
				#echo "Table $tableName already exists in PostgreSQL. Skipping...\n";
				continue; // Skip to next table
			}
			// Get the MySQL schema for the table
			$createTableQuery = "SHOW CREATE TABLE `$tableName`";
			$createTableResult = $mysqlConn->query($createTableQuery);
			if (!$createTableResult) {
				die("Error fetching table schema for $tableName: " . $mysqlConn->error);
			}

			$createTable = $createTableResult->fetch_row();
			$createTableSQL = $createTable[1];

			// Modify the CREATE TABLE SQL to be PostgreSQL-compatible
			$createTableSQL = str_replace('`', '"', $createTableSQL); // Convert backticks to double quotes
			$createTableSQL = preg_replace('/\s+unsigned\b/i', '', $createTableSQL); // Remove 'unsigned' keyword (not supported in PostgreSQL)

			$createTableSQL = preg_replace_callback(
				'/"(\w+)"\s+(?:bigint|int)(?:\(\d+\))?(?:\s+unsigned)?\s+NOT NULL\s+AUTO_INCREMENT/i',
				function ($matches) use (&$sequenceTracking, $tableName) {
					$col = $matches[1];
					$type = stripos($matches[0], 'bigint') !== false ? 'BIGSERIAL' : 'SERIAL';
					$sequenceTracking[] = [$tableName, $col]; 
					return "\"$col\" $type PRIMARY KEY";
				},
				$createTableSQL
			);
			$createTableSQL = preg_replace('/\s*AUTO_INCREMENT\s*/i', '', $createTableSQL);
			$createTableSQL = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $createTableSQL);
			$createTableSQL = preg_replace('/ENGINE\s*=\s*\w+/i', '', $createTableSQL);
			$createTableSQL = preg_replace('/DEFAULT CHARSET\s*=\s*\w+/i', '', $createTableSQL);
			$createTableSQL = preg_replace('/COLLATE\s*=\s*[\w_]+/i', '', $createTableSQL);

			// Handle ENUM columns
			$createTableSQL = preg_replace_callback('/ENUM\(([^)]+)\)/i', function ($matches) {
				return 'TEXT';  // Replace ENUM with TEXT
			}, $createTableSQL);

			// Remove UNIQUE KEY and KEY lines
			$createTableSQL = preg_replace('/UNIQUE KEY\s+"?[\w\d_]+"?\s*\([^)]+\)/i', '', $createTableSQL);
			$createTableSQL = preg_replace('/KEY\s+"?[\w\d_]+"?\s*\([^)]+\)/i', '', $createTableSQL);

			// Replace UNIQUE KEY with UNIQUE
			$createTableSQL = preg_replace('/UNIQUE\s+KEY/i', 'UNIQUE', $createTableSQL);

			// Remove DEFAULT NULL (PostgreSQL defaults to NULL if no DEFAULT is provided)
			$createTableSQL = preg_replace('/\s+DEFAULT\s+NULL/i', '', $createTableSQL);

			// Handle DECIMAL (15,0) correctly by removing unnecessary assignment of values
			$createTableSQL = preg_replace_callback(
				'/"(\w+)"\s+decimal\(\s*(\d+)\s*,\s*0\s*\)\s+DEFAULT\s+\'?(\d+)\'?/i',
				fn($m) => "\"{$m[1]}\" DECIMAL({$m[2]}) DEFAULT {$m[3]}",
				$createTableSQL
			);

			
			//Remove the PRIMARY KEY clause if ev_id already includes it
			$createTableSQL = preg_replace(
				'/,\s*PRIMARY KEY\s*\("ev_id"\)/i',
				'',
				$createTableSQL
			);
			// REMOVE ANY ROGUE '=2 )' EXPRESSIONS
			$createTableSQL = preg_replace('/=\s*\d*\s*\)/', ')', $createTableSQL);

			// REMOVE ANY OTHER ROGUE EXPRESSIONS LIKE '=X' AT THE END OF COLUMNS
			$createTableSQL = preg_replace('/\s*=\s*\d*\s*$/', '', $createTableSQL);

			
			// Remove duplicate PRIMARY KEY clauses
			$createTableSQL = preg_replace('/PRIMARY KEY\s+\("id"\)/i', '', $createTableSQL);

			// Clean up any extra whitespace in the SQL
			$createTableSQL = preg_replace('/\s+/', ' ', $createTableSQL);

			// Remove any repeated commas (e.g., ", , , ,")
			$createTableSQL = preg_replace('/(,\s*)+/', ',', $createTableSQL);

			// Replace tinyint(1) with BOOLEAN
			$createTableSQL = preg_replace('/tinyint\s*\(\s*1\s*\)/i', 'BOOLEAN', $createTableSQL);
			//Remove comma before the closing parenthesis
			$createTableSQL = preg_replace('/,\s*(?=\))/', '', $createTableSQL);

			// Replace MySQL DATETIME with PostgreSQL TIMESTAMP
			$createTableSQL = preg_replace('/\bDATETIME\b/i', 'TIMESTAMP', $createTableSQL);

			// Remove any trailing commas before the closing parenthesis
			$createTableSQL = preg_replace('/\)\s*\)+\s*$/', ')', $createTableSQL);

			$createTableSQL = rtrim($createTableSQL); // Remove whitespace at the end
			// Remove comma before closing parenthesis (", )" → ")")
			$createTableSQL = preg_replace('/,\s*\)/', ')', $createTableSQL);

			// Ensure balanced parentheses (count of '(' matches count of ')')
			$openParens = substr_count($createTableSQL, '(');
			$closeParens = substr_count($createTableSQL, ')');
			if ($closeParens < $openParens) {
				$createTableSQL .= str_repeat(')', $openParens - $closeParens);
			}

			// Ensure it ends with a closing parenthesis (if somehow still broken)
			$createTableSQL = rtrim($createTableSQL);
			if (!str_ends_with($createTableSQL, ')')) {
				$createTableSQL .= ')';
			}
			// Remove any rogue closing parentheses, such as ")),"
			$createTableSQL = rtrim($createTableSQL, ')') . ')'; // Fix if we have double closing parentheses

			// Ensure balanced parentheses (count of '(' matches count of ')')
			$openParens = 0;  // Count of opening parentheses '('
			$closeParens = 0; // Count of closing parentheses ')'

			// Find position of first opening parenthesis '('
			$firstParenPos = strpos($createTableSQL, '(');

			// If there's an opening parenthesis after CREATE TABLE, count parentheses
			if ($firstParenPos !== false) {
				// Iterate through the string starting from the first parenthesis
				for ($i = $firstParenPos; $i < strlen($createTableSQL); $i++) {
					if ($createTableSQL[$i] === '(') {
						$openParens++;
					} elseif ($createTableSQL[$i] === ')') {
						$closeParens++;
					}
				}

				// If there are more opening parentheses than closing, append exactly one closing parenthesis
				if ($openParens > $closeParens) {
					$createTableSQL .= ')';  // Append only one closing parenthesis
				}
			}




			// Final check for multiple commas in a row
			if (strpos($createTableSQL, ', ,') !== false) {
				echo "Warning: Potential double commas still in CREATE TABLE for $tableName:\n$createTableSQL\n";
			}

			// Create the table in PostgreSQL
			$result = pg_query($pgConn, $createTableSQL);
			if (!$result) {
				// If an error occurs, rollback the transaction
				pg_query($pgConn, "ROLLBACK");
				echo "\n📄 Final SQL for table $tableName:\n$createTableSQL\n\n";
				die("Error creating table $tableName in PostgreSQL: " . pg_last_error());
			}

		

			// Get the data from MySQL for this table
			$dateColumns = [];
			$numericColumns = [];

			$columnTypeQuery = "
				SELECT column_name, data_type 
				FROM information_schema.columns 
				WHERE table_schema = 'public' AND table_name = '$tableName'
			";
			$columnTypeResult = pg_query($pgConn, $columnTypeQuery);
			if (!$columnTypeResult) {
				pg_query($pgConn, "ROLLBACK");
				die("Error fetching column types for $tableName: " . pg_last_error());
			}

			while ($col = pg_fetch_assoc($columnTypeResult)) {
				$colName = $col['column_name'];
				$type = strtolower($col['data_type']);

				if (in_array($type, ['integer', 'bigint', 'numeric', 'decimal', 'real', 'double precision'])) {
					$numericColumns[] = $colName;
				}
				if (in_array($type, ['date', 'timestamp without time zone', 'timestamp with time zone'])) {
					$dateColumns[] = $colName;
				}
			}

			$dataResult = $mysqlConn->query("SELECT * FROM `$tableName`");

			if (!$dataResult) {
				pg_query($pgConn, "ROLLBACK");
				die("Error fetching data from $tableName: " . $mysqlConn->error);
			}

			if ($dataResult->num_rows === 0) {
			# echo "No data found in $tableName, skipping insert.\n";
				continue;
			}

			while ($row = $dataResult->fetch_assoc()) {
				// Ensure that $row is valid before proceeding
				if (!is_array($row) || empty($row)) {
				#   echo "Skipping invalid or empty row...\n";
					continue; // Skip if $row is not an array or is empty
				}

				$columns = array_keys($row);

				// Create the placeholders for the SQL query (i.e., $1, $2, $3, ...)
				$placeholders = array_map(function ($index) {
					return '$' . ($index + 1); // Use index to create placeholders $1, $2, ...
				}, range(0, count($columns) - 1));

				// Prepare the SQL query with placeholders
				$insertSQL = "INSERT INTO \"$tableName\" (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

				// Map the values to bind them to the placeholders
				$values = array_map(function ($column, $value) use ($numericColumns, $dateColumns) {
					// Handle numeric columns
					if (in_array($column, $numericColumns)) {
						return ($value === "" || $value === null || !is_numeric($value)) ? null : $value;
					}

					// Handle date/timestamp columns
					if (in_array($column, $dateColumns)) {
						return ($value === "" || $value === null) ? null : $value; // PostgreSQL will handle dates properly
					}

					// Handle BOOLEAN columns (like 'status')
					if ($column == 'status') {
						return ($value === "" || $value === null) ? null : (strtolower($value) === 'true' || $value == 1);
					}

					// Default: return string values, NULL if empty
					return ($value === "" || $value === null) ? null : $value;
				}, array_keys($row), array_values($row));

				// Execute the query with parameter binding
				$insertResult = pg_query_params($pgConn, $insertSQL, $values);
				
				if (!$insertResult) {
					pg_query($pgConn, "ROLLBACK");
					die("Error: " . pg_last_error($pgConn));
				}
			}

			
		}

			#############
			foreach ($sequenceTracking as [$table, $column]) {
				// Try to get the attached sequence name
				$seqQuery = pg_query($pgConn, "SELECT pg_get_serial_sequence('\"$table\"', '$column')");
				$sequenceName = null;

				if ($seqQuery && pg_num_rows($seqQuery) > 0) {
					$seqRow = pg_fetch_row($seqQuery);
					$sequenceName = $seqRow[0];
				}

				// create sequence if not set
				if (!$sequenceName) {
					$sequenceName = "{$table}_{$column}_seq";
					error_log( "⚠️ Creating missing sequence $sequenceName for $table.$column\n");
					pg_query($pgConn, "CREATE SEQUENCE \"$sequenceName\"");
					pg_query($pgConn, "ALTER TABLE \"$table\" ALTER COLUMN \"$column\" SET DEFAULT nextval('\"$sequenceName\"')");
				}

				// Get the max ID currently in the column
				$maxIdQuery = pg_query($pgConn, "SELECT MAX(\"$column\") FROM \"$table\"");
				if (!$maxIdQuery) {
					error_log( "⚠️ Warning: Could not fetch max ID from $table.$column\n");
					continue;
				}

				$maxIdRow = pg_fetch_row($maxIdQuery);
				$maxId = $maxIdRow[0];
				if ($maxId === null || $maxId < 1) {
					$maxId = 1; // PostgreSQL sequences must be >= 1
				}

				// Set the sequence to the correct next value
				$setValSQL = "SELECT setval('$sequenceName', $maxId)";
				$setValResult = pg_query($pgConn, $setValSQL);
				if (!$setValResult) {
					error_log( "❌ Error setting sequence value for $sequenceName: " . pg_last_error() . "\n");
				} else {
					error_log("✅ Sequence $sequenceName set to $maxId for $table.$column\n");
				}
			}

		// Commit the transaction if all tables were created and data inserted successfully
		pg_query($pgConn, "COMMIT");
		echo "Database migration from MySQL to PostgreSQL completed successfully.\n";
	} catch (Exception $e) {
		// Rollback the transaction on error
		pg_query($pgConn, "ROLLBACK");
		echo "Error: " . $e->getMessage() . "\n";

		// Attempt to restore the backup from the $backupfil
		echo "Restoring PostgreSQL database '$pgDb' from backup...\n";
		$restoreCommand = "psql -h $pgHost -U $pgUser -d $pgDb -f $backupfil";
		$output = null;
		$resultCode = null;
		
		// Run the restore command and capture output and result code
		exec($restoreCommand, $output, $resultCode);
		
		if ($resultCode !== 0) {
			die("Error: Failed to restore the backup for PostgreSQL database '$pgDb'. Migration aborted.\n");
		} else {
			echo "Backup restored successfully from: $backupfil\n";
		}
	}

    // Close the connections
    $mysqlConn->close();
    pg_close($pgConn);
	system ("rm -rf ../temp/".$pgDb."/*");
    echo "Database migration completed.\n";
}

#++++++++++++++++++++
#####################


if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}
?>

