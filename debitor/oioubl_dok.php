<?php #topkode_start
@session_start();
$s_id=session_id();

// ---------debitor/oioubl_dok.php----patch 3.2.7---2012-01-26---------
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

#$testdok="Tester"; # Skal slettes naar test er faerdig
$css="../css/standard.css";

#$form=array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/oioublfunk.php");
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

# Udskriv OIOUBL-faktura
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
if ((strtolower($doktype)=="faktura")||(strtolower($doktype)=="kreditnota")) fwrite($fp,oioubldoc_faktura($id, $doktype, $testdok));
fclose($fp);

print "\n\n\n<h1>Gem OIOUBL-filen</h1>\n\n";
print "<p>Gem OIOUBL-filen ved at h&oslash;jreklikke p&aring; linket nedenfor og v&aelig;lge <b>Gem link som...</b> eller lignende.</p>\n\n";
print "<p><a href=\"../temp/$db/$printfilnavn\" title=\"Gem OIOUBL-filen ved at h&oslash;jreklikke p&aring; linket og v&aelig;lge 'Gem link som...' eller lignende\">";
print "OIOUBL-filen $printfilnavn</a></p>\n\n";

print "\n<h1>Send OIOUBL-filen</h1>\n\n";
print "Du kan bl.a. sende oioubl-filen via <a href=\"http://www.ebconnect.dk\" target=\"blank\">ebConnect</a> eller via <a href=\"http://www.sproom.dk\" target=\"blank\">Sproom</a>\n\n";

print "\n<h1>Test OIOUBL-filen</h1>\n\n";
print "<p>Hvis du vil teste OIOUBL-filen kan validering af filen ske med \n";
print "<a href=\"http://www.oioubl.info/validator/\" title=\"ITST - OIOUBL Online Validator\">OIOUBL Validator</a>.</p>\n";
print "<p>Hvis OIOUBL-filen ikke validerer s&aring; send filen vedlagt en e-mail til \n";
print "<a href=\"mailto:oio@saldi.dk\">oio@saldi.dk</a>, s&aring; vi kan finde &aring;rsagen. P&aring; forh&aring;nd tak.</p>\n\n";  

print "</body></html>";

?>
