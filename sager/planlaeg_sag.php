<?php
	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();
	
	$bg="nix";
	$header='nix';

	$menu_sager='id="menuActive"';
	$menu_planlaeg=NULL;
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
	
	$sag_id=if_isset($_GET['sag_id']);
	
	$items = NULL;
	$ganttdata = NULL;
	
	$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
	$sag_id=$r['id'];
	$sag_nr=$r['sagsnr']*1;
	$sag_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$sag_firmanavn=htmlspecialchars($r['firmanavn']);
	$sag_ansvarlig=htmlspecialchars($r['ref']);
	$sag_omfang=htmlspecialchars($r['omfang']);
	$sag_oprettet=htmlspecialchars($r['ref']);
	$udf_firmanavn=htmlspecialchars($r['udf_firmanavn']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$oprettet_af=htmlspecialchars($r['oprettet_af']);
	$dato=date("d-m-y",$r['tidspkt']);
	$tid=date("H:i",$r['tidspkt']);
	$status=$r['status'];
	$konto_id=$r['konto_id'];
	$planfraop=dkdato($r['planfraop']);
	$plantilop=dkdato($r['plantilop']);
	//$planfraned=$r['planfraned'];
	//$plantilned=$r['plantilned'];
	
	list($daystartop, $monthstartop,  $yearstartop) = explode("-",$planfraop);
	$newmonthstartop = sprintf('%02d',$monthstartop-1);
	
	list($dayendop, $monthendop,  $yearendop) = explode("-",$plantilop);
	$newmonthendop = sprintf('%02d',$monthendop-1);
	/*
	list($daystartned, $monthstartned,  $yearstartned) = explode("-",$planfraned);
	$newmonthstartned = sprintf('%02d',$monthstartned-1);
	
	list($dayendned, $monthendned,  $yearendned) = explode("-",$plantilned);
	$newmonthendned = sprintf('%02d',$monthendned-1);
	
	$itemop = "{ name: \"<span style='color:#078585;'>Løbetid<\/span>\", title: \"Planlagt\", start: new Date($yearstartop,$newmonthstartop,$daystartop), end: new Date($yearendop,$newmonthendop,$dayendop), op: \"1\"}";
	$itemned = "{ name: \"<span style='color:#DE6B0C;'>Demontage<\/span>\", title: \"Planlagt\", start: new Date($yearstartned,$newmonthstartned,$daystartned), end: new Date($yearendned,$newmonthendned,$dayendned), op: \"0\", color: \"#FFC18E\"}";
	
	if ($planfraop && !$planfraned) {
		$allitems = "$itemop";
	} elseif (!$planfraop && $planfraned) {
		$allitems = "$itemned";
	} elseif ($planfraop && $planfraned) {
		$allitems = "$itemop,$itemned";
	}
	*/
	$titlesag = "$udf_addr1, $udf_postnr $udf_bynavn";
	$titlesagtrim = trim($titlesag);
	$titlesagrtrim = rtrim($titlesagtrim, ",");
	$itemsag = "{ name: \"<span style='color:#078585;'>Løbetid<\/span>\", title: \"Planlagt\", start: new Date($yearstartop,$newmonthstartop,$daystartop), end: new Date($yearendop,$newmonthendop,$dayendop)}";
	
	$items = "{ id: \"$sag_id\", opg_id: \"0\", name: \"<a href='sager.php?funktion=ret_sag&sag_id=$sag_id&konto_id=$konto_id' title='$titlesagrtrim'>$udf_addr1<\/a>\", title: \"Planlagt\", series: [$itemsag]},";
	
	
	
	
	$where="where assign_id = $sag_id and status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet' and opg_planfra > '0' and opg_plantil > '0'";
	
	$x=0;
	$q=db_select("SELECT * FROM opgaver $where ORDER BY nr ASC",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$opg_id[$x]=$r['id'];
		$opg_nr[$x]=$r['nr'];
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
	}
	$antal_opgaver=$x;
	
	

	for ($x=1;$x<=$antal_opgaver;$x++) {
	
		list($opg_daystart, $opg_monthstart,  $opg_yearstart) = explode("-",$opg_planfra[$x]);
		$opg_newmonthstart = sprintf('%02d',$opg_monthstart-1);
		
		list($opg_dayend, $opg_monthend,  $opg_yearend) = explode("-",$opg_plantil[$x]);
		$opg_newmonthend = sprintf('%02d',$opg_monthend-1);
		
		if ($opg_tilknyttil[$x] == 'demontage') {
			$bgcolor = '#FF8E8E';
			$opgcolor = '#DE0C0C';
			$opgnavn = 'Demontage';
		} elseif ($opg_tilknyttil[$x] == 'andet') {
			$bgcolor = '#FFC18E';
			$opgcolor = '#DE6B0C';
			$opgnavn = 'Andet';
		} else {
			$bgcolor = '#7EE37E';
			$opgcolor = '#09B109';
			$opgnavn = 'Montage';
		}
		
		$items .= "{ id: \"0\", opg_id: \"$opg_id[$x]\", name: \"<a href='sager.php?funktion=ret_opgave&sag_id=$sag_id&konto_id=$konto_id&opgave_id=$opg_id[$x]' title='Opgave nr:\t$opg_nr[$x]\\nOprettet:\t$opg_dato[$x]\\nOprettet af:\t$opg_oprettet_af[$x]\\nStatus:\t\t$opg_status[$x]'>$opg_beskrivelse[$x]<\/a>\", series: [{ name: \"<span style='color:$opgcolor'>$opgnavn<\/span>\", title: \"Planlagt\", start: new Date($opg_yearstart,$opg_newmonthstart,$opg_daystart), end: new Date($opg_yearend,$opg_newmonthend,$opg_dayend), color: \"$bgcolor\"}]},"; // #FF8E8E rød til demontage!
	
	}
	$ganttdata = "[".rtrim($items, ",")."]";
	
	
	//echo "$items";
	
	
	
	
	
	
	
	
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
					print "<li><a href=\"../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sag_nr, $sag_beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sag_nr</a></li>";
					print "<li>Planlægning af sag</li>\n";
				print "</ul>
				
	</div><!-- end of breadcrumbbar -->\n";
	
	print "<div class=\"maincontentLargeHolder\">\n";
	print "<div class=\"maincontentLarge\">\n";
	print "<div class=\"ganttcontent\">\n";
	
	//echo $ganttdata;
	if ($status!='Beregning' && $status!='Tilbud' && $status!='Afsluttet' && ($planfraop || $planfraned)) {
		print "<div id=\"ganttChart\"></div>";
		if (!$opg_id) {
			print "<div style=\"text-align:center;\"><br><p style=\"font-size:14px;color:#cd3300;\">Ingen aktive opgaver på sagen!</p><p>HUSK at vælge start og slut dato på opgave, hvis den skal vises</p></div>";
		} 
	} else {
		print "<div style=\"text-align:center;\"><h3>Planlægning af sagen!</h3><br><p style=\"font-size:14px;color:#cd3300;\">Her vises sagens længde + start og slut dato for opgaverne på sagen.</p><br><p>For at bruge planlægning, skal sagens status være aktiv. Når status på sagen er aktiv kan der vælges start og slut dato under planlægnings information.</p><p>Det samme gør sig gældende for opgaver. Når opgavens status er aktiv kan start og slut dato vælges.</p></div>";
	}
	//print "<br/><br/>";
	//print "<div id=\"eventMessage\"></div>";
	
	print "</div><!-- end of ganttcontent -->";
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
				groupBySeries: false,
				groupById: false,
				groupByIdDrawAllTitles: false,
				behavior: {
					onClick: function (data) { 
						//var msg = "You clicked on an event: { start: " + data.start.toString("dd-MM-yyyy") + ", end: " + data.end.toString("dd-MM-yyyy") + " }" + " id: " + data.id + " navn: " + data.name + " title: " + data.title + " opg_id: " + data.opg_id;
						//$("#eventMessage").text(msg);
						//window.location.href = "sager.php?funktion=vis_sag&sag_id=" + data.id;
					},
					onResize: function (data) { 
						var planfra = data.start.toString("dd-MM-yyyy");
						var plantil = data.end.toString("dd-MM-yyyy");
						var id = data.id;
						var opg_id = data.opg_id;
						var op = data.op;
						var dataString = 'planfra='+ planfra + '&plantil=' + plantil + '&id=' + id + '&opg_id=' + opg_id + '&op=' + op; // 
						$.ajax ({
							type: "POST",
							url: "ajax_planlaeg_sag.php",
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
						var op = data.op;
						var dataString = 'planfra='+ planfra + '&plantil=' + plantil + '&id=' + id + '&opg_id=' + opg_id + '&op=' + op; //  
						$.ajax ({
							type: "POST",
							url: "ajax_planlaeg_sag.php",
							data: dataString,
							success: function(data) {
								//alert(data);
								//var ganttData=data;
								//return ganttData;
								//$("#eventMessage").text(ganttData);
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
    $( "div.ganttview-slide-container" ).dragscrollable({
    dragSelector: 'div.ganttview-grid-row-cell, div.ganttview-block-container', 
    acceptPropagatedEvent: false
		});
  });
	</script>
	
	
	
	<?php
	print "
		</body>
	</html>";
			
	
?>