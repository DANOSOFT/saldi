<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/debitor/dunning/index.php --- ver 5.0.0 --- 2026.10.07
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
require_once __DIR__ . '/../../../../services/DunningService.php';

final class DunningEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'debitorordre','action'=>$_SERVER['REQUEST_METHOD'] === 'GET' ? 'read' : 'write'];
    }

    public function handleRequestMethod()
    {
        header('Cache-Control: private, no-store');
        if (!in_array(ifset($_SERVER,'REQUEST_METHOD'),['GET','POST','PUT','PATCH','DELETE'],true)) {
            header('Allow: GET, POST');
            $this->sendError('validation_failed','Method not allowed',405);
        }
        parent::handleRequestMethod();
    }

    protected function handlePut($data)
    {
        $this->methodNotAllowed();
    }

    protected function handlePatch($data)
    {
        $this->methodNotAllowed();
    }

    protected function handleDelete($data)
    {
        $this->methodNotAllowed();
    }

    private function methodNotAllowed(): void
    {
        header('Allow: GET, POST');
        $this->sendError('validation_failed','Method not allowed',405);
    }

    protected function checkAuthorization()
    {
        if (!JWTAuth::validateToken()) {
            $this->sendError('invalid_token','Valid Bearer token required',401);
        }
        return parent::checkAuthorization();
    }

    private function service(): DunningService
    {
        $payload = JWTAuth::validateToken();
        if ((int)ifset($payload,'tenant_id',0) <= 0) {
            $this->sendError('invalid_token','A tenant-bound token is required',401);
        }
        $context = EngineContext::forConfig($this->conn,$this->db,(int)$payload['tenant_id'],$this->username,(int)$this->userId);
        return new DunningService($context,$this->subject);
    }

    protected function handleGet($id = null)
    {
        try {
            $kontoId = DunningService::id(ifset($_GET, 'konto_id'));
            $this->sendResponse(true, $this->service()->history($kontoId,$_GET));
        } catch (MobileT2Error $error) {
            $this->sendError($error->error, 'Dunning history refused', $error->status, ['details'=>$error->details]);
        }
    }

    protected function handlePost($data)
    {
        try {
            $result = $this->service()->create((array)$data);
            foreach ($result['headers'] as $name=>$value) {
                header($name . ': ' . $value);
            }
            $this->sendResponse($result['body']['success'],$result['body']['data'],$result['body']['message'],$result['status']);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error, 'Dunning creation refused', $error->status, ['details'=>$error->details]);
        }
    }
}

(new DunningEndpoint())->handleRequestMethod();
