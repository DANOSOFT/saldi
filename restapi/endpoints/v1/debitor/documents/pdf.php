<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/debitor/documents/pdf.php --- ver 5.0.0 --- 2026.10.02
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
require_once __DIR__ . '/../../../../services/DocumentService.php';

final class DocumentPdfEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'debitorordre', 'action' => 'read'];
    }

    public function handleRequestMethod()
    {
        header('Cache-Control: private, no-store');
        if (ifset($_SERVER, 'REQUEST_METHOD') !== 'GET') {
            header('Allow: GET');
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleRequestMethod();
    }

    protected function handleGet($id = null)
    {
        $started = microtime(true);
        try {
            $orderId = DocumentService::positiveId(ifset($_GET, 'order_id'));
            $store = new MobileSchema($this->conn);
            $rows = $store->rows("SELECT COALESCE(fakturadate,ordredate,CURRENT_DATE)::text AS date FROM ordrer WHERE id=$orderId");
            if (!$rows) {
                throw new MobileT2Error('not_found', 404);
            }
            $payload = JWTAuth::validateToken();
            $context = new EngineContext($this->conn, $this->db, (int)$payload['tenant_id'], $this->username,
                (int)$this->userId, $rows[0]['date']);
            $rendered = (new DocumentService($context, $this->subject))->renderPdf($orderId);
            write_log(['method' => 'GET', 'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
                'request_id' => $this->requestId, 'subject' => $this->subject, 'status' => 200,
                'duration' => round((microtime(true) - $started) * 1000, 3)], $this->db);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $rendered['filename'] . '"');
            header('X-Content-Type-Options: nosniff');
            header('Content-Length: ' . filesize($rendered['pdf']));
            readfile($rendered['pdf']);
            exit;
        } catch (MobileT2Error $error) {
            $this->sendError($error->error, 'Document PDF unavailable', $error->status, ['details' => $error->details]);
        }
    }
}

(new DocumentPdfEndpoint())->handleRequestMethod();
