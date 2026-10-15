<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stdFunc/cvrLookup.php --- ver 5.0.0 --- 2026.10.03
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

if (!function_exists('cvr_lookup')) {
    /**
     * Shared web CVR transport; server configuration may point to a local test stub.
     * @return array{status:int,body:array|null,raw:string|null} raw preserves the web proxy response bytes.
     */
    function cvr_lookup(string $type, string $param, string $country = 'dk'): array
    {
        if (!in_array($type, ['vat','phone'], true) || !preg_match('/^[0-9]{8}$/D', $param) || !preg_match('/^[a-z]{2}$/D', $country)) {
            return ['status'=>400,'body'=>['error'=>'invalid parameters'],'raw'=>null];
        }
        $base = getenv('SALDI_CVR_API_URL') ?: 'https://cvrapi.dk/api';
        $url = $base . (str_contains($base, '?') ? '&' : '?') . $type . '=' . urlencode($param) . '&country=' . urlencode($country);
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>true,
            CURLOPT_TIMEOUT=>10, CURLOPT_USERAGENT=>'saldi.dk - kundeopslag (support@saldi.dk)',
            CURLOPT_HTTPHEADER=>['Accept: application/json']]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code >= 400) {
            return ['status'=>$code ?: 502,'body'=>['error'=>'upstream error','status'=>$code],'raw'=>null];
        }
        $body = json_decode($raw, true);
        return ['status'=>$code ?: 200,'body'=>is_array($body) ? $body : null,'raw'=>$raw];
    }
}
