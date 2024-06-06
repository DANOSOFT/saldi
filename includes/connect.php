<?php
// ------------------------------------------------------/includes/connect.php---------------lap 1.0.2----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2006 ITz ApS
// ----------------------------------------------------------------------

if (!isset($bg)) $bg='';
# if (!isset($title)) $title='';
$db_encode="UTF8";

if (file_exists("../includes/db_query.php")) {
	include("../includes/db_query.php");
	include("../includes/version.php");
	include("../includes/settings.php");
}
elseif (file_exists("../../includes/db_query.php")){
	include("../../includes/db_query.php");
	include("../../includes/version.php");
	include("../../includes/settings.php");
}

$sqhost = "localhost";
$squser	= "postgres";
$sqpass = "h-.[5Rn:eTYaB346";
$sqdb = "saldi";

#$login = "";
#$login = "dropdown";
$login = "cookie";
$revisorregnskab=1;
$saldiregnskab=1;

# $brug_timestamp="y";

#if ($sqpass) $connection = db_connect ("host=$sqhost dbname=$sqdb user=$squser password=$sqpass");
#else 
$connection = db_connect ("$sqhost","$squser","$sqpass","$sqdb");
if (!isset($connection))
{
	die( "Unable to connect");
}

// 170524 MMK - Added standard functions for interacting with the settings table
if(!function_exists('update_settings_value')){
	function update_settings_value($var_name, $var_grp, $var_value, $var_description) {
		# Expect a posted ID
		$qtxt = "SELECT var_value FROM settings WHERE var_name='$var_name' AND var_grp = '$var_grp'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));

		# If the row already exsists
		if ($r) {
			$qtxt = "UPDATE settings SET var_value='$var_value' WHERE var_name='$var_name' AND var_grp = '$var_grp'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		# If the row needs to be created in the database
		} else {
			$qtxt = "INSERT INTO settings(var_name, var_grp, var_value, var_description) VALUES ('$var_name', '$var_grp', '$var_value', '$var_description')";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
}

if(!function_exists('get_settings_value')){
	function get_settings_value($var_name, $var_grp, $default) {
		$qtxt = "SELECT var_value FROM settings WHERE var_name='$var_name' AND var_grp = '$var_grp'";
		$r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($r) {
			return $r[0];
		} else {
			return $default;
		}
	}
}

?>
