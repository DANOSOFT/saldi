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
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	$bg="nix";
	$header='nix';

	$modulnr=0;
	ob_start();	# online.php skriver html - den maa ikke havne i json-svaret
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
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

	$url = "https://cvrapi.dk/api?".$type."=".urlencode($param)."&country=".urlencode($country);

	$ch = curl_init($url);
	curl_setopt_array($ch,array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT        => 10,
		CURLOPT_USERAGENT      => 'saldi.dk - kundeopslag (support@saldi.dk)',
		CURLOPT_HTTPHEADER     => array('Accept: application/json'),
	));

	$body = curl_exec($ch);
	$code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	curl_close($ch);

	if ($body === false || $code >= 400) {
		# curl_error() can reveal internal network details, so it is logged instead of
		# being sent to the browser.
		if ($err) error_log("cvrLookupProxy: call to cvrapi.dk failed (status $code): $err");
		http_response_code($code ? $code : 502);
		print json_encode(array('error'=>'upstream error','status'=>$code));
		exit;
	}

	print $body;
?>
