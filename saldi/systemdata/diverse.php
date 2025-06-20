<?php
// --------------------------------------------systemdata/diverse.php------patch1.0.8--------------------
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
// Copyright (c) 2004-2006 DANOSOFT ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$title="Diverse Indstilinger";
$modulnr=1;
;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/dkdecimal.php");
include("../includes/db_query.php");
include("top.php");


print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

if ($_POST) {
	$id=$_POST['id'];
	$beskrivelse=$_POST['beskrivelse'];
	$box1=$_POST['box1'];
	$box2=$_POST['box2'];
	$box3=$_POST['box3'];
	$box4=$_POST['box4'];

	
	for ($x=1; $x<=1; $x++) {
		if  (($id[$x]==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='$x'")))) $id[$x]=$r['id'];
		elseif ($id[$x]==0){
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3) values ('Provisionsrapport', '1', 'DIV', '$box1[$x]', '$box2[$x]', '$box3[$x]')");
		} elseif ($id[$x] > 0) db_modify("update grupper set  box1 = '$box1[$x]', box2 = '$box2[$x]', box3 = '$box3[$x]' , box4 = '$box4[$x]' where id = '$id[$x]'");
	}
}

$ref=''; $kua=''; $smart='';
$kort=''; $batch='';

$x=1;
$q = db_select("select * from grupper where art = 'DIV' and kodenr = '$x'");
$r = db_fetch_array($q);
$id[$x]=$r['id'];
$beskrivelse[$x]=$r['beskrivelse'];
$kodenr[$x]=$r['kodenr'];
$box1[$x]=$r['box1'];
$box2[$x]=$r['box2'];
$box3[$x]=$r['box3'];
$box4[$x]=$r['box4'];

if ($box1[$x]=='ref') $ref="checked";
elseif ($box1[$x]=='kua') $kua="checked";
else $smart="checked";

if ($box2[$x]=='kort') $kort="checked";
else $batch="checked";

if ($box4[$x]=='bet') $bet="checked";
else $fak="checked";


print "<form name=diverse action=diverse.php method=post>";
print "<tr><td colspan=6>$font Grundlag for provisionsberegning</td></tr>";
print "<tr><td colspan=6><hr></td></tr>";
print "<input type=hidden name=id[$x] value='$id[$x]'>";
print "<tr><td width=55%>$font<small>Beregn provision på ordrer som er faktureret eller faktureret og betalt</small></td><td></td><td width=15% align=center>$font<small>Faktureret</small></td><td width=15% align=center>$font<small>Betalt</small></td></tr>";
print "<tr><td></td><td></td><td align=center><input type=radio name=box4[$x] value=fak title='Provision beregnes på fakturerede ordrer' $fak></td><td align=center><input type=radio name=box4[$x] value=bet title= 'Provision beregnes på betalte ordrer' $bet></td></tr>";
print "<tr><td width=55%>$font<small>Kilde for personinfo</small></td><td width=15% align=center>$font<small>Ref.</small></td><td width=15% align=center>$font<small>Kundeans.</small></td><td width=15% align=center>$font<small>Begge</small></td></tr>";
print "<tr><td></td><td align=center><input type=radio name=box1[$x] value=ref title='Provision tilfalder den der er angivet som referenceperson på de enkelte ordrer' $ref></td><td align=center><input type=radio name=box1[$x] value=kua title= 'Provision tilfalder den kundeansvarlige' $kua></td><td align=center><input type=radio name=box1[$x] value=smart title='Provision tilfalder den kundeansvarlige s&aring;fremt der er tildelt en sådan, ellers til den som er referenceperson på de enkelte ordrer' $smart></td></tr>";
print "<tr><td>$font<small>Kilde for kostpris</small></td><td></td><td align=center>$font<small>Indk&oslash;bspris</small></td><td align=center>$font<small>Varekort</small></td></tr>";
print "<tr><td></td><td></td><td align=center><input type=radio name=box2[$x] value=batch title='Anvend varens reelle indk&oslash;bspris som kostpris.' $batch></td><td align=center><input type=radio name=box2[$x] value=kort title='Anvend kostpris fra varekort.' $kort></td></tr>";
print "<tr><td>$font<small>Skæringsdato for provisionsberegning</small></td><td></td><td></td><td align=center><SELECT NAME=box3[$x] title='Dato hvorfra og med (i foreg&aring;ende måned) til (dato i indev&aelig;rende m&aring;ned)provisionsberegning foretages'>";
if ($box3[$x]) print"<option>$box3[$x]</option>";
for ($x=1; $x<=28; $x++) { 
	print "<option>$x</option>";
}
print "</SELECT></td></tr>";;
print "<tr><td><br></td></tr>";
print "<tr><td><br></td></tr>";

print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";
?>
</tbody>
</table>
</td></tr>
</tbody></table>
</body></html>
