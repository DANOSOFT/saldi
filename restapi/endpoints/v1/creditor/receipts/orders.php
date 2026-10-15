<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/creditor/receipts/orders.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Resolve legacy tenant headers through JWTAuth and defer the fiscal-year context.


require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../services/GoodsReceiptService.php';

final class GoodsReceiptOrdersEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'kreditorordre','action'=>'read'];
    }

    /** Preserve the coded error envelope on new endpoints before the legacy authorization response exits. */
    protected function checkAuthorization()
    {
        if (!JWTAuth::validateToken()) {
            $this->sendError('invalid_token', 'Valid Bearer token required', 401);
        }
        return parent::checkAuthorization();
    }

    /** @return GoodsReceiptService Authenticated, server-selected identity; fiscal year is resolved on use. */
    private function service(): GoodsReceiptService
    {
        $payload = JWTAuth::validateToken();
        return new GoodsReceiptService(new GoodsReceiptIdentity($this->conn, $this->db, (int)JWTAuth::tenantId($payload),
            $this->username, (int)$this->userId), $this->subject);
    }

    protected function handleError($error = null)
    {
        if ($error instanceof MobileT2Error) {
            $this->sendError($error->error, 'Receipt request refused', $error->status, ['details'=>$error->details]);
        }
        parent::handleError($error);
    }

    protected function handleGet($id = null)
    {
        header('Cache-Control: private, no-store');
        $id = $id === null ? null : GoodsReceiptService::id($id);
        $data = $id === null ? $this->service()->openOrders($_GET) : $this->service()->order($id);
        if ($id !== null) {
            header('ETag: ' . $data['etag']);
        }
        $this->sendResponse(true, $data);
    }

    protected function handlePost($data)
    {
        header('Cache-Control: private, no-store');
        $this->methodNotAllowed();
    }

    private function methodNotAllowed(): void
    {
        header('Allow: GET');
        $this->sendError('validation_failed', 'Method not allowed', 405);
    }

    protected function handlePut($data) { $this->methodNotAllowed(); }
    protected function handlePatch($data) { $this->methodNotAllowed(); }
    protected function handleDelete($data) { $this->methodNotAllowed(); }
}

(new GoodsReceiptOrdersEndpoint())->handleRequestMethod();
