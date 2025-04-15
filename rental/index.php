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
if(isset($_GET["vare"]) || isset($_GET["singleItem"]) || isset($_GET["showBooking"])){
  $side = "overview";
}else{
  $side = "overview2";
}
include "header.php"; 

$steps = array();
$steps[] = array(
    "selector" => "#search",
    "content" => "Søg efter stand navne, seperer med komma for at søge efter flere."
);
$steps[] = array(
    "selector" => ".th-link",
    "content" => "Se dage, klik på dem for at se ledige stande på den valgte dag"
);
$steps[] = array(
  "selector" => ".bg-success",
  "content" => "De grønne prikker viser ledige dage, og de røde prikker viser bookede dage. Klik på en grøn prik for at oprette en booking for en given dag/stand."
);
$steps[] = array(
  "selector" => ".searchItem",
  "content" => "Klik på et standnavn for at se bookinger, oprette nye eller spærre standen."
);
$steps[] = array(
  "selector" => ".spærring",
  "content" => "Her kan du oprette spærringer. En spærring er en slags 'soft booking', som markerer standen med en gul prik og fungerer som en advarsel. Det kan f.eks. bruges, hvis du vil vente med at booke standen, fordi den nuværende lejer måske ønsker at forlænge. Standen kan stadig bookes, selvom den er spærret."
);
$steps[] = array(
  "selector" => ".circle",
  "content" => "Vælg en dag for at se ind- og udflytninger på den pågældende dag. Den grønne markerer dags dato."
);

?>
<div class="modal fade" id="exampleModal2" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Ændre spærrings periode</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
      <div class="changing-input row mt-4">
        <h4>Vælg tidsrum for spærring</h4>
          <div class="form-group col-6">
              <label for="from-two">Start dato:</label>
              <input type="text" class="from-two">
          </div>
          <div class="form-group col-6">
              <label for="to-two">Slut dato:</label>
              <input type="text" class="to-two">
          </div>
      </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary close-modal2" data-bs-dismiss="modal">Luk</button>
        <button type="button" class="btn btn-primary change-reservation">Ændre spærring</button>
      </div>
    </div>
  </div>
</div>
<button type="button" class="show-modal2" data-bs-toggle="modal" data-bs-target="#exampleModal2" hidden></button>
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Find ledige stande</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
      <div class="mb-3">
        <label for="select" class="form-label">Vælg stand type</label>
        <select  id="select" class="form-select available-select">
        </select>
      </div>
      <div class="changing-input row mt-4">
        <h4>Vælg tidsrum for ledighed</h4>
          <div class="form-group col-6">
              <label for="from">Start dato:</label>
              <input type="text" class="from">
          </div>
          <div class="form-group col-6">
              <label for="to">Slut dato:</label>
              <input type="text" class="to">
          </div>
      </div>
      </div>
      <div class="modal-footer">
        <a href="index.php?vare"><button type="button" class="btn btn-secondary close-modal" data-bs-dismiss="modal">Luk</button></a>
        <button type="button" class="btn btn-primary find-available">Find stande</button>
      </div>
    </div>
  </div>
</div>
<button type="button" class="show-modal" data-bs-toggle="modal" data-bs-target="#exampleModal" hidden></button>
        <div class="back-button"></div>
      <div class="calendar">
        <div class="sticky-top w-100 " style="background-color: #fff; height: 4rem;">
          <div class="d-flex justify-content-between flex-content width-50 mx-auto pt-1">
            <button class="btn btn-primary backward w-1/2" style="height: 50%;">
              <svg xmlns="http://www.w3.org/2000/svg" width="45" height="24" fill="currentColor" class="bi bi-arrow-left arrow" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M15 8a.5.5 0 0 0-.5-.5H2.707l3.147-3.146a.5.5 0 1 0-.708-.708l-4 4a.5.5 0 0 0 0 .708l4 4a.5.5 0 0 0 .708-.708L2.707 8.5H14.5A.5.5 0 0 0 15 8z"/>
              </svg>
            </button>
            <span class="h2 month"></span>
            <button class="btn btn-primary forward" style="height: 50%;">
            <svg xmlns="http://www.w3.org/2000/svg" width="45" height="24" fill="currentColor" class="bi bi-arrow-right" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/>
            </svg>
            </button>
          </div>
        </div>
        <div class="table-point"></div>
      </div>
</div>
</div>
    <script src="bootstrap.min.js"></script>
    <script src="index.js?<?php echo time(); ?>" type="module"></script>
    <?php
      include(__DIR__ . "/../includes/tutorial.php");
      create_tutorial("book-view", $steps);
    ?>
</body>
</html>