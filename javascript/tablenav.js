// Searches for table rows with the class 'ordrelinje', this will search for all text fields and input in the row and allow the user to naviagte using ctrl + arrowkeys


document.addEventListener('keydown', function(event) {
// Only navigate when Ctrl is held down
if (event.ctrlKey) {
  const rows = Array.from(document.getElementsByClassName('ordrelinje'));

  // Get all inputs, textareas, and selects inside the rows, but ignore hidden elements
  const inputsTextareasSelects = rows.flatMap(row => 
    Array.from(row.querySelectorAll('input:not([type="hidden"]), textarea, select'))
      .filter(el => el.offsetParent !== null && window.getComputedStyle(el).visibility !== 'hidden')
  );
  
  const activeElement = document.activeElement;

  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(activeElement.tagName)) {
    const currentIndex = inputsTextareasSelects.indexOf(activeElement);
    let nextIndex = currentIndex;
    
    // Get column count once, outside of the switch, to reuse it
    const columnCount = Array.from(rows[0].querySelectorAll('input:not([type="hidden"]), textarea, select'))
      .filter(el => el.offsetParent !== null && window.getComputedStyle(el).visibility !== 'hidden')
      .length;

    switch (event.key) {
      case 'ArrowRight':
        nextIndex = currentIndex + 1;
        break;
      case 'ArrowLeft':
        nextIndex = currentIndex - 1;
        break;
      case 'ArrowDown':
        const currentRowIndex = Math.floor(currentIndex / columnCount);
        const columnIndex = currentIndex % columnCount;
        nextIndex = (currentRowIndex + 1) * columnCount + columnIndex;
        break;
      case 'ArrowUp':
        const upRowIndex = Math.floor(currentIndex / columnCount);
        nextIndex = (upRowIndex - 1) * columnCount + (currentIndex % columnCount);
        break;
      default:
        return;
    }

    // Ensure next element exists and focus it
    if (inputsTextareasSelects[nextIndex]) {
      const nextElement = inputsTextareasSelects[nextIndex];
      nextElement.focus();
      event.preventDefault(); // Prevent default scrolling behavior

      // Select the text if it's an input or textarea
      if (nextElement.tagName === 'INPUT' || nextElement.tagName === 'TEXTAREA') {
        nextElement.select();
      }
    }
  }
}
});