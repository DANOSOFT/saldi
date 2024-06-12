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
    $side = "patchNotes";
    include "header.php" 
    ?>
    <div class="patch-notes">
        <h2>Opdateringer i Booking Systemet</h2>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                1.0.2 - 2024-04-04
            </figcaption>
        </figure>
        <ul>
            <li>
                <strong>Forbedret Navigation:</strong>
                <ul>
                    <li>Toppen af siden, hvor måned, år og navigationsknapperne er placeret, følger nu med under rullefunktionen, hvilket gør det nemmere at navigere rundt på siden.</li>
                    <li>Din position gemmes nu, når du bladrer frem og tilbage, så du vender tilbage til det samme sted på siden.</li>
                </ul>
            </li>
            <li>
                <strong>Udvidet Datooversigt:</strong>
                <ul>
                    <li>Ugedagene (man, tir, ons, tor osv.) vises nu sammen med datoerne i begge oversigter, hvilket giver et bedre overblik over dagene.</li>
                    <li>Hvor mange bogstaver der bliver vist afhænger af skærmopløsningen. Ved skærmopløsninger under 1080x1920 vises kun et bogstav for at bevare tabellen som den er.</li>
                </ul>
            </li>
            <li>
                <strong>Ny Adgangskode Funktion:</strong>
                <ul>
                    <li>Tilføjet en ny indstilling, hvor du kan aktivere eller deaktivere adgangskodebeskyttelse samt indstille en ny adgangskode.</li>
                    <li>Adgangskoden skal nu bruges på alle sider under indstillinger.</li>
                </ul>
            </li>
            <li>
                <strong>Forbedret Sammenhængende Bookinger:</strong>
                <ul>
                    <li>Sammenhængende bookinger fungerer nu også for indkommende bookinger og vises korrekt i oversigten.</li>
                </ul>
            </li>
            <li>
                <strong>Forbedret Brugerinteraktion:</strong>
                <ul>
                    <li>Når du holder musen over de røde firkanter, vises nu også navn og kontonummer på den, der har booket.</li>
                </ul>
            </li>
            <li>
                <strong>Forbedret Sortering af Bookinger:</strong>
                <ul>
                    <li>På den enkelte stands oversigt vises nu nyeste bookinger først, og sorteringen fungerer korrekt igen.</li>
                </ul>
            </li>
            <li>
                <strong>Visuel Forbedring:</strong>
                <ul>
                    <li>Tallene i de blå cirkler er nu hvide, hvilket gør dem lettere at se.</li>
                </ul>
            </li>
            <li>
                <strong>Redigering af Spærring Datoer:</strong>
                <ul>
                    <li>Datoerne i spærring kan nu også redigeres uden brug af hjælpemidler.</li>
                </ul>
            </li>
            <li>
                <strong>Bladring i daglig oversigt når en dato er valgt:</strong>
                <ul>
                    <li>Fejlrettet et problem, hvor systemet anvendte den forkerte måned til at beregne antallet af dage i måneden, når man skiftede måned ved at gå frem eller tilbage i datoerne.</li>
                </ul>
            </li>
            <li>
                <strong>Design:</strong>
                <ul>
                    <li>Små design forskelle.</li>
                </ul>
            </li>
        </ul>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                1.0.2 - 2024-05-01
            </figcaption>
        </figure>
        <ul>
            <li>
                <strong>Fixed dato konvertering når der skiftes måned</strong>
                <ul>
                    <li>Når 'Sæt udflytningsdagen til dagen forinden' var aktiveret på dagen før månedsskiftet, var der en fejl i systemet, der resulterede i, at den tjekkede den forkerte dato.</li>
                </ul>
            </li>
        </ul>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js?1.0.0" integrity="sha384-ENjdO4Dr2bkBIFxQpeoTz1HIcje39Wm4jDKdf19U8gI4ddQ3GYNS7NTKfAdVQSZe" crossorigin="anonymous"></script>
</body>
</html>