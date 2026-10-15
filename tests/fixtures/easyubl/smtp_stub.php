<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/fixtures/easyubl/smtp_stub.php --- ver 5.0.0 --- 2026.10.02
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

/** CLI-only local SMTP fixture: reject recipients, lose DATA acknowledgement, or edit after intent commit. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
[$script, $port, $scenario, $output] = $argv;
$server = stream_socket_server('tcp://127.0.0.1:' . (int)$port);
if ($server === false) {
    throw new RuntimeException('Cannot start local SMTP fixture');
}
file_put_contents($output . '.ready', 'ready');
$client = stream_socket_accept($server, 20);
if ($client === false) {
    throw new RuntimeException('No SMTP connection');
}
stream_set_timeout($client, 15);
$intentCommitted = null;
if ($scenario === 'snapshot') {
    require_once __DIR__ . '/../../restapi/support/RestApiEnv.php';
    $connection = RestApiEnv::connect($argv[4]);
    RestApiEnv::rows($connection, "SET lock_timeout='2s'");
    $intentCommitted = (int)RestApiEnv::rows($connection,
        "SELECT count(*) FROM document_deliveries WHERE ordre_id=$1 AND state='sending'", [(int)$argv[5]])[0]['count'];
    RestApiEnv::rows($connection,
        "UPDATE ordrer SET konto_id=$1,email='changed@example.invalid',firmanavn='Changed customer',status=3,
            mail_subj='Changed subject',mail_text='Changed body' WHERE id=$2", [(int)$argv[6], (int)$argv[5]]);
    RestApiEnv::rows($connection, "UPDATE ordrelinjer SET beskrivelse='Changed line' WHERE ordre_id=$1", [(int)$argv[5]]);
    pg_close($connection);
}
fwrite($client, "220 localhost ESMTP\r\n");
$data = '';
$recipients = [];
$readingData = false;
while (($line = fgets($client)) !== false) {
    if ($readingData) {
        if (rtrim($line, "\r\n") === '.') {
            file_put_contents($output, json_encode(['data' => $data, 'recipients' => $recipients,
                'intent_committed' => $intentCommitted], JSON_THROW_ON_ERROR));
            if ($scenario === 'disconnect') {
                break;
            }
            fwrite($client, $scenario === 'data_rejected' ? "554 Message rejected\r\n" : "250 Message accepted\r\n");
            $readingData = false;
        } else {
            $data .= $line;
        }
        continue;
    }
    $command = strtoupper(strtok($line, ' '));
    if ($command === 'EHLO' || $command === 'HELO') {
        fwrite($client, "250 localhost\r\n");
    } elseif ($command === 'RCPT') {
        preg_match('/<([^>]+)>/', $line, $matches);
        $address = $matches[1];
        $accepted = $scenario !== 'all_rejected' && !str_starts_with($address, 'reject-');
        $recipients[$address] = $accepted;
        fwrite($client, $accepted ? "250 Recipient accepted\r\n" : "550 Recipient rejected\r\n");
    } elseif (trim($command) === 'DATA') {
        fwrite($client, "354 Send data\r\n");
        $readingData = true;
    } elseif (trim($command) === 'QUIT') {
        fwrite($client, "221 Bye\r\n");
        break;
    } else {
        fwrite($client, "250 OK\r\n");
    }
}
if (!is_file($output)) {
    file_put_contents($output, json_encode(['data' => $data, 'recipients' => $recipients,
        'intent_committed' => $intentCommitted], JSON_THROW_ON_ERROR));
}
fclose($client);
fclose($server);
