<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/payments/register.php --- ver 5.0.0 --- 2026-10-07 ---
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
// 20261001 CDX/LH Created. Payment register endpoint.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../services/PaymentRegistrationService.php';

final class PaymentRegisterEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'kassekladde','action'=>'write'];
    }

    protected function handlePost($data)
    {
        try {
            $body = (array)$data;
            Idempotency::clientId($body);
            $date = ifset($body,'date');
            if (!is_string($date)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_date']);
            }
            $payload = JWTAuth::validateToken();
            $context = new EngineContext($this->conn,$this->db,(int)$payload['tenant_id'],$this->username,(int)$this->userId,$date);
            $result = (new PaymentRegistrationService($context,$this->subject))->register($body);
            foreach ($result['headers'] as $key=>$value) {
                header($key.': '.$value);
            }
            header('Cache-Control: private, no-store');
            $this->sendResponse($result['body']['success'],$result['body']['data'],$result['body']['message'],$result['status']);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error,'Payment registration refused',$error->status,['details'=>$error->details]);
        }
    }

    protected function handleError($exception=null)
    {
        if ($exception instanceof BadMethodCallException || ($exception instanceof Exception && $exception->getMessage()==='Method Not Allowed')) {
            header('Allow: POST');
            $this->sendError('validation_failed','Method not allowed',405);
        }
        parent::handleError($exception);
    }
}
(new PaymentRegisterEndpoint())->handleRequestMethod();
