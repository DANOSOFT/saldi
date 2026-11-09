<?php
// tests/shopify/support/webhook_router.php
//
// Router for `php -S` in WebhookEndpointTest (SD-748) and OauthEndpointTest (SD-790): serves only
// api/shopify/webhook.php and api/shopify/oauth.php, from their own directory as Apache does,
// because includes/connect.php finds db_query.php relative to the working directory.
//
// History:
// 20261027 CL/SZ SD-748: created.
// 20261028 CL/SZ SD-748 review: SALDI_SHOPIFY_TEST_DIE=1 loads webhook_die_prepend.php first; php -S doesn't run
//                 auto_prepend_file for a router on every PHP version.
// 20261103 CL/MJ SD-790: also serves api/shopify/oauth.php, for OauthEndpointTest.

$endpoint = basename((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (!in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/api/shopify/webhook.php', '/api/shopify/oauth.php'], true)) {
    http_response_code(404);
    return true;
}
if (getenv('SALDI_SHOPIFY_TEST_DIE') === '1') {
    require __DIR__ . '/webhook_die_prepend.php';
}
chdir(__DIR__ . '/../../../api/shopify');
require $endpoint;
