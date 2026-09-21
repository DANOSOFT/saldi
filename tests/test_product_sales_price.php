<?php
// 20260920 CDX/LH Verify product-price input types and the real endpoint's HTTP-400 error mapping.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message) { throw new RuntimeException($message); });
require_once(__DIR__ . '/../restapi/models/lager/VareModel.php');
require_once(__DIR__ . '/../restapi/core/ApiException.php');
function checkPrice($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
$product = new VareModel();
foreach (array(0, 1, 1.5, 99.995, 1234.56) as $value) {
    $product->setSalesPrice($value);
    checkPrice($product->getSalesPrice() === (float)$value, 'Preserve numeric JSON price ' . $value);
}
$invalid = array('1,50', '1.50', '1.234,56', '123abc', '', true, false, null, array(1), (object)array('amount'=>1), -1, INF, NAN);
foreach ($invalid as $value) {
    $product->setSalesPrice(42.5);
    try {
        $product->setSalesPrice($value);
        throw new RuntimeException('Invalid input was accepted');
    } catch (InvalidArgumentException $e) {
        checkPrice($product->getSalesPrice() === 42.5, 'Reject ' . get_debug_type($value) . ' without changing the current price');
    }
}
// Exercise the actual POST handler before it can call save(); only the HTTP/auth shell is replaced.
class BaseEndpoint {
    public $lastError;
    protected function validateData($data, $fields) { }
    protected function handleError($error) { $this->lastError = $error; }
}
$source = file_get_contents(__DIR__ . '/../restapi/endpoints/v1/products/index.php');
$source = substr($source, strpos($source, 'class ProductsEndpoint'));
$source = substr($source, 0, strpos($source, '// Initialize and handle the request'));
eval($source);
$endpoint = (new ReflectionClass('ProductsEndpoint'))->newInstanceWithoutConstructor();
$post = new ReflectionMethod('ProductsEndpoint', 'handlePost');
foreach (array('1,50', '1.50', null, -1, true) as $value) {
    $post->invoke($endpoint, (object)array('sku'=>'PRICE-TEST', 'description'=>'Price regression', 'salesPrice'=>$value));
    checkPrice($endpoint->lastError instanceof ApiException && $endpoint->lastError->getStatusCode() === 400, 'POST rejects invalid price as HTTP 400 before any database write');
}
