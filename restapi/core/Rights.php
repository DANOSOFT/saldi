<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/core/Rights.php --- ver 5.0.0 --- 2026.10.08
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
// 20261001 CDX/LH Decode case Login and approval offsets and account-only employee declarations.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

require_once __DIR__ . '/../../includes/permissions/SaldiRightsTable.php';

final class Rights
{
    private const POSITIONS = SaldiRightsTable::LEGACY_MODULES;
    private const CASE_POSITIONS = ['sager' => 2, 'sager_loen' => 0, 'sager_godkend' => 6];

    /**
     * Navigation flags; declarations may use the precise web module names too.
     * @return array{finans:bool, debitor:bool, lager:bool, sager:bool, bilag:bool, admin:bool, assistant:bool}
     */
    public static function tenant(string $digits, ?string $caseDigits = null, bool $revisor = false): array
    {
        $rights = [];
        foreach (['finans', 'debitor', 'lager', 'sager', 'bilag', 'admin', 'assistant'] as $module) {
            $rights[$module] = self::allows($digits, $module, 'read', $caseDigits, $revisor);
        }
        return $rights;
    }

    /**
     * Digit 1 permits writes, 2 is read only. Empty rights retain the web's unrestricted treatment.
     * Case actions use employee group box2; employee/own also needs EmployeeContext in middleware.
     */
    public static function allows(string $digits, string $module, string $action, ?string $caseDigits = null, bool $revisor = false): bool
    {
        if (!in_array($action, ['read', 'write', 'own'], true)
            || (!isset(self::CASE_POSITIONS[$module]) && $module !== 'employee' && $module !== 'assistant' && !isset(self::POSITIONS[$module]))) {
            return false;
        }
        if ($module === 'employee') {
            return $action === 'own' && !$revisor;
        }
        if (in_array($module, ['sager_loen','sager_godkend'], true) && $revisor) {
            return false;
        }
        if ($module === 'sager_godkend' && $action !== 'write') {
            return false;
        }
        if ($action === 'own') {
            return $module === 'bilag';
        }
        if ($revisor) {
            return true;
        }
        if (isset(self::CASE_POSITIONS[$module])) {
            return (bool)substr((string)$caseDigits, self::CASE_POSITIONS[$module], 1);
        }
        $positions = $module === 'assistant' ? SaldiRightsTable::MODULES['assistant'] : self::POSITIONS[$module];
        return SaldiRightsTable::digitAllows($digits, $positions, $action, $module !== 'assistant');
    }

    /** @return array{admin:bool, oprette:bool, slette:bool, tenant_ids:list<int>} */
    public static function registry(string $rights): array
    {
        $parts = array_pad(explode(',', $rights, 4), 4, '');
        $ids = [];
        foreach (explode(',', $parts[3]) as $id) {
            $id = trim($id);
            if (ctype_digit($id) && (int)$id > 0) {
                $ids[] = (int)$id;
            }
        }
        return ['admin' => (bool)$parts[0], 'oprette' => (bool)$parts[1], 'slette' => (bool)$parts[2],
            'tenant_ids' => array_values(array_unique($ids))];
    }
}
