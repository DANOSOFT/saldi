 // 20250324 LOE file created.
    // // Function to check if konto_til is valid when konto_fra is selected
    // function validateKontoFra() {
    //     var kontoFra = document.getElementsByName('konto_fra')[0].value;
    //     var kontoTil = document.getElementsByName('konto_til')[0].value;
        
    //     if (kontoFra !== '' && kontoTil !== '') {
    //         // If konto_fra is greater than  konto_til, show an alert
    //         if (parseFloat(kontoFra) >= parseFloat(kontoTil)) {
    //             alert('Konto (fra) cannot be greater than  Konto (til).');
    //             document.getElementsByName('konto_fra')[0].value = ''; // Reset konto_fra selection
    //             location.reload();
    //         }
    //     }
    // }

    // // Function to check if konto_fra is valid when konto_til is selected
    // function validateKontoTil() {
    //     var kontoFra = document.getElementsByName('konto_fra')[0].value;
    //     var kontoTil = document.getElementsByName('konto_til')[0].value;
        
    //     if (kontoFra !== '' && kontoTil !== '') {
    //         // If konto_til is less than  konto_fra, show an alert
    //         if (parseFloat(kontoTil) <= parseFloat(kontoFra)) {
    //             alert('Konto (til) cannot be less than  Konto (fra).');
    //             document.getElementsByName('konto_til')[0].value = ''; // Reset konto_til selection
    //             location.reload();
    //         }
    //     }
    // }

    // // Attach event listeners when the page loads
    // window.onload = function() {
    //     document.getElementsByName('konto_fra')[0].addEventListener('change', validateKontoFra);
    //     document.getElementsByName('konto_til')[0].addEventListener('change', validateKontoTil);
    // }



 // 20250516 Sulayman updated the code to use dropdowns for konto_fra and konto_til


document.addEventListener('DOMContentLoaded', function () {
    const kontoFraEl = document.getElementsByName('konto_fra')[0];
    const kontoTilEl = document.getElementsByName('konto_til')[0];
    const rapportartEl = document.querySelector('select[name="rapportart"]');
  
    if (!kontoFraEl || !kontoTilEl || !rapportartEl || typeof konti === 'undefined') return;
  
    let currentOptions = konti;
  
    function buildOptions(element, options, selectedValue) {
      element.innerHTML = '';
      options.forEach(k => {
        const opt = document.createElement('option');
        opt.value = k.kontonr;
        opt.textContent = k.label;
        if (selectedValue && selectedValue === k.kontonr) opt.selected = true;
        element.appendChild(opt);
      });
    }
  
    function filterOptionsByRapportart() {
      const rapportart = rapportartEl.value;
      const sideskift = konti.find(k => k.type === 'X')?.kontonr;
      if (!sideskift) return konti;
  
      const x = parseFloat(sideskift);
  
      if (rapportart === 'balance') {
        return konti.filter(k => parseFloat(k.kontonr) > x);
      } else if (['resultat', 'budget', 'lastYear'].includes(rapportart)) {
        return konti.filter(k => parseFloat(k.kontonr) < x);
      } else {
        return konti;
      }
    }
  
    function updateAllDropdowns() {
      currentOptions = filterOptionsByRapportart();
  
      // Reset both
      const defaultFrom = currentOptions[0]?.kontonr || '';
      const defaultTo = currentOptions[currentOptions.length - 1]?.kontonr || '';
  
      buildOptions(kontoFraEl, currentOptions, defaultFrom);
      buildOptions(kontoTilEl, currentOptions, defaultTo);
    }
  
    function onFraChange() {
        const fra = parseFloat(kontoFraEl.value);
        const til = parseFloat(kontoTilEl.value);
      
        if (!isNaN(fra) && !isNaN(til) && fra > til) {
          alert('Konto (fra) cannot be greater than Konto (til).');
          buildOptions(kontoFraEl, currentOptions, til);
        } else {
          const filteredTil = currentOptions.filter(k => parseFloat(k.kontonr) >= fra);
          const stillValid = filteredTil.find(k => k.kontonr === kontoTilEl.value);
          buildOptions(kontoTilEl, filteredTil, stillValid ? kontoTilEl.value : filteredTil[0]?.kontonr);
        }
      }
      
      
      function onTilChange() {
        const fra = parseFloat(kontoFraEl.value);
        const til = parseFloat(kontoTilEl.value);
      
        if (!isNaN(fra) && !isNaN(til) && til < fra) {
          alert('Konto (til) cannot be less than Konto (fra).');
          buildOptions(kontoTilEl, currentOptions, fra);
        } else {
          const filteredFra = currentOptions.filter(k => parseFloat(k.kontonr) <= til);
          const stillValid = filteredFra.find(k => k.kontonr === kontoFraEl.value);
          buildOptions(kontoFraEl, filteredFra, stillValid ? kontoFraEl.value : filteredFra[0]?.kontonr);
        }
      }
      
      
    updateAllDropdowns();
  
    rapportartEl.addEventListener('change', updateAllDropdowns);
    kontoFraEl.addEventListener('change', onFraChange);
    kontoTilEl.addEventListener('change', onTilChange);
  });
  