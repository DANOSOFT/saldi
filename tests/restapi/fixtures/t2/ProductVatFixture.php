<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/fixtures/t2/ProductVatFixture.php --- ver 5.0.0 --- 2026.10.02
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
// 20261001 CDX/LH Add missing SM rate fixtures with VAT-inclusive prices.

final class ProductVatFixture
{
    /** @return array{product_id:int,order_id:int,year:int,department:int,caller_exemption:string,expected_rate:float,expected_exempt:string,expected_reverse:string} */
    public static function create($connection, callable $rows, int $year, string $scenario): array
    {
        $number = 7800 + array_search($scenario, self::scenarios(), true);
        $exempt = $scenario === 'group_exempt' ? 'on' : '';
        $reverse = in_array($scenario, ['reverse_customer', 'reverse_normal'], true) ? 'on' : '';
        $account = $scenario === 'no_code' ? 7802 : 7801;
        $department = $scenario === 'department' ? 7899 : 0;
        $rate = $scenario === 'department' ? 12.5 : 25;
        if (in_array($scenario, ['group_exempt', 'caller_exempt', 'no_code', 'missing_account'], true)) {
            $rate = 0;
        }
        $vatCode = $scenario === 'missing_account' ? 7802 : 7801;
        $vatAccount = $scenario === 'missing_account' ? '' : '7901';
        $rows($connection, "DELETE FROM grupper WHERE fiscal_year=$1 AND ((art='VG' AND kodenr=$2) OR (art='DG' AND kodenr=$2))", [$year, $number]);
        $rows($connection, "INSERT INTO grupper (art,kodenr,fiscal_year,beskrivelse,box4,box6,box7,box8)
            VALUES ('VG',$1,$2,'VAT fixture',$3,$4,$5,'')", [$number, $year, $account, $reverse, $exempt]);
        $rows($connection, "INSERT INTO grupper (art,kodenr,fiscal_year,beskrivelse,box9)
            VALUES ('DG',$1,$2,'VAT debtor',$3)", [$number, $year, $scenario === 'reverse_customer' ? 'on' : '']);
        $rows($connection, 'DELETE FROM kontoplan WHERE regnskabsaar=$1 AND kontonr IN (7801,7802)', [$year]);
        $rows($connection, "INSERT INTO kontoplan (kontonr,beskrivelse,kontotype,regnskabsaar,moms)
            VALUES (7801,'VAT revenue','D',$1,$2),(7802,'No VAT','D',$1,'')", [$year, 'S' . $vatCode]);
        $rows($connection, "DELETE FROM grupper WHERE art='SM' AND fiscal_year=$1 AND kodenr IN (7801,7802,7899)", [$year]);
        $rows($connection, "INSERT INTO grupper (art,kodenr,fiscal_year,beskrivelse,box1,box2)
            VALUES ('SM',$1,$2,'VAT fixture',$3,'25'),('SM',7899,$2,'Department','7901','12.5')", [$vatCode, $year, $vatAccount]);
        $rows($connection, "DELETE FROM settings WHERE var_name='maxDepVatRate_7899'");
        if ($department) {
            $rows($connection, "INSERT INTO settings (var_name,var_grp,var_value,user_id) VALUES ('maxDepVatRate_7899','fixture','0',0)");
        }
        $debtor = $rows($connection, "INSERT INTO adresser (art,kontonr,firmanavn,gruppe)
            VALUES ('D',$1,'VAT fixture customer',$2) RETURNING id", [$number, $number])[0];
        $product = $rows($connection, "INSERT INTO varer (varenr,varenr_alias,stregkode,beskrivelse,enhed,salgspris,kostpris,gruppe)
            VALUES ($1,$2,$3,'VAT fixture','stk',100,30,$4) RETURNING id", ['VAT-' . $number, 'ALIAS-' . $number, 'BAR-' . $number, $number])[0];
        $order = $rows($connection, "INSERT INTO ordrer (konto_id,art,status,momssats,valuta,ordredate,afd)
            VALUES ($1,'DO',1,25,'DKK',CURRENT_DATE,$2) RETURNING id", [$debtor['id'], $department])[0];
        return ['product_id' => (int)$product['id'], 'order_id' => (int)$order['id'], 'year' => $year,
            'department' => $department, 'caller_exemption' => $scenario === 'caller_exempt' ? 'on' : '',
            'expected_rate' => (float)$rate, 'expected_exempt' => $exempt ?: ($scenario === 'caller_exempt' ? 'on' : ''),
            'expected_reverse' => $scenario === 'reverse_customer' ? 'on' : ''];
    }

    /** @return list<string> */
    public static function scenarios(): array
    {
        return ['taxable', 'group_exempt', 'no_code', 'department', 'caller_exempt', 'reverse_customer', 'reverse_normal', 'missing_account'];
    }

    /** @return array{product_id:int,order_id:int,year:int,department:int,caller_exemption:string,expected_rate:float,expected_exempt:string,expected_reverse:string,price:int,incl_moms:int} */
    public static function missingRate($connection, callable $rows, int $year, ?string $rate, bool $department): array
    {
        $fixture = self::create($connection, $rows, $year, $department ? 'department' : 'taxable');
        $code = $department ? 7899 : 7801;
        if ($rate === null) {
            $rows($connection, "DELETE FROM grupper WHERE art='SM' AND fiscal_year=$1 AND kodenr=$2", [$year, $code]);
        } else {
            $rows($connection, "UPDATE grupper SET box2=$1 WHERE art='SM' AND fiscal_year=$2 AND kodenr=$3", [$rate, $year, $code]);
        }
        $fixture['price'] = 125;
        $fixture['incl_moms'] = 1;
        $fixture['expected_rate'] = $rate === '0.0' || $rate === null ? 0.0 : 25.0;
        return $fixture;
    }
}
