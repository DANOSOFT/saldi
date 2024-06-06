<?php

global $menu;

if ($rapportart == "openpost") $title = "Poster";

if ($menu=='T') {
  $top_bund = "";
  #Top table padding
  $padding = "style='padding: 25px 20px 10px 20px;'";
  #Info table padding
  $padding2 = "style='padding: 20px 10px 5px 10px;'";
  $padding1_5 = "padding: 20px 10px 5px 10px;";
} else {
  $top_bund = $top_bund;
  $padding = "";
  $padding2 = "";
}

?>

<!DOCTYPE html>
  <html>
    <body onload="set_style_from_cookie()">
        <head>
            <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
            <title><?php if (isset($title)) {echo "◖ Saldi • $title ◗";} else {echo "◖ Saldi ◗";} ?></title>
            <link rel='ICON' href='../../../img/topmenu/favicon.ico' type='image/ico' />
            <link href='https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css' rel='stylesheet'>
            <link href='../../../css/topmenu/bootstrap.min.css' rel='stylesheet'>
            <link rel="stylesheet" type="text/css" title="darkcolor" href="../css/topmenu/darkcolor.css">
            <link rel="stylesheet" type="text/css" title="darkcolor" href="../css/topmenu/dropdown.css">
            <link rel="alternate stylesheet" type="text/css" title="darkgrey" href="../css/topmenu/darkgrey.css">
            <link rel="alternate stylesheet" type="text/css" title="darkgrey" href="../css/topmenu/dropdown.css">
            <link rel="alternate stylesheet" type="text/css" title="lightcolor" href="../css/topmenu/lightcolor.css">
            <link rel="alternate stylesheet" type="text/css" title="lightcolor" href="../css/topmenu/dropdown.css">
			      <link rel="alternate stylesheet" type="text/css" title="lightgrey" href="../css/topmenu/lightgrey.css">
            <link rel="alternate stylesheet" type="text/css" title="lightgrey" href="../css/topmenu//dropdown.css">
            <script src='../../../javascript/topmenu/jquery.min.js'></script>
            <script src='../../../javascript/topmenu/jbootstrap.min.js'></script>
            <script src='../../../javascript/topmenu/slide-menu.js'></script>
            <script src='../../../javascript/topmenu/dropdown-click-menu.js'></script>
            <script src='../../../javascript/topmenu/cookie-stylesheet.js'></script>    
            <script LANGUAGE="JavaScript" SRC="../../../javascript/overlib.js"></script>
        </head>
    <body>

    <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>

  <?php 
    $kund='titleKund';
    $lev='titleLev';
    $fina='titleFina';
    $lag='titlelag';
    $brug='titleBrug';

    $classtable='class="tableTopmenu"';
    $border='border:0px';
    $bgcolor='';
    $textcolor='';
    $textcolor2='';
    $textcolor3='';
    $bgcolor2='';
    $bgcolor3='';
    $bgcolor4='';
    $bgcolor5='#5e5e5e';
    $bgcolor01='';
    $bgnuance1 ='';
    $bgnuance = '';
    $font ='';
    $color = 'white';
    $linjebg = '';
  ?>

  <div class='header'>
  <div class='container'>
  <!-- Navigation Menu Start -->
  <div class='navigation'>
    <div class='row'>
        <!-- Navigation Menu Link Lists -->
        <div class='col-xs-12 col-sm-12 col-md-2 col-lg-2'>
        <div class='menu'>
        <span class='headKund'><i class="fa fa-users"></i> &nbsp;<?php echo findtekst(991,$sprog_id) ?></span>
            <div class='menu-list'>
              <ul>
              <li>
                  <a href='../../../debitor/ordreliste.php?valg=ordrer'><?php echo findtekst(985,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../debitor/ordreliste.php?valg=faktura'><?php echo findtekst(986,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../debitor/debitor.php?valg=debitor'><?php echo findtekst(606,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../debitor/debitor.php?valg=historik'><?php echo findtekst(131,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../debitor/rapport.php'><?php echo findtekst(124,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../sager/sager.php'><?php echo findtekst(987,$sprog_id) ?></a>
                </li>
                </ul>
                <div class='menu-end'>&nbsp;</div>
            </div>
          </div>
        </div>
        <div class='col-xs-12 col-sm-3 col-md-2 col-lg-2'>
        <div class='menu'>
            <span class='headLev'><i class="fa fa-truck"></i> &nbsp;<?php echo findtekst(988,$sprog_id) ?></span>
            <div class='menu-list'>
              <ul>
                <li>
                  <a href='../../../kreditor/ordreliste.php?valg=ordrer'><?php echo findtekst(985,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../kreditor/ordreliste.php?valg=fakture'><?php echo findtekst(989,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../kreditor/kreditor.php'><?php echo findtekst(606,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../kreditor/rapport.php'><?php echo findtekst(603,$sprog_id) ?></a>
                </li>
              </ul>
              <div class='menu-end'>&nbsp;</div>
            </div>
          </div>
        </div>
        <div class='col-xs-12 col-sm-3 col-md-2 col-lg-2'>
            <div class='menu'>
            <span class='headFina'><i class="fa fa-euro"></i> &nbsp; <?php echo findtekst(600,$sprog_id) ?></span>
            <div class='menu-list'>
              <ul>
                <li>
                  <a href='../../../finans/kladdeliste.php'><?php echo findtekst(105,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../finans/regnskab.php'><?php echo findtekst(849,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../finans/budget.php'><?php echo findtekst(1067,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../finans/rapport.php'><?php echo findtekst(603,$sprog_id) ?></a>
                </li>
              </ul>
              <div class='menu-end'>&nbsp;</div>
            </div>
          </div>
        </div>
        <div class='col-xs-12 col-sm-3 col-md-2 col-lg-2'>
          <div class='menu'>
            <span class='headLag'><i class="fa fa-cubes"></i> &nbsp;<?php echo findtekst(608,$sprog_id) ?></span>
            <div class='menu-list'>
              <ul>
                <li>
                  <a href='../../../lager/varer.php'><?php echo findtekst(110,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../lager/modtageliste.php'><?php echo findtekst(610,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../lager/rapport.php'><?php echo findtekst(603,$sprog_id) ?></a>
                </li>
              </ul>
              <div class='menu-end'>&nbsp;</div>
            </div>
          </div>
        </div>
        <div class='col-xs-12 col-sm-3 col-md-2 col-lg-2'>
          <div class='menu'>
            <span class='headSys'><i class="fa fa-gear"></i>&nbsp;<?php echo findtekst(611,$sprog_id) ?></span>
            <div class='menu-list'>
              <ul>
                <li>
                  <a href='../../../systemdata/kontoplan.php'><?php echo findtekst(113,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../systemdata/syssetup.php'><?php echo findtekst(613,$sprog_id) ?></a>
                </li>
                <li>
                  <a href='../../../admin/backup.php'><?php echo findtekst(521,$sprog_id) ?></a>
                </li>
              </ul>
              <div class='menu-end'>&nbsp;</div>
            </div>
          </div>
        </div>
        <div class='col-xs-12 col-sm-12 col-md-2 col-lg-2'>
          <div class='menu'>
            <span class='headBrug'><i class="fa fa-user"></i> &nbsp;<?php echo findtekst(990,$sprog_id) ?></span>
            <div class='menu-list'>
              <ul>
                <li>
                  <a href='#' onClick="MyWindow=window.open('http://www.saldi.dk/dok/komigang.html','MyWindow','width=600,height=600'); return false;"><?php echo findtekst(92,$sprog_id) ?></a>
                </li>
                <li>
                  <div class="dropdown">
                  <button onclick="myFunction()" class="dropbtn"><?php echo findtekst(1075,$sprog_id) ?>: &nbsp; <img class='currentThemeIMG' src='../img/topmenu/theme/placeholder.png'> &nbsp; <i class="fa fa-caret-down"></i></button>
                      <div id="myDropdown" class="dropdown-content">
                        <button class='theme-switcher-btn' type="button" onclick="switch_style('darkcolor');return false;" name="theme" title='Mørkt farverigt tema'><img alt='Mørkt farverigt tema' class='themeIMG' src='../img/topmenu/theme/darkcolor.png'></button>
                        <button class='theme-switcher-btn' type="button" onclick="switch_style('darkgrey');return false;" name="theme" title='Mørkt gråt tema'><img alt='Mørkt gråt tema' class='themeIMG' src='../img/topmenu/theme/darkgrey.png'></button>
                        <button class='theme-switcher-btn' type="button" onclick="switch_style('lightcolor');return false;" name="theme" title='Lyst farverigt tema'><img alt='Lyst farverigt tema' class='themeIMG' src='../img/topmenu/theme/lightcolor.png'></button>
                        <button class='theme-switcher-btn' type="button" onclick="switch_style('lightgrey');return false;" name="theme" title='Lyst gråt tema'><img alt='Lyst gråt tema' class='themeIMG' src='../img/topmenu/theme/lightgrey.png'></button>
                      </div>
                  </div>
                </li>
                <li>
                  <a href='../../../index/logud.php'><?php echo findtekst(93,$sprog_id) ?></a>
                </li>
              </ul>
              <div class='menu-end'>&nbsp;</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Navigation menu end -->

<!-- Navigation menu button -->
<div class='menu-btn'>
  <a onclick='documentTrack('#');' href='#'>Menu <i class='fa fa-chevron-down'></i></a>
</div> 
        
<div class='head'>
   <div class='logo'>
     <div class='logo-container' title='Klik for at komme tilbage til forsiden'>
     <a href='../../../index/menu.php' class='logolink'>
      <img style='pointer-events:none;' src='../../../img/topmenu/logo.PNG'> <div class='logo-name'>Saldi</div><div class='regnskab-name'> • <?php global $regnskab; echo $regnskab; ?></div>
    </a>
     </div>
   </div>
</div>

<div class='flex-container'>

<div class='content'>