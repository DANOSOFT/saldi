<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- sager/sager.php --- lap 4.0.8 --- 2023-09-27 ---
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2012-2023 saldi.dk aps
// ----------------------------------------------------------------------
// Har lagt al javascript i en separat fil ved navn 'jquery.sager.js' + diverse html rettelser
// HTML rettelser til liste-visning og oprettelse af sag
// 20130201 Tilføjet funktion ret_opgave 
// 20140610 Ændret variablenavne til status (mulig duplikat med status i funktion 'ret_sag'). Søg #20140610-1
// 20140610 Man kan kun ændre status, hvis man er ansvarlig på sagen. Søg #20140610-2
// 20140620 Har tilføjet en checkbox 'slider' til at slå ret status til og fra, ved brug af $_SESSION. Søg #20140620-1
// 20140620 Ændring af tekst-farve ved ret status. Søg #20140620-2
// 20140807 Ny funktion 'sag_kontakt'. Her oprettes kantakter til den enkelte sag.
// 20140902 Indsat visning af kreditnota i 'vis_sag'. Søg kreditnota 
// 20150220 Tilføjet avanceret søg i sagsliste. Søg asoeg
// 20150224 Tilføjet db_escape_string til insert i ret_sag. Søg ret_sag()
// 20150327 Ny funktion 'akkordliste', som samler akkordlister fra sagen
// 20150407 Mulighed for at sammenligne to opgaver i funktion 'akkordliste'
// 20150528 Der er sat session på tilbud, aktive og afsluttede i leftmenu, så den husker søgning. Søg tilbudL eller aktivL eller afsluttedeL
// 20150717
// 20151019 Der er indsat to nye punkter i status "Beregning og Drivgods". Der er rettet flere steder på siden(Søg #20151019, #20151019-1, #20151019-2) + 
//					main.css(Søg status_color1, statcolor_1) + ajax_statusupdate.php + autocomplete.php(Søg case 'sagsnr',case 'sagsaddr') + planlaeg_sager.php + planlaeg_sag.php
// 20160303 Har delt query til visning af timer og loen_id. Har fjernet 'akkord afregning' fra query(timer), da den talte alle dyrtidstimer sammen to gange. Søg. #20160303
// 20160405 Har tilføjet afvist = '' til query under akkord afregning, da sedler som havde været godkendt og afvist blev talt med i samlet timeantal.
// 20160415 Query til visning af faktura i grupper under sagens omkostninger.
// 20160729 Tilføjet lønudgifter i visning af akkordlister. Søg #20160729
// 20160926 Hvis der er ændringer i kundekort synces det med sagen. Søg #20160926
// 20160930 Tilføjet funktion ny_kunde, som ændre kunde på sag.
// 20161125 Beregning og visning af dækningsbidrag og dækningsgrad i mellem lønudgifter og faktureringer. #20161125
// 20170421 Mulighed for at vælge en fra og til dato i funktion 'akkordliste'
// 20230927 PHR Page title now set in online.php

	@session_start();	# Skal angives oeverst i filen??!!
	$s_id=session_id();
	ini_set("display_errors", "0");

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

	$funktion=if_isset($_GET['funktion']);
	$sag_id=if_isset($_GET['sag_id']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$vis=if_isset($_GET['vis']);
	$returside=if_isset($_GET['returside']);
	$konto_id=if_isset($_GET['konto_id']);
	$ordre_id=if_isset($_GET['ordre_id']);
	#cho "returside: $returside";
		
	if (!$funktion) {
		($sag_id)?$funktion='':$funktion='sagsliste';  
	}

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
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/smoothness/jquery-ui-1.9.2.custom.css\">
		<link rel=\"stylesheet\" type=\"text/css\" href=\"../css/print.css\" media=\"print\">
		<script type=\"text/javascript\" src=\"../javascript/jquery-1.8.0.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery-ui-1.9.2.custom.min.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/ui.datepicker-da.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.autocomplete.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.pajinate.js\"></script>
		<script type=\"text/javascript\" src=\"../javascript/jquery.sager.js\"></script>
		
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
		<title>".$pageTitle."</title>
		
	</head>
	<body>
		<div id=\"wrapper\">";
			include ("../includes/sagsmenu.php");
			$retstatus=if_isset($_POST['onoffswitch']); #20140620-1
		
			if ($_POST['onoffswitch']){
				if ($_POST['onoffswitch']=='on') {
					$_SESSION['retstatus']=$retstatus;
				} elseif ($_POST['onoffswitch']=='off') {
					unset($_SESSION['retstatus']);
				}
			} else {
				$retstatus = if_isset($_SESSION['retstatus']);
			}
			
			($retstatus=='on')?$checked_retstatus='checked':$checked_retstatus=NULL;
			print "<div id=\"breadcrumbbar\">
			
			<form name=\"retstatus\" action=\"sager.php\" method=\"post\">
				<ul id=\"breadcrumb\">
					<li>";
					if (substr($sag_rettigheder,2,1)) print "<a href=\"sager.php\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					else print "<a href=\"\" title=\"Hjem\"><img src=\"../img/home.png\" alt=\"Hjem\" class=\"home\" /></a>";
					print "</li>\n";
					if ($returside=='ordre') {
						$r=db_fetch_array(db_select("select * from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
						$sagsnr=$r['sagsnr'];
						$beskrivelse=htmlspecialchars($r['beskrivelse']);
						$udf_addr1=htmlspecialchars($r['udf_addr1']);
						$udf_postnr=$r['udf_postnr'];
						$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
						
						$r=db_fetch_array(db_select("select * from ordrer where id='$ordre_id'",__FILE__ . " linje " . __LINE__));
						$nr=$r['nr'];
						
						print "<li><a href=\"../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id&amp;konto_id=$konto_id\" title=\"Sag: $sagsnr, $beskrivelse, $udf_addr1, $udf_postnr $udf_bynavn\">Tilbage til sag $sagsnr</a></li>
						<li><a href=\"../debitor/ordre.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;tjek=$ordre_id&amp;id=$ordre_id&amp;returside=sager\" title=\"Tilbage til tilbud\">Original tilbud $nr</a></li>
						<li>Kopi til sag</li>";
					} else {
					print "
						<!--<li><a href=\"#\" title=\"Sample page 3\">Sample page 3</a></li>
						<li>Current page</li>-->
						<li>Sager</li>\n";
					}
					if ($funktion=='akkordliste') {
						print "<li style=\"float:right;\"><a href=\"#\" title=\"Print akkordliste\" class=\"print-preview\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print akkordliste\" class=\"printIcon\" /></a></li>";
					}
					if ($funktion=='ret_sag') {
						print "<li style=\"float:right;\"><a href=\"#\" title=\"Print sags information\" class=\"print-preview\" onclick=\"printDiv('printableArea')\" style=\"background-image: none;\"><img src=\"../img/printIcon2.png\" alt=\"Print akkordliste\" class=\"printIcon\" /></a></li>";
					}
					if (!$sag_id) {
						print "<li style=\"float:right;\">
						<div class=\"onoffswitch\">
								<input type=\"hidden\" name=\"onoffswitch\" value=\"off\" />
								<input type=\"checkbox\" name=\"onoffswitch\" class=\"onoffswitch-checkbox\" id=\"myonoffswitch\" onclick=\"this.form.submit()\" $checked_retstatus>
								<label class=\"onoffswitch-label\" for=\"myonoffswitch\">
										<span class=\"onoffswitch-inner\"><!-- --></span>
										<span class=\"onoffswitch-switch\"><!-- --></span>
								</label>
						</div>
						</li>
						<li style=\"float: right;font-size: 12px;color: #444;\">Ret status</li>\n";
					}
					print "
				</ul>
			</form>
			
			</div><!-- end of breadcrumbbar -->

			<div id=\"leftmenuholder\">";
				include ("leftmenu.php");
			print "</div><!-- end of leftmenuholder -->
			<div class=\"maincontent\">";
			if (substr($sag_rettigheder,2,1)) $funktion($sag_id);
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
		
		<script type=\"text/javascript\">
		
		$(document).ready(function () {
		
			// Her en funktion som viser select box, når der clickes på status-text. 
			// Hvis anden status-text clickes mens select box er vist, gemmes den forige og ny vises.
			$('.toggle').hide();
			$('span.togglelink').click(function (e) {
					e.preventDefault();
					var elem = $(this).next('.toggle')
					$('.toggle').not(elem).hide();
					elem.toggle();
			});
			
			/*
			// Her en funktion som gemmer '.toggle' når der clickes på link med class .hideIt
			// Click event på 'option' VIRKER IKKE MED CHROME, SAFARI OG OPERA ???? 
			// Funktionen er flyttet til ajax 'success' som det sidste event.
			$('.hideIt').click(function() {
				$('.toggle').hide();
			});
			*/
			
			// Her en funktion som gemmer '.toggle', hvis der bliver clicket uden for boxen
			$(document).click(function (e) {
					if ($(e.target).closest('.toggle').length > 0 || $(e.target).closest('.togglelink').length > 0) return;
					$('.toggle').hide();
			});
		});
		
		
		function statussubmit(formId)
		{
		
				var status = $(\".update_select\"+formId).val();
				var sagid = $(\".update_id\"+formId).val();
				var dataString = 'status='+ status + '&sagid=' + sagid;
			/* $('.dataString').html(dataString); */ // til echo af dataString
			$.ajax({
					type: \"POST\",
					url: \"ajax_statusupdate.php\",
					data: dataString,
					dataType: \"json\",
					success: function(data) {
							$(\".updated_status\"+formId).html(data.status);
							$(\".updated_status\"+formId).prop('title', data.status);
							if ($(\".updated_color\"+formId).hasClass('statcolor_1')) {
								$(\".updated_color\"+formId).removeClass('statcolor_1').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_2')) {
								$(\".updated_color\"+formId).removeClass('statcolor_2').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_3')) {
								$(\".updated_color\"+formId).removeClass('statcolor_3').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_4')) {
								$(\".updated_color\"+formId).removeClass('statcolor_4').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_5')) {
								$(\".updated_color\"+formId).removeClass('statcolor_5').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_6')) {
								$(\".updated_color\"+formId).removeClass('statcolor_6').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_7')) {
								$(\".updated_color\"+formId).removeClass('statcolor_7').addClass(data.statcolor);
							} else if ($(\".updated_color\"+formId).hasClass('statcolor_8')) {
								$(\".updated_color\"+formId).removeClass('statcolor_8').addClass(data.statcolor);
							} else {
								$(\".updated_color\"+formId).addClass(data.statcolor);
							}
							$('.toggle').hide();
					}
				});
				return false;
				
		}
	
		
		</script>
		<script type=\"text/javascript\">
		
		/* javascript funktion til print */
			function printDiv(divName) { // Original code. MÅ IKKE SLETTES!!!!!
				var printContents = document.getElementById(divName).innerHTML;
				var originalContents = document.body.innerHTML;
				
				document.body.innerHTML = printContents;

				window.print();

				document.body.innerHTML = originalContents;
				//$(\"#akkordliste\").submit();
				return false;
			}
			
		</script>
		<script type=\"text/javascript\">
		/* function til valg af start og slut dato til stillads op planlægning med datepicker */
		$(document).ready(function() {
				$( \"#planfraop\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#plantilop\").datepicker(\"option\",\"minDate\", selected)
						}
				});
				$( \"#plantilop\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#planfraop\").datepicker(\"option\",\"maxDate\", selected)
						}
				});
		});
		
		/* function til valg af opret og tilbud dato til beregning i planlægning med datepicker */
		$(document).ready(function() {
				$( \"#beregn_opret\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#beregn_tilbud\").datepicker(\"option\",\"minDate\", selected)
						}
				});
				$( \"#beregn_tilbud\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#beregn_opret\").datepicker(\"option\",\"maxDate\", selected)
						}
				});
		});
		
		/* function til valg af start og slut dato til stillads ned planlægning med datepicker */
		$(document).ready(function() {
				$( \"#planfraned\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#plantilned\").datepicker(\"option\",\"minDate\", selected)
						}
				});
				$( \"#plantilned\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#planfraned\").datepicker(\"option\",\"maxDate\", selected)
						}
				});
		});
		
		/* function til valg af start og slut dato til opgave planlægning med datepicker */
		$(document).ready(function() {
				$( \"#opgave_planfra\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#opgave_plantil\").datepicker(\"option\",\"minDate\", selected)
						}
				});
				$( \"#opgave_plantil\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						onSelect: function(selected) {
							$(\"#opgave_planfra\").datepicker(\"option\",\"maxDate\", selected)
						}
				});
		});
		
		/* function til valg af fra og til dato til visning af akkordlister med datepicker */
		$(document).ready(function() {
				
				//var eventDates = ['2016/10/10', '2016/10/11', '2016/11/18', '2016/12/08'];
				
				var uniqueDates = $(\"#uniqueDates\").val();
				var dateArray = uniqueDates.split(\",\");
				
				var item, eventDates = [];
				for (var i = 0; i < dateArray.length; i++) {
						item = {};
						item = dateArray[i];
						eventDates.push(item);
				}
				
				function highLight(date) {
						for (var i = 0; i < eventDates.length; i++) {
								if (new Date(eventDates[i]).toString() == date.toString()) {
										return [true, \"event\", ''];
								}
						}
						return [true];
				}
				
				//alert(eventDates);
				
				$( \"#akkordfraSoeg\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						showButtonPanel: true,
						closeText: 'Slet',
						defaultDate: $(\"#akkordfra1\").val(),
						minDate: $(\"#akkordfra1\").val(),
						maxDate: $(\"#akkordtil1\").val(),
						beforeShowDay: highLight,
						onSelect: function(selected) {
							$(\"#akkordtilSoeg\").datepicker(\"option\",\"minDate\", selected)
							$(\"#akkordliste\").submit(); 
						},
						onClose: function () {
                var event = arguments.callee.caller.caller.arguments[0];
                if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
                    $(this).val('');
                    $(\"#akkordtilSoeg\").val('');
                    $(\"#akkordliste\").submit();
                }
            }
				});
				$( \"#akkordtilSoeg\" ).datepicker({
						showWeek: true,
						showOtherMonths: true,
						selectOtherMonths: true,
						showButtonPanel: true,
						closeText: 'Slet',
						minDate: $(\"#akkordfra2\").val(),
						maxDate: $(\"#akkordtil2\").val(),
						beforeShowDay: highLight,
						onSelect: function(selected) {
							if($.trim($(\"#akkordfraSoeg\").val()) == ''){
								var date=$(this).datepicker('getDate');
								$(\"#akkordfraSoeg\").datepicker(\"setDate\", date);
							}
							$(\"#akkordfraSoeg\").datepicker(\"option\",\"maxDate\", selected)
							$(\"#akkordliste\").submit();
						},
						onClose: function () {
                var event = arguments.callee.caller.caller.arguments[0];
                if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
                    $(this).val('');
                    $(\"#akkordliste\").submit();
                }
            }
				});
		});
		</script>
	</body>
</html>";

function sagsliste() {
	global $brugernavn;
	
	$notat_id=if_isset($_GET['notat_id']);
	$retstatus=$_SESSION['retstatus'];
	//$asoeg=$_SESSION['avanceretsoeg'];
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('sagsnr','firmanavn','udf_addr1','ref','status');
	$vis=if_isset($_GET['vis']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	$sag_limit=if_isset($_POST['sag_limit']);
	$asoeg=if_isset($_GET['asoeg']);
	$ss_sagsagsnr=if_isset($_POST['ss_sagsagsnr']);
	$ss_sagfirmanavn=if_isset($_POST['ss_sagfirmanavn']);
	$ss_sagadresse=if_isset($_POST['ss_sagadresse']);
	$ss_sagpostnr=if_isset($_POST['ss_sagpostnr']);
	$ss_sagby=if_isset($_POST['ss_sagby']);
	$ss_ansvarlig=if_isset($_POST['ss_ansvarlig']);
	$ss_status=if_isset($_POST['ss_status']);
	
	if ($vis=='beregningL') $beregningL=$vis;
	elseif ($vis=='tilbudL') $tilbudL=$vis;
	elseif ($vis=='aktivL') $aktivL=$vis;
	elseif ($vis=='afsluttedeL') $afsluttedeL=$vis;
	
	if ($_GET['asoeg']){
		if ($_GET['asoeg']=='on') {
			$_SESSION['avanceretsoeg']=$asoeg;
		} elseif ($_GET['asoeg']=='off') {
			unset($_SESSION['avanceretsoeg']);
		}
	} else {
		$asoeg = if_isset($_SESSION['avanceretsoeg']);
	}
	
	if(isset($_POST['soeg']) && $_POST['soeg']) {
		$_SESSION['ss_sagsagsnr']    = $ss_sagsagsnr;
		$_SESSION['ss_sagfirmanavn'] = $ss_sagfirmanavn;
		$_SESSION['ss_sagadresse']   = $ss_sagadresse;
		$_SESSION['ss_sagpostnr']    = $ss_sagpostnr;
		$_SESSION['ss_sagby']        = $ss_sagby;
		$_SESSION['ss_ansvarlig']    = $ss_ansvarlig;
		$_SESSION['ss_status']       = $ss_status;
	} else {
		$ss_sagsagsnr    = if_isset($_SESSION['ss_sagsagsnr']   );
		$ss_sagfirmanavn = if_isset($_SESSION['ss_sagfirmanavn']);
		$ss_sagadresse   = if_isset($_SESSION['ss_sagadresse']  );
		$ss_sagpostnr    = if_isset($_SESSION['ss_sagpostnr']   );
		$ss_sagby        = if_isset($_SESSION['ss_sagby']       );
		$ss_ansvarlig    = if_isset($_SESSION['ss_ansvarlig']   );
		$ss_status       = if_isset($_SESSION['ss_status']      );
	}
	/*
	$statusupdat=if_isset($_POST['status']);
	$sagidupdat=if_isset($_POST['sagid']);
	
	echo "status: $statusupdat<br>";
	echo "sagid: $sagidupdat";
	*/
	/*
	if ($statusupdat && $sagidupdat) {
		db_modify("update sager set status='$statusupdat' where id = '$sagidupdat'",__FILE__ . " linje " . __LINE__);
	}
	*/
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
		$_SESSION['sager_sagsnr']=$sortstyle[0];
		$_SESSION['sager_firmanavn']=$sortstyle[1];
		$_SESSION['sager_udf_addr1']=$sortstyle[2];
		$_SESSION['sager_ref']=$sortstyle[3];
		$_SESSION['sager_status']=$sortstyle[4];
	} else {
		$sortstyle[0] = if_isset($_SESSION['sager_sagsnr']);
		$sortstyle[1] = if_isset($_SESSION['sager_firmanavn']);
		$sortstyle[2] = if_isset($_SESSION['sager_udf_addr1']);
		$sortstyle[3] = if_isset($_SESSION['sager_ref']);
		$sortstyle[4] = if_isset($_SESSION['sager_status']);
	}
	
	if ($_GET['vis']) {
		$_SESSION['beregningL']=$beregningL;
		$_SESSION['tilbudL']=$tilbudL;
		$_SESSION['aktivL']=$aktivL;
		$_SESSION['afsluttedeL']=$afsluttedeL;
	} else {
		$beregningL  = if_isset($_SESSION['beregningL']);
		$tilbudL     = if_isset($_SESSION['tilbudL']);
		$aktivL      = if_isset($_SESSION['aktivL']);
		$afsluttedeL = if_isset($_SESSION['afsluttedeL']);
	}
	
	if ($_POST['sag_limit']) {
		$_SESSION['sag_limit']=$sag_limit;
	} else {
		$sag_limit = if_isset($_SESSION['sag_limit']);
	}
	
	
		
	if ($unsetsort) {
		unset($_SESSION['sager_sort'],
					$_SESSION['sager_sagsnr'],$sortstyle[0],
					$_SESSION['sager_firmanavn'],$sortstyle[1],
					$_SESSION['sager_udf_addr1'],$sortstyle[2],
					$_SESSION['sager_ref'],$sortstyle[3],
					$_SESSION['sager_status'],$sortstyle[4],
					$_SESSION['sag_limit'],$sag_limit,
					$_SESSION['ss_sagsagsnr'],$ss_sagsagsnr,
					$_SESSION['ss_sagfirmanavn'],$ss_sagfirmanavn,
					$_SESSION['ss_sagadresse'],$ss_sagadresse,
					$_SESSION['ss_sagpostnr'],$ss_sagpostnr,
					$_SESSION['ss_sagby'],$ss_sagby,
					$_SESSION['ss_ansvarlig'],$ss_ansvarlig,
					$_SESSION['ss_status'],$ss_status,
					$_SESSION['beregningL'],$beregningL,
					$_SESSION['tilbudL'],$tilbudL,
					$_SESSION['aktivL'],$aktivL,
					$_SESSION['afsluttedeL'],$afsluttedeL
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if (isset($sort) && $sort) $_SESSION['sager_sort']=$sort;
	else $sort = if_isset($_SESSION['sager_sort']);
	if (!$sort) $sort="sagsnr%20desc";
	
	$sqlsort=urldecode($sort);
	
	$limitarray=array('500','1000','2500','5000','10000','NULL');
	$limitnavn=array('500','1000','2500','5000','10000','Alle');
	
	($sag_limit)?$limit=$sag_limit:$limit='500';
	
	//if ($vis=='ordrebekraeftelse') $where="where status ='Ordrebekræftelse'"; 
	if ($vis=='beregning') $where="where status ='Beregning' ";
	elseif ($vis=='tilbud') $where="where status ='Tilbud' ";
	elseif ($vis=='aktiv') $where="where status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet'"; #  status !='Ordrebekræftelse' and
	elseif ($vis=='afsluttede') $where="where status ='Afsluttet'";
	elseif ($beregningL) $where="where status ='Beregning' ";
	elseif ($tilbudL) $where="where status ='Tilbud' "; 
	elseif ($aktivL) $where="where status != 'Beregning' and status != 'Tilbud' and status != 'Afsluttet'";
	elseif ($afsluttedeL) $where="where status ='Afsluttet'";
	else $where='';
	
	// Her indsættes $where til avanceret søg
	if ($asoeg=='on') {
		if ($ss_sagsagsnr) $where.= " and sagsnr::text LIKE '$ss_sagsagsnr%'";
		if ($ss_sagfirmanavn) $where.= " and firmanavn = '$ss_sagfirmanavn'";
		if ($ss_sagadresse) {
			if (preg_match('/[A-Za-z]\s/', $ss_sagadresse) && preg_match('/[0-9]/', $ss_sagadresse)) {
				//echo 'Contains at least one letter and one number<br>';
				$result = preg_split('/(?<=[A-Za-z])\s+(?=\d)/', "$ss_sagadresse"); // '#(?<=\d)(?=[a-z])#i' , '/(?<=\d)\s+(?=\d)/' , '/[\s,]+/'
				$letter = $result[0];
				$number = $result[1];
				$where.= " and udf_addr1 ILIKE '%$letter%' and udf_addr1 ILIKE '%$number%'";
			} else {
				//echo 'Contains only letter or number';
				$where.= " and udf_addr1 ILIKE '%$ss_sagadresse%'";
			}
		}
		if ($ss_sagpostnr) $where.= " and udf_postnr = '$ss_sagpostnr'"; 
		if ($ss_sagby) {
			$tmp = $ss_sagby.chr(46);
			$where.= " and (udf_bynavn ILIKE '$ss_sagby' OR udf_bynavn ILIKE '$tmp')"; 
		}
		if ($ss_ansvarlig) $where.= " and ref = '$ss_ansvarlig'";
		if ($ss_status) $where.= " and status = '$ss_status'";
	}
	$where=trim($where);
	if (substr($where,0,3)=='and') $where=" where ".substr($where,4);
	
	$x=0;
	$q=db_select("select * from sager $where order by $sqlsort limit $limit",__FILE__ . " linje " . __LINE__);
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
	
	// Her tæller vi alle sager uden limit
	$x=0;
	$q=db_select("select id from sager",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$alleSagerId[$x]=$r['id'];
		}
	$antal_sager_ialt=$x;
	
	$statcolor = NULL;
	if ($status=='Beregning') $statcolor = "status_color1";
	if ($status=='Tilbud') $statcolor = "status_color2";
	if ($status=='Ordrebekræftelse') $statcolor = "status_color3";
	if ($status=='Montage') $statcolor = "status_color4";
	if ($status=='Godkendt') $statcolor = "status_color5";
	if ($status=='Afmeldt') $statcolor = "status_color6";
	if ($status=='Drivgods') $statcolor = "status_color7";
	if ($status=='Afsluttet') $statcolor = "status_color8";
	$color = array("status_color1","status_color2","status_color3","status_color4","status_color5","status_color6","status_color7","status_color8");
	
	/* Query til opg_status
	---------------------------------------
	*/
	#20140610-1 
	#20151019
	 //Her ændres tekst til status 
	$sag_status_tekst_liste="Beregning,Tilbud,Ordrebekræftelse,Montage,Godkendt,Afmeldt,Drivgods,Afsluttet"; // gammel status "Opm&aring;ling,Tilbud,Ordre modtaget,Montage,Aflevering,Afmeldt,Demontage,Afsluttet" 
	
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__))) {
		if ($r['box1']==$sag_status_tekst_liste) $sag_status_liste=explode(chr(44),$r['box1']);
		else {
			db_modify("update grupper set box1='$sag_status_tekst_liste' where art='SAGSTAT'",__FILE__ . " linje " . __LINE__);
		}
/*
	$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
	$sag_status_liste=explode(chr(44),$r['box1']);
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__)) && ($r['box1']==$sag_status_tekst_liste)) {
		$sag_status_liste=explode(chr(44),$r['box1']);
	} elseif ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__)) && ($r['box1']!=$sag_status_tekst_liste)) {
		db_modify("update grupper set box1='$sag_status_tekst_liste' where art='SAGSTAT'",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
		$sag_status_liste=explode(chr(44),$r['box1']);
*/		
	} else { 
		db_modify("insert into grupper (art,box1) values ('SAGSTAT','$sag_status_tekst_liste')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
		$sag_status_liste=explode(chr(44),$r['box1']);
	}
	
	/* Query der finder ansvarlig
	---------------------------------------
	*/
	#20140610-2
	/* Udkommenteret 20140620
	$r=db_fetch_array(db_select("select ansat_id from brugere where brugernavn = '$brugernavn'",__FILE__ . " linje " . __LINE__));
	$ansat_id=$r['ansat_id']*1;
	$r=db_fetch_array(db_select("select navn from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__));
	$ansvarlig=$r['navn'];
	*/
	
	/* Ændring af farve til 'ret status'
	---------------------------------------
	*/
	($retstatus)?$retstatusstyle="color:green;":$retstatusstyle=NULL; #20140620-2
	
	// Query til ansvarlig
	$x=0;
	$q=db_select("select * from grupper where art='brgrp'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$gruppe_id[$x]=$r['id']*1;
		$beskrivelse[$x]=$r['beskrivelse'];
		$rettigheder[$x]=(substr($r['box2'],2,1)); //finder opret/ret sag rettighed
		if($rettigheder[$x]==1) $gruppeid[$x]=$gruppe_id[$x]; // finder de gruppe_id'er som har rettighed til opret/ret sag
	} 
	
	$in_str = "'".implode("', '", $gruppeid)."'"; // formatere '$gruppeid[]' til f.eks. '52','77' osv.
	
	$x=0;
	$q=db_select("select * from ansatte where konto_id=1 and gruppe IN ($in_str) order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ansvarlig[$x]=htmlspecialchars($r['navn']);
		$gruppe[$x]=$r['gruppe']; // gruppe_id fra 'grupper'
		$x++;
	}
	
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
		<div class=\"contentsoeg\">\n";
		if($asoeg=='on') {
			print "
			<form name=\"kundesoeg\" action=\"sager.php\" method=\"post\">
				<table border=\"0\" cellspacing=\"0\" width=\"828\">
					<thead>
						<tr>
							<th width=\"70\">Sagsnr</th>
							<th width=\"150\">Kunde</th>
							<th width=\"200\">Adresse</th>
							<th width=\"70\">Postnr</th>
							<th width=\"100\">By</th>
							<th width=\"80\">Ansvarlig</th>
							<th width=\"80\">Status</th>
							<th colspan=\"2\" class=\"link\"><a href=\"sager.php?funktion=sagsliste&amp;asoeg=off\">Hurtig&nbsp;søg</a></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							
							<td><input class=\"textinput ss_sagsagsnr\" type=\"text\" value=\"$ss_sagsagsnr\" id=\"ss_sagsagsnr\" name=\"ss_sagsagsnr\" tabindex=\"1\"/></td>
							<td><input class=\"textinput ss_sagfirmanavn\" type=\"text\" value=\"$ss_sagfirmanavn\" id=\"ss_sagfirmanavn\" name=\"ss_sagfirmanavn\" tabindex=\"2\" title=\"$ss_sagfirmanavn\"/></td>
							<td><input class=\"textinput ss_sagadresse\" type=\"text\" value=\"$ss_sagadresse\" id=\"ss_sagadresse\" name=\"ss_sagadresse\" tabindex=\"3\" title=\"$ss_sagadresse\"/></td>
							<td><input class=\"textinput ss_sagpostnr\" type=\"text\" value=\"$ss_sagpostnr\" id=\"ss_sagpostnr\" name=\"ss_sagpostnr\" tabindex=\"4\"/></td>
							<td><input class=\"textinput ss_sagby\" type=\"text\" value=\"$ss_sagby\" id=\"ss_sagby\" name=\"ss_sagby\" tabindex=\"5\" title=\"$ss_sagby\"/></td>
							<td><select name=\"ss_ansvarlig\" class=\"selectinputsager\" style=\"width:73px\" title=\"$ss_ansvarlig\">\n";
							for ($i=0;$i<=count($ansvarlig);$i++) {
								if ($ansvarlig[$i]==$ss_ansvarlig) print "<option value=\"$ansvarlig[$i]\">$ansvarlig[$i]&nbsp;</option>\n"; 
							}
							if (!$ansvarlig) print "<option value=\"\">&nbsp;</option>\n";
							for ($i=0;$i<count($ansvarlig);$i++) {
								if ($ansvarlig[$i]!=$ss_ansvarlig) print "<option value=\"$ansvarlig[$i]\">$ansvarlig[$i]&nbsp;</option>\n"; 
							}
							if ($ss_ansvarlig) print "<option value=\"\">&nbsp;</option>\n";
							print "</select>
							</td>
							<td><select name=\"ss_status\" class=\"selectinputsager\" style=\"width:73px\" title=\"$ss_status\">\n";
							for ($i=0;$i<=count($sag_status_liste);$i++) {
								if ($sag_status_liste[$i]==$ss_status) print "<option value=\"$sag_status_liste[$i]\">$sag_status_liste[$i]&nbsp;</option>\n"; 
							}
							if (!$sag_status_liste) print "<option value=\"\">&nbsp;</option>\n";
							for ($i=0;$i<count($sag_status_liste);$i++) {
								if ($sag_status_liste[$i]!=$ss_status) print "<option value=\"$sag_status_liste[$i]\">$sag_status_liste[$i]&nbsp;</option>\n"; 
							}
							if ($ss_status) print "<option value=\"\">&nbsp;</option>\n";
							print "</select>
							<!-- <td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"sag_id\"><input type=\"hidden\"  value=\"vis_sag\" name=\"funktion\"></td> -->  
							<td align=\"center\"><input type=\"submit\" value=\"Søg\" name=\"soeg\" class=\"button gray small\" tabindex=\"8\"></td>
							
						</tr>
					</tbody>
				</table>
				</form>\n";
			} else {
				print "
				<form name=\"kundesoeg\" action=\"sager.php\" method=\"get\">
				<table border=\"0\" cellspacing=\"0\" width=\"828\">
					<thead>
						<tr>
							<th width=\"100\">Sagsnr</th>
							<th width=\"225\">Kunde</th>
							<th width=\"385\">Opstillings adresse</th>
							<th colspan=\"2\" class=\"link\"><a href=\"sager.php?funktion=sagsliste&amp;asoeg=on\">Avanceret&nbsp;søg</a></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							
							<td><input class=\"textinput sagsagsnr\" type=\"text\" value=\"\" id=\"sagsagsnr\" name=\"sagsagsnr\" tabindex=\"1\"/></td>
							<td><input class=\"textinput sagfirmanavn\" type=\"text\" value=\"\" id=\"sagfirmanavn\" name=\"sagfirmanavn\" tabindex=\"2\"/></td>
							<td><input class=\"textinput sagadresse\" type=\"text\" value=\"\" id=\"sagadresse\" name=\"sagadresse\" tabindex=\"3\"/></td>
							<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"sag_id\"><input type=\"hidden\"  value=\"vis_sag\" name=\"funktion\"></td>   
							<td align=\"center\"><input type=\"submit\" value=\"Find sag\" name=\"findsag\" class=\"button gray small\" tabindex=\"4\"></td>
							
						</tr>
					</tbody>
				</table>
				</form>\n";
			}
			print "
			<form name=\"sagliste\" action=\"sager.php?funktion=sagsliste\" method=\"post\">
				<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;width:260px;\"><a href=\"sager.php?funktion=sagsliste&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>\n";
					($antal_sager_ialt<=500)?$display="display:none;":$display=NULL;
					print "
					<div style=\"float:right;$display\">
						<p style=\"float:left;\">Vælg antal viste linjer:&nbsp;</p>
						<select name=\"sag_limit\" class=\"selectinputloen\" style=\"width:76px;\" onchange=\"this.form.submit()\">\n";
						
							for ($i=0;$i<count($limitarray);$i++) {
									if ($sag_limit==$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
								}
								for ($i=0;$i<count($limitarray);$i++) {
									if ($sag_limit!=$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
								}
								
							print "
						</select>
					</div><!-- end of select -->
				</div>
			</form>
		</div><!-- end of contentsoeg -->\n";
		($antal_sager<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i head, under pagination
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
			//print "<p class=\"dataString\"></p>\n"; // echo af jquery status
			
		print "<div class=\"contentkundehead\">
			<ul id=\"sort\">
					<li>
							<a href=\"sager.php?funktion=sagsliste&amp;nysort=sagsnr&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:65px\">Sagsnr</a>
							<a href=\"sager.php?funktion=sagsliste&amp;nysort=firmanavn&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:205px\">Kunde</a>
							<a href=\"sager.php?funktion=sagsliste&amp;nysort=udf_addr1&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:315px\">Opstillings adresse</a>
							<a href=\"sager.php?funktion=sagsliste&amp;nysort=ref&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:145px\">Ansvarlig</a>
							<a href=\"sager.php?funktion=sagsliste&amp;nysort=status&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:75px;$retstatusstyle\">Status</a>
					</li>
			</ul>
		</div><!-- end of contentkundehead -->
		<div class=\"contentkundesager\"> 
			
			<ul id=\"things\" class=\"paging_content\">\n";
				for ($x=1;$x<=$antal_sager;$x++) {
				/*$stat = "";
						if ($status[$x]<=1) $stat = "0pmåling";
						if ($status[$x]==2) $stat = "Tilbud";
						if ($status[$x]==3) $stat = "Ordre modtaget";
						if ($status[$x]==4) $stat = "montage";
						if ($status[$x]==5) $stat = "Aflevering";
						if ($status[$x]==6) $stat = "Afmeldt";
						if ($status[$x]==7) $stat = "Demontage";
						if ($status[$x]==8) $stat = "Afsluttet";*/
						$statcolor = NULL;
						if ($status[$x]=='Beregning') $statcolor = "color:black;";
						if ($status[$x]=='Tilbud') $statcolor = "color:black;";
						if ($status[$x]=='Ordrebekræftelse') $statcolor = "color:black;";//
						if ($status[$x]=='Montage') $statcolor = "color:red;";//
						if ($status[$x]=='Godkendt') $statcolor = "color:green;";//
						if ($status[$x]=='Afmeldt') $statcolor = "color:#C1BE00;";// 
						if ($status[$x]=='Drivgods') $statcolor = "color:black;";// 
						if ($status[$x]=='Afsluttet') $statcolor = "color:black;";
					print "<li><a href=\"sager.php?funktion=vis_sag&amp;sag_id=$sag_id[$x]&amp;konto_id=$konto_id[$x]\">
						<span class=\"felt01\" style=\"width:65px;\">$sag_nr[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:205px;\" title=\"$sag_firmanavn[$x]\">$sag_firmanavn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:315px;\" title=\"$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]\">$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:145px;\" title=\"$sag_ansvarlig[$x]\">$sag_ansvarlig[$x]&nbsp;</span></a>
						<div class=\"contentkundestatussager\"><span class=\"felt05 togglelink updated_status$sag_id[$x] updated_color$sag_id[$x]\" style=\"width:75px;$statcolor\" title=\"$status[$x]\">$status[$x]&nbsp;</span>";
							if ($retstatus) { #20140610-2 #Indsættes ved begrænsning af ansatte '$sag_ansvarlig[$x]==$ansvarlig &&' 
							print "
							<div class=\"toggle\" style=\"display: none;\">
								
								<select style=\"width:95px;\" onchange=\"statussubmit($sag_id[$x])\" class=\"update_select$sag_id[$x]\" name=\"status\">\n";
								for($y=0;$y<count($sag_status_liste);$y++) { #20140610-1
									if ($status[$x]==$sag_status_liste[$y]) print "<option class=\"$color[$y] hideIt\" value=\"$sag_status_liste[$y]\">$sag_status_liste[$y]</option>\n";
								}
								for($y=0;$y<count($sag_status_liste);$y++) {
									if ($status[$x]!=$sag_status_liste[$y]) print "<option class=\"$color[$y] hideIt\" value=\"$sag_status_liste[$y]\">$sag_status_liste[$y]</option>\n";
								}
								print "</select>
								<input type=\"hidden\" name=\"sagid\" class=\"update_id$sag_id[$x]\" value=\"$sag_id[$x]\">
							</div>";
							}
							
						print "</div>\n";
					print "</li>\n";
				}
			print "</ul>
			
		</div><!-- end of contentkundesager -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of paging_container -->";
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

function opret_sag() {

	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('kontonr','firmanavn','addr1','postnr','bynavn','kontakt','tlf');
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	
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
		$_SESSION['opret_sag_kontonr']=$sortstyle[0];
		$_SESSION['opret_sag_firmanavn']=$sortstyle[1];
		$_SESSION['opret_sag_addr1']=$sortstyle[2];
		$_SESSION['opret_sag_postnr']=$sortstyle[3];
		$_SESSION['opret_sag_bynavn']=$sortstyle[4];
		$_SESSION['opret_sag_kontakt']=$sortstyle[5];
		$_SESSION['opret_sag_tlf']=$sortstyle[6];
	} else {
		$sortstyle[0]=$_SESSION['opret_sag_kontonr'];
		$sortstyle[1]=$_SESSION['opret_sag_firmanavn'];
		$sortstyle[2]=$_SESSION['opret_sag_addr1'];
		$sortstyle[3]=$_SESSION['opret_sag_postnr'];
		$sortstyle[4]=$_SESSION['opret_sag_bynavn'];
		$sortstyle[5]=$_SESSION['opret_sag_kontakt'];
		$sortstyle[6]=$_SESSION['opret_sag_tlf'];
	}
	
	if ($unsetsort) {
		unset($_SESSION['opret_sag_sort'],
					$_SESSION['opret_sag_kontonr'],$sortstyle[0],
					$_SESSION['opret_sag_firmanavn'],$sortstyle[1],
					$_SESSION['opret_sag_addr1'],$sortstyle[2],
					$_SESSION['opret_sag_postnr'],$sortstyle[3],
					$_SESSION['opret_sag_bynavn'],$sortstyle[4],
					$_SESSION['opret_sag_kontakt'],$sortstyle[5],
					$_SESSION['opret_sag_tlf'],$sortstyle[6]
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['opret_sag_sort']=$sort;
	else $sort=$_SESSION['opret_sag_sort'];
	if (!$sort) $sort="firmanavn";
	
	$sqlsort=urldecode($sort);
	
	$x=0;
	//$sort=if_isset($_GET['sort']);
	//if (!$sort) $sort='firmanavn';
	$q=db_select("SELECT * FROM adresser WHERE art='D' ORDER BY $sqlsort",__FILE__ . " linje " . __LINE__); # AND lukket != 'on' ??? virker ikke
	while ($r = db_fetch_array($q)) {
		$x++;
		$konto_id[$x]=$r['id'];
		$kontonr[$x]=$r['kontonr'];
		$firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$addr1[$x]=htmlspecialchars($r['addr1']);
		$addr2[$x]=htmlspecialchars($r['addr2']);
		$postnr[$x]=$r['postnr'];
		$bynavn[$x]=htmlspecialchars($r['bynavn']);
		$kontakt[$x]=htmlspecialchars($r['kontakt']);
		$telefon[$x]=$r['tlf'];
	}
	$antal_adresser=$x;
	/*
	print "
		<div class=\"contentsoeg\">
			<table border=\"0\" cellspacing=\"0\" width=\"778\">
				<thead>
					<tr>
						<th width=\"100\">Kontonr</th>
						<th width=\"130\">Firmanavn</th>
						<th width=\"200\">Adresse</th>
						<th width=\"230\">Fritekst</th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><input class=\"textinput\" type=\"text\" id=\"kontonr\" name=\"kontonr\" tabindex=\"1\"/></td>
						<td><input class=\"textinput\" type=\"text\" id=\"firmanavn\" name=\"firmanavn\" tabindex=\"2\"/></td>
						<td><input class=\"textinput\" type=\"text\" id=\"adresse\" name=\"adresse\" tabindex=\"3\"/></td>
						<td><input class=\"textinput\" type=\"text\" id=\"fritext\" name=\"fritext\" tabindex=\"4\"/></td>   
						<td align=\"center\"><input type=\"submit\" value=\"Find kunde\" class=\"button gray small\" tabindex=\"5\"></td>
					</tr>
				</tbody>
			</table>
		</div><!-- end of contentsoeg -->
*/
		print "<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"sager.php?funktion=ret_sag\" method=\"post\">
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
				<thead>
					<tr>
						<th width=\"100\">Kundenr</th>
						<th width=\"225\">Firmanavn</th>
						<th width=\"385\">Adresse</th>
						<th colspan=\"2\">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						
						<td><input class=\"textinput kontonr\" type=\"text\" value=\"\" id=\"kontonr\" name=\"kontonr\" tabindex=\"1\"/></td>
						<td><input class=\"textinput firmanavn\" type=\"text\" value=\"\" id=\"firmanavn\" name=\"firmanavn\" tabindex=\"2\"/></td>
						<td><input class=\"textinput firmaadresse\" type=\"text\" value=\"\" id=\"adresse\" name=\"adresse\" tabindex=\"3\"/></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"konto_id\"></td>   
						<td align=\"center\"><input type=\"submit\" value=\"Find kunde\" name=\"findkunde\" class=\"button gray small\" tabindex=\"4\"></td>
						
					</tr>
				</tbody>
			</table>
			</form>
			<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;width:200px;\"><a href=\"sager.php?funktion=opret_sag&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
					<span style=\"#text-align:center;font-size: 14px;\"><i><b>Opret ny, eller vælg eksisterende kunde til sag her!</b></i></span>
			</div>
		</div><!-- end of contentsoeg -->";
		(count($konto_id)<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.sager.js, under pagination
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>
		<div class=\"contentkundehead\">
			<ul id=\"sort\">
					<li>
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=kontonr&amp;sort=$sort&amp;nysortstyle=$sortarray[0]\" class=\"felt01 $sortstyle[0]\" style=\"width:72px\">Kundenr</a>
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=firmanavn&amp;sort=$sort&amp;nysortstyle=$sortarray[1]\" class=\"felt02 $sortstyle[1]\" style=\"width:175px\">Navn</a>
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=addr1&amp;sort=$sort&amp;nysortstyle=$sortarray[2]\" class=\"felt03 $sortstyle[2]\" style=\"width:180px\">Addresse</a>
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=postnr&amp;sort=$sort&amp;nysortstyle=$sortarray[3]\" class=\"felt04 $sortstyle[3]\" style=\"width:60px\">Postnr</a>
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=bynavn&amp;sort=$sort&amp;nysortstyle=$sortarray[4]\" class=\"felt05 $sortstyle[4]\" style=\"width:105px\">By</a>       
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=kontakt&amp;sort=$sort&amp;nysortstyle=$sortarray[5]\" class=\"felt06 $sortstyle[5]\" style=\"width:120px\">Kontaktperson</a>
							<a href=\"sager.php?funktion=opret_sag&amp;nysort=tlf&amp;sort=$sort&amp;nysortstyle=$sortarray[6]\" class=\"felt07 $sortstyle[6]\" style=\"width:85px\">Telefon</a>
					</li>
			</ul>
		</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content\">";
				for ($x=1;$x<=$antal_adresser;$x++) {
					print "<li><a href=\"sager.php?funktion=ret_sag&amp;konto_id=$konto_id[$x]&amp;sag_id=0\">
						<span class=\"felt01\" style=\"width:72px\">$kontonr[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:175px\" title=\"$firmanavn[$x]\">$firmanavn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:180px\" title=\"$addr1[$x]\">$addr1[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:60px\">$postnr[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:105px\" title=\"$bynavn[$x]\">$bynavn[$x]&nbsp;</span>	           
						<span class=\"felt06\" style=\"width:120px\" title=\"$kontakt[$x]\">$kontakt[$x]&nbsp;</span>
						<span class=\"felt07\" style=\"width:85px\">$telefon[$x]&nbsp;</span>
					</a></li>";
				}
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of pagin_content -->";
}

function ret_kunde() {

	global $charset;
	global $sprog_id;

	$loen_id = $loen_sum = $tilbud_moms = $tilbud_sum = array();

	print "<div class=\"content\">";
	include "../debitor/debkort_save.php";
	print "	<form name=\"ret_kunde\" action=\"sager.php?funktion=ret_kunde\" method=\"post\">\n";
	include "../debitor/debkort_load.php";
	print "		<table border=\"0\"><tbody><tr><td><table><tbody>";
	include "../debitor/debkort_1.php";
	print "		</tbody></table></td><td><table><tbody>";
	include "../debitor/debkort_2.php";
	print "		</tbody></table></td><td><table><tbody>";
	include "../debitor/debkort_3.php";
	print "		</tbody></table></td><tr><tr><td colspan=\"3\"><table><tbody>";
	include "../debitor/debkort_4.php";
	print "		</tbody></table></td><tr><tr><td colspan=\"3\" align = \"center\">
							<input type=\"submit\" accesskey=\"g\" value=\"Gem / opdat&eacute;r\" name=\"submit\" onclick=\"javascript:docChange = false;\">
						</td></tr></tbody></table>";
	print "	</form>";
	print "</div>";
}

function vis_sag() {
	global $brugernavn;
	global $db;

	$id=if_isset($_GET['sag_id']);
	$konto_id=if_isset($_GET['konto_id']);
	$vis_fase=if_isset($_GET['vis_fase']);
	
	if (!$id) { header("location:sager.php?funktion=sagsliste"); exit(); }

	if ($slet_bilag=if_isset($_GET['slet_bilag'])) {
	
		$x=0;
		$q = db_select("select * from bilag_tjekskema where bilag_id = '$slet_bilag'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$bilag_tjekskema_id[$x]=$r['id'];
			$bilag_tjekskema_tjekskema_id[$x]=$r['tjekskema_id'];
			$bilag_tjekskema_bilag_id[$x]=$r['bilag_id'];
			$x++;
		}
		
		if ($bilag_tjekskema_id) {
			for ($x=0;$x<count($bilag_tjekskema_id);$x++) {
				db_modify("delete from bilag_tjekskema where id = '$bilag_tjekskema_id[$x]'",__FILE__ . " linje " . __LINE__);
			}
		}
		
		$r=db_fetch_array(db_select("select filtype from bilag where id='$slet_bilag'",__FILE__ . " linje " . __LINE__));
		unlink("../bilag/$db/$id/$slet_bilag.$r[filtype]");
		db_modify("delete from bilag where id = $slet_bilag",__FILE__ . " linje " . __LINE__);
	}
	
	if ($unlink_notat=if_isset($_GET['unlink_notat'])) {
		db_modify("update noter set assign_id='0',sagsnr=NULL where id='$unlink_notat'",__FILE__ . " linje " . __LINE__);
	}
	
	if (!$id && $konto_id) {
		$tidspkt=date('U');
		$r=db_fetch_array(db_select("select max(sagsnr) as sagsnr from sager",__FILE__ . " linje " . __LINE__));
		$sagsnr=$r['sagsnr']+1;
	
		if($r=db_fetch_array(db_select("select * from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__))) {
			db_modify("insert into sager(sagsnr,konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,kontakt,status,tidspkt,hvem,oprettet_af) values ('$sagsnr','$konto_id','$r[kontonr]','$r[firmanavn]','$r[addr1]','$r[addr2]','$r[postnr]','$r[bynavn]','$r[kontakt]','0','$tidspkt','$brugernavn','$brugernavn')",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from sager where tidspkt='$tidspkt' and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
	}

	$r=db_fetch_array(db_select("select * from sager where id='$id'",__FILE__ . " linje " . __LINE__));
	$sagsnr=$r['sagsnr']*1;
	$konto_id=$r['konto_id'];
	$kontonr=$r['kontonr'];
	$firmanavn=htmlspecialchars($r['firmanavn']);
	$addr1=htmlspecialchars($r['addr1']);
	$addr2=htmlspecialchars($r['addr2']);
	$postnr=$r['postnr'];
	$bynavn=htmlspecialchars($r['bynavn']);
	$kontakt=htmlspecialchars($r['kontakt']);
	$beskrivelse=htmlspecialchars($r['beskrivelse']);
	$omfang=htmlspecialchars($r['omfang']);
	(!$omfang)?$omfang='<i>Ingen beskrivelse</i>':$omfang;
	$udf_firmanavn=htmlspecialchars($r['udf_firmanavn']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$ref=htmlspecialchars($r['ref']);
	(!$ref)?$ref='<i>Ingen ansvarlig</i>':$ref;
	$oprettet_af=htmlspecialchars($r['oprettet_af']);
	$dato=date("d-m-y",$r['tidspkt']);
	$tid=date("H:i",$r['tidspkt']);
	$status=$r['status'];
	//if (!$status) $status=1;
	$statcolor = NULL;
	if ($status=='Tilbud') $statcolor = "color:black;";
	if ($status=='Ordrebekræftelse') $statcolor = "color:black;";
	if ($status=='Montage') $statcolor = "color:red;";
	if ($status=='Godkendt') $statcolor = "color:green;";
	if ($status=='Afmeldt') $statcolor = "color:#C1BE00;";
	if ($status=='Afsluttet') $statcolor = "color:black;";
	
	// query til kunde-info i hoved
	$r=db_fetch_array(db_select("select * from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
	$k_firmanavn=htmlspecialchars($r['firmanavn']);
	$k_addr1=htmlspecialchars($r['addr1']);
	$k_addr2=htmlspecialchars($r['addr2']);
	$k_postnr=$r['postnr'];
	$k_bynavn=htmlspecialchars($r['bynavn']);
	$k_kontonr=$r['kontonr'];
	$kunde_tlf=$r['tlf'];
	$kunde_email=$r['email'];
	(!$kunde_tlf)?$kunde_tlf='<i>Ingen telefonnummer</i>':$kunde_tlf;
	(!$kunde_email)?$kunde_email='<i>Ingen emailadresse</i>':$kunde_email;
	
	// Her opdater jeg kunde-oplysninger hvis der er ændringer #20160926
	if ($firmanavn != $k_firmanavn || $addr1 != $k_addr1 || $addr2 != $k_addr2 || $postnr != $k_postnr || $bynavn != $k_bynavn || $kontonr != $k_kontonr) {
		db_modify("update sager set firmanavn='$k_firmanavn',addr1='$k_addr1',addr2='$k_addr2',postnr='$k_postnr',bynavn='$k_bynavn',kontonr='$k_kontonr' where id='$id'",__FILE__ . " linje " . __LINE__);
		$firmanavn = $k_firmanavn;
	}
	
	// query til kunde-ansatte i hoved
	$r=db_fetch_array(db_select("select * from ansatte where konto_id='$konto_id' and navn='$kontakt'",__FILE__ . " linje " . __LINE__));
	$ansatte_id=$r['id'];
	$ansatte_navn=$r['navn'];
	$ansatte_tlf=$r['mobil'];
	$ansatte_email=$r['email'];
	(!$ansatte_tlf)?$ansatte_tlf='<i>Ingen telefonnummer</i>':$ansatte_tlf;
	(!$ansatte_email)?$ansatte_email='<i>Ingen emailadresse</i>':$ansatte_email;
	
	$x=0;
	$q=db_select("select * from opgaver where assign_id='$id' order by nr",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$opgave_id[$x]=$r['id'];
		$opgave_nr[$x]=$r['nr'];
		$opgave_status[$x]=$r['status'];
		$opgave_beskrivelse[$x]=$r['beskrivelse'];
		$opgave_omfang[$x]=$r['omfang'];
		$opgave_udf_firmanavn[$x]=$r['udf_firmanavn'];
		$opgave_ref[$x]=$r['ref'];
		$opgave_oprettet_af[$x]=$r['oprettet_af'];
		$opgave_dato[$x]=date("d-m-y",$r['tidspkt']);
		$opgave_tid[$x]=date("H:i",$r['tidspkt']);
		$x++;	
	}
	
	$x=0;
	$q=db_select("select * from bilag where assign_to='sager' and assign_id='$id' order by datotid asc",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$bilag_id[$x]=$r['id'];
		$bilag_sub_id[$x]=$r['sub_id'];
		$bilag_title[$x]=$r['navn'];
		$tmp=utf8_decode($r['navn']);
		//if (strlen($tmp)>17) $tmp=substr($tmp,0,17)."...";
		$bilag_navn[$x]=utf8_encode($tmp);
		$bilag_filtype[$x]=$r['filtype'];
		$bilag_kategori[$x]=$r['kategori'];
		$bilag_beskrivelse[$x]=$r['beskrivelse'];
		$bilag_dato[$x]=date("d-m-Y",$r['datotid']);
		$bilag_tidspkt[$x]=date("H:i",$r['datotid']);
		$bilag_datotid[$x]=$r['datotid'];
		$bilag_fase[$x]=$r['fase']*1;
		$bilag_hvem[$x]=$r['hvem'];
		$bilag_bilag_fase[$x]=$r['bilag_fase'];
#		if (!file_exists("../bilag/$db/$id/$bilag_id[$x].$bilag_filtype[$x]")) $x--; 
		$x++;
	}

	$x=0;
	$q=db_select("select * from noter where assign_to='sager' and assign_id='$id' order by datotid asc",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$notat_id[$x]=$r['id'];
		$notat_sub_id[$x]=$r['sub_id'];
		list($notat_notat[$x],$tmp)=explode("\n",$r['notat'],2);
		$notat_title[$x]=$r['beskrivelse'];
		$tmp=utf8_decode($r['beskrivelse']);
		//if (strlen($tmp)>20) $tmp=substr($tmp,0,20)."...";
		$notat_beskrivelse[$x]=utf8_encode($tmp);
		$notat_overskrift[$x]=$r['overskrift'];
		$notat_dato[$x]=date("d-m-Y",$r['datotid']);
		$notat_tidspkt[$x]=date("H:i",$r['datotid']);
		$notat_datotid[$x]=$r['datotid'];
		$notat_fase[$x]=$r['fase']*1;
		$notat_status[$x]=$r['status'];
		$notat_hvem[$x]=$r['hvem'];
		$notat_notat_fase[$x]=$r['notat_fase'];
		$notat_kategori[$x]=$r['kategori'];
		$x++;
	}

	$x=0;
	$q = db_select("select * from tjekskema where sag_id = '$id' order by id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kontrol_id[$x]=$r['id'];
		$kontrol_tjek_id[$x]=$r['tjekliste_id'];
		$kontrol_dato[$x]=date("d-m-Y",$r['datotid']);
		$kontrol_tidspkt[$x]=date("H:i",$r['datotid']);
		$kontrol_opg_art[$x]=$r['opg_art'];
		$kontrol_opg_navn[$x]=$r['opg_navn'];
		$kontrol_sjak[$x]=$r['sjak'];
		$kontrol_ref[$x]=$r['hvem'];
		$x++;
	}
	/*
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=$r['tjekpunkt']; 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
*/
	$x=0;
	$q = db_select("select * from opgaver where assign_to = 'sager' and assign_id = '$id' order by nr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$opgave_id[$x]=$r['id'];
		$opgave_nr[$x]=$r['nr'];
		$opgave_status[$x]=$r['status'];
		if ($opgave_status[$x]=='Tilbud') $opgcolor[$x] = "color:black;";
		if ($opgave_status[$x]=='Ordrebekræftelse') $opgcolor[$x] = "color:black;";
		if ($opgave_status[$x]=='Montage') $opgcolor[$x] = "color:red;";
		if ($opgave_status[$x]=='Godkendt') $opgcolor[$x] = "color:green;";
		if ($opgave_status[$x]=='Afmeldt') $opgcolor[$x] = "color:#C1BE00;";
		if ($opgave_status[$x]=='Afsluttet') $opgcolor[$x] = "color:black;";
		$opgave_beskrivelse[$x]=htmlspecialchars($r['beskrivelse']);
		$opgave_oprettet_af[$x]=$r['oprettet_af'];
		$x++;
	}
	
	
	
	/*
	$x=0;
	$q = db_select("select * from tilbud where sag_id = '$id' and opgave_id = '0' order by id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tilbud_id[$x]=$r['id'];
		$tilbud_nr[$x]=$r['tilbudnr'];
		$tilbud_beskrivelse[$x]=$r['beskrivelse'];
		$tilbud_tekst[$x]=$r['tekst'];
		$tilbud_dato[$x]=date("d-m-Y",$r['datotid']);
		$tilbud_tidspkt[$x]=date("H:i",$r['datotid']);
		$tilbud_hvem[$x]=$r['hvem'];
		$x++;
	}
	*/
	
	// Query til Tilbud
	$x=0;
	$q = db_select("select * from ordrer where sag_id = '$id' and status <= '2' and (art = 'DO' or art = 'DK') order by datotid",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ordrer_id[$x]=$r['id'];
		$ordrer_konto_id[$x]=$r['konto_id'];
		$ordrer_tilbudnr[$x]=$r['tilbudnr'];
		$ordrer_ordrenr[$x]=$r['ordrenr'];
		$ordrer_nr[$x]=$r['nr'];
		$ordrer_dato[$x]=date("d-m-Y",$r['datotid']);
		$ordrer_tidspkt[$x]=date("H:i",$r['datotid']);
		$ordrer_ref[$x]=$r['ref'];
		$ordrer_art[$x]=$r['art'];
		$ordrer_status[$x]=$r['status'];
		$ordrer_kred_id[$x]=$r['kred_ord_id'];
		if ($ordrer_status[$x] == '0') $opgstatus[$x] = "Tilbud";
		if ($ordrer_status[$x] == '1') $opgstatus[$x] = "Ordrebekræftelse";
		if ($ordrer_status[$x] == '2') $opgstatus[$x] = "Levering";
		if ($ordrer_status[$x] == '0' && $ordrer_kred_id[$x] > '0') $opgstatus[$x] = "Kreditnota";
		if ($ordrer_status[$x] == '1' && $ordrer_kred_id[$x] > '0') $opgstatus[$x] = "Kreditnota bekræftelse";
		if ($ordrer_status[$x] == '2' && $ordrer_kred_id[$x] > '0') $opgstatus[$x] = "Kreditnota modtag";
		//if ($ordrer_art[$x] == 'OT' && $ordrer_status[$x] == '0') {$opgstatus[$x] = "Original tilbud";$ordrer_color[$x] = "color:black;";}
		$x++;
	}
	
	// Query til original tilbud
	$x=0;
	$q = db_select("select * from ordrer where sag_id = '$id' and status <= '2' and art = 'OT' order by id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ot_id[$x]=$r['id'];
		$ot_konto_id[$x]=$r['konto_id'];
		$ot_tilbudnr[$x]=$r['tilbudnr'];
		$ot_ordrenr[$x]=$r['ordrenr'];
		$ot_nr[$x]=$r['nr'];
		$ot_dato[$x]=date("d-m-Y",$r['datotid']);
		$ot_tidspkt[$x]=date("H:i",$r['datotid']);
		$ot_ref[$x]=$r['ref'];
		$ot_status[$x]=$r['status'];
		if ($ot_status[$x] == '0') $otstatus[$x] = "Tilbud";
		$x++;
	}
	
	// Query til Faktura
	$x=0;
	$q = db_select("select * from ordrer where sag_id = '$id' and status >= '3' and kred_ord_id is NULL order by fakturanr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$faktura_id[$x]=$r['id'];
		$faktura_konto_id[$x]=$r['konto_id'];
		$faktura_tilbudnr[$x]=$r['tilbudnr'];
		$faktura_fakturanr[$x]=$r['fakturanr'];
		$faktura_ordrenr[$x]=$r['ordrenr'];
		$faktura_tilbud_nr[$x]=$r['nr'];
		$fakturadate[$x]=$r['fakturadate'];
		$faktura_dato[$x] = date("d-m-Y", strtotime($fakturadate[$x]));
		//$faktura_dato[$x]=date("d-m-Y",$r['datotid']);
		//$faktura_tidspkt[$x]=date("H:i",$r['datotid']);
		$faktura_ref[$x]=$r['ref'];
		$faktura_status[$x]=$r['status'];
		//if ($ordrer_status[$x] == '0') $opgstatus[$x] = "Tilbud";
		//if ($ordrer_status[$x] == '1') $opgstatus[$x] = "Ordrebekræftelse";
		//if ($ordrer_status[$x] == '2') $opgstatus[$x] = "Levering";
		if ($faktura_status[$x] >= '3') $faktstatus[$x] = "Fakture";
		$x++;
	}
	
	// Query til kreditnota
	$x=0;
	$q = db_select("select * from ordrer where sag_id = '$id' and status >= '3' and kred_ord_id >= '1' order by fakturanr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$kreditnota_id[$x]=$r['id'];
		$kreditnota_tilbudnr[$x]=$r['tilbudnr'];
		$kreditnota_fakturanr[$x]=$r['fakturanr'];
		$kreditnota_ordrenr[$x]=$r['ordrenr'];
		$kreditnota_tilbud_nr[$x]=$r['nr'];
		$kreditnotadate[$x]=$r['fakturadate'];
		$kreditnota_dato[$x] = date("d-m-Y", strtotime($kreditnotadate[$x]));
		//$faktura_dato[$x]=date("d-m-Y",$r['datotid']);
		//$faktura_tidspkt[$x]=date("H:i",$r['datotid']);
		$kreditnota_ref[$x]=$r['ref'];
		$kreditnota_status[$x]=$r['status'];
		//if ($ordrer_status[$x] == '0') $opgstatus[$x] = "Tilbud";
		//if ($ordrer_status[$x] == '1') $opgstatus[$x] = "Ordrebekræftelse";
		//if ($ordrer_status[$x] == '2') $opgstatus[$x] = "Levering";
		if ($kreditnota_status[$x] >= '3') $kreditnotastatus[$x] = "Kreditnota";
		$x++;
	}
	
	/* Sagens omkostninger     
	---------------------------------------------------------------------------------------------------------------------
	*/
	// Query til lønudgifter
	$loen_id[0] = $loen_sum[0] = $x = 0;
	$qtxt = "select * from loen where sag_id = '$id' and godkendt >= '1' and art != 'akktimer'";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$loen_id[$x]=$r['id'];
		$loen_sum[$x]=$r['sum'];
		$x++;
	}
	$lonsum = '0';
	(array_sum($loen_sum))?$lonsum = array_sum($loen_sum):$lonsum = '0';
	// Query til tilbudssum ialt
	if (db_fetch_array(db_select("select * from ordrer where sag_id = '$id' and status = '0' and art = 'OT'",__FILE__ . " linje " . __LINE__))) {
		$x=0;
		$q = db_select("select * from ordrer where sag_id = '$id' and status = '0' and art = 'OT'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$tilbudid[$x]=$r['id'];
			$tilbud_sum[$x]=$r['sum'];
			$tilbud_moms[$x]=$r['moms'];
			$x++;
		}
	} else {
		$tilbud_moms[0] = $tilbud_sum[0] = $x = 0;
		$q = db_select("select * from ordrer where sag_id = '$id' and status = '0' and (art = 'DO' or art = 'DK')",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$tilbudid[$x]=$r['id'];
			$tilbud_sum[$x]=$r['sum'];
			$tilbud_moms[$x]=$r['moms'];
			$x++;
		}
	}
	(array_sum($tilbud_moms))?$tilbudmoms = array_sum($tilbud_moms):$tilbudmoms='0';
	(array_sum($tilbud_sum))?$tilbudssum = array_sum($tilbud_sum):$tilbudssum='0';
	
	// Query til tilbudssum på enkelte varer
	if (db_fetch_array(db_select("select * from ordrer where sag_id = '$id' and status = '0' and art = 'OT'",__FILE__ . " linje " . __LINE__))) {
		$x=0;
		$q = db_select("SELECT ordrelinjer.id as ordrelinjer_id,ordrelinjer.ordre_id as ordrelinjer_ordre_id,ordrelinjer.varenr as ordrelinjer_varenr,ordrelinjer.pris as ordrelinjer_pris,ordrelinjer.antal as ordrelinjer_antal,ordrelinjer.procent as ordrelinjer_procent,ordrelinjer.vare_id as ordrelinjer_vare_id,varer.id as varer_id,varer.varenr as varer_varenr,varer.prisgruppe as varer_prisgruppe,varer.beskrivelse as varer_beskrivelse,grupper.id as grupper_id,grupper.beskrivelse as grupper_beskrivelse,grupper.kodenr as grupper_kodenr,grupper.art as grupper_art,ordrer.id as ordrer_id,ordrer.sag_id as ordrer_sag_id,ordrer.status as ordrer_status,ordrer.art as ordrer_art,ordrer.procenttillag as ordrer_procenttillag FROM ordrelinjer 
									INNER JOIN varer ON ordrelinjer.vare_id = varer.id
									INNER JOIN grupper ON varer.prisgruppe = grupper.kodenr::int4
									INNER JOIN ordrer ON ordrelinjer.ordre_id = ordrer.id
									WHERE ordrer.sag_id = '$id' AND ordrer.status = '0' AND ordrer.art = 'OT' AND grupper.art = 'VPG' ORDER BY grupper.kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ordrelinjer_tilbud_id[$x]=$r['ordrelinjer_id'];
			$ordrelinjer_tilbud_ordre_id[$x]=$r['ordrelinjer_ordre_id'];
			$ordrelinjer_tilbud_varenr[$x]=$r['ordrelinjer_varenr'];
			$ordrelinjer_tilbud_pris[$x]=$r['ordrelinjer_pris'];
			$ordrelinjer_tilbud_antal[$x]=$r['ordrelinjer_antal'];
			$ordrelinjer_tilbud_procent[$x]=$r['ordrelinjer_procent'];
			$ordrelinjer_tilbud_vare_id[$x]=$r['ordrelinjer_vare_id'];
			$varer_tilbud_id[$x]=$r['varer_id'];
			$varer_tilbud_varenr[$x]=$r['varer_varenr'];
			$varer_tilbud_prisgruppe[$x]=$r['varer_prisgruppe'];
			$varer_tilbud_beskrivelse[$x]=$r['varer_beskrivelse'];
			$grupper_tilbud_id[$x]=$r['grupper_id'];
			$grupper_tilbud_beskrivelse[$x]=$r['grupper_beskrivelse'];
			$grupper_tilbud_kodenr[$x]=$r['grupper_kodenr'];
			$grupper_tilbud_art[$x]=$r['grupper_art'];
			$ordrer_tilbud_id[$x]=$r['ordrer_id'];
			$ordrer_tilbud_sag_id[$x]=$r['ordrer_sag_id'];
			$ordrer_tilbud_status[$x]=$r['ordrer_status'];
			$ordrer_tilbud_art[$x]=$r['ordrer_art'];
			$ordrer_tilbud_procenttillag[$x]=$r['ordrer_procenttillag'];
			$x++;
		}
		$antal_tilbud=$x;
		
		// Her oprettes en midlertidig database til at opsamle alle tilbud-ordreliner
		db_modify("CREATE TEMPORARY TABLE temp_tilbud (id serial NOT NULL,sag_id integer,beskrivelse text,gruppe integer,pris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		
		$pris_tilbud = array(); // opretter et nyt array til samlet tilbudpris for ordrelinje
		$procenttillag_tilbud = array(); // opretter et nyt array til samlet procenttillæg for ordrelinje
		
		for ($y=0;$y<$antal_tilbud;$y++) {
			// samlet pris for ordrelinje bliver beregnet, og sat ind i den midlertidig database 
			$pris_tilbud[$y] = ($ordrelinjer_tilbud_antal[$y] * $ordrelinjer_tilbud_pris[$y] * $ordrelinjer_tilbud_procent[$y]/100);
			// procenttillæg for ordrelinje bliver beregnet
			$procenttillag_tilbud[$y] = ($pris_tilbud[$y] * $ordrer_tilbud_procenttillag[$y]/100);
			
			db_modify("INSERT INTO temp_tilbud (sag_id,beskrivelse,gruppe,pris) VALUES ('$id','$grupper_tilbud_beskrivelse[$y]','$varer_tilbud_prisgruppe[$y]','$pris_tilbud[$y]')",__FILE__ . " linje " . __LINE__);
			
		}
		
		(array_sum($procenttillag_tilbud))?$allrisk = array_sum($procenttillag_tilbud):$allrisk='0';
		
	} else {
		$x=0;
		$q = db_select("SELECT ordrelinjer.id as ordrelinjer_id,ordrelinjer.ordre_id as ordrelinjer_ordre_id,ordrelinjer.varenr as ordrelinjer_varenr,ordrelinjer.pris as ordrelinjer_pris,ordrelinjer.antal as ordrelinjer_antal,ordrelinjer.procent as ordrelinjer_procent,ordrelinjer.vare_id as ordrelinjer_vare_id,varer.id as varer_id,varer.varenr as varer_varenr,varer.prisgruppe as varer_prisgruppe,varer.beskrivelse as varer_beskrivelse,grupper.id as grupper_id,grupper.beskrivelse as grupper_beskrivelse,grupper.kodenr as grupper_kodenr,grupper.art as grupper_art,ordrer.id as ordrer_id,ordrer.sag_id as ordrer_sag_id,ordrer.status as ordrer_status,ordrer.art as ordrer_art,ordrer.procenttillag as ordrer_procenttillag FROM ordrelinjer 
									INNER JOIN varer ON ordrelinjer.vare_id = varer.id
									INNER JOIN grupper ON varer.prisgruppe = grupper.kodenr::int4
									INNER JOIN ordrer ON ordrelinjer.ordre_id = ordrer.id
									WHERE ordrer.sag_id = '$id' AND ordrer.status = '0' AND (ordrer.art = 'DO' or ordrer.art = 'DK') AND grupper.art = 'VPG' ORDER BY grupper.kodenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$ordrelinjer_tilbud_id[$x]=$r['ordrelinjer_id'];
			$ordrelinjer_tilbud_ordre_id[$x]=$r['ordrelinjer_ordre_id'];
			$ordrelinjer_tilbud_varenr[$x]=$r['ordrelinjer_varenr'];
			$ordrelinjer_tilbud_pris[$x]=$r['ordrelinjer_pris'];
			$ordrelinjer_tilbud_antal[$x]=$r['ordrelinjer_antal'];
			$ordrelinjer_tilbud_procent[$x]=$r['ordrelinjer_procent'];
			$ordrelinjer_tilbud_vare_id[$x]=$r['ordrelinjer_vare_id'];
			$varer_tilbud_id[$x]=$r['varer_id'];
			$varer_tilbud_varenr[$x]=$r['varer_varenr'];
			$varer_tilbud_prisgruppe[$x]=$r['varer_prisgruppe'];
			$varer_tilbud_beskrivelse[$x]=$r['varer_beskrivelse'];
			$grupper_tilbud_id[$x]=$r['grupper_id'];
			$grupper_tilbud_beskrivelse[$x]=$r['grupper_beskrivelse'];
			$grupper_tilbud_kodenr[$x]=$r['grupper_kodenr'];
			$grupper_tilbud_art[$x]=$r['grupper_art'];
			$ordrer_tilbud_id[$x]=$r['ordrer_id'];
			$ordrer_tilbud_sag_id[$x]=$r['ordrer_sag_id'];
			$ordrer_tilbud_status[$x]=$r['ordrer_status'];
			$ordrer_tilbud_art[$x]=$r['ordrer_art'];
			$ordrer_tilbud_procenttillag[$x]=$r['ordrer_procenttillag'];
			$x++;
		}
		$antal_tilbud=$x;
		
		// Her oprettes en midlertidig database til at opsamle alle tilbud-ordreliner
		db_modify("CREATE TEMPORARY TABLE temp_tilbud (id serial NOT NULL,sag_id integer,beskrivelse text,gruppe integer,pris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
		
		$pris_tilbud = array(); // opretter et nyt array til samlet tilbudpris for ordrelinje
		$procenttillag_tilbud = array(); // opretter et nyt array til samlet procenttillæg for ordrelinje
		
		for ($y=0;$y<$antal_tilbud;$y++) {
			// samlet pris for ordrelinje bliver beregnet, og sat ind i den midlertidig database 
			$pris_tilbud[$y] = ($ordrelinjer_tilbud_antal[$y] * $ordrelinjer_tilbud_pris[$y] * $ordrelinjer_tilbud_procent[$y]/100);
			// procenttillæg for ordrelinje bliver beregnet
			$procenttillag_tilbud[$y] = ($pris_tilbud[$y] * $ordrer_tilbud_procenttillag[$y]/100);
			
			db_modify("INSERT INTO temp_tilbud (sag_id,beskrivelse,gruppe,pris) VALUES ('$id','$grupper_tilbud_beskrivelse[$y]','$varer_tilbud_prisgruppe[$y]','$pris_tilbud[$y]')",__FILE__ . " linje " . __LINE__);
			
		}
		
		(array_sum($procenttillag_tilbud))?$allrisk = array_sum($procenttillag_tilbud):$allrisk='0';
		
	}
	
	// Her er visning af tilbud efter gruppering af sum og beskrivelse 
	$temp_tilbud_pris[0] = $x = 0;
	$q = db_select("SELECT gruppe,sum(pris) AS pris,beskrivelse FROM temp_tilbud WHERE sag_id = '$id' GROUP BY gruppe,beskrivelse ORDER BY gruppe",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$temp_tilbud_gruppe[$x]=$r['gruppe'];
		$temp_tilbud_pris[$x]=$r['pris'];
		$temp_tilbud_beskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}
	$temp_tilbud_antal=$x;
	
	(array_sum($temp_tilbud_pris))?$tilbud_pris = array_sum($temp_tilbud_pris):$tilbud_pris='0';
	
	
	// Query til fakturasum ialt
	$faktura_sum[0] = $x = 0;
	$q = db_select("select * from ordrer where sag_id = '$id' and status >= '3'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$fakturaid[$x]=$r['id'];
		$faktura_sum[$x]=$r['sum'];
		$x++;
	}
	(array_sum($faktura_sum))?$fakturasum = array_sum($faktura_sum):$fakturasum='0';
	
	// Query til faktura på enkelte varer
	$x=0;
	$q = db_select("SELECT ordrelinjer.id as ordrelinjer_id,ordrelinjer.ordre_id as ordrelinjer_ordre_id,ordrelinjer.varenr as ordrelinjer_varenr,ordrelinjer.pris as ordrelinjer_pris,ordrelinjer.antal as ordrelinjer_antal,ordrelinjer.procent as ordrelinjer_procent,ordrelinjer.vare_id as ordrelinjer_vare_id,varer.id as varer_id,varer.varenr as varer_varenr,varer.prisgruppe as varer_prisgruppe,varer.beskrivelse as varer_beskrivelse,grupper.id as grupper_id,grupper.beskrivelse as grupper_beskrivelse,grupper.kodenr as grupper_kodenr,grupper.art as grupper_art,ordrer.id as ordrer_id,ordrer.sag_id as ordrer_sag_id,ordrer.status as ordrer_status FROM ordrelinjer 
								INNER JOIN varer ON ordrelinjer.vare_id = varer.id
								INNER JOIN grupper ON varer.prisgruppe = grupper.kodenr::int4
								INNER JOIN ordrer ON ordrelinjer.ordre_id = ordrer.id
								WHERE ordrer.sag_id = '$id' AND ordrer.status >= '3' AND grupper.art = 'VPG' ORDER BY grupper.kodenr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$ordrelinjer_id[$x]=$r['ordrelinjer_id'];
		$ordrelinjer_ordre_id[$x]=$r['ordrelinjer_ordre_id'];
		$ordrelinjer_varenr[$x]=$r['ordrelinjer_varenr'];
		$ordrelinjer_pris[$x]=$r['ordrelinjer_pris'];
		$ordrelinjer_antal[$x]=$r['ordrelinjer_antal'];
		$ordrelinjer_procent[$x]=$r['ordrelinjer_procent'];
		$ordrelinjer_vare_id[$x]=$r['ordrelinjer_vare_id'];
		$varer_id[$x]=$r['varer_id'];
		$varer_varenr[$x]=$r['varer_varenr'];
		$varer_prisgruppe[$x]=$r['varer_prisgruppe'];
		$varer_beskrivelse[$x]=$r['varer_beskrivelse'];
		$grupper_id[$x]=$r['grupper_id'];
		$grupper_beskrivelse[$x]=$r['grupper_beskrivelse'];
		$grupper_kodenr[$x]=$r['grupper_kodenr'];
		$grupper_art[$x]=$r['grupper_art'];
		//$ordrer_id[$x]=$r['ordrer_id'];
		$ordrer_sag_id[$x]=$r['ordrer_sag_id'];
		$ordrer_status[$x]=$r['ordrer_status'];
		$x++;
	}
	$antal_faktura=$x;
	
	// Her oprettes en midlertidig database til at opsamle alle fatura-ordreliner
	db_modify("CREATE TEMPORARY TABLE temp_faktura (id serial NOT NULL,sag_id integer,beskrivelse text,gruppe integer,pris numeric(15,3),PRIMARY KEY (id))",__FILE__ . " linje " . __LINE__);
	
	/*
	print_r($ordrelinjer_pris); echo "<br>";
	echo array_sum($faktura_sum); echo "<br>";
	print_r($varer_prisgruppe); echo "<br>";
	print_r($grupper_beskrivelse); echo "<br><br>";
	*/
	/*
	print "<table border=\"1\" style=\"width:100%\">
						<tr>
							<th>Sag id</th>
							<th>Beskrivelse</th>
							<th>Gruppe</th> 
							<th>Antal</th>
							<th>Procent</th>
							<th>Ordrelinje pris</th>
							<th>Samlet pris</th>
						</tr>";
						*/
	$pris = array(); // opretter et nyt array til samlet pris for ordrelinje
	
	for ($y=0;$y<$antal_faktura;$y++) {
		// samlet pris for ordrelinje bliver beregnet, og sat ind i den midlertidig database 
		$pris[$y] = ($ordrelinjer_antal[$y] * $ordrelinjer_pris[$y] * $ordrelinjer_procent[$y]/100);
		
		db_modify("INSERT INTO temp_faktura (sag_id,beskrivelse,gruppe,pris) VALUES ('$id','$grupper_beskrivelse[$y]','$varer_prisgruppe[$y]','$pris[$y]')",__FILE__ . " linje " . __LINE__);
		
		
		//print "$grupper_beskrivelse[$y] | $varer_prisgruppe[$y] | $ordrelinjer_pris[$y] | $pris[$y] : $y<br>";
		/*
		print "<tr>
							<td>$id</td>
							<td>$grupper_beskrivelse[$y]</td>
							<td>$varer_prisgruppe[$y]</td>
							<td>$ordrelinjer_antal[$y]</td>
							<td>$ordrelinjer_procent[$y]</td>
							<td>$ordrelinjer_pris[$y]</td>
							<td>$pris[$y]</td>
						</tr>";
					*/
	}
	/*
	print "<tr><td colspan=\"6\">I alt</td><td>".array_sum($pris)."</td></tr>";
	print "<tr><td colspan=\"6\">Samlet sum fra ordrer.php</td><td>$fakturasum</td></tr>";
	print "</table>";
	
	echo "<br>";
	*/
	// Her er visning af faktura efter gruppering af sum og beskrivelse 
	$x=0;
	$q = db_select("SELECT gruppe,sum(pris) AS pris,beskrivelse FROM temp_faktura WHERE sag_id = '$id' GROUP BY gruppe,beskrivelse ORDER BY gruppe",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$temp_gruppe[$x]=$r['gruppe'];
		$temp_pris[$x]=$r['pris'];
		$temp_beskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}
	$temp_antal=$x;
	
	
	
	
	
	if (!$udf_addr1||!$udf_postnr||!$udf_bynavn) $ret_sag=1;
	print "<div class=\"content\">\n";
	print "<div class=\"vissaghoved\"><a href=\"sager.php?konto_id=$konto_id&amp;sag_id=$id&amp;funktion=ret_sag\" title=\"Ret oplysninger og status til sagen her!\"><span class=\"tableSagerEllipsis\" style=\"width:828px;\">Sag: $sagsnr - $beskrivelse - $udf_addr1, $udf_postnr $udf_bynavn</span></a></div>\n";
	print "<!--<hr>-->
	<table border=\"0\" cellspacing=\"0\" width=\"828\" class=\"tableSager\">
	<colgroup>
    <col width=\"124\">
    <col width=\"224\">
		<col width=\"100\">
		<col width=\"100\">
    <col width=\"80\">
		<col width=\"80\">
		<col width=\"84\">
    <col width=\"18\">
		<col width=\"18\">
  </colgroup>
  <tbody>
		<tr style=\"height:0px;padding:0px;margin:0px;\">
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
		</tr>
  </tbody>
	<tbody>
	<tr>
		<td><p><b>Kunde:</b></p></td>
		<td colspan=\"5\"><h4 style=\"color:#111;\">$firmanavn</h4></td>
		<td colspan=\"3\"><span class=\"floatright\"><a class=\"button gray small\" title=\"Gå til kundekortet her!\" href=\"kunder.php?funktion=ret_kunde&amp;konto_id=$konto_id&amp;sag_id=$id\">Til kunde</a></span></td>
	</tr>\n";
	if ($ansatte_id) {
	print "
	<tr>
		<td>&nbsp;</td>
		<td><p><b>Kontakt:</b> $ansatte_navn</p></td>
		<td colspan=\"2\"><p><b>Tlf:</b> $ansatte_tlf</p></td>
		<td colspan=\"5\"><p><b>Email:</b> $ansatte_email</p></td>
	</tr>\n";
	} else {
	print "
	<tr>
		<td>&nbsp;</td>
		<td colspan=\"1\"><p><b>Tlf:</b> $kunde_tlf</p></td>
		<td colspan=\"7\"><p><b>Email:</b> $kunde_email</p></td>
	</tr>\n";
	}
	print "
	<!--<tr class=\"sagheader\"><td colspan=\"9\"><hr></td></tr>-->
	<tr style=\"background-color:#f2f2f2;\">
		<td valign=\"top\"><p style=\"margin:10px 0 10px 0;\"><b>Generelt for sagen:</b></p></td>
		<td colspan=\"8\"><p style=\"margin:10px 0 10px 0;\">$omfang</p></td>
	</tr>
	<!--<tr class=\"sagheader\"><td colspan=\"9\"><hr></td></tr>-->
	<tr>
		<td><p><b>Ansvarlig:</b></p></td>
		<td colspan=\"4\"><p>$ref&nbsp;</p></td>
		<td rowspan=\"2\"><p><b>Status:</b></p></td>
		<td rowspan=\"2\" colspan=\"3\"><p style=\"$statcolor\"><b>$status&nbsp;</b></p></td>
	</tr>
	<tr>
		<td><p><b>Indtastet af:</b></p></td>
		<td><p>$oprettet_af&nbsp;</p></td>
		<td colspan=\"3\"><p><b>Den:</b> $dato <b>kl.</b> $tid</p></td>
		
	</tr>\n";
	
	// Her skal sagens omkostninger vises....
	$lonsumsocial = ($lonsum*35/100)+$lonsum; // lønsum plus de sociale, som er 35% 
	$tilbudtotalsum = $tilbudssum+$tilbudmoms; // tilbudssum + moms
	$tilbudspris = "<span style=\"color:black;#font-size:13px;\">".dkdecimal($tilbudssum)."</span>";
	$tilbudsprismoms = "<span style=\"color:black;#font-size:13px;\">".dkdecimal($tilbudtotalsum)."</span>";
	$faktureret = "<span style=\"color:green;#font-size:13px;\">".dkdecimal($fakturasum)."</span>";
	$lonudgifter = "<span style=\"color:red;#font-size:13px;\">".dkdecimal($lonsum)."</span>";
	$lonudgifterialt = "<span style=\"color:red;#font-size:13px;\">".dkdecimal($lonsumsocial)."</span>";
	/*if ($fakturasum == 0) { #20161125
		$daekningsgrad = "<i>ingen dækningsgrad</i>";
		$daekningsbidrag = "<i>ingen dækningsbidrag</i>";
	} else {*/
	$daekningsbidragsum = $fakturasum-$lonsumsocial; // Dækningsbidrag beregnes
	if ($daekningsbidragsum < 0) {
		$daekningsprocent = ($daekningsbidragsum/$lonsumsocial)*100; // Dækningsgrad i % hvis dækningsbidrag er negativ
		$daekningsgrad = "<span style=\"color:red;\">".dkdecimal($daekningsprocent)."%</span>";
		$daekningsbidrag = "<span style=\"color:red;\">".dkdecimal($daekningsbidragsum)."</span>";
	} elseif ($fakturasum) {
		$daekningsprocent = ($daekningsbidragsum/$fakturasum)*100; // Dækningsgrad i %
		$daekningsgrad = "<span style=\"color:green;\">".dkdecimal($daekningsprocent)."%</span>";
		$daekningsbidrag = "<span style=\"color:black;\">".dkdecimal($daekningsbidragsum)."</span>";
	} else {
		$daekningsprocent = $daekningsgrad = $daekningsbidrag = 0;
	}
	
	print "<tr class=\"sagensomkostninger\">
		<td colspan=\"9\"><a href=\"#\">Sagens omkostninger:</a></td> <!-- sagomkostning.php?sag_id=$id&amp;konto_id=$konto_id -->
	</tr>
	<tr>
		<td colspan=\"9\" align=\"center\">\n";
		
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableOmkostninger\">
						<colgroup>
							<col width=\"33%\">
							<col width=\"34%\">
							<col width=\"33%\">
						</colgroup>
						<tr>
							<td style=\"vertical-align:top;border-right: 1px solid lightgray;\">";
							
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableOmkostninger02\">
						<tr>
							<th colspan=\"2\">Tilbudspris</th>
						</tr>";
	for ($z=0;$z<$temp_tilbud_antal;$z++) {
		print "<tr>
							<td>$temp_tilbud_beskrivelse[$z]</td>
							<td style=\"text-align: right;\">".dkdecimal($temp_tilbud_pris[$z])."</td>
						</tr>";
	}
	if ($allrisk > 0) {
		print "<tr>
							<td>Allrisk</td>
							<td style=\"text-align: right;\">".dkdecimal($allrisk)."</td>
						</tr>";
	}
		print "<tr>
							<td class=\"tableOmkostningerBorder\">Tilbudspris u. moms</td>
							<td class=\"tableOmkostningerBorder\" style=\"text-align: right;\">$tilbudspris</td>
						</tr>
						<tr>
							<td>Tilbudspris m. moms</td>
							<td style=\"text-align: right;\">$tilbudsprismoms</td>
						</tr>
					</table>";
					
			print "</td>
							<td style=\"vertical-align:top;border-right: 1px solid lightgray;\">";
					
		print "<table border=\"0\" cellspacing=\"0\" class=\"tableOmkostninger02\">
						<tr>
							<th colspan=\"2\">Faktureret</th>
						</tr>";
	
	
	for ($y=0;$y<$temp_antal;$y++) {
		print "<tr>
							<td>$temp_beskrivelse[$y]</td>
							<td style=\"text-align: right;\">".dkdecimal($temp_pris[$y])."</td>
					</tr>";
	}
		print "<tr>
							<td class=\"tableOmkostningerBorder\">Faktureret ialt</td>
							<td class=\"tableOmkostningerBorder\" style=\"text-align: right;\">$faktureret</td>
					</tr>";
	print "</table>";
	
			print "</td>
							<td style=\"vertical-align:top\">";
	
	print "<table border=\"0\" cellspacing=\"0\" class=\"tableOmkostninger02\">
						<tr>
							<th colspan=\"2\">Lønudgifter</th>
						</tr>
						<tr>
							<td>Lønudgifter u. sociale</td>
							<td style=\"text-align: right;\">$lonudgifter</td>
						</tr>
						<tr>
							<td>Lønudgifter m. sociale</td>
							<td style=\"text-align: right;\">$lonudgifterialt</td>
						</tr>
					</table>";
					
			print "</td>
						</tr>
					</table>";
					
	print "<table border=\"0\" cellspacing=\"0\" class=\"tableOmkostninger03\">
					<colgroup>
						<col width=\"33%\">
						<col width=\"34%\">
						<col width=\"33%\">
					</colgroup>
						<tr>
							<td>&nbsp;</td>
							<td style=\"text-align: center;\">Dækningsbidrag: $daekningsbidrag</td>
							<td style=\"text-align: center;\">Dækningsgrad: $daekningsgrad</td>
						</tr>
					</table>";
					
	print "<table border=\"0\" cellspacing=\"0\" class=\"tableSagerBorder\" style=\"width:100%;\">
						<tr>
							<td><a class=\"button gray small\" title=\"klik her for at se den samlede akkordliste på sagen!\" href=\"sager.php?funktion=akkordliste&amp;sag_id=$id\">Vis akkordlister</a></td>
						</tr>
					</table>";
					
	/* gammel table
	print "<table border=\"0\" cellspacing=\"0\" width=\"100%\">
				<tbody>
					<tr>
						<td align=\"left\"><p style=\"margin:10px 0 10px 0;\"><b>Tilbudspris u. moms: $tilbudspris kr.</b></p></td>
						<td>&nbsp;</td>
						<td align=\"right\"><p style=\"margin:10px 0 10px 0;\"><b>Lønudgifter u. sociale: $lonudgifter kr.</b></p></td>
					</tr>
					<tr>
						<td align=\"left\"><p style=\"margin:10px 0 10px 0;\"><b>Tilbudspris m. moms: $tilbudsprismoms kr.</b></p></td>
						<td align=\"center\"><p style=\"margin:10px 0 10px 0;\"><b>Faktureret ialt: $faktureret kr.</b></p></td>
						
						<td align=\"right\"><p style=\"margin:10px 0 10px 0;\"><b>Lønudgifter m. sociale: $lonudgifterialt kr.</b></p></td>
					</tr>
				</tbody>
				<tbody>
					<tr>
						<td colspan=\"3\"><a class=\"button gray small\" title=\"klik her for at se den samlede akkordliste på sagen!\" href=\"sager.php?funktion=akkordliste&amp;sag_id=$id\">Vis akkordlister</a></td>
					</tr>
				</tbody>
			</table>
		</td>
	</tr>\n";
	*/
	
	/* // visning uden table
	print "<tr>
		<td colspan=\"2\"><p style=\"margin:10px 0 10px 0;\"><b>Tilbudspris: $tilbudspris kr.</b></p></td>
		<td colspan=\"3\"><p style=\"margin:10px 0 10px 0;\"><b>Faktureret: $faktureret kr.</b></p></td>
		<td colspan=\"4\"><p style=\"margin:10px 0 10px 0;\"><b>Lønudgifter: $lonudgifter kr.</b></p></td>
	</tr>\n";
	*/
	if (!$kontrol_id) print "<tr><td colspan=\"9\"><hr></td></tr>\n";
	print "</tbody>\n";
	
	//Her skal liste med kontrolskemaer vises
	if ($kontrol_id) {
	print "<tbody><tr><td colspan=\"9\"><hr></td></tr>\n";
	print "<tr><td colspan=\"9\"><p><b>Kontrol:</b></p></td></tr>"; 
	print "<tr class=\"tableSagerHead\">
						<td colspan=\"1\"><p>Skema</p></td>
						<td colspan=\"3\"><p>Opgave</p></td>
						<td colspan=\"1\"><p>Dato</p></td>
						<td colspan=\"1\"><p>Tid</p></td>
						<td colspan=\"3\"><p>Kontrolleret af</p></td>
					</tr></tbody>";
	print "<tbody class=\"tableSagerZebra\">";
					
					
					for ($y=0;$y<count($kontrol_id);$y++) {
						$r=db_fetch_array(db_select("select * from tjekliste where id='$kontrol_tjek_id[$y]'",__FILE__ . " linje " . __LINE__));
						$tjekpunkt=$r['tjekpunkt'];
						$fase=$r['fase']*1;
						($fase==1)?$funktion_navn = "arbejdsseddel":$funktion_navn = "kontrolskema";
						
						print "<tr>
							<td colspan=\"1\"><p><a href=\"kontrol_sager.php?funktion=$funktion_navn&amp;sag_id=$id&amp;sag_fase=$fase&amp;tjek_id=$kontrol_tjek_id[$y]&amp;tjekskema_id=$kontrol_id[$y]\">$tjekpunkt</a></p></td>
							<td colspan=\"3\"><p>$kontrol_opg_navn[$y]&nbsp;</p></td>
							<td colspan=\"1\"><p>$kontrol_dato[$y]</p></td>
							<td colspan=\"1\"><p>$kontrol_tidspkt[$y]</p></td>
							<td colspan=\"3\" title=\"$kontrol_ref[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:118px;\">$kontrol_ref[$y]</p></td>
						</tr>";
					}
	print "</tbody>\n";
				}
	print "<tbody>\n";
	if (!$kontrol_id) {
	print "<tr><td colspan=\"9\"><p><i><b>Opret skema til sagen her:</b></i></p></tr>\n";
	print "<tr><td colspan=\"9\"><a class=\"button blue small\" title=\"klik her for at oprette kontrolskema til sagen\" href=\"kontrol_sager.php?sag_id=$id&amp;konto_id=$konto_id\">Opret skema</a></td></tr>\n";
	} else {
	print "<tr><td colspan=\"9\" class=\"tableSagerBorder\"><a class=\"button blue small\" title=\"klik her for at oprette et nyt kontrolskema til sagen\" href=\"kontrol_sager.php?sag_id=$id&amp;konto_id=$konto_id\">Nyt skema</a></td></tr>\n";
	}
	
	
	/*
	print "<td  colspan=\"2\" align=\"right\"><p><b>Status:</b>
		<select name=\"status\">";
		for($x=0;$x<count($tjek_id);$x++) {
			if ($status==$tjek_fase[$x]) print "<option value=\"$tjek_fase[$x]\">$tjek_punkt[$x]</option>";
		}
		for($x=0;$x<count($tjek_id);$x++) {
			if ($status!=$tjek_fase[$x]) print "<option value=\"$tjek_fase[$x]\">$tjek_punkt[$x]</option>";
		}
		print "</select></p></td> -->
	</tr>
<!--	<tr>
		<td valign=\"top\"><p><a href=\"tilbud.php?konto_id=$konto_id&sag_id=$id\"><b>Tilbud:</b></a></p></td>
	</tr> -->";
*/
	
	
	
	
	#	for ($x=1;$x<=$tjeklister;$x++) {
		print "<tr><td colspan=\"9\"><hr></td></tr></tbody>\n";
		#print "<tr><td><b><big>";
#			if ($status>$x) {
#			 ($vis_fase==$x)?print "<a href=sager.php?sag_id=$id>$tjek_punkt[$x]</a>":print "<a href=sager.php?sag_id=$id&vis_fase=$x>$tjek_punkt[$x]</a>";
#			} else print "$tjek_punkt[$x]";
		#print "</big></b></td></tr>";
#		if ($status==$x || (is_numeric($vis_fase) && $vis_fase==$x)) {
			//print "<tr><td><a href=\"sager.php?sag_id=$id&amp;funktion=kontrolskema&amp;sag_fase=$x\"><b>Kontrolskema</b></a></td></tr>"; # sag_fase i kontrolskema??
			if ($ordrer_id) { 
				print "<tbody><tr><td colspan=\"9\"><p><b>Tilbud:</b></p></td></tr>";
				print "<tr class=\"tableSagerHead\">
						<td colspan=\"1\"><p>Nummer</p></td>
						<td colspan=\"1\"><p>Tilbuds nr</p></td>
						<td colspan=\"2\"><p>Status</p></td>
						<td colspan=\"1\"><p>Dato</p></td>
						<td colspan=\"1\"><p>Tid</p></td>
						<td colspan=\"3\"><p>Skrevet af</p></td>
					</tr></tbody>";
					print "<tbody class=\"tableSagerZebra\">";
					for ($y=0;$y<count($ordrer_id);$y++) {
						$kopi = NULL;
						if ($r=db_fetch_array(db_select("select * from ordrer where sag_id='$id' and status >= '3' and tilbudnr = '$ordrer_tilbudnr[$y]'",__FILE__ . " linje " . __LINE__))) { # Her tjekker vi om der er en faktura på det samme tilbudsnr
							if ($r['tilbudnr'] && ($ordrer_art[$y]!='OT')) {
								$kopi = "- <i>Kopi</i>";
								$ordrer_color[$y] = "color:green;";
							} else {
								$kopi = NULL;
							}
						}
							print "<tr>
								<td colspan=\"1\"><p><a href=\"../debitor/ordre.php?sag_id=$id&amp;konto_id=$ordrer_konto_id[$y]&amp;tjek=$ordrer_id[$y]&amp;id=$ordrer_id[$y]&amp;returside=sager\">Tilbud $ordrer_nr[$y]</a></p></td>
								<td colspan=\"1\"><p style=\"$ordrer_color[$y]\">$ordrer_tilbudnr[$y] $kopi</p></td>
								<td colspan=\"2\"><p style=\"$ordrer_color[$y]\">$opgstatus[$y]</p></td>
								<td colspan=\"1\"><p style=\"$ordrer_color[$y]\">$ordrer_dato[$y]</p></td>
								<td colspan=\"1\"><p style=\"$ordrer_color[$y]\">$ordrer_tidspkt[$y]</p></td>
								<td colspan=\"3\" title=\"$ordrer_ref[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:120px;$ordrer_color[$y]\">$ordrer_ref[$y]</p></td>
							</tr>";
					}
					print "</tbody>\n";
				}
				print "<tbody>\n";
			if (!$ordrer_id) {
			print "<tr><td colspan=\"9\"><p><i><b>Opret tilbud til sagen her:</b></i></p></tr>\n";
			print "<tr><td colspan=\"9\"><a class=\"button green small\" title=\"klik her for at oprette et tilbud til sagen\" href=\"../debitor/ordre.php?funktion=opret_ordre&amp;sag_id=$id&amp;konto_id=$konto_id&amp;returside=sager\">Opret Tilbud</a></td></tr>\n";
			} else {
			print "<tr><td class=\"tableSagerBorder\" colspan=\"9\"><a class=\"button green small\" title=\"klik her for at oprette et nyt tilbud til sagen\" href=\"../debitor/ordre.php?funktion=opret_ordre&amp;sag_id=$id&amp;konto_id=$konto_id&amp;returside=sager\">Nyt Tilbud</a></td></tr>\n";
			}
			
			print "<tr><td colspan=\"9\"><hr></td></tr>\n";
			print "</tbody>\n";
			if ($ot_id) {
				print "<tbody><tr><td colspan=\"9\"><p><b>Original tilbud:</b></p></td></tr>";
				print "<tr class=\"tableSagerHead\">
						<td colspan=\"1\"><p>Nummer</p></td>
						<td colspan=\"1\"><p>Tilbuds nr</p></td>
						<td colspan=\"2\"><p>Status</p></td>
						<td colspan=\"1\"><p>Dato</p></td>
						<td colspan=\"1\"><p>Tid</p></td>
						<td colspan=\"3\"><p>Skrevet af</p></td>
					</tr></tbody>";
					print "<tbody class=\"tableSagerZebra\">";
					for ($y=0;$y<count($ot_id);$y++) {
							print "<tr>
								<td colspan=\"1\"><p><a href=\"../debitor/ordre.php?sag_id=$id&amp;konto_id=$ot_konto_id[$y]&amp;tjek=$ot_id[$y]&amp;id=$ot_id[$y]&amp;returside=sager\">Original tilbud $ot_nr[$y]</a></p></td>
								<td colspan=\"1\"><p>$ot_tilbudnr[$y]</p></td>
								<td colspan=\"2\"><p>$otstatus[$y]</p></td>
								<td colspan=\"1\"><p>$ot_dato[$y]</p></td>
								<td colspan=\"1\"><p>$ot_tidspkt[$y]</p></td>
								<td colspan=\"3\" title=\"$ot_ref[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:120px;\">$ot_ref[$y]</p></td>
							</tr>";
					}
					print "</tbody>\n";
					print "<tbody>\n";
					print "<tr><td class=\"tableSagerBorder\" colspan=\"9\"></td></tr>\n";
					print "<tr><td colspan=\"9\"><hr></td></tr>\n";
					print "</tbody>\n";
			}
			if ($faktura_id) {
				print "<tbody><tr><td colspan=\"9\"><p><b>Faktura:</b></p></td></tr>";
				print "<tr class=\"tableSagerHead\">
						<td colspan=\"1\"><p>Nummer</p></td>
						<td colspan=\"1\"><p>Ordre nr</p></td>
						<td colspan=\"2\"><p>Status</p></td>
						<td colspan=\"1\"><p>Dato</p></td>
						<td colspan=\"1\"><p>&nbsp;</p></td>
						<td colspan=\"3\"><p>Skrevet af</p></td>
					</tr></tbody>";
					print "<tbody class=\"tableSagerZebra\">";
					for ($y=0;$y<count($faktura_id);$y++) {
							print "<tr>
								<td colspan=\"1\"><p><a href=\"../debitor/ordre.php?sag_id=$id&amp;konto_id=$faktura_konto_id[$y]&amp;tjek=$faktura_id[$y]&amp;id=$faktura_id[$y]&amp;returside=sager\">Faktura $faktura_fakturanr[$y]</a></p></td>
								<td colspan=\"1\"><p>$faktura_tilbudnr[$y] - <i>fra Tilbud $faktura_tilbud_nr[$y]</i></p></td>
								<td colspan=\"2\"><p>$faktstatus[$y]</p></td>
								<td colspan=\"1\"><p>$faktura_dato[$y]</p></td>
								<td colspan=\"1\"><p>&nbsp;</p></td>
								<td colspan=\"3\" title=\"$faktura_ref[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:120px;\">$faktura_ref[$y]</p></td>
							</tr>";
					}
					print "</tbody>\n";
					print "<tbody>\n";
					print "<tr><td class=\"tableSagerBorder\" colspan=\"9\"></td></tr>\n";
					print "<tr><td colspan=\"9\"><hr></td></tr>\n";
					print "</tbody>\n";
			}
			if ($kreditnota_id) {
				print "<tbody><tr><td colspan=\"9\"><p><b>Kreditnota:</b></p></td></tr>";
				print "<tr class=\"tableSagerHead\">
						<td colspan=\"1\"><p>Nummer</p></td>
						<td colspan=\"1\"><p>Ordre nr</p></td>
						<td colspan=\"2\"><p>Status</p></td>
						<td colspan=\"1\"><p>Dato</p></td>
						<td colspan=\"1\"><p>&nbsp;</p></td>
						<td colspan=\"3\"><p>Skrevet af</p></td>
					</tr></tbody>";
					print "<tbody class=\"tableSagerZebra\">";
					for ($y=0;$y<count($kreditnota_id);$y++) {
							print "<tr>
								<td colspan=\"1\"><p><a href=\"../debitor/ordre.php?sag_id=$id&amp;konto_id=$konto_id&amp;tjek=$kreditnota_id[$y]&amp;id=$kreditnota_id[$y]&amp;returside=sager\">Kreditnota $kreditnota_fakturanr[$y]</a></p></td>
								<td colspan=\"1\"><p>$kreditnota_tilbudnr[$y] - <i>fra Tilbud $kreditnota_tilbud_nr[$y]</i></p></td>
								<td colspan=\"2\"><p>$kreditnotastatus[$y]</p></td>
								<td colspan=\"1\"><p>$kreditnota_dato[$y]</p></td>
								<td colspan=\"1\"><p>&nbsp;</p></td>
								<td colspan=\"3\" title=\"$kreditnota_ref[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:120px;\">$kreditnota_ref[$y]</p></td>
							</tr>";
					}
					print "</tbody>\n";
					print "<tbody>\n";
					print "<tr><td class=\"tableSagerBorder\" colspan=\"9\"></td></tr>\n";
					print "<tr><td colspan=\"9\"><hr></td></tr>\n";
					print "</tbody>\n";
			}
			if ($bilag_id) {
				print "<tbody>\n";
				print "<tr><td colspan=\"9\"><p><b>Bilag:</b></p></td></tr>";
				print "<tr class=\"tableSagerHead\">
					<td colspan=\"1\"><p>Filnavn</p></td>
					<td colspan=\"1\"><p>Beskrivelse</p></td>
					<td colspan=\"1\"><p>Fase</p></td>
					<td colspan=\"1\"><p>Kategori</p></td>
					<td colspan=\"1\"><p>Dato</p></td>
					<td colspan=\"1\"><p>Tid</p></td>
					<td colspan=\"3\"><p>Tilf&oslash;jet af</p></td>
				</tr>";
				print "</tbody>\n";
				print "<tbody class=\"tableSagerZebra\">\n";
				for ($y=0;$y<count($bilag_id);$y++) {
				#				if ($bilag_fase[$y]==$x) {
						print "<tr>
							<td colspan=\"1\" title=\"$bilag_title[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:120px;\"><a href=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\" name=\"IMG_$bilag_navn[$y]\" target=\"blank\">$bilag_navn[$y]</a></p></td>
							<td colspan=\"1\" title=\"$bilag_beskrivelse[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:220px;\">$bilag_beskrivelse[$y]&nbsp;</p></td>
							<td colspan=\"1\" title=\"$bilag_bilag_fase[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:94px;\">$bilag_bilag_fase[$y]&nbsp;</p></td>
							<td colspan=\"1\" title=\"$bilag_kategori[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:100px;\">$bilag_kategori[$y]&nbsp;</p></td>
							<td colspan=\"1\"><p>".date("d-m-Y",$bilag_datotid[$y])."</p></td>
							<td colspan=\"1\"><p>$bilag_tidspkt[$y]</p></td>
							<td colspan=\"1\" title=\"$bilag_hvem[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:80px;\">$bilag_hvem[$y]&nbsp;</p></td>";
							print "<td colspan=\"1\" title=\"Ret fase, kategori og tilknyt bilag til kontrolskema\"><a href=\"bilag_sager.php?kilde=sager&amp;sag_id=$id&amp;konto_id=$konto_id&amp;kilde_id=$id&amp;bilag_id=$bilag_id[$y]\" class=\"cross\"></a></td>\n";
							//if (($bilag_datotid[$y]+3600>date("U") && $bilag_hvem[$y]==$brugernavn) || $bilag_hvem[$y]=='') {
								print "<td colspan=\"1\" title=\"Klik her for at slette dette bilag\">
								<a href=\"sager.php?funktion=vis_sag&amp;sag_id=$id&amp;slet_bilag=$bilag_id[$y]\" class=\"xmark\" onclick=\"return confirm('Vil du slette bilag: $bilag_beskrivelse[$y]?')\"></a>
								</td>";
							//} else {
								//print "<td colspan=\"1\" width=\"18\">&nbsp;</td>";
							//}
	#						<!--<td colspan=\"1\"><p><a href=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\" name=\"$bilag_navn[$y]\" target = \"blank\">&Aring;bn</a></p></td>
	#						<td colspan=\"1\"><p><a download=\"$bilag_navn[$y]\" href=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\" target = \"blank\">Gem</a></p></td>-->
						print "</tr>";
	#				}
				}
				print "</tbody>\n";
			}
			print "<tbody>\n";
			if (!$bilag_id) {
			print "<tr><td colspan=\"9\"><p><i><b>Opret bilag til sagen her:</b></i></p></tr>\n";
			print "<tr><td colspan=\"9\"><a class=\"button blue small\" title=\"klik her for at vedh&aelig;fte et bilag\" href=\"bilag_sager.php?kilde=sager&amp;ny=ja&amp;kilde_id=$id&amp;sag_id=$id&amp;konto_id=$konto_id&amp;fase=$x\">Opret bilag</a></td></tr>\n";
			} else {
			print "<tr><td class=\"tableSagerBorder\" colspan=\"9\"><a class=\"button blue small\" title=\"klik her for at vedh&aelig;fte et bilag\" href=\"bilag_sager.php?kilde=sager&amp;ny=ja&amp;kilde_id=$id&amp;sag_id=$id&amp;konto_id=$konto_id&amp;fase=$x\">Nyt bilag</a>
			<a class=\"button gray small textSpaceSmall\" title=\"klik her for at se alle bilag fra sagen\" href=\"view_bilag_sager.php?sag_id=$id\" target=\"blank\">Vis alle</a></td></tr>\n";
			}
			print "<tr><td colspan=\"9\"><hr></td></tr></tbody>\n";
			if ($notat_id) {
				print "<tbody><tr><td colspan=\"9\"><p><b>Noter:</b></p></td></tr>\n";
				print "<tr style=\"background:lightgray;\">
					<td colspan=\"1\"><p>Overskrift</p></td>
					<td colspan=\"1\"><p>Status</p></td>
					<td colspan=\"1\"><p>Fase</p></td>
					<td colspan=\"1\"><p>Kategori</p></td>
					<td colspan=\"1\"><p>Dato</p></td>
					<td colspan=\"1\"><p>Tid</p></td>
					<td colspan=\"3\"><p>Skrevet af</p></td>
					<!--<td colspan=\"1\"><p>Notat</p></td>-->
				</tr>\n";
				print "</tbody>\n";
				print "<tbody class=\"tableSagerZebra\">";
				for ($y=0;$y<count($notat_id);$y++) {
	#cho "$notat_fase[$y]==$x<br>";
	#				if ($notat_fase[$y]==$x) {
					$stat = "";
					if (!$notat_status[$y]) $stat = "Kladde";
					elseif (!$notat_status[$y]==1) $stat = "Afventer læsning";
					else $stat = "OK";
						print "<tr>
							<td colspan=\"1\" title=\"$notat_title[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:120px;\"><a href=\"notat.php?id=$notat_id[$y]&amp;konto_id=$konto_id\">$notat_beskrivelse[$y]</a></p></td>
							<td colspan=\"1\"><p>$stat</p></td>
							<td colspan=\"1\" title=\"$notat_notat_fase[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:94px;\">$notat_notat_fase[$y]&nbsp;</p></td>
							<td colspan=\"1\" title=\"$notat_kategori[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:100px;\">$notat_kategori[$y]&nbsp;</p></td>
							<td colspan=\"1\"><p>$notat_dato[$y]</p></td>
							<td colspan=\"1\"><p>$notat_tidspkt[$y]</p></td>
							<td colspan=\"1\" title=\"$notat_hvem[$y]\"><p class=\"tableSagerEllipsis\" style=\"max-width:80px;\">$notat_hvem[$y]</p></td>
							<td colspan=\"1\">&nbsp;</td>\n";
							if ($notat_datotid[$y]+3600>date("U") && $notat_status[$y]==1) {
							print "<td colspan=\"1\" title=\"Klik her for at slette notatet fra sagen\">
								<a href=\"sager.php?funktion=vis_sag&amp;sag_id=$id&amp;unlink_notat=$notat_id[$y]\" class=\"xmark\" onclick=\"return confirm('Vil du slette notatet fra sagen?')\"></a>
								</td>\n";
							} elseif (!$notat_status[$y]) {
							print "<td colspan=\"1\" title=\"Klik her for at slette notatet fra sagen\">
								<a href=\"sager.php?funktion=vis_sag&amp;sag_id=$id&amp;unlink_notat=$notat_id[$y]\" class=\"xmark\" onclick=\"return confirm('Vil du slette notatet fra sagen?')\"></a>
								</td>\n";
							} else {
								print "<td colspan=\"1\">&nbsp;</td>";
							}
							
						print "</tr>";
	#				}
				}
				print "</tbody>\n";
			}
			print "<tbody>\n";
			if (!$notat_id) {
			print "<tr><td colspan=\"9\"><p><i><b>Opret notat til sagen her:</b></i></p></tr>\n";
			print "<tr><td colspan=\"9\"><a class=\"button blue small\" title=\"klik her for at oprettet et notat til sagen\" href=\"../sager/notat.php?sag_id=$id&amp;sag_fase=$x&amp;konto_id=$konto_id\">Opret notat</a></td></tr>\n";
			} else {
			print "<tr><td class=\"tableSagerBorder\" colspan=\"9\"><a class=\"button blue small\" title=\"klik her for at oprettet et notat til sagen\" href=\"../sager/notat.php?sag_id=$id&amp;sag_fase=$x&amp;konto_id=$konto_id\">Nyt notat</a></td></tr>\n";
			}
			print "</tbody>\n";
#		} 
#	}
	if ($opgave_id) {
		print "<tbody>\n";
		print "<tr><td colspan=\"9\"><hr></td></tr>\n";
		print "<tr><td colspan=\"9\"><p><b>Opgaver:</b></p></td></tr>\n";
		print "<tr class=\"tableSagerHead\">
						<td colspan=\"1\"><p>Opgave navn</p></td>
						<td colspan=\"3\"><p>Beskrivelse</p></td>
						<td colspan=\"2\"><p>Status</p></td>
						<td colspan=\"3\"><p>Oprettet af</p></td>
					</tr>";
		print "</tbody>\n";
		
		print "<tbody class=\"tableSagerZebra\">\n";
	for ($x=0;$x<count($opgave_id);$x++) {
		print "<tr><td colspan=\"1\"><p><a href=\"sager.php?sag_id=$id&amp;opgave_id=$opgave_id[$x]&amp;konto_id=$konto_id&amp;funktion=ret_opgave\">Opgave $opgave_nr[$x]</a></p></td>\n";
		print "<td colspan=\"3\" title=\"$opgave_beskrivelse[$x]\"><p class=\"tableSagerEllipsis\" style=\"max-width:418px;\">$opgave_beskrivelse[$x]</p></td>\n";
		print "<td colspan=\"2\"><p style=\"$opgcolor[$x]\">$opgave_status[$x]&nbsp;</p></td>\n";
		print "<td colspan=\"3\" title=\"$opgave_oprettet_af[$x]\"><p class=\"tableSagerEllipsis\" style=\"max-width:118px;\">$opgave_oprettet_af[$x]&nbsp;</p></td>\n";
		print "</tr>\n";
	}
	print "</tbody>\n";
	}
	print "</table></div><!-- end of content -->\n";
}# endfunc vis_sag

function ret_opgave($sag_id) {
	global $brugernavn;
	global $db;

	$opgave_id=if_isset($_GET['opgave_id'])*1;
	$konto_id=if_isset($_GET['konto_id']);
	$tilbud_id=if_isset($_GET['tilbud_id']);
	
	if ($_POST['opdater'] && isset($_POST['opgave_nr'])) {
		$opgave_nr=if_isset($_POST['opgave_nr']);
		$opgave_status=if_isset($_POST['opgave_status']);
		$opgave_beskrivelse=db_escape_string($_POST['opgave_beskrivelse']);
		$opgave_omfang=db_escape_string($_POST['opgave_omfang']);
		$opgave_kontakt=if_isset($_POST['opgave_kontakt']);
		$opg_planfra=$_POST['opgave_planfra']; 
 		$opg_plantil=$_POST['opgave_plantil'];
 		($opg_planfra)?$opgave_planfra=usdate($opg_planfra):$opg_planfra=NULL;
 		($opg_plantil)?$opgave_plantil=usdate($opg_plantil):$opg_plantil=NULL;
 		$opgave_tilknyttil=$_POST['opgave_tilknyttil'];
 		
 		if (!$opgave_planfra && $opgave_plantil) {
			$opgave_planfra=$opgave_plantil;
		} 
		if ($opgave_planfra && !$opgave_plantil) {
			$opgave_plantil=$opgave_planfra;
		}
		
		if ($opgave_id) {
		
			db_modify("update opgaver set omfang='$opgave_omfang',beskrivelse='$opgave_beskrivelse',status='$opgave_status',kunde_ref='$opgave_kontakt',hvem='$brugernavn',opg_planfra='$opgave_planfra',opg_plantil='$opgave_plantil',opg_tilknyttil='$opgave_tilknyttil' where id='$opgave_id'",__FILE__ . " linje " . __LINE__);
		} else {
			$tidspkt=date("U");
			db_modify("insert into opgaver (assign_id,assign_to,nr,status,beskrivelse,omfang,hvem,oprettet_af,tidspkt,kunde_ref)values('$sag_id','sager','$opgave_nr','$opgave_status','$opgave_beskrivelse','$opgave_omfang','$brugernavn','$brugernavn','$tidspkt','$opgave_kontakt')",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from opgaver where assign_id='$sag_id' and assign_to='sager' and nr='$opgave_nr'",__FILE__ . " linje " . __LINE__));
			$opgave_id=$r['id'];
		}
	}  elseif (isset($_POST['slet_opgave']) && $opgave_id) {
		db_modify("delete from opgaver where id = '$opgave_id'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/sager.php?funktion=vis_sag&amp;sag_id=$sag_id\">";
	}
	
	$r=db_fetch_array(db_select("select sagsnr,firmanavn,udf_addr1,konto_id,planfraop,plantilop,planfraned,plantilned,status from sager where id='$sag_id'",__FILE__ . " linje " . __LINE__));
	$sag_nr=$r['sagsnr'];
	$sag_konto_id=$r['konto_id'];
	$sag_firma=htmlspecialchars($r['firmanavn']);
	$sag_adresse=htmlspecialchars($r['udf_addr1']);
	$sag_status=htmlspecialchars($r['status']);
	$sag_planfraop=dkdato($r['planfraop']);
	$sag_plantilop=dkdato($r['plantilop']);
	$sag_planfraned=dkdato($r['planfraned']);
	$sag_plantilned=dkdato($r['plantilned']);
	//$sag_kontakt=htmlspecialchars($r['kontakt']);
	
	$r=db_fetch_array(db_select("select * from opgaver where id='$opgave_id'",__FILE__ . " linje " . __LINE__));
	$opgave_id=$r['id'];
	$opgave_nr=$r['nr'];
	$opgave_status=$r['status'];
	$opgave_beskrivelse=htmlspecialchars($r['beskrivelse']);
	$opgave_omfang=htmlspecialchars($r['omfang']);
	$opgave_udf_firmanavn=htmlspecialchars($r['udf_firmanavn']);
	$opgave_ref=htmlspecialchars($r['ref']);
	$opgave_oprettet_af=htmlspecialchars($r['oprettet_af']);
	$opgave_dato=date("d-m-y",$r['tidspkt']);
	$opgave_tid=date("H:i",$r['tidspkt']);
	$opgave_kontakt=htmlspecialchars($r['kunde_ref']);
	if (!$opgave_id) {
		$r=db_fetch_array(db_select("select max(nr) as nr from opgaver where assign_id='$sag_id' and assign_to='sager'",__FILE__ . " linje " . __LINE__));
		$opgave_nr=$r['nr']+1;
	}
	$opgave_planfra=dkdato($r['opg_planfra']);
	$opgave_plantil=dkdato($r['opg_plantil']);
	$opgave_tilknyttil=$r['opg_tilknyttil'];
	
	$checked1 = NULL;$checked2 = NULL;
	if ($opgave_tilknyttil == 'demontage') $checked1 = "checked";
	if ($opgave_tilknyttil == 'andet') $checked2 = "checked";
	
	#20151019-2
	$statcolor = NULL;
	if ($opgave_status=='Afsluttet') $statcolor = "status_color1";
	if ($opgave_status=='Tilbud') $statcolor = "status_color2";
	if ($opgave_status=='Ordrebekræftelse') $statcolor = "status_color3";
	if ($opgave_status=='Montage') $statcolor = "status_color4";
	if ($opgave_status=='Godkendt') $statcolor = "status_color5";
	if ($opgave_status=='Afmeldt') $statcolor = "status_color6";
	if ($opgave_status=='Drivgods') $statcolor = "status_color7";
	if ($opgave_status=='Afsluttet') $statcolor = "status_color8";
	
	$color = array("status_color1","status_color2","status_color3","status_color4","status_color5","status_color6","status_color7","status_color8");
	
	// Query til kunde kontakt
	$x=0;
	$q=db_select("select * from ansatte where konto_id='$sag_konto_id' order by posnr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$k_id[$x]=$r['id'];
		$k_kontakt[$x]=$r['navn'];
		$x++;
	}
	
	// Query til kundes kontaktoplysninger
	$r=db_fetch_array(db_select("select * from ansatte where navn='$opgave_kontakt' and konto_id='$sag_konto_id'",__FILE__ . " linje " . __LINE__));
	$kontakt_id=$r['id'];
	$kontakt_telefon=$r['mobil'];
	$kontakt_email=$r['email'];
	(!$kontakt_telefon)?$kontakt_telefon='<i>Ingen telefonnummer</i>':$kontakt_telefon;
	(!$kontakt_email)?$kontakt_email='<i>Ingen emailadresse</i>':$kontakt_email;
	
	// Query til status
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=$r['tjekpunkt']; 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
	
	/* Query til opg_status
	---------------------------------------
	*/
	#20151019-2
	 //Her ændres tekst til status 
	$opg_status_tekst="Beregning,Tilbud,Ordrebekræftelse,Montage,Godkendt,Afmeldt,Drivgods,Afsluttet"; // gammel status "Opm&aring;ling,Tilbud,Ordre modtaget,Montage,Aflevering,Afmeldt,Demontage,Afsluttet"
	
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__)) && ($r['box1']==$opg_status_tekst)) {
		$opg_status=explode(chr(44),$r['box1']);
	} elseif ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__)) && ($r['box1']!=$opg_status_tekst)) {
		db_modify("update grupper set box1='$opg_status_tekst' where art='SAGSTAT'",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
		$opg_status=explode(chr(44),$r['box1']);
	} else { 
		db_modify("insert into grupper (art,box1) values ('SAGSTAT','$opg_status_tekst')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
		$opg_status=explode(chr(44),$r['box1']);
	}
	
	
	// Query til opgave-tilbud
	/*if ($opgave_id) {
		$x=0;
		$q = db_select("select * from tilbud where sag_id = '$sag_id' and opgave_id = '$opgave_id' order by id",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$tilbud_id[$x]=$r['id'];
			$tilbud_nr[$x]=$r['tilbudnr'];
			$tilbud_beskrivelse[$x]=$r['beskrivelse'];
			$tilbud_tekst[$x]=$r['tekst'];
			$tilbud_dato[$x]=date("d-m-Y",$r['datotid']);
			$tilbud_tidspkt[$x]=date("H:i",$r['datotid']);
			$tilbud_hvem[$x]=$r['hvem'];
			$x++;
		}
	}*/
	print "<div class=\"content\">";
		print "<form method=\"post\" action=\"sager.php?konto_id=$konto_id&amp;sag_id=$sag_id&amp;opgave_id=$opgave_id&amp;funktion=ret_opgave\">";
		print "<input type=\"hidden\" name=\"opgave_nr\" value=\"$opgave_nr\">";	
		print "<div style=\"float:left; width:828px;\">
					<h3>Sag: $sag_nr $sag_adresse ($sag_firma)</h3>
					</div><!-- end of full container -->
				<div class=\"clear\"></div>
			<div style=\"float:left; margin-right:70px; width:379px;\">
			<div class=\"contentA\">
			<div class=\"row\">
					<div class=\"left\">Opgave nr:</div>
					<div class=\"right\"><b>$opgave_nr</b></div>
					<div class=\"clear\"></div>
			</div>";
			if($opgave_id) {
			print "<div class=\"row\">
					<div class=\"left\">Oprettet:</div>
					<div class=\"right\">d.$opgave_dato kl. $opgave_tid</div>
					<div class=\"clear\"></div>
			</div>
			<div class=\"row\">
					<div class=\"left\">Oprettet af:</div>
					<div class=\"right\">$opgave_oprettet_af</div>
					<div class=\"clear\"></div>
			</div>";
			}
			print "<div class=\"row\">
					<div class=\"left\">Beskrivelse:</div> 
					<div class=\"right\"><input class=\"text\" type=\"text\" name=\"opgave_beskrivelse\" value=\"$opgave_beskrivelse\"></div>
					<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
		</div><!-- end of left container -->
		<div style=\"float:left; width:379px;\">
			<div class=\"contentA\">
			<div class=\"row\">
					<div class=\"left\">Status</div>
					<div class=\"right\"><select onchange=\"this.className=this.options[this.selectedIndex].className\" style=\"width:194px;\" class=\"$statcolor\" name=\"opgave_status\">";
		for($y=0;$y<=count($opg_status);$y++) {
			if ($opgave_status==$opg_status[$y]) print "<option class=\"$color[$y]\" value=\"$opg_status[$y]\">$opg_status[$y]</option>";
		}
		for($y=0;$y<=count($opg_status);$y++) {
			if ($opgave_status!=$opg_status[$y]) print "<option class=\"$color[$y]\" value=\"$opg_status[$y]\">$opg_status[$y]</option>";
		}
		print "</select></div>
					<div class=\"clear\"></div>
			</div>
			</div><!-- end of contentA -->
		</div><!-- end of right container -->";
		if ($opgave_status!='Beregning' && $opgave_status!='Tilbud' && $opgave_status!='Afsluttet' && $opgave_status!='' && $sag_status!='Beregning' && $sag_status!='Tilbud' && $sag_status!='Afsluttet') { #20151019-2
		print "
		<div class=\"clear\"></div>
		<hr>
		<div style=\"float:left; width:828px;\">
			<h3>Planlægnings information til opgave:</h3>
		</div><!-- end of full container -->
		<div class=\"clear\"></div>
		<div style=\"float:left; margin-right:70px; width:379px;\">
			<div class=\"contentA\">
				<div style=\"float:left; padding:5px 0px 0px 7px;\"><p><i>Her tastes den planlagte start og slut dato for opgaven, som vises i planlægningsskemaet</i></p></div>
			</div><!-- end of contentA -->
			<div class=\"clear\"></div>
			<div class=\"contentA\">
				<div class=\"row\">
					<div class=\"left\">Start / Slut: </div>
					<div class=\"rightSmall\"><input name=\"opgave_planfra\" id=\"opgave_planfra\" type=\"text\" class=\"textMedium\" value=\"$opgave_planfra\"/></div>
					<div class=\"rightSmall\"><input name=\"opgave_plantil\" id=\"opgave_plantil\" type=\"text\" class=\"textMedium\" value=\"$opgave_plantil\"/></div>
					<div class=\"clear\"></div>
				</div>
				<div class=\"row\">
					<div class=\"left\">Vis som:</div>
					<div class=\"right\"><input type=\"radio\" name=\"opgave_tilknyttil\" value=\"montage\" checked><span style=\"color:#09B109;vertical-align:top;padding:0 25px 0 5px;\">Montage</span><input type=\"radio\" name=\"opgave_tilknyttil\" value=\"demontage\" $checked1><span style=\"color:#DE0C0C;vertical-align:top;padding:0 0 0 5px\">Demontage</span></div>
					<div class=\"left\">&nbsp;</div>
					<div class=\"right\"><input type=\"radio\" name=\"opgave_tilknyttil\" value=\"andet\" $checked2><span style=\"color:#DE6B0C;vertical-align:top;padding:0 25px 0 5px;\">Andet</span></div>
					<div class=\"clear\"></div>
				</div>
			</div><!-- end of contentA -->
		</div><!-- end of left container -->";
			if ($sag_planfraop) {
			print "<div style=\"float:left; width:379px;\">
				<div class=\"contentA\">
					<div class=\"row\">
						<div style=\"float:left; padding:5px 0px 0px 7px;\"><p>Den planlagte dato for sagen er:</p></div>
						<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;\">Start: <span style=\"color:#cd3300;\">$sag_planfraop</span> - Slut: <span style=\"color:#cd3300;\">$sag_plantilop</span></p><br></div>
						<div class=\"clear\"></div>
					</div>
				</div><!-- end of contentA -->
			</div><!-- end of right container -->";
			} else {
			print "<div style=\"float:left; width:379px;\">
				<div class=\"contentA\">
					<div class=\"row\">
						<div style=\"float:left; padding:5px 0px 0px 7px;\"><p><i>Der er ingen planlagt start og slut dato for sagen</i></p></div>
						<div class=\"clear\"></div>
					</div>
				</div><!-- end of contentA -->
			</div><!-- end of right container -->";
			}
		/*print "<div style=\"float:left; width:379px;\">
			<div class=\"contentA\">
				<div class=\"row\">
					<div style=\"float:left; padding:5px 0px 0px 7px;\"><p>Den planlagte dato for montage er:</p></div>
					<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;\">Start: <span style=\"color:#cd3300;\">$sag_planfraop</span> - Slut: <span style=\"color:#cd3300;\">$sag_plantilop</span></p><br></div>
					<div style=\"float:left; padding:5px 0px 0px 7px;\"><p>Den planlagte dato for demontage er:</p></div>
					<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;\">Start: <span style=\"color:#cd3300;\">$sag_planfraned</span> - Slut: <span style=\"color:#cd3300;\">$sag_plantilned</span></p></div>
					<div class=\"clear\"></div>
				</div>
			</div><!-- end of contentA -->
		</div><!-- end of right container -->";*/
		} else {
		print "<input type=\"hidden\" name=\"opgave_planfra\" value=\"$opgave_planfra\"><input type=\"hidden\" name=\"opgave_plantil\" value=\"$opgave_plantil\">";
		}
		print "<div class=\"clear\"></div>
		<!--<div class=\"contentA\">
			<a class=\"button blue small\" href=\"sager.php?funktion=kontrolskema&amp;sag_id=$sag_id&amp;opgave_id=$opgave_id\">Kontrolskema</a>
		</div>--><!-- end of contentA -->
		
		<!--<div class=\"clear\"></div>-->
		<hr>";
		
		print "<div style=\"float:left; width:828px;\">
		<div class=\"contentAB\">
		
			<!--<div class=\"row\">
					<div class=\"left\">Beskrivelse:</div> 
					<div class=\"right\"><input style=\"width:350px\" type=\"text\" name=\"del_opgave_beskrivelse\" value=\"\"></div>
					<div class=\"clear\"></div>
			</div>-->";
			
			// Skal være dynamisk. Skal kunne oprette nye omfang med priser og start/slut dato
			print "<div class=\"row\">
				<div class=\"left\">Omfang:</div>
				<div class=\"right\"><textarea cols=\"78\" rows=\"3\" style=\"min-width:679px;max-width:679px;\" name=\"opgave_omfang\">$opgave_omfang</textarea></div>
				<div class=\"clear\"></div>
			</div>
			</div><!-- end of contentAB -->
		</div><!-- end of full container -->
		<div class=\"clear\"></div>
		<div style=\"float:left; margin-right:70px; width:379px;\">
			<div class=\"contentBA\">
			
			<!--<div class=\"row\">
				<div class=\"left\">Pris:</div>
				<div class=\"right\"><input class=\"text\" type=\"text\" name=\"opg_pris\"></div>
				<div class=\"clear\"></div>
			</div>-->
			
			<div class=\"row\">
				<div class=\"left\">Kontakt:</div>"; 
				print "<div class=\"right\"><select style=\"width:194px;\" id=\"opgave_kontakt\" name=\"opgave_kontakt\">\n";
					for ($x=0;$x<=count($k_kontakt);$x++) {
						if ($opgave_kontakt==$k_kontakt[$x]) print "<option value=\"$k_kontakt[$x]\">$k_kontakt[$x]&nbsp;</option>\n";	
					}
					for ($x=0;$x<=count($k_kontakt);$x++) {
						if ($opgave_kontakt!=$k_kontakt[$x]) print "<option value=\"$k_kontakt[$x]\">$k_kontakt[$x]&nbsp;</option>\n";	
					}
					//print "<option><p>$kontakt</p></option>";
				print "</select></div>
				<div class=\"clear\"></div>
			</div>";
			if($kontakt_id){
			print "
			<div class=\"row\">
				<div class=\"left\">Kontakt Tlf:</div>
				<div class=\"right\">$kontakt_telefon</div>
				<div class=\"clear\"></div>
			</div>
			</div><!-- end of contentBA -->
		</div><!-- end of left container -->
		<div style=\"float:left; width:379px;\">
			<div class=\"contentBA\">
			<div class=\"row\">
				<div class=\"left\">&nbsp;</div>
				<div class=\"right\">&nbsp;</div>
				<div class=\"clear\"></div>
			</div>
			<div class=\"row\">
				<div class=\"left\">Kontakt email:</div>
				<div class=\"right\">$kontakt_email</div>
				<div class=\"clear\"></div>
			</div>";
			}
			print "
			</div><!-- end of contentBA -->
		</div><!-- end of right or left container -->
		<div class=\"clear\"></div>";
		/*if ($opgave_id) {
			print "<hr>\n";
			if ($tilbud_id) {
				print "<p><b>Opgave tilbud:</b></p>\n";
			}
			print "<div class=\"contentA\">\n";
			print "<table border=\"0\" cellspacing=\"0\" width=\"808\">\n";
			if ($tilbud_id) {
					
					print "<tr class=\"tableSagerHead\">
							<td colspan=\"1\"><p>Overskrift</p></td>
							<td colspan=\"1\"><p>Tilbuds nr</p></td>
							<td colspan=\"1\" width=\"75\"><p>Dato</p></td>
							<td colspan=\"1\"><p>Tid</p></td>
							<td colspan=\"1\"><p>Skrevet af</p></td>
							<td colspan=\"1\">&nbsp;</td>
						</tr>";
						print "<tbody class=\"tableSagerZebra\">";
						for ($y=0;$y<count($tilbud_id);$y++) {
								print "<tr>
									<td colspan=\"1\"><p><a href=\"tilbud.php?sag_id=$sag_id&amp;tilbud_id=$tilbud_id[$y]&amp;opgave_id=$opgave_id&amp;konto_id=$konto_id\">$tilbud_beskrivelse[$y]</a></p></td>
									<td colspan=\"1\"><p>$tilbud_nr[$y]</p></td>
									<td colspan=\"1\"><p>$tilbud_dato[$y]</p></td>
									<td colspan=\"1\"><p>$tilbud_tidspkt[$y]</p></td>
									<td colspan=\"2\"><p>$tilbud_hvem[$y]</p></td>
								</tr>";
						}
						print "</tbody>\n";
					}
					print "<tbody>\n";
				if (!$tilbud_id) {
				print "<tr><td colspan=\"6\"><p><i><b>Opret tilbud til opgaven her:</b></i></p></tr>\n";
				print "<tr><td colspan=\"6\" title=\"klik her for at oprette et tilbud til opgaven\"><p><a class=\"button green small\" href=\"../sager/tilbud.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;opgave_id=$opgave_id\">Opret Tilbud</a></p></td></tr>\n";
				} else {
				print "<tr><td class=\"tableSagerBorder\" colspan=\"6\" title=\"klik her for at oprette et nyt tilbud til opgaven\"><p><a class=\"button green small\" href=\"../sager/tilbud.php?sag_id=$sag_id&amp;konto_id=$konto_id&amp;opgave_id=$opgave_id&amp;nyt_tilbud=0\">Nyt Tilbud</a></p></td></tr>\n";
				}
			print "</tbody></table>\n";
			print "</div><!-- end of contentA -->\n";
			print "<div class=\"clear\"></div>\n";
			}*/
		print "<hr>
		<div class=\"contentA\">
			<input class=\"button gray medium\" type=\"submit\" name=\"opdater\" value=\"Opdater\">";
			print "<input class=\"button rosy medium\" style=\"float:right\" type=\"submit\" name=\"slet_opgave\" value=\"Slet opgave\" onclick=\"return confirm('Vil du slette denne opgave?');\">
		</div><!-- end of contentA -->";
	print "</form>\n";
	print "</div><!-- end of content -->\n";
}

function ret_sag() {
	global $brugernavn;
	global $db;

	$id=if_isset($_GET['sag_id']);
	$konto_id=if_isset($_GET['konto_id']);
	if(isset($_POST['konto_id'])) $konto_id = $_POST['konto_id'];
	if (!$konto_id) { header("location:sager.php?funktion=opret_sag"); exit(); }
#	$ret_sag=if_isset($_GET['ret_sag']);
	if (!$id && $konto_id) {
		$r=db_fetch_array(db_select("select max(sagsnr) as sagsnr from sager",__FILE__ . " linje " . __LINE__));
		$sagsnr=$r['sagsnr']+1; 
		$tidspkt=date('U');
		if($r=db_fetch_array(db_select("select * from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__))) {
			db_modify("insert into sager(konto_id,kontonr,firmanavn,addr1,addr2,postnr,bynavn,kontakt,status,tidspkt,hvem,oprettet_af,sagsnr) values ('$konto_id','".db_escape_string($r['kontonr'])."','".db_escape_string($r['firmanavn'])."','".db_escape_string($r['addr1'])."','".db_escape_string($r['addr2'])."','".db_escape_string($r['postnr'])."','".db_escape_string($r['bynavn'])."','".db_escape_string($r['kontakt'])."','$status','$tidspkt','$brugernavn','$brugernavn','$sagsnr')",__FILE__ . " linje " . __LINE__);
			$r=db_fetch_array(db_select("select id from sager where tidspkt='$tidspkt' and hvem='$brugernavn'",__FILE__ . " linje " . __LINE__));
			$id=$r['id'];
		}
	}
	if (isset($_POST['opdater']) && $id) {
		$sagsnr=db_escape_string($_POST['sagsnr']);
		$beskrivelse=db_escape_string($_POST['beskrivelse']);
		$udf_firmanavn=db_escape_string($_POST['udf_firmanavn']);
		$udf_addr1=db_escape_string($_POST['udf_addr1']);
		$udf_addr2=db_escape_string($_POST['udf_addr2']);
		$udf_postnr=db_escape_string($_POST['udf_postnr']);
		$udf_bynavn=db_escape_string($_POST['udf_bynavn']);
		$omfang=db_escape_string($_POST['omfang']);
		$kontakt=db_escape_string($_POST['kontakt']);
		$status=db_escape_string($_POST['status']);
		$ref=db_escape_string($_POST['ref']);
		$kunde_ref_nr=db_escape_string($_POST['kunde_ref_nr']);
		$posnr=$_POST['posnr'];
 		$ans_id=$_POST['ans_id'];
 		$ans_ant=$_POST['ans_ant'];
 		$sag_planfraop=$_POST['planfraop']; 
 		$sag_plantilop=$_POST['plantilop'];
 		($sag_planfraop)?$planfraop=usdate($sag_planfraop):$planfraop=NULL;
 		($sag_plantilop)?$plantilop=usdate($sag_plantilop):$plantilop=NULL;
 		//$sag_planfraned=$_POST['planfraned']; 
 		//$sag_plantilned=$_POST['plantilned'];
 		//($sag_planfraned)?$planfraned=usdate($sag_planfraned):$planfraned=NULL;
 		//($sag_plantilned)?$plantilned=usdate($sag_plantilned):$plantilned=NULL;
		$beregn_opret=$_POST['beregn_opret'];
		$beregn_tilbud=$_POST['beregn_tilbud'];
		($beregn_opret)?$beregnopret=usdate($beregn_opret):$beregnopret=NULL;
 		($beregn_tilbud)?$beregntilbud=usdate($beregn_tilbud):$beregntilbud=NULL;
		$beregner=db_escape_string($_POST['beregner']);
		$beregn_beskrivelse=db_escape_string($_POST['beregn_beskrivelse']);
		
		if (!$planfraop && $plantilop) { 
			$planfraop=$plantilop;
		} 
		if ($planfraop && !$plantilop) {
			$plantilop=$planfraop;
		} 
		/*
		if (!$planfraned && $plantilned) { 
			$planfraned=$plantilned;
		} 
		if ($planfraned && !$plantilned) {
			$plantilned=$planfraned;
		} 
		*/
		if (!is_numeric($sagsnr))
		{
			$messages = "Skal være et tal";
		} elseif ($r=db_fetch_array(db_select("SELECT id FROM sager WHERE sagsnr='$sagsnr' AND id != '$id'",__FILE__ . " linje " . __LINE__))){
			$messages = "Sagsnummer eksisterer i forvejen ";
		} else {
			if ($udf_postnr && !$udf_bynavn) $udf_bynavn=bynavn($udf_postnr);	
			db_modify("update sager set sagsnr='$sagsnr',beskrivelse='$beskrivelse',omfang='$omfang',udf_firmanavn='$udf_firmanavn',udf_addr1='$udf_addr1',udf_addr2='$udf_addr2',udf_postnr='$udf_postnr',udf_bynavn='$udf_bynavn',kontakt='$kontakt',status='$status',ref='$ref',kunde_ref_nr='$kunde_ref_nr',planfraop='$planfraop',plantilop='$plantilop',planfraned='$planfraned',plantilned='$plantilned',beregn_opret='$beregnopret',beregn_tilbud='$beregntilbud',beregner='$beregner',beregn_beskrivelse='$beregn_beskrivelse' where id = '$id'",__FILE__ . " linje " . __LINE__);
			
			for ($x=1; $x<=$ans_ant; $x++) {
 	 	 	 	$y=trim($posnr[$x]);
 	 	 	 	if ($y && is_numeric($y) && $ans_id[$x]) db_modify("update ansatte set posnr = '$y' where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);
 	 	 	 	elseif (($y=="-")&&($ans_id[$x])){db_modify("delete from ansatte 	where id = '$ans_id[$x]'",__FILE__ . " linje " . __LINE__);}
 	 	 	 	else {
					$alerttekst=findtekst(352,$sprog_id);
					print "<BODY onLoad=\"javascript:alert('$alerttekst')\"><!--tekst 352-->\n";
				}
 	 	 	}
 	 	 	
			// Her tjekker vi om 'sag_id' er i loen
			if($r=db_fetch_array(db_select("select sag_id from loen where sag_id='$id'",__FILE__ . " linje " . __LINE__))) {
			
				// Her finder vi ud af hvor mange id'er i tabellen 'loen', som har den samme 'sag_id'
				$x=0;
				$q = db_select("select id from loen where sag_id='$id'",__FILE__ . " linje " . __LINE__);
				while ($r = db_fetch_array($q)) {
					$loenid[$x]=$r['id'];
					$x++;
				}
				
				// Her tæller vi id'erne, og opdatere 'loen' antal gange med 'ref'
				for ($x=1;$x<=count($loenid);$x++) { 
					//if (!$sagid[$x]==NULL) {
						db_modify("update loen set sag_ref = '$ref' where sag_id = '$id'",__FILE__ . " linje " . __LINE__);
					//}
				}
			}
		}
	} elseif (isset($_POST['slet_sag']) && $id) {
		db_modify("delete from sager where id = '$id'",__FILE__ . " linje " . __LINE__);
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/sager.php\">";
	}

	$r=db_fetch_array(db_select("select * from sager where id='$id'",__FILE__ . " linje " . __LINE__));
	$sagsnr=$r['sagsnr']*1;
	$firmanavn=htmlspecialchars($r['firmanavn']);
	$addr1=htmlspecialchars($r['addr1']);
	$postnr=$r['postnr'];
	$bynavn=htmlspecialchars($r['bynavn']);
	$beskrivelse=htmlspecialchars($r['beskrivelse']);
	$omfang=htmlspecialchars($r['omfang']);
	$kontakt=htmlspecialchars($r['kontakt']);
	$status=htmlspecialchars($r['status']);
	$udf_firmanavn=htmlspecialchars($r['udf_firmanavn']);
	$udf_addr1=htmlspecialchars($r['udf_addr1']);
	$udf_addr2=htmlspecialchars($r['udf_addr2']);
	$udf_postnr=$r['udf_postnr'];
	$udf_bynavn=htmlspecialchars($r['udf_bynavn']);
	$ref=htmlspecialchars($r['ref']);
	$kunde_ref_nr=htmlspecialchars($r['kunde_ref_nr']);
	$oprettet_af=htmlspecialchars($r['oprettet_af']);
	$dato=date("d-m-y",$r['tidspkt']);
	$tid=date("H:i",$r['tidspkt']);
	$planfraop=dkdato($r['planfraop']);
	$plantilop=dkdato($r['plantilop']);
	//$planfraned=dkdato($r['planfraned']);
	//$plantilned=dkdato($r['plantilned']);
	$beregn_opret=dkdato($r['beregn_opret']);
	$beregn_tilbud=dkdato($r['beregn_tilbud']);
	$beregner=htmlspecialchars($r['beregner']);
	$beregn_beskrivelse=htmlspecialchars($r['beregn_beskrivelse']);
	
	
	#20151019-1
	$statcolor = NULL;
	if ($status=='Beregning') $statcolor = "status_color1";
	if ($status=='Tilbud') $statcolor = "status_color2";
	if ($status=='Ordrebekræftelse') $statcolor = "status_color3";
	if ($status=='Montage') $statcolor = "status_color4";
	if ($status=='Godkendt') $statcolor = "status_color5";
	if ($status=='Afmeldt') $statcolor = "status_color6";
	if ($status=='Drivgods') $statcolor = "status_color7";
	if ($status=='Afsluttet') $statcolor = "status_color8";
	$color = array("status_color1","status_color2","status_color3","status_color4","status_color5","status_color6","status_color7","status_color8");
	
	/* Query til ansatte (externe kontaktpersoner) */
	// Query til stilladstype
	$x=0;
	$q=db_select("select * from grupper where art='V_CAT' order by box1",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$v_cat[$x]=htmlspecialchars($r['box1']);
		$x++;
	}
	
	// Query til kunde kontakt
	$x=0;
	$q=db_select("select * from ansatte where konto_id='$konto_id' order by posnr",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$k_kontakt[$x]=htmlspecialchars($r['navn']);
		$x++;
	}
	
	// Query til kundes kontaktoplysninger
	$r=db_fetch_array(db_select("select * from ansatte where navn='$kontakt' and konto_id='$konto_id'",__FILE__ . " linje " . __LINE__));
	$kontakt_id=$r['id'];
	$kontakt_telefon=$r['mobil'];
	$kontakt_email=$r['email'];
	(!$kontakt_telefon)?$kontakt_telefon='<i>Ingen telefonnummer</i>':$kontakt_telefon;
	(!$kontakt_email)?$kontakt_email='<i>Ingen emailadresse</i>':$kontakt_email;
	
	// Query til ansvarlig
	$x=0;
	$q=db_select("select * from grupper where art='brgrp'",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$gruppe_id[$x]=$r['id']*1;
		$rettigheder[$x]=(substr($r['box2'],2,1)); //finder opret/ret sag rettighed
		if($rettigheder[$x]==1) $gruppeid[$x]=$gruppe_id[$x]; // finder de gruppe_id'er som har rettighed til opret/ret sag
	} 
	
	$in_str = "'".implode("', '", $gruppeid)."'"; // formatere '$gruppeid[]' til f.eks. '52','77' osv.
	
	$x=0;
	$q=db_select("select * from ansatte where konto_id=1 and gruppe IN ($in_str) order by id",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$ansvarlig[$x]=htmlspecialchars($r['navn']);
		$x++;
	}
	
	// query til kunde-info
	$r=db_fetch_array(db_select("select * from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
	$kunde_tlf=$r['tlf'];
	$kunde_email=$r['email'];
	$kunde_tlf_chk=$r['tlf'];
	$kunde_email_chk=$r['email'];
	(!$kunde_tlf)?$kunde_tlf='<i>Ingen telefonnummer</i>':$kunde_tlf;
	(!$kunde_email)?$kunde_email='<i>Ingen emailadresse</i>':$kunde_email;
	
	/*
	// Query til status
	$x=0;
	$q = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '0' order by fase",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$tjek_id[$x]=$r['id'];
		$tjek_sub_id[$x]=$r['sub_id'];
		$tjek_punkt[$x]=htmlspecialchars($r['tjekpunkt']); 
		$tjek_fase[$x]=$r['fase']*1;
		$x++;
	}
	*/
	
	/* Query til opg_status
	---------------------------------------
	*/
	#20151019-1
	 //Her ændres tekst til status 
	$sag_status_tekst="Beregning,Tilbud,Ordrebekræftelse,Montage,Godkendt,Afmeldt,Drivgods,Afsluttet"; // gammel status "Opm&aring;ling,Tilbud,Ordre modtaget,Montage,Aflevering,Afmeldt,Demontage,Afsluttet"
	
	if ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__)) && ($r['box1']==$sag_status_tekst)) {
		$sag_status=explode(chr(44),$r['box1']);
	} elseif ($r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__)) && ($r['box1']!=$sag_status_tekst)) {
		db_modify("update grupper set box1='$sag_status_tekst' where art='SAGSTAT'",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
		$sag_status=explode(chr(44),$r['box1']);
	} else { 
		db_modify("insert into grupper (art,box1) values ('SAGSTAT','$sag_status_tekst')",__FILE__ . " linje " . __LINE__);
		$r=db_fetch_array(db_select("select * from grupper where art = 'SAGSTAT'",__FILE__ . " linje " . __LINE__));
		$sag_status=explode(chr(44),$r['box1']);
	}
	
	if (!$sagsnr) {
		$r=db_fetch_array(db_select("select max(sagsnr) as sagsnr from sager",__FILE__ . " linje " . __LINE__));
		$sagsnr=$r['sagsnr']+1;
	}
	$r=db_fetch_array(db_select("select count(id) as bilag from bilag where assign_to='sager' and assign_id='$id'",__FILE__ . " linje " . __LINE__));
	$bilag=$r['bilag'];
	$r=db_fetch_array(db_select("select count(id) as noter from noter where assign_to='sager' and assign_id='$id'",__FILE__ . " linje " . __LINE__));
	$noter=$r['noter'];
		$qtxt="select count(id) as tjekpunkter from tjekpunkter where assign_id ='$id'";
#echo "$qtxt<br>";
		$r=db_fetch_array(db_select("select count(id) as tjekpunkter from tjekpunkter where assign_id ='$id'",__FILE__ . " linje " . __LINE__));
	$tjekpunkter=$r['tjekpunkter'];
#echo "$tjekpunkter<br>";
	
	print "<div class=\"content\">";
	print "<div id=\"printableArea\">\n";
	print "<form method=\"post\" action=\"sager.php?konto_id=$konto_id&amp;sag_id=$id&amp;funktion=ret_sag\">";
	//echo "Status $sag_status[$x]";
	print "
	<div style=\"float:left; margin-right:70px; width:379px;\" class=\"printSagsnrBox\">
		<h3 class=\"printHeadlineSagInfo\" >Sags information:</h3>
		<div class=\"contentA\">
			<div class=\"row printDisplayNone\">
				<div class=\"left \">Sagsnr</div>
				<div class=\"right \"><input type=\"text\" class=\"text textIndent\" id=\"sagsnr\" name=\"sagsnr\" value=\"$sagsnr\"/><i style=\"color:red;\">$messages&nbsp;</i></div>
				<div class=\"clear\"></div>
			</div>
			<div class=\"contentNone printSagsnr\">Sagsnr: <span class=\"printSagsnrTxt\">$sagsnr</span></div>
		</div><!-- end of contentA -->
	</div><!-- end of left container -->
	<div style=\"float:left; width:379px;\">
		<h3>&nbsp;</h3>
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Ansvarlig</div>";
							print "<div class=\"right\"><select style=\"width:194px;\" id=\"ref\" name=\"ref\" class=\"printSelect3 printSagInfoBg\">\n";
							for ($x=0;$x<=count($ansvarlig);$x++) {
								if ($ref==$ansvarlig[$x]) print "<option value=\"$ansvarlig[$x]\">$ansvarlig[$x]&nbsp;</option>\n";	
							} 
							for ($x=0;$x<=count($ansvarlig);$x++) {
								if ($ref!=$ansvarlig[$x]) print "<option value=\"$ansvarlig[$x]\">$ansvarlig[$x]&nbsp;</option>\n";	
							}
							print "</select>
				</div>
			</div>
			<div class=\"row\">
				<div class=\"left\">Status</div>";
							print "<div class=\"right\"><select onchange=\"this.className=this.options[this.selectedIndex].className\" style=\"width:194px;\" class=\"$statcolor printSelect3\" id=\"status\" name=\"status\">\n";
							for($y=0;$y<count($sag_status);$y++) {
								if ($status==$sag_status[$y]) print "<option class=\"$color[$y]\" value=\"$sag_status[$y]\">$sag_status[$y]</option>\n";
							}
							for($y=0;$y<count($sag_status);$y++) {
								if ($status!=$sag_status[$y]) print "<option class=\"$color[$y]\" value=\"$sag_status[$y]\">$sag_status[$y]</option>\n";
							}
							print "</select>
				</div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of right container -->";
	
	// indtast til planlaeg_beregning
	if ($status=='' || $status=='Beregning') {
	print "
	<div class=\"clear\"></div>
	<hr>
	<div style=\"float:left; width:828px;\">
		<h3>Planlægnings information:</h3>
		<div class=\"contentA\">
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p><i>Her tastes informationer til beregning, som vises under planlægning</i></p></div>
		</div><!-- end of contentA -->
	</div><!-- end of full container -->
	<div class=\"clear\"></div>
	<div style=\"float:left; margin-right:70px; width:379px;\">
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Sag oprettet: </div>
				<div class=\"rightSmall\"><input name=\"beregn_opret\" id=\"beregn_opret\" type=\"text\" class=\"textMedium textIndent printSagInfoText\" value=\"$beregn_opret\"/></div>
				<div class=\"clear\"></div>
			</div>
			<div class=\"row\">
				<div class=\"left\">Tilbud gives:</div>
				<div class=\"rightSmall\"><input name=\"beregn_tilbud\" id=\"beregn_tilbud\" type=\"text\" class=\"textMedium textIndent printSagInfoText\" value=\"$beregn_tilbud\"/></div>
				<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of left container -->
	<div style=\"float:left; width:379px;\">
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Beregner</div>";
							print "<div class=\"right\"><select style=\"width:194px;\" id=\"beregner\" name=\"beregner\" class=\"printSelect3\">\n";
							for ($x=0;$x<=count($ansvarlig);$x++) {
								if ($beregner==$ansvarlig[$x]) print "<option value=\"$ansvarlig[$x]\">$ansvarlig[$x]&nbsp;</option>\n";	
							} 
							for ($x=0;$x<=count($ansvarlig);$x++) {
								if ($beregner!=$ansvarlig[$x]) print "<option value=\"$ansvarlig[$x]\">$ansvarlig[$x]&nbsp;</option>\n";	
							}
							print "</select>
				</div>
			</div>
			<div class=\"row\">
				<div class=\"left\">Beskrivelse</div>
				<div class=\"right\"><input type=\"text\" class=\"text textIndent printSagInfoText\" id=\"beregn_beskrivelse\" name=\"beregn_beskrivelse\" value=\"$beregn_beskrivelse\"/></div>
				<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of right container -->";
		if ($beregn_opret) {
			print "<div class=\"contentA printDisplayNone\" style=\"float:right;\">
				<a href=\"planlaeg_beregning.php\" class=\"button gray small\">Planlæg beregning</a>
			</div><!-- end of full container -->\n";
		}
		print "<input type=\"hidden\" name=\"planfraop\" value=\"$planfraop\"><input type=\"hidden\" name=\"plantilop\" value=\"$plantilop\">"; // fra planlaeg_sager
	
	} elseif ($status!='' && $status!='Beregning' && $status!='Tilbud' && $status!='Afsluttet') { #20151019-1 // Indtast til planlaeg_sager
	print "
	<div class=\"clear\"></div>
	<hr>
	<div style=\"float:left; width:828px;\">
		<h3>Planlægnings information:</h3>
		<div class=\"contentA\">
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p><i>Her tastes den planlagte start og slut dato for sagen, som vises under planlægning</i></p></div>
		</div><!-- end of contentA -->
	</div><!-- end of full container -->
	<div class=\"clear\"></div>
	<div style=\"float:left; margin-right:70px; width:379px;\">
		<div class=\"contentA\">
			<div class=\"left\"></div>
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;color:#cd3300;\">Løbetid for hele sagen</p></div>
		</div><!-- end of contentA -->
		<div class=\"clear\"></div>
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Start / Slut: </div>
				<div class=\"rightSmall\"><input name=\"planfraop\" id=\"planfraop\" type=\"text\" class=\"textMedium textIndent printSagInfoText\" value=\"$planfraop\"/></div>
				<div class=\"rightSmall\"><input name=\"plantilop\" id=\"plantilop\" type=\"text\" class=\"textMedium textIndent printSagInfoText\" value=\"$plantilop\"/></div>
				<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of left container -->";
		if ($planfraop) {
			print "<div style=\"float:left; width:379px;\">
				<div class=\"contentA printDisplayNone\" style=\"float:right;padding-top:55px;\">
					<a href=\"planlaeg_sager.php\" class=\"button gray small\">Planlæg sager</a>
				</div><!-- end of contentA -->
			</div><!-- end of right container -->\n";
		}
		print "<input type=\"hidden\" name=\"beregn_opret\" value=\"$beregn_opret\"><input type=\"hidden\" name=\"beregn_tilbud\" value=\"$beregn_tilbud\">"; // fra planlaeg_beregning
		print "<input type=\"hidden\" name=\"beregner\" value=\"$beregner\"><input type=\"hidden\" name=\"beregn_beskrivelse\" value=\"$beregn_beskrivelse\">"; // fra planlaeg_beregning
		
	} else {
		print "<input type=\"hidden\" name=\"planfraop\" value=\"$planfraop\"><input type=\"hidden\" name=\"plantilop\" value=\"$plantilop\">"; // fra planlaeg_sager
		print "<input type=\"hidden\" name=\"beregn_opret\" value=\"$beregn_opret\"><input type=\"hidden\" name=\"beregn_tilbud\" value=\"$beregn_tilbud\">"; // fra planlaeg_beregning
		print "<input type=\"hidden\" name=\"beregner\" value=\"$beregner\"><input type=\"hidden\" name=\"beregn_beskrivelse\" value=\"$beregn_beskrivelse\">"; // fra planlaeg_beregning
		//print "<input type=\"hidden\" name=\"planfraned\" value=\"$planfraned\"><input type=\"hidden\" name=\"plantilned\" value=\"$plantilned\">";
	}
	/*print "
	</div><!-- end of left container -->";*/
	/*print "<div style=\"float:left; width:379px;\">
		<div class=\"contentA\">
			<div class=\"left\"></div>
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;color:#cd3300;\">Demontage</p></div>
		</div><!-- end of contentA -->
		<div class=\"clear\"></div>
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Start / Slut: </div>
				<div class=\"rightSmall\"><input name=\"planfraned\" id=\"planfraned\" type=\"text\" class=\"textMedium\" value=\"$planfraned\"/></div>
				<div class=\"rightSmall\"><input name=\"plantilned\" id=\"plantilned\" type=\"text\" class=\"textMedium\" value=\"$plantilned\"/></div>
				<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of right container -->";*/
	/*print "
	<div class=\"clear\"></div>
	<hr>
	<div style=\"float:left; width:828px;\">
		<h3>Planlægnings information:</h3>
		<div class=\"contentA\">
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p><i>Her tastes den planlagte start og slut dato for montage og demontage af stilladset, som vises i planlægningsskemaet</i></p></div>
		</div><!-- end of contentA -->
	</div><!-- end of full container -->
	<div class=\"clear\"></div>
	<div style=\"float:left; margin-right:70px; width:379px;\">
		<div class=\"contentA\">
			<div class=\"left\"></div>
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;color:#cd3300;\">Montage</p></div>
		</div><!-- end of contentA -->
		<div class=\"clear\"></div>
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Start / Slut: </div>
				<div class=\"rightSmall\"><input name=\"planfraop\" id=\"planfraop\" type=\"text\" class=\"textMedium\" value=\"$planfraop\"/></div>
				<div class=\"rightSmall\"><input name=\"plantilop\" id=\"plantilop\" type=\"text\" class=\"textMedium\" value=\"$plantilop\"/></div>
				<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of left container -->";
	print "<div style=\"float:left; width:379px;\">
		<div class=\"contentA\">
			<div class=\"left\"></div>
			<div style=\"float:left; padding:5px 0px 0px 7px;\"><p style=\"font-size:14px;color:#cd3300;\">Demontage</p></div>
		</div><!-- end of contentA -->
		<div class=\"clear\"></div>
		<div class=\"contentA\">
			<div class=\"row\">
				<div class=\"left\">Start / Slut: </div>
				<div class=\"rightSmall\"><input name=\"planfraned\" id=\"planfraned\" type=\"text\" class=\"textMedium\" value=\"$planfraned\"/></div>
				<div class=\"rightSmall\"><input name=\"plantilned\" id=\"plantilned\" type=\"text\" class=\"textMedium\" value=\"$plantilned\"/></div>
				<div class=\"clear\"></div>
			</div>
		</div><!-- end of contentA -->
	</div><!-- end of right container -->";*/
	
	print "
	<div class=\"clear\"></div>
	<hr>";
	print "<div style=\"float:left; margin-right:70px; width:379px;#background-color:lightgreen;\">
		<h3>Opstillings information:</h3>
		<div class=\"contentA\">
				<div class=\"row\">
						<div class=\"left\">Stilladstype</div>";
							print "<div class=\"right\"><select style=\"width:194px;\" id=\"beskrivelse\" name=\"beskrivelse\" class=\"printSelect3\">\n";
							for ($x=0;$x<=count($v_cat);$x++) {
								if ($beskrivelse==$v_cat[$x]) print "<option value=\"$v_cat[$x]\">$v_cat[$x]&nbsp;</option>\n";	
							}
							for ($x=0;$x<=count($v_cat);$x++) {
								if ($beskrivelse!=$v_cat[$x]) print "<option value=\"$v_cat[$x]\">$v_cat[$x]&nbsp;</option>\n";	
							}
							print "</select></div>\n";

						#<div class=\"right\"><input type=\"text\" class=\"text\" id=\"beskrivelse\" name=\"beskrivelse\" value=\"$beskrivelse\"/></div>
						print "<div class=\"clear\"></div>
				</div>
				<!--<div class=\"row\">
						<div class=\"left\">Firmanavn</div>
						<div class=\"right\"><input type=\"text\" class=\"text\" id=\"udf_firmanavn\" name=\"udf_firmanavn\" value=\"$udf_firmanavn\"/></div>
						<div class=\"clear\"></div>
				</div>-->
				<div class=\"row\">
						<div class=\"left\">Adresse</div>
						<div class=\"right\"><input type=\"text\" class=\"text textIndent printSagInfoText\" id=\"udf_addr1\" name=\"udf_addr1\" value=\"$udf_addr1\"/></div>
						<div class=\"clear\"></div>
				</div>
				<div class=\"row\">
						<div class=\"left\">Adresse 2</div>
						<div class=\"right\"><input type=\"text\" class=\"text textIndent printSagInfoText\" id=\"udf_addr2\" name=\"udf_addr2\" value=\"$udf_addr2\"/></div>
						<div class=\"clear\"></div>
				</div>
				<div class=\"row\">
						<div class=\"left\">Postnr. &amp; by</div>
						<div class=\"right\"><input type=\"text\" class=\"textSmall textIndent printSagInfoText\" id=\"udf_postnr\" name=\"udf_postnr\" value=\"$udf_postnr\"/><input type=\"text\" class=\"textMediumLarge textSpace textIndent printSagInfoText\" id=\"udf_bynavn\" name=\"udf_bynavn\" value=\"$udf_bynavn\"/></div>
						<div class=\"clear\"></div>
				</div>
		</div><!-- end of contentA -->
	</div><!-- end of left container -->
<div class=\"clear\"></div>";
print "<hr>
	<div style=\"float:left; margin-right:70px; width:379px;\">
		<h3>Kunde information:</h3>
		<div class=\"contentAB\" style=\"#background-color:lightblue;\">
				<div class=\"row\">
						<div class=\"left\">Kunde:</div>
						<div class=\"right printSagInfoIndent\"><b>$firmanavn</b></div>
						<div class=\"clear\"></div>
				</div>";
				if($kunde_tlf_chk || $kunde_email_chk) {
				print "
				<div class=\"row\">
						<div class=\"left\">Kunde Tlf:</div>
						<div class=\"right printSagInfoIndent\">$kunde_tlf</div>
						<div class=\"clear\"></div>
				</div>";
				}
			print "
		</div><!-- end of contentAB -->
	</div><!-- end of left container -->
	<div style=\"float:left; width:379px;\">
		<h3>&nbsp;</h3>
		<div class=\"contentAB\">
				<div class=\"row\">
					<div class=\"left\">Kunde ref. nr.</div>
					<div class=\"right\"><input type=\"text\" class=\"text textIndent printSagInfoText\" id=\"kunde_ref_nr\" name=\"kunde_ref_nr\" value=\"$kunde_ref_nr\"/></div>
					<div class=\"clear\"></div>
				</div>";
				if($kunde_tlf_chk || $kunde_email_chk) {
				print "
				<div class=\"row\">
						<div class=\"left\">Kunde email:</div>
						<div class=\"right printSagInfoIndent\">$kunde_email</div>
						<div class=\"clear\"></div>
				</div>";
				}
			print "
		</div><!-- end of contentAB -->
	</div><!-- end of right container -->
	<div style=\"float:left; width:828px;\">
		<div class=\"contentAA\" style=\"width: 808px;#background-color:red;\">
				<div class=\"row\">
						<div class=\"left\">Generelt for sagen:</div>
						<div class=\"right\"><textarea rows=\"3\" cols=\"76\" style=\"min-width:681px;max-width:681px;\" class=\"textArea printSagInfoTextArea\" name=\"omfang\">$omfang</textarea></div>
						<div class=\"clear\"></div>
				</div>
		</div><!-- end of contentAA -->
	</div><!-- end of full container -->
	<div style=\"float:left; margin-right:70px; width:379px;\">
		<div class=\"contentBA\">
				<div class=\"row\">
						<div class=\"left\">Kontakt:</div>";
						print "<div class=\"right\"><select style=\"width:194px;\" id=\"kontakt\" name=\"kontakt\" class=\"printSelect3\">\n";
						for ($x=0;$x<=count($k_kontakt);$x++) {
							if ($kontakt==$k_kontakt[$x]) print "<option value=\"$k_kontakt[$x]\">$k_kontakt[$x]&nbsp;</option>\n";	
						}
						for ($x=0;$x<=count($k_kontakt);$x++) {
							if ($kontakt!=$k_kontakt[$x]) print "<option value=\"$k_kontakt[$x]\">$k_kontakt[$x]&nbsp;</option>\n";	
						}
						//print "<option><p>$kontakt</p></option>";
						print "</select></div>
					
						<div class=\"clear\"></div>
				</div>\n";
				
				if($kontakt_id){
			print "
			<div class=\"row\">
				<div class=\"left\">Kontakt Tlf:</div>
				<div class=\"right printSagInfoIndent\">$kontakt_telefon</div>
				<div class=\"clear\"></div>
			</div>
			</div><!-- end of contentBA -->
		</div><!-- end of left container -->
		<div style=\"float:left; width:379px;\">
			<div class=\"contentBA\">
			<div class=\"row\">
				<div class=\"left\">&nbsp;</div>
				<div class=\"right\">&nbsp;</div>
				<div class=\"clear\"></div>
			</div>
			<div class=\"row\">
				<div class=\"left\">Kontakt email:</div>
				<div class=\"right printSagInfoIndent\">$kontakt_email</div>
				<div class=\"clear\"></div>
			</div>\n";
			}
			print "
			</div><!-- end of contentBA -->
		</div><!-- end of right or left container -->
	<div class=\"clear\"></div>
	<hr>";
		$r=db_fetch_array(db_select("SELECT * FROM ansatte WHERE sag_id = '$id'",__FILE__ . " linje " . __LINE__));#  WHERE sag_id = '$id'
		$check_ansat_id=$r['id'];
		if ($check_ansat_id) {
			print "<h3>Kontaktpersoner til sagen</h3>
			<div class=\"contentkontakt\">
			<ul><li>
				<span class=\"pos\" title=\"\"><b>Pos.</b></span>
				<span class=\"kontakt\"><b>Kontakt</b></span>
				<span class=\"lokal\" title=\"\"><b>Direkte/lokal</b></span>
				<span class=\"mobil\"><b>Mobil</b></span>
				<span class=\"email\"><b>E-mail</b></span>
			</li></ul>
			<ul class=\"contentkontaktbody contentkontaktborder printSagInfo\">\n";
			$x=0;
			$q = db_select("SELECT * FROM ansatte WHERE sag_id = '$id' ORDER BY posnr",__FILE__ . " linje " . __LINE__);
			while ($r = db_fetch_array($q)){
				$x++;
				//if (strpos($_SERVER['PHP_SELF'],"kunder.php")) $href="<a href=\"kunder.php?konto_id=$id&amp;ansat_id=$r[id]&amp;funktion=ret_kunde_ansat\">";
				$href="<a href=\"sager.php?konto_id=$konto_id&amp;sag_id=$id&amp;ansat_id=$r[id]&amp;funktion=sag_kontakt\">";
				print "<li>
				<span class=\"pos\"><input class=\"textXSmall printSagInfoText\" type=\"text\" name=\"posnr[$x]\" value=\"$x\"/></span>
				$href<span class=\"kontakt\" title=\"".htmlentities($r['notes'],ENT_COMPAT,$charset)."\">".htmlspecialchars($r['navn'])."</span>
				<span class=\"lokal\">$r[tlf]&nbsp;</span>
				<span class=\"mobil\">$r[mobil]&nbsp;</span>
				<span class=\"email\">".htmlspecialchars($r['email'])."&nbsp;</span>
				<input type=\"hidden\" name=\"ans_id[$x]\" value=$r[id]>\n";
				//if ($x==1) {print "<input type=\"hidden\" name=\"kontakt\" value=\"".htmlspecialchars($r['navn'])."\">\n";}
				print "</a>\n";
				print "</li>\n";
			}
			print "<li style=\"display:none;\">&nbsp;</li>";
			print "</ul>\n";
			print "<input type=\"hidden\" name=\"ans_ant\" value=$x>\n";
			print "</div><!-- end of contentkontakt -->\n";
			print "<div class=\"clear\"></div>\n";
			print "<div class=\"contentA\" style=\"float:right;\">\n";
			//if (strpos($_SERVER['PHP_SELF'],"kunder.php")) $href="<a href=\"kunder.php?konto_id=$id&amp;ansat_id=0&amp;funktion=ret_kunde_ansat\" class=\"button blue small\">";
			$href="<a href=\"sager.php?konto_id=$konto_id&amp;sag_id=$id&amp;funktion=sag_kontakt\" class=\"button blue small printDisplayNone\">";
			print "$href".findtekst(669,$sprog_id)."<!--tekst 669--></a>\n";
			print "</div>\n";
		} else {
			print "<h3 class=\"printDisplayNone\">Opret kontaktperson her</h3>\n";
			print "<div class=\"contentA\" style=\"float:left;\">\n";
			//if (strpos($_SERVER['PHP_SELF'],"kunder.php")) $href="<a href=\"kunder.php?konto_id=$id&amp;ansat_id=0&amp;funktion=ret_kunde_ansat\" class=\"button blue small\">";
			$href="<a href=\"sager.php?konto_id=$konto_id&amp;sag_id=$id&amp;funktion=sag_kontakt\" class=\"button blue small printDisplayNone\">";
			print "$href".findtekst(669,$sprog_id)."<!--tekst 669--></a>\n";
			print "</div>\n";
		}
	
	print "<div class=\"clear\"></div>
	<hr class=\"printDisplayNone\">
	<div class=\"contentA\">
		<input class=\"button gray medium printDisplayNone\" type=\"submit\" name=\"opdater\" value=\"Opdater\">";
	if (!$noter && !$tjekpunkter && !$bilag) { #skal laves om. flere ting er på en sag nu f. eks opgaver, ordre...
		print "<input class=\"button rosy medium printDisplayNone\" style=\"float:right\" type=\"submit\" name=\"slet_sag\" value=\"Slet sag\" onclick=\"return confirm('Vil du slette denne sag?');\">
		</div><!-- end of contentA -->";
	} else {
		print "</div><!-- end of contentA -->\n";
	}
print "</form>\n";
print "</div><!-- end of printContents -->\n";
print "</div><!-- end of content -->\n";
}# endfunc vis_sag

/*
function kontrolskema() {
// Tjekliste består at flg.pkt.
//	id : fortløbende nr.
//	tjekpunkt : Navn på punkt
//	Fase :  Hvorlangt sagen er nået - Opstart, tilbud, ordre mm. 
//	Assign_id : Id i tjekliste som punktet er underlagt. (Der er 3 niveauer) 
//	Assign_to :I dette tilfælde, 'sager', forberedt for andet.

// Tjekpunkter består at flg.pkt.
//	id : fortløbende nr.
//	tjekliste_id : ID på tjekpunkt i tjekliste -Hvis denne eksisterer er punket afmærket, ellers ikke.
//	assign_id: Sagen punktet tilhører

	$sag_id=$_GET['sag_id'];
	$sag_fase=$_GET['sag_fase'];
	if(isset($opgave_id)) $_GET['opgave_id'];

	global $sprog_id;
	global $bgcolor;
	global $bgcolor5;

	$linjebg1="ffffff";
	$linjebg2="f0f0f0";
	$r = db_fetch_array(db_select("select status from sager where id = '$sag_id'",__FILE__ . " linje " . __LINE__));
	($sag_fase<$r['status'])?$disabled="DISABLED=\"disabled\"":$disabled=NULL;
	
	if (isset($_POST['kontrolskema'])) {
		$a=0;$b=0;
		$tjekliste_id=if_isset($_POST['tjekliste_id']);
		$kontrolpunkt_id=if_isset($_POST['kontrolpunkt_id']);
		$tjekantal=if_isset($_POST['tjekantal']);
		$kontrolpunkt=if_isset($_POST['kontrolpunkt']);
		$pre_kontrolpunkt=if_isset($_POST['pre_kontrolpunkt']);
		for ($x=1;$x<=$tjekantal;$x++) {
			if ($tjekliste_id[$x]) {
				$a++;	
				if($kontrolpunkt[$x]) $b++;
				if ($kontrolpunkt[$x] && !$pre_kontrolpunkt[$x]) {
					db_modify("insert into tjekpunkter (assign_id,tjekliste_id) values ('$sag_id','$tjekliste_id[$x]')",__FILE__ . " linje " . __LINE__);
				} elseif (!$kontrolpunkt[$x] && $pre_kontrolpunkt[$x]) {
					db_modify("delete from tjekpunkter where tjekliste_id = '$tjekliste_id[$x]'",__FILE__ . " linje " . __LINE__);
				} 
			}
		}
		
		if ($a==$b) {
			$tmp=$sag_fase+1;
			db_modify("update sager set status = '$tmp' where id = '$sag_id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/sager.php?sag_id=$sag_id\">";
		}
	}
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
		$q2 = db_select("select * from tjekliste where assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
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
			$q3 = db_select("select * from tjekliste where id !=$id[$x] and assign_to = 'sager' and assign_id = '$id[$x]' order by tjekpunkt",__FILE__ . " linje " . __LINE__);
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
	$x=0;
	$q = db_select("select * from tjekpunkter where assign_id =$sag_id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$kontrolpunkt[$x]=$r['tjekliste_id'];
	}

	print "<form name=\"diverse\" action=\"sager.php?sag_id=$sag_id&amp;funktion=kontrolskema&amp;sag_fase=$sag_fase\" method=\"post\">\n";
	print "<table width=\"500px\"><tbody>";
	print "<tr><td colspan=\"2\"><hr></td></tr>\n";
	print "<tr bgcolor=\"$linjebg\"><td colspan=\"2\"><b><u>Tjeklister</u></b></td></tr>\n";
	for ($x=1;$x<=count($id);$x++) {
		print "<input type=\"hidden\" name=\"tjekantal\" value='".count($id)."'>\n";
		print "<input type=\"hidden\" name=\"id[$x]\" value='$id[$x]'>\n";
		if (!$gruppe_id[$x] && !$punkt_id[$x]) {
			$bgcolor=$linjebg1;
			print "<tr bgcolor=\"$bgcolor\"><td colspan=\"2\"><big><b>$tjekpunkt[$x]</b></big></td></tr>\n";
			$l_id=$id[$x];
		}
		if ($gruppe_id[$x] && !$punkt_id[$x]) { 
			$bgcolor=$linjebg1;
			print "<input type=\"hidden\" name=\"tjekgruppe[$x]\" value='$id[$x]'>\n";
			print "<tr bgcolor=\"$bgcolor\"><td colspan=\"2\" title=\"$assign_id[$x]==$l_id\"><b>".$tjekpunkt[$x]."</b></td></tr>"; #<td><INPUT CLASS=\"inputbox\" TYPE=\"checkbox\" name=\"aktiv[$x]\"></td></tr>\n";
		}
		if ($punkt_id[$x]) { 
			($bgcolor==$linjebg1)?$bgcolor=$linjebg2:$bgcolor=$linjebg1;
			if (!isset($kontrolpunkt[$x])) $kontrolpunkt[$x]=NULL;
			print "<input type=\"hidden\" name=\"tjekliste_id[$x]\" value='$id[$x]'>\n";
			(in_array($id[$x],$kontrolpunkt))?$tmp="checked='checked'":$tmp=NULL;
			print "<input type=\"hidden\" name=\"pre_kontrolpunkt[$x]\" value='$tmp'>\n";
			print "<tr bgcolor=\"$bgcolor\"><td width=\"50px\"><INPUT CLASS=\"inputbox\" $disabled TYPE=\"checkbox\" name=\"kontrolpunkt[$x]\" $tmp></td><td title=\"$assign_id[$x]==$l_id\">".$tjekpunkt[$x]."</td></tr>\n";
		}		
	}	
	print "<tr><td colspan=\"2\"><hr></td></tr>\n";
	print "<tr><td><br></td><td><br></td><td><br></td><td align = \"center\"><input type=\"submit\" accesskey=\"g\" value=\"Gem/opdat&eacute;r\" name=\"kontrolskema\"></td>\n";
	print "</form>\n";
	print "</tbody></table>";
} # endfunc kontrolskema
*/

function kopi_ordre() {

	//$konto_id=$_GET['konto_id'];
	$id=$_GET['sag_id'];
	$ordre_id=$_GET['ordre_id'];


	//echo "konto_id: $konto_id<br>";
	//echo "sag_id: $sag_id<br>";
	//echo "ordre_id: $ordre_id";

	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('sagsnr','firmanavn','udf_addr1','ref','status');
	$vis=if_isset($_GET['vis']);
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	$kopi_ordre_limit=if_isset($_POST['kopi_ordre_limit']);
	
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
		$_SESSION['kopi_ordre_sagsnr']=$sortstyle[0];
		$_SESSION['kopi_ordre_firmanavn']=$sortstyle[1];
		$_SESSION['kopi_ordre_udf_addr1']=$sortstyle[2];
		$_SESSION['kopi_ordre_ref']=$sortstyle[3];
		$_SESSION['kopi_ordre_status']=$sortstyle[4];
	} else {
		$sortstyle[0]=$_SESSION['kopi_ordre_sagsnr'];
		$sortstyle[1]=$_SESSION['kopi_ordre_firmanavn'];
		$sortstyle[2]=$_SESSION['kopi_ordre_udf_addr1'];
		$sortstyle[3]=$_SESSION['kopi_ordre_ref'];
		$sortstyle[4]=$_SESSION['kopi_ordre_status'];
	}
	
	if ($_POST['kopi_ordre_limit']) {
		$_SESSION['kopi_ordre_limit']=$kopi_ordre_limit;
	} else {
		$kopi_ordre_limit=$_SESSION['kopi_ordre_limit'];
	}
	
	
		
	if ($unsetsort) {
		unset($_SESSION['kopi_ordre_sort'],
					$_SESSION['kopi_ordre_sagsnr'],$sortstyle[0],
					$_SESSION['kopi_ordre_firmanavn'],$sortstyle[1],
					$_SESSION['kopi_ordre_udf_addr1'],$sortstyle[2],
					$_SESSION['kopi_ordre_ref'],$sortstyle[3],
					$_SESSION['kopi_ordre_status'],$sortstyle[4],
					$_SESSION['kopi_ordre_limit'],$kopi_ordre_limit
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['kopi_ordre_sort']=$sort;
	else $sort=$_SESSION['kopi_ordre_sort'];
	if (!$sort) $sort="sagsnr%20desc";
	
	$sqlsort=urldecode($sort);
	
	$limitarray=array('500','1000','2500','5000','10000','NULL');
	$limitnavn=array('500','1000','2500','5000','10000','Alle');
	
	($kopi_ordre_limit)?$limit=$kopi_ordre_limit:$limit='500';
	
	//if ($vis=='ordrebekraeftelse') $where="where status ='Ordrebekræftelse'"; 
	if ($vis=='tilbud') $where="where status ='Tilbud' "; 
	elseif ($vis=='aktiv') $where="where status != 'Tilbud' and status != 'Afsluttet'"; #  status !='Ordrebekræftelse' and
	elseif ($vis=='afsluttede') $where="where status ='Afsluttet'";
	else $where='';
	
	$x=0;
	$q=db_select("select * from sager $where order by $sqlsort limit $limit",__FILE__ . " linje " . __LINE__);
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
	
	// Her tæller vi alle sager uden limit
	$x=0;
	$q=db_select("select id from sager",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$x++;
		$alleSagerId[$x]=$r['id'];
		}
	$antal_sager_ialt=$x;
	
	$statcolor = NULL;
	if ($status=='Tilbud') $statcolor = "status_color1";
	if ($status=='Ordrebekræftelse') $statcolor = "status_color2";
	if ($status=='Montage') $statcolor = "status_color3";
	if ($status=='Godkendt') $statcolor = "status_color4";
	if ($status=='Afmeldt') $statcolor = "status_color5";
	if ($status=='Afsluttet') $statcolor = "status_color6";
	$color = array("status_color1","status_color2","status_color3","status_color4","status_color5","status_color6");
	
		print "
		<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"../debitor/ordre.php\" method=\"get\">
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
						
						<td><input class=\"textinput ordre_kopi_sagsnr\" type=\"text\" value=\"\" id=\"ordre_kopi_sagsnr\" name=\"ordre_kopi_sagsnr\" tabindex=\"1\"/></td>
						<td><input class=\"textinput ordre_kopi_firmanavn\" type=\"text\" value=\"\" id=\"ordre_kopi_firmanavn\" name=\"ordre_kopi_firmanavn\" tabindex=\"2\"/></td>
						<td><input class=\"textinput ordre_kopi_adresse\" type=\"text\" value=\"\" id=\"ordre_kopi_adresse\" name=\"ordre_kopi_adresse\" tabindex=\"3\"/></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"sag_id\"></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"konto_id\" value=\"\" name=\"konto_id\"><input type=\"hidden\" value=\"opret_ordre_kopi\" name=\"funktion\"><input type=\"hidden\"  value=\"$ordre_id\" name=\"ordre_id\"></td>   
						<td align=\"center\"><input type=\"submit\" value=\"Kopi til sag\" name=\"findsag\" class=\"button gray small\" tabindex=\"4\"></td>
						
					</tr>
				</tbody>
			</table>
			</form>
			<form name=\"sagliste\" action=\"sager.php?funktion=kopi_ordre\" method=\"post\">
				<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;width:260px;\"><a href=\"sager.php?funktion=kopi_ordre&amp;unsetsort=unset&amp;returside=ordre&amp;sag_id=$id&amp;ordre_id=$ordre_id\" class=\"button gray small\">Slet sortering</a></span>
					<span style=\"#text-align:center;\"><h3><i><b>Vælg sag som tilbud skal kopieres til!</b></i></h3></span>\n";
					($antal_sager_ialt<=500)?$display="display:none;":$display=NULL;
					print "
					<div style=\"float:right;$display\">
						<p style=\"float:left;\">Vælg antal viste linjer:&nbsp;</p>
						<select name=\"kopi_ordre_limit\" class=\"selectinputloen\" style=\"width:76px;\" onchange=\"this.form.submit()\">\n";
						
							for ($i=0;$i<count($limitarray);$i++) {
								if ($kopi_ordre_limit==$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
							}
							for ($i=0;$i<count($limitarray);$i++) {
								if ($kopi_ordre_limit!=$limitarray[$i]) print "<option value=\"$limitarray[$i]\">$limitnavn[$i]</option>\n"; 
							}
								
							print "
						</select>
					</div><!-- end of select -->
				</div>
			</form>
		</div><!-- end of contentsoeg -->\n";
		($antal_sager<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i head, under pagination
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>";
		
		print "<div class=\"contentkundehead\">
			<ul id=\"sort\">
					<li>
							<a href=\"sager.php?funktion=kopi_ordre&amp;nysort=sagsnr&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[0]&amp;returside=ordre&amp;sag_id=$id&amp;ordre_id=$ordre_id\" class=\"felt01 $sortstyle[0]\" style=\"width:65px\">Sagsnr</a>
							<a href=\"sager.php?funktion=kopi_ordre&amp;nysort=firmanavn&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[1]&amp;returside=ordre&amp;sag_id=$id&amp;ordre_id=$ordre_id\" class=\"felt02 $sortstyle[1]\" style=\"width:205px\">Kunde</a>
							<a href=\"sager.php?funktion=kopi_ordre&amp;nysort=udf_addr1&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[2]&amp;returside=ordre&amp;sag_id=$id&amp;ordre_id=$ordre_id\" class=\"felt03 $sortstyle[2]\" style=\"width:315px\">Opstillings adresse</a>
							<a href=\"sager.php?funktion=kopi_ordre&amp;nysort=ref&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[3]&amp;returside=ordre&amp;sag_id=$id&amp;ordre_id=$ordre_id\" class=\"felt04 $sortstyle[3]\" style=\"width:145px\">Ansvarlig</a>
							<a href=\"sager.php?funktion=kopi_ordre&amp;nysort=status&amp;vis=$vis&amp;sort=$sort&amp;nysortstyle=$sortarray[4]&amp;returside=ordre&amp;sag_id=$id&amp;ordre_id=$ordre_id\" class=\"felt05 $sortstyle[4]\" style=\"width:75px;$retstatusstyle\">Status</a>
					</li>
			</ul>
		</div><!-- end of contentkundehead -->
		
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content\">";
				for ($x=1;$x<=$antal_sager;$x++) {
						$statcolor = NULL;
						if ($status[$x]=='Tilbud') $statcolor = "color:black;";
						if ($status[$x]=='Ordrebekræftelse') $statcolor = "color:black;";
						if ($status[$x]=='Montage') $statcolor = "color:red;";
						if ($status[$x]=='Godkendt') $statcolor = "color:green;";
						if ($status[$x]=='Afmeldt') $statcolor = "color:#C1BE00;";
						if ($status[$x]=='Afsluttet') $statcolor = "color:black;";
					print "<li><a href=\"../debitor/ordre.php?funktion=opret_ordre_kopi&amp;sag_id=$sag_id[$x]&amp;konto_id=$konto_id[$x]&amp;ordre_id=$ordre_id&amp;returside=sager\">
						<span class=\"felt01\" style=\"width:65px;\">$sag_nr[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:205px;\" title='$sag_firmanavn[$x]'>$sag_firmanavn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:315px;\" title='$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]'>$udf_addr1[$x], $udf_postnr[$x] $udf_bynavn[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:145px;\" title='$sag_ansvarlig[$x]'>$sag_ansvarlig[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:75px;$statcolor\" title='$status[$x]'>$status[$x]&nbsp;</span>       
					</a></li>";
				}
			print "</ul>
			
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of pagin_content -->";

}

function sag_kontakt() {

	global $charset;
	
	$id=if_isset($_GET['sag_id']);
	$ansat_id=if_isset($_GET['ansat_id']);
	$konto_id=if_isset($_GET['konto_id']);
	if(isset($_POST['konto_id'])) $konto_id = $_POST['konto_id'];
	//echo "sag_id: $id<br>";
	//echo "konto_id: $konto_id";
	
	if (isset($_POST['submit'])) {
		$navn=db_escape_string(trim($_POST['navn']));
		$addr1=db_escape_string(trim($_POST['addr1']));
		$addr2=db_escape_string(trim($_POST['addr2']));
		$postnr=db_escape_string(trim($_POST['postnr']));
		$bynavn=db_escape_string(trim($_POST['bynavn']));
		$tlf=db_escape_string(trim($_POST['tlf']));
		$fax=db_escape_string(trim($_POST['fax']));
		$mobil=db_escape_string(trim($_POST['mobil']));
		$email=db_escape_string(trim($_POST['email']));
		$notes=db_escape_string(trim($_POST['notes']));
		$posnr=db_escape_string(trim($_POST['posnr']));
		
		/*
		// Validering af email
		if (!empty($email)) {
			if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$emailErr = "<span style=\"color:red;\">Er ikke en gyldig email adresse</span>";
			}
		}
		*/
	
		if (empty($navn)) {
			$nameErr = "<span style=\"color:red;\">Navn er p&aring;kr&aelig;vet</span>";
		} else {

			if ($ansat_id ) {
				db_modify("update ansatte set navn='$navn',addr1='$addr1',addr2='$addr2',postnr='$postnr',bynavn='$bynavn',tlf='$tlf',fax='$fax',mobil='$mobil',email='$email',notes='$notes' where id='$ansat_id'",__FILE__ . " linje " . __LINE__);
			} else {
				db_modify("insert into ansatte (navn,addr1,addr2,postnr,bynavn,tlf,fax,mobil,email,notes,posnr,sag_id)values('$navn','$addr1','$addr2','$postnr','$bynavn','$tlf','$fax','$mobil','$email','$notes','$posnr','$id')",__FILE__ . " linje " . __LINE__);
				$r=db_fetch_array(db_select("select id from ansatte where sag_id='$id' and posnr='$posnr'",__FILE__ . " linje " . __LINE__));
				$ansat_id=$r['id'];
			}
		}
	}  elseif (isset($_POST['slet']) && $ansat_id) {
		
		db_modify("delete from ansatte where id = '$ansat_id'",__FILE__ . " linje " . __LINE__);
		
		print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/sager.php?funktion=ret_sag&amp;sag_id=$id&amp;konto_id=$konto_id\">";
	}
	
	if ($ansat_id) {
		$r=db_fetch_array(db_select("select * from ansatte where id='$ansat_id'",__FILE__ . " linje " . __LINE__));
		$navn=htmlentities($r['navn'],ENT_COMPAT,$charset);
		$addr1=htmlentities($r['addr1'],ENT_COMPAT,$charset);
		$addr2=htmlentities($r['addr2'],ENT_COMPAT,$charset);
		$postnr=htmlentities($r['postnr'],ENT_COMPAT,$charset);
		$bynavn=htmlentities($r['bynavn'],ENT_COMPAT,$charset);
		$email=htmlentities($r['email'],ENT_COMPAT,$charset);
		$tlf=htmlentities($r['tlf'],ENT_COMPAT,$charset);
		$fax=htmlentities($r['fax'],ENT_COMPAT,$charset);
		$mobil=htmlentities($r['mobil'],ENT_COMPAT,$charset);
		$notes=htmlentities($r['notes'],ENT_COMPAT,$charset);
		$posnr=$r['posnr'];
 	}
 	if (!$ansat_id) {
		if($r=db_fetch_array(db_select("select * from ansatte where sag_id='$id'",__FILE__ . " linje " . __LINE__))) {
			$r=db_fetch_array(db_select("select max(posnr) as posnr from ansatte where sag_id='$id'",__FILE__ . " linje " . __LINE__));
			$posnr=$r['posnr']+1;
		} else { 
			$posnr=1;
		}
	}
	//echo "posnr: $posnr";
	
	print "<div class=\"content\">\n";
	print "<form name=\"ansatte\" action=\"sager.php?funktion=sag_kontakt&amp;ansat_id=$ansat_id&amp;sag_id=$id\" method=\"post\">\n";
	print "<div style=\"float:left; margin-right:70px; width:379px;\">\n";
	print "<h3>Kontaktperson til sag</h3>\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">Navn</div><div class=\"right\"><input class=\"text\" type=\"text\" name=\"navn\" value=\"$navn\">$nameErr</div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Adresse</div><div class=\"right\"><input class=\"text\" type=\"text\" name=\"addr1\" value=\"$addr1\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Adresse2</div><div class=\"right\"><input class=\"text\" type=\"text\" name=\"addr2\" value=\"$addr2\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Postnr. &amp; by</div><div class=\"right\"><input class=\"textSmall\" type=\"text\" name=\"postnr\" value=\"$postnr\"><input class=\"textMediumLarge textSpace\" type=\"text\" name=\"bynavn\" value=\"$bynavn\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of left container -->\n";
	print "<div style=\"float:left; width:379px;\">\n";
	print "<h3>&nbsp;</h3>\n";
	print "<div class=\"contentA\">\n";
	print "<div class=\"row\"><div class=\"left\">E-mail</div><div class=\"right\"><input class=\"text\" type=\"text\" name=\"email\" value=\"$email\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Mobil</div><div class=\"right\"><input class=\"text\" type=\"text\" name=\"mobil\" value=\"$mobil\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Lokalnr.</div><div class=\"right\"><input class=\"text\" type=\"text\" name=\"tlf\" value=\"$tlf\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "<div class=\"row\"><div class=\"left\">Lokal fax</div><div class=\"right\"><input type=\"text\" class=\"text\" name=\"fax\" value=\"$fax\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of right container -->\n";
	print "<div style=\"float:left; width:828px;\">\n";
	print "<div class=\"contentA\" style=\"width:808px;;\">\n";
	print "<div class=\"row\"><div class=\"left\">Bem&aelig;rkning</div><div class=\"right\"><textarea style=\"width:679px\" name=\"notes\" rows=\"3\" cols=\"76\">$notes</textarea><input type=\"hidden\" name=\"posnr\" value=\"$posnr\"><input type=\"hidden\" name=\"konto_id\" value=\"$konto_id\"><input type=\"hidden\" name=\"sag_id\" value=\"$id\"></div><div class=\"clear\"></div></div><!-- end of row -->\n";
	print "</div><!-- end of contentA -->\n";
	print "</div><!-- end of full container -->\n";
	print "<div class=\"clear\"></div>\n";
	print "<hr>\n";
	print "<div class=\"contentA\" style=\"text-align:center;\">\n";
	print "<input type=\"submit\" class=\"button gray medium\" value=\"Gem\" name=\"submit\"><input type=\"submit\" class=\"button rosy medium textSpaceLarge\" value=\"Slet\" onclick=\"return confirm('Er du sikker på du vil slette denne kontakt?');\" name=\"slet\">\n";
	print "</div><!-- end of contentA -->\n";
	print "	</form>\n";
	print "</div><!-- end of content -->\n";

}

function akkordliste() {

	$id=if_isset($_GET['sag_id']);
	$opg_id=if_isset($_POST['opg_id'])*1;
	$opg_id2=if_isset($_POST['opg_id2'])*1;
	if ($opg_id == $opg_id2) $opg_id2 = '0';
	if ($opg_id == '0') $opg_id2 = '0';
	$akkordfraSoeg=if_isset($_POST['akkordfraSoeg']);
	$akkordtilSoeg=if_isset($_POST['akkordtilSoeg']);
	if (!$akkordfraSoeg) $akkordfraSoeg=if_isset($_GET['akkordfraSoeg']);
	if (!$akkordtilSoeg) $akkordtilSoeg=if_isset($_GET['akkordtilSoeg']);
	if (!$opg_id) $opg_id=if_isset($_GET['opg_id']);
	//echo "sag_id: $id<br>";
	//echo "opg_id: $opg_id<br>";
	//echo "opg_id2: $opg_id2<br>";
	
	// Hvis 'fra' er tom, skal 'til' sættes til tom
	if ($akkordfraSoeg==NULL) $akkordtilSoeg=NULL;
	
	// indsættes SESSION på akkordFraSoeg og akkordTilSoeg her.
	// Hver SESSION skal have unique navn. F.eks. $_SESSION['akkordFraSoeg_$sag_id']. Her sættes sag id efter navn.
	// Vi skal havde fundet ud af om der findes en SESSION på sagen(akkordliste) som matcher med unique navn????
	// For at se navn på SESSION bruges session_name("akkordFraSoeg_$sag_id")
	// Anden løsning vil være at gemme akkordFraSoeg og akkordTilSoeg ($_GET) som link i leftmenu, så man kan komme tilbage til akkordliste
	
	// Indsættes i WHERE hvis der er opg_id 
	if ($opg_id && ($opg_id2 == '0')) {
		$where = "AND opg_id = '$opg_id'";
	} elseif ($opg_id && ($opg_id2 > 0)) { // til sammenligning af flere opgaver
		$where = "AND opg_id IN ($opg_id,$opg_id2)"; 
	} else {
		$where = NULL;
	}
	
	if ($akkordfraSoeg) {
		if ($akkordfraSoeg && $akkordtilSoeg) {
			$where2 = " AND (loendate>='".usdate($akkordfraSoeg)."' AND loendate<='".usdate($akkordtilSoeg)."')";
		} else {
			$where2 = " AND loendate='".usdate($akkordfraSoeg)."'";
		}
	}
	//echo "WHERE: $where $where2<br>";
	
	// Alle godkendte lønsedler på sagen, undtagen akkord afregning
	$x=0; #20160303
	$timer=array();
	$qtxt="SELECT * FROM loen WHERE sag_id = '$id' AND godkendt >= '1' AND afvist = '' AND art != 'akk_afr' $where $where2 ORDER BY id";
	$q = db_select($qtxt,__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		//$loen_id[$x]=$r['id'];
		//$opg_id[$x]=$r['opg_id'];
		//$opg_nr[$x]=$r['opg_nr'];
		$timer=explode(chr(9),$r['timer']);
		$timersum[$x]=array_sum($timer);
		//$timer[$x]=$r['timer'];
		$x++;
	}
	// samlede løntimer på sagen 
	($timer)?$alletimersum=array_sum($timersum):$alletimersum='0';
	
	// Query til lønudgifter
	$x=0; #20160729
	$q = db_select("SELECT * FROM loen WHERE sag_id = '$id' AND godkendt >= '1' AND art != 'akktimer' $where $where2",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		//$loen_id[$x]=$r['id'];
		$loen_sum[$x]=$r['sum'];
		$x++;
	}
	(array_sum($loen_sum))?$lonsum = array_sum($loen_sum):$lonsum='0';
	
	// Alle godkendte lønsedler på sagen
	$y=0;
	$q = db_select("SELECT * FROM loen WHERE sag_id = '$id' AND godkendt >= '1' AND afvist = '' $where $where2 ORDER BY id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$loen_id[$y]=$r['id'];
		$y++;
	}
	
	// samler loen_id til en kommasepareret liste
	$loen_ids = implode(",", $loen_id);
	//echo "loen_id: $loen_ids<br>";
	
	// Start og slut dato på lønsedler i sagen
	$z=0;
	$q = db_select("SELECT * FROM loen WHERE sag_id = '$id' AND godkendt >= '1' AND afvist = '' $where ORDER BY id",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		//$loen_id[$y]=$r['id'];
		$loendate[$z]=$r['loendate'];
		$nummerAll[$z]=$r['nummer'];
		$artAll[$z]=$r['art'];
		$z++;
	}
	$akkordfra1=dkdato(min($loendate));
	$akkordtil1=dkdato(max($loendate));
	$akkordfra2=dkdato(min($loendate));
	$akkordtil2=dkdato(max($loendate));
	
	if ($akkordfraSoeg!=NULL) $akkordfra2=$akkordfraSoeg; // Sætter minDate i Datepicker (Til)
	if ($akkordtilSoeg!=NULL) $akkordtil1=$akkordtilSoeg; // Sætter maxDate i Datepicker (Fra)
	
	$uniqueLoendate = array_values(array_unique($loendate)); // Her fjernes dato-duplikater og der sættes nye keys i array
	
	$uniqueDateString = NULL;
	for ($x=0;$x<count($uniqueLoendate);$x++) {
		$newUniqueLoendate[$x] = str_replace('-', '/', $uniqueLoendate[$x]); // Her ændres datoformat fra '2017-04-05' til '2017/04/05'
		$uniqueDateString .= $newUniqueLoendate[$x].","; // Datoer sættes i en streng delt med ','
	}

	$uniqueDates = rtrim($uniqueDateString, ","); // Her fjernes det sidste ',' fra streng
	
	
	/*
	print_r($loendate);
	echo "<br>";
	print_r($nummerAll);
	echo "<br>";
	print_r($artAll);
	echo "<br>";
	print_r($uniqueLoendate);
	echo "<br>";
	echo "<br>";
	print_r($uniqueTitle);
	echo "<br>";
	print_r($newUniqueLoendate);
	*/
	
	// Her hentes akkordliste
	if ($loen_ids) {
		$x=-1;
		$qtxt=
		$q = db_select("SELECT loen_enheder.id as loenenhed_id,loen_enheder.loen_id as loenenhed_loenid,loen_enheder.vare_id as loenenhed_vareid,loen_enheder.op as total_op,loen_enheder.ned as total_ned,varer.id as varer_id,varer.varenr as varer_nr,varer.beskrivelse as varer_beskrivelse,varer.gruppe,varer.kategori,grupper.kodenr,grupper.beskrivelse as cat_beskrivelse,grupper.art,grupper.box10 FROM loen_enheder 
										INNER JOIN varer ON loen_enheder.vare_id = varer.id
										INNER JOIN grupper ON varer.gruppe = grupper.kodenr
										WHERE loen_enheder.loen_id IN ($loen_ids) AND grupper.art = 'VG' AND grupper.box10 = 'on' ORDER BY gruppe,varer.varenr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			if (in_array($r['loenenhed_vareid'],$loenenhed_vareid)) {
				$op[$x]+=$r['total_op']*1;
				$ned[$x]+=$r['total_ned']*1;
			} else {
				$x++;
				$loenenhed_id[$x]=$r['loenenhed_id'];
				$loenenhed_loenid[$x]=$r['loenenhed_loenid'];
				$loenenhed_vareid[$x]=$r['loenenhed_vareid'];
				$op[$x]=$r['total_op']*1;
				$ned[$x]=$r['total_ned']*1;
				$varer_id[$x]=$r['varer_id'];
				$varer_nr[$x]=$r['varer_nr'];
				$varer_gruppe[$x]=$r['gruppe'];
				$varer_beskrivelse[$x]=$r['varer_beskrivelse'];
				$cat_id[$x]=$r['kodenr'];
				$cat_navn[$x]=$r['cat_beskrivelse'];
			}
		}
	}
	/* // her er navnene til varer_gruppe....
	$q=db_select("select id,kodenr,beskrivelse from grupper where art ='VG' and box10='on' order by beskrivelse",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$cat_id[$y]=$r['kodenr'];
		$cat_navn[$y]=$r['beskrivelse'];
		$y++;
	}
	*/
	/*
	$x=0;
	$q = db_select("SELECT * FROM loen_enheder WHERE loen_id IN ($loen_ids)",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$loenenhed_id[$x]=$r['id'];
		$op[$x]=$r['op'];
		$ned[$x]=$r['ned'];
		$loenid[$x]=$r['loen_id'];
		$varer_id[$x]=$r['vare_id'];
		$x++;
	}
	*/
	//print_r($loenenhed_id);
	//echo "opg_id: $opg_id<br>";
	
	// Her hentes de opgaver som er tilknyttet sagen 
	$x=0;
	$q = db_select("SELECT * FROM opgaver WHERE assign_to = 'sager' AND assign_id = '$id' ORDER BY nr",__FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$opgave_id[$x]=$r['id'];
		$opgave_nr[$x]=$r['nr'];
		$opgave_beskrivelse[$x]=$r['beskrivelse'];
		$x++;
	}
	
	// Her hentes de opgaver som der skal sammenlignes med.
	if ($opg_id) {
		$x=0;
		$q = db_select("SELECT * FROM opgaver WHERE assign_to = 'sager' AND assign_id = '$id' AND id != $opg_id ORDER BY nr",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$opgave_id2[$x]=$r['id'];
			$opgave_nr2[$x]=$r['nr'];
			$opgave_beskrivelse2[$x]=$r['beskrivelse'];
			$x++;
		}
	}
	// Her hentes 'nr' og 'beskrivelse' fra opgaver til visning på akkordliste
	if ($opg_id) {
		$r=db_fetch_array(db_select("SELECT * FROM opgaver WHERE assign_to = 'sager' AND id = '$opg_id'",__FILE__ . " linje " . __LINE__));
		$opg_nr=$r['nr'];
		$opg_beskrivelse=$r['beskrivelse'];
	}
	
	// Her hentes 'nr' og 'beskrivelse' fra opgave 2 til visning på akkordliste
	if ($opg_id2) {
		$r=db_fetch_array(db_select("SELECT * FROM opgaver WHERE assign_to = 'sager' AND id = '$opg_id2'",__FILE__ . " linje " . __LINE__));
		$opg_nr2=$r['nr'];
		$opg_beskrivelse2=$r['beskrivelse'];
	}
	
	// Her hentes sagsnummer til visning på akkordliste
	$r=db_fetch_array(db_select("SELECT sagsnr FROM sager WHERE id = '$id'",__FILE__ . " linje " . __LINE__));
	$sagsnr=$r['sagsnr'];
	
	print "<div class=\"content\">\n";
	print "<form name=\"akkordliste\" id=\"akkordliste\" action=\"sager.php?funktion=akkordliste&amp;sag_id=$id\" method=\"post\">\n";
	print "<table border=\"0\" cellspacing=\"0\" id=\"dataTable\" class=\"dataTable\">\n";
	print "<tbody>\n";
	print "<tr><td align=\"center\">\n";
	
	print "<table border=\"0\" cellspacing=\"0\" width=\"595\">\n";
	print "<tbody>\n";
	print "<tr style=\"height:35px;\">\n";
	print "<td align=\"right\"><p>Vælg den opgave som skal vises:</p></td><td><select name=\"opg_id\" onchange=\"this.form.submit()\">\n";
					for ($x=0;$x<count($opgave_id);$x++) {
						if ($opg_id==$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>\n"; 
					}
					if (!$opg_id) print "<option value=\"0\">Alle opgaver</option>\n";
					for ($x=0;$x<count($opgave_id);$x++) {
						if ($opg_id!=$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>\n"; 
					}
					if ($opg_id) print "<option value=\"0\">Alle opgaver</option>\n";
	print "</select></td>\n";
	print "</tr>\n";
	print "<tr style=\"height:35px;\">\n";
	if ($opg_id && (count($opgave_id) > 1)) {
		print "<td align=\"right\"><p>Vælg opgave som skal vises med:</p></td><td><select name=\"opg_id2\" onchange=\"this.form.submit()\">\n";
					for ($x=0;$x<count($opgave_id2);$x++) {
						if ($opg_id2==$opgave_id2[$x]) print "<option value=\"$opgave_id2[$x]\">$opgave_nr2[$x]: $opgave_beskrivelse2[$x]</option>\n"; 
					}
					if (!$opg_id2) print "<option value=\"0\">Ingen opgave</option>\n";
					for ($x=0;$x<count($opgave_id2);$x++) {
						if ($opg_id2!=$opgave_id2[$x]) print "<option value=\"$opgave_id2[$x]\">$opgave_nr2[$x]: $opgave_beskrivelse2[$x]</option>\n"; 
					}
					if ($opg_id2) print "<option value=\"0\">Ingen opgave</option>\n";
		print "</select></td>\n";
	} else {
		print "<td>&nbsp;</td><td>&nbsp;</td>\n";
	}
	print "</tr>\n";
	print "<tr style=\"height:35px;\">\n";
	print "<td align=\"right\"><p>Vælg fra/til dato for akkordlister som skal vises:</p></td><td><span style=\"#padding: 5px;\"><input id=\"akkordfraSoeg\" name=\"akkordfraSoeg\" type=\"text\" class=\"textMedium textIndent\" value=\"$akkordfraSoeg\"/></span>
				<span style=\"padding: 0px 5px 0px 5px;\"><input id=\"akkordtilSoeg\" name=\"akkordtilSoeg\" type=\"text\" class=\"textMedium textIndent\" value=\"$akkordtilSoeg\"/></span>
				<input id=\"akkordfra1\" type=\"hidden\" value=\"$akkordfra1\"/>
				<input id=\"akkordtil1\" type=\"hidden\" value=\"$akkordtil1\"/>
				<input id=\"akkordfra2\" type=\"hidden\" value=\"$akkordfra2\"/>
				<input id=\"akkordtil2\" type=\"hidden\" value=\"$akkordtil2\"/>
				<input id=\"uniqueDates\" type=\"hidden\" value=\"$uniqueDates\"/></td>\n";
	print "</tr>\n";
	print "<tr style=\"height:35px;\"><td class=\"tableAkkordlisteBorder\" colspan=\"2\">&nbsp;</td></tr>\n";
	print "</tbody>\n";
	print "</table>\n";
	/*
	print "<div style=\"#background-color:lightblue;#height:40px;padding:5px 0 5px 0;\"><p>Vælg den opgave som skal vises:&nbsp;\n"; // Vælg opgave som skal vises
	print "<select name=\"opg_id\" onchange=\"this.form.submit()\">\n";
					for ($x=0;$x<count($opgave_id);$x++) {
						if ($opg_id==$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>\n"; 
					}
					if (!$opg_id) print "<option value=\"0\">Alle opgaver</option>\n";
					for ($x=0;$x<count($opgave_id);$x++) {
						if ($opg_id!=$opgave_id[$x]) print "<option value=\"$opgave_id[$x]\">$opgave_nr[$x]: $opgave_beskrivelse[$x]</option>\n"; 
					}
					if ($opg_id) print "<option value=\"0\">Alle opgaver</option>\n";
	print "</select></p></div>\n";
	
	if ($opg_id && (count($opgave_id) > 1)) {
		print "<div style=\"#background-color:lightgreen;height:35px;padding-top:5px;\"><p>Vælg opgave som skal vises med:&nbsp;\n"; // Vælg opgave som skal vises med
		print "<select name=\"opg_id2\" onchange=\"this.form.submit()\">\n";
						for ($x=0;$x<count($opgave_id2);$x++) {
							if ($opg_id2==$opgave_id2[$x]) print "<option value=\"$opgave_id2[$x]\">$opgave_nr2[$x]: $opgave_beskrivelse2[$x]</option>\n"; 
						}
						if (!$opg_id2) print "<option value=\"0\">Ingen opgave</option>\n";
						for ($x=0;$x<count($opgave_id2);$x++) {
							if ($opg_id2!=$opgave_id2[$x]) print "<option value=\"$opgave_id2[$x]\">$opgave_nr2[$x]: $opgave_beskrivelse2[$x]</option>\n"; 
						}
						if ($opg_id2) print "<option value=\"0\">Ingen opgave</option>\n";
		print "</select></p></div>\n";
	} else {
		print "<div style=\"#background-color:lightgreen;height:35px;padding-top:5px;\"></div>\n";
	}
	print "<div style=\"#background-color:lightblue;height:35px;padding-top:5px;\"><p>Vælg fra/til dato for akkordlister som skal vises:&nbsp;
				<span style=\"#padding: 5px;\"><input id=\"akkordfraSoeg\" name=\"akkordfraSoeg\" type=\"text\" class=\"textMedium textIndent\" value=\"$akkordfraSoeg\"/></span>
				<span style=\"padding: 0px 5px 0px 5px;\"><input id=\"akkordtilSoeg\" name=\"akkordtilSoeg\" type=\"text\" class=\"textMedium textIndent\" value=\"$akkordtilSoeg\"/></span>
				<input id=\"akkordfra1\" type=\"hidden\" value=\"$akkordfra1\"/>
				<input id=\"akkordtil1\" type=\"hidden\" value=\"$akkordtil1\"/>
				<input id=\"akkordfra2\" type=\"hidden\" value=\"$akkordfra2\"/>
				<input id=\"akkordtil2\" type=\"hidden\" value=\"$akkordtil2\"/>
				<input id=\"uniqueDates\" type=\"hidden\" value=\"$uniqueDates\"/>
				<!--<input type=\"submit\" value=\"Søg\" class=\"button gray small\"/>-->\n";
	print "</p></div>\n";
	*/
	print "<div id=\"printableArea\">\n";
	($opg_beskrivelse)?$opg='Opgave&nbsp;<b>'.$opg_nr.':'.$opg_beskrivelse.'</b>':$opg=NULL;
	($opg_beskrivelse2)?$opg2='og&nbsp;<b>'.$opg_nr2.':'.$opg_beskrivelse2.'</b>':$opg2=NULL;
	($akkordfraSoeg && !$akkordtilSoeg)?$akkDato='Akkordliste dato&nbsp;<b>'.$akkordfraSoeg.'</b>':$akkDato=NULL;
	($akkordtilSoeg && $akkordtilSoeg)?$akkFraTilDato='Akkordliste dato fra&nbsp;<b>'.$akkordfraSoeg.'</b>&nbsp;til&nbsp;<b>'.$akkordtilSoeg.'</b>':$akkFraTilDato=NULL;
	print "<h3 class=\"printHeadLineAkkordliste\">Akkordlister på sagen</h3>\n";
	print "<p class=\"printOpgAkkordliste\">&nbsp;$opg&nbsp;$opg2</p>\n";
	print "<p class=\"printOpgAkkordliste\">&nbsp;$akkDato&nbsp;$akkFraTilDato</p>\n";
	print "<table border=\"0\" cellspacing=\"0\" width=\"595\" class=\"tableAkkordliste printAkkordliste\">\n";
	print "<tbody>
					<tr>
						<td colspan=\"3\"><p>Samlede antal timer:<b> ".dkdecimal($alletimersum)."</b></p></td><td><p>Lønudgifter ialt:<span style=\"color:red;\"><b> ".dkdecimal($lonsum)."</b> </span> <span style=\"float:right;\">Sagsnr: <b>$sagsnr</b></span></p></td>
					</tr>
				</tbody>\n";
	print "<tbody><tr><td class=\"tableAkkordlisteBorder\" colspan=\"4\">&nbsp;</td></tr></tbody>\n";
		if ($loen_ids && (isset($loenenhed_id))) {
			for ($x=0;$x<count($loenenhed_id);$x++) {
			
				if($varer_gruppe[$x] != $varer_gruppe[$x-1]) {
				
					if($x > 0) {
						print "</tbody>\n";
						//print "<div class=\"page-break\">B</div>\n";
						print "<tbody><tr><td class=\"tableAkkordlisteBorder\" colspan=\"4\">&nbsp;</td></tr></tbody>\n";
					}
					
					print "<tbody>
					<tr><td colspan=\"4\"><p><b>$cat_navn[$x]</b></p></td></tr>
						<tr class=\"tableAkkordlisteHead\">
							<td><p><b>Op</b></p></td>
							<td><p><b>Ned</b></p></td>
							<td><p><b>Diff.</b></p></td>
							<td><p><b>Beskrivelse</b></p></td>
						</tr>
					</tbody>\n";
					print "<tbody class=\"tableAkkordlisteZebra\">\n";
				} 
				
				$diffcolor = NULL;
				($op[$x])?$op[$x]=str_replace(".",",",$op[$x]):$op[$x]='0';
				($ned[$x])?$ned[$x]=str_replace(".",",",$ned[$x]):$ned[$x]='0';
				$diff[$x]=$op[$x]-$ned[$x];
				if ($diff[$x] < 0) $diffcolor = "color:red;";
				if ($diff[$x] == 0) $diffcolor = "color:green;";
				print "<tr>
					<td><p>$op[$x]</p></td>
					<td><p>$ned[$x]</p></td>
					<td><p style=\"$diffcolor\">$diff[$x]</p></td>
					<td><p>$varer_beskrivelse[$x]</p></td>\n";
				print "</tr>\n";
				
			}
		} else {
			print "<tbody><tr><td colspan=\"4\"><p><i>Ingen akkordliste</i></p></td></tr>";
		}
	
	print "</tbody>\n";
	print "<tbody><tr><td class=\"tableAkkordlisteBorder\" colspan=\"4\">&nbsp;</td></tr></tbody>\n";
	print "</table>\n";
	print "</div><!-- end of printableArea -->\n";
	print "</td></tr>\n";
	print "</tbody>\n";
	print "</table>\n";
	print "</form>\n";
	print "</div><!-- end of content -->\n";
}

function ny_kunde() {

	$id=if_isset($_GET['sag_id']);
	$gml_konto_id=if_isset($_GET['konto_id']);
	$ny_konto_id=if_isset($_GET['ny_konto_id']);
	if(isset($_POST['ny_konto_id'])) $ny_konto_id = $_POST['ny_konto_id'];
	//echo "sagid: $id<br>";
	//echo "gml konto id: $gml_konto_id<br>";
	//echo "ny konto id: $ny_konto_id<br>";

	if ($ny_konto_id) {
		
		//echo "ny kunde:$ny_konto_id, sagid: $id<br>";
		
		if($r=db_fetch_array(db_select("select * from adresser where id='$ny_konto_id'",__FILE__ . " linje " . __LINE__))) {
			
			db_modify("update sager set konto_id='$ny_konto_id',kontonr='".db_escape_string($r['kontonr'])."',firmanavn='".db_escape_string($r['firmanavn'])."',addr1='".db_escape_string($r['addr1'])."',addr2='".db_escape_string($r['addr2'])."',postnr='".db_escape_string($r['postnr'])."',bynavn='".db_escape_string($r['bynavn'])."',kontakt='".db_escape_string($r['kontakt'])."' where id = '$id'",__FILE__ . " linje " . __LINE__);
			print "<meta http-equiv=\"refresh\" content=\"0;URL=../sager/sager.php?funktion=ret_sag&amp;konto_id=$ny_konto_id&amp;sag_id=$id\">";
		}
	}
	
	$sortstyle=array();
	$nysortstyle=if_isset($_GET['nysortstyle']);
	$sortarray=array('kontonr','firmanavn','addr1','postnr','bynavn','kontakt','tlf');
	$sort=if_isset($_GET['sort']);
	$nysort=if_isset($_GET['nysort']);
	$unsetsort=if_isset($_GET['unsetsort']);
	
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
		$_SESSION['ny_kunde_kontonr']=$sortstyle[0];
		$_SESSION['ny_kunde_firmanavn']=$sortstyle[1];
		$_SESSION['ny_kunde_addr1']=$sortstyle[2];
		$_SESSION['ny_kunde_postnr']=$sortstyle[3];
		$_SESSION['ny_kunde_bynavn']=$sortstyle[4];
		$_SESSION['ny_kunde_kontakt']=$sortstyle[5];
		$_SESSION['ny_kunde_tlf']=$sortstyle[6];
	} else {
		$sortstyle[0]=$_SESSION['ny_kunde_kontonr'];
		$sortstyle[1]=$_SESSION['ny_kunde_firmanavn'];
		$sortstyle[2]=$_SESSION['ny_kunde_addr1'];
		$sortstyle[3]=$_SESSION['ny_kunde_postnr'];
		$sortstyle[4]=$_SESSION['ny_kunde_bynavn'];
		$sortstyle[5]=$_SESSION['ny_kunde_kontakt'];
		$sortstyle[6]=$_SESSION['ny_kunde_tlf'];
	}
	
	if ($unsetsort) {
		unset($_SESSION['ny_kunde_sort'],
					$_SESSION['ny_kunde_kontonr'],$sortstyle[0],
					$_SESSION['ny_kunde_firmanavn'],$sortstyle[1],
					$_SESSION['ny_kunde_addr1'],$sortstyle[2],
					$_SESSION['ny_kunde_postnr'],$sortstyle[3],
					$_SESSION['ny_kunde_bynavn'],$sortstyle[4],
					$_SESSION['ny_kunde_kontakt'],$sortstyle[5],
					$_SESSION['ny_kunde_tlf'],$sortstyle[6]
				);
	}
	
	//print_r($sortstyle);
	//print_r($sortarray);
	//echo "sort: $sort";
	
	if ($sort) $_SESSION['ny_kunde_sort']=$sort;
	else $sort=$_SESSION['ny_kunde_sort'];
	if (!$sort) $sort="firmanavn";
	
	$sqlsort=urldecode($sort);
	
	$x=0;
	//$sort=if_isset($_GET['sort']);
	//if (!$sort) $sort='firmanavn';
	$q=db_select("SELECT * FROM adresser WHERE art='D' ORDER BY $sqlsort",__FILE__ . " linje " . __LINE__); # AND lukket != 'on' ??? virker ikke
	while ($r = db_fetch_array($q)) {
		$x++;
		$konto_id[$x]=$r['id'];
		$kontonr[$x]=$r['kontonr'];
		$firmanavn[$x]=htmlspecialchars($r['firmanavn']);
		$addr1[$x]=htmlspecialchars($r['addr1']);
		$addr2[$x]=htmlspecialchars($r['addr2']);
		$postnr[$x]=$r['postnr'];
		$bynavn[$x]=htmlspecialchars($r['bynavn']);
		$kontakt[$x]=htmlspecialchars($r['kontakt']);
		$telefon[$x]=$r['tlf'];
	}
	$antal_adresser=$x;
	/*
	print "
		<div class=\"contentsoeg\">
			<table border=\"0\" cellspacing=\"0\" width=\"778\">
				<thead>
					<tr>
						<th width=\"100\">Kontonr</th>
						<th width=\"130\">Firmanavn</th>
						<th width=\"200\">Adresse</th>
						<th width=\"230\">Fritekst</th>
						<th>&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><input class=\"textinput\" type=\"text\" id=\"kontonr\" name=\"kontonr\" tabindex=\"1\"/></td>
						<td><input class=\"textinput\" type=\"text\" id=\"firmanavn\" name=\"firmanavn\" tabindex=\"2\"/></td>
						<td><input class=\"textinput\" type=\"text\" id=\"adresse\" name=\"adresse\" tabindex=\"3\"/></td>
						<td><input class=\"textinput\" type=\"text\" id=\"fritext\" name=\"fritext\" tabindex=\"4\"/></td>   
						<td align=\"center\"><input type=\"submit\" value=\"Find kunde\" class=\"button gray small\" tabindex=\"5\"></td>
					</tr>
				</tbody>
			</table>
		</div><!-- end of contentsoeg -->
*/
		print "<div class=\"contentsoeg\">
		<form name=\"kundesoeg\" action=\"sager.php?funktion=ny_kunde&amp;sag_id=$id&amp;konto_id=$gml_konto_id\" method=\"post\">
			<table border=\"0\" cellspacing=\"0\" width=\"828\">
				<thead>
					<tr>
						<th width=\"100\">Kundenr</th>
						<th width=\"225\">Firmanavn</th>
						<th width=\"385\">Adresse</th>
						<th colspan=\"2\">&nbsp;</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						
						<td><input class=\"textinput kontonr\" type=\"text\" value=\"\" id=\"kontonr\" name=\"kontonr\" tabindex=\"1\"/></td>
						<td><input class=\"textinput firmanavn\" type=\"text\" value=\"\" id=\"firmanavn\" name=\"firmanavn\" tabindex=\"2\"/></td>
						<td><input class=\"textinput firmaadresse\" type=\"text\" value=\"\" id=\"adresse\" name=\"adresse\" tabindex=\"3\"/></td>
						<td style=\"padding:0px;\"><input type=\"hidden\" class=\"id\" value=\"\" name=\"ny_konto_id\"></td>   
						<td align=\"center\"><input type=\"submit\" value=\"Ny kunde\" name=\"nykunde\" class=\"button gray small\" tabindex=\"4\" onclick=\"if(!this.form.ny_konto_id.value){alert('Der er ikke valgt en kunde!');return false}else{return confirm('Vil du ændre kunde på denne sag?');}\"></td>
						
					</tr>
				</tbody>
			</table>
			</form>
			<div style=\"height:25px;padding:10px 12px 0 12px;#background-color:#f2f2f2;\">
					<span style=\"float:left;width:200px;\"><a href=\"sager.php?funktion=ny_kunde&amp;konto_id=$gml_konto_id&amp;sag_id=$id&amp;unsetsort=unset\" class=\"button gray small\">Slet sortering</a></span>
					<span style=\"#text-align:center;font-size: 14px;\"><i><b>Ændre eksisterende kunde på sagen!</b></i></span>
			</div>
		</div><!-- end of contentsoeg -->"; 
		(count($konto_id)<=50)?$abortlist="abort_small_list":$abortlist=NULL; // tallet sættes til det samme som 'items_per_page' i jquery.sager.js, under pagination
		print "<div id=\"paging_container\">
			<div class=\"info_text\"></div>
			<div class=\"page_navigation $abortlist\"></div>
		<div class=\"contentkundehead\">
			<ul id=\"sort\">
					<li>
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=kontonr&amp;sort=$sort&amp;nysortstyle=$sortarray[0]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt01 $sortstyle[0]\" style=\"width:72px\">Kundenr</a>
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=firmanavn&amp;sort=$sort&amp;nysortstyle=$sortarray[1]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt02 $sortstyle[1]\" style=\"width:175px\">Navn</a>
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=addr1&amp;sort=$sort&amp;nysortstyle=$sortarray[2]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt03 $sortstyle[2]\" style=\"width:180px\">Addresse</a>
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=postnr&amp;sort=$sort&amp;nysortstyle=$sortarray[3]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt04 $sortstyle[3]\" style=\"width:60px\">Postnr</a>
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=bynavn&amp;sort=$sort&amp;nysortstyle=$sortarray[4]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt05 $sortstyle[4]\" style=\"width:105px\">By</a>       
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=kontakt&amp;sort=$sort&amp;nysortstyle=$sortarray[5]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt06 $sortstyle[5]\" style=\"width:120px\">Kontaktperson</a>
							<a href=\"sager.php?funktion=ny_kunde&amp;nysort=tlf&amp;sort=$sort&amp;nysortstyle=$sortarray[6]&amp;konto_id=$gml_konto_id&amp;sag_id=$id\" class=\"felt07 $sortstyle[6]\" style=\"width:85px\">Telefon</a>
					</li>
			</ul>
		</div><!-- end of contentkundehead -->
		<div class=\"contentkunde\"> 
			<ul id=\"things\" class=\"paging_content\">";
				for ($x=1;$x<=$antal_adresser;$x++) {
					print "<li><a href=\"sager.php?funktion=ny_kunde&amp;ny_konto_id=$konto_id[$x]&amp;sag_id=$id\" onclick=\"return confirm('Vil du ændre kunde på denne sag?');\">
						<span class=\"felt01\" style=\"width:72px\">$kontonr[$x]&nbsp;</span>
						<span class=\"felt02\" style=\"width:175px\" title=\"$firmanavn[$x]\">$firmanavn[$x]&nbsp;</span>
						<span class=\"felt03\" style=\"width:180px\" title=\"$addr1[$x]\">$addr1[$x]&nbsp;</span>
						<span class=\"felt04\" style=\"width:60px\">$postnr[$x]&nbsp;</span>
						<span class=\"felt05\" style=\"width:105px\" title=\"$bynavn[$x]\">$bynavn[$x]&nbsp;</span>	           
						<span class=\"felt06\" style=\"width:120px\" title=\"$kontakt[$x]\">$kontakt[$x]&nbsp;</span>
						<span class=\"felt07\" style=\"width:85px\">$telefon[$x]&nbsp;</span>
					</a></li>"; 
				}
			print "</ul>
		</div><!-- end of contentkunde -->
		<div class=\"page_navigation $abortlist\"></div>
		</div><!-- end of pagin_content -->";
}
?>

