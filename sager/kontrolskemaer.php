<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();

// ------ sager/kontrolskemaer.php-------lap 3.3.0 ------2013-11-04------07:30---------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
// 
// Programmet er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
// 
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2003-2013 Danosoft ApS
// ----------------------------------------------------------------------
// 20170303 Visning af bilag, hvis tilknyttet. Søg #20170303
// 20250130 migrate utf8_en-/decode() to mb_convert_encoding

	$bg="nix";
	$header='nix';

	$menu_sager=NULL;
	$menu_planlaeg=NULL;
	$menu_dagbog=NULL;
	$menu_kunder=NULL;
	$menu_loen=NULL;
	$menu_ansatte=NULL;
	$menu_certificering='id="menuActive"';
	$menu_medarbejdermappe=NULL;

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");

	$funktion=if_isset($_GET['funktion']);
	$skema_id=if_isset($_GET['tjekskema_id']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$vis=if_isset($_GET['vis']);
	
	if (!$funktion)$funktion='kontrolskemaliste';
	/*
	if (!$funktion) {
		($sag_id)?$funktion='':$funktion='kontrolskemaliste';  
	}
	*/
print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
<html>
	<head>
		<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/search.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/form.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/pajinate.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/print.css\" media=\"print\"> 
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autosize.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.pajinate.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.kontrolskemaer.js\"></script>
		
		<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->
		<!--[if IE]>
		
		<style>
				.tableSager
				{
						table-layout: fixed;
						width: 828px;
				}
		</style>
		<![endif]-->
		
		<script type=\"text/javascript\">
		/* javascript funktion til print */
			function printDiv(divName) { // Original code. MÅ IKKE SLETTES!!!!!
				var printContents = document.getElementById(divName).innerHTML;
				var originalContents = document.body.innerHTML;

				document.body.innerHTML = printContents;

				window.print();

				document.body.innerHTML = originalContents;
			}
		</script>
		<title>Stillads</title>
	</head>
	<body>
		<div id=\"wrapper\">";
			include ("../includes/sagsmenu.php");
			
			
			if (substr($sag_rettigheder,2,1)) $funktion($skema_id);
			print "</div><!-- end of maincontent -->
			</div><!-- end of wrapper -->  
		<!-- <div id=\"footer\"><p>Pluder | Pluder</p></div> -->
		<script type=\"text/javascript\">
		
		// function til pagination
		$(document).ready(function(){
			$('#paging_container').pajinate({
				items_per_page : 50,
				item_container_id : '.paging_content',
				num_page_links_to_display : 10,
				abort_on_small_lists: true,
				nav_label_info : 'Viser {0}-{1} af {2}',
				nav_label_first : '<<',
				nav_label_last : '>>',
				nav_label_prev : '<',
				nav_label_next : '>'
			});
		});
		
		</script>
	</body>
</html>";

function kontrolskemaliste() {

	global $sag_rettigheder;
	global $funktion;
	
	$notat_id=if_isset($_GET['notat_id']);
	$menu_certificering='id="menuActive"';
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('datotid','tjekpunkt','opg_navn','sagsnr','udf_addr1','skema_hvem','sjak','status');
	$vis=if_isset($_GET['vis']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	$skema_limit=if_isset($_POST['skema_limit']);
	
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
		$_SESSION['kontrolskema_datotid']=$sortstyle[0];
		$_SESSION['kontrolskema_skema']=$sortstyle[1];
		$_SESSION['kontrolskema_opgave']=$sortstyle[2];
		$_SESSION['kontrolskema_sagsnr']=$sortstyle[3];
		$_SESSION['kontrolskema_addr']=$sortstyle[4];
		$_SESSION['kontrolskema_af']=$sortstyle[5];
		$_SESSION['kontrolskema_sjak']=$sortstyle[6];
		//$_SESSION['kontrolskema_status']=$sortstyle[7];
	} else {
		$sortstyle[0]=$_SESSION['kontrolskema_datotid'];
		$sortstyle[1]=$_SESSION['kontrolskema_skema'];
		$sortstyle[2]=$_SESSION['kontrolskema_opgave'];
		$sortstyle[3]=$_SESSION['kontrolskema_sagsnr'];
		$sortstyle[4]=$_SESSION['kontrolskema_addr'];
		$sortstyle[5]=$_SESSION['kontrolskema_af'];
		$sortstyle[6]=$_SESSION['kontrolskema_sjak'];
		//$sortstyle[7]=$_SESSION['kontrolskema_status'];
	}
	
	if ($_POST['skema_limit']) {
		$_SESSION['skema_limit']=$skema_limit;
	} else {
		$skema_limit=$_SESSION['skema_limit'];
	}
	
	if ($unsetsort) {
		unset($_SESSION['kontrolskema_sort'],
					$_SESSION['kontrolskema_datotid'],$sortstyle[0],
					$_SESSION['kontrolskema_skema'],$sortstyle[1],
					$_SESSION['kontrolskema_opgave'],$sortstyle[2],
					$_SESSION['kontrolskema_sagsnr'],$sortstyle[3],
					$_SESSION['kontrolskema_addr'],$sortstyle[4],
					$_SESSION['kontrolskema_af'],$sortstyle[5],
					$_SESSION['kontrolskema_sjak'],$sortstyle[6],
					//$_SESSION['kontrolskema_status'],$sortstyle[7],
					$_SESSION['skema_limit'],$skema_limit
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['kontrolskema_sort']=$sort;
	else $sort=$_SESSION['kontrolskema_sort'];
	if (!$sort) $sort="datotid%20DESC";
	
	$sqlsort=urldecode($sort);
	
	$limitarray=array('500','1000','2500','5000','10000','NULL');
	$limitnavn=array('500','1000','2500','5000','10000','Alle');
	
	($skema_limit)?$limit=$skema_limit:$limit='500';
	
	$x=0; #22052014
	$q=db_select("SELECT fase FROM tjekliste WHERE assign_to = 'sager' AND assign_id = '0' ORDER BY fase ASC",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$fase[$x]=$r['fase'];
	}
	$antal_fase=$x;
		
	for ($x=1;$x<=$antal_fase;$x++) {
		if ($vis==$fase[$x]) $and="and fase ='$fase[$x]'";
	}
	/*
	// ændre $and (where) til sidemenu-visning
	if ($vis=='1') $and="and fase ='1'"; 
	elseif ($vis=='2') $and="and fase ='2'";  
	elseif ($vis=='3') $and="and fase ='3'"; 
	else $and='';
	*/
	$x=0;
	$q=db_select("SELECT tjekskema.id as skema_id,tjekskema.datotid,tjekskema.tjekliste_id as tjek_id,tjekskema.sag_id,tjekskema.hvem as skema_hvem,tjekskema.opg_navn,tjekskema.sjak,tjekskema.sjakid,tjekliste.id,tjekliste.tjekpunkt,tjekliste.fase,tjekliste.assign_to,tjekliste.assign_id,sager.id,sager.udf_addr1,sager.udf_postnr,sager.udf_bynavn,sager.sagsnr FROM tjekskema 
								INNER JOIN tjekliste ON tjekskema.tjekliste_id = tjekliste.id
								INNER JOIN sager ON tjekskema.sag_id = sager.id
								WHERE tjekliste.assign_to = 'sager' and tjekliste.assign_id = '0' $and ORDER BY $sqlsort limit $limit",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$skema_id[$x]=$r['skema_id'];
		$skema_navn[$x]=$r['tjekpunkt'];
		$skema_datotid[$x]=$r['datotid'];
		$skema_sjak[$x]=htmlspecialchars($r['sjak']);
		$skema_sjakid[$x]=$r['sjakid'];
		$skema_addr[$x]=htmlspecialchars($r['udf_addr1']);
		$skema_postnr[$x]=$r['udf_postnr'];
		$skema_bynavn[$x]=$r['udf_bynavn'];
		$skema_kontrol_af[$x]=htmlspecialchars($r['skema_hvem']);
		$skema_opgave[$x]=htmlspecialchars($r['opg_navn']);
		$skema_sagsnr[$x]=$r['sagsnr'];
		$skema_status[$x]=$r['skema_id'];
		$skema_fase[$x]=$r['fase']*1;
		$skema_sagid[$x]=$r['sag_id'];
		$skema_tjek_id[$x]=$r['tjek_id'];
		}
	$antal_skemaer=$x;
	
	$x=0;
	$q=db_select("SELECT id FROM tjekskema",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$skema_id_alle[$x]=$r['id'];
		}
	$antal_skemaer_alle=$x;
	/*
	echo "antal_skemaer: $antal_skemaer<br>";
	echo "antal_skemaer_alle: $antal_skemaer_alle";
	*/
		print "<div id=\"breadcrumbbar\">
				<ul id=\"breadcrumb\">
					<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"#\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>
					<li><a href=\"certificering.php\" title=\"Tilbage til certificerings-menu\">Certificering</a></li>
					<!--<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
					<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
					<li>Current page</li>-->
					<li>Kontrolskemaer</li>
				</ul>

		</div><!-- end of breadcrumbbar -->

		<div id=\"leftmenuholder\">";
			include ("leftmenu.php");
		print "</div><!-- end of leftmenuholder -->";
			
		print "<div class=\"maincontent\">";
		print "
		<div class=\"contentsoeg\">
		<form name=\"skemasoeg\" action=\"kontrolskemaer.php\" method=\"get\"><!-- funktion skal være en variable -->
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
				<thead>
					<tr>
						<th width=\"100\">Dato</th>
						<th width=\"100\">Sagsnr</th>
						<th width=\"100\">Af</th>
						<th width=\"410\">Opstillings adresse</th>
						<th colspan=\"6\">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						
						<td><input class=\"textinput k_dato\" type=\"text\" value=\"\" id=\"k_dato\" name=\"k_dato\" tabindex=\"1\"/></td>
						<td><input class=\"textinput k_sagsnr\" type=\"text\" value=\"\" id=\"k_sagsnr\" name=\"k_sagsnr\" tabindex=\"2\"/></td>
						<td><input class=\"textinput k_af\" type=\"text\" value=\"\" id=\"k_af\" name=\"k_af\" tabindex=\"3\"/></td>
						<td><input class=\"textinput k_adresse\" type=\"text\" value=\"\" id=\"k_adresse\" name=\"k_adresse\" tabindex=\"4\"/></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"tjekskema_id\"></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"fase\" value=\"\" name=\"sag_fase\"></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"sag_id\" value=\"\" name=\"sag_id\"></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"tjek_id\" value=\"\" name=\"tjek_id\"></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" value=\"vis_skema\" name=\"funktion\"></td>
						<td align=\"center\"><input type=\"submit\" value=\"Find skema\" name=\"findsag\" class=\"button gray small\" tabindex=\"4\"></td>
						
					</tr>
				</tbody>
			</table>
			</form>
			<form name=\"sagliste\" action=\"kontrolskemaer.php?funktion=kontrolskemaliste\" method=\"post\">
				<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;width:260px;\"><a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>\n";
					($antal_skemaer_alle<=500)?$display="display:none;":$display=NULL;
					print "
					<div style=\"float:right;$display\">
						<p style=\"float:left;\">Vælg antal viste linjer:&nbsp;</p>
						<select name=\"skema_limit\" class=\"selectinputloen\" style=\"width:76px;\" onchange=\"this.form.submit()\">\n";
						
							for ($i=0;$i<count($limitarray);$i++) {
									if ($skema_limit==$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
								}
								for ($i=0;$i<count($limitarray);$i++) {
									if ($skema_limit!=$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
								}
								
							print "
						</select>
					</div><!-- end of select -->
				</div>
			</form>
		</div><!-- end of contentsoeg -->\n";
		($antal_skemaer<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i head, under pagination
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
			
		print "<div class=\"contentkundehead\">
			<ul id=\"sort\">
					<li>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=datotid&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:115px\">Dato</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=tjekpunkt&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:120px\">Skema</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=opg_navn&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:75px\">Opgave</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=sagsnr&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt03 $sortstyle[3]\" style=\"width:65px\">Sagsnr</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=udf_addr1&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt04 $sortstyle[4]\" style=\"width:215px\">Opstillings adresse</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=skema_hvem&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[5]\" class=\"felt05 $sortstyle[5]\" style=\"width:75px\">Af</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste&amp;nysort=sjak&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[6]\" class=\"felt06 $sortstyle[6]\" style=\"width:70px\">Sjak</a>
							<a href=\"kontrolskemaer.php?funktion=kontrolskemaliste\" class=\"felt07 $sortstyle[7]\" style=\"width:60px\">status</a>
					</li>
			</ul>
		</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content\">";
			
				
				
				for ($x=1;$x<=$antal_skemaer;$x++) {
				
					$tjekpunkter_id=array();
					$status=array();
					
					$y=0;
					$q=db_select("select * from tjekpunkter where tjekskema_id = '$skema_id[$x]'",__FILE__ . " linje " . __LINE__);
					while ($r = db_fetch_array($q)) {
						$tjekpunkter_id[$y]=$r['id'];
						$status[$y]=$r['status']; 
						$y++;
					}
					$antal_punkter=$y; // bruges kun til kontrol... kan slettes
					/*
						print "<pre style=\"float:left;\">";
						print "Status:<br>";
						print_r ($status);
						print "</pre>";
						print "<pre>";
						print "Id:<br>";
						print_r ($tjekpunkter_id);
						print "</pre>";
						*/
						$stat1 = NULL;
						$stat2 = NULL;
						$stat3 = NULL;
						$stattitle = NULL;
						$stat1 = count( array_keys( $status, "1" ));
						$stat2 = count( array_keys( $status, "2" ));
						$stat3 = count( array_keys( $status, "3" ));
						$stattitle = "OK: $stat1 - Fejl: $stat2 - Kritisk: $stat3";
						
						if($stat1==0) $stat1 = "";
						if($stat2==0) $stat2 = "";
						if($stat3==0) $stat3 = "";
						
						$stat1color = NULL;
						$stat2color = NULL;
						$stat3color = NULL;
						($stat1)?$stat1color = "background-color:green;color:#333;":$stat1 = "<span class=\"contentkundestatus\">&nbsp;</span>";
						($stat2)?$stat2color = "background-color:yellow;color:#333;":$stat2 = "<span class=\"contentkundestatus\">&nbsp;</span>";
						($stat3)?$stat3color = "background-color:red;color:#333;":$stat3 = "<span class=\"contentkundestatus\">&nbsp;</span>";
						
						// Her nulstiller vi $sjaktitle variable, så den ikke genskriver hvis der ingen $skema_sjakid eller $skema_sjak[$x] er. 
						$sjaktitle = NULL;
						
						// Denne funktion laver $skema_sjakid, som indeholder ansatte id(er) om til title med navn og initialer
						if ($skema_sjakid[$x]) {
							
							$sjaktitleny = NULL;
							$sjakider = NULL;
							
							// Her skiller vi id'erne til et array
							$sjakider = explode(";", $skema_sjakid[$x]);
						
							// Query der henter initialer og navn fra ansatte
							for ($z=0;$z<count($sjakider);$z++) {
								$r=db_fetch_array(db_select("select * from ansatte where id = '$sjakider[$z]'",__FILE__ . " linje " . __LINE__)); 
								$sjaknavn[$z]=$r['navn'];
								$sjakini[$z]=$r['initialer'];
								$sjaktitleny[$z]="(".$r['initialer'].")"." ".$r['navn']."\n";
							}
		
							// Her splejser vi $sjaktitleny til en streng
							$sjaktitle = implode("", $sjaktitleny);
						} else {
							$sjaktitle = $skema_sjak[$x];
						}
				
						($skema_fase[$x]==1)?$funktion_navn = "vis_arbejdsseddel":$funktion_navn = "vis_kontrolskema";
					print "<li><a href=\"kontrolskemaer.php?funktion=$funktion_navn&amp;sag_id=$skema_sagid[$x]&amp;sag_fase=$skema_fase[$x]&amp;tjek_id=$skema_tjek_id[$x]&amp;tjekskema_id=$skema_id[$x]\">
						<span class=\"felt01\" style=\"width:115px;\">".date("d-m-y",$skema_datotid[$x])." kl. ".date("H:i",$skema_datotid[$x])."&nbsp;</span>
						<span class=\"felt02\" style=\"width:120px;\" title='$skema_navn[$x]'>$skema_navn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:75px;\" title='$skema_opgave[$x]'>$skema_opgave[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:65px;\" title='$skema_sagsnr[$x]'>$skema_sagsnr[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:215px;\" title='$skema_addr[$x], $skema_postnr[$x] $skema_bynavn[$x]'>$skema_addr[$x], $skema_postnr[$x] $skema_bynavn[$x]&nbsp;</span>
						<span class=\"felt06\" style=\"width:75px;\" title='$skema_kontrol_af[$x]'>$skema_kontrol_af[$x]&nbsp;</span>
						<span class=\"felt07\" style=\"width:70px;\" title='$sjaktitle'>".rtrim($skema_sjak[$x], ', ')."&nbsp;</span>
						<span class=\"felt08\" style=\"width:60px;\" title='$stattitle'><span class=\"contentkundestatus\" style=\"$stat1color\">$stat1</span><span class=\"contentkundestatus\" style=\"$stat2color\">$stat2</span><span class=\"contentkundestatus\" style=\"$stat3color\">$stat3</span></span>";
					print "</a></li>";
				}
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of paging_container -->";
		
}

function vis_skema() { // bruges til at redirecte søgning fra kontrolskemaliste
	$sag_id=if_isset($_GET['sag_id']);
	$sag_fase=if_isset($_GET['sag_fase']);
	$tjekpunkt_id=if_isset($_GET['tjek_id']);
	$tjekskema_id=if_isset($_GET['tjekskema_id']);
	if($sag_fase==NULL) {
		header("Location: kontrolskemaer.php?funktion=kontrolskemaliste"); exit();
	} else {
		($sag_fase==1)?$funktion_navn = "vis_arbejdsseddel":$funktion_navn = "vis_kontrolskema";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/kontrolskemaer.php?funktion=$funktion_navn&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\">";
		exit();
	}
	
	//header("Location: kontrolskemaer.php?funktion=$funktion_navn&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id"); exit();
	//echo "funktion_navn: $funktion_navn";
	/*
	echo "Sag_id: $sag_id<br>";
	echo "Sag_fase: $sag_fase<br>";
	echo "tjekpunkt_id: $tjekpunkt_id<br>";
	echo "tjekskema_id: $tjekskema_id<br>";
	echo "funktion_navn: $funktion_navn";
	*/
}

function vis_arbejdsseddel() {

	global $regnskab;
	global $sag_rettigheder;
	global $funktion;
	global $db;
	
	$sag_id=if_isset($_GET['sag_id']);
	$sag_fase=if_isset($_GET['sag_fase']);
	$tjekpunkt_id=if_isset($_GET['tjek_id']);
	$tjekskema_id=if_isset($_GET['tjekskema_id']);
	$menu_certificering='id="menuActive"';
	
	
	// Visning af tjekskema, hvis tjekskema_id er sat
	$r=db_fetch_array(db_select("select * from tjekskema where sag_id='$sag_id' and tjekliste_id='$tjekpunkt_id' and id='$tjekskema_id'",__FILE__ . " linje " . __LINE__));
	$tjekskema_id=$r['id']*1;
	$tjekskema_tjekliste_id=$r['tjekliste_id'];
	$udf_dato=date("d-m-Y",$r['datotid']);
	$opg_art=htmlspecialchars($r['opg_art']);
	$opg_navn=htmlspecialchars($r['opg_navn']);
	$opg_beskrivelse=htmlspecialchars($r['opg_beskrivelse']);
	$sjak=htmlspecialchars($r['sjak']);
	$sjakid=$r['sjakid'];
	$hvem=htmlspecialchars($r['hvem']);
	$stillads_til=htmlspecialchars($r['stillads_til']);
	$man_trans=htmlspecialchars($r['man_trans']);
	($man_trans)?$man_trans=$man_trans:$man_trans="&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	
	// Denne funktion laver $sjakid, som indeholder ansatte id(er) om til navn og initialer
	if (isset($sjakid) && $sjakid!=NULL) {
		// Her skiller vi id'erne til et array
		$sjakider = explode(";", $sjakid);
		
		// Query der henter initialer og navn fra ansatte
		for ($x=0;$x<count($sjakider);$x++) {
			$r=db_fetch_array(db_select("select * from ansatte where id = '$sjakider[$x]'",__FILE__ . " linje " . __LINE__)); 
			$sjaknavn[$x]=$r['navn'];
			$sjakini[$x]=$r['initialer'];
			$sjaktitleny[$x]="(".$r['initialer'].")"." ".$r['navn']."\n";
		}
		// Her splejser vi henholdsvis navn og initialer til hver deres streng
		#$sjakinitialer = implode(", ", $sjakini).", ";
		$sjaktitle = implode("", $sjaktitleny);
	}
		
	// Visning af tjekliste
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt']; 
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=htmlspecialchars($r2['tjekpunkt']); 
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=htmlspecialchars($r3['tjekpunkt']); 
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	
	// Visning af sagsinformationer
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$sag_firmanavn=htmlspecialchars($r['firmanavn']);
	$sag_kontakt=htmlspecialchars($r['kontakt']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$sag_omfang=htmlspecialchars($r['omfang']);
	
	// Visning af tjeklistenavn
	$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and id='$tjekpunkt_id'",__FILE__ . " linje " . __LINE__)); 
	$tjekpunktnavn=$r['tjekpunkt'];
	
	// Visning af bilag, hvis tilknyttet #20170303
	$x=0;
	$q = db_select("SELECT bilag.id as bilagid,bilag_tjekskema.id as bilag_tjekskema_id,* FROM bilag 
									LEFT JOIN bilag_tjekskema ON bilag.id = bilag_tjekskema.bilag_id
									WHERE assign_to = 'sager' and assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bilag_id[$x]=$r['bilagid'];
		$bilag_title[$x]=$r['navn'];
		$tmp=mb_convert_encoding($r['navn'], 'ISO-8859-1', 'UTF-8');
		$bilag_navn[$x]=mb_convert_encoding($tmp, 'UTF-8', 'ISO-8859-1');
		$bilag_beskrivelse[$x]=$r['beskrivelse'];
		$bilag_dato[$x]=date("d-m-Y",$r['datotid']);
		$bilag_hvem[$x]=$r['hvem'];
		$bilag_filtype[$x]=$r['filtype'];
		$bilag_tjekskema_id[$x]=$r['bilag_tjekskema_id'];
		$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
		$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
		$x++;
	}
	
	print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
				if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				else print "<a href=\"\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				print "</li>
				<li><a href=\"certificering.php\" title=\"Tilbage til certificerings-menu\">Certificering</a></li>
				<li><a href=\"kontrolskemaer.php\" title=\"Til kontrolskema-liste\">Kontrolskemaer</a></li>
				<!--<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
				<li>Current page</li>-->
				<li title=\"Sag: $sagsnr - $udf_addr1, $udf_postnr $udf_bynavn\">$tjekpunktnavn</li>
				<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>
				<!--<li style=\"float:right;\"><a href=\"kontrol_sager.php?funktion=emailArbejdsseddel&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" title=\"Email skema\" style=\"background-image: none;\"><img src=\"../img/mail.png\" alt=\"Email skema\" class=\"printIcon\" /></a></li>-->
			</ul>

	</div><!-- end of breadcrumbbar -->

	<div id=\"leftmenuholder\">";
		include ("leftmenu.php");
	print "</div><!-- end of leftmenuholder -->";
		
	print "<div class=\"maincontent\">";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	print "<tr><td width=\"100%\" align=\"center\">\n";
	
	print "<div id=\"printableArea\">\n";
	
	($opg_navn)?$opg='til&nbsp;'.$opg_navn:$opg=NULL;
	print "<h3 class=\"printHeadLineSkema\">$tjekpunktnavn $opg</h3>\n";
	print "<table border=\"0\" cellspacing=\"0\" class=\"kontrolskema printKontrolskematxt\">\n";
	print "<colgroup>
    <col width=\"75\">
    <col width=\"75\">
		<col width=\"150\">
		<col width=\"100\">
    <col width=\"100\">
		<col width=\"95\">
  </colgroup>
  <tbody >
		<tr>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
		</tr>
  </tbody>\n";
  print "<tbody>\n";
  print "<tr><td colspan=\"4\"><p><b>Udføres dato:&nbsp;</b>$udf_dato</p></td>\n";// .date("d-m-Y",$udf_dato).
	print "<td colspan=\"2\"><p><b>Sagsnr:&nbsp;</b> $sagsnr</p></td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;#width:200px;\"><p><b>Opstillingsadresse:</b></p><p>$udf_addr1, $udf_postnr $udf_bynavn</p></td>\n";
	print "<td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Kunde:</b></p><p>$sag_firmanavn</p></td>\n";
	print "<td colspan=\"2\" style=\"vertical-align:top;\"><p><b>kontakt:</b></p><p>$sag_kontakt&nbsp;</p></td></tr>\n";
	print "<tr><td colspan=\"4\" style=\"vertical-align:top;\"><p><b>Sjak:</b></p>\n";
	print "<p title=\"$sjaktitle\">$sjak</p></td>\n";
	print "<td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Konduktør:</b></p><p>$hvem</p></td></tr>\n";
	if ($opg_beskrivelse) print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Opgave beskrivelse:</b></p></td><td colspan=\"4\"><p><i><b>$opg_navn:</b> $opg_beskrivelse&nbsp;</i></p></td></tr>\n";
	print "</tbody>\n";
  print "<tbody>\n";
  for ($x=1;$x<=count($id);$x++) {
		
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			
			print "<tr style=\"display:none;\"><td colspan=\"6\"><input type=\"hidden\" name=\"tjekantal\" value='".count($id)."'><input type=\"hidden\" name=\"id[$x]\" value='$id[$x]'></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) { 
		
			print "<tr><td colspan=\"2\" title=\"$assign_id[$x]==$l_id\" style=\"vertical-align:top;\"><input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'><p><b>".$tjekpunkt[$x].":</b></p></td><td colspan=\"4\" class=\"printtxt\">\n"; #<td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
		}
		
		// Kontrolskema vises hvis der er id
		if ($punkt_id[$x] && $tjekskema_id) { 
		
			$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
			$tjekpunkter_id=$r['id'];
			$status=$r['status'];
			
			if ($status == 1) {
				$status="checked='checked'";
				$statuscolor="color:#cd3300; !important";
			} else {
				$status=NULL;
				$statuscolor=NULL;
			}
			
			print "<span style=\"float:left;min-width:91px;font-size: 12px;line-height: 18px;margin:0 6px 0 0;#background-color:lightblue;\" class=\"checkboxNed\">\n";
			print "<input style=\"float:left;margin-right:4px;background:transparent;\" class=\"inputbox\" type=\"checkbox\" name=\"kontrolpunkt[$x]\" value=\"1\" $status onclick=\"if(this.checked == true) this.checked=false; else this.checked=true\">\n";
			print "<span style=\"$statuscolor\">".$tjekpunkt[$x]."</span>\n";
			print "<input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'><input type=\"hidden\" name=\"tjekpunkter_id[$x]\" value='$tjekpunkter_id'>\n";
			print "</span>\n";
		}
  }
  print "</td></tr>\n";
  print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Manuel Transport:</b></p></td><td colspan=\"4\"><p><b>$man_trans</b> Gange (hvis mere end forventet skal der ringes til ansvarlig konduktør)</p></td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Stillads til:<br>(Evt. Tegning)</b></p></td><td colspan=\"4\"><textarea class=\"textAreaSager autosize kontrolskema_tegning\" style=\"border:none;background:transparent;\" name=\"stillads_til\" rows=\"1\" cols=\"37\" disabled>$stillads_til</textarea></td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Generalt for sagen:</b></p></td><td colspan=\"4\" style=\"#color:#cd3300 !important;\"><p><i><b>$sag_omfang</b></i></p></td></tr>\n";
	print "<tr><td colspan=\"2\" style=\"vertical-align:top;\"><p><b>Husk hver dag at:</b></p></td><td colspan=\"4\" style=\"text-align:center;color:#cd3300 !important;\"><p><b>Kontrollere bilen for fejl/mangler.</b></p><p><b>Kontrollere eget udstyr og værktøj.</b></p></td></tr>\n";
	print "</tbody>\n";
	print "</table>\n";
  print "<div id=\"printableFooter\">\n";
	// Footer skal laves unik til de enkelte stilladsvirksomheder!!!
	if(file_exists("../includes/footer_$regnskab.php")){
		include("../includes/footer_$regnskab.php");
	}
	print "</div><!-- end of printableFooter -->\n";
	print "</div><!-- end of printableArea -->\n";
	
	if ($bilag_id) { #20170303
		print "<br>";
		print "<h3>Bilag:</h3>\n";
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableBilag\">\n";
		print "<tbody class=\"tableBilagZebra tableBilagBorderTop tableBilagBorderBottom\">\n";
		for ($y=0;$y<count($bilag_id);$y++) {
			print "<tr><td><p>$bilag_beskrivelse[$y]</p></td><td align=\"right\"><p><a href=\"../bilag/$db/$sag_id/$bilag_id[$y].$bilag_filtype[$y]\" target=\"blank\" class=\"button blue small\">Vis</a></p></td></tr>\n";
		}
		print "</tbody>\n";
		print "</table>\n";
	}
	
  print "</td></tr>\n";
	print "</tbody></table>\n";
}

function vis_kontrolskema() {

	global $sag_rettigheder;
	global $funktion;
	global $db;
	
	$sag_id=if_isset($_GET['sag_id']);
	$sag_fase=if_isset($_GET['sag_fase']);
	$tjekpunkt_id=if_isset($_GET['tjek_id']);
	$tjekskema_id=if_isset($_GET['tjekskema_id']);
	$menu_certificering='id="menuActive"';
	
	// Visning af tjekskema
	$r=db_fetch_array(db_select("select * from tjekskema where sag_id='$sag_id' and tjekliste_id='$tjekpunkt_id' and id='$tjekskema_id'",__FILE__ . " linje " . __LINE__));
	$tjekskema_id=$r['id']*1;
	$tjekskema_tjekliste_id=$r['tjekliste_id'];
	$datotid=$r['datotid'];
	$opg_art=htmlspecialchars($r['opg_art']);
	$opg_navn=htmlspecialchars($r['opg_navn']);
	$opg_beskrivelse=htmlspecialchars($r['opg_beskrivelse']);
	$sjak=htmlspecialchars($r['sjak']);
	$sjakid=$r['sjakid'];
	$hvem=htmlspecialchars($r['hvem']);
	
	// Denne funktion laver $sjakid, som indeholder ansatte id(er) om til navn og initialer
	if (isset($sjakid) && $sjakid!=NULL) {
		// Her skiller vi id'erne til et array
		$sjakider = explode(";", $sjakid);
		
		// Query der henter initialer og navn fra ansatte
		for ($x=0;$x<count($sjakider);$x++) {
			$r=db_fetch_array(db_select("select * from ansatte where id = '$sjakider[$x]'",__FILE__ . " linje " . __LINE__)); 
			$sjaknavn[$x]=$r['navn'];
			$sjakini[$x]=$r['initialer'];
			$sjaktitleny[$x]="(".$r['initialer'].")"." ".$r['navn']."\n";
		}
		// Her splejser vi henholdsvis navn og initialer til hver deres streng
		#$sjakinitialer = implode(", ", $sjakini).", ";
		$sjaktitle = implode("", $sjaktitleny);
	}
	
	// Visning af tjekliste
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and fase = '$sag_fase'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$id[$x]=$r['id'];
		$tjekpunkt[$x]=$r['tjekpunkt']; 
		$fase[$x]=$r['fase']*1;
		$assign_id[$x]=$r['assign_id']*1;
		$punkt_id[$x]=0;
		$gruppe_id[$x]=0;
		$liste_id[$x]=$id[$x];
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
		while ($r2 = db_fetch_array($q2)) {
			$x++;
			$max_gruppe=$x;
			$id[$x]=$r2['id'];
			$tjekpunkt[$x]=$r2['tjekpunkt']; 
			$assign_id[$x]=$r2['assign_id']*1;
			$fase[$x]=$fase[$x-1];
			$punkt_id[$x]=0;
			$gruppe_id[$x]=$id[$x];
			$liste_id[$x]=$liste_id[$x-1];
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by id",__FILE__ . " linje " . __LINE__);
			while ($r3 = db_fetch_array($q3)) {
				$x++;
				$id[$x]=$r3['id'];
				$tjekpunkt[$x]=$r3['tjekpunkt']; 
				$assign_id[$x]=$r3['assign_id']*1;
				$fase[$x]=$fase[$x-1];
				$punkt_id[$x]=$id[$x];
				$gruppe_id[$x]=$gruppe_id[$x-1];
				$liste_id[$x]=$liste_id[$x-1];
			}
		}
	}
	
	// Visning af sagsinformationer
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__)); 
	$sagsnr=$r['sagsnr'];
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	
	// Visning af tjeklistenavn
	$r=db_fetch_array(db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' and id='$tjekpunkt_id'",__FILE__ . " linje " . __LINE__)); 
	$tjekpunktnavn=$r['tjekpunkt'];
	
	// Visning af bilag, hvis tilknyttet #20170303
	$x=0;
	$q = db_select("SELECT bilag.id as bilagid,bilag_tjekskema.id as bilag_tjekskema_id,* FROM bilag 
									LEFT JOIN bilag_tjekskema ON bilag.id = bilag_tjekskema.bilag_id
									WHERE assign_to = 'sager' and assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$bilag_id[$x]=$r['bilagid'];
		$bilag_title[$x]=$r['navn'];
		$tmp=mb_convert_encoding($r['navn'], 'ISO-8859-1', 'UTF-8');
		$bilag_navn[$x]=mb_convert_encoding($tmp, 'UTF-8', 'ISO-8859-1');
		$bilag_beskrivelse[$x]=$r['beskrivelse'];
		$bilag_dato[$x]=date("d-m-Y",$r['datotid']);
		$bilag_hvem[$x]=$r['hvem'];
		$bilag_filtype[$x]=$r['filtype'];
		$bilag_tjekskema_id[$x]=$r['bilag_tjekskema_id'];
		$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
		$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
		$x++;
	}
	
	print "<div id=\"breadcrumbbar\">
			<ul id=\"breadcrumb\">
				<li>";
				if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				else print "<a href=\"\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
				print "</li>
				<li><a href=\"certificering.php\" title=\"Tilbage til certificerings-menu\">Certificering</a></li>
				<li><a href=\"kontrolskemaer.php\" title=\"Til kontrolskema-liste\">Kontrolskemaer</a></li>
				<!--<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
				<li>Current page</li>-->
				<li title=\"Sag: $sagsnr - $udf_addr1, $udf_postnr $udf_bynavn\">$tjekpunktnavn</li>
				<li style=\"float:right;\"><a href=\"#\" title=\"Print skema\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print skema\" class=\"printIcon\" /></a></li>
				<!--<li style=\"float:right;\"><a href=\"kontrol_sager.php?funktion=emailKontrolskema&amp;sag_id=$sag_id&amp;sag_fase=$sag_fase&amp;tjek_id=$tjekpunkt_id&amp;tjekskema_id=$tjekskema_id\" title=\"Email skema\" style=\"background-image: none;\"><img src=\"../img/mail.png\" alt=\"Email skema\" class=\"printIcon\" /></a></li>-->
			</ul>

	</div><!-- end of breadcrumbbar -->

	<div id=\"leftmenuholder\">";
		include ("leftmenu.php");
	print "</div><!-- end of leftmenuholder -->";
		
	print "<div class=\"maincontent\">";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	print "<tr><td width=\"100%\" align=\"center\">\n";
	
	print "<div id=\"printableArea\">\n";
	
	($opg_navn)?$opg='til&nbsp;'.$opg_navn:$opg=NULL;
	print "<h3 class=\"printHeadLineSkema\">$tjekpunktnavn $opg</h3>\n";
	print "<table border=\"0\" cellspacing=\"0\" class=\"kontrolskema\" style=\"table-layout:fixed;\">\n";
	print "<colgroup>
    <col width=\"100\">
    <col width=\"125\">
    <col width=\"70\">
		<col width=\"80\">
		<col width=\"115\">
    <col width=\"105\">
  </colgroup>
  <tbody >
		<tr>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
			<td style=\"height:0px;padding:0px;margin:0px;border:none;\"></td>
		</tr>
  </tbody>\n";
  print "<tbody>\n";
	print "<tr><td colspan=\"2\" class=\"printtxt\"><p><b>Opstillingsadresse:</b></p><p>$udf_addr1, $udf_postnr $udf_bynavn</p></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\"><p><b>Status:</b></p></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\" class=\"printdate\"><p><b>Dato:</b></p><p>".date("d-m-Y",$datotid)."</p></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\"><p><b>Opgavens art:</b></p><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"opg_art\" rows=\"4\" cols=\"12\" style=\"height:64px;width:95px;border:none;background:transparent;\" disabled>".htmlspecialchars($opg_art)."</textarea></td>\n";
	print "<td rowspan=\"2\" align=\"center\" valign=\"top\"><p><b>Sjak:</b></p><textarea class=\"textAreaSager autosize kontrolskema_font\" name=\"sjak\" rows=\"4\" cols=\"10\" title=\"$sjaktitle\" style=\"height:64px;width:85px;border:none;background:transparent;\" disabled>".htmlspecialchars($sjak)."</textarea></td></tr>\n";
	print "<tr><td colspan=\"2\" class=\"printtxt\"><input type=\"hidden\" name=\"hvem\" value='$ansat_navn'><p><b>Kontroleret af:</b></p><p>".htmlspecialchars($hvem)."</p></td></tr>\n";
	if ($opg_beskrivelse) print "<tr><td colspan=\"2\" class=\"printtxt\" style=\"vertical-align:top;\"><p><b>Opgave beskrivelse:</b></p></td><td colspan=\"4\" class=\"printtxt\"><p><i><b>$opg_navn:</b> $opg_beskrivelse&nbsp;</i></p></td></tr>\n";
	// Array til status select-box i kontrolskema
	$value = array(0,1,2,3,4);
	$color = array("white","green","yellow","red","white");
	$option_name = array("&nbsp;","OK","Fejl","Kritisk","N/A");
	
	for ($x=1;$x<=count($id);$x++) {
		
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			
			print "<tr style=\"display:none;\"><td colspan=\"6\"><input type=\"hidden\" name=\"tjekantal\" value='".count($id)."'><input type=\"hidden\" name=\"id[$x]\" value='$id[$x]'></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) { 
		
			print "<tr><td class=\"printtxt\" colspan=\"6\" title=\"$assign_id[$x]==$l_id\"><input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'><p><b>".$tjekpunkt[$x]."</b></p></td></tr>\n"; #<td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
		}
		// Kontrolskema vises hvis der er id
		if ($punkt_id[$x] && $tjekskema_id) { 
		
			$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
			$tjekpunkter_id=$r['id'];
			$status=$r['status'];
			$status_tekst=$r['status_tekst'];
		//echo "id: $tjekpunkter_id";
			/*
			$x=0;
			$q=db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__);
			while($r=db_fetch_array($q)) {
				//$tjekpunkter_id[$x]=$r['id'];
				$x++;	
			}*/
			//if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
		
			//(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;
			$statcolor = NULL;
				if ($status<=0) $statcolor = "background-color:white;";
				if ($status==1) $statcolor = "background-color:green;";
				if ($status==2) $statcolor = "background-color:yellow;";
				if ($status==3) $statcolor = "background-color:red;";
				if ($status==4) $statcolor = "background-color:white;";
			
			print "<tr><td colspan=\"2\" class=\"printtxt\" title=\"$assign_id[$x]==$l_id\"><p>".$tjekpunkt[$x]."</p></td>\n";
			print "<td class=\"kontrol_color\" style=\"$statcolor\"><input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'><input type=\"hidden\" name=\"tjekpunkter_id[$x]\" value='$tjekpunkter_id'>\n";
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" style=\"color:black;\" disabled>\n";
			for($y=0;$y<count($value);$y++) {
				if ($status==$value[$y]) print "<option class=\"kontrol_status_option\" value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			for($y=0;$y<count($value);$y++) {
				if ($status!=$value[$y]) print "<option class=\"kontrol_status_option\" value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			print "</select>\n";
			//print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			//print "<input class=\"inputbox\" $disabled type=\"checkbox\" name=\"kontrolpunkt[$x]\" $tmp>";
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >
				<option value=\"0\" style=\"background-color:white;\">&nbsp;</option>
				<option value=\"1\" style=\"background-color:green;\">green</option>
				<option value=\"2\" style=\"background-color:yellow;\">yellow</option>
				<option value=\"3\" style=\"background-color:red;\">red</option>
			</select>\n";
			*/
			
			print "</td>\n";
			
			print "<td colspan=\"3\" class=\"printtxt kontrolskema_font\">".htmlspecialchars($status_tekst)."</td></tr>\n";
		}	
		// Nyt kontrolskema vises her
		if ($punkt_id[$x] && !$tjekskema_id) { 
		/*
			$r=db_fetch_array(db_select("select * from tjekpunkter where assign_id = '$sag_id' and tjekskema_id = '$tjekskema_id' and tjekliste_id = '$id[$x]'",__FILE__ . " linje " . __LINE__)); 
			$status=$r['status'];
			$status_tekst=$r['status_tekst'];
		*/
			//if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
		
			//(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;

			print "<tr><td colspan=\"2\" class=\"printtxt\" title=\"$assign_id[$x]==$l_id\"><p>".$tjekpunkt[$x]."</p></td>\n";
			print "<td align=\"center\" class=\"kontrol_color\"><input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'><input type=\"hidden\" name=\"tjekskema_id\" value='$tjekskema_id'>\n";
			/*
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >\n";
			for($y=0;$y<count($value);$y++) {
				if ($status==$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			for($y=0;$y<count($value);$y++) {
				if ($status!=$value[$y]) print "<option value=\"$value[$y]\" style=\"background-color:$color[$y];\">$option_name[$y]</option>\n";
			}
			print "</select>\n";
			*/
			//print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			//print "<input class=\"inputbox\" $disabled type=\"checkbox\" name=\"kontrolpunkt[$x]\" $tmp>";
			
			print "<select name=\"kontrolpunkt[$x]\" class=\"kontrol_status\" >
				<option value=\"0\" style=\"background-color:white;\">&nbsp;</option>
				<option value=\"1\" style=\"background-color:green;\">OK</option>
				<option value=\"2\" style=\"background-color:yellow;\">Fejl</option>
				<option value=\"3\" style=\"background-color:red;\">Kritisk</option>
			</select>\n";
			
			
			print "</td>\n";
			
			print "<td colspan=\"3\"><textarea class=\"textAreaSager autosize kontrolskemaText\" name=\"status_tekst[$x]\" rows=\"1\" cols=\"37\">".htmlspecialchars($status_tekst)."</textarea></td></tr>\n";
		}
	}	
	
	print "</tbody>\n";
	print "</table>\n";
	print "</div><!-- end of printableArea -->\n";
	
	if ($bilag_id) { #20170303
		print "<br>";
		print "<h3>Bilag:</h3>\n";
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableBilag\">\n";
		print "<tbody class=\"tableBilagZebra tableBilagBorderTop tableBilagBorderBottom\">\n";
		for ($y=0;$y<count($bilag_id);$y++) {
			print "<tr><td><p>$bilag_beskrivelse[$y]</p></td><td align=\"right\"><p><a href=\"../bilag/$db/$sag_id/$bilag_id[$y].$bilag_filtype[$y]\" target=\"blank\" class=\"button blue small\">Vis</a></p></td></tr>\n";
		}
		print "</tbody>\n";
		print "</table>\n";
	}
	
	print "</td></tr>\n";
	print "</tbody></table>\n";
}

?>

