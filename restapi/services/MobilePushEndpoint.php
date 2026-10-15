<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/MobilePushEndpoint.php --- ver 5.0.0 --- 2026.10.01
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
require_once __DIR__ . '/../core/BaseEndpoint.php';
require_once __DIR__ . '/ReminderService.php';

/** Shared tenant selection and predictable validation errors for the E4 endpoints. */
abstract class MobilePushEndpoint extends BaseEndpoint
{
    protected int $tenantId;

    /** @return list<string> */
    abstract protected function methods(): array;

    protected function checkAuthorization()
    {
        $payload = JWTAuth::validateToken();
        if (!$payload || filter_var(ifset($payload, 'tenant_id'), FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) {
            $this->sendError('invalid_token', 'A tenant access token is required', 401);
        }
        $this->tenantId = (int)$payload['tenant_id'];
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
        if (!in_array(ifset($_SERVER, 'REQUEST_METHOD', ''), $this->methods(), true)) {
            header('Allow: ' . implode(', ', $this->methods()));
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    /** Map expected service errors without exposing internal exception details. */
    protected function handleError($error = null)
    {
        if ($error instanceof MobileReminderError) {
            $this->sendError($error->error, $error->getMessage(), $error->status);
        }
        parent::handleError($error);
    }

    /** @return ReminderService */
    protected function reminders(): ReminderService
    {
        return new ReminderService($this->conn, MobileAuth::connectRegistry(), $this->tenantId);
    }

    /** @return int */
    protected function positive($value, string $name, int $max = PHP_INT_MAX): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value > $max) {
            $this->sendError('validation_failed', "$name must be a positive integer (maximum $max)", 422);
        }
        return (int)$value;
    }

    /** @return array{limit:int,before:int|null} */
    protected function pagination(): array
    {
        return [
            'limit'=>$this->positive(ifset($_GET, 'limit', 50), 'limit', 100),
            'before'=>array_key_exists('before', $_GET) ? $this->positive($_GET['before'], 'before') : null,
        ];
    }
}
