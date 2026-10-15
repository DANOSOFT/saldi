<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/easyubl/DocumentFixture.php --- ver 5.0.0 --- 2026.10.02
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

final class DocumentFixture
{
    /** @return array{order_id:int,customer_id:int,recipient:string} */
    public static function seed($connection, int $status = 3, string $art = 'DO'): array
    {
        $recipient = 'document-' . bin2hex(random_bytes(6)) . '@example.invalid';
        $group = pg_fetch_assoc(pg_query($connection, "SELECT kodenr FROM grupper WHERE art='DG' ORDER BY kodenr LIMIT 1"));
        $customer = pg_fetch_assoc(pg_query_params($connection,
            "INSERT INTO adresser(kontonr,firmanavn,addr1,postnr,bynavn,email,art,gruppe,betalingsbet,betalingsdage,ean,cvrnr)
             VALUES(91901,'Document customer','Testvej 1','8000','Aarhus',$1,'D',$2,'Netto',8,'5790000000000','12345678') RETURNING id",
            [$recipient, (int)$group['kodenr']]));
        $customerId = (int)$customer['id'];
        $order = pg_fetch_assoc(pg_query_params($connection,
            "INSERT INTO ordrer(konto_id,kontonr,firmanavn,addr1,postnr,bynavn,email,art,ordredate,fakturadate,levdate,ordrenr,
                fakturanr,status,sum,moms,momssats,valuta,sprog,mail_fakt,udskriv_til,mail_subj,mail_text,betalingsbet,betalingsdage,ean,cvrnr)
             VALUES($1,91901,'Document customer','Testvej 1','8000','Aarhus',$2,$3,CURRENT_DATE,CURRENT_DATE,CURRENT_DATE,
                91901,'91901',$4,250,62.5,25,'DKK','dansk','on','email','Document subject','Document body','Netto',8,'5790000000000','12345678') RETURNING id",
            [$customerId, $recipient, $art, $status]));
        $orderId = (int)$order['id'];
        foreach (['ordrer' => $orderId, 'adresser' => $customerId] as $table => $rowId) {
            $columns = pg_query_params($connection,
                "SELECT column_name FROM information_schema.columns WHERE table_name=$1 AND data_type IN ('text','character varying','character')", [$table]);
            $assignments = [];
            while ($column = pg_fetch_assoc($columns)) {
                $identifier = pg_escape_identifier($connection, $column['column_name']);
                $assignments[] = "$identifier=COALESCE($identifier,'')";
            }
            pg_query($connection, 'UPDATE ' . pg_escape_identifier($connection, $table) . ' SET ' . implode(',', $assignments) . ' WHERE id=' . $rowId);
        }
        pg_query_params($connection,
            "INSERT INTO ordrelinjer(ordre_id,varenr,beskrivelse,enhed,posnr,antal,pris,rabat,momssats,procent)
             VALUES($1,'DOC','Document line','stk',1,2,125,0,25,100)", [$orderId]);
        if (str_starts_with($art, 'R')) {
            $form = 5 + (int)substr($art, 1);
            $fee = pg_fetch_assoc(pg_query($connection, "INSERT INTO varer(varenr,beskrivelse,salgspris,gruppe) VALUES('B5FEE','Dunning fee',100,1) RETURNING id"));
            pg_query_params($connection,
                "INSERT INTO formularer(formular,art,sprog,beskrivelse,xb,yb,color) VALUES($1,1,'dansk','GEBYR',$2,$2,'000000000')", [$form, $fee['id']]);
            $post = pg_fetch_assoc(pg_query_params($connection,
                "INSERT INTO openpost(konto_id,konto_nr,faktnr,amount,refnr,beskrivelse,udlignet,transdate,forfaldsdate,valuta,valutakurs)
                 VALUES($1,91901,'91901',312.5,$2,'Document invoice','',CURRENT_DATE-20,CURRENT_DATE-10,'DKK',100) RETURNING id",
                [$customerId, $orderId]));
            pg_query_params($connection, 'UPDATE ordrelinjer SET enhed=$1,antal=0,pris=0,momssats=0 WHERE ordre_id=$2', [$post['id'], $orderId]);
        }
        return ['order_id' => $orderId, 'customer_id' => $customerId, 'recipient' => $recipient];
    }

    public static function configureMail($connection): void
    {
        $host = getenv('SMTP_HOST');
        $port = getenv('SMTP_PORT');
        if (!$host || !$port) {
            throw new RuntimeException('Mailpit SMTP_HOST and SMTP_PORT are required');
        }
        pg_query_params($connection,
            "UPDATE adresser SET firmanavn='Document sender',email=$1,felt_1=$2,felt_2='',felt_3='',felt_4='' WHERE art='S'",
            [getenv('SMTP_FROM') ?: 'sender@example.invalid', $host . ':' . $port]);
        pg_query($connection, "UPDATE grupper SET box3='' WHERE art='PV' AND kodenr='1'");
    }

    /** @return array{messages:array,total:int} */
    public static function messages(string $recipient): array
    {
        $url = 'http://' . getenv('SMTP_HOST') . ':8025/api/v1/search?query=' . rawurlencode('to:' . $recipient);
        $response = file_get_contents($url);
        if ($response === false) {
            throw new RuntimeException('Mailpit API is unavailable');
        }
        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        $result['total'] = $result['messages_count'];
        return $result;
    }
}
