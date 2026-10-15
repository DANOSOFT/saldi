<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/poolReviewVersion.php --- ver 5.0.0 --- 2026.10.05
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
// 20261001 CDX/LH Created.

require_once __DIR__ . '/poolMetadata.php';
require_once __DIR__ . '/../std_func.php';

/** @return string Unquoted SHA-256 review validator, including vendor-only rematches. */
function pool_review_version(array $row): string
{
    $values = [poolMetadataVersion($row)];
    foreach (['status','review_state','vat_code','bank_line_id','vendor_name','vendor_cvr',
        'vendor_iban','vendor_konto_id','vendor_match','vendor_score'] as $field) {
        $values[] = (string)ifset($row, $field, '');
    }
    return hash('sha256', serialize($values));
}
