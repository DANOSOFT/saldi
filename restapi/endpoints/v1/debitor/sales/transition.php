<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/debitor/sales/transition.php --- ver 5.0.0 --- 2026.10.03
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

require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../services/SalesService.php';

final class SalesTransitionEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array { return ['module'=>'debitorordre','action'=>'write']; }

    protected function handlePost($data)
    {
        try {
            $body=(array)$data;
            $id=SalesService::id(ifset($body,'order_id'),true);
            // Invoice-date validation happens after replay inside the service.
            $today=(new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d');
            $rows=(new MobileSchema($this->conn))->rows("SELECT COALESCE(ordredate,CURRENT_DATE)::text AS date FROM ordrer WHERE id=$id");
            $contextDate=(string)ifset($rows,[0,'date'],$today);
            $payload=JWTAuth::validateToken();
            $service=new SalesService(new EngineContext($this->conn,$this->db,(int)$payload['tenant_id'],
                $this->username,(int)$this->userId,$contextDate),$this->subject);
            $result=$service->transition($id,$body,ifset($_SERVER,'HTTP_IF_MATCH'));
            foreach ($result['headers'] as $key=>$value) { header($key.': '.$value); }
            $this->sendResponse($result['body']['success'],$result['body']['data'],$result['body']['message'],$result['status']);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error,'Sales transition refused',$error->status,['details'=>$error->details]);
        }
    }

    protected function handleGet($id=null) { header('Allow: POST'); $this->sendError('validation_failed','Method not allowed',405); }
    protected function handlePut($data) { header('Allow: POST'); $this->sendError('validation_failed','Method not allowed',405); }
    protected function handlePatch($data) { header('Allow: POST'); $this->sendError('validation_failed','Method not allowed',405); }
    protected function handleDelete($id) { header('Allow: POST'); $this->sendError('validation_failed','Method not allowed',405); }
}
(new SalesTransitionEndpoint())->handleRequestMethod();
