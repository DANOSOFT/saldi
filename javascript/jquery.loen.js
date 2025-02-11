$(document).ready(function() {
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

// function som tilføjer en ny række i ansatteTable med autocomplete
(function($) {
	$(document).ready(function(){
		$(".addRow").btnAddRow(function(){
			function formatItem(row) {
				return row[0] + " : " + row[1];
			}
			function formatResult(row) {
				return row[0].replace(/(<.+?>)/gi, '');
			}
			$(".medarbejdernr").autocomplete("autocomplete.php?mode=medarbejdernr", {
				width: 300,
				selectFirst: true,
				matchContains: true,
				formatItem: formatItem,
				formatResult: formatResult
			});
			$(".medarbejdernr").result(function(event, data, formatted) {
				$(this).parent().next().find(">:input").val(data[1]);
			});
			$(".medarbejdernavn").autocomplete("autocomplete.php?mode=medarbejdernavn", {
				selectFirst: true,
				matchContains: true,
				formatResult: formatResult
			});
			$(".medarbejdernavn").result(function(event, data, formatted) {
				$(this).parent().prev().find(">:input").val(data[1]);
			});
		});
		$(".delRow").btnDelRow();		
	})
})(jQuery);

// Her er function til autocomplete
$(document).ready(function(){
	function formatItem(row) {
		return row[0] + " : " + row[1];
	}
	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
			
	$(".sagsnr").autocomplete("autocomplete.php?mode=sagsnr", {
		width: 300,
		selectFirst: true,
		mustMatch: true,
		matchContains: true,
		formatItem: formatItem,
		formatResult: formatResult
	});
	$(".sagsnr").result(function(event, data, formatted) {
		$(this).parent().next().find(">:input").val(data[1]);
	});
	
	$(".sagsaddr").autocomplete("autocomplete.php?mode=sagsaddr", {
		selectFirst: true,
		matchContains: true,
		formatResult: formatResult
	});
	$(".sagsaddr").result(function(event, data, formatted) {
		$(this).parent().prev().find(">:input").val(data[1]);
	});
	$(".medarbejdernr").autocomplete("autocomplete.php?mode=medarbejdernr", {
		width: 300,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItem,
		formatResult: formatResult
	});
	$(".medarbejdernr").result(function(event, data, formatted) {
		$(this).parent().next().find(">:input").val(data[1]);
	});
			
	$(".medarbejdernavn").autocomplete("autocomplete.php?mode=medarbejdernavn", {
		selectFirst: true,
		matchContains: true,
		formatResult: formatResult
	});
	$(".medarbejdernavn").result(function(event, data, formatted) {
		$(this).parent().prev().find(">:input").val(data[1]);
	});
});
		
// her er function som gemmer og viser
/*
function toggleAndChangeText() {
	$('#toggle').toggle();
	if ($('#toggle').css('display') == 'none') {
		$('#aTag').html('Vis akkordliste &#9658');
	}
	else {
		$('#aTag').html('Luk akkordliste &#9660');
	}
}
*/

$(document).ready(function()
{
    // Open / Close Panel According to Cookie //    
    if ($.cookie('panel') == 'open'){    
        //$('#toggle').slideDown('fast'); // Show on Page Load / Refresh with Animation
        $('#toggle').show(); // Show on Page Load / Refresh without Animation
				$('#aTag').html('Luk akkordliste &#9660');
    } else {
        //$('#toggle').slideUp('fast'); // Hide on Page Load / Refresh with Animation
        $('#toggle').hide(); // Hide on Page Load / Refresh without Animation
				$('#aTag').html('Vis akkordliste &#9658');
    }

    // Toggle Panel and Set Cookie //
    $('#aTag').click(function(){        
        $('#toggle').slideToggle('fast', function(){
            if ($(this).is(':hidden')) {
                $.cookie('panel', 'closed');
								$('#aTag').html('Vis akkordliste &#9658');
            } else {
                $.cookie('panel', 'open');
								$('#aTag').html('Luk akkordliste &#9660');
            }
        });
    });
});

// function til brug af piletaster i akkordliste + table
$(document).ready(function () {
		$('#toggle input[type="text"],input[type="checkbox"]').keyup(function (e) {
				if (e.which === 39) {
						$(this).closest('td').next().find('input[type="text"],input[type="checkbox"]').focus();
				} else if (e.which === 37) {
						$(this).closest('td').prev().find('input[type="text"],input[type="checkbox"]').focus();
				} else if (e.which === 40) {
						var t = $(this).closest('tr').next().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"],input[type="checkbox"]');
						if (t.length == 0) {
								t = $(document).find('table:eq(' + ($('table').index($(this).closest('table')) + 1) + ')').find('tbody tr td').parent().first().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"]:not([readonly]),input[type="checkbox"]');
						}
						t.focus();
				} else if (e.which === 38) {
						var t = $(this).closest('tr').prev().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"],input[type="checkbox"]');
						if (t.length == 0) {
								t = $(document).find('table:eq(' + ($('table').index($(this).closest('table')) - 1) + ')').find('tbody tr td').parent().last().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"]:not([readonly]),input[type="checkbox"]');
						}
						t.focus();
				}
		});
});

// function til valg af akkordliste
$(document).ready(function(akkordlistevalg) {
	$(".akkordlistevalg").click(function() {
		var id=$(this).val();
		var dataString = 'id='+ id;
		$.ajax ({
			type: "POST",
			url: "ajax_loen.php",
			data: dataString,
			cache: false,
			success: function(html) {
				$(".akkordlisteVis").html(html);
			} 
		});
	});
});

// function der automatisk tilføjer en ny række på andetTable
$("document").ready(function(){
	$(".andetTable").tableAutoAddRow({autoAddRow:true});
	$(".delRow2").btnDelRow();
});
/* fjernet 20142803
// Function som sortere i table 
	$("document").ready(function() {
	$(".akkordlisteSort tbody").tableDnD({onDragClass: "highlight"});
});
*/
// her er function til sortering i liste
/*
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
});*/
/*
$("#sort a").click(function(e) {
    var desc = $(this).hasClass("asc"),
        sort = this.hash.substr(1),
        list = $("#things");
    $(list.children().detach().find("span."+sort).get().sort(function(a, b) {
        var aProp = $.text([a]),
            bProp = $.text([b]);
        return (aProp > bProp ? 1 : aProp < bProp ? -1 : 0) * (desc ? -1 : 1);
    })).parent().appendTo(list);
    $(this).toggleClass("desc", desc)
           .toggleClass("asc", !desc)
           .siblings().removeClass("asc desc");
    e.preventDefault();
});
*/
/*
// function til sortering i table
 $(document).ready(function() // function til sortering i table
		{ 
				$("#dataTable").tablesorter({widthFixed: true})
				.tablesorterPager({container: $("#pager")}); 
		} 
); 
 */
// function til pagination
$(document).ready(function(){
		$('#paging_container').pajinate({
			items_per_page : 250,
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

// function til datepicker
$(document).ready(function() {
		$( "#datepicker" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true
		});
});

$(document).ready(function() {
	$("#datepicker01").datepicker({
		showWeek: true,
		//showOtherMonths: true,
		//selectOtherMonths: true,
		//showButtonPanel: true,
		//closeText: 'Slet',
		/*onSelect: function ( dateText, inst ) {
			$(this).val( dateText );
			inst.inline = true;
		},*/
		/*onClose: function(date,inst){
			inst.inline = false;
		},*/
		beforeShowDay: function(date) {
			var date1 = $.datepicker.parseDate($.datepicker._defaults.dateFormat, $("#datepickerFra").val());
			var date2 = $.datepicker.parseDate($.datepicker._defaults.dateFormat, $("#datepickerTil").val());
			return [true, date1 && ((date.getTime() == date1.getTime()) || (date2 && date >= date1 && date <= date2)) ? "dp-highlight" : ""];
		},
		onSelect: function(dateText, inst) {
			//$(this).text(dateText);
      //setTimeout(function () { $('#datepicker01').datepicker("show");}, 0);
      inst.inline = true;
			var date1 = $.datepicker.parseDate($.datepicker._defaults.dateFormat, $("#datepickerFra").val());
			var date2 = $.datepicker.parseDate($.datepicker._defaults.dateFormat, $("#datepickerTil").val());
			if (!date1 || date2) {
				$("#datepickerFra").val(dateText);
				$("#datepickerTil").val("");
				//$(this).datepicker("option", "minDate", dateText);
			} else {
				$("#datepickerTil").val(dateText);
				//$(this).datepicker("option", "minDate", null);
			}
		},
		onClose: function(date,inst){
			inst.inline = false;
			/*var event = arguments.callee.caller.caller.arguments[0];
			if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
					$(this).val('');
			}*/
		}
	});
});
/*
$(document).ready(function() {
		$( "#datepicker01" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true
		});
}); 
*/
$(document).ready(function() {
		$( "#datepicker02" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true,
				showButtonPanel: true,
				closeText: 'Slet',
				onClose: function () {
						var event = arguments.callee.caller.caller.arguments[0];
						if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
								$(this).val('');
						}
				}
		});
}); 

$(document).ready(function() {
		$( "#datepicker03" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true,
				showButtonPanel: true,
				closeText: 'Slet',
				onClose: function () {
						var event = arguments.callee.caller.caller.arguments[0];
						if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
								$(this).val('');
						}
				}
		});
}); 

$(document).ready(function() {
		$( "#datepicker04" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true,
				showButtonPanel: true,
				closeText: 'Slet',
				onClose: function () {
						var event = arguments.callee.caller.caller.arguments[0];
						if ($(event.delegateTarget).hasClass('ui-datepicker-close')) {
								$(this).val('');
						}
				}
		});
});

// function til valg af ferie med datepicker
$(document).ready(function() {
		$( "#feriefra" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true,
				onSelect: function(selected) {
          $("#ferietil").datepicker("option","minDate", selected)
        }
		});
		$( "#ferietil" ).datepicker({
				showWeek: true,
				showOtherMonths: true,
				selectOtherMonths: true,
				onSelect: function(selected) {
          $("#feriefra").datepicker("option","maxDate", selected)
        }
		});
});

// Function som fjerner link til akkordliste i leftmenu
$(document).ready(function() {
		var loendate = $("#loendateVis").val();
		if (loendate != 'on') {
			$('#akkordlisteLink').remove();
		}
});

// Funktion som fjerner link til sag, opgave og akkordliste i leftmenu
$(document).ready(function() {
		var sagnr = $("#sagnrVis").val();
		if (sagnr != 'on') {
			$('#sagidLink').remove();
			$('#opgaveLink').remove();
			$('#akkordlisteLink').remove();
		}
});
