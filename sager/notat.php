	<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/notat.php --- ver 4.0.8 -- 2023-04-05 --
// LICENSE
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
//
// Copyright (c) 2009-2023 Saldi.dk ApS
// --------------------------------------------------------------------------

// Har lagt javascript i en separat fil ved navn 'jquery.notat.js' 
// Har rettet diverse html fejl
// 20141803 email ved 'gem og afslut' er fjernet. søg 20141803-1
// 20141803 'return confirm' er tilføjet 'gem og afslut'. søg 20141803-2
// 20141903 Ved sag_id i query til liste hentes udførselsaddr. i tabelen 'sager'. søg 20141903-1
// 20142103 Ændret standart email til phpmailer. søg 20142103-1
// 20160107 Har fjernet alle medarbejdere som ikke har en email og aftrådte i 'find person'. Søg #20160107
// 20190910 PHR '$notat' will not be saved if $status > 0. 
// 20190910 PHR Button 'bilag' removed by # as it doesn't work. 

@session_start();
$s_id=session_id();


$bg="nix";
$header='nix';

$menu_sager=NULL;
$menu_planlaeg=NULL;
$menu_dagbog='id="menuActive"';
$menu_kunder=NULL;
$menu_loen=NULL;
$menu_ansatte=NULL;
$menu_certificering=NULL;
$menu_medarbejdermappe=NULL;

$modulnr=0;
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$funktion=if_isset($_GET['funktion']);
$id=if_isset($_GET['id']);
$sag_id=if_isset($_GET['sag_id']);
$konto_id=if_isset($_GET['konto_id']);
$sag_fase=if_isset($_GET['sag_fase']);
$mine_notater=if_isset($_GET['mine_notater']);

if (isset($_POST['find_person']) && ($_POST['find_person']=='Find person')) $funktion="find_person"; 
if (isset($_POST['find_sag']) && ($_POST['find_sag']=='Find sag')) {
#cho $_POST['submit']." | ".$_POST['find_sag']." /sager/sager.php?notat_id=$id&funktion=sagsliste<br>";
#	exit;
	print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/notat.php?notat_id=$id&funktion=findsag\">";
}


print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
	<head>
		<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/form.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/search.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/pajinate.css\">
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
		<!--<script type=\"text/javascript\" src=\"../javascript/jquery.clearable.js\"></script>-->
		<script type=\"text/javascript\" src=\"../javascript/jquery.pajinate.js\"></script>
		
		<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->
		<title>". $pageTitle ."</title>
	</head>
	<body>
		<div id=\"wrapper\">"; 
			include ("../includes/sagsmenu.php");
			print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
				if (substr($sag_rettigheder,2,1)) {
					print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				}	else print "<a href=\"\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				print "</li>
				<!--<li><a href=\"notat.php\" title=\"Dagbog\">Dagbog</a></li>
				<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
				<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
				<li>Current page</li>-->
				<li>Dagbog</li>
			</ul>

		</div><!-- end of breadcrumbbar -->
		
		<div id=\"leftmenuholder\">";
			include("leftmenu.php");
			print "</div><!-- end of leftmenuholder -->";
#cho "$funktion -> $id || $sag_id<br>";

			if ($funktion) $funktion($id,$sag_id,$sag_fase);
			elseif ($id || $sag_id) ret_note($id,$sag_id,$sag_fase);
			else noteliste($mine_notater);
		
		print "</div><!-- end of wrapper -->  
		<!--<div id=\"footer\"><p>Pluder | Pluder</p></div>-->
		<script type=\"text/javascript\" src=\"../javascript/jquery.notat.js\"></script>
	</body>
</html>";


function noteliste($mine_notater) {
	global $brugernavn;
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('datotid','sagsnr','udf_addr1','hvem','status','beskrivelse');
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	
	
	if ($nysort && $nysort==$sort) {
		$sort=$nysort."%20desc";
	foreach ($sortarray as $key => $val){
	($nysortstyle==$sortarray[$key])?$sortstyle[$key]="desc":$sortstyle[$key]="";
		}
	} else { 
		$sort=$nysort;
	foreach ($sortarray as $key => $val){
	($nysortstyle==$sortarray[$key])?$sortstyle[$key]="asc":$sortstyle[$key]="";
		}
	}
		
	if ($_GET['nysortstyle']) {
	$_SESSION['notat_datotid']=$sortstyle[0];
	$_SESSION['notat_sagsnr']=$sortstyle[1];
	$_SESSION['notat_udf_addr1']=$sortstyle[2];
	$_SESSION['notat_hvem']=$sortstyle[3];
	$_SESSION['notat_status']=$sortstyle[4];
	$_SESSION['notat_beskrivelse']=$sortstyle[5];
	} else {
	$sortstyle[0]=$_SESSION['notat_datotid'];
	$sortstyle[1]=$_SESSION['notat_sagsnr'];
	$sortstyle[2]=$_SESSION['notat_udf_addr1'];
	$sortstyle[3]=$_SESSION['notat_hvem'];
	$sortstyle[4]=$_SESSION['notat_status'];
	$sortstyle[5]=$_SESSION['notat_beskrivelse'];
	}
		
	if ($unsetsort) {
		unset($_SESSION['notat_sort'],
			$_SESSION['notat_datotid'],$sortstyle[0],
			$_SESSION['notat_sagsnr'],$sortstyle[1],
			$_SESSION['notat_udf_addr1'],$sortstyle[2],
			$_SESSION['notat_hvem'],$sortstyle[3],
			$_SESSION['notat_status'],$sortstyle[4],
			$_SESSION['notat_beskrivelse'],$sortstyle[5]
		);
	}
		
	if ($sort) $_SESSION['notat_sort']=$sort;
	else $sort=$_SESSION['notat_sort'];
	if (!$sort) $sort="status,%20datotid%20desc";
		
	$sqlsort=urldecode($sort);
	
	$x=0;
	if ($mine_notater=(if_isset($_GET['mine_notater']))) { #20141903-1
		$qtxt="select noter.id as n_id,noter.notat,noter.beskrivelse,noter.hvem,noter.assign_id,noter.datotid,noter.status,noter.sagsnr,noter.assign_to,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn from noter 
		LEFT JOIN sager ON noter.assign_id = sager.id
		where noter.assign_to='sager' and noter.hvem='$brugernavn' order by $sqlsort";
	} else {
		$qtxt="select noter.id as n_id,noter.notat,noter.beskrivelse,noter.hvem,noter.assign_id,noter.datotid,noter.status,noter.sagsnr,noter.assign_to,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn from noter 
		LEFT JOIN sager ON noter.assign_id = sager.id
		where noter.assign_to='sager' and (noter.status>='1' or noter.hvem='$brugernavn') order by $sqlsort";
	}
	$q = db_select("$qtxt",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$notat_id[$x]=$r['n_id'];
		$notat[$x]=htmlspecialchars($r['notat']);
		$beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$forfatter[$x]=htmlspecialchars($r['hvem']);
		$sags_id[$x]=$r['assign_id']*1;
		$datotid[$x]=$r['datotid'];
		$status[$x]=$r['status'];
		$sagsnr[$x]=$r['sagsnr'];
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		if ($sags_id[$x]) $sag_addr[$x]="$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]";
	}
	print "<div class=\"maincontent\">
		<div class=\"contentsoeg\">
		<form name=\"notatsoeg\" action=\"notat.php?id=$_GET[id]\" method=\"get\">
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
				<thead>
				<tr>
					<th width=\"100\">Dato</th>
					<th width=\"100\">Sagsnummer</th>
					<th width=\"100\">Af</th>
					<th width=\"410\">Beskrivelse</th>
					<th colspan=\"2\">&nbsp;</th>
				</tr>
			</thead>
			<tbody>
			<tr>
		
				<td><input class=\"textinput n_dato\" type=\"text\" value=\"\" id=\"n_dato\" name=\"n_dato\" tabindex=\"1\"/></td>
				<td><input class=\"textinput n_sagsnr\" type=\"text\" value=\"\" id=\"n_sagsnr\" name=\"n_sagsnr\" tabindex=\"2\"/></td>
				<td><input class=\"textinput n_af\" type=\"text\" value=\"\" id=\"n_af\" name=\"n_af\" tabindex=\"3\"/></td>
				<td><input class=\"textinput n_beskrivelse\" type=\"text\" value=\"\" id=\"n_beskrivelse\" name=\"n_beskrivelse\" tabindex=\"4\"/></td>
				<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"id\"></td>   
				<td align=\"center\"><input type=\"submit\" value=\"Find notat\" name=\"findnotat\" class=\"button gray small\" tabindex=\"5\"></td>
			</tr>
			</tbody>
	</table>
</form>
			<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
			<span style=\"float:left;\"><a href=\"notat.php?funktion=noteliste&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
			</div>
		</div><!-- end of contentsoeg -->";
		(count($notat_id)<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.notat.js, under 'pagination'
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
			print "<div class=\"contentkundehead\">
				<ul id=\"sort\">
					<li>
					<a href=\"notat.php?funktion=noteliste&amp;nysort=datotid&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:115px\">Dato / tid</a>
					<a href=\"notat.php?funktion=noteliste&amp;nysort=sagsnr&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:70px\">Sagsnr</a>
					<a href=\"notat.php?funktion=noteliste&amp;nysort=udf_addr1&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:215px\">Opstillings adresse</a>
				<a href=\"notat.php?funktion=noteliste&amp;nysort=hvem&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:80px\">Af</a>
				<a href=\"notat.php?funktion=noteliste&amp;nysort=status&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:70px\">Status</a>
				<a href=\"notat.php?funktion=noteliste&amp;nysort=beskrivelse&amp;sort=$sort&amp;nysortstyle=$sortarray[5]\" class=\"felt06 $sortstyle[5]\" style=\"width:255px\">Beskrivelse</a>
				</li>
			</ul>
		</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content\">";
			for ($x=1;$x<=count($notat_id);$x++) {
				$stat = "";
				if (!$status[$x]) $stat = "Kladde";
				elseif (!$status[$x]==1) $stat = "Afventer læsning";
				else $stat = "OK";
				print "<li><a href=\"notat.php?id=$notat_id[$x]\">
					<span class=\"felt01\" style=\"width:115px\">".date("d-m-y",$datotid[$x])." kl. ".date("H:i",$datotid[$x])."&nbsp;</span>
					<span class=\"felt02\" style=\"width:70px\">$sagsnr[$x]&nbsp;</span>
					<span class=\"felt03\" style=\"width:215px\" title='$sag_addr[$x]'>$sag_addr[$x]&nbsp;</span>
					<span class=\"felt04\" style=\"width:80px\">$forfatter[$x]&nbsp;</span>
					<span class=\"felt05\" style=\"width:70px\">$stat&nbsp;</span>
					<span class=\"felt06\" style=\"width:255px\" title='$beskrivelse[$x]'>$beskrivelse[$x]&nbsp;</span>";
					print "</a></li>";
				}
		print "</ul>
	</div><!-- end of contentkunde -->
	<div class=\"page_navigation $abortlist\"></div>
	</div><!-- end of pagin_content -->
	</div><!-- end of maincontent -->";
}

	function vis_note($id,$sag_id,$sag_fase) {

		if ($id) { 
			$qtxt="select * from noter where id = '$id'";
			$r = db_fetch_array($q = db_select("$qtxt",__FILE__ . " linje " . __LINE__));
			$notat=htmlspecialchars($r['notat']);
			$beskrivelse=htmlspecialchars($r['beskrivelse']);
			$forfatter=htmlspecialchars($r['hvem']);
			$sag_id=$r['assign_id']*1;
			$sag_fase=$r['fase'];
			$datotid=$r['datotid'];
			$status=$r['status'];
		}

		print "<div class=\"maincontent\">
			<div class=\"content\">
		<table border=\"0\" cellspacing=\"0\" width=\"600\" style=\"margin-left: 90px;\">
			<tr>
		<td><p class=\"date\">Af $forfatter, $datotid</p></td>
			</tr>
			<tr>
		<td><h4>$beskrivelse</h4>
		<p>$notat</p></td>
			</tr>
		</table>
			</div>
			<hr style=\"margin: 9px 80px 9px 80px;\">
			<div class=\"content\">";
		if ($sag_id) {
			$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
			($r['ufd_addr1'])?$adr=$r['ufd_addr1']:$adr=$r['addr1'];
			($r['ufd_postnr'])?$pnr=$r['ufd_postnr']:$pnr=$r['postnr'];
			($r['ufd_bynavn'])?$byn=$r['ufd_bynavn']:$byn=$r['bynavn'];
			print "<p class=\"infonote\">Denne note er tilknyttet Sag: $sag_id, $r[beskrivelse], $adr, $pnr $byn</p><br/>
			<table border=\"0\" cellspacing=\"0\" width=\"600\" style=\"margin-left: 90px;\">
		<tr>
			<td><a href=\"sager.php?sag_id=$sag_id&funktion=vis_sag\" class=\"button medium gray\">Vis sag</a></td>
		</tr>
			</table>";
		}
			print "</div>
		</div><!-- end of maincontent -->";
	} # end of function vis_note

function find_person($id,$sag_id,$sag_fase) {
	// Finder konto_id fra egen konto
	$r=db_fetch_array(db_select("select id from adresser where art='S'",__FILE__ . " linje " . __LINE__));
	$konto_id=$r['id']*1;
	// Finder egne ansatte 
	$x=0;
	$q=db_select("select navn,email from ansatte where konto_id = '$konto_id' and email > '' and lukket < '0'",__FILE__ . " linje " . __LINE__); #20160107
	while ($r = db_fetch_array($q)) {
		$s_navn[$x]=$r['navn'];
		$s_email[$x]=$r['email'];
		$x++;
	}
	// Hvis ingen sag_id, findes sag_id fra noter
	if (!$sag_id) {
		//if ($id) {
			$r=db_fetch_array(db_select("select assign_id from noter where id='$id'",__FILE__ . " linje " . __LINE__));
			$sag_id=$r['assign_id']*1;
			//}
		}
	// Her finder vi konto_id fra sager
	if ($sag_id) {
		$r=db_fetch_array(db_select("select konto_id from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$konto_id=$r['konto_id']*1;
	// Finder kundes navn og email (da $x skal starte efter ansatte findes $x ved at counte antal ansatte)
		$x=count($s_navn);
		$q=db_select("select navn,email from ansatte where konto_id = '$konto_id'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$d_navn[$x]=$r['navn'];
			$d_email[$x]=$r['email'];
			$x++;
		}
	}
	print "<div class=\"maincontent\">
		<div class=\"content\">
			<form action=\"$_SERVER[PHP_SELF]?id=$id&amp;funktion=ret_note\" method=\"post\">\n";
			for ($x=0;$x<count($s_navn);$x++) {
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$s_email[$x]\">\n";
			}
			print "<input type=\"hidden\" name=\"space\" value=\"\">\n";
			for ($x=count($s_navn);$x<count($s_navn)+count($d_navn);$x++) { 
		print "<input type=\"hidden\" name=\"e_mail[$x]\" value=\"$d_email[$x]\">\n";
			}
			print "<table border=\"0\" cellspacing=\"0\" width=\"595\" style=\"margin:10px 0px 0px 120px;\">
			<tbody>
		<tr>
			<td><p><b>Kolleger:</b></p></td>
			<td colspan=\"2\">&nbsp;</td>
		</tr>
		<tr class=\"tableSagerHead\">
			<td><p>Navn</p></td>
			<td><p>e-mail</p></td>
			<td>&nbsp;</td>
		</tr>
			</tbody>\n";
		print "<tbody class=\"tableSagerZebra\">\n";
		for ($x=0;$x<count($s_navn);$x++) {
			print "<tr>
			<td><p>$s_navn[$x]</p></td>
			<td><p>$s_email[$x]&nbsp;</p></td>\n";
			if ($s_email[$x]) {
		print "<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\"></p></td>\n";
		} else print "<td>&nbsp;</td>\n";
			print "</tr>\n";
		}
			print "</tbody>\n";
			print "<tbody><tr><td class=\"tableSagerBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
		if ($sag_id) {
			print "<tbody>
			<tr>
		<td><p><b>Kundekontakter:</b></p></td>
		<td colspan=\"2\">&nbsp;</td>
			</tr>
			<tr class=\"tableSagerHead\">
		<td><p><b>Navn</b></p></td>
		<td><p><b>e-mail</b></p></td>
		<td>&nbsp;</td>
			</tr>
		</tbody>\n";
		print "<tbody class=\"tableSagerZebra\">\n";
		if (!$d_navn) {
			print "<td colspan=\"2\"align=\"center\"><p><i>Der er ingen kontakter tilknyttet kunde</i></p></td>\n";
			} else {
			for ($x=count($s_navn);$x<count($s_navn)+count($d_navn);$x++) {
		print "<tr>
					<td><p>$d_navn[$x]</p></td>\n";
					if (!$d_email[$x]) {
				print "<td colspan=\"2\"><p><i>Der er ingen e-mail adresse</i></p></td>\n";
					} else {
				print "<td><p>$d_email[$x]</p></td>
				<td><p><input type=\"checkbox\" name=\"mailvalg[$x]\"></p></td>\n";
					}
				print "</tr>";
			}
				print "</tbody>\n";
				print "<tbody><tr><td class=\"tableSagerBorder\" colspan=\"3\">&nbsp;</td></tr></tbody>\n";
			}
		}
			print "<tbody>
				<tr>
					<td colspan=\"3\"><input type=\"submit\" class=\"button gray medium\" name=\"mail_til\" value=\"Ok\"></td>
				</tr>
			</tbody>
		</table>
			</form>
		</div><!-- end of content -->
	</div><!-- end of maincontent -->\n";
	//exit;
} # end of function find_person
	
function ret_note($id,$sag_id,$sag_fase) {
	#cho "ID = $id";
	global $brugernavn;
	global $ansat_id;
	global $db;
	#		if (!$sag_id) $sag_id=if_isset($_POST['sag_id'])*1;
#		if (!$sag_nr) $sag_nr=if_isset($_POST['sag_nr'])*1;
#		if (!$sag_fase) $sag_fase=if_isset($_POST['sag_fase']);
#		$sag_fase*=1;
#		$datotid=date("d-m-Y H:i");
	$konto_id=if_isset($_GET['konto_id']);
	//$sag_id=if_isset($_GET['sag_id']);
	

// kommer fra 'find_person', som indsætter email(s) i db
	if (isset($_POST['mail_til']) && $_POST['mail_til']=='Ok'){
		$besked_til='';
		$mailvalg=$_POST['mailvalg'];
		$e_mail=$_POST['e_mail'];
		for($x=0;$x<count($e_mail);$x++) {
			if ($mailvalg[$x]=='on') {
		($besked_til)?$besked_til.=";".$e_mail[$x]:$besked_til=$e_mail[$x];
			}
		}
		if ($besked_til && $id) db_modify("update noter set besked_til='$besked_til' where id='$id'",__FILE__ . " linje " . __LINE__);
	}
	if (!$sag_id && $sag_nr) { 
		$r = db_fetch_array(db_select("select id,status from sager where sagsnr='$sag_nr'",__FILE__ . " linje " . __LINE__));
		$sag_id=$r['id'];
		$sag_fase=$r['status'];
		if (!$sag_fase) $sag_fase=1;
	} 
	if ($sag_id && $id && is_numeric($sag_fase)) {
		if (!$sag_fase) $sag_fase=1;
		$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$sagsnr=$r['sagsnr'];
		db_modify("update noter set assign_id='$sag_id',fase='$sag_fase',sagsnr='$sagsnr' where id = '$id'",__FILE__ . " linje " . __LINE__);
	} elseif (isset($_POST['id']) && $_POST['id'] && isset($_POST['beskrivelse'])) {
		$id=$_POST['id'];
		#		if ($notat=db_escape_string(if_isset($_POST['notat'])) || $beskrivelse=db_escape_string(if_isset($_POST['beskrivelse']))) {
		$notat=if_isset($_POST['notat']);
		$status=if_isset($_POST['status']);
		// Her slår vi op i noter for at få beskrivelse og nr
		$r = db_fetch_array(db_select("select beskrivelse,nr from noter where id='$id'",__FILE__ . " linje " . __LINE__));
		$notat_beskrivelse=$r['beskrivelse'];
		$notat_nr=$r['nr'];
		// her tjekker vi om beskrivelse er det samme som beskrivelse i db. hvis de er forskellige sættes nr til 0
		if ($notat_beskrivelse != if_isset($_POST['beskrivelse'])) {
			$notat_nr='0';
		} else {
			$notat_nr=$r['nr']*1;
		}
		$beskrivelse=if_isset($_POST['beskrivelse']);
		// hvis beskrivelse er tom
		if (!$beskrivelse) {
			$sag_id=if_isset($_POST['sag_id'])*1;
			if ($r=db_fetch_array(db_select("select * from noter where assign_id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
			$r=db_fetch_array(db_select("select max(nr) as nr from noter where assign_id='$sag_id'",__FILE__ . " linje " . __LINE__));
			$notat_nr=$r['nr']+1;
		} else {
			$notat_nr='1';
		}
		$beskrivelse = "Notat $notat_nr";
			}
		$sagsnr=NULL;
		$sag_id=if_isset($_POST['sag_id'])*1;
		$sag_fase=db_escape_string(if_isset($_POST['sag_fase']));
		$besked_til=db_escape_string(if_isset($_POST['besked_til']));
		$notat_fase=db_escape_string(if_isset($_POST['notat_fase']));
		$kategori=db_escape_string(if_isset($_POST['kategori']));
		$send_mail=db_escape_string(if_isset($_POST['send_mail']));
		$bilag=db_escape_string(if_isset($_POST['bilag']));
#		if ($id && $bilag) 
		if ($afslut=isset($_POST['afslut'])) $status=1;
		elseif ($kladde=isset($_POST['kladde'])) $status=0;
		$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$sagsnr=$r['sagsnr'];

		//echo "sag_id: $sag_id"; exit();
		//$status=0;
		$qtxt="update noter set ";
		if ($status<1) $qtxt.= "notat='".db_escape_string($notat)."',"; #20190910
		$qtxt.= "beskrivelse='".db_escape_string($beskrivelse)."',assign_to='sager',";
		$qtxt.= "assign_id='$sag_id',fase='$sag_fase',besked_til='$besked_til',nr='$notat_nr',sagsnr='$sagsnr'";
		if ($konto_id) $qtxt.=",notat_fase='$notat_fase',kategori='$kategori'";
		elseif (!$besked_til) $qtxt.=",status='$status'";
		$qtxt.= " where id = '$id'";
		db_modify($qtxt,__FILE__ . " linje " . __LINE__);
		if ($afslut) {
			$datotid=date("U");
			db_modify("update noter set datotid='$datotid',status='$status' where id = '$id'",__FILE__ . " linje " . __LINE__);
			#20141803-1
			// Har udkommenteret gammel kode der sender mail ved 'gem og afslut'. Status bliver updateret sammen med datotid, i stedet for ved afsendelse af mail 
			/*
			if ($besked_til) {
		if ($sag_id) {
			$r = db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
			$sag_tekst="<i>".$r['sagsnr'].", ".$r['beskrivelse'].", ".$r['udf_addr1'].", ".$r['udf_postnr']." ".$r['udf_bynavn']."</i>";
		}
		$r = db_fetch_array(db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__));
		$stam_firmanavn=$r['firmanavn'];
		$r = db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
		$ansat_email=$r['email'];
		$ansat_navn="<b>".$r['navn']."</b>";
		
		// Validering af email
		$mail_fejl = "0";
		$email_list=preg_split('[,|;]',$besked_til);
		foreach ($email_list as $mail) {
			if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
				$mail_fejl = "1";
				$error_message = $mail."\\n\\nEr ikke en gyldig email adresse";
				print "<BODY onLoad=\"javascript:alert('$error_message')\">";
			}
		}
		
		if ($ansat_email) {
			if ($mail_fejl == "0") {
				db_modify("update noter set status='$status' where id = '$id'",__FILE__ . " linje " . __LINE__);
				$headers='From: mailer@saldi.dk' . "\r\n";
				$headers.='Reply-To: '.$ansat_email."\r\n";
				$headers.='Content-type: text/html; charset=iso-8859-1' . "\r\n";
				$headers.='X-Mailer: PHP/' . phpversion(). "\r\n";
				$headers .= 'Cc: '.$ansat_email."\r\n";
				$to = "$besked_til";
				$subject = "$beskrivelse";
				$message="Der er en besked til dig fra $ansat_navn";
				if ($sag_id) $message.=", vedrørende $sag_tekst\r\n";
				$message.=".<br><br> \r\nBeskeden kan ses under \"Dagbog\" i vores sagssystem.\r\n";
				$message.="<br><br>\r\nVenlig hilsen $stam_firmanavn.\r\n";
				$subject=utf8_decode($subject);
				$message=utf8_decode($message);
				if (mail ($to, $subject, $message, $headers)) {
					print "<BODY onLoad=\"javascript:alert('Besked sendt')\">";
				}
			}
		} else print "<BODY onLoad=\"javascript:alert('Ingen e-mail i stamdata')\">";
			}
			*/
		}
		// Her sendes mail med notat
		if ($besked_til && $send_mail) {
			if ($sag_id) {
		$r = db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
			$sag_tekst="<i>".$r['sagsnr'].", ".$r['beskrivelse'].", ".$r['udf_addr1'].", ".$r['udf_postnr']." ".$r['udf_bynavn']."</i>";
			}
			//$r = db_fetch_array(db_select("select firmanavn from adresser where art='S'",__FILE__ . " linje " . __LINE__));
			//$stam_firmanavn=$r['firmanavn'];
			$r = db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
			$ansat_email=$r['email'];
			$ansat_navn="<b>".$r['navn']."</b>";
			
			// Validering af email
			$mail_fejl = "0";
			$email_list=preg_split('[,|;]',$besked_til);
			foreach ($email_list as $mail) {
		if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) {
			$mail_fejl = "1";
			$error_message = $mail."\\n\\nEr ikke en gyldig email adresse";
			print "<BODY onLoad=\"javascript:alert('$error_message')\">";
		}
			}
			#20142103-1
			$emails=array();
			$besked_til=str_replace(",",";",$besked_til);
			if (strpos($besked_til,";")) {
		$emails=explode(";",$besked_til);
			} else $emails[0]=$besked_til;
			
			// Henter firma adresse og email
			$row = db_fetch_array(db_select("select * from adresser where art='S'",__FILE__ . " linje " . __LINE__));
			$afsendermail=$row['email'];
			$afsendernavn=$row['firmanavn'];
			
			$smtp = 'localhost';
			$from = $afsendernavn.'<mailer.'.$db.'@saldi.dk>';
			$replyto = $afsendernavn.'<'.$afsendermail.'>';
		
			if ($mail_fejl == "0") {
		ini_set("include_path", ".:../phpmailer");
		require("class.phpmailer.php");
		
		$beskrivelse=utf8_decode($beskrivelse);
		$notat=utf8_decode($notat);
		$sag_tekst=utf8_decode(", vedrørende $sag_tekst");
		
		$mail = new PHPMailer();

		$mail->IsSMTP();                                      // set mailer to use SMTP
		$mail->Host = $smtp;  // specify main and backup server
		$mail->SMTPAuth = false;     // turn on SMTP authentication
		//$mail->Username = "";  // SMTP username
		//$mail->Password = ""; // SMTP password

		$mail->From = 'mailer.'.$db.'@saldi.dk';
		$mail->FromName = $afsendernavn;
		$mail->AddAddress($emails[0]);
		for ($i=1;$i<count($emails);$i++) $mail->AddCC($emails[$i]);
		//$mail->AddAddress("ellen@example.com");                  // name is optional
		$mail->AddReplyTo($afsendermail,$afsendernavn);

		$mail->WordWrap = 50;                                 // set word wrap to 50 characters
		//$mail->AddAttachment("/var/tmp/file.tar.gz");         // add attachments
		//$mail->AddAttachment("/tmp/image.jpg", "new.jpg");    // optional name
		$mail->IsHTML(true);                                  // set email format to HTML

		$mail->Subject = "$beskrivelse";
			
		$mail->Body = "Der er en besked til dig fra $ansat_navn";
		if ($sag_id) $mail->Body .= "$sag_tekst\r\n";
		$mail->Body .= "<br>\r\n";
		$mail->Body .= "<p>$notat</p>";
		$mail->Body .= "<br>\r\nVenlig hilsen $afsendernavn.\r\n";
		$mail->AltBody = "This is the body in plain text for non-HTML mail clients";

		if(!$mail->Send())
		{
			echo "Message could not be sent. <p>";
			echo "Mailer Error: " . $mail->ErrorInfo;
			exit;
		} else {
			for ($i=0;$i<count($emails);$i++) {
				$beskedSendtTil.=$emails[$i].'\\n';
			}
			print "<BODY onLoad=\"javascript:alert('Besked sendt til:\\n$beskedSendtTil')\">";
		}
			}
			
			// Gammel kode til afsendelse af mail
			/*
			if ($mail_fejl == "0") {
		$headers='From: mailer@saldi.dk' . "\r\n";
		$headers.='Reply-To: '.$ansat_email."\r\n";
		$headers.='Content-type: text/html; charset=iso-8859-1' . "\r\n";
		$headers.='X-Mailer: PHP/' . phpversion(). "\r\n";
		$headers .= 'Cc: '.$besked_til."\r\n";
		$to = "$besked_til";
		$subject = "$beskrivelse";
		$message="Der er en besked til dig fra $ansat_navn";
		if ($sag_id) $message.=", vedrørende $sag_tekst\r\n";
		$message.=".<br>$notat\r\n";
		$message.="<br>\r\nVenlig hilsen $stam_firmanavn.\r\n";
		$subject=utf8_decode($subject);
		$message=utf8_decode($message);
		if (mail ($to, $subject, $message, $headers)) {
			print "<BODY onLoad=\"javascript:alert('Besked sendt')\">";
		}
			} 
			*/
		}
		#cho "Sag id $sag_id<br>";
	#cho "select * from noter where id='$id'<br>";
	#exit;
		$r = db_fetch_array(db_select("select * from noter where id='$id'",__FILE__ . " linje " . __LINE__));
		$hvem=$r['hvem'];
		$overskrift=$r['overskrift'];
		$notat=$r['notat'];
		$status=$r['status'];
		$beskrivelse=$r['beskrivelse'];
		$sag_id=$r['assign_id']*1;
		$besked_til=$r['besked_til'];
		$datotid=date("U");
	#exit;
		if ("$hvem" == "$brugernavn") $forfatter = $ansat_navn;
		elseif ($r = db_fetch_array(db_select("select ansatte.navn as navn from ansatte,adresser,brugere where brugere.brugernavn = '$hvem' and ansatte.id=brugere.ansat_id",__FILE__ . " linje " . __LINE__))) {
			$forfatter = $r['navn'];	
		}
	} elseif (!$id) {
#exit;
		if (($notat=db_escape_string(if_isset($_POST['notat']))) || isset($_POST['find_sag'])) { #Fjern ikke tilsyneladende overflødige paranteser.
			if (!$beskrivelse=db_escape_string($_POST['beskrivelse'])) {
			$sag_id=if_isset($_POST['sag_id'])*1;
			if ($r=db_fetch_array(db_select("select * from noter where assign_id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
			$r=db_fetch_array(db_select("select max(nr) as nr from noter where assign_id='$sag_id'",__FILE__ . " linje " . __LINE__));
			$notat_nr=$r['nr']+1;
		} else {
			$notat_nr='1';
		}
		$beskrivelse = "Notat $notat_nr";
			}
			$sag_id=if_isset($_POST['sag_id'])*1;
			$sag_fase=if_isset($_POST['sag_fase'])*1;
			$notat_fase=if_isset($_POST['notat_fase']);
			$kategori=if_isset($_POST['kategori']);
			if (!$notat_nr) $notat_nr='0';
			if ($afslut=isset($_POST['afslut'])) $status=1;
			else $status=0;
			#			elseif ($kladde=isset($_POST['kladde'])) $status=0;
			$datotid=date("U");
			if ($r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__))) {
		$sagsnr=$r['sagsnr'];
			};
			
			//echo "status: $status";
			//exit;
	#			(isset($_POST['notat']))?$status=1:$status=0; 
#cho "insert into noter(notat,beskrivelse,status,hvem,assign_to,assign_id,fase,datotid) values ('$notat','$beskrivelse','$status','$brugernavn','sager','$sag_id','$sag_fase','$datotid')";
#exit;
			db_modify("insert into noter(notat,beskrivelse,status,hvem,assign_to,assign_id,fase,datotid,notat_fase,kategori,nr,sagsnr) values ('$notat','$beskrivelse','$status','$brugernavn','sager','$sag_id','$sag_fase','$datotid','$notat_fase','$kategori','$notat_nr','$sagsnr')",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select max (id) as id from noter where hvem='$brugernavn' and status = '$status'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
	}
	// Her opdateres fase og kategori når status er 1
	if (isset($_POST['opdater']) && $id)  {
		$besked_til=db_escape_string(if_isset($_POST['besked_til']));
		$notat_fase=db_escape_string(if_isset($_POST['notat_fase']));
		$kategori=db_escape_string(if_isset($_POST['kategori']));

			db_modify("update noter set besked_til='$besked_til',notat_fase='$notat_fase',kategori='$kategori' where id = '$id'",__FILE__ . " linje " . __LINE__);
	}
	if (isset($_POST['slet_kladde']) && $id) {
		db_modify("delete from noter where id = '$id'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/notat.php?funktion=noteliste\">";
	}
	// Her opdateres assign_id med sagsnummer ved tilknytning af notat til sag
	if ($sag_id && $id && $konto_id) {
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
	$sagsnr=$r['sagsnr'];
			
		db_modify("update noter set assign_id='$sag_id',sagsnr='$sagsnr' where id = '$id'",__FILE__ . " linje " . __LINE__);
	}

	if ($id) { 
		$qtxt="select * from noter where id = '$id'";
		$r = db_fetch_array($q = db_select("$qtxt",__FILE__ . " linje " . __LINE__));
		$notat=$r['notat'];
		$beskrivelse=htmlspecialchars($r['beskrivelse']);
		$besked_til=htmlspecialchars($r['besked_til']);
		$forfatter=htmlspecialchars($r['hvem']);
		$sag_id=$r['assign_id']*1;
		$sag_fase=$r['fase'];
		$datotid=$r['datotid'];
		$status=$r['status'];
		$notat_fase=$r['notat_fase'];
		$kategori=$r['kategori'];
	}
	
	if ($sag_id && !$sag_nr) {
		$r = db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
		$sag_nr=$r['sagsnr'];
		$sag_beskrivelse=htmlspecialchars($r['beskrivelse']); 
		$udf_addr1=htmlspecialchars($r['udf_addr1']); 
		$udf_postnr=$r['udf_postnr']; 
		$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	}
	
	// Query til kategori
	$x=0;
	$q=db_select("select distinct(kategori) from bilag where assign_to = 'sager' order by kategori",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		if ($r['kategori']) {
			$x++;
			$sags_kat[$x]=$r['kategori'];
		}
	}

	// Query til fase
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=$r['tjekpunkt']; 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
	
	print "<script type=\"text/javascript\" src=\"../tiny_mce/tiny_mce.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.clearable.js\"></script>
		<script type=\"text/javascript\">
		tinyMCE.init({
			// General options
			mode: \"exact\",
			elements : \"dagbog\",
			theme : \"advanced\",
			plugins : \"autolink,lists,spellchecker,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template\",
		// Theme options
			theme_advanced_buttons1 : \"bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,bullist,numlist,|,formatselect,undo,redo\",
		
			theme_advanced_toolbar_location : \"top\",
			theme_advanced_toolbar_align : \"left\",
			theme_advanced_statusbar_location : \"bottom\",
			theme_advanced_resizing : false,
		
			width: \"595\",
			height: \"400\",
		// Skin options
			skin : \"default\",
			skin_variant : \"\",
		// Example content CSS (should be your site CSS)
			content_css : \"../css/mce_content.css\",
		// Drop lists for link/image/../img/template dialogs
			template_external_list_url : \"js/template_list.js\",
			external_link_list_url : \"js/link_list.js\",
			external_image_list_url : \"js/image_list.js\",
			media_external_list_url : \"js/media_list.js\",
		// Replace values for the template plugin
			template_replace_values : {
		username : \"Some User\",
		staffid : \"991234\"
			}
		});
		// Her er function til autocomplete
		$(document).ready(function(){
		
		function formatItem(row) {
			return row[0] + \" : \" + row[1];
		}
		
		function formatResult(row) {
			return row[0] + \" : \" + row[1].replace(/(<.+?>)/gi, '');
		}
		
		function formatResultLast(row) {
			return row[1].replace(/(<.+?>)/gi, '');
		}
		
		$(\".sager\").autocomplete(\"autocomplete.php?mode=sager\", {
				selectFirst: true,
				matchContains: true,
				formatItem: formatItem,
				formatResult: formatResult
		});
		$(\".sager\").result(function(event, data, formatted) {
			$(this).parent().next().find(\".sager\").val(data[1]);
			$(this).parent().next().next().find(\".sag_id\").val(data[2]);
		});
		
		$(\".medarbejder\").autocomplete(\"autocomplete.php?mode=medarbejder\", {
				selectFirst: true,
				matchContains: true,
				formatItem: formatItem,
				formatResult: formatResultLast
		});
		$(\".medarbejder\").result(function(event, data, formatted) {
			$(this).parent().val(data[1]);
		});
		});
		</script>
		<div class=\"maincontent\">
			<form action=\"$_SERVER[PHP_SELF]?id=$id&amp;konto_id=$konto_id&amp;funktion=ret_note\" method=\"post\">
			<div class=\"contentsoegh\">
		<table border=\"0\" cellspacing=\"0\" width=\"595\" style=\"margin-left: 127px;\">
			<tr class=\"link\">
				<!--<th width=\"90\"><a href=\"sager.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;funktion=vis_sag\">Til sag:</a></th>-->\n";
				if (!$sag_nr) {
					print "<th width=\"90\">Sag:</th>\n";
					print "<td align=\"center\"><p><i>Der er ikke tilknyttet en sag til notatet</i></p></td>\n";
					print "<td width=\"90\"><input class=\"button gray small widebtn\" type=\"button\" name=\"find_sag\" value=\"Find sag\" onClick=\"window.location='notat.php?notat_id=$id&amp;funktion=findsag' \"/></td>\n";
					//print "<td><input class=\"textinput sager\" type=\"text\" id=\"sag_nr\" name=\"sag_nr\" value=\"\" /><input type=\"hidden\" class=\"sag_id\" value=\"\" name=\"sag_id\"></td>\n";
					//print "<td width=\"90\"><input class=\"button gray small widebtn\" type=\"submit\" name=\"find_sag\" value=\"Find sag\" /></td>\n";
				} else {
					print "<th width=\"90\"><a href=\"sager.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;funktion=vis_sag\">Til sag:</a></th>\n";
					print "<td colspan=2>&nbsp;<b>$sag_nr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn</b></td>\n";
				}
					print "</tr>\n";
				if ($id) { 
			print "<tr>
				<th>Besked til:</th>
				<td><input class=\"textinputClear clearable\" style=\"text-indent: 3px;padding: 3px 0;font-size: 12px;\" type=\"text\" id=\"ansat\" name=\"besked_til\" value=\"$besked_til\" title=\"$besked_til\"/></td>
				<td width=\"90\"><input class=\"button gray small widebtn\" type=\"submit\" name=\"find_person\" value=\"Find person\" /></td>
			</tr>\n";
				}
			print "<tr>
				<th>Overskrift:</th>";
				if ($status < 1) print "<td colspan=\"2\"><input class=\"textinputClear\" style=\"text-indent: 3px;padding: 3px 0;font-size: 12px;\" type=\"text\" id=\"beskrivelse\" name=\"beskrivelse\" value=\"$beskrivelse\" /></td>";
				else print "<td colspan=\"2\"><input type=\"hidden\" name=\"beskrivelse\" value=\"$beskrivelse\">&nbsp;$beskrivelse</td>";
			print "</tr>
		</table>
		</div><!-- end of contentsoegh -->\n";
		if($konto_id) {
			print "
			<div class=\"contentsoegN\">
			<table border=\"0\" cellspacing=\"0\" width=\"595\" style=\"margin-left: 127px;\">
			<tr>
				<td width=\"90\" align=\"right\">Fase:</td>
				<td><select style=\"width:194px;\" name=\"notat_fase\">";
					for($y=0;$y<=count($tjek_id);$y++) {
				if ($notat_fase==$tjek_punkt[$y]) print "<option value=\"$tjek_punkt[$y]\">$tjek_punkt[$y]&nbsp;</option>";
					}
					for($y=0;$y<=count($tjek_id);$y++) {
				if ($notat_fase!=$tjek_punkt[$y]) print "<option value=\"$tjek_punkt[$y]\">$tjek_punkt[$y]&nbsp;</option>";
					}
					print "</select></td>
				<td width=\"80\" align=\"right\">Kategori:</td>
				<td><select style=\"width:194px;\" name=\"kategori\">";
				if ($kategori==NULL) print "<option value=\"\">&nbsp;</option>\n";
				$x=1;
				while ($sags_kat[$x]) {
					if ($kategori==$sags_kat[$x]) print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
					$x++;
				}
				$x=1;
				while ($sags_kat[$x]) {
					if ($kategori!=$sags_kat[$x]) print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
					$x++;
				}
				if ($kategori!=NULL) print "<option value=\"\">&nbsp;</option>\n";
				/*
					$x=1;
					while ($sags_kat[$x]) {
				if ($kategori==$sags_kat[$x]) print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
				$x++;
					}
					$x=1;
					while ($sags_kat[$x]) {
				if ($kategori!=$sags_kat[$x]) print "<option value=\"$sags_kat[$x]\">$sags_kat[$x]</option>\n";
				$x++;
					}
					*/
					print "</select></td>
			</tr>
			</table>
		</div><!-- end of contentsoegN -->\n";
		}
			print "
			<div class=\"content\">
		<table border=\"0\" cellspacing=\"0\" width=\"595\" style=\"margin-left: 115px;\">\n";
			if ($status >= 1) {
				print "<tbody class=\"notatTable\"><tr>\n";
				print "<td colspan=\"2\"><input type=\"hidden\" name=\"notat\" value=\"$notat\">".str_replace("\n","<br>",$notat)."</td>\n";
			} else {
				print "<tbody><tr>\n";
				print "<td colspan=\"2\"><textarea id=\"dagbog\" name=\"notat\" cols=\"20\" rows=\"50\">".htmlspecialchars($notat)."</textarea></td>\n";
			}
			print "</tr>\n";
			print "</tbody>\n";
			print "<tbody>\n";
			print "<tr>\n";
			
			if (!$status) {
				print "<td style=\"padding-top:10px;\">";
				print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
				print "<input type=\"hidden\" name=\"sag_fase\" value=\"$sag_fase\">";
				print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">";
				print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
				print "<input class=\"button gray medium\" type=\"submit\" name=\"kladde\" value=\"Gem som kladde\">";
				if ($id) {
					print "<input style=\"margin-left:10px;\" class=\"button rosy medium\" type=\"submit\" name=\"slet_kladde\" value=\"Slet kladde\" onclick=\"return confirm('Vil du slette dette notat?');\">";
				}
				if ($besked_til) {
					print "<input style=\"margin-left:10px;\" class=\"button blue medium\" type=\"submit\" name=\"send_mail\" value=\"Send mail\" >";
				}
				$alerttext="Du er ved at gemme og afslutte notat.\\n\\nDet er ikke muligt at rette eller slette notat herefter!"; #20141803-2
				print "</td><td style=\"padding-top:10px;\" align=\"right\"><input class=\"button gray medium\" type=\"submit\" name=\"afslut\" value=\"Gem og afslut\" onclick=\"return confirm('$alerttext')\"></td>";
			} elseif ($status >= 1 && $konto_id) {
				print "<td style=\"padding-top:10px;\">";
				print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
				print "<input type=\"hidden\" name=\"sag_fase\" value=\"$sag_fase\">";
				print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">";
				print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
				print "<input class=\"button gray medium\" type=\"submit\" name=\"opdater\" value=\"Opdater\" >";
				if ($besked_til) {
					print "<input style=\"margin-left:10px;\" class=\"button blue medium\" type=\"submit\" name=\"send_mail\" value=\"Send mail\" >";
				}
				print "</td>\n";
			} elseif ($status >= 1 && $besked_til) {
				print "<td style=\"padding-top:10px;\">";
				print "<input type=\"hidden\" name=\"id\" value=\"$id\">";
				print "<input type=\"hidden\" name=\"sag_fase\" value=\"$sag_fase\">";
				print "<input type=\"hidden\" name=\"sag_id\" value=\"$sag_id\">";
				print "<input type=\"hidden\" name=\"status\" value=\"$status\">";
				print "<input style=\"width:100px;\" class=\"button gray medium\" type=\"submit\" name=\"opdater\" value=\"Opdater\" >";
#				print "<input style=\"margin-left:10px;width:100px;\" class=\"button green medium\" type=\"submit\" name=\"bilag\" value=\"Bilag\" >";
				print "<input style=\"margin-left:10px;width:100px;\" class=\"button blue medium\" type=\"submit\" name=\"send_mail\" value=\"Send mail\" >";
			} else print "<td style=\"padding-top:10px;width:100px;\"><input class=\"button gray medium\" type=\"submit\" name=\"opdater\" value=\"Opdater\" ></td>\n";
			#else print "<td><input class=\"button gray medium\" type=\"submit\" name=\"opdater\" value=\"Opdater\" ></td>";
		print "</tr>
			</tbody>
		</table>	
			</div><!-- end of content -->
		</form>
	</div><!-- end of maincontent -->";
}

function findsag() {
	$notat_id=if_isset($_GET['notat_id']);
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('sagsnr','firmanavn','udf_addr1','beskrivelse','status');
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	$findsag_limit=if_isset($_POST['findsag_limit']);
	
	
	if ($nysort && $nysort==$sort) {
		$sort=$nysort."%20desc";
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="desc":$sortstyle[$key]="";
		}
	}else{ 
		$sort=$nysort;
			foreach ($sortarray as $key => $val){
			($nysortstyle==$sortarray[$key])?$sortstyle[$key]="asc":$sortstyle[$key]="";
		}
	}
	
	if ($_GET['nysortstyle']) {
		$_SESSION['findsag_sagsnr']=$sortstyle[0];
		$_SESSION['findsag_firmanavn']=$sortstyle[1];
		$_SESSION['findsag_udf_addr1']=$sortstyle[2];
		$_SESSION['findsag_ref']=$sortstyle[3];
		$_SESSION['findsag_status']=$sortstyle[4];
	} else {
		$sortstyle[0]=$_SESSION['findsag_sagsnr'];
		$sortstyle[1]=$_SESSION['findsag_firmanavn'];
		$sortstyle[2]=$_SESSION['findsag_udf_addr1'];
		$sortstyle[3]=$_SESSION['findsag_ref'];
		$sortstyle[4]=$_SESSION['findsag_status'];
	}
	
	if ($_POST['findsag_limit']) {
		$_SESSION['findsag_limit']=$findsag_limit;
	} else {
		$findsag_limit=$_SESSION['findsag_limit'];
	}
	
	if ($unsetsort) {
		unset($_SESSION['findsag_sort'],
			$_SESSION['findsag_sagsnr'],$sortstyle[0],
			$_SESSION['findsag_firmanavn'],$sortstyle[1],
			$_SESSION['findsag_udf_addr1'],$sortstyle[2],
			$_SESSION['findsag_ref'],$sortstyle[3],
			$_SESSION['findsag_status'],$sortstyle[4],
			$_SESSION['findsag_limit'],$findsag_limit
		);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['findsag_sort']=$sort;
	else $sort=$_SESSION['findsag_sort'];
	if (!$sort) $sort="sagsnr%20desc";
	
	$sqlsort=urldecode($sort);
	
	$limitarray=array('500','1000','2500','5000','10000','NULL');
	$limitnavn=array('500','1000','2500','5000','10000','Alle');
	
	($findsag_limit)?$limit=$findsag_limit:$limit='500';
	
	$x=0;
	$q=db_select("select * from sager order by $sqlsort limit $limit",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$sag_id[$x]=$r['id'];
		$sag_nr[$x]=$r['sagsnr']*1;
		$sag_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$sag_firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$sag_ansvarlig[$x]=htmlspecialchars($r['ref']);
		$sag_omfang[$x]=htmlspecialchars($r['omfang']);
		$sag_oprettet[$x]=htmlspecialchars($r['ref']);
		$udf_firmanavn[$x]=htmlspecialchars($r['udf_firmanavn']);
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		$oprettet_af[$x]=htmlspecialchars($r['oprettet_af']);
		$dato[$x]=date("d-m-y",$r['tidspkt']);
		$tid[$x]=date("H:i",$r['tidspkt']);
		$status[$x]=$r['status'];
		$konto_id[$x]=$r['konto_id'];
		}
	$antal_sager=$x;
/*
	print "<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"sager.php?funktion=vis_sag\" method=\"get\">
			<table border=\"0\" cellspacing=\"0\" width=\"778\">
		<thead>
			<tr>
				<th width=\"100\">Sagsnr</th>
				<th width=\"560\">Opstillings adresse</th>
				<th colspan=\"2\">&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><input class=\"textinput sagsagsnr\" type=\"text\" value=\"\" id=\"sagsagsnr\" name=\"sagsagsnr\" tabindex=\"1\"/></td>
				<td><input class=\"textinput sagadresse\" type=\"text\" value=\"\" id=\"sagadresse\" name=\"sagadresse\" tabindex=\"2\"/></td>
				<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"sag_id\"></td>   
				<td align=\"center\"><input type=\"submit\" value=\"Find sag\" name=\"findsag\" class=\"button gray small\" tabindex=\"3\"></td>
			</tr>
		</tbody>
			</table>
			</form>
		</div><!-- end of contentsoeg -->";
		*/
		print "
		<div class=\"maincontent\">
		<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"notat.php\" method=\"get\">
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
		<thead>
			<tr>
				<th width=\"100\">Sagsnr</th>
				<th width=\"225\">Kunde</th>
				<th width=\"385\">Opstillings adresse</th>
				<th colspan=\"3\">&nbsp;</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				
				<td><input class=\"textinput n_sagsagsnr\" type=\"text\" value=\"\" id=\"n_sagsagsnr\" name=\"n_sagsagsnr\" tabindex=\"1\"/></td>
				<td><input class=\"textinput n_sagfirmanavn\" type=\"text\" value=\"\" id=\"n_sagfirmanavn\" name=\"n_sagfirmanavn\" tabindex=\"2\"/></td>
				<td><input class=\"textinput n_sagadresse\" type=\"text\" value=\"\" id=\"n_sagadresse\" name=\"n_sagadresse\" tabindex=\"3\"/></td>
				<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"sag_id\"></td>
				<td style=\"padding:0px;\"><input type=\"hidden\" class=\"konto_id\" value=\"\" name=\"konto_id\"><input type=\"hidden\"  value=\"$notat_id\" name=\"id\"></td>   
				<td align=\"center\"><input type=\"submit\" value=\"Find sag\" name=\"findsag\" class=\"button gray small\" tabindex=\"4\"></td>
				
			</tr>
		</tbody>
			</table>
			</form>
			<form name=\"sagliste\" action=\"notat.php?funktion=findsag\" method=\"post\">
		<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
			<span style=\"float:left;width:270px;\"><a href=\"notat.php?funktion=findsag&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
			<span style=\"float:left;\"><h3><i><b>Tilknyt en sag til notatet her!</b></i></h3></span>\n";
			($antal_sager<=500)?$display="display:none;":$display=NULL;
			print "
			<div style=\"float:right;$display\">
				<p style=\"float:left;\">Vælg antal viste linjer:&nbsp;</p>
				<select name=\"findsag_limit\" class=\"selectinputloen\" style=\"width:76px;\" onchange=\"this.form.submit()\">\n";
				
					for ($i=0;$i<count($limitarray);$i++) {
				if ($findsag_limit==$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
					}
					for ($i=0;$i<count($limitarray);$i++) {
				if ($findsag_limit!=$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
					}
				
					print "
				</select>
			</div><!-- end of select -->
		</div>
			</form>
		</div><!-- end of contentsoeg -->\n";
		($antal_sager<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.notat.js, under 'pagination i findsag'
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
		
		print "<div class=\"contentkundehead\">
			<ul id=\"sort\">
			<li>
					<a href=\"notat.php?funktion=findsag&amp;nysort=sagsnr&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:65px\">Sagsnr</a>
					<a href=\"notat.php?funktion=findsag&amp;nysort=firmanavn&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:205px\">Kunde</a>
					<a href=\"notat.php?funktion=findsag&amp;nysort=udf_addr1&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:315px\">Opstillings adresse</a>
					<a href=\"notat.php?funktion=findsag&amp;nysort=ref&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:145px\">Ansvarlig</a>
					<a href=\"notat.php?funktion=findsag&amp;nysort=status&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:75px\">Status</a>
			</li>
			</ul>
	</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content_findsag\">";
		for ($x=1;$x<=$antal_sager;$x++) {
		/*$stat = "";
				if ($status[$x]<=1) $stat = "Tilbud";
				if ($status[$x]==2) $stat = "Opstart";
				if ($status[$x]==3) $stat = "Aflevering";
				if ($status[$x]==4) $stat = "Kontrol";
				if ($status[$x]==5) $stat = "Nedtagning";
				if ($status[$x]==6) $stat = "Afsluttet";*/
				/*
				$statcolor = NULL;
				if ($status[$x]=='Opmåling') $statcolor = "color:#006600;";// green
				if ($status[$x]=='Tilbud') $statcolor = "color:#009900;";//
				if ($status[$x]=='Ordre modtaget') $statcolor = "color:#00CC00;";//
				if ($status[$x]=='Montage') $statcolor = "color:#C1BE00;";//
				if ($status[$x]=='Aflevering') $statcolor = "color:#FF9900;";// 
				if ($status[$x]=='Afmeldt') $statcolor = "color:#FF6600;";// 
				if ($status[$x]=='Demontage') $statcolor = "color:#FF3300;";// 
				if ($status[$x]=='Afsluttet') $statcolor = "color:#FF0000;";// red
				*/
				$statcolor = NULL;
				if ($status[$x]=='Tilbud') $statcolor = "color:black;";
				if ($status[$x]=='Ordrebekræftelse') $statcolor = "color:black;";
				if ($status[$x]=='Montage') $statcolor = "color:red;";
				if ($status[$x]=='Godkendt') $statcolor = "color:green;";
				if ($status[$x]=='Afmeldt') $statcolor = "color:#C1BE00;";
				if ($status[$x]=='Afsluttet') $statcolor = "color:black;";
			print "<li><a href=\"notat.php?konto_id=$konto_id[$x]&amp;id=$notat_id&amp;sag_id=$sag_id[$x]\">
				<span class=\"felt01\" style=\"width:65px;\">$sag_nr[$x]&nbsp;</span>
				<span class=\"felt02\" style=\"width:205px;\" title='$sag_firmanavn[$x]'>$sag_firmanavn[$x]&nbsp;</span>
				<span class=\"felt03\" style=\"width:315px;\" title='$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]'>$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]&nbsp;</span>
				<span class=\"felt04\" style=\"width:145px;\" title='$sag_ansvarlig[$x]'>$sag_ansvarlig[$x]&nbsp;</span>
				<span class=\"felt05\" style=\"width:75px;$statcolor\" title='$status[$x]'>$status[$x]&nbsp;</span>";
			print "</a></li>";
		}
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of pagin_content -->
		</div><!-- end of maincontent -->";
		/*
	for ($x=1;$x<=$antal_sager;$x++) {
		print "<div class=\"contentlist\">";
			if ($notat_id) $href="notat.php?id=$notat_id&sag_id=$sag_id[$x]&sag_fase=$status[$x]";
			else $href="sager.php?funktion=vis_sag&amp;sag_id=$sag_id[$x]";
			print "<h4><a href=\"$href\">Sag: $sag_nr[$x], $sag_beskrivelse[$x], $udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]</a></h4>
			<hr>
			<table border=\"0\" cellspacing=\"0\" width=\"780\">
		<tr>
			<td width=\"72\"><p><b>Kunde:</b></p></td>
			<td colspan=\"5\"><p><b>$sag_firmanavn[$x]</b></p></td>
			<td width=\"75\" align=\"center\"><p><b>Status:</b></p></td>
		</tr>
		<tr>
			<td valign=\"top\"><p><b>Omfang:</b></p></td>
			<td colspan=\"5\"><p>$sag_omfang[$x]&nbsp;</p></td>
			<!--<td rowspan=\"2\" align=\"center\" valign=\"top\"><p class=\"staclrgreen\" title=\"tilbud\"></p></td>-->";
			if ($status[$x]<=1) print "<td rowspan=\"2\" align=\"center\" valign=\"top\">Tilbud</td>";
			if ($status[$x]==2) print "<td rowspan=\"2\" align=\"center\" valign=\"top\">Opstart</td>";
			if ($status[$x]==3) print "<td rowspan=\"2\" align=\"center\" valign=\"top\">Aflevering</td>";
			if ($status[$x]==4) print "<td rowspan=\"2\" align=\"center\" valign=\"top\">Kontrol</td>";
			if ($status[$x]==5) print "<td rowspan=\"2\" align=\"center\" valign=\"top\">Nedtagning</td>";
			if ($status[$x]==6) print "<td rowspan=\"2\" align=\"center\" valign=\"top\">Afsluttet</td>";
		print "</tr>
		<tr>
			<td><p><b>Ansvarlig:</b></p></td>
			<td><p>$sag_ansvarlig[$x]&nbsp;</p></td>
			<td width=\"70\"><p><b>Indtastet:</b></p></td>
			<td><p>d.$dato[$x] kl. $tid[$x]</p></td>
			<td width=\"25\"><p><b>Af:</b></p></td>
			<td><p>$oprettet_af[$x]</p></td>
		</tr>
			</table>
		</div><!-- end of contentlist -->
		<hr>";
	}*/
}
?>
