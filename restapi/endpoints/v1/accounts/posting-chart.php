<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/accounts/posting-chart.php --- ver 5.0.0 --- 2026.10.01
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
require_once __DIR__ . '/../../../models/finans/VatModel.php';

final class PostingChartEndpoint extends BaseEndpoint
{
    private const MAX_ACCOUNTS = 5000;

    /** @return array{module:string, action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'bilag', 'action' => 'own'];
    }

    /** Require a token-bound tenant even for legacy access tokens on these new operations. */
    protected function checkAuthorization()
    {
        $payload = JWTAuth::validateToken();
        if (!$payload || filter_var(ifset($payload, 'tenant_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            $this->sendError('invalid_token', 'A tenant access token is required', 401);
        }
        $this->db = JWTAuth::getTenantDatabase();
        if (!$this->db) {
            $this->sendError('invalid_token', 'Token tenant does not exist', 401);
        }
        $this->userId = (int)$payload['user_id'];
        $this->username = ifset($payload, 'username', '');
        global $sqhost, $squser, $sqpass;
        $this->conn = db_connect($sqhost, $squser, $sqpass, $this->db, __FILE__ . ' linje ' . __LINE__);
        if (!$this->conn) {
            throw new RuntimeException('Cannot open token tenant');
        }
        return true;
    }

    public function handleRequestMethod()
    {
        if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'GET') {
            header('Allow: GET');
            $this->sendResponse(false, null, 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    /** @return int A validated internal fiscal-year id. */
    private function referenceYear(): int
    {
        $requested = ifset($_GET, 'year');
        if (array_key_exists('year', $_GET) && (!is_string($requested)
            || !preg_match('/^[1-9][0-9]*$/D', $requested)
            || filter_var($requested, FILTER_VALIDATE_INT) === false)) {
            $this->sendError('validation_failed', 'year must be a positive internal fiscal-year id', 422);
        }
        $year = VatModel::resolveReferenceYear($requested === null ? null : (int)$requested);
        if ($year === null) {
            $this->sendError('validation_failed', 'No matching fiscal year', 422);
        }
        return $year;
    }

    /** Return one complete, bounded year's open chart; select no financial balances. */
    protected function handleGet($id = null)
    {
        $year = $this->referenceYear();
        $fetch = self::MAX_ACCOUNTS + 1;
        $query = db_select("SELECT kontonr, beskrivelse, moms, kontotype FROM kontoplan
            WHERE regnskabsaar=$year AND (lukket IS NULL OR TRIM(lukket)='')
            ORDER BY kontonr, id LIMIT $fetch", __FILE__ . ' linje ' . __LINE__);
        $accounts = [];
        while ($row = db_fetch_array($query)) {
            if (count($accounts) === self::MAX_ACCOUNTS) {
                $this->sendError('payload_too_large', 'Posting chart exceeds the 5000-row limit', 413);
            }
            $accounts[] = [
                'kontonr' => (int)$row['kontonr'],
                'beskrivelse' => (string)$row['beskrivelse'],
                'moms' => (string)$row['moms'],
                'kontotype' => (string)$row['kontotype'],
            ];
        }
        $this->sendResponse(true, $accounts);
    }
}

(new PostingChartEndpoint())->handleRequestMethod();
