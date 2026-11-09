<?php
// tests/shopify/support/webhook_die_prepend.php
//
// Loaded by webhook_router.php when SALDI_SHOPIFY_TEST_DIE=1 (WebhookEndpointTest, SD-748): defines
// db_connect() before includes/db_query.php does (it is guarded by function_exists), and ends the
// request the way the legacy connect error does: HTML printed, then die.
//
// History:
// 20261028 CL/SZ SD-748 review: created.
// 20261028 CL/SZ SD-748 review: loaded by the router, not as auto_prepend_file.

function db_connect($l_host, $l_bruger, $l_password, $l_database = "", $l_spor = "")
{
    print "<h1>Fejl: legacy error page</h1><script>alert('legacy')</script>";
    die('Unable to connect to database');
}
