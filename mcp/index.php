<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- mcp/index.php --- ai/mcp-assistant --- 2026.10.01
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
//
// HTTP adapter for the stateless Saldi MCP endpoint.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../includes/mcp/SaldiMcpServer.php';

$saldiMcpHeaders = function_exists('getallheaders') ? getallheaders() : [];
foreach (['CONTENT_TYPE' => 'Content-Type', 'HTTP_ORIGIN' => 'Origin', 'HTTP_AUTHORIZATION' => 'Authorization', 'HTTP_MCP_PROTOCOL_VERSION' => 'MCP-Protocol-Version'] as $key => $name) {
	if (isset($_SERVER[$key]) && !isset($saldiMcpHeaders[$name])) {
		$saldiMcpHeaders[$name] = $_SERVER[$key];
	}
}
$saldiMcpStream = fopen('php://input', 'rb');
$saldiMcpBody = stream_get_contents($saldiMcpStream, 256 * 1024 + 1);
fclose($saldiMcpStream);
$saldiMcpResponse = (new SaldiMcpServer())->handle(['method' => $_SERVER['REQUEST_METHOD'], 'headers' => $saldiMcpHeaders, 'body' => $saldiMcpBody, 'server' => $_SERVER]);
http_response_code($saldiMcpResponse['status']);
foreach ($saldiMcpResponse['headers'] as $name => $value) {
	header($name . ': ' . $value);
}
if ($saldiMcpResponse['body'] !== null) {
	echo json_encode($saldiMcpResponse['body'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
