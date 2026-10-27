<?php
// tests/shopify/support/webhook_router.php
//
// Router for `php -S` in WebhookEndpointTest (SD-748): serves only api/shopify/webhook.php,
// from its own directory as Apache does, because includes/connect.php finds db_query.php
// relative to the working directory.
//
// History:
// 20261027 CL/SZ SD-748: created.

if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/api/shopify/webhook.php') {
    http_response_code(404);
    return true;
}
chdir(__DIR__ . '/../../../api/shopify');
require 'webhook.php';
