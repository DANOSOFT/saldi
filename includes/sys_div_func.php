<?php
// // ------------includes/sys_div_func.php------lap 3.3.6---2014.05.08------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// Kaldes fra systemdata/diverse.php
// 2013.11.01 Tilføjet fravalg af tjek for forskellige datoer på samme bilag i kasseklasse. Søg 20131101
// 2013.12.10	Tilføjet valg om kort er betalingskort som aktiver betalingsterminal. Søg 21031210
// 2013.12.13	Tilføjet "intern" bilagsopbevaring (box6 under ftp)
// 2014.01.29	Tilføjet valg til automatisk genkendelse af betalingskort (kun ved integreret betalingsterminal) Søg 20140129
// 2014.05.08	Tilføjet valg til bordhåndtering under pos_valg Søg 20140508

function kontoindstillinger($regnskab,$skiftnavn)
{
	global $bgcolor;
	global $bgcolor5;

	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Kontoindstillinger</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	if (!$skiftnavn) {
		print "<tr><td colspan=6>Dit regnskab hedder: \"$regnskab\". Klik <a href=diverse.php?sektion=kontoindstillinger&skiftnavn=ja>her</a> for at &aelig;ndre navnet.</td></tr>";
		$r=db_fetch_array(db_select("select felt_1 from adresser where art = 'S'",__FILE__ . " linje " . __LINE__));
		print "<tr><td colspan=\"6\"<hr></td></tr>";
		print "<form name=diverse action=diverse.php?sektion=smtp method=post>";
		$tekst1=findtekst(434,$sprog_id);
		$tekst2=findtekst(435,$sprog_id);
		$tekst3=findtekst(436,$sprog_id);
		print "<tr><td title=\"$tekst1\"><!--tekst 434-->$tekst2<!--tekst 434--></td><td title=\"$tekst1\"><INPUT CLASS=\"inputbox\" TYPE = \"text\" style=\"width:200px\" name=\"smtp\" value=\"$r[felt_1]\"></td><td><input type=submit value=\"$tekst3\" name=\"submit\"><!--tekst 435--></td></tr>";
		print "</form>";
	} else  {
		print "<form name=diverse action=diverse.php?sektion=kontoindstillinger method=post>";
		print "<tr><td colspan=6>Skriv nyt navn p&aring; regnskab <INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:400px\"  name=\"nyt_navn\" value=\"$regnskab\"> og klik <input type=submit value=\"Skift&nbsp;navn\" name=\"submit\"></td></tr>";
		print "</form>";
	}


	print "<tr><td colspan=6><br></td></tr>";
}

function provision()
{
	global $bgcolor;
	global $bgcolor5;

	$bet=NULL; $ref=NULL; $kua=NULL; $smart=NULL;
	$kort=NULL; $batch=NULL;

	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
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
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Grundlag for provisionsberegning</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td>Beregn provision p&aring; ordrer som er faktureret eller faktureret og betalt</td><td></td><td align=center>Faktureret</td><td align=center>Betalt</td></tr>";
	print "<tr><td></td><td></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box4 value=fak title='Provision beregnes p&aring; fakturerede ordrer' $fak></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box4 value=bet title= 'Provision beregnes p&aring; betalte ordrer' $bet></td></tr>";
	print "<tr><td>Kilde for personinfo</td><td align=center>Ref.</td><td align=center>Kundeans.</td><td align=center>Begge</td></tr>";
	print "<tr><td></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box1 value=ref title='Provision tilfalder den der er angivet som referenceperson p&aring; de enkelte ordrer' $ref></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box1 value=kua title= 'Provision tilfalder den kundeansvarlige' $kua></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box1 value=smart title='Provision tilfalder den kundeansvarlige s&aring;fremt der er tildelt en s&aring;dan, ellers til den som er referenceperson p&aring; de enkelte ordrer' $smart></td></tr>";
	print "<tr><td>Kilde for kostpris</td><td></td><td align=center>Indk&oslash;bspris</td><td align=center>Varekort</td></tr>";
	print "<tr><td></td><td></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box2 value=batch title='Anvend varens reelle indk&oslash;bspris som kostpris.' $batch></td><td align=center><INPUT CLASS=\"inputbox\" TYPE=radio name=box2 value=kort title='Anvend kostpris fra varekort.' $kort></td></tr>";
	print "<tr><td>Sk&aelig;ringsdato for provisionsberegning</td><td></td><td></td><td align=center><SELECT class=\"inputbox\" NAME=box3 title='Dato hvorfra og med (i foreg&aring;ende m&aring;ned) til (dato i indev&aelig;rende m&aring;ned)provisionsberegning foretages'>";
	if ($box3) print"<option>$box3</option>";
	for ($x=1; $x<=28; $x++) {
		print "<option>$x</option>";
	}
	print "</SELECT></td></tr>";;
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc provision

function kontoplan_io()
{
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'RA' order by  kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
	}
	$antal_regnskabsaar=$x;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s/udl&aelig;s kontoplan</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=kontoplan_io method=post>";
		print "<tr><td colspan=2>Eksport&eacute;r kontoplan</td>\n";
		print "<td align=center><SELECT class=\"inputbox\" NAME=regnskabsaar title='V&aelig;lg det regnskabs&aring;r hvor kontoplanen skal eksporteres fra'>";
#		if ($box3[$x]) print"\t<option>$box3[$x]</option>";
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print "\t<option>$kodenr[$x] : $beskrivelse[$x]</option>";
		}
		print "</select></td>";;
		print "<td align = center><input type=submit style=\"width: 8em\" accesskey=\"e\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>";
		print "<tr><td colspan=3>Import&eacute;r kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </td>";
		print "<td align = center><input type=submit style=\"width: 8em\" accesskey=\"i\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>";
		print "</form>";
	} else {
		print "<tr><td colspan=3>Eksport&eacute;r kontoplan</td><td align=center title='V&aelig;lg det regnskabs&aring;r hvor kontoplanen skal eksporteres fra'>";
#		if ($box3[$x]) {
#			print "<form form name=exporter$kodenr[$x] action=\"exporter_kontoplan.php?aar=$box3[$x]\" method=\"post\">\n";
#			print"<input type=\"submit\" style=\"width: 8em\" value=\"$box3[$x]\"><br>\n";
#			print "</form>\n";
#		}
		for ($x=1; $x<=$antal_regnskabsaar; $x++) {
			print "";
			print "<form name=exporter$kodenr[$x] action=exporter_kontoplan.php?aar=$kodenr[$x] method=post><input type=\"submit\" style=\"width: 8em\" value=\"$beskrivelse[$x]\"></form>\n";
		}	print "";
		print "</td></tr>\n\n";
		print "<tr><td colspan=3>Import&eacute;r kontoplan (erstatter kontoplanen for nyeste regnskabs&aring;r) </td>";
		print "<td align = center><form action=\"importer_kontoplan.php\"><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\" accesskey=\"i\"></form></td><tr>";
#		print "<td align = center><a href=\"importer_kontoplan.php\" style=\"text-decoration:none\" accesskey=\"i\">Import&eacute;r</a></td><tr>";
	}
#	print "</tbody></table></td></tr>";

} # endfunc kontoplan_io

function kreditor_io()
{
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s/udl&aelig;s kreditorer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r kreditorer</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=exporter_kreditor.php method=post>";
	print "<td align = center><input type=submit style=\"width: 8em\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>\n\n";
	print "<tr><td colspan=3>Import&eacute;r kreditorer </td>\n";
	print "</form>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=kreditor_io method=post>";
	else print "<form name=diverse action=importer_kreditor.php method=post>";
	print "<td align = center><input type=submit style=\"width: 8em\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";

} # endfunc kreditor_io
function formular_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s formularer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r formularer</td>";
	if ($popup)	print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=exporter_formular.php method=post>";
	print "<td align = center><input type=submit style=\"width: 8em\" value=\"Export&eacute;r\" name=\"submit\"></td><tr>\n\n";
	print "</form>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=3>Import&eacute;r formularer</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=formular_io method=post>";
	else print "<form name=diverse action=importer_formular.php method=post>";
	print "<td align = center><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\"></td></tr>\n\n";
	print "</form>";
} # endfunc formular_io

function varer_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s varer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r varer</td>";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<td align = center><a href=\"exporter_varer.php\" style=\"text-decoration:none\"><input type=\"button\" style=\"width: 8em\"  value=\"Eksport&eacute;r\"></a></td></tr>\n\n";
	print "<tr><td colspan=3>Import&eacute;r Varer</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	else print "<form name=diverse action=importer_varer.php method=post>";
	print "<td align = center><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\"></td></tr>\n\n";
	print "</form>";
/*
	print "<tr><td colspan=3>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style=\"width: 8em\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc varer_io
function variantvarer_io() {
	global $bgcolor;
	global $bgcolor5;

	$x=0;
#	print "<form name=diverse action=diverse.php?sektion=varer_io method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s variantvarer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r variantvarer</td>";
	if ($popup) print "<td align = center><input type=submit accesskey=\"e\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>\n\n";
	else print "<td align = center><a href=\"exporter_variantvarer.php\" style=\"text-decoration:none\"><input type=\"button\" style=\"width: 8em\"  value=\"Eksport&eacute;r\"></a></td></tr>\n\n";
	print "<tr><td colspan=3>Import&eacute;r variantvarer</td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=variantvarer_io method=post>";
	else print "<form name=diverse action=importer_variantvarer.php method=post>";
	print "<td align = center><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\"></td></tr>\n\n";
	print "</form>";
/*
	print "<tr><td colspan=3>Import&eacute;r VVSpris fil fra Solar </td>\n";
	if ($popup) print "<form name=diverse action=diverse.php?sektion=solar_io method=post>";
	else print "<form name=diverse action=solarvvs.php?sektion=solar_io method=post>";
	print "<td align = center><input type=submit style=\"width: 8em\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>\n\n";
#	print "</tbody></table></td></tr>";
	print "</form>";
*/
} # endfunc variantvarer_io
function adresser_io()
{
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Indl&aelig;s/udl&aelig;s debitorer/kreditorer</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=3>Eksport&eacute;r debitorer</td>";
	if ($popup) {
		print "<form name=diverse action=diverse.php?sektion=adresser_io method=post>";
		print "<td align = center><input type=submit accesskey=\"e\" style=\"width: 8em\" value=\"Eksport&eacute;r\" name=\"submit\"></td><tr>";
		print "<tr><td colspan=3>Import&eacute;r debitorer/kreditorer</td>";
		print "<td align = center><input type=submit accesskey=\"i\" style=\"width: 8em\" value=\"Import&eacute;r\" name=\"submit\"></td><tr>";
		print "</form>";
	} else {
		print "<td align = center><form name=impdeb action=\"exporter_debitor.php\"><input type=\"submit\" style=\"width: 8em\" value=\"Eksport&eacute;r\"></form></td></tr>\n\n";
		print "<tr><td colspan=3>Import&eacute;r debitorer/kreditorer</td>";
		print "<td align = center><form name=expdeb action=\"importer_adresser.php\"><input type=\"submit\" style=\"width: 8em\" value=\"Import&eacute;r\"></form></td></tr>\n\n";
	}
#	print "</tbody></table></td></tr>";

} # endfunc adresser_io

function sqlquery_io($sqlstreng) {
	global $bgcolor;
	global $bgcolor5;

$titletxt="Skriv en SQL forespørgsel uden 'select'. F.eks: * from varer eller: varenr,salgspris from varer where lukket != 'on'";  

print "<form name=exportselect action=diverse.php?sektion=sqlquery_io method=post>";
print "<tr><td colspan=6><hr></td></tr>";
print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Dataudtr&aelig;k</u></b></td></tr>";
print "<tr><td colspan=6><br></td></tr>";
print "<input type=hidden name=id value='$id'>";
print "<tr><td valign=\"top\" title=\"$titletxt\">SELECT</td><td colspan=\"2\"><textarea name=\"sqlstreng\" rows=\"5\" cols=\"80\">$sqlstreng</textarea></td>";
print "<td align = center><input  style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Send\" name=\"submit\"></td>";
print "</form>";	$x=0;
if ($sqlstreng=trim($sqlstreng)) {
	global $db;
	global $bruger_id;

	$linje=NULL;
	$filnavn="../temp/$db/$bruger_id.csv";
	$fp=fopen($filnavn,"w");
	$sqlstreng=strtolower($sqlstreng);
	if ((strstr($sqlstreng,'update')) || (strstr($sqlstreng,'delete'))) {
		print "<BODY onLoad=\"JavaScript:alert('Forsøg på datamanipulation registreret og logget')\">";
		exit;
	}
	list($del1,$del2)=explode("where",$sqlstreng,2);
#cho "del 1 $del1<br>";
#cho "del2 $del2<br>";
	
	for($x=0;$x<strlen($del2);$x++){
		$t=substr($del2,$x,1);
		if (!$tilde) {
			if ($t=="'") {
				$tilde=1;
				$var='';
			} else $streng.=$t;
		}	else {
			if ($t=="'") {
				$tilde=0;
				$streng.="'".db_escape_string($var)."'";
			}
		}

		
	}	
#cho "$sqlstreng<br>";
	$query="select ".db_escape_string($del1);
#cho "$query<br>";
	$query="select ".$sqlstreng;
#cho "$query<br>";

	$r=0;
	$q=db_select("$query");
	while ($r < db_num_fields($q)) {
		$fieldName[$r] = db_field_name($q,$r); 
		$fieldType[$r] = db_field_type($q,$r); 
		($linje)?$linje.=chr(9).$fieldName[$r]."(".$fieldType[$r].")":$linje=$fieldName[$r]."(".$fieldType[$r].")"; 
		$r++;
	}
	if ($fp) {
		fwrite ($fp, "$linje\n");
	}
	$q=db_select("$query");
	while($r=db_fetch_array($q)) {
		$linje=NULL;
		$arraysize=count($r);
		for ($x=0;$x<$arraysize;$x++) {
			($linje)?$linje.=chr(9).$r[$x]:$linje=$r[$x]; 
		}
		if ($fp) {
			fwrite ($fp, "$linje\n");
		}
	}
	fclose($fp);
	print "<tr><td></td><td align=\"left\" colspan=\"3\"> H&oslash;jreklik her: <a href='$filnavn'>Datafil</a> og v&aelig;lg \"gem destination som\"</td></tr>";

}


} # endfunc sqlquery_io

function sprog () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'SPROG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Sprog</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);
	print "<tr><td title=\"Klik her for at rette tekster\"><a href=tekster.php?sprog_id=1>$tekst1</a></td><td><SELECT class=\"inputbox\" NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_sprog; $x++) {
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
	print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan=4><input type=submit value=\"$tekst1\" name=\"submit\"></td></tr>";
#	print "<td align = center><input type=submit value=\"$tekst2\" name=\"submit\"></td>";
#	print "<td align = center><input type=submit value=\"$tekst3\" name=\"submit\"></td><tr>";
/*
	print "</tbody></table></td></tr>";
*/
	print "</form>";
} # endfunc sprog

function jobkort () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$x=0;
	$q = db_select("select * from grupper where art = 'JOBKORT' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$kodenr[$x]=$r['kodenr'];
		$sprogkode[$x]=$r['box1'];
	}
	$antal_sprog=$x;
	print "<form name=diverse action=diverse.php?sektion=sprog method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Sprog</b></u></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	$tekst1=findtekst(1,$sprog_id);
	$tekst2=findtekst(2,$sprog_id);
	print "<tr><td>	$tekst1</td><td><SELECT class=\"inputbox\" NAME=sprog title='$tekst2'>";
	if ($box3[$x]) print"<option>$box3[$x]</option>";
	for ($x=1; $x<=$antal_sprog; $x++) {
		print "<option>$beskrivelse[$x]</option>";
	}
	print "</SELECT></td></tr>";
	print "<tr><td><br></td></tr>";
	$tekst1=findtekst(3,$sprog_id);
	print "<tr><td align = right colspan=4><input type=submit value=\"$tekst1\" name=\"submit\"></td></tr>";
#	print "<td align = center><input type=submit value=\"$tekst2\" name=\"submit\"></td>";
#	print "<td align = center><input type=submit value=\"$tekst3\" name=\"submit\"></td><tr>";
/*
	print "</tbody></table></td></tr>";
*/
	print "</form>";

} # endfunc sprog


function personlige_valg() {
	global $sprog_id;
	global $popup;
	global $bruger_id;
	global $bgcolor;
	global $bgcolor5;
	global $nuance;

	$gl_menu=NULL;$sidemenu=NULL;$topnemu=NULL;

	$r = db_fetch_array(db_select("select * from grupper where art = 'USET' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$jsvars=$r['box1'];
	($r['box2'])?$popup='checked':$popup=NULL;
	if ($r['box3'] == 'S') $sidemenu='checked';
	elseif ($r['box3'] == 'T') $topmenu='checked';
	else $gl_menu='checked';
	($r['box4'])?$bgcolor=$r['box4']:$bgcolor=NULL;
	($r['box5'])?$nuance=$r['box5']:$nuance=NULL;

	$nuancefarver[0]=findtekst(418,$sprog_id); $nuancekoder[0]="+00-22-22";
	$nuancefarver[1]=findtekst(419,$sprog_id); $nuancekoder[1]="-22+00-22";
	$nuancefarver[2]=findtekst(420,$sprog_id); $nuancekoder[2]="-22-22+00";
	$nuancefarver[3]=findtekst(421,$sprog_id); $nuancekoder[3]="+00+00-33";
	$nuancefarver[4]=findtekst(422,$sprog_id); $nuancekoder[4]="+00-33+00";
	$nuancefarver[5]=findtekst(423,$sprog_id); $nuancekoder[5]="-33+00+00";

	print "<form name=personlige_valg action=diverse.php?sektion=personlige_valg&popup=$popup method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Personlige valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
#	print "<input type=hidden name=id value='$id'>";

	print "<tr><td title=\"".findtekst(207,$sprog_id)."\">".findtekst(208,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"popup\" $popup></td></tr>";
	if (strpos($_SERVER['SERVER_NAME'],'ateway') || strpos($_SERVER['SERVER_NAME'],'sl3')) {
	#	print "<tr><td title=\"".findtekst(316,$sprog_id)."\"><!--Tekst 523-->".findtekst(315,$sprog_id)."<!--Tekst 315--></td><td><INPUT CLASS=\"inputbox\" TYPE=\"radio\" name=\"menu\" value=\"sidemenu\" $sidemenu></td></tr>";
		print "<tr><td title=\"".findtekst(523,$sprog_id)."\"><!--Tekst 523-->".findtekst(522,$sprog_id)."<!--Tekst 522--></td><td><INPUT CLASS=\"inputbox\" TYPE=\"radio\" name=\"menu\" value=\"topmenu\" $topmenu></td></tr>";
	}	else $gl_menu='checked';
	print "<tr><td title=\"".findtekst(525,$sprog_id)."\"><!--Tekst 525-->".findtekst(524,$sprog_id)."<!--Tekst 524--></td><td><INPUT CLASS=\"inputbox\" TYPE=\"radio\" name=\"menu\"  value=\"gl_menu\" $gl_menu></td></tr>";
	print "<tr><td title=\"".findtekst(209,$sprog_id)."\">".findtekst(210,$sprog_id)."</td><td colspan=\"4\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:600px\" name=\"jsvars\" value=\"$jsvars\"></td></tr>";
	print "<tr><td title=\"".findtekst(318,$sprog_id)."\">".findtekst(317,$sprog_id)."</td><td colspan=\"4\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"width:100px\" name=\"bgcolor\" value=\"".substr($bgcolor,1,6)."\"></td></tr>";
	print "<tr><td title=\"".findtekst(416,$sprog_id)."\">".findtekst(415,$sprog_id)."</td><td colspan=\"4\"><select name='nuance' title='".findtekst(417,$sprog_id)."'>\n";
	if ( ! $nuance ) {
		$valgt = "selected='selected'";
	} else {
		$valgt="";
	}
	print "   <option $valgt value='' style='background:$bgcolor'>Intet</option>\n";
	$antal_nuancer=count($nuancefarver);
	for ($x=0; $x<=$antal_nuancer;$x++) {
		if ( $nuance === $nuancekoder[$x] ) {
			$valgt = "selected='selected'";
		} else {
			$valgt="";
		}
		print "   <option $valgt value='$nuancekoder[$x]' style='background:".farvenuance($bgcolor, $nuancekoder[$x])."'>$nuancefarver[$x]</option>\n";
	}
	print "</select></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td></tr>\n";
	print "</form>";
} # endfunc personlige_valg

function div_valg() {
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;

	$gruppevalg=NULL;$kuansvalg=NULL;
	$ref=NULL; $kua=NULL; $smart=NULL;
	$jobkort=NULL; $kort=NULL; $batch=NULL;

	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse']; $kodenr=$r['kodenr'];	$box1=$r['box1'];	 $box2=$r['box2']; $box3=$r['box3']; $box4=$r['box4']; $box5=$r['box5']; $box6=$r['box6'];$box7=$r['box7'];$box8=$r['box8'];$box9=$r['box9'];$box10=$r['box10'];
	if ($box1=='on') $gruppevalg="checked"; if ($box2=='on') $kuansvalg="checked"; if ($box3=='on') $extra_ansat="checked";
	if ($box4=='on') $forskellige_datoer="checked";	if ($box5=='on') $ledig="checked";	if ($box6=='on') $docubizz="checked";
	if ($box7=='on') $jobkort="checked";if ($box8) $ebconnect="checked";if ($box9=='on') $ledig="checked";
	if ($box10=='on') $betalingsliste="checked";

	print "<form name=diverse action=diverse.php?sektion=div_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Diverse valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(186,$sprog_id)."\">".findtekst(162,$sprog_id)."</td><td title=\"".findtekst(186,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box1 $gruppevalg></td></tr>";
#	print "<td title=\"".findtekst(211,$sprog_id)."\">".findtekst(212,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box7 $jobkort></td></tr>";
	print "<tr><td title=\"".findtekst(187,$sprog_id)."\">".findtekst(163,$sprog_id)."</td><td title=\"".findtekst(187,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box2 $kuansvalg></td></tr>";
	print "<tr><td title=\"".findtekst(615,$sprog_id)."\">".findtekst(616,$sprog_id)."</td><td title=\"".findtekst(615,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box3 $extra_ansat></td></tr>";
	print "<tr><td title=\"".findtekst(185,$sprog_id)."\">".findtekst(184,$sprog_id)."</td><td title=\"".findtekst(185,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box10 $betalingsliste></td></tr>";
	print "<tr><td title=\"".findtekst(193,$sprog_id)."\">".findtekst(167,$sprog_id)."</td><td title=\"".findtekst(193,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box6 $docubizz></td></tr>";
	print "<tr><td title=\"".findtekst(194,$sprog_id)."\">".findtekst(168,$sprog_id)."</td><td title=\"".findtekst(194,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box7 $jobkort></td></tr>";
	print "<tr><td title=\"".findtekst(639,$sprog_id)."\">".findtekst(638,$sprog_id)."</td><td title=\"".findtekst(639,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box4 $forskellige_datoer></td></tr>";#20131101
	print "<tr><td title=\"".findtekst(527,$sprog_id)."\">".findtekst(526,$sprog_id)."</td><td title=\"".findtekst(527,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=box8 $ebconnect></td></tr>";
	if ($box8) {
		list($oiourl,$oiobruger,$oiokode)=explode(chr(9),$box8);
		print "<tr><td title=\"\">".findtekst(528,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"oiourl\" value=$oiourl></td></tr>";
		print "<tr><td title=\"\">".findtekst(529,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"oiobruger\" value=$oiobruger></td></tr>";
		print "<tr><td title=\"\">".findtekst(530,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"password\" name=\"oiokode\" value=$oiokode></td></tr>";
	}
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc div_valg

function ordre_valg()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$hurtigfakt=NULL; $incl_moms=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;

	$r=db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '3'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];
	$kodenr=$r['kodenr'];
	($r['box1']=='on')?$incl_moms="checked":$incl_moms=NULL;; 
	$rabatvareid=$r['box2'];
	($r['box3']=='on')?$folge_s_tekst="checked":$folge_s_tekst=NULL;;
	($r['box4']=='on')?$hurtigfakt="checked":$hurtigfakt=NULL;;	
	($r['box5']=='on')?$straks_bogf="checked":$straks_bogf=NULL;;	
	($r['box6']=='on')?$fifo="checked":$fifo==NULL;;
	$kontantkonto=$r['box7'];
	($r['box8']=='on')?$vis_nul_lev="checked":$vis_nul_lev=NULL;;
	($r['box9']=='on')?$negativt_lager="checked":$negativt_lager=NULL;
	$kortkonto=$r['box10'];
	($r['box11']=='on')?$advar_lav_beh="checked":$advar_lav_beh=NULL;
	($r['box12']=='on')?$procentfakt="checked":$procentfakt=NULL;
	list($procenttillag,$procentvare)=explode(chr(9),$r['box13']);

	if ($rabatvareid) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
		$rabatvarenr=$r['varenr'];
	}

	print "<form name=diverse action=diverse.php?sektion=ordre_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Ordrerelaterede valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(197,$sprog_id)."\">".findtekst(196,$sprog_id)."</td><td><INPUT title=\"".findtekst(197,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=box1 $incl_moms></td></tr>";
	print "<tr><td title=\"".findtekst(188,$sprog_id)."\">".findtekst(164,$sprog_id)."</td><td><INPUT title=\"".findtekst(188,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=box3 $folge_s_tekst></td></tr>";
	print "<tr><td title=\"".findtekst(189,$sprog_id)."\">".findtekst(169,$sprog_id)."</td><td><INPUT title=\"".findtekst(189,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=box8 $vis_nul_lev></td></tr>";
	$r=db_fetch_array(db_select("select id from grupper where art = 'VG' and box9='on'",__FILE__ . " linje " . __LINE__));
	if ($r['id']) $hurtigfakt="onclick=\"return false\"";
	print "<tr><td title=\"".findtekst(190,$sprog_id)."\">".findtekst(165,$sprog_id)."</td><td><INPUT title=\"".findtekst(190,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box4\" $hurtigfakt></td></tr>";
	print "<tr><td title=\"".findtekst(191,$sprog_id)."\">".findtekst(166,$sprog_id)."</td><td><INPUT title=\"".findtekst(191,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box5\" $straks_bogf></td></tr>";
	print "<tr><td title=\"".findtekst(313,$sprog_id)."\">".findtekst(314,$sprog_id)."</td><td><INPUT title=\"".findtekst(313,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box6\" $fifo></td></tr>";
	print "<tr><td title=\"".findtekst(192,$sprog_id)."\">".findtekst(183,$sprog_id)."</td><td><INPUT title=\"".findtekst(192,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box9\" $negativt_lager></td></tr>";
	print "<tr><td title=\"".findtekst(680,$sprog_id)."\">".findtekst(644,$sprog_id)."</td><td><INPUT title=\"".findtekst(680,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box11\" $advar_lav_beh></td></tr>";
	print "<tr><td title=\"".findtekst(682,$sprog_id)."\">".findtekst(681,$sprog_id)."</td><td><INPUT title=\"".findtekst(682,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box12\" $procentfakt></td></tr>";
	print "<tr><td title=\"".findtekst(684,$sprog_id)."\">".findtekst(683,$sprog_id)."</td><td><INPUT title=\"".findtekst(684,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"text\" style=\"width:35px;text-align:right;\" name=\"procenttillag\" value=\"$procenttillag\">%</td></tr>";
	print "<tr><td title=\"".findtekst(686,$sprog_id)."\">".findtekst(685,$sprog_id)."</td><td><INPUT title=\"".findtekst(686,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"text\" style=\"width:70px;text-align:right;\" name=\"procentvare\" value=\"$procentvare\"></td></tr>";
	print "<tr><td title=\"".findtekst(288,$sprog_id)."\">".findtekst(287,$sprog_id)."</td><td><INPUT title=\"".findtekst(288,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"text\" style=\"width:70px;text-align:right;\" name=\"box2\" value=\"$rabatvarenr\"></td></tr>";
	print "<tr><td title=\"".findtekst(618,$sprog_id)."\">".findtekst(617,$sprog_id)."</td><td><INPUT title=\"".findtekst(618,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"text\" style=\"width:70px;text-align:right;\" name=\"box7\" value=\"$kontantkonto\"></td></tr>";
	print "<tr><td title=\"".findtekst(620,$sprog_id)."\">".findtekst(619,$sprog_id)."</td><td><INPUT title=\"".findtekst(620,$sprog_id)."\" CLASS=\"inputbox\" TYPE=\"text\" style=\"width:70px;text-align:right;\" name=\"box10\" value=\"$kortkonto\"></td></tr>";
	

	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc ordre_valg

function vare_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
#	global $delete_var_type;
#	global $delete_varianter;
#	global $rename_var_type;
#	global $rename_varrianter;

#	$hurtigfakt=NULL; $incl_moms=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;

	$q = db_select("select * from grupper where art = 'DIV' and kodenr = '5'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];$kodenr=$r['kodenr'];$box1=trim($r['box1']);$box2=trim($r['box2']);$box3=trim($r['box3']);$box4=trim($r['box4']);;$box5=trim($r['box5']);

	print "<form name=diverse action=diverse.php?sektion=vare_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(470,$sprog_id)."<!--tekst 470--></u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(469,$sprog_id)."\">".findtekst(468,$sprog_id)."</td><td title=\"".findtekst(469,$sprog_id)."\"><SELECT CLASS=\"inputbox\" name=\"box1\">";
	$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$box1'",__FILE__ . " linje " . __LINE__));
	if ($box1) $value="S".$box1.":".$r['beskrivelse']; 
	print "<option value=\"$box1\">$value</option>";
	$q=db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$value="S".$r['kodenr'].":".$r['beskrivelse']; 
		print "<option value=\"$r[kodenr]\">$value</option>";
	}
	print "<option></option>";
	print "</select></td></tr>";
	if ($box2=='!') $box3='1'; 
	print "<tr><td><br></td></tr>";
	print "<tr><td title=\"".findtekst(625,$sprog_id)."\"><!--tekst 626-->".findtekst(625,$sprog_id)."<!--tekst 626--></td><td colspan=\"3\" title=\"".findtekst(625,$sprog_id)."\"><select style=\"text-align:left;width:300px;\" name=\"box3\">";
	if (!$box3) print "<option value='0'>".findtekst(627,$sprog_id)."<!--tekst 627--></option>";
	if ($box3=='1') print "<option value='1'>".findtekst(628,$sprog_id)."<!--tekst 628--></option>";
	if ($box3=='2') print "<option value='2'>".findtekst(629,$sprog_id)."<!--tekst 629--></option>";
	if ($box3) print "<option value='0'>".findtekst(627,$sprog_id)."<!--tekst 627--></option>";
	if ($box3!='1') print "<option value='1'>".findtekst(628,$sprog_id)."<!--tekst 628--></option>";
	if ($box3!='2') print "<option value='2'>".findtekst(629,$sprog_id)."<!--tekst 629--></option>";
	print "</select></td></tr>";
	if ($box3=='2') print "<tr><td title=\"".findtekst(503,$sprog_id)."\"><!--tekst 503-->".findtekst(504,$sprog_id)."<!--tekst 504--></td><td colspan=\"3\" title=\"".findtekst(503,$sprog_id)."\"><!--tekst 503--><input type=\"text\" style=\"text-align:left;width:300px;\" name=\"box2\" value = \"$box2\"</td></tr>";
	if ($box3=='1') {
		print "<tr><td title=\"".findtekst(621,$sprog_id)."\"><!--tekst 621-->".findtekst(622,$sprog_id)."<!--tekst 622--></td><td colspan=\"3\" title=\"".findtekst(621,$sprog_id)."\"><!--tekst 621--><input type=\"text\" style=\"text-align:left;width:300px;\" name=\"box4\" value = \"$box4\"</td></tr>";
		print "<tr><td title=\"".findtekst(623,$sprog_id)."\"><!--tekst 623-->".findtekst(624,$sprog_id)."<!--tekst 624--></td><td colspan=\"3\" title=\"".findtekst(623,$sprog_id)."\"><!--tekst 623--><input type=\"text\" style=\"text-align:left;width:300px;\" name=\"box5\" value = \"$box5\"</td></tr>";
	}
	print "<tr><td>";
	print "<br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"".findtekst(471,$sprog_id)."\" name=\"submit\"><!--tekst 471--></td>";
	print "</form>";

	print "<form name=diverse action=diverse.php?sektion=varianter method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(472,$sprog_id)."<!--tekst 472--></u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	
	if ($delete_var_type=if_isset($_GET['delete_var_type'])) db_modify("delete from variant_typer where id = '$delete_var_type'",__FILE__ . " linje " . __LINE__);
	if ($delete_variant=if_isset($_GET['delete_variant'])) {
		db_modify("delete from variant_typer where variant_id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from varianter where id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
	}
	if ($rename_var_type=if_isset($_GET['rename_var_type'])) {
		$r=db_fetch_array(db_select("select beskrivelse from variant_typer where id=$rename_var_type",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_var_type' value='$rename_var_type'>";
		print "<tr><td>".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td><input type=\"text\" name=\"var_type_beskrivelse\" value = \"$r[beskrivelse]\"</td></tr>";
	}elseif ($rename_variant=if_isset($_GET['rename_variant'])) {
		$r=db_fetch_array(db_select("select beskrivelse from varianter where id=$rename_variant",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_varianter' value='$rename_variant'>";
		print "<tr><td>".findtekst(474,$sprog_id)."<!--tekst 474--></td><td></td><td><input type=\"text\" name=\"variant_beskrivelse\" value = \"$r[beskrivelse]\"</td></tr>";
	} else {
		$x=0;
		$q=db_select("select * from varianter order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			$variant_id[$x]=$r['id'];
			$variant_beskrivelse[$x]=$r['beskrivelse'];
			$y=0;
			$q2=db_select("select * from variant_typer where variant_id=$variant_id[$x] order by beskrivelse",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$y++;
				$var_type_id[$x][$y]=$r2['id'];
				$var_type_beskrivelse[$x][$y]=$r2['beskrivelse'];
			}
			$var_type_antal[$x]=$y;
		}
		$variant_antal=$x;
		print "<tr><td></td><td><b>".findtekst(475,$sprog_id)."<!--tekst 475--></b></td><td><b>".findtekst(476,$sprog_id)."<!--tekst 476--></b></td></tr>";
		for ($x=1;$x<=$variant_antal;$x++){
			print "<tr><td></td><td>$variant_beskrivelse[$x]</td></td><td>";
			print "<td><span title=\"".findtekst(477,$sprog_id)."\"><!--tekst 477--><a href=\"diverse.php?sektion=varianter&rename_variant=".$variant_id[$x]."\" onclick=\"return confirm('".findtekst(483,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
			print "<span title=\"".findtekst(478,$sprog_id)."\"><!--tekst 478--><a href=\"diverse.php?sektion=varianter&delete_variant=".$variant_id[$x]."\" onclick=\"return confirm('".findtekst(481,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			for ($y=1;$y<=$var_type_antal[$x];$y++){
#				if ($y>1) 
				print "<tr></td><td><td></td>";
				print "<td>".$var_type_beskrivelse[$x][$y]."</td>";
				print "<td><span title=\"".findtekst(479,$sprog_id)."\"><!--tekst 479--><a href=\"diverse.php?sektion=varianter&rename_var_type=".$var_type_id[$x][$y]."\" onclick=\"return confirm('".findtekst(484,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
				print "<span title=\"".findtekst(480,$sprog_id)."\"><!--tekst 480--><a href=\"diverse.php?sektion=varianter&delete_var_type=".$var_type_id[$x][$y]."\" onclick=\"return confirm('".findtekst(482,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			} 
			print "<input type='hidden' name='variant_id[$x]' value='$variant_id[$x]'>";
			print "<tr><td title=\"".findtekst(486,$sprog_id)."\"><!--tekst 486-->".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td title=\"".findtekst(486,$sprog_id)."\"><!--tekst 486--><input type=\"text\" name=\"var_type_beskrivelse[$x]\"</td></tr>";
		} 
		print "<input type='hidden' name='variant_antal' value='$variant_antal'>";
		print "<tr><td title=\"".findtekst(485,$sprog_id)."\"><!--tekst 485-->".findtekst(474,$sprog_id)."<!--tekst 474--></td><td title=\"".findtekst(485,$sprog_id)."\"><!--tekst 485--><input type=\"text\" name=\"variant_beskrivelse\"</td></tr>";
	}		
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"".findtekst(471,$sprog_id)."\" name=\"submit\"><!--tekst 471--></td>";
	print "</form>";

} # endfunc vare_valg

function shop_valg() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;
#	global $delete_var_type;
#	global $delete_varianter;
#	global $rename_var_type;
#	global $rename_varrianter;

#	$hurtigfakt=NULL; $incl_moms=NULL; $folge_s_tekst=NULL; $negativt_lager=NULL; $straks_bogf=NULL; $vis_nul_lev=NULL;

	$q = db_select("select * from grupper where art = 'SHOP' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$beskrivelse=$r['beskrivelse'];$kodenr=$r['kodenr'];$box1=trim($r['box1']);$box2=trim($r['box2']);

	print "<form name=diverse action=diverse.php?sektion=shop_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(470,$sprog_id)."<!--tekst 470--></u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(469,$sprog_id)."\">".findtekst(468,$sprog_id)."</td><td><SELECT CLASS=\"inputbox\" name=\"box1\">";
	$r=db_fetch_array(db_select("select * from grupper where art = 'SM' and kodenr = '$box1'",__FILE__ . " linje " . __LINE__));
	if ($box1) $value="S".$box1.":".$r['beskrivelse']; 
	print "<option value=\"$box1\">$value</option>";
	$q=db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$value="S".$r['kodenr'].":".$r['beskrivelse']; 
		print "<option value=\"$r[kodenr]\">$value</option>";
	}
	print "<option></option>";
	print "</select></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td title=\"".findtekst(503,$sprog_id)."\"><!--tekst 503-->".findtekst(504,$sprog_id)."<!--tekst 504--></td><td colspan=\"3\"><input type=\"text\" style=\"text-align:left;width:300px;\" name=\"box2\" value = \"$box2\"</td></tr>";

	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"".findtekst(471,$sprog_id)."\" name=\"submit\"><!--tekst 471--></td>";
	print "</form>";

	print "<form name=diverse action=diverse.php?sektion=varianter method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(472,$sprog_id)."<!--tekst 472--></u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	
	if ($delete_var_type=if_isset($_GET['delete_var_type'])) db_modify("delete from variant_typer where id = '$delete_var_type'",__FILE__ . " linje " . __LINE__);
	if ($delete_variant=if_isset($_GET['delete_variant'])) {
		db_modify("delete from variant_typer where variant_id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
		db_modify("delete from varianter where id = '$delete_variant'",__FILE__ . " linje " . __LINE__);
	}
	if ($rename_var_type=if_isset($_GET['rename_var_type'])) {
		$r=db_fetch_array(db_select("select beskrivelse from variant_typer where id=$rename_var_type",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_var_type' value='$rename_var_type'>";
		print "<tr><td>".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td><input type=\"text\" name=\"var_type_beskrivelse\" value = \"$r[beskrivelse]\"</td></tr>";
	}elseif ($rename_variant=if_isset($_GET['rename_variant'])) {
		$r=db_fetch_array(db_select("select beskrivelse from varianter where id=$rename_variant",__FILE__ . " linje " . __LINE__));
		print "<input type='hidden' name='rename_varianter' value='$rename_variant'>";
		print "<tr><td>".findtekst(474,$sprog_id)."<!--tekst 474--></td><td></td><td><input type=\"text\" name=\"variant_beskrivelse\" value = \"$r[beskrivelse]\"</td></tr>";
	} else {
		$x=0;
		$q=db_select("select * from varianter order by beskrivelse",__FILE__ . " linje " . __LINE__);
		while ($r=db_fetch_array($q)) {
			$x++;
			$variant_id[$x]=$r['id'];
			$variant_beskrivelse[$x]=$r['beskrivelse'];
			$y=0;
			$q2=db_select("select * from variant_typer where variant_id=$variant_id[$x] order by beskrivelse",__FILE__ . " linje " . __LINE__);
			while ($r2=db_fetch_array($q2)) {
				$y++;
				$var_type_id[$x][$y]=$r2['id'];
				$var_type_beskrivelse[$x][$y]=$r2['beskrivelse'];
			}
			$var_type_antal[$x]=$y;
		}
		$variant_antal=$x;
		print "<tr><td></td><td><b>".findtekst(475,$sprog_id)."<!--tekst 475--></b></td><td><b>".findtekst(476,$sprog_id)."<!--tekst 476--></b></td></tr>";
		for ($x=1;$x<=$variant_antal;$x++){
			print "<tr><td></td><td>$variant_beskrivelse[$x]</td></td><td>";
			print "<td><span title=\"".findtekst(477,$sprog_id)."\"><!--tekst 477--><a href=\"diverse.php?sektion=varianter&rename_variant=".$variant_id[$x]."\" onclick=\"return confirm('".findtekst(483,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
			print "<span title=\"".findtekst(478,$sprog_id)."\"><!--tekst 478--><a href=\"diverse.php?sektion=varianter&delete_variant=".$variant_id[$x]."\" onclick=\"return confirm('".findtekst(481,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			for ($y=1;$y<=$var_type_antal[$x];$y++){
#				if ($y>1) 
				print "<tr></td><td><td></td>";
				print "<td>".$var_type_beskrivelse[$x][$y]."</td>";
				print "<td><span title=\"".findtekst(479,$sprog_id)."\"><!--tekst 479--><a href=\"diverse.php?sektion=varianter&rename_var_type=".$var_type_id[$x][$y]."\" onclick=\"return confirm('".findtekst(484,$sprog_id)."')\"><img src=../ikoner/rename.png border=0></a></span>\n";
				print "<span title=\"".findtekst(480,$sprog_id)."\"><!--tekst 480--><a href=\"diverse.php?sektion=varianter&delete_var_type=".$var_type_id[$x][$y]."\" onclick=\"return confirm('".findtekst(482,$sprog_id)."')\"><img src=../ikoner/delete.png border=0></a></span></td></tr>\n";
			} 
			print "<input type='hidden' name='variant_id[$x]' value='$variant_id[$x]'>";
			print "<tr><td title=\"".findtekst(486,$sprog_id)."\"><!--tekst 486-->".findtekst(473,$sprog_id)."<!--tekst 473--></td><td></td><td title=\"".findtekst(486,$sprog_id)."\"><!--tekst 486--><input type=\"text\" name=\"var_type_beskrivelse[$x]\"</td></tr>";
		} 
		print "<input type='hidden' name='variant_antal' value='$variant_antal'>";
		print "<tr><td title=\"".findtekst(485,$sprog_id)."\"><!--tekst 485-->".findtekst(474,$sprog_id)."<!--tekst 474--></td><td title=\"".findtekst(485,$sprog_id)."\"><!--tekst 485--><input type=\"text\" name=\"variant_beskrivelse\"</td></tr>";
	}		
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"".findtekst(471,$sprog_id)."\" name=\"submit\"><!--tekst 471--></td>";
	print "</form>";

} # endfunc shop_valg

function prislister()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$prislister=array();
	$antal=0;
	$q=db_select("select * from grupper where art = 'PL' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$antal++;
		$id[$antal]=$r['id'];
		$beskrivelse[$antal]=$r['beskrivelse'];
		$lev_id[$antal]=$r['box1'];
		$prisfil[$antal]=$r['box2'];
		$opdateret[$antal]=$r['box3'];
		$aktiv[$antal]=$r['box4'];
		$rabat[$antal]=$r['box6'];
		$gruppe[$antal]=$r['box8'];
	}
	$gpantal=0;
	$q=db_select("select * from grupper where art = 'VG' order by kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$gpantal++;
		$vgrp[$gpantal]=$r['kodenr'];
		$vgbesk[$gpantal]=$r['beskrivelse'];
	}

	if (!in_array('Solar',$beskrivelse)) {
		$antal++;
		$beskrivelse[$antal]='Solar';
		$prisfil[$antal]="../prislister/solar.txt";
	}
	print "<form name='diverse' action='diverse.php?sektion=prislister' method='post'>";
	print "<input type='hidden' name='antal' value='$antal'>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td><b>".findtekst(427,$sprog_id)."<!--tekst 427--></b></td><td><b>".findtekst(428,$sprog_id)."<!--tekst 428--></b></td><td><b>".findtekst(429,$sprog_id)."<!--tekst 429--></b></td><td colspan=\"3\"><b>".findtekst(430,$sprog_id)."<!--tekst 430--></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	for ($x=1;$x<=$antal;$x++) {
		print "<input type='hidden' name='beskrivelse[$x]' value='$beskrivelse[$x]'>";
		print "<input type='hidden' name='lev_id[$x]' value='$lev_id[$x]'>";
		print "<input type='hidden' name='prisfil[$x]' value='$prisfil[$x]'>";
		print "<input type='hidden' name='id[$x]' value='$id[$x]'>";
		if ($aktiv[$x]) {
			$title=findtekst(426,$sprog_id);
			print "<tr><td title=\"$title\"><!--tekst 426--><a href=lev_rabat.php?id=$id[$x]&lev_id=$lev_id[$x]&prisliste=$beskrivelse[$x]>$beskrivelse[$x]</a></td>";
			$aktiv[$x]="checked";
			if (!$lev_id[$x]) print "<meta http-equiv=\"refresh\" content=\"0;URL=lev_rabat.php?id=$id[$x]&prisliste=$beskrivelse[$x]\">\n";
		} else print "<tr><td>$beskrivelse[$x]</td>";
		$title=str_replace('$beskrivelse',$beskrivelse[$x],findtekst(431,$sprog_id));
		print "<td title=\"$title\"><!--tekst 431--><INPUT CLASS=\"inputbox\" STYLE=\"width:30px;text-align:right\" TYPE=\"text\" name=\"rabat[$x]\" value=\"$rabat[$x]\">%</td>";
		$title=str_replace('$beskrivelse',$beskrivelse[$x],findtekst(432,$sprog_id));
		print "<td title=\"$title\"><!--tekst 432--><select CLASS=\"inputbox\" name=\"gruppe[$x]\">";
		for ($y=1;$y<=$gpantal;$y++) {
			if ($vgrp[$y]==$gruppe[$x]) print "<option value=\"$vgrp[$y]\">$vgrp[$y]: $vgbesk[$y]</option>";
		}
		for ($y=1;$y<=$gpantal;$y++) {
			if ($vgrp[$y]!=$gruppe[$x]) print "<option value=\"$vgrp[$y]\">$vgrp[$y]: $vgbesk[$y]</option>";
		}
		print "</select></td>"; 
		print "<td title=\"".findtekst(425,$sprog_id)."\"><!--tekst 425--><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\" $aktiv[$x]></td></tr>";
		print "<tr><td><br></td></tr>";
	}
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc prislister

function rykker_valg()
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$box1=NULL;$box2=NULL;$box3=NULL;$box4=NULL;$box5=NULL;$box6=NULL;$box7=NULL;$box8=NULL;$box9=NULL;

	$r = db_fetch_array(db_select("select * from grupper where art = 'DIV' and kodenr = '4'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$box1=$r['box1'];	 $box2=$r['box2'];
	if ($r['box3']) $box3=$r['box3']*1;
	$box4=$r['box4'];
	if ($r['box5']) $box5=$r['box5']*1;
	if ($r['box6']) $box6=$r['box6']*1;
	if ($r['box7']) $box7=$r['box7']*1;
#	$box8=$r['box8']; Box 8 bruger til resistrering af sidst sendte reminder.
	$box9=$r['box9'];$box10=$r['box10'];

	$x=0;
	$q = db_select("select id,brugernavn from brugere order by brugernavn",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$br_id[$x]=$r['id'];
		$br_navn[$x]=$r['brugernavn'];
		if ($box1==$br_id[$x]) $box1=$br_navn[$x];
	}
	$br_antal=$x;
/*
	if ($box3 || $box4) {
		if ($r=db_fetch_array(db_select("select beskrivelse from varer where varenr = '$box4'",__FILE__ . " linje " . __LINE__))) {
			$varetekst=htmlentities($r['beskrivelse']);
		} else print "<BODY onLoad=\"JavaScript:alert('Varenummer ikke gyldigt')\">";
	}
*/
	print "<form name=diverse action=diverse.php?sektion=rykker_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>Rykkerrelaterede valg</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	#Box1 Brugernavn for "rykkeransvarlig - Naar bruger logger ind adviseres hvis der skal rykkes - Hvis navn ikke angives adviseres alle..

	print "<tr><td title=\"".findtekst(224,$sprog_id)."\">".findtekst(225,$sprog_id)."</td>";
	print "<td title=\"".findtekst(224,$sprog_id)."\"><SELECT class=\"inputbox\" NAME=box1>";
	if ($box1) print "<option>$box1</option>";
	print "<option></option>";
	for ($x=1;$x<=$br_antal;$x++){
		if ($br_navn[$x]!=$box1) print "<option>$br_navn[$x]</option>";
	}
	print "</select></td></tr>";
	#Box2 Mailadresse for rykkeransvarlig hvis angivet sendes email naar der skal rykkes. (Naar nogen logger ind - uanset hvem)
	print "<tr><td title=\"".findtekst(226,$sprog_id)."\">".findtekst(227,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text size=15 name=box2 value=\"$box2\"></td></tr>";
	#Box4 Varenummer for rente
#	print "<tr><td title=\"".findtekst(230,$sprog_id)."\">".findtekst(231,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text size=15 name=box4 value=\"$box4\"></td></tr>";
	#Box3 Rentesats % pr paabegyndt md.
#	print "<tr><td title=\"".findtekst(228,$sprog_id)."\">".findtekst(229,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" size=1 name=box3 value=\"$box3\"> %</td></tr>";
	#Box5 Dage betalingsfrist skal vaere overskredet foer der rykkes.
	print "<tr><td title=\"".findtekst(232,$sprog_id)."\">".findtekst(233,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" size=1 name=box5 value=\"$box5\"> dage</td></tr>";
	#Box6 Dage fra rykker 1 til rykker 2
	print "<tr><td title=\"".findtekst(234,$sprog_id)."\">".findtekst(235,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" size=1 name=box6 value=\"$box6\"> dage</td></tr>";
	#Box7 Dage fra rykker 2 til rykker 3
	print "<tr><td title=\"".findtekst(236,$sprog_id)."\">".findtekst(237,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" size=1 name=box7 value=\"$box7\"> dage</td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc rykker_valg


function tjekliste() {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$ret=if_isset($_GET['ret']);
	
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt']; 
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=$r2['tjekpunkt']; 
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
#cho "select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt<br>\n";
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=$r3['tjekpunkt']; 
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	$fasenr=0;
	print "<form name=\"diverse\" action=\"diverse.php?sektion=tjekliste\" method=\"post\">\n";
	print "<tr><td colspan=\"6\"><hr></td></tr>\n";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=\"6\"><b><u>Tjeklister</u></b></td></tr>\n";
	for ($x=1;$x<=count($id);$x++) {
		if ($fase[$x]!=$fase[$x-1]) $fasenr++;
		print "<input type=\"hidden\" name=\"tjekantal\" value='".count($id)."'>\n";
		print "<input type=\"hidden\" name=\"id[$x]\" value='$id[$x]'>\n";
		print "<input type=\"hidden\" name=\"fase[$x]\" value='$fase[$x]'>\n";
		print "<input type=\"hidden\" name=\"tjekpunkt[$x]\" value='$tjekpunkt[$x]'>\n";
		if ($fase[$x]!=$fasenr) db_modify("update tjekliste set fase='$fasenr' where id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			print "<tr><td colspan=\"6\"><hr></td></tr>\n";
			if ($ret==$id[$x]) print "<tr><td colspan=\"1\"><big><b><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"tjekpunkt[$x]\" size=\"20\" value=\"$tjekpunkt[$x]\"></b></big></td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"ny_fase[$x]\" style=\"text-align:right;width:20px\" value=\"$fasenr\"></td></tr>\n";
			else print "<tr><td colspan=\"1\"><span title='Klik for at ændre navnet'><big><b><a href=\"../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]\" style=\"text-decoration:none\">$tjekpunkt[$x]</a></b></big></td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"ny_fase[$x]\" style=\"text-align:right;width:20px\" value=\"$fasenr\"></span></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) { 
			print "<input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'>\n";
			if ($ret==$id[$x]) print "<tr><td title=\"$assign_id[$x]==$l_id\"><b><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"tjekpunkt[$x]\" size=\"20\" value=\"$tjekpunkt[$x]\"></b></td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
			else print "<tr><td title=\"$assign_id[$x]==$l_id\"><span title='Klik for at ændre navnet'><b><a href=\"../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]\" style=\"text-decoration:none\">".$tjekpunkt[$x]."</a></b></td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></span></td></tr>\n";
		}
		if ($punkt_id[$x]) { 
			print "<input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'>\n";
			if ($ret==$id[$x]) print "<tr><td title=\"$assign_id[$x]==$l_id\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"tjekpunkt[$x]\" size=\"20\" value=\"$tjekpunkt[$x]\"></td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
			else print "<tr><td title=\"$assign_id[$x]==$l_id\"><span title='Klik for at ændre navnet'><a href=\"../systemdata/diverse.php?sektion=tjekliste&ret=$id[$x]\" style=\"text-decoration:none\">".$tjekpunkt[$x]."</a></td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></span></td></tr>\n";
		}	
		if ($gruppe_id[$x] && $gruppe_id[$x] != $gruppe_id[$x+1]) {
			print "<input type=\"hidden\" name=\"fase[$x]\" value='$fase[$x]'>\n";
			print "<input type=\"hidden\" name=\"gruppe_id[$x]\" value='$gruppe_id[$x]'>\n";
#				print "<input type=\"hidden\" name=\"assign_id[$x]\" value='$assign_id[$x]'>\n";
			print "<tr><td>Nyt tjek punkt</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"nyt_tjekpunkt[$x]\" size=\"20\" value=\"\"></td></tr>\n";
		}
		if ($liste_id[$x] && $liste_id[$x] != $liste_id[$x+1]) {
			print "<input type=\"hidden\" name=\"fase[$x]\" value='$fase[$x]'>\n";
			print "<input type=\"hidden\" name=\"liste_id[$x]\" value='$liste_id[$x]'>\n";
#			print "<input type=\"hidden\" name=\"liste_id[$x]\" value='$assign_id[$x]'>\n";
			print "<tr><td colspan=\"6\"></td></tr>\n";
			print "<tr><td><b>Ny tjek gruppe</b></td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"ny_tjekgruppe[$x]\" size=\"20\" value=\"\"></td></tr>\n";
		}
	}
	print "<tr><td colspan=\"6\"><hr></td></tr>\n";
#	$ny_fase=$fase[$x]+1;
	print "<input type=\"hidden\" name=\"ret\" value='$ret'>\n";
	print "<tr><td>Ny tjekliste</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"ny_tjekliste\" size=\"20\" value=\"\"></td></tr>\n";
	print "<tr><td><br></td></tr>\n";
	print "<td><br></td><td><br></td><td><br></td><td align = \"center\"><input type=\"submit\" accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>\n";
	print "</form>\n";


#		} 
#		if ($assign_id[$x] && $assign_id[$x]==$g_id) {
#			print "<tr><td title=\"$assign_id[$x]==$g_id\">".$tjekpunkt[$x]."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
#			$p_id=$id[$x];
#			if ($assign_id[$x+1]!=$l_id) {
#				print "<tr><td>Nyt tjek punkt</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"nyt_tjekpunkt\" size=\"20\" value=\"\"></td></tr>\n";
#			}
#		} elseif (($assign_id[$x-1] && $assign_id[$x-1]) && $g_id) {
#				print "<input type=\"hidden\" name=\"gruppe_id[$x]\" value='$g_id'>\n";
#				print "<tr><td>Nyt tjek punkt</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"nyt_tjekpunkt[$x]\" size=\"20\" value=\"\"></td></tr>\n";
#		}

#		print "<tr><td>Nyt tjek punkt</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"nyt_tjekpunkt[$x]\" size=\"20\" value=\"\"></td>
#		<td align=\"center\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"overskrift[$x]\"></td></tr>\n";
#echo "$liste_id!=$id[$x]<br>\n";
#		if ($liste_id!=$id[$x]) {
#			print "<input type=\"hidden\" name=\"liste_id[$x]\" value='$liste_id'>\n";
#			print "<tr><td>$id[$x] Ny tjek gruppe</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" name=\"ny_tjekgruppe[$x]\" size=\"20\" value=\"\"></td></tr>\n";
#		}
	#	print "</tbody></table><td></tr>\n";


} # endfunc tjeklister

function docubizz() {
	global $bgcolor;
	global $bgcolor5;

	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(docubizz) {
		if (docubizz.box3.value != docubizz.pw2.value) {
		alert("Begge adgangskoder skal v&aelig;re ens.");
		docubizz.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$q = db_select("select * from grupper where art = 'DocBiz'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode=$r['box3'];
	$ftp_dnld_mappe=$r['box4'];
	$ftp_upld_mappe=$r['box5'];

	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>DocuBizz</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";

	print "<form name=docubizz action=diverse.php?sektion=docubizz method=post onsubmit=\"return Form1_Validator(this)\">";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td>Navn eller IP p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box1 size=20 value=\"$ftpsted\"></td></tr>";
	print "<tr><td>Mappe til download p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box4 size=20 value=\"$ftp_dnld_mappe\"></td></tr>";
	print "<tr><td>Mappe til upload p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box5 size=20 value=\"$ftp_upld_mappe\"></td></tr>";
	print "<tr><td>Brugernavn p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box2 size=20 value=\"$ftplogin\"></td></tr>";
	print "<tr><td>Adgangskode til ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=password name=box3 size=20 value=\"$ftpkode\"></td></tr>";
	print "<tr><td>Gentag adgangskode</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=password name=pw2 size=20 value=\"$ftpkode\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td><tr>";
	print "</form>";
	print "<form name=upload_dbz action=diverse.php?sektion=upload_dbz method=post>";
	print "<tr><td><br></td></tr>";
	print "<tr><td colspan=3>Opdater Docubizz server</td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Send data\" name=\"submit\"></td><tr>";
	print "</form>";

} # endfunc docubizz

function ftp() {
	global $s_id;
	global $bgcolor;
	global $bgcolor5;

	?>
	<script Language="JavaScript">
	<!--
	function Form1_Validator(ftp) {
		if (ftp.box3.value != ftp.pw2.value) {
		alert("Begge adgangskoder skal v&aelig;re ens.");
		ftp.box3.focus();
		return (false);
		}
	}
	//--></script>

	<?php
	$r=db_fetch_array(db_select("select * from grupper where art = 'FTP'",__FILE__ . " linje " . __LINE__));
	$id=$r['id'];
	$ftpsted=$r['box1'];
	$ftplogin=$r['box2'];
	$ftpkode='********';
	$ftp_bilag_mappe=$r['box4'];
	$ftp_dokument_mappe=$r['box5'];
	if ($r['box6']) {
		$intern_ftp='checked';
			include("../includes/connect.php");
			$r=db_fetch_array(db_select("select box1,box2 from diverse where beskrivelse='FTP' and nr='1'"));
			$box1=$r['box1'];
			$box2=$r['box2'];
			include("../includes/online.php");
	} else {
		$intern_ftp=NULL;
		if ($ftpsted==$box1 && $ftplogin==$box2) {
			$ftpsted=NULL;
			$ftplogin=NULL;
			$ftp_bilag_mappe=NULL;
			$ftp_dokument_mappe=NULL;
		}
	}
	if (!$ftp_bilag_mappe) $ftp_bilag_mappe='bilag';
	if (!$ftp_dokument_mappe) $ftp_dokument_mappe='dokumenter';

#cho "intern_ftp $intern_ftp<br>";
	
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>FTP</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<tr><td colspan=6>Denne sektion indeholder de informationer, som er n&oslash;dvendige for at kunne h&aring;ndtere scannede bilag</td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<form name=ftp action=diverse.php?sektion=ftp method=post onsubmit=\"return Form1_Validator(this)\">";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(212,$sprog_id)."\">".findtekst(211,$sprog_id)."</td><td colspan=\"2\" title=\"".findtekst(212,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"box6\" $intern_ftp></td></tr>";
	if (!$intern_ftp) {
		print "<tr><td>Navn eller IP p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box1 size=20 value=\"$ftpsted\"></td></tr>";
		print "<tr><td>Brugernavn p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box2 size=20 value=\"$ftplogin\"></td></tr>";
		print "<tr><td>Adgangskode til ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=password name=box3 size=20 value=\"$ftpkode\"></td></tr>";
		print "<tr><td>Gentag adgangskode</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=password name=pw2 size=20 value=\"$ftpkode\"></td></tr>";
		print "<tr><td>Mappe til bilag p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box4 size=20 value=\"$ftp_bilag_mappe\"></td></tr>";
		print "<tr><td>Mappe til dokumenter p&aring; ftpserver</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text name=box5 size=20 value=\"$ftp_dokument_mappe\"></td></tr>";
		print "<tr><td><br></td></tr>";
	}
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td><tr>";
	print "</form>";
} # endfunc ftp

function orediff($diffkto)
{
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$q = db_select("select * from grupper where art = 'OreDif'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	$maxdiff=dkdecimal($r['box1']);
	if (!$diffkto) $diffkto=$r['box2'];

	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(170,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";

	print "<form name=orediff action=diverse.php?sektion=orediff method=post onsubmit=\"return Form1_Validator(this)\">";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(171,$sprog_id)."\">".findtekst(172,$sprog_id)."</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" name=box1 size=3 value=\"$maxdiff\"></td></tr>";
	print "<tr><td title=\"".findtekst(173,$sprog_id)."\">".findtekst(174,$sprog_id)."</td><td colspan=2><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" name=box2 size=3 value=\"$diffkto\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = center><input style=\"width: 8em\" type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td><tr>";
	print "</form>";

} # endfunc orediff.
function massefakt () {
	global $sprog_id;
	global $docubizz;
	global $bgcolor;
	global $bgcolor5;

	$folge_s_tekst=NULL;$gruppevalg=NULL;$kuansvalg=NULL;
	$ref=NULL; $kua=NULL; $smart=NULL;
	$kort=NULL; $batch=NULL;

	$q = db_select("select * from grupper where art = 'MFAKT' and kodenr = '1'",__FILE__ . " linje " . __LINE__);
	$r = db_fetch_array($q);
	$id=$r['id'];
	if ($r['box1'] == 'on') $brug_mfakt='checked';
	else $brug_mfakt='';
	if ($r['box2'] == 'on') $brug_dellev='checked';
	else $brug_dellev='';
	$levfrist=$r['box3'];

	print "<form name=diverse action=diverse.php?sektion=massefakt method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(200,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id value='$id'>";
	print "<tr><td title=\"".findtekst(202,$sprog_id)."\">".findtekst(201,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=brug_mfakt $brug_mfakt></td></tr>";
	print "<tr><td title=\"".findtekst(204,$sprog_id)."\">".findtekst(203,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=brug_dellev $brug_dellev></td></tr>";
	print "<tr><td title=\"".findtekst(206,$sprog_id)."\">".findtekst(205,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=text style=\"text-align:right\" name=levfrist size=1 value=\"$levfrist\"></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
} # endfunc massefakt
#####################################################
function pos_valg () {
	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$kassekonti=array();
	$afd=array();

	$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '1'",__FILE__ . " linje " . __LINE__));
	$id1=$r['id'];
	$kasseantal=$r['box1']*1;
	$kassekonti=explode(chr(9),$r['box2']);
	$afd=explode(chr(9),$r['box3']);
	$kortantal=$r['box4']*1;
	$korttyper=explode(chr(9),$r['box5']);
	$kortkonti=explode(chr(9),$r['box6']);
	$moms=explode(chr(9),$r['box7']);
	$rabatvareid=$r['box8']*1;
	($r['box9'])?$straksbogfor='checked':$straksbogfor='';
	($r['box10'])?$udskriv_bon='checked':$udskriv_bon='';
	($r['box11'])?$vis_kontoopslag='checked':$vis_kontoopslag='';
	($r['box12'])?$vis_hurtigknap='checked':$vis_hurtigknap='';
	$timeout=$r['box13']*1;
	($r['box14'])?$vis_indbetaling='checked':$vis_indbetaling='';

	if ($r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__))) {
		$id2=$r['id'];
	} else {
		db_modify("insert into grupper(beskrivelse,kode,kodenr,art,box1,box2,box3,box4,box5,box6,box7,box8,box9,box10,box11,box12,box13,box14)values('Pos valg','','2','POS','0','','','','','','','','','','','','','')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'POS' and kodenr = '2'",__FILE__ . " linje " . __LINE__)); 
		$id2=$r['id'];
	}
	$kasseprimo=dkdecimal($r['box1']);
	($r['box2'])?$optalassist='checked':$optalassist=NULL;
	$printer_ip=explode(chr(9),$r['box3']);
	$terminal_ip=explode(chr(9),$r['box4']);
	$betalingskort=explode(chr(9),$r['box5']); #20131210
	$div_kort_kto=$r['box6']; #20140129
	if ($r['box7']) $bord=explode(chr(9),$r['box7']); #20140506
		
	$q = db_select("select * from grupper where art = 'POSBUT'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) $posbuttons++;

	if ($rabatvareid) {
		$r = db_fetch_array(db_select("select varenr from varer where id = '$rabatvareid'",__FILE__ . " linje " . __LINE__));
		$rabatvarenr=$r['varenr'];
	}

	$x=0;
	if ($kasseantal) {
		$q = db_select("select * from grupper where art = 'AFD' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$afd_nr[$x]=$r['kodenr'];
			$afd_navn[$x]=$r['beskrivelse'];
		}
		$afd_antal=$x;
		$x=0;
		$q = db_select("select * from grupper where art = 'SM' order by kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$x++;
			$moms_nr[$x]=$r['kodenr'];
			$moms_navn[$x]=$r['beskrivelse'];
		}
		$moms_antal=$x;
	}

	print "<form name=diverse action=diverse.php?sektion=pos_valg method=post>";
	print "<tr><td colspan=6><hr></td></tr>";
	print "<tr bgcolor=\"$bgcolor5\"><td colspan=6><b><u>".findtekst(265,$sprog_id)."</u></b></td></tr>";
	print "<tr><td colspan=6><br></td></tr>";
	print "<input type=hidden name=id1 value='$id1'>";
	print "<input type=hidden name=id2 value='$id2'>";
	print "<tr><td title=\"".findtekst(266,$sprog_id)."\">".findtekst(267,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right\" size=\"1\" name=\"kasseantal\" value=\"$kasseantal\"></td></tr>";
#	print "<tr><td title=\"".findtekst(285,$sprog_id)."\">".findtekst(285,$sprog_id)."</td>";
	if ($kasseantal) {
		print "<tr><td title=\"".findtekst(288,$sprog_id)."\">".findtekst(287,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" size=\"7\" name=\"rabatvarenr\" value=\"$rabatvarenr\"></td></tr>";
		print "<tr><td colspan=\"6\"><hr></td></tr>";
		print "<tr><td>".findtekst(272,$sprog_id)."</td>";
		if ($afd_antal) print "<td title=\"".findtekst(273,$sprog_id)."\">".findtekst(274,$sprog_id)."</td>";
		if ($moms_antal) print "<td title=\"".findtekst(285,$sprog_id)."\">".findtekst(286,$sprog_id)."</td>";
		print "<td title=\"".findtekst(275,$sprog_id)."\">".findtekst(276,$sprog_id)."</td>";
		print "<td title=\"".findtekst(635,$sprog_id)."\"><!--Tekst 635-->".findtekst(634,$sprog_id)."<!--Tekst 634--></td>";
		print "<td title=\"".findtekst(637,$sprog_id)."\"><!--Tekst 637-->".findtekst(636,$sprog_id)."<!--Tekst 636--></td></tr>";
		for($x=0;$x<$kasseantal;$x++) {
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			if ($afd_antal) {
				print "<td title=\"".findtekst(273,$sprog_id)."\"><SELECT class=\"inputbox\" NAME=afd_nr[$x] title=\"".findtekst(273,$sprog_id)."\">";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]==$afd_nr[$y]) print "<option value=\"$afd_nr[$y]\">$afd_navn[$y]</option>";
				}
				print "<option value=\"0\"></option>";
				for($y=1;$y<=$afd_antal;$y++) {
					if ($afd[$x]!=$afd_nr[$y]) print "<option value=\"$afd_nr[$y]\">$afd_navn[$y]</option>";
				}
-				print "</SELECT></td>";;
			}
			if ($moms_antal) {
				print "<td title=\"".findtekst(273,$sprog_id)."\"><SELECT class=\"inputbox\" NAME=moms_nr[$x] title=\"".findtekst(273,$sprog_id)."\">";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]==$moms_nr[$y]) print "<option value=\"$moms_nr[$y]\">$moms_navn[$y]</option>";
				}
				print "<option value=\"0\"></option>";
				for($y=1;$y<=$moms_antal;$y++) {
					if ($moms[$x]!=$moms_nr[$y]) print "<option value=\"$moms_nr[$y]\">$moms_navn[$y]</option>";
				}
-				print "</SELECT></td>";;
			}
			print "<td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right;width:50px;\" name=\"kassekonti[$x]\" value=\"$kassekonti[$x]\"></td>";
			if (!$printer_ip[$x])$printer_ip[$x]='localhost';
			print "<td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right;width:100px;\" name=\"printer_ip[$x]\" value=\"$printer_ip[$x]\"></td>";
			print "<td align=\"center\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right;width:100px;\" name=\"terminal_ip[$x]\" value=\"$terminal_ip[$x]\"></td></tr>";
		}
	}
	print "<tr><td colspan=\"6\"><hr></td></tr>";
	print "<tr><td title=\"".findtekst(279,$sprog_id)."\">".findtekst(280,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right\" size=\"1\" name=\"kortantal\" value=\"$kortantal\"></td></tr>";
	if ($kortantal) {
		print "<tr><td></td><td title=\"".findtekst(281,$sprog_id)."\">".findtekst(283,$sprog_id)."</td>";
		print "<td title=\"".findtekst(282,$sprog_id)."\">".findtekst(284,$sprog_id)."</td>";
		print "<td title=\"".findtekst(641,$sprog_id)."\">".findtekst(640,$sprog_id)."</td></tr>";
		print "<tr><td colspan=\"6\"></td></tr>";
		for($x=0;$x<$kortantal;$x++) {
			($betalingskort[$x])?$betalingskort[$x]='checked':$betalingskort[$x]=NULL; # 20131210
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			print "<td title=\"".findtekst(281,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:left\" size=\"15\" name=\"korttyper[$x]\" value=\"$korttyper[$x]\"></td>";
			print "<td title=\"".findtekst(282,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right\" size=\"3\" name=\"kortkonti[$x]\" value=\"$kortkonti[$x]\"></td>";
			print "<td title=\"".findtekst(641,$sprog_id)."\" align=\"center\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" style=\"text-align:right\" name=\"betalingskort[$x]\" $betalingskort[$x]></td></tr>"; #20131210
		}
		$bet_term=NULL;
		for ($x=0;$x<count($terminal_ip);$x++) {
			if ($terminal_ip[$x]) $bet_term=1; #Så er der betalinggsterminal på min 1. kasse. 
		}
		if ($bet_term) {
			$tmp++;
			print "<tr bgcolor=$bgcolor5>";
			print "<td>$tmp</td>";
			print "<td title=\"".findtekst(643,$sprog_id)."\">".findtekst(642,$sprog_id)."</td>";
			print "<td title=\"".findtekst(643,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right\" size=\"3\" name=\"div_kort_kto\" value=\"$div_kort_kto\"></td>";
			print "<td title=\"".findtekst(643,$sprog_id)."\" align=\"center\"><INPUT DISABLED=\"disabled\" CLASS=\"inputbox\" TYPE=\"checkbox\" style=\"text-align:right\" checked></td></tr>";
		}
	}
	print "<tr><td colspan=\"6\"><hr></td></tr>";
	# 20140508 ->
	$bordantal=count($bord); 
	print "<tr><td title=\"".findtekst(673,$sprog_id)."\">".findtekst(674,$sprog_id)."</td><td><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right\" size=\"1\" name=\"bordantal\" value=\"$bordantal\"></td></tr>";
	if ($bordantal) {
		print "<tr><td></td><td title=\"".findtekst(675,$sprog_id)."\">".findtekst(676,$sprog_id)."</td></tr>";
		print "<tr><td colspan=\"6\"></td></tr>";
		for($x=0;$x<$bordantal;$x++) {
			print "<tr bgcolor=$bgcolor5>";
			$tmp=$x+1;
			print "<td>$tmp</td>";
			print "<td title=\"".findtekst(675,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:left\" size=\"15\" name=\"bord[$x]\" value=\"$bord[$x]\"></td></tr>";
		}
	}
	print "<tr><td colspan=\"6\"><hr></td></tr>";
	# <- 20140508
	print "<tr><td title=\"".findtekst(453,$sprog_id)."\">".findtekst(454,$sprog_id)."</td><td title=\"".findtekst(453,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"straksbogfor\" $straksbogfor></td></tr>";
	print "<tr><td title=\"".findtekst(456,$sprog_id)."\">".findtekst(457,$sprog_id)."</td><td title=\"".findtekst(456,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"udskriv_bon\" $udskriv_bon></td></tr>";
	print "<tr><td title=\"".findtekst(458,$sprog_id)."\">".findtekst(459,$sprog_id)."</td><td title=\"".findtekst(458,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"vis_hurtigknap\" $vis_hurtigknap></td></tr>";
	print "<tr><td title=\"".findtekst(460,$sprog_id)."\">".findtekst(461,$sprog_id)."</td><td title=\"".findtekst(460,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"vis_kontoopslag\" $vis_kontoopslag></td></tr>";
	print "<tr><td title=\"".findtekst(464,$sprog_id)."\">".findtekst(465,$sprog_id)."</td><td title=\"".findtekst(464,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"vis_indbetaling\" $vis_indbetaling></td></tr>";
	print "<tr><td title=\"".findtekst(462,$sprog_id)."\">".findtekst(463,$sprog_id)."</td><td title=\"".findtekst(462,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right;width:25px\" name=\"timeout\" value=\"$timeout\"></td></tr>";
	print "<tr><td title=\"".findtekst(631,$sprog_id)."\">".findtekst(630,$sprog_id)."</td><td title=\"".findtekst(631,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"text\" style=\"text-align:right;width:100px\" name=\"kasseprimo\" value=\"$kasseprimo\"></td></tr>";
	print "<tr><td title=\"".findtekst(633,$sprog_id)."\">".findtekst(632,$sprog_id)."</td><td title=\"".findtekst(633,$sprog_id)."\"><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"optalassist\" $optalassist></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<tr><td><br></td></tr>";
	print "<td><br></td><td><br></td><td><br></td><td align = center><input type=submit accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"submit\"></td>";
	print "</form>";
	print "<tr><td><a href=posmenuer.php>Klik her for at oprette / rette genvejstaster p&aring; kassesiden</a></td></tr>";
} # endfunc pos
#####################################################
function testftp($box1,$box2,$box3,$box4,$box5,$box6) {
 	global $db;
	global $exec_path;
	if (!$exec_path) $exec_path="\usr\bin";

	if ($box6) {
		$fp=fopen("../temp/$db/ftpscript1","w");
		if ($fp) {
			fwrite ($fp,"set confirm-close no\nmkdir ".$_SERVER['SERVER_NAME']."\ncd ".$_SERVER['SERVER_NAME']."\nmkdir $db\nbye\n");
		}
		fclose($fp);
		
		$tmp=$_SERVER['SERVER_NAME']."/";
		$tmp=str_replace($tmp,'',$box1);
		$tmp1=$db."/";
		$tmp=str_replace($tmp1,'',$tmp);
		$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$tmp." < ftpscript1 > ftplog1\nrm testfil.txt\n";
		system ($kommando);
	}
	$fp=fopen("../temp/$db/testfil.txt","w");
	if ($fp) {
		fwrite ($fp,"testfil fra saldi\n");
	}
	fclose($fp);
	$fp=fopen("../temp/$db/ftpscript2","w");
	if ($fp) {
		fwrite ($fp, "mkdir $box4\nmkdir $box5\ncd $box4\nput testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1." < ftpscript2 > ftplog2\nrm testfil.txt\n";
	system ($kommando);
	$fp=fopen("../temp/$db/ftpscript3","w");
	if ($fp) {
		fwrite ($fp, "get testfil.txt\ndel testfil.txt\nbye\n");
	}
	fclose($fp);
	$kommando="cd ../temp/$db\n$exec_path/ncftp ftp://".$box2.":".$box3."@".$box1."/".$box4." < ftpscript3 > ftplog3\n"; #rm ftpscript\nrm ftplog\n";
	system ($kommando);
	($box6)?$tmp="Dokumentserver":$tmp="FTP";
	if (file_exists("../temp/$db/testfil.txt")) print "<BODY onLoad=\"JavaScript:alert('$tmp tilg&aelig;ngelig')\">";
	else print "<BODY onLoad=\"JavaScript:alert('$tmp ikke tilg&aelig;ngelig')\">";
}

?>
