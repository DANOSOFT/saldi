<?php
// tests/characterization/support/fake_sendmail.php
//
// A sendmail_path stand-in for suites that need to see what PHP's mail() hands the mail program: the
// arguments (the envelope sender as -f<address> when the mailer sets one) and the whole message with
// its headers. Each call appends one JSON line {"args": [...], "message": "..."} to the result file.
//
// Use: php -d "sendmail_path=<php> fake_sendmail.php <result file>" ...; mail() appends its own arguments.
//
// History:
// 20261126 CL/ASR Created for CliMailSenderTest (review round 1, I6).

if ($argc < 2) {
    fwrite(STDERR, "usage: php fake_sendmail.php <result file> [mailer arguments]\n");
    exit(2);
}
$message = (string)stream_get_contents(STDIN);
file_put_contents($argv[1], json_encode(['args' => array_slice($argv, 2), 'message' => $message]) . "\n", FILE_APPEND | LOCK_EX);
