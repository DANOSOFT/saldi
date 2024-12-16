<?php
    @session_start();
    $s_id=session_id();
    $header = "nix";
    $bg = "nix";
    include "../includes/connect.php";
    include "../includes/online.php";
    $query = db_select("SELECT box3 FROM grupper where  art = 'USET' and kodenr = '$bruger_id'", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
?>

<div class="container-fluid">
    <div class="row flex-nowrap">
        <?php if($res["box3"] != "S"){ ?>
        <div class="col-auto col-sm-4 col-md-3 col-xl-2 px-sm-2 px-0 bg-light header">
            <div class="d-flex flex-column flex-shrink-0 p-3 min-vh-100 sticky-top">
                <a href="index.php?vare" class="d-flex align-items-center pb-3 mb-md-0 me-md-auto text-decoration-none mx-auto">
                    <span class="fs-3 d-none d-sm-inline"><img src="../img/saldiLogo.png" width="100"></span>
                </a>
                <hr>
                <ul class="nav nav-pills flex-column mb-auto" id="menu">
                <li>
                    <?php
                        if(!isset($side)){
                            $side = "overview";
                        }
                        if($side == "overview" || $side == "booking"){
                            echo '<a href="index.php?vare" class="nav-link active">';
                        }else{
                            echo '<a href="index.php?vare" class="nav-link">';
                        }
                    ?>
                    <i class="fs-4 bi-table"></i> <span class="ms-1 d-none d-sm-inline"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar4" viewBox="0 0 16 16">
                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v1h14V3a1 1 0 0 0-1-1H2zm13 3H1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5z"/>
                    </svg> Udlejnings oversigt</span></a>
                </li>
                <li>
                    <?php
                        if($side == "overview2"){
                            echo '<a href="index.php" class="nav-link active">';
                        }else{
                            echo '<a href="index.php" class="nav-link">';
                        }
                    ?>
                    <i class="fs-4 bi-table"></i> <span class="ms-1 d-none d-sm-inline"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-calendar4" viewBox="0 0 16 16">
                        <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v1h14V3a1 1 0 0 0-1-1H2zm13 3H1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V5z"/>
                    </svg> Daglig oversigt</span></a>
                </li> 
<!--                     <li>
                        <?php
                           /*  if($side == "booking"){
                                echo '<a href="booking.php" class="nav-link active">';
                            }else{
                                echo '<a href="booking.php" class="nav-link">';
                            } */
                        ?>
                            <i class="fs-4 bi-table"></i> <span class="ms-1 d-none d-sm-inline"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-plus-square" viewBox="0 0 16 16">
  <path d="M14 1a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
  <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
</svg> Ny booking</span></a>
                    </li> -->
                    <li>
                        <?php
                            if($side == "settings"){
                                echo '<a href="#submenu" data-bs-toggle="collapse" id="click" class="nav-link active">';
                                ?>
                                <script>
                                    // wait for dom to load
                                    document.addEventListener("DOMContentLoaded", function() {
                                        // click the link
                                        document.getElementById('click').click()
                                    })
                                </script>
                                <?php
                            }else{
                                echo '<a href="#submenu" data-bs-toggle="collapse" class="nav-link">';
                            }
                        ?>
                    <i class="fs-4 bi-table"></i> <span class="ms-1 d-none d-sm-inline">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16">
                        <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                        <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                    </svg> Indstillinger</span></a>
                    <ul class="collapse nav flex-column ms-1" id="submenu" data-bs-parent="#menu">
                        <li class="w-100">
                            <a href="settings.php" class="nav-link px-0"> <span class="d-none d-sm-inline ms-5">Indstillinger</span></a>
                        </li>
                        <li>
                            <a href="daysoff.php" class="nav-link px-0"> <span class="d-none d-sm-inline ms-5">Lukkedage</span></a>
                        </li>
                        <li>
                            <a href="items.php" class="nav-link px-0"> <span class="d-none d-sm-inline ms-5">Udlejningsvarer</span></a>
                        </li>
                        <li>
                            <a href="remote.php" class="nav-link px-0"> <span class="d-none d-sm-inline ms-5">Fjern booking</span></a>
                        </li>
                    </ul>
                        </a>
                    </li>
                    <li>
                    <?php
                        if(!isset($side)){
                            $side = "lookupcust";
                        }
                        if($side == "lookupcust"){
                            echo '<a href="lookupcust.php" class="nav-link active">';
                        }else{
                            echo '<a href="lookupcust.php" class="nav-link">';
                        }
                    ?>
                    <i class="fs-4 bi-table"></i> <span class="ms-1 d-none d-sm-inline"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
                        <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5m.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1z"/>
                    </svg> Søg kunde historik</span></a>
                </li>
                    
                <li>
                        <a href="../debitor/debitor.php?returside=../index/menu.php" class="nav-link">
                            <i class="fs-4 bi-table"></i> <span class="ms-1 d-none d-sm-inline"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-backspace" viewBox="0 0 16 16">
                        <path d="M5.83 5.146a.5.5 0 0 0 0 .708L7.975 8l-2.147 2.146a.5.5 0 0 0 .707.708l2.147-2.147 2.146 2.147a.5.5 0 0 0 .707-.708L9.39 8l2.146-2.146a.5.5 0 0 0-.707-.708L8.683 7.293 6.536 5.146a.5.5 0 0 0-.707 0z"/>
                        <path d="M13.683 1a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-7.08a2 2 0 0 1-1.519-.698L.241 8.65a1 1 0 0 1 0-1.302L5.084 1.7A2 2 0 0 1 6.603 1h7.08zm-7.08 1a1 1 0 0 0-.76.35L1 8l4.844 5.65a1 1 0 0 0 .759.35h7.08a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1h-7.08z"/>
                        </svg> Tilbage til debitor</span></a>
                </li>
            </ul>
        </div>
    </div>
    <?php } ?>
    <div class="col py-3 min-vh-100 p-2 h-100 content ">