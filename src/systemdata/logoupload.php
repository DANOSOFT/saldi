<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// -------systemdata/logoupload.php-----patch 4.1.1 ----2026-01-03-------
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
//
// 20131118 PK Har ændret upload af baggrund. Det er nu muligt at vælge forskellige baggrund til Tilbud, Ordrer og Faktura
// 20131118 PK Har fjernet upload af jpg og eps logo og tilføjet pdf bilag til mail (Tilbud, Ordrer og Faktura)
// 20131118 PK Man kan preview og slette den enkelte uploadede fil. Ved preview er der oprettet et nyt document 'view_logoupload.php'
// 20161123 PK Har ændret upload størrelse fra 1mb til 10mb
// 20170224 PHR	Tilføjet mulighed for upload af generel baggrund.
// 20190225 MSC - Rettet topmenu design og isset fejl
// 20210803 LOE - Translated some texts here and included the required file
// 20220615 PHR - Creates folder logolib if not exists
// 20260102 LOE - Added uploading to folders by department

session_start();
$s_id=session_id();
$css="../css/standard.css";
$title="SALDI - Logo Upload";

include("../includes/connect.php");
include("../includes/settings.php");
include("../includes/online.php");
include("../includes/db_query.php");
include("../includes/std_func.php"); #20210803
include("../includes/topline_settings.php");

if (!isset ($_POST['bilagfil'])) $_POST['bilagfil'] = null;

global $db_id; 
global $menu;
global $sprog_id; 

// Get current user info
global $bruger_id;

// Check if user is admin (bruger_id = -1 or 1)
$is_admin = ($bruger_id == -1 || $bruger_id == 1);

// Get user's assigned department from settings (for permission checking)
$afd = get_settings_value('afd', 'brugerAfd', 1, $bruger_id);
$user_assigned_department = isset($afd) && $afd > 0 ? (int)$afd : 0;

$checksprog = $_GET['sprog'];

error_log("checksprog from GET: " . ($checksprog ?? 'not set'));
error_log("complete get array: " . print_r($_GET, true));
$current_sprog = 'Dansk'; 
if (isset($_GET['sprog'])) {
    $current_sprog = $_GET['sprog'];
    $_SESSION['current_sprog'] = $current_sprog;
} elseif (isset($_SESSION['current_sprog'])) {
    $current_sprog = $_SESSION['current_sprog'];
} elseif (isset($_POST['sprog_valg'])) {
    $current_sprog = $_POST['sprog_valg'];
    $_SESSION['current_sprog'] = $current_sprog;
}
error_log("current sprog A is: " . $current_sprog);
// Get departments for dropdown
$departments = array();
$q = db_select("SELECT kodenr, beskrivelse FROM grupper WHERE art='AFD' ORDER BY beskrivelse", __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
    $departments[$r['kodenr']] = $r['beskrivelse'];
}

# Get selected department from GET, POST, or session ; THIS IS THE DEPARTMENT TO WORK WITH
$selected_department = null;
if (isset($_GET['department'])) {
    $selected_department = (int)$_GET['department'];
    $_SESSION['selected_department'] = $selected_department;
} elseif (isset($_POST['department'])) {
    $selected_department = (int)$_POST['department'];
    $_SESSION['selected_department'] = $selected_department;
} elseif (isset($_SESSION['selected_department'])) {
    $selected_department = (int)$_SESSION['selected_department'];
}

// If no department selected yet, set default based on user type
if ($selected_department === null) {
    if ($is_admin) {
        // Admin: default to first available department
        if (!empty($departments)) {
            reset($departments);
            $selected_department = (int)key($departments);
        } else {
            $selected_department = 0;
        }
    } else {
        // Non-admin: default to their assigned department
        $selected_department = $user_assigned_department;
    }
    $_SESSION['selected_department'] = $selected_department;
}

// Check if user has permission to access selected department
$has_permission = false;
if ($is_admin) {
    // Admin can access all departments
    $has_permission = true;
} else {
    // Non-admin can only access their own assigned department
    $has_permission = ($selected_department == $user_assigned_department);
}

// If non-admin has no permission and has a department, redirect to it
if (!$has_permission && $user_assigned_department > 0) {
    $selected_department = $user_assigned_department;
    $_SESSION['selected_department'] = $selected_department;
    $has_permission = true;
}

// If still no permission (user with no department trying to access other department), show error
if (!$has_permission) {
    die("You do not have access to this department.");
}

// Validate that selected department exists in departments list (for both admin and non-admin)
if ($selected_department > 0 && !isset($departments[$selected_department]) && !$is_admin) {
    // For non-admin, if their assigned department doesn't exist in the list, show error
    die("Your department does not exist in the system.");
}


print "<div align=\"center\">";
if ($menu=='T') {
#	print "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">";
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">\n";
	print "<div class=\"headerbtnLft\"></div>\n";
#	print "<span class=\"headerTxt\">Systemsetup</span>\n";     
#	print "<div class=\"headerbtnRght\"><!--<a href=\"index.php?page=../debitor/debitorkort.php;title=debitor\" class=\"button green medium right\">Ny debitor</a>--></div>";       
	print "</div><!-- end of header -->";
	print "<div id=\"leftmenuholder\">";
	include_once 'left_menu.php';
	print "</div><!-- end of leftmenuholder -->\n";
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<table border=\"1\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable2\"><tbody>";
} elseif ($menu=='S') {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";

	print "<td width=\"10%\"><a href=\"formularkort.php\" accesskey=\"L\">";
	print "<button style='$buttonStyle; width:100%' onMouseOver=\"this.style.cursor='pointer'\">".findtekst('30|Back', $sprog_id)."</button></a></td>"; #20210803

	print "<td width=\"80%\" align='center' style='$topStyle'>".findtekst('1745|Load File', $sprog_id)."</td>";
	print "<td width=\"10%\" align='center' style='$topStyle'><br></td>";

	print "</tbody></table>";
	print "</td></tr>";
} else {
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td height = \"25\" align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><a href=\"formularkort.php\" accesskey=\"L\">".findtekst('30|Back', $sprog_id)."</a></td>"; #20210803
	print "<td width=\"80%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\">".findtekst('1745|Load File', $sprog_id)."</td>";
	print "<td width=\"10%\" $top_bund><font face=\"Helvetica, Arial, sans-serif\" color=\"#000066\"><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	}
if (!file_exists("../logolib")) mkdir("../logolib",0777); 

// Create department directories if they don't exist - FIXED: now creates language_department directories
foreach ($departments as $dept_id => $dept_name) {
    // Get all languages
    $languages = array('Dansk'); // Start with default
    $q = db_select("SELECT DISTINCT sprog FROM formularer WHERE sprog != 'Dansk' ORDER BY sprog", __FILE__ . " linje " . __LINE__);
    while ($r = db_fetch_array($q)) {
        if (!in_array($r['sprog'], $languages)) {
            $languages[] = $r['sprog'];
        }
    }
    
    // Create directory for each language and department combination
    foreach ($languages as $language) {
        $dir_name = strtolower(str_replace(' ', '_', $language)) . "_" . $dept_id;
        $dept_dir = "../logolib/" . $dir_name;
        if (!file_exists($dept_dir)) {
            mkdir($dept_dir, 0777, true);
        }
    }
}

if (isset($_GET['slet_bilag'])) {
    $slet_bilag=$_GET['slet_bilag'].".pdf"; 
    
    // For delete operation, use the selected department and current language
    $delete_department = $selected_department;
    
    // Check permission before deleting
    if (!$is_admin && $delete_department != $user_assigned_department) {
        print "<BODY onLoad=\"javascript:alert('You do not have permission to delete files from this department')\">";
        upload();
        exit;
    }
    error_log("current sprog C is: " . $current_sprog);
    
    // FIX: Changed $GET to $_GET
    $selected_sprog = $_GET['sprog'] ?? $current_sprog;
    error_log("Current sprog is : $current_sprog");
    error_log("Selected sprog is : $selected_sprog");
    
    // FIXED: Use language_department directory structure
    $language_dir_name = strtolower(str_replace(' ', '_', $current_sprog)) . "_" . $delete_department;
    $file_path = "../logolib/" . $language_dir_name . "/" . $slet_bilag;
    error_log("Deleting file: $file_path (User is admin: " . ($is_admin ? 'Yes' : 'No') . ", Selected department: $selected_department, Language: $current_sprog)");
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    upload();
    exit;
}
	
if(isset($_POST['bgfil'])||($_POST['bilagfil'])) {
    
    // Check permission before uploading
    $department = (int)$_POST['department'];
    if (!$is_admin && $department != $user_assigned_department) {
        print "<BODY onLoad=\"javascript:alert('You do not have permission to upload files to this department')\">";
        upload();
        exit;
    }
	
	$fejl = $_FILES['uploadedfile']['error'];
	$alert1 = 'Unfortunately - your logo is too big. Only up to 100 kb is accepted';
	if ($fejl) {
		switch ($fejl) {
			case 2: print "<BODY onLoad=\"javascript:alert('$alert1')\">";
		}
		upload();
		exit;
	}
	if (!isset ($_POST['bilag_valg'])) $_POST['bilag_valg'] = null;
	if (!isset ($_POST['bg_valg'])) $_POST['bg_valg'] = null;
	if (!isset ($_POST['department'])) $_POST['department'] = 0;
	
	$bilag_valg = $_POST['bilag_valg'];
	$bg_valg = $_POST['bg_valg'];
	$department = (int)$_POST['department'];
	error_log("Uploading to department: $department (User is admin: " . ($is_admin ? 'Yes' : 'No') . ")");
	
	if (!isset ($_POST['sprog_valg'])) $_POST['sprog_valg'] = 'Dansk';
	$sprog_valg = $_POST['sprog_valg'];
	
	$fil_stoerrelse = $_FILES['uploadedfile']['size'];
	$filetype = $_FILES['uploadedfile']['type'];
	$fileName= $_FILES['uploadedfile']['name'];
	$fra = $_FILES["uploadedfile"]["tmp_name"];
	$fil_stoerrelse = $_FILES["uploadedfile"]["size"];
	
	// Build filename - FIXED: No language prefix in filename since it's in the directory structure
	$valg = '';
	if ($bg_valg) {
		$valg = $bg_valg;
	} else {
		$valg = $bilag_valg;
	}

	if ((strpos($filetype,'pdf'))||(strpos($fileName,'.PDF'))||(strpos($fileName,'pdf'))) {
		if($fil_stoerrelse > 10485760) {
			$tmp=ceil($fil_stoerrelse);
			system ("rm $filename");
			$tmp/=1024;
			$alert = findtekst('1747|Unfortunately - your PDF is too big. Only up to 10 MB is accepted, and it takes up', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alert $tmp MB')\">";
			upload();
			exit;
		}
		
		// FIXED: Use language_department directory structure
		$language_dir_name = strtolower(str_replace(' ', '_', $sprog_valg)) . "_" . $department;
		$dest_dir = "../logolib/" . $language_dir_name . "/";
		if (!file_exists($dest_dir)) {
			mkdir($dest_dir, 0777, true);
		}
		$til = $dest_dir . $valg . ".pdf";
		error_log("Saving file to: $til (Language: $sprog_valg, Department: $department)");
		
	} else {
		$alert1 = findtekst('1748|The file format must be PDF', $sprog_id);
		print "<BODY onLoad=\"javascript:alert('$alert1')\">";
		upload();
		exit;
	}
	
	if (move_uploaded_file($fra, $til)) {
		$pdftk = shell_exec("which pdftk");
		if ($pdftk) {
			$alert= findtekst('1751|The page has been loaded.', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alert')\">";
			upload();
			exit;
		} elseif (file_exists($pdf2ps)) {
			$pdffil=$til;
			$pdffil = str_replace($dest_dir,"",$pdffil);
			$psfil=str_replace(".pdf",".ps",$pdffil);
			system ("cd $dest_dir\nrm $psfil\n$pdf2ps $pdffil");
			$alert1= findtekst('1751|The page has been loaded.', $sprog_id);
			print "<BODY onLoad=\"javascript:alert('$alert1')\">";
		}
		else print "<BODY onLoad=\"javascript:alert('".findtekst('1752|Neither PDFTK (recommended) or PDF2PS is installed - logo cannot be loaded', $sprog_id)."')\">";
	} else { 
		$txt1= findtekst('1753|An error occurred during loading. Please try again', $sprog_id);
		print "<BODY onLoad=\"javascript:alert('$txt1')\">";
		echo "$txt1";
		upload();
	}
} else upload();
print "</tbody></table>";



function upload(){
    global $font;
    global $db_id;
    global $sprog_id;
    global $current_sprog;
    global $departments;
    global $selected_department;
    global $bruger_id;
    global $user_assigned_department;
    global $is_admin;

    // Function to check for files in department directory - FIXED: Use language_department directory structure
    function check_file_exists($language, $file_type, $department) {
        // Convert language to lowercase and replace spaces with underscores for directory name
        $language_dir_name = strtolower(str_replace(' ', '_', $language)) . "_" . $department;
        $dept_dir = "../logolib/" . $language_dir_name . "/";
        $file_path = $dept_dir . $file_type . ".pdf";
        
        if (file_exists($file_path)) {
            return array('file' => $file_path, 'name' => $file_type, 'department' => $department, 'language' => $language);
        }
        return false;
    }
    
    // Check for files in the current language and selected department
    $bg_check = check_file_exists($current_sprog, 'bg', $selected_department);
    if ($bg_check) {
        $dept_info = " (Dept. {$bg_check['department']}, Language: {$bg_check['language']})";
        $bg="<a href=\"view_logoupload.php?vis={$bg_check['name']}&sprog=$current_sprog&department={$bg_check['department']}\">".findtekst('1754|show background', $sprog_id)."$dept_info</a>";
        $txt1= findtekst('1755|Do you want to delete this background for all forms?', $sprog_id);
        
        $slet_bg="<a href=\"logoupload.php?slet_bilag={$bg_check['name']}&sprog={$bg_check['language']}&department={$bg_check['department']}\" onclick=\"return confirm('$txt1')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $bg="<i>".findtekst('1758|No background', $sprog_id)."</i>";
        $slet_bg=NULL;
    }
    
    $tilbud_bg_check = check_file_exists($current_sprog, 'tilbud_bg', $selected_department);
    if ($tilbud_bg_check) {
        $dept_info = " (Dept. {$tilbud_bg_check['department']}, Language: {$tilbud_bg_check['language']})";
        $tilbud_bg="<a href=\"view_logoupload.php?vis={$tilbud_bg_check['name']}&sprog=$current_sprog&department={$tilbud_bg_check['department']}\">".findtekst('1756|show background for quotes', $sprog_id)."$dept_info</a>";
        $txt= findtekst('1757|Do you want to delete this background for quotes?', $sprog_id);
       
        $slet_tilbud_bg="<a href=\"logoupload.php?slet_bilag={$tilbud_bg_check['name']}&sprog={$tilbud_bg_check['language']}&department={$tilbud_bg_check['department']}\" onclick=\"return confirm('$txt')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $tilbud_bg="<i>".findtekst('1758|No background', $sprog_id)."</i>";
        $slet_tilbud_bg=NULL;
    }
    
    $ordrer_bg_check = check_file_exists($current_sprog, 'ordrer_bg', $selected_department);
    if ($ordrer_bg_check) {
        $dept_info = " (Dept. {$ordrer_bg_check['department']}, Language: {$ordrer_bg_check['language']})";
        $ordrer_bg="<a href=\"view_logoupload.php?vis={$ordrer_bg_check['name']}&sprog=$current_sprog&department={$ordrer_bg_check['department']}\">".findtekst('1759|show background for orders', $sprog_id)."$dept_info</a>";
        $txt= findtekst('1760|Do you want to delete this background for orders?', $sprog_id);
        
        $slet_ordrer_bg="<a href=\"logoupload.php?slet_bilag={$ordrer_bg_check['name']}&sprog={$ordrer_bg_check['language']}&department={$ordrer_bg_check['department']}\" onclick=\"return confirm('$txt')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $ordrer_bg="<i>".findtekst('1758|No background', $sprog_id)."</i>";
        $slet_ordrer_bg=NULL;
    }
    
    $faktura_bg_check = check_file_exists($current_sprog, 'faktura_bg', $selected_department);
    if ($faktura_bg_check) {
        $dept_info = " (Dept. {$faktura_bg_check['department']}, Language: {$faktura_bg_check['language']})";
        $faktura_bg="<a href=\"view_logoupload.php?vis={$faktura_bg_check['name']}&sprog=$current_sprog&department={$faktura_bg_check['department']}\">".findtekst('1761|show background for invoices', $sprog_id)."$dept_info</a>";
        $txt= findtekst('1762|Do you want to delete this background for invoices?', $sprog_id);
       
        $slet_faktura_bg="<a href=\"logoupload.php?slet_bilag={$faktura_bg_check['name']}&sprog={$faktura_bg_check['language']}&department={$faktura_bg_check['department']}\" onclick=\"return confirm('$txt')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $faktura_bg="<i>".findtekst('1758|No background', $sprog_id)."</i>";
        $slet_faktura_bg=NULL;
    }
    
    // Check for bilag files
    $tilbud_bilag_check = check_file_exists($current_sprog, 'tilbud_bilag', $selected_department);
    if ($tilbud_bilag_check) {
        $dept_info = " (Dept. {$tilbud_bilag_check['department']}, Language: {$tilbud_bilag_check['language']})";
        $tilbud_bilag="<a href=\"view_logoupload.php?vis={$tilbud_bilag_check['name']}&sprog=$current_sprog&department={$tilbud_bilag_check['department']}\">".findtekst('1763|show attachment for quotes', $sprog_id)."$dept_info</a>";
        $txt= findtekst('1764|Do you want to delete this attachment for quotes?', $sprog_id);
       
        $slet_tilbud_bilag="<a href=\"logoupload.php?slet_bilag={$tilbud_bilag_check['name']}&sprog={$tilbud_bilag_check['language']}&department={$tilbud_bilag_check['department']}\" onclick=\"return confirm('$txt')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $tilbud_bilag="<i>".findtekst('1765|No attachment', $sprog_id)."</i>";
        $slet_tilbud_bilag=NULL;
    }
    
    $ordrer_bilag_check = check_file_exists($current_sprog, 'ordrer_bilag', $selected_department);
    if ($ordrer_bilag_check) {
        $dept_info = " (Dept. {$ordrer_bilag_check['department']}, Language: {$ordrer_bilag_check['language']})";
        $ordrer_bilag="<a href=\"view_logoupload.php?vis={$ordrer_bilag_check['name']}&sprog=$current_sprog&department={$ordrer_bilag_check['department']}\">".findtekst('1766|show attachment for orders', $sprog_id)."$dept_info</a>";
        $txt= findtekst('1767|Do you want to delete this attachment for orders?', $sprog_id);
       
        $slet_ordrer_bilag="<a href=\"logoupload.php?slet_bilag={$ordrer_bilag_check['name']}&sprog={$ordrer_bilag_check['language']}&department={$ordrer_bilag_check['department']}\" onclick=\"return confirm('$txt')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $ordrer_bilag="<i>".findtekst('1765|No attachment', $sprog_id)."</i>";
        $slet_ordrer_bilag=NULL;
    }
    
    $faktura_bilag_check = check_file_exists($current_sprog, 'faktura_bilag', $selected_department);
    if ($faktura_bilag_check) {
        $dept_info = " (Dept. {$faktura_bilag_check['department']}, Language: {$faktura_bilag_check['language']})";
        $faktura_bilag="<a href=\"view_logoupload.php?vis={$faktura_bilag_check['name']}&sprog=$current_sprog&department={$faktura_bilag_check['department']}\">".findtekst('1768|show attachment for invoices', $sprog_id)."$dept_info</a>";
        $txt= findtekst('1769|Do you want to delete this attachment for invoices?', $sprog_id);
        
        $slet_faktura_bilag="<a href=\"logoupload.php?slet_bilag={$faktura_bilag_check['name']}&sprog={$faktura_bilag_check['language']}&department={$faktura_bilag_check['department']}\" onclick=\"return confirm('$txt')\">".findtekst('1099|Delete', $sprog_id)."</a>";
    } else {
        $faktura_bilag="<i>".findtekst('1765|No attachment', $sprog_id)."</i>";
        $slet_faktura_bilag=NULL;
    }
    
    print "<tr><td width=\"100%\" align=\"center\">";
    print "<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">";
    print "<colgroup>
                        <col width=\"15%\">
                        <col width=\"20%\">
                        <col width=\"30%\">
                        <col width=\"5%\">
                        <col width=\"12%\">
                        <col width=\"3%\">
                        <col width=\"15%\">
                </colgroup>";
    print "<tbody>";

    print "<tr><td colspan=\"7\" align=\"center\">";
    print "<form method=\"GET\" action=\"logoupload.php\" id=\"filterForm\">";
    print "<strong>Select language: </strong>";
    print "<select name=\"sprog\" onchange=\"this.form.submit()\">";
    print "<option value=\"Dansk\"" . ($current_sprog == 'Dansk' ? ' selected' : '') . ">Dansk</option>";
    $q=db_select("select distinct sprog from formularer order by sprog",__FILE__ . " linje " . __LINE__);
    while ($r=db_fetch_array($q)) {
        if ($r['sprog'] != 'Dansk') {
            $selected = ($current_sprog == $r['sprog']) ? ' selected' : '';
            print "<option value=\"".$r['sprog']."\"$selected>".$r['sprog']."</option>";
        }
    }
    print "</select>";
    
    print "&nbsp;&nbsp;<strong>Select department: </strong>";
    print "<select name=\"department\" onchange=\"this.form.submit()\">";
    #
    foreach ($departments as $dept_id => $dept_name) {
        $selected = ($selected_department == $dept_id) ? ' selected' : '';
        if ($is_admin) {
            // Admin can see and select all departments
            print "<option value=\"$dept_id\"$selected>$dept_id - $dept_name</option>";
        } else {
            // Non-admin can only see and select their own assigned department
            if ($dept_id == $user_assigned_department) {
                print "<option value=\"$dept_id\"$selected>$dept_id - $dept_name</option>";
            } else {
                // Still show but disabled
                print "<option value=\"$dept_id\" disabled>$dept_id - $dept_name</option>";
            }
        }
    }
    print "</select>";
    
    // Show user permission info
    if (!$is_admin) {
        print "<br><small><i>You only have access to your own department (Dept. $user_assigned_department)</i></small>";
    } else {
        print "<br><small><i>Admin: You have access to all departments</i></small>";
    }
    
    print "</form>";
    
    $dept_info = isset($departments[$selected_department]) 
        ? " (Dept. $selected_department: " . $departments[$selected_department] . ")" 
        : "";
    
    print "<br><strong>Showing files for: $current_sprog$dept_info</strong><br><br></td></tr>";
    
    print "<tr><td colspan=\"2\">&nbsp;</td><td align=\"justify\">$font ".findtekst('1770|You have the option to upload a full page in PDF format as background for all forms or specifically for quotes, orders and invoices.', $sprog_id)."<br>";
    print "<br>".findtekst('1771|It is also possible to upload an attachment in PDF format, as an attached file in email for quotes, orders and invoices.', $sprog_id)."<br>";
    print "<br>".findtekst('1772|Use e.g.', $sprog_id)." <a href=\"http://da.libreoffice.org\" target=\"blank\">Libre Office</a> ".findtekst('1773|which can save directly to PDF.', $sprog_id);
    print "<br>".findtekst('1774|The size of the PDF must be max 10mb.', $sprog_id)."<br><br></td><td colspan=\"4\">&nbsp;</td></tr>";
    print "<tr><td>&nbsp;</td><td colspan=\"5\" align=\"center\">$font<hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
    print "</tbody>";
    
    // Check if user can upload to selected department
    $can_upload = ($is_admin || $selected_department == $user_assigned_department);
    
    if ($can_upload) {
        print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
        print "<tbody>";
        print "<tr><td>&nbsp;";
        print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
        print "<input type=\"hidden\" name=\"filtype\" value='PDF'>";
        print "<input type=\"hidden\" name=\"sprog_valg\" value=\"$current_sprog\">";
        print "<input type=\"hidden\" name=\"department\" value=\"$selected_department\"></td>";
        print "<td align=left>$font ".findtekst('1775|Select PDF file for background for:', $sprog_id)."</td>";
        
        print "<td><select name=\"bg_valg\">
                    <option value=\"bg\">".findtekst('1776|All forms', $sprog_id)."</option>
                    <option value=\"tilbud_bg\">".findtekst('812|Quotes', $sprog_id)."</option>
                    <option value=\"ordrer_bg\">".findtekst('107|Orders', $sprog_id)."</option>
                    <option value=\"faktura_bg\">".findtekst('1777|Invoices', $sprog_id)."</option>
                </select>";
        print "<input name=\"uploadedfile\" type=\"file\" /><br /></td>";
        print "<td>$font ".findtekst('1776|All forms', $sprog_id)."</td><td>$font $bg&nbsp;</td><td>$font $slet_bg&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$font ".findtekst('812|Quotes', $sprog_id)."</td><td>$font $tilbud_bg&nbsp;</td><td>$font $slet_tilbud_bg&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$font ".findtekst('107|Orders', $sprog_id)."</td><td>$font $ordrer_bg&nbsp;</td><td>$font $slet_ordrer_bg&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td>&nbsp;</td><td align=center><input class='button green medium' type=\"submit\" name=\"bgfil\" value=\"".findtekst('1360|Load', $sprog_id)."\"></td><td>$font ".findtekst('1777|Invoices', $sprog_id)."</td><td>$font $faktura_bg&nbsp;</td><td>$font $slet_faktura_bg&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td colspan=\"5\" align=\"center\"><br><hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
        print "</tbody>";
        print "</form>";
        
        print "<form enctype=\"multipart/form-data\" action=\"logoupload.php\" method=\"POST\">";
        print "<tbody>";
        print "<tr><td>&nbsp;";
        print "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"10000000\">";
        print "<input type=\"hidden\" name=\"filtype\" value='logo'>";
        print "<input type=\"hidden\" name=\"sprog_valg\" value=\"$current_sprog\">";
        print "<input type=\"hidden\" name=\"department\" value=\"$selected_department\"></td>";
        print "<td align=left>$font ".findtekst('1778|Select PDF as attachment in email to:', $sprog_id)."</td>";
        
        print "<td><select name=\"bilag_valg\">
                    <option value=\"tilbud_bilag\">".findtekst('812|Quotes', $sprog_id)."</option>
                    <option value=\"ordrer_bilag\">".findtekst('107|Orders', $sprog_id)."</option>
                    <option value=\"faktura_bilag\">".findtekst('1777|Invoices', $sprog_id)."</option>
                </select>";
        print "<input name=\"uploadedfile\" type=\"file\" /><br /></td>";
        print "<td>$font ".findtekst('812|Quotes', $sprog_id)."</td><td>$font $tilbud_bilag&nbsp;</td><td>$font $slet_tilbud_bilag&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>$font ".findtekst('107|Orders', $sprog_id)."</td><td>$font $ordrer_bilag&nbsp;</td><td>$font $slet_ordrer_bilag&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td>&nbsp;</td><td align=\"center\"><input class='button green medium' type=\"submit\" name=\"bilagfil\" value=\"".findtekst('1360|Load', $sprog_id)."\"></td><td width=5%>$font ".findtekst('1777|Invoices', $sprog_id).":</td><td>$font $faktura_bilag&nbsp;</td><td>$font $slet_faktura_bilag&nbsp;</td><td>&nbsp;</td></tr>";
        print "<tr><td>&nbsp;</td><td colspan=\"5\" align=\"center\"><br><hr width=\"100%\"><br></td><td>&nbsp;</td></tr>";
        print "</tbody>";
        print "</form>";
    } else {
        // User doesn't have permission to upload
        print "<tr><td colspan=\"7\" align=\"center\"><br>";
        print "<div style=\"color: red; font-weight: bold;\">";
        print 'You do not have permission to upload or delete files in this department';
        print "</div><br></td></tr>";
    }
    
    print "</table>";
    print "</td></tr>";
}



print "</tbody></table>";
print "</td></tr>";

?>
