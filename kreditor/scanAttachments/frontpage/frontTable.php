<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- kreditor/scanAttachments/frontpage/frontTable.php --- lap 4.0.4 --- 2021.11.25 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
//
// Copyright (c) 2021-2021 saldi.dk aps
// ----------------------------------------------------------------------

$id=array();

$newItemNo = if_isset($_POST['newItemNo']);
$updateLineId = if_isset($_POST['lineId']);

$showLines=if_isset($_GET['showLines']);
$move2order=if_isset($_GET['move2order']);
$deleteMe=if_isset($_GET['deleteMe']);

if ($newItemNo && $updateLineId) {
	$qtxt = "select id,varenr from varer where lower(varenr) = '". strtolower(db_escape_string($newItemNo)) ."'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "update ordrelinjer set varenr = '". db_escape_string($r['varenr']) ."', vare_id = '$r[id]' where id = '$updateLineId'";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
#	} elseif ($newDescription = $_POST['description']) {
#		$newPrice = $_POST['price'];
#		$qtxt = "insert into varer (varenr,beskrivelse,salgspris) values ";
#		$qtxt.= "('". db_escape_string($newItemNo) ."','". db_escape_string($newDescription) ."','$newPrice')";
#		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
#		$qtxt = "select id,varenr from varer where varenr = '". db_escape_string($newItemNo) ."'";
#		if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
#			$qtxt = "update ordrelinjer set varenr = '". db_escape_string($r['varenr']) ."', vare_id = '$r[id]' where id = '$updateLineId'";
#			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
#			if ($_POST['accountId'] && $_POST['suppItemNo']) {
#				$qtxt = "insert into vare_lev (posnr,lev_id,vare_id,lev_varenr,kostpris) values ";
#				$qtxt.= "'10','". $_POST['accountId'] ."','$r[id]','". db_escape_string($_POST['suppItemNo']) ."','$newPrice')";
#			}
#		}
	} else alert("varenr: $newItemNo eksisterer ikke i varetabel");
} elseif (isset($_POST['varenr_ny']) && $_POST['varenr_ny'] && $showLines) {
	$qtxt = "select * from varer where lower(varenr) = '".db_escape_string(($_POST['varenr_ny']))."'";
	if ($r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
		$qtxt = "insert into ordrelinjer (posnr,ordre_id,vare_id,varenr,antal,beskrivelse,pris) values ";
		$qtxt.= "('". db_escape_string($_POST['posnr_ny']) ."''$showLines','$r[id]',";
		$qtxt.= "'". db_escape_string($r['varenr']) ."','1','". db_escape_string($r['beskrivelse']) ."','$r[kostpris]')";
		db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		$qtxt = "select max(id) as id from ordrelinjer where ordre_id = '$showLines'";
		$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
		if ($_POST['beskrivelse_ny']) {
			$qtxt = "update ordrelinjer set beskrivelse = '". db_escape_string($_POST['beskrivelse_ny']) ."' where id = '$r[id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if ($_POST['lev_varenr_ny']) {
			$qtxt = "update ordrelinjer set lev_varenr = '". db_escape_string($_POST['lev_varenr_ny']) ."' where id = '$r[id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if ($_POST['pris_ny']) {
			$qtxt = "update ordrelinjer set pris = '". usdecimal($_POST['pris_ny'],2) ."' where id = '$r[id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
		if ($_POST['rabat_ny']) {
			$qtxt = "update ordrelinjer set rabat = '". usdecimal($_POST['rabat_ny'],3) ."' where id = '$r[id]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	} else alert("varenr $varenr_ny ikke fundet i varetabel");
}

if ($deleteMe && is_numeric($deleteMe)) {
	$qtxt = "select dokument from ordrer where dokument != '' and id = '$deleteMe'";
	if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
		unlink("../bilag/$db/$r[dokument]");
	}
	db_modify("delete from ordrelinjer where ordre_id = '$deleteMe'", __FILE__ . " linje " . __LINE__);
	db_modify("delete from ordrer where id = '$deleteMe'", __FILE__ . " linje " . __LINE__);
}
if ($move2order && is_numeric($move2order)) {
	$qtxt = "select max(ordrenr) as ordrenr from ordrer where art like 'K%'";
	$r = db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
	$newOrderNr = $r['ordrenr']+1;
	$qtxt = "update ordrer set art ='KO', ordrenr = '$newOrderNr', status='0' where id = '$move2order'";
	db_modify($qtxt, __FILE__ . " linje " . __LINE__);
}


print "<table class=\"orderCreditTable\">";
#print "<h2> Length: $paperflowLength </h2>";
#echo '<pre>'; print_r($paperflowArray); echo '</pre>';
$x=0;
$qtxt = "select * from ordrer where art = 'CS' order by id DESC";
$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
while ($r = db_fetch_array($q)) {
	$id[$x]                = $r['id'];
	$accountId[$x]         = $r['konto_id'];
	$accountNo[$x]         = $r['kontonr'];
	$companyVatNo[$x]      = $r['cvrnr'];
	$companyName[$x]       = $r['firmanavn'];
	$companyAddr1[$x]      = $r['addr1'];
	$companyAddr2[$x]      = $r['addr2'];
	$companyZip[$x]        = $r['postnr'];
	$companycity[$x]       = $r['bynavn'];
	$invoiceDate[$x]       = $r['fakturadate'];
	$invoiceNumber[$x]     = $r['fakturanr'];
	$dueDate[$x]           = $r['due_date'];
	$country[$x]           = $r['land'];
	$amountExVat[$x]       = $r['sum'];
	$vat[$x]               = $r['vat'];
	$vatRate[$x]           = $r['momssats'];
	$amountIncVat[$x]      = $amountExVat[$x]+$vat[$x];
	$currency[$x]          = $r['valuta'];
	$scanId[$x]            = $r['scan_id'];
	$document[$x]          = $r['dokument'];
	$ready4Import[$x]      = 1;
	$x++;
}
print "<tr><td>Kontonr</td><td>Skan Id</td><td>Cvr nr.</td><td>Fakt. nr.</td><td>Firmanavn</td><td>Nettosum</td><tr>";
for ($x=0;$x<count($id);$x++) {
	if (!$accountNo[$x]) {
		$qtxt = "select id, kontonr from adresser where art = 'K' and cvrnr != '' and  ";
		$qtxt.= "(cvrnr = '$companyVatNo[$x]' or cvrnr = '". str_replace('dk','',strtolower($companyVatNo[$x])) ."')";
#echo "$qtxt<br>";		
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$accountId[$x] = $r['id'];
			$accountNo[$x] = $r['kontonr'];
			$qtxt = "update ordrer set konto_id = '$accountId[$x]', kontonr = '$accountNo[$x]' where id = '$id[$x]'";
#echo "$qtxt<br>";		
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		} else $ready4Import[$x] = 0;
	}
	if ($accountId[$x] && !$companyAddr1[$x]) {
		$qtxt = "select addr1,addr2,postnr,bynavn from adresser where id = '$accountId[$x]'";
		if ($r = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
			$companyAddr1[$x]      = $r['addr1'];
			$companyAddr2[$x]      = $r['addr2'];
			$companyZip[$x]        = $r['postnr'];
			$companycity[$x]       = $r['bynavn'];
			$qtxt = "update ordrer set addr1 = '". db_escape_string($companyAddr1[$x]) ."', ";
			$qtxt.= "addr2 = '". db_escape_string($companyAddr2[$x]) ."', ";
			$qtxt.= "postnr = '". db_escape_string($companyZip[$x]) ."', ";
			$qtxt.= "bynavn = '". db_escape_string($companyCity[$x]) ."' where id = '$id[$x]'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
		}
	}
	
	include ("scanAttachments/getScan/getFields.php");
	$missingItems[$x]=$totalLineSum[$x]=$y=0;
	$lineId[$x]=$ItemNo[$x]=$quantity[$x]=$description[$x]=$price[$x]=$discount[$x]=array();
	$qtxt = "select * from ordrelinjer where ordre_id = '$id[$x]'";
	$q = db_select($qtxt, __FILE__ . " linje " . __LINE__);
	while ($r = db_fetch_array($q)) {
		$lineId[$x][$y]      = $r['id'];
		$itemNo[$x][$y]      = $r['varenr'];
		$suppItemNo[$x][$y]  = $r['lev_varenr'];
		$quantity[$x][$y]    = $r['antal'];
		$description[$x][$y] = $r['beskrivelse'];
		$price[$x][$y]       = $r['pris'];
		$discount[$x][$y]    = $r['rabat'];
		$lineSum[$x][$y]     = $quantity[$x][$y]*$price[$x][$y]-($quantity[$x][$y]*$price[$x][$y]*$discount[$x][$y]/100);
		$totalLineSum[$x]   += $lineSum[$x][$y];
		if ($suppItemNo[$x][$y] && !$itemNo[$x][$y]) {
			$qtxt = "select varer.varenr,vare_lev.vare_id from vare_lev,varer where ";
			$qtxt.= "lower(vare_lev.lev_varenr) = '". strtolower($suppItemNo[$x][$y]) ."' ";
			$qtxt.= "and varer.id = vare_lev.vare_id"; 
			if ($r2 = db_fetch_array(db_select($qtxt, __FILE__ . " linje " . __LINE__))) {
				$itemId[$x][$y]=$r2['id'];
				$itemNo[$x][$y]=$r2['varenr'];
				$qtxt = "update ordrelinjer set vare_id = '". $itemId[$x][$y] ."', varenr = '". $itemNo[$x][$y] ."'";
				$qtxt.= "where id = '". $lineId[$x][$y] ."'";
				db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			}
		}
		if (!$itemNo[$x][$y]) {
			$missingItems[$x]++;
			$ready4Import[$x] = 0;
		}
		$y++;
	}
	$linecount[$x]=$y;
	if ($var[$x] && $totalLineSum[$x] == $amountIncVat[$x]) {
		for ($y=0;$y<count($lineId[$x]);$y++) {
			$price[$x][$y] = $price[$x][$y]/$amountIncVat[$x]*$amountExVat[$x]; 
			$qtxt = "update ordrelinjer set pris = '". $price[$x][$y] ."' where id = '". $lineId[$x][$y] ."'";
			db_modify($qtxt, __FILE__ . " linje " . __LINE__);
			$totalLineSum[$x] += $lineSum[$x][$y];
		}
	}
	if ($accountNo[$x]) {
		print " <tr><td><a href = 'ordreliste.php?valg=skanBilag&showLines=$id[$x]'>$accountNo[$x]</a></td>";
	}	elseif ($companyVatNo[$x]) {
		print "<tr><td><a href = 'kreditorkort.php?cvrnr=$companyVatNo[$x]&firmanavn=$companyName[$x]";
		print "&bank_reg=$PayRegNo&bank_konto=$PayAccNo&returside=ordreliste.php'>Create</a></td>";
	} else print "<tr><td>Not ready</td>";
	if ($amountExVat[$x] == $totalLineSum[$x]) {
		$textColor='#000000';
	} else {
		$ready4Import[$x] = 0;
		$textColor='#FF0000';
	}
	print "<td>$scanId[$x]</td><td>$companyVatNo[$x]</td><td>$invoiceNumber[$x]</td><td>$companyName[$x]</td>";
	print "<td style ='text-align:right;color:$textColor;'>". dkdecimal($amountExVat[$x]) ."</td>";
	print "<td align='right'><a href = 'ordreliste.php?valg=skanBilag&deleteMe=$id[$x]' title = 'Slet bilag'>";
	print "<img src='../img/trash.png' alt = 'Trash'style='width:20px;height:20px;'></a>";
	if ($ready4Import[$x]) {
		print "<a href = 'ordreliste.php?valg=skanBilag&move2order=$id[$x]'  title = 'Flyt til ordrer'>";
	print "<img src='../img/thumbsUp.png' alt = 'Flyt til ordrer' style='width:25px;height:25px;'></a>";
	}
	print "</td></tr>";
	if ($showLines == $id[$x]) {
		if (!$missingItems[$x]) print "<form action='ordreliste.php?valg=skanBilag&showLines=$id[$x]' method='POST'>";

		print "<tr><td colspan='8'><table width = '100%' border = '1'>";	
		print "<tr><td colspan='7'><hr></td></tr>";
		print "<tr><td>Varenr</td><td>Lev. Varenr</td><td>Varenavn</td><td style ='text-align:right;'>Antal</td>";
		print "<td align='right'>Pris</td><td align='right'>Rabat</td><td align='right'>I alt</td></tr>";

		for ($y=0;$y<count($lineId[$x]);$y++) {
			print "<tr><td>";
			if ($itemNo[$x][$y]) print $itemNo[$x][$y];
			else {
				print "<form action='ordreliste.php?valg=skanBilag&showLines=$id[$x]' method='POST'>";
				print "<input type = 'hidden' name = 'accountId'   value = '". $accountId[$x] ."'>";
				print "<input type = 'hidden' name = 'suppItemNo'  value = '". $suppItemNo[$x][$y] ."'>";
				print "<input type = 'hidden' name = 'lineId'      value = '". $lineId[$x][$y] ."'>";
				print "<input type = 'hidden' name = 'description' value = '". $description[$x][$y] ."'>";
				print "<input type = 'hidden' name = 'price'       value = '". $price[$x][$y] ."'>";
				print "<input style='width:100px;' Name = 'newItemNo' type = 'text' placeholder = 'New Item No'>";
				print "<input type= 'submit' value='Ok'>";
				print "</form>";
			}
			print "</td><td>". $suppItemNo[$x][$y] ."</td><td>". $description[$x][$y] ."</td>";
			print "<td style ='text-align:right;'>". dkdecimal($quantity[$x][$y],2) ."</td>";
			print "<td align='right'>". dkdecimal($price[$x][$y]) ."</td>";
			if ($totalLineSum[$x] <= $amountExVat[$x]) {
				print "<td align='right'>". dkdecimal($discount[$x][$y]) ."</td>";
			} else {
			print "<td align = 'right'><input style = 'width:50px;text-align:right;' type = 'text' name = 'rabat'></td>";
			}
			print "<td align='right'>". dkdecimal($lineSum[$x][$y],2) ."</td>";
		}
		if ($totalLineSum[$x] < $amountExVat[$x]) {
			$diff = $amountExVat[$x] - $totalLineSum[$x];
			print "<tr>";
			$posnr=$y+2;
			print "<td><input type = 'hidden' name = 'posnr_ny' value = '$posnr'>";
			print "<input style = 'width:100px;' type = 'text' name = 'varenr_ny'></td>";
			print "<td><input style = 'width:100px;' type = 'text' name = 'lev_varenr_ny'></td>";
			print "<td><input style = 'width:300px;' type = 'text' name = 'beskrivelse_ny'></td>";
			print "<td align = 'right'><input style = 'width:50px;text-align:right;' type = 'text' name = 'antal_ny'></td>";
			print "<td align = 'right'>";
			print "<input style = 'width:50px;text-align:right;' type = 'text' name = 'pris_ny' value = '". dkdecimal($diff,2) ."'></td>";
			print "<td align = 'right'><input style = 'width:50px;text-align:right;' type = 'text' name = 'rabat_ny'></td>";
			print "</tr>";
		}
		if (!$missingItems[$x] && $totalLineSum[$x] != $amountExVat[$x]) print "<tr><td colspan = '7' align = 'right'><input type = 'submit' name = 'updateLines' value = 'update'</td></tr>";
		print "<tr><td colspan = '6'>Sum of orderlines</td><td align='right'>". dkdecimal($totalLineSum[$x],2) ."</td></tr>";
		if ($document[$x] && file_exists("../bilag/$db/scan/$document[$x]")) {
			print "<tr><td colspan = '7'><embed src='../bilag/$db/scan/$document[$x]' type='application/pdf' width='80%' height='80%' /></td></tr>";
		}
		print "<tr><td colspan='7' align='center'><b>Dokument ID:</b> $voucherId <b>Filnavn:</b> $document[$x]</td></tr>";
		print "<tr><td colspan='7'><hr></td></tr>";
		print "</table></td></tr>";
	}
}   
/*
 echo "$docType | $doc<br>";
    if ($docType == "array") {
            $orderValue = isMakeOrderReady($paperflowArray['data'][$i]);
            $creditorValue = checkExistingCreditor($paperflowArray['data'][$i]['header_fields']);
            #print "<h1> Kreditor value: $creditorValue </h1>";
            #print "<h1> Order value: $orderValue </h1>";
            $showDoc = $paperflowArray['data'][$i]['image'];
            foreach ($paperflowArray['data'][$i]['header_fields'] as $fields) {
                if ($fields['code'] == "company_name") {
                    $docName = $fields['value'];
                    //echo '<pre>'; print_r($docName); echo '</pre>';
                }
            }
            $docNumber = $i + 1;
            print "<tr class='pdfTable'>
                    <td class=\"orderCreditPdf\"> 
                            $docNumber.
                    </td>  
                    <td class=\"orderCreditPdf\"> 
                        <b> 
                            $id
                        </b> 
                    </td>
                    <td class=\"orderCreditPdf\"> 
                        $matches[1]
                    </td>";
            if (isset($doc['dokument'])) {
                print "<td class=\"orderCreditPdf\"> 
                        <div class=\"orderCreditLink\"> 
                            $doc[dokument]
                        </div> 
                    </td>";
            } else {
                print "<td class=\"orderCreditPdf\"> 
                        <div class=\"orderCreditLink\"> 
                        </div> 
                    </td>";
            }
            //print "orderValue: $orderValue <br>";
            if ($orderValue == 1) {
                print "<td class=\"orderCreditPdf\"> 
                        <div class=\"orderCreditLink\"> 
                            Ordren er oprettet
                        </div> 
                    </td>";
            } elseif ($orderValue == 2) {
                print "<td class=\"orderCreditPdf\"> 
                        <a class=\"orderCreditLink\" href='ordreliste.php?sort=$sort&makeOrder=$id&arrayNb=$i&valg=skanBilag$hreftext'> 
                            Opret som ordre 
                        </a> 
                    </td>";
            } elseif ($orderValue == 0) {
                print "<td class=\"orderCreditPdf\"> 
                        <div class=\"orderCreditLink\"> 
                            Ordren er ikke klar til oprettelse 
                        </div> 
                    </td>";
            }


            if ($creditorValue == true) {
                print "<td class=\"orderCreditPdf\"> 
                            <div class=\"orderCreditLink\"> 
                                    Kreditor er oprettet
                            </div> 
                    </td>";
            } else {
                print "<td class=\"orderCreditPdf\"> 
                            <a class=\"orderCreditLink\" href='ordreliste.php?sort=$sort&makeCreditor=$id&arrayNb=$i&valg=skanBilag$hreftext'> 
                                    Opret kreditor
                            </a> 
                    </td>";
            }

           # print "<td class=\"orderCreditPdf\">
            #            <a class=\"orderCreditLink\" href='ordreliste.php?sort=$sort&makeCashDraw=$id&arrayNb=$i&valg=skanBilag$hreftext'>
            #                Flyt til kassekladde
            #            </a>
            #    </td>";

            if (isset($docName)) {
                print "<td class=\"orderCreditPdf\"> 
                    <a class=\"orderCreditLink\" href=ordreliste.php?sort=$sort&showPdf=$i&valg=skanBilag$hreftext> 
                        $docName
                    </a> 
                </td>";
            } else {
                print "<td class=\"orderCreditPdf\">  </td>";
            }

            print "<td class=\"orderCreditPdf\"> 
                    <a class=\"orderCreditLink\" href='ordreliste.php?sort=$sort&showContent=$i&valg=skanBilag$hreftext'> 
                        Vis indhold 
                    </a> 
                </td>
                <td class=\"orderCreditPdf\"> 
                    <a class=\"orderCreditLink\" href='ordreliste.php?sort=$sort&deleteId=$id&valg=skanBilag$hreftext'> 
                        Slet voucher
                    </a> 
                </td>
            </tr>";
        }
    }
 */   
    print "</table>";

    print "<style>
                .orderCreditPdf, .showContentTable {
                        border: 1px solid black;
                        height: 25px;
                        text-align: center;
                }
                .orderCreditLink {
                        cursor: pointer;
                }
                .orderCreditTable {
                        border-collapse: collapse;
                        width: 1200px;
                }
                .pdfTable {
                    height: 30px;
                }
                .theShowContentTable {
                        border-collapse: collapse;
                        width: 900px;
                        margin-top: 30px;
                } 
            </style>";
?>


