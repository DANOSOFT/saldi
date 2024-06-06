<?php



    function insertIntoDatabase($paperflowArray, $arrayNb)
    {
        #print "<h3>
        #            Her skal den oprette en ny kreditor. Informationer er hentet fra cvr registeret.
        #        </h3>";
        #print "<h3> _GET </h3>";
        #print "<pre>"; print_r($_GET); print "</pre>";
        #print "<h3> _COOKIE </h3>";
        #print "<pre>"; print_r($_COOKIE); print "</pre>";
        #print "<h3> Paperflow </h3>";
        $data = getDbData($paperflowArray['data'][$arrayNb]['header_fields']);
        #print "<pre>"; print_r($data); print "</pre>";
        $dbValues = "firmanavn, addr1, postnr, bynavn, tlf, email, bank_reg, bank_konto, art, cvrnr";
        $creditorInsert = "insert into adresser (" . $dbValues . ")";
        $creditorInsert .= "values ('$_COOKIE[name]', '$_COOKIE[adress]',";
        $creditorInsert .= "'$_COOKIE[zipcode]', '$_COOKIE[city]', '$_COOKIE[phone]', '$_COOKIE[email]',";
        $creditorInsert .= "$data[payRegNb], $data[payAccNb], 'K', '$data[cvr]')";
        db_modify($creditorInsert, __FILE__ . " linje " . __LINE__);
    }


    function getDbData($pdfArray)
    {
        //         print "<pre>"; print_r($pdfArray); print "</pre>";
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