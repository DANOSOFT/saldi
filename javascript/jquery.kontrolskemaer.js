/*
// her er function til sortering i liste
$(document).ready(function(){
	$("#sort a").click(function(e) {
	var desc = $(this).hasClass("asc"),
			sort = this.hash.substr(1),
			list = $("#things");
	list.append(list.children().get().sort(function(a, b) {
			var aProp = $(a).find("span."+sort).text(),
					bProp = $(b).find("span."+sort).text();
			return (aProp > bProp ? 1 : aProp < bProp ? -1 : 0) * (desc ? -1 : 1);
	}));
	$(this).toggleClass("desc", desc)
				.toggleClass("asc", !desc)
				.siblings().removeClass("asc desc");
	e.preventDefault();
	});
});
*/
// Her er function til autocomplete
$(document).ready(function(){
	// Labels in the user's language from sagerTekst (printed by kontrolskemaer.php), Danish as fallback
	var tekst = window.sagerTekst || {};

	function label(key, dansk) {
		return "<b>" + (tekst[key] || dansk) + ":</b>";
	}

	// The stored task name "Opgave N" is shown as e.g. "Task N"; other names are the user's own text
	function opgaveNavn(navn) {
		var match = /^Opgave (\d+)$/.exec(navn);
		return (match && tekst.opgave) ? tekst.opgave + " " + match[1] : navn;
	}

	function formatItemDato(row) {
		return row[0] + " - " + label("sagsnr", "Sagsnr") + " <i>" + row[1] + "</i> - " + label("af", "Af") + " <i>" + row[2] + "</i><br>" + label("adresse", "Adresse") + " <i>" + row[3] + "</i><br>" + label("skema", "Skema") + " <i>" + row[4] + "</i> - " + label("opgave", "Opgave") + " <i>" + opgaveNavn(row[5]) + "</i>";
	}
	
	function formatItemSagsnr(row) {
		return row[0] + " - " + label("dato", "Dato") + " <i>" + row[1] + "</i> - " + label("af", "Af") + " <i>" + row[2] + "</i><br>" + label("adresse", "Adresse") + " <i>" + row[3] + "</i><br>" + label("skema", "Skema") + " <i>" + row[4] + "</i> - " + label("opgave", "Opgave") + " <i>" + opgaveNavn(row[5]) + "</i>";
	}
	
	function formatItemAf(row) {
		return row[0] + " - " + label("dato", "Dato") + " <i>" + row[1] + "</i> - " + label("sagsnr", "Sagsnr") + " <i>" + row[2] + "</i><br>" + label("adresse", "Adresse") + " <i>" + row[3] + "</i><br>" + label("skema", "Skema") + " <i>" + row[4] + "</i> - " + label("opgave", "Opgave") + " <i>" + opgaveNavn(row[5]) + "</i>";
	}
	
	function formatItemAdresse(row) {
		return row[0] + "<br>" + label("dato", "Dato") + " <i>" + row[1] + "</i> - " + label("sagsnr", "Sagsnr") + " <i>" + row[2] + "</i>" + label("af", "Af") + " <i>" + row[3] + "</i><br>" + label("skema", "Skema") + " <i>" + row[4] + "</i> - " + label("opgave", "Opgave") + " <i>" + opgaveNavn(row[5]) + "</i>";
	}

	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".k_dato").autocomplete("autocomplete.php?mode=k_dato", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemDato,
		formatResult: formatResult
	});
	$(".k_dato").result(function(event, data, formatted) {
		$(this).parent().next().find(".k_sagsnr").val(data[1]);
		$(this).parent().next().next().find(".k_af").val(data[2]);
		$(this).parent().next().next().next().find(".k_adresse").val(data[3]);
		$(this).parent().next().next().next().next().find(".id").val(data[6]);
		$(this).parent().next().next().next().next().next().find(".fase").val(data[7]);
		$(this).parent().next().next().next().next().next().next().find(".sag_id").val(data[8]);
		$(this).parent().next().next().next().next().next().next().next().find(".tjek_id").val(data[9]);
	});
							
	$(".k_sagsnr").autocomplete("autocomplete.php?mode=k_sagsnr", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemSagsnr,
			formatResult: formatResult
	});
	$(".k_sagsnr").result(function(event, data, formatted) {
		$(this).parent().prev().find(".k_dato").val(data[1]);
		$(this).parent().next().find(".k_af").val(data[2]);
		$(this).parent().next().next().find(".k_adresse").val(data[3]);
		$(this).parent().next().next().next().find(".id").val(data[6]);
		$(this).parent().next().next().next().next().find(".fase").val(data[7]);
		$(this).parent().next().next().next().next().next().find(".sag_id").val(data[8]);
		$(this).parent().next().next().next().next().next().next().find(".tjek_id").val(data[9]);
	});
	
	$(".k_af").autocomplete("autocomplete.php?mode=k_af", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAf,
			formatResult: formatResult
	});
	$(".k_af").result(function(event, data, formatted) {
		$(this).parent().prev().find(".k_sagsnr").val(data[2]);
		$(this).parent().prev().prev().find(".k_dato").val(data[1]);
		$(this).parent().next().find(".k_adresse").val(data[3]);
		$(this).parent().next().next().find(".id").val(data[6]);
		$(this).parent().next().next().next().find(".fase").val(data[7]);
		$(this).parent().next().next().next().next().find(".sag_id").val(data[8]);
		$(this).parent().next().next().next().next().next().find(".tjek_id").val(data[9]);
	});
	
	$(".k_adresse").autocomplete("autocomplete.php?mode=k_adresse", {
			width: 475,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAdresse,
			formatResult: formatResult
	});
	$(".k_adresse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".k_af").val(data[3]);
		$(this).parent().prev().prev().find(".k_sagsnr").val(data[2]);
		$(this).parent().prev().prev().prev().find(".k_dato").val(data[1]);
		$(this).parent().next().find(".id").val(data[6]);
		$(this).parent().next().next().find(".fase").val(data[7]);
		$(this).parent().next().next().next().find(".sag_id").val(data[8]);
		$(this).parent().next().next().next().next().find(".tjek_id").val(data[9]);
	});
});

// function til pagination
$(document).ready(function(){
	$('#paging_container').pajinate({
		items_per_page : 50,
		item_container_id : '.paging_content',
		num_page_links_to_display : 10,
		abort_on_small_lists: true,
		nav_label_info : (window.sagerTekst || {}).visning || 'Viser {0}-{1} af {2}',
		nav_label_first : '<<',
		nav_label_last : '>>',
		nav_label_prev : '<',
		nav_label_next : '>'
	});
});	
