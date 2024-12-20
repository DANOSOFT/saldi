<?php
@session_start();	# Skal angives oeverst i filen??!!
$s_id=session_id();
// ------ SAGER/ANSATTE.php-------lap 3.3.0 ------2013-02-01------14:22---------
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
 
// Nyt flueben - ny i branchen. reduceret løn 80% i x mdr.½
// Har lagt al javascript i en separat fil ved navn 'jquery.ansatte.js' + diverse html rettelser
// Flere html rettelser + html rettelser i "/systemdata/ansatte_load.php" 
// 20140923 PK - Indsat medarbejdernummer i ansatliste.
// 20140923 PK - Laver et medarbejdernummer ved at finde det højeste nummer og lægge 1 til. Søg 20140923
// 20140924 PK - Tilrettet breadcrumb 
// 20151007 PK - Der er sat session på alle og aftrådte i leftmenu, så den husker søgning. Søg alleA eller tiltraadteA
// 20170911 PK - Har sat validering på brugernavn så der ikke kommer duplikater. Søg 20170911 

	//ini_set("display_errors", "1");
	$bg="nix";
	$header='nix';

	$menu_sager=NULL;
	$menu_planlaeg=NULL;
	$menu_dagbog=NULL;
	$menu_kunder=NULL;
	$menu_loen=NULL;
	$menu_ansatte='id="menuActive"';
	$menu_certificering=NULL;
	$menu_medarbejdermappe=NULL;

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");

	if(isset($_GET['id'])) $id = $_GET['id'];
	if(isset($_POST['id'])) $id = $_POST['id'];
	$konto_id=if_isset($_GET['konto_id']);
	if (!$konto_id) $konto_id = 0; 
	$funktion=if_isset($_GET['funktion']);
	if (!$funktion)$funktion="ansatliste";
	$vis=if_isset($_GET['vis']);
	
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html lang=\"da\">
	<head>
		<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/search.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/form.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/pajinate.css\">
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/hyphenator.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.pajinate.js\"></script>
		
		<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->
		<title>Stillads</title>
	</head>
	<body>
		<div id=\"wrapper\">";  
			include ("../includes/sagsmenu.php");
			print "<div id=\"breadcrumbbar\">

				<ul id=\"breadcrumb\">
					<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>\n";
					if ($funktion=="ret_ansat" && !$id) {
						print "<li><a href=\"ansatte.php?funktion=ansatliste\" title=\"Til ansatteliste\">Ansatte</a></li>\n";
						print "<li>Opret medarbejder</li>";
					} elseif ($funktion=="ret_ansat" && $id) {
						$r=db_fetch_array(db_select("select navn from ansatte where id='$id'",__FILE__ . " linje " . __LINE__));
						$navn=$r['navn'];
						if ($navn) {
							print "<li><a href=\"ansatte.php?funktion=ansatliste\" title=\"Til ansatteliste\">Ansatte</a></li>\n";
							print "<li>$navn</li>";
						} else {
							print "<li><a href=\"ansatte.php?funktion=ansatliste\" title=\"Til ansatteliste\">Ansatte</a></li>\n";
							print "<li>Ingen navn</li>";
						}
					} elseif ($funktion=="brugergrupper") {
						print "<li><a href=\"ansatte.php?funktion=ansatliste\" title=\"Til ansatteliste\">Ansatte</a></li>\n";
						print "<li>Brugergrupper</li>";
					} elseif ($funktion=="stamkort") {
						print "<li><a href=\"ansatte.php?funktion=ansatliste\" title=\"Til ansatteliste\">Ansatte</a></li>\n";
						print "<li>Stamkort</li>";
					} else {
					print "
					<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>
					<li><a href=\"#\" title=\"Sample page 2\">Sample page 2</a></li>
					<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
					<li>Current page</li>-->
					<li>Ansatte</li>\n";
					}
					print "
				</ul>

			</div><!-- end of breadcrumbbar -->

			<div id=\"leftmenuholder\">";
				include ("leftmenu.php");
			print "</div><!-- end of leftmenuholder -->
			<div class=\"maincontent\">\n";
			$funktion($konto_id);
			print "</div><!-- end of maincontent -->
			</div><!-- end of wrapper -->  
		<!-- <div id=\"footer\"><p>Pluder | Pluder</p></div> -->
		<script type=\"text/javascript\" src=\"../javascript/jquery.ansatte.js\"></script>
	</body>
	</html>";

function ansatliste() {

	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('initialer','nummer','navn','addr1','postnr','bynavn','email','mobil');
	$vis=if_isset($_GET['vis']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	
	if ($vis=='alleA') $alleA=$vis;
	elseif ($vis=='fratraadteA') $fratraadteA=$vis;
	
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
		$_SESSION['ansatte_initialer']=$sortstyle[0];
		$_SESSION['ansatte_nummer']=$sortstyle[1];
		$_SESSION['ansatte_navn']=$sortstyle[2];
		$_SESSION['ansatte_addr1']=$sortstyle[3];
		$_SESSION['ansatte_postnr']=$sortstyle[4];
		$_SESSION['ansatte_bynavn']=$sortstyle[5];
		$_SESSION['ansatte_email']=$sortstyle[6];
		$_SESSION['ansatte_mobil']=$sortstyle[7];
	} else {
		$sortstyle[0]=$_SESSION['ansatte_initialer'];
		$sortstyle[1]=$_SESSION['ansatte_nummer'];
		$sortstyle[2]=$_SESSION['ansatte_navn'];
		$sortstyle[3]=$_SESSION['ansatte_addr1'];
		$sortstyle[4]=$_SESSION['ansatte_postnr'];
		$sortstyle[5]=$_SESSION['ansatte_bynavn'];
		$sortstyle[6]=$_SESSION['ansatte_email'];
		$sortstyle[7]=$_SESSION['ansatte_mobil'];
	}
	
	if ($_GET['vis']) {
		$_SESSION['alleA']=$alleA;
		$_SESSION['fratraadteA']=$fratraadteA;
	} else {
		$alleA=$_SESSION['alleA'];
		$fratraadteA=$_SESSION['fratraadteA'];
	}
	
	if ($unsetsort) {
		unset($_SESSION['ansatte_sort'],
					$_SESSION['ansatte_initialer'],$sortstyle[0],
					$_SESSION['ansatte_nummer'],$sortstyle[1],
					$_SESSION['ansatte_navn'],$sortstyle[2],
					$_SESSION['ansatte_addr1'],$sortstyle[3],
					$_SESSION['ansatte_postnr'],$sortstyle[4],
					$_SESSION['ansatte_bynavn'],$sortstyle[5],
					$_SESSION['ansatte_email'],$sortstyle[6],
					$_SESSION['ansatte_mobil'],$sortstyle[7],
					$_SESSION['alleA'],$alleA,
					$_SESSION['fratraadteA'],$fratraadteA
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['ansatte_sort']=$sort;
	else $sort=$_SESSION['ansatte_sort'];
	if (!$sort) $sort="navn";
	
	$sqlsort=urldecode($sort);
	
	if ($alleA) $where="where adresser.art='S' and ansatte.konto_id = adresser.id";
	elseif ($fratraadteA) $where="where adresser.art='S' and ansatte.konto_id = adresser.id and ansatte.slutdate !='9999-12-31'";
	else $where="where adresser.art='S' and ansatte.konto_id = adresser.id and ansatte.slutdate ='9999-12-31'";
	
	$x=0;
	//$sort=if_isset($_GET['sort']);
	//if (!$sort) $sort='navn';
	//$dd=date("Y-m-d");
#cho "select ansatte.* from ansatte,adresser where adresser.art='S' and ansatte.konto_id = adresser.id order by $sort<br>";
	$q=db_select("select ansatte.* from ansatte,adresser $where order by $sqlsort",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$konto_id[$x]=$r['id'];
		$adresse_id=$r['konto_id'];
		$initialer[$x]=htmlspecialchars($r['initialer']);
		$navn[$x]=htmlspecialchars($r['navn']);
		$addr1[$x]=htmlspecialchars($r['addr1']);
		$addr2[$x]=htmlspecialchars($r['addr2']);
		$postnr[$x]=$r['postnr'];
		$bynavn[$x]=htmlspecialchars($r['bynavn']);#
		$email[$x]=htmlspecialchars($r['email']);
		$telefon[$x]=$r['mobil'];
		$nummer[$x]=$r['nummer'];
	}
	$antal_adresser=$x;

	print "
		<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"ansatte.php?funktion=ret_ansat\" method=\"post\">
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
				<thead>
					<tr>
						<th width=\"100\">Med. nr</th>
						<th width=\"250\">Navn</th>
						<th width=\"360\">Adresse</th>
						<th colspan=\"2\">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						
						<td><input class=\"textinput medarbejdernr2\" type=\"text\" value=\"\" id=\"medarbejdernr2\" name=\"medarbejdernr2\" tabindex=\"1\"/></td>
						<td><input class=\"textinput medarbejdernavn2\" type=\"text\" value=\"\" id=\"medarbejdernavn2\" name=\"medarbejdernavn2\" tabindex=\"2\"/></td>
						<td><input class=\"textinput medarbejderadresse\" type=\"text\" value=\"\" id=\"medarbejderadresse\" name=\"medarbejderadresse\" tabindex=\"3\"/></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"id\"></td>   
						<td align=\"center\"><input type=\"submit\" value=\"Find ansat\" name=\"findansat\" class=\"button gray small\" tabindex=\"4\"></td>
						
					</tr>
				</tbody>
			</table>
		</form>
		<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
				<span style=\"float:left;\"><a href=\"ansatte.php?funktion=ansatliste&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
		</div>
		</div><!-- end of contentsoeg -->\n";
		
		($antal_adresser<=25)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.ansatte.js, under pagination
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
		print "<div class=\"contentkundehead\">
			<ul id=\"sort\">
				<li>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=initialer&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:48px\">Initial</a>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=nummer&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:54px\">MA-nr.</a>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=navn&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:165px\">Navn</a>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=addr1&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:170px\">Adresse</a>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=postnr&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:60px\">Postnr</a>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=bynavn&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[5]\" class=\"felt06 $sortstyle[5]\" style=\"width:105px\">By</a>       
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=email&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[6]\" class=\"felt07 $sortstyle[6]\" style=\"width:120px\">Email</a>
					<a href=\"ansatte.php?funktion=ansatliste&amp;nysort=mobil&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[7]\" class=\"felt08 $sortstyle[7]\" style=\"width:75px\">Telefon</a>
				</li>
			</ul>
		</div>
	
		<div class=\"contentkunde contentansatte\"> 
			<ul id=\"things\" class=\"paging_content\">";
				for ($x=1;$x<=$antal_adresser;$x++) {
					print "<li><a href=\"ansatte.php?funktion=ret_ansat&amp;konto_id=$adresse_id[$x]&amp;id=$konto_id[$x]\">
						<span class=\"felt01\" style=\"width:48px\">$initialer[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:54px\">$nummer[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:165px\" title=\"$navn[$x]\">$navn[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:170px\" title=\"$addr1[$x]\">$addr1[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:60px\">$postnr[$x]&nbsp;</span>
						<span class=\"felt06\" style=\"width:105px\" title=\"$bynavn[$x]\">$bynavn[$x]&nbsp;</span>	           
						<span class=\"felt07\" style=\"width:120px\" title=\"$email[$x]\">$email[$x]&nbsp;</span>
						<span class=\"felt08\" style=\"width:75px\">$telefon[$x]&nbsp;</span>
					</a></li>";
				}
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of paging_container -->";
}
function ret_ansat($id) {

	global $charset;
	global $sprog_id;
	
	if(isset($_GET['id'])) $id = $_GET['id'];
	if(isset($_POST['id'])) $id = $_POST['id'];
	if(isset($_GET['konto_id'])) $konto_id = $_GET['konto_id'];
	//if (!$id) { header("location:ansatte.php?funktion=ansatliste"); exit(); }
	//echo "id: $id konto_id: $konto_id";
	
	print "<div class=\"content\">\n";
	include "../systemdata/ansatte_save.php";
	
	if (!$id) { #20140923
		$r=db_fetch_array(db_select("select max(nummer) as nummer from ansatte",__FILE__ . " linje " . __LINE__));
		$nummer=$r['nummer']+1; 
	}
	if(isset($_POST['brugergruppe']) && $id) {	
		$brugergruppe = $_POST['brugergruppe']*1;
		if ($id && $brugergruppe) {
			db_modify("update ansatte set gruppe = '$brugergruppe' where id = '$id'",__FILE__ . " linje " . __LINE__);
		}
	}
	if (isset($_POST['brugere_navn']) && $id) {	
		$brugere_navn = $_POST['brugere_navn'];
		$brugere_id=if_isset($_POST['brugere_id']);
		$kode1=if_isset($_POST['kode1']);
		$kode2=if_isset($_POST['kode2']);
		
		
		if ($brugere_navn && !$brugere_id) { #20170911
			$query = db_select("select id from brugere where brugernavn = '$brugere_navn'",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				$alerttext="Der findes allerede en bruger med brugernavn: $brugere_navn";
				print "<BODY onLoad=\"javascript:alert('$alerttext');location.hash='#anch';\">";
			} 
		}
		
		$ret_kode=1;
		if (!$kode1 || !$kode2) {
			$ret_kode=0;
			print "<BODY onLoad=\"javascript:alert('Kodeord skal angives!');location.hash='#anch';\">";
		} elseif ($kode1 == '********') $ret_kode=0;
		elseif ($kode1 != $kode2) {
			$ret_kode=0;
			print "<BODY onLoad=\"javascript:alert('Kodeord ikke ens!');location.hash='#anch';\">";
		} else $kode1=md5($kode1);
		
		if ($id) {
			if ($ret_kode && $brugere_id) {
				db_modify("update brugere set kode = '$kode1' where id = '$brugere_id'",__FILE__ . " linje " . __LINE__);
			}
			if ($brugere_navn && !$brugere_id) {
			$r=db_fetch_array(db_select("select id,ansat_id from brugere where lower (brugernavn)='".db_escape_string(strtolower($brugere_navn))."'",__FILE__ . " linje " . __LINE__));
				if ($r['id'] && !$r['ansat_id']) {
					$brugere_id=$r['id'];
					if ($brugere_id) db_modify("update brugere set ansat_id = '$id' where id = '$brugere_id'",__FILE__ . " linje " . __LINE__);
				} else {
					db_modify("insert into brugere (brugernavn,kode,ansat_id) values ('$brugere_navn','$kode1','$id')",__FILE__ . " linje " . __LINE__);
				}
			} elseif ($brugere_navn && $brugere_id) { #20170911
				$r2=db_fetch_array(db_select("select id,brugernavn from brugere where id = '$brugere_id'",__FILE__ . " linje " . __LINE__));
				if ($r2['brugernavn']!=$brugere_navn) {
					//echo "$r2[brugernavn] != $brugere_navn<br>";
					$r3=db_fetch_array(db_select("select id from brugere where brugernavn = '$brugere_navn'",__FILE__ . " linje " . __LINE__));
					if ($r3['id']>0 && $r3['id']!=$brugere_id) {
						//echo "$r3[id] != $brugere_id<br>
						$alerttext="Der findes allerede en bruger med brugernavn: $brugere_navn";
						print "<BODY onLoad=\"javascript:alert('$alerttext');location.hash='#anch';\">";
					} else {
						db_modify("update brugere set brugernavn = '$brugere_navn' where id = '$brugere_id'",__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
	}
	print "<form name=\"ret_ansat\" action=\"ansatte.php?funktion=ret_ansat\" method=\"post\">\n"; // #anchor $anchor
	$x=0;
	$q=db_select("select * from grupper where art='brgrp' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$gruppe_id[$x]=$r['id'];
		$gruppe_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$x++;
	}
	include "../systemdata/ansatte_load.php";
	#print "<table border=\"0\"><tbody>";
	include "ansatte_body.php";
	#print "<div class=\"clear\"><div>\n";
	#print "<hr>\n";
	print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
	print "<h3 id=\"anch\">Login &amp; brugergruppe</h3>\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">Brugernavn</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"brugere_navn\" value=\"$brugere_navn\"></div><div class=\"clear\"></div></div><!-- end of row -->\n"; 
	print "<div class=\"row\"><div class=\"left\">Brugergruppe</div><div class=\"right\"><select name=\"brugergruppe\" style=\"width:194px;\">\n";
	for ($x=0;$x<=count($gruppe_id);$x++) {
		if ($gruppe==$gruppe_id[$x]) print "<option value=\"$gruppe_id[$x]\">$gruppe_beskrivelse[$x]&nbsp;</option>\n"; 
	}
	for ($x=0;$x<=count($gruppe_id);$x++) {
		if ($gruppe!=$gruppe_id[$x]) print "<option value=\"$gruppe_id[$x]\">$gruppe_beskrivelse[$x]&nbsp;</option>\n"; 
	}
	print "</select></div><div class=\"clear\"></div></div><!-- end of row -->\n"; 
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
	
	print "<div style=\"float:left; width:379px;\">\n";
	print "<h3>&nbsp;</h3>\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">Adgangskode</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"kode1\" value=\"********\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Gentag kode</div><div class=\"right\"><input class=\"text textIndent\" type=\"text\" name=\"kode2\" value=\"********\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
	
	print "<input type=\"hidden\" name=\"extra_id_0\" value=\"$extra_id_0\">\n";
	print "<input type=\"hidden\" name=\"extra_id_1\" value=\"$extra_id_1\">\n";
	print "<input type=\"hidden\" name=\"konto_id\" value='$konto_id'>\n";
	print "<input type=\"hidden\" name=\"brugere_id\" value='$brugere_id'>\n";
	print "<input type=\"hidden\" name=\"id\" value='$id'>\n";
	print "<div style=\"float:left; width:828px;\">";
	print "<div class=\"contentA\" style=\"width:808px; text-align:center;\">\n";
	print "<input type=\"submit\" accesskey=\"g\" class=\"button gray medium\" value=\"Gem / opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\">\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of full container -->\n";
	print "</form>\n";
	print "</div><!-- end of content -->\n";
}

function brugergrupper($id) {

	global $charset;
	global $sprog_id;

	if(isset($_GET['id'])) $id = $_GET['id'];
	if(isset($_GET['konto_id'])) $konto_id = $_GET['konto_id'];

	if (isset($_POST['gruppe_id']) && $gruppe_id=$_POST['gruppe_id']) {
		$beskrivelse=$_POST['beskrivelse'];
		$login=$_POST['login'];
		$opret_grp=$_POST['opret_grp'];
		$opret_sag=$_POST['opret_sag'];
		$opret_ansat=$_POST['opret_ansat'];
		$opret_kunde=$_POST['opret_kunde'];
		$opret_notat=$_POST['opret_notat'];
		$godk_akkord=$_POST['godk_akkord'];
		$regnskab=$_POST['regnskab'];
		for ($x=0;$x<count($gruppe_id);$x++) {
			$gruppe_id[$x]*=1;
			($login[$x])?$rettigheder[$x]='1':$rettigheder[$x]='0';
			($opret_grp[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			($opret_sag[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			($opret_ansat[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			($opret_kunde[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			($opret_notat[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			($godk_akkord[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			($regnskab[$x])?$rettigheder[$x].='1':$rettigheder[$x].='0';
			if ($beskrivelse[$x]) {
				db_modify("update grupper set beskrivelse='".db_escape_string($beskrivelse[$x])."',box2='$rettigheder[$x]' where id='$gruppe_id[$x]'",__FILE__ . " linje " . __LINE__);
			} else {
				if ($r=db_fetch_array(db_select("select id from ansatte where gruppe = '$gruppe_id[$x]'",__FILE__ . " linje " . __LINE__))) {
					print "<BODY onLoad=\"javascript:alert('Der er brugere tilknyttet denne gruppe - kan ikke slettes!')\">";
				} else {
					db_modify("delete from grupper where id = '$gruppe_id[$x]'",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	}
	if (isset($_POST['ny_brugergruppe']) && $ny_brugergruppe=db_escape_string($_POST['ny_brugergruppe'])) {
		$r=db_fetch_array(db_select("select max(kodenr) as kodenr from grupper where art='brgrp'",__FILE__ . " linje " . __LINE__));
		$kodenr=$r['kodenr']+1;
		db_modify("insert into grupper (beskrivelse,kodenr,art,box2) values ('".db_escape_string($ny_brugergruppe)."','$kodenr','brgrp','11111111')",__FILE__ . " linje " . __LINE__);
	}
	
	$x=0;
	$gruppe_id=array();
	$q=db_select("select * from grupper where art='brgrp' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$gruppe_id[$x]=$r['id'];
		$beskrivelse[$x]=$r['beskrivelse'];
		$rettigheder[$x]=$r['box2'];
		$x++;
		} if (!$x) {
		db_modify("insert into grupper (beskrivelse,kodenr,art,box2) values ('Administratorer','0','brgrp','11111111')",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/ansatte.php?funktion=brugergrupper\">\n";
	}
	print "<div class=\"content\">\n";
	print "<form name=\"brugergrupper\" action=\"ansatte.php?funktion=brugergrupper\" method=post>\n";
	
	print "<div style=\"float:left; width:828px;\">\n";
	print "<h3>Brugergrupper</h3>\n";
	
	for  ($x=0;$x<count($gruppe_id);$x++) {
	print "<div style=\"float:left; width:414px;\">\n";
	print "<div class=\"contentA\">\n";
		print "<div class=\"row\"><div class=\"leftLarge\">Gruppenavn</div><div class=\"rightMediumLarge\"><input type=\"text\" class=\"textMediumLarge textIndent\" name=\"beskrivelse[$x]\" value=\"$beskrivelse[$x]\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],0,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Login</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"login[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],1,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Opret/ret brugergrupper</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"opret_grp[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],2,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Opret/ret sag</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"opret_sag[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],3,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Opret/ret ansat</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"opret_ansat[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],4,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Opret/ret kunde</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"opret_kunde[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],5,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Opret/ret notat</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"opret_notat[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],6,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Godkend akkordseddel</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"godk_akkord[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		(substr($rettigheder[$x],7,1))?$checked="checked='checked'":$checked=NULL;
		print "<div class=\"row\"><div class=\"leftLarge\">Regnskab</div><div class=\"rightMediumLarge\"><input type=\"checkbox\" class=\"textSpace\" name=\"regnskab[$x]\" $checked></div><div class=\"clear\"></div></div><!-- end of row -->\n";
		print "<input type=\"hidden\" name=\"gruppe_id[$x]\" value='$gruppe_id[$x]'>";
		print "</div><!-- end of contentA -->\n";
		print "</div>\n";
	}
	
	print "</div><!-- end of full container -->\n";
	print "<div class=\"clear\"></div>\n";
	print "<hr>\n";
	print "<div style=\"float:left; width:828px;\">\n";
	print "<div class=\"contentA\">\n";
	print "<input type=hidden name=id value='$id'>";
	print "<div class=\"row\"><div class=\"leftLarge\">Ny gruppe</div><div class=\"right\"><input type=\"text\" class=\"textMediumLarge textIndent\" name=\"ny_brugergruppe\" value=\"\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of full container -->\n";
	print "<div class=\"clear\"></div>\n";
	print "<hr>\n";
	print "<div style=\"float:left; width:828px;\">\n";
	print "<div class=\"contentA\" style=\"text-align:center;\">\n";
	print "<input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" class=\"button gray medium\" name=\"submit\" onclick=\"javascript:docChange = false;\">";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of full container -->\n";
	print "</form>\n";
	print "</div>\n";
}

function stamkort($id) {

	global $charset;
	global $sprog_id;

	if(isset($_GET['id'])) $id = $_GET['id'];
	if(isset($_GET['konto_id'])) $konto_id = $_GET['konto_id'];
	
#echo "Grp ID ".$_POST['gruppe_id_0']."<br>";
	if (isset($_POST['gruppe_id_0']) && $gruppe_id_0=$_POST['gruppe_id_0']) {
		$gruppe_id_1=$_POST['gruppe_id_1'];
	#echo "Grp ID ".$_POST['gruppe_id_0']."<br>";
		$feltnavn=$_POST['feltnavn'];
		$felttype=$_POST['felttype'];
		$feltvalg=$_POST['feltvalg'];

		for ($x=1;$x<=28;$x++) {
			$tekstnr=616+$x;
#echo " $tekstnr ";
#echo findtekst($tekstnr,$sprog_id)."!=$feltnavn[$x]<br>";
			if (findtekst($tekstnr,$sprog_id)!=$feltnavn[$x]) {
				db_modify("update tekster set tekst='$feltnavn[$x]' where tekst_id='$tekstnr' and sprog_id='$sprog_id'",__FILE__ . " linje " . __LINE__);
 			}
			$box[$x]=$felttype[$x];
			if ($felttype[$x]=="select") {
				for($y=0;$y<count($feltvalg[$x]);$y++) {
					if ($feltvalg[$x][$y]) $box[$x].="|".$feltvalg[$x][$y];
				}
			}
		}
		
		db_modify("update grupper set 
			kode='0',
			box1='".db_escape_string($box[1])."',
			box2='".db_escape_string($box[2])."',
			box3='".db_escape_string($box[3])."',
			box4='".db_escape_string($box[4])."',
			box5='".db_escape_string($box[5])."',
			box6='".db_escape_string($box[6])."',
			box7='".db_escape_string($box[7])."',
			box8='".db_escape_string($box[8])."',
			box9='".db_escape_string($box[9])."',
			box10='".db_escape_string($box[10])."',
			box11='".db_escape_string($box[11])."',
			box12='".db_escape_string($box[12])."',
			box13='".db_escape_string($box[13])."',
			box14='".db_escape_string($box[14])."' 
		where id='$gruppe_id_0'",__FILE__ . " linje " . __LINE__);

		db_modify("update grupper set 
			kode='1',
			box1='".db_escape_string($box[15])."',
			box2='".db_escape_string($box[16])."',
			box3='".db_escape_string($box[17])."',
			box4='".db_escape_string($box[18])."',
			box5='".db_escape_string($box[19])."',
			box6='".db_escape_string($box[20])."',
			box7='".db_escape_string($box[21])."',
			box8='".db_escape_string($box[22])."',
			box9='".db_escape_string($box[23])."',
			box10='".db_escape_string($box[24])."',
			box11='".db_escape_string($box[25])."',
			box12='".db_escape_string($box[26])."',
			box13='".db_escape_string($box[27])."',
			box14='".db_escape_string($box[28])."' 
		where id='$gruppe_id_1'",__FILE__ . " linje " . __LINE__);
	}
	
	$q=db_select("select * from grupper where art='ANSAT' and kodenr='0'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		if ($r['kode']=='1') {
			$gruppe_id_1=$r['id'];
			$box[15]=$r['box1'];
			$box[16]=$r['box2'];
			$box[17]=$r['box3'];
			$box[18]=$r['box4'];
			$box[19]=$r['box5'];
			$box[20]=$r['box6'];
			$box[21]=$r['box7'];
			$box[22]=$r['box8'];
			$box[23]=$r['box9'];
			$box[24]=$r['box10'];
			$box[25]=$r['box11'];
			$box[26]=$r['box12'];
			$box[27]=$r['box13'];
			$box[28]=$r['box14'];
		} else {
			$gruppe_id_0=$r['id'];
			$box[1]=$r['box1'];
			$box[2]=$r['box2'];
			$box[3]=$r['box3'];
			$box[4]=$r['box4'];
			$box[5]=$r['box5'];
			$box[6]=$r['box6'];
			$box[7]=$r['box7'];
			$box[8]=$r['box8'];
			$box[9]=$r['box9'];
			$box[10]=$r['box10'];
			$box[11]=$r['box11'];
			$box[12]=$r['box12'];
			$box[13]=$r['box13'];
			$box[14]=$r['box14'];
		}
	}
	for ($x=1;$x<=28;$x++) {
		$tmp=NULL;
		$feltvalg[$x]=array();
		list($felttype[$x],$tmp)=explode("|",$box[$x],2);
		($tmp)?$feltvalg[$x]=explode("|",$tmp):$feltvalg[$x]=NULL;
	}
	if (!$gruppe_id_0) {
		if ($r = db_fetch_array(db_select("select id,box3 from grupper where art = 'DIV' and kodenr='2'",__FILE__ . " linje " . __LINE__))) {
			if (!$r['box3']) db_modify("update grupper set box3='on' where id = '$r[id]'",__FILE__ . " linje " . __LINE__);
		} else db_modify("insert into grupper (beskrivelse,kodenr,art,box3) values ('Div_valg','2','DIV','on')",__FILE__ . " linje " . __LINE__);
		db_modify("insert into grupper (beskrivelse,kodenr,kode,art) values ('Ekstra felter på ansatte stamkort','0','0','ANSAT')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q=db_select("select id from grupper where art='ANSAT' and kodenr='0'",__FILE__ . " linje " . __LINE__));
		$gruppe_id_0=$r['id'];
	}
	if (!$gruppe_id_1) {
		db_modify("insert into grupper (beskrivelse,kodenr,kode,art) values ('Ekstra felter på ansatte stamkort','0','1','ANSAT')",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array($q=db_select("select id from grupper where art='ANSAT' and kodenr='0' and kode='1'",__FILE__ . " linje " . __LINE__));
		$gruppe_id_1=$r['id'];
	
	}
	
	print "<form name=\"stamkort\" action=\"ansatte.php?funktion=stamkort\" method=post>\n";
	print "<div class=\"content\">";
	print "<div class=\"contentBorder stamkort\">";
	print "<table border=\"0\" cellspacing=\"0\" width=\"826\"><thead>";
	Print "<tr><th width=\"225\">Feltnavn</th><th width=\"170\">Felttype</th><th width=\"225\">Feltnavn</th><th width=\"170\">Felttype</th></tr></thead>"; 
	print "<tbody>";
	$i = 0;
	for ($x=1;$x<=28;$x++) {
		$tekstnr=616+$x;
		if ($felttype[$x]=="textarea") {
			$i = 1;
			print "<tr>";
			print "<td valign=\"top\"><input type=\"text\" class=\"textIndent\" name=\"feltnavn[$x]\" style=\"width:98%; padding:1px; \" value=\"".findtekst($tekstnr,$sprog_id)."\"></td>
			<td valign=\"top\"><select name=\"felttype[$x]\" style=\"width:98%;\">";
				if ($felttype[$x]=="text") print "<option value=\"text\">Tekstlinje</option>"; 
				if ($felttype[$x]=="select") print "<option value=\"select\">Valgfelt</option>"; 
				if ($felttype[$x]=="checkbox") print "<option value=\"checkbox\">Afmærkningsfelt</option>"; 
				if ($felttype[$x]=="textarea") print "<option value=\"textarea\">Tekstfelt</option>"; 
				if ($felttype[$x]!= "text") print "<option value=\"text\">Tekstlinje</option>";
				if ($felttype[$x]!="select") print "<option value=\"select\">Valgfelt</option>";
				if ($felttype[$x]!="checkbox") print "<option value=\"checkbox\">Afmærkningsfelt</option>";
				if ($felttype[$x]!="textarea") print "<option value=\"textarea\">Tekstfelt</option>"; 
			print "</select>";
			print "</td><td colspan=\"2\"></td></tr>\n";
		} else {
		if (!fmod($i, 2))
			print "<tr>";
			print "<td valign=\"top\"><input type=\"text\" class=\"textIndent\" name=\"feltnavn[$x]\" style=\"width:98%; padding:1px; \" value=\"".findtekst($tekstnr,$sprog_id)."\"></td>
			<td valign=\"top\"><select name=\"felttype[$x]\" style=\"width:98%;\">";
				if ($felttype[$x]=="text") print "<option value=\"text\">Tekstlinje</option>"; 
				if ($felttype[$x]=="select") print "<option value=\"select\">Valgfelt</option>"; 
				if ($felttype[$x]=="checkbox") print "<option value=\"checkbox\">Afmærkningsfelt</option>"; 
				if ($felttype[$x]=="textarea") print "<option value=\"textarea\">Tekstfelt</option>"; 
				if ($felttype[$x]!= "text") print "<option value=\"text\">Tekstlinje</option>";
				if ($felttype[$x]!="select") print "<option value=\"select\">Valgfelt</option>";
				if ($felttype[$x]!="checkbox") print "<option value=\"checkbox\">Afmærkningsfelt</option>";
				if ($felttype[$x]!="textarea") print "<option value=\"textarea\">Tekstfelt</option>"; 
			print "</select>";
			if ($felttype[$x]=="select") {
				print "<div class=\"valg-box\">";
				for ($y=0;$y<=count($feltvalg[$x]);$y++) {
					print "<input type =\"text\" class=\"textIndent\" name=\"feltvalg[$x][$y]\" style=\"padding:1px; width:96%;\" value=\"".$feltvalg[$x][$y]."\"><br>";
				}
				print "</div>";
			} 
			print "</td>";
			if (fmod($i, 2)) {
			print "</tr>\n";
			} 
		}
	$i++;
	}
	print "</tbody></table>";
	print "</div><!-- end of contentBorder -->";
	print "</div><!-- end of content -->";
	print "<div class=\"content\">";
	print "<table class=\"akkordTable\"><tbody>";
	print "<tr><td colspan=\"4\"><input type=hidden name=\"gruppe_id_0\" value='$gruppe_id_0'>";
	print "<input type=hidden name=\"gruppe_id_1\" value='$gruppe_id_1'>";
	print "<input type=hidden name=id value='$id'></td></tr>";
	//print "<tr><td colspan=\"4\" align = \"center\"><hr></td></tr>
	print "<tr><td colspan=\"4\" align = \"center\"><input type=submit accesskey=\"g\" value=\"Gem / opdat&eacute;r\" class=\"button gray medium\" name=\"submit\" onclick=\"javascript:docChange = false;\">
		</td></tr></tbody></table>";
	print "</div><!-- end of content -->";
	print "	</form>";
}

?>

