<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- debitor/anonymize.php --- ver 5.1.1 --- 2026-11-05 ---
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
// Copyright (c) 2021 - 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20230223 PHR added fax
// 20261105 CL/LH Anonymisér also blanks adresser.lev_tlf/lev_email and ordrer.lev_email/kontakt_tlf, and deletes the account's delivery_addresses rows.
//                One transaction, and the posted id is cast.
//
//
// The confirmation promises that name, address and phone go from the card and from every order (rykkere are ordrer
// rows too) and that the contacts are deleted. The delivery addresses are the card's too: debitorkort.php keeps the
// primary one in adresser.lev_* and the others in delivery_addresses, and the order copies lev_email and the
// contact's phone (kontakt_tlf). db_modify() exits on a failed write, so the transaction leaves either all of it
// done or nothing.
$anonymizeId = (int)$id;
transaktion('begin');
$qtxt = "update ordrer set firmanavn = 'Anonym', addr1 = '', addr2 = '', bynavn='', postnr='', kontakt = '', phone ='', ";
$qtxt.= "kontakt_tlf = '', email='', lev_navn = '', lev_addr1 = '', lev_addr2 = '', lev_bynavn='', lev_postnr='', ";
$qtxt.= "lev_kontakt ='', lev_email = '' ";
$qtxt.= "where konto_id = '$anonymizeId'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
$qtxt = "update adresser set firmanavn = 'Anonym', fornavn = '', efternavn = '', addr1 = '', addr2 = '', bynavn='', postnr='', ";
$qtxt.= "email='', kontakt = '', tlf ='', mobile ='', lev_firmanavn = '', lev_fornavn = '', lev_efternavn = '', ";
$qtxt.= "lev_addr1 = '', lev_addr2 = '', lev_bynavn='', lev_postnr='', lev_kontakt ='', lev_tlf = '', lev_email = '', ";
$qtxt.= "bank_konto='' ";
$qtxt.= "where id = '$anonymizeId'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
$qtxt = "delete from ansatte where konto_id = '$anonymizeId'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
$qtxt = "delete from kontakt_emails where konto_id = '$anonymizeId'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
$qtxt = "delete from delivery_addresses where account_id = '$anonymizeId'";
db_modify($qtxt,__FILE__ . " linje " . __LINE__);
transaktion('commit');

?>
