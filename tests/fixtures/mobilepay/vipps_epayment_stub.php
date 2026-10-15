<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/mobilepay/vipps_epayment_stub.php --- ver 5.0.0 --- 2026-10-05 ---
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
// 20261001 CDX/LH Created. Invoice payment links.
// 20261001 CDX/LH Model authorization cancellation and creation recovery races.
// 20261005 CDX/LH Model authoritative successful capture events with provider timestamps.

// Test-only server: php -S 127.0.0.1:<ephemeral port> this file. State path comes from the test environment.
$path = getenv('SALDI_EP_STUB_STATE');
if (!$path || PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}
$fp = fopen($path,'c+');
flock($fp,LOCK_EX);
$state = json_decode(stream_get_contents($fp),true) ?: ['payments'=>[],'calls'=>[]];
$route = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$body = json_decode(file_get_contents('php://input'),true) ?: [];
$code = 200;
$result = [];
if ($route === '/accesstoken/get') {
    $result = ['access_token'=>bin2hex(random_bytes(32))];
} elseif ($route === '/__control') {
    if (isset($body['reference'])) {
        $state['payments'][$body['reference']] = array_replace($state['payments'][$body['reference']] ?? [],$body['payment'] ?? []);
        if (array_key_exists('events', $body)) {
            $state['events'][$body['reference']] = $body['events'];
        } elseif (isset($body['payment']['aggregate']['capturedAmount'])) {
            $captured = $body['payment']['aggregate']['capturedAmount'];
            $state['events'][$body['reference']] = $captured['value'] > 0 ? [[
                'reference'=>$body['reference'],'name'=>'CAPTURED','success'=>true,
                'amount'=>$captured,'timestamp'=>gmdate('c'),'pspReference'=>'capture-' . bin2hex(random_bytes(8)),
            ]] : [];
        }
    }
    if (isset($body['mode'])) {
        $state['mode'] = $body['mode'];
    }
    $result = $state;
} else {
    $state['calls'][] = ['method'=>$method,'path'=>$route,'body'=>$body,'key'=>$_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null];
    if (($state['mode'] ?? '') === 'unavailable') {
        $code = 503;
    } elseif ($route === '/epayment/v1/payments' && $method === 'POST') {
        $ref = $body['reference'];
        if (!isset($state['payments'][$ref])) {
            $state['payments'][$ref] = ['reference'=>$ref,'state'=>'CREATED','amount'=>$body['amount'],
                'aggregate'=>['capturedAmount'=>['value'=>0,'currency'=>$body['amount']['currency']]],
                'redirectUrl'=>'https://api.vipps.no/pay/'.$ref];
        }
        $result = ['reference'=>$ref,'redirectUrl'=>$state['payments'][$ref]['redirectUrl']];
    } elseif (preg_match('~^/epayment/v1/payments/([A-Za-z0-9-]+)(/(cancel|capture|events))?$~',$route,$match)) {
        $ref = $match[1];
        if (!isset($state['payments'][$ref])) {
            $code = 404;
        } else {
            if (($match[3] ?? '') === 'cancel') {
                if (($state['mode'] ?? '') === 'cancel_refused') {
                    $code = 409;
                } else {
                    // Simulate approval after the caller's GET but before its cancel POST.
                    if (($state['mode'] ?? '') === 'authorize_before_cancel') {
                        $state['payments'][$ref]['state'] = 'AUTHORIZED';
                    }
                    if ($state['payments'][$ref]['state'] === 'AUTHORIZED') {
                        $amount = $state['payments'][$ref]['amount'];
                        $amount['value'] -= $state['payments'][$ref]['aggregate']['capturedAmount']['value'];
                        $state['payments'][$ref]['aggregate']['cancelledAmount'] = $amount;
                    } else {
                        $state['payments'][$ref]['state'] = 'TERMINATED';
                    }
                }
            } elseif (($match[3] ?? '') === 'capture') {
                $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '';
                if (!isset($state['captures'][$key])) {
                    $state['payments'][$ref]['aggregate']['capturedAmount'] = $body['modificationAmount'];
                    $state['events'][$ref][] = ['reference'=>$ref,'name'=>'CAPTURED','success'=>true,
                        'amount'=>$body['modificationAmount'],'timestamp'=>gmdate('c'),
                        'pspReference'=>'capture-' . bin2hex(random_bytes(8)),'idempotencyKey'=>$key];
                    $state['captures'][$key] = true;
                }
            }
            $result = ($match[3] ?? '') === 'events' ? ($state['events'][$ref] ?? []) : $state['payments'][$ref];
        }
    } else {
        $code = 404;
    }
}
rewind($fp);
ftruncate($fp,0);
fwrite($fp,json_encode($state,JSON_THROW_ON_ERROR));
flock($fp,LOCK_UN);
fclose($fp);
http_response_code($code);
header('Content-Type: application/json');
echo json_encode($result,JSON_THROW_ON_ERROR);
