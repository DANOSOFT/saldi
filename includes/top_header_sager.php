<?php
// ---------includes/top_header_sager.phpi------lap 3.4.4---2014-10-30----
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// // Copyright (c) 2004-2014 DANOSOFT ApS
// ----------------------------------------------------------------------
// 20141030 CA  Understøttelse for andre vinduesstørrelser. Søg 20141030

if ( ! $viewport_width ) $viewport_width=1024;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
	<head>
		<meta http-equiv="X-UA-Compatible" content="IE=10">
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<meta name="viewport" content="width=1024">
<?php
print "		<meta name=\"viewport\" content=\"width=".$viewport_width."\">\n"; # 20141030
if($meta_returside) print "$meta_returside";
?>
		<!--<link rel="stylesheet" type="text/css" href="../css/main.css">-->
		<link rel="stylesheet" type="text/css" href="../css/main_tilbud.css">
		<link rel="stylesheet" type="text/css" href="../css/search.css">
		<link rel="stylesheet" type="text/css" href="../css/form.css">
		<script type="text/javascript" src="../javascript/overlib.js"></script>
		<script type="text/javascript" src="../javascript/confirmclose.js"></script>
		<script type="text/javascript" src="../javascript/jquery-1.8.0.min.js"></script>
		<script type="text/javascript" src="../javascript/jquery.autocomplete.js"></script>
		<script type="text/javascript" src="../javascript/jquery.autosize.js"></script>
		<script type="text/javascript" src="../javascript/jquery.tablednd.js"></script>
		<script type="text/javascript" src="../javascript/jquery.sager.js"></script>
		<!--[if lt IE 9]>
		<script src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script>
		<![endif]-->
		<script type="text/javascript">
		
		// jQuery funktion til autosize på textarea 
			$(document).ready(function(){
				$('.autosize').autosize();
			});
		
		// jQuery funktion til ordrelinjer i ordre.php. Ved tryk på enter submitter formen og ved shift+enter laver den ny linje i textarea
			$(function() {
				$('textarea.comment').keyup(function(e) {
						if (e.which == 13 && ! e.shiftKey) {
								$("#submit").click();
						}
				});
			});
		
		// javascript til ordre.php
			var linje_id=0;
			var vare_id=0;
			var antal=0;
			function serienummer(linje_id,antal){
				window.open("serienummer.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
			}
			function batch(linje_id,antal){
				window.open("batch.php?linje_id="+ linje_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
			}
			function stykliste(vare_id){
				window.open("../lager/fuld_stykliste.php?id="+ vare_id,"","left=10,top=10,width=400,height=400,scrollbars=yes,resizable=yes,menubar=no,location=no")
			}

		</script>
		
		<script language="javascript" type="text/javascript">
			function DropDownTextToBox(objDropdown, strTextboxId) {
					document.getElementById(strTextboxId).value = objDropdown.options[objDropdown.selectedIndex].value;
					DropDownIndexClear(objDropdown.id);
					document.getElementById(strTextboxId).focus();
			}
			function DropDownIndexClear(strDropdownId) {
					if (document.getElementById(strDropdownId) != null) {
							document.getElementById(strDropdownId).selectedIndex = -1;
					}
			}
		</script>
		<title>Stillads</title>
	</head>
	<body>
		<div id="wrapper">
