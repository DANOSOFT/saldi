<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/dashboard.php --- lap 4.1.0 --- 2024.02.09 ---
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
// Copyright (c) 2024-2024 saldi.dk aps
// ----------------------------------------------------------------------
// 17042024 MMK - Added suport for reloading page, and keeping current URI, DELETED old system that didnt work

@session_start();
$s_id = session_id();

$css = "../css/sidebar_style.css";

include ("../includes/std_func.php");
include ("../includes/connect.php");
include ("../includes/online.php");
include ("../includes/stdFunc/dkDecimal.php");

function check_permissions($permarr) {
	global $rettigheder;
	$filtered = array_filter($permarr, function ($item) use ($rettigheder) {
		return (substr($rettigheder, $item, 1) == "1");
	});
	return !empty($filtered);
}

?>
  <meta charset="utf-8">
  <title>Sidebar</title>
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="icon" href="../img/saldiLogo.png">
  <link href='../css/sidebar_style.css' rel='stylesheet'>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <div class="sidebar">

    <div class="logo">
      <img class="logo-img" src="../img/sidebar_logo.png">
       <i id="icon-open" class='bx bxs-arrow-from-right'></i>
<!--        <i id="icon-closed" class='bx bx-menu'></i>  -->
       <i id="icon-closed" class='bx bxs-arrow-from-left'></i> 
     </div>
    <ul class="nav-links top-links" style='margin-top: 1em'>
      <li class="active">
        <a href="#" onclick='clear_sidebar(); this.parentElement.classList.add("active"); update_iframe("/index/dashboard.php")'>
	  <i class='bx bxs-dashboard'></i>
          <span class="link_name">Overblik</span>
        </a>
        <ul class="sub-menu blank" >
          <li><a class="" href="#" onclick='clear_sidebar(); update_iframe("/index/dashboard.php")'>Overblik</a></li>
        </ul>
      </li>

      <li style="display: <?php if (check_permissions(array(2,3,4))) {echo 'block';} else {echo 'none';} ?>">
        <div class="icon_link">
          <a href="#">
            <i class='bx bx-coin-stack' ></i>
            <span class="link_name">Finans</span>
          </a>
          <i class='bx bxs-chevron-down arrow' > </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">Finans</span></li>
<?php 
	if (check_permissions(array(2))) {
		echo '<li><a href="#" onclick=\'update_iframe("/finans/kladdeliste.php")\'>Kassekladder</a></li>';
	}
	if (check_permissions(array(3))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/finans/regnskab.php")\'>Regnskab</a></li>';
	}
	if (check_permissions(array(4))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/finans/rapport.php")\'>Rapporter</a></li>';
	}
?>
        </ul>
      </li>

      <li style="display: <?php if (check_permissions(array(5,6,12))) {echo 'block';} else {echo 'none';} ?>">
        <div class="icon_link">
          <a href="#">
	    <i class='bx bx-group'></i>
            <span class="link_name">Debitor</span>
          </a>
          <i class='bx bxs-chevron-down arrow' > </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">Debitor</span></li>
<?php 
	if (check_permissions(array(5))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/debitor/ordreliste.php")\'>Ordrer</a></li>';
	}
	if (check_permissions(array(6))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/debitor/debitor.php")\'>Konti</a></li>';
	}
	if (check_permissions(array(12))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/debitor/rapport.php")\'>Rapporter</a></li>';
	}
?>
        </ul>
      </li>

      <li style="display: <?php if (check_permissions(array(7,8,13))) {echo 'block';} else {echo 'none';} ?>">
        <div class="icon_link">
          <a href="#">
            <i class='bx bx-archive-out' ></i>
            <span class="link_name">Kreditor</span>
          </a>
          <i class='bx bxs-chevron-down arrow' > </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">Kreditor</span></li>
<?php 
	if (check_permissions(array(7))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/kreditor/ordreliste.php")\'>Ordrer</a></li>';
	}
	if (check_permissions(array(8))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/kreditor/kreditor.php")\'>Konti</a></li>';
	}
	if (check_permissions(array(13))) {
		  echo '<li><a href="#" onclick=\'update_iframe("/kreditor/rapport.php")\'>Rapporter</a></li>';
	}
?>
        </ul>
      </li>

      <li style="display: <?php if (check_permissions(array(9,10,15))) {echo 'block';} else {echo 'none';} ?>">
        <div class="icon_link">
          <a href="#">
            <i class='bx bx-package' ></i>
            <span class="link_name">Lager</span>
          </a>
          <i class='bx bxs-chevron-down arrow' > </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">Lager</span></li>
<?php 
	if (check_permissions(array(9))) {
        	echo '<li><a href="#" onclick=\'update_iframe("/lager/varer.php")\'>Varer</a></li>';
	}
	if (check_permissions(array(10))) {
        	echo '<li><a href="#" onclick=\'update_iframe("/lager/modtageliste.php")\'>Varemodtagelse</a></li>';
	}
	if (check_permissions(array(15))) {
        	echo '<li><a href="#" onclick=\'update_iframe("/lager/rapport.php")\'>Rapporter</a></li>';
	}
?>
        </ul>
      </li>

      <li style="display: <?php if (check_permissions(array(0,1,11))) {echo 'block';} else {echo 'none';} ?>">
        <div class="icon_link">
          <a href="#">
          <i class='bx bx-cog'></i>
          <span class="link_name">System</span>
        </a>
        <i class='bx bxs-chevron-down arrow' > </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">System</span></li>
<?php 
	if (check_permissions(array(0))) {
        	echo '<li><a href="#" onclick=\'update_iframe("/systemdata/kontoplan.php")\'>Kontoplan</a></li>';
	}
	if (check_permissions(array(1))) {
        	echo '<li><a href="#" onclick=\'update_iframe("/systemdata/syssetup.php")\'>Indstillinger</a></li>';
		
		# Kassesystem eller ej
		$qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
		$state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
		if ($state) {
			print "<li><a href=\"#\" onclick='update_iframe(\"/systemdata/posmenuer.php\")'>POS menuer</a></li>";
		}
	}
	if (check_permissions(array(11))) {
        	echo '<li><a href="#" onclick=\'update_iframe("/admin/backup.php")\'>Sikkerhedskopi</a></li>';
	}
?>
        </ul>
      </li>
    </ul>

    <ul class="nav-links">
      <li>
        <a href="#" onclick="alert('Kontakt os på tlf: 46 90 22 08 mail: support@saldi.dk')">
          <i class='bx bx-phone' ></i>
          <span class="link_name">Kontakt</span>
        </a>
        <ul class="sub-menu blank" >
          <li><a class="" href="#" onclick="alert('Kontakt os på tlf: 46 90 22 08 mail: support@saldi.dk')">Kontakt</a></li>
        </ul>
      </li>

      <li>
        <a href="#" onclick='redirect_uri("/index/logud.php")'>
          <i class='bx bx-log-out' ></i>
          <span class="link_name">Logud</span>
        </a>
        <ul class="sub-menu blank" >
          <li><a class="" href="#" onclick='redirect_uri("/index/logud.php")'>Logud</a></li>
        </ul>
      </li>

    </ul>

    <div id="desc-line">
      <p ><a href="menu.php?useMain=off">Gl. design</a></p>
      <p title="DB nummer <?php print $db; ?>">Saldi version <?php print $version; ?></p>
    </div>
  </div>

<section class="home-section">
  <div class="home-content">
    <iframe 
      onLoad="
      document.title = 'Saldi - ' + this.contentWindow.document.title; 
console.log('Locaiton', this.contentWindow.document.location.href);
trigger_iframe_load();"
      id="iframe_a" src="-" 
      name="iframe_a" 
      title="Site" 
      class="content-iframe"
    ></iframe>
  </div>
</section>

<script>
function setCookie(cname, cvalue, exdays) {
	console.log(cname, cvalue);
  const d = new Date();
  d.setTime(d.getTime() + (exdays*24*60*60*1000));
  let expires = "expires="+ d.toUTCString();
  document.cookie = cname + "=" + cvalue;
}

function getCookie(cname) {
  let name = cname + "=";
  let decodedCookie = decodeURIComponent(document.cookie);
  let ca = decodedCookie.split(';');
  for(let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}

  const warn_paths = [
    // "systemdata/syssetup.php",
  ]

  let arrow = document.querySelectorAll(".icon_link");

  for (var i = 0; i < arrow.length; i++) {
    arrow[i].addEventListener("click", (e)=>{
      let arrowParent = e.target.parentElement;
      console.log(e);
      arrowParent.classList.toggle("showMenu");
    });
  }

  let sidebar = document.querySelector(".sidebar");
  let sidebarBtn = document.querySelector(".logo");
  sidebarBtn.addEventListener("click", ()=>{
    sidebar.classList.toggle("closed");
    document.cookie = `isSidebarOpen=${sidebar.classList.contains("closed")}`
  });

  console.log(getCookie("isSidebarOpen"));
  if (getCookie("isSidebarOpen") === "true") {
    sidebar.classList.toggle("closed");
  }

  const update_iframe = (uri) => {
    const iframe = document.querySelector(".content-iframe")
    const path = iframe.contentWindow.location.href

    doConfirm = false;
    for (let i = 0; i < warn_paths.length; i++) {
      if (path.endsWith(warn_paths[i])) {
        doConfirm = true;
      }
    }
    
    if (doConfirm || iframe.contentWindow?.docChange) {
      if (!window.confirm("Er du sikker på du gerne vil ændre side? Dine ændringer vil ikke blive gemt")) {
        return;
      }
    }

    iframe.src = (location+"").split("/").splice(0,4).join("/") + uri
  }

  const redirect_uri = (uri) => {
    window.location = (location+"").split("/").splice(0,4).join("/") + uri
  }

  // Check for page reloads and manage inital load of iframe
  if (window.performance) {
    if (performance.navigation.type == performance.navigation.TYPE_RELOAD) {
      // This page is reloaded
      update_iframe(getCookie("last-sidebar-location"));
    } else {
      // This page is not reloaded
      update_iframe("/index/dashboard.php");
    }
  } else {
    // Performance does not work on this machine
    update_iframe("/index/dashboard.php");
  }

  function trigger_iframe_load() {
    const iframe = document.querySelector(".content-iframe")
    const path = "/" + iframe.contentWindow.document.location.pathname.split("/").slice(2).join("/");
    setCookie('last-sidebar-location', path, 1)
  }

  document.addEventListener('DOMContentLoaded', function () {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a");
    for (let i = 0; i < refs.length; i++) {
      refs[i].addEventListener('click', function () {
        clear_sidebar();
        this.classList.toggle('active');
	console.log(this);
      });
    }
  });

  function clear_sidebar() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a, ul.nav-links li");
    for (let i = 0; i < refs.length; i++) {
      refs[i].classList.remove('active');
    }
  }

</script>

</html>
