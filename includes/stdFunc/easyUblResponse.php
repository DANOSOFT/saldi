<?php
// includes/stdFunc/easyUblResponse.php
//
// History:
// 20260914 Sawaneh    Created (JOB-141). debitor/api.php getInvoicesOrder() classified EasyUBL's
//                     reply inline while sending, looked for error fields the API does not return
//                     (errorMessage/error - EasyUBL returns errNo/message) and could not be tested
//                     without the network. The classification is a pure function here.
// 20260915 Sawaneh    PR #619 review: an explicit errNo/message is an error even when a document
//                     is returned alongside it.

/**
 * Classify a reply from EasyUBL's SendDocuments endpoints.
 *
 * EasyUBL's documented return object is {documentType, conveyer, errNo, message,
 * base64EncodedDocumentXml}. A document was only produced when the call returned 2xx and
 * base64EncodedDocumentXml decodes to a non-empty XML string; everything else is a failure.
 *
 * @param int          $httpCode  HTTP status from cURL (0 when the request never completed).
 * @param string|false $rawBody   Raw body from curl_exec() (false when the transfer failed).
 * @param int          $curlErrno curl_errno() of the handle.
 * @param string       $curlError curl_error() of the handle.
 * @return array{
 *   kind: 'ok'|'transport'|'empty'|'invalid_json'|'api_error'|'http_error'|'no_document',
 *   http_code: int,
 *   err_no: int|null,
 *   message: string,
 *   detail: string,
 *   base64: string,
 *   xml: string
 * } kind says what happened; message is EasyUBL's or cURL's own text when there is one; detail is
 *   a short excerpt of a body that could not be used; base64/xml are filled only for kind 'ok'.
 */
function easyubl_interpret_response($httpCode, $rawBody, $curlErrno = 0, $curlError = '') {
	$out = array(
		'kind' => '', 'http_code' => (int) $httpCode, 'err_no' => null,
		'message' => '', 'detail' => '', 'base64' => '', 'xml' => ''
	);
	if ($curlErrno) {
		$out['kind'] = 'transport';
		$out['message'] = trim((string) $curlError);
		return $out;
	}
	$body = is_string($rawBody) ? trim($rawBody) : '';
	if ($body === '') {
		$out['kind'] = 'empty';
		return $out;
	}
	$decoded = json_decode($body, true);
	if (!is_array($decoded)) {
		$out['kind'] = 'invalid_json';
		$out['detail'] = easyubl_body_excerpt($body);
		return $out;
	}
	if (isset($decoded['errNo']) && is_numeric($decoded['errNo'])) {
		$out['err_no'] = (int) $decoded['errNo'];
	}
	// errNo/message is the documented shape; errorMessage/error are kept for older replies
	foreach (array('message', 'errorMessage', 'error') as $key) {
		if (!empty($decoded[$key])) {
			$out['message'] = is_array($decoded[$key])
				? json_encode($decoded[$key], JSON_UNESCAPED_UNICODE)
				: trim((string) $decoded[$key]);
			break;
		}
	}
	$base64 = '';
	if (isset($decoded['base64EncodedDocumentXml']) && is_string($decoded['base64EncodedDocumentXml'])) {
		$base64 = trim($decoded['base64EncodedDocumentXml']);
	}
	$xml = ($base64 !== '') ? base64_decode($base64, true) : false;
	$httpOk = ($out['http_code'] >= 200 && $out['http_code'] < 300);
	// An explicit error wins even when a document came along with it
	$apiError = ($out['err_no'] !== null && $out['err_no'] != 0) || ($out['err_no'] === null && $out['message'] !== '');

	if ($httpOk && !$apiError && $xml !== false && trim($xml) !== '') {
		$out['kind'] = 'ok';
		$out['base64'] = $base64;
		$out['xml'] = $xml;
		return $out;
	}
	if ($apiError) {
		$out['kind'] = 'api_error';
	} elseif (!$httpOk) {
		$out['kind'] = 'http_error';
	} else {
		$out['kind'] = 'no_document';
	}
	$out['detail'] = easyubl_body_excerpt($body);
	return $out;
}

/**
 * First 200 characters of a body with tags and runs of whitespace removed - enough to tell an
 * IIS/ASP.NET error page from a JSON fragment in a log line or an alert.
 *
 * @param string $body
 * @return string
 */
function easyubl_body_excerpt($body) {
	$text = trim(preg_replace('/\s+/', ' ', strip_tags((string) $body)));
	return mb_substr($text, 0, 200, 'UTF-8');
}
