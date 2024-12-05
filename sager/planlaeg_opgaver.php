<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();
	
	$bg="nix";
	$header='nix';

	$menu_sager=NULL;
	$menu_planlaeg='id="menuActive"';
	$menu_dagbog=NULL;
	$menu_kunder=NULL;
	$menu_loen=NULL;
	$menu_ansatte=NULL;
	$menu_certificering=NULL;
	$menu_medarbejdermappe=NULL;

	$modulnr=0;
	include("../includes/connect.php");
	include("../includes/online.php");
	include("../includes/std_func.php");
	
	$vis=if_isset($_GET['vis']);
	$sort=if_isset($_GET['sort']);
	$nyopgaver=NULL;$ascopg=NULL;$planlaeg_opgaver='1';
	
	if ($vis=='nyopgaver') $nyopgaver=$vis;
	if ($sort=='ASCopg') $ascopg=$sort;
	
	if ($_GET['vis']){
		if ($_GET['vis']=='nyopgaver') {
			$_SESSION['nyopgaver']=$nyopgaver;
		} elseif ($_GET['vis']=='aktopgaver') {
			unset($_SESSION['nyopgaver']);
		}
	} else {
		$nyopgaver=if_isset($_SESSION['nyopgaver']);
	}
	
	if ($_GET['sort']){
		if ($_GET['sort']=='ASCopg') {
			$_SESSION['ASCopg']=$ascopg;
		} elseif ($_GET['sort']=='DESC') {
			unset($_SESSION['ASCopg']);
		}
	} else {
		$ascopg=if_isset($_SESSION['ASCopg']);
	}
	
	$items = NULL;
	$ganttdata = NULL;
	
	$dagsdato = date("Y-m-d");
	
	if ($sort=='ASCopg') $sorting = 'ASC';
	if ($ascopg) $sorting = 'ASC';
	else $sorting = 'DESC';
	
	if ($vis=='nyopgaver') $order = NULL;
	if ($nyopgaver) $order = NULL;
	else $order = "(opg_planfra <= '$dagsdato' and opg_plantil >= '$dagsdato') DESC,";
	
	
	$where="where sager.status != 'Beregning' and sager.status != 'Tilbud' and sager.status != 'Afsluttet' and opgaver.status != 'Beregning' and opgaver.status != 'Tilbud' and opgaver.status != 'Afsluttet' and opg_planfra > '0' and opg_plantil > '0'";
	
	$x=0;
	$opg_assign_id = array();
	$q=db_select("SELECT *,opgaver.id as opg_id,sager.ref as sager_ref,sager.beskrivelse as sager_beskrivelse,sager.status as sager_status FROM opgaver 
								INNER JOIN sager ON opgaver.assign_id = sager.id
								$where ORDER BY $order sager.id DESC",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$opg_id[$x]=$r['opg_id'];
		$opg_nr[$x]=$r['nr'];
		$opg_assign_id[$x]=$r['assign_id'];
		$opg_status[$x]=$r['status'];
		$opg_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$opg_omfang[$x]=htmlspecialchars($r['omfang']);
		$opg_udf_firmanavn[$x]=htmlspecialchars($r['udf_firmanavn']);
		$opg_ref[$x]=htmlspecialchars($r['ref']);
		$opg_oprettet_af[$x]=htmlspecialchars($r['oprettet_af']);
		$opg_dato[$x]=date("d-m-Y",$r['tidspkt']);
		$opg_tid[$x]=date("H:i",$r['tidspkt']);
		$opg_kontakt[$x]=htmlspecialchars($r['kunde_ref']);
		$opg_planfra[$x]=dkdato($r['opg_planfra']);
		$opg_plantil[$x]=dkdato($r['opg_plantil']);
		$opg_tilknyttil[$x]=$r['opg_tilknyttil'];
		
		$sag_id[$x]=$r['id'];
		$sag_nr[$x]=$r['sagsnr']*1;
		$konto_id[$x]=$r['konto_id'];
		$sag_beskrivelse[$x]=htmlspecialchars($r['sager_beskrivelse']);
		$sag_firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		$sag_ansvarlig[$x]=htmlspecialchars($r['sager_ref']);
		$status[$x]=$r['sager_status'];
	}
	$antal_opgaver=$x;
	//echo "$antal_opgaver<br><br>";
	
	$sag_id_unique = array_unique($opg_assign_id); // Her fjerner vi dublikater af sags id
	if ($sorting == 'ASC') krsort($sag_id_unique); // Hvis ASC, sortere vi array keys i omvendt rækkefølge
	$sag_id_values = array_values($sag_id_unique); // Her indexere vi det nye array, så keys kommer i rigtig rækkefølge
	
	/*
	print "<pre>";
	print_r($sag_id_unique);
	print "</pre>";
	echo "<br>";
	print "<pre>";
	print_r($sag_id_values);
	print "</pre>";
	echo "<br>";
	*/
	
	/*
	for ($x=1;$x<=$antal_opgaver;$x++) {
		echo "$x sag id: $opg_assign_id[$x] - opgave nr: $opg_nr[$x] - opgave id: $opg_id[$x] ( $opg_planfra[$x] - $opg_plantil[$x] )<br>";
	}
	echo "<br>";
	*/
	
	for ($y=0;$y<count($sag_id_values);$y++) {
	
		//echo "Ny sag id: $sag_id_values[$y]<br>";
		
		$r=db_fetch_array(db_select("select * from sager where id='$sag_id_values[$y]'",__FILE__ . " linje " . __LINE__));
		$sag_id=$r['id'];
		$sag_nr=$r['sagsnr']*1;
		//$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
		$sag_firmanavn=htmlspecialchars($r['firmanavn']);
		$sag_ansvarlig=htmlspecialchars($r['ref']);
		//$sag_omfang=htmlspecialchars($r['omfang']);
		//$sag_oprettet=htmlspecialchars($r['ref']);
		//$udf_firmanavn=htmlspecialchars($r['udf_firmanavn']);
		$udf_addr1=htmlspecialchars($r['udf_addr1']);
		$udf_postnr=$r['udf_postnr'];
		$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
		//$oprettet_af=htmlspecialchars($r['oprettet_af']);
		//$dato=date("d-m-y",$r['tidspkt']);
		//$tid=date("H:i",$r['tidspkt']);
		$status=$r['status'];
		$konto_id=$r['konto_id'];
		//$planfraop=dkdato($r['planfraop']);
		//$plantilop=dkdato($r['plantilop']);
	
		$allitems = NULL;
		$itemopg = NULL;
	
		
			$where="where assign_id = $sag_id_values[$y] and status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet' and opg_planfra > '0' and opg_plantil > '0'";
			
			$z=0;
			$q=db_select("SELECT * FROM opgaver $where ORDER BY nr ASC",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$z++;
				$opg_id[$z]=$r['id'];
				$opg_nr[$z]=$r['nr'];
				$opg_status[$z]=$r['status'];
				$opg_beskrivelse[$z]=htmlspecialchars($r['beskrivelse']);
				$opg_omfang[$z]=htmlspecialchars($r['omfang']);
				//$opg_udf_firmanavn[$y]=htmlspecialchars($r['udf_firmanavn']);
				$opg_ref[$z]=htmlspecialchars($r['ref']);
				$opg_oprettet_af[$z]=htmlspecialchars($r['oprettet_af']);
				$opg_dato[$z]=date("d-m-Y",$r['tidspkt']);
				$opg_tid[$z]=date("H:i",$r['tidspkt']);
				$opg_kontakt[$z]=htmlspecialchars($r['kunde_ref']);
				$opg_planfra[$z]=dkdato($r['opg_planfra']);
				$opg_plantil[$z]=dkdato($r['opg_plantil']);
				$opg_tilknyttil[$z]=$r['opg_tilknyttil'];
			}
			$antal_opgaver=$z;
			
			

			for ($z=1;$z<=$antal_opgaver;$z++) {
				
				$opg_omfangNy = NULL;
				$n = NULL;
				
				
				list($opg_daystart, $opg_monthstart,  $opg_yearstart) = explode("-",$opg_planfra[$z]);
				$opg_newmonthstart = sprintf('%02d',$opg_monthstart-1);
				
				list($opg_dayend, $opg_monthend,  $opg_yearend) = explode("-",$opg_plantil[$z]);
				$opg_newmonthend = sprintf('%02d',$opg_monthend-1);
				
				if ($opg_tilknyttil[$z] == 'demontage') {
					$bgcolor = '#FF8E8E';
				} elseif ($opg_tilknyttil[$z] == 'andet') {
					$bgcolor = '#FFC18E';
				} else {
					$bgcolor = '#7EE37E';
				}
				
				$opg_omfangNy = str_replace(array("\n", "\r"), ' ', $opg_omfang[$z]); // Fjerner skjulte linebreaks i omfang (textarea), da ganttView ikke kan vise dem
				($opg_omfangNy)?$n = "\\nBeskrivelse:\\n".$opg_omfangNy:$n = ''; // Hvis omfang, indsættes det i strengen
				
				if ($ny_sag_nr == $sag_nr) {
					$name_sag_nr = NULL;
				} else {
					$name_sag_nr = "name: \"<span style='color:#078585;float:right;padding-right:5px;'>$sag_nr<\/span>\",";
				}
				
				
				$itemopg .= "{ opg_id: \"$opg_id[$z]\", $name_sag_nr title: \"Opgave nr:\t$opg_nr[$z]\\nNavn:\t\t$opg_beskrivelse[$z]\\nOprettet:\t$opg_dato[$z]\\nOprettet af:\t$opg_oprettet_af[$z]\\nStatus:\t\t$opg_status[$z]$n\\n\\nPlanlagt\", start: new Date($opg_yearstart,$opg_newmonthstart,$opg_daystart), end: new Date($opg_yearend,$opg_newmonthend,$opg_dayend), color: \"$bgcolor\"},";
				
				$ny_sag_nr = $sag_nr;
			}
		
		$allitems = "$itemopg";// $itemsag,
		//echo "$allitems";
		$items .= "{ name: \"<a href='sager.php?funktion=vis_sag&sag_id=$sag_id&konto_id=$konto_id' title='Sag:\t\t$sag_nr\\nKunde:\t\t$sag_firmanavn\\nUdf. adr.:\t$udf_addr1, $udf_postnr $udf_bynavn\\nAnsvarlig:\t$sag_ansvarlig\\nStatus:\t\t$status'>$udf_addr1<\/a>\", series: [$allitems]},";
	
		
	}
	$ganttdata = "[".rtrim($items, ",")."]";
	
	
	//echo "Today's date $dagsdato<br>";
	//echo "<pre style=\"float:left;\">Fra op<br>",print_r($planfraop,1),"</pre>";
	//echo "<pre style=\"float:left;\">Til op<br>",print_r($plantilop,1),"</pre>";
	//echo "<pre>Id<br>",print_r($sag_id,1),"</pre>";
	
	
	print "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\" \"http://www.w3.org/TR/html4/loose.dtd\">
	<html>
	<head>
		<meta http-equiv=\"X-UA-Compatible\" content=\"IE=10\">
		<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">
		<meta name=\"viewport\" content=\"width=1024\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/main_tilbud.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/jquery-ui-1.8.4.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/jquery.ganttView.css\">
		
		<!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
		<![endif]-->
		<title>Stillads</title>
	</head>
	<body>
		<div id=\"wrapper\">";
	
	include("../includes/top_sagsmenu.php");
	
	print "<div id=\"breadcrumbbar\">

				<ul id=\"breadcrumb\">
					<li><a href=\"../sager/sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a></li>
					<!--<li><a href=\"#\" title=\"Sample page 1\">Sample page 1</a></li>-->";
					print "<li><a href=\"planlaeg.php\" title=\"Tilbage til planlægning menu\">Planlægning</a></li>\n";
					print "<li>Planlægning opgaver</li>\n";
				print "</ul>
				
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class=\"maincontentLarge\">\n";
	print "<div class=\"ganttcontent\">\n";
	
	//echo $ganttdata;
	if ($opg_planfra || $opg_plantil) {
		print "<div id=\"ganttChart\"></div>\n";
	} else {
		print "<div style=\"text-align: center;\"><h3>Planlægning til opgaver for sager med aktiv status!</h3><br><p style=\"font-size:14px;color:#cd3300;\">Her vises start og slut dato for aktive opgaver.</p><br><p>For at bruge planlægning til opgaver, skal sagerne til opgaverne der vises, have en aktiv status. Når status på en opgave er aktiv kan der vælges start og slut dato for opgavens løbetid under planlægnings information.</p></div>\n";
	}
	//print "<br/><br/>";
	//print "<div id=\"eventMessage\"></div>";
	
	print "</div><!-- end of ganttcontent -->\n";
	print "</div><!-- end of maincontentLarge -->\n";
	print "</div><!-- end of maincontentLargeHolder -->\n";
	print "</div><!-- end of wrapper -->\n";
	
	?>
	<script type="text/javascript" src="../javascript/jquery-1.4.2.js"></script>
	<script type="text/javascript" src="../javascript/date-da-DK.js"></script>
	<script type="text/javascript" src="../javascript/jquery-ui-1.8.4.js"></script>
	<script type="text/javascript" src="../javascript/jquery.ganttView.js"></script>
	<script type="text/javascript" src="../javascript/jquery.scrollTo.js"></script>
	<script type="text/javascript" src="../javascript/dragscrollable.js"></script>
	<script type="text/javascript">
		$(function () {
			var ganttData = <?php echo $ganttdata; ?>;
			$("#ganttChart").ganttView({ 
				data: ganttData,
				slideWidth: 1550,
				groupBySeries: true,
				groupById: false,
				groupByIdDrawAllTitles: false,
				behavior: {
					onClick: false,//function (data) { 
						//var msg = "You clicked on an event: { start: " + data.start.toString("dd-MM-yyyy") + ", end: " + data.end.toString("dd-MM-yyyy") + " }" + " id: " + data.id + " navn: " + data.name + " title: " + data.title;
						//var msg = data.color + " " + data.opg_id;
						//$("#eventMessage").text(msg);
						//window.location.href = "planlaeg_sag.php?sag_id=" + data.id;
					//},
					onResize: function (data) { 
						var planfra = data.start.toString("dd-MM-yyyy");
						var plantil = data.end.toString("dd-MM-yyyy");
						var id = data.id;
						var opg_id = data.opg_id;
						var dataString = 'planfra='+ planfra + '&plantil=' + plantil + '&id=' + id + '&opg_id=' + opg_id;
						$.ajax ({
							type: "POST",
							url: "ajax_planlaeg_sager.php",
							data: dataString,
							success: function(data) {
								//alert(data);
							} 
						});
					},
					onDrag: function (data) { 
						var planfra = data.start.toString("dd-MM-yyyy");
						var plantil = data.end.toString("dd-MM-yyyy");
						var id = data.id;
						var opg_id = data.opg_id;
						var dataString = 'planfra='+ planfra + '&plantil=' + plantil + '&id=' + id + '&opg_id=' + opg_id;
						$.ajax ({
							type: "POST",
							url: "ajax_planlaeg_sager.php",
							data: dataString,
							success: function(data) {
								//var ganttData=data;
								//return ganttData;
								//$("div.ganttview-block-text").text('test' + id);
							} 
						});
					}
				}
			});
			
			// $("#ganttChart").ganttView("setSlideWidth", 600);
		});
	
	
	// Finder dags dato, og scroller til div med dags dato
	$(document).ready(function(){
		
		var d = new Date();

		var month = d.getMonth()+1;
		var day = d.getDate();
		var year = d.getFullYear();

		var output = "d" + day + "" + month + "" + year;

		//alert(output);
		//$( "div.ganttview-slide-container" ).scrollLeft(300);
		$( "div.ganttview-slide-container" ).scrollTo('#' + output);
	});
	
	
	// Scroll med mus i ganttView 
	$(document).ready(function(){
		if (! navigator.userAgent.match(/(iPod|iPhone|iPad|Android)/)) { 
			$( "div.ganttview-slide-container" ).dragscrollable({
			dragSelector: 'div.ganttview-grid-row-cell, div.ganttview-block-container', 
			acceptPropagatedEvent: false
			});
		}
  });
  
  
  // Sætter datobar i ganttChart fast til toppen af siden når der scrolles ned
  $(document).ready(function(){
  
		if (! navigator.userAgent.match(/(iPod|iPhone|iPad|Android)/)) { 
  
		//store the elements
		var $cache = $('.ganttview-hzheader');
		var $cache2 = $('.ganttview-grid');
		var $cache3 = $('.ganttview-vtheader-top');
		var $cache4 = $('.ganttview-vtheader');

		//store the initial position of the element
		var vTop = $cache.offset().top - parseFloat($cache.css('margin-top').replace(/auto/, 0));
			$(window).scroll(function (event) {
				// what the y position of the scroll is
				var y = $(this).scrollTop();
				// what the scrollPos position of the scroll is - 162px to top of page
				var scrollPos = ($(document).scrollTop() - 162);
				
				// whether that's below the form
				if (y >= vTop) {
					// if so, ad the fixed class
					$cache.addClass('stuck');
					$cache2.addClass('stuck2');
					$cache3.addClass('stuck3');
					$cache4.addClass('stuck4');
						// set the top position of element
					$cache.css({
						top: scrollPos
					});
				} else {
					// otherwise remove it
					$cache.removeClass('stuck');
					$cache2.removeClass('stuck2');
					$cache3.removeClass('stuck3');
					$cache4.removeClass('stuck4');
					}
			});
		}
  });
  
</script>
	
	
	<?php
	print "
		</body>
	</html>";
			
	
?>
