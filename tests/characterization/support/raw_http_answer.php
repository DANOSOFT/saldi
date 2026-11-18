<?php
// tests/characterization/support/raw_http_answer.php
//
// A fake webshop for ShopOfferPushTest that writes its answer's header lines byte for byte, which `php -S` cannot (PHP's
// header() refuses a folded field). Run as `php raw_http_answer.php <port file>`: it listens on a free loopback port, writes
// the port to the file, and answers each request by its path's first segment (/<case>?...), one connection at a time,
// HTTP/1.1 with Content-Length (or chunked) and Connection: close. Cases with Content-Encoding fields other than identity, or
// with a malformed field, send a JSON refusal zlib-compressed ("deflate"); the identity cases send OK. A redirect case answers
// 302 to another case; `continue` sends a 100 Continue block first; `chunked` sends its body in chunks, `chunked-trailer` with
// a trailer field after it. It never forwards anything to a real shop.
//
// History:
// 20261118 CL/LH Created for Codex verification 11: folded and repeated Content-Encoding fields, for every tick transport.
// 20261118 CL/LH Codex verification 12: malformed field names (space or tab before the colon, a byte outside a token), a plain deflate answer for the shell's header-file faults, and the 100 Continue, chunked and trailer compatibility cases.

$portFile = (string)($argv[1] ?? '');
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if ($server === false || $portFile === '') {
	fwrite(STDERR, "raw_http_answer: no port file, or cannot listen: $errstr\n");
	exit(1);
}
$name = (string)stream_socket_get_name($server, false);
file_put_contents($portFile, substr($name, strrpos($name, ':') + 1));

$refusal = (string)gzcompress('{"success":false,"message":"refused"}');
$cases = array(
	// every one of these declares a coding besides identity, so the answer must not be judged
	'folded'                => array(200, "Content-Encoding: identity,\r\n deflate", $refusal),
	'folded-empty'          => array(200, "Content-Encoding:\r\n deflate", $refusal),
	'folded-identity'       => array(200, "Content-Encoding: identity\r\n , deflate", $refusal),
	'folded-tab'            => array(200, "Content-Encoding: identity\r\n\t,deflate", $refusal),
	'double-identity-first' => array(200, "Content-Encoding: identity\r\nContent-Encoding: deflate", $refusal),
	'double-deflate-first'  => array(200, "Content-Encoding: deflate\r\nContent-Encoding: identity", $refusal),
	'redirect-to-encoded'   => array(302, "Location: /double-deflate-first?shop=2", ''),
	'deflate'               => array(200, "Content-Encoding: deflate", $refusal),
	// malformed fields: the answer's headers are not read at all, so the answer is not judged
	'space-colon'           => array(200, "Content-Encoding : deflate", $refusal),
	'tab-colon'             => array(200, "Content-Encoding\t: deflate", $refusal),
	'fold-space-colon'      => array(200, "Content-Encoding : identity\r\n , deflate", $refusal),
	'name-not-token'        => array(200, "Content-Encoding\xff: deflate", $refusal),
	'chunked-trailer'       => array(200, "Content-Encoding: identity", 'OK', 'X-Checksum: 1'),
	// only identity, however written: judged as usual
	'identity'              => array(200, "Content-Encoding: identity", 'OK'),
	'identity-mixed-case'   => array(200, "content-encoding: IDENTITY", 'OK'),
	'identity-folded'       => array(200, "Content-Encoding:\r\n identity", 'OK'),
	'identity-repeated'     => array(200, "Content-Encoding: identity\r\nContent-Encoding: identity", 'OK'),
	'redirect-from-encoded' => array(302, "Content-Encoding: deflate\r\nLocation: /identity?shop=2", ''),
	'continue'              => array(200, "Content-Encoding: identity", 'OK', null, "HTTP/1.1 100 Continue\r\n\r\n"),
	'chunked'               => array(200, "Content-Encoding: identity", 'OK', ''),
);

while (true) {
	$conn = @stream_socket_accept($server, -1);
	if ($conn === false) {
		continue;
	}
	stream_set_timeout($conn, 5);
	$request = '';
	while (strpos($request, "\r\n\r\n") === false && strlen($request) < 65536) {
		$chunk = fread($conn, 8192);
		if ($chunk === false || $chunk === '') {
			break;
		}
		$request .= $chunk;
	}
	$case = preg_match('#^[A-Z]+ /([a-z-]+)#', $request, $m) ? $m[1] : '';
	list($status, $fields, $body) = $cases[$case] ?? array(404, '', 'unknown case');
	$trailer = $cases[$case][3] ?? null; // a string: chunked, with this trailer field when it is not ''
	fwrite($conn, ($cases[$case][4] ?? '')
		. "HTTP/1.1 $status " . ($status === 200 ? 'OK' : ($status === 302 ? 'Found' : 'Not Found')) . "\r\n"
		. "Content-Type: application/json\r\n"
		. ($fields !== '' ? $fields . "\r\n" : '')
		. ($trailer === null ? 'Content-Length: ' . strlen($body) . "\r\n" : "Transfer-Encoding: chunked\r\n")
		. "Connection: close\r\n\r\n"
		. ($trailer === null ? $body : dechex(strlen($body)) . "\r\n" . $body . "\r\n0\r\n" . ($trailer !== '' ? $trailer . "\r\n" : '') . "\r\n"));
	fclose($conn);
}
