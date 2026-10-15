<?php
// -------- sager/cvrLookupProxy.php --- ver 5.0.0 --- 2026-10-03 --------
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
// Copyright (c) 2004-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Extract the shared CVR lookup while preserving web session authorization.

/**
 * Injected by includes/online.php, included below:
 * @var string $brugernavn
 * @var string $db
 */
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

	$bg="nix";
	$header='nix';

	$modulnr=0;
	ob_start();	# online.php skriver html - den maa ikke havne i json-svaret
	include(__DIR__ . "/../includes/connect.php");
	include(__DIR__ . "/../includes/online.php");
	include(__DIR__ . "/../includes/std_func.php");
	ob_end_clean();

	header('Content-Type: application/json; charset=utf-8');

	# online.php only rejects anonymous calls when $nextver is unset, so the login is
	# checked explicitly here - otherwise anyone could use the proxy to call cvrapi.dk.
	if (empty($brugernavn) || empty($db)) {
		http_response_code(403);
		print json_encode(array('error'=>'not authenticated'));
		exit;
	}


    require_once __DIR__ . '/../includes/stdFunc/cvrLookup.php';
    $type = ifset($_GET, 'type', 'vat');
    $param = ifset($_GET, 'param', '');
    $country = ifset($_GET, 'country', 'dk');
    if (!is_string($type) || !is_string($param) || !is_string($country)) {
        http_response_code(400);
        print json_encode(['error'=>'invalid parameters']);
        exit;
    }
    session_write_close();
    $result = cvr_lookup($type, $param, $country);
    http_response_code($result['status']);
    print $result['raw'] ?? json_encode($result['body']);
