<?php






    function checkExistingCreditor($dataArray)
    {
        $checkData = checkCreditorData($dataArray);
        #print "<h1> Check </h1>";
        #print "<pre>"; print_r($checkData); print "</pre>";

        if (count($checkData) > 0) {
            $cvrCheck = db_fetch_array(db_select("select * from adresser where cvrnr = '$checkData[cvr]'", __FILE__ . " linje " . __LINE__));
            $bankAccCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$checkData[payAccNb]' and bank_reg = '$checkData[payRegNb]'", __FILE__ . " linje " . __LINE__));
            $bankCvrCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$checkData[payAccNb]' and bank_reg = '$checkData[payRegNb]' and cvrnr = '$checkData[cvr]'", __FILE__ . " linje " . __LINE__));
            #print "<h3> cvr: </h3>"; print_r($cvrCheck);
            #print "<h3> bank: </h3>"; print_r($bankAccCheck);
            #print "<h3> cvr og bank: </h3>"; print_r($bankCvrCheck);
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




    function checkCreditorData($pdfArray)
    {
        #print "<h2> Check creditor data </h2>";
        #print "<pre>"; print_r($pdfArray); print "</pre>";
        $returnArray = array();
        foreach($pdfArray as $data) {
            if ($data['code'] == "payment_reg_number") {
                $returnArray['payRegNb'] = $data['value'];
            } elseif ($data['code'] == "payment_account_number") {
                $returnArray['payAccNb'] = $data['value'];
            } elseif ($data['code'] == "company_vat_reg_no") {
                $returnArray['cvr'] = $data['value'];
            } elseif ($data['code'] == "company_name") {
                $returnArray['comName'] = $data['value'];
            }
        }
        #print "<pre>"; print_r($returnArray); print "</pre>";
        return $returnArray;
    }









?>