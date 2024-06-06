class Figure {
  constructor(idx, base, x, y, width, height, page) {
    this.base = base;
    this.name = name;
    this.page = page;
    this.type = "rect";
    this.idx = idx;
    this.width = width;
    this.height = height;

    this.elm = document.createElement("div");
    this.elm.className = "figure";
    this.elm.style.width = width + "px";
    this.elm.style.height = height + "px";
    this.elm.tableId = idx;
    this.elm.zIndex = idx;

    this.elm.offsetWidthe = width;
    this.elm.offsetHeighte = height;

    this.base.appendChild(this.elm);

    // Setup resizer
    var startX, startY, startWidth, startHeight;
    var element = null;

    var both = document.createElement("div");
    both.className = "resizer-both";
    this.elm.appendChild(both);
    both.addEventListener("mousedown", initDrag, false);
    both.parentPopup = this.elm;

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
    }

    function stopDrag() {
      document.getElementById('base').dragging = false;

      // Setup min values
      element.offsetWidthe = Math.max(element.offsetWidthe, 20);
      element.offsetHeighte = Math.max(element.offsetHeighte, 20);

      element.style.width = element.offsetWidthe + "px";
      element.style.height = element.offsetHeighte + "px";

      document.documentElement.removeEventListener("mousemove", doDrag, false);
      document.documentElement.removeEventListener("mouseup", stopDrag, false);
    }
  }

  render(){
    this.elm.style.width = this.elm.offsetWidthe + "px";
    this.elm.style.height = this.elm.offsetHeighte + "px";

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
      // stop moving when mouse button is released:
      document.onmouseup = null;
      document.onmousemove = null;
    }
  }
}


