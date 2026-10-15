<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/CustomerDirectoryService.php --- ver 5.0.0 --- 2026.10.03
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
// 20261001 CDX/LH Resolve sale-ready defaults, synchronize primary email and scope account CVR buckets.

require_once __DIR__ . '/OpenPostService.php';
require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/../core/Subject.php';
require_once __DIR__ . '/../../includes/debitor/debtorAccount.php';
require_once __DIR__ . '/../../includes/stdFunc/cvrLookup.php';
require_once __DIR__ . '/../../includes/mobileRegistryUpdates.php';

/** Customer reads share the accounting reader; writes share the web card engine. */
final class CustomerDirectoryService
{
    private $connection;
    private MobileSchema $store;
    public function __construct($connection) {
        $this->connection = $connection;
        $this->store = new MobileSchema($connection);
    }

    /** @return array<string,mixed> Contact fields shared by every customer card. */
    private function contact(array $row): array {
        return ['id'=>(int)$row['id'],'kontonr'=>(string)$row['kontonr'],'name'=>(string)$row['firmanavn'],
            'postnr'=>(string)$row['postnr'],'city'=>(string)$row['bynavn'],'phone'=>(string)$row['tlf'],
            'email'=>(string)$row['email'],'cvr'=>(string)$row['cvrnr']];
    }

    /** @return array{id:int,kontonr:string,name:string,postnr:string,city:string,email:string,ean:string,payment_terms:array{type:string,days:int}} */
    private function chooser(array $row): array {
        return ['id'=>(int)$row['id'],'kontonr'=>(string)$row['kontonr'],'name'=>(string)$row['firmanavn'],
            'postnr'=>(string)$row['postnr'],'city'=>(string)$row['bynavn'],'email'=>(string)$row['email'],
            'ean'=>(string)$row['ean'],'payment_terms'=>['type'=>(string)$row['betalingsbet'],'days'=>(int)$row['betalingsdage']]];
    }

    /** @return array{items:array,total:int,limit:int,offset:int} */
    public function search(array $query, bool $restricted = false): array {
        $paging = new Paging($query);
        $q = ifset($query, 'q', '');
        if (!is_string($q) || mb_strlen($q) > 200) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_filter']);
        }
        $where = "art='D'";
        if (trim($q) !== '' && trim($q) !== '*') {
            $pattern = '%' . str_replace('*', '%', addcslashes(trim($q), '\\%_')) . '%';
            $parts = [];
            foreach (['firmanavn','kontonr','tlf','mobile','cvrnr','email'] as $column) {
                $parts[] = "COALESCE($column,'') ILIKE " . $this->store->literal($pattern);
            }
            $where .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        // The restricted query never reads contact/financial columns it cannot return.
        $columns = $restricted ? 'id,kontonr,firmanavn,postnr,bynavn,email,ean,betalingsbet,betalingsdage'
            : 'id,kontonr,firmanavn,postnr,bynavn,tlf,email,cvrnr';
        $total = (int)$this->store->rows("SELECT count(*) AS count FROM adresser WHERE $where")[0]['count'];
        $rows = $this->store->rows("SELECT $columns FROM adresser WHERE $where ORDER BY lower(firmanavn),id " . $paging->sql());
        $balances = $restricted ? [] : openposts_reader($this->connection)->saldo(array_map('intval',array_column($rows,'id')));
        $items = [];
        foreach ($rows as $row) {
            $items[] = $restricted ? $this->chooser($row) : $this->contact($row) + ['saldo'=>$balances[(int)$row['id']]];
        }
        return $paging->response($items,$total);
    }

    /** @return array<string,mixed> Debtor card or the restricted sales chooser row. */
    public function detail($id, bool $restricted = false): array {
        $id = OpenPostService::id($id);
        $columns = $restricted ? 'id,kontonr,firmanavn,postnr,bynavn,email,ean,betalingsbet,betalingsdage' : '*';
        $rows = $this->store->rows("SELECT $columns FROM adresser WHERE art='D' AND id=$id");
        if (!$rows) { throw new MobileT2Error('not_found',404); }
        $row = $rows[0];
        if ($restricted) { return $this->chooser($row); }
        $reader = openposts_reader($this->connection);
        $posts = $reader->items(['konto_id'=>$id],100,0);
        $documents = $this->store->rows("SELECT id,art,status,ordrenr,fakturanr,ordredate,fakturadate,sum,moms,valuta FROM ordrer
            WHERE konto_id=$id AND art IN ('DO','DK') ORDER BY COALESCE(fakturadate,ordredate) DESC NULLS LAST,id DESC LIMIT 10");
        $recent = [];
        foreach ($documents as $document) {
            $status = (int)$document['status'];
            $kind = $document['art'] === 'DK' ? 'kreditnota' : ($status >= 3 ? 'faktura' : ($status === 0 ? 'tilbud' : 'ordre'));
            $recent[] = ['order_id'=>(int)$document['id'],'kind'=>$kind,
                'number'=>(string)($status >= 3 ? $document['fakturanr'] : $document['ordrenr']),
                'date'=>$status >= 3 ? $document['fakturadate'] : $document['ordredate'],
                'total'=>OpenPostsReader::money((float)$document['sum']+(float)$document['moms']),
                'currency'=>trim((string)$document['valuta']) ?: $posts['summary']['currency'],'status'=>$status];
        }
        return $this->contact($row) + ['saldo'=>$reader->saldo([$id])[$id],
            'currency'=>$posts['summary']['currency'],'addr1'=>(string)$row['addr1'],'addr2'=>(string)$row['addr2'],
            'country'=>(string)$row['land'],'ean'=>(string)$row['ean'],'group'=>(int)$row['gruppe'],
            'payment_terms'=>['type'=>(string)$row['betalingsbet'],'days'=>(int)$row['betalingsdage']],
            'open_posts_count'=>$posts['total'],'open_posts'=>$posts['items'],
            'overdue_total'=>$posts['summary']['overdue_total'],'recent_documents'=>$recent,'etag'=>ETag::customer($row)];
    }

    /** @return array<string,string|int> Only the specified public fields, with validated scalar types. */
    private function fields(array $body, bool $create): array {
        $allowed = $create ? ['cvr','name','addr1','addr2','postnr','city','country','phone','email','ean','group']
            : ['phone','email','addr1','addr2','postnr','city'];
        foreach (array_keys($body) as $key) {
            if ($key !== 'client_id' && !in_array($key,$allowed,true)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_field','field'=>$key]);
            }
        }
        $fields = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key,$body)) { continue; }
            $value = $body[$key];
            if ($key === 'group') {
                if ((!is_int($value) && (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D',$value))) || strlen((string)$value)>10 || (int)$value<0 || (int)$value>2147483647) {
                    throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_group']);
                }
                $fields['gruppe'] = (int)$value;
                continue;
            }
            if (!is_string($value) || mb_strlen($value) > ($key === 'email' ? 254 : 200)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_field','field'=>$key]);
            }
            $value = trim($value);
            if ($key === 'email' && $value !== '' && !filter_var($value,FILTER_VALIDATE_EMAIL)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_email']);
            }
            if (($key === 'cvr' && $value !== '' && !preg_match('/^[0-9]{8}$/D',$value))
                || ($key === 'ean' && $value !== '' && !preg_match('/^[0-9]{13}$/D',$value))) {
                throw new MobileT2Error('validation_failed',422,['reason'=>$key === 'ean' ? 'invalid_ean' : 'invalid_cvr']);
            }
            $column = ['name'=>'firmanavn','city'=>'bynavn','country'=>'land','phone'=>'tlf','cvr'=>'cvrnr'][$key] ?? $key;
            $fields[$column] = $value;
        }
        if ($create && trim((string)ifset($fields,'firmanavn','')) === '') {
            throw new MobileT2Error('validation_failed',422,['reason'=>'missing_fields','field'=>'name']);
        }
        return $fields;
    }

    /**
     * Resolve the primary web form defaults, rejecting unusable fiscal-year groups.
     * @return array<string,string|int> Validated creation fields with payment terms.
     */
    private function creationDefaults(array $fields, int $year): array {
        if (!array_key_exists('gruppe',$fields)) {
            $required = $this->store->rows("SELECT id FROM grupper WHERE art='DIV' AND kodenr=2 AND box1='on'");
            if ($required) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'missing_fields','field'=>'group']);
            }
            $fields['gruppe'] = 1;
            if (!$this->store->rows("SELECT id FROM grupper WHERE art='DG' AND kodenr=1 AND fiscal_year=$year")) {
                throw new MobileT2Error('not_configured',422,['what'=>'debtor_group']);
            }
        }
        $group = (int)$fields['gruppe'];
        if (!$this->store->rows("SELECT id FROM grupper WHERE art='DG' AND kodenr=$group AND fiscal_year=$year")) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_group']);
        }
        $defaults = debtor_payment_defaults();
        // A legacy blank first option supplies no terms; use the card's initial Netto default.
        if (trim($defaults['betalingsbet']) === '') { $defaults['betalingsbet'] = 'Netto'; }
        if (in_array($defaults['betalingsbet'],['Kontant','Efterkrav','Forud'],true)) {
            $defaults['betalingsdage'] = 0;
        }
        return $fields + $defaults;
    }

    /**
     * Match the web card's first-row primary email rule, leaving extra recipients intact.
     * The caller holds the adresser row lock and owns the transaction.
     * @return void
     */
    private function primaryEmail(int $id, string $email): void {
        $rows = $this->store->rows("SELECT id FROM kontakt_emails WHERE konto_id=$id ORDER BY id LIMIT 1 FOR UPDATE");
        $value = $this->store->literal($email);
        if ($rows) {
            $primaryId = (int)$rows[0]['id'];
            if ($email === '') {
                $this->store->execute("DELETE FROM kontakt_emails WHERE id=$primaryId AND konto_id=$id");
            } else {
                $this->store->execute("UPDATE kontakt_emails SET email=$value WHERE id=$primaryId AND konto_id=$id");
            }
        } elseif ($email !== '') {
            $this->store->execute("INSERT INTO kontakt_emails(konto_id,email,email_type) VALUES ($id,$value,'hoved')");
        }
        $rows = $this->store->rows("SELECT email FROM kontakt_emails WHERE konto_id=$id ORDER BY id LIMIT 1");
        $primary = $this->store->literal((string)ifset($rows,[0,'email'],''));
        $this->store->execute("UPDATE adresser SET email=$primary WHERE id=$id");
    }

    /** @return array{status:int,body:array,headers:array} Atomically persisted response, including replay. */
    public function write(EngineContext $context, string $subject, array $body, $id = null, ?string $ifMatch = null): array {
        if (pg_transaction_status($this->connection) !== PGSQL_TRANSACTION_IDLE) {
            throw new LogicException('Customer writes require an idle connection');
        }
        $id = $id === null ? null : OpenPostService::id($id);
        return LegacyEngine::run($context,function () use ($context,$subject,$body,$id,$ifMatch): array {
            $this->store->execute('BEGIN');
            try {
                $operation = $id === null ? 'customer.create' : 'customer.update';
                $response = (new Idempotency($this->connection))->execute($subject,$operation,$id,$body,function () use ($context,$body,$id,$ifMatch): array {
                    $fields = $this->fields($body,$id === null);
                    if ($id === null) {
                        $fields = $this->creationDefaults($fields,$context->regnaar);
                        $newId = debtor_create($fields);
                        if (array_key_exists('email',$fields)) { $this->primaryEmail($newId,$fields['email']); }
                        $data = $this->store->rows("SELECT id,kontonr FROM adresser WHERE id=$newId")[0];
                        $data['id'] = (int)$data['id'];
                    } else {
                        $rows = $this->store->rows("SELECT id,modtime FROM adresser WHERE art='D' AND id=$id FOR UPDATE");
                        if (!$rows) { throw new MobileT2Error('not_found',404); }
                        ETag::requireMatch($ifMatch,ETag::customer($rows[0]));
                        $assignments = [];
                        foreach ($fields as $column=>$value) { $assignments[] = $column.'='.$this->store->literal($value); }
                        // Also advance modtime on tenants lacking the legacy update trigger.
                        $assignments[] = 'modtime=clock_timestamp()';
                        $this->store->execute("UPDATE adresser SET " . implode(',',$assignments) . " WHERE id=$id");
                        if (array_key_exists('email',$fields)) { $this->primaryEmail($id,$fields['email']); }
                        $data = $this->detail($id);
                    }
                    return ['status'=>$id === null ? 201 : 200,'body'=>['success'=>true,'message'=>'','data'=>$data]];
                });
                $this->store->execute('COMMIT');
                return $response;
            } catch (Throwable $error) {
                $this->store->execute('ROLLBACK');
                throw $error;
            }
        });
    }
}

/** Installation-wide company cache and tenant-scoped account CVR token buckets. */
final class CvrDirectoryService
{
    private $connection;
    private MobileSchema $store;
    private $lookup;
    public function __construct($registryConnection, ?callable $lookup = null) {
        $this->connection = $registryConnection;
        $this->store = mobileT2RegistryStore($registryConnection);
        $this->lookup = $lookup ?? 'cvr_lookup';
    }

    /** @return string Registry bucket identity; registry principals share across selected tenants. */
    public static function bucketKey(string $subject, int $tenantId): string {
        $identity = Subject::parse($subject);
        if ($tenantId < 1) { throw new InvalidArgumentException('CVR lookup requires an authenticated tenant'); }
        return hash('sha256',Idempotency::canonicalJson([$subject,$identity['principal'] === 'account' ? $tenantId : 0]));
    }

    /** @return array{cvr:string,name:string,addr1:string,postnr:string,city:string,phone:string,email:string,industry:string,status:string,fetched_at:string} */
    public function lookup($cvr, string $subject, int $tenantId): array {
        if (!is_string($cvr) || !preg_match('/^[0-9]{8}$/D',$cvr)) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_cvr']);
        }
        $key = $this->store->literal(self::bucketKey($subject,$tenantId));
        $number = $this->store->literal($cvr);
        $this->store->execute('BEGIN');
        try {
            $this->store->execute("INSERT INTO mobile_rate_buckets(scope,bucket_key,tokens,updated_at) VALUES ('cvr',$key,10,now()) ON CONFLICT DO NOTHING");
            $row = $this->store->rows("SELECT LEAST(10,tokens+GREATEST(0,EXTRACT(EPOCH FROM (now()-updated_at)))/60) AS available
                FROM mobile_rate_buckets WHERE scope='cvr' AND bucket_key=$key FOR UPDATE")[0];
            $available = (float)$row['available'];
            $remaining = $available < 1 ? $available : $available-1;
            $this->store->execute("UPDATE mobile_rate_buckets SET tokens=$remaining,updated_at=now() WHERE scope='cvr' AND bucket_key=$key");
            $this->store->execute('COMMIT');
        } catch (Throwable $error) {
            $this->store->execute('ROLLBACK');
            throw $error;
        }
        if ($available < 1) {
            throw new MobileT2Error('rate_limited',429,['retry_after'=>(int)ceil((1-$available)*60)]);
        }
        // Serialize cache misses per CVR so simultaneous misses do not hit the provider twice.
        $this->store->execute('SELECT pg_advisory_lock(hashtextextended('.$this->store->literal('saldi:cvr:'.$cvr).',0))');
        try {
            $rows = $this->store->rows("SELECT payload FROM cvr_cache WHERE cvr=$number AND fetched_at>now()-interval '24 hours'");
            if ($rows) { return json_decode($rows[0]['payload'],true,512,JSON_THROW_ON_ERROR); }
            $result = ($this->lookup)('vat',$cvr,'dk');
            if ($result['status'] === 404) { throw new MobileT2Error('not_found',404); }
            $body = $result['body'];
            if ($result['status'] !== 200 || !is_array($body) || empty($body['name']) || !is_scalar(ifset($body,'vat')) || (string)$body['vat'] !== $cvr) {
                throw new MobileT2Error('upstream_unavailable',503);
            }
            foreach (['vat','name','address','zipcode','city','phone','email','industrydesc','status'] as $field) {
                if (isset($body[$field]) && !is_scalar($body[$field])) {
                    throw new MobileT2Error('upstream_unavailable',503);
                }
            }
            $now = $this->store->rows("SELECT to_char(now() AT TIME ZONE 'UTC','YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS instant")[0]['instant'];
            $data = ['cvr'=>$cvr,'name'=>(string)$body['name'],'addr1'=>(string)ifset($body,'address',''),
                'postnr'=>(string)ifset($body,'zipcode',''),'city'=>(string)ifset($body,'city',''),
                'phone'=>(string)ifset($body,'phone',''),'email'=>(string)ifset($body,'email',''),
                'industry'=>(string)ifset($body,'industrydesc',''),
                'status'=>strtoupper((string)ifset($body,'status','NORMAL')) === 'NORMAL' ? 'active' : 'ceased','fetched_at'=>$now];
            $payload = $this->store->literal(json_encode($data,JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $this->store->execute("INSERT INTO cvr_cache(cvr,payload,fetched_at) VALUES ($number,$payload,now())
                ON CONFLICT(cvr) DO UPDATE SET payload=EXCLUDED.payload,fetched_at=EXCLUDED.fetched_at");
            return $data;
        } finally {
            $this->store->execute('SELECT pg_advisory_unlock(hashtextextended('.$this->store->literal('saldi:cvr:'.$cvr).',0))');
        }
    }
}
