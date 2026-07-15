<?php
// -----------includes/std_func.php-------lap 3.1.1----2011-01-06-----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg

// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2011 DANOSOFT ApS
// ----------------------------------------------------------------------
if (!function_exists('spec_1')) {
	function spec_1($fokus,$id,$kode)	{
		// Studenterhuset ved københavns universitet
		// Tildeling af rabat til studerende ud fra studenterkort.
		// kaldes fra debitor/pos_ordre.php
		if ($kode && strlen($kode)==12) {
			$kontrolciffer=substr($kode,0,1)*4;
			$kontrolciffer+=substr($kode,1,1)*3;
			$kontrolciffer+=substr($kode,2,1)*2;
			$kontrolciffer+=substr($kode,3,1)*7;
			$kontrolciffer+=substr($kode,4,1)*6;
			$kontrolciffer+=substr($kode,5,1)*5;
			$kontrolciffer+=substr($kode,6,1)*4;
			$kontrolciffer+=substr($kode,7,1)*3;
			$kontrolciffer+=substr($kode,8,1)*2;
			$kontrolciffer+=substr($kode,9,1)*1;
			$kontrolciffer/=11;
			$fakultet=substr($kode,10,2)*1;
			if ($kontrolciffer==round($kontrolciffer,0) && $fakultet>=1 && $fakultet<=15) {
				if ($r=db_fetch_array(db_select("select id from adresser where kontonr = '1000' and art = 'D'",__FILE__ . " linje " . __LINE__))) {
					return($r['id']);
				}	else  return ("Kontonummer 1000 for \"Debitor studerende ved KU\" ikke fundet");
			} else return ("Stregkode ikke gyldig for studerende, rabat kan ikke gives");	
		} else {
			print "<form name=spec_1 action=pos_ordre.php?id=$id&spec_func=spec_1 method=post>\n";
			print "<tr><td><input type=\"text\" name=\"kode\"></td>
							<td><a style=\"text-decoration:none\" href=pos_ordre.php?id=$id>
							<input type=\"button\" style=\"width:100px;height:40px;text-align:center;font-size:25px; background-color:#FF0000;\" value= \"Fortryd\">
						</a></td></tr>";
			print "<td colspan=\"1\"><input STYLE=\"width: 100%;height: 0.01em;\" type=submit name=\"OK\" value=\"\"></td></tr>\n";
			print "<script language=\"javascript\">";
			print "document.spec_1.kode.focus();";
			print "</script>";
			exit;
		}
	}
}

