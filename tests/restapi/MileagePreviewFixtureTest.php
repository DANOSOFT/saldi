<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MileagePreviewFixtureTest.php --- ver 5.0.0 --- 2026.10.15
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by the Free Software Foundation; either in version 2
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
// 20261001 CDX/LH Created.
// 20261015 CL/LH Moved from the app (now DANOSOFT/Saldi-Mobile); the fixture file is shared with its Jest tests.

require_once __DIR__ . '/support/T4ClaimsTestCase.php';

final class MileagePreviewFixtureTest extends T4ClaimsTestCase
{
    /** The same exact fixtures are consumed by the app's TypeScript tests (Saldi-Mobile __tests__/claims/preview-fixtures.json). */
    public function test_app_fixtures_equal_the_unmodified_server_allocation_engine(): void
    {
        $fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures/mileage-preview.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixtures as $index => $fixture) {
            $year = (int)$fixture['rates']['year'];
            self::setting('km_rate_' . $year, (string)$fixture['rates']['rate']);
            self::setting('km_rate_low_' . $year, (string)$fixture['rates']['rate_low']);
            self::setting('km_threshold_' . $year, (string)$fixture['rates']['threshold_km']);
            $scope = 'ansat:preview-fixture-' . (int)$index;
            $this->statement($scope, $year, (string)$fixture['consumed']);
            $trips = array_map(static fn(array $trip): array => [
                'id' => (int)$trip['id'], 'trip_date' => (string)$trip['date'], 'km' => (string)$trip['km'],
            ], $fixture['trips']);
            $allocation = mileage_allocate($scope, $year, $trips, self::$conn, true);
            $expected = $fixture['expected'];
            foreach ($expected['lines'] as &$line) {
                unset($line['rate_preview']);
            }
            unset($line);
            $this->assertSame($expected, $allocation, $fixture['name']);
            // ClaimsService uses this weighted rate (before amount rounding) in trip responses.
            foreach ($allocation['lines'] as $position => $line) {
                $distance = mileage_units($line['km'], 1, 20000);
                $weighted = mileage_units($line['km_high'], 1, 20000) * mileage_units($line['rate'], 2, 100000)
                    + mileage_units($line['km_low'], 1, 20000) * mileage_units($line['rate_low'], 2, 100000);
                $rate = mileage_decimal(intdiv($weighted + intdiv($distance, 2), $distance), 2);
                $this->assertSame($fixture['expected']['lines'][$position]['rate_preview'], $rate, $fixture['name']);
            }
        }
    }
}
