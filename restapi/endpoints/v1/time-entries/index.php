<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/time-entries/index.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Use the non-accounting time identity without requiring a fiscal year covering today.

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../services/TimeEntryService.php';

final class TimeEntriesEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'sager_loen','action'=>$_SERVER['REQUEST_METHOD'] === 'GET' ? 'read' : 'write'];
    }

    private function service(): TimeEntryService
    {
        $payload = JWTAuth::validateToken();
        $users = (new MobileSchema($this->conn))->rows('SELECT brugernavn FROM brugere WHERE id=' . (int)$this->userId);
        $context = new TimeEntryContext($this->conn, $this->db, (int)$payload['tenant_id'],
            (string)$users[0]['brugernavn'], (int)$this->userId);
        return new TimeEntryService($context, $this->principal, $this->subject);
    }

    protected function handleError($error = null)
    {
        if ($error instanceof MobileT2Error) {
            $this->sendError($error->error, 'Time request refused', $error->status, ['details'=>$error->details]);
        }
        parent::handleError($error);
    }

    /** @return array<string,mixed> */
    private function body($data): array
    {
        if (!$data instanceof stdClass) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'body_invalid']);
        }
        return (array)$data;
    }

    private function respond(array $response): void
    {
        header('Cache-Control: private, no-store');
        foreach ($response['headers'] as $name=>$value) {
            header($name . ': ' . $value);
        }
        if (isset($response['body']['data']['etag'])) {
            header('ETag: ' . $response['body']['data']['etag']);
        }
        $this->sendResponse(true, $response['body']['data'], '', $response['status']);
    }

    private function methodNotAllowed(): void
    {
        header('Allow: GET, POST, PATCH, DELETE');
        $this->sendError('validation_failed', 'Method not allowed', 405);
    }
    protected function handleGet($id = null)
    {
        header('Cache-Control: private, no-store');
        $data = $id === null ? $this->service()->list($_GET) : $this->service()->detail($id);
        if (isset($data['etag'])) {
            header('ETag: ' . $data['etag']);
        }
        $this->sendResponse(true, $data);
    }
    protected function handlePost($data)
    {
        $this->respond($this->service()->create($this->body($data)));
    }
    protected function handlePut($data) { $this->methodNotAllowed(); }
    protected function handlePatch($data)
    {
        $this->respond($this->service()->update(ifset($_GET, 'id'), $this->body($data), ifset($_SERVER, 'HTTP_IF_MATCH')));
    }
    protected function handleDelete($data)
    {
        $this->respond($this->service()->delete(ifset($_GET, 'id'), $this->body($data), ifset($_SERVER, 'HTTP_IF_MATCH')));
    }
}

(new TimeEntriesEndpoint())->handleRequestMethod();
