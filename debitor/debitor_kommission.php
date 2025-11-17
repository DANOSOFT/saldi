<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/debitor_kommission.php -----patch 4.1.0 ----2025-04-15--------------
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
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------
// Kommission view - separate file for better grid differentiation

#ob_start();
@session_start();
$s_id=session_id();
setcookie("mySalePw", $s_id,0,"/");

$adresseantal=$check_all=$hrefslut=$javascript=$kontoid=$linjebg=$linjetext=NULL;
$nextpil=$ny_sort=$prepil=$skjul_lukkede=$tidspkt=$understreg=$udv2=NULL;
$cat_liste=$dg_liste=$find=$dg_id=$dg_navn=$selectfelter=array();

print "
<script LANGUAGE=\"JavaScript\">
<!--
function MasseFakt(tekst)
{
	var agree = confirm(tekst);
	if (agree)
		return true ;
	else
    return false ;
}
// -->
</script>
";
$css="../css/standard.css";
$modulnr=6;
$title="Debitorliste";
$firmanavn=NULL; 
$ansat_id = array();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include("../includes/udvaelg.php");
include("../includes/row-hover-style.js.php");
include(get_relative() . "includes/grid.php");

$id = if_isset($_GET,NULL,'id');
$returside=if_isset($_GET,NULL,'returside');

$valg = "kommission";
$sort = if_isset($_GET, NULL, 'sort');
$start = if_isset($_GET, NULL, 'start');
$nysort = if_isset($_GET, NULL, 'nysort');
$invite=$mailTo=$mySale=array();

// Handle POST for kommission email sending
if (isset($_POST['kommission']) && $_POST['debId']) {
	$debId=$_POST['debId'];
	if (isset($_POST['mySale'])) $mySale=$_POST['mySale'];
	if (isset($_POST['invite'])) $invite=$_POST['invite'];
	if (isset($_POST['mailTo'])) $mailTo=$_POST['mailTo'];
	$start*=1;
	for ($i=0;$i<count($debId);$i++) {
		$qtxt="select id,kontonr,firmanavn,email from adresser where id='$debId[$i]'";
		$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		$custId[$i]   = $r['id'];
		$custNo[$i]   = $r['kontonr'];
		$custName[$i] = $r['firmanavn'];
		$custMail[$i] = $r['email'];
		if (!isset($mySale[$i])) $mySale[$i]=NULL;
		if (!isset($invite[$i])) $invite[$i]=NULL;
		if ($mySale[$i] || $invite[$i]) {
			$tmp=trim($_SERVER['PHP_SELF'],'/');
			list ($folder,$tmp)=explode('/',$tmp,2);
			$lnk[$i]="https://". $_SERVER['HTTP_HOST'] .'/'. $folder ."/mysale/mysale.php?id=";
			$lnk[$i]=str_replace('bizsys','mysale',$lnk[$i]);
			$txt = $custId[$i] .'|'. $custNo[$i] .'@'. $db  .'@'. $_SERVER['HTTP_HOST'];
			for ($x=0;$x<strlen($txt);$x++) {
				$lnk[$i].=dechex(ord(substr($txt,$x,1)));
			}
		}
	}
	include("../includes/connect.php");
	
	$x = 0;
	$myAccId = array();
	$qtxt="select * from mysale where db='$db'";
	$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$myId[$x]=$r['id'];
		$myAccId[$x]=$r['deb_id'];
		$x++;
	}
	for ($i=0;$i<count($debId);$i++) {
		if (in_array($debId[$i],$myAccId)) {
			for ($x=0;$x<count($myAccId);$x++) {
				if ($myAccId[$x] == $debId[$i]) {
					if ($mySale[$i] || $invite[$i]) $qtxt = "update mysale set email = '$custMail[$i]', link = '$lnk[$i]'";
					else $qtxt = "update mysale set email = '', link = ''";
					$qtxt.= " where id = $myId[$x]";
					db_modify($qtxt,__FILE__ . " linje " . __LINE__);
				}
			}
		} elseif ($mySale[$i] || $invite[$i]) {
			$qtxt="insert into mysale (deb_id,db,email,link) values ('$debId[$i]','$db','$custMail[$i]','$lnk[$i]')";
			db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		}
	}
	include("../includes/online.php");
	if((count($invite) || count($mailTo)) && !class_exists('phpmailer')) {
		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");
	}
	# Hent egen stamdata
	$qtxt="select * from adresser where art='S'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$afsendermail=$r['email'];
	$afsendernavn=$r['firmanavn'];
	$from=$afsendermail;
	
	($r['felt_1'])?$smtp=$r['felt_1']:$smtp='localhost';
	($r['felt_2'])?$smtp_user=$r['felt_2']:$smtp_user=NULL;
	($r['felt_3'])?$smtp_pwd=$r['felt_3']:$smtp_pwd=NULL;
	($r['felt_4'])?$smtp_enc=$row['felt_4']:$smtp_enc=NULL;

	for ($i=0;$i<count($debId);$i++) {
		if (!isset($invite[$i])) $invite[$i]=NULL;
		if (!isset($mailTo[$i])) $mailTo[$i]=NULL;
		if ($invite[$i] || $mailTo[$i]) {
			if ($invite[$i]) db_modify("update adresser set mysale='$invite[$i]' where id = '$debId[$i]'",__FILE__ . " linje " . __LINE__);
			$qtxt="select * from adresser where art='S'";
			$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));

			if ($invite[$i]) { 
				$myLink="<a href='$lnk[$i]'>Mit Salg</a>";
				$mailText = "Kære $custName[$i],<br><br>Klik på nedestående link for at se dit salg.<br><br>";
				$mailText.= "$myLink<br><br>";
				$mailText.= "Bedste hilsner<br>$afsendernavn<br>";
				$varGrp='mySale';
			} else {
				$varGrp='debitor';
			}
			$qtxt="select var_value from settings where var_name = 'mailSubject' and var_grp = '$varGrp'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r2['var_value']) $subject=$r2['var_value'];
			else $subject = "Adgang til dit salg hos $afsendernavn";
			$qtxt="select var_value from settings where var_name = 'mailText' and var_grp = '$varGrp'";
			$r2=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
			if ($r2['var_value']) {
				$mailText=$r2['var_value'];
				$mailText=str_replace("\n","<br>",$mailText);
				$mailText=str_replace('$kunde',$custName[$i],$mailText);
				$mailText=str_replace('$link',$myLink,$mailText);
			}
			$mail = new PHPMailer();
			$mail->IsSMTP();
			$mail->CharSet = "$charset";
			$mail->SMTPDebug  = 2;
			$mail->Host  = $smtp;
			if ($smtp!='localhost') {
				if ($smtp_user) {
					$mail->SMTPAuth = true;
					$mail->Username = $smtp_user;
					$mail->Password = $smtp_pwd;
					if ($smtp_enc) $mail->SMTPSecure = $smtp_enc;
				}
			} else {
				$mail->SMTPAuth = false;
				if (strpos($_SERVER['SERVER_NAME'],'saldi.dk')) {
					$from = $db.'@'.$_SERVER['SERVER_NAME'];
					$from = str_replace('bizsys','post',$from);
				}
			}
			if ($subject && $mailText) {
				$mail->SetFrom($from,$afsendernavn);
				$mail->AddReplyTo($afsendermail);
				$mail->AddAddress($custMail[$i]);
				$mail->WordWrap = 50;
				$mail->IsHTML(true);
				$ren_text=html_entity_decode($mailText,ENT_COMPAT,$charset);
				$ren_text=str_replace("<br>","\n",$ren_text);
				$ren_text=str_replace("<b>","*",$ren_text);
				$ren_text=str_replace("</b>","*",$ren_text);
				$ren_text=str_replace("<a href='$lnk'>". $lnk ."</a>"," $lnk ",$ren_text);
				$ren_text=str_replace("<hr>","------------------------------",$ren_text);
				$mail->Subject  =  "$subject";
				$mail->Body     =  "$mailText";
				$mail->AltBody  =  "$ren_text";
				$svar=NULL;
				print "<!--";
				if(!$mail->Send()){
					$svar = "Mailer Error: " . $mail->ErrorInfo;
				}
				print "-->";
				if ($svar) {
					echo $svar."<br>";
					exit;
				}
				echo "Mail sendt til $custName[$i] &lt;$custMail[$i]&gt;<br>";
				flush();
				usleep (250000);
			}
		} else {
			if (!isset($mySale[$i])) $mySale[$i]=NULL;
			db_modify("update adresser set mysale='$mySale[$i]' where id = '$debId[$i]'",__FILE__ . " linje " . __LINE__);
		}
	}
	print "<meta http-equiv='refresh' content='2'>";
}

$sort=str_replace("adresser.","",$sort);
if ($sort && $nysort==$sort) $sort=$sort." desc";
elseif ($nysort) $sort=$nysort;
$r=db_fetch_array(db_select("select box7 from grupper where art = 'DIV' and kodenr = '2'",__FILE__ . " linje " . __LINE__));
$jobkort=$r['box7'];
$qtxt = "select var_value from settings where var_grp='debitor' and var_name='mySale'";
($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$showMySale=trim($r['var_value']):$showMySale=NULL;
$qtxt = "select var_value from settings where var_grp='rental'";
($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)))?$showRental=trim($r['var_value']):$showRental=NULL;
$x = 0;
$qtxt = "select id,box3,box6 from grupper where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'";
$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($x > 0) db_modify("delete from grupper where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	elseif ($valg=='kommission' && date('Y-m') == '2020-06' && substr($r['box6'],-4)=='lger') {
		$box3 = "kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9);
		$box3.= "bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."invoiced";
		$box6 = "Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9);
		$box6.= "Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."Sidste faktura";
		db_modify("update grupper set box3 = '$box3',box6 = '$box6' where id='$r[id]'",__FILE__ . " linje " . __LINE__);
	} 
	$x++;
}
if ($x == 0) {
	$box7 = 100;
	$box3 = "kontonr".chr(9)."firmanavn".chr(9)."addr1".chr(9)."addr2".chr(9)."postnr".chr(9);
	$box3.= "bynavn".chr(9)."kontakt".chr(9)."tlf".chr(9)."invoiced";
	$box5 = "right".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left".chr(9)."left";
	$box4 = "5".chr(9)."35".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10".chr(9)."10";
	$box6 = "Kontonr".chr(9)."Firmanavn".chr(9)."Adresse".chr(9)."Adresse 2".chr(9);
	$box6.= "Postnr".chr(9)."By".chr(9)."Kontakt".chr(9)."Telefon".chr(9)."Sidste faktura";
	$qtxt = "insert into grupper(beskrivelse,kode,kodenr,art,box3,box4,box5,box6,box7) values ";
	$qtxt.= "('debitorlistevisning','$valg','$bruger_id','DLV','$box3','$box4','$box5','$box6','$box7')";
	db_modify($qtxt,__FILE__ . " linje " . __LINE__);
} else {
	$qtxt="select box1,box2,box7,box9,box10,box11 from grupper where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'";
	$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__)); 
	$dg_liste=explode(chr(9),$r['box1']);
	$cat_liste=explode(chr(9),$r['box2']);
	$skjul_lukkede=$r['box11'];
	$linjeantal=$r['box7'];
	if (!$sort) $sort=$r['box9'];
	$find=explode("\n",$r['box10']);
}
	
if ($popup) $returside= "../includes/luk.php";
else $returside= "../index/menu.php";

db_modify("update grupper set box9='$sort' where art = 'DLV' and kode='$valg' and kodenr = '$bruger_id'",__FILE__ . " linje " . __LINE__);

$tidspkt=date("U");
 
if ($search = if_isset($_POST, NULL, 'search')) {
	$find = if_isset($_POST, NULL, 'find');
	$sort = if_isset($_POST, NULL, 'sort');
	$nysort = if_isset($_POST, NULL, 'nysort');
	$firma = if_isset($_POST, NULL, 'firma');
}

if (!$sort) $sort = "firmanavn";

$sort=str_replace("adresser.","",$sort);
$sortering=$sort;

if ($menu=='T') {
	$title= "".findtekst(909,$sprog_id)."";
} else {
	$title="Kommission";
}

if ($menu=='T') {
	include_once '../includes/top_header.php';
	include_once '../includes/top_menu.php';
	print "<div id=\"header\">"; 
	print "<div class=\"headerbtnLft headLink\">&nbsp;&nbsp;&nbsp;</div>";   
	print "<div class=\"headerTxt\">$title</div>";     
	print "<div class=\"headerbtnRght headLink\">";
	print "<a accesskey=V href='debitorvisning.php?valg=$valg' title='Ændre visning'><i class='fa fa-gear fa-lg'></i></a> &nbsp; ";
	print "<a accesskey=N href='ordre.php?konto_id=$konto_id&returside=ordreliste.php?konto_id=$konto_id' title='Opret nyt kundekort'><i class='fa fa-plus-square fa-lg'></i></a></div>";     
	print "</div>";
	print "<div class='content-noside'>";
} elseif ($menu=='S') include_once 'debLstIncludes/topLine.php';
else include_once 'debLstIncludes/oldTopLine.php';

// Use grid system for kommission view
$r = db_fetch_array(db_select("select box3,box4,box5,box6,box8,box11 from grupper where art = 'DLV' and kodenr = '$bruger_id' and kode='$valg'",__FILE__ . " linje " . __LINE__));
$vis_felt=explode(chr(9),$r['box3']);
$feltbredde=explode(chr(9),$r['box4']);
$justering=explode(chr(9),$r['box5']);
$feltnavn=explode(chr(9),$r['box6']);
$vis_feltantal=count($vis_felt);
$select=explode(chr(9),$r['box8']);
$skjul_lukkede=$r['box11'];

$y=0;
for ($x=0;$x<=$vis_feltantal;$x++) {
	if (isset($select[$x]) && isset($vis_felt[$x]) && $select[$x] && $vis_felt[$x]) {
		$selectfelter[$y]=$vis_felt[$x];
		$y++;
	}
}
$numfelter=array("rabat","momskonto","kreditmax","betalingsdage","gruppe","kontoansvarlig","postnr","kontonr");

// GRID SYSTEM IMPLEMENTATION
// Build columns array for grid
$columns = array();

// Get ansatte for kontoansvarlig field
$ansat_id=array();
$ansat_init=array();
$y=0;
$qtxt = "select distinct(ansatte.id) as ansat_id,ansatte.initialer as initialer from ansatte,adresser where ";
$qtxt.= "adresser.art='S' and ansatte.konto_id=adresser.id order by ansatte.initialer";
$q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	$y++;
	$ansat_id[$y]=$r['ansat_id'];
	$ansat_init[$y]=$r['initialer'];
}
$ansatantal=$y;

// Get status options
$status_id=array();
$status_beskrivelse=array();
$qtxt = "select box3,box4 from grupper where art='DebInfo'";
$r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
if ($r) {
	$status_id=explode(chr(9),$r['box3']);
	$status_beskrivelse=explode(chr(9),$r['box4']);
}
$status_antal=count($status_id);

// Build columns from stored configuration
for ($x=0;$x<$vis_feltantal;$x++) {
	if (!isset($vis_felt[$x]) || !$vis_felt[$x]) continue;
	if (substr($vis_felt[$x],0,4) == 'cat_') continue; // Skip category columns for now
	
	$field = $vis_felt[$x];
	$headerName = isset($feltnavn[$x]) ? $feltnavn[$x] : $field;
	$width = isset($feltbredde[$x]) && $feltbredde[$x] ? ($feltbredde[$x] / 100) : 1;
	$align = isset($justering[$x]) ? $justering[$x] : 'left';
	$isSearchable = true; // All fields are searchable in grid
	
	$column = array(
		"field" => $field,
		"headerName" => $headerName,
		"width" => $width,
		"align" => $align,
		"searchable" => $isSearchable,
		"sqlOverride" => "a.$field"
	);
	
	// Determine field type
	// kontonr and postnr are identifiers, not numeric values, so treat as text
	if ($field == 'kontonr' || $field == 'postnr') {
		$column["type"] = "text";
	} elseif (in_array($field, $numfelter)) {
		$column["type"] = "number";
		if ($align == 'left') $column["align"] = "right";
	} elseif (in_array($field, array('invoiced', 'kontaktet', 'kontaktes'))) {
		$column["type"] = "date";
	} else {
		$column["type"] = "text";
	}
	
	// Special renderers
	if ($field == 'kontoansvarlig') {
		$column["render"] = function ($value, $row, $column) use ($ansat_id, $ansat_init, $ansatantal) {
			$display = '';
			for ($y=1;$y<=$ansatantal;$y++) {
				if (isset($ansat_id[$y]) && $ansat_id[$y]==$value) {
					$display = stripslashes($ansat_init[$y]);
					break;
				}
			}
			return "<td align='{$column['align']}'>$display</td>";
		};
	} elseif ($field == 'status') {
		$column["render"] = function ($value, $row, $column) use ($status_id, $status_beskrivelse, $status_antal) {
			$display = '';
			for ($y=0;$y<$status_antal;$y++) {
				if (isset($status_id[$y]) && $status_id[$y]==$value) {
					$display = stripslashes($status_beskrivelse[$y]);
					break;
				}
			}
			return "<td align='{$column['align']}'>$display</td>";
		};
	} elseif (in_array($field, array('invoiced', 'kontaktet', 'kontaktes'))) {
		$column["render"] = function ($value, $row, $column) {
			if ($value=='1970-01-01' || !$value) return "<td align='{$column['align']}'></td>";
			return "<td align='{$column['align']}'>".dkdato($value)."</td>";
		};
	} elseif ($field == 'kontakt') {
		$column["generateSearch"] = function ($column, $term) {
			$term = db_escape_string($term);
			return "a.id in (select konto_id from ansatte where LOWER(navn) like LOWER('%$term%'))";
		};
	} elseif ($field == 'kontonr' || $field == 'postnr') {
		// kontonr and postnr are text identifiers, use text search
		$column["generateSearch"] = function ($column, $term) {
			$field = $column['sqlOverride'];
			$term = db_escape_string($term);
			return "$field::text ILIKE '%$term%'";
		};
	} elseif (in_array($field, $numfelter)) {
		$column["generateSearch"] = function ($column, $term) {
			$field = $column['sqlOverride'];
			$term = db_escape_string($term);
			if (strstr($term, ':')) {
				list($num1, $num2) = explode(":", $term, 2);
				return "$field >= '".usdecimal($num1)."' AND $field <= '".usdecimal($num2)."'";
			} else {
				$term = usdecimal($term);
				return "$field >= $term AND $field <= $term";
			}
		};
	} elseif (in_array($field, array('invoiced', 'kontaktet', 'kontaktes'))) {
		$column["generateSearch"] = function ($column, $term) {
			$field = $column['sqlOverride'];
			$term = db_escape_string($term);
			if (strstr($term, ':')) {
				list($date1, $date2) = explode(":", $term, 2);
				return "$field >= '".usdate($date1)."' AND $field <= '".usdate($date2)."'";
			} else {
				return "$field = '".usdate($term)."'";
			}
		};
	}
	
	$columns[] = $column;
}

// Add clickable row renderer for kontonr - kommission handles links separately
foreach ($columns as &$column) {
	if ($column['field'] == 'kontonr') {
		$column["render"] = function ($value, $row, $column) {
			// Handle kommission links separately
			return "<td align='{$column['align']}'><a href='#'>$value</a></td>";
		};
		break;
	}
}

// Build filters
$filters = array();

// Hide closed filter
if ($skjul_lukkede) {
	$filters[] = array(
		"filterName" => "Misc",
		"joinOperator" => "and",
		"options" => array(
			array(
				"name" => "Vis udgået",
				"checked" => "",
				"sqlOn" => "",
				"sqlOff" => "(a.lukket IS NULL OR a.lukket = '0' or a.lukket = '')",
			)
		)
	);
}

// Debtor groups filter
$q=db_select("select kodenr, MIN(beskrivelse) as beskrivelse from grupper where art = 'DG' group by kodenr order by kodenr",__FILE__ . " linje " . __LINE__);
$dg_options = array();
$seen_kodenr = array();
$seen_names = array();
while ($r=db_fetch_array($q)) {
	$kodenr = (int)$r['kodenr'];
	$name = trim($r['beskrivelse']);
	$key = $kodenr . '|' . $name;
	if (!in_array($kodenr, $seen_kodenr) && !in_array($name, $seen_names)) {
		$seen_kodenr[] = $kodenr;
		$seen_names[] = $name;
		$dg_options[] = array(
			"name" => $name,
			"checked" => "",
			"sqlOn" => "a.gruppe = $kodenr",
			"sqlOff" => "",
		);
	}
}
if (count($dg_options)) {
	$filters[] = array(
		"filterName" => "Kundegrupper",
		"joinOperator" => "or",
		"options" => $dg_options
	);
}

// Categories filter
$r=db_fetch_array(db_select("select box1,box2 from grupper where art='DebInfo'",__FILE__ . " linje " . __LINE__));
if ($r && $r['box1'] && $r['box2']) {
	$cat_id=explode(chr(9),$r['box1']);
	$cat_beskrivelse=explode(chr(9),$r['box2']);
	$cat_antal=count($cat_id);
	$cat_options = array();
	for ($y=0;$y<$cat_antal;$y++) {
		if (isset($cat_id[$y]) && isset($cat_beskrivelse[$y]) && $cat_id[$y] && $cat_beskrivelse[$y]) {
			$cat_options[] = array(
				"name" => $cat_beskrivelse[$y],
				"checked" => "",
				"sqlOn" => "(a.kategori = '{$cat_id[$y]}' or a.kategori LIKE '{$cat_id[$y]}".chr(9)."%' or a.kategori LIKE '%".chr(9)."{$cat_id[$y]}' or a.kategori LIKE '%".chr(9)."{$cat_id[$y]}".chr(9)."%')",
				"sqlOff" => "",
			);
		}
	}
	if (count($cat_options)) {
		$filters[] = array(
			"filterName" => "Kategorier",
			"joinOperator" => "or",
			"options" => $cat_options
		);
	}
}

// Build query
$select_fields = array();
foreach ($columns as $col) {
	$select_fields[] = $col['sqlOverride'] . " AS " . $col['field'];
}
$select_fields[] = "a.id AS id";
$select_fields[] = "a.mysale AS mysale";
$select_fields[] = "a.email AS email";

$query = "SELECT " . implode(",\n    ", $select_fields) . "
FROM adresser a
WHERE a.art = 'D' AND {{WHERE}}
ORDER BY {{SORT}}";

// Row styling
$rowStyleFn = function ($row) {
	if (isset($row['lukket']) && $row['lukket'] == 'on') {
		return "color: #f00;";
	}
	return "";
};

// Meta column for kommission view
$metaColumnFn = function ($row) {
	$mySale = isset($row['mysale']) && $row['mysale'] ? "checked='checked'" : "";
	$email = isset($row['email']) ? $row['email'] : '';
	$disabled = $email ? "" : "disabled title='email mangler på konto'";
	$html = "<td align='center'><input type='checkbox' name='mySale[]' value='{$row['id']}' $mySale onclick=\"event.stopPropagation ? event.stopPropagation() : (window.event.cancelBubble=true);\"></td>";
	$html .= "<td align='center'><input type='checkbox' name='invite[]' value='{$row['id']}' $disabled onclick=\"event.stopPropagation ? event.stopPropagation() : (window.event.cancelBubble=true);\"></td>";
	return $html;
};

// Create grid data array
$data = array(
	"table_name" => "debitor_kommission",
	"query" => $query,
	"columns" => $columns,
	"filters" => $filters,
	"rowStyle" => $rowStyleFn,
	"metaColumn" => $metaColumnFn,
);

// Render grid - use unique table_id to prevent conflicts with other grid views
$table_id = 'debitor_kommission';

// Render grid
print "<div style='width: 100%; height: calc(100vh - 34px - 16px);'>";
create_datagrid($table_id, $data);

// Add form for kommission AFTER the wrapper div
$action="debitor_kommission.php";
print "<div style='text-align: right; padding: 10px;'>";
print "<form name='kommission' action='$action' method='post' style='display: inline;'>";
print "<input style='width:75px;' type='submit' name='kommission' value='OK'>";
print "<input style='width:100px;' type='submit' name='chooseAll' value='".findtekst('89|Vælg alle', $sprog_id)."'>";
print "</form>";
print "</div>";
print "</div>";

// Setup tutorial steps
$steps = array();
$steps[] = array(
	"selector" => "#debitore",
	"content" => findtekst('2621|Her ser du en liste af alle dine kunder', $sprog_id)."."
);
$steps[] = array(
	"selector" => "#opret-ny",
	"content" => findtekst('2622|For at oprette en ny kunde, klik her', $sprog_id)."."
);
$steps[] = array(
	"selector" => "#kommission",
	"content" => findtekst('2623|Tilmeld dine kunder til kommissionssalgssystemet her', $sprog_id)."."
);
$steps[] = array(
	"selector" => 'input[type="checkbox"][name^="mySale["], input[type="submit"][name="kommission"]',
	"content" => findtekst('2624|Før du kan inviterer en kunde til \'Mit Salg\', skal du aktivere deres konto. Sæt hak i \'aktiver\' og tryk OK.', $sprog_id)
);
$steps[] = array(
	"selector" => ".kommission-link",
	"content" => findtekst('2625|Når en kunde er aktiveret kan du åbne deres konto her', $sprog_id)."."
);
$steps[] = array(
	"selector" => 'input[type="checkbox"][name^="invite["], input[type="submit"][name="kommission"]',
	"content" => findtekst('2626|Når kunden er aktiveret, vil det være muligt at sende dem en invitation på mail. De vil her få et link til \'Mit Salg\' og kan oprette deres labels derigennem. Det er kun muligt at invitere en kunde, hvis de har en e-mail sat op på deres stamkort.', $sprog_id)
);

include(__DIR__ . "/../includes/tutorial.php");
create_tutorial("deblist", $steps);

if ($menu=='T') {
	include_once '../includes/topmenu/footer.php';
} else {
	include_once '../includes/oldDesign/footer.php';
}

?>

