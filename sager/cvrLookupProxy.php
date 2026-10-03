<?php
// -------- sager/cvrLookupProxy.php ----------------------------------------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2026 saldi.dk aps
// ----------------------------------------------------------------------
// 20261003 CL/SZ SD-721 The call to cvrapi.dk is includes/cvrLookup.php's cvrLookupFetch(), which the automatic kreditor creation uses too.
//                A CVR number's answer now comes from its cache when it was looked up before.
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	$bg="nix";
	$header='nix';

	$modulnr=0;
	ob_start();	# online.php skriver html - den maa ikke havne i json-svaret
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("../includes/cvrLookup.php");
	ob_end_clean();

	header('Content-Type: application/json; charset=utf-8');

	# online.php only rejects anonymous calls when $nextver is unset, so the login is
	# checked explicitly here - otherwise anyone could use the proxy to call cvrapi.dk.
	if (empty($brugernavn) || empty($db)) {
		http_response_code(403);
		print json_encode(array('error'=>'not authenticated'));
		exit;
	}

	$type    = if_isset($_GET,'vat','type');
	$param   = if_isset($_GET,'','param');
	$country = if_isset($_GET,'dk','country');

	if (!in_array($type,array('vat','phone'),true) || !preg_match('/^\d{8}$/',$param) || !preg_match('/^[a-z]{2}$/',$country)) {
		http_response_code(400);
		print json_encode(array('error'=>'invalid parameters'));
		exit;
	}

	# session_start() holds the session lock until this script ends, and nothing below writes
	# to the session. Released here so a slow cvrapi.dk call cannot block the user's other
	# requests for up to the 10 second timeout.
	session_write_close();

	$answer = cvrLookupFetch($type,$param,$country,10);
	$code = $answer['code'];

	if ($answer['body'] === null || $code >= 400) {
		http_response_code($code ? $code : 502);
		print json_encode(array('error'=>'upstream error','status'=>$code));
		exit;
	}

	$body = $answer['body'];
	print $body;
?>
