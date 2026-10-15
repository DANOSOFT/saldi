<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/fixtures/modify-trail/legacy_db_modify.php --- ver 5.0.0 --- 2026.10.07
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261007 CDX/LH Freeze the pre-extraction db_modify implementation for byte comparisons.

namespace SaldiTrailBaseline;

	function db_modify($qtext, $spor, $global = false) {
		global $brugernavn;
		global $connection,$customAlertText;
		global $db,$db_skriv_id,$db_type;
		global $sqdb,$sqhost,$squser,$sqpass;
		global $webservice;

		$temp = get_relative() . 'temp/' . $db;

		$qtext=injecttjek($qtext);

		// When $global=true, run the query against the global database instead of the user's database.
		$use_connection = db_query_connection($global);

		#20190704 START
		if ($db_type == "mysql" || $db_type == "mysqli") {

            $db_query = mysqli_query($use_connection, $qtext);  //mysql_query deprecated in php 7 and above
			if (!$db_query) {
				$error_message = "Error executing query: " . mysqli_error($use_connection) . " | Query: $qtext";
				error_log($error_message);

			}

		}else {
			$qtext=str_replace(' like ',' ilike ',$qtext);
			$db_query=pg_query($use_connection, $qtext);
		}
		#20190704 END

		(isset($db)) ? $db=trim($db) : $db='';
		if ($db_skriv_id>1 && $db != $sqdb) {
			db_log_append("$temp/.ht_modify.log", [
				"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor.": ".$db_skriv_id."\n",
				$qtext.";\n",
			]);
		}
		if (!$db_query) { #20190704
			#if ($db_type=="mysql")       $errtxt = mysql_error($connection);
			if ($db_type=="mysqli") $errtxt = mysqli_error($use_connection); #20190704
			else $errtxt=pg_last_error($use_connection);
			db_log_append("$temp/.ht_modify.log", [
				"-- ".$brugernavn." ".date("Y-m-d H:i:s").": ".$spor."\n",
				"-- Fejl!! ".$qtext." | $errtxt;\n",
			]);
			$message=$db." | ".$qtext." | ".$spor." | ".$brugernavn." ".date("Y-m-d H:i:s")." | $errtxt";
			if (strstr($spor,"includes/opdat")) {
				if (file_exists("$temp/opdatfejl.txt")) {
					$ff=fopen("$temp/opdatfejl.txt","r");
					$lastmail=trim(fgets($ff));
					fclose($ff);
				} else $lastmail=0;
				if($lastmail!=date("U")) {
					if ($sqdb == 'develop') echo "$message<br>";
					else {
						$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
						mail('fejl@saldi.dk', 'SALDI Opdat fejl', $message, $headers);
					}
					db_log_append("$temp/opdatfejl.txt", date("U")."\n", 'w');
				}
			} else {
				if (file_exists("$temp/modifyfejl.txt")) {
					$ff=fopen("$temp/modifyfejl.txt","r");
					$lastmail=trim(fgets($ff));
					fclose($ff);
				} else $lastmail=0;
				if($lastmail!=date("U")) {
					if ($sqdb == 'develop') echo "$message<br>";
					else {
						$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
						mail('fejl@saldi.dk', 'SALDI Fejl - modify', $message, $headers);
					}
					db_log_append("$temp/modifyfejl.txt", date("U")."\n", 'w');
				}
				// if ($db_type=="mysql") {
				// 	mysql_query("ROLLBACK");
				// } elseif ($db_type=="mysqli") { #20190704
				// 	mysqli_query($connection, "ROLLBACK");
				// }
				#if ($db_type == "mysql" || $db_type == "mysqli") {
					#mysqli_query($connection, "ROLLBACK");
				#}

				(isset($customAlertText))?$alerttekst=$customAlertText:$alerttekst="Uforudset hændelse, kontakt salditeamet på telefon 4690 2208";
				if ($webservice) {
					global $db_modify_fejl; #20260729 SZ surface write failures so posting-path callers can detect a failed statement even though they don't check this return value (SD-595)
					$db_modify_fejl = true;
					return ('1'.chr(9)."$alerttekst");
				}
				alert("$alerttekst");
				exit;
			}
		}
		return ('0'.chr(9).'query accepted');
	}
