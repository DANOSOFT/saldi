<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiSchemaValidator.php --- ai/mcp-assistant --- 2026.10.01
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
// Bounded JSON Schema validation with field-path errors.
//
// 20261001 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../runtime/SaldiRuntimeError.php';

final class SaldiSchemaValidator
{
	/** Validate the supported JSON Schema subset; report paths without echoing input values. */
	public static function validate(mixed $value, array $schema): void
	{
		$fields = [];
		self::check($value, $schema, '$', $fields);
		if ($fields) {
			$fields = array_slice(array_values(array_unique($fields)), 0, 100);
			throw new SaldiRuntimeError('VALIDATION_ERROR', 'Input does not match schema', ['fields' => array_map(static fn(string $path): string => mb_substr($path, 0, 500), $fields)]);
		}
	}
	private static function check(mixed $v, array $s, string $path, array &$fields): void
	{
		$types = isset($s['type']) ? (array)$s['type'] : [];
		$object = $v instanceof stdClass || (is_array($v) && !array_is_list($v));
		$matches = !$types;
		foreach ($types as $type) {
			$matches = $matches || match ($type) {
				'object' => $object, 'array' => is_array($v) && array_is_list($v),
				'string' => is_string($v), 'integer' => is_int($v), 'number' => is_int($v) || (is_float($v) && is_finite($v)),
				'boolean' => is_bool($v), 'null' => $v === null, default => false
			};
		}
		if (!$matches) {
			$fields[] = $path;
			return;
		}
		if ((isset($s['enum']) && !in_array($v, $s['enum'], true)) || (array_key_exists('const', $s) && $v !== $s['const'])) {
			$fields[] = $path;
		}
		if (is_string($v)) {
			if ((isset($s['minLength']) && mb_strlen($v) < $s['minLength']) || (isset($s['maxLength']) && mb_strlen($v) > $s['maxLength']) || (isset($s['pattern']) && preg_match('~' . str_replace('~', '\\~', $s['pattern']) . '~uD', $v) !== 1)) {
				$fields[] = $path;
			}
		}
		if (is_int($v) || is_float($v)) {
			if ((isset($s['minimum']) && $v < $s['minimum']) || (isset($s['maximum']) && $v > $s['maximum'])) {
				$fields[] = $path;
			}
		}
		if ($object && in_array('object', $types, true)) {
			$props = (array)($s['properties'] ?? []);
			$values = (array)$v;
			foreach ($s['required'] ?? [] as $key) {
				if (!array_key_exists($key, $values)) {
					$fields[] = $path . '.' . $key;
				}
			}
			foreach ($values as $key => $item) {
				if (isset($props[$key])) {
					self::check($item, $props[$key], $path . '.' . $key, $fields);
				} elseif (($s['additionalProperties'] ?? true) === false) {
					$fields[] = $path . '.' . $key;
				}
			}
		}
		if (is_array($v) && array_is_list($v) && in_array('array', $types, true)) {
			if ((isset($s['maxItems']) && count($v) > $s['maxItems']) || (isset($s['minItems']) && count($v) < $s['minItems'])) {
				$fields[] = $path;
			}
			foreach ($v as $i => $item) {
				if (isset($s['items'])) {
					self::check($item, $s['items'], $path . '[' . $i . ']', $fields);
				}
			}
		}
	}
}
