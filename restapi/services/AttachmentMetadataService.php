<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/AttachmentMetadataService.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Validate JSON numeric amounts before passing decimal text to the invoice parser.
// 20261001 CDX/LH Refuse generated statement overrides before fiscal-year validation.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/MobileEventOutbox.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../core/MobileDisplayName.php';
require_once __DIR__ . '/../models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolReviewVersion.php';
require_once __DIR__ . '/../../finans/kassekladde_includes/vatCodes.php';

/** Owner corrections use the same metadata engine as the web; all writes commit together. */
final class AttachmentMetadataService
{
    private MobileSchema $store;
    private DurableAttachmentModel $model;
    public function __construct(private $connection, private string $db, private string $subject,
        private string $username, private int $userId, private int $tenantId, private ?string $requestId = null)
    {
        $this->store = new MobileSchema($connection);
        $this->model = new DurableAttachmentModel($connection, $db, $subject);
    }

    /** @return int Positive server attachment id; these routes never accept filename selectors. */
    public static function id($value): int
    {
        if ((!is_int($value) && (!is_string($value) || !preg_match('/\A[1-9][0-9]*\z/', $value)))
            || filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 1 || (int)$value > 2147483647) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'id']);
        }
        return (int)$value;
    }

    /** @return array{status:int,body:array{success:bool,message:string,data:array},headers:array<string,string>} Stored envelope and replay headers. */
    public function update(int $id, array $input, ?string $ifMatch, string $clientId, bool $resubmit = false): array
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Metadata service owns its transaction');
        }
        $input['client_id'] = $clientId;
        $this->store->execute('BEGIN');
        try {
            $response = (new Idempotency($this->connection))->execute($this->subject,'attachment.metadata',$id,$input,
                function () use ($id,$input,$ifMatch,$resubmit): array {
                    $row = $this->find($id,true,true);
                    if ($row['status'] === 'deleted') { throw new MobileT2Error('gone',410); }
                    if ($row['status'] !== 'ubehandlet' || !in_array($row['review_state'],['pending','rejected'],true)) {
                        throw new MobileT2Error('conflict',409,['reason'=>'attachment_processed']);
                    }
                    ETag::requireMatch($ifMatch,'"' . pool_review_version($row) . '"');
                    $fields = ['vendor'=>'subject','description'=>'description','amount'=>'amount','date'=>'file_date',
                        'invoice_number'=>'invoice_number','currency'=>'currency','kontonr'=>'account'];
                    $metadata = [];
                    foreach ($input as $field=>$value) {
                        if (!in_array($field,array_merge(array_keys($fields),['vat_code','resubmit','client_id']),true)) {
                            throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>$field]);
                        }
                        if (isset($fields[$field])) {
                            if ((!is_string($value) && !is_int($value) && !is_float($value) && $value !== null)
                                || (is_string($value) && (!mb_check_encoding($value,'UTF-8') || str_contains($value,"\0")))) {
                                throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>$field]);
                            }
                            if ($field === 'amount' && (is_int($value) || is_float($value))) {
                                // Three fractional digits are thousands grouping to the invoice parser.
                                // Only cent precision within norm_amount's 12 integer digits is supported.
                                if (!is_finite((float)$value) || abs($value) >= 1000000000000
                                    || (is_float($value) && $value !== round($value, 2))) {
                                    throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'amount']);
                                }
                                $value = number_format($value, 2, '.', '');
                            }
                            $metadata[$fields[$field]] = $value;
                        }
                    }
                    if (array_key_exists('resubmit',$input) && !is_bool($input['resubmit'])) {
                        throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'resubmit']);
                    }
                    if ($resubmit && ($row['review_state'] !== 'rejected' || trim((string)ifset($row,'reviewed_by','')) === '')) {
                        throw new MobileT2Error('conflict',409,['reason'=>'already_decided']);
                    }
                    if (poolMetadataGeneratedChange($row,$metadata,$this->connection)) {
                        throw new MobileT2Error('conflict',409,['reason'=>'generated_document']);
                    }
                    $rawDate = trim((string)ifset($metadata,'file_date',$row['file_date']));
                    $date = normalizeDateFormat($rawDate);
                    if ($rawDate !== '' && (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/',$date,$parts)
                        || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1]))) {
                        throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'date']);
                    }
                    $context = $date === ''
                        ? EngineContext::forConfig($this->connection,$this->db,$this->tenantId,$this->username,$this->userId)
                        : new EngineContext($this->connection,$this->db,$this->tenantId,$this->username,$this->userId,$date);
                    $result = LegacyEngine::run($context,function () use ($row,$metadata,$input,$resubmit,$context,$id): array {
                        return pool_audit_actor_scope($this->subject,$this->requestId,function () use ($row,$metadata,$input,$resubmit,$context,$id): array {
                            try {
                                poolMetadataApply($row,$metadata,true,poolMetadataVersion($row),$context->regnaar);
                            } catch (InvalidArgumentException $error) {
                                $field = ['Invalid account'=>'kontonr','Invalid amount'=>'amount','Invalid date'=>'date'][$error->getMessage()] ?? 'metadata';
                                throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>$field]);
                            }
                            if (array_key_exists('vat_code',$input)) {
                                $code = $input['vat_code'];
                                if ($code !== null && (!is_string($code) || ($code !== '' && !isset(kassekladde_vat_codes()[strtoupper(trim($code))])))) {
                                    throw new MobileT2Error('validation_failed',422,['reason'=>'vat_code_invalid']);
                                }
                                $value = $code === null || trim($code) === '' ? 'NULL' : $this->store->literal(strtoupper(trim($code)));
                                $this->store->execute("UPDATE pool_files SET vat_code=$value WHERE id=$id");
                            }
                            if ($resubmit) {
                                $this->store->execute("UPDATE pool_files SET review_state='pending',reviewed_by=NULL,reviewed_at=NULL,
                                    rejection_code=NULL,rejection_note=NULL,resubmitted_at=now() WHERE id=$id");
                                pool_audit_append($id,'resubmitted',$this->subject,[],null,null);
                                $saved = $this->find($id);
                                $name = (new MobileDisplayName($this->connection))->sender($this->subject);
                                MobileEventOutbox::emit($this->connection,'attachment_resubmitted',
                                    'attachment_resubmitted:' . $id . ':' . Idempotency::clientId($input),'attachment',$id,
                                    ['name'=>$name,'description'=>$saved['description']],ifset($row,'reviewed_by'));
                            }
                            return $this->model->toArray($this->find($id));
                        });
                    });
                    return ['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>$result]];
                });
            $this->store->execute('COMMIT');
            return $response;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
    }

    /** @return array<string,mixed> Owner or reviewer-scoped identity; staged rows stay private. */
    private function find(int $id, bool $lock = false, bool $ownOnly = true): array
    {
        try { $row = $this->model->find(['id'=>$id],$lock,$ownOnly); }
        catch (AttachmentException $error) {
            throw new MobileT2Error($error->getStatusCode() === 404 ? 'not_found' : $error->error,$error->getStatusCode());
        }
        if ($row['status'] === 'staged') { throw new MobileT2Error('not_found',404); }
        return $row;
    }

    /** @return array{items:list<array>,total:int,limit:int,offset:int,chain_ok:bool} Safe names and full-chain verification, including outside the requested page. */
    public function audit(int $id, array $query, bool $readAll = false): array
    {
        $paging = new Paging($query);
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Audit service owns its snapshot');
        }
        $this->store->execute('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        $previous = ifset($GLOBALS,'connection');
        $GLOBALS['connection'] = $this->connection;
        try {
            $model = new DurableAttachmentModel($this->connection,$this->db,$this->subject,$readAll);
            try { $row = $model->find(['id'=>$id]); }
            catch (AttachmentException $error) { throw new MobileT2Error('not_found',404); }
            if ($row['status'] === 'staged') { throw new MobileT2Error('not_found',404); }
            $result = pool_audit_read($id,$paging->limit,$paging->offset);
            $names = new MobileDisplayName($this->connection);
            $result['items'] = array_map(static function (array $item) use ($names): array {
                $actor = $item['actor'];
                $web = $actor === 'web' || str_starts_with($actor,'web:');
                $name = $web ? (substr($actor,4) ?: 'Saldi') : $names->sender($actor);
                $kind = $web ? 'web' : ($name === $actor ? 'system'
                    : ($item['request_id'] !== null || $item['event'] === 'uploaded' ? 'app' : 'web'));
                if (!$web && $name === $actor) { $name = 'Saldi'; }
                return ['id'=>(int)$item['id'],'event'=>$item['event'],'actor'=>['name'=>$name,'kind'=>$kind],
                    'at'=>(new DateTimeImmutable($item['created_at']))->setTimezone(new DateTimeZone('UTC'))->format(DateTimeInterface::ATOM),
                    'changes'=>json_decode($item['changes'],false,512,JSON_THROW_ON_ERROR),
                    'reason_code'=>$item['reason_code'],'note'=>$item['note']];
            },$result['items']);
            $this->store->execute('COMMIT');
            return $result;
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        } finally { $GLOBALS['connection'] = $previous; }
    }
}
