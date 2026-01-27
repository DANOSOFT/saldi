<?php 
// ../kreditor/_accountLookupHelper.php

print <<<HTML
<div class="fixed-footer" id="staticFooter">
  <div class="left">
    <button id="prevPage">&laquo; Prev</button>
    <button id="nextPage">Next &raquo;</button>
  </div>
  <div class="center" id="footerInfo">Loading footer info...</div> 
  <div class="right">
    Rows per page:
    <select id="rowsPerPageSelect">
      <option value="100" selected>100</option>
      <option value="200">200</option>
      <option value="500">500</option>
      <option value="3000">3000</option>
      <option value="all">All</option>
    </select>
  </div>
</div>

<style>
.fixed-footer {
  position: fixed;
  bottom: 0;
  left: 0;
  width: 100%;
  background-color: #f0f0f0;
  padding: 10px 20px;
  font-weight: bold;
  border-top: 1px solid #ccc;
  box-shadow: 0 -1px 5px rgba(0, 0, 0, 0.1);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.fixed-footer .left, .fixed-footer .center, .fixed-footer .right {
  flex: 1;
}
.fixed-footer select, .fixed-footer button {
  padding: 4px 8px;
}
body {
  margin-bottom: 70px;
}
</style>
HTML;

$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

echo <<<JS
<script>
let currentPage = 1;
let rowsPerPage = 100;
let totalPages = 1;
let currentSort = 'firmanavn';
let currentSortDirection = 'ASC';

const currentUrl = "{$currentUrl}";
const fokus = "{$fokus}";
const usedId = "{$id}";
const bgcolor = "{$bgcolor}";
const bgcolor5 = "{$bgcolor5}";

// Event listeners
document.addEventListener('DOMContentLoaded', () => {
  const rowsSelect = document.getElementById('rowsPerPageSelect');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  rowsSelect?.addEventListener('change', () => {
    const selected = rowsSelect.value;
    rowsPerPage = (selected === 'all') ? 'all' : parseInt(selected);
    currentPage = 1;
    fetchData();
  });

  prevBtn?.addEventListener('click', () => {
    if (currentPage > 1) {
      currentPage--;
      fetchData();
    }
  });

  nextBtn?.addEventListener('click', () => {
    if (currentPage < totalPages) {
      currentPage++;
      fetchData();
    }
  });
});

function updateFooter(data) {
  totalPages = Math.ceil(data.totalRows / (rowsPerPage === 'all' ? data.totalRows : rowsPerPage));

  const footerInfo = document.getElementById('footerInfo'); 
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  footerInfo.innerHTML =
    'Page <strong>' + currentPage + '</strong> of <strong>' + totalPages + '</strong> | ' +
    'Rows per page: <strong>' + rowsPerPage + '</strong> | ' +
    'Total records: <strong>' + data.totalRows + '</strong>';

  prevBtn.disabled = currentPage === 1;
  nextBtn.disabled = currentPage >= totalPages;
}

function fetchData() {
  const filters = {
    kontonr: document.getElementById('filter_kontonr')?.value,
    firmanavn: document.getElementById('filter_firmanavn')?.value,
    addr1: document.getElementById('filter_addr1')?.value,
    addr2: document.getElementById('filter_addr2')?.value,
    postnr: document.getElementById('filter_postnr')?.value,
    bynavn: document.getElementById('filter_bynavn')?.value,
    land: document.getElementById('filter_land')?.value,
    kontakt: document.getElementById('filter_kontakt')?.value,
    fokus: fokus,
    id: usedId,
    sort: currentSort
  };

  const kreditorIndex = currentUrl.indexOf('kreditor');
  let apiPath = '';
  if (kreditorIndex !== -1) {
    const basePath = currentUrl.substring(0, kreditorIndex + 'kreditor'.length);
    apiPath = basePath + '/accountLookupData.php';
	console.log("Located 'kreditor' in the URL. API Path: " + apiPath);
  } else {
    console.error("Could not locate 'kreditor' in the URL. Using fallback.");
    apiPath = window.location.origin + '/accountLookupData.php';
  }

  let url = new URL(apiPath);
  url.searchParams.set('page', currentPage);
  url.searchParams.set('rowsPerPage', rowsPerPage); 
  url.searchParams.set('sort', currentSort); 
  url.searchParams.set('direction', currentSortDirection);

  for (const key in filters) {
    if (filters[key]) url.searchParams.set(key, filters[key]);
  }

  fetch(url)
    .then(res => res.json())
    .then(data => {
      renderTable(data.data);
      updateFooter(data);
    })
    .catch(err => console.error("Fetch error:", err));
}

function renderTable(entries) {
  const tbody = document.getElementById('tableBody');
  const noResultsForm = document.getElementById('noResultsForm');

  tbody.innerHTML = '';
  if (entries.length === 0) {
    tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No results found</td></tr>';
    if (noResultsForm) noResultsForm.style.display = 'block';
    return;
  }

  if (noResultsForm) noResultsForm.style.display = 'none';

  entries.forEach((entry, index) => {
    const tr = document.createElement('tr');
    tr.style.backgroundColor = (index % 2 === 0) ? bgcolor : bgcolor5;

   tr.innerHTML =
    '<td><a href="ordre.php?fokus=' + fokus + '&id=' + usedId + 
    '&konto_id=' + entry.id + 
    '&kontonr=' + encodeURIComponent(entry.kontonr) + 
    '">' + entry.kontonr + '</a></td>' +
      '<td>' + entry.firmanavn + '</td>' +
      '<td>' + entry.addr1 + '</td>' +
      '<td>' + entry.addr2 + '</td>' +
      '<td>' + entry.postnr + '</td>' +
      '<td>' + entry.bynavn + '</td>' +
      '<td>' + entry.land + '</td>' +
      '<td>' + entry.kontakt + '</td>' +
      '<td>' + entry.tlf + '</td>';

    tbody.appendChild(tr);
  });
}

function changeSort(column) {
  if (currentSort === column) {
    currentSortDirection = (currentSortDirection === 'ASC') ? 'DESC' : 'ASC';
  } else {
    currentSort = column;
    currentSortDirection = 'ASC';
  }
  currentPage = 1;
  fetchData();
}

// Auto-fetch data on page load
window.addEventListener('load', fetchData);

// Re-fetch on filter input change
document.querySelectorAll('input[id^="filter_"]').forEach(function(input) {
  input.addEventListener('input', function() {
    currentPage = 1;
    fetchData();
  });
});
</script>
JS;
