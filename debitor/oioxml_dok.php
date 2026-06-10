<?php #topkode_start
@session_start();
$s_id=session_id();

// ---------debitor/oioxml_dok.php----patch 3.2.5---2012-01-19--
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
// En dansk oversaetelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2012 DANOSOFT ApS
// ----------------------------------------------------------------------

$css="../css/standard.css";

#$form=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/oioxmlfunk.php");
include("../includes/var2str.php");

$id=if_isset($_GET['id']);
$doktype=if_isset($_GET['doktype']);
$returside=if_isset($_GET['returside']);
if ($popup) $returside= "../includes/luk.php";
else $returside= "ordre.php?id=$id";


$bg="nix";

## TIL TEST - START
#$id = 2;
#$doktype = "faktura";
## TIL TEST - END

# Udskriv OIOXML-faktura
$printfilnavn="doktype-".$doktype."_dokid-".$id.".xml";

if ( ! file_exists("../temp/$db") ) mkdir("../temp/$db", 0775);


print "<div align=\"center\">
<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>
	<tr><td height = \"25\" align=\"center\" valign=\"top\">
		<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"4\" cellpadding=\"0\"><tbody>
			<td width=\"10%\" align=center><div class=\"top_bund\"><a href=\"$returside\" accesskey=L>Luk</a></div></td>
			<td width=\"80%\" align=center><div class=\"top_bund\">$title</a></div></td>
			<td width=\"10%\" align=center><div class=\"top_bund\"><br></div></td>
			 </tr>
			</tbody></table>
	</td></tr>
 <tr><td valign=\"top\">
<table cellpadding=\"1\" cellspacing=\"1\" border=\"0\" width=\"100%\" valign = \"top\">
<tbody>";


$fp=fopen("../temp/$db/$printfilnavn","w");
if ((strtolower($doktype)=="faktura")||(strtolower($doktype)=="kreditnota")) fwrite($fp,oioxmldoc_faktura($id, $doktype, $testdok));
fclose($fp);

print "\n\n\n<h1>Gem OIOXML-filen</h1>\n\n";
print "<p>Gem OIOXML-filen ved at h&oslash;jreklikke p&aring; linket nedenfor og v&aelig;lge <b>Gem link som...</b> eller lignende.</p>\n\n";
print "<p><a href=\"../temp/$db/$printfilnavn\" title=\"Gem OIOXML-filen ved at h&oslash;jreklikke p&aring; linket og v&aelig;lge 'Gem link som...' eller lignende\">";
print "OIOXML-filen $printfilnavn</a></p>\n\n";

print "\n<h1>Send OIOXML-filen</h1>\n\n";
print "<p><b>OBS Pr. 01. december 2011 kan man ikke længere sende OIOXML gennem NemHandel programmet</b></p>\n\n";
print "<p>I stedet kan filen sendes gennem <a href=\"http://www.sproom.dk\" target=\"blank\">www.sproom.dk</a></p>\n\n";
print "<p>Hvis du stadig vil bruge NemHandel programmet skal du i stedet udskrive til OIOUBL\n<br>";

print "\n<h1>Test OIOXML-filen</h1>\n\n";
print "<p>Hvis du vil teste OIOXML-filen, s&aring; kan validering af filen ske med \n";
print "<a href=\"http://xmltools.oio.dk/oioonlinevalidator/index.aspx\" title=\"Offentlige Information Onlines OIOXML Validator\">OIOXML Validator</a>.</p>\n";
print "<p>Validatoren kr&aelig;ver, at hele indholdet af OIOXML-filen kopieres ind i tekstfeltet. S&aring; &aring;bn filen i en teksteditor, \n";
print "mark&eacute;r det hele med CTRL-A, kopi&eacute;r med CTRL-C og inds&aelig;t det i tekstfeltet med CTRL-V.</p>\n\n";
print "<p>Hvis OIOXML-filen ikke validerer s&aring; send filen vedlagt en e-mail til \n";
print "<a href=\"mailto:oio@saldi.dk\">oio@saldi.dk</a>, s&aring; vi kan finde &aring;rsagen. P&aring; forh&aring;nd tak.</p>\n\n";  

# Kommenteringern af disse linjer skal slettes, naar bidragssiden er paa plads - slet ogsaa slut linjen \n"; 
/*
print "\n<h1>St&oslash;t udviklingen af OIOUBL</h1>\n\n";
print "<p>Udviklerne hos DANOSOFT, som st&aring;r for udviklingen af SALDI, er i gang med at udvikle fuld underst&oslash;ttelse af \n";
print "<strong>OIOUBL</strong>, som er den fuldautomatiske efterf&oslash;lger til OIOXML.</p>\n\n";
print "<p>Med OIOUBL kan du i fremtiden h&aring;ndtere katalogopslag, tilbud, ordrer, fakturaer, kreditnotaer, p&aring;mindelser, rykkerer \n";
print "med meget mere direkte i SALDI - alts&aring; uden brug af et \n";
print "ekstra program - til det offentlige som til private virksomheder, der ogs&aring; benytter OIOUBL.</p>\n\n";
print "<p>I prioriteringen af udviklingsopgaver er &oslash;konomi en meget v&aelig;sentlig faktor, s&aring; st&oslash;t udviklingen \n";
print "af netop OIOUBL til SALDI ved at give et bidrag - sm&aring; som store - alle er velkomne, s&aring; udviklingen fremskyndes.<p>\n\n";
print "<p>Se mere p&aring; hjemmesiden <a href=\"http://saldi.dk/bidrag\">Bidrag til SALDI</a>.</p>\n\n";
*/
print "</body></html>";

?>
