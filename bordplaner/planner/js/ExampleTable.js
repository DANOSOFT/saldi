class ExampleTable {
  constructor(width, height, name, tooltip){
    this.width = width;
    this.height = height;
    this.name = name;
    this.tooltip = tooltip;

    // Get the elm
    const elm = document.getElementById("modal-preview");
    this.elm = elm.children[0];
  }

  render() {
    this.elm.style.height = this.height + "px";
    this.elm.style.width = this.width + "px";
    this.elm.children[0].innerText = this.name;
    this.elm.children[1].innerText = this.tooltip;
  }
}
