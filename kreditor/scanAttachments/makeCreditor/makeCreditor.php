<?php


    function makeCreditor($id, $paperflowArray, $sort, $hreftext)
    {
        #print "<h2> Den nye kreditor side</h2>";

        $arrayNb = if_isset($_GET['arrayNb']);

        #print "makeOrder: $id <br>";
        #print "Array number: $arrayNb <br>";

        $paperflowData = getCreditorData($paperflowArray['data'][$arrayNb]['header_fields']);

        $paperflowCvr = $paperflowData['cvr'];
        $paperflowBankAcc = $paperflowData['payAccNb'];
        $paperflowBankReg = $paperflowData['payRegNb'];

        #print "<h3> Cvr nr: $paperflowCvr <br> Bank reg: $paperflowBankReg <br> Konto nr: $paperflowBankAcc <br> </h3>";


        $cvrCheck = db_fetch_array(db_select("select * from adresser where cvrnr = '$paperflowCvr'", __FILE__ . " linje " . __LINE__));
        $bankAccCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$paperflowBankAcc' and bank_reg = '$paperflowBankReg'", __FILE__ . " linje " . __LINE__));
        $bankCvrCheck = db_fetch_array(db_select("select * from adresser where bank_konto = '$paperflowBankAcc' and bank_reg = '$paperflowBankReg' and cvrnr = '$paperflowCvr'", __FILE__ . " linje " . __LINE__));

        if(!isset($cvrCheck['id']) && !isset($bankAccCheck['id']) && !isset($bankCvrCheck['id'])) {
            #print "Denne kreditor skal oprettes";
            creditorModal($paperflowCvr, $sort, $id, $hreftext);
            creditorStyle();
            creditorScript();
        } else {
            #print "Denne kreditor er oprettet";
        }
    }

    function creditorModal($cvrNr, $sort, $i, $hreftext)
    {
        $arrayNb = $_GET['arrayNb'];
        print "<div id=\"creditorModal\" class=\"modal\">
                    <div class=\"modal-content\">
                        <span class=\"close\">&times;</span>
                        <h2>
                            Der er to muligheder for at oprette denne kreditor. Enten kan du oprette en helt ny kreditor, eller indtaste et konto nr. på en eksisterende kreditor.
                        </h2>
                        <div><b>Id: $i</b></div>
                        <div><b>Cvr nr: $cvrNr</b></div><br>
    
                        <a class=\"makeCreditorButton\" href=\"ordreliste.php?sort=$sort&arrayNb=$arrayNb&arrayId=$i&makeNewCreditor=1&creditorCvr=$cvrNr&valg=skanBilag$hreftext\">
                            Opret ny kreditor
                        </a>
                        
                        <a class='makeCreditorButton enterAccountNumber'>Indtast konto nr</a>
                        
                        <div class='enterAccountNumberForm' style='display: none'>	
                            <br>
                            <label for=\"fname\">
                                <b>
                                    Indtast konto nr på eksisterende kreditor:
                                </b>
                            </label>
                            <input type=\"text\" id=\"accountNumber\" name=\"accountNumber\">
                                <a class=\"makeCreditorButton submitAccountNumber\" style=\"padding: 5px 20px;\" href='ordreliste.php?sort=$sort&arrayId=$i&arrayNb=$arrayNb&valg=skanBilag$hreftext'> 
                                    Enter
                                </a> 				  	
                            <br><br>
                        </div>
                    </div>
            </div>";
    }


    function creditorScript()
    {
        print "<script>
                    $(\".submitAccountNumber\").click(function (){
                        let accNb = $(\"#accountNumber\").val();

                        if (!accNb.match(/[0-9]+/) || accNb.match(/^00*0$|^0$/)) {
                            alert(\"Du skal indtaste et konto nr, kun bestående af tal\");
                            return false;
                        } else {
                            document.cookie = \"accountNumber =\" + accNb;
                            document.cookie = \"checkAccountNumber = true\";
                        }
            });
    
                    $(\".enterAccountNumber\").click(function () {
                            $(\".enterAccountNumberForm\").show();
                    });							
    
                    $(\".close\").click(function() {
                        $(\"#creditorModal\").hide();
                    });
                </script>";
    }

    function creditorStyle()
    {
        print "<style>
                        a.makeCreditorButton {
                                appearance: button;
                                border: solid;
                                background-color: #e7e7e7;
                                font-size: 12px;
                                border-radius: 2px;
                                padding: 12px 25px;
                                text-decoration: none;
                                color: initial;
                                cursor: pointer;
                        }
                </style>";
    }

    function makeNewCreditor($paperflowArray)
    {
        #print "<pre>"; print_r($_GET); print "</pre>";
        $cvr = $_GET['creditorCvr'];
        $arrayNb = $_GET['arrayNb'];
        if (strpos($cvr, "DK") !== false) {
            $cvr = str_replace("DK", "", $cvr);
        }
        $url = "https://cvrapi.dk/api?search=" . $cvr . "&country=dk";
        #print "<h3> Cvr nr: $cvr </h3>";
        #print "<h3> Web: $url </h3>";

        print "<script>
                    let url = " . json_encode($url) . ";
                    $.get(url, function(data) {
                        console.log({url});	
                        console.log({data});	
                        
                        document.cookie = 'adress=' + data.address;				
                        document.cookie = 'city=' + data.city;				
                        document.cookie = 'zipcode=' + data.zipcode;				
                        document.cookie = 'vat=' + data.vat;				
                        document.cookie = 'phone=' + data.phone;				
                        document.cookie = 'name=' + data.name;				
                        document.cookie = 'email=' + data.email;				
                    }, 'jsonp' );
                </script>";
        insertIntoDatabase($paperflowArray, $arrayNb);
    }


    function getCreditorData($pdfArray)
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