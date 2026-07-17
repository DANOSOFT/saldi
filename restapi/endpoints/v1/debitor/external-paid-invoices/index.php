<?php
// 20260716 CL/PHR Added Stripe paid-invoice import endpoint.

require_once __DIR__ . '/../../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../services/ExternalPaidInvoiceImportService.php';
include_once __DIR__ . '/../../../../../includes/ordrefunc.php';

class ExternalPaidInvoicesEndpoint extends BaseEndpoint
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ExternalPaidInvoiceImportService();
    }

    protected function handlePost($data)
    {
        if ($data === null) {
            $this->sendResponse(false, null, 'Request body must be valid JSON', 400);
            return;
        }

        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $result = $this->service->import($data, $this->username);
        } finally {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }
        }

        $statusCode = $result['idempotent'] ? 200 : 201;
        $message = $result['idempotent']
            ? 'External paid invoice already imported'
            : 'External paid invoice imported successfully';

        $this->sendResponse(true, $result, $message, $statusCode);
    }

    protected function handleGet($id = null)
    {
        $this->sendResponse(false, null, 'GET method is not supported for external paid invoices', 405);
    }

    protected function handlePut($data)
    {
        $this->sendResponse(false, null, 'PUT method is not supported for external paid invoices', 405);
    }

    protected function handleDelete($data)
    {
        $this->sendResponse(false, null, 'DELETE method is not supported for external paid invoices', 405);
    }

    protected function handlePatch($data)
    {
        $this->sendResponse(false, null, 'PATCH method is not supported for external paid invoices', 405);
    }
}

$endpoint = new ExternalPaidInvoicesEndpoint();
$endpoint->handleRequestMethod();
