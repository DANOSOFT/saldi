<?php

    function accountNumberCheck($paperflowArray)
    {
        #print "<h2> We must check the account number </h2>";
        $accId = $_GET['arrayId'];
        $i = $_GET['arrayNb'];
        $accNb = $_COOKIE['accountNumber'];
        //print "<pre>"; print_r($_GET); print "</pre>";
        $accCheck = db_fetch_array(db_select("select * from adresser where kontonr = '$accNb'", __FILE__ . " linje " . __LINE__));
        if (isset($accCheck['id'])) {
            #print "<pre>"; print_r($accCheck); print "</pre>";
            setAccountHtml($accNb, $accCheck['firmanavn'], $i, $paperflowArray, $accId);
        } else {
            makeNewCreditorHtml($accNb, $i, $paperflowArray, $accId);
        }
        print "<script> document.cookie = \"checkAccountNumber = false; expires=Thu, 01 Jan 1970 00:00:00 UTC;\"; </script>";
    }

    function setAccountHtml($accountNumber, $company, $i, $paperflowArray, $addId)
    {
        #print "<h3> setAccountHtml </h3>";
        $sort = $_GET['sort'];
        $id = $paperflowArray['data'][$i]['id'];
        print "<div id=\"myModal\" class=\"modal\">
                    <div class=\"modal-content\">
                        <h2>
                            Vil du bruge dette firma som kreditor?
                        </h2>
                        <div><b>Firmanavn: $company</b></div>
                        <div><b>Konto nr: $accountNumber</b></div><br>
                        <button type=\"button\" onclick=\"window.location.href='ordreliste.php?sort=$sort&useAccountNumber=1&arrayNb=$i&valg=skanBilag'\">
                            Ja
                        </button>
                        <button type=\"button\" onclick=\"window.location.href='ordreliste.php?sort=$sort&makeCreditor=$id&arrayNb=$i&valg=skanBilag'\">
                            Nej
                        </button>
                    </div>
                </div>";
    }

    function makeNewCreditorHtml($accNb, $i, $paperflowArray, $accId)
    {
        $sort = $_GET['sort'];
        $id = $paperflowArray['data'][$i]['id'];
        #print "<h3> makeNewCreditorHtml <br> id: $id <br> i: $i <br> accNb: $accNb </h3>";
        print "<div id=\"myModal\" class=\"modal\">
                        <div class=\"modal-content\">
                            <h2>
                                Kunne ikke finde en kreditor med konto nummer: $accNb
                            </h2>
                            <div><b>Vil du oprette en ny kreditor med det konto nr? </b></div><br>
                            <button type=\"button\" onclick=\"window.location.href='ordreliste.php?sort=$sort&useAccountNumber=2&arrayNb=$i&valg=skanBilag'\">
                                Ja
                            </button>
                            <button type=\"button\" onclick=\"window.location.href='ordreliste.php?sort=$sort&makeCreditor=$id&arrayNb=$i&valg=skanBilag'\">
                                Nej
                            </button>
                        </div>
                    </div>";
    }

    function useInsertedAccountNumber($paperflowArray)
    {
        $arrayNb = $_GET['arrayNb'];
        $paperflowData = getPaperflowData($paperflowArray['data'][$arrayNb]['header_fields']);
        # print "<h2> We will use the account number </h2>";
        if($_GET['useAccountNumber'] == 1) {
            insertAccount($paperflowData, $_COOKIE['accountNumber'], "existingCreditor");
            #   print "<h1> About to make the final insert, to the kreditor with konto nr: $_COOKIE[accountNumber]</h1>";
        } elseif($_GET['useAccountNumber'] == 2) {
            insertAccount($paperflowData, $_COOKIE['accountNumber'], "newCreditor");
            #    print "<h1> About to make a new kreditor with konto nr: $_COOKIE[accountNumber]</h1>";
        }

        #     print "Paperflow: <br>";
        #    print "<pre>"; print_r($_GET); print "</per>";
        #    print "<pre>"; print_r($paperflowArray['data'][$arrayNb]['header_fields']); print "</per>";
        #$data = getPaperflowData($paperflowArray['data'][$arrayNb]['header_fields']);
        #$data['payAccNb'] = $_COOKIE['accountNumber'];
        #updateOrder($data, $addrId);
    }

    function insertAccount($data, $accNb, $insertType)
    {
        #   print "<h3> insertAccount </h3>";
        if ($insertType == "existingCreditor") {
            $addrUpdate = "update adresser set firmanavn='$data[comName]', land='$data[country]', bank_reg='$data[payRegNb]', bank_konto='$data[payAccNb]', art='K', cvrnr='$data[cvr]' where kontonr = '$accNb'";
            db_modify($addrUpdate, __FILE__ . " linje " . __LINE__);
        } elseif ($insertType == "newCreditor") {
            $creditorInsert = "insert into adresser (firmanavn, land, bank_reg, bank_konto, art, cvrnr, kontonr)";
            $creditorInsert .= "values ('$data[comName]', '$data[country]', '$data[payRegNb]', '$data[payAccNb]', 'K', '$data[cvr]', '$accNb')";
            db_modify($creditorInsert, __FILE__ . " linje " . __LINE__);
        }
    }

    function updateOrder($data, $addrId)
    {
        #   print "<h3> updateOrder </h3>";
        $orderInsert = "insert into ordrer (konto_id, firmanavn, land, kontonr, cvrnr, art, valuta, fakturadate, sum, moms)";
        $orderInsert .= "values ('$addrId', '$data[comName]', '$data[country]', '$data[payAccNb]', '$data[cvr]', 'KO', '$data[currency]', '$data[invoiceDate]', '$data[totalAmountInclVat]', '$data[totalVat]')";
        db_modify($orderInsert, __FILE__ . " linje " . __LINE__);

        $orderId = db_fetch_array(db_select("select * from ordrer where konto_id = '$addrId'", __FILE__ . " linje " . __LINE__))['id'];
        $orderlineInsert = "insert into ordrelinjer (ordre_id)";
        $orderlineInsert .= "values ('$orderId')";
        db_modify($orderlineInsert, __FILE__ . " linje " . __LINE__);
    }

    function insertProducts($prArray, $id, $addrId)
    {
        # print "<h3> insertProducts </h3>";
        $lineItems = $prArray['data'][$id]['line_items'];
        for ($i = 0; $i < sizeof($lineItems); $i++) {
            $content = $lineItems[$i]['fields'];
            foreach ($content as $singleContent) {
                if ($singleContent['code'] == "description") {
                    $description = $singleContent['value'];
                    # print "Description: " . $description . "<br>";
                } elseif ($singleContent['code'] == "unit_price") {
                    $unitPrice = $singleContent['value'];
                    #  print "Enhedspris: " . $unitPrice . "<br>";
                } elseif ($singleContent['code'] == "amount") {
                    $amount = $singleContent['value'];
                    #  print "Pris: " . $amount . "<br>";
                } elseif ($singleContent['code'] == "quantity") {
                    $quantity = $singleContent['value'];
                    #   print "Mængde: " . $quantity . "<br>";
                } elseif ($singleContent['code'] == "article_number") {
                    $varenr = $singleContent['value'];
                    #  print "Varenr: " . $varenr . "<br>";
                }
            }

            if (isset($description) && isset($quantity) && isset($unitPrice) && isset($amount) && isset($varenr)) {
                $supplier = db_fetch_array(db_select("select * from vare_lev where lev_id = '$addrId', lev_varenr = '$varenr'", __FILE__ . " linje " . __LINE__));
                if (isset($supplier)) {
                    #    print "It exists";
                    $vareId = $supplier['vare_id'];
                    $product = db_fetch_array(db_select("select * from varer where id = '$vareId'", __FILE__ . " linje " . __LINE__));
                    print "<script> $(\".showPossibleProduct\").show(); </script>";
                } else {
                    #   print "It does not exists";
                    $product = db_fetch_array(db_select("select * from varer where varenr = '$varenr'", __FILE__ . " linje " . __LINE__));
                }
            }
            print "<br> <br> <br> <br>";
        }
    }

    function getPaperflowData($pdfArray)
    {
        #print "<pre>"; print_r($pdfArray); print "</pre>";
        $returnArray = array();
        foreach($pdfArray as $data) {
            if ($data['code'] == "payment_reg_number") {
                $returnArray['payRegNb'] = $data['value'];
            } elseif ($data['code'] == "total_amount_incl_vat") {
                $returnArray['totalAmountInclVat'] = $data['value'];
            } elseif ($data['code'] == "payment_date") {
                $returnArray['payDate'] = $data['value'];
            } elseif ($data['code'] == "voucher_type") {
                $returnArray['voType'] = $data['value'];
            } elseif ($data['code'] == "company_name") {
                $returnArray['comName'] = $data['value'];
            } elseif ($data['code'] == "payment_account_number") {
                $returnArray['payAccNb'] = $data['value'];
            } elseif ($data['code'] == "country") {
                $returnArray['country'] = $data['value'];
            } elseif ($data['code'] == "total_amount_excl_vat") {
                $returnArray['totalAmountExclVat'] = $data['value'];
            } elseif ($data['code'] == "invoice_date") {
                $returnArray['invoiceDate'] = $data['value'];
            } elseif ($data['code'] == "total_vat_amount_scanned") {
                $returnArray['totalVat'] = $data['value'];
            } elseif ($data['code'] == "company_vat_reg_no") {
                $returnArray['cvr'] = $data['value'];
            } elseif ($data['code'] == "payment_code_id") {
                $returnArray['payCodeId'] = $data['value'];
            } elseif ($data['code'] == "joint_payment_id") {
                $returnArray['jointPayId'] = $data['value'];
            } elseif ($data['code'] == "payment_id") {
                $returnArray['payId'] = $data['value'];
            } elseif ($data['code'] == "voucher_number") {
                $returnArray['voNb'] = $data['value'];
            } elseif ($data['code'] == "currency") {
                $returnArray['currency'] = $data['value'];
            } elseif ($data['code'] == "article_number") {
                $returnArray['articleNumber'] = $data['value'];
            }
        }
        #print "<pre>"; print_r($returnArray); print "</pre>";
        return $returnArray;
    }

?>
