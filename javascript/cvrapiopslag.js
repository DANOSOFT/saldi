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
// 20260728 NTR CVR-nr. lookup now triggers on a trailing *, +, or /, or on 8 digits when "Auto-lookup CVR nr." is checked
// 20260730 Sawaneh Read current values from the visible field when a form also emits a hidden
//                  duplicate of the same name (sager/kunder.php), so overwrite detection works

function cvrField(name) {
	var visible = $("[name=" + name + "]").not("[type=hidden]");
	return visible.length ? visible.first() : $("[name=" + name + "]").first();
}

function cvrValue(name) {
	var el = cvrField(name);
	return el.length ? (el.val() || '').trim() : '';
}

$(document).keydown(function(e){
	// Tryk på F2 aktiverer rubrikken kundenr. eller CVR-nr., hvis kundenr. allerede er aktivt
	if(e.which == '113'){	// F2
		e.preventDefault();
		if(cvrField('ny_kontonr').is(':focus')) cvrField('cvrnr').select();
		else cvrField('ny_kontonr').select();
	}
});

function getExistingFormData() {
	return {
		cvrnr:       cvrValue('cvrnr'),
		firmanavn:   cvrValue('firmanavn'),
		addr1:       cvrValue('addr1'),
		addr2:       cvrValue('addr2'),
		postnr:      cvrValue('postnr'),
		bynavn:      cvrValue('bynavn'),
		tlf:         cvrValue('tlf'),
		email:       cvrValue('email'),
		fax:         cvrValue('fax')
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

function cvrapi(param, country, type, felt){
	// A page can point at a server proxy by setting cvrLookupProxy before this script is
	// loaded. cvrapi.dk answers 403 to the jsonp call because the browser cannot set the
	// required User-Agent, so the proxy calls cvrapi.dk from the server instead.
	var brugProxy = (typeof cvrLookupProxy !== 'undefined' && cvrLookupProxy);

	cvrStatus(felt, cvrTekst('soeger'), false);

	jQuery.ajax
	({
		type: "GET",
		dataType: brugProxy ? "json" : "jsonp",
		url: brugProxy
			? cvrLookupProxy+"?type="+encodeURIComponent(type)+"&param="+encodeURIComponent(param)+"&country="+encodeURIComponent(country)
			: "//cvrapi.dk/api?"+type+"="+param+"&country="+country,
		success: function (b)
		{
			if (!b || b.error) {
				cvrFejl(felt, b ? b.error : '');
				return;
			}
			cvrStatus(felt, '', false);

			var existing = getExistingFormData();
			var incoming = normaliseApiData(b);

			if (detectConflicts(existing, incoming)) {
				showConfirmOverlay(existing, incoming);
			} else {
				applyFormFields(incoming);
			}
		},
		error: function ()
		{
			cvrFejl(felt, '');
		}
	});
}

// The lookup used to be completely silent - neither "searching" nor an error was shown.
// The message is written next to the field rather than in a dialog, so auto lookup does
// not interrupt typing. Only on pages that set cvrLookupProxy - Finans keeps its previous
// behaviour unchanged.
// cvrapi.dk always answers in English, so the error code is looked up in cvrTekster, which
// the page fills using findtekst() in the user's language. Danish is used as the fallback.
var cvrFejlTekst = {
	fejl:           'CVR-opslaget kunne ikke gennemføres. Udfyld felterne manuelt.',
	QUOTA_EXCEEDED: 'Kvoten for CVR-opslag er opbrugt.',
	NOT_FOUND:      'CVR-nummeret blev ikke fundet.',
	INVALID_VAT:    'CVR-nummeret er ikke gyldigt.',
	soeger:         'Søger...'
};
function cvrTekst(noegle) {
	if (typeof cvrTekster !== 'undefined' && cvrTekster && cvrTekster[noegle]) return cvrTekster[noegle];
	return cvrFejlTekst[noegle] || '';
}

function cvrStatus(felt, tekst, fejl) {
	if (typeof cvrLookupProxy === 'undefined' || !cvrLookupProxy) return;
	if (!felt || !felt.length) return;
	var boks = felt.parent().find('.cvr-status');
	if (!boks.length) {
		boks = $('<div class="cvr-status" style="font-size:11px;line-height:14px;padding-top:2px;"></div>');
		felt.parent().append(boks);
	}
	boks.css('color', fejl ? '#c00000' : '#666666').text(tekst || '');
}

function cvrFejl(felt, kode) {
	var aarsag = cvrTekst(kode);
	cvrStatus(felt, aarsag ? aarsag : cvrTekst('fejl'), true);
}

var pattern = /^[\*\/\+]\d{8}[\*\/\+]$/;
var plainCvr = /^\d{8}$/;
var trailingSymbolCvr = /^(\d{8})[\*\/\+]$/;

// The lookup used to be sent on the keystroke itself, so typing on - a longer number, say -
// fired off several calls. It now waits until typing has been still for a moment, and
// tabbing or clicking out of the field looks up straight away.
var cvrPause = 400;
var cvrTimer = null;
var cvrSidste = null;

function cvrOpslag(felt, vaerdi, type){
	if (cvrTimer) { clearTimeout(cvrTimer); cvrTimer = null; }
	if (cvrSidste === type+vaerdi) return;
	cvrSidste = type+vaerdi;
	cvrapi(vaerdi, 'dk', type, felt);
}

// Auto lookup on 8 bare digits is only wanted in the fields the page names in
// cvrAutoFelter. Under sager the customer no. is often the customer's own phone or CVR
// number, and there 8 digits must not start a lookup by itself - it takes *, + or /.
// If the page names nothing, it applies to every field as before.
function cvrAutoTilladt(navn) {
	if (typeof cvrAutoFelter === 'undefined' || !cvrAutoFelter) return true;
	return $.inArray(navn, cvrAutoFelter) !== -1;
}

function cvrAutoOpslag(felt, vaerdi) {
	return plainCvr.test(vaerdi) && cvrAutoTilladt(felt.attr('name')) && $("[name=auto_lookup_cvr]").is(':checked');
}

function cvrKeyupOpslag(e){
	var felt = $(e.target);
	var vaerdi = (felt.val() || '').trim();
	var trailingMatch = trailingSymbolCvr.exec(vaerdi);

	if (cvrTimer) { clearTimeout(cvrTimer); cvrTimer = null; }

	if(trailingMatch){
		felt.val(trailingMatch[1]);
		cvrOpslag(felt, trailingMatch[1], 'vat');
	} else if(cvrAutoOpslag(felt, vaerdi)){
		cvrTimer = setTimeout(function(){ cvrOpslag(felt, vaerdi, 'vat'); }, cvrPause);
	} else {
		cvrSidste = null;
		cvrStatus(felt, '', false);
	}
}

function cvrBlurOpslag(e){
	var felt = $(e.target);
	var vaerdi = (felt.val() || '').trim();
	if(cvrAutoOpslag(felt, vaerdi)) cvrOpslag(felt, vaerdi, 'vat');
}

cvrField('ny_kontonr').keyup(cvrKeyupOpslag).blur(cvrBlurOpslag);
cvrField('cvrnr').keyup(cvrKeyupOpslag).blur(cvrBlurOpslag);

cvrField('tlf').keyup(function(e){
        var tlfnr = ($(e.target).val() || '').trim();
        if(pattern.test(tlfnr)){
                tlfnr = tlfnr.substr(1,8);
                cvrOpslag($(e.target), tlfnr, 'phone');
        }
});
