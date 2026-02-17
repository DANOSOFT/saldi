<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/gs1_parserformfunk.php --- patch 4.1.1 --- 2026-01-03 ---
//                           LICENSE
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------


/**
 * Parse a GS1 barcode string into its component Application Identifiers.
 *
 * Supports both parenthesized format: (01)04012345678901(10)ABC123
 * and raw format with FNC1/GS separators: 010401234567890110ABC123
 *
 * @param string $barcode Raw GS1 string
 * @return array [ ['ai' => '01', 'label' => 'GTIN', 'value' => '...'], ... ]
 */
function parseGS1(string $barcode): array
{
    $fnc1 = chr(29);

    // Normalise alternative FNC1/GS representations to chr(29)
    $barcode = str_replace(['^', '{GS}'], $fnc1, $barcode);

    // AI definitions: ai => [ai_length, fixed_data_length|null, max_data_length, label]
    $aiDefs = [
        // 2-char AIs
        '00'  => [2, 18, 18,  'SSCC'],
        '01'  => [2, 14, 14,  'GTIN'],
        '02'  => [2, 14, 14,  'CONTENT'],
        '03'  => [2, 14, 14,  'MTO GTIN'],
        '10'  => [2, null, 20, 'BATCH/LOT'],
        '11'  => [2, 6, 6,    'PROD DATE'],
        '12'  => [2, 6, 6,    'DUE DATE'],
        '13'  => [2, 6, 6,    'PACK DATE'],
        '15'  => [2, 6, 6,    'BEST BEFORE'],
        '16'  => [2, 6, 6,    'SELL BY'],
        '17'  => [2, 6, 6,    'USE BY'],
        '20'  => [2, 2, 2,    'VARIANT'],
        '21'  => [2, null, 20, 'SERIAL'],
        '22'  => [2, null, 20, 'CPV'],
        '30'  => [2, null, 8,  'COUNT'],
        '37'  => [2, null, 8,  'COUNT OF TRADE ITEMS'],
        // 3-char AIs
        '235' => [3, null, 28, 'TPX'],
        '240' => [3, null, 30, 'ADDITIONAL ID'],
        '241' => [3, null, 30, 'CUST. PART No.'],
        '242' => [3, null, 6,  'MTO VARIANT'],
        '243' => [3, null, 20, 'PCN'],
        '250' => [3, null, 30, 'SECONDARY SERIAL'],
        '251' => [3, null, 30, 'REF. TO SOURCE'],
        '400' => [3, null, 30, 'CUSTOMER PO No.'],
        '401' => [3, null, 30, 'CONSIGNMENT No.'],
        '410' => [3, 13, 13,  'SHIP TO'],
        '411' => [3, 13, 13,  'BILL TO'],
        '412' => [3, 13, 13,  'PURCHASE FROM'],
        '413' => [3, 13, 13,  'SHIP FOR'],
        '414' => [3, 13, 13,  'IDENTIFICATION'],
        '415' => [3, 13, 13,  'PAY TO'],
        '416' => [3, 13, 13,  'PROD/SERV LOC'],
        '420' => [3, null, 20, 'SHIP TO POST'],
        '421' => [3, null, 12, 'SHIP TO POST ISO'],
        '422' => [3, 3, 3,    'ORIGIN'],
        '423' => [3, null, 15, 'COUNTRY INITIAL PROCESS'],
        '424' => [3, 3, 3,    'COUNTRY PROCESS'],
        '425' => [3, null, 15, 'COUNTRY DISASSEMBLY'],
        '426' => [3, 3, 3,    'COUNTRY FULL PROCESS'],
    ];

    // 4-char measurement AIs (3xxx): last digit = implied decimal places (0-9), data always 6 digits fixed.
    // 310x-316x: metric weight/dimensions; 320x-329x: imperial weight/dimensions;
    // 330x-337x: gross metric; 340x-349x: gross imperial; 350x-357x: area; 360x-369x: volume.
    $measure4 = [
        '310' => 'NET WEIGHT (kg)',    '311' => 'LENGTH (m)',
        '312' => 'WIDTH (m)',          '313' => 'HEIGHT (m)',
        '314' => 'AREA (m2)',          '315' => 'NET VOLUME (l)',
        '316' => 'NET VOLUME (m3)',
        '320' => 'NET WEIGHT (lb)',    '321' => 'LENGTH (in)',
        '322' => 'LENGTH (ft)',        '323' => 'LENGTH (yd)',
        '324' => 'WIDTH (in)',         '325' => 'WIDTH (ft)',
        '326' => 'WIDTH (yd)',         '327' => 'HEIGHT (in)',
        '328' => 'HEIGHT (ft)',        '329' => 'HEIGHT (yd)',
        '330' => 'GROSS WEIGHT (kg)',  '331' => 'LENGTH (m) LOG',
        '332' => 'WIDTH (m) LOG',      '333' => 'HEIGHT (m) LOG',
        '334' => 'AREA (m2) LOG',      '335' => 'GROSS VOLUME (l)',
        '336' => 'GROSS VOLUME (m3)', '337' => 'KG PER m2',
        '340' => 'GROSS WEIGHT (lb)', '341' => 'LENGTH (in) LOG',
        '342' => 'LENGTH (ft) LOG',   '343' => 'LENGTH (yd) LOG',
        '344' => 'WIDTH (in) LOG',    '345' => 'WIDTH (ft) LOG',
        '346' => 'WIDTH (yd) LOG',    '347' => 'HEIGHT (in) LOG',
        '348' => 'HEIGHT (ft) LOG',   '349' => 'HEIGHT (yd) LOG',
        '350' => 'AREA (in2)',         '351' => 'AREA (ft2)',
        '352' => 'AREA (yd2)',         '355' => 'AREA (in2) LOG',
        '356' => 'AREA (ft2) LOG',     '357' => 'AREA (yd2) LOG',
        '360' => 'NET VOLUME (qt)',    '361' => 'NET VOLUME (gal)',
        '362' => 'GROSS VOLUME (qt)', '363' => 'GROSS VOLUME (gal)',
        '364' => 'VOLUME (in3)',       '365' => 'VOLUME (ft3)',
        '366' => 'VOLUME (yd3)',       '367' => 'VOLUME (in3) LOG',
        '368' => 'VOLUME (ft3) LOG',  '369' => 'VOLUME (yd3) LOG',
    ];
    foreach ($measure4 as $prefix => $label) {
        for ($d = 0; $d <= 9; $d++) {
            $aiDefs["{$prefix}{$d}"] = [4, 6, 6, $label];
        }
    }

    // 4-char price/amount AIs (390x–395x): last digit = implied decimal places (0-9)
    $price4 = [
        '390' => [null, 15, 'AMOUNT'],
        '391' => [null, 18, 'AMOUNT ISO'],
        '392' => [null, 15, 'PRICE'],
        '393' => [null, 18, 'PRICE ISO'],
        '394' => [4,    4,  'PCT OFF'],
        '395' => [6,    6,  'PRICE PER UNIT'],
    ];
    foreach ($price4 as $prefix => [$fixedLen, $maxLen, $label]) {
        for ($d = 0; $d <= 9; $d++) {
            $aiDefs["{$prefix}{$d}"] = [4, $fixedLen, $maxLen, $label];
        }
    }

    // Sort by AI length descending so longer prefixes match first
    uksort($aiDefs, fn($a, $b) => strlen($b) <=> strlen($a));

    // Detect parenthesized format
    $hasParens = str_contains($barcode, '(');

    if ($hasParens) {
        // Extract AI/value pairs directly from parens
        preg_match_all('/\((\d{2,4})\)([^(]*)/', $barcode, $matches, PREG_SET_ORDER);

        $results = [];
        foreach ($matches as $m) {
            $ai = $m[1];
            $value = str_replace($fnc1, '', $m[2]);

            $label = 'UNKNOWN';
            if (isset($aiDefs[$ai])) {
                $label = $aiDefs[$ai][3];
            }

            $results[] = [
                'ai'    => $ai,
                'label' => $label,
                'value' => $value,
            ];
        }
        return $results;
    }

    // Raw format parsing (no parentheses, FNC1/GS delimited)
    $results = [];
    $pos = 0;
    $len = strlen($barcode);

    while ($pos < $len) {
        if ($barcode[$pos] === $fnc1) {
            $pos++;
            continue;
        }

        $matched = false;

        foreach ($aiDefs as $ai => $def) {
            [$aiLen, $fixedLen, $maxLen, $label] = $def;

            if (substr($barcode, $pos, $aiLen) === (string)$ai) {
                $dataStart = $pos + $aiLen;

                if ($fixedLen !== null) {
                    // FNC1 within a nominally fixed-length field acts as an early terminator
                    $fnc1InField = strpos($barcode, $fnc1, $dataStart);
                    if ($fnc1InField !== false && $fnc1InField < $dataStart + $fixedLen) {
                        $value = substr($barcode, $dataStart, $fnc1InField - $dataStart);
                        $pos = $fnc1InField;
                    } else {
                        $value = substr($barcode, $dataStart, $fixedLen);
                        $pos = $dataStart + $fixedLen;
                    }
                } else {
                    // Variable length — terminated by FNC1 or end of string
                    $fnc1Pos = strpos($barcode, $fnc1, $dataStart);
                    if ($fnc1Pos === false) {
                        $fnc1Pos = $len;
                    }
                    $endPos = min($fnc1Pos, $dataStart + $maxLen);
                    $value = substr($barcode, $dataStart, $endPos - $dataStart);
                    $pos = $endPos;
                }

                $results[] = [
                    'ai'    => $ai,
                    'label' => $label,
                    'value' => $value,
                ];

                $matched = true;
                break;
            }
        }

        if (!$matched) {
            $pos++;
        }
    }

    return $results;
}

// --- Example usage ---
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $fnc1 = chr(29);

    $testBarcode = '(01)40192155942545(10)A8CEFB(21)11222233';
    // $testBarcode = "{$fnc1}1010950400005911817271231107654321D{$fnc1}2110987";
    // $testBarcode = "010001234500005817271231";
    // $testBarcode = "010001234500005810ABC123^1727123121123456";
    // $testBarcode = "0100012345000058230300125617271231";
    // $testBarcode = "010952110153001817271231";
    // $testBarcode = "010952110153001822ABC^17271231";
    // $testBarcode = "010952110153001810ABC123^17271231";
    // $testBarcode = "010952110153001810ABC123^21123456^17271231";
    // $testBarcode = "0109521101530018172712313202001256";
    // $testBarcode = "010952110153001817271231";
    // $testBarcode = "010952110153001822ABC^17271231";
    // $testBarcode = "010952110153001810ABC123^17271231";
    // $testBarcode = "010952110153001810ABC123^21123456^17271231";
    // $testBarcode = "0109521101530018230200125617271231";
    // $testBarcode = "01035105{GS}11220101{GS}10A1B2C3D4";
    $parsed = parseGS1($testBarcode);

    echo "Input: $testBarcode\n\n";
    foreach ($parsed as $item) {
        printf("AI (%s) %s: %s\n", $item['ai'], $item['label'], $item['value']);
    }
}
// --- Example Output ---
/*
* php gs1_parser.php 
* Input: (01)40192155942545(10)A8CEFB(21)11222233
* 
* AI (01) GTIN: 40192155942545
* AI (10) BATCH/LOT: A8CEFB
* AI (21) SERIAL: 11222233
*/