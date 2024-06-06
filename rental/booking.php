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
    $side = "booking";
    include "header.php" 
    ?>
    <div id="loading">
    <img id="loading-image" src="https://upload.wikimedia.org/wikipedia/commons/c/c7/Loading_2.gif?20170503175831" alt="Loading..." />
    </div>
        <div class="width-80">
            <form class="form">
                <!-- <div class="form-group">
                    <label for="select">Vælg booking format</label>
                    <select class="form-control format">
                        <option value="2">Dato</option>
                    </select>
                </div> -->
                <div class="changing-input row mt-4">
                    <div class="form-group col-6">
                        <label for="from">Start dato:</label>
                        <input type="text" class="from">
                    </div>
                    <div class="form-group col-6">
                        <label for="to">Slut dato:</label>
                        <input type="text" class="to">
                    </div>
                    <div class="form-group">
                        <p class="info">0 uger og 0 dage</p>
                    </div>
                </div><br>
                <div class="form-group">
                    <label for="customers">Vælg bruger</label>
                    <input list="customers" class="form-control customers-search">
                    <datalist id="customers" class="customers"></datalist>
                </div><br>
                <div class="product">
                </div><br>
                <div class="form-group mt-4">
                    <label for="items">Vælg stand til udlejning</label>
                    <select id="inputItems" name="item" class="form-control items" required>
                    </select>
                    <!-- <p class="row mt-4 mx-3"><span class="bg-success col-1"></span><span class="ml-1 col-3">Har hele uger/uge i mellem</span><span class="bg-warning col-1 ml-1"></span><span class="ml-1 col-3">Har ikke hele uger/uge i mellem</span></p> -->
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input reservation" id="resCheck">
                    <label class="form-check-label" for="resCheck">Spær stand efterfølgende</label>
                </div>
                <button type="submit" class="btn btn-primary mt-2">Opret booking</button>
            </form>
        </div>
</div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js?1.0.0" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="booking.js?1.1.4" type="module"></script>
</body>
</html>