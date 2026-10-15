<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiSkillToolsTest.test.php --- ai/mcp-assistant --- 2026.10.02
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
// Regression coverage for the owned procedure, mail and digest services.
//
// 20261002 CDX/LH Created.

declare(strict_types=1);

require_once __DIR__ . '/../support/SaldiReachTestCase.php';
require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

final class SaldiSkillToolsTest extends SaldiReachTestCase
{
    private function fixtures(): string
    {
        return __DIR__ . '/../fixtures/skills';
    }

    public function testFixturePacksSortAndPreserveLiteralPlaceholders(): void
    {
        $ctx = $this->env->ctx();
        $listing = SaldiSkillTools::listSkills($ctx, $this->env->db, [], $this->fixtures());
        self::assertSame(['alpha', 'zebra'], array_column($listing['data']['rows'], 'slug'));
        self::assertSame(2, $listing['result_count']);
        self::assertSame([], $listing['citations']);
        $result = SaldiSkillTools::getSkill($ctx, $this->env->db, ['slug' => 'alpha'], $this->fixtures());
        self::assertSame('$period_start', $result['data']['steps'][1]['check_args']->from);
        self::assertSame('$period_end', $result['data']['steps'][1]['check_args']->to);
        self::assertSame([], $result['citations']);
        self::assertStringContainsString('# Afstemning', $result['data']['body']);
        $registry = SaldiToolRegistry::default();
        foreach (['list_procedures' => $listing, 'get_procedure' => $result] as $name => $output) {
            unset($output['result_count']);
            SaldiSchemaValidator::validate(
                ['ok' => true, 'committed' => false] + $output,
                $registry->get($name)->outputSchema()
            );
        }
    }

    public function testMalformedMetadataAndUnsafeStepsFailClosed(): void
    {
        $source = file_get_contents($this->fixtures() . '/alpha/SKILL.md');
        $variants = [
            str_replace('version: 1', 'version: nope', $source),
            str_replace('audience: bogholder', 'audience: robot', $source),
            str_replace('title: Afstemning', "title: Afstemning\ntitle: Duplicate", $source),
            str_replace('title: Afstemning', '  title: nested', $source),
            str_replace('slug: alpha', 'slug: ../alpha', $source),
            str_replace('"list_journals"', '"propose_journal_entry"', $source),
            str_replace('"list_journals"', '"missing_tool"', $source),
            str_replace('finans/kladdeliste.php', 'logud.php', $source),
            str_replace('"check_args":{}', '"check_args":[]', $source),
            str_replace('"check_args":{}', '"check_args":{"unknown":"value"}', $source),
            str_replace('"s2"', '"s1"', $source),
            str_replace('```json saldi-steps', '```json', $source),
            $source . "\n```text\nnot the steps\n```\n",
            str_replace('"$period_start"', 'invalid-json', $source),
        ];
        foreach ($variants as $variant) {
            $this->error('VALIDATION_ERROR', fn() => SaldiSkillTools::parsePack($variant));
        }
        $this->error('VALIDATION_ERROR', fn() => SaldiSkillTools::parsePack($source, 'other-slug'));
    }

    public function testUnknownSlugsAndEmptyDirectoryHaveHonestResults(): void
    {
        foreach (['unknown-slug', '../alpha', 'alpha/../../passwd'] as $slug) {
            $error = $this->error('NOT_FOUND', fn() => SaldiSkillTools::getSkill(
                $this->env->ctx(),
                $this->env->db,
                ['slug' => $slug],
                $this->fixtures()
            ));
            SaldiSchemaValidator::validate([
                'ok' => false, 'committed' => false,
                'error' => ['code' => $error->errorCode, 'class' => $error->errorClass(), 'message_da' => 'Ikke fundet',
                    'message_en' => $error->getMessage()],
            ], SaldiToolRegistry::default()->get('get_procedure')->outputSchema());
        }
        $empty = SaldiSkillTools::listSkills(
            $this->env->ctx(),
            $this->env->db,
            [],
            $this->fixtures() . '/missing'
        );
        self::assertSame([], $empty['data']['rows']);
        self::assertSame(0, $empty['result_count']);
    }

    public function testEveryRealPackOnDiskIsValidIncludingAnEmptyCollection(): void
    {
        $packs = SaldiSkillTools::packs();
        self::assertIsArray($packs);
        foreach ($packs as $pack) {
            self::assertSame($pack['slug'], SaldiSkillTools::parsePack(
                file_get_contents(__DIR__ . '/../../../includes/automation/skills/' . $pack['slug'] . '/SKILL.md'),
                $pack['slug']
            )['slug']);
        }
    }
}
