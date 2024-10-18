<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// ------------- debitor/pos_ordre_includes/divFuncs/finishedOrder.php ---------- lap 3.7.7----2019.07.09-------
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
// Copyright (c) 2004-2019 saldi.dk aps
// ----------------------------------------------------------------------
//
// LN 20190709 Make function that sets the "Hent bestilling" site for the take away

function showFinishedOrders()
{
	  print "<script type=\"text/javascript\" src=\"https://cdn.jsdelivr.net/jquery/latest/jquery.min.js\"></script>";
      print "<script type=\"text/javascript\" src=\"https://cdn.jsdelivr.net/momentjs/latest/moment.min.js\"></script>";
      print "<script type=\"text/javascript\" src=\"https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js\"></script>";
      print "<link rel=\"stylesheet\" type=\"text/css\" href=\"https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css\" />";
      $finishedOrder = getFinishedTakeAwayData();
	  print "<form name=pos_ordre action=\"pos_ordre.php?id=$id&getOrder=true\" method=\"post\" autocomplete=\"off\">\n";

    $today = date("Y-m-d");
    $yesterday = date_create($today)->modify("-1 day")->format("Y-m-d");

      print "<center><table id=\"getOrderTable\">";
      print "<div style=\"margin-left: -400px\">
                <input type=\"date\" class=\"datepickerStart\" value=\"$yesterday\" />
				- 
                <input type=\"date\" class=\"datepickerEnd\" value=\"$today\" />
                <input type='button' class='getDateButton' value=\"Ok\">
                <b style=\"font-size:40px;\"> Afsluttede bestillinger </b>
            </div>";
	
	print "<tbody style=\"margin-buttom: 20px;\">
		<tr>
			  <button style=\"margin-right: 10px;\" class=\"historyButton\" type=\"submit\" name=\"submit\" value=\"backToGetOrder\">
				Tilbage
			  </button>
			  <button style='margin-right: 10px;' class=\"historyButton detailbutton\" disabled type=\"submit\" name=\"submit\" value=\"showDetailedTakeAwayOrder\">
				Vis bestilling
			  </button>
			  <button class=\"historyButton detailbutton\" type=\"submit\" name=\"submit\" value=\"showDeletedTakeAwayOrders\">
				Slettede ordrer
			  </button>
		</tr>
	</tbody>";
	
      print "<tr>
                <td class=\"infoLine\">
                  <b class=\"headline\"> Ordre id </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Tidspunkt </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Navn </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Telefon </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Leveringsform </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Modtaget via </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Betalt </b>
                </td>
                <td class=\"infoLine\">
                      <b class=\"headline\"> Adresse </b>
                </td>
            </tr>";
      $x = 0;
    foreach ($finishedOrder as $key => $value) {
          $received = setReceived($value);

	print "<tbody id=\"tbodyData\">";
          print "<tr id=\"historyOrderRow$x\" class=\"historyOrderRow\" value=\"$value[ordrenr]\">
                    <td class=\"infoLine\">
                      <div class='historyInfo'>";
          print            $value['id'];
          print       "</div>
                    </td>
                  <td class=\"infoLine dateTime\">
                          <div class='historyInfo'>";
          print $value['ordredate'];
          print "</div><center>";
          print $value['tidspkt'];
          print "</center>
                    </td>
                    <td class=\"infoLine\">
                          <div class='historyInfo'>";
          print $value['lev_navn'];
          print "</div>
                    </td>
                    <td class=\"infoLine\">
                          <div class='historyInfo'>";
          print $value['kontakt_tlf'];
          print "</div>
                    </td>
                    <td class=\"infoLine\">
                          <div class='historyInfo'>";
          print $value['lev_addr1'];
          print "</div>
                    </td>
                    <td class=\"infoLine\">
                          <div class='historyInfo'>";
          print $received;
          print "</div>
                    </td>
                    <td class=\"infoLine\">
                          <div class='historyInfo'>";
          print $value['betalt'];
          print "</div>
                    </td>
                    <td class=\"infoLine\">
                          <div class='historyInfo'>";
          print $value['addr1'];
          print "</div> </td> </tr>";
          $x++;
        }
  print "</tbody> </table>";

		
   print "</center>";

    print "<input type=\"hidden\" name=\"idButton\" id=\"submitId\">";

	print "<script> 
		jQuery(document).ready(function() {
			$(\".getDateButton\").trigger('click');    
		});
		$('.getDateButton').click(function(){
			$('#getOrderTable #tbodyData td:nth-child(2) .historyInfo').each(function(index) {
				var rowDate = Date.parse($(this).html());				
				var startDate = Date.parse($(\".datepickerStart\").val());
				var endDate = Date.parse($(\".datepickerEnd\").val());

				if (rowDate > endDate || rowDate < startDate) {
					$(this).closest('tr').hide();
				} else {
					$(this).closest('tr').show();
				}
			});
		});
		
		  var rowId;
		  $(\".historyOrderRow\").click(function() {
			  if(typeof rowId == 'undefined') {
				  $(\".detailbutton\").removeAttr(\"disabled\");
			  } else {
  				  $(\"#\" + rowId).css('color', 'black'  );
				  $(\"#\" + rowId).css('background-color', '#999999');
			  }

			  rowId = $(this).attr('id');
			  var orderNr = $(this).attr('value');
			  $(\"#submitId\").val(orderNr);

			  $(this).css({
				'background-color' : '#404040',
				'color' : 'white'
			  });
		  });
		</script>";

    print "<style>
              .headline {
                  font-size: 20px;
              }
              .getDateButton {
                    border-radius: 20%;
                    width: 60px;
                    height: 50px;
                    padding: 0;
					margin-left: 10px;
					margin-bottom: -10px;
					margin-right: 100px;
              }
			  .datepickerStart, .datepickerEnd {
					padding: 5px;
					border-radius: 10px 10px 10px 10px;
			  }
			  .historyOrderRow {
				  background-color: #999999;
			  }
              .historyOrderRow:hover {
                background-color: #595959;
                color: white;
              }
              tr:nth-child(even) {
                background-color: #dddddd;
              }
              .historyInfo {
                text-align: center;
              }
              .historyButton {
                  width:200px;
                  border: none;
                  padding: 30px 10px;
                  font-size: 25px;
                  cursor: pointer;
                  border-radius: 6px;
                  border: 3px solid #8c8c8c;
				  margin-top: 20px;
				  margin-bottom: 20px;
              }
              .datepicker {
                 width:170px;
                 border: 1px solid #ccc;
                 padding:10px 20px;
                 margin-left: -200px;
                 margin-right: 100px;
                 border-radius: 5px 5px 5px 5px;
              }
              .infoLine {
                border: 1px solid #dddddd;
                padding: 8px;
                font-size: 16px;
              }
              #getOrderTable {
                 margin-top: 20px;
                 width: 80%;
                 border-collapse: collapse;
                 font-family: arial, sans-serif;
              }
          </style>";
          exit;
}

?>