// ----------javascript/cvrapiopslag.js------------------------------lap 3.5.0---2015.01.23---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2015.01.23 Hente virksomhedsdata fra CVR med CVRapi - tak Niels Rune https://github.com/nielsrune

// Use strict mode for better error handling and modern JS features
'use strict';

// F2 key handler using event listener instead of jQuery handler
document.addEventListener('keydown', (e) => {
  // F2 key activates account number or CVR number field
  if (e.key === 'F2' || e.keyCode === 113) {
    e.preventDefault();
    
    const cvrnrField = document.querySelector('[name=cvrnr]');
    if(cvrnrField) {
      cvrnrField.select();
    }
  }
});

/**
 * Fetch company data from CVR API
 * @param {string} param - The search parameter (VAT number, phone, etc)
 * @param {string} country - Country code (dk, no, etc)
 * @param {string} type - Type of search (vat, phone, etc)
 * @returns {Promise} - Promise resolving with company data
 */
const cvrapi = async (param, country, type) => {
  try {
    const response = await fetch(`https://cvrapi.dk/api?${type}=${param}&country=${country}`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      }
    });
    
    if (!response.ok) {
      throw new Error(`CVR API request failed with status ${response.status}`);
    }
    
    const data = await response.json();
    updateFormFields(data);
    return data;
  } catch (error) {
    console.error('Error fetching data from CVR API:', error);
  }
};

/**
 * Update form fields with company data
 * @param {Object} data - Company data from CVR API
 */
const updateFormFields = (data) => {
  // Use optional chaining and nullish coalescing for safer property access
  if (data?.vat) document.querySelector('[name=cvrnr]').value = data.vat;
  if (data?.name) document.querySelector('[name=firmanavn]').value = data.name;
  
  if (data?.address) {
    if (data?.addressco) {
      document.querySelector('[name=addr1]').value = `c/o ${data.addressco}`;
      document.querySelector('[name=addr2]').value = data.address;
    } else {
      document.querySelector('[name=addr1]').value = data.address;
      document.querySelector('[name=addr2]').value = '';
    }
  }
  
  if (data?.zipcode) document.querySelector('[name=postnr]').value = data.zipcode;
  if (data?.city) document.querySelector('[name=bynavn]').value = data.city;
  if (data?.phone) document.querySelector('[name=tlf]').value = data.phone;
  if (data?.email) document.querySelector('[name=email]').value = data.email;
  if (data?.fax) document.querySelector('[name=fax]').value = data.fax;
};

// Update the pattern matching function to process direct input
/**
 * Process input field and make API call when appropriate
 * @param {HTMLElement} element - Input element
 * @param {string} type - API search type (vat, phone, etc)
 */
const processInput = (element, type) => {
  const value = element.value.trim();
  
  // Check if the value contains exactly 8 digits - direct input
  if (/^\d{8}$/.test(value)) {
    // Make API call immediately with direct 8-digit input
    cvrapi(value, 'dk', type);
    return;
  }
  
  // Support the legacy special character format
  if (/^[\*\/\+]\d{8}[\*\/\+]$/.test(value)) {
    // Extract the 8 digits between special characters
    const processedValue = value.slice(1, 9);
    element.value = processedValue;
    cvrapi(processedValue, 'dk', type);
    return;
  }
};

// Add event listeners to form fields
document.addEventListener('DOMContentLoaded', () => {
  // Account number field
  const nyKontonrField = document.querySelector('[name=ny_kontonr]');
  if (nyKontonrField) {
    nyKontonrField.addEventListener('input', () => {
      processInput(nyKontonrField, 'vat');
    });
  }

  // CVR number field  
  const cvrnrField = document.querySelector('[name=cvrnr]');
  if (cvrnrField) {
    cvrnrField.addEventListener('input', () => {
      processInput(cvrnrField, 'vat');
    });
  }

  // Phone number field
  const tlfField = document.querySelector('[name=tlf]');
  if (tlfField) {
    tlfField.addEventListener('input', () => {
      processInput(tlfField, 'phone');
    });
  }
});