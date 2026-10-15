<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/payments/options.php --- ver 5.0.0 --- 2026-10-07 ---
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
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created. Invoice payment links.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../services/payments/PaymentLinkService.php';

final class PaymentOptionsEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'debitorrapport','action'=>'read'];
    }
    protected function handleGet($id = null)
    {
        try {
            $payload = JWTAuth::validateToken();
            $context = EngineContext::forConfig($this->conn,$this->db,(int)$payload['tenant_id'],$this->username,(int)$this->userId);
            header('Cache-Control: private, no-store');
            $this->sendResponse(true,(new PaymentLinkService($context,$this->subject))->options(OpenPostService::id(ifset($_GET,'openpost_id'))));
        } catch (MobileT2Error $error) {
            $this->sendError($error->error,'Payment options refused',$error->status,['details'=>$error->details]);
        }
    }
    public function handleRequestMethod()
    {
        if (ifset($_SERVER,'REQUEST_METHOD') !== 'GET') {
            header('Allow: GET');
            $this->sendError('validation_failed','Method not allowed',405);
        }
        parent::handleRequestMethod();
    }
}
(new PaymentOptionsEndpoint())->handleRequestMethod();
