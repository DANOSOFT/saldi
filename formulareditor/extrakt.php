<?php 
$SourceFile = "SaldiFormular-design.fodg";
if (file_exists($SourceFile)) {ScanXMLfile(); } else {echo "<b>FEJL: $SourceFile ikke fundet!</b>";}

function ScanXMLfile () {
  $doc = new DOMDocument();   $doc->load('SaldiFormular-design.fodg');
  foreach ($doc->getElementsByTagName('*') as $element) { 
    $LagNavn = $element->getAttribute('draw:layer');    $LokaltNavn = $element->localName;  // Ret "and" i linie 10 til "or" for at se styles m.v.
    if ((($LagNavn == "Saldi-Objekter") or ($element->prefix == 'style') or ($element->prefix == 'form'))    // Kun Lag:Saldi-objekter og Style-tabeller!    
    and (($LokaltNavn == 'custom-shape') OR ($LokaltNavn == 'frame') OR ($LokaltNavn == 'combobox') OR ($LokaltNavn == 'line'))) {// Kun "system-objekter"
      echo '<br>Element: <i>', $element->prefix, ':', $element->localName, '</i> <b>[', $element->nodeValue, "]</b> Attr: ";  
      MyEcho($element, 'draw:layer');                       //	Filtrering af output
      MyEcho($element, 'svg:width');                        //	Feltbredde
      MyEcho($element, 'svg:height');                       //	Felthøjde
      MyEcho($element, 'svg:x');                            //	'$xa' - generelt
      MyEcho($element, 'svg:y');                            //	'$ya' - generelt
      MyEcho($element, 'svg:x1');                           //	'$xa' - linier
      MyEcho($element, 'svg:y1');                           //	'$ya' - linier
      MyEcho($element, 'svg:x2');                           //	'$xb' - linier	(ell. tekstlængde)
      MyEcho($element, 'svg:y2');                           //	'$yb' - linier	(ell. teksthøjde)
      MyEcho($element, 'draw:style-name');                  //	For "opslag i style-tabel: gr##"	(grafisk-style)
      MyEcho($element, 'draw:text-style-name');             //	For "opslag i style-tabel: P##"		(paragraf-style)
      MyEcho($element, 'text:style-name');                  //	For "opslag i style-tabel: T##"		(textfont-style)	
      MyEcho($element, 'style:name');                       //	
      MyEcho($element, 'style:style');                      //	
      MyEcho($element, 'style:font-name');                  //	
      MyEcho($element, 'fo:font-size');                     //	'$yb' -  teksthøjde
      MyEcho($element, 'fo:text-align');                    //	?Justering V / C / H
      MyEcho($element, 'draw:fill-color');                  //	
      MyEcho($element, 'draw:stroke');                      //	
      MyEcho($element, 'draw:stroke-dash');                 //	
      MyEcho($element, 'draw:textarea-horizontal-align');		//	?Justering V / C / H
			// Pladsreservation for varelinier ?
    }
  }
  echo '<br>';
}

function MyEcho ($element, $Attr) 
	{if ($element->getAttribute($Attr) ) {echo " [", $Attr, ": <i><b>", $element->getAttribute($Attr), "</b></i>]";}}     
?>

<?php 
/********************************

 Saldi-felter:	  LO-felter:
	 ID								Vælges af DB?
	'$formular'				1..14	(fra form:combobox eller dias-navn?)
	'$art'						1 / 2 / 3 / 6 = f(Kilde-Objekt: 1=draw:line 2=draw:frame	draw:custom-shape		?	)
	'$beskrivelse'		$element->nodeValue
	'$xa'							svg:x, svg:x1
	'$ya'							svg:y, svg:y1
	'$xb'							svg:x2
	'$yb'							svg:y2
	'$justering'			H / C / V  = f(fo:text-align)
	'$str'						 = f(fo:font-size)
	'$color'					 = f(fo:color)
	'$font'						Times / Helvetica = f(style:font-name)
	'$fed'						on / off = f(fo:font-weight "normal"|"bold")
	'$kursiv'					on / off = f(fo:font-style="italic")
	'$side'						A / 1 / S / !S = f(stregtype: draw:stroke-dash)
	'$sprog'					Dansk (fra form:combobox)
	OBS:
	X mm fra venstre	X cm fra venstre
	Y mm fra bund			Y cm fra top !!!


  Basale Attr:  "style-name"  "text-style-name" "layer" "width" "height"  "x" "y"
  draw:line x og y erstattet af:  "x1"  "y1"  "x2"  "y2"
  
 <draw:custom-shape - Indeholder: Data-variabel
    draw:style-name="gr52" 
    draw:text-style-name="P9" 
    draw:layer="Saldi-Objekter" 
    svg:width="9.31cm" 
    svg:height="0.449cm" 
    svg:x="6.068cm" 
    svg:y="13.297cm"
 >      draw:stroke="dash" draw:stroke-dash=    (Ramme-stregtype:)
        Ultrafine_20_2_20_Dots_20_3_20_Dashes = 2 prikker 3 streger => "S"
        _33__20_Dashes_20_3_20_Dots_20__28_var_29_ draw:display-name="3 Dashes 3 Dots => "!S"
        _32__20_Dots_20_1_20_Dash draw:display-name="2 Dots 1 Dash" => "1"

 <draw:frame -  Indeholder: text-box
    draw:style-name="gr54" 
    draw:text-style-name="P32" 
    draw:layer="Saldi-Objekter" 
    svg:width="11.017cm" 
    svg:height="1.827cm" 
    svg:x="0.343cm" 
    svg:y="15.268cm"
  >
    
   <draw:control -  Indeholder: ComboBox mked Sprog og Formular
    draw:style-name="gr38" 
    draw:text-style-name="P29" 
    draw:layer="Saldi-Objekter" 
    svg:width="3.077cm" 
    svg:height="0.647cm" 
    svg:x="12.224cm" 
    svg:y="-0.699cm" 
    draw:control="control3" Indeholder yderligere!
  />
      
  <draw:line -  Indeholder: Linie
    draw:style-name="gr37" 
    draw:text-style-name="P11" 
    draw:layer="Saldi-Objekter" 
    svg:x1="0.362cm" 
    svg:y1="7.52cm" 
    svg:x2="20.721cm" 
    svg:y2="7.52cm"
  >

Optput med kun: [draw:layer: Saldi-Objekter]

Element: draw:custom-shape [ $eget_firmanavn * $egen_addr1 * $eget_postnr $eget_bynavn * Danmark ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 13.863cm] [svg:height: 0.5cm] [svg:x: 0.476cm] [svg:y: 0.997cm] [draw:style-name: gr1] [draw:text-style-name: P2]
Element: draw:custom-shape [ $eget_firmanavn ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.856cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 3.263cm] [draw:style-name: gr2] [draw:text-style-name: P2]
Element: draw:custom-shape [ CVR nr: $eget_cvrnr ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.876cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 6.387cm] [draw:style-name: gr3] [draw:text-style-name: P2]
Element: draw:custom-shape [ DK-$eget_postnr $eget_bynavn ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.877cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 4.828cm] [draw:style-name: gr4] [draw:text-style-name: P2]
Element: draw:custom-shape [ if($ordre_kontakt;)Att: $ordre_kontakt; ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.699cm] [svg:height: 0.5cm] [svg:x: 10.097cm] [svg:y: 5.325cm] [draw:style-name: gr5] [draw:text-style-name: P2]
Element: draw:custom-shape [ Tlf:. $egen_tlf ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.876cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 5.354cm] [draw:style-name: gr3] [draw:text-style-name: P2]
Element: draw:custom-shape [ Fax: $egen_fax ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.876cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 5.874cm] [draw:style-name: gr3] [draw:text-style-name: P2]
Element: draw:frame [ Faktura ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3cm] [svg:height: 0.888cm] [svg:x: 10.086cm] [svg:y: 2.424cm] [draw:style-name: gr6] [draw:text-style-name: P4]
Element: draw:custom-shape [ $egen_bank_reg $egen_bank_konto ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.914cm] [svg:height: 0.5cm] [svg:x: 1.177cm] [svg:y: 26.319cm] [draw:style-name: gr7] [draw:text-style-name: P2]
Element: draw:custom-shape [ $egen_bank_navn ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.877cm] [svg:height: 0.5cm] [svg:x: 1.177cm] [svg:y: 25.819cm] [draw:style-name: gr8] [draw:text-style-name: P2]
Element: draw:custom-shape [ Betales inden: $formular_forfaldsdato ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 8.533cm] [svg:height: 0.5cm] [svg:x: 11.919cm] [svg:y: 24.172cm] [draw:style-name: gr9] [draw:text-style-name: P2]
Element: draw:custom-shape [ $formular_moms ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.373cm] [svg:height: 0.5cm] [svg:x: 12.343cm] [svg:y: 21.788cm] [draw:style-name: gr10] [draw:text-style-name: P6]
Element: draw:custom-shape [ $formular_ialt ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 2.448cm] [svg:height: 0.5cm] [svg:x: 4.061cm] [svg:y: 21.788cm] [draw:style-name: gr11] [draw:text-style-name: P6]
Element: draw:custom-shape [ $ordre_postnr $ordre_bynavn ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.326cm] [svg:height: 0.501cm] [svg:x: 1.956cm] [svg:y: 4.026cm] [draw:style-name: gr12] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_firmanavn ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.326cm] [svg:height: 0.5cm] [svg:x: 1.956cm] [svg:y: 2.525cm] [draw:style-name: gr13] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_addr2; ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.326cm] [svg:height: 0.5cm] [svg:x: 1.956cm] [svg:y: 3.526cm] [draw:style-name: gr13] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_addr1 ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.326cm] [svg:height: 0.501cm] [svg:x: 1.956cm] [svg:y: 3.025cm] [draw:style-name: gr12] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_fakturanr ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.5cm] [svg:height: 0.5cm] [svg:x: 10.097cm] [svg:y: 3.325cm] [draw:style-name: gr14] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_kundeordnr; ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.5cm] [svg:height: 0.5cm] [svg:x: 10.097cm] [svg:y: 4.325cm] [draw:style-name: gr14] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_fakturadate ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.5cm] [svg:height: 0.5cm] [svg:x: 10.097cm] [svg:y: 3.825cm] [draw:style-name: gr14] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_ordrenr ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.5cm] [svg:height: 0.5cm] [svg:x: 10.097cm] [svg:y: 4.825cm] [draw:style-name: gr14] [draw:text-style-name: P2]
Element: draw:custom-shape [ beskrivelse ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 9.016cm] [svg:height: 0.939cm] [svg:x: 5.354cm] [svg:y: 8.301cm] [draw:style-name: gr15] [draw:text-style-name: P8]
Element: draw:custom-shape [ $egen_addr2 ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.856cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 4.288cm] [draw:style-name: gr2] [draw:text-style-name: P2]
Element: draw:custom-shape [ $egen_addr1 ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.856cm] [svg:height: 0.5cm] [svg:x: 14.544cm] [svg:y: 3.775cm] [draw:style-name: gr2] [draw:text-style-name: P2]
Element: draw:custom-shape [ ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 20.741cm] [svg:height: 12.305cm] [svg:x: 0.108cm] [svg:y: 8.2cm] [draw:style-name: gr19] [draw:text-style-name: P11]
Element: draw:custom-shape [ varenr ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.549cm] [svg:height: 0.5cm] [svg:x: 2.743cm] [svg:y: 8.301cm] [draw:style-name: gr20] [draw:text-style-name: P8]
Element: draw:custom-shape [ posnr ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 0.961cm] [svg:height: 0.496cm] [svg:x: 1.579cm] [svg:y: 8.301cm] [draw:style-name: gr21] [draw:text-style-name: P8]
Element: draw:custom-shape [ antal ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 0.913cm] [svg:height: 0.5cm] [svg:x: 4.335cm] [svg:y: 8.301cm] [draw:style-name: gr22] [draw:text-style-name: P13]
Element: draw:custom-shape [ LOGO ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.98cm] [svg:height: 2.408cm] [svg:x: 14.478cm] [svg:y: 0.644cm] [draw:style-name: gr23] [draw:text-style-name: P15]
Element: draw:custom-shape [ liniesum ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 2.16cm] [svg:height: 0.5cm] [svg:x: 18.06cm] [svg:y: 8.301cm] [draw:style-name: gr24] [draw:text-style-name: P13]
Element: draw:custom-shape [ pris ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.369cm] [svg:height: 0.5cm] [svg:x: 14.531cm] [svg:y: 8.301cm] [draw:style-name: gr25] [draw:text-style-name: P13]
Element: draw:frame [ Varenummer ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 2.073cm] [svg:height: 0.624cm] [svg:x: 2.499cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P16]
Element: draw:frame [ Pos. ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.117cm] [svg:height: 0.624cm] [svg:x: 1.5cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P16]
Element: draw:frame [ Antal ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.139cm] [svg:height: 0.624cm] [svg:x: 4.3cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P16]
Element: draw:frame [ Produkt beskrivelse ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 4.855cm] [svg:height: 0.624cm] [svg:x: 5.2cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P16]
Element: draw:frame [ à pris ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.472cm] [svg:height: 0.624cm] [svg:x: 14.4cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:frame [ Beløb u. moms ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 2.305cm] [svg:height: 0.624cm] [svg:x: 18.201cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:custom-shape [ Side $formular_side ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.85cm] [svg:height: 0.5cm] [svg:x: 16.841cm] [svg:y: 28.451cm] [draw:style-name: gr27] [draw:text-style-name: P2]
Element: draw:custom-shape [ $formular_sum ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 3.877cm] [svg:height: 0.5cm] [svg:x: 16.074cm] [svg:y: 21.788cm] [draw:style-name: gr8] [draw:text-style-name: P6]
Element: draw:custom-shape [ $formular_transportsum ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 4.604cm] [svg:height: 0.5cm] [svg:x: 15.901cm] [svg:y: 20.99cm] [draw:style-name: gr28] [draw:text-style-name: P2]
Element: draw:custom-shape [ $ordre_momssats;% moms ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 4.234cm] [svg:height: 0.5cm] [svg:x: 6.966cm] [svg:y: 21.788cm] [draw:style-name: gr29] [draw:text-style-name: P6]
Element: draw:custom-shape [ $ordre_betalingsbet $ordre_betalingsdage dage ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 17.661cm] [svg:height: 1.047cm] [svg:x: 1.177cm] [svg:y: 27.14cm] [draw:style-name: gr30] [draw:text-style-name: P2]
Element: draw:custom-shape [ Transport til side $formular_nextside ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 5.769cm] [svg:height: 0.5cm] [svg:x: 9.736cm] [svg:y: 20.99cm] [draw:style-name: gr31] [draw:text-style-name: P2]
Element: draw:custom-shape [ if($ordre_lev_addr1)Leveringsadresse: ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.362cm] [svg:height: 0.501cm] [svg:x: 1.956cm] [svg:y: 5.013cm] [draw:style-name: gr32] [draw:text-style-name: P19]
Element: draw:custom-shape [ rabat ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 0.939cm] [svg:height: 0.5cm] [svg:x: 15.944cm] [svg:y: 8.301cm] [draw:style-name: gr33] [draw:text-style-name: P13]
Element: draw:frame [ Rabat ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.233cm] [svg:height: 0.624cm] [svg:x: 15.752cm] [svg:y: 7.696cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:frame [ Nummer: ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.694cm] [svg:height: 0.624cm] [svg:x: 8.534cm] [svg:y: 3.282cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:frame [ Dato: ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.694cm] [svg:height: 0.624cm] [svg:x: 8.534cm] [svg:y: 3.782cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:custom-shape [ $ordre_lev_navn ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.362cm] [svg:height: 0.5cm] [svg:x: 1.956cm] [svg:y: 5.514cm] [draw:style-name: gr34] [draw:text-style-name: P20]
Element: draw:custom-shape [ $ordre_lev_addr1; ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.362cm] [svg:height: 0.501cm] [svg:x: 1.956cm] [svg:y: 6.014cm] [draw:style-name: gr32] [draw:text-style-name: P20]
Element: draw:custom-shape [ $ordre_lev_postnr $ordre_lev_bynavn; ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 6.362cm] [svg:height: 0.5cm] [svg:x: 1.956cm] [svg:y: 6.515cm] [draw:style-name: gr34] [draw:text-style-name: P20]
Element: draw:frame [ Deres Ref. ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 2.185cm] [svg:height: 0.624cm] [svg:x: 8.043cm] [svg:y: 4.282cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:frame [ Ordrenr: ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.694cm] [svg:height: 0.624cm] [svg:x: 8.534cm] [svg:y: 4.782cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:custom-shape [ Varemomssats ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.047cm] [svg:height: 0.896cm] [svg:x: 16.945cm] [svg:y: 8.301cm] [draw:style-name: gr35] [draw:text-style-name: P21]
Element: draw:frame [ Moms% ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 1.504cm] [svg:height: 0.624cm] [svg:x: 16.675cm] [svg:y: 7.71cm] [draw:style-name: gr26] [draw:text-style-name: P17]
Element: draw:custom-shape [ ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 20.426cm] [svg:height: 1.144cm] [svg:x: 0.265cm] [svg:y: 8.222cm] [draw:style-name: gr36] [draw:text-style-name: P8]
Element: draw:custom-shape [ ] Attr: [draw:layer: Saldi-Objekter] [svg:width: 20.426cm] [svg:height: 1.144cm] [svg:x: 0.265cm] [svg:y: 9.422cm] [draw:style-name: gr36] [draw:text-style-name: P8]
Element: draw:line [ ] Attr: [draw:layer: Saldi-Objekter] [svg:x1: 0.362cm] [svg:y1: 7.52cm] [svg:x2: 20.721cm] [svg:y2: 7.52cm] [draw:style-name: gr39] [draw:text-style-name: P9]
Element: draw:line [ ] Attr: [draw:layer: Saldi-Objekter] [svg:x1: 0.403cm] [svg:y1: 25.51cm] [svg:x2: 20.762cm] [svg:y2: 25.51cm] [draw:style-name: gr39] [draw:text-style-name: P9]

*/