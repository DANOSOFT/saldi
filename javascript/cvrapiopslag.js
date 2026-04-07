// ----------javascript/cvrapiopslag.js----lap 5.0.0---2026.03.28---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial, 
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 2015.01.23 Hente virksomhedsdata fra CVR med CVRapi - tak Niels Rune https://github.com/nielsrune
// Use strict mode for better error handling and modern JS features
// 20260328 LOE Update to handle user confirmation on data overwrite.
'use strict';

document.addEventListener('keydown', (e) => {
  if (e.key === 'F2' || e.keyCode === 113) {
    e.preventDefault();
    const cvrnrField = document.querySelector('[name=cvrnr]');
    if (cvrnrField) cvrnrField.select();
  }
});

/**
 * Returns an object of the current form field values for fields
 * that the CVR API would overwrite, used to detect pre-existing data.
 */
const getExistingFormData = () => ({
  firmanavn: document.querySelector('[name=firmanavn]')?.value.trim(),
  addr1:     document.querySelector('[name=addr1]')?.value.trim(),
  addr2:     document.querySelector('[name=addr2]')?.value.trim(),
  postnr:    document.querySelector('[name=postnr]')?.value.trim(),
  bynavn:    document.querySelector('[name=bynavn]')?.value.trim(),
  tlf:       document.querySelector('[name=tlf]')?.value.trim(),
  fax:       document.querySelector('[name=fax]')?.value.trim(),
});

/**
 * Checks whether any CVR-writable field already contains a value.
 * Returns an array of { label, value } for fields that would be overwritten.
 */
const detectConflicts = (existing, incoming) => {
  const labelMap = {
    firmanavn: 'Firmanavn',
    addr1:     'Adresse',
    addr2:     'Adresse 2',
    postnr:    'Postnr.',
    bynavn:    'By',
    tlf:       'Telefon',
    fax:       'Telefax',
  };

  return Object.entries(labelMap)
    .filter(([key]) => existing[key] && incoming[key] && existing[key] !== incoming[key])
    .map(([key, label]) => ({ label, current: existing[key], incoming: incoming[key] }));
};

/**
 * Shows a confirmation overlay. Calls onConfirm if user clicks Yes,
 * onCancel if user clicks No. The overlay is removed either way.
 */
const showConfirmOverlay = (conflicts, onConfirm, onCancel) => {
  const overlay = document.createElement('div');
  overlay.id = 'cvr-confirm-overlay';

  const fieldRows = conflicts
    .map(c => `<div style="margin-bottom:4px;">${c.label}: <span style="font-weight:500;color:var(--color-text-primary, #000);">${c.current}</span> → <span style="font-weight:500;color:var(--color-text-info, #0066cc);">${c.incoming}</span></div>`)
    .join('');

  overlay.innerHTML = `
    <div id="cvr-modal">
      <p style="font-size:15px;font-weight:500;color:var(--color-text-primary, #000);margin:0 0 6px;">Overwrite existing data?</p>
      <p style="font-size:13px;color:var(--color-text-secondary, #666);margin:0 0 12px;line-height:1.6;">
        The following fields already have values. Replace them with data from the CVR registry?
      </p>
      <div id="cvr-conflicts">${fieldRows}</div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button id="cvr-btn-no">No, keep current</button>
        <button id="cvr-btn-yes">Yes, update</button>
      </div>
    </div>
  `;

  Object.assign(overlay.style, {
    position:       'fixed',
    inset:          '0',
    background:     'rgba(0,0,0,0.45)',
    display:        'flex',
    alignItems:     'center',
    justifyContent: 'center',
    zIndex:         '9999',
  });

  const modal = overlay.querySelector('#cvr-modal');
  Object.assign(modal.style, {
    background:   'var(--color-background-primary, #fff)',
    border:       '0.5px solid var(--color-border-secondary, #ccc)',
    borderRadius: '12px',
    padding:      '1.5rem 1.75rem',
    maxWidth:     '380px',
    width:        '90%',
  });

  const conflictsEl = overlay.querySelector('#cvr-conflicts');
  Object.assign(conflictsEl.style, {
    background:   'var(--color-background-secondary, #f5f5f5)',
    borderRadius: '8px',
    padding:      '10px 12px',
    marginBottom: '1.25rem',
    fontSize:     '12px',
    color:        'var(--color-text-secondary, #666)',
    lineHeight:   '1.7',
  });

  const btnNo = overlay.querySelector('#cvr-btn-no');
  const btnYes = overlay.querySelector('#cvr-btn-yes');

  const sharedBtnStyle = {
    fontSize:     '13px',
    padding:      '6px 16px',
    borderRadius: '8px',
    border:       '0.5px solid var(--color-border-secondary, #ccc)',
    background:   'var(--color-background-primary, #fff)',
    color:        'var(--color-text-primary, #000)',
    cursor:       'pointer',
  };

  Object.assign(btnNo.style, sharedBtnStyle);
  Object.assign(btnYes.style, {
    ...sharedBtnStyle,
    background: 'var(--color-text-primary, #000)',
    color:      'var(--color-background-primary, #fff)',
    border:     'none',
  });

  const teardown = () => document.body.removeChild(overlay);

  btnYes.addEventListener('click', () => { teardown(); onConfirm(); });
  btnNo.addEventListener('click',  () => { teardown(); onCancel(); });

  document.body.appendChild(overlay);
};

/**
 * Applies CVR API data directly to form fields.
 */
const applyFormFields = (data) => {
  if (data?.vat)  document.querySelector('[name=cvrnr]').value    = data.vat;
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
  if (data?.city)    document.querySelector('[name=bynavn]').value  = data.city;
  if (data?.phone)   document.querySelector('[name=tlf]').value     = data.phone;
  if (data?.email)   document.querySelector('[name=email]').value   = data.email;
  if (data?.fax)     document.querySelector('[name=fax]').value     = data.fax;
};

/**
 * Maps a CVR API response to the same shape as getExistingFormData()
 * so conflict detection can compare apples to apples.
 */
const normaliseApiData = (data) => ({
  firmanavn: data?.name    ?? '',
  addr1:     data?.addressco ? `c/o ${data.addressco}` : (data?.address ?? ''),
  addr2:     data?.addressco ? (data?.address ?? '') : '',
  postnr:    data?.zipcode ?? '',
  bynavn:    data?.city    ?? '',
  tlf:       data?.phone   ?? '',
  fax:       data?.fax     ?? '',
});

/**
 * Fetches company data from CVR API and either applies it directly
 * or, when fields already contain data, asks the user first.
 * @param {string} param - search value
 * @param {string} country - country code
 * @param {string} type - 'vat' | 'phone'
 * @param {HTMLInputElement} triggerField - the field that triggered the lookup
 * @param {string} rawValue - the original raw value (before cleanup) so we can revert on cancel
 */
const cvrapi = async (param, country, type, triggerField, rawValue) => {
  try {
    const response = await fetch(`https://cvrapi.dk/api?${type}=${param}&country=${country}`, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });

    if (!response.ok) throw new Error(`CVR API error: ${response.status}`);

    const data = await response.json();
    const existing = getExistingFormData();
    const incoming = normaliseApiData(data);
    const conflicts = detectConflicts(existing, incoming);

    if (conflicts.length === 0) {
      // Nothing would be overwritten — apply immediately
      applyFormFields(data);
      return;
    }

    showConfirmOverlay(
      conflicts,
      () => applyFormFields(data),          // Yes: apply
      () => {
         //const cleaned = rawValue.replace(/^[\*\/\+]|[\*\/\+]$/g, '');
         //triggerField.value = cleaned.slice(0, -1);   //no don't
      }
    );
  } catch (error) {
    console.error('CVR API fetch failed:', error);
  }
};

/**
 * Processes an input field value and fires the API call when appropriate.
 */
const processInput = (element, type) => {
  const value = element.value.trim();

  if (/^\d{8}$/.test(value)) {
    cvrapi(value, 'dk', type, element, value);
    return;
  }

  if (/^[\*\/\+]\d{8}[\*\/\+]$/.test(value)) {
    const cleaned = value.slice(1, 9);
    element.value = cleaned;
    cvrapi(cleaned, 'dk', type, element, value);
  }
};

document.addEventListener('DOMContentLoaded', () => {
  const nyKontonrField = document.querySelector('[name=ny_kontonr]');
  if (nyKontonrField) {
    nyKontonrField.addEventListener('input', () => processInput(nyKontonrField, 'vat'));
  }

  const cvrnrField = document.querySelector('[name=cvrnr]');
  if (cvrnrField) {
    cvrnrField.addEventListener('input', () => processInput(cvrnrField, 'vat'));
  }

  const tlfField = document.querySelector('[name=tlf]');
  if (tlfField) {
    tlfField.addEventListener('input', () => processInput(tlfField, 'phone'));
  }
});