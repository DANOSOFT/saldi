<?php


    function makeCashDraw($paperflowArray, $id, $i)
    {
        print "<h2> Her er vi klar til at lave kassekladen </h2>";
        print "<h3> Vi har id: $id og nr: $i </h3>";
        print "<h3> Data for det bilag: </h3>";
        $paperflowData = $paperflowArray['data'][$i]['header_fields'];
        #print "<pre>"; print_r($paperflowData); print "</pre>";
        $data = getCashDrawData($paperflowData);
        print "<h3> Cvr nr: $data[cvr] </h3>";
        print "<h3> Total sum: $data[totalsum] </h3>";
    }

    function getCashDrawData($array)
    {
        $returnArr = array();
        foreach ($array as $arr) {
            if ($arr['code'] == "company_vat_reg_no") {
                $returnArr['cvr'] = $arr['value'];
            } elseif ($arr['code'] == "total_amount_incl_vat") {
                $returnArr['totalsum'] = $arr['value'];
            }
        }
        return $returnArr;
    }






?>