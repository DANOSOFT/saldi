<?php
// ------------debitor/pbsfakt.php------- patch 2.1.2 ---2010.02.22------
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
// Copyright (c) 2004-2010 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20260908 CL/Sawaneh SST-763: pbsfakt() moved to includes/pbsfunc.php; this file only
//                     remains so existing include("pbsfakt.php") callers keep working.

include_once(__DIR__ . '/../includes/pbsfunc.php');
