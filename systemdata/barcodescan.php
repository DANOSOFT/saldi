<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --------------systemdata/barcodescan.php-----ver 5.1.0 ----2026-11-02-----
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------

// 20240828 LOE created this file
// 20261102 CL/ASR The standard settings layout (includes/settings/layout.php; owner, 2 Nov 2026: every page reached from the settings keeps the settings menu): opened from Indstillinger > Integrationer > App (Vis QR-kode til app-login) the page had no way back.
//                 It shows the breadcrumb Indstillinger / Virksomhed / Integrationer / Vis QR-kode til app-login, Tilbage to the App panel and the Virksomhed menu with Integrationer marked; the QR code and its texts are unchanged, their styles only reach the page's own box.
//                 The login is checked by includes/online.php as on every page, and the code's address is the session's own company ($db), not the first online row with the same user name (another company's, when two share a user name).

session_start();
$s_id = session_id();
$title = "QR Code Page";
$css = "../css/standard.css";
$permission_key = 'any'; // every logged-in user may show the code, as before

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");
include_once("../includes/settings/layout.php");

$data = $h4Style = $error = null;
if (!empty($db)) {
	$data = "bilag_" . $db . "@" . $_SERVER['SERVER_NAME']; // the company's address, for the app
} else {
	$error = "Invalid user detail";
	$h4Style = 'style="color: red;"';
}
settings_layout_open(array(
	'at' => 'barcodescan', 'title' => findtekst('6095|Vis QR-kode til app-login', $sprog_id),
	'back' => 'settingsSection.php?s=integrations.connections&item=app',
));
?>
    <style>
        .bc-page {
            font-family: Arial, sans-serif;
        }
        .bc-page .container {
            display: flex;
            max-width: 1200px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 20px;
        }
        .bc-page .column {
            flex: 1;
            padding: 20px;
        }
        .bc-page .barcode-column {
            text-align: center;
            overflow: hidden;
        }
        .bc-page .description {
            margin-bottom: 20px;
        }
        .bc-page h4 {
            color: red;
        }
        .bc-page img {
            width: 300px; /* Adjust image size for the QR Code */
            height: auto;
        }
    </style>
    <!-- QR Code Library -->
    <script language="javascript" type="text/javascript" src="../javascript/qrcode.min.js"></script>
    <div class="bc-page">
    <div class="container">
        <div class="column">
            <!-- Left column content -->
            <h4 id="error-message" <?php echo $h4Style; ?>>
                <?php
                if (!empty($error)) {
                    echo htmlspecialchars($error);
                } else {
                    echo "Hello " . htmlspecialchars((string) $brugernavn_raw);
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
    </div>

    <script>
        window.onload = function() {
            var data = <?php echo json_encode((string) $data); ?>; // PHP variable for data

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
<?php settings_layout_close(); ?>
</body>
</html>