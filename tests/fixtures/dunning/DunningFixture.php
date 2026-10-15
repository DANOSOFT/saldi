<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/dunning/DunningFixture.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Add mixed-currency reminders with historical exchange rates.

final class DunningFixture
{
    /** @return array{konto_id:int,posts:array<int,int>,fee_id:int,interest_id:int,email:string} */
    public static function seed($connection, float $rate = 1): array
    {
        $group = pg_fetch_assoc(pg_query($connection, "SELECT kodenr FROM grupper WHERE art='DG' ORDER BY kodenr LIMIT 1"));
        $email = 'dunning-' . bin2hex(random_bytes(6)) . '@example.invalid';
        $customer = pg_fetch_assoc(pg_query_params($connection,
            "INSERT INTO adresser(kontonr,firmanavn,addr1,postnr,bynavn,art,gruppe,email,betalingsbet,betalingsdage)
             VALUES(91881,'Dunning customer','Testvej 2','8000','Aarhus','D',$1,$2,'Netto',8) RETURNING id", [$group['kodenr'],$email]));
        $kontoId = (int)$customer['id'];
        // The legacy page assumes card text columns have the web saver's empty-string defaults.
        $columns = pg_query($connection, "SELECT column_name FROM information_schema.columns WHERE table_name='adresser' AND data_type IN ('text','character varying','character')");
        $assignments = [];
        while ($row = pg_fetch_assoc($columns)) {
            $column = pg_escape_identifier($connection, $row['column_name']);
            $assignments[] = "$column=COALESCE($column,'')";
        }
        pg_query($connection, 'UPDATE adresser SET ' . implode(',', $assignments) . ' WHERE id=' . $kontoId);
        pg_query($connection, "UPDATE grupper SET box3='DKK',box4='dansk' WHERE art='DG' AND kodenr=" . (int)$group['kodenr']);
        pg_query($connection, "UPDATE grupper SET box5='8',box6='8',box7='8' WHERE art='DIV' AND kodenr=4");
        $fee = pg_fetch_assoc(pg_query($connection, "INSERT INTO varer(varenr,beskrivelse,salgspris,gruppe) VALUES('B2FEE','Dunning fee',100,1) RETURNING id"));
        $interest = pg_fetch_assoc(pg_query($connection, "INSERT INTO varer(varenr,beskrivelse,salgspris,gruppe) VALUES('B2INT','Dunning interest',0,1) RETURNING id"));
        pg_query($connection, "DELETE FROM formularer WHERE beskrivelse='GEBYR' AND formular IN (6,7,8)");
        foreach ([6,7,8] as $form) {
            pg_query_params($connection, "INSERT INTO formularer(formular,art,sprog,beskrivelse,xb,yb,str,color)
                VALUES($1,1,'dansk','GEBYR',$2,$3,$4,'000000000')", [$form,$fee['id'],$interest['id'],$rate]);
        }
        $posts = [];
        foreach ([1000,500] as $index => $amount) {
            $post = pg_fetch_assoc(pg_query_params($connection,
                "INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,forfaldsdate,valuta,valutakurs)
                 VALUES($1,91881,$2,$3,0,$4,'0',CURRENT_DATE-45,CURRENT_DATE-35,'DKK',100) RETURNING id",
                [$kontoId,'B2-' . $index,$amount,'Dunning invoice ' . $index]));
            $posts[] = (int)$post['id'];
        }
        return ['konto_id'=>$kontoId,'posts'=>$posts,'fee_id'=>(int)$fee['id'],'interest_id'=>(int)$interest['id'],'email'=>$email];
    }

    /** @return array{konto_id:int,posts:array<int,int>,fee_id:int,interest_id:int,email:string} */
    public static function mixedCurrency($connection): array
    {
        $fixture = self::seed($connection, 0);
        pg_query_params($connection, "UPDATE grupper SET box3='EUR' WHERE art='DG'
            AND kodenr::text=(SELECT gruppe::text FROM adresser WHERE id=$1)", [$fixture['konto_id']]);
        $group = pg_fetch_assoc(pg_query($connection, "SELECT kodenr FROM grupper WHERE art='VK' AND box1='EUR' ORDER BY id LIMIT 1"));
        if (!$group) {
            $group = pg_fetch_assoc(pg_query($connection, "INSERT INTO grupper(art,kodenr,box1)
                SELECT 'VK',COALESCE(MAX(kodenr),0)+1,'EUR' FROM grupper WHERE art='VK' RETURNING kodenr"));
        }
        pg_query_params($connection, 'DELETE FROM valuta WHERE gruppe=$1', [(int)$group['kodenr']]);
        foreach ([[-60,700],[-5,750],[5,800]] as [$days,$rate]) {
            pg_query_params($connection, 'INSERT INTO valuta(gruppe,valdate,kurs) VALUES($1,CURRENT_DATE+$2::integer,$3)',
                [(int)$group['kodenr'],$days,$rate]);
        }
        pg_query_params($connection, "UPDATE openpost SET valuta='EUR',valutakurs=800 WHERE id=$1", [$fixture['posts'][1]]);
        $credit = pg_fetch_assoc(pg_query_params($connection, "INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,refnr,
            beskrivelse,udlignet,transdate,forfaldsdate,valuta,valutakurs)
            VALUES($1,91881,'B2-credit',-7.123,0,'USD credit','0',CURRENT_DATE-45,CURRENT_DATE-35,'USD',630) RETURNING id",
            [$fixture['konto_id']]));
        $fixture['posts'][] = (int)$credit['id'];
        return $fixture;
    }

    /** Posted base rykker: advance copies both its invoice and its booked fee references. @return int */
    public static function base($connection, array $fixture, int $level = 1): int
    {
        $order = pg_fetch_assoc(pg_query_params($connection,
            "INSERT INTO ordrer(ordrenr,konto_id,kontonr,firmanavn,email,art,status,betalt,sum,valuta,valutakurs,
                ordredate,fakturadate,levdate,fakturanr,ref,kontakt,mail_fakt)
             VALUES(91881,$1,91881,'Dunning customer',$2,$3,2,'-',100,'DKK',100,CURRENT_DATE-20,CURRENT_DATE-20,CURRENT_DATE-20,$4,'','','') RETURNING id",
            [$fixture['konto_id'],$fixture['email'],'R'.$level,'91881-'.$level]));
        $id = (int)$order['id'];
        pg_query_params($connection, "INSERT INTO ordrelinjer(ordre_id,posnr,enhed,serienr,beskrivelse)
            VALUES($1,1,$2,(CURRENT_DATE-45)::text,'Dunning invoice 0')", [$id,$fixture['posts'][0]]);
        pg_query_params($connection, "INSERT INTO ordrelinjer(ordre_id,posnr,vare_id,varenr,beskrivelse,antal,pris)
            VALUES($1,2,$2,'B2FEE','Dunning fee',1,100)", [$id,$fixture['fee_id']]);
        pg_query_params($connection, "INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,forfaldsdate,valuta,valutakurs)
            VALUES($1,91881,$2,100,$3,'Previous dunning fee','0',CURRENT_DATE-20,CURRENT_DATE-12,'DKK',100)",
            [$fixture['konto_id'],'91881-'.$level,$id]);
        return $id;
    }
}
