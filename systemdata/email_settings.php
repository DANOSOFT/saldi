<?php
// ------------systemdata/email_settings.php-----patch 4.0.8 ----2025-01-26--
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
// Language-specific sender email settings

@session_start();
$s_id=session_id();

$title = "Email Settings";
$css="../css/standard.css";

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/topline_settings.php");
include("../includes/db_query.php");
include("../includes/varer.php");
include("../includes/opdat.php");
include("../includes/top.php");

# Process form submission
if ($_POST['submit']) {
	# Process sender emails
	foreach ($_POST['sender_email'] as $lang_id => $email) {
		$email = trim($email);
		
		# Check if setting exists
		$qtxt = "select id from settings where var_name = 'sender_email' and var_grp = 'email_settings' and group_id = '$lang_id'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		
		if ($r) {
			# Update existing setting
			if ($email) {
				$qtxt = "update settings set var_value = '".db_escape_string($email)."' where id = ".$r['id'];
			} else {
				$qtxt = "delete from settings where id = ".$r['id'];
			}
		} else {
			# Insert new setting
			if ($email) {
				$qtxt = "insert into settings (var_name, var_grp, var_value, var_description, group_id) values ('sender_email', 'email_settings', '".db_escape_string($email)."', 'Language-specific sender email', '$lang_id')";
			}
		}
		
		if ($email || $r) {
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	
	# Process sender names
	foreach ($_POST['sender_name'] as $lang_id => $name) {
		$name = trim($name);
		
		# Check if setting exists
		$qtxt = "select id from settings where var_name = 'sender_name' and var_grp = 'email_settings' and group_id = '$lang_id'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		
		if ($r) {
			# Update existing setting
			if ($name) {
				$qtxt = "update settings set var_value = '".db_escape_string($name)."' where id = ".$r['id'];
			} else {
				$qtxt = "delete from settings where id = ".$r['id'];
			}
		} else {
			# Insert new setting
			if ($name) {
				$qtxt = "insert into settings (var_name, var_grp, var_value, var_description, group_id) values ('sender_name', 'email_settings', '".db_escape_string($name)."', 'Language-specific sender name', '$lang_id')";
			}
		}
		
		if ($name || $r) {
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	
	print "<script>alert('Email indstillinger gemt succesfuldt!');</script>";
}

# Add menu structure
if ($menu=='T') {  # Top menu layout
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\">";
    print "<a class='button blue small' href=\"formularkort.php?valg=formularer\">".findtekst('573|Formularkort', $sprog_id)."</a> &nbsp;";
    print "<a class='button blue small' href=\"email_settings.php\" style='background-color: #4CAF50;'>Email Indstillinger</a></div>\n";
	print "<span class=\"headerTxt\">Email Indstillinger</span>\n";     
	print "<div class=\"headerbtnRght\"></div>";    
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\"><tbody>";
} elseif ($menu=='S') {
	print "<html>\n";
	print "<head>\n";
	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
	print "<meta name='viewport' content='width=1024'>\n";
	print "</head>\n";
	print "<body>\n";
	print "<table width='100%' border='0' cellspacing='0' cellpadding='0'><tbody>\n";
	print "<tr><td width='' height='1%' align='center' valign='top' collspan='2'>\n";
	print "<table width='100%' height='1%' align='center' border='0' cellspacing='2' cellpadding='0'><tbody>\n";

	print "<td width='12%'><a href=formularkort.php?valg=formularer accesskey='l'><button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">";
	print "Tilbage</button></a></td>\n";

	print "<td width='76%' align='center' style='$topStyle'>Email Indstillinger</td>\n";
	print "</tbody></table></td></tr>\n";
} else {
	# Fallback for other menu types
	print "<html>\n";
	print "<head>\n";
	print "<meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>\n";
	print "</head>\n";
	print "<body>\n";
}

# Get all languages from grupper table
$qtxt = "select kodenr, box1 from grupper where art = 'VSPR' order by kodenr";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);

$languages = array();
$languages[0] = "Dansk (Default)"; # Add Danish as default

while ($r = db_fetch_array($q)) {
	$languages[$r['kodenr']] = $r['box1'];
}

# Get current email settings
$qtxt = "select group_id, var_value from settings where var_name = 'sender_email' and var_grp = 'email_settings'";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);

$current_emails = array();
while ($r = db_fetch_array($q)) {
	$current_emails[$r['group_id']] = $r['var_value'];
}

# Get current sender name settings
$qtxt = "select group_id, var_value from settings where var_name = 'sender_name' and var_grp = 'email_settings'";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);

$current_names = array();
while ($r = db_fetch_array($q)) {
	$current_names[$r['group_id']] = $r['var_value'];
}

print "<form method='post'>";
print "<table width='100%' border='0' cellpadding='2' cellspacing='0'>";
print "<tr><td colspan='3'><h2>Sprogspecifikke Afsender Email Indstillinger</h2></td></tr>";
print "<tr><td colspan='3'><p>Konfigurer afsender email adresse og navn for hvert sprog. Emails vil blive sendt fra disse adresser med de angivne navne baseret på det sprog der er valgt for formularen.</p></td></tr>";

print "<tr>";
print "<td width='200'><strong>Sprog</strong></td>";
print "<td width='300'><strong>Email Adresse</strong></td>";
print "<td><strong>Afsender Navn</strong></td>";
print "</tr>";

foreach ($languages as $lang_id => $lang_name) {
	$current_email = isset($current_emails[$lang_id]) ? $current_emails[$lang_id] : '';
	$current_name = isset($current_names[$lang_id]) ? $current_names[$lang_id] : '';
	
	print "<tr>";
	print "<td><strong>$lang_name</strong></td>";
	print "<td><input type='email' name='sender_email[$lang_id]' value='$current_email' placeholder='Indtast email adresse' style='width:280px;'></td>";
	print "<td><input type='text' name='sender_name[$lang_id]' value='$current_name' placeholder='Indtast afsender navn' style='width:280px;'></td>";
	print "</tr>";
}

print "<tr><td colspan='3'><br><input type='submit' name='submit' value='Gem Indstillinger' class='button'></td></tr>";
print "</table>";
print "</form>";

# Close menu structure
if ($menu=='T') {
	print "</tbody></table></div><!-- end of maincontentLargeHolder -->";
} elseif ($menu=='S') {
	print "</tbody></table></td></tr></tbody></table>";
} else {
	# Fallback closing
}

include("../includes/bottom.php");
?>
