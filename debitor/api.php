<?php
// This file is used to send invoices to EasyUBL
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/api.php --- ver 5.0.0 --- 2026-10-02 ---
// LICENSE
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

// 20260518 NTR - Changed address fetch logic, such that multiple spaces doesn't result in a incorrect address
// &&           - Changed the total logic, such that values above a thousand doesn't cut it to the thousands. aka. 27,010.40 became 27 due to the ,
// 20260609 CL/PHR - Null-check på EasyUBL-svar: tomt svar (HTTP 500) ved kreditnotaer giver nu dansk fejlbesked i stedet for "null"
// 20260619 NTR - Added more info to error files and changed the random string logic with orderId so it's easier to know which file comes from which order.
// &&           - Added the logic from ordre.php about fetching ean numbers from konto instead if it's not in the ordrer.
// 20260623 NTR - Added a , check on Addresses as sometimes people write extra information that is useless to us.
// 20260701 NTR - Added the moms logic from formfunk to api.php. Ideally this should be moved to a separate shared function, but for now it's here.
// 20260703 CDX/NTR - Added a function to take care of streetnames as the edgecases was making it too complex to handle in a oneliner, due to people writing addresses in a non-standard way. This function will split the streetname and buildingnumber into two separate fields, and also handle additional streetname if it exists.
// 20260703 NTR - Added var_grp to db_select for companyID and updatedCompany to avoid conflicts with other modules that might use the same var_name in settings table.
// 20260703 NTR - Added filtering out lines with 0 amount and not just empty description, so if either is empty, the line will be skipped. This is to avoid sending empty lines to EasyUBL, which I suspect crashes their code.
// 20260706 CL/NTR - Changed the way headers are captured from EasyUBL responses, using CURLOPT_HEADERFUNCTION instead of splitting the header string, which can mis-split on HTTP/2 responses. This should improve reliability of header capture.
// 20260722 NTR - Changed logic of antal 0 lines, from dropping the lines to changing their antal to 1 as well as changing price to 0, to try and avoid errors.
// 20260825 NTR - Set isAllowanceCharge based on price sign (negative price indicates allowance/charge)
// 20260825 CL/NTR - Added 0184 (Danish CVR) to the Peppol scheme map so an "0184:xxxxxxxx" EAN gets the DK prefix and correct country code instead of falling through unprefixed.
// &&       Also added "0088" as a swedish prefix.
// 20260914 Sawaneh    JOB-141: getInvoicesOrder() classifies EasyUBL's reply with
//                     easyubl_interpret_response() (includes/stdFunc/easyUblResponse.php) - the
//                     error branch looked for errorMessage/error, which EasyUBL never sends
//                     (it returns errNo/message), so real API errors showed as "Uventet svar".
//                     Every failure now logs one fakture-error file, sets ordrer.digital_status
//                     to SendFailed so the order shows it and a resend asks first, and reports
//                     through findtekst(); an HTTP 500 with a document is no longer a success.

// 20261001 CDX/LH Load the session-free EasyUBL functions while preserving the web bootstrap.

    @session_start();
    $s_id=session_id();
    include(__DIR__ . '/../includes/connect.php');
    include(__DIR__ . '/../includes/var2str.php');

    // Getting the api key and tenant id from the database
    $query = db_select("SELECT var_value FROM settings WHERE var_name = 'apiKey' AND var_grp = 'easyUBL'", __FILE__ . " linje " . __LINE__);
    $res = db_fetch_array($query);
    $apiKey = $res["var_value"];
    //echo $apiKey . "<br>";

    include(__DIR__ . '/../includes/online.php');
    include(__DIR__ . '/../includes/forfaldsdag.php');
    include_once __DIR__ . '/../includes/stdFunc/easyUblResponse.php';

require_once __DIR__ . '/../includes/easyUbl/easyUblInvoice.php';
