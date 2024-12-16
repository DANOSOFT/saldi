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
        include "header.php" 
    ?>
    <form class="form width-80">
        <h3>Indstillinger</h3>
        <div class="form-group mt-3">
            <label for="format">Vælg booking format:</label>
            <select class="form-control format" id="format" disabled>
                <option value="2">Dato</option>
                <option value="1">Tidsrum</option>
            </select>
            <figure class="mt-3">
                <figcaption class="blockquote-footer">
                    Dato: er for booking af perioder over flere dage
                </figcaption>
                <figcaption class="blockquote-footer">
                    Tidsrum: er for booking af tidsrum på given dato
                </figcaption>
            </figure>
        </div>
        <hr>
        <label>Indstillinger for søgning af kunder:</label>
        <figure class="mt-3">
                <figcaption class="blockquote-footer">
                    Vælg hvilke felter der skal søges i ved indtastning når du laver en ny booking
                </figcaption>
            </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input kundenr" id="kundenr">
            <label for="kundenr">Kundenr.</label>
        </div>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input tlf" id="tlf">
            <label for="tlf">Telefonnr.</label>
        </div>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input navn" id="navn">
            <label for="navn">Navn</label>
        </div><br>
        <hr>
        <label>Ændringer på Daglig oversigt:</label>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                sæt indflytnings dag til dagen forinden startsdato
            </figcaption>
        </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input indflytning" id="indflytning">
            <label for="indflytning">Sæt indflytnings dagen til dagen forinden</label>
            <figure class="mt-3">
                <figcaption class="blockquote-footer">
                    sæt indflytnings dag til dagen forinden startsdato
                </figcaption>
            </figure>
            <input type="checkbox" role="switch" class="form-check-input udflytning" id="udflytning">
            <label for="udflytning">Sæt udflytnings dagen til dagen forinden</label>
        </div><br>
        <hr>
        <label>Usikker sletning:</label>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                Fjerner popup vinduet der spøger om du er sikker på du vil slette denne stand
            </figcaption>
        </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input sletning" id="sletning">
            <label for="sletning">Fjern godkendelse ved sletning af stande</label>
        </div>
        <hr>
        <label>Hjælp til valg a dato:</label>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                Få hjælp til at finde dato med hele uger når du vælger dato
            </figcaption>
        </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input findUger" id="findUger">
            <label for="findUger">Sæt hjælper til</label>
        </div>
        <hr>
        <label>Sammensæt sammenhængende bookinger:</label>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                Sammensæt sammenhængende bookinger fra oversigten. Dette gælder for bookinger, hvor den samme person har to eller flere bookinger af den samme stand i træk.<br>
                (f.eks. 1/1-2021 til 3/1-2021 og 4/1-2021 til 5/1-2021 bliver til 1/1-2021 til 5/1-2021)
            </figcaption>
        </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input putTogether" id="Sammensæt">
            <label for="Sammensæt">Sammensæt sammenhængende bookinger</label>
        </div>
        <hr>
        <label>Fakturadato:</label>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                Vælg, om fakturadatoen skal sættes som startdatoen for bookingen i stedet for at skulle indtastes manuelt.
            </figcaption>
        </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input putTogether" id="fakturadato">
            <label for="fakturadato">Sæt faktura dato til bookings startdato</label>
        </div>
        <hr>
        <label>Sæt adgangskode til indstillinger:</label>
        <figure class="mt-3">
            <figcaption class="blockquote-footer">
                Sæt en adgangskode til indstillingerne. Denne adgangskode skal bruges for at ændre indstillingerne
            </figcaption>
        </figure>
        <div class="form-check form-switch">
            <input type="checkbox" role="switch" class="form-check-input putTogether" id="use_password">
            <label for="use_password">Brug adgangskode</label>
        </div>
        <div class="form-group mt-3">
            <label for="password">Adgangskode:</label>
            <input type="password" class="form-control password" id="password">
        </div>
        <button class="btn btn-primary save mt-3">Gem</button>
    </form>
</div>
</div>
    <script src="bootstrap.min.js"></script>
    <script src="settings.js?<?php echo time(); ?>" type="module"></script>
</body>
</html>