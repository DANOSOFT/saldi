<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/payments/links.php --- ver 5.0.0 --- 2026-10-07 ---
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
require_once __DIR__ . '/../../../core/MobileAuth.php';
require_once __DIR__ . '/../../../services/payments/PaymentLinkService.php';

final class PaymentLinksEndpoint extends BaseEndpoint
{
    private float $paymentStarted = 0;
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ifset($_SERVER,'REQUEST_METHOD') === 'GET'
            ? ['module'=>'debitorrapport','action'=>'read'] : ['module'=>'debitorordre','action'=>'write'];
    }
    private function service(): PaymentLinkService
    {
        $payload = JWTAuth::validateToken();
        $context = EngineContext::forConfig($this->conn,$this->db,(int)$payload['tenant_id'],$this->username,(int)$this->userId);
        return new PaymentLinkService($context,$this->subject);
    }
    protected function handleGet($id = null)
    {
        $this->respond(fn() => ['status'=>200,'body'=>['success'=>true,'message'=>'',
            'data'=>$this->service()->status(OpenPostService::id($id))],'headers'=>[]]);
    }
    protected function handlePost($data)
    {
        $this->respond(function () use ($data): array {
            // Replays also consume this abuse bucket; identities are scoped to tenant + canonical subject.
            PaymentLinkService::rateLimit(MobileAuth::connectRegistry(),'paylink_create',
                (int)JWTAuth::tenantId(JWTAuth::validateToken()).':'.$this->subject,30,0.5);
            return $this->service()->create((array)$data);
        });
    }
    protected function handleDelete($data)
    {
        $this->respond(fn() => $this->service()->cancel(OpenPostService::id(ifset($_GET,'id')),(array)$data));
    }
    private function respond(callable $operation): void
    {
        $this->paymentStarted = microtime(true);
        header('Cache-Control: private, no-store');
        try {
            $result = $operation();
            foreach ($result['headers'] as $key=>$value) {
                header($key.': '.$value);
            }
            $this->sendResponse($result['body']['success'],$result['body']['data'],$result['body']['message'],$result['status']);
        } catch (MobileT2Error $error) {
            if ($error->status === 429) {
                header('Retry-After: '.(int)$error->details['retry_after']);
            }
            $this->sendError($error->error,'Payment link refused',$error->status,['details'=>$error->details]);
        }
    }
    /** Preserve the one explicit safe provider failure; unknown server exceptions retain the core internal envelope. */
    protected function sendError(string $code, string $message, int $httpCode, array $details = []): void
    {
        if ($code !== 'upstream_unavailable' || $httpCode !== 503) {
            parent::sendError($code,$message,$httpCode,$details);
            return;
        }
        RequestId::emit();
        write_log(['method'=>ifset($_SERVER,'REQUEST_METHOD',''),'path'=>parse_url(ifset($_SERVER,'REQUEST_URI',''),PHP_URL_PATH) ?: '',
            'request_id'=>RequestId::current(),'subject'=>$this->subject,'status'=>503,'duration'=>round((microtime(true)-$this->paymentStarted)*1000,3)],$this->db);
        while (ob_get_level()) {
            ob_end_clean();
        }
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['success'=>false,'message'=>'Payment provider unavailable','data'=>['error'=>'upstream_unavailable']]);
        exit;
    }

    public function handleRequestMethod()
    {
        if (!in_array(ifset($_SERVER,'REQUEST_METHOD'),['GET','POST','DELETE'],true)) {
            header('Allow: GET, POST, DELETE');
            $this->sendError('validation_failed','Method not allowed',405);
        }
        parent::handleRequestMethod();
    }
}
(new PaymentLinksEndpoint())->handleRequestMethod();
