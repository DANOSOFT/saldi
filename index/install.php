<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
//------------index/install.php----lap 5.0.0---2026-01-20---
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
//
// Copyright (c) 2003-2025 saldi.dk aps
// ----------------------------------------------------------------------
//
// 20140701 Tilføjet bilag til create regnskab
// 20161106 Tilrettet til ny adgangskodehåndtering. Søg saldikrypt.
// 20200308	Added MySQLi support etc.
// 20210125 PHR Added table mysale
// 20211013 PHR Added colunm language_id to online.
// 20220207 PHR Changed varchar size in 'regnskab'
// 20220823 PHR Changed email to varchar(60) in 'regnskab'
// 20221106 PHR - Various changes to fit php8 / MySQLi
// 20250116 allow user specified hostname for database, ie. other than localhost.
// 20250129 Increase session_id length constraint from 30 to 32 on table online.
// 20250428 LOE Set default storage engine to InnoDB for MySQL 5.7+ (replaces 'storage_engine')+$current_year
// 20260120 PHR Removed an echo.

session_start();
ob_start(); //Starter output buffering
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
	<meta content="text/html; charset=UTF-8" http-equiv="content-type">
	<title>SALDI - det frie danske finansprogram</title>
<?php
if (file_exists("../includes/connect.php")) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL=index.php\">";
	exit;
}
$noskriv=NULL;
$timezone='Europe/Copenhagen';

include("../includes/db_query.php");
include("../includes/settings.php");
include("../includes/version.php");
include("../includes/std_func.php");

print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<tr><td align=\"center\" valign=\"top\">";
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
print "<td width=\"100%\" align = \"center\" $top_bund>$font<a href=\"http://saldi.dk/dok/komigang.html\" target=\"_blank\"><small>Vejledning</small></a></td>\n";
print "</tbody></table></td></tr><tr><td align=\"center\" valign=\"center\">\n";

##########
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $_SESSION['language'] = $_POST['language'];

    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

$current_language = $_SESSION['language'] ?? '';

$language_names = [
    '1' => 'Dansk',
    '2' => 'English',
    '3' => 'Norsk',
];

print '<html><head><title>Language Selection</title></head><body>';

print '<form method="POST">';
print '<label for="language">Choose language:</label>';
print '<select name="language" id="language" onchange="this.form.submit()">';

print '<option value="">-- Select --</option>';
print '<option value="1"' . ($current_language === '1' ? ' selected' : '') . '>1: Dansk</option>';
print '<option value="2"' . ($current_language === '2' ? ' selected' : '') . '>2: English</option>';
print '<option value="3"' . ($current_language === '3' ? ' selected' : '') . '>3: Norsk</option>';

print '</select>';
print '</form>';
##########




if (isset($_POST['opret'])){
	$felt_mangler=false;	
	$pw_diff=false;	
	$db_encode=$_POST['db_encode'];
	$db_type=strtolower($_POST['db_type']);
	$db_navn=trim($_POST['db_navn']);
	if ( strlen($db_navn)==0 ) {
		$felt_mangler=true;
		$db_navn="<i>Feltet er tomt!</i>";
	}

	$db_host=trim($_POST['db_host']);
	if ( strlen($db_host)==0 ) {
		$db_host="localhost";
	}

	$db_bruger=trim($_POST['db_bruger']);
	if ( strlen($db_bruger)==0 ) {
		$felt_mangler=true;
		$db_bruger="<i>Feltet er tomt!</i>";
	}
	$db_password=trim($_POST['db_password']);
	if ( strlen($db_password)==0 ) {
		$felt_mangler=true;
		$db_pw="<i>Feltet er tomt!</i>";
	} else {
		$db_pw="-- vises ikke --";
	}
	$adm_navn=trim($_POST['adm_navn']);
	if ( strlen($adm_navn)==0 ) {
		$felt_mangler=true;
		$adm_navn="<i>Feltet er tomt!</i>";
	}
	$adm_password=trim($_POST['adm_password']);
	$verify_adm_password=trim($_POST['verify_adm_password']);
	if ( strlen($adm_password)==0 ) {
		$felt_mangler=true;
		$adm_pw="<i>Feltet er tomt!</i>";
		if ( strlen($verify_adm_password)==0 ) {
			$verify_adm_pw="<i>Feltet er tomt!</i>";
		} else {
			$verify_adm_pw = "<i>Adgangskoder forskellige! Skal v&aelig;re ens.</i>";
		}
	} else { 
	        if ( $adm_password == $verify_adm_password ) {
			$adm_pw = "**********";
			$verify_adm_pw = "**********";
		} else { 
			$pw_diff=true;
			$verify_adm_pw = "<i>Adgangskoder forskellige. Skal v&aelig;re ens.</i>";
		}
	}
	$_SESSION['db_encode']=$db_encode;
	$_SESSION['db_type']=$_POST['db_type'];
	$_SESSION['db_host']=$db_host;
	$_SESSION['db_navn']=$db_navn;
	$_SESSION['db_bruger']=$db_bruger;
	$_SESSION['db_password']=$db_password;
	$_SESSION['adm_navn']=$adm_navn;
	$_SESSION['adm_password']=$adm_password;

	$tmp = "<table>\n";
	$tmp.= "<tr><td colspan=\"2\" align=\"center\"><big><b>Oplysninger til SALDI-installering</b></big></td></tr>\n";	
	$tmp.= "<tr><td>Databaseserver </td><td><b>$db_type</b></td></tr>\n";
	$tmp.="<tr><td>Servernavn </td><td><b>$db_host</b></td></tr>\n";
	$tmp.= "<tr><td>Tegns&aelig;t </td><td><b>$db_encode</b></td></tr>\n";
	$tmp.= "<tr><td>Databasenavn </td><td><b>$db_navn</b></td></tr>\n";
	$tmp.= "<tr><td>Dataadministrator </td><td><b>$db_bruger</b></td></tr>\n";
	$tmp.= "<tr><td>Adgangskode for databaseadministrator </td><td><b>$db_pw</b></td></tr>\n";
	$tmp.= "<tr><td>SALDI-administratorens brugernavn </td><td><b>$adm_navn</b></td></tr>\n";
	$tmp.= "<tr><td>SALDI-administratorens adgangskode </td><td><b>$adm_pw</b></td></tr>\n";
	$tmp.= "<tr><td>Verificeret adgangskode </td><td><b>$verify_adm_pw</b></td></tr>\n";
/*
	$tmp.="<tr><td>Program til postscript -> pdf (ps2pdf)</td><td><b>$path_ps2pdf</b></td></tr>\n";
	$tmp.="<tr><td>Program til html -> pdf (weasyprint)</td><td><b>$path_weasyprint</b></td></tr>\n";
	$tmp.="<tr><td>Program til sammenlægning af PDF (pdftk)</td><td><b>$path_pdftk</b></td></tr>\n";
	$tmp.="<tr><td>Program til ftp (ncftp) </td><td><b>$path_ncftp</b></td></tr>\n";
	$tmp.="<tr><td>Program til databasedump (pg_dump) </td><td><b>$path_dbdump</b></td></tr>\n";
	$tmp.="<tr><td>Program til datakompression (gzip) </td><td><b>$path_gzip</b></td></tr>\n";
	$tmp.="<tr><td>Program til datakompression (gunzip) </td><td><b>$path_gunzip</b></td></tr>\n";
	$tmp.="<tr><td>Program til datapakning (tar) </td><td><b>$path_tar</b></td></tr>\n";
*/
	
	$tmp.="<tr><td colspan=\"2\"><hr \></td></tr>\n\n";
	if ( $felt_mangler ) $tmp.="<tr><td colspan=\"2\"><b><i>Et eller flere felter mangler at blive udfyldt ovenfor.</i></b></td></tr>\n";
	if ( $pw_diff )  $tmp.="<tr><td colspan=\"2\"><b><i>Adgangskode og verifikationskoden for SALDI-administrator er forskellig.</i></b></td></tr>\n";
	if ( $felt_mangler || $pw_diff ) {
		$tmp.="<tr><td colspan=\"2\"><b><i>G&aring; tilbage til forrige side og ret fejlene</i></b><br />Brug eventuelt browserens tilbage-knap for at g&aring; tilbage.</p>\n\n";
		$tmp.="</body></html>\n";
		print $tmp;
		exit;
	}
	if ($fp=fopen("../includes/connect.php","w")) {
		fclose($fp);
		unlink("../includes/connect.php");
	}	else $noskriv="includes";
	if ($fp=fopen("../temp/test.txt","w")) {
		fclose($fp);
		unlink("../temp/test.txt");
	}	else $noskriv="temp";
	if ($fp=fopen("../logolib/test.txt","w")) {
		fclose($fp);
		unlink("../logolib/test.txt");
	}	else $noskriv="logolib";
	if ($noskriv) {
#		($db_encode=="UTF8")? $href="INSTALLATION_utf8.txt":$href="INSTALLATION_lat9.txt";
		if ($noskriv=="includes") print "<p>Webbrugere har ikke skriveadgang til kataloget \"$noskriv\", hvor \"connect.php\" skal oprettes.</p>\n\n";
		else "Webbrugere har ikke skriveadgang til kataloget \"$noskriv\".";
		print "<p>S&oslash;rg for at der er skriveadgang for den bruger, som den bes&oslash;gende k&oslash;rer som (webserverbrugeren) \n";
		print "til katalogerne";
		print "\"includes\", \"temp\" og \"logolib\".<br>\n\n Se hvordan i installeringsvejledningen <a href=\"../INSTALLATION.txt\" target=\"blank\">INSTALLATION.txt</a>.</p>\n\n";
		print "</td></tr></table></body></html>\n";
		exit;
	}		

	$tmp="";

	$tempdb="template0";

	if ($db_type=="mysqli") {
		$connection = db_connect ("$db_host", "$db_bruger", "$db_password");
	} else {
		$connection = db_connect ("$db_host", "$db_bruger", "$db_password", "template1");
	}

	if (!$connection)	{
		if ($db_type=="mysqli") die( "Kan ikke oprette forbindelse til MySQLi\n");
		else die( "Kan ikke oprette forbindelse til PostgreSQL\n");
	} 

	if ($db_type=="mysqli") {
		$qtxt = "CREATE DATABASE if not exists $db_navn";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		mysqli_select_db($connection,$db_navn);
	} else {
		if (db_exists($db_navn)) {
			$txt="$db_type databasen $db_navn eksisterer i forvejen";
			alert($txt);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=install.php\">";
			exit;
		}
		if ($db_encode=="UTF8") $qtxt = "CREATE DATABASE $db_navn encoding = 'UTF8' template $tempdb";
		else $qtxt = "CREATE DATABASE $db_navn encoding = 'LATIN9' template $tempdb";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		db_close($connection);
		$connection = db_connect ("$db_host", "$db_bruger", "$db_password", "$db_navn");
	}
	transaktion("begin");

	db_modify("CREATE TABLE brugere(id serial NOT NULL, brugernavn text, kode text, status boolean, regnskabsaar integer, rettigheder text, PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	db_modify("INSERT INTO brugere (brugernavn, kode, rettigheder) values ('$adm_navn' ,'$adm_password', '11111111111111111111')",__FILE__ . " linje " . __LINE__);
	$r=db_fetch_array(db_select("SELECT id FROM brugere where brugernavn='$adm_navn'",__FILE__ . " linje " . __LINE__));	
	$adm_password=saldikrypt($r['id'],$adm_password);
	db_modify("UPDATE brugere SET kode='$adm_password' where id = '$r[id]'",__FILE__ . " linje " . __LINE__); 
	$qtxt = "CREATE TABLE regnskab (id serial NOT NULL,	regnskab varchar(60), dbhost varchar(25), dbuser varchar(25), ";
	$qtxt.= "db varchar(25), version varchar(10), sidst varchar(16), brugerantal numeric(5,0), posteringer numeric(10,0), ";
	$qtxt.= "posteret numeric(10,0), mysale numeric(1,0), lukket varchar(2),administrator varchar(2),lukkes date, ";
	$qtxt.= "betalt_til date,logintekst text,email varchar(60),bilag numeric(1,0),sms INT, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "INSERT INTO regnskab (regnskab, dbhost, dbuser, db, version,bilag) values ";
	$qtxt.= "('$db_navn' ,'$db_host', '$db_bruger', '$db_navn', '$version','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE online (session_id varchar(32), brugernavn text, db varchar(30), dbuser varchar(30), rettigheder varchar(30), ";
	$qtxt.= "regnskabsaar integer, logtime varchar(30), revisor boolean, language_id int)";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE kundedata (id serial NOT NULL, firmanavn text, addr1 text, addr2 text, postnr varchar(10), ";
	$qtxt.= "bynavn text, kontakt text, email text, cvrnr text, regnskab text, regnskab_id integer,brugernavn text, ";
	$qtxt.= "kodeord text, kontrol_id text, aktiv int, logtime text,slettet varchar(2),PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE tekster (id serial NOT NULL, sprog_id integer, tekst_id integer, tekst text, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE revisor";
	$qtxt.= "(id serial NOT NULL,regnskabsaar integer,bruger_id integer,brugernavn text,db_id integer,PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE mysale (id serial NOT NULL,deb_id int, db varchar(20), email varchar(60), link text, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "CREATE TABLE settings ";
	$qtxt.= "(id serial NOT NULL, var_name text, var_grp text, var_value text, var_description text, user_id integer, PRIMARY KEY (id))";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('timezone','globals','Europe/Copenhagen','Global Timezone','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('ps2pdf','globals',' 	/usr/bin/ps2pdf','Program for converting PostScript to PDF','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('html2pdf','globals','/usr/bin/weasyprint','Program for converting HTML to PDF','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('pdfmerge','globals','/usr/bin/pdftk','Program for merging PDF files','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('ftp','globals','/usr/bin/ncftp','Program for handling FTP','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('dbdump','globals','/usr/bin/pg_dump','Program for dumping database','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('zip','globals','/bin/gzip','Program for compressing files','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('tar','globals','/bin/tar','Program for packing files','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$languages = "Dansk".chr(9)."English".chr(9)."Norsk";
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('languages','globals','$languages','Avalilable languages','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	$qtxt = "insert into settings(var_name,var_grp,var_value,var_description,user_id) values ";
	$qtxt.= "('language_id','globals','0','Active systemlanguage','0')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	
	transaktion("commit");
#	rename("../includes/connect", "../includes/connect.php");
	
	
	if ($fp=fopen("../includes/connect.php","w")) {
		skriv_connect($fp,$db_host,$db_bruger,$db_password,$db_navn,$db_encode,$db_type);
		fclose($fp);
		print "<table width=\"75%\"><tr><td style=\"text-align:center\">\n\n";
		print "\n\n<h1>SALDI er installeret</h1>\n\n";
		print "<p>Dit SALDI-system er nu oprettet. Og det f&oslash;rste, du skal g&oslash;re, er at oprette et regnskab.</p>\n\n";
		print "<p>Dette g&oslash;res ved at loggge ind med <b>$db_navn</b> som regnskab, <b>$adm_navn</b> \n";
		print "som brugernavn og den valgte adgangskode</p>\n\n";
		print "<p>Tegn en hotline-aftale for kun, s&aring; kan du ringe eller sende en e-mail \n";
		print "og f&aring; hurtigt svar p&aring; sp&oslash;rgsm&aring;l om brugen af SALDI<!-- samt sikret dig adgang til automatiske opdateringer -->.</p>\n\n";
		print "<p>Se mere p&aring; <a href=\"http://saldi.dk/hotline\" target=\"_blank\">http://saldi.dk/hotline</a></p>\n\n";
		print "<p>&nbsp;</p>\n\n";
#		print "<p><a href=../index/index.php>Forts&aelig;t</a></p>\n\n";
		print "<p><a href=\"../index/index.php\" title=\"Til SALDI-administratorsiden hvor regnskaber administreres\" \n";
		print " style=\"text-decoration:none\"><input type=\"button\" value=\"Forts&aelig;t\"></a>\n\n";
		print "</td></tr></table>\n\n";
	} else {
		print "<p>Webbrugere har ikke skriveadgang til kataloget \"includes\", hvor \"connect.php\" skal oprettes.</p>\n\n";
		print "<p>S&oslash;rg for at der er skriveadgang for den bruger, som den bes&oslash;gende k&oslash;rer som (webserverbrugeren) \n";
		print "til katalogerne \n";
		print "\"includes\", \"temp\" og \"logolib\". Se hvordan i installeringsvejledningen INSTALLATION.txt.</p>\n\n";
		print "</td></tr></table></body></html>\n";
		exit;
	}		
} else {
	$db_encode    = if_isset($_SESSION['db_encode']);
	$db_type      = if_isset($_SESSION['db_type']);
	$db_host      = if_isset($_SESSION['db_host']);
	$db_navn      = if_isset($_SESSION['db_navn']);
	$db_bruger    = if_isset($_SESSION['db_bruger']);
	$db_password  = if_isset($_SESSION['db_password']);
	$adm_navn     = if_isset($_SESSION['adm_navn']);
	$adm_password = if_isset($_SESSION['adm_password']);
	if(!if_isset($sprog_id,1));
	if(isset( $_SESSION['language'])){
		$sprog_id = $_SESSION['language'];
	}

	$current_year = date("Y");
	print	"<table width=40% align=center border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print	"<tr><td colspan=\"5\" align=\"center\"><font face=\"Helvetica, Arial, sans-serif\"><big><b>Velkommen til SALDI</b></big></td></tr>";
	print	"<tr><td colspan=\"5\"> <font face=\"Helvetica, Arial, sans-serif\">".findtekst('1994|Hvis du har installeret webserveren Apache med PHP og en af databaseserverne PostgreSQL eller MySQL, kan du nu installere SALDI',$sprog_id).".</td></tr>";
	print	"<FORM name=\"opret\" METHOD=POST ACTION=\"install.php\"><tr><td colspan=2><br></td></tr>";
	$title="V&aelig;lg den databaseserver, du &oslash;nsker at bruge.";
	print "<tr><td><font face=\"Arial,Helvetica\">Databaseserver</td><td title=\"$title\"><SELECT NAME=db_type>";
	if ($db_type) print "<option>$db_type</option>";
	if ($db_type!='PostgreSQL') print "<option>PostgreSQL</option>";
	if ($db_type!='MySQLi') print "<option>MySQLi</option></SELECT>";
	print "</td><td></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('2559|Servernavn',$sprog_id)."</td><td title=\"Hostname for databaseserver\"><input type=\"text\" name=\"db_host\" value=\"$db_host\"><td><td width=\"5%\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('1965|Tegnsæt',$sprog_id)."</td><td title=\"V&aelig;lg det tegns&aelig;t du &oslash;nsker at bruge. Nyere versioner af PostgreSQL fungerer kun med UTF8\"><SELECT NAME=db_encode><option>UTF8</option><option>LATIN9</option></SELECT></td><td></td></tr>";;
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('1966|Databasenavn',$sprog_id)."</td><td title=\"&Oslash;nsket navn p&aring; din hoveddatabase for SALDI\"><INPUT TYPE=TEXT NAME=db_navn VALUE = \"$db_navn\"> <td><td width=5%></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('2560|Eksisterende databaseadministrator',$sprog_id)."</td> <td title=\"Navn p&aring; en bruger, som har i forvejen har tilladelse til at oprette, rette og slette databaser. Typisk er det for PostgreSQL brugeren postgres og for MySQL brugeren root.\"><INPUT TYPE=TEXT NAME=db_bruger VALUE=\"$db_bruger\"></td><td></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('1968|Adgangskode for databaseadministrator',$sprog_id)."</td><td title=\"Adgangskode for ovenst&aring;ende bruger\"><INPUT TYPE=password NAME=db_password VALUE=\"$db_password\"></td><td></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('1969|SALDI-administratorens brugernavn',$sprog_id)."</td><td title=\"&Oslash;nsket navn p&aring; din administratorkonto til dit SALDI-system\"><INPUT TYPE=TEXT NAME=adm_navn VALUE = \"$adm_navn\"></td><td></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('1970|SALDI-administratorens adgangskode',$sprog_id)."</td><td title=\"&Oslash;nsket adgangskode for administratoren af dit SALDI-system\"><INPUT TYPE=password NAME=adm_password VALUE = \"$adm_password\"></td><td></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><font face=\"Arial,Helvetica\">".findtekst('2561|SALDI-administratorens adgangskode igen',$sprog_id)."</td><td title=\"Verificering af ovenst&aring;ende adgangskode\"><INPUT TYPE=password NAME=verify_adm_password VALUE = \"$adm_password\"></td><td></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=2 align=center title=\"Klik her for at oprette dit SALDI-system\"><INPUT TYPE=submit name=opret VALUE=Install&eacute;r></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=\"5\"> <font face=\"Helvetica, Arial, sans-serif\"> ".findtekst('2004|<b>Alle</b> felter skal udfyldes. Hvis du er i tvivl, s&aring; udfyld kun de tomme felter',$sprog_id).".</td></tr>";
	print "<tr><td><br></td></tr><tr></tr></FORM>";
	print "</tr>";
	print	"</tbody></table>";
	print	"</td></tr>";
	print	"<tr><td align=\"center\" valign=\"bottom\">";
	print	"<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print	"<td style=\"border: 1px solid rgb(180,180,255); padding: 0pt 0pt 1px;\" align=\"left\" background=\"../img/grey1.gif\" width=\"100%\" bgcolor=\"$bgcolor2\"><font face=\"Helvetica, Arial, sans-serif\" color=\"#000000\"><small><small>&nbsp;Copyright&nbsp;&copy;&nbsp;2003-$current_year&nbsp;saldi.dk&nbsp;aps</small></small></td>";
	print	"</tbody></table>";
	print	"</td></tr>";
	print	"</tbody></table>";
}


function skriv_connect($fp,$db_host,$db_bruger,$db_password,$db_navn,$db_encode,$db_type) {
	$current_year = date("Y");
	fwrite($fp,"<?php\n");
	fwrite($fp,"//                         ___   _   _   __  _     ___  _ _  \n");
	fwrite($fp,"//                        / __| / \ | | |  \| |   |   \| / / \n");
	fwrite($fp,"//                        \__ \/ _ \| |_| | | | _ | |) |  <  \n");
	fwrite($fp,"//                        |___/_/ \_|___|__/|_||_||___/|_\_\ \n");
	fwrite($fp,"//\n");
	fwrite($fp,"// --- includes/connect.php --- ver 4.1.1 --- 2025.04.24 ---\n");
	fwrite($fp,"// LICENSE\n");
	fwrite($fp,"//\n");
	fwrite($fp,"// This program is free software. You can redistribute it and / or\n");
	fwrite($fp,"// modify it under the terms of the GNU General Public License (GPL)\n");
	fwrite($fp,"// which is published by The Free Software Foundation; either in version 2\n");
	fwrite($fp,"// of this license or later version of your choice.\n");
	fwrite($fp,"// However, respect the following:\n");
	fwrite($fp,"//\n");
	fwrite($fp,"// It is forbidden to use this program in competition with Saldi.DK ApS\n");
	fwrite($fp,"// or other proprietor of the program without prior written agreement.\n");
	fwrite($fp,"//\n");
	fwrite($fp,"// The program is published with the hope that it will be beneficial,\n");
	fwrite($fp,"// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.\n");
	fwrite($fp,"// See GNU General Public License for more details.\n");
	fwrite($fp,"//\n");
	fwrite($fp,"// Copyright (c) 2003-$current_year saldi.dk aps\n");
	fwrite($fp,"// ----------------------------------------------------------------------\n");
	fwrite($fp,"\n");
	fwrite($fp,"if (!isset(\$bg)) \$bg='';\n");
	fwrite($fp,"if (!isset(\$title)) \$title='';\n");
	fwrite($fp,"\$db_encode = \"$db_encode\";\n");
	fwrite($fp,"\$db_type = \"$db_type\";\n");
	fwrite($fp,"\n");
	fwrite($fp,"if (file_exists(\"../includes/db_query.php\")) {\n");
	fwrite($fp,"	include(\"../includes/db_query.php\");\n");
	fwrite($fp,"	include(\"../includes/version.php\");\n");
	fwrite($fp,"	include(\"../includes/settings.php\");\n");
	fwrite($fp,"}\n");
	fwrite($fp,"elseif (file_exists(\"../../includes/db_query.php\")){\n");
	fwrite($fp,"	include(\"../../includes/db_query.php\");\n");
	fwrite($fp,"	include(\"../../includes/version.php\");\n");
	fwrite($fp,"	include(\"../../includes/settings.php\");\n");
	fwrite($fp,"}\n");
	fwrite($fp,"\n");
	fwrite($fp,"\$sqhost = \"$db_host\";\n");
	fwrite($fp,"\$squser = \"$db_bruger\";\n");
	fwrite($fp,"\$sqpass = \"$db_password\";\n");
	fwrite($fp,"\$sqdb   = \"$db_navn\";\n");
	fwrite($fp,"\n");
	fwrite($fp,"#\$login = \"\";\n");
	fwrite($fp,"#\$login = \"dropdown\";\n");
	fwrite($fp,"\$login = \"cookie\";\n");
	fwrite($fp,"\$revisorregnskab = \"1\";\n");
	fwrite($fp,"\n");
	fwrite($fp,"# \$brug_timestamp=\"y\";\n");
	fwrite($fp,"\n");
	fwrite($fp,"\$font = \"<font face='Arial, Helvetica, sans-serif'>\";\n");
	fwrite($fp,"\n");
	if ($db_type=='mysqli') {
		fwrite($fp,"\$connection = db_connect (\"\$sqhost\", \"\$squser\", \"\$sqpass\");\n");
	} else {
		fwrite($fp,"if (\$sqpass) \$connection = db_connect (\"\$sqhost\", \"\$squser\", \"\$sqpass\", \"\$sqdb\");\n");
		fwrite($fp,"else \$connection = db_connect (\"\$sqhost\", \"\$squser\", \"\$sqpass\", \"\$sqdb\");\n");
	}
	fwrite($fp,"if (!isset(\$connection)) die( \"Unable to connect to database\");\n");
	if ($db_type == 'mysqli') {
		fwrite($fp, "elseif (!mysqli_select_db(\$connection, \$sqdb)) die( \"Unable to connect to MySQL\");\n");
		fwrite($fp, "else mysqli_query(\$connection, \"SET SESSION default_storage_engine='InnoDB'\");\n"); // Set default storage engine to InnoDB for MySQL 5.7+ (replaces 'storage_engine') 

	}	
	fwrite($fp,"\n");
	fwrite($fp,"?".">\n");
}

?>
</head>
</body></html>
