<?php

//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/tools/SaldiSchemaValidatorTest.test.php --- ai/mcp-assistant --- 2026.10.04
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
// JSON Schema subset, Unicode boundaries and precise decimal formatting tests.
//
// 20261001 CDX/LH Created.
// 20261002 CDX/LH Cover companion foundation contracts.
// 20261004 CDX/LH Add companion part 2 foundation contracts.

declare(strict_types=1);

require_once __DIR__ . '/../../../includes/tools/SaldiToolRegistry.php';

final class SaldiSchemaValidatorTest extends PHPUnit\Framework\TestCase
{
    public function testSubsetAndNestedFields(): void
    {
        $schema = SaldiToolSchemas::object([
            'text' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 3, 'pattern' => '^[æøå]+$'],
            'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2],
            'fixed' => ['const' => false],
            'branch' => ['enum' => ['D', 'K']],
            'rows' => SaldiToolSchemas::list(
                SaldiToolSchemas::object(['id' => ['type' => 'integer']]),
                1
            ) + ['minItems' => 1],
        ]);
        $good = ['text' => 'æø', 'count' => 1, 'fixed' => false, 'branch' => 'D', 'rows' => [['id' => 7]]];
        SaldiSchemaValidator::validate($good, $schema);
        $badCases = [
            array_replace($good, ['text' => 'x']), array_replace($good, ['text' => 'æøåæ']),
            array_replace($good, ['count' => 0]), array_replace($good, ['count' => 3]),
            array_replace($good, ['count' => '1']), array_replace($good, ['fixed' => true]),
            array_replace($good, ['branch' => 'S']), array_replace($good, ['rows' => []]),
            array_replace($good, ['rows' => [['id' => 1], ['id' => 2]]]),
            array_replace($good, ['rows' => [['id' => 'bad', 'hidden' => true]]]),
            $good + ['hidden' => true], array_diff_key($good, ['text' => true]),
        ];
        foreach ($badCases as $bad) {
            try {
                SaldiSchemaValidator::validate($bad, $schema);
                self::fail('Invalid input was accepted');
            } catch (SaldiRuntimeError $e) {
                self::assertSame('VALIDATION_ERROR', $e->errorCode);
                self::assertNotEmpty($e->detail['fields']);
            }
        }
        self::assertTrue(true);
    }

    public function testNullUnionsBooleansObjectsAndNumbers(): void
    {
        foreach ([null, 1] as $value) {
            SaldiSchemaValidator::validate($value, ['type' => ['integer', 'null']]);
        }
        SaldiSchemaValidator::validate((object)[], SaldiToolSchemas::object([]));
        SaldiSchemaValidator::validate(1.5, ['type' => 'number', 'minimum' => 1]);
        $this->expectException(SaldiRuntimeError::class);
        SaldiSchemaValidator::validate(1, ['type' => 'boolean']);
    }

    public function testEmptyArrayIsNotAnObjectAndErrorPathsAreBounded(): void
    {
        foreach ([[], ['rows' => []]] as $input) {
            try {
                $schema = $input === []
                    ? ['type' => 'object']
                    : SaldiToolSchemas::object(['rows' => ['type' => 'object']]);
                SaldiSchemaValidator::validate($input, $schema);
                self::fail('Array accepted as object');
            } catch (SaldiRuntimeError $e) {
                self::assertSame('VALIDATION_ERROR', $e->errorCode);
            }
        }
        $properties = [];
        for ($i = 0; $i < 200; $i++) {
            $properties[str_repeat('x', 600) . $i] = true;
        }
        try {
            SaldiSchemaValidator::validate((object)$properties, SaldiToolSchemas::object([]));
            self::fail('Unknown properties accepted');
        } catch (SaldiRuntimeError $e) {
            self::assertCount(100, $e->detail['fields']);
            self::assertSame(500, mb_strlen($e->detail['fields'][0]));
        }
        self::assertSame(
            '{"a":{},"b":{"c":1,"d":2}}',
            SaldiToolRuntime::canonical((object)['b' => (object)['d' => 2,
                        'c' => 1],
            'a' => (object)[]])
        );
    }

    public function testMoneyRoundsHalfAwayWithoutFloatLoss(): void
    {
        foreach (
            ['1.005' => '1.01',
                '-1.005' => '-1.01',
                '0.004' => '0.00',
                '-0.004' => '0.00',
                '999999999999.995' => '1000000000000.00',
                '1250.1' => '1250.10'] as $stored => $expected
        ) {
            self::assertSame($expected, SaldiToolRuntime::money($stored));
        }
    }

    public function testCatalogueMetadataAndDescriptionSlots(): void
    {
        $tools = SaldiToolRegistry::default()->all();
        self::assertCount(54, $tools);
        foreach ($tools as $tool) {
            foreach (
                ['WHAT:',
                    'READS:',
                    'WRITES:',
                    'IRREVERSIBILITY:',
                    'PRECONDITIONS:',
                    'SAFE FALLBACK:',
                    'NEXT STEP:'] as $slot
            ) {
                self::assertStringContainsString($slot, $tool->description);
            }
            self::assertFalse($tool->inputSchema['additionalProperties']);
            self::assertSame($tool->effect === 'read' ? 'auto' : 'gated', $tool->tier);
            SaldiSchemaValidator::validate(
                ['ok' => false,
                    'committed' => false,
                    'error' => ['code' => 'UNAVAILABLE',
                        'class' => 'RETRYABLE',
                        'message_en' => 'Unavailable',
                        'detail' => (object)[]]],
                $tool->outputSchema()
            );
        }
    }
}
