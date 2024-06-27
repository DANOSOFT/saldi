// Setup listners

document.getElementById("name-inp").onkeyup = (e) => {change(e, "name-inp")};
document.getElementById("table-text-inp").onkeyup = (e) => {change(e, "table-text-inp")};
document.getElementById("size-x-inp").onkeyup = (e) => {change(e, "size-x-inp")};
document.getElementById("size-x-inp").onchange = (e) => {change(e, "size-x-inp")};
document.getElementById("size-y-inp").onkeyup = (e) => {change(e, "size-y-inp")};
document.getElementById("size-y-inp").onchange = (e) => {change(e, "size-y-inp")};
document.getElementById("pages").onchange = (e) => {change(e, "pages")}

function change(e, inpId){
  const elm = document.getElementById(inpId);
  if (inpId === "name-inp"){
    exampleTable.name = elm.value;
  } else if (inpId === "table-text-inp"){
    exampleTable.tooltip = elm.value;
  } else if (inpId === "size-x-inp"){
    if (elm.value > 1200){
      elm.value = 1200;
    }
    exampleTable.width = elm.value;
  } else if (inpId === "size-y-inp"){
    if (elm.value > 800){
      elm.value = 800;
    }
    exampleTable.height = elm.value;
  } else if (inpId === "pages"){
    console.log(elm.value);
    exampleTable.page = elm.value;
  }
  exampleTable.render();
}

// Get the modal
var modal = document.getElementById("myModal");

// Get the button that opens the modal
var btn = document.getElementById("open-modal");

// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];

// When the user clicks on the button, open the modal
btn.onclick = function() {
  modal.style.display = "block";
  const table = tables[get_table_index(elm.tableId)];

  // Update modal data
  if (table.tooltip != undefined){
    document.getElementById("table-settings").style.display = "block";
    document.getElementById("example-tooltip").style.display = "block";
    document.getElementById("modal-header").innerText = "Rediger Bord";
    document.getElementById("modal-description").innerText = "Her kan du redigere dit bord.";
    exampleTable.name = table.name;
    exampleTable.tooltip = table.tooltip;
  } else {
    exampleTable.name = "";
    exampleTable.tooltip = "";
    document.getElementById("table-settings").style.display = "none";
    document.getElementById("example-tooltip").style.display = "none";
    document.getElementById("modal-header").innerText = "Rediger Figur";
    document.getElementById("modal-description").innerText = "Her kan du redigere din firgur.";
  }
  exampleTable.width = table.elm.offsetWidthe;
  exampleTable.height = table.elm.offsetHeighte;
  exampleTable.page = base.page;


  document.getElementById("name-inp").value = table.name;
  document.getElementById("table-text-inp").value = table.tooltip;
  document.getElementById("size-x-inp").value = table.width;
  document.getElementById("size-y-inp").value = table.height;

  // Set default select value for page
  var mySelect = document.getElementById('pages');

  for(var i, j = 0; i = mySelect.options[j]; j++) {
      if(i.value == base.page) {
          mySelect.selectedIndex = j;
          break;
      }
  }

  exampleTable.render();
}

// When the user clicks on <span> (x), close the modal
span.onclick = function() {
  modal.style.display = "none";
}

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (event.target == modal || event.target.className === "close-modal") {
    modal.style.display = "none";
  }
} 

document.getElementById("save").onclick = function (event){
  const table = tables[get_table_index(elm.tableId)];

  // Update modal data
  table.name = exampleTable.name;
  table.tooltip = exampleTable.tooltip;
  table.elm.offsetWidthe = parseInt(exampleTable.width);
  table.elm.offsetHeighte = parseInt(exampleTable.height);
  table.page = parseInt(exampleTable.page);

  renderBoard();
  modal.style.display = "none";
}

document.getElementById("delete-modal").onclick = function (event){
  var table = tables[get_table_index(elm.tableId)];
  table.elm.remove();
  tables.splice(get_table_index(elm.tableId), 1);
  modal.style.display = "none";
}

document.getElementById("delete-context").onclick = function (event){
  var table = tables[get_table_index(elm.tableId)];
  table.elm.remove();
  tables.splice(get_table_index(elm.tableId), 1);
  modal.style.display = "none";
}

document.getElementById("add-context").onclick = function (event){
  // We add the large number to avoid id collisions with the db
  var new_table = new Table(tables.length+10000000000, base, event.clientX, event.clientY, 100, 100, tables.length+1, "", base.page);
  new_table.render();
  tables.push(new_table);
}

document.getElementById("add-figure").onclick = function (event){
  var new_table = new Figure(tables.length+10000000000, base, event.clientX, event.clientY, 20, 20, base.page);
  new_table.render();
  tables.push(new_table);
}
