<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ----------/admin/master_warning_info.php---lap 4.3.0--2026-07-17--------
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
// 20260717 CL/NTR New. JSON endpoint for the vis_regnskaber.php master warning:
//                  returns the regnskab db name, its db version and master's version.
@session_start();
$s_id=session_id();

// connect.php and online.php print page chrome (an HTML head/body, a button-colour <style>
// block) plus trailing whitespace. Buffer everything they emit and discard it, so only the
// json_encode() below reaches the client and the response stays valid JSON.
ob_start();
include(__DIR__ . "/../includes/connect.php");
include(__DIR__ . "/../includes/online.php");
ob_end_clean();

global $version;

header('Content-Type: application/json; charset=UTF-8');

// Same guard as the pages: the master session must be on the master db.
if ($db != $sqdb) {
	echo json_encode(array('error' => 'Adgang nægtet'));
	exit;
}

$db_id = isset($_GET['db_id']) ? intval($_GET['db_id']) : 0;

// Only expose regnskaber the current user may actually open (admin or explicit access),
// mirroring the access check in vis_regnskaber.php.
$r = db_fetch_array(db_select("select rettigheder from brugere where brugernavn = '$brugernavn'", __FILE__ . " linje " . __LINE__));
list($admin,$oprette,$slette,$tmp) = explode(",", $r['rettigheder'], 4);
$adgang_til = explode(",", $tmp);
if (!$admin && !in_array($db_id, $adgang_til)) {
	echo json_encode(array('error' => 'Adgang nægtet'));
	exit;
}

$r = db_fetch_array(db_select("select db, regnskab from regnskab where id = '$db_id'", __FILE__ . " linje " . __LINE__));
if (!$r || !$r['db']) {
	echo json_encode(array('error' => 'Regnskab ikke fundet'));
	exit;
}
$db_navn = $r['db'];

$dbver = 'ukendt'; # stays "ukendt" if the version can't be read
if (db_exists($db_navn)) {
	db_connect("$sqhost", "$squser", "$sqpass", "$db_navn", __FILE__ . " linje " . __LINE__);
	$rv = db_fetch_array(db_select("select box1 from grupper where art = 'VE'", __FILE__ . " linje " . __LINE__));
	if ($rv && $rv['box1']) $dbver = $rv['box1'];
	$connection = db_connect("$sqhost", "$squser", "$sqpass", "$sqdb", __FILE__ . " linje " . __LINE__); # back to master (no output)
}

echo json_encode(array(
	'db'     => $db_navn,
	'dbver'  => $dbver,
	'master' => $version,
));
exit;
?>
