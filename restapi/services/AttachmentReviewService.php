<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/AttachmentReviewService.php --- ver 5.0.0 --- 2026.10.06
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
// 20261001 CDX/LH Resolve current draft currency and preserve audited approval expense direction.
// 20261006 CL/LH Compare adresser.kontonr (varchar) as text for debitor/kreditor counter-accounts.

require_once __DIR__ . '/AttachmentMetadataService.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolReview.php';
require_once __DIR__ . '/../../includes/finans/kladdeJournal.php';

/** Reviewer reads and rejection; draft amounts use the shared journal VAT engine. */
final class AttachmentReviewService
{
    private MobileSchema $store;
    private DurableAttachmentModel $model;
    private MobileDisplayName $names;

    public function __construct(private $connection, private string $db, private string $subject,
        private string $username, private int $userId, private int $tenantId, private ?string $requestId = null)
    {
        $this->store = new MobileSchema($connection);
        $this->model = new DurableAttachmentModel($connection, $db, $subject, true);
        $this->names = new MobileDisplayName($connection);
    }

    /** @return int Positive canonical server attachment/journal id. */
    public static function id($value): int { return AttachmentMetadataService::id($value); }

    /** @return mixed Callback result, with transaction ownership and selected connection restored. */
    private function transaction(bool $readOnly, callable $operation)
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Review service owns its transaction');
        }
        $present = array_key_exists('connection', $GLOBALS);
        $previous = ifset($GLOBALS, 'connection');
        $GLOBALS['connection'] = $this->connection;
        try {
            $this->store->execute($readOnly ? 'BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY' : 'BEGIN');
            $result = $operation();
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) { $this->store->execute('ROLLBACK'); }
            throw $error;
        } finally {
            if ($present) { $GLOBALS['connection'] = $previous; }
            else { unset($GLOBALS['connection']); }
        }
    }

    /** @return array<string,mixed> Reviewer-visible identity; staged reservations are never reviewable. */
    private function find(int $id, bool $lock = false): array
    {
        try { $row = $this->model->find(['id'=>$id], $lock); }
        catch (AttachmentException $error) { throw new MobileT2Error('not_found', 404); }
        if ($row['status'] === 'staged') { throw new MobileT2Error('not_found', 404); }
        return $row;
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int} Review items in a single snapshot. */
    public function queue(array $query): array
    {
        $page = new Paging($query);
        $state = ifset($query, 'state', 'pending');
        if (!is_string($state)) { throw new MobileT2Error('validation_failed', 422, ['reason'=>'metadata_invalid','field'=>'state']); }
        return $this->transaction(true, function () use ($page, $state): array {
            $result = pool_review_queue($this->store, $state, $page);
            $result['items'] = array_map(fn($row)=>pool_review_item($this->store, $this->model, $this->names, $this->subject, $row), $result['items']);
            return $result;
        });
    }

    /**
     * Add editable metadata, current ledger match and audit head to the review projection.
     * @return array{
     *   id:int,etag:string,metadata:array{vendor:string,description:string,amount:?string,date:?string,
     *     invoice_number:string,currency:string,kontonr:?int,vat_code:?string},
     *   kreditor:array{kontonr:string,name:string,match:?string,mode:string,candidates:list<array{kontonr:string,name:string,match:?string}>}|null,
     *   rejection:array{code:?string,message:?string,at:?string,by_name:string}|null,
     *   draft:array{kassekladde_id:int,kladde_id:int,bilag:int,posted:bool,journal_note:string}|null,
     *   audit:list<array{id:int,event:string,actor:array{name:string,kind:string},at:?string,changes:object,reason_code:?string,note:?string}>,
     *   ...
     * }
     */
    public function detail(int $id): array
    {
        return $this->transaction(true, function () use ($id): array {
            $row = $this->find($id);
            $item = pool_review_item($this->store, $this->model, $this->names, $this->subject, $row);
            $item['metadata'] = ['vendor'=>(string)$row['subject'], 'description'=>(string)$row['description'], 'amount'=>$item['amount'],
                'date'=>$item['date'], 'invoice_number'=>(string)$row['invoice_number'], 'currency'=>$item['currency'],
                'kontonr'=>ctype_digit((string)$row['account']) && (int)$row['account'] > 0 ? (int)$row['account'] : null, 'vat_code'=>$row['vat_code']];
            $vendor = null;
            if (trim((string)ifset($row, 'vendor_match', '')) !== '') {
                // Pure existing matcher, with no rematch writes on this GET path.
                $vendor = poolVendorFromRow($row, poolVendorLoadIndex());
            }
            $choice = poolVendorSuggestion($vendor);
            $item['kreditor'] = $choice['mode'] === 'none' ? null : ['kontonr'=>(string)$choice['kontonr'],
                'name'=>(string)$choice['firmanavn'], 'match'=>$choice['mode'] === 'choose' ? null : $vendor['match'],
                'mode'=>$choice['mode'], 'candidates'=>array_map(static fn($c)=>
                    ['kontonr'=>$c['kontonr'], 'name'=>$c['firmanavn'], 'match'=>null], $choice['candidates'])];
            $item['rejection'] = $this->model->toArray($row)['rejection'];
            $drafts = $this->store->rows("SELECT k.id AS kassekladde_id,k.kladde_id,k.bilag,j.bogfort,j.kladdenote
                FROM mobile_journal_lines m JOIN kassekladde k ON k.id=m.kassekladde_id AND k.kladde_id=m.kladde_id
                JOIN kladdeliste j ON j.id=k.kladde_id
                WHERE m.pool_file_id=$id AND EXISTS (SELECT 1 FROM documents d WHERE d.pool_file_id=m.pool_file_id
                    AND d.source='kassekladde' AND d.source_id=k.id) ORDER BY m.id DESC LIMIT 1");
            $draft = ifset($drafts, 0);
            $item['draft'] = $draft === null ? null : ['kassekladde_id'=>(int)$draft['kassekladde_id'],
                'kladde_id'=>(int)$draft['kladde_id'], 'bilag'=>(int)$draft['bilag'], 'posted'=>$draft['bogfort'] === 'V',
                'journal_note'=>(string)$draft['kladdenote']];
            $audit = pool_audit_read($id, 20);
            $item['audit'] = array_map(function ($a): array {
                $actor = (string)$a['actor'];
                $web = $actor === 'web' || str_starts_with($actor, 'web:');
                $name = $web ? (substr($actor, 4) ?: 'Saldi') : pool_review_name($this->names, $actor);
                return ['id'=>(int)$a['id'], 'event'=>$a['event'], 'actor'=>['name'=>$name,
                    'kind'=>$web ? 'web' : ($name === 'Saldi' ? 'system' : 'app')], 'at'=>pool_review_time($a['created_at']),
                    'changes'=>json_decode($a['changes'], false, 512, JSON_THROW_ON_ERROR), 'reason_code'=>$a['reason_code'], 'note'=>$a['note']];
            }, $audit['items']);
            return $item;
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Atomic decision and stored replay envelope. */
    public function reject(int $id, array $input, ?string $ifMatch): array
    {
        return $this->transaction(false, fn()=> (new Idempotency($this->connection))->execute(
            $this->subject, 'attachment.reject', $id, $input, function () use ($id, $input, $ifMatch): array {
                $row = $this->find($id, true);
                return pool_audit_actor_scope($this->subject, $this->requestId, function () use ($row, $id, $input, $ifMatch): array {
                    $saved = pool_review_reject($this->store, $row, $this->subject, $ifMatch, $input);
                    $data = $this->model->toArray($saved);
                    $target = trim((string)ifset($saved, 'uploaded_by', ''));
                    if ($target !== '') {
                        MobileEventOutbox::emit($this->connection, 'attachment_decided',
                            'attachment_decided:' . $id . ':' . Idempotency::clientId($input), 'attachment', $id,
                            ['decision'=>'rejected', 'name'=>pool_review_name($this->names, $this->subject),
                                'description'=>$saved['description'], 'reason'=>$saved['rejection_note']], $target);
                    }
                    return ['status'=>200, 'body'=>['success'=>true, 'message'=>'', 'data'=>
                        array_intersect_key($data, array_flip(['id','review_state','display_status','rejection','etag']))]];
                });
            }));
    }

    /**
     * Read only lines with mobile provenance; totals cover those lines in base currency before paging.
     * @return array{journal:array{kladde_id:int,note:string,posted:bool,locked_by:?string}|null,
     *   items:list<array>,totals:array{amount:string,vat:?string},total:int,limit:int,offset:int}
     */
    public function drafts(array $query): array
    {
        $page = new Paging($query);
        $id = array_key_exists('kladde_id', $query) ? self::id($query['kladde_id']) : null;
        return $this->transaction(true, function () use ($id, $page): array {
            $explicit = $id !== null;
            if ($id === null) {
                $pointer = $this->store->rows("SELECT var_value FROM settings WHERE var_grp='mobile' AND var_name='approval_kladde_id' AND user_id=0 ORDER BY id DESC LIMIT 1");
                $raw = (string)ifset($pointer, [0,'var_value'], '');
                $id = ctype_digit($raw) && strlen($raw) <= 10 && (float)$raw <= 2147483647 ? (int)$raw : 0;
            }
            $journals = $this->store->rows("SELECT * FROM kladdeliste WHERE id=$id" . ($explicit ? '' : ' AND mobile_approval_journal=true'));
            if (!$journals) {
                if ($explicit) { throw new MobileT2Error('not_found', 404); }
                return ['journal'=>null, 'totals'=>['amount'=>'0.00','vat'=>'0.00']] + $page->response([], 0);
            }
            $journal = $journals[0];
            $lease = kladde_lease_holder($id);
            $rows = $this->store->rows("SELECT k.*,m.pool_file_id AS attachment_id,m.mode AS approval_mode,p.subject AS vendor,
                COALESCE((SELECT a.changes::jsonb #>> '{amount,from}' FROM pool_file_audit a
                    WHERE a.pool_file_id=m.pool_file_id AND a.event='metadata_changed' AND a.changes::jsonb ? 'amount'
                    AND a.id > (SELECT d.id FROM pool_file_audit d WHERE d.pool_file_id=m.pool_file_id
                        AND d.event='draft_created' AND d.created_at=m.created_at
                        AND d.changes::jsonb->>'kassekladde_id'=k.id::text ORDER BY d.id DESC LIMIT 1)
                    ORDER BY a.id LIMIT 1),p.amount) AS approval_amount FROM kassekladde k
                JOIN LATERAL (SELECT * FROM mobile_journal_lines m WHERE m.kassekladde_id=k.id AND m.kladde_id=k.kladde_id
                    AND m.mode<>'attach_only' ORDER BY m.id DESC LIMIT 1) m ON true
                JOIN pool_files p ON p.id=m.pool_file_id WHERE k.kladde_id=$id ORDER BY k.bilag,k.id");
            $items = [];
            $amount = $vat = 0;
            foreach ($rows as $row) {
                $computed = $this->draftItem($row);
                $items[] = $computed['item'];
                $amount += $computed['amount_cents'];
                $vat += $computed['vat_cents'];
            }
            return ['journal'=>['kladde_id'=>$id, 'note'=>(string)$journal['kladdenote'], 'posted'=>$journal['bogfort'] === 'V',
                'locked_by'=>ifset($lease, 'holder')], 'totals'=>['amount'=>number_format($amount/100, 2, '.', ''),
                'vat'=>number_format($vat/100, 2, '.', '')]] + $page->response(array_slice($items, $page->offset, $page->limit), count($items));
        });
    }

    /**
     * Row amounts use line currency; totals use the dated conversion and VAT used by web posting.
     * @return array{amount_cents:int,vat_cents:int,item:array{
     *   kassekladde_id:int,bilag:int,date:string,description:string,kontonr:?int,account_name:?string,
     *   vat_code:?string,amount:string,vat_amount:string,currency:string,
     *   contra:array{type:string,kontonr:string,name:string}|null,attachment_id:int,vendor:string}}
     */
    private function draftItem(array $row): array
    {
        $context = new EngineContext($this->connection, $this->db, $this->tenantId, $this->username, $this->userId, $row['transdate']);
        return LegacyEngine::run($context, function () use ($row, $context): array {
            LegacyEngine::load('kladde');
            // Approval swaps negative new lines. Its signed receipt amount is retained in the
            // pool/audit history; the first later amount diff restores that approval snapshot.
            // Bank approvals fill debit without swapping, whatever the receipt's amount says.
            $credit = $row['approval_mode'] === 'new_line' && normalizePoolAmount($row['approval_amount']) < 0;
            $other = $credit ? 'd' : 'k';
            $account = (int)$row[$credit ? 'kredit' : 'debet'];
            $contraNumber = (string)$row[$credit ? 'debet' : 'kredit'];
            $contraType = $row[$other . '_type'];
            $code = kladde_saved_vat_override($row, $credit ? 'kreditvat' : 'debetvat');
            $otherCode = kladde_saved_vat_override($row, $credit ? 'debetvat' : 'kreditvat');
            $control = '';
            if ((int)$contraNumber > 0 && in_array($contraType, ['D','K'], true)) {
                [, $control] = kladde_gruppeopslag($contraType, (int)$contraNumber);
            }
            $calculateVat = static function (float $gross) use ($row, $account, $control, $code, $otherCode): float {
                if ($row['momsfri'] || $account < 1 || $gross <= 0) { return 0.0; }
                [, $value] = kladde_momsberegning($account, $gross, '', (string)$control, $code ?? '', trim((string)$otherCode) !== '');
                return (float)$value;
            };
            $gross = (float)$row['amount'];
            $vat = $calculateVat($gross);
            $baseGross = $gross;
            $currencyId = (int)$row['valuta'];
            $currency = $context->baseCurrency;
            if ($currencyId > 0) {
                $currency = normalizePoolCurrency(ifset($this->store->rows("SELECT box1 FROM grupper
                    WHERE art='VK' AND kodenr=$currencyId ORDER BY id LIMIT 1"), [0,'box1']));
                if ($currency === null || $currency === '') {
                    throw new MobileT2Error('validation_failed', 422, ['reason'=>'currency_unknown']);
                }
            }
            if ($currencyId > 0 && $gross != 0 && ($row['debet'] || $row['kredit'])) {
                // The legacy helper prints an alert when the rate is missing; keep GET JSON valid.
                $dateSql = $this->store->literal($row['transdate']);
                $rate = $this->store->rows("SELECT kurs FROM valuta WHERE gruppe=$currencyId AND valdate<=$dateSql ORDER BY valdate DESC LIMIT 1");
                if (!(float)ifset($rate, [0,'kurs'], 0)) {
                    throw new MobileT2Error('validation_failed', 422, ['reason'=>'missing_exchange_rate']);
                }
                [$baseGross] = kladde_valutaopslag($gross, $currencyId, $row['transdate']);
            }
            $baseVat = $baseGross === $gross ? $vat : $calculateVat((float)$baseGross);
            if ($code === null || $code === '') {
                $code = trim((string)ifset($this->store->rows("SELECT moms FROM kontoplan WHERE kontonr=$account AND regnskabsaar=$context->regnaar ORDER BY id LIMIT 1"), [0,'moms'], '')) ?: null;
            }
            $contra = null;
            if ((int)$contraNumber > 0) {
                $nr = (int)$contraNumber;
                $type = $this->store->literal((string)$contraType);
                $name = $contraType === 'F' ? pool_review_account_name($this->store, $nr, $context->regnaar)
                    : ifset($this->store->rows("SELECT firmanavn FROM adresser WHERE kontonr='$nr' AND art=$type ORDER BY id LIMIT 1"), [0,'firmanavn']);
                $contra = ['type'=>$contraType, 'kontonr'=>$contraNumber, 'name'=>(string)$name];
            }
            $sign = $credit ? -1 : 1;
            return ['amount_cents'=>(int)round($sign*$baseGross*100), 'vat_cents'=>(int)round($sign*$baseVat*100),
                'item'=>['kassekladde_id'=>(int)$row['id'], 'bilag'=>(int)$row['bilag'], 'date'=>$row['transdate'],
                'description'=>(string)$row['beskrivelse'], 'kontonr'=>$account ?: null,
                'account_name'=>pool_review_account_name($this->store, $account, $context->regnaar),
                'vat_code'=>$row['momsfri'] ? null : $code, 'amount'=>number_format($sign*(float)$row['amount'], 2, '.', ''),
                'vat_amount'=>number_format($sign*(float)$vat, 2, '.', ''), 'currency'=>$currency,
                'contra'=>$contra, 'attachment_id'=>(int)$row['attachment_id'], 'vendor'=>(string)$row['vendor']]];
        });
    }
}
