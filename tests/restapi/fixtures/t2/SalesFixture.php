<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/fixtures/t2/SalesFixture.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Seed the posting accounts produced by the legacy product VAT engine.

final class SalesFixture
{
    /** @return array{konto_id:int,product_id:int,order_id:int,year:int} */
    public static function create($conn, callable $rows): array
    {
        $rows($conn, "ALTER TABLE ordrer ADD COLUMN IF NOT EXISTS performed_by varchar(80)");
        $rows($conn, "ALTER TABLE ordrelinjer ALTER COLUMN samlevare TYPE varchar(20)");
        $year = (int)$rows($conn, "SELECT max(kodenr) AS year FROM grupper WHERE art='RA'")[0]['year'];
        $rows($conn, "DELETE FROM grupper WHERE art IN ('VG','DG','SM') AND kodenr='7861'");
        $rows($conn, "INSERT INTO grupper (art,kodenr,fiscal_year,box1,box2,box3,box4,box6,box8) VALUES
            ('VG',7861,$1,'','','','7861','','on'),('DG',7861,$1,'S1','','DKK','Dansk','',''),('SM',7861,$1,'7901','25','','','','')", [$year]);
        $rows($conn, "DELETE FROM kontoplan WHERE kontonr=7861 AND regnskabsaar=$1", [$year]);
        $rows($conn, "INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar,moms) VALUES (7861,'Sales fixture','D',$1,'S7861')", [$year]);
        $rows($conn, "UPDATE grupper SET box12='',box13='0'||chr(9)||'',box14='',box4='' WHERE art='DIV' AND kodenr=3");
        $rows($conn, "DELETE FROM settings WHERE var_name IN ('vatPrivateCustomers','vatBusinessCustomers','porto_varnr')");
        $rows($conn, "INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('vatPrivateCustomers','ordre','',0),('vatBusinessCustomers','ordre','',0)");
        $customer = $rows($conn, "INSERT INTO adresser (art,kontonr,firmanavn,gruppe,betalingsbet,betalingsdage,email,addr1,postnr,bynavn,tlf,kontotype)
            VALUES ('D','7861','Sales O''Neil',7861,'Netto',14,'sales@example.invalid','Testvej 1','1000','Testby','123456789012345','privat') RETURNING id")[0];
        $product = $rows($conn, "INSERT INTO varer (varenr,beskrivelse,enhed,salgspris,kostpris,gruppe,beholdning,min_lager) VALUES ('SALES-7861','Sales fixture','stk',100,30,7861,20,5) RETURNING id")[0];
        $order = $rows($conn, "INSERT INTO ordrer (konto_id,kontonr,firmanavn,art,status,momssats,valuta,ordredate,ordrenr,sprog,betalingsbet,betalingsdage,afd)
            VALUES ($1,'7861','Sales O''Neil','DO',0,25,'DKK',CURRENT_DATE,7861,'Dansk','Netto',14,0) RETURNING id", [$customer['id']])[0];
        return ['konto_id'=>(int)$customer['id'],'product_id'=>(int)$product['id'],'order_id'=>(int)$order['id'],'year'=>$year];
    }

    /** @return int Persist a legacy line, including fields the mobile model cannot express. */
    public static function line($conn, callable $rows, array $f, array $opts=[]): int
    {
        $values = array_merge(['ordre_id'=>$f['order_id'],'vare_id'=>$f['product_id'],'varenr'=>'SALES-7861','beskrivelse'=>'Fixture','enhed'=>'stk',
            'posnr'=>1,'antal'=>2,'pris'=>100,'kostpris'=>30,'rabat'=>10,'rabatart'=>'percent','procent'=>100,'momssats'=>25,'momsfri'=>'',
            'm_rabat'=>0,'rabatgruppe'=>0,'samlevare'=>'','folgevare'=>0,'leveret'=>0,'leveres'=>0,'lager'=>1,'saet'=>0,'variant_id'=>0,'kred_linje_id'=>0,
            'bogf_konto'=>7861,'vat_account'=>7901],$opts);
        return (int)$rows($conn, 'INSERT INTO ordrelinjer ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_map(static fn($i)=>'$'.$i, range(1,count($values)))).') RETURNING id',array_values($values))[0]['id'];
    }
}
