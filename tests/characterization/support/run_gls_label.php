<?php
// 20261113 CDX/NTR Exercise the real GLS function against an isolated carrier transport.

if (extension_loaded('curl')) {
    throw new RuntimeException('Run this transport fixture with php -n');
}

define('CURLOPT_RETURNTRANSFER', 1);
define('CURLOPT_HTTPHEADER', 2);
define('CURLOPT_POSTFIELDS', 3);

function curl_init($url)
{
    return (object)['url' => $url];
}

function curl_setopt_array($handle, $options)
{
    file_put_contents('../temp/fixture/transport.json', json_encode([
        'url' => $handle->url,
        'headers' => $options[CURLOPT_HTTPHEADER],
        'return_transfer' => $options[CURLOPT_RETURNTRANSFER],
        'request' => json_decode($options[CURLOPT_POSTFIELDS], true),
    ]));
    return true;
}

function curl_exec($handle)
{
    return $GLOBALS['scenario'] === 'invalid' ? '{}' : json_encode(['PDF' => base64_encode('%PDF-1.4 fixture label')]);
}

function curl_errno($handle)
{
    return $GLOBALS['scenario'] === 'error' ? 7 : 0;
}

function curl_error($handle)
{
    return 'fixture transport failure';
}

function curl_close($handle)
{
}

[$script, $directory, $scenario] = $argv;
chdir($directory . '/run');
$db = 'fixture';
require dirname(__DIR__, 3) . '/includes/ordrefunc.php';

gls_label(
    'fixture-user',
    bin2hex(random_bytes(16)),
    42,
    7,
    123,
    'customer-reference',
    'Billing &amp; Company',
    'Test &quot;Street&quot; 1',
    '12345',
    'Malm&ouml;',
    $scenario === 'delivery' ? 'unrecognized' : 'Sweden',
    'shipping@example.invalid',
    $scenario === 'delivery' ? str_repeat('N', 45) : '',
    'Delivery &amp; Street 2',
    '8000',
    '&Aring;rhus',
    'Sweden',
    $scenario === 'delivery' ? str_repeat('C', 45) : 'O&#039;Brien',
    $scenario === 'delivery' ? 2.1 : 0
);
