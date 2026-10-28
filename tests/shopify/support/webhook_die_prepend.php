<?php
// tests/shopify/support/webhook_die_prepend.php
//
// auto_prepend_file for WebhookEndpointTest (SD-748): defines db_connect() before
// includes/db_query.php does (it is guarded by function_exists), and ends the request the way
// the legacy connect error does: HTML printed, then die.
//
// History:
// 20261028 CL/SZ SD-748 review: created.

function db_connect($l_host, $l_bruger, $l_password, $l_database = "", $l_spor = "")
{
    print "<h1>Fejl: legacy error page</h1><script>alert('legacy')</script>";
    die('Unable to connect to database');
}
