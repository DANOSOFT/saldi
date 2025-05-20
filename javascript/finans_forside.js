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



 // 20250516 Sawaneh updated the code to use dropdowns for konto_fra and konto_til


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
  


 // 20250516 Sawaneh fix date issue not setting for the correct month
// 20250516 Sawaneh FINAL FIX
 document.addEventListener('DOMContentLoaded', function() {
  function getDaysInMonth(year, month) {
      return new Date(year, month, 0).getDate();
  }

  const manualSelections = {
      to: { active: false, date: null }
  };

  function updateDateDropdown(monthSelect, dateSelect, isFrom) {
      if (!monthSelect || !dateSelect) return;
      
      const [year, month] = monthSelect.value.split('|').map(Number);
      const daysInMonth = getDaysInMonth(year, month);
      let currentDate = parseInt(dateSelect.value) || 1;
      
      if (!isFrom) {
          const wasLastDay = manualSelections.to.date === getDaysInMonth(
              parseInt(monthSelect.dataset.prevYear || year),
              parseInt(monthSelect.dataset.prevMonth || month)
          );
          
          if (wasLastDay || currentDate > daysInMonth) {
              currentDate = daysInMonth;
              manualSelections.to.active = false;
          } else {
              currentDate = Math.min(currentDate, daysInMonth);
          }
      } else {
          currentDate = Math.min(currentDate, daysInMonth);
      }

      monthSelect.dataset.prevYear = year;
      monthSelect.dataset.prevMonth = month;

      dateSelect.innerHTML = '';
      for (let day = 1; day <= daysInMonth; day++) {
          const option = document.createElement('option');
          option.value = day;
          option.textContent = day + '.';
          if (day === currentDate) {
              option.selected = true;
          }
          dateSelect.appendChild(option);
      }
      
      if (!isFrom) {
          manualSelections.to.date = currentDate;
      }
  }

  function initDateDropdowns() {
      const monthFrom = document.querySelector('select[name="maaned_fra"]');
      const dateFrom = document.querySelector('select[name="dato_fra"]');
      const monthTo = document.querySelector('select[name="maaned_til"]');
      const dateTo = document.querySelector('select[name="dato_til"]');

      if (dateTo) {
          dateTo.addEventListener('change', function() {
              const [year, month] = monthTo.value.split('|').map(Number);
              const daysInMonth = getDaysInMonth(year, month);
              
              if (parseInt(this.value) !== daysInMonth) {
                  manualSelections.to.active = true;
                  manualSelections.to.date = parseInt(this.value);
              } else {
                  manualSelections.to.active = false;
              }
          });
      }

      function setupMonthHandler(monthSelect, dateSelect, isFrom) {
          if (!monthSelect || !dateSelect) return;
          
          const [initYear, initMonth] = monthSelect.value.split('|').map(Number);
          monthSelect.dataset.prevYear = initYear;
          monthSelect.dataset.prevMonth = initMonth;
          
          updateDateDropdown(monthSelect, dateSelect, isFrom);
          
          monthSelect.addEventListener('change', function() {
              updateDateDropdown(monthSelect, dateSelect, isFrom);
          });
      }

      setupMonthHandler(monthFrom, dateFrom, true);
      setupMonthHandler(monthTo, dateTo, false);
  }

  function initAccountSelection() {
      const kontoFra = document.querySelector('select[name="konto_fra"]');
      const kontoTil = document.querySelector('select[name="konto_til"]');
      
      if (!kontoFra || !kontoTil || typeof konti === 'undefined') return;

      kontoFra.addEventListener('change', function() {
          const selectedKonto = parseInt(this.value);
          kontoTil.innerHTML = '';
          
          konti.forEach(konto => {
              if (konto.kontonr >= selectedKonto) {
                  const option = document.createElement('option');
                  option.value = konto.kontonr;
                  option.textContent = konto.label;
                  kontoTil.appendChild(option);
              }
          });
          
          if (kontoTil.querySelector(`option[value="${kontoTil.value}"]`)) {
              kontoTil.value = kontoTil.value;
          }
      });
  }

  initDateDropdowns();
  initAccountSelection();
});