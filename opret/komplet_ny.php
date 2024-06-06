<?php
  include '../includes/connect.php';
  ini_set("include_path", ".:../phpmailer");
  require("class.phpmailer.php");

  header("Access-Control-Allow-Origin: http://ssl9.saldi.dk");
  // get data from the request
  $data = json_decode(file_get_contents('php://input'), true);

  file_put_contents("../temp/data.txt", print_r($data, true));

  $posteringer = $data["posteringer"];
if($data["id"] == 1 && $data["radio"] == "m"){
    $pris = 99;
}else if($data["id"] == 1 && $data["radio"] == "aa"){
    $pris = 1188;
}else if($data["id"] == 2 && $data["radio"] == "m"){
    $pris = 149;
}else if($data["id"] == 2 && $data["radio"] == "aa"){
    $pris = 1788;
    $varenr = "S100";
}else{
    exit;
}

$merePris = 0;

$postArr = [
    0,
    50,
    100,
    150,
    200,
    250,
    300,
    350,
    400,
    450,
    475,
    500,
    525,
    550,
    575,
    600,
    625,
    650,
    675,
    700,
    725,
    750,
    775,
    800,
    825
];

if($posteringer > 1000){
  if($data["radio"] == "m"){
    $index = $posteringer/1000;
    $merePris = $postArr[$index-1];
  }else if($data["radio"] == "aa"){
    $index = $posteringer/1000;
    $merePris = $postArr[$index-1];
    $merePris = $merePris*12;
  }
}

$ordreNr = rand(100000, 999999);
$amount = $pris+$merePris;

  // check if regnskab with the given name already exists
  $query = db_select("SELECT id FROM regnskab WHERE regnskab = '$data[regnskabsNavn]'", __FILE__ . " linje " . __LINE__);

  if(db_num_rows($query) > 0){
    $randomNumber = rand(1, 100);
    $data["regnskabsNavn"] = $data["regnskabsNavn"] . " ($randomNumber)";
  }

  if($data["id"] == "2"){
    $ordreLinjer = [
      "1" => "Alle funktioner",
      "2" => "Posteringer pr. måned $data[posteringer]",
      "3" => "Support via mail og telefon"
    ];
  }else{
    $ordreLinjer = [
      "1" => "Kun bogføring & fakturering",
      "2" => "Ubegrænset posteringer",
      "3" => "Support"
    ];
  }

  $date = date("Y-m-d");

  $message = `
  <!DOCTYPE html>

  <html lang="en" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:v="urn:schemas-microsoft-com:vml">
  <head>
  <title></title>
  <meta content="text/html; charset=utf-8" http-equiv="Content-Type"/>
  <meta content="width=device-width, initial-scale=1.0" name="viewport"/><!--[if mso]><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch><o:AllowPNG/></o:OfficeDocumentSettings></xml><![endif]--><!--[if !mso]><!-->
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@100;200;300;400;500;600;700;800;900" rel="stylesheet" type="text/css"/><!--<![endif]-->
  <style>
      * {
        box-sizing: border-box;
      }
  
      body {
        margin: 0;
        padding: 0;
      }
  
      a[x-apple-data-detectors] {
        color: inherit !important;
        text-decoration: inherit !important;
      }
  
      #MessageViewBody a {
        color: inherit;
        text-decoration: none;
      }
  
      p {
        line-height: inherit
      }
  
      .desktop_hide,
      .desktop_hide table {
        mso-hide: all;
        display: none;
        max-height: 0px;
        overflow: hidden;
      }
  
      .image_block img+div {
        display: none;
      }
  
      @media (max-width:640px) {
        .desktop_hide table.icons-inner {
          display: inline-block !important;
        }
  
        .icons-inner {
          text-align: center;
        }
  
        .icons-inner td {
          margin: 0 auto;
        }
  
        .mobile_hide {
          display: none;
        }
  
        .row-content {
          width: 100% !important;
        }
  
        .stack .column {
          width: 100%;
          display: block;
        }
  
        .mobile_hide {
          min-height: 0;
          max-height: 0;
          max-width: 0;
          overflow: hidden;
          font-size: 0px;
        }
  
        .desktop_hide,
        .desktop_hide table {
          display: table !important;
          max-height: none !important;
        }
      }
    </style>
  </head>
  <body style="background-color: #DFDFDF; margin: 0; padding: 0; -webkit-text-size-adjust: none; text-size-adjust: none;">
  <table border="0" cellpadding="0" cellspacing="0" class="nl-container" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #DFDFDF;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="image_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad" style="width:100%;">
  <div align="center" class="alignment" style="line-height:10px">
  <div style="max-width: 620px;"><img alt="Image" src="images/top_rounded_15.png" style="display: block; height: auto; border: 0; width: 100%;" title="Image" width="620"/></div>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/hero_bg_2.jpg'); background-position: top center; background-repeat: no-repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-left:60px;padding-right:60px;padding-top:60px;">
  <div style="color:#FFFFFF;font-family:'Oswald','Lucida Sans Unicode','Lucida Grande',sans-serif;font-size:34px;line-height:180%;text-align:center;mso-line-height-alt:61.2px;">
  <p style="margin: 0; word-break: break-word;"><span><span>Velkommen </span><strong><span><span style="background-color: #ffffff;"><span style="color: #003300; background-color: #ffffff;"> <span style="color: #2566ff;">John Doe</span> </span></span>, </span></strong></span></p>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-left:60px;padding-right:60px;">
  <div style="color:#FFFFFF;font-family:'Oswald','Lucida Sans Unicode','Lucida Grande',sans-serif;font-size:24px;line-height:180%;text-align:center;mso-line-height-alt:43.2px;">
  <p style="margin: 0; word-break: break-word;"><span><span>TAK FORDI DU VALGTE SALDI!</span></span></p>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-3" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-left:60px;padding-right:60px;">
  <div style="color:#FFFFFF;font-family:'Oswald','Lucida Sans Unicode','Lucida Grande',sans-serif;font-size:14px;line-height:180%;text-align:center;mso-line-height-alt:25.2px;">
  <p style="margin: 0; word-break: break-word;"><br/></p>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="0" cellspacing="0" class="image_block block-4 mobile_hide" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad" style="width:100%;">
  <div align="center" class="alignment" style="line-height:10px">
  <div style="max-width: 64px;"><img alt="Image" src="images/smile.png" style="display: block; height: auto; border: 0; width: 100%;" title="Image" width="64"/></div>
  </div>
  </td>
  </tr>
  </table>
  <div class="spacer_block block-5 mobile_hide" style="height:125px;line-height:125px;font-size:1px;"> </div>
  <div class="spacer_block block-6 mobile_hide" style="height:40px;line-height:40px;font-size:1px;"> </div>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-3" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #ebebeb; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; border-right: 1px solid #DFDFDF; padding-bottom: 10px; padding-top: 20px; vertical-align: top; border-top: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="0" cellspacing="0" class="image_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad" style="width:100%;">
  <div align="center" class="alignment" style="line-height:10px">
  <div style="max-width: 27px;"><img alt="Image" src="images/barcode.png" style="display: block; height: auto; border: 0; width: 100%;" title="Image" width="27"/></div>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="10" cellspacing="0" class="text_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="font-family: sans-serif">
  <div class="" style="font-size: 12px; font-family: 'Oxygen', 'Trebuchet MS', Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #555555; line-height: 1.2;">
  <p style="margin: 0; font-size: 14px; text-align: center; mso-line-height-alt: 16.8px;"><span style="color:#999999;font-size:14px;">Ordre Nr:</span> <strong>$ordreNr</strong></p>
  </div>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-2" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; border-right: 1px solid #DFDFDF; padding-bottom: 10px; padding-top: 15px; vertical-align: top; border-top: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="0" cellspacing="0" class="image_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad" style="width:100%;">
  <div align="center" class="alignment" style="line-height:10px">
  <div style="max-width: 27px;"><img alt="Image" src="images/calendar.png" style="display: block; height: auto; border: 0; width: 100%;" title="Image" width="27"/></div>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="10" cellspacing="0" class="text_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="font-family: sans-serif">
  <div class="" style="font-size: 12px; font-family: 'Oxygen', 'Trebuchet MS', Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #555555; line-height: 1.2;">
  <p style="margin: 0; font-size: 14px; text-align: center; mso-line-height-alt: 16.8px;"><span style="color:#999999;font-size:14px;">Ordre Dato:</span> <strong>$date</strong></p>
  </div>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-3" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 15px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="0" cellspacing="0" class="image_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad" style="width:100%;">
  <div align="center" class="alignment" style="line-height:10px">
  <div style="max-width: 14px;"><img alt="Image" src="images/dollar.png" style="display: block; height: auto; border: 0; width: 100%;" title="Image" width="14"/></div>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="10" cellspacing="0" class="text_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="font-family: sans-serif">
  <div class="" style="font-size: 12px; font-family: 'Oxygen', 'Trebuchet MS', Helvetica, sans-serif; mso-line-height-alt: 14.399999999999999px; color: #555555; line-height: 1.2;">
  <p style="margin: 0; font-size: 14px; text-align: center; mso-line-height-alt: 16.8px;"><span style="color:#999999;font-size:14px;">Total:</span> <strong>$amount kr.</strong></p>
  </div>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-4" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-bottom:5px;padding-left:40px;padding-right:40px;padding-top:15px;">
  <div style="color:#2566ff;font-family:'Oswald','Lucida Sans Unicode','Lucida Grande',sans-serif;font-size:24px;line-height:150%;text-align:center;mso-line-height-alt:36px;">
  <p style="margin: 0;">Din Ordre</p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-5" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #2566ff; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; border-right: 1px solid #DFDFDF; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="10" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#FFFFFF;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:13px;line-height:120%;text-align:center;mso-line-height-alt:15.6px;">
  <p style="margin: 0; word-break: break-word;"><span><strong>Beskrivelse</strong></span></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-3" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="10" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#FFFFFF;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:13px;line-height:120%;text-align:center;mso-line-height-alt:15.6px;">
  <p style="margin: 0; word-break: break-word;"><span><strong>Total</strong></span></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-6" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; border-right: 1px solid #DFDFDF; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="5" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:center;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;"><span style="color:rgb(153,153,153);">$ordreLinjer[0]</span></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-3" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="10" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:center;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;"><strong>? kr</strong></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-7" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #F5F5F5; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #F5F5F5; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="center" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span> </span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-8" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #F5F5F5; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; border-right: 1px solid #DFDFDF; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="5" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:center;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;"><span style="color:rgb(153,153,153);">$ordreLinjer[1]</span></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-3" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="10" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:center;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;"><strong>? kr.</strong></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-9" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="left" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span></span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-10" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; border-right: 1px solid #DFDFDF; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="5" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:center;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;"><span style="color:rgb(153,153,153);">$ordreLinjer[2]</span></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-3" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="10" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:center;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;"><strong>? kr.</strong></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-11" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #F5F5F5; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #F5F5F5; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="center" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span> </span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-13" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="left" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span> </span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-14" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-top: 15px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-left:25px;padding-top:5px;">
  <div style="color:#000;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:14px;line-height:120%;text-align:left;mso-line-height-alt:16.8px;">
  <p style="margin: 0; word-break: break-word;">Betalings metode:</p>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-left:25px;">
  <div style="color:#000;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:20px;line-height:120%;text-align:left;mso-line-height-alt:24px;">
  <p style="margin: 0;">Kreditkort</p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  <td class="column column-2" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="33.333333333333336%">
  <div class="spacer_block block-1" style="height:20px;line-height:20px;font-size:1px;"> </div>
  </td>
  <td class="column column-3" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; background-color: #2566ff; border-left: 1px solid #DFDFDF; padding-bottom: 5px; padding-top: 10px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px;" width="33.333333333333336%">
  <div class="spacer_block block-1" style="height:20px;line-height:20px;font-size:1px;"> </div>
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-bottom:25px;padding-left:10px;padding-right:10px;">
  <div style="color:#FFFFFF;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:18px;line-height:120%;text-align:center;mso-line-height-alt:21.599999999999998px;">
  <p style="margin: 0; word-break: break-word;"><span><strong>$amount kr.</strong></span></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-15" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 20px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-bottom:5px;padding-left:40px;padding-right:40px;padding-top:15px;">
  <div style="color:#000;font-family:'Oswald','Lucida Sans Unicode','Lucida Grande',sans-serif;font-size:24px;line-height:150%;text-align:center;mso-line-height-alt:36px;">
  <p style="margin: 0;">Hvad nu?</p>
  </div>
  </td>
  </tr>
  </table>
  <table border="0" cellpadding="0" cellspacing="0" class="paragraph_block block-2" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad" style="padding-bottom:15px;padding-left:40px;padding-right:40px;">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:12px;line-height:150%;text-align:center;mso-line-height-alt:18px;">
  <p style="margin: 0;">Du vil modtage en email med link til oprettelse af dit regnsakb.</p>
  <p style="margin: 0;">Hvis du ikke har modtaget den email inden for en time så ring på <a data-brz-link-type="external" href="tel:46902208" style="text-decoration: underline; color: #0068A5;">+45 46 90 22 08</a></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-16" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 20px; padding-right: 20px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="left" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span> </span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-17" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 20px; padding-right: 20px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="left" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span> </span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-18" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-left: 20px; padding-right: 20px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="divider_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad">
  <div align="left" class="alignment">
  <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="divider_inner" style="font-size: 1px; line-height: 1px; border-top: 1px solid #DFDFDF;"><span> </span></td>
  </tr>
  </table>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-19" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #FFFFFF; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; padding-top: 10px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <div class="spacer_block block-1" style="height:50px;line-height:50px;font-size:1px;"> </div>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-20" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 5px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="0" cellspacing="0" class="image_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt;" width="100%">
  <tr>
  <td class="pad" style="width:100%;">
  <div align="center" class="alignment" style="line-height:10px">
  <div style="max-width: 620px;"><img alt="Image" src="images/bottom_rounded_15.png" style="display: block; height: auto; border: 0; width: 100%;" title="Image" width="620"/></div>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-21" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #F0F0F0; background-image: url('images/groovepaper_1.png'); background-position: top center; background-repeat: repeat;" width="100%">
  <tbody>
  <tr>
  <td>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row-content stack" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; color: #000000; width: 620px; margin: 0 auto;" width="620">
  <tbody>
  <tr>
  <td class="column column-1" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; font-weight: 400; text-align: left; padding-bottom: 15px; padding-top: 15px; vertical-align: top; border-top: 0px; border-right: 0px; border-bottom: 0px; border-left: 0px;" width="100%">
  <table border="0" cellpadding="10" cellspacing="0" class="paragraph_block block-1" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; word-break: break-word;" width="100%">
  <tr>
  <td class="pad">
  <div style="color:#555555;font-family:'Oxygen', 'Trebuchet MS', Helvetica, sans-serif;font-size:12px;line-height:120%;text-align:center;mso-line-height-alt:14.399999999999999px;">
  <p style="margin: 0; word-break: break-word;"><strong>Saldi © All rights reserved</strong></p>
  <p style="margin: 0; word-break: break-word;"><a href="saldi.dk" rel="noopener" style="text-decoration: underline; color: #56B500;" target="_blank">Saldi.dk</a></p>
  </div>
  </td>
  </tr>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table>
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="row row-22" role="presentation" style="mso-table-lspace: 0pt; mso-table-rspace: 0pt; background-color: #ffffff;" width="100%">
  <tbody>
  <tr>
  <td>
  </td>
  </tr>
  </tbody>
  </table>
  </td>
  </tr>
  </tbody>
  </table><!-- End -->
  </body>
  </html>`;

    // send mail
    $mail = new PHPMailer();
    $mail->IsSMTP();                                   // send via SMTP
    $mail->Host  = "localhost"; // SMTP servers
    $mail->SMTPAuth = false;     // turn on SMTP authentication
    $afsendermail='phr@saldi.dk';
    $afsendernavn='SALDI ';
  
    $mail->SetFrom($afsendermail,$afsendernavn);
  #		$mail->From  = $afsendermail;
  #		$mail->FromName = $afsendernavn;
    $mail->AddAddress($data["email"]);
    $mail->AddBCC('phr@saldi.dk');
    $mail->Subject  =  "Bestilling af SALDI-Abonnement";
    $mail->Body     =  $message;
  
    if(!$mail->Send()){
      echo "Fejl i afsendelse til $to<p>";
        echo "Mailer Error: " . $mail->ErrorInfo;
       exit;
    }                                   // send via SMTP

  // setup email
  $posteringer = $data["posteringer"];
  $brugerantal='1';
  $kontrol_id="";
  srand((double)microtime()*1000000);
  $chars = 'ABCDEFGHIJKLNMOPQRSTUVXYabcdefghijklmnopqrstuvwxyz0123456789';
  for ($i=0; $i<3; $i++) $kontrol_id.= $chars[rand(0, strlen($chars)-1)];
  $kontrol_id.=date("U");
  for ($i=0; $i<3; $i++) $kontrol_id.= $chars[rand(0, strlen($chars)-1)];
  $kontrol_id=$kontrol_id.":".$brugerantal.":".$posteringer;
  $linkadresse="https://https://ssl5.saldi.dk/finans/opret/komplet.php?kontrol_id=".$kontrol_id;
  $message = "Tak for din bestilling af ";
  $fakt_interval = "månedligt";
  $tmp=substr($fakt_interval,0,3);

  $message .= "\nKlik på nedenstående link for at bekræfte bestillingen og verificere\noprettelsen af dit regnskab:\n$linkadresse\n\n";
#		$message .= "Linket er gyldigt i 7 dage.\n\n";
  $message .= "Tilmeldingsoplysninger:\n";
  $message .= "Navn:       $data[navn]\n";
  $message .= "Firma:      $data[firma]\n";
  $message .= "CVR-nr.:    $data[CVR]\n";
  $message .= "Adresse:    $data[adresse]\n";
  $message .= "\nPostnr/By:  $data[postnr] $data[by]\n";
  $message .= "Telefon:    $data[tlf]\n";
  $message .= "E-mail:     $data[email]\n";
  $message .= "Regnskab:   $data[regnskabsNavn]\n\n";
  $message .= "Din email og telefonnummer anvendes som hhv. brugernavn og adgangskode ved første login.\n"; 
  $message .= "Du opfordres til at ændre brugernavn og især adgangskode under Indstillinger -> Brugere.\n";
  $message .= "Her kan du også tilføje flere brugere.\n";
  if ($data["andet"]) $message .= "Eventuelt: $data[andet]\n";
  $message .= "\nBemærk at navnet på regnskabet skal skrives nøjagtigt som angivet. \nDer skelnes mellem store og små bogstaver.\n";
  $message .= "Ved første login kommer du direkte ind i oprettelse af 1. regnskabsår.\nDette SKAL oprettes inden regnskabet kan bruges.\n";
  $tmp = urlencode($data["regnskabsNavn"]);
  $message .= "Herefter kan du finde dit regnskab på:\nhttp://www.saldi.dk/finans?regnskab=$tmp \n\n";
  $message .= "På http://forum.saldi.dk kan der findes svar på de fleste spørgsmål.\n";
#		$message .= "Bemærk at forummet er beskyttet med adgangskode - Skriv Saldi (med stort S) som både brugernavn og adgangskode.\n";
  $message .= "Brugervejledningen kan findes her:  http://www.saldi.dk/dok/index.htm\n";
  #$message .= "og du kan finde en videomanual her: http://flash.saldi.dk \n";
  $message .= "Som SALDI-kunde har du adgang til hotline og ubegrænset support pr. email. Benyt mailadressen support@saldi.dk.\n";
  $message .= "Har du i øvrigt spørgsmål, eller hvis der er andet, vi kan hjælpe med, så ring blot på telefon 4690 2208.\n\n";
  $message .= "Velkommen til og god fornøjelse\n\n";
  $message .= "Med venlig hilsen\n";
  $message .= "Saldi.dk ApS\n";
  
  // send mail
  $mail = new PHPMailer();
  $mail->IsSMTP();                                   // send via SMTP
  $mail->Host  = "localhost"; // SMTP servers
  $mail->SMTPAuth = false;     // turn on SMTP authentication
  $afsendermail='phr@saldi.dk';
  $afsendernavn='SALDI ';

  $mail->SetFrom($afsendermail,$afsendernavn);
#		$mail->From  = $afsendermail;
#		$mail->FromName = $afsendernavn;
  $mail->AddAddress($data["email"]);
  $mail->AddBCC('phr@saldi.dk');
  $mail->Subject  =  "Bestilling af SALDI-Abonnement";
  $mail->Body     =  $message;

  if(!$mail->Send()){
    echo "Fejl i afsendelse til $to<p>";
      echo "Mailer Error: " . $mail->ErrorInfo;
     exit;
  }


  // create new regnskab
  $dd=date("Y-m-d");
  $query = db_modify("INSERT INTO kundedata 
  (firmanavn,addr1,postnr,bynavn,kontakt,tlf,email,cvrnr,regnskab,brugernavn,kodeord,kontrol_id,aktiv,oprettet)
  VALUES ('".db_escape_string($data["firma"])."', '".db_escape_string($data["adresse"])."', '".db_escape_string($data["postnr"])."', '".db_escape_string($data["by"])."', 
  '".db_escape_string($data["navn"])."', '".db_escape_string($data["tlf"])."', '".db_escape_string($data["email"])."', '".db_escape_string($data["CVR"])."', '".db_escape_string($data["regnskabsNavn"])."'
  , '".db_escape_string($data["email"])."', '".db_escape_string($data["tlf"])."', '".db_escape_string($kontrol_id)."',0,'$dd')", __FILE__ . " linje " . __LINE__);

  // connect to saldi_6 with pdo
  /* $pdo = new PDO("mysql:host=$sqhost;dbname=saldi_6", $squser, $sqpass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt = $pdo->prepare("INSERT INTO adresser (firmanavn, addr1, postnr, bynavn, kontakt, tlf, email, cvrnr, regnskab, brugernavn, kodeord, kontrol_id, aktiv, oprettet) VALUES (:firmanavn, :addr1, :postnr, :bynavn, :kontakt, :tlf, :email, :cvrnr, :regnskab, :brugernavn, :kodeord, :kontrol_id, 0, :oprettet)");
  $stmt->execute(array(
    ':firmanavn' => $data["firma"],
    ':addr1' => $data["adresse"],
    ':postnr' => $data["postnr"],
    ':bynavn' => $data["by"],
    ':kontakt' => $data["navn"],
    ':tlf' => $data["tlf"],
    ':email' => $data["email"],
    ':cvrnr' => $data["CVR"],
    ':regnskab' => $data["regnskabsNavn"],
    ':brugernavn' => $data["email"],
    ':kodeord' => $data["tlf"],
    ':kontrol_id' => $kontrol_id,
    ':oprettet' => $dd
  ));

  $kontoId = $pdo->lastInsertId();
  $stmt = $pdo->prepare("SELECT kontonr FROM adresser WHERE id = $kontoId ORDER BY kontonr DESC LIMIT 1");
  $stmt->execute();
  $kontoNr = $stmt->fetch()["kontonr"];
  $stmt = $pdo->prepare("SELECT ordrenr FROM ordrer WHERE art LIKE 'D%' ORDER BY ordrenr DESC LIMIT 1");
  $stmt->execute();
  $row = $stmt->fetch();
  $ordrenr = $row["ordrenr"] + 1;
  $date = date("Y-m-d");
  $datePM = date("Y-m-d", strtotime("+1 month"));
  $moms = $amount*0.2;
  $stmt = $pdo->prepare("INSERT INTO ordrer (firmanavn, addr1, postnr, bynavn, email, betalingsdage, kontonr, art, valuta, ordredate, levdate, fakturadate, ordrenr, sum, status, konto_id, momssats, nextfakt, moms) VALUES (:firmanavn, :addr1, :postnr, :bynavn, :email, 1, :kontonr, 'D', 'DKK', :ordredate, :levdate, :fakturadate, :ordrenr, :sum, 1, :konto_id, 25, :nextfakt, :moms)");
  $stmt->execute(array(
    ':firmanavn' => $data["firma"],
    ':addr1' => $data["adresse"],
    ':postnr' => $data["postnr"],
    ':bynavn' => $data["by"],
    ':email' => $data["email"],
    ':kontonr' => $kontoNr,
    ':ordredate' => $date,
    ':levdate' => $date,
    ':fakturadate' => $date,
    ':ordrenr' => $ordrenr,
    ':sum' => $amount,
    ':konto_id' => $kontoId,
    ':nextfakt' => $datePM,
    ':moms' => $moms
  ));
  $ordreId = $pdo->lastInsertId();
  $stmt->$pdo->prepare("INSERT INTO "); */

  // return a response
  echo json_encode(["msg" => "<p>Tak for din tilmelding. Der er sendt en e-mail til $data[email] med instruktioner om, hvordan du opretter og aktiverer dit nye regnskab.</p>"]);

?>