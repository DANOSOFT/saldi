$(document).ready(function () {
	$('input[type="text"],textarea,a[href]').keyup(function (e) {
		if (e.which === 39 && e.shiftKey) {
			$(this).closest('td').next().find('input[type="text"],textarea,a[href]').focus();
		} else if (e.which == 37 && e.shiftKey) {
			$(this).closest('td').prev().find('input[type="text"],textarea,a[href]').focus();
		} else if (e.which === 40) {
		var t = $(this).closest('tr').next().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"],textarea,a[href]');
		if (t.length == 0) {
//			t = $(document).find('table:eq(' + ($('table').index($(this).closest('table')) + 1) + ')').find('tbody tr td').parent().first().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"]:not([readonly]),textarea,a[href]');
		}
		t.focus();
		} else if (e.which === 38) {
				var t = $(this).closest('tr').prev().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"],textarea,a[href]');
			if (t.length == 0) {
//				t = $(document).find('table:eq(' + ($('table').index($(this).closest('table')) - 1) + ')').find('tbody tr td').parent().last().find('td:eq(' + $(this).closest('td').index() + ')').find('input[type="text"]:not([readonly]),textarea,a[href]');
			}
			t.focus();
		} else if (e.which === 27) {
			window.history.back();
		}
	});
});
   