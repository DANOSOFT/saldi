<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/ocr-token.php --- ver 5.0.0 --- 2026.10.01
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
require_once __DIR__ . '/../../../core/OcrKeys.php';

final class OcrTokenEndpoint extends BaseEndpoint
{
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
        if (ifset($_SERVER, 'REQUEST_METHOD', '') !== 'POST') {
            header('Allow: POST');
            $this->sendResponse(false, null, 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    /** The empty POST body carries no claims; identity always comes from the access token. */
    protected function handlePost($data)
    {
        if ($data !== null && (!is_object($data) || get_object_vars($data) !== [])) {
            $this->sendError('validation_failed', 'OCR token request must be empty or {}', 422);
        }
        $payload = JWTAuth::validateToken();
        header('Cache-Control: no-store');
        $this->sendResponse(true, (new OcrKeys())->mint(Subject::installation(), $this->subject, (int)$payload['tenant_id']));
    }
}

(new OcrTokenEndpoint())->handleRequestMethod();
