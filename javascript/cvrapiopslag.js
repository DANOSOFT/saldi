// --- javascript/cvrapiopslag.js --- patch 5.0.0 --- 2026-07-06 ---
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
//
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med DANOSOFT ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2004-2026 Danosoft.ApS
// ----------------------------------------------------------------------
// 2015.01.23 Hente virksomhedsdata fra CVR med CVRapi - tak Niels Rune https://github.com/nielsrune
// 20260706 MJ Add plain 8-digit trigger, confirmation overlay with type="button" to prevent accidental form submission

$(document).keydown(function(e){
	// Tryk på F2 aktiverer rubrikken kundenr. eller CVR-nr., hvis kundenr. allerede er aktivt
	if(e.which == '113'){	// F2
		e.preventDefault();
		if($("[name=ny_kontonr]").is(':focus')) $("[name=cvrnr]").select();
		else $("[name=ny_kontonr]").select();
	}
});

function getExistingFormData() {
	return {
		cvrnr:       ($("[name=cvrnr]").val()       || '').trim(),
		firmanavn:   ($("[name=firmanavn]").val()   || '').trim(),
		addr1:       ($("[name=addr1]").val()       || '').trim(),
		addr2:       ($("[name=addr2]").val()       || '').trim(),
		postnr:      ($("[name=postnr]").val()      || '').trim(),
		bynavn:      ($("[name=bynavn]").val()      || '').trim(),
		tlf:         ($("[name=tlf]").val()         || '').trim(),
		email:       ($("[name=email]").val()       || '').trim(),
		fax:         ($("[name=fax]").val()         || '').trim()
	};
}

function normaliseApiData(b) {
	var data = {};
	if (b.hasOwnProperty("vat"))     data.cvrnr     = String(b.vat).trim();
	if (b.hasOwnProperty("name"))    data.firmanavn  = String(b.name).trim();
	if (b.hasOwnProperty("address")) {
		if (b.hasOwnProperty("addressco") && b.addressco != null) {
			data.addr1 = "c/o " + b.addressco;
			data.addr2 = b.address;
		} else {
			data.addr1 = b.address;
			data.addr2 = '';
		}
	}
	if (b.hasOwnProperty("zipcode")) data.postnr    = String(b.zipcode).trim();
	if (b.hasOwnProperty("city"))    data.bynavn    = String(b.city).trim();
	if (b.hasOwnProperty("phone"))   data.tlf       = String(b.phone).trim();
	if (b.hasOwnProperty("email"))   data.email     = String(b.email).trim();
	if (b.hasOwnProperty("fax"))     data.fax       = String(b.fax).trim();
	return data;
}

function detectConflicts(existing, incoming) {
	var conflicts = false;
	for (var key in incoming) {
		if (!incoming.hasOwnProperty(key)) continue;
		var cur = (existing[key] || '').trim();
		var nw  = (incoming[key] || '').trim();
		if (cur !== '' && nw !== '' && cur !== nw) {
			conflicts = true;
			break;
		}
	}
	return conflicts;
}

function applyFormFields(data) {
	for (var key in data) {
		if (!data.hasOwnProperty(key)) continue;
		var el = $("[name=" + key + "]");
		if (el.length) el.val(data[key]);
	}
}

function showConfirmOverlay(existingData, incomingData) {
	$('#cvr-overlay').remove();

	var overlay = $(
		'<div id="cvr-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;' +
		'background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;">' +
		'<div style="background:#fff;padding:24px;border-radius:6px;max-width:420px;width:90%;box-shadow:0 4px 16px rgba(0,0,0,0.3);">' +
		'<p style="margin:0 0 16px;font-size:15px;">CVR-opslag vil overskrive eksisterende felter. Vil du opdatere?</p>' +
		'<div style="display:flex;gap:12px;justify-content:flex-end;">' +
		'<button type="button" id="cvr-btn-no"  style="padding:8px 16px;">Nej, behold nuværende</button>' +
		'<button type="button" id="cvr-btn-yes" style="padding:8px 16px;">Ja, opdater</button>' +
		'</div></div></div>'
	);

	$('body').append(overlay);

	$('#cvr-btn-yes').on('click', function() {
		applyFormFields(incomingData);
		$('#cvr-overlay').remove();
	});
	$('#cvr-btn-no').on('click', function() {
		$('#cvr-overlay').remove();
	});
}

function cvrapi(param, country, type){
	jQuery.ajax
	({
		type: "GET",
		dataType: "jsonp",
		url: "//cvrapi.dk/api?"+type+"="+param+"&country="+country,
		success: function (b)
		{
			var existing = getExistingFormData();
			var incoming = normaliseApiData(b);

			if (detectConflicts(existing, incoming)) {
				showConfirmOverlay(existing, incoming);
			} else {
				applyFormFields(incoming);
			}
		}
	});
}

var pattern = /^[\*\/\+]\d{8}[\*\/\+]$/;
var plainCvr = /^\d{8}$/;

$("[name=ny_kontonr]").keyup(function(e){
        var ny_kontonr = $("[name=ny_kontonr]").val();
        if(pattern.test(ny_kontonr)){
		ny_kontonr = $("[name=ny_kontonr]").val().substr(1,8);
		$("[name=ny_kontonr]").val(ny_kontonr);
                cvrapi(ny_kontonr, 'dk', 'vat');
        }
});

$("[name=cvrnr]").keyup(function(e){
	var cvrnr = $("[name=cvrnr]").val();
	if(pattern.test(cvrnr)){
		cvrnr = cvrnr.substr(1,8);
		cvrapi(cvrnr, 'dk', 'vat');
	} else if(e.which == 13 && plainCvr.test(cvrnr)){
		cvrapi(cvrnr, 'dk', 'vat');
	}
});

$("[name=tlf]").keyup(function(e){
        var tlfnr = $("[name=tlf]").val();
        if(pattern.test(tlfnr)){
                tlfnr = tlfnr.substr(1,8);
                cvrapi(tlfnr, 'dk', 'phone');
        }
});
