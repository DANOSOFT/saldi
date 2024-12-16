<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saldi</title>
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
   $page = "overview"; 
   include "header.php"; 
   ?>
    <div id="loading">
    <img id="loading-image" src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Loading_2.gif?20170503175831" alt="Loading..." />
    </div>
        <div class="width-80">
            <div class="text-center">
                <span class="h2 name"></span>
            </div>
            <form class="form row">
                <div class="row rental-option"></div>
            </form>
            <div class="items"></div>
        </div>
</div>
</div>


    <script src="bootstrap.min.js"></script>
    <script src="edit.js?<?php echo time(); ?>" type="module"></script>
</body>
</html>