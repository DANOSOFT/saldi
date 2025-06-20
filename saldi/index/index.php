<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -----------index/index.php-----------lap 3.8.9------2020-02-17---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2020 saldi.dk aps
// ----------------------------------------------------------------------
// 20140321	Tilføjet link til glemt kode
// 20161104	Div ændringer relateret til bedre sikkerhed
// 20190815 PHR added redirect option for systems moved to another server. Links defined in redirect.php
// 20200205 PHR fixed cookie saving.
// 20200217 PHR Added '!isset($_POST['fejltxt'])' to avoid endless loop if wrong password

$regnskab=''; $brugernavn=''; $kode=''; 
$css="../css/login.css";

if (filesize("../includes/connect.php") < 10) unlink ("../includes/connect.php"); 
if (!file_exists("../includes/connect.php")) {
	print "<meta http-equiv=\"refresh\" content=\"0;url=install.php\">\n";
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
include("../includes/std_func.php");

list($hm,$rs,$bn)=explode(chr(9),$_COOKIE['saldi_huskmig']);
if (!isset($_POST['fejltxt']) && isset($_POST['regnskab']) && isset($_POST['brugernavn']) && isset($_POST['password'])) {
	if (file_exists("redirect.php")) include ("redirect.php"); 
	else $action="login.php";
	$cookievalue = $_POST['huskmig'] . chr(9) . $_POST['regnskab'] . chr(9) . $_POST['brugernavn'];
	if ($_POST['huskmig']) setcookie ('saldi_huskmig', $cookievalue, time() + (86400 * 30));
	elseif ($rs == $_POST['regnskab'] && $bn == $_POST['brugernavn']) {
		setcookie ('saldi_huskmig', $cookievalue, time() - 3600);
	}
	print "<form name=\"login\" METHOD=\"POST\" ACTION=\"$action\" onSubmit=\"return handleLogin(this);\">\n";
	print "<input type=\"hidden\" name=\"regnskab\" value=\"$_POST[regnskab]\">\n";
	print "<input type=\"hidden\" name=\"brugernavn\" value=\"$_POST[brugernavn]\">\n";
	print "<input type=\"hidden\" name=\"password\"  value=\"$_POST[password]\">\n";
	print "<input type=\"hidden\" name=\"vent\"  value=\"$_POST[vent]\">\n";
	print "<body onload=\"document.login.submit()\">";
	print "</form>";
	exit;
}

if (isset ($_GET['navn'])) $brugernavn = html_entity_decode(stripslashes($_GET['navn']),ENT_COMPAT,$charset);
if (isset ($_GET['regnskab'])) $regnskab = html_entity_decode(stripslashes($_GET['regnskab']),ENT_COMPAT,$charset);
if (isset ($_GET['tlf'])) $kode = stripslashes($_GET['tlf']);
$fejltxt = if_isset($_POST['fejltxt']);
$vent = if_isset($_POST['vent']);
if (!$regnskab && !$brugernavn) {
	if (isset($_POST['regnskab'])) $regnskab = $_POST['regnskab'];
	if (isset($_POST['brugernavn'])) $brugernavn = $_POST['brugernavn'];
	if (isset($_COOKIE['saldi_huskmig'])) {
		if ($hm) $huskmig="checked='checked'";
		else $huskmig=''; 
		if (!$regnskab) $regnskab = $rs;
		if (!$brugernavn) $brugernavn = $bn;
	} else $huskmig='';
}

if (isset($brug_timestamp)) {
 	?>
	<script language="javascript" type="text/javascript" src="../javascript/md5.js"></script>

	<script language="javascript" type="text/javascript">
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
print "<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->\n";
print "<meta http-equiv=\"content-type\" content=\"text/html; charset=$charset\"></head>\n";
if (isset($mastername)&&$mastername) $host="$mastername";   
elseif (strpos($_SERVER['PHP_SELF'],"beta")) $host="!!! BETA !!!";
elseif (!file_exists("../sager/sager.php")) $host="SALDI";
if (file_exists("bg.php")) include ("bg.php");
else $style=''; 
print "<body $style>\n";
print "	<div id=\"main\">\n";    				
print "		<div class=\"loginHolder\">\n";
print "			<div class=\"loginBox\">\n";
print "				<div class=\"loginForm\">\n";    
print "					<form name=\"login1\" METHOD=\"POST\" ACTION=\"index.php\" onSubmit=\"return handleLogin(this);\">\n";
print "						<input type=\"hidden\" name=\"vent\" value=\"$vent\">\n";
if ($fejltxt) {
	print "<label style=\"text-align:center;color:red;\">$fejltxt</label>\n";
	print "<label><br></label>\n";
	print "<label><hr></label>\n";
	print "<label><br></label>\n";
}
print "						<label for=\"Regnskab\">Regnskab:</label>\n";
print "						<input class=\"textinput\" type=\"text\" id=\"regnskab\" name=\"regnskab\" value=\"$regnskab\" tabindex=\"1\">\n";
print "						<label for=\"login\">Brugernavn:</label>\n";
print "						<input class=\"textinput\" type=\"text\" id=\"login\" name=\"brugernavn\" value=\"$brugernavn\" tabindex=\"2\">\n";
print "						<label for=\"password\">Password:</label>\n";
print "						<input class=\"textinput\" type=\"password\" id=\"password\" name=\"password\"  value=\"$kode\" tabindex=\"3\">\n";
print "						<div class=\"loginAction\">\n";
print "							<div class=\"flleft\">\n";
print "								<label for=\"husk_mig\">\n";
print "								<input type=\"checkbox\" id=\"husk_mig\" name=\"huskmig\" $huskmig tabindex=\"4\">\n";
print "								Husk mig</label>\n";
print "								<a class=\"forgotpass\" href=\"glemt_kode.php\" tabindex=\"5\">Glemt adgangskode?</a>\n";
print "							</div><!-- end of flleft -->\n";
print "							<input class=\"button blue flright\" type=\"submit\" value=\"Login\" alt=\"Login\" title=\"Login\" tabindex=\"6\">\n";
print "							<div class=\"clearfix\"></div>\n";
print "						</div><!-- end of loginAction -->\n";
if (isset($mastername) && strtolower($mastername)=='rotary') {
	print "<label style=\"text-align:center;font-size:12px;\">".findtekst(325,$sprog_id)."</label>\n";
}

print "					</form>\n";
print "				</div><!-- end of loginForm -->\n";
print "			</div><!-- end of loginBox -->\n";
print	"		</div><!-- end of loginHolder -->\n";
print "	</div><!-- end of main -->\n";
include ("../includes/version.php");
print "	<div id=\"footer\"><p>Copyright&nbsp;&copy;&nbsp; - $copyright</p></div>\n";




if (!isset($_COOKIE['saldi_std'])) {
	print "<script language=\"javascript\" type=\"text/javascript\">\n";
	print "document.login.regnskab.focus();\n";
	print "</script>\n";
} else {
	print "<script language=\"javascript\" type=\"text/javascript\">\n";
	print "document.login.login.focus();\n";
	print "</script>\n";
}
?>
</body></html>
