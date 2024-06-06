<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/anonymize.php --- lap 4.0.8 --- 2023-02-23 ---
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
//
// Copyright (c) 2021 - 2023 saldi.dk aps
// ----------------------------------------------------------------------
// 20230223 PHR added fax

$qtxt = "update ordrer set firmanavn = 'Anonym', addr1 = '', addr2 = '', bynavn='', postnr='', kontakt = '', phone ='', ";
$qtxt.= "email='', lev_navn = '', lev_addr1 = '', lev_addr2 = '', lev_bynavn='', lev_postnr='', lev_kontakt ='' ";
$qtxt.= "where konto_id = '$id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
$qtxt = "update adresser set firmanavn = 'Anonym', fornavn = '', efternavn = '', addr1 = '', addr2 = '', bynavn='', postnr='', ";
$qtxt.= "email='', kontakt = '', tlf ='', fax ='', lev_firmanavn = '', lev_fornavn = '', lev_efternavn = '', ";
$qtxt.= "lev_addr1 = '', lev_addr2 = '', lev_bynavn='', lev_postnr='', lev_kontakt ='', bank_konto='' ";
$qtxt.= "where id = '$id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);  
$qtxt = "delete from ansatte where konto_id = '$id'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);  

?>
