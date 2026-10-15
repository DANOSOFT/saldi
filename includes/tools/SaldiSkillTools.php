<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiSkillTools.php --- ai/mcp-assistant --- 2026.10.02
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
// Read and validate Danosoft's local procedure packs without executing their checks.
//
// 20261002 CDX/LH Created.
// 20261002 CDX/LH Parse procedure metadata and validate read-only steps.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/bootstrap.php';

final class SaldiSkillTools
{
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function listSkills(SaldiTenantContext $ctx, SaldiDb $db, array $args, ?string $directory = null): array
	{
		$rows = [];
		foreach (self::packs($directory) as $pack) {
			$rows[] = array_intersect_key($pack, array_flip(['slug', 'title', 'description', 'version']));
		}
		usort($rows, static fn(array $first, array $second): int => strcmp($first['title'], $second['title']));
		return ['data' => ['rows' => $rows, 'total' => count($rows)], 'citations' => [], 'possibly_incomplete' => false, 'result_count' => count($rows)];
	}
	/** @return array{data:array,citations:list<array{id:string,label:string,screen?:string}>,possibly_incomplete:bool,result_count:int} */
	public static function getSkill(SaldiTenantContext $ctx, SaldiDb $db, array $args, ?string $directory = null): array
	{
		$slug = $args['slug'] ?? null;
		if (!is_string($slug) || !preg_match('/^[a-z0-9-]{3,40}$/D', $slug)) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Procedure not found');
		}
		$file = ($directory ?? __DIR__ . '/../automation/skills') . '/' . $slug . '/SKILL.md';
		if (!is_file($file)) {
			throw new SaldiRuntimeError('NOT_FOUND', 'Procedure not found');
		}
		$source = file_get_contents($file);
		if ($source === false) {
			throw new SaldiRuntimeError('UNAVAILABLE', 'Procedure pack unreadable');
		}
		$pack = self::parsePack($source, $slug);
		$data = array_intersect_key($pack, array_flip(['slug', 'title', 'version', 'body', 'steps']));
		return ['data' => $data, 'citations' => [], 'possibly_incomplete' => false, 'result_count' => 1];
	}
	/** @return list<array{slug:string,title:string,description:string,version:int,audience:string,body:string,steps:list<array>}> */
	public static function packs(?string $directory = null): array
	{
		$packs = [];
		foreach (glob(($directory ?? __DIR__ . '/../automation/skills') . '/*/SKILL.md') ?: [] as $file) {
			$source = file_get_contents($file);
			if ($source === false) {
				throw new SaldiRuntimeError('UNAVAILABLE', 'Procedure pack unreadable');
			}
			$packs[] = self::parsePack($source, basename(dirname($file)));
		}
		return $packs;
	}
	/** @return array{slug:string,title:string,description:string,version:int,audience:string,body:string,steps:list<array>} */
	public static function parsePack(string $source, ?string $expectedSlug = null): array
	{
		require_once __DIR__ . '/SaldiToolRegistry.php';
		$invalid = static function (): never {
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Invalid procedure pack');
		};
		$source = str_replace("\r\n", "\n", $source);
		if (!preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $source, $parts)) {
			$invalid();
		}
		$pack = [];
		foreach (explode("\n", $parts[1]) as $line) {
			if (!preg_match('/^(slug|title|description|version|audience):[ \t]+([^\n]+)$/D', $line, $field) || isset($pack[$field[1]])) {
				$invalid();
			}
			$pack[$field[1]] = trim($field[2]);
		}
		if (count($pack) !== 5 || !preg_match('/^[a-z0-9-]{3,40}$/D', $pack['slug']) || ($expectedSlug !== null && $pack['slug'] !== $expectedSlug)
			|| $pack['title'] === '' || $pack['description'] === '' || !preg_match('/^[1-9][0-9]{0,8}$/D', $pack['version']) || !in_array($pack['audience'], ['bogholder', 'ejer', 'alle'], true)) {
			$invalid();
		}
		$pack['version'] = (int)$pack['version'];
		$pack['body'] = trim($parts[2]);
		preg_match_all('/^```([^\n]*)\n(.*?)^```[ \t]*$/ms', $pack['body'], $blocks, PREG_SET_ORDER);
		$last = $blocks ? $blocks[count($blocks) - 1] : null;
		if (!$last || trim($last[1]) !== 'json saldi-steps') {
			$invalid();
		}
		try {
			$steps = json_decode($last[2], false, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$invalid();
		}
		if (!is_array($steps)) {
			$invalid();
		}
		$registry = SaldiToolRegistry::default();
		$ids = [];
		$pack['steps'] = [];
		foreach ($steps as $step) {
			if (!$step instanceof stdClass) {
				$invalid();
			}
			$step = (array)$step;
			if (array_diff(array_keys($step), ['id', 'title', 'check_tool', 'check_args', 'screen', 'doc']) || !is_string($step['id'] ?? null) || $step['id'] === ''
				|| isset($ids[$step['id']]) || !is_string($step['title'] ?? null) || trim($step['title']) === '') {
				$invalid();
			}
			$ids[$step['id']] = true;
			if (array_key_exists('check_tool', $step)) {
				$tool = is_string($step['check_tool']) ? $registry->get($step['check_tool']) : null;
				if (!$tool || $tool->effect !== 'read' || $tool->tier !== 'auto') {
					$invalid();
				}
			}
			if (array_key_exists('check_args', $step) && (!isset($step['check_tool']) || !$step['check_args'] instanceof stdClass)) {
				$invalid();
			}
			if (array_key_exists('screen', $step) && (!is_string($step['screen']) || !SaldiScreens::isAllowed($step['screen']))) {
				$invalid();
			}
			if (array_key_exists('doc', $step) && (!is_string($step['doc']) || !preg_match('/^docs:[a-zA-Z0-9_-]+$/D', $step['doc']))) {
				$invalid();
			}
			$pack['steps'][] = $step;
		}
		SaldiSchemaValidator::validate(array_intersect_key($pack, array_flip(['slug', 'title', 'version', 'body', 'steps'])), $registry->get('get_procedure')->dataSchema);
		return $pack;
	}
}
