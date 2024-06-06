function open_page(page) {
  for (let i = 0; i < pages.length; i++){
    var elms = document.getElementsByClassName(pages[i]);
    for (let x = 0; x < elms.length; i++){
      elms[x].style.display = "none";
    }
  }

  var elms = document.getElementsByClassName(page);
  for (let x = 0; x < elms.length; i++){
    elms[x].style.display = "block";
  }
}
