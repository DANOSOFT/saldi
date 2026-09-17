document.onclick = hideMenu;
document.oncontextmenu = rightClick;

const base = document.getElementById("base");
var elm = null;
const exampleTable = new ExampleTable(100, 100, "", "", 0);

function open_page(id) {
  if (document.getElementsByClassName("selected").length !== 0){
    document.getElementsByClassName("selected")[0].className = "";
  }
  document.getElementById(`switch${id}`).className = "selected";
  var page = id;
  base.page = page;
  renderBoard();
}

function hideMenu() {
  document.getElementById("contextMenu").style.display = "none";
}

function get_table_index(id) {
  for (let i = 0; i < tables.length; i++){
    if (tables[i].idx == id){
      return i;
    }
  }
}

function rightClick(e) {
  if (e.target.id !== "base" && e.target.className !== "table" && e.target.className !== "figure"){
    hideMenu();
    return 0;
  }

  e.preventDefault();

  if (document.getElementById("contextMenu").style.display == "block"){
    hideMenu();
  } else {
    elm = e.target;
    var elm_parent = elm.parentNode;
    var menu = document.getElementById("contextMenu");

    // Check where the right click happened and configure the menu as such
    if (elm.id === "base"){
      menu.children[0].children[0].style.display = "none";
      menu.children[0].children[1].style.display = "none";
      menu.children[0].children[2].style.display = "flex";
      menu.children[0].children[3].style.display = "flex";
    } else if (elm.className === "table" || elm.className === "figure") {
      menu.children[0].children[0].style.display = "flex";
      menu.children[0].children[1].style.display = "flex";
      menu.children[0].children[2].style.display = "none";
      menu.children[0].children[3].style.display = "none";
    }

    menu.style.display = 'block';
    menu.style.left = e.pageX + "px";
    menu.style.top = e.pageY + "px";
  }
}


function renderBoard(){
  // Update all the tables size and titles
  for (let i = 0; i < tables.length; i++){
    tables[i].render();
  }
}
setTimeout(renderBoard, 300);
