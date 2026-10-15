<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/tools/SaldiToolDefinition.php --- ai/mcp-assistant --- 2026.10.02
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
// Typed tool metadata and full MCP result schemas.
//
// 20261001 CDX/LH Created.
// 20261002 CL/LH Receipt states applied and closed, with item status and deduplicated flag.

declare(strict_types=1);

require_once __DIR__ . '/SaldiToolSchemas.php';

final class SaldiToolDefinition
{
	public function __construct(
		public readonly string $name,
		public readonly string $title,
		public readonly string $description,
		public readonly array $inputSchema,
		public readonly array $dataSchema,
		public readonly array $discoveryPermissions,
		public readonly string $effect,
		public readonly string $tier,
		public readonly Closure $handler,
	) {
		if (!preg_match('/^[a-z][a-z0-9_]{2,47}$/D', $name) || !in_array([$effect, $tier], [['read', 'auto'], ['draft', 'gated']], true) || ($inputSchema['additionalProperties'] ?? null) !== false) {
			throw new InvalidArgumentException('Invalid tool definition');
		}
	}
	/** Full structuredContent envelope, including both success and error fields. @return array */
	public function outputSchema(): array
	{
		return SaldiToolSchemas::object([
			'ok' => ['type' => 'boolean'], 'committed' => ['const' => false], 'data' => $this->dataSchema,
			'receipt' => SaldiToolSchemas::object(['state' => ['enum' => ['done', 'awaiting_approval', 'applied', 'closed']], 'label_da' => ['type' => 'string'], 'label_en' => ['type' => 'string'], 'item_id' => ['type' => 'integer'], 'status' => ['type' => 'string'], 'deduplicated' => ['type' => 'boolean']], ['state', 'label_da', 'label_en']),
			'citations' => SaldiToolSchemas::list(SaldiToolSchemas::object(['id' => ['type' => 'string'], 'label' => ['type' => 'string'], 'screen' => ['type' => 'string']], ['id', 'label'])),
			'possibly_incomplete' => ['type' => 'boolean'],
			'error' => SaldiToolSchemas::object(['code' => ['type' => 'string'], 'class' => ['type' => 'string'], 'message_en' => ['type' => 'string'], 'message_da' => ['type' => 'string'], 'detail' => ['type' => 'object']], ['code', 'class', 'message_en']),
		], ['ok', 'committed']);
	}
	/** MCP discovery representation. @return array */
	public function discovery(): array
	{
		return ['name' => $this->name, 'title' => $this->title, 'description' => $this->description, 'inputSchema' => $this->inputSchema, 'outputSchema' => $this->outputSchema(), 'annotations' => ['readOnlyHint' => $this->effect === 'read', 'destructiveHint' => false, 'idempotentHint' => $this->effect === 'read', 'openWorldHint' => false]];
	}
}
