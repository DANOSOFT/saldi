<?php
// 20260910 CDX/PHR Cover local UBL extraction without an API key and reject unsafe XML.
// 20260922 CL/LAH Cover the seller/buyer identity fields (vendorCvr, vendorIban, vendorBankReg,
//                  vendorBankKonto, customerCvr) from the API response and from UBL XML.
/**
 * Focused tests for includes/docsIncludes/invoiceExtractionApi.php.
 * Run: php tests/test_invoice_extraction_api.php
 */

$passed = 0;
$failed = 0;

// Every extraction result carries the party identity keys; null when the source has none.
$noIdentity = array('vendorCvr' => null, 'vendorIban' => null, 'vendorBankReg' => null, 'vendorBankKonto' => null, 'customerCvr' => null);

function check($condition, $message) {
	global $passed, $failed;
	if ($condition) {
		$passed++;
		echo "PASS  $message\n";
	} else {
		$failed++;
		echo "FAIL  $message\n";
	}
}

function db_select($query, $source, $global = false) {
	global $dbSelectCalls, $dbSelectResult;
	$dbSelectCalls[] = array($query, $source, $global);
	return $dbSelectResult;
}

function db_fetch_array($query) {
	return $query;
}

include __DIR__ . '/../includes/docsIncludes/invoiceExtractionApi.php';

$tempDir = sys_get_temp_dir() . '/saldi_invoice_extraction_' . uniqid();
mkdir($tempDir);
$pdfPath = $tempDir . '/invoice.pdf';
$pdfBytes = "%PDF-1.7\npage-one\fpage-two\n%%EOF";
file_put_contents($pdfPath, $pdfBytes);
$xmlPath = $tempDir . '/invoice.xml';
$xmlBytes = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
  <cbc:CustomizationID>urn:cen.eu:en16931:2017</cbc:CustomizationID>
  <cbc:ID>INV-42</cbc:ID>
  <cbc:IssueDate>2026-07-23</cbc:IssueDate>
  <cbc:DocumentCurrencyCode>DKK</cbc:DocumentCurrencyCode>
  <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Leverandør ApS</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
  <cac:LegalMonetaryTotal><cbc:PayableAmount currencyID="DKK">1250.00</cbc:PayableAmount></cac:LegalMonetaryTotal>
  <cac:InvoiceLine><cac:Item><cbc:Name>Konsulentydelse</cbc:Name></cac:Item></cac:InvoiceLine>
</Invoice>';
file_put_contents($xmlPath, $xmlBytes);

$dbSelectCalls = array();
$dbSelectResult = array('var_value' => 'global-key');
check(invoiceExtractionApiResolveApiKey() === 'global-key', 'resolves the global API key');
check(count($dbSelectCalls) === 1 && $dbSelectCalls[0][2] === true, 'uses db_select global connection');
check(strpos($dbSelectCalls[0][0], "var_name = 'apikey'") !== false && strpos($dbSelectCalls[0][0], "var_grp = 'app_api'") !== false, 'queries the app_api key setting');

// Endpoint: settings row wins when it is a URL, otherwise the wuweiworkai.com default.
$dbSelectResult = array('var_value' => 'https://ai.saldi.dk/extract-invoice');
check(invoiceExtractionApiResolveUrl() === 'https://ai.saldi.dk/extract-invoice', 'uses the extract_url setting when present');
$dbSelectResult = array('var_value' => 'not a url');
check(invoiceExtractionApiResolveUrl() === 'https://wuweiworkai.com/extract-invoice', 'falls back to the default when the setting is not a URL');
$dbSelectResult = false;
check(invoiceExtractionApiResolveUrl() === 'https://wuweiworkai.com/extract-invoice', 'falls back to the default when no setting exists');
check(count($dbSelectCalls) === 4 && $dbSelectCalls[3][2] === true && strpos($dbSelectCalls[3][0], "var_name = 'extract_url'") !== false, 'reads extract_url from the global settings table');

$captured = array();
$invoiceExtractionApiDependencies = array(
	'key_resolver' => function () { return 'test-key'; },
	'transport' => function ($url, $headers, $body, $options) use (&$captured) {
		$captured = array($url, $headers, json_decode($body, true), $options);
		return array('response' => json_encode(array(
			'status' => 'partial_success',
			'extracted_data' => array('total_amount' => '123.45', 'invoice_date' => '26-01-2026', 'vendor' => 'Acme', 'invoice_number' => 'A-1', 'invoice_description' => 'Widgets', 'currency' => 'DKK')
		)), 'http_code' => 200, 'error' => '', 'errno' => 0);
	}
);
$result = extractInvoiceData($pdfPath, 'invoice-test');
check($captured[0] === 'https://wuweiworkai.com/extract-invoice', 'posts to the resolved extraction route (default host)');
check(in_array('Authorization: Bearer test-key', $captured[1], true), 'sends bearer authentication');
check($captured[2]['id'] === 'invoice-test' && $captured[2]['skip_classification'] === true, 'sends required payload fields');
check(base64_decode($captured[2]['image']) === $pdfBytes, 'passes original multi-page PDF bytes unchanged');
check($captured[3] === array('connect_timeout' => 10, 'timeout' => 120), 'uses required timeouts');
check($result === array('amount' => '123.45', 'date' => '2026-01-26', 'vendor' => 'Acme', 'invoiceNumber' => 'A-1', 'description' => 'Widgets', 'currency' => 'DKK') + $noIdentity, 'accepts partial success and preserves SALDI fields');

// Party identity from the extract-invoice service (fields added 2026-09-22). Values are
// passed through as printed; normalization happens in poolVendorMatcher.php.
$invoiceExtractionApiDependencies['transport'] = function () {
	return array('response' => json_encode(array(
		'status' => 'success',
		'extracted_data' => array(
			'total_amount' => '1862.50', 'currency' => 'DKK', 'vendor' => 'Dan Group Alarm ApS',
			'vendor_vat_number' => 'DK 12 34 56 78', 'vendor_iban' => 'DK50 0040 0440 1162 43',
			'vendor_bank_reg' => '0040', 'vendor_bank_account' => '0440116243',
			'customer_name' => 'MEDshop.dk ApS', 'customer_vat_number' => 'DK31500362'
		)
	)), 'http_code' => 200);
};
$result = extractInvoiceData($pdfPath, 'identity-test');
check($result !== null && $result['vendorCvr'] === 'DK 12 34 56 78' && $result['customerCvr'] === 'DK31500362', 'returns seller and buyer VAT numbers separately');
check($result !== null && $result['vendorIban'] === 'DK50 0040 0440 1162 43' && $result['vendorBankReg'] === '0040' && $result['vendorBankKonto'] === '0440116243', 'returns the seller bank details');

$invoiceExtractionApiDependencies['transport'] = function () {
	return array('response' => json_encode(array(
		'status' => 'success',
		'extracted_data' => array('total_amount' => '10.00', 'currency' => 'DKK', 'vendor' => 'Kiosk', 'vendor_vat_number' => 'null', 'vendor_iban' => '', 'customer_vat_number' => 'N/A')
	)), 'http_code' => 200);
};
$result = extractInvoiceData($pdfPath, 'identity-null-test');
check($result !== null && $result['vendorCvr'] === null && $result['vendorIban'] === null && $result['customerCvr'] === null, 'treats empty/"null"/"N/A" identity values as absent');

$transportCalls = 0;
$invoiceExtractionApiDependencies = array(
	'key_resolver' => function () { return null; },
	'transport' => function () use (&$transportCalls) { $transportCalls++; return array(); }
);
$xmlResult = extractInvoiceData($xmlPath, 'xml-test');
check($xmlResult === array('amount' => '1250.00', 'date' => '2026-07-23', 'vendor' => 'Leverandør ApS', 'invoiceNumber' => 'INV-42', 'description' => 'Konsulentydelse', 'currency' => 'DKK') + $noIdentity, 'extracts standard OIOUBL/Peppol fields locally');

$identityXml = str_replace(
	'<cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Leverandør ApS</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>',
	'<cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Leverandør ApS</cbc:Name></cac:PartyName><cac:PartyTaxScheme><cbc:CompanyID>DK12345678</cbc:CompanyID></cac:PartyTaxScheme></cac:Party></cac:AccountingSupplierParty>'
	. '<cac:AccountingCustomerParty><cac:Party><cac:PartyLegalEntity><cbc:RegistrationName>Køber A/S</cbc:RegistrationName><cbc:CompanyID>DK31500362</cbc:CompanyID></cac:PartyLegalEntity></cac:Party></cac:AccountingCustomerParty>'
	. '<cac:PaymentMeans><cac:PayeeFinancialAccount><cbc:ID>0001001348</cbc:ID><cac:FinancialInstitutionBranch><cbc:ID>0892</cbc:ID></cac:FinancialInstitutionBranch></cac:PayeeFinancialAccount></cac:PaymentMeans>',
	$xmlBytes
);
$placeholderXml = str_replace('<cac:InvoiceLine><cac:Item><cbc:Name>Konsulentydelse</cbc:Name></cac:Item></cac:InvoiceLine>',
	'<cac:InvoiceLine><cac:Item><cbc:Description>.</cbc:Description><cbc:Name>.</cbc:Name></cac:Item></cac:InvoiceLine><cac:InvoiceLine><cac:Item><cbc:Name>-</cbc:Name></cac:Item></cac:InvoiceLine><cac:InvoiceLine><cac:Item><cbc:Name>Konsulentydelse</cbc:Name></cac:Item></cac:InvoiceLine>', $xmlBytes);
file_put_contents($xmlPath, $placeholderXml);
$placeholderResult = extractInvoiceData($xmlPath, 'xml-placeholder');
check($placeholderResult !== null && $placeholderResult['description'] === 'Konsulentydelse', 'drops punctuation-only UBL line names from the description');
file_put_contents($xmlPath, $identityXml);
$identityResult = extractInvoiceData($xmlPath, 'xml-identity');
check($identityResult !== null && $identityResult['vendorCvr'] === 'DK12345678' && $identityResult['customerCvr'] === 'DK31500362', 'reads supplier and customer CompanyID from UBL');
check($identityResult !== null && $identityResult['vendorIban'] === null && $identityResult['vendorBankReg'] === '0892' && $identityResult['vendorBankKonto'] === '0001001348', 'reads a Danish reg/konto payee account from UBL');
file_put_contents($xmlPath, str_replace('<cbc:ID>0001001348</cbc:ID>', '<cbc:ID schemeID="IBAN">DK50 0040 0440 1162 43</cbc:ID>', $identityXml));
$identityResult = extractInvoiceData($xmlPath, 'xml-iban');
check($identityResult !== null && $identityResult['vendorIban'] === 'DK5000400440116243' && $identityResult['vendorBankKonto'] === null, 'reads an IBAN payee account from UBL');
file_put_contents($xmlPath, $xmlBytes);
check($transportCalls === 0, 'does not call the AI transport for XML invoices');

$unsafeXmlPath = $tempDir . '/unsafe.xml';
file_put_contents($unsafeXmlPath, '<!DOCTYPE Invoice [<!ENTITY secret SYSTEM "file:///etc/passwd">]><Invoice>&secret;</Invoice>');
check(extractInvoiceData($unsafeXmlPath, 'unsafe-xml') === null, 'rejects XML document type and entity declarations');

$creditXml = str_replace(array('<Invoice ', '</Invoice>', 'InvoiceLine', '<cac:PartyName><cbc:Name>Leverandør ApS</cbc:Name></cac:PartyName>'), array('<CreditNote ', '</CreditNote>', 'CreditNoteLine', '<cac:PartyLegalEntity><cbc:RegistrationName>Leverandør ApS</cbc:RegistrationName></cac:PartyLegalEntity>'), $xmlBytes);
$creditXml = str_replace(array('currencyID="DKK"', 'xsd:Invoice-2'), array('', 'xsd:CreditNote-2'), $creditXml);
file_put_contents($xmlPath, $creditXml);
check(extractInvoiceData($xmlPath) === $xmlResult, 'extracts credit note fields with legal-name and currency fallbacks');
file_put_contents($xmlPath, '<Invoice>');
check(extractInvoiceData($xmlPath) === null, 'rejects malformed XML');
file_put_contents($xmlPath, '<Order><ID>42</ID></Order>');
check(extractInvoiceData($xmlPath) === null, 'rejects unsupported XML document types');
file_put_contents($xmlPath, '');
check(extractInvoiceData($xmlPath) === null, 'rejects empty XML');

$transportCalls = 0;
$invoiceExtractionApiDependencies = array(
	'key_resolver' => function () { return null; },
	'transport' => function () use (&$transportCalls) { $transportCalls++; return array(); }
);
check(extractInvoiceData($pdfPath, 'missing-key') === null && $transportCalls === 0, 'does not call transport without an API key');

foreach (array(
	'HTTP failure' => array('response' => 'gateway unavailable', 'http_code' => 503, 'error' => '', 'errno' => 0),
	'transport failure' => array('response' => false, 'http_code' => 0, 'error' => 'Could not resolve host', 'errno' => 6),
	'timeout' => array('response' => false, 'http_code' => 0, 'error' => 'Operation timed out', 'errno' => 28)
) as $label => $transportResult) {
	$invoiceExtractionApiDependencies = array(
		'key_resolver' => function () { return 'test-key'; },
		'transport' => function () use ($transportResult) { return $transportResult; }
	);
	check(extractInvoiceData($pdfPath, 'failure-test') === null, "returns null for $label");
}

require_once(__DIR__ . '/../includes/docsIncludes/FileReservation.php');
$xmlReservation = FileReservation::reserve($tempDir, 'invoice', 'xml', array('pdf', 'jpg', 'jpeg', 'png', 'xml', 'info'));
check($xmlReservation !== null && $xmlReservation->baseName() === 'invoice_1' && $xmlReservation->ext() === 'xml', 'preserves XML extension when an upload filename is already taken');
if ($xmlReservation !== null) $xmlReservation->discard();

unset($invoiceExtractionApiDependencies);
unlink($pdfPath);
unlink($xmlPath);
unlink($unsafeXmlPath);
rmdir($tempDir);

echo "\nResults: $passed passed, $failed failed\n";
exit($failed ? 1 : 0);
