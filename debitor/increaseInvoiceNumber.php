<?php
// 20261110 CDX/ASR Retire the counter endpoint; EasyUBL updates the counter internally.
// 20261124 CL/ASR Comments state the current rule; no code change.

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => false, 'message' => 'This endpoint has been retired.']);
