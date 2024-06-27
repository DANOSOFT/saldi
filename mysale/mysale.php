<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/mysale.php -----patch 4.0.8 ----2023-09-18--------------
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
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20200617 PHR Design issues
// 20200702 PHR Looking in 'labels' for 'minpris'
// 20200924 MSC Datepicker
// 20200930 PHR Addad 'delete from online' 
// 20201001 MSC Datepicker ver 2
// 20201223 PHR Added sort option 
// 20210503 PHR	Added password option.
// 20210505 PHR Password cookie set if referer is develop/debitor/debitor.php
// 20210829 PHR	varoius minor changes
// 20210901 LOE to manage users via IPs
// 20210906 PHR	changes MyTmpPass.dat to .ht_MyTmpPass to avoid display in browser
// 20220212 PHR Updated password handling and replaced cookies with session vars
// 20220530 PHR resetPW was newer hit and no mail sent. Added '&& !$resetPW' to if ($account)  
// 20230311 PHR Various updates according to PHP8 
// 20230918 PHR Check if db exists 

@session_start();
$s_id=session_id();

$accountId=$f=$t=$tmpcode=$s=NULL;
if (!isset($_SESSION['mySalePw'])) $_SESSION['mySalePw'] = NULL;
if (!isset($_SESSION['mySaleAcId'])) $_SESSION['mySaleAcId'] = NULL;

$quickpay = 1;

print "<html>";
print "	<head><title>Mit Salg</title><meta http-equiv='content-type' content='text/html; charset=UTF-8;'>
	<meta http-equiv='content-language' content='da'>
</head><body>";

$menu = 'mySale';
if(isMobileDevice()) {
	$mobile=1;
	$css='../css/mysale_m.css';
} else {
	$mobile=0;
	$css='../css/mysale.css';
}
$medlem = $sort = $tilsalg = $tmp = '';

$showMySale = 1;
(isset($_GET['id']))?         $id=$_GET['id']:                  $id=NULL;
(isset($_GET['condition']))?  $condition=$_GET['condition']:    $condition=NULL;
(isset($_GET['newSort']))?    $newSort=$_GET['newSort']:        $newSort=NULL;
(isset($_GET['sort']))?       $sort=$_GET['sort']:              $sort=NULL;
(isset($_GET['editProfile']))?$editProfile=$_GET['editProfile']:$editProfile=NULL;
(isset($_GET['email']))?      $email=$_GET['email']:            $email=NULL;
(isset($_GET['tmpcode']))?    $tmpcode=$_GET['tmpcode']:        $tmpcode=NULL;
(isset($_POST['pw']))?        $pw=$_POST['pw']:                 $pw=NULL;
(isset($_POST['resetPW']))?   $resetPW=$_POST['resetPW']:       $resetPW=NULL;

#echo $_SERVER['REMOTE_ADDR'];
if ($tmpcode) {
	$tmp='';
	for ($x=0;$x<strlen($tmpcode);$x=$x+2) {
		$tmp.=chr(hexdec(substr($tmpcode,$x,2)));
	}
	list($email,$tmppw)=explode('|',$tmp);
	if ($email && $tmppw) {
echo "$email && $tmppw<br>";
		$found = 0;
		$fp = fopen("../temp/.ht_MyTmpPass","r"); 
		while ($tmp = fgets($fp)) {	
#cho "$tmp<br>";
			list ($a,$b,$c,$d,$e) = explode (chr(9),$tmp);
#cho "$a<br>$b<br>$c<br>$d<br>";

			if ($a == $email && $b == $tmppw) {
				$found = 1;
#cho "Found $found<br>";
				if ($e <= date('U')+60*60) $found=2;
#cho "Found $found<br>";
			}
		}
		if ($found == 1) alert('Midertidig kode udløbet');
		elseif ($found == 2) {	
			$_SESSION['mySalePw'] = $s_id;
			include ('../includes/connect.php');
			$qtxt = "select deb_id,link from mysale where email = '$email' and db = '$c'";
echo "$qtxt<br>";
			if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
				$id = $r['link'];
				$_SESSION['mySaleAcId'] = $r['deb_id'];
				print "<meta http-equiv=\"refresh\" content=\"0;URL='mysale.php?id=$id&condition=$condition&editProfile=1'\">";
				exit;
			}
		}
	} else $_SESSION['mySaleAcId'] = $_SESSION['mySalePw'] = NULL;
}

if ($id) {
$link = $id;
	$tmp='';
	for ($x=0;$x<strlen($id);$x=$x+2) {
		$tmp.=chr(hexdec(substr($id,$x,2)));
#if ($_SERVER['REMOTE_ADDR'] == '87.62.100.183') echo  substr($id,$x,2) ." | ". chr(hexdec(substr($id,$x,2)))."<br>";
	}
	list($kto,$db,$ssl)=explode('@',$tmp);
	if (strpos($kto,"|")) list($accountId,$account)=explode('|',$kto);
	else $account=$kto;
}
#if ($_SERVER['REMOTE_ADDR'] == '87.62.100.183') echo "db $db<br>";
if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'],'debitor/debitor.php')) { # 20210505
	$_SESSION['mySalePw'] = $s_id;
	$_SESSION['mySaleAcId'] = $accountId;
}

if (file_exists('redirect.php')) include ('redirect.php');
if ($id && !is_numeric($account)) {
#echo "$id <br> $tmp <br> $account - $kto - $db - $ssl" ;
	file_put_contents("../temp/mysaleError.txt","$id",FILE_APPEND);
	print "<center><br><br><br><br><b>Fejl i ID<br><br>Kontakt butikken for nyt ID</b>";
	exit;
}
if (!$accountId) $accountId = 0;
if (!$account)    $account = 0;
(isset($_POST['dateFrom']))?$from=$_POST['dateFrom']:$from=NULL;
(isset($_POST['dateTo']))?$to=$_POST['dateTo']:$to=NULL;
if (isset($_POST['condition'])) $condition=$_POST['condition'];

$accountId = (int)$accountId;
$totalPrice=$qty=$yourTotalPrice=0;
#cho __line__." connect<br>";	
include ('../includes/connect.php');
include ('../includes/std_func.php');
// revomoves leftover.
$logtime = date('U');
$tmp = $logtime - 10;
$qtxt = "select id from regnskab where db = '$db'"; #20230918
if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
	print "<center><br><br><br><br><b>Konto ikke fundet<br><br>Kontakt butikken.</b>";
	exit;
}
$qtxt = "delete from online where rettigheder = '0' and regnskabsaar = '0' and logtime < '". $logtime ."'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
if ($account && !$resetPW) {
	setcookie("mylabel","$account|$db",time()-60,"/");
	$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='online' and column_name='sprog'";
	if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		db_modify("ALTER table online ADD column sprog varchar(10)",__FILE__ . " linje " . __LINE__);
	}
	$qtxt = "insert into online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,sprog)";
	$qtxt.= " values ";
	$qtxt.= "('$s_id','". db_escape_string($account) ."','". db_escape_string($db) ."','". db_escape_string($squser) ."',";
	$qtxt.= "'0',0,'". date('U') ."',FALSE,'1')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	include ('../includes/online.php');

	$qtxt = "SELECT column_name FROM information_schema.columns WHERE table_name='ordrelinjer' and ";
	$qtxt.= "column_name = 'barcode'";
	if (!db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "ALTER TABLE ordrelinjer ADD COLUMN barcode varchar(20)";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}

	$lineColor = $bgcolor5;
	if(!isset($_SESSION['linkLog']) && $_SESSION['mySaleAcId'] != $accountId){
		$_SESSION['linkLog'] = 1;
		}
	($id && ($_SESSION['mySalePw'] != $s_id || $_SESSION['mySaleAcId'] != $accountId))?$askPW=1:$askPW=0;
	if ($askPW) {
		$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name='password'";
		if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			db_modify("ALTER table adresser ADD column password varchar(50)",__FILE__ . " linje " . __LINE__);
		}
		$qtxt = "select id,email,password from adresser where id = '$accountId'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$accountId = $r['id'];
			$email     = $r['email'];
			$acPw      = $r['password'];
			if ($accountId && $db && $email && $link) {
				include ('../includes/connect.php');
				$qtxt = "select * from mysale where deb_id = '$accountId' and db ='$db'";
				if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					if ($email != $r['email'] || $link != $r['link']) {
						$qtxt = "update mysale set email = '". db_escape_string($email) ."', link = '". db_escape_string($link) ."' where id = '$r[id]'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				} else {
					$qtxt = "insert into mysale (deb_id,db,email,link) values ";
					$qtxt.= "('$accountId','$db','". db_escape_string($email) ."','". db_escape_string($link) ."')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			include ('../includes/online.php');
			}
			if ($acPw) {
				if ($pw && saldikrypt($accountId,$pw) == $acPw) {
				$_SESSION['mySalePw'] = $s_id;
				$_SESSION['mySaleAcId'] = $accountId;
					print "<meta http-equiv=\"refresh\" content=\"0;URL='mysale.php?id=$id'\">";
				} else {
					$askPW = 1;
					$_SESSION['mySaleAcId'] = $_SESSION['mySalePw'] = NULL;
				}
			} else {
				$askPW = 0;
				$_SESSION['mySalePw'] = $s_id;
				$_SESSION['mySaleAcId'] = $accountId;
			}
			if ($askPW) {
				askPW($accountId,$email,$mobile);
				$editProfile = $showMySale = NULL;
			}
		}
	}
} else {
	if ($_POST['email'] && $_POST['pw']) {
		include ('../includes/std_func.php');
		$email = strtolower($_POST['email']);
		$pw = $_POST['pw'];
			if ($resetPW) {
				$found=$x=0;
				if ($db) $databases[$x]=$db;
				else {
					$qtxt = "select db from mysale where lower (email) = '". db_escape_string($email) ."'";
#					$qtxt.= " && account_no = '". db_escape_string($pw) ."'";
					$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r=db_fetch_array($q)) {
						$databases[$x]=$r['db'];
						$x++;
					}
				}
				$found=$x=0;
				while ($found == '0' && $x<count($databases)) {
					$qtxt = "insert into online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,sprog)";
					$qtxt.= " values ";
					$qtxt.= "('$s_id','". db_escape_string($email) ."','". db_escape_string($databases[$x]) ."','". db_escape_string($squser) ."',";
					$qtxt.= "'0',0,'". date('U') ."',FALSE,'1')";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					include ('../includes/online.php');
					$qtxt = "select id,kontonr,firmanavn from adresser where lower(email) = '$email' and kontonr = '". $_POST['pw'] ."'";
					if ($r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						$found=1;
						$data = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcefghijklmnopqrstuvwxyz';
						$tmppw = substr(str_shuffle($data), 0, 8);
						$fp=fopen("../temp/.ht_MyTmpPass","a"); 
						fwrite($fp, $email.chr(9).$tmppw.chr(9).$databases[$x].chr(9).$r2['kontonr'].chr(9).date('U')."\n");
						fclose($fp);
						$hexcode = '';
						$txt = $email.'|'.$tmppw;
						for ($x=0;$x<strlen($txt);$x++) $hexcode.= dechex(ord(substr($txt,$x,1)));
						ini_set("include_path", ".:../phpmailer");
						require("class.phpmailer.php");
						$subj = 'Glemt adgangskode til Mit Salg';
						$message = "Kære $r2[firmanavn],\n\n";
						$message.= "Nogen, måske dig?, har bestilt et midlertidigt login for konto $r2[kontonr] til 'Mit Salg'\n";
						$message.= "er det ikke dig, kan du ignorere denne mail, ellers kan du få adgang på nedenstående link\n\n";
						$message.= "https://".$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF']."?tmpcode=$hexcode\n\n";
						$message.= "Denner mail kan ikke besvares\n";
						$mail = new PHPMailer();
						$mail->IsSMTP();                                   // send via SMTP
						$mail->Host  = "localhost"; // SMTP servers
						$mail->SMTPAuth = false;     // turn on SMTP authentication
						$afsendermail='mysale@'.$_SERVER['SERVER_NAME'];
						$afsendernavn='Mit Salg';
						$mail->From  = $afsendermail;
						$mail->FromName = $afsendernavn;
						$mail->AddAddress($email);
						$mail->CharSet = 'UTF-8';
						$mail->Subject  =  "Login til 'Mit Salg'";
						$mail->Body     =  $message;
						if ($mail->Send()) alert("Mail sent to $email, kundenr. $r2[kontonr]");
						else alert("Fejl i afsendelse til $email, kundenr. $r2[kontonr]");
					}
#cho __line__." connect<br>";	
					include ('../includes/connect.php');
					$qtxt = "delete from online where session_id = '$s_id' and brugernavn = '". db_escape_string($email) ."' and db = '$databases[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$x++;
				}
				if ($found == 0) alert("$email matcher ikke konto $_POST[pw]");
				print "<meta http-equiv=\"refresh\" content=\"0;URL='mysale.php?id=$id&email=$email'\">";
				exit;
			} else {
#cho __line__." CSS $css<br>";
				$x=0;
				$qtxt = "select db from mysale where lower (email) = '". db_escape_string($email) ."'";
#cho __line__." $qtxt<br>";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				while ($r=db_fetch_array($q)) {
					$databases[$x]=$r['db'];
#cho __line__." $databases[$x]<br>";
					$x++;
				}
				$found = $x = '0';
				while ($found == '0' && $x<count($databases)) {
					$qtxt = "insert into online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor,sprog)";
					$qtxt.= " values ";
					$qtxt.= "('$s_id','". db_escape_string($email) ."','". db_escape_string($databases[$x]) ."','". db_escape_string($squser) ."',";
					$qtxt.= "'0',0,'". date('U') ."',FALSE,'1')";
#cho __line__." $qtxt<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#cho __line__." CSS $css<br>";
#cho __line__." online<br>";	
					include ('../includes/online.php');
#cho __line__." CSS $css<br>";
					$found=0;
					$qtxt="SELECT column_name FROM information_schema.columns WHERE table_name='adresser' and column_name='password'";
#cho __line__." $qtxt<br>";
					if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
						db_modify("ALTER table adresser ADD column password varchar(50)",__FILE__ . " linje " . __LINE__);
					}
					$qtxt = "select id,kontonr,password from adresser where lower(email) = '". db_escape_string($email) ."'";
#cho __line__." $db $qtxt<br>";
					$q2 = db_select($qtxt,__FILE__ . " linje " . __LINE__);
					while ($r2=db_fetch_array($q2)) {
#cho __line__." $db $qtxt<br>";
					$encPw = saldikrypt($r2['id'],$pw);
#cho "$r2[id] -> $r2[password] == $encPw<br>";					
					if ($r2['password'] == $encPw) {
							$accountId = $r2['id'];
							$account   = $r2['kontonr'];
							$found     = $databases[$x];
						}
					}
#cho __line__." CSS $css<br>";
					
#cho __line__." $db $found<br>";
#cho __line__." connect<br>";	
					include ('../includes/connect.php');
					if ($found) {
						$qtxt = "select link from mysale where deb_id = '$accountId' and email = '$email' and db='$found'";
#						setcookie("mySalePw", $s_id,0,"/");
						$_SESSION['mySalePw'] = $s_id;
						$_SESSION['mySaleAcId'] = $accountId;
						$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
						if ($r=db_fetch_array($q)) {
							$link = $r['link'];
							list ($tmp,$id) = explode ('?id=',$link);
							for ($z=0;$z<strlen($id);$z=$z+2) $tmp.=chr(hexdec(substr($id,$z,2)));
							list($kto,$db,$ssl)=explode('@',$tmp);
							if (strpos($kto,"|")) list($a,$b)=explode('|',$kto);
							if ($accountId == $a) {
	#							include ('../includes/connect.php');
	#							$qtxt = "delete from online where session_id='$s_id' and brugernavn='$account' and db='$db'";
	#							db_modify($qtxt,__FILE__ . " linje " . __LINE__);

#								print "<meta http-equiv=\"refresh\" content=\"0;URL='mysale.php?id=$id&email=$email'\">";
								exit;
							}
#cho "CSS $css<br>";	
							if (!$css) {
								if(isMobileDevice()) {
									$mobile=1;
									$css='../css/mysale_m.css';
								} else {
									$mobile=0;
									$css='../css/mysale.css';
								}
							}
#							include ('../includes/online.php');
						}
					} else {
						$qtxt = "delete from online where session_id='$s_id' and brugernavn='". db_escape_string($email) ."' and db='$databases[$x]'";
#cho __line__." $qtxt<br>";	
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
#cho __line__." online<br>";	
					if ($found) include ('../includes/online.php');
					$x++;
				} #else alert('Email ikke genkendt');
			} 
#cho __line__." $db $found<br>";
		} else {
			print "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">\n";
			print "<center>Velkommen til 'mit salg'</center>";
			askPW($accountId,$email,$mobile);
			exit;	
		}
		if (!$found) {
			alert('Forkert adgangskode');
			print "<meta http-equiv=\"refresh\" content=\"0;URL='mysale.php?id=$id&email=$email'\">";
			$editProfile = $showMySale = NULL;
#		} else {
#			$_SESSION['mySalePw'] = $s_id;
#			$_SESSION['mySaleAcId'] = $accountId;
		}
		
}
 	#test
    #test2
    #test3
 	
#cho __line__." CSS $css<br>";
	
#cho __line__." $db $found<br>";
#xit;
if ($editProfile) {
	include("editProfile.php");
#	if ($quickpay) include("quickpay.php");
}
elseif ($showMySale) include("showMySale.php");
print "</body></html>";
#cho __line__." connect<br>";	
include ('../includes/connect.php');
$qtxt = "delete from online where session_id='$s_id' and brugernavn='$account' and db='$db'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
#cho __line__." CSS $css<br>";

function askPW($accountId,$email,$mobile) {
	global $id;
	($mobile)?$width='300px':$width='150px';
	print "<center><table >";
	print "<tr><td colspan = '4' align='center'><form action='mysale.php?id=$id' method='post'>Skriv email og adgangskode.</td></tr>";
	print "<tr><td colspan = '4' align='center'></td></tr>";
	print "<tr><td></td><td style = 'width:$width'>Email</td><td style = 'width:$width'>";
	print "<input  style = 'width:$width' name = 'email' type = 'text' value='$email'><td></td></td></tr>";
	print "<tr><td></td><td>Adgangskode</td>";
	print "<td><input  style = 'width:$width' name = 'pw' type = 'password'></td><td></td></tr>";
	print "<tr><td colspan = '4' align='center'><br></td></tr>";
	if ($mobile) {
	print "<tr><td></td><td colspan = '2'>";
	print "<input style = 'width:". $width * 2 .";height:60px;' name = 'ok' type = 'submit' value = 'OK'></td></td></td></tr>";
	print "<tr><td colspan='4'></td></tr><tr><td></td><td colspan = '2'>";
	print "<input style = 'width:". $width * 2 .";height:60px;' name = 'resetPW' type = 'submit' value = 'Glemt kode'><td></td></td></tr>";
	} else {
	print "<tr><td></td><td><input style = 'width:$width;' name = 'ok' type = 'submit' value = 'OK'></td>";
	print "<td><input style = 'width:$width;' name = 'resetPW' type = 'submit' value = 'Glemt kode'><td></td></td></tr>";
	}
	print "<tr><td colspan = '4' align='center'><br></td></tr>";
	print "<tr><td colspan = '4' align='center'>Har du glemt adgangskoden så skriv din email samt dit</td></tr>";
	print "<tr><td colspan = '4' align='center'>kundenummer som adgangskode og klik på 'Glemt kode'.</td></tr>";
	print "<tr><td colspan = '4' align='center'>Så sender vi et link med en midlertidig adgangskode</td></tr>";
	print "</form></table>";
	$showMySale = NULL;
}

function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}
?>
