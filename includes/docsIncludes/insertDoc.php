<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/insertDoc.php --- patch 4.1.0 --- 2024-03-29 ---
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
//20240305 PHR Varioous corrections
//20240329 PHR Now returns to kassekladde when done.

$sth = dirname(dirname(dirname(__FILE__)));

isset($_GET['bilag_id'])? $bilag_id = $_GET['bilag_id']: $bilag_id = null;
isset($_GET['bilag'])? $bilag = $_GET['bilag']: $bilag = null;
if(!isset($globalId)) $globalId =1;
$qtxt = "select var_value from settings where var_name = 'globalId'";
if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $globalId = $r['var_value'];
else alert ('Missing global ID');

$docFolder.= "/$db";
if ($poolFile && !$fileName) $fileName = $poolFile;
#cho "$poolFile && !$fileName<br>";
#cho __line__." $docFolder && $source<br>";
if ($docFolder && $source == 'creditorOrder') {
	
	if (!file_exists("$docFolder"))                 mkdir ("$docFolder/",0777);
#	if (!file_exists("$docFolder"))                 #cho __line__."<br>";
	if (!file_exists("$docFolder/creditor"))        mkdir ("$docFolder//creditor",0777);
#	if (!file_exists("$docFolder/creditor"))                 #cho __line__."<br>";
	if (!file_exists("$docFolder/creditor/orders")) mkdir ("$docFolder//creditor/orders",0777);
#	if (!file_exists("$docFolder/creditor/orders"))                 #cho __line__;
#		$tmp = floor($sourceId/1000)*1000;
#		$tmp2 = $tmp+1000;
#		$filePath = "/creditor/orders/".$tmp."-".$tmp2;
	$filePath = "/creditor/orders/$sourceId";
	if (!file_exists("$docFolder/$filePath")) mkdir ("$docFolder/$filePath",0777);
		if (!file_exists("$docFolder/$filePath/$fileName")) {
/*
			if(move_uploaded_file($_FILES['uploadedFile']['tmp_name'],"$docFolder/$filePath/$fileName")) {
			$qtxt = "insert into documents(global_id,filename,filepath,source,source_id,timestamp,user_id) values ";
			$qtxt.= "('$globalId','$fileName','$filePath','$source','$sourceId','". date('U') ."','$userId')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				$showDoc = "$docFolder/$filePath/$fileName";
			} else alert("Upload to $docFolder/$filePath/$fileName failed");
*/
			$showDoc = "$docFolder/$filePath/$fileName";
		} else alert("$docFolder/$filePath/$fileName allready exists");
		$showDoc = "$docFolder/$filePath/$fileName";
#cho __line__." $showDoc<br>";
} elseif ($docFolder && $source == 'kassekladde') {
#cho __line__." $sourceId<br>";
	if (!$sourceId) {
#cho __line__."<br>";
		if (!$bilag) {
			include_once("../includes/stdFunc/fiscalYear.php");
			$bilag=1;
			if ($_POST['bilag']) $bilag = (int)$_POST['bilag'];
			else {
				list ($regnstart,$regnslut) = explode(":",fiscalYear($regnaar));
				$qtxt = "select MAX(bilag) as bilag from kassekladde where transdate>='$regnstart' and transdate<='$regnslut'";
				$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
				if ($row = db_fetch_array($q)) $bilag=$row['bilag']+1;
			}
		}
		$qtxt = "insert into kassekladde (bilag,kladde_id,transdate,d_type,k_type,amount) values ";
		$qtxt.= "('$bilag','$kladde_id','". date("Y-m-d") ."','F','F','0')";
#cho "$qtxt<br>";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		$qtxt = "select max(id) as id from kassekladde where kladde_id = '$kladde_id' and bilag = '$bilag'";
		if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
			$sourceId = $r['id'];
		}
	} 
	if ($sourceId) {
		if ($_POST['dato']) {
			$qtxt = "update kassekladde set transdate = '". usdate($_POST['dato']) ."' where id = '$sourceId'";
#cho $qtxt."<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);				
		}
		if ($_POST['beskrivelse']) {
			$qtxt = "update kassekladde set beskrivelse = '". db_escape_string($_POST['beskrivelse']) ."' where id = '$sourceId'";
#cho $qtxt."<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);				
		}
		if ($_POST['debet']) {
			if (!is_numeric(substr($_POST['debet'],0,1))) {
				$qtxt = "update kassekladde set d_type = '". substr($_POST['debet'],0,1) ."', ";
				$qtxt.= "debet = '". (int)substr($_POST['debet'],1) ."' where id = '$sourceId'";
#cho $qtxt."<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);				
			} else {
				$qtxt = "update kassekladde set debet = '". (int)$_POST['debet'] ."' where id = '$sourceId'";
#cho $qtxt."<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if ($_POST['kredit']) {
			if (!is_numeric(substr($_POST['kredit'],0,1))) {
				$qtxt = "update kassekladde set k_type = '". substr($_POST['kredit'],0,1) ."', ";
				$qtxt.= "kredit = '". (int)substr($_POST['kredit'],1) ."' where id = '$sourceId'";
#cho $qtxt."<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);				
			} else {
				$qtxt = "update kassekladde set kredit = '". (int)$_POST['kredit'] ."' where id = '$sourceId'";
#cho $qtxt."<br>";
				db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			}
		}
		if ($_POST['sum']) {
			$qtxt = "update kassekladde set amount = '". usdecimal($_POST['sum']) ."' where id = '$sourceId'";
#cho $qtxt."<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);				
		}
		if ($_POST['fakturanr']) {
			$qtxt = "update kassekladde set faktura = '". db_escape_string($_POST['fakturanr']) ."' where id = '$sourceId'";
#cho $qtxt."<br>";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);				
		}
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=../finans/kassekladde.php?kladde_id=$kladde_id\">";
	} else {
		alert("Bilaget kunne ikke indsættes");
	}
	$path = "../bilag/$db/finance/$kladde_id/$sourceId/";
	$showDoc = $path.$fileName;
#cho "$showDoc <br>";
	if(!file_exists("../bilag/$db")) 							mkdir ("../bilag/$db",0777);
	if(!file_exists("../bilag/$db")) {
		print "creation of ../bilag/$db failed<br>";
		exit;
	}
#cho __line__."<br>";
	if (!file_exists($docFolder))                 			mkdir ($docFolder,0777);
#cho __line__."<br>";
	if (!file_exists("$docFolder")) print "Ku ik oprette $docFolder<br>";
#cho __line__."<br>";
	if (!file_exists("$docFolder/finance"))        		mkdir ("$docFolder/finance",0777);
#cho __line__."<br>";
	if (!file_exists("$docFolder/finance")) print "Ku ik oprette $docFolder/finance<br>";
#cho __line__."<br>";
	if (!file_exists("$docFolder/finance/$kladde_id")) 	mkdir ("$docFolder/finance/$kladde_id",0777); //Groups the individual attached files
# 	if (!file_exists("$docFolder/finance/$kladde_id")) #cho "Ku ik oprette $docFolder/finance/$kladde_id<br>";
#cho __line__."$docFolder/finance/$kladde_id/$sourceId<br>";
	if (!file_exists("$docFolder/finance/$kladde_id/$sourceId")) 	mkdir ("$docFolder/finance/$kladde_id/$sourceId",0777);
#cho __line__."<br>";
#	if (!file_exists("$docFolder/finance/$kladde_id/$sourceId")) #cho "Ku ik oprette $docFolder/finance/$kladde_id/$sourceId<br>";
	$filePath = "/finance/$kladde_id/$sourceId";
}
if (!file_exists($showDoc)) {
	if ($insertFile && file_exists("$docFolder/pulje/$fileName")) rename("$docFolder/pulje/$fileName",$showDoc);
	else move_uploaded_file($_FILES['uploadedFile']['tmp_name'],"$showDoc");
	if(file_exists($showDoc)) {
		if (!$sourceId) {
			if ($source == 'kassekladde') {
				$qtxt = "insert into kassekladde (kladde_id) values ('$kladde_id')";
			}
		}
		$qtxt = "insert into documents(global_id,filename,filepath,source,source_id,timestamp,user_id) values ";
		$qtxt.= "('$globalId','". db_escape_string($fileName) ."','$filePath','$source','$sourceId','". date('U') ."','$userId')";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		} else {
			alert("Move from pool Failed");
		}
	} else {
	 alert("$showDoc allready exists");
}
if (file_exists($showDoc)) {
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?$params&showDoc=$showDoc\">";
	if($source == 'kassekladde'){
		if ($poolFile) $href = "../finans/kassekladde.php?kladde_id=$kladde_id&fokus=$fokus";
		else $href = "documents.php?$params&showDoc=$showDoc";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=$href\">";
//		print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?$params&showDoc=$showDoc\">";
		exit;
	}else{
		print "<meta http-equiv=\"refresh\" content=\"0;URL=documents.php?source=creditorOrder&sourceId=$sourceId&showDoc=$showDoc\">";
		exit;
	}
} else alert("Move to $showDoc failed");

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
/*
$queryParameters = 
	"sourceId=$sourceId"."&".
	"kladde_id=$kladde_id"."&".
	"source=$source"."&".
	"bilag=$bilag"."&".
	"fokus=$fokus"."&".
	"bilag_id=$sourceId";


// Generate the URL with the query parameters
$targetPage = "docsIncludes/emailDoc.php?" . $queryParameters;


#cho '<a href="' . $targetPage . '">';
print "<button id=\"emailD\">Email files</button>";
#cho '</a>';
#****************
print "</td></tr>";
*/
?>
