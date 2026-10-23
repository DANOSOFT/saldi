<?php
// tests/shopify/support/http_stub.php
//
// Router for `php -S` used by ClientHttpTest: answers by path so the real curl
// transport can be exercised locally without Shopify.
//
// History:
// 20261021 CL/SZ created (SD-747).
// 20261023 CDX/LH Restrict the HTTP stub to PHP's built-in development server.

if (PHP_SAPI !== 'cli-server') {
	http_response_code(404);
	exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
switch ($path) {
	case '/echo':
		echo json_encode([
			'method' => $_SERVER['REQUEST_METHOD'],
			'token' => $_SERVER['HTTP_X_SHOPIFY_ACCESS_TOKEN'] ?? null,
			'body' => file_get_contents('php://input'),
		]);
		break;
	case '/throttled':
		http_response_code(429);
		header('Retry-After: 2');
		echo '{"errors":"Too many requests"}';
		break;
	case '/slow':
		sleep(2);
		echo '{}';
		break;
	case '/file':
		header('Content-Type: application/jsonl');
		echo "{\"id\":1}\n{\"id\":2}\n";
		break;
	default:
		http_response_code(404);
		echo '{"errors":"Not Found"}';
}
