<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ---- index/main.php --- lap 4.1.0 --- 2024.02.09 ---
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

$css = "../css/sidebar_style.css?v=20";

include("../includes/std_func.php");
include("../includes/connect.php");
include("../includes/online.php");
include("../includes/stdFunc/dkDecimal.php");


function check_permissions($permarr)
{
  global $rettigheder;
  $filtered = array_filter($permarr, function ($item) use ($rettigheder) {
    return (substr($rettigheder, $item, 1) == "1");
  });
  return !empty($filtered);
}

if (substr($brugernavn, 0, 11) == "debitoripad") {
  header('Location: ../debitoripad/await.php');
}

?>
<meta charset="utf-8">
<title>Sidebar</title>
<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="icon" href="../img/saldiLogo.png">
<link href='../css/sidebar_style.css' rel='stylesheet'>
<meta name="viewport" content="width=device-width, initial-scale=0.8">

<div class="modalbg" onclick="
    document.getElementsByClassName('sidebar')[0].style.width=''; 
    document.getElementsByClassName('modalbg')[0].style.display='none'; 
  "></div>
<div class="sidebar">

  <div class="logo wide">
    <img class="logo-img" src="../img/sidebar_logo.png">
    <i id="icon-open" class='bx bxs-arrow-from-right'></i>
  </div>

  <div class="logo small" onclick="
      document.getElementsByClassName('sidebar')[0].style.width=''; 
      document.getElementsByClassName('modalbg')[0].style.display='none'; 
    ">
    <img class="logo-img" src="../img/sidebar_logo.png">
    <i id="icon-open" class='bx bxs-arrow-from-right'></i>
  </div>

  <ul class="nav-links top-links" style='margin-top: 1em'>
    <!-- Finans -->
    <li class="active">
      <a href="#" id="dashboard" onclick='clear_sidebar(); this.parentElement.classList.add("active"); update_iframe("/index/dashboard.php")'>
        <i class='bx bxs-dashboard'></i>
        <span class="link_name"><?php print findtekst('3100|Oversigt', $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='clear_sidebar(); update_iframe("/index/dashboard.php")'><?php print findtekst(3075, $sprog_id); ?></a></li>
      </ul>
    </li>

    <li style="display: <?php if (check_permissions(array(2, 3, 4))) {
                          echo 'block';
                        } else {
                          echo 'none';
                        } ?>">
      <div class="icon_link" id="finans">
        <a href="#">
          <i class='bx bx-coin-stack'></i>
          <span class="link_name"><?php print findtekst(600, $sprog_id); ?></span>
        </a>
        <i class='bx bxs-chevron-down arrow'> </i>
      </div>
      <ul class="sub-menu">
        <li><span class="link_name"><?php print findtekst(600, $sprog_id); ?></span></li>
        <?php
        if (check_permissions(array(2))) {
          echo '<li><a href="#" id="kladdeliste" onclick=\'update_iframe("/finans/kladdeliste.php")\'>' . findtekst(601, $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(3))) {
          echo '<li><a href="#" id="regnskab" onclick=\'update_iframe("/finans/regnskab.php")\'>' . findtekst(602, $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(4))) {
          echo '<li><a href="#" id="rapport" onclick=\'update_iframe("/finans/rapport.php")\'>' . findtekst(603, $sprog_id) . '</a></li>';
        }
        ?>
      </ul>
    </li>

    <!-- Debitor -->
    <li style="display: <?php if (check_permissions(array(5, 6, 12))) {
                          echo 'block';
                        } else {
                          echo 'none';
                        } ?>">
      <div class="icon_link" id="debitor">
        <a href="#">
          <i class='bx bx-group'></i>
          <span class="link_name"><?php print findtekst(604, $sprog_id); ?></span>
        </a>
        <i class='bx bxs-chevron-down arrow'> </i>
      </div>
      <ul class="sub-menu">
        <li><span class="link_name"><?php print findtekst(604, $sprog_id); ?></span></li>
        <?php
        if (check_permissions(array(5))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/ordreliste.php")\'>' . findtekst(605, $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(6))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/debitor.php")\'>' . findtekst(606, $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(12))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/rapport.php")\'>' . findtekst(603, $sprog_id) . '</a></li>';
        }
        if (check_permissions(array(6))) {
          echo '<li><a href="#" onclick=\'update_iframe("/debitor/crmkalender.php")\'>CRM</a></li>';
        }
        ?>
      </ul>
    </li>
    <!-- Booking -->
    <?php
    $query = db_select("select var_value from settings where var_grp='rental'", __FILE__ . " linje " . __LINE__);
    if (db_num_rows($query) > 0) {
    ?>
      <li style="display: <?php if (check_permissions(array(6))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link">
          <a href="#">
            <i class='bx bx-calendar'></i>
            <span class="link_name"><?php print findtekst(1116, $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst(1116, $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(6))) {
            echo '<li><a href="#" onclick=\'update_iframe("/rental/index.php?vare")\'>' . findtekst(2137, $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/index.php")\'>' . findtekst(2138, $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/settings.php")\'>' . findtekst(122, $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/daysoff.php")\'>' . findtekst(2140, $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/items.php")\'>' . findtekst(2141, $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/remote.php")\'>' . findtekst(2143, $sprog_id) . '</a></li>';
            echo '<li><a href="#" onclick=\'update_iframe("/rental/lookupcust.php")\'>' . findtekst(2142, $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      <?php } ?>
      <!-- Kreditor -->
      <li style="display: <?php if (check_permissions(array(7, 8, 13))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="kreditor">
          <a href="#">
            <i class='bx bx-archive-out'></i>
            <span class="link_name"><?php print findtekst(607, $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst(607, $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(7))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/ordreliste.php")\'>' . findtekst(605, $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(8))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/kreditor.php")\'>' . findtekst(606, $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(13))) {
            echo '<li><a href="#" onclick=\'update_iframe("/kreditor/rapport.php")\'>' . findtekst(603, $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>

      <!-- Kreditor -->
      <li style="display: <?php if (check_permissions(array(9, 10, 15))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="lager">
          <a href="#">
            <i class='bx bx-package'></i>
            <span class="link_name"><?php print findtekst(608, $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name"><?php print findtekst(608, $sprog_id); ?></span></li>
          <?php
          if (check_permissions(array(9))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/varer.php")\'>' . findtekst(609, $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(10))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/modtageliste.php")\'>' . findtekst(610, $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(15))) {
            echo '<li><a href="#" onclick=\'update_iframe("/lager/rapport.php")\'>' . findtekst(603, $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>

      <!-- Kreditor -->
      <li style="display: <?php if (check_permissions(array(0, 1, 11))) {
                            echo 'block';
                          } else {
                            echo 'none';
                          } ?>">
        <div class="icon_link" id="system">
          <a href="#">
            <i class='bx bx-cog'></i>
            <span class="link_name"><?php print findtekst(3076, $sprog_id); ?></span>
          </a>
          <i class='bx bxs-chevron-down arrow'> </i>
        </div>
        <ul class="sub-menu">
          <li><span class="link_name">System</span></li>
          <?php
          if (check_permissions(array(0))) {
            echo '<li><a href="#" onclick=\'update_iframe("/systemdata/kontoplan.php")\'>' . findtekst(612, $sprog_id) . '</a></li>';
          }
          if (check_permissions(array(1))) {
            echo '<li><a href="#" onclick=\'update_iframe("/systemdata/syssetup.php")\'>' . findtekst(613, $sprog_id) . '</a></li>';

            # Kassesystem eller ej
            $qtxt = "SELECT id FROM grupper WHERE art='POS' AND box1>='1' AND fiscal_year='$regnaar'";
            $state = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__));
            if ($state) {
              print "<li><a href=\"#\" onclick='update_iframe(\"/systemdata/posmenuer.php\")'>" . findtekst(1940, $sprog_id) . "</a></li>";
            }
          }
          if (check_permissions(array(11))) {
            echo '<li><a href="#" onclick=\'update_iframe("/admin/backup.php")\'>' . findtekst(614, $sprog_id) . '</a></li>';
          }
          ?>
        </ul>
      </li>
  </ul>

  <ul class="nav-links">
    <li>
      <a href="#" onclick="alert('Kontakt os på tlf: 46 90 22 08 mail: support@saldi.dk')">
        <i class='bx bx-envelope'></i>
        <span class="link_name"><?php print findtekst(398, $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick="alert('Kontakt os på tlf: 46 90 22 08 mail: support@saldi.dk')">Kontakt</a>
        </li>
      </ul>
    </li>

    <li>
      <a href="#" onclick='redirect_uri("/index/logud.php")'>
        <i class='bx bx-log-out'></i>
        <span class="link_name"><?php print findtekst(93, $sprog_id); ?></span>
      </a>
      <ul class="sub-menu blank">
        <li><a class="" href="#" onclick='redirect_uri("/index/logud.php")'><?php print findtekst(93, $sprog_id); ?></a>
        </li>
      </ul>
    </li>

  </ul>

  <div id="desc-line">
    <a href="#" onclick="window.frames['iframe_a'].focus();
                           window.frames['iframe_a'].print();">Print</a>
    <p><a href="menu.php?useMain=off">Gl. design</a></p>
    <p title="DB nummer <?php print $db; ?>">Saldi version <?php print $version; ?></p>
  </div>
</div>

<section class="home-section">
  <div class="topbar">
    <a href="javascript:void(0)" onclick="document.getElementsByClassName('sidebar')[0].setAttribute(`style`, `width: 210px !important; height: ${window.screen.availHeight+1}px`); document.getElementsByClassName('modalbg')[0].style.display='block'; "><i class='bx bx-menu' style="color: white; font-size: 50px"></i></a>
  </div>

  <div class="home-content">
    <iframe
      onLoad="
      document.title = 'Saldi - ' + this.contentWindow.document.title; 
console.log('Locaiton', this.contentWindow.document.location.href);
      trigger_iframe_load();
      stopLoading();
      content_finished_loading(this);"
      id="iframe_a" src="-"
      name="iframe_a"
      title="Site"
      class="content-iframe"></iframe>
  </div>
  <div id="loadingBar">
    <div></div>
  </div>
</section>

<script>
  function setCookie(cname, cvalue, exdays) {
    console.log(cname, cvalue);
    const d = new Date();
    d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
    let expires = "expires=" + d.toUTCString();
    document.cookie = cname + "=" + cvalue;
  }

  function getCookie(cname) {
    let name = cname + "=";
    let decodedCookie = decodeURIComponent(document.cookie);
    let ca = decodedCookie.split(';');
    for (let i = 0; i < ca.length; i++) {
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

  let arrow = document.querySelectorAll(".icon_link");

  for (var i = 0; i < arrow.length; i++) {
    arrow[i].addEventListener("click", (e) => {
      let arrowParent = e.target.parentElement;
      console.log(e);
      arrowParent.classList.toggle("showMenu");
    });
  }

  let sidebar = document.querySelector(".sidebar");
  let sidebarBtn = document.querySelector(".logo.wide");
  sidebarBtn.addEventListener("click", () => {
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

    if (iframe.contentWindow?.docChange) {
      if (!window.confirm("Er du sikker på du gerne vil ændre side? Dine ændringer vil ikke blive gemt")) {
        return;
      }
    }

    iframe.src = (location + "").split("/").splice(0, 4).join("/") + uri
  }

  const redirect_uri = (uri) => {
    window.location = (location + "").split("/").splice(0, 4).join("/") + uri
  }

  // Check for page reloads and manage inital load of iframe
  update_iframe(window.location.hash == "" ? "/index/dashboard.php" : window.location.hash.replace("#", ""));

  let manualHashChange = true;
  addEventListener("hashchange", (event) => {
    if (manualHashChange) {
      const newHash = event.newURL.split("#")[1];
      if (newHash && newHash !== "/") {
        update_iframe(newHash);
      }
    }
  });

  function trigger_iframe_load() {
    const iframe = document.querySelector(".content-iframe");
    const path = "/" + iframe.contentWindow.document.location.href.split("/").slice(4).join("/");

    // Prevent iframe load hashchange from triggering update_iframe
    manualHashChange = false;
    window.location.hash = path;
    setCookie('last-sidebar-location', path, 1);

    // Reset manualHashChange flag after the hash has been set
    setTimeout(() => {
      manualHashChange = true;
    }, 0);
  }

  document.addEventListener('DOMContentLoaded', function() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a");
    for (let i = 0; i < refs.length; i++) {
      refs[i].addEventListener('click', function() {
        clear_sidebar();
        this.classList.toggle('active');
      });
    }
  });

  function clear_sidebar() {
    const refs = document.querySelectorAll(".sidebar ul.nav-links li ul.sub-menu li a, ul.nav-links li");
    for (let i = 0; i < refs.length; i++) {
      refs[i].classList.remove('active');
    }
  }

  function startLoading() {
    var loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'block'; // Show the loading bar
  }

  function stopLoading() {
    var loadingBar = document.getElementById('loadingBar');
    loadingBar.style.display = 'none'; // Hide the loading bar
  }

  var content_finished_loading = function(iframe) {
    // inject the start loading handler when content finished loading
    iframe.contentWindow.onbeforeunload = startLoading;
  };
</script>

<style>
  /* The loading bar container */
  #loadingBar {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 5px;
    background-color: #f3f3f3;
    z-index: 9999;
    display: none;
    /* Initially hidden */
    overflow: hidden;
  }

  /* The loading bar itself, with cool back-and-forth animation */
  #loadingBar div {
    position: fixed;
    height: 100%;
    width: 0;
    background-color: #4caf50;
    animation: loadingAnimation 2s infinite ease-in-out;
  }

  /* Keyframes for back-and-forth animation */
  @keyframes loadingAnimation {
    0% {
      width: 0;
      left: 0;
    }

    50% {
      width: 100%;
      left: 0;
    }

    100% {
      width: 0;
      left: 100%;
    }
  }
</style>

</html>