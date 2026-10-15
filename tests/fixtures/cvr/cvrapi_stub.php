<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/cvr/cvrapi_stub.php --- ver 5.0.0 --- 2026.10.03
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

/** @return array{status:int,body:string} Local deterministic CVR transport fixture. */
function cvrapi_fixture(string $number): array
{
    if ($number === '99999999') {
        return ['status'=>503,'body'=>'{}'];
    }
    if ($number === '00000000') {
        return ['status'=>404,'body'=>'{}'];
    }
    if ($number === '88888888') {
        return ['status'=>200,'body'=>'invalid json'];
    }
    return ['status'=>200,'body'=>json_encode(['vat'=>$number,'name'=>'Stub Hansen ApS',
        'address'=>'Testvej 1','zipcode'=>'8000','city'=>'Aarhus','phone'=>'12345678',
        'email'=>'test@example.invalid','industrydesc'=>'Test industry','status'=>'NORMAL'])];
}
if (PHP_SAPI !== 'cli') {
    $result = cvrapi_fixture((string)($_GET['vat'] ?? $_GET['phone'] ?? ''));
    http_response_code($result['status']);
    header('Content-Type: application/json');
    echo $result['body'];
}
