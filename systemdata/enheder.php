<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/enheder.php --- lap 3.9.9 --- 2021-02-11 ---
// LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2003-2021 saldi.dk aps
// ----------------------------------------------------------------------
// 2019.02.25 MSC - Rettet topmenu design til og isset fejl
// 2021.02.11 PHR	- Some cleanup

	@session_start();
	$s_id=session_id();
 	$css="../css/standard.css";
	
	$modulnr=9;
 	$title="Enheder / materialer";
 
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");

	if ($menu=='T') {
			$border="0";
			include_once '../includes/top_header.php';
			include_once '../includes/top_menu.php';
			print "<div id=\"header\">\n";
			print "<div class=\"headerbtnLft\"></div>\n";
			print "</div><!-- end of header -->";
			print "<div id=\"leftmenuholder\">";
			include_once 'left_menu.php';
			print "</div><!-- end of leftmenuholder -->\n";
			print "<div class=\"maincontentLargeHolder\">\n";
	} else {
		include("top.php");
		$border="1";
	}

	if (!isset ($_GET['returside'])) $_GET['returside'] = null;
	if (!isset ($_GET['enh_id'])) $_GET['enh_id'] = null;
	if (!isset ($_GET['mat_id'])) $_GET['mat_id'] = null;
	if (!isset ($_POST['enheder'])) $_POST['enheder'] = null;
	if (!isset ($_POST['materialer'])) $_POST['materialer'] = null;
	if (!isset ($mat_id)) $mat_id = null;
	if (!isset ($_POST['mat_id'])) $_POST['mat_id'] = null;
	if (!isset ($_POST['enh_id'])) $_POST['enh_id'] = null;
	
	if($_GET['returside']){
		$returside= $_GET['returside'];
		$ordre_id = $_GET['ordre_id'];
		$fokus = $_GET['fokus'];
	}
	else {$returside="kreditor.php";}

	$enh_ret_id = $_GET['enh_id'];
	$mat_ret_id = $_GET['mat_id'];

	if ($_POST['enheder']){
		$enh_id          = $_POST['enh_id'];
		$enh_betegnelse  = $_POST['enh_betegnelse'];
		$enh_beskrivelse = $_POST['enh_beskrivelse'];

		
		if (isset($enh_betegnelse[0]) && $enh_betegnelse[0]) {
			$query = db_select("select id from enheder where betegnelse = '$enh_betegnelse[0]'",__FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			if ($row['id']){
				echo "<big><b>".findtekst('2394|Der findes allerede en enhed med betegnelsen', $sprog_id).": $enh_betegnelse[0]</b></big><br><br>";
			}
			else{
				$qtxt = "insert into enheder (betegnelse, beskrivelse) values ('$enh_betegnelse[0]', '$enh_beskrivelse[0]')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		elseif ($enh_id > 0 && $enh_betegnelse[$enh_id] && $enh_betegnelse[$enh_id] != "-"){
			$qtxt = "update enheder set betegnelse = '$enh_betegnelse[$enh_id]', beskrivelse = '$enh_beskrivelse[$enh_id]' where id = '$enh_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		elseif ($enh_id > 0 && $enh_betegnelse[$enh_id] == "-"){
			db_modify("delete from enheder where id = '$enh_id'",__FILE__ . " linje " . __LINE__);
		}
	}

	if ($_POST['materialer']){
		$mat_id=$_POST['mat_id'];
		$mat_beskrivelse=$_POST['mat_beskrivelse'];
		$mat_densitet=$_POST['mat_densitet'];
			
		$mat_beskrivelse[0]=trim($mat_beskrivelse[0]);
		$mat_beskrivelse[$mat_id]=trim(if_isset($mat_beskrivelse[$mat_id]));
 
		if (($mat_beskrivelse[0])&&($mat_densitet[0])){
			$mat_densitet[0]=usdecimal($mat_densitet[0]);
			$q = db_select("select id from materialer where beskrivelse = '$mat_beskrivelse[0]'",__FILE__ . " linje " . __LINE__);
			if ($r = db_fetch_array($q)) {
			 echo "<big><b>".findtekst('2395|Der findes allerede et materiale med beskrivelsen', $sprog_id).": '$mat_beskrivelse[0]'</b></big><br><br>";
			} else {
				$qtxt="insert into materialer (beskrivelse, densitet) values ('$mat_beskrivelse[0]', '$mat_densitet[0]')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		elseif ($mat_id > 0 && $mat_beskrivelse[$mat_id]) {
			$mat_densitet[$mat_id]=usdecimal($mat_densitet[$mat_id]);
			$qtxt = "update materialer set beskrivelse = '$mat_beskrivelse[$mat_id]', densitet = '$mat_densitet[$mat_id]' where id = '$mat_id'";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
		elseif ($mat_id > 0 && !$mat_beskrivelse[$mat_id]) {
			db_modify("delete from materialer where id = '$mat_id'",__FILE__ . " linje " . __LINE__);
		}
	}
/*
	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund><a href=../includes/luk.php accesskey=L>Luk</a></td>";
	print "<td width=\"80%\" $top_bund>Enheder & materialer</td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
*/
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" valign=top><tbody>";
	$x=0;
	$query = db_select("select * from enheder order by betegnelse",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		$x++;
		$enh_id[$x]=$row['id'];
		$enh_betegnelse[$x]=$row['betegnelse'];
		$enh_beskrivelse[$x]=$row['beskrivelse'];
	}
	$enh_antal=$x;

	$x=0;
	$query = db_select("select * from materialer order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		$x++;
		$mat_id[$x]=$row['id'];
		$mat_beskrivelse[$x]=$row['beskrivelse'];
		$mat_densitet[$x]=dkdecimal($row['densitet']);
	}
	$mat_antal=$x;
	if ($enh_antal >= $mat_antal){$max_antal=$enh_antal;}
	else {$max_antal=$mat_antal;}

	print "<td width=50% valign=top>";
	print "<table class='dataTable' cellpadding=\"1\" cellspacing=\"1\" border=\"$border\"><tbody>";
	print "<form name=enheder action=enheder.php method=post>";


	print "<tr><td align=center valign=top class='tableHeader'> ".findtekst('945|Enhed', $sprog_id)."</td><td align=center valign=top class='tableHeader'>".findtekst('914|Beskrivelse', $sprog_id)."</td></tr>";
	for ($x=1; $x<=$max_antal; $x++)
	{
		if ($enh_id[$x]) {print "<tr><td><a href=enheder.php?enh_id=$enh_id[$x]> $enh_betegnelse[$x]</a></td><td> $enh_beskrivelse[$x]</td></tr>";}
		else {print "<tr><td><br></td></tr>";}
	}

	if ($enh_ret_id)
	{
		$query = db_select("select * from enheder where id = $enh_ret_id",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$enh_betegnelse[$enh_ret_id]=$row['betegnelse'];
		$enh_beskrivelse[$enh_ret_id]=$row['beskrivelse'];
		print "<input type=hidden name=enh_id value=$enh_ret_id>";
		print "<tr><td><input type=text size=3 name=enh_betegnelse[$enh_ret_id] value=$enh_betegnelse[$enh_ret_id]></td><td><input type=text size=25 name=enh_beskrivelse[$enh_ret_id] value='$enh_beskrivelse[$enh_ret_id]'></td></tr>";
	}
	else {print "<tr><td><input type=text size=3 name=enh_betegnelse[0]></td><td><input type=text size=25 name=enh_beskrivelse[0]></td></tr>";}

	print "<tr><td align = center colspan=2><input class='button green medium' type=submit accesskey=\"g\" value=\"".findtekst('471|Gem/opdatér', $sprog_id)."\" name=\"enheder\"></td></tr>";
	print "</tbody></table border=1>";

	print "<td width=50% valign=top><table class='dataTable' cellpadding=\"1\" cellspacing=\"1\" border=\"$border\"><tbody>";
	print "<form name=materialer action=enheder.php method=post>";


	print "<tr><td align=center valign=top class='tableHeader'> ".findtekst('570|Materiale', $sprog_id)."</td><td align=center valign=top class='tableHeader'>".findtekst('569|Densitet', $sprog_id)."</td></tr>";
	for ($x=1; $x<=$max_antal; $x++) {
		if (isset($mat_id[$x])) {
			print "<tr><td> $mat_beskrivelse[$x]</td><td><a href=enheder.php?mat_id=$mat_id[$x]> $mat_densitet[$x]</a></td></tr>";
		} else print "<tr><td><br></td></tr>";
	}
	if ($mat_ret_id)
	{
		$query = db_select("select * from materialer where id = $mat_ret_id",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$mat_beskrivelse[$mat_ret_id]=$row['beskrivelse'];
		$mat_densitet[$mat_ret_id]=dkdecimal($row['densitet']);
		print "<input type=hidden name=mat_id value=$mat_ret_id>";
		print "<tr><td><input type=text size=25 name=mat_beskrivelse[$mat_ret_id] value='$mat_beskrivelse[$mat_ret_id]'></td><td><input type=text size=3 name=mat_densitet[$mat_ret_id] value=$mat_densitet[$mat_ret_id]	></td><tr>";
	}
	else {print "<tr><td><input type=text size=25 name=mat_beskrivelse[0]></td><td><input type=text size=3 name=mat_densitet[0]></td><tr>";}

	print "<tr><td align = center colspan=2><input class='button green medium' type=submit accesskey=\"g\" value=\"".findtekst('471|Gem/opdatér', $sprog_id)."\" name=\"materialer\"></td></tr>";
	print "</tbody></table>";
	print "</tbody></table>";


?>
</td></tr>
</tbody></table>
</body></html>
