<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/LegacyStockFixture.php --- ver 5.0.0 --- 2026.10.06
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

/** Stock fixtures shared by the legacy characterization and concurrent writer tests. */
final class LegacyStockFixture
{
    /** @return array{item:int,order:int,line:int,variant:int,batches:list<int>} */
    public static function seed(callable $rows, int $quantity = 10, bool $status = true, bool $variant = false, bool $batch = false, int $warehouse = 2, string $art = 'DO'): array
    {
        $year = (int)$rows("SELECT max(kodenr) AS n FROM grupper WHERE art='RA'")[0]['n'];
        $group = $batch ? 98762 : 98761;
        $rows("INSERT INTO grupper(art,kodenr,fiscal_year,box1,box2,box3,box4,box8,box9)
            SELECT 'VG',$1,$2,'1000','2000','1000','2000','on',$3
            WHERE NOT EXISTS(SELECT 1 FROM grupper WHERE art='VG' AND kodenr=$1 AND fiscal_year=$2)", [$group,$year,$batch ? 'on' : '']);
        $item = (int)$rows("INSERT INTO varer(varenr,beskrivelse,gruppe,beholdning,kostpris,salgspris,samlevare,serienr,lukket)
            VALUES($1,'Legacy stock fixture',$2,$3,4,8,'','','') RETURNING id", ['LS-'.bin2hex(random_bytes(4)),$group,$quantity])[0]['id'];
        $variantId = $variant ? (int)$rows("INSERT INTO variant_varer(vare_id,variant_id,variant_stregkode,variant_beholdning)
            VALUES($1,1,$2,$3) RETURNING id", [$item,'EAN'.$item,$quantity])[0]['id'] : 0;
        if ($status) {
            $rows('INSERT INTO lagerstatus(vare_id,variant_id,lager,beholdning) VALUES($1,$2,$3,$4)', [$item,$variantId,$warehouse,$quantity]);
        }
        $batches = [];
        foreach ($quantity > 0 ? [min(3,$quantity),max(0,$quantity-3)] : [] as $q) {
            if (!$q) {
                continue;
            }
            $batches[] = (int)$rows("INSERT INTO batch_kob(vare_id,variant_id,lager,antal,rest,pris,kobsdate,fakturadate,linje_id,ordre_id)
                VALUES($1,$2,$3,$4,$4,4,'2026-01-01','2026-01-01',0,0) RETURNING id", [$item,$variantId,$warehouse,$q])[0]['id'];
        }
        $order = (int)$rows("INSERT INTO ordrer(art,status,ordrenr,ordredate,levdate,fakturadate,afd,ref)
            VALUES($1,1,1,'2026-10-06','2026-10-06','2026-10-06',0,'') RETURNING id", [$art])[0]['id'];
        $line = (int)$rows("INSERT INTO ordrelinjer(ordre_id,vare_id,varenr,posnr,antal,leveres,leveret,pris,kostpris,rabat,variant_id,lager,serienr,folgevare,kred_linje_id,bogf_konto,fast_db)
            VALUES($1,$2,$3,1,5,5,0,8,4,0,$4,$5,'',0,0,0,0) RETURNING id", [$order,$item,'LS-'.$item,$variantId,$warehouse])[0]['id'];
        if ($batch) {
            foreach ($batches as $i=>$id) {
                $rows('INSERT INTO reservation(linje_id,vare_id,batch_kob_id,antal,lager) VALUES($1,$2,$3,$4,$5)', [$line,$item,$id,$i ? 2 : 3,$warehouse]);
            }
        }
        return ['item'=>$item,'order'=>$order,'line'=>$line,'variant'=>$variantId,'batches'=>$batches];
    }
}
