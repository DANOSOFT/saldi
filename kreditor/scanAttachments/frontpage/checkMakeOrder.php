<?php



    function isMakeOrderReady($dataArray)
    {
        $itemCheck = checkItems($dataArray['line_items']);
        $creditCheck = checkCreditors($dataArray['header_fields']);
        $orderCheck = alreadyMade($dataArray['header_fields']);
        if ($orderCheck == true) {
            return 1;
        } elseif ($itemCheck == true && $creditCheck == true) {
            return 2;
        } else {
            return 0;
        }
    }

    function alreadyMade($initalData)
    {
        $data = getCheckData($initalData);
        if (checkArrayIndexes($data) && count($data) > 0) {
            //print "<pre>"; print_r($data); print "</pre>";
            $orderInsert = "select * from ordrer where firmanavn = '$data[name]' and ";
            $orderInsert .= "land = '$data[country]' and ";
            $orderInsert .= "ordredate = '$data[date]' and cvrnr = '$data[cvr]'";
            $existingOrder = db_fetch_array(db_select($orderInsert, __FILE__ . " linje " . __LINE__));
            if (isset($existingOrder['id'])) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    function checkArrayIndexes($array)
    {
        $exist = 0;
        $nonExists = 0;
        foreach ($array as $key => $value) {
            $value = trim($value);
            if (empty($value)) {
                $nonExists++;
            } else {
                $exist++;
            }
        }
        if ($nonExists > 2) {
            return false;
        } else {
            return true;
        }
    }

    function getCheckData($pdfArray)
    {
        //print "<pre>"; print_r($pdfArray); print "</pre>";
        $returnArray = array();
        foreach($pdfArray as $data) {
            if ($data['code'] == "company_vat_reg_no") {
                $returnArray['cvr'] = $data['value'];
            } elseif ($data['code'] == "invoice_date") {
                $returnArray['date'] = $data['value'];
            } elseif ($data['code'] == "company_name") {
                $returnArray['name'] = $data['value'];
            } elseif ($data['code'] == "payment_date") {
                $returnArray['payDate'] = $data['value'];
            } elseif ($data['code'] == "country") {
                $returnArray['country'] = $data['value'];
            }
        }
        //print "<pre>"; print_r($returnArray); print "</pre>";
        return $returnArray;
    }

    function checkItems($lineItems)
    {
        #$lineItems = $paperflowArray['data'][$contentId]['line_items'];
        for ($i = 0; $i < sizeof($lineItems); $i++) {
            $content = $lineItems[$i]['fields'];
            foreach ($content as $singleContent) {
                if ($singleContent['code'] == "description") {
                    $description = $singleContent['value'];
                } elseif ($singleContent['code'] == "article_number") {
                    $artNb = $singleContent['value'];
                } elseif ($singleContent['code'] == "unit_price") {
                    $unitPrice = $singleContent['value'];
                }
            }
            $productCheck = db_fetch_array(db_select("select * from varer where beskrivelse = '$description'", __FILE__ . " linje " . __LINE__));
            if (!isset($productCheck['id']) && ($unitPrice != 0 || $artNb != "")) {
                return false;
            }
        }
        return true;
    }

    function checkCreditors($dataArray)
    {
        $checkData = getTempData($dataArray);
        #print "<pre>"; print_r($checkData); print "</pre>";
        if (count($checkData) > 0) {
            $cvrCheck = db_fetch_array(db_select("select * from adresser where cvrnr = '$checkData[cvr]'", __FILE__ . " linje " . __LINE__));
            $bankAccCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$checkData[payAccNb]' and bank_reg = '$checkData[payRegNb]'", __FILE__ . " linje " . __LINE__));
            $bankCvrCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$checkData[payAccNb]' and bank_reg = '$checkData[payRegNb]' and cvrnr = '$checkData[cvr]'", __FILE__ . " linje " . __LINE__));
            if (isset($cvrCheck['id']) || isset($bankAccCheck['id']) || isset($bankCvrCheck['id'])) {
                #print "True <br>";
                return true;
            } else {
                #print "False <br>";
                return false;
            }
        } else {
            return false;
        }
    }




    function getTempData($pdfArray)
    {
        #print "<pre>"; print_r($pdfArray); print "</pre>";
        $returnArray = array();
        foreach($pdfArray as $data) {
            if ($data['code'] == "payment_reg_number") {
                $returnArray['payRegNb'] = $data['value'];
            } elseif ($data['code'] == "payment_account_number") {
                $returnArray['payAccNb'] = $data['value'];
            } elseif ($data['code'] == "company_vat_reg_no") {
                $returnArray['cvr'] = $data['value'];
            }
        }
        #print "<pre>"; print_r($returnArray); print "</pre>";
        return $returnArray;
    }









?>