<?php
// --- includes/documents.php -----patch 4.0.8 ----2024-01-18------------
//                           LICENSE
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. 
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
//20230622 - LOE  Updated file path and some related modifications.

@session_start();
$s_id=session_id();
$css="../css/std.css";

$title="Documents";
print '<script src="../javascript/jquery-3.6.4.min.js"></script>';
print '<link rel="stylesheet" type="text/css" href="../css/dragAndDrop.css">';
print "<script LANGUAGE=\"javascript\" TYPE=\"text/javascript\" SRC=\"../javascript/dragAndDrop.js\"></script>";


include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

if (!isset($userId) || !$userId) $userId = $bruger_id;

print "<div align=\"center\">";
$fokus=$dokument = $openPool=$docFocus=$deleteDoc=$showDoc= $poolFile=$moveDoc=$kladde_id=$bilag=$source=$sourceId=null;
if(($_GET)||($_POST)) {

	$funktion=if_isset($_GET['funktion']);
	if (isset($_GET['sourceId'])) {
		$bilag		  = if_isset($_GET['bilag']);
		$fokus		  = if_isset($_GET['fokus']);
		$docFocus	  = if_isset($_GET['docFocus']);
		$sourceId   = if_isset($_GET['sourceId'],0);
		$source     = if_isset($_GET['source']);
		$showDoc    = if_isset($_GET['showDoc']);
		$deleteDoc  = if_isset($_GET['deleteDoc']);
		$moveDoc  	= if_isset($_GET['moveDoc']);
		$kladde_id  = if_isset($_GET['kladde_id']);
		$dokument   = if_isset($_GET['dokument']);
		$openPool    = if_isset($_GET['openPool']);
		$poolFile    = if_isset($_GET['poolFile']);
	}
	if (isset($_POST['sourceId'])) {
		$sourceId  = $_POST['sourceId'];
		$source    = $_POST['source'];	
	}
}
$params = "kladde_id=$kladde_id&bilag=$bilag&source=$source&sourceId=$sourceId&fokus=$fokus";

if (isset($_GET['test'])) exit;
#xit;
$qtxt = "select var_value from settings where var_name = 'globalId'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $globalId = $r['var_value'];
else alert ('Missing global ID');

if (file_exists('../owncloud')) $docFolder = '../owncloud';
elseif (file_exists('../bilag')) $docFolder = '../bilag';
elseif (file_exists('../documents')) $docFolder = '../documents';

if ($dokument) {
	if (file_exists("$docFolder/$db/bilag/kladde_$kladde_id/bilag_$sourceId")) {
			include("docsIncludes/convertOldDoc.php");
		} else print "$dokument Ikke fundet";
}
#$openPool,$sourceId,$source,$bilag,$fokus,$poolFile,$docFolder
#echo $poolParams;


// ---------- Main table start ---------
print "<table width=\"100%\" height=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>"; 
print "<tr><td colspan= \"3\" height = \"25\" align=\"center\" valign=\"top\">";
// ---------- Header table start ---------
print "<table width=\"100%\" align=\"center\" border=\"0\" cellspacing=\"2\" cellpadding=\"0\"><tbody>";
include("docsIncludes/header.php");
// ---------- Header table end ---------
print "</tbody></table>";
print "</td></tr><tr><td width = '20%'>";
// ---------- Left table start ---------
print "<table width=\"100%\" height=\"98%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
#cho "$openPool || $poolFile<br>";
if ($openPool) {
	include ("docsIncludes/docPool.php");
	docPool($sourceId,$source,$kladde_id,$bilag,$fokus,$poolFile,$docFolder,$docFocus);
	exit;
}
#xit;
if ($moveDoc) include("docsIncludes/moveDoc.php");
elseif ($deleteDoc) include("docsIncludes/deleteDoc.php");
include("docsIncludes/listDocs.php");
include("docsIncludes/uploadDoc.php"); 

// Generate the URL with the query parameters
#$targetPage = "docsIncludes/emailDoc.php?" . $queryParameters;
$poolParams =
	"openPool=1"."&".
	"kladde_id=$kladde_id"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"poolFile=$poolFile"."&".
	"docFolder=$docFolder"."&".
	"sourceId=$sourceId"."&".
	"source=$source";
$targetPage = "documents.php?" . $poolParams;
#****************
print "<br>Bilag kan sendes til<br>";
print "<a href='mailto:bilag_".$db."@".$_SERVER['SERVER_NAME']."'>";
print "bilag_".$db."@".$_SERVER['SERVER_NAME']."</a><br><br>\n";
print '<a href="' . $targetPage . '">';
print "<button id=\"emailD\">Dokument pulje</button>";
print '</a><br>';
;



$dropZone = "<div id='dropZone' ondrop='handleDrop(event)' ondragover='handleDragOver(event)' style='width: 200px; height: 150px; border: 2px dashed #ccc; text-align: center; padding: 20px;'>
    <span id='dropText'>Drop pdf file here</span>
</div>";

$clipImage = "<span class='clip-image drop-zone-container' title='Drag and Drop the file here'>
    {$dropZone}
</span>";

print $clipImage;

// Check if the confirm flag is set
if (isset($_GET['confirm_upload']) && $_GET['confirm_upload'] == 'true') {
    // reload the page after upload
	header('Location: ' . $_SERVER['REQUEST_URI']);

}




print "</td></tr>";




// Print the JavaScript code used in dragAndDrop.js 
print "<script>
var clipVariables = {
sourceId: $sourceId ,
kladde_id: $kladde_id,
bilag: $bilag,
fokus: '$fokus',
source: '$source'
};
</script>";



// ---------- Left table end ---------
print "</tbody></table>";
print "</td><td width = '80%'>";
// ---------- Right table start ---------
print "<table width=\"100%\" height=\"98%\" border=\"1\" cellspacing=\"0\" cellpadding=\"0\"><tbody>";
include("docsIncludes/showDoc.php");
// ---------- Right table end ---------
print "</tbody></table>";
print "</td></tr>";
// ---------- Main table start ---------
print "</tbody></table>";
print "</body></html>";

?>
