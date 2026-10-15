<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- systemdata/diverseIncludes/mobilBetalingValg.php --- ver 5.0.0 --- 2026-10-03 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Mobile payment account configuration.

require_once __DIR__ . '/../../restapi/core/Rights.php';

/** Check the current tenant's settings right, including the web revisor treatment. */
function mobilBetalingAllowed(string $action): bool
{
    global $brugernavn, $revisor;
    $name = db_escape_string((string)$brugernavn);
    $row = db_fetch_array(db_select("SELECT rettigheder FROM brugere WHERE brugernavn='$name'",__FILE__));
    $isRevisor = $revisor === true || $revisor === 't' || $revisor === 'on' || $revisor === 1;
    return Rights::allows((string)ifset($row,'rettigheder','0'),'admin',$action,null,$isRevisor);
}

/**
 * Validate the whole submitted configuration before saving any field.
 * @param array<string,mixed> $input Submitted form fields.
 * @return int Translation id describing the result.
 */
function mobilBetalingSave(array $input): int
{
    global $regnaar, $db;
    $token = ifset($input,'csrf_token');
    if (!mobilBetalingAllowed('write') || !is_string($token) || !hash_equals((string)ifset($_SESSION,'csrf_token',''),$token)
        || ifset($_SESSION,'mobile_payment_settings_db') !== $db) {
        return 5477;
    }
    $values = [];
    foreach (['bank','kasse','mobilepay'] as $method) {
        $value = ifset($input,'account_'.$method,'');
        if (!is_string($value) || ($value !== '' && (!preg_match('/^[1-9][0-9]{0,8}$/D',$value)
            || !db_fetch_array(db_select("SELECT id FROM kontoplan WHERE kontonr=".(int)$value." AND regnskabsaar=".(int)$regnaar." AND kontotype='S' AND COALESCE(trim(lukket),'')=''",__FILE__))))) {
            return 5476;
        }
        $values['account_'.$method] = $value;
    }
    $days = ifset($input,'link_lifetime_days','14');
    $url = ifset($input,'public_base_url','');
    if (!is_string($days) || !preg_match('/^[1-9][0-9]{0,2}$/D',$days) || (int)$days > 365 || !is_string($url)) {
        return 5476;
    }
    $url = rtrim(trim($url),'/');
    $parts = parse_url($url);
    if ($url !== '' && (!$parts || ifset($parts,'scheme') !== 'https' || !ifset($parts,'host')
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
        || preg_match('/[\x00-\x20]/',$url))) {
        return 5476;
    }
    $values['link_lifetime_days'] = (string)(int)$days;
    $values['public_base_url'] = $url;
    transaktion('begin');
    foreach ($values as $name=>$value) {
        $escaped = db_escape_string($value);
        $row = db_fetch_array(db_select("SELECT id FROM settings WHERE var_grp='mobile_betaling' AND var_name='$name' AND user_id=0 ORDER BY id DESC LIMIT 1",__FILE__));
        if ($row) {
            db_modify("UPDATE settings SET var_value='$escaped' WHERE var_grp='mobile_betaling' AND var_name='$name' AND user_id=0",__FILE__);
        } else {
            db_modify("INSERT INTO settings (var_grp,var_name,var_value,var_description,user_id) VALUES ('mobile_betaling','$name','$escaped','Mobile payment configuration',0)",__FILE__);
        }
    }
    if (!empty($GLOBALS['db_modify_fejl'])) {
        transaktion('rollback');
        return 5476;
    }
    transaktion('commit');
    return 5478;
}

/** Render configured accounts for the selected fiscal year. All mutations happen before rendering. */
function mobilBetalingValg(): void
{
    global $regnaar, $sprog_id, $db;
    if (!mobilBetalingAllowed('read')) {
        http_response_code(403);
        print htmlspecialchars(findtekst('5477|Ingen adgang eller sessionen er udløbet',$sprog_id),ENT_QUOTES,'UTF-8');
        return;
    }
    $_SESSION['mobile_payment_settings_db'] = $db;
    $values = ['account_bank'=>'','account_kasse'=>'','account_mobilepay'=>'','public_base_url'=>'','link_lifetime_days'=>'14'];
    $query = db_select("SELECT var_name,var_value FROM settings WHERE var_grp='mobile_betaling' AND user_id=0 ORDER BY id",__FILE__);
    while ($row = db_fetch_array($query)) {
        if (array_key_exists($row['var_name'],$values)) {
            $values[$row['var_name']] = (string)$row['var_value'];
        }
    }
    $accounts = [];
    $query = db_select("SELECT kontonr,beskrivelse FROM kontoplan WHERE regnskabsaar=".(int)$regnaar." AND kontotype='S' AND COALESCE(trim(lukket),'')='' ORDER BY kontonr",__FILE__);
    while ($row=db_fetch_array($query)) {
        $accounts[] = $row;
    }
    $escape = static fn(string $value): string => htmlspecialchars($value,ENT_QUOTES,'UTF-8');
    $label = static fn(int $id,string $fallback): string => $escape(findtekst($id.'|'.$fallback,$sprog_id));
    print '<h2>'.$label(5470,'Betalinger i Saldi Mobile').'</h2>';
    $notice = (int)ifset($_SESSION,'mobile_payment_settings_notice',0);
    unset($_SESSION['mobile_payment_settings_notice']);
    if (in_array($notice,[5476,5477,5478],true)) {
        print '<p role="status">'.$escape(findtekst($notice,$sprog_id)).'</p>';
    }
    print '<form method="post" action="diverse.php?sektion=mobil_betaling_valg">';
    print '<input type="hidden" name="csrf_token" value="'.$escape((string)$_SESSION['csrf_token']).'">';
    foreach (['bank'=>[5471,'Bankkonto'],'kasse'=>[5472,'Kassekonto'],'mobilepay'=>[5473,'MobilePay modkonto']] as $method=>$text) {
        print '<p><label>'.$label($text[0],$text[1]).' <select name="account_'.$method.'">';
        print '<option value="">'.$label(5479,'Ingen konto valgt').'</option>';
        foreach ($accounts as $account) {
            $number=(int)$account['kontonr'];
            $selected=(string)$number === $values['account_'.$method] ? ' selected':'';
            print '<option value="'.$number.'"'.$selected.'>'.$number.' '.$escape((string)$account['beskrivelse']).'</option>';
        }
        print '</select></label></p>';
    }
    print '<p><label>'.$label(5474,'Offentlig URL til betalingslink').' <input type="url" name="public_base_url" value="'.$escape($values['public_base_url']).'"></label></p>';
    print '<p><label>'.$label(5475,'Betalingslink gyldigt i dage').' <input type="number" min="1" max="365" name="link_lifetime_days" value="'.$escape($values['link_lifetime_days']).'"></label></p>';
    if (mobilBetalingAllowed('write')) {
        print '<button type="submit">'.$escape(findtekst('3|Gem',$sprog_id)).'</button>';
    }
    print '</form>';
}
