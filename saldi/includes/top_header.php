<?php
// -----------------includes/top_header.php----lap 3.4.4---2014-10-30----
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

if ( ! isset($viewport_width) ) $viewport_width=1024;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php
print "        <meta name=\"viewport\" content=\"width=".$viewport_width."\">\n"; # 20141030
if(isset($meta_returside)) print "$meta_returside";

?>
        <link rel="stylesheet" type="text/css" href="../css/top_menu.css">
        <link rel="stylesheet" type="text/css" href="../css/left_menu.css">
        <script type="text/javascript" src="../javascript/overlib.js"></script>
				<script type="text/javascript" src="../javascript/confirmclose.js"></script>
        <script type="text/javascript" src="../javascript/jquery-1.8.2.min.js"></script> 
        <script type="text/javascript" src="../javascript/jquery.metadata.js"></script>
        <script type="text/javascript" src="../javascript/jquery.autosize.js"></script>
        <script type="text/javascript" src="../javascript/jquery.tablednd.js"></script>
        <script type="text/javascript" src="../javascript/jquery.tablesorter.min.js"></script>
        <script type="text/javascript" src="../javascript/jquery.placeholder.min.js"></script>
        <!--[if lt IE 9]>
				<script src="http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js"></script>
				<![endif]-->
        <script type="text/javascript">
            $(document).ready(function() // function til sortering i table
            { 
                $("#dataTable").tablesorter({widthFixed: true})
                .tablesorterPager({container: $("#pager")}); 
            } 
        ); 
           
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
        <!--[if IE 9]>
        <style>
        .navbar li {  
        background-color: rgba(0, 0, 0, 0);/* for at få IE9 til at ignorere border på menubar on hover*/
        }
        </style>
        <![endif]-->
        <title>= Saldi =</title>
    </head>
    <body>
        <div id="wrapper"> 