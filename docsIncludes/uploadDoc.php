<?php
// --- includes/docsIncludes/uploadDoc.php -----patch 4.0.8 ----2024-01-19------
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
//20230706 LOE Some modifications relating to bilag_id and kassekladde made 
//20230806 LOE bilag directory explicitly created, globalId initilized to 1


$sth = dirname(dirname(dirname(__FILE__)));

isset($_GET['bilag_id'])? $bilag_id = $_GET['bilag_id']: $bilag_id = null;
isset($_GET['bilag'])? $bilag = $_GET['bilag']: $bilag = null;
if(!isset($globalId)) $globalId =1;
if (isset($_FILES) && isset($_FILES['uploadedFile']['name'])) {
    $servername = $_SERVER['SERVER_NAME'];
   // $kladde_id = $_GET['kladde_id'];
	

	$fileTypes = array('jpg','jpeg','pdf','png');
	$fileName = basename($_FILES['uploadedFile']['name']);
	list($tmp,$fileType) = explode("/",$_FILES['uploadedFile']['type']);
	if (!in_array(strtolower($fileType),$fileTypes)) {
		alert ("File must be either pdf or jpg, not $fileType");
		$fileType = NULL;
	}
	$qtxt = "select var_value from settings where var_name = 'globalId'";
	if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $globalId = $r['var_value'];
	else alert ('Missing global ID');

	include("docsIncludes/insertDoc.php");
	exit;
/*
	$docFolder.= "/$db";

	if ($fileType && $docFolder && $source == 'creditorOrder') {
		if (!file_exists("$docFolder"))                 mkdir ("$docFolder/",0777);
		if (!file_exists("$docFolder"))                 echo __line__."<br>";
		if (!file_exists("$docFolder/creditor"))        mkdir ("$docFolder//creditor",0777);
		if (!file_exists("$docFolder/creditor"))                 echo __line__."<br>";
		if (!file_exists("$docFolder/creditor/orders")) mkdir ("$docFolder//creditor/orders",0777);
		if (!file_exists("$docFolder/creditor/orders"))                 echo __line__;
#		$tmp = floor($sourceId/1000)*1000;
#		$tmp2 = $tmp+1000;
#		$filePath = "/creditor/orders/".$tmp."-".$tmp2;
		$filePath = "/creditor/orders/$sourceId";
		if (!file_exists("$docFolder/$filePath")) mkdir ("$docFolder/$filePath",0777);
 		if (!file_exists("$docFolder/$filePath/$fileName")) {
			if(move_uploaded_file($_FILES['uploadedFile']['tmp_name'],"$docFolder/$filePath/$fileName")) {
				$qtxt = "insert into documents(global_id,filename,filepath,source,source_id,timestamp,user_id) values ";
				$qtxt.= "('$globalId','$fileName','$filePath','$source','$sourceId','". date('U') ."','$userId')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$showDoc = "$docFolder/$filePath/$fileName";
			} else alert("Upload to $docFolder/$filePath/$fileName failed");

			$showDoc = "$docFolder/$filePath/$fileName";

		} else alert("$docFolder/$filePath/$fileName allready exists");
	} elseif ($fileType && $docFolder && $servername && $source == 'kassekladde') {
        $path = "../bilag/$db/finance/$kladde_id/$sourceId/";
		$showDoc = $path.$fileName;
		if(!file_exists("$sth/bilag")) 							mkdir ("$sth/bilag",0777);
		if(!file_exists("$sth/bilag")) {
			echo "creation of $sth/bilag failed<br>";
			exit;
		}
		if (!file_exists($docFolder))                 			mkdir ($docFolder,0777);
# 		if (!file_exists("$docFolder")) echo "Ku ik oprette $docFolder<br>";
		if (!file_exists("$docFolder/finance"))        		mkdir ("$docFolder/finance",0777);
 #		if (!file_exists("$docFolder/finance")) echo "Ku ik oprette $docFolder/finance<br>";
		if (!file_exists("$docFolder/finance/$kladde_id")) 	mkdir ("$docFolder/finance/$kladde_id",0777); //Groups the individual attached files 
 	#	if (!file_exists("$docFolder/finance/$kladde_id")) echo "Ku ik oprette $docFolder/finance/$kladde_id<br>";
		if (!file_exists("$docFolder/finance/$kladde_id/$sourceId")) 	mkdir ("$docFolder/finance/$kladde_id/$sourceId",0777);
	#	if (!file_exists("$docFolder/finance/$kladde_id/$sourceId")) echo "Ku ik oprette $docFolder/finance/$kladde_id/$sourceId<br>";
		$filePath = "/finance/$kladde_id/$sourceId";
		if (!file_exists($showDoc)) {
			if ($insertFile) {
				echo "rename($poolFile,$showdoc)<br>";
				rename($poolFile,$showdoc); 
			}	if (move_uploaded_file($_FILES['uploadedFile']['tmp_name'],$showDoc)) {
				$qtxt = "insert into documents(global_id,filename,filepath,source,source_id,timestamp,user_id) values ";
				$qtxt.= "('$globalId','$fileName','$filePath','$source','$sourceId','". date('U') ."','$userId')";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			} else {
				alert("Upload from ".$_FILES['uploadedFile']['tmp_name']." to $showDoc failed");
			}
		} else alert("$showDoc allready exists");

 }
 */
	if (file_exists($showDoc)) {
		if($source == 'kassekladde'){
			//print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?source=kassekladde&sourceId=$sourceId&showDoc=$showDoc\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?$params&showDoc=$showDoc\">";
			exit;
		}else{
			print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?$params&showDoc=$showDoc\">";
			exit;
		}
		
	} else alert("Upload to $showDoc failed");
}

print "<tr><td width='100%' valign = 'top' align='center'>";
if($source == 'kassekladde'){
	#print "<form enctype='multipart/form-data' action='documents.php?source=$source&sourceId=$sourceId&fokus=$fokus&showDoc=".urlencode($showDoc)."' method='POST'>";
	print "<form enctype='multipart/form-data' action='documents.php?$params&showDoc=".urlencode($showDoc)."' method='POST'>";
}else{
	print "<form enctype='multipart/form-data' action='documents.php?$params&showDoc=".urlencode($showDoc)."' method='POST'>";
	
}
print "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'>";
print findtekst(1414, $sprog_id).":<br><br><input class='inputbox' name='uploadedFile' type='file' accept='.pdf,.jpg,.png'><br><br>";
print "<input type='submit' value='".findtekst(1078, $sprog_id)."'>";
print "</form>";
#*******************
/*
$queryParameters = 
	"sourceId=$sourceId"."&".
	"kladde_id=$kladde_id"."&".
	"source=$source"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"bilag_id=$bilag_id";


// Generate the URL with the query parameters
$targetPage = "docsIncludes/emailDoc.php?" . $queryParameters;


echo '<a href="' . $targetPage . '">';
print "<button id=\"emailD\">Email files</button>";
echo '</a>';
#****************
print "</td></tr>";
*/
?>
