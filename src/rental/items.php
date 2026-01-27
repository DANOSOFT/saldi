<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saldi</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="rental.css">
</head>
<body>
    <?php 
    $side = "settings";
    include "header.php";

    $steps = array();
    $steps[] = array(
        "selector" => ".table",
        "content" => findtekst('2666|Her kan du se en liste over de varer, du har sat til udlejning. Klik på \'Rediger\' for at oprette stande under de enkelte varer.', $sprog_id)
    );
    ?>
        <div class="width-80">
            <table class="table table-light table-striped">
                <tBody></tBody>
            </table>
            <p>
For at sætte varer til udlejning skal du følge disse trin:
<ol>
<li>Klik på linket herunder</li>
<li><a href="../lager/varer.php?returside=../index/menu.php">Link til vare</a></li>
<li>Find den specifikke vare, du ønsker at sætte til udlejning, og klik på den.</li>
<li>Nederst til højre på skærmen vil du se en knap mærket "Udlejning". Klik på denne knap.</li>
</ol>
Når du har fulgt disse trin, vil varen blive markeret som værende til udlejning. Og du vil kunne se den/dem på denn liste</p>
        </div>
</div>
</div>
    <script src="bootstrap.min.js"></script>
    <script src="items.js?<?php echo time(); ?>" type="module"></script>
    <?php
      include(__DIR__ . "/../includes/tutorial.php");
      create_tutorial("book-items", $steps);
    ?>
</body>
</html>