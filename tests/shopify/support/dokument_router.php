<?php
// tests/shopify/support/dokument_router.php
//
// Router for `php -S` in DokumentLinkTest (SD-752): serves only api/dokument.php, from its own
// directory as Apache does. pdftk is DOKUMENT_TEST_PDFTK (the global render_document() reads),
// and send_mails() is replaced by a recorder that appends to DOKUMENT_TEST_MAIL_LOG, so a test
// sees any mail a request would have sent.
//
// History:
// 20261030 CL/SZ SD-752: created.

if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) !== '/api/dokument.php') {
    http_response_code(404);
    return true;
}

function send_mails($ordre_id, $filnavn, $email, $mailsprog, $form_nr, $subjekt, $mailtext, $mailbilag, $mailnr)
{
    file_put_contents((string)getenv('DOKUMENT_TEST_MAIL_LOG'), "$ordre_id $email\n", FILE_APPEND | LOCK_EX);
    return 'OK';
}

$pdftk = (string)getenv('DOKUMENT_TEST_PDFTK');
chdir(__DIR__ . '/../../../api');
require 'dokument.php';
