<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/services/SalesService.php --- ver 5.0.0 --- 2026.10.07
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
// 20261001 CDX/LH Resolve dated mobile exchange rates before creating order lines.
// 20261001 CDX/LH Add atomic approval/invoice transitions with replay, web locks and ETags.
// 20261001 CDX/LH Dispatch collected webshop stock keys only after invoice commit.
// 20261007 CDX/LH Separate configuration fiscal years from posting date validation.

require_once __DIR__ . '/legacy/LegacyEngine.php';
require_once __DIR__ . '/../core/ETag.php';
require_once __DIR__ . '/../core/Paging.php';
require_once __DIR__ . '/../../includes/orders/orderTotals.php';
require_once __DIR__ . '/../../includes/orders/orderHeader.php';
require_once __DIR__ . '/../../includes/orders/orderLines.php';
require_once __DIR__ . '/../../includes/orders/orderApprove.php';
require_once __DIR__ . '/../../includes/invoicing/invoiceFinalize.php';

/** Caller-owned transactions over Saldi's pricing/header/totals engines. */
final class SalesService
{
    private EngineContext $context;
    private string $subject;
    private Idempotency $idempotency;

    public function __construct(EngineContext $context, string $subject)
    {
        $this->context=$context; $this->subject=$subject;
        $this->idempotency=new Idempotency($context->connection);
    }

    /** @return int Canonical positive database id; body ids must be JSON integers. */
    public static function id($value, bool $body = false): int
    {
        if (($body && !is_int($value)) || (!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]{0,9}$/D',(string)$value) || (int)$value>2147483647) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_id']);
        }
        return (int)$value;
    }

    /**
     * Price through opret_ordrelinje in a rolled-back transaction without allocating an order number.
     * @return array{lines:array,totals:array{net:string,vat:string,gross:string,currency:string},warnings:array}
     */
    public function quote(array $body): array
    {
        $this->execute('BEGIN');
        try {
            $result=$this->engine(function () use ($body): array {
                $customer=self::id(ifset($body,'konto_id'),true);
                $lines=$this->validateLines(ifset($body,'lines'));
                $id=order_create_header($customer,0,[],false);
                $this->resolveExchangeRate($id);
                $this->setOrderGlobals($id);
                order_insert_app_lines($id,$lines);
                order_totals_persist($id);
                $order=$this->readOrder($id);
                return ['lines'=>$order['lines'],'totals'=>$order['totals'],'warnings'=>$this->warnings($lines)];
            });
            return $result;
        } finally {
            $this->execute('ROLLBACK');
        }
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Durable, replayable tilbud/ordre. */
    public function create(array $body): array
    {
        return $this->write('sales.create',null,$body,function () use ($body): array {
            $customer=self::id(ifset($body,'konto_id'),true);
            $kind=ifset($body,'kind');
            if (!in_array($kind,['tilbud','ordre'],true)) { throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_kind']); }
            $lines=$this->validateLines(ifset($body,'lines'));
            $fields=[];
            foreach (['reference'=>'ref','notes'=>'notes','email'=>'email','delivery_date'=>'levdate'] as $source=>$column) {
                if (array_key_exists($source,$body)) {
                    if (!is_string($body[$source]) || strlen($body[$source])>($source==='notes' ? 4000 : 255) || str_contains($body[$source],"\0")) {
                        throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_header','field'=>$source]);
                    }
                    if ($source==='email' && !filter_var($body[$source],FILTER_VALIDATE_EMAIL)) { throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_email']); }
                    if ($source==='delivery_date') {
                        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$body[$source]);
                        if (!$date || $date->format('Y-m-d')!==$body[$source]) { throw new MobileT2Error('validation_failed',422,['reason'=>'missing_delivery_date']); }
                    }
                    $fields[$column]=$body[$source];
                }
            }
            $id=order_create_header($customer,$kind==='ordre' ? 1 : 0,['fields'=>$fields]);
            $this->resolveExchangeRate($id);
            if ($kind==='ordre' && !isset($fields['levdate'])) {
                $this->execute("UPDATE ordrer SET levdate=CURRENT_DATE WHERE id=$id");
            }
            $this->setOrderGlobals($id);
            order_insert_app_lines($id,$lines);
            if ($why=order_app_editable($id)) { throw new MobileT2Error('conflict',409,['reason'=>'order_not_editable','why'=>$why]); }
            order_totals_persist($id);
            $this->execute('INSERT INTO mobile_order_origins (ordre_id,created_by,client_id,kind) VALUES ($1,$2,$3,$4)',[$id,$this->subject,strtolower($body['client_id']),$kind]);
            return ['status'=>201,'body'=>['success'=>true,'message'=>'','data'=>$this->readOrder($id)],'resource_type'=>'order','resource_id'=>$id];
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Replay is checked before If-Match/state. */
    public function replaceLines(int $id, array $body, ?string $ifMatch): array
    {
        return $this->write('sales.lines',$id,$body,function () use ($id,$body,$ifMatch): array {
            $header=$this->header($id,true);
            $lines=$this->rawLines($id);
            $this->checkWebLock($header);
            ETag::requireMatch($ifMatch,ETag::order($header,$lines));
            $this->setOrderGlobals($id);
            if ($why=order_app_editable($id)) { throw new MobileT2Error('conflict',409,['reason'=>'order_not_editable','why'=>$why]); }
            $replacement=$this->validateLines(ifset($body,'lines'));
            order_replace_lines($id,$replacement);
            if ($why=order_app_editable($id)) { throw new MobileT2Error('conflict',409,['reason'=>'order_not_editable','why'=>$why]); }
            order_totals_persist($id);
            $this->execute("UPDATE ordrer SET tidspkt=$1 WHERE id=$2",[(string)time(),$id]);
            return ['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>$this->readOrder($id)],'resource_type'=>'order','resource_id'=>$id];
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Atomic approval/finalization and replay. */
    public function transition(int $id, array $body, ?string $ifMatch): array
    {
        $stockKeys=[]; $syncYear=$this->context->regnaar;
        $result=$this->write('sales.transition',$id,$body,function () use ($id,$body,$ifMatch,&$stockKeys,&$syncYear): array {
            $to=ifset($body,'to');
            if (!in_array($to,['ordre','faktura'],true)) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_transition']);
            }
            // Acquire the allocator's table lock before any order row/table lock.
            // Upgrading concurrent row locks to EXCLUSIVE during numbering can deadlock.
            $this->execute('LOCK TABLE ordrer IN EXCLUSIVE MODE');
            $header=$this->header($id,true);
            $this->checkWebLock($header);
            if ((int)$header['status']>=3) { throw new MobileT2Error('conflict',409,['reason'=>'already_invoiced']); }
            ETag::requireMatch($ifMatch,ETag::order($header,$this->rawLines($id)));
            $this->setOrderGlobals($id);
            if ($to==='faktura') {
                $date=ifset($body,'invoice_date',(new DateTimeImmutable('now',new DateTimeZone('Europe/Copenhagen')))->format('Y-m-d'));
                if (!is_string($date)) { throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_date']); }
                // Select the invoice date's fiscal year without leaking an alternate context.
                $invoiceContext=new EngineContext($this->context->connection,$this->context->db,$this->context->dbId,
                    $this->context->username,$this->context->userId,$date,$this->context->languageId);
                $GLOBALS['regnaar']=$invoiceContext->regnaar;
                $invoice=invoice_finalize_app($id,$date);
                $stockKeys=$invoice['stock_keys'];
                $syncYear=$invoiceContext->regnaar;
            } else {
                $result=order_approve($id,$header['levdate']);
                if ($result!=='OK') { throw new MobileT2Error('validation_failed',422,['reason'=>'missing_delivery_date']); }
            }
            return ['status'=>200,'body'=>['success'=>true,'message'=>'','data'=>$this->readOrder($id)],'resource_type'=>'order','resource_id'=>$id];
        });
        if ($stockKeys) {
            // write() has committed the invoice and replay record. Refusals and replays have no keys.
            try {
                $this->engine(static function () use ($stockKeys,$syncYear): void {
                    $GLOBALS['regnaar']=$syncYear;
                    foreach ($stockKeys as $key) {
                        try { sync_shop_vare($key['vare_id'],$key['variant_id'],$key['lager']); }
                        catch (Throwable $error) { error_log('Invoice stock sync failed: '.get_class($error)); }
                    }
                });
            } catch (Throwable $error) {
                // An external failure cannot undo an already committed invoice.
                error_log('Invoice stock sync context failed: '.get_class($error));
            }
        }
        return $result;
    }

    /** @return array<string,mixed> Order, including persisted totals and its current ETag. */
    public function get(int $id): array
    {
        return $this->engine(fn(): array=>$this->readOrder($id));
    }

    /** @return array{items:array,total:int,limit:int,offset:int} Orders created by this canonical subject. */
    public function listMine(array $query=[]): array
    {
        $paging=new Paging($query);
        return $this->engine(function () use ($paging): array {
            $total=(int)$this->rows("SELECT count(*) AS n FROM mobile_order_origins m JOIN ordrer o ON o.id=m.ordre_id WHERE m.created_by=$1",[$this->subject])[0]['n'];
            $ids=$this->rows("SELECT o.id FROM ordrer o JOIN mobile_order_origins m ON m.ordre_id=o.id WHERE m.created_by=$1 ORDER BY o.ordredate DESC,o.id DESC ".$paging->sql(),[$this->subject]);
            return $paging->response(array_map(fn(array $row): array=>$this->readOrder((int)$row['id']),$ids),$total);
        });
    }

    /** @return array{status:int,body:array,headers:array<string,string>} Atomic domain mutation plus replay record. */
    private function write(string $operation, $target, array $body, callable $change): array
    {
        $this->execute('BEGIN');
        try {
            $result=$this->idempotency->execute($this->subject,$operation,$target,$body,fn(): array=>$this->engine($change));
            $this->execute('COMMIT');
            if (isset($result['body']['data']['etag'])) { $result['headers']['ETag']=$result['body']['data']['etag']; }
            return $result;
        } catch (Throwable $error) {
            $this->execute('ROLLBACK'); throw $error;
        }
    }

    /** @return mixed Contain all page globals consumed by line creation in addition to EngineContext's globals. */
    private function engine(callable $operation)
    {
        $defaults=['afd'=>0,'barcodeNew'=>'','folger'=>'','formularsprog'=>'','kundedisplay'=>0,'momssats'=>25,
            'fakturadate'=>null,'valutakurs'=>100,'valuta'=>'','levdate'=>null,'lev_nr'=>0,'afd_lager'=>0,'reportNumber'=>0,
            'mail_fakt'=>'','pbs'=>'','retur'=>'','difkto'=>0,'title'=>'','art'=>'DO',
            'procentfakt'=>0,'status'=>0,'tilfravalgNy'=>'','vis_saet'=>0,'voucherNumber'=>null];
        $saved=[];
        foreach ($defaults as $key=>$value) { $saved[$key]=[array_key_exists($key,$GLOBALS),ifset($GLOBALS,$key)]; $GLOBALS[$key]=$value; }
        try {
            $directory=__DIR__.'/../../temp/'.$this->context->db;
            if (!is_dir($directory) && !mkdir($directory,0770,true) && !is_dir($directory)) { throw new RuntimeException('Engine directory missing'); }
            return LegacyEngine::run($this->context,function () use ($operation) {
                LegacyEngine::load('orders');
                try { return $operation(); }
                catch (InvalidArgumentException $error) { throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_order_configuration']); }
            });
        } finally {
            foreach ($saved as $key=>[$exists,$value]) { if ($exists) { $GLOBALS[$key]=$value; } else { unset($GLOBALS[$key]); } }
        }
    }

    /** Resolve the web save's dated rate for mobile headers before pricing any lines. @return void */
    private function resolveExchangeRate(int $id): void
    {
        $header=$this->header($id);
        $currency=trim((string)$header['valuta']) ?: $this->context->baseCurrency;
        $rate=100.0;
        if ($currency!==$this->context->baseCurrency) {
            $rates=$this->rows("SELECT v.kurs FROM valuta v JOIN grupper g ON v.gruppe=CAST(g.kodenr AS integer)
                WHERE g.art='VK' AND g.box1=$1 AND v.valdate<=$2 ORDER BY v.valdate DESC LIMIT 1",[$currency,$header['ordredate']]);
            $rate=(float)ifset($rates,[0,'kurs'],0);
            if (!is_finite($rate) || $rate<=0) {
                throw new MobileT2Error('validation_failed',422,['reason'=>'missing_exchange_rate']);
            }
        }
        $this->execute('UPDATE ordrer SET valuta=$1,valutakurs=$2 WHERE id=$3',[$currency,$rate,$id]);
    }

    /** Supply the same order fields the page passes into opret_ordrelinje. @return void */
    private function setOrderGlobals(int $id): void
    {
        $header=$this->header($id);
        $GLOBALS['afd']=(int)$header['afd']; $GLOBALS['momssats']=(float)$header['momssats'];
        $GLOBALS['status']=(int)$header['status'];
        $GLOBALS['procentfakt']=order_totals_context($header)['procentfakt'];
    }

    /** @return array Raw database header for ETag/version checks. */
    private function header(int $id, bool $lock=false): array
    {
        $rows=$this->rows("SELECT * FROM ordrer WHERE id=$1 AND art IN ('DO','DK')".($lock ? ' FOR UPDATE' : ''),[$id]);
        if (!$rows) { throw new MobileT2Error('not_found',404); }
        return $rows[0];
    }

    /** @return array Raw line rows in stable order. */
    private function rawLines(int $id): array
    {
        return $this->rows('SELECT * FROM ordrelinjer WHERE ordre_id=$1 ORDER BY posnr,id',[$id]);
    }

    /** @return void Apply the specified web lock rule; the acting user's own lock permits edits. */
    private function checkWebLock(array $order): void
    {
        $owner=trim((string)$order['hvem']);
        if ($owner!=='' && $owner!==$this->context->username) { throw new MobileT2Error('conflict',409,['reason'=>'order_locked','locked_by'=>$owner]); }
    }

    /** @return array<string,mixed> The binding Sales Order shape. */
    private function readOrder(int $id): array
    {
        $header=$this->header($id); $raw=$this->rawLines($id);
        $why=order_app_editable($id); $lock=trim((string)$header['hvem']);
        $customer=$this->rows('SELECT id,kontonr,firmanavn,email,ean FROM adresser WHERE id=$1',[(int)$header['konto_id']]);
        $account=ifset($customer,0,[]);
        $open=$this->rows("SELECT id FROM openpost WHERE refnr=$1 AND konto_id=$2 AND amount>0 ORDER BY id LIMIT 1",[$id,(int)$header['konto_id']]);
        $origin=$this->rows('SELECT 1 FROM mobile_order_origins WHERE ordre_id=$1',[$id]);
        $deliveries=$this->rows('SELECT id,channel,state,recipient,created_at FROM document_deliveries WHERE ordre_id=$1 ORDER BY id',[$id]);
        $net=(float)$header['sum']; $vat=(float)$header['moms'];
        return ['id'=>$id,'ordrenr'=>(int)$header['ordrenr'],
            'kind'=>$header['art']==='DK' ? 'kreditnota' : ((int)$header['status']>=3 ? 'faktura' : ((int)$header['status']===0 ? 'tilbud' : 'ordre')),
            'status'=>(int)$header['status'],'fakturanr'=>(string)$header['fakturanr'],
            'konto'=>['id'=>(int)$header['konto_id'],'kontonr'=>(string)ifset($account,'kontonr',$header['kontonr']),
                'name'=>(string)ifset($account,'firmanavn',$header['firmanavn']),'email'=>(string)$header['email'],'ean'=>(string)$header['ean']],
            'lines'=>$this->lineShapes($raw,$header),'totals'=>['net'=>self::money($net),'vat'=>self::money($vat),'gross'=>self::money($net+$vat),
                'currency'=>trim((string)$header['valuta']) ?: $this->context->baseCurrency],
            'posted'=>(int)$header['status']>=4,'openpost_id'=>$open ? (int)$open[0]['id'] : null,'etag'=>ETag::order($header,$raw),
            'locked_by_web'=>$lock!=='' && $lock!==$this->context->username ? $lock : null,'editable'=>$why===null,
            'not_editable_why'=>$why,'created_in_app'=>(bool)$origin,
            'deliveries'=>array_map(static fn(array $row): array=>['id'=>(int)$row['id'],'channel'=>$row['channel'],'state'=>$row['state'],
                'recipient'=>$row['recipient'],'at'=>(new DateTimeImmutable($row['created_at']))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')],$deliveries)];
    }

    /** @return array Sales line shapes; prices are net amounts, independent of the web's display setting. */
    private function lineShapes(array $lines,array $order): array
    {
        $ctx=order_totals_context($order);
        return array_map(static function (array $line) use ($ctx): array {
            $effective=$line; $effective['m_rabat']=-(float)$line['m_rabat'];
            $rate=(float)$line['momssats'];
            if (!$line['momsfri'] && !$rate) { $rate=$ctx['momssats']; }
            elseif ($rate>$ctx['momssats']) { $rate=$ctx['momssats']; }
            elseif ($line['momsfri'] || $line['omvbet']) { $rate=0; }
            $effective['momssats']=$rate;
            return ['vare_id'=>(int)$line['vare_id'],'varenr'=>(string)$line['varenr'],'name'=>(string)$line['beskrivelse'],
                'quantity'=>rtrim(rtrim(number_format((float)$line['antal'],3,'.',''),'0'),'.'),'unit'=>(string)$line['enhed'],
                'unit_price'=>self::money((float)$line['pris']),'discount_pct'=>self::percentage((float)$line['rabat']),
                'vat_rate'=>self::money((float)$line['momssats']),'momsfri'=>$line['momsfri']==='on','line_net'=>self::money(order_line_amounts($effective,$ctx)['net'])];
        },$lines);
    }

    /** @return array Validated lines; no request value reaches the legacy SQL engine uncast. */
    private function validateLines($lines): array
    {
        if (!is_array($lines) || !array_is_list($lines) || count($lines)<1 || count($lines)>100) { throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_lines']); }
        $result=[];
        foreach ($lines as $index=>$input) {
            if (is_object($input)) { $input=get_object_vars($input); }
            if (!is_array($input) || array_diff(array_keys($input),['vare_id','quantity','unit_price','discount_pct'])) { throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_line','line'=>$index]); }
            $id=self::id(ifset($input,'vare_id'),true);
            $item=['vare_id'=>$id,'quantity'=>$this->decimal(ifset($input,'quantity'),3,1000000,false)];
            if (array_key_exists('unit_price',$input)) { $item['unit_price']=$this->decimal($input['unit_price'],2,99999999,true); }
            if (array_key_exists('discount_pct',$input)) { $item['discount_pct']=$this->decimal($input['discount_pct'],5,100,true); }
            $product=$this->rows('SELECT * FROM varer WHERE id=$1',[$id]);
            if (!$product) { throw new MobileT2Error('not_found',404,['line'=>$index]); }
            $row=$product[0];
            $variant=$this->rows('SELECT 1 FROM variant_varer WHERE vare_id=$1 LIMIT 1',[$id]);
            if ($variant || $row['samlevare'] || (int)$row['folgevare'] || $row['serienr']) {
                throw new MobileT2Error('conflict',409,['reason'=>'order_not_editable','why'=>'unsupported_product','line'=>$index]);
            }
            $result[]=$item;
        }
        return $result;
    }

    /** @return float Bounded decimal with explicit precision, rejecting booleans/exponents/commas. */
    private function decimal($value,int $precision,float $maximum,bool $zero): float
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !preg_match('/^[0-9]+(?:\.[0-9]{1,'.$precision.'})?$/D',(string)$value)
            || !is_finite((float)$value) || (float)$value>$maximum || (!$zero && (float)$value<=0)) {
            throw new MobileT2Error('validation_failed',422,['reason'=>'invalid_decimal']);
        }
        return (float)$value;
    }

    /** @return array<array{line:int,code:string}> Advisory catalogue warnings. */
    private function warnings(array $lines): array
    {
        $warnings=[];
        foreach ($lines as $index=>$line) {
            $row=$this->rows('SELECT lukket,beholdning,min_lager FROM varer WHERE id=$1',[$line['vare_id']])[0];
            if (trim((string)$row['lukket'])) { $warnings[]=['line'=>$index,'code'=>'closed_item']; }
            if ((float)$row['beholdning']-(float)$line['quantity']<(float)$row['min_lager']) { $warnings[]=['line'=>$index,'code'=>'below_min_stock']; }
        }
        return $warnings;
    }

    /** @return array<array<string,mixed>> Bound PostgreSQL rows from the server-selected connection. */
    private function rows(string $sql,array $parameters=[]): array
    {
        $result=$parameters ? pg_query_params($this->context->connection,$sql,$parameters) : pg_query($this->context->connection,$sql);
        if ($result===false) { throw new RuntimeException('Sales query failed'); }
        return pg_fetch_all($result) ?: [];
    }

    /** @return void Bound PostgreSQL mutation inside the caller transaction. */
    private function execute(string $sql,array $parameters=[]): void { $this->rows($sql,$parameters); }

    /** @return string Money with exactly two decimals and no negative zero. */
    private static function money(float $value): string { return number_format(abs($value)<0.005 ? 0 : $value,2,'.',''); }

    /** @return string Preserve the legacy discount's five decimals, with at least two decimal places. */
    private static function percentage(float $value): string
    {
        $decimal=rtrim(number_format($value,5,'.',''),'0');
        $places=strlen(substr($decimal,strpos($decimal,'.')+1));
        return $decimal.str_repeat('0',max(0,2-$places));
    }
}
