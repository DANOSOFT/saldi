<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/mobilMarkenValg.php --- ver 5.0.0 --- 2026.10.06
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

/** @var int $regnaar Fiscal year from includes/online.php. */
/** @var string $db Tenant database from includes/online.php. */
require_once __DIR__ . '/mobilBetalingValg.php';
require_once __DIR__ . '/../../includes/employeeClaims/mileageRates.php';

/** Validate all fields before any write, using current admin rights and a tenant-bound CSRF token. @return int Translation result id. */
function mobilMarkenSave(array $input): int
{
    global $regnaar,$db;
    $expected = (string)ifset($_SESSION,'csrf_token','');
    $token = ifset($input,'csrf_token');
    if (!mobilBetalingAllowed('write') || $expected === '' || !is_string($token) || !hash_equals($expected,$token)
        || ifset($_SESSION,'mobile_marken_settings_db') !== $db) {
        return 5498;
    }
    $year = ifset($input,'year');
    if (!is_string($year) || !preg_match('/\A[1-9][0-9]{3}\z/',$year)) { return 5498; }
    $values = [];
    foreach (['claims_payable_account'=>"'S'",'claims_mileage_account'=>"'D','S'"] as $name=>$types) {
        $value = ifset($input,$name,'');
        if (!is_string($value) || ($value !== '' && (!preg_match('/\A[1-9][0-9]{0,8}\z/',$value)
            || !db_fetch_array(db_select('SELECT id FROM kontoplan WHERE kontonr='.(int)$value.' AND regnskabsaar='.(int)$regnaar
                ." AND kontotype IN ($types) AND COALESCE(trim(lukket),'')=''",__FILE__))))) {
            return 5498;
        }
        $values[$name] = $value;
    }
    try {
        $high = ifset($input,'km_rate','');
        $low = ifset($input,'km_rate_low','');
        if ($high === '' && $low === '') {
            $values['km_rate_'.$year] = $values['km_rate_low_'.$year] = '';
        } else {
            $h = mileage_units($high,2,100000);
            $l = mileage_units($low,2,100000);
            if ($h <= 0 || $l > $h) { return 5498; }
            $values['km_rate_'.$year] = mileage_decimal($h,2);
            $values['km_rate_low_'.$year] = mileage_decimal($l,2);
        }
        $threshold = mileage_units(ifset($input,'km_threshold','20000'),1,100000000);
        $values['km_threshold_'.$year] = mileage_decimal($threshold,1);
    } catch (MobileT2Error $error) { return 5498; }
    foreach (['booking_day_start'=>'07:00','booking_day_end'=>'17:00'] as $name=>$default) {
        $value = ifset($input,$name,$default);
        if (!is_string($value) || !preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/',$value)) { return 5498; }
        $values[$name] = $value;
    }
    $slot = ifset($input,'booking_slot_minutes','60');
    if ($values['booking_day_start'] >= $values['booking_day_end'] || !is_string($slot)
        || !preg_match('/\A[1-9][0-9]{0,2}\z/',$slot) || (int)$slot > 480) { return 5498; }
    $values['booking_slot_minutes'] = $slot;
    transaktion('begin');
    db_select("SELECT pg_advisory_xact_lock(hashtext('saldi:mobile_marken_settings'))",__FILE__);
    foreach ($values as $name=>$value) {
        $name = db_escape_string($name);
        $value = db_escape_string($value);
        $row = db_fetch_array(db_select("SELECT id FROM settings WHERE var_grp='mobile_marken' AND user_id=0 AND var_name='$name' ORDER BY id DESC LIMIT 1",__FILE__));
        if ($row) {
            db_modify("UPDATE settings SET var_value='$value' WHERE var_grp='mobile_marken' AND user_id=0 AND var_name='$name'",__FILE__);
        } else {
            db_modify("INSERT INTO settings(var_grp,var_name,var_value,var_description,user_id) VALUES ('mobile_marken','$name','$value','Mobile field configuration',0)",__FILE__);
        }
    }
    if (!empty($GLOBALS['db_modify_fejl'])) { transaktion('rollback'); return 5498; }
    transaktion('commit');
    return 5499;
}

/** Render only; POST handling and 303 redirect live before the page layout. */
function mobilMarkenValg(): void
{
    global $regnaar,$sprog_id,$db;
    $escape = static fn(string $value): string => htmlspecialchars($value,ENT_QUOTES,'UTF-8');
    $label = static fn(int $id,string $fallback): string => $escape(findtekst($id.'|'.$fallback,$sprog_id));
    if (!mobilBetalingAllowed('read')) {
        http_response_code(403);
        print $label(5498,'Ingen adgang eller ugyldige indstillinger');
        return;
    }
    $_SESSION['mobile_marken_settings_db'] = $db;
    $year = ifset($_GET,'year',date('Y'));
    if (!is_string($year) || !preg_match('/\A[1-9][0-9]{3}\z/',$year)) { $year = date('Y'); }
    $values = ['claims_payable_account'=>'','claims_mileage_account'=>'','km_rate_'.$year=>'',
        'km_rate_low_'.$year=>'','km_threshold_'.$year=>'20000','booking_day_start'=>'07:00','booking_day_end'=>'17:00','booking_slot_minutes'=>'60'];
    $query = db_select("SELECT var_name,var_value FROM settings WHERE var_grp='mobile_marken' AND user_id=0 ORDER BY id",__FILE__);
    while ($row = db_fetch_array($query)) {
        if (array_key_exists($row['var_name'],$values)) { $values[$row['var_name']] = (string)$row['var_value']; }
    }
    print '<h2>'.$label(5490,'Marken i Saldi Mobile').'</h2>';
    $notice = (int)ifset($_SESSION,'mobile_marken_settings_notice',0);
    unset($_SESSION['mobile_marken_settings_notice']);
    if (in_array($notice,[5498,5499],true)) { print '<p role="status">'.$escape(findtekst($notice,$sprog_id)).'</p>'; }
    print '<form method="get" action="diverse.php"><input type="hidden" name="sektion" value="mobil_marken_valg">';
    print '<label>'.$label(5493,'År').' <input type="number" name="year" min="1000" max="9999" value="'.$escape($year).'"></label> <button>'.$escape(findtekst('3|Gem',$sprog_id)).'</button></form>';
    print '<form method="post" action="diverse.php?sektion=mobil_marken_valg&amp;year='.$escape($year).'">';
    print '<input type="hidden" name="year" value="'.$escape($year).'"><input type="hidden" name="csrf_token" value="'.$escape((string)$_SESSION['csrf_token']).'">';
    $accounts = [];
    $query = db_select('SELECT kontonr,beskrivelse,kontotype FROM kontoplan WHERE regnskabsaar='.(int)$regnaar." AND kontotype IN ('D','S') AND COALESCE(trim(lukket),'')='' ORDER BY kontonr",__FILE__);
    while ($row = db_fetch_array($query)) { $accounts[] = $row; }
    foreach (['claims_payable_account'=>[5491,'Konto til skyldige udlæg'],'claims_mileage_account'=>[5492,'Konto til kørselsgodtgørelse']] as $name=>$text) {
        print '<p><label>'.$label($text[0],$text[1]).' <select name="'.$name.'"><option value="">'.$escape(findtekst('5479|Ingen konto valgt',$sprog_id)).'</option>';
        foreach ($accounts as $account) {
            if ($name === 'claims_payable_account' && $account['kontotype'] !== 'S') { continue; }
            $number = (int)$account['kontonr'];
            print '<option value="'.$number.'"'.((string)$number === $values[$name] ? ' selected' : '').'>'.$number.' '.$escape((string)$account['beskrivelse']).'</option>';
        }
        print '</select></label></p>';
    }
    foreach (['km_rate'=>[5494,'Kørselssats'],'km_rate_low'=>[5495,'Sats over grænsen'],'km_threshold'=>[5496,'Årlig kilometergrænse']] as $name=>$text) {
        print '<p><label>'.$label($text[0],$text[1]).' <input type="number" step="'.($name === 'km_threshold' ? '0.1' : '0.01').'" min="0" name="'.$name.'" value="'.$escape($values[$name.'_'.$year]).'"></label></p>';
    }
    print '<fieldset><legend>'.$label(5497,'Bookingdag: fra, til og interval i minutter').'</legend>';
    foreach (['booking_day_start'=>[2493,'Start'],'booking_day_end'=>[2494,'Slut'],'booking_slot_minutes'=>[5497,'Bookingdag: fra, til og interval i minutter']] as $name=>$text) {
        print '<label>'.$label($text[0],$text[1]).' <input type="'.($name === 'booking_slot_minutes' ? 'number' : 'time').'" name="'.$name.'" value="'.$escape($values[$name]).'"></label> ';
    }
    print '</fieldset>';
    if (mobilBetalingAllowed('write')) { print '<button type="submit">'.$escape(findtekst('3|Gem',$sprog_id)).'</button>'; }
    print '</form>';
}
