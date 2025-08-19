<?php
print <<<HTML
<style>
.hover-highlight:hover {
  outline: 2px solid #000;
  background-color: #f9f9f9;
  cursor: pointer;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('table').forEach(table => {
    table.querySelectorAll('tr').forEach(row => {
      const tds = row.querySelectorAll('td');
      if (tds.length <= 1) return;
      if (row.offsetParent === null) return;
      if (row.classList.contains('noHover')) return;
      
      let hasLabel = false;
      let skip = false;

      for (let td of tds) {
        if (td.querySelector('label')) {
          hasLabel = true;
        }

        const interactive = td.querySelector('button, select, input:not([type="hidden"]), b, i, img');
        if (interactive) {
          skip = true;
          break;
        }
      }

      if (!skip || hasLabel) {
        row.classList.add('hover-highlight');
      }
    });
  });
});
</script>
HTML;
?>
