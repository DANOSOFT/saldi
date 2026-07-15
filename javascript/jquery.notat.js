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
	function formatItemDato(row) {
		return row[0] + " - <b>Sagsnr:</b> <i>" + row[1] + "</i> - <b>Af:</b> <i>" + row[2] + "</i><br><b>Beskrivelse:</b> <i>" + row[3] + "</i>";
	}
	
	function formatItemSagsnr(row) {
		return row[0] + " - <b>Dato:</b> <i>" + row[1] + "</i> - <b>Af:</b> <i>" + row[2] + "</i><br><b>Beskrivelse:</b> <i>" + row[3] + "</i>";
	}
	
	function formatItemAf(row) {
		return row[0] + " - <b>Dato:</b> <i>" + row[1] + "</i> - <b>Sagsnr:</b> <i>" + row[2] + "</i><br><b>Beskrivelse:</b> <i>" + row[3] + "</i>";
	}
	
	function formatItemBeskrivelse(row) {
		return row[0] + "<br><b>Dato:</b> <i>" + row[1] + "</i> - <b>Sagsnr:</b> <i>" + row[2] + "</i><b>Af:</b> <i>" + row[3] + "</i>";
	}

	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".n_dato").autocomplete("autocomplete.php?mode=n_dato", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemDato,
		formatResult: formatResult
	});
	$(".n_dato").result(function(event, data, formatted) {
		$(this).parent().next().find(".n_sagsnr").val(data[1]);
		$(this).parent().next().next().find(".n_af").val(data[2]);
		$(this).parent().next().next().next().find(".n_beskrivelse").val(data[3]);
		$(this).parent().next().next().next().next().find(".id").val(data[4]);
	});
							
	$(".n_sagsnr").autocomplete("autocomplete.php?mode=n_sagsnr", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemSagsnr,
			formatResult: formatResult
	});
	$(".n_sagsnr").result(function(event, data, formatted) {
		$(this).parent().prev().find(".n_dato").val(data[1]);
		$(this).parent().next().find(".n_af").val(data[2]);
		$(this).parent().next().next().find(".n_beskrivelse").val(data[3]);
		$(this).parent().next().next().next().find(".id").val(data[4]);
	});
	
	$(".n_af").autocomplete("autocomplete.php?mode=n_af", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAf,
			formatResult: formatResult
	});
	$(".n_af").result(function(event, data, formatted) {
		$(this).parent().prev().find(".n_sagsnr").val(data[2]);
		$(this).parent().prev().prev().find(".n_dato").val(data[1]);
		$(this).parent().next().find(".n_beskrivelse").val(data[3]);
		$(this).parent().next().next().find(".id").val(data[4]);
	});
	
	$(".n_beskrivelse").autocomplete("autocomplete.php?mode=n_beskrivelse", {
			width: 475,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemBeskrivelse,
			formatResult: formatResult
	});
	$(".n_beskrivelse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".n_af").val(data[3]);
		$(this).parent().prev().prev().find(".n_sagsnr").val(data[2]);
		$(this).parent().prev().prev().prev().find(".n_dato").val(data[1]);
		$(this).parent().next().find(".id").val(data[4]);
	});
});

// Her er function til autocomplete i sager under 'find sag'
$(document).ready(function(){
	function formatItemNr(row) {
		return row[0] + " - <b>Kunde:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
	}
	
	function formatItemNavn(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
	}
	
	function formatItemAddr(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[2] + "</i> - <b>Kunde:</b> <i>" + row[1] + "</i>";
	}

	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".n_sagsagsnr").autocomplete("autocomplete.php?mode=n_sagsagsnr", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemNr,
		formatResult: formatResult
	});
	$(".n_sagsagsnr").result(function(event, data, formatted) {
		$(this).parent().next().find(".n_sagfirmanavn").val(data[1]);
		$(this).parent().next().next().find(".n_sagadresse").val(data[2]);
		$(this).parent().next().next().next().find(".id").val(data[3]);
		$(this).parent().next().next().next().next().find(".konto_id").val(data[4]);
	});
							
	$(".n_sagfirmanavn").autocomplete("autocomplete.php?mode=n_sagfirmanavn", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemNavn,
			formatResult: formatResult
	});
	$(".n_sagfirmanavn").result(function(event, data, formatted) {
		$(this).parent().prev().find(".n_sagsagsnr").val(data[1]);
		$(this).parent().next().find(".n_sagadresse").val(data[2]);
		$(this).parent().next().next().find(".id").val(data[3]);
		$(this).parent().next().next().next().find(".konto_id").val(data[4]);
	});
	
	$(".n_sagadresse").autocomplete("autocomplete.php?mode=n_sagadresse", {
			width: 475,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAddr,
			formatResult: formatResult
	});
	$(".n_sagadresse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".n_sagfirmanavn").val(data[1]);
		$(this).parent().prev().prev().find(".n_sagsagsnr").val(data[2]);
		$(this).parent().next().find(".id").val(data[3]);
		$(this).parent().next().next().find(".konto_id").val(data[4]);
	});
	
});

// function til pagination
$(document).ready(function(){
	$('#paging_container').pajinate({
		items_per_page : 50,
		item_container_id : '.paging_content',
		num_page_links_to_display : 10,
		abort_on_small_lists: true,
		nav_label_info : 'Viser {0}-{1} af {2}',
		nav_label_first : '<<',
		nav_label_last : '>>',
		nav_label_prev : '<',
		nav_label_next : '>'
	});
});	

// function til pagination i findsag
$(document).ready(function(){
	$('#paging_container').pajinate({
		items_per_page : 50,
		item_container_id : '.paging_content_findsag',
		num_page_links_to_display : 10,
		abort_on_small_lists: true,
		nav_label_info : 'Viser {0}-{1} af {2}',
		nav_label_first : '<<',
		nav_label_last : '>>',
		nav_label_prev : '<',
		nav_label_next : '>'
	});
});	

// Her er function til clear-button
        $(document).ready(function() {
            $('.clearable').clearable()
        });