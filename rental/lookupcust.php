<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saldi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js?1.0.0" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="lookupcust.js?1.1.0" type="module"></script>
</body>
</html>