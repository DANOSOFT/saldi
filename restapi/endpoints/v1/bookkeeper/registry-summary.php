<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/bookkeeper/registry-summary.php --- ver 5.0.0 --- 2026.10.05
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

final class BookkeeperRegistrySummaryEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'registry', 'action' => 'read'];
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
            $this->sendError('invalid_token', 'A registry access token is required', 401);
        }
        return parent::checkAuthorization();
    }

    protected function handleGet($id = null)
    {
        try {
            $service = new BookkeeperSummaryService(MobileAuth::connectRegistry());
            $data = $service->forRegistry((int)$this->userId, $this->subject, $_GET);
        } catch (MobileT2Error $e) {
            $this->sendError($e->error, 'Invalid paging', $e->status, ['details' => $e->details]);
            return;
        } catch (MobileAuthError $e) {
            $this->sendError($e->error, 'Permission denied', $e->status);
            return;
        }
        $this->sendResponse(true, $data);
    }
}

if (realpath((string)ifset($_SERVER, 'SCRIPT_FILENAME', '')) === __FILE__) {
    (new BookkeeperRegistrySummaryEndpoint())->handleRequestMethod();
}
