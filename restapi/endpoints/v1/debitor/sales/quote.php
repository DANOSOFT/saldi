<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/debitor/sales/quote.php --- ver 5.0.0 --- 2026.10.07
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
require_once __DIR__ . '/../../../../services/SalesService.php';

final class SalesQuoteEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array { return ['module'=>'debitorordre','action'=>'write']; }
    protected function handlePost($data)
    {
        try {
            header('Cache-Control: private, no-store');
            $this->sendResponse(true,$this->service()->quote((array)$data));
        } catch (MobileT2Error $e) { $this->sendError($e->error,'Sales quote refused',$e->status,['details'=>$e->details]); }
    }
    /** @return SalesService Authenticated, server-selected tenant context. */
    private function service(): SalesService
    {
        $payload=JWTAuth::validateToken();
        return new SalesService(EngineContext::forConfig($this->conn,$this->db,(int)$payload['tenant_id'],$this->username,(int)$this->userId),$this->subject);
    }
}
(new SalesQuoteEndpoint())->handleRequestMethod();
