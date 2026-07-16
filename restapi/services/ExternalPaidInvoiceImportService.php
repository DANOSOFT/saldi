<?php
// 20260716 CL/PHR Added atomic, idempotent Stripe paid-invoice import service.

require_once __DIR__ . '/../core/ApiException.php';

class ExternalPaidInvoiceImportService
{
    const IMPORT_MARKER = 'stripe_paid_bridge';
    const CARD_PAYMENT_TERM = 'Kreditkort';

    public function import($request, $username)
    {
        $payload = $this->validatePayload($request);
        $transactionOpen = false;

        try {
            $this->prepareLegacyGlobals($payload, $username);

            $regnaar = $this->resolveFiscalYear($payload['invoiceDate']);
            $GLOBALS['regnaar'] = $regnaar;

            $baseCurrency = strtoupper($this->getSettingValue('baseCurrency', 'DKK'));
            if ($baseCurrency !== 'DKK') {
                throw new ApiException('The Stripe paid-invoice bridge only supports databases with DKK as baseCurrency', 422);
            }

            $cardClearingAccount = $this->requireCardClearingAccount($regnaar);
            $debtorGroup = $this->requireDebtorGroup($payload['customer']['groupCode'], $regnaar);
            $preparedLines = $this->prepareLines($payload['lines'], $regnaar);

            transaktion('begin');
            $transactionOpen = true;
            $this->lockImportTables();

            $existingOrders = $this->findExistingImports($payload['externalInvoiceId']);
            if (count($existingOrders) > 1) {
                throw new ApiException('externalInvoiceId is associated with multiple imported invoices', 409);
            }
            if (count($existingOrders) === 1) {
                $existingOrder = $existingOrders[0];
                if ((string) $existingOrder['betalings_id'] !== $payload['payloadHash']) {
                    throw new ApiException('externalInvoiceId is already imported with a different payloadHash', 409);
                }

                $result = $this->buildPostedResponse((int) $existingOrder['id'], $payload, $cardClearingAccount, true);
                transaktion('rollback');
                $transactionOpen = false;

                return $result;
            }

            $debtor = $this->findOrCreateDebtor($payload['customer'], $debtorGroup, $username);
            $orderId = $this->createDraftOrder($payload, $debtor, $preparedLines['headerVatRate'], $username);

            $this->insertOrderLines($orderId, $preparedLines['lines']);
            $this->reconcileDraftTotals($orderId, $payload, $preparedLines['lines']);
            $this->deliverAndPost($orderId);

            $result = $this->buildPostedResponse($orderId, $payload, $cardClearingAccount, false);

            transaktion('commit');
            $transactionOpen = false;

            return $result;
        } catch (ApiException $exception) {
            if ($transactionOpen) {
                transaktion('rollback');
            }
            throw $exception;
        } catch (Exception $exception) {
            if ($transactionOpen) {
                transaktion('rollback');
            }
            throw new ApiException('Stripe invoice import failed: ' . $exception->getMessage(), 500);
        }
    }

    public function validatePayload($request)
    {
        $payload = $this->normalizeValue($request);
        if (!is_array($payload)) {
            throw new ApiException('Request body must be a JSON object', 400);
        }

        $this->rejectForbiddenKeys($payload);

        $externalInvoiceId = $this->requireString($payload, 'externalInvoiceId');
        if (!preg_match('/^in_[A-Za-z0-9]+$/', $externalInvoiceId)) {
            throw new ApiException('externalInvoiceId must be a Stripe invoice id starting with in_', 422);
        }

        $payloadHash = $this->requireString($payload, 'payloadHash');
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $payloadHash)) {
            throw new ApiException('payloadHash must be 8-128 characters and only contain letters, digits, dot, underscore, colon or hyphen', 422);
        }

        $invoiceDate = $this->requireDate($payload, 'invoiceDate');
        $currency = strtoupper($this->requireString($payload, 'currency'));
        if ($currency !== 'DKK') {
            throw new ApiException('Only DKK invoices are supported', 422);
        }

        $totals = $this->requireArray($payload, 'totals');
        $netOre = $this->requireIntegerField($totals, 'netOre', true);
        $vatOre = $this->requireIntegerField($totals, 'vatOre', false);
        $grossOre = $this->requireIntegerField($totals, 'grossOre', true);
        if ($grossOre !== $netOre + $vatOre) {
            throw new ApiException('Invoice grossOre must equal netOre + vatOre', 422);
        }

        $customer = $this->requireArray($payload, 'customer');
        $normalizedCustomer = $this->normalizeCustomer($customer);

        $lines = $this->requireLines($payload);
        $normalizedLines = array();
        $lineNetOre = 0;
        $lineVatOre = 0;
        $lineGrossOre = 0;

        for ($index = 0; $index < count($lines); $index++) {
            $normalizedLine = $this->normalizeLine($lines[$index], $index + 1);
            $this->deriveSupportedVatRate($normalizedLine['netOre'], $normalizedLine['vatOre'], 'lines[' . $index . ']');
            $normalizedLines[] = $normalizedLine;
            $lineNetOre += $normalizedLine['netOre'];
            $lineVatOre += $normalizedLine['vatOre'];
            $lineGrossOre += $normalizedLine['grossOre'];
        }

        if ($lineNetOre !== $netOre || $lineVatOre !== $vatOre || $lineGrossOre !== $grossOre) {
            throw new ApiException('Invoice totals must reconcile exactly with the provided lines', 422);
        }

        return array(
            'externalInvoiceId' => $externalInvoiceId,
            'payloadHash' => $payloadHash,
            'invoiceDate' => $invoiceDate,
            'currency' => $currency,
            'totals' => array(
                'netOre' => $netOre,
                'vatOre' => $vatOre,
                'grossOre' => $grossOre,
            ),
            'customer' => $normalizedCustomer,
            'lines' => $normalizedLines,
        );
    }

    private function normalizeValue($value)
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $normalized = array();
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeValue($item);
        }

        return $normalized;
    }

    private function rejectForbiddenKeys($value)
    {
        if (!is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, array('ean', 'eannumber', 'institution', 'oioubl', 'oio_ubl', 'oioublprofileid', 'endpointid'), true)) {
                throw new ApiException('EAN and OIOUBL fields are not accepted by this endpoint', 422);
            }
            $this->rejectForbiddenKeys($item);
        }
    }

    private function normalizeCustomer($customer)
    {
        $companyName = $this->optionalString($customer, 'companyName');
        $contactName = $this->optionalString($customer, 'contactName');
        if ($companyName === '' && $contactName === '') {
            throw new ApiException('customer.companyName or customer.contactName is required', 422);
        }

        $cvr = preg_replace('/\s+/', '', $this->requireString($customer, 'cvr'));
        if (!preg_match('/^[0-9]{8}$/', $cvr)) {
            throw new ApiException('customer.cvr must be an 8-digit Danish CVR number', 422);
        }

        $email = $this->optionalString($customer, 'email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ApiException('customer.email must be a valid email address when provided', 422);
        }

        $phone = $this->optionalString($customer, 'phone');
        $groupCode = $this->requireIntegerField($customer, 'groupCode', true);

        return array(
            'companyName' => $companyName,
            'contactName' => $contactName,
            'address1' => $this->requireString($customer, 'address1'),
            'address2' => $this->optionalString($customer, 'address2'),
            'postalCode' => $this->requireString($customer, 'postalCode'),
            'city' => $this->requireString($customer, 'city'),
            'country' => $this->requireString($customer, 'country'),
            'cvr' => $cvr,
            'email' => $email,
            'phone' => $phone,
            'groupCode' => $groupCode,
        );
    }

    private function requireLines($payload)
    {
        if (!isset($payload['lines']) || !is_array($payload['lines']) || count($payload['lines']) === 0) {
            throw new ApiException('At least one invoice line is required', 422);
        }

        return array_values($payload['lines']);
    }

    private function normalizeLine($line, $position)
    {
        if (!is_array($line)) {
            throw new ApiException('lines[' . ($position - 1) . '] must be an object', 422);
        }

        $sku = $this->requireString($line, 'sku');
        $quantityInfo = $this->normalizeQuantity($line, 'quantity');
        $netOre = $this->requireIntegerField($line, 'netOre', true);
        $vatOre = $this->requireIntegerField($line, 'vatOre', false);
        $grossOre = $this->requireIntegerField($line, 'grossOre', true);

        if ($grossOre !== $netOre + $vatOre) {
            throw new ApiException('lines[' . ($position - 1) . '].grossOre must equal netOre + vatOre', 422);
        }

        return array(
            'position' => $position,
            'sku' => $sku,
            'description' => $this->optionalString($line, 'description'),
            'quantity' => $quantityInfo['string'],
            'quantityMilli' => $quantityInfo['milli'],
            'netOre' => $netOre,
            'vatOre' => $vatOre,
            'grossOre' => $grossOre,
        );
    }
    private function normalizeQuantity($data, $field)
    {
        if (!array_key_exists($field, $data)) {
            throw new ApiException($field . ' is required', 422);
        }

        $value = $data[$field];
        if (is_int($value)) {
            $quantity = (string) $value;
        } elseif (is_float($value)) {
            if (!is_finite($value) || $value <= 0) {
                throw new ApiException($field . ' must be a positive finite quantity', 422);
            }
            if (abs(round($value, 3) - $value) > 0.0000001) {
                throw new ApiException($field . ' may have at most three decimals', 422);
            }
            $quantity = number_format(round($value, 3), 3, '.', '');
        } elseif (is_string($value)) {
            $quantity = trim($value);
        } else {
            throw new ApiException($field . ' must be a positive finite quantity', 422);
        }

        if (!preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/', $quantity)) {
            throw new ApiException($field . ' must be a positive quantity with up to three decimals', 422);
        }

        $quantity = rtrim(rtrim($quantity, '0'), '.');
        if ($quantity === '') {
            $quantity = '0';
        }

        $parts = explode('.', $quantity, 2);
        $milli = ((int) $parts[0]) * 1000;
        if (isset($parts[1])) {
            $milli += (int) str_pad($parts[1], 3, '0', STR_PAD_RIGHT);
        }

        if ($milli <= 0) {
            throw new ApiException($field . ' must be greater than zero', 422);
        }

        return array(
            'string' => $quantity,
            'milli' => $milli,
        );
    }

    private function prepareLegacyGlobals($payload, $username)
    {
        $GLOBALS['brugernavn'] = $username;
        $GLOBALS['webservice'] = true;
        $GLOBALS['baseCurrency'] = $payload['currency'];
        $GLOBALS['fakturadate'] = $payload['invoiceDate'];
        $GLOBALS['levdate'] = $payload['invoiceDate'];
        $GLOBALS['valutakurs'] = 100;
    }

    private function lockImportTables()
    {
        $query = 'LOCK TABLE ordrer, ordrelinjer, adresser, batch_salg, batch_kob, lagerstatus, reservation, transaktioner, openpost, pos_betalinger, kontoplan IN EXCLUSIVE MODE';
        $this->execute($query);
    }

    private function findExistingImports($externalInvoiceId)
    {
        $externalInvoiceId = $this->escape($externalInvoiceId);
        $marker = $this->escape(self::IMPORT_MARKER);
        $query = "SELECT id, betalings_id, status, valuta FROM ordrer WHERE art = 'DO' AND shop_status = '$marker' AND kundeordnr = '$externalInvoiceId' ORDER BY id";

        return $this->fetchAll($query);
    }

    private function resolveFiscalYear($invoiceDate)
    {
        $year = (int) substr($invoiceDate, 0, 4);
        $month = (int) substr($invoiceDate, 5, 2);

        $del1 = "(box1<='$month' and box2<='$year' and box3>='$month' and box4>='$year')";
        $del2 = "(box1<='$month' and box2<='$year' and box3<'$month' and box4>'$year')";
        $del3 = "(box1>'$month' and box2<'$year' and box3>='$month' and box4>='$year')";
        $row = $this->fetchRow("SELECT kodenr FROM grupper WHERE art='RA' AND ($del1 OR $del2 OR $del3) ORDER BY kodenr DESC LIMIT 1");
        if ($row && isset($row['kodenr'])) {
            return (int) $row['kodenr'];
        }

        $fallback = $this->fetchRow("SELECT MAX(kodenr) AS kodenr FROM grupper WHERE art='RA'");
        if ($fallback && isset($fallback['kodenr']) && $fallback['kodenr'] !== null && $fallback['kodenr'] !== '') {
            return (int) $fallback['kodenr'];
        }

        throw new ApiException('No fiscal year is configured for invoiceDate ' . $invoiceDate, 422);
    }

    private function getSettingValue($name, $default = '')
    {
        $name = $this->escape($name);
        $row = $this->fetchRow("SELECT var_value FROM settings WHERE var_name = '$name' LIMIT 1");
        if ($row && array_key_exists('var_value', $row) && $row['var_value'] !== null && $row['var_value'] !== '') {
            return $row['var_value'];
        }

        return $default;
    }

    private function requireCardClearingAccount($regnaar)
    {
        $row = $this->fetchRow("SELECT box10 FROM grupper WHERE art = 'DIV' AND kodenr = '3' LIMIT 1");
        $kontonr = $row ? trim((string) $row['box10']) : '';
        if ($kontonr === '' || !preg_match('/^[0-9]+$/', $kontonr)) {
            throw new ApiException('Card clearing account is not configured in Indstillinger > Diverse > Ordrerelaterede valg', 422);
        }

        $kontonr = $this->escape($kontonr);
        $exists = $this->fetchRow("SELECT kontonr FROM kontoplan WHERE kontonr = '$kontonr' AND regnskabsaar = '$regnaar' LIMIT 1");
        if (!$exists) {
            throw new ApiException('Configured credit-card clearing account does not exist in kontoplan for the invoice fiscal year', 422);
        }

        return (int) $kontonr;
    }

    private function requireDebtorGroup($groupCode, $regnaar)
    {
        $groupCode = (int) $groupCode;
        if ($groupCode <= 0) {
            throw new ApiException('customer.groupCode must be a positive integer', 422);
        }

        $query = "SELECT kodenr, box1 FROM grupper WHERE art = 'DG' AND kodenr = '$groupCode' AND fiscal_year = '$regnaar' LIMIT 1";
        $row = $this->fetchRow($query);
        if (!$row) {
            throw new ApiException('Customer group ' . $groupCode . ' does not exist for the invoice fiscal year', 422);
        }

        return array(
            'code' => $groupCode,
            'box1' => isset($row['box1']) ? trim((string) $row['box1']) : '',
        );
    }

    private function prepareLines($lines, $regnaar)
    {
        $prepared = array();
        $headerVatRate = 0;

        for ($index = 0; $index < count($lines); $index++) {
            $line = $lines[$index];
            $product = $this->requireProductBySku($line['sku']);
            $this->assertProductCanBeImported($product, $line['sku']);

            $requestedVatRate = $this->deriveSupportedVatRate($line['netOre'], $line['vatOre'], 'lines[' . $index . ']');
            $configuredVatRate = $this->resolveProductVatRate($product['gruppe'], $regnaar, $line['sku']);
            if ((float) $configuredVatRate !== (float) $requestedVatRate) {
                throw new ApiException('Product ' . $line['sku'] . ' VAT setup does not match the requested line VAT', 422);
            }

            $unitPrice = round(($line['netOre'] * 10) / $line['quantityMilli'], 6);
            $prepared[] = array(
                'position' => $line['position'],
                'sku' => $product['varenr'],
                'description' => ($line['description'] !== '') ? $line['description'] : trim((string) $product['beskrivelse']),
                'quantity' => $line['quantity'],
                'quantityMilli' => $line['quantityMilli'],
                'netOre' => $line['netOre'],
                'vatOre' => $line['vatOre'],
                'grossOre' => $line['grossOre'],
                'vatRate' => $requestedVatRate,
                'momsfri' => ($requestedVatRate == 0) ? 'on' : '',
                'productId' => (int) $product['id'],
                'unitPrice' => $this->formatDecimal($unitPrice, 6),
            );

            if ($requestedVatRate > $headerVatRate) {
                $headerVatRate = $requestedVatRate;
            }
        }

        return array(
            'headerVatRate' => $headerVatRate,
            'lines' => $prepared,
        );
    }

    private function requireProductBySku($sku)
    {
        $sku = trim($sku);
        $skuEscaped = $this->escape($sku);
        $query = "SELECT id, varenr, varenr_alias, beskrivelse, gruppe, samlevare, m_antal, m_rabat FROM varer WHERE UPPER(varenr) = UPPER('$skuEscaped') OR UPPER(varenr_alias) = UPPER('$skuEscaped') ORDER BY id";
        $rows = $this->fetchAll($query);

        if (count($rows) === 0) {
            throw new ApiException('Unknown product SKU: ' . $sku, 422);
        }
        if (count($rows) > 1) {
            throw new ApiException('SKU ' . $sku . ' resolves to multiple products and must be made unique before import', 422);
        }

        return $rows[0];
    }

    private function assertProductCanBeImported($product, $sku)
    {
        if (trim((string) $product['samlevare']) === 'on') {
            throw new ApiException('SKU ' . $sku . ' is a samlevare and cannot be imported through this bridge', 422);
        }
        if (trim((string) $product['m_antal']) !== '' || trim((string) $product['m_rabat']) !== '') {
            throw new ApiException('SKU ' . $sku . ' has quantity discount configuration and cannot be imported safely through this bridge', 422);
        }
        if (!is_numeric($product['gruppe']) || (int) $product['gruppe'] <= 0) {
            throw new ApiException('SKU ' . $sku . ' is missing a valid varegruppe', 422);
        }
    }

    private function resolveProductVatRate($varegruppe, $regnaar, $sku)
    {
        $varegruppe = (int) $varegruppe;
        $row = $this->fetchRow("SELECT box4 FROM grupper WHERE art = 'VG' AND kodenr = '$varegruppe' AND fiscal_year = '$regnaar' LIMIT 1");
        if (!$row || trim((string) $row['box4']) === '') {
            throw new ApiException('SKU ' . $sku . ' is missing a sales account on its varegruppe', 422);
        }

        $salesAccount = $this->escape(trim((string) $row['box4']));
        $konto = $this->fetchRow("SELECT moms FROM kontoplan WHERE kontonr = '$salesAccount' AND regnskabsaar = '$regnaar' LIMIT 1");
        if (!$konto || trim((string) $konto['moms']) === '') {
            throw new ApiException('SKU ' . $sku . ' sales account is missing a moms code in kontoplan', 422);
        }

        $momsCode = (int) substr(trim((string) $konto['moms']), 1);
        if ($momsCode <= 0) {
            return 0;
        }

        $momsGroup = $this->fetchRow("SELECT box2 FROM grupper WHERE art = 'SM' AND kodenr = '$momsCode' AND fiscal_year = '$regnaar' LIMIT 1");
        if (!$momsGroup) {
            throw new ApiException('SKU ' . $sku . ' references an unknown momsgruppe', 422);
        }

        $vatRate = (float) $momsGroup['box2'];
        if ($vatRate !== 0.0 && $vatRate !== 25.0) {
            throw new ApiException('Only 0% and 25% VAT products are supported by this bridge', 422);
        }

        return $vatRate;
    }

    private function findOrCreateDebtor($customer, $debtorGroup, $username)
    {
        $firmName = ($customer['companyName'] !== '') ? $customer['companyName'] : $customer['contactName'];
        $company = $this->escape($firmName);
        $address1 = $this->escape($customer['address1']);
        $address2 = $this->escape($customer['address2']);
        $postalCode = $this->escape($customer['postalCode']);
        $city = $this->escape($customer['city']);
        $country = $this->escape($customer['country']);
        $cvr = $this->escape($customer['cvr']);

        $query = "SELECT id, kontonr, gruppe, sprog FROM adresser WHERE art = 'D' AND firmanavn = '$company' AND addr1 = '$address1' AND addr2 = '$address2' AND postnr = '$postalCode' AND bynavn = '$city' AND land = '$country' AND cvrnr = '$cvr' ORDER BY id";
        $matches = $this->fetchAll($query);

        if (count($matches) > 1) {
            throw new ApiException('Multiple existing debtors match the provided customer identity and address. Merge them before using this endpoint.', 409);
        }

        if (count($matches) === 1) {
            if ((int) $matches[0]['gruppe'] !== (int) $debtorGroup['code']) {
                throw new ApiException('Existing debtor group does not match customer.groupCode', 409);
            }

            return array(
                'id' => (int) $matches[0]['id'],
                'kontonr' => trim((string) $matches[0]['kontonr']),
                'sprog' => (int) (($matches[0]['sprog'] !== '' && $matches[0]['sprog'] !== null) ? $matches[0]['sprog'] : 1),
                'firmName' => $firmName,
            );
        }

        $kontonr = get_next_number('adresser', 'D');
        $kontonr = (int) $kontonr;
        if ($kontonr <= 0) {
            throw new ApiException('Could not allocate a debtor account number', 500);
        }

        $contact = $this->escape($customer['contactName']);
        $email = $this->escape($customer['email']);
        $phone = $this->escape($customer['phone']);
        $query = "INSERT INTO adresser (kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, cvrnr, email, tlf, gruppe, art, betalingsbet, betalingsdage, kontakt, lev_firmanavn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_land, lev_kontakt, lev_tlf, lev_email, lukket, oprettet_af) VALUES ('$kontonr', '$company', '$address1', '$address2', '$postalCode', '$city', '$country', '$cvr', '$email', '$phone', '{$debtorGroup['code']}', 'D', 'netto', '8', '$contact', '$company', '$address1', '$address2', '$postalCode', '$city', '$country', '$contact', '$phone', '$email', '', '" . $this->escape($username) . "')";
        $this->execute($query);

        $created = $this->fetchRow("SELECT id, sprog FROM adresser WHERE art = 'D' AND kontonr = '$kontonr' ORDER BY id DESC LIMIT 1");
        if (!$created) {
            throw new ApiException('Debtor creation failed', 500);
        }

        return array(
            'id' => (int) $created['id'],
            'kontonr' => (string) $kontonr,
            'sprog' => (int) (($created['sprog'] !== '' && $created['sprog'] !== null) ? $created['sprog'] : 1),
            'firmName' => $firmName,
        );
    }
    private function createDraftOrder($payload, $debtor, $headerVatRate, $username)
    {
        $ordrenr = get_next_order_number('DO', true);
        $invoiceDate = $this->escape($payload['invoiceDate']);
        $firmName = $this->escape($debtor['firmName']);
        $customer = $payload['customer'];
        $contact = $this->escape($customer['contactName']);
        $email = $this->escape($customer['email']);
        $phone = $this->escape($customer['phone']);
        $address1 = $this->escape($customer['address1']);
        $address2 = $this->escape($customer['address2']);
        $postalCode = $this->escape($customer['postalCode']);
        $city = $this->escape($customer['city']);
        $country = $this->escape($customer['country']);
        $cvr = $this->escape($customer['cvr']);
        $externalInvoiceId = $this->escape($payload['externalInvoiceId']);
        $payloadHash = $this->escape($payload['payloadHash']);
        $username = $this->escape($username);
        $tidspkt = $this->escape(date('H:i'));
        $currency = $this->escape($payload['currency']);
        $netAmount = $this->formatOreAsDecimal($payload['totals']['netOre']);
        $vatAmount = $this->formatOreAsDecimal($payload['totals']['vatOre']);

        $query = "INSERT INTO ordrer (ordrenr, konto_id, kontonr, firmanavn, addr1, addr2, postnr, bynavn, land, kontakt, email, phone, art, momssats, betalingsbet, betalingsdage, betalings_id, status, ordredate, levdate, fakturadate, valuta, valutakurs, ref, hvem, kundeordnr, cvrnr, sum, moms, lev_navn, lev_addr1, lev_addr2, lev_postnr, lev_bynavn, lev_land, lev_kontakt, betalt, shop_status, notes, sprog, tidspkt, felt_1, felt_2, felt_3, felt_4, felt_5) VALUES ('$ordrenr', '{$debtor['id']}', '{$this->escape($debtor['kontonr'])}', '$firmName', '$address1', '$address2', '$postalCode', '$city', '$country', '$contact', '$email', '$phone', 'DO', '$headerVatRate', '" . self::CARD_PAYMENT_TERM . "', '0', '$payloadHash', '0', '$invoiceDate', '$invoiceDate', '$invoiceDate', '$currency', '100', '$username', '$username', '$externalInvoiceId', '$cvr', '$netAmount', '$vatAmount', '$firmName', '$address1', '$address2', '$postalCode', '$city', '$country', '$contact', 'on', '" . self::IMPORT_MARKER . "', 'Stripe paid invoice bridge import', '{$debtor['sprog']}', '$tidspkt', '', '', '', '', '')";
        $this->execute($query);

        $row = $this->fetchRow("SELECT id FROM ordrer WHERE ordrenr = '$ordrenr' AND art = 'DO' AND shop_status = '" . self::IMPORT_MARKER . "' ORDER BY id DESC LIMIT 1");
        if (!$row) {
            throw new ApiException('Draft order creation failed', 500);
        }

        return (int) $row['id'];
    }

    private function insertOrderLines($orderId, $lines)
    {
        for ($index = 0; $index < count($lines); $index++) {
            $line = $lines[$index];
            $result = opret_ordrelinje(
                $orderId,
                $line['productId'],
                $line['sku'],
                $line['quantity'],
                $line['description'],
                $line['unitPrice'],
                0,
                100,
                'DO',
                $line['momsfri'],
                $line['position'],
                0,
                0,
                '',
                '',
                0,
                0,
                0,
                '',
                0,
                ''
            );

            if (is_string($result) && trim($result) !== '') {
                throw new ApiException('Order line import failed for SKU ' . $line['sku'] . ': ' . $result, 422);
            }
        }
    }

    private function reconcileDraftTotals($orderId, $payload, $lines)
    {
        $momsupdatResult = momsupdat($orderId);
        if ($momsupdatResult !== 'OK') {
            throw new ApiException('Failed to reconcile draft VAT totals: ' . $momsupdatResult, 422);
        }

        $orderRow = $this->fetchRequiredOrder($orderId);
        $lineRows = $this->fetchOrderLines($orderId);
        if (count($lineRows) !== count($lines)) {
            throw new ApiException('Legacy line creation changed the line set and cannot be reconciled safely', 422);
        }

        $summary = $this->summarizeLines($lineRows);
        $this->assertSummaryMatchesPayload($summary, $payload, true);
        $this->assertOrderTotalsMatchPayload($orderRow, $payload);
    }

    private function deliverAndPost($orderId)
    {
        $deliveryResult = levering($orderId, 'on', '', true);
        if ($deliveryResult !== 'OK') {
            throw new ApiException('Delivery failed: ' . $deliveryResult, 422);
        }

        $postingResult = bogfor($orderId, 'on', false, true);
        if ($postingResult !== 'OK') {
            throw new ApiException('Posting failed: ' . $postingResult, 422);
        }

        $orderRow = $this->fetchRequiredOrder($orderId);
        if ((int) $orderRow['status'] === 3) {
            $finalPostingResult = bogfor_nu($orderId, 'webservice');
            if ($finalPostingResult !== 'OK') {
                throw new ApiException('Final posting failed: ' . $finalPostingResult, 422);
            }
        }
    }

    private function buildPostedResponse($orderId, $payload, $cardClearingAccount = null, $idempotent = false)
    {
        $orderRow = $this->fetchRequiredOrder($orderId);
        $lineRows = $this->fetchOrderLines($orderId);
        $summary = $this->summarizeLines($lineRows);

        $this->assertSummaryMatchesPayload($summary, $payload, false);
        $this->assertOrderTotalsMatchPayload($orderRow, $payload);

        if ((int) $orderRow['status'] !== 4) {
            throw new ApiException('Imported invoice did not finish as status 4', 500);
        }
        if (trim((string) $orderRow['betalt']) !== 'on') {
            throw new ApiException('Imported invoice is no longer marked paid', 500);
        }
        if (trim((string) $orderRow['betalingsbet']) !== self::CARD_PAYMENT_TERM) {
            throw new ApiException('Imported invoice no longer uses credit-card settlement', 500);
        }
        if (strtoupper(trim((string) $orderRow['valuta'])) !== 'DKK') {
            throw new ApiException('Imported invoice currency drifted away from DKK', 500);
        }

        $openPostRow = $this->fetchRow("SELECT id FROM openpost WHERE refnr = '$orderId' LIMIT 1");
        if ($openPostRow) {
            throw new ApiException('Imported invoice created an open post instead of settling through card clearing', 500);
        }

        $posRow = $this->fetchRow("SELECT id FROM pos_betalinger WHERE ordre_id = '$orderId' LIMIT 1");
        if ($posRow) {
            throw new ApiException('Imported invoice was misclassified as POS payment', 500);
        }

        if ($cardClearingAccount !== null) {
            $cardRow = $this->fetchRow("SELECT id FROM transaktioner WHERE ordre_id = '$orderId' AND kontonr = '$cardClearingAccount' LIMIT 1");
            if (!$cardRow) {
                throw new ApiException('Imported invoice did not create a card-clearing transaction', 500);
            }
        }

        return array(
            'idempotent' => $idempotent,
            'orderId' => (int) $orderRow['id'],
            'invoiceId' => (int) $orderRow['id'],
            'invoiceNumber' => (int) $orderRow['fakturanr'],
            'externalInvoiceId' => $payload['externalInvoiceId'],
            'payloadHash' => $payload['payloadHash'],
            'status' => (int) $orderRow['status'],
            'paid' => true,
            'settled' => true,
            'paidState' => 'settled',
            'currency' => 'DKK',
            'netOre' => $summary['netOre'],
            'vatOre' => $summary['vatOre'],
            'grossOre' => $summary['grossOre'],
            'lines' => $summary['lines'],
        );
    }

    private function fetchRequiredOrder($orderId)
    {
        $marker = $this->escape(self::IMPORT_MARKER);
        $row = $this->fetchRow("SELECT id, fakturanr, status, valuta, sum, moms, betalt, betalingsbet, kundeordnr, betalings_id, shop_status FROM ordrer WHERE id = '$orderId' AND art = 'DO' AND shop_status = '$marker' LIMIT 1");
        if (!$row) {
            throw new ApiException('Imported order could not be read back', 500);
        }

        return $row;
    }

    private function fetchOrderLines($orderId)
    {
        return $this->fetchAll("SELECT varenr, beskrivelse, antal, pris, rabat, rabatart, procent, momssats, momsfri, posnr FROM ordrelinjer WHERE ordre_id = '$orderId' AND posnr > 0 ORDER BY posnr, id");
    }

    private function summarizeLines($lineRows)
    {
        $summary = array(
            'netOre' => 0,
            'vatOre' => 0,
            'grossOre' => 0,
            'lines' => array(),
        );

        for ($index = 0; $index < count($lineRows); $index++) {
            $row = $lineRows[$index];
            $netAmount = (float) $row['pris'] * (float) $row['antal'];
            if ((float) $row['rabat'] != 0.0) {
                if ((string) $row['rabatart'] === 'amount') {
                    $netAmount = ((float) $row['pris'] - (float) $row['rabat']) * (float) $row['antal'];
                } else {
                    $netAmount = ((float) $row['pris'] - ((float) $row['pris'] * (float) $row['rabat'] / 100)) * (float) $row['antal'];
                }
            }
            if ($row['procent'] !== '' && $row['procent'] !== null) {
                $netAmount *= ((float) $row['procent'] / 100);
            }

            $netOre = $this->decimalToOre($netAmount);
            $vatOre = (trim((string) $row['momsfri']) === 'on' || (float) $row['momssats'] == 0.0)
                ? 0
                : $this->decimalToOre(round($netAmount * ((float) $row['momssats'] / 100), 2));
            $grossOre = $netOre + $vatOre;

            $summary['netOre'] += $netOre;
            $summary['vatOre'] += $vatOre;
            $summary['grossOre'] += $grossOre;
            $summary['lines'][] = array(
                'sku' => trim((string) $row['varenr']),
                'description' => trim((string) $row['beskrivelse']),
                'quantity' => $this->formatQuantity($row['antal']),
                'netOre' => $netOre,
                'vatOre' => $vatOre,
                'grossOre' => $grossOre,
            );
        }

        return $summary;
    }

    private function assertSummaryMatchesPayload($summary, $payload, $enforcePositionEquality)
    {
        if ($summary['netOre'] !== $payload['totals']['netOre'] || $summary['vatOre'] !== $payload['totals']['vatOre'] || $summary['grossOre'] !== $payload['totals']['grossOre']) {
            throw new ApiException('Read-back totals do not match the request payload', 500);
        }

        if (count($summary['lines']) !== count($payload['lines'])) {
            throw new ApiException('Read-back line count does not match the request payload', 500);
        }

        for ($index = 0; $index < count($payload['lines']); $index++) {
            $expected = $payload['lines'][$index];
            $actual = $summary['lines'][$index];
            if ($actual['netOre'] !== $expected['netOre'] || $actual['vatOre'] !== $expected['vatOre'] || $actual['grossOre'] !== $expected['grossOre']) {
                throw new ApiException('Read-back line totals do not match the request payload', 500);
            }
            if ($enforcePositionEquality && $actual['sku'] !== $expected['sku']) {
                throw new ApiException('Read-back line SKU order does not match the request payload', 500);
            }
        }
    }

    private function assertOrderTotalsMatchPayload($orderRow, $payload)
    {
        if ($this->decimalToOre($orderRow['sum']) !== $payload['totals']['netOre']) {
            throw new ApiException('Read-back order net total does not match the request payload', 500);
        }
        if ($this->decimalToOre($orderRow['moms']) !== $payload['totals']['vatOre']) {
            throw new ApiException('Read-back order VAT total does not match the request payload', 500);
        }
    }
    private function deriveSupportedVatRate($netOre, $vatOre, $fieldName)
    {
        if ($vatOre === 0) {
            return 0;
        }

        if ($netOre <= 0) {
            throw new ApiException($fieldName . ' netOre must be positive when VAT is present', 422);
        }

        $expectedVat = (int) round($netOre * 0.25, 0, PHP_ROUND_HALF_UP);
        if ($vatOre !== $expectedVat) {
            throw new ApiException($fieldName . ' VAT must match a supported 25% VAT calculation in whole �re', 422);
        }

        return 25;
    }

    private function requireArray($data, $field)
    {
        if (!isset($data[$field]) || !is_array($data[$field])) {
            throw new ApiException($field . ' must be an object', 422);
        }

        return $data[$field];
    }

    private function requireString($data, $field)
    {
        if (!isset($data[$field])) {
            throw new ApiException($field . ' is required', 422);
        }

        $value = trim((string) $data[$field]);
        if ($value === '') {
            throw new ApiException($field . ' must not be empty', 422);
        }

        return $value;
    }

    private function optionalString($data, $field)
    {
        if (!isset($data[$field]) || $data[$field] === null) {
            return '';
        }

        return trim((string) $data[$field]);
    }

    private function requireIntegerField($data, $field, $mustBePositive)
    {
        if (!array_key_exists($field, $data)) {
            throw new ApiException($field . ' is required', 422);
        }

        $value = $data[$field];
        if (is_int($value)) {
            $integer = $value;
        } elseif (is_string($value) && preg_match('/^-?[0-9]+$/', trim($value))) {
            $integer = (int) trim($value);
        } else {
            throw new ApiException($field . ' must be an integer �re amount', 422);
        }

        if ($mustBePositive && $integer <= 0) {
            throw new ApiException($field . ' must be greater than zero', 422);
        }
        if (!$mustBePositive && $integer < 0) {
            throw new ApiException($field . ' must not be negative', 422);
        }

        return $integer;
    }

    private function requireDate($data, $field)
    {
        $value = $this->requireString($data, $field);
        $date = DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new ApiException($field . ' must use YYYY-MM-DD format', 422);
        }

        return $value;
    }

    private function fetchRow($query)
    {
        $result = db_select($this->prepareQuery($query), __FILE__ . ' linje ' . __LINE__);
        if (!$result) {
            return false;
        }

        return db_fetch_array($result);
    }

    private function fetchAll($query)
    {
        $rows = array();
        $result = db_select($this->prepareQuery($query), __FILE__ . ' linje ' . __LINE__);
        if (!$result) {
            return $rows;
        }

        while ($row = db_fetch_array($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    private function execute($query)
    {
        return db_modify($this->prepareQuery($query), __FILE__ . ' linje ' . __LINE__);
    }

    private function prepareQuery($query)
    {
        if (function_exists('chk4utf8')) {
            return chk4utf8($query);
        }

        return $query;
    }

    private function escape($value)
    {
        return db_escape_string((string) $value);
    }

    private function formatOreAsDecimal($ore)
    {
        return number_format(((int) $ore) / 100, 2, '.', '');
    }

    private function formatDecimal($value, $decimals)
    {
        $formatted = number_format((float) $value, $decimals, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        if ($formatted === '' || $formatted === '-0') {
            $formatted = '0';
        }

        return $formatted;
    }

    private function decimalToOre($amount)
    {
        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    private function formatQuantity($quantity)
    {
        $formatted = rtrim(rtrim(number_format((float) $quantity, 3, '.', ''), '0'), '.');
        return ($formatted === '') ? '0' : $formatted;
    }
}
