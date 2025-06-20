<?php
// ----------------systemdata/enheder.php---------lap 2.0.7--2009-05-15---------
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
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

	@session_start();
	$s_id=session_id();
 	$css="../css/standard.css";
	
	$modulnr=9;
 	$title="Enheder / materialer";
 
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	include("top.php");

	
	if($_GET['returside']){
		$returside= $_GET['returside'];
		$ordre_id = $_GET['ordre_id'];
		$fokus = $_GET['fokus'];
	}
	else {$returside="kreditor.php";}

	$enh_ret_id = $_GET[enh_id];
	$mat_ret_id = $_GET[mat_id];

	if ($_POST['enheder']){
		$enh_id=$_POST[enh_id];
		$enh_betegnelse=$_POST['enh_betegnelse'];
		$enh_beskrivelse=$_POST['enh_beskrivelse'];

		$enh_betegnelse[0]=trim($enh_betegnelse[0]);
		$enh_beskrivelse[$enh_id]=trim($enh_beskrivelse[$enh_id]);
		$enh_beskrivelse[0]=trim($enh_beskrivelse[0]);
		
		if ($enh_betegnelse[0]){
			$query = db_select("select id from enheder where betegnelse = '$enh_betegnelse[0]'");
			$row = db_fetch_array($query);
			if ($row[id]){
				echo "<big><b>Der findes allerede en enhed med betegnelsen: $enh_betegnelse[0]</b></big><br><br>";
			}
			else{
				db_modify("insert into enheder (betegnelse, beskrivelse) values ('$enh_betegnelse[0]', '$enh_beskrivelse[0]')");
			}
		}
		elseif ($enh_id > 0 && $enh_betegnelse[$enh_id] && $enh_betegnelse[$enh_id] != "-"){
			db_modify("update enheder set betegnelse = '$enh_betegnelse[$enh_id]', beskrivelse = '$enh_beskrivelse[$enh_id]' where id = '$enh_id'");
		}
		elseif ($enh_id > 0 && $enh_betegnelse[$enh_id] == "-"){
			db_modify("delete from enheder where id = '$enh_id'");
		}
	}

	if ($_POST['materialer']){
		$mat_id=$_POST[mat_id];
		$mat_beskrivelse=$_POST['mat_beskrivelse'];
		$mat_densitet=$_POST['mat_densitet'];
			
		$mat_beskrivelse[0]=trim($mat_beskrivelse[0]);
		$mat_beskrivelse[$mat_id]=trim($mat_beskrivelse[$mat_id]);
 
#		$mat_densitet[0]=+$mat_densitet[0]; Remmet 150606 - kan ikke lige gennemskue, hvorfor de har vaeret her!! ??
#		$mat_densitet[$mat_id]=+$mat_densitet[$mat_id];
		if (($mat_beskrivelse[0])&&($mat_densitet[0])){
			$mat_densitet[0]=usdecimal($mat_densitet[0]);
			$query = db_select("select id from materialer where beskrivelse = '$mat_beskrivelse[0]'");
			$row = db_fetch_array($query);
			if ($row[id]) echo "<big><b>Der findes allerede et materiale med beskrivelsen: '$mat_beskrivelse[0]'</b></big><br><br>";
			else 	db_modify("insert into materialer (beskrivelse, densitet) values ('$mat_beskrivelse[0]', '$mat_densitet[0]')");
		}
		elseif (($mat_id > 0)&&($mat_beskrivelse[$mat_id])){
			$mat_densitet[$mat_id]=usdecimal($mat_densitet[$mat_id]);
			db_modify("update materialer set beskrivelse = '$mat_beskrivelse[$mat_id]', densitet = '$mat_densitet[$mat_id]' where id = '$mat_id'");
		}
		elseif (($mat_id > 0)&&(!$mat_beskrivelse[$mat_id])) db_modify("delete from materialer where id = '$mat_id'");
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
	$query = db_select("select * from enheder order by betegnelse");
	while ($row = db_fetch_array($query))
	{
		$x++;
		$enh_id[$x]=$row[id];
		$enh_betegnelse[$x]=$row['betegnelse'];
		$enh_beskrivelse[$x]=$row['beskrivelse'];
	}
	$enh_antal=$x;

	$x=0;
	$query = db_select("select * from materialer order by beskrivelse");
	while ($row = db_fetch_array($query))
	{
		$x++;
		$mat_id[$x]=$row[id];
		$mat_beskrivelse[$x]=$row['beskrivelse'];
		$mat_densitet[$x]=dkdecimal($row['densitet']);
	}
	$mat_antal=$x;
	if ($enh_antal >= $mat_antal){$max_antal=$enh_antal;}
	else {$max_antal=$mat_antal;}

	print "<td width=50% valign=top>";
	print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<form name=enheder action=enheder.php method=post>";


	print "<tr><td align=center valign=top> Enhed</td><td align=center valign=top> Beskrivelse</td></tr>";
	for ($x=1; $x<=$max_antal; $x++)
	{
		if ($enh_id[$x]) {print "<tr><td><a href=enheder.php?enh_id=$enh_id[$x]> $enh_betegnelse[$x]</a></td><td> $enh_beskrivelse[$x]</td></tr>";}
		else {print "<tr><td><br></td></tr>";}
	}

	if ($enh_ret_id)
	{
		$query = db_select("select * from enheder where id = $enh_ret_id");
		$row = db_fetch_array($query);
		$enh_betegnelse[$enh_ret_id]=$row['betegnelse'];
		$enh_beskrivelse[$enh_ret_id]=$row['beskrivelse'];
		print "<input type=hidden name=enh_id value=$enh_ret_id>";
		print "<tr><td><input type=text size=3 name=enh_betegnelse[$enh_ret_id] value=$enh_betegnelse[$enh_ret_id]></td><td><input type=text size=25 name=enh_beskrivelse[$enh_ret_id] value='$enh_beskrivelse[$enh_ret_id]'></td></tr>";
	}
	else {print "<tr><td><input type=text size=3 name=enh_betegnelse[0]></td><td><input type=text size=25 name=enh_beskrivelse[0]></td></tr>";}

	print "<tr><td align = center colspan=2><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"enheder\"></td></tr>";
	print "</tbody></table border=1>";

	print "<td width=50% valign=top><table cellpadding=\"1\" cellspacing=\"1\" border=\"1\"><tbody>";
	print "<form name=materialer action=enheder.php method=post>";


	print "<tr><td align=center valign=top> Materiale</td><td align=center valign=top> Densitet</td></tr>";
	for ($x=1; $x<=$max_antal; $x++)
	{
		if ($mat_id[$x]) {print "<tr><td> $mat_beskrivelse[$x]</td><td><a href=enheder.php?mat_id=$mat_id[$x]> $mat_densitet[$x]</a></td></tr>";}
		else {print "<tr><td><br></td></tr>";}
	}
	if ($mat_ret_id)
	{
		$query = db_select("select * from materialer where id = $mat_ret_id");
		$row = db_fetch_array($query);
		$mat_beskrivelse[$mat_ret_id]=$row['beskrivelse'];
		$mat_densitet[$mat_ret_id]=dkdecimal($row['densitet']);
		print "<input type=hidden name=mat_id value=$mat_ret_id>";
		print "<tr><td><input type=text size=25 name=mat_beskrivelse[$mat_ret_id] value='$mat_beskrivelse[$mat_ret_id]'></td><td><input type=text size=3 name=mat_densitet[$mat_ret_id] value=$mat_densitet[$mat_ret_id]	></td><tr>";
	}
	else {print "<tr><td><input type=text size=25 name=mat_beskrivelse[0]></td><td><input type=text size=3 name=mat_densitet[0]></td><tr>";}

	print "<tr><td align = center colspan=2><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"materialer\"></td></tr>";
	print "</tbody></table>";
	print "</tbody></table>";


?>
</td></tr>
</tbody></table>
</body></html>
