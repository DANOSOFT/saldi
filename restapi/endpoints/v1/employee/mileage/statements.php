<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/employee/mileage/statements.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Preserve structured attachment validation and expired-reservation error codes.

require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../services/ClaimsService.php';

final class MileageStatementEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'bilag','action'=>'own'];
    }

    private function service(): ClaimsService
    {
        return new ClaimsService($this->conn,$this->db,$this->subject,$this->principal,(int)$this->userId);
    }

    protected function handleError($error = null)
    {
        if ($error instanceof MobileT2Error) {
            $this->sendError($error->error,'Claim operation refused',$error->status,['details'=>$error->details]);
        }
        if ($error instanceof AttachmentException) {
            $this->sendError($error->error, $error->getMessage(), $error->getStatusCode());
        }
        parent::handleError($error);
    }

    /** @return array Validated object write body. */
    private function body($data): array
    {
        if (!is_object($data)) { throw new MobileT2Error('validation_failed',422); }
        return (array)$data;
    }

    /** Send the stored envelope and idempotency headers after commit. */
    private function result(array $result): void
    {
        header('Cache-Control: private, no-store');
        foreach ($result['headers'] as $key=>$value) { header($key.': '.$value); }
        if (isset($result['body']['data']['etag'])) { header('ETag: '.$result['body']['data']['etag']); }
        $this->sendResponse($result['body']['success'],$result['body']['data'],$result['body']['message'],$result['status']);
    }

    protected function handleGet($id = null)
    {
        header('Cache-Control: private, no-store');
        $trips = $this->service()->statements($_GET);
        if (isset($trips['etag'])) { header('ETag: '.$trips['etag']); }
        $this->sendResponse(true,$trips);
    }

    protected function handlePost($data)
    {
        $this->result($this->service()->issueStatement($this->body($data)));
    }

    protected function handlePatch($data)
    {
        $this->sendError('validation_failed','Method not allowed',405);
    }

    protected function handleDelete($data)
    {
        $this->sendError('validation_failed','Method not allowed',405);
    }

    protected function handlePut($data)
    {
        $this->sendError('validation_failed','Method not allowed',405);
    }
}
(new MileageStatementEndpoint())->handleRequestMethod();
