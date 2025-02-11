// ----------javascript/cvrapiopslag.php------------------------------lap 3.5.0---2015.01.23---
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
// Copyright (c) 2004-2015 DANOSOFT ApS
// ----------------------------------------------------------------------
// 2015.01.23 Hente virksomhedsdata fra CVR med CVRapi - tak Niels Rune https://github.com/nielsrune

$(document).keydown(function(e){
	// Tryk på F2 aktiverer rubrikken kundenr. eller CVR-nr., hvis kundenr. allerede er aktivt
	if(e.which == '113'){	// F2
		e.preventDefault();
		if($("[name=ny_kontonr]").is(':focus')) $("[name=cvrnr]").select();
		else $("[name=ny_kontonr]").select();
	}
});

function cvrapi(param, country, type){
	jQuery.ajax
	({
		type: "GET",
		dataType: "jsonp",
		url: "//cvrapi.dk/api?"+type+"="+param+"&country="+country,
		success: function (b)
		{
			if(b.hasOwnProperty("vat")) $("[name=cvrnr]").val(b.vat);
			if(b.hasOwnProperty("name")) $("[name=firmanavn]").val(b.name);
			if(b.hasOwnProperty("address")){
				if(b.hasOwnProperty("addressco") && b.addressco != null){
					$("[name=addr1]").val("c/o " + b.addressco);
					$("[name=addr2]").val(b.address);
				} else {
					$("[name=addr1]").val(b.address);
					$("[name=addr2]").val(null);
				}
			}
			if(b.hasOwnProperty("zipcode")) $("[name=postnr]").val(b.zipcode);
			if(b.hasOwnProperty("city")) $("[name=bynavn]").val(b.city);
			if(b.hasOwnProperty("phone")) $("[name=tlf]").val(b.phone);
			if(b.hasOwnProperty("email")) $("[name=email]").val(b.email);
			if(b.hasOwnProperty("fax")) $("[name=fax]").val(b.fax);
		}
	});
}

var pattern = /^[\*\/\+]\d{8}[\*\/\+]$/;

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
	}
});

$("[name=tlf]").keyup(function(e){
        var tlfnr = $("[name=tlf]").val();
        if(pattern.test(tlfnr)){
                tlfnr = tlfnr.substr(1,8);
                cvrapi(tlfnr, 'dk', 'phone');
        }
});
