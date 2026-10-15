<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/DocumentService.php --- ver 5.0.0 --- 2026.10.02
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
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.
// 20261001 CDX/LH Prepare immutable PDF and MIME under the recipient lock and retain uncertain SMTP outcomes.

require_once __DIR__ . '/legacy/LegacyEngine.php';

/** Tenant-form rendering and durable, at-most-once outside effects. */
final class DocumentService
{
    private EngineContext $context;
    private string $subject;
    private MobileSchema $store;
    private Idempotency $idempotency;

    public function __construct(EngineContext $context, string $subject)
    {
        if ($subject === '' || strlen($subject) > 255) {
            throw new InvalidArgumentException('Invalid document subject');
        }
        $this->context = $context;
        $this->subject = $subject;
        $this->store = new MobileSchema($context->connection);
        $this->idempotency = new Idempotency($context->connection);
    }

    /** @return array{pdf:string,filename:string,email:string,mailsprog:string,form_nr:int|null,mail_subj:string,mail_text:string,mail_bilag:string} */
    public function renderPdf(int $ordreId, ?int $formular = null): array
    {
        $order = $this->order($ordreId);
        $result = $this->engine(function () use ($ordreId, $formular, $order) {
            LegacyEngine::load('documents');
            if (str_starts_with($order['art'], 'R')) {
                return rykkerprint([(int)$order['konto_id']], [$ordreId], (int)substr($order['art'], 1), 1, 12,
                    $this->context->regnaar, 'fil');
            }
            $form = $formular ?? ((int)$order['status'] >= 3 ? 4 : ((int)$order['status'] === 0 ? 1 : 2));
            $GLOBALS['formular'] = $form;
            return formularprint($ordreId, $form, 0, 'UTF-8', 'fil');
        });
        if (!is_array($result) || !isset($result['pdf'])) {
            throw new MobileT2Error('not_configured', 422, ['reason' => 'form_not_configured']);
        }
        $path = realpath(__DIR__ . '/../../debitor/' . $result['pdf']);
        $root = realpath(__DIR__ . '/../../temp/' . $this->context->db);
        if ($path === false || $root === false || !str_starts_with($path, $root . '/') || !is_readable($path)
            || file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new MobileT2Error('not_configured', 422, ['reason' => 'pdf_render_failed']);
        }
        $result['pdf'] = $path;
        $label = ['tilbud' => 'Tilbud', 'ordre' => 'Ordre', 'faktura' => 'Faktura', 'rykker' => 'Rykker'][$this->document($order)];
        $number = (string)($order['fakturanr'] ?: $order['ordrenr']);
        $result['filename'] = $label . '-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $number) . '.pdf';
        return $result;
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function deliver(array $body, string $operation = 'document.deliver', ?int $formular = null): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Delivery must commit its intent before calling outside the database');
        }
        $ordreId = self::positiveId(ifset($body, 'order_id'));
        $this->store->execute('BEGIN');
        return $this->deliverStarted($body, $operation, $formular, $body, $ordreId);
    }

    /**
     * Hand off a creation transaction: commit the created document, its sending intent and its replay envelope together.
     * The owning dunning service must check Idempotency::replay before creating the rykker, using the same operation/target/request.
     * This method takes ownership of committing/rolling back the open transaction; no outside effect occurs before that commit.
     * @return array{status:int,body:array,headers:array<string,string>}
     */
    public function deliverCreated(int $ordreId, string $channel, ?string $email, array $request, string $operation,
        $targetId, array $responseData, int $status = 201): array
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_INTRANS) {
            throw new LogicException('A document creation transaction must be open');
        }
        $body = ['order_id' => $ordreId, 'channel' => $channel];
        if ($email !== null) {
            $body['email'] = $email;
        }
        return $this->deliverStarted($body, $operation, null, $request, $targetId, $responseData, $status);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    private function deliverStarted(array $body, string $operation, ?int $formular, array $request, $targetId,
        array $responseData = [], int $status = 200): array
    {
        try {
            $ordreId = self::positiveId(ifset($body, 'order_id'));
            $replay = $this->idempotency->replay($this->subject, $operation, $targetId, $request);
            if ($replay !== null) {
                $this->store->execute('COMMIT');
                return $replay;
            }
            $order = $this->order($ordreId, true);
            $channel = ifset($body, 'channel');
            if (!is_string($channel) || !in_array($channel, ['email', 'ean', 'none'], true)) {
                throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_channel']);
            }
            if (ifset($body, 'attach_payment_link', false) !== false) {
                throw new MobileT2Error('not_configured', 422, ['reason' => 'payment_link_not_configured']);
            }
            $recipient = null;
            if ($channel === 'email') {
                $recipient = ifset($body, 'email', (string)$order['email']);
                if (!is_string($recipient) || !self::validEmail($recipient)) {
                    throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_email']);
                }
                $recipient = str_replace(' ', '', $recipient);
            } elseif ($channel === 'ean') {
                $company = $this->store->rows("SELECT var_value FROM settings WHERE var_grp='easyUBL' AND var_name='companyID' ORDER BY id DESC LIMIT 1");
                if (trim((string)ifset($company, [0, 'var_value'], '')) === '') {
                    throw new MobileT2Error('not_configured', 422, ['reason' => 'company_setup_failed']);
                }
                if ((int)$order['status'] < 3 || !in_array($order['art'], ['DO', 'DK'], true)) {
                    throw new MobileT2Error('validation_failed', 422, ['reason' => 'not_invoice']);
                }
                $recipient = (string)($order['ean'] ?: $order['customer_ean']);
                if ($recipient !== '' && !preg_match('/^(?:[0-9]{13}|[0-9]{4}:[A-Za-z0-9.-]+)$/D', $recipient)) {
                    throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_ean']);
                }
            }
            $prepared = null;
            $preparationOutcome = null;
            if ($channel === 'email') {
                try {
                    $prepared = $this->prepareEmail($ordreId, $formular, $recipient, $order);
                } catch (MobileT2Error $error) {
                    $preparationOutcome = ['state' => 'failed', 'reason' => ifset($error->details, 'reason', 'pdf_render_failed'), 'provider_ref' => null];
                }
            }
            $document = $this->store->literal($this->document($order));
            $channelSql = $this->store->literal($channel);
            $recipientSql = $recipient === null ? 'NULL' : $this->store->literal($recipient);
            $subjectSql = $this->store->literal($this->subject);
            $clientSql = $this->store->literal(Idempotency::clientId($request));
            $state = $channel === 'none' ? 'sent' : 'sending';
            $finished = $channel === 'none' ? 'now()' : 'NULL';
            $row = $this->store->rows("INSERT INTO document_deliveries(ordre_id,document,channel,recipient,state,created_by,client_id,finished_at)
                VALUES($ordreId,$document,$channelSql,$recipientSql,'$state',$subjectSql,$clientSql,$finished) RETURNING id")[0];
            $deliveryId = (int)$row['id'];
            $delivery = ['id' => $deliveryId, 'channel' => $channel, 'state' => $channel === 'none' ? 'none' : 'sending',
                'recipient' => $recipient, 'error_reason' => null, 'provider_ref' => null];
            $response = self::envelope($delivery, $responseData);
            $this->idempotency->store($this->subject, $operation, $targetId, $request, $status, $response, 'document_delivery', $deliveryId);
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
        if ($channel === 'none') {
            return ['status' => $status, 'body' => $response, 'headers' => []];
        }
        try {
            $outcome = $preparationOutcome ?? ($channel === 'ean' ? $this->eanEffect($ordreId, $order['art']) : $this->emailEffect($prepared));
        } catch (MobileT2Error $error) {
            $outcome = ['state' => 'failed', 'reason' => ifset($error->details, 'reason', 'pdf_render_failed'), 'provider_ref' => null];
        } catch (Throwable $error) {
            $outcome = ['state' => 'unknown', 'reason' => 'delivery_unknown', 'provider_ref' => null];
        }
        $delivery['state'] = $outcome['state'];
        $delivery['error_reason'] = $outcome['reason'];
        $delivery['provider_ref'] = $outcome['provider_ref'];
        if (ifset($outcome, 'missing_fields', []) !== []) {
            $delivery['missing_fields'] = $outcome['missing_fields'];
        }
        $response = self::envelope($delivery, $responseData);
        $this->store->execute('BEGIN');
        try {
            $this->idempotency->complete($this->subject, $operation, $targetId, $request, $status, $response);
            $current = $this->store->rows("SELECT state FROM document_deliveries WHERE id=$deliveryId FOR UPDATE")[0];
            if ($current['state'] === 'sending') {
                $stateSql = $this->store->literal($delivery['state']);
                $reasonSql = $delivery['error_reason'] === null ? 'NULL' : $this->store->literal($delivery['error_reason']);
                $refSql = $delivery['provider_ref'] === null ? 'NULL' : $this->store->literal($delivery['provider_ref']);
                $this->store->execute("UPDATE document_deliveries SET state=$stateSql,error_reason=$reasonSql,provider_ref=$refSql,finished_at=now() WHERE id=$deliveryId");
            } else {
                $delivery['state'] = $current['state'];
                $response = self::envelope($delivery, $responseData);
                $this->idempotency->complete($this->subject, $operation, $targetId, $request, $status, $response);
            }
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->rollback();
            throw $error;
        }
        return ['status' => $status, 'body' => $response, 'headers' => []];
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function sendEmail(int $ordreId, int $formular, ?string $email, array $request): array
    {
        $request['order_id'] = $ordreId;
        $request['channel'] = 'email';
        if ($email !== null) {
            $request['email'] = $email;
        }
        return $this->deliver($request, 'document.deliver', $formular);
    }

    /** @return array{status:int,body:array,headers:array<string,string>} */
    public function sendEan(int $ordreId, array $request): array
    {
        $request['order_id'] = $ordreId;
        $request['channel'] = 'ean';
        return $this->deliver($request);
    }

    /** Standalone resend intent; initial create-and-send uses deliverCreated(). @return array{status:int,body:array,headers:array<string,string>} */
    public function sendDunning(int $rykkerId, ?string $email, array $request): array
    {
        $order = $this->order($rykkerId);
        if (!str_starts_with($order['art'], 'R')) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'not_dunning']);
        }
        return $this->sendEmail($rykkerId, 5 + (int)substr($order['art'], 1), $email, $request);
    }

    /** @return array<string,mixed> */
    private function order(int $ordreId, bool $lock = false): array
    {
        $rows = $this->store->rows("SELECT o.*,a.ean AS customer_ean FROM ordrer o LEFT JOIN adresser a ON a.id=o.konto_id
            WHERE o.id=$ordreId" . ($lock ? ' FOR UPDATE OF o' : ''));
        if (!$rows || !in_array($rows[0]['art'], ['DO', 'DK', 'R1', 'R2', 'R3'], true)) {
            throw new MobileT2Error('not_found', 404);
        }
        return $rows[0];
    }

    private function document(array $order): string
    {
        return str_starts_with($order['art'], 'R') ? 'rykker' : ((int)$order['status'] >= 3 ? 'faktura' : ((int)$order['status'] === 0 ? 'tilbud' : 'ordre'));
    }

    /**
     * Freeze both attachments and all template substitutions while the order lock is held.
     * @return array{ok:bool,error:string|null,recipient:string,mail?:PHPMailer\PHPMailer\PHPMailer,sender?:string,user_mail?:string|null}
     */
    private function prepareEmail(int $ordreId, ?int $formular, string $recipient, array $order): array
    {
        $rendered = $this->renderPdf($ordreId, $formular);
        return $this->engine(function () use ($ordreId, $rendered, $recipient, $order) {
            LegacyEngine::load('documents');
            $GLOBALS['formular'] = str_starts_with($order['art'], 'R') ? 5 + (int)substr($order['art'], 1) : $rendered['form_nr'];
            $GLOBALS['formularsprog'] = $rendered['mailsprog'];
            return send_mails_prepare_result($ordreId, $rendered['pdf'], $recipient, $rendered['mailsprog'], $rendered['form_nr'],
                $rendered['mail_subj'], $rendered['mail_text'], $rendered['mail_bilag'], 0, false);
        });
    }

    /** @return array{state:string,reason:string|null,provider_ref:string|null} */
    private function emailEffect(array $prepared): array
    {
        $result = send_mails_transmit_result($prepared);
        return ['state' => $result['outcome'], 'reason' => $result['ok'] ? null : ($result['outcome'] === 'unknown' ? 'smtp_unknown' : 'smtp_failed'),
            'provider_ref' => null];
    }

    /** @return array{state:string,reason:string|null,provider_ref:string|null,missing_fields:array} */
    private function eanEffect(int $ordreId, string $art): array
    {
        $result = $this->engine(function () use ($ordreId, $art) {
            require_once __DIR__ . '/../../includes/easyUbl/easyUblInvoice.php';
            return easyubl_send_invoice_result($ordreId, $art === 'DK' ? 'creditnote' : 'invoice');
        });
        return ['state' => $result['outcome'], 'reason' => $result['reason'], 'provider_ref' => $result['provider_ref'],
            'missing_fields' => $result['missing_fields']];
    }

    /** Run with the print/mail globals normally provided by the browser shell, restoring them afterwards. @return mixed */
    private function engine(callable $callback)
    {
        $defaults = ['charset' => 'UTF-8', 'formularsprog' => 'dansk', 'ansat_id' => 0,
            'deb_valuta' => $this->context->baseCurrency, 'deb_valutakurs' => 100, 'returside' => '',
            'ps2pdf' => ifset($GLOBALS, 'ps2pdf') ?: '/usr/bin/ps2pdf', 'pdftk' => ifset($GLOBALS, 'pdftk') ?: '/usr/bin/pdftk',
            'weasyprint' => ifset($GLOBALS, 'weasyprint', ''), 'exec_path' => '/usr/bin', 'formular' => 0,
            'logoart' => null, 'logo' => null, 'mailantal' => 0, 's_id' => '', 'lev_nr' => 0];
        $saved = $GLOBALS;
        foreach ($defaults as $key => $value) {
            $GLOBALS[$key] = $value;
        }
        try {
            return LegacyEngine::run($this->context, $callback);
        } finally {
            foreach (array_diff_key($GLOBALS, $saved) as $key => $unused) {
                unset($GLOBALS[$key]);
            }
            foreach ($saved as $key => $value) {
                $GLOBALS[$key] = $value;
            }
        }
    }

    private function rollback(): void
    {
        if (pg_transaction_status($this->context->connection) !== PGSQL_TRANSACTION_IDLE) {
            $this->store->execute('ROLLBACK');
        }
    }

    /** @return array{success:bool,message:string,data:array{delivery:array}} */
    private static function envelope(array $delivery, array $responseData = []): array
    {
        return ['success' => true, 'message' => '', 'data' => array_replace($responseData, ['delivery' => $delivery])];
    }

    public static function positiveId($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value)
            || filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new MobileT2Error('validation_failed', 422, ['reason' => 'invalid_order_id']);
        }
        return (int)$value;
    }

    private static function validEmail(string $value): bool
    {
        $recipients = preg_split('/[;,]/', str_replace(' ', '', $value));
        foreach ($recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }
        return strlen($value) <= 2000 && !str_contains($value, "\r") && !str_contains($value, "\n");
    }
}
