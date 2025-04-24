<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/barcodescan.php-----patch 4.1.1 ----2025-04-14-----
//                           LICENSE
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
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2025 Saldi.dk ApS
// ----------------------------------------------------------------------

// 20240828 LOE created this file

session_start();

include("../includes/connect.php");
include("../includes/std_func.php");
$data = $h4Style= null;
    if (isset($_SESSION['UserName'])) {
    
        $brugernavn= $_SESSION['UserName'];
    } elseif(isset($brugernavn)){
        $brugernavn = null;
    }

    $r = db_fetch_array(db_select("select * from online WHERE brugernavn ='$brugernavn'",__FILE__ . " linje " . __LINE__));

		
 if($r && if_isset($r['session_id'])){

    $db_name = trim($r['db']); //to locate the db used for the email
    $saldiEmail = "bilag_".$db_name."@".$_SERVER['SERVER_NAME'];
    $data = $saldiEmail;
           
 }else{
    $error = "Invalid user detail";
    $h4Style = !empty($error) ? 'style="color: red;"' : '';
 }



 ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f4f4;
        }
        .container {
            display: flex;
            width: 80%;
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .column {
            flex: 1;
            padding: 20px;
        }
        .barcode-column {
            text-align: center;
            overflow: hidden;
        }
        .description {
            margin-bottom: 20px;
        }
        h4 {
            color: red;
        }
        img {
            width: 300px; /* Adjust image size for the QR Code */
            height: auto;
        }
    </style>
    <!-- QR Code Library -->
    <script language="javascript" type="text/javascript" src="../javascript/qrcode.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="column">
            <!-- Left column content -->
            <h4 id="error-message" <?php echo $h4Style; ?>>
                <?php
                if (!empty($error)) {
                    echo htmlspecialchars($error);
                } else {
                    echo "Hello " . htmlspecialchars($brugernavn);
                }
                ?>
            </h4>
        </div>
        <div class="column barcode-column">
            <!-- Center column with barcode -->
           
            <!-- Image for QR Code -->
            <img id="qrcode-img" alt="QR Code" />
        </div>
        <div class="column">
            <!-- Right column content -->
            <div class="description">
                <p>The QR code gives a one-time access to the saldi app, until a user unchecks the "Remember me" box on the app.</p>
                <p>Please scan to continue if you are authorized!</p>
            </div>
        </div>
    </div>

    <script>
        window.onload = function() {
            var data = "<?php echo $data; ?>"; // PHP variable for data

            // Error handling in JavaScript
            var errorMessageElement = document.getElementById("error-message");

            if (data) {
               

                // Generate QR code only if there's data
                QRCode.toDataURL(data, function (err, url) {
                    if (err) {
            
                        errorMessageElement.textContent = "Error generating QR code.";
                    } else {
                        // Set the src attribute of the img tag to the generated QR code URL
                        document.getElementById('qrcode-img').src = url;
                    }
                });
            } else {
                // Handle error if no data is available
                errorMessageElement.textContent = "Invalid data for QR code generation.";
            }
        };
    </script>
</body>
</html>