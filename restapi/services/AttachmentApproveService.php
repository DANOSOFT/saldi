<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/AttachmentApproveService.php --- ver 5.0.0 --- 2026.10.07
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
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.
require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/Subject.php';
require_once __DIR__ . '/../../includes/docsIncludes/poolApprove.php';

/** Approval facade; tenant/actor values always come from the authenticated endpoint. */
final class AttachmentApproveService
{
    public function __construct(private $connection, private string $db, private string $subject,
        private string $username, private int $userId, private int $tenantId, private ?string $requestId=null,
        private ?Closure $hook=null) { }

    /** @return int Positive server identity; the approve request uses a JSON integer. */
    public static function id($id): int
    {
        if (!is_int($id) || $id<=0 || $id>2147483647) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>'id']);
        }
        return $id;
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Committed envelope and optional replay headers. */
    public function approve(int $id, array $input, ?string $ifMatch, string $clientId): array
    {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) { throw new LogicException('Approval requires idle connection'); }
        $input['client_id']=$clientId;
        foreach (array_keys($input) as $field) {
            if (!in_array($field,['client_id','id','kontonr','vat_code','vat_exempt','contra','date','amount','currency','description','invoice_number','suggestion_id'],true)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'metadata_invalid','field'=>$field]);
            }
        }
        // Use a known fiscal context for bootstrap only. Draft validation resolves the corrected
        // date inside the decision; attaching to a posted row does not validate a draft/date/rate.
        $context=EngineContext::forConfig($this->connection,$this->db,$this->tenantId,$this->username,$this->userId);
        $existed=array_key_exists('revisor',$GLOBALS);
        $previous=ifset($GLOBALS,'revisor');
        $GLOBALS['revisor']=Subject::parse($this->subject)['principal'] === 'registry';
        try {
            return LegacyEngine::run($context,fn(): array => pool_audit_actor_scope($this->subject,$this->requestId,
                fn(): array => pool_approve(['connection'=>$this->connection,'db'=>$this->db,'subject'=>$this->subject,
                    'username'=>$this->username,'user_id'=>$this->userId,'tenant_id'=>$this->tenantId,'request_id'=>$this->requestId,
                    'if_match'=>$ifMatch,'hook'=>$this->hook],$id,$input)));
        } finally { if ($existed) { $GLOBALS['revisor']=$previous; } else { unset($GLOBALS['revisor']); } }
    }
}
