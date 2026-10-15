<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiToolContract.php --- ai/mcp-assistant --- 2026.10.01
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
// Canonical v1 contract for registry drift detection and downstream pinning.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/SaldiToolRegistry.php';
require_once __DIR__ . '/../mcp/SaldiMcpServer.php';

final class SaldiToolContract
{
	/** Exportable protocol, screens, kinds and complete tool definitions. @return array */
	public static function build(): array
	{
		$errors = [];
		foreach (SaldiRuntimeError::CODES as $code => [$class, $http]) {
			$errors[] = ['code' => $code, 'class' => $class, 'http' => $http];
		}
		usort($errors, static fn(array $a, array $b): int => strcmp($a['code'], $b['code']));
		$screens = [];
		foreach (SaldiScreens::SCREENS as $type => [$screen, $param]) {
			$screens[] = ['type' => $type, 'screen' => $screen, 'param' => $param];
		}
		usort($screens, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));
		$kinds = SaldiKindRegistry::default();
		$names = $kinds->names();
		sort($names);
		$tools = [];
		foreach (SaldiToolRegistry::default()->all() as $t) {
			$tools[] = ['name' => $t->name, 'title' => $t->title, 'description' => $t->description, 'inputSchema' => $t->inputSchema, 'outputSchema' => $t->outputSchema(), 'dataSchema' => $t->dataSchema, 'discoveryPermissions' => $t->discoveryPermissions, 'effect' => $t->effect, 'tier' => $t->tier];
		}
		return ['version' => 1, 'protocol_versions' => SaldiMcpServer::PROTOCOL_VERSIONS, 'token_format' => 'smcp1.<install_id>.<regnskab_id>.<grant_id>.<secret>', 'error_codes' => $errors, 'forbidden_capabilities' => ['pay', 'file_vat', 'lock_period', 'delete_records', 'change_bank_details', 'change_rights', 'post_journal', 'send_documents'], 'screens' => $screens, 'tools' => $tools, 'kinds' => array_map(static fn(string $name): array => ['name' => $name, 'action_class' => $kinds->get($name)->actionClass()], $names)];
	}
	/** Sorted object keys, exactly two-space indentation, trailing LF. */
	public static function json(): string
	{
		$canonical = json_decode(SaldiToolRuntime::canonical(self::build()), false, 512, JSON_THROW_ON_ERROR);
		$fourSpaces = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return preg_replace_callback('/^( +)/m', static fn(array $m): string => str_repeat(' ', intdiv(strlen($m[1]), 2)), $fourSpaces) . "\n";
	}
}
