<?php
// --------------------lager(enheder.php ---------lap 2.0--2008.04.28---------
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
// Copyright (c) 2004-2008 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20260920 CDX/LUI Reuse current formatters and normalize PHP 8 form ids and result arrays.

	@session_start();
	$s_id=session_id();
 
	$modulnr=9;
 	$title="Enheder / materialer";
 
	include(__DIR__ . "/../includes/connect.php");
	include(__DIR__ . "/../includes/online.php");
	require_once(__DIR__ . "/../includes/std_func.php");
#	include("../includes/db_query.php");

	if(ifset($_GET, 'returside')){
		$returside= $_GET['returside'];
		$ordre_id = (int)ifset($_GET, 'ordre_id', 0);
		$fokus = ifset($_GET, 'fokus', '');
	}
	else {$returside="kreditor.php";}

	$enh_ret_id = (int)ifset($_GET, 'enh_id', 0);
	$mat_ret_id = (int)ifset($_GET, 'mat_id', 0);

	if (ifset($_POST, 'enheder')){
		$enh_id = (int)ifset($_POST, 'enh_id', 0);
		$enh_betegnelse = array_map('db_escape_string', (array)ifset($_POST, 'enh_betegnelse', []));
		$enh_betegnelse += [0 => '', $enh_id => ''];
		$enh_beskrivelse = array_map('db_escape_string', (array)ifset($_POST, 'enh_beskrivelse', []));
		$enh_beskrivelse += [0 => '', $enh_id => ''];

		$enh_betegnelse[0]=trim($enh_betegnelse[0]);
		$enh_beskrivelse[$enh_id]=trim($enh_beskrivelse[$enh_id]);
		$enh_beskrivelse[0]=trim($enh_beskrivelse[0]);
		
		if ($enh_betegnelse[0]){
			$query = db_select("select id from enheder where betegnelse = '$enh_betegnelse[0]'", __FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			if (!empty($row['id'])){
				echo "<big><b>Der findes allerede en enhed med betegnelsen: $enh_betegnelse[0]</b></big><br><br>";
			}
			else{
				db_modify("insert into enheder (betegnelse, beskrivelse) values ('$enh_betegnelse[0]', '$enh_beskrivelse[0]')", __FILE__ . " linje " . __LINE__);
			}
		}
		elseif ($enh_id > 0 && $enh_betegnelse[$enh_id] && $enh_betegnelse[$enh_id] != "-"){
			db_modify("update enheder set betegnelse = '$enh_betegnelse[$enh_id]', beskrivelse = '$enh_beskrivelse[$enh_id]' where id = '$enh_id'", __FILE__ . " linje " . __LINE__);
		}
		elseif ($enh_id > 0 && $enh_betegnelse[$enh_id] == "-"){
			db_modify("delete from enheder where id = '$enh_id'", __FILE__ . " linje " . __LINE__);
		}
	}

	if (ifset($_POST, 'materialer')){
		$mat_id = (int)ifset($_POST, 'mat_id', 0);
		$mat_beskrivelse = array_map('db_escape_string', (array)ifset($_POST, 'mat_beskrivelse', []));
		$mat_beskrivelse += [0 => '', $mat_id => ''];
		$mat_densitet = (array)ifset($_POST, 'mat_densitet', []);
		$mat_densitet += [0 => '', $mat_id => ''];
			
		$mat_beskrivelse[0]=trim($mat_beskrivelse[0]);
		$mat_beskrivelse[$mat_id]=trim($mat_beskrivelse[$mat_id]);
 
#		$mat_densitet[0]=+$mat_densitet[0]; Remmet 150606 - kan ikke lige gennemskue hvorfor de har væred her!! ??
#		$mat_densitet[$mat_id]=+$mat_densitet[$mat_id];
		if (($mat_beskrivelse[0])&&($mat_densitet[0])){
			$mat_densitet[0]=usdecimal($mat_densitet[0]);
			$query = db_select("select id from materialer where beskrivelse = '$mat_beskrivelse[0]'", __FILE__ . " linje " . __LINE__);
			$row = db_fetch_array($query);
			if (!empty($row['id'])) echo "<big><b>Der findes allerede et materiale med beskrivelsen: '$mat_beskrivelse[0]'</b></big><br><br>";
			else 	db_modify("insert into materialer (beskrivelse, densitet) values ('$mat_beskrivelse[0]', '$mat_densitet[0]')", __FILE__ . " linje " . __LINE__);
		}
		elseif (($mat_id > 0)&&($mat_beskrivelse[$mat_id])){
			$mat_densitet[$mat_id]=usdecimal($mat_densitet[$mat_id]);
			db_modify("update materialer set beskrivelse = '$mat_beskrivelse[$mat_id]', densitet = '$mat_densitet[$mat_id]' where id = '$mat_id'", __FILE__ . " linje " . __LINE__);
		}
		elseif (($mat_id > 0)&&(!$mat_beskrivelse[$mat_id])) db_modify("delete from materialer where id = '$mat_id'", __FILE__ . " linje " . __LINE__);
	}

	print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
	print "<tr><td align=\"center\" valign=\"top\">";
	print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
	print "<td width=\"10%\" $top_bund>$font<small><a href=../includes/luk.php accesskey=L>Luk</a></small></td>";
	print "<td width=\"80%\" $top_bund>$font<small>Enheder & materialer</small></td>";
	print "<td width=\"10%\" $top_bund><br></td>";
	print "</tbody></table>";
	print "</td></tr>";
	print "<td align = center valign = center>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" valign=top><tbody>";

	$enh_id = $enh_betegnelse = $enh_beskrivelse = [];
	$mat_id = $mat_beskrivelse = $mat_densitet = [];
	$x=0;
	$query = db_select("select * from enheder order by betegnelse", __FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query))
	{
		$x++;
		$enh_id[$x]=$row['id'];
		$enh_betegnelse[$x]=$row['betegnelse'];
		$enh_beskrivelse[$x]=$row['beskrivelse'];
	}
	$enh_antal=$x;

	$x=0;
	$query = db_select("select * from materialer order by beskrivelse", __FILE__ . " linje " . __LINE__);
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
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<form name=enheder action=enheder.php method=post>";


	print "<tr><td align=center valign=top>$font Enhed</td><td align=center valign=top>$font Beskrivelse</td></tr>";
	for ($x=1; $x<=$max_antal; $x++)
	{
		if (isset($enh_id[$x])) {print "<tr><td><a href=enheder.php?enh_id=$enh_id[$x]>$font $enh_betegnelse[$x]</a></td><td>$font $enh_beskrivelse[$x]</td></tr>";}
		else {print "<tr><td><br></td></tr>";}
	}

	if ($enh_ret_id)
	{
		$query = db_select("select * from enheder where id = $enh_ret_id", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$enh_betegnelse[$enh_ret_id]=$row['betegnelse'];
		$enh_beskrivelse[$enh_ret_id]=$row['beskrivelse'];
		print "<input type=hidden name=enh_id value=$enh_ret_id>";
		print "<tr><td><input type=text size=3 name=enh_betegnelse[$enh_ret_id] value=$enh_betegnelse[$enh_ret_id]></td><td><input type=text size=25 name=enh_beskrivelse[$enh_ret_id] value='$enh_beskrivelse[$enh_ret_id]'></td></tr>";
	}
	else {print "<tr><td><input type=text size=3 name=enh_betegnelse[0]></td><td><input type=text size=25 name=enh_beskrivelse[0]></td></tr>";}

	print "<tr><td align = center colspan=2><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"enheder\"></td></tr>";
	print "</tbody></table border=1>";

	print "<td width=50% valign=top><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<form name=materialer action=enheder.php method=post>";


	print "<tr><td align=center valign=top>$font Materiale</td><td align=center valign=top>$font Densitet</td></tr>";
	for ($x=1; $x<=$max_antal; $x++)
	{
		if (isset($mat_id[$x])) {print "<tr><td>$font $mat_beskrivelse[$x]</td><td><a href=enheder.php?mat_id=$mat_id[$x]>$font $mat_densitet[$x]</a></td></tr>";}
		else {print "<tr><td><br></td></tr>";}
	}
	if ($mat_ret_id)
	{
		$query = db_select("select * from materialer where id = $mat_ret_id", __FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$mat_beskrivelse[$mat_ret_id]=$row['beskrivelse'];
		$mat_densitet[$mat_ret_id]=dkdecimal($row['densitet']);
		print "<input type=hidden name=mat_id value=$mat_ret_id>";
		print "<tr><td><input type=text size=25 name=mat_beskrivelse[$mat_ret_id] value='$mat_beskrivelse[$mat_ret_id]'></td><td><input type=text size=3 name=mat_densitet[$mat_ret_id] value=$mat_densitet[$mat_ret_id]	></td><tr>";
	}
	else {print "<tr><td><input type=text size=25 name=mat_beskrivelse[0]></td><td><input type=text size=3 name=mat_densitet[0]></td><tr>";}

	print "<tr><td align = center colspan=2><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"materialer\"></td></tr>";
	print "</tbody></table>";
	print "</tbody></table>";


?>
</td></tr>
<tr><td align = "center" valign = "bottom">
		<table width="100%" align="center" border="1" cellspacing="0" cellpadding="0"><tbody>
			<td width="100%" bgcolor="<?php echo $bgcolor2 ?>"><font face="Helvetica, Arial, sans-serif" color="#000066"><small><br></small></td>
		</tbody></table>
</td></tr>
</tbody></table>
</body></html>
