<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/cvr/lookup.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Pass authenticated tenant identity to the registry rate limiter.

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../services/CustomerDirectoryService.php';

final class CvrLookupEndpoint extends BaseEndpoint
{
    private float $directoryStarted = 0;
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array { return ['module'=>'debitorkonti','action'=>'read']; }
    protected function handleGet($id = null) {
        $this->directoryStarted = microtime(true);
        header('Cache-Control: private, no-store');
        try {
            $data = (new CvrDirectoryService(MobileAuth::connectRegistry()))->lookup(ifset($_GET,'cvr'),$this->subject,(int)JWTAuth::tenantId(JWTAuth::validateToken()));
            if (isset($data['etag'])) { header('ETag: ' . $data['etag']); }
            $this->sendResponse(true,$data);
        } catch (MobileT2Error $error) {
            if ($error->status === 429) { header('Retry-After: ' . (int)$error->details['retry_after']); }
            $this->sendError($error->error,'Directory request failed',$error->status,['details'=>$error->details]);
        }
    }
    /** Preserve only this explicit public 503; unexpected exceptions use BaseEndpoint's generic 500. */
    protected function sendError(string $code, string $message, int $httpCode, array $details = []): void {
        if ($code !== 'upstream_unavailable' || $httpCode !== 503) {
            parent::sendError($code,$message,$httpCode,$details);
            return;
        }
        RequestId::emit();
        write_log(['method'=>'GET','path'=>parse_url(ifset($_SERVER,'REQUEST_URI',''),PHP_URL_PATH) ?: '',
            'request_id'=>RequestId::current(),'subject'=>$this->subject,'status'=>503,
            'duration'=>round((microtime(true)-$this->directoryStarted)*1000,3)],$this->db);
        while (ob_get_level()) { ob_end_clean(); }
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>'CVR directory unavailable','data'=>['error'=>'upstream_unavailable']]);
        exit;
    }
    private function methodNotAllowed(): void {
        header('Allow: GET');
        $this->sendError('validation_failed','Method not allowed',405);
    }
    protected function handlePost($data) { $this->methodNotAllowed(); }
    protected function handlePut($data) { $this->methodNotAllowed(); }
    protected function handlePatch($data) { $this->methodNotAllowed(); }
    protected function handleDelete($data) { $this->methodNotAllowed(); }
}

(new CvrLookupEndpoint())->handleRequestMethod();
