<?php
ob_start();
include_once("api.php");
$name = isset($_GET["name"]) ? $_GET["name"] : "";
$type = isset($_GET["type"]) ? $_GET["type"] : "";

if(isset($_GET["id"]) && isset($_GET["type"])){
    $id = (int)$_GET["id"];
    if($_GET["type"] == "invoice"){
        $name = sendInvoice($id, "invoice");
        header("Location: peppol.php?name=".$name."&type=faktura");
        exit;
    }elseif($_GET["type"] == "order"){
        $name = sendOrder($id);
        header("Location: peppol.php?name=".$name."&type=faktura");
        exit;
    }elseif($_GET["type"] == "creditnote"){
        $name = sendInvoice($id, "creditnote");
        header("Location: peppol.php?name=".$name."&type=faktura");
        exit;
    }
}
?>
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
        <div class="container">
        <ul class="nav nav-tabs">
        <li class="nav-item">
            <?php
                if($type == "faktura" || $type == ""){
                    echo '<a class="nav-link active" aria-current="page" onclick="run(`faktura`)" href="#" >Faktura</a>';
                }else{
                    echo '<a class="nav-link" onclick="run(`faktura`)" href="#">Faktura</a>';
                }
            ?>
        </li>
        <li class="nav-item">
            <?php
                if($type == "xml"){
                    echo '<a class="nav-link active" aria-current="page" onclick="run(`xml`)" href="#">XML</a>';
                }else{
                    echo '<a class="nav-link" onclick="run(`xml`)" href="#">XML</a>';
                }
            ?>
        </li>
        <li class="nav-item">
            <?php
                if($type == "links"){
                    echo '<a class="nav-link active" aria-current="page" onclick="run(`links`)" href="#">Download Links</a>';
                }else{
                    echo '<a class="nav-link" onclick="run(`links`)" href="#">Download Links</a>';
                }
            ?>
        </li>
        </ul>
        <div class="row">
            <?php
                if(!empty($db) && !empty($name)){
                    if($type == "faktura" || $type == ""){
                        $file = "../temp/".$db."/".$name.".html";
                        if(file_exists($file)){
                            echo file_get_contents($file);
                        } else {
                            echo "<p>File not found: " . htmlspecialchars($file) . "</p>";
                        }
                    }elseif($type == "xml"){
                        $file = "../temp/".$db."/".$name.".xml";
                        if(file_exists($file)){
                            $xml = htmlspecialchars(file_get_contents($file), ENT_QUOTES);
                            echo "<pre class='prettyprint'>";
                            echo "<code class='language-xml' style='display: block; white-space: pre-wrap;'>" . $xml . "</code>";
                            echo "</pre>";
                        } else {
                            echo "<p>File not found: " . htmlspecialchars($file) . "</p>";
                        }
                    }elseif($type == "links"){
                        $file = "../temp/".$db."/".$name.".xml";
                        if(file_exists($file)){
                            echo "<a href='" . htmlspecialchars($file) . "' download>XML</a><br>";
                        } else {
                            echo "<p>File not found: " . htmlspecialchars($file) . "</p>";
                        }
                    }
                } else {
                    echo "<p>Please navigate from an invoice or use the proper parameters.</p>";
                }
            ?>
        </div>
        </div>
        <script>
            function run(type){
                var encodedName = <?php echo json_encode(urlencode($name)); ?>;
                window.location.href = "peppol.php?name=" + encodedName + "&type=" + type;
            }
        </script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
        <script src="https://cdn.rawgit.com/google/code-prettify/master/loader/run_prettify.js"></script>
    </body>
    </html>
    <?php
        ob_end_flush();
    ?>
