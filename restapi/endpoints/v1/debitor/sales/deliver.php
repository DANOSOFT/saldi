<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/debitor/sales/deliver.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Reserve and attach invoice payment links in the durable mail intent.

require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../services/DocumentService.php';
require_once __DIR__ . '/../../../../services/payments/PaymentLinkService.php';
require_once __DIR__ . '/../../../../../includes/payments/paymentLinkToken.php';

final class DocumentDeliveryEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'debitorordre', 'action' => 'write'];
    }

    public function handleRequestMethod()
    {
        header('Cache-Control: private, no-store');
        if (ifset($_SERVER, 'REQUEST_METHOD') !== 'POST') {
            header('Allow: POST');
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    protected function handlePost($data)
    {
        try {
            $orderId = DocumentService::positiveId(ifset($data, 'order_id'));
            $store = new MobileSchema($this->conn);
            $rows = $store->rows("SELECT COALESCE(fakturadate,ordredate,CURRENT_DATE)::text AS date FROM ordrer WHERE id=$orderId");
            $payload = JWTAuth::validateToken();
            $context = new EngineContext($this->conn, $this->db, (int)$payload['tenant_id'], $this->username,
                (int)$this->userId, (string)ifset($rows, [0, 'date'], (new DateTimeImmutable('now', new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d')));
            $body = (array)$data;
            $attach = ifset($body,'attach_payment_link',false);
            if (!is_bool($attach)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_attach_payment_link']);
            }
            $documents = new DocumentService($context,$this->subject);
            if (!$attach) {
                $result = $documents->deliver($body);
            } else {
                $store->execute('BEGIN');
                try {
                    $cached = (new Idempotency($this->conn))->replay($this->subject,'document.deliver',$orderId,$body);
                    if ($cached !== null) {
                        $store->execute('COMMIT');
                        $result = $cached;
                    } else {
                        if (ifset($body,'email') !== null && !is_string($body['email'])) {
                            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_email']);
                        }
                        if (ifset($body,'channel') !== 'email') {
                            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_channel']);
                        }
                        $posts = $store->rows("SELECT id FROM openpost WHERE refnr=$orderId AND amount>0 ORDER BY id LIMIT 1");
                        if (!$posts) {
                            throw new MobileT2Error('validation_failed',422,['reason'=>'not_payable']);
                        }
                        $paymentLink = (new PaymentLinkService($context,$this->subject))->reserve((int)$posts[0]['id'],Idempotency::clientId($body));
                        PaymentLinkMailToken::set($orderId,$paymentLink['url']);
                        $result = $documents->deliverCreated($orderId,'email',ifset($body,'email'),$body,
                            'document.deliver',$orderId,['payment_link'=>$paymentLink],200);
                    }
                } catch (Throwable $error) {
                    if (pg_transaction_status($this->conn) !== PGSQL_TRANSACTION_IDLE) {
                        $store->execute('ROLLBACK');
                    }
                    throw $error;
                } finally {
                    PaymentLinkMailToken::set($orderId,null);
                }
            }
            foreach ($result['headers'] as $key => $value) {
                header($key . ': ' . $value);
            }
            $this->sendResponse($result['body']['success'], $result['body']['data'], $result['body']['message'], $result['status']);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error, 'Document delivery refused', $error->status, ['details' => $error->details]);
        }
    }
}

(new DocumentDeliveryEndpoint())->handleRequestMethod();
