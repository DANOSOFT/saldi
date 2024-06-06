<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mysale/mylabel.php --- lap 4.0.8 --- 2023-05-16 ---
// LICENS
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2020 - 2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20200617 PHR Support for more pages.
// 20200702 PHR Support for single label.
// 20200722 PHR Changed '3' to '$accountId' in 'select max(page) .....'
// 20200930 PHR Cookie set to avoid 'online' conflict in labelprint.
// 20201205 PHR column price locked if value in lastprint. 
// 20201206 PHR Control for duplicates 20201206 if fx. page refreshed
// 20201209 PHR Better controle to avoid changes in printed labels
// 20201215 PHR Some improvements in user experience.
// 20210313 PHR Mobil version shows only one column now.
// 20210426 PHR Mobil dublets set to false and barcode created if not set
// 20210429 PHR Check for passwd / seesion cookie mySalePw
// 20211024 PHR if dechex is not rounded it sometimes reduses the result by 1 (eg. if price is 9.95)
// 20211205 PHR Implemented languate.
// 20220921 DAPE added productlimit
// 20230313 PHR Various updates according to PHP8 and added productLimit to mobileview
// 20230325 PHR Added memberShip to query and corrected an error in labelView for members. 
// 20230331 PHR Added $freeLabels to enhance procuctlimit functionality
// 20230516 PHR Changed number of lines to $productLimit / 5 for members

@session_start();
$s_id=session_id();

if(isMobileDevice()) {
	$mobile=1;
	$css='../css/mysale_m.css';
} else {
	$mobile=0;
	$css='../css/mysale.css';
}

$medlem = $tmp = '';
$page = 0;
(isset($_POST['labelName']))?$labelName=$_POST['labelName']:$labelName=NULL;
(isset($_GET['id']))?$id=$_GET['id']:$id=NULL;
if (!isset($_SESSION['mySalePw']) || $s_id != $_SESSION['mySalePw']) {
	print "<meta http-equiv=\"refresh\" content=\"0;URL='mysale.php?id=$id'\">";
}
if ($id) {
	for ($x=0;$x<strlen($id);$x=$x+2) {
		$tmp.=chr(hexdec(substr($id,$x,2)));
	}
	list($kto,$db,$ssl)=explode('@',$tmp);
	if (strpos($kto,"|")) list($accountId,$account)=explode('|',$kto);
	else $account=$kto;
	include ('../includes/db_query.php');
}
if (!is_numeric($account)) {
	print "<center><br><br><br><br><b>Fejl i ID<br><br>Kontakt butikken for nyt ID</b>";
	exit;
}
$accountId *= 1;
$account  *= 1;

setcookie("mylabel","$account|$db",0,"/");

include ('../includes/connect.php');
$qtxt = "insert into online(session_id,brugernavn,db,dbuser,rettigheder,regnskabsaar,logtime,revisor)";
$qtxt.= " values ";
$qtxt.= "('$s_id','". db_escape_string($account) ."','". db_escape_string($db) ."','". db_escape_string($squser) ."',";
$qtxt.= "'0',0,'". date('U') ."',FALSE)";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
include ('../includes/online.php');
include ('../includes/std_func.php');

(substr($_SERVER['PHP_SELF'],0,4)=='/no/')?$sprog_id=3:$sprog_id=1;

$qtxt = "select var_value from settings where var_name='medlemSetting' or var_name = 'memberShip'";
if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) $medlem = $r['var_value'];
else $medlem = NULL;

if (isset($_GET['page'])) $page = $_GET['page']; 
(isset($_GET['condition']))?$condition=$_GET['condition']:$condition='used';
(isset($_POST['mySale']))?$mySale=$_POST['mySale']:$mySale=NULL; 
(isset($_POST['new']))?$new=$_POST['new']:$new=NULL; 
(isset($_POST['all']))?$all="checked='checked'":$all=NULL; 
(isset($_POST['update']))?$update=$_POST['update']:$update=NULL; 
(isset($_POST['delete']))?$delete=$_POST['delete']:$delete=NULL; 
if (isset($_POST['printSheet'])) $print='Sheet'; 
elseif (isset($_POST['printLabels'])) $print='Label';
else $print=NULL;
$pL = '';
$lineColor=$bgcolor5;

$cols=5;
if ($mySale) {
	print "<meta http-equiv='refresh' content='1;URL=mysale.php?id=$id&condition=$condition'>";
	exit;
} elseif  ( $update || $print || $new  || $delete ) {
	$numbers     = array('0','1','2','3','4','5','6','7','8','9',',');

	$page        = $_POST['page'];
	$labelId     = $_POST['labelId'];
	$labelName   = $_POST['labelName'];
	$price       = $_POST['price'];
	$itemNo      = $_POST['itemNo'];
	$itemId      = $_POST['itemId'];
	$selectLabel = if_isset($_POST['selectLabel'],NULL);
	$printIds=$Lp= '';
	$productLimit = if_isset($_POST['productLimit'],NULL);
	($medlem)?$rows = $productLimit/5:$rows=13;
  #cho "$medlem $productLimit $rows";
	for ($a=1;$a<=$rows;$a++) {
		for ($b=1;$b<=$cols;$b++) {
			$price[$a][$b]     = if_isset($price[$a][$b],0);
			$labelName[$a][$b] = if_isset($labelName[$a][$b],NULL);
			$labelId[$a][$b]   = if_isset($labelId[$a][$b],0);
			for ($c=0;$c<strlen($price[$a][$b]);$c++) {
				$char = substr($price[$a][$b],$c,1);
				if (!in_array($char,$numbers)) {
					$price[$a][$b]=str_replace($char,'|',$price[$a][$b]);
				}
			}
			$price[$a][$b]=str_replace('|','',$price[$a][$b]);
			$price[$a][$b] = usdecimal($price[$a][$b])*1;
			$labelName[$a][$b] = db_escape_string(trim($labelName[$a][$b]));
#cho $labelId[$a][$b] ." || ". $labelName[$a][$b] ."||". $price[$a][$b] ."<br>";
			if ($labelId[$a][$b]) {
#cho $labelId[$a][$b]."<br>";
				$barcode[$a][$b]=dechex($price[$a][$b]*100);
				while(strlen($barcode[$a][$b])<6) $barcode[$a][$b]='0'.$barcode[$a][$b];
				$barcode[$a][$b]=$labelId[$a][$b].$barcode[$a][$b];
				while(strlen($barcode[$a][$b])<12)$barcode[$a][$b]='0'.$barcode[$a][$b];
				$qtxt=NULL;
				if ($delete && isset($selectLabel[$a][$b]) &&	 $selectLabel[$a][$b] == 'on') {
					if($medlem){
					$qtxt = "delete from mylabel "; 
					$qtxt.= "where id = '". $labelId[$a][$b] ."'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
					else{
					$qtxt = "update mylabel set hidden = 'TRUE' ";
					$qtxt.= "where id = '". $labelId[$a][$b] ."'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					}
				} else {

					$qt2 = "select lastprint from mylabel where id = '". $labelId[$a][$b] ."' and lastprint > '0'";
					if (!$r=db_fetch_array(db_select($qt2,__FILE__ . " linje " . __LINE__))) {
						$qtxt = "update mylabel set description = '". $labelName[$a][$b] ."' ";
						$qtxt.= ", price = '" .$price[$a][$b]. "', barcode = '". $barcode[$a][$b] ."' ";
						$qtxt.= "where id = '". $labelId[$a][$b] ."'";
						db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					} else { #20210426 Can be removed in 2022
						$qt2 = "select barcode from mylabel where id = '". $labelId[$a][$b] ."' and barcode = ''";
						if (!$r=db_fetch_array(db_select($qt2,__FILE__ . " linje " . __LINE__))) {
							$qtxt = "update mylabel set barcode = '". $barcode[$a][$b] ."' ";
							$qtxt.= "where id = '". $labelId[$a][$b] ."'";
							db_modify($qtxt,__FILE__ . " linje " . __LINE__);
						}
					}
				}
				if ($print) {
					if (isset($selectLabel[$a][$b]) && $selectLabel[$a][$b]) $printIds.=$labelId[$a][$b].',';
					if (isset($labelName[$a][$b]) && $labelName[$a][$b] && $price[$a][$b]) $pL.=$labelId[$a][$b].',';
				}
			} elseif ($labelName[$a][$b] || $price[$a][$b]) { 
				$qtxt = "select id from mylabel where account_id = '$accountId' and page =  '$page' and condition = '$condition'";
				$qtxt.= " and row = '$a' and col ='$b' and hidden = false";
				if ($medlem) $qtxt.= " and sold ='0'";
#cho $qtxt ."<br>";
				if (!$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
					$qtxt = "insert into mylabel (account_id,page,condition,row,col,description,price,hidden,sold,created) values ";
					$qtxt.= "('$accountId','$page','$condition','$a','$b','". $labelName[$a][$b] ."','" .$price[$a][$b]. "',FALSE,0,".date('U').")";
#cho $db." ".$qtxt ."<br>";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
					$qtxt = "select max(id) as id from mylabel where account_id = '$accountId' and page='$page' ";
					$qtxt.= "and description = '". $labelName[$a][$b] ."' and price = '". $price[$a][$b] ."' ";
					$qtxt.= "and condition='$condition' and row = $a and col = $b";
					$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
					$labelId[$a][$b]=$r['id'];
					$barcode[$a][$b]=dechex(round($price[$a][$b]*100,0)); #20211024
					while(strlen($barcode[$a][$b])<6) $barcode[$a][$b]='0'.$barcode[$a][$b];
					$barcode[$a][$b]=$labelId[$a][$b].$barcode[$a][$b];
					while(strlen($barcode[$a][$b])<12)$barcode[$a][$b]='0'.$barcode[$a][$b];
					$qtxt = "update mylabel set barcode = '". $barcode[$a][$b] ."' "; 
					$qtxt.= "where id = '". $labelId[$a][$b] ."'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				} #else echo __line__." ".$db." ".$r['id'];
			}
		}
	}
	if ($new) $page++;
	if ($print) {
		if ($print=='Label') {
			if (!$printIds) $printIds= $pL;
			if (!$printIds) {
				alert ('Intet at udskrive');
				print "<meta http-equiv='refresh' content='1;URL=mylabel.php?id=$id&condition=$condition&page=$page'>";
				exit;
			}
			$printIds=trim($printIds,',');
			$lockPrint=explode(',',$printIds);
			for ($x=0;$x<count($lockPrint);$x++) {
				if ($lockPrint[$x]) {
					$qtxt = "update mylabel set lastprint = '". date('U') ."' where id = '$lockPrint[$x]'";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
			print "<BODY onload=\"javascript:";
			print "window.open('../lager/labelprint.php?account=$account&condition=$condition&printIds=$printIds&print=$print');\">";
		} else {
			$qtxt = "update mylabel set lastprint = '". date('U') ."' where account_id = '$accountId' and page='$page' ";
			$qtxt.= "and hidden is FALSE";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
			print "<BODY onload=\"javascript:";
			print "window.open('../lager/labelprint.php?account=$account&condition=$condition&page=$page&print=$print');\">";
		}
	}
} elseif (!$page) $page=1;

$qtxt = "select id,kontonr,art,productlimit,firmanavn,mysale from adresser where ";
if ($accountId) $qtxt.= "(id='". db_escape_string($accountId) ."' and kontonr='". db_escape_string($account) ."') ";
else $qtxt.= "kontonr='". db_escape_string($account) ."' ";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$accountId=$r['id'];
$account=$r['kontonr'];
$productLimit=$r['productlimit'];
$custName=$r['firmanavn'];
$access=$r['mysale'];

/*
$qtxt = "select var_value from settings where var_name='medlemSetting'";
if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) $medlem=$r['var_value'];
*/

if (!$condition) $condition='used';
($condition=='new')?$tmp='n':$tmp='b';



$qtxt = "select id,varenr from varer where varenr like 'k". $tmp ."___". $account ."'";;
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$itemId=$r['id'];
$itemNo=$r['varenr'];

if (strlen($itemNo) != 9) {
	#cho "fejl i varenr ". strlen($itemNo);
	exit;
}

$qtxt="select max(page) as pages from mylabel where account_id = '$accountId'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
$pages=$r['pages'];
if (!$pages) $pages=1;
$labelId=array();
$labelName=array();
$price=array();
$a=$b=$c=$d=NULL;
# --> 20210426 Sets dublets to false
$qtxt = "select * from mylabel ";
$qtxt.= "where account_id = '$accountId' and page='$page' and condition='$condition' and hidden != true order by row,col,id desc";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) { 
	if ($r['row']==$b && $r['col']==$c && $r['page']==$d){
		$qtxt="update mylabel set hidden = true where id = '$r[id]'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
	}
	$a=$r['id'];
	$b=$r['row'];
	$c=$r['col'];
	$d=$r['page'];
}
($medlem)?$rows = $productLimit/5:$rows=13;
# <-- 20210426
$a = $b = 0;
$freeLabels = $productLimit;
$qtxt = "select id,row,col,description,price,lastprint from mylabel ";
$qtxt.= "where account_id = '$accountId' and page='$page' and condition='$condition' and hidden != true";
if ($medlem) $qtxt.= " and sold ='0'";
$qtxt.= " order by row,col";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
#cho "R $rows<br>";
while ($r=db_fetch_array($q)) {
	for ($a=1;$a<=$rows;$a++) {
		for ($b=1;$b<=5;$b++) {
			if ($a==$r['row'] && $b==$r['col']) {
				$labelId[$a][$b]=$r['id'];
				$labelName[$a][$b]=$r['description'];
				$price[$a][$b]=$r['price']*1;   
				$lastPrint[$a][$b]=$r['lastprint'];
				$freeLabels--;
			} 
		}
	}
}

if ($medlem){
	$i = 0;
	$tilsalgOprettet = array();
	$qtxt = "select id from mylabel where account_id = '$accountId' and sold = '0' and hidden = 'f'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$tilsalgOprettet[$i]=$r['id'];
		$i++;
	}
	$vareLimit = $productLimit - count($tilsalgOprettet);
} else $vareLimit = $productLimit = 0;
print "<html lang='da-dk'><body>";
print "<div class='flex-container'>";
print "<div class='container'>";
print "<div class='kundeNavn'>$custName • ";
if ($condition == 'new') print findtekst(1951, $sprog_id) ."</div><br>";
else print findtekst(1952, $sprog_id) ."</div>";
if ($mobile) {
	print "<br>";
} else {
	print "<br>";
	print "<div class='center'>". findtekst(1948, $sprog_id) ."<br>\n";
	print findtekst(1949, $sprog_id) ." ";
	if ($medlem){
		if($vareLimit <= 0) $vareLimit = 0;
		print "<br><br >Du kan tilføje <b>$vareLimit</b> varer.</br><br>";
	}
	print "<a href='https://saldi.dk/dok/myLabelPdf_$sprog_id.pdf' target='_blank'>". findtekst(1950, $sprog_id) ."</a></div>\n";
}
print "<form action='mylabel.php?id=$id&condition=$condition' method='post'>\n";
print "<input type='hidden' name='page' value='". $page ."'>\n";
print "<input type='hidden' name='itemNo' value='". $itemNo ."'>\n";
print "<input type='hidden' name='itemId' value='". $itemId ."'>\n";
print "<input type='hidden' name='productLimit' value='". $productLimit ."'>\n";
print "<input type='hidden' name='update' value='1'>\n";
if(!$medlem) {
if ($mobile) print "<div class='center'>Side ";
else print "<div class='center'>Side ";
print "<select name='page' onchange='javascript:this.form.submit()'>";
print "<option value='$page'>$page</option>\n";
for ($p=1;$p<=$pages;$p++) {
	if ($p != $page) print "<option value='$p'>$p</option>\n";
}
print "</select></div>";
}
$count = 0;
if ($mobile) {
print "<table class='table' border = '1'>\n";
($productLimit)?$rows=$productLimit:$rows=13;
for ($a=1;$a<=$rows;$a++) {
		for ($b=1;$b<=5;$b++) {
			if (!isset ($barcode[$a][$b]))   $barcode[$a][$b]   = NULL;
			if (!isset ($labelId[$a][$b]))   $labelId[$a][$b]   = NULL;
			if (!isset ($labelName[$a][$b])) $labelName[$a][$b] = NULL;
			if (!isset ($lastPrint[$a][$b])) $lastPrint[$a][$b] = NULL;
			if (!isset ($price[$a][$b]))     $price[$a][$b]     = NULL;
			$count++;
			if ($productLimit) {
				($freeLabels > 0)?$showLabel = 1:$showLabel = 0;
				if ($labelId[$a][$b] && $lastPrint[$a][$b]) $showLabel = 1;
				if ($showLabel && !$labelId[$a][$b]) $freeLabels--;
			} else $showLabel = 1;
			if ($showLabel) {
				print "<tr>";
				($lastPrint[$a][$b] && $price[$a][$b])?$ro="readonly='readonly'":$ro=NULL;
				($price[$a][$b])?$price[$a][$b]=dkdecimal($price[$a][$b]):$price[$a][$b]=NULL;
				($lastPrint[$a][$b])?$title="title = 'Sidst udskrevet ". date("d.m.y H:i",$lastPrint[$a][$b]) ."'":$title=NULL;
				if ($mobile) print "<td class='tdLabel'; $title>";
				else print "<td class='tdLabel'; $title>";
				print "<input type='hidden' name='labelId[$a][$b]' value='". $labelId[$a][$b] ."'>";
				if ($mobile) print "<input type='text' $ro maxlength='22' class='inputLabel' name='labelName[$a][$b]' ";
				else print "<input type='text' $ro maxlength='22' class='inputLabel' name='labelName[$a][$b]' ";
				print "value='". htmlentities($labelName[$a][$b],ENT_QUOTES) ."' placeholder='Beskrivelse'>";
				($price[$a][$b])?$ta='text-align:right':$ta='text-align:center';
				print "<input type='text' $ro style='$ta;' class='inputPris' 
				name='price[$a][$b]' value='". $price[$a][$b] ."' placeholder='Pris'>";
				if ($labelId[$a][$b]) print "<input style='transform:scale(3);' type='checkbox' name='selectLabel[$a][$b]' $all>";
				else print "<input type='hidden' name='selectLabel[$a][$b]' value=''>";
				if ($lastPrint[$a][$b]) {
					print "&nbsp;<img class='checkMobile' src=\"../ikoner/checkmrk.png\" style=\"border: 0px solid;\">";
				}
				print "</td></tr>\n";
			}
		}
	}
	print "</table>";

	print "<div class='footer'>";
	print "<div class='inputs'>";
	print "<input class='inputSubmit' type='submit' name='update' value='". findtekst(3, $sprog_id) ."'>";
	$title = findtekst(1942, $sprog_id);
	print "<span title = '$title'>";
	print "<input class='inputSubmit' type='submit' name='delete' value='". findtekst(1099, $sprog_id) ."'";
	print "onclick=\"return confirm('". findtekst(1943, $sprog_id) ."')\"></span>";
	if (!$productLimit) {
		$title = findtekst(1944, $sprog_id);
		print "<span title = '$title'>";
		print "<input class='inputSubmit' type='submit' name='new' value='". findtekst(1945, $sprog_id) ."'></span>";
	}
	$title = findtekst(1946, $sprog_id);
	print "<span style='text-align=left;' title = '$title'>";
	print "<input class='inputSubmit' type='submit' name='all' value='". findtekst(89, $sprog_id) ."'>";
	print"</span>";
	$title = findtekst(1947, $sprog_id);
	print "<span title='$title'>";
	print "<input class='inputSubmit' type='submit' name='mySale' value='". findtekst(1881, $sprog_id) ."'>";
	print "</span>";
	print "</div>";
	print "</div>";
	print "</form>";
} else {
	print "<table class='table' border = '1' valign='top'>\n";
	($medlem)?$lines = $productLimit/5:$lines=13;
	for ($a=1;$a<=$lines;$a++) {
		($lineColor==$bgcolor)?$lineColor=$bgcolor5:$lineColor=$bgcolor;
#		if (!$productLimit || $count <= $productLimit) {
			print "<tr bgcolor='$lineColor'>";
			for ($b=1;$b<=5;$b++) {
				if (!isset ($barcode[$a][$b]))   $barcode[$a][$b]   = NULL;
				if (!isset ($labelId[$a][$b]))   $labelId[$a][$b]   = NULL;
				if (!isset ($labelName[$a][$b])) $labelName[$a][$b] = NULL;
				if (!isset ($lastPrint[$a][$b])) $lastPrint[$a][$b] = NULL;
				if (!isset ($price[$a][$b]))     $price[$a][$b]     = NULL;
				($lastPrint[$a][$b] && $price[$a][$b])?$ro="readonly='readonly'":$ro=NULL;
				($price[$a][$b])?$price[$a][$b]=dkdecimal($price[$a][$b]):$price[$a][$b]=NULL;
				($lastPrint[$a][$b])?$title="title = 'Sidst udskrevet ". date("d.m.y H:i",$lastPrint[$a][$b]) ."'":$title=NULL;
				$count++;
				($freeLabels > 0)?$showLabel=1:$showLabel=0;
				if ($labelId[$a][$b]) $showLabel=1;
				if ($showLabel && !$labelId[$a][$b]) $freeLabels--;
				print "<td style='width:175px;height:40px'; $title>";
				if (!$productLimit || $showLabel) {
					print "<input type='hidden' name='labelId[$a][$b]' value='". $labelId[$a][$b] ."'>";
					print "<input type='text' $ro maxlength='22' style='width:150px;' name='labelName[$a][$b]' ";
					print "value='". htmlentities($labelName[$a][$b],ENT_QUOTES) ."' placeholder='Beskrivelse'>";
					if ($labelId[$a][$b]) print "<input type='checkbox' name='selectLabel[$a][$b]' $all>"; 
					else print "<input type='hidden' name='selectLabel[$a][$b]' value=''>";
					print "<input type='text' $ro style='width:130px;' name='price[$a][$b]' ";
					print "value='". $price[$a][$b] ."' placeholder='Pris'>";
					if ($labelId[$a][$b] && $barcode[$a][$b]) {
						($lastPrint[$a][$b])?$title="title = 'Sidst udskrevet ". date('d.m.y H:i',$lastPrint[$a][$b]) ."'":$title=NULL;
						print "<span $title >";
						print "<a href='../lager/labelprint.php?account=$account&condition=$condition&print=label&";
						print "stregkode=". $barcode[$a][$b] ."&labelId=". $labelId[$a][$b] ."' target='_blank'>";
						print "<img src=\"../ikoner/print.png\" style=\"border: 0px solid;\"></a>";
						if ($lastPrint[$a][$b]) print "&nbsp;<img src=\"../ikoner/checkmrk.png\" style=\"border: 0px solid;\">";
					}
				}
				print "</td>";
			}
			print "</tr>\n";
#		}
	}
	print "</td></tr></table>";
	print "<div class='footer'>";
	print "<td><input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='update' value='". findtekst(3, $sprog_id) ."'></td>";
	$title = findtekst(1942, $sprog_id);
	print "<td title = '$title'>";
	print "<input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='delete' value='". findtekst(1099, $sprog_id) ."'";
	print "onclick=\"return confirm('". findtekst(1943, $sprog_id) ."')\"></td>";
	$title = findtekst(1944, $sprog_id);
	if(!$medlem){
	print "<td title = '$title'>";
	print "<input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='new' value='". findtekst(1945, $sprog_id) ."'></td>";
	}
	$title = findtekst(1946, $sprog_id);
	print "<td title = '$title'>";
	print "<input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='all' value='". findtekst(89, $sprog_id) ."'></td>";
	$title = findtekst(1947, $sprog_id);
	print "<td title='$title'>";
	print "<input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='mySale' value='". findtekst(1881, $sprog_id) ."'></td>";
	print "<td><input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='printSheet' value='". findtekst(1953, $sprog_id) ."'></td>";
	$title = findtekst(1954, $sprog_id);
	print "<td title = '$title'>";
	print "<input class='submit' style='width:120px;text-align:center;' ";
	print "type='submit' name='printLabels' value='". findtekst(1955, $sprog_id) ."'></td>";
	print "<td></td>";
	print "</tr>\n";
	print "</form>";
	print "</div>\n";
	print "</div>\n";
}

include ('../includes/connect.php');
$qtxt = "delete from online where session_id='$s_id' and brugernavn='$account' and db='$db'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);

function isMobileDevice() {
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $_SERVER["HTTP_USER_AGENT"]);
}
print "</body></html>";
