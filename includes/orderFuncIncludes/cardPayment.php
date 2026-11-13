<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//--- includes/orderFuncIncludes/cardPayment.php ---ver 5.1.1 ----2026-11-13 ---
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
// -----------------------------------------------------------
//
// 20261113 CDX/NTR SD-599 Extract the existing terminal-file card payment registration.

if (!function_exists('registrer_betaling')) {
	function registrer_betaling($betalings_id, $ordre_id, $betalingstype, $amount, $valuta, $valutakurs, $terminal_ip)
	{
		global $db, $db_skriv_id, $regnaar, $sprog_id;

		$godkendt = NULL;

		if ($betalingstype == '!') {
			$termfile = "../temp/$db/$ordre_id-$betalings_id.txt";

			if (file_exists($termfile)) {
				$tf = fopen($termfile, 'r');
				list($korttype, $betalt) = explode(chr(9), trim(fgets($tf)));
				if ($betalt == $amount)
					$godkendt = 'OK';
			}
			$gf = fopen("../temp/$db/godkendt.txt", "a"); #20180816
			if ($godkendt) {
				$txt = "pos_ordre.php?id=$ordre_id&modtaget=$amount&korttype=$korttype&betaling=$korttype&godkendt=OK";
				fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s") . " $txt\n");
				fclose($gf);
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$txt\">\n";
				exit;
			} else {
				$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$korttyper = explode(chr(9), $r['box5']);
				$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$betalingskort = explode(chr(9), $r['box5']);
				$indbetaling = 0;
				$qtxt = "select konto_id from ordrer where id = '$ordre_id'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if ($r['konto_id']) {
					$qtxt = "select count(id) as tmp from ordrelinjer where ordre_id = '$ordre_id'";
					$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
					if (!$r['tmp'])
						$indbetaling = $amount;
				}
				print "<table align='center'><tbody>";
				fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s"));
				fwrite($gf, " Fejlet betaling: ID $ordre_id  kr. " . dkdecimal($amount, 2) . " \n");
				$txt = "" . findtekst(1114, $sprog_id) . " " . dkdecimal($amount, 2) . ", hvor";
				print "<tr><td>$txt</td></tr>";
				$txt = findtekst(1114, $sprog_id);
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				$txt = findtekst(1113, $sprog_id);
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				$txt = findtekst(1112, $sprog_id);
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				$txt = findtekst(1111, $sprog_id);
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				$txt = findtekst(1109, $sprog_id);
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				$txt = "";
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				$txt = findtekst(1108, $sprog_id);
				#fwrite($gf,"$txt\n");
				print "<tr><td>$txt</td></tr>";
				print "<tr><td><br></td></tr>";
				fclose($gf);
				$txt = findtekst(1108, $sprog_id);
				for ($x = 0; $x < count($korttyper); $x++) {
					if ($betal_type != $korttyper[$x] && $betalingskort[$x]) {
						print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&modtaget=$amount&korttype=$korttyper[$x]&betaling=$korttyper[$x]&indbetaling=$indbetaling&godkendt=OK' onclick=\"return confirm('$txt')\"><button style=\"height:100px;width:300px;\">$korttyper[$x]</button></a></td></tr>";
					}
				}
				$txt = findtekst(1107, $sprog_id);
				print "<tr><td align='center'><br></td></tr>";
				print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'onclick=\"return confirm('$txt')\"><button style=\"height:100px;width:300px;\">" . findtekst(1106, $sprog_id) . "</button></a></td></tr>";
				print "</tbody></table>";
			}
		}
		$gf = fopen("../temp/$db/godkendt.txt", "a"); #20180816
		if ($godkendt) {
			$txt = "pos_ordre.php?id=$ordre_id&modtaget=$amount&korttype=$korttype&betaling=$korttype&godkendt=OK";
			fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s") . " $txt\n");
			fclose($gf);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=$txt\">\n";
			exit;
		} else {
			$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '1' and fiscal_year = '$regnaar'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$korttyper = explode(chr(9), $r['box5']);
			$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '2' and fiscal_year = '$regnaar'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			$betalingskort = explode(chr(9), $r['box5']);
			$indbetaling = 0;
			$qtxt = "select konto_id from ordrer where id = '$ordre_id'";
			$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
			if ($r['konto_id']) {
				$qtxt = "select count(id) as tmp from ordrelinjer where ordre_id = '$ordre_id'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				if (!$r['tmp'])
					$indbetaling = $amount;
			}
			print "<table align='center'><tbody>";
			fwrite($gf, "\n" . __FILE__ . " " . __LINE__ . " " . date("H:i:s"));
			fwrite($gf, " Fejlet betaling: ID $ordre_id  kr. " . dkdecimal($amount, 2) . " \n");
			$txt = "" . findtekst(1114, $sprog_id) . " " . dkdecimal($amount, 2) . ", hvor";
			print "<tr><td>$txt</td></tr>";
			$txt = findtekst(1114, $sprog_id);
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt = findtekst(1113, $sprog_id);
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt = findtekst(1112, $sprog_id);
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt = findtekst(1111, $sprog_id);
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt = findtekst(1109, $sprog_id);
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt = "";
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			$txt = findtekst(1108, $sprog_id);
			#fwrite($gf,"$txt\n");
			print "<tr><td>$txt</td></tr>";
			print "<tr><td><br></td></tr>";
			fclose($gf);
			$txt = findtekst(1108, $sprog_id);
			for ($x = 0; $x < count($korttyper); $x++) {
				if ($betal_type != $korttyper[$x] && $betalingskort[$x]) {
					print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&modtaget=$amount&korttype=$korttyper[$x]&betaling=$korttyper[$x]&indbetaling=$indbetaling&godkendt=OK' onclick=\"return confirm('$txt')\"><button style=\"height:100px;width:300px;\">$korttyper[$x]</button></a></td></tr>";
				}
			}
			$txt = findtekst(1107, $sprog_id);
			print "<tr><td align='center'><br></td></tr>";
			print "<tr><td align='center'><a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'onclick=\"return confirm('$txt')\"><button style=\"height:100px;width:300px;\">" . findtekst(1106, $sprog_id) . "</button></a></td></tr>";
			/*
									print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
									print "<tr><td align='center'>B<a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'><button style=\"width:250px;\">".findtekst(1105,$sprog_id)."</button></a></td></tr>";
									print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
									print "<tr><td align='center'>C<a href='pos_ordre.php?id=$ordre_id&godkendt=afvist'><button style=\"width:250px;\">".findtekst(1104,$sprog_id)."</button></a></td></tr>";
									if ($_SERVER['HTTPS']) $url='https://';
									else $url='http://';
									$url.=$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'];
									if (isset($_COOKIE['salditerm']) && $_COOKIE['salditerm']) $terminal_ip=$_COOKIE['salditerm'];
									if ($terminal_ip=='box' || $terminal_ip=='saldibox') {
										$filnavn="http://saldi.dk/kasse/".$_SERVER['REMOTE_ADDR'].".ip";
										if ($fp=fopen($filnavn,'r')) {
											$terminal_ip=trim(fgets($fp));
											fclose ($fp);
										}
									} # else $terminal_ip=$printserver;
									print "<tr><td align='center'><hr style=\"width:250px;\"></td></tr>";
									print "<tr><td align='center'>D<a href='http://$terminal_ip/pointd/point.php?url=$url&id=$ordre_id'><button style=\"width:250px;\">".findtekst(1103,$sprog_id)."</button></td></tr>";
						*/
			print "</tbody></table>";
		}
	}
}
