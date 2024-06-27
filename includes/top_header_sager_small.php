<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=10">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta name="viewport" content="width=1024">
		<!--<link rel="stylesheet" type="text/css" href="../css/main.css">-->
		<link rel="stylesheet" type="text/css" href="../css/main_tilbud.css">
		<link rel="stylesheet" type="text/css" href="../css/search.css">
		<link rel="stylesheet" type="text/css" href="../css/form.css">
		<link rel="stylesheet" type="text/css" href="../css/smoothness/jquery-ui-1.9.2.custom.css">
		<link rel="stylesheet" type="text/css" href="../css/print.css" media="print">
		<!--<link rel="stylesheet" type="text/css" href="../css/print-preview.css" media="screen">-->
		<script type="text/javascript" src="../javascript/overlib.js"></script>
		<script type="text/javascript" src="../javascript/confirmclose.js"></script>
		<script type="text/javascript" src="../javascript/jquery-1.8.0.min.js"></script>
		<script type="text/javascript" src="../javascript/jquery-ui-1.9.2.custom.min.js"></script>
		<script type="text/javascript" src="../javascript/ui.datepicker-da.js"></script>
		<script type="text/javascript" src='../javascript/jquery.autosize.js'></script>
		<script type="text/javascript" src="../javascript/jquery.tablednd.js"></script>
		<script type="text/javascript" src="../javascript/jquery.autocomplete.js"></script>
		<script type="text/javascript" src="../javascript/jquery.printElement.js"></script>
		<!--<script type="text/javascript" src="../javascript/jquery.print-preview.js"></script>-->
		<script type="text/javascript" src="../javascript/jquery.sager.js"></script>
		<!--[if lt IE 9]>
		<script src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script>
		<![endif]-->
		
		
		<!--<script type="text/javascript" src="http://www.google.com/cloudprint/client/cpgadget.js"></script>-->
		<script type="text/javascript">/* Google cloud print function */
				/*window.onload = function() {
					var gadget = new cloudprint.Gadget();
					gadget.setPrintButton(
						cloudprint.Gadget.createDefaultPrintButton("print_button_container")); //div id to contain the button
					gadget.setPrintDocument("url", "Test Page", "http://www.google.com/landing/cloudprint/testpage.pdf");
				}*/
		</script>
		
		<script type="text/javascript">
		
		/* jQuery funktion til autosize på textarea */
		$(document).ready(function(){
			$('.autosize').autosize();
		});
			
		// function til datepicker 
		$(document).ready(function() {
			$( "#datepicker" ).datepicker({
					showWeek: true,
					showOtherMonths: true,
					selectOtherMonths: true           
			});
		});
		/*
		$(function() {
			$('a.print-preview').printPreview();
		});
		*/
			/*
			$(document).ready(function() {
				$('.kontrol_status').change(function() {
					$('.kontrol_status option:selected').html();
				});
			});
			*/
			
		/* jQuery funktion til statusfarve på kontrolskema */
			$(document).ready(function(){
				$(".kontrol_status").change(function(){    
					var style=$(this).children(":selected").attr("style");
					$(this).attr("style", style);
					$(this).closest("td").attr("style", style);
				});
			});
			
			/*
			$(document).ready(function(){
				$('.kontrol_status').change(function(){
					$('.kontrol_color').removeClass('white red yellow green').addClass(
						$(this).find('option:selected').text().toLowerCase()
					);
				})
				.change();
			});
			*/
			/* 
			$(".sjak").focus(function(){
				this.selectionStart = this.selectionEnd = this.value.length;
			});*/
		</script>
		<script type="text/javascript">
		
		/* javascript funktion til print */
			function printDiv(divName) { // Original code. MÅ IKKE SLETTES!!!!!
				var printContents = document.getElementById(divName).innerHTML;
				var originalContents = document.body.innerHTML;

				document.body.innerHTML = printContents;

				window.print();

				document.body.innerHTML = originalContents;
				return false;
			}
			/*
			function printDiv(divName) { // Google cloud print test
				//document.styleSheets[0];
				var printContents = document.getElementById(divName).innerHTML;
			*/
			/*
				// Forsøg med at få css på innerHTML
				var headHTML = document.getElementsByTagName('head')[0].innerHTML;
				headHTML    += '<link type="text/css" rel="stylesheet" href="../css/main_tilbud.css">';
				document.getElementsByTagName('head')[0].innerHTML = headHTML;
			*/
				//var originalContents = document.body.innerHTML;
			/*
				//document.body.innerHTML = printContents;
				var gadget = new cloudprint.Gadget();
				gadget.setPrintDocument("text/html", "kontrolskema", printContents);
				gadget.openPrintDialog();
				//gadget.setPrintDocument("url", "Test Page", "http://www.google.com/landing/cloudprint/testpage.pdf");
				//window.print();
			*/
			/*
			var config1 = new cloudprint.Configuration().setShowPrintLocally(true); 
			var gadget1 = new cloudprint.Gadget(config1); 
			gadget1.setPrintButton( cloudprint.Gadget.createDefaultPrintButton("cloudprint_gadget_document")); 
			gadget1.setPrintDocument( "text/html", "Gadget test", new XMLSerializer().serializeToString(document)); 
			gadget1.setPrintTags(['foo', 'bar', 'hello', 'world']); 
			
			var config2 = new cloudprint.Configuration(). setMode(cloudprint.Configuration.Mode.PRINT_FILE); 
			var gadget2 = new cloudprint.Gadget(config2); 
			gadget2.setPrintButton(cloudprint.Gadget.createDefaultPrintButton("cloudprint_gadget_file")); 
			gadget2.setPrintTags(['foo', 'bar', 'hello', 'world']);
			
			}*/
		</script>
		
		<script type="text/javascript">/*
			$(document).ready(function() { // Test med jquery.printElement.js
				$("#simplePrint").click(function(){
					$('#printableArea').printElement({ 
						printMode: 'popup', 
						overrideElementCSS: ['http://ssl3.saldi.dk/stillads/css/main_tilbud.css',
							{ href:'http://ssl3.saldi.dk/stillads/css/print.css',media:'print'}]
					});
				});
			});*/
    </script>
    
    <script type="text/javascript">

			$(document).ready(function() {
				$("#opgavenavn option").each(function() {
					if($(this).text().length>50) {
						$(this).text($(this).text().substring(0,50) + '...');
					}
				});
			});
			/*
			$(function() {
				$('#opgavenavn').change(function(){
					var selected = $(this).find('option:selected');
					$('#opgavenavn option:selected').html(selected.data('foo'));
				}).change();
			});
			*/
    </script>
		<!--
		<style type="text/css">
		.kontrol_white{
			background-color:white;
		}
		.kontrol_red{
			background-color:red;
		}
		.kontrol_green{
			background-color:green;
		}
		.kontrol_yellow{
			background-color:yellow;
		}
		</style>
		-->
		<title><?php echo $pageTitle ?></title>
	</head>
	<body>
		<div id="wrapper2">
