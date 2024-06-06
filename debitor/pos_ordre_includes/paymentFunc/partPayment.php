<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/pos_ordre_includes/exitFunc/exit.php --- lap 4.1.0 --- 2024.3.13---
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
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// LN 20190510 Move function find_bon here
// 20210125 PHR Varouis changes related to voucer.
// 20211203 PHR drawer will now remail closed if no cash is involved
// 20240209 PHR Added indbetaling to vibrant & flatpay
// 20240313 MMK/PHR Vipps / Mobilepay

function delbetal($id, $betaling, $betaling2, $modtaget, $modtaget2, $indbetaling, $godkendt, $kortnavn, $betvaluta, $betvalkurs, $receipt_id = 0)
{
	print "<!-- Function delbetal (start)-->\n";
	global $bruger_id;
	global $charset;
	global $db, $delbetaling;
	global $kasse;
	global $regnaar, $retur;
	global $tracelog;

	#cho __line__." $fokus . $afslut . $betaling . $delbetaling . $modtaget . $sum<br>";
	include('../debitor/pos_ordre_includes/voucherFunc/voucherPay.php');
	voucherPay($id, $betaling, $modtaget);
	if (!isset($_COOKIE['giftcard']) || $_COOKIE['giftcard'] == true) {
		#			$sum = betaling($id, $momssats, $betaling, $betaling2, $modtaget, $modtaget2, $kasse);
	} else {
		setcookie('giftcard', '', time() - 3600);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id\">\n";
		exit(0);
	}
	#cho __line__." $fokus . $afslut . $betaling . $delbetaling . $modtaget . $sum<br>";

	$tmp = array();
	$betalingskort = array();
	$betalingstype = NULL;

	$qtxt = "select box5 from grupper where art = 'POS' and kodenr = '1' order by fiscal_year desc limit 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$korttyper = explode(chr(9), $r['box5']);
	//	$qtxt="select box4 from grupper where art = 'POS' and kodenr = '3' and fiscal_year = '$regnaar'";
//	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$voucher = explode(chr(9), $r['box4']);
	$qtxt = "select box3,box4,box5,box6,box11,box12 from grupper where art = 'POS' and kodenr='2' order by fiscal_year desc limit 1";
	$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
	$x = $kasse - 1;
	$tmp = explode(chr(9), $r['box3']);
	$printserver = trim($tmp[$x]);
	$tmp = explode(chr(9), $r['box4']);
	$terminal_ip = trim($tmp[$x]);
	$betkort = explode(chr(9), $r['box5']);
	$div_kort_kto = trim($r['box6']);

	if ($betaling == 'Betalingskort') {
		$betalingstype = 'Betalingskort';
		for ($x = 0; $x < count($korttyper); $x++) {
			if (strtolower($kortnavn) == strtolower($korttyper[$x])) {
				$betaling = $korttyper[$x];
				$betalingstype = $betkort[$x];
			}
		}
	} elseif (in_array($betaling, $korttyper)) {
		for ($x = 0; $x < count($korttyper); $x++) {
			if (strtolower($betaling) == strtolower($korttyper[$x])) {
				$betalingstype = $betkort[$x];
			}
		}
	}
	if ($modtaget && (($terminal_ip && ($godkendt == 'OK' || !$betalingstype)) || !$terminal_ip)) { #20170109
		setcookie("saldi_bet", $cookietxt, time() - 3600);
		$receipt_id = if_isset($_GET['receipt_id'], 0);
		$qtxt = "select id from pos_betalinger where ordre_id=$id and betalingstype = '!'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$qtxt = "update pos_betalinger set betalingstype='$betaling',amount='$modtaget',valuta='$betvaluta',valutakurs='$betvalkurs',receipt_id='$receipt_id' where id='$r[id]'";
		} else {
			$qtxt = "insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs,receipt_id) values ";
			$qtxt .= "('$id','$betaling','$modtaget','$betvaluta','$betvalkurs','$receipt_id')";
		}
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$godkendt = NULL;
		return ($godkendt);
		exit;
	}
	if ($godkendt != 'OK') { #20131205
		$qtxt = "select box3,box4,box5,box6,box11,box12 from grupper where art = 'POS' and kodenr='2' order by fiscal_year desc limit 1";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		$x = $kasse - 1;
		#		$tmp=explode(chr(9),$r['box3']);
#		$printserver=trim($tmp[$x]);
#		$tmp=explode(chr(9),$r['box4']);
#		$terminal_ip=trim($tmp[$x]);
		$betalingskort = explode(chr(9), $r['box5']); #20170106 Havelåge fjernet da kort ikke blev set som betalingskort
		$div_kort_kto = trim($r['box6']); #20170106 Havelåge fjernet da kort ikke blev set som betalingskort
#		$b_vare_id=$r['box11']*1;

		#		if ($b_vare_id) {
#			$r = db_fetch_array(db_select("select varenr,beskrivelse from varer where id = '$b_vare_id'",__FILE__ . " linje " . __LINE__));
#			$b_varenr=$r['varenr'];
#			$b_beskrivelse=$r['beskrivelse'];
#		}
#		db_modify("insert into ordrelinjer (ordre_id,vare_id,varenr,lev_varenr,beskrivelse,antal,m_rabat,pris,kostpris,momssats,momsfri,posnr,projekt,kdo) values ('$id','$b_vare_id','$b_varenr','$delbetaling','$b_beskrivelse','-1','0','$modtaget','0','0','on','-99','0','1')",__FILE__ . " linje " . __LINE__);
		if ($terminal_ip) { # 20131210  div ændringer i rutine
			$r = db_fetch_array(db_select("select box4,box5 from grupper where art = 'POS' and kodenr = '1' order by fiscal_year desc limit 1", __FILE__ . " linje " . __LINE__));
			$kortantal = (int) $r['box4'];
			$korttyper = explode(chr(9), $r['box5']);
			if ($div_kort_kto) {
				$betalingskort[$kortantal] = 'on';
				$korttyper[$kortantal] = 'Betalingskort';
				$kortantal++;
			}
			if (in_array($betaling, $korttyper) || in_array($betaling2, $korttyper)) {
				$amount = 0;
				for ($x = 0; $x < $kortantal; $x++) {
					if ($betaling == $korttyper[$x] && $betalingskort[$x] && !$amount)
						$amount = $modtaget;

					#					} elseif ($betaling==$korttyper[$x] && $betalingskort[$x] && $amount) return ("Der kan ikke betales med 2 betalingskort");
#					if ($betaling2==$korttyper[$x] && $betalingskort[$x] && !$amount) $amount=$modtaget2;
#					elseif ($betaling2==$korttyper[$x] && $betalingskort[$x] && $amount) return ("Der kan ikke betales med 2 betalingskort");
				}
			}
			if(!$amount && strtolower($betaling) == "mobilepay"){
					$qtxt = "SELECT var_value FROM settings WHERE var_name = 'client_id' AND var_grp = 'mobilepay'";
					$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
					if(db_num_rows($q) >= 1) {
						$amount = $modtaget;
						$payment = 'mobilepay';
					}
				}
			}
			if ($amount) {
				if (!$printserver)
					$printserver = 'localhost';
				$belob = dkdecimal($amount, 2);
				$belob = str_replace(".", "", $belob);
				if ($_SERVER['HTTPS'])
					$server = 'https://';
				else
					$server = 'http://';
				$server .= $_SERVER['SERVER_NAME'];
				$serverfile = $_SERVER['PHP_SELF'];
				$url = $server . $serverfile;
				if ($_COOKIE['salditerm'])
					$terminal_ip = $_COOKIE['salditerm'];
				if ($terminal_ip == 'box' || $terminal_ip == 'saldibox') {
					$filnavn = "http://saldi.dk/kasse/" . $_SERVER['REMOTE_ADDR'] . ".ip";
					if ($fp = fopen($filnavn, 'r')) {
						$terminal_ip = trim(fgets($fp));
						fclose($fp);
					}
				} # else $terminal_ip=$printserver;
				if ($terminal_ip == 'box') {
					echo "hmm - termnalserver ikke fundet";
					exit;
				}
				$tidspkt = date("U");
				$qtxt = "insert into pos_betalinger(ordre_id,betalingstype,amount,valuta,valutakurs, receipt_id) values ";
				$qtxt .= "('$id','!','$modtaget','$betvaluta','$betvalkurs','$receipt_id')";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
				$qtxt = "select max(id) as pos_bet_id from pos_betalinger where ordre_id='$id' and betalingstype='!'";
				$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
				$pos_bet_id = $r['pos_bet_id'];

				if ($payment == "mobilepay") {
					$tmp="payments/mobilepay.php?amount=$belob&id=$id&indbetaling=$indbetaling";
					setcookie("saldi_bet",$tmp,time()+60*60*24*7);
					print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
					exit;
				} else {
				# Check for flatpay or ip term
					$qtxt = "SELECT var_value FROM settings WHERE var_name='terminal_type' AND pos_id=$kasse";
					$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

					if ($r[0] == "Flatpay") {
						$tmp="payments/flatpay.php?amount=$belob&id=$id&indbetaling=$indbetaling";
						setcookie("saldi_bet",$tmp,time()+60*60*24*7);
						print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
						exit;
					} else if ("Vibrant:" == substr($r[0], 0, 8)) {
						$tmp="payments/vibrant.php?amount=$belob&id=$id&indbetaling=$indbetaling";
						setcookie("saldi_bet",$tmp,time()+60*60*24*7);
						print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
						exit;
					}
				}



				$tmp = "http://$terminal_ip/pointd/kvittering.php?url=$url&id=$id&server=$server&serverfile=$serverfile&";
				$tmp .= "kommando=kortbetaling&pos_bet_id=$pos_bet_id&db=$db&belob=$belob&betaling=$betaling&betaling2=$betaling2&";
				$tmp .= "modtaget=$modtaget&modtaget2=$modtaget2&indbetaling=$indbetaling&tidspkt=$tidspkt";
				setcookie("saldi_bet", $tmp, time() + 60 * 60 * 24 * 7);
				if ($tracelog)
					fwrite($tracelog, __FILE__ . " " . __LINE__ . " Calls $terminal_ip/pointd/kvittering.php\n");
				print "<meta http-equiv=\"refresh\" content=\"0;URL=$tmp\">\n";
				exit;
			}
	} elseif ($kortnavn) { #20140129
#cho "$kortnavn Amount $amount<br>\n";
#xit;
#cho "select box3,box4,box5,box6,box11 from grupper where art = 'POS' and kodenr='2'<br>\n";
		$r = db_fetch_array(db_select("select box3,box4,box5,box6,box11 from grupper where art = 'POS' and kodenr='2' order by fiscal_year desc limit 1 ", __FILE__ . " linje " . __LINE__));
		$x = $kasse - 1;
		$tmp = explode(chr(9), $r['box3']);
		$printserver = trim($tmp[$x]);
		$tmp = explode(chr(9), $r['box4']);
		$terminal_ip = trim($tmp[$x]);
		$betalingskort = explode(chr(9), $r['box5']);
		$div_kort_kto = trim($r['box6']);
		$b_vare_id = (int) $r['box11'];
		if ($terminal_ip && $div_kort_kto) {
			$r = db_fetch_array(db_select("select box4,box5 from grupper where art = 'POS' and kodenr = '1' order by fiscal_year desc limit 1", __FILE__ . " linje " . __LINE__));
			$kortantal = (int) $r['box4'];
			$korttyper = explode(chr(9), $r['box5']);
			$lkt = explode(chr(9), strtolower($r['box5']));
			$lk = strtolower($kortnavn);
			if (in_array($lk, $lkt)) {
				for ($x = 0; $x < $kortantal; $x++) {
					if ($lk == $lkt[$x] && $betaling == 'Betalingskort')
						$betaling = $korttyper[$x];
					if ($lk == $lkt[$x] && $betaling2 == 'Betalingskort')
						$betaling2 = $korttyper[$x];
				}
			} elseif ($betaling == 'Betalingskort')
				$betaling .= "|" . $kortnavn;
			elseif ($betaling2 == 'Betalingskort')
				$betaling2 = "|" . $kortnavn;
		}
		$r = db_fetch_array(db_select("select max(posnr) as posnr from ordrelinjer where ordre_id = '$id'", __FILE__ . " linje " . __LINE__));
		if ($r['posnr'] > 0)
			$ny_pos = $r['posnr'] + 1;
		else
			$ny_pos = 1;
		$r = db_fetch_array(db_select("* from pos_betalinger where ordre_id = '$id'", __FILE__ . " linje " . __LINE__));
		$r = db_fetch_array(db_select("select id,lev_varenr,beskrivelse from ordrelinjer where ordre_id = '$id' and posnr='-99' and vare_id='$b_vare_id'", __FILE__ . " linje " . __LINE__));
		$delbetaling = $r['lev_varenr'] - 1;
		$ny_beskrivelse = $r['ny_beskrivelse'];
		$ny_id = $r['id'];

		if ($kortnavn)
			$ny_beskrivelse .= " " . $kortnavn;
		db_modify("update ordrelinjer set beskrivelse='$ny_beskrivelse',posnr='$ny_pos' where id = '$ny_id'", __FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=pos_ordre.php?id=$id&delbetaling=$delbetaling\">\n";
	}
	print "<!-- Function delbetal (slut)-->\n";
} # endfunc delbetal



?>

