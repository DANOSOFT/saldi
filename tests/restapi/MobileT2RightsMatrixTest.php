<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/MobileT2RightsMatrixTest.php --- ver 5.0.0 --- 2026.10.05
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/support/T2Seed.php';

final class MobileT2RightsMatrixTest extends TestCase
{
    private static ?T2Seed $seed = null;
    private static array $contractCases = [];
    private static array $personaResponses = [];

    public static function setUpBeforeClass(): void
    {
        if (($reason=RestApiEnv::unavailableReason()) !== null) {
            self::markTestSkipped($reason);
        }
        self::$seed = new T2Seed();
        self::$seed->personas();
    }

    public static function tearDownAfterClass(): void
    {
        $output = getenv('SALDI_T2_CONTRACT_OUTPUT');
        if ($output && self::$contractCases) {
            file_put_contents($output,json_encode(['cases'=>self::$contractCases,'personas'=>self::$personaResponses],JSON_THROW_ON_ERROR));
        }
        self::$seed?->close();
        self::$seed = null;
    }

    /** @return iterable<string,array{string,string,string,string}> Complete supported T2 method inventory. */
    public static function operations(): iterable
    {
        $rows = [
            ['debitor/open-posts/index.php','GET','debitorrapport','read'],
            ['debitor/dunning/preview.php','GET','debitorordre','write'],
            ['debitor/dunning/index.php','GET','debitorordre','read'],
            ['debitor/dunning/index.php','POST','debitorordre','write'],
            ['debitor/dunning/send.php','POST','debitorordre','write'],
            ['debitor/customers/search.php','GET','debitorkonti','read'],
            ['debitor/customers/detail.php','GET','debitorkonti','read'],
            ['debitor/customers/create.php','POST','debitorkonti','write'],
            ['debitor/customers/update.php','PATCH','debitorkonti','write'],
            ['cvr/lookup.php','GET','debitorkonti','read'],
            ['products/search.php','GET','varer','read'],
            ['products/barcode.php','GET','varer','read'],
            ['products/changes.php','GET','varer','read'],
            ['products/detail.php','GET','varer','read'],
            ['debitor/sales/products.php','GET','debitorordre','read'],
            ['debitor/sales/customers.php','GET','debitorordre','read'],
            ['debitor/sales/quote.php','POST','debitorordre','write'],
            ['debitor/sales/index.php','GET','debitorordre','read'],
            ['debitor/sales/index.php','POST','debitorordre','write'],
            ['debitor/sales/lines.php','PUT','debitorordre','write'],
            ['debitor/sales/transition.php','POST','debitorordre','write'],
            ['debitor/sales/deliver.php','POST','debitorordre','write'],
            ['debitor/documents/pdf.php','GET','debitorordre','read'],
            ['payments/accounts.php','GET','kassekladde','read'],
            ['payments/register.php','POST','kassekladde','write'],
            ['payments/options.php','GET','debitorrapport','read'],
            ['payments/links.php','GET','debitorrapport','read'],
            ['payments/links.php','POST','debitorordre','write'],
            ['payments/links.php','DELETE','debitorordre','write'],
        ];
        foreach ($rows as $row) {
            yield $row[0].' '.$row[1] => $row;
        }
    }

    /** @return array{string,?array,list<string>} Prepare valid input, independently for each authorized principal. */
    private function scenario(string $file,string $method): array
    {
        $s = self::$seed;
        $f = $s->sale();
        $path = $file;
        $body = null;
        $headers = [];
        if (str_starts_with($file,'debitor/dunning/')) {
            $d = DunningFixture::seed($s->tenant);
            $path .= '?konto_id='.$d['konto_id'];
            if ($method==='POST') {
                $body = ['client_id'=>T2Seed::uuid(),'konto_id'=>$d['konto_id'],'expected_level'=>1,'deliver'=>'none'];
                if (str_ends_with($file,'send.php')) {
                    $created = $s->request('POST','debitor/dunning/index.php',$body);
                    self::assertSame(201,$created['status'],$created['body']);
                    $body = ['client_id'=>T2Seed::uuid(),'rykker_id'=>$created['json']['data']['rykker_id'],'email'=>$d['email']];
                }
            }
        } elseif ($file==='cvr/lookup.php') {
            $path .= '?cvr='.$s->cvr();
        } elseif (str_starts_with($file,'debitor/customers/')) {
            if (str_ends_with($file,'create.php')) {
                $body = ['client_id'=>T2Seed::uuid(),'name'=>'X1 customer '.bin2hex(random_bytes(4)),
                    'email'=>'x1-'.bin2hex(random_bytes(4)).'@example.invalid'];
            } elseif (str_ends_with($file,'update.php')) {
                $path .= '?id='.$f['konto_id'];
                $detail = $s->request('GET','debitor/customers/detail.php?id='.$f['konto_id']);
                self::assertSame(200,$detail['status'],$detail['body']);
                $headers[] = 'If-Match: '.$detail['json']['data']['etag'];
                $body = ['client_id'=>T2Seed::uuid(),'phone'=>'87654321'];
            } elseif (str_ends_with($file,'detail.php')) {
                $path .= '?id='.$f['konto_id'];
            } else {
                $path .= '?q=Sales';
            }
        } elseif (str_starts_with($file,'products/')) {
            if (str_ends_with($file,'detail.php')) {
                $path .= '?id='.$f['product_id'];
            } elseif (str_ends_with($file,'barcode.php')) {
                $path .= '?code=X1-'.$f['product_id'];
            }
        } elseif ($file==='debitor/documents/pdf.php' || $file==='debitor/sales/deliver.php') {
            $document = DocumentFixture::seed($s->tenant);
            if ($method==='GET') {
                $path .= '?order_id='.$document['order_id'];
            } else {
                $body = ['client_id'=>T2Seed::uuid(),'order_id'=>$document['order_id'],'channel'=>'none'];
            }
        } elseif (str_starts_with($file,'debitor/sales/')) {
            if ($file==='debitor/sales/index.php' && $method==='GET') {
                $path .= '?id='.$f['order_id'];
            } elseif ($file==='debitor/sales/lines.php' || $file==='debitor/sales/transition.php') {
                $created = $s->request('POST','debitor/sales/index.php',$s->saleBody($f));
                self::assertSame(201,$created['status'],$created['body']);
                $order = $created['json']['data'];
                $headers[] = 'If-Match: '.$order['etag'];
                if ($method==='PUT') {
                    $path .= '?order_id='.$order['id'];
                    $body = ['client_id'=>T2Seed::uuid(),'lines'=>$s->saleBody($f)['lines']];
                } else {
                    $body = ['client_id'=>T2Seed::uuid(),'order_id'=>$order['id'],'to'=>'faktura','invoice_date'=>T2Seed::today()];
                }
            } elseif ($method==='POST') {
                $body = $s->saleBody($f);
            } elseif ($file==='debitor/sales/customers.php') {
                $path .= '?id='.$f['konto_id'];
            }
        } elseif ($file==='debitor/open-posts/index.php' || str_starts_with($file,'payments/')) {
            $post = $s->invoice();
            if ($file==='debitor/open-posts/index.php') {
                $path .= '?id='.$post;
            } elseif ($file==='payments/options.php') {
                $path .= '?openpost_id='.$post;
            } elseif ($file==='payments/register.php') {
                $body = ['client_id'=>T2Seed::uuid(),'openpost_id'=>$post,'amount'=>'1000.00','method'=>'bank','date'=>'2026-03-15'];
            } elseif ($file==='payments/links.php') {
                $body = ['client_id'=>T2Seed::uuid(),'openpost_id'=>$post,'provider'=>'vipps_epayment'];
                if ($method!=='POST') {
                    $created = $s->request('POST',$file,$body);
                    self::assertSame(201,$created['status'],$created['body']);
                    $path .= '?id='.$created['json']['data']['id'];
                    $body = $method==='DELETE' ? ['client_id'=>T2Seed::uuid()] : null;
                }
            }
        }
        return [$path,$body,$headers];
    }

    #[DataProvider('operations')]
    public function testEverySupportedOperation(string $file,string $method,string $module,string $action): void
    {
        [$path,$body,$headers] = $this->scenario($file,$method);
        $anonymous = T2Seed::http($method,$path,$body,null,$headers);
        self::assertSame(401,$anonymous['status'],$anonymous['body']);
        self::assertFalse($anonymous['json']['success']);
        self::assertArrayHasKey('x-request-id',$anonymous['headers']);
        foreach (['receipt','unrelated'] as $persona) {
            $this->denied(self::$seed->request($method,$path,$body,$persona,$headers),403,'permission_denied');
        }
        $readOnly = self::$seed->request($method,$path,$body,$module.'2',$headers);
        if ($action==='write') {
            $this->denied($readOnly,403,'permission_denied');
        } else {
            $this->allowed($readOnly,$file);
        }
        foreach ([$module.'1','registry'] as $persona) {
            [$path,$body,$headers] = $this->scenario($file,$method);
            $response = self::$seed->request($method,$path,$body,$persona,$headers);
            $this->allowed($response,$file);
            if ($action==='write' && $file!=='debitor/sales/quote.php' && $file!=='debitor/dunning/preview.php') {
                $again = self::$seed->request($method,$path,$body,$persona,$headers);
                self::assertSame($response['status'],$again['status'],$again['body']);
                self::assertSame($response['json'],$again['json']);
                self::assertSame('true',$again['headers']['idempotent-replayed'] ?? null);
                $changed = $body;
                $changed['x1_changed_body'] = true;
                $conflict = self::$seed->request($method,$path,$changed,$persona,$headers);
                $this->denied($conflict,409,'conflict');
                self::assertSame('client_id_reused',$conflict['json']['data']['details']['reason']);
            }
        }
    }

    private function denied(array $r,int $status,string $code): void
    {
        self::assertSame($status,$r['status'],$r['body']);
        self::assertFalse($r['json']['success']);
        self::assertSame($code,$r['json']['data']['error']);
        self::assertArrayHasKey('x-request-id',$r['headers']);
    }

    private function allowed(array $r,string $file): void
    {
        self::assertGreaterThanOrEqual(200,$r['status'],$r['body']);
        self::assertLessThan(300,$r['status'],$r['body']);
        self::assertArrayHasKey('x-request-id',$r['headers']);
        if ($file==='debitor/documents/pdf.php') {
            self::assertStringStartsWith('%PDF-',$r['body']);
            self::assertStringContainsString('application/pdf',$r['headers']['content-type']);
        } else {
            self::assertIsArray($r['json'],$r['body']);
            self::assertTrue($r['json']['success']);
            $file = explode('?',$file)[0];
            $schema = match ($file) {
                'debitor/open-posts/index.php' => isset($r['json']['data']['items']) ? 'open-posts' : 'open-posts-detail',
                'debitor/dunning/preview.php' => 'dunning-preview',
                'debitor/dunning/index.php' => isset($r['json']['data']['items']) ? 'dunning-history' : 'dunning-created',
                'debitor/dunning/send.php' => 'dunning-send',
                'debitor/customers/create.php' => 'customers-created',
                'debitor/sales/products.php' => 'sales-products',
                'debitor/sales/customers.php' => isset($r['json']['data']['items']) ? 'sales-customers' : 'sales-customer',
                'debitor/sales/quote.php', 'quote' => 'sales-quote',
                'debitor/sales/index.php' => isset($r['json']['data']['items']) ? 'sales-orders' : 'sales-order',
                'debitor/sales/lines.php' => 'sales-lines',
                'debitor/sales/transition.php', 'transition' => 'sales-transition',
                'debitor/sales/deliver.php', 'deliver' => 'sales-deliver',
                'payments/accounts.php' => 'payments-accounts',
                'payments/register.php' => 'payments-register',
                'payments/options.php' => 'payments-options',
                'payments/links.php' => isset($r['json']['data']['openpost_id']) ? 'payments-link-status' : 'payments-link',
                'cvr/lookup.php' => 'cvr',
                'auth/me.php' => 'me',
                default => str_replace(['debitor/','products/','customers/','.php'],['','products-','customers-',''],$file),
            };
            if (in_array($schema,['create','customer'],true)) {
                $schema = $schema==='customer' ? 'customers-detail' : (isset($r['json']['data']['totals']) ? 'sales-created' : 'customers-created');
            }
            self::$contractCases[] = ['schema'=>$schema,'response'=>$r['json']];
        }
    }

    public function testPersonaNormalFlowsUseOnlyPermittedEndpoints(): void
    {
        $s = self::$seed;
        $f = $s->sale();
        foreach (['debitorordre','debitorkonti','varer','debitorrapport'] as $module) {
            $me = $s->request('GET','auth/me.php',null,$module.'1');
            $this->allowed($me,'auth/me.php');
            self::$personaResponses[$module] = $me['json'];
            foreach (T2Seed::MODULES as $name=>$offset) {
                self::assertSame($name===$module,$me['json']['data']['permissions'][$name]['read']);
                self::assertSame($name===$module,$me['json']['data']['permissions'][$name]['write']);
            }
        }
        foreach (['debitor/sales/customers.php?id='.$f['konto_id'], 'debitor/sales/products.php?q=SALES'] as $path) {
            $r = $s->request('GET',$path,null,'debitorordre1');
            $this->allowed($r,$path);
            self::assertStringNotContainsString('saldo',json_encode($r['json']['data']));
            self::assertStringNotContainsString('cost_price',json_encode($r['json']['data']));
        }
        $body = $s->saleBody($f);
        $quote = $s->request('POST','debitor/sales/quote.php',$body,'debitorordre1');
        $this->allowed($quote,'quote');
        $create = $s->request('POST','debitor/sales/index.php',$body,'debitorordre1');
        $this->allowed($create,'create');
        self::assertSame($quote['json']['data']['totals'],$create['json']['data']['totals']);
        $order = $create['json']['data'];
        $invoice = $s->request('POST','debitor/sales/transition.php',['client_id'=>T2Seed::uuid(),'order_id'=>$order['id'],
            'to'=>'faktura','invoice_date'=>T2Seed::today()],'debitorordre1',['If-Match: '.$order['etag']]);
        $this->allowed($invoice,'transition');
        $this->allowed($s->request('POST','debitor/sales/deliver.php',['client_id'=>T2Seed::uuid(),'order_id'=>$order['id'],
            'channel'=>'none'],'debitorordre1'),'deliver');
        $customer = $s->request('POST','debitor/customers/create.php',['client_id'=>T2Seed::uuid(),'name'=>'X1 normal customer'], 'debitorkonti1');
        $this->allowed($customer,'create');
        $this->allowed($s->request('GET','debitor/customers/detail.php?id='.$customer['json']['data']['id'],null,'debitorkonti1'),'customer');
        foreach (['products/search.php?q=SALES','products/barcode.php?code=X1-'.$f['product_id'],
            'products/detail.php?id='.$f['product_id'],'products/changes.php'] as $path) {
            $this->allowed($s->request('GET',$path,null,'varer1'),$path);
        }
        $post = $s->invoice();
        foreach (['debitor/open-posts/index.php','debitor/open-posts/index.php?id='.$post,'payments/options.php?openpost_id='.$post] as $path) {
            $this->allowed($s->request('GET',$path,null,'debitorrapport1'),$path);
        }
        // Capture every chooser mode and list/detail shape for the real-response mobile contract gate.
        foreach (['debitor/sales/index.php','debitor/sales/customers.php','debitor/customers/search.php',
            'debitor/customers/detail.php?id='.$f['konto_id']] as $path) {
            $this->allowed($s->request('GET',$path),$path);
        }
        foreach (['barcode'=>'code=X1-'.$f['product_id'],'changes'=>''] as $mode=>$query) {
            $r = $s->request('GET','debitor/sales/products.php?mode='.$mode.'&'.$query);
            $this->allowed($r,'debitor/sales/products.php');
            self::$contractCases[array_key_last(self::$contractCases)]['schema']='sales-products-'.$mode;
        }
        foreach (glob(__DIR__.'/fixtures/t2/*.json') as $file) {
            $fixture = json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            if (!array_key_exists('success',$fixture)) {
                // Domain account seeds are exercised by OpenPostsEndpointTest, rather than treated as envelopes.
                self::assertSame('open-posts-accounts.json',basename($file));
                continue;
            }
            $schema = basename($file,'.json');
            if ($schema==='open-posts-list') {
                $schema='open-posts';
            }
            $candidates = array_values(array_filter(self::$contractCases,static fn(array $case): bool=>$case['schema']===$schema));
            self::assertNotEmpty($candidates,'No real PHP response for '.basename($file));
            $differences = array_map(fn(array $case): array=>$this->fixtureDifferences($fixture,$case['response']),$candidates);
            self::assertContains([],$differences,'Published fixture shape differs from real PHP: '.basename($file).json_encode($differences));
        }
    }

    /** @return list<string> Fixture ids/values/cardinalities vary; compare object fields and JSON scalar types. */
    private function fixtureDifferences($expected,$actual,string $path=''): array
    {
        // Nullable values are checked by the unchanged mobile schemas in T2SchemaContract.test.tsx.
        if ($expected===null || $actual===null) {
            return [];
        }
        if (get_debug_type($expected)!==get_debug_type($actual)) {
            return [$path.': expected '.get_debug_type($expected).', received '.get_debug_type($actual)];
        }
        if (!is_array($expected)) {
            return [];
        }
        if (array_is_list($expected)) {
            if (!$expected || !$actual) {
                return [];
            }
            return $this->fixtureDifferences($expected[0],$actual[0],$path.'[]');
        }
        if ($path==='.data.accounts') {
            return []; // Customer ids key this dynamic reconciliation map.
        }
        $differences=[];
        foreach ($expected as $key=>$value) {
            if (!array_key_exists($key,$actual)) {
                $differences[]=$path.'.'.$key.': missing';
            } else {
                $differences=array_merge($differences,$this->fixtureDifferences($value,$actual[$key],$path.'.'.$key));
            }
        }
        return $differences;
    }

    public function testAllLiveSettlementAllocatorsUseTheSharedHelper(): void
    {
        $root = dirname(__DIR__,2);
        $leftovers = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $relative = substr($file->getPathname(),strlen($root)+1);
            // The protected backup is exempt and is never opened. Its old MAX allocator is documented in T2 section 2.0.
            if (!$file->isFile() || $file->getExtension()!=='php' || $relative==='includes/betweenUpdates2.php'
                || $relative==='includes/openpost/udlignId.php' || preg_match('~^(tests|vendor|mobile|\\.git|\\.codex-run)/~',$relative)) {
                continue;
            }
            if (preg_match('/max\\s*\\(\\s*udlign_id\\s*\\)/i',file_get_contents($file->getPathname()))) {
                $leftovers[] = $relative;
            }
        }
        self::assertSame([],$leftovers);
    }
}
