<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/runtime/SaldiMobileGrantSchemaTest.test.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Created for the mobile Assist contract.

declare(strict_types=1);

require_once __DIR__ . '/SaldiRuntimeDbTestCase.php';
final class SaldiMobileGrantSchemaTest extends SaldiRuntimeDbTestCase
{
	public function testOldWatermarkAddsFamilyColumnAndIndexWithoutChangingRows(): void
	{
		$db = $this->env->db;
		$this->env->enable();
		$issued = (new SaldiGrantService($this->env->master, $db, $this->env->ctx()))->issuePersonal('Preserved', ['read'], 30);
		$db->exec('DROP INDEX agent_grants_family_idx');
		$db->exec('ALTER TABLE agent_grants DROP COLUMN family_id');
		$db->exec("UPDATE settings SET var_value = 's92_bank_import_lines' WHERE var_name = 'plan_updates_last_step'");
		saldi_runtime_ensure_schema($db);
		self::assertTrue($db->hasColumn('agent_grants', 'family_id'));
		self::assertNull($db->value('SELECT family_id FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
		self::assertSame('Preserved', $db->value('SELECT label FROM agent_grants WHERE id = ?', [$issued['grant']['id']]));
		saldi_runtime_ensure_schema($db);
		self::assertSame(1, (int)$db->value("SELECT COUNT(*) FROM pg_indexes WHERE tablename = 'agent_grants' AND indexname = 'agent_grants_family_idx'"));
	}
	public function testUpgradeDdlIsIdempotentOnBothEngines(): void
	{
		foreach (['postgresql', 'mysql'] as $type) {
			$writes = [];
			$step = saldi_plan_update_steps()['s93_agent_grants_family_id'];
			$step($type, function ($sql) use (&$writes): void { $writes[] = $sql; }, fn($sql) => null);
			self::assertSame(['ALTER TABLE agent_grants ADD COLUMN family_id varchar(64)', 'CREATE INDEX agent_grants_family_idx ON agent_grants (family_id, kind)'], $writes);
			$step($type, fn($sql) => self::fail('Repeated upgrade wrote schema'), fn($sql) => ['present' => 1]);
		}
	}
}
