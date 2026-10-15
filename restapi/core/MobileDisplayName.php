<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/MobileDisplayName.php --- ver 5.0.0 --- 2026.10.02
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

require_once __DIR__ . '/MobileAuth.php';

/** Resolve sender identities in their principal namespace and the selected tenant. */
final class MobileDisplayName
{
    private MobileSchema $tenant;
    private ?MobileSchema $registry;
    private array $names = [];

    public function __construct($tenantConnection, $registryConnection = null)
    {
        $this->tenant = new MobileSchema($tenantConnection);
        $this->registry = $registryConnection === null ? null : new MobileSchema($registryConnection);
    }

    /**
     * Resolve the current employee/user name, then username; unavailable identities retain their subject.
     * @return string Sender label; cached only for this response's resolver instance.
     */
    public function sender(string $subject): string
    {
        if (array_key_exists($subject, $this->names)) {
            return $this->names[$subject];
        }
        try {
            $identity = Subject::parse($subject);
        } catch (InvalidArgumentException $e) {
            return $this->names[$subject] = $subject;
        }
        if ($identity['installation'] !== Subject::installation()) {
            return $this->names[$subject] = $subject;
        }
        if ($identity['principal'] === 'registry') {
            $this->registry = $this->registry ?? new MobileSchema(MobileAuth::connectRegistry());
            $store = $this->registry;
        } else {
            $store = $this->tenant;
        }
        $id = (int)$identity['user_id'];
        $user = ifset($store->rows("SELECT * FROM brugere WHERE id=$id"), 0, []);
        $name = trim((string)ifset($user, 'navn', ''));
        $employee = (int)ifset($user, 'ansat_id', 0);
        if ($identity['principal'] === 'account' && $employee > 0) {
            $employees = $store->rows("SELECT navn FROM ansatte WHERE id=$employee");
            $name = trim((string)ifset($employees, [0, 'navn'], $name));
        }
        $username = (string)ifset($user, 'brugernavn', $subject);
        return $this->names[$subject] = $name !== '' ? $name : $username;
    }
}
