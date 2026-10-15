<?php
// 20261001 CDX/LH Durable upload identities, scoped DB reads, multipart validation and tombstone deletion.
// 20261001 CDX/LH Bind reminder resolution to upload success and map reminder failures.
// 20261001 CDX/LH Return the stored legacy XML MIME and extension on downloads.

// 20261001 CDX/LH Accept udlaeg claims on JSON and multipart uploads and publish them atomically.

require_once __DIR__ . '/../../../services/ClaimsService.php';
require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../../../includes/mobileUpdates.php';

class AttachmentEndpoint extends BaseEndpoint
{
    private float $downloadStarted;
    /** Receipt capture and own reads are available to every authenticated tenant user. @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'bilag', 'action' => 'own'];
    }

    protected function checkAuthorization()
    {
        $this->downloadStarted = microtime(true);
        if (!parent::checkAuthorization()) {
            return false;
        }
        mobileTenantUpdates($this->conn);
        AttachmentModel::setDatabase($this->db);
        $type = (string)ifset($_SERVER, 'CONTENT_TYPE', '');
        $multipart = str_starts_with(strtolower($type), 'multipart/form-data');
        $maximum = $multipart ? AttachmentUpload::MAX_BYTES + 65536 : 4 * (int)ceil(AttachmentUpload::MAX_BYTES / 3) + 65536;
        if ((int)ifset($_SERVER, 'CONTENT_LENGTH', 0) > $maximum) {
            $this->sendError('payload_too_large', 'Upload body exceeds the 15 MB file limit', 413);
        }
        return true;
    }

    /** BaseEndpoint dispatch still owns authentication, declarations, request ids and error handling. */
    protected function decodeJsonBody($rawInput)
    {
        if (str_starts_with(strtolower((string)ifset($_SERVER, 'CONTENT_TYPE', '')), 'multipart/form-data')) {
            $data = (object)$_POST;
            if (isset($data->extracted_data)) {
                if (!is_string($data->extracted_data)) {
                    $this->sendError('validation_failed', 'extracted_data must be a JSON object', 422);
                }
                $metadata = json_decode($data->extracted_data);
                if (!is_object($metadata)) {
                    $this->sendError('validation_failed', 'extracted_data must be a JSON object', 422);
                }
                $data->extracted_data = $metadata;
            }
            if (isset($data->claim) && is_string($data->claim)) {
                $data->claim = json_decode($data->claim);
            }
            return $data;
        }
        return parent::decodeJsonBody($rawInput);
    }

    /** Load current readable flags, keeping colleagues' visibility out of bilag/own's blanket gate. */
    private function store(): DurableAttachmentModel
    {
        $readAll = $this->principal === 'registry';
        if (!$readAll) {
            $id = (int)$this->userId;
            $row = db_fetch_array(db_select("SELECT rettigheder FROM brugere WHERE id=$id", __FILE__ . ' line ' . __LINE__));
            $rights = Rights::tenant((string)ifset($row, 'rettigheder', '0000000000000000'));
            $readAll = $rights['finans'] || $rights['bilag'];
        }
        return new DurableAttachmentModel($this->conn, $this->db, $this->subject, $readAll);
    }

    /** @return array<string,mixed> Only identity selectors; never use a request path. */
    private function selector(): array
    {
        return array_intersect_key($_GET, array_flip(['id', 'client_id', 'file']));
    }

    protected function handleGet($id = null)
    {
        $store = $this->store();
        $selector = $this->selector();
        if ($selector !== []) {
            $row = $store->find($selector);
            if ($row['status'] === 'staged') {
                $this->sendError('validation_failed', 'Upload not completed; retry POST', 404);
            }
            if (ifset($_GET, 'download') === 'true') {
                $this->download($store->path($row), $row);
            }
            if ($row['status'] !== 'deleted') {
                $store->path($row);
            }
            $this->sendResponse(true, $store->toArray($row));
        }
        if (isset($_GET['download'])) {
            $this->sendError('validation_failed', 'Download requires id, client_id or file', 422);
        }
        $limit = $this->integerParameter('limit', 50);
        $offset = $this->integerParameter('offset', 0);
        $mine = ifset($_GET, 'mine', '0');
        if (!in_array($mine, ['0', '1'], true)) {
            $this->sendError('validation_failed', 'mine must be 0 or 1', 422);
        }
        $status = ifset($_GET, 'status');
        if ($status !== null && !is_string($status)) {
            $this->sendError('validation_failed', 'Invalid status', 422);
        }
        $this->sendResponse(true, $store->listing($limit, $offset, $status, $mine === '1'));
    }

    /** @return int Validated pagination input. */
    private function integerParameter(string $name, int $default): int
    {
        $value = ifset($_GET, $name, (string)$default);
        if (!is_string($value) || !preg_match('/\A[0-9]{1,9}\z/', $value)) {
            throw new AttachmentException('validation_failed', 'Invalid ' . $name, 422);
        }
        return (int)$value;
    }

    protected function handlePost($data)
    {
        $claims = null;
        if (is_object($data) && property_exists($data,'claim')) {
            if (!is_object($data->claim) || get_object_vars($data->claim) !== ['kind'=>'udlaeg']) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'claim_invalid']);
            }
            $claims = new ClaimsService($this->conn,$this->db,$this->subject,$this->principal,(int)$this->userId);
            $claims->requireAvailable(true);
        }
        $upload = AttachmentUpload::decode($data, $_FILES);
        if ($claims !== null) { $upload['publication_claim'] = ['kind'=>'udlaeg','ansat_id'=>$claims->employee->ansatId]; }
        $store = $this->store();
        $payload = JWTAuth::validateToken();
        $tenantId = ifset($payload, 'tenant_id');
        if (array_key_exists('reminder_id', $upload['extracted_data']) && $tenantId === null) {
            $this->sendError('invalid_token', 'Reminder uploads require a tenant-bound token', 401);
        }
        $result = $store->upload($upload, $tenantId === null ? null : (int)$tenantId,
            $claims === null ? null : [$claims,'publishExpense']);
        $this->sendResponse(true, $store->toArray($result['row']), 'File uploaded successfully', $result['created'] ? 201 : 200);
    }

    protected function handlePut($data)
    {
        $this->sendError('validation_failed', 'Method Not Allowed', 405);
    }

    protected function handlePatch($data)
    {
        $this->sendError('validation_failed', 'Method Not Allowed', 405);
    }

    protected function handleDelete($data)
    {
        $selector = $this->selector();
        $store = $this->store();
        $row = $store->delete($selector);
        $this->sendResponse(true, $store->toArray($row), 'Attachment deleted');
    }

    /** Preserve the foundation error envelope for validation, ownership and lifecycle failures. */
    protected function handleError($exception = null)
    {
        if ($exception instanceof MobileT2Error) {
            $this->sendError($exception->error,'Claim upload refused',$exception->status,['details'=>$exception->details]);
        }
        if ($exception instanceof AttachmentException) {
            $this->sendError($exception->error, $exception->getMessage(), $exception->getStatusCode());
        }
        if ($exception instanceof MobileReminderError) {
            $this->sendError($exception->error, $exception->getMessage(), $exception->status);
        }
        parent::handleError($exception);
    }

    /** Stream a contained DB-selected file; filenames in the header cannot contain control bytes. */
    private function download(string $path, array $row): void
    {
        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open attachment');
        }
        RequestId::emit();
        write_log(['method' => 'GET', 'path' => parse_url(ifset($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH),
            'request_id' => RequestId::current(), 'subject' => $this->subject, 'status' => 200,
            'duration' => round((microtime(true) - $this->downloadStarted) * 1000, 3)], $this->db);
        $mime = DurableAttachmentModel::mimeType($row);
        $extension = $mime === 'application/xml' ? '.xml' : '.pdf';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="attachment-' . (int)$row['id'] . $extension . '"');
        header('Content-Length: ' . fstat($stream)['size']);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        fpassthru($stream);
        fclose($stream);
        exit;
    }
}

$endpoint = new AttachmentEndpoint();
$endpoint->handleRequestMethod();
