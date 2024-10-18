<?php
// --- includes/docsIncludes/uploadDoc.php -----patch 4.1.0 ----2024-05-01------
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

$path = "$docFolder/$db/finance/$kladde_id/$sourceId/";
$showDoc = trim($path.$dokument);
if (!file_exists("$docFolder/$db/finance")) {
	mkdir ("$docFolder/$db/finance",0777);
}
if (!file_exists("$docFolder/$db/finance/$kladde_id")) {
#	echo "opretter $docFolder/$db/finance/$kladde_id<br>";
	mkdir ("$docFolder/$db/finance/$kladde_id",0777); //Groups the individual attached files
}
if (file_exists("$docFolder/$db/finance/$kladde_id/$sourceId")) {
#	echo "$docFolder/$db/finance/$kladde_id/$sourceId Eksisterer<br>";
}
if (!file_exists("$docFolder/$db/finance/$kladde_id/$sourceId")) {
#	echo "opretter $docFolder/$db/finance/$kladde_id/$sourceId<br>";
	mkdir ("$docFolder/$db/finance/$kladde_id/$sourceId",0777);
}
$filePath = "/finance/$kladde_id/$sourceId/";
if (!file_exists($showDoc)) {
	if (rename("$docFolder/$db/bilag/kladde_$kladde_id/bilag_$sourceId","$showDoc")) {
		$filemtime = filemtime($showDoc);
		$qtxt = "insert into documents(global_id,filename,filepath,source,source_id,timestamp,user_id) values ";
		$qtxt.= "('$globalId','$dokument','$filePath','$source','$sourceId','$filemtime','$userId')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "update kassekladde set dokument = '' where id = '$sourceId'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	} else {
		alert("Move from $docFolder/$db/bilag/kladde_$kladde_id/bilag_$sourceId to $showDoc failed");
	}
} else alert("$showDoc allready exists");

if (file_exists($showDoc)) {
	if($source == 'kassekladde'){
		//print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?source=kassekladde&sourceId=$sourceId&showDoc=$showDoc\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?source=kassekladde&kladde_id=$kladde_id&bilag_id=$sourceId&sourceId=$sourceId&showDoc=$showDoc\">";
		exit;
	} else {
		print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?source=creditorOrder&sourceId=$sourceId&showDoc=$showDoc\">";
		exit;
	}
} else alert("Move to $showDoc failed");

/*
print "<tr><td width='100%' valign = 'top' align='center'>";
if($source == 'kassekladde'){

	#print "<form enctype='multipart/form-data' action='documents.php?source=$source&sourceId=$sourceId&fokus=$fokus&showDoc=".urlencode($showDoc)."' method='POST'>";
	print "<form enctype='multipart/form-data' action='documents.php?source=$source&sourceId=$sourceId&kladde_id=$kladde_id&bilag_id=$sourceId&fokus=$fokus&showDoc=".urlencode($showDoc)."' method='POST'>";
}else{
	print "<form enctype='multipart/form-data' action='documents.php?source=$source&sourceId=$sourceId&fokus=$fokus&showDoc=".urlencode($showDoc)."' method='POST'>";
	
}
print "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'>";
print findtekst(1414, $sprog_id).":<br><br><input class='inputbox' name='uploadedFile' type='file' accept='.pdf,.jpg,.png'><br><br>";
print "<input type='submit' value='".findtekst(1078, $sprog_id)."'>";
print "</form>";
#*******************
$queryParameters = 
	"sourceId=$sourceId"."&".
	"kladde_id=$kladde_id"."&".
	"source=$source"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"bilag_id=$sourceId";


// Generate the URL with the query parameters
$targetPage = "docsIncludes/emailDoc.php?" . $queryParameters;


echo '<a href="' . $targetPage . '">';
print "<button id=\"emailD\">Email files</button>";
echo '</a>';
#****************
print "</td></tr>";
*/
?>
