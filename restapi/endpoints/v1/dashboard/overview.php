<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/dashboard/overview.php --- ver 5.0.0 --- 2026.10.01
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
require_once __DIR__ . '/../../../services/OverviewService.php';

final class DashboardOverviewEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} Own queue always; money separately requires finans/read. */
    protected function requiredRight(): ?array
    {
        return ['module' => 'bilag', 'action' => 'own'];
    }

    /** Require the token to select its tenant before the common identity and rights checks. */
    protected function checkAuthorization()
    {
        $payload = JWTAuth::validateToken();
        if (!$payload || filter_var(ifset($payload, 'tenant_id'), FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]) === false) {
            $this->sendError('invalid_token', 'A tenant access token is required', 401);
        }
        return parent::checkAuthorization();
    }

    /** Only read methods; do not expose cacheable tenant financial data. */
    public function handleRequestMethod()
    {
        header('Cache-Control: private, no-store');
        if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'GET') {
            header('Allow: GET');
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    /** Read current rights, not token claims, before calling the money service. */
    protected function handleGet($id = null)
    {
        $query = $_GET;
        try {
            $asOf = OverviewService::date(ifset($query, 'as_of', OverviewService::today()));
            $year = ifset($query, 'year');
            if (array_key_exists('year', $query) && (!is_string($year)
                || !preg_match('/^[1-9][0-9]*$/D', $year)
                || filter_var($year, FILTER_VALIDATE_INT) === false)) {
                throw new InvalidArgumentException('year must be a positive internal fiscal-year id');
            }
            $service = new OverviewService($this->conn);
            $canReadMoney = $this->principal === 'registry';
            if (!$canReadMoney) {
                $result = pg_query_params($this->conn, 'SELECT rettigheder FROM brugere WHERE id=$1', [(int)$this->userId]);
                if ($result === false) {
                    throw new RuntimeException('Cannot read overview rights');
                }
                $user = pg_fetch_assoc($result);
                $canReadMoney = $user && Rights::allows((string)$user['rettigheder'], 'finans', 'read');
            }
            $data = $service->overview($this->subject, $canReadMoney, $asOf, $year === null ? null : (int)$year);
        } catch (InvalidArgumentException $e) {
            $this->sendError('validation_failed', $e->getMessage(), 422);
            return;
        }
        $this->sendResponse(true, $data);
    }
}

(new DashboardOverviewEndpoint())->handleRequestMethod();
