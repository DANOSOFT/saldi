
document.getElementById("save-doc").onclick = function (event){
  var old_page = base.page;
  page = -1;
  base.page = -1;
  renderBoard();
  // Collect all the data into a simpe array of objects
  var data = [];
  for (let i = 0; i < tables.length; i++){
    data.push({
      "id": tables[i].idx,
      "name": tables[i].name,
      "tooltip": tables[i].tooltip,
      "width": tables[i].elm.offsetWidthe,
      "height": tables[i].elm.offsetHeighte,
      "posX": tables[i].elm.offsetLeft,
      "posY": tables[i].elm.offsetTop,
      "type": tables[i].type,
      "page": tables[i].page
    })
  }
  console.log(data)

  page = old_page;
  base.page = old_page;
  renderBoard();

  fetch("save.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify(data)
  }).then((res) => {
    location.reload()
  });
}
