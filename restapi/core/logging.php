<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/logging.php --- ver 5.0.0 --- 2026.10.01
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
// 20261001 CDX/LH Allowlist REST metadata and redact before truncation; discard legacy free-text logs.

require_once __DIR__ . '/RequestId.php';

/** Redact before any truncation; malformed JSON is discarded rather than partially logged. */
function redactRestLogData($data)
{
    $fields = ['refresh_token', 'access_token', 'image_base64', 'code', 'password', 'authorization',
        'token', 'secret', 'api_key', 'apikey', 'cookie'];
    if (is_string($data)) {
        $data = json_decode($data, true);
        if (!is_array($data)) {
            return '[REDACTED]';
        }
    }
    if (is_array($data)) {
        foreach ($data as $key => &$value) {
            if (in_array(strtolower((string)$key), $fields, true)) {
                $value = '[REDACTED]';
            } elseif (is_array($value)) {
                $value = redactRestLogData($value);
            }
        }
        unset($value);
    }
    return $data;
}

/**
 * Structured request metadata only. Legacy free-text calls are intentionally discarded.
 * @param array{method?:string,path?:string,request_id?:string,subject?:?string,status?:int,duration?:float}|string $text
 */
function write_log($text, $db, $log_level = 'INFO')
{
    if (!is_array($text)) {
        return;
    }
    $text = redactRestLogData($text);
    $safe = array_intersect_key($text, array_flip(['method', 'path', 'request_id', 'subject', 'status', 'duration']));
    $safe['request_id'] = RequestId::current();
    foreach ($safe as &$value) {
        if (is_string($value)) {
            $value = substr(str_replace(["\r", "\n"], '', $value), 0, 512);
        }
    }
    unset($value);
    $db = is_string($db) && preg_match('/^[A-Za-z0-9_]+$/D', $db) ? $db : 'api';
    $directory = __DIR__ . '/../../temp/' . $db;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create REST log directory');
    }
    file_put_contents($directory . '/.ht_rest_api.log', json_encode($safe, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}
