<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- index/index.php -----patch 4.1.1 ----2025-04-02--------------
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
// 20140321	Tilføjet link til glemt kode
// 20161104	Div ændringer relateret til bedre sikkerhed
// 20190815 PHR added redirect option for systems moved to another server. Links defined in redirect.php
// 20200205 PHR fixed cookie saving.
// 20200217 PHR Added '!isset($_POST['fejltxt'])' to avoid endless loop if wrong password
// 20201020 PHR Added reading from alert.txt if exists.
// 20210512 LOE This was added previously to allow users read the content before the refresh. Logged today 
// 20220330 LOE Made some modifications here.
// 20220426 DAPE Fixed language select, made language selector dynamic to take new languages whenever they're implemented in tekster.csv
// 20220618 PHR Changed 'language' and 'language_id' to 'languageId'
// 20230726 LOE Minor modification
// 20250313 LOE Sanitized some inputs to mitigate against XSS attack CWE-79
// 20250314 LOE Updated the  document.login.submit with $nonce variable to work witht the enforced CSP 
// 20250402 LOE Applied $nonce to javascript handling languageId form and other clean up
@session_start();

if (!isset($_SESSION['nonce'])) {
    $_SESSION['nonce'] = bin2hex(random_bytes(16));  // Generate a random 16-byte nonce
}

$nonce = $_SESSION['nonce']; 

header("Content-Security-Policy: script-src 'self' 'nonce-$nonce';");

$regnskab=''; $brugernavn=''; $kode=''; $languageId=''; 
$css="../css/login.css?cssver=1";
if(file_exists("../includes/connect.php")){
	if (filesize("../includes/connect.php") < 10) unlink ("../includes/connect.php"); 
}
if (!file_exists("../includes/connect.php")) {
	print "<meta http-equiv=\"refresh\" content=\"2;url=install.php\">\n"; #20210512
	print "</head><body>\n\n";
	print "<p>Du skulle automatisk bliver videresendt til installeringssiden.</p>\n\n";
	print "<p>Skulle dette ikke ske, s&aring; <a href=\"install.php\">KLIK HER</a></p>\n\n";
	print "</body></html>\n";
	exit;
}

#cho $_SERVER['HTTP_USER_AGENT'];
if (!isset($timezone)) $timezone='Europe/Copenhagen';

include("../includes/connect.php");
include("../includes/db_query.php");
#include("../includes/online.php"); #20210929
include("../includes/std_func.php");
$hm=$rs=$bn=null; #20211007
$huskmig = null;
print  "<script nonce='{$nonce}'>
if(window.self !== window.top) {
	// Run this code if in an iframe
	parent.location.href = '../index/index.php';
}
</script>";




	$regnskab = check_and_sanitize_input('regnskab', 'Input for regnskab is too long.', $nonce);
	$brugernavn = check_and_sanitize_input('brugernavn', 'Input for brugernavn is too long.', $nonce);
	$languageId = check_and_sanitize_input('languageId', 'Input for languageId is too long.', $nonce);
	$vent = check_and_sanitize_input('vent', 'Input for vent is too long.', $nonce);
	$password = check_and_sanitize_input('password', 'Input for password is too long.', $nonce);
	$fejltxt = check_and_sanitize_input('fejltxt', 'Input for fejltxt is too long.', $nonce);

if(isset($_POST['languageId'])){
	$languageId = $_POST['languageId'];
} elseif(isset($_COOKIE['languageId'])){
	$languageId = $_COOKIE['languageId'];
}
if ($languageId) setcookie('languageId',$languageId, time() + (10 * 365 * 24 * 60 * 60) );

 if(isset($_COOKIE['saldi_huskmig'])) list($hm,$rs,$bn)=explode(chr(9),$_COOKIE['saldi_huskmig']); #20211007



if (!isset($fejltxt) && isset($regnskab) && isset($brugernavn) && isset($password) && isset($_POST['languageId'])) {
	
	if (file_exists("redirect.php")) include ("redirect.php"); 
	else $action="login.php";
	if(isset($_POST['huskmig'])){ #20211007
	$cookievalue = $huskmig . chr(9) . $_POST['regnskab'] . chr(9) . $_POST['brugernavn'];
	}
	if (isset($_POST['huskmig'])){ setcookie ('saldi_huskmig', $cookievalue, time() + (86400 * 30));} #20211007
	#elseif ($rs == $_POST['regnskab'] && $bn == $_POST['brugernavn']) {
elseif ($rs == $regnskab && $bn == $brugernavn) {	
		setcookie ('saldi_huskmig', $cookievalue, time() - 3600);
	}
	print "<form name=\"login\" METHOD=\"POST\" ACTION=\"$action\" onSubmit=\"return handleLogin(this);\">\n";
	// print "<input type=\"hidden\" name=\"regnskab\" value=\"$_POST[regnskab]\">\n";
	// print "<input type=\"hidden\" name=\"brugernavn\" value=\"$_POST[brugernavn]\">\n";
	// print "<input type=\"hidden\" name=\"password\"  value=\"$_POST[password]\">\n";
	print "<input type=\"hidden\" name=\"regnskab\" value=\"$regnskab\">\n";
	print "<input type=\"hidden\" name=\"brugernavn\" value=\"$brugernavn\">\n";
	print "<input type=\"hidden\" name=\"password\"  value=\"$password\">\n";
	if(isset($_COOKIE['languageId'])){
	print "<input type=\"hidden\" name=\"languageId\"  value=\"$_COOKIE[languageId]\">\n"; #20220330
	}
	//print "<input type=\"hidden\" name=\"vent\"  value=\"$_POST[vent]\">\n";
	print "<input type=\"hidden\" name=\"vent\"  value=\"$vent\">\n";
	// print "<body onload=\"document.login.submit()\">";
	print "<body>";
	print "<script nonce=\"$nonce\">\n";
		print "window.onload = function() {\n"; 
		print "    document.login.submit();\n";
		print "};\n";
	print "</script>\n";
	print "</form>";
	exit;
}


if (isset ($_GET['navn'])) $brugernavn = html_entity_decode(stripslashes($_GET['navn']),ENT_COMPAT,$charset);
if (isset ($_GET['regnskab'])) $regnskab = html_entity_decode(stripslashes($_GET['regnskab']),ENT_COMPAT,$charset);
if (isset ($_GET['tlf'])) $kode = stripslashes($_GET['tlf']);
// $fejltxt = if_isset($_POST['fejltxt']);
// $vent = if_isset($_POST['vent']);
$fejltxt = check_and_sanitize_input('fejltxt', 'Input for fejltxt is too long.', $nonce);
$vent = check_and_sanitize_input('vent', 'Input for vent is too long.', $nonce);
if (!$regnskab && !$brugernavn) {
	// if (isset($_POST['regnskab'])) $regnskab = $_POST['regnskab'];
	// if (isset($_POST['brugernavn'])) $brugernavn = $_POST['brugernavn'];
	$regnskab = check_and_sanitize_input('regnskab', 'Input for regnskab is too long.', $nonce);
	$brugernavn = check_and_sanitize_input('brugernavn', 'Input for brugernavn is too long.', $nonce);
	if (isset($_COOKIE['saldi_huskmig'])) {
		if ($hm) $huskmig="checked='checked'";
		else $huskmig=''; 
		if (!$regnskab) $regnskab = $rs;
		if (!$brugernavn) $brugernavn = $bn;
	} else $huskmig='';
}

if (isset($brug_timestamp)) {
 	?>
	<script language="javascript" type="text/javascript" src="../javascript/md5.js" nonce="<?php echo $nonce; ?>"></script>

	<script language="javascript" type="text/javascript" nonce="<?php echo $nonce; ?>">
		function handleLogin (loginForm) {
			var inputTimestamp = loginForm.timestamp.value;
			var inputPassword = loginForm.password.value;

			loginForm.password.value = hex_md5(inputTimestamp+hex_md5(inputPassword));
			return true;
		}
		
	
	</script>
	<?php
}

if ($db_encode=="UTF8") $charset="UTF-8";
else $charset="ISO-8859-1";
if (file_exists("../doc/vejledning.pdf")) $vejledning="../doc/vejledning.pdf";
else $vejledning="http://saldi.dk/dok/komigang.html";
PRINT "<!DOCTYPE html PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">\n";
print "<html>\n";
print "<head>\n";
print "<title>$title</title>\n";
if ($css) PRINT "<link rel=\"stylesheet\" type=\"text/css\" href=\"$css\">\n";
// print "<!--[if lt IE 9]>
// 		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
// 		<![endif]-->\n"; // Deprecated and non existent
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
if (strpos($_SERVER['PHP_SELF'],"beta")) $host="!!! BETA !!!";
elseif (!file_exists("../sager/sager.php")) $host="SALDI";
if (file_exists("bg.php")) include ("bg.php");
else $style=''; 
print "<body>\n";
print "	<div id=\"main\">\n";    				
print "		<div class=\"loginHolder\">\n";
print "			<div class=\"loginBox\">\n";
print "				<div class=\"loginForm\">\n";    
print "				<a href='https://saldi.dk'><img class=\"logoimg\" src='../img/Saldi_Main_Logo.png' width='100px'></a>\n";    
print "				<form method=\"POST\" action=\"index.php\">\n";
print "				<div class='loginAction'>\n";    
print "					<h2>Login</h2>\n";    
print "					<select id=\"languageId\" name=\"languageId\" onchange=\"this.form.submit();\" >\n";

print "<script language=\"javascript\" type=\"text/javascript\" nonce=\"$nonce\">\n";
print "    document.getElementById('languageId').onchange = function() {\n";
print "        this.form.submit();\n";
print "    };\n";
print "</script>\n";
$fp = fopen("../importfiler/tekster.csv","r");
if ($linje=trim(fgets($fp))) {
$a = explode("\t",$linje);
}
fclose($fp);

if (!is_numeric($languageId)) $languageId = 1;
for ($x=1; $x<count($a); $x++){
if ($x == $languageId){
print "<option selected value=\"$x\">". findtekst(1,$x) ."</option>\n";
}
else {
print "<option value=\"$x\">". findtekst(1,$x) ."</option>\n";
}
}
print "</select>\n";
print "				</div>\n";    
print "</form>\n";

print "					<form name=\"login1\" METHOD=\"POST\" ACTION=\"index.php\" onSubmit=\"return handleLogin(this);\">\n";
print "						<input type=\"hidden\" name=\"vent\" value=\"$vent\">\n";
if (!$fejltxt && file_exists('alert.txt')) $fejltxt=file_get_contents('alert.txt'); 
if ($fejltxt) {
	print "<label style=\"text-align:center;color:red;\">$fejltxt</label>\n";
	print "<label><br></label>\n";
	print "<label><hr></label>\n";
	print "<label><br></label>\n";
}

print " <input type=\"hidden\" value=\"$languageId\" name=\"languageId\" >";
print "						<label for=\"Regnskab\">". findtekst(115,$languageId) ."</label>\n";
print "						<input class=\"textinput\" type=\"text\" id=\"regnskab\" name=\"regnskab\" value=\"$regnskab\" tabindex=\"1\">\n";
print "						<label for=\"login\">". findtekst(225,$languageId) ."</label>\n";
print "						<input class=\"textinput\" type=\"text\" id=\"login\" name=\"brugernavn\" value=\"$brugernavn\" tabindex=\"2\">\n";
print "						<label for=\"password\">". findtekst(324,$languageId) ."</label>\n";
print "						<input class=\"textinput\" type=\"password\" id=\"password\" name=\"password\"  value=\"$kode\" tabindex=\"3\">\n";
print "						<div class=\"loginAction\">\n";
print "							<div class=\"flleft\">\n";
print "								<label for=\"husk_mig\">\n";
print "								<input type=\"checkbox\" id=\"husk_mig\" name=\"huskmig\" $huskmig tabindex=\"4\">\n";
print "								". findtekst(2006,$languageId) ."</label>\n";
print "								<a class=\"forgotpass\" href=\"glemt_kode.php\" tabindex=\"5\">". findtekst(2007,$languageId) ."</a>\n";
print "							</div><!-- end of flleft -->\n";
print "							<input class=\"button blue flright\" type=\"submit\" value=\"Login\" alt=\"Login\" title=\"Login\" tabindex=\"6\">\n";
# print "							<div class=\"clearfix\"></div>\n"; #25042024
print "						</div><!-- end of loginAction -->\n";
if (strtolower($sqdb)=='rotary') {
	print "<label style=\"text-align:center;font-size:12px;\">".findtekst(325,$sprog_id)."</label>\n";
}

print "					</form>\n";
print "				</div><!-- end of loginForm -->\n";
print "	<div id=\"footer\"><p>Copyright&nbsp;&copy;&nbsp; - $copyright</p></div>\n";
print "			</div><!-- end of loginBox -->\n";
print	"		</div><!-- end of loginHolder -->\n";
print "	</div><!-- end of main -->\n";
include ("../includes/version.php");

if (!isset($_COOKIE['saldi_std'])) {
	print "<script language=\"javascript\" type=\"text/javascript\" nonce=\"$nonce\">\n";
	print "document.login.regnskab.focus();\n";
	print "</script>\n";
} else {
	print "<script language=\"javascript\" type=\"text/javascript\" nonce=\"$nonce\">\n";
	print "document.login.login.focus();\n";
	print "</script>\n";
}
?>
</body></html>
