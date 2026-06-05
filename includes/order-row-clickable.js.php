<?php
// ..includes/order-row-clickable.js.php
// 20260603 Sawaneh Make the whole debitor order line clickable (and right-clickable for "open in new tab/window")

print <<<HTML
<style>
  tr.order-row-link:hover {
    background-color: #f9f9f9;
    cursor: pointer;
  }
  tr.order-row-link td a.order-row-cell-link {
    display: block;
    color: inherit;
    text-decoration: none;
  }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {

  var parts    = window.location.pathname.split('/'); 
  var instance = parts[1] || '';
  var routeDir = '/' + parts.slice(2, -1).join('/');  

  function toShellHref(bareHref) {
    return '/' + instance + '/index/main.php#' + routeDir + '/' + bareHref;
  }

  document.querySelectorAll("table[id^='datatable-ordrelst_'] tbody tr").forEach(function (row) {
    if (row.classList.contains('filler-row')) return;

    var orderLink = row.querySelector("a[href*='ordre.php']");
    if (!orderLink) return;
    var bareHref  = orderLink.getAttribute('href');
    var shellHref = toShellHref(bareHref);
    var linkTitle = orderLink.getAttribute('title') || '';

    orderLink.setAttribute('href', shellHref);
    orderLink.classList.add('order-row-cell-link');

    row.querySelectorAll('td').forEach(function (cell) {
   
      if (cell.querySelector('a, button, input, select, textarea')) return;
      if (cell.getAttribute('data-rowlink-done')) return;

      var link = document.createElement('a');
      link.className = 'order-row-cell-link';
      link.setAttribute('href', shellHref);
      if (linkTitle) link.setAttribute('title', linkTitle);
      while (cell.firstChild) {
        link.appendChild(cell.firstChild);
      }
      cell.appendChild(link);
      cell.setAttribute('data-rowlink-done', '1');
    });

    row.classList.add('order-row-link');

    row.addEventListener('click', function (e) {
      if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      var hit = e.target.closest('a, button, input, select, textarea, label, svg');
      if (hit) {
        if (hit.classList && hit.classList.contains('order-row-cell-link')) {
          e.preventDefault();
          window.location.href = bareHref;
        }
        return;
      }
      if (window.getSelection && window.getSelection().toString()) return; // allow text selection
      window.location.href = bareHref;
    });

    row.addEventListener('auxclick', function (e) {
      if (e.button !== 1) return;
      if (e.target.closest('a, button, input, select, textarea, label, svg')) return;
      window.open(shellHref, '_blank');
    });
  });
});
</script>
HTML;
?>
