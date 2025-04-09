 // 20250324 LOE file created.
    // Function to check if konto_til is valid when konto_fra is selected
    function validateKontoFra() {
        var kontoFra = document.getElementsByName('konto_fra')[0].value;
        var kontoTil = document.getElementsByName('konto_til')[0].value;
        
        if (kontoFra !== '' && kontoTil !== '') {
            // If konto_fra is greater than  konto_til, show an alert
            if (parseFloat(kontoFra) >= parseFloat(kontoTil)) {
                alert('Konto (fra) cannot be greater than  Konto (til).');
                document.getElementsByName('konto_fra')[0].value = ''; // Reset konto_fra selection
                location.reload();
            }
        }
    }

    // Function to check if konto_fra is valid when konto_til is selected
    function validateKontoTil() {
        var kontoFra = document.getElementsByName('konto_fra')[0].value;
        var kontoTil = document.getElementsByName('konto_til')[0].value;
        
        if (kontoFra !== '' && kontoTil !== '') {
            // If konto_til is less than  konto_fra, show an alert
            if (parseFloat(kontoTil) <= parseFloat(kontoFra)) {
                alert('Konto (til) cannot be less than  Konto (fra).');
                document.getElementsByName('konto_til')[0].value = ''; // Reset konto_til selection
                location.reload();
            }
        }
    }

    // Attach event listeners when the page loads
    window.onload = function() {
        document.getElementsByName('konto_fra')[0].addEventListener('change', validateKontoFra);
        document.getElementsByName('konto_til')[0].addEventListener('change', validateKontoTil);
    }

