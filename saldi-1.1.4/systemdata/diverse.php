<?php
// -------------------------------------------- systemdata/diverse.php ------ patch1.1.2 --------------------
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
// Copyright (c) 2004-2007 DANOSOFT ApS
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

if ($_GET['sektion']) $sektion=$_GET['sektion'];

if ($_POST) {
	if ($sektion=='provision') {
		$id=$_POST['id'];
		$beskrivelse=$_POST['beskrivelse'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='1'")))) $id=$r['id'];
		elseif ($id==0){
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4) values ('Provisionsrapport', '1', 'DIV', '$box1', '$box2', '$box3', '$box4')");
		} elseif ($id > 0) db_modify("update grupper set  box1 = '$box1', box2 = '$box2', box3 = '$box3' , box4 = '$box4' where id = '$id'");
	#######################################################################################
	} elseif ($sektion=='div_valg') {
		$id=$_POST['id'];
		$box1=$_POST['box1'];
		$box2=$_POST['box2'];
		$box3=$_POST['box3'];
		$box4=$_POST['box4'];
		$box5=$_POST['box5'];
		
		if  (($id==0) && ($r = db_fetch_array(db_select("select id from grupper where art = 'DIV' and kodenr='2'")))) $id=$r['id'];
		elseif ($id==0){
		db_modify("insert into grupper (beskrivelse, kodenr, art, box1, box2, box3, box4, box5) values ('Div_valg', '2', 'DIV', '$box1', '$box2', '$box3', '$box4', '$box5')");
		} elseif ($id > 0) db_modify("update grupper set  box1 = '$box1', box2 = '$box2', box3 = '$box3', box4 = '$box4', box5 = '$box5' where id = '$id'");
	#######################################################################################
	} elseif ($sektion=='kontoplan_io') {
			if ($_POST['submit']=="Eksporter") {
				list($tmp)=split(":",$_POST['regnskabsaar']);
				print "<BODY onLoad=\"javascript:exporter_kontoplan=window.open('exporter_kontoplan.php?aar=$tmp','lager','scrollbars=yes,resizable=yes,dependent=yes');exporter_kontoplan.focus();\">";				
			}
			elseif ($_POST['submit']=="Importer") {
				print "<BODY onLoad=\"javascript:importer_kontoplan=window.open('importer_kontoplan.php','lager','scrollbars=yes,resizable=yes,dependent=yes');importer_kontoplan.focus();\">";				
			}
	}
}
print "<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\"><tbody>";

provision();
div_valg();
kontoplan_io();

print "</form>";
print "</tbody></table></td></tr>";

function provision() 
{
	global $font;

	$ref=''; $kua=''; $smart='';
	$kort=''; $batch='';
	
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '1'");
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];
	$kodenr=$r['kodenr'];
	$box1=$r['box1'];
	$box2=$r['box2'];
	$box3=$r['box3'];
	$box4=$r['box4'];

	if ($box1=='ref') $ref="checked";
	elseif ($box1=='kua') $kua="checked";
	else $smart="checked";

	if ($box2=='kort') $kort="checked";
	else $batch="checked";

	if ($box4=='bet') $bet="checked";
	else $fak="checked";

	print "<form name=diverse action=diverse.php?sektion=provision method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td colspan=6>$font<b><u>Grundlag for provisionsberegning</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td width=55%>$font<small>Beregn provision p&aring; ordrer som er faktureret eller faktureret og betalt</small></td><td></td><td width=15% align=center>$font<small>Faktureret</small></td><td width=15% align=center>$font<small>Betalt</small></td></tr>";
	print "<tr><td></td><td></td><td align=center><input type=radio name=box4 value=fak title='Provision beregnes p&aring; fakturerede ordrer' $fak></td><td align=center><input type=radio name=box4 value=bet title= 'Provision beregnes p&aring; betalte ordrer' $bet></td></tr>";
	print "<tr><td width=55%>$font<small>Kilde for personinfo</small></td><td width=15% align=center>$font<small>Ref.</small></td><td width=15% align=center>$font<small>Kundeans.</small></td><td width=15% align=center>$font<small>Begge</small></td></tr>";
	print "<tr><td></td><td align=center><input type=radio name=box1 value=ref title='Provision tilfalder den der er angivet som referenceperson p&aring; de enkelte ordrer' $ref></td><td align=center><input type=radio name=box1 value=kua title= 'Provision tilfalder den kundeansvarlige' $kua></td><td align=center><input type=radio name=box1 value=smart title='Provision tilfalder den kundeansvarlige s&aring;fremt der er tildelt en s&aring;dan, ellers til den som er referenceperson p&aring; de enkelte ordrer' $smart></td></tr>";
	print "<tr><td>$font<small>Kilde for kostpris</small></td><td></td><td align=center>$font<small>Indk&oslash;bspris</small></td><td align=center>$font<small>Varekort</small></td></tr>";
	print "<tr><td></td><td></td><td align=center><input type=radio name=box2 value=batch title='Anvend varens reelle indk&oslash;bspris som kostpris.' $batch></td><td align=center><input type=radio name=box2 value=kort title='Anvend kostpris fra varekort.' $kort></td></tr>";
	print "<tr><td>$font<small>Sk&aelig;ringsdato for provisionsberegning</small></td><td></td><td></td><td align=center><SELECT NAME=box3 title='Dato hvorfra og med (i foreg&aring;ende m&aring;ned) til (dato i indev&aelig;rende m&aring;ned)provisionsberegning foretages'>";
	if ($box3) print"<option>$box3</option>";
	for ($x=1; $x<=28; $x++) { 
		print "<option>$x</option>";
	}
	print "</SELECT></td></tr>";;
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";
	print "</form>";
} # endfunc provision

function kontoplan_io() 
{
	global $font;
	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by  kodenr");
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
	}
	$antal_regnskabsaar=$x;
	print "<form name=diverse action=diverse.php?sektion=kontoplan_io method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td colspan=6>$font<b><u>Indl&aelig;s  / udl&aelig;s kontoplan</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=2>$font<small>Eksporter kontoplan</small></td><td align=center><SELECT NAME=regnskabsaar title='V&aelig; det regnskabs&aring;r hvorfra kontoplanen skal eksporteres'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_regnskabsaar; $x++) { 
		print "<option>$kodenr[$x] : $beskrivelse[$x]</option>";
	}
	print "</SELECT></td>";;
	print "<td align = center><input type=submit accesskey=\"g\" value=\"Eksporter\" name=\"submit\"></td><tr>";
	print "<tr><td colspan=3>$font<small>Importer kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </small></td>";
	print "<td align = center><input type=submit accesskey=\"g\" value=\"Importer\" name=\"submit\"></td><tr>";
	print "</tbody></table></td></tr>";
} # endfunc kontoplan_io

function div_valg() 
{
	global $font;

	$ref=''; $kua=''; $smart='';
	$kort=''; $batch='';
	
	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '2'");
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $kodenr=$r['kodenr'];	$box1=$r['box1'];	 $box2=$r['box2']; $box3=$r['box3']; $box4=$r['box4']; $box5=$r['box5'];
	if ($box1=='on') $gruppevalg="checked"; if ($box2=='on') $kuansvalg="checked"; if ($box3=='on') $folge_s_tekst="checked"; 
	if ($box4=='on') $hurtigfakt="checked";	if ($box5=='on') $straks_bogf="checked";

	print "<form name=diverse action=diverse.php?sektion=div_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr><td colspan=6>$font<b><u>Diverse valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td>$font<small>Tvungen valg af debitorgruppe p&aring; debitorkort</td><td><input type=checkbox name=box1 $gruppevalg></small></td></tr>";
	print "<tr><td>$font<small>Tvungen valg af kundeansvarlig p&aring; debitorkort</td><td><input type=checkbox name=box2 $kuansvalg></small></td></tr>";
	print "<tr><td>$font<small>Medtag kommentarer p&aring; f&oslash;lgesedler</td><td><input type=checkbox name=box3 $folge_s_tekst></small></td></tr>";
	$q = db_select("select id from grupper where art = 'VG' and box9='on'");
	if (!db_fetch_array($q)) print "<tr><td>$font<small>Anvend hurtigfakturering (Ingen tilbud & automatisk levering ved fakturering)</td><td><input type=checkbox name=box4 $hurtigfakt></small></td></tr>";
	print "<tr><td>$font<small>Omg&aring;ende bogf&oslash;ring af k&oslash;bs- og salgsordrer</td><td><input type=checkbox name=box5 $straks_bogf></small></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem / opdater\" name=\"submit\"></td>";
	print "</form>";
} # endfunc tvang

?>
</tbody></table>
</body></html>
