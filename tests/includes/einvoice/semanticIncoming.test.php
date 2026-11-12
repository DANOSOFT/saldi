<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/includes/einvoice/semanticIncoming.test.php --- ver 5.1.1 --- 2026-11-12 ---
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY. See
// GNU General Public License for more details.
//
// Copyright (c) 2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261112 CL/MMR SD-815 Created. Incoming e-documents through Semantic with a fake transport and an in-memory store, synthetic data only.
//                 An invoice or credit note lands in the pool once, an ApplicationResponse only reaches the one send-log row it answers, and the tenant guards and Semantic's errors change nothing.

declare(strict_types=1);

namespace SaldiTests\Einvoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../includes/einvoice/semanticIncoming.php';

/**
 * SemanticIncomingStore without a database, pool folder or mail server.
 */
final class MemoryIncomingStore extends \SemanticIncomingStore
{
    public bool $isReady = true;
    public bool $provider = true;
    /** @var array<string, string> */
    public array $settingsData = array();
    /** @var array<string, array<string, mixed>> einvoice_incoming by semantic_incoming_id */
    public array $rows = array();
    /** @var array<string, string> pool filename by content hash */
    public array $pool = array();
    /** @var list<array{xml: string, filename: string, db: string}> */
    public array $poolCalls = array();
    public bool $poolFails = false;
    /** @var list<array{to: string, subject: string, html: string}> */
    public array $mails = array();
    public string $email = 'bogholderi@example.invalid';
    /** @var array<int, array<string, mixed>> send log by id, with 'xml' and the receiver_* fields */
    public array $sendlog = array();
    /** @var list<array{int, array<string, string>}> */
    public array $receiverWrites = array();
    /** @var list<string> */
    public array $logs = array();
    /** @var string|null An id another run holds. */
    public ?string $foreignClaim = null;

    public function ready()
    {
        return $this->isReady;
    }

    public function settings()
    {
        return $this->settingsData;
    }

    public function providerIsSemantic()
    {
        return $this->provider;
    }

    public function get($incomingId)
    {
        if (!isset($this->rows[$incomingId])) {
            return null;
        }
        $row = $this->rows[$incomingId];
        return array('status' => $row['status'], 'attempts' => $row['attempts'], 'pool_filename' => (string)($row['pool_filename'] ?? ''), 'sendlog_id' => (int)($row['sendlog_id'] ?? 0));
    }

    public function claim($incomingId, array $fields)
    {
        if ($this->foreignClaim === $incomingId) {
            return null;
        }
        $token = bin2hex(random_bytes(8));
        if (!isset($this->rows[$incomingId])) {
            $this->rows[$incomingId] = $fields + array('status' => 'processing', 'attempts' => 1, 'token' => $token);
            return $token;
        }
        $row = &$this->rows[$incomingId];
        if ($row['status'] === 'failed' && $row['attempts'] < SEMANTIC_INCOMING_MAX_ATTEMPTS) {
            $row['status'] = 'processing';
            $row['attempts']++;
            $row['token'] = $token;
            return $token;
        }
        return null;
    }

    public function finish($incomingId, $token, array $fields)
    {
        if (isset($this->rows[$incomingId]) && $this->rows[$incomingId]['token'] === $token) {
            $this->rows[$incomingId] = array_merge($this->rows[$incomingId], $fields);
        }
    }

    public function poolIngest($xml, $filename, $db)
    {
        $this->poolCalls[] = array('xml' => $xml, 'filename' => $filename, 'db' => $db);
        if ($this->poolFails) {
            return array('success' => false, 'message' => 'Bilaget kunne ikke uploades. Prøv igen.');
        }
        $hash = hash('sha256', $xml);
        if (isset($this->pool[$hash])) {
            return array('success' => false, 'duplicate' => true, 'existing' => $this->pool[$hash], 'message' => 'findes');
        }
        $this->pool[$hash] = $filename;
        return array('success' => true, 'filename' => $filename, 'message' => 'File uploaded successfully');
    }

    public function companyEmail()
    {
        return $this->email;
    }

    public function sendMail($to, $subject, $html)
    {
        $this->mails[] = array('to' => $to, 'subject' => $subject, 'html' => $html);
        return true;
    }

    public function sendlogByFakturanr($fakturanr)
    {
        $rows = array();
        foreach ($this->sendlog as $row) {
            if ($row['provider'] === 'semantic' && $row['fakturanr'] === $fakturanr && !in_array($row['status'], array('ValidationFailed', 'SendFailed'), true)) {
                $rows[] = $row;
            }
        }
        usort($rows, function ($a, $b) {
            return $b['id'] <=> $a['id'];
        });
        return $rows;
    }

    public function sendlogXml($id)
    {
        return $this->sendlog[$id]['xml'] ?? null;
    }

    public function sendlogReceiver($id)
    {
        if (!isset($this->sendlog[$id])) {
            return null;
        }
        $row = $this->sendlog[$id];
        return array('status' => $row['receiver_status'] ?? '', 'text' => $row['receiver_status_text'] ?? '', 'date' => $row['receiver_status_date'] ?? '', 'responseId' => $row['receiver_response_id'] ?? '');
    }

    public function sendlogSetReceiver($id, array $fields)
    {
        $this->receiverWrites[] = array((int)$id, $fields);
        $this->sendlog[$id] = array_merge($this->sendlog[$id], $fields);
    }

    public function log($message)
    {
        $this->logs[] = (string)$message;
    }
}

final class semanticIncoming extends TestCase
{
    private const BASE = 'https://semantic.example.invalid';
    private const DB = 'test_7';
    private const OUR_CVR = '11111111';
    private const CUSTOMER_CVR = '22222222';
    private const VENDOR_CVR = '33333333';

    private string $companyId = '';
    private string $endpointId = '';
    private MemoryIncomingStore $store;
    /** @var list<array{method: string, url: string, body: ?string}> */
    private array $calls = array();
    /** @var list<array{status: int, body: string, error: ?string}> */
    private array $answers = array();

    protected function setUp(): void
    {
        $this->companyId = self::uuid();
        $this->endpointId = self::uuid();
        $this->calls = array();
        $this->answers = array();
        $this->store = new MemoryIncomingStore();
        $this->store->settingsData = array_fill_keys(semantic_settings_names(), '');
        $this->store->settingsData['companyId'] = $this->companyId;
        $this->store->settingsData['endpointId'] = $this->endpointId;
        $this->store->settingsData['endpointIdentifier'] = self::OUR_CVR;
        $this->store->settingsData['dbName'] = self::DB;
    }

    private static function uuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-8' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
    }

    private function client(): \SemanticClient
    {
        $transport = function (string $method, string $url, array $headers, ?string $body): array {
            $this->calls[] = array('method' => $method, 'url' => $url, 'body' => $body);
            return array_shift($this->answers) ?? array('status' => 500, 'body' => 'no answer queued', 'error' => null);
        };
        return new \SemanticClient(self::BASE, self::uuid(), self::uuid(), $transport);
    }

    private function answer(int $status, string $body = '', ?string $error = null): void
    {
        $this->answers[] = array('status' => $status, 'body' => $body, 'error' => $error);
    }

    /**
     * An IncomingDocument as Semantic lists it.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function semanticDoc(int $id, string $ublId, array $overrides = array()): array
    {
        return $overrides + array(
            'incomingDocumentId' => $id,
            'receiverEndpointId' => $this->endpointId,
            'namespace' => 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2',
            'documentNumber' => 'INV-' . $id,
            'documentType' => 110,
            'ublDocumentId' => $ublId,
            'senderEndpointIdentifier' => self::VENDOR_CVR,
            'senderEndpointType' => '0184',
            'senderCompanyName' => 'Testleverandør ApS',
            'receivedDateTime' => '2026-11-12T08:15:00Z',
            'documentStatus' => 0,
            'previousOutgoingDocumentId' => null,
        );
    }

    private static function ns(string $root): string
    {
        return 'xmlns="urn:oasis:names:specification:ubl:schema:xsd:' . $root . '-2" '
            . 'xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" '
            . 'xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"';
    }

    /** An invoice or credit note: $supplier sends to $customer. */
    private static function ublDocument(string $root, string $id, string $issueDate, string $supplier, string $customer): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<' . $root . ' ' . self::ns($root) . '>'
            . '<cbc:CustomizationID>urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0</cbc:CustomizationID>'
            . '<cbc:ID>' . $id . '</cbc:ID><cbc:IssueDate>' . $issueDate . '</cbc:IssueDate><cbc:DocumentCurrencyCode>DKK</cbc:DocumentCurrencyCode>'
            . '<cac:AccountingSupplierParty><cac:Party><cbc:EndpointID schemeID="0184">' . $supplier . '</cbc:EndpointID>'
            . '<cac:PartyName><cbc:Name>Leverandør ' . $supplier . '</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>'
            . '<cac:AccountingCustomerParty><cac:Party><cbc:EndpointID schemeID="0184">' . $customer . '</cbc:EndpointID>'
            . '<cac:PartyName><cbc:Name>Kunde ' . $customer . '</cbc:Name></cac:PartyName></cac:Party></cac:AccountingCustomerParty>'
            . '<cac:LegalMonetaryTotal><cbc:PayableAmount currencyID="DKK">125.00</cbc:PayableAmount></cac:LegalMonetaryTotal>'
            . '</' . $root . '>';
    }

    /**
     * A Peppol Invoice Response.
     *
     * @param array<string, string> $o code, refId, refType, refDate, sender, receiver, reason, date, time, customization
     */
    private static function responseXml(array $o): string
    {
        $o += array('code' => 'RE', 'refId' => '1001', 'refType' => '380', 'refDate' => '', 'sender' => self::CUSTOMER_CVR, 'receiver' => self::OUR_CVR,
            'reason' => 'Forkert ordrenummer', 'date' => '2026-11-12', 'time' => '09:30:00',
            'customization' => 'urn:fdc:peppol.eu:poacc:trns:invoice_response:3');
        $reference = ($o['refId'] !== '' ? '<cbc:ID>' . $o['refId'] . '</cbc:ID>' : '')
            . ($o['refDate'] !== '' ? '<cbc:IssueDate>' . $o['refDate'] . '</cbc:IssueDate>' : '')
            . ($o['refType'] !== '' ? '<cbc:DocumentTypeCode>' . $o['refType'] . '</cbc:DocumentTypeCode>' : '');
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<ApplicationResponse ' . self::ns('ApplicationResponse') . '>'
            . '<cbc:CustomizationID>' . $o['customization'] . '</cbc:CustomizationID>'
            . '<cbc:ProfileID>urn:fdc:peppol.eu:poacc:bis:invoice_response:3</cbc:ProfileID>'
            . '<cbc:ID>IR-' . $o['refId'] . '</cbc:ID><cbc:IssueDate>' . $o['date'] . '</cbc:IssueDate><cbc:IssueTime>' . $o['time'] . '</cbc:IssueTime>'
            . ($o['sender'] !== '' ? '<cac:SenderParty><cbc:EndpointID schemeID="0184">' . $o['sender'] . '</cbc:EndpointID></cac:SenderParty>' : '')
            . '<cac:ReceiverParty><cbc:EndpointID schemeID="0184">' . $o['receiver'] . '</cbc:EndpointID></cac:ReceiverParty>'
            . '<cac:DocumentResponse><cac:Response><cbc:ResponseCode listID="UNCL4343OpSubset">' . $o['code'] . '</cbc:ResponseCode>'
            . '<cbc:EffectiveDate>' . $o['date'] . '</cbc:EffectiveDate>'
            . ($o['reason'] !== '' ? '<cac:Status><cbc:StatusReasonCode listID="OPStatusReason">REF</cbc:StatusReasonCode><cbc:StatusReason>' . $o['reason'] . '</cbc:StatusReason></cac:Status>' : '')
            . '</cac:Response>'
            . ($reference !== '' ? '<cac:DocumentReference>' . $reference . '</cac:DocumentReference>' : '')
            . '</cac:DocumentResponse></ApplicationResponse>';
    }

    /**
     * Send-log rows of documents we sent: invoice 1001 (order 10), credit note 1001 (order 11, same number in its
     * own series) and invoice 1002 to another customer.
     */
    private function seedSendlog(): void
    {
        $rows = array(
            1 => array('ordre_id' => 10, 'art' => 'DO', 'fakturanr' => '1001', 'semantic_doc_id' => '9001', 'status' => 'Sent',
                'xml' => self::ublDocument('Invoice', '1001', '2026-11-01', self::OUR_CVR, self::CUSTOMER_CVR)),
            2 => array('ordre_id' => 11, 'art' => 'DK', 'fakturanr' => '1001', 'semantic_doc_id' => '9002', 'status' => 'Sent',
                'xml' => self::ublDocument('CreditNote', '1001', '2026-11-05', self::OUR_CVR, self::CUSTOMER_CVR)),
            3 => array('ordre_id' => 12, 'art' => 'DO', 'fakturanr' => '1002', 'semantic_doc_id' => '9003', 'status' => 'Sent',
                'xml' => self::ublDocument('Invoice', '1002', '2026-11-06', self::OUR_CVR, '44444444')),
        );
        foreach ($rows as $id => $row) {
            $this->store->sendlog[$id] = $row + array('id' => $id, 'provider' => 'semantic');
        }
    }

    /** @return array{ok: bool, outcome: string, message: string, incomingId: string|null} */
    private function process(array $ref): array
    {
        return semantic_incoming_process($this->client(), $ref, array('db' => self::DB, 'store' => $this->store, 'now' => '2026-11-12 09:00:00'));
    }

    /** Queue Semantic's answers for one incoming document: the search, then the XML. */
    private function queueDocument(array $doc, string $xml): void
    {
        $this->answer(200, (string)json_encode(array($doc)));
        $this->answer(200, (string)json_encode($xml));
    }

    public function testInvoiceLandsInThePoolOnceAndASecondDeliveryIsANoOp(): void
    {
        $ublId = self::uuid();
        $xml = self::ublDocument('Invoice', 'INV-501', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR);
        $this->queueDocument($this->semanticDoc(501, $ublId), $xml);

        $result = $this->process(array('incomingId' => '501'));
        $this->assertSame('pooled', $result['outcome'], $result['message']);
        $this->assertTrue($result['ok']);

        $this->assertCount(2, $this->calls);
        $this->assertSame('GET', $this->calls[0]['method']);
        $this->assertSame(self::BASE . '/api/IncomingDocument', $this->calls[0]['url']);
        $filter = json_decode((string)$this->calls[0]['body'], true);
        $this->assertSame(501, $filter['id']);
        $this->assertSame($this->companyId, $filter['companyId']);
        $this->assertSame(self::BASE . '/api/UblDocument/' . $ublId, $this->calls[1]['url']);

        $this->assertCount(1, $this->store->poolCalls);
        $this->assertSame($xml, $this->store->poolCalls[0]['xml']);
        $this->assertSame('efaktura_INV-501.xml', $this->store->poolCalls[0]['filename']);
        $this->assertSame(self::DB, $this->store->poolCalls[0]['db']);
        $this->assertSame('pooled', $this->store->rows['501']['status']);
        $this->assertSame('Invoice', $this->store->rows['501']['document_type']);
        $this->assertSame('efaktura_INV-501.xml', $this->store->rows['501']['pool_filename']);
        $this->assertSame('0184:' . self::VENDOR_CVR, $this->store->rows['501']['sender_endpoint']);
        $this->assertSame(self::DB, $this->store->rows['501']['db_name']);

        $this->assertCount(1, $this->store->mails);
        $this->assertSame('bogholderi@example.invalid', $this->store->mails[0]['to']);
        $this->assertSame('Ny faktura modtaget', $this->store->mails[0]['subject']);
        $this->assertStringContainsString('Du har modtaget en ny elektronisk faktura', $this->store->mails[0]['html']);
        $this->assertStringContainsString('2026-11-12 09:00:00', $this->store->mails[0]['html']);
        $this->assertSame(array(), $this->store->receiverWrites, 'an invoice never touches the send log');

        // The same notification again, and the poll listing the same document: nothing is read or written again
        $again = $this->process(array('incomingId' => '501'));
        $this->assertSame('already', $again['outcome']);
        $this->assertTrue($again['ok']);
        $this->assertCount(2, $this->calls, 'a handled document is not fetched again');
        $viaPoll = $this->process(array('document' => $this->semanticDoc(501, $ublId)));
        $this->assertSame('already', $viaPoll['outcome']);
        $this->assertCount(1, $this->store->poolCalls);
        $this->assertCount(1, $this->store->mails);
    }

    public function testCreditNoteLandsInThePoolToo(): void
    {
        $ublId = self::uuid();
        $xml = self::ublDocument('CreditNote', 'CN-77', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR);
        $this->queueDocument($this->semanticDoc(502, $ublId, array('documentNumber' => 'CN-77', 'documentType' => 120)), $xml);

        $result = $this->process(array('incomingId' => '502'));
        $this->assertSame('pooled', $result['outcome'], $result['message']);
        $this->assertSame('ekreditnota_CN-77.xml', $this->store->poolCalls[0]['filename']);
        $this->assertSame('CreditNote', $this->store->rows['502']['document_type']);
        $this->assertCount(1, $this->store->mails);
    }

    public function testTheSameContentUnderANewIdIsAPoolDuplicateWithoutAMail(): void
    {
        $xml = self::ublDocument('Invoice', 'INV-9', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR);
        $this->queueDocument($this->semanticDoc(503, self::uuid()), $xml);
        $this->assertSame('pooled', $this->process(array('incomingId' => '503'))['outcome']);
        $this->queueDocument($this->semanticDoc(504, self::uuid()), $xml);
        $second = $this->process(array('incomingId' => '504'));
        $this->assertSame('duplicate', $second['outcome']);
        $this->assertSame('duplicate', $this->store->rows['504']['status']);
        $this->assertSame('efaktura_INV-503.xml', $this->store->rows['504']['pool_filename'], 'the copy already in the pool');
        $this->assertCount(1, $this->store->mails, 'only the first copy is announced');
    }

    public function testRejectionWithReasonIsStoredOnTheInvoiceRowOnly(): void
    {
        $this->seedSendlog();
        $xml = self::responseXml(array('code' => 'RE', 'refId' => '1001', 'refType' => '380', 'reason' => 'Forkert ordrenummer'));
        $this->queueDocument($this->semanticDoc(601, self::uuid(), array('documentType' => 130, 'senderEndpointIdentifier' => self::CUSTOMER_CVR)), $xml);

        $result = $this->process(array('incomingId' => '601'));
        $this->assertSame('response_applied', $result['outcome'], $result['message']);
        $this->assertSame(array(), $this->store->poolCalls, 'an ApplicationResponse never goes to the pool');
        $this->assertSame(array(), $this->store->mails);
        $this->assertCount(1, $this->store->receiverWrites);
        $this->assertSame(1, $this->store->receiverWrites[0][0], 'the invoice, not the credit note with the same number');
        $this->assertSame(array(
            'receiver_status' => 'RE',
            'receiver_status_text' => 'Forkert ordrenummer',
            'receiver_status_date' => '2026-11-12 09:30:00',
            'receiver_response_id' => '601',
        ), $this->store->receiverWrites[0][1]);
        $this->assertSame('response_applied', $this->store->rows['601']['status']);
        $this->assertSame(1, $this->store->rows['601']['sendlog_id']);
        $this->assertSame('ApplicationResponse', $this->store->rows['601']['document_type']);
        $this->assertStringContainsString('Forkert ordrenummer', (string)einvoice_sendlog_unpack_xml($this->store->rows['601']['xml']), 'the answer is archived');
        $this->assertSame('Afvist af modtager: Forkert ordrenummer', semantic_incoming_receiver_display($this->store->sendlogReceiver(1)));
        $this->assertSame('', $this->store->sendlog[2]['receiver_status'] ?? '');

        // Delivered again: no second write
        $this->assertSame('already', $this->process(array('incomingId' => '601'))['outcome']);
        $this->assertCount(1, $this->store->receiverWrites);
    }

    public function testCreditNoteResponseAndSemanticsLinkFindTheirRow(): void
    {
        $this->seedSendlog();
        $xml = self::responseXml(array('code' => 'AP', 'refId' => '1001', 'refType' => '381', 'reason' => ''));
        $this->queueDocument($this->semanticDoc(602, self::uuid()), $xml);
        $this->assertSame('response_applied', $this->process(array('incomingId' => '602'))['outcome']);
        $this->assertSame(2, $this->store->receiverWrites[0][0]);
        $this->assertSame('', $this->store->receiverWrites[0][1]['receiver_status_text']);
        $this->assertSame('Godkendt af modtager', semantic_incoming_receiver_display($this->store->sendlogReceiver(2)));

        // Without a type code the number fits both series; Semantic's link to our outgoing document decides
        $linked = self::responseXml(array('code' => 'PD', 'refId' => '1001', 'refType' => '', 'reason' => '', 'time' => '10:00:00'));
        $this->queueDocument($this->semanticDoc(603, self::uuid(), array('previousOutgoingDocumentId' => 9001)), $linked);
        $this->assertSame('response_applied', $this->process(array('incomingId' => '603'))['outcome']);
        $this->assertSame(1, $this->store->receiverWrites[1][0]);
    }

    /**
     * @return array<string, array{array<string, string>, array<string, mixed>, string}>
     */
    public static function weakMatches(): array
    {
        return array(
            'unknown invoice number' => array(array('refId' => '7777'), array(), 'no_candidates'),
            'no reference' => array(array('refId' => ''), array(), 'no_reference'),
            'no responder' => array(array('sender' => ''), array(), 'no_parties'),
            'another customer answers' => array(array('sender' => '55555555'), array(), 'no_match'),
            'answer meant for someone else' => array(array('receiver' => '66666666'), array(), 'no_match'),
            'invoice and credit note share the number' => array(array('refType' => ''), array(), 'ambiguous'),
            'issue date differs' => array(array('refDate' => '2026-10-31'), array(), 'no_match'),
            'linked to another document' => array(array(), array('previousOutgoingDocumentId' => 9003), 'link_mismatch'),
            'unknown response code' => array(array('code' => 'XX'), array(), 'no_code'),
            'invoice sent to another customer' => array(array('refId' => '1002'), array(), 'no_match'),
        );
    }

    /**
     * @param array<string, string> $xmlChanges
     * @param array<string, mixed>  $docChanges
     */
    #[DataProvider('weakMatches')]
    public function testAWeakOrMissingMatchChangesNoRowAndIsLogged(array $xmlChanges, array $docChanges, string $reason): void
    {
        $this->seedSendlog();
        $before = $this->store->sendlog;
        $this->queueDocument($this->semanticDoc(610, self::uuid(), $docChanges), self::responseXml($xmlChanges));

        $result = $this->process(array('incomingId' => '610'));
        $this->assertSame('response_unmatched', $result['outcome']);
        $this->assertTrue($result['ok'], 'recorded as handled, not tried again');
        $this->assertSame(array(), $this->store->receiverWrites);
        $this->assertSame($before, $this->store->sendlog);
        $this->assertSame(array(), $this->store->poolCalls);
        $this->assertSame('response_unmatched', $this->store->rows['610']['status']);
        $this->assertStringStartsWith($reason . ' (', $this->store->rows['610']['status_text']);
        $this->assertNotEmpty(array_filter($this->store->logs, function ($line) use ($reason) {
            return strpos($line, 'matched no sent document: ' . $reason) !== false;
        }), 'the miss is logged');
    }

    public function testAResendOfTheSameDocumentMatchesItsNewestSend(): void
    {
        $this->seedSendlog();
        $this->store->sendlog[4] = $this->store->sendlog[1];
        $this->store->sendlog[4]['id'] = 4;
        $this->store->sendlog[4]['semantic_doc_id'] = '9004';
        $this->queueDocument($this->semanticDoc(611, self::uuid()), self::responseXml(array('code' => 'AB', 'reason' => '')));
        $this->assertSame('response_applied', $this->process(array('incomingId' => '611'))['outcome']);
        $this->assertSame(4, $this->store->receiverWrites[0][0]);
    }

    public function testAnOlderAnswerDoesNotReplaceANewerOne(): void
    {
        $this->seedSendlog();
        $this->store->sendlog[1] += array('receiver_status' => 'RE', 'receiver_status_text' => 'Forkert pris', 'receiver_status_date' => '2026-11-12 09:30:00', 'receiver_response_id' => '620');
        $this->queueDocument($this->semanticDoc(621, self::uuid()), self::responseXml(array('code' => 'AB', 'time' => '08:00:00', 'reason' => '')));
        $this->assertSame('response_older', $this->process(array('incomingId' => '621'))['outcome']);
        $this->assertSame(array(), $this->store->receiverWrites);
        $this->assertSame('response_older', $this->store->rows['621']['status']);

        $this->assertTrue(semantic_incoming_receiver_should_apply(null, 'AB', '2026-11-12 08:00:00'));
        $this->assertTrue(semantic_incoming_receiver_should_apply(array('status' => 'AB', 'date' => '2026-11-12 08:00:00'), 'AP', '2026-11-12 09:00:00'));
        $this->assertTrue(semantic_incoming_receiver_should_apply(array('status' => 'AB', 'date' => '2026-11-12 08:00:00'), 'RE', '2026-11-12 08:00:00'), 'same time: the later step');
        $this->assertFalse(semantic_incoming_receiver_should_apply(array('status' => 'PD', 'date' => '2026-11-12 08:00:00'), 'AB', '2026-11-12 08:00:00'));
    }

    public function testACopyOfTheRegnskabReceivesNothingAndNeverCallsSemantic(): void
    {
        $result = semantic_incoming_process($this->client(), array('incomingId' => '501'), array('db' => 'test_7_restored', 'store' => $this->store));
        $this->assertSame('copy', $result['outcome']);
        $this->assertFalse($result['ok']);
        $this->assertSame(array(), $this->calls);
        $this->assertSame(array(), $this->store->rows);

        $poll = semantic_incoming_poll('2026-11-11 00:00:00', 50, $this->client(), 'test_7_restored', $this->store);
        $this->assertSame(0, $poll['listed']);
        $this->assertSame(array(), $this->calls);
    }

    public function testADocumentForAnotherCompanysEndpointIsNotTouched(): void
    {
        $this->queueDocument($this->semanticDoc(505, self::uuid(), array('receiverEndpointId' => self::uuid())), self::ublDocument('Invoice', 'X', '2026-11-11', self::VENDOR_CVR, '99999999'));
        $result = $this->process(array('incomingId' => '505'));
        $this->assertSame('wrong_endpoint', $result['outcome']);
        $this->assertCount(1, $this->calls, 'only the search; the document itself is not read');
        $this->assertSame(array(), $this->store->rows, 'not even recorded here');
        $this->assertSame(array(), $this->store->poolCalls);
        $this->assertNotEmpty($this->store->logs);
    }

    /**
     * @return array<string, array{string, bool, bool, string}>
     */
    public static function closedRegnskaber(): array
    {
        return array(
            'provider is EasyUBL' => array('provider', false, true, 'provider_off'),
            'not updated yet' => array('ready', false, true, 'not_ready'),
            'never registered at Semantic' => array('settings', true, false, 'not_registered'),
        );
    }

    #[DataProvider('closedRegnskaber')]
    public function testNothingRunsBeforeTheRegnskabCanReceive(string $what, bool $provider, bool $registered, string $outcome): void
    {
        if ($what === 'provider') {
            $this->store->provider = $provider;
        } elseif ($what === 'ready') {
            $this->store->isReady = false;
        } elseif (!$registered) {
            $this->store->settingsData = array_fill_keys(semantic_settings_names(), '');
        }
        $result = $this->process(array('incomingId' => '501'));
        $this->assertSame($outcome, $result['outcome']);
        $this->assertFalse($result['ok']);
        $this->assertSame(array(), $this->calls);
        $this->assertSame(array(), $this->store->rows);
    }

    public function testSemanticErrorsLeaveTheDocumentForTheNextTry(): void
    {
        // The search fails: nothing is recorded, so the poll finds it later
        $this->answer(503, '{"title":"Service Unavailable"}');
        $down = $this->process(array('incomingId' => '701'));
        $this->assertSame('fetch_failed', $down['outcome']);
        $this->assertFalse($down['ok']);
        $this->assertStringContainsString('HTTP 503', $down['message']);
        $this->assertSame(array(), $this->store->rows);

        // Not found, and an answer with the id twice, are both refused
        $this->answer(200, '[]');
        $this->assertSame('not_found', $this->process(array('incomingId' => '701'))['outcome']);
        $ublId = self::uuid();
        $this->answer(200, (string)json_encode(array($this->semanticDoc(701, $ublId), $this->semanticDoc(701, self::uuid()))));
        $this->assertSame('not_found', $this->process(array('incomingId' => '701'))['outcome']);
        $this->assertSame(array(), $this->store->rows);

        // The XML call is refused: recorded as failed, tried again next time, then it lands
        $this->answer(200, (string)json_encode(array($this->semanticDoc(701, $ublId))));
        $this->answer(401, '{"title":"Unauthorized"}');
        $failed = $this->process(array('incomingId' => '701'));
        $this->assertSame('failed', $failed['outcome']);
        $this->assertSame('failed', $this->store->rows['701']['status']);
        $this->assertStringContainsString('HTTP 401', $this->store->rows['701']['status_text']);

        // Not XML at all
        $this->answer(200, (string)json_encode(array($this->semanticDoc(701, $ublId))));
        $this->answer(200, '"bm90IHhtbA=="');
        $this->assertSame('failed', $this->process(array('incomingId' => '701'))['outcome']);
        $this->assertSame(2, $this->store->rows['701']['attempts']);

        // The pool refuses: failed, no mail
        $this->store->poolFails = true;
        $this->queueDocument($this->semanticDoc(701, $ublId), self::ublDocument('Invoice', 'INV-701', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR));
        $this->assertSame('failed', $this->process(array('incomingId' => '701'))['outcome']);
        $this->assertSame(array(), $this->store->mails);
        $this->store->poolFails = false;

        // XML as base64 (the GetUblDocumentAsBase64EncodedXml form) is accepted
        $xml = self::ublDocument('Invoice', 'INV-701', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR);
        $this->answer(200, (string)json_encode(array($this->semanticDoc(701, $ublId))));
        $this->answer(200, (string)json_encode(base64_encode("\xEF\xBB\xBF" . $xml)));
        $this->assertSame('pooled', $this->process(array('incomingId' => '701'))['outcome']);
        $this->assertSame(4, $this->store->rows['701']['attempts']);
        $this->assertSame($xml, $this->store->poolCalls[1]['xml']);
    }

    public function testAClaimHeldByAnotherRunIsLeftAlone(): void
    {
        $this->store->foreignClaim = '702';
        $this->answer(200, (string)json_encode(array($this->semanticDoc(702, self::uuid()))));
        $this->assertSame('busy', $this->process(array('incomingId' => '702'))['outcome']);
        $this->assertCount(1, $this->calls);
        $this->assertSame(array(), $this->store->poolCalls);
    }

    public function testAfterTooManyAttemptsTheDocumentIsLeftForSupport(): void
    {
        $this->store->rows['703'] = array('status' => 'failed', 'attempts' => SEMANTIC_INCOMING_MAX_ATTEMPTS, 'token' => 'old');
        $this->answer(200, (string)json_encode(array($this->semanticDoc(703, self::uuid()))));
        $this->assertSame('busy', $this->process(array('incomingId' => '703'))['outcome']);
        $this->assertSame(array(), $this->store->poolCalls);
    }

    public function testOtherDocumentTypesAreRecordedAndIgnored(): void
    {
        $order = '<?xml version="1.0"?><Order ' . self::ns('Order') . '><cbc:ID>O-1</cbc:ID></Order>';
        $this->queueDocument($this->semanticDoc(704, self::uuid()), $order);
        $this->assertSame('ignored', $this->process(array('incomingId' => '704'))['outcome']);
        $this->assertSame('ignored', $this->store->rows['704']['status']);
        $this->assertSame('Order', $this->store->rows['704']['document_type']);
        $this->assertSame(array(), $this->store->poolCalls);

        $entity = '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e "x">]><Invoice ' . self::ns('Invoice') . '><cbc:ID>&e;</cbc:ID></Invoice>';
        $this->queueDocument($this->semanticDoc(705, self::uuid()), $entity);
        $this->assertSame('failed', $this->process(array('incomingId' => '705'))['outcome'], 'a DTD is never parsed');
    }

    public function testPollHandlesEachOwnDocumentOnceAndReportsTheNewest(): void
    {
        $this->seedSendlog();
        $first = $this->semanticDoc(801, self::uuid(), array('receivedDateTime' => '2026-11-12T07:00:00Z'));
        $second = $this->semanticDoc(802, self::uuid(), array('receivedDateTime' => '2026-11-12T09:45:10Z'));
        $foreign = $this->semanticDoc(803, self::uuid(), array('receiverEndpointId' => self::uuid()));
        $this->answer(200, (string)json_encode(array($first, $foreign, $second)));
        $this->answer(200, (string)json_encode(self::ublDocument('Invoice', 'P-1', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR)));
        $this->answer(200, (string)json_encode(self::responseXml(array('code' => 'RE'))));

        $poll = semantic_incoming_poll('2026-11-11 00:00:00', 50, $this->client(), self::DB, $this->store);
        $this->assertSame(2, $poll['listed']);
        $this->assertSame(2, $poll['handled'], implode(' | ', $poll['messages']));
        $this->assertSame(1, $poll['skipped']);
        $this->assertSame(0, $poll['failed']);
        $this->assertSame(semantic_incoming_datetime('2026-11-12T09:45:10Z'), $poll['newest']);
        $filter = json_decode((string)$this->calls[0]['body'], true);
        $this->assertSame($this->companyId, $filter['companyId']);
        $this->assertSame(1, $filter['pageNumber']);
        $this->assertSame(100, $filter['pageSize']);
        $this->assertTrue($poll['complete']);
        $this->assertNull($poll['resumeFrom']);
        $this->assertSame(date('c', (int)strtotime('2026-11-11 00:00:00')), $filter['receivedFrom']);
        $this->assertCount(3, $this->calls, 'one list, two documents; the foreign one is not read');
        $this->assertCount(1, $this->store->poolCalls);
        $this->assertCount(1, $this->store->receiverWrites);
        $this->assertArrayNotHasKey('803', $this->store->rows);

        $this->answer(200, (string)json_encode(array($first, $foreign, $second)));
        $again = semantic_incoming_poll('2026-11-11 00:00:00', 50, $this->client(), self::DB, $this->store);
        $this->assertSame(2, $again['already']);
        $this->assertSame(0, $again['handled']);
        $this->assertCount(4, $this->calls, 'only the list was read again');

        $this->answer(500, 'boom');
        $down = semantic_incoming_poll('2026-11-11 00:00:00', 50, $this->client(), self::DB, $this->store);
        $this->assertSame(1, $down['failed']);
        $this->assertStringContainsString('HTTP 500', $down['messages'][0]);
    }

    public function testAnOlderFailedDocumentHoldsTheWindowAndIsDoneAfterTheOutage(): void
    {
        $this->seedSendlog();
        $older = $this->semanticDoc(811, self::uuid(), array('receivedDateTime' => '2026-11-12T08:00:00Z'));
        $newer = $this->semanticDoc(812, self::uuid(), array('receivedDateTime' => '2026-11-12T12:00:00Z'));
        $this->answer(200, (string)json_encode(array($older, $newer)));
        $this->answer(503, 'down');
        $this->answer(200, (string)json_encode(self::ublDocument('Invoice', 'P-2', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR)));

        $first = semantic_incoming_poll('2026-11-12 00:00:00', 50, $this->client(), self::DB, $this->store);
        $this->assertSame(1, $first['failed']);
        $this->assertSame(1, $first['handled']);
        $this->assertTrue($first['complete']);
        $this->assertSame(semantic_incoming_datetime('2026-11-12T08:00:00Z'), $first['resumeFrom'], 'the next window starts no later than the failed one');
        $this->assertSame(semantic_incoming_datetime('2026-11-12T12:00:00Z'), $first['newest']);

        // The next run starts an hour before resumeFrom (semantic_poll_incoming()), so the older one is listed again.
        $since = date('Y-m-d H:i:s', (int)strtotime((string)$first['resumeFrom']) - 3600);
        $this->answer(200, (string)json_encode(array($older, $newer)));
        $this->answer(200, (string)json_encode(self::ublDocument('Invoice', 'P-1', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR)));
        $second = semantic_incoming_poll($since, 50, $this->client(), self::DB, $this->store);
        $this->assertSame(1, $second['handled'], implode(' | ', $second['messages']));
        $this->assertSame(1, $second['already']);
        $this->assertSame(0, $second['failed']);
        $this->assertNull($second['resumeFrom']);
        $this->assertSame('pooled', $this->store->rows['811']['status']);
    }

    public function testThePullStopsWhenTheBudgetSaysNo(): void
    {
        $this->seedSendlog();
        $a = $this->semanticDoc(821, self::uuid(), array('receivedDateTime' => '2026-11-12T08:00:00Z'));
        $b = $this->semanticDoc(822, self::uuid(), array('receivedDateTime' => '2026-11-12T09:00:00Z'));
        $this->answer(200, (string)json_encode(array($a, $b)));
        $this->answer(200, (string)json_encode(self::ublDocument('Invoice', 'P-1', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR)));
        $rows = 2;
        $may = function () use (&$rows): bool {
            return $rows-- > 0;
        };
        $poll = semantic_incoming_poll('2026-11-12 00:00:00', 50, $this->client(), self::DB, $this->store, $may);
        $this->assertTrue($poll['stopped']);
        $this->assertFalse($poll['complete']);
        $this->assertSame(1, $poll['handled']);
        $this->assertCount(2, $this->calls, 'the list and one document, nothing after the budget said no');
        $this->assertArrayNotHasKey('822', $this->store->rows, 'the second document is not claimed');
        $this->assertSame(semantic_incoming_datetime('2026-11-12T09:00:00Z'), $poll['resumeFrom']);

        $none = function (): bool {
            return false;
        };
        $nothing = semantic_incoming_poll('2026-11-12 00:00:00', 50, $this->client(), self::DB, $this->store, $none);
        $this->assertTrue($nothing['stopped']);
        $this->assertCount(2, $this->calls, 'no list call without budget');
    }

    public function testADocumentGivenUpDoesNotHoldTheWindow(): void
    {
        $this->seedSendlog();
        $doc = $this->semanticDoc(831, self::uuid(), array('receivedDateTime' => '2026-11-12T08:00:00Z'));
        $this->store->rows['831'] = array('status' => 'failed', 'attempts' => SEMANTIC_INCOMING_MAX_ATTEMPTS, 'pool_filename' => '', 'sendlog_id' => 0);
        $this->answer(200, (string)json_encode(array($doc)));
        $poll = semantic_incoming_poll('2026-11-12 00:00:00', 50, $this->client(), self::DB, $this->store);
        $this->assertSame(1, $poll['already']);
        $this->assertTrue($poll['complete']);
        $this->assertNull($poll['resumeFrom']);
        $this->assertCount(1, $this->calls, 'not read again');
    }

    public function testTheWebhookNoticeOnlyNamesTheDocument(): void
    {
        $payload = semantic_webhook_parse_payload('{"incomingDocumentId":"901","ublDocumentId":"' . self::uuid() . '","documentStatus":5170,"message":"Afvist"}');
        $this->assertTrue($payload['ok']);
        $this->assertSame('incoming', $payload['kind']);
        $this->assertSame('901', $payload['incomingDocumentId']);
        $onlyIncoming = semantic_webhook_parse_payload('{"incomingDocumentId":902}');
        $this->assertTrue($onlyIncoming['ok'], 'a notice with only the incoming id is accepted');
        $this->assertSame('902', $onlyIncoming['incomingDocumentId']);
        $this->assertSame('no_document_id', semantic_webhook_parse_payload('{"incomingDocumentId":"9 OR 1=1"}')['reason']);

        // What the document is comes from Semantic: the notice's status and text are not used
        $this->seedSendlog();
        $this->queueDocument($this->semanticDoc(901, self::uuid()), self::responseXml(array('code' => 'AP', 'reason' => '')));
        $result = semantic_incoming_handle_notification($this->client(), $payload, self::DB, array('store' => $this->store));
        $this->assertSame('response_applied', $result['outcome'], $result['message']);
        $this->assertSame(901, json_decode((string)$this->calls[0]['body'], true)['id']);
        $this->assertSame('AP', $this->store->sendlog[1]['receiver_status'], 'the code in the document, not the notice status 5170');
        $this->assertSame('', $this->store->sendlog[1]['receiver_status_text'], 'the notice text is not stored');

        // An incoming notice that names only the document id (TenantNotification.documentId)
        $byDocumentId = semantic_webhook_parse_payload('{"incomingDocumentId":null,"documentId":903}');
        $this->assertSame('incoming', $byDocumentId['kind']);
        $this->queueDocument($this->semanticDoc(903, self::uuid()), self::ublDocument('Invoice', 'W-903', '2026-11-11', self::VENDOR_CVR, self::OUR_CVR));
        $this->assertSame('pooled', semantic_incoming_handle_notification($this->client(), $byDocumentId, self::DB, array('store' => $this->store))['outcome']);
    }

    public function testParsedResponseFields(): void
    {
        $document = semantic_incoming_load_xml(self::responseXml(array('code' => 'uq', 'refDate' => '2026-11-01', 'reason' => "  Mangler\n  rekvisitionsnummer ")));
        $this->assertNotNull($document);
        $response = semantic_incoming_parse_response($document);
        $this->assertSame('invoice_response', $response['kind']);
        $this->assertSame('UQ', $response['code']);
        $this->assertSame('Mangler rekvisitionsnummer', $response['text']);
        $this->assertSame(array('scheme' => '0184', 'id' => self::CUSTOMER_CVR), $response['sender']);
        $this->assertSame(array('scheme' => '0184', 'id' => self::OUR_CVR), $response['receiver']);
        $this->assertSame('1001', $response['refId']);
        $this->assertSame('380', $response['refTypeCode']);
        $this->assertSame('2026-11-01', $response['refIssueDate']);
        $this->assertSame('2026-11-12 09:30:00', $response['date']);

        $mlr = semantic_incoming_parse_response(semantic_incoming_load_xml(self::responseXml(array('customization' => 'urn:fdc:peppol.eu:poacc:trns:mlr:3'))));
        $this->assertSame('mlr', $mlr['kind']);
    }

    public function testClientReadsXmlInEveryFormSemanticMayUse(): void
    {
        $xml = '<Invoice/>';
        $this->assertSame($xml, \SemanticClient::xmlFrom($xml));
        $this->assertSame($xml, \SemanticClient::xmlFrom("\xEF\xBB\xBF" . $xml));
        $this->assertSame($xml, \SemanticClient::xmlFrom(base64_encode($xml)));
        $this->assertNull(\SemanticClient::xmlFrom(base64_encode('no xml')));
        $this->assertNull(\SemanticClient::xmlFrom(array('xml' => $xml)));
        $this->assertNull(\SemanticClient::xmlFrom(''));
        $this->assertSame('validation', $this->client()->getUblDocumentXml('not-a-uuid')['kind']);
        $this->assertSame(array(), $this->calls);
    }

    public function testMailIsTheOneEasyUblSends(): void
    {
        $mail = semantic_incoming_mail_content('2026-11-12 09:00:00');
        $this->assertSame('Ny faktura modtaget', $mail['subject']);
        $this->assertStringContainsString('<title>Faktura notification</title>', $mail['html']);
        $this->assertStringContainsString('Du kan se den i din dokument pulje', $mail['html']);
        $this->assertStringContainsString('kassekladder -&gt; gå ind på en kladde -&gt; papirklips ikonet', $mail['html']);
        $this->assertStringContainsString('Tidspunkt: 2026-11-12 09:00:00', $mail['html']);
    }

    public function testPoolFilenameIsSafe(): void
    {
        $this->assertSame('efaktura_1001.xml', semantic_incoming_pool_filename('Invoice', '1001'));
        $this->assertSame('ekreditnota_a-b-c.xml', semantic_incoming_pool_filename('CreditNote', 'a/../b c'));
        $this->assertSame('efaktura.xml', semantic_incoming_pool_filename('Invoice', '../'));
    }
}
