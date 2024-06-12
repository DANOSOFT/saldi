<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saldi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-KK94CHFLLe+nY2dmCWGMq91rCGa5gtU4mk92HdvYe+M/SXH301p5ILy+dN9+nJOZ" crossorigin="anonymous">
    <link rel="stylesheet" href="rental.css">
</head>
<body>
    <?php 
    $side = "booking";
    include "header.php" 
    ?>
        <div class="width-80">
            <form class="form">
                <div class="form-group">
                    <label for="select">Vælg booking format</label>
                    <select class="form-control format">
                        <option value="1">Tidsrum</option>
                        <option value="2">Dato</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="customers">Vælg Brugere</label>
                    <select id="inputCustomers" name="customer" class="form-control customers">
                    </select>
                </div>
                <div class="changing-input row">
                </div>
                <div class="form-group">
                    <label for="items">Vælg Udlejnings Vare</label>
                    <select id="inputItems" name="item" class="form-control items">
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Lav Booking</button>
            </form>
        </div>
</div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
    <script src="booking.js" type="module"></script>
</body>
</html>