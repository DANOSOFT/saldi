<?php
// ..includes/order-row-clickable.js.php
// 20260603 Sawaneh Make the whole order line clickable (and right-clickable
//                  for "open in new tab/window"), not just the order number.

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
    // bareHref is relative (e.g. "ordre.php?..."); turn it into a shell route.
    return '/' + instance + '/index/main.php#' + routeDir + '/' + bareHref;
  }

  document.querySelectorAll("table[id^='datatable-ordrelst_'] tbody tr, table[id^='datatable-kredorliste_'] tbody tr").forEach(function (row) {
    if (row.classList.contains('filler-row')) return;

    var orderLink = row.querySelector("a[href*='ordre.php']");
    if (!orderLink) return;
    var bareHref  = orderLink.getAttribute('href');
    var shellHref = toShellHref(bareHref);
    var linkTitle = orderLink.getAttribute('title') || '';

    // Re-point the existing order-number link at the shell URL too, and mark it,
    // so right-clicking the number opens a tab WITH the menu like the rest of the line.
    orderLink.setAttribute('href', shellHref);
    orderLink.classList.add('order-row-cell-link');

    row.querySelectorAll('td').forEach(function (cell) {
      // Leave cells that already hold interactive content (the order-number
      // link itself, the selection checkbox, the action icons) exactly as-is.
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

    // Plain left-click anywhere on the line -> open the order INSIDE the shell
    // iframe (fast, keeps the menu, avoids loading the shell within itself).
    // Modifier clicks fall through to the browser so they use shellHref (new tab).
    row.addEventListener('click', function (e) {
      if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      var hit = e.target.closest('a, button, input, select, textarea, label, svg');
      if (hit) {
        // Only intercept our own order links; the checkbox and action icons keep default.
        if (hit.classList && hit.classList.contains('order-row-cell-link')) {
          e.preventDefault();
          window.location.href = bareHref;
        }
        return;
      }
      // Click landed on empty cell space / padding.
      if (window.getSelection && window.getSelection().toString()) return; // allow text selection
      window.location.href = bareHref;
    });

    // Middle-click on empty cell space -> new tab with the shell (menu) version.
    // (Middle-clicking the anchors themselves already opens shellHref natively.)
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
