<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/debitor/customers/create.php --- ver 5.0.0 --- 2026.10.07
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
require_once __DIR__ . '/../../../../services/CustomerDirectoryService.php';

final class CustomerCreateEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array { return ['module'=>'debitorkonti','action'=>'write']; }
    protected function handlePost($data) {
        header('Cache-Control: private, no-store');
        try {
            if (!$data instanceof stdClass) { throw new MobileT2Error('validation_failed',422); }
            $data = (array)$data;
            $context = EngineContext::forConfig($this->conn,$this->db,(int)JWTAuth::tenantId(JWTAuth::validateToken()),$this->username,(int)$this->userId);
            $response = (new CustomerDirectoryService($this->conn))->write($context,$this->subject,$data,null);
            foreach ($response['headers'] as $name=>$value) { header($name . ': ' . $value); }
            if (isset($response['body']['data']['etag'])) { header('ETag: ' . $response['body']['data']['etag']); }
            $this->sendResponse(true,$response['body']['data'],'',$response['status']);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error,'Customer request failed',$error->status,['details'=>$error->details]);
        }
    }
    private function methodNotAllowed(): void {
        header('Allow: POST');
        $this->sendError('validation_failed','Method not allowed',405);
    }
    protected function handleGet($id = null) { $this->methodNotAllowed(); }
    protected function handlePut($data) { $this->methodNotAllowed(); }
    protected function handlePatch($data) { $this->methodNotAllowed(); }
    protected function handleDelete($data) { $this->methodNotAllowed(); }
}

(new CustomerCreateEndpoint())->handleRequestMethod();
