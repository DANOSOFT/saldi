<?php
// tests/characterization/support/smtp_sink.php
//
// A one-connection SMTP server for suites that need to see the mail a page
// sends. It listens on 127.0.0.1 on a free port, takes one connection, answers
// it like a mail server that accepts everything, and writes what the client
// said to a JSON file when the client goes away.
//
// EHLO offers AUTH PLAIN and no STARTTLS, so PHPMailer logs in only when it
// has a username and asks for TLS only when SMTPSecure says so. STARTTLS is
// recorded and refused, which ends that client's attempt.
//
// argv: 1 = file the result is written to
//       2 = seconds to wait for the connection (default 60)
//       then any of:
//       --port=<n>       listen on that port, e.g. 25 for what PHPMailer reaches as "localhost"
//       --no-auth        a relay without AUTH: EHLO does not offer it, and AUTH is refused (503)
//       --sender=<addr>  a server that takes only that sender: any other MAIL FROM is refused (553)
//
// Prints "PORT <n>" on stdout as soon as it listens, and exits with 1 when it
// cannot listen (the port is taken, or below 1024 without root). The result:
//   connected - a client connected
//   commands  - every command line the client sent, in order
//   auth      - [username, password] from AUTH PLAIN, or null
//   data      - the message as sent after DATA, or ""
//
// History:
// 20261105 CL/LH Created for PasswordRecoveryMailTest.

if ($argc < 2) {
    fwrite(STDERR, "usage: php smtp_sink.php <result file> [accept timeout] [--port=<n>] [--no-auth] [--sender=<addr>]\n");
    exit(2);
}
$sinkFile = $argv[1];
$acceptTimeout = (float)($argv[2] ?? 60);
$port = 0;
$offerAuth = true;
$sender = null;
foreach (array_slice($argv, 3) as $option) {
    if (preg_match('/^--port=(\d+)$/', $option, $m)) {
        $port = (int)$m[1];
    } elseif ($option === '--no-auth') {
        $offerAuth = false;
    } elseif (preg_match('/^--sender=(\S+)$/', $option, $m)) {
        $sender = strtolower($m[1]);
    } else {
        fwrite(STDERR, "unknown option: $option\n");
        exit(2);
    }
}

$server = @stream_socket_server("tcp://127.0.0.1:$port", $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "could not listen: $errstr\n");
    exit(1);
}
$name = (string)stream_socket_get_name($server, false);
fwrite(STDOUT, 'PORT ' . substr($name, strrpos($name, ':') + 1) . "\n");
fflush(STDOUT);

$result = ['connected' => false, 'commands' => [], 'auth' => null, 'data' => ''];
$conn = @stream_socket_accept($server, $acceptTimeout);
if ($conn !== false) {
    $result['connected'] = true;
    stream_set_timeout($conn, 20);
    $say = static function (string $reply) use ($conn): void {
        fwrite($conn, $reply . "\r\n");
    };
    $plain = static function (string $encoded) use (&$result): void {
        $parts = explode("\0", (string)base64_decode($encoded, true));
        $result['auth'] = [$parts[1] ?? '', $parts[2] ?? ''];
    };
    $say('220 chartest ESMTP');
    while (($line = fgets($conn)) !== false) {
        $line = rtrim($line, "\r\n");
        $result['commands'][] = $line;
        $verb = strtoupper((string)strtok($line, ' '));
        if ($verb === 'EHLO') {
            $say('250-chartest');
            if ($offerAuth) {
                $say('250-AUTH PLAIN');
            }
            $say('250 8BITMIME');
        } elseif ($verb === 'MAIL' && $sender !== null && !preg_match('/^MAIL FROM:\s*<' . preg_quote($sender, '/') . '>/i', $line)) {
            $say("553 5.7.1 Sender address rejected: not owned by this account, only <$sender>");
        } elseif ($verb === 'HELO' || $verb === 'MAIL' || $verb === 'RCPT' || $verb === 'RSET' || $verb === 'NOOP') {
            $say('250 OK');
        } elseif ($verb === 'AUTH' && !$offerAuth) {
            $say('503 5.5.1 AUTH not available');
        } elseif ($verb === 'AUTH') {
            $words = explode(' ', $line);
            if (strtoupper($words[1] ?? '') !== 'PLAIN') {
                $say('504 only PLAIN');
                continue;
            }
            if (!isset($words[2])) {
                $say('334 ');
                $creds = fgets($conn);
                if ($creds === false) {
                    break;
                }
                $words[2] = rtrim($creds, "\r\n");
            }
            $plain($words[2]);
            $say('235 Authentication succeeded');
        } elseif ($verb === 'DATA') {
            $say('354 End data with <CR><LF>.<CR><LF>');
            while (($dataLine = fgets($conn)) !== false && $dataLine !== ".\r\n") {
                $result['data'] .= $dataLine;
            }
            $say('250 OK queued');
        } elseif ($verb === 'STARTTLS') {
            $say('454 TLS not available');
        } elseif ($verb === 'QUIT') {
            $say('221 Bye');
            break;
        } else {
            $say('502 Command not implemented');
        }
    }
    fclose($conn);
}
fclose($server);
file_put_contents($sinkFile, json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE));
