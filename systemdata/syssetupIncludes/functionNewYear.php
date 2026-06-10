<?php
function nytaar($beskrivelse,$kodenr,$kode,$art,$box1,$box2,$box3,$box4,$box5,$box6) {
	$query = db_select("SELECT id FROM grupper WHERE art = 'RA'",__FILE__ . " linje " . __LINE__);
	print "<form name=nytaar action=syssetup.php method=post>";
	print "<tr><td colspan=4 align = center><big><b>".findtekst(1002,$sprog_id)." $beskrivelse</td></tr>\n";
	if (!$row = db_fetch_array($query)) {
		print "<tr><td colspan=2 align=\"center\"> ".findtekst(999,$sprog_id)."</td><td align = center>".findtekst(1000,$sprog_id)."</td><td align = center>".findtekst(1001,$sprog_id)."</td></tr>\n";
		$query = db_select("SELECT id, kontonr,beskrivelse FROM kontoplan WHERE kontotype='D' or kontotype='S' order by kontonr",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			print "<tr><input type=hidden name=kontonr[$y] value=$row[kontonr]><td>$row[kontonr]</td><td>$row[beskrivelse]</td><td width=10 align=right><input class=\"inputbox\" type=\"text\" size=10 name=debet[$y]></td><td align=right><input class=\"inputbox\" type=\"text\" size=10 name=kredit[$y]></td></tr>\n";
		}
	} else {
		print "<tr><td> ".findtekst(1003,$sprog_id)."</td><td><input class=\"inputbox\" type=\"checkbox\" name=aabn_bal></td></tr>\n";
	}
	print "<tr><td colspan = 4 align = center><input type=submit accesskey=\"g\" value=\"".findtekst(471,$sprog_id)."\" name=\"submit\"></td></tr>\n";
	print "</form>";
	exit;
}
?>
