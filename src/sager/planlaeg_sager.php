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
	$nysager=NULL;$asc=NULL;
	
	if ($vis=='nysager') $nysager=$vis;
	if ($sort=='ASC') $asc=$sort;
	
	if ($_GET['vis']){
		if ($_GET['vis']=='nysager') {
			$_SESSION['nysager']=$nysager;
		} elseif ($_GET['vis']=='aktsager') {
			unset($_SESSION['nysager']);
		}
	} else {
		$nysager=if_isset($_SESSION['nysager']);
	}
	
	if ($_GET['sort']){
		if ($_GET['sort']=='ASC') {
			$_SESSION['ASC']=$asc;
		} elseif ($_GET['sort']=='DESC') {
			unset($_SESSION['ASC']);
		}
	} else {
		$asc=if_isset($_SESSION['ASC']);
	}
	
	$items = NULL;
	$ganttdata = NULL;
	
	$dagsdato = date("Y-m-d");
	// Her finder vi de aktive sager, som ligger inden for dags dato.
	//$where="where status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet' and (planfraop > '0') and (planfraop <= '$dagsdato' and plantilop >= '$dagsdato')"; // and (planfraop > '0') and (planfraop <= '$dagsdato' and plantilop >= '$dagsdato')
	
	// Her finder vi alle aktive sager, som har en planfraop dato.
	$where = "where status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet' and (planfraop > '0')";
	
	if ($sort=='ASC') $sorting = 'ASC';
	if ($asc) $sorting = 'ASC';
	else $sorting = 'DESC';
	
	if ($vis=='nysager') $order = NULL;
	if ($nysager) $order = NULL;
	else $order = "(planfraop <= '$dagsdato' and plantilop >= '$dagsdato') $sorting,";
	
	
	
	$x=0;
	$q=db_select("SELECT * FROM sager $where ORDER BY $order id $sorting",__FILE__ . " linje " . __LINE__); // 
	while ($r = db_fetch_array($q)) {
		$x++;
		$sag_id[$x]=$r['id'];
		$sag_nr[$x]=$r['sagsnr']*1;
		$sag_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$sag_firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$sag_ansvarlig[$x]=htmlspecialchars($r['ref']);
		$sag_omfang[$x]=htmlspecialchars($r['omfang']);
		$sag_oprettet[$x]=htmlspecialchars($r['ref']);
		//$udf_firmanavn[$x]=htmlspecialchars($r['udf_firmanavn']);
		$udf_addr1[$x]=htmlspecialchars($r['udf_addr1']);
		$udf_postnr[$x]=$r['udf_postnr'];
		$udf_bynavn[$x]=htmlspecialchars($r['udf_bynavn']);
		$oprettet_af[$x]=htmlspecialchars($r['oprettet_af']);
		$dato[$x]=date("d-m-y",$r['tidspkt']);
		$tid[$x]=date("H:i",$r['tidspkt']);
		$status[$x]=$r['status'];
		$konto_id[$x]=$r['konto_id'];
		$planfraop[$x]=dkdato($r['planfraop']);
		$plantilop[$x]=dkdato($r['plantilop']);
		//$planfraop[$x]=$r['planfraop'];
		//$plantilop[$x]=$r['plantilop'];
		//$planfraned[$x]=$r['planfraned'];
		//$plantilned[$x]=$r['plantilned'];
	}
	$antal_sager=$x;
	
	/*--------------------------------------------------------------------------------------*/
	/* Skal i en updat.php                                                                  */
	
	/*
	// Til opdatering fra dkdato til usdate i sager
	$y=0;
	$q=db_select("SELECT * FROM sager where (planfraop > '0' or planfraned > '0') ORDER BY id DESC",__FILE__ . " linje " . __LINE__); // 
	while ($r = db_fetch_array($q)) {
		$y++;
		$sag_id_opdat[$y]=$r['id'];
		$planfraop_opdat[$y]=$r['planfraop'];
		$plantilop_opdat[$y]=$r['plantilop'];
		$planfraned_opdat[$y]=$r['planfraned'];
		$plantilned_opdat[$y]=$r['plantilned'];
	}
	$antal_opdat=$y;
	
	for ($y=1;$y<=$antal_opdat;$y++) {
		
		(empty($planfraop_opdat[$y]))?$planfraop_opdat[$y] = NULL:$planfraop_opdat[$y] = usdate($planfraop_opdat[$y]);
		(empty($plantilop_opdat[$y]))?$plantilop_opdat[$y] = NULL:$plantilop_opdat[$y] = usdate($plantilop_opdat[$y]);
		(empty($planfraned_opdat[$y]))?$planfraned_opdat[$y] = NULL:$planfraned_opdat[$y] = usdate($planfraned_opdat[$y]);
		(empty($plantilned_opdat[$y]))?$plantilned_opdat[$y] = NULL:$plantilned_opdat[$y] = usdate($plantilned_opdat[$y]);
		
		//db_modify("update sager set planfraop='$planfraop_opdat[$y]',plantilop='$plantilop_opdat[$y]',planfraned='$planfraned_opdat[$y]',plantilned='$plantilned_opdat[$y]' where id = '$sag_id_opdat[$y]'",__FILE__ . " linje " . __LINE__);
		//echo "$y $planfraop_opdat[$y] - $plantilop_opdat[$y] -- $planfraned_opdat[$y] - $plantilned_opdat[$y]<br>";
	}
	*/
	/*
  // til opdatering fra dkdato til usdate i opgaver  
	$z=0;
	$q=db_select("SELECT * FROM opgaver WHERE opg_planfra > '0' ORDER BY id DESC",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$z++;
		$opg_id_opdat[$z]=$r['id'];
		$opg_planfra_opdat[$z]=$r['opg_planfra'];
		$opg_plantil_opdat[$z]=$r['opg_plantil'];
	}
	$antal_opgaver_opdat=$z;
	
	for ($z=1;$z<=$antal_opgaver_opdat;$z++) {
		
		(empty($opg_planfra_opdat[$z]))?$opg_planfra_opdat[$z] = NULL:$opg_planfra_opdat[$z] = usdate($opg_planfra_opdat[$z]);
		(empty($opg_plantil_opdat[$z]))?$opg_plantil_opdat[$z] = NULL:$opg_plantil_opdat[$z] = usdate($opg_plantil_opdat[$z]);
		
		db_modify("update opgaver set opg_planfra='$opg_planfra_opdat[$z]',opg_plantil='$opg_plantil_opdat[$z]' where id = '$opg_id_opdat[$z]'",__FILE__ . " linje " . __LINE__);
		//echo "$z $opg_planfra_opdat[$z] - $opg_plantil_opdat[$z]<br>";
	}
	*/
	/*----------------------------------------------------------------------------------------*/
	
	
	for ($x=1;$x<=$antal_sager;$x++) {
		
		$allitems = NULL;
		$itemsag = NULL;
		$itemopg = NULL;
	
		
		list($daystartop, $monthstartop,  $yearstartop) = explode("-",$planfraop[$x]);
		$newmonthstartop = sprintf('%02d',$monthstartop-1);
		
		list($dayendop, $monthendop,  $yearendop) = explode("-",$plantilop[$x]);
		$newmonthendop = sprintf('%02d',$monthendop-1);
		
		$itemsag = "{ id: \"$sag_id[$x]\", name: \"<span style='color:#078585;float:right;padding-right:5px;'>$sag_nr[$x]<\/span>\", title: \"Planlagt\", start: new Date($yearstartop,$newmonthstartop,$daystartop), end: new Date($yearendop,$newmonthendop,$dayendop)}";
		
	
		
			$where="where assign_id = $sag_id[$x] and status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet' and opg_planfra > '0' and opg_plantil > '0'";
			
			$y=0;
			$q=db_select("SELECT * FROM opgaver $where ORDER BY nr ASC",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)) {
				$y++;
				$opg_id[$y]=$r['id'];
				$opg_nr[$y]=$r['nr'];
				$opg_status[$y]=$r['status'];
				$opg_beskrivelse[$y]=htmlspecialchars($r['beskrivelse']);
				$opg_omfang[$y]=htmlspecialchars($r['omfang']);
				//$opg_udf_firmanavn[$y]=htmlspecialchars($r['udf_firmanavn']);
				$opg_ref[$y]=htmlspecialchars($r['ref']);
				$opg_oprettet_af[$y]=htmlspecialchars($r['oprettet_af']);
				$opg_dato[$y]=date("d-m-Y",$r['tidspkt']);
				$opg_tid[$y]=date("H:i",$r['tidspkt']);
				$opg_kontakt[$y]=htmlspecialchars($r['kunde_ref']);
				$opg_planfra[$y]=dkdato($r['opg_planfra']);
				$opg_plantil[$y]=dkdato($r['opg_plantil']);
				$opg_tilknyttil[$y]=$r['opg_tilknyttil'];
			}
			$antal_opgaver=$y;
			
			

			for ($y=1;$y<=$antal_opgaver;$y++) {
				
				$opg_omfangNy = NULL;
				$n = NULL;
				
				list($opg_daystart, $opg_monthstart,  $opg_yearstart) = explode("-",$opg_planfra[$y]);
				$opg_newmonthstart = sprintf('%02d',$opg_monthstart-1);
				
				list($opg_dayend, $opg_monthend,  $opg_yearend) = explode("-",$opg_plantil[$y]);
				$opg_newmonthend = sprintf('%02d',$opg_monthend-1);
				
				if ($opg_tilknyttil[$y] == 'demontage') {
					$bgcolor = '#FF8E8E';
				} elseif ($opg_tilknyttil[$y] == 'andet') {
					$bgcolor = '#FFC18E';
				} else {
					$bgcolor = '#7EE37E';
				}
				
				$opg_omfangNy = str_replace(array("\n", "\r"), ' ', $opg_omfang[$y]); // Fjerner skjulte linebreaks i omfang (textarea), da ganttView ikke kan vise dem
				($opg_omfangNy)?$n = "\\nBeskrivelse:\\n".$opg_omfangNy:$n = ''; // Hvis omfang, indsættes det i strengen
				
				$itemopg .= "{ opg_id: \"$opg_id[$y]\", title: \"Opgave nr:\t$opg_nr[$y]\\nNavn:\t\t$opg_beskrivelse[$y]\\nOprettet:\t$opg_dato[$y]\\nOprettet af:\t$opg_oprettet_af[$y]\\nStatus:\t\t$opg_status[$y]$n\\n\\nPlanlagt\", start: new Date($opg_yearstart,$opg_newmonthstart,$opg_daystart), end: new Date($opg_yearend,$opg_newmonthend,$opg_dayend), color: \"$bgcolor\"},";
			
			}
		
		$allitems = "$itemsag,$itemopg";
		//echo "$allitems";
		$items .= "{ name: \"<a href='sager.php?funktion=vis_sag&sag_id=$sag_id[$x]&konto_id=$konto_id[$x]' title='Sag:\t\t$sag_nr[$x]\\nKunde:\t\t$sag_firmanavn[$x]\\nUdf. adr.:\t$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]\\nAnsvarlig:\t$sag_ansvarlig[$x]\\nStatus:\t\t$status[$x]'>$udf_addr1[$x]<\/a>\", series: [$allitems]},";
	
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
					print "<li>Planlægning sager</li>\n";
				print "</ul>
				
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class=\"maincontentLarge\">\n";
	print "<div class=\"ganttcontent\">\n";
	
	//echo $ganttdata;
	if ($planfraop || $planfraned) {
		print "<div id=\"ganttChart\"></div>\n";
	} else {
		print "<div style=\"text-align: center;\"><h3>Planlægning til sager med aktiv status!</h3><br><p style=\"font-size:14px;color:#cd3300;\">Her vises start og slut dato for aktive sager og opgaver.</p><br><p>For at bruge planlægning, skal sagerne der vises have en aktiv status. Når status på en sag er aktiv kan der vælges start og slut dato for sagens løbetid under planlægnings information.</p></div>\n";
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