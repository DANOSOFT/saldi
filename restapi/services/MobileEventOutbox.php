<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/MobileEventOutbox.php --- ver 5.0.0 --- 2026.10.05
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

/** Transactional, deduplicated tenant events; the caller retains transaction ownership. */
final class MobileEventOutbox
{
    /** @return bool True for a new event, false when the tenant dedupe key already exists. */
    public static function emit($conn, string $type, string $dedupeKey, ?string $refType,
        ?int $refId, array $payload, ?string $targetSubject): bool
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $params = [$type, $dedupeKey, $refType, $refId, $json, $targetSubject];
        if ($conn instanceof mysqli) {
            $statement = $conn->prepare('INSERT INTO mobile_events(type,dedupe_key,ref_type,ref_id,payload,target_subject)
                VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
            if (!$statement || !$statement->execute($params)) {
                throw new RuntimeException('Event outbox insert failed');
            }
            $inserted = $statement->affected_rows === 1;
            $statement->close();
            return $inserted;
        }
        $result = pg_query_params($conn, 'INSERT INTO mobile_events(type,dedupe_key,ref_type,ref_id,payload,target_subject)
            VALUES ($1,$2,$3,$4,$5,$6) ON CONFLICT (dedupe_key) DO NOTHING RETURNING id', $params);
        if (!$result) {
            throw new RuntimeException('Event outbox insert failed');
        }
        return pg_num_rows($result) === 1;
    }
}
