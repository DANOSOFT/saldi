<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tools/plan/export_tool_registry.php --- ai/mcp-assistant --- 2026.10.01
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
// CLI exporter for the canonical, revision-free tool contract.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit;
}
require_once __DIR__ . '/../../includes/tools/SaldiToolContract.php';
$saldiContractPath = __DIR__ . '/../../doc/contracts/tool-registry.v1.json';
if (!is_dir(dirname($saldiContractPath))) {
	mkdir(dirname($saldiContractPath), 0775, true);
}
if (file_put_contents($saldiContractPath, SaldiToolContract::json()) === false) {
	fwrite(STDERR, "Could not write tool contract\n");
	exit(1);
}
fwrite(STDOUT, "Exported doc/contracts/tool-registry.v1.json\n");
