<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saldi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
</head>
<body>
    <?php
        //include_once("api.php");
        if(isset($_GET["id"]) && isset($_GET["type"])){
            $id = (int)$_GET["id"];
            if($_GET["type"] == "invoice"){
                $name = sendInvoice($id, "invoice");
                /* header("location: peppol.php?name=".$name."&type=faktura"); */
            }elseif($_GET["type"] == "order"){
                $name = sendOrder($id);
                /* header("location: peppol.php?name=".$name."&type=faktura"); */
            }elseif($_GET["type"] == "creditnote"){
                $name = sendInvoice($id, "creditnote");
                /* header("location: peppol.php?name=".$name."&type=faktura"); */
            }
        }elseif(isset($_GET["name"])){
            $name = $_GET["name"];
        }
    ?>
    <div class="container">
        <ul class="nav nav-tabs">
        <li class="nav-item">
            <?php
                if($_GET["type"] == "faktura" || !isset($_GET["type"])){
                    echo '<a class="nav-link active" aria-current="page" onclick="run(`faktura`)" href="#" >Faktura</a>';
                }else{
                    echo '<a class="nav-link" onclick="run(`faktura`)" href="#">Faktura</a>';
                }
            ?>
        </li>
        <li class="nav-item">
            <?php
                if($_GET["type"] == "xml"){
                    echo '<a class="nav-link active" aria-current="page" onclick="run(`xml`)" href="#">XML</a>';
                }else{
                    echo '<a class="nav-link" onclick="run(`xml`)" href="#">XML</a>';
                }
            ?>
        </li>
        <li class="nav-item">
            <?php
                if($_GET["type"] == "links"){
                    echo '<a class="nav-link active" aria-current="page" onclick="run(`links`)" href="#">Download Links</a>';
                }else{
                    echo '<a class="nav-link" onclick="run(`links`)" href="#">Download Links</a>';
                }
            ?>
        </li>
        </ul>
        <div class="row">
            <?php 
                if($_GET["type"] == "faktura" || !isset($_GET["type"])){
                    echo file_get_contents("../temp/".$db."/".$name.".html");
                }elseif($_GET["type"] == "xml"){
                    $xml = htmlspecialchars(file_get_contents("../temp/".$db."/".$name.".xml"), ENT_QUOTES);
                    echo "<pre class='prettyprint'>";
                    echo "<code class='language-xml' style='display: block; white-space: pre-wrap;'>" . $xml . "</code>";
                    echo "</pre>";
                }elseif($_GET["type"] == "links"){
                    echo "<a href='../temp/".$db."/".$name.".xml' download>XML</a><br>";
                }
            ?>
        </div>
    </div>
    <script>
        function run(type){
            window.location.href = "peppol.php?name=<?php echo $name; ?>&type=" + type;
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
    <script src="https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js"></script>
</body>
</html>