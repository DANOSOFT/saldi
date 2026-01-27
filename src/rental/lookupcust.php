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
        $side = "lookupcust";
        include "header.php" 
    ?>
    <div class="width-80">
        <div class="form-group">
            <label for="customers">Vælg bruger</label>
            <input list="customers" class="form-control customers-search">
            <datalist id="customers" class="customers"></datalist>
        </div><br>

        <div class="bookings">
        </div>
    </div>
    <script src="bootstrap.min.js"></script>
    <script src="lookupcust.js?<?php echo time(); ?>" type="module"></script>
</body>
</html>