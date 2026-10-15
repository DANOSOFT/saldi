<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/bookkeeper/summary.php --- ver 5.0.0 --- 2026.10.05
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

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../services/BookkeeperSummaryService.php';

final class BookkeeperSummaryEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'bilag', 'action' => 'read'];
    }

    public function handleRequestMethod()
    {
        header('Cache-Control: private, no-store');
        if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'GET') {
            header('Allow: GET');
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    protected function checkAuthorization()
    {
        if (!JWTAuth::validateToken()) {
            $this->sendError('invalid_token', 'A tenant access token is required', 401);
        }
        return parent::checkAuthorization();
    }

    protected function handleGet($id = null)
    {
        $registry = MobileAuth::connectRegistry();
        $payload = JWTAuth::validateToken();
        $tenantId = (int)JWTAuth::tenantId($payload);
        $store = new MobileSchema($registry);
        $tenant = ifset($store->rows('SELECT id,regnskab AS name FROM regnskab WHERE id=' . $tenantId), 0);
        if (!$tenant) {
            $this->sendError('permission_denied', 'Tenant unavailable', 403);
        }
        $rights = null;
        if ($this->principal !== 'registry') {
            $user = ifset((new MobileSchema($this->conn))->rows('SELECT rettigheder FROM brugere WHERE id=' . (int)$this->userId), 0);
            if (!$user || !Rights::allows((string)$user['rettigheder'], 'bilag', 'read')) {
                $this->sendError('permission_denied', 'Permission denied', 403);
            }
            $rights = ['debitorrapport' => ['read' => Rights::allows((string)$user['rettigheder'], 'debitorrapport', 'read')]];
        }
        mobileTenantUpdates($this->conn);
        $data = (new BookkeeperSummaryService($registry))->forTenant($this->conn, $rights, $tenant);
        $this->sendResponse(true, $data);
    }
}

if (realpath((string)ifset($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
    (new BookkeeperSummaryEndpoint())->handleRequestMethod();
}
