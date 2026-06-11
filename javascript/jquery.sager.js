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
// Her er function til autocomplete i sager på forsiden
$(document).ready(function(){
	function formatItemNr(row) {
		return row[0] + " - <b>Kunde:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i><br><b>Status:</b> <i>" + row[4] + "</i>";
	}
	
	function formatItemNavn(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i><br><b>Status:</b> <i>" + row[4] + "</i>";
	}
	
	function formatItemAddr(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[2] + "</i> - <b>Kunde:</b> <i>" + row[1] + "</i><br><b>Status:</b> <i>" + row[4] + "</i>";
	}

	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".sagsagsnr").autocomplete("autocomplete.php?mode=sagsagsnr", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemNr,
		formatResult: formatResult
	});
	$(".sagsagsnr").result(function(event, data, formatted) {
		$(this).parent().next().find(".sagfirmanavn").val(data[1]);
		$(this).parent().next().next().find(".sagadresse").val(data[2]);
		$(this).parent().next().next().next().find(".id").val(data[3]);
	});
							
	$(".sagfirmanavn").autocomplete("autocomplete.php?mode=sagfirmanavn", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemNavn,
			formatResult: formatResult
	});
	$(".sagfirmanavn").result(function(event, data, formatted) {
		$(this).parent().prev().find(".sagsagsnr").val(data[1]);
		$(this).parent().next().find(".sagadresse").val(data[2]);
		$(this).parent().next().next().find(".id").val(data[3]);
	});
	
	$(".sagadresse").autocomplete("autocomplete.php?mode=sagadresse", {
			width: 475,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAddr,
			formatResult: formatResult
	});
	$(".sagadresse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".sagfirmanavn").val(data[1]);
		$(this).parent().prev().prev().find(".sagsagsnr").val(data[2]);
		$(this).parent().next().find(".id").val(data[3]);
	});
	
});

// Her er function til autocomplete til kopi_ordre
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
	
	$(".ordre_kopi_sagsnr").autocomplete("autocomplete.php?mode=ordre_kopi_sagsnr", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemNr,
		formatResult: formatResult
	});
	$(".ordre_kopi_sagsnr").result(function(event, data, formatted) {
		$(this).parent().next().find(".ordre_kopi_firmanavn").val(data[1]);
		$(this).parent().next().next().find(".ordre_kopi_adresse").val(data[2]);
		$(this).parent().next().next().next().find(".id").val(data[3]);
		$(this).parent().next().next().next().next().find(".konto_id").val(data[4]);
	});
							
	$(".ordre_kopi_firmanavn").autocomplete("autocomplete.php?mode=ordre_kopi_firmanavn", {
			width: 500,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemNavn,
			formatResult: formatResult
	});
	$(".ordre_kopi_firmanavn").result(function(event, data, formatted) {
		$(this).parent().prev().find(".ordre_kopi_sagsnr").val(data[1]);
		$(this).parent().next().find(".ordre_kopi_adresse").val(data[2]);
		$(this).parent().next().next().find(".id").val(data[3]);
		$(this).parent().next().next().next().find(".konto_id").val(data[4]);
	});
	
	$(".ordre_kopi_adresse").autocomplete("autocomplete.php?mode=ordre_kopi_adresse", {
			width: 475,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemAddr,
			formatResult: formatResult
	});
	$(".ordre_kopi_adresse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".ordre_kopi_firmanavn").val(data[1]);
		$(this).parent().prev().prev().find(".ordre_kopi_sagsnr").val(data[2]);
		$(this).parent().next().find(".id").val(data[3]);
		$(this).parent().next().next().find(".konto_id").val(data[4]);
	});
	
});

// Her er function til autocomplete i kunder under 'opret sag'
$(document).ready(function(){
	function formatItemOpretNr(row) {
		return row[0] + " - <b>Firma:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
	}
	
	function formatItemOpretNavn(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[1] + "</i> - <b>Adresse:</b> <i>" + row[2] + "</i>";
	}
	
	function formatItemOpretAddr(row) {
		return row[0] + " - <b>Nr:</b> <i>" + row[2] + "</i> - <b>Navn:</b> <i>" + row[1] + "</i>";
	}

	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".kontonr").autocomplete("autocomplete.php?mode=kontonr", {
		width: 500,
		selectFirst: true,
		matchContains: true,
		formatItem: formatItemOpretNr,
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
			formatItem: formatItemOpretNavn,
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
			formatItem: formatItemOpretAddr,
			formatResult: formatResult
	});
	$(".firmaadresse").result(function(event, data, formatted) {
		$(this).parent().prev().find(".firmanavn").val(data[1]);
		$(this).parent().prev().prev().find(".kontonr").val(data[2]);
		$(this).parent().next().find(".id").val(data[3]);
	});
});


// Her er function til autocomplete i kontrol_sager.php til sjak
$(document).ready(function(){
	function formatItemSjak(row) {
		return "(" + row[0] + ") " + row[1];
	}
	
	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".sjak").autocomplete("autocomplete.php?mode=sjak", {
		width: 250,
		selectFirst: true,
		multiple: true,
		mustMatch: true,
		//autoFill: true,
		matchContains: true,
		formatItem: formatItemSjak,
		formatResult: formatResult
	});
	$(".sjak").result(function(event, data, formatted) {
		//$(this).parent().next().find(".sjakid").val(data[2]);
		
		var hidden = $(this).parent().next().find(".sjakid");
		hidden.val( (hidden.val() ? hidden.val() + ";" : hidden.val()) + data[2]);
		
		var hidden2 = $(this).parent().next().next().find(".sjakid");
		hidden2.val( (hidden2.val() ? hidden2.val() + ";" : hidden2.val()) + data[2]);
	});
	
});


// Her er function til autocomplete i medarbejdermappe.php til valg af medarbejder
$(document).ready(function(){
	function formatItemMedarbejder(row) {
		return "(" + row[0] + ") " + row[1];
	}
	
	function formatResult(row) {
		return "(" + row[0] + ") " + row[1].replace(/(<.+?>)/gi, '');
	}
	
	$(".mm_medarbejdernavn").autocomplete("autocomplete.php?mode=mm_medarbejdernavn", {
			width: 400,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemMedarbejder,
			formatResult: formatResult
	});
	$(".mm_medarbejdernavn").result(function(event, data, formatted) {
		$(this).parent().next().find(".mm_medarbejdernavn").val(data[1]);
		$(this).parent().next().find(".ans_id").val(data[2]);
	});
});

// Her er function til autocomplete i sager.php til valg af kunde i avanceret søg
$(document).ready(function(){
	function formatItemKunde(row) {
		return row[0];
	}
	
	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".ss_sagfirmanavn").autocomplete("autocomplete.php?mode=ss_sagfirmanavn", {
			width: 400,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemKunde,
			formatResult: formatResult
	});
	$(".ss_sagfirmanavn").result(function(event, data, formatted) {
		$(this).parent().next().find(".ss_sagfirmanavn").val(data[1]);
	});
});

// Her er function til autocomplete i sager.php til valg af postnr i avanceret søg
$(document).ready(function(){
	function formatItemPostnr(row) {
		return row[0];
	}
	
	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".ss_sagpostnr").autocomplete("autocomplete.php?mode=ss_sagpostnr", {
			width: 80,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemPostnr,
			formatResult: formatResult
	});
	$(".ss_sagpostnr").result(function(event, data, formatted) {
		$(this).parent().next().find(".ss_sagpostnr").val(data[1]);
	});
});

// Her er function til autocomplete i sager.php til valg af postnr i avanceret søg
$(document).ready(function(){
	function formatItemBy(row) {
		return row[0];
	}
	
	function formatResult(row) {
		return row[0].replace(/(<.+?>)/gi, '');
	}
	
	$(".ss_sagby").autocomplete("autocomplete.php?mode=ss_sagby", {
			width: 250,
			selectFirst: true,
			matchContains: true,
			formatItem: formatItemBy,
			formatResult: formatResult
	});
	$(".ss_sagby").result(function(event, data, formatted) {
		$(this).parent().next().find(".ss_sagby").val(data[1]);
	});
});

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
	$('.contentAB .left, .contentAB input, .contentAB textarea, .contentAB select').focus(function(){
			$(this).parents('.row').addClass("over");
	}).blur(function(){
			$(this).parents('.row').removeClass("over");
	});
	$('.contentBA .left, .contentBA input, .contentBA textarea, .contentBA select').focus(function(){
			$(this).parents('.row').addClass("over");
	}).blur(function(){
			$(this).parents('.row').removeClass("over");
	});
	$('.contentAA .left, .contentAA input, .contentAA textarea, .contentAA select').focus(function(){
			$(this).parents('.row').addClass("over");
	}).blur(function(){
			$(this).parents('.row').removeClass("over");
	});
});


/*
function updatestatus() {
	var dataString = 'status='+ status + '&sagid=' + sagid;
  //alert (dataString);return false;
  $.ajax({
    type: "POST",
    url: "ajax_statusupdate.php",
    data: dataString,
    success: function(response)
       {
         alert("Record successfully updated");
       }
  });
  return false;
}
*//*
$(document).ready(function () {
	$(".update_form").each(function() {
	
	
		//var dataString = 'status='+ status + '&sagid=' + sagid;
		//alert (dataString);return false;
		alert("Record successfully updated");return false;
	
		$.ajax({
			type: "POST",
			url: "ajax_statusupdate.php",
			data: $(this).parent().serialize(),
			success: function(response)
				{
					alert("Record successfully updated");
				}
		});
		return false;
		
	});
});*/
/*
// function til pagination
$(document).ready(function(){
	$('#paging_container').pajinate({
		items_per_page : 50,
		item_container_id : '.paging_content',
		num_page_links_to_display : 10,
		//abort_on_small_lists: true,
		nav_label_info : 'Viser {0}-{1} af {2}',
		nav_label_first : '<<',
		nav_label_last : '>>',
		nav_label_prev : '<',
		nav_label_next : '>'
	});
});	*/
/*
// Function som sortere i table
	$("document").ready(function() {
		$(".ordretekstSort .ordretekstListe").tableDnD({
			onDragClass: "highlight",
			onDrop: function(table, row) {
			var orders = $.tableDnD.serialize();
			$.post('../debitor/sort.php', { orders : orders });
		},
		dragHandle: ".dragHandle"
	});
});
$("document").ready(function() {
	$(".ordretekstListe tr").hover(function() {
          $(this.cells[0]).addClass('showDragHandle');
    }, function() {
          $(this.cells[0]).removeClass('showDragHandle');
    });
});
*/
/*
// function til datepicker 
$(document).ready(function() {
	$( "#datepicker" ).datepicker({
			showOtherMonths: true,
			selectOtherMonths: true           
	});
});*/
/*
$(function(){
	var pickerOpts = {
		dateFormat:"@"
	};	
	$("#datepickerUnix").datepicker(pickerOpts) / 1000;
});
*/
/*
$(document).ready(function() {
	$( "#datepickerUnix" ).datepicker({
			showOtherMonths: true,
			selectOtherMonths: true,
			//dateFormat: '@' 
	});
	$("#datepickerUnix").datepicker.formatDate("@", $(this).datepicker("getDate") / 1000);
	//$("#datepickerUnix").datepicker("setDate", new Date);
});
*/