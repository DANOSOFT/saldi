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

      let skip = false;
      for (let td of tds) {
        if (
          td.querySelector('button,b, select, input, textarea, i, img')
        ) {
          skip = true;
          break;
        }
      }

      if (!skip) row.classList.add('hover-highlight');
    });
  });
});
</script>
HTML;
?>
