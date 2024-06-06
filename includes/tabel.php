<?php

// -------------------------tidsreg/operation1.php---1.1.0 -------------------------
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
// Copyright (c) 2000-2007 A. B. Jensens Maskinfabrik A/S
// ----------------------------------------------------------------------

function tabeller ($query,$header,$ny_side,$naeste_side,$print_table) {
global $person;
global $operation;
global $ordrenr;
global $ordre_id;

global $sql_array;

$result = db_select($query);
if ($result) {
	print("<br><TABLE BORDER=\"1\">\n");
	for ($header1 = 0; $header1 < count($header); ++$header1){
		if($header1==0) print("<TR>\n");
		print("<TD>$header[$header1]</TD>\n");
		if($header1 == count($header)) print("</TR>\n");
	}
	$antal_felter=db_num_fields($result);
	for ($raekke = 1; $r = db_fetch_row($result); ++$raekke){
		for ($felter = 1;$felter<=$antal_felter; ++$felter){
			if($felter==1 and $print_table=="ja") print("<TR>\n");
			if($print_table=="ja"){
				if($felter==1 and $ny_side == "ja"){
					print("<TR>\n");
					$vaerdi=$r[$felter-1];
					print("<TD><A HREF=\"$naeste_side?id=$vaerdi&person=$person&operation=$operation&ordrenr=$ordrenr\">$vaerdi</A></TD>\n");
				}
				else {
					$vaerdi=$r[$felter-1];
					print("<TD>$vaerdi</TD>");
				}
			}
			$vaerdi=$r[$felter-1];
			$sql_array[$raekke][$felter]=$vaerdi;
	
			if($antal_felter == $felter) print("</TR>\n");
		}
	}
	print("</TABLE>");
}
}
?>