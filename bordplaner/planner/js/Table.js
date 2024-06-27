function round_20(num){
  return Math.round(num / 20) * 20;
}

class Table {
  constructor(idx, base, x, y, width, height, name, people, page) {
    this.base = base;
    this.name = name;
    this.type = "table";
    this.page = page;
    this.tooltip = people;
    this.idx = idx
    this.width = width;
    this.height = height;

    this.elm = document.createElement("div");
    this.elm.className = "table";
    this.elm.style.width = width + "px";
    this.elm.style.height = height + "px";
    this.elm.tableId = idx;
    this.elm.zIndex = idx;

    this.elm.offsetWidthe = width;
    this.elm.offsetHeighte = height;

    // Setup the text inside the element
    this.inner = document.createElement("span");
    this.inner.className = "table-text";
    this.inner.innerText = name;
    this.inner.style.fontSize = Math.min(Math.min(this.width, this.height) *.50, 40);

    if (people !== "") {
      // Create the people text
      this.people = document.createElement("div");
      this.people.className = "table-tooltip";
      this.people.innerText = people;
      this.people.zIndex = idx;

      this.elm.appendChild(this.people);
    }

    // Setup resizer
    var startX, startY, startWidth, startHeight;
    var element = null;

    var both = document.createElement("div");
    both.className = "resizer-both";
    this.elm.appendChild(both);
    both.addEventListener("mousedown", initDrag, false);
    both.parentPopup = this.elm;

    this.elm.appendChild(this.inner);
    this.base.appendChild(this.elm);

    this.setPos(x, y)
    this.dragElement(this.elm);
    this.base.dragging = false;

    function initDrag(e) {
      element = this.parentPopup;

      startX = e.clientX;
      startY = e.clientY;
      startWidth = parseInt(
        document.defaultView.getComputedStyle(element).width,
        10
      );
      startHeight = parseInt(
        document.defaultView.getComputedStyle(element).height,
        10
      );
      document.documentElement.addEventListener("mousemove", doDrag, false);
      document.documentElement.addEventListener("mouseup", stopDrag, false);
    }

    function doDrag(e) {
      document.getElementById('base').dragging = true;

      element.offsetWidthe = startWidth + (e.clientX - startX);
      element.offsetHeighte = startHeight + (e.clientY - startY);

      element.style.width = element.offsetWidthe + "px";
      element.style.height = element.offsetHeighte + "px";

      let inner = element.children[2];
      inner.style.fontSize = Math.min(Math.min(element.offsetWidthe, element.offsetHeighte) *.50, 40);
    }

    function stopDrag() {
      document.getElementById('base').dragging = false;

      // Setup min values
      element.offsetWidthe = round_20(Math.max(element.offsetWidthe, 20));
      element.offsetHeighte = round_20(Math.max(element.offsetHeighte, 20));

      element.style.width = element.offsetWidthe + "px";
      element.style.height = element.offsetHeighte + "px";

      document.documentElement.removeEventListener("mousemove", doDrag, false);
      document.documentElement.removeEventListener("mouseup", stopDrag, false);
    }

  }

  render(){
    this.inner.innerText = this.name;
    // Handel setting up tooltips
    if (this.people !== undefined && this.tooltip !== ""){
      this.people.innerText = this.tooltip;
    } else if (this.people === undefined && this.tooltip !== ""){
      this.people = document.createElement("div");
      this.people.className = "table-tooltip";
      this.people.innerText = this.tooltip;
      this.people.zIndex = this.elm.zIndex;

      this.elm.appendChild(this.people);
    } else if (this.people !== undefined && this.tooltip === ""){
      this.people.remove();
      this.people = undefined;
    } 

    this.elm.style.width = this.elm.offsetWidthe + "px";
    this.elm.style.height = this.elm.offsetHeighte + "px";
    this.inner.style.fontSize = Math.min(Math.min(this.elm.offsetWidthe, this.elm.offsetHeighte) *.50, 40);

    if (this.page !== this.base.page && this.base.page !== -1){
      this.elm.style.display = "none";
    } else {
      this.elm.style.display = "block";
    }
  }

  setPos(x, y) {
    this.elm.style.top = y + "px";
    this.elm.style.left = x + "px";
  }

  dragElement(elmnt) {
    var pos1 = 0, pos2 = 0, pos3 = 0, pos4 = 0;
    if (document.getElementById(elmnt.id + "header")) {
      // if present, the header is where you move the DIV from:
      document.getElementById(elmnt.id + "header").onmousedown = dragMouseDown;
    } else {
      // otherwise, move the DIV from anywhere inside the DIV:
      elmnt.onmousedown = dragMouseDown;
    }

    function dragMouseDown(e) {
      // If it is a leftclick
      if (e.button === 0) {
        e = e || window.event;
        e.preventDefault();
        // get the mouse cursor position at startup:
        pos3 = e.clientX;
        pos4 = e.clientY;
        document.onmouseup = closeDragElement;
        // call a function whenever the cursor moves:
        document.onmousemove = elementDrag;
      } else {
        // Open the context menu for delete and edit item
      }
    }

    function elementDrag(e) {
      if (e.className == "resizer-both" || document.getElementById('base').dragging){
        return;
      }

      e = e || window.event;
      e.preventDefault();

      // calculate the new cursor position:
      pos1 = pos3 - e.clientX;
      pos2 = pos4 - e.clientY;
      pos3 = e.clientX;
      pos4 = e.clientY;

      // set the element's new position:
      const x = elmnt.offsetLeft - pos1;
      const y = elmnt.offsetTop - pos2;

      elmnt.style.top = y + "px";
      elmnt.style.left = x + "px";
    }

    function closeDragElement() {
      console.log("End")
      elmnt.style.top = round_20(elmnt.style.top.slice(0, -2)) + "px";
      elmnt.style.left = round_20(elmnt.style.left.slice(0, -2)) + "px";
      console.log(elmnt.style.left)

      // stop moving when mouse button is released:
      document.onmouseup = null;
      document.onmousemove = null;
    }
  }
}

