<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- bank_integration/includes/ebConfig.php --- ver 5.1.1 --- 2026-11-18 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or any later version of your choice.
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
//
// 20261006 CDX/LH Created Enable Banking ebConfig backend service.
// 20261118 CL/LH Optional company allowlist ($enableBankingCompanies / SALDI_ENABLEBANKING_COMPANIES): while set, only the listed company databases see the feature.
//                A live install can then be opened for one company first.

/**
 * @return array{app_id:string, key_path:string, redirect_url:string, environment:string, companies:list<string>, configured:bool, problem:string}
 */
function ebConfig(): array
{
    $fields = ['app_id' => ['APP_ID', 'enableBankingAppId'], 'key_path' => ['KEY_PATH', 'enableBankingKeyPath'],
        'redirect_url' => ['REDIRECT_URL', 'enableBankingRedirectUrl'], 'environment' => ['ENV', 'enableBankingEnvironment']];
    $config = [];
    foreach ($fields as $field => [$env, $global]) {
        $value = trim((string)getenv('SALDI_ENABLEBANKING_' . $env));
        $config[$field] = $value !== '' ? $value : trim((string)($GLOBALS[$global] ?? ''));
    }
    $config['environment'] = strtoupper($config['environment'] ?: 'SANDBOX');
    // Pilot allowlist of company databases ("saldi_1091, saldi_7"); empty means every company on the install.
    $companies = trim((string)getenv('SALDI_ENABLEBANKING_COMPANIES'));
    $companies = $companies !== '' ? $companies : (string)($GLOBALS['enableBankingCompanies'] ?? '');
    $config['companies'] = array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', $companies)), 'strlen'));
    $problem = '';
    $keyPath = realpath($config['key_path']);
    $root = realpath(dirname(__DIR__, 2));
    if ($config['app_id'] === '') {
        $problem = 'Enable Banking application id mangler.';
    } elseif (!filter_var($config['redirect_url'], FILTER_VALIDATE_URL) || !in_array(parse_url($config['redirect_url'], PHP_URL_SCHEME), ['http', 'https'], true)) {
        $problem = 'Enable Banking callback URL mangler eller er ugyldig.';
    } elseif (!$keyPath || !is_file($keyPath) || !is_readable($keyPath)) {
        $problem = 'Enable Banking nøglefil mangler eller kan ikke læses.';
    } elseif ($keyPath === $root || str_starts_with($keyPath, $root . DIRECTORY_SEPARATOR)) {
        $problem = 'Enable Banking nøglefil skal ligge uden for Saldi-installationen.';
    } elseif (!in_array($config['environment'], ['SANDBOX', 'PRODUCTION'], true)) {
        $problem = 'Enable Banking miljø er ugyldigt.';
    }
    $config['configured'] = $problem === '';
    $config['problem'] = $problem;
    return $config;
}

/** @return bool Whether server-side provider configuration is available to the current company ($GLOBALS['db']). */
function ebFeatureAvailable(): bool
{
    $config = ebConfig();
    return $config['configured'] && ($config['companies'] === [] || in_array((string)($GLOBALS['db'] ?? ''), $config['companies'], true));
}
