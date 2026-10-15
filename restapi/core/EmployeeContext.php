<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/EmployeeContext.php --- ver 5.0.0 --- 2026.10.06
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

require_once __DIR__ . '/MobileSchema.php';

/** Current employee identity from the selected tenant; never resolves a registry id as an account user. */
final class EmployeeContext
{
    public readonly string $principal;
    public readonly int $userId;
    public readonly ?int $ansatId;
    public readonly bool $linked;
    public readonly bool $ownCompany;
    public readonly ?string $caseDigits;
    /** @var array{id:int,navn:string,initialer:string,nummer:string,gruppe:int|null,lukket:string,konto_id:int|null}|null */
    public readonly ?array $employee;

    public function __construct($connection, string $principal, int $userId)
    {
        if (!in_array($principal, ['account', 'registry'], true) || $userId < 1) {
            throw new InvalidArgumentException('Invalid employee principal');
        }
        $this->principal = $principal;
        $this->userId = $userId;
        $row = null;
        if ($principal === 'account') {
            $store = new MobileSchema($connection);
            $rows = $store->rows("SELECT a.id,a.navn,a.initialer,a.nummer,a.gruppe,a.lukket,a.konto_id,g.box2,
                CASE WHEN a.konto_id=(SELECT id FROM adresser WHERE art='S' ORDER BY id LIMIT 1)
                    THEN 1 ELSE 0 END AS own_company
                FROM brugere b JOIN ansatte a ON a.id=b.ansat_id
                LEFT JOIN grupper g ON g.id=a.gruppe WHERE b.id=" . (int)$userId);
            $row = ifset($rows, 0);
        }
        $this->ansatId = $row === null ? null : (int)$row['id'];
        $this->linked = $row !== null && trim((string)$row['lukket']) !== 'on';
        $this->ownCompany = $this->linked && (int)$row['own_company'] === 1;
        $this->caseDigits = $row !== null && $row['box2'] !== null ? (string)$row['box2'] : null;
        $this->employee = $row === null ? null : [
            'id' => (int)$row['id'], 'navn' => (string)$row['navn'], 'initialer' => (string)$row['initialer'],
            'nummer' => (string)$row['nummer'], 'gruppe' => $row['gruppe'] === null ? null : (int)$row['gruppe'],
            'lukket' => (string)$row['lukket'], 'konto_id' => $row['konto_id'] === null ? null : (int)$row['konto_id'],
        ];
    }

    /** @return string|null Permission reason; foreign-company links remain valid for own operations. */
    public function denialReason(): ?string
    {
        return $this->linked ? null : 'no_employee_link';
    }
}
