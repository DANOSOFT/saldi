<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental</title>
    <link href="bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="rental.css">
    <!-- Include Flatpickr CSS -->
    <link rel="stylesheet" href="../css/flatpickr.min.css">
    <link rel="stylesheet" type="text/css" href="../css/flatpickrDark.css">

    <!-- Include Flatpickr JavaScript -->
    <script src="../javascript/flatpickr.min.js"></script>
    <script src="../javascript/flatpickrDa.js"></script>
</head>
<body>
<?php 
$side = "settings";
include "header.php";

$steps = array();
$steps[] = array(
    "selector" => ".flatpickr-calendar",
    "content" => findtekst('2665|Vælg de dage, hvor butikken har lukket – så kan systemet guide dig, når du opretter bookinger. Hvis en booking falder hen over lukkedage, bliver disse automatisk fratrukket prisen.', $sprog_id)
);

?>   
    <p class="text-center">Vælg lukke dag</p>
    <div class="d-flex justify-content-center">
        <div id="calendar"></div>
    </div>
    <table class="table">
        <tbody class="tBody">

        </tbody>    
    </table>
</div>
</div>
    <script src="bootstrap.min.js"></script>
    <script src="daysoff.js?<?php echo time(); ?>" type="module"></script>
    <?php
      include(__DIR__ . "/../includes/tutorial.php");
      create_tutorial("book-cday", $steps);
    ?>
</body>
</html>