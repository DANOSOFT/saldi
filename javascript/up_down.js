var boolCtrlDown = false;
var curField = null;

function doKeyDown(e) {
	if (!e) var e = window.event;
	if (e.keyCode) kCode = e.keyCode;
	else if (e.which) kCode = e.which;
	var elm;
	if (e.srcElement) elm = e.srcElement;
	else if (e.target) elm = e.target;
	else return false;
// alert(kCode);
	curField = (elm.tagName.toUpperCase() == 'INPUT' && elm.type == 'text') ? elm : null;
	var boolDoCancel = false;
	if (boolCtrlDown) {
		switch (kCode) {
			case 37: navHor('previous'); boolDoCancel = true; break; //shift + venstre pil
			case 39: if (boolCtrlDown) navHor('next'); boolDoCancel = true; break; // shift + hoejre pil
		}
	}
	else {
		switch (kCode) {
	  	case 38: navVer('previous'); boolDoCancel = true; break; //pil op
	 	 case 40: navVer('next'); boolDoCancel = true; break; //pil ned
		}
	}
	boolCtrlDown = (kCode == 17);
	if (boolDoCancel) {
		e.returnValue = false;
		e.cancelBubble = true;
		if (e.stopPropagation) e.stopPropagation();
		}
}

function navHor(dir) {
	var elm = eval('curField.parentElement.' + dir + 'Sibling');
	if (elm != null) {
		elm = elm.firstChild;
		if (elm != null && elm.tagName.toUpperCase() == 'INPUT' && elm.type == 'text') elm.focus();
		}
}

function navVer(dir) {
	var elm = eval('curField.parentElement.parentElement.' + dir + 'Sibling');
	var ix = 0;
	var tmpElm = curField.parentElement;
	while (tmpElm.previousSibling) {
		ix++;
		tmpElm = tmpElm.previousSibling;
		}
	if (elm != null) {
		elm = elm.firstChild;
		if (elm != null) {
			i = 0;
			while (ix > i) {
				if (elm.nextSibling) {
					elm = elm.nextSibling;
					}
				else return false;
				i++;
				}
			if (elm.firstChild != null) elm.firstChild.focus();
			}
		}
}