function update_date(from_id, to_id, out_id) {
	console.log("Hejjj");
	let from = document.getElementById(from_id);
	let to   = document.getElementById(to_id);
	let out  = document.getElementById(out_id);

	if (to.value == "" && from.value) {
		if ((""+parseInt(from.value.split('-')[0])).length == 4) {
			to.value = from.value;
		}
	}

	let datestr = ((""+from.value).split("-").reverse()).join("");
	// Format str by removing decade
	datestr = datestr.slice(0, 4) + datestr.slice(6);

	let datestr2 = ((""+to.value).split("-").reverse()).join("");
	// Format str by removing decade
	datestr2 = datestr2.slice(0, 4) + datestr2.slice(6);

	if (from.value == "") {
		to.value = "";
		out.value = "";
	} else if (to.value == "") {
		if ((""+parseInt(from.value.split('-')[2]).length) == 4) {
			out.value = `${datestr}`;
		}
	} else {
		out.value = `${datestr}:${datestr2}`;
	}
}

