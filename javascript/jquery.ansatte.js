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
	function formatItemNr(row) {
		return row[0] + " - <b>Navn:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
	}
	
	function formatItemNavn(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
	}
	
	function formatItemAddr(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[2] + "</i> - <b>Navn:</b> <i>" + row[1] + "</i>";
	}

	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".medarbejdernr2").autocomplete("autocomplete.php?mode=medarbejdernr2", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemNr,
		formatResult: formatResult
	});
	$(".medarbejdernr2").result(function(event, data, formatted) {
		$(this).parent().next().find(".medarbejdernavn2").val(data[1]);
		$(this).parent().next().next().find(".medarbejderadresse").val(data[2]);
		$(this).parent().next().next().next().find(".id").val(data[3]);
	});
							
	$(".medarbejdernavn2").autocomplete("autocomplete.php?mode=medarbejdernavn2", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemNavn,
			formatResult: formatResult
	});
	$(".medarbejdernavn2").result(function(event, data, formatted) {
		$(this).parent().prev().find(".medarbejdernr2").val(data[1]);
		$(this).parent().next().find(".medarbejderadresse").val(data[2]);
		$(this).parent().next().next().find(".id").val(data[3]);
	});
	
	$(".medarbejderadresse").autocomplete("autocomplete.php?mode=medarbejderadresse", {
			width: 475,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAddr,
			formatResult: formatResult
	});
	$(".medarbejderadresse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".medarbejdernavn2").val(data[1]);
		$(this).parent().prev().prev().find(".medarbejdernr2").val(data[2]);
		$(this).parent().next().find(".id").val(data[3]);
	});
});

// Her er function til highlight af inputfelter
$(document).ready(function()
{
	$('.contentB .leftTableCell, .contentB input, .contentB textarea, .contentB select').focus(function(){
			$(this).parents('.row2').addClass("over");
	}).blur(function(){
			$(this).parents('.row2').removeClass("over");
	});
	$('.contentA .left, .contentA input, .contentA textarea, .contentA select').focus(function(){
			$(this).parents('.row').addClass("over");
	}).blur(function(){
			$(this).parents('.row').removeClass("over");
	});
});

// function til pagination
$(document).ready(function(){
	$('#paging_container').pajinate({
		items_per_page : 25,
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