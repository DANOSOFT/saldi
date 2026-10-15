<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/fixtures/t2/InvoiceFixture.php --- ver 5.0.0 --- 2026.10.03
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

require_once __DIR__ . '/SalesFixture.php';

final class InvoiceFixture
{
    /** @return array{konto_id:int,product_id:int,order_id:int,year:int,line_id:int,batch_id:int} */
    public static function create($conn, callable $rows, bool $posted = true): array
    {
        $rows($conn, "CREATE TABLE IF NOT EXISTS moms_periode_luk (kalender_aar integer,kalender_maaned integer,status text)");
        $f = SalesFixture::create($conn, $rows);
        $year = $f['year'];
        $rows($conn, "UPDATE grupper SET box2='7900' WHERE art='DG' AND kodenr=7861");
        foreach ([7900,7901] as $account) {
            $rows($conn, 'INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar) SELECT $1,\'Invoice fixture\',\'D\',$2 WHERE NOT EXISTS (SELECT 1 FROM kontoplan WHERE kontonr=$1 AND regnskabsaar=$2)', [$account,$year]);
        }
        $rows($conn, "UPDATE grupper SET box5=$1 WHERE art='DIV' AND kodenr=3", [$posted ? 'on' : '']);
        $rows($conn, 'UPDATE ordrer SET status=1,levdate=CURRENT_DATE,fakturadate=CURRENT_DATE,sum=180,moms=45,valutakurs=100 WHERE id=$1', [$f['order_id']]);
        $f['line_id'] = SalesFixture::line($conn,$rows,$f,['leveres'=>2]);
        $f['batch_id'] = (int)$rows($conn, 'INSERT INTO batch_kob (ordre_id,linje_id,vare_id,antal,rest,lager,kobsdate,pris) VALUES (0,0,$1,20,20,1,CURRENT_DATE,30) RETURNING id', [$f['product_id']])[0]['id'];
        $rows($conn, 'INSERT INTO lagerstatus (vare_id,lager,beholdning) VALUES ($1,1,20)', [$f['product_id']]);
        return $f;
    }

    /** @return array{sales:array,reservations:array,stock:array,quantity:string} Exact stock state for one fixture. */
    public static function stock($conn, callable $rows, array $f): array
    {
        return ['sales'=>$rows($conn,'SELECT * FROM batch_salg WHERE ordre_id=$1 ORDER BY id',[$f['order_id']]),
            'reservations'=>$rows($conn,'SELECT * FROM reservation WHERE vare_id=$1 ORDER BY linje_id,batch_kob_id,batch_salg_id',[$f['product_id']]),
            'stock'=>$rows($conn,'SELECT * FROM lagerstatus WHERE vare_id=$1 ORDER BY lager',[$f['product_id']]),
            'quantity'=>$rows($conn,'SELECT beholdning FROM varer WHERE id=$1',[$f['product_id']])[0]['beholdning']];
    }
}
