<?php
// --- includes/docsIncludes/moveDoc.php -----patch 4.1.0 ----2024-03-05---
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
// Copyright (c) 2024-2024 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20230707 LOE Added kassekladde part
// 20230724 LOE made some modifications to include alert also
// 20240305 PHR Varioous corrections


if ($moveDoc) {
	$tmpA = explode("/",$moveDoc);

	$x = count($tmpA)-1;
	$h = count($tmpA)-3;

	$bilag_id = $tmpA[$h];
	$fileName = $tmpA[$x];
	$new = '';

	for ($i=0;$i<count($tmpA)-4;$i++) {
		if ($tmpA[$i]) $new.= $tmpA[$i]."/";
	}
	$new.= "pulje";
	if (!file_exists($new)) mkdir($new, 0777);
	$new.= "/$tmpA[$x]";
	$new = str_replace(' ','',$new);
	rename("$moveDoc", "$new");
	$qtxt = "delete from documents where source = '$source' and source_id = '$sourceId' ";
	$qtxt.= "and filename = '".db_escape_string($fileName)."'";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
}
?>
