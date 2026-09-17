
// Her er function til highlight af inputfelter
 $(document).ready(function()
	{
			$('.contentB .left, .contentB input, .contentB textarea, .contentB select').focus(function(){
					$(this).parents('.contentB').addClass("over");
			}).blur(function(){
					$(this).parents('.contentB').removeClass("over");
			});
			$('.contentA .left, .contentA input, .contentA textarea, .contentA select').focus(function(){
					$(this).parents('.row').addClass("over");
			}).blur(function(){
					$(this).parents('.row').removeClass("over");
			});
	});
 // Her er function til autocomplete
	$(document).ready(function(){
			function formatItemNr(row) {
				return row[0] + " - <b>Firma:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
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
			
			$(".kontonr").autocomplete("autocomplete.php?mode=kontonr", {
				width: 500,
				selectFirst: true,
				matchContains: true,
				formatItem: formatItemNr,
				formatResult: formatResult
			});
			$(".kontonr").result(function(event, data, formatted) {
				$(this).parent().next().find(".firmanavn").val(data[1]);
				$(this).parent().next().next().find(".firmaadresse").val(data[2]);
				$(this).parent().next().next().next().find(".id").val(data[3]);
			});
									
			$(".firmanavn").autocomplete("autocomplete.php?mode=firmanavn", {
					width: 500,
					selectFirst: true,
					matchContains: true,
					formatItem: formatItemNavn,
					formatResult: formatResult
			});
			$(".firmanavn").result(function(event, data, formatted) {
				$(this).parent().prev().find(".kontonr").val(data[1]);
				$(this).parent().next().find(".firmaadresse").val(data[2]);
				$(this).parent().next().next().find(".id").val(data[3]);
			});
			
			$(".firmaadresse").autocomplete("autocomplete.php?mode=firmaadresse", {
					width: 475,
					selectFirst: true,
					matchContains: true,
					formatItem: formatItemAddr,
					formatResult: formatResult
			});
			$(".firmaadresse").result(function(event, data, formatted) {
				$(this).parent().prev().find(".firmanavn").val(data[1]);
				$(this).parent().prev().prev().find(".kontonr").val(data[2]);
				$(this).parent().next().find(".id").val(data[3]);
			});
		});
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

	// function til pagination sagliste
$(document).ready(function(){
	$('#paging_container_sager').pajinate({
		items_per_page : 50,
		item_container_id : '.paging_content_sager',
		num_page_links_to_display : 10,
		abort_on_small_lists: true,
		nav_label_info : 'Viser {0}-{1} af {2}',
		nav_label_first : '<<',
		nav_label_last : '>>',
		nav_label_prev : '<',
		nav_label_next : '>'
	});
});	