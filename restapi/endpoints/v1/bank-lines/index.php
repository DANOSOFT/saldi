<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/bank-lines/index.php --- ver 5.0.0 --- 2026.10.05
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
require_once __DIR__ . '/../../../services/BankLineService.php';

final class BankLinesIndexEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'kassekladde','action'=>'read'];
    }

    private function service(): BankLineService
    {
        return new BankLineService($this->conn,MobileAuth::connectRegistry(),
            (int)JWTAuth::tenantId(JWTAuth::validateToken()),$this->subject);
    }

    protected function handleError($error = null)
    {
        if ($error instanceof MobileT2Error) {
            $this->sendError($error->error,'Bank line request failed',$error->status,['details'=>$error->details]);
        }
        if ($error instanceof MobileReminderError) {
            $this->sendError($error->error,$error->getMessage(),$error->status);
        }
        parent::handleError($error);
    }

    private function methodNotAllowed(): void
    {
        header('Allow: GET');
        $this->sendError('validation_failed','Method not allowed',405);
    }

    protected function handleGet($id = null)
    {
        header('Cache-Control: private, no-store');
        $this->sendResponse(true,$this->service()->listing($_GET),'');
    }
    protected function handlePost($data) { $this->methodNotAllowed(); }
    protected function handlePut($data) { $this->methodNotAllowed(); }
    protected function handlePatch($data) { $this->methodNotAllowed(); }
    protected function handleDelete($data) { $this->methodNotAllowed(); }
}

(new BankLinesIndexEndpoint())->handleRequestMethod();
