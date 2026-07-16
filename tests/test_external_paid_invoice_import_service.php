<?php
/**
 * Run: php tests/test_external_paid_invoice_import_service.php
 */

$passed = 0;
$failed = 0;
$transactionCalls = array();
$dbModifyCalls = array();
$mockDb = null;

class MockResult
{
    public $rows;
    public $index = 0;

    public function __construct($rows)
    {
        $this->rows = $rows;
    }
}

class MockDbHandler
{
    private $responses = array();
    public $queries = array();

    public function addResponse($needle, $rows, $consume = false)
    {
        $this->responses[] = array('needle' => $needle, 'rows' => $rows, 'consume' => $consume);
    }

    public function select($query)
    {
        $this->queries[] = $query;
        for ($i = count($this->responses) - 1; $i >= 0; $i--) {
            if (strpos($query, $this->responses[$i]['needle']) !== false) {
                $response = $this->responses[$i];
                if ($response['consume']) {
                    array_splice($this->responses, $i, 1);
                }
                return new MockResult($response['rows']);
            }
        }

        return new MockResult(array());
    }
}

function db_select($query, $source)
{
    global $mockDb;
    return $mockDb->select($query);
}

function db_fetch_array($result)
{
    if (!($result instanceof MockResult)) {
        return false;
    }
    if ($result->index >= count($result->rows)) {
        return false;
    }

    $row = $result->rows[$result->index];
    $result->index++;
    return $row;
}

function db_modify($query, $source)
{
    global $dbModifyCalls;
    $dbModifyCalls[] = $query;
    return "0\tOK";
}

function db_escape_string($value)
{
    return str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $value);
}

function chk4utf8($query)
{
    return $query;
}

function transaktion($action)
{
    global $transactionCalls;
    $transactionCalls[] = $action;
    return true;
}

require_once __DIR__ . '/../restapi/core/ApiException.php';
require_once __DIR__ . '/../restapi/services/ExternalPaidInvoiceImportService.php';

function check($condition, $message)
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS  $message\n";
    } else {
        $failed++;
        echo "FAIL  $message\n";
    }
}

function fail($message)
{
    check(false, $message);
}

function resetMocks()
{
    global $transactionCalls, $dbModifyCalls, $mockDb;
    $transactionCalls = array();
    $dbModifyCalls = array();
    $mockDb = new MockDbHandler();
}

function configureImportPrerequisites()
{
    global $mockDb;
    $mockDb->addResponse("SELECT kodenr FROM grupper WHERE art='RA'", array(
        array('kodenr' => '2026'),
    ));
    $mockDb->addResponse("SELECT var_value FROM settings WHERE var_name = 'baseCurrency'", array(
        array('var_value' => 'DKK'),
    ));
    $mockDb->addResponse("SELECT box10 FROM grupper WHERE art = 'DIV' AND kodenr = '3'", array(
        array('box10' => '5820'),
    ));
    $mockDb->addResponse("FROM kontoplan WHERE kontonr = '5820'", array(
        array('kontonr' => '5820'),
    ));
    $mockDb->addResponse("FROM grupper WHERE art = 'DG'", array(
        array('kodenr' => '1', 'box1' => 'S1'),
    ));
    $mockDb->addResponse("FROM varer WHERE UPPER(varenr)", array(
        array(
            'id' => '10', 'varenr' => 'STRIPE-SERVICE', 'varenr_alias' => '',
            'beskrivelse' => 'Stripe service', 'gruppe' => '1', 'samlevare' => '',
            'm_antal' => '', 'm_rabat' => '',
        ),
    ));
    $mockDb->addResponse("FROM grupper WHERE art = 'VG'", array(
        array('box4' => '1000'),
    ));
    $mockDb->addResponse("FROM kontoplan WHERE kontonr = '1000'", array(
        array('moms' => 'S1'),
    ));
    $mockDb->addResponse("FROM grupper WHERE art = 'SM'", array(
        array('box2' => '25'),
    ));
}

function basePayload()
{
    return array(
        'externalInvoiceId' => 'in_1Qwerty123456789',
        'payloadHash' => '4f7a1b6a9d8c2e1f',
        'invoiceDate' => '2026-07-15',
        'currency' => 'DKK',
        'totals' => array(
            'netOre' => 100000,
            'vatOre' => 25000,
            'grossOre' => 125000,
        ),
        'customer' => array(
            'companyName' => 'ACME ApS',
            'contactName' => 'Jane Doe',
            'address1' => 'Main Street 123',
            'address2' => 'Suite 4',
            'postalCode' => '2100',
            'city' => 'Copenhagen',
            'country' => 'Denmark',
            'cvr' => '12345678',
            'email' => 'billing@acme.example',
            'phone' => '+4511223344',
            'groupCode' => 1,
        ),
        'lines' => array(
            array(
                'sku' => 'STRIPE-SERVICE',
                'description' => 'Consulting July 2026',
                'quantity' => '1',
                'netOre' => 100000,
                'vatOre' => 25000,
                'grossOre' => 125000,
            ),
        ),
    );
}

function expectApiException($callable, $statusCode, $messagePart)
{
    try {
        $callable();
        fail('Expected ApiException with status ' . $statusCode);
    } catch (ApiException $exception) {
        check(
            $exception->getStatusCode() === $statusCode && strpos($exception->getMessage(), $messagePart) !== false,
            'Throws ApiException ' . $statusCode . ' containing "' . $messagePart . '"'
        );
    }
}

$service = new ExternalPaidInvoiceImportService();

$normalized = $service->validatePayload(basePayload());
check($normalized['currency'] === 'DKK', 'validatePayload accepts a valid DKK payload');
check($normalized['lines'][0]['quantity'] === '1', 'validatePayload keeps normalized quantity strings');

$badCurrency = basePayload();
$badCurrency['currency'] = 'EUR';
expectApiException(function () use ($service, $badCurrency) {
    $service->validatePayload($badCurrency);
}, 422, 'Only DKK invoices are supported');

$forbidden = basePayload();
$forbidden['customer']['ean'] = '5790000000000';
expectApiException(function () use ($service, $forbidden) {
    $service->validatePayload($forbidden);
}, 422, 'EAN and OIOUBL');

resetMocks();
configureImportPrerequisites();
$mockDb->addResponse("SELECT id, betalings_id, status, valuta FROM ordrer WHERE art = 'DO' AND shop_status = 'stripe_paid_bridge'", array(
    array('id' => 55, 'betalings_id' => '4f7a1b6a9d8c2e1f', 'status' => '4', 'valuta' => 'DKK'),
));
$mockDb->addResponse("SELECT id, fakturanr, status, valuta, sum, moms, betalt, betalingsbet, kundeordnr, betalings_id, shop_status FROM ordrer WHERE id = '55'", array(
    array(
        'id' => 55,
        'fakturanr' => '9001',
        'status' => '4',
        'valuta' => 'DKK',
        'sum' => '1000.00',
        'moms' => '250.00',
        'betalt' => 'on',
        'betalingsbet' => 'Kreditkort',
        'kundeordnr' => 'in_1Qwerty123456789',
        'betalings_id' => '4f7a1b6a9d8c2e1f',
        'shop_status' => 'stripe_paid_bridge',
    ),
));
$mockDb->addResponse("FROM ordrelinjer WHERE ordre_id = '55'", array(
    array(
        'varenr' => 'STRIPE-SERVICE',
        'beskrivelse' => 'Consulting July 2026',
        'antal' => '1',
        'pris' => '1000.00',
        'rabat' => '0',
        'rabatart' => '',
        'procent' => '100',
        'momssats' => '25',
        'momsfri' => '',
        'posnr' => '1',
    ),
));
$mockDb->addResponse("SELECT id FROM pos_betalinger WHERE ordre_id = '55'", array());
$mockDb->addResponse("SELECT id FROM openpost WHERE refnr = '55'", array());
$mockDb->addResponse("SELECT id FROM transaktioner WHERE ordre_id = '55' AND kontonr = '5820'", array(
    array('id' => 800),
));
$result = $service->import(basePayload(), 'bridge-user');
check($result['idempotent'] === true && $result['invoiceNumber'] === 9001, 'same externalInvoiceId and payloadHash returns the existing posted result');
check($transactionCalls === array('begin', 'rollback'), 'idempotent replay uses a no-op rollback instead of commit');

resetMocks();
configureImportPrerequisites();
$mockDb->addResponse("SELECT id, betalings_id, status, valuta FROM ordrer WHERE art = 'DO' AND shop_status = 'stripe_paid_bridge'", array(
    array('id' => 55, 'betalings_id' => 'different-hash', 'status' => '4', 'valuta' => 'DKK'),
));
expectApiException(function () use ($service) {
    $service->import(basePayload(), 'bridge-user');
}, 409, 'different payloadHash');
check($transactionCalls === array('begin', 'rollback'), 'conflicting idempotency key rolls the transaction back');

resetMocks();
$mockDb->addResponse("SELECT kodenr FROM grupper WHERE art='RA'", array(
    array('kodenr' => '2026'),
));
$mockDb->addResponse("SELECT var_value FROM settings WHERE var_name = 'baseCurrency'", array(
    array('var_value' => 'DKK'),
));
$mockDb->addResponse("SELECT box10 FROM grupper WHERE art = 'DIV' AND kodenr = '3'", array(
    array('box10' => ''),
));
expectApiException(function () use ($service) {
    $service->import(basePayload(), 'bridge-user');
}, 422, 'Card clearing account is not configured');
check($transactionCalls === array(), 'missing card-clearing configuration fails before opening a transaction');

echo "\nResults: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
