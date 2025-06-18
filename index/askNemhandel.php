<?php 
    $query = db_select("SELECT * FROM settings WHERE var_name = 'companyID'", __FILE__ . " linje " . __LINE__);
    $row = db_fetch_array($query);
    if(db_num_rows($query) === 0){
        // Ask the user if they want to get created in nemhandel
        ?>
        <script>
            if(confirm("Vil du oprettes i nemHandel?")){
                res = fetch("createEasyUbl.php",
                {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        "apiKey": "<?php echo $apiKey; ?>"
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if(data.succes == true){
                        res = fetch("uploadCompanyId.php",
                        {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json"
                            },
                            body: JSON.stringify({
                                "companyID": data.companyID
                            })
                        }).then(response => response.json())
                        .then(data => {
                            if(data.succes == true){
                                alert("Du er nu oprettet i nemHandel");
                                window.location.href = "index.php";
                            }else{
                                alert("Der skete en fejl");
                            }
                        })
                    }else{
                        alert("Der skete en fejl: " + data.message);
                    }
                })
            }
        </script>
        <?php
    }