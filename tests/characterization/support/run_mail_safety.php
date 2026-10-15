<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/characterization/support/run_mail_safety.php --- ver 5.0.0 --- 2026.10.02
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

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require_once $argv[2];
if ($argv[1] === 'loader') {
    send_mails_load_mailer();
    echo json_encode(['mailer' => class_exists(PHPMailer\PHPMailer\PHPMailer::class),
        'smtp' => class_exists(PHPMailer\PHPMailer\SMTP::class), 'root_loaded' => isset($GLOBALS['test_root_loader'])]);
} elseif ($argv[1] === 'duplicate') {
    $first = send_mails_reserve_browser($argv[3], 999, 'duplicate@example.invalid', 'Duplicate guard', 'same.pdf');
    $second = send_mails_reserve_browser($argv[3], 999, 'duplicate@example.invalid', 'Duplicate guard', 'same.pdf');
    echo json_encode([$first, $second]);
} else {
    $database = $argv[3];
    $writer = (int)$argv[4];
    for ($i = 0; $i < 500; $i++) {
        if (!send_mails_reserve_browser($database, $writer, 'writer-' . $writer . '@example.invalid',
            str_repeat('Subject', $writer * 7) . $i, 'file-' . $writer . '-' . $i)) {
            throw new RuntimeException('Independent browser mail was rejected');
        }
        include dirname($argv[2], 3) . '/temp/' . $database . '/mailchk.php';
        if (!is_int($chkOid) || !is_string($chkSubj) || !is_string($chkFil)) {
            throw new RuntimeException('Incomplete mail guard');
        }
    }
    echo 'OK';
}
