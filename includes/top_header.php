<?php
// ------includes/top_header.php---patch 4.0.8 ----2023-07-12------------
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2023 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20141030 CA  Understøttelse for andre vinduesstørrelser. Søg 20141030
// 20231107 PK Added css- and javascript-link for flatpickr

global $regnskab;

if (!isset($viewport_width))
  $viewport_width = 1024;
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php
print "        <meta name=\"viewport\" content=\"width=" . $viewport_width . "\">\n"; # 20141030
if (isset($meta_returside))
  print "$meta_returside";

?>
        <link rel="stylesheet" type="text/css" href="../css/top_menu.css">
        <link rel="stylesheet" type="text/css" href="../css/left_menu.css">
        <link rel="stylesheet" type="text/css" href="../css/saft.css">
        <link rel="stylesheet" type="text/css" href="../css/prism.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <script type="text/javascript" src="../javascript/overlib.js"></script>
        <script type="text/javascript" src="../javascript/confirmclose.js"></script>
        <script type="text/javascript" src="../javascript/jquery-1.8.2.min.js"></script> 
        <script type="text/javascript" src="../javascript/jquery.metadata.js"></script>
        <script type="text/javascript" src="../javascript/jquery.autosize.js"></script>
        <script type="text/javascript" src="../javascript/jquery.tablednd.js"></script>
        <script type="text/javascript" src="../javascript/jquery.tablesorter.min.js"></script>
        <script type="text/javascript" src="../javascript/jquery.placeholder.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://npmcdn.com/flatpickr/dist/flatpickr.min.js"></script>
        <script src="https://npmcdn.com/flatpickr/dist/l10n/da.js"></script>
        <link rel='ICON' href='../img/topmenu/favicon.ico?=v7' type='image/ico' />
        <link href='../css/topmenu/bootstrap.min.css' rel='stylesheet'>
        <link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet'>
        <link rel="stylesheet" type="text/css" title="lightcolor" href="../css/topmenu/lightcolor-v1.css">
        <link rel="stylesheet" type="text/css" title="lightcolor" href="../css/topmenu/responsive-navigation-v1.css">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Exo:wght@600&display=swap" rel="stylesheet">
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
        <title><?php if (isset($title)) {
          echo "◖ Saldi • $title ◗";
        } else {
          echo "◖ Saldi ◗";
        } ?></title>
    </head>
    <body>