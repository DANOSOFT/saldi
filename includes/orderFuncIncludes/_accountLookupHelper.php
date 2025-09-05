<?php 
//.../includes/orderFuncIncludes/_accountLookupHelper.php
############


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

#############

    // Get current URL for API calls



$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
    . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
 // JS Script added at the end of HTML for client-side filtering
echo <<<JS
<script>
let currentPage = 1;
let rowsPerPage = 100;
let totalPages = 1;
let currentSort = 'firmanavn';
const o_art = '{$o_art}';
const currentUrl = "{$currentUrl}";
console.log("Current URL:", currentUrl);
fokus = "{$fokus}";
usedId = "{$id}";
bgcolor = "{$bgcolor}";
bgcolor5 = "{$bgcolor5}";

// Footer control handlers
document.addEventListener('DOMContentLoaded', () => {
  const rowsSelect = document.getElementById('rowsPerPageSelect');
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  if (rowsSelect) {
    rowsSelect.addEventListener('change', () => {
     // rowsPerPage = parseInt(rowsSelect.value);
     const selectedValue = rowsSelect.value;
        rowsPerPage = (selectedValue === 'all') ? 'all' : parseInt(selectedValue);

      currentPage = 1;
      fetchData();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        fetchData();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      if (currentPage < totalPages) {
        currentPage++;
        fetchData();
      }
    });
  }
});

function updateFooter(data) {
  totalPages = Math.ceil(data.totalRows / rowsPerPage);

  const footerInfo = document.getElementById('footerInfo'); 
  const prevBtn = document.getElementById('prevPage');
  const nextBtn = document.getElementById('nextPage');

  if (footerInfo) {
    footerInfo.innerHTML =
      'Page <strong>' + currentPage + '</strong> of <strong>' + totalPages + '</strong> | ' +
      'Rows per page: <strong>' + rowsPerPage + '</strong> | ' +
      'Total records: <strong>' + data.totalRows + '</strong>';
  }

  if (prevBtn) prevBtn.disabled = currentPage === 1;
  if (nextBtn) nextBtn.disabled = currentPage === totalPages;
}

function fetchData() {
  const filters = {
    kontonr: document.getElementById('filter_kontonr').value,
    firmanavn: document.getElementById('filter_firmanavn').value,
    addr1: document.getElementById('filter_addr1').value,
    addr2: document.getElementById('filter_addr2').value,
    postnr: document.getElementById('filter_postnr').value,
    bynavn: document.getElementById('filter_bynavn').value,
    land: document.getElementById('filter_land').value,
    kontakt: document.getElementById('filter_kontakt').value,
    o_art: '{$o_art}',
    fokus: fokus,
    email: document.getElementById('filter_email')?.value || '',
    cvrnr: document.getElementById('filter_cvrnr')?.value || '',
    ean: document.getElementById('filter_ean')?.value || '',
    betalingsbet: document.getElementById('filter_betalingsbet')?.value || '',
    betalingsdage: document.getElementById('filter_betalingsdage')?.value || '',
    id: usedId,
    sort: currentSort

  };

  const debitorIndex = currentUrl.indexOf('debitor');
  let apiPath = '';
  if (debitorIndex !== -1) {
    const basePath = currentUrl.substring(0, debitorIndex + 'debitor'.length);
    apiPath = basePath + '/accountLookupData.php';
  } else {
    console.error("Could not locate 'debitor' in the URL. Using fallback.");
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
      console.log("Fetched data:", data);
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

   entries.forEach(function(entry, index) {
    const tr = document.createElement('tr');

    // Alternate row background color using PHP-passed values
    const rowColor = (index % 2 === 0) ? bgcolor : bgcolor5;
    tr.style.backgroundColor = rowColor;

    tr.innerHTML =
      '<td><a href="ordre.php?fokus=' + fokus + '&id=' + usedId + '&konto_id=' + entry.id + '">' + entry.kontonr + '</a></td>' +
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

// Input filters trigger reload
document.querySelectorAll('input[id^="filter_"]').forEach(function(input) {
  input.addEventListener('input', function() {
    currentPage = 1;
    fetchData();
  });
});

//

let currentSortDirection = 'ASC';

function changeSort(column) {
  if (currentSort === column) {
    // Toggle sort direction
    currentSortDirection = (currentSortDirection === 'ASC') ? 'DESC' : 'ASC';
  } else {
    currentSort = column;
    currentSortDirection = 'ASC';
  }

  currentPage = 1; // Optionally reset to first page on sort
  fetchData();
}

//

window.addEventListener('load', fetchData);
</script>
JS;
############