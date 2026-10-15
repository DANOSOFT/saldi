<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/stock/counts/index.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.
require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../services/StockCountService.php';

final class StockCountsEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'varer','action'=>ifset($_SERVER, 'REQUEST_METHOD') === 'GET' ? 'read' : 'write'];
    }

    private function service(): StockCountService
    {
        $payload = JWTAuth::validateToken();
        $context = EngineContext::forConfig($this->conn, $this->db, (int)$payload['tenant_id'], $this->username, (int)$this->userId);
        return new StockCountService($context, $this->subject);
    }

    protected function handleError($error = null)
    {
        if ($error instanceof MobileT2Error) {
            $this->sendError($error->error, 'Stock count request refused', $error->status, ['details'=>$error->details]);
        }
        parent::handleError($error);
    }

    protected function handleGet($id = null)
    {
        header('Cache-Control: private, no-store');
        $this->sendResponse(true, $this->service()->history($_GET));
    }

    protected function handlePost($data)
    {
        if (!$data instanceof stdClass) {
            throw new MobileT2Error('validation_failed', 422, ['reason'=>'count_invalid','field'=>'body']);
        }
        $actor = new EmployeeContext($this->conn, $this->principal, (int)$this->userId);
        $response = $this->service()->submit((array)$data, $actor);
        foreach ($response['headers'] as $name=>$value) {
            header($name . ': ' . $value);
        }
        $this->sendResponse(true, $response['body']['data'], '', $response['status']);
    }

    private function methodNotAllowed(): void
    {
        header('Allow: GET, POST');
        $this->sendError('validation_failed', 'Method not allowed', 405);
    }
    protected function handlePut($data) { $this->methodNotAllowed(); }
    protected function handlePatch($data) { $this->methodNotAllowed(); }
    protected function handleDelete($data) { $this->methodNotAllowed(); }
}

(new StockCountsEndpoint())->handleRequestMethod();
