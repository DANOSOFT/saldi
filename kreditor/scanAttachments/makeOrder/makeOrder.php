<?php


    function makeAsOrder($paperflowArray, $id)
    {
        $arrayNb = if_isset($_GET['arrayNb']);

        #print "makeOrder: $id <br>";
        #print "Array number: $arrayNb <br>";

        $paperflowData = getOrderData($paperflowArray['data'][$arrayNb]['header_fields']);
        insertOrder($paperflowData);

        #print "<pre>"; print_r($paperflowData); print "</pre>";
        $paperflowCvr = $paperflowData['cvr'];
        $paperflowBankAcc = $paperflowData['payAccNb'];
        $paperflowBankReg = $paperflowData['payRegNb'];

        #print "<h3> Cvr nr: $paperflowCvr <br> Bank reg: $paperflowBankReg <br> Konto nr: $paperflowBankAcc <br> </h3>";

    }

    function insertOrder($data)
    {
        $dbValues = "firmanavn, fakturadate, land, ordredate, cvrnr, art";
        $orderInsert = "insert into ordrer (" . $dbValues . ")";
        $orderInsert .= "values ('$data[name]', '$data[payDate]',";
        $orderInsert .= "'$data[country]', '$data[date]', '$data[cvr]', 'KO')";
        db_modify($orderInsert, __FILE__ . " linje " . __LINE__);
    }

			
    function getOrderData($pdfArray)
    {
        //print "<pre>"; print_r($pdfArray); print "</pre>";
        $returnArray = array();
        foreach($pdfArray as $data) {
            if ($data['code'] == "payment_reg_number") {
                $returnArray['payRegNb'] = $data['value'];
            } elseif ($data['code'] == "payment_account_number") {
                $returnArray['payAccNb'] = $data['value'];
            } elseif ($data['code'] == "company_vat_reg_no") {
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
        #print "<pre>"; print_r($returnArray); print "</pre>";
        return $returnArray;
    }

?>
