<?php
// 20260907 CDX/LH Read-only assistant record endpoint. Opt-in, scoped to the active SALDI login.
// 20260908 CDX/LH Accept the native Apache authorization header.
require_once __DIR__ . '/assist/RecordService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try {
    if (getenv('SALDI_ASSIST_RECORDS_ENABLED') !== '1') {
        throw new SaldiAssistFailure('records_disabled', 503);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        throw new SaldiAssistFailure('method_not_allowed', 405);
    }
    $input = file_get_contents('php://input', false, null, 0, 8193);
    $body = strlen($input) <= 8192 ? json_decode($input, true) : null;
    if (!is_array($body)) {
        throw new SaldiAssistFailure('invalid_request', 400);
    }
    $kid = getenv('SALDI_ASSIST_KID') ?: 'k2026a';
    $secret = getenv('SALDI_ASSIST_CONTEXT_SECRET') ?: '';
    if (strlen($secret) < 32) {
        throw new SaldiAssistFailure('not_configured', 503);
    }
    $masterName = getenv('SALDI_ASSIST_DB_MASTER') ?: '';
    $master = saldi_assist_read_connection($masterName);
    $operation = $body['operation'] ?? '';
    if ($operation === 'grant') {
        // JSON POST and exact Origin prevent cross-site minting. No CORS is exposed.
        $origin = getenv('SALDI_ASSIST_HOST_ORIGIN') ?: ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off' ? 'http://' : 'https://') . $_SERVER['HTTP_HOST']);
        if (($_SERVER['HTTP_ORIGIN'] ?? '') !== $origin) {
            throw new SaldiAssistFailure('origin_forbidden');
        }
        session_start(['read_and_close' => true, 'use_strict_mode' => true]);
        $online = saldi_assist_one($master, 'SELECT * FROM online WHERE session_id = ? ORDER BY logtime DESC LIMIT 1', [session_id()]);
        $kind = $body['kind'] ?? '';
        $id = $body['record_id'] ?? null;
        $embed = $body['embed_session'] ?? '';
        if (!in_array($kind, ['journal', 'invoice'], true) || !is_int($id) || $id < 1 || $id > 2147483647
            || !is_string($embed) || !preg_match('/^[0-9a-f]{32}$/D', $embed)) {
            throw new SaldiAssistFailure('invalid_record', 400);
        }
    } else {
        $authorization = saldi_assist_authorization($_SERVER, function_exists('getallheaders') ? getallheaders() : []);
        $token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
        $claims = SaldiAssistRecordAuth::verify($token, $kid, $secret, time());
        $online = saldi_assist_one($master, 'SELECT * FROM online WHERE md5(session_id) = ? ORDER BY logtime DESC LIMIT 1', [$claims['session_locator']]);
        if ($online) {
            SaldiAssistRecordAuth::assertSession($claims, $online, $secret);
        }
        $kind = $claims['kind'];
        $id = $claims['record_id'];
    }
    if (!$online || empty($online['db']) || empty($online['brugernavn']) || $online['db'] === $masterName) {
        throw new SaldiAssistFailure('session_expired', 401);
    }
    $tenant = saldi_assist_read_connection($online['db']);
    saldi_assist_authorize($master, $tenant, $online, $kind);
    $service = new SaldiAssistRecordService($tenant, (int)$online['regnskabsaar']);
    $rowIds = $body['row_ids'] ?? [];
    if (!is_array($rowIds) || count($rowIds) > 20 || array_filter($rowIds, static fn($id): bool => !is_int($id) || $id < 1)) {
        throw new SaldiAssistFailure('invalid_rows', 400);
    }
    $result = $service->execute($operation === 'grant' ? ($kind === 'journal' ? 'get_journal_context' : 'get_invoice_status') : $operation, $kind, $id, $rowIds);
    if (isset($body['revision']) && (!is_string($body['revision']) || !hash_equals($result['revision'], $body['revision']))) {
        throw new SaldiAssistFailure('record_changed', 409);
    }
    if ($operation === 'grant') {
        $claims = SaldiAssistRecordAuth::claims($online, $kind, $id, $embed, $secret, time());
        $result = ['kind' => $kind, 'recordId' => $id, 'revision' => $result['revision'],
            'grant' => SaldiAssistRecordAuth::sign($claims, $kid, $secret), 'expiresAt' => $claims['exp'],
            'hasSavedDraft' => $result['has_saved_draft'] ?? false];
    }
    // No accounting mutation is possible inside either READ ONLY transaction.
    $tenant->rollBack();
    $master->rollBack();
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (SaldiAssistFailure $error) {
    http_response_code($error->status);
    echo json_encode(['error' => $error->getMessage()]);
} catch (Throwable $error) {
    // PDO messages can contain tenant names and SQL. Keep them out of responses and assistant logs.
    http_response_code(503);
    echo json_encode(['error' => 'records_unavailable']);
}
